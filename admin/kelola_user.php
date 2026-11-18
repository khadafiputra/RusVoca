<?php
$judul  = 'Kelola Pengguna';
$active = 'pengguna';

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/auth.php';

/** @var mysqli $koneksi Koneksi database dari config/koneksi.php */
$nama_admin = $_SESSION['nama'] ?? 'Admin';

$pesan     = '';
$pesan_tipe = 'ok';

// Semua aksi POST wajib memiliki token CSRF yang sah.
$csrf_ok = ($_SERVER['REQUEST_METHOD'] !== 'POST') || csrf_valid();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_ok) {
    $pesan = 'Sesi keamanan sudah berakhir. Muat ulang halaman lalu coba lagi.';
    $pesan_tipe = 'err';
}

$id_saya   = (int)($_SESSION['user_id'] ?? 0);
$hari_ini  = date('Y-m-d');

function jml_admin(mysqli $koneksi): int {
    $q = mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM tb_akun WHERE role = 'admin'");
    if (!$q) { return 0; }
    $d = mysqli_fetch_assoc($q);
    return (int)($d['jml'] ?? 0);
}

// ---------------------------------------------------------------------------
// AKSI: Ubah hak akses (role)
// ---------------------------------------------------------------------------
if ($csrf_ok && isset($_POST['aksi_ubah_role'])) {
    $id   = (int)($_POST['id'] ?? 0);
    $role = ($_POST['role'] ?? 'siswa') === 'admin' ? 'admin' : 'siswa';

    $q = mysqli_query($koneksi, "SELECT role FROM tb_akun WHERE id = $id");
    $d = $q ? mysqli_fetch_assoc($q) : null;

    if (!$d) {
        $pesan = 'Akun tidak ditemukan.';
        $pesan_tipe = 'err';
    } elseif ($id === $id_saya) {
        $pesan = 'Kamu tidak bisa mengubah hak akses akunmu sendiri.';
        $pesan_tipe = 'err';
    } elseif ($d['role'] === 'admin' && $role === 'siswa' && jml_admin($koneksi) <= 1) {
        $pesan = 'Minimal harus ada satu admin. Ubah admin lain menjadi admin dulu.';
        $pesan_tipe = 'err';
    } elseif (mysqli_query($koneksi, "UPDATE tb_akun SET role = '$role' WHERE id = $id")) {
        $pesan = 'Hak akses berhasil diubah menjadi ' . strtoupper($role) . '.';
    } else {
        $pesan = 'Gagal mengubah hak akses.';
        $pesan_tipe = 'err';
    }
}

// ---------------------------------------------------------------------------
// AKSI: Reset password
// ---------------------------------------------------------------------------
if ($csrf_ok && isset($_POST['aksi_reset_password'])) {
    $id  = (int)($_POST['id'] ?? 0);
    $baru = $_POST['password_baru'] ?? '';

    if ($id <= 0 || strlen($baru) < 6) {
        $pesan = 'Password baru minimal 6 karakter.';
        $pesan_tipe = 'err';
    } else {
        $hash = password_hash($baru, PASSWORD_DEFAULT);
        $hash_aman = mysqli_real_escape_string($koneksi, $hash);
        if (mysqli_query($koneksi, "UPDATE tb_akun SET password = '$hash_aman' WHERE id = $id")) {
            $pesan = 'Password akun berhasil direset.';
        } else {
            $pesan = 'Gagal mereset password.';
            $pesan_tipe = 'err';
        }
    }
}

