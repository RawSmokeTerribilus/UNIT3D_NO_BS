# Arena NOBS · Counter-Strike 1.6 — imagen «salas»: Xash3D FWGS dedicado + cs16-client (ReGameDLL + yapb)
# + datos de HLDS recortados + agente de salas + relé común (/dg).
# Contexto de build = carpeta preparada por arena/bin/publicar-cs16.sh (solo lo necesario):
#   cs16/{xash, filesystem_stdio.so, valve/, cstrike/ (con dlls/cs_amd64.so, yapb_amd64.so, extras.pk3)}
#   agente.py   relay.py   presets/
# ubuntu:24.04 porque los binarios se compilan ahí (glibc 2.39).
FROM ubuntu:24.04

RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends python3 python3-venv ca-certificates \
 && python3 -m venv /opt/arena-venv \
 && /opt/arena-venv/bin/pip install --no-cache-dir 'websockets==15.0.1' \
 && rm -rf /var/lib/apt/lists/* \
 && useradd -u 2102 -M -d /datos -s /usr/sbin/nologin arena \
 && install -d -o 2102 -g 2102 /datos

COPY cs16/ /opt/cs16/
COPY presets/ /opt/arena/presets/
COPY agente.py relay.py /opt/arena/

ENV ARENA_DATOS=/datos CS_BIN=/opt/cs16/xash CS_BASE=/opt/cs16 PYTHONUNBUFFERED=1
USER 2102
VOLUME ["/datos"]
EXPOSE 8080 8081
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s CMD python3 -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8081/salud', timeout=3)"
ENTRYPOINT ["python3", "/opt/arena/agente.py"]
