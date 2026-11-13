<?php
require_once __DIR__ . '/config/keamanan.php';

// Logout hanya lewat POST + token CSRF yang sah.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    header("Location: dashboard.php");
    exit();
}

session_unset();
session_destroy();

header("Location: index.php");
exit();
