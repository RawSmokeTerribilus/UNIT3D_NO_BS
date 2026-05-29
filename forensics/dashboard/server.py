#!/usr/bin/env python3
"""UNIT3D forensics lab dashboard — MVP.

A tiny stdlib HTTP front for the air-gapped MySQL forensics bench. It shells out
to the existing bin/forensics/lab-*.sh scripts (same way the operator runs them
by hand) — no docker.sock, no new privilege, no container. Local-only by default.

MVP surface: status, list-backups, wake, sleep, plus a job + log-stream model so
the (minutes-long) wake can be followed live. Restore/diff/export land later.
"""
import json
import os
import re
import shutil
import subprocess
import threading
import time
import urllib.parse
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

# --- paths (mirror bin/forensics/_common.sh resolution) ----------------------
DASHBOARD_DIR = os.path.dirname(os.path.abspath(__file__))          # forensics/dashboard
PROJECT_ROOT = os.path.abspath(os.path.join(DASHBOARD_DIR, "..", ".."))  # UNIT3D_Docker
STATIC_DIR = os.path.join(DASHBOARD_DIR, "static")
RUN_DIR = os.path.join(DASHBOARD_DIR, "run")
BIN_FORENSICS = os.path.join(PROJECT_ROOT, "bin", "forensics")
LOCK_FILE = os.path.join(RUN_DIR, "lab.lock")
JOBS_FILE = os.path.join(RUN_DIR, "jobs.json")

# --- config (env, with the same defaults as forensics.env.example) -----------
BIND_ADDR = os.environ.get("BIND_ADDR", "127.0.0.1")
PORT = int(os.environ.get("PORT", "8780"))
LAB_DB_CONTAINER = os.environ.get("LAB_DB_CONTAINER", "unit3d-lab-db")
FX_CONTAINER = os.environ.get("FX_CONTAINER", "unit3d-forensics")
BACKUPS_DIR = os.path.join(PROJECT_ROOT, "backups", "db_regular")
EXPORT_DIR = os.path.join(PROJECT_ROOT, "backups", "forensics-export")

# actions the dashboard is allowed to run -> script filename (in bin/forensics/)
SCRIPTS = {
    "wake": "lab-up.sh",
    "sleep": "lab-down.sh",
    "restore": "lab-restore.sh",
    "diff": "lab-diff.sh",
    "export": "lab-export.sh",
    "torrent-validate": "torrent-validate.sh",
    "reset": "lab-reset.sh",
    "timeline": "binlog-timeline.sh",
    "prod-apply": "prod-apply.sh",
    "build-apply-kit": "build-apply-kit.sh",
    "prod-restore": "prod-restore.sh",
    "exact-count": "exact-count-all.sh",
    "maint": "prod-maint.sh",
    "verify-backups": "verify-backups.sh",
}
MAINT_OPS = ("check", "analyze", "optimize")
# snapshot uses the shared backup script in bin/ (not bin/forensics/)
SNAPSHOT_SCRIPT = os.path.join(PROJECT_ROOT, "bin", "db-backup-regular.sh")
TIMELINE_JSON = os.path.join(RUN_DIR, "timeline.json")
EXACT_COUNTS_JSON = os.path.join(RUN_DIR, "exact-counts.json")
DIFF_DEFAULT_TABLES = ["users", "topics", "posts", "comments", "torrents"]

# --- strict input validation (args are interpolated into SQL/shell by scripts) -
RE_TABLE = re.compile(r"^[A-Za-z0-9_]{1,64}$")
RE_UNTIL = re.compile(r"^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$")
RE_DUMP = re.compile(r"^db_unit3d_[0-9_]+\.sql\.gz$")
RE_TORRENT = re.compile(r"^(\d+|[0-9A-Fa-f]{40})$")


class BadInput(ValueError):
    pass


