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

// Contoh kata yang datanya perlu dirapikan agar tetap berupa kata Kiril.
$contoh_override = [
    'Ъ' => ['contoh' => 'Объект (Obyekt)', 'arti' => 'Objek'],
    'Ь' => ['contoh' => 'День (Den)', 'arti' => 'Hari'],
];

$vokal = ['А', 'Е', 'Ё', 'И', 'О', 'У', 'Ы', 'Э', 'Ю', 'Я'];
$tanda = ['Ъ', 'Ь'];

$grup_meta = [
    'latin_sama' => [
        'judul' => t('Kembaran Latin', 'Latin Lookalikes'),
        'sub'   => t('Bentuk & bunyi hampir sama', 'Similar shape and sound'),
        'desc'  => t('Huruf-huruf ini paling bersahabat untuk pemula karena bentuk dan bunyinya mirip huruf Latin.', 'These letters are the friendliest for beginners because their shape and sound resemble Latin letters.'),
        'kelas' => 'g-blue',
    ],
    'latin_trap' => [
        'judul' => t('Mirip Latin, Beda Bunyi', 'Latin Look, Different Sound'),
        'sub'   => t('Jangan tertipu bentuknya', 'Do not be fooled by the shape'),
        'desc'  => t('Bentuknya terlihat familiar, tetapi bunyinya berbeda. Ini kelompok yang paling sering membuat pemula salah baca.', 'They look familiar, but sound different. This is the group that most often makes beginners misread.'),
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

    $contoh_raw = trim((string)($row['contoh_kata'] ?? ''));
    $arti = trim((string)($row['arti_contoh'] ?? ''));
    if (isset($contoh_override[$besar])) {
        $contoh_raw = $contoh_override[$besar]['contoh'];
        $arti = $contoh_override[$besar]['arti'];
    }

    $kata_contoh = trim(preg_replace('/\s*\(.*\)\s*$/u', '', $contoh_raw));
    $translit_contoh = '';
    if (preg_match('/\((.*)\)\s*$/u', $contoh_raw, $m)) {
        $translit_contoh = trim($m[1]);
    }

    $tipe = in_array($besar, $vokal, true) ? 'vokal' : (in_array($besar, $tanda, true) ? 'tanda' : 'konsonan');
    $grup = $grup_huruf[$besar] ?? 'unik';

    $alfabet[] = [
        'besar'          => $besar,
        'kecil'          => $kecil,
        'baca'           => (string)($row['cara_baca'] ?? ''),
        'tipe'           => $tipe,
        'grup'           => $grup,
        'contoh_kata'    => $kata_contoh,
        'contoh_translit'=> $translit_contoh,
        'arti'           => $arti,
        'contoh_ucap'    => preg_match('/[А-Яа-яЁё]/u', $kata_contoh) ? $kata_contoh : '',
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
        'simbol' => 'О',
        'judul'  => t('Tekanan kata & akanye', 'Word stress & akanye'),
        'desc'   => t('Setiap kata Rusia punya satu tekanan utama. Huruf о yang TIDAK mendapat tekanan dibaca mendekati "a". Inilah sebabnya молоко terdengar seperti "malako", bukan "moloko".', 'Every Russian word has one main stress. An unstressed о is pronounced close to "a". That is why молоко sounds like "malako", not "moloko".'),
        'contoh' => [
            ['kata' => 'молоко', 'baca' => 'malako', 'arti' => t('susu', 'milk')],
            ['kata' => 'хорошо', 'baca' => 'kharasho', 'arti' => t('baik / bagus', 'good / fine')],
        ],
    ],
    [
        'simbol' => 'Д',
        'judul'  => t('Konsonan tumpul di akhir kata', 'Devoiced consonants at the end of a word'),
        'desc'   => t('Konsonan bersuara berubah menjadi pasangannya yang tak bersuara di akhir kata. Contoh: д menjadi т, б menjadi п, в menjadi ф, г menjadi к, ж menjadi ш, з menjadi с.', 'Voiced consonants change into their voiceless partners at the end of a word. For example: д becomes т, б becomes п, в becomes ф, г becomes к, ж becomes ш, з becomes с.'),
        'contoh' => [
            ['kata' => 'друг', 'baca' => 'druk', 'arti' => t('teman', 'friend')],
            ['kata' => 'хлеб', 'baca' => 'khlep', 'arti' => t('roti', 'bread')],
        ],
    ],
    [
        'simbol' => 'Ь',
        'judul'  => t('Keras vs lembut', 'Hard vs soft'),
        'desc'   => t('Tanda lunak ь dan vokal е, ё, и, ю, я membuat konsonan sebelumnya terdengar lebih "lembut" (dipalatalkan). Bandingkan мат dan мать — bedanya tipis tetapi penting.', 'The soft sign ь and the vowels е, ё, и, ю, я make the preceding consonant sound softer (palatalized). Compare мат and мать — the difference is subtle but important.'),
        'contoh' => [
            ['kata' => 'мат', 'baca' => 'mat', 'arti' => t('makian / mat', 'profanity / checkmate')],
            ['kata' => 'мать', 'baca' => 'mat’', 'arti' => t('ibu', 'mother')],
        ],
    ],
    [
        'simbol' => 'Ы',
        'judul'  => t('Bunyi ы yang khas', 'The distinctive ы sound'),
        'desc'   => t('ы adalah bunyi vokal "i" yang ditarik ke belakang, mirip "ui" tanpa bulat. Bahasa Indonesia tidak punya bunyi ini persis, jadi latih dengan pasangan быт dan бит.', 'ы is an "i" vowel pulled toward the back, similar to "ui" without rounded lips. Indonesian has no exact equivalent, so practice with the pair быт and бит.'),
        'contoh' => [
            ['kata' => 'быт', 'baca' => 'byt', 'arti' => t('kehidupan sehari-hari', 'everyday life')],
            ['kata' => 'бит', 'baca' => 'bit', 'arti' => t('bit', 'bit')],
        ],
    ],
    [
        'simbol' => 'Ш',
        'judul'  => t('Ш vs Щ', 'Ш vs Щ'),
        'desc'   => t('ш adalah "sy" pendek seperti pada "syukur", sedangkan щ adalah "sy" panjang dan lebih lembut. Bedakan lewat contoh шар dan щи.', 'ш is a short "sh" as in "ship", while щ is a longer and softer "sh". Distinguish them with шар and щи.'),
        'contoh' => [
            ['kata' => 'шар', 'baca' => 'shar', 'arti' => t('bola', 'ball')],
            ['kata' => 'щи', 'baca' => 'shchi', 'arti' => t('sup kubis', 'cabbage soup')],
        ],
    ],
    [
        'simbol' => 'Р',
        'judul'  => t('R yang bergetar', 'The rolled R'),
        'desc'   => t('р diucapkan dengan ujung lidah bergetar, bukan seperti r bahasa Indonesia yang ringan. Latih dengan kata pendek berulang: ра-ра-ра, ро-ро-ро.', 'р is pronounced with a vibrating tongue tip, unlike the light Indonesian r. Practice with repeated short syllables: ра-ра-ра, ро-ро-ро.'),
        'contoh' => [
            ['kata' => 'работа', 'baca' => 'rabota', 'arti' => t('pekerjaan', 'work')],
            ['kata' => 'рука', 'baca' => 'ruka', 'arti' => t('tangan', 'hand')],
        ],
    ],
    [
        'simbol' => 'Ъ',
        'judul'  => t('Ъ dan Ь tidak berbunyi', 'Ъ and Ь are silent'),
        'desc'   => t('Tanda keras ъ dan tanda lunak ь tidak memiliki bunyi sendiri. Keduanya hanya mengubah cara huruf di sekitarnya diucapkan. Jangan pernah membacanya sebagai huruf vokal.', 'The hard sign ъ and soft sign ь have no sound of their own. They only change how nearby letters are pronounced. Never read them as vowels.'),
        'contoh' => [
            ['kata' => 'подъезд', 'baca' => 'padyezd', 'arti' => t('pintu masuk / gang', 'entrance / alley')],
            ['kata' => 'день', 'baca' => 'dyen’', 'arti' => t('hari', 'day')],
        ],
    ],
    [
        'simbol' => 'Е',
        'judul'  => t('Vokal tanpa tekanan menyusut', 'Unstressed vowels reduce'),
        'desc'   => t('Vokal tanpa tekanan sering terdengar lebih pendek dan samar. е tanpa tekanan bisa mendekati "i", sedangkan а/о bisa mendekati "a" netral. Dengarkan contohnya, jangan hanya membaca huruf.', 'Unstressed vowels often sound shorter and less distinct. An unstressed е can approach "i", while а/о can approach a neutral "a". Listen to the examples instead of only reading the letters.'),
        'contoh' => [
            ['kata' => 'телефон', 'baca' => 'tilifon', 'arti' => t('telepon', 'telephone')],
            ['kata' => 'собака', 'baca' => 'sabaka', 'arti' => t('anjing', 'dog')],
        ],
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

// Langkah teknik membaca
$langkah_baca = [
    ['judul' => t('Kenali bentuk, bukan arti', 'Recognize the shape, not the meaning'), 'isi' => t('Latih dulu membedakan В/Б, Р/П, Н/И, dan Ш/Щ. Jangan buru-buru menerjemahkan; fokus pada bentuk huruf.', 'First practice telling В/Б, Р/П, Н/И, and Ш/Щ apart. Do not rush to translate; focus on letter shapes.')],
    ['judul' => t('Baca per suku kata', 'Read syllable by syllable'), 'isi' => t('Pecah kata menjadi kelompok kecil: мо-ло-ко. Setelah lancar, baru gabungkan menjadi satu kata utuh.', 'Break words into small groups: мо-ло-ко. Once smooth, join them into the whole word.')],
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

// Latihan kilat membaca
$latihan_baca = [
    ['kata' => 'Привет', 'baca' => 'Privet', 'arti' => t('Halo (informal)', 'Hello (informal)')],
    ['kata' => 'Спасибо', 'baca' => 'Spasiba', 'arti' => t('Terima kasih', 'Thank you')],
    ['kata' => 'Молоко', 'baca' => 'Malako', 'arti' => t('Susu', 'Milk')],
    ['kata' => 'Книга', 'baca' => 'Kniga', 'arti' => t('Buku', 'Book')],
    ['kata' => 'Школа', 'baca' => 'Shkola', 'arti' => t('Sekolah', 'School')],
    ['kata' => 'Дом', 'baca' => 'Dom', 'arti' => t('Rumah', 'House')],
    ['kata' => 'Хлеб', 'baca' => 'Khlep', 'arti' => t('Roti', 'Bread')],
    ['kata' => 'Друг', 'baca' => 'Druk', 'arti' => t('Teman', 'Friend')],
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
        .user-info-role { font-size: 11px; color: var(--text-muted); font-weight: 500; }
        .logout-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 6px; border-radius: 6px; transition: all 0.2s; }
        .logout-btn:hover { color: #EF4444; background-color: rgba(239, 68, 68, 0.1); }

        /* === KONTEN UTAMA === */
        .main-content { margin-left: 260px; flex: 1; padding: 34px 40px 70px; max-width: 1220px; }
        .alpha-section { scroll-margin-top: 96px; margin-bottom: 52px; }
        #latihan { scroll-margin-top: 96px; }

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
        .alpha-nav .nav-quiz { margin-left: auto; background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; }
        .alpha-nav .nav-quiz:hover { background: #FEF3C7; color: #92400E; }

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
        .alpha-example { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .alpha-example-text { min-width: 0; }
        .alpha-example-word { font-size: 14px; font-weight: 800; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .alpha-example-arti { font-size: 10px; color: var(--text-muted); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .alpha-example .rv-audio { width: 32px; height: 32px; }
        .alpha-example .rv-audio svg { width: 15px; height: 15px; }
        .alpha-empty { grid-column: 1 / -1; text-align: center; padding: 40px 20px; background: #fff; border: 1px dashed var(--border-color); border-radius: 14px; color: var(--text-muted); font-size: 13px; font-weight: 600; }

        /* Kelompok huruf */
        .group-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
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
        .phonetic-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
        .phonetic-card { background: #fff; border: 1px solid var(--border-color); border-radius: 16px; padding: 18px; transition: .18s; }
        .phonetic-card:hover { border-color: #BFDBFE; box-shadow: 0 10px 22px rgba(15, 63, 122, .06); }
        .phonetic-head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .phonetic-symbol { width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; background: var(--primary-blue); color: #fff; font-size: 19px; font-weight: 800; flex-shrink: 0; }
        .phonetic-card h4 { font-size: 13.5px; font-weight: 800; }
        .phonetic-card > p { font-size: 11.5px; color: var(--text-muted); line-height: 1.6; margin-bottom: 12px; }
        .phonetic-example { display: flex; align-items: center; gap: 10px; background: #F8FAFC; border: 1px solid var(--border-color); border-radius: 10px; padding: 8px 10px; margin-top: 8px; }
        .phonetic-example .word { font-size: 14px; font-weight: 800; color: var(--primary-blue); }
        .phonetic-example .read { font-size: 10.5px; color: var(--text-muted); font-weight: 600; }
        .phonetic-example .artinya { margin-left: auto; font-size: 10px; color: var(--text-muted); font-weight: 600; text-align: right; }

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

        /* Latihan kilat */
        .reading-quiz { background: #0B2F5E; border-bottom: 3px solid var(--accent-gold); border-radius: 22px; padding: 26px; color: #fff; box-shadow: 0 18px 36px rgba(15, 63, 122, .18); }
        .reading-quiz-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
        .reading-quiz-head h3 { font-size: 19px; font-weight: 800; }
        .reading-quiz-head p { font-size: 11.5px; color: rgba(255,255,255,.75); margin-top: 3px; }
        .quiz-counter { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); padding: 7px 13px; border-radius: 999px; font-size: 11px; font-weight: 800; }
        .quiz-word-row { display: flex; align-items: center; justify-content: center; gap: 12px; margin: 6px 0 4px; flex-wrap: wrap; }
        .quiz-word { font-size: 38px; font-weight: 800; letter-spacing: .5px; }
        .quiz-reading { text-align: center; font-size: 11px; color: rgba(255,255,255,.7); font-weight: 600; margin-bottom: 16px; }
        .quiz-options { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .quiz-option { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); color: #fff; padding: 12px; border-radius: 11px; font-size: 13px; font-weight: 800; cursor: pointer; transition: .18s; }
        .quiz-option:hover:not(:disabled) { background: rgba(255,255,255,.18); transform: translateY(-2px); }
        .quiz-option:disabled { cursor: default; }
        .quiz-option.correct { background: #10B981; border-color: #10B981; }
        .quiz-option.wrong { background: #EF4444; border-color: #EF4444; }
        .quiz-feedback { text-align: center; font-size: 12px; font-weight: 700; margin-top: 14px; min-height: 18px; color: #FDE68A; }
        .quiz-next { margin: 14px auto 0; display: none; background: #F2B233; color: #3B2500; border: none; border-radius: 10px; padding: 10px 22px; font-weight: 800; font-size: 12.5px; cursor: pointer; }
        .quiz-next:hover { background: #F7C04A; }
        .quiz-result { text-align: center; padding: 8px 0 2px; }
        .quiz-result .big { font-size: 46px; font-weight: 800; color: #FDE68A; line-height: 1; margin-bottom: 8px; }
        .quiz-result p { font-size: 12.5px; color: rgba(255,255,255,.8); margin-bottom: 16px; }
        .quiz-restart { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.28); color: #fff; padding: 10px 18px; border-radius: 10px; font-weight: 800; font-size: 12.5px; cursor: pointer; }
        .quiz-restart:hover { background: rgba(255,255,255,.24); }

        .tip-band { display: flex; gap: 12px; align-items: flex-start; background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 14px; padding: 14px 16px; margin-top: 18px; }
        .tip-band svg { flex-shrink: 0; color: #B45309; margin-top: 1px; }
        .tip-band p { font-size: 11.5px; color: #92400E; line-height: 1.6; font-weight: 600; }

        @media (max-width: 1100px) {
            .structure-grid { grid-template-columns: 1fr 1fr; }
            .group-grid { grid-template-columns: 1fr; }
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
            .alpha-nav .nav-quiz { margin-left: 0; }
            .structure-grid { grid-template-columns: 1fr; }
            .alphabet-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            .steps { grid-template-columns: 1fr; }
            .quiz-options { grid-template-columns: 1fr; }
            .alpha-search input { width: 100%; }
            .alpha-search { width: 100%; }
        }
    </style>
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
                <li class="nav-lang"><?= tombol_bahasa('sidebar') ?></li>
            </ul>
        </div>
        <div class="user-profile-bottom">
            <div class="avatar-circle"><svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg></div>
            <div class="user-info">
                <div class="user-info-name"><?= htmlspecialchars($nama_user); ?></div>
                <div class="user-info-role"><?= t('Siswa', 'Student') ?></div>
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
                <p><?= t('Mulai dari struktur dasar, kelompok huruf yang mirip, aturan fonetik, sampai teknik membaca dan menulis. Klik huruf mana pun untuk mendengar bunyinya.', 'Start with the basic structure, similar letter groups, phonetic rules, and reading and writing techniques. Click any letter to hear its sound.') ?></p>
                <div class="rv-hero-actions">
                    <a href="#struktur" class="rv-btn rv-btn-gold"><?= t('Mulai dari Struktur', 'Start with Structure') ?></a>
                    <a href="#latihan" class="rv-btn rv-btn-light"><?= t('Latihan Kilat', 'Quick Practice') ?></a>
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
            <a href="#kelompok"><b>02</b><span><?= t('Kelompok Huruf', 'Letter Groups') ?></span></a>
            <a href="#fonetik"><b>03</b><span><?= t('Fonetik', 'Phonetics') ?></span></a>
            <a href="#teknik"><b>04</b><span><?= t('Teknik Baca-Tulis', 'Reading & Writing') ?></span></a>
            <a href="#latihan" class="nav-quiz"><?= t('Latihan kilat', 'Quick practice') ?></a>
        </nav>

        <!-- ============================================================
             BAGIAN 1: STRUKTUR DASAR
             ============================================================ -->
        <section class="alpha-section" id="struktur">
            <div class="rv-section-head">
                <div>
                    <h2><span class="rv-icon-badge"><svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg></span> <?= t('Struktur Dasar Alfabet', 'Basic Alphabet Structure') ?></h2>
                    <p><?= t('Alfabet Rusia disebut <b>а́збука (azbuka)</b>. Ada 33 huruf: 10 vokal, 21 konsonan, dan 2 tanda yang tidak berbunyi.', 'The Russian alphabet is called <b>а́збука (azbuka)</b>. It has 33 letters: 10 vowels, 21 consonants, and 2 silent signs.') ?></p>
                </div>
            </div>

            <div class="structure-grid">
                <div class="structure-card main">
                    <h3><?= t('Tiga kelompok besar', 'Three major groups') ?></h3>
                    <p><?= t('Vokal menjadi inti suku kata, konsonan membentuk kerangka bunyi, sedangkan tanda mengubah cara baca tanpa menghasilkan bunyi sendiri.', 'Vowels form the core of syllables, consonants build the sound framework, and signs change pronunciation without producing a sound of their own.') ?></p>
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
                    <p><?= t('Inti suku kata. Bunyinya berubah tergantung tekanan.', 'The core of a syllable. Their sound changes with stress.') ?></p>
                    <div class="letters">А · Е · Ё · И · О · У · Ы · Э · Ю · Я</div>
                </div>
                <div class="structure-card">
                    <div class="big-stat"><?= $jumlah_konsonan ?></div>
                    <h4><?= t('Konsonan', 'Consonants') ?></h4>
                    <p><?= t('Banyak yang berpasangan bersuara–tak bersuara.', 'Many come in voiced–voiceless pairs.') ?></p>
                    <div class="letters">Б · В · Г · Д · Ж · З · Й · К · Л · М · Н · П · Р · С · Т · Ф · Х · Ц · Ч · Ш · Щ</div>
                </div>
                <div class="structure-card">
                    <div class="big-stat"><?= $jumlah_tanda ?></div>
                    <h4><?= t('Tanda', 'Signs') ?></h4>
                    <p><?= t('Tidak berbunyi, tetapi mengubah konsonan di sekitarnya.', 'Silent, but they alter nearby consonants.') ?></p>
                    <div class="letters">Ъ · Ь</div>
                </div>
            </div>

            <div class="tip-band">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p><?= t('<b>Cara pakai halaman ini:</b> klik tombol speaker di setiap kartu untuk mendengar contoh katanya. Gunakan filter atau kolom pencarian untuk menemukan huruf tertentu.', '<b>How to use this page:</b> click the speaker button on any card to hear its example word. Use the filters or search field to find a specific letter.') ?></p>
            </div>

            <div class="rv-section-head" style="margin-top: 26px;">
                <div>
                    <h3 style="font-size:16px;"><?= t('Peta 33 huruf', 'Map of 33 letters') ?></h3>
                    <p><?= t('Huruf besar, huruf kecil, cara baca, dan contoh kata dalam satu tampilan.', 'Uppercase, lowercase, pronunciation, and example words in one view.') ?></p>
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
                        <input type="search" id="cari-huruf" placeholder="<?= t('Cari huruf atau kata...', 'Search letters or words...') ?>" aria-label="<?= t('Cari huruf alfabet', 'Search alphabet letters') ?>">
                    </div>
                </div>
            </div>

            <div class="alphabet-grid" id="alphabet-grid">
                <?php foreach ($alfabet as $a): ?>
                    <article class="alpha-card" data-tipe="<?= $a['tipe'] ?>" data-grup="<?= $a['grup'] ?>" data-cari="<?= htmlspecialchars(strtr(strtolower($a['besar'] . ' ' . $a['kecil'] . ' ' . $a['baca'] . ' ' . $a['contoh_translit'] . ' ' . $a['contoh_kata'] . ' ' . $a['arti']), $peta_kecil)) ?>">
                        <span class="alpha-type t-<?= $a['tipe'] ?>"><?= $a['tipe'] === 'vokal' ? t('Vokal', 'Vowel') : ($a['tipe'] === 'tanda' ? t('Tanda', 'Sign') : t('Konsonan', 'Consonant')) ?></span>
                        <div class="alpha-pair">
                            <span class="alpha-upper"><?= htmlspecialchars($a['besar']) ?></span>
                            <span class="alpha-lower"><?= htmlspecialchars($a['kecil']) ?></span>
                        </div>
                        <div class="alpha-pronounce"><?= t('Dibaca', 'Read as') ?> [ <?= htmlspecialchars($a['baca']) ?> ]</div>
                        <div class="alpha-divider"></div>
                        <div class="alpha-example">
                            <div class="alpha-example-text">
                                <div class="alpha-example-word"><?= htmlspecialchars($a['contoh_kata']) ?></div>
                                <div class="alpha-example-arti"><?= htmlspecialchars($a['arti']) ?><?= $a['contoh_translit'] !== '' ? ' · ' . htmlspecialchars($a['contoh_translit']) : '' ?></div>
                            </div>
                            <?php if ($a['contoh_ucap'] !== ''): ?>
                                <button type="button" class="rv-audio" data-ucap="<?= htmlspecialchars($a['contoh_ucap']) ?>" data-rate="0.8" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($a['contoh_kata']) ?>" aria-label="<?= t('Dengarkan contoh kata', 'Listen to example word') ?> <?= htmlspecialchars($a['contoh_kata']) ?>">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>
                                </button>
                            <?php else: ?>
                                <button type="button" class="rv-audio" data-ucap="<?= htmlspecialchars($a['besar']) ?>" data-rate="0.7" title="<?= t('Dengarkan huruf', 'Listen to letter') ?>" aria-label="<?= t('Dengarkan huruf', 'Listen to letter') ?> <?= htmlspecialchars($a['besar']) ?>">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>
                                </button>
                            <?php endif; ?>
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
                    <p><?= t('Otak lebih cepat mengingat kalau huruf dikelompokkan. Kenali dulu mana yang aman, mana yang menjebak.', 'The brain remembers letters faster when they are grouped. First learn which are safe and which are tricky.') ?></p>
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
                                <button type="button" class="letter-chip" data-ucap="<?= htmlspecialchars($g['besar']) ?>" data-rate="0.7" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($g['besar']) ?>">
                                    <?= htmlspecialchars($g['besar']) ?> <small><?= htmlspecialchars($g['kecil']) ?> · <?= htmlspecialchars($g['contoh_translit'] ?: $g['baca']) ?></small>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                <p><?= t('<b>Jebakan paling umum:</b> В dibaca <b>v</b> (bukan b), Н dibaca <b>n</b> (bukan h), Р dibaca <b>r</b> (bukan p), С dibaca <b>s</b> (bukan c), У dibaca <b>u</b> (bukan y), dan Х dibaca <b>kh</b> (bukan x).', '<b>Most common traps:</b> В is read <b>v</b> (not b), Н is read <b>n</b> (not h), Р is read <b>r</b> (not p), С is read <b>s</b> (not c), У is read <b>u</b> (not y), and Х is read <b>kh</b> (not x).') ?></p>
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

            <div class="phonetic-grid">
                <?php foreach ($fonetik as $f): ?>
                    <div class="phonetic-card">
                        <div class="phonetic-head">
                            <div class="phonetic-symbol"><?= htmlspecialchars($f['simbol']) ?></div>
                            <h4><?= htmlspecialchars($f['judul']) ?></h4>
                        </div>
                        <p><?= htmlspecialchars($f['desc']) ?></p>
                        <?php foreach ($f['contoh'] as $c): ?>
                            <div class="phonetic-example">
                                <div>
                                    <div class="word"><?= htmlspecialchars($c['kata']) ?></div>
                                    <div class="read">[ <?= htmlspecialchars($c['baca']) ?> ]</div>
                                </div>
                                <div class="artinya"><?= htmlspecialchars($c['arti']) ?></div>
                                <button type="button" class="rv-audio is-small" data-ucap="<?= htmlspecialchars($c['kata']) ?>" data-rate="0.8" title="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($c['kata']) ?>" aria-label="<?= t('Dengarkan', 'Listen to') ?> <?= htmlspecialchars($c['kata']) ?>">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="tip-band" style="margin-top: 18px;">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p><?= t('<b>Latihan terbaik:</b> dengarkan satu kata, tiru tanpa melihat teks, lalu bandingkan lagi. Jangan hanya menghafal transliterasi — telinga juga perlu dilatih.', '<b>Best practice:</b> listen to one word, imitate it without looking at the text, then compare again. Do not only memorize transliteration — your ears need training too.') ?></p>
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

            <div class="rv-section-head" style="margin-top: 10px;">
                <div>
                    <h3 style="font-size:16px;"><?= t('Cek pemahaman: baca kata berikut', 'Check your understanding: read these words') ?></h3>
                    <p><?= t('Pilih cara baca yang benar. Gunakan tombol audio kalau perlu bantuan.', 'Choose the correct pronunciation. Use the audio button if you need help.') ?></p>
                </div>
            </div>

            <!-- LATIHAN KILAT -->
            <div class="reading-quiz" id="latihan">
                <div class="reading-quiz-head">
                    <div>
                        <h3><?= t('Latihan Kilat Membaca', 'Quick Reading Practice') ?></h3>
                        <p><?= t('4 soal acak dari kata-kata yang sering muncul.', '4 random questions from frequently used words.') ?></p>
                    </div>
                    <div class="quiz-counter" id="baca-counter"><?= t('Soal', 'Question') ?> 1 / 4</div>
                </div>
                <div id="baca-area"></div>
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
        const bagian = ['struktur', 'kelompok', 'fonetik', 'teknik', 'latihan']
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

    // ============================================================
    // Latihan kilat membaca
    // ============================================================
    (function () {
        const bank = <?= json_encode($latihan_baca, JSON_UNESCAPED_UNICODE) ?>;
        const TXT = {
            question: <?= json_encode(t('Soal', 'Question'), JSON_UNESCAPED_UNICODE) ?>,
            listen: <?= json_encode(t('Dengarkan kata', 'Listen to the word'), JSON_UNESCAPED_UNICODE) ?>,
            howRead: <?= json_encode(t('Bagaimana cara membaca kata ini?', 'How do you read this word?'), JSON_UNESCAPED_UNICODE) ?>,
            next: <?= json_encode(t('Lanjut', 'Next'), JSON_UNESCAPED_UNICODE) ?>,
            showResult: <?= json_encode(t('Lihat Hasil', 'See Result'), JSON_UNESCAPED_UNICODE) ?>,
            nextQuestion: <?= json_encode(t('Soal Berikutnya', 'Next Question'), JSON_UNESCAPED_UNICODE) ?>,
            correct: <?= json_encode(t('Benar!', 'Correct!'), JSON_UNESCAPED_UNICODE) ?>,
            wrong: <?= json_encode(t('Belum tepat. Cara bacanya:', 'Not quite. The correct reading is:'), JSON_UNESCAPED_UNICODE) ?>,
            done: <?= json_encode(t('Selesai', 'Finished'), JSON_UNESCAPED_UNICODE) ?>,
            retry: <?= json_encode(t('Terus latih telinga dan matamu ya!', 'Keep training your ears and eyes!'), JSON_UNESCAPED_UNICODE) ?>,
            perfect: <?= json_encode(t('Sempurna! Kamu sudah bisa membaca kata-kata ini dengan baik.', 'Perfect! You can read these words well.'), JSON_UNESCAPED_UNICODE) ?>,
            good: <?= json_encode(t('Bagus! Tinggal sedikit lagi menuju lancar.', 'Good! You are almost fluent.'), JSON_UNESCAPED_UNICODE) ?>,
            okay: <?= json_encode(t('Cukup baik. Ulangi bagian fonetik untuk memperkuat dasarnya.', 'Fairly good. Review the phonetics section to strengthen the basics.'), JSON_UNESCAPED_UNICODE) ?>,
            tryAgain: <?= json_encode(t('Coba Lagi', 'Try Again'), JSON_UNESCAPED_UNICODE) ?>
        };
        const area = document.getElementById('baca-area');
        const counter = document.getElementById('baca-counter');
        let soal = [];
        let indeks = 0;
        let benar = 0;

        function acak(arr) {
            const a = arr.slice();
            for (let i = a.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [a[i], a[j]] = [a[j], a[i]];
            }
            return a;
        }

        function mulai() {
            soal = acak(bank).slice(0, 4).map(function (item) {
                const salah = acak(bank.filter(function (x) { return x.baca !== item.baca; }))
                    .slice(0, 3).map(function (x) { return x.baca; });
                return {
                    kata: item.kata,
                    baca: item.baca,
                    arti: item.arti,
                    opsi: acak([item.baca].concat(salah))
                };
            });
            indeks = 0;
            benar = 0;
            tampilkan();
        }

        function tampilkan() {
            if (indeks >= soal.length) return hasil();
            const q = soal[indeks];
            counter.textContent = TXT.question + ' ' + (indeks + 1) + ' / ' + soal.length;
            area.innerHTML = '';

            const row = document.createElement('div');
            row.className = 'quiz-word-row';
            const kata = document.createElement('div');
            kata.className = 'quiz-word';
            kata.textContent = q.kata;
            row.appendChild(kata);

            const audio = document.createElement('button');
            audio.type = 'button';
            audio.className = 'rv-audio';
            audio.title = TXT.listen;
            audio.innerHTML = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" /></svg>';
            audio.addEventListener('click', function () {
                if (window.RusvocaSound) RusvocaSound.klik();
                ucapkanTeks(q.kata, { btn: audio, rate: 0.8 });
            });
            row.appendChild(audio);
            area.appendChild(row);

            const ket = document.createElement('div');
            ket.className = 'quiz-reading';
            ket.textContent = TXT.howRead;
            area.appendChild(ket);

            const grid = document.createElement('div');
            grid.className = 'quiz-options';
            q.opsi.forEach(function (opsi) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'quiz-option';
                btn.textContent = opsi;
                btn.addEventListener('click', function () {
                    pilih(btn, opsi, q);
                });
                grid.appendChild(btn);
            });
            area.appendChild(grid);

            const feedback = document.createElement('div');
            feedback.className = 'quiz-feedback';
            feedback.id = 'baca-feedback';
            area.appendChild(feedback);

            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'quiz-next';
            next.textContent = TXT.next + ' →';
            next.addEventListener('click', function () {
                indeks++;
                tampilkan();
            });
            area.appendChild(next);
        }

        function pilih(btn, opsi, q) {
            const semua = area.querySelectorAll('.quiz-option');
            semua.forEach(function (b) { b.disabled = true; });
            const feedback = document.getElementById('baca-feedback');

            if (opsi === q.baca) {
                btn.classList.add('correct');
                benar++;
                feedback.textContent = TXT.correct + ' ' + q.kata + ' = ' + q.arti;
                if (window.RusvocaSound) RusvocaSound.benar();
            } else {
                btn.classList.add('wrong');
                semua.forEach(function (b) {
                    if (b.textContent === q.baca) b.classList.add('correct');
                });
                feedback.textContent = TXT.wrong + ' ' + q.baca + ' (' + q.arti + ').';
                if (window.RusvocaSound) RusvocaSound.salah();
            }

            const next = area.querySelector('.quiz-next');
            if (next) {
                next.style.display = 'inline-block';
                next.textContent = (indeks === soal.length - 1) ? TXT.showResult + ' →' : TXT.nextQuestion + ' →';
            }
        }

        function hasil() {
            counter.textContent = TXT.done;
            const persen = Math.round((benar / soal.length) * 100);
            let pesan = TXT.retry;
            if (persen === 100) pesan = TXT.perfect;
            else if (persen >= 75) pesan = TXT.good;
            else if (persen >= 50) pesan = TXT.okay;

            area.innerHTML =
                '<div class="quiz-result">' +
                    '<div class="big">' + benar + '/' + soal.length + '</div>' +
                    '<p>' + pesan + '</p>' +
                    '<button type="button" class="quiz-restart" id="baca-ulang">' + TXT.tryAgain + '</button>' +
                '</div>';

            document.getElementById('baca-ulang').addEventListener('click', mulai);
            if (window.RusvocaSound) RusvocaSound.menang();
        }

        mulai();
    })();
</script>
</body>
</html>
