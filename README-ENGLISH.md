# 🎬 UNIT3D BÚNKER - N.O.B.S Edition

> **A Private Torrent Tracker, Dockerized & Battle-Hardened**

```
███████████████████████████████████████████████████████████████
█                                                             █
█   🛡️  UNIT3D BÚNKER  |  Nuclear Order Bit Syndicate         █
█                                                             █
█   "From the Scene, For the Scene"                           █
█   2000+ hours of stabilization, automation, and resilience  █
█                                                             █
███████████████████████████████████████████████████████████████
```


> **⚠️ AVISO PARA NAVEGANTES:** Si vas a tocar el stack, deja el café un segundo y lee. Entrar aquí sin pasar por la wiki es como intentar desarmar una bomba con palillos chinos. Bajo tu propio riesgo.

---

<p align="center">
  <a href="https://rawsmoketerribilus.github.io/UNIT3D/">
    <img src="https://img.shields.io/badge/📖_WIKI_Y_MANUAL-ESTADO:_ONLINE-brightgreen?style=for-the-badge&logo=gitbook&logoColor=white" alt="Manual Online">
  </a>
  <a href="https://github.com/rawsmoketerribilus/UNIT3D/actions">
    <img src="https://img.shields.io/badge/BOT_DEPLOY-ESTADO:_OPERATIVO-blue?style=for-the-badge&logo=github-actions&logoColor=white" alt="Bot Status">
  </a>
</p>

---

### 📚 La Biblia de Operaciones
Todo lo que necesitas para que el tracker no explote está en nuestra Wiki oficial:

