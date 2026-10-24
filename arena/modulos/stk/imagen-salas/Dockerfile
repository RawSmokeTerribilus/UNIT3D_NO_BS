# Arena NOBS · SuperTuxKart — imagen «salas»: servidor STK nativo (SERVER_ONLY) + datos + agente + relé (/dg).
# Contexto = carpeta preparada por arena/bin/publicar-stk.sh: stk/{supertuxkart, data/}  agente.py  relay.py  presets/
# ubuntu:24.04 porque el binario se compila ahí.
FROM ubuntu:24.04

RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends libcurl4t64 libssl3t64 libenet7 zlib1g \
      python3 python3-venv ca-certificates \
 && python3 -m venv /opt/arena-venv \
 && /opt/arena-venv/bin/pip install --no-cache-dir 'websockets==15.0.1' \
 && rm -rf /var/lib/apt/lists/* \
 && useradd -u 2103 -M -d /datos -s /usr/sbin/nologin arena \
 && install -d -o 2103 -g 2103 /datos

COPY stk/ /opt/stk/
COPY presets/ /opt/arena/presets/
COPY agente.py relay.py /opt/arena/

ENV ARENA_DATOS=/datos STK_BIN=/opt/stk/supertuxkart STK_DATOS=/opt/stk PYTHONUNBUFFERED=1
USER 2103
VOLUME ["/datos"]
EXPOSE 8080 8081
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s CMD python3 -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8081/salud', timeout=3)"
ENTRYPOINT ["python3", "/opt/arena/agente.py"]
