#!/bin/bash
# Validate a torrent end-to-end, the way the 2026-05-10 "Infohash not found"
# fallout was diagnosed:
#   1. prod DB row exists (id, file_name, info_hash)
#   2. the stored .torrent file exists on disk
#   3. recomputed info_hash from the .torrent matches the DB info_hash
# On any mismatch / miss, prints the announce cache key to clear.
#
# Usage: torrent-validate.sh <torrent_id | 40-char-infohash-hex>
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

[ $# -eq 1 ] || die "usage: torrent-validate.sh <id|infohash-hex>"
ARG="$1"

if [[ "$ARG" =~ ^[0-9]+$ ]]; then
  WHERE="id=$ARG"
elif [[ "$ARG" =~ ^[0-9A-Fa-f]{40}$ ]]; then
  WHERE="info_hash=UNHEX('$ARG')"
else
  die "argument must be a numeric torrent id or a 40-char hex info_hash"
fi

ROW="$(prod_mysql "$DB_NAME" -N -e \
  "SELECT id, file_name, LOWER(HEX(info_hash)) FROM torrents WHERE $WHERE LIMIT 1;")"
[ -n "$ROW" ] || die "no torrent row matched ($WHERE)"

ID="$(echo "$ROW" | cut -f1)"
FILE_NAME="$(echo "$ROW" | cut -f2)"
DB_HASH="$(echo "$ROW" | cut -f3)"
echo "DB     : id=$ID  file_name=$FILE_NAME  info_hash=$DB_HASH"

FILE_PATH="/torrents/files/$FILE_NAME"
if ! docker exec "$FX_CONTAINER" test -f "$FILE_PATH" 2>/dev/null; then
  echo "FILE   : MISSING at storage/app/files/torrents/files/$FILE_NAME"
  echo "VERDICT: .torrent file absent — restore the file (and/or the DB row) before clearing cache."
  echo "CACHE  : announce-torrents:by-infohash:$DB_HASH"
  exit 2
fi

DISK_HASH="$(docker exec -i "$FX_CONTAINER" python3 - "$FILE_PATH" <<'PY'
import sys, hashlib
data = open(sys.argv[1], 'rb').read()
def parse(s, i):
    c = s[i:i+1]
    if c == b'i':
        j = s.index(b'e', i); return int(s[i+1:j]), j+1
    if c == b'l':
        i += 1
        while s[i:i+1] != b'e':
            _, i = parse(s, i)
        return None, i+1
    if c == b'd':
        i += 1; pos = {}
        while s[i:i+1] != b'e':
            k, i = parse(s, i); vstart = i; _, i = parse(s, i); pos[k] = (vstart, i)
        return pos, i+1
    if c.isdigit():
        j = s.index(b':', i); n = int(s[i:j]); start = j+1
        return s[start:start+n], start+n
    raise ValueError('bad bencode at %d' % i)
top, _ = parse(data, 0)
a, b = top[b'info']
print(hashlib.sha1(data[a:b]).hexdigest())
PY
)"
echo "DISK   : recomputed info_hash=$DISK_HASH"

if [ "$DISK_HASH" = "$DB_HASH" ]; then
  echo "VERDICT: MATCH — DB row and .torrent agree. If users still see 'Infohash not found',"
  echo "         it's stale announce cache, not a missing/bad torrent."
  echo "CACHE  : clear  announce-torrents:by-infohash:$DB_HASH"
  exit 0
else
  echo "VERDICT: MISMATCH — DB info_hash != .torrent info_hash. Investigate before clearing cache."
  echo "CACHE  : announce-torrents:by-infohash:$DB_HASH  (DB value)"
  exit 3
fi
