# UNIT3D Forensics & Recovery Bench

A permanent, off-by-default recovery lab that sits beside a Dockerized UNIT3D
production stack. It exists so the next "the database looks empty" moment is a
repeatable 5-minute workflow instead of an improvised 3 a.m. scramble.

It was built after a real incident where production was found effectively empty
mid open-signups, and recovery had to be improvised with a throwaway lab. This
bench makes that recovery boring, isolated, and fast.

## Quick start (cold)

From the UNIT3D project root, on a vanilla layout (no config needed):

```sh
bin/forensics/lab-up.sh                                  # wake the bench (first run pulls ~764MB)
bin/forensics/lab-restore.sh                             # latest dump + replay binlogs to HEAD
bin/forensics/lab-diff.sh users topics posts comments torrents   # what is prod missing? (lab_only)
bin/forensics/lab-down.sh                                # sleep it (frees RAM)
```

Notes for a first run:
- First `lab-up` pulls the toolbox image (~764MB) or builds it — not instant.
- Reading prod binlogs/datadir uses the same sudo/docker access as the rest of the stack.
- Scripts find the project root from their own location — run from any directory.
- If your paths differ from the defaults: `cp forensics/forensics.env.example forensics/forensics.env` and edit.

## What it does

- **Hybrid point-in-time recovery (PITR):** restore the most recent SQL dump into
  an isolated lab MySQL, then replay binlogs forward to fill the gap between the
  dump and any target moment — without touching production.
- **Authoritative diffing:** compare any recovered table in the lab against current
  production, by primary key, to find exactly which rows production is missing.
- **Surgical export:** emit `INSERT IGNORE` for just the missing rows, for you to
  review and apply to production deliberately. The bench never writes to prod.
- **Torrent validation:** check that a torrent's DB row, its stored `.torrent` file,
  and the recomputed info_hash all agree — the standard "Infohash not found" triage.

## Safety model

The bench is designed to be impossible to confuse with production:

- **Off by default.** Both services are gated behind a Compose `forensics` profile
  and live in a separate Compose file with a separate project name. A normal
  `docker compose up` for prod never starts them — zero RAM/CPU when idle.
- **Air-gapped.** The lab network is `internal: true`: no internet egress and no
  route to the production DB. Nothing can leak out or accidentally reach prod.
- **Read-only on prod.** Every production path (binlogs, backups, `.torrent` files,
  redis, meilisearch) is mounted `:ro`. The bench cannot modify production data.
- **Separate datadir.** The lab MySQL uses its own datadir, never the prod datadir.
- **No prod privilege escalation.** The PITR binlog coordinate is captured from the
  filesystem (newest binlog file + its byte size = the write position), so it needs
  no special MySQL grant. The lab replay uses `--force`; `lab-diff` is the
  authoritative check for what prod is actually missing.

## Requirements

- A Dockerized UNIT3D stack with MySQL 8.0 and binary logging enabled (the default).
- A regular DB dump job that writes `*.sql.gz` plus a `*.sql.gz.binlogpos` sidecar
  (see `bin/db-backup-regular.sh`, which captures the coordinate with no DB privilege).
- A read-only MySQL account for diffing (defaults to `backupbot`).
- Docker + Docker Compose v2.

## Configuration

All configuration is environment-driven with sane defaults for a standard
`UNIT3D_Docker` layout, and every path is relative to the project root.

```sh
cp forensics/forensics.env.example forensics/forensics.env
# edit forensics/forensics.env if your layout differs (image, names, paths, lab password)
```

`forensics/forensics.env` is gitignored (it may hold the local lab password). The
`.example` documents every knob.

## Usage

All scripts live in `bin/forensics/` and run from the host.

```sh
bin/forensics/lab-up.sh            # start the bench (pulls/builds the toolbox image as needed)

bin/forensics/lab-restore.sh                         # latest dump + replay to HEAD
bin/forensics/lab-restore.sh --until "2026-05-09 23:59:00"   # PITR to a moment
bin/forensics/lab-restore.sh --dump <path> --no-replay       # snapshot only

bin/forensics/lab-diff.sh users topics posts comments torrents   # lab vs prod, by id
bin/forensics/lab-export.sh comments                 # INSERT IGNORE for rows prod is missing

bin/forensics/torrent-validate.sh 3754               # DB row <-> .torrent <-> info_hash
bin/forensics/torrent-validate.sh <40-char-infohash-hex>

bin/forensics/lab-down.sh          # stop the bench (keeps the lab datadir)
bin/forensics/lab-reset.sh --yes   # stop and wipe the lab datadir for a clean run
```

### The recovery workflow

1. `lab-up.sh` — bring the bench online.
2. `lab-restore.sh [--until <when>]` — restore + replay into the lab.
3. `lab-diff.sh <tables>` — see which rows prod is missing (`lab_only`).
4. `lab-export.sh <table>` — emit `INSERT IGNORE` for those rows.
5. Review the export, then apply it to production yourself as a deliberate step.
6. `lab-down.sh` — tear down.

## Toolbox image

The toolbox is built from Percona Server (a drop-in MySQL 8.0 fork) so that
`mysqlbinlog` is version-matched to prod binlogs — the Oracle minimal images ship
no `mysqlbinlog`, and MariaDB's cannot reliably parse MySQL 8.0 ROW binlogs. It also
carries `percona-toolkit`, `python3`, `jq`, `ripgrep`, `sqlite3`, and `redis-tools`.

Build / publish:

```sh
bin/forensics/lab-build.sh         # build the tagged image locally (persists across up/down)
bin/forensics/lab-publish.sh       # build + push version tag and :latest to the registry
```

Pin `FORENSICS_IMAGE` in `forensics.env` to a version tag (not `:latest`) so a later
`:latest` push can't silently change a running bench.

## Known limitations

- `lab-diff` / `lab-export` key on an `id` primary column. UNIT3D tables that use a
  composite key (e.g. `peers`, `history`) are skipped; the human-content tables
  (`users`, `topics`, `posts`, `comments`, `torrents`) all have `id`.
- Binlog replay recovers only as far back as the oldest retained binlog
  (`binlog_expire_logs_seconds`, 30 days by default).

## Roadmap

- A local recovery dashboard with guarded auto-recover (restore → replay → diff →
  propose merge; merge-to-prod stays human-confirmed).
- Optional `lab-redis` / `lab-meili` services for whole-stack replay.
