"""Fuente binlog — la única máquina del tiempo que hay.

Las tablas dicen cómo está algo HOY. El binlog dice cuándo cambió y desde qué
valor. Así se resolvió el caso rt001ztp: un salto de subida de 100 GiB clavados
que resultó ser una compra de tienda, no seedeo.

Ventana real: 30 días. Lo anterior no existe en ninguna parte.

Los binlogs van en formato ROW: llevan el valor de CADA columna antes y después
de cada cambio, incluidos los secretos en claro. Por eso aquí se aplica la misma
redacción que en todo lo demás: se ve QUÉ cambió, no a qué valor.
"""
import glob
import os
import re
import subprocess
import time
from datetime import datetime, timedelta, timezone

from config import cfg
from query.result import Result
from query.sources.mysql import MySQLSource

_CAB = re.compile(r"^#(\d{6})\s+(\d{1,2}:\d{2}:\d{2})\s+server id")
_OP = re.compile(r"^### (DELETE FROM|UPDATE|INSERT INTO) `([^`]+)`\.`([^`]+)`")
_CAMPO = re.compile(r"^###\s+@(\d+)=(.*)$")
_SECCION = re.compile(r"^### (WHERE|SET)\s*$")

_OPS = {"DELETE FROM": "DELETE", "UPDATE": "UPDATE", "INSERT INTO": "INSERT"}


class BinlogError(Exception):
    pass


