# P2BOYUAN v0.1 Remote Rehearsal MVP

This repository is the deployable v0.1 demo for the remote rehearsal flow. It lets testers log in as different roles and walk through the closed loop from store submission, inspection assignment, audit decision, payout confirmation, and super admin reset.

## Stack

- Frontend: Vue 3 + Vite + TypeScript + Element Plus + Pinia
- Backend: Laravel API + Sanctum bearer tokens
- Database: MySQL 8
- Local Docker stack: Nginx, frontend, backend, and MySQL via Docker Compose

## v0.1 Scope

Included:

- Store creates applications and submits supplement materials.
- Auditor assigns sales agents, approves, rejects, or requests supplement materials.
- Sales agent starts inspection, submits inspection, rejects inspection for supplement, and submits supplement materials when assigned.
- Cashier confirms payout and records payout voucher information.
- Super admin views demo accounts, resets demo data, and manually adjusts demo status for rehearsal recovery.
- Unified API response format, role permissions, status logs, and seeded demo data.

Not included in v0.1:

- Real SMS, payment, banking, OCR, push notification, or file CDN integration.
- Production identity verification or real customer data onboarding.
- Production-grade UI design system beyond the roadshow-ready rehearsal interface.
- MySQL migration verification in this local workspace when Docker/MySQL is unavailable. SQLite-backed automated tests cover the schema and flow logic.

## Local Startup

### 1. Prepare environment files

Backend:

```powershell
cd backend
copy .env.example .env
php artisan key:generate
```

Frontend:

```powershell
cd frontend
copy .env.example .env
```

Root `.env.example` mirrors the Docker Compose variables used by the full local app stack. For local Docker, copy it to `.env` and set `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`, and `BACKEND_APP_KEY` before starting containers.

### 2. Start the local Docker app stack

```powershell
copy .env.example .env
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Edit root `.env`:

- Set `MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD` to local-only non-placeholder values.
- Paste the generated key into `BACKEND_APP_KEY=base64:...`.

Then start the full stack:

```powershell
docker compose up -d --build
```

Open `http://localhost`.

The Docker MySQL service is internal to the Compose network and is not exposed on host port `3307`. For host-based Laravel development, use your own MySQL service or explicitly add a local-only port mapping before pointing `backend/.env` at it.

### 3. Install dependencies

If you prefer host-based development instead of the Docker app stack, install dependencies and run Laravel/Vite directly:

Backend:

```powershell
cd backend
composer install
```

Frontend:

```powershell
cd frontend
npm install
```

### 4. Migrate and seed demo data

```powershell
cd backend
php artisan migrate:fresh --seed
```

### 5. Start services

Backend API:

```powershell
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

Frontend:

```powershell
cd frontend
npm run dev -- --host 0.0.0.0
```

Open `http://localhost:5173`.

## Fast Remote Demo Deployment

Use this path when you want a tester-accessible rehearsal demo on a fresh Ubuntu server with Docker Compose. The flow is: buy a server, upload this repository to GitHub, clone it on the server, copy env files, fill passwords and `APP_KEY`, start Docker Compose, initialize demo data, and share the server URL with testers.

### 1. Buy and prepare a server

Minimum recommendation:

- Ubuntu 22.04 LTS or Ubuntu 24.04 LTS
- 2 CPU cores and 4 GB RAM
- Public IPv4 address
- Port `80` open in the cloud security group and server firewall

Log in with SSH, then install Docker:

```bash
sudo apt update
sudo apt install -y ca-certificates curl git
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
docker --version
docker compose version
```

### 2. Upload and clone from GitHub

Push this project to your own GitHub repository, then clone it on the server:

```bash
git clone <YOUR_GITHUB_REPO_URL>
cd p2boyuan-v01
cp .env.deploy.example .env
cp backend/.env.docker.example backend/.env.docker
```

### 3. Fill production demo values

Generate a Laravel `APP_KEY` without needing local PHP, Composer, or backend `vendor` dependencies:

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Edit the root `.env` file:

