# Deployment Checklist

## Before deployment

1. Copy `.env.production.example` to the server's `.env` and replace every placeholder.
2. Generate `APP_KEY` once with `php artisan key:generate`. Never copy a development key to production.
3. Confirm `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Bangkok`, HTTPS `APP_URL`, and `SESSION_SECURE_COOKIE=true`.
4. Back up PostgreSQL before applying migrations.
5. Confirm the assessment round has all three dates and that they are ordered correctly. Round activation validates the schedule only; assessment and IDP readiness are checked per user when each workflow becomes available.
6. Confirm Redis is reachable and production uses Redis for sessions, cache, and queues.
7. Confirm the PHP-FPM, PostgreSQL, Redis, and queue-worker metrics described in `docs/project/load-testing.md` are available.

## Release commands

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

Restart the PHP runtime after the release so OPcache cannot serve old code. Run the configured queue workers under a process supervisor; assessment notifications are queued and require an active worker.

## Smoke checks

- `/up` returns HTTP 200.
- Redis session/cache operations succeed and a queued test job is processed.
- Public registration routes return HTTP 404.
- An active admin can log in with username and password.
- Existing accounts without a username can still log in with their full email address until an admin assigns a username.
- Self-service password reset routes return HTTP 404; users who forget their password must contact an admin.
- A suspended account cannot log in and an existing session is ended.
- Employee/HR dashboards do not receive the global user list.
- Admin and HR write routes reject unauthorized roles.
- Self-assessment and supervisor actions stop at their configured dates; IDP remains usable while the same round stays active.
- Creating and activating a new round does not reuse assessment or IDP records from the old round.
