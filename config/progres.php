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
 * Ambil durasi & status target harian user pada satu tanggal.
 *
 * @return array{menit:int,target:int,tercapai:bool,status:int}
 */
function durasi_harian_user(mysqli $koneksi, int $user_id, ?string $tanggal = null): array
{
    $tanggal = $tanggal ?: date('Y-m-d');
    $target  = target_harian_user($koneksi, $user_id);
    $menit   = 0;
    $status  = 0;

    $q = mysqli_query($koneksi, "SELECT durasi_menit, status_tercapai FROM tb_target_harian WHERE user_id = $user_id AND tanggal = '$tanggal'");
    if ($q) {
        $d = mysqli_fetch_assoc($q);
        if ($d) {
            $menit  = (int)($d['durasi_menit'] ?? 0);
            $status = (int)($d['status_tercapai'] ?? 0);
        }
    }

    return [
        'menit'    => $menit,
        'target'   => $target,
        'tercapai' => $menit >= $target,
        'status'   => $status,
    ];
}

/**
 * Tambah menit belajar ke target harian.
 *
 * @return bool True bila target baru saja tercapai pada penambahan ini.
 */
function tambah_durasi_harian(mysqli $koneksi, int $user_id, int $menit): bool
{
    if ($user_id <= 0 || $menit <= 0) { return false; }

    $target = target_harian_user($koneksi, $user_id);
    $hari   = date('Y-m-d');

    $sebelum = 0;
    $q = mysqli_query($koneksi, "SELECT status_tercapai FROM tb_target_harian WHERE user_id = $user_id AND tanggal = '$hari'");
    if ($q) {
        $d = mysqli_fetch_assoc($q);
        if ($d) { $sebelum = (int)($d['status_tercapai'] ?? 0); }
    }

    mysqli_query($koneksi, "
        INSERT INTO tb_target_harian (user_id, tanggal, durasi_menit, status_tercapai)
        VALUES ($user_id, '$hari', $menit, 0)
        ON DUPLICATE KEY UPDATE durasi_menit = durasi_menit + $menit
    ");

    mysqli_query($koneksi, "
        UPDATE tb_target_harian
        SET status_tercapai = IF(durasi_menit >= $target, 1, 0)
        WHERE user_id = $user_id AND tanggal = '$hari'
    ");

    $sesudah = 0;
    $q2 = mysqli_query($koneksi, "SELECT status_tercapai FROM tb_target_harian WHERE user_id = $user_id AND tanggal = '$hari'");
    if ($q2) {
        $d2 = mysqli_fetch_assoc($q2);
        if ($d2) { $sesudah = (int)($d2['status_tercapai'] ?? 0); }
    }

    return $sesudah === 1 && $sebelum === 0;
}

/**
 * Catat detik belajar dari heartbeat browser lalu ubah ke menit penuh.
 * Sisa detik disimpan di session agar tidak hilang.
 *
 * @return array{menit:int,target:int,tercapai:bool,baru_tercapai:bool,detik_tersimpan:int}
 */
function tambah_detik_belajar(mysqli $koneksi, int $user_id, int $detik): array
{
    $hari = date('Y-m-d');
    if (($_SESSION['belajar_detik_tanggal'] ?? '') !== $hari) {
        $_SESSION['belajar_detik'] = 0;
        $_SESSION['belajar_detik_tanggal'] = $hari;
    }

    $detik = max(0, min(120, $detik)); // batasi agar tidak melonjak
    $_SESSION['belajar_detik'] = (int)($_SESSION['belajar_detik'] ?? 0) + $detik;

    $baru  = false;
    $menit = intdiv((int)$_SESSION['belajar_detik'], 60);
    if ($menit > 0) {
        $_SESSION['belajar_detik'] = (int)$_SESSION['belajar_detik'] - ($menit * 60);
        $baru = tambah_durasi_harian($koneksi, $user_id, $menit);
    }

    $data = durasi_harian_user($koneksi, $user_id);
    $data['baru_tercapai']   = $baru;
    $data['detik_tersimpan'] = (int)($_SESSION['belajar_detik'] ?? 0);
    return $data;
}

/**
 * Hitung durasi sesi yang baru diselesaikan lalu tambahkan ke target harian.
 * Waktu nyata sudah dicatat lewat heartbeat, jadi di sini hanya sisa detik
 * yang belum menjadi menit penuh. Minimal 1 menit per latihan tetap dijaga
 * tanpa menghitung ganda bila heartbeat sudah mencatatnya.
 *
 * @return int Jumlah menit yang dicatat.
 */
function catat_durasi_belajar(mysqli $koneksi, int $user_id, int $id_pelajaran, string $mode): int
{
    if ($user_id <= 0 || $id_pelajaran <= 0 || $mode === '') {
        return 0;
    }

    unset($_SESSION['belajar_mulai'][$id_pelajaran][$mode]);

    $hari  = date('Y-m-d');
    $detik = (int)($_SESSION['belajar_detik'] ?? 0);
    $menit = intdiv($detik, 60);
    if ($menit > 0) {
        $_SESSION['belajar_detik'] = $detik - ($menit * 60);
        tambah_durasi_harian($koneksi, $user_id, $menit);
    }

    // Jamin minimal 1 menit untuk satu latihan yang selesai.
    $data = durasi_harian_user($koneksi, $user_id, $hari);
    if ($data['menit'] < 1) {
        tambah_durasi_harian($koneksi, $user_id, 1);
        $menit = 1;
    }

    return $menit;
}
