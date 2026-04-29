# 🎬 UNIT3D BÚNKER - N.O.B.S Edition

> **A Private Torrent Tracker, Dockerized & Battle-Hardened**

```
███████████████████████████████████████████████████████████████
█                                                             █
█   🛡️  UNIT3D BÚNKER  |  Nuclear Order Bit Syndicate         █
█                                                             █
█   "From the Scene, For the Scene"                           █
█   100+ hours of stabilization, automation, and resilience   █
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
| **Health Check Automation** | Monitor 8008, Meilisearch, MySQL, Redis; alert on failure |
| **Auto-Healing Entrypoints** | Power off/on → everything works (no manual intervention) |
| **Makefile Control** | `make up`, `make backup`, `make health` (simple operations, zero learning curve) |

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

**Philosophy**: Backups must be **corruption-proof, complete, and offline-capable**. 

```bash
./backup.sh workflow:

1. 💾 MySQL Dump (hot dump, --no-tablespaces for MySQL 8)
   └─ Captures database state without locking issues

2. 🛑 Container Freeze (docker compose stop)
   └─ Stops all containers for consistent file snapshot
   
3. 📦 Image Snapshot (docker save)
   └─ Exports all Docker images (php:8.4, mysql:8.0, redis, meilisearch)
   └─ Used for offline reconstruction if Docker Hub unavailable
   
4. 📂 Full Archive (tar -czf)
   └─ Compresses: app code, vendor/, node_modules/, configs, data
   └─ Includes everything needed for complete offline recovery
   
5. ♻️ Rotation (keep last 3 snapshots)
   └─ Prevents disk fill-up, maintains recent backups
   
6. 🚀 Resurrection (docker compose up)
   └─ Verifies backup was successful
   └─ Restarts system immediately (minimize downtime)
```

**Why "surgical"?**:
- ✅ **No corruption**: Stopping containers ensures file consistency during copy
- ✅ **Plug & Play**: Full `vendor/` and `node_modules/` included
- ✅ **Offline recovery**: Docker images + all dependencies = works anywhere
- ✅ **Atomic**: Complete snapshot at single point in time

---

#### **Health Checks**

```bash
make health  # Runs ./health_check.sh

Checks:
✅ Port 8008 responds with HTTP 200
✅ Meilisearch /health endpoint
✅ MySQL connectivity
✅ Redis connectivity
✅ Queue worker alive
✅ Scheduler running

If any fail: Alerts + can auto-restart
```

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
- Docker images are included (can work offline)
- No need to download anything; entirely self-contained

---

## 🛠️ Management: The Makefile

```bash
make help        # Show all commands
make up          # Start containers (daemon mode)
make stop        # Stop containers
make restart     # Restart app + web (after code changes)
make status      # Show container status
make backup      # Run surgical backup
make health      # Run health checks
make logs        # Tail app logs live
make clean       # Clear Laravel caches (config, routes, views)
```

---

## 📊 Architecture

```
┌──────────────────────────────────────────────────┐
│                   NGINX (Port 8008)              │
│               (Reverse Proxy + Static)           │
└────────────┬─────────────────────────────────────┘
             │
      ┌──────▼──────┐
      │   PHP-FPM   │ (Laravel App)
      │  (Port 9000)│
      └──────┬──────┘
             │
   ┌─────────┼─────────┬────────────┬──────────────┐
   │         │         │            │              │
┌──▼──┐  ┌──▼──┐  ┌───▼────┐  ┌───▼────┐  ┌───▼──┐
│MySQL│  │Redis│  │Meili   │  │Mailpit │  │Worker│
│8.0  │  │     │  │search  │  │(Mailbox)  │Queue │
└─────┘  └─────┘  └────────┘  └────────┘  └──────┘

Scheduler: Runs php artisan schedule:work (background cron)
Worker: Runs php artisan queue:work (background jobs)
```

---

## ⚙️ Port Mapping

| Service | Internal | External | Purpose |
|---------|----------|----------|---------|
| Nginx | 80 | 8008 | Web UI |
| PHP-FPM | 9000 | — | App runtime |
| MySQL | 3306 | 3307 | Database |
| Redis | 6379 | 6380 | Cache/Sessions/Queue |
| Meilisearch | 7700 | 7701 | Search Engine |
| Mailpit | 1025/8025 | 8026 | Email Testing |

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

---

## 🎯 Philosophy: "From the Scene, For the Scene"

This project reflects 100+ hours of work to resurrect UNIT3D from its broken community edition state. Every fix, every automation, every redundancy exists because **we believe in the platform**.

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
- **The N.O.B.S crew** for the 100 hours it took to make this work

---

**Last Updated**: March 2026 | **Status**: 🟢 Production Ready

```
Made with resilience and care.
From the scene. For the scene.
```
