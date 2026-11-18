<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/bahasa.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$nama_user = $_SESSION['nama'] ?? 'Siswa';
$id_pelajaran = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil info pelajaran
$query_pelajaran = mysqli_query($koneksi, "SELECT * FROM tb_pelajaran WHERE id_pelajaran = $id_pelajaran");
if (!$query_pelajaran || mysqli_num_rows($query_pelajaran) == 0) {
    header("Location: kosakata.php");
    exit();
}
$data_pelajaran = mysqli_fetch_assoc($query_pelajaran);

// Ambil daftar kosakata dan ubah menjadi Array untuk dikirim ke JavaScript
$query_kosakata = mysqli_query($koneksi, "SELECT * FROM tb_kosakata WHERE id_pelajaran = $id_pelajaran");
$kosakata_array = [];
if ($query_kosakata) {
while($row = mysqli_fetch_assoc($query_kosakata)){
    $kosakata_array[] = $row;
}
}
// Encode ke JSON agar bisa dibaca oleh JavaScript
$json_kosakata = json_encode($kosakata_array);
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Kartu Flash', 'Flashcards') ?> - <?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    
    <style>
        :root {
            --primary-blue: #0F3F7A; --bg-light: #F4F7FA; --border-color: #E2E8F0;
            --text-dark: #0F172A; --text-muted: #64748B; --card-bg: #FFFFFF;
            --accent-purple: #9333EA;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .app-layout { display: flex; min-height: 100vh; }

        /* SIDEBAR (Sama persis dengan mode_kosakata.php) */
        .sidebar { width: 260px; background-color: #FFFFFF; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between; padding: 24px 16px; position: fixed; height: 100vh; left: 0; top: 0; z-index: 50; }
        .brand-header { display: flex; align-items: center; gap: 12px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; padding-left: 10px; }
        .brand-logo-img { width: 42px; height: 42px; object-fit: contain; }
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

        /* KONTEN UTAMA */
        .main-content { margin-left: 260px; flex: 1; padding: 40px; display: flex; flex-direction: column; align-items: center; }
        
        .header-area { width: 100%; max-width: 800px; margin-bottom: 20px; }
        
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted);
            text-decoration: none; font-size: 14px; font-weight: 700; margin-bottom: 24px; transition: 0.2s;
        }
        .btn-back:hover { color: var(--primary-blue); transform: translateX(-5px); }

        .page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 10px; }
        .icon-mode { width: 48px; height: 48px; background: #F5F3FF; color: #6D28D9; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .header-title h1 { font-size: 24px; font-weight: 800; color: var(--text-dark); }
        .header-title p { font-size: 14px; color: var(--text-muted); font-weight: 500; }

        /* === AREA FLASHCARD (ANIMASI 3D) === */
        .flashcard-wrapper {
            width: 100%; max-width: 600px; margin: 30px auto;
            perspective: 1000px; /* Efek 3D Kedalaman */
        }

        .flashcard {
            width: 100%; height: 350px;
            position: relative;
            transition: transform 0.6s cubic-bezier(0.4, 0.2, 0.2, 1); /* Animasi smooth */
            transform-style: preserve-3d;
            cursor: pointer;
        }

        /* Class ini ditambahkan oleh JS saat kartu diklik */
        .flashcard.flipped {
            transform: rotateY(180deg);
        }

        .card-face {
            position: absolute; width: 100%; height: 100%;
            -webkit-backface-visibility: hidden; /* Safari */
            backface-visibility: hidden;
            border-radius: 24px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 40px; text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        /* BAGIAN DEPAN (Indonesia) */
        .card-front {
            background-color: var(--card-bg); border: 2px solid var(--border-color);
        }
        .lang-label-front { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px; }
        .word-front { font-size: 32px; font-weight: 800; color: var(--text-dark); }
        .hint-text { position: absolute; bottom: 24px; font-size: 12px; color: var(--text-muted); font-weight: 600; display: flex; align-items: center; gap: 6px; }

        /* BAGIAN BELAKANG (Rusia) */
        .card-back {
            background: #0B2F5E;
            color: #fff;
            transform: rotateY(180deg); /* Membalik posisi awal */
        }
        .lang-label-back { font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px; }
        .word-back { font-size: 42px; font-weight: 800; margin-bottom: 10px; letter-spacing: 1px; }
        .transliteration { font-size: 18px; font-weight: 500; color: rgba(255,255,255,0.8); font-style: italic; margin-bottom: 30px; }

        .btn-audio {
            width: 56px; height: 56px; border-radius: 50%;
            background-color: #fff; border: none;
            color: var(--primary-blue); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .btn-audio:hover { transform: scale(1.1); }
        .btn-audio.playing { animation: pulse-audio 1s infinite; background-color: var(--accent-green); color: #fff; }

        @keyframes pulse-audio { 0% { box-shadow: 0 0 0 0 rgba(255,255,255,0.4); } 70% { box-shadow: 0 0 0 15px rgba(255,255,255,0); } 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); } }

        /* Navigasi Bawah (Prev / Next) */
        .card-controls { display: flex; align-items: center; justify-content: space-between; max-width: 600px; width: 100%; margin: 0 auto; padding: 0 20px; }
        
        .btn-nav {
            padding: 12px 24px; border: none; border-radius: 12px;
            background-color: var(--card-bg); color: var(--text-dark); font-weight: 700; font-size: 14px;
            cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.2s; display: flex; align-items: center; gap: 8px;
        }
        .btn-nav:hover:not(:disabled) { background-color: var(--primary-blue); color: #fff; }
        .btn-nav:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .card-counter { font-size: 15px; font-weight: 700; color: var(--text-muted); }

        .empty-state { text-align: center; padding: 40px; border: 1px dashed var(--border-color); border-radius: 12px; color: var(--text-muted); width: 100%; max-width: 600px; margin-top: 40px; }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <!-- (Sidebar Sama) -->
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
                <li class="nav-item"><a href="profil.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= t('Profil Saya', 'My Profile') ?></span></a></li>
                <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>
                <li class="nav-lang"><?= tombol_bahasa('sidebar') ?></li>
            </ul>
        </div>
        <div class="user-profile-bottom">
            <div class="avatar-circle"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
            <div class="user-info"><div class="user-info-name"><?= htmlspecialchars($nama_user); ?></div><div class="user-info-role"><?= t('Siswa', 'Student') ?></div></div>
            <form action="logout.php" method="POST" style="display: flex;" onsubmit="return confirm('<?= t('Apakah Anda yakin ingin keluar?', 'Are you sure you want to log out?') ?>');">
                <?= csrf_field() ?><button type="submit" class="logout-btn"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></button>
            </form>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="main-content">
        <div class="header-area">
            <a href="detail_pelajaran.php?id=<?= $id_pelajaran ?>" class="btn-back">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                <?= t('Kembali ke Pilihan Mode', 'Back to Mode Selection') ?>
            </a>
            <div class="rv-mode-head">
                <div class="rv-mode-icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
                </div>
                <div class="rv-mode-copy">
                    <div class="rv-mode-eyebrow"><?= t('Latihan', 'Practice') ?></div>
                    <h1><?= t('Kartu Flash', 'Flashcards') ?>: <?= htmlspecialchars(materi_t($data_pelajaran['judul_pelajaran'])) ?></h1>
                    <p><?= t('Hafalkan kata dengan membolak-balik kartu.', 'Memorize words by flipping the cards.') ?></p>
                </div>
            </div>
        </div>

        <?php if (count($kosakata_array) > 0): ?>
            
            <!-- Area Flashcard -->
            <div class="flashcard-wrapper">
                <div class="flashcard" id="flashcard" onclick="flipCard()">
                    
                    <!-- Sisi Depan (Indonesia) -->
                    <div class="card-face card-front">
                        <div class="lang-label-front"><?= t('Bahasa Indonesia', 'Indonesian') ?></div>
                        <div class="word-front" id="text-arti"><?= t('Memuat...', 'Loading...') ?></div>
                        
                        <div class="hint-text">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" /></svg>
                            <?= t('Klik kartu untuk membalik', 'Click the card to flip') ?>
                        </div>
                    </div>

                    <!-- Sisi Belakang (Rusia) -->
                    <div class="card-face card-back">
                        <div class="lang-label-back"><?= t('Bahasa Rusia', 'Russian') ?></div>
                        <div class="word-back" id="text-rusia"><?= t('Memuat...', 'Loading...') ?></div>
                        <div class="transliteration" id="text-baca"><?= t('Memuat...', 'Loading...') ?></div>
                        
                        <!-- Tombol Audio (Stop propagation agar saat klik audio, kartu tidak ikut terbalik) -->
                        <button class="btn-audio" id="btn-audio" onclick="playAudio(event)" title="<?= t('Dengarkan', 'Listen') ?>">
                            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                            </svg>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Kontrol Navigasi Bawah -->
            <div class="card-controls">
                <button class="btn-nav" id="btn-prev" onclick="prevCard()">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    <?= t('Sebelumnya', 'Previous') ?>
                </button>
                <div class="card-counter"><span id="current-count">1</span> / <span id="total-count">0</span></div>
                <button class="btn-nav" id="btn-next" onclick="nextCard()">
                    <?= t('Selanjutnya', 'Next') ?>
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>

        <?php else: ?>
            <div class="empty-state"><?= t('Belum ada kartu flash untuk pelajaran ini.', 'No flashcards for this lesson yet.') ?></div>
        <?php endif; ?>
    </main>
</div>

<!-- SCRIPT LOGIKA FLASHCARD & AUDIO -->
<script>
    // Mengambil data kosakata dari PHP dan mengubahnya jadi objek Javascript
    const kosakata = <?= $json_kosakata ?>;
    let currentIndex = 0;
    const totalCards = kosakata.length;

    // Element DOM
    const flashcard = document.getElementById('flashcard');
    const textArti = document.getElementById('text-arti');
    const textRusia = document.getElementById('text-rusia');
    const textBaca = document.getElementById('text-baca');
    const currentCountEl = document.getElementById('current-count');
    const totalCountEl = document.getElementById('total-count');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnAudio = document.getElementById('btn-audio');

    // Inisialisasi awal jika ada datanya
    if (totalCards > 0) {
        totalCountEl.textContent = totalCards;
        loadCard(currentIndex);
    }

    // Fungsi memuat isi kartu berdasarkan index
    function loadCard(index) {
        // Tutup kartu (kembali ke depan) jika sedang terbuka
        flashcard.classList.remove('flipped');
        
        // Kasih delay dikit biar teks ga ganti saat kartu masih proses muter
        setTimeout(() => {
            textArti.textContent = kosakata[index].arti_indonesia;
            textRusia.textContent = kosakata[index].kata_rusia;
            textBaca.textContent = "[ " + kosakata[index].cara_baca + " ]";
            currentCountEl.textContent = index + 1;
            
            // Atur tombol prev/next mati nyala
            btnPrev.disabled = index === 0;
            btnNext.disabled = index === totalCards - 1;
        }, 150);
    }

    // Fungsi membalik kartu (3D Animation Trigger)
    function flipCard() {
        flashcard.classList.toggle('flipped');
    }

    // Tombol Selanjutnya
    function nextCard() {
        if (currentIndex < totalCards - 1) {
            currentIndex++;
            loadCard(currentIndex);
        }
    }

    // Tombol Sebelumnya
    function prevCard() {
        if (currentIndex > 0) {
            currentIndex--;
            loadCard(currentIndex);
        }
    }

    // Fungsi Audio Web Speech API (Suara Asli Rusia)
    function playAudio(event) {
        // Mencegah klik tombol audio memicu fungsi balik kartu di background
        event.stopPropagation();

        const teksRusia = kosakata[currentIndex].kata_rusia;
        
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel(); // Stop suara yg lagi jalan
            
            let msg = new SpeechSynthesisUtterance();
            msg.text = teksRusia;
            msg.lang = 'ru-RU'; 
            msg.rate = 0.85; 
            
            btnAudio.classList.add('playing');
            msg.onend = function() {
                btnAudio.classList.remove('playing');
            };
            
            window.speechSynthesis.speak(msg);
        } else {
            alert("Maaf, browser Anda tidak mendukung fitur pemutar suara.");
        }
    }
</script>

<?php include __DIR__ . '/partials/target_harian.php'; ?>
</body>
</html>