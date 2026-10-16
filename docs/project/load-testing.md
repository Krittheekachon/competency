# Load testing and capacity plan

## Capacity target

The system has a target population of about 1,000 users. Registered users are not the same as concurrent users, so capacity is defined with two separate tests:

1. `capacity`: 300 concurrent virtual users for the expected peak period.
2. `spike1000`: a short burst of 1,000 concurrent virtual users to expose login storms, connection exhaustion, and queue or session failures.

The 300-user target comes from this planning model:

- 1,000 total accounts.
- Up to 60% (600 users) active during a 15-minute peak window.
- Draft auto-save or another dynamic request about every 30 seconds while active: about 20 requests/second.
- Login, dashboard, assessment reads, and reviewer actions add about 5 requests/second.
- A 3x burst factor gives a target of 75 requests/second.
- With a 1.5-second p95 latency, Little's Law gives about `75 × 1.5 = 113` in-flight requests. A 2x safety margin is 226, rounded up to 300 virtual users.

If all 1,000 users continuously make one request every five seconds, the workload is about 200 requests/second. That is a stress target, not the expected business workload, and should be tested with `spike1000` before each major release or infrastructure change.

## Current baseline

The local database contained 36 users, 288 assessments, and 15 competencies. A read-only controller profile on 15 October 2026 produced these results:

| Role | Queries before | Queries after | Reduction |
| --- | ---: | ---: | ---: |
| Employee | 478 | 84 | 82.4% |
| Supervisor | 859 | 171 | 80.1% |
| HR | 464 | 143 | 69.2% |
| Admin | 1,613 | 654 | 59.5% |
| Dean | 220 | 98 | 55.5% |

These are controller/database profiles on a development machine, not HTTP capacity results. The data set is too small to prove support for 1,000 users. In particular, the admin dashboard still grows with the number of users and should be paginated or split into lazy-loaded modules before treating the 1,000-user spike as passed for the admin role.

The default employee dashboard also duplicated the same competency, gap, and FC payload inside `currentUser`. Removing that duplicate reduced one representative uncompressed HTML response from 837,734 bytes to 495,428 bytes (about 41%). The same local data set still produced employee dashboard responses from about 69 KB to 1.75 MB depending on reviewer assignments, so reviewer work lists also need pagination or lazy loading.

A local multi-session diagnostic used eight separate employee sessions and sent 200 dashboard requests through eight concurrent workers. It completed with zero failed requests, but took 50 seconds overall (about 4 requests/second), with per-account p95 latency between 3.6 and 8.4 seconds. This does **not** pass the capacity target. The result came from `php artisan serve` without a production PHP-FPM/OPcache stack, so it is useful for finding payload and query problems but cannot certify production capacity. Do not state that 1,000-user support is ready until the staging `capacity` and `spike1000` profiles pass.

## Running k6

Install k6 on the load-generator machine. Run tests only against a staging environment with production-like PHP-FPM, PostgreSQL, Redis, and built frontend assets. Do not use `php artisan serve` for capacity certification.

Create a credential file that is ignored by Git:

```bash
cp tests/Load/accounts.example.json tests/Load/accounts.local.json
```

Use unique accounts for accurate session and authorization behavior. The `capacity` profile requires 300 accounts and `spike1000` requires 1,000 accounts.

Smoke test:

```bash
k6 run \
  -e BASE_URL=https://staging.example.ac.th \
  -e PROFILE=smoke \
  -e ACCOUNTS_FILE=./accounts.local.json \
  tests/Load/competency.js
```

Expected peak:

```bash
k6 run \
  -e BASE_URL=https://staging.example.ac.th \
  -e PROFILE=capacity \
  -e ACCOUNTS_FILE=./accounts.local.json \
  tests/Load/competency.js
```

1,000-user spike:

```bash
k6 run \
  -e BASE_URL=https://staging.example.ac.th \
  -e PROFILE=spike1000 \
  -e ACCOUNTS_FILE=./accounts.local.json \
  tests/Load/competency.js
```

