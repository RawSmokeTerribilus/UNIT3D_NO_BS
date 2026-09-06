"""Buscar una IP en TODAS partes.

Nace de una investigación a mano: para saber quién era una IP hubo que mirar en
seis sitios distintos, uno por uno. Eso debería ser un botón.

Fuentes que interroga, en este orden:
  1. geolocalización (GeoLite de MaxMind, en local, sin salir a Internet)
  2. MySQL — conexiones de cliente, logins fallidos, seedboxes, IPs bloqueadas
  3. el almacén de IPs recolectadas de los logs
  4. Loki — 7 días de nginx: cuántas peticiones, qué códigos, qué rutas
  5. CrowdSec — alertas y decisiones, leídas de su propia base
  6. un veredicto legible que junta todo lo anterior

Cada bloque dice SIEMPRE hasta dónde alcanza. Un «sin coincidencias» de una
fuente que sólo guarda 7 días no significa lo mismo que uno de una tabla que
guarda desde siempre, y confundirlos es como se sacan conclusiones falsas.
"""
import ipaddress
import json
import os
import re
import sqlite3
import time
import urllib.parse
from collections import Counter

from config import cfg
from query.sources.http_json import pedir
from query.sources.mysql import MySQLSource

RUTA_GEO = os.environ.get("AUDITOR_GEOIP_DIR", "/geoip")
RUTA_CROWDSEC = os.environ.get("AUDITOR_CROWDSEC_DB", "/crowdsec/crowdsec.db")

_LINEA = re.compile(
    r'^(\S+) \S+ \S+ \[([^\]]+)\] "(?:(\S+) (\S+)[^"]*)" (\d{3}) (\d+) "([^"]*)" "([^"]*)"')


class IpFindError(Exception):
    pass


def normalizar(texto):
    try:
        return str(ipaddress.ip_address((texto or "").strip()))
    except ValueError:
        raise IpFindError("«%s» no es una dirección IP válida" % texto)


def buscar(ip_texto, horas_log=168):
    ip = normalizar(ip_texto)
    informe = {"ip": ip, "bloques": []}
    for fn in (_geo, _mysql, _almacen, _loki, _crowdsec):
        try:
            b = fn(ip, horas_log)
        except Exception as e:  # una fuente caída no tumba el informe entero
            b = {"fuente": fn.__name__.strip("_"), "error": "%s: %s" % (type(e).__name__, e)}
        if b:
            informe["bloques"].append(b)
    informe["veredicto"] = _veredicto(informe)
    return informe


# --------------------------------------------------------------------- fuentes
def _geo(ip, _h):
    try:
        import maxminddb
    except ImportError:
        return {"fuente": "geo", "alcance": "—", "nota": "falta el módulo maxminddb"}
    d = {"fuente": "geo", "alcance": "base local de MaxMind, sin salir a Internet",
         "datos": {}}
    for fichero, campos in (("GeoLite2-ASN.mmdb", None), ("GeoLite2-City.mmdb", None)):
        ruta = os.path.join(RUTA_GEO, fichero)
        if not os.path.exists(ruta):
            continue
        with maxminddb.open_database(ruta) as r:
            v = r.get(ip) or {}
        if "ASN" in fichero:
            d["datos"]["asn"] = v.get("autonomous_system_number")
            d["datos"]["operador"] = v.get("autonomous_system_organization")
        else:
            d["datos"]["pais"] = (v.get("country") or {}).get("iso_code")
            d["datos"]["ciudad"] = ((v.get("city") or {}).get("names") or {}).get("es") \
                or ((v.get("city") or {}).get("names") or {}).get("en")
    return d


