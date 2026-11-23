<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/auth.php';
require_once 'config/progres.php';
require_once 'config/riwayat_kuis.php';
require_once 'config/bahasa.php';
require_once 'config/level.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);$nama_user = $_SESSION['nama'] ?? 'Siswa';$id_pelajaran = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id_pelajaran']) ? (int)$_POST['id_pelajaran'] : 0);
$is_admin = pengguna_admin($koneksi);
$level    = hitung_level($koneksi, $user_id, $is_admin);

// Kode mode + penanda waktu mulai (untuk pencatatan target harian)
$mode_kode = 'kuis_mendengarkan';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { tandai_mulai_belajar($id_pelajaran, $mode_kode); }

// ==============================================================================
// LOGIKA SIMPAN SKOR
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['skor_akhir']) && csrf_valid()) {
    $skor = max(0, min(100, (int)$_POST['skor_akhir']));
    $mode = 'kuis_mendengarkan'; // Kode mode untuk database

    try {
        if ($id_pelajaran > 0) {
            // Hanya simpan nilai tertinggi
            $cek_skor = mysqli_query($koneksi, "SELECT skor FROM tb_progres_belajar WHERE user_id = $user_id AND id_pelajaran = $id_pelajaran AND mode_belajar = '$mode'");

            if ($cek_skor && mysqli_num_rows($cek_skor) > 0) {
                $skor_lama = (int)mysqli_fetch_assoc($cek_skor)['skor'];
                if ($skor > $skor_lama) {
                    mysqli_query($koneksi, "UPDATE tb_progres_belajar SET skor = $skor, tanggal_akses = CURRENT_TIMESTAMP WHERE user_id = $user_id AND id_pelajaran = $id_pelajaran AND mode_belajar = '$mode'");
                } else {
                    mysqli_query($koneksi, "UPDATE tb_progres_belajar SET tanggal_akses = CURRENT_TIMESTAMP WHERE user_id = $user_id AND id_pelajaran = $id_pelajaran AND mode_belajar = '$mode'");
                }
            } else {
                mysqli_query($koneksi, "INSERT INTO tb_progres_belajar (user_id, id_pelajaran, mode_belajar, skor, status_selesai) VALUES ($user_id, $id_pelajaran, '$mode', $skor, 1)");
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
            simpan_riwayat_kuis($koneksi, $user_id, $id_pelajaran, $mode, $skor, $detail);
        }
    }

    // Kembali ke halaman menu pilihan mode (id aman dari GET maupun POST)
    if ($id_pelajaran > 0) {
        header("Location: detail_pelajaran.php?id=$id_pelajaran");
    } else {
        header("Location: kosakata.php");
    }
    exit();
}
// ==============================================================================

// Ambil info pelajaran
$query_pelajaran = mysqli_query($koneksi, "SELECT * FROM tb_pelajaran WHERE id_pelajaran = $id_pelajaran");
if (!$query_pelajaran || mysqli_num_rows($query_pelajaran) == 0) {
    header("Location: kosakata.php");
    exit();
}
$data_pelajaran = mysqli_fetch_assoc($query_pelajaran);

// Ambil daftar kosakata
$query_kosakata = mysqli_query($koneksi, "SELECT * FROM tb_kosakata WHERE id_pelajaran = $id_pelajaran");
$kosakata_array = [];
if ($query_kosakata) {
while($row = mysqli_fetch_assoc($query_kosakata)){
    $kosakata_array[] =$row;
}
}
$json_kosakata = json_encode($kosakata_array);
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Kuis Mendengarkan', 'Listening Quiz') ?> - <?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    
    <style>
        :root {
            --primary-blue: #0F3F7A; --bg-light: #F4F7FA; --border-color: #E2E8F0;
            --text-dark: #0F172A; --text-muted: #64748B; --card-bg: #FFFFFF;
            --accent-orange: #F59E0B; --accent-green: #10B981; --accent-red: #EF4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .app-layout { display: flex; min-height: 100vh; }

        /* SIDEBAR (Sama dengan sebelumnya) */
        .sidebar { width: 260px; background-color: #FFFFFF; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between; padding: 24px 16px; position: fixed; height: 100vh; left: 0; top: 0; z-index: 50; }
        .brand-header { display: flex; align-items: center; gap: 12px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; padding-left: 10px; }
        .brand-logo-img { width: 42px; height: 42px; object-fit: contain; }
        .brand-name { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; color: var(--primary-blue); }
        .brand-name span { color: #E09B19; }
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
        
        /* KONTEN UTAMA */
        .main-content { margin-left: 260px; flex: 1; padding: 40px; display: flex; flex-direction: column; align-items: center; }
        .header-area { width: 100%; max-width: 700px; margin-bottom: 20px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 700; margin-bottom: 24px; transition: 0.2s; }
        .btn-back:hover { color: var(--primary-blue); transform: translateX(-5px); }

        /* Kotak Kuis */
        .quiz-container { width: 100%; max-width: 700px; background-color: var(--card-bg); border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid var(--border-color); padding: 40px; text-align: center; }
        .quiz-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; font-weight: 700; color: var(--text-muted); font-size: 14px; }
        
        .progress-bar-bg { width: 100%; height: 8px; background: var(--bg-light); border-radius: 4px; margin-top: 10px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: var(--accent-orange); width: 0%; transition: width 0.3s ease; }

        /* AREA AUDIO BUKAN TEKS */
        .audio-play-area { margin-bottom: 40px; display: flex; flex-direction: column; align-items: center; gap: 16px; }
        
        .btn-big-audio {
            width: 100px; height: 100px; border-radius: 50%;
            background: #B45309;
            border: 1px solid #92400E; color: #fff; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 20px rgba(180, 83, 9, 0.28);
            transition: all 0.2s;
        }
        .btn-big-audio:hover { transform: scale(1.05); }
        .btn-big-audio.playing { animation: pulse-orange 1s infinite; }

        @keyframes pulse-orange { 0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.5); } 70% { box-shadow: 0 0 0 20px rgba(245, 158, 11, 0); } 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); } }

        .hint-text { font-size: 14px; color: var(--text-muted); font-weight: 600; }
        .revealed-text { font-size: 24px; font-weight: 800; color: var(--primary-blue); display: none; margin-top: 10px; }

        /* Grid Pilihan Ganda */
        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 30px; }
        .btn-option { padding: 16px; font-size: 16px; font-weight: 700; color: var(--text-dark); background-color: var(--card-bg); border: 2px solid var(--border-color); border-radius: 12px; cursor: pointer; transition: all 0.2s; }
        .btn-option:hover:not(:disabled) { border-color: var(--accent-orange); background-color: #FFFBEB; }
        .btn-option.correct { background-color: #D1FAE5; border-color: var(--accent-green); color: #065F46; }
        .btn-option.wrong { background-color: #FEE2E2; border-color: var(--accent-red); color: #991B1B; opacity: 0.8; }
        .btn-option:disabled { cursor: not-allowed; }

        .btn-next { padding: 14px 30px; background-color: var(--primary-blue); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.2s; display: none; margin: 0 auto; }
        .btn-next:hover { background-color: #0a2d59; transform: scale(1.05); }

        /* Layar Hasil Kuis */
        .result-screen { display: none; flex-direction: column; align-items: center; }
        .score-circle { width: 120px; height: 120px; border-radius: 50%; background: #B45309; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 40px; font-weight: 800; margin-bottom: 20px; box-shadow: 0 8px 18px rgba(180, 83, 9, 0.22); }

        @media (max-width: 768px) {
            .sidebar { width: 72px; padding: 16px 8px; }
            .brand-text, .user-info { display: none; }
            .brand-logo-img { width: 32px; height: 32px; }
            .brand-header { padding-bottom: 12px; margin-bottom: 12px; justify-content: center; padding-left: 0; }
            .user-profile-bottom { justify-content: center; padding: 8px; flex-direction: column; gap: 15px; }
            .nav-item a { justify-content: center; padding: 12px; }
            .nav-item span { display: none; }
            .main-content { margin-left: 72px; padding: 24px; }
            .options-grid { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/mobile.css?v=20261123">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <!-- Sidebar SAMA -->
        <div>
            <div class="brand-header"><img src="assets/img/logo.png" alt="Logo RusVoca" class="brand-logo-img"><div class="brand-text"><div class="brand-name">Rus<span>Voca</span></div></div></div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg><span><?= t('Dashboard', 'Dashboard') ?></span></a></li>
                <li class="nav-item"><a href="alfabet.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h9v2zm0-4H9V5h9v2z"/></svg><span><?= t('Belajar Alfabet', 'Learn the Alphabet') ?></span></a></li>
                <li class="nav-item active"><a href="kosakata.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 4h2v5H9V4zm0 7h2v2H9v-2zm0 4h2v2H9v-2zm6 2h-3v-2h3v2zm0-4h-3v-2h3v2zm0-5h-3V4h3v5z"/></svg><span><?= t('Kosakata', 'Vocabulary') ?></span></a></li>
                <li class="nav-item"><a href="riwayat.php"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span><?= t('Riwayat', 'History') ?></span></a></li>
                <li class="nav-item"><a href="profil.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= t('Profil Saya', 'My Profile') ?></span></a></li>
                <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>
                <li class="nav-lang"><?= tombol_bahasa('sidebar') ?></li>
            </ul>
        </div>
        <div class="user-profile-bottom">
            <div class="avatar-circle"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
            <div class="user-info"><div class="user-info-name"><?= htmlspecialchars($nama_user); ?></div><div class="user-info-role" style="color: <?= $level['warna'] ?>;"><svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="<?= $level['ikon'] ?>"/></svg><span><?= htmlspecialchars(materi_t($level['nama'])) ?></span></div></div>
            <form action="logout.php" method="POST" style="display: flex;" onsubmit="return confirm('<?= t('Apakah Anda yakin ingin keluar?', 'Are you sure you want to log out?') ?>');">
                <?= csrf_field() ?><button type="submit" class="logout-btn"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></button>
            </form>
        </div>
    </aside>

    <main class="main-content">
        <div class="header-area">
            <a href="detail_pelajaran.php?id=<?= $id_pelajaran ?>" class="btn-back">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                <?= t('Keluar Kuis', 'Exit Quiz') ?>
            </a>
        </div>

        <div class="quiz-container">
            <?php if(count($kosakata_array) >= 2): ?>
                
                <div id="quiz-screen">
                    <div class="quiz-header">
                        <span style="color: var(--accent-orange);"><?= t('Dengarkan dan Pilih Arti!', 'Listen and Choose the Meaning!') ?></span>
                        <span id="question-counter"><?= t('Pertanyaan', 'Question') ?> 1 / 5</span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" id="progress-bar"></div>
                    </div>

                    <!-- Area Putar Suara (Teks Rusia Disembunyikan Awalnya) -->
                    <div class="audio-play-area">
                        <button class="btn-big-audio" id="btn-audio" onclick="replayAudio()" title="<?= t('Putar Ulang Suara', 'Replay Audio') ?>">
                            <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                            </svg>
                        </button>
                        <div class="hint-text"><?= t('Klik ikon untuk memutar ulang audio', 'Click the icon to replay the audio') ?></div>
                        
                        <!-- Teks Rusia akan muncul setelah user menjawab -->
                        <div class="revealed-text" id="revealed-text">Привет</div>
                    </div>

                    <div class="options-grid" id="options-container">
                        <!-- Pilihan otomatis JS -->
                    </div>

                    <button class="btn-next" id="btn-next" onclick="nextQuestion()"><?= t('Lanjut Pertanyaan', 'Next Question') ?> →</button>
                </div>

                <!-- Tampilan Skor Akhir -->
                <div id="result-screen" class="result-screen">
                    <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 10px;"><?= t('Pendengaranmu Bagus!', 'Great Listening!') ?></h2>
                    <p style="color: var(--text-muted); margin-bottom: 20px;"><?= t('Ini adalah hasil kuis mendengarkanmu:', 'Here is your listening quiz result:') ?></p>
                    
                    <div class="score-circle" id="final-score">100</div>
                    
                    <form action="?id=<?= $id_pelajaran ?>" method="POST" style="width: 100%; max-width: 300px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="skor_akhir" id="input-skor" value="0">
                        <input type="hidden" name="detail_jawaban" id="input-detail" value="">
                        <input type="hidden" name="id_pelajaran" value="<?= $id_pelajaran ?>">
                        <button type="submit" class="btn-next" style="display: block; width: 100%;">
                            <?= t('Simpan Skor & Kembali', 'Save Score & Return') ?> →
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <div style="color: var(--text-muted);"><?= t('Materi kurang dari 2 kata. Kuis tidak bisa dijalankan.', 'There are fewer than 2 words. The quiz cannot run.') ?></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- SCRIPT LOGIKA KUIS MENDENGARKAN -->
<script>
    const dbKosakata = <?= $json_kosakata ?>;
    const TXT = {
        question: <?= json_encode(t('Pertanyaan', 'Question'), JSON_UNESCAPED_UNICODE) ?>,
        unsupported: <?= json_encode(t('Maaf, browser tidak mendukung audio.', 'Sorry, this browser does not support audio.'), JSON_UNESCAPED_UNICODE) ?>
    };
    
    let questions = [];
    let currentQIndex = 0;
    let score = 0;
    let totalQuestions = dbKosakata.length; 
    let currentAudioWord = ""; // Menyimpan kata yang sedang diuji
    let detailJawaban = [];

    const quizScreen = document.getElementById('quiz-screen');
    const resultScreen = document.getElementById('result-screen');
    const revealedText = document.getElementById('revealed-text');
    const btnAudio = document.getElementById('btn-audio');
    const optionsContainer = document.getElementById('options-container');
    const btnNext = document.getElementById('btn-next');
    const questionCounter = document.getElementById('question-counter');
    const progressBar = document.getElementById('progress-bar');
    
    function shuffle(array) {
        let currentIndex = array.length, randomIndex;
        while (currentIndex !== 0) {
            randomIndex = Math.floor(Math.random() * currentIndex);
            currentIndex--;
            [array[currentIndex], array[randomIndex]] = [array[randomIndex], array[currentIndex]];
        }
        return array;
    }

    function initQuiz() {
        if(dbKosakata.length < 2) return;
        
        let shuffledDB = shuffle([...dbKosakata]);
        
        shuffledDB.forEach(k => {
            let correctAns = k.arti_indonesia;
            let wrongOptions = dbKosakata.filter(x => x.arti_indonesia !== correctAns).map(x => x.arti_indonesia);
            wrongOptions = shuffle(wrongOptions).slice(0, 3);
            let finalOptions = shuffle([correctAns, ...wrongOptions]);
            
            questions.push({
                word: k.kata_rusia,
                read: k.cara_baca,
                correct: correctAns,
                options: finalOptions
            });
        });
        
        loadQuestion();
    }

    function loadQuestion() {
        btnNext.style.display = 'none';
        revealedText.style.display = 'none'; // Sembunyikan kata Rusia
        optionsContainer.innerHTML = '';
        
        const q = questions[currentQIndex];
        currentAudioWord = q.word; // Simpan untuk diputar JS
        revealedText.textContent = `${q.word} [${q.read}]`; // Update teks rahasia
        
        questionCounter.textContent = `${TXT.question} ${currentQIndex + 1} / ${totalQuestions}`;
        progressBar.style.width = `${((currentQIndex) / totalQuestions) * 100}%`;

        q.options.forEach(opt => {
            const btn = document.createElement('button');
            btn.className = 'btn-option';
            btn.textContent = opt;
            btn.onclick = () => checkAnswer(btn, opt, q.correct);
            optionsContainer.appendChild(btn);
        });

        // Otomatis putar audio saat soal muncul (diberi jeda dikit biar halaman ready)
        setTimeout(replayAudio, 500);
    }

    // Fungsi Putar Audio (Web Speech API)
    function replayAudio() {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
            
            let msg = new SpeechSynthesisUtterance();
            msg.text = currentAudioWord;
            msg.lang = 'ru-RU'; 
            msg.rate = 0.85; 
            
            btnAudio.classList.add('playing');
            msg.onend = function() { btnAudio.classList.remove('playing'); };
            
            window.speechSynthesis.speak(msg);
        } else {
            alert(TXT.unsupported);
        }
    }

    function checkAnswer(selectedBtn, selectedAns, correctAns) {
        const allBtns = document.querySelectorAll('.btn-option');
        allBtns.forEach(btn => btn.disabled = true); // Kunci jawaban

        const q = questions[currentQIndex];
        detailJawaban.push({
            pertanyaan: q.word + ' [' + q.read + ']',
            jawaban_user: selectedAns,
            jawaban_benar: correctAns,
            benar: selectedAns === correctAns,
            tipe: 'mendengarkan'
        });
        
        // Setelah menjawab, baru teks bahasa rusia nya dimunculkan
        revealedText.style.display = 'block';
        
        if(selectedAns === correctAns) {
            selectedBtn.classList.add('correct');
            score++;
            if (window.RusvocaSound) RusvocaSound.benar();
        } else {
            selectedBtn.classList.add('wrong');
            if (window.RusvocaSound) RusvocaSound.salah();
            allBtns.forEach(btn => {
                if(btn.textContent === correctAns) btn.classList.add('correct');
            });
        }
        
        btnNext.style.display = 'block';
    }

    function nextQuestion() {
        currentQIndex++;
        if (currentQIndex < totalQuestions) {
            loadQuestion();
        } else {
            showResult();
        }
    }

    function showResult() {
        quizScreen.style.display = 'none';
        resultScreen.style.display = 'flex';
        
        let finalScore = Math.round((score / totalQuestions) * 100);
        document.getElementById('final-score').textContent = finalScore;
        document.getElementById('input-skor').value = finalScore;
        document.getElementById('input-detail').value = JSON.stringify(detailJawaban);
        if (window.RusvocaSound) RusvocaSound.menang();
    }

    window.onload = initQuiz;
</script>

<script src="assets/js/sound.js"></script>
<script src="assets/js/quiz-guard.js?v=20261108"></script>
<?php include __DIR__ . '/partials/target_harian.php'; ?>
</body>
</html>