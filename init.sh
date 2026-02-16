#!/usr/bin/env bash
set -euo pipefail

if [ ! -f .env ]; then
  echo "❌ .env not found. Create it and set MAGENTO_PUBLIC_KEY / MAGENTO_PRIVATE_KEY."
  exit 1
fi
# shellcheck disable=SC1091
source .env

# Defaults (safe with `set -u`)
: "${CITYPAY_PLUGIN_DIR:=.}"
: "${ENABLE_NGROK:=1}"
: "${INSTALL_SAMPLE_DATA:=1}"   # <-- NEW: 1=install sample data; 0=skip
: "${FOLLOW_LOGS:=1}"           # 1=tail docker logs after init, 0=exit immediately
: "${LOG_SERVICES:=nginx app}"  # space-separated docker compose services
: "${NGROK_AUTHTOKEN:=}"
: "${NGROK_DOMAIN:=}"
: "${MAGENTO_HTTP_PORT:=8081}"   # nginx host port
: "${NGROK_URL:=}"
: "${NGROK_HOST:=}"

if [ ! -d "${CITYPAY_PLUGIN_DIR}" ]; then
  echo "❌ CITYPAY_PLUGIN_DIR does not exist: ${CITYPAY_PLUGIN_DIR}"
  exit 1
fi
if [ ! -f "${CITYPAY_PLUGIN_DIR}/registration.php" ]; then
  echo "⚠️  CITYPAY_PLUGIN_DIR does not look like a Magento module root: ${CITYPAY_PLUGIN_DIR}"
  echo "   Expected file not found: ${CITYPAY_PLUGIN_DIR}/registration.php"
fi
export CITYPAY_PLUGIN_DIR
echo "▶ CityPay plugin path: ${CITYPAY_PLUGIN_DIR}"

# Starts ngrok and returns immediately. If NGROK_DOMAIN is set, we export the URL right away.
get_ngrok_url() {
  set +u  # relax nounset locally in case caller didn't predefine variables
  local PORT="${MAGENTO_HTTP_PORT:-8081}"
  local TARGET="http://localhost:${PORT}"
  local MAX_MS="${NGROK_MAX_WAIT_MS:-8000}"  # total time budget to discover URL
  local STEP_MS=200                          # probe every 200ms (no heavy waiting)

  # Ensure ngrok exists on the host
  if ! command -v ngrok >/dev/null 2>&1; then
    echo "❌ ngrok not found on PATH (install on your HOST)."
    return 1
  fi

  # Optional auth (no-op if already configured)
  if [ -n "${NGROK_AUTHTOKEN:-}" ]; then
    ngrok config add-authtoken "$NGROK_AUTHTOKEN" >/dev/null 2>&1 || true
  fi

  # Start ngrok if a matching http tunnel isn't already running
  if pgrep -f "ngrok .*http .*${TARGET}" >/dev/null 2>&1 || pgrep -f "ngrok .*http .*(^|[^0-9])${PORT}($|[^0-9])" >/dev/null 2>&1; then
    echo "ℹ️  ngrok for ${TARGET} already running."
  else
    echo "🚀 Starting ngrok: ngrok http ${TARGET}"
    ngrok http "${TARGET}" >/dev/null 2>&1 &
  fi

  # Helper: query any agent API on 4040..4050 and return first https public_url
  _read_public_url_once() {
    local url=""
    for p in $(seq 4040 4050); do
      if curl -sf --max-time 0.5 "http://127.0.0.1:${p}/api/tunnels" >/dev/null 2>&1; then
        if command -v jq >/dev/null 2>&1; then
          url="$(curl -s "http://127.0.0.1:${p}/api/tunnels" \
            | jq -r '.tunnels[] | select(.public_url|startswith("https://")) | .public_url' \
            | head -n1)"
        else
          url="$(curl -s "http://127.0.0.1:${p}/api/tunnels" \
            | sed -n 's/.*"public_url":"\([^"]*https:[^"]*\)".*/\1/p' \
            | head -n1)"
        fi
        [ -n "$url" ] && { printf '%s\n' "$url"; return 0; }
      fi
    done
    return 1
  }

  # Probe for the URL up to MAX_MS (fast, lightweight; not “waiting forever”)
  local elapsed=0
  local url=""
  while [ "$elapsed" -lt "$MAX_MS" ]; do
    url="$(_read_public_url_once || true)"
    [ -n "$url" ] && break
    # sleep STEP_MS (portable 0.2s)
    perl -e 'select(undef,undef,undef,0.2);' 2>/dev/null || sleep 0.2
    elapsed=$((elapsed + STEP_MS))
  done

  if [ -z "$url" ]; then
    echo "❌ Failed to obtain ngrok https URL for ${TARGET} within $((MAX_MS/1000))s."
    echo "   Check if your ngrok account allows a session and no other agent is blocking it."
    return 1
  fi

  # Export NGROK_URL / NGROK_HOST for the caller
  NGROK_URL="${url%/}/"
  NGROK_HOST="$(printf '%s' "$NGROK_URL" | sed -E 's#^https?://([^/]+)/?.*#\1#')"
  export NGROK_URL NGROK_HOST

  echo "✅ ngrok: ${NGROK_URL}"
  set -u
  return 0
}
# -----------------------------------------------------------------------------
mkdir -p docker/nginx src

