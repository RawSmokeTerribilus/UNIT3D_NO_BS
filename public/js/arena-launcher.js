/**
 * NOBS — Arena: botón de pantalla completa de /arena/<slug>.
 *
 * Pone en pantalla completa el contenedor del iframe (no el iframe), así el juego ocupa toda
 * la pantalla. Fichero aparte porque la CSP de gaming.isolation no admite JS inline.
 *
 * Esc: los juegos lo usan para su menú (Quake 3) y el navegador para salir de pantalla completa
 * y soltar el ratón. Con la Keyboard Lock API (Chrome/Edge; solo en la página de arriba, por eso
 * va aquí y no en el módulo) Esc llega al juego y para salir se mantiene Esc pulsado. Al salir,
 * el juego vuelve a quedar centrado en la página.
 */
(function () {
    'use strict';

    var boton = document.getElementById('arena-fullscreen');
    var marco = document.getElementById('arena-frame');
    if (!boton || !marco) {
        return;
    }
    var envoltorio = marco.parentElement;
    var bloqueo = navigator.keyboard && typeof navigator.keyboard.lock === 'function';

    if (!document.fullscreenEnabled) {
        boton.hidden = true;
        return;
    }

    boton.title = bloqueo
        ? 'Pantalla completa (para salir, mantén pulsado Esc)'
        : 'Pantalla completa (Esc sale; el menú del juego también está en F10)';

    boton.addEventListener('click', function () {
        if (document.fullscreenElement) {
            document.exitFullscreen();
            return;
        }
        envoltorio.requestFullscreen().then(function () {
            if (bloqueo) {
                navigator.keyboard.lock(['Escape']).catch(function () {});
            }
            // Que el teclado vaya al juego sin tener que hacer clic dentro
            marco.focus();
        }).catch(function () {});
    });

    document.addEventListener('fullscreenchange', function () {
        if (document.fullscreenElement) {
            return;
        }
        if (bloqueo) {
            navigator.keyboard.unlock();
        }
        // De vuelta a la página: el juego centrado y con el foco, sin pelear con el scroll
        envoltorio.scrollIntoView({ block: 'center', behavior: 'instant' });
        marco.focus();
    });
})();
