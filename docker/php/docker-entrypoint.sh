#!/bin/sh
set -e

cd /var/www/html

echo "[entrypoint] waiting for database ${DB_HOST:-mysql}:3306 …"
i=0
until php -r "try { new PDO('mysql:host=${DB_HOST:-mysql};port=3306', getenv('MYSQL_USER') ?: 'okapi', getenv('MYSQL_PASSWORD') ?: ''); exit(0);} catch (Throwable \$e) { exit(1); }" 2>/dev/null; do
  i=$((i + 1))
  if [ "$i" -gt 60 ]; then
    echo "[entrypoint] database not reachable" >&2
    exit 1
  fi
  sleep 1
done
echo "[entrypoint] mysql is up"

mkdir -p config/jwt var/cache var/log var/share public/media

if [ ! -f config/jwt/private.pem ]; then
  echo "[entrypoint] generating JWT keypair…"
  openssl genrsa -out config/jwt/private.pem 2048
  openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem
fi

if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] composer install…"
  if [ "${APP_ENV}" = "prod" ]; then
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
  else
    composer install --no-interaction --prefer-dist --no-scripts
  fi
fi

# Run Symfony scripts once deps are present
composer dump-autoload -o --no-interaction 2>/dev/null || true

if [ -n "${APP_SECRET:-}" ]; then
  echo "[entrypoint] doctrine migrate + cache…"
  php bin/console doctrine:database:create --if-not-exists --no-interaction 2>/dev/null || true
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
  php bin/console assets:install public --no-interaction 2>/dev/null || true
  php bin/console cache:clear --no-interaction || true
fi

chown -R www-data:www-data var public/media config/jwt 2>/dev/null || true
chmod -R ug+rwX var public/media 2>/dev/null || true

echo "[entrypoint] starting $*"
exec docker-php-entrypoint "$@"
