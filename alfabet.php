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

$nama_user = $_SESSION['nama'] ?? 'Siswa';
$user_id   = (int)($_SESSION['user_id'] ?? 0);

// Tingkat keahlian untuk tema halaman.
$is_admin    = pengguna_admin($koneksi);
$level       = hitung_level($koneksi, $user_id, $is_admin);
$level_kelas = $level['kelas'];

// ==========================================================================
// DATA ALFABET
// ==========================================================================
$query_alfabet = mysqli_query($koneksi, "SELECT * FROM tb_alfabet ORDER BY id ASC");
$data_alfabet_mentah = [];
if ($query_alfabet) {
    while ($row = mysqli_fetch_assoc($query_alfabet)) {
        $data_alfabet_mentah[] = $row;
    }
}

// Peta huruf kecil (PHP CLI bisa saja tanpa mbstring, jadi tidak bergantung mb_strtolower).
$peta_kecil = [
    'А' => 'а', 'Б' => 'б', 'В' => 'в', 'Г' => 'г', 'Д' => 'д', 'Е' => 'е', 'Ё' => 'ё',
    'Ж' => 'ж', 'З' => 'з', 'И' => 'и', 'Й' => 'й', 'К' => 'к', 'Л' => 'л', 'М' => 'м',
    'Н' => 'н', 'О' => 'о', 'П' => 'п', 'Р' => 'р', 'С' => 'с', 'Т' => 'т', 'У' => 'у',
    'Ф' => 'ф', 'Х' => 'х', 'Ц' => 'ц', 'Ч' => 'ч', 'Ш' => 'ш', 'Щ' => 'щ', 'Ъ' => 'ъ',
    'Ы' => 'ы', 'Ь' => 'ь', 'Э' => 'э', 'Ю' => 'ю', 'Я' => 'я',
];

$vokal = ['А', 'Е', 'Ё', 'И', 'О', 'У', 'Ы', 'Э', 'Ю', 'Я'];
$tanda = ['Ъ', 'Ь'];

$grup_meta = [
    'latin_sama' => [
        'judul' => t('Kembaran Latin', 'Latin Lookalikes'),
        'sub'   => t('Bentuk & bunyi hampir sama', 'Similar shape and sound'),
        'desc'  => t('Huruf-huruf ini paling bersahabat untuk pemula karena bentuk dan bunyinya mirip huruf Latin. Lima di antaranya — А, К, М, О, Т — menjadi dasar teknik Akamoto.', 'These letters are the friendliest for beginners because their shape and sound resemble Latin letters. Five of them — А, К, М, О, Т — form the basis of the Akamoto technique.'),
        'kelas' => 'g-blue',
    ],
    'latin_trap' => [
        'judul' => t('Mirip Latin, Beda Bunyi', 'Latin Look, Different Sound'),
        'sub'   => t('Jangan tertipu bentuknya', 'Do not be fooled by the shape'),
        'desc'  => t('Bentuknya terlihat familiar, tetapi bunyinya berbeda. Ini kelompok yang paling sering membuat pemula salah baca. Waspadai poin palsu seperti В, Н, Р, dan Х.', 'They look familiar, but sound different. This is the group that most often makes beginners misread. Watch out for false friends such as В, Н, Р, and Х.'),
        'kelas' => 'g-gold',
    ],
    'unik' => [
        'judul' => t('Bentuk Khas Kiril', 'Distinctly Cyrillic'),
        'sub'   => t('Tidak ada padanan Latin', 'No Latin equivalent'),
        'desc'  => t('Huruf-huruf ini punya bentuk khas yang perlu dilatih satu per satu. Banyak di antaranya menghasilkan bunyi yang tidak ada di bahasa Indonesia.', 'These letters have distinct shapes that need to be practiced one by one. Many produce sounds that do not exist in Indonesian.'),
        'kelas' => 'g-pink',
    ],
];

$grup_huruf = [
    'А' => 'latin_sama', 'К' => 'latin_sama', 'М' => 'latin_sama', 'О' => 'latin_sama', 'Т' => 'latin_sama',
    'В' => 'latin_trap', 'Е' => 'latin_trap', 'Ё' => 'latin_trap', 'Н' => 'latin_trap',
    'Р' => 'latin_trap', 'С' => 'latin_trap', 'У' => 'latin_trap', 'Х' => 'latin_trap',
];

$alfabet = [];
foreach ($data_alfabet_mentah as $row) {
    $raw = trim((string)($row['huruf_kiril'] ?? ''));
    $pecah = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $besar = $pecah[0] ?? '';
    $kecil = $pecah[1] ?? ($peta_kecil[$besar] ?? '');
    if ($besar === '') { continue; }

    $baca = (string)($row['cara_baca'] ?? '');
    $ucap = $besar;
    if ($besar === 'Ь') {
        $baca = t('Tanda lunak', 'Soft sign');
        $ucap = 'мягкий знак';
    } elseif ($besar === 'Ъ') {
        $baca = t('Tanda keras', 'Hard sign');
        $ucap = 'твёрдый знак';
    }

    $tipe = in_array($besar, $vokal, true) ? 'vokal' : (in_array($besar, $tanda, true) ? 'tanda' : 'konsonan');
    $grup = $grup_huruf[$besar] ?? 'unik';

    $alfabet[] = [
        'besar' => $besar,
        'kecil' => $kecil,
        'baca'  => $baca,
        'tipe'  => $tipe,
        'grup'  => $grup,
        'ucap'  => $ucap,
    ];
}

$grup_isi = ['latin_sama' => [], 'latin_trap' => [], 'unik' => []];
foreach ($alfabet as $a) {
    if (isset($grup_isi[$a['grup']])) { $grup_isi[$a['grup']][] = $a; }
}

$jumlah_vokal = 0;
$jumlah_konsonan = 0;
$jumlah_tanda = 0;
foreach ($alfabet as $a) {
    if ($a['tipe'] === 'vokal') { $jumlah_vokal++; }
    elseif ($a['tipe'] === 'tanda') { $jumlah_tanda++; }
    else { $jumlah_konsonan++; }
}

