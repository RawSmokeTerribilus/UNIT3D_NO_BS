#!/usr/bin/env python3
"""Panel de consultas para auditorías — servidor.

Corre dentro del contenedor unit3d-auditor. NO monta docker.sock y NO ejecuta
docker: sólo lee MySQL, Loki y Prometheus por red, y los binlogs en :ro.
"""
import json
import os
import sys
import traceback
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

from config import cfg
from query import archive
from query.compile import (OPERADORES, CompileError, compilar, compilar_binlog,
                           compilar_loki, compilar_prom)
from query.guard import GuardError, revisar
from query.modelo import ModeloError, cargar
from query.sources.binlog import BinlogError, BinlogSource
from query.ipfind import IpFindError, buscar as buscar_ip
from query.sources.http_json import FuenteHTTPError
from query.sources.ipstore import IpStore, IpStoreError
from query.sources.loki import LokiSource
from query.sources.mysql import MySQLError, MySQLSource
from query.sources.prom import PromSource

DIR_ESTATICOS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "static")
# Dos sitios a propósito: lo SEMBRADO viaja en git dentro de la imagen y es de
# sólo lectura; lo que guarda el operador es dato local y va al volumen. Se leen
# los dos, se escribe siempre en el segundo.
DIR_SEMBRADAS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "query", "saved")
DIR_GUARDADAS = os.path.join(cfg.run_dir, "saved")

TIPOS = {".html": "text/html; charset=utf-8", ".js": "application/javascript; charset=utf-8",
         ".css": "text/css; charset=utf-8", ".svg": "image/svg+xml"}


def validar_auth():
    """Arranque que falla cerrado.

    El panel lee la base de producción. Dentro del contenedor el bind es
    0.0.0.0 por necesidad, así que quien declara la exposición real es
    AUDITOR_PUBLIC. Con exposición y sin autenticación, no arranca.
    """
    publico = os.environ.get("AUDITOR_PUBLIC", "").strip().lower() in ("1", "true", "yes")
    if publico and cfg.auth_mode == "none":
        sys.exit("NEGADO: AUDITOR_PUBLIC=1 con AUTH_MODE=none. El panel lee la "
                 "base de producción; no se expone sin autenticación.")
    if cfg.auth_mode == "cf-access" and not (cfg.cf_team and cfg.cf_aud and cfg.allowed_emails):
        sys.exit("NEGADO: AUTH_MODE=cf-access exige CF_ACCESS_TEAM, CF_ACCESS_AUD "
                 "y ALLOWED_EMAILS.")


