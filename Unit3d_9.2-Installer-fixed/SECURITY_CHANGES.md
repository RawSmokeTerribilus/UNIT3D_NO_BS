# Security Changes - Unit3D Installer v1.2

## Overview
This document explains the security hardening applied to the installer script and important deployment considerations.

---

## 1. File & Directory Permissions (Lines 497-507)

### Changes Made
```bash
find ${INSTALL_PATH} -type f -exec chmod 644 {} \;
find ${INSTALL_PATH} -type d -exec chmod 755 {} \;
chmod 640 ${INSTALL_PATH}/.env
chmod 775 ${INSTALL_PATH}/storage
chmod 775 ${INSTALL_PATH}/bootstrap/cache
chmod 775 ${INSTALL_PATH}/public
```

### Rationale
**Before:** `chmod -R 755` applied 755 to all files and directories, making regular files world-executable and readable
**After:** 
- Files: 644 (rw-r--r--) — owner read/write, others read only
- Directories: 755 (rwxr-xr-x) — standard for web servers
- `.env`: 640 (rw-r-----) — readable by owner and group only
- Writable dirs: 775 — allow www-data to write cache/logs/uploads

### Security Impact
- ✅ `.env` file containing DB passwords, API keys is no longer world-readable
- ✅ Application code is protected from casual inspection
- ✅ Laravel caches in `bootstrap/cache` remain writable by www-data

---

## 2. Critical Permission Issue for Docker Deployments

### The Problem
When this bare-metal installer script is used with Docker **mounted volumes**, the file permissions on the host filesystem apply inside the container:

```
Host: /home/rawserver/UNIT3D_Docker/.env (mode 640, owner rawserver:docker)
Container: Same file, same permissions
Container PHP (running as www-data/UID 33) cannot read it
```

### Solution for Docker Users

**If you deploy to Docker with mounted volumes, run these additional commands:**

```bash
# After installation completes:
cd /path/to/your/UNIT3D_Docker
chmod 640 .env
chgrp docker .env

# Verify:
ls -la .env
# Should show: -rw-r----- 1 rawserver docker
```

The installer now **alerts users to this requirement** in the completion message.

### Why chmod 640 + chgrp docker Works for Both Scenarios

| Scenario | Owner | Group | .env Mode | Result |
|----------|-------|-------|-----------|--------|
| Bare-metal | www-data | www-data | 640 | www-data (owner) can read ✅ |
| Docker | rawserver | docker | 640 | docker group can read ✅ |

---

## 3. Ownership Model

### Current Implementation
```bash
chown -R www-data:www-data ${INSTALL_PATH}
```

### For Bare-Metal
- www-data user exists on the host
- www-data can read/write its own files
- .env (mode 640) is readable by www-data (as owner)

### For Docker Mounted Volumes
- rawserver owns the directory on host
- Change .env group to `docker`: `chgrp docker .env`
- Now docker group (and thus the docker daemon) can read it
- Container PHP processes can access via docker group membership

---

## 4. Security Vulnerabilities Fixed

### Issue 1: World-Readable .env File
**Vulnerability:** Database credentials, API keys, SMTP passwords visible to all system users

**Before:**
```
-rwxr-xr-x. www-data www-data .env  # 755 — world-readable
```

**After:**
```
-rw-r-----. www-data www-data .env  # 640 — group-readable only
```

**Severity:** HIGH (exposed credentials)
**Impact:** Any user on system could read database password

---

### Issue 2: World-Readable Application Files
**Vulnerability:** PHP source code and Laravel framework files visible to all users

**Before:**
```
-rwxr-xr-x. www-data www-data index.php  # 755 — world-executable + readable
```

**After:**
```
-rw-r--r--. www-data www-data index.php  # 644 — group and others read-only
```

**Severity:** MEDIUM (source code exposure, no execution by others)
**Impact:** Easier for attackers to find vulnerabilities through code analysis

---

## 5. Cache & Storage Permissions

### Design Decision
```bash
chmod 775 ${INSTALL_PATH}/storage        # rwxrwxr-x
chmod 775 ${INSTALL_PATH}/bootstrap/cache
```

**Rationale:**
- www-data must write logs, uploaded files, cache data
- 775 allows www-data (group) full access
- Public is also 775 because assets may be built/deployed to it
- This is standard Laravel deployment practice

**Trade-off:**
- Group-writable (other users in www-data group could modify)
- Acceptable for single-purpose servers where only www-data users exist

---

## 6. Recommendations for Deployment

### For Bare-Metal (Ubuntu 22.04/24.04)
✅ Use script as-is
✅ Permissions are correct for single-user/www-data-only deployment
✅ No additional steps needed

### For Docker with Mounted Volumes
⚠️ Run additional commands after installation:
```bash
chmod 640 ${INSTALL_PATH}/.env
chgrp docker ${INSTALL_PATH}/.env
```

### For Multi-User Systems
⚠️ Additional hardening recommended:
```bash
# Restrict storage to www-data only
chmod 700 ${INSTALL_PATH}/storage
chmod 700 ${INSTALL_PATH}/bootstrap/cache

# Restrict application code
chmod 750 ${INSTALL_PATH}
find ${INSTALL_PATH} -type d -exec chmod 750 {} \;
```

---

## 7. Mitigation Strategy: Post-Installation Cache Clearing

The installer properly handles:
1. **Config caching:** `php artisan config:cache` caches environment variables
2. **Route caching:** `php artisan route:cache` pre-compiles routes
3. **View caching:** `php artisan view:cache` compiles Blade templates

**Important:** If file permissions are incorrect after installation, you must clear caches:

```bash
# Inside container or on host
php artisan config:clear
php artisan optimize:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 8. Known Issues & Solutions

### Issue: "Class 'view' does not exist"
**Cause:** Laravel cache corrupted when `.env` permissions are wrong
**Solution:** Clear cache and rebuild (see section 7 above)

### Issue: Permission denied when uploading files
**Cause:** Storage directory not writable by www-data
**Solution:** `chmod 775 /var/www/html/storage`

### Issue: "Cannot read .env" in Docker
**Cause:** File mode 640 or 600 with rawserver:rawserver ownership
**Solution:** `chmod 640 .env && chgrp docker .env`

---

## 9. Security Checklist

Use this to verify your installation is secure:

```bash
# .env should NOT be world-readable
ls -la /var/www/html/.env
# Bad:  -rw-r--r-- or -rwxr-xr-x
# Good: -rw-r----- or -rw------- (depending on deployment)

# PHP files should not be executable for group/other
ls -la /var/www/html/index.php
# Bad:  -rwxrwxr-x (755)
# Good: -rw-r--r-- (644)

# Directories should be rwx for owner, rx for group/other
ls -lad /var/www/html
# Bad:  -rwxr-xr-- (750)
# Good: -rwxr-xr-x (755)

# Storage must be writable by www-data
ls -lad /var/www/html/storage
# Must: -rwxrwxr-x (775) or 700 if www-data is only user
```

---

## 10. Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.2 | May 2026 | Added proper file permissions, .env secured to 640, Docker warnings |
| 1.1 | Apr 2026 | Initial security fixes |
| 1.0 | Mar 2026 | Original bare-metal installer |

---

## 11. References

- [Laravel File Permissions](https://laravel.com/docs/master/installation#configuration)
- [OWASP: File & Code Permissions](https://owasp.org/www-community/attacks/File_Injection)
- [Docker Volume Permissions](https://docs.docker.com/storage/volumes/)
- [Linux File Permissions](https://en.wikipedia.org/wiki/File_system_permissions#Numeric_notation)