// ==========================================================================
// MATERI FONETIK
// ==========================================================================
$fonetik = [
    [
        'simbol' => 'Я',
        'judul'  => t('Vokal keras dan lunak', 'Hard and soft vowels'),
        'desc'   => t('Vokal Rusia berpasangan: а–я, о–ё, у–ю, э–е, ы–и. Vokal lunak (я, ё, ю, е) melembutkan konsonan sebelumnya. Di awal kata, setelah vokal lain, atau setelah ь/ъ, vokal ini dibaca dengan bunyi y: я = ya, ё = yo, ю = yu, е = ye.', 'Russian vowels come in pairs: а–я, о–ё, у–ю, э–е, ы–и. The soft vowels (я, ё, ю, е) soften the preceding consonant. At the start of a word, after another vowel, or after ь/ъ, they are read with a y sound: я = ya, ё = yo, ю = yu, е = ye.'),
    ],
    [
        'simbol' => 'Ы',
        'judul'  => t('Vokal ы tanpa padanan Latin', 'The ы vowel has no Latin equivalent'),
        'desc'   => t('ы tidak sama dengan i biasa. Lidah ditarik ke belakang, bibir tidak dibulatkan, dan bunyinya berada di antara "i" dan "ui". Tidak ada padanan persis dalam bahasa Indonesia, jadi latih berulang dan bandingkan dengan и.', 'ы is not the same as a regular i. The tongue is pulled back, the lips are not rounded, and the sound sits between "i" and "ui". Indonesian has no exact equivalent, so practice repeatedly and compare it with и.'),
    ],
    [
        'simbol' => 'Ь',
        'judul'  => t('Tanda lunak ь', 'The soft sign ь'),
        'desc'   => t('ь tidak memiliki bunyi sendiri. Tugasnya melembutkan konsonan tepat di depannya. Kehadiran ь bisa membedakan arti kata, jadi jangan pernah membacanya sebagai vokal.', 'ь has no sound of its own. Its job is to soften the consonant immediately before it. Its presence can change a word’s meaning, so never read it as a vowel.'),
    ],
    [
        'simbol' => 'Ъ',
        'judul'  => t('Tanda keras ъ', 'The hard sign ъ'),
        'desc'   => t('ъ juga tidak berbunyi. Tanda ini memisahkan awalan dari akar kata dan mencegah konsonan sebelumnya melembut. Setelah ъ, vokal е, ё, ю, я dibaca dengan bunyi y di depannya.', 'ъ is silent too. It separates a prefix from a root and prevents the preceding consonant from softening. After ъ, the vowels е, ё, ю, я are read with a y sound before them.'),
    ],
    [
        'simbol' => 'Р',
        'judul'  => t('R yang bergetar', 'The rolled R'),
        'desc'   => t('р diucapkan dengan ujung lidah bergetar, bukan seperti r bahasa Indonesia yang ringan. Latih getarannya secara terpisah sebelum memasukkannya ke dalam suku kata.', 'р is pronounced with a vibrating tongue tip, unlike the light Indonesian r. Practice the trill on its own before putting it into syllables.'),
    ],
    [
        'simbol' => 'Ш',
        'judul'  => t('Ш vs Щ', 'Ш vs Щ'),
        'desc'   => t('ш adalah bunyi "sy" pendek dan keras, sedangkan щ adalah "sy" panjang dan lebih lembut. Perbedaan panjang dan kelembutan ini penting karena bisa membedakan kata.', 'ш is a short, hard "sh" sound, while щ is a longer, softer "sh". The difference in length and softness matters because it can distinguish words.'),
    ],
    [
        'simbol' => '´',
        'judul'  => t('Tekanan kata', 'Word stress'),
        'desc'   => t('Setiap kata Rusia punya satu tekanan utama. Vokal yang mendapat tekanan dibaca jelas, sedangkan vokal tanpa tekanan sering melemah. Dalam teks biasa tekanan tidak ditulis, jadi latih pendengaranmu.', 'Every Russian word has one main stress. A stressed vowel is pronounced clearly, while unstressed vowels often weaken. Normal text does not mark stress, so train your ear.'),
    ],
    [
        'simbol' => 'Е',
        'judul'  => t('Reduksi vokal tanpa tekanan', 'Unstressed vowel reduction'),
        'desc'   => t('Vokal tanpa tekanan sering terdengar lebih pendek dan samar. о tanpa tekanan mendekati "a", dan е tanpa tekanan bisa mendekati "i". Ini pengecualian utama dari prinsip satu huruf satu bunyi.', 'Unstressed vowels often sound shorter and less distinct. An unstressed о approaches "a", and an unstressed е can approach "i". This is the main exception to the one-letter-one-sound principle.'),
    ],
];

// Pasangan konsonan bersuara / tak bersuara
$pasangan_konsonan = [
    ['bersuara' => 'Б', 'bunyi' => 'b', 'tumpul' => 'П', 'bunyi2' => 'p'],
    ['bersuara' => 'В', 'bunyi' => 'v', 'tumpul' => 'Ф', 'bunyi2' => 'f'],
    ['bersuara' => 'Г', 'bunyi' => 'g', 'tumpul' => 'К', 'bunyi2' => 'k'],
    ['bersuara' => 'Д', 'bunyi' => 'd', 'tumpul' => 'Т', 'bunyi2' => 't'],
    ['bersuara' => 'Ж', 'bunyi' => 'zh', 'tumpul' => 'Ш', 'bunyi2' => 'sh'],
    ['bersuara' => 'З', 'bunyi' => 'z', 'tumpul' => 'С', 'bunyi2' => 's'],
];

// Pasangan vokal keras / lunak
$pasangan_vokal = [
    ['keras' => 'А', 'bunyi' => 'a', 'lunak' => 'Я', 'bunyi2' => 'ya'],
    ['keras' => 'О', 'bunyi' => 'o', 'lunak' => 'Ё', 'bunyi2' => 'yo'],
    ['keras' => 'У', 'bunyi' => 'u', 'lunak' => 'Ю', 'bunyi2' => 'yu'],
    ['keras' => 'Э', 'bunyi' => 'e', 'lunak' => 'Е', 'bunyi2' => 'ye'],
    ['keras' => 'Ы', 'bunyi' => 'y', 'lunak' => 'И', 'bunyi2' => 'i'],
];

// Poin kembar / palsu: bentuk mirip Latin, bunyi berbeda.
$poin_palsu = [
    ['huruf' => 'В', 'mirip' => 'B', 'baca' => 'v', 'catatan' => t('Bukan b. Bibir atas menyentuh gigi bawah.', 'Not b. The upper lip touches the lower teeth.')],
    ['huruf' => 'Е', 'mirip' => 'E', 'baca' => 'ye', 'catatan' => t('Di awal kata dibaca "ye", bukan "e".', 'At the start of a word it is "ye", not "e".')],
    ['huruf' => 'Ё', 'mirip' => 'Ë', 'baca' => 'yo', 'catatan' => t('Selalu bertekanan dan dibaca "yo".', 'Always stressed and read "yo".')],
    ['huruf' => 'Н', 'mirip' => 'H', 'baca' => 'n', 'catatan' => t('Bukan h. Bentuknya seperti H, bunyinya n.', 'Not h. It looks like H but sounds like n.')],
    ['huruf' => 'Р', 'mirip' => 'P', 'baca' => 'r', 'catatan' => t('Bukan p. Bunyinya r bergetar.', 'Not p. It is a rolled r.')],
    ['huruf' => 'С', 'mirip' => 'C', 'baca' => 's', 'catatan' => t('Bukan c. Selalu dibaca s.', 'Not c. Always read s.')],
    ['huruf' => 'У', 'mirip' => 'Y', 'baca' => 'u', 'catatan' => t('Bukan y. Dibaca u seperti pada "ibu".', 'Not y. Read u as in "boot".')],
    ['huruf' => 'Х', 'mirip' => 'X', 'baca' => 'kh', 'catatan' => t('Bukan x. Dibaca kh seperti pada "khawatir".', 'Not x. Read kh as in "loch".')],
];

// Lima huruf dasar teknik Akamoto.
$akamoto = [
    ['huruf' => 'А', 'baca' => 'a'],
    ['huruf' => 'К', 'baca' => 'k'],
    ['huruf' => 'М', 'baca' => 'm'],
    ['huruf' => 'О', 'baca' => 'o'],
    ['huruf' => 'Т', 'baca' => 't'],
];

// Langkah teknik membaca
$langkah_baca = [
    ['judul' => t('Kenali bentuk, bukan arti', 'Recognize the shape, not the meaning'), 'isi' => t('Latih dulu membedakan В/Б, Р/П, Н/И, dan Ш/Щ. Jangan buru-buru menerjemahkan; fokus pada bentuk huruf.', 'First practice telling В/Б, Р/П, Н/И, and Ш/Щ apart. Do not rush to translate; focus on letter shapes.')],
    ['judul' => t('Baca per suku kata', 'Read syllable by syllable'), 'isi' => t('Gabungkan satu konsonan dengan satu vokal menjadi suku kata pendek, lalu rangkai suku kata itu menjadi kata utuh.', 'Combine one consonant with one vowel into a short syllable, then join the syllables into a whole word.')],
    ['judul' => t('Tandai vokalnya', 'Mark the vowels'), 'isi' => t('Vokal adalah inti suku kata. Tandai а, е, ё, и, о, у, ы, э, ю, я agar ritme baca lebih mudah.', 'Vowels are the core of each syllable. Mark а, е, ё, и, о, у, ы, э, ю, я to make the reading rhythm easier.')],
    ['judul' => t('Cari tekanan katanya', 'Find the word stress'), 'isi' => t('Tekanan mengubah bunyi vokal. Dengarkan audio, tiru intonasinya, lalu ulangi tanpa melihat transliterasi.', 'Stress changes the vowel sound. Listen to the audio, imitate the intonation, then repeat without looking at the transliteration.')],
    ['judul' => t('Baca ulang dengan suara', 'Read aloud again'), 'isi' => t('Baca keras 2–3 kali. Setelah itu tutup transliterasi dan uji apakah kamu masih bisa membacanya.', 'Read aloud 2–3 times. Then cover the transliteration and check whether you can still read it.')],
];

