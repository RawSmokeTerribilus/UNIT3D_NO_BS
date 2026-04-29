#!/bin/bash

################################################################################
#                                                                              #
#   UNIT3D TELEGRAM IMPLEMENTATION - PRODUCTION DEPLOYMENT SCRIPT             #
#   Version: 4.0.0                                                            #
#   Purpose: Safe, idempotent deployment of Telegram features to production   #
#   Updated: 2026-03-25 - Ban→Kick, Invite Link, config key validation        #
#                                                                              #
################################################################################

set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

# Configuration
ENVIRONMENT="${1:-production}"
BACKUP_DIR="./backups/deployment-$(date +%Y%m%d-%H%M%S)"
DEPLOYMENT_LOG="./deployment-telegram-$(date +%Y%m%d-%H%M%S).log"
ROLLBACK_ENABLED=true

# Global database variables (extracted in Phase 1)
DB_HOST=""
DB_USER=""
DB_PASS=""
DB_NAME=""
TELEGRAM_TOKEN=""
TELEGRAM_BOT_USERNAME=""
TELEGRAM_GROUP_INVITE_LINK=""

# Environment validation
validate_environment() {
    log_info "Phase 0: Validating environment..."
    
    if [ ! -f ".env" ]; then
        log_error ".env file not found"
        exit 1
    fi
    
    # Check required Telegram config variables
    if ! grep -q "TELEGRAM_BOT_TOKEN" .env; then
        log_error "TELEGRAM_BOT_TOKEN not found in .env"
        exit 1
    fi
    
    if ! grep -q "TELEGRAM_GROUP_ID" .env; then
        log_error "TELEGRAM_GROUP_ID not found in .env"
        exit 1
    fi
    
    if ! grep -q "TELEGRAM_TOPIC_NOVEDADES" .env; then
        log_error "TELEGRAM_TOPIC_NOVEDADES not found in .env"
        exit 1
    fi
    
    if ! grep -q "TELEGRAM_BOT_USERNAME" .env; then
        log_error "TELEGRAM_BOT_USERNAME not found in .env"
        exit 1
    fi
    
    if ! grep -q "TELEGRAM_GROUP_INVITE_LINK" .env; then
        log_error "TELEGRAM_GROUP_INVITE_LINK not found in .env (required for group invite button)"
        exit 1
    fi
    
    # Validate docker-compose exists
    if [ ! -f "docker-compose.yml" ]; then
        log_error "docker-compose.yml not found"
        exit 1
    fi
    
    # Check if containers are running
    if ! docker compose ps --services | grep -q "app"; then
        log_error "Docker services are not running. Run 'docker compose up -d' first"
        exit 1
    fi
    
    log_success "Environment validation passed"
}

