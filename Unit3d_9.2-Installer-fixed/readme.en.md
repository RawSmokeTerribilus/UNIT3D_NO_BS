🛡️ UNIT3D COMMUNITY RECOVERY GUIDE
For: UNIT3D Community members whose repo is broken
From: RawSmokeTerribilus & the Scene
Date: May 2026

📋 BACKGROUND: Why This Happened
The original UNIT3D Community Edition (v9.2.0) shipped from HDInnovations with significant gaps:

No automated installer — the installation script was removed, leaving operators to figure out dependencies manually
Meilisearch unconfigured — search engine included but not indexed or synchronized
Docker not supported — no containerization provided (fragile host setups)
Email validation brittle — if the external CDN goes down, registrations break
Brute-force blocking too aggressive — users get locked out for 24+ hours on typing errors
No backup strategy — disaster recovery was manual and fragile

One tracker operator maintained a community fork to fix these issues. When they stepped back due to personal/health reasons, they posted their installer to Rentry so the community wouldn't lose the work.
This guide resurrects that work for your community.

🚀 WHAT YOU'RE GETTING
The Installer Script (unit3d-installer.sh)
This is an automated bash installer that:
✅ Detects your OS (Ubuntu 22.04 or 24.04 LTS only)
✅ Installs all dependencies (PHP 8.4, MySQL, Redis, Nginx, Node.js, Bun, Composer, Certbot)
✅ Configures and starts all services
✅ Initializes the database and Meilisearch
✅ Builds frontend assets
✅ Sets up SSL with Let's Encrypt
✅ Saves your credentials securely
Runtime: ~20-25 minutes on a clean Ubuntu server.

⚠️ CRITICAL NOTES FOR YOUR DEPLOYMENT
Platform Support

✅ Ubuntu 22.04 LTS — Supported
✅ Ubuntu 24.04 LTS — Supported
❌ RHEL / CentOS / AlmaLinux — NOT supported — you'll need to adapt package manager commands (dnf instead of apt)
❌ Debian (non-Ubuntu) — Not tested; YMMV

Hardware Requirements

Minimum: 2 CPU cores, 4GB RAM, 50GB disk (for database growth)
Recommended: 4+ CPU cores, 8GB+ RAM, 100GB+ disk (for torrent database)
Network: Static IP strongly recommended; dynamic IPs require DNS updater

What This Script Does NOT Do

❌ Manage SSL cert renewal (Certbot auto-renewal is set up, but you must verify /etc/letsencrypt/renewal/ cron)
❌ Configure a firewall (you should add UFW/iptables rules)
❌ Set up automated backups (use backup.sh from the N.O.B.S fork separately)
❌ Install Docker (this is the bare-metal installer; use N.O.B.S fork if you want containers)
❌ Configure Telegram bots or advanced integrations (those are in the Docker version)


