"""Compositor → SQL.

Recibe la cadena de pasos que arma la interfaz y emite SQL con parámetros
ligados. El operador nunca escribe SQL; el panel siempre se lo enseña, que es
lo que hace la cosa auditable en vez de mágica.
"""
from query.modelo import ModeloError

ALIAS_BASE = "t"

# Operadores por tipo de campo. La interfaz llena el desplegable con esto, así
# que no puede ofrecerse nada que el compilador no sepa emitir.
OPERADORES = {
    "texto":    ["es", "no es", "contiene", "empieza por", "está vacío", "no está vacío"],
    "numero":   ["=", "≠", "<", "≤", ">", "≥", "entre", "está vacío", "no está vacío"],
    "bytes":    ["=", "≠", "<", "≤", ">", "≥", "entre", "está vacío", "no está vacío"],
    "fecha":    ["antes de", "después de", "entre", "en los últimos días",
                 "hace más de", "está vacío", "no está vacío"],
    "bool":     ["es"],
    "catalogo": ["es", "no es", "es uno de", "no es ninguno de"],
}

_COMPARADOR = {"=": "=", "≠": "<>", "<": "<", "≤": "<=", ">": ">", "≥": ">=",
               "es": "=", "no es": "<>"}

_UMBRAL = {"más de": ">", "al menos": ">=", "menos de": "<",
           "como mucho": "<=", "exactamente": "="}


class CompileError(Exception):
    pass


class Compilado:
    def __init__(self, sql, params, columnas, entidad, avisos):
        self.sql = sql
        self.params = params
        self.columnas = columnas
        self.entidad = entidad
        self.avisos = avisos


