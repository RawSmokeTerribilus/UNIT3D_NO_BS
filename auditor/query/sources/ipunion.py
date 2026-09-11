"""Registro unificado de IPs: las cinco procedencias en una sola tabla.

El problema que resuelve: `coincidencias` compara UN rasgo por consulta. Quien
comparte la IP de cliente con A y la de navegación con B no sale en ninguna de
las dos. Medido: 15 IPs compartidas uniendo peers + logins fallidos + seedboxes,
contra 8 mirando sólo peers — y eso antes de sumar las del almacén web.

Cómo: se cargan las cinco procedencias en un SQLite EN MEMORIA y se consulta
ahí. Así el compilador funciona sin cambios —ya emite dialecto SQLite para el
almacén de IPs— y se pueden agrupar, contar y poner umbrales sobre la unión.
Son ~1.900 filas: cabe de sobra.

Las procedencias no valen lo mismo y por eso van etiquetadas:
  cliente        el torrent anunciando. La más fiable y la que más lejos llega.
  web            navegación, resuelta por la clave de la URL. Muestra, no censo.
  login-fallido  un intento de entrar. Puede ser el dueño o puede ser otro.
  bloqueada      de la lista negra del tracker; sin usuario asociado.

Las seedboxes NO entran: su IP está cifrada en la base y aquí sólo llegaría el
texto cifrado. Ver el comentario en `_materializar`.
"""
import os
import sqlite3
import time

from config import cfg
from query.result import Result
from query.sources.mysql import MySQLSource

_CACHE = {"cuando": 0, "con": None}
_TTL = 30  # segundos: una auditoría encadena varias consultas seguidas


class IpUnionError(Exception):
    pass


class IpUnion:
    name = "ips_todas"

    def __init__(self, config=None):
        self.cfg = config or cfg

    # ---------------------------------------------------------- construcción
    def _materializar(self):
        con = sqlite3.connect(":memory:")
        con.execute("""
            CREATE TABLE ips (
                usuario_id  INTEGER,
                usuario     TEXT,
                ip          TEXT NOT NULL,
                procedencia TEXT NOT NULL,
                primera     TEXT,
                ultima      TEXT,
                veces       INTEGER DEFAULT 1)""")
        con.execute("CREATE INDEX ix_ips_ip ON ips(ip)")
        con.execute("CREATE INDEX ix_ips_usuario ON ips(usuario)")

        s = MySQLSource()
        # Cada consulta devuelve las mismas seis columnas, en el mismo orden.
        consultas = [
            ("cliente",
             "SELECT p.user_id AS uid, u.username AS quien, INET6_NTOA(p.ip) AS ip, "
             "MIN(p.created_at) AS prim, MAX(p.updated_at) AS ult, COUNT(*) AS n "
             "FROM peers p "
             "JOIN users u ON u.id = p.user_id AND u.deleted_at IS NULL "
             "GROUP BY p.user_id, p.ip"),
            ("login-fallido",
             "SELECT f.user_id AS uid, COALESCE(u.username, f.username) AS quien, "
             "f.ip_address AS ip, MIN(f.created_at) AS prim, MAX(f.created_at) AS ult, "
             "COUNT(*) AS n FROM failed_login_attempts f "
             "LEFT JOIN users u ON u.id = f.user_id "
             "WHERE f.ip_address IS NOT NULL GROUP BY f.user_id, f.username, f.ip_address"),
            # SEEDBOXES FUERA, a propósito. `seedboxes.ip` está CIFRADO en la
            # base con el Crypt de Laravel (AES-CBC): el valor es
            # `eyJpdiI6...`, base64 de {"iv","value","mac","tag"}. Meterlo aquí
            # llenaba el registro de 24 cadenas que no son IPs.
            #
            # Y peor: el IV es aleatorio, así que la MISMA IP cifra distinto
            # cada vez —24 filas, 24 valores distintos—, de modo que comparar
            # cifrado contra cifrado no casa nunca. El campo «comparte seedbox»
            # daba 0 siempre, y ese 0 no significaba «nadie comparte»: no
            # significaba nada.
            #
            # Descifrarlo aquí es posible (hace falta APP_KEY) pero es una
            # decisión del operador, no un detalle de implementación: esos datos
            # están cifrados en reposo a propósito.
            ("bloqueada",
             "SELECT NULL AS uid, NULL AS quien, b.ip_address AS ip, "
             "MIN(b.created_at) AS prim, MAX(b.created_at) AS ult, COUNT(*) AS n "
             "FROM blocked_ips b GROUP BY b.ip_address"),
        ]
        for procedencia, sql in consultas:
            r = s.run(sql, (), limit=100000)
            con.executemany(
                "INSERT INTO ips (usuario_id, usuario, ip, primera, ultima, veces, "
                "procedencia) VALUES (?,?,?,?,?,?,'%s')" % procedencia,
                [(f[0], f[1], f[2], str(f[3]) if f[3] else None,
                  str(f[4]) if f[4] else None, f[5]) for f in r.rows if f[2]])

        # El almacén web es SQLite en disco: se lee aparte.
        ruta = os.path.join(self.cfg.run_dir, "ips.sqlite")
        if os.path.exists(ruta):
            web = sqlite3.connect("file:%s?mode=ro" % ruta, uri=True)
            try:
                filas = web.execute(
                    "SELECT usuario_id, usuario, ip, MIN(primera), MAX(ultima), "
                    "SUM(veces) FROM vistas GROUP BY usuario_id, ip").fetchall()
            finally:
                web.close()
            con.executemany(
                "INSERT INTO ips (usuario_id, usuario, ip, primera, ultima, veces, "
                "procedencia) VALUES (?,?,?,?,?,?,'web')", filas)
        con.commit()
        return con

    def _con(self):
        ahora = time.time()
        if _CACHE["con"] is None or ahora - _CACHE["cuando"] > _TTL:
            if _CACHE["con"] is not None:
                try:
                    _CACHE["con"].close()
                except Exception:
                    pass
            _CACHE["con"] = self._materializar()
            _CACHE["cuando"] = ahora
        return _CACHE["con"]

    # -------------------------------------------------------------- consulta
    def run(self, sql, params=(), limit=None):
        limit = self.cfg.max_rows if limit is None else limit
        t0 = time.time()
        con = self._con()
        try:
            cur = con.execute(sql, tuple(params))
            columnas = [c[0] for c in (cur.description or [])]
            filas = [list(f) for f in cur.fetchmany(limit + 1)]
        except sqlite3.Error as e:
            raise IpUnionError("%s: %s" % (type(e).__name__, e))
        truncado = len(filas) > limit
        if truncado:
            filas = filas[:limit]
        return Result(
            columns=columnas, rows=filas, source=self.name,
            consulta_generada=sql,
            duration_ms=int((time.time() - t0) * 1000),
            truncated=truncado,
            warnings=self.contexto() + (
                ["Resultado recortado a %d filas." % limit] if truncado else []))

    def contexto(self):
        con = self._con()
        n, ips, us = con.execute(
            "SELECT COUNT(*), COUNT(DISTINCT ip), COUNT(DISTINCT usuario_id) "
            "FROM ips").fetchone()
        por = con.execute(
            "SELECT procedencia, COUNT(DISTINCT ip) FROM ips GROUP BY 1 "
            "ORDER BY 2 DESC").fetchall()
        return ["Unión de %d registros: %d IPs de %d usuarios. Por procedencia: %s."
                % (n, ips, us, ", ".join("%s %d" % (p, c) for p, c in por))]