- Set `APP_URL=http://YOUR_SERVER_IP`
- Set `FRONTEND_URL=http://YOUR_SERVER_IP`
- Replace `MYSQL_PASSWORD=change_me_mysql_password`
- Replace `MYSQL_ROOT_PASSWORD=change_me_root_password`
- Paste the generated key into `BACKEND_APP_KEY=base64:...`

Keep `backend/.env.docker` copied from `backend/.env.docker.example`; it reads these values from Docker Compose.

### 4. Start and initialize the demo

Run:

```bash
./scripts/deploy-server.sh
./scripts/init-demo.sh
```

Open:

```text
http://YOUR_SERVER_IP
```

Share this URL with testers after you confirm the smoke test in `deploy/production-checklist.md`.

### 5. Demo safety notes

All seeded demo accounts use the weak password `123456`. This is acceptable only for rehearsal and short-lived testing. Before any public production use, change all demo passwords, enable HTTPS with a real domain, restrict firewall access where possible, and use strong MySQL/root passwords.

This Docker deployment task does not add, modify, or delete API endpoints or database tables. The API Word document and database Word document do not need synchronization for this documentation-only change.

## Environment Variables

### Backend

Key variables in `backend/.env`:

- `APP_ENV`: use `local` for local development and `production` on the server.
- `APP_KEY`: generated by `php artisan key:generate`.
- `APP_DEBUG`: `true` locally, `false` in production.
- `APP_URL`: backend URL, for example `http://localhost:8000`.
- `FRONTEND_URL`: frontend URL, for example `http://localhost:5173`.
- `SANCTUM_STATEFUL_DOMAINS`: local frontend hosts, for example `localhost:5173,127.0.0.1:5173`.
- `DB_CONNECTION`: `mysql`.
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: MySQL connection settings.
- `FILESYSTEM_DISK`: `local` for v0.1 demo uploads.
- `QUEUE_CONNECTION`: `database` by default.

### Frontend

Key variable in `frontend/.env`:

- `VITE_API_BASE_URL`: API base URL, for example `http://localhost:8000/api/v1`.

For production, this should point to the deployed API path, for example `https://demo.example.com/api/v1`.

## Demo Accounts

All demo accounts use password `123456`.

| Role | Username | Purpose |
| --- | --- | --- |
| Super Admin | `admin001` | Reset demo data, view accounts, manually adjust status |
| Auditor | `audit001` | Assign sales, approve, reject, request supplements |
| Cashier | `cashier001` | Confirm payout and register voucher |
| Sales Agent | `sales001` | Inspect assigned applications |
| Sales Agent | `sales002` | Alternate inspection tester |
| Store | `store001` | Submit applications and supplement materials |
| Store | `store002` | Alternate store tester |

## Admin Reset

Log in as `admin001 / 123456`, open the Super Admin workspace, and use the reset action to restore the demo seed data.

Equivalent API behavior is implemented by the super admin reset endpoint. Use the UI during rehearsal unless an engineer is intentionally testing the API.

## Common Commands

Backend tests with local configured database:

```powershell
cd backend
php artisan test
```

Backend tests with SQLite in memory:

```powershell
cd backend
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:QUEUE_CONNECTION='sync'
php artisan test
```

Frontend build:

```powershell
cd frontend
npm run build
```

Frontend development server:

```powershell
cd frontend
npm run dev -- --host 0.0.0.0
```

Laravel API server:

```powershell
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

Fresh demo data:

```powershell
cd backend
php artisan migrate:fresh --seed
```

Git whitespace check:

```powershell
git diff --check
```

## Deployment Files

- `docker/nginx/default.conf`: Nginx configuration used by Docker demo deployments.
- `deploy/nginx.conf`: reference Nginx configuration for manual Linux/Nginx/PHP-FPM deployments.
- `deploy/production-checklist.md`: step-by-step production checklist.

For manual deployments, server paths, domain names, PHP-FPM socket names, and TLS certificate paths must be adjusted for the target server.

## API And Data Table Documents

Task 6 only adds deployment documentation. It does not add, modify, or delete API endpoints, request/response contracts, database tables, columns, indexes, or migrations. Therefore the v0.1 API interface document and data table design document do not need synchronization for this task.
