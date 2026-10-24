#!/bin/sh
set -e
mkdir -p /tmp/arena /tmp/stkhome && echo 'sala1: 127.0.0.1:2759' > /tmp/arena/tokens && : > /tmp/arena/vetos
/opt/arena-venv/bin/python /relay/relay.py &
cd /tmp/stkhome
export HOME=/tmp/stkhome SUPERTUXKART_DATADIR=/stk
exec /stk/supertuxkart --lan-server="Arena NOBS (espiga)" --port=2759 --max-players=8 --network-ai=0 --difficulty=1 --mode=0 --laps=3 --server-config=/tmp/stkhome/servidor.xml
