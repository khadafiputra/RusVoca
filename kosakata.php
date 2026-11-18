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
$nama_user = $_SESSION['nama'] ?? 'Siswa';
$is_admin = pengguna_admin($koneksi);

// Tingkat keahlian untuk tema halaman.
$level       = hitung_level($koneksi, $user_id, $is_admin);
$level_kelas = $level['kelas'];

// =========================================================================================
// LOGIKA SISTEM LEVEL (UNLOCK):
// Cari nomor pelajaran tertinggi di mana KEEMPAT latihannya sudah LULUS (skor >= 60).
// Mode Game Memori & Tic-Tac-Toe sudah dihapus.
// Admin memiliki akses penuh: semua bab langsung terbuka.
// (Disamakan dengan syarat tuntas di detail_pelajaran.php & profil.php)
// =========================================================================================
$pelajaran_terbuka_maks = 1;
if ($is_admin) {
    $pelajaran_terbuka_maks = PHP_INT_MAX;
} else {
    $query_max = mysqli_query($koneksi, "
        SELECT MAX(p.nomor_pelajaran) as max_nomor 
        FROM tb_pelajaran p 
        WHERE (
            SELECT COUNT(DISTINCT pb.mode_belajar) 
            FROM tb_progres_belajar pb 
            WHERE pb.id_pelajaran = p.id_pelajaran 
            AND pb.user_id = '$user_id' 
            AND pb.skor >= 60 
            AND pb.mode_belajar IN ('kuis_kosakata', 'kuis_mendengarkan', 'mode_speaking', 'mode_writing')
        ) = 4
    ");
    if ($query_max) {
        $data_max = mysqli_fetch_assoc($query_max);
        // Pelajaran yang TERBUKA adalah Bab yang sudah lulus 4 LATIHAN DITAMBAH 1 bab ke depannya.
        $pelajaran_terbuka_maks = (isset($data_max['max_nomor']) && $data_max['max_nomor'] !== null) ? ((int)$data_max['max_nomor'] + 1) : 1;
    }
}
// =========================================================================================

// Daftar bab yang sudah TUNTAS: keempat latihan lulus dengan skor minimal 60.
// Dipakai untuk menampilkan tanda "Tuntas" pada kartu bab.
$bab_tuntas = [];
$query_bab_tuntas = mysqli_query($koneksi, "
    SELECT id_pelajaran
    FROM tb_progres_belajar
    WHERE user_id = '$user_id'
      AND skor >= 60
      AND mode_belajar IN ('kuis_kosakata', 'kuis_mendengarkan', 'mode_speaking', 'mode_writing')
    GROUP BY id_pelajaran
    HAVING COUNT(DISTINCT mode_belajar) = 4
");
if ($query_bab_tuntas) {
    while ($baris_tuntas = mysqli_fetch_assoc($query_bab_tuntas)) {
        $bab_tuntas[(int)$baris_tuntas['id_pelajaran']] = true;
    }
}

// Mengambil semua data pelajaran dari tb_pelajaran beserta jumlah kosakatanya
$query_pelajaran = mysqli_query($koneksi, "
    SELECT p.*, (
        SELECT COUNT(*) FROM tb_kosakata k WHERE k.id_pelajaran = p.id_pelajaran
    ) AS jumlah_kata
    FROM tb_pelajaran p
    ORDER BY p.nomor_pelajaran ASC
");

// Warna & huruf hias untuk thumbnail tiap bab (konsisten dengan palet aplikasi).
$warna_bab = [
    '#1D4ED8',
    '#B45309',
    '#047857',
    '#6D28D9',
    '#BE185D',
];
$huruf_bab = ['П', 'Д', 'С', 'Т', 'Ж'];
?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Daftar Pelajaran', 'Lesson List') ?> - RusVoca</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    <link rel="stylesheet" href="assets/css/level-theme.css?v=20261113">
    
    <style>
        :root {
            --primary-blue: #0F3F7A; --bg-light: #F4F7FA; --border-color: #E2E8F0;
            --text-dark: #0F172A; --text-muted: #64748B; --card-bg: #FFFFFF;
            --accent-blue: #2563EB;
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
        .main-content { margin-left: 260px; flex: 1; padding: 40px; max-width: 1200px; }
        .page-header { margin-bottom: 32px; }
        .page-title { font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        .page-subtitle { font-size: 15px; color: var(--text-muted); font-weight: 500; line-height: 1.5; max-width: 800px; }

        .lesson-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }

        .lesson-card {
            background-color: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 20px 22px; text-decoration: none; display: flex; align-items: center;
            gap: 16px; transition: all 0.2s ease-in-out; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .lesson-card:hover { transform: translateY(-4px); border-color: var(--primary-blue); box-shadow: 0 14px 26px rgba(15, 63, 122, 0.12); }
        .lesson-card.is-done { border-left: 4px solid #10B981; }
        .lesson-card.is-done .lesson-title { color: #065F46; }
        .lesson-card.is-done:hover { border-color: #10B981; }
        .lesson-main { display: flex; align-items: center; gap: 15px; flex: 1; min-width: 0; }
        .lesson-thumb {
            width: 56px; height: 56px; border-radius: 15px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 24px; font-weight: 800;
            box-shadow: 0 8px 16px rgba(15, 63, 122, .2);
            position: relative; overflow: hidden;
        }
        .lesson-thumb::after {
            content: ""; position: absolute; width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255, 255, 255, .18); top: -12px; right: -8px;
        }
        .lesson-info { display: flex; flex-direction: column; gap: 6px; min-width: 0; flex: 1; }
        .lesson-number { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .lesson-title { font-size: 16px; font-weight: 800; color: var(--text-dark); }
        .lesson-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 2px; }
        .lesson-card:hover .lesson-title { color: var(--primary-blue); }
        .lesson-arrow { color: var(--text-muted); transition: 0.2s; display: flex; align-items: center; justify-content: center; }
        .lesson-card:hover .lesson-arrow { transform: translateX(5px); color: var(--primary-blue); }

        .lesson-card.locked {
            background-color: #F8FAFC; border-color: #E2E8F0; box-shadow: none; cursor: not-allowed; opacity: 0.8;
        }
        .lesson-card.locked:hover { transform: none; border-color: #E2E8F0; box-shadow: none; }
        .lesson-card.locked .lesson-title { color: var(--text-muted); }
        .lesson-card.locked .lesson-arrow { color: #94A3B8; }
        
        .locked-badge {
            font-size: 10px; font-weight: 700; color: #EF4444; background: #FEE2E2;
            padding: 4px 8px; border-radius: 6px; display: inline-block; margin-top: 8px; width: fit-content;
        }

        .empty-state { grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: #fff; border-radius: 12px; border: 1px dashed var(--border-color); color: var(--text-muted); }

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
                <li class="nav-item"><a href="profil.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= t('Profil Saya', 'My Profile') ?></span></a></li>
                <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>
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
        <div class="rv-hero rv-rise">
            <div class="rv-hero-copy">
                <span class="rv-eyebrow"><span class="dot"></span> <?= t('Jalur belajar', 'Learning path') ?></span>
                <h1><?= t('Pelajaran', 'Russian') ?> <span class="hl"><?= t('Bahasa Rusia', 'Language Lessons') ?></span> <?= t('Bertahap', 'Step by Step') ?></h1>
                <p><?php if ($is_admin): ?><?= t('Mode admin aktif: semua bab terbuka tanpa syarat skor.', 'Admin mode active: all chapters are unlocked without score requirements.') ?><?php else: ?><?= t('Selesaikan', 'Complete') ?> <b><?= t('keempat latihan', 'all four practices') ?></b> <?= t('(Kuis Kosa Kata, Kuis Audio, Speaking, dan Writing) dengan nilai minimal 60 untuk membuka gembok pada materi selanjutnya.', '(Vocabulary Quiz, Listening Quiz, Speaking, and Writing) with a minimum score of 60 to unlock the next material.') ?><?php endif; ?></p>
                <div class="rv-hero-stats">
                    <div class="rv-stat"><b>4</b><span><?= t('Latihan per bab', 'Practices per chapter') ?></span></div>
                    <div class="rv-stat"><b>33</b><span><?= t('Huruf Kiril', 'Cyrillic letters') ?></span></div>
                    <div class="rv-stat"><b>60</b><span><?= t('Skor lulus', 'Passing score') ?></span></div>
                </div>
            </div>
            <div class="rv-hero-art">
                <img src="assets/img/hero-dashboard.svg" alt="Ilustrasi matryoshka dan arsitektur Rusia">
            </div>
        </div>

        <div class="lesson-grid">
            <?php if($query_pelajaran && mysqli_num_rows($query_pelajaran) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($query_pelajaran)): ?>
                    
                    <?php 
                        // Cek apakah nomor bab ini melebihi batas bab yang boleh diakses user
                        $is_locked = $row['nomor_pelajaran'] > $pelajaran_terbuka_maks; 
                        $idx_bab   = ((int)$row['nomor_pelajaran'] - 1) % count($warna_bab);
                        $gaya_bab  = $warna_bab[$idx_bab];
                        $huruf_thumb = $huruf_bab[$idx_bab] ?? 'А';
                    ?>
                    
                    <?php if($is_locked): ?>
                        <!-- KARTU TERKUNCI -->
                        <div class="lesson-card locked rv-rise" title="<?= t('Selesaikan semua latihan di pelajaran sebelumnya untuk membuka', 'Complete all practices in the previous lesson to unlock') ?>">
                            <div class="lesson-main">
                                <div class="lesson-thumb" style="background: #E2E8F0; color: #94A3B8; box-shadow: none;"><?= $huruf_thumb ?></div>
                                <div class="lesson-info">
                                    <span class="lesson-number"><?= t('Pelajaran', 'Lesson') ?> <?= $row['nomor_pelajaran'] ?></span>
                                    <h3 class="lesson-title"><?= htmlspecialchars(materi_t($row['judul_pelajaran'])) ?></h3>
                                    <div class="lesson-meta">
                                        <span class="rv-chip"><?= (int)$row['jumlah_kata'] ?> <?= t('kata', 'words') ?></span>
                                    </div>
                                    <div class="locked-badge"><?= t('Terkunci · selesaikan bab sebelumnya', 'Locked · complete the previous chapter') ?></div>
                                </div>
                            </div>
                            <div class="lesson-arrow">
                                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- KARTU TERBUKA -->
                        <?php $is_tuntas_bab = !empty($bab_tuntas[(int)$row['id_pelajaran']]); ?>
                        <a href="detail_pelajaran.php?id=<?= $row['id_pelajaran'] ?>" class="lesson-card rv-rise <?= $is_tuntas_bab ? 'is-done' : '' ?>">
                            <div class="lesson-main">
                                <div class="lesson-thumb" style="background: <?= $gaya_bab ?>;"><?= $huruf_thumb ?></div>
                                <div class="lesson-info">
                                    <span class="lesson-number"><?= t('Pelajaran', 'Lesson') ?> <?= $row['nomor_pelajaran'] ?></span>
                                    <h3 class="lesson-title"><?= htmlspecialchars(materi_t($row['judul_pelajaran'])) ?></h3>
                                    <div class="lesson-meta">
                                        <span class="rv-chip"><?= (int)$row['jumlah_kata'] ?> <?= t('kata', 'words') ?></span>
                                        <?php if ($is_tuntas_bab): ?>
                                            <span class="rv-chip is-green">
                                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                <?= t('Tuntas', 'Completed') ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (trim((string)($row['materi'] ?? '')) !== ''): ?><span class="rv-chip is-gold"><?= t('Materi', 'Material') ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="lesson-arrow">
                                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                </svg>
                            </div>
                        </a>
                    <?php endif; ?>

                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3><?= t('Belum Ada Pelajaran', 'No Lessons Yet') ?></h3>
                    <p><?= t('Materi pelajaran bahasa Rusia belum ditambahkan ke dalam database.', 'Russian lesson materials have not been added to the database yet.') ?></p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include __DIR__ . '/partials/target_harian.php'; ?>
</body>
</html>