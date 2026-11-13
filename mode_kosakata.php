<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/bahasa.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$nama_user    = $_SESSION['nama'] ?? 'Siswa';
$id_pelajaran = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil info pelajaran
$query_pelajaran = mysqli_query($koneksi, "SELECT * FROM tb_pelajaran WHERE id_pelajaran = $id_pelajaran");
if (!$query_pelajaran || mysqli_num_rows($query_pelajaran) == 0) {
    header("Location: kosakata.php");
    exit();
}
$data_pelajaran = mysqli_fetch_assoc($query_pelajaran);

// Ambil daftar kosakata berdasarkan id_pelajaran.
// Urutkan per kelompok agar tampil rapi seperti materi LingoHut.
$query_kosakata = mysqli_query($koneksi, "
    SELECT * FROM tb_kosakata
    WHERE id_pelajaran = $id_pelajaran
    ORDER BY id_kosakata ASC
");

$kosakata_array = [];
if ($query_kosakata) {
    while ($row = mysqli_fetch_assoc($query_kosakata)) {
        $row['kelompok'] = trim((string)($row['kelompok'] ?? ''));
        if ($row['kelompok'] === '') { $row['kelompok'] = 'Kosakata'; }
        $kosakata_array[] = $row;
    }
}

// Kelompokkan kosakata (urutan mengikuti query).
$kelompok_kosakata = [];
foreach ($kosakata_array as $kata) {
    $kelompok_kosakata[$kata['kelompok']][] = $kata;
}

$jumlah_kata    = count($kosakata_array);
$jumlah_kelompok = count($kelompok_kosakata);

/**
 * Bersihkan HTML materi dari admin. Hanya tag ringan yang diizinkan agar
 * tampilan materi aman sekaligus tetap mendukung paragraf, daftar, dan bold.
 */
function materi_aman(?string $html): string
{
    $html = trim((string)$html);
    if ($html === '') { return ''; }

    // Materi polos (tanpa tag): ubah baris baru menjadi paragraf.
    if (strpos($html, '<') === false) {
        return '<p>' . nl2br(htmlspecialchars($html, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    $bersih = strip_tags($html, '<p><br><strong><b><em><i><ul><ol><li><h4><h5><span>');
    // Buang atribut event (onclick dll) dan skema javascript:.
    $bersih = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $bersih);
    $bersih = preg_replace('/javascript\s*:/i', '', $bersih);
    return $bersih;
}

$json_kosakata = json_encode(
    $kosakata_array,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Materi Pelajaran', 'Lesson Material') ?> - <?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">

    <style>
        :root {
            --primary-blue: #0F3F7A; --bg-light: #F4F7FA; --border-color: #E2E8F0;
            --text-dark: #0F172A; --text-muted: #64748B; --card-bg: #FFFFFF;
            --accent-blue: #2563EB; --accent-green: #10B981; --accent-gold: #E09B19;
            --accent-purple: #6D28D9; --accent-red: #EF4444;
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
        .main-content { margin-left: 260px; flex: 1; padding: 40px; max-width: 1000px; margin-right: auto; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 700; margin-bottom: 24px; transition: 0.2s; }
        .btn-back:hover { color: var(--primary-blue); transform: translateX(-5px); }

        /* === KARTU MATERI === */
        .material-card {
            background: #fff; border: 1px solid var(--border-color); border-radius: 18px;
            padding: 28px 30px; margin-bottom: 32px; box-shadow: 0 4px 16px rgba(15, 63, 122, .05);
            border-top: 4px solid var(--accent-gold);
        }
        .material-eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: #B45309; background: #FFFBEB; border: 1px solid #FDE68A; padding: 5px 10px; border-radius: 999px; margin-bottom: 14px; }
        .material-card h2 { font-size: 22px; font-weight: 800; color: var(--text-dark); margin-bottom: 14px; }
        .material-body { font-size: 15px; line-height: 1.75; color: #334155; }
        .material-body p { margin-bottom: 12px; }
        .material-body ul, .material-body ol { margin: 0 0 14px 22px; }
        .material-body li { margin-bottom: 7px; }
        .material-body h4 { font-size: 15px; font-weight: 800; color: var(--primary-blue); margin: 20px 0 8px; }
        .material-body h5 { font-size: 14px; font-weight: 800; color: var(--primary-blue); margin: 16px 0 6px; }
        .material-body strong { color: var(--text-dark); }

        .goal-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 20px; padding-top: 18px; border-top: 1px dashed var(--border-color); }
        .goal-chip { font-size: 11.5px; font-weight: 800; color: var(--primary-blue); background: #EFF6FF; border: 1px solid #DBEAFE; padding: 6px 11px; border-radius: 999px; }

        /* === KOSAKATA === */
        .group-block { margin-bottom: 30px; }
        .group-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .group-head h3 { font-size: 17px; font-weight: 800; color: var(--text-dark); }
        .group-count { font-size: 11px; font-weight: 800; color: var(--text-muted); background: #F1F5F9; border: 1px solid var(--border-color); padding: 3px 9px; border-radius: 999px; }

        .word-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; }
        .word-card {
            background: #fff; border: 1px solid var(--border-color); border-radius: 15px;
            padding: 16px 18px; display: flex; align-items: flex-start; gap: 12px;
            transition: all .2s ease; box-shadow: 0 2px 4px rgba(0,0,0,.02);
        }
        .word-card:hover { border-color: #93C5FD; box-shadow: 0 8px 18px rgba(15, 63, 122, .08); transform: translateY(-2px); }
        .word-main { flex: 1; min-width: 0; }
        .word-ru { font-size: 22px; font-weight: 800; color: var(--primary-blue); line-height: 1.2; letter-spacing: .3px; }
        .word-read { font-size: 12.5px; color: var(--text-muted); font-style: italic; margin-top: 2px; }
        .word-id { font-size: 14.5px; font-weight: 700; color: var(--text-dark); margin-top: 7px; }
        .word-example { margin-top: 10px; padding-top: 9px; border-top: 1px dashed var(--border-color); }
        .word-example .ru { font-size: 12.5px; font-weight: 700; color: #475569; line-height: 1.45; }
        .word-example .id { font-size: 11.5px; color: var(--text-muted); line-height: 1.45; margin-top: 2px; }
        .word-actions { display: flex; flex-direction: column; gap: 6px; flex-shrink: 0; }

        /* === KUIS MATERI === */
        .quiz-card {
            background: #fff; border: 1px solid var(--border-color); border-radius: 18px;
            padding: 26px 28px; box-shadow: 0 4px 16px rgba(15, 63, 122, .05);
            border-top: 4px solid var(--accent-purple);
        }
        .quiz-top { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
        .quiz-icon { width: 50px; height: 50px; border-radius: 14px; background: #F5F3FF; color: var(--accent-purple); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .quiz-top h3 { font-size: 18px; font-weight: 800; }
        .quiz-top p { font-size: 12.5px; color: var(--text-muted); margin-top: 3px; }
        .quiz-hint { font-size: 12.5px; color: var(--text-muted); background: #FAF5FF; border: 1px dashed #DDD6FE; border-radius: 11px; padding: 12px 15px; line-height: 1.55; }
        .quiz-start { display: inline-flex; align-items: center; gap: 8px; margin-top: 16px; padding: 12px 22px; border: none; border-radius: 11px; background: var(--accent-purple); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; transition: .2s; }
        .quiz-start:hover { background: #5B21B6; transform: translateY(-2px); }
        .quiz-start:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        #mk-quiz { display: none; }
        .mk-progress-row { display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 800; color: var(--text-muted); margin-bottom: 8px; }
        .mk-progress-track { height: 8px; border-radius: 999px; background: #EDE9FE; overflow: hidden; margin-bottom: 22px; }
        .mk-progress-fill { height: 100%; background: var(--accent-purple); width: 0; transition: width .3s ease; }
        .mk-word { font-size: 34px; font-weight: 800; color: var(--text-dark); text-align: center; line-height: 1.2; }
        .mk-read { text-align: center; font-size: 14px; color: var(--text-muted); font-style: italic; margin-top: 4px; }
        .mk-audio-row { display: flex; justify-content: center; margin: 12px 0 22px; }
        .mk-options { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .mk-option { padding: 14px; font-size: 14.5px; font-weight: 700; color: var(--text-dark); background: #fff; border: 2px solid var(--border-color); border-radius: 11px; cursor: pointer; transition: .18s; }
        .mk-option:hover:not(:disabled) { border-color: var(--accent-purple); background: #FAF5FF; }
        .mk-option.correct { background: #D1FAE5; border-color: var(--accent-green); color: #065F46; }
        .mk-option.wrong { background: #FEE2E2; border-color: var(--accent-red); color: #991B1B; opacity: .85; }
        .mk-option:disabled { cursor: not-allowed; }
        .mk-next { display: none; margin: 18px auto 0; padding: 11px 26px; border: none; border-radius: 10px; background: var(--primary-blue); color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; transition: .2s; }
        .mk-next:hover { background: #0A2D59; }

        .mk-result { display: none; text-align: center; padding: 10px 0; }
        .mk-score { width: 100px; height: 100px; border-radius: 50%; background: var(--accent-purple); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; margin: 0 auto 16px; box-shadow: 0 10px 22px rgba(109, 40, 217, .25); }
        .mk-result h4 { font-size: 20px; font-weight: 800; margin-bottom: 6px; }
        .mk-result p { font-size: 13.5px; color: var(--text-muted); margin-bottom: 18px; }
        .mk-result-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .mk-btn-ghost { padding: 11px 20px; border-radius: 10px; border: 1px solid var(--border-color); background: #fff; color: var(--primary-blue); font-size: 13px; font-weight: 800; cursor: pointer; text-decoration: none; }
        .mk-btn-ghost:hover { border-color: #93C5FD; background: #F8FAFC; }

        .quiz-cta { margin-top: 20px; padding-top: 18px; border-top: 1px dashed var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .quiz-cta p { font-size: 12.5px; color: var(--text-muted); }
        .quiz-cta a { display: inline-flex; align-items: center; gap: 7px; padding: 10px 18px; border-radius: 10px; background: var(--primary-blue); color: #fff; text-decoration: none; font-size: 13px; font-weight: 800; }
        .quiz-cta a:hover { background: #0A2D59; }

        .empty-state { text-align: center; padding: 40px; border: 1px dashed var(--border-color); border-radius: 12px; color: var(--text-muted); background: #fff; }

        @media (max-width: 768px) {
            .sidebar { width: 72px; padding: 16px 8px; }
            .brand-text, .user-info { display: none; }
            .brand-logo-img { width: 32px; height: 32px; }
            .brand-header { padding-bottom: 12px; margin-bottom: 12px; justify-content: center; padding-left: 0; }
            .user-profile-bottom { justify-content: center; padding: 8px; flex-direction: column; gap: 15px; }
            .nav-item a { justify-content: center; padding: 12px; }
            .nav-item span { display: none; }
            .main-content { margin-left: 72px; padding: 24px; }
            .material-card, .quiz-card { padding: 20px; }
            .mk-options { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <div>
            <div class="brand-header">
                <img src="assets/img/logo.png" alt="Logo RusVoca" class="brand-logo-img">
                <div class="brand-text">
                    <div class="brand-name">Rus<span>Voca</span></div>
                    <div class="brand-sub"><?= t('APLIKASI KOSAKATA BAHASA RUSIA', 'RUSSIAN VOCABULARY APP') ?></div>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg><span><?= t('Dashboard', 'Dashboard') ?></span></a></li>
                <li class="nav-item"><a href="alfabet.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h9v2zm0-4H9V5h9v2z"/></svg><span><?= t('Belajar Alfabet', 'Learn the Alphabet') ?></span></a></li>
                <li class="nav-item active"><a href="kosakata.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 4h2v5H9V4zm0 7h2v2H9v-2zm0 4h2v2H9v-2zm6 2h-3v-2h3v2zm0-4h-3v-2h3v2zm0-5h-3V4h3v5z"/></svg><span><?= t('Kosakata', 'Vocabulary') ?></span></a></li>
                <li class="nav-item"><a href="riwayat.php"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span><?= t('Riwayat', 'History') ?></span></a></li>
                <li class="nav-item"><a href="profil.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= t('Profil Saya', 'My Profile') ?></span></a></li>
                <li class="nav-lang"><?= tombol_bahasa('sidebar') ?></li>
            </ul>
        </div>
        <div class="user-profile-bottom">
            <div class="avatar-circle"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
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
        <a href="detail_pelajaran.php?id=<?= $id_pelajaran ?>" class="btn-back">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            <?= t('Kembali ke Pilihan Mode', 'Back to Mode Selection') ?>
        </a>

        <div class="rv-hero rv-rise">
            <div class="rv-hero-copy">
                <span class="rv-eyebrow"><span class="dot"></span> <?= t('Pelajaran', 'Lesson') ?> <?= (int)$data_pelajaran['nomor_pelajaran'] ?> · <?= t('Materi kosakata', 'Vocabulary material') ?></span>
                <h1><?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?></h1>
                <p><?= t('Baca materi singkat, pelajari kosakatanya, lalu uji pemahamanmu lewat kuis materi di bagian bawah halaman ini.', 'Read the short material, study the vocabulary, then test your understanding with the material quiz at the bottom of this page.') ?></p>
                <div class="rv-hero-stats">
                    <div class="rv-stat"><b><?= $jumlah_kata ?></b><span><?= t('Kata', 'Words') ?></span></div>
                    <div class="rv-stat"><b><?= $jumlah_kelompok ?></b><span><?= t('Kelompok', 'Groups') ?></span></div>
                    <div class="rv-stat"><b><?= $jumlah_kata ?></b><span><?= t('Soal kuis materi', 'Material quiz items') ?></span></div>
                </div>
            </div>
            <div class="rv-hero-art">
                <img src="assets/img/hero-vocab.svg" alt="<?= t('Ilustrasi buku kosakata bahasa Rusia', 'Russian vocabulary book illustration') ?>">
            </div>
        </div>

        <?php if (trim((string)($data_pelajaran['materi'] ?? '')) !== ''): ?>
        <section class="material-card rv-rise">
            <span class="material-eyebrow">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                <?= t('Materi Pelajaran', 'Lesson Material') ?>
            </span>
            <h2><?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?></h2>
            <div class="material-body"><?= materi_aman($data_pelajaran['materi']) ?></div>

            <?php if ($jumlah_kelompok > 0): ?>
            <div class="goal-row">
                <span class="goal-chip"><?= t('Kosakata bab ini', 'Vocabulary in this chapter') ?>: <?= $jumlah_kata ?> <?= t('kata', 'words') ?></span>
                <?php foreach (array_keys($kelompok_kosakata) as $nama_kelompok): ?>
                    <span class="goal-chip"><?= htmlspecialchars(materi_t($nama_kelompok)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <div class="rv-section-head">
            <div>
                <h3>
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="var(--primary-blue)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                    <?= t('Kosakata Materi', 'Material Vocabulary') ?>
                </h3>
                <p><?= t('Setiap kata dilengkapi cara baca, arti, dan contoh kalimat. Ketuk ikon suara untuk mendengar pelafalan Rusia.', 'Each word includes pronunciation, meaning, and an example sentence. Tap the sound icon to hear the Russian pronunciation.') ?></p>
            </div>
        </div>

        <?php if ($jumlah_kata > 0): ?>
            <?php foreach ($kelompok_kosakata as $nama_kelompok => $daftar_kata): ?>
                <section class="group-block rv-rise">
                    <div class="group-head">
                        <h3><?= htmlspecialchars(materi_t($nama_kelompok)) ?></h3>
                        <span class="group-count"><?= count($daftar_kata) ?> <?= t('kata', 'words') ?></span>
                    </div>
                    <div class="word-grid">
                        <?php foreach ($daftar_kata as $row): ?>
                        <article class="word-card">
                            <div class="word-main">
                                <div class="word-ru"><?= htmlspecialchars($row['kata_rusia']) ?></div>
                                <div class="word-read">[ <?= htmlspecialchars($row['cara_baca']) ?> ]</div>
                                <div class="word-id"><?= htmlspecialchars($row['arti_indonesia']) ?></div>
                                <?php if (trim((string)($row['contoh_kalimat'] ?? '')) !== ''): ?>
                                <div class="word-example">
                                    <div class="ru"><?= htmlspecialchars($row['contoh_kalimat']) ?></div>
                                    <?php if (trim((string)($row['arti_contoh'] ?? '')) !== ''): ?>
                                    <div class="id"><?= htmlspecialchars($row['arti_contoh']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="word-actions">
                                <button type="button" class="rv-audio is-small" data-ucap="<?= htmlspecialchars($row['kata_rusia']) ?>" data-rate="0.85" title="<?= t('Dengarkan kata', 'Listen to the word') ?>" aria-label="<?= t('Dengarkan pelafalan', 'Listen to pronunciation') ?> <?= htmlspecialchars($row['kata_rusia']) ?>">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>
                                </button>
                                <?php if (trim((string)($row['contoh_kalimat'] ?? '')) !== ''): ?>
                                <button type="button" class="rv-audio is-small" data-ucap="<?= htmlspecialchars($row['contoh_kalimat']) ?>" data-rate="0.8" title="<?= t('Dengarkan contoh kalimat', 'Listen to the example sentence') ?>" aria-label="<?= t('Dengarkan contoh kalimat', 'Listen to the example sentence') ?>">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                                </button>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <?= t('Belum ada kosakata untuk pelajaran ini.', 'No vocabulary for this lesson yet.') ?>
            </div>
        <?php endif; ?>

        <div class="rv-section-head">
            <div>
                <h3>
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="var(--accent-purple)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                    <?= t('Kuis Materi', 'Material Quiz') ?>
                </h3>
                <p><?= t('Uji pemahaman kosakata bab ini langsung di halaman materi.', 'Test your understanding of this chapter vocabulary right on the material page.') ?></p>
            </div>
        </div>

        <section class="quiz-card rv-rise" id="mk-quiz-card">
            <div class="quiz-top">
                <div class="quiz-icon">
                    <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>
                </div>
                <div>
                    <h3><?= t('Uji Pemahaman Kosakata', 'Vocabulary Comprehension Check') ?></h3>
                    <p><?= t('Pilih arti yang benar dari setiap kata Rusia di bab ini.', 'Choose the correct meaning of each Russian word in this chapter.') ?></p>
                </div>
            </div>

            <div id="mk-start-panel">
                <?php if ($jumlah_kata >= 2): ?>
                    <p class="quiz-hint">
                        <strong><?= $jumlah_kata ?> <?= t('soal', 'questions') ?></strong> · <?= t('Setiap soal berisi satu kata Rusia dan empat pilihan arti. Nilai muncul setelah semua soal dijawab. Kuis ini untuk latihan; nilai resmi tetap dicatat di Kuis Kosa Kata.', 'Each question shows one Russian word and four meaning options. Your score appears after all questions are answered. This quiz is for practice; the official score is still recorded in the Vocabulary Quiz.') ?>
                    </p>
                    <button type="button" class="quiz-start" id="mk-start-btn">
                        <svg width="17" height="17" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        <?= t('Mulai Kuis Materi', 'Start Material Quiz') ?>
                    </button>
                <?php else: ?>
                    <p class="quiz-hint"><?= t('Kuis materi butuh minimal 2 kosakata. Tambahkan kosakata terlebih dahulu.', 'The material quiz needs at least 2 words. Add vocabulary first.') ?></p>
                <?php endif; ?>
            </div>

            <div id="mk-quiz">
                <div class="mk-progress-row">
                    <span><?= t('Soal', 'Question') ?> <b id="mk-counter">1 / 1</b></span>
                    <span><?= t('Benar', 'Correct') ?>: <b id="mk-correct">0</b></span>
                </div>
                <div class="mk-progress-track"><div class="mk-progress-fill" id="mk-progress"></div></div>

                <div class="mk-word" id="mk-word">...</div>
                <div class="mk-read" id="mk-read"></div>
                <div class="mk-audio-row">
                    <button type="button" class="rv-audio" id="mk-audio" title="<?= t('Dengarkan pelafalan', 'Listen to pronunciation') ?>">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>
                    </button>
                </div>
                <div class="mk-options" id="mk-options"></div>
                <button type="button" class="mk-next" id="mk-next"><?= t('Soal Berikutnya', 'Next Question') ?> →</button>
            </div>

            <div class="mk-result" id="mk-result">
                <div class="mk-score" id="mk-score">0</div>
                <h4 id="mk-result-title"><?= t('Kuis Selesai!', 'Quiz Complete!') ?></h4>
                <p id="mk-result-desc"></p>
                <div class="mk-result-actions">
                    <button type="button" class="mk-btn-ghost" id="mk-retry"><?= t('Ulangi Kuis', 'Retry Quiz') ?></button>
                    <a href="mode_kuis.php?id=<?= $id_pelajaran ?>" class="rv-btn rv-btn-navy"><?= t('Kuis Resmi (Dinilai)', 'Official Quiz (Graded)') ?> →</a>
                </div>
            </div>

            <div class="quiz-cta">
                <p><?= t('Ingin nilaimu dicatat dan membuka bab berikutnya? Kerjakan Kuis Kosa Kata resmi.', 'Want your score recorded and the next chapter unlocked? Take the official Vocabulary Quiz.') ?></p>
                <a href="mode_kuis.php?id=<?= $id_pelajaran ?>">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    <?= t('Buka Kuis Kosa Kata', 'Open Vocabulary Quiz') ?>
                </a>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    const WORDS = <?= $json_kosakata ?>;

    const startPanel  = document.getElementById('mk-start-panel');
    const startBtn    = document.getElementById('mk-start-btn');
    const quizWrap    = document.getElementById('mk-quiz');
    const resultWrap  = document.getElementById('mk-result');
    const wordEl      = document.getElementById('mk-word');
    const readEl      = document.getElementById('mk-read');
    const optionsEl   = document.getElementById('mk-options');
    const nextBtn     = document.getElementById('mk-next');
    const counterEl   = document.getElementById('mk-counter');
    const correctEl   = document.getElementById('mk-correct');
    const progressEl  = document.getElementById('mk-progress');
    const audioBtn    = document.getElementById('mk-audio');
    const scoreEl     = document.getElementById('mk-score');
    const resultTitle = document.getElementById('mk-result-title');
    const resultDesc  = document.getElementById('mk-result-desc');
    const retryBtn    = document.getElementById('mk-retry');

    const TXT = {
        question: <?= json_encode(t('Soal', 'Question'), JSON_UNESCAPED_UNICODE) ?>,
        perfect:  <?= json_encode(t('Luar biasa! Semua jawaban benar.', 'Excellent! All answers are correct.'), JSON_UNESCAPED_UNICODE) ?>,
        good:     <?= json_encode(t('Bagus! Sedikit lagi menuju sempurna.', 'Great! Just a little more to perfection.'), JSON_UNESCAPED_UNICODE) ?>,
        retry:    <?= json_encode(t('Terus berlatih dan baca ulang materinya, ya.', 'Keep practicing and reread the material.'), JSON_UNESCAPED_UNICODE) ?>,
        summary:  <?= json_encode(t('Kamu menjawab benar', 'You answered correctly'), JSON_UNESCAPED_UNICODE) ?>
    };

    let questions = [];
    let index = 0;
    let benar = 0;

    function shuffle(arr) {
        const a = arr.slice();
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    }

    function buildQuestions() {
        const daftarArti = [...new Set(WORDS.map(w => w.arti_indonesia))];
        return shuffle(WORDS).map(w => {
            const pengecoh = shuffle(daftarArti.filter(a => a !== w.arti_indonesia)).slice(0, 3);
            return {
                kata: w.kata_rusia,
                baca: w.cara_baca || '',
                benar: w.arti_indonesia,
                pilihan: shuffle([w.arti_indonesia, ...pengecoh])
            };
        });
    }

    function mulai() {
        questions = buildQuestions();
        index = 0;
        benar = 0;
        startPanel.style.display = 'none';
        resultWrap.style.display = 'none';
        quizWrap.style.display = 'block';
        correctEl.textContent = '0';
        tampilkanSoal();
    }

    function tampilkanSoal() {
        const q = questions[index];
        optionsEl.innerHTML = '';
        nextBtn.style.display = 'none';

        wordEl.textContent = q.kata;
        readEl.textContent = q.baca ? '[ ' + q.baca + ' ]' : '';
        counterEl.textContent = (index + 1) + ' / ' + questions.length;
        progressEl.style.width = ((index / questions.length) * 100) + '%';
        audioBtn.dataset.ucap = q.kata;

        q.pilihan.forEach(pilihan => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mk-option';
            btn.textContent = pilihan;
            btn.addEventListener('click', () => jawab(btn, pilihan, q.benar));
            optionsEl.appendChild(btn);
        });
    }

    function jawab(btn, pilihan, benarJawaban) {
        const semua = optionsEl.querySelectorAll('.mk-option');
        semua.forEach(b => { b.disabled = true; });

        if (pilihan === benarJawaban) {
            btn.classList.add('correct');
            benar++;
            correctEl.textContent = String(benar);
            if (window.RusvocaSound) RusvocaSound.benar();
        } else {
            btn.classList.add('wrong');
            if (window.RusvocaSound) RusvocaSound.salah();
            semua.forEach(b => {
                if (b.textContent === benarJawaban) b.classList.add('correct');
            });
        }

        nextBtn.textContent = (index === questions.length - 1)
            ? <?= json_encode(t('Lihat Hasil →', 'See Result →'), JSON_UNESCAPED_UNICODE) ?>
            : <?= json_encode(t('Soal Berikutnya →', 'Next Question →'), JSON_UNESCAPED_UNICODE) ?>;
        nextBtn.style.display = 'block';
    }

    function lanjut() {
        index++;
        if (index < questions.length) {
            tampilkanSoal();
        } else {
            selesai();
        }
    }

    function selesai() {
        quizWrap.style.display = 'none';
        resultWrap.style.display = 'block';
        progressEl.style.width = '100%';

        const nilai = Math.round((benar / questions.length) * 100);
        scoreEl.textContent = nilai;
        resultDesc.textContent = TXT.summary + ' ' + benar + ' / ' + questions.length + ' (' + nilai + '%).';

        if (nilai === 100) {
            resultTitle.textContent = TXT.perfect;
            scoreEl.style.background = '#10B981';
        } else if (nilai >= 70) {
            resultTitle.textContent = TXT.good;
            scoreEl.style.background = '#6D28D9';
        } else {
            resultTitle.textContent = TXT.retry;
            scoreEl.style.background = '#EF4444';
        }

        if (window.RusvocaSound) RusvocaSound.menang();
    }

    if (startBtn) {
        startBtn.addEventListener('click', function () {
            if (window.RusvocaSound) RusvocaSound.klik();
            mulai();
        });
    }
    if (nextBtn) { nextBtn.addEventListener('click', lanjut); }
    if (retryBtn) {
        retryBtn.addEventListener('click', function () {
            resultWrap.style.display = 'none';
            mulai();
        });
    }
    if (audioBtn) {
        audioBtn.addEventListener('click', function () {
            const teks = audioBtn.dataset.ucap || '';
            if (teks && typeof ucapkanTeks === 'function') {
                ucapkanTeks(teks, { btn: audioBtn, rate: 0.85 });
            }
        });
    }
})();
</script>

<script src="assets/js/audio.js"></script>
<script src="assets/js/sound.js"></script>
</body>
</html>
