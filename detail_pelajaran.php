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

// Tingkat keahlian untuk tema halaman.
$is_admin    = pengguna_admin($koneksi);
$level       = hitung_level($koneksi, $user_id, $is_admin);
$level_kelas = $level['kelas'];
$id_pelajaran = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data pelajaran
$query_pelajaran = mysqli_query($koneksi, "SELECT * FROM tb_pelajaran WHERE id_pelajaran = $id_pelajaran");

if (!$query_pelajaran || mysqli_num_rows($query_pelajaran) == 0) {
    header("Location: kosakata.php");
    exit();
}
$data_pelajaran = mysqli_fetch_assoc($query_pelajaran);

// =========================================================================================
// CEK KELULUSAN KEEMPAT LATIHAN UNTUK BAB INI
// Mode Game Memori & Tic-Tac-Toe sudah dihapus agar evaluasi lebih fokus.
// =========================================================================================
/**
 * Ambil skor maksimal user untuk satu pelajaran + mode belajar.
 *
 * @param mysqli $koneksi Koneksi database aktif.
 * @param int $user_id ID user.
 * @param int $id_pelajaran ID pelajaran.
 * @param string $mode Mode belajar (mis. 'kuis_kosakata').
 * @return int Skor maksimal (0 jika belum ada data).
 */
function get_max_skor(mysqli $koneksi, int $user_id, int $id_pelajaran, string $mode): int {
    $q = mysqli_query($koneksi, "SELECT MAX(skor) as max_skor FROM tb_progres_belajar WHERE user_id = '$user_id' AND id_pelajaran = $id_pelajaran AND mode_belajar = '$mode'");
    if (!$q) { return 0; }
    $d = mysqli_fetch_assoc($q);
    if (!$d || !isset($d['max_skor']) || $d['max_skor'] === null) { return 0; }
    return (int)$d['max_skor'];
}

$skor_kuis     = get_max_skor($koneksi, $user_id, $id_pelajaran, 'kuis_kosakata');
$skor_audio    = get_max_skor($koneksi, $user_id, $id_pelajaran, 'kuis_mendengarkan');
$skor_speaking = get_max_skor($koneksi, $user_id, $id_pelajaran, 'mode_speaking');
$skor_writing  = get_max_skor($koneksi, $user_id, $id_pelajaran, 'mode_writing');

$tuntas_kuis     = $skor_kuis >= 60;
$tuntas_audio    = $skor_audio >= 60;
$tuntas_speaking = $skor_speaking >= 60;
$tuntas_writing  = $skor_writing >= 60;

$daftar_tuntas   = [$tuntas_kuis, $tuntas_audio, $tuntas_speaking, $tuntas_writing];
$jumlah_tuntas   = count(array_filter($daftar_tuntas));
$total_latihan   = count($daftar_tuntas);
$persen_tuntas   = (int)round(($jumlah_tuntas / $total_latihan) * 100);
$is_tuntas_semua = ($jumlah_tuntas === $total_latihan);

// Jumlah kosakata untuk ditampilkan di hero.
$q_jumlah_kata = mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM tb_kosakata WHERE id_pelajaran = $id_pelajaran");
$jumlah_kata = 0;
if ($q_jumlah_kata) {
    $d_jumlah_kata = mysqli_fetch_assoc($q_jumlah_kata);
    $jumlah_kata = (int)($d_jumlah_kata['jml'] ?? 0);
}

