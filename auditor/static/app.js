/* Panel de consultas — compositor. Vanilla JS, sin build, sin dependencias. */
'use strict';

let MODELO = null;      // {entidades:{id:ent}, operadores:{tipo:[...]}, ...}
let PASOS = [];         // estado del compositor
let ULTIMO = null;      // último resultado, para exportar

const $ = (s) => document.querySelector(s);
const el = (tag, attrs = {}, hijos = []) => {
  const n = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === 'class') n.className = v;
    else if (k === 'text') n.textContent = v;
    else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
    else n.setAttribute(k, v);
  }
  for (const h of [].concat(hijos)) n.appendChild(typeof h === 'string' ? document.createTextNode(h) : h);
  return n;
};

async function api(ruta, opciones) {
  const r = await fetch(ruta, opciones);
  const d = await r.json().catch(() => ({ error: 'respuesta ilegible' }));
  if (!r.ok) throw new Error(d.mensaje || d.error || ('HTTP ' + r.status));
  return d;
}

/* ------------------------------------------------------------------ arranque */
async function arrancar() {
  const d = await api('/api/query/model');
  MODELO = { entidades: {}, operadores: d.operadores, topes: d.topes, retencion: d.retencion };
  for (const e of d.entidades) MODELO.entidades[e.id] = e;
  $('#cabecera-info').textContent =
    Object.keys(MODELO.entidades).length + ' entidades · tope ' +
    d.topes.filas + ' filas / ' + (d.topes.ms / 1000) + ' s';
  if (!PASOS.length) PASOS.push(nuevoPaso(Object.keys(MODELO.entidades)[0]));
  pintarPasos();
  pintarHistorial();
}

const nuevoPaso = (entidad) => ({ entidad, condiciones: [], mostrar: [], agrupar: [], calcular: [] });
const campoDe = (ent, cid) => MODELO.entidades[ent].campos.find((c) => c.id === cid);

/* ------------------------------------------------------------- el compositor */
function pintarPasos() {
  const cont = $('#pasos');
  cont.textContent = '';
  PASOS.forEach((paso, i) => {
    if (i > 0) cont.appendChild(el('div', { class: 'enlace', text: '↓ pasa la clave del paso ' + i }));
    cont.appendChild(tarjetaPaso(paso, i));
  });
}

function tarjetaPaso(paso, i) {
  const ent = MODELO.entidades[paso.entidad];
  const caja = el('div', { class: 'paso' });

  const selEnt = el('select', {
    onchange: (e) => { Object.assign(paso, nuevoPaso(e.target.value)); pintarPasos(); },
  });
  for (const id of Object.keys(MODELO.entidades)) {
    const o = el('option', { value: id, text: MODELO.entidades[id].nombre });
    if (id === paso.entidad) o.selected = true;
    selEnt.appendChild(o);
  }

  const cuenta = el('span', { class: 'cuenta', text: paso._cuenta == null ? '' : paso._cuenta + ' filas' });
  caja.appendChild(el('div', { class: 'cabecera' },
    [el('span', { class: 'num', text: 'PASO ' + (i + 1) }), selEnt, cuenta]));

  if (ent.ambito) {
    caja.appendChild(el('div', { class: 'nota', text: 'Ámbito automático: ' + ent.ambito + '.' }));
  }
  for (const n of ent.notas || []) caja.appendChild(el('div', { class: 'nota', text: n }));

  /* condiciones */
  paso.condiciones.forEach((cond, j) => caja.appendChild(lineaCondicion(paso, cond, j)));
  caja.appendChild(el('div', { class: 'linea' }, [
    el('span', { class: 'junta', text: '' }),
    el('button', {
      class: 'icono', text: '+ condición',
      onclick: () => {
        const primero = ent.campos[0];
        paso.condiciones.push({ campo: primero.id, op: opsDe(primero)[0], valor: '', junta: 'Y' });
        pintarPasos();
      },
    }),
  ]));

  /* mostrar */
  caja.appendChild(el('div', { class: 'linea' }, [
    el('span', { class: 'etiqueta', text: 'mostrar' }),
    chipsCampos(paso, 'mostrar'),
  ]));
  /* agrupar y calcular */
  caja.appendChild(el('div', { class: 'linea' }, [
    el('span', { class: 'etiqueta', text: 'agrupar' }),
    chipsCampos(paso, 'agrupar'),
  ]));
  caja.appendChild(el('div', { class: 'linea' }, [
    el('span', { class: 'etiqueta', text: 'calcular' }),
    selCalculo(paso),
  ]));

  return caja;
}

