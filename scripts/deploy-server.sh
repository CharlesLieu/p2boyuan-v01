#!/usr/bin/env sh
set -eu

if [ ! -f .env ]; then
  echo "Missing root .env. Copy .env.deploy.example to .env and edit passwords first." >&2
  exit 1
fi

if [ ! -f backend/.env.docker ]; then
  echo "Missing backend/.env.docker. Copy backend/.env.docker.example and edit it first." >&2
  exit 1
fi

get_env_value() {
  awk -F= -v key="$1" '
    $0 !~ /^[[:space:]]*#/ && $1 == key {
      value = substr($0, index($0, "=") + 1)
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
      gsub(/^["'\'']|["'\'']$/, "", value)
      print value
      exit
    }
  ' .env
}

reject_placeholder() {
  name="$1"
  value="$2"
  placeholder="$3"

  if [ -z "$value" ]; then
    echo "ERROR: $name is empty in root .env." >&2
    exit 1
  fi

  if [ "$value" = "$placeholder" ]; then
    echo "ERROR: $name still uses placeholder value '$placeholder'. Update root .env before deployment." >&2
    exit 1
  fi

  case "$value" in
    *change_me*|*CHANGE_ME*)
      echo "ERROR: $name appears to be a placeholder. Update root .env before deployment." >&2
      exit 1
      ;;
  esac
}

reject_app_key() {
  value="$1"

  if [ -z "$value" ]; then
    echo "ERROR: BACKEND_APP_KEY is empty in root .env. Generate a Laravel APP_KEY and set BACKEND_APP_KEY=base64:..." >&2
    exit 1
  fi

  case "$value" in
    *change_me*|*CHANGE_ME*|"<generated value>"|"base64:<generated value>"|YOUR*|your*|base64:YOUR*|base64:your*)
      echo "ERROR: BACKEND_APP_KEY appears to be a placeholder. Generate a real Laravel APP_KEY before deployment." >&2
      exit 1
      ;;
  esac
}

mysql_password="$(get_env_value MYSQL_PASSWORD)"
mysql_root_password="$(get_env_value MYSQL_ROOT_PASSWORD)"
backend_app_key="$(get_env_value BACKEND_APP_KEY)"

reject_placeholder "MYSQL_PASSWORD" "$mysql_password" "change_me_mysql_password"
reject_placeholder "MYSQL_ROOT_PASSWORD" "$mysql_root_password" "change_me_root_password"
reject_app_key "$backend_app_key"

docker compose pull mysql nginx || true
docker compose up -d --build
docker compose ps

echo "Containers started. Run ./scripts/init-demo.sh to reset and seed demo data."
