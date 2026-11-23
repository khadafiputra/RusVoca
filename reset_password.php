<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/bahasa.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . (($_SESSION['role'] ?? 'siswa') === 'admin' ? 'admin/index.php' : 'dashboard.php'));
    exit();
}

$pesan_error = '';
$info        = (string)($_SESSION['reset_info'] ?? '');
unset($_SESSION['reset_info']);

$otp_lokal = (string)($_SESSION['otp_lokal'] ?? '');
$email     = (string)($_SESSION['reset_email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $otp        = trim($_POST['otp'] ?? '');
    $password   = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';
    $_SESSION['reset_email'] = $email;

    if (!csrf_valid()) {
        $pesan_error = t(
            'Sesi kamu sudah berakhir. Muat ulang halaman lalu coba lagi.',
            'Your session has expired. Reload the page and try again.'
        );
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan_error = t('Alamat email tidak valid.', 'Invalid email address.');
    } elseif (!preg_match('/^[0-9]{6}$/', $otp)) {
        $pesan_error = t('Kode reset harus terdiri dari 6 angka.', 'The reset code must be 6 digits.');
    } elseif (strlen($password) < 6) {
        $pesan_error = t('Password baru minimal 6 karakter.', 'The new password must be at least 6 characters.');
    } elseif ($password !== $konfirmasi) {
        $pesan_error = t('Konfirmasi password tidak sama.', 'Password confirmation does not match.');
    } else {
        // Rate limit: maksimal 10 tebakan kode per 15 menit untuk IP + email.
        $kunci_reset  = 'reset|' . throttle_ip() . '|' . $email;
        $status_reset = throttle_cek($kunci_reset, 10, 900);

        $email_aman = mysqli_real_escape_string($koneksi, $email);
        $q = mysqli_query(
            $koneksi,
            "SELECT id, reset_otp, reset_otp_expired
             FROM tb_akun WHERE email = '$email_aman' LIMIT 1"
        );
        $akun = ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;

        if (!$status_reset['boleh']) {
            $pesan_error = throttle_pesan($status_reset['tunggu']);
        } elseif (!$akun || (string)($akun['reset_otp'] ?? '') === '') {
            // Pesan umum: jangan beri tahu apakah email terdaftar atau tidak.
            $pesan_error = t(
                'Kode reset tidak valid atau sudah kedaluwarsa. Minta kode baru.',
                'The reset code is invalid or has expired. Request a new code.'
            );
        } elseif ((int)($_SESSION['reset_attempts'] ?? 0) >= 5) {
            mysqli_query($koneksi, "UPDATE tb_akun SET reset_otp = NULL, reset_otp_expired = NULL WHERE id = " . (int)$akun['id']);
            unset($_SESSION['otp_lokal']);
            $pesan_error = t(
                'Terlalu banyak percobaan. Untuk keamanan, minta kode reset baru.',
                'Too many attempts. For security, request a new reset code.'
            );
        } elseif (!hash_equals((string)$akun['reset_otp'], $otp)) {
            throttle_tambah($kunci_reset, 900);
            $_SESSION['reset_attempts'] = (int)($_SESSION['reset_attempts'] ?? 0) + 1;
            $sisa = 5 - (int)$_SESSION['reset_attempts'];
            $pesan_error = t(
                'Kode reset salah. Sisa percobaan: ' . max(0, $sisa) . '.',
                'Incorrect reset code. Attempts left: ' . max(0, $sisa) . '.'
            );
        } elseif (empty($akun['reset_otp_expired']) || strtotime((string)$akun['reset_otp_expired']) < time()) {
            mysqli_query($koneksi, "UPDATE tb_akun SET reset_otp = NULL, reset_otp_expired = NULL WHERE id = " . (int)$akun['id']);
            unset($_SESSION['otp_lokal']);
            $pesan_error = t(
                'Kode reset sudah kedaluwarsa. Minta kode baru.',
                'The reset code has expired. Request a new code.'
            );
        } else {
            $hash = mysqli_real_escape_string($koneksi, password_hash($password, PASSWORD_DEFAULT));
            $ok = mysqli_query(
                $koneksi,
                "UPDATE tb_akun
                 SET password = '$hash', reset_otp = NULL, reset_otp_expired = NULL
                 WHERE id = " . (int)$akun['id']
            );

            if ($ok) {
                throttle_hapus($kunci_reset);
                unset($_SESSION['otp_lokal'], $_SESSION['reset_email'], $_SESSION['reset_attempts'], $_SESSION['reset_request_at'], $_SESSION['reset_info']);
                $_SESSION['success_login'] = t(
                    'Password berhasil diganti. Silakan login dengan password baru.',
                    'Password changed successfully. Please log in with your new password.'
                );
                header("Location: login.php");
                exit();
            }

            $pesan_error = t(
                'Gagal menyimpan password baru. Coba lagi.',
                'Failed to save the new password. Please try again.'
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Reset Password', 'Reset Password') ?> - RusVoca</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    <link rel="stylesheet" href="assets/css/auth.css?v=20261112b">
    <link rel="stylesheet" href="assets/css/mobile.css?v=20261123">
</head>
<body>

    <?= tombol_bahasa('floating') ?>

    <div class="auth-wrapper">
        <div class="auth-left">
            <img src="assets/img/tamnel.png" alt="<?= t('Ilustrasi Belajar Rusia', 'Russian learning illustration') ?>">
            <h3><?= t('BUAT PASSWORD BARU', 'SET A NEW PASSWORD') ?></h3>
            <p><?= t('KODE RESET BERLAKU 15 MENIT DAN HANYA BISA DIPAKAI SEKALI.', 'THE RESET CODE IS VALID FOR 15 MINUTES AND CAN ONLY BE USED ONCE.') ?></p>
        </div>

        <div class="auth-right">
            <div class="auth-header">
                <img src="assets/img/logo.png" alt="Logo RusVoca">
                <h1><?= t('Reset Password', 'Reset Password') ?></h1>
                <p><?= t('Masukkan kode 6 digit lalu tentukan password baru.', 'Enter the 6-digit code, then choose a new password.') ?></p>
            </div>

            <?php if ($info !== ''): ?>
                <div class="alert alert-info"><?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($otp_lokal !== ''): ?>
                <div class="otp-box">
                    <?= t('Mode pengembangan lokal: kode reset kamu adalah', 'Local development mode: your reset code is') ?>
                    <strong><?= htmlspecialchars($otp_lokal, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($pesan_error !== ''): ?>
                <div class="alert alert-error"><?= htmlspecialchars($pesan_error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="reset_password.php">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="<?= t('Contoh@gmail.com', 'name@example.com') ?>"
                           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="otp"><?= t('Kode Reset', 'Reset Code') ?></label>
                    <input type="text" id="otp" name="otp" class="form-control otp-input"
                           placeholder="000000" inputmode="numeric" pattern="[0-9]{6}"
                           maxlength="6" autocomplete="one-time-code" required>
                </div>

                <div class="form-group">
                    <label for="password"><?= t('Password Baru', 'New Password') ?></label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="<?= t('Minimal 6 karakter', 'At least 6 characters') ?>"
                           minlength="6" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label for="konfirmasi_password"><?= t('Ulangi Password Baru', 'Confirm New Password') ?></label>
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control"
                           placeholder="<?= t('Ketik ulang password baru', 'Re-enter the new password') ?>"
                           minlength="6" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn-auth"><?= t('Simpan Password Baru', 'Save New Password') ?></button>
            </form>

            <div class="auth-links">
                <a href="lupa_password.php"><?= t('Kirim ulang kode', 'Resend code') ?></a>
                <span class="sep">•</span>
                <a href="login.php" class="muted"><?= t('Kembali ke Login', 'Back to Log In') ?></a>
            </div>
        </div>
    </div>

</body>
</html>
