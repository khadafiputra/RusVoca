<?php
/**
 * catat_belajar.php
 * ---------------------------------------------------------------------------
 * Endpoint heartbeat target belajar harian. Dipanggil berkala oleh
 * assets/js/target-harian.js untuk menambah waktu belajar siswa.
 * ---------------------------------------------------------------------------
 */
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/progres.php';
require_once __DIR__ . '/config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'pesan' => 'Sesi login berakhir.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Permintaan tidak valid.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Admin tidak terkena target harian.
if (pengguna_admin($koneksi)) {
    echo json_encode([
        'ok'            => true,
        'admin'         => true,
        'menit'         => 0,
        'target'        => 0,
        'tercapai'      => true,
        'baru_tercapai' => false,
    ]);
    exit();
}

$detik = (int)($_POST['detik'] ?? 0);
$data  = tambah_detik_belajar($koneksi, $user_id, $detik);

echo json_encode([
    'ok'            => true,
    'menit'         => (int)$data['menit'],
    'target'        => (int)$data['target'],
    'tercapai'      => (bool)$data['tercapai'],
    'baru_tercapai' => (bool)$data['baru_tercapai'],
]);
