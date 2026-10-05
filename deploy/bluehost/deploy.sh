#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# ViralDose deploy script for Bluehost / cPanel (run over SSH).
#   bash ~/viraldose/deploy/bluehost/deploy.sh
# Layout: app in ~/viraldose, web root ~/public_html (primary domain).
# ---------------------------------------------------------------------------
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/viraldose}"
WEB_ROOT="${WEB_ROOT:-$HOME/public_html}"
PHP="${PHP:-php}"           # Bluehost: usually "php" (set version in cPanel → MultiPHP Manager → 8.3)
COMPOSER="${COMPOSER:-}"    # auto-detected below

cd "$APP_DIR"

echo "==> Pulling latest code"
if [ -d .git ]; then git pull --ff-only; fi

echo "==> Installing PHP dependencies"
if [ -z "$COMPOSER" ]; then
  if command -v composer >/dev/null 2>&1; then COMPOSER="composer";
  elif [ -f "$HOME/composer.phar" ]; then COMPOSER="$PHP $HOME/composer.phar";
  else
    echo "    composer not found – downloading composer.phar to \$HOME"
    $PHP -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    $PHP composer-setup.php --install-dir="$HOME" --filename=composer.phar --quiet
    rm -f composer-setup.php
    COMPOSER="$PHP $HOME/composer.phar"
  fi
fi
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ ! -f .env ]; then
  cp .env.example .env
  $PHP artisan key:generate --force
  echo "    .env created – edit it (DB_*, APP_URL) and run this script again."
  exit 0
fi

echo "==> Publishing the public folder into $WEB_ROOT"
mkdir -p "$WEB_ROOT"
# Static assets (compiled CSS/JS, favicon, .htaccess, .user.ini) – never the index.php
rm -rf "$WEB_ROOT/build" && cp -R "$APP_DIR/public/build" "$WEB_ROOT/build"
cp -f "$APP_DIR/public/favicon.svg" "$WEB_ROOT/favicon.svg"
cp -f "$APP_DIR/public/favicon.ico" "$WEB_ROOT/favicon.ico" 2>/dev/null || true
cp -f "$APP_DIR/deploy/bluehost/public_html/.htaccess" "$WEB_ROOT/.htaccess"
cp -f "$APP_DIR/deploy/bluehost/public_html/.user.ini" "$WEB_ROOT/.user.ini"
cp -f "$APP_DIR/deploy/bluehost/public_html/index.php" "$WEB_ROOT/index.php"
# Point the bridge at the real app folder
sed -i "s#__DIR__.'/../viraldose'#'$APP_DIR'#" "$WEB_ROOT/index.php"

echo "==> Database & caches"
$PHP artisan migrate --force
$PHP artisan db:seed --force
APP_PUBLIC_PATH="$WEB_ROOT" $PHP artisan storage:link --force
$PHP artisan optimize:clear
$PHP artisan optimize

chmod -R ug+rwX storage bootstrap/cache

echo
echo "Done. Add this cron job in cPanel → Cron Jobs (every minute):"
echo "  * * * * * cd $APP_DIR && $PHP artisan schedule:run >> /dev/null 2>&1"
