<?php
/**
 * admin/partials/header.php
 * Header + navigasi bersama untuk semua halaman admin.
 * Variabel yang dipakai: $judul (judul tab), $active (menu aktif).
 */
// Tolak akses langsung ke file partial ini.
if (!isset($judul) && !isset($koneksi)) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

if (!isset($judul))  { $judul  = 'Panel Admin'; }
if (!isset($active)) { $active = ''; }
// Nama admin bisa berasal dari halaman pemanggil; sediakan fallback agar aman.
$nama_admin = $nama_admin ?? ($_SESSION['nama'] ?? 'Admin');

$menu = [
    'index'        => ['Dashboard',      'index.php',        'M4 13h6V4H4v9zm10 7h6v-9h-6v9zM4 20h6v-5H4v5zm10-11h6V4h-6v5z'],
    'materi'       => ['Kelola Materi',  'kelola_materi.php','M4 5h16v2H4V5zm0 6h16v2H4v-2zm0 6h10v2H4v-2zM17 14l4 2-4 2v-4z'],
    'pengguna'     => ['Kelola Pengguna','kelola_user.php',  'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z'],
    'nilai'        => ['Rekap Nilai',    'rekap_nilai.php',  'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($judul) ?> - Admin RusVoca</title>
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary-blue:#0F3F7A; --gold:#E09B19; --bg:#F4F7FA; --border:#E2E8F0; --text:#0F172A; --muted:#64748B; }
        * { box-sizing:border-box; margin:0; padding:0; font-family:'Plus Jakarta Sans',sans-serif; }
        body { background:var(--bg); color:var(--text); min-height:100vh; }

        /* TOPBAR */
        .admin-topbar { background:var(--primary-blue); color:#fff; display:flex; align-items:center; gap:24px; padding:0 24px; height:64px; position:sticky; top:0; z-index:50; box-shadow:0 2px 12px rgba(15,63,122,.25); }
        .admin-brand { display:flex; align-items:center; gap:10px; font-weight:800; font-size:18px; letter-spacing:-.3px; }
        .admin-brand img { width:34px; height:34px; object-fit:contain; }
        .admin-brand span { color:var(--gold); }
        .admin-brand small { display:block; font-size:9px; letter-spacing:1.5px; color:rgba(255,255,255,.7); font-weight:700; }
        .admin-nav { display:flex; gap:6px; margin-left:12px; flex-wrap:wrap; }
        .admin-nav a { display:flex; align-items:center; gap:8px; color:rgba(255,255,255,.78); text-decoration:none; font-size:13px; font-weight:700; padding:9px 14px; border-radius:9px; transition:.2s; }
        .admin-nav a:hover { background:rgba(255,255,255,.12); color:#fff; }
        .admin-nav a.active { background:rgba(255,255,255,.18); color:#fff; }
        .admin-nav svg { width:17px; height:17px; fill:currentColor; }
        .admin-user { margin-left:auto; display:flex; align-items:center; gap:12px; font-size:13px; font-weight:700; }
        .admin-user .pill { background:var(--gold); color:#3b2600; font-size:10px; font-weight:800; padding:3px 9px; border-radius:20px; text-transform:uppercase; letter-spacing:.5px; }
        .admin-user a { color:rgba(255,255,255,.85); text-decoration:none; font-weight:700; font-size:13px; }
        .admin-user a:hover { color:#fff; text-decoration:underline; }
        .admin-user .logout-link { background:none; border:none; padding:0; color:rgba(255,255,255,.85); font:inherit; font-weight:700; font-size:13px; cursor:pointer; }
        .admin-user .logout-link:hover { color:#fff; text-decoration:underline; }

        /* LAYOUT */
        .admin-wrap { max-width:1180px; margin:0 auto; padding:28px 24px 60px; }
        .page-head { margin-bottom:22px; }
        .page-head h1 { font-size:24px; font-weight:800; letter-spacing:-.4px; }
        .page-head p { color:var(--muted); font-size:14px; margin-top:4px; }

        /* KOMPONEN UMUM */
        .btn { display:inline-block; padding:10px 18px; border-radius:9px; text-decoration:none; font-weight:700; font-size:13px; background:var(--primary-blue); color:#fff; border:none; cursor:pointer; transition:.2s; }
        .btn:hover { filter:brightness(1.08); }
        .btn.alt { background:var(--gold); color:#3b2600; }
        .btn.ghost { background:#fff; color:var(--primary-blue); border:1px solid var(--border); }
        .btn.danger { background:#EF4444; }
        .btn.small { padding:7px 12px; font-size:12px; border-radius:7px; }
        .card { background:#fff; border:1px solid var(--border); border-radius:14px; padding:20px; margin-bottom:18px; }
        .alert { padding:12px 16px; border-radius:10px; margin-bottom:18px; font-weight:600; font-size:14px; }
        .alert-ok { background:#D1FAE5; border:1px solid #6EE7B7; color:#065F46; }
        .alert-err { background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th,td { border:1px solid var(--border); padding:9px 11px; text-align:left; vertical-align:middle; }
        th { background:#F8FAFC; font-weight:700; }
        input,select,textarea { padding:10px 12px; border:1px solid var(--border); border-radius:9px; font-size:13px; width:100%; margin-bottom:10px; font-family:inherit; }
        input:focus,select:focus,textarea:focus { outline:none; border-color:#2563EB; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        @media (max-width:820px) {
            .admin-nav { display:none; }
            .row { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<header class="admin-topbar">
    <div class="admin-brand">
        <img src="../assets/img/logo.png" alt="RusVoca">
        <div>Rus<span>Voca</span><small>ADMIN PANEL</small></div>
    </div>
    <nav class="admin-nav">
        <?php foreach ($menu as $key => $m): ?>
            <a href="<?= $m[1] ?>" class="<?= $active === $key ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="<?= $m[2] ?>"/></svg><?= $m[0] ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="admin-user">
        <span class="pill">Admin</span>
        <span><?= htmlspecialchars($nama_admin) ?></span>
        <a href="../dashboard.php">Lihat Situs</a>
        <form method="POST" action="../logout.php" style="display:inline;margin:0" onsubmit="return confirm('Keluar dari panel admin?')">
            <?= csrf_field() ?>
            <button type="submit" class="logout-link">Keluar</button>
        </form>
    </div>
</header>
<main class="admin-wrap">
