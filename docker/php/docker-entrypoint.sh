#!/bin/sh
set -e

cd /var/www/html

DB_HOST="${DB_HOST:-mysql}"
echo "[entrypoint] waiting for MySQL at ${DB_HOST}:3306 …"

i=0
until mysqladmin ping -h"$DB_HOST" -uroot -p"${MYSQL_ROOT_PASSWORD:-root}" --silent 2>/dev/null \
   || mysqladmin ping -h"$DB_HOST" -u"${MYSQL_USER:-okapi}" -p"${MYSQL_PASSWORD:-okapi}" --silent 2>/dev/null; do
  i=$((i + 1))
  if [ "$i" -gt 90 ]; then
    echo "[entrypoint] MySQL not reachable after 90s" >&2
    exit 1
  fi
  sleep 1
done
echo "[entrypoint] MySQL is up"

mkdir -p config/jwt var/cache var/log var/share public/media public/bundles

if [ ! -f config/jwt/private.pem ]; then
  echo "[entrypoint] generating JWT keypair (no passphrase)…"
  openssl genrsa -out config/jwt/private.pem 2048
  openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem
  chmod 644 config/jwt/*.pem
fi

if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] composer install…"
  if [ "${APP_ENV}" = "prod" ]; then
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
  else
    composer install --no-interaction --prefer-dist --no-scripts
  fi
fi

composer dump-autoload -o --no-interaction 2>/dev/null || true

if [ -n "${APP_SECRET:-}" ] && [ -n "${DATABASE_URL:-}" ]; then
  echo "[entrypoint] doctrine migrate + messenger + cache (MySQL)…"
  php bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null || true
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
  php bin/console messenger:setup-transports --no-interaction 2>/dev/null || true
  php bin/console assets:install public --no-interaction 2>/dev/null || true
  # Compile Asset Mapper so /assets/* exist on disk (Swagger / API Platform UI)
  php bin/console asset-map:compile --no-interaction 2>/dev/null || true
  # Warm only — never cache:clear here (races with worker + deploy composer scripts → var/cache/de_)
  php bin/console cache:warmup --no-interaction 2>/dev/null || true
fi

chown -R www-data:www-data var public/media config/jwt 2>/dev/null || true
chmod -R ug+rwX var public/media 2>/dev/null || true

echo "[entrypoint] starting $*"
exec docker-php-entrypoint "$@"
