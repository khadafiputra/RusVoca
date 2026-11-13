<?php
require_once __DIR__ . '/keamanan.php';

// Konfigurasi Database
$host     = "localhost";
$user     = "root";
$password = "";          // Sesuaikan jika MySQL kamu memakai password
$database = "db_rusvoca"; // Sesuaikan dengan nama database di phpMyAdmin

// Membuat Koneksi
$koneksi = mysqli_connect($host, $user, $password, $database);

// Cek Koneksi
if (!$koneksi) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}

// Samakan zona waktu MySQL dengan PHP (WIB) agar NOW()/CURDATE() konsisten.
mysqli_query($koneksi, "SET time_zone = '+07:00'");

// ---------------------------------------------------------------------------
// Penjaga Skema (self-healing)
// Kolom mode_belajar HARUS fleksibel. Pada versi lama kolom ini bertipe ENUM
// ('kosakata','flashcard','kuis_kosakata','game_memori','kuis_mendengarkan','catur_jawa')
// sehingga kode mode baru (mode_memori, mode_tictactoe, mode_speaking, mode_writing)
// ditolak MySQL dengan error "Data truncated for column 'mode_belajar'".
// Jika database di-import ulang memakai skema lama, otomatis diubah ke VARCHAR
// dan data lama dimigrasikan ke kode yang baru.
// ---------------------------------------------------------------------------
try {
    $cek_kolom = mysqli_query($koneksi, "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_progres_belajar' AND COLUMN_NAME = 'mode_belajar'");
    $info_kolom = $cek_kolom ? mysqli_fetch_assoc($cek_kolom) : null;

    if ($info_kolom && strtolower((string)$info_kolom['DATA_TYPE']) === 'enum') {
        mysqli_query($koneksi, "ALTER TABLE tb_progres_belajar MODIFY mode_belajar VARCHAR(50) NOT NULL");
        mysqli_query($koneksi, "UPDATE tb_progres_belajar SET mode_belajar = 'mode_memori' WHERE mode_belajar = 'game_memori'");
        mysqli_query($koneksi, "UPDATE tb_progres_belajar SET mode_belajar = 'mode_tictactoe' WHERE mode_belajar = 'catur_jawa'");
    }

    // -----------------------------------------------------------------------
    // Penjaga skema materi pelajaran (gaya LingoHut)
    // Database lama belum punya kolom materi pada tb_pelajaran dan kolom
    // kelompok/contoh kalimat pada tb_kosakata. Tambahkan otomatis agar
    // halaman materi dan kuis tidak error setelah database di-import ulang.
    // -----------------------------------------------------------------------
    $kolom_tambahan = [
        'tb_pelajaran' => [
            'materi' => "LONGTEXT NULL AFTER deskripsi",
        ],
        'tb_kosakata' => [
            'kelompok'       => "VARCHAR(80) NULL AFTER arti_indonesia",
            'contoh_kalimat' => "VARCHAR(255) NULL AFTER kelompok",
            'arti_contoh'    => "VARCHAR(255) NULL AFTER contoh_kalimat",
        ],
    ];

    foreach ($kolom_tambahan as $tabel => $daftar_kolom) {
        foreach ($daftar_kolom as $nama_kolom => $definisi) {
            $cek = mysqli_query($koneksi, "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tabel' AND COLUMN_NAME = '$nama_kolom'");
            if ($cek && mysqli_num_rows($cek) === 0) {
                mysqli_query($koneksi, "ALTER TABLE $tabel ADD COLUMN `$nama_kolom` $definisi");
            }
        }
    }
} catch (mysqli_sql_exception $e) {
    // Abaikan: aplikasi tetap berjalan walau penyesuaian skema gagal.
}
?>