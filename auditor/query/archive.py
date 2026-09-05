"""Archivo de ejecuciones.

Tres cosas de una sola:
  - el registro de auditoría (quién consultó qué y cuándo)
  - poder releer un informe de hace meses aunque Loki ya haya tirado los logs
    que lo alimentaron (Loki guarda 7 días, Prometheus 15)
  - la serie de recuentos de una composición guardada a lo largo del tiempo,
    que es el eje temporal que necesita cualquier análisis conductual

Todo en UTC. La base va en UTC y el host en CEST; mezclarlos da informes que no
cuadran entre sí.
"""
import json
import os
import random
import string
import time
from datetime import datetime, timezone

from config import cfg

_ALFABETO = string.ascii_lowercase + string.digits


def _ahora():
    return datetime.now(timezone.utc)


def _dir_base():
    return os.path.join(cfg.run_dir, "queries")


def _ruta_indice():
    return os.path.join(_dir_base(), "index.jsonl")


def nuevo_id(momento=None):
    m = momento or _ahora()
    sufijo = "".join(random.choice(_ALFABETO) for _ in range(6))
    return "%s-%s" % (m.strftime("%Y%m%dT%H%M%SZ"), sufijo)


def registrar(resultado, composicion=None, guardada=None, identidad="local",
              error=None):
    """Archiva una ejecución. Devuelve el run_id."""
    momento = _ahora()
    run_id = nuevo_id(momento)
    carpeta = os.path.join(_dir_base(), momento.strftime("%Y"), momento.strftime("%m"))
    os.makedirs(carpeta, exist_ok=True)

    d = resultado.to_dict() if resultado is not None else {"columns": [], "rows": [], "meta": {}}
    entero = {
        "run_id": run_id,
        "ts_utc": momento.isoformat(),
        "identidad": identidad,
        "guardada": guardada,
        "composicion": composicion,
        "error": error,
        "columns": d["columns"],
        "rows": d["rows"],
        "meta": d["meta"],
    }
    _escribir_atomico(os.path.join(carpeta, run_id + ".json"), entero)

    # El índice no lleva filas: se lista y se busca sin cargar los datos.
    linea = {
        "run_id": run_id,
        "ts_utc": entero["ts_utc"],
        "identidad": identidad,
        "guardada": guardada,
        "entidad": (composicion or {}).get("entidad") if isinstance(composicion, dict) else None,
        "source": d["meta"].get("source"),
        "row_count": d["meta"].get("row_count", 0),
        "duration_ms": d["meta"].get("duration_ms", 0),
        "truncated": d["meta"].get("truncated", False),
        "error": error,
    }
    os.makedirs(_dir_base(), exist_ok=True)
    with open(_ruta_indice(), "a", encoding="utf-8") as fh:
        fh.write(json.dumps(linea, ensure_ascii=False, default=str) + "\n")
    return run_id


def historial(guardada=None, desde=None, hasta=None, limite=200):
    ruta = _ruta_indice()
    if not os.path.exists(ruta):
        return []
    filas = []
    with open(ruta, encoding="utf-8") as fh:
        for linea in fh:
            linea = linea.strip()
            if not linea:
                continue
            try:
                d = json.loads(linea)
            except ValueError:
                continue  # una línea corrupta no invalida el resto del índice
            if guardada and d.get("guardada") != guardada:
                continue
            if desde and d.get("ts_utc", "") < desde:
                continue
            if hasta and d.get("ts_utc", "") > hasta:
                continue
            filas.append(d)
    filas.sort(key=lambda d: d.get("ts_utc", ""), reverse=True)
    return filas[:limite]


def leer(run_id):
    if not _id_valido(run_id):
        raise ValueError("run_id inválido: %r" % run_id)
    base = _dir_base()
    anio, mes = run_id[0:4], run_id[4:6]
    ruta = os.path.join(base, anio, mes, run_id + ".json")
    if not os.path.exists(ruta):
        return None
    with open(ruta, encoding="utf-8") as fh:
        return json.load(fh)


def serie(guardada):
    """Recuento de filas de una composición guardada a lo largo del tiempo."""
    puntos = [
        {"ts_utc": d["ts_utc"], "row_count": d.get("row_count", 0),
         "run_id": d["run_id"], "truncated": d.get("truncated", False)}
        for d in historial(guardada=guardada, limite=10000)
        if not d.get("error")
    ]
    puntos.sort(key=lambda p: p["ts_utc"])
    return puntos


def caducar(dias=None):
    """Borra las FILAS de ejecuciones viejas. El índice se queda: es diminuto."""
    dias = cfg.archive_row_days if dias is None else dias
    if dias <= 0:
        return 0
    corte = time.time() - dias * 86400
    borrados = 0
    for raiz, _, ficheros in os.walk(_dir_base()):
        for f in ficheros:
            if not f.endswith(".json"):
                continue
            ruta = os.path.join(raiz, f)
            try:
                if os.path.getmtime(ruta) < corte:
                    os.remove(ruta)
                    borrados += 1
            except OSError:
                pass
    return borrados


def _id_valido(run_id):
    return (
        isinstance(run_id, str)
        and len(run_id) == 23
        and run_id[16] == "-"
        and run_id[:8].isdigit()
        and all(c in _ALFABETO for c in run_id[17:])
    )


def _escribir_atomico(ruta, obj):
    tmp = ruta + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(obj, fh, ensure_ascii=False, default=str)
    os.replace(tmp, ruta)
