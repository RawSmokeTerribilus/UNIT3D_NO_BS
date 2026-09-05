"""Candado del modo crudo.

El candado de verdad está en el modelo: un campo marcado `secreto` no se puede
ni elegir para mostrar, así que el compositor NO SABE emitir su valor. Esto de
aquí es la red por debajo, para cuando alguien escribe SQL a mano.

Límite honesto: es un tokenizador, no un parser. `SELECT CONCAT(password,'')`
lo esquiva. Sirve contra el volcado accidental a la propia pantalla, que es la
amenaza real; no se vende como control contra alguien decidido.
"""
import re

from config import cfg

PRIMERAS_PALABRAS = ("SELECT", "WITH", "SHOW", "EXPLAIN", "DESCRIBE", "DESC")

_CADENAS = re.compile(r"'(?:[^'\\]|\\.|'')*'|\"(?:[^\"\\]|\\.|\"\")*\"", re.S)
_COMENTARIOS = re.compile(r"/\*.*?\*/|--[^\n]*|#[^\n]*", re.S)
_IDENT = re.compile(r"[A-Za-z_][A-Za-z0-9_$]*")


class GuardError(Exception):
    pass


def revisar(sql):
    """Lanza GuardError si la sentencia no puede ejecutarse. Devuelve avisos."""
    if not sql or not sql.strip():
        raise GuardError("consulta vacía")

    desnudo = _desnudar(sql)

    if ";" in desnudo.strip().rstrip(";"):
        raise GuardError(
            "sólo se admite UNA sentencia. Quita el «;» del medio.")

    m = _IDENT.match(desnudo.strip())
    if not m or m.group(0).upper() not in PRIMERAS_PALABRAS:
        primera = m.group(0).upper() if m else "?"
        raise GuardError(
            "«%s» no es de lectura. Sólo se admite %s."
            % (primera, ", ".join(PRIMERAS_PALABRAS)))

    if re.search(r"\bmysql\s*\.", desnudo, re.I):
        raise GuardError(
            "el esquema «mysql» está fuera de alcance. La cuenta auditro "
            "tampoco tiene permiso, pero mejor decirlo aquí que con un 1142.")

    avisos = []
    for nombre in _secretos_en_proyeccion(desnudo):
        raise GuardError(
            "«%s» es un secreto: su valor no sale por pantalla. Sí puedes "
            "interrogarlo — en el WHERE («WHERE %s IS NOT NULL») o contándolo "
            "(«COUNT(%s)»)." % (nombre, nombre, nombre))

    if not re.search(r"\bLIMIT\b", desnudo, re.I):
        avisos.append("Sin LIMIT propio: se aplica el tope del panel.")
    return avisos


def _desnudar(sql):
    """Quita comentarios y contenido de cadenas, conservando las posiciones."""
    s = _COMENTARIOS.sub(lambda m: " " * len(m.group(0)), sql)
    s = _CADENAS.sub(lambda m: "'" + " " * (len(m.group(0)) - 2) + "'", s)
    return s


def _secretos_en_proyeccion(desnudo):
    """Identificadores secretos que aparecen en la lista del SELECT.

    Sólo se mira el tramo entre el primer SELECT y su FROM a profundidad cero:
    lo que va en WHERE, HAVING o GROUP BY es legítimo y no se toca.
    """
    tramo = _tramo_proyeccion(desnudo)
    if tramo is None:
        return []
    encontrados = []
    for m in _IDENT.finditer(tramo):
        bajo = m.group(0).lower()
        if bajo not in cfg.secretos or bajo in encontrados:
            continue
        # COUNT(secreto) no revela ningún valor: es «interrogar», no
        # «proyectar». Cualquier otra función sí puede revelarlo
        # —CONCAT(password,'') sería un volcado— así que sólo pasa COUNT.
        if _envoltura(tramo, m.start()) == "COUNT":
            continue
        encontrados.append(bajo)
    return encontrados


def _envoltura(tramo, pos):
    """Nombre de la función cuyo paréntesis abierto envuelve a `pos`."""
    profundidad = 0
    i = pos - 1
    while i >= 0:
        c = tramo[i]
        if c == ")":
            profundidad += 1
        elif c == "(":
            if profundidad == 0:
                j = i - 1
                while j >= 0 and tramo[j].isspace():
                    j -= 1
                fin = j + 1
                while j >= 0 and (tramo[j].isalnum() or tramo[j] == "_"):
                    j -= 1
                return tramo[j + 1:fin].upper()
            profundidad -= 1
        i -= 1
    return ""


def _tramo_proyeccion(desnudo):
    m = re.search(r"\bSELECT\b", desnudo, re.I)
    if not m:
        return None
    i = m.end()
    profundidad = 0
    for j in range(i, len(desnudo)):
        c = desnudo[j]
        if c == "(":
            profundidad += 1
        elif c == ")":
            profundidad -= 1
        elif profundidad == 0 and desnudo.startswith(("FROM", "from", "From"), j):
            antes = desnudo[j - 1] if j else " "
            despues = desnudo[j + 4] if j + 4 < len(desnudo) else " "
            if not antes.isalnum() and antes != "_" and not despues.isalnum() and despues != "_":
                return desnudo[i:j]
    return desnudo[i:]  # SELECT sin FROM: todo es proyección
