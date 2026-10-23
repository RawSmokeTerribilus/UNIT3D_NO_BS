#!/usr/bin/env python3
"""Arena NOBS · Warzone 2100 — shell propio del cliente, sin recompilar el wasm.

Toma el index.html que genera el build web (shell oficial de WZ + parche Arena) y lo deja apto
para una CSP estricta y sin terceros (revisión de seguridad H1/H2, 23-10-2026):
  - <style> y scripts inline → ficheros propios (arena-shell.css, arena-shell-N.js)
  - atributos on*="…" → arena-eventos.js (addEventListener; sin 'unsafe-inline')
  - Bootstrap de cdnjs → copia local en vendor/ (mismo SRI): ninguna IP de socio sale a terceros
  - sin PWA ni service worker: service-worker.js pasa a ser un SW que se da de baja (los socios
    que ya entraron tienen registrado el oficial, que serviría su caché vieja)
  - textos en castellano y aviso visible cuando el relé cierra la sesión (vetado, límites…)
  - regenera los .gz de lo que toca (nginx sirve gzip_static: un .gz viejo ganaría)

Uso: rehacer_shell.py <carpeta cliente>   (la copia del dist dentro del contexto de la imagen)
"""

import gzip
import html
import re
import shutil
import sys
from pathlib import Path

AQUI = Path(__file__).resolve().parent
REPO_ARENA = 'https://codeberg.org/RawSmoke/UNIT3D/src/branch/staging-fresh-20260616/arena/modulos/wz2100'

TEXTOS = {
    'Warzone 2100 - Web Edition': 'Warzone 2100 · Arena NOBS',
    'Loading ...': 'Cargando…',
    'Web Edition': 'Arena NOBS',
    'Get the Full Version': 'Versión completa',
    'Donate': 'Donar',
    'Options': 'Opciones',
    'Javascript is disabled': 'JavaScript está desactivado',
    'The web edition of Warzone 2100 requires Javascript to be enabled': 'Warzone 2100 en el navegador necesita JavaScript',
    'Please enable Javascript and refresh your browser to continue.': 'Actívalo y recarga la página.',
    'Unsupported Browser': 'Navegador no compatible',
    'Unfortunately, your browser does not support the Web Edition of Warzone 2100': 'Tu navegador no puede ejecutar Warzone 2100',
    'Please try updating to the latest version of a supported browser': 'Prueba con la última versión de un navegador compatible',
    '(ex. Chrome, Edge, Firefox, or Safari).': '(Chrome, Edge, Firefox o Safari).',
    'Missing or disabled features:': 'Funciones que faltan o están desactivadas:',
    'Launch the Web Edition': 'Entrar en la sala',
    'Play Now': 'Jugar',
    'If this is your first time, this will download approximately 60MiB of data.': 'La primera vez descarga unos 45 MB.',
    'View Options': 'Opciones',
    'An error occurred trying to load Warzone 2100.': 'No se pudo cargar Warzone 2100.',
    'Please check your Internet connection, and reload to try again.': 'Revisa la conexión y recarga para reintentarlo.',
    'Error Message:': 'Mensaje de error:',
    'Reload': 'Recargar',
    'Thanks for playing!': '¡Gracias por jugar!',
    "If you'd like to play again, simply reload.": 'Para volver a jugar, recarga o vuelve a la sala de espera.',
    'Or help support by donating:': 'O apoya al proyecto Warzone 2100:',
    'Troubleshooting: If requested, you can': 'Si el staff te lo pide, puedes',
    'view logs': 'ver los registros',
    'from this last run.': 'de esta partida.',
    'Or download the Full Version, with:': 'O descarga la versión completa, con:',
    'Download Warzone 2100': 'Descargar Warzone 2100',
    'Get the Source': 'Código fuente',
    'Too Small': 'Pantalla demasiado pequeña',
    'Window / display is too small for Warzone 2100': 'La ventana es demasiado pequeña para Warzone 2100',
    'Required Minimum: 640x480': 'Mínimo: 640×480',
    'Reset All Configuration and Data?': '¿Borrar toda la configuración y los datos?',
    'This will': 'Esto',
    'erase all Warzone 2100 saved games / data, configuration & settings': 'borra todas las partidas guardadas, datos y ajustes de Warzone 2100',
    'Are you sure you want to continue?': '¿Seguro que quieres seguir?',
    '(This operation is irreversible.)': '(No se puede deshacer.)',
    'Cancel': 'Cancelar',
    'Yes, Reset All Saved Data': 'Sí, borrarlo todo',
    'Launch Options': 'Opciones de arranque',
    'Load Music': 'Cargar la música',
    'Load Classic Terrain Pack': 'Cargar el terreno clásico',
    'Advanced Options': 'Opciones avanzadas',
    'Debug Log Options:': 'Registro de depuración:',
    'Wasm.Memory Limit:': 'Límite de memoria:',
    'Configures the memory limit for the game. Not all systems or browsers may support higher limits.': 'Memoria máxima del juego. No todos los equipos o navegadores admiten los límites altos.',
    'Web App Install Prompt:': 'Instalar como aplicación:',
    'Hidden': 'Oculto',
    'Available': 'Disponible',
    'Configures whether the Web App install prompt is available (ex. on desktop systems, where the full download is recommended).': 'Si se ofrece instalarlo como aplicación.',
    'Checking storage status ...': 'Comprobando el almacenamiento…',
    'Storage Persistence: Enabled': 'Almacenamiento persistente: activado',
    'Game configuration and savegames should be persisted, but manually clearing browser cache can still remove them.': 'La configuración se conserva, pero borrar la caché del navegador la elimina.',
    'Storage Persistence: Not Enabled': 'Almacenamiento persistente: desactivado',
    'Game configuration and savegames may be automatically cleared by the browser.': 'El navegador puede borrar la configuración por su cuenta.',
    'Reset Configuration & Saved Data': 'Borrar configuración y datos',
    'Version:': 'Versión:',
    'Git Commit:': 'Commit:',
    'Runtime Error:': 'Error durante la partida:',
    'Restart WZ': 'Reiniciar',
    'Warzone 2100 has encountered an error:': 'Warzone 2100 ha tenido un error:',
    'View Logs': 'Ver registros',
    'The following logs were generated from the most recent run of Warzone 2100:': 'Registros de la última ejecución:',
    'Close': 'Cerrar',
    'Classic look,': 'Multijugador entre socios,',
    'Medium-quality textures,': 'salas con IA,',
    'Campaign & skirmish': 'sin instalar nada',
    'Plays Best With:': 'Mejor con:',
    'Mouse & Keyboard': 'ratón y teclado',
    'HQ remastered graphics,': 'gráficos remasterizados,',
    'Online multiplayer,': 'multijugador online,',
    'Better performance': 'mejor rendimiento',
    'Copyright © Warzone 2100 Project. Licensed under': 'Copyright © Warzone 2100 Project. Licencia',
    'Or get the Full Desktop version, with HQ remastered graphics, Online multiplayer, Better performance, and more.': 'O descarga la versión de escritorio: gráficos remasterizados, multijugador online, mejor rendimiento y más.',
    'Warnings': 'Avisos',
    'Update Required': 'Actualización pendiente',
    'Warzone 2100 must be restarted to apply updates.': 'Hay que reiniciar Warzone 2100 para aplicarla.',
    'Restart Warzone 2100': 'Reiniciar Warzone 2100',
}