// Teknik menulis
$teknik_tulis = [
    ['judul' => t('Mulai dari huruf cetak', 'Start with print letters'), 'isi' => t('Kuasai bentuk cetak besar dan kecil lebih dulu. Setelah hafal, baru pindah ke tulisan sambung agar tidak bingung.', 'Master uppercase and lowercase print forms first. Once memorized, move to cursive so you do not get confused.')],
    ['judul' => t('Perhatikan tinggi huruf', 'Watch letter height'), 'isi' => t('Huruf seperti б, в, д punya bagian yang naik atau turun. Gunakan garis bantu buku tulis untuk menjaga proporsi.', 'Letters such as б, в, д have parts that rise or descend. Use notebook guide lines to keep proportions.')],
    ['judul' => t('Tulis per kata, bukan per huruf', 'Write whole words, not single letters'), 'isi' => t('Setelah bentuk huruf stabil, latih menulis kata utuh. Ini melatih otot tangan mengenali pola sambungan.', 'Once letter shapes are stable, practice writing whole words. This trains your hand to recognize connection patterns.')],
    ['judul' => t('Bandingkan dengan audio', 'Compare with the audio'), 'isi' => t('Setiap selesai menulis, dengarkan audio kata tersebut. Tulisan dan bunyi yang dipasangkan akan lebih cepat melekat.', 'After writing, listen to the audio of that word. Pairing writing with sound helps it stick faster.')],
];

$cursive_tips = [
    ['cetak' => 'т', 'tulis' => 'т', 'catatan' => t('Dalam tulisan sambung sering terlihat seperti huruf "m" kecil.', 'In cursive it often looks like a lowercase "m".')],
    ['cetak' => 'д', 'tulis' => 'д', 'catatan' => t('Bentuk sambungnya bisa tampak seperti "g" atau "d" yang lonjong.', 'Its cursive form can look like an elongated "g" or "d".')],
    ['cetak' => 'г', 'tulis' => 'г', 'catatan' => t('Sambungannya hanya berupa lengkung pendek, mirip "r" Latin.', 'The cursive form is just a short curve, similar to a Latin "r".')],
    ['cetak' => 'п', 'tulis' => 'п', 'catatan' => t('Dalam tulisan cepat sering tampak seperti "n" Latin.', 'In fast writing it often looks like a Latin "n".')],
    ['cetak' => 'и', 'tulis' => 'и', 'catatan' => t('Mirip "u" Latin; bedakan dengan ш yang punya tiga kaki.', 'Similar to a Latin "u"; distinguish it from ш, which has three legs.')],
    ['cetak' => 'ш', 'tulis' => 'ш', 'catatan' => t('Tiga goresan vertikal; щ menambahkan ekor kecil di kanan bawah.', 'Three vertical strokes; щ adds a small tail at the lower right.')],
];

?>

