# ViralDose on Hostinger shared hosting

Hostinger serves the domain from `~/domains/viraldose.in/public_html`. The project is installed **inside**
that folder (layout B): the repository-root `.htaccess` routes every request into `public/` and blocks
application files from the web.

## Updating the code

**With Git (hPanel → Websites → Manage → Advanced → Git):** connect `https://github.com/PranayReddy10/viraldose`
(branch `main`) to `domains/viraldose.in/public_html`, then press **Deploy** whenever there is an update.
Afterwards run the post-update commands below.

**Over SSH** (hPanel → Advanced → SSH Access → enable; `ssh -p 65002 uXXXXXXXX@YOUR-IP`):
```bash
cd ~/domains/viraldose.in/public_html
git pull                      # or re-upload the zip and extract over the folder
composer install --no-dev --optimize-autoloader   # only needed when composer.json changed
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```
If `composer` is missing: `php -r "copy('https://getcomposer.org/installer','c.php');" && php c.php && php composer.phar install --no-dev`.

## Importing the old posts (posts.sql)

Do **not** import `posts.sql` into the new database with phpMyAdmin – the tables are different. Use one of:

1. **Browser (no SSH):** Admin → *Import old posts* → upload `posts.sql` → Dry run → Import (keep
   "Download images" ticked). The result and counts are shown on the page.
2. **SSH:** upload `posts.sql` to your home folder, then
   `php artisan import:varient-sql ~/posts.sql --dry-run` and
   `php artisan import:varient-sql ~/posts.sql --download-images`.

Both are safe to repeat; posts are matched by their old id. Rows that fail are listed at the end and the
rest still import.

**Images:** old posts mostly hot-link pictures from other news sites, which often block that. Step 3 on the
Import page (“Download next 15”) copies them into your own storage; the cron job also does it automatically
(`images:fetch-remote`, every 5 minutes) once the cron below is set up. Failed ones show a reason and can be
retried; a failed image leaves the remote link in place.

## Cron (hPanel → Advanced → Cron Jobs)

Every minute: `cd /home/uXXXXXXXX/domains/viraldose.in/public_html && php artisan schedule:run >> /dev/null 2>&1`

## PHP

hPanel → Advanced → PHP Configuration: PHP 8.3, and raise `upload_max_filesize` / `post_max_size` to 100M /
110M, `max_execution_time` 300 (the bundled `public/.user.ini` sets the same for the app).

## Checks

- `https://viraldose.in/robots.txt` must show the ViralDose rules (dynamic), `https://viraldose.in/.env` must be 403.
- Uploaded images missing → `php artisan storage:link` (creates `public/storage`).
- 500 after an update → `php artisan optimize:clear`, check `storage/logs/laravel.log`, `chmod -R 775 storage bootstrap/cache`.
