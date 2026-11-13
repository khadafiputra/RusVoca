-- ============================================================================
-- RusVoca - Materi Pelajaran Kosakata (gaya LingoHut)
-- ----------------------------------------------------------------------------
-- File ini menambah:
--   1. Kolom tb_pelajaran.materi          -> isi materi pelajaran (HTML ringan)
--   2. Kolom tb_kosakata.kelompok         -> pengelompokan kata dalam bab
--   3. Kolom tb_kosakata.contoh_kalimat   -> contoh pemakaian kata
--   4. Kolom tb_kosakata.arti_contoh      -> terjemahan contoh kalimat
--   5. 12 bab materi + kosakata + contoh kalimat
--
-- CARA PAKAI:
--   phpMyAdmin -> pilih db_rusvoca -> tab SQL -> tempel isi file ini.
--   Atau lewat terminal:
--     mysql -u root db_rusvoca < database/materi_pelajaran.sql
--
-- CATATAN:
--   - Bagian kolom juga diperbaiki otomatis oleh config/koneksi.php, jadi
--     aplikasi tetap berjalan walau file ini belum dijalankan.
--   - Penyisipan kosakata memakai pola "insert jika belum ada", sehingga
--     aman dijalankan ulang tanpa membuat data ganda.
--   - Materi bab 1 dan 2 akan diperbarui (kolom materi) setiap kali file ini
--     dijalankan. Bab 3 sampai 12 hanya ditambahkan jika belum ada.
-- ============================================================================


-- ============================================================================
-- BAGIAN 1 - SKEMA
-- ----------------------------------------------------------------------------
-- Menambah kolom hanya jika belum ada, sehingga file aman dijalankan ulang.
-- ============================================================================
DROP PROCEDURE IF EXISTS rv_tambah_kolom;
DELIMITER $$
CREATE PROCEDURE rv_tambah_kolom(IN p_tabel VARCHAR(64), IN p_kolom VARCHAR(64), IN p_definisi TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabel AND COLUMN_NAME = p_kolom
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_tabel, '` ADD COLUMN `', p_kolom, '` ', p_definisi);
        PREPARE rv_stmt FROM @ddl;
        EXECUTE rv_stmt;
        DEALLOCATE PREPARE rv_stmt;
    END IF;
END$$
DELIMITER ;

CALL rv_tambah_kolom('tb_pelajaran', 'materi', 'LONGTEXT NULL AFTER deskripsi');
CALL rv_tambah_kolom('tb_kosakata', 'kelompok', 'VARCHAR(80) NULL AFTER arti_indonesia');
CALL rv_tambah_kolom('tb_kosakata', 'contoh_kalimat', 'VARCHAR(255) NULL AFTER kelompok');
CALL rv_tambah_kolom('tb_kosakata', 'arti_contoh', 'VARCHAR(255) NULL AFTER contoh_kalimat');

DROP PROCEDURE IF EXISTS rv_tambah_kolom;


-- ============================================================================
-- BAGIAN 2 - MATERI BAB 1 & 2 (bab yang sudah ada)
-- ============================================================================
UPDATE tb_pelajaran SET
    judul_pelajaran = 'Bertemu Seseorang',
    deskripsi = 'Sapaan dasar dalam bahasa Rusia',
    materi = '<p>Bab ini melatih sapaan yang paling sering dipakai orang Rusia setiap hari. Sapaan dibedakan berdasarkan waktu dan tingkat formalitas: <strong>Привет</strong> untuk teman sebaya, sedangkan <strong>Здравствуйте</strong> untuk orang yang lebih tua atau situasi resmi.</p><h4>Kapan dipakai?</h4><ul><li><strong>Доброе утро</strong> dipakai pada pagi hari sampai sekitar pukul 12.</li><li><strong>Добрый день</strong> dipakai pada siang sampai sore.</li><li><strong>Добрый вечер</strong> dipakai mulai sore sampai malam.</li></ul><p>Untuk memperkenalkan diri, gunakan pola <strong>Меня зовут ...</strong> yang berarti nama saya .... Setelah itu jawab dengan <strong>Очень приятно</strong> (senang berkenalan).</p>'
WHERE nomor_pelajaran = 1;

UPDATE tb_pelajaran SET
    judul_pelajaran = 'Iya dan Terima Kasih',
    deskripsi = 'Belajar merespons dengan sopan',
    materi = '<p>Orang Rusia sangat menghargai kesopanan. Kata <strong>спасибо</strong> (terima kasih) dan <strong>пожалуйста</strong> (tolong / sama-sama) adalah dua kata yang paling sering muncul dalam percakapan sehari-hari.</p><h4>Catatan penting</h4><ul><li><strong>Пожалуйста</strong> bisa berarti tolong saat meminta, dan sama-sama saat menjawab terima kasih.</li><li>Gunakan <strong>извините</strong> untuk meminta perhatian atau permisi, dan <strong>простите</strong> untuk meminta maaf.</li></ul><p>Biasakan menjawab ajakan atau tawaran dengan <strong>да</strong> (iya), <strong>нет</strong> (tidak), atau <strong>конечно</strong> (tentu saja).</p>'
