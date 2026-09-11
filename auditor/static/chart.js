/* Gráficas del panel. SVG a mano, sin librería: el servicio no tiene
   dependencias y puede estar sin red.

   La forma se elige sola según lo que devuelva la consulta:
     · una sola cifra            -> cifra grande (nunca una barra suelta)
     · categoría + medida        -> barras horizontales, UN SOLO tono
     · fecha + medida            -> líneas
     · cualquier otra cosa       -> sin gráfica; la tabla ya está debajo

   Paleta validada contra la superficie #161b22 (checks de banda de luminosidad,
   croma, separación para daltonismo y contraste: los cinco pasan).
   Un solo tono para una sola serie; la lista categórica sólo cuando hay varias,
   en orden fijo y sin generar colores más allá del octavo. */
'use strict';

const VIZ = {
  serie1: '#3987e5',
  categoricos: ['#3987e5', '#d95926', '#199e70', '#c98500',
                '#d55181', '#008300', '#9085e9', '#e66767'],
  tinta: '#c9d1d9',
  tintaSuave: '#8b949e',
  linea: '#30363d',
  superficie: '#161b22',
  MAX_SERIES: 8,
};

const svgEl = (t, a = {}) => {
  const n = document.createElementNS('http://www.w3.org/2000/svg', t);
  for (const [k, v] of Object.entries(a)) if (v != null) n.setAttribute(k, v);
  return n;
};
const esNum = (v) => typeof v === 'number' && isFinite(v);
const esFecha = (v) => typeof v === 'string' && /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test(v);
const fmt = (v) => (esNum(v) ? v.toLocaleString('es-ES') : String(v));

/* ------------------------------------------------------- elección de forma */
/* Una columna que vale lo mismo en todas las filas no distingue nada: sólo
   alarga el nombre de la serie. Las métricas de cAdvisor traen docenas. */
const varia = (rows, i) => new Set(rows.map((r) => String(r[i]))).size > 1;

export function elegirForma(columns, rows) {
  if (!rows.length) return null;
  const idxNum = columns.map((c, i) => i).filter((i) => rows.every((r) => esNum(r[i])));
  if (!idxNum.length) return null;
  const medida = idxNum[idxNum.length - 1];
  const etiquetas = columns.map((c, i) => i).filter((i) => i !== medida);

  if (rows.length === 1 && idxNum.length === 1) return { tipo: 'cifra', medida, etiquetas };

  const iFecha = columns.findIndex((c, i) => i !== medida && rows.every((r) => esFecha(r[i])));
  if (iFecha >= 0) {
    const series = etiquetas.filter((i) => i !== iFecha && varia(rows, i));
    return { tipo: 'linea', medida, x: iFecha, series };
  }
  if (etiquetas.length >= 1 && rows.length <= 60) {
    const utiles = etiquetas.filter((i) => varia(rows, i));
    return { tipo: 'barras', medida, etiqueta: utiles.length ? utiles : etiquetas };
  }
  return null;
}

export function pintarGrafica(cont, columns, rows) {
  cont.textContent = '';
  const forma = elegirForma(columns, rows);
  if (!forma) return false;
  if (forma.tipo === 'cifra') cifra(cont, columns, rows, forma);
  else if (forma.tipo === 'barras') barras(cont, columns, rows, forma);
  else linea(cont, columns, rows, forma);
  return true;
}

/* ------------------------------------------------------------ cifra grande */
function cifra(cont, columns, rows, f) {
  const caja = document.createElement('div');
  caja.className = 'cifra';
  const n = document.createElement('div');
  n.className = 'cifra-valor';
  n.textContent = fmt(rows[0][f.medida]);
  caja.appendChild(n);
  const pie = f.etiquetas.map((i) => columns[i] + ': ' + rows[0][i]).concat(columns[f.medida]);
  const p = document.createElement('div');
  p.className = 'cifra-pie';
  p.textContent = pie.join(' · ');
  caja.appendChild(p);
  cont.appendChild(caja);
}

