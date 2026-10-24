# Arena NOBS · Quake 3 (OpenArena) — imagen «salas»: ioq3ded nativo + agente de salas + relé común (/dg).
# Contexto de build = carpeta preparada por arena/bin/publicar-q3.sh (solo lo necesario):
#   q3/ioq3ded   q3/baseoa/*.pk3   agente.py   relay.py   presets/
FROM debian:bookworm-slim

RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends python3 python3-venv ca-certificates \
 && python3 -m venv /opt/arena-venv \
 && /opt/arena-venv/bin/pip install --no-cache-dir 'websockets==15.0.1' \
 && rm -rf /var/lib/apt/lists/* \
 && useradd -u 2101 -M -d /datos -s /usr/sbin/nologin arena \
 && install -d -o 2101 -g 2101 /datos

COPY q3/ /opt/q3/
COPY presets/ /opt/arena/presets/
COPY agente.py relay.py /opt/arena/

ENV ARENA_DATOS=/datos Q3_BIN=/opt/q3/ioq3ded Q3_BASE=/opt/q3 PYTHONUNBUFFERED=1
USER 2101
VOLUME ["/datos"]
EXPOSE 8080 8081
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s CMD python3 -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8081/salud', timeout=3)"
ENTRYPOINT ["python3", "/opt/arena/agente.py"]
