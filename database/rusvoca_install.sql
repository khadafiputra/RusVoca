-- ============================================================================
-- RusVoca - Instalasi database lengkap (struktur + konten dasar)
-- ----------------------------------------------------------------------------
-- Isi file:
--   1. Struktur semua tabel (tb_akun, tb_pelajaran, tb_kosakata, tb_alfabet,
--      tb_progres_belajar, tb_target_harian, tb_sesi_kuis, tb_jawaban_kuis).
--   2. Data 12 bab materi, 144 kosakata, dan 33 huruf alfabet Kiril.
--   3. TIDAK berisi akun/pengguna maupun riwayat belajar.
--
-- PERINGATAN:
--   File ini menghapus lalu membuat ulang tabel di database `db_rusvoca`.
--   Jangan import ke database yang sudah berisi data penting.
--
-- Cara pakai (phpMyAdmin):
--   Tab Import -> pilih file ini. Database `db_rusvoca` dibuat otomatis.
--
-- Cara pakai (terminal):
--   mysql -u root < database/rusvoca_install.sql
--
-- Setelah import, buat akun lewat halaman register, lalu jadikan admin:
--   UPDATE tb_akun SET role = 'admin' WHERE email = 'email-kamu@contoh.com';
-- ============================================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `db_rusvoca` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `db_rusvoca`;
DROP TABLE IF EXISTS `tb_akun`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_akun` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('siswa','admin') DEFAULT 'siswa',
  `target_menit_harian` int DEFAULT '10',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reset_otp` varchar(6) DEFAULT NULL,
  `reset_otp_expired` datetime DEFAULT NULL,
  `target_waktu` int DEFAULT '15',
  `tanggal_daftar` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tb_alfabet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_alfabet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `huruf_kiril` varchar(10) NOT NULL,
  `cara_baca` varchar(50) NOT NULL,
  `contoh_kata` varchar(100) NOT NULL,
  `arti_contoh` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tb_jawaban_kuis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_jawaban_kuis` (
  `id_jawaban` int NOT NULL AUTO_INCREMENT,
  `id_sesi` int NOT NULL,
  `urutan` int NOT NULL DEFAULT '0',
  `pertanyaan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jawaban_user` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `jawaban_benar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_benar` tinyint(1) NOT NULL DEFAULT '0',
  `tipe` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'teks',
  PRIMARY KEY (`id_jawaban`),
  KEY `idx_jawaban_sesi` (`id_sesi`),
  CONSTRAINT `fk_jawaban_sesi` FOREIGN KEY (`id_sesi`) REFERENCES `tb_sesi_kuis` (`id_sesi`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tb_kosakata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_kosakata` (
  `id_kosakata` int NOT NULL AUTO_INCREMENT,
  `id_pelajaran` int NOT NULL,
  `kata_rusia` varchar(100) NOT NULL,
  `cara_baca` varchar(100) NOT NULL,
  `arti_indonesia` varchar(100) NOT NULL,
  `kelompok` varchar(80) DEFAULT NULL,
  `contoh_kalimat` varchar(255) DEFAULT NULL,
  `arti_contoh` varchar(255) DEFAULT NULL,
  `file_audio` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_kosakata`),
  KEY `id_pelajaran` (`id_pelajaran`),
  CONSTRAINT `tb_kosakata_ibfk_1` FOREIGN KEY (`id_pelajaran`) REFERENCES `tb_pelajaran` (`id_pelajaran`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=145 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tb_pelajaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_pelajaran` (
  `id_pelajaran` int NOT NULL AUTO_INCREMENT,
  `nomor_pelajaran` int NOT NULL,
  `judul_pelajaran` varchar(100) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `materi` longtext,
  PRIMARY KEY (`id_pelajaran`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tb_progres_belajar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_progres_belajar` (
  `id_progres` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `id_pelajaran` int NOT NULL,
  `mode_belajar` varchar(50) NOT NULL,
  `skor` int DEFAULT '0',
  `status_selesai` tinyint(1) DEFAULT '0',
  `tanggal_akses` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_progres`),
  UNIQUE KEY `uniq_user_kelas_mode` (`user_id`,`id_pelajaran`,`mode_belajar`),
  KEY `id_pelajaran` (`id_pelajaran`),
  CONSTRAINT `tb_progres_belajar_ibfk_1` FOREIGN KEY (`id_pelajaran`) REFERENCES `tb_pelajaran` (`id_pelajaran`) ON DELETE CASCADE,
  CONSTRAINT `tb_progres_belajar_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `tb_akun` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tb_sesi_kuis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_sesi_kuis` (
  `id_sesi` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `id_pelajaran` int NOT NULL,
  `mode_belajar` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `skor` int NOT NULL DEFAULT '0',
  `jumlah_benar` int NOT NULL DEFAULT '0',
  `jumlah_soal` int NOT NULL DEFAULT '0',
  `tanggal_akses` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_sesi`),
  KEY `idx_sesi_user_tanggal` (`user_id`,`tanggal_akses`),
  KEY `idx_sesi_pelajaran` (`id_pelajaran`),
  CONSTRAINT `fk_sesi_pelajaran` FOREIGN KEY (`id_pelajaran`) REFERENCES `tb_pelajaran` (`id_pelajaran`) ON DELETE CASCADE,
  CONSTRAINT `fk_sesi_user` FOREIGN KEY (`user_id`) REFERENCES `tb_akun` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tb_target_harian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tb_target_harian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `durasi_menit` int DEFAULT '0',
  `status_tercapai` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_tanggal` (`user_id`,`tanggal`),
  CONSTRAINT `tb_target_harian_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `tb_akun` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

/*!40000 ALTER TABLE `tb_pelajaran` DISABLE KEYS */;
INSERT INTO `tb_pelajaran` (`id_pelajaran`, `nomor_pelajaran`, `judul_pelajaran`, `deskripsi`, `materi`) VALUES (1,1,'Bertemu Seseorang','Sapaan dasar dalam bahasa Rusia','<p>Bab ini melatih sapaan yang paling sering dipakai orang Rusia setiap hari. Sapaan dibedakan berdasarkan waktu dan tingkat formalitas: <strong>Привет</strong> untuk teman sebaya, sedangkan <strong>Здравствуйте</strong> untuk orang yang lebih tua atau situasi resmi.</p><h4>Kapan dipakai?</h4><ul><li><strong>Доброе утро</strong> dipakai pada pagi hari sampai sekitar pukul 12.</li><li><strong>Добрый день</strong> dipakai pada siang sampai sore.</li><li><strong>Добрый вечер</strong> dipakai mulai sore sampai malam.</li></ul><p>Untuk memperkenalkan diri, gunakan pola <strong>Меня зовут ...</strong> yang berarti nama saya .... Setelah itu jawab dengan <strong>Очень приятно</strong> (senang berkenalan).</p>'),(2,2,'Iya dan Terima Kasih','Belajar merespons dengan sopan','<p>Orang Rusia sangat menghargai kesopanan. Kata <strong>спасибо</strong> (terima kasih) dan <strong>пожалуйста</strong> (tolong / sama-sama) adalah dua kata yang paling sering muncul dalam percakapan sehari-hari.</p><h4>Catatan penting</h4><ul><li><strong>Пожалуйста</strong> bisa berarti tolong saat meminta, dan sama-sama saat menjawab terima kasih.</li><li>Gunakan <strong>извините</strong> untuk meminta perhatian atau permisi, dan <strong>простите</strong> untuk meminta maaf.</li></ul><p>Biasakan menjawab ajakan atau tawaran dengan <strong>да</strong> (iya), <strong>нет</strong> (tidak), atau <strong>конечно</strong> (tentu saja).</p>'),(3,3,'Angka 1–10','Hitung dari nol sampai seratus','<p>Angka Rusia termasuk pola yang harus dihafal karena bentuknya berubah mengikuti kata benda. Pada bab ini kamu mempelajari angka nol sampai seratus sebagai dasar berhitung.</p><h4>Pola cepat</h4><ul><li>Angka 1 sampai 10 dihafal satu per satu.</li><li>Angka 11 sampai 19 umumnya berakhiran <strong>-надцать</strong>, misalnya одиннадцать (sebelas).</li><li>Angka puluhan berakhiran <strong>-дцать</strong> atau <strong>-десят</strong>, misalnya двадцать (dua puluh).</li></ul><p>Latih dengan menghitung benda di sekitarmu, misalnya <strong>два яблока</strong> (dua apel) atau <strong>пять минут</strong> (lima menit).</p>'),(4,4,'Warna','Kenali nama warna dan penggunaannya','<p>Nama warna dalam bahasa Rusia mengikuti kata benda yang diterangkannya. Untuk bentuk dasar (maskulin), gunakan akhiran <strong>-ый</strong> seperti <strong>красный</strong> (merah).</p><h4>Perubahan akhiran</h4><ul><li>Maskulin: красный дом (rumah merah).</li><li>Feminin: красная книга (buku merah).</li><li>Netral: красное яблоко (apel merah).</li></ul><p>Untuk warna yang berakhiran <strong>-ий</strong> seperti <strong>синий</strong> (biru tua), bentuk femininnya menjadi <strong>синяя</strong>.</p>'),(5,5,'Keluarga','Perkenalkan anggota keluarga','<p>Kosakata keluarga sangat berguna saat memperkenalkan diri. Perhatikan bahwa bahasa Rusia membedakan saudara laki-laki dan perempuan, begitu juga anak laki-laki dan anak perempuan.</p><h4>Pola kalimat</h4><ul><li><strong>У меня есть ...</strong> berarti saya punya ....</li><li><strong>Это моя мама.</strong> berarti ini ibu saya.</li><li><strong>Это мой брат.</strong> berarti ini saudara laki-laki saya.</li></ul><p>Kata <strong>мой</strong> dipakai untuk kata benda maskulin, <strong>моя</strong> untuk feminin, dan <strong>моё</strong> untuk netral.</p>'),(6,6,'Makanan & Minuman','Makanan, minuman, dan frasa di meja makan','<p>Bab ini membahas makanan dan minuman sehari-hari. Saat memesan di kafe, kamu bisa memakai pola <strong>Я хочу ...</strong> (saya mau ...) atau <strong>Дайте, пожалуйста, ...</strong> (tolong beri ...).</p><h4>Di meja makan</h4><ul><li><strong>чай с сахаром</strong> berarti teh dengan gula.</li><li><strong>без сахара</strong> berarti tanpa gula.</li><li><strong>стакан воды</strong> berarti segelas air.</li></ul><p>Kata <strong>вода</strong> (air) berubah menjadi <strong>воду</strong> ketika menjadi objek, misalnya <strong>Я пью воду</strong> (saya minum air).</p>'),(7,7,'Hewan','Nama hewan dan suaranya','<p>Nama hewan dalam bahasa Rusia memiliki gender. Beberapa hewan memiliki bentuk maskulin dan feminin yang berbeda, misalnya <strong>кот</strong> (kucing jantan) dan <strong>кошка</strong> (kucing betina).</p><h4>Suara hewan</h4><ul><li>Собака лает. (Anjing menggonggong.)</li><li>Кошка мяукает. (Kucing mengeong.)</li><li>Птица поёт. (Burung berkicau.)</li></ul><p>Cobalah menyebut hewan peliharaan di sekitarmu memakai pola <strong>У меня есть ...</strong> (saya punya ...).</p>'),(8,8,'Anggota Tubuh','Bagian tubuh dan cara menyebut keluhan','<p>Kosakata tubuh dipakai saat berobat, berbelanja pakaian, atau menjelaskan keadaan diri. Beberapa kata memiliki bentuk jamak tidak beraturan, misalnya <strong>волосы</strong> (rambut).</p><h4>Pola kalimat</h4><ul><li><strong>У меня болит голова.</strong> berarti kepala saya sakit.</li><li><strong>У меня болят зубы.</strong> berarti gigi saya sakit.</li></ul><p>Gunakan <strong>болит</strong> untuk satu bagian tubuh dan <strong>болят</strong> untuk jamak.</p>'),(9,9,'Waktu & Hari','Jam, hari, dan penanda waktu','<p>Bab ini membantu kamu membicarakan waktu, hari, dan kebiasaan harian. Perhatikan bahwa <strong>час</strong> berarti jam (durasi atau pukul satu), sedangkan <strong>время</strong> berarti waktu secara umum.</p><h4>Penanda waktu</h4><ul><li><strong>сегодня</strong> (hari ini), <strong>вчера</strong> (kemarin), <strong>завтра</strong> (besok).</li><li><strong>утром</strong> (pada pagi hari), <strong>вечером</strong> (pada malam hari).</li></ul><p>Contoh: <strong>Завтра утром я учу русский язык.</strong> (Besok pagi saya belajar bahasa Rusia.)</p>'),(10,10,'Cuaca & Musim','Cuaca, musim, dan percakapan ringan','<p>Cuaca adalah topik pembuka percakapan yang ringan. Untuk menyatakan keadaan cuaca, bahasa Rusia memakai kata keterangan seperti <strong>жарко</strong> (panas) dan <strong>холодно</strong> (dingin) tanpa kata kerja.</p><h4>Pola kalimat</h4><ul><li><strong>Сегодня холодно.</strong> (Hari ini dingin.)</li><li><strong>Идёт дождь.</strong> (Hujan sedang turun.)</li><li><strong>Идёт снег.</strong> (Salju sedang turun.)</li></ul><p>Nama musim dipakai dengan bentuk <strong>весной, летом, осенью, зимой</strong> yang berarti pada musim ....</p>'),(11,11,'Arah & Perjalanan','Bertanya arah dan mengenal tempat umum','<p>Saat bepergian di Rusia, kemampuan bertanya arah sangat penting. Gunakan <strong>Где ...?</strong> (Di mana ...?) untuk menanyakan lokasi.</p><h4>Pola kalimat</h4><ul><li><strong>Где метро?</strong> (Di mana kereta bawah tanah?)</li><li><strong>Идите прямо.</strong> (Jalan lurus.)</li><li><strong>Поверните налево.</strong> (Belok ke kiri.)</li></ul><p>Kata <strong>здесь</strong> (di sini) dan <strong>там</strong> (di sana) sering dipakai bersama gestur tangan.</p>'),(12,12,'Kata Kerja Sehari-hari','Kata kerja dasar untuk kegiatan sehari-hari','<p>Kata kerja adalah inti kalimat. Dalam bahasa Rusia, kata kerja berubah bentuk mengikuti subjek dan waktu. Pada bab ini kamu mempelajari bentuk kamus (infinitif) yang umumnya berakhiran <strong>-ть</strong>.</p><h4>Pola kalimat</h4><ul><li><strong>Я читаю книгу.</strong> (Saya membaca buku.)</li><li><strong>Он говорит по-русски.</strong> (Dia berbicara bahasa Rusia.)</li><li><strong>Мы слушаем музыку.</strong> (Kami mendengarkan musik.)</li></ul><p>Hafalkan pasangan kata kerja bersama objeknya agar mudah diingat, misalnya <strong>читать книгу</strong> (membaca buku).</p>');
/*!40000 ALTER TABLE `tb_pelajaran` ENABLE KEYS */;

/*!40000 ALTER TABLE `tb_kosakata` DISABLE KEYS */;
INSERT INTO `tb_kosakata` (`id_kosakata`, `id_pelajaran`, `kata_rusia`, `cara_baca`, `arti_indonesia`, `kelompok`, `contoh_kalimat`, `arti_contoh`, `file_audio`) VALUES (1,1,'Привет','Privet','Halo (Informal)','Sapaan','Привет, как дела?','Hai, apa kabar?',NULL),(2,1,'Здравствуйте','Zdravstvuyte','Halo (Formal)','Sapaan','Здравствуйте, меня зовут Ахмад.','Halo, nama saya Ahmad.',NULL),(3,1,'Доброе утро','Dobroye utro','Selamat pagi','Sapaan','Доброе утро, мама!','Selamat pagi, ibu!',NULL),(4,1,'Как дела?','Kak dela?','Apa kabar?','Perkenalan & Pamitan','Привет! Как дела?','Hai! Apa kabar?',NULL),(5,1,'Добрый день','Dobryy den','Selamat siang','Sapaan','Добрый день, Анна!','Selamat siang, Anna!',NULL),(6,1,'Добрый вечер','Dobryy vecher','Selamat malam','Sapaan','Добрый вечер, друзья!','Selamat malam, teman-teman!',NULL),(7,1,'Спокойной ночи','Spokoynoy nochi','Selamat tidur','Sapaan','Спокойной ночи!','Selamat tidur!',NULL),(8,1,'Хорошо','Khorosho','Baik','Perkenalan & Pamitan','Спасибо, хорошо.','Terima kasih, baik.',NULL),(9,1,'Меня зовут','Menya zovut','Nama saya','Perkenalan & Pamitan','Меня зовут Ахмад.','Nama saya Ahmad.',NULL),(10,1,'Очень приятно','Ochen priyatno','Senang berkenalan','Perkenalan & Pamitan','Очень приятно!','Senang berkenalan!',NULL),(11,1,'До свидания','Do svidaniya','Sampai jumpa','Perkenalan & Pamitan','До свидания, до завтра!','Sampai jumpa, sampai besok!',NULL),(12,1,'Пока','Poka','Dadah','Perkenalan & Pamitan','Пока, увидимся!','Dadah, sampai jumpa!',NULL),(13,2,'Да','Da','Iya','Jawaban Sopan','Да, конечно.','Iya, tentu saja.',NULL),(14,2,'Нет','Net','Tidak','Jawaban Sopan','Нет, спасибо.','Tidak, terima kasih.',NULL),(15,2,'Спасибо','Spasibo','Terima kasih','Jawaban Sopan','Спасибо за помощь.','Terima kasih atas bantuannya.',NULL),(16,2,'Пожалуйста','Pozhaluysta','Tolong / Sama-sama','Jawaban Sopan','Дайте, пожалуйста, воду.','Tolong beri air.',NULL),(17,2,'Извините','Izvinite','Permisi','Jawaban Sopan','Извините, где метро?','Permisi, di mana kereta bawah tanah?',NULL),(18,2,'Простите','Prostite','Maafkan saya','Jawaban Sopan','Простите, я опоздал.','Maafkan saya, saya terlambat.',NULL),(19,2,'Конечно','Konechno','Tentu saja','Jawaban Sopan','Конечно, можно.','Tentu saja, boleh.',NULL),(20,2,'Не за что','Ne za chto','Sama-sama','Ungkapan Tambahan','Не за что, это пустяки.','Sama-sama, tidak apa-apa.',NULL),(21,2,'Огромное спасибо','Ogromnoye spasibo','Terima kasih banyak','Ungkapan Tambahan','Огромное спасибо за всё!','Terima kasih banyak untuk semuanya!',NULL),(22,2,'Будьте добры','Budte dobry','Tolong (sopan)','Ungkapan Tambahan','Будьте добры, помогите мне.','Tolong bantu saya.',NULL),(23,2,'С удовольствием','S udovolstviyem','Dengan senang hati','Ungkapan Tambahan','С удовольствием!','Dengan senang hati!',NULL),(24,2,'Рад тебя видеть','Rad tebya videt','Senang melihatmu','Ungkapan Tambahan','Привет! Рад тебя видеть.','Hai! Senang melihatmu.',NULL),(25,3,'один','odin','satu','Angka Pokok','Один билет, пожалуйста.','Satu tiket, tolong.',NULL),(26,3,'два','dva','dua','Angka Pokok','У меня два брата.','Saya punya dua saudara laki-laki.',NULL),(27,3,'три','tri','tiga','Angka Pokok','Три яблока.','Tiga apel.',NULL),(28,3,'четыре','chetyre','empat','Angka Pokok','Четыре студента.','Empat siswa.',NULL),(29,3,'пять','pyat','lima','Angka Pokok','Пять минут.','Lima menit.',NULL),(30,3,'шесть','shest','enam','Angka Pokok','Шесть книг.','Enam buku.',NULL),(31,3,'семь','sem','tujuh','Angka Pokok','Семь дней в неделю.','Tujuh hari dalam seminggu.',NULL),(32,3,'восемь','vosem','delapan','Angka Pokok','Восемь часов.','Jam delapan.',NULL),(33,3,'девять','devyat','sembilan','Angka Pokok','Девять окон.','Sembilan jendela.',NULL),(34,3,'десять','desyat','sepuluh','Angka Pokok','Десять рублей.','Sepuluh rubel.',NULL),(35,3,'ноль','nol','nol','Angka Tambahan','Ноль градусов.','Nol derajat.',NULL),(36,3,'сто','sto','seratus','Angka Tambahan','Сто рублей.','Seratus rubel.',NULL),(37,4,'красный','krasnyy','merah','Warna Dasar','Красное яблоко.','Apel merah.',NULL),(38,4,'синий','siniy','biru','Warna Dasar','Синее небо.','Langit biru.',NULL),(39,4,'зелёный','zelenyy','hijau','Warna Dasar','Зелёная трава.','Rumput hijau.',NULL),(40,4,'жёлтый','zheltyy','kuning','Warna Dasar','Жёлтый лимон.','Lemon kuning.',NULL),(41,4,'чёрный','chernyy','hitam','Warna Dasar','Чёрная кошка.','Kucing hitam.',NULL),(42,4,'белый','belyy','putih','Warna Dasar','Белый снег.','Salju putih.',NULL),(43,4,'оранжевый','oranzhevyy','oranye','Warna Lain','Оранжевый апельсин.','Jeruk oranye.',NULL),(44,4,'розовый','rozovyy','merah muda','Warna Lain','Розовый цветок.','Bunga merah muda.',NULL),(45,4,'фиолетовый','fioletovyy','ungu','Warna Lain','Фиолетовая книга.','Buku ungu.',NULL),(46,4,'коричневый','korichnevyy','cokelat','Warna Lain','Коричневый стол.','Meja cokelat.',NULL),(47,4,'серый','seryy','abu-abu','Warna Lain','Серый дом.','Rumah abu-abu.',NULL),(48,4,'голубой','goluboy','biru muda','Warna Lain','Голубое небо.','Langit biru muda.',NULL),(49,5,'мама','mama','ibu','Keluarga Inti','Это моя мама.','Ini ibu saya.',NULL),(50,5,'папа','papa','ayah','Keluarga Inti','Это мой папа.','Ini ayah saya.',NULL),(51,5,'брат','brat','saudara laki-laki','Keluarga Inti','У меня есть брат.','Saya punya saudara laki-laki.',NULL),(52,5,'сестра','sestra','saudara perempuan','Keluarga Inti','Моя сестра студентка.','Saudara perempuan saya seorang mahasiswa.',NULL),(53,5,'сын','syn','anak laki-laki','Keluarga Inti','Мой сын учится.','Anak laki-laki saya belajar.',NULL),(54,5,'дочь','doch','anak perempuan','Keluarga Inti','Это моя дочь.','Ini anak perempuan saya.',NULL),(55,5,'бабушка','babushka','nenek','Keluarga Besar','Бабушка готовит суп.','Nenek memasak sup.',NULL),(56,5,'дедушка','dedushka','kakek','Keluarga Besar','Дедушка читает газету.','Kakek membaca koran.',NULL),(57,5,'муж','muzh','suami','Keluarga Besar','Это мой муж.','Ini suami saya.',NULL),(58,5,'жена','zhena','istri','Keluarga Besar','Это моя жена.','Ini istri saya.',NULL),(59,5,'семья','semya','keluarga','Keluarga Besar','У нас большая семья.','Kami punya keluarga besar.',NULL),(60,5,'родители','roditeli','orang tua','Keluarga Besar','Мои родители дома.','Orang tua saya di rumah.',NULL),(61,6,'хлеб','khleb','roti','Makanan','Свежий хлеб.','Roti segar.',NULL),(62,6,'мясо','myaso','daging','Makanan','Я люблю мясо.','Saya suka daging.',NULL),(63,6,'рыба','ryba','ikan','Makanan','Рыба очень вкусная.','Ikan sangat enak.',NULL),(64,6,'рис','ris','nasi','Makanan','Рис с овощами.','Nasi dengan sayuran.',NULL),(65,6,'суп','sup','sup','Makanan','Горячий суп.','Sup panas.',NULL),(66,6,'соль','sol','garam','Makanan','Добавьте соль.','Tambahkan garam.',NULL),(67,6,'вода','voda','air','Minuman & Lainnya','Стакан воды.','Segelas air.',NULL),(68,6,'чай','chay','teh','Minuman & Lainnya','Чай с молоком.','Teh dengan susu.',NULL),(69,6,'кофе','kofe','kopi','Minuman & Lainnya','Кофе без сахара.','Kopi tanpa gula.',NULL),(70,6,'молоко','moloko','susu','Minuman & Lainnya','Я пью молоко.','Saya minum susu.',NULL),(71,6,'сахар','sakhar','gula','Minuman & Lainnya','Сахар в чае.','Gula di dalam teh.',NULL),(72,6,'яблоко','yabloko','apel','Minuman & Lainnya','Красное яблоко.','Apel merah.',NULL),(73,7,'кошка','koshka','kucing','Hewan Rumah','Кошка спит.','Kucing tidur.',NULL),(74,7,'собака','sobaka','anjing','Hewan Rumah','Собака лает.','Anjing menggonggong.',NULL),(75,7,'птица','ptitsa','burung','Hewan Rumah','Птица поёт.','Burung berkicau.',NULL),(76,7,'лошадь','loshad','kuda','Hewan Rumah','Лошадь бежит.','Kuda berlari.',NULL),(77,7,'корова','korova','sapi','Hewan Rumah','Корова даёт молоко.','Sapi memberi susu.',NULL),(78,7,'свинья','svinya','babi','Hewan Rumah','Свинья живёт на ферме.','Babi hidup di peternakan.',NULL),(79,7,'овца','ovtsa','domba','Hewan Lain','Овца ест траву.','Domba makan rumput.',NULL),(80,7,'рыба','ryba','ikan','Hewan Lain','Рыба плавает.','Ikan berenang.',NULL),(81,7,'мышь','mysh','tikus','Hewan Lain','Мышь маленькая.','Tikus kecil.',NULL),(82,7,'тигр','tigr','harimau','Hewan Lain','Тигр сильный.','Harimau kuat.',NULL),(83,7,'слон','slon','gajah','Hewan Lain','Слон большой.','Gajah besar.',NULL),(84,7,'обезьяна','obezyana','monyet','Hewan Lain','Обезьяна умная.','Monyet pintar.',NULL),(85,8,'голова','golova','kepala','Kepala & Wajah','У меня болит голова.','Kepala saya sakit.',NULL),(86,8,'глаз','glaz','mata','Kepala & Wajah','У неё красивые глаза.','Dia punya mata yang indah.',NULL),(87,8,'ухо','ukho','telinga','Kepala & Wajah','Ухо слышит звук.','Telinga mendengar suara.',NULL),(88,8,'нос','nos','hidung','Kepala & Wajah','У собаки холодный нос.','Anjing punya hidung dingin.',NULL),(89,8,'рот','rot','mulut','Kepala & Wajah','Откройте рот.','Buka mulut.',NULL),(90,8,'зуб','zub','gigi','Kepala & Wajah','У меня болит зуб.','Gigi saya sakit.',NULL),(91,8,'рука','ruka','tangan','Tubuh & Anggota','Я мою руки.','Saya mencuci tangan.',NULL),(92,8,'нога','noga','kaki','Tubuh & Anggota','У него длинные ноги.','Dia punya kaki panjang.',NULL),(93,8,'сердце','serdtse','jantung','Tubuh & Anggota','Сердце бьётся быстро.','Jantung berdetak cepat.',NULL),(94,8,'волосы','volosy','rambut','Tubuh & Anggota','У неё длинные волосы.','Dia punya rambut panjang.',NULL),(95,8,'палец','palets','jari','Tubuh & Anggota','На руке пять пальцев.','Di tangan ada lima jari.',NULL),(96,8,'спина','spina','punggung','Tubuh & Anggota','У меня болит спина.','Punggung saya sakit.',NULL),(97,9,'время','vremya','waktu','Satuan Waktu','У меня нет времени.','Saya tidak punya waktu.',NULL),(98,9,'час','chas','jam','Satuan Waktu','Один час.','Satu jam.',NULL),(99,9,'минута','minuta','menit','Satuan Waktu','Подождите минуту.','Tunggu sebentar.',NULL),(100,9,'день','den','hari','Satuan Waktu','Добрый день!','Selamat siang!',NULL),(101,9,'неделя','nedelya','minggu','Satuan Waktu','На этой неделе.','Pada minggu ini.',NULL),(102,9,'месяц','mesyats','bulan','Satuan Waktu','В следующем месяце.','Pada bulan depan.',NULL),(103,9,'год','god','tahun','Satuan Waktu','С Новым годом!','Selamat Tahun Baru!',NULL),(104,9,'сегодня','segodnya','hari ini','Penanda Waktu','Сегодня понедельник.','Hari ini Senin.',NULL),(105,9,'вчера','vchera','kemarin','Penanda Waktu','Вчера было холодно.','Kemarin dingin.',NULL),(106,9,'завтра','zavtra','besok','Penanda Waktu','Завтра я работаю.','Besok saya bekerja.',NULL),(107,9,'утро','utro','pagi','Penanda Waktu','Доброе утро!','Selamat pagi!',NULL),(108,9,'вечер','vecher','malam','Penanda Waktu','Добрый вечер!','Selamat malam!',NULL),(109,10,'погода','pogoda','cuaca','Cuaca','Какая сегодня погода?','Bagaimana cuaca hari ini?',NULL),(110,10,'солнце','solntse','matahari','Cuaca','Солнце светит.','Matahari bersinar.',NULL),(111,10,'дождь','dozhd','hujan','Cuaca','Идёт дождь.','Hujan turun.',NULL),(112,10,'снег','sneg','salju','Cuaca','Идёт снег.','Salju turun.',NULL),(113,10,'ветер','veter','angin','Cuaca','Сильный ветер.','Angin kencang.',NULL),(114,10,'облако','oblako','awan','Cuaca','На небе облако.','Di langit ada awan.',NULL),(115,10,'жарко','zharko','panas','Suhu & Musim','Сегодня жарко.','Hari ini panas.',NULL),(116,10,'холодно','kholodno','dingin','Suhu & Musim','Зимой холодно.','Pada musim dingin dingin.',NULL),(117,10,'весна','vesna','musim semi','Suhu & Musim','Весной тепло.','Pada musim semi hangat.',NULL),(118,10,'лето','leto','musim panas','Suhu & Musim','Летом мы отдыхаем.','Pada musim panas kami berlibur.',NULL),(119,10,'осень','osen','musim gugur','Suhu & Musim','Осенью идёт дождь.','Pada musim gugur hujan turun.',NULL),(120,10,'зима','zima','musim dingin','Suhu & Musim','Зимой идёт снег.','Pada musim dingin salju turun.',NULL),(121,11,'влево','vlevo','ke kiri','Arah','Поверните влево.','Belok ke kiri.',NULL),(122,11,'вправо','vpravo','ke kanan','Arah','Поверните вправо.','Belok ke kanan.',NULL),(123,11,'прямо','pryamo','lurus','Arah','Идите прямо.','Jalan lurus.',NULL),(124,11,'здесь','zdes','di sini','Arah','Я здесь.','Saya di sini.',NULL),(125,11,'там','tam','di sana','Arah','Магазин там.','Toko di sana.',NULL),(126,11,'близко','blizko','dekat','Arah','Близко от дома.','Dekat dari rumah.',NULL),(127,11,'далеко','daleko','jauh','Arah','Аэропорт далеко.','Bandara jauh.',NULL),(128,11,'улица','ulitsa','jalan','Tempat Umum','Это главная улица.','Ini jalan utama.',NULL),(129,11,'город','gorod','kota','Tempat Umum','Москва — большой город.','Moskow adalah kota besar.',NULL),(130,11,'метро','metro','kereta bawah tanah','Tempat Umum','Где метро?','Di mana kereta bawah tanah?',NULL),(131,11,'аэропорт','aeroport','bandara','Tempat Umum','Аэропорт рядом.','Bandara di dekat sini.',NULL),(132,11,'вокзал','vokzal','stasiun kereta','Tempat Umum','Поезд на вокзале.','Kereta di stasiun.',NULL),(133,12,'быть','byt','menjadi / ada','Kata Kerja Dasar','Я хочу быть врачом.','Saya ingin menjadi dokter.',NULL),(134,12,'иметь','imet','memiliki','Kata Kerja Dasar','Я имею право.','Saya punya hak.',NULL),(135,12,'делать','delat','melakukan','Kata Kerja Dasar','Что ты делаешь?','Apa yang kamu lakukan?',NULL),(136,12,'говорить','govorit','berbicara','Kata Kerja Dasar','Я говорю по-русски.','Saya berbicara bahasa Rusia.',NULL),(137,12,'читать','chitat','membaca','Kata Kerja Dasar','Мы читаем книгу.','Kami membaca buku.',NULL),(138,12,'писать','pisat','menulis','Kata Kerja Dasar','Она пишет письмо.','Dia menulis surat.',NULL),(139,12,'слушать','slushat','mendengarkan','Kata Kerja Harian','Слушайте музыку.','Dengarkan musik.',NULL),(140,12,'смотреть','smotret','menonton','Kata Kerja Harian','Я смотрю фильм.','Saya menonton film.',NULL),(141,12,'есть','yest','makan','Kata Kerja Harian','Я хочу есть.','Saya ingin makan.',NULL),(142,12,'пить','pit','minum','Kata Kerja Harian','Я пью чай.','Saya minum teh.',NULL),(143,12,'идти','idti','pergi','Kata Kerja Harian','Мы идём домой.','Kami pergi ke rumah.',NULL),(144,12,'спать','spat','tidur','Kata Kerja Harian','Ребёнок спит.','Anak sedang tidur.',NULL);
/*!40000 ALTER TABLE `tb_kosakata` ENABLE KEYS */;

/*!40000 ALTER TABLE `tb_alfabet` DISABLE KEYS */;
INSERT INTO `tb_alfabet` (`id`, `huruf_kiril`, `cara_baca`, `contoh_kata`, `arti_contoh`) VALUES (1,'А а','A','Автобус (Avtobus)','Bus'),(2,'Б б','Be','Брат (Brat)','Saudara laki-laki'),(3,'В в','Ve','Вода (Voda)','Air'),(4,'Г г','Ge','Город (Gorod)','Kota'),(5,'Д д','De','Дом (Dom)','Rumah'),(6,'Е е','Ye','Еда (Yeda)','Makanan'),(7,'Ё ё','Yo','Ёлка (Yolka)','Pohon natal / cemara'),(8,'Ж ж','Zhe','Жена (Zhena)','Istri'),(9,'З з','Ze','Зима (Zima)','Musim dingin'),(10,'И и','I','Имя (Imya)','Nama'),(11,'Й й','I Kratkoye (Y)','Чай (Chay)','Teh'),(12,'К к','Ka','Книга (Kniga)','Buku'),(13,'Л л','El','Любовь (Lyubov)','Cinta'),(14,'М м','Em','Мама (Mama)','Ibu'),(15,'Н н','En','Ночь (Noch)','Malam'),(16,'О о','O','Окно (Okno)','Jendela'),(17,'П п','Pe','Папа (Papa)','Ayah'),(18,'Р р','Er','Работа (Rabota)','Pekerjaan'),(19,'С с','Es','Солнце (Solntse)','Matahari'),(20,'Т т','Te','Телефон (Telefon)','Telepon'),(21,'У у','U','Утро (Utro)','Pagi'),(22,'Ф ф','Ef','Фото (Foto)','Foto'),(23,'Х х','Kha','Хлеб (Khleb)','Roti'),(24,'Ц ц','Tse','Центр (Tsentr)','Pusat / Tengah'),(25,'Ч ч','Che','Часы (Chasy)','Jam / Arloji'),(26,'Ш ш','Sha','Школа (Shkola)','Sekolah'),(27,'Щ щ','Shcha','Борщ (Borsch)','Sup bit Rusia'),(28,'Ъ ъ','Tvyordyy Znak (Tanda Keras)','Subjek lunak/Keras','Pemisah pelafalan (tanpa bunyi)'),(29,'Ы ы','Yery (I Berat)','Сыр (Syr)','Keju'),(30,'Ь ь','Myagkiy Znak (Tanda Kelembutan)','День (Den)','Penghalus huruf konsonan akhir'),(31,'Э э','E','Это (Eto)','Ini'),(32,'Ю ю','Yu','Юг (Yug)','Selatan'),(33,'Я я','Ya','Яблоко (Yabloko)','Apel');
/*!40000 ALTER TABLE `tb_alfabet` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