echo "▶ docker compose up..."
docker compose up -d

echo "⏳ wait for MySQL (db:3306)..."
until docker compose exec -T db mysqladmin ping -h"db" --silent; do sleep 2; done

echo "⏳ wait for OpenSearch (host:9200)..."
until curl -sf http://localhost:9200 >/dev/null; do sleep 3; done

echo "▶ composer create-project (as www-data)..."
docker compose exec -T -u www-data app bash -lc '
  set -e
  cd /var/www/html

  if [ -f composer.json ]; then
    echo "composer.json already present -> skipping create-project"
    exit 0
  fi

  export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=-1 COMPOSER_HOME=/tmp/composer
  AUTH_JSON=$(printf "{\"http-basic\":{\"repo.magento.com\":{\"username\":\"%s\",\"password\":\"%s\"}}}" "'"$MAGENTO_PUBLIC_KEY"'" "'"$MAGENTO_PRIVATE_KEY"'" )

  if [ -z "$(ls -A .)" ]; then
    echo "Directory is empty -> creating project in place"
    COMPOSER_AUTH="$AUTH_JSON" composer -n create-project --repository-url=https://repo.magento.com/ \
      magento/project-community-edition=2.4.8-p2 .
  else
    echo "Directory not empty -> creating in ./_new and merging"
    rm -rf _new
    COMPOSER_AUTH="$AUTH_JSON" composer -n create-project --repository-url=https://repo.magento.com/ \
      magento/project-community-edition=2.4.8-p2 ./_new
    (cd _new && tar cf - .) | tar xpf -
    rm -rf _new
  fi
'

echo "🛠  ensure DB & user exist (MySQL 8 syntax)..."
docker compose exec -T db sh -lc '
mysql -uroot -proot <<SQL
CREATE DATABASE IF NOT EXISTS magento CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS "magento"@"%" IDENTIFIED BY "magento";
GRANT ALL PRIVILEGES ON magento.* TO "magento"@"%";
FLUSH PRIVILEGES;
SQL
'

echo "🔍 check DB schema (flag table)"
DB_HAS_FLAG=$(docker compose exec -T db sh -lc \
  'mysql -uroot -proot -NBe "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"magento\" AND table_name=\"flag\";"' \
  || echo 0)

if [ "${DB_HAS_FLAG}" = "0" ]; then
  echo "⚠️  DB looks empty or missing schema; forcing fresh install (will remove app/etc/env.php)"
  docker compose exec -T -u www-data app bash -lc 'rm -f /var/www/html/app/etc/env.php'
fi

# Guard against stale env.php (wrong db host)
docker compose exec -T -u www-data app bash -lc '
cd /var/www/html
if [ -f app/etc/env.php ] && grep -q "magento-mysql" app/etc/env.php; then
  echo "Found stale DB host in env.php -> removing to reinstall..."
  rm -f app/etc/env.php
fi
'

echo "▶ magento install or configure..."
docker compose exec -T -u www-data app bash -lc "
set -e
cd /var/www/html

if [ ! -f app/etc/env.php ]; then
  php bin/magento setup:install \
    --base-url='${BASE_URL}' \
    --db-host=db --db-name=magento --db-user=magento --db-password=magento \
    --search-engine=opensearch --opensearch-host=opensearch --opensearch-port=9200 \
    --backend-frontname=admin \
    --admin-firstname='${ADMIN_FN}' --admin-lastname='${ADMIN_LN}' \
    --admin-email='${ADMIN_EMAIL}' \
    --admin-user='${ADMIN_USER}' --admin-password='${ADMIN_PASS}' \
    --language=en_GB --currency=GBP --timezone=Europe/London \
    --use-rewrites=1
