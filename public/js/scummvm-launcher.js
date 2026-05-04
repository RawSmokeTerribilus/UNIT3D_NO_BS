/**
 * ScummVM WebAssembly Launcher — con debug logging completo
 * Para activar el panel de debug: añadir ?debug a la URL del juego
 * Ej: http://127.0.0.1:58080/gaming/mi1-vga?debug
 */
(function () {
    'use strict';

    // ── Debug logging ────────────────────────────────────────────────────────
    var _debugEl     = document.getElementById('debug-log');
    var _statusBadge = document.getElementById('debug-status-badge');
    var _debugLines  = [];

    // Activar panel de debug si URL tiene ?debug (en query string o en el fragmento hash)
    var _hasDebug = window.location.search.includes('debug') || window.location.hash.includes('debug');
    if (_hasDebug) {
        var _panel = document.getElementById('gaming-debug-panel');
        if (_panel) _panel.style.display = 'block';
    }

    // Event listeners que antes eran inline (removidos del blade por CSP)
    var _canvas = document.getElementById('canvas');
    if (_canvas) {
        _canvas.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    }
    var _clearBtn = document.getElementById('debug-clear-btn');
    if (_clearBtn) {
        _clearBtn.addEventListener('click', function () {
            _debugLines = [];
            if (_debugEl) _debugEl.textContent = '';
        });
    }

    // Interceptar teclas que el navegador se comería cuando el juego tiene foco
    // F5 = guardar/cargar en ScummVM, sin interceptar recarga el navegador
    // F11 = fullscreen en ScummVM, sin interceptar activa fullscreen del navegador
    document.addEventListener('keydown', function (e) {
        if (e.key === 'F5' || e.key === 'F11') {
            e.preventDefault();
        }
    }, false);

    function dbg(msg, level) {
        var ts     = new Date().toTimeString().slice(0, 8);
        var prefix = level === 'error' ? 'ERR' : level === 'warn' ? 'WRN' : level === 'ok' ? ' OK' : level === 'scumm' ? 'SCM' : ' --';
        var line   = ts + ' [' + prefix + '] ' + msg;
        _debugLines.push(line);
        if (_debugLines.length > 400) _debugLines.shift();
        if (_debugEl) { _debugEl.textContent = _debugLines.join('\n'); _debugEl.scrollTop = _debugEl.scrollHeight; }
        if (_statusBadge && level) _statusBadge.textContent = level.toUpperCase();
        (level === 'error' ? console.error : level === 'warn' ? console.warn : console.log)('[ScummVM]', msg);
    }

    // ── Config Blade ─────────────────────────────────────────────────────────
    var configEl = document.getElementById('gaming-config');
    if (!configEl) { dbg('No gaming-config element — aborting', 'error'); return; }
    var config;
    try { config = JSON.parse(configEl.textContent); }
    catch (e) { dbg('JSON parse error: ' + e, 'error'); return; }

    var gameId        = config.gameId;
    var scummId       = config.scummId;
    var engineId      = config.engineId;
    var engineVersion = config.engineVersion || '';
    var files         = config.files;
    var syncUrl       = config.syncUrl;
    var saves         = config.saves;
    var csrfToken     = config.csrfToken;
    var scummIni      = config.scummIni;
    var engineQuery   = engineVersion ? ('?v=' + encodeURIComponent(engineVersion)) : '';
    dbg('Config loaded: gameId=' + gameId + ' scummId=' + scummId + ' engineId=' + engineId + ' files=' + (files ? files.length : 0) + ' engineVersion=' + engineVersion);

    // Whitelist defensiva — engineId acaba como path en una URL
    // (/engine/data/plugins/lib<engineId>.so) y como nombre de archivo en el VFS.
    // El controlador ya filtra por el mismo regex; esto es defensa en profundidad
    // que sólo debería disparar ante un bug de configuración, no en ejecución normal.
    var ID_REGEX = /^[a-z][a-z0-9]*$/;
    if (!ID_REGEX.test(engineId || '')) {
        dbg('FATAL: invalid engineId from config: ' + JSON.stringify(engineId), 'error');
        return;
    }
    if (typeof scummIni !== 'string' || scummIni.length === 0) {
        dbg('FATAL: missing scummIni in config', 'error');
        return;
    }

    // ── DOM refs ─────────────────────────────────────────────────────────────
    var loadingEl     = document.getElementById('gaming-loading');
    var loadingTextEl = document.getElementById('gaming-loading-text');
    var statusTextEl  = document.getElementById('gaming-status-text');
    var saveStatusEl    = document.getElementById('gaming-save-status');
    var _fullscreenBtn  = document.getElementById('gaming-fullscreen-btn');
    var canvas          = document.getElementById('canvas'); // Emscripten requiere id="canvas"
    if (!canvas) { dbg('FATAL: no canvas#canvas element found', 'error'); }
    else { dbg('Canvas element OK (id=canvas)'); }

    var SAVE_DIR = '/saves';
    var GAME_DIR = '/games/' + gameId;
    // .sog/.ogg/etc NO usan createLazyFile — XHR síncrono roto en browsers modernos sin web workers
    // Todos los archivos se fetchean async. Los grandes muestran progreso.
    var LAZY_EXT = []; // desactivado

    // ── UI helpers ───────────────────────────────────────────────────────────
    function setStatus(msg)  { if (statusTextEl)  statusTextEl.textContent  = msg; }
    function setLoading(msg) { if (loadingTextEl) loadingTextEl.textContent = msg; }
    function hideLoading()   { if (loadingEl) loadingEl.style.display = 'none'; }
    function showSaveNotice(msg) {
        if (!saveStatusEl) return;
        saveStatusEl.textContent = msg;
        clearTimeout(showSaveNotice._t);
        showSaveNotice._t = setTimeout(function () { saveStatusEl.textContent = ''; }, 3000);
    }

    // Trick: cuando un asset crítico (plugin del motor, archivo del juego)
    // no puede descargarse — típicamente bloqueado por adblocker, extensión
    // del navegador o filtro DNS — abortamos preRun y mostramos un mensaje
    // accionable en lugar de dejar que el engine arranque y muera con un
    // error críptico ("Could not find suitable engine plugin").
    function showFatalError(reason) {
        if (loadingTextEl) {
            loadingTextEl.innerHTML =
                '<strong>No se pudo cargar el motor del juego.</strong><br><br>' +
                '<span style="font-size:.85em;opacity:.85;text-align:left;display:inline-block">' +
                'Probablemente un bloqueador de anuncios, extensión del ' +
                'navegador o filtro DNS (Pi-hole, NextDNS) está bloqueando ' +
                'recursos del motor.<br><br>' +
                '<u>Soluciones</u>:<br>' +
                '• Prueba en una ventana de incógnito<br>' +
                '• Desactiva extensiones (AdBlock, uBlock, Microsoft Editor)<br>' +
                '• Si usas Pi-hole / NextDNS, añade el dominio a la lista blanca' +
                '</span>';
        }
        var icon = document.querySelector('.gaming-loading__icon i');
        if (icon) icon.classList.remove('fa-spin');
        var sub = document.querySelector('.gaming-loading__sub');
        if (sub) sub.style.display = 'none';
        setStatus('Error: motor bloqueado');
        dbg('FATAL: ' + reason, 'error');
    }

    // Escotilla de emergencia: nuca IndexedDB + Cache Storage + Service Workers.
    // Reproduce manualmente lo que hace "borrar todos los datos del sitio" en
    // Chrome. Necesario porque el engine de ScummVM cachea listados de
    // directorio en IndexedDB y, si esa caché se corrompe en una sesión
    // fallida, las siguientes sesiones quedan en bucle infinito hasta que el
    // asyncify rewind se corrompe y el __dlopen_js peta. Las partidas viven
    // en nuestro backend, así que borrar todo el storage del cliente es seguro.
    async function resetGameData() {
        if (!window.confirm(
            '¿Borrar la caché del navegador para este sitio?\n\n' +
            '• Las partidas guardadas en la nube NO se verán afectadas.\n' +
            '• La página se recargará después.'
        )) return;

        setStatus('Borrando datos…');
        dbg('resetGameData: starting full client-side wipe', 'warn');

        try {
            // Pass 1: enumerate via API (may return [] on Firefox — that's why pass 2 exists)
            var dbs = (typeof indexedDB.databases === 'function') ? (await indexedDB.databases()) : [];
            dbg('IDB databases found via API: ' + (dbs.length ? dbs.map(function (d) { return d && d.name; }).join(', ') : '(none)'));
            // Pass 2: unconditionally delete the hardcoded IDBFS mount paths that
            // ScummVM's Emscripten build uses as IndexedDB database names.
            // indexedDB.databases() silently misses these in Firefox and Chrome incognito.
            var knownIdbPaths = ['/home/web_user', '/home'];
            knownIdbPaths.forEach(function (p) {
                if (!dbs.some(function (d) { return d && d.name === p; })) dbs.push({ name: p });
            });
            await Promise.all(dbs.map(function (d) {
                return new Promise(function (res) {
                    if (!d || !d.name) return res();
                    dbg('Deleting IDB: ' + d.name);
                    var req = indexedDB.deleteDatabase(d.name);
                    req.onsuccess = req.onerror = req.onblocked = function () { res(); };
                });
            }));
        } catch (e) { dbg('IDB clear failed: ' + e, 'warn'); }

        try {
            if (window.caches && typeof caches.keys === 'function') {
                var keys = await caches.keys();
                dbg('Cache Storage keys: ' + keys.join(', '));
                await Promise.all(keys.map(function (k) { return caches.delete(k); }));
            }
        } catch (e) { dbg('caches clear failed: ' + e, 'warn'); }

        try {
            if (navigator.serviceWorker && typeof navigator.serviceWorker.getRegistrations === 'function') {
                var regs = await navigator.serviceWorker.getRegistrations();
                dbg('Service workers found: ' + regs.length);
                await Promise.all(regs.map(function (r) { return r.unregister(); }));
            }
        } catch (e) { dbg('SW unregister failed: ' + e, 'warn'); }

        dbg('resetGameData: wipe complete, reloading', 'ok');
        location.reload();
    }

    // ── Mount ScummVM engine plugins into VFS ────────────────────────────────
    // ScummVM WASM tries to browse plugins via HTTPFilesystem (data/index.json → 404).
    // Pre-loading libscumm.so into the VFS bypasses that entirely.
    // El plugin se elige a partir de engineId (validado contra ID_REGEX arriba).
    // Cuando se compilen plugins adicionales (sci, ags, sword1, sky, queen…) el
    // launcher no necesita cambios: basta con dejar caer el .so en
    // public/engine/data/plugins/ y registrar entradas con el engine_id correcto.
    var PLUGINS = [
        { url: '/engine/data/plugins/lib' + engineId + '.so' + engineQuery,  vfsPath: '/plugins/lib' + engineId + '.so'  },
    ];

    async function mountPlugins() {
        setLoading('Cargando plugins del motor...');
        try { FS.mkdir('/plugins'); } catch (_) {}
        for (var i = 0; i < PLUGINS.length; i++) {
            var p = PLUGINS[i];
            try {
                dbg('Fetching plugin: ' + p.url + '...');
                var res = await fetch(p.url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                var buf = await res.arrayBuffer();
                FS.writeFile(p.vfsPath, new Uint8Array(buf));
                dbg('Plugin OK: ' + p.vfsPath + ' (' + buf.byteLength + ' bytes)', 'ok');
            } catch (e) {
                dbg('Plugin FAILED: ' + p.url + ' -- ' + e, 'error');
                throw new Error('Plugin bloqueado: ' + p.url + ' (' + e.message + ')');
            }
        }
    }

    // ── Mount game files into Emscripten VFS ─────────────────────────────────
    async function mountGameFiles() {
        setLoading('Montando archivos del juego...');
        dbg('Mounting VFS: ' + GAME_DIR);
        try { FS.mkdir('/games'); } catch (_) {}
        try { FS.mkdir(GAME_DIR); } catch (e) { dbg('mkdir ' + GAME_DIR + ': ' + e, 'warn'); }

        var fileList = Array.isArray(files) ? files : [];
        dbg('Files to mount (' + fileList.length + '): ' + fileList.join(', '));

        for (var i = 0; i < fileList.length; i++) {
            var filename = fileList[i];
            var url      = '/games/' + gameId + '/' + filename;

            try {
                dbg('Fetching ' + filename + '...');
                var res = await fetch(url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status + ' ' + res.statusText);
                // Progreso para archivos grandes (speech, audio)
                var contentLength = res.headers.get('content-length');
                var total = contentLength ? parseInt(contentLength, 10) : 0;
                var buf;
                if (total > 10 * 1024 * 1024) {
                    var reader = res.body.getReader();
                    var chunks = [];
                    var received = 0;
                    while (true) {
                        var _r = await reader.read();
                        if (_r.done) break;
                        chunks.push(_r.value);
                        received += _r.value.length;
                        if (total) {
                            var pct = Math.round(received / total * 100);
                            setLoading('Descargando ' + filename + '... ' + pct + '%');
                        }
                    }
                    var merged = new Uint8Array(received);
                    var offset = 0;
                    for (var c = 0; c < chunks.length; c++) { merged.set(chunks[c], offset); offset += chunks[c].length; }
                    buf = merged.buffer;
                } else {
                    buf = await res.arrayBuffer();
                }
                // Crear directorios intermedios para juegos con layout anidado (Full Throttle: DATA/, VIDEO/)
                if (filename.indexOf('/') !== -1) {
                    var subDir = GAME_DIR + '/' + filename.substring(0, filename.lastIndexOf('/'));
                    try { FS.mkdirTree(subDir); } catch (me) { dbg('mkdirTree ' + subDir + ': ' + me, 'warn'); }
                }
                FS.writeFile(GAME_DIR + '/' + filename, new Uint8Array(buf));
                // Alias MONSTER.SOG para MI2
                if (filename.toLowerCase().endsWith('.sog')) {
                    try { FS.writeFile(GAME_DIR + '/MONSTER.SOG', new Uint8Array(buf)); dbg('Alias MONSTER.SOG written', 'ok'); }
                    catch (ae) { dbg('MONSTER.SOG alias failed: ' + ae, 'warn'); }
                }
                dbg('writeFile OK: ' + filename + ' (' + buf.byteLength + ' bytes)', 'ok');
            } catch (e) { dbg('FETCH FAILED: ' + filename + ' -- ' + e, 'error'); }
        }

        try {
            var vfsList = FS.readdir(GAME_DIR);
            dbg('VFS ' + GAME_DIR + ' contents: [' + vfsList.join(', ') + ']', 'ok');
        } catch (e) { dbg('Cannot list VFS ' + GAME_DIR + ': ' + e, 'error'); }
    }

    // ── Restore cloud saves ──────────────────────────────────────────────────
    async function restoreSaves() {
        if (!saves || saves.length === 0) { dbg('No cloud saves to restore'); return; }
        setLoading('Restaurando ' + saves.length + ' partida(s)...');
        dbg('Restoring ' + saves.length + ' saves from backend');
        try {
            try { FS.mkdir(SAVE_DIR); } catch (_) {}
            await Promise.all(saves.map(async function (save) {
                var res = await fetch(save.download_url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status + ' for ' + save.filename);
                var buf = await res.arrayBuffer();
                FS.writeFile(SAVE_DIR + '/' + save.filename, new Uint8Array(buf));
                dbg('Save restored: ' + save.filename + ' (' + buf.byteLength + ' B)', 'ok');
            }));
            setStatus(saves.length + ' partida(s) restaurada(s)');
        } catch (err) { dbg('Save restore failed: ' + err, 'warn'); setStatus('Sin partidas previas'); }
    }

    // ── Upload save to backend ───────────────────────────────────────────────

    // Only actual save slots qualify — rejects ScummVM metadata: timestamps, thumbs, .tmp
    // SCUMM saves: monkey.s00, monkey2.s01 …
    function isSaveFile(path) {
        return /\.[sS]\d+$/.test(path.split('/').pop());
    }

    async function uploadSave(virtualPath) {
        try {
            var raw      = FS.readFile(virtualPath, { encoding: 'binary' });
            var blob     = new Blob([raw], { type: 'application/octet-stream' });
            var filename = virtualPath.split('/').pop();
            var fd       = new FormData();
            fd.append('save_blob', blob, filename);
            fd.append('game_id', gameId);
            fd.append('_token', csrfToken);
            var res = await fetch(syncUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrfToken }, body: fd });
            if (res.ok) { showSaveNotice('Partida guardada en la nube'); setStatus('Partida sincronizada'); dbg('Save uploaded: ' + filename, 'ok'); }
            else { dbg('Upload save HTTP error: ' + res.status + ' for ' + filename, 'error'); showSaveNotice('Error al sincronizar (' + res.status + ')'); }
        } catch (err) { dbg('Upload save failed: ' + err, 'error'); showSaveNotice('Error de red al guardar'); }
    }

    // ── onRuntimeInitialized ─────────────────────────────────────────────────
    function installFilesystemTracking() {
        dbg('onRuntimeInitialized fired -- ScummVM Wasm ready!', 'ok');
        setStatus('Motor listo');
        try { var v = FS.readdir(GAME_DIR); dbg('VFS post-init ' + GAME_DIR + ': [' + v.join(', ') + ']'); }
        catch (e) { dbg('VFS post-init list error: ' + e, 'warn'); }
        try { FS.mkdir(SAVE_DIR); } catch (_) {}

        // Guard against re-entrancy: uploadSave calls FS.readFile which calls FS.close.
        // Without this Set, each upload would trigger another upload infinitely.
        var _uploadGuard = new Set();

        var _origClose = FS.close;
        FS.close = function (stream) {
            var path = stream && stream.path ? stream.path : null;
            var result = _origClose.call(FS, stream);
            if (path && !_uploadGuard.has(path) && path.startsWith(SAVE_DIR + '/') && isSaveFile(path)) {
                _uploadGuard.add(path);
                dbg('FS.close intercepted save: ' + path, 'ok');
                uploadSave(path).finally(function () { _uploadGuard.delete(path); });
            }
            return result;
        };
        dbg('FS.close patched for save detection');
        hideLoading();
        setStatus('En juego');
        dbg('Game running', 'ok');
        if (_fullscreenBtn) _fullscreenBtn.removeAttribute('disabled');
    }

    // ── Cleanup ──────────────────────────────────────────────────────────────
    window.addEventListener('beforeunload', function () {
        // Intentionally empty: FS.close interceptor handles uploads during gameplay.
        // sendBeacon loop removed — FS.readFile inside it calls our patched FS.close,
        // causing infinite recursion that saturates the event queue and hangs the page.
    });

    // ── fetch intercept for scummvm.ini ──────────────────────────────────────
    // Must be installed BEFORE scummvm.js loads (it saves its own fetch override).
    // El INI lo genera el backend (GamingController::buildScummIni) a partir del
    // catálogo en config/gaming.php — fuente única de verdad. output_rate omitido
    // a propósito: dejar que ScummVM use el sample rate nativo del SDL audio device.
    // Forzarlo a 44100 causaba buffer size mismatch en el pipeline a 48 kHz de Chrome.
    (function () {
        var _realFetch = window.fetch;
        window.fetch = function (input, init) {
            var url = (typeof input === 'string') ? input : (input && input.url) || '';
            if (url === 'scummvm.ini' || url.endsWith('/scummvm.ini')) {
                dbg('Intercepted fetch(scummvm.ini) -- serving generated ini (' + scummIni.length + ' bytes)', 'ok');
                return Promise.resolve(new Response(scummIni, { status: 200, headers: { 'Content-Type': 'text/plain' } }));
            }
            return _realFetch.apply(window, arguments);
        };
        dbg('scummvm.ini fetch intercept installed');
    }());

    // ── Module object ────────────────────────────────────────────────────────
    window.Module = {
        canvas: canvas,

        locateFile: function (path) {
            var r = '/engine/' + path + engineQuery;
            dbg('locateFile: ' + path + ' -> ' + r);
            return r;
        },

        preRun: [
            function () {
                dbg('preRun: starting VFS setup');
                // Force scummvm.ini re-download each session (prevents stale IDBFS config)
                var _orig = FS.analyzePath;
                FS.analyzePath = function (path) {
                    if (path && path.indexOf('scummvm.ini') !== -1) {
                        dbg('analyzePath override: ' + path + ' -> {exists:false}');
                        return { exists: false };
                    }
                    return _orig.apply(FS, arguments);
                };

                Module.addRunDependency('gameSetup');
                dbg('addRunDependency(gameSetup) -- blocking main() until VFS ready');

                (async function () {
                    var fatal = false;
                    try {
                        await mountPlugins();
                        await mountGameFiles();
                        try { FS.mkdir(SAVE_DIR); } catch (_) {}
                        await restoreSaves();
                        dbg('preRun complete -- VFS ready', 'ok');
                    } catch (err) {
                        fatal = true;
                        showFatalError(String(err && err.message || err));
                    } finally {
                        if (fatal) {
                            // Dejamos addRunDependency vivo a propósito: el engine
                            // queda en pausa y el usuario ve el mensaje de error
                            // en lugar de un fallo críptico dentro del juego.
                            dbg('Engine paused on fatal error -- run dependency NOT removed', 'warn');
                        } else {
                            Module.removeRunDependency('gameSetup');
                            dbg('removeRunDependency(gameSetup) -- unblocking main()');
                        }
                    }
                }());
            },
        ],

        onRuntimeInitialized: installFilesystemTracking,

        print:    function (text) { dbg(text, 'scumm'); },
        printErr: function (text) { dbg(text, 'warn'); },

        setStatus: function (text) {
            if (text) { dbg('Engine setStatus: ' + text); setLoading(text); }
        },

        totalDependencies: 0,
        monitorRunDependencies: function (left) {
            this.totalDependencies = Math.max(this.totalDependencies, left);
            var done = this.totalDependencies - left;
            if (left === 0) {
                dbg('All engine dependencies resolved (' + this.totalDependencies + ')', 'ok');
                setStatus('Motor cargado');
            } else {
                dbg('Engine deps: ' + done + '/' + this.totalDependencies);
                setLoading('Cargando engine... (' + done + '/' + this.totalDependencies + ')');
            }
        },
    };

    // ── Hash args ─────────────────────────────────────────────────────────────
    // scummvm.js line 2: Module["arguments"]=[] then reads location.hash via
    // decodeURI(hash.substring(1)).split(" ").
    // The ini already has path= and savepath= for each game, so we only need
    // to pass the game target ID — avoids any --path encoding issues.
    var scummArgs = scummId;
    try {
        history.replaceState(null, '', window.location.pathname + window.location.search + '#' + scummArgs);
    } catch (_) { window.location.hash = scummArgs; }
    dbg('Hash args set: ' + scummArgs);
    dbg('Full URL: ' + window.location.href);

    // ── Arrancar el engine — dentro del gesto de usuario (click) ─────────────
    // Chrome exige que AudioContext se cree/reanude dentro del propio event
    // handler del click. El engine llama SDL_OpenAudio profundamente en WASM
    // asyncify, mucho después de que el gesto expire, causando hang silencioso.
    // Solución: botón "▶ Jugar" — el engine se inyecta dentro del click handler.
    function launchEngine() {
        if (window._scummvmLaunched) { dbg('launchEngine called twice — ignoring', 'warn'); return; }
        window._scummvmLaunched = true;
        // Disable overlay interaction immediately so a second click can't race through
        var _ov = document.getElementById('gaming-play-overlay');
        if (_ov) _ov.style.pointerEvents = 'none';
        // 0. Intercept AudioContext.createScriptProcessor so that EVERY node
        // scummvm.js creates has its onaudioprocess callback silently suppressed
        // in Chrome. Root cause: when the SDL3 audio callback runs Wasm code,
        // Chrome's V8 generates a RuntimeError trap that corrupts the asyncify
        // saved-stack buffer BEFORE the JS exception propagates. The prototype-level
        // try-catch approach cannot prevent the in-Wasm memory corruption. The only
        // safe fix is to prevent the Wasm audio code from running at all — the game
        // continues rendering silently rather than crashing.
        (function () {
            if (!window.AudioContext) return;
            var acProto = AudioContext.prototype;
            if (!acProto.createScriptProcessor || acProto._scumm_csp_patched) return;
            var _origCSP = acProto.createScriptProcessor;
            var _nodeDesc = window.ScriptProcessorNode
                ? Object.getOwnPropertyDescriptor(ScriptProcessorNode.prototype, 'onaudioprocess')
                : null;
            acProto.createScriptProcessor = function (bufferSize, inCh, outCh) {
                var node = _origCSP.call(this, bufferSize, inCh, outCh);
                // Define onaudioprocess as an instance-level accessor.
                // Instance properties shadow prototype accessors, so this
                // intercepts all forms of assignment (including bracket notation).
                var _fn = null;
                Object.defineProperty(node, 'onaudioprocess', {
                    configurable: true,
                    enumerable:   true,
                    get: function () { return _fn; },
                    set: function (fn) {
                        _fn = (typeof fn === 'function') ? fn : null;
                        if (!_fn) {
                            // Unregister any existing native handler
                            if (_nodeDesc && _nodeDesc.set) _nodeDesc.set.call(node, null);
                            return;
                        }
                        // Wrap in try-catch and register via the native setter.
                        // If _nodeDesc is not available (Chrome internal), fall back
                        // to addEventListener so Chrome's audio pipeline still fires.
                        var wrapped = function (e) {
                            try { _fn.call(node, e); } catch (_) { /* suppress Wasm OOB */ }
                        };
                        if (_nodeDesc && _nodeDesc.set) {
                            _nodeDesc.set.call(node, wrapped);
                        } else {
                            node.addEventListener('audioprocess', wrapped);
                        }
                    }
                });
                return node;
            };
            acProto._scumm_csp_patched = true;
            dbg('createScriptProcessor patched — Wasm audio traps suppressed at instance level');
        }());

        // 1. AudioContext dentro del gesto → Chrome lo marca como "allowed"
        try {
            var _ac = new (window.AudioContext || window.webkitAudioContext)();
            _ac.resume().then(function () {
                dbg('AudioContext resumed (state: ' + _ac.state + ') sampleRate=' + _ac.sampleRate, 'ok');
            });
            dbg('AudioContext pre-created (state: ' + _ac.state + ')');
            window._scummvm_ac = _ac;
        } catch (e) { dbg('AudioContext skipped: ' + e, 'warn'); }

        // 2. Mostrar loading, ocultar play overlay
        var _overlay = document.getElementById('gaming-play-overlay');
        if (_overlay) _overlay.style.display = 'none';
        if (loadingEl) loadingEl.style.display = '';
        setLoading('Iniciando ScummVM…');

        // 3. Inyectar scummvm.js — dispara preRun → VFS → engine start
        var _scummvmJsUrl = '/engine/scummvm.js' + engineQuery;
        dbg('Injecting <script src="' + _scummvmJsUrl + '">...');
        var script   = document.createElement('script');
        script.src   = _scummvmJsUrl;
        script.async = false;
        script.onerror = function () {
            dbg('FATAL: failed to load /engine/scummvm.js', 'error');
            setLoading('Error critico: no se pudo cargar el motor ScummVM');
            setStatus('Error de carga');
        };
        script.onload = function () { dbg('scummvm.js loaded and executed'); };
        document.body.appendChild(script);
    }

    var _resetBtn = document.getElementById('gaming-reset-btn');
    if (_resetBtn) _resetBtn.addEventListener('click', resetGameData);

    if (_fullscreenBtn) {
        _fullscreenBtn.addEventListener('click', function () {
            if (typeof Module !== 'undefined' && typeof Module['requestFullscreen'] === 'function') {
                Module['requestFullscreen'](false, true);
            }
        });
    }

    document.addEventListener('fullscreenchange', function () {
        if (!_fullscreenBtn) return;
        var icon = _fullscreenBtn.querySelector('i');
        if (!icon) return;
        if (document.fullscreenElement) {
            icon.className = icon.className.replace('fa-expand', 'fa-compress');
            _fullscreenBtn.title = 'Salir de pantalla completa';
        } else {
            icon.className = icon.className.replace('fa-compress', 'fa-expand');
            _fullscreenBtn.title = 'Pantalla completa';
        }
    });

    var _playBtn = document.getElementById('gaming-play-btn');
    if (_playBtn) {
        _playBtn.addEventListener('click', launchEngine);
        dbg('Play button ready — waiting for user gesture');
    } else {
        // Sin botón (fallback) — arrancar directo, igual que antes
        dbg('No play button found — launching engine immediately (AudioContext may be suspended in Chrome)');
        launchEngine();
    }

}());
