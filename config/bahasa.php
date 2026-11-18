<?php
/**
 * config/bahasa.php
 * ---------------------------------------------------------------------------
 * Pilihan bahasa antarmuka: Indonesia (default) atau Inggris.
 * Materi pelajaran tetap memakai bahasa sumbernya; label dan petunjuk aplikasi
 * yang diterjemahkan.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/keamanan.php';

if (isset($_SESSION['bahasa']) && !in_array($_SESSION['bahasa'], ['id', 'en'], true)) {
    $_SESSION['bahasa'] = 'id';
}

$BAHASA = $_SESSION['bahasa'] ?? 'id';

/** Kode bahasa aktif. */
function bahasa(): string
{
    global $BAHASA;
    return $BAHASA === 'en' ? 'en' : 'id';
}

/**
 * Terjemahkan teks antarmuka.
 * Contoh: t('Kosakata', 'Vocabulary')
 */
function t(string $id, ?string $en = null): string
{
    if (bahasa() === 'en' && $en !== null) { return $en; }
    return $id;
}

/** Atribut lang untuk elemen <html>. */
function bahasa_html(): string
{
    return bahasa();
}

/** Terjemahan ringan untuk konten yang sudah dikenal. */
function materi_t(string $teks): string
{
    if (bahasa() !== 'en' || $teks === '') { return $teks; }

    static $peta = [
        // Pelajaran
        'Bertemu Seseorang' => 'Meeting Someone',
        'Sapaan dasar dalam bahasa Rusia' => 'Basic greetings in Russian',
        'Iya dan Terima Kasih' => 'Yes and Thank You',
        'Belajar merespons dengan sopan' => 'Learning to respond politely',
        'Angka 1–10' => 'Numbers 1–10',
        'Hitung dari nol sampai seratus' => 'Count from zero to one hundred',
        'Warna' => 'Colors',
        'Kenali nama warna dan penggunaannya' => 'Learn color names and how to use them',
        'Keluarga' => 'Family',
        'Perkenalkan anggota keluarga' => 'Introduce family members',
        'Makanan & Minuman' => 'Food & Drink',
        'Makanan, minuman, dan frasa di meja makan' => 'Food, drinks, and table phrases',
        'Hewan' => 'Animals',
        'Nama hewan dan suaranya' => 'Animal names and their sounds',
        'Anggota Tubuh' => 'Body Parts',
        'Bagian tubuh dan cara menyebut keluhan' => 'Body parts and how to describe complaints',
        'Waktu & Hari' => 'Time & Days',
        'Jam, hari, dan penanda waktu' => 'Hours, days, and time markers',
        'Cuaca & Musim' => 'Weather & Seasons',
        'Cuaca, musim, dan percakapan ringan' => 'Weather, seasons, and small talk',
        'Arah & Perjalanan' => 'Directions & Travel',
        'Bertanya arah dan mengenal tempat umum' => 'Ask for directions and know public places',
        'Kata Kerja Sehari-hari' => 'Everyday Verbs',
        'Kata kerja dasar untuk kegiatan sehari-hari' => 'Basic verbs for daily activities',
        // Kelompok kosakata dalam materi
        'Sapaan' => 'Greetings',
        'Perkenalan & Pamitan' => 'Introductions & Farewells',
        'Jawaban Sopan' => 'Polite Answers',
        'Ungkapan Tambahan' => 'Extra Expressions',
        'Angka Pokok' => 'Core Numbers',
        'Angka Tambahan' => 'Extra Numbers',
        'Warna Dasar' => 'Basic Colors',
        'Warna Lain' => 'Other Colors',
        'Keluarga Inti' => 'Immediate Family',
        'Keluarga Besar' => 'Extended Family',
        'Makanan' => 'Food',
        'Minuman & Lainnya' => 'Drinks & Others',
        'Hewan Rumah' => 'Farm Animals',
        'Hewan Lain' => 'Other Animals',
        'Kepala & Wajah' => 'Head & Face',
        'Tubuh & Anggota' => 'Body & Limbs',
        'Satuan Waktu' => 'Time Units',
        'Penanda Waktu' => 'Time Markers',
        'Cuaca' => 'Weather',
        'Suhu & Musim' => 'Temperature & Seasons',
        'Arah' => 'Directions',
        'Tempat Umum' => 'Public Places',
        'Kata Kerja Dasar' => 'Basic Verbs',
        'Kata Kerja Harian' => 'Daily Verbs',
        'Kosakata' => 'Vocabulary',
        // Kosakata
        'Halo (Informal)' => 'Hello (Informal)',
        'Halo (Formal)' => 'Hello (Formal)',
        'Selamat pagi' => 'Good morning',
        'Apa kabar?' => 'How are you?',
        'Halo' => 'Hello',
        'Selamat pagi' => 'Good morning',
        'Hari' => 'Day',
        'Objek' => 'Object',
        // Tingkatan
        'Pemula' => 'Beginner',
        'Menengah' => 'Intermediate',
        'Mahir' => 'Advanced',
        'Legenda' => 'Legend',
        // Mode dan status
        'Kuis Kosa Kata' => 'Vocabulary Quiz',
        'Kuis Audio' => 'Listening Quiz',
        'Latihan Berbicara' => 'Speaking Practice',
        'Latihan Menulis' => 'Writing Practice',
        'Latihan' => 'Practice',
        'Siswa' => 'Student',
        '(Pelajaran dihapus)' => '(Lesson deleted)',
    ];

    return $peta[$teks] ?? $teks;
}

