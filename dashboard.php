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

$user_id   = (int)($_SESSION['user_id'] ?? 0);
$nama_user = $_SESSION['nama'] ?? 'Siswa';
$nama_depan = trim(explode(' ', trim($nama_user))[0] ?? '');
if ($nama_depan === '') { $nama_depan = 'Siswa'; }
$is_admin  = pengguna_admin($koneksi);
$pesan_akses = $_SESSION['error_akses'] ?? '';
if ($pesan_akses === '') { $pesan_akses = $_SESSION['error_target'] ?? ''; }
unset($_SESSION['error_akses'], $_SESSION['error_target']);

// ===================================================================
// SISTEM GRADE / TINGKATAN KEAHLIAN (helper bersama config/level.php)
// ===================================================================
$level        = hitung_level($koneksi, $user_id, $is_admin);
$total_xp     = $level['xp'];
$grade_name   = $level['nama'];
$grade_color  = $level['warna'];
$next_xp      = $level['next_xp'];
$persen_grade = $level['persen'];
$level_kelas  = $level['kelas'];
$grade_icon   = '<svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="' . $level['ikon'] . '"/></svg>';

// ===================================================================
// FUNGSI PENGHITUNG WAKTU memakai helper bersama di config/bahasa.php
// ===================================================================

// Data Target Harian (target diambil dari pengaturan profil user)
$tanggal_hari_ini = date('Y-m-d');
$target_harian    = 15;
$q_target_user    = mysqli_query($koneksi, "SELECT target_waktu FROM tb_akun WHERE id = '$user_id'");
if ($q_target_user) {
    $d_target_user = mysqli_fetch_assoc($q_target_user);
    if ($d_target_user && isset($d_target_user['target_waktu']) && in_array((int)$d_target_user['target_waktu'], [10, 15, 30], true)) {
        $target_harian = (int)$d_target_user['target_waktu'];
    }
}
$durasi           = 0;
$query_target     = mysqli_query($koneksi, "SELECT * FROM tb_target_harian WHERE user_id = '$user_id' AND tanggal = '$tanggal_hari_ini'");
if ($query_target) {
    $data_target = mysqli_fetch_assoc($query_target);
    if ($data_target && isset($data_target['durasi_menit'])) {
        $durasi = (int)$data_target['durasi_menit'];
    }
}
$persen_progress  = min(100, round(($durasi / $target_harian) * 100));
$circumference    = 2 * pi() * 45; 
$stroke_offset    = $circumference - ($persen_progress / 100) * $circumference;

