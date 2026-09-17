# PRD — Rekon Akuntansi MVP

## 1. Tujuan

Menyediakan aplikasi sederhana untuk membantu SKPD dan SKPKD melakukan rekonsiliasi data akuntansi berdasarkan data pengesahan pendapatan dan belanja, menghasilkan Berita Acara (BA), dan menjaga hasil rekonsiliasi sebagai snapshot yang tidak berubah setelah BA diterbitkan.

## 2. Pengguna

### Admin/SKPKD
- Mengelola tahun aktif.
- Mengelola master SKPD.
- Mengelola data pejabat.
- Mengelola user.
- Memasukkan/mengimpor data pengesahan.
- Melakukan dan menyelesaikan rekonsiliasi.
- Membuat BA rekonsiliasi.
- Melihat histori rekonsiliasi seluruh SKPD sesuai kewenangan.

### User SKPD
- Hanya dapat mengakses data dan rekonsiliasi SKPD sendiri.
- Melihat data sumber yang terkait dengan SKPD.
- Melihat hasil rekonsiliasi.
- Melihat BA yang telah diterbitkan untuk SKPD tersebut.

## 3. Konsep data

### Tahun aktif
Sistem memiliki satu atau lebih tahun akuntansi. Salah satu tahun ditandai aktif untuk transaksi dan proses rekonsiliasi baru.

### SKPD
Master organisasi yang digunakan sebagai boundary akses dan identitas rekonsiliasi.

### Pejabat
Master pejabat disimpan terpisah dan dapat diedit. Untuk BA yang sudah diterbitkan, identitas pejabat yang relevan harus disalin ke snapshot sehingga perubahan master tidak mengubah BA lama.

### Data pengesahan
Pengesahan diperlakukan sebagai sumber data pendapatan dan belanja yang akan digunakan dalam rekonsiliasi. Struktur rinci mengikuti format sumber yang telah diberikan.

### Rekonsiliasi
Rekonsiliasi mempunyai fase kerja yang masih dapat diproses/diperbaiki sebelum difinalkan. Hasil final tidak boleh bergantung pada nilai master atau sumber yang dapat berubah.

### Snapshot
Pada saat BA dibuat/final, sistem menyalin seluruh informasi yang diperlukan untuk merepresentasikan hasil rekonsiliasi pada saat tersebut ke tabel snapshot. Snapshot tidak boleh diedit atau dihapus melalui aplikasi.

### BA
Berita Acara adalah dokumen formal yang mereferensikan snapshot final rekonsiliasi. Metadata BA meliputi minimal nomor, tanggal, tahun, SKPD, pejabat yang digunakan, dan identitas hasil rekonsiliasi.

### Sumber Aset/BMD
Belum tersedia saat implementasi awal. Arsitektur menyediakan titik integrasi untuk data dari Bidang Aset/BMD yang nantinya digunakan sebagai sumber pencocokan penambahan aset. Jangan membangun modul manajemen aset penuh sebelum format BA aktual tersedia.

## 4. Alur MVP

```text
Master Tahun/SKPD/Pejabat/User
             |
             v
      Data Pengesahan
             |
             v
     Rekonsiliasi Kerja
             |
       review/selisih
             |
             v
       Finalisasi / BA
             |
             v
   Immutable Snapshot
```

## 5. Otorisasi

- Admin/SKPKD: global sesuai fungsi.
- SKPD: query dan aksi dibatasi berdasarkan `skpd_id` milik user.
- Backend wajib melakukan authorization pada setiap endpoint, bukan hanya menyembunyikan menu di frontend.

## 6. Persyaratan snapshot

Saat finalisasi:
- Simpan identitas tahun.
- Simpan identitas SKPD dan nama saat itu.
- Simpan identitas pejabat dan nama/jabatan saat itu jika digunakan dalam BA.
- Simpan baris sumber dan hasil pencocokan yang membentuk hasil rekonsiliasi.
- Simpan nomor/tanggal BA.
- Simpan timestamp finalisasi.
- Tandai snapshot sebagai immutable.

Perubahan pada master SKPD, pejabat, atau data kerja setelah finalisasi tidak boleh mengubah snapshot.

## 7. Batasan implementasi

- Laravel 11, PHP 8.3+
- React + Vite + Tailwind
- SQLite untuk development; MySQL untuk deployment awal.
- Struktur SQL sebisa mungkin portable ke PostgreSQL.
- Tidak menggunakan microservices, CQRS, event sourcing, atau clean architecture berlapis secara berlebihan.
- Service layer sederhana digunakan untuk proses finalisasi/snapshot dan rekonsiliasi.

## 8. Status data

### Reconciliation
`draft` → `in_review` → `finalized`

Setelah `finalized`, data hasil rekonsiliasi tidak dapat diedit. Koreksi dilakukan melalui proses/rekonsiliasi baru sesuai kebutuhan bisnis.

## 9. Non-goals MVP

- Full asset/BMD management.
- General ledger engine.
- Budget planning engine.
- Workflow engine generik.
- Multi-tenant platform.
- Complex rule engine.
