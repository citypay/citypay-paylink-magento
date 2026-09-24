#!/bin/sh
set -e

# Start ngrok only when explicitly enabled
if [ "${ENABLE_NGROK:-0}" = "1" ] && [ -n "${NGROK_AUTHTOKEN:-}" ]; then
  echo "==> Starting ngrok"
  ngrok config add-authtoken "$NGROK_AUTHTOKEN" >/dev/null 2>&1 || true

  # v3 config (kept here for when you re-enable)
  if [ -n "${NGROK_DOMAIN:-}" ]; then
    cat > /etc/ngrok.yml <<YAML_WITH_DOMAIN
version: "3"
agent:
  web_addr: 0.0.0.0:4040
tunnels:
  magento:
    proto: http
    addr: 127.0.0.1:80
    domain: "${NGROK_DOMAIN}"
    host_header: rewrite
YAML_WITH_DOMAIN
  else
    cat > /etc/ngrok.yml <<'YAML'
version: "3"
agent:
  web_addr: 0.0.0.0:4040
tunnels:
  magento:
    proto: http
    addr: 127.0.0.1:80
    host_header: rewrite
YAML
  fi

  ngrok start --config /etc/ngrok.yml --all >/var/log/ngrok.log 2>&1 &
else
  echo "Skipping ngrok (ENABLE_NGROK!=1 or no token)"
fi

# Launch nginx in the foreground
exec nginx -g 'daemon off;'