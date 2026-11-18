<?php
require_once __DIR__ . '/config/keamanan.php';
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/progres.php';
require_once __DIR__ . '/config/auth.php';

// Logout hanya lewat POST + token CSRF yang sah.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    header("Location: dashboard.php");
    exit();
}

// Siswa diminta konfirmasi bila keluar sebelum target belajar hari ini
// terpenuhi. Bila pengguna sudah memilih "Ya" (konfirmasi_keluar), logout
// tetap dilanjutkan. Admin tidak terkena aturan ini.
if (isset($_SESSION['user_id']) && !pengguna_admin($koneksi)) {
    $user_id = (int)$_SESSION['user_id'];
    $target  = durasi_harian_user($koneksi, $user_id);
    if (!$target['tercapai'] && empty($_POST['konfirmasi_keluar'])) {
        $_SESSION['error_target'] = 'Kamu belum memenuhi target belajar hari ini ('
            . (int)$target['menit'] . '/' . (int)$target['target']
            . ' menit). Apakah Anda yakin keluar tetapi Anda belum mencapai target?';
        header("Location: dashboard.php");
        exit();
    }
}

session_unset();
session_destroy();

header("Location: index.php");
exit();
