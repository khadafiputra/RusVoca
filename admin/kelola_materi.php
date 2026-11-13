<?php
$judul  = 'Kelola Materi';
$active = 'materi';

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/auth.php';

/** @var mysqli $koneksi Koneksi database dari config/koneksi.php */
$nama_admin = $_SESSION['nama'] ?? 'Admin';

$pesan      = '';
$pesan_tipe = 'ok';

// Semua aksi POST (termasuk hapus) wajib membawa token CSRF yang sah.
$csrf_ok = ($_SERVER['REQUEST_METHOD'] !== 'POST') || csrf_valid();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_ok) {
    $pesan = 'Sesi keamanan sudah berakhir. Muat ulang halaman lalu coba lagi.';
    $pesan_tipe = 'err';
}

// --- Tambah pelajaran ---
if ($csrf_ok && isset($_POST['tambah_pelajaran'])) {
    $nomor = (int)($_POST['nomor_pelajaran'] ?? 0);
    $judul_p = mysqli_real_escape_string($koneksi, trim($_POST['judul_pelajaran'] ?? ''));
    $desk  = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi'] ?? ''));
    $materi = mysqli_real_escape_string($koneksi, trim($_POST['materi'] ?? ''));
    if ($nomor > 0 && $judul_p !== '') {
        if (mysqli_query($koneksi, "INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi) VALUES ('$nomor', '$judul_p', '$desk', '$materi')")) {
            $pesan = 'Pelajaran berhasil ditambahkan.';
        } else {
            $pesan = 'Gagal menambah pelajaran: ' . htmlspecialchars(mysqli_error($koneksi)); $pesan_tipe = 'err';
        }
    } else {
        $pesan = 'Nomor dan judul pelajaran wajib diisi.'; $pesan_tipe = 'err';
    }
}
// --- Edit pelajaran (judul, deskripsi, materi) ---
if ($csrf_ok && isset($_POST['simpan_pelajaran'])) {
    $id    = (int)($_POST['id_pelajaran'] ?? 0);
    $judul_p = mysqli_real_escape_string($koneksi, trim($_POST['judul_pelajaran'] ?? ''));
    $desk  = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi'] ?? ''));
    $materi = mysqli_real_escape_string($koneksi, trim($_POST['materi'] ?? ''));
    if ($id > 0 && $judul_p !== '') {
        if (mysqli_query($koneksi, "UPDATE tb_pelajaran SET judul_pelajaran = '$judul_p', deskripsi = '$desk', materi = '$materi' WHERE id_pelajaran = $id")) {
            $pesan = 'Materi pelajaran berhasil diperbarui.';
        } else {
            $pesan = 'Gagal memperbarui pelajaran: ' . htmlspecialchars(mysqli_error($koneksi)); $pesan_tipe = 'err';
        }
    } else {
        $pesan = 'Judul pelajaran wajib diisi.'; $pesan_tipe = 'err';
    }
}
// --- Hapus pelajaran (beserta kosakatanya) ---
if ($csrf_ok && isset($_POST['aksi_hapus_pelajaran'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        mysqli_query($koneksi, "DELETE FROM tb_kosakata WHERE id_pelajaran = $id");
        mysqli_query($koneksi, "DELETE FROM tb_pelajaran WHERE id_pelajaran = $id");
    }
    header("Location: kelola_materi.php");
    exit();
}
// --- Tambah kosakata ---
if ($csrf_ok && isset($_POST['tambah_kosakata'])) {
    $idp  = (int)($_POST['id_pelajaran'] ?? 0);
    $ru   = mysqli_real_escape_string($koneksi, trim($_POST['kata_rusia'] ?? ''));
    $art  = mysqli_real_escape_string($koneksi, trim($_POST['arti_indonesia'] ?? ''));
    $baca = mysqli_real_escape_string($koneksi, trim($_POST['cara_baca'] ?? ''));
    $kel   = mysqli_real_escape_string($koneksi, trim($_POST['kelompok'] ?? ''));
    $contoh = mysqli_real_escape_string($koneksi, trim($_POST['contoh_kalimat'] ?? ''));
    $arti_contoh = mysqli_real_escape_string($koneksi, trim($_POST['arti_contoh'] ?? ''));
    if ($idp > 0 && $ru !== '' && $art !== '') {
        if (mysqli_query($koneksi, "INSERT INTO tb_kosakata (id_pelajaran, kata_rusia, arti_indonesia, cara_baca, kelompok, contoh_kalimat, arti_contoh) VALUES ('$idp', '$ru', '$art', '$baca', '$kel', '$contoh', '$arti_contoh')")) {
            $pesan = 'Kosakata berhasil ditambahkan.';
        } else {
            $pesan = 'Gagal menambah kosakata: ' . htmlspecialchars(mysqli_error($koneksi)); $pesan_tipe = 'err';
        }
    } else {
        $pesan = 'Kata Rusia dan artinya wajib diisi.'; $pesan_tipe = 'err';
    }
}
// --- Hapus kosakata ---
if ($csrf_ok && isset($_POST['aksi_hapus_kosakata'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        mysqli_query($koneksi, "DELETE FROM tb_kosakata WHERE id_kosakata = $id");
    }
    header("Location: kelola_materi.php");
    exit();
}
// --- Tambah alfabet (contoh_kata & arti_contoh WAJIB diisi) ---
if ($csrf_ok && isset($_POST['tambah_alfabet'])) {
    $huruf  = mysqli_real_escape_string($koneksi, trim($_POST['huruf_kiril'] ?? ''));
    $baca   = mysqli_real_escape_string($koneksi, trim($_POST['cara_baca'] ?? ''));
    $contoh = mysqli_real_escape_string($koneksi, trim($_POST['contoh_kata'] ?? ''));
    $arti   = mysqli_real_escape_string($koneksi, trim($_POST['arti_contoh'] ?? ''));
    if ($huruf !== '' && $baca !== '' && $contoh !== '' && $arti !== '') {
        if (mysqli_query($koneksi, "INSERT INTO tb_alfabet (huruf_kiril, cara_baca, contoh_kata, arti_contoh) VALUES ('$huruf', '$baca', '$contoh', '$arti')")) {
            $pesan = 'Huruf alfabet berhasil ditambahkan.';
        } else {
            $pesan = 'Gagal menambah alfabet: ' . htmlspecialchars(mysqli_error($koneksi)); $pesan_tipe = 'err';
        }
    } else {
        $pesan = 'Huruf, cara baca, contoh kata, dan artinya wajib diisi.'; $pesan_tipe = 'err';
    }
}
// --- Hapus alfabet ---
if ($csrf_ok && isset($_POST['aksi_hapus_alfabet'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        mysqli_query($koneksi, "DELETE FROM tb_alfabet WHERE id = $id");
    }
    header("Location: kelola_materi.php");
    exit();
}

$q_pelajaran = mysqli_query($koneksi, "SELECT * FROM tb_pelajaran ORDER BY nomor_pelajaran ASC");
$list_pelajaran = [];
if ($q_pelajaran) { while ($r = mysqli_fetch_assoc($q_pelajaran)) { $list_pelajaran[] = $r; } }

$q_kosakata = mysqli_query($koneksi, "SELECT k.*, p.judul_pelajaran FROM tb_kosakata k LEFT JOIN tb_pelajaran p ON k.id_pelajaran = p.id_pelajaran ORDER BY k.id_pelajaran ASC, k.id_kosakata ASC");
$list_kosakata = [];
if ($q_kosakata) { while ($r = mysqli_fetch_assoc($q_kosakata)) { $list_kosakata[] = $r; } }

$q_alfabet = mysqli_query($koneksi, "SELECT * FROM tb_alfabet ORDER BY id ASC");
$list_alfabet = [];
if ($q_alfabet) { while ($r = mysqli_fetch_assoc($q_alfabet)) { $list_alfabet[] = $r; } }

// Data pelajaran yang sedang diedit (materi, judul, deskripsi).
$edit_pelajaran = null;
if (isset($_GET['edit_pelajaran'])) {
    $id_edit = (int)$_GET['edit_pelajaran'];
    $q_edit = mysqli_query($koneksi, "SELECT * FROM tb_pelajaran WHERE id_pelajaran = $id_edit");
    if ($q_edit && mysqli_num_rows($q_edit) > 0) { $edit_pelajaran = mysqli_fetch_assoc($q_edit); }
}

include __DIR__ . '/partials/header.php';
?>
<div class="page-head">
    <h1>Kelola Materi</h1>
    <p>Tambah / hapus pelajaran, kosakata, dan huruf alfabet Kiril.</p>
</div>

<?php if ($pesan !== ''): ?>
    <div class="alert <?= $pesan_tipe === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= $pesan ?></div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom:12px;font-size:16px;">Tambah Pelajaran</h3>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="row">
            <input type="number" name="nomor_pelajaran" placeholder="Nomor (mis. 1)" min="1" required>
            <input type="text" name="judul_pelajaran" placeholder="Judul pelajaran" required>
        </div>
        <textarea name="deskripsi" placeholder="Deskripsi singkat"></textarea>
        <textarea name="materi" placeholder="Isi materi pelajaran (boleh HTML ringan: <p>, <strong>, <ul><li>, <h4>)" style="min-height:130px;"></textarea>
        <button class="btn" type="submit" name="tambah_pelajaran">Tambah Pelajaran</button>
    </form>
</div>

<?php if ($edit_pelajaran): ?>
<div class="card" style="border:2px solid var(--gold);">
    <h3 style="margin-bottom:12px;font-size:16px;">Edit Materi Pelajaran: <?= htmlspecialchars($edit_pelajaran['judul_pelajaran'] ?? '') ?></h3>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="id_pelajaran" value="<?= (int)$edit_pelajaran['id_pelajaran'] ?>">
        <input type="text" name="judul_pelajaran" value="<?= htmlspecialchars($edit_pelajaran['judul_pelajaran'] ?? '') ?>" required>
        <textarea name="deskripsi" placeholder="Deskripsi singkat"><?= htmlspecialchars($edit_pelajaran['deskripsi'] ?? '') ?></textarea>
        <textarea name="materi" placeholder="Isi materi pelajaran" style="min-height:220px;"><?= htmlspecialchars($edit_pelajaran['materi'] ?? '') ?></textarea>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn" type="submit" name="simpan_pelajaran">Simpan Materi</button>
            <a class="btn ghost" href="kelola_materi.php">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom:12px;font-size:16px;">Daftar Pelajaran (<?= count($list_pelajaran) ?>)</h3>
    <div style="overflow-x:auto;">
    <table>
        <tr><th style="width:60px">No</th><th>Judul</th><th>Deskripsi</th><th style="width:110px">Materi</th><th style="width:150px">Aksi</th></tr>
        <?php foreach ($list_pelajaran as $p): ?>
        <tr>
            <td><?= (int)$p['nomor_pelajaran'] ?></td>
            <td><?= htmlspecialchars($p['judul_pelajaran'] ?? '') ?></td>
            <td><?= htmlspecialchars($p['deskripsi'] ?? '') ?></td>
            <td><?= trim((string)($p['materi'] ?? '')) !== '' ? 'Ada' : '<span style="color:#B45309;font-weight:700;">Belum</span>' ?></td>
            <td style="white-space:nowrap;">
                <a class="btn small alt" href="?edit_pelajaran=<?= (int)$p['id_pelajaran'] ?>">Edit Materi</a>
                <form method="POST" action="" style="display:inline;margin:0" onsubmit="return confirm('Hapus pelajaran ini beserta kosakatanya?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$p['id_pelajaran'] ?>">
                    <button class="btn small danger" type="submit" name="aksi_hapus_pelajaran" value="1">Hapus</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (count($list_pelajaran) === 0): ?><tr><td colspan="5" style="color:var(--muted)">Belum ada pelajaran.</td></tr><?php endif; ?>
    </table>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom:12px;font-size:16px;">Tambah Kosakata</h3>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <select name="id_pelajaran" required>
            <option value="">-- Pilih Pelajaran --</option>
            <?php foreach ($list_pelajaran as $p): ?>
            <option value="<?= (int)$p['id_pelajaran'] ?>">Bab <?= (int)$p['nomor_pelajaran'] ?> - <?= htmlspecialchars($p['judul_pelajaran'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
        <div class="row">
            <input type="text" name="kata_rusia" placeholder="Kata Rusia (Cyrillic)" required>
            <input type="text" name="arti_indonesia" placeholder="Arti Indonesia" required>
        </div>
        <div class="row">
            <input type="text" name="cara_baca" placeholder="Cara baca / transliterasi">
            <input type="text" name="kelompok" placeholder="Kelompok (mis. Sapaan, Warna Dasar)">
        </div>
        <div class="row">
            <input type="text" name="contoh_kalimat" placeholder="Contoh kalimat Rusia (opsional)">
            <input type="text" name="arti_contoh" placeholder="Arti contoh kalimat (opsional)">
        </div>
        <button class="btn" type="submit" name="tambah_kosakata">Tambah Kosakata</button>
    </form>
</div>

<div class="card">
    <h3 style="margin-bottom:12px;font-size:16px;">Daftar Kosakata (<?= count($list_kosakata) ?>)</h3>
    <div style="overflow-x:auto;">
    <table>
        <tr><th>Pelajaran</th><th>Kelompok</th><th>Kata Rusia</th><th>Arti</th><th>Cara Baca</th><th>Contoh Kalimat</th><th style="width:90px">Aksi</th></tr>
        <?php foreach ($list_kosakata as $k): ?>
        <tr>
            <td><?= htmlspecialchars($k['judul_pelajaran'] ?? '-') ?></td>
            <td><?= htmlspecialchars($k['kelompok'] ?? '-') ?></td>
            <td><?= htmlspecialchars($k['kata_rusia'] ?? '') ?></td>
            <td><?= htmlspecialchars($k['arti_indonesia'] ?? '') ?></td>
            <td><?= htmlspecialchars($k['cara_baca'] ?? '') ?></td>
            <td><?= htmlspecialchars($k['contoh_kalimat'] ?? '') ?></td>
            <td>
                <form method="POST" action="" style="display:inline;margin:0" onsubmit="return confirm('Hapus kosakata ini?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$k['id_kosakata'] ?>">
                    <button class="btn small danger" type="submit" name="aksi_hapus_kosakata" value="1">Hapus</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (count($list_kosakata) === 0): ?><tr><td colspan="7" style="color:var(--muted)">Belum ada kosakata.</td></tr><?php endif; ?>
    </table>
    </div>
</div>

<div class="card">
    <h3 style="margin-bottom:12px;font-size:16px;">Tambah Huruf Alfabet</h3>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="row">
            <input type="text" name="huruf_kiril" placeholder="Huruf Kiril (mis. А)" required>
            <input type="text" name="cara_baca" placeholder="Cara baca (mis. A)" required>
        </div>
        <div class="row">
            <input type="text" name="contoh_kata" placeholder="Contoh kata (mis. Автобус)" required>
            <input type="text" name="arti_contoh" placeholder="Arti contoh (mis. Bus)" required>
        </div>
        <button class="btn" type="submit" name="tambah_alfabet">Tambah Huruf</button>
    </form>
</div>

<div class="card">
    <h3 style="margin-bottom:12px;font-size:16px;">Daftar Alfabet (<?= count($list_alfabet) ?>)</h3>
    <table>
        <tr><th style="width:80px">Huruf</th><th style="width:110px">Cara Baca</th><th>Contoh Kata</th><th>Arti</th><th style="width:90px">Aksi</th></tr>
        <?php foreach ($list_alfabet as $a): ?>
        <tr>
            <td style="font-size:18px;font-weight:800;color:var(--primary-blue)"><?= htmlspecialchars($a['huruf_kiril'] ?? '') ?></td>
            <td><?= htmlspecialchars($a['cara_baca'] ?? '') ?></td>
            <td><?= htmlspecialchars($a['contoh_kata'] ?? '') ?></td>
            <td><?= htmlspecialchars($a['arti_contoh'] ?? '') ?></td>
            <td>
                <form method="POST" action="" style="display:inline;margin:0" onsubmit="return confirm('Hapus huruf ini?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button class="btn small danger" type="submit" name="aksi_hapus_alfabet" value="1">Hapus</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (count($list_alfabet) === 0): ?><tr><td colspan="5" style="color:var(--muted)">Belum ada data alfabet.</td></tr><?php endif; ?>
    </table>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
