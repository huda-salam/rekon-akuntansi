# Rekon Akuntansi

Aplikasi sederhana untuk rekonsiliasi akuntansi pemerintah daerah.

## Scope MVP

- Manajemen tahun aktif
- Master SKPD
- Master pejabat
- User management dan pembatasan akses berdasarkan SKPD
- Data sumber pengesahan pendapatan dan belanja
- Proses rekonsiliasi bulanan oleh SKPKD/admin (tahun + SKPD + bulan)
- Import source workbook → normalized financial facts → calculation → reconciliation → exception review → BA
- Drill-down lineage dari hasil rekonsiliasi ke file, sheet, baris, dan financial fact sumber
- Berita Acara (BA) rekonsiliasi
- Snapshot rekonsiliasi immutable pada saat BA dibuat
- Fondasi sumber data penambahan aset dari Bidang Aset/BMD (format menyusul)

## Technology direction

- Backend: Laravel 11 / PHP 8.3+
- Frontend: React + Vite
- Database: SQLite for development, MySQL for production
- Authentication: Laravel Sanctum

## Design principle

Aplikasi sengaja dibuat sederhana dan sesuai proses bisnis rekonsiliasi. Tidak menggunakan microservices, CQRS, event sourcing, atau abstraction berlebihan pada tahap awal.

Rekonsiliasi yang sudah difinalisasi tidak diedit. Berita Acara dan snapshot menyimpan keadaan data pada saat finalisasi, termasuk hash SHA-256 sebagai identitas snapshot.

Data Bidang Aset/BMD belum dimodelkan secara spesifik karena format BA sumber belum tersedia. Setelah format aktual tersedia, struktur sumber dan aturan pencocokan akan disesuaikan berdasarkan dokumen tersebut, bukan diasumsikan dari format umum.

## API utama

- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`
- `GET /api/years`
- `POST /api/years` — admin/SKPKD
- `POST /api/years/{id}/activate` — admin/SKPKD
- `GET/POST /api/authorizations`
- `GET/POST /api/reconciliations`
- `GET/PUT /api/reconciliations/{id}`
- `POST /api/reconciliations/{id}/finalize` — admin/SKPKD

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

Seeded development users:

- Admin: `admin@example.test` / `password`
- SKPD: `skpd@example.test` / `password`

Do not use the seeded credentials in production.
