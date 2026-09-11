# Panel de consultas para auditorías — imagen.
#
# Base: la misma imagen del banco forense, que ya está en disco y trae
# Python 3.11, mysqlbinlog 8.0 y el cliente mysql. El host va con Python 3.9
# (RHEL), que es la razón por la que esto se contiene en vez de vivir fuera.
#
# Este contenedor NO monta docker.sock y NO ejecuta docker. Sólo lee tres
# servicios por red (MySQL, Loki, Prometheus) y un directorio de binlogs en :ro.
FROM rawsmoke/unit3d-forensics:v0.1.0

# PyMySQL desde Debian: sin pip, sin pelearse con PEP 668.
# cryptography es OBLIGATORIO: MySQL 8 autentica con caching_sha2_password y
# sin él PyMySQL ni siquiera conecta.
RUN apt-get update \
 && apt-get install -y --no-install-recommends python3-pymysql python3-cryptography python3-maxminddb \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

ENV HOME=/tmp \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

EXPOSE 8781
ENTRYPOINT ["python3", "-u", "/app/server.py"]