For a local or isolated staging environment, mock SSO is supported with `-e AUTH_MODE=mock-sso`. It must never be exposed in production.

Draft writes are disabled by default. They modify assessment data and must use a disposable staging database:

```bash
k6 run \
  -e BASE_URL=https://staging.example.ac.th \
  -e PROFILE=capacity \
  -e ACCOUNTS_FILE=./accounts.local.json \
  -e ENABLE_DRAFTS=true \
  -e CONFIRM_STAGING_WRITE=yes \
  tests/Load/competency.js
```

## Pass/fail criteria

The k6 script enforces the request-level gates:

- Fewer than 1% failed HTTP requests and failed user journeys.
- At least 99% successful checks.
- Overall p95 below 1.5 seconds and p99 below 3 seconds.
- Dashboard p95 below 1.5 seconds.
- Dashboard response-size p95 below 1 MiB uncompressed.
- Assessment read p95 below 1 second.
- Draft-save p95 below 2 seconds.

The run passes operationally only when all of these are also true:

- No HTTP 500/502/503/504 responses and no Laravel exceptions.
- App CPU stays below 70% sustained, with short peaks below 85%.
- PostgreSQL CPU stays below 70%, active connections below 80% of the limit, and lock waits remain near zero.
- Redis has no evictions and memory remains below 75%.
- PHP-FPM listen queue stays at zero in the `capacity` test.
- Queue oldest-job age remains below 60 seconds and failed jobs remain zero.
- At least 30% memory headroom remains on every node.

## Starting production topology

Use this as a starting point and resize from the staging measurements:

- Load balancer or Nginx in front of two application nodes.
- Each application node: 4 vCPU, 8 GB RAM, OPcache enabled, and 16–24 PHP-FPM children.
- PostgreSQL: 4 vCPU, 8–16 GB RAM, SSD storage, slow-query logging, and automated backups.
- Redis: 1–2 GB for sessions, cache, queues, and rate limiting.
- Two to four queue workers, supervised and restarted during releases.
- Shared object storage for uploaded evidence before adding multiple app nodes; local-only files are not safe behind a load balancer.

Calculate the PHP-FPM memory ceiling rather than guessing:

```text
max_children <= floor((node RAM - OS/nginx/agent reserve) / measured p95 PHP child RSS)
```

For an 8 GB node with a 3 GB reserve and a conservative 96 MB child RSS, the memory ceiling is 53 children. Start at 16–24 because CPU and database connections normally become the limit first.

For two nodes with 24 PHP-FPM children and four queue workers, reserve at least 62 database client connections (`2 × 24 + 4 + 10`). Prefer PgBouncer in transaction mode and keep PostgreSQL `max_connections` around 100–150 unless measurements justify more.

## Production configuration

- Use Redis for `SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION`; do not put session and cache traffic on the business PostgreSQL database.
- Run `php artisan optimize` and restart PHP-FPM/queue workers after every release.
- Run `php artisan queue:work --sleep=1 --tries=3 --timeout=90` under a process supervisor.
- Run `php artisan schedule:run` every minute.
- Enable OPcache and disable debug mode.
- Monitor p50/p95/p99 latency, error rate, PHP-FPM queue length, PostgreSQL connections/locks/slow queries, Redis memory/evictions, and Laravel failed jobs.

## Remaining scaling work

The highest remaining risks are the admin dashboard and large reviewer work lists. The admin dashboard serializes every user and builds nested competency, gap, FC, and reviewer-chain data. Reviewer dashboards can also exceed 1 MiB when a reviewer owns many assignments. Before production reaches 1,000 accounts:

1. Move the admin user table to server-side pagination and lazy-load the selected user's details.
2. Paginate assessment, FC, IDP, and progress-review work lists and fetch details only after an item is selected.
3. Load a staging data set with at least 1,000 users and representative assessment/IDP history.
4. Run `capacity`, tune PHP-FPM/database/Redis from observed metrics, and repeat until every gate passes.
5. Run `spike1000`; only then record the tested infrastructure and release as certified capacity.
