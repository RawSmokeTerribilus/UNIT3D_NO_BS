"""Ficha completa de un usuario: todo lo que se sabe de alguien, de una vez.

En moderación es la pregunta que más veces se hace, y hasta ahora había que
lanzar seis o siete consultas y juntarlas a mano.

Mismo patrón que `query/ipfind.py`: bloques, y cada uno diciendo HASTA DÓNDE
ALCANZA. Un «no aparece» de una fuente de 7 días no significa lo mismo que uno
de una tabla que guarda desde siempre.
"""
import os
import sqlite3
import time

from config import cfg
from query.sources.ipunion import IpUnion
from query.sources.mysql import MySQLSource


class UserFindError(Exception):
    pass


def buscar(texto):
    nombre = (texto or "").strip()
    if not nombre:
        raise UserFindError("hace falta un nombre de usuario")

    s = MySQLSource()
    r = s.run(
        "SELECT id, username FROM users WHERE username = %s "
        "OR LOWER(username) = LOWER(%s) ORDER BY username = %s DESC LIMIT 5",
        (nombre, nombre, nombre))
    if not r.rows:
        r = s.run("SELECT id, username FROM users WHERE username LIKE %s LIMIT 8",
                  ("%" + nombre + "%",))
        if not r.rows:
            raise UserFindError("no hay ningún usuario que se llame «%s»" % nombre)
        if len(r.rows) > 1:
            raise UserFindError(
                "«%s» no existe. ¿Alguno de éstos? %s"
                % (nombre, ", ".join(f[1] for f in r.rows)))
    uid, usuario = r.rows[0][0], r.rows[0][1]

    ficha = {"id": uid, "usuario": usuario, "bloques": []}
    for fn in (_identidad, _ratio, _sanciones, _actividad, _ips,
               _coincidencias, _invitaciones, _telegram):
        try:
            b = fn(s, uid, usuario)
        except Exception as e:
            b = {"bloque": fn.__name__.strip("_"),
                 "error": "%s: %s" % (type(e).__name__, e)}
        if b:
            ficha["bloques"].append(b)
    ficha["veredicto"] = _veredicto(ficha)
    return ficha


def _campos(s, uid, ids):
    """Pide campos del modelo por su id, reusando el compilador."""
    from query.compile import compilar
    from query.modelo import cargar
    ents = cargar()
    c = compilar({"entidad": "usuarios", "mostrar": ids,
                  "condiciones": {"y": [{"campo": "id", "op": "=", "valor": uid}]}}, ents)
    r = s.run(c.sql, c.params, limit=1)
    if not r.rows:
        return {}
    return dict(zip(c.columnas, r.rows[0]))


def _identidad(s, uid, usuario):
    d = _campos(s, uid, ["id", "nombre", "correo", "grupo", "grupo_que_tocaria",
                         "en_autogroup", "desviado", "alta", "ultimo_acceso",
                         "ultima_accion", "dias_sin_actividad", "puede_descargar",
                         "es_donante", "baja"])
    return {"bloque": "identidad", "alcance": "desde siempre", "datos": d}


def _ratio(s, uid, usuario):
    d = _campos(s, uid, ["ratio", "ratio_infinito", "subido", "bajado", "bonus",
                         "bonus_sin_gastar", "bajo_ratio_minimo"])
    return {"bloque": "ratio y tráfico", "alcance": "desde siempre",
            "nota": "El ratio va redondeado a 2 como lo compara AutoGroup. Si no ha "
                    "bajado nada, el código le da ratio INFINITO y aquí sale vacío.",
            "datos": d}


def _sanciones(s, uid, usuario):
    d = _campos(s, uid, ["hitandruns", "avisos_activos", "avisos_para_ban"])
    r = s.run("SELECT w.reason, w.active, w.created_at, w.expires_on, w.deleted_at "
              "FROM warnings w WHERE w.user_id = %s ORDER BY w.created_at DESC LIMIT 10",
              (uid,))
    return {"bloque": "sanciones",
            "alcance": "umbral leído EN VIVO de la tabla settings, no del fichero",
            "datos": d,
            "hallazgos": [{"motivo": f[0], "activo": bool(f[1]), "puesto": f[2],
                           "vence": f[3], "borrado": f[4]} for f in r.rows]}


def _actividad(s, uid, usuario):
    d = _campos(s, uid, ["nunca_ha_descargado", "descargas_max_dia", "seedtime_medio",
                         "seedtime_bajo_minimo", "pico_descarga", "pico_subida",
                         "velocidad_descarga", "velocidad_subida", "tiene_cliente",
                         "clientes_activos", "puerto_cerrado", "usa_api",
                         "torrents_subidos", "volumen_subido",
                         "dias_hasta_primera_subida"])
    r = s.run("SELECT COUNT(*), SUM(h.seeder = 1), SUM(h.active = 1), SUM(h.hitrun = 1) "
              "FROM history h WHERE h.user_id = %s AND h.deleted_at IS NULL", (uid,))
    n, semilla, activos, hr = (r.rows[0] if r.rows else (0, 0, 0, 0))
    d["Registros de historial"] = n
    d["De ellos sembrando"] = semilla
    d["Activos ahora"] = activos
    d["Marcados hit and run"] = hr
    return {"bloque": "actividad", "alcance": "desde siempre", "datos": d}


