/* NOBS · la película del socio, en su perfil.
 * No carga nada hasta que se pulsa ▶: entonces trae el motor y las escenas que falten y las
 * encadena con los datos de <script type="application/json" data-pelicula-datos>, que trae
 * { orden: [ids], datos: { id: {...} }, versiones: { fichero: v } }.
 */
(function () {
  'use strict';

  function cargar(src) {
    return new Promise((ok, mal) => {
      const s = document.createElement('script');
      s.src = src;
      s.onload = ok;
      s.onerror = () => mal(new Error('No se pudo cargar ' + src));
      document.head.appendChild(s);
    });
  }

  async function preparar(base, cfg) {
    const v = f => '?v=' + encodeURIComponent((cfg.versiones || {})[f] || '1');
    if (!window.Escenas) await cargar(base + 'motor.js' + v('motor.js'));
    const hay = new Set(Escenas.lista().map(d => d.id));
    for (const id of cfg.orden) if (!hay.has(id)) await cargar(base + id + '.js' + v(id + '.js'));
  }

  function montarPelicula(caja) {
    const json = caja.querySelector('script[data-pelicula-datos]');
    const cv = caja.querySelector('canvas[data-pelicula-lienzo]');
    const boton = caja.querySelector('[data-pelicula-play]');
    const portada = caja.querySelector('[data-pelicula-portada]');
    const barra = caja.querySelector('[data-pelicula-barra]');
    const rotulo = caja.querySelector('[data-pelicula-capitulo]');
    if (!json || !cv || !boton) return;
    let cfg;
    try { cfg = JSON.parse(json.textContent); } catch (e) { return; }
    if (!cfg || !Array.isArray(cfg.orden) || !cfg.orden.length) return;
    const base = caja.getAttribute('data-pelicula-base') || '/js/escenas/';
    let idx = 0, ctl = null, cargada = false;

    function capitulo() {
      if (!rotulo) return;
      const def = Escenas.lista().find(d => d.id === cfg.orden[idx]);
      rotulo.textContent = (idx + 1) + ' / ' + cfg.orden.length + (def ? ' · ' + def.titulo : '');
    }
    function ir(i) {
      if (ctl) ctl.destroy();
      if (i >= cfg.orden.length) {
        ctl = null;
        boton.textContent = '▶ Otra vez';
        boton.style.display = '';
        return;
      }
      idx = i;
      capitulo();
      ctl = Escenas.montar(cv, cfg.orden[idx], {
        datos: (cfg.datos || {})[cfg.orden[idx]] || {},
        loop: false,
        forzar: true,
        onTime: (t, L) => { if (barra) barra.style.width = ((idx + t / L) / cfg.orden.length * 100).toFixed(2) + '%'; },
        onEnd: () => ir(idx + 1)
      });
      ctl.play();
    }
    boton.addEventListener('click', async () => {
      boton.style.display = 'none';
      if (!cargada) {
        if (rotulo) rotulo.textContent = 'Cargando…';
        try { await preparar(base, cfg); cargada = true; } catch (e) {
          if (rotulo) rotulo.textContent = 'No se ha podido cargar la película.';
          boton.style.display = '';
          return;
        }
      }
      if (portada) portada.style.display = 'none';
      cv.style.display = 'block';
      ir(0);
    });
  }

  function arrancar() { document.querySelectorAll('[data-pelicula]').forEach(montarPelicula); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', arrancar);
  else arrancar();
})();
