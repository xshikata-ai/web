<?php
// File: logout.php
require_once __DIR__ . '/include/config.php'; // Memuat BASE_URL dan akan memulai sesi

// Hancurkan semua data sesi
$_SESSION = array();

// Jika ingin menghancurkan sesi, hapus juga cookie sesi.
// Catatan: Ini akan menghancurkan sesi, bukan hanya data sesi!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Akhiri sesi
session_destroy();

// Arahkan kembali ke halaman utama atau halaman login
header("Location: " . BASE_URL);
exit();
?>