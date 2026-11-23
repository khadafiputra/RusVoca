<?php
session_start();
require_once 'config/bahasa.php';
$sudah_login = isset($_SESSION['user_id']);
$nama_user = $_SESSION['nama'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('RusVoca - Belajar Kosakata Bahasa Rusia dengan Cara Seru', 'RusVoca - Learn Russian Vocabulary the Enjoyable Way') ?></title>
    <meta name="description" content="<?= t('RusVoca adalah aplikasi belajar kosakata bahasa Rusia interaktif: alfabet Kiril, kuis kata, kuis audio, speaking, dan writing. Gratis!', 'RusVoca is an interactive Russian vocabulary learning app: Cyrillic alphabet, word quiz, listening quiz, speaking, and writing. Free!') ?>">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    <style>
        :root {
            --primary-blue: #0F3F7A; --primary-hover: #0b2d5c; --gold: #E09B19;
            --bg-light: #F4F7FA; --border-color: #E2E8F0; --text-dark: #0F172A;
            --text-muted: #64748B; --card-bg: #FFFFFF;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        html { scroll-behavior: smooth; }
        body { background: var(--bg-light); color: var(--text-dark); }

        /* NAVBAR */
        .navbar { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,.92); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border-color); }
        .nav-inner { max-width: 1140px; margin: 0 auto; padding: 14px 24px; display: flex; align-items: center; gap: 16px; }
        .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .brand img { width: 40px; height: 40px; object-fit: contain; }
        .brand-name { font-size: 22px; font-weight: 800; color: var(--primary-blue); letter-spacing: -.5px; }
        .brand-name span { color: var(--gold); }
        .nav-links { display: flex; gap: 26px; margin-left: 32px; }
        .nav-links a { text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 700; transition: color .2s; }
        .nav-links a:hover { color: var(--primary-blue); }
        .nav-cta { margin-left: auto; display: flex; gap: 10px; align-items: center; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 22px; border-radius: 10px; font-size: 14px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; transition: all .2s; }
        .btn-primary { background: var(--primary-blue); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .btn-gold { background: var(--gold); color: #fff; }
        .btn-gold:hover { filter: brightness(.94); transform: translateY(-1px); }
        .btn-outline { background: #fff; color: var(--primary-blue); border: 1.5px solid var(--border-color); }
        .btn-outline:hover { border-color: var(--primary-blue); }
        .btn-lg { padding: 15px 30px; font-size: 15px; border-radius: 12px; }
        .hamburger { display: none; margin-left: auto; background: none; border: 1px solid var(--border-color); border-radius: 8px; padding: 8px; cursor: pointer; color: var(--primary-blue); }

        /* HERO */
        .hero { background: #0B2F5E; color: #fff; overflow: hidden; position: relative; border-bottom: 4px solid var(--gold); }
        .hero::after { content: none; }
        .hero::before { content: none; }
        .hero-inner { max-width: 1140px; margin: 0 auto; padding: 72px 24px 80px; display: grid; grid-template-columns: 1.1fr 1fr; gap: 48px; align-items: center; position: relative; z-index: 1; }
        .hero-badge { display: inline-flex; align-items: center; gap: 10px; background: none; border: 0; padding: 0; border-radius: 0; color: rgba(255,255,255,.72); font-size: 11px; font-weight: 800; letter-spacing: 1.6px; margin-bottom: 20px; }
        .hero-badge .dot { width: 24px; height: 2px; border-radius: 0; background: var(--gold); }
        .hero h1 { font-size: 44px; font-weight: 800; line-height: 1.16; letter-spacing: -.9px; margin-bottom: 16px; }
        .hero h1 .hl { color: #F3C969; }
        .hero p { font-size: 16px; line-height: 1.7; color: rgba(255,255,255,.78); margin-bottom: 30px; max-width: 520px; }
        .hero-cta { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 36px; }
        .hero-stats { display: flex; gap: 36px; }
        .stat b { display: block; font-size: 26px; font-weight: 800; }
        .stat span { font-size: 12px; opacity: .75; font-weight: 600; }
        .hero-visual { position: relative; display: flex; justify-content: center; }
        .hero-visual img { width: 100%; max-width: 420px; border-radius: 14px; border: 1px solid rgba(255,255,255,.18); box-shadow: 0 20px 46px rgba(0,0,0,.24); }
        .float-card { position: absolute; background: rgba(8,35,68,.9); color: #fff; border: 1px solid rgba(255,255,255,.18); border-radius: 10px; padding: 11px 14px; backdrop-filter: blur(8px); display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 800; }
        .float-card small { display: block; font-weight: 600; color: rgba(255,255,255,.62); font-size: 11px; }
        .float-1 { top: 12px; left: 0; }
        .float-2 { bottom: 16px; right: 0; }
        .float-ico { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; }

        /* SECTION */
        .section { max-width: 1140px; margin: 0 auto; padding: 72px 24px 8px; }
        .section-tag { display: inline-block; font-size: 12px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; color: var(--primary-blue); background: rgba(15,63,122,.08); padding: 6px 14px; border-radius: 20px; margin-bottom: 14px; }
        .section h2 { font-size: 32px; font-weight: 800; letter-spacing: -.5px; margin-bottom: 10px; }
        .section .lead { color: var(--text-muted); font-size: 15px; max-width: 640px; line-height: 1.7; margin-bottom: 36px; }

        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .feat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 26px; transition: all .25s; }
        .feat-card:hover { transform: translateY(-5px); box-shadow: 0 16px 32px rgba(15,63,122,.12); border-color: var(--primary-blue); }
        .feat-ico { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
        .feat-ico svg { width: 26px; height: 26px; }
        .feat-card h3 { font-size: 17px; font-weight: 800; margin-bottom: 8px; }
        .feat-card p { font-size: 13.5px; color: var(--text-muted); line-height: 1.65; }
        .c-blue { background: #EFF6FF; color: #1D4ED8; }
        .c-green { background: #ECFDF5; color: #047857; }
        .c-orange { background: #FFFBEB; color: #B45309; }
        .c-cyan { background: #ECFEFF; color: #0E7490; }
        .c-purple { background: #F5F3FF; color: #6D28D9; }
        .c-pink { background: #FDF2F8; color: #BE185D; }

        /* GRADE */
        .grade-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .grade-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 28px 20px; text-align: center; transition: all .25s; }
        .grade-card:hover { transform: translateY(-5px); box-shadow: 0 16px 32px rgba(0,0,0,.08); }
        .grade-ring { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; color: #fff; }
        .grade-ring svg { width: 30px; height: 30px; }
        .grade-card h3 { font-size: 17px; font-weight: 800; margin-bottom: 6px; }
        .grade-card p { font-size: 13px; color: var(--text-muted); font-weight: 600; }

        /* STEPS */
        .step-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 28px; position: relative; }
        .step-num { position: absolute; top: -18px; left: 24px; width: 40px; height: 40px; border-radius: 12px; background: var(--primary-blue); color: #fff; font-weight: 800; font-size: 18px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 16px rgba(15,63,122,.3); }
        .step-card h3 { font-size: 17px; font-weight: 800; margin: 12px 0 8px; }
        .step-card p { font-size: 13.5px; color: var(--text-muted); line-height: 1.65; }

        /* CTA */
        .cta-box { max-width: 1140px; margin: 72px auto; padding: 0 24px; }
        .cta-inner { background: #0B2F5E; border-bottom: 3px solid var(--gold); border-radius: 24px; padding: 56px 40px; text-align: center; color: #fff; position: relative; overflow: hidden; }
        .cta-inner::before { content: "АБВ"; position: absolute; right: 24px; bottom: -30px; font-size: 130px; font-weight: 800; opacity: .08; }
        .cta-inner h2 { font-size: 30px; font-weight: 800; margin-bottom: 12px; }
        .cta-inner p { opacity: .85; margin-bottom: 28px; font-size: 15px; }

        /* FOOTER */
        footer { border-top: 1px solid var(--border-color); background: #fff; }
        .foot-inner { max-width: 1140px; margin: 0 auto; padding: 28px 24px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .foot-inner img { width: 32px; height: 32px; object-fit: contain; }
        .foot-inner .fbrand { font-weight: 800; color: var(--primary-blue); }
        .foot-inner .fbrand span { color: var(--gold); }
        .foot-inner small { color: var(--text-muted); margin-left: auto; }

        @media (max-width: 900px) {
            .hero-inner { grid-template-columns: 1fr; padding: 52px 24px 60px; }
            .hero h1 { font-size: 34px; }
            .grid-3 { grid-template-columns: 1fr 1fr; }
            .grade-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .nav-links { display: none; position: absolute; top: 68px; left: 0; right: 0; background: #fff; flex-direction: column; padding: 16px 24px 24px; gap: 16px; border-bottom: 1px solid var(--border-color); margin-left: 0; }
            .nav-links.open { display: flex; }
            .hamburger { display: block; }
            .nav-cta .btn-outline { display: none; }
            .grid-3, .grid-2 { grid-template-columns: 1fr; }
            .hero-stats { gap: 24px; }
            .float-1 { left: 8px; } .float-2 { right: 8px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/mobile.css?v=20261123">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-inner">
        <a class="brand" href="index.php">
            <img src="assets/img/logo.png" alt="Logo RusVoca">
            <span class="brand-name">Rus<span>Voca</span></span>
        </a>
        <div class="nav-links" id="navLinks">
            <a href="#fitur"><?= t('Mode Latihan', 'Practice Modes') ?></a>
            <a href="#tingkatan"><?= t('Tingkatan', 'Levels') ?></a>
            <a href="#mulai"><?= t('Cara Memulai', 'How to Start') ?></a>
        </div>
        <div class="nav-cta">
            <?= tombol_bahasa('inline') ?>
            <?php if ($sudah_login): ?>
                <span style="font-size:13px; font-weight:700; color:var(--text-muted);"><?= t('Halo', 'Hi') ?>, <?= htmlspecialchars($nama_user) ?>!</span>
                <a href="dashboard.php" class="btn btn-primary"><?= t('Buka Dashboard', 'Open Dashboard') ?></a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline"><?= t('Masuk', 'Log In') ?></a>
                <a href="register.php" class="btn btn-primary"><?= t('Daftar Gratis', 'Sign Up Free') ?></a>
            <?php endif; ?>
        </div>
        <button class="hamburger" onclick="document.getElementById('navLinks').classList.toggle('open')" aria-label="<?= t('Menu', 'Menu') ?>">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
        </button>
    </div>
</nav>

<!-- HERO -->
<header class="hero">
    <div class="hero-inner">
        <div>
            <div class="hero-badge"><span class="dot"></span> <?= t('APLIKASI BELAJAR BAHASA RUSIA', 'RUSSIAN LEARNING APP') ?></div>
            <h1><?= t('Belajar Kosakata Bahasa Rusia dengan Cara <span class="hl">Seru</span>', 'Learn Russian Vocabulary the <span class="hl">Enjoyable</span> Way') ?></h1>
            <p><?= t('RusVoca menggabungkan materi alfabet Kiril yang mendalam, kosakata bertahap, dan 4 latihan interaktif — kuis kata, kuis audio, berbicara, dan menulis. Kumpulkan XP dan naikkan tingkatanmu dari Pemula hingga Legenda!', 'RusVoca combines in-depth Cyrillic alphabet lessons, progressive vocabulary, and 4 interactive practices — word quiz, listening quiz, speaking, and writing. Earn XP and rise from Beginner to Legend!') ?></p>
            <div class="hero-cta">
                <?php if ($sudah_login): ?>
                    <a href="dashboard.php" class="btn btn-gold btn-lg"><?= t('Lanjutkan Belajar', 'Continue Learning') ?></a>
                    <a href="#fitur" class="btn btn-lg" style="background:rgba(255,255,255,.15); color:#fff; border:1px solid rgba(255,255,255,.3);"><?= t('Lihat Fitur', 'View Features') ?></a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-gold btn-lg"><?= t('Mulai Belajar Gratis', 'Start Learning Free') ?></a>
                    <a href="login.php" class="btn btn-lg" style="background:rgba(255,255,255,.15); color:#fff; border:1px solid rgba(255,255,255,.3);"><?= t('Saya Sudah Punya Akun', 'I Already Have an Account') ?></a>
                <?php endif; ?>
            </div>
            <div class="hero-stats">
                <div class="stat"><b>4</b><span><?= t('Mode Latihan', 'Practice Modes') ?></span></div>
                <div class="stat"><b>4</b><span><?= t('Tingkatan XP', 'XP Levels') ?></span></div>
                <div class="stat"><b>100%</b><span><?= t('Gratis', 'Free') ?></span></div>
            </div>
        </div>
        <div class="hero-visual">
            <img src="assets/img/tamnel.png" alt="<?= t('Ilustrasi belajar bahasa Rusia', 'Russian learning illustration') ?>">
            <div class="float-card float-1">
                <div class="float-ico" style="background:#B45309;">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                </div>
                <div><?= t('Pemula', 'Beginner') ?><small><?= t('Kumpulkan XP pertamamu', 'Earn your first XP') ?></small></div>
            </div>
            <div class="float-card float-2">
                <div class="float-ico" style="background:#10B981;">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </div>
                <div><?= t('Skor 100', 'Score 100') ?><small><?= t('Lulus semua latihan', 'Pass all practices') ?></small></div>
            </div>
        </div>
    </div>
</header>

<!-- FITUR -->
<section class="section" id="fitur">
    <span class="section-tag"><?= t('Mode Latihan', 'Practice Modes') ?></span>
    <h2><?= t('4 Cara Seru Menguasai Kosakata', '4 Enjoyable Ways to Master Vocabulary') ?></h2>
    <p class="lead"><?= t('Setiap bab pelajaran dilengkapi empat latihan berbeda. Lulus semuanya dengan skor minimal 60 untuk membuka bab berikutnya.', 'Every lesson chapter includes four different practices. Pass them all with a minimum score of 60 to unlock the next chapter.') ?></p>
    <div class="grid-3" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
        <div class="feat-card">
            <div class="feat-ico c-green"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg></div>
            <h3><?= t('Kuis Kosa Kata', 'Vocabulary Quiz') ?></h3>
            <p><?= t('Tebak arti kata bahasa Rusia lewat pilihan ganda yang diacak. Ada tombol suara untuk mendengar pelafalannya.', 'Guess the meaning of Russian words through shuffled multiple-choice options. A sound button lets you hear the pronunciation.') ?></p>
        </div>
        <div class="feat-card">
            <div class="feat-ico c-orange"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg></div>
            <h3><?= t('Kuis Audio', 'Listening Quiz') ?></h3>
            <p><?= t('Dengarkan pelafalan asli lalu pilih jawaban yang tepat. Melatih telinga dan pengucapan.', 'Listen to native pronunciation and choose the correct answer. Trains your ears and pronunciation.') ?></p>
        </div>
        <div class="feat-card">
            <div class="feat-ico c-purple"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg></div>
            <h3><?= t('Latihan Berbicara', 'Speaking Practice') ?></h3>
            <p><?= t('Ucapkan kosakata Rusia dengan mikrofon dan dapatkan penilaian otomatis.', 'Pronounce Russian vocabulary using your microphone and get an automatic score.') ?></p>
        </div>
        <div class="feat-card">
            <div class="feat-ico c-pink"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></div>
            <h3><?= t('Latihan Menulis', 'Writing Practice') ?></h3>
            <p><?= t('Susun huruf Kiril dengan bantuan suara, transliterasi, dan petunjuk huruf pertama.', 'Arrange Cyrillic letters with sound, transliteration, and first-letter hints.') ?></p>
        </div>
    </div>
</section>

<!-- TINGKATAN -->
<section class="section" id="tingkatan">
    <span class="section-tag"><?= t('Sistem XP', 'XP System') ?></span>
    <h2><?= t('Naik Tingkatan, Kumpulkan XP', 'Level Up, Collect XP') ?></h2>
    <p class="lead"><?= t('Setiap skor terbaikmu di tiap mode latihan dihitung sebagai XP. Semakin rajin, semakin tinggi grade keahlianmu.', 'Your best score in each practice mode counts as XP. The more you practice, the higher your skill level.') ?></p>
    <div class="grade-grid">
        <div class="grade-card">
            <div class="grade-ring" style="background:#B45309;"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
            <h3><?= t('Pemula', 'Beginner') ?></h3><p>0 – 499 XP</p>
        </div>
        <div class="grade-card">
            <div class="grade-ring" style="background:#64748B;"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L3 6v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V6l-9-4z"/></svg></div>
            <h3><?= t('Menengah', 'Intermediate') ?></h3><p>500 – 1.499 XP</p>
        </div>
        <div class="grade-card">
            <div class="grade-ring" style="background:#D97706;"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M5 16h14v2H5zm14-9l-4 3-3-5-3 5-4-3 2 8h10l2-8z"/></svg></div>
            <h3><?= t('Mahir', 'Advanced') ?></h3><p>1.500 – 2.999 XP</p>
        </div>
        <div class="grade-card">
            <div class="grade-ring" style="background:#2563EB;"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 9l10 13 10-13z"/></svg></div>
            <h3><?= t('Legenda', 'Legend') ?></h3><p>3.000+ XP</p>
        </div>
    </div>
</section>

<!-- CARA MEMULAI -->
<section class="section" id="mulai" style="padding-bottom: 8px;">
    <span class="section-tag"><?= t('Mulai dalam 3 langkah', 'Start in 3 steps') ?></span>
    <h2><?= t('Mulai Belajarmu Hari Ini', 'Start Learning Today') ?></h2>
    <p class="lead"><?= t('Tidak perlu pengalaman bahasa Rusia. Cukup daftar dan ikuti alurnya.', 'No Russian experience needed. Just sign up and follow the path.') ?></p>
    <div class="grid-3">
        <div class="step-card">
            <div class="step-num">1</div>
            <h3><?= t('Daftar Akun Gratis', 'Create a Free Account') ?></h3>
            <p><?= t('Buat akun hanya dengan nama, email, dan password. Langsung bisa dipakai belajar.', 'Create an account with just your name, email, and password. Ready to use right away.') ?></p>
        </div>
        <div class="step-card">
            <div class="step-num">2</div>
            <h3><?= t('Pelajari Alfabet & Kosakata', 'Learn the Alphabet & Vocabulary') ?></h3>
            <p><?= t('Kenali huruf Kiril dan hafalkan kosakata tiap bab lewat kartu flash interaktif.', 'Learn Cyrillic letters and memorize each chapter’s vocabulary with interactive flashcards.') ?></p>
        </div>
        <div class="step-card">
            <div class="step-num">3</div>
            <h3><?= t('Tuntaskan 4 Latihan', 'Complete All 4 Practices') ?></h3>
            <p><?= t('Dapatkan skor minimal 60 di semua mode untuk membuka bab berikutnya dan panen XP.', 'Score at least 60 in every mode to unlock the next chapter and harvest XP.') ?></p>
        </div>
    </div>
</section>

<!-- CTA -->
<div class="cta-box">
    <div class="cta-inner">
        <h2><?= t('Siap Jadi Legenda Bahasa Rusia?', 'Ready to Become a Russian Legend?') ?></h2>
        <p><?= t('Bergabung sekarang, gratis dan tanpa batas. Приступим!', 'Join now, free and unlimited. Приступим!') ?></p>
        <?php if ($sudah_login): ?>
            <a href="dashboard.php" class="btn btn-gold btn-lg"><?= t('Kembali ke Dashboard', 'Back to Dashboard') ?></a>
        <?php else: ?>
            <a href="register.php" class="btn btn-gold btn-lg"><?= t('Daftar Sekarang — Gratis', 'Sign Up Now — Free') ?></a>
        <?php endif; ?>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="foot-inner">
        <img src="assets/img/logo.png" alt="Logo RusVoca">
        <span class="fbrand">Rus<span>Voca</span></span>
        <small>&copy; <?= date('Y') ?> RusVoca — Russian Vocabulary App. <?= t('Belajar jadi seru.', 'Learning made enjoyable.') ?></small>
    </div>
</footer>

</body>
</html>