const CATALOGOS = {};
async function cargarCatalogo(entidad, campo) {
  const clave = entidad + '.' + campo;
  if (!CATALOGOS[clave]) {
    CATALOGOS[clave] = api('/api/query/valores?entidad=' + encodeURIComponent(entidad) +
                           '&campo=' + encodeURIComponent(campo)).then((d) => d.valores);
  }
  return CATALOGOS[clave];
}

const opsDe = (campo) => MODELO.operadores[campo.tipo] || MODELO.operadores.texto;

function lineaCondicion(paso, cond, j) {
  const ent = MODELO.entidades[paso.entidad];
  const campo = campoDe(paso.entidad, cond.campo);
  const linea = el('div', { class: 'linea' });

  const junta = el('select', { onchange: (e) => { cond.junta = e.target.value; } });
  for (const v of ['Y', 'O']) {
    const o = el('option', { value: v, text: v });
    if (v === (cond.junta || 'Y')) o.selected = true;
    junta.appendChild(o);
  }
  linea.appendChild(j === 0 ? el('span', { class: 'junta', text: 'donde' }) : junta);

  const selCampo = el('select', {
    onchange: (e) => {
      cond.campo = e.target.value;
      cond.op = opsDe(campoDe(paso.entidad, cond.campo))[0];
      cond.valor = '';
      pintarPasos();
    },
  });
  for (const c of ent.campos) {
    const o = el('option', { value: c.id, text: c.etiqueta + (c.secreto ? ' 🔒' : '') });
    if (c.id === cond.campo) o.selected = true;
    selCampo.appendChild(o);
  }
  linea.appendChild(selCampo);

  const selOp = el('select', { onchange: (e) => { cond.op = e.target.value; pintarPasos(); } });
  for (const op of opsDe(campo)) {
    const o = el('option', { value: op, text: op });
    if (op === cond.op) o.selected = true;
    selOp.appendChild(o);
  }
  linea.appendChild(selOp);

  if (!['está vacío', 'no está vacío'].includes(cond.op)) {
    if (campo.tipo === 'bool') {
      const s = el('select', { onchange: (e) => { cond.valor = e.target.value === 'sí'; } });
      for (const v of ['sí', 'no']) {
        const o = el('option', { value: v, text: v });
        if ((v === 'sí') === (cond.valor === true)) o.selected = true;
        s.appendChild(o);
      }
      linea.appendChild(s);
    } else if (campo.tipo === 'catalogo') {
      // Desplegable con los valores reales. Se ENSEÑA el nombre («Sanguijuela»)
      // y se ENVÍA el slug («leech»), que es lo estable.
      const s = el('select', { onchange: (e) => { cond.valor = e.target.value; } });
      s.appendChild(el('option', { value: '', text: 'cargando…' }));
      linea.appendChild(s);
      cargarCatalogo(paso.entidad, campo.id).then((vals) => {
        s.textContent = '';
        for (const v of vals) {
          const o = el('option', { value: v.valor, text: v.etiqueta });
          if (v.valor === cond.valor) o.selected = true;
          s.appendChild(o);
        }
        if (!cond.valor && vals.length) cond.valor = vals[0].valor;
      }).catch(() => { s.textContent = ''; s.appendChild(el('option', { text: '(no disponible)' })); });
    } else {
      linea.appendChild(el('input', {
        value: cond.valor == null ? '' : cond.valor,
        placeholder: campo.tipo,
        oninput: (e) => { cond.valor = e.target.value; },
      }));
    }
  }

  linea.appendChild(el('button', {
    class: 'icono', text: '×', title: 'quitar',
    onclick: () => { paso.condiciones.splice(j, 1); pintarPasos(); },
  }));

  const envoltorio = el('div', {}, [linea]);
  if (campo.nota) {
    envoltorio.appendChild(el('div', { class: 'nota' + (campo.secreto ? ' secreto' : ''), text: campo.nota }));
  }
  return envoltorio;
}