def _mysql(ip, _h):
    s = MySQLSource()
    hallazgos = []

    r = s.run("SELECT COUNT(*), COUNT(DISTINCT p.user_id), "
              "GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', '), "
              "MIN(p.created_at), MAX(p.updated_at) "
              "FROM peers p LEFT JOIN users u ON u.id = p.user_id "
              "WHERE INET6_NTOA(p.ip) = %s", (ip,))
    n, nu, quienes, prim, ult = r.rows[0]
    if n:
        hallazgos.append({"donde": "Conexiones de cliente (peers)", "cuantos": n,
                          "usuarios": quienes, "primera": prim, "ultima": ult})

    r = s.run("SELECT COUNT(*), GROUP_CONCAT(DISTINCT username SEPARATOR ', '), "
              "MIN(created_at), MAX(created_at) "
              "FROM failed_login_attempts WHERE ip_address = %s", (ip,))
    n, quienes, prim, ult = r.rows[0]
    if n:
        hallazgos.append({"donde": "Logins fallidos", "cuantos": n,
                          "usuarios": quienes, "primera": prim, "ultima": ult})

    r = s.run("SELECT COUNT(*), GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') "
              "FROM seedboxes sb LEFT JOIN users u ON u.id = sb.user_id "
              "WHERE sb.ip = %s", (ip,))
    if r.rows[0][0]:
        hallazgos.append({"donde": "Seedbox declarada", "cuantos": r.rows[0][0],
                          "usuarios": r.rows[0][1]})

    r = s.run("SELECT COUNT(*) FROM blocked_ips WHERE ip_address = %s", (ip,))
    if r.rows[0][0]:
        hallazgos.append({"donde": "Lista de IPs bloqueadas del tracker",
                          "cuantos": r.rows[0][0]})

    return {"fuente": "mysql", "alcance": "desde siempre (peers, desde 2026-04)",
            "hallazgos": hallazgos}


def _almacen(ip, _h):
    ruta = os.path.join(cfg.run_dir, "ips.sqlite")
    if not os.path.exists(ruta):
        return {"fuente": "ips_web", "alcance": "vacío", "hallazgos": [],
                "nota": "no se ha recolectado nunca"}
    con = sqlite3.connect("file:%s?mode=ro" % ruta, uri=True)
    try:
        filas = con.execute(
            "SELECT usuario, via, veces, primera, ultima FROM vistas WHERE ip=? "
            "ORDER BY veces DESC", (ip,)).fetchall()
        cobertura = con.execute("SELECT MIN(primera), MAX(ultima) FROM vistas").fetchone()
    finally:
        con.close()
    return {"fuente": "ips_web",
            "alcance": "recolectado de los logs: %s a %s" % (cobertura or ("—", "—")),
            "hallazgos": [{"usuario": f[0], "via": f[1], "peticiones": f[2],
                           "primera": f[3], "ultima": f[4]} for f in filas]}


def _loki(ip, horas):
    horas = min(float(horas), cfg.retention_days["loki"] * 24)
    fin = int(time.time() * 1e9)
    ini = fin - int(horas * 3600 * 1e9)
    d = pedir("loki", cfg.loki_url, "/loki/api/v1/query_range",
              {"query": '{job="unit3d_nginx"} |= "%s"' % ip,
               "start": str(ini), "end": str(fin), "limit": "5000",
               "direction": "backward"}, timeout=60)
    lineas = [l for f in d.get("data", {}).get("result", []) for _t, l in f.get("values", [])]
    if not lineas:
        return {"fuente": "loki", "alcance": "%d días de nginx" % (horas / 24),
                "peticiones": 0, "hallazgos": []}

    est, rutas, agentes = Counter(), Counter(), Counter()
    primera = ultima = None
    for l in lineas:
        m = _LINEA.match(l)
        if not m:
            continue
        cuando, ruta, estado, agente = m.group(2), m.group(4), m.group(5), m.group(8)
        rutas[ruta.split("?")[0]] += 1
        est[estado] += 1
        agentes[agente[:70]] += 1
        primera = min(primera or cuando, cuando)
        ultima = max(ultima or cuando, cuando)
    return {"fuente": "loki",
            "alcance": "%d días de nginx (Loki no guarda más)" % (horas / 24),
            "peticiones": len(lineas),
            "primera": lineas[-1][:0] or primera, "ultima": ultima,
            "truncado": len(lineas) >= 5000,
            "estados": dict(est.most_common(8)),
            "rutas": est and [{"ruta": r, "veces": n} for r, n in rutas.most_common(10)],
            "agentes": [{"agente": a, "veces": n} for a, n in agentes.most_common(3)]}