// Data 5 Riwayat Terakhir
$query_riwayat = mysqli_query($koneksi, "
    SELECT p.judul_pelajaran, pb.mode_belajar, pb.skor, pb.tanggal_akses 
    FROM tb_progres_belajar pb 
    LEFT JOIN tb_pelajaran p ON pb.id_pelajaran = p.id_pelajaran 
    WHERE pb.user_id = '$user_id' 
      AND pb.mode_belajar IN ('kuis_kosakata','kuis_mendengarkan','mode_speaking','mode_writing')
    ORDER BY pb.tanggal_akses DESC 
    LIMIT 5
");
$jumlah_riwayat = ($query_riwayat) ? mysqli_num_rows($query_riwayat) : 0;
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_admin ? t('Dashboard Admin', 'Admin Dashboard') : t('Dashboard Siswa', 'Student Dashboard') ?> - RusVoca</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    <link rel="stylesheet" href="assets/css/level-theme.css?v=20261113">
    
    <style>
        :root { --primary-blue: #0F3F7A; --primary-hover: #0a2d59; --bg-light: #F4F7FA; --border-color: #E2E8F0; --text-dark: #0F172A; --text-muted: #64748B; --card-bg: #FFFFFF; --accent-green: #10B981; --accent-blue: #2563EB; --accent-red: #EF4444; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .app-layout { display: flex; min-height: 100vh; }

        /* SIDEBAR */
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

        /* MAIN CONTENT */
        .main-content { margin-left: 260px; flex: 1; padding: 40px; max-width: 1200px; }
        .dashboard-title { font-size: 26px; font-weight: 800; color: var(--text-dark); margin-bottom: 24px; }
        .widgets-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 32px; }
        .card-ui { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; display: flex; flex-direction: column; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .card-title { font-size: 15px; font-weight: 700; color: var(--text-dark); margin-bottom: 16px; }
        
        .progress-circle-box { display: flex; flex-direction: column; align-items: center; gap: 16px; margin-top: auto; margin-bottom: auto; }
        .circular-progress-svg { transform: rotate(-90deg); }
        .progress-status { font-size: 13px; font-weight: 700; color: var(--primary-blue); text-align: center; }

        /* WIDGET GRADE KLIKABEL */
        .grade-card { cursor: pointer; transition: all 0.2s ease-in-out; position: relative; }
        .grade-card:hover { transform: translateY(-3px); border-color: var(--primary-blue); box-shadow: 0 10px 15px -3px rgba(15, 63, 122, 0.1); }
        .grade-content { display: flex; flex-direction: column; align-items: center; justify-content: center; margin: auto 0; text-align: center; gap: 8px; }
        .grade-icon-box { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 8px 16px rgba(0,0,0,0.1); margin-bottom: 8px; transition: 0.3s; }
        .grade-card:hover .grade-icon-box { transform: scale(1.05); }
        .grade-icon-box svg { width: 32px; height: 32px; }
        .grade-text { font-size: 20px; font-weight: 800; }
        .grade-xp { font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 12px; }
        .grade-bar-bg { width: 100%; background-color: var(--border-color); height: 8px; border-radius: 10px; overflow: hidden; margin-bottom: 8px; }
        .grade-bar-fill { height: 100%; transition: width 0.5s ease-in-out; }
        .grade-target { font-size: 11px; color: var(--text-muted); font-weight: 500; }

        /* WIDGET RIWAYAT */
        .timeline-list { display: flex; flex-direction: column; gap: 16px; }
        .timeline-item { display: flex; gap: 12px; align-items: flex-start; }
        .timeline-badge { display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0; margin-top: 2px; }
        .timeline-info h5 { font-size: 13px; font-weight: 700; color: var(--text-dark); line-height: 1.4; }
        .timeline-info p { font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 500;}

        /* MATERI POPULER */
        .section-header { font-size: 18px; font-weight: 800; color: var(--text-dark); margin-bottom: 16px; }
        .materi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; }
        .materi-card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; display: flex; gap: 20px; align-items: center; transition: all 0.2s ease-in-out; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .materi-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(15, 63, 122, 0.1); }
        .materi-banner { width: 80px; height: 80px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #FFFFFF; font-size: 20px; flex-shrink: 0; background-color: var(--primary-blue); }
        .materi-banner.yellow { background-color: #E09B19; }
        .materi-art { width: 74px; height: 74px; flex-shrink: 0; object-fit: contain; filter: drop-shadow(0 6px 10px rgba(15, 63, 122, .14)); }
        .materi-details { flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .materi-details h4 { font-size: 16px; font-weight: 800; color: var(--text-dark); }
        .materi-details p { font-size: 12px; color: var(--text-muted); line-height: 1.5; margin-bottom: 8px; }
        .learn-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; background-color: var(--primary-blue); color: #FFFFFF; text-decoration: none; font-size: 12px; font-weight: 700; border-radius: 8px; width: fit-content; transition: background-color 0.15s ease; border: none; }
        .learn-btn:hover { background-color: var(--primary-hover); }

        /* MODAL RANK (POPUP) */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-box { background: var(--card-bg); width: 90%; max-width: 450px; border-radius: 20px; padding: 32px; transform: translateY(20px); transition: transform 0.3s ease; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); position: relative; }
        .modal-overlay.show .modal-box { transform: translateY(0); }
        .modal-close { position: absolute; top: 20px; right: 20px; background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
        .modal-close:hover { background: var(--bg-light); color: var(--accent-red); }
        .modal-title { font-size: 20px; font-weight: 800; color: var(--text-dark); margin-bottom: 24px; text-align: center; }
        
        .rank-list { display: flex; flex-direction: column; gap: 12px; }
        .rank-item { display: flex; align-items: center; gap: 16px; padding: 16px; border-radius: 16px; border: 1px solid var(--border-color); background: var(--bg-light); transition: 0.2s; }
        .rank-item:hover { transform: translateX(5px); background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .rank-icon { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; }
        .rank-icon svg { width: 22px; height: 22px; }
        .rank-info { flex: 1; }
        .rank-info h4 { font-size: 15px; font-weight: 800; margin-bottom: 4px; }
        .rank-info p { font-size: 12px; color: var(--text-muted); font-weight: 600; }
        .rank-badge { font-size: 10px; font-weight: 800; padding: 4px 8px; border-radius: 6px; text-transform: uppercase; background: var(--primary-blue); color: #fff; }
        
        /* Rank Colors */
        .rank-item.bronze { border-color: rgba(180, 83, 9, 0.2); } .rank-item.bronze .rank-info h4 { color: #B45309; }
        .rank-item.silver { border-color: rgba(100, 116, 139, 0.2); } .rank-item.silver .rank-info h4 { color: #64748B; }
        .rank-item.gold { border-color: rgba(217, 119, 6, 0.2); } .rank-item.gold .rank-info h4 { color: #D97706; }
        .rank-item.diamond { border-color: rgba(37, 99, 235, 0.2); } .rank-item.diamond .rank-info h4 { color: #2563EB; }
        
        /* Highlight Current Rank */
        .rank-item.active-rank { border-width: 2px; background: #fff; box-shadow: 0 8px 16px rgba(0,0,0,0.08); transform: scale(1.02); }
        .rank-item.active-rank.bronze { border-color: #B45309; }
        .rank-item.active-rank.silver { border-color: #64748B; }
        .rank-item.active-rank.gold { border-color: #D97706; }
        .rank-item.active-rank.diamond { border-color: #2563EB; }

        @media (max-width: 768px) {
            .sidebar { width: 72px; padding: 16px 8px; }
            .brand-text, .user-info { display: none; }
            .brand-logo-img { width: 32px; height: 32px; }
            .main-content { margin-left: 72px; padding: 24px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/mobile.css?v=20261123">
</head>
<body class="<?= htmlspecialchars($level_kelas) ?>">

<div class="app-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div>
            <div class="brand-header">
                <img src="assets/img/logo.png" alt="Logo RusVoca" class="brand-logo-img">
                <div class="brand-text">
                    <div class="brand-name">Rus<span>Voca</span></div>
                    <div class="brand-sub">RUSSIAN VOCABULARY APP</div>
                </div>
            </div>

            <ul class="nav-menu">
                <li class="nav-item active"><a href="dashboard.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg><span><?= t('Dashboard', 'Dashboard') ?></span></a></li>
                <li class="nav-item"><a href="alfabet.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h9v2zm0-4H9V5h9v2z"/></svg><span><?= t('Belajar Alfabet', 'Learn the Alphabet') ?></span></a></li>
                <li class="nav-item"><a href="kosakata.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 4h2v5H9V4zm0 7h2v2H9v-2zm0 4h2v2H9v-2zm6 2h-3v-2h3v2zm0-4h-3v-2h3v2zm0-5h-3V4h3v5z"/></svg><span><?= t('Kosakata', 'Vocabulary') ?></span></a></li>
                <li class="nav-item"><a href="riwayat.php"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span><?= t('Riwayat', 'History') ?></span></a></li>
                
                <!-- LINK PROFIL SUDAH DIPERBAIKI DI SINI -->
                <li class="nav-item"><a href="profil.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= t('Profil Saya', 'My Profile') ?></span></a></li>
                <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>
                <li class="nav-lang"><?= tombol_bahasa('sidebar') ?></li>
            </ul>
        </div>

        <!-- Profil bawah dengan Grade SVG Baru -->
        <div class="user-profile-bottom">
            <div class="avatar-circle"><svg style="width: 20px; height: 20px; color: #fff;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
            <div class="user-info">
                <div class="user-info-name"><?= htmlspecialchars($nama_user); ?></div>
                <div class="user-info-role" style="color: <?= $level['warna'] ?>;">
                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="<?= $level['ikon'] ?>"/></svg>
                    <span><?= htmlspecialchars(materi_t($level['nama'])) ?></span>
                </div>
            </div>
            <form action="logout.php" method="POST" style="display: flex;" onsubmit="return confirm('<?= t('Apakah Anda yakin ingin keluar?', 'Are you sure you want to log out?') ?>');">
                <?= csrf_field() ?><button type="submit" class="logout-btn" title="Keluar"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <?php if ($pesan_akses !== ''): ?>
        <div style="background:#FEF3C7;border:1px solid #FBBF24;color:#92400E;padding:12px 16px;border-radius:10px;font-weight:600;font-size:14px;margin-bottom:18px;">
            <?= htmlspecialchars($pesan_akses) ?>
        </div>
        <?php endif; ?>
        <div class="rv-hero rv-rise">
            <div class="rv-hero-copy">
                <span class="rv-eyebrow"><span class="dot"></span> <?= $is_admin ? t('Dashboard admin', 'Admin dashboard') : t('Dashboard siswa', 'Student dashboard') ?></span>
                <h1>Привет, <?= htmlspecialchars($nama_depan) ?>! <span class="hl"><?= t('Siap belajar?', 'Ready to learn?') ?></span></h1>
                <p><?php if ($is_admin): ?><?= t('Mode admin aktif: semua bab dan latihan terbuka, dan tingkatan ditampilkan maksimal.', 'Admin mode active: all chapters and practices are unlocked, and your level is shown at maximum.') ?><?php else: ?><?= t('Lanjutkan perjalananmu menguasai bahasa Rusia. Setiap latihan menambah XP dan membuka bab baru.', 'Continue your journey to master Russian. Every practice session earns XP and unlocks new chapters.') ?><?php endif; ?></p>
                <div class="rv-hero-actions">
                    <a href="kosakata.php" class="rv-btn rv-btn-gold"><?= t('Lanjutkan Belajar', 'Continue Learning') ?></a>
                    <a href="alfabet.php" class="rv-btn rv-btn-light"><?= t('Belajar Alfabet', 'Learn the Alphabet') ?></a>
                </div>
                <div class="rv-hero-stats">
                    <div class="rv-stat"><b><?= number_format($total_xp, 0, ',', '.') ?></b><span><?= t('Total XP', 'Total XP') ?></span></div>
                    <div class="rv-stat"><b><?= (int)$durasi; ?>/<?= (int)$target_harian; ?></b><span><?= t('Menit hari ini', 'Minutes today') ?></span></div>
                    <div class="rv-stat"><b><?= htmlspecialchars(materi_t($grade_name)) ?></b><span><?= t('Tingkatan', 'Level') ?></span></div>
                </div>
            </div>
            <div class="rv-hero-art">
                <img src="assets/img/hero-dashboard.svg" alt="Ilustrasi matryoshka dan arsitektur Rusia">
            </div>
        </div>

        <div class="widgets-grid">
            <!-- Widgets 1: Progres Belajar Harian -->
            <div class="card-ui">
                <div class="card-title"><?= t('Progres Belajar Harian', 'Daily Learning Progress') ?></div>
                <div class="progress-circle-box">
                    <svg class="circular-progress-svg" width="120" height="120" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#E2E8F0" stroke-width="8" />
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#0F3F7A" stroke-width="8" 
                                stroke-dasharray="<?= $circumference; ?>" 
                                stroke-dashoffset="<?= $stroke_offset; ?>" 
                                stroke-linecap="round" />
                        <text x="50" y="47" text-anchor="middle" font-size="16" font-weight="800" fill="#0F3F7A" transform="rotate(90 50 50)"><?= (int)$durasi; ?>/<?= (int)$target_harian; ?></text>
                        <text x="50" y="65" text-anchor="middle" font-size="9" font-weight="700" fill="#64748B" transform="rotate(90 50 50)"><?= t('Menit', 'Min') ?></text>
                    </svg>
                    <div class="progress-status"><?= t('Target', 'Goal') ?> <?= (int)$target_harian; ?> <?= t('menit', 'minutes') ?> • <?= t('Tetap semangat belajar!', 'Keep up the good work!') ?></div>
                </div>
            </div>

            <!-- Widgets 2: Tingkatan Keahlian (KLIKABEL) -->
            <div class="card-ui grade-card" onclick="openModal()">
                <div class="card-title" style="display: flex; justify-content: space-between; align-items:center;">
                    <?= t('Tingkat Keahlian', 'Skill Level') ?>
                    <span style="font-size:11px; color:var(--primary-blue); font-weight:700;"><?= t('Lihat Detail', 'View Details') ?> &rarr;</span>
                </div>
                <div class="grade-content">
                    <div class="grade-icon-box" style="background-color: <?= $grade_color ?>;">
                        <?= $grade_icon ?>
                    </div>
                    <div class="grade-text" style="color: <?= $grade_color ?>;"><?= htmlspecialchars(materi_t($grade_name)) ?></div>
                    <div class="grade-xp"><?= t('Total', 'Total') ?>: <?= number_format($total_xp, 0, ',', '.') ?> XP</div>
                    
                    <div class="grade-bar-bg">
                        <div class="grade-bar-fill" style="width: <?= $persen_grade ?>%; background-color: <?= $grade_color ?>;"></div>
                    </div>
                    
                    <?php if($total_xp >= 3000): ?>
                        <div class="grade-target"><?= t('Level Maksimal! Anda Hebat!', 'Maximum level! Great job!') ?></div>
                    <?php else: ?>
                        <div class="grade-target"><?= number_format($next_xp - $total_xp, 0, ',', '.') ?> XP <?= t('menuju grade selanjutnya', 'to the next level') ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Widgets 3: Aktivitas Terakhir -->
            <div class="card-ui">
                <div class="card-title" style="display: flex; justify-content: space-between; align-items:center;">
                    <?= t('Aktivitas Terakhir', 'Recent Activity') ?>
                    <a href="riwayat.php" style="font-size:11px; color:var(--primary-blue); text-decoration:none;"><?= t('Lihat Semua', 'View All') ?></a>
                </div>
                <div class="timeline-list">
                    <?php if($query_riwayat && $jumlah_riwayat > 0): ?>
                        <?php while($riwayat = mysqli_fetch_assoc($query_riwayat)): 
                            $nama_mode = "Latihan";
                            if($riwayat['mode_belajar'] == 'kuis_kosakata') $nama_mode = "Kuis Kosa Kata";
                            if($riwayat['mode_belajar'] == 'kuis_mendengarkan') $nama_mode = "Kuis Audio";
                            if($riwayat['mode_belajar'] == 'mode_speaking') $nama_mode = "Latihan Berbicara";
                            if($riwayat['mode_belajar'] == 'mode_writing') $nama_mode = "Latihan Menulis";
                            
                            $warna_badge = ($riwayat['skor'] >= 60) ? "var(--accent-green)" : "#EF4444";
                            $bg_badge = ($riwayat['skor'] >= 60) ? "rgba(16, 185, 129, 0.1)" : "rgba(239, 68, 68, 0.1)";
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-badge" style="background-color: <?= $bg_badge ?>; color: <?= $warna_badge ?>;">
                                <?php if($riwayat['skor'] >= 60): ?>
                                    <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                <?php else: ?>
                                    <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-info">
                                <h5><?= htmlspecialchars(materi_t($nama_mode)) ?>: <?= htmlspecialchars(materi_t($riwayat['judul_pelajaran'] ?? '(Pelajaran dihapus)')) ?></h5>
                                <p><?= waktu_relatif($riwayat['tanggal_akses']) ?> - <?= t('Skor', 'Score') ?>: <?= $riwayat['skor'] ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: var(--text-muted); font-size: 13px; padding: 20px 0;">
                            <?= t('Belum ada aktivitas. Yuk mulai belajar!', 'No activity yet. Let\'s start learning!') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section Materi Populer -->
        <h3 class="section-header"><?= t('Materi Populer', 'Popular Materials') ?></h3>
        <div class="materi-grid">
            <div class="materi-card">
                <img src="assets/img/hero-alphabet.svg" alt="Ilustrasi alfabet Kiril" class="materi-art">
                <div class="materi-details">
                    <h4><?= t('Alfabet Kiril', 'Cyrillic Alphabet') ?></h4>
                    <p><?= t('Struktur dasar, kelompok huruf mirip, fonetik, dan teknik membaca-menulis.', 'Basic structure, similar letter groups, phonetics, and reading-writing techniques.') ?></p>
                    <a href="alfabet.php" class="learn-btn"><?= t('Mulai Belajar', 'Start Learning') ?></a>
                </div>
            </div>
            <div class="materi-card">
                <img src="assets/img/hero-vocab.svg" alt="Ilustrasi kosakata bahasa Rusia" class="materi-art">
                <div class="materi-details">
                    <h4><?= t('Pelajaran Kosa Kata', 'Vocabulary Lessons') ?></h4>
                    <p><?= t('Kumpulan kosa kata dasar dalam interaksi sehari-hari, lengkap dengan audio.', 'Essential vocabulary for everyday interactions, complete with audio.') ?></p>
                    <a href="kosakata.php" class="learn-btn"><?= t('Mulai Belajar', 'Start Learning') ?></a>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ============================================== -->
<!-- MODAL POPUP: DAFTAR TINGKAT KEAHLIAN (RANK) -->
<!-- ============================================== -->
<div class="modal-overlay" id="rankModal" onclick="closeModal(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <button class="modal-close" onclick="closeModal(event)">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
        <h3 class="modal-title"><?= t('Daftar Tingkat Keahlian', 'Skill Level List') ?></h3>
        
        <div class="rank-list">
            
            <div class="rank-item bronze <?= $grade_name == 'Pemula' ? 'active-rank' : '' ?>">
                <div class="rank-icon" style="background-color: #B45309;"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
                <div class="rank-info"><h4><?= t('Pemula', 'Beginner') ?></h4><p>0 - 499 XP</p></div>
                <?php if($grade_name == 'Pemula'): ?><div class="rank-badge" style="background: #B45309;"><?= t('Saat Ini', 'Current') ?></div><?php endif; ?>
            </div>

            <div class="rank-item silver <?= $grade_name == 'Menengah' ? 'active-rank' : '' ?>">
                <div class="rank-icon" style="background-color: #64748B;"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L3 6v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V6l-9-4z"/></svg></div>
                <div class="rank-info"><h4><?= t('Menengah', 'Intermediate') ?></h4><p>500 - 1.499 XP</p></div>
                <?php if($grade_name == 'Menengah'): ?><div class="rank-badge" style="background: #64748B;"><?= t('Saat Ini', 'Current') ?></div><?php endif; ?>
            </div>

            <div class="rank-item gold <?= $grade_name == 'Mahir' ? 'active-rank' : '' ?>">
                <div class="rank-icon" style="background-color: #D97706;"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M5 16h14v2H5zm14-9l-4 3-3-5-3 5-4-3 2 8h10l2-8z"/></svg></div>
                <div class="rank-info"><h4><?= t('Mahir', 'Advanced') ?></h4><p>1.500 - 2.999 XP</p></div>
                <?php if($grade_name == 'Mahir'): ?><div class="rank-badge" style="background: #D97706;"><?= t('Saat Ini', 'Current') ?></div><?php endif; ?>
            </div>

            <div class="rank-item diamond <?= $grade_name == 'Legenda' ? 'active-rank' : '' ?>">
                <div class="rank-icon" style="background-color: #2563EB;"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 9l10 13 10-13z"/></svg></div>
                <div class="rank-info"><h4><?= t('Legenda', 'Legend') ?></h4><p>3.000+ XP</p></div>
                <?php if($grade_name == 'Legenda'): ?><div class="rank-badge" style="background: #2563EB;"><?= t('Saat Ini', 'Current') ?></div><?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('rankModal');
    function openModal() { modal.classList.add('show'); document.body.style.overflow = 'hidden'; }
    function closeModal(event) { if (event) { modal.classList.remove('show'); document.body.style.overflow = 'auto'; } }
</script>

<?php include __DIR__ . '/partials/target_harian.php'; ?>
</body>
</html> 