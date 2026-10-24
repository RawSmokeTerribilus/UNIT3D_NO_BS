#!/bin/sh
set -e
apt-get update -qq >/dev/null && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq python3-venv >/dev/null 2>&1
python3 -m venv /tmp/v && /tmp/v/bin/pip install -q websockets==15.0.1
mkdir -p /tmp/arena && echo 'sala1: 127.0.0.1:27015' > /tmp/arena/tokens && : > /tmp/arena/vetos
/tmp/v/bin/python /relay/relay.py &
cd /srv && exec ./xash -game cstrike +ip 127.0.0.1 +maxplayers 12 +sv_lan 1 +map de_dust2
