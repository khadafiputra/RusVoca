<?php
$judul  = 'Kelola Kuis';
$active = 'kuis';

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/bank_soal.php';

/** @var mysqli $koneksi Koneksi database dari config/koneksi.php */
$nama_admin = $_SESSION['nama'] ?? 'Admin';

pastikan_tabel_bank_soal($koneksi);

$pesan      = '';
$pesan_tipe = 'ok';

// Semua aksi POST wajib membawa token CSRF yang sah.
$csrf_ok = ($_SERVER['REQUEST_METHOD'] !== 'POST') || csrf_valid();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_ok) {
    $pesan = 'Sesi keamanan sudah berakhir. Muat ulang halaman lalu coba lagi.';
    $pesan_tipe = 'err';
}

// --- Tambah soal ---
if ($csrf_ok && isset($_POST['tambah_soal'])) {
    $idp        = (int)($_POST['id_pelajaran'] ?? 0);
    $pertanyaan = mysqli_real_escape_string($koneksi, trim($_POST['pertanyaan'] ?? ''));
    $benar      = mysqli_real_escape_string($koneksi, trim($_POST['jawaban_benar'] ?? ''));
    $s1         = mysqli_real_escape_string($koneksi, trim($_POST['pilihan_salah_1'] ?? ''));
    $s2         = mysqli_real_escape_string($koneksi, trim($_POST['pilihan_salah_2'] ?? ''));
    $s3         = mysqli_real_escape_string($koneksi, trim($_POST['pilihan_salah_3'] ?? ''));

    if ($idp > 0 && $pertanyaan !== '' && $benar !== '') {
        $sql = "INSERT INTO tb_bank_soal
                    (id_pelajaran, pertanyaan, jawaban_benar, pilihan_salah_1, pilihan_salah_2, pilihan_salah_3)
                VALUES ($idp, '$pertanyaan', '$benar', '$s1', '$s2', '$s3')";
        if (mysqli_query($koneksi, $sql)) {
            $pesan = 'Soal berhasil ditambahkan ke bank soal.';
        } else {
            $pesan = 'Gagal menambah soal: ' . htmlspecialchars(mysqli_error($koneksi));
            $pesan_tipe = 'err';
        }
    } else {
        $pesan = 'Bab, pertanyaan, dan jawaban benar wajib diisi.';
        $pesan_tipe = 'err';
    }
}

// --- Edit soal ---
if ($csrf_ok && isset($_POST['simpan_soal'])) {
    $id         = (int)($_POST['id_soal'] ?? 0);
    $idp        = (int)($_POST['id_pelajaran'] ?? 0);
    $pertanyaan = mysqli_real_escape_string($koneksi, trim($_POST['pertanyaan'] ?? ''));
    $benar      = mysqli_real_escape_string($koneksi, trim($_POST['jawaban_benar'] ?? ''));
    $s1         = mysqli_real_escape_string($koneksi, trim($_POST['pilihan_salah_1'] ?? ''));
    $s2         = mysqli_real_escape_string($koneksi, trim($_POST['pilihan_salah_2'] ?? ''));
    $s3         = mysqli_real_escape_string($koneksi, trim($_POST['pilihan_salah_3'] ?? ''));

    if ($id > 0 && $idp > 0 && $pertanyaan !== '' && $benar !== '') {
        $sql = "UPDATE tb_bank_soal SET
                    id_pelajaran = $idp,
                    pertanyaan = '$pertanyaan',
                    jawaban_benar = '$benar',
                    pilihan_salah_1 = '$s1',
                    pilihan_salah_2 = '$s2',
                    pilihan_salah_3 = '$s3'
                WHERE id_soal = $id";
        if (mysqli_query($koneksi, $sql)) {
            $pesan = 'Soal berhasil diperbarui.';
        } else {
            $pesan = 'Gagal memperbarui soal: ' . htmlspecialchars(mysqli_error($koneksi));
            $pesan_tipe = 'err';
        }
    } else {
        $pesan = 'Bab, pertanyaan, dan jawaban benar wajib diisi.';
        $pesan_tipe = 'err';
    }
}

// --- Hapus soal ---
if ($csrf_ok && isset($_POST['aksi_hapus_soal'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        mysqli_query($koneksi, "DELETE FROM tb_bank_soal WHERE id_soal = $id");
    }
    header('Location: kelola_kuis.php');
    exit();
}

$q_pelajaran = mysqli_query($koneksi, "SELECT * FROM tb_pelajaran ORDER BY nomor_pelajaran ASC");
$list_pelajaran = [];
if ($q_pelajaran) { while ($r = mysqli_fetch_assoc($q_pelajaran)) { $list_pelajaran[] = $r; } }

$filter_bab = (int)($_GET['bab'] ?? 0);
$where_bab  = $filter_bab > 0 ? "WHERE b.id_pelajaran = $filter_bab" : '';
$q_soal = mysqli_query($koneksi, "
    SELECT b.*, p.nomor_pelajaran, p.judul_pelajaran
    FROM tb_bank_soal b
    LEFT JOIN tb_pelajaran p ON p.id_pelajaran = b.id_pelajaran
    $where_bab
    ORDER BY b.id_pelajaran ASC, b.id_soal ASC
");
$list_soal = [];
if ($q_soal) { while ($r = mysqli_fetch_assoc($q_soal)) { $list_soal[] = $r; } }

// Soal yang sedang diedit.
$edit_soal = null;
if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];
    $q_edit = mysqli_query($koneksi, "SELECT * FROM tb_bank_soal WHERE id_soal = $id_edit");
    if ($q_edit && mysqli_num_rows($q_edit) > 0) { $edit_soal = mysqli_fetch_assoc($q_edit); }
}

