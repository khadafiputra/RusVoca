<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/bahasa.php';

// Pengguna yang sudah login tidak perlu halaman ini.
if (isset($_SESSION['user_id'])) {
    header("Location: " . (($_SESSION['role'] ?? 'siswa') === 'admin' ? 'admin/index.php' : 'dashboard.php'));
    exit();
}

$pesan_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $pesan_error = t(
            'Sesi kamu sudah berakhir. Muat ulang halaman lalu coba lagi.',
            'Your session has expired. Reload the page and try again.'
        );
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));

        // Rate limit: maksimal 5 permintaan kode per jam untuk IP + email yang sama.
        $kunci_otp  = 'otp|' . throttle_ip() . '|' . $email;
        $status_otp = throttle_cek($kunci_otp, 5, 3600);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pesan_error = t(
                'Masukkan alamat email yang valid.',
                'Please enter a valid email address.'
            );
        } elseif (!$status_otp['boleh']) {
            $pesan_error = throttle_pesan($status_otp['tunggu']);
        } elseif (
            isset($_SESSION['reset_request_at'])
            && (time() - (int)$_SESSION['reset_request_at']) < 30
        ) {
            $pesan_error = t(
                'Terlalu cepat. Tunggu sekitar 30 detik sebelum meminta kode lagi.',
                'Too soon. Wait about 30 seconds before requesting another code.'
            );
        } else {
            throttle_tambah($kunci_otp, 3600);
            $_SESSION['reset_request_at'] = time();
            $_SESSION['reset_attempts']   = 0;
            unset($_SESSION['otp_lokal']);
            $_SESSION['reset_email'] = $email;

            $email_aman = mysqli_real_escape_string($koneksi, $email);
            $q = mysqli_query($koneksi, "SELECT id FROM tb_akun WHERE email = '$email_aman' LIMIT 1");
            $akun = ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;

            if ($akun) {
                $otp     = buat_otp();
                $expired = date('Y-m-d H:i:s', time() + 15 * 60);
                $otp_aman = mysqli_real_escape_string($koneksi, $otp);

                mysqli_query(
                    $koneksi,
                    "UPDATE tb_akun
                     SET reset_otp = '$otp_aman', reset_otp_expired = '$expired'
                     WHERE id = " . (int)$akun['id']
                );

                // Belum ada pengiriman email, jadi kode ditampilkan di layar
                // hanya ketika aplikasi berjalan di komputer lokal.
                if (mode_pengembangan()) {
                    $_SESSION['otp_lokal'] = $otp;
                }
            }

            // Pesan tetap umum agar tidak membocorkan email mana yang terdaftar.
            $_SESSION['reset_info'] = t(
                'Jika email tersebut terdaftar, kode reset 6 digit sudah dibuat dan berlaku selama 15 menit.',
                'If that email is registered, a 6-digit reset code has been created and is valid for 15 minutes.'
            );

            header("Location: reset_password.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Lupa Password', 'Forgot Password') ?> - RusVoca</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    <link rel="stylesheet" href="assets/css/auth.css?v=20261112b">
</head>
<body>

    <?= tombol_bahasa('floating') ?>

    <div class="auth-wrapper">
        <div class="auth-left">
            <img src="assets/img/tamnel.png" alt="<?= t('Ilustrasi Belajar Rusia', 'Russian learning illustration') ?>">
            <h3><?= t('LUPA PASSWORD?', 'FORGOT YOUR PASSWORD?') ?></h3>
            <p><?= t('MASUKKAN EMAIL AKUNMU, LALU BUAT PASSWORD BARU DENGAN KODE 6 DIGIT.', 'ENTER YOUR ACCOUNT EMAIL, THEN SET A NEW PASSWORD WITH A 6-DIGIT CODE.') ?></p>
        </div>

        <div class="auth-right">
            <div class="auth-header">
                <img src="assets/img/logo.png" alt="Logo RusVoca">
                <h1><?= t('Lupa Password', 'Forgot Password') ?></h1>
                <p><?= t('Masukkan email yang kamu pakai saat mendaftar.', 'Enter the email you used when signing up.') ?></p>
            </div>

            <?php if ($pesan_error !== ''): ?>
                <div class="alert alert-error"><?= htmlspecialchars($pesan_error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="lupa_password.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="<?= t('Contoh@gmail.com', 'name@example.com') ?>"
                           value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           required autofocus>
                </div>
                <button type="submit" class="btn-auth"><?= t('Buat Kode Reset', 'Create Reset Code') ?></button>
            </form>

            <div class="auth-links">
                <a href="login.php">← <?= t('Kembali ke Login', 'Back to Log In') ?></a>
            </div>
        </div>
    </div>

</body>
</html>
