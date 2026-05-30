# Docker + GitHub + Server Deployment Design

Date: 2026-05-30

## Goal

Package the v0.1 remote rehearsal MVP so it can be pushed to GitHub, cloned on a fresh Ubuntu server, and started with Docker Compose. The user should not need to install PHP, Composer, Node.js, MySQL, or Nginx manually on the server.

The target outcome is:

```bash
git clone <repo-url>
cd p2boyuan-v01
cp .env.deploy.example .env
docker compose up -d --build
docker compose exec backend php artisan migrate:fresh --seed
```

Then testers can open the server IP or domain and log in with the existing demo accounts.

## Chosen Approach

Use one Linux server running Docker Compose.

Services:

- `frontend`: Nginx serving the built Vue app.
- `backend`: Laravel/PHP-FPM API container.
- `mysql`: MySQL 8 with a named Docker volume.
- `nginx`: public entrypoint reverse proxy routing `/` to frontend and `/api` to backend.

This is the fastest reliable path because it keeps all runtime dependencies inside containers and avoids local setup complexity. GitHub is used for source control and delivery, not as a long-running server.

## Alternatives Considered

### GitHub Pages / Actions

Rejected for runtime hosting. GitHub Pages only serves static assets and cannot run Laravel/PHP/MySQL. GitHub Actions is for CI jobs, not long-lived application hosting.

### Render / Railway / Fly.io

Possible later. These platforms can be convenient but add platform-specific configuration, free-tier sleep behavior, and storage/database constraints. A normal Docker Compose server is simpler to reason about for the first external test.

### WordPress Hosting

Rejected. WordPress can link to the demo later, but it should not host this Laravel/Vue/MySQL business system.

## Architecture

```text
Browser
  |
  v
nginx public container :80
  |-- /           -> frontend nginx container
  |-- /api, /storage -> backend PHP/Laravel container
  |
  v
mysql container with persistent volume
```

The public Nginx container is the only service that needs to expose port `80` to the internet in the first deployment. MySQL remains internal to the Docker network.

## Configuration

Add deploy-oriented environment templates:

- `.env.deploy.example`: root variables used by Docker Compose.
- `backend/.env.docker.example`: Laravel production-like container env.
- `frontend/.env.docker.example`: frontend build-time API base URL.

Expected first deployment can use plain HTTP and server IP. HTTPS/domain can be added after the demo is reachable.

## Files To Add Or Modify

Expected implementation files:

- `docker-compose.yml`: replace MySQL-only compose with full app stack.
- `docker/nginx/default.conf`: public routing config.
- `backend/Dockerfile`: PHP runtime with required extensions and Composer install.
- `backend/docker/entrypoint.sh`: wait for MySQL, cache config, run storage link if needed, start PHP-FPM.
- `frontend/Dockerfile`: build Vue app and serve static output with Nginx.
- `.env.deploy.example`: root deploy values.
- `scripts/deploy-server.sh`: optional helper for first server startup.
- `scripts/init-demo.sh`: optional helper for migrate/seed.
- `README.md`: add GitHub + Ubuntu server deployment steps.
- `deploy/production-checklist.md`: update with Docker Compose path.

## Data Flow

1. Tester opens `http://SERVER_IP`.
2. Public Nginx serves Vue frontend.
3. Vue calls `/api/v1/...`.
4. Public Nginx proxies API traffic to Laravel backend.
5. Laravel reads/writes MySQL.
6. Demo reset uses existing super admin endpoint and seeded data.

## Secrets And Safety

The repository should contain only example env files. Real server secrets stay in `.env` on the server and should not be committed.

First demo can use seeded weak passwords because it is a rehearsal MVP. README and deployment checklist must explicitly warn not to expose this publicly for production without changing demo passwords and hardening configuration.

## Testing

Implementation must verify:

- Backend tests pass with the available SQLite test runtime.
- Frontend `npm run build` passes.
- Docker Compose config validates with `docker compose config` if Docker is available locally.
- Documentation includes exact server commands for a fresh Ubuntu machine.

If Docker is not available locally, the implementation must state that container startup was not locally executed and provide the server-side verification steps.

## Documentation Sync

This deployment work should not change API contracts or database schema. If implementation adds or changes any runtime endpoint or table, the v0.1 API and database Word documents must be updated. Otherwise, README should state no API/data-table document sync was required.

## Acceptance Criteria

- A user can push the repo to GitHub and clone it on an Ubuntu server.
- A fresh server with Docker installed can start the app with Docker Compose.
- Testers can access the frontend through server IP.
- Backend API and MySQL run in containers.
- Demo data can be initialized with one documented command.
- No `.env`, `vendor`, `node_modules`, or built `dist` artifacts are committed.