class Handler(BaseHTTPRequestHandler):
    server_version = "unit3d-auditor"
    protocol_version = "HTTP/1.1"

    def log_message(self, fmt, *args):
        sys.stderr.write("%s %s\n" % (self.address_string(), fmt % args))

    # ---------------------------------------------------------------- salida
    def _json(self, obj, code=200):
        cuerpo = json.dumps(obj, ensure_ascii=False, default=str).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(cuerpo)))
        self.end_headers()
        self.wfile.write(cuerpo)

    def _estatico(self, rel):
        rel = rel.lstrip("/") or "index.html"
        ruta = os.path.normpath(os.path.join(DIR_ESTATICOS, rel))
        if not ruta.startswith(DIR_ESTATICOS) or not os.path.isfile(ruta):
            return self._json({"error": "no encontrado"}, 404)
        with open(ruta, "rb") as fh:
            cuerpo = fh.read()
        self.send_response(200)
        self.send_header("Content-Type", TIPOS.get(os.path.splitext(ruta)[1], "application/octet-stream"))
        self.send_header("Content-Length", str(len(cuerpo)))
        self.end_headers()
        self.wfile.write(cuerpo)

    def _identidad(self):
        if cfg.auth_mode == "cf-access":
            return self.headers.get("Cf-Access-Authenticated-User-Email", "desconocido")
        return "local"

    def _origen(self):
        """De dónde vino la ejecución.

        Sin esto el historial mezcla lo que se lanza desde la página con lo que
        se lanza por API o desde la terminal, y todo se ve igual.
        """
        o = (self.headers.get("X-Panel-Origen") or "").strip().lower()
        return o if o in ("panel", "cli") else "api"

    # ------------------------------------------------------------------ GET
    def do_GET(self):
        u = urlparse(self.path)
        ruta, q = u.path, parse_qs(u.query)
        try:
            if ruta == "/api/health":
                return self._json({"ok": True, "servicio": "unit3d-auditor"})
            if ruta == "/api/selftest":
                return self._json(_selftest())
            if ruta == "/api/query/model":
                ents = cargar()
                return self._json({
                    "entidades": [e.to_dict() for e in ents.values()],
                    "operadores": OPERADORES,
                    "retencion": cfg.retention_days,
                    "topes": {"filas": cfg.max_rows, "ms": cfg.max_exec_ms,
                              "claves_enlace": cfg.max_link_keys},
                })
            if ruta == "/api/ip":
                return self._json(buscar_ip(
                    (q.get("q") or [""])[0],
                    horas_log=float((q.get("horas") or [168])[0])))
            if ruta == "/api/ips/estado":
                return self._json({"contexto": IpStore().contexto()})
            if ruta == "/api/query/valores":
                return self._json(_valores_catalogo(
                    (q.get("entidad") or [""])[0], (q.get("campo") or [""])[0]))
            if ruta == "/api/query/metricas":
                return self._json({"metricas": PromSource().metricas(
                    (q.get("q") or [""])[0])[:500]})
            if ruta == "/api/query/saved":
                return self._json({"guardadas": _listar_guardadas()})
            if ruta == "/api/query/history":
                return self._json({"ejecuciones": archive.historial(
                    guardada=(q.get("saved") or [None])[0],
                    desde=(q.get("from") or [None])[0],
                    hasta=(q.get("to") or [None])[0],
                    limite=int((q.get("limit") or [200])[0]))})
            if ruta == "/api/query/series":
                nombre = (q.get("saved") or [None])[0]
                if not nombre:
                    return self._json({"error": "falta el parámetro saved"}, 400)
                return self._json({"serie": archive.serie(nombre)})
            if ruta.startswith("/api/query/run/"):
                d = archive.leer(ruta.rsplit("/", 1)[-1])
                return self._json(d if d else {"error": "no encontrada"}, 200 if d else 404)
            if ruta.startswith("/api/"):
                return self._json({"error": "ruta desconocida: %s" % ruta}, 404)
            return self._estatico(ruta)
        except Exception as e:
            return self._error(e)

    # ----------------------------------------------------------------- POST
    def do_POST(self):
        ruta = urlparse(self.path).path
        try:
            cuerpo = self._leer_json()
            if ruta == "/api/query/compile":
                return self._json(_compilar(cuerpo))
            if ruta == "/api/query/run":
                return self._json(_ejecutar(cuerpo, self._identidad(), self._origen()))
            if ruta == "/api/query/saved":
                return self._json(_guardar(cuerpo, self._identidad()))
            if ruta == "/api/ips/recolectar":
                return self._json(IpStore().recolectar(
                    horas=float(cuerpo.get("horas") or 6)))
            if ruta == "/api/query/saved/borrar":
                return self._json(_borrar_guardada(cuerpo.get("nombre")))
            return self._json({"error": "ruta desconocida: %s" % ruta}, 404)
        except Exception as e:
            return self._error(e)

    def _leer_json(self):
        n = int(self.headers.get("Content-Length") or 0)
        if n <= 0:
            return {}
        return json.loads(self.rfile.read(n).decode("utf-8"))

    def _error(self, e):
        """Los errores suben enteros. Nada de cero filas fingidas."""
        if isinstance(e, MySQLError):
            return self._json({"error": "mysql", "codigo": e.code,
                               "mensaje": e.message, "sql": e.sql}, 502)
        if isinstance(e, IpFindError):
            return self._json({"error": "ip", "mensaje": str(e)}, 400)
        if isinstance(e, IpStoreError):
            return self._json({"error": "ips_web", "mensaje": str(e)}, 502)
        if isinstance(e, BinlogError):
            return self._json({"error": "binlog", "mensaje": str(e)}, 502)
        if isinstance(e, FuenteHTTPError):
            return self._json({"error": e.fuente, "mensaje": e.detalle,
                               "url": e.url}, 502)
        if isinstance(e, (CompileError, GuardError, ModeloError, ValueError)):
            return self._json({"error": type(e).__name__, "mensaje": str(e)}, 400)
        traceback.print_exc()
        return self._json({"error": type(e).__name__, "mensaje": str(e)}, 500)


