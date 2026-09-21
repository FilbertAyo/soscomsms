# Soscom SMS

Laravel 12 + React SMS platform for SOSCOM. Production deploys from `main` over SSH to shared hosting.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run dev
```

PHP 8.2+ and Node 22 are required. Queue workers run with `composer run dev`.

## Production

Copy `.env.production.example` to `.env` on the server, fill in secrets, then push to `main`. GitHub Actions builds the frontend, uploads the release, and runs `scripts/deploy.sh` (migrate, caches, queue restart).
