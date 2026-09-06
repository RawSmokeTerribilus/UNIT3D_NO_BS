"""Carga del modelo curado.

El modelo es el corazón del panel: es donde viven las trampas. Si el campo
«Ratio» está DEFINIDO como ROUND(...,2), dividir sin redondear deja de ser una
de las cosas que se pueden componer. Y un campo marcado `secreto` no se puede
elegir para mostrar, así que su valor no tiene por dónde salir.
"""
import glob
import json
import os

DIR_MODELO = os.path.join(os.path.dirname(__file__), "model")


class ModeloError(Exception):
    pass


class Campo:
    def __init__(self, d):
        self.id = d["id"]
        self.etiqueta = d.get("etiqueta", d["id"])
        self.tipo = d.get("tipo", "texto")
        self.col = d.get("col")
        self.expr = d.get("expr")
        self.via = d.get("via")
        self.secreto = bool(d.get("secreto"))
        self.nota = d.get("nota")
        # fuentes que no son MySQL
        self.loki = d.get("loki")          # etiqueta de flujo en Loki
        self.linea = bool(d.get("linea"))  # filtra el texto de la línea
        self.prom = d.get("prom")          # etiqueta de serie en Prometheus
        if not (self.col or self.expr or self.via or self.loki or self.linea
                or self.prom or self.tipo == "metrica"):
            raise ModeloError("campo %s: falta col, expr, via, loki o prom" % self.id)

    def sql_valor(self):
        """La expresión que produce el valor a MOSTRAR."""
        if self.expr:
            return self.expr
        if self.via:
            return "%s.%s" % (self.via.get("alias", "j"), self.via["muestra"])
        return "t.%s" % self.col

    def sql_filtro(self):
        """La expresión contra la que se FILTRA.

        En un campo de catálogo no son la misma: se muestra el nombre del grupo
        y se filtra por su slug, que es estable. Los ids de grupo van +2
        respecto al enum de la aplicación y no se tocan nunca.
        """
        if self.via:
            return "%s.%s" % (self.via.get("alias", "j"), self.via.get("filtra", self.via["muestra"]))
        return self.sql_valor()

    def to_dict(self):
        return {
            "id": self.id, "etiqueta": self.etiqueta, "tipo": self.tipo,
            "secreto": self.secreto, "nota": self.nota,
            "mostrable": not self.secreto,
            # La columna real se expone para que la interfaz pueda casar los
            # `cruces` (que van en nombres de columna) con ids de campo.
            "col": self.col,
            "loki": self.loki, "linea": self.linea, "prom": self.prom,
        }


class Entidad:
    def __init__(self, d):
        self.id = d["id"]
        self.nombre = d.get("nombre", d["id"])
        self.fuente = d.get("fuente", "mysql")
        self.tabla = d.get("tabla")
        self.clave = d.get("clave")
        self.ambito = d.get("ambito")
        self.ambito_etiqueta = d.get("ambito_etiqueta")
        self.notas = d.get("notas", [])
        self.cruces = d.get("cruces", [])
        self.selector_base = d.get("selector_base", {})
        self.campos = {}
        for c in d.get("campos", []):
            campo = Campo(c)
            if campo.id in self.campos:
                raise ModeloError("%s: campo duplicado %s" % (self.id, campo.id))
            self.campos[campo.id] = campo

    def campo(self, cid):
        if cid not in self.campos:
            raise ModeloError(
                "la entidad «%s» no tiene el campo «%s»" % (self.nombre, cid))
        return self.campos[cid]

    def to_dict(self):
        return {
            "id": self.id, "nombre": self.nombre, "fuente": self.fuente,
            "clave": self.clave, "notas": self.notas, "cruces": self.cruces,
            "selector_base": self.selector_base,
            "ambito": self.ambito_etiqueta,
            "campos": [c.to_dict() for c in self.campos.values()],
        }


def cargar(directorio=DIR_MODELO):
    entidades = {}
    for ruta in sorted(glob.glob(os.path.join(directorio, "*.json"))):
        with open(ruta, encoding="utf-8") as fh:
            d = json.load(fh)
        e = Entidad(d)
        entidades[e.id] = e
    if not entidades:
        raise ModeloError("no se cargó ninguna entidad de %s" % directorio)
    return entidades
