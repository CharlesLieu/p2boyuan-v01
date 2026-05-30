# P2BOYUAN v0.1 Production Checklist

Use this checklist before putting the remote rehearsal demo online.

## 1. Server Runtime

- Confirm PHP version matches the backend requirements in `backend/composer.json`.
- Install PHP extensions required by Laravel and this app, including `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, and `ctype`.
- Install Composer.
- Install Node.js and npm for frontend build, or build frontend in CI and upload `dist`.
- Install MySQL 8.
- Install and configure Nginx with HTTPS.

## 2. Production `.env`

Create `backend/.env` from `backend/.env.example` and update:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.example`
- `FRONTEND_URL=https://your-domain.example`
- `SANCTUM_STATEFUL_DOMAINS=your-domain.example`
- `DB_CONNECTION=mysql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `FILESYSTEM_DISK=local`
- `LOG_LEVEL=warning` or another production-appropriate level

Generate the app key:

```bash
php artisan key:generate --force
```

Do not commit production `.env`.

## 3. MySQL

- Create the production MySQL database.
- Create a dedicated MySQL user with access only to this database.
- Confirm the server can connect from Laravel:

```bash
php artisan migrate:status
```

If this is the first demo deployment, run:

```bash
php artisan migrate --force
php artisan db:seed --force
```

For rehearsal reset on an existing demo environment, prefer the Super Admin reset function in the UI. Use `migrate:fresh --seed --force` only when intentionally wiping all demo data.

## 4. Backend Release Commands

From `backend/`:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Confirm writable paths:

- `backend/storage`
- `backend/bootstrap/cache`

The web server user must be able to write to these directories.

## 5. Frontend Build

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

## 6. Nginx And HTTPS

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

## 7. Admin Login

After deployment, log in with:

- Username: `admin001`
- Password: `123456`

Immediately confirm the Super Admin workspace can:

- Show demo accounts.
- Reset demo data.
- Manually adjust an application status for rehearsal recovery.

For a public demo, change the default password before sharing the URL outside the test group.

## 8. Complete Smoke Test

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

## 9. Local Verification Notes For This Task

Task 13 does not add, modify, or delete API endpoints or database schema. The v0.1 API interface document and data table design document do not require synchronization for this task.

In this local workspace, MySQL/Docker may be unavailable. If MySQL cannot be started locally, do not claim MySQL `migrate:fresh` was run. Use the SQLite in-memory automated test suite as local verification, then run MySQL migration and seed on the target server.
