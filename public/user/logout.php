<?php
session_start();

// Hanya hapus session milik user, bukan admin
unset($_SESSION['user_id']);
unset($_SESSION['nama']);
unset($_SESSION['role']);

// Opsional: pastikan tidak ada data user tersisa
if (isset($_SESSION['admin_id'])) {
    // jangan hapus admin
}

// redirect ke halaman utama
header("Location: ../index.php");
exit;
?>