def compilar(paso, entidades):
    """Compila un paso del compositor a (sql, params, columnas).

    Sirve para MySQL y para el almacén de IPs, que es SQLite: el dialecto que
    se genera aquí (SELECT, JOIN, GROUP BY, HAVING, marcadores) es el mismo en
    los dos. Lo único que cambia es el marcador de parámetro.
    """
    eid = paso.get("entidad")
    if eid not in entidades:
        raise CompileError("entidad desconocida: %r" % eid)
    ent = entidades[eid]
    if ent.fuente not in ("mysql", "ipstore"):
        raise CompileError(
            "la entidad «%s» es de la fuente %s, que no se consulta con SQL"
            % (ent.nombre, ent.fuente))

    avisos = []
    joins = {}
    params = []

    # Entidades por rasgo: el FROM sale de una subconsulta distinta según lo
    # que se elija comparar. La condición del rasgo se consume aquí y no llega
    # al WHERE.
    origen, paso, avisos_rasgo = _origen(ent, paso)
    avisos.extend(avisos_rasgo)

    mostrar = list(paso.get("mostrar") or [])
    agrupar = list(paso.get("agrupar") or [])
    calcular = list(paso.get("calcular") or [])

    if not mostrar and not agrupar and not calcular:
        mostrar = [c.id for c in ent.campos.values() if not c.secreto][:6]
        avisos.append("Sin columnas elegidas: se muestran las primeras %d." % len(mostrar))

    # --- el candado, aquí: un secreto no se proyecta jamás -------------------
    for cid in mostrar + agrupar + [c.get("campo") for c in calcular if c.get("campo")]:
        if cid is None:
            continue
        campo = ent.campo(cid)
        if campo.secreto:
            raise CompileError(
                "«%s» es un campo secreto: se puede usar como condición, no se "
                "puede mostrar ni agrupar. Para saber si tiene valor, filtra por "
                "él con «es sí» o «es no»." % campo.etiqueta)

    seleccion = []
    columnas = []

    if agrupar or calcular:
        for cid in agrupar:
            campo = ent.campo(cid)
            _registrar_join(campo, joins)
            seleccion.append("%s AS %s" % (campo.sql_valor(), _ident(campo.etiqueta)))
            columnas.append(campo.etiqueta)
        for calc in calcular:
            fn = (calc.get("fn") or "contar").lower()
            cid = calc.get("campo")
            if fn in ("contar", "count"):
                seleccion.append("COUNT(*) AS %s" % _ident("Cuántos"))
                columnas.append("Cuántos")
                continue
            campo = ent.campo(cid)
            _registrar_join(campo, joins)
            sqlfn = {"sumar": "SUM", "media": "AVG", "minimo": "MIN",
                     "maximo": "MAX", "distintos": "COUNT(DISTINCT"}.get(fn)
            if not sqlfn:
                raise CompileError("cálculo desconocido: %r" % fn)
            if fn == "distintos":
                expr = "COUNT(DISTINCT %s)" % campo.sql_valor()
                etiqueta = "%s (distintos)" % campo.etiqueta
            else:
                expr = "%s(%s)" % (sqlfn, campo.sql_valor())
                etiqueta = "%s (%s)" % (campo.etiqueta, fn)
            seleccion.append("%s AS %s" % (expr, _ident(etiqueta)))
            columnas.append(etiqueta)
    else:
        for cid in mostrar:
            campo = ent.campo(cid)
            _registrar_join(campo, joins)
            seleccion.append("%s AS %s" % (campo.sql_valor(), _ident(campo.etiqueta)))
            columnas.append(campo.etiqueta)

    # --- condiciones ---------------------------------------------------------
    donde = []
    if ent.ambito:
        donde.append("(%s)" % _cualificar(ent.ambito))
        if ent.ambito_etiqueta:
            avisos.append("Ámbito aplicado: %s (%s)." % (ent.ambito_etiqueta, ent.ambito))

    arbol = paso.get("condiciones")
    if arbol:
        sql_cond = _condiciones(arbol, ent, joins, params)
        if sql_cond:
            donde.append(sql_cond)

    enlace = paso.get("_enlace_valores")
    if enlace:
        campo = ent.campo(enlace["campo"])
        _registrar_join(campo, joins)
        valores = enlace["valores"]
        if not valores:
            donde.append("1 = 0")
            avisos.append("El paso anterior no devolvió ninguna clave: este paso sale vacío.")
        else:
            donde.append("%s IN (%s)" % (campo.sql_filtro(), ", ".join(["%s"] * len(valores))))
            params.extend(valores)

    # --- montaje -------------------------------------------------------------
    sql = "SELECT\n  %s\nFROM %s AS %s" % (",\n  ".join(seleccion), origen, ALIAS_BASE)
    for j in joins.values():
        sql += "\n%s" % j
    if donde:
        sql += "\nWHERE " + "\n  AND ".join(donde)
    if agrupar:
        sql += "\nGROUP BY " + ", ".join(str(i + 1) for i in range(len(agrupar)))

    # «Sólo grupos con más de N»: es la forma de toda auditoría de coincidencias
    # —multicuenta, espías, granjas—, y sin esto no se puede preguntar.
    umbral = paso.get("umbral")
    if umbral:
        if not calcular:
            raise CompileError(
                "«sólo grupos con…» necesita un cálculo: añade «contar» o "
                "«contar distintos» para saber de qué se ponen más de N.")
        op = _UMBRAL.get(umbral.get("op", "más de"))
        if not op:
            raise CompileError(
                "umbral desconocido: %r. Vale %s"
                % (umbral.get("op"), ", ".join(_UMBRAL)))
        try:
            n = int(umbral.get("valor"))
        except (TypeError, ValueError):
            raise CompileError("el umbral tiene que ser un número entero")
        # Se compara contra el ALIAS del cálculo, que MySQL admite en HAVING y
        # evita repetir la expresión.
        sql += "\nHAVING %s %s %%s" % (_ident(columnas[-1]), op)
        params.append(n)
    orden = paso.get("ordenar")
    if orden:
        sentido = "DESC" if str(orden.get("sentido", "asc")).lower().startswith("desc") else "ASC"
        cid = orden.get("campo")
        if agrupar or calcular:
            # La sesión corre con ONLY_FULL_GROUP_BY (viene en ANSI, igual que la
            # aplicación): al agrupar sólo se puede ordenar por una columna
            # agrupada o por el cálculo. Se ordena por posición para no repetir
            # la expresión.
            if cid in ("_calculo", None) or cid not in agrupar:
                if cid not in ("_calculo", None):
                    avisos.append(
                        "Al agrupar no se puede ordenar por «%s», que no está en el "
                        "agrupado: se ordena por el cálculo." % ent.campo(cid).etiqueta)
                sql += "\nORDER BY %d %s" % (len(seleccion), sentido)
            else:
                sql += "\nORDER BY %d %s" % (agrupar.index(cid) + 1, sentido)
        else:
            sql += "\nORDER BY %s %s" % (ent.campo(cid).sql_valor(), sentido)

    return Compilado(sql, tuple(params), columnas, ent, avisos)