SW_BAJA = """// Arena NOBS: este cliente no usa service worker. Si quedó registrado el oficial de WZ (que
// serviría su caché vieja), este lo sustituye, se da de baja y recarga las pestañas una vez.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (ev) => {
  ev.waitUntil(self.registration.unregister()
    .then(() => self.clients.matchAll({ type: 'window' }))
    .then((pestanas) => pestanas.forEach((p) => p.navigate(p.url))));
});
"""

# Tal como queda en warzone2100.js tras la minificación de Emscripten (-O3)
FIN_ORIGINAL = 'console.log("Arena: relay ended the session: "+(new TextDecoder).decode(f.subarray(1)));'
FIN_NUEVO = ('var arenaMotivo=(new TextDecoder).decode(f.subarray(1));'
             'console.log("Arena: relay ended the session: "+arenaMotivo);'
             'if(window.arenaFin){window.arenaFin(arenaMotivo)}')


def traducir(trozo):
    """Traduce el texto visible entre etiquetas (sin tocar los <script>, que ya están fuera)."""
    def sustituir(m):
        crudo = m.group(1)
        limpio = html.unescape(crudo).strip()
        if limpio in TEXTOS:
            return '>' + crudo.replace(crudo.strip(), html.escape(TEXTOS[limpio], quote=False)) + '<'
        return m.group(0)
    return re.sub(r'>([^<>]+)<', sustituir, trozo)


