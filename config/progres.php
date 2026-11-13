<?php
/**
 * config/progres.php
 * ---------------------------------------------------------------------------
 * Helper pencatatan durasi belajar untuk "Target Belajar Harian".
 * Durasi dikumpulkan di tabel tb_target_harian (1 baris per user per hari).
 * ---------------------------------------------------------------------------
 */

/**
 * Target menit harian milik user (10/15/30, default 15).
 *
 * @param mysqli $koneksi Koneksi database aktif.
 * @param int $user_id ID user.
 * @return int Target menit per hari.
 */
function target_harian_user(mysqli $koneksi, int $user_id): int
{
    $target = 15;
    $q = mysqli_query($koneksi, "SELECT target_waktu FROM tb_akun WHERE id = $user_id");
    if ($q) {
        $d = mysqli_fetch_assoc($q);
        if ($d && isset($d['target_waktu']) && in_array((int)$d['target_waktu'], [10, 15, 30], true)) {
            $target = (int)$d['target_waktu'];
        }
    }
    return $target;
}

/**
 * Tandai waktu mulai mengerjakan satu mode untuk satu pelajaran.
 * Dipanggil saat halaman mode dibuka (bukan saat menyimpan skor).
 */
function tandai_mulai_belajar(int $id_pelajaran, string $mode): void
{
    if ($id_pelajaran > 0 && $mode !== '') {
        $_SESSION['belajar_mulai'][$id_pelajaran][$mode] = time();
    }
}

/**
 * Hitung durasi sesi yang baru diselesaikan lalu tambahkan ke target harian.
 * Minimal 1 menit setiap menyelesaikan latihan, dibatasi maksimal 60 menit
 * agar tidak melebih-lebihkan bila tab dibiarkan terbuka lama.
 *
 * @return int Jumlah menit yang dicatat.
 */
function catat_durasi_belajar(mysqli $koneksi, int $user_id, int $id_pelajaran, string $mode): int
{
    if ($user_id <= 0 || $id_pelajaran <= 0 || $mode === '') {
        return 0;
    }

    $mulai = $_SESSION['belajar_mulai'][$id_pelajaran][$mode] ?? null;
    $menit = 1; // minimal 1 menit tiap menyelesaikan satu latihan
    if (is_numeric($mulai) && (int)$mulai > 0) {
        $detik = time() - (int)$mulai;
        $menit = max(1, (int)round($detik / 60));
        if ($menit > 60) {
            $menit = 60;
        }
    }
    // Hindari terhitung dua kali bila form ter-submit ulang.
    unset($_SESSION['belajar_mulai'][$id_pelajaran][$mode]);

    $target = target_harian_user($koneksi, $user_id);
    $hari   = date('Y-m-d');

    // Tambah durasi hari ini (butuh index unik uniq_user_tanggal).
    mysqli_query($koneksi, "
        INSERT INTO tb_target_harian (user_id, tanggal, durasi_menit, status_tercapai)
        VALUES ($user_id, '$hari', $menit, 0)
        ON DUPLICATE KEY UPDATE durasi_menit = durasi_menit + $menit
    ");

    // Perbarui status tercapai berdasarkan total menit hari ini.
    mysqli_query($koneksi, "
        UPDATE tb_target_harian
        SET status_tercapai = IF(durasi_menit >= $target, 1, 0)
        WHERE user_id = $user_id AND tanggal = '$hari'
    ");

    return $menit;
}
