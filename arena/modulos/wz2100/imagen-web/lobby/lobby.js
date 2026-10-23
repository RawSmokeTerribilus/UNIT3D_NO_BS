// Arena · Warzone 2100 — sala de espera del módulo.
// Pinta las salas que publica el agente y monta la URL del cliente: puente WebSocket del mismo
// origen (?token=salaN) y --join a una IP literal (el nombre da igual: el wasm manda TODOS los
// sockets a wsurl, y websockify enruta por token).
(function () {
  'use strict';

  var base = window.location.pathname.replace(/[^/]*$/, ''); // .../arena-app/wz2100/
  var wsProto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
  var FASES = { lobby: 'Esperando jugadores', partida: 'En partida', reiniciando: 'Reiniciando', parada: 'Parada' };

  function urlSala(token) {
    var wsurl = wsProto + '//' + window.location.host + base + 'ws?token=' + encodeURIComponent(token);
    return base + 'cliente/?wsurl=' + encodeURIComponent(wsurl) + '&join=10.21.0.1';
  }

  function el(etiqueta, clase, texto) {
    var n = document.createElement(etiqueta);
    if (clase) { n.className = clase; }
    if (texto !== undefined) { n.textContent = texto; }
    return n;
  }

  function pintar(lista) {
    var cont = document.getElementById('salas');
    cont.textContent = '';
    if (!lista.length) {
      cont.appendChild(el('p', 'vacio', 'Ahora mismo no hay salas abiertas.'));
      return;
    }
    lista.forEach(function (s) {
      var tarjeta = el('article', 'tarjeta');
      tarjeta.appendChild(el('h2', null, s.nombre));
      tarjeta.appendChild(el('p', null, (FASES[s.fase] || s.fase) + ' · ' + s.jugadores + '/' + s.huecos + ' jugadores'));
      if (s.fase === 'lobby' && s.jugadores < s.huecos) {
        var a = el('a', 'boton', 'Entrar');
        a.href = urlSala(s.id);
        tarjeta.appendChild(a);
      } else {
        tarjeta.appendChild(el('span', 'boton boton--apagado', s.fase === 'partida' ? 'En curso' : 'No disponible'));
      }
      cont.appendChild(tarjeta);
    });
  }

  function refrescar() {
    fetch(base + 'salas.json', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
      .then(function (d) { pintar(d.salas || []); })
      .catch(function () {
        var cont = document.getElementById('salas');
        cont.textContent = '';
        cont.appendChild(el('p', 'vacio', 'No se pudo leer la lista de salas. Reintentando…'));
      });
  }

  fetch(base + 'yo.json', { credentials: 'same-origin', cache: 'no-store' })
    .then(function (r) { return r.json(); })
    .then(function (yo) {
      var partes = String(yo.socio || '').split(':');
      document.getElementById('socio').textContent = partes.length > 1 ? decodeURIComponent(partes.slice(1).join(':')) : '¿?';
    })
    .catch(function () { document.getElementById('socio').textContent = '¿?'; });

  refrescar();
  setInterval(refrescar, 5000);
})();
