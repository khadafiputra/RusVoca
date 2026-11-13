<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/bahasa.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// Tolak permintaan tanpa token CSRF yang sah.
if (!csrf_valid()) {
    $_SESSION['error_login'] = t(
        'Sesi kamu sudah berakhir. Silakan muat ulang halaman lalu coba lagi.',
        'Your session has expired. Please reload the page and try again.'
    );
    header("Location: login.php");
    exit();
}

$email    = mysqli_real_escape_string($koneksi, strtolower(trim($_POST['email'] ?? '')));
$password = $_POST['password'] ?? '';

// Rate limit: maksimal 5 percobaan gagal per 15 menit untuk IP + email yang sama.
$kunci_throttle = 'login|' . throttle_ip() . '|' . strtolower(trim($_POST['email'] ?? ''));
$status_throttle = throttle_cek($kunci_throttle, 5, 900);
if (!$status_throttle['boleh']) {
    $_SESSION['error_login'] = throttle_pesan($status_throttle['tunggu']);
    header("Location: login.php");
    exit();
}

// Lapis kedua: batasi total kegagalan dari satu IP agar tidak bisa "spray" banyak email.
$kunci_ip       = 'login-ip|' . throttle_ip();
$status_ip      = throttle_cek($kunci_ip, 30, 900);
if (!$status_ip['boleh']) {
    $_SESSION['error_login'] = throttle_pesan($status_ip['tunggu']);
    header("Location: login.php");
    exit();
}

$query = mysqli_query($koneksi, "SELECT * FROM tb_akun WHERE email = '$email'");

// Pesan sengaja dibuat umum agar tidak membocorkan email mana yang terdaftar.
$pesan_gagal = t('Email atau password salah.', 'Incorrect email or password.');

if ($query && mysqli_num_rows($query) > 0) {
    $data = mysqli_fetch_assoc($query);

    // Verifikasi password. Mendukung hash modern (password_hash) sekaligus
    // password lama yang (keliru) masih tersimpan sebagai teks biasa.
    $hash_tersimpan = (string)$data['password'];
    $cocok = password_verify($password, $hash_tersimpan);

    // Migrasi otomatis password lama (teks biasa) ke hash yang aman.
    if (!$cocok && strpos($hash_tersimpan, '$') !== 0 && hash_equals($hash_tersimpan, $password)) {
        $cocok = true;
        $hash_baru = password_hash($password, PASSWORD_DEFAULT);
        mysqli_query($koneksi, "UPDATE tb_akun SET password = '" . mysqli_real_escape_string($koneksi, $hash_baru) . "' WHERE id = " . (int)$data['id']);
    }

    if ($cocok) {
        session_regenerate_id(true);
        csrf_perbarui();
        throttle_hapus($kunci_throttle);

        $_SESSION['user_id'] = $data['id'];
        $_SESSION['nama']    = $data['nama'];
        $_SESSION['role']    = $data['role'] ?? 'siswa';

        if (($_SESSION['role'] ?? 'siswa') === 'admin') {
            header("Location: admin/index.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    }
}

$_SESSION['error_login'] = $pesan_gagal;
throttle_tambah($kunci_throttle, 900);
throttle_tambah($kunci_ip, 900);
header("Location: login.php");
exit();
