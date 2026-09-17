# Local setup

The repository is intentionally kept as a lightweight Laravel application.

## Requirements

- PHP 8.3+
- Composer 2+
- Node.js 20+
- npm

## Bootstrap

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

For the first development database, use SQLite:

```bash
touch database/database.sqlite
```

Then set:

```dotenv
DB_CONNECTION=sqlite
```

Use MySQL in deployment by changing the standard Laravel `DB_*` settings.

## Implementation status

The current repository contains the MVP domain skeleton, migrations, policy, API routes/controller, and immutable finalization service. UI pages, import screens, and full CRUD screens are the next implementation layer.
