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
$mode_kode = 'mode_speaking';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { tandai_mulai_belajar($id_pelajaran, $mode_kode); }

// ==============================================================================
// LOGIKA SIMPAN SKOR (Hanya menyimpan nilai tertinggi)
// ==============================================================================
if (isset($_POST['simpan_skor']) && csrf_valid()) {
    $skor_baru = max(0, min(100, (int)$_POST['skor']));
    $mode_belajar = 'mode_speaking';

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

    // Kembali ke halaman menu pilihan mode (id aman dari GET maupun POST)
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
    <title><?= t('Latihan Speaking', 'Speaking Practice') ?> - <?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?></title>
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
        .main-content { margin-left: 260px; flex: 1; padding: 40px; max-width: 900px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 700; margin-bottom: 24px; transition: 0.2s; }
        .btn-back:hover { color: var(--primary-blue); transform: translateX(-5px); }
        
        .game-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .game-title h2 { font-size: 24px; font-weight: 800; color: var(--text-dark); }
        .game-title p { font-size: 14px; color: var(--text-muted); }
        .progress-box { font-weight: 800; color: var(--primary-blue); background: #E0E7FF; padding: 8px 16px; border-radius: 12px; }

        /* KOTAK SPEAKING */
        .speaking-card { background: #fff; border: 2px solid var(--border-color); border-radius: 20px; padding: 40px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .word-arti { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 10px; }
        .word-rusia { font-size: 40px; font-weight: 800; color: var(--primary-blue); margin-bottom: 5px; }
        .word-trans { font-size: 16px; color: var(--text-muted); margin-bottom: 40px; font-style: italic; }

        .mic-btn { width: 100px; height: 100px; border-radius: 50%; background: var(--primary-blue); border: 1px solid #0a2d59; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; margin: 0 auto; transition: 0.3s; box-shadow: 0 8px 18px rgba(15, 63, 122, 0.22); }
        .mic-btn:hover { transform: scale(1.04); background: #0a2d59; }
        .mic-btn.listening { animation: pulse 1.5s infinite; background: #C2410C; border-color: #9A3412; box-shadow: 0 10px 20px rgba(194, 65, 12, 0.25); }
        .mic-btn svg { width: 40px; height: 40px; }
        
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }

        .status-text { margin-top: 20px; font-size: 15px; font-weight: 700; color: var(--text-muted); min-height: 24px; }
        .result-text { font-size: 18px; font-weight: 800; margin-top: 10px; }
        .benar { color: var(--accent-green); }
        .salah { color: var(--accent-red); }

        .btn-next { display: none; margin: 30px auto 0; padding: 12px 30px; background: var(--primary-blue); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-next:hover { background: #0a2d59; }

        #end-screen { display: none; text-align: center; }
        .skor-akhir { font-size: 60px; font-weight: 800; color: var(--primary-blue); margin: 20px 0; }
        .btn-save { padding: 14px 40px; background: var(--accent-green); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 800; cursor: pointer; transition: 0.2s; width: 100%; max-width: 300px; }

        @media (max-width: 768px) {
            .sidebar { width: 72px; padding: 16px 8px; }
            .brand-text, .user-info { display: none; }
            .brand-logo-img { width: 32px; height: 32px; }
            .main-content { margin-left: 72px; padding: 24px; }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div>
            <div class="brand-header"><img src="assets/img/logo.png" alt="Logo RusVoca" class="brand-logo-img"><div class="brand-text"><div class="brand-name">Rus<span>Voca</span></div><div class="brand-sub">RUSSIAN VOCABULARY APP</div></div></div>
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
                <h2><?= t('Latihan Speaking', 'Speaking Practice') ?></h2>
                <p><?= t('Ucapkan kata dalam bahasa Rusia dengan jelas.', 'Pronounce the Russian word clearly.') ?></p>
            </div>
            <div class="progress-box"><span id="current-q">1</span> / <span id="total-q"><?= count($kosakata_array) ?></span></div>
        </div>

        <div class="speaking-card" id="game-screen">
            <div class="word-arti" id="teks-arti">...</div>
            <div class="word-rusia" id="teks-rusia">...</div>
            <div class="word-trans" id="teks-trans">...</div>

            <button class="mic-btn" id="btn-mic" onclick="startListening()">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                </svg>
            </button>

            <div class="status-text" id="status-text"><?= t('Tekan mikrofon dan mulailah berbicara...', 'Press the microphone and start speaking...') ?></div>
            <div class="result-text" id="result-text"></div>

            <button class="btn-next" id="btn-next" onclick="nextQuestion()"><?= t('Lanjut', 'Next') ?></button>
        </div>

        <div class="speaking-card" id="end-screen">
            <h2><?= t('Latihan Selesai!', 'Practice Complete!') ?></h2>
            <div class="skor-akhir" id="skor-display">0</div>
            <p style="color: var(--text-muted); margin-bottom: 30px;"><?= t('Skor tertinggimu akan otomatis disimpan ke database.', 'Your best score will be saved automatically.') ?></p>
            
            <form method="POST" action="?id=<?= $id_pelajaran ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="skor" id="input-skor">
                <input type="hidden" name="detail_jawaban" id="input-detail" value="">
                <input type="hidden" name="id_pelajaran" value="<?= $id_pelajaran ?>">
                <button type="submit" name="simpan_skor" class="btn-save"><?= t('Simpan Skor & Kembali', 'Save Score & Return') ?></button>
            </form>
        </div>
    </main>
</div>

<script>
    // Ambil data kosakata dari PHP
    const words = <?= json_encode($kosakata_array) ?>;
    const TXT = {
        unsupported: <?= json_encode(t('Browser Anda tidak mendukung fitur Suara. Harap gunakan Google Chrome.', 'Your browser does not support speech recognition. Please use Google Chrome.'), JSON_UNESCAPED_UNICODE) ?>,
        press: <?= json_encode(t('Tekan mikrofon dan mulailah berbicara...', 'Press the microphone and start speaking...'), JSON_UNESCAPED_UNICODE) ?>,
        listening: <?= json_encode(t('Mendengarkan... (Silakan bicara)', 'Listening... (Please speak)'), JSON_UNESCAPED_UNICODE) ?>,
        said: <?= json_encode(t('Kamu mengucapkan', 'You said'), JSON_UNESCAPED_UNICODE) ?>,
        correct: <?= json_encode(t('Tepat sekali!', 'Exactly right!'), JSON_UNESCAPED_UNICODE) ?>,
        wrong: <?= json_encode(t('Kurang tepat. Coba perbaiki pengucapanmu.', 'Not quite. Try improving your pronunciation.'), JSON_UNESCAPED_UNICODE) ?>,
        error: <?= json_encode(t('Suara tidak terdengar atau terjadi error. Coba lagi.', 'No speech detected or an error occurred. Try again.'), JSON_UNESCAPED_UNICODE) ?>
    };
    let currentIndex = 0;
    let skor = 0;
    const poinPerSoal = words.length > 0 ? 100 / words.length : 0;
    let detailJawaban = [];

    // Cek Dukungan Speech Recognition (Web Speech API)
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition;

    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = 'ru-RU'; // Fokus pengenalan suara bahasa Rusia
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
    } else {
        alert(TXT.unsupported);
    }

    // Element DOM
    const elArti = document.getElementById('teks-arti');
    const elRusia = document.getElementById('teks-rusia');
    const elTrans = document.getElementById('teks-trans');
    const btnMic = document.getElementById('btn-mic');
    const statusText = document.getElementById('status-text');
    const resultText = document.getElementById('result-text');
    const btnNext = document.getElementById('btn-next');

    function loadQuestion() {
        elArti.innerText = words[currentIndex].arti_indonesia;
        elRusia.innerText = words[currentIndex].kata_rusia;
        elTrans.innerText = words[currentIndex].cara_baca;
        
        document.getElementById('current-q').innerText = currentIndex + 1;
        
        statusText.innerText = TXT.press;
        resultText.innerText = "";
        btnNext.style.display = 'none';
        btnMic.style.display = 'flex';
    }

    function startListening() {
        if (!recognition) return;
        
        btnMic.classList.add('listening');
        statusText.innerText = TXT.listening;
        resultText.innerText = "";

        recognition.start();

        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript.toLowerCase().trim();
            const correctWord = words[currentIndex].kata_rusia.toLowerCase().trim();
            
            // Hapus tanda baca dasar untuk mempermudah pengecekan
            const cleanTranscript = transcript.replace(/[.,!?]/g, '');
            const cleanCorrect = correctWord.replace(/[.,!?]/g, '');

            statusText.innerText = `${TXT.said}: "${transcript}"`;

            const cocok = cleanTranscript === cleanCorrect;
            detailJawaban.push({
                pertanyaan: words[currentIndex].kata_rusia + ' — ' + words[currentIndex].arti_indonesia,
                jawaban_user: transcript,
                jawaban_benar: correctWord,
                benar: cocok,
                tipe: 'speaking'
            });

            if (cocok) {
                resultText.innerHTML = TXT.correct;
                resultText.className = "result-text benar";
                skor += poinPerSoal;
                if (window.RusvocaSound) RusvocaSound.benar();
            } else {
                resultText.innerHTML = TXT.wrong;
                resultText.className = "result-text salah";
                if (window.RusvocaSound) RusvocaSound.salah();
            }

            btnMic.classList.remove('listening');
            btnMic.style.display = 'none';
            btnNext.style.display = 'block';
        };

        recognition.onerror = function(event) {
            btnMic.classList.remove('listening');
            statusText.innerText = TXT.error;
        };
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

    // Inisialisasi game pertama kali
    if(words.length > 0) loadQuestion();
</script>
<script src="assets/js/sound.js"></script>
<script src="assets/js/quiz-guard.js?v=20261108"></script>
<?php include __DIR__ . '/partials/target_harian.php'; ?>
</body>
</html>