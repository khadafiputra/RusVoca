<?php
$judul  = 'Dashboard Admin';
$active = 'index';

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/auth.php';

/** @var mysqli $koneksi Koneksi database dari config/koneksi.php */
$nama_admin = $_SESSION['nama'] ?? 'Admin';

function hitung(mysqli $koneksi, string $sql): int {
    $q = mysqli_query($koneksi, $sql);
    if (!$q) { return 0; }
    $d = mysqli_fetch_assoc($q);
    return (int)($d['jml'] ?? 0);
}

$label_mode = [
    'kuis_kosakata'     => 'Kuis Kata',
    'kuis_mendengarkan' => 'Kuis Audio',
    'mode_speaking'     => 'Berbicara',
    'mode_writing'      => 'Menulis',
];

$jml_siswa     = hitung($koneksi, "SELECT COUNT(*) AS jml FROM tb_akun WHERE role = 'siswa'");
$jml_admin     = hitung($koneksi, "SELECT COUNT(*) AS jml FROM tb_akun WHERE role = 'admin'");
$jml_pelajaran = hitung($koneksi, "SELECT COUNT(*) AS jml FROM tb_pelajaran");
$jml_kosakata  = hitung($koneksi, "SELECT COUNT(*) AS jml FROM tb_kosakata");
$jml_alfabet   = hitung($koneksi, "SELECT COUNT(*) AS jml FROM tb_alfabet");
$aktivitas_hari= hitung($koneksi, "SELECT COUNT(*) AS jml FROM tb_progres_belajar WHERE DATE(tanggal_akses) = CURDATE() AND mode_belajar IN ('kuis_kosakata','kuis_mendengarkan','mode_speaking','mode_writing')");

// Papan peringkat (top 5 berdasarkan total XP = jumlah skor tertinggi tiap mode)
$leaderboard = [];
$q_lb = mysqli_query($koneksi, "
    SELECT a.id, a.nama, a.email, COALESCE(SUM(sub.max_skor), 0) AS xp
    FROM tb_akun a
    LEFT JOIN (
        SELECT user_id, MAX(skor) AS max_skor
        FROM tb_progres_belajar
        WHERE mode_belajar IN ('kuis_kosakata','kuis_mendengarkan','mode_speaking','mode_writing')
        GROUP BY user_id, id_pelajaran, mode_belajar
    ) sub ON sub.user_id = a.id
    WHERE a.role = 'siswa'
    GROUP BY a.id, a.nama, a.email
    ORDER BY xp DESC
    LIMIT 5
");
if ($q_lb) { while ($r = mysqli_fetch_assoc($q_lb)) { $leaderboard[] = $r; } }

// Aktivitas terbaru
$aktivitas = [];
$q_ak = mysqli_query($koneksi, "
    SELECT a.nama, p.judul_pelajaran, pb.mode_belajar, pb.skor, pb.tanggal_akses
    FROM tb_progres_belajar pb
    LEFT JOIN tb_akun a ON a.id = pb.user_id
    LEFT JOIN tb_pelajaran p ON p.id_pelajaran = pb.id_pelajaran
    WHERE pb.mode_belajar IN ('kuis_kosakata','kuis_mendengarkan','mode_speaking','mode_writing')
    ORDER BY pb.tanggal_akses DESC
    LIMIT 8
");
if ($q_ak) { while ($r = mysqli_fetch_assoc($q_ak)) { $aktivitas[] = $r; } }

include __DIR__ . '/partials/header.php';
?>
<div class="page-head">
    <h1>Panel Admin RusVoca</h1>
    <p>Halo, <?= htmlspecialchars($nama_admin) ?>. Kelola materi, akun, dan pantau nilai siswa di sini.</p>
</div>

<div class="grid">
    <div class="card"><div style="font-size:30px;font-weight:800;color:var(--primary-blue)"><?= $jml_siswa ?></div><div style="color:var(--muted);font-weight:600;font-size:13px">Siswa Terdaftar</div></div>
    <div class="card"><div style="font-size:30px;font-weight:800;color:var(--gold)"><?= $jml_admin ?></div><div style="color:var(--muted);font-weight:600;font-size:13px">Admin</div></div>
    <div class="card"><div style="font-size:30px;font-weight:800;color:#2563EB"><?= $aktivitas_hari ?></div><div style="color:var(--muted);font-weight:600;font-size:13px">Latihan Hari Ini</div></div>
    <div class="card"><div style="font-size:30px;font-weight:800;color:#10B981"><?= $jml_pelajaran ?></div><div style="color:var(--muted);font-weight:600;font-size:13px">Pelajaran</div></div>
    <div class="card"><div style="font-size:30px;font-weight:800;color:#8B5CF6"><?= $jml_kosakata ?></div><div style="color:var(--muted);font-weight:600;font-size:13px">Kosakata</div></div>
    <div class="card"><div style="font-size:30px;font-weight:800;color:#06B6D4"><?= $jml_alfabet ?></div><div style="color:var(--muted);font-weight:600;font-size:13px">Huruf Alfabet</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:4px" class="admin-cols">
    <div class="card">
        <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:16px;"><svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="var(--gold)" stroke-width="1.9" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8v4a4 4 0 01-8 0V4zM8 5H5a2 2 0 002 2h1m8-2h3a2 2 0 01-2 2h-1m-4 5v4m-3 4h6m-6 0a3 3 0 013-3h0a3 3 0 013 3"/></svg> Papan Peringkat (Top 5)</h3>
        <table>
            <tr><th>#</th><th>Siswa</th><th>XP</th></tr>
            <?php if (count($leaderboard) === 0): ?>
                <tr><td colspan="3" style="color:var(--muted)">Belum ada siswa.</td></tr>
            <?php else: foreach ($leaderboard as $i => $u): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($u['nama'] ?? '') ?><br><small style="color:var(--muted)"><?= htmlspecialchars($u['email'] ?? '') ?></small></td>
                <td style="font-weight:700;color:var(--primary-blue)"><?= number_format((int)$u['xp'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

    <div class="card">
        <h3 style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:16px;"><svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="var(--primary-blue)" stroke-width="1.9" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Aktivitas Terbaru</h3>
        <table>
            <tr><th>Siswa</th><th>Mode</th><th>Skor</th><th>Waktu</th></tr>
            <?php if (count($aktivitas) === 0): ?>
                <tr><td colspan="4" style="color:var(--muted)">Belum ada aktivitas.</td></tr>
            <?php else: foreach ($aktivitas as $r):
                $m = $label_mode[$r['mode_belajar']] ?? 'Latihan';
                $lulus = ((int)$r['skor'] >= 60);
            ?>
            <tr>
                <td><?= htmlspecialchars($r['nama'] ?? '(terhapus)') ?></td>
                <td><?= htmlspecialchars($m) ?></td>
                <td style="font-weight:700;color:<?= $lulus ? '#059669' : '#DC2626' ?>"><?= (int)$r['skor'] ?></td>
                <td><small><?= htmlspecialchars(date('d/m H:i', strtotime($r['tanggal_akses']))) ?></small></td>
            </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
