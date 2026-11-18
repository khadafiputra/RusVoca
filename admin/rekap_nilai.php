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
];

// ---------------------------------------------------------------------------
// Filter: siswa, bab, dan rentang tanggal
// ---------------------------------------------------------------------------
$filter_siswa  = (int)($_GET['siswa'] ?? 0);
$filter_bab    = (int)($_GET['bab'] ?? 0);
$filter_dari   = trim($_GET['dari'] ?? '');
$filter_sampai = trim($_GET['sampai'] ?? '');

// Validasi tanggal: hanya format Y-m-d yang sah yang dipakai.
function tanggal_sah(string $t): bool {
    if ($t === '') { return false; }
    $d = DateTime::createFromFormat('Y-m-d', $t);
    return $d !== false && $d->format('Y-m-d') === $t;
}
if (!tanggal_sah($filter_dari))   { $filter_dari = ''; }
if (!tanggal_sah($filter_sampai)) { $filter_sampai = ''; }

$where_skor = [];
if ($filter_siswa > 0)     { $where_skor[] = "user_id = $filter_siswa"; }
if ($filter_bab > 0)       { $where_skor[] = "id_pelajaran = $filter_bab"; }
$daftar_mode = implode(',', array_map(static function ($m) { return "'" . $m . "'"; }, array_keys($modes)));
$where_skor[] = "mode_belajar IN ($daftar_mode)";
if ($filter_dari !== '')   { $where_skor[] = "tanggal_akses >= '$filter_dari 00:00:00'"; }
if ($filter_sampai !== '') { $where_skor[] = "tanggal_akses <= '$filter_sampai 23:59:59'"; }
$sql_where = count($where_skor) > 0 ? 'WHERE ' . implode(' AND ', $where_skor) : '';

$sql_user = "SELECT id, nama, email FROM tb_akun WHERE role = 'siswa'";
if ($filter_siswa > 0) { $sql_user .= " AND id = $filter_siswa"; }
$sql_user .= " ORDER BY nama ASC";
$q_user = mysqli_query($koneksi, $sql_user);
$users = [];
if ($q_user) { while ($r = mysqli_fetch_assoc($q_user)) { $users[] = $r; } }

$sql_pel = "SELECT id_pelajaran, nomor_pelajaran, judul_pelajaran FROM tb_pelajaran";
if ($filter_bab > 0) { $sql_pel .= " WHERE id_pelajaran = $filter_bab"; }
$sql_pel .= " ORDER BY nomor_pelajaran ASC";
$q_pel = mysqli_query($koneksi, $sql_pel);
$pelajaran = [];
if ($q_pel) { while ($r = mysqli_fetch_assoc($q_pel)) { $pelajaran[] = $r; } }

// Daftar lengkap untuk mengisi pilihan filter (tidak ikut terfilter).
$q_semua_user = mysqli_query($koneksi, "SELECT id, nama FROM tb_akun WHERE role = 'siswa' ORDER BY nama ASC");
$pilihan_siswa = [];
if ($q_semua_user) { while ($r = mysqli_fetch_assoc($q_semua_user)) { $pilihan_siswa[] = $r; } }

$q_semua_pel = mysqli_query($koneksi, "SELECT id_pelajaran, nomor_pelajaran, judul_pelajaran FROM tb_pelajaran ORDER BY nomor_pelajaran ASC");
$pilihan_bab = [];
if ($q_semua_pel) { while ($r = mysqli_fetch_assoc($q_semua_pel)) { $pilihan_bab[] = $r; } }

// Skor maksimal per user x pelajaran x mode (mengikuti filter).
$skor = [];
$q = mysqli_query($koneksi, "SELECT user_id, id_pelajaran, mode_belajar, MAX(skor) AS max_skor FROM tb_progres_belajar $sql_where GROUP BY user_id, id_pelajaran, mode_belajar");
if ($q) { while ($r = mysqli_fetch_assoc($q)) { $skor[$r['user_id']][$r['id_pelajaran']][$r['mode_belajar']] = (int)$r['max_skor']; } }

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

