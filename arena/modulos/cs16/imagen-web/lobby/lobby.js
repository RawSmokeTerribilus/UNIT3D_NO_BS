// Arena · Counter-Strike 1.6 — sala de espera del módulo (calcada de la de WZ/Q3).
// Pinta las salas que publica el agente y abre el cliente con ?token=salaN (el cliente monta el
// WebSocket del relé /dg). Cada socio puede crear una sala suya.
(function () {
  'use strict';

  var base = window.location.pathname.replace(/[^/]*$/, ''); // .../arena-app/wz2100/
  var FASES = { lobby: 'Esperando jugadores', partida: 'En marcha', reiniciando: 'Reiniciando', parada: 'Parada' };
  var yo = null; // nombre de usuario de NOBS

  function urlSala(token) {
    return base + 'cliente/?token=' + encodeURIComponent(token);
  }

  function el(etiqueta, clase, texto) {
    var n = document.createElement(etiqueta);
    if (clase) { n.className = clase; }
    if (texto !== undefined) { n.textContent = texto; }
    return n;
  }

  function mensaje(texto, error) {
    var m = document.getElementById('crear-mensaje');
    m.textContent = texto || '';
    m.className = 'mensaje' + (error ? ' mensaje--error' : '');
  }

  // Escrituras: JSON + X-Requested-With (el agente lo exige; un formulario de otra web no puede mandarlo)
  function pedir(metodo, ruta, cuerpo) {
    return fetch(base + ruta, {
      method: metodo,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'arena' },
      body: cuerpo ? JSON.stringify(cuerpo) : undefined
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok) { throw new Error(d.error || ('HTTP ' + r.status)); }
        return d;
      });
    });
  }

  function cerrarSala(id) {
    if (!window.confirm('¿Cerrar tu sala? Quien esté dentro sale.')) { return; }
    pedir('DELETE', 'mis-salas/' + id).then(function () {
      mensaje('Sala cerrada.');
      refrescar();
    }).catch(function (e) { mensaje(e.message, true); });
  }

  function pintar(lista) {
    var cont = document.getElementById('salas');
    cont.textContent = '';
    var tengo = false;
    if (!lista.length) {
      cont.appendChild(el('p', 'vacio', 'Ahora mismo no hay salas abiertas.'));
    }
    lista.forEach(function (s) {
      var mia = yo && s.dueno === yo;
      tengo = tengo || mia;
      var tarjeta = el('article', 'tarjeta' + (mia ? ' tarjeta--mia' : ''));
      tarjeta.appendChild(el('h2', null, (s.contrasena ? '🔒 ' : '') + s.nombre));
      var linea = (s.modo ? s.modo + ' · ' + s.mapa + ' · ' : '') + (FASES[s.fase] || s.fase) + ' · ' + s.jugadores + '/' + s.huecos + ' jugadores';
      if (s.dueno) { linea += ' · sala de ' + s.dueno; }
      tarjeta.appendChild(el('p', null, linea));
      if (s.socios && s.socios.length) {
        tarjeta.appendChild(el('p', 'socios', 'Dentro: ' + s.socios.join(', ')));
      }
      var acciones = el('div', 'acciones');
      if (s.vetado) {
        acciones.appendChild(el('span', 'boton boton--apagado', 'Vetado en esta sala'));
      } else if ((s.fase === 'lobby' || s.fase === 'partida') && s.jugadores < s.huecos) {
        var a = el('a', 'boton', 'Entrar');
        a.href = urlSala(s.id);
        acciones.appendChild(a);
      } else {
        acciones.appendChild(el('span', 'boton boton--apagado', s.fase === 'partida' ? 'En curso' : 'No disponible'));
      }
      if (mia) {
        var cerrar = el('button', 'boton boton--secundario', 'Cerrar mi sala');
        cerrar.type = 'button';
        cerrar.addEventListener('click', function () { cerrarSala(s.id); });
        acciones.appendChild(cerrar);
      }
      tarjeta.appendChild(acciones);
      cont.appendChild(tarjeta);
    });
    document.getElementById('crear').hidden = tengo;
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

  function cargarPresets() {
    fetch(base + 'presets.json', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var sel = document.getElementById('crear-preset');
        sel.textContent = '';
        (d.presets || []).forEach(function (p) {
          var o = el('option', null, p.nombre + ' — ' + p.mapa + ' (' + p.humanos + (p.humanos === 1 ? ' jugador' : ' jugadores') + (p.ias ? ', ' + p.ias + ' bots' : '') + ')');
          o.value = p.slug;
          sel.appendChild(o);
        });
      })
      .catch(function () { mensaje('No se pudieron cargar los modos de partida.', true); });
  }

  document.getElementById('form-crear').addEventListener('submit', function (ev) {
    ev.preventDefault();
    mensaje('Creando…');
    pedir('POST', 'mis-salas', {
      preset: document.getElementById('crear-preset').value,
      nombre: document.getElementById('crear-nombre').value,
      contrasena: document.getElementById('crear-contrasena').value
    }).then(function (s) {
      mensaje('Sala «' + s.nombre + '» creada. Ya puedes entrar.');
      refrescar();
    }).catch(function (e) { mensaje(e.message, true); });
  });

  fetch(base + 'yo.json', { credentials: 'same-origin', cache: 'no-store' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var partes = String(d.socio || '').split(':');
      yo = partes.length > 1 ? decodeURIComponent(partes.slice(1).join(':')) : null;
      document.getElementById('socio').textContent = yo || '¿?';
      refrescar();
    })
    .catch(function () { document.getElementById('socio').textContent = '¿?'; });

  cargarPresets();
  refrescar();
  setInterval(refrescar, 5000);
})();