def _origen(ent, paso):
    """Devuelve (FROM, paso sin la condición de rasgo, avisos)."""
    if not ent.rasgos:
        return _ident(ent.tabla), paso, []

    elegido, resto = _extraer_rasgo(paso.get("condiciones"), ent)
    if not elegido:
        raise CompileError(
            "hay que elegir qué comparar: añade la condición «Rasgo compartido "
            "es …». Vale %s." % ", ".join(ent.rasgos))
    if elegido not in ent.rasgos:
        raise CompileError("rasgo desconocido: %r. Vale %s"
                           % (elegido, ", ".join(ent.rasgos)))

    paso = dict(paso)
    paso["condiciones"] = resto
    avisos = []
    nota = ent.rasgos[elegido].get("nota")
    if nota:
        avisos.append(nota)

    # Un racimo de uno no es una coincidencia: si nadie dice lo contrario, se
    # filtran los de 2 o más y se avisa de que se ha hecho.
    if not _menciona(resto, "usuarios"):
        extra = {"campo": "usuarios", "op": "≥", "valor": 2}
        paso["condiciones"] = {"y": ([resto] if resto else []) + [extra]}
        avisos.append("Sin condición sobre cuántos lo comparten: se muestran "
                      "los de 2 o más.")
    return "(%s)" % ent.rasgos[elegido]["sql"], paso, avisos


def _extraer_rasgo(nodo, ent):
    """Saca la hoja del campo de tipo `rasgo` y devuelve (valor, resto)."""
    if not nodo:
        return None, None
    if "y" in nodo or "o" in nodo:
        clave = "y" if "y" in nodo else "o"
        elegido, hijos = None, []
        for n in nodo[clave]:
            e, r = _extraer_rasgo(n, ent)
            elegido = elegido or e
            if r:
                hijos.append(r)
        if not hijos:
            return elegido, None
        if len(hijos) == 1:
            return elegido, hijos[0]
        return elegido, {clave: hijos}
    campo = ent.campos.get(nodo.get("campo"))
    if campo is not None and campo.tipo == "rasgo":
        return nodo.get("valor"), None
    return None, nodo


def _menciona(nodo, cid):
    if not nodo:
        return False
    if "y" in nodo or "o" in nodo:
        return any(_menciona(n, cid) for n in nodo.get("y", nodo.get("o", [])))
    return nodo.get("campo") == cid


def _registrar_join(campo, joins):
    if not campo.via:
        return
    via = campo.via
    alias = via.get("alias", "j")
    if alias in joins:
        return
    joins[alias] = "LEFT JOIN %s AS %s ON %s.%s = %s.%s" % (
        _ident(via["tabla"]), alias,
        ALIAS_BASE, via["por"],
        alias, via.get("clave_remota", "id"),
    )


def _condiciones(nodo, ent, joins, params):
    if "y" in nodo:
        partes = [_condiciones(n, ent, joins, params) for n in nodo["y"]]
        partes = [p for p in partes if p]
        return "(%s)" % " AND ".join(partes) if partes else ""
    if "o" in nodo:
        partes = [_condiciones(n, ent, joins, params) for n in nodo["o"]]
        partes = [p for p in partes if p]
        return "(%s)" % " OR ".join(partes) if partes else ""
    return _hoja(nodo, ent, joins, params)


