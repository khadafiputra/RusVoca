<?php
/**
 * admin/auth.php
 * ---------------------------------------------------------------------------
 * Gerbang keamanan semua halaman di folder /admin.
 * - Memastikan session aktif
 * - Memastikan user sudah login
 * - Memastikan user berperan 'admin' (siswa dialihkan ke dashboard)
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';

// Halaman admin memakai path relatif ke root (satu tingkat ke atas).
wajib_admin($koneksi, '../login.php', '../dashboard.php');

$nama_admin = $_SESSION['nama'] ?? 'Admin';