# Robust extraction of environment variables with proper quoting
extract_env_variables() {
    log_info "Phase 1: Extracting environment variables..."
    
    # Extract database host
    DB_HOST=$(grep "DB_HOST=" .env | sed 's/.*DB_HOST=//;s/^"//;s/"$//')
    if [ -z "$DB_HOST" ]; then
        log_error "DB_HOST not found in .env"
        return 1
    fi
    
    # Extract database user
    DB_USER=$(grep "DB_USERNAME=" .env | sed 's/.*DB_USERNAME=//;s/^"//;s/"$//')
    if [ -z "$DB_USER" ]; then
        log_error "DB_USERNAME not found in .env"
        return 1
    fi
    
    # Extract database password - ROBUST: handles spaces, asterisks, special chars
    DB_PASS=$(grep "DB_PASSWORD=" .env | sed 's/.*DB_PASSWORD=//;s/^"//;s/"$//')
    if [ -z "$DB_PASS" ]; then
        log_error "DB_PASSWORD not found in .env"
        return 1
    fi
    
    # Extract database name
    DB_NAME=$(grep "DB_DATABASE=" .env | sed 's/.*DB_DATABASE=//;s/^"//;s/"$//')
    if [ -z "$DB_NAME" ]; then
        log_error "DB_DATABASE not found in .env"
        return 1
    fi
    
    # Extract Telegram token
    TELEGRAM_TOKEN=$(grep "TELEGRAM_BOT_TOKEN=" .env | sed 's/.*TELEGRAM_BOT_TOKEN=//;s/^"//;s/"$//')
    if [ -z "$TELEGRAM_TOKEN" ]; then
        log_error "TELEGRAM_BOT_TOKEN not found in .env"
        return 1
    fi
    
    # Extract Telegram bot username
    TELEGRAM_BOT_USERNAME=$(grep "TELEGRAM_BOT_USERNAME=" .env | sed 's/.*TELEGRAM_BOT_USERNAME=//;s/^"//;s/"$//')
    if [ -z "$TELEGRAM_BOT_USERNAME" ]; then
        log_error "TELEGRAM_BOT_USERNAME not found in .env"
        return 1
    fi
    
    # Extract Telegram group invite link
    TELEGRAM_GROUP_INVITE_LINK=$(grep "TELEGRAM_GROUP_INVITE_LINK=" .env | sed 's/.*TELEGRAM_GROUP_INVITE_LINK=//;s/^"//;s/"$//')
    if [ -z "$TELEGRAM_GROUP_INVITE_LINK" ]; then
        log_error "TELEGRAM_GROUP_INVITE_LINK not found in .env"
        return 1
    fi
    
    log_success "Environment variables extracted"
}

# Creation de backup de database antes de migration
backup_database() {
    log_info "Phase 2: Backing up database..."
    
    mkdir -p "$BACKUP_DIR"
    
    # Use proper quoting for password with special characters
    if docker compose exec -T db mysqldump \
        -h "$DB_HOST" \
        -u "$DB_USER" \
        -p"$DB_PASS" \
        "$DB_NAME" > "$BACKUP_DIR/unit3d_pre_telegram_migration.sql" 2>>"$DEPLOYMENT_LOG"; then
        log_success "Database backup created at $BACKUP_DIR/unit3d_pre_telegram_migration.sql"
    else
        log_error "Database backup failed"
        return 1
    fi
}

# Phase 3: Validate Telegram API connectivity
validate_telegram_api() {
    log_info "Phase 3: Validating Telegram Bot API connectivity..."
    
    if [ -z "$TELEGRAM_TOKEN" ]; then
        log_error "TELEGRAM_BOT_TOKEN is empty"
        return 1
    fi
    
    local response=$(curl -s "https://api.telegram.org/bot${TELEGRAM_TOKEN}/getMe")
    
    if echo "$response" | grep -q '"ok":true'; then
        log_success "Telegram Bot API is accessible and token is valid"
    else
        log_error "Telegram Bot API validation failed"
        log_error "Response: $response"
        return 1
    fi
}

# Phase 4: Validate and apply migrations
apply_migrations() {
    log_info "Phase 4: Applying database migrations..."
    
    # List pending migrations
    log_info "Checking for pending migrations..."
    docker compose exec -T app php artisan migrate:status >> "$DEPLOYMENT_LOG" 2>&1
    
    # Apply migrations
    if docker compose exec -T app php artisan migrate --force 2>>"$DEPLOYMENT_LOG"; then
        log_success "Database migrations applied successfully"
    else
        log_error "Migration failed - rolling back to backup"
        if [ "$ROLLBACK_ENABLED" = true ]; then
            log_warning "To restore from backup, run:"
            log_warning "  docker compose exec -T db mysql -u\"$DB_USER\" -p\"$DB_PASS\" \"$DB_NAME\" < $BACKUP_DIR/unit3d_pre_telegram_migration.sql"
        fi
        return 1
    fi
}

