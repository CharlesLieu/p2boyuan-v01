# Docker GitHub Server Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Package the v0.1 rehearsal MVP so it can be pushed to GitHub, cloned on a fresh Ubuntu server, and started with Docker Compose.

**Architecture:** A public Nginx container exposes port 80, serves the Vue frontend through an internal frontend Nginx container, and proxies `/api` and `/storage` to a Laravel/PHP-FPM backend container. MySQL 8 runs as an internal container with a persistent named volume.

**Tech Stack:** Docker Compose, Nginx, PHP 8.4 FPM, Laravel, Composer, Node/Vite build, MySQL 8.

---

## File Structure

- `docker-compose.yml`: full production-ish demo stack: `nginx`, `frontend`, `backend`, `mysql`.
- `.env.deploy.example`: root Compose variables copied to `.env` on the server.
- `docker/nginx/default.conf`: public Nginx routing for frontend and Laravel API.
- `backend/Dockerfile`: Laravel PHP-FPM image with required PHP extensions and Composer dependencies.
- `backend/docker/entrypoint.sh`: container startup checks and Laravel cache/storage preparation.
- `backend/.env.docker.example`: backend container environment template.
- `frontend/Dockerfile`: Vue build stage plus Nginx static runtime.
- `frontend/nginx.conf`: internal frontend static server.
- `frontend/.env.docker.example`: frontend Docker build environment template.
- `scripts/init-demo.sh`: server helper for migrations and demo seed.
- `scripts/deploy-server.sh`: server helper for first pull/build/start/init.
- `README.md`: update with GitHub and server deployment steps.
- `deploy/production-checklist.md`: update with Docker Compose path.

No API route or database schema changes are expected. If an implementation task discovers one is needed, update the API and data table Word documents before final completion.

---

### Task 1: Docker Ignore And Environment Templates

**Files:**
- Create: `.dockerignore`
- Create: `.env.deploy.example`
- Create: `backend/.env.docker.example`
- Create: `frontend/.env.docker.example`
- Modify: `.gitignore`

- [ ] **Step 1: Add root Docker ignore**

Create `.dockerignore`:

```dockerignore
.git
.runtime
node_modules
frontend/node_modules
frontend/dist
backend/vendor
backend/.env
backend/storage/logs/*.log
backend/bootstrap/cache/*.php
*.log
.env
.env.production
```

- [ ] **Step 2: Add root deploy env template**

Create `.env.deploy.example`:

```env
APP_NAME=P2BOYUAN
APP_ENV=production
APP_DEBUG=false
APP_URL=http://YOUR_SERVER_IP
FRONTEND_URL=http://YOUR_SERVER_IP

MYSQL_DATABASE=p2boyuan_v01
MYSQL_USER=p2boyuan
MYSQL_PASSWORD=change_me_mysql_password
MYSQL_ROOT_PASSWORD=change_me_root_password

BACKEND_APP_KEY=
```

- [ ] **Step 3: Add backend Docker env template**

Create `backend/.env.docker.example`:

```env
APP_NAME=P2BOYUAN
APP_ENV=production
APP_KEY=${BACKEND_APP_KEY}
APP_DEBUG=false
APP_URL=${APP_URL}
FRONTEND_URL=${FRONTEND_URL}

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=${MYSQL_DATABASE}
DB_USERNAME=${MYSQL_USER}
DB_PASSWORD=${MYSQL_PASSWORD}

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public

SANCTUM_STATEFUL_DOMAINS=
```

- [ ] **Step 4: Add frontend Docker env template**

Create `frontend/.env.docker.example`:

```env
VITE_API_BASE_URL=/api/v1
```

- [ ] **Step 5: Ensure generated deploy env files are ignored**

Modify `.gitignore` to include:

```gitignore
.env
.env.deploy
backend/.env.docker
frontend/.env.docker
```

- [ ] **Step 6: Verify formatting**

Run:

```bash
git diff --check
```

Expected: no output.

- [ ] **Step 7: Commit**

```bash
git add .dockerignore .env.deploy.example backend/.env.docker.example frontend/.env.docker.example .gitignore
git commit -m "chore: add docker deployment env templates"
```

---

### Task 2: Backend Docker Runtime

**Files:**
- Create: `backend/Dockerfile`
- Create: `backend/docker/entrypoint.sh`
- Modify: `backend/.dockerignore` only if needed; prefer root `.dockerignore`

- [ ] **Step 1: Create backend entrypoint**

Create `backend/docker/entrypoint.sh`:

```sh
#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ -n "${APP_KEY:-}" ]; then
  echo "APP_KEY is configured."
else
  echo "ERROR: APP_KEY is empty. Set BACKEND_APP_KEY in the root .env file." >&2
  exit 1
fi

php artisan storage:link || true
php artisan config:cache
php artisan route:cache

exec "$@"
```

