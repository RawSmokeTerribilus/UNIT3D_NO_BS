/* NOBS · lanzador de escenas dentro del tracker.
 * Busca cada <canvas data-escena="id">, lee sus datos de <script type="application/json"
 * data-escena-datos="id"> (la CSP no deja scripts en línea, pero sí JSON) y la monta.
 * El botón «Ocultar» de cada panel se recuerda por navegador.
 */
(function () {
  'use strict';
  const CLAVE = 'nobs-escena-oculta:';
  let hecho = false;

  function leer(id) {
    const el = document.querySelector('script[data-escena-datos="' + id + '"]');
    if (!el) return {};
    try {
      const d = JSON.parse(el.textContent);
      return d && typeof d === 'object' && !Array.isArray(d) ? d : {};
    } catch (e) {
      return {};
    }
  }
  function oculta(id) {
    try { return localStorage.getItem(CLAVE + id) === '1'; } catch (e) { return false; }
  }
  function recordar(id, v) {
    try { if (v) localStorage.setItem(CLAVE + id, '1'); else localStorage.removeItem(CLAVE + id); } catch (e) { /* sin almacenamiento */ }
  }

  function arrancar() {
    if (hecho || !window.Escenas) return;
    hecho = true;
    document.querySelectorAll('canvas[data-escena]').forEach(cv => {
      const id = cv.getAttribute('data-escena');
      const cuerpo = document.querySelector('[data-escena-cuerpo="' + id + '"]');
      const boton = document.querySelector('[data-escena-toggle="' + id + '"]');
      let ctl = null;
      const montar = () => {
        if (ctl) return;
        try { ctl = Escenas.montar(cv, id, { datos: leer(id) }); } catch (e) { console.warn('[escenas]', id, e); }
      };
      const aplicar = esconder => {
        if (cuerpo) cuerpo.style.display = esconder ? 'none' : '';
        if (boton) boton.textContent = esconder ? 'Mostrar' : 'Ocultar';
        if (esconder) { if (ctl) { ctl.destroy(); ctl = null; } } else montar();
      };
      if (boton) boton.addEventListener('click', () => {
        const esconder = !(cuerpo && cuerpo.style.display === 'none');
        recordar(id, esconder);
        aplicar(esconder);
      });
      aplicar(!!boton && oculta(id));
    });
  }

  // Las escenas se cargan con defer después de este fichero: se monta al terminar de leer la página.
  if (document.readyState === 'complete') arrancar();
  else {
    document.addEventListener('DOMContentLoaded', arrancar);
    window.addEventListener('load', arrancar);
  }
})();
