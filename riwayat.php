<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/auth.php';
require_once 'config/riwayat_kuis.php';
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

/**
 * Informasi tampilan untuk setiap mode.
 */
function info_mode(string $mode): array {
    $peta = [
        'kuis_kosakata' => [
            'nama' => t('Kuis Kosakata', 'Vocabulary Quiz'), 'kelas' => 'mode-vocab',
            'ikon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        ],
        'kuis_mendengarkan' => [
            'nama' => t('Kuis Mendengarkan', 'Listening Quiz'), 'kelas' => 'mode-listen',
            'ikon' => 'M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z',
        ],
        'mode_speaking' => [
            'nama' => t('Latihan Berbicara', 'Speaking Practice'), 'kelas' => 'mode-speak',
            'ikon' => 'M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z',
        ],
        'mode_writing' => [
            'nama' => t('Latihan Menulis', 'Writing Practice'), 'kelas' => 'mode-write',
            'ikon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        ],
    ];
    if (isset($peta[$mode])) { return $peta[$mode]; }
    return [
        'nama' => t('Latihan', 'Practice'), 'kelas' => 'mode-archive',
        'ikon' => 'M4 7v10a2 2 0 002 2h12a2 2 0 002-2V7M4 7l1.5-3h13L20 7M4 7h16M10 11h4',
    ];
}

function ikon_mode(string $mode): string {
    $info = info_mode($mode);
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $info['ikon'] . '"/></svg>';
}

/** Halaman latihan untuk mengulang sesi dengan mode tertentu. */
function file_mode(string $mode): ?string {
    $peta = [
        'kuis_kosakata'     => 'mode_kuis.php',
        'kuis_mendengarkan' => 'mode_mendengarkan.php',
        'mode_speaking'     => 'mode_speaking.php',
        'mode_writing'      => 'mode_writing.php',
    ];
    return $peta[$mode] ?? null;
}

/** Ambil sesi kuis + detail jawaban. */
$riwayat_sesi = ambil_riwayat_kuis($koneksi, $user_id, 40);

$total_sesi   = count($riwayat_sesi);
$total_soal   = 0;
$total_benar  = 0;
$total_jawaban = 0;
foreach ($riwayat_sesi as $s) {
    $total_soal  += (int)$s['jumlah_soal'];
    $total_benar += (int)$s['jumlah_benar'];
    $total_jawaban += count($s['detail'] ?? []);
}
$akurasi = $total_soal > 0 ? (int)round(($total_benar / $total_soal) * 100) : 0;

/** Rekap skor terbaik per mode. */
$rekap_skor = [];
$q_rekap = mysqli_query($koneksi, "
    SELECT pb.mode_belajar, pb.skor, pb.tanggal_akses,
           p.judul_pelajaran, p.nomor_pelajaran
    FROM tb_progres_belajar pb
    LEFT JOIN tb_pelajaran p ON p.id_pelajaran = pb.id_pelajaran
    WHERE pb.user_id = '$user_id'
      AND pb.mode_belajar IN ('kuis_kosakata','kuis_mendengarkan','mode_speaking','mode_writing')
    ORDER BY pb.tanggal_akses DESC
    LIMIT 60
");
if ($q_rekap) {
    while ($r = mysqli_fetch_assoc($q_rekap)) { $rekap_skor[] = $r; }
}
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Riwayat Pembelajaran', 'Learning History') ?> - RusVoca</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    <link rel="stylesheet" href="assets/css/level-theme.css?v=20261113">
    <style>
        :root {
            --primary-blue: #0F3F7A;
            --bg-light: #F4F6F8;
            --border-color: #E2E8F0;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --card-bg: #FFFFFF;
            --accent-green: #0F9F6E;
            --accent-red: #DC4C4C;
            --accent-gold: #C88A18;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .app-layout { display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: 260px; background: #FFFFFF; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between; padding: 24px 16px; position: fixed; height: 100vh; left: 0; top: 0; z-index: 50; }
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
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-blue); display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; }
        .user-info { flex: 1; overflow: hidden; }
        .user-info-name { font-size: 13px; font-weight: 700; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-info-role { font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 4px; margin-top: 2px; }
        .logout-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 6px; border-radius: 6px; transition: all 0.2s; }
        .logout-btn:hover { color: #EF4444; background-color: rgba(239, 68, 68, 0.1); }

        /* Konten */
        .main-content { margin-left: 260px; flex: 1; padding: 38px 40px 70px; max-width: 1120px; }
        .page-eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: var(--accent-gold); margin-bottom: 10px; }
        .page-eyebrow::before { content: ""; width: 22px; height: 2px; background: var(--accent-gold); }
        .page-title { font-size: 31px; line-height: 1.15; letter-spacing: -.7px; font-weight: 800; margin-bottom: 8px; }
        .page-subtitle { font-size: 14px; color: var(--text-muted); line-height: 1.65; max-width: 680px; }

        .stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin: 26px 0; }
        .stat-card { background: #fff; border: 1px solid var(--border-color); border-radius: 14px; padding: 18px 20px; }
        .stat-card .num { font-size: 28px; font-weight: 800; line-height: 1; color: var(--primary-blue); }
        .stat-card .lbl { margin-top: 6px; font-size: 11.5px; color: var(--text-muted); font-weight: 700; }
        .stat-card:nth-child(2) .num { color: var(--accent-gold); }
        .stat-card:nth-child(3) .num { color: var(--accent-green); }

        .section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
        .section-head h2 { font-size: 18px; font-weight: 800; letter-spacing: -.2px; }
        .section-head p { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .section-note { font-size: 11px; color: var(--text-muted); font-weight: 600; }

        /* Filter sesi */
        .filter-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
        .filter-chip { border: 1px solid var(--border-color); background: #fff; color: var(--text-muted); font-family: inherit; font-size: 11.5px; font-weight: 700; padding: 7px 13px; border-radius: 999px; cursor: pointer; transition: .16s ease; }
        .filter-chip:hover { border-color: #C7D4E6; color: var(--primary-blue); }
        .filter-chip.is-active { background: var(--primary-blue); border-color: var(--primary-blue); color: #fff; }
        .filter-chip .count { opacity: .7; font-weight: 600; margin-left: 3px; }
        .filter-empty { display: none; padding: 34px 20px; text-align: center; font-size: 12.5px; color: var(--text-muted); }

        .history-card { background: #fff; border: 1px solid var(--border-color); border-radius: 18px; padding: 8px 22px; }
        .attempt { border-bottom: 1px solid #EEF2F6; }
        .attempt:last-child { border-bottom: none; }
        .attempt summary { list-style: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 0; }
        .attempt summary::-webkit-details-marker { display: none; }
        .attempt-main { display: flex; align-items: center; gap: 14px; min-width: 0; }
        .mode-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .mode-icon svg { width: 21px; height: 21px; }
        .mode-vocab { background: #EFF6FF; color: #1D4ED8; }
        .mode-listen { background: #FFF7ED; color: #C2410C; }
        .mode-speak { background: #F5F3FF; color: #6D28D9; }
        .mode-write { background: #ECFDF5; color: #047857; }
        .mode-archive { background: #F1F5F9; color: #64748B; }
        .attempt-title { font-size: 14.5px; font-weight: 800; line-height: 1.3; }
        .attempt-title span { font-weight: 600; color: var(--text-muted); }
        .attempt-meta { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: 4px; font-size: 11.5px; color: var(--text-muted); font-weight: 600; }
        .attempt-meta .dot { width: 3px; height: 3px; border-radius: 50%; background: #CBD5E1; align-self: center; }
        .attempt-side { display: flex; align-items: center; gap: 14px; flex-shrink: 0; }
        .attempt-score { text-align: right; }
        .score-num { font-size: 19px; font-weight: 800; line-height: 1; display: block; }
        .score-num.pass { color: var(--accent-green); }
        .score-num.fail { color: var(--accent-red); }
        .score-cap { font-size: 9.5px; font-weight: 800; letter-spacing: .7px; text-transform: uppercase; color: var(--text-muted); }
        .attempt-flag { font-size: 10.5px; font-weight: 800; padding: 4px 9px; border-radius: 999px; white-space: nowrap; }
        .attempt-flag.is-wrong { background: #FEF2F2; color: #B91C1C; }
        .attempt-flag.is-perfect { background: #ECFDF5; color: #047857; }
        .chevron { width: 18px; height: 18px; color: #94A3B8; transition: transform .2s ease; }
        .attempt[open] .chevron { transform: rotate(180deg); }

        .attempt-body { padding: 0 0 18px 58px; }
        .attempt-actions { display: flex; justify-content: flex-end; margin-bottom: 10px; }
        .retry-link { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 800; color: var(--primary-blue); text-decoration: none; border: 1px solid #DBEAFE; background: #EFF6FF; padding: 6px 12px; border-radius: 8px; transition: .16s; }
        .retry-link:hover { background: #DBEAFE; }
        .answer-list { display: flex; flex-direction: column; gap: 8px; }
        .answer-row { display: flex; gap: 10px; padding: 10px 12px; border-radius: 10px; background: #FAFBFC; border: 1px solid #EEF2F6; }
        .answer-row.is-wrong { background: #FFF9F9; border-color: #FBE3E3; }
        .answer-row.is-correct { background: #F8FCFA; border-color: #E3F5EC; }
        .answer-index { width: 16px; flex-shrink: 0; text-align: right; font-size: 10.5px; font-weight: 800; color: #94A3B8; padding-top: 4px; }
        .answer-mark { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; flex-shrink: 0; margin-top: 1px; }
        .is-correct .answer-mark { background: #DCFCE7; color: #15803D; }
        .is-wrong .answer-mark { background: #FEE2E2; color: #B91C1C; }
        .answer-content { min-width: 0; flex: 1; }
        .answer-question { font-size: 13px; font-weight: 800; line-height: 1.4; }
        .answer-question small { font-size: 10.5px; font-weight: 600; color: var(--text-muted); margin-left: 6px; }
        .answer-line { font-size: 12px; color: var(--text-muted); margin-top: 3px; line-height: 1.5; }
        .answer-line b { color: var(--text-dark); font-weight: 700; }
        .answer-line .key { color: #15803D; }
        .answer-line .wrong-answer { color: #B91C1C; }
        .answer-empty { font-size: 12px; color: var(--text-muted); padding: 8px 0 2px 58px; }

        .empty-state { text-align: center; padding: 46px 20px; color: var(--text-muted); }
        .empty-state svg { color: #CBD5E1; margin-bottom: 10px; }
        .empty-state h3 { color: var(--text-dark); font-size: 15px; margin-bottom: 5px; }
        .empty-state p { font-size: 12.5px; line-height: 1.6; }
        .empty-state a { display: inline-block; margin-top: 14px; background: var(--primary-blue); color: #fff; text-decoration: none; font-size: 12px; font-weight: 800; padding: 9px 16px; border-radius: 9px; }

        .best-recap { margin-top: 26px; background: #fff; border: 1px solid var(--border-color); border-radius: 16px; padding: 0 20px; }
        .best-recap summary { list-style: none; cursor: pointer; padding: 16px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .best-recap summary::-webkit-details-marker { display: none; }
        .best-recap summary h2 { font-size: 14.5px; font-weight: 800; }
        .best-recap summary span { font-size: 11.5px; color: var(--text-muted); font-weight: 600; }
        .best-list { padding: 0 0 12px; }
        .best-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 9px 0; border-top: 1px solid #F1F5F9; font-size: 12px; }
        .best-row .best-name { font-weight: 700; }
        .best-row .best-lesson { color: var(--text-muted); font-weight: 600; }
        .best-row .best-score { font-weight: 800; color: var(--primary-blue); white-space: nowrap; }

        @media (max-width: 900px) {
            .stat-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 72px; padding: 16px 8px; }
            .brand-text, .user-info { display: none; }
            .brand-logo-img { width: 32px; height: 32px; }
            .brand-header { padding-bottom: 12px; margin-bottom: 12px; justify-content: center; padding-left: 0; }
            .user-profile-bottom { justify-content: center; padding: 8px; flex-direction: column; gap: 15px; }
            .nav-item a { justify-content: center; padding: 12px; }
            .nav-item span { display: none; }
            .main-content { margin-left: 72px; padding: 24px 18px 60px; }
            .page-title { font-size: 25px; }
            .history-card { padding: 4px 16px; }
            .attempt summary { align-items: flex-start; }
            .attempt-body { padding-left: 0; }
            .answer-empty { padding-left: 0; }
            .attempt-title span { display: block; margin-top: 2px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/mobile.css?v=20261123">
</head>
<body class="<?= htmlspecialchars($level_kelas) ?>">

<div class="app-layout">
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
                <li class="nav-item"><a href="kosakata.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 4h2v5H9V4zm0 7h2v2H9v-2zm0 4h2v2H9v-2zm6 2h-3v-2h3v2zm0-4h-3v-2h3v2zm0-5h-3V4h3v5z"/></svg><span><?= t('Kosakata', 'Vocabulary') ?></span></a></li>
                <li class="nav-item active"><a href="riwayat.php"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span><?= t('Riwayat', 'History') ?></span></a></li>
                <li class="nav-item"><a href="profil.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= t('Profil Saya', 'My Profile') ?></span></a></li>
                <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>
                <li class="nav-lang"><?= tombol_bahasa('sidebar') ?></li>
            </ul>
        </div>
        <div class="user-profile-bottom">
            <div class="avatar-circle"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
            <div class="user-info"><div class="user-info-name"><?= htmlspecialchars($nama_user); ?></div><div class="user-info-role" style="color: <?= $level['warna'] ?>;"><svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="<?= $level['ikon'] ?>"/></svg><span><?= htmlspecialchars(materi_t($level['nama'])) ?></span></div></div>
            <form action="logout.php" method="POST" style="display:flex;" onsubmit="return confirm('<?= t('Apakah Anda yakin ingin keluar?', 'Are you sure you want to log out?') ?>');">
                <?= csrf_field() ?><button type="submit" class="logout-btn" aria-label="Keluar"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></button>
            </form>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-eyebrow"><?= t('Aktivitas belajar', 'Learning activity') ?></div>
        <h1 class="page-title"><?= t('Riwayat Pembelajaran', 'Learning History') ?></h1>
        <p class="page-subtitle"><?= t('Setiap kuis yang selesai tersimpan di sini. Buka salah satu sesi untuk melihat soal mana yang sudah benar dan mana yang masih perlu diulang.', 'Every completed quiz is saved here. Open a session to see which questions were correct and which need another try.') ?></p>

        <div class="stat-row">
            <div class="stat-card"><div class="num"><?= $total_sesi ?></div><div class="lbl"><?= t('Sesi kuis tercatat', 'Recorded quiz sessions') ?></div></div>
            <div class="stat-card"><div class="num"><?= $total_jawaban ?></div><div class="lbl"><?= t('Jawaban dievaluasi', 'Answers evaluated') ?></div></div>
            <div class="stat-card"><div class="num"><?= $akurasi ?>%</div><div class="lbl"><?= t('Akurasi jawaban', 'Answer accuracy') ?></div></div>
        </div>

        <div class="section-head">
            <div>
                <h2><?= t('Aktivitas terbaru', 'Recent activity') ?></h2>
                <p><?= t('Klik satu sesi untuk membuka rincian jawaban.', 'Click a session to open the answer details.') ?></p>
            </div>
            <span class="section-note"><?= t('Maksimal 40 sesi terakhir', 'Latest 40 sessions') ?></span>
        </div>

        <?php
        $jumlah_sesi_salah = 0;
        $jumlah_sesi_sempurna = 0;
        foreach ($riwayat_sesi as $s) {
            $detail_s = $s['detail'] ?? [];
            if (!$detail_s) { continue; }
            $ada_salah = false;
            foreach ($detail_s as $d_s) {
                if ((int)$d_s['is_benar'] !== 1) { $ada_salah = true; break; }
            }
            if ($ada_salah) { $jumlah_sesi_salah++; } else { $jumlah_sesi_sempurna++; }
        }
        ?>
        <div class="filter-row" role="group" aria-label="<?= t('Saring riwayat', 'Filter history') ?>">
            <button type="button" class="filter-chip is-active" data-filter="all">
                <?= t('Semua sesi', 'All sessions') ?> <span class="count"><?= $total_sesi ?></span>
            </button>
            <button type="button" class="filter-chip" data-filter="wrong">
                <?= t('Ada jawaban salah', 'Has wrong answers') ?> <span class="count"><?= $jumlah_sesi_salah ?></span>
            </button>
            <button type="button" class="filter-chip" data-filter="perfect">
                <?= t('Semua benar', 'Perfect score') ?> <span class="count"><?= $jumlah_sesi_sempurna ?></span>
            </button>
        </div>

        <div class="history-card">
            <?php if ($riwayat_sesi): ?>
                <?php foreach ($riwayat_sesi as $sesi): ?>
                    <?php
                    $info = info_mode($sesi['mode_belajar']);
                    $lulus = (int)$sesi['skor'] >= 60;
                    $detail = $sesi['detail'] ?? [];
                    $jumlah_salah = 0;
                    foreach ($detail as $d_salah) {
                        if ((int)$d_salah['is_benar'] !== 1) { $jumlah_salah++; }
                    }
                    $status_sesi = !$detail ? 'nodetail' : ($jumlah_salah > 0 ? 'wrong' : 'perfect');
                    ?>
                    <details class="attempt" data-status="<?= $status_sesi ?>">
                        <summary>
                            <div class="attempt-main">
                                <div class="mode-icon <?= htmlspecialchars($info['kelas']) ?>"><?= ikon_mode($sesi['mode_belajar']) ?></div>
                                <div>
                                    <div class="attempt-title"><?= htmlspecialchars($info['nama']) ?> <span>· <?= t('Bab', 'Chapter') ?> <?= htmlspecialchars($sesi['nomor_pelajaran'] ?? '-') ?> <?= htmlspecialchars(materi_t($sesi['judul_pelajaran'] ?? '')) ?></span></div>
                                    <div class="attempt-meta">
                                        <span><?= format_tanggal($sesi['tanggal_akses'], true) ?></span>
                                        <span class="dot"></span>
                                        <span><?= waktu_relatif($sesi['tanggal_akses']) ?></span>
                                        <span class="dot"></span>
                                        <span><?= (int)$sesi['jumlah_benar'] ?>/<?= (int)$sesi['jumlah_soal'] ?> <?= t('benar', 'correct') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="attempt-side">
                                <?php if ($detail): ?>
                                    <span class="attempt-flag <?= $jumlah_salah > 0 ? 'is-wrong' : 'is-perfect' ?>">
                                        <?= $jumlah_salah > 0
                                            ? $jumlah_salah . ' ' . t('salah', 'wrong')
                                            : t('Semua benar', 'All correct') ?>
                                    </span>
                                <?php endif; ?>
                                <div class="attempt-score">
                                    <span class="score-num <?= $lulus ? 'pass' : 'fail' ?>"><?= (int)$sesi['skor'] ?></span>
                                    <span class="score-cap"><?= $lulus ? t('Tuntas', 'Passed') : t('Belum', 'Not yet') ?></span>
                                </div>
                                <svg class="chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </summary>
                        <div class="attempt-body">
                            <?php $file_ulang = file_mode($sesi['mode_belajar']); ?>
                            <?php if ($file_ulang && $id_pelajaran_mode = (int)($sesi['id_pelajaran'] ?? 0)): ?>
                                <div class="attempt-actions">
                                    <a class="retry-link" href="<?= htmlspecialchars($file_ulang) ?>?id=<?= $id_pelajaran_mode ?>">
                                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M20 9a8 8 0 00-14.5-3M4 15a8 8 0 0014.5 3"/></svg>
                                        <?= t('Ulangi latihan ini', 'Retry this practice') ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($detail): ?>
                                <div class="answer-list">
                                    <?php $no_soal = 0; foreach ($detail as $d): $no_soal++; ?>
                                        <?php
                                        $benar = ((int)$d['is_benar'] === 1);
                                        $tipe = $d['tipe'] ?? '';
                                        if ($tipe === '' || $tipe === '0') { $tipe = $sesi['mode_belajar']; }
                                        $labelUser = t('Jawabanmu', 'Your answer');
                                        $labelKey = t('Kunci', 'Answer key');
                                        if ($tipe === 'speaking') { $labelUser = t('Terdengar', 'Heard'); $labelKey = t('Target', 'Target'); }
                                        elseif ($tipe === 'writing') { $labelUser = t('Tulisanmu', 'You wrote'); $labelKey = t('Kunci', 'Answer key'); }
                                        $tampilkanKunci = !$benar || in_array($tipe, ['writing', 'speaking'], true);
                                        ?>
                                        <div class="answer-row <?= $benar ? 'is-correct' : 'is-wrong' ?>">
                                            <div class="answer-index"><?= $no_soal ?></div>
                                            <div class="answer-mark"><?= $benar ? '✓' : '×' ?></div>
                                            <div class="answer-content">
                                                <div class="answer-question"><?= htmlspecialchars(materi_t($d['pertanyaan'])) ?></div>
                                                <div class="answer-line"><?= $labelUser ?>: <b class="<?= $benar ? '' : 'wrong-answer' ?>"><?= htmlspecialchars($d['jawaban_user'] !== '' ? materi_t($d['jawaban_user']) : '—') ?></b></div>
                                                <?php if ($tampilkanKunci): ?>
                                                    <div class="answer-line"><?= $labelKey ?>: <b class="key"><?= htmlspecialchars(materi_t($d['jawaban_benar'])) ?></b></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="answer-empty"><?= t('Rincian jawaban belum tersedia untuk sesi ini.', 'Answer details are not available for this session.') ?></div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v2m0 4h.01M5 20h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 17.25A2 2 0 005 20z"/></svg>
                    <h3><?= t('Belum ada sesi kuis', 'No quiz sessions yet') ?></h3>
                    <p><?= t('Selesaikan satu kuis, lalu rincian benar dan salahnya akan muncul di sini.', 'Complete a quiz and its correct and incorrect answers will appear here.') ?></p>
                    <a href="kosakata.php"><?= t('Mulai belajar', 'Start learning') ?></a>
                </div>
            <?php endif; ?>
            <div class="filter-empty" id="filter-empty"><?= t('Tidak ada sesi yang cocok dengan saringan ini.', 'No sessions match this filter.') ?></div>
        </div>

        <?php if ($rekap_skor): ?>
            <details class="best-recap">
                <summary>
                    <h2><?= t('Rekap skor terbaik', 'Best score summary') ?></h2>
                    <span><?= t('Data progres per mode', 'Progress data per mode') ?> · <?= count($rekap_skor) ?> <?= t('baris', 'rows') ?></span>
                </summary>
                <div class="best-list">
                    <?php foreach ($rekap_skor as $r): ?>
                        <?php $mi = info_mode($r['mode_belajar']); ?>
                        <div class="best-row">
                            <div>
                                <span class="best-name"><?= htmlspecialchars($mi['nama']) ?></span>
                                <span class="best-lesson"> · <?= t('Bab', 'Chapter') ?> <?= htmlspecialchars($r['nomor_pelajaran'] ?? '-') ?> <?= htmlspecialchars(materi_t($r['judul_pelajaran'] ?? '')) ?></span>
                            </div>
                            <div class="best-score"><?= (int)$r['skor'] ?> / 100</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    </main>
</div>

<script>
(function () {
    var chips = document.querySelectorAll('.filter-chip');
    var attempts = document.querySelectorAll('.attempt');
    var empty = document.getElementById('filter-empty');
    if (!chips.length) { return; }

    function terapkan(filter) {
        var tampil = 0;
        attempts.forEach(function (el) {
            var cocok = filter === 'all' || el.dataset.status === filter;
            el.style.display = cocok ? '' : 'none';
            if (cocok) { tampil++; }
        });
        if (empty) { empty.style.display = tampil === 0 ? 'block' : 'none'; }
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('is-active'); });
            chip.classList.add('is-active');
            terapkan(chip.dataset.filter || 'all');
        });
    });
})();
</script>

<?php include __DIR__ . '/partials/target_harian.php'; ?>
</body>
</html>