def build_argv(action, params):
    """Map an action + request params to a validated script argv. Raises BadInput."""
    if action == "snapshot":
        return [SNAPSHOT_SCRIPT]
    script = os.path.join(BIN_FORENSICS, SCRIPTS[action])
    if action in ("wake", "sleep", "verify-backups"):
        return [script]
    if action == "restore":
        argv = [script]
        dump = (params.get("dump") or "latest").strip()
        if dump == "latest":
            argv += ["--dump", "latest"]
        elif RE_DUMP.match(dump):
            path = os.path.join(BACKUPS_DIR, dump)
            if not os.path.isfile(path):
                raise BadInput("dump not found: %s" % dump)
            argv += ["--dump", path]
        else:
            raise BadInput("bad dump name")
        until = (params.get("until") or "HEAD").strip()
        if until == "HEAD":
            argv += ["--until", "HEAD"]
        elif RE_UNTIL.match(until):
            argv += ["--until", until]
        else:
            raise BadInput("bad --until (need 'YYYY-MM-DD HH:MM:SS' or HEAD)")
        if params.get("no_replay"):
            argv += ["--no-replay"]
        return argv
    if action == "diff":
        tables = params.get("tables") or []
        if not isinstance(tables, list) or not tables:
            raise BadInput("no tables given")
        for t in tables:
            if not RE_TABLE.match(str(t)):
                raise BadInput("bad table name: %r" % t)
        return [script] + [str(t) for t in tables]
    if action == "export":
        t = str(params.get("table") or "")
        if not RE_TABLE.match(t):
            raise BadInput("bad table name")
        return [script, t]
    if action == "torrent-validate":
        target = str(params.get("target") or "").strip()
        if not RE_TORRENT.match(target):
            raise BadInput("target must be a numeric id or 40-hex infohash")
        return [script, target]
    if action == "reset":
        if (params.get("confirm") or "") != "RESET":
            raise BadInput("type RESET to confirm the destructive lab wipe")
        return [script, "--yes"]
    if action == "timeline":
        argv = [script, TIMELINE_JSON]
        if params.get("all"):
            argv.append("--all")
        return argv
    if action == "exact-count":
        return [script, EXACT_COUNTS_JSON]
    if action == "maint":
        op = params.get("op")
        if op not in MAINT_OPS:
            raise BadInput("op must be one of %s" % (MAINT_OPS,))
        tables = params.get("tables") or []
        if not isinstance(tables, list) or not tables:
            raise BadInput("no tables given")
        for t in tables:
            if not RE_TABLE.match(str(t)):
                raise BadInput("bad table name: %r" % t)
        return [script, op] + [str(t) for t in tables]
    if action in ("prod-apply", "build-apply-kit"):
        # one or more bare .sql filenames inside the export dir
        files = params.get("files") or ([params["file"]] if params.get("file") else [])
        if not files:
            raise BadInput("no export file given")
        for f in files:
            if "/" in f or not f.endswith(".sql") or not os.path.isfile(os.path.join(EXPORT_DIR, f)):
                raise BadInput("bad or missing export file: %r" % f)
        if action == "build-apply-kit":
            return [script] + files
        argv = [script, files[0]]
        if params.get("commit"):
            argv.append("--commit")
        return argv
    if action == "prod-restore":
        argv = [script]
        dump = (params.get("dump") or "latest").strip()
        if dump == "latest":
            argv += ["--dump", "latest"]
        elif RE_DUMP.match(dump) and os.path.isfile(os.path.join(BACKUPS_DIR, dump)):
            argv += ["--dump", dump]
        else:
            raise BadInput("bad dump name")
        until = (params.get("until") or "HEAD").strip()
        if until != "HEAD" and not RE_UNTIL.match(until):
            raise BadInput("bad --until")
        argv += ["--until", until]
        return argv
    raise BadInput("unknown action")


def build_env(action, params):
    """Sensitive env for prod-write actions. Never logged, never persisted, never
    placed on a command line. Returns a dict to merge into the subprocess env."""
    env = {}
    if action in ("prod-apply", "prod-restore"):
        pw = params.get("password")
        if not pw:
            raise BadInput("prod DB password required")
        env["PROD_WRITE_PW"] = pw
    if action == "prod-restore":
        if (params.get("confirm") or "") != "RESTORE PROD":
            raise BadInput("type 'RESTORE PROD' to confirm the destructive restore")
        env["PROD_RESTORE_CONFIRM"] = "RESTORE PROD"
    if action == "maint" and params.get("op") in ("analyze", "optimize"):
        pw = params.get("password")
        if not pw:
            raise BadInput("prod DB password required for %s" % params.get("op"))
        env["PROD_WRITE_PW"] = pw
    return env

