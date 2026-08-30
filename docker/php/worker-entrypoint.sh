#!/bin/sh
set -e

cd /var/www/html

echo "[worker] waiting for app + MySQL + Symfony cache…"
i=0
until [ -f vendor/autoload.php ] \
  && { [ -d var/cache/dev ] || [ -d var/cache/prod ]; } \
  && php -r "exit(0);" 2>/dev/null; do
  i=$((i + 1))
  if [ "$i" -gt 120 ]; then
    echo "[worker] app not ready" >&2
    exit 1
  fi
  sleep 2
done

# Ensure messenger tables exist (doctrine transport, auto_setup=0)
php bin/console messenger:setup-transports --no-interaction 2>/dev/null || true

echo "[worker] consuming async (+ failed)…"
# --time-limit: recycle process periodically (memory leaks)
# supervisord/docker restart policy brings it back
exec php bin/console messenger:consume async failed \
  --time-limit=3600 \
  --memory-limit=256M \
  --sleep=1 \
  -vv
