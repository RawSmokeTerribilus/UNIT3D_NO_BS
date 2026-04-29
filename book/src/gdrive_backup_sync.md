# Google Drive Backup Sync

This page covers the rclone-based layer that uploads local UNIT3D snapshots to Google Drive with transparent encryption. It complements the built-in Laravel backup tool described in [Backups](backups.md), which creates the local snapshots that this sync layer uploads to the cloud.

## 1. Purpose and architecture

Local snapshots produced by `php artisan backup:run` accumulate in `backups/`. The rclone sync layer picks up that directory and mirrors it to a Google Drive `crypt` remote (`gdrive_crypt:`), encrypting file names and contents transparently so raw Google Drive access never exposes backup data.

The sync runs as an **ephemeral Docker container** defined in `rclone_gdrive/docker-compose.yml`. The container starts, performs the sync, and is destroyed (`--rm`). No long-running rclone process is kept alive.

```
backups/  (local snapshots, read-only mount)
    └──► rclone_sync container (ephemeral)
              └──► gdrive_crypt: remote  (Google Drive, encrypted)
```

## 2. Prerequisites

- **Docker** and **Docker Compose** available on the host (the sync runs inside a container — no host-level rclone installation required).
- A **Google Drive OAuth app** configured in rclone (`rclone config`), producing a remote named `gdrive_crypt` of type `crypt` backed by a plain `gdrive` remote.
- A completed `rclone_gdrive/config/rclone.conf` file containing the `gdrive` and `gdrive_crypt` remote definitions.

> [!IMPORTANT]
> `rclone_gdrive/config/rclone.conf` is git-ignored. You must generate it manually on each host using `rclone config` and place it at that path before running any sync or restore command.

## 3. Configuration reference

File: `rclone_gdrive/docker-compose.yml`

| Option | Value | Effect |
|--------|-------|--------|
| Image | `rclone/rclone:latest` | Official rclone image |
| Source mount | `/home/rawserver/UNIT3D_Docker/backups` → `/data` (read-only) | Local snapshots, never modified |
| Config mount | `./config` → `/config/rclone` | Provides `rclone.conf` to the container |
| Log mount | `./logs` → `/logs` | Persists sync logs on the host |
| `--drive-chunk-size` | `1024M` | Large chunks avoid Google Drive upload timeouts on big archives |
| `--transfers` | `4` | Parallel file transfers |
| `--checkers` | `8` | Parallel file-existence checks |
| `--delete-after` | *(flag)* | Deletes destination files only after all transfers complete successfully |
| `-v` | *(flag)* | Verbose logging written to `--log-file` |
| Log file | `/logs/sync_execution.log` | Mapped to `rclone_gdrive/logs/sync_execution.log` on the host |

The `sync` subcommand is used, meaning the destination mirrors the source exactly (files removed locally will eventually be removed from the cloud after `--delete-after` processing).

## 4. Running a sync

Use the wrapper script to run a sync:

```sh
cd /home/rawserver/UNIT3D_Docker/rclone_gdrive
bash scripts/run_sync.sh
```

The script:
1. Changes into the project directory (`rclone_gdrive/`).
2. Appends a timestamped start entry to `logs/cron_wrapper.log`.
3. Runs `docker compose run --rm rclone_sync` (ephemeral — container is destroyed on exit).
4. Appends a success or error entry to `logs/cron_wrapper.log` based on the exit code.
5. Exits with the same exit code as the rclone process.

> [!IMPORTANT]
> The script must be run from a user that has permission to call `docker compose`. Ensure the user is in the `docker` group or use `sudo`.

## 5. Cron setup

Add an entry to the crontab of the user that has Docker access. For example, to sync daily at 07:00:

```sh
crontab -e
```

```
0 7 * * * /home/rawserver/UNIT3D_Docker/rclone_gdrive/scripts/run_sync.sh
```

The wrapper script writes its own timestamped log to `rclone_gdrive/logs/cron_wrapper.log`, so cron output redirection is optional. Detailed per-file rclone output goes to `rclone_gdrive/logs/sync_execution.log`.

## 6. Restore procedure

Use the restore script to download and decrypt a specific backup from Google Drive:

```sh
cd /home/rawserver/UNIT3D_Docker/rclone_gdrive
bash scripts/restore_snapshot.sh
```

The script runs interactively:

1. **Lists** all top-level directories in `gdrive_crypt:/` so you can see what snapshots are available.
2. **Prompts** for the exact folder name to restore (example: `snapshot_2026-03-19_0600`).
3. **Creates** the local destination directory at `/home/rawserver/UNIT3D_Docker/restauracion_emergencia/<TARGET>`.
4. **Downloads and decrypts** the snapshot using `rclone copy gdrive_crypt:/<TARGET>` with `--drive-chunk-size 1024M`.
5. **Reports** completion and lists the restored files with sizes.

> [!IMPORTANT]
> The restore destination is `/home/rawserver/UNIT3D_Docker/restauracion_emergencia/`. Files are decrypted transparently by rclone using the `gdrive_crypt` remote definition in `rclone.conf`. After restore, follow the procedures in [Backups — Restoring a backup](backups.md#4-restoring-a-backup) to apply the snapshot to the running application.

## 7. Logs

| File | Contents |
|------|----------|
| `rclone_gdrive/logs/cron_wrapper.log` | Timestamped start/success/error lines written by `run_sync.sh` |
| `rclone_gdrive/logs/sync_execution.log` | Verbose per-file rclone output written by the container (`-v --log-file`) |

To follow a running sync in real time:

```sh
tail -f /home/rawserver/UNIT3D_Docker/rclone_gdrive/logs/sync_execution.log
```

To review the cron history:

```sh
cat /home/rawserver/UNIT3D_Docker/rclone_gdrive/logs/cron_wrapper.log
```