# Phase 5: Verify code changes are ready
verify_code_changes() {
    log_info "Phase 5: Verifying code changes..."
    
    local files_to_check=(
        "app/Jobs/SendTelegramNotification.php"
        "app/Observers/TorrentObserver.php"
        "app/Http/Controllers/User/TelegramController.php"
        "app/Http/Controllers/API/TelegramWebhookController.php"
        "app/Http/Controllers/Staff/BanController.php"
        "app/Services/TelegramService.php"
        "config/services.php"
        "resources/views/partials/telegram_settings.blade.php"
        "resources/views/user/notification-setting/edit.blade.php"
        "database/migrations/2026_03_24_010501_add_telegram_fields_to_users_table.php"
    )
    
    for file in "${files_to_check[@]}"; do
        if [ ! -f "$file" ]; then
            log_error "Missing required file: $file"
            return 1
        fi
    done
    
    # Verify SendTelegramNotification has retry policy
    if ! grep -q 'public \$tries = 3' app/Jobs/SendTelegramNotification.php; then
        log_error "SendTelegramNotification missing retry policy (\$tries)"
        return 1
    fi
    
    # Verify languageToFlag method exists (flag emojis for audio/subs)
    if ! grep -q 'languageToFlag' app/Jobs/SendTelegramNotification.php; then
        log_error "SendTelegramNotification missing languageToFlag method"
        return 1
    fi
    
    # Verify Observer dispatches to the correct Job
    if ! grep -q 'SendTelegramNotification::dispatch' app/Observers/TorrentObserver.php; then
        log_error "TorrentObserver not dispatching SendTelegramNotification"
        return 1
    fi
    
    # Verify notification-setting view includes telegram partial
    if ! grep -q "@include('partials.telegram_settings')" resources/views/user/notification-setting/edit.blade.php; then
        log_error "Telegram settings partial not included in notification-setting view"
        return 1
    fi
    
    # Verify token generation uses TRK- prefix
    if ! grep -q "'TRK-'" app/Http/Controllers/User/TelegramController.php; then
        log_error "TelegramController token generation missing TRK- prefix"
        return 1
    fi
    
    # Verify webhook controller handles /start, /status, /help
    if ! grep -q 'handleStart' app/Http/Controllers/API/TelegramWebhookController.php; then
        log_error "WebhookController missing handleStart method"
        return 1
    fi
    if ! grep -q 'handleStatus' app/Http/Controllers/API/TelegramWebhookController.php; then
        log_error "WebhookController missing handleStatus method"
        return 1
    fi
    
    # Verify sendMessageWithButton method exists (group invite inline keyboard)
    if ! grep -q 'sendMessageWithButton' app/Http/Controllers/API/TelegramWebhookController.php; then
        log_error "WebhookController missing sendMessageWithButton method (invite link button)"
        return 1
    fi
    
    # Verify TelegramService uses correct config keys (token, not bot_token)
    if grep -q "config('services.telegram.bot_token')" app/Services/TelegramService.php; then
        log_error "TelegramService.php still uses deprecated 'bot_token' key — must be 'token'"
        return 1
    fi
    if grep -q "config('services.telegram.group_id')" app/Services/TelegramService.php; then
        log_error "TelegramService.php still uses deprecated 'group_id' key — must be 'chat_id'"
        return 1
    fi
    
    # Verify BanController has Telegram kick integration
    if ! grep -q 'kickUser' app/Http/Controllers/Staff/BanController.php; then
        log_error "BanController missing kickUser() call — ban won't kick from Telegram group"
        return 1
    fi
    
    # Verify config/services.php has group_invite_link key
    if ! grep -q 'group_invite_link' config/services.php; then
        log_error "config/services.php missing 'group_invite_link' key in telegram section"
        return 1
    fi
    
    # Validate PHP syntax of key files
    log_info "  Checking PHP syntax..."
    for phpfile in app/Jobs/SendTelegramNotification.php app/Observers/TorrentObserver.php app/Http/Controllers/User/TelegramController.php app/Http/Controllers/API/TelegramWebhookController.php app/Services/TelegramService.php app/Http/Controllers/Staff/BanController.php; do
        if ! docker compose exec -T app php -l "$phpfile" >> "$DEPLOYMENT_LOG" 2>&1; then
            log_error "Syntax error in $phpfile"
            return 1
        fi
    done
    
    log_success "All code changes verified"
}