else
  echo 'Magento already installed – skipping setup:install'
fi

# dev-friendly toggles
php bin/magento deploy:mode:set developer -s
php bin/magento module:disable Magento_TwoFactorAuth Magento_AdminAdobeImsTwoFactorAuth || true
php bin/magento config:set web/secure/use_in_frontend 0
php bin/magento config:set web/secure/use_in_adminhtml 0
php bin/magento config:set dev/static/sign 1
php bin/magento config:set dev/template/allow_symlink 1

# --- SAMPLE DATA (optional) --------------------------------------------------
if [ \"${INSTALL_SAMPLE_DATA}\" = \"1\" ]; then
  echo '▶ installing sample data...'
  export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=-1 COMPOSER_HOME=/tmp/composer
  composer config -g http-basic.repo.magento.com \"${MAGENTO_PUBLIC_KEY}\" \"${MAGENTO_PRIVATE_KEY}\"
  php -dmemory_limit=2G bin/magento sampledata:deploy --no-interaction
  php -dmemory_limit=2G bin/magento setup:upgrade
  # generate thumbnails (product images)
  php bin/magento catalog:images:resize || true
  php bin/magento cache:flush
else
  echo '▶ skipping sample data (INSTALL_SAMPLE_DATA!=1)'
fi
# ---------------------------------------------------------------------------

# developer mode static strategy:
# do not run static-content:deploy, so static assets are generated/symlinked dynamically.
rm -rf var/view_preprocessed/*
find pub/static -mindepth 1 -maxdepth 1 ! -name '.htaccess' -exec rm -rf {} +
php bin/magento cache:clean

if [ -d app/code/CityPay/Paylink ]; then
  if php bin/magento module:status CityPay_Paylink 2>/dev/null | grep -q 'Module is enabled'; then
    echo 'CityPay_Paylink already enabled.'
  else
    echo '▶ enabling CityPay_Paylink...'
    php bin/magento module:enable CityPay_Paylink
  fi

  echo '▶ applying CityPay_Paylink setup updates...'
  php bin/magento setup:upgrade --keep-generated
  php bin/magento cache:flush
else
  echo '⚠️ app/code/CityPay/Paylink not found; skipping CityPay_Paylink enable.'
fi
"

# --- NGROK (optional) --------------------------------------------------------
if [ "${ENABLE_NGROK:-0}" = "1" ]; then
  echo "▶ starting ngrok for http://localhost:${MAGENTO_HTTP_PORT:-8081} and capturing URL ..."
  if get_ngrok_url; then
    # safe under set -u
    echo "▶ setting Magento base URLs to ${NGROK_URL} ..."
    docker compose exec -T -u www-data app bash -lc "
      set -e
      cd /var/www/html
      bin/magento config:set web/unsecure/base_url  '${NGROK_URL}'
      bin/magento config:set web/secure/base_url    '${NGROK_URL}'
      bin/magento config:set web/secure/use_in_frontend 1
      bin/magento config:set web/secure/use_in_adminhtml 1
      bin/magento config:set web/secure/offloader_header 'X-Forwarded-Proto'
      bin/magento config:set web/cookie/cookie_domain '${NGROK_HOST}'
      bin/magento cache:flush
    "
    PUBLIC_URL="$NGROK_URL"
  else
    echo "⏭️  Could not capture ngrok URL right now; leaving Magento at BASE_URL."
  fi
fi

PUBLIC_URL="${PUBLIC_URL:-${BASE_URL%/}/}"
echo "✅ Done. Store: ${PUBLIC_URL} | Admin: ${PUBLIC_URL}admin (user: ${ADMIN_USER} / pass: ${ADMIN_PASS})"

if [ "${FOLLOW_LOGS}" = "1" ]; then
  echo "▶ streaming logs (Ctrl+C to stop viewing logs; containers keep running)..."
  # shellcheck disable=SC2086
  docker compose logs -f --tail=200 ${LOG_SERVICES}
else
  echo "ℹ️  Logs are not attached (FOLLOW_LOGS=${FOLLOW_LOGS})."
  echo "   Run manually: docker compose logs -f --tail=200 ${LOG_SERVICES}"
fi
# ---------------------------------------------------------------------------
