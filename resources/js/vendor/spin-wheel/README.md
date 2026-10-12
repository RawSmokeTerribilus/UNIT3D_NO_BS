# spin-wheel 5.0.2 (CrazyTim, MIT)

Copia literal de `dist/spin-wheel-esm.js` del paquete npm `spin-wheel@5.0.2`
(https://github.com/CrazyTim/spin-wheel), con su `LICENSE.md`. Se copia en vez de
instalarlo con npm para que el porte a producción sea copiar un fichero y no
resolver dependencias allí.

Revisado el 2026-10-12: sin peticiones de red, sin `eval`, sin cookies ni
almacenamiento. Lo usa la ruleta del mirón de la página de donar, a través de
`resources/js/vendor/ruleta.js`.

No editar el fichero: para actualizar, sustituirlo entero por el de otra versión.