def main(dst):
    dst = Path(dst)
    h = (dst / 'index.html').read_text(encoding='utf-8')

    # <style> → css propio
    estilos = re.findall(r'<style[^>]*>(.*?)</style>', h, re.S)
    assert estilos, 'el shell no trae <style>'
    (dst / 'arena-shell.css').write_text('\n'.join(estilos), encoding='utf-8')
    h = re.sub(r'<style[^>]*>.*?</style>', '', h, count=0, flags=re.S)
    h = h.replace('</head>', '<link rel="stylesheet" href="arena-shell.css">\n</head>', 1)

    # Bootstrap local (mismo SRI que el oficial: la copia de vendor/ se verificó contra él)
    vendor = dst / 'vendor'
    shutil.copytree(AQUI / 'vendor', vendor, dirs_exist_ok=True)
    for remoto, local in (('https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css', 'vendor/bootstrap-5.3.2/bootstrap.min.css'),
                          ('https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js', 'vendor/bootstrap-5.3.2/bootstrap.bundle.min.js')):
        assert remoto in h, remoto
        h = h.replace(remoto, local)
    h = h.replace(' crossorigin="anonymous" referrerpolicy="no-referrer"', '')

    # Sin PWA
    h = re.sub(r'\s*<link rel="manifest"[^>]*>', '', h)

    # Scripts inline → ficheros (mismo sitio y orden: los <script src> sin async ejecutan en orden)
    n = 0
    def sacar(m):
        nonlocal n
        attrs, cuerpo = m.group(1), m.group(2)
        if 'src=' in attrs or not cuerpo.strip():
            return m.group(0)
        n += 1
        (dst / f'arena-shell-{n}.js').write_text(cuerpo, encoding='utf-8')
        return f'<script src="arena-shell-{n}.js"></script>'
    h = re.sub(r'<script([^>]*)>(.*?)</script>', sacar, h, flags=re.S)
    assert n >= 2, f'esperaba los scripts inline del shell, salieron {n}'

    # on*="…" → arena-eventos.js
    manejadores = []
    def evento(m):
        manejadores.append((m.group(1), html.unescape(m.group(2))))
        return f' data-arena-on="{m.group(1)}:{len(manejadores) - 1}"'
    h = re.sub(r' on([a-z]+)="([^"]*)"', evento, h)
    cuerpo = ',\n'.join(f'  {i}: function (event) {{ {codigo} }}' for i, (_, codigo) in enumerate(manejadores))
    (dst / 'arena-eventos.js').write_text(
        "// Generado por rehacer_shell.py: los on*=\"…\" del shell oficial, como listeners (CSP sin 'unsafe-inline').\n"
        "(function () {\n  var M = {\n" + cuerpo + "\n  };\n"
        "  document.querySelectorAll('[data-arena-on]').forEach(function (el) {\n"
        "    var p = el.getAttribute('data-arena-on').split(':');\n"
        "    el.addEventListener(p[0], function (event) { return M[p[1]].call(el, event); });\n"
        "  });\n})();\n", encoding='utf-8')

    # Lo nuestro, al final del body: eventos + arena-shell.js (SW fuera, aviso de fin de sesión)
    shutil.copy(AQUI / 'arena-shell.js', dst / 'arena-shell.js')
    h = h.replace('</body>', '<script src="arena-eventos.js"></script>\n<script src="arena-shell.js"></script>\n</body>', 1)

    h = traducir(h).replace('<html lang="en"', '<html lang="es"')
    # GPL: además del código de WZ, los cambios de la Arena (parche + build + este shell)
    fuente = '>Código fuente</a>.'
    assert h.count(fuente) == 1, 'no encuentro el enlace al código fuente'
    h = h.replace(fuente, fuente + ' Cambios de la Arena: <a href="' + REPO_ARENA + '" rel="noopener" target="_blank" '
                  'class="text-white" style="--bs-text-opacity: .5;">parche y build</a>.')
    (dst / 'index.html').write_text(h, encoding='utf-8')

    # Service worker de baja y fuera workbox
    (dst / 'service-worker.js').write_text(SW_BAJA, encoding='utf-8')
    for w in dst.glob('workbox-*.js*'):
        w.unlink()
    (dst / 'manifest.json').unlink(missing_ok=True)

    # Aviso de fin de sesión en el ArenaSocket (prejs) del build actual; el parche ya lo trae para los próximos
    js = (dst / 'warzone2100.js').read_text(encoding='utf-8')
    if 'window.arenaFin' not in js:
        assert js.count(FIN_ORIGINAL) == 1, 'no encuentro el aviso FIN del ArenaSocket en warzone2100.js'
        (dst / 'warzone2100.js').write_text(js.replace(FIN_ORIGINAL, FIN_NUEVO), encoding='utf-8')

    # .gz al día de todo lo tocado/nuevo (gzip_static)
    for f in dst.rglob('*'):
        if f.suffix in ('.html', '.js', '.css') and f.is_file():
            with open(f, 'rb') as ent, gzip.open(str(f) + '.gz', 'wb', compresslevel=9) as sal:
                shutil.copyfileobj(ent, sal)
    for gz in dst.rglob('*.gz'):
        if not Path(str(gz)[:-3]).exists():
            gz.unlink()

    # Comprobaciones finales
    final = (dst / 'index.html').read_text(encoding='utf-8')
    assert 'cdnjs' not in final, 'queda cdnjs en el shell'
    assert not re.search(r' on[a-z]+="', final), 'quedan manejadores inline'
    assert not re.search(r'<script(?![^>]*src=)[^>]*>\s*\S', final), 'quedan scripts inline'
    print(f'shell propio: {n} scripts, {len(manejadores)} manejadores, {len(estilos)} bloques de estilo')


if __name__ == '__main__':
    main(sys.argv[1])
