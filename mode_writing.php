<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/progres.php';
require_once 'config/riwayat_kuis.php';
require_once 'config/bahasa.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$nama_user = $_SESSION['nama'] ?? 'Siswa';
$id_pelajaran = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id_pelajaran']) ? (int)$_POST['id_pelajaran'] : 0);

// Kode mode + penanda waktu mulai (untuk pencatatan target harian)
$mode_kode = 'mode_writing';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { tandai_mulai_belajar($id_pelajaran, $mode_kode); }

// ==============================================================================
// LOGIKA SIMPAN SKOR (Hanya menyimpan nilai tertinggi)
// ==============================================================================
if (isset($_POST['simpan_skor']) && csrf_valid()) {
    $skor_baru = max(0, min(100, (int)$_POST['skor']));
    $mode_belajar = 'mode_writing';

    try {
        if ($id_pelajaran > 0) {
            $cek_riwayat = mysqli_query($koneksi, "SELECT id_progres, skor FROM tb_progres_belajar WHERE user_id = '$user_id' AND id_pelajaran = '$id_pelajaran' AND mode_belajar = '$mode_belajar'");

            if ($cek_riwayat && mysqli_num_rows($cek_riwayat) > 0) {
                $data_lama = mysqli_fetch_assoc($cek_riwayat);
                $id_progres = $data_lama['id_progres'];
                if ($skor_baru > $data_lama['skor']) {
                    mysqli_query($koneksi, "UPDATE tb_progres_belajar SET skor = '$skor_baru', status_selesai = 1, tanggal_akses = NOW() WHERE id_progres = '$id_progres'");
                } else {
                    mysqli_query($koneksi, "UPDATE tb_progres_belajar SET status_selesai = 1, tanggal_akses = NOW() WHERE id_progres = '$id_progres'");
                }
            } else {
                mysqli_query($koneksi, "INSERT INTO tb_progres_belajar (user_id, id_pelajaran, mode_belajar, skor, status_selesai, tanggal_akses) VALUES ('$user_id', '$id_pelajaran', '$mode_belajar', '$skor_baru', 1, NOW())");
            }
        }
    } catch (mysqli_sql_exception $e) {
        // Abaikan kegagalan simpan skor agar tetap diarahkan, bukan halaman error
    }

    // Catat durasi belajar untuk target harian
    if ($id_pelajaran > 0) { catat_durasi_belajar($koneksi, $user_id, $id_pelajaran, $mode_kode); }

    // Simpan rincian jawaban untuk halaman Riwayat
    if ($id_pelajaran > 0) {
        $detail = json_decode((string)($_POST['detail_jawaban'] ?? ''), true);
        if (is_array($detail)) {
            simpan_riwayat_kuis($koneksi, $user_id, $id_pelajaran, $mode_belajar, $skor_baru, $detail);
        }
    }

    if ($id_pelajaran > 0) {
        header("Location: detail_pelajaran.php?id=$id_pelajaran");
    } else {
        header("Location: kosakata.php");
    }
    exit();
}
// ==============================================================================

// Ambil data pelajaran & Kosakata
$query_pelajaran = mysqli_query($koneksi, "SELECT * FROM tb_pelajaran WHERE id_pelajaran = $id_pelajaran");
if (!$query_pelajaran || mysqli_num_rows($query_pelajaran) == 0) {
    header("Location: kosakata.php");
    exit();
}
$data_pelajaran = mysqli_fetch_assoc($query_pelajaran);

