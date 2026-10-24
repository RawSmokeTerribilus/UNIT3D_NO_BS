#!/usr/bin/env python3
"""Arena NOBS · SuperTuxKart — ajustes al JS del motor (build de la demo de ading2210), sin recompilar.

Resolución de render acotada: el SDL de Emscripten redimensiona la ventana al tamaño CSS del canvas
(STK la crea RESIZABLE), así que a pantalla completa pintaba a la resolución entera del monitor y los
fps (y con ellos los controles) se hundían. Se escala por globalThis.arenaFactor (lo define pre.js,
720 de alto por defecto) en los dos sitios que leen el tamaño del canvas:
  1. emscripten_get_element_css_size → el motor «ve» el canvas reducido y pinta a esa resolución
     (CSS lo estira a pantalla);
  2. fillMouseEventData (targetX/Y) → SDL convierte con xscale = ancho_ventana / css, así que con las dos
     cosas escaladas por el mismo factor el ratón sigue cayendo donde toca en los menús.

Uso: ajustar_motor.py <supertuxkart.js>
"""
import re
import sys

CAMBIOS = [
    ('var rect=getBoundingClientRect(target);HEAPF64[width>>3]=rect.width;HEAPF64[height>>3]=rect.height;return 0}',
     'var rect=getBoundingClientRect(target);var f=globalThis.arenaFactor?globalThis.arenaFactor(target,rect):1;'
     'HEAPF64[width>>3]=rect.width*f;HEAPF64[height>>3]=rect.height*f;return 0}'),
]
# El ratón: los índices de HEAP32 cambian con la versión de Emscripten (13/14 en la demo, 10/11 con 4.0.10)
RATON = re.compile(r'var rect=getBoundingClientRect\(target\);HEAP32\[idx\+(\d+)\]=e\.clientX-\(rect\.left\|0\);'
                   r'HEAP32\[idx\+(\d+)\]=e\.clientY-\(rect\.top\|0\)\}')
RATON_NUEVO = (r'var rect=getBoundingClientRect(target);var f=globalThis.arenaFactor?globalThis.arenaFactor(target,rect):1;'
               r'HEAP32[idx+\1]=(e.clientX-(rect.left|0))*f;HEAP32[idx+\2]=(e.clientY-(rect.top|0))*f}')

ruta = sys.argv[1]
js = open(ruta, encoding='utf-8').read()
for viejo, nuevo in CAMBIOS:
    if nuevo in js:
        continue
    assert js.count(viejo) == 1, f'no encuentro (o hay varias): {viejo[:60]}…'
    js = js.replace(viejo, nuevo)
if 'HEAP32[idx+' not in js or '(e.clientX-(rect.left|0))*f' not in js:
    js, n = RATON.subn(RATON_NUEVO, js)
    assert n == 1, f'no encuentro el cálculo del ratón (coincidencias: {n})'
open(ruta, 'w', encoding='utf-8').write(js)
print('motor STK ajustado: resolución acotada por arenaFactor')
