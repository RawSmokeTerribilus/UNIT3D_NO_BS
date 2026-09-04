#!/bin/sh
set -eu

urlencode() {
    python3 -c 'import sys, urllib.parse; print(urllib.parse.quote(sys.argv[1], safe=""))' "$1"
}

db_user="${TRACKER_DB_USERNAME:-${DB_USERNAME:-}}"
db_password="${TRACKER_DB_PASSWORD:-${DB_PASSWORD:-}}"
db_name="${TRACKER_DB_DATABASE:-${DB_DATABASE:-}}"
db_host="${TRACKER_DB_HOST:-db}"
db_port="${TRACKER_DB_PORT:-3306}"

if [ -z "${db_user}" ] || [ -z "${db_password}" ] || [ -z "${db_name}" ]; then
    echo "Missing database credentials for unit3d-announce." >&2
    exit 1
fi

if [ -z "${TRACKER_KEY:-}" ]; then
    echo "TRACKER_KEY is required for unit3d-announce." >&2
    exit 1
fi

# El freeleech global lo gobierna el dashboard (settings `other.freeleech`), que
# reescribe DOWNLOAD_FACTOR en este mismo fichero y llama a /config/reload. /app
# es un bind mount del host, asi que el fichero sobrevive al contenedor: aqui hay
# que conservar ese valor o cada reinicio apagaria la promo en silencio.
#
# TRACKER_DOWNLOAD_FACTOR queda solo como semilla del primer arranque, cuando el
# fichero todavia no existe.
download_factor="${TRACKER_DOWNLOAD_FACTOR:-100}"

if [ -f /app/.env ]; then
    persisted="$(sed -n 's/^DOWNLOAD_FACTOR=\([0-9]\{1,3\}\)$/\1/p' /app/.env | head -1)"

    if [ -n "$persisted" ]; then
        download_factor="$persisted"
    fi
fi

db_user_encoded="$(urlencode "$db_user")"
db_password_encoded="$(urlencode "$db_password")"
db_name_encoded="$(urlencode "$db_name")"

cat > /app/.env <<EOF
DATABASE_URL=mysql://${db_user_encoded}:${db_password_encoded}@${db_host}:${db_port}/${db_name_encoded}
RUST_LOG=${TRACKER_RUST_LOG:-unit3d_announce=info,axum::rejection=off}
FLUSH_INTERVAL_MILLISECONDS=${TRACKER_FLUSH_INTERVAL_MILLISECONDS:-3000}
MAX_BATCHES_PER_FLUSH=${TRACKER_MAX_BATCHES_PER_FLUSH:-1}
NUMWANT_DEFAULT=${TRACKER_NUMWANT_DEFAULT:-15}
NUMWANT_MAX=${TRACKER_NUMWANT_MAX:-15}
ANNOUNCE_MIN=${TRACKER_ANNOUNCE_MIN:-1800}
ANNOUNCE_MIN_ENFORCED=${TRACKER_ANNOUNCE_MIN_ENFORCED:-1740}
ANNOUNCE_MAX=${TRACKER_ANNOUNCE_MAX:-3600}
UPLOAD_FACTOR=${TRACKER_UPLOAD_FACTOR:-100}
DOWNLOAD_FACTOR=${download_factor}
PEER_EXPIRY_INTERVAL=${TRACKER_PEER_EXPIRY_INTERVAL:-1800}
ACTIVE_PEER_TTL=${TRACKER_ACTIVE_PEER_TTL:-7200}
INACTIVE_PEER_TTL=${TRACKER_INACTIVE_PEER_TTL:-1814400}
MAX_PEERS_PER_TORRENT_PER_USER=${TRACKER_MAX_PEERS_PER_TORRENT_PER_USER:-3}
APIKEY=${TRACKER_KEY}
LISTENING_IP_ADDRESS=${TRACKER_LISTENING_IP_ADDRESS:-0.0.0.0}
LISTENING_PORT=${TRACKER_LISTENING_PORT:-${TRACKER_PORT:-6969}}
IS_CONNECTIVITY_CHECK_ENABLED=${TRACKER_IS_CONNECTIVITY_CHECK_ENABLED:-false}
CONNECTIVITY_CHECK_INTERVAL=${TRACKER_CONNECTIVITY_CHECK_INTERVAL:-1800}
REQUIRE_PEER_CONNECTIVITY=${TRACKER_REQUIRE_PEER_CONNECTIVITY:-false}
IS_ANNOUNCE_LOGGING_ENABLED=${TRACKER_IS_ANNOUNCE_LOGGING_ENABLED:-false}
REVERSE_PROXY_CLIENT_IP_HEADER_NAME=${TRACKER_REVERSE_PROXY_CLIENT_IP_HEADER_NAME:-X-Real-IP}
USER_RECEIVE_SEED_LIST_RATE_LIMITS="${TRACKER_USER_RECEIVE_SEED_LIST_RATE_LIMITS:-60=10;900=25;7200=125;86400=500;604800=2500}"
USER_RECEIVE_LEECH_LIST_RATE_LIMITS="${TRACKER_USER_RECEIVE_LEECH_LIST_RATE_LIMITS:-60=20;900=50;7200=250;86400=1000;604800=5000}"
EOF

# www-data (uid 82) reescribe DOWNLOAD_FACTOR desde el panel de configuracion.
# El announce corre como root, asi que sigue leyendolo sin problema.
chown 82:82 /app/.env 2>/dev/null || true
chmod 640 /app/.env

exec /usr/local/bin/unit3d-announce
