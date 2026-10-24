# Arena NOBS · Quake 3 (OpenArena) — imagen «web»: cliente ioquake3 + pk3 de OpenArena + sala de espera + relé /dg.
# Contexto de build = carpeta preparada por arena/bin/publicar-q3.sh:
#   cliente/ (ioquake3.js/.wasm, shell, ioquake3-config.json, baseoa/*.pk3)   lobby/   web.conf
FROM nginx:1.29-alpine
# Plantilla: el entrypoint la vuelca en conf.d con ARENA_PADRE sustituido (en el compose, tmpfs)
COPY web.conf /etc/nginx/templates/default.conf.template
ENV ARENA_PADRE="'self'"
COPY lobby/ /srv/lobby/
COPY cliente/ /srv/cliente/
HEALTHCHECK --interval=30s --timeout=5s CMD wget -q -O /dev/null http://127.0.0.1/cliente/index.html || exit 1