def _crowdsec(ip, _h):
    if not os.path.exists(RUTA_CROWDSEC):
        return {"fuente": "crowdsec", "alcance": "—",
                "nota": "no se puede leer la base de CrowdSec"}
    con = sqlite3.connect("file:%s?mode=ro" % RUTA_CROWDSEC, uri=True)
    try:
        alertas = con.execute(
            "SELECT scenario, events_count, started_at, source_country, source_as_name "
            "FROM alerts WHERE source_ip = ? ORDER BY started_at DESC LIMIT 20",
            (ip,)).fetchall()
        decisiones = con.execute(
            "SELECT type, scenario, until FROM decisions WHERE value = ? "
            "ORDER BY until DESC LIMIT 10", (ip,)).fetchall()
        total = con.execute("SELECT COUNT(*) FROM alerts").fetchone()[0]
    finally:
        con.close()
    return {"fuente": "crowdsec",
            "alcance": "%d alertas en la base de CrowdSec" % total,
            "alertas": [{"escenario": a[0], "eventos": a[1], "cuando": a[2],
                         "pais": a[3], "operador": a[4]} for a in alertas],
            "decisiones": [{"tipo": d[0], "motivo": d[1], "hasta": d[2]}
                           for d in decisiones]}


# -------------------------------------------------------------------- veredicto
def _veredicto(informe):
    """Una frase que junta lo anterior. No sustituye a mirar; orienta."""
    por = {b.get("fuente"): b for b in informe["bloques"]}
    señales = []

    usuarios = set()
    for h in (por.get("mysql", {}).get("hallazgos") or []):
        if h.get("usuarios"):
            usuarios.update(u.strip() for u in str(h["usuarios"]).split(","))
        señales.append(h["donde"].lower())
    for h in (por.get("ips_web", {}).get("hallazgos") or []):
        usuarios.add(h["usuario"])

    # `peers` da el nombre real y `failed_login_attempts` guarda lo que se
    # tecleó al entrar: «Raulgg21» y «raulgg21» son el mismo. Se deduplica sin
    # distinguir mayúsculas, quedándose con la primera grafía vista.
    canon = {}
    for u in usuarios:
        u = (u or "").strip()
        if u and u != "None":
            canon.setdefault(u.lower(), u)
    usuarios = set(canon.values())
    cs = por.get("crowdsec", {})
    peticiones = por.get("loki", {}).get("peticiones", 0)

    partes = []
    if usuarios:
        partes.append("Es de %s: %s." % (
            "un miembro" if len(usuarios) == 1 else "varios miembros",
            ", ".join(sorted(usuarios)[:8])))
    elif peticiones:
        partes.append("No casa con ningún miembro; sólo aparece en los logs de nginx.")
    else:
        partes.append("No aparece en ninguna fuente.")

    if cs.get("alertas"):
        partes.append("CrowdSec la ha señalado %d vez(ces): %s." % (
            len(cs["alertas"]),
            ", ".join(sorted({a["escenario"] for a in cs["alertas"]}))))
    if cs.get("decisiones"):
        partes.append("Tiene %d decisión(es) registradas." % len(cs["decisiones"]))
    if usuarios and cs.get("alertas"):
        partes.append("OJO: es un miembro Y está señalada — mirar si es un falso "
                      "positivo antes de actuar.")
    if peticiones:
        partes.append("%d peticiones en la ventana de logs." % peticiones)
    return " ".join(partes)