# ------------------------------------------------------------------ lógica
def _compilar(cuerpo):
    """Enseña la consulta sin ejecutarla. Una cadena devuelve una por paso."""
    ents = cargar()
    pasos = cuerpo.get("pasos") or [cuerpo.get("paso") or cuerpo]
    salida = []
    for i, paso in enumerate(pasos):
        paso = dict(paso)
        enlace = paso.pop("enlace", None)
        if i > 0 and enlace:
            # Sin ejecutar no hay claves; se enseña el hueco tal cual, para que
            # se vea DÓNDE entran las del paso anterior.
            paso["_enlace_valores"] = {"campo": enlace["campo"],
                                       "valores": ["‹claves del paso %d›" % i]}
        eid = paso.get("entidad")
        fuente = ents[eid].fuente if eid in ents else "mysql"
        if fuente == "binlog":
            a = compilar_binlog(paso, ents[eid])
            salida.append({"paso": i + 1, "entidad": eid, "fuente": fuente,
                           "consulta": "mysqlbinlog · " + repr(a), "parametros": [],
                           "columnas": [], "avisos": []})
            continue
        if fuente == "loki":
            c = compilar_loki(paso, ents[eid])[0]
        elif fuente == "prom":
            c = compilar_prom(paso, ents[eid])[0]
        else:
            c = compilar(paso, ents)
        salida.append({"paso": i + 1, "entidad": eid, "fuente": fuente,
                       "consulta": c.sql, "parametros": list(c.params),
                       "columnas": c.columnas, "avisos": c.avisos})
    return {"pasos": salida, "consulta": "\n\n".join(
        "-- paso %d (%s)\n%s" % (p["paso"], p["entidad"], p["consulta"]) for p in salida),
        "parametros": [v for p in salida for v in p["parametros"]],
        "columnas": salida[-1]["columnas"],
        "avisos": [a for p in salida for a in p["avisos"]]}


def _ejecutar(cuerpo, identidad, procedencia="api"):
    """Ejecuta y archiva. También archiva lo que se RECHAZA.

    Un intento denegado —pedir un hash, escribir, tocar el esquema mysql— es
    justo lo que un registro de auditoría tiene que conservar. Si sólo se
    guardara lo que sale bien, el registro contaría media historia.
    """
    guardada = cuerpo.get("guardada")
    composicion = {"modo": "crudo", "sql": cuerpo["sql"]} if cuerpo.get("sql") else (
        cuerpo.get("paso") or cuerpo)
    try:
        return _ejecutar_inner(cuerpo, identidad, guardada, procedencia)
    except Exception as e:
        archive.registrar(None, composicion=composicion, guardada=guardada,
                          identidad=identidad, origen=procedencia,
                          error="%s: %s" % (type(e).__name__, e))
        raise


def _ejecutar_inner(cuerpo, identidad, guardada, procedencia="api"):
    origen = MySQLSource()
    limite = int(cuerpo.get("limite") or cfg.max_rows)

    if cuerpo.get("sql"):
        # modo crudo: aquí sí actúa el candado de respaldo
        sql = cuerpo["sql"]
        avisos = revisar(sql)
        r = origen.run(sql, (), limit=limite)
        r.warnings = avisos + r.warnings
        composicion = {"modo": "crudo", "sql": sql}
    else:
        ents = cargar()
        pasos = cuerpo.get("pasos") or [cuerpo.get("paso") or cuerpo]
        r, tramos = _cadena(pasos, ents, origen, limite)
        composicion = {"pasos": pasos}

    run_id = archive.registrar(r, composicion=composicion, guardada=guardada,
                               identidad=identidad, origen=procedencia)
    d = r.to_dict()
    d["run_id"] = run_id
    if not cuerpo.get("sql"):
        d["pasos"] = tramos
    return d


