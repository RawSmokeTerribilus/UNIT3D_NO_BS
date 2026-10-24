// Arena NOBS · SuperTuxKart — se ejecuta ANTES del motor: Module (argumentos, canvas, WebSocket del relé).
(function () {
  var q = new URLSearchParams(location.search);
  var token = /^sala[0-9]{1,2}$/.test(q.get('token') || '') ? q.get('token') : 'sala1';
  var raiz = location.pathname.replace(/cliente\/[^/]*$/, '');
  var wsurl = (location.protocol === 'https:' ? 'wss:' : 'ws:') + '//' + location.host + raiz + 'dg?token=' + token;
  // El port web parchea SOCKFS: url = Module.websocket.url + "/" + host:puerto (el relé limpia el sufijo)
  globalThis.config = { ws_enabled: true, ws_proxy: wsurl };
  globalThis.Module = {
    canvas: document.getElementById('canvas'),
    arguments: ['--connect-now=127.0.0.1:2759'],
    websocket: { url: wsurl },
  };
})();