class BinlogSource:
    name = "binlog"

    def __init__(self, config=None):
        self.cfg = config or cfg

    def run(self, tabla, clave_col=None, clave_val=None, ventana=None, limit=None):
        limit = self.cfg.max_rows if limit is None else limit
        horas = float((ventana or {}).get("ultimas_horas") or 24)
        dias = self.cfg.retention_days["binlog"]
        avisos = []
        dentro = True
        if horas > dias * 24:
            dentro = False
            avisos.append(
                "Se pidieron %.0f h pero los binlogs sólo llegan a %d días. Lo "
                "anterior NO EXISTE en ninguna parte." % (horas, dias))
            horas = dias * 24

        desde = datetime.now(timezone.utc) - timedelta(hours=horas)
        columnas, secretos = self._columnas(tabla)
        if not columnas:
            raise BinlogError("la tabla «%s» no existe en la base" % tabla)

        ficheros = self._ficheros(desde)
        if not ficheros:
            raise BinlogError(
                "no hay binlogs que cubran esa ventana en %s" % self.cfg.binlog_dir)
        avisos.append("Leídos %d ficheros de binlog desde %s UTC."
                      % (len(ficheros), desde.strftime("%Y-%m-%d %H:%M")))

        t0 = time.time()
        filas, parados, agotado = self._leer(ficheros, desde, tabla, columnas,
                                             secretos, clave_col, clave_val, limit)
        if parados:
            avisos.append("Lectura cortada al llegar al tope de filas: hay más cambios.")
        if agotado:
            avisos.append(
                "Se agotaron los %d s de lectura. Los binlogs se recorren del más "
                "NUEVO al más viejo, así que lo que se ve es el final de la "
                "ventana y falta el principio. Acota la ventana o la fila."
                % self.cfg.binlog_max_seconds)

        return Result(
            columns=["Momento (UTC)", "Operación", "Fila", "Columna", "Antes", "Después"],
            rows=filas, source=self.name,
            consulta_generada=("mysqlbinlog --base64-output=DECODE-ROWS -v "
                               "--start-datetime='%s' · tabla=%s%s"
                               % (desde.strftime("%Y-%m-%d %H:%M:%S"), tabla,
                                  (" · %s=%s" % (clave_col, clave_val)) if clave_val else "")),
            duration_ms=int((time.time() - t0) * 1000),
            truncated=parados, window_ok=dentro and not agotado, warnings=avisos,
            redacted=sorted(secretos.values()))

    # ---------------------------------------------------------------- ayudas
    def _columnas(self, tabla):
        """@N del binlog -> nombre de columna, por ordinal_position."""
        if not re.match(r"^[A-Za-z0-9_]{1,64}$", tabla or ""):
            raise BinlogError("nombre de tabla inválido: %r" % tabla)
        r = MySQLSource().run(
            "SELECT ordinal_position, column_name FROM information_schema.columns "
            "WHERE table_schema = %s AND table_name = %s ORDER BY ordinal_position",
            (self.cfg.db_name, tabla), limit=1000)
        columnas = {int(p): n for p, n in r.rows}
        secretos = {p: n for p, n in columnas.items() if n.lower() in cfg.secretos}
        return columnas, secretos

    def _ficheros(self, desde):
        """Sólo los binlogs que pueden contener la ventana.

        Se incluye el inmediatamente anterior al primero que cae dentro: un
        fichero empieza antes de su propia fecha de modificación.
        """
        todos = sorted(glob.glob(os.path.join(self.cfg.binlog_dir, "binlog.0*")))
        corte = desde.timestamp()
        dentro, ultimo_fuera = [], None
        for f in todos:
            try:
                mt = os.path.getmtime(f)
            except OSError:
                continue
            if mt >= corte:
                dentro.append(f)
            else:
                ultimo_fuera = f
        if ultimo_fuera:
            dentro.insert(0, ultimo_fuera)
        return dentro

    def _leer(self, ficheros, desde, tabla, columnas, secretos,
              clave_col, clave_val, limit):
        clave_pos = None
        if clave_col:
            for p, n in columnas.items():
                if n == clave_col:
                    clave_pos = p
                    break
            if clave_pos is None:
                raise BinlogError("«%s» no es una columna de %s" % (clave_col, tabla))

        base = ["mysqlbinlog", "--base64-output=DECODE-ROWS", "-v",
                "--start-datetime=" + desde.strftime("%Y-%m-%d %H:%M:%S")]

        filas, parados, agotado = [], False, False
        limite_reloj = time.time() + self.cfg.binlog_max_seconds
        leidas = 0
        ts = None
        op = None
        seccion = None
        antes, despues = {}, {}

        def volcar():
            """Emite las columnas que CAMBIARON de un evento."""
            if not op:
                return
            fila_id = antes.get(clave_pos) or despues.get(clave_pos) if clave_pos else None
            if clave_val is not None and str(fila_id) != str(clave_val):
                return
            posiciones = sorted(set(antes) | set(despues))
            for p in posiciones:
                a, d = antes.get(p), despues.get(p)
                if op == "UPDATE" and a == d:
                    continue
                nombre = columnas.get(p, "@%d" % p)
                if p in secretos:
                    a = "••••" if a not in (None, "NULL") else a
                    d = "••••" if d not in (None, "NULL") else d
                filas.append([ts, op, fila_id if fila_id is not None else "",
                              nombre, a, d])

        err = ""
        for fichero in reversed(ficheros):
            if agotado or parados:
                break
            proc = subprocess.Popen(base + [fichero], stdout=subprocess.PIPE,
                                    stderr=subprocess.PIPE, text=True,
                                    errors="replace", bufsize=1)
            ts = None
            op = None
            seccion = None
            antes, despues = {}, {}
            try:
                for linea in proc.stdout:
                    leidas += 1
                    # El reloj se mira cada pocas miles de líneas: comprobarlo en
                    # cada una cuesta más que leerla.
                    if (leidas & 0x3FFF) == 0 and time.time() > limite_reloj:
                        agotado = True
                        break
                    m = _CAB.match(linea)
                    if m:
                        f6, hh = m.group(1), m.group(2)
                        if len(hh) == 7:
                            hh = "0" + hh
                        ts = "20%s-%s-%s %s" % (f6[0:2], f6[2:4], f6[4:6], hh)
                        continue
                    m = _OP.match(linea)
                    if m:
                        volcar()
                        if len(filas) > limit:
                            parados = True
                            break
                        antes, despues, seccion = {}, {}, None
                        op = _OPS[m.group(1)] if m.group(3) == tabla else None
                        if op == "INSERT":
                            seccion = "SET"
                        elif op == "DELETE":
                            seccion = "WHERE"
                        continue
                    if op is None:
                        continue
                    m = _SECCION.match(linea)
                    if m:
                        seccion = m.group(1)
                        continue
                    m = _CAMPO.match(linea)
                    if m and seccion:
                        destino = antes if seccion == "WHERE" else despues
                        destino[int(m.group(1))] = m.group(2).strip()
                else:
                    volcar()
            finally:
                try:
                    proc.stdout.close()
                    proc.kill()
                    e = (proc.stderr.read() or "").strip()
                    err = e or err
                    proc.stderr.close()
                except Exception:
                    pass
                proc.wait()

        if not filas and err:
            # El error del binario sube entero. Nunca cero filas mudas.
            raise BinlogError(err[:400])

        if len(filas) > limit:
            filas = filas[:limit]
            parados = True
        filas.sort(key=lambda f: f[0] or "", reverse=True)
        return filas, parados, agotado
