<?php
/**
 * config/keamanan.php
 * ---------------------------------------------------------------------------
 * Bootstrap keamanan aplikasi:
 * - Zona waktu tunggal (WIB) agar PHP dan MySQL tidak berbeda 7 jam.
 * - Sesi PHP dengan cookie yang dikeraskan (HttpOnly, SameSite, Secure).
 * - Token CSRF untuk semua form POST.
 * - Rate limit sederhana berbasis file untuk login/register/OTP.
 * - Deteksi mode pengembangan (localhost) untuk menampilkan OTP.
 * ---------------------------------------------------------------------------
 */

// Seluruh aplikasi memakai WIB (UTC+7). MySQL disamakan di config/koneksi.php.
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Jakarta');
}
date_default_timezone_set(APP_TIMEZONE);

/**
 * True bila aplikasi dijalankan di komputer lokal.
 * Dipakai untuk menampilkan OTP dan detail error saat pengembangan.
 */
function mode_pengembangan(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
    }
    $host = (string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '');
    $host = strtolower(preg_replace('/:\d+$/', '', $host));
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

// Di server produksi jangan tampilkan error ke pengunjung; catat ke file.
if (!mode_pengembangan()) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

// ---------------------------------------------------------------------------
// Sesi aman
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// ---------------------------------------------------------------------------
// Folder penyimpanan internal (throttle & log), dilindungi .htaccess.
// ---------------------------------------------------------------------------
function folder_penyimpanan(): string
{
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function folder_throttle(): string
{
    $dir = folder_penyimpanan() . '/throttle';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

/** Token CSRF milik sesi ini. Dibuat sekali lalu dipakai ulang. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Input hidden siap tempel di dalam <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Periksa token CSRF dari POST (atau token yang diberikan). */
function csrf_valid(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals((string)$_SESSION['csrf_token'], $token);
}

/** Buat token baru (dipakai setelah login berhasil). */
function csrf_perbarui(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------------------------
// Rate limit berbasis file (tanpa tabel database tambahan).
// Setiap kunci disimpan sebagai JSON: {"jumlah": n, "reset": unix_time}
// ---------------------------------------------------------------------------

/** Alamat IP pengunjung (cukup untuk aplikasi lokal/sederhana). */
function throttle_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function throttle_file(string $kunci): string
{
    return folder_throttle() . '/' . hash('sha256', $kunci) . '.json';
}

/**
 * Cek sisa kuota untuk sebuah kunci.
 *
 * @return array{boleh: bool, sisa: int, tunggu: int}
 */
function throttle_cek(string $kunci, int $maks = 5, int $jendela = 900): array
{
    $data = ['jumlah' => 0, 'reset' => time() + $jendela];
    $file = throttle_file($kunci);

    if (is_file($file)) {
        $isi  = @file_get_contents($file);
        $json = $isi !== false ? json_decode($isi, true) : null;
        if (is_array($json) && isset($json['jumlah'], $json['reset']) && (int)$json['reset'] > time()) {
            $data = ['jumlah' => (int)$json['jumlah'], 'reset' => (int)$json['reset']];
        }
    }

    $sisa = max(0, $maks - $data['jumlah']);
    return [
        'boleh'  => $sisa > 0,
        'sisa'   => $sisa,
        'tunggu' => max(0, $data['reset'] - time()),
    ];
}

/** Tambah satu percobaan untuk sebuah kunci. */
function throttle_tambah(string $kunci, int $jendela = 900): void
{
    $file = throttle_file($kunci);
    $fh   = @fopen($file, 'c+');
    if (!$fh) {
        return;
    }

    if (flock($fh, LOCK_EX)) {
        $isi       = stream_get_contents($fh);
        $json      = $isi !== '' ? json_decode($isi, true) : null;
        $sekarang  = time();

        if (!is_array($json) || !isset($json['reset']) || (int)$json['reset'] <= $sekarang) {
            $json = ['jumlah' => 0, 'reset' => $sekarang + $jendela];
        }

        $json['jumlah'] = (int)$json['jumlah'] + 1;

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($json));
        fflush($fh);
        flock($fh, LOCK_UN);
    }

    fclose($fh);
    @chmod($file, 0664);
}

/** Hapus catatan throttle (dipakai setelah login/OTP berhasil). */
function throttle_hapus(string $kunci): void
{
    $file = throttle_file($kunci);
    if (is_file($file)) {
        @unlink($file);
    }
}

/** Pesan ramah saat kuota percobaan habis. */
function throttle_pesan(int $detik): string
{
    $menit = max(1, (int)ceil($detik / 60));
    if (function_exists('t')) {
        return t(
            'Terlalu banyak percobaan. Coba lagi dalam ' . $menit . ' menit.',
            'Too many attempts. Try again in ' . $menit . ' minute(s).'
        );
    }
    return 'Terlalu banyak percobaan. Coba lagi dalam ' . $menit . ' menit.';
}

// ---------------------------------------------------------------------------
// OTP
// ---------------------------------------------------------------------------

/** Kode OTP 6 digit yang aman. */
function buat_otp(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
