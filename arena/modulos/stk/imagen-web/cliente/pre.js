// Arena NOBS · SuperTuxKart — se ejecuta ANTES del motor: Module (argumentos, canvas, WebSocket del relé).
(function () {
  var q = new URLSearchParams(location.search);
  var token = /^sala[0-9]{1,2}$/.test(q.get('token') || '') ? q.get('token') : 'sala1';
  var raiz = location.pathname.replace(/cliente\/[^/]*$/, '');
  var wsurl = (location.protocol === 'https:' ? 'wss:' : 'ws:') + '//' + location.host + raiz + 'dg?token=' + token;
  // El port web parchea SOCKFS: url = Module.websocket.url + "/" + host:puerto (el relé limpia el sufijo)
  globalThis.config = { ws_enabled: true, ws_proxy: wsurl };
  // Motivo de cierre del relé (código 4001: vetado, límites, sala cerrada…)
  var WSOriginal = window.WebSocket;
  window.WebSocket = function (url, protocolos) {
    var ws = protocolos === undefined ? new WSOriginal(url) : new WSOriginal(url, protocolos);
    ws.addEventListener('close', function (ev) {
      if (ev.code === 4001 && globalThis.arenaFin) { globalThis.arenaFin(ev.reason || 'el relé cerró la conexión'); }
    });
    return ws;
  };
  window.WebSocket.prototype = WSOriginal.prototype;
  ['CONNECTING', 'OPEN', 'CLOSING', 'CLOSED'].forEach(function (k) { window.WebSocket[k] = WSOriginal[k]; });
  globalThis.Module = {
    canvas: document.getElementById('canvas'),
    arguments: ['--connect-now=127.0.0.1:2759'],
    websocket: { url: wsurl },
  };
})();