👉 **[ACCEDER AL MANUAL COMPLETO](https://rawsmoketerribilus.github.io/UNIT3D/)**

**¿Qué encontrarás ahí dentro?**
* 🛠️ **Configuración del Entorno:** Cómo domar los contenedores sin morir en el intento.
* 💾 **Backups Blindados:** El sistema de snapshots que nos salva el culo a las 06:00 AM.
* 📑 **Guía de Desarrollo:** Para que el código nuevo no parezca escrito por el becario.
* 🏗️ **Testing:** Cómo montar un laboratorio de pruebas que no pese 22GB.

---

---

## 📚 What is UNIT3D?

**[UNIT3D](https://github.com/HDInnovations/UNIT3D)** is a modern, feature-rich Private Torrent Tracker software built on **Laravel 12**, **Livewire**, and **AlpineJS**. Created by the HDInnovations team, it powers high-performance private tracker communities with support for:

- 🔐 **Advanced User Management**: Roles, permissions, invitations, achievements
- 🔍 **Meilisearch Integration**: Millisecond search across millions of torrents
- 📊 **Comprehensive Analytics**: Torrent stats, user activity, seeding ratios
- 🎨 **Theme System**: Customizable UI with Sass/CSS
- 📧 **Email Notifications**: SMTP integration, activity alerts
- 🔗 **IRC Integration**: Live announcements and bot integration
- 🌍 **Internationalization**: Multi-language support

### **Big Thanks to HDInnovations** ❤️

This project would not exist without UNIT3D. The original developers created an incredible platform for private tracker communities. [**→ Visit UNIT3D GitHub**](https://github.com/HDInnovations/UNIT3D)

---

## 🔧 Why N.O.B.S? What We Built

UNIT3D is a **brilliant platform**, but it arrives as source code, not a packaged deployment. We took the Community Edition and did two things:

### **Part 1: Fixed UNIT3D's Broken Pieces**

The Community Edition had **unfixed bugs and missing features**:

| Problem | Impact | Our Solution |
|---------|--------|--------------|
| **Installer Removed** | Official install script deleted by devs; left in broken state | Re-implemented setup logic in `entrypoint.sh` (auto-run migrations, blacklist, cache) |
| **Unconfigured Meilisearch** | Search engine shipped but not indexed or synced | Implemented cold-boot indexing, real-time observer syncing, Master Key protection |
| **Brute-Force Too Aggressive** | Settings locked out legitimate users (5 attempts = 24h block) | Tuned FortifyServiceProvider (5→15 attempts, 24h→1h, created backup owner) |
| **Email Blacklist Fragility** | System breaks if external CDN unreachable | Created persistent local cache (`storage/app/email-blacklist.json`) with hybrid fallback |

---

### **Part 2: Dockerized It (No Trivial Task)**

The original UNIT3D is **not Docker-native**. We built the complete containerization:

| Challenge | Solution |
|-----------|----------|
| **Missing Background Services** | Added `scheduler` and `worker` containers with dedicated entrypoints |
| **IP Address Masking** | Configured Nginx reverse proxy headers + Laravel TrustProxies (real IPs in profiles) |
| **Permission Chaos in Containers** | Auto-healing in `entrypoint.sh` (chmod 775, chown www-data on boot) |
| **Storage Link in Docker** | Configured persistent volume mounts with correct symlinks |
| **No Dependency Persistence** | Included `vendor/` and `node_modules/` in repo (Plug & Play offline recovery) |

---

### **Part 3: Added Resilience (The "Búnker" Philosophy)**

Beyond fixing and dockerizing, we added **autonomous, offline-first features**:

| Feature | Benefit |
|---------|---------|
| **Cold Backup Strategy** | Stop containers → copy → restart (zero corruption, data integrity guaranteed) |
| **Encrypted Google Drive Sync** | Off-site encrypted snapshots via rclone + ephemeral container |
| **Health Check Automation** | Monitors Nginx, Meilisearch, MySQL, Redis, scheduler, worker and announce endpoint |
| **Self-Healing Entrypoints** | Power off / power on → everything just works (no manual intervention) |
| **Dedicated Metadata Queue** | Isolated `meta-refresh` worker so TMDB/MAL/IGDB scrapes never starve the main queue |
| **Rust Tracker (UNIT3D-Announce)** | `/announce/` decoupled from PHP, hot-sync commands from Laravel |
| **Telegram Notifications** | Deep-link account binding, auto-kick on ban, poster + mediainfo per torrent |
| **Makefile Control** | `make up`, `make backup`, `make health`, `make meilisearch` (zero learning curve) |

**Result**: A production-ready, autonomous system designed for **communities running their own infrastructure**.

---

## 🚀 Core Improvements

### 1. **🔍 Meilisearch: Instant, Resilient Search**

**The Challenge**: UNIT3D includes Meilisearch as its search engine, but **provides no documentation or setup**. Installing it and configuring it are left to the operator.

**Our Solution**:

```
🏗️ INFRASTRUCTURE:
  • Dedicated container (getmeili/meilisearch:latest) in docker-compose.yml
  • Persistent index storage (Docker volume meilisearch-data)
  • Master Key protection (MEILISEARCH_KEY in .env, never logged)
  
🔄 INITIALIZATION:
  • Cold-boot indexing: entrypoint.sh runs php artisan scout:import
  • If indexes missing, system rebuilds them on boot (self-healing)
  • Configuration: app/Http/Scout config maps Torrent → Meilisearch
  
⚡ REAL-TIME SYNC:
  • Laravel Observers listen for new/updated torrents
  • Instant indexing (milliseconds) as users upload
  • TMDB/IGDB metadata enrichment (posters, genres, ratings)
  
🛡️ RESILIENCE:
  • Indexes survive container restarts (persisted to volume)
  • Query fallback to MySQL if Meilisearch unavailable
  • Health check monitors /health endpoint
```

**Why it matters**: Searching 50,000+ torrents takes **milliseconds** instead of seconds. Database stays lean. Users get instant, filtered results.

---

### 2. **📧 Resilient Email Blacklist**

**The Problem**: UNIT3D fetches disposable email domains from an external CDN during registration validation. **If the CDN is down or unreachable, registrations fail entirely.**

**Our Solution - Hybrid Blacklist Strategy**:

```
PRIMARY (Online):
  ✅ Fetch fresh list from CDN (andreis/disposable-emails)
  ✅ Update once on boot (php artisan auto:email-blacklist-update)
  
FALLBACK (Offline):
  ✅ Store local copy: storage/app/email-blacklist.json
  ✅ 7,160+ domains persisted locally
  ✅ If CDN unreachable, use local cache (registration still works)
  
PERSISTENCE:
  ✅ Cache survives container restarts
  ✅ Cache survives docker compose down/up
  ✅ Cache included in full backups
```

**Implementation Details**:
- Created `app/Helpers/EmailBlacklistUpdater.php` (auto-update logic)
- Entrypoint runs `php artisan auto:email-blacklist-update` on boot
- Custom Artisan command watches CDN + writes to local JSON
- Registration uses local cache as primary (faster, reliable)

**Result**: Registration works **even if CDN is down**. System is autonomous and offline-capable.

---

### 3. **🌐 IP Address Transparency (Docker Networks)**

**The Problem**: In Docker, Nginx and the Laravel app run in separate containers. Without proper headers, all requests appear to come from Docker's internal gateway (`172.21.0.1`). **All users show the same IP in their profiles.**

**Our Solution - Reverse Proxy Headers + TrustProxies**:

```
NGINX LAYER (.docker/nginx/default.conf):
  • proxy_set_header X-Real-IP $remote_addr;
  • proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  • proxy_set_header X-Forwarded-Proto $scheme;
  
LARAVEL LAYER (app/Http/Middleware/TrustProxies.php):
  • protected $proxies = '*';  [Trust Nginx as reverse proxy]
  • Reads X-Real-IP header and uses it as user's source IP
  
RESULT:
  ✅ Real user IPs captured in database
  ✅ Each user sees their actual public IP in profiles
  ✅ IP-based banning/stats work correctly
```

**Verification**: Log in, visit your profile → you'll see your real public IP, not Docker's gateway.

---

### 4. **🔒 Brute-Force Protection: Balance Security & Usability**

**The Problem**: UNIT3D's default Fortify settings were **too aggressive**:
- 5 failed logins → blocked for 24 hours
- Single shared gateway IP (172.21.0.1 in Docker) → legitimate users all blocked together
- Result: **Developers locked themselves out during testing/recovery**

**Our Adjustment** (`app/Providers/FortifyServiceProvider.php`):

```php
// Before (too strict):
RateLimiter::for('login', 5 attempts per minute);        // 5 failures = block
$throttleKey = hashless unique attempt;

// After (balanced):
RateLimiter::for('login', 15 attempts per minute);       // 15 failures = block
RateLimiter::for('two-factor', 6 attempts per minute);   // 2FA more lenient
Block duration: 24h → 1h                                  // Recovery faster
Multi-account check: 1 → 3 threshold                      // Allow account switching
```

**Additional Safety**:
- Created `BackupOwner` account with full permissions (emergency access)
- Can use backup account if primary is locked
- Logs track failed attempts to investigate actual attacks

**Result**: System protects against brute-force **while allowing legitimate recovery and testing**.

---

### 5. **🛡️ Autonomous Infrastructure (The "Búnker")**

#### **Auto-Healing on Startup**

Every container boot triggers automatic recovery:

```bash
# .docker/entrypoint.sh does:
✅ Copy .env.example → .env (if .env missing)
✅ composer install (if vendor/ missing)  
✅ npm install + build (if public/build/ missing)
✅ Create storage folders
✅ Fix permissions (chmod 775, chown www-data)
✅ Wait for MySQL
✅ Generate APP_KEY (if missing)
✅ Run migrations (--force)
✅ Update email blacklist
✅ Start PHP-FPM
```

**Result**: Power off the server, power on → everything works. No manual intervention.

---

#### **Cold Backup (Surgical Snapshot)**

**Philosophy**: Backups must be **corruption-proof, complete, and easy to restore**.

```bash
./backup.sh workflow:

1. 💾 MySQL Dump (hot dump, --no-tablespaces for MySQL 8)
   └─ Captures database state without locking issues

2. 🛑 Container Freeze (docker compose stop)
   └─ Stops all containers for consistent file snapshot
   
3. 📂 Full Archive (tar -czf)
   └─ Compresses: app code, vendor/, node_modules/, configs, data
   └─ Captures the project tree and exact deployment recipe

4. 🧳 Optional External Mirror
   └─ Copies the snapshot to `BACKUP_EXTERNAL_DIR` when `BACKUP_EXTERNAL_ENABLED=true`
   └─ Keeps its own rotation so you have a second local copy outside the main tree
    
5. ♻️ Rotation (local + external)
   └─ `BACKUP_LOCAL_RETENTION` controls local retention
   └─ `BACKUP_EXTERNAL_RETENTION` controls external-disk retention
   
6. 🚀 Resurrection (docker compose up)
   └─ Verifies backup was successful
   └─ Restarts system immediately (minimize downtime)
```

**Why "surgical"?**:
- ✅ **No corruption**: Stopping containers ensures file consistency during copy
- ✅ **Plug & Play**: Full `vendor/` and `node_modules/` included
- ✅ **Integrated secondary copy**: Snapshot can be mirrored automatically to an external disk with its own retention
- ✅ **Atomic**: Complete snapshot at single point in time

---

#### **Health Checks**

```bash
make health  # Runs ./health_check.sh

Checks:
✅ Main site URL responds with HTTP 200/302
✅ Meilisearch /health endpoint
✅ MySQL connectivity
✅ Redis connectivity
✅ Queue worker alive
✅ Scheduler running
✅ Optional announce endpoint when `ANNOUNCE_HEALTHCHECK_URL` is configured

If any fail: Alerts + can auto-restart
```

---

#### **Rust Announce (External Tracker Implementation)**

The `/announce/` path no longer depends on the classic PHP announce controller. It is now served by a dedicated Docker `announce` service running **UNIT3D-Announce** in Rust.

```bash
Current architecture:

Internet/Cloudflare
    ↓
Nginx (`web`)
    ↓  proxy /announce/
Rust tracker (`announce:6969`)
    ↓  internal API
Laravel (`app`) → Unit3dAnnounce::*
```

Important points:
- ✅ **Tracker code is vendored in-repo** at `rust-announce/UNIT3D-Announce`
- ✅ **No submodules, no symlinks, no hardlinks** in the vendored production tree
- ✅ **Real client IP** is forwarded to the tracker via `CF-Connecting-IP` / `X-Real-IP`
- ✅ **Tracker admin API is blocked publicly** at nginx (`/announce/{TRACKER_KEY}/...`)
- ✅ **Dedicated healthcheck** at `/announce/health/ping`
- ✅ **Simple rollback**: set `TRACKER_ENABLED=false` in `.env` and restart `app/scheduler/worker/web`

Relevant variables:
- `TRACKER_ENABLED`
- `TRACKER_HOST=announce`
- `TRACKER_PORT=6969`
- `TRACKER_KEY`
- `ANNOUNCE_HEALTHCHECK_URL`

Operational implications:
- `backup.sh` already captures the tracker code because it lives inside the project tree
- `disaster-recovery-script.sh` already rebuilds the service during `docker compose up -d`
- the dashboard can still read external tracker stats through `Unit3dAnnounce::getStats()`

---

### 6. **🎨 N.O.B.S Branding (Custom Theme)**

UNIT3D ships with a default theme. We created a custom N.O.B.S identity:

- **Custom SCSS Theme**: `resources/sass/themes/_refined-nobs.scss`
  - Neon cyan/pink aesthetic
  - Glass-morphism panels with blur effects
  - Industrial-blocky typography
  
- **Asset Customization**:
  - **Favicon**: Custom 64x64 NOBS medal icon
  - **Logo**: NOBS branding on login/register pages
  - **OG Image**: Social media sharing image
  - **Auth Pages**: Custom backgrounds and styling
  
- **Easy Extensibility**:
  - All styles in Sass (themeable variables)
  - Compiled with Vite (`npm run build`)
  - Switch themes via admin panel or `config/other.php`

This is **not a core UNIT3D change** — it's a custom skin that respects the original platform.

---

### 7. **⚙️ Configuration Adjustments**

**config/other.php** optimizations:
- Invitation wait time: 24h → 1h (after 2FA activation)
- Max unused invites per user: 1 → 10 (staff-friendly)
- Site subtitle: Contextualized for N.O.B.S
- Email fallback: Safe default if .env missing

**Security hardening**:
- `SESSION_SECURE_COOKIE=true` (HTTPS only)
- `SESSION_DOMAIN=nobs.rawsmoke.net` (explicit domain)
- `TRUSTED_PROXIES=*` (for reverse proxy chains)

---

### 8. **🎨 Aesthetic and Functional Refactor: Retro Theme (v2)**

**The Challenge**: The original `refined_nobs` theme had critical UX/UI issues: dropdowns blocked by z-index conflicts, buttons rendered as flat black blocks without depth, and a pink saturation that hurt long reading sessions. On top of that, torrent ratios were leaking technical garbage (`INF` strings, raw HTML).

**Our Solution (v2)**:

```
🚀 REIMAGINED UI/UX:
  • Deep dark base (#050507) with neon accents (purple → fuchsia) and cyan highlights
  • Fixed dropdowns: z-index ordering and hover effects for smooth navigation
  • Professional buttons: 6px softened borders, subtle gradients, white icons
  • Readability: zebra-striping across tables and centered data panels

🛠️ TECHNICAL FIXES:
  • Ratio "INF" → replaced with the proper infinity symbol (∞)
  • HTML leak: removed literal markup from torrent history tables
  • Blade hygiene: @class directives for clean, robust rendering
```

**Result**: A modern, elegant and functional UI that keeps the Cyberpunk/Synthwave N.O.B.S identity without sacrificing usability or clarity.

---

### 9. **📡 Telegram Integration (Torrent Notification Bot)**

**The Challenge**: UNIT3D has no native Telegram notification system. Operators have no way to automatically announce new torrents to a Telegram group or integrate bot-based user linking.

**Our Solution**:

```
🤝 DEEP-LINK HANDSHAKE:
  • User visits notification settings → gets a unique TRK- token
  • Sends /start TRK-xxxx to the bot in Telegram
  • Bot links their Telegram account to their tracker profile (lockForUpdate transaction)

📢 QUEUED TORRENT NOTIFICATIONS:
  • TorrentObserver fires when torrent status → APPROVED
  • SendTelegramNotification job (3 tries, backoff: 10s / 60s / 300s)
  • Rich message: poster, mediainfo (codec, resolution, audio, bitrate, framerate)
  • Language flags (40+ languages), inline keyboard: IMDb / TMDb / Trailer / Download

🚫 BAN → KICK:
  • When a user is banned, BanController calls TelegramService::kickUser()
  • kickUser() = banChatMember + immediate unbanChatMember (clean kick, not permanent ban)
  • telegram_chat_id and telegram_token are cleared from the user record on ban

🔗 GROUP INVITE:
  • Bot sends group invite link on successful handshake
  • Uses Http::asJson() to preserve + characters in invite URLs
```

**Required `.env` Variables**:

| Variable | Purpose |
|----------|---------|
| `TELEGRAM_BOT_TOKEN` | Bot token from @BotFather |
| `TELEGRAM_GROUP_ID` | Supergroup ID (negative number, e.g. `-1001234567890`) |
| `TELEGRAM_TOPIC_NOVEDADES` | Thread/topic ID for torrent announcements |
| `TELEGRAM_BOT_USERNAME` | Bot @username (without @) |
| `TELEGRAM_GROUP_INVITE_LINK` | Invite URL (`t.me/+xxxxx`) |

**Full documentation**: See [`docs/TELEGRAM_INTEGRATION_GUIDE.md`](./docs/TELEGRAM_INTEGRATION_GUIDE.md)

---

### 10. **☁️ Google Drive Backup Sync (rclone + encryption)**

**The Challenge**: Local cold backups protect against software failures, but hardware loss or server destruction destroys them too. A redundant encrypted cloud copy is essential for true disaster recovery.

**Our Solution — Ephemeral rclone Container**:

```
📦 ARCHITECTURE:
  • rclone_gdrive/docker-compose.yml runs rclone/rclone:latest
  • Container mounts ./backups read-only → syncs to gdrive_crypt: remote
  • Ephemeral: spins up, syncs, self-destructs (--rm)

🔒 ENCRYPTION:
  • gdrive_crypt: is an rclone crypt remote layered over Google Drive
  • Filenames and contents encrypted at rest in the cloud
  • Only the local rclone.conf (git-ignored) holds the decryption keys

⚙️ SYNC OPTIONS:
  • --transfers 4 / --checkers 8 (parallel performance)
  • --drive-chunk-size 1024M (avoids timeout on large snapshots)
  • --delete-after (cloud mirrors local: old snapshots pruned automatically)

♻️ RESTORE:
  • rclone_gdrive/scripts/restore_snapshot.sh (interactive)
  • Lists cloud backups, prompts for target name, downloads + decrypts transparently
  • Output written to: restauracion_emergencia/<snapshot_name>/
```

**How to trigger a sync**:

```bash
./rclone_gdrive/scripts/run_sync.sh
# Logs: rclone_gdrive/logs/cron_wrapper.log
#       rclone_gdrive/logs/sync_execution.log
```

**Cron example** (daily at 07:00):

```bash
0 7 * * * /home/rawserver/UNIT3D_Docker/rclone_gdrive/scripts/run_sync.sh
```

---

### 11. **🧬 Multi-Provider Metadata with Consensus (TMDB · IGDB · MAL · Anilist · IMDb · TVmaze)**

**The Challenge**: The community edition only talks to TMDB. When the match is ambiguous — generic titles, fuzzy years, anime with multiple versions — posters, synopses and genres come back wrong, and the operator ends up editing torrents by hand.

**Our Solution — `ConsensusResolver`**:

```
🔍 6 PROVIDERS IN PARALLEL:
  • TmdbClient   — movies + series (authoritative for Western media)
  • ImdbClient   — fallback for ratings and details when TMDB misses
  • TvmazeClient — TV calendar and episode-level data
  • MalClient    — anime (MyAnimeList API + scraper fallback)
  • AnilistClient — anime/manga GraphQL (cross-voting with MAL)
  • IgdbClient   — video games (separate category, not mixed with film)

🗳️ CONSENSUS ALGORITHM:
  • Each provider returns a normalized score (title + year + type)
  • Hits below the threshold do NOT vote (noise rejection)
  • Majority vote decides canonical_id and artwork
  • Ties broken by category priority (anime→MAL, film→TMDB, games→IGDB)

🖼️ ROTATING ARTWORK:
  • Each torrent stores N posters in `metadata_artwork`
  • `meta:rotate-covers` rotates the active poster so the catalog visually breathes
  • `metadata_resolutions` table audits which provider won and why
```

**Components**:
- `app/Services/Metadata/ConsensusResolver.php` — vote orchestrator
- `app/Services/Metadata/*Client.php` — one client per provider
- Tables: `metadata_resolutions`, `metadata_artwork`, `mal_anime`
- Commands: `meta:sync`, `meta:refresh-dispatch`, `meta:rotate-covers`, `fetch:meta`, `sync:missing-trailers`

**Result**: Much more robust metadata matching, especially on anime (where TMDB is historically weak) and titles with name collisions.

---

### 12. **🛰️ Meta-Worker (Dedicated Queue for Metadata Refresh)**

**The Challenge**: Refreshing metadata can choke the main queue: TMDB rate limits, slow MAL scrapes, IMDb timeouts. If they share a queue, Telegram notifications stall behind a 30-second scrape.

**Our Solution — Isolated Worker**:

```yaml
meta-worker:
  entrypoint: /usr/local/bin/entrypoint-worker.sh
  environment:
    QUEUE_WORK_QUEUES: meta-refresh
    QUEUE_WORK_TIMEOUT: 300
```

```
🚦 QUEUE SEPARATION:
  • `default`     queue → regular worker (Telegram, mails, light jobs)
  • `meta-refresh` queue → meta-worker (TMDB/IGDB/MAL/Anilist/IMDb)
  • Extended 300s timeout to tolerate slow APIs
  • No contention: a stuck scrape never affects notifications

⏰ AUTO-DISPATCH:
  • `php artisan meta:refresh-dispatch --limit=5 --stale-hours=720 --dispatch-ttl-minutes=10`
  • Runs every minute from the scheduler
  • Dispatch TTL avoids re-queueing in-flight jobs
  • Refreshes metadata for torrents untouched for 30+ days
```

**Result**: The UI stays fast while the catalog quietly reindexes itself. The critical queue never drowns.

---

### 13. **🕸️ Swarm Intelligence + 3D Tracker Map**

**The Challenge**: Operators have no view of the actual swarm topology. Who's seeding to whom? Are there cliques? Is there a central node whose absence would shatter the swarm?

**Our Solution — Two Original Views**:

```
📊 SWARM INTEL (torrent page):
  • Foldable Livewire component embedded in each torrent page (`torrent/show.blade.php`)
  • Loaded per-torrent through <livewire:swarm-intelligence :torrentId="$torrent->id" />
  • Peer geographic distribution (flags, ASN, ISP)
  • BitTorrent client histogram
  • Suspicious-pattern detection (same ASN, identical join windows)
  • Foldable panel: staff/users open it only when they need intelligence, without cluttering the main page

🌐 INTERACTIVE 3D MAP (Community section):
  • Force-directed 3D graph rendered in WebGL (three.js + 3d-force-graph)
  • Nodes = users, edges = co-seeding on shared torrents
  • Filters by category, role, activity
  • Near-real-time refresh
```

**Tech stack**:
- `app/Http/Controllers/SwarmGraphController.php` — graph API
- `resources/views/livewire/swarm-intelligence.blade.php` — per-torrent view
- `resources/views/swarm/*` — 3D viewer
- Vendor JS under `public/vendor/` (force-graph, 3d-force-graph, three, d3) — **gitignored**
- `install-swarm-assets.sh` — fetches the libs on fresh clones / rebuilds

**Result**: First public UNIT3D fork with a 3D swarm visualization. Useful for spotting abuse, gauging community health, and — bluntly — it looks fantastic.

---

### 14. **🎮 RetroArch Web (Multi-System Arcade, 26 Libretro Cores)**

**The Challenge**: ScummVM covers classic point-and-click, but the real retro catalog lives on NES, SNES, Mega Drive, Game Boy, PS1, Capcom/Neo Geo arcade. A general-purpose in-browser emulator was needed.

**Our Solution — RetroArch Compiled to WebAssembly**:

```
🕹️ 26 LIBRETRO CORES IN public/retroarch/:
  • fceumm (NES), snes9x (SNES), genesis_plus_gx (Mega Drive/SMS/GG)
  • gambatte (Game Boy/GBC), mgba (GBA), mednafen_psx (PS1)
  • fbneo (arcade), pcsx_rearmed, ecwolf (Wolfenstein 3D), …
  • Full list in core_list.js

🔐 AUTH-WALL:
  • /retroarch/* gated behind Laravel session (no anonymous scraping)
  • `gaming.isolation` middleware applies COOP/COEP on the show page
  • Index/show pages separated: catalog open to members, player isolated

📦 LIBRARY AND COVERS:
  • Cover art via `retroarch:fetch-covers`
  • ROM scanning via `retroarch:scan-roms`
  • ROMs are gitignored — only skeleton + per-system README in git
  • `?debug` mode for diagnosing failed core boots
```

**Components**:
- `app/Http/Controllers/RetroArchController.php`
- `public/retroarch/` (gitignored except skeleton + core_list.js)
- Commands: `retroarch:fetch-covers`, `retroarch:scan-roms`

**Result**: NES, SNES, Mega Drive, PS1 and more, playable straight from the member's browser. Zero installs, Laravel session as the only door.

---

### 15. **🛡️ COOP/COEP Isolation + TMDB Image Proxy (CSP Compliant)**

**The Challenge**: To make certain libretro cores and WASM modules work, you need `Cross-Origin-Opener-Policy: same-origin` + `Cross-Origin-Embedder-Policy: require-corp`. But then the browser blocks `image.tmdb.org` posters for missing `Cross-Origin-Resource-Policy`. Conflict.

**Our Solution — Selective Middleware + Local Proxy**:

```
🧱 gaming.isolation MIDDLEWARE:
  • Applied ONLY to /gaming/{id} and /retroarch/{system}/show
  • Injects COOP/COEP only where isolated WASM is required
  • Rest of the site stays unrestricted (performance intact)

🖼️ TMDB IMAGE PROXY:
  • Route /tmdb-proxy/{size}/{file} → TmdbImageProxyController
  • Serves TMDB images from the same origin (satisfies CSP img-src 'self')
  • Long HTTP cache at the nginx layer
  • Transparent to views: helpers rewrite the URLs

🔐 CLEAN CSP:
  • No per-request dynamic nonces (KISS)
  • Inline JS minimized, vendor lives under /vendor/
  • Headers consolidated across nginx + Laravel
```

**Result**: Isolated WASM running and posters showing, all from the same origin, with no holes punched in the global CSP.

---

### 16. **🤝 Thanks Ratio + Spanish Default Locale**

**Thanks Ratio**:
- A "thanks received / thanks given" metric exposed in profiles, top-nav, and invite/BON forms.
- Encourages participation beyond raw seeding ratio.
- Integrated in `User`, `InviteController`, `TransactionController`.

**Localization**:
- Default locale migrated from `en` → `es` (`change_locale_default_to_es_in_user_settings`).
- Full translation of audit logs, downloads, reports, subtitles, tools, user settings and profile sections.
- New Blade components for staff command buttons and profile conditionals.
- `show_poster` enabled by default (better first impression on a fresh catalog).

**Self-Explaining Profile — "Conditions that apply to you"**:
- New partial in `resources/views/user/profile/partials/my-conditions.blade.php`, mounted into `user/profile/show.blade.php`.
- Shows the user, with no ambiguity, which rules actually affect them:
  - Freeleech (site-wide, group-wide, or both)
  - Double upload
  - Effective minimum ratio (global or group override)
  - Hit & Run: min seed, grace, max warnings, expiration
  - Download slots
- Result: fewer stupid support tickets of the "why does this count / not count for me?" variety, and less staff acting as a human calculator.

---

### 17. **🔒 Edge Hardening (Nginx Announce + Verification)**

**Nginx Announce Hardening** (`.docker/nginx/default.conf`):
- Per-IP connection limits on `/announce/`
- Tightened timeouts (short read/write windows, no zombie connections)
- Rust tracker admin API (`/announce/{TRACKER_KEY}/...`) blocked from the public internet
- Only `/announce/health/ping` is publicly reachable

**Hardened Verification Flow**:
- Dedicated rate-limit on `/email/verify-link/{id}/{hash}` (GET and POST)
- Single-use tokens, short expiration
- Explicit logging of failed attempts
- Resilient registration: backoff in `RegisterController`, hardened validation against disposable-email domains (local list), `ApplicationController` and application flow with their own throttle so bots can't suffocate the public form

**Rust Tracker Sync Commands**:
- `tracker:sync-users` — hot-push user changes to the tracker
- `tracker:sync-torrents` — re-sync the catalog
- `tracker:sync-groups` — refresh per-group permissions
- Useful after backup restores or bulk permission changes

---

### 18. **🎞️ Reactive UI: Floating Trailers, Flash Cards and Freeleech Backdrops**

**The Challenge**: UNIT3D's torrent listing is plain text — title, size, ratio. Users have to open every detail page to figure out what a torrent actually is. Posters stay hidden. The freeleech banner is a flat strip.

**Our Solution — Metadata Sprayed Across the Whole UI**:

```
🎬 FLOATING TRAILER ON HOVER (over the torrent name):
  • Alpine.js component in components/torrent/row.blade.php
  • Detects YouTube key via $meta->trailer or a regex over description
  • youtube-nocookie embed with autoplay + mute + loop + modestbranding
  • Automatic fallback to the poster if the video reports onError (150/101)
  • Tuned delays (400ms enter / 150ms leave) — doesn't fire on quick mouse passes

🪪 FLASH CARD WITH MEDIAINFO + EMBEDDED TRAILER (quick-view button):
  • Small button next to the torrent opens a side-by-side card:
    YouTube trailer (autoplay) │ Compact MediaInfo (codec, res, audio, bitrate)
  • Grid layout 1fr 1fr when both exist, 1fr when only one does
  • HD trailer thumbnail with fallback to hqdefault
  • Zero extra requests: metadata ships with the torrent row

🃏 TMDB FLASH CARD ON THUMBNAILS:
  • Hover over the mini poster → card with synopsis, genre, rating, year
  • Pulled from the ConsensusResolver: the same vote that picks the poster picks the card
  • No client-side scraping — everything from the local cache

🌌 DYNAMIC BACKDROPS IN THE FREELEECH BANNER (resources/views/partials/alerts.blade.php):
  • AlertsComposer (app/View/Composers/AlertsComposer.php) picks 10 backdrops
  • Top torrents by seeders + completions (cache::flexible 900/1800)
  • Images served through /authenticated-images/tmdb-proxy/{size}/{file}
  • Honors CSP img-src 'self' even with COOP/COEP active globally
  • image.tmdb.org URLs are rewritten on the fly before rendering

🔖 REFINED BOOKMARKS:
  • Button shows a live counter of how many users have bookmarked
  • Filled/outlined state depending on whether it's yours
  • "Bookmarked by N users" tooltip (useful social signal without leaking identities)
```

**Result**: The catalog stops being a table and turns into a showcase. Users browse with cinematic previews before deciding what to grab. **All of it riding on the 6 metadata providers — TMDB, IGDB, MAL, Anilist, IMDb, TVmaze shitting bits all over the tracker.**

---

### 19. **🗄️ Deeply Modified Database (No Longer UNIT3D Community)**

**Where we are**: After 2000+ hours, this fork's DB is no longer recognizable to anyone cloning UNIT3D Community Edition and inspecting `database/migrations/`.

**Total migrations**: 376 files in `database/migrations/`. Of those, 10 are N.O.B.S contributions layered on top of the original migrations:

| Migration | Purpose |
|---|---|
| `2026_03_08_000000_create_settings_table.php` | Dynamic tracker settings table (replaces hardcoded constants) |
| `2026_03_24_010501_add_telegram_fields_to_users_table.php` | `telegram_chat_id`, `telegram_token`, `telegram_username` per user |
| `2026_03_27_000001_create_disposable_email_domains_table.php` | Disposable-email blacklist persisted in DB (not volatile JSON) |
| `2026_04_27_000001_create_game_saves_table.php` | Per-user saves for the ScummVM arcade |
| `2026_05_10_000600_add_telegram_group_joined_at_to_users_table.php` | Audit of when a user joined the Telegram group |
| `2026_05_11_000000_change_locale_default_to_es_in_user_settings.php` | Default locale migrated to `es` |
| `2026_05_12_000000_default_show_poster_to_true_in_user_settings.php` | `show_poster` enabled by default on new accounts |
| `2026_05_17_000001_create_mal_anime_table.php` | Local MAL anime cache (avoids scrape rate limits) |
| `2026_05_22_000001_create_metadata_resolutions_table.php` | Audit of ConsensusResolver votes per torrent |
| `2026_05_22_000002_create_metadata_artwork_table.php` | Multi-poster store per torrent (rotating artwork) |

**Operational implications**:
- ⚠️ **Don't migrate this fork against a vanilla UNIT3D Community DB** — the extra tables are mandatory for Telegram, arcade and the multi-provider resolver to boot.
- ⚠️ **`migrate:fresh` is forbidden in production** — wipes the catalog, logs, saved games, and metadata audit trail.
- ✅ For restore use **Path B (Restore from Backup)** — backups capture the full SQL dump, including every applied migration.
- ✅ Seeders do not populate N.O.B.S tables — the disposable-email list is seeded from `EmailBlacklistUpdater::sync()`, metadata fills in via `meta:sync` and `meta:refresh-dispatch`.

**Result**: The DB is now a coherent extension of UNIT3D, not a patch glued on top. Every N.O.B.S feature has its table, its migration, and its place in the backup.

---

### 20. **🎛️ Staff Super-Panels (What HDInnovations Sells Separately, in FOSS)**

**Context**: UNIT3D Community Edition ships with a Staff Dashboard that's functional but **deliberately incomplete**. The advanced admin panels — the ones an operator actually uses daily — are paid extras in HDInnovations' private edition: there are invoices in the wild for up to **one thousand euros** for a serious command panel. No public UNIT3D fork had built them. Nobody had the balls.

**The only thing we saw was a blurry photo of one**. A week of paper design later, we started. What follows has 2000+ hours of iteration on it — and the occasional accidental tracker nuke that still hurts to remember.

#### **Direct Comparison vs. Community Edition**

| Metric | Community Edition | NOBS Fork | Delta |
|---|---|---|---|
| Methods in `CommandController` | 9 | **34** | +25 actions |
| `app/Http/Livewire/Staff/` directory | **does not exist** | present (`ConfigManager`) | +∞ |
| Themed panels in `/staff/commands` | 1 flat list | **9 themed panels with icons** | +8 |
| Total Staff routes | 257 | 282 | +25 |
| Global site configuration panel | **nonexistent** (edit `.env` by hand) | Livewire UI with 6 groups, 25 hot-swap settings | new |
| Methods in Staff `UserController` | 4 | 5 (`telegramInfo`) | +1 |

#### **§20.1 — Command Panel: From Flat List to Operations Center**

Community Edition is a single vertical list of 8-9 buttons (clear cache, maintenance, test email). Ours is an **operations center segmented into 9 themed panels with icons**:

```
🛡️ Site Maintenance and Control          (fa-shield-alt)
   • Enable / disable maintenance mode
   • Toggle invite-only
   • Create storage:link

⚡ Cache and Performance                   (fa-bolt)
   • Clear cache / view / route / config
   • Optimize:clear (Laravel + OPcache)
   • Set all cache (aggressive precaching)
   • Flush queue

☢️ Critical Data Operations                (fa-radiation, danger styled)
   • Destructive actions confined to ONE red panel
   • Explicit confirmation before every shot
   • Persistent logs of who pressed what and when

🎬 TMDB                                    (fa-film)
   • Sync of missing trailers (normal and --force)
   • Rotate covers (rotating artwork)

📡 Rust Tracker — Sync                     (fa-broadcast-tower)
   • Sync users / torrents / groups with UNIT3D-Announce
   • Useful after a restore or bulk permission change

🌱 Peer and Torrent Management             (fa-seedling)
   • Flush old peers / reset user flushes
   • Sync peers / sync torrents
   • Surgical cleanup of tracker state

👥 Users and Cleanup                       (fa-users)
   • Mass ban of disposable-email accounts
   • Deactivate expired warnings
   • Generate Telegram tokens in bulk
   • Clean failed login attempts

🧪 Tests and Utilities                     (fa-flask)
   • Test email (result shown on screen, not in logs)
   • Set Telegram webhook
   • Fix Meilisearch + reindex scout
   • Meilisearch full repair (nuke + rebuild)
   • Update email blacklist from CDN

🔎 Metadata — Identification               (fa-fingerprint)
   • meta:sync and meta:sync --force
   • Rotate active poster
   • Re-resolve orphan torrents
```

**Reusable component**: `Staff/command/_btn.blade.php` — button with inline confirmation, Livewire spinner and visual feedback. Every panel uses it so the UI stays consistent.

#### **§20.2 — Config Manager: The Panel That NEVER EXISTED in Community**

UNIT3D Community **has no global configuration panel**. If you want to change `other.ratio`, `other.freeleech`, `hitrun.seedtime` or any deep tracker setting, you have to edit `.env`, reload cache, pray.

**We have it in production behind a real route**:
- `https://nobs.rawsmoke.net/dashboard/config`
- Staff can open/close registration, toggle site flags, inspect the current effective state, and change live PHP tracker config without leaving the browser.

**We built one from scratch**:

- `app/Http/Controllers/Staff/ConfigController.php` — endpoint
- `app/Http/Livewire/Staff/ConfigManager.php` — Livewire component (**141 lines**, persistence in the `settings` table)
- `resources/views/livewire/staff/config-manager.blade.php` — UI (175 lines)
- `resources/views/Staff/config/index.blade.php` — page shell

**6 themed groups, 25 hot-swap settings**:

| Group | Icon | Settings |
|---|---|---|
| **Site** | 🌐 | Invite-only, default theme, Telegram label |
| **Freeleech & Double Upload** | 🎁 | Global freeleech, until-when, double upload, refundable ratio |
| **Ratio & Downloads** | ⚖️ | Min ratio, initial upload/download, download check page, magnet links |
| **Invitations** | ✉️ | Expiration (days), max unused invites per user |
| **Hit & Run** | ⚠️ | H&R enabled, min seed time (hours), max warnings |
| **Thanks System** | (custom) | Thanks Ratio thresholds, integration with ratio bonus |

**Supported field types**: `boolean`, `bool01`, `text`, `integer`, `decimal`, `bytes`, `theme`. Each one with its own contextual hint.

**Result**: The operator changes the tracker's minimum ratio from the UI. No SSH, no `.env` editing, no `php artisan config:cache`. The change persists in the DB (the `settings` table we also added — see §19) and applies hot.

#### **§20.3 — Why this matters**

This panel is two things at once:

1. **Operational risk reduction**: every critical tracker action has a button with confirmation and a log. Before, you had to remember the exact `php artisan` incantation in a root terminal at 03:00. Now there's a red panel with `fa-radiation` that says "you're about to wipe the Meilisearch cache, sure?".

2. **Living documentation**: how the panel is organized IS the documentation. A new staff member opens `/staff/commands` and understands what tools exist without reading 40 files under `app/Console/Commands/`.

3. **Contribution to the FOSS scene**: as far as we know, **no public UNIT3D fork has had this**. It's work the community can port — the files are versioned, not obfuscated, with no restrictive license beyond the parent project's AGPLv3.

4. **Wiki Gate + guided uploader flow**:
   - The tracker does not dump users in blind: `config('other.upload-guide_url')` points at `/pages/4`.
   - `PageSeeder` explicitly documents **Singularity / RaW_Suite** as the recommended path for professional-grade uploads.
   - Result: staff do not answer the same question 80 times; the wiki acts as the front gate and Singularity does the heavy lifting.

#### **§20.4 — Coordination with Singularity / RaW_Suite (Admin Innovation)**

The tracker panel does not live in isolation. Its identification and large-scale maintenance logic is coordinated with **Singularity / RaW_Suite**, whose local repo lives at:
- `/home/rawserver/scripts/Media-Management/RaW_Suite`

What matters for admins and owners:
- `docs/unit3d_orchestrator.md` — documents the **Mass-Edition** suite
- `docs/unit3d_mass_edition/*` — pipeline, workflows, setup and safety
- `singularity.py` → `unit3d_orchestrator()` — interactive menu for the UNIT3D module
- `config/mass_config.py` — tracker config for mass edition

**What it brings**:
- Mass processing
- Mass upload
- Mass editing of torrent pages on UNIT3D trackers
- Image resurrection
- Description cleanup / enrichment
- Metadata re-coordination at scale, not one torrent at a time

This is not a side curiosity. It is another admin/owner innovation: **an external multi-tool that speaks the tracker's language and lets you operate on hundreds of torrents with pipeline discipline, not manual clicking.**

> *"It's improvable but already a hundred times better than what was there. Sweat, tears, and the occasional accidental tracker nuke."*

---

### 21. **🕹️ Integrated Arcade: ScummVM WebAssembly (Pioneers)**

![Arcade in action inside the tracker](Initial_NOBS_art/photo_2026-04-27_20-23-32.jpg)

**The Challenge**: No UNIT3D fork had ever tried to run classic adventure games directly inside the tracker. We did.

**What we built**:

A full arcade room inside the tracker, with ScummVM compiled to WebAssembly running straight in the browser — no plugins, no installs, no leaving the site.

```
🎮 7 LUCASARTS CLASSICS (SCUMM engine):
  • The Secret of Monkey Island (VGA CD)
  • Monkey Island 2: LeChuck's Revenge (CD Talkie)
  • Maniac Mansion
  • Loom (CD Talkie)
  • Zak McKracken and the Alien Mindbenders
  • Indiana Jones and the Fate of Atlantis (CD Talkie)
  • Sam & Max Hit the Road (CD Talkie)

💾 PER-USER SAVE FILES:
  • Each user gets their own save slot in the database
  • Transparent load/save via REST API
  • Saves persist across sessions and devices

⚙️ TECH STACK:
  • ScummVM compiled to WASM with Asyncify (pthread-less builds)
  • No SharedArrayBuffer — no COOP/COEP headers required for ScummVM itself
  • Single plugin loaded: libscumm.so (~3MB) — only the SCUMM engine
  • scummvm.js (~9MB) + scummvm.wasm (~37MB) served as static
  • INI generated dynamically by GamingController (savepath, language, subtitles)
  • Native fullscreen: requestFullscreen() from a dedicated button

🏗️ LARAVEL ARCHITECTURE:
  • GamingController: static catalog of 7 games with full metadata
  • GameSaveController (API): CRUD on saves with user_id + game_id validation
  • game_saves migration: relational table with unique (user_id, game_id, filename)
  • Blade views: arcade.index (catalog) + arcade.show (player with JS launcher)
  • scummvm-launcher.js: 7 INI sections, save management, fullscreen events
```

**Implementation details**:
- Game files (ROMs) are **gitignored** — copyright. The directory layout IS in git with a `README.md` per game listing required files and exact source.
- The WASM engine is also gitignored (~50MB). See [`docs/GAMING_SETUP.md`](./docs/GAMING_SETUP.md) for full setup.
- Files under `public/` are owned by uid=82 (container www-data) — any copy requires `sudo` + `chown`.

**Why this is pioneering**: We checked every public UNIT3D fork. None has an arcade. None has embedded ScummVM WASM. None has per-user saves. We have it in production.

> *"First private tracker with an integrated arcade room and ScummVM running in the browser."*

---

## 📦 Two Installation Paths

### **🚀 Path A: Fresh Install (New Tracker)**

For a brand-new tracker on a fresh machine:

```bash
# 1. Clone
git clone https://github.com/RawSmokeTerribilus/UNIT3D_Docker.git
cd UNIT3D_Docker

# 2. Configure
cp .env.example .env
# Edit .env with your settings:
#   - APP_URL, ANNOUNCE_URL
#   - DB credentials
#   - MAIL_* settings
#   - MEILISEARCH_KEY
#   - TMDB_API_KEY (optional)

# 3. Install
make install

# 4. Seed initial data (optional)
docker compose exec app php artisan db:seed
docker compose exec app php artisan scout:import

# 5. Access
# Web: http://localhost:8008
# Login: UNIT3D / UNIT3D (from seeder)
```

**What `make install` does**:
- Creates storage/framework directories
- Sets permissions (775 on storage/, bootstrap/cache/)
- Builds Docker images
- Starts all containers
- Entrypoint auto-handles composer/npm/migrations

---

### **📀 Path B: Restore from Backup (Disaster Recovery)**

If your tracker dies or you're moving to a new server:

```bash
# 1. Have your backup
ls -lh backups/snapshot_*/unit3d_full_snapshot_*.tar.gz

# 2. On new host, extract
mkdir -p /home/rawserver/UNIT3D_Docker
tar -xzf backup.tar.gz -C /home/rawserver/UNIT3D_Docker

# 3. Start containers
cd /home/rawserver/UNIT3D_Docker
make up

# 4. Wait for MySQL to boot
sleep 10

# 5. Restore database
docker exec -i unit3d-db mysql -u unit3d -punit3d unit3d < db_unit3d.sql

# 6. Restart app layer
make restart

# 7. Verify
make health
```

**Why this works**:
- Backup includes everything: source code, vendor/, node_modules/, configs
- Database dump is included
- Snapshot can be copied automatically to an external disk configured in `.env`
- Rebuild relies on code-on-disk plus the versioned deployment recipe

---

## 🛠️ Management: The Makefile

```bash
make help            # Show all commands
make install         # Fresh install (folders, permissions, build, up)
make up              # Start containers (daemon mode)
make stop            # Stop containers
make restart         # Restart app + web (after code changes)
make status          # Show container status
make backup          # Run surgical backup (sudo ./backup.sh)
make health          # Run health checks
make logs            # Tail app logs live
make clean           # Clear and re-cache Laravel config/route/view
make meilisearch     # Apply dual-index Meilisearch config (Torrents + People)
make meilisearch-fix # Reinitialize Meilisearch from scratch (wipes data and reindexes)
```

---

## 📊 Architecture

```
┌──────────────────────────────────────────────────────────┐
│                  NGINX (web · port 8008)                 │
│      Reverse Proxy + Static + TMDB Image Proxy           │
└─────┬────────────────────────────────────┬───────────────┘
      │ /announce/*                        │ /* (Laravel)
      ▼                                    ▼
┌──────────────┐                  ┌────────────────┐
│ announce     │                  │ PHP-FPM (app)  │
│ Rust tracker │                  │ Laravel 12     │
│ :6969        │                  │ :9000          │
└──────┬───────┘                  └────────┬───────┘
       │ internal API                      │
       └────────────────┬──────────────────┘
                        │
   ┌────────┬───────────┼──────────────┬──────────────┬─────────────┐
   │        │           │              │              │             │
┌──▼──┐  ┌──▼──┐  ┌────▼─────┐  ┌─────▼──────┐  ┌────▼─────┐  ┌────▼──────┐
│MySQL│  │Redis│  │Meilisearch│  │  Mailpit   │  │  Worker  │  │meta-worker│
│ 8.0 │  │     │  │           │  │ (test box) │  │ default  │  │meta-refresh│
└─────┘  └─────┘  └───────────┘  └────────────┘  └──────────┘  └───────────┘

Scheduler:    php artisan schedule:work (background cron)
Worker:       php artisan queue:work --queue=default  (Telegram, mails, light jobs)
Meta-worker:  php artisan queue:work --queue=meta-refresh  (TMDB/IGDB/MAL/Anilist/IMDb)
Announce:     Rust binary (UNIT3D-Announce) — code vendored in rust-announce/
```

---

## ⚙️ Port Mapping

| Service | Internal | External | Purpose |
|---------|----------|----------|---------|
| Nginx (`web`) | 80 | 8008 | Web UI + proxy to `/announce/` |
| Rust Tracker (`announce`) | 6969 | — | BitTorrent tracker (only reachable via nginx) |
| PHP-FPM (`app`) | 9000 | — | App runtime |
| MySQL (`db`) | 3306 | 3307 | Database |
| Redis | 6379 | 6380 | Cache/Sessions/Queue |
| Meilisearch | 7700 | 7701 | Search Engine |
| Mailpit | 1025/8025 | 8026 | Email Testing |
| Scheduler | — | — | `schedule:work` (background) |
| Worker | — | — | `default` queue |
| Meta-worker | — | — | `meta-refresh` queue |

---

## 🔐 Security Notes

### Environment Variables (.env)

**Keep these safe:**
- `APP_KEY` — Laravel encryption key (generated on install)
- `MAIL_PASSWORD` — SMTP credentials
- `MEILISEARCH_KEY` — Search engine Master Key
- `TMDB_API_KEY` — Third-party API access
- `TELEGRAM_BOT_TOKEN` — Telegram bot authentication token

**Never commit `.env`** to version control. Use `.env.example` as a template.

### Hardened Settings

- Sessions are HTTPS-only (`SESSION_SECURE_COOKIE=true`)
- Session domain is explicit (`SESSION_DOMAIN=your-domain`)
- Brute-force protection tuned to prevent lockouts
- IP addresses correctly forwarded (no Docker gateway exposure)

---

## 📖 Troubleshooting

### **Error 500 / Permission Denied**

```bash
# Auto-fixed on restart, but to force:
docker compose restart app
docker exec unit3d-app chmod -R 775 storage bootstrap/cache
docker exec unit3d-app chown -R www-data:www-data storage bootstrap/cache
```

### **Search not working / No results**

```bash
# Re-index Meilisearch
docker compose exec app php artisan scout:import

# Verify health
make health
```

### **Email not sending**

```bash
# Check Mailpit dashboard (if using local testing)
# Open: http://localhost:8026

# If using SMTP:
docker compose logs app | grep -i mail

# Test via Tinker
docker compose exec app php artisan tinker
# >>> Mail::raw('Test', fn($m) => $m->to('test@example.com')->send());
```

### **Database locked / MySQL issues**

```bash
# Check MySQL logs
docker compose logs db

# If corrupted, restore from backup
# See "Path B: Restore from Backup" above
```

### **Telegram: Webhook not receiving updates / 500 errors**

```bash
# Check worker is processing queued jobs
docker compose logs worker | tail -20

# Verify webhook route is registered
docker compose exec -T app php artisan route:list | grep telegram
```

- **Webhook returns 500**: Confirm the webhook route excludes `throttle:api`, `auth:api`, and `banned` middleware — it is handled by `TelegramWebhookController` which bypasses auth.
- **No notifications after approve**: Ensure the queue worker is running (`docker compose ps worker`) and `TELEGRAM_BOT_TOKEN` / `TELEGRAM_GROUP_ID` are set correctly in `.env`.
- **`+` stripped from invite link**: Confirm `config/services.php` `group_invite_link` is populated and `TelegramService` uses `Http::asJson()` for `sendMessageWithButton`.

### **Metadata not updating / catalog showing stale posters**

```bash
# Confirm the meta-worker is alive
docker compose ps meta-worker
docker compose logs meta-worker | tail -50

# Force a manual batch refresh
docker compose exec app php artisan meta:refresh-dispatch --limit=20 --stale-hours=0

# Re-resolve a single torrent (multi-provider)
docker compose exec app php artisan meta:sync --force --limit=1

# Rotate the active poster (rotating artwork)
docker compose exec app php artisan meta:rotate-covers
```

### **3D Swarm Map fails to load / console shows 404 under /vendor/**

```bash
# Swarm assets are gitignored. Re-fetch them:
./install-swarm-assets.sh
# or inside the container:
docker compose exec app ./install-swarm-assets.sh

# Verify the files exist and are reasonably sized
ls -lh public/vendor/{force-graph,3d-force-graph,three,d3}/*.js
```

### **RetroArch: core won't boot / black screen**

```bash
# Enable debug mode on the player
# Open: https://your-tracker/retroarch/{system}/{game}?debug

# Re-scan cores and covers
docker compose exec app php artisan retroarch:scan-roms
docker compose exec app php artisan retroarch:fetch-covers

# Verify COOP/COEP headers on the show page (required by some cores)
curl -I https://your-tracker/retroarch/snes/show | grep -iE 'cross-origin'
```

### **Rust tracker out of sync with Laravel (permissions / new group)**

```bash
# Hot-sync with the Rust binary
docker compose exec app php artisan tracker:sync-users
docker compose exec app php artisan tracker:sync-torrents
docker compose exec app php artisan tracker:sync-groups
```

---

## 🎯 Philosophy: "From the Scene, For the Scene"

This project reflects 2000+ hours of work to resurrect UNIT3D from its broken community edition state. Every fix, every automation, every redundancy exists because **we believe in the platform**.

- **Offline-first**: Works completely standalone (no cloud dependencies)
- **Resilient**: Auto-heals from common failures (permissions, missing folders, network timeouts)
- **Transparent**: Changes are documented and justified (see this README)
- **Maintainable**: Simple Makefile + scripts anyone can understand
- **Peer-to-peer**: Designed for communities running their own infrastructure

This is tracker software **for people who run trackers**, not a SaaS product with vendor lock-in.

---

## 📝 Contributing

Found a bug? Have an improvement? Issues and PRs welcome!

This is a community fork. We're improving UNIT3D for the benefit of private tracker operators everywhere.

---

## 📜 License

UNIT3D is licensed under the GNU Affero General Public License v3.0. See [LICENSE.txt](./LICENSE.txt).

This fork maintains the same license and spirit: open, transparent, and community-driven.

---

## ❤️ Acknowledgments

- **HDInnovations** for creating UNIT3D
- **The private tracker scene** for decades of innovation and community building
- **The N.O.B.S crew** for the 2000+ hours it took to make this work

---

**Last Updated**: May 2026 | **Status**: 🟢 Production Ready

```
Made with resilience and care.
From the scene. For the scene.
```
