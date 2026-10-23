/**
 * NOBS — Arena: botón de pantalla completa de /arena/<slug>.
 *
 * Pone en pantalla completa el contenedor del iframe (no el iframe), así el juego
 * ocupa toda la pantalla y Esc devuelve a la página. Fichero aparte porque la CSP
 * de gaming.isolation no admite JS inline.
 */
(function () {
    'use strict';

    var boton = document.getElementById('arena-fullscreen');
    var marco = document.getElementById('arena-frame');
    if (!boton || !marco) {
        return;
    }
    var envoltorio = marco.parentElement;

    if (!document.fullscreenEnabled) {
        boton.hidden = true;
        return;
    }

    boton.addEventListener('click', function () {
        if (document.fullscreenElement) {
            document.exitFullscreen();
            return;
        }
        envoltorio.requestFullscreen().then(function () {
            // Que el teclado vaya al juego sin tener que hacer clic dentro
            marco.focus();
        }).catch(function () {});
    });
})();
