# PoC A → B → C: aplikasi terpisah

Repository ini sekarang berisi tiga aplikasi Laravel mandiri.

## Status demo lokal yang sudah dijalankan

UI B sekarang tersedia pada `http://localhost:8001/`: daftar transaksi hasil clone, pencarian/filter/pagination, ringkasan nominal dan status snapshot. Halaman tidak menampilkan key API atau data pegawai.

Database A sudah di-backup dan di-restore ke database B baru. PostgreSQL 17.11 khusus PoC dipasang dalam `.runtime/` dan berjalan hanya di `127.0.0.1:5433`. Role `finance_c`, password, database C, migrasi, dan `.env` C lokal sudah disiapkan. Driver PHP `pdo_pgsql` diaktifkan melalui konfigurasi proses lokal, tanpa mengubah php.ini global. Paket PostgreSQL berasal dari [distribusi Windows resmi EDB](https://www.enterprisedb.com/download-postgresql-binaries).

Sinkronisasi HTTP B ke PostgreSQL C telah berhasil untuk 10.012 transaksi dan 1.003 pegawai. Jumlah transaksi dan total nominal Rp24.875.599.950,00 sama di A/B/C pada saat verifikasi. Kontrak snapshot masih menyertakan pegawai; UI B/C hanya menampilkan transaksi.

Setelah restart komputer, jalankan dari folder utama:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\start-demo.ps1
```

Launcher menyalakan PostgreSQL, server A/B/C dan scheduler B di background. Server yang sudah berjalan pada port yang tepat dipakai kembali. Scheduler B menjalankan clone A → B setiap jam dan menerbitkan snapshot setelah restore sukses. Scheduler C lama dihentikan: B → C hanya ditarik manual melalui tombol **Tarik data dari B** pada dashboard C. Launcher sendiri tidak langsung melakukan restore saat dibuka; tugas clone mengikuti jadwal per jam.

Untuk command C langsung dari terminal, aktifkan driver pada perintah PHP, misalnya `php -d extension=pdo_pgsql app-c/artisan finance:sync-from-b` dari folder utama. Pastikan PostgreSQL dan server B sedang berjalan.

- **A — folder utama `D:\PoC_Web_TokoA`**: admin toko, MSSQL `db_toko_a`, seeder data dummy. Jalankan di port **8000**, buka `/admin`.
- **B — folder `app-b/`**: API snapshot dari MSSQL `db_keuangan_b`. Jalankan di port **8001**. Tidak memiliki route admin A atau dashboard C.
- **C — folder `app-c/`**: pengunduh API B dan dashboard transaksi PostgreSQL `db_keuangan_c`. Jalankan di port **8002**, buka `/app-c`.

Setiap aplikasi mempunyai `artisan`, `composer.json`, `vendor`, `.env`, konfigurasi, cache, storage dan tes sendiri. B dan C tidak memuat file aplikasi dari folder A atau dari satu sama lain saat runtime. Tidak perlu memindahkan kode untuk deployment: folder B dan C dapat disalin ke server masing-masing, lalu jalankan `composer install` di folder terkait.

`.env` A yang lama tidak diubah. `.env` B/C lokal sudah dibuat dengan APP_KEY berbeda dan key API B/C yang cocok. Jangan commit atau membagikan file `.env`. Pada instalasi baru, salin `.env.example`, isi konfigurasi, jalankan `composer install` dan `php artisan key:generate`.

## Menjalankan demo lokal

Buka tiga terminal terpisah dari folder repository:

```powershell
# Terminal A
php artisan serve --port=8000
```

```powershell
# Terminal B
cd app-b
php artisan serve --port=8001
```

```powershell
# Terminal C
cd app-c
php artisan serve --port=8002
```

Alamat A: http://localhost:8000/admin

Alamat B: http://localhost:8001 (dashboard provider API)

Alamat C: http://localhost:8002/app-c

`/app-c` tidak lagi disajikan oleh A. Tautan sidebar A mengarah ke URL C terpisah melalui `APP_C_URL` (default `http://localhost:8002/app-c`).

## Alur data

1. Admin memasukkan data di A.
2. Backup/restore MSSQL menyalin **seluruh database A** ke B.
3. B menerbitkan snapshot dari `db_transaksi` dan `db_pegawai` termasuk gaji.
4. C mengunduh snapshot melalui API B dengan header `X-API-KEY`, memvalidasi semua halaman, lalu mengganti tabel mirror di PostgreSQL dalam satu transaksi.
5. Dashboard C menampilkan transaksi saja; pegawai tidak ditampilkan. Pembatasan tampilan ini **tidak** menghapus pegawai dari kontrak API/sinkronisasi.

## A → B: clone database MSSQL

Skrip berada di `database/clone-a-to-b.ps1`. Memerlukan `sqlcmd`, hak backup/restore dan direktori yang dapat ditulis layanan SQL Server. Versi PoC mendukung satu instance SQL Server dengan satu file data dan satu log; untuk instance berbeda gunakan prosedur backup/restore DBA.

```powershell
.\database\clone-a-to-b.ps1 -ServerInstance 'localhost\SQLEXPRESS' `
  -SourceDatabase db_toko_a -TargetDatabase db_keuangan_b `
  -BackupFile 'D:\SqlBackups\toko_a_for_b.bak' `
  -DataFile 'D:\SqlData\db_keuangan_b.mdf' `
  -LogFile 'D:\SqlData\db_keuangan_b_log.ldf' -WhatIf
```

Periksa simulasi, kemudian hapus `-WhatIf` untuk eksekusi langsung skrip root. SQL Server lokal pada demo ini memakai sertifikat lokal; gunakan `-TrustServerCertificate` untuk koneksi lokal tersebut. Jika B sudah ada, skrip root memerlukan `-AllowReplaceTarget` dan konfirmasi. Backup file yang disebutkan juga ditimpa; gunakan file khusus proses ini. Data tambahan milik B harus berada di database lain karena full restore mengganti seluruh database B. Clone otomatis menggunakan skrip milik B (`app-b/database/clone-a-to-b.ps1`) dalam mode unattended, sesuai konfigurasi clone yang diaktifkan.

## Konfigurasi B

Edit `app-b/.env`, bukan `.env` A. Gunakan `DB_CONNECTION=sqlsrv`, host/kredensial MSSQL, `DB_DATABASE=db_keuangan_b`, `FINANCE_PUBLISHER_ENABLED=true`, `FINANCE_SOURCE_CONNECTION=sqlsrv`, `FINANCE_CLONE_ENABLED=true`, `FINANCE_CLONE_SOURCE_DATABASE=db_toko_a`. `API_GATEWAY_KEY` B harus sama dengan `FINANCE_UPSTREAM_KEY` C. Publisher API hanya memerlukan akses baca. Scheduler clone PoC memakai Windows Authentication pada instance yang sama dan harus dijalankan oleh akun Windows dengan hak backup/restore serta akses direktori SQL Server. SQL Authentication dan DB_URL belum didukung untuk job clone ini.

Setelah clone sukses, jalankan di folder **app-b**:

```bash
php artisan config:clear
php artisan finance:publish-snapshot
```

API B (memerlukan header `X-API-KEY`):

- `GET /api/v1/finance/snapshot`
- `GET /api/v1/finance/snapshots/{snapshot_id}/{transaksi|pegawai}/{page}`

Default 500 baris per halaman, maksimal 1.000. Halaman berasal dari snapshot tetap, bukan query live per halaman. Snapshot memakai pembacaan SERIALIZABLE, sehingga dapat menahan perubahan B selama ekspor. Snapshot berlaku 24 jam dan dibersihkan setelah publikasi berikutnya. File snapshot disimpan pada storage privat B. Jika memakai gateway eksternal, teruskan path API dan header key ke B melalui HTTPS.

## Konfigurasi C

Pada instalasi baru, aktifkan ekstensi PHP `pdo_pgsql`, buat database `db_keuangan_c`, lalu isi host, port, username dan password PostgreSQL pada **app-c/.env**. Demo lokal di atas sudah disiapkan pada port 5433. Set:

```dotenv
DB_CONNECTION=pgsql
FINANCE_TARGET_CONNECTION=pgsql
FINANCE_CONSUMER_ENABLED=true
FINANCE_UPSTREAM_URL=http://localhost:8001
FINANCE_UPSTREAM_KEY=key-yang-sama-dengan-b
FINANCE_SOURCE_TIMEZONE=Asia/Jakarta
```

HTTP localhost hanya untuk environment local/testing. Pada server gunakan HTTPS. URL upstream berupa origin, tanpa tambahan `/api`. Sesuaikan zona waktu dengan arti DATETIME sumber MSSQL.

Jalankan di folder **app-c** setelah PostgreSQL siap dan server B berjalan:

```bash
php artisan config:clear
php artisan migrate --database=pgsql --path=database/migrations/finance
php artisan finance:sync-from-b
```

Tabel C: `mirror_b_transaksi`, `mirror_b_pegawai`, `mirror_b_sync_state`. Simpan akun, pengaturan, dan catatan lokal pada tabel lain. Sync mengunduh snapshot penuh: data baru, perubahan dan penghapusan mengikuti B. Jika proses gagal, mirror sebelumnya dipertahankan. Snapshot sama dilewati; snapshot lama tidak menggantikan snapshot baru.

Dashboard `/app-c` membaca mirror PostgreSQL. Tidak ada fallback ke database A. Tersedia pencarian, filter tanggal/status, pagination, ringkasan nominal dan status sinkronisasi. Tombol **Tarik data dari B** menjalankan penarikan manual melalui POST dengan CSRF; refresh halaman biasa tidak menarik data. Jika penarikan gagal, data lama tetap dipakai. Atur perlindungan akses saat deployment sesuai kebutuhan instalasi; PoC belum menyediakan login pengguna B/C.

## Scheduler

### Mengakses melalui ngrok

Untuk membuka A melalui ngrok, arahkan tunnel ke port 8000. A/B/C mempercayai header reverse proxy hanya dari loopback 127.0.0.1/::1, sehingga aset dan form mengikuti HTTPS ngrok sementara localhost tetap HTTP.

Jika C pada laptop teman ingin menarik data B, tunnel harus menuju **port 8001 (aplikasi B)**, bukan `/admin` pada A. Jalankan `ngrok http 8001`, lalu isi `.env` C teman dengan `FINANCE_UPSTREAM_URL=https://domain-tunnel-b.ngrok-free.dev` (origin saja, tanpa `/admin` atau `/api`) dan key B yang sesuai. Jalankan `php artisan config:clear` di C, kemudian tekan **Tarik data dari B**. Pengunduh C mengirim header `ngrok-skip-browser-warning` sesuai dukungan ngrok. Tunnel A yang sudah berjalan tidak otomatis menjadi tunnel API B.

Panduan rujukan: [Laravel trusted proxies](https://laravel.com/docs/13.x/requests#configuring-trusted-proxies), [ngrok free-plan interstitial](https://github.com/ngrok/ngrok-docs/blob/main/pricing-limits/free-plan-limits.mdx).

Jalankan `php artisan schedule:work` pada terminal tambahan di **app-b saja** untuk demo (launcher sudah melakukan ini). Produksi: cron/Windows Task Scheduler menjalankan `artisan schedule:run` setiap menit pada folder B. Aplikasi C tidak memiliki tugas terjadwal.

Pada menit 00 setiap jam, B menjalankan `finance:clone-from-a`: backup A, restore mengganti B, lalu terbitkan snapshot setelah sukses. Lock mencegah clone dan publisher berjalan bersamaan. Restore dapat membuat pembacaan UI B tidak tersedia sesaat; endpoint snapshot tetap memakai berkas snapshot sebelumnya yang utuh. Jika restore gagal, snapshot baru tidak diterbitkan dan kegagalan dicatat pada log B. Penarikan C dilakukan saat pengguna menekan tombol atau menjalankan `finance:sync-from-b`; tidak ada jadwal menit 05. Pantau log dan waktu penarikan terakhir pada `mirror_b_sync_state`.

## Data dummy dan tes

Seeder dijalankan **hanya di A**:

```bash
php artisan db:seed --class=TokoDemoSeeder --no-interaction
```

Menambahkan 10.000 transaksi DEMO dan 1.000 pegawai DEMO tanpa menghapus data lama. Aman diulang tanpa duplikat; environment produksi ditolak. Data B/C baru ikut berubah setelah clone dan sync.

Jalankan `php artisan test --compact` dari masing-masing folder A, B, dan C. Pengujian lintas komponen terpisah dari runtime aplikasi:

```bash
# Dari folder utama
php vendor/bin/phpunit -c phpunit.integration.xml
```

Suite integrasi memuat kode B/C hanya pada proses pengujian. Tes otomatis menggunakan SQLite/HTTP fake; backup/restore MSSQL dan koneksi PostgreSQL perlu diuji pada database uji sebelum deployment.
