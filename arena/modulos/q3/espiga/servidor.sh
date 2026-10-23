#!/bin/sh
# ioq3ded con OpenArena + relé /dg (el común de la Arena) en el mismo contenedor
set -e
pip install -q websockets==15.0.1 2>/dev/null
mkdir -p /tmp/arena && echo 'sala1: 127.0.0.1:27960' > /tmp/arena/tokens && : > /tmp/arena/vetos
python3 /relay/relay.py &
exec /q3/ioq3ded +set fs_basepath /oa +set fs_homepath /tmp/q3home +set com_basegame baseoa +set dedicated 1 \
  +set net_port 27960 +set net_ip 127.0.0.1 +set sv_pure 0 +set sv_hostname "Arena NOBS (espiga)" +set g_gametype 0 \
  +set bot_minplayers 3 +set fraglimit 20 +map oa_dm1
