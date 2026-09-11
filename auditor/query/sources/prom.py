"""Fuente Prometheus (PromQL).

Retención: 15 días o 5 GB, lo que llegue antes — así que la ventana real puede
ser MENOR que 15 días si el disco se llenó primero. Cuando se pide más atrás de
lo que hay, se avisa.
"""
import time
from datetime import datetime, timezone

from config import cfg
from query.result import Result
from query.sources.http_json import pedir


class PromSource:
    name = "prom"

    def __init__(self, config=None):
        self.cfg = config or cfg
        self.base = self.cfg.prom_url

    def salud(self):
        try:
            pedir("prometheus", self.base, "/api/v1/query", {"query": "up"}, timeout=5)
            return True
        except Exception:
            return False

    def metricas(self, prefijo=""):
        d = pedir("prometheus", self.base, "/api/v1/label/__name__/values", {})
        nombres = d.get("data", [])
        if prefijo:
            nombres = [n for n in nombres if prefijo.lower() in n.lower()]
        return nombres

    def run(self, promql, ventana=None, limit=None, rango=False):
        limit = self.cfg.max_rows if limit is None else limit
        ahora = time.time()
        horas = float((ventana or {}).get("ultimas_horas") or 1)
        ini, fin = ahora - horas * 3600, ahora
        dias = self.cfg.retention_days["prom"]
        avisos = []
        dentro = True
        if ini < ahora - dias * 86400:
            dentro = False
            avisos.append(
                "Se pidieron %.0f h pero Prometheus guarda %d días o 5 GB, lo que "
                "llegue antes. Si el disco se llenó, la ventana real es aún más "
                "corta." % (horas, dias))

        t0 = time.time()
        if rango:
            # 120 puntos por serie: suficiente para una gráfica de 260px y deja
            # sitio a varias series sin chocar con el tope de filas. Con 400 y 23
            # contenedores se pasaba de 5.000 y el resultado salía recortado.
            paso = max(15, int((fin - ini) / 120))
            d = pedir("prometheus", self.base, "/api/v1/query_range",
                      {"query": promql, "start": "%.3f" % ini, "end": "%.3f" % fin,
                       "step": str(paso)})
            columnas, filas = _serie(d)
        else:
            d = pedir("prometheus", self.base, "/api/v1/query", {"query": promql})
            columnas, filas = _instantanea(d)

        truncado = len(filas) > limit
        if truncado:
            filas = filas[:limit]
            avisos.append("Resultado recortado a %d puntos. Hay más." % limit)

        return Result(columns=columnas, rows=filas, source=self.name,
                      consulta_generada=promql,
                      duration_ms=int((time.time() - t0) * 1000),
                      truncated=truncado, window_ok=dentro, warnings=avisos)


# Etiquetas que identifican de verdad, en orden de utilidad. cAdvisor añade
# docenas que no distinguen nada y que, volcadas tal cual, dejan el nombre de la
# serie en un hash de contenedor ilegible.
_PREFERIDAS = ["name", "container", "pod", "instance", "job", "device",
               "mountpoint", "cpu", "mode", "state", "code", "type"]
_RUIDO = ("container_label_", "annotation_", "label_")
_RUIDO_EXACTO = {"id", "image", "container_id", "__name__"}


def _claves(resultado):
    """Etiquetas útiles, ordenadas: primero las que identifican, luego el resto.

    `id` e `image` se descartan cuando hay `name`, que dice lo mismo y se lee.
    """
    vistas = set()
    for s in resultado:
        vistas.update(s.get("metric", {}).keys())
    hay_nombre = "name" in vistas or "container" in vistas

    utiles = []
    for k in _PREFERIDAS:
        if k in vistas:
            utiles.append(k)
    for k in sorted(vistas):
        if k in utiles or k in _RUIDO_EXACTO or k.startswith(_RUIDO):
            continue
        utiles.append(k)
    if not hay_nombre:
        # Sin `name` no se puede tirar `id`: sería quedarse sin identidad.
        for k in ("id", "image"):
            if k in vistas and k not in utiles:
                utiles.append(k)
    return utiles


def _instantanea(d):
    resultado = d.get("data", {}).get("result", [])
    claves = _claves(resultado)
    filas = []
    for s in resultado:
        m = s.get("metric", {})
        filas.append([m.get("__name__", "")] + [m.get(k, "") for k in claves]
                     + [_num(s.get("value", [None, None])[1])])
    return ["Métrica"] + claves + ["Valor"], filas


def _serie(d):
    resultado = d.get("data", {}).get("result", [])
    claves = _claves(resultado)
    filas = []
    for s in resultado:
        m = s.get("metric", {})
        etiquetas = [m.get("__name__", "")] + [m.get(k, "") for k in claves]
        for ts, v in s.get("values", []):
            filas.append([_hora(ts)] + etiquetas + [_num(v)])
    filas.sort(key=lambda f: f[0])
    return ["Momento (UTC)", "Métrica"] + claves + ["Valor"], filas


def _hora(ts):
    return datetime.fromtimestamp(float(ts), timezone.utc).strftime("%Y-%m-%d %H:%M:%S")


def _num(v):
    try:
        f = float(v)
        return int(f) if f.is_integer() else round(f, 4)
    except (TypeError, ValueError):
        return v