/**
 * Label waktu relatif, mis. "3 jam yang lalu" / "3 hours ago".
 */
function waktu_relatif(string $datetime, bool $dengan_kemarin = true): string
{
    $waktu = strtotime($datetime);
    if ($waktu === false) { return '-'; }
    $selisih = max(0, time() - $waktu);

    if ($selisih < 60) { return t('Baru saja', 'Just now'); }

    $kondisi = [
        12 * 30 * 24 * 60 * 60 => ['tahun', 'year'],
        30 * 24 * 60 * 60      => ['bulan', 'month'],
        24 * 60 * 60           => ['hari', 'day'],
        60 * 60                => ['jam', 'hour'],
        60                     => ['menit', 'minute'],
    ];

    foreach ($kondisi as $detik => $satuan) {
        $nilai = $selisih / $detik;
        if ($nilai < 1) { continue; }

        $jumlah = (int) round($nilai);

        if ($dengan_kemarin && $satuan[0] === 'hari' && $jumlah === 1) {
            return t('Kemarin', 'Yesterday');
        }

        if (bahasa() === 'en') {
            return $jumlah . ' ' . $satuan[1] . ($jumlah > 1 ? 's' : '') . ' ago';
        }
        return $jumlah . ' ' . $satuan[0] . ' yang lalu';
    }

    return t('Baru saja', 'Just now');
}

/**
 * Tanggal absolut yang mengikuti bahasa aktif.
 * Contoh: 8 November 2026 / 8 November 2026 (nama bulan ikut bahasa).
 */
function format_tanggal(string $datetime, bool $dengan_jam = false): string
{
    $waktu = strtotime($datetime);
    if ($waktu === false) { return '-'; }

    static $bulan_id = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
    static $bulan_en = [
        1 => 'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    $bulan = bahasa() === 'en' ? $bulan_en : $bulan_id;
    $teks  = date('j', $waktu) . ' ' . $bulan[(int) date('n', $waktu)] . ' ' . date('Y', $waktu);

    return $dengan_jam ? $teks . ', ' . date('H:i', $waktu) : $teks;
}

/** URL halaman saat ini tanpa parameter lang lama. */
function url_sekarang(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
    $uri = preg_replace('/([?&])lang=(id|en)(&|$)/', '$1', $uri);
    $uri = rtrim($uri, '?&');
    return $uri !== '' ? $uri : 'dashboard.php';
}

/**
 * Tombol pilihan bahasa.
 * $posisi: 'sidebar' atau 'floating'.
 */
function tombol_bahasa(string $posisi = 'sidebar'): string
{
    $aktif = bahasa();
    if ($posisi === 'floating') { $kelas = 'rv-lang rv-lang-floating'; }
    elseif ($posisi === 'inline') { $kelas = 'rv-lang rv-lang-inline'; }
    else { $kelas = 'rv-lang rv-lang-sidebar'; }
    $redirect = htmlspecialchars(url_sekarang(), ENT_QUOTES, 'UTF-8');

    $html  = '<form method="post" action="ganti_bahasa.php" class="' . $kelas . '" aria-label="' . t('Pilih bahasa', 'Choose language') . '">';
    $html .= csrf_field();
    $html .= '<input type="hidden" name="redirect" value="' . $redirect . '">';
    $html .= '<button type="submit" name="lang" value="id" class="' . ($aktif === 'id' ? 'is-active' : '') . '" aria-pressed="' . ($aktif === 'id' ? 'true' : 'false') . '">ID</button>';
    $html .= '<button type="submit" name="lang" value="en" class="' . ($aktif === 'en' ? 'is-active' : '') . '" aria-pressed="' . ($aktif === 'en' ? 'true' : 'false') . '">EN</button>';
    $html .= '</form>';
    return $html;
}
