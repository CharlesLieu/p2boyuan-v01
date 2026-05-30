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

docker compose pull mysql nginx || true
docker compose up -d --build
docker compose ps

echo "Containers started. Run ./scripts/init-demo.sh to reset and seed demo data."
