<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/bahasa.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

if (!csrf_valid()) {
    $_SESSION['error_register'] = t(
        'Sesi kamu sudah berakhir. Silakan muat ulang halaman lalu coba lagi.',
        'Your session has expired. Please reload the page and try again.'
    );
    header("Location: register.php");
    exit();
}

$nama      = trim($_POST['nama'] ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$password  = $_POST['password'] ?? '';
$konfirmasi = $_POST['konfirmasi_password'] ?? '';

/** Simpan pesan error lalu kembali ke form. */
function gagal_register(string $pesan): void
{
    $_SESSION['error_register'] = $pesan;
    header("Location: register.php");
    exit();
}

// --- Validasi isi form -----------------------------------------------------
// Rate limit: maksimal 10 pengiriman form pendaftaran per jam dari satu IP.
$kunci_throttle = 'register|' . throttle_ip();
$status_throttle = throttle_cek($kunci_throttle, 10, 3600);
if (!$status_throttle['boleh']) {
    gagal_register(throttle_pesan($status_throttle['tunggu']));
}
throttle_tambah($kunci_throttle, 3600);

if ($nama === '' || $email === '' || $password === '' || $konfirmasi === '') {
    gagal_register(t(
        'Pendaftaran gagal! Semua kolom wajib diisi.',
        'Registration failed! All fields are required.'
    ));
}

if (mb_strlen($nama) < 2 || mb_strlen($nama) > 100) {
    gagal_register(t(
        'Nama harus terdiri dari 2 sampai 100 karakter.',
        'Your name must be between 2 and 100 characters.'
    ));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    gagal_register(t(
        'Format email tidak valid. Contoh: nama@email.com.',
        'Invalid email format. Example: name@email.com.'
    ));
}

if (strlen($password) < 6) {
    gagal_register(t(
        'Password minimal 6 karakter.',
        'Password must be at least 6 characters.'
    ));
}

if ($password !== $konfirmasi) {
    gagal_register(t(
        'Konfirmasi password tidak sama dengan password yang kamu isi.',
        'Password confirmation does not match the password you entered.'
    ));
}

// --- Cek email ganda -------------------------------------------------------
$email_aman = mysqli_real_escape_string($koneksi, $email);
$cek_email  = mysqli_query($koneksi, "SELECT id FROM tb_akun WHERE email = '$email_aman'");

if ($cek_email && mysqli_num_rows($cek_email) > 0) {
    gagal_register(t(
        'Email sudah terdaftar. Silakan gunakan email lain atau login.',
        'This email is already registered. Use another email or log in.'
    ));
}

// --- Simpan akun baru ------------------------------------------------------
$nama_aman     = mysqli_real_escape_string($koneksi, $nama);
$password_hash = mysqli_real_escape_string($koneksi, password_hash($password, PASSWORD_DEFAULT));

$query_insert = "INSERT INTO tb_akun (nama, email, password, role, tanggal_daftar)
                 VALUES ('$nama_aman', '$email_aman', '$password_hash', 'siswa', CURDATE())";

if (mysqli_query($koneksi, $query_insert)) {
    $_SESSION['success_register'] = t(
        'Pendaftaran berhasil! Akun kamu sudah dibuat, silakan login.',
        'Registration successful! Your account has been created. Please log in.'
    );
    header("Location: register.php");
    exit();
}

$_SESSION['error_register'] = t(
    'Terjadi kesalahan sistem saat menyimpan data.',
    'A system error occurred while saving your data.'
);
header("Location: register.php");
exit();
