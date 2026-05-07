/**
 * RetroArch Catalog Launcher — controles de iframe + debug bridge
 *
 * Externo (no inline) porque la CSP de gaming.isolation no permite
 * 'unsafe-inline' en script-src-elem.  Lee su configuración de un
 * <script type="application/json" id="ra-config"> en el padre.
 *
 * Activación de debug: añadir ?debug a la URL del juego, ej:
 *   /retroarch/nes/adventure-island-usa?debug
 * El padre activa el panel y este script enchufa el postMessage listener.
 * El iframe (libretro.js parchado) reenvía console al padre vía postMessage.
 */
(function () {
    'use strict';

    var configEl = document.getElementById('ra-config');
    if (!configEl) return;

    var config;
    try { config = JSON.parse(configEl.textContent); }
    catch (e) { console.error('[ra-launcher] bad ra-config JSON:', e); return; }

    var isArcade = !!config.isArcade;
    var isDebug  = !!config.isDebug;

    // ── Controles de teclado al iframe ──────────────────────────────────
    function dispatch(keyDef) {
        var f = document.getElementById('ra-frame');
        if (!f || !f.contentWindow) return;
        var win = f.contentWindow;
        var doc = f.contentDocument || (win && win.document);
        ['keydown', 'keyup'].forEach(function (type, i) {
            setTimeout(function () {
                var init = {
                    key: keyDef.key, code: keyDef.code,
                    keyCode: keyDef.keyCode, which: keyDef.keyCode,
                    location: keyDef.location || 0,
                    bubbles: true, cancelable: true, composed: true
                };
                try { win.dispatchEvent(new KeyboardEvent(type, init)); } catch (_) {}
                try { doc && doc.dispatchEvent(new KeyboardEvent(type, init)); } catch (_) {}
                var canvas = doc && doc.getElementById && doc.getElementById('canvas');
                if (canvas) { try { canvas.dispatchEvent(new KeyboardEvent(type, init)); } catch (_) {} }
            }, i * 80);
        });
    }
    function sendCoin() {
        // Probar varios candidatos: 5, Tab, RShift — bindings SELECT/COIN
        // varían entre revisiones de retroarch.cfg.
        dispatch({ key: '5',     code: 'Digit5',     keyCode: 53 });
        setTimeout(function () { dispatch({ key: 'Tab',   code: 'Tab',        keyCode: 9 }); }, 200);
        setTimeout(function () { dispatch({ key: 'Shift', code: 'ShiftRight', keyCode: 16, location: 2 }); }, 400);
    }
    function sendStart() {
        dispatch({ key: '1',     code: 'Digit1', keyCode: 49 });
        setTimeout(function () { dispatch({ key: 'Enter', code: 'Enter', keyCode: 13 }); }, 200);
    }

    var coinBtn  = document.querySelector('[data-ra-action="coin"]');
    var startBtn = document.querySelector('[data-ra-action="start"]');
    if (coinBtn)  coinBtn.addEventListener('click', sendCoin);
    if (startBtn) startBtn.addEventListener('click', sendStart);

    // Auto-coin para arcade (si el sistema vuelve a abrirse).  Tres rondas
    // de tres monedas, con buffers para cubrir tiempos de carga distintos.
    if (isArcade) {
        [6000, 9000, 12000].forEach(function (delay) {
            setTimeout(function () {
                sendCoin();
                setTimeout(sendCoin, 700);
                setTimeout(sendCoin, 1400);
            }, delay);
        });
    }

    // ── Debug panel ──────────────────────────────────────────────────────
    if (!isDebug) return;

    var logEl   = document.getElementById('ra-debug-log');
    var badgeEl = document.getElementById('ra-debug-badge');
    var lines   = [];

    function append(level, msg) {
        var ts  = new Date().toTimeString().slice(0, 8);
        var tag = level === 'error' ? 'ERR' : level === 'warn' ? 'WRN' : level === 'info' ? 'INF' : 'LOG';
        lines.push(ts + ' [' + tag + '] ' + msg);
        if (lines.length > 600) lines.shift();
        if (logEl) { logEl.textContent = lines.join('\n'); logEl.scrollTop = logEl.scrollHeight; }
        if (badgeEl) badgeEl.textContent = tag;
    }

    append('info', 'panel ready — esperando logs del iframe vía postMessage');
    append('info', 'parent origin: ' + window.location.origin);
    var iframeEl = document.getElementById('ra-frame');
    append('info', 'iframe src: ' + (iframeEl ? iframeEl.src : 'NO IFRAME'));

    window.addEventListener('message', function (ev) {
        var origin = ev.origin || '?';
        var data   = ev.data;
        if (data && data.__ra === 'log') {
            append(data.level || 'log', String(data.msg || ''));
            return;
        }
        try {
            var s = typeof data === 'string' ? data : JSON.stringify(data);
            append('info', '[msg] from ' + origin + ' :: ' + (s || '').slice(0, 200));
        } catch (_) {
            append('info', '[msg] from ' + origin + ' :: (unstringifiable)');
        }
    });

    setTimeout(function () {
        if (lines.length <= 4) {
            append('warn', 'sin mensajes del iframe en 4s — revisa F12 → consola por "[ra-debug]" y por errores de postMessage');
        }
    }, 4000);

    var probeBtn = document.getElementById('ra-debug-probe');
    var clearBtn = document.getElementById('ra-debug-clear');
    if (probeBtn) probeBtn.addEventListener('click', function () {
        var stateEl = document.getElementById('ra-debug-state');
        var state;
        try { state = JSON.parse(stateEl.textContent); }
        catch (e) { append('error', 'no pude parsear ra-debug-state'); return; }
        var assets = state.assets || {};
        Object.keys(assets).forEach(function (k) {
            var url = assets[k];
            if (!url) return append('warn', '[' + k + '] sin URL');
            fetch(url, { method: 'HEAD', credentials: 'same-origin' })
                .then(function (r) {
                    append(r.ok ? 'log' : 'warn',
                        '[' + k + '] ' + r.status + '  ' + url +
                        '  (' + (r.headers.get('content-length') || '?') + ' B)');
                })
                .catch(function (e) { append('error', '[' + k + '] ' + e); });
        });
    });
    if (clearBtn) clearBtn.addEventListener('click', function () {
        lines = [];
        if (logEl) logEl.textContent = '';
    });
})();
