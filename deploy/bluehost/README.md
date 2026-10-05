# Deploying ViralDose on Bluehost (shared hosting, primary domain)

On Bluehost the primary domain always serves `~/public_html`; you cannot point it at Laravel's `public/`
folder. Two supported layouts – **A is recommended**.

## A. App outside the web root (recommended)

```
/home/USER/viraldose/      ← the whole repository (not reachable from the web)
/home/USER/public_html/    ← only: index.php (bridge), .htaccess, .user.ini, build/, favicon, storage (symlink)
```

1. cPanel → **MultiPHP Manager** → set the domain to **PHP 8.3** (8.2 minimum).
2. cPanel → **MySQL Databases** → create a database + user, grant all privileges.
3. cPanel → **Terminal** (or SSH):
   ```bash
   cd ~ && git clone https://github.com/PranayReddy10/viraldose.git viraldose
   bash ~/viraldose/deploy/bluehost/deploy.sh      # first run creates .env and stops
   nano ~/viraldose/.env                            # APP_URL=https://viraldose.in, APP_ENV=production, APP_DEBUG=false, DB_*
   bash ~/viraldose/deploy/bluehost/deploy.sh      # installs, migrates, publishes public_html, caches
   ```
   No git on the host? Upload a zip of the repo through File Manager into `~/viraldose` and run the same script.
   No composer? The script downloads `composer.phar` automatically. If `php` is not on PATH use
   `PHP=/opt/cpanel/ea-php83/root/usr/bin/php bash deploy.sh`.
4. cPanel → **Cron Jobs** → add (every minute):
   `* * * * * cd /home/USER/viraldose && php artisan schedule:run >> /dev/null 2>&1`
5. cPanel → **SSL/TLS Status** → run AutoSSL, then in `~/public_html/.htaccess` uncomment the HTTPS +
   non-www redirect block.
6. Log in at `https://viraldose.in/admin` (credentials from `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env`).

Re-deploying later: `bash ~/viraldose/deploy/bluehost/deploy.sh` (pulls, installs, migrates, re-publishes).

What the bridge does: `public_html/index.php` loads `../viraldose/vendor/autoload.php`, boots the app and
calls `$app->usePublicPath(__DIR__)` so assets, the Vite manifest and the `storage` symlink are looked up in
`public_html`. `storage:link` is run with `APP_PUBLIC_PATH=~/public_html` so the symlink is created there.

## B. Whole project inside public_html (quick, no SSH needed)

Upload/clone the repository **into** `~/public_html` itself. The repository root contains a router
`.htaccess` that:

- rewrites every request into `public/` (Laravel's real front controller),
- 301-redirects `/public/...` URLs back to the clean URL,
- blocks `.env`, `vendor/`, `storage/`, `app/`, etc. from the web.

Then run (Terminal) `cd ~/public_html && composer install --no-dev && cp .env.example .env && php artisan
key:generate && php artisan migrate --force --seed && php artisan storage:link && php artisan optimize`.

Layout A keeps secrets physically outside the web root, which is why it is preferred; B relies on the
`.htaccess` deny rules.

## Common Bluehost issues

| Symptom | Fix |
|---|---|
| 500 error right after upload | `storage/` and `bootstrap/cache/` must be writable: `chmod -R 775 storage bootstrap/cache`. Check `storage/logs/laravel.log`. |
| Images uploaded in admin don't show | Run `APP_PUBLIC_PATH=~/public_html php artisan storage:link --force` (layout A) or `php artisan storage:link` (B). |
| CSS missing / unstyled | `~/public_html/build/manifest.json` must exist – re-run `deploy.sh` (layout A). |
| “Upload too large” for reels | `.user.ini` sets 100 MB; also raise limits in cPanel → MultiPHP INI Editor. |
| Composer memory error | `COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev`. |
| Wrong PHP version in Terminal | `PHP=/opt/cpanel/ea-php83/root/usr/bin/php bash deploy.sh` |
| Scheduled posts / feeds not running | The cron job in step 4 is missing. |
| Emails | Set `MAIL_MAILER=smtp` with Bluehost SMTP (`mail.yourdomain`, port 465) in `.env`. |
