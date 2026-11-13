-- =====================================================================
-- RusVoca - Perbaikan Database (db_rusvoca)
-- Dibuat: 2026-10-18
-- Server : MySQL 8.0 / MariaDB (Laragon) - port 3306
--
-- CARA PAKAI:
--   1. Buka phpMyAdmin -> pilih database `db_rusvoca` -> tab SQL
--   2. Jalankan bagian yang Anda butuhkan (A/B/C sudah pernah dijalankan
--      saat perbaikan aplikasi; cukup jalankan jika meng-import ulang DB).
--   3. Bagian D/E bersifat OPSIONAL dan menghapus kolom -> baca dulu.
--
-- CATATAN: Bagian A juga otomatis diperbaiki oleh aplikasi lewat
--          config/koneksi.php (self-healing), jadi aplikasi tetap aman
--          walau database di-import ulang dengan skema lama.
--
-- FILE TERKAIT: materi_pelajaran.sql
--          Menambah kolom materi/kelompok/contoh kalimat + 12 bab materi
--          kosakata gaya LingoHut beserta kuisnya.
-- =====================================================================


-- =====================================================================
-- BAGIAN A [WAJIB] - Perbaikan kolom mode_belajar
-- ---------------------------------------------------------------------
-- MASALAH: kolom bertipe ENUM lama:
--   enum('kosakata','flashcard','kuis_kosakata','game_memori',
--        'kuis_mendengarkan','catur_jawa')
-- Aplikasi menyimpan kode mode baru (mode_memori, mode_tictactoe,
-- mode_speaking, mode_writing) yang TIDAK ADA di ENUM itu, sehingga
-- MySQL melempar error:
--   "Data truncated for column 'mode_belajar' at row 1"
-- dan kuis gagal menyimpan skor + halaman error.
-- SOLUSI: ubah ENUM menjadi VARCHAR(50) agar semua kode mode diterima.
-- =====================================================================
ALTER TABLE tb_progres_belajar
    MODIFY mode_belajar VARCHAR(50) NOT NULL;

-- Migrasi kode mode lama -> kode baru agar skor/badge lama tetap terbaca
UPDATE tb_progres_belajar SET mode_belajar = 'mode_memori'    WHERE mode_belajar = 'game_memori';
UPDATE tb_progres_belajar SET mode_belajar = 'mode_tictactoe' WHERE mode_belajar = 'catur_jawa';


-- =====================================================================
-- BAGIAN B [DISARANKAN] - Index unik untuk mencegah data ganda
-- ---------------------------------------------------------------------
-- MASALAH: aplikasi menyimpan skor dengan pola "SELECT lalu INSERT/UPDATE".
-- Tanpa index unik, tombol simpan yang ter-klik dua kali (atau dua tab)
-- bisa membuat BARIS GANDA untuk user + pelajaran + mode yang sama,
-- sehingga riwayat jadi dobel.
-- Sudah dipastikan tidak ada duplikat saat index ini dibuat.
-- =====================================================================
ALTER TABLE tb_progres_belajar
    ADD UNIQUE KEY uniq_user_kelas_mode (user_id, id_pelajaran, mode_belajar);

-- Satu baris target per user per hari
-- PENTING: index ini WAJIB untuk fitur Target Harian, karena aplikasi
-- memakai "INSERT ... ON DUPLICATE KEY UPDATE" saat menambah durasi belajar.
ALTER TABLE tb_target_harian
    ADD UNIQUE KEY uniq_user_tanggal (user_id, tanggal);


-- =====================================================================
-- BAGIAN C [DISARANKAN] - Perbaikan data
-- ---------------------------------------------------------------------
-- 1) Semua akun lama memiliki tanggal_daftar = NULL sehingga profil
--    menampilkan "Bergabung sejak -". Diisi dari created_at.
-- =====================================================================
UPDATE tb_akun
    SET tanggal_daftar = DATE(created_at)
    WHERE tanggal_daftar IS NULL;

-- 2) Samakan penanda "selesai": setiap skor >= 60 (batas lulus) dianggap tuntas.
UPDATE tb_progres_belajar
    SET status_selesai = 1
    WHERE skor >= 60 AND status_selesai = 0;


-- =====================================================================
-- BAGIAN D [OPSIONAL - MENGHAPUS KOLOM] - Bersihkan kolom sisa
-- ---------------------------------------------------------------------
-- Jalankan hanya jika Anda yakin tidak butuh datanya lagi.
--
-- 1) tb_akun.target_menit_harian  -> duplikat dari target_waktu
--    (aplikasi memakai `target_waktu`; target_menit_harian tidak dipakai).
--    Perhatikan: default keduanya beda (10 vs 15) dan membingungkan.
-- =====================================================================
-- ALTER TABLE tb_akun DROP COLUMN target_menit_harian;

-- 2) PENTING: JANGAN hapus kolom reset_otp & reset_otp_expired.
--    Sejak fitur "Lupa Password" diaktifkan kembali, kedua kolom ini
--    WAJIB ada. Menghapusnya akan membuat permintaan kode reset gagal.
-- =====================================================================
-- (Baris DROP untuk reset_otp sengaja dihapus dari file ini.)


-- =====================================================================
-- BAGIAN E [OPSIONAL] - Cegah kosakata ganda dalam satu pelajaran
-- ---------------------------------------------------------------------
-- Jalankan hanya jika Anda tidak pernah butuh kata Rusia yang sama dua
-- kali di satu bab (mis. untuk arti berbeda).
-- =====================================================================
-- ALTER TABLE tb_kosakata
--     ADD UNIQUE KEY uniq_kelas_kata (id_pelajaran, kata_rusia);


-- =====================================================================
-- BAGIAN F [SUDAH DIPERBAIKI] - Pelajaran tanpa kosakata
-- ---------------------------------------------------------------------
-- Catatan lama: pelajaran id = 2 ("Iya dan Terima Kasih") punya 0
-- kosakata. Kini sudah dilengkapi oleh `materi_pelajaran.sql`
-- (12 bab, masing-masing 12 kata + contoh kalimat + materi bacaan).
-- Query berikut tetap berguna untuk memeriksa kelengkapan data:
-- =====================================================================
-- SELECT p.id_pelajaran, p.judul_pelajaran, COUNT(k.id_kosakata) AS jumlah_kata
-- FROM tb_pelajaran p
-- LEFT JOIN tb_kosakata k ON p.id_pelajaran = k.id_pelajaran
-- GROUP BY p.id_pelajaran, p.judul_pelajaran;

-- Contoh menambah kosakata untuk pelajaran id = 2 (sesuaikan isinya):
-- INSERT INTO tb_kosakata (id_pelajaran, kata_rusia, cara_baca, arti_indonesia) VALUES
--   (2, 'да',      'da',      'iya'),
--   (2, 'нет',     'nyet',    'tidak'),
--   (2, 'спасибо', 'spasiba', 'terima kasih');