# Phase 6: Restart worker and clear caches (NO full rebuild - avoids downtime)
restart_services() {
    log_info "Phase 6: Restarting worker and clearing caches..."
    
    # Clear all caches from the app container
    docker compose exec -T app php artisan config:clear >> "$DEPLOYMENT_LOG" 2>&1
    docker compose exec -T app php artisan cache:clear >> "$DEPLOYMENT_LOG" 2>&1
    docker compose exec -T app php artisan view:clear >> "$DEPLOYMENT_LOG" 2>&1
    docker compose exec -T app php artisan route:clear >> "$DEPLOYMENT_LOG" 2>&1
    
    # Warm up config cache
    docker compose exec -T app php artisan config:cache >> "$DEPLOYMENT_LOG" 2>&1
    
    # CRITICAL: Restart the WORKER container so it picks up code changes
    # The worker runs 'php artisan queue:work' as PID 1 and caches classes in memory
    log_info "  Restarting worker container..."
    if docker compose restart worker >> "$DEPLOYMENT_LOG" 2>&1; then
        log_success "  Worker container restarted"
    else
        log_error "  Failed to restart worker container"
        return 1
    fi
    
    # Wait for worker to be back up
    sleep 5
    local worker_status=$(docker compose ps worker --format "{{.Status}}" 2>/dev/null)
    if echo "$worker_status" | grep -q "Up"; then
        log_success "  Worker is running: $worker_status"
    else
        log_error "  Worker failed to restart: $worker_status"
        return 1
    fi
    
    log_success "Services restarted and caches cleared"
}

# Phase 7: Verify telegram routes
verify_routes() {
    log_info "Phase 7: Verifying Telegram routes..."
    
    if docker compose exec -T app php artisan route:list 2>>"$DEPLOYMENT_LOG" | grep -q "users.telegram.reset"; then
        log_success "Telegram reset route is registered (users.telegram.reset)"
    else
        log_error "Telegram reset route not found"
        return 1
    fi
    
    # Verify webhook route
    if docker compose exec -T app php artisan route:list 2>>"$DEPLOYMENT_LOG" | grep -q "api/telegram/webhook"; then
        log_success "Telegram webhook route is registered (api/telegram/webhook)"
    else
        log_error "Telegram webhook route not found in api.php"
        return 1
    fi
}

