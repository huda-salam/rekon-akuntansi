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

## Development

The repository is currently bootstrapped with the project requirements and will be implemented incrementally.
