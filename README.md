# Dimzz Donasi — Platform Kebaikan Digital Indonesia

Aplikasi web portal donasi online berbasis PHP Native dan MySQL dengan antarmuka modern, interaktif, dan aman.

---

## 🌟 Fitur Utama

1. **Portal Donasi Interaktif (`db.php`)**:
   - Banner Slider Carousel otomatis & responsif.
   - Filter Kategori: *Darurat Bencana*, *Yatim & Dhuafa*, *Pendidikan*, *Pemberdayaan*, dan *Kesehatan*.
   - Pencarian campaign real-time.
   - Floating Notification donasi live terkini.
2. **Kuitansi Donasi Digital Resmi (`kuitansi.php`)**:
   - Bukti donasi digital sah dengan nomor invoice unik, rincian dana, doa donatur, dan stempel digital *Verified Dimzz System*.
   - Fitur **Cetak / Simpan PDF** siap cetak A4/A5.
   - Tombol **Kirim ke WhatsApp 💬** otomatis dengan teks ucapan terima kasih terformat rapi.
3. **Keamanan & Proteksi Fondasi**:
   - Hashing password Bcrypt modern (`password_hash` & `password_verify`).
   - Fitur *Auto-Migration* password transparan untuk akun lama.
   - Token proteksi Anti-CSRF (`csrf.php`) di seluruh form.
4. **Panel Administrator (`admin.php`)**:
   - Ringkasan KPI: Total Dana Terkumpul, Donasi Lunas, Transaksi Menunggu, dan Program Aktif.
   - Manajemen donasi, verifikasi pembayaran, export CSV data donasi.
   - **Modul Penyaluran Dana (LPJ Transparansi)**: Dokumentasi realisasi penyaluran dana ke masyarakat dengan kalkulasi sisa saldo.
   - Manajemen program donasi, kategori, metode pembayaran, pengumuman/hadist, dan akun pengguna.

---

## 🛠️ Persyaratan Sistem

- **Web Server**: Apache / Nginx (Laragon / XAMPP / Live Hosting)
- **PHP**: Versi 7.4, 8.0, 8.1, atau 8.2+
- **MySQL / MariaDB**: Versi 5.7+ / 8.0+

---

## 🚀 Panduan Instalasi Lokal (Laragon / XAMPP)

1. Letakkan folder ini di direktori web server:
   - Laragon: `C:\laragon\www\donasi`
   - XAMPP: `C:\xampp\htdocs\donasi`
2. Buka phpMyAdmin (`http://localhost/phpmyadmin/`), buat database baru bernama `donasi`.
3. Import file `donasi_schema.sql` ke dalam database `donasi`.
4. Salin file konfigurasi environment:
   ```bash
   cp .env.example .env
   ```
   *(Secara bawaan, konfigurasi sudah siap berjalan untuk user `root` tanpa password di localhost).*
5. Buka browser dan akses:
   - Portal Utama: `http://localhost/donasi/db.php`
   - Halaman Login: `http://localhost/donasi/index.php`
   - Akun Admin Bawaan:
     - **Email**: `admin@dimzz.id`
     - **Password**: `admin123`

---

## 🌐 Panduan Deployment ke Cloud Hosting (cPanel / VPS / Railway)

### Opsi A: Shared Hosting / cPanel (Niagahoster, IDCloudHost, DomaiNesia)
1. Compress seluruh isi folder `donasi` menjadi file `.zip` (pastikan file `.env.example` dan `.htaccess` ikut terbawa).
2. Di cPanel File Manager, upload dan ekstrak ke folder `public_html` (atau subdomain).
3. Di menu **MySQL Database Wizard**, buat database dan user database baru.
4. Di **phpMyAdmin** hosting, import file `donasi_schema.sql`.
5. Buat file `.env` di folder root hosting dan isi kredensial database hosting Anda:
   ```env
   DB_HOST=localhost
   DB_USER=u1234567_donasi
   DB_PASS=PasswordKuatAnda123!
   DB_NAME=u1234567_donasi
   DB_PORT=3306
   APP_URL=https://domainanda.com
   ```
6. Website siap beroperasi 24/7!

---

## 📄 Lisensi
Hak Cipta © 2026 Dimzz Donasi. Seluruh Hak Dilindungi.
