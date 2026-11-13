<?php
/**
 * config/auth.php
 * ---------------------------------------------------------------------------
 * Helper hak akses (role) untuk RusVoca.
 * Role disimpan di tb_akun.role : 'siswa' | 'admin'.
 * ---------------------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ambil role user yang sedang login.
 * Jika session belum punya role (mis. login sebelum fitur role ada),
 * otomatis diambil dari database lalu disimpan ke session.
 *
 * @param mysqli $koneksi Koneksi database aktif.
 * @return string 'admin' atau 'siswa'.
 */
function role_aktif(mysqli $koneksi): string
{
    if (isset($_SESSION['role']) && $_SESSION['role'] !== '') {
        return (string)$_SESSION['role'];
    }

    $role = 'siswa';
    $uid  = (int)($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        $q = mysqli_query($koneksi, "SELECT role FROM tb_akun WHERE id = $uid");
        if ($q) {
            $d = mysqli_fetch_assoc($q);
            if ($d && !empty($d['role'])) {
                $role = (string)$d['role'];
            }
        }
    }
    $_SESSION['role'] = $role;
    return $role;
}

/** Apakah user yang login berperan sebagai admin? */
function pengguna_admin(mysqli $koneksi): bool
{
    return role_aktif($koneksi) === 'admin';
}

/**
 * Wajib berperan admin. Siswa yang mencoba membuka halaman admin
 * akan dialihkan ke dashboard disertai pesan.
 */
function wajib_admin(mysqli $koneksi, string $login_path = 'login.php', string $dash_path = 'dashboard.php'): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: $login_path");
        exit();
    }
    if (role_aktif($koneksi) !== 'admin') {
        $_SESSION['error_akses'] = "Halaman tersebut khusus untuk admin. Kamu dialihkan ke dashboard.";
        header("Location: $dash_path");
        exit();
    }
}
