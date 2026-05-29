# UNIT3D forensics & recovery toolbox — local, air-gapped recovery bench.
#
# Why Percona: prod is MySQL 8.0. Replaying its ROW binlogs needs a version-matched
# mysqlbinlog. The Oracle mysql images ship server-minimal (no mysqlbinlog), and
# MariaDB's mysqlbinlog cannot reliably parse MySQL 8.0 binlogs — the exact blocker
# during the 2026-05-10 incident. Percona Server is a drop-in MySQL 8.0 fork:
#   - percona-server-server  -> mysqlbinlog (8.0-compatible)   [Percona ships it here]
#   - percona-server-client  -> mysql, mysqldump
#   - percona-toolkit        -> pt-table-checksum / pt-table-sync (surgical merges)
#
# Build deliberately with bin/forensics/lab-build.sh. The tagged image persists across
# bench up/down cycles; push it to a registry to survive a full local image prune.
FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

# Don't let the percona-server-server install try to start a daemon during build.
RUN printf '#!/bin/sh\nexit 101\n' > /usr/sbin/policy-rc.d && chmod +x /usr/sbin/policy-rc.d

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends wget gnupg ca-certificates; \
    wget -qO /tmp/percona-release.deb https://repo.percona.com/apt/percona-release_latest.generic_all.deb; \
    apt-get install -y /tmp/percona-release.deb; \
    percona-release setup -y ps80; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        percona-server-server \
        percona-server-client \
        percona-toolkit \
        redis-tools \
        jq \
        ripgrep \
        sqlite3 \
        python3 \
        less \
        vim-tiny; \
    rm -rf /var/lib/apt/lists/* /tmp/percona-release.deb /var/lib/mysql; \
    command -v mysqlbinlog; command -v mysql; command -v pt-table-sync   # fail build if missing

WORKDIR /work
CMD ["sleep", "infinity"]