// ---------------------------------------------------------------------------
// AKSI: Edit profil (nama, email, target harian)
// ---------------------------------------------------------------------------
if ($csrf_ok && isset($_POST['simpan_user'])) {
    $id     = (int)($_POST['id'] ?? 0);
    $nama   = trim($_POST['nama'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $target = (int)($_POST['target_waktu'] ?? 15);

    // Target harian yang dikenal aplikasi hanya 10, 15, dan 30 menit.
    if (!in_array($target, [10, 15, 30], true)) { $target = 15; }

    $nama_aman  = mysqli_real_escape_string($koneksi, $nama);
    $email_aman = mysqli_real_escape_string($koneksi, $email);

    $q_cek = mysqli_query($koneksi, "SELECT id FROM tb_akun WHERE email = '$email_aman' AND id <> $id");
    $email_pakai = ($q_cek && mysqli_num_rows($q_cek) > 0);

    if ($id <= 0) {
        $pesan = 'Akun tidak ditemukan.';
        $pesan_tipe = 'err';
    } elseif ($nama === '') {
        $pesan = 'Nama wajib diisi.';
        $pesan_tipe = 'err';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan = 'Format email tidak valid.';
        $pesan_tipe = 'err';
    } elseif ($email_pakai) {
        $pesan = 'Email sudah dipakai akun lain.';
        $pesan_tipe = 'err';
    } elseif (mysqli_query($koneksi, "UPDATE tb_akun SET nama = '$nama_aman', email = '$email_aman', target_waktu = '$target' WHERE id = $id")) {
        if ($id === $id_saya) {
            $_SESSION['nama'] = $nama;
        }
        $pesan = 'Profil pengguna berhasil diperbarui.';
    } else {
        $pesan = 'Gagal memperbarui profil pengguna.';
        $pesan_tipe = 'err';
    }
}

// ---------------------------------------------------------------------------
// AKSI: Hapus akun
// ---------------------------------------------------------------------------
if ($csrf_ok && isset($_POST['aksi_hapus_user'])) {
    $id = (int)($_POST['id'] ?? 0);
    $q = mysqli_query($koneksi, "SELECT role FROM tb_akun WHERE id = $id");
    $d = $q ? mysqli_fetch_assoc($q) : null;

    if (!$d) {
        $pesan = 'Akun tidak ditemukan.';
        $pesan_tipe = 'err';
    } elseif ($id === $id_saya) {
        $pesan = 'Kamu tidak bisa menghapus akunmu sendiri.';
        $pesan_tipe = 'err';
    } elseif ($d['role'] === 'admin' && jml_admin($koneksi) <= 1) {
        $pesan = 'Minimal harus ada satu admin. Tidak bisa menghapus admin terakhir.';
        $pesan_tipe = 'err';
    } elseif (mysqli_query($koneksi, "DELETE FROM tb_akun WHERE id = $id")) {
        $pesan = 'Akun berhasil dihapus beserta seluruh riwayat belajarnya.';
    } else {
        $pesan = 'Gagal menghapus akun.';
        $pesan_tipe = 'err';
    }
}

// ---------------------------------------------------------------------------
// Ambil daftar pengguna (+ total XP & jumlah aktivitas)
// ---------------------------------------------------------------------------
$cari = trim($_GET['cari'] ?? '');
$where = '';
if ($cari !== '') {
    $cari_aman = mysqli_real_escape_string($koneksi, $cari);
    $where = "WHERE a.nama LIKE '%$cari_aman%' OR a.email LIKE '%$cari_aman%'";
}

$daftar = [];
$q_user = mysqli_query($koneksi, "
    SELECT a.id, a.nama, a.email, a.role, a.target_waktu, a.tanggal_daftar,
           COALESCE(x.total_xp, 0) AS total_xp,
           COALESCE(x.jml, 0) AS jml_aktivitas
    FROM tb_akun a
    LEFT JOIN (
        SELECT user_id, SUM(max_skor) AS total_xp, COUNT(*) AS jml
        FROM (
            SELECT user_id, id_pelajaran, mode_belajar, MAX(skor) AS max_skor
            FROM tb_progres_belajar
            WHERE mode_belajar IN ('kuis_kosakata','kuis_mendengarkan','mode_speaking','mode_writing')
            GROUP BY user_id, id_pelajaran, mode_belajar
        ) t
        GROUP BY user_id
    ) x ON x.user_id = a.id
    $where
    ORDER BY (a.role = 'admin') DESC, a.nama ASC
");
if ($q_user) { while ($r = mysqli_fetch_assoc($q_user)) { $daftar[] = $r; } }

// Data pengguna yang sedang diedit (dari ?edit_user=ID atau setelah submit form).
$edit_user = null;
$id_edit_user = 0;
if (isset($_GET['edit_user'])) {
    $id_edit_user = (int)$_GET['edit_user'];
} elseif (isset($_POST['simpan_user'])) {
    $id_edit_user = (int)($_POST['id'] ?? 0);
}
if ($id_edit_user > 0) {
    $q_edit = mysqli_query($koneksi, "SELECT id, nama, email, role, target_waktu FROM tb_akun WHERE id = $id_edit_user");
    if ($q_edit && mysqli_num_rows($q_edit) > 0) {
        $edit_user = mysqli_fetch_assoc($q_edit);
    }
    // Bila validasi gagal, pertahankan isian terakhir agar admin tidak mengetik ulang.
    if ($edit_user && $pesan_tipe === 'err' && isset($_POST['simpan_user'])) {
        $target_post = (int)($_POST['target_waktu'] ?? $edit_user['target_waktu']);
        $edit_user['nama']         = trim($_POST['nama'] ?? $edit_user['nama']);
        $edit_user['email']        = trim($_POST['email'] ?? $edit_user['email']);
        $edit_user['target_waktu'] = in_array($target_post, [10, 15, 30], true) ? $target_post : 15;
    }
}

$total_admin = jml_admin($koneksi);

include __DIR__ . '/partials/header.php';
?>
<div class="page-head">
    <h1>Kelola Pengguna &amp; Hak Akses</h1>
    <p>Atur hak akses, edit profil dan target harian, reset password, serta hapus akun bila perlu.</p>
</div>

<?php if ($pesan !== ''): ?>
    <div class="alert <?= $pesan_tipe === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= htmlspecialchars($pesan) ?></div>
<?php endif; ?>

<?php if ($edit_user): ?>
<div class="card" style="border:2px solid var(--gold);">
    <h3 style="margin-bottom:12px;font-size:16px;">Edit Profil: <?= htmlspecialchars($edit_user['nama'] ?? '') ?></h3>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$edit_user['id'] ?>">
        <div class="row">
            <input type="text" name="nama" value="<?= htmlspecialchars($edit_user['nama'] ?? '') ?>" placeholder="Nama lengkap" required>
            <input type="email" name="email" value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>" placeholder="Email" required>
        </div>
        <label style="display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:4px;">Target belajar harian</label>
        <select name="target_waktu">
            <?php foreach ([10, 15, 30] as $menit): ?>
            <option value="<?= $menit ?>" <?= (int)($edit_user['target_waktu'] ?? 15) === $menit ? 'selected' : '' ?>><?= $menit ?> menit / hari</option>
            <?php endforeach; ?>
        </select>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn" type="submit" name="simpan_user">Simpan Profil</button>
            <a class="btn ghost" href="kelola_user.php">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="" style="display:flex;gap:10px;align-items:center;margin:0">
        <input type="text" name="cari" value="<?= htmlspecialchars($cari) ?>" placeholder="Cari nama atau email..." style="margin:0;max-width:320px">
        <button class="btn" type="submit">Cari</button>
        <?php if ($cari !== ''): ?><a class="btn ghost" href="kelola_user.php">Reset</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <table>
        <tr>
            <th>Nama</th><th>Email</th><th>Hak Akses</th><th>Target</th>
            <th>XP</th><th>Aktivitas</th><th>Terdaftar</th><th style="min-width:340px">Aksi</th>
        </tr>
        <?php if (count($daftar) === 0): ?>
            <tr><td colspan="8" style="color:var(--muted)">Tidak ada pengguna.</td></tr>
        <?php else: foreach ($daftar as $u):
            $is_saya   = ((int)$u['id'] === $id_saya);
            $is_admin  = ($u['role'] === 'admin');
            $badge_bg  = $is_admin ? '#FEF3C7' : '#E0F2FE';
            $badge_fg  = $is_admin ? '#92400E' : '#075985';
        ?>
        <tr>
            <td>
                <?= htmlspecialchars($u['nama'] ?? '') ?>
                <?php if ($is_saya): ?><br><small style="color:var(--gold);font-weight:700">(kamu)</small><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
            <td>
                <span style="background:<?= $badge_bg ?>;color:<?= $badge_fg ?>;padding:4px 10px;border-radius:20px;font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
                    <?= $is_admin ? 'Admin' : 'Siswa' ?>
                </span>
            </td>
            <td><?= (int)($u['target_waktu'] ?? 15) ?> mnt</td>
            <td style="font-weight:700;color:var(--primary-blue)"><?= number_format((int)$u['total_xp'], 0, ',', '.') ?></td>
            <td><?= (int)$u['jml_aktivitas'] ?></td>
            <td><small><?= !empty($u['tanggal_daftar']) ? htmlspecialchars(date('d/m/Y', strtotime($u['tanggal_daftar']))) : '-' ?></small></td>
            <td style="white-space:nowrap;">
                <a class="btn small alt" href="?edit_user=<?= (int)$u['id'] ?>">Edit</a>
                <?php if (!$is_saya): ?>
                <form method="POST" action="" style="display:inline"
                      onsubmit="return confirm('Ubah hak akses pengguna ini?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="role" value="<?= $is_admin ? 'siswa' : 'admin' ?>">
                    <button class="btn small <?= $is_admin ? 'ghost' : 'alt' ?>" type="submit" name="aksi_ubah_role" value="1">
                        <?= $is_admin ? 'Jadikan Siswa' : 'Jadikan Admin' ?>
                    </button>
                </form>

                <button class="btn small ghost" type="button" onclick="bukaReset(<?= (int)$u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nama'] ?? '')) ?>')">Reset Password</button>

                <form method="POST" action="" style="display:inline"
                      onsubmit="return confirm('HAPUS akun ini beserta seluruh riwayatnya? Tindakan ini permanen.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button class="btn small danger" type="submit" name="aksi_hapus_user" value="1">Hapus</button>
                </form>
                <?php else: ?>
                <small style="color:var(--muted);display:block;margin-top:6px;">Role &amp; password akun sendiri tidak bisa diubah di sini</small>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; endif; ?>
    </table>
</div>

<!-- Modal reset password -->
<div id="modal-reset" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:100;align-items:center;justify-content:center;">
    <div class="card" style="max-width:400px;width:92%;margin:0">
        <h3 style="margin-bottom:6px;font-size:17px;">Reset Password</h3>
        <p style="color:var(--muted);font-size:13px;margin-bottom:14px;">Atur password baru untuk <strong id="reset-nama"></strong>.</p>
        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="reset-id">
            <input type="text" name="password_baru" placeholder="Password baru (min. 6 karakter)" minlength="6" required autocomplete="new-password">
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn ghost" onclick="tutupReset()">Batal</button>
                <button type="submit" class="btn" name="aksi_reset_password" value="1">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function bukaReset(id, nama) {
        document.getElementById('reset-id').value = id;
        document.getElementById('reset-nama').textContent = nama;
        document.getElementById('modal-reset').style.display = 'flex';
        document.querySelector('#modal-reset input[name=password_baru]').focus();
    }
    function tutupReset() {
        document.getElementById('modal-reset').style.display = 'none';
    }
    document.getElementById('modal-reset').addEventListener('click', function (e) {
        if (e.target === this) tutupReset();
    });
</script>
<?php include __DIR__ . '/partials/footer.php'; ?>
