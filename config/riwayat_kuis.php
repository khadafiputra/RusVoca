<?php
/**
 * config/riwayat_kuis.php
 * ---------------------------------------------------------------------------
 * Menyimpan setiap sesi kuis beserta rincian jawaban, lalu membacanya kembali
 * untuk halaman Riwayat. Tabel dibuat otomatis jika belum ada.
 * ---------------------------------------------------------------------------
 */

/** Mode yang boleh menyimpan rincian jawaban. */
const MODE_KUIS_BERDETAIL = [
    'kuis_kosakata',
    'kuis_mendengarkan',
    'mode_speaking',
    'mode_writing',
];

/** Batasi panjang teks agar aman masuk kolom VARCHAR(255). */
function batasi_teks_kuis($teks, int $maks = 255): string
{
    $teks = trim((string)$teks);
    if (function_exists('mb_substr')) {
        return mb_substr($teks, 0, $maks, 'UTF-8');
    }
    return substr($teks, 0, $maks);
}

/** Pastikan tabel riwayat tersedia (aman dipanggil berulang). */
function pastikan_tabel_riwayat_kuis(mysqli $koneksi): void
{
    static $sudah = false;
    if ($sudah) { return; }

    mysqli_query($koneksi, "
        CREATE TABLE IF NOT EXISTS tb_sesi_kuis (
            id_sesi INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            id_pelajaran INT NOT NULL,
            mode_belajar VARCHAR(50) NOT NULL,
            skor INT NOT NULL DEFAULT 0,
            jumlah_benar INT NOT NULL DEFAULT 0,
            jumlah_soal INT NOT NULL DEFAULT 0,
            tanggal_akses DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_sesi),
            KEY idx_sesi_user_tanggal (user_id, tanggal_akses),
            KEY idx_sesi_pelajaran (id_pelajaran),
            CONSTRAINT fk_sesi_user FOREIGN KEY (user_id) REFERENCES tb_akun (id) ON DELETE CASCADE,
            CONSTRAINT fk_sesi_pelajaran FOREIGN KEY (id_pelajaran) REFERENCES tb_pelajaran (id_pelajaran) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    mysqli_query($koneksi, "
        CREATE TABLE IF NOT EXISTS tb_jawaban_kuis (
            id_jawaban INT NOT NULL AUTO_INCREMENT,
            id_sesi INT NOT NULL,
            urutan INT NOT NULL DEFAULT 0,
            pertanyaan VARCHAR(255) NOT NULL,
            jawaban_user VARCHAR(255) NOT NULL DEFAULT '',
            jawaban_benar VARCHAR(255) NOT NULL DEFAULT '',
            is_benar TINYINT(1) NOT NULL DEFAULT 0,
            tipe VARCHAR(30) NOT NULL DEFAULT 'teks',
            PRIMARY KEY (id_jawaban),
            KEY idx_jawaban_sesi (id_sesi),
            CONSTRAINT fk_jawaban_sesi FOREIGN KEY (id_sesi) REFERENCES tb_sesi_kuis (id_sesi) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $sudah = true;
}

/**
 * Simpan satu sesi kuis dan seluruh jawabannya.
 *
 * @param array $detail Daftar jawaban: pertanyaan, jawaban_user, jawaban_benar, benar, tipe.
 * @return int|null ID sesi, atau null jika tidak disimpan.
 */
function simpan_riwayat_kuis(
    mysqli $koneksi,
    int $user_id,
    int $id_pelajaran,
    string $mode,
    int $skor,
    array $detail
): ?int {
    if ($user_id <= 0 || $id_pelajaran <= 0 || !in_array($mode, MODE_KUIS_BERDETAIL, true)) {
        return null;
    }

    $bersih = [];
    foreach (array_slice($detail, 0, 100) as $d) {
        if (!is_array($d)) { continue; }
        $pertanyaan = batasi_teks_kuis($d['pertanyaan'] ?? '');
        if ($pertanyaan === '') { continue; }
        $bersih[] = [
            'pertanyaan'    => $pertanyaan,
            'jawaban_user'  => batasi_teks_kuis($d['jawaban_user'] ?? ''),
            'jawaban_benar' => batasi_teks_kuis($d['jawaban_benar'] ?? ''),
            'benar'         => !empty($d['benar']) ? 1 : 0,
            'tipe'          => batasi_teks_kuis($d['tipe'] ?? 'teks', 30),
        ];
    }

    if (!$bersih) { return null; }

    pastikan_tabel_riwayat_kuis($koneksi);

    $jumlah_soal  = count($bersih);
    $jumlah_benar = 0;
    foreach ($bersih as $b) { $jumlah_benar += $b['benar']; }

    mysqli_begin_transaction($koneksi);
    try {
        $stmt = mysqli_prepare($koneksi, "
            INSERT INTO tb_sesi_kuis
                (user_id, id_pelajaran, mode_belajar, skor, jumlah_benar, jumlah_soal, tanggal_akses)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) { throw new RuntimeException('Gagal menyiapkan sesi kuis.'); }
        mysqli_stmt_bind_param($stmt, 'iisiii', $user_id, $id_pelajaran, $mode, $skor, $jumlah_benar, $jumlah_soal);
        mysqli_stmt_execute($stmt);
        $id_sesi = (int)mysqli_insert_id($koneksi);
        mysqli_stmt_close($stmt);

        $stmt_j = mysqli_prepare($koneksi, "
            INSERT INTO tb_jawaban_kuis
                (id_sesi, urutan, pertanyaan, jawaban_user, jawaban_benar, is_benar, tipe)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt_j) { throw new RuntimeException('Gagal menyiapkan jawaban kuis.'); }

        $urutan = 0;
        foreach ($bersih as $b) {
            $urutan++;
            mysqli_stmt_bind_param(
                $stmt_j,
                'iisssis',
                $id_sesi,
                $urutan,
                $b['pertanyaan'],
                $b['jawaban_user'],
                $b['jawaban_benar'],
                $b['benar'],
                $b['tipe']
            );
            mysqli_stmt_execute($stmt_j);
        }
        mysqli_stmt_close($stmt_j);

        mysqli_commit($koneksi);
        return $id_sesi;
    } catch (Throwable $e) {
        mysqli_rollback($koneksi);
        return null;
    }
}

/**
 * Ambil riwayat sesi beserta rincian jawabannya, terbaru lebih dulu.
 *
 * @return array<int, array<string, mixed>>
 */
function ambil_riwayat_kuis(mysqli $koneksi, int $user_id, int $limit = 40): array
{
    if ($user_id <= 0) { return []; }
    pastikan_tabel_riwayat_kuis($koneksi);

    $limit = max(1, min(100, $limit));
    $sesi = [];
    $q = mysqli_query($koneksi, "
        SELECT s.id_sesi, s.id_pelajaran, s.mode_belajar, s.skor, s.jumlah_benar, s.jumlah_soal, s.tanggal_akses,
               p.judul_pelajaran, p.nomor_pelajaran
        FROM tb_sesi_kuis s
        LEFT JOIN tb_pelajaran p ON p.id_pelajaran = s.id_pelajaran
        WHERE s.user_id = $user_id
        ORDER BY s.tanggal_akses DESC, s.id_sesi DESC
        LIMIT $limit
    ");

    if ($q) {
        while ($row = mysqli_fetch_assoc($q)) {
            $row['detail'] = [];
            $sesi[(int)$row['id_sesi']] = $row;
        }
    }

    if (!$sesi) { return []; }

    $ids = implode(',', array_map('intval', array_keys($sesi)));
    $q_detail = mysqli_query($koneksi, "
        SELECT id_sesi, urutan, pertanyaan, jawaban_user, jawaban_benar, is_benar, tipe
        FROM tb_jawaban_kuis
        WHERE id_sesi IN ($ids)
        ORDER BY id_sesi ASC, urutan ASC
    ");
    if ($q_detail) {
        while ($d = mysqli_fetch_assoc($q_detail)) {
            $id = (int)$d['id_sesi'];
            if (isset($sesi[$id])) {
                $sesi[$id]['detail'][] = $d;
            }
        }
    }

    return array_values($sesi);
}
