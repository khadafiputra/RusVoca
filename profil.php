<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/auth.php';
require_once 'config/bahasa.php';
require_once 'config/level.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = pengguna_admin($koneksi);

// =====================================================================================
// [SISTEM OTOMATIS] MENAMBAHKAN KOLOM DATABASE JIKA BELUM ADA (AGAR TIDAK ERROR)
// Tabel akun yang dipakai aplikasi adalah tb_akun (sama seperti login/register).
// =====================================================================================
$cek_kolom_target = mysqli_query($koneksi, "SHOW COLUMNS FROM tb_akun LIKE 'target_waktu'");
if ($cek_kolom_target && mysqli_num_rows($cek_kolom_target) == 0) {
    mysqli_query($koneksi, "ALTER TABLE tb_akun ADD target_waktu INT DEFAULT 15");
}

$cek_kolom_tgl = mysqli_query($koneksi, "SHOW COLUMNS FROM tb_akun LIKE 'tanggal_daftar'");
if ($cek_kolom_tgl && mysqli_num_rows($cek_kolom_tgl) == 0) {
    mysqli_query($koneksi, "ALTER TABLE tb_akun ADD tanggal_daftar DATE NULL");
}
// =====================================================================================

// =====================================================================================
// PROSES PENGUBAHAN DATA (FORM SUBMIT)
// =====================================================================================
$pesan_sukses = "";
$pesan_error = "";

// Semua aksi POST wajib membawa token CSRF yang sah.
$csrf_ok = ($_SERVER['REQUEST_METHOD'] !== 'POST') || csrf_valid();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_ok) {
    $pesan_error = t(
        'Sesi keamanan sudah berakhir. Muat ulang halaman lalu coba lagi.',
        'Your security session has expired. Reload the page and try again.'
    );
}

// 1. Proses Ubah Target Belajar
if ($csrf_ok && isset($_POST['simpan_target'])) {
    $target_baru = (int)$_POST['target_waktu'];
    if (!in_array($target_baru, [10, 15, 30], true)) { $target_baru = 15; }
    $update = mysqli_query($koneksi, "UPDATE tb_akun SET target_waktu = '$target_baru' WHERE id = '$user_id'");
    if ($update) $pesan_sukses = t('Target waktu belajar berhasil diperbarui!', 'Daily study goal updated successfully!');
    else $pesan_error = t('Gagal memperbarui target.', 'Failed to update the goal.');
}

// 2. Proses Ubah Nama Profil
if ($csrf_ok && isset($_POST['simpan_profil'])) {
    $nama_baru = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap'] ?? ''));
    if ($nama_baru === '') {
        $pesan_error = t('Nama tidak boleh kosong.', 'Name cannot be empty.');
    } else {
        $update = mysqli_query($koneksi, "UPDATE tb_akun SET nama = '$nama_baru' WHERE id = '$user_id'");
        if ($update) {
            $_SESSION['nama'] = $nama_baru; // Update session
            $pesan_sukses = t('Nama profil berhasil diubah!', 'Profile name updated successfully!');
        } else {
            $pesan_error = t('Gagal mengubah nama profil.', 'Failed to update the profile name.');
        }
    }
}

// 3. Proses Ubah Password (akun memakai password_hash())
if ($csrf_ok && isset($_POST['simpan_password'])) {
    $pass_lama = $_POST['pass_lama'] ?? '';
    $pass_baru = $_POST['pass_baru'] ?? '';

    if (strlen($pass_baru) < 6) {
        $pesan_error = t('Password baru minimal 6 karakter!', 'The new password must be at least 6 characters!');
    } else {
        $cek_pass = mysqli_query($koneksi, "SELECT password FROM tb_akun WHERE id = '$user_id'");
        $data_pass = $cek_pass ? mysqli_fetch_assoc($cek_pass) : null;
        $hash_lama = (string)($data_pass['password'] ?? '');
        $lama_benar = ($data_pass !== null) && (
            password_verify($pass_lama, $hash_lama)
            || (strpos($hash_lama, '$') !== 0 && $hash_lama !== '' && hash_equals($hash_lama, $pass_lama))
        );
        if ($lama_benar) {
            $hash_baru = password_hash($pass_baru, PASSWORD_DEFAULT);
            $update = mysqli_query($koneksi, "UPDATE tb_akun SET password = '$hash_baru' WHERE id = '$user_id'");
            if ($update) $pesan_sukses = t('Password berhasil diperbarui! Ingat baik-baik password barumu.', 'Password updated successfully! Remember your new password.');
            else $pesan_error = t('Gagal memperbarui password.', 'Failed to update the password.');
        } else {
            $pesan_error = t('Password lama yang kamu masukkan salah!', 'The current password you entered is incorrect!');
        }
    }
}
// =====================================================================================


