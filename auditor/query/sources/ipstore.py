"""IP ↔ usuario, recolectado de los logs y GUARDADO.

Por qué existe: en la base NO hay IP de navegación. `sessions` está vacía porque
`SESSION_DRIVER=redis`, y no hay columna de IP de registro. La única fuente es
el log de nginx… que Loki sólo guarda 7 días.

Así que se recolecta y se acumula en un SQLite propio. A partir de la primera
pasada, la ventana deja de ser 7 días y empieza a ser todo lo que se haya
recogido.

Cómo se resuelve quién es cada IP: parte del tráfico lleva una clave por usuario
en la URL —`/rss/<id>.<32 hex>`, `/torrent/download/<id>.<32 hex>` y
`api_token=…`— y esas claves casan contra `users.rsskey` y `users.api_token`.

Limitación que se dice siempre: es una MUESTRA, no un censo. Quien entra por la
web y no usa cliente, RSS ni API no aparece aquí.
"""
import json
import os
import re
import sqlite3
import time
import urllib.parse
from datetime import datetime, timezone

from config import cfg
from query.result import Result
from query.sources.http_json import pedir
from query.sources.mysql import MySQLSource

# 187.13.1.134 - - [06/Sep/2026:13:35:02 +0000] "GET /ruta HTTP/1.1" 200 …
_LINEA = re.compile(r'^(\S+) \S+ \S+ \[([^\]]+)\] "(?:GET|POST|HEAD) (\S+)')
_CLAVE_RUTA = re.compile(r"/(?:rss|torrent/download)/\d+\.([0-9a-f]{32})", re.I)
_CLAVE_QS = re.compile(r"[?&]api_token=([^&\s\"]+)")
_FECHA = "%d/%b/%Y:%H:%M:%S %z"


class IpStoreError(Exception):
    pass


