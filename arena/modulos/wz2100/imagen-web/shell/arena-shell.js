// Arena NOBS · Warzone 2100 — lo propio del shell (lo inyecta rehacer_shell.py al final del body).
(function () {
  'use strict';

  // Sin service worker: el shell oficial lo registra tras cargar. Se anula aquí (corre antes del
  // evento load) y se da de baja cualquiera que quedara de visitas anteriores.
  window.wz_serviceworker_register = function () {};
  window.wz_serviceworker_register_early_if_possible = function () {};
  // Sin PWA (se quitó el manifest): el oficial lo busca para quitarlo en escritorio
  window.wz_handle_desktop_pwa_installable = function () {};
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(function (regs) {
      regs.forEach(function (r) { r.unregister(); });
    }).catch(function () {});
  }

  // El relé cerró la sesión (FIN): vetado, límites, sala cerrada… El juego solo enseñaría
  // «Unable to join»; aquí se dice el motivo y se ofrece volver a la sala de espera.
  var EXPLICACION = {
    'el staff te ha vetado en esta sala': 'El staff te ha vetado en esta sala.',
    'la Arena está llena ahora mismo: prueba en un rato': 'La Arena está llena ahora mismo. Prueba en un rato.',
    'sala desconocida': 'Esta sala ya no existe.',
    'sesion caducada o desconocida': 'Tu sesión ha caducado: estuviste fuera demasiado tiempo.'
  };
  var IGNORAR = ['el jugador cerró el juego'];

  window.arenaFin = function (motivo) {
    if (IGNORAR.indexOf(motivo) !== -1 || document.getElementById('arena-fin')) { return; }
    var texto = EXPLICACION[motivo] || (motivo.charAt(0).toUpperCase() + motivo.slice(1) + '.');
    var capa = document.createElement('div');
    capa.id = 'arena-fin';
    capa.setAttribute('role', 'alertdialog');
    Object.assign(capa.style, {
      position: 'fixed', inset: '0', zIndex: '10000', display: 'flex', alignItems: 'center',
      justifyContent: 'center', background: 'rgba(0,0,0,.75)', padding: '16px'
    });
    var caja = document.createElement('div');
    Object.assign(caja.style, {
      maxWidth: '440px', background: '#1b1f27', color: '#e6e6e6', border: '1px solid #3a4150',
      borderRadius: '8px', padding: '20px', font: '15px/1.45 system-ui, sans-serif', textAlign: 'center'
    });
    var titulo = document.createElement('h2');
    titulo.textContent = 'Fuera de la sala';
    Object.assign(titulo.style, { margin: '0 0 10px', fontSize: '20px' });
    var p = document.createElement('p');
    p.textContent = texto;
    p.style.margin = '0 0 16px';
    var volver = document.createElement('a');
    volver.textContent = 'Volver a la sala de espera';
    volver.href = '../';
    volver.target = '_self';
    Object.assign(volver.style, {
      display: 'inline-block', background: '#e8a83a', color: '#111', fontWeight: '700',
      padding: '8px 16px', borderRadius: '4px', textDecoration: 'none'
    });
    caja.appendChild(titulo);
    caja.appendChild(p);
    caja.appendChild(volver);
    capa.appendChild(caja);
    document.body.appendChild(capa);
    if (document.exitPointerLock) { document.exitPointerLock(); }
  };
})();