// =====================================================================================
// AMBIL DATA USER & STATISTIK
// =====================================================================================
$nama_user = $_SESSION['nama'] ?? 'Siswa';
$query_user = mysqli_query($koneksi, "SELECT * FROM tb_akun WHERE id = '$user_id'");
$data_user = ($query_user) ? mysqli_fetch_assoc($query_user) : null;
if (!$data_user) { $data_user = ['nama' => $nama_user, 'email' => '', 'target_waktu' => 15, 'tanggal_daftar' => null]; }

// Menghitung Total XP & tingkat keahlian (helper bersama config/level.php)
$level       = hitung_level($koneksi, $user_id, $is_admin);
$total_xp    = $level['xp'];
$grade_name  = $level['nama'];
$grade_color = $level['warna'];
$level_kelas = $level['kelas'];
$grade_icon  = '<svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="' . $level['ikon'] . '"/></svg>';

// Menghitung Jumlah Bab Selesai (Tuntas 4 Latihan)
$bab_tuntas = 0;
$q_tuntas = mysqli_query($koneksi, "SELECT id_pelajaran FROM tb_progres_belajar WHERE user_id = '$user_id' AND skor >= 60 AND mode_belajar IN ('kuis_kosakata', 'kuis_mendengarkan', 'mode_speaking', 'mode_writing') GROUP BY id_pelajaran HAVING COUNT(DISTINCT mode_belajar) = 4");
if ($q_tuntas) { $bab_tuntas = mysqli_num_rows($q_tuntas); }

// Admin memiliki akses penuh: anggap semua bab sudah tuntas.
if ($is_admin) {
    $q_total_bab = mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM tb_pelajaran");
    if ($q_total_bab) {
        $d_total_bab = mysqli_fetch_assoc($q_total_bab);
        $bab_tuntas = (int)($d_total_bab['jml'] ?? 0);
    }
}