def _cadena(pasos, ents, origen, limite):
    """Ejecuta una cadena de pasos. Devuelve (resultado del último, resumen).

    Un paso puede consumir la clave del anterior. Cuando ambos son de MySQL se
    resuelve con una lista IN; el día que haya fuentes distintas, el cruce será
    en memoria sobre la misma forma de resultado.
    """
    resultado = None
    tramos = []
    claves = None

    for i, paso in enumerate(pasos):
        paso = dict(paso)
        enlace = paso.pop("enlace", None)
        cruce_en_memoria = None

        if i > 0 and enlace and claves is not None:
            ent_destino = ents.get(paso.get("entidad"))
            if ent_destino is None:
                raise CompileError("entidad desconocida: %r" % paso.get("entidad"))
            if ent_destino.fuente in ("mysql", "ipstore"):
                # Las dos hablan SQL: el enlace entra como lista IN.
                paso["_enlace_valores"] = {"campo": enlace["campo"], "valores": claves}
            elif ent_destino.enlace_en:
                # No habla SQL, pero su resultado trae una columna que
                # identifica la fila: se cruza en memoria después de ejecutar.
                cruce_en_memoria = (ent_destino.enlace_en, set(map(str, claves)))
            else:
                # Ni SQL ni columna que cruzar. ANTES esto se ignoraba en
                # silencio y devolvía el paso SIN FILTRAR, presentándolo como si
                # fuera el resultado del cruce. Mentir así es justo lo que este
                # panel existe para no hacer.
                raise CompileError(
                    "«%s» no se puede encadenar: sus resultados no traen ninguna "
                    "columna que identifique a un usuario o a una fila, así que "
                    "no hay nada contra lo que cruzar las claves del paso "
                    "anterior. Para llegar de un usuario a sus logs, usa «IPs de "
                    "la web», que sí resuelve quién es cada IP."
                    % ent_destino.nombre)

        r = _un_paso(paso, ents, origen, limite)

        if cruce_en_memoria:
            col, valores = cruce_en_memoria
            r = _cruzar(r, col, valores)

        # Claves para el paso siguiente. Se piden con una consulta propia: así
        # no dependen de qué columnas haya elegido mostrar el operador.
        siguiente = pasos[i + 1] if i + 1 < len(pasos) else None
        if siguiente and siguiente.get("enlace"):
            claves, aviso = _claves(paso, siguiente["enlace"], ents, origen)
            if aviso:
                r.warnings.append(aviso)

        tramos.append({
            "entidad": paso.get("entidad"),
            "fuente": r.source,
            "row_count": len(r.rows),
            "duration_ms": r.duration_ms,
            "truncated": r.truncated,
            "window_ok": r.window_ok,
            "consulta": r.consulta_generada,
            "claves_pasadas": None if claves is None or not siguiente else len(claves),
        })
        resultado = r

    return resultado, tramos


def _un_paso(paso, ents, origen, limite):
    """Ejecuta un paso contra la fuente que le toque.

    Las tres fuentes devuelven la MISMA forma de resultado; por eso el cruce
    entre pasos, la gráfica y el archivo no tienen que saber de dónde vino.
    """
    eid = paso.get("entidad")
    if eid not in ents:
        raise CompileError("entidad desconocida: %r" % eid)
    fuente = ents[eid].fuente

    if fuente == "mysql":
        c = compilar(paso, ents)
        r = origen.run(c.sql, c.params, limit=limite)
        r.warnings = c.avisos + r.warnings
        r.consulta_generada = c.sql
        if c.columnas:
            r.columns = c.columnas + r.columns[len(c.columnas):]
        return r

    if fuente == "ipstore":
        from query.compile import a_sqlite
        c = compilar(paso, ents)
        r = IpStore().run(a_sqlite(c.sql), c.params, limit=limite)
        r.warnings = c.avisos + r.warnings
        if c.columnas:
            r.columns = c.columnas + r.columns[len(c.columnas):]
        return r

    if fuente == "loki":
        c, agregada = compilar_loki(paso, ents[eid])
        r = LokiSource().run(c.sql, paso.get("ventana"), limit=limite, agregada=agregada)
        r.warnings = c.avisos + r.warnings
        return r

    if fuente == "prom":
        c, rango = compilar_prom(paso, ents[eid])
        r = PromSource().run(c.sql, paso.get("ventana"), limit=limite, rango=rango)
        r.warnings = c.avisos + r.warnings
        return r

    if fuente == "binlog":
        a = compilar_binlog(paso, ents[eid])
        return BinlogSource().run(a["tabla"], a["clave_col"], a["clave_val"],
                                  paso.get("ventana"), limit=limite)

    raise CompileError("fuente desconocida: %r" % fuente)


def _cruzar(r, columna, valores):
    """Filtra un resultado en memoria por una columna contra un juego de claves.

    Es el cruce entre fuentes que no hablan SQL. Funciona porque las cuatro
    fuentes devuelven la misma forma de resultado.
    """
    if columna not in r.columns:
        r.warnings.append(
            "No se pudo cruzar: el resultado no trae la columna «%s»." % columna)
        return r
    i = r.columns.index(columna)
    antes = len(r.rows)
    r.rows = [f for f in r.rows if str(f[i]) in valores]
    r.warnings.append(
        "Cruzado en memoria por «%s»: %d de %d filas casan con el paso anterior."
        % (columna, len(r.rows), antes))
    if r.truncated:
        r.warnings.append(
            "OJO: el paso venía RECORTADO antes de cruzar, así que pueden faltar "
            "coincidencias que se quedaron fuera del tope.")
    return r


