"""Fuente MySQL — el canal.

El fallo que originó todo este proyecto (un `mysql | grep` que truncó
resultados en silencio porque `grep` tomó por binaria una salida con acentos)
nace de mover datos como TEXTO entre procesos. Con un driver ese plano no
existe: no hay docker exec, no hay argv, no hay TSV que parsear, no hay
codificación que negociar, y los tipos llegan como tipos.

Reglas que no se negocian aquí:
  - parámetros ligados, jamás interpolación
  - los errores suben enteros; nada de 2>/dev/null ni de `|| true`
  - si se alcanza el tope de filas se marca `truncated`, nunca se recorta callando
"""
import re
import time

import pymysql
import pymysql.cursors

from config import cfg
from query.result import Result

# sql_mode idéntico al de la aplicación: config/database.php:59-60 declara
# 'modes' => ['ANSI'], así que Laravel corre con ANSI_QUOTES y SIN
# STRICT_TRANS_TABLES. Un cliente mysql por defecto corre justo al revés, y esa
# diferencia cambia el resultado de una consulta. Se fija para que una consulta
# guardada signifique lo mismo que el código.
SQL_MODE = "ANSI"

# Sólo se puede envolver en LIMIT lo que es una subconsulta válida.
_ENVOLVIBLE = re.compile(r"^\s*(?:WITH|SELECT)\b", re.IGNORECASE)


class MySQLError(Exception):
    """Error de MySQL con su código y su mensaje, tal cual vinieron."""

    def __init__(self, code, message, sql):
        self.code = code
        self.message = message
        self.sql = sql
        super().__init__("MySQL %s: %s" % (code, message))


class MySQLSource:
    name = "mysql"

    def __init__(self, config=None):
        self.cfg = config or cfg

    def _connect(self):
        conn = pymysql.connect(
            host=self.cfg.db_host,
            port=self.cfg.db_port,
            user=self.cfg.db_user,
            password=self.cfg.db_pass,
            database=self.cfg.db_name,
            charset="utf8mb4",
            autocommit=True,
            connect_timeout=10,
            read_timeout=max(30, self.cfg.max_exec_ms // 1000 + 10),
            cursorclass=pymysql.cursors.SSCursor,
        )
        with conn.cursor() as cur:
            cur.execute("SET SESSION sql_mode=%s", (SQL_MODE,))
            cur.execute("SET SESSION max_execution_time=%s", (self.cfg.max_exec_ms,))
            cur.execute("SET SESSION transaction_read_only=1")
            cur.execute("SET SESSION time_zone='+00:00'")
        return conn

    def run(self, sql, params=(), limit=None, redactar=()):
        """Ejecuta una sentencia de lectura y devuelve un Result."""
        limit = self.cfg.max_rows if limit is None else limit
        sql_final, params_final = self._aplicar_limite(sql, params, limit)

        t0 = time.time()
        conn = None
        try:
            conn = self._connect()
            with conn.cursor() as cur:
                cur.execute(sql_final, params_final)
                columns = [d[0] for d in (cur.description or [])]
                # Se pide una fila de más: si llega, es que hay más de las que
                # caben, y eso se DICE.
                rows = []
                for row in cur:
                    rows.append(list(row))
                    if len(rows) > limit:
                        break
        except pymysql.MySQLError as e:
            code = e.args[0] if e.args else 0
            msg = e.args[1] if len(e.args) > 1 else str(e)
            raise MySQLError(code, msg, sql_final)
        finally:
            if conn is not None:
                conn.close()

        truncated = len(rows) > limit
        if truncated:
            rows = rows[:limit]

        columns, rows, redacted = _redactar(columns, rows, redactar)

        warnings = []
        if truncated:
            warnings.append(
                "Resultado recortado a %d filas. Hay más: afina el filtro o sube el tope."
                % limit
            )

        return Result(
            columns=columns,
            rows=rows,
            source=self.name,
            consulta_generada=_para_mostrar(sql_final, params_final),
            duration_ms=int((time.time() - t0) * 1000),
            truncated=truncated,
            warnings=warnings,
            redacted=redacted,
        )

    def _aplicar_limite(self, sql, params, limit):
        """Envuelve la consulta para pedir limit+1 filas.

        Envolver en lugar de añadir ` LIMIT n` respeta cualquier LIMIT que ya
        traiga la consulta: el de dentro sigue mandando y el de fuera sólo pone
        el techo. SHOW y EXPLAIN no se envuelven porque no son subconsultas.
        """
        if not _ENVOLVIBLE.match(sql or ""):
            return sql, tuple(params)
        envuelta = "SELECT * FROM (\n%s\n) AS _panel LIMIT %%s" % sql.rstrip().rstrip(";")
        return envuelta, tuple(params) + (limit + 1,)


def _redactar(columns, rows, extra=()):
    """Tapa el VALOR de las columnas secretas y añade su columna de presencia.

    El candado de verdad está en el modelo (una columna marcada `secreto` no se
    puede ni elegir para mostrar). Esto es la red por debajo, para el modo
    crudo y para el binlog.
    """
    secretos = set(cfg.secretos) | {s.lower() for s in extra}
    idx = {i for i, c in enumerate(columns) if str(c).lower() in secretos}
    if not idx:
        return columns, rows, []

    tapadas = [columns[i] for i in sorted(idx)]

    # Columnas y filas se construyen en la MISMA pasada, para que no dependan
    # de buscar por nombre (hay consultas con nombres repetidos) ni de índices
    # que se desplazan al ir insertando.
    nuevas = []
    for i, c in enumerate(columns):
        nuevas.append(c)
        if i in idx:
            nuevas.append("%s_presente" % c)

    salida = []
    for row in rows:
        nueva = []
        for i, valor in enumerate(row):
            nueva.append("••••" if i in idx else valor)
            if i in idx:
                nueva.append(valor is not None and valor != "")
        salida.append(nueva)
    return nuevas, salida, tapadas


def _para_mostrar(sql, params):
    """La consulta tal como se ejecuta, para el botón «Ver la consulta».

    Sólo para leerla. Nunca se manda a MySQL esta cadena: lo que viaja son la
    sentencia con marcadores y los valores por separado.
    """
    if not params:
        return sql
    return "%s\n-- parámetros: %r" % (sql, list(params))
