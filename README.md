# Competency

ENKKU Competency & IDP System built with Laravel, Inertia, Vue, Vite, and PostgreSQL.

## Requirements

- PHP 8.3+
- Composer
- Node.js and npm
- Docker Desktop

## Local Setup

Install PHP and JavaScript dependencies:

```bash
composer install
npm install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

Start PostgreSQL:

```bash
docker compose up -d
```

Run database migrations:

```bash
php artisan migrate
```

Start the Laravel and Vite development servers in separate terminals:

```bash
php artisan serve
npm run dev
```

Open the app at:

```text
http://127.0.0.1:8000
```

## Database

The local PostgreSQL database runs in Docker with these default credentials:

```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=competency
DB_USERNAME=sail
DB_PASSWORD=password
```

When database structure changes, commit the migration files and have teammates run:

```bash
php artisan migrate
```

To rebuild the local database from scratch:

```bash
php artisan migrate:fresh --seed
```