def _claves(paso, enlace, ents, origen):
    """Valores de la columna que enlaza dos pasos.

    Se piden con una consulta propia para no depender de qué columnas haya
    elegido mostrar el operador — y contra la fuente que toque, que un paso de
    origen puede ser el almacén de IPs y no MySQL.
    """
    ent = ents[paso["entidad"]]
    campo_origen = enlace.get("desde_campo") or ent.clave
    solo_clave = dict(paso)
    solo_clave["mostrar"] = [campo_origen]
    solo_clave.pop("ordenar", None)
    # El umbral («sólo grupos con más de N») necesita su cálculo y su agrupado:
    # quitarlos dejaba el HAVING huérfano y la consulta reventaba.
    if not solo_clave.get("umbral"):
        solo_clave["agrupar"] = []
        solo_clave["calcular"] = []
    elif campo_origen not in (solo_clave.get("agrupar") or []):
        solo_clave["agrupar"] = [campo_origen] + list(solo_clave.get("agrupar") or [])

    c = compilar(solo_clave, ents)
    tope = cfg.max_link_keys
    if ent.fuente == "ipstore":
        from query.compile import a_sqlite
        r = IpStore().run(a_sqlite(c.sql), c.params, limit=tope)
    else:
        r = origen.run(c.sql, c.params, limit=tope)
    valores = sorted({fila[0] for fila in r.rows if fila[0] is not None})
    aviso = None
    if r.truncated:
        aviso = ("El enlace entre pasos se quedó en %d claves, que es el tope. "
                 "El paso siguiente está viendo sólo una parte: afina el primer "
                 "paso." % tope)
    return valores, aviso


def _valores_catalogo(eid, cid):
    """Valores posibles de un campo de catálogo.

    Sin esto el compositor te enseña «Sanguijuela» y te pide escribir «leech»:
    los slug de UNIT3D son ingleses y los nombres están traducidos. Quien usa
    el panel no tiene por qué saberse esa correspondencia.
    """
    ents = cargar()
    if eid not in ents:
        raise ValueError("entidad desconocida: %r" % eid)
    campo = ents[eid].campo(cid)
    if ents[eid].fuente == "loki" and campo.loki:
        # Las etiquetas de Loki se preguntan a Loki, no a MySQL.
        from query.sources.http_json import pedir
        import time as _t
        fin = int(_t.time() * 1e9)
        ini = fin - 6 * 3600 * 10 ** 9
        d = pedir("loki", cfg.loki_url, "/loki/api/v1/label/%s/values" % campo.loki,
                  {"start": str(ini), "end": str(fin)})
        vals = sorted(d.get("data") or [])
        return {"campo": campo.id, "etiqueta": campo.etiqueta,
                "valores": [{"valor": v, "etiqueta": v} for v in vals]}
    if not campo.via:
        raise ValueError("«%s» no es un campo de catálogo" % campo.etiqueta)
    via = campo.via
    sql = ("SELECT DISTINCT %s AS valor, %s AS etiqueta FROM `%s` ORDER BY 2"
           % (_col(via.get("filtra", via["muestra"])), _col(via["muestra"]), via["tabla"]))
    r = MySQLSource().run(sql, (), limit=2000)
    return {"campo": campo.id, "etiqueta": campo.etiqueta,
            "valores": [{"valor": v, "etiqueta": e} for v, e in r.rows]}


def _col(nombre):
    if not str(nombre).replace("_", "").isalnum():
        raise ValueError("nombre de columna inválido: %r" % nombre)
    return "`%s`" % nombre


_NOMBRE_OK = __import__("re").compile(r"^[a-z0-9][a-z0-9-]{1,58}[a-z0-9]$")


def _ruta_guardada(nombre):
    if not _NOMBRE_OK.match(nombre or ""):
        raise ValueError(
            "el nombre sólo admite minúsculas, números y guiones, de 3 a 60: %r" % nombre)
    return os.path.join(DIR_GUARDADAS, nombre + ".json")


def _guardar(cuerpo, identidad):
    """Guarda una composición con nombre. Así crece el catálogo: por uso."""
    nombre = (cuerpo.get("nombre") or "").strip().lower().replace(" ", "-")
    ruta = _ruta_guardada(nombre)
    os.makedirs(DIR_GUARDADAS, exist_ok=True)
    d = {
        "nombre": nombre,
        "titulo": cuerpo.get("titulo") or nombre.replace("-", " "),
        "porque": cuerpo.get("porque") or "",
        "pasos": cuerpo.get("pasos") or [],
        "guardada_por": identidad,
        "guardada_en": archive.now_utc(),
    }
    if not d["pasos"]:
        raise ValueError("no hay ningún paso que guardar")
    tmp = ruta + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(d, fh, ensure_ascii=False, indent=2)
    os.replace(tmp, ruta)
    return {"ok": True, "nombre": nombre}


def _borrar_guardada(nombre):
    ruta = _ruta_guardada((nombre or "").strip().lower())
    if os.path.exists(ruta):
        os.remove(ruta)
        return {"ok": True, "nombre": nombre}
    return {"ok": False, "mensaje": "no existe: %s" % nombre}


def _listar_guardadas():
    """Las sembradas y las propias. Si coinciden en nombre, manda la propia."""
    por_nombre = {}
    for carpeta, origen in ((DIR_SEMBRADAS, "sembrada"), (DIR_GUARDADAS, "propia")):
        if not os.path.isdir(carpeta):
            continue
        for f in sorted(os.listdir(carpeta)):
            if not f.endswith(".json"):
                continue
            try:
                with open(os.path.join(carpeta, f), encoding="utf-8") as fh:
                    d = json.load(fh)
            except ValueError:
                continue
            d["origen"] = origen
            d["editable"] = origen == "propia"
            por_nombre[d.get("nombre") or f[:-5]] = d
    return [por_nombre[k] for k in sorted(por_nombre)]


def _selftest():
    origen = MySQLSource()
    p = {}
    p["conecta"] = origen.run("SELECT 1 AS vivo").rows == [[1]]
    modo = origen.run("SELECT @@session.sql_mode AS modo").rows[0][0]
    p["sql_mode"] = modo
    p["sql_mode_ok"] = "ANSI_QUOTES" in str(modo)
    p["usuarios_count_exacto"] = origen.run("SELECT COUNT(*) FROM users").rows[0][0]
    for etiqueta, sql in (("mysql_user_bloqueado", "SELECT user FROM mysql.user LIMIT 1"),
                          ("escritura_bloqueada", "UPDATE users SET username=username WHERE id=0")):
        try:
            origen.run(sql)
            p[etiqueta] = False
        except MySQLError as e:
            p[etiqueta] = True
            p[etiqueta + "_error"] = "%s: %s" % (e.code, e.message)
    p["loki_vivo"] = LokiSource().salud()
    p["prom_vivo"] = PromSource().salud()
    try:
        p["entidades"] = sorted(cargar().keys())
    except ModeloError as e:
        p["entidades_error"] = str(e)
    return p


def _recolector():
    """Hilo de fondo que acumula IPs. Es lo que hace que la ventana deje de ser
    los 7 días de Loki: lo que no se recoja a tiempo se pierde para siempre."""
    import threading
    import time as _t

    if cfg.ips_auto_horas <= 0:
        sys.stderr.write("recolector de IPs DESACTIVADO (AUDITOR_IPS_AUTO_HORAS=0)\n")
        return

    def bucle():
        _t.sleep(20)  # que el servicio arranque antes de la primera pasada
        while True:
            try:
                r = IpStore().recolectar(horas=cfg.ips_auto_ventana)
                sys.stderr.write(
                    "recolector: %d líneas, %d resueltas, %d pares nuevos\n"
                    % (r["lineas"], r["resueltas"], r["pares_nuevos"]))
            except Exception as e:  # nunca tumba el servicio
                sys.stderr.write("recolector: fallo — %s: %s\n" % (type(e).__name__, e))
            _t.sleep(cfg.ips_auto_horas * 3600)

    h = threading.Thread(target=bucle, name="recolector-ips", daemon=True)
    h.start()
    sys.stderr.write("recolector de IPs cada %d h (ventana %d h)\n"
                     % (cfg.ips_auto_horas, cfg.ips_auto_ventana))


def main():
    validar_auth()
    archive.caducar()
    _recolector()
    srv = ThreadingHTTPServer((cfg.bind_addr, cfg.port), Handler)
    sys.stderr.write("unit3d-auditor escuchando en %s:%d (auth=%s)\n"
                     % (cfg.bind_addr, cfg.port, cfg.auth_mode))
    srv.serve_forever()


if __name__ == "__main__":
    main()
