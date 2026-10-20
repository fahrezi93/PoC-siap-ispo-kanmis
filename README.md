# PoC A → B → C: aplikasi terpisah

Repository ini sekarang berisi tiga aplikasi Laravel mandiri.

## Demo dua laptop melalui ngrok (A → B tetap full clone)

Laptop pertama menjalankan A dan MSSQL A. Laptop kedua menjalankan B dengan MSSQL B lokal serta C dengan PostgreSQL. B meminta backup `.bak` A melalui HTTPS ngrok, memeriksa SHA256, lalu menggunakan skrip restore yang sama. Tidak perlu SQL A dibuka ke jaringan atau folder SMB share. B → C tetap penarikan manual melalui API snapshot B.

Di `.env` **A**, aktifkan endpoint backup dan isi key khusus clone yang berbeda dari key API transaksi:

```dotenv
DB_CLONE_EXPORT_ENABLED=true
DB_CLONE_KEY=isi-key-rahasia-khusus-clone
```

Jalankan `php artisan config:clear`, kemudian `php artisan serve --port=8000`. Di terminal lain, jalankan `ngrok http 8000`. Gunakan origin HTTPS ngrok yang muncul, tanpa `/admin` atau `/api`. Rujukan: [ngrok HTTP endpoints](https://ngrok.com/docs/gateway/endpoints/http).

Endpoint `POST /api/v1/database/backup` memerlukan header `X-API-KEY` dengan key clone. Endpoint hanya mengekspor database yang dikonfigurasi pada A; nama database/path tidak bisa dipilih pemanggil. File dibuat di folder privat `storage/app/private/database-backups` A, dikirim sebagai download, lalu dihapus setelah respons selesai. SQL Server A dan aplikasi A harus berada di mesin yang sama, akun SQL A harus berhak backup, dan akun layanan SQL Server harus diberi izin menulis folder privat itu. Proses PHP A harus dapat membaca/menghapus file backup tersebut. File berisi **seluruh database termasuk data pegawai/gaji**; gunakan database dummy untuk demo. Jangan simpan `.bak` dalam folder `public`.

Untuk instance SQLEXPRESS dengan identitas layanan `NT SERVICE\MSSQL$SQLEXPRESS`, jalankan sekali di PowerShell **Administrator** laptop A (sesuaikan lokasi repository dan akun layanan bila berbeda):

```powershell
New-Item -ItemType Directory -Force 'D:\PoC_Web_TokoA\storage\app\private\database-backups'
icacls 'D:\PoC_Web_TokoA\storage\app\private\database-backups' /grant 'NT SERVICE\MSSQL$SQLEXPRESS:(OI)(CI)M'
```

Di `.env` **B** pada laptop kedua, pertahankan koneksi MSSQL lokal B yang benar, kemudian set:

```dotenv
FINANCE_CLONE_ENABLED=true
FINANCE_CLONE_TRANSPORT=https
FINANCE_CLONE_UPSTREAM_URL=https://domain-a.ngrok-free.dev
FINANCE_CLONE_UPSTREAM_KEY=key-yang-sama-dengan-DB_CLONE_KEY-A
FINANCE_CLONE_SOURCE_DATABASE=db_toko_a
FINANCE_CLONE_TRANSFER_DESTINATION='D:\Inbound\toko_a_for_b.bak'
```

Buat folder `D:\Inbound` di laptop B; akun Windows yang menjalankan PHP harus dapat menulisnya dan layanan SQL B harus dapat membaca hasil unduhan. B memakai host SQL `localhost`, `127.0.0.1`, atau `localhost\INSTANCE`; gunakan nama database B khusus seperti `db_keuangan_b`. Versi SQL B harus sama atau lebih baru dari versi A. Pengaturan `SOURCE_SERVER`, `SOURCE_BACKUP_PATH`, `TRANSFER_SOURCE`, dan `TARGET_BACKUP_PATH` milik mode LAN tidak digunakan pada mode HTTPS; B menggunakan file lokal hasil unduhan untuk restore.

Di folder **app-b**, jalankan:

```powershell
php artisan config:clear
php artisan finance:clone-from-a
```

Command tersebut benar-benar mengganti database B dan menerbitkan snapshot hanya jika restore berhasil. Setelah satu siklus berhasil, jalankan `php artisan serve --port=8001` dan `php artisan schedule:work` pada dua terminal berbeda di B. Endpoint/tunnel A harus tetap aktif agar jadwal setiap jam berhasil. Bila domain ngrok berubah, perbarui URL B dan restart scheduler setelah membersihkan config cache.

C pada laptop yang sama dengan B tetap memakai `FINANCE_UPSTREAM_URL=http://localhost:8001` dan key API snapshot B; **bukan key clone A**. Jalankan migrasi finance PostgreSQL saat instalasi C pertama, buka `/app-c`, lalu tekan **Tarik data dari B**. Sesudah demo, nonaktifkan `DB_CLONE_EXPORT_ENABLED` A dan tutup tunnel. Pengujian HTTP fake tidak menggantikan uji transfer/restore antar kedua laptop.

Verifikasi lokal 20 Oktober 2026: endpoint A menghasilkan backup sekitar 6 MB dengan SHA256 cocok. Skrip mode unduhan berhasil me-restore backup ke database uji terpisah; jumlah transaksi sumber dan hasil restore sama-sama 10.014. Database uji dibersihkan setelah pengujian. Koneksi ngrok dan restore pada laptop teman belum diverifikasi.

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

Untuk deployment berbeda komputer, scheduler di **B** menjalankan `app-b/database/clone-network.ps1` melalui `ScheduledDatabaseClone`. Alurnya:

1. B menghubungi SQL Server A melalui jaringan privat dan meminta `BACKUP DATABASE ... WITH COPY_ONLY, INIT, CHECKSUM`.
2. SQL Server A menulis `.bak` pada folder backup A. File ini dibuat saat job berjalan, bukan berkas yang disertakan melalui GitHub.
3. Proses scheduler B membaca file tersebut melalui SMB/UNC share, lalu menyalinnya ke folder penerima B.
4. B memeriksa SHA256 sebelum/sesudah transfer, menjalankan `RESTORE VERIFYONLY`, dan membandingkan `BackupSetGUID` dengan backup yang dibuat di A. Path yang menunjuk backup berbeda ditolak sebelum restore.
5. SQL Server B me-restore file tersebut ke database mirror B, kemudian B menerbitkan snapshot API untuk C.

Contoh tiga mesin: A `192.168.10.10`, B `192.168.10.20`, C `192.168.10.30`. Alamat ini hanya contoh; server dan share jaringan nyata belum tersedia sehingga mode ini belum diuji antarserver. A → B tidak melalui halaman `/admin`, API, atau tunnel HTTP ngrok.

Isi **`.env` milik B** sesuai jaringan sebenarnya:

```dotenv
DB_CONNECTION=sqlsrv
DB_HOST=192.168.10.20
DB_PORT=1433
DB_DATABASE=db_keuangan_b
DB_USERNAME=akun_sql_b
DB_PASSWORD=isi-secara-privat
DB_TRUST_SERVER_CERTIFICATE=false
FINANCE_CLONE_TRANSPORT=network
FINANCE_CLONE_ENABLED=false
FINANCE_CLONE_SOURCE_SERVER=192.168.10.10,1433
FINANCE_CLONE_SOURCE_DATABASE=db_toko_a
FINANCE_CLONE_SOURCE_USERNAME=akun_sql_a
FINANCE_CLONE_SOURCE_PASSWORD=isi-secara-privat
FINANCE_CLONE_SOURCE_TRUST_CERTIFICATE=false
FINANCE_CLONE_SOURCE_BACKUP_PATH='D:\SqlBackups\toko_a_for_b.bak'
FINANCE_CLONE_TRANSFER_SOURCE='\\SERVER-A\CloneBackup\toko_a_for_b.bak'
FINANCE_CLONE_TRANSFER_DESTINATION='D:\Inbound\toko_a_for_b.bak'
FINANCE_CLONE_TARGET_BACKUP_PATH='D:\Inbound\toko_a_for_b.bak'
```

Empat path memiliki sudut pandang berbeda: `SOURCE_BACKUP_PATH` dilihat SQL A; `TRANSFER_SOURCE` adalah file yang sama melalui share; `TRANSFER_DESTINATION` dilihat proses scheduler B; `TARGET_BACKUP_PATH` adalah hasil transfer yang sama dilihat SQL B. Jika aplikasi B dan SQL B juga terpisah mesin, gunakan share penerima yang sesuai untuk dua path terakhir. Folder harus sudah tersedia. Gunakan nama file khusus job ini karena file `.bak` dan database mirror B diganti pada setiap clone.

Prasyarat jaringan dan hak akses:

- Windows PowerShell dan PHP di mesin scheduler B; driver SQL Server untuk Laravel. Mode jaringan memakai .NET SqlClient, tidak memerlukan `sqlcmd`.
- SQL A/B dapat dijangkau melalui TCP pada port instance sebenarnya; SMB share dapat dijangkau oleh B melalui jaringan privat/VPN. Beda subnet memerlukan routing serta aturan firewall dari pengelola jaringan.
- Akun SQL A mempunyai hak backup; akun SQL B mempunyai hak restore/create/replace database mirror. Jika username kosong, skrip memakai Windows Authentication dari identitas proses scheduler.
- Akun layanan SQL A dapat menulis folder backup A. Akun Windows scheduler B dapat membaca share A dan menulis folder penerima. Akun layanan SQL B dapat membaca file hasil transfer. Kredensial SQL tidak otomatis memberi izin SMB/NTFS.
- Koneksi SQL terenkripsi secara default. Gunakan sertifikat yang dipercaya dan nama host yang sesuai. Flag trust certificate hanya untuk instalasi yang memang memerlukannya.
- Versi SQL B harus sama atau lebih baru dari A. Skrip saat ini mendukung satu file data dan satu log; kebutuhan lain seperti TDE memerlukan penyiapan DBA tambahan. Hak akses aplikasi B setelah restore juga harus disiapkan pada SQL Server B.

Rujukan: [izin SQL Server untuk backup jaringan](https://learn.microsoft.com/en-us/sql/relational-databases/backup-restore/backup-devices-sql-server?view=sql-server-ver16), [batasan versi backup/restore](https://learn.microsoft.com/en-us/troubleshoot/sql/database-engine/backup-restore/backup-restore-operations).

Setelah semua alamat, folder, dan izin tersedia, ubah `FINANCE_CLONE_ENABLED=true`, jalankan `php artisan config:clear`, lalu `php artisan finance:clone-from-a` dari **app-b** untuk menjalankan satu siklus nyata. Command ini benar-benar mengganti database B. Setelah berhasil, aktifkan scheduler B. C tetap mengambil data secara manual dari URL API B dengan key yang cocok.

### Mode demo satu instance

Demo lokal yang sudah berjalan memakai `FINANCE_CLONE_TRANSPORT=local` secara eksplisit pada `.env` B; koneksinya tidak dipindahkan ke alamat contoh. Skrip root `database/clone-a-to-b.ps1` juga tersedia untuk backup/restore manual satu instance. Skrip lokal memerlukan `sqlcmd`, Windows Authentication, hak backup/restore dan direktori yang dapat ditulis layanan SQL Server.

```powershell
.\database\clone-a-to-b.ps1 -ServerInstance 'localhost\SQLEXPRESS' `
  -SourceDatabase db_toko_a -TargetDatabase db_keuangan_b `
  -BackupFile 'D:\SqlBackups\toko_a_for_b.bak' `
  -DataFile 'D:\SqlData\db_keuangan_b.mdf' `
  -LogFile 'D:\SqlData\db_keuangan_b_log.ldf' -WhatIf
```

Periksa simulasi, kemudian hapus `-WhatIf` untuk eksekusi langsung skrip root. SQL Server lokal pada demo ini memakai sertifikat lokal; gunakan `-TrustServerCertificate` untuk koneksi lokal tersebut. Jika B sudah ada, skrip root memerlukan `-AllowReplaceTarget` dan konfirmasi. Backup file yang disebutkan juga ditimpa; gunakan file khusus proses ini. Data tambahan milik B harus berada di database lain karena full restore mengganti seluruh database B. Clone otomatis menggunakan skrip milik B (`app-b/database/clone-a-to-b.ps1`) dalam mode unattended, sesuai konfigurasi clone yang diaktifkan.

## Konfigurasi B

Edit `app-b/.env`, bukan `.env` A. Gunakan `DB_CONNECTION=sqlsrv`, host/kredensial MSSQL B, `DB_DATABASE=db_keuangan_b`, `FINANCE_PUBLISHER_ENABLED=true` dan `FINANCE_SOURCE_CONNECTION=sqlsrv`. Konfigurasi clone jaringan dijelaskan di atas; `.env.example` menonaktifkan clone sampai koneksi siap. `API_GATEWAY_KEY` B harus sama dengan `FINANCE_UPSTREAM_KEY` C. Publisher API hanya memerlukan akses baca, sedangkan akun pelaksana clone memerlukan hak backup/restore. Mode jaringan mendukung SQL Authentication atau Windows Authentication; `DB_URL` tidak didukung untuk job clone, gunakan variabel host/port/username/password eksplisit.

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