include __DIR__ . '/partials/header.php';
?>
<div class="page-head">
    <h1>Kelola Kuis</h1>
    <p>Isi bank soal kuis per bab. Soal ini dipakai oleh Kuis Kata dan Kuis Audio. Bila bank soal suatu bab kosong, kuis otomatis memakai soal dari kosakata.</p>
</div>

<?php if ($pesan !== ''): ?>
    <div class="alert <?= $pesan_tipe === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= $pesan ?></div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom:12px;font-size:16px;">Tambah Soal</h3>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <select name="id_pelajaran" required>
            <option value="">-- Pilih Bab --</option>
            <?php foreach ($list_pelajaran as $p): ?>
            <option value="<?= (int)$p['id_pelajaran'] ?>">Bab <?= (int)$p['nomor_pelajaran'] ?> - <?= htmlspecialchars($p['judul_pelajaran'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
        <div class="row">
            <input type="text" name="pertanyaan" placeholder="Pertanyaan (kata Rusia)" required>
            <input type="text" name="jawaban_benar" placeholder="Jawaban benar (arti Indonesia)" required>
        </div>
        <div class="row">
            <input type="text" name="pilihan_salah_1" placeholder="Pilihan salah 1">
            <input type="text" name="pilihan_salah_2" placeholder="Pilihan salah 2">
        </div>
        <input type="text" name="pilihan_salah_3" placeholder="Pilihan salah 3">
        <button class="btn" type="submit" name="tambah_soal">Tambah Soal</button>
    </form>
</div>

<?php if ($edit_soal): ?>
<div class="card" style="border:2px solid var(--gold);">
    <h3 style="margin-bottom:12px;font-size:16px;">Edit Soal</h3>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="id_soal" value="<?= (int)$edit_soal['id_soal'] ?>">
        <select name="id_pelajaran" required>
            <option value="">-- Pilih Bab --</option>
            <?php foreach ($list_pelajaran as $p): ?>
            <option value="<?= (int)$p['id_pelajaran'] ?>" <?= (int)$p['id_pelajaran'] === (int)$edit_soal['id_pelajaran'] ? 'selected' : '' ?>>Bab <?= (int)$p['nomor_pelajaran'] ?> - <?= htmlspecialchars($p['judul_pelajaran'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
        <div class="row">
            <input type="text" name="pertanyaan" value="<?= htmlspecialchars($edit_soal['pertanyaan'] ?? '') ?>" placeholder="Pertanyaan (kata Rusia)" required>
            <input type="text" name="jawaban_benar" value="<?= htmlspecialchars($edit_soal['jawaban_benar'] ?? '') ?>" placeholder="Jawaban benar (arti Indonesia)" required>
        </div>
        <div class="row">
            <input type="text" name="pilihan_salah_1" value="<?= htmlspecialchars($edit_soal['pilihan_salah_1'] ?? '') ?>" placeholder="Pilihan salah 1">
            <input type="text" name="pilihan_salah_2" value="<?= htmlspecialchars($edit_soal['pilihan_salah_2'] ?? '') ?>" placeholder="Pilihan salah 2">
        </div>
        <input type="text" name="pilihan_salah_3" value="<?= htmlspecialchars($edit_soal['pilihan_salah_3'] ?? '') ?>" placeholder="Pilihan salah 3">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn" type="submit" name="simpan_soal">Simpan Soal</button>
            <a class="btn ghost" href="kelola_kuis.php">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <h3 style="font-size:16px;">Bank Soal (<?= count($list_soal) ?>)</h3>
        <form method="GET" action="" style="margin:0;display:flex;gap:8px;align-items:center;">
            <select name="bab" style="margin:0;min-width:220px;">
                <option value="">Semua bab</option>
                <?php foreach ($list_pelajaran as $p): ?>
                <option value="<?= (int)$p['id_pelajaran'] ?>" <?= $filter_bab === (int)$p['id_pelajaran'] ? 'selected' : '' ?>>Bab <?= (int)$p['nomor_pelajaran'] ?> - <?= htmlspecialchars($p['judul_pelajaran'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn small" type="submit">Filter</button>
        </form>
    </div>
    <div style="overflow-x:auto;">
    <table>
        <tr><th>Bab</th><th>Pertanyaan</th><th>Jawaban Benar</th><th>Pilihan Salah</th><th style="width:140px">Aksi</th></tr>
        <?php foreach ($list_soal as $s): ?>
        <tr>
            <td><?= (int)($s['nomor_pelajaran'] ?? 0) ?> - <?= htmlspecialchars($s['judul_pelajaran'] ?? '-') ?></td>
            <td><?= htmlspecialchars($s['pertanyaan'] ?? '') ?></td>
            <td><?= htmlspecialchars($s['jawaban_benar'] ?? '') ?></td>
            <td>
                <?php
                $salah = [];
                for ($i = 1; $i <= 3; $i++) {
                    $v = trim((string)($s['pilihan_salah_' . $i] ?? ''));
                    if ($v !== '') { $salah[] = $v; }
                }
                echo htmlspecialchars(implode(', ', $salah));
                ?>
            </td>
            <td style="white-space:nowrap;">
                <a class="btn small alt" href="?edit=<?= (int)$s['id_soal'] ?>">Edit</a>
                <form method="POST" action="" style="display:inline;margin:0" onsubmit="return confirm('Hapus soal ini dari bank soal?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$s['id_soal'] ?>">
                    <button class="btn small danger" type="submit" name="aksi_hapus_soal" value="1">Hapus</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (count($list_soal) === 0): ?><tr><td colspan="5" style="color:var(--muted)">Belum ada soal pada bank soal.</td></tr><?php endif; ?>
    </table>
    </div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
