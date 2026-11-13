<?php
require_once __DIR__ . '/config/keamanan.php';

$lang = $_POST['lang'] ?? '';
if (csrf_valid() && in_array($lang, ['id', 'en'], true)) {
    $_SESSION['bahasa'] = $lang;
}

$redirect = (string)($_POST['redirect'] ?? 'dashboard.php');

// Hanya izinkan path internal agar tidak menjadi open redirect.
$valid = $redirect !== ''
    && strpos($redirect, '//') === false
    && strpos($redirect, ':') === false
    && preg_match('~^[A-Za-z0-9_\-./?=&%#]+$~', $redirect);

if (!$valid) {
    $redirect = 'dashboard.php';
}

header('Location: ' . $redirect);
exit();