# --- in-process state --------------------------------------------------------
JOB_LOCK = threading.Lock()      # admits at most one mutating job at a time
STATE_LOCK = threading.Lock()    # guards JOBS + CURRENT_JOB
JOBS = {}                        # job_id -> dict
CURRENT_JOB = None               # job_id of the running mutating job, or None


def now_iso():
    return datetime.now(timezone.utc).astimezone().isoformat(timespec="seconds")


def docker(args, timeout=10):
    """Run a docker query, return (rc, stdout). Never raises on non-zero."""
    try:
        p = subprocess.run(["docker"] + args, capture_output=True, text=True,
                            cwd=PROJECT_ROOT, timeout=timeout)
        return p.returncode, p.stdout.strip()
    except (subprocess.TimeoutExpired, FileNotFoundError) as e:
        return 1, str(e)


def container_running(name):
    rc, out = docker(["ps", "--format", "{{.Names}}"])
    return rc == 0 and name in out.splitlines()


def _parse_uptime(started_at):
    """docker StartedAt (RFC3339, nanos) -> seconds running, or None."""
    if not started_at or started_at.startswith("0001"):
        return None
    s = started_at.strip()
    # trim sub-second precision docker emits beyond what fromisoformat handles
    m = re.match(r"(.*\.\d{6})\d*([+-]\d{2}:?\d{2}|Z)?$", s)
    if m:
        s = m.group(1) + (m.group(2) or "")
    s = s.replace("Z", "+00:00")
    try:
        dt = datetime.fromisoformat(s)
        return max(0, int((datetime.now(timezone.utc) - dt.astimezone(timezone.utc)).total_seconds()))
    except ValueError:
        return None


def container_info(name):
    """Running/health/uptime for one container in a single docker inspect."""
    rc, out = docker(["inspect", "-f",
                      "{{.State.Running}}|{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}|{{.State.StartedAt}}",
                      name])
    if rc != 0 or "|" not in out:
        return {"name": name, "running": False, "health": "absent", "uptime": None}
    running, health, started = (out.split("|", 2) + ["", "", ""])[:3]
    return {
        "name": name,
        "running": running == "true",
        "health": health if health != "none" else "n/a",
        "uptime": _parse_uptime(started) if running == "true" else None,
    }


def docker_stats(names):
    """One-shot CPU%/mem for the named containers. Slow-ish (~1-2s), so it lives
    on its own endpoint, not the fast status poll."""
    rc, out = docker(["stats", "--no-stream", "--format",
                      "{{.Name}}|{{.CPUPerc}}|{{.MemUsage}}|{{.MemPerc}}"] + names,
                     timeout=8)
    stats = {}
    if rc == 0:
        for line in out.splitlines():
            parts = line.split("|")
            if len(parts) == 4:
                stats[parts[0]] = {"cpu": parts[1], "mem": parts[2], "mem_pct": parts[3]}
    return stats


def persist_jobs():
    try:
        with open(JOBS_FILE, "w") as f:
            json.dump({"jobs": JOBS, "current": CURRENT_JOB}, f)
    except OSError:
        pass


def clear_stale_lock():
    """On startup: if a lab.lock from a previous run names a dead pid, drop it."""
    if not os.path.exists(LOCK_FILE):
        return
    try:
        with open(LOCK_FILE) as f:
            data = json.load(f)
        pid = int(data.get("pid", -1))
        os.kill(pid, 0)  # raises if no such process
    except (OSError, ValueError, json.JSONDecodeError):
        try:
            os.remove(LOCK_FILE)
        except OSError:
            pass


