#!/usr/bin/env python3
"""Ajusta el tamaño aparente y la posición vertical de un SVG sin tocar CSS.

Encoger el viewBox agranda el dibujo; desplazar su `y` hacia abajo hace que el
dibujo suba dentro de la caja. Ambos son cambios del asset, así que valen igual
en cualquier sitio donde se pinte el icono.

    ajustar-svg.py <fichero> <factor_tamaño> <subida> [salida]

El directorio public/img/insignias es 82:82, asi que por defecto escribe el
resultado en /tmp y hay que copiarlo con sudo.

    factor_tamaño  1.08 = un 8% más grande
    subida         0.04 = sube un 4% de la altura de la caja
"""
import re, sys, shutil

fichero, factor, subida = sys.argv[1], float(sys.argv[2]), float(sys.argv[3])
salida = sys.argv[4] if len(sys.argv) > 4 else '/tmp/' + fichero.split('/')[-1]
s = open(fichero, encoding='utf-8').read()
m = re.search(r'viewBox="\s*([-\d.]+)[ ,]+([-\d.]+)[ ,]+([-\d.]+)[ ,]+([-\d.]+)\s*"', s)
x, y, w, h = (float(v) for v in m.groups())

k = 1.0 / factor
nw, nh = w * k, h * k
nx = x + (w - nw) / 2
ny = y + (h - nh) / 2 + nh * subida     # +y en el viewBox = el dibujo sube

s = s[:m.start()] + f'viewBox="{nx:.4f} {ny:.4f} {nw:.4f} {nh:.4f}"' + s[m.end():]
open(salida, 'w', encoding='utf-8').write(s)
print(f"  {fichero.split('/')[-1]:<22} {x:.3f} {y:.3f} {w:.3f} {h:.3f}  ->  {nx:.3f} {ny:.3f} {nw:.3f} {nh:.3f}")