WHERE nomor_pelajaran = 2;


-- ============================================================================
-- BAGIAN 3 - BAB BARU (3 s.d. 12)
-- ============================================================================
INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 3, 'Angka 1–10', 'Hitung dari nol sampai seratus',
'<p>Angka Rusia termasuk pola yang harus dihafal karena bentuknya berubah mengikuti kata benda. Pada bab ini kamu mempelajari angka nol sampai seratus sebagai dasar berhitung.</p><h4>Pola cepat</h4><ul><li>Angka 1 sampai 10 dihafal satu per satu.</li><li>Angka 11 sampai 19 umumnya berakhiran <strong>-надцать</strong>, misalnya одиннадцать (sebelas).</li><li>Angka puluhan berakhiran <strong>-дцать</strong> atau <strong>-десят</strong>, misalnya двадцать (dua puluh).</li></ul><p>Latih dengan menghitung benda di sekitarmu, misalnya <strong>два яблока</strong> (dua apel) atau <strong>пять минут</strong> (lima menit).</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 3);

INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 4, 'Warna', 'Kenali nama warna dan penggunaannya',
'<p>Nama warna dalam bahasa Rusia mengikuti kata benda yang diterangkannya. Untuk bentuk dasar (maskulin), gunakan akhiran <strong>-ый</strong> seperti <strong>красный</strong> (merah).</p><h4>Perubahan akhiran</h4><ul><li>Maskulin: красный дом (rumah merah).</li><li>Feminin: красная книга (buku merah).</li><li>Netral: красное яблоко (apel merah).</li></ul><p>Untuk warna yang berakhiran <strong>-ий</strong> seperti <strong>синий</strong> (biru tua), bentuk femininnya menjadi <strong>синяя</strong>.</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 4);

INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 5, 'Keluarga', 'Perkenalkan anggota keluarga',
'<p>Kosakata keluarga sangat berguna saat memperkenalkan diri. Perhatikan bahwa bahasa Rusia membedakan saudara laki-laki dan perempuan, begitu juga anak laki-laki dan anak perempuan.</p><h4>Pola kalimat</h4><ul><li><strong>У меня есть ...</strong> berarti saya punya ....</li><li><strong>Это моя мама.</strong> berarti ini ibu saya.</li><li><strong>Это мой брат.</strong> berarti ini saudara laki-laki saya.</li></ul><p>Kata <strong>мой</strong> dipakai untuk kata benda maskulin, <strong>моя</strong> untuk feminin, dan <strong>моё</strong> untuk netral.</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 5);

INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 6, 'Makanan & Minuman', 'Makanan, minuman, dan frasa di meja makan',
'<p>Bab ini membahas makanan dan minuman sehari-hari. Saat memesan di kafe, kamu bisa memakai pola <strong>Я хочу ...</strong> (saya mau ...) atau <strong>Дайте, пожалуйста, ...</strong> (tolong beri ...).</p><h4>Di meja makan</h4><ul><li><strong>чай с сахаром</strong> berarti teh dengan gula.</li><li><strong>без сахара</strong> berarti tanpa gula.</li><li><strong>стакан воды</strong> berarti segelas air.</li></ul><p>Kata <strong>вода</strong> (air) berubah menjadi <strong>воду</strong> ketika menjadi objek, misalnya <strong>Я пью воду</strong> (saya minum air).</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 6);

INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 7, 'Hewan', 'Nama hewan dan suaranya',
'<p>Nama hewan dalam bahasa Rusia memiliki gender. Beberapa hewan memiliki bentuk maskulin dan feminin yang berbeda, misalnya <strong>кот</strong> (kucing jantan) dan <strong>кошка</strong> (kucing betina).</p><h4>Suara hewan</h4><ul><li>Собака лает. (Anjing menggonggong.)</li><li>Кошка мяукает. (Kucing mengeong.)</li><li>Птица поёт. (Burung berkicau.)</li></ul><p>Cobalah menyebut hewan peliharaan di sekitarmu memakai pola <strong>У меня есть ...</strong> (saya punya ...).</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 7);

INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 8, 'Anggota Tubuh', 'Bagian tubuh dan cara menyebut keluhan',
'<p>Kosakata tubuh dipakai saat berobat, berbelanja pakaian, atau menjelaskan keadaan diri. Beberapa kata memiliki bentuk jamak tidak beraturan, misalnya <strong>волосы</strong> (rambut).</p><h4>Pola kalimat</h4><ul><li><strong>У меня болит голова.</strong> berarti kepala saya sakit.</li><li><strong>У меня болят зубы.</strong> berarti gigi saya sakit.</li></ul><p>Gunakan <strong>болит</strong> untuk satu bagian tubuh dan <strong>болят</strong> untuk jamak.</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 8);

INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 9, 'Waktu & Hari', 'Jam, hari, dan penanda waktu',
'<p>Bab ini membantu kamu membicarakan waktu, hari, dan kebiasaan harian. Perhatikan bahwa <strong>час</strong> berarti jam (durasi atau pukul satu), sedangkan <strong>время</strong> berarti waktu secara umum.</p><h4>Penanda waktu</h4><ul><li><strong>сегодня</strong> (hari ini), <strong>вчера</strong> (kemarin), <strong>завтра</strong> (besok).</li><li><strong>утром</strong> (pada pagi hari), <strong>вечером</strong> (pada malam hari).</li></ul><p>Contoh: <strong>Завтра утром я учу русский язык.</strong> (Besok pagi saya belajar bahasa Rusia.)</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 9);

INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 10, 'Cuaca & Musim', 'Cuaca, musim, dan percakapan ringan',
'<p>Cuaca adalah topik pembuka percakapan yang ringan. Untuk menyatakan keadaan cuaca, bahasa Rusia memakai kata keterangan seperti <strong>жарко</strong> (panas) dan <strong>холодно</strong> (dingin) tanpa kata kerja.</p><h4>Pola kalimat</h4><ul><li><strong>Сегодня холодно.</strong> (Hari ini dingin.)</li><li><strong>Идёт дождь.</strong> (Hujan sedang turun.)</li><li><strong>Идёт снег.</strong> (Salju sedang turun.)</li></ul><p>Nama musim dipakai dengan bentuk <strong>весной, летом, осенью, зимой</strong> yang berarti pada musim ....</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 10);

INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 11, 'Arah & Perjalanan', 'Bertanya arah dan mengenal tempat umum',
'<p>Saat bepergian di Rusia, kemampuan bertanya arah sangat penting. Gunakan <strong>Где ...?</strong> (Di mana ...?) untuk menanyakan lokasi.</p><h4>Pola kalimat</h4><ul><li><strong>Где метро?</strong> (Di mana kereta bawah tanah?)</li><li><strong>Идите прямо.</strong> (Jalan lurus.)</li><li><strong>Поверните налево.</strong> (Belok ke kiri.)</li></ul><p>Kata <strong>здесь</strong> (di sini) dan <strong>там</strong> (di sana) sering dipakai bersama gestur tangan.</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 11);

INSERT INTO tb_pelajaran (nomor_pelajaran, judul_pelajaran, deskripsi, materi)
SELECT 12, 'Kata Kerja Sehari-hari', 'Kata kerja dasar untuk kegiatan sehari-hari',
'<p>Kata kerja adalah inti kalimat. Dalam bahasa Rusia, kata kerja berubah bentuk mengikuti subjek dan waktu. Pada bab ini kamu mempelajari bentuk kamus (infinitif) yang umumnya berakhiran <strong>-ть</strong>.</p><h4>Pola kalimat</h4><ul><li><strong>Я читаю книгу.</strong> (Saya membaca buku.)</li><li><strong>Он говорит по-русски.</strong> (Dia berbicara bahasa Rusia.)</li><li><strong>Мы слушаем музыку.</strong> (Kami mendengarkan musik.)</li></ul><p>Hafalkan pasangan kata kerja bersama objeknya agar mudah diingat, misalnya <strong>читать книгу</strong> (membaca buku).</p>'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM tb_pelajaran WHERE nomor_pelajaran = 12);


-- ============================================================================
-- BAGIAN 4 - KOSAKATA + CONTOH KALIMAT
-- ============================================================================
CREATE TEMPORARY TABLE tmp_kosakata_seed (
    nomor_pelajaran INT NOT NULL,
    kata_rusia VARCHAR(100) NOT NULL,
    cara_baca VARCHAR(100) NOT NULL,
    arti_indonesia VARCHAR(100) NOT NULL,
    kelompok VARCHAR(80) NULL,
    contoh_kalimat VARCHAR(255) NULL,
    arti_contoh VARCHAR(255) NULL
);

INSERT INTO tmp_kosakata_seed
    (nomor_pelajaran, kata_rusia, cara_baca, arti_indonesia, kelompok, contoh_kalimat, arti_contoh)