// Ringkasan materi pelajaran untuk banner di atas daftar mode.
$materi_ringkas = trim(strip_tags((string)($data_pelajaran['materi'] ?? '')));
if (function_exists('mb_substr')) {
    $materi_ringkas = mb_substr($materi_ringkas, 0, 190);
} else {
    $materi_ringkas = substr($materi_ringkas, 0, 190);
}
// =========================================================================================
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?> - RusVoca</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    <link rel="stylesheet" href="assets/css/level-theme.css?v=20261113">
    
    <style>
        :root {
            --primary-blue: #0F3F7A; --bg-light: #F4F7FA; --border-color: #E2E8F0;
            --text-dark: #0F172A; --text-muted: #64748B; --card-bg: #FFFFFF;
            --accent-blue: #2563EB; --accent-purple: #9333EA; --accent-orange: #F97316;
            --accent-green: #10B981; --accent-red: #EF4444; --accent-cyan: #06B6D4;
            --accent-gold: #E09B19;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .app-layout { display: flex; min-height: 100vh; }

        /* === SIDEBAR === */
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
        .user-info-role { font-size: 11px; color: var(--text-muted); font-weight: 500; }
        .logout-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 6px; border-radius: 6px; transition: all 0.2s; }
        .logout-btn:hover { color: #EF4444; background-color: rgba(239, 68, 68, 0.1); }

        /* === KONTEN UTAMA === */
        .main-content { margin-left: 260px; flex: 1; padding: 40px; max-width: 1100px; }
        
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 700; margin-bottom: 24px; transition: 0.2s; }
        .btn-back:hover { color: var(--primary-blue); transform: translateX(-5px); }
        
        /* Banner Sukses (Buka Kunci Bab Selanjutnya) */
        .alert-success { background-color: #ECFDF5; border: 1px solid #34D399; border-radius: 12px; padding: 16px 20px; display: flex; gap: 16px; align-items: flex-start; margin-bottom: 24px; box-shadow: 0 4px 6px rgba(16, 185, 129, 0.05); }
        .alert-success .alert-icon { color: var(--accent-green); flex-shrink: 0; }
        .alert-success .alert-text h4 { font-size: 15px; font-weight: 800; color: #065F46; margin-bottom: 4px; }
        .alert-success .alert-text p { font-size: 13px; color: #047857; line-height: 1.5; font-weight: 500; }
        .alert-success .alert-link { color: #065F46; font-weight: 800; text-decoration: underline; }

        .header-lesson { background: #0B2F5E; border-bottom: 3px solid var(--accent-gold); padding: 30px; border-radius: 16px; color: #fff; margin-bottom: 35px; box-shadow: 0 10px 25px rgba(15, 63, 122, 0.15); }
        .header-lesson h4 { font-size: 14px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; margin-bottom: 8px; }
        .header-lesson h1 { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
        .header-lesson p { font-size: 15px; opacity: 0.9; }

        /* Panel progres bab */
        .detail-progress {
            background: #FFFFFF; border: 1px solid var(--border-color); border-radius: 16px;
            padding: 18px 22px; margin: -10px 0 30px; display: flex; align-items: center; gap: 18px;
            box-shadow: 0 4px 14px rgba(15, 63, 122, .04); flex-wrap: wrap;
        }
        .detail-progress-label { font-size: 13px; font-weight: 800; color: var(--text-dark); }
        .detail-progress-label small { display: block; font-size: 11px; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
        .detail-progress-track { flex: 1; min-width: 160px; height: 10px; border-radius: 999px; background: #E2E8F0; overflow: hidden; }
        .detail-progress-fill { height: 100%; border-radius: inherit; background: var(--accent-green); transition: width .6s ease; }
        .detail-progress-dots { display: flex; gap: 6px; }
        .detail-dot { width: 10px; height: 10px; border-radius: 50%; background: #E2E8F0; transition: .2s; }
        .detail-dot.done { background: var(--accent-green); box-shadow: 0 0 0 3px rgba(16, 185, 129, .15); }
        .detail-progress-count { font-size: 12px; font-weight: 800; color: var(--accent-green); white-space: nowrap; }

        /* Ringkasan materi pelajaran */
        .material-preview {
            display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
            background: #FFFBEB; border: 1px solid #FDE68A; border-left: 4px solid var(--accent-gold);
            border-radius: 14px; padding: 16px 20px; margin-bottom: 30px;
        }
        .material-preview-icon {
            width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: #FEF3C7; color: #B45309;
        }
        .material-preview-copy { flex: 1; min-width: 200px; }
        .material-preview-copy h4 { font-size: 14px; font-weight: 800; color: #78350F; margin-bottom: 3px; }
        .material-preview-copy p { font-size: 12.5px; color: #92400E; line-height: 1.5; }
        .material-preview a {
            display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0;
            padding: 9px 16px; border-radius: 9px; background: var(--accent-gold);
            color: #3B2500; text-decoration: none; font-size: 12.5px; font-weight: 800;
        }
        .material-preview a:hover { background: #F7C04A; }

        /* Tambahan kecil untuk kartu mode */
        .mode-card { position: relative; overflow: hidden; }
        .mode-card::after {
            content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
            box-shadow: inset 0 0 0 1px transparent; transition: .25s;
        }
        .mode-card:hover::after { box-shadow: inset 0 0 0 1px rgba(37, 99, 235, .25); }
        .mode-text { min-width: 0; }
        .mode-text p { overflow-wrap: anywhere; }
        .mode-card .badge-status { flex-shrink: 0; }

        .section-title { font-size: 20px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .section-desc { font-size: 14px; color: var(--text-muted); margin-bottom: 20px; font-weight: 500; }
        
        .modes-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 40px; }

        .mode-card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; display: flex; align-items: center; gap: 20px; text-decoration: none; transition: all 0.3s ease; }
        .mode-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.06); border-color: var(--primary-blue); }
        
        .mode-card.card-tuntas { border: 2px solid var(--accent-green); }

        .mode-icon-box { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .mode-text { flex: 1; }
        .mode-text .title-wrapper { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; gap: 8px; }
        .mode-text h3 { font-size: 16px; font-weight: 800; color: var(--text-dark); }
        .mode-text p { font-size: 12px; color: var(--text-muted); line-height: 1.4; }

        .badge-status { font-size: 10px; font-weight: 800; padding: 4px 8px; border-radius: 6px; text-transform: uppercase; white-space: nowrap; }
        .badge-tuntas { background-color: #D1FAE5; color: #059669; }
        .badge-belum { background-color: #FEE2E2; color: #DC2626; }

        .color-vocab { background: #EFF6FF; color: #1D4ED8; }
        .color-flash { background: #F5F3FF; color: #6D28D9; }
        .color-quiz { background: #ECFDF5; color: #047857; }
        .color-memory { background: #ECFEFF; color: #0E7490; }
        .color-listen { background: #FFFBEB; color: #B45309; }
        .color-catur { background: #FEF2F2; color: #B91C1C; }
        .color-speak { background: #F5F3FF; color: #6D28D9; }
        .color-write { background: #FDF2F8; color: #BE185D; }

        @media (max-width: 768px) {
            .sidebar { width: 72px; padding: 16px 8px; }
            .brand-text, .user-info { display: none; }
            .brand-logo-img { width: 32px; height: 32px; }
            .main-content { margin-left: 72px; padding: 24px; }
            .modes-grid { grid-template-columns: 1fr; }
            .mode-text .title-wrapper { flex-direction: column; align-items: flex-start; }
        }
    </style>
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
                <li class="nav-item"><a href="dashboard.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg><span><?= t('Dashboard', 'Dashboard') ?></span></a></li>
                <li class="nav-item"><a href="alfabet.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h9v2zm0-4H9V5h9v2z"/></svg><span><?= t('Belajar Alfabet', 'Learn the Alphabet') ?></span></a></li>
                <li class="nav-item active"><a href="kosakata.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 4h2v5H9V4zm0 7h2v2H9v-2zm0 4h2v2H9v-2zm6 2h-3v-2h3v2zm0-4h-3v-2h3v2zm0-5h-3V4h3v5z"/></svg><span><?= t('Kosakata', 'Vocabulary') ?></span></a></li>
                <li class="nav-item"><a href="riwayat.php"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span><?= t('Riwayat', 'History') ?></span></a></li>
                
                <!-- LINK PROFIL SUDAH DIPERBAIKI DI SINI -->
                <li class="nav-item"><a href="profil.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= t('Profil Saya', 'My Profile') ?></span></a></li>
                <li class="nav-lang"><?= tombol_bahasa('sidebar') ?></li>
            </ul>
        </div>
        <div class="user-profile-bottom">
            <div class="avatar-circle">
                <svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <div class="user-info">
                <div class="user-info-name"><?= htmlspecialchars($nama_user); ?></div>
                <div class="user-info-role"><?= t('Siswa', 'Student') ?></div>
            </div>
            <form action="logout.php" method="POST" style="display: flex;" onsubmit="return confirm('<?= t('Apakah Anda yakin ingin keluar?', 'Are you sure you want to log out?') ?>');">
                <?= csrf_field() ?><button type="submit" class="logout-btn"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></button>
            </form>
        </div>
    </aside>

    <main class="main-content">
        <a href="kosakata.php" class="btn-back">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            <?= t('Kembali ke Daftar Pelajaran', 'Back to Lesson List') ?>
        </a>

        <?php if($is_tuntas_semua): ?>
        <div class="alert-success">
            <div class="alert-icon">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="alert-text">
                <h4><?= t('Selamat! Kamu Telah Menuntaskan Bab Ini!', 'Congratulations! You have completed this chapter!') ?></h4>
                <p><?= t('Kamu sudah menyelesaikan keempat evaluasi (Kuis Kosa Kata, Kuis Audio, Speaking, dan Writing).', 'You have completed all four evaluations (Vocabulary Quiz, Listening Quiz, Speaking, and Writing).') ?> <b><?= t('Materi pelajaran bab selanjutnya kini telah terbuka', 'The next chapter is now unlocked') ?></b> <?= t('dan bisa kamu akses di halaman', 'and can be accessed on the') ?> <a href="kosakata.php" class="alert-link"><?= t('Daftar Pelajaran', 'Lesson List') ?></a>.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="rv-hero rv-rise">
            <div class="rv-hero-copy">
                <span class="rv-eyebrow"><span class="dot"></span> <?= t('Pelajaran', 'Lesson') ?> <?= (int)$data_pelajaran['nomor_pelajaran'] ?></span>
                <h1><?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?></h1>
                <p><?= htmlspecialchars($data_pelajaran['deskripsi'] ? materi_t($data_pelajaran['deskripsi']) : t('Pelajari kosakata baru, dengarkan pelafalannya, lalu tuntaskan empat latihan interaktif di bab ini.', 'Learn new vocabulary, listen to the pronunciation, then complete four interactive practices in this chapter.')) ?></p>
                <div class="rv-hero-stats">
                    <div class="rv-stat"><b><?= $jumlah_kata ?></b><span><?= t('Kosakata', 'Vocabulary') ?></span></div>
                    <div class="rv-stat"><b><?= $total_latihan ?></b><span><?= t('Latihan wajib', 'Required practices') ?></span></div>
                    <div class="rv-stat"><b><?= $jumlah_tuntas ?>/<?= $total_latihan ?></b><span><?= t('Tuntas', 'Completed') ?></span></div>
                    <div class="rv-stat"><b>60</b><span><?= t('Skor lulus', 'Passing score') ?></span></div>
                </div>
            </div>
            <div class="rv-hero-art">
                <img src="assets/img/hero-vocab.svg" alt="Ilustrasi buku kosakata bahasa Rusia">
            </div>
        </div>

        <div class="detail-progress rv-rise" style="--d: 90ms;">
            <div class="detail-progress-label">
                <?= t('Progres bab ini', 'Chapter progress') ?>
                <small><?= $is_tuntas_semua ? t('Semua latihan sudah tuntas. Bab berikutnya terbuka!', 'All practices completed. The next chapter is unlocked!') : t('Tuntaskan semua latihan dengan skor minimal 60.', 'Complete all practices with a minimum score of 60.') ?></small>
            </div>
            <div class="detail-progress-track"><div class="detail-progress-fill" style="width: <?= $persen_tuntas ?>%;"></div></div>
            <div class="detail-progress-dots" aria-hidden="true">
                <?php foreach ($daftar_tuntas as $tuntas): ?><span class="detail-dot <?= $tuntas ? 'done' : '' ?>"></span><?php endforeach; ?>
            </div>
            <div class="detail-progress-count"><?= $persen_tuntas ?>%</div>
        </div>

        <?php if ($materi_ringkas !== ''): ?>
        <div class="material-preview rv-rise" style="--d: 120ms;">
            <div class="material-preview-icon">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
            </div>
            <div class="material-preview-copy">
                <h4><?= t('Materi pelajaran bab ini', 'This chapter lesson material') ?></h4>
                <p><?= htmlspecialchars($materi_ringkas) ?><?= strlen($materi_ringkas) >= 190 ? '…' : '' ?></p>
            </div>
            <a href="mode_kosakata.php?id=<?= $id_pelajaran ?>">
                <?= t('Baca Materi', 'Read Material') ?>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
            </a>
        </div>
        <?php endif; ?>

        <h3 class="section-title">
            <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="var(--primary-blue)" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <?= t('Materi Utama', 'Core Material') ?>
        </h3>
        <p class="section-desc"><?= t('Pahami dan hafalkan materi ini terlebih dahulu sebelum mengerjakan Latihan & Evaluasi.', 'Understand and memorize this material before taking the Practices & Evaluation.') ?></p>
        
        <div class="modes-grid">
            <a href="mode_kosakata.php?id=<?= $id_pelajaran ?>" class="mode-card rv-rise" style="--d: 60ms;">
                <div class="mode-icon-box color-vocab"><svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg></div>
                <div class="mode-text">
                    <div class="title-wrapper"><h3><?= t('Materi Pelajaran', 'Lesson Material') ?></h3><span class="rv-chip is-gold"><?= $jumlah_kata ?> <?= t('kata', 'words') ?></span></div>
                    <p><?= t('Penjelasan materi, kosakata berkelompok, contoh kalimat, dan kuis materi.', 'Material explanation, grouped vocabulary, example sentences, and a material quiz.') ?></p>
                </div>
            </a>
            <a href="mode_flashcard.php?id=<?= $id_pelajaran ?>" class="mode-card rv-rise" style="--d: 120ms;">
                <div class="mode-icon-box color-flash"><svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg></div>
                <div class="mode-text"><div class="title-wrapper"><h3><?= t('Kartu Flash (Flashcards)', 'Flashcards') ?></h3></div><p><?= t('Uji ingatan dengan bolak-balik kartu.', 'Test your memory by flipping the cards.') ?></p></div>
            </a>
        </div>

        <h3 class="section-title" style="margin-top: 10px;">
            <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="var(--accent-green)" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <?= t('Latihan & Evaluasi', 'Practice & Evaluation') ?>
        </h3>
        <p class="section-desc">
            <?= t('Kamu', 'You') ?> <b><?= t('wajib lulus keempat latihan di bawah', 'must pass all four practices below') ?></b> <?= t('(minimal skor 60) untuk membuka akses ke Pelajaran Bab selanjutnya.', '(minimum score 60) to unlock access to the next chapter.') ?>
        </p>
        
        <div class="modes-grid">
            <a href="mode_kuis.php?id=<?= $id_pelajaran ?>" class="mode-card rv-rise <?= $tuntas_kuis ? 'card-tuntas' : '' ?>" style="--d: 60ms;">
                <div class="mode-icon-box color-quiz"><svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg></div>
                <div class="mode-text">
                    <div class="title-wrapper"><h3><?= t('Kuis Kosa Kata', 'Vocabulary Quiz') ?></h3><?php if($tuntas_kuis): ?><span class="badge-status badge-tuntas"><?= t('Tuntas', 'Passed') ?> (<?= $skor_kuis ?>)</span><?php else: ?><span class="badge-status badge-belum"><?= t('Belum Tuntas', 'Not Passed') ?></span><?php endif; ?></div>
                    <p><?= t('Tebak arti kata Rusia. Ada tombol suara untuk mendengar pelafalannya.', 'Guess the meaning of Russian words. Use the sound button to hear the pronunciation.') ?></p>
                </div>
            </a>

            <a href="mode_mendengarkan.php?id=<?= $id_pelajaran ?>" class="mode-card rv-rise <?= $tuntas_audio ? 'card-tuntas' : '' ?>" style="--d: 120ms;">
                <div class="mode-icon-box color-listen"><svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg></div>
                <div class="mode-text">
                    <div class="title-wrapper"><h3><?= t('Kuis Mendengarkan', 'Listening Quiz') ?></h3><?php if($tuntas_audio): ?><span class="badge-status badge-tuntas"><?= t('Tuntas', 'Passed') ?> (<?= $skor_audio ?>)</span><?php else: ?><span class="badge-status badge-belum"><?= t('Belum Tuntas', 'Not Passed') ?></span><?php endif; ?></div>
                    <p><?= t('Dengarkan audio, lalu pilih jawaban yang tepat.', 'Listen to the audio, then choose the correct answer.') ?></p>
                </div>
            </a>

            <a href="mode_speaking.php?id=<?= $id_pelajaran ?>" class="mode-card rv-rise <?= $tuntas_speaking ? 'card-tuntas' : '' ?>" style="--d: 180ms;">
                <div class="mode-icon-box color-speak"><svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg></div>
                <div class="mode-text">
                    <div class="title-wrapper"><h3><?= t('Berbicara (Speaking)', 'Speaking') ?></h3><?php if($tuntas_speaking): ?><span class="badge-status badge-tuntas"><?= t('Tuntas', 'Passed') ?> (<?= $skor_speaking ?>)</span><?php else: ?><span class="badge-status badge-belum"><?= t('Belum Tuntas', 'Not Passed') ?></span><?php endif; ?></div>
                    <p><?= t('Ucapkan kosa kata dengan mikrofon dan dapatkan penilaian.', 'Pronounce the vocabulary using your microphone and get a score.') ?></p>
                </div>
            </a>

            <a href="mode_writing.php?id=<?= $id_pelajaran ?>" class="mode-card rv-rise <?= $tuntas_writing ? 'card-tuntas' : '' ?>" style="--d: 240ms;">
                <div class="mode-icon-box color-write"><svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></div>
                <div class="mode-text">
                    <div class="title-wrapper"><h3><?= t('Menulis (Writing)', 'Writing') ?></h3><?php if($tuntas_writing): ?><span class="badge-status badge-tuntas"><?= t('Tuntas', 'Passed') ?> (<?= $skor_writing ?>)</span><?php else: ?><span class="badge-status badge-belum"><?= t('Belum Tuntas', 'Not Passed') ?></span><?php endif; ?></div>
                    <p><?= t('Susun huruf Kiril dengan bantuan suara dan transliterasi.', 'Arrange Cyrillic letters with sound and transliteration support.') ?></p>
                </div>
            </a>
        </div>
    </main>
</div>

</body>
</html>