def run_job(action, argv, job_id, env=None):
    """Worker thread: run the script argv, tee combined output to run/<job_id>.log.
    `env` (e.g. a prod password) is merged into the child env only — never logged."""
    global CURRENT_JOB
    log_path = os.path.join(RUN_DIR, job_id + ".log")
    with open(LOCK_FILE, "w") as f:
        json.dump({"pid": os.getpid(), "job_id": job_id, "action": action}, f)
    child_env = dict(os.environ)
    if env:
        child_env.update(env)
    try:
        with open(log_path, "w") as logf:
            logf.write("[dashboard] %s running: %s\n" % (now_iso(), " ".join(argv)))
            logf.flush()
            proc = subprocess.Popen(["bash"] + argv, cwd=PROJECT_ROOT,
                                    stdout=logf, stderr=subprocess.STDOUT, env=child_env)
            rc = proc.wait()
            logf.write("\n[dashboard] exit code: %d\n" % rc)
        with STATE_LOCK:
            JOBS[job_id].update(state="done", exit_code=rc, ended=now_iso())
            persist_jobs()
    finally:
        with STATE_LOCK:
            CURRENT_JOB = None
            persist_jobs()
        try:
            os.remove(LOCK_FILE)
        except OSError:
            pass
        JOB_LOCK.release()


def start_job(action, argv, env=None):
    """Try to start a mutating job. Returns (job_id, None) or (None, busy_id)."""
    global CURRENT_JOB
    if not JOB_LOCK.acquire(blocking=False):
        return None, CURRENT_JOB
    job_id = "%s-%s" % (action, datetime.now().strftime("%Y%m%d-%H%M%S"))
    with STATE_LOCK:
        CURRENT_JOB = job_id
        JOBS[job_id] = {"id": job_id, "action": action, "state": "running",
                        "exit_code": None, "started": now_iso(), "ended": None}
        persist_jobs()
    threading.Thread(target=run_job, args=(action, argv, job_id, env), daemon=True).start()
    return job_id, None


def run_bin(script, args=(), timeout=30):
    """Run a bin/forensics script, return (rc, stdout). For read-only helpers."""
    try:
        p = subprocess.run(["bash", os.path.join(BIN_FORENSICS, script)] + list(args),
                           cwd=PROJECT_ROOT, capture_output=True, text=True, timeout=timeout)
        return p.returncode, p.stdout
    except (subprocess.TimeoutExpired, FileNotFoundError) as e:
        return 1, str(e)


_STORAGE_CACHE = {"ts": 0.0, "data": None}


def get_storage_health():
    """Cached (15s) read-only binlog/redis/meili/disk footprint."""
    if time.time() - _STORAGE_CACHE["ts"] < 15 and _STORAGE_CACHE["data"] is not None:
        return _STORAGE_CACHE["data"]
    rc, out = run_bin("storage-health.sh", timeout=30)
    try:
        data = json.loads(out.strip().splitlines()[-1])
    except (ValueError, IndexError):
        data = {"error": "storage-health failed", "rc": rc,
                "binlogs": {}, "redis_bytes": None, "meili_bytes": None, "disk": None}
    _STORAGE_CACHE.update(ts=time.time(), data=data)
    return data


_TOPO_CACHE = {"ts": 0.0, "data": None}


def get_topology():
    """Cached (2s) prod topology + lab overlay. Cheap info_schema, read-only."""
    if time.time() - _TOPO_CACHE["ts"] < 2 and _TOPO_CACHE["data"] is not None:
        return _TOPO_CACHE["data"]
    rc, out = run_bin("topology.sh")
    try:
        data = json.loads(out.strip().splitlines()[-1])
    except (ValueError, IndexError):
        data = {"error": "topology query failed", "rc": rc, "prod": [], "ids": [],
                "lab": None, "lab_up": False}
    _TOPO_CACHE.update(ts=time.time(), data=data)
    return data


def list_exports():
    out = []
    try:
        names = sorted((n for n in os.listdir(EXPORT_DIR) if n.endswith(".sql")), reverse=True)
    except OSError:
        return out
    for n in names:
        p = os.path.join(EXPORT_DIR, n)
        try:
            st = os.stat(p)
        except OSError:
            continue
        out.append({"name": n, "size": st.st_size,
                    "mtime": datetime.fromtimestamp(st.st_mtime).isoformat(timespec="seconds")})
    return out