def _hoja(nodo, ent, joins, params):
    campo = ent.campo(nodo["campo"])
    _registrar_join(campo, joins)
    op = nodo.get("op", "es")
    valor = nodo.get("valor")
    izq = campo.sql_filtro()

    permitidos = OPERADORES.get(campo.tipo, OPERADORES["texto"])
    if op not in permitidos:
        raise CompileError(
            "«%s» no admite el operador «%s». Admite: %s"
            % (campo.etiqueta, op, ", ".join(permitidos)))

    if op == "está vacío":
        return "(%s IS NULL)" % izq
    if op == "no está vacío":
        return "(%s IS NOT NULL)" % izq

    # Una condición sin valor NO se emite. `t.id = \'\'` devuelve cero filas y
    # nadie sabe por qué: es exactamente el canal que miente, esta vez en la
    # cara del operador. Mejor un error que diga qué falta.
    if valor is None or valor == "" or (isinstance(valor, (list, tuple)) and not valor):
        raise CompileError(
            "la condición sobre «%s» está sin valor. Pon uno, elige «está vacío» "
            "o quita la condición." % campo.etiqueta)

    if campo.tipo == "bool":
        ciertos = (True, 1, "1", "si", "sí", "true", "yes")
        falsos = (False, 0, "0", "no", "false")
        if valor in ciertos:
            cierto = True
        elif valor in falsos:
            cierto = False
        else:
            raise CompileError(
                "«%s» sólo admite sí o no, y llegó %r." % (campo.etiqueta, valor))
        if campo.expr:
            return "(%s)" % campo.expr if cierto else "(NOT (%s))" % campo.expr
        params.append(1 if cierto else 0)
        return "(%s = %%s)" % izq

    if op == "contiene":
        params.append("%" + str(valor) + "%")
        return "(%s LIKE %%s)" % izq
    if op == "empieza por":
        params.append(str(valor) + "%")
        return "(%s LIKE %%s)" % izq

    if op in ("es uno de", "no es ninguno de"):
        vals = valor if isinstance(valor, (list, tuple)) else [valor]
        if not vals:
            return "1 = 0" if op == "es uno de" else ""
        params.extend(vals)
        marcas = ", ".join(["%s"] * len(vals))
        return "(%s %sIN (%s))" % (izq, "" if op == "es uno de" else "NOT ", marcas)

    if op == "entre":
        lo, hi = valor
        params.extend([lo, hi])
        return "(%s BETWEEN %%s AND %%s)" % izq

    if op == "antes de":
        params.append(valor)
        return "(%s < %%s)" % izq
    if op == "después de":
        params.append(valor)
        return "(%s > %%s)" % izq
    if op == "en los últimos días":
        params.append(int(valor))
        return "(%s >= UTC_TIMESTAMP() - INTERVAL %%s DAY)" % izq
    if op == "hace más de":
        params.append(int(valor))
        return "(%s < UTC_TIMESTAMP() - INTERVAL %%s DAY)" % izq

    cmp_ = _COMPARADOR.get(op)
    if not cmp_:
        raise CompileError("operador no soportado: %r" % op)
    params.append(valor)
    return "(%s %s %%s)" % (izq, cmp_)


def _cualificar(expr):
    """Cualifica una expresión de ámbito escrita en corto (`deleted_at IS NULL`)."""
    partes = expr.split()
    if partes and "." not in partes[0] and partes[0].isidentifier():
        partes[0] = "%s.%s" % (ALIAS_BASE, partes[0])
    return " ".join(partes)


def a_sqlite(sql):
    """Traduce el SQL generado al dialecto de SQLite.

    Sólo dos diferencias en lo que emite este compilador: el marcador de
    parámetro y el acento grave, que SQLite entiende pero es más limpio
    normalizar a comillas dobles.
    """
    return sql.replace("%s", "?").replace("`", '"')


def _ident(nombre):
    """Comilla un identificador.

    La sesión corre con sql_mode=ANSI, donde la comilla doble es identificador
    y no cadena — igual que la aplicación. Se usa el acento grave, que vale en
    los dos modos, para que estas cadenas no dependan del sql_mode.
    """
    return "`%s`" % str(nombre).replace("`", "")


# ---------------------------------------------------------------- LogQL / PromQL
def compilar_loki(paso, ent):
    """Compositor → LogQL.

    Las condiciones sobre etiquetas entran en el selector de flujo, que es lo
    barato. El filtro por texto de la línea va después, que es lo caro.
    """
    selector = dict(ent.selector_base)
    filtros_linea = []
    avisos = []

    for hoja in _hojas(paso.get("condiciones")):
        campo = ent.campo(hoja["campo"])
        op = hoja.get("op", "es")
        valor = hoja.get("valor")
        if campo.linea:
            if op in ("es", "contiene"):
                filtros_linea.append('|= "%s"' % _escapa(valor))
            elif op == "no es":
                filtros_linea.append('!= "%s"' % _escapa(valor))
            else:
                raise CompileError(
                    "sobre el texto de la línea sólo valen «contiene» y «no es»")
        elif campo.loki:
            if op in ("es",):
                selector[campo.loki] = ("=", str(valor))
            elif op in ("no es",):
                selector[campo.loki] = ("!=", str(valor))
            else:
                raise CompileError(
                    "«%s» es una etiqueta de Loki: sólo «es» y «no es»." % campo.etiqueta)
        else:
            raise CompileError("«%s» no se puede consultar en Loki" % campo.etiqueta)

    partes = []
    for k, v in selector.items():
        op, val = v if isinstance(v, tuple) else ("=", v)
        partes.append('%s%s"%s"' % (k, op, _escapa(val)))
    logql = "{%s}" % ", ".join(partes)
    if filtros_linea:
        logql += " " + " ".join(filtros_linea)

    agregada = bool(paso.get("calcular"))
    if agregada:
        agrupar = [ent.campo(c).loki for c in (paso.get("agrupar") or [])]
        if any(g is None for g in agrupar):
            raise CompileError("en Loki sólo se puede agrupar por etiquetas de flujo")
        horas = float((paso.get("ventana") or {}).get("ultimas_horas") or 1)
        rango = "%dm" % max(1, int(horas * 60))
        interior = "count_over_time(%s[%s])" % (logql, rango)
        logql = ("sum by (%s) (%s)" % (", ".join(agrupar), interior)) if agrupar \
            else "sum(%s)" % interior
        avisos.append("Se cuenta sobre la ventana entera (%s)." % rango)

    return Compilado(logql, (), [], ent, avisos), agregada


