"""Cliente HTTP mínimo para Loki y Prometheus. Sólo stdlib."""
import json
import urllib.error
import urllib.parse
import urllib.request


class FuenteHTTPError(Exception):
    def __init__(self, fuente, detalle, url=""):
        self.fuente = fuente
        self.detalle = detalle
        self.url = url
        super().__init__("%s: %s" % (fuente, detalle))


def pedir(fuente, base, ruta, params, timeout=30):
    """GET con JSON de vuelta. Los errores suben con su cuerpo, no vacíos."""
    url = base + ruta + "?" + urllib.parse.urlencode(params)
    try:
        with urllib.request.urlopen(url, timeout=timeout) as r:
            return json.load(r)
    except urllib.error.HTTPError as e:
        cuerpo = ""
        try:
            cuerpo = e.read().decode("utf-8", "replace")[:400]
        except Exception:
            pass
        raise FuenteHTTPError(fuente, "HTTP %s %s" % (e.code, cuerpo.strip()), url)
    except urllib.error.URLError as e:
        raise FuenteHTTPError(fuente, "no se pudo conectar: %s" % e.reason, url)
    except ValueError as e:
        raise FuenteHTTPError(fuente, "respuesta ilegible: %s" % e, url)