VALUES
-- Bab 1 - Bertemu Seseorang
(1, 'Привет', 'Privet', 'Halo (informal)', 'Sapaan', 'Привет, как дела?', 'Hai, apa kabar?'),
(1, 'Здравствуйте', 'Zdravstvuyte', 'Halo (formal)', 'Sapaan', 'Здравствуйте, меня зовут Ахмад.', 'Halo, nama saya Ahmad.'),
(1, 'Доброе утро', 'Dobroye utro', 'Selamat pagi', 'Sapaan', 'Доброе утро, мама!', 'Selamat pagi, ibu!'),
(1, 'Добрый день', 'Dobryy den', 'Selamat siang', 'Sapaan', 'Добрый день, Анна!', 'Selamat siang, Anna!'),
(1, 'Добрый вечер', 'Dobryy vecher', 'Selamat malam', 'Sapaan', 'Добрый вечер, друзья!', 'Selamat malam, teman-teman!'),
(1, 'Спокойной ночи', 'Spokoynoy nochi', 'Selamat tidur', 'Sapaan', 'Спокойной ночи!', 'Selamat tidur!'),
(1, 'Как дела?', 'Kak dela?', 'Apa kabar?', 'Perkenalan & Pamitan', 'Привет! Как дела?', 'Hai! Apa kabar?'),
(1, 'Хорошо', 'Khorosho', 'Baik', 'Perkenalan & Pamitan', 'Спасибо, хорошо.', 'Terima kasih, baik.'),
(1, 'Меня зовут', 'Menya zovut', 'Nama saya', 'Perkenalan & Pamitan', 'Меня зовут Ахмад.', 'Nama saya Ahmad.'),
(1, 'Очень приятно', 'Ochen priyatno', 'Senang berkenalan', 'Perkenalan & Pamitan', 'Очень приятно!', 'Senang berkenalan!'),
(1, 'До свидания', 'Do svidaniya', 'Sampai jumpa', 'Perkenalan & Pamitan', 'До свидания, до завтра!', 'Sampai jumpa, sampai besok!'),
(1, 'Пока', 'Poka', 'Dadah', 'Perkenalan & Pamitan', 'Пока, увидимся!', 'Dadah, sampai jumpa!'),
-- Bab 2 - Iya dan Terima Kasih
(2, 'Да', 'Da', 'Iya', 'Jawaban Sopan', 'Да, конечно.', 'Iya, tentu saja.'),
(2, 'Нет', 'Net', 'Tidak', 'Jawaban Sopan', 'Нет, спасибо.', 'Tidak, terima kasih.'),
(2, 'Спасибо', 'Spasibo', 'Terima kasih', 'Jawaban Sopan', 'Спасибо за помощь.', 'Terima kasih atas bantuannya.'),
(2, 'Пожалуйста', 'Pozhaluysta', 'Tolong / Sama-sama', 'Jawaban Sopan', 'Дайте, пожалуйста, воду.', 'Tolong beri air.'),
(2, 'Извините', 'Izvinite', 'Permisi', 'Jawaban Sopan', 'Извините, где метро?', 'Permisi, di mana kereta bawah tanah?'),
(2, 'Простите', 'Prostite', 'Maafkan saya', 'Jawaban Sopan', 'Простите, я опоздал.', 'Maafkan saya, saya terlambat.'),
(2, 'Конечно', 'Konechno', 'Tentu saja', 'Jawaban Sopan', 'Конечно, можно.', 'Tentu saja, boleh.'),
(2, 'Не за что', 'Ne za chto', 'Sama-sama', 'Ungkapan Tambahan', 'Не за что, это пустяки.', 'Sama-sama, tidak apa-apa.'),
(2, 'Огромное спасибо', 'Ogromnoye spasibo', 'Terima kasih banyak', 'Ungkapan Tambahan', 'Огромное спасибо за всё!', 'Terima kasih banyak untuk semuanya!'),
(2, 'Будьте добры', 'Budte dobry', 'Tolong (sopan)', 'Ungkapan Tambahan', 'Будьте добры, помогите мне.', 'Tolong bantu saya.'),
(2, 'С удовольствием', 'S udovolstviyem', 'Dengan senang hati', 'Ungkapan Tambahan', 'С удовольствием!', 'Dengan senang hati!'),
(2, 'Рад тебя видеть', 'Rad tebya videt', 'Senang melihatmu', 'Ungkapan Tambahan', 'Привет! Рад тебя видеть.', 'Hai! Senang melihatmu.'),
-- Bab 3 - Angka
(3, 'один', 'odin', 'satu', 'Angka Pokok', 'Один билет, пожалуйста.', 'Satu tiket, tolong.'),
(3, 'два', 'dva', 'dua', 'Angka Pokok', 'У меня два брата.', 'Saya punya dua saudara laki-laki.'),
(3, 'три', 'tri', 'tiga', 'Angka Pokok', 'Три яблока.', 'Tiga apel.'),
(3, 'четыре', 'chetyre', 'empat', 'Angka Pokok', 'Четыре студента.', 'Empat siswa.'),
(3, 'пять', 'pyat', 'lima', 'Angka Pokok', 'Пять минут.', 'Lima menit.'),
(3, 'шесть', 'shest', 'enam', 'Angka Pokok', 'Шесть книг.', 'Enam buku.'),
(3, 'семь', 'sem', 'tujuh', 'Angka Pokok', 'Семь дней в неделю.', 'Tujuh hari dalam seminggu.'),
(3, 'восемь', 'vosem', 'delapan', 'Angka Pokok', 'Восемь часов.', 'Jam delapan.'),
(3, 'девять', 'devyat', 'sembilan', 'Angka Pokok', 'Девять окон.', 'Sembilan jendela.'),
(3, 'десять', 'desyat', 'sepuluh', 'Angka Pokok', 'Десять рублей.', 'Sepuluh rubel.'),
(3, 'ноль', 'nol', 'nol', 'Angka Tambahan', 'Ноль градусов.', 'Nol derajat.'),
(3, 'сто', 'sto', 'seratus', 'Angka Tambahan', 'Сто рублей.', 'Seratus rubel.'),
-- Bab 4 - Warna
(4, 'красный', 'krasnyy', 'merah', 'Warna Dasar', 'Красное яблоко.', 'Apel merah.'),
(4, 'синий', 'siniy', 'biru', 'Warna Dasar', 'Синее небо.', 'Langit biru.'),
(4, 'зелёный', 'zelenyy', 'hijau', 'Warna Dasar', 'Зелёная трава.', 'Rumput hijau.'),
(4, 'жёлтый', 'zheltyy', 'kuning', 'Warna Dasar', 'Жёлтый лимон.', 'Lemon kuning.'),
(4, 'чёрный', 'chernyy', 'hitam', 'Warna Dasar', 'Чёрная кошка.', 'Kucing hitam.'),
(4, 'белый', 'belyy', 'putih', 'Warna Dasar', 'Белый снег.', 'Salju putih.'),
(4, 'оранжевый', 'oranzhevyy', 'oranye', 'Warna Lain', 'Оранжевый апельсин.', 'Jeruk oranye.'),
(4, 'розовый', 'rozovyy', 'merah muda', 'Warna Lain', 'Розовый цветок.', 'Bunga merah muda.'),
(4, 'фиолетовый', 'fioletovyy', 'ungu', 'Warna Lain', 'Фиолетовая книга.', 'Buku ungu.'),
(4, 'коричневый', 'korichnevyy', 'cokelat', 'Warna Lain', 'Коричневый стол.', 'Meja cokelat.'),
(4, 'серый', 'seryy', 'abu-abu', 'Warna Lain', 'Серый дом.', 'Rumah abu-abu.'),
(4, 'голубой', 'goluboy', 'biru muda', 'Warna Lain', 'Голубое небо.', 'Langit biru muda.'),
-- Bab 5 - Keluarga
(5, 'мама', 'mama', 'ibu', 'Keluarga Inti', 'Это моя мама.', 'Ini ibu saya.'),
(5, 'папа', 'papa', 'ayah', 'Keluarga Inti', 'Это мой папа.', 'Ini ayah saya.'),
(5, 'брат', 'brat', 'saudara laki-laki', 'Keluarga Inti', 'У меня есть брат.', 'Saya punya saudara laki-laki.'),
(5, 'сестра', 'sestra', 'saudara perempuan', 'Keluarga Inti', 'Моя сестра студентка.', 'Saudara perempuan saya seorang mahasiswa.'),
(5, 'сын', 'syn', 'anak laki-laki', 'Keluarga Inti', 'Мой сын учится.', 'Anak laki-laki saya belajar.'),
(5, 'дочь', 'doch', 'anak perempuan', 'Keluarga Inti', 'Это моя дочь.', 'Ini anak perempuan saya.'),
(5, 'бабушка', 'babushka', 'nenek', 'Keluarga Besar', 'Бабушка готовит суп.', 'Nenek memasak sup.'),
(5, 'дедушка', 'dedushka', 'kakek', 'Keluarga Besar', 'Дедушка читает газету.', 'Kakek membaca koran.'),
(5, 'муж', 'muzh', 'suami', 'Keluarga Besar', 'Это мой муж.', 'Ini suami saya.'),
(5, 'жена', 'zhena', 'istri', 'Keluarga Besar', 'Это моя жена.', 'Ini istri saya.'),
(5, 'семья', 'semya', 'keluarga', 'Keluarga Besar', 'У нас большая семья.', 'Kami punya keluarga besar.'),
(5, 'родители', 'roditeli', 'orang tua', 'Keluarga Besar', 'Мои родители дома.', 'Orang tua saya di rumah.'),
-- Bab 6 - Makanan & Minuman
(6, 'хлеб', 'khleb', 'roti', 'Makanan', 'Свежий хлеб.', 'Roti segar.'),
(6, 'мясо', 'myaso', 'daging', 'Makanan', 'Я люблю мясо.', 'Saya suka daging.'),
(6, 'рыба', 'ryba', 'ikan', 'Makanan', 'Рыба очень вкусная.', 'Ikan sangat enak.'),
(6, 'рис', 'ris', 'nasi', 'Makanan', 'Рис с овощами.', 'Nasi dengan sayuran.'),
(6, 'суп', 'sup', 'sup', 'Makanan', 'Горячий суп.', 'Sup panas.'),
(6, 'соль', 'sol', 'garam', 'Makanan', 'Добавьте соль.', 'Tambahkan garam.'),
(6, 'вода', 'voda', 'air', 'Minuman & Lainnya', 'Стакан воды.', 'Segelas air.'),
(6, 'чай', 'chay', 'teh', 'Minuman & Lainnya', 'Чай с молоком.', 'Teh dengan susu.'),
(6, 'кофе', 'kofe', 'kopi', 'Minuman & Lainnya', 'Кофе без сахара.', 'Kopi tanpa gula.'),
(6, 'молоко', 'moloko', 'susu', 'Minuman & Lainnya', 'Я пью молоко.', 'Saya minum susu.'),
(6, 'сахар', 'sakhar', 'gula', 'Minuman & Lainnya', 'Сахар в чае.', 'Gula di dalam teh.'),
(6, 'яблоко', 'yabloko', 'apel', 'Minuman & Lainnya', 'Красное яблоко.', 'Apel merah.'),
-- Bab 7 - Hewan
(7, 'кошка', 'koshka', 'kucing', 'Hewan Rumah', 'Кошка спит.', 'Kucing tidur.'),
(7, 'собака', 'sobaka', 'anjing', 'Hewan Rumah', 'Собака лает.', 'Anjing menggonggong.'),
(7, 'птица', 'ptitsa', 'burung', 'Hewan Rumah', 'Птица поёт.', 'Burung berkicau.'),
(7, 'лошадь', 'loshad', 'kuda', 'Hewan Rumah', 'Лошадь бежит.', 'Kuda berlari.'),
(7, 'корова', 'korova', 'sapi', 'Hewan Rumah', 'Корова даёт молоко.', 'Sapi memberi susu.'),
(7, 'свинья', 'svinya', 'babi', 'Hewan Rumah', 'Свинья живёт на ферме.', 'Babi hidup di peternakan.'),
(7, 'овца', 'ovtsa', 'domba', 'Hewan Lain', 'Овца ест траву.', 'Domba makan rumput.'),
(7, 'рыба', 'ryba', 'ikan', 'Hewan Lain', 'Рыба плавает.', 'Ikan berenang.'),
(7, 'мышь', 'mysh', 'tikus', 'Hewan Lain', 'Мышь маленькая.', 'Tikus kecil.'),
(7, 'тигр', 'tigr', 'harimau', 'Hewan Lain', 'Тигр сильный.', 'Harimau kuat.'),
(7, 'слон', 'slon', 'gajah', 'Hewan Lain', 'Слон большой.', 'Gajah besar.'),
(7, 'обезьяна', 'obezyana', 'monyet', 'Hewan Lain', 'Обезьяна умная.', 'Monyet pintar.'),
-- Bab 8 - Anggota Tubuh
(8, 'голова', 'golova', 'kepala', 'Kepala & Wajah', 'У меня болит голова.', 'Kepala saya sakit.'),
(8, 'глаз', 'glaz', 'mata', 'Kepala & Wajah', 'У неё красивые глаза.', 'Dia punya mata yang indah.'),
(8, 'ухо', 'ukho', 'telinga', 'Kepala & Wajah', 'Ухо слышит звук.', 'Telinga mendengar suara.'),
(8, 'нос', 'nos', 'hidung', 'Kepala & Wajah', 'У собаки холодный нос.', 'Anjing punya hidung dingin.'),
(8, 'рот', 'rot', 'mulut', 'Kepala & Wajah', 'Откройте рот.', 'Buka mulut.'),
(8, 'зуб', 'zub', 'gigi', 'Kepala & Wajah', 'У меня болит зуб.', 'Gigi saya sakit.'),
(8, 'рука', 'ruka', 'tangan', 'Tubuh & Anggota', 'Я мою руки.', 'Saya mencuci tangan.'),
(8, 'нога', 'noga', 'kaki', 'Tubuh & Anggota', 'У него длинные ноги.', 'Dia punya kaki panjang.'),
(8, 'сердце', 'serdtse', 'jantung', 'Tubuh & Anggota', 'Сердце бьётся быстро.', 'Jantung berdetak cepat.'),
(8, 'волосы', 'volosy', 'rambut', 'Tubuh & Anggota', 'У неё длинные волосы.', 'Dia punya rambut panjang.'),
(8, 'палец', 'palets', 'jari', 'Tubuh & Anggota', 'На руке пять пальцев.', 'Di tangan ada lima jari.'),
(8, 'спина', 'spina', 'punggung', 'Tubuh & Anggota', 'У меня болит спина.', 'Punggung saya sakit.'),
-- Bab 9 - Waktu & Hari
(9, 'время', 'vremya', 'waktu', 'Satuan Waktu', 'У меня нет времени.', 'Saya tidak punya waktu.'),
(9, 'час', 'chas', 'jam', 'Satuan Waktu', 'Один час.', 'Satu jam.'),
(9, 'минута', 'minuta', 'menit', 'Satuan Waktu', 'Подождите минуту.', 'Tunggu sebentar.'),
(9, 'день', 'den', 'hari', 'Satuan Waktu', 'Добрый день!', 'Selamat siang!'),
(9, 'неделя', 'nedelya', 'minggu', 'Satuan Waktu', 'На этой неделе.', 'Pada minggu ini.'),
(9, 'месяц', 'mesyats', 'bulan', 'Satuan Waktu', 'В следующем месяце.', 'Pada bulan depan.'),
(9, 'год', 'god', 'tahun', 'Satuan Waktu', 'С Новым годом!', 'Selamat Tahun Baru!'),
(9, 'сегодня', 'segodnya', 'hari ini', 'Penanda Waktu', 'Сегодня понедельник.', 'Hari ini Senin.'),
(9, 'вчера', 'vchera', 'kemarin', 'Penanda Waktu', 'Вчера было холодно.', 'Kemarin dingin.'),
(9, 'завтра', 'zavtra', 'besok', 'Penanda Waktu', 'Завтра я работаю.', 'Besok saya bekerja.'),
(9, 'утро', 'utro', 'pagi', 'Penanda Waktu', 'Доброе утро!', 'Selamat pagi!'),
(9, 'вечер', 'vecher', 'malam', 'Penanda Waktu', 'Добрый вечер!', 'Selamat malam!'),
-- Bab 10 - Cuaca & Musim
(10, 'погода', 'pogoda', 'cuaca', 'Cuaca', 'Какая сегодня погода?', 'Bagaimana cuaca hari ini?'),
(10, 'солнце', 'solntse', 'matahari', 'Cuaca', 'Солнце светит.', 'Matahari bersinar.'),
(10, 'дождь', 'dozhd', 'hujan', 'Cuaca', 'Идёт дождь.', 'Hujan turun.'),
(10, 'снег', 'sneg', 'salju', 'Cuaca', 'Идёт снег.', 'Salju turun.'),
(10, 'ветер', 'veter', 'angin', 'Cuaca', 'Сильный ветер.', 'Angin kencang.'),
(10, 'облако', 'oblako', 'awan', 'Cuaca', 'На небе облако.', 'Di langit ada awan.'),
(10, 'жарко', 'zharko', 'panas', 'Suhu & Musim', 'Сегодня жарко.', 'Hari ini panas.'),
(10, 'холодно', 'kholodno', 'dingin', 'Suhu & Musim', 'Зимой холодно.', 'Pada musim dingin dingin.'),
(10, 'весна', 'vesna', 'musim semi', 'Suhu & Musim', 'Весной тепло.', 'Pada musim semi hangat.'),
(10, 'лето', 'leto', 'musim panas', 'Suhu & Musim', 'Летом мы отдыхаем.', 'Pada musim panas kami berlibur.'),
(10, 'осень', 'osen', 'musim gugur', 'Suhu & Musim', 'Осенью идёт дождь.', 'Pada musim gugur hujan turun.'),
(10, 'зима', 'zima', 'musim dingin', 'Suhu & Musim', 'Зимой идёт снег.', 'Pada musim dingin salju turun.'),
-- Bab 11 - Arah & Perjalanan
(11, 'влево', 'vlevo', 'ke kiri', 'Arah', 'Поверните влево.', 'Belok ke kiri.'),
(11, 'вправо', 'vpravo', 'ke kanan', 'Arah', 'Поверните вправо.', 'Belok ke kanan.'),
(11, 'прямо', 'pryamo', 'lurus', 'Arah', 'Идите прямо.', 'Jalan lurus.'),
(11, 'здесь', 'zdes', 'di sini', 'Arah', 'Я здесь.', 'Saya di sini.'),
(11, 'там', 'tam', 'di sana', 'Arah', 'Магазин там.', 'Toko di sana.'),
(11, 'близко', 'blizko', 'dekat', 'Arah', 'Близко от дома.', 'Dekat dari rumah.'),
(11, 'далеко', 'daleko', 'jauh', 'Arah', 'Аэропорт далеко.', 'Bandara jauh.'),
(11, 'улица', 'ulitsa', 'jalan', 'Tempat Umum', 'Это главная улица.', 'Ini jalan utama.'),
(11, 'город', 'gorod', 'kota', 'Tempat Umum', 'Москва — большой город.', 'Moskow adalah kota besar.'),
(11, 'метро', 'metro', 'kereta bawah tanah', 'Tempat Umum', 'Где метро?', 'Di mana kereta bawah tanah?'),
(11, 'аэропорт', 'aeroport', 'bandara', 'Tempat Umum', 'Аэропорт рядом.', 'Bandara di dekat sini.'),
(11, 'вокзал', 'vokzal', 'stasiun kereta', 'Tempat Umum', 'Поезд на вокзале.', 'Kereta di stasiun.'),
-- Bab 12 - Kata Kerja Sehari-hari
(12, 'быть', 'byt', 'menjadi / ada', 'Kata Kerja Dasar', 'Я хочу быть врачом.', 'Saya ingin menjadi dokter.'),
(12, 'иметь', 'imet', 'memiliki', 'Kata Kerja Dasar', 'Я имею право.', 'Saya punya hak.'),
(12, 'делать', 'delat', 'melakukan', 'Kata Kerja Dasar', 'Что ты делаешь?', 'Apa yang kamu lakukan?'),
(12, 'говорить', 'govorit', 'berbicara', 'Kata Kerja Dasar', 'Я говорю по-русски.', 'Saya berbicara bahasa Rusia.'),
(12, 'читать', 'chitat', 'membaca', 'Kata Kerja Dasar', 'Мы читаем книгу.', 'Kami membaca buku.'),
(12, 'писать', 'pisat', 'menulis', 'Kata Kerja Dasar', 'Она пишет письмо.', 'Dia menulis surat.'),
(12, 'слушать', 'slushat', 'mendengarkan', 'Kata Kerja Harian', 'Слушайте музыку.', 'Dengarkan musik.'),
(12, 'смотреть', 'smotret', 'menonton', 'Kata Kerja Harian', 'Я смотрю фильм.', 'Saya menonton film.'),
(12, 'есть', 'yest', 'makan', 'Kata Kerja Harian', 'Я хочу есть.', 'Saya ingin makan.'),
(12, 'пить', 'pit', 'minum', 'Kata Kerja Harian', 'Я пью чай.', 'Saya minum teh.'),
(12, 'идти', 'idti', 'pergi', 'Kata Kerja Harian', 'Мы идём домой.', 'Kami pergi ke rumah.'),
(12, 'спать', 'spat', 'tidur', 'Kata Kerja Harian', 'Ребёнок спит.', 'Anak sedang tidur.');

