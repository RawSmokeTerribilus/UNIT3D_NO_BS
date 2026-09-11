"""Fuente Loki (LogQL).

Retención real: 7 días, y además Loki RECHAZA muestras de más de 168 h. Si se
pide más atrás, se avisa: devolver una respuesta corta sin decirlo sería el
mismo fallo del canal que miente, en otra fuente.

Salud: /loki/api/v1/labels. NO /ready, que lleva semanas devolviendo
«503 Ingester not ready» mientras la API de consulta responde perfectamente.
"""
import time

from config import cfg
from query.result import Result
from query.sources.http_json import pedir


class LokiSource:
    name = "loki"

    def __init__(self, config=None):
        self.cfg = config or cfg
        self.base = self.cfg.loki_url

    def salud(self):
        try:
            pedir("loki", self.base, "/loki/api/v1/labels", {}, timeout=5)
            return True
        except Exception:
            return False

    def run(self, logql, ventana, limit=None, agregada=False):
        limit = self.cfg.max_rows if limit is None else limit
        ini, fin, dentro, aviso_ventana = _ventana(ventana, self.cfg.retention_days["loki"])
        t0 = time.time()
        avisos = list(aviso_ventana)

        if agregada:
            d = pedir("loki", self.base, "/loki/api/v1/query",
                      {"query": logql, "time": str(fin)})
            columnas, filas = _instantanea(d)
        else:
            d = pedir("loki", self.base, "/loki/api/v1/query_range",
                      {"query": logql, "start": str(ini), "end": str(fin),
                       "limit": str(limit + 1), "direction": "backward"})
            columnas, filas = _lineas(d)

        truncado = len(filas) > limit
        if truncado:
            filas = filas[:limit]
            avisos.append("Resultado recortado a %d líneas. Hay más." % limit)

        return Result(columns=columnas, rows=filas, source=self.name,
                      consulta_generada=logql,
                      duration_ms=int((time.time() - t0) * 1000),
                      truncated=truncado, window_ok=dentro, warnings=avisos)


def _ventana(ventana, dias_retencion):
    """Devuelve (inicio_ns, fin_ns, dentro_de_retencion, avisos)."""
    ahora = time.time()
    horas = float((ventana or {}).get("ultimas_horas") or 1)
    fin = ahora
    ini = ahora - horas * 3600
    tope = ahora - dias_retencion * 86400
    avisos = []
    dentro = True
    if ini < tope:
        dentro = False
        avisos.append(
            "Se pidieron %.0f h pero Loki sólo guarda %d días (y rechaza "
            "muestras más viejas). Lo anterior a eso NO EXISTE: no es que la "
            "consulta no encuentre nada." % (horas, dias_retencion))
    return int(ini * 1e9), int(fin * 1e9), dentro, avisos


def _lineas(d):
    filas = []
    for flujo in d.get("data", {}).get("result", []):
        etiquetas = flujo.get("stream", {})
        for ts, linea in flujo.get("values", []):
            filas.append([
                _hora(ts),
                etiquetas.get("log_type", ""),
                etiquetas.get("status", ""),
                etiquetas.get("method", ""),
                linea,
            ])
    filas.sort(key=lambda f: f[0], reverse=True)
    return ["Momento (UTC)", "Tipo", "Estado", "Método", "Línea"], filas


def _instantanea(d):
    resultado = d.get("data", {}).get("result", [])
    claves = []
    for serie in resultado:
        for k in serie.get("metric", {}):
            if k not in claves:
                claves.append(k)
    filas = []
    for serie in resultado:
        m = serie.get("metric", {})
        valor = serie.get("value", [None, None])[1]
        filas.append([m.get(k, "") for k in claves] + [_num(valor)])
    filas.sort(key=lambda f: f[-1] if isinstance(f[-1], (int, float)) else 0, reverse=True)
    return claves + ["Cuántos"], filas


def _hora(ts_ns):
    from datetime import datetime, timezone
    return datetime.fromtimestamp(int(ts_ns) / 1e9, timezone.utc).strftime("%Y-%m-%d %H:%M:%S")


def _num(v):
    try:
        f = float(v)
        return int(f) if f.is_integer() else f
    except (TypeError, ValueError):
        return v