def compilar_prom(paso, ent):
    """Compositor → PromQL."""
    metrica = None
    etiquetas = []
    for hoja in _hojas(paso.get("condiciones")):
        campo = ent.campo(hoja["campo"])
        op = hoja.get("op", "es")
        valor = hoja.get("valor")
        signo = {"es": "=", "no es": "!=", "contiene": "=~"}.get(op)
        if signo is None:
            raise CompileError(
                "«%s» en Prometheus admite «es», «no es» y «contiene»." % campo.etiqueta)
        if campo.tipo == "metrica":
            if signo != "=":
                raise CompileError("la métrica se elige con «es»")
            metrica = str(valor)
        elif campo.prom:
            patron = ".*%s.*" % _escapa(valor) if signo == "=~" else _escapa(valor)
            etiquetas.append('%s%s"%s"' % (campo.prom, signo, patron))
        else:
            raise CompileError("«%s» no se puede consultar en Prometheus" % campo.etiqueta)

    if not metrica:
        raise CompileError(
            "hace falta elegir una métrica: añade la condición «Métrica es …».")
    if not _METRICA_OK.match(metrica):
        raise CompileError("nombre de métrica inválido: %r" % metrica)

    promql = metrica + ("{%s}" % ", ".join(etiquetas) if etiquetas else "")
    calc = (paso.get("calcular") or [{}])[0].get("fn")
    agrupar = [ent.campo(c).prom for c in (paso.get("agrupar") or [])]
    fn = {"sumar": "sum", "media": "avg", "minimo": "min", "maximo": "max",
          "contar": "count"}.get(calc)
    if fn:
        promql = ("%s by (%s) (%s)" % (fn, ", ".join(a for a in agrupar if a), promql)) \
            if any(agrupar) else "%s(%s)" % (fn, promql)
    return Compilado(promql, (), [], ent, []), bool(paso.get("rango"))


_METRICA_OK = __import__("re").compile(r"^[A-Za-z_:][A-Za-z0-9_:]*$")


def _hojas(nodo):
    """Aplana el árbol de condiciones. Loki y Prometheus no tienen OR de
    condiciones como tal, así que aquí sólo se admite la conjunción."""
    if not nodo:
        return []
    if "y" in nodo:
        salida = []
        for n in nodo["y"]:
            salida.extend(_hojas(n))
        return salida
    if "o" in nodo:
        raise CompileError(
            "en logs y métricas las condiciones se combinan sólo con Y, no con O.")
    return [nodo]


def _escapa(v):
    return str(v).replace("\\", "\\\\").replace('"', '\\"')


def compilar_binlog(paso, ent):
    """Compositor → parámetros de lectura de binlog.

    No hay lenguaje que emitir aquí: lo que se compone es qué tabla, qué fila y
    qué ventana. El resto lo hace mysqlbinlog.
    """
    args = {"tabla": None, "clave_col": "id", "clave_val": None}
    for hoja in _hojas(paso.get("condiciones")):
        campo = ent.campo(hoja["campo"])
        op = hoja.get("op", "es")
        if op not in ("es", "="):
            raise CompileError(
                "«%s» aquí sólo admite «es»: el binlog se lee por tabla y fila, "
                "no se filtra como una consulta." % campo.etiqueta)
        destino = {"tabla": "tabla", "clave": "clave_val", "clave_col": "clave_col"}.get(
            getattr(campo, "binlog", None))
        if not destino:
            raise CompileError("«%s» no sirve para leer el binlog" % campo.etiqueta)
        args[destino] = hoja.get("valor")
    if not args["tabla"]:
        raise CompileError(
            "hace falta decir la tabla: añade la condición «Tabla es users» (o la "
            "que sea). Leer el binlog entero son 1,7 GB por día.")
    return args