/* ------------------------------------------------- barras horizontales */
function barras(cont, columns, rows, f) {
  const datos = rows
    .map((r) => ({ etiqueta: f.etiqueta.map((i) => r[i]).join(' · '), valor: r[f.medida] }))
    .sort((a, b) => b.valor - a.valor);

  const alto = 22, hueco = 6, margenIzq = 190, margenDer = 90, margenSup = 8, margenInf = 26;
  const anchoTotal = Math.max(cont.clientWidth || 900, 520);
  const anchoPlot = anchoTotal - margenIzq - margenDer;
  const altoTotal = margenSup + datos.length * (alto + hueco) + margenInf;
  const max = Math.max(...datos.map((d) => d.valor), 0) || 1;
  const x = (v) => (v / max) * anchoPlot;

  const svg = svgEl('svg', { width: '100%', height: altoTotal,
    viewBox: `0 0 ${anchoTotal} ${altoTotal}`, role: 'img' });
  svg.setAttribute('aria-label', columns[f.medida] + ' por ' + f.etiqueta.map((i) => columns[i]).join(' y '));

  // rejilla discreta, línea continua — nada de discontinuas
  const ticks = 4;
  for (let k = 0; k <= ticks; k++) {
    const vx = margenIzq + (anchoPlot * k) / ticks;
    svg.appendChild(svgEl('line', { x1: vx, y1: margenSup, x2: vx,
      y2: altoTotal - margenInf, stroke: VIZ.linea, 'stroke-width': 1 }));
    const t = svgEl('text', { x: vx, y: altoTotal - 8, fill: VIZ.tintaSuave,
      'font-size': 11, 'text-anchor': 'middle' });
    t.textContent = fmt(Math.round((max * k) / ticks));
    svg.appendChild(t);
  }

  const etiquetarTodas = datos.length <= 15;
  datos.forEach((d, i) => {
    const y = margenSup + i * (alto + hueco);
    const w = Math.max(x(d.valor), d.valor > 0 ? 2 : 0);

    const et = svgEl('text', { x: margenIzq - 10, y: y + alto / 2 + 4,
      fill: VIZ.tinta, 'font-size': 12, 'text-anchor': 'end' });
    et.textContent = String(d.etiqueta).slice(0, 28);
    svg.appendChild(et);

    // extremo redondeado 4px, anclado a la línea base
    const barra = svgEl('rect', { x: margenIzq, y, width: w, height: alto,
      rx: 4, fill: VIZ.serie1 });
    barra.appendChild(svgEl('title')).textContent =
      d.etiqueta + ' — ' + fmt(d.valor) + ' ' + columns[f.medida].toLowerCase();
    svg.appendChild(barra);

    if (etiquetarTodas || i < 5) {
      const v = svgEl('text', { x: margenIzq + w + 8, y: y + alto / 2 + 4,
        fill: VIZ.tintaSuave, 'font-size': 12 });
      v.textContent = fmt(d.valor);
      svg.appendChild(v);
    }
  });
  cont.appendChild(svg);
  if (!etiquetarTodas) {
    const n = document.createElement('div');
    n.className = 'muted grafica-pie';
    n.textContent = 'Se rotulan las 5 mayores; el resto, al pasar por encima o en la tabla.';
    cont.appendChild(n);
  }
}

