<?php
// File: generate_admin_hash.php
$admin_password = '@04Dec97'; // GANTI INI dengan password admin yang Anda inginkan
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
echo "Hash password Anda: " . $hashed_password;
?>