def _ips(s, uid, usuario):
    u = IpUnion()
    r = u.run("SELECT ip, procedencia, veces, primera, ultima FROM ips "
              "WHERE usuario_id = ? ORDER BY ultima DESC", (uid,), limit=200)
    return {"bloque": "ips", "alcance": u.contexto()[0],
            "hallazgos": [dict(zip(["ip", "procedencia", "veces", "primera", "ultima"], f))
                          for f in r.rows]}


def _coincidencias(s, uid, usuario):
    d = _campos(s, uid, ["comparte_correo", "comparte_ip_cliente", "ips_compartidas",
                         "comparte_seedbox", "ips_ultima_semana"])
    u = IpUnion()
    r = u.run(
        "SELECT b.usuario, a.ip, a.procedencia, b.procedencia FROM ips a "
        "JOIN ips b ON b.ip = a.ip AND b.usuario_id <> a.usuario_id "
        "WHERE a.usuario_id = ? AND b.usuario IS NOT NULL "
        "GROUP BY b.usuario, a.ip, a.procedencia, b.procedencia", (uid,), limit=100)
    return {"bloque": "coincidencias",
            "alcance": "las cinco procedencias de IP a la vez",
            "nota": "Compartir IP NO prueba nada: CGNAT, piso compartido, VPN y "
                    "proveedor de seedbox dan exactamente esta señal.",
            "datos": d,
            "hallazgos": [{"con": f[0], "ip": f[1], "él por": f[2], "el otro por": f[3]}
                          for f in r.rows]}


def _invitaciones(s, uid, usuario):
    d = _campos(s, uid, ["invitado_por", "invitados", "invites_sin_usar"])
    r = s.run(
        "SELECT u.username, u.created_at, "
        "  (SELECT COUNT(*) FROM history h WHERE h.user_id = u.id) AS act "
        "FROM invites i JOIN users u ON u.id = i.accepted_by "
        "WHERE i.user_id = %s ORDER BY i.accepted_at DESC LIMIT 30", (uid,))
    fam = s.run("SELECT raiz, nivel FROM (%s) AS f WHERE usuario_id = %%s" %
                _sub_familias(), (uid,))
    if fam.rows:
        d["Raíz de su familia"] = fam.rows[0][0]
        d["Nivel"] = fam.rows[0][1]
    return {"bloque": "invitaciones",
            "alcance": "el árbol dibujado está en /users/%s/invite-tree del tracker" % usuario,
            "datos": d,
            "hallazgos": [{"invitado": f[0], "alta": f[1],
                           "arrancó": "sí" if f[2] else "NO"} for f in r.rows]}


def _sub_familias():
    from query.modelo import cargar
    return cargar()["familias"].subconsulta


def _telegram(s, uid, usuario):
    d = _campos(s, uid, ["tg_vinculado", "tg_pendiente", "tg_en_grupo",
                         "tg_usuario", "tg_alta_grupo", "tfa_confirmado"])
    return {"bloque": "telegram y 2FA", "alcance": "desde siempre", "datos": d}


def _veredicto(ficha):
    por = {b.get("bloque"): b for b in ficha["bloques"]}
    d = lambda b, k, x=None: (por.get(b, {}).get("datos") or {}).get(k, x)
    partes = ["%s (id %s)" % (ficha["usuario"], ficha["id"])]

    grupo, tocaria = d("identidad", "Grupo"), d("identidad", "Grupo que le tocaría")
    if grupo:
        partes.append("está en %s%s." % (
            grupo, " pero le tocaría %s" % tocaria if tocaria and tocaria != grupo else ""))
    ratio = d("ratio y tráfico", "Ratio")
    if ratio is not None:
        partes.append("Ratio %s%s." % (ratio, ", por debajo del mínimo"
                                       if d("ratio y tráfico", "Por debajo del ratio mínimo") else ""))
    hr = d("sanciones", "Hit and runs") or 0
    faltan = d("sanciones", "Avisos que le faltan para el ban")
    if hr:
        partes.append("%s hit and run(s); le faltan %s avisos para el ban." % (hr, faltan))
    if d("actividad", "Nunca ha descargado ni sembrado"):
        partes.append("NUNCA ha descargado ni sembrado.")
    dias = d("identidad", "Días sin actividad")
    if dias is not None and int(dias or 0) > 60:
        partes.append("Lleva %s días sin aparecer." % dias)
    coincide = (por.get("coincidencias", {}).get("hallazgos") or [])
    if coincide:
        quienes = sorted({c["con"] for c in coincide})
        partes.append("Comparte IP con %s. OJO: eso no prueba nada por sí solo."
                      % ", ".join(quienes[:6]))
    if d("actividad", "Puerto cerrado"):
        partes.append("Tiene el puerto cerrado.")
    return " ".join(partes)