/* --------------------------------------------------------------- líneas */
function linea(cont, columns, rows, f) {
  const clave = (r) => (f.series.length ? f.series.map((i) => r[i]).join(' · ') : columns[f.medida]);
  const mapa = new Map();
  for (const r of rows) {
    const k = clave(r);
    if (!mapa.has(k)) mapa.set(k, []);
    mapa.get(k).push({ t: new Date(r[f.x].replace(' ', 'T') + 'Z').getTime(), v: r[f.medida] });
  }
  let series = [...mapa.entries()].map(([nombre, puntos]) => ({
    nombre, puntos: puntos.sort((a, b) => a.t - b.t),
  }));
  series.sort((a, b) => Math.max(...b.puntos.map((p) => p.v)) - Math.max(...a.puntos.map((p) => p.v)));

  let recortadas = 0;
  if (series.length > VIZ.MAX_SERIES) {
    // Nunca se genera un noveno color: lo que sobra se dice, no se pinta.
    recortadas = series.length - VIZ.MAX_SERIES;
    series = series.slice(0, VIZ.MAX_SERIES);
  }

  const margenIzq = 70, margenDer = 16, margenSup = 12, margenInf = 34;
  const anchoTotal = Math.max(cont.clientWidth || 900, 520);
  const altoTotal = 260;
  const anchoPlot = anchoTotal - margenIzq - margenDer;
  const altoPlot = altoTotal - margenSup - margenInf;

  const todos = series.flatMap((s) => s.puntos);
  const t0 = Math.min(...todos.map((p) => p.t)), t1 = Math.max(...todos.map((p) => p.t));
  const vmax = Math.max(...todos.map((p) => p.v), 0) || 1;
  const X = (t) => margenIzq + (t1 === t0 ? anchoPlot / 2 : ((t - t0) / (t1 - t0)) * anchoPlot);
  const Y = (v) => margenSup + altoPlot - (v / vmax) * altoPlot;

  const svg = svgEl('svg', { width: '100%', height: altoTotal,
    viewBox: `0 0 ${anchoTotal} ${altoTotal}`, role: 'img' });
  svg.setAttribute('aria-label', columns[f.medida] + ' en el tiempo');

  for (let k = 0; k <= 4; k++) {
    const y = margenSup + (altoPlot * k) / 4;
    svg.appendChild(svgEl('line', { x1: margenIzq, y1: y, x2: anchoTotal - margenDer,
      y2: y, stroke: VIZ.linea, 'stroke-width': 1 }));
    const t = svgEl('text', { x: margenIzq - 8, y: y + 4, fill: VIZ.tintaSuave,
      'font-size': 11, 'text-anchor': 'end' });
    t.textContent = fmt(Math.round(vmax * (1 - k / 4)));
    svg.appendChild(t);
  }
  for (const [tt, anc] of [[t0, 'start'], [t1, 'end']]) {
    const t = svgEl('text', { x: X(tt), y: altoTotal - 12, fill: VIZ.tintaSuave,
      'font-size': 11, 'text-anchor': anc });
    t.textContent = new Date(tt).toISOString().slice(5, 16).replace('T', ' ');
    svg.appendChild(t);
  }

  series.forEach((s, i) => {
    const color = series.length === 1 ? VIZ.serie1 : VIZ.categoricos[i % VIZ.categoricos.length];
    const d = s.puntos.map((p, k) => (k ? 'L' : 'M') + X(p.t) + ' ' + Y(p.v)).join(' ');
    svg.appendChild(svgEl('path', { d, fill: 'none', stroke: color,
      'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));
    for (const p of s.puntos) {
      const c = svgEl('circle', { cx: X(p.t), cy: Y(p.v), r: 5, fill: color,
        stroke: VIZ.superficie, 'stroke-width': 2, opacity: s.puntos.length > 60 ? 0 : 1 });
      c.appendChild(svgEl('title')).textContent =
        s.nombre + ' — ' + fmt(p.v) + '\n' + new Date(p.t).toISOString().slice(0, 16).replace('T', ' ') + ' UTC';
      svg.appendChild(c);
    }
  });
  cont.appendChild(svg);

  // Con dos series o más la leyenda es obligatoria: la identidad no puede ir
  // sólo en el color.
  if (series.length >= 2) {
    const leyenda = document.createElement('div');
    leyenda.className = 'leyenda';
    series.forEach((s, i) => {
      const it = document.createElement('span');
      it.className = 'leyenda-item';
      const punto = document.createElement('span');
      punto.className = 'leyenda-punto';
      punto.style.background = VIZ.categoricos[i % VIZ.categoricos.length];
      it.appendChild(punto);
      it.appendChild(document.createTextNode(s.nombre));
      leyenda.appendChild(it);
    });
    if (recortadas) {
      const n = document.createElement('span');
      n.className = 'muted';
      n.textContent = '+ ' + recortadas + ' series más, sólo en la tabla';
      leyenda.appendChild(n);
    }
    cont.appendChild(leyenda);
  }
}