class IpStore:
    """El almacén. Un SQLite en el volumen, indexado por ip y por usuario."""

    name = "ips_web"

    def __init__(self, config=None):
        self.cfg = config or cfg
        self.ruta = os.path.join(self.cfg.run_dir, "ips.sqlite")

    def _con(self):
        os.makedirs(os.path.dirname(self.ruta), exist_ok=True)
        con = sqlite3.connect(self.ruta, timeout=20)
        con.execute("PRAGMA journal_mode=WAL")
        con.execute("""
            CREATE TABLE IF NOT EXISTS vistas (
                usuario_id INTEGER NOT NULL,
                usuario    TEXT    NOT NULL,
                ip         TEXT    NOT NULL,
                via        TEXT    NOT NULL,
                primera    TEXT    NOT NULL,
                ultima     TEXT    NOT NULL,
                veces      INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (usuario_id, ip, via))""")
        con.execute("CREATE INDEX IF NOT EXISTS ix_vistas_ip ON vistas(ip)")
        con.execute("CREATE INDEX IF NOT EXISTS ix_vistas_usuario ON vistas(usuario)")
        con.execute("""
            CREATE TABLE IF NOT EXISTS pasadas (
                cuando TEXT PRIMARY KEY, horas REAL, lineas INTEGER,
                resueltas INTEGER, sin_resolver INTEGER, nuevas INTEGER)""")
        return con

    # ------------------------------------------------------------ recolectar
    def recolectar(self, horas=6, tope_lineas=200000):
        """Lee Loki, resuelve las claves y acumula. Devuelve el resumen."""
        horas = min(float(horas), self.cfg.retention_days["loki"] * 24)
        claves = self._claves()
        if not claves:
            raise IpStoreError("no se pudo leer ninguna clave de usuario de la base")

        fin = int(time.time() * 1e9)
        ini = fin - int(horas * 3600 * 1e9)
        lineas = self._lineas(ini, fin, tope_lineas)

        vistas = {}
        resueltas = sin_resolver = 0
        for ip, momento, ruta in lineas:
            clave = self._clave_de(ruta)
            if not clave:
                continue
            quien = claves.get(clave.lower())
            if not quien:
                sin_resolver += 1
                continue
            resueltas += 1
            uid, nombre, via = quien[0], quien[1], quien[2]
            k = (uid, ip, via)
            v = vistas.get(k)
            if v is None:
                vistas[k] = [nombre, momento, momento, 1]
            else:
                v[1] = min(v[1], momento)
                v[2] = max(v[2], momento)
                v[3] += 1

        nuevas = 0
        con = self._con()
        try:
            with con:
                for (uid, ip, via), (nombre, prim, ult, n) in vistas.items():
                    cur = con.execute(
                        "SELECT primera, ultima, veces FROM vistas "
                        "WHERE usuario_id=? AND ip=? AND via=?", (uid, ip, via))
                    fila = cur.fetchone()
                    if fila is None:
                        nuevas += 1
                        con.execute(
                            "INSERT INTO vistas (usuario_id, usuario, ip, via, "
                            "primera, ultima, veces) VALUES (?,?,?,?,?,?,?)",
                            (uid, nombre, ip, via, prim, ult, n))
                    else:
                        con.execute(
                            "UPDATE vistas SET usuario=?, primera=?, ultima=?, veces=? "
                            "WHERE usuario_id=? AND ip=? AND via=?",
                            (nombre, min(fila[0], prim), max(fila[1], ult),
                             fila[2] + n, uid, ip, via))
                con.execute(
                    "INSERT OR REPLACE INTO pasadas (cuando, horas, lineas, "
                    "resueltas, sin_resolver, nuevas) VALUES (?,?,?,?,?,?)",
                    (datetime.now(timezone.utc).isoformat(), horas, len(lineas),
                     resueltas, sin_resolver, nuevas))
        finally:
            con.close()

        return {"horas": horas, "lineas": len(lineas), "resueltas": resueltas,
                "sin_resolver": sin_resolver, "pares_nuevos": nuevas,
                "pares_tocados": len(vistas)}

    def _claves(self):
        """clave (32 hex o token) -> (id, nombre, vía). Nunca se devuelve la clave."""
        r = MySQLSource().run(
            "SELECT id, username, LOWER(rsskey), LOWER(api_token) FROM users "
            "WHERE deleted_at IS NULL", limit=100000)
        m = {}
        for uid, nombre, rsskey, api in r.rows:
            if rsskey:
                m[rsskey] = (uid, nombre, "rss/descarga")
            if api:
                m[api] = (uid, nombre, "api")
        return m

    def _lineas(self, ini, fin, tope):
        salida = []
        # Se piden sólo las líneas que pueden llevar clave: acotar en Loki es
        # muchísimo más barato que traerlo todo y filtrar aquí.
        for filtro in ('|~ "/rss/[0-9]+\\\\."',
                       '|~ "/torrent/download/[0-9]+\\\\."',
                       '|= "api_token="'):
            consulta = '{job="unit3d_nginx"} ' + filtro
            d = pedir("loki", self.cfg.loki_url, "/loki/api/v1/query_range",
                      {"query": consulta, "start": str(ini), "end": str(fin),
                       "limit": str(min(tope, 5000)), "direction": "backward"},
                      timeout=60)
            for flujo in d.get("data", {}).get("result", []):
                for _ts, linea in flujo.get("values", []):
                    m = _LINEA.match(linea)
                    if not m:
                        continue
                    try:
                        cuando = datetime.strptime(m.group(2), _FECHA).astimezone(
                            timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
                    except ValueError:
                        continue
                    salida.append((m.group(1), cuando, m.group(3)))
        return salida

    @staticmethod
    def _clave_de(ruta):
        m = _CLAVE_RUTA.search(ruta)
        if m:
            return m.group(1)
        m = _CLAVE_QS.search(ruta)
        if m:
            return urllib.parse.unquote(m.group(1))
        return None

    # --------------------------------------------------------------- consulta
    def run(self, sql, params=(), limit=None):
        limit = self.cfg.max_rows if limit is None else limit
        t0 = time.time()
        con = self._con()
        try:
            cur = con.execute(sql, tuple(params))
            columnas = [c[0] for c in (cur.description or [])]
            filas = [list(f) for f in cur.fetchmany(limit + 1)]
        except sqlite3.Error as e:
            raise IpStoreError("%s: %s" % (type(e).__name__, e))
        finally:
            con.close()
        truncado = len(filas) > limit
        if truncado:
            filas = filas[:limit]
        avisos = list(self.contexto())
        if truncado:
            avisos.append("Resultado recortado a %d filas." % limit)
        return Result(columns=columnas, rows=filas, source=self.name,
                      consulta_generada=sql,
                      duration_ms=int((time.time() - t0) * 1000),
                      truncated=truncado, warnings=avisos)

    def contexto(self):
        """Qué hay recogido. Sin esto, un cero es indistinguible de «no se ha recolectado»."""
        con = self._con()
        try:
            n, u, prim, ult = con.execute(
                "SELECT COUNT(*), COUNT(DISTINCT usuario_id), MIN(primera), "
                "MAX(ultima) FROM vistas").fetchone()
            pasadas = con.execute("SELECT COUNT(*), MAX(cuando) FROM pasadas").fetchone()
        finally:
            con.close()
        if not n:
            return ["El almacén está VACÍO: hay que recolectar antes de consultar "
                    "(botón «Recolectar IPs»). Un cero aquí no significa que no "
                    "haya coincidencias."]
        return ["Recogido: %d pares IP↔usuario de %d usuarios, entre %s y %s UTC. "
                "%d pasadas, la última %s." % (n, u, prim, ult, pasadas[0],
                                               (pasadas[1] or "?")[:19])]
