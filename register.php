<?php
session_start();
require_once 'config/bahasa.php';

// Jika sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Daftar', 'Sign Up') ?> - RusVoca</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    
    <style>
        :root {
            --primary-blue: #0F387A;
            --secondary-gold: #E09B19;
            --bg-light: #F8FAFC;
            --card-white: #FFFFFF;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background-image: url('assets/img/background login.webp');
            background-position: center; background-repeat: no-repeat;
            background-size: 100% 100vh; background-attachment: fixed;
            display: flex; align-items: center; justify-content: center; min-height: 100vh;
        }

        .login-wrapper {
            background: var(--card-white); width: 800px; max-width: 95%;
            border-radius: 20px; box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
            display: flex; overflow: hidden;
        }

        /* Kolom Kiri */
        .login-left {
            flex: 1; background: var(--primary-blue); padding: 40px 30px; 
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; color: #fff;
        }

        .login-left img { width: 100%; max-width: 280px; height: auto; margin-bottom: 25px; }
        .login-left h3 { font-size: 20px; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 10px; }
        .login-left p { font-size: 12px; font-weight: 500; color: rgba(255, 255, 255, 0.85); line-height: 1.5; padding: 0 10px; }

        /* Kolom Kanan */
        .login-right {
            flex: 1; padding: 40px 50px; /* Padding atas bawah sedikit dikurangi agar form muat */
            display: flex; flex-direction: column; justify-content: center;
        }

        .login-header { text-align: center; margin-bottom: 20px; }
        .login-header img { width: 65px; height: 65px; object-fit: contain; margin-bottom: 4px; }
        .login-header h2 { font-size: 28px; font-weight: 800; color: var(--primary-blue); margin-top: 0; }

        /* Style untuk Kotak Alert/Error (Jika ada validasi gagal) */
        .alert-error {
            background-color: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px;
            font-size: 13px; margin-bottom: 15px; font-weight: 600; text-align: center; border: 1px solid #fecaca;
        }
        .alert-success {
            background-color: #dcfce7; color: #16a34a; padding: 12px; border-radius: 8px;
            font-size: 13px; margin-bottom: 15px; font-weight: 600; text-align: center; border: 1px solid #bbf7d0;
        }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; }

        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 15px; width: 20px; height: 20px; color: #94a3b8; pointer-events: none; transition: 0.3s; }

        .form-control {
            width: 100%; padding: 12px 15px 12px 45px; border: 1.5px solid var(--border-color);
            border-radius: 10px; font-size: 14px; color: var(--text-dark); background: #F8FAFC; transition: 0.3s;
        }
        
        .password-wrapper .form-control { padding-right: 45px; }

        .form-control:focus { outline: none; border-color: var(--primary-blue); background: #fff; box-shadow: 0 0 0 4px rgba(15, 56, 122, 0.1); }
        .form-control:focus + .input-icon, .form-control:focus ~ .input-icon { color: var(--primary-blue); }

        .toggle-password {
            position: absolute; right: 15px; width: 20px; height: 20px; color: #94a3b8; cursor: pointer; transition: 0.2s; user-select: none;
        }
        .toggle-password:hover { color: var(--primary-blue); }

        .btn-login {
            width: 100%; padding: 14px; background: var(--primary-blue); color: #fff; margin-top: 10px;
            font-size: 15px; font-weight: 700; border: none; border-radius: 10px; cursor: pointer; transition: 0.3s;
        }
        .btn-login:hover { background: #0b2a5c; }
        .btn-login:active { transform: scale(0.98); }

        .register-link { text-align: center; margin-top: 20px; font-size: 13px; font-weight: 600; color: var(--text-muted); }
        .register-link a { color: var(--secondary-gold); text-decoration: none; font-weight: 800; }
        .register-link a:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .login-wrapper { flex-direction: column; margin: 20px; }
            .login-left, .login-right { padding: 30px; }
        }
    </style>
</head>
<body>

    <?= tombol_bahasa('floating') ?>

    <div class="login-wrapper">
        <!-- Bagian Kiri: Ilustrasi -->
        <div class="login-left">
            <img src="assets/img/tamnel.png" alt="<?= t('Ilustrasi Belajar Rusia', 'Russian learning illustration') ?>">
            <h3><?= t('BERGABUNG BERSAMA KAMI', 'JOIN US') ?></h3>
            <p><?= t('MULAI PERJALANAN BELAJAR BAHASA RUSIA ANDA HARI INI SECARA GRATIS', 'START YOUR RUSSIAN LEARNING JOURNEY TODAY FOR FREE') ?></p>
        </div>

        <!-- Bagian Kanan: Form Register -->
        <div class="login-right">
            <div class="login-header">
                <img src="assets/img/logo.png" alt="Logo RusVoca">
                <h2><?= t('Daftar Akun', 'Create Account') ?></h2>
            </div>
            
            <!-- Tempat Notifikasi Error / Sukses -->
            <?php 
            if (isset($_SESSION['error_register'])) {
                echo "<div class='alert-error'>" . $_SESSION['error_register'] . "</div>";
                unset($_SESSION['error_register']);
            }
            if (isset($_SESSION['success_register'])) {
                echo "<div class='alert-success'>" . $_SESSION['success_register'] . "</div>";
                unset($_SESSION['success_register']);
            }
            ?>
            
            <form action="proses_register.php" method="POST">
                <?= csrf_field() ?>
                
                <!-- Input Nama Lengkap -->
                <div class="form-group">
                    <label for="nama"><?= t('Nama Lengkap', 'Full Name') ?></label>
                    <div class="input-wrapper">
                        <!-- Icon User SVG -->
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <input type="text" id="nama" name="nama" class="form-control" placeholder="<?= t('Masukkan nama kamu', 'Enter your name') ?>" required>
                    </div>
                </div>

                <!-- Input Email -->
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <!-- Icon Envelope SVG -->
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <input type="email" id="email" name="email" class="form-control" placeholder="<?= t('Contoh@gmail.com', 'name@example.com') ?>" required>
                    </div>
                </div>
                
                <!-- Input Password -->
                <div class="form-group">
                    <label for="password"><?= t('Password', 'Password') ?></label>
                    <div class="input-wrapper password-wrapper">
                        <!-- Icon Lock SVG -->
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        
                        <input type="password" id="password" name="password" class="form-control" placeholder="<?= t('Masukkan password (Min. 6 Karakter)', 'Enter a password (min. 6 characters)') ?>" required minlength="6">
                        
                        <!-- Icon Toggle Password SVG -->
                        <svg id="togglePassword" class="toggle-password" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path id="eyeIcon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22" />
                        </svg>
                    </div>
                </div>

                <!-- Konfirmasi Password -->
                <div class="form-group">
                    <label for="konfirmasi_password"><?= t('Ulangi Password', 'Confirm Password') ?></label>
                    <div class="input-wrapper password-wrapper">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control" placeholder="<?= t('Ketik ulang password yang sama', 'Re-enter the same password') ?>" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" class="btn-login"><?= t('Daftar Sekarang', 'Sign Up Now') ?></button>
                
                <div class="register-link">
                    <?= t('Sudah punya akun?', 'Already have an account?') ?> <a href="login.php"><?= t('Login di sini', 'Log in here') ?></a>
                </div>
                <div class="register-link" style="margin-top: 12px;">
                    <a href="index.php" style="color: var(--text-muted); font-weight: 700; text-decoration: none;">← <?= t('Kembali ke Beranda', 'Back to Home') ?></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Script Logika Buka/Tutup Password SVG -->
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        const eyeOpenPath = "M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z";
        const eyeClosedPath = "M13.875 18.825A10.05 10.05 0 0112 19c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22";

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            if (type === 'password') { eyeIcon.setAttribute('d', eyeClosedPath); } else { eyeIcon.setAttribute('d', eyeOpenPath); }
        });
    </script>

</body>
</html>