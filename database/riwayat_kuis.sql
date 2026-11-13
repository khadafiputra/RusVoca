-- ============================================================================
-- Riwayat pengerjaan kuis + detail jawaban
-- Jalankan file ini sekali, atau biarkan config/riwayat_kuis.php membuatnya
-- otomatis saat aplikasi dipakai.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `tb_sesi_kuis` (
    `id_sesi` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `id_pelajaran` INT NOT NULL,
    `mode_belajar` VARCHAR(50) NOT NULL,
    `skor` INT NOT NULL DEFAULT 0,
    `jumlah_benar` INT NOT NULL DEFAULT 0,
    `jumlah_soal` INT NOT NULL DEFAULT 0,
    `tanggal_akses` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_sesi`),
    KEY `idx_sesi_user_tanggal` (`user_id`, `tanggal_akses`),
    KEY `idx_sesi_pelajaran` (`id_pelajaran`),
    CONSTRAINT `fk_sesi_user`
        FOREIGN KEY (`user_id`) REFERENCES `tb_akun` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sesi_pelajaran`
        FOREIGN KEY (`id_pelajaran`) REFERENCES `tb_pelajaran` (`id_pelajaran`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_jawaban_kuis` (
    `id_jawaban` INT NOT NULL AUTO_INCREMENT,
    `id_sesi` INT NOT NULL,
    `urutan` INT NOT NULL DEFAULT 0,
    `pertanyaan` VARCHAR(255) NOT NULL,
    `jawaban_user` VARCHAR(255) NOT NULL DEFAULT '',
    `jawaban_benar` VARCHAR(255) NOT NULL DEFAULT '',
    `is_benar` TINYINT(1) NOT NULL DEFAULT 0,
    `tipe` VARCHAR(30) NOT NULL DEFAULT 'teks',
    PRIMARY KEY (`id_jawaban`),
    KEY `idx_jawaban_sesi` (`id_sesi`),
    CONSTRAINT `fk_jawaban_sesi`
        FOREIGN KEY (`id_sesi`) REFERENCES `tb_sesi_kuis` (`id_sesi`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