<!DOCTYPE html>
<html lang="<?= bahasa_html() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Belajar Alfabet Kiril', 'Learn the Cyrillic Alphabet') ?> - RusVoca</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Caveat:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/learn-ui.css?v=20261108">
    <link rel="stylesheet" href="assets/css/level-theme.css?v=20261113">
    
    <style>
        :root {
            --primary-blue: #0F3F7A; --bg-light: #F4F7FA; --border-color: #E2E8F0;
            --text-dark: #0F172A; --text-muted: #64748B; --card-bg: #FFFFFF;
            --accent-blue: #2563EB; --accent-orange: #E09B19;
            --accent-green: #10B981; --accent-red: #EF4444;
            --accent-gold: #E09B19;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-dark); min-height: 100vh; scroll-behavior: smooth; }
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
        .user-info-role { font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 4px; margin-top: 2px; }
        .logout-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 6px; border-radius: 6px; transition: all 0.2s; }
        .logout-btn:hover { color: #EF4444; background-color: rgba(239, 68, 68, 0.1); }

        /* === KONTEN UTAMA === */
        .main-content { margin-left: 260px; flex: 1; padding: 34px 40px 70px; max-width: 1220px; }
        .alpha-section { scroll-margin-top: 96px; margin-bottom: 52px; }

        /* Navigasi lengket */
        .alpha-nav {
            position: sticky; top: 14px; z-index: 40;
            display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
            background: rgba(255, 255, 255, .88); backdrop-filter: blur(12px);
            border: 1px solid var(--border-color); border-radius: 16px;
            padding: 8px; margin: 22px 0 30px;
            box-shadow: 0 8px 22px rgba(15, 63, 122, .06);
        }
        .alpha-nav a {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 12px; border-radius: 10px; text-decoration: none;
            font-size: 12px; font-weight: 800; color: var(--text-muted);
            transition: .18s; white-space: nowrap;
        }
        .alpha-nav a b { font-size: 10px; color: #94A3B8; }
        .alpha-nav a:hover { background: #EFF6FF; color: var(--accent-blue); }
        .alpha-nav a.is-active { background: var(--primary-blue); color: #fff; box-shadow: 0 6px 14px rgba(15, 63, 122, .2); }
        .alpha-nav a.is-active b { color: #FDE68A; }

        /* Struktur */
        .structure-grid { display: grid; grid-template-columns: 1.35fr 1fr 1fr 1fr; gap: 14px; margin-bottom: 22px; }
        .structure-card { background: #fff; border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; box-shadow: 0 3px 10px rgba(15, 63, 122, .03); }
        .structure-card.main { grid-row: span 1; }
        .structure-card .big-stat { font-size: 34px; font-weight: 800; line-height: 1; color: var(--primary-blue); margin-bottom: 4px; }
        .structure-card h4 { font-size: 13px; font-weight: 800; margin-bottom: 6px; }
        .structure-card p { font-size: 11.5px; color: var(--text-muted); line-height: 1.55; font-weight: 500; }
        .structure-card .letters { margin-top: 10px; font-size: 15px; font-weight: 800; color: var(--accent-orange); letter-spacing: 2px; line-height: 1.5; }
        .structure-card.main h3 { font-size: 17px; font-weight: 800; margin-bottom: 8px; }
        .structure-card.main p { font-size: 12.5px; }
        .structure-bar { display: flex; height: 12px; border-radius: 999px; overflow: hidden; margin-top: 14px; background: #E2E8F0; }
        .structure-bar i { display: block; height: 100%; }
        .bar-vokal { width: 30.3%; background: #D97706; }
        .bar-konsonan { width: 63.6%; background: #1D4ED8; }
        .bar-tanda { width: 6.1%; background: #6D28D9; }
        .bar-legend { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 10px; font-size: 10.5px; font-weight: 700; color: var(--text-muted); }
        .bar-legend span { display: inline-flex; align-items: center; gap: 5px; }
        .bar-legend i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

        /* Toolbar alfabet */
        .alpha-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .alpha-search { position: relative; }
        .alpha-search input {
            width: 250px; max-width: 100%; padding: 10px 14px 10px 36px;
            border: 1px solid var(--border-color); border-radius: 10px;
            background: #fff; font-size: 12.5px; font-weight: 600; color: var(--text-dark);
            outline: none; transition: .18s;
        }
        .alpha-search input:focus { border-color: #93C5FD; box-shadow: 0 0 0 4px rgba(37, 99, 235, .1); }
        .alpha-search svg { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #94A3B8; }

        /* Kartu huruf */
        .alphabet-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(168px, 1fr)); gap: 12px; }
        .alpha-card {
            position: relative; background: #fff; border: 1px solid var(--border-color);
            border-radius: 16px; padding: 16px 16px 14px; text-align: left;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            box-shadow: 0 2px 6px rgba(15, 63, 122, .03);
        }
        .alpha-card:hover { transform: translateY(-4px); border-color: #93C5FD; box-shadow: 0 12px 24px rgba(15, 63, 122, .1); }
        .alpha-card[hidden] { display: none; }
        .alpha-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
        .alpha-pair { display: flex; align-items: baseline; gap: 4px; line-height: 1; }
        .alpha-upper { font-size: 40px; font-weight: 800; color: var(--primary-blue); letter-spacing: -1px; }
        .alpha-lower { font-size: 26px; font-weight: 700; color: #93C5FD; }
        .alpha-type { font-size: 9px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; padding: 3px 7px; border-radius: 999px; position: absolute; top: 12px; right: 12px; }
        .t-vokal { background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; }
        .t-konsonan { background: #EFF6FF; color: #1D4ED8; border: 1px solid #DBEAFE; }
        .t-tanda { background: #F5F3FF; color: #6D28D9; border: 1px solid #DDD6FE; }
        .alpha-pronounce { margin-top: 8px; font-size: 11px; font-weight: 800; color: var(--accent-orange); letter-spacing: .6px; text-transform: uppercase; }
        .alpha-name { font-size: 10.5px; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
        .alpha-divider { height: 1px; background: var(--border-color); margin: 12px 0 10px; }
        .alpha-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .alpha-group-label { font-size: 9px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: .4px; line-height: 1.35; }
        .alpha-foot .rv-audio { width: 32px; height: 32px; flex-shrink: 0; }
        .alpha-foot .rv-audio svg { width: 15px; height: 15px; }
        .alpha-empty { grid-column: 1 / -1; text-align: center; padding: 40px 20px; background: #fff; border: 1px dashed var(--border-color); border-radius: 14px; color: var(--text-muted); font-size: 13px; font-weight: 600; }

        /* Kelompok huruf */
        .group-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
        .fast-track { display: grid; grid-template-columns: 1.25fr .75fr; gap: 14px; margin-bottom: 24px; }
        .fast-track .technique-card > p { margin-bottom: 12px; }
        .fast-track .pair-grid { margin-top: 4px; }
        .group-card { border-radius: 18px; padding: 20px; background: #fff; border: 1px solid var(--border-color); box-shadow: 0 3px 10px rgba(15, 63, 122, .03); }
        .group-card .group-head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .group-badge { width: 36px; height: 36px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 800; flex-shrink: 0; }
        .g-blue .group-badge { background: #EFF6FF; color: #1D4ED8; }
        .g-gold .group-badge { background: #FFFBEB; color: #B45309; }
        .g-pink .group-badge { background: #FDF2F8; color: #BE185D; }
        .group-card h3 { font-size: 14px; font-weight: 800; }
        .group-card .group-sub { font-size: 10.5px; color: var(--text-muted); font-weight: 700; }
        .group-card p { font-size: 11.5px; color: var(--text-muted); line-height: 1.55; margin-bottom: 12px; }
        .letter-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .letter-chip {
            display: inline-flex; align-items: center; gap: 4px;
            background: #F8FAFC; border: 1px solid var(--border-color); border-radius: 8px;
            padding: 6px 8px; cursor: pointer; transition: .15s;
            font-size: 14px; font-weight: 800; color: var(--primary-blue);
        }
        .letter-chip small { font-size: 9px; font-weight: 700; color: var(--text-muted); }
        .letter-chip:hover { background: #EFF6FF; border-color: #93C5FD; transform: translateY(-2px); }

        /* Tabel pasangan */
        .pair-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; }
        .pair-card { background: #fff; border: 1px solid var(--border-color); border-radius: 13px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; transition: .18s; }
        .pair-card:hover { border-color: #93C5FD; box-shadow: 0 8px 18px rgba(15, 63, 122, .07); }
        .pair-letter { width: 44px; height: 44px; border-radius: 11px; background: #F8FAFC; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; color: var(--primary-blue); cursor: pointer; flex-shrink: 0; }
        .pair-letter:hover { background: #EFF6FF; border-color: #93C5FD; }
        .pair-arrow { color: #94A3B8; font-weight: 800; }
        .pair-info { min-width: 0; }
        .pair-info b { display: block; font-size: 12px; color: var(--text-dark); }
        .pair-info span { font-size: 10px; color: var(--text-muted); font-weight: 600; }

        /* Fonetik */
        .principle-band { display: flex; align-items: flex-start; gap: 16px; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 18px; padding: 18px 20px; margin-bottom: 18px; }
        .principle-badge { width: 54px; height: 54px; border-radius: 16px; background: #047857; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 800; flex-shrink: 0; letter-spacing: .5px; }
        .principle-band h3 { font-size: 15px; font-weight: 800; color: #065F46; margin-bottom: 5px; }
        .principle-band p { font-size: 11.5px; color: #065F46; line-height: 1.65; font-weight: 500; }
        .phonetic-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
        .phonetic-card { background: #fff; border: 1px solid var(--border-color); border-radius: 16px; padding: 18px; transition: .18s; }
        .phonetic-card:hover { border-color: #BFDBFE; box-shadow: 0 10px 22px rgba(15, 63, 122, .06); }
        .phonetic-head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .phonetic-symbol { width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; background: var(--primary-blue); color: #fff; font-size: 19px; font-weight: 800; flex-shrink: 0; }
        .phonetic-card h4 { font-size: 13.5px; font-weight: 800; }
        .phonetic-card > p { font-size: 11.5px; color: var(--text-muted); line-height: 1.6; margin-bottom: 0; }

        /* Teknik */
        .steps { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 26px; }
        .step { background: #fff; border: 1px solid var(--border-color); border-radius: 15px; padding: 16px; position: relative; transition: .18s; }
        .step:hover { border-color: #93C5FD; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(15, 63, 122, .06); }
        .step-num { width: 28px; height: 28px; border-radius: 9px; background: #EFF6FF; color: var(--accent-blue); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; margin-bottom: 9px; }
        .step h4 { font-size: 12.5px; font-weight: 800; margin-bottom: 5px; line-height: 1.35; }
        .step p { font-size: 10.5px; color: var(--text-muted); line-height: 1.55; }

        .writing-technique { display: grid; grid-template-columns: 1.1fr .9fr; gap: 14px; margin-bottom: 24px; }
        .technique-card { background: #fff; border: 1px solid var(--border-color); border-radius: 18px; padding: 20px; }
        .technique-card h3 { font-size: 15px; font-weight: 800; margin-bottom: 6px; }
        .technique-card > p { font-size: 11.5px; color: var(--text-muted); line-height: 1.6; margin-bottom: 14px; }
        .technique-list { display: flex; flex-direction: column; gap: 10px; }
        .technique-item { display: flex; gap: 10px; align-items: flex-start; }
        .technique-item .tick { width: 20px; height: 20px; border-radius: 50%; background: #ECFDF5; color: #047857; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 11px; font-weight: 800; }
        .technique-item b { display: block; font-size: 12px; margin-bottom: 2px; }
        .technique-item span { font-size: 10.5px; color: var(--text-muted); line-height: 1.5; }

        .cursive-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        .cursive-table th { text-align: left; color: var(--text-muted); font-size: 10px; text-transform: uppercase; letter-spacing: .6px; padding: 7px 6px; border-bottom: 1px solid var(--border-color); }
        .cursive-table td { padding: 8px 6px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
        .cursive-table tr:last-child td { border-bottom: none; }
        .cursive-print { font-size: 19px; font-weight: 800; color: var(--primary-blue); }
        .cursive-write { font-family: 'Caveat', cursive; font-size: 28px; color: var(--accent-orange); line-height: 1; }
        .cursive-table td:last-child { color: var(--text-muted); font-size: 10.5px; }

        .tip-band { display: flex; gap: 12px; align-items: flex-start; background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 14px; padding: 14px 16px; margin-top: 18px; }
        .tip-band svg { flex-shrink: 0; color: #B45309; margin-top: 1px; }
        .tip-band p { font-size: 11.5px; color: #92400E; line-height: 1.6; font-weight: 600; }

        @media (max-width: 1100px) {
            .structure-grid { grid-template-columns: 1fr 1fr; }
            .group-grid { grid-template-columns: 1fr; }
            .fast-track { grid-template-columns: 1fr; }
            .steps { grid-template-columns: repeat(2, 1fr); }
            .writing-technique { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            .phonetic-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 72px; padding: 16px 8px; }
            .brand-text, .user-info { display: none; }
            .brand-logo-img { width: 32px; height: 32px; }
            .brand-header { padding-bottom: 12px; margin-bottom: 12px; justify-content: center; padding-left: 0; }
            .user-profile-bottom { justify-content: center; padding: 8px; flex-direction: column; gap: 15px; }
            .nav-item a { justify-content: center; padding: 12px; }
            .nav-item span { display: none; }
            .main-content { margin-left: 72px; padding: 22px 18px 60px; }
            .alpha-nav { top: 8px; }
            .alpha-nav a span { display: none; }
            .structure-grid { grid-template-columns: 1fr; }
            .alphabet-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            .steps { grid-template-columns: 1fr; }
            .alpha-search input { width: 100%; }
            .alpha-search { width: 100%; }
            .principle-band { flex-direction: column; gap: 10px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/mobile.css?v=20261123">
</head>
<body class="<?= htmlspecialchars($level_kelas) ?>">

<div class="app-layout">
    <!-- Sidebar -->
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
                <li class="nav-item active"><a href="alfabet.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h9v2zm0-4H9V5h9v2z"/></svg><span><?= t('Belajar Alfabet', 'Learn the Alphabet') ?></span></a></li>
                <li class="nav-item"><a href="kosakata.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 4h2v5H9V4zm0 7h2v2H9v-2zm0 4h2v2H9v-2zm6 2h-3v-2h3v2zm0-4h-3v-2h3v2zm0-5h-3V4h3v5z"/></svg><span><?= t('Kosakata', 'Vocabulary') ?></span></a></li>
                <li class="nav-item"><a href="riwayat.php"><svg class="nav-icon" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span><?= t('Riwayat', 'History') ?></span></a></li>
                <li class="nav-item"><a href="profil.php"><svg class="nav-icon solid" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= t('Profil Saya', 'My Profile') ?></span></a></li>
                <?php include __DIR__ . '/partials/sidebar_admin.php'; ?>
                <li class="nav-lang"><?= tombol_bahasa('sidebar') ?></li>
            </ul>
        </div>
        <div class="user-profile-bottom">
            <div class="avatar-circle"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
            <div class="user-info">
                <div class="user-info-name"><?= htmlspecialchars($nama_user); ?></div>
                <div class="user-info-role" style="color: <?= $level['warna'] ?>;"><svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="<?= $level['ikon'] ?>"/></svg><span><?= htmlspecialchars(materi_t($level['nama'])) ?></span></div>
            </div>
            <form action="logout.php" method="POST" style="display: flex;" onsubmit="return confirm('<?= t('Apakah Anda yakin ingin keluar?', 'Are you sure you want to log out?') ?>');">
                <?= csrf_field() ?><button type="submit" class="logout-btn"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></button>
            </form>
        </div>
    </aside>

    <main class="main-content">
        <!-- HERO -->
        <div class="rv-hero rv-rise">
            <div class="rv-hero-copy">
                <span class="rv-eyebrow"><span class="dot"></span> <?= t('Panduan lengkap', 'Complete guide') ?></span>
                <h1><?= t('Kuasai <span class="hl">Alfabet Kiril</span> dari Nol', 'Master the <span class="hl">Cyrillic Alphabet</span> from Scratch') ?></h1>
                <p><?= t('Mulai dari struktur dasar, pembagian huruf, poin kembar, aturan fonetik, sampai teknik membaca dan menulis. Klik tombol speaker untuk mendengar bunyi setiap huruf.', 'Start with the basic structure, letter divisions, false friends, phonetic rules, and reading and writing techniques. Click the speaker button to hear each letter.') ?></p>
                <div class="rv-hero-actions">
                    <a href="#struktur" class="rv-btn rv-btn-gold"><?= t('Mulai dari Struktur', 'Start with Structure') ?></a>
                </div>
                <div class="rv-hero-stats">
                    <div class="rv-stat"><b><?= count($alfabet) ?></b><span><?= t('Huruf', 'Letters') ?></span></div>
                    <div class="rv-stat"><b><?= $jumlah_vokal ?></b><span><?= t('Vokal', 'Vowels') ?></span></div>
                    <div class="rv-stat"><b><?= $jumlah_konsonan ?></b><span><?= t('Konsonan', 'Consonants') ?></span></div>
                    <div class="rv-stat"><b><?= $jumlah_tanda ?></b><span><?= t('Tanda', 'Signs') ?></span></div>
                </div>
            </div>
            <div class="rv-hero-art">
                <img src="assets/img/hero-alphabet.svg" alt="<?= t('Ilustrasi balok huruf Kiril', 'Cyrillic letter blocks illustration') ?>">
            </div>
        </div>

        <!-- NAVIGASI -->
        <nav class="alpha-nav rv-rise" style="--d: 80ms;" aria-label="<?= t('Navigasi materi alfabet', 'Alphabet material navigation') ?>">
            <a href="#struktur" class="is-active"><b>01</b><span><?= t('Struktur Dasar', 'Basic Structure') ?></span></a>
            <a href="#kelompok"><b>02</b><span><?= t('Kelompok & Teknik', 'Groups & Techniques') ?></span></a>
            <a href="#fonetik"><b>03</b><span><?= t('Fonetik', 'Phonetics') ?></span></a>
            <a href="#teknik"><b>04</b><span><?= t('Teknik Baca-Tulis', 'Reading & Writing') ?></span></a>
        </nav>

        <!-- ============================================================
             BAGIAN 1: STRUKTUR DASAR
             ============================================================ -->
        <section class="alpha-section" id="struktur">
            <div class="rv-section-head">
                <div>
                    <h2><span class="rv-icon-badge"><svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg></span> <?= t('Struktur Dasar Alfabet', 'Basic Alphabet Structure') ?></h2>
                    <p><?= t('Alfabet Rusia modern disebut <b>а́збука (azbuka)</b> dan terdiri dari 33 huruf: 10 vokal, 21 konsonan, serta 2 tanda khusus. Vokal menjadi inti suku kata, konsonan membentuk kerangka bunyi, dan tanda mengubah cara baca tanpa bunyi sendiri.', 'The modern Russian alphabet is called <b>а́збука (azbuka)</b> and has 33 letters: 10 vowels, 21 consonants, and 2 special signs. Vowels form the core of syllables, consonants build the sound framework, and signs change pronunciation without a sound of their own.') ?></p>
                </div>
            </div>

            <div class="structure-grid">
                <div class="structure-card main">
                    <h3><?= t('Jumlah dan pembagian huruf', 'Letter count and division') ?></h3>
                    <p><?= t('<b>33 huruf = 10 vokal + 21 konsonan + 2 tanda.</b> Vokal membuat suku kata bisa diucapkan, konsonan memberi bentuk pada bunyi, sedangkan ь dan ъ tidak berbunyi tetapi memengaruhi huruf di sekitarnya.', '<b>33 letters = 10 vowels + 21 consonants + 2 signs.</b> Vowels make syllables pronounceable, consonants shape the sound, while ь and ъ are silent but affect nearby letters.') ?></p>
                    <div class="structure-bar">
                        <i class="bar-vokal"></i><i class="bar-konsonan"></i><i class="bar-tanda"></i>
                    </div>
                    <div class="bar-legend">
                        <span><i style="background:#F59E0B"></i> <?= t('Vokal', 'Vowels') ?> <?= $jumlah_vokal ?></span>
                        <span><i style="background:#2563EB"></i> <?= t('Konsonan', 'Consonants') ?> <?= $jumlah_konsonan ?></span>
                        <span><i style="background:#8B5CF6"></i> <?= t('Tanda', 'Signs') ?> <?= $jumlah_tanda ?></span>
                    </div>
                </div>
                <div class="structure-card">
                    <div class="big-stat"><?= $jumlah_vokal ?></div>
                    <h4><?= t('Vokal', 'Vowels') ?></h4>
                    <p><?= t('Inti suku kata. Kualitas bunyinya berubah mengikuti tekanan kata.', 'The core of a syllable. Their sound quality changes with word stress.') ?></p>
                    <div class="letters">А · Е · Ё · И · О · У · Ы · Э · Ю · Я</div>
                </div>
                <div class="structure-card">
                    <div class="big-stat"><?= $jumlah_konsonan ?></div>
                    <h4><?= t('Konsonan', 'Consonants') ?></h4>
                    <p><?= t('Banyak yang berpasangan bersuara–tak bersuara. Й berperilaku sebagai konsonan pendek.', 'Many come in voiced–voiceless pairs. Й behaves as a short consonant.') ?></p>
                    <div class="letters">Б · В · Г · Д · Ж · З · Й · К · Л · М · Н · П · Р · С · Т · Ф · Х · Ц · Ч · Ш · Щ</div>
                </div>
                <div class="structure-card">
                    <div class="big-stat"><?= $jumlah_tanda ?></div>
                    <h4><?= t('Tanda', 'Signs') ?></h4>
                    <p><?= t('ь (lunak) dan ъ (keras) tidak berbunyi, tetapi mengubah cara baca huruf di sekitarnya.', 'ь (soft) and ъ (hard) are silent, but they change how nearby letters are read.') ?></p>
                    <div class="letters">Ъ · Ь</div>
                </div>
            </div>

            <div class="tip-band">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p><?= t('<b>Cara pakai halaman ini:</b> klik tombol speaker di setiap kartu untuk mendengar bunyi hurufnya. Gunakan filter atau kolom pencarian untuk menemukan huruf tertentu.', '<b>How to use this page:</b> click the speaker button on any card to hear the letter sound. Use the filters or search field to find a specific letter.') ?></p>
            </div>

            <div class="rv-section-head" style="margin-top: 26px;">
                <div>
                    <h3 style="font-size:16px;"><?= t('Peta 33 huruf', 'Map of 33 letters') ?></h3>
                    <p><?= t('Huruf besar, huruf kecil, cara baca, dan kelompoknya dalam satu tampilan.', 'Uppercase, lowercase, pronunciation, and group in one view.') ?></p>
                </div>
                <div class="alpha-toolbar" style="margin:0;">
                    <div class="rv-tabs" id="filter-tipe">
                        <button type="button" class="rv-tab is-active" data-filter="semua"><?= t('Semua', 'All') ?></button>
                        <button type="button" class="rv-tab" data-filter="vokal"><?= t('Vokal', 'Vowels') ?></button>
                        <button type="button" class="rv-tab" data-filter="konsonan"><?= t('Konsonan', 'Consonants') ?></button>
                        <button type="button" class="rv-tab" data-filter="tanda"><?= t('Tanda', 'Signs') ?></button>
                    </div>
                    <div class="alpha-search">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" id="cari-huruf" placeholder="<?= t('Cari huruf atau cara baca...', 'Search letters or pronunciation...') ?>" aria-label="<?= t('Cari huruf alfabet', 'Search alphabet letters') ?>">
                    </div>
                </div>
            </div>

            <div class="alphabet-grid" id="alphabet-grid">
                <?php foreach ($alfabet as $a): ?>
                    <?php
                        $label_tipe = $a['tipe'] === 'vokal' ? t('vokal', 'vowel') : ($a['tipe'] === 'tanda' ? t('tanda', 'sign') : t('konsonan', 'consonant'));
                        $teks_cari = $a['besar'] . ' ' . $a['kecil'] . ' ' . $a['baca'] . ' ' . $grup_meta[$a['grup']]['judul'] . ' ' . $label_tipe;
                    ?>
                    <article class="alpha-card" data-tipe="<?= $a['tipe'] ?>" data-grup="<?= $a['grup'] ?>" data-cari="<?= htmlspecialchars(strtr(strtolower($teks_cari), $peta_kecil)) ?>">
                        <span class="alpha-type t-<?= $a['tipe'] ?>"><?= $a['tipe'] === 'vokal' ? t('Vokal', 'Vowel') : ($a['tipe'] === 'tanda' ? t('Tanda', 'Sign') : t('Konsonan', 'Consonant')) ?></span>
                        <div class="alpha-pair">
                            <span class="alpha-upper"><?= htmlspecialchars($a['besar']) ?></span>
                            <span class="alpha-lower"><?= htmlspecialchars($a['kecil']) ?></span>
                        </div>
                        <div class="alpha-pronounce"><?= t('Dibaca', 'Read as') ?> [ <?= htmlspecialchars($a['baca']) ?> ]</div>
                        <div class="alpha-divider"></div>
                        <div class="alpha-foot">
                            <span class="alpha-group-label"><?= htmlspecialchars($grup_meta[$a['grup']]['judul']) ?></span>
                            <button type="button" class="rv-audio" data-ucap="<?= htmlspecialchars($a['ucap']) ?>" data-rate="0.7" title="<?= t('Dengarkan huruf', 'Listen to letter') ?> <?= htmlspecialchars($a['besar']) ?>" aria-label="<?= t('Dengarkan huruf', 'Listen to letter') ?> <?= htmlspecialchars($a['besar']) ?>">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
                <div class="alpha-empty" id="alpha-empty" hidden><?= t('Tidak ada huruf yang cocok. Coba kata kunci lain.', 'No matching letters. Try another keyword.') ?></div>
            </div>
        </section>

        <!-- ============================================================
             BAGIAN 2: PENGELOMPOKAN HURUF
             ============================================================ -->
        <section class="alpha-section" id="kelompok">
            <div class="rv-section-head">
                <div>
                    <h2><span class="rv-icon-badge" style="background:#B45309;"><svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h7"/></svg></span> <?= t('Pengelompokan Huruf Berdasarkan Kemiripan', 'Grouping Letters by Similarity') ?></h2>
                    <p><?= t('Otak lebih cepat mengingat kalau huruf dikelompokkan. Kenali dulu mana yang aman, mana yang menjebak, lalu mulai membaca dengan teknik Akamoto.', 'The brain remembers letters faster when they are grouped. First learn which are safe and which are tricky, then start reading with the Akamoto technique.') ?></p>
                </div>
            </div>

            <div class="group-grid">
                <?php foreach ($grup_meta as $kunci => $meta): ?>
                    <div class="group-card <?= $meta['kelas'] ?>">
                        <div class="group-head">
                            <div class="group-badge"><?= count($grup_isi[$kunci]) ?></div>
                            <div>
                                <h3><?= htmlspecialchars($meta['judul']) ?></h3>
                                <div class="group-sub"><?= htmlspecialchars($meta['sub']) ?></div>
                            </div>
                        </div>
                        <p><?= htmlspecialchars($meta['desc']) ?></p>
                        <div class="letter-chips">
                            <?php foreach ($grup_isi[$kunci] as $g): ?>
                                <button type="button" class="letter-chip" data-ucap="<?= htmlspecialchars($g['ucap']) ?>" data-rate="0.7" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($g['besar']) ?>">
                                    <?= htmlspecialchars($g['besar']) ?> <small><?= htmlspecialchars($g['kecil']) ?><?= $g['tipe'] === 'tanda' ? '' : ' · ' . htmlspecialchars($g['baca']) ?></small>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="rv-section-head" style="margin-top: 26px;">
                <div>
                    <h3 style="font-size:16px;"><?= t('Teknik pengenalan cepat', 'Fast recognition techniques') ?></h3>
                    <p><?= t('Dua cara praktis menghadapi huruf yang mirip Latin: waspadai poin palsu, lalu mulai dari lima huruf Akamoto.', 'Two practical ways to handle Latin lookalikes: watch out for false friends, then start with the five Akamoto letters.') ?></p>
                </div>
            </div>

            <div class="fast-track">
                <div class="technique-card">
                    <h3><?= t('Poin kembar / palsu', 'Twin / false friends') ?></h3>
                    <p><?= t('Huruf-huruf ini bentuknya familiar, tetapi bunyinya berbeda. Hafalkan pasangannya sebelum membaca teks.', 'These letters look familiar, but sound different. Memorize the pairs before reading texts.') ?></p>
                    <div class="pair-grid">
                        <?php foreach ($poin_palsu as $p): ?>
                            <div class="pair-card">
                                <button type="button" class="pair-letter" data-ucap="<?= htmlspecialchars($p['huruf']) ?>" data-rate="0.7" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($p['huruf']) ?>"><?= htmlspecialchars($p['huruf']) ?></button>
                                <span class="pair-arrow">→</span>
                                <div class="pair-info">
                                    <b><?= t('Mirip', 'Looks like') ?> <?= htmlspecialchars($p['mirip']) ?> · <?= t('dibaca', 'read') ?> [<?= htmlspecialchars($p['baca']) ?>]</b>
                                    <span><?= htmlspecialchars($p['catatan']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="technique-card">
                    <h3><?= t('Teknik Akamoto', 'Akamoto technique') ?></h3>
                    <p><?= t('Mulai dari lima huruf yang bentuk dan bunyinya sama dengan Latin. Kelima huruf ini membantu membaca sebagian besar teks dasar dengan cepat; setelah otomatis, lanjut ke huruf lain satu per satu.', 'Start with five letters whose shape and sound match Latin. These five help you read most basic texts quickly; once automatic, add the other letters one by one.') ?></p>
                    <div class="letter-chips">
                        <?php foreach ($akamoto as $ak): ?>
                            <button type="button" class="letter-chip" data-ucap="<?= htmlspecialchars($ak['huruf']) ?>" data-rate="0.7" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($ak['huruf']) ?>">
                                <?= htmlspecialchars($ak['huruf']) ?> <small><?= htmlspecialchars($ak['baca']) ?></small>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="technique-list" style="margin-top: 14px;">
                        <div class="technique-item">
                            <div class="tick">✓</div>
                            <div>
                                <b><?= t('5 huruf dasar', '5 base letters') ?></b>
                                <span><?= t('А, К, М, О, Т adalah modal awal karena tidak perlu transliterasi.', 'А, К, М, О, Т are the starting capital because they need no transliteration.') ?></span>
                            </div>
                        </div>
                        <div class="technique-item">
                            <div class="tick">✓</div>
                            <div>
                                <b><?= t('Tambahkan bertahap', 'Add gradually') ?></b>
                                <span><?= t('Setelah lima huruf lancar, tambah satu kelompok kecil dan ulangi sampai otomatis.', 'Once the five are smooth, add one small group and repeat until automatic.') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rv-section-head" style="margin-top: 8px;">
                <div>
                    <h3 style="font-size:16px;"><?= t('Pasangan konsonan bersuara &amp; tak bersuara', 'Voiced &amp; voiceless consonant pairs') ?></h3>
                    <p><?= t('Konsonan bersuara berubah menjadi pasangannya saat berada di akhir kata.', 'A voiced consonant changes into its partner at the end of a word.') ?></p>
                </div>
            </div>

            <div class="pair-grid">
                <?php foreach ($pasangan_konsonan as $p): ?>
                    <div class="pair-card">
                        <button type="button" class="pair-letter" data-ucap="<?= htmlspecialchars($p['bersuara']) ?>" data-rate="0.7" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($p['bersuara']) ?>"><?= htmlspecialchars($p['bersuara']) ?></button>
                        <span class="pair-arrow">↔</span>
                        <button type="button" class="pair-letter" data-ucap="<?= htmlspecialchars($p['tumpul']) ?>" data-rate="0.7" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($p['tumpul']) ?>"><?= htmlspecialchars($p['tumpul']) ?></button>
                        <div class="pair-info">
                            <b>[<?= htmlspecialchars($p['bunyi']) ?>] ↔ [<?= htmlspecialchars($p['bunyi2']) ?>]</b>
                            <span><?= t('bersuara ↔ tak bersuara', 'voiced ↔ voiceless') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="tip-band" style="margin-top: 18px;">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p><?= t('<b>Jebakan paling umum:</b> В dibaca <b>v</b> (bukan b), Н dibaca <b>n</b> (bukan h), Р dibaca <b>r</b> (bukan p), С dibaca <b>s</b> (bukan c), У dibaca <b>u</b> (bukan y), Х dibaca <b>kh</b> (bukan x), Е dibaca <b>ye</b>, dan Ё dibaca <b>yo</b>.', '<b>Most common traps:</b> В is read <b>v</b> (not b), Н is read <b>n</b> (not h), Р is read <b>r</b> (not p), С is read <b>s</b> (not c), У is read <b>u</b> (not y), Х is read <b>kh</b> (not x), Е is read <b>ye</b>, and Ё is read <b>yo</b>.') ?></p>
            </div>
        </section>

        <!-- ============================================================
             BAGIAN 3: FONETIK
             ============================================================ -->
        <section class="alpha-section" id="fonetik">
            <div class="rv-section-head">
                <div>
                    <h2><span class="rv-icon-badge" style="background:#047857;"><svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg></span> <?= t('Aspek Fonetik dan Pengucapan', 'Phonetics and Pronunciation') ?></h2>
                    <p><?= t('Menulis huruf benar hanyalah setengah perjalanan. Aturan bunyi inilah yang membuat pelafalanmu terdengar alami.', 'Writing letters correctly is only half the journey. These sound rules are what make your pronunciation sound natural.') ?></p>
                </div>
            </div>

            <div class="principle-band">
                <div class="principle-badge">1 : 1</div>
                <div>
                    <h3><?= t('Prinsip "Satu Huruf, Satu Bunyi"', 'The "One Letter, One Sound" principle') ?></h3>
                    <p><?= t('Secara umum, pelafalan alfabet Rusia jauh lebih konsisten dan fonetik dibandingkan bahasa Inggris. Jika kamu tahu cara membaca hurufnya, kamu bisa melafalkan kata tersebut. Pengecualian utamanya adalah tekanan kata dan reduksi vokal tanpa tekanan.', 'In general, Russian spelling is far more consistent and phonetic than English. If you know how to read the letters, you can pronounce the word. The main exceptions are word stress and unstressed vowel reduction.') ?></p>
                </div>
            </div>

            <div class="phonetic-grid">
                <?php foreach ($fonetik as $f): ?>
                    <div class="phonetic-card">
                        <div class="phonetic-head">
                            <div class="phonetic-symbol"><?= htmlspecialchars($f['simbol']) ?></div>
                            <h4><?= htmlspecialchars($f['judul']) ?></h4>
                        </div>
                        <p><?= htmlspecialchars($f['desc']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="rv-section-head" style="margin-top: 22px;">
                <div>
                    <h3 style="font-size:16px;"><?= t('Pasangan vokal keras–lunak', 'Hard–soft vowel pairs') ?></h3>
                    <p><?= t('Vokal lunak е, ё, ю, я melembutkan konsonan sebelumnya. Di awal kata, setelah vokal lain, atau setelah ь/ъ, bunyinya menjadi ye, yo, yu, ya.', 'The soft vowels е, ё, ю, я soften the preceding consonant. At the start of a word, after another vowel, or after ь/ъ, they sound like ye, yo, yu, ya.') ?></p>
                </div>
            </div>

            <div class="pair-grid">
                <?php foreach ($pasangan_vokal as $v): ?>
                    <div class="pair-card">
                        <button type="button" class="pair-letter" data-ucap="<?= htmlspecialchars($v['keras']) ?>" data-rate="0.7" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($v['keras']) ?>"><?= htmlspecialchars($v['keras']) ?></button>
                        <span class="pair-arrow">↔</span>
                        <button type="button" class="pair-letter" data-ucap="<?= htmlspecialchars($v['lunak']) ?>" data-rate="0.7" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($v['lunak']) ?>"><?= htmlspecialchars($v['lunak']) ?></button>
                        <div class="pair-info">
                            <b>[<?= htmlspecialchars($v['bunyi']) ?>] ↔ [<?= htmlspecialchars($v['bunyi2']) ?>]</b>
                            <span><?= t('keras ↔ lunak', 'hard ↔ soft') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="tip-band" style="margin-top: 18px;">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p><?= t('<b>Latihan terbaik:</b> dengarkan bunyi huruf, tiru tanpa melihat teks, lalu bandingkan lagi. Jangan hanya menghafal transliterasi — telinga juga perlu dilatih.', '<b>Best practice:</b> listen to the letter sound, imitate it without looking at the text, then compare again. Do not only memorize transliteration — your ears need training too.') ?></p>
            </div>
        </section>

        <!-- ============================================================
             BAGIAN 4: TEKNIK MEMBACA & MENULIS
             ============================================================ -->
        <section class="alpha-section" id="teknik">
            <div class="rv-section-head">
                <div>
                    <h2><span class="rv-icon-badge" style="background:#BE185D;"><svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></span> <?= t('Teknik Membaca dan Menulis', 'Reading and Writing Techniques') ?></h2>
                    <p><?= t('Gunakan urutan latihan yang jelas supaya tidak kewalahan. Baca dulu, tulis kemudian, lalu gabungkan keduanya.', 'Follow a clear practice order so you do not get overwhelmed. Read first, write next, then combine both.') ?></p>
                </div>
            </div>

            <div class="steps">
                <?php foreach ($langkah_baca as $i => $s): ?>
                    <div class="step">
                        <div class="step-num"><?= $i + 1 ?></div>
                        <h4><?= htmlspecialchars($s['judul']) ?></h4>
                        <p><?= htmlspecialchars($s['isi']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="writing-technique">
                <div class="technique-card">
                    <h3><?= t('Teknik menulis yang efektif', 'Effective writing technique') ?></h3>
                    <p><?= t('Menulis Kiril bukan soal cepat-cepatan. Fokus pada bentuk yang konsisten agar mudah dibaca orang lain dan dirimu sendiri.', 'Writing Cyrillic is not about speed. Focus on consistent shapes so others and you yourself can read them easily.') ?></p>
                    <div class="technique-list">
                        <?php foreach ($teknik_tulis as $t): ?>
                            <div class="technique-item">
                                <div class="tick">✓</div>
                                <div>
                                    <b><?= htmlspecialchars($t['judul']) ?></b>
                                    <span><?= htmlspecialchars($t['isi']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="technique-card">
                    <h3><?= t('Huruf yang berubah di tulisan sambung', 'Letters that change in cursive') ?></h3>
                    <p><?= t('Tulisan cetak dan tulisan sambung (курсив) bisa terlihat sangat berbeda. Ini beberapa huruf yang sering membingungkan.', 'Print and cursive (курсив) can look very different. These are some letters that often confuse learners.') ?></p>
                    <table class="cursive-table">
                        <thead>
                            <tr><th><?= t('Cetak', 'Print') ?></th><th><?= t('Sambung', 'Cursive') ?></th><th><?= t('Catatan', 'Note') ?></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cursive_tips as $c): ?>
                                <tr>
                                    <td class="cursive-print"><?= htmlspecialchars($c['cetak']) ?></td>
                                    <td class="cursive-write"><?= htmlspecialchars($c['tulis']) ?></td>
                                    <td><?= htmlspecialchars($c['catatan']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tip-band">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p><?= t('Sudah lancar membaca? Lanjutkan ke', 'Reading smoothly already? Continue to') ?> <a href="kosakata.php" style="color:#92400E;font-weight:800;"><?= t('Pelajaran Kosa Kata', 'Vocabulary Lessons') ?></a><?= t(', lalu uji kemampuan menulismu di latihan Writing.', ', then test your writing skills in Writing Practice.') ?></p>
            </div>
        </section>
    </main>
</div>

<script src="assets/js/audio.js"></script>
<script src="assets/js/sound.js"></script>
<script>
    // ============================================================
    // Filtrar & pencarian huruf
    // ============================================================
    (function () {
        const kartu = Array.from(document.querySelectorAll('.alpha-card'));
        const tombolFilter = Array.from(document.querySelectorAll('#filter-tipe .rv-tab'));
        const inputCari = document.getElementById('cari-huruf');
        const kosong = document.getElementById('alpha-empty');
        let filterAktif = 'semua';

        function terapkan() {
            const kata = (inputCari.value || '').toLowerCase().trim();
            let tampil = 0;
            kartu.forEach(function (k) {
                const cocokTipe = filterAktif === 'semua' || k.dataset.tipe === filterAktif;
                const cocokCari = kata === '' || (k.dataset.cari || '').indexOf(kata) !== -1;
                const lolos = cocokTipe && cocokCari;
                k.hidden = !lolos;
                if (lolos) tampil++;
            });
            kosong.hidden = tampil !== 0;
        }

        tombolFilter.forEach(function (btn) {
            btn.addEventListener('click', function () {
                tombolFilter.forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                filterAktif = btn.dataset.filter;
                terapkan();
            });
        });
        inputCari.addEventListener('input', terapkan);
    })();

    // ============================================================
    // Scrollspy navigasi bagian
    // ============================================================
    (function () {
        const tautan = Array.from(document.querySelectorAll('.alpha-nav a'));
        const bagian = ['struktur', 'kelompok', 'fonetik', 'teknik']
            .map(function (id) { return document.getElementById(id); })
            .filter(Boolean);

        if (!('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                tautan.forEach(function (a) {
                    a.classList.toggle('is-active', a.getAttribute('href') === '#' + entry.target.id);
                });
            });
        }, { rootMargin: '-90px 0px -55% 0px', threshold: 0.1 });

        bagian.forEach(function (b) { observer.observe(b); });
    })();

</script>
<?php include __DIR__ . '/partials/target_harian.php'; ?>
</body>
</html>