function chipsCampos(paso, clave) {
  const cont = el('div', { class: 'chips' });
  for (const c of MODELO.entidades[paso.entidad].campos) {
    const puesto = paso[clave].includes(c.id);
    const chip = el('span', {
      class: 'chip' + (puesto ? ' on' : '') + (c.secreto ? ' bloqueado' : ''),
      text: c.etiqueta + (c.secreto ? ' 🔒' : ''),
      title: c.secreto
        ? 'Es un secreto: se puede usar como condición, no se puede mostrar.'
        : (c.nota || ''),
      onclick: () => {
        if (c.secreto) return;
        const k = paso[clave].indexOf(c.id);
        if (k >= 0) paso[clave].splice(k, 1); else paso[clave].push(c.id);
        pintarPasos();
      },
    });
    cont.appendChild(chip);
  }
  return cont;
}

function selCalculo(paso) {
  const cont = el('div', { class: 'chips' });
  const actual = paso.calcular[0] || null;
  const s = el('select', {
    onchange: (e) => {
      paso.calcular = e.target.value ? [{ fn: e.target.value }] : [];
      pintarPasos();
    },
  });
  for (const [v, t] of [['', '(nada)'], ['contar', 'contar filas']]) {
    const o = el('option', { value: v, text: t });
    if (actual && actual.fn === v) o.selected = true;
    if (!actual && v === '') o.selected = true;
    s.appendChild(o);
  }
  cont.appendChild(s);
  return cont;
}

/* ------------------------------------------------------------------ ejecutar */
const aCuerpo = (paso) => ({
  entidad: paso.entidad,
  condiciones: aArbol(paso.condiciones),
  mostrar: paso.mostrar,
  agrupar: paso.agrupar,
  calcular: paso.calcular,
});

function aArbol(conds) {
  if (!conds.length) return null;
  // Se agrupan los tramos unidos por O y el conjunto se une con Y, que es como
  // lo lee cualquiera al mirarlo de arriba abajo.
  const grupos = [[conds[0]]];
  for (let i = 1; i < conds.length; i++) {
    if ((conds[i].junta || 'Y') === 'O') grupos[grupos.length - 1].push(conds[i]);
    else grupos.push([conds[i]]);
  }
  const hoja = (c) => ({ campo: c.campo, op: c.op, valor: c.valor });
  return { y: grupos.map((g) => (g.length === 1 ? hoja(g[0]) : { o: g.map(hoja) })) };
}

async function ejecutar(cuerpo, guardada) {
  $('#estado').textContent = 'ejecutando…';
  try {
    const d = await api('/api/query/run', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ guardada: guardada || null }, cuerpo)),
    });
    ULTIMO = d;
    pintarResultado(d);
    if (PASOS[0]) PASOS[0]._cuenta = d.meta.row_count;
    pintarPasos();
    $('#estado').textContent = '';
  } catch (e) {
    ULTIMO = null;
    pintarError(e.message);
    $('#estado').textContent = '';
  }
  pintarHistorial();
}

function pintarError(msg) {
  $('#resultado-caja').classList.remove('oculto');
  $('#bandas').textContent = '';
  $('#bandas').appendChild(el('div', { class: 'banda malo', text: msg }));
  $('#tabla').querySelector('thead').textContent = '';
  $('#tabla').querySelector('tbody').textContent = '';
  $('#resumen').textContent = '';
}