INSERT INTO tb_kosakata
    (id_pelajaran, kata_rusia, cara_baca, arti_indonesia, kelompok, contoh_kalimat, arti_contoh)
SELECT p.id_pelajaran, t.kata_rusia, t.cara_baca, t.arti_indonesia, t.kelompok, t.contoh_kalimat, t.arti_contoh
FROM tmp_kosakata_seed t
JOIN tb_pelajaran p ON p.nomor_pelajaran = t.nomor_pelajaran
WHERE NOT EXISTS (
    SELECT 1 FROM tb_kosakata k
    WHERE k.id_pelajaran = p.id_pelajaran AND k.kata_rusia = t.kata_rusia
);

-- Lengkapi kolom baru untuk kosakata bab 1 yang sudah ada sebelumnya.
UPDATE tb_kosakata SET kelompok = 'Sapaan', contoh_kalimat = 'Привет, как дела?', arti_contoh = 'Hai, apa kabar?'
WHERE id_pelajaran = 1 AND kata_rusia = 'Привет' AND kelompok IS NULL;
UPDATE tb_kosakata SET kelompok = 'Sapaan', contoh_kalimat = 'Здравствуйте, меня зовут Ахмад.', arti_contoh = 'Halo, nama saya Ahmad.'
WHERE id_pelajaran = 1 AND kata_rusia = 'Здравствуйте' AND kelompok IS NULL;
UPDATE tb_kosakata SET kelompok = 'Sapaan', contoh_kalimat = 'Доброе утро, мама!', arti_contoh = 'Selamat pagi, ibu!'
WHERE id_pelajaran = 1 AND kata_rusia = 'Доброе утро' AND kelompok IS NULL;
UPDATE tb_kosakata SET kelompok = 'Perkenalan & Pamitan', contoh_kalimat = 'Привет! Как дела?', arti_contoh = 'Hai! Apa kabar?'
WHERE id_pelajaran = 1 AND kata_rusia = 'Как дела?' AND kelompok IS NULL;

DROP TEMPORARY TABLE tmp_kosakata_seed;

-- ============================================================================
-- SELESAI
-- ============================================================================
