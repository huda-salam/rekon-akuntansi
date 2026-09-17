# Rekon Akuntansi

Aplikasi sederhana untuk rekonsiliasi akuntansi pemerintah daerah.

## Scope MVP

- Manajemen tahun aktif
- Master SKPD
- Master pejabat
- User management dan pembatasan akses berdasarkan SKPD
- Data sumber pengesahan pendapatan dan belanja
- Proses rekonsiliasi oleh SKPKD/admin
- Berita Acara (BA) rekonsiliasi
- Snapshot rekonsiliasi immutable pada saat BA dibuat
- Fondasi sumber data penambahan aset dari Bidang Aset/BMD (format menyusul)

## Technology direction

- Backend: Laravel 11 / PHP 8.3+
- Frontend: React + Vite + Tailwind CSS
- Database: SQLite for development, MySQL for production
- Authentication: Laravel Sanctum

## Design principle

Aplikasi sengaja dibuat sederhana dan sesuai proses bisnis rekonsiliasi. Tidak menggunakan microservices, CQRS, event sourcing, atau abstraction berlebihan pada tahap awal.

## Development status

The repository is being implemented incrementally. Current implementation includes the Laravel application bootstrap, core database migrations/models, reconciliation API, immutable snapshot finalization, and an initial React/Vite dashboard.

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
mkdir -p database && touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

For the frontend in development:

```bash
npm install
npm run dev
```

The API requires an authenticated Sanctum user. The initial UI is intentionally lightweight and will be expanded with CRUD and reconciliation workflows in the next iteration.