function pintarResultado(d) {
  $('#resultado-caja').classList.remove('oculto');
  $('#consulta-caja').classList.remove('oculto');
  $('#consulta').textContent = d.meta.consulta_generada || '';

  const bandas = $('#bandas');
  bandas.textContent = '';
  if (d.meta.truncated) {
    bandas.appendChild(el('div', {
      class: 'banda corte',
      text: '⚠ RESULTADO RECORTADO — hay más filas de las que se muestran.',
    }));
  }
  if (d.meta.window_ok === false) {
    bandas.appendChild(el('div', {
      class: 'banda corte',
      text: '⚠ FUERA DE RETENCIÓN — la fuente no guarda tan atrás; lo que falta no existe.',
    }));
  }
  for (const w of d.meta.warnings || []) bandas.appendChild(el('div', { class: 'banda aviso', text: w }));
  if ((d.meta.redacted || []).length) {
    bandas.appendChild(el('div', {
      class: 'banda aviso',
      text: 'Columnas tapadas por ser secretas: ' + d.meta.redacted.join(', '),
    }));
  }

  $('#resumen').textContent =
    d.meta.row_count + ' filas · ' + d.meta.duration_ms + ' ms · ' + (d.run_id || '');

  const thead = $('#tabla').querySelector('thead');
  const tbody = $('#tabla').querySelector('tbody');
  thead.textContent = '';
  tbody.textContent = '';
  thead.appendChild(el('tr', {}, d.columns.map((c) => el('th', { text: c }))));
  for (const fila of d.rows) {
    tbody.appendChild(el('tr', {}, fila.map((v) => {
      if (v === null) return el('td', { class: 'nulo', text: 'nulo' });
      if (v === '••••') return el('td', { class: 'tapado', text: '••••' });
      if (typeof v === 'number') return el('td', { class: 'num', text: String(v) });
      return el('td', { text: String(v) });
    })));
  }
}

/* ----------------------------------------------------------------- historial */
async function pintarHistorial() {
  let d;
  try { d = await api('/api/query/history?limit=25'); } catch (e) { return; }
  const t = $('#historial');
  const thead = t.querySelector('thead');
  const tbody = t.querySelector('tbody');
  thead.textContent = '';
  tbody.textContent = '';
  thead.appendChild(el('tr', {}, ['cuándo (UTC)', 'quién', 'guardada', 'filas', 'ms', 'resultado']
    .map((c) => el('th', { text: c }))));
  for (const e of d.ejecuciones) {
    tbody.appendChild(el('tr', {}, [
      el('td', { text: e.ts_utc.slice(0, 19).replace('T', ' ') }),
      el('td', { text: e.identidad }),
      el('td', { text: e.guardada || '—' }),
      el('td', { class: 'num', text: e.error ? '—' : String(e.row_count) }),
      el('td', { class: 'num', text: e.error ? '—' : String(e.duration_ms) }),
      el('td', { class: e.error ? 'tapado' : '', text: e.error ? e.error.slice(0, 80) : 'ok' }),
    ]));
  }
}

/* ----------------------------------------------------------------- exportar */
function descargar(nombre, texto, tipo) {
  const a = el('a', { href: URL.createObjectURL(new Blob([texto], { type: tipo })), download: nombre });
  document.body.appendChild(a); a.click(); a.remove();
}
const csvCampo = (v) => {
  if (v === null) return '';
  const s = String(v);
  return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
};

/* -------------------------------------------------------------------- eventos */
$('#btn-ejecutar').addEventListener('click', () => ejecutar({ paso: aCuerpo(PASOS[0]) }));
$('#btn-ejecutar-crudo').addEventListener('click', () => ejecutar({ sql: $('#sql-crudo').value }));
$('#btn-crudo').addEventListener('click', () => $('#crudo').classList.toggle('oculto'));
$('#btn-limpiar').addEventListener('click', () => {
  PASOS = [nuevoPaso(Object.keys(MODELO.entidades)[0])];
  $('#resultado-caja').classList.add('oculto');
  $('#consulta-caja').classList.add('oculto');
  pintarPasos();
});
$('#btn-ver').addEventListener('click', async () => {
  try {
    const d = await api('/api/query/compile', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ paso: aCuerpo(PASOS[0]) }),
    });
    $('#consulta-caja').classList.remove('oculto');
    $('#consulta').textContent = d.consulta +
      (d.parametros.length ? '\n-- parámetros: ' + JSON.stringify(d.parametros) : '');
  } catch (e) { pintarError(e.message); }
});
$('#btn-csv').addEventListener('click', () => {
  if (!ULTIMO) return;
  const filas = [ULTIMO.columns.map(csvCampo).join(',')]
    .concat(ULTIMO.rows.map((r) => r.map(csvCampo).join(',')));
  descargar((ULTIMO.run_id || 'consulta') + '.csv', filas.join('\n'), 'text/csv');
});
$('#btn-json').addEventListener('click', () => {
  if (!ULTIMO) return;
  descargar((ULTIMO.run_id || 'consulta') + '.json', JSON.stringify(ULTIMO, null, 2), 'application/json');
});

arrancar().catch((e) => { $('#cabecera-info').textContent = 'error: ' + e.message; });