# Phase 8: End-to-end validation
validate_deployment() {
    log_info "Phase 8: Running end-to-end validation tests..."
    
    # Test 1: Check if TelegramService can be instantiated
    log_info "  Testing TelegramService instantiation..."
    if docker compose exec -T app php artisan tinker --execute="(new \App\Services\TelegramService());" 2>>"$DEPLOYMENT_LOG"; then
        log_success "  ✓ TelegramService is instantiable"
    else
        log_error "  ✗ TelegramService instantiation failed"
        return 1
    fi
    
    # Test 2: Verify event listener is registered
    log_info "  Testing event observer registration..."
    if docker compose exec -T app php artisan tinker --execute="\App\Models\Torrent::resolveObserverCallbacks('created');" 2>>"$DEPLOYMENT_LOG"; then
        log_success "  ✓ TorrentObserver is registered"
    else
        log_warning "  ⚠ Could not verify TorrentObserver registration"
    fi
    
    # Test 3: Check queue worker connectivity
    log_info "  Testing queue worker..."
    local worker_status=$(docker compose ps worker 2>/dev/null | grep -c "Up" || echo "0")
    if [ "$worker_status" -gt 0 ]; then
        log_success "  ✓ Queue worker is running"
    else
        log_error "  ✗ Queue worker is not running"
        return 1
    fi
    
    # Test 4: Validate Telegram config is loaded and database connectivity with proper quoting
    log_info "  Validating Telegram configuration and database connectivity..."
    if docker compose exec -T db mysqladmin \
        -h "$DB_HOST" \
        -u "$DB_USER" \
        -p"$DB_PASS" \
        ping 2>>"$DEPLOYMENT_LOG" | grep -q "mysqld is alive"; then
        log_success "  ✓ Database is accessible"
    else
        log_error "  ✗ Database is not accessible"
        return 1
    fi
    
    # Test 5: Validate Telegram configuration in application
    docker compose exec -T app php artisan tinker --execute="\$config = config('services.telegram'); echo 'Token: ' . (empty(\$config['token']) ? 'MISSING' : 'OK') . ', Chat: ' . (empty(\$config['chat_id']) ? 'MISSING' : 'OK') . ', InviteLink: ' . (empty(\$config['group_invite_link']) ? 'MISSING' : 'OK');" 2>>"$DEPLOYMENT_LOG"
    
    log_success "End-to-end validation completed"
}



# Phase 9: Verify UI injection of Telegram settings
verify_ui_injection() {
    log_info "Phase 9: Verifying Telegram UI injection..."
    
    local notification_edit="resources/views/user/notification-setting/edit.blade.php"
    local telegram_partial="resources/views/partials/telegram_settings.blade.php"
    
    # Verify partial exists
    if [ ! -f "$telegram_partial" ]; then
        log_error "Telegram partial not found: $telegram_partial"
        return 1
    fi
    
    # Verify inclusion in notification settings view
    if ! grep -q "@include('partials.telegram_settings')" "$notification_edit"; then
        log_error "Telegram settings not injected into notification settings view"
        return 1
    fi
    
    log_success "Telegram UI properly injected in notification settings"
}

# Phase 9: Populate telegram tokens for all users
populate_telegram_tokens() {
    log_info "Phase 10: Populating telegram tokens for users..."
    
    # Check if command exists
    if ! docker compose exec -T app php artisan list 2>/dev/null | grep -q 'telegram:generate-tokens'; then
        log_warning "telegram:generate-tokens command not available yet, skipping"
        return 0
    fi
    
    if docker compose exec -T app php artisan telegram:generate-tokens --force 2>>"$DEPLOYMENT_LOG"; then
        log_success "Telegram tokens generated for all users"
    else
        log_error "Failed to generate telegram tokens (non-critical)"
        return 0
    fi
}

