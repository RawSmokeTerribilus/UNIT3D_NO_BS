# Arena NOBS · Warzone 2100 — imagen «web»: cliente wasm parcheado + sala de espera + puente /ws.
# Contexto de build = carpeta preparada por arena/bin/publicar-imagenes.sh:
#   cliente/ (dist del build web, sin share/)   lobby/   web.conf
FROM nginx:1.29-alpine
COPY web.conf /etc/nginx/conf.d/default.conf
COPY lobby/ /srv/lobby/
COPY cliente/ /srv/cliente/
HEALTHCHECK --interval=30s --timeout=5s CMD wget -q -O /dev/null http://127.0.0.1/cliente/index.html || exit 1