- [ ] **Step 2: Create backend Dockerfile**

Create `backend/Dockerfile`:

```dockerfile
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.4-fpm-alpine
WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    zip \
    unzip \
  && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    pdo_mysql \
    zip

COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/p2boyuan-entrypoint

RUN chmod +x /usr/local/bin/p2boyuan-entrypoint \
  && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["p2boyuan-entrypoint"]
CMD ["php-fpm"]
```

- [ ] **Step 3: Check shell script line endings**

Run:

```bash
git diff --check
```

Expected: no whitespace errors. The entrypoint must use LF line endings in git.

- [ ] **Step 4: Commit**

```bash
git add backend/Dockerfile backend/docker/entrypoint.sh
git commit -m "chore: add backend docker runtime"
```

---

### Task 3: Frontend Docker Runtime

**Files:**
- Create: `frontend/Dockerfile`
- Create: `frontend/nginx.conf`

- [ ] **Step 1: Create frontend Nginx config**

Create `frontend/nginx.conf`:

```nginx
server {
    listen 80;
    server_name _;
    root /usr/share/nginx/html;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location = /health {
        access_log off;
        return 200 "ok\n";
    }
}
```

- [ ] **Step 2: Create frontend Dockerfile**

Create `frontend/Dockerfile`:

```dockerfile
FROM node:24-alpine AS build
WORKDIR /app

ARG VITE_API_BASE_URL=/api/v1
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL

COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
```

- [ ] **Step 3: Verify frontend build still passes locally**

Run:

```bash
cd frontend
npm run build
```

Expected: build passes. Existing third-party Vite/Rolldown warnings are acceptable if the exit code is 0.

- [ ] **Step 4: Commit**

```bash
git add frontend/Dockerfile frontend/nginx.conf
git commit -m "chore: add frontend docker runtime"
```

---

### Task 4: Public Nginx And Compose Stack

**Files:**
- Modify: `docker-compose.yml`
- Create: `docker/nginx/default.conf`

- [ ] **Step 1: Create public Nginx routing config**

Create `docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    server_name _;

    client_max_body_size 20m;

    location /api/ {
        proxy_pass http://backend:9000;
        include fastcgi_params;
    }
}
```

This first draft is intentionally expected to fail review because PHP-FPM cannot be reached with `proxy_pass`. The correct implementation is in Step 2.

- [ ] **Step 2: Replace with correct Nginx config**

Replace `docker/nginx/default.conf` with:

```nginx
upstream frontend_upstream {
    server frontend:80;
}

server {
    listen 80;
    server_name _;

    client_max_body_size 20m;

    location / {
        proxy_pass http://frontend_upstream;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /api/ {
        root /var/www/html/public;
        try_files $uri /index.php?$query_string;
        include fastcgi_params;
        fastcgi_pass backend:9000;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param HTTP_PROXY "";
    }

    location /storage/ {
        alias /var/www/html/public/storage/;
        access_log off;
        expires 7d;
    }
}
```

- [ ] **Step 3: Replace docker-compose.yml**

Replace `docker-compose.yml` with:

```yaml
services:
  nginx:
    image: nginx:1.27-alpine
    container_name: p2boyuan_v01_nginx
    restart: unless-stopped
    depends_on:
      - frontend
      - backend
    ports:
      - "80:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - backend_public:/var/www/html/public:ro

  frontend:
    build:
      context: ./frontend
      args:
        VITE_API_BASE_URL: /api/v1
    container_name: p2boyuan_v01_frontend
    restart: unless-stopped

  backend:
    build:
      context: ./backend
    container_name: p2boyuan_v01_backend
    restart: unless-stopped
    env_file:
      - ./backend/.env.docker
    depends_on:
      mysql:
        condition: service_healthy
    volumes:
      - backend_storage:/var/www/html/storage
      - backend_public:/var/www/html/public

  mysql:
    image: mysql:8.0
    container_name: p2boyuan_v01_mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${MYSQL_DATABASE}
      MYSQL_USER: ${MYSQL_USER}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-p${MYSQL_ROOT_PASSWORD}"]
      interval: 10s
      timeout: 5s
      retries: 10

volumes:
  mysql_data:
  backend_storage:
  backend_public:
```

- [ ] **Step 4: Validate Compose syntax if Docker is available**

Run:

```bash
docker compose config
```

Expected: rendered config exits 0. If Docker is unavailable locally, record that and continue with static file review.

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml docker/nginx/default.conf
git commit -m "chore: add full docker compose stack"
```

---

### Task 5: Server Helper Scripts

**Files:**
- Create: `scripts/init-demo.sh`
- Create: `scripts/deploy-server.sh`

- [ ] **Step 1: Create init script**

Create `scripts/init-demo.sh`:

```sh
#!/usr/bin/env sh
set -eu

docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan storage:link || true
docker compose exec backend php artisan config:cache
docker compose exec backend php artisan route:cache

echo "Demo data initialized."
```

- [ ] **Step 2: Create deploy script**

Create `scripts/deploy-server.sh`:

```sh
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
```

- [ ] **Step 3: Make scripts executable in git**

Run:

```bash
git update-index --chmod=+x scripts/init-demo.sh scripts/deploy-server.sh
```

- [ ] **Step 4: Commit**

```bash
git add scripts/init-demo.sh scripts/deploy-server.sh
git commit -m "chore: add server deployment helper scripts"
```

---

### Task 6: Deployment Documentation

**Files:**
- Modify: `README.md`
- Modify: `deploy/production-checklist.md`
- Modify: `deploy/nginx.conf` or replace notes with pointer to `docker/nginx/default.conf`

- [ ] **Step 1: Add GitHub + Ubuntu quick deployment section to README**

Add this section near the top of `README.md`:

```markdown
## Fast Remote Demo Deployment

Use this path when you want testers to access the demo from a server and you do not want local PHP/Node/MySQL setup.

### Server requirement

- Ubuntu 22.04 or 24.04
- 2 CPU / 4 GB RAM minimum for rehearsal
- Ports 80 open in the cloud firewall

### Install Docker on a fresh Ubuntu server

```bash
sudo apt update
sudo apt install -y ca-certificates curl git
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
docker --version
docker compose version
```

### Clone and configure

```bash
git clone <YOUR_GITHUB_REPO_URL>
cd p2boyuan-v01
cp .env.deploy.example .env
cp backend/.env.docker.example backend/.env.docker
```

Edit `.env` and `backend/.env.docker`.

Generate an app key:

```bash
docker run --rm -v "$PWD/backend:/app" -w /app php:8.4-cli php artisan key:generate --show
```

Put that value into `BACKEND_APP_KEY` in `.env` and `APP_KEY` in `backend/.env.docker`.

### Start the app

```bash
./scripts/deploy-server.sh
./scripts/init-demo.sh
```

Open:

```text
http://YOUR_SERVER_IP
```
```

- [ ] **Step 2: Update production checklist**

Ensure `deploy/production-checklist.md` includes Docker Compose deployment, security warnings for demo accounts, MySQL volume backup, and the full smoke test.

- [ ] **Step 3: Update Nginx deployment note**

If `deploy/nginx.conf` is older or non-Docker-specific, add a top comment:

```nginx
# Docker demo deployments use docker/nginx/default.conf.
# This file is a reference for manual Linux/Nginx deployments.
```

- [ ] **Step 4: Commit**

```bash
git add README.md deploy/production-checklist.md deploy/nginx.conf
git commit -m "docs: add github docker server deployment guide"
```

---

### Task 7: Verification And Final Review

**Files:**
- No code changes expected.

- [ ] **Step 1: Run backend full tests**

Run:

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:CACHE_STORE='array'
$env:SESSION_DRIVER='array'
$env:QUEUE_CONNECTION='sync'
& 'C:\Users\haoli\OneDrive\Desktop\Codex\P2BOYUAN\p2boyuan-v01\.runtime\php\php.exe' artisan test
```

Expected: all backend tests pass.

- [ ] **Step 2: Run frontend build**

Run:

```bash
cd frontend
npm run build
```

Expected: build exits 0. Existing third-party Vite/Rolldown warnings are acceptable.

- [ ] **Step 3: Validate Git state**

Run:

```bash
git status --short
git diff --check
```

Expected: clean status and no whitespace errors.

- [ ] **Step 4: Validate no API or database docs sync needed**

Inspect changed files:

```bash
git diff --name-only HEAD~7..HEAD
```

Expected: deployment files only. If any API routes, controllers, migrations, models, or request/response contracts changed beyond deployment health/config, update the v0.1 API and data table Word documents.

- [ ] **Step 5: Final commit only if verification docs changed**

If Step 4 required documentation changes:

```bash
git add <changed-docs>
git commit -m "docs: sync deployment-related technical docs"
```

Otherwise do not create an empty commit.

---

## Self-Review

**Spec coverage:** Tasks cover Docker runtime files, Compose stack, server scripts, GitHub/server deployment docs, and verification. The plan keeps GitHub as source control rather than runtime hosting, matching the approved design.

**Placeholder scan:** No open placeholders remain. Commands, files, and expected outputs are explicit.

**Type consistency:** Service names are consistent across Compose and Nginx: `nginx`, `frontend`, `backend`, `mysql`. The frontend API base remains `/api/v1`, matching the Nginx public route and existing Vue client.