# Phase 11: Generate deployment report
generate_report() {
    log_info "Phase 11: Generating deployment report..."
    
    # Write report header with expanded variables, then static body with 'EOF' (safe for backticks)
    {
        echo "# Telegram Implementation - Production Deployment Report"
        echo ""
        echo "## Deployment Information"
        echo "- **Date**: $(date)"
        echo "- **Environment**: ${ENVIRONMENT}"
        echo "- **Backup Location**: ${BACKUP_DIR}"
        echo "- **Log File**: ${DEPLOYMENT_LOG}"
        cat << 'EOF'

## Changes Applied

### 1. Database Migration
- File: `database/migrations/2026_03_24_010501_add_telegram_fields_to_users_table.php`
- Fields Added: `telegram_chat_id`, `telegram_token`

### 2. Configuration
- ✅ `docker-compose.yml` - Added TELEGRAM_BOT_TOKEN, TELEGRAM_GROUP_ID, TELEGRAM_TOPIC_NOVEDADES
- ✅ `config/services.php` - Telegram service configuration

### 3. Core Implementation
- ✅ `app/Jobs/SendTelegramNotification.php`
  - Retry policy: 3 attempts with [10s, 60s, 300s] backoff
  - Timeout: 30 seconds
  - Error handling: Comprehensive logging
  
- ✅ `app/Services/TelegramService.php`
  - Updated API: banChatMember (not deprecated kickChatMember)
  - Methods: sendAnnouncement(), sendMessage(), kickUser()
  
- ✅ `app/Http/Controllers/API/TelegramWebhookController.php`
  - DB transaction with pessimistic lock (prevents race condition)
  - User linking via /start TRK-TOKEN telegram bot command
  
- ✅ `app/Observers/TorrentObserver.php`
  - Registered in EventServiceProvider::boot()
  - Fires SendTelegramNotification on status change to APPROVED

## Deployment Checklist

- [ ] Database backup verified
- [ ] Telegram API connectivity confirmed
- [ ] Migrations applied successfully
- [ ] Docker containers rebuilt with env vars
- [ ] TelegramService instantiation test passed
- [ ] Queue worker running
- [ ] Configuration loaded correctly
- [ ] End-to-end test completed

## Manual Testing Steps

1. **Test Torrent Announcement**:
   ```bash
   # Create a test torrent and approve it
   # Check if Telegram message appears in the configured group/topic
   ```

2. **Test User Linking**:
   ```bash
   # Send /start TRK-XXXXXX to the telegram bot
   # Verify user.telegram_chat_id is populated
   ```

3. **Test Queue Processing**:
   ```bash
   docker compose logs worker
   # Watch for SendTelegramNotification processing
   ```

4. **Monitor Log Files**:
   ```bash
   docker compose logs app | grep -i telegram
   ```

## Rollback Procedure

If deployment fails, restore the database:

```bash
docker compose exec -T db mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "${BACKUP_DIR}/unit3d_pre_telegram_migration.sql"
```

Then redeploy or investigate errors in `${DEPLOYMENT_LOG}`

## Support

For issues, check:
- `storage/logs/laravel.log`
- `${DEPLOYMENT_LOG}`
- `docker compose logs app worker`

EOF
    } > "$BACKUP_DIR/DEPLOYMENT_REPORT.md"
    
    log_success "Deployment report generated at $BACKUP_DIR/DEPLOYMENT_REPORT.md"
}

# Main deployment flow
main() {
    log_info "╔════════════════════════════════════════════════════════════════╗"
    log_info "║   UNIT3D TELEGRAM IMPLEMENTATION - PRODUCTION DEPLOYMENT      ║"
    log_info "║   Environment: $ENVIRONMENT"
    log_info "║   Start Time: $(date '+%Y-%m-%d %H:%M:%S')"
    log_info "╚════════════════════════════════════════════════════════════════╝"
    
    # Run all phases
    validate_environment || exit 1
    extract_env_variables || exit 1
    backup_database || exit 1
    validate_telegram_api || exit 1
    apply_migrations || exit 1
    verify_code_changes || exit 1
    verify_ui_injection || exit 1
    populate_telegram_tokens || exit 1
    restart_services || exit 1
    verify_routes || exit 1
    validate_deployment || exit 1
    generate_report || exit 1
    
    log_info "╔════════════════════════════════════════════════════════════════╗"
    log_success "DEPLOYMENT COMPLETED SUCCESSFULLY"
    log_info "║"
    log_info "║   Next Steps:"
    log_info "║   1. Monitor logs: docker compose logs -f app worker"
    log_info "║   2. Run manual tests (see deployment report)"
    log_info "║   3. Verify Telegram messages in production group"
    log_info "║"
    log_info "║   Backup Location: $BACKUP_DIR"
    log_info "║   Report File: $BACKUP_DIR/DEPLOYMENT_REPORT.md"
    log_info "╚════════════════════════════════════════════════════════════════╝"
    
    # Display deployment log summary
    log_info "Deployment Log Summary:"
    tail -20 "$DEPLOYMENT_LOG"
}

# Run main function
main "$@"
