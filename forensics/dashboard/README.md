# Forensics Lab Dashboard (MVP)

A tiny local web front for the air-gapped MySQL forensics bench. It runs the
existing `bin/forensics/lab-*.sh` scripts the same way you would by hand — no
docker.sock, no container, no extra privilege. Stdlib Python only.

## What it does (MVP)
- **Status** — is the lab up? healthy? is a job running?
- **Wake / Sleep** — runs `lab-up.sh` / `lab-down.sh`, with the output streamed live.
- **Backups** — lists `backups/db_regular/*.sql.gz` snapshots and their
  `.binlogpos` / `.sha256` sidecars.

Restore, diff, export, torrent-validate and reset are intentionally **not** wired
yet (next passes). Export will always stay human-applied to prod.

## Run it
```bash
cp forensics/dashboard/dashboard.env.example forensics/dashboard/dashboard.env   # first time
bash deploy/install.sh        # installs + starts the --user systemd unit
```
Then open http://127.0.0.1:8780 on the box.

Manual run (no systemd):
```bash
cd ~/UNIT3D_Docker
set -a; . forensics/dashboard/dashboard.env; set +a
python3 forensics/dashboard/server.py
```

## Remote access
Local-only by default (`BIND_ADDR=127.0.0.1`). To reach it over the Tailscale
tailnet, set `BIND_ADDR` to the box's tailnet IP in `dashboard.env` and restart
the unit. Auth is deferred — keep it tailnet-only, never `0.0.0.0`.

## Layout
- `server.py` — stdlib `ThreadingHTTPServer`; routing, subprocess jobs, lockfile, status.
- `static/` — single-page UI (`index.html` + `app.js` + `style.css`), no build step.
- `run/` — gitignored: `<job_id>.log` (streamed), `lab.lock`, `jobs.json`.
- `dashboard.env` — gitignored local config.

## Concurrency
One mutating job at a time: a second wake/sleep while one is in flight returns
`409` with the running job id. A stale `run/lab.lock` from a crashed run is
cleared at startup if its pid is dead.