// Keterangan filter aktif.
$info_filter = [];
if ($filter_siswa > 0)     { $info_filter[] = 'siswa: ' . ($users[0]['nama'] ?? '-'); }
if ($filter_bab > 0)       { $info_filter[] = 'bab: ' . (isset($pelajaran[0]) ? 'Bab ' . (int)$pelajaran[0]['nomor_pelajaran'] . ' - ' . $pelajaran[0]['judul_pelajaran'] : '-'); }
if ($filter_dari !== '')   { $info_filter[] = 'dari ' . date('d/m/Y', strtotime($filter_dari)); }
if ($filter_sampai !== '') { $info_filter[] = 'sampai ' . date('d/m/Y', strtotime($filter_sampai)); }
$teks_filter = count($info_filter) > 0 ? 'Filter aktif - ' . implode(' | ', $info_filter) : 'Menampilkan seluruh data.';

// Parameter filter yang dipakai ulang untuk tautan ekspor.
$params_filter = [];
if ($filter_siswa > 0)     { $params_filter['siswa'] = $filter_siswa; }
if ($filter_bab > 0)       { $params_filter['bab'] = $filter_bab; }
if ($filter_dari !== '')   { $params_filter['dari'] = $filter_dari; }
if ($filter_sampai !== '') { $params_filter['sampai'] = $filter_sampai; }
$link_pdf = 'rekap_nilai.php?' . http_build_query(array_merge($params_filter, ['export' => 'pdf']));

// ---------------------------------------------------------------------------
// Ekspor PDF Ringkasan Siswa (diproses sebelum header HTML)
// ---------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../config/pdf.php';

    $baris_pdf = [];
    foreach ($users as $u) {
        $baris_pdf[] = [
            'nama'   => (string)($u['nama'] ?? ''),
            'email'  => (string)($u['email'] ?? ''),
            'xp'     => (int)($ringkasan[$u['id']]['xp'] ?? 0),
            'tuntas' => (int)($ringkasan[$u['id']]['tuntas'] ?? 0),
        ];
    }

    $pdf = pdf_rekap_siswa($baris_pdf, $teks_filter);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="ringkasan_siswa_rusvoca_' . date('Ymd_His') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit();
}

include __DIR__ . '/partials/header.php';
?>
<div class="page-head">
    <h1>Rekap Nilai Siswa</h1>
    <p>Skor tertinggi tiap mode per bab. Hijau = tuntas (≥ 60), merah = belum tuntas.</p>
</div>

<div class="card">
    <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin:0">
        <div style="flex:1;min-width:170px;">
            <label for="f-siswa" style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px;">Siswa</label>
            <select id="f-siswa" name="siswa" style="margin:0;">
                <option value="">Semua siswa</option>
                <?php foreach ($pilihan_siswa as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $filter_siswa === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:170px;">
            <label for="f-bab" style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px;">Bab</label>
            <select id="f-bab" name="bab" style="margin:0;">
                <option value="">Semua bab</option>
                <?php foreach ($pilihan_bab as $b): ?>
                <option value="<?= (int)$b['id_pelajaran'] ?>" <?= $filter_bab === (int)$b['id_pelajaran'] ? 'selected' : '' ?>>Bab <?= (int)$b['nomor_pelajaran'] ?> - <?= htmlspecialchars($b['judul_pelajaran']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width:150px;">
            <label for="f-dari" style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px;">Dari tanggal</label>
            <input type="date" id="f-dari" name="dari" value="<?= htmlspecialchars($filter_dari) ?>" style="margin:0;">
        </div>
        <div style="min-width:150px;">
            <label for="f-sampai" style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px;">Sampai tanggal</label>
            <input type="date" id="f-sampai" name="sampai" value="<?= htmlspecialchars($filter_sampai) ?>" style="margin:0;">
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn" type="submit">Terapkan</button>
            <a class="btn ghost" href="rekap_nilai.php">Reset</a>
        </div>
    </form>
    <p style="margin-top:12px;font-size:12px;color:var(--muted);">
        <?= htmlspecialchars($teks_filter) ?>
        <?php if ($filter_dari !== '' || $filter_sampai !== ''): ?>
        <br>Total XP dihitung dari skor pada rentang tanggal tersebut.
        <?php endif; ?>
    </p>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <strong>Ringkasan Siswa</strong>
        <a class="btn alt" href="<?= htmlspecialchars($link_pdf) ?>" style="display:inline-flex;align-items:center;gap:7px"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v11m0 0l-4-4m4 4l4-4"/></svg> Unduh PDF</a>
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
