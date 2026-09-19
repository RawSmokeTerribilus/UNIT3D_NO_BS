/*
 * NOBS — Nuclear Order Bit Syndicate
 * Copyright (C) 2026 RawSmoke
 * Obra original de NOBS, parte de un derivado de UNIT3D Community Edition
 * (HDInnovations) del que hereda la licencia.
 *
 * Modulo «nuke» de la lista de reportes (livewire/includes/_nuke-panel).
 * Secuencia: 1 llave (arma) → 2 interruptores (modo, los lleva Livewire) →
 * 3 tapa → 4 mantener la seta MANTENER_MS. Se desarma solo a los ARMADO_MS.
 *
 * El estado vive aqui y en clases de las dos columnas con wire:ignore: el
 * resto del panel lo re-renderiza Livewire y perderia cualquier atributo.
 * La autorizacion NO esta aqui: ReportSearch::nuke() la comprueba en el
 * servidor.
 */
(function () {
    'use strict';

    var MANTENER_MS = 1500;
    var ARMADO_MS = 30000;

    var estado = 'desarmado'; // desarmado | armado | abierto | disparando
    var temporizador = null;
    var carga = null;

    function raiz() {
        return document.querySelector('[data-nuke-root]');
    }

    function lados(r) {
        return r ? r.querySelectorAll('[data-nuke-lado]') : [];
    }

    function cuenta(r) {
        var d = r && r.querySelector('[data-nuke-display]');
        return d ? parseInt(d.getAttribute('data-cuenta'), 10) || 0 : 0;
    }

    function rotulo(r, texto) {
        var e = r && r.querySelector('[data-nuke-estado]');
        if (e) {
            e.textContent = texto;
        }
    }

    function pintar() {
        var r = raiz();
        if (!r) {
            return;
        }
        var armado = estado !== 'desarmado';
        var abierto = estado === 'abierto' || estado === 'disparando';
        lados(r).forEach(function (l) {
            l.classList.toggle('is-armado', armado);
            l.classList.toggle('is-abierto', abierto);
        });
        var llave = r.querySelector('[data-nuke-llave]');
        if (llave) {
            llave.setAttribute('aria-pressed', armado ? 'true' : 'false');
        }
        var seta = r.querySelector('[data-nuke-seta]');
        if (seta) {
            seta.disabled = !(estado === 'abierto');
        }
        if (estado === 'desarmado') {
            rotulo(r, 'desarmado');
        } else if (estado === 'armado') {
            rotulo(r, 'armado · levanta la tapa');
        } else if (estado === 'abierto') {
            rotulo(r, cuenta(r) > 0 ? 'mantén la seta' : 'nada que descartar');
        }
    }

    function rearmarTemporizador() {
        clearTimeout(temporizador);
        if (estado !== 'desarmado' && estado !== 'disparando') {
            temporizador = setTimeout(desarmar, ARMADO_MS);
        }
    }

    function desarmar() {
        cancelarCarga();
        estado = 'desarmado';
        clearTimeout(temporizador);
        pintar();
    }

    function traba(el) {
        if (!el) {
            return;
        }
        el.classList.remove('is-traba');
        void el.offsetWidth;
        el.classList.add('is-traba');
    }

    function cancelarCarga() {
        var r = raiz();
        var seta = r && r.querySelector('[data-nuke-seta]');
        if (carga) {
            cancelAnimationFrame(carga.raf);
            carga = null;
        }
        if (seta) {
            seta.classList.remove('is-apretada');
            seta.style.setProperty('--nuke-carga', '0');
        }
    }

    function empezarCarga() {
        var r = raiz();
        var seta = r && r.querySelector('[data-nuke-seta]');
        if (!seta || estado !== 'abierto' || carga) {
            return;
        }
        if (cuenta(r) === 0) {
            traba(seta.closest('.nuke__caja'));
            rotulo(r, 'nada que descartar');
            return;
        }
        seta.classList.add('is-apretada');
        var inicio = performance.now();
        carga = { raf: 0 };
        var paso = function (ahora) {
            if (!carga) {
                return;
            }
            var p = Math.min(1, (ahora - inicio) / MANTENER_MS);
            seta.style.setProperty('--nuke-carga', String(p));
            if (p >= 1) {
                carga = null;
                disparar();
                return;
            }
            carga.raf = requestAnimationFrame(paso);
        };
        carga.raf = requestAnimationFrame(paso);
    }

    function componente(r) {
        var host = r && r.closest('[wire\\:id]');
        if (!host || !window.Livewire) {
            return null;
        }
        return window.Livewire.find(host.getAttribute('wire:id'));
    }

    function disparar() {
        var r = raiz();
        var wire = componente(r);
        estado = 'disparando';
        clearTimeout(temporizador);
        rotulo(r, 'ejecutando…');
        if (!wire) {
            rotulo(r, 'error: sin livewire');
            setTimeout(desarmar, 3000);
            return;
        }
        wire.call('nuke');
    }

    document.addEventListener('click', function (ev) {
        var r = raiz();
        if (!r || !r.contains(ev.target)) {
            return;
        }
        if (ev.target.closest('[data-nuke-llave]')) {
            if (estado === 'desarmado') {
                estado = 'armado';
            } else if (estado !== 'disparando') {
                estado = 'desarmado';
            }
            pintar();
            rearmarTemporizador();
            return;
        }
        var tapa = ev.target.closest('[data-nuke-tapa]');
        if (tapa) {
            if (estado === 'armado') {
                estado = 'abierto';
                pintar();
                rearmarTemporizador();
            } else {
                traba(tapa.closest('.nuke__caja'));
                rotulo(r, 'primero la llave');
            }
            return;
        }
        if (r.contains(ev.target) && estado !== 'desarmado') {
            rearmarTemporizador();
        }
    });

    // La seta: mantener pulsado. Raton, tactil y teclado.
    document.addEventListener('pointerdown', function (ev) {
        if (ev.target.closest && ev.target.closest('[data-nuke-seta]')) {
            ev.preventDefault();
            empezarCarga();
        }
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (tipo) {
        document.addEventListener(tipo, function (ev) {
            if (carga && (tipo !== 'pointerleave' || (ev.target.closest && ev.target.closest('[data-nuke-seta]')))) {
                cancelarCarga();
            }
        }, true);
    });
    document.addEventListener('keydown', function (ev) {
        if ((ev.key === ' ' || ev.key === 'Enter') && ev.target.closest && ev.target.closest('[data-nuke-seta]')) {
            ev.preventDefault();
            if (!ev.repeat) {
                empezarCarga();
            }
        }
    });
    document.addEventListener('keyup', function (ev) {
        if ((ev.key === ' ' || ev.key === 'Enter') && carga) {
            cancelarCarga();
        }
    });

    function alIniciarLivewire() {
        window.Livewire.on('nuke-hecho', function (datos) {
            var d = Array.isArray(datos) ? datos[0] : datos;
            var n = d && typeof d.cerrados === 'number' ? d.cerrados : 0;
            cancelarCarga();
            rotulo(raiz(), n === 0 ? 'nada que descartar' : '☢ ' + n + ' a la playa');
            setTimeout(desarmar, 4000);
        });
    }

    if (window.Livewire) {
        alIniciarLivewire();
    } else {
        document.addEventListener('livewire:init', alIniciarLivewire);
    }
})();
