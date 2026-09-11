"""Forma de resultado única para las tres fuentes.

Que MySQL, Loki y Prometheus devuelvan exactamente esto es lo que hace
uniformes el cruce entre pasos, la gráfica y el archivo de ejecuciones.
"""


class Result:
    def __init__(self, columns, rows, source, consulta_generada="",
                 duration_ms=0, truncated=False, window_ok=True,
                 warnings=None, redacted=None):
        self.columns = list(columns)
        self.rows = [list(r) for r in rows]
        self.source = source
        self.consulta_generada = consulta_generada
        self.duration_ms = duration_ms
        self.truncated = truncated
        self.window_ok = window_ok
        self.warnings = list(warnings or [])
        self.redacted = list(redacted or [])

    def to_dict(self):
        return {
            "columns": self.columns,
            "rows": self.rows,
            "meta": {
                "source": self.source,
                "duration_ms": self.duration_ms,
                "row_count": len(self.rows),
                "truncated": self.truncated,
                "window_ok": self.window_ok,
                "warnings": self.warnings,
                "redacted": self.redacted,
                "consulta_generada": self.consulta_generada,
            },
        }
