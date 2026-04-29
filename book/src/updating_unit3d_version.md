# Updating UNIT3D

Update UNIT3D to the latest version by reviewing the release notes and following the steps below:

## 1. Create backup

UNIT3D offers built-in backups. Refer to the [Backups documentation](./backups.md) for usage.

> [!IMPORTANT]
> Ensure there is a complete backup before proceeding.

## 2. Enter maintenance mode

```bash
cd /var/www/html
php artisan down
```

## 3. Update UNIT3D

<video controls width="640" height="360">
  <source src="./assets/update_unit3d.webm" type="video/webm">
  Your browser does not support the video tag.
</video>

> [!NOTE]
> Before running the update, review the new release’s minimum requirements to ensure the environment meets them.

1. **Proceed to update:**

   The updater will fetch the latest commits from the upstream repository and stage them for installation.

   ```bash
   cd /var/www/html
   php artisan git:update
   ```

   There will be a prompt to confirm each step; choose `yes` to overwrite with the new version.

   ```bash
   Start the update process (yes/no) [yes]:
   > yes
   ```

2. **Accept upstream files:**

   When prompted for each changed file, type `yes` to overwrite the local copy or press `Enter` to accept the default shown in brackets.

   ```bash
   Update config/unit3d.php (yes/no) [yes]:
   > yes

   git checkout origin/master -- config/unit3d.php
   [============================] (Done!)
   ```

3. **Run new migrations:**

   ```bash
   Run new migrations (php artisan migrate) (yes/no) [yes]:
   > yes
   ```

4. **Install new packages:**

   ```bash
   Install new packages (composer install) (yes/no) [yes]:
   > yes
   ```

5. **Compile assets:**

   ```bash
   Compile assets (bun run build) (yes/no) [yes]:
   > yes
   ```

## Troubleshooting clean-up

The following commands are **optional** and should be run only as needed to resolve specific errors:

- **Finish any migrations not completed:**

    ```sh
    sudo php artisan migrate
    ```

- **Reinstall dependencies:**

    ```sh
    composer install --prefer-dist --no-dev -o
    ```

- **Clear caches:**

    ```sh
    sudo php artisan cache:clear && \
    sudo php artisan queue:clear && \
    sudo php artisan auto:email-blacklist-update && \
    sudo php artisan auto:cache_random_media && \
    sudo php artisan set:all_cache
    ```

- **Rebuild static assets:**

    ```sh
    sudo bun install && sudo bun run build
    ```

- **Restart services:**

    ```sh
    sudo systemctl restart php8.5-fpm && \
    sudo php artisan queue:restart && \
    sudo php artisan up
    ```

- **If running external UNIT3D-Announce, restart the supervisor services:**

    ```sh
    sudo supervisorctl reread && \
    sudo supervisorctl update && \
    sudo supervisorctl reload
    ```
