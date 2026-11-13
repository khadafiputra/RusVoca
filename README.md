# RusVoca

Aplikasi web sederhana untuk belajar kosakata dan alfabet bahasa Rusia. Dibuat
dengan PHP dan MySQL, berjalan di Laragon/XAMPP, dan tidak membutuhkan
framework atau proses build apa pun.

Antarmuka tersedia dalam **Bahasa Indonesia** dan **English** (tombol ID/EN),
materi tetap memakai teks sumbernya.

---

## 1. Fitur

### Untuk siswa

- **Dashboard** - ringkasan progres, XP, level, target harian, dan riwayat
  terakhir.
- **12 bab materi** dengan 144 kosakata, contoh kalimat, dan terjemahan.
- **6 cara belajar per bab**:
  - Kosakata (daftar kata + pelafalan)
  - Flashcard
  - Kuis Kosa Kata (dinilai)
  - Kuis Audio / Listening (dinilai)
  - Latihan Berbicara / Speaking (dinilai)
  - Latihan Menulis / Writing (dinilai)
- **Alfabet Kiril** - 33 huruf dengan bentuk, pelafalan, dan contoh.
- **Riwayat** - daftar sesi kuis, skor, tanggal, dan **detail jawaban**
  (jawaban benar/salah per soal).
- **Profil** - ubah nama, target belajar harian (10/15/30 menit), dan password.
- **Lupa password** - verifikasi kode OTP 6 digit (lihat catatan di bagian
  keamanan).
- **Pelafalan otomatis** memakai Web Speech API bawaan browser.

### Untuk admin

- **Dashboard admin** - statistik pengguna, materi, dan aktivitas.
- **Kelola materi** - tambah, ubah, hapus pelajaran dan kosakata.
- **Kelola pengguna** - lihat akun, ubah role, hapus akun.
- **Rekap nilai** - rekap skor per siswa dan per mode.

---

## 2. Kebutuhan

| Komponen | Versi yang dipakai |
| --- | --- |
| PHP | 8.1+ (ekstensi `mysqli`, `mbstring`) |
| MySQL | 8.0+ (MariaDB 10.4+ juga bisa) |
| Web server | Apache (Laragon/XAMPP) dengan `mod_rewrite` dan `mod_authz_core` |
| Browser | Chrome/Edge/Firefox terbaru |

Tidak perlu Composer, Node.js, atau build step.

---

## 3. Struktur folder

```
rusvoca/
├── admin/                 Halaman admin (dashboard, materi, user, nilai)
├── assets/
│   ├── css/               auth.css, learn-ui.css
│   ├── img/               logo, ilustrasi, background
│   └── js/                audio.js, sound.js, quiz-guard.js
├── config/
│   ├── koneksi.php        Koneksi database + penyelarasan zona waktu
│   ├── keamanan.php       Sesi, CSRF, rate limit, OTP, timezone
│   ├── bahasa.php         Terjemahan ID/EN
│   ├── auth.php           Helper role siswa/admin
│   ├── progres.php        Helper target belajar harian
│   └── riwayat_kuis.php   Simpan/baca riwayat + detail jawaban
├── database/
│   ├── rusvoca_install.sql  Instalasi lengkap (struktur + materi)
│   ├── perbaikan.sql        Migrasi/perbaikan untuk database lama
│   ├── materi_pelajaran.sql Upgrade materi & kosakata
│   └── riwayat_kuis.sql     Tabel riwayat (dibuat otomatis oleh aplikasi)
├── storage/
│   ├── throttle/          File rate limit (tidak di-commit)
│   └── backup/            Arsip lokal (tidak di-commit)
├── *.php                  Halaman & proses aplikasi
├── .htaccess              Blokir file sensitif
└── README.md
```

Halaman utama: `index.php` (landing), `login.php`, `register.php`,
`dashboard.php`, `kosakata.php`, `alfabet.php`, `riwayat.php`, `profil.php`.

---

## 4. Instalasi

### Langkah 1 - Salin proyek

Letakkan folder proyek di web root Laragon:

```
C:\laragon\www\rusvoca
```

### Langkah 2 - Import database

Cara termudah memakai file instalasi lengkap:

**phpMyAdmin**

1. Buka `http://localhost/phpmyadmin`.
2. Tab **Import**.
3. Pilih `database/rusvoca_install.sql`, lalu **Go**.

**Terminal**

```bash
mysql -u root < database/rusvoca_install.sql
```

File tersebut akan:

- membuat database `db_rusvoca` bila belum ada,
- **menghapus lalu membuat ulang** semua tabel,
- mengisi 12 bab materi, 144 kosakata, dan 33 huruf alfabet,
- **tidak** mengisi akun atau riwayat belajar.

> **Peringatan:** jangan import `rusvoca_install.sql` ke database yang sudah
> berisi data penting, karena tabel lama akan diganti.

Tabel yang dibuat: `tb_akun`, `tb_pelajaran`, `tb_kosakata`, `tb_alfabet`,
`tb_progres_belajar`, `tb_target_harian`, `tb_sesi_kuis`, `tb_jawaban_kuis`.

### Langkah 3 - Sesuaikan koneksi

Buka `config/koneksi.php` dan sesuaikan bila perlu:

```php
$host     = "localhost";
$user     = "root";
$password = "";            // isi password MySQL bila ada
$database = "db_rusvoca";
```

Zona waktu MySQL otomatis diset `+07:00` agar sama dengan PHP
(`Asia/Jakarta`).

### Langkah 4 - Buka aplikasi

```
http://localhost/rusvoca/
```

Daftar akun lewat halaman **Daftar**, lalu masuk.