$tanggal_daftar = !empty($data_user['tanggal_daftar']) ? format_tanggal($data_user['tanggal_daftar']) : "-";
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Profil Saya', 'My Profile') ?> - RusVoca</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    <link rel="stylesheet" href="assets/css/level-theme.css?v=20261113">
    
    <style>
        :root { --primary-blue: #0F3F7A; --primary-hover: #0a2d59; --bg-light: #F4F7FA; --border-color: #E2E8F0; --text-dark: #0F172A; --text-muted: #64748B; --card-bg: #FFFFFF; --accent-blue: #2563EB; --accent-green: #10B981; --accent-red: #EF4444; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .app-layout { display: flex; min-height: 100vh; }

        /* SIDEBAR SINKRON */
        .sidebar { width: 260px; background-color: #FFFFFF; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between; padding: 24px 16px; position: fixed; height: 100vh; left: 0; top: 0; z-index: 50; }
        .brand-header { display: flex; align-items: center; gap: 12px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; padding-left: 10px; }
        .brand-logo-img { width: 42px; height: 42px; object-fit: contain; }
        .brand-text { display: flex; flex-direction: column; }
        .brand-name { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; color: var(--primary-blue); }
        .brand-name span { color: #E09B19; }
        .brand-sub { font-size: 9px; font-weight: 700; letter-spacing: 1px; color: var(--text-muted); margin-top: 2px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 600; border-radius: 10px; transition: all 0.2s ease; }
        .nav-item a:hover { background-color: var(--bg-light); color: var(--primary-blue); }
        .nav-item.active a { background-color: var(--primary-blue); color: #FFFFFF; box-shadow: 0 4px 12px rgba(15, 63, 122, 0.15); }
        .nav-icon { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .nav-icon.solid { fill: currentColor; stroke: none; }
        .user-profile-bottom { display: flex; align-items: center; gap: 12px; background-color: var(--bg-light); border: 1px solid var(--border-color); padding: 12px; border-radius: 12px; margin-top: auto; }
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-blue); display: flex; align-items: center; justify-content: center; color: #fff; }
        .user-info { flex: 1; overflow: hidden; }
        .user-info-name { font-size: 13px; font-weight: 700; color: var(--text-dark); white-space: nowrap; text-overflow: ellipsis; overflow: hidden; }
        .user-info-role { font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 4px; margin-top: 2px; }
        .logout-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 6px; border-radius: 6px; transition: all 0.2s; }
        .logout-btn:hover { color: #EF4444; background-color: rgba(239, 68, 68, 0.1); }

        /* KONTEN UTAMA PROFIL */
        .main-content { margin-left: 260px; flex: 1; padding: 40px; max-width: 1100px; }
        .page-title { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 24px; }
        
        /* ALERT NOTIFIKASI */
        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background-color: #ECFDF5; color: #047857; border: 1px solid #34D399; }
        .alert-error { background-color: #FEF2F2; color: #B91C1C; border: 1px solid #F87171; }

        /* GRID LAYOUT */
        .profile-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px; }

        /* KARTU KIRI (INFO & STATS) */
        .card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px; }
        
        .profile-header { display: flex; flex-direction: column; align-items: center; text-align: center; border-bottom: 1px solid var(--border-color); padding-bottom: 24px; margin-bottom: 24px; }
        .profile-avatar { width: 100px; height: 100px; background: var(--primary-blue); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 40px; font-weight: 800; margin-bottom: 16px; box-shadow: 0 8px 14px -4px rgba(15,63,122,0.25); }
        .profile-name { font-size: 22px; font-weight: 800; color: var(--text-dark); }
        .profile-date { font-size: 13px; color: var(--text-muted); font-weight: 500; margin-top: 4px; }
        .profile-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 800; margin-top: 12px; color: #fff; }

        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .stat-box { background: var(--bg-light); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color); text-align: center; }
        .stat-icon { font-size: 24px; margin-bottom: 8px; }
        .stat-value { font-size: 20px; font-weight: 800; color: var(--text-dark); }
        .stat-label { font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin-top: 4px; }

        /* KARTU KANAN (FORM PENGATURAN) */
        .settings-title { font-size: 18px; font-weight: 800; color: var(--text-dark); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .settings-title svg { width: 22px; height: 22px; color: var(--primary-blue); }
        .settings-desc { font-size: 12.5px; color: var(--text-muted); line-height: 1.65; margin: -10px 0 16px; }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
        .form-input { width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 10px; font-size: 14px; transition: 0.2s; }
        .form-input:focus { outline: none; border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .pass-wrapper { position: relative; }
        .pass-wrapper .form-input { padding-right: 46px; }
        .pass-toggle { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #94A3B8; padding: 6px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
        .pass-toggle:hover { color: var(--primary-blue); background: var(--bg-light); }
        .pass-toggle svg { width: 20px; height: 20px; }
        
        .radio-group { display: flex; gap: 12px; }
        .radio-label { flex: 1; border: 1px solid var(--border-color); padding: 12px; border-radius: 10px; text-align: center; cursor: pointer; transition: 0.2s; font-weight: 700; font-size: 13px; color: var(--text-muted); }
        .radio-input { display: none; }
        .radio-input:checked + .radio-label { background: var(--primary-blue); color: #fff; border-color: var(--primary-blue); }

        .btn-submit { background: var(--primary-blue); color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; transition: 0.2s; width: 100%; }
        .btn-submit:hover { background: var(--primary-hover); }
        
        hr { border: none; border-top: 1px solid var(--border-color); margin: 30px 0; }

        @media (max-width: 900px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 72px; padding: 16px 8px; }
            .brand-text, .user-info { display: none; }
            .brand-logo-img { width: 32px; height: 32px; }
            .main-content { margin-left: 72px; padding: 24px; }
        }
    </style>
</head>
<body class="<?= htmlspecialchars($level_kelas) ?>">

<div class="app-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div>
            <div class="brand-header"><img src="assets/img/logo.png" alt="Logo RusVoca" class="brand-logo-img"><div class="brand-text"><div class="brand-name">Rus<span>Voca</span></div><div class="brand-sub">RUSSIAN VOCABULARY APP</div></div></div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg><span><?= t('Dashboard', 'Dashboard') ?></span></a></li>
                <li class="nav-item"><a href="alfabet.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h9v2zm0-4H9V5h9v2z"/></svg><span><?= t('Belajar Alfabet', 'Learn the Alphabet') ?></span></a></li>
                <li class="nav-item"><a href="kosakata.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 4h2v5H9V4zm0 7h2v2H9v-2zm0 4h2v2H9v-2zm6 2h-3v-2h3v2zm0-4h-3v-2h3v2zm0-5h-3V4h3v5z"/></svg><span><?= t('Kosakata', 'Vocabulary') ?></span></a></li>
                <li class="nav-item"><a href="riwayat.php"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span><?= t('Riwayat', 'History') ?></span></a></li>
                <li class="nav-item active"><a href="profil.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= t('Profil Saya', 'My Profile') ?></span></a></li>
                <li class="nav-lang"><?= tombol_bahasa('sidebar') ?></li>
            </ul>
        </div>
        <!-- Profil Bawah -->
        <div class="user-profile-bottom">
            <div class="avatar-circle"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
            <div class="user-info">
                <div class="user-info-name"><?= htmlspecialchars($nama_user); ?></div>
                <div class="user-info-role" style="color: <?= $grade_color ?>;"><?= $grade_icon ?><span><?= htmlspecialchars(materi_t($grade_name)) ?></span></div>
            </div>
            <form action="logout.php" method="POST" style="display: flex;" onsubmit="return confirm('<?= t('Yakin ingin keluar?', 'Are you sure you want to log out?') ?>');"><?= csrf_field() ?><button type="submit" class="logout-btn"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></button></form>
        </div>
    </aside>

    <main class="main-content">
        <h2 class="page-title"><?= t('Profil & Pengaturan Akun', 'Profile & Account Settings') ?></h2>

        <?php if($pesan_sukses != ""): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                <?= $pesan_sukses ?>
            </div>
        <?php endif; ?>
        <?php if($pesan_error != ""): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                <?= $pesan_error ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            
            <!-- KOLOM KIRI (INFO STATISTIK) -->
            <div>
                <div class="card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <!-- Menampilkan inisial huruf pertama nama -->
                            <?= strtoupper(substr($nama_user, 0, 1)) ?>
                        </div>
                        <h3 class="profile-name"><?= htmlspecialchars($data_user['nama']) ?></h3>
                        <p class="profile-date"><?= t('Bergabung sejak', 'Joined since') ?> <?= $tanggal_daftar ?></p>
                        <div class="profile-badge" style="background-color: <?= $grade_color ?>;">
                            <?= $grade_icon ?> <?= htmlspecialchars(materi_t($grade_name)) ?>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#C88A18" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                            <div class="stat-value"><?= number_format($total_xp, 0, ',', '.') ?></div>
                            <div class="stat-label"><?= t('Total XP', 'Total XP') ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="#0F3F7A" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg></div>
                            <div class="stat-value"><?= $bab_tuntas ?></div>
                            <div class="stat-label"><?= t('Bab Diselesaikan', 'Chapters Completed') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KOLOM KANAN (FORM PENGATURAN) -->
            <div>
                <!-- FORM 0: BAHASA ANTARMUKA -->
                <div class="card">
                    <h3 class="settings-title">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 16M6 9a9 9 0 006 9m6-13l-4 10-4-10m6.5 10h1l2.5 5"/></svg>
                        <?= t('Bahasa Antarmuka', 'Interface Language') ?>
                    </h3>
                    <p class="settings-desc"><?= t('Pilih bahasa untuk menu, tombol, dan petunjuk aplikasi. Materi pelajaran tetap dalam Bahasa Indonesia.', 'Choose the language for menus, buttons, and app instructions. Lesson material stays in Indonesian.') ?></p>
                    <?= tombol_bahasa('sidebar') ?>
                </div>

                <!-- FORM 1: TARGET BELAJAR HARIAN -->
                <div class="card">
                    <h3 class="settings-title">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <?= t('Target Belajar Harian', 'Daily Study Goal') ?>
                    </h3>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label class="form-label"><?= t('Berapa menit target belajarmu setiap hari?', 'How many minutes do you want to study each day?') ?></label>
                            <div class="radio-group">
                                <input type="radio" name="target_waktu" id="t10" value="10" class="radio-input" <?= ($data_user['target_waktu'] == 10) ? 'checked' : '' ?>>
                                <label for="t10" class="radio-label"><?= t('Santai', 'Casual') ?> (10 <?= t('mnt', 'min') ?>)</label>

                                <input type="radio" name="target_waktu" id="t15" value="15" class="radio-input" <?= ($data_user['target_waktu'] == 15) ? 'checked' : '' ?>>
                                <label for="t15" class="radio-label"><?= t('Reguler', 'Regular') ?> (15 <?= t('mnt', 'min') ?>)</label>

                                <input type="radio" name="target_waktu" id="t30" value="30" class="radio-input" <?= ($data_user['target_waktu'] == 30) ? 'checked' : '' ?>>
                                <label for="t30" class="radio-label"><?= t('Serius', 'Serious') ?> (30 <?= t('mnt', 'min') ?>)</label>
                            </div>
                        </div>
                        <button type="submit" name="simpan_target" class="btn-submit"><?= t('Simpan Target', 'Save Goal') ?></button>
                    </form>
                </div>

                <!-- FORM 2: UBAH PROFIL -->
                <div class="card">
                    <h3 class="settings-title">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        <?= t('Data Pribadi', 'Personal Data') ?>
                    </h3>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label class="form-label"><?= t('Nama Lengkap', 'Full Name') ?></label>
                            <input type="text" name="nama_lengkap" class="form-input" value="<?= htmlspecialchars($data_user['nama']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= t('Email (tidak bisa diubah)', 'Email (cannot be changed)') ?></label>
                            <!-- Email tidak bisa diubah (readonly) -->
                            <input type="text" class="form-input" value="<?= htmlspecialchars($data_user['email'] ?? '') ?>" disabled style="background:#F1F5F9; cursor:not-allowed;">
                        </div>
                        <button type="submit" name="simpan_profil" class="btn-submit"><?= t('Simpan Perubahan Nama', 'Save Name Changes') ?></button>
                    </form>

                    <hr>

                    <!-- FORM 3: UBAH PASSWORD -->
                    <h3 class="settings-title">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        <?= t('Keamanan & Sandi', 'Security & Password') ?>
                    </h3>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label class="form-label"><?= t('Password Lama', 'Current Password') ?></label>
                            <div class="pass-wrapper">
                                <input type="password" name="pass_lama" id="pass_lama" class="form-input" placeholder="<?= t('Masukkan password saat ini', 'Enter your current password') ?>" required>
                                <button type="button" class="pass-toggle" onclick="togglePass('pass_lama', this)" title="<?= t('Tampilkan/sembunyikan password', 'Show/hide password') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22" /></svg></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= t('Password Baru', 'New Password') ?></label>
                            <div class="pass-wrapper">
                                <input type="password" name="pass_baru" id="pass_baru" class="form-input" placeholder="<?= t('Masukkan password baru', 'Enter a new password') ?>" required>
                                <button type="button" class="pass-toggle" onclick="togglePass('pass_baru', this)" title="<?= t('Tampilkan/sembunyikan password', 'Show/hide password') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22" /></svg></button>
                            </div>
                        </div>
                        <button type="submit" name="simpan_password" class="btn-submit" style="background:var(--accent-red);"><?= t('Ganti Password', 'Change Password') ?></button>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    var eyeClosed = "M13.875 18.825A10.05 10.05 0 0112 19c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22";
    var eyeOpen = "M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z";
    function togglePass(inputId, btn) {
        var input = document.getElementById(inputId);
        var path = btn.querySelector('path');
        if (input.type === 'password') {
            input.type = 'text';
            if (path) path.setAttribute('d', eyeOpen);
        } else {
            input.type = 'password';
            if (path) path.setAttribute('d', eyeClosed);
        }
    }
</script>

</body>
</html>