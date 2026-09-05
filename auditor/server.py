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
from query.compile import OPERADORES, CompileError, compilar
from query.guard import GuardError, revisar
from query.modelo import ModeloError, cargar
from query.sources.mysql import MySQLError, MySQLSource

DIR_ESTATICOS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "static")
DIR_GUARDADAS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "query", "saved")

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
            if ruta == "/api/query/valores":
                return self._json(_valores_catalogo(
                    (q.get("entidad") or [""])[0], (q.get("campo") or [""])[0]))
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
                return self._json(_ejecutar(cuerpo, self._identidad()))
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
        if isinstance(e, (CompileError, GuardError, ModeloError, ValueError)):
            return self._json({"error": type(e).__name__, "mensaje": str(e)}, 400)
        traceback.print_exc()
        return self._json({"error": type(e).__name__, "mensaje": str(e)}, 500)


# ------------------------------------------------------------------ lógica
def _compilar(cuerpo):
    ents = cargar()
    paso = cuerpo.get("paso") or cuerpo
    c = compilar(paso, ents)
    return {"consulta": c.sql, "parametros": list(c.params),
            "columnas": c.columnas, "avisos": c.avisos}


def _ejecutar(cuerpo, identidad):
    """Ejecuta y archiva. También archiva lo que se RECHAZA.

    Un intento denegado —pedir un hash, escribir, tocar el esquema mysql— es
    justo lo que un registro de auditoría tiene que conservar. Si sólo se
    guardara lo que sale bien, el registro contaría media historia.
    """
    guardada = cuerpo.get("guardada")
    composicion = {"modo": "crudo", "sql": cuerpo["sql"]} if cuerpo.get("sql") else (
        cuerpo.get("paso") or cuerpo)
    try:
        return _ejecutar_inner(cuerpo, identidad, guardada)
    except Exception as e:
        archive.registrar(None, composicion=composicion, guardada=guardada,
                          identidad=identidad,
                          error="%s: %s" % (type(e).__name__, e))
        raise


def _ejecutar_inner(cuerpo, identidad, guardada):
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
        paso = cuerpo.get("paso") or cuerpo
        c = compilar(paso, ents)
        r = origen.run(c.sql, c.params, limit=limite)
        r.warnings = c.avisos + r.warnings
        r.consulta_generada = c.sql
        if c.columnas:
            r.columns = c.columnas + r.columns[len(c.columnas):]
        composicion = paso

    run_id = archive.registrar(r, composicion=composicion, guardada=guardada,
                               identidad=identidad)
    d = r.to_dict()
    d["run_id"] = run_id
    return d


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


def _listar_guardadas():
    salida = []
    if not os.path.isdir(DIR_GUARDADAS):
        return salida
    for f in sorted(os.listdir(DIR_GUARDADAS)):
        if not f.endswith(".json"):
            continue
        with open(os.path.join(DIR_GUARDADAS, f), encoding="utf-8") as fh:
            d = json.load(fh)
        d["archivo"] = f
        salida.append(d)
    return salida


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
    try:
        p["entidades"] = sorted(cargar().keys())
    except ModeloError as e:
        p["entidades_error"] = str(e)
    return p


def main():
    validar_auth()
    archive.caducar()
    srv = ThreadingHTTPServer((cfg.bind_addr, cfg.port), Handler)
    sys.stderr.write("unit3d-auditor escuchando en %s:%d (auth=%s)\n"
                     % (cfg.bind_addr, cfg.port, cfg.auth_mode))
    srv.serve_forever()


if __name__ == "__main__":
    main()
