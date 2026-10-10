# PoC API Gateway: Aplikasi Toko A (KANMIS) ke Aplikasi B (SI-ISPO)

Proyek ini adalah **Proof of Concept (PoC)** untuk mendemonstrasikan pertukaran data yang aman antar dua sistem yang berbeda menggunakan arsitektur **API Gateway** berbasis Laravel dan **Microsoft SQL Server**.

## 🏗 Konsep Arsitektur
Dalam skenario dunia nyata:
- **Aplikasi A (Toko A / KANMIS)**: Bertindak sebagai **Data Provider**. Memiliki database SQL Server raksasa yang menyimpan data rahasia (seperti Gaji Pegawai) dan data yang boleh dibagikan (seperti Transaksi Harian).
- **Aplikasi B (SI-ISPO)**: Bertindak sebagai **Data Consumer**. Aplikasi luar yang membutuhkan data dari KANMIS tanpa diberikan akses langsung ke database.

**Solusi PoC**:
Aplikasi A menyediakan *"Jembatan"* berupa endpoint API (`/api/v1/transaksi/harian`) yang diamankan menggunakan `X-API-KEY`. Aplikasi B (disimulasikan dengan file `demo.html`) bertugas menarik data dari jembatan tersebut.

---

## 🛠 Persyaratan Sistem (Prerequisites)
Sebelum menjalankan proyek ini, pastikan komputer Anda sudah terinstal:
1. **PHP 8.2 / 8.3** (Rekomendasi menggunakan Laragon atau XAMPP).
2. **Composer** (Package manager PHP).
3. **Microsoft SQL Server Engine** (Minimal versi Express Edition 2019/2022).
4. **Azure Data Studio** atau **SSMS** (Untuk meremote database).
5. ⚠️ **SUPER PENTING: Driver SQL Server untuk PHP**.
   *(Jika Anda melewatkan ini, Anda akan mendapat error `could not find driver`!)*
   - Download driver resmi Microsoft: [SQLSRV Driver](https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server?view=sql-server-ver17).
   - Ekstrak dan *copy* file `php_pdo_sqlsrv_XX_ts_x64.dll` & `php_sqlsrv_XX_ts_x64.dll` ke dalam folder `ext` milik PHP Anda.
   - Daftarkan `extension=pdo_sqlsrv_...` di dalam file `php.ini`.
   - **Restart web server (Laragon/Apache) Anda.**

---

## 🚀 Panduan Instalasi (Step-by-Step)

### 1. Persiapan Database
1. Buka Azure Data Studio / SSMS.
2. Login menggunakan **Windows Authentication** ke server lokal Anda (misal: `localhost\SQLEXPRESS` atau `(localdb)\MSSQLLocalDB`).
3. Buka file `database_setup.sql` yang ada di root folder ini.
4. Klik **Run / Execute**. Script ini akan otomatis membuatkan database `db_toko_a`, membuat 2 tabel, dan mengisi *dummy data*.

### 2. Konfigurasi Proyek Laravel
1. Buka terminal (Command Prompt / VS Code Terminal) di dalam folder proyek ini.
2. Jalankan perintah instalasi pustaka Laravel:
   ```bash
   composer install
   ```
3. Copy file `.env.example` menjadi `.env` (jika belum ada).
4. Sesuaikan konfigurasi Database di `.env` (Jika pakai Windows Auth, kosongkan username/password):
   ```env
   DB_CONNECTION=sqlsrv
   DB_HOST=localhost\SQLEXPRESS
   DB_PORT=
   DB_DATABASE=db_toko_a
   DB_USERNAME=
   DB_PASSWORD=
   DB_TRUST_SERVER_CERTIFICATE=true
   
   # Kunci Rahasia API
   API_GATEWAY_KEY=secret_toko_a_2026
   
   # Gunakan file session agar tidak perlu membuat tabel tambahan
   SESSION_DRIVER=file
   CACHE_STORE=file
   ```
5. Buat kunci enkripsi aplikasi:
   ```bash
   php artisan key:generate
   ```

---

## 🎮 Cara Menjalankan & Mendemokan Aplikasi

Jalankan server lokal bawaan Laravel (Pastikan port 8001 tidak dipakai aplikasi lain):
```bash
php artisan serve --port=8001
```

### Skenario Presentasi:
1. **Pamerkan Layar Admin (Aplikasi A / Provider):**
   - Buka browser ke: **[http://localhost:8001/admin](http://localhost:8001/admin)**
   - **Tunjukkan:** Di sini Admin Toko A bisa melihat semua data, termasuk **Data Pegawai (Rahasia)** dan **Data Transaksi**.
   
2. **Pamerkan Layar Consumer (Aplikasi B / SI-ISPO):**
   - Buka Tab baru di browser ke: **[http://localhost:8001/demo.html](http://localhost:8001/demo.html)**
   - **Tunjukkan Keamanan:** Coba klik tombol "Tarik Data" dengan API Key yang disalahkan. Hasilnya akan gagal (Error 401 Unauthorized), membuktikan API terlindungi dari *Hacker*.
   - **Tunjukkan Privasi Data:** Masukkan API Key yang benar (`secret_toko_a_2026`). Hasilnya sukses, namun *perhatikan* bahwa Aplikasi B **HANYA** mendapatkan "Data Transaksi" saja. Data Pegawai tetap aman tidak terbocorkan.

---
*Dibuat untuk kebutuhan eksperimen dan pembuktian konsep (PoC).*
