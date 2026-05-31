# P2BOYUAN v0.1 Production Checklist

Use this checklist before putting the remote rehearsal demo online. The preferred quick demo path is Docker Compose from the project root. Manual Linux/Nginx/PHP-FPM deployment remains available as a reference path.

## 1. Server And Network

- Use Ubuntu 22.04 LTS or Ubuntu 24.04 LTS.
- Use at least 2 CPU cores and 4 GB RAM for a smooth rehearsal demo.
- Open inbound port `80` in the cloud security group and server firewall.
- For public production, also plan port `443`, a domain name, HTTPS certificates, and firewall restrictions.

Install Docker and Git:

```bash
sudo apt update
sudo apt install -y ca-certificates curl git
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
docker --version
docker compose version
```

## 2. Docker Compose Deployment Path

Clone the repository and create env files:

```bash
git clone <YOUR_GITHUB_REPO_URL>
cd p2boyuan-v01
cp .env.deploy.example .env
cp backend/.env.docker.example backend/.env.docker
```

Generate a Laravel `APP_KEY` without depending on host PHP, Composer, or backend `vendor` files:

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Update root `.env`:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=http://YOUR_SERVER_IP` for the fast demo, or `https://your-domain.example` after HTTPS is configured
- `FRONTEND_URL=http://YOUR_SERVER_IP` for the fast demo, or `https://your-domain.example` after HTTPS is configured
- `MYSQL_DATABASE=p2boyuan_v01`
- `MYSQL_USER=p2boyuan`
- `MYSQL_PASSWORD=<strong password>`
- `MYSQL_ROOT_PASSWORD=<strong root password>`
- `BACKEND_APP_KEY=base64:<generated value>`

Keep `backend/.env.docker` as the Docker container env file. It reads the root `.env` values through Docker Compose.

Start and initialize:

```bash
./scripts/deploy-server.sh
./scripts/init-demo.sh
```

Open `http://YOUR_SERVER_IP`.

Do not commit `.env` or `backend/.env.docker`.

## 3. Docker MySQL Volume And Backup

Confirm the MySQL service is healthy:

```bash
docker compose ps
```

Back up the Docker MySQL volume before destructive maintenance or before refreshing a demo that contains useful tester notes:

```bash
mkdir -p backups
set -a
. ./.env
set +a
docker compose exec -T mysql mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" > "backups/p2boyuan_$(date +%Y%m%d_%H%M%S).sql"
```

For rehearsal reset on an existing demo environment, prefer the Super Admin reset function in the UI. Use `./scripts/init-demo.sh` only when intentionally applying the scripted demo initialization path. Confirm whether it wipes or reseeds data before running it on a shared server.

## 4. HTTPS And Domain Follow-Up

- Point a domain such as `demo.example.com` to the server public IP.
- Configure HTTPS before public production use.
- Replace plain `http://YOUR_SERVER_IP` values with `https://demo.example.com` in `.env`.
- Recreate/reload containers after env changes:

```bash
./scripts/deploy-server.sh
```

- The Docker demo Nginx config lives at `docker/nginx/default.conf`.
- `deploy/nginx.conf` is a reference for manual Linux/Nginx/PHP-FPM deployments.
- For public production, restrict SSH, keep only required ports open, rotate demo passwords, and document who has server access.

## 5. Manual Linux/Nginx Reference Path

If you are not using Docker Compose, prepare the server manually:

- Confirm PHP version matches the backend requirements in `backend/composer.json`.
- Install PHP extensions required by Laravel and this app, including `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, and `ctype`.
- Install Composer.
- Install Node.js and npm for frontend build, or build frontend in CI and upload `dist`.
- Install MySQL 8.
- Install and configure Nginx with HTTPS.

Create `backend/.env` from `backend/.env.example`, then set production values and generate the key with `php artisan key:generate --force` on that server.

From `backend/`:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Confirm writable paths:

- `backend/storage`
- `backend/bootstrap/cache`

The web server user must be able to write to these directories.

Create `frontend/.env`:

```bash
VITE_API_BASE_URL=https://your-domain.example/api/v1
```

Build:

```bash
cd frontend
npm install
npm run build
```

Deploy `frontend/dist` to the path configured in Nginx, for example `/var/www/p2boyuan/frontend/dist`.

For manual Nginx:

- Copy or adapt `deploy/nginx.conf`.
- Replace `demo.example.com` with the real domain.
- Replace `/var/www/p2boyuan/frontend/dist` and `/var/www/p2boyuan/backend` with real server paths.
- Replace the PHP-FPM socket, for example `unix:/run/php/php8.3-fpm.sock`, if your server uses another PHP version or TCP upstream.
- Configure valid HTTPS certificates.
- Test Nginx configuration:

```bash
nginx -t
systemctl reload nginx
```

## 6. Demo Account Safety

After deployment, log in with:

- Username: `admin001`
- Password: `123456`

Immediately confirm the Super Admin workspace can:

- Show demo accounts.
- Reset demo data.
- Manually adjust an application status for rehearsal recovery.

All seeded demo accounts use password `123456`. This weak password is only for private rehearsal. For a public demo or production-like environment, change default passwords, use HTTPS, set strong MySQL passwords, and restrict access with firewall rules where possible.

## 7. Complete Smoke Test

Run this role-play test from the deployed URL:

1. Store logs in as `store001 / 123456`.
2. Store creates a new application.
3. Auditor logs in as `audit001 / 123456`.
4. Auditor assigns an active sales agent.
5. Sales agent logs in as `sales001 / 123456`.
6. Sales agent starts inspection and submits inspection information.
7. Auditor approves the application.
8. Cashier logs in as `cashier001 / 123456`.
9. Cashier confirms payout and records voucher information.
10. Store checks final status and payout information.
11. Super Admin logs in as `admin001 / 123456` and resets demo data.

Also test the supplement branch:

1. Auditor requests supplement materials from store or sales.
2. Assigned owner submits supplement materials.
3. Auditor continues review.

Technical smoke checks:

- `docker compose ps` shows frontend/Nginx, backend, and MySQL services running.
- `http://YOUR_SERVER_IP` loads the login page.
- Login works for `admin001`, `audit001`, `sales001`, `cashier001`, and `store001`.
- API calls from the browser return success responses instead of CORS, `500`, or `502` errors.
- Demo reset works from the Super Admin workspace.
- Uploaded supplement or voucher files, if tested, remain accessible through the deployed app.

## 8. Local Verification Notes For This Task

This documentation task does not add, modify, or delete API endpoints or database schema. The v0.1 API interface document and data table design document do not require synchronization for this task.

In this local workspace, MySQL/Docker may be unavailable. If MySQL cannot be started locally, do not claim MySQL `migrate:fresh` was run. Use the SQLite in-memory automated test suite as local verification, then run MySQL migration and seed on the target server.
