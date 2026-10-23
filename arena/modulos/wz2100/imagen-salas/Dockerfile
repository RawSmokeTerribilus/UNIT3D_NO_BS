# Arena NOBS · Warzone 2100 — imagen «salas»: WZ nativo headless + agente de salas + relé de sesiones reanudables.
# Contexto de build = carpeta preparada por arena/bin/publicar-imagenes.sh (solo lo necesario, nada del repo ni del .env):
#   wz/bin/warzone2100   wz/share/warzone2100/{base.wz,mp.wz,fonts}   agente.py   relay.py   presets/
FROM ubuntu:24.04

# Bibliotecas de runtime del binario (sacadas con ldd + dpkg -S sobre el build del tag 4.7.0; SDL3 va estático)
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      libasound2t64 libbrotli1 libbsd0 libbz2-1.0 libcairo2 libcurl3t64-gnutls libexpat1 libfontconfig1 \
      libfreetype6 libfribidi0 libglib2.0-0t64 libharfbuzz0b libogg0 libopenal1 libopus0 libphysfs1 \
      libpng16-16t64 libprotobuf32t64 libsndio7.0 libsodium23 libsqlite3-0 libtheora0 libvorbis0a \
      libvorbisfile3 libx11-6 libxext6 libxrender1 libzip4t64 libzstd1 zlib1g \
      python3 python3-venv ca-certificates \
 && python3 -m venv /opt/arena-venv \
 && /opt/arena-venv/bin/pip install --no-cache-dir 'websockets==15.0.1' \
 && rm -rf /var/lib/apt/lists/* \
 && useradd -u 2100 -M -d /datos -s /usr/sbin/nologin arena \
 && install -d -o 2100 -g 2100 /datos

COPY wz/ /opt/wz/
COPY presets/ /opt/arena/presets/
COPY agente.py relay.py /opt/arena/

ENV ARENA_DATOS=/datos WZ_BIN=/opt/wz/bin/warzone2100 PYTHONUNBUFFERED=1
USER 2100
VOLUME ["/datos"]
EXPOSE 8080 8081
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s CMD python3 -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8081/salud', timeout=3)"
ENTRYPOINT ["python3", "/opt/arena/agente.py"]
