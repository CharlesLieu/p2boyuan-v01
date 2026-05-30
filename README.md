# P2BOYUAN v0.1 Remote Rehearsal MVP

## Stack

- Frontend: Vue 3 + Vite + Element Plus
- Backend: Laravel API + Sanctum bearer tokens
- Database: MySQL 8
- Local runtime: Docker Compose for MySQL

## Local startup

1. Start MySQL:
   `docker compose up -d mysql`

2. Start backend:
   `cd backend && php artisan serve --host=0.0.0.0 --port=8000`

3. Start frontend:
   `cd frontend && npm run dev -- --host 0.0.0.0`

## Demo accounts

- admin001 / 123456
- audit001 / 123456
- cashier001 / 123456
- sales001 / 123456
- sales002 / 123456
- store001 / 123456
- store002 / 123456