def list_backups():
    out = []
    try:
        names = sorted((n for n in os.listdir(BACKUPS_DIR) if n.endswith(".sql.gz")),
                       reverse=True)
    except OSError:
        return out
    for n in names:
        p = os.path.join(BACKUPS_DIR, n)
        try:
            st = os.stat(p)
        except OSError:
            continue
        out.append({
            "name": n,
            "size": st.st_size,
            "mtime": datetime.fromtimestamp(st.st_mtime).isoformat(timespec="seconds"),
            "binlogpos": os.path.exists(p + ".binlogpos"),
            "sha256": os.path.exists(p + ".sha256"),
        })
    return out


CTYPE = {".html": "text/html; charset=utf-8", ".js": "application/javascript",
         ".css": "text/css", ".json": "application/json"}
JOB_RE = re.compile(r"^/api/jobs/([A-Za-z0-9_\-]+)(/stream)?$")


class Handler(BaseHTTPRequestHandler):
    server_version = "ForensicsDash/0.1"

    def log_message(self, fmt, *args):  # quieter logs
        pass

    def _json(self, obj, code=200):
        body = json.dumps(obj).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _static(self, rel):
        path = os.path.normpath(os.path.join(STATIC_DIR, rel))
        if not path.startswith(STATIC_DIR) or not os.path.isfile(path):
            return self._json({"error": "not found"}, 404)
        with open(path, "rb") as f:
            body = f.read()
        self.send_response(200)
        self.send_header("Content-Type", CTYPE.get(os.path.splitext(path)[1], "application/octet-stream"))
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        path = self.path.split("?", 1)[0]
        if path == "/" or path == "/index.html":
            return self._static("index.html")
        if path in ("/app.js", "/style.css"):
            return self._static(path.lstrip("/"))
        if path == "/api/status":
            db = container_info(LAB_DB_CONTAINER)
            fx = container_info(FX_CONTAINER)
            with STATE_LOCK:
                cur = CURRENT_JOB
                last = JOBS.get(cur) if cur else (
                    max(JOBS.values(), key=lambda j: j["started"]) if JOBS else None)
            return self._json({
                "lab_up": db["running"],
                "health": db["health"] if db["running"] else "down",
                "toolbox_up": fx["running"],
                "containers": [db, fx],
                "current_job": cur,
                "last_job": last,
                "time": now_iso(),
            })
        if path == "/api/stats":
            names = [c for c in (LAB_DB_CONTAINER, FX_CONTAINER) if container_running(c)]
            return self._json({"stats": docker_stats(names) if names else {}})
        if path == "/api/backups":
            backups = list_backups()
            newest_age = None
            if backups:
                newest_age = int(time.time() - max(
                    os.path.getmtime(os.path.join(BACKUPS_DIR, b["name"])) for b in backups))
            return self._json({
                "backups": backups,
                "summary": {
                    "count": len(backups),
                    "total_size": sum(b["size"] for b in backups),
                    "newest_age_secs": newest_age,
                    # cron runs every 4h; allow 30m slack before "stale"
                    "cron_interval_secs": 4 * 3600,
                    "stale_after_secs": 4 * 3600 + 1800,
                },
            })
        if path == "/api/tables":
            return self._json({"default_tables": DIFF_DEFAULT_TABLES})
        if path == "/api/storage-health":
            return self._json(get_storage_health())
        if path == "/api/topology":
            return self._json(get_topology())
        if path == "/api/timeline":
            try:
                with open(TIMELINE_JSON) as f:
                    data = json.load(f)
                data["mtime"] = datetime.fromtimestamp(
                    os.path.getmtime(TIMELINE_JSON)).isoformat(timespec="seconds")
                return self._json(data)
            except (OSError, ValueError):
                return self._json({"events": [], "count": 0, "mtime": None})
        if path == "/api/exact-counts":
            try:
                with open(EXACT_COUNTS_JSON) as f:
                    data = json.load(f)
                return self._json({"counts": data,
                                   "mtime": datetime.fromtimestamp(
                                       os.path.getmtime(EXACT_COUNTS_JSON)).isoformat(timespec="seconds")})
            except (OSError, ValueError):
                return self._json({"counts": {}, "mtime": None})
        if path == "/api/table-count":
            qs = urllib.parse.urlparse(self.path).query
            table = urllib.parse.parse_qs(qs).get("table", [""])[0]
            if not RE_TABLE.match(table):
                return self._json({"error": "bad table name"}, 400)
            rc, out = run_bin("table-count.sh", [table])
            try:
                return self._json(json.loads(out.strip().splitlines()[-1]))
            except (ValueError, IndexError):
                return self._json({"error": "count failed", "rc": rc}, 502)
        if path == "/api/exports":
            return self._json({"exports": list_exports()})
        if path == "/api/exports/view":
            qs = urllib.parse.urlparse(self.path).query
            name = urllib.parse.parse_qs(qs).get("name", [""])[0]
            if not name or "/" in name or not name.endswith(".sql"):
                return self._json({"error": "bad name"}, 400)
            fpath = os.path.normpath(os.path.join(EXPORT_DIR, name))
            if not fpath.startswith(EXPORT_DIR + os.sep) or not os.path.isfile(fpath):
                return self._json({"error": "not found"}, 404)
            with open(fpath, "rb") as f:
                body = f.read(2 * 1024 * 1024)  # cap view at 2 MiB
            self.send_response(200)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return
        m = JOB_RE.match(path)
        if m:
            job_id, is_stream = m.group(1), m.group(2)
            with STATE_LOCK:
                job = JOBS.get(job_id)
            if not job:
                return self._json({"error": "no such job"}, 404)
            if is_stream:
                return self._stream_log(job_id)
            return self._json(job)
        return self._json({"error": "not found"}, 404)

    def _stream_log(self, job_id):
        log_path = os.path.join(RUN_DIR, job_id + ".log")
        self.send_response(200)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.send_header("Cache-Control", "no-cache")
        self.end_headers()  # HTTP/1.0: no Content-Length, client reads until close
        deadline = time.time() + 600
        try:
            with open(log_path, "r") as f:
                while time.time() < deadline:
                    chunk = f.read()
                    if chunk:
                        self.wfile.write(chunk.encode())
                        self.wfile.flush()
                    with STATE_LOCK:
                        done = JOBS.get(job_id, {}).get("state") == "done"
                    if done:
                        rest = f.read()
                        if rest:
                            self.wfile.write(rest.encode())
                        break
                    time.sleep(0.5)
        except (BrokenPipeError, ConnectionResetError, FileNotFoundError):
            pass

    def _read_json_body(self):
        try:
            n = int(self.headers.get("Content-Length", 0))
        except ValueError:
            return {}
        if n <= 0:
            return {}
        raw = self.rfile.read(min(n, 64 * 1024))
        try:
            obj = json.loads(raw or b"{}")
            return obj if isinstance(obj, dict) else {}
        except json.JSONDecodeError:
            return {}

    def do_POST(self):
        path = self.path.split("?", 1)[0]
        if not path.startswith("/api/"):
            return self._json({"error": "not found"}, 404)
        action = path[len("/api/"):]
        if action not in SCRIPTS and action != "snapshot":
            return self._json({"error": "unknown action"}, 404)
        params = self._read_json_body()
        try:
            argv = build_argv(action, params)
            env = build_env(action, params)
        except BadInput as e:
            return self._json({"error": str(e)}, 400)
        job_id, busy = start_job(action, argv, env)
        if busy is not None:
            return self._json({"error": "lab busy", "running_job": busy}, 409)
        return self._json({"job_id": job_id, "action": action}, 202)


def main():
    os.makedirs(RUN_DIR, exist_ok=True)
    clear_stale_lock()
    if not shutil.which("docker"):
        raise SystemExit("docker not on PATH — dashboard needs the docker CLI")
    srv = ThreadingHTTPServer((BIND_ADDR, PORT), Handler)
    print("forensics dashboard on http://%s:%d (project=%s)" % (BIND_ADDR, PORT, PROJECT_ROOT))
    try:
        srv.serve_forever()
    except KeyboardInterrupt:
        srv.shutdown()


if __name__ == "__main__":
    main()
