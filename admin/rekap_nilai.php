<?php
$judul  = 'Rekap Nilai';
$active = 'nilai';

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/auth.php';

/** @var mysqli $koneksi Koneksi database dari config/koneksi.php */
$nama_admin = $_SESSION['nama'] ?? 'Admin';

$modes = [
    'kuis_kosakata'     => 'Kuis Kata',
    'kuis_mendengarkan' => 'Audio',
    'mode_speaking'     => 'Speaking',
    'mode_writing'      => 'Writing',
    // Mode yang sudah dihapus tetap ditampilkan sebagai arsip bila ada datanya.
    'mode_memori'       => 'Arsip Memori',
    'mode_tictactoe'    => 'Arsip TicTacToe',
];

$q_user = mysqli_query($koneksi, "SELECT id, nama, email FROM tb_akun WHERE role = 'siswa' ORDER BY nama ASC");
$users = [];
if ($q_user) { while ($r = mysqli_fetch_assoc($q_user)) { $users[] = $r; } }

$q_pel = mysqli_query($koneksi, "SELECT id_pelajaran, nomor_pelajaran, judul_pelajaran FROM tb_pelajaran ORDER BY nomor_pelajaran ASC");
$pelajaran = [];
if ($q_pel) { while ($r = mysqli_fetch_assoc($q_pel)) { $pelajaran[] = $r; } }

// Skor maksimal per user x pelajaran x mode
$skor = [];
$q = mysqli_query($koneksi, "SELECT user_id, id_pelajaran, mode_belajar, MAX(skor) AS max_skor FROM tb_progres_belajar GROUP BY user_id, id_pelajaran, mode_belajar");
if ($q) { while ($r = mysqli_fetch_assoc($q)) { $skor[$r['user_id']][$r['id_pelajaran']][$r['mode_belajar']] = (int)$r['max_skor']; } }

// ---------------------------------------------------------------------------
// Ekspor CSV (diproses sebelum header HTML)
// ---------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rekap_nilai_rusvoca_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    $head = ['Siswa', 'Email', 'Pelajaran'];
    foreach ($modes as $label) { $head[] = $label; }
    fputcsv($out, $head);
    foreach ($users as $u) {
        foreach ($pelajaran as $p) {
            $row = [$u['nama'], $u['email'], 'Bab ' . (int)$p['nomor_pelajaran'] . ' - ' . $p['judul_pelajaran']];
            foreach ($modes as $kode => $label) {
                $row[] = $skor[$u['id']][$p['id_pelajaran']][$kode] ?? '';
            }
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit();
}

// Ringkasan per siswa (total XP & jumlah mode tuntas)
$ringkasan = [];
foreach ($users as $u) {
    $total_xp = 0; $tuntas = 0;
    foreach ($pelajaran as $p) {
        foreach ($modes as $kode => $label) {
            $nilai = $skor[$u['id']][$p['id_pelajaran']][$kode] ?? null;
            if ($nilai !== null) {
                $total_xp += $nilai;
                if ($nilai >= 60) { $tuntas++; }
            }
        }
    }
    $ringkasan[$u['id']] = ['xp' => $total_xp, 'tuntas' => $tuntas];
}

include __DIR__ . '/partials/header.php';
?>
<div class="page-head">
    <h1>Rekap Nilai Siswa</h1>
    <p>Skor tertinggi tiap mode per bab. Hijau = tuntas (≥ 60), merah = belum tuntas.</p>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <strong>Ringkasan Siswa</strong>
        <a class="btn alt" href="?export=csv" style="display:inline-flex;align-items:center;gap:7px"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v11m0 0l-4-4m4 4l4-4"/></svg> Unduh CSV</a>
    </div>
    <div style="margin-top:14px;overflow-x:auto">
        <table>
            <tr><th>Siswa</th><th>Email</th><th>Total XP</th><th>Mode Tuntas</th></tr>
            <?php if (count($users) === 0): ?>
                <tr><td colspan="4" style="color:var(--muted)">Belum ada akun siswa.</td></tr>
            <?php else: foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nama'] ?? '') ?></td>
                    <td><small><?= htmlspecialchars($u['email'] ?? '') ?></small></td>
                    <td style="font-weight:700;color:var(--primary-blue)"><?= number_format($ringkasan[$u['id']]['xp'] ?? 0, 0, ',', '.') ?></td>
                    <td><?= (int)($ringkasan[$u['id']]['tuntas'] ?? 0) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>

<?php foreach ($pelajaran as $p): ?>
<div class="card">
    <h3 style="margin-bottom:12px;font-size:16px;">Bab <?= (int)$p['nomor_pelajaran'] ?> - <?= htmlspecialchars($p['judul_pelajaran'] ?? '') ?></h3>
    <div style="overflow-x:auto">
    <table>
        <tr><th>Siswa</th><?php foreach ($modes as $label): ?><th><?= $label ?></th><?php endforeach; ?></tr>
        <?php if (count($users) === 0): ?>
            <tr><td colspan="<?= count($modes) + 1 ?>" style="color:var(--muted)">Belum ada akun siswa.</td></tr>
        <?php else: foreach ($users as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['nama'] ?? '') ?><br><small style="color:var(--muted)"><?= htmlspecialchars($u['email'] ?? '') ?></small></td>
            <?php foreach ($modes as $kode => $label):
                $nilai = $skor[$u['id']][$p['id_pelajaran']][$kode] ?? null;
                if ($nilai === null) { $bg = '#fff'; $fg = '#CBD5E1'; $txt = '-'; }
                elseif ($nilai >= 60) { $bg = '#D1FAE5'; $fg = '#065F46'; $txt = $nilai; }
                else { $bg = '#FEE2E2'; $fg = '#991B1B'; $txt = $nilai; }
            ?>
            <td style="background:<?= $bg ?>;color:<?= $fg ?>;font-weight:<?= $nilai === null ? '400' : '700' ?>;text-align:center"><?= $txt ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; endif; ?>
    </table>
    </div>
</div>
<?php endforeach; ?>
<?php if (count($pelajaran) === 0): ?>
    <div class="card" style="color:var(--muted)">Belum ada pelajaran.</div>
<?php endif; ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