📋 PRE-INSTALLATION CHECKLIST
Before running the installer, have these ready:
Domain & SSL:

 Valid domain name (e.g., tracker.example.com)
 Ability to port-forward 80/443 to your server (for Let's Encrypt validation)
 Email for SSL certificate registration (Let's Encrypt will contact you)

SMTP Configuration:

 SMTP host (e.g., smtp.mailgun.org, mail.example.com)
 SMTP port (usually 587 for TLS or 25 for plain)
 SMTP username & password
 "From" email address for tracker notifications

Optional:

 TMDB API Key (for movie/TV metadata) — Register here

Server Access:

 SSH access as root or user with sudo privileges
 Linux knowledge (basic bash, vi/nano, systemctl)


🛠️ INSTALLATION STEPS
1. Download the Script
bash# Create a working directory
mkdir -p /opt/unit3d-install
cd /opt/unit3d-install

# Save the installer (copy from the code block above)
nano unit3d-installer.sh
# Paste the script, save (Ctrl+X → Y → Enter)

# Make it executable
chmod +x unit3d-installer.sh
2. Run the Installer
bashsudo ./unit3d-installer.sh
The script will:

✅ Verify you're root
✅ Detect Ubuntu version
✅ Prompt you for configuration (domain, SMTP, etc.)
✅ Install everything
✅ Show a completion summary

3. Save Your Credentials
After installation completes, find your credentials file:
bashcat /root/unit3d-credentials.txt
IMPORTANT: Save this file securely. It contains:

Owner login credentials
MySQL passwords
Meilisearch API key
Useful admin commands

Then delete the original:
bashrm /root/unit3d-credentials.txt
4. Verify Installation
bash# Check services
systemctl status nginx
systemctl status php8.4-fpm
systemctl status mysql
systemctl status redis-server
systemctl status supervisor

# Check the tracker loads
curl -I https://YOUR_DOMAIN

# Check Meilisearch
curl http://localhost:7700/health
5. Login
Open https://YOUR_DOMAIN in your browser.
Default owner login:

Username: UNIT3D (or what you set)
Password: (from the credentials file)


🔧 AFTER INSTALLATION: NEXT STEPS
A. Configure Invite System
By default, invites are disabled. To enable:
bash# SSH into your server
sudo -u www-data php artisan tinker

>>> Setting::query()->update(['invite_only' => false])
>>> exit
Or use the admin panel: Admin → Settings → Site → Invite Only
B. Add Content (Torrents)

Upload test torrents via the admin panel
Meilisearch will automatically index them
Configure announcement feeds if you have IRC/Discord bots

C. Configure Backups
The installer doesn't set up automated backups. To add them:
bash# Daily backup at 2 AM
(crontab -l 2>/dev/null; echo "0 2 * * * /var/www/html/backup.sh") | crontab -
(You'll need to create backup.sh or use the Docker version's backup script)
D. Set Up Email Notifications
Test SMTP configuration:
bashcd /var/www/html

# Send a test email
php artisan tinker
>>> Mail::raw('Test email', fn($m) => $m->to('admin@example.com')->send())
>>> exit
If it works, notifications are enabled. Users will get emails on:

Registration confirmation
Torrent approval
New peers in torrents they're downloading

E. (Optional) Configure Telegram Bot Integration
This requires additional setup (not in the bare-metal installer). See the N.O.B.S fork docs if you want real-time Telegram notifications.

🚨 COMMON ISSUES & FIXES
Issue: "Meilisearch returns 500"
bash# Rebuild the search index
php artisan scout:import

# Restart Meilisearch
systemctl restart meilisearch
Issue: "MySQL port 3306 in use / can't start database"
bash# Check what's using it
sudo lsof -i :3306

# If another MySQL instance, stop it
sudo systemctl stop mysql
sudo systemctl disable mysql
# Then run the installer on a different port
Issue: "Let's Encrypt cert renewal failing"
bash# Test renewal manually
sudo certbot renew --dry-run

# Check the renewal cron
sudo systemctl status snap.certbot.renew.timer
Issue: "Users getting 'Permission Denied' on uploads"
bash# Fix storage permissions
sudo chown -R www-data:www-data /var/www/html/storage
sudo chmod -R 775 /var/www/html/storage
Issue: "Search returns no results"
bash# Re-index all torrents
cd /var/www/html
sudo -u www-data php artisan scout:import

# Verify Meilisearch is running
curl http://localhost:7700/health

📚 USEFUL COMMANDS FOR ADMINS
bash# View logs
tail -f /var/www/html/storage/logs/laravel.log

# Clear all caches
cd /var/www/html
sudo -u www-data php artisan optimize:clear

# Rebuild Laravel caches (for performance)
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Restart all services
sudo systemctl restart nginx php8.4-fpm mysql redis-server supervisor

# Check queue workers
sudo supervisorctl status

# Restart queue workers
sudo supervisorctl restart all

# Access Laravel Tinker (interactive shell)
cd /var/www/html
sudo -u www-data php artisan tinker

🛡️ SECURITY HARDENING (Post-Install)
1. Firewall
bashsudo ufw allow 22/tcp     # SSH
sudo ufw allow 80/tcp     # HTTP
sudo ufw allow 443/tcp    # HTTPS
sudo ufw default deny incoming
sudo ufw enable
2. Fail2Ban (Brute-Force Protection)
bashsudo apt install fail2ban
sudo systemctl enable fail2ban
3. Regular Backups
Set up a backup cron (you'll need to write the script):
bash(crontab -l 2>/dev/null; echo "0 2 * * * mysqldump -u root unit3d > /backups/unit3d_$(date +\%Y\%m\%d).sql") | crontab -
4. Disable Root SSH
bashsudo nano /etc/ssh/sshd_config
# Change: PermitRootLogin no
sudo systemctl restart sshd

💾 DISASTER RECOVERY
If something breaks catastrophically:
Backup Location
The installer doesn't create automated backups. Your data lives in:

MySQL Database: /var/lib/mysql/unit3d/
User Files: /var/www/html/storage/app/
Application Code: /var/www/html/

If You Need to Restore from Previous Server

Dump the database from the old server:

bashmysqldump -u root unit3d > unit3d_backup.sql

On the new server, after installation:

bashmysql -u root unit3d < unit3d_backup.sql

Copy user files:

bashscp -r old-server:/var/www/html/storage/app/* /var/www/html/storage/app/
sudo chown -R www-data:www-data /var/www/html/storage

🤝 GETTING HELP
If you encounter issues:

Check the logs:

bashtail -f /var/www/html/storage/logs/laravel.log
docker compose logs app  # (if using Docker version)

Check the GitHub repo: https://github.com/RawSmokeTerribilus/UNIT3D_NO_BS
(For the Docker-ized version with more features)
Consult UNIT3D Official Docs: https://github.com/HDInnovations/UNIT3D-Community-Edition
Community Support:
Original UNIT3D: https://unit3d.io/
Scene channels / private tracker forums


❤️ CREDITS & THANKS

HDInnovations — Created UNIT3D, the brilliant underlying platform
RawSmokeTerribilus — Fixed and stabilized UNIT3D for the community
The Scene — Decades of innovation in private tracker infrastructure

This installer is shared under the AGPL v3.0 license, same as UNIT3D.

📝 NOTES FOR YOUR COMMUNITY
You are now running the fixed version that:

✅ Actually installs automatically
✅ Handles Meilisearch setup
✅ Isn't fragile on email validation
✅ Doesn't lock users out excessively
✅ Works on fresh Ubuntu servers

The operator who created this stepped back for health/personal reasons. This work is now in the community's hands. Feel free to improve it, fix bugs, and share improvements back.

Last Updated: May 2026
Status: ✅ Production Ready
Tested On: Ubuntu 22.04 LTS, Ubuntu 24.04 LTS

This should give your community everything they need to resurrect and run a healthy tracker. Copy both the script and this guide to your community channels. Good luck! 🚀