$query_kosakata = mysqli_query($koneksi, "SELECT * FROM tb_kosakata WHERE id_pelajaran = $id_pelajaran");
$kosakata_array = [];
if ($query_kosakata) {
    while ($row = mysqli_fetch_assoc($query_kosakata)) {
        $kosakata_array[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Latihan Writing', 'Writing Practice') ?> - <?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    
    <style>
        :root { --primary-blue: #0F3F7A; --bg-light: #F4F7FA; --border-color: #E2E8F0; --text-dark: #0F172A; --text-muted: #64748B; --card-bg: #FFFFFF; --accent-blue: #2563EB; --accent-green: #10B981; --accent-red: #EF4444; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .app-layout { display: flex; min-height: 100vh; }

        /* SIDEBAR (SINKRON) */
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

        /* KONTEN GAME */
        .main-content { margin-left: 260px; flex: 1; padding: 40px; max-width: 980px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 700; margin-bottom: 24px; transition: 0.2s; }
        .btn-back:hover { color: var(--primary-blue); transform: translateX(-5px); }

        .game-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; }
        .game-title h2 { font-size: 25px; font-weight: 800; color: var(--text-dark); }
        .game-title p { font-size: 14px; color: var(--text-muted); margin-top: 4px; }
        .progress-box { font-weight: 800; color: var(--primary-blue); background: #E0E7FF; padding: 9px 16px; border-radius: 12px; font-size: 14px; white-space: nowrap; }

        /* KARTU WRITING */
        .writing-card { background: #fff; border: 1px solid var(--border-color); border-radius: 22px; padding: 30px; text-align: center; box-shadow: 0 14px 34px rgba(15, 63, 122, .07); }
        .question-meta { display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .word-row { display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 6px; flex-wrap: wrap; }
        .word-arti { font-size: 29px; font-weight: 800; color: var(--text-dark); line-height: 1.2; }
        .word-baca { font-size: 14px; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; }
        .word-baca b { color: var(--accent-blue); }
        .word-note { font-size: 12px; color: var(--text-muted); margin-bottom: 20px; font-weight: 600; }

        .input-wrapper { display: flex; flex-direction: column; align-items: center; gap: 15px; margin-bottom: 20px; }

        /* Papan susun huruf */
        .susunan-box { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; align-items: center; min-height: 82px; margin-bottom: 12px; padding: 14px; background: var(--bg-light); border: 2px dashed var(--border-color); border-radius: 14px; transition: .2s; }
        .susunan-box.kosong::after { content: attr(data-kosong); font-size: 13px; color: #94A3B8; font-weight: 600; }
        .susunan-box.jawaban-benar { border-color: var(--accent-green); border-style: solid; background: #ECFDF5; }
        .susunan-box.jawaban-salah { border-color: var(--accent-red); border-style: solid; background: #FEF2F2; animation: rv-shake .4s ease; }
        @keyframes rv-shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }

        .huruf-pilih { min-width: 44px; height: 52px; padding: 0 12px; font-size: 22px; font-weight: 800; color: var(--primary-blue); background: #fff; border: 2px solid var(--border-color); border-bottom-width: 4px; border-radius: 10px; cursor: pointer; transition: 0.15s; }
        .huruf-pilih:hover { border-color: var(--accent-red); color: var(--accent-red); }
        .bank-huruf { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 18px; }
        .huruf-bank { min-width: 44px; height: 52px; padding: 0 12px; font-size: 22px; font-weight: 800; color: var(--text-dark); background: #fff; border: 2px solid var(--border-color); border-bottom-width: 4px; border-radius: 10px; cursor: pointer; transition: 0.15s; }
        .huruf-bank:hover { background: #EFF6FF; border-color: var(--accent-blue); transform: translateY(-2px); }
        .huruf-bank:disabled { opacity: 0.25; cursor: default; transform: none; }

        .koreksi-text { font-size: 14px; font-weight: 700; color: var(--accent-red); min-height: 22px; margin-bottom: 10px; line-height: 1.5; }
        .koreksi-text.ok { color: var(--accent-green); }

        .toolbar { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; margin-bottom: 16px; }
        .btn-kecil { display: inline-flex; align-items: center; gap: 6px; background: #F8FAFC; border: 1px solid var(--border-color); border-radius: 9px; color: var(--text-dark); font-weight: 700; cursor: pointer; font-size: 12.5px; padding: 8px 12px; transition: .15s; }
        .btn-kecil:hover { background: #EFF6FF; border-color: #BFDBFE; color: var(--accent-blue); }

        /* Panel bantuan */
        .help-panel { background: #F8FAFC; border: 1px solid var(--border-color); border-radius: 14px; padding: 16px; margin-bottom: 16px; text-align: left; }
        .help-panel summary { cursor: pointer; font-weight: 800; font-size: 13px; color: var(--primary-blue); display: flex; align-items: center; gap: 8px; list-style: none; }
        .help-panel summary::-webkit-details-marker { display: none; }
        .help-panel summary::before { content: "＋"; font-weight: 800; }
        .help-panel[open] summary::before { content: "－"; }
        .help-body { margin-top: 12px; }
        .help-caption { font-size: 11.5px; color: var(--text-muted); font-weight: 600; margin-bottom: 8px; line-height: 1.5; }
        .huruf-guide { display: flex; flex-wrap: wrap; gap: 8px; }
        .guide-item { display: flex; flex-direction: column; align-items: center; gap: 2px; min-width: 56px; padding: 8px 6px; border-radius: 10px; background: #fff; border: 1px solid var(--border-color); cursor: pointer; transition: .15s; }
        .guide-item:hover { border-color: var(--accent-blue); background: #EFF6FF; transform: translateY(-2px); }
        .guide-item b { font-size: 20px; color: var(--primary-blue); line-height: 1; }
        .guide-item span { font-size: 10px; color: var(--text-muted); font-weight: 700; }
        .help-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .btn-hint { background: #FFFBEB; border: 1px solid #FDE68A; color: #B45309; font-weight: 800; cursor: pointer; font-size: 12.5px; padding: 8px 12px; border-radius: 9px; transition: .15s; }
        .btn-hint:hover { background: #FEF3C7; }
        .btn-reveal { background: #FEF2F2; border: 1px solid #FECACA; color: #B91C1C; }
        .btn-reveal:hover { background: #FEE2E2; }

        .btn-action { padding: 14px 40px; background: var(--primary-blue); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 800; cursor: pointer; transition: 0.2s; width: 100%; max-width: 320px; }
        .btn-action:hover { background: #0a2d59; transform: translateY(-1px); }
        .btn-next { background: var(--accent-green); display: none; margin: 0 auto; }
        .btn-next:hover { background: #059669; }

        #end-screen { display: none; text-align: center; }
        .skor-akhir { font-size: 64px; font-weight: 800; color: var(--primary-blue); margin: 16px 0; line-height: 1; }
        .end-emoji { font-size: 48px; }

        @media (max-width: 768px) {
            .sidebar { width: 72px; padding: 16px 8px; }
            .brand-text, .user-info { display: none; }
            .brand-logo-img { width: 32px; height: 32px; }
            .brand-header { padding-bottom: 12px; margin-bottom: 12px; justify-content: center; padding-left: 0; }
            .user-profile-bottom { justify-content: center; padding: 8px; flex-direction: column; gap: 15px; }
            .nav-item a { justify-content: center; padding: 12px; }
            .nav-item span { display: none; }
            .main-content { margin-left: 72px; padding: 24px; }
            .writing-card { padding: 22px 16px; }
            .word-arti { font-size: 24px; }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div>
            <div class="brand-header"><img src="assets/img/logo.png" alt="Logo RusVoca" class="brand-logo-img"><div class="brand-text"><div class="brand-name">Rus<span>Voca</span></div><div class="brand-sub"><?= t('APLIKASI KOSAKATA BAHASA RUSIA', 'RUSSIAN VOCABULARY APP') ?></div></div></div>
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
            <div class="user-info"><div class="user-info-name"><?= htmlspecialchars($nama_user); ?></div><div class="user-info-role"><?= t('Siswa', 'Student') ?></div></div>
            <form action="logout.php" method="POST" style="display: flex;" onsubmit="return confirm('<?= t('Yakin ingin keluar?', 'Are you sure you want to log out?') ?>');"><?= csrf_field() ?><button type="submit" class="logout-btn"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></button></form>
        </div>
    </aside>

    <main class="main-content">
        <a href="detail_pelajaran.php?id=<?= $id_pelajaran ?>" class="btn-back">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            <?= t('Kembali', 'Back') ?>
        </a>

        <div class="game-header">
            <div class="game-title">
                <h2><?= t('Latihan Writing', 'Writing Practice') ?></h2>
                <p><?= t('Susun huruf Kiril. Ada audio, transliterasi, dan panduan huruf supaya lebih mudah.', 'Arrange the Cyrillic letters. Audio, transliteration, and a letter guide make it easier.') ?></p>
            </div>
            <div class="progress-box"><span id="current-q">1</span> / <span id="total-q"><?= count($kosakata_array) ?></span></div>
        </div>

        <div class="writing-card" id="game-screen">
            <div class="question-meta">
                <span class="rv-chip is-gold" id="chip-huruf">0 <?= t('huruf', 'letters') ?></span>
                <span class="rv-chip" id="chip-bantuan"><?= t('Bantuan aktif', 'Hints on') ?></span>
            </div>

            <div class="word-row">
                <div class="word-arti" id="teks-arti">...</div>
                <button type="button" class="rv-audio" id="btn-dengar-kata" title="<?= t('Dengarkan kata', 'Listen to the word') ?>" aria-label="<?= t('Dengarkan kata bahasa Rusia', 'Listen to the Russian word') ?>">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                    </svg>
                </button>
            </div>
            <div class="word-baca" id="teks-baca"><?= t('Cara baca', 'Read as') ?>: <b>...</b></div>
            <div class="word-note" id="teks-catatan"></div>

            <div class="susunan-box kosong" id="susunan-jawaban" data-kosong="<?= t('Ketuk huruf di bawah untuk menyusun jawaban...', 'Tap the letters below to build the answer...') ?>"></div>
            <div class="bank-huruf" id="bank-huruf"></div>
            <div class="koreksi-text" id="teks-koreksi"></div>

            <div class="toolbar">
                <button class="btn-kecil" id="btn-hapus" onclick="hapusHuruf()"><?= t('Hapus', 'Delete') ?></button>
                <button class="btn-kecil" id="btn-ulangi" onclick="ulangiJawaban()"><?= t('Ulangi', 'Reset') ?></button>
                <button class="btn-kecil" id="btn-dengar-lagi" onclick="dengarKata()"><?= t('Dengar kata', 'Hear word') ?></button>
            </div>

            <details class="help-panel" id="help-panel">
                <summary><?= t('Panduan huruf & bantuan', 'Letter guide & hints') ?></summary>
                <div class="help-body">
                    <div class="help-caption"><?= t('Ketuk huruf untuk mendengar bunyinya. Panduan ini hanya menampilkan huruf yang dipakai pada jawaban.', 'Tap a letter to hear its sound. This guide only shows letters used in the answer.') ?></div>
                    <div class="huruf-guide" id="panduan-huruf"></div>
                    <div class="help-actions">
                        <button type="button" class="btn-kecil btn-hint" id="btn-huruf-pertama" onclick="tampilkanHurufPertama()"><?= t('Tampilkan huruf pertama', 'Show the first letter') ?></button>
                        <button type="button" class="btn-kecil btn-hint btn-reveal" id="btn-lihat-jawaban" onclick="lihatJawaban()"><?= t('Lihat jawaban (0 poin)', 'Show answer (0 points)') ?></button>
                    </div>
                </div>
            </details>

            <button class="btn-action" id="btn-cek" onclick="checkAnswer()"><?= t('Cek Jawaban', 'Check Answer') ?></button>
            <button class="btn-action btn-next" id="btn-next" onclick="nextQuestion()"><?= t('Lanjut', 'Next') ?></button>
        </div>

        <div class="writing-card" id="end-screen">
            <h2 style="margin-top: 8px;"><?= t('Latihan Menulis Selesai!', 'Writing Practice Complete!') ?></h2>
            <div class="skor-akhir" id="skor-display">0</div>
            <p style="color: var(--text-muted); margin-bottom: 30px;"><?= t('Skor tertinggimu akan otomatis disimpan ke database.', 'Your best score will be saved automatically.') ?></p>
            
            <form method="POST" action="?id=<?= $id_pelajaran ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="skor" id="input-skor">
                <input type="hidden" name="detail_jawaban" id="input-detail" value="">
                <input type="hidden" name="id_pelajaran" value="<?= $id_pelajaran ?>">
                <button type="submit" name="simpan_skor" class="btn-action" style="background: var(--accent-green);"><?= t('Simpan Skor & Kembali', 'Save Score & Return') ?></button>
            </form>
        </div>
    </main>
</div>

<script src="assets/js/audio.js"></script>
<script>
    const words = <?= json_encode($kosakata_array) ?>;
    const TXT = {
        readAs: <?= json_encode(t('Cara baca', 'Read as'), JSON_UNESCAPED_UNICODE) ?>,
        letters: <?= json_encode(t('huruf', 'letters'), JSON_UNESCAPED_UNICODE) ?>,
        notePunct: <?= json_encode(t('Spasi dan tanda baca sudah disiapkan otomatis — kamu cukup menyusun hurufnya.', 'Spaces and punctuation are added automatically — just arrange the letters.'), JSON_UNESCAPED_UNICODE) ?>,
        noteNormal: <?= json_encode(t('Susun huruf sesuai urutan. Kamu punya 2 kesempatan sebelum jawaban ditampilkan.', 'Arrange the letters in order. You have 2 attempts before the answer is shown.'), JSON_UNESCAPED_UNICODE) ?>,
        remove: <?= json_encode(t('Ketuk untuk melepas huruf ini', 'Tap to remove this letter'), JSON_UNESCAPED_UNICODE) ?>,
        guide: <?= json_encode(t('Dengarkan bunyi', 'Hear the sound of'), JSON_UNESCAPED_UNICODE) ?>,
        audioError: <?= json_encode(t('Maaf, browser kamu belum mendukung pemutar suara otomatis.', 'Sorry, your browser does not support automatic audio playback.'), JSON_UNESCAPED_UNICODE) ?>,
        hint: <?= json_encode(t('Petunjuk: huruf pertama adalah', 'Hint: the first letter is'), JSON_UNESCAPED_UNICODE) ?>,
        answerShown: <?= json_encode(t('Jawaban:', 'Answer:'), JSON_UNESCAPED_UNICODE) ?>,
        noPoints: <?= json_encode(t('tidak ada poin untuk soal ini. Pelajari lalu lanjut ya!', 'no points for this question. Study it and move on.'), JSON_UNESCAPED_UNICODE) ?>,
        allLetters: <?= json_encode(t('Susun semua huruf dulu ya!', 'Arrange all the letters first!'), JSON_UNESCAPED_UNICODE) ?>,
        correct: <?= json_encode(t('Benar!', 'Correct!'), JSON_UNESCAPED_UNICODE) ?>,
        points: <?= json_encode(t('poin', 'points'), JSON_UNESCAPED_UNICODE) ?>,
        second: <?= json_encode(t('percobaan kedua', 'second attempt'), JSON_UNESCAPED_UNICODE) ?>,
        tryAgain: <?= json_encode(t('Belum tepat. Coba sekali lagi! Huruf pertama:', 'Not quite. Try once more! First letter:'), JSON_UNESCAPED_UNICODE) ?>,
        rightAnswer: <?= json_encode(t('Jawaban yang benar:', 'The correct answer is:'), JSON_UNESCAPED_UNICODE) ?>,
        noPointsShort: <?= json_encode(t('Tidak ada poin untuk soal ini.', 'No points for this question.'), JSON_UNESCAPED_UNICODE) ?>,
        notAnswered: <?= json_encode(t('(tidak dijawab)', '(not answered)'), JSON_UNESCAPED_UNICODE) ?>,
        insufficient: <?= json_encode(t('Kosakata belum cukup untuk latihan ini.', 'There is not enough vocabulary for this practice.'), JSON_UNESCAPED_UNICODE) ?>
    };
    let currentIndex = 0;
    let skor = 0;
    const poinPerSoal = words.length > 0 ? 100 / words.length : 0;
    let percobaan = 0;
    let jawabanDibuka = false;
    let detailJawaban = [];
    let sudahDicatat = false;

    const elArti = document.getElementById('teks-arti');
    const elBaca = document.getElementById('teks-baca');
    const elCatatan = document.getElementById('teks-catatan');
    const elSusunan = document.getElementById('susunan-jawaban');
    const elBank = document.getElementById('bank-huruf');
    const elKoreksi = document.getElementById('teks-koreksi');
    const elPanduan = document.getElementById('panduan-huruf');
    const btnCek = document.getElementById('btn-cek');
    const btnNext = document.getElementById('btn-next');
    const btnDengar = document.getElementById('btn-dengar-kata');
    const chipHuruf = document.getElementById('chip-huruf');
    const btnHurufPertama = document.getElementById('btn-huruf-pertama');
    const btnLihatJawaban = document.getElementById('btn-lihat-jawaban');

    const HURUF_PENGECOH = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя'.split('');

    // Perkiraan bunyi huruf untuk panduan pemula.
    const BUNYI_HURUF = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'ye', 'ё': 'yo',
        'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
        'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
        'ф': 'f', 'х': 'kh', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'shch',
        'ъ': 'tanda keras', 'ы': 'y berat', 'ь': 'tanda lembut', 'э': 'e',
        'ю': 'yu', 'я': 'ya'
    };

    let bankHuruf = []; // {huruf, terpakai}
    let tersusun = [];  // {huruf, bankIndex}
    let terkunci = false;

    function acak(arr) {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr;
    }

    // Hanya huruf Kiril yang perlu disusun; spasi & tanda baca sudah disiapkan.
    // Huruf besar/kecil dipertahankan agar latihan menulis sesuai kata aslinya.
    function jawabanBenar() {
        return String(words[currentIndex].kata_rusia || '').replace(/[^а-яёА-ЯЁ]/g, '');
    }

    function sisaKarakter() {
        return String(words[currentIndex].kata_rusia || '').replace(/[а-яё]/gi, '');
    }

    function loadQuestion() {
        const kata = words[currentIndex];
        elArti.innerText = kata.arti_indonesia;
        elBaca.innerHTML = TXT.readAs + ': <b>' + (kata.cara_baca || '-') + '</b>';
        elKoreksi.innerText = '';
        elKoreksi.classList.remove('ok');
        elSusunan.classList.remove('jawaban-benar', 'jawaban-salah');
        tersusun = [];
        terkunci = false;
        percobaan = 0;
        jawabanDibuka = false;
        sudahDicatat = false;

        const ans = jawabanBenar();
        chipHuruf.innerText = ans.length + ' ' + TXT.letters;

        // Catatan bila kata asli punya spasi / tanda baca.
        const sisa = sisaKarakter();
        if (sisa !== '') {
            elCatatan.innerText = TXT.notePunct;
        } else {
            elCatatan.innerText = TXT.noteNormal;
        }

        // Bank huruf = huruf jawaban + maksimal 3 huruf pengecoh, lalu diacak
        bankHuruf = ans.split('').map(h => ({ huruf: h, terpakai: false }));
        const unik = [...new Set(ans.split(''))];
        const calon = HURUF_PENGECOH.filter(h => !unik.includes(h));
        acak(calon);
        calon.slice(0, Math.min(3, calon.length)).forEach(h => bankHuruf.push({ huruf: h, terpakai: false }));
        acak(bankHuruf);

        renderSusunan();
        renderBank();
        renderPanduan(unik);

        document.getElementById('current-q').innerText = currentIndex + 1;

        btnCek.style.display = 'inline-block';
        btnNext.style.display = 'none';
        btnHurufPertama.style.display = 'inline-block';
        btnLihatJawaban.style.display = 'inline-block';
        document.getElementById('help-panel').open = false;
    }

    function renderSusunan() {
        elSusunan.innerHTML = '';
        elSusunan.classList.toggle('kosong', tersusun.length === 0);
        tersusun.forEach((t, i) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'huruf-pilih';
            b.textContent = t.huruf;
            b.title = TXT.remove;
            b.onclick = () => lepasHuruf(i);
            elSusunan.appendChild(b);
        });
    }

    function renderBank() {
        elBank.innerHTML = '';
        bankHuruf.forEach((t, i) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'huruf-bank';
            b.textContent = t.huruf;
            b.disabled = t.terpakai || terkunci;
            b.onclick = () => pilihHuruf(i);
            elBank.appendChild(b);
        });
    }

    function renderPanduan(hurufUnik) {
        elPanduan.innerHTML = '';
        hurufUnik.forEach(h => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'guide-item';
            item.innerHTML = '<b>' + h + '</b><span>' + (BUNYI_HURUF[h.toLowerCase()] || '—') + '</span>';
            item.title = TXT.guide + ' ' + h;
            item.onclick = () => {
                if (window.RusvocaSound) RusvocaSound.klik();
                ucapkanTeks(h, { rate: 0.7 });
            };
            elPanduan.appendChild(item);
        });
    }

    function pilihHuruf(i) {
        if (terkunci || bankHuruf[i].terpakai) return;
        bankHuruf[i].terpakai = true;
        tersusun.push({ huruf: bankHuruf[i].huruf, bankIndex: i });
        if (window.RusvocaSound) RusvocaSound.klik();
        renderSusunan();
        renderBank();
        elKoreksi.innerText = '';
    }

    function lepasHuruf(i) {
        if (terkunci) return;
        const t = tersusun[i];
        bankHuruf[t.bankIndex].terpakai = false;
        tersusun.splice(i, 1);
        renderSusunan();
        renderBank();
    }

    function hapusHuruf() {
        if (terkunci || tersusun.length === 0) return;
        lepasHuruf(tersusun.length - 1);
    }

    function ulangiJawaban() {
        if (terkunci) return;
        tersusun.forEach(t => { bankHuruf[t.bankIndex].terpakai = false; });
        tersusun = [];
        renderSusunan();
        renderBank();
        elKoreksi.innerText = '';
    }

    function dengarKata() {
        const kata = words[currentIndex];
        if (!kata) return;
        if (window.RusvocaSound) RusvocaSound.klik();
        if (!ucapkanTeks(kata.kata_rusia, { btn: btnDengar, rate: 0.8 })) {
            alert(TXT.audioError);
        }
    }

    btnDengar.addEventListener('click', dengarKata);

    function tampilkanHurufPertama() {
        if (terkunci) return;
        const ans = jawabanBenar();
        const hurufPertama = ans.charAt(0);
        elKoreksi.classList.remove('ok');
        elKoreksi.style.color = 'var(--accent-blue)';
        elKoreksi.innerText = TXT.hint + ' "' + hurufPertama + '" (' + (BUNYI_HURUF[hurufPertama.toLowerCase()] || '?') + ').';
        if (window.RusvocaSound) RusvocaSound.klik();
        ucapkanTeks(hurufPertama, { rate: 0.7 });
    }

    function catatJawaban(benar) {
        if (sudahDicatat) return;
        sudahDicatat = true;
        const kata = words[currentIndex];
        detailJawaban.push({
            pertanyaan: kata.arti_indonesia,
            jawaban_user: tersusun.map(t => t.huruf).join('') || TXT.notAnswered,
            jawaban_benar: kata.kata_rusia,
            benar: !!benar,
            tipe: 'writing'
        });
    }

    function lihatJawaban() {
        if (terkunci) return;
        const jawab = words[currentIndex].kata_rusia;
        jawabanDibuka = true;
        catatJawaban(false);
        kunciJawaban(false);
        elKoreksi.classList.remove('ok');
        elKoreksi.style.color = 'var(--accent-red)';
        elKoreksi.innerText = TXT.answerShown + ' ' + jawab + ' — ' + TXT.noPoints;
        btnHurufPertama.style.display = 'none';
        btnLihatJawaban.style.display = 'none';
        if (window.RusvocaSound) RusvocaSound.salah();
    }

    function kunciJawaban(benar) {
        terkunci = true;
        btnCek.style.display = 'none';
        btnNext.style.display = 'inline-block';
        btnHurufPertama.style.display = 'none';
        btnLihatJawaban.style.display = 'none';
        renderBank();
        if (benar) {
            elSusunan.classList.add('jawaban-benar');
            elSusunan.classList.remove('kosong', 'jawaban-salah');
        }
    }

    function checkAnswer() {
        if (terkunci) return;
        const benar = jawabanBenar();

        if (tersusun.length !== benar.length) {
            elKoreksi.classList.remove('ok');
            elKoreksi.style.color = 'var(--accent-red)';
            elKoreksi.innerText = TXT.allLetters + ' (' + tersusun.length + '/' + benar.length + ')';
            return;
        }

        const jawabanUser = tersusun.map(t => t.huruf).join('');
        elKoreksi.innerText = '';

        if (jawabanUser === benar) {
            // Percobaan pertama dapat poin penuh, percobaan kedua setengah.
            if (!jawabanDibuka) {
                skor += (percobaan === 0) ? poinPerSoal : (poinPerSoal / 2);
            }
            kunciJawaban(true);
            catatJawaban(true);
            elKoreksi.classList.add('ok');
            elKoreksi.style.color = 'var(--accent-green)';
            elKoreksi.innerText = percobaan === 0
                ? TXT.correct + ' +' + Math.round(poinPerSoal) + ' ' + TXT.points
                : TXT.correct + ' +' + Math.round(poinPerSoal / 2) + ' ' + TXT.points + ' (' + TXT.second + ')';
            if (window.RusvocaSound) RusvocaSound.benar();
            ucapkanTeks(words[currentIndex].kata_rusia, { rate: 0.85 });
            return;
        }

        // Jawaban belum tepat
        percobaan++;
        if (window.RusvocaSound) RusvocaSound.salah();
        elSusunan.classList.add('jawaban-salah');

        if (percobaan === 1) {
            elKoreksi.classList.remove('ok');
            elKoreksi.style.color = 'var(--accent-red)';
            const hurufPertama = benar.charAt(0);
            elKoreksi.innerText = TXT.tryAgain + ' "' + hurufPertama + '".';
            // Buka bantuan otomatis agar siswa bisa memeriksa huruf.
            document.getElementById('help-panel').open = true;
            setTimeout(() => elSusunan.classList.remove('jawaban-salah'), 500);
        } else {
            jawabanDibuka = true;
            kunciJawaban(false);
            catatJawaban(false);
            elKoreksi.classList.remove('ok');
            elKoreksi.style.color = 'var(--accent-red)';
            elKoreksi.innerText = TXT.rightAnswer + ' ' + words[currentIndex].kata_rusia + '. ' + TXT.noPointsShort;
        }
    }

    function nextQuestion() {
        currentIndex++;
        if (currentIndex < words.length) {
            loadQuestion();
        } else {
            showEndScreen();
        }
    }

    function showEndScreen() {
        document.getElementById('game-screen').style.display = 'none';
        document.getElementById('end-screen').style.display = 'block';

        let finalScore = Math.round(skor);
        document.getElementById('skor-display').innerText = finalScore;
        document.getElementById('input-skor').value = finalScore;
        document.getElementById('input-detail').value = JSON.stringify(detailJawaban);
        if (window.RusvocaSound) RusvocaSound.menang();
    }

    // Enter = cek jawaban / lanjut, memudahkan pengguna keyboard.
    // Abaikan bila fokus sedang di tombol/link agar tidak memicu aksi dua kali.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const aktif = document.activeElement;
        if (aktif && ['BUTTON', 'A', 'INPUT', 'SUMMARY'].includes(aktif.tagName)) return;
        if (!terkunci) { checkAnswer(); }
        else if (btnNext.style.display !== 'none') { nextQuestion(); }
    });

    if (words.length > 0) {
        loadQuestion();
    } else {
        document.getElementById('game-screen').innerHTML = '<p style="color: var(--text-muted); padding: 30px;">' + TXT.insufficient + '</p>';
    }
</script>
<script src="assets/js/sound.js"></script>
<script src="assets/js/quiz-guard.js?v=20261113"></script>
</body>
</html>
