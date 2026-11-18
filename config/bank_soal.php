<?php
/**
 * config/bank_soal.php
 * ---------------------------------------------------------------------------
 * Bank soal kuis per bab. Soal disimpan di tb_bank_soal dan dipakai oleh
 * Kuis Kata serta Kuis Audio. Jika bank soal suatu bab masih kosong, kuis
 * otomatis memakai soal dari kosakata (perilaku lama).
 * ---------------------------------------------------------------------------
 */

/** Pastikan tabel bank soal tersedia (aman dipanggil berulang). */
function pastikan_tabel_bank_soal(mysqli $koneksi): void
{
    static $sudah = false;
    if ($sudah) { return; }

    mysqli_query($koneksi, "
        CREATE TABLE IF NOT EXISTS tb_bank_soal (
            id_soal INT NOT NULL AUTO_INCREMENT,
            id_pelajaran INT NOT NULL,
            pertanyaan VARCHAR(255) NOT NULL,
            jawaban_benar VARCHAR(255) NOT NULL,
            pilihan_salah_1 VARCHAR(255) NOT NULL DEFAULT '',
            pilihan_salah_2 VARCHAR(255) NOT NULL DEFAULT '',
            pilihan_salah_3 VARCHAR(255) NOT NULL DEFAULT '',
            tanggal_dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_soal),
            KEY idx_bank_pelajaran (id_pelajaran),
            CONSTRAINT fk_bank_pelajaran FOREIGN KEY (id_pelajaran) REFERENCES tb_pelajaran (id_pelajaran) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $sudah = true;
}

/**
 * Ambil soal bank untuk satu bab (atau semua bab bila $id_pelajaran = 0).
 *
 * @return array<int, array<string, mixed>>
 */
function ambil_bank_soal(mysqli $koneksi, int $id_pelajaran = 0): array
{
    pastikan_tabel_bank_soal($koneksi);

    $where = $id_pelajaran > 0 ? "WHERE b.id_pelajaran = $id_pelajaran" : '';
    $q = mysqli_query($koneksi, "
        SELECT b.*, p.nomor_pelajaran, p.judul_pelajaran
        FROM tb_bank_soal b
        LEFT JOIN tb_pelajaran p ON p.id_pelajaran = b.id_pelajaran
        $where
        ORDER BY b.id_pelajaran ASC, b.id_soal ASC
    ");

    $hasil = [];
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $hasil[] = $r;
        }
    }
    return $hasil;
}

/**
 * Bangun daftar soal kuis siap pakai (word, read, correct, options).
 * Prioritas: bank soal. Fallback: seluruh kosakata bab tersebut.
 *
 * @param array<int, array<string, mixed>> $kosakata Kosakata bab terpilih.
 * @return array<int, array{word:string,read:string,correct:string,options:array<int,string>}>
 */
function siapkan_soal_kuis(mysqli $koneksi, int $id_pelajaran, array $kosakata): array
{
    $peta_baca = [];
    foreach ($kosakata as $k) {
        $kata = trim((string)($k['kata_rusia'] ?? ''));
        if ($kata !== '') {
            $peta_baca[$kata] = (string)($k['cara_baca'] ?? '');
        }
    }

    // 1) Coba bank soal.
    $bank = ambil_bank_soal($koneksi, $id_pelajaran);
    $dari_bank = [];
    foreach ($bank as $b) {
        $pertanyaan = trim((string)($b['pertanyaan'] ?? ''));
        $benar      = trim((string)($b['jawaban_benar'] ?? ''));
        if ($pertanyaan === '' || $benar === '') { continue; }

        $opsi = [$benar];
        for ($i = 1; $i <= 3; $i++) {
            $salah = trim((string)($b['pilihan_salah_' . $i] ?? ''));
            if ($salah !== '' && !in_array($salah, $opsi, true)) {
                $opsi[] = $salah;
            }
        }
        if (count($opsi) < 2) { continue; }

        shuffle($opsi);
        $dari_bank[] = [
            'word'    => $pertanyaan,
            'read'    => $peta_baca[$pertanyaan] ?? '',
            'correct' => $benar,
            'options' => $opsi,
        ];
    }
    if (count($dari_bank) >= 2) {
        shuffle($dari_bank);
        return $dari_bank;
    }

    // 2) Fallback: soal otomatis dari kosakata.
    $soal = [];
    foreach ($kosakata as $k) {
        $kata  = trim((string)($k['kata_rusia'] ?? ''));
        $benar = trim((string)($k['arti_indonesia'] ?? ''));
        if ($kata === '' || $benar === '') { continue; }

        $salah = [];
        foreach ($kosakata as $lain) {
            $arti = trim((string)($lain['arti_indonesia'] ?? ''));
            if ($arti !== '' && $arti !== $benar && !in_array($arti, $salah, true)) {
                $salah[] = $arti;
            }
        }
        shuffle($salah);
        $opsi = array_merge([$benar], array_slice($salah, 0, 3));
        if (count($opsi) < 2) { continue; }
        shuffle($opsi);

        $soal[] = [
            'word'    => $kata,
            'read'    => (string)($k['cara_baca'] ?? ''),
            'correct' => $benar,
            'options' => $opsi,
        ];
    }

    shuffle($soal);
    return $soal;
}