### Langkah 5 - Jadikan admin (opsional)

Akun baru selalu berrole `siswa`. Untuk membuka panel admin, ubah role lewat
phpMyAdmin atau terminal:

```sql
UPDATE tb_akun SET role = 'admin' WHERE email = 'email-kamu@contoh.com';
```

Logout lalu login kembali agar role baru terbaca. Panel admin ada di
`http://localhost/rusvoca/admin/`.

### Upgrade database lama

Kalau memakai database yang sudah ada dan tidak ingin import ulang:

1. `database/perbaikan.sql` - perbaikan struktur (wajib untuk database lama).
2. `database/materi_pelajaran.sql` - menambah materi/kosakata baru (aman
   dijalankan berulang).
3. `database/riwayat_kuis.sql` - tabel riwayat; sebenarnya dibuat otomatis
   oleh aplikasi saat pertama dipakai.

`config/koneksi.php` juga punya "penjaga skema" yang otomatis mengubah kolom
`mode_belajar` dari ENUM lama menjadi `VARCHAR(50)`.

---

## 5. Keamanan

Yang sudah diterapkan:

- **Password** disimpan dengan `password_hash()` dan diverifikasi dengan
  `password_verify()`.
- **CSRF token** pada semua form POST (login, register, profil, admin, simpan
  skor, ganti bahasa, logout).
- **Sesi dikeraskan**: cookie `HttpOnly`, `SameSite=Lax`, `Secure` otomatis
  saat HTTPS, `session.use_strict_mode=1`, `use_only_cookies=1`, dan
  `session_regenerate_id(true)` setelah login.
- **Rate limit** berbasis file di `storage/throttle/`:
  - login: 5 kegagalan / 15 menit per IP+email, 30 kegagalan / 15 menit per IP
  - register: 10 kiriman / jam per IP
  - minta OTP: 5 / jam per IP+email + jeda 30 detik
  - tebak kode reset: 10 / 15 menit per IP+email + 5 percobaan per sesi
- **Pesan login generik** agar tidak membocorkan email mana yang terdaftar.
- **Password minimal 6 karakter**, email divalidasi, konfirmasi password wajib.
- **File sensitif diblokir** lewat `.htaccess`: `.sql`, `.env`, `.ini`, `.log`,
  `.bak`, `.md`, dan akses folder `config/`, `database/`, `storage/` → 403.
- **Error produksi** tidak ditampilkan ke pengunjung (hanya di localhost).
- **Zona waktu** PHP dan MySQL disamakan ke WIB.

### Catatan penting: OTP & email

Pengiriman email **belum diaktifkan** pada versi ini. Saat berjalan di
localhost, kode OTP lupa password ditampilkan langsung di halaman agar alur
bisa diuji. Di server produksi kode tidak ditampilkan, sehingga fitur reset
password belum bisa dipakai sampai pengiriman email (SMTP) ditambahkan.

Kode OTP berlaku 15 menit dan disimpan di kolom `reset_otp` /
`reset_otp_expired`. Jangan menghapus kedua kolom tersebut.

---

## 6. Checklist pengujian manual

Setelah instalasi, cek hal berikut:

1. `index.php` tampil normal, tombol ID/EN berfungsi.
2. Register akun baru → berhasil masuk dashboard.
3. Logout → login kembali dengan akun tersebut.
4. Buka satu bab → coba Kosakata, Flashcard, dan 4 mode penilaian.
5. Selesaikan kuis → skor tersimpan dan muncul di `riwayat.php`.
6. Buka detail riwayat → jawaban per soal tampil benar.
7. Ubah target harian dan password di `profil.php`.
8. Lupa password di localhost → OTP tampil → reset berhasil.
9. Ubah role akun menjadi `admin` → panel `admin/` bisa dibuka.
10. Coba akses `database/rusvoca_install.sql` dari browser → harus **403**.
11. Pastikan tidak ada warning PHP atau error console di halaman utama.
12. Cek jam: `SELECT NOW();` di MySQL harus sama dengan `date('Y-m-d H:i:s')`
    di PHP (WIB).

Semua 34 file PHP dapat dicek sintaksnya dengan:

```bash
php -l namafile.php
```

---

## 7. Batasan yang diketahui

- **Email OTP belum dikirim** (lihat catatan di atas).
- **Audio** memakai Web Speech API, jadi kualitas dan ketersediaan suara
  tergantung browser/OS. Belum ada file audio Rusia bawaan.
- **Rate limit** berbasis file lokal; cocok untuk satu server. Jika di belakang
  reverse proxy, `REMOTE_ADDR` perlu disesuaikan.
- **Query database** memakai `mysqli` + escaping, belum prepared statement.
  Untuk pengembangan lanjutan, prepared statement lebih disarankan.
- **Belum ada pagination** untuk riwayat/materi dalam jumlah sangat besar.
- **Belum ada automated test**; pengujian masih manual.
- Mode **Game Memori** dan **Tic-Tac-Toe** sudah dihapus dari alur, data lama
  tetap ditampilkan sebagai arsip.

---

## 8. Pengembangan lanjutan (opsional)

- Integrasi SMTP untuk OTP produksi.
- File audio pelafalan Rusia.
- Mode review kesalahan jawaban.
- Pagination riwayat dan rekap nilai.
- Migrasi query ke prepared statement.
- Automated test (PHPUnit) dan CI.

---

## 9. Lisensi & kredit

Proyek pembelajaran. Materi kosakata dan ilustrasi dipakai untuk keperluan
edukasi. Silakan sesuaikan lisensi sebelum dipublikasikan.
