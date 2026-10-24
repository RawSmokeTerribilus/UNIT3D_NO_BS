#!/usr/bin/env python3
"""Arena NOBS — resolución de render acotada en motores Emscripten+SDL2 con ventana redimensionable
(SuperTuxKart, Xash3D/CS), sin recompilar.

El SDL de Emscripten redimensiona la ventana al tamaño CSS del canvas, así que a pantalla completa el
motor pinta a la resolución entera del monitor. Se escala por globalThis.arenaFactor(objetivo, rect)
(lo define el shell de cada juego: 720 de alto por defecto, ?alto=N para probar) en los dos sitios que
leen el tamaño del canvas en el JS de Emscripten:
  1. emscripten_get_element_css_size → el motor «ve» el canvas reducido (CSS lo estira a pantalla);
  2. fillMouseEventData (targetX/Y) → SDL convierte con ancho_ventana / css: con las dos cosas
     escaladas por el mismo factor, el ratón sigue cayendo donde toca.
Vale para el JS minificado y para el formateado con espacios (cambia según la build).

Uso: ajustar_resolucion.py <fichero.js>
"""
import re
import sys

CSS = re.compile(r'var rect\s*=\s*getBoundingClientRect\(target\);\s*HEAPF64\[width\s*>>\s*3\]\s*=\s*rect\.width;\s*'
                 r'HEAPF64\[height\s*>>\s*3\]\s*=\s*rect\.height;\s*return 0;?\s*\}')
CSS_NUEVO = ('var rect=getBoundingClientRect(target);var f=globalThis.arenaFactor?globalThis.arenaFactor(target,rect):1;'
             'HEAPF64[width>>3]=rect.width*f;HEAPF64[height>>3]=rect.height*f;return 0}')
RATON = re.compile(r'var rect\s*=\s*getBoundingClientRect\(target\);\s*HEAP32\[idx\s*\+\s*(\d+)\]\s*=\s*e\.clientX\s*-\s*'
                   r'\(rect\.left\s*\|\s*0\);\s*HEAP32\[idx\s*\+\s*(\d+)\]\s*=\s*e\.clientY\s*-\s*\(rect\.top\s*\|\s*0\);?\s*\}')
RATON_NUEVO = (r'var rect=getBoundingClientRect(target);var f=globalThis.arenaFactor?globalThis.arenaFactor(target,rect):1;'
               r'HEAP32[idx+\1]=(e.clientX-(rect.left|0))*f;HEAP32[idx+\2]=(e.clientY-(rect.top|0))*f}')

ruta = sys.argv[1]
js = open(ruta, encoding='utf-8').read()
if 'globalThis.arenaFactor' not in js:
    js, n1 = CSS.subn(CSS_NUEVO, js)
    js, n2 = RATON.subn(RATON_NUEVO, js)
    assert n1 == 1 and n2 == 1, f'no encuentro las funciones del canvas (css {n1}, ratón {n2})'
    open(ruta, 'w', encoding='utf-8').write(js)
print(f'resolución acotada por arenaFactor: {ruta.rsplit("/", 1)[-1]}')
