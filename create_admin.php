<?php
// File: create_admin.php - SKRIP SEKALI PAKAI UNTUK MEMBUAT / MEMPERBARUI AKUN ADMIN
// PENTING: HAPUS FILE INI DARI SERVER ANDA SETELAH BERHASIL DIGUNAKAN!

require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

// --- UBAH INI DENGAN KREDENSIAL ADMIN YANG ANDA INGINKAN ---
$adminUsername = 'xshikata'; // GANTI INI!
$adminPassword = '@04Dec97'; // GANTI INI DENGAN PASSWORD KUAT DAN UNIK!
// -----------------------------------------------------------

echo "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Setup Admin</title>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #121212; color: #e0e0e0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background-color: #0a0a0a; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); text-align: center; max-width: 500px; width: 90%; }
        h1 { color: #d4a017; margin-bottom: 20px; }
        p { margin-bottom: 10px; }
        .success { color: #4caf50; font-weight: bold; }
        .error { color: #e53935; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Pengaturan Akun Admin</h1>";

if ($adminUsername === 'your_admin_username' || $adminPassword === 'your_strong_password') {
    echo "<p class='error'>ERROR: Harap ubah \$adminUsername dan \$adminPassword di dalam file <b>create_admin.php</b> sebelum menjalankannya!</p>";
} else {
    // Hash password sebelum disimpan
    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

    if (insertOrUpdateAdminUser($adminUsername, $hashedPassword)) {
        echo "<p class='success'>Akun admin berhasil dibuat/diperbarui!</p>";
        echo "<p><b>Username:</b> " . htmlspecialchars($adminUsername) . "</p>";
        echo "<p>Anda sekarang dapat login ke <a href='" . ADMIN_PATH . "login.php' style='color: #1e88e5; text-decoration: underline;'>halaman admin</a>.</p>";
        echo "<p class='error'><b>PENTING:</b> Hapus file <b>" . basename(__FILE__) . "</b> dari server Anda SEGERA untuk keamanan!</p>";
    } else {
        echo "<p class='error'>Gagal membuat/memperbarui akun admin. Periksa log error server untuk detail lebih lanjut.</p>";
    }
}

echo "</div></body></html>";
?>
