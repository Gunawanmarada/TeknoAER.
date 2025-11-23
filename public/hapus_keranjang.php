<?php
session_start();
// PERBAIKAN PATH: Keluar satu tingkat (dari public/) ke tekno-aer/config/
include '../config/db.php';

// Pastikan user login
if (!isset($_SESSION['user_id'])) {
    // Redirect ke user/login.php
    header("Location: user/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    // Redirect ke keranjang.php (di folder yang sama)
    echo "<script>alert('ID keranjang tidak ditemukan'); window.location='keranjang.php';</script>";
    exit;
}

$id = intval($_GET['id']);
$user_id = intval($_SESSION['user_id']);

// Pastikan data adalah milik user
// PERHATIAN: Query ini menggunakan string interpolasi, sebaiknya gunakan prepared statement
$cek = $conn->query("SELECT * FROM keranjang WHERE keranjang_id='$id' AND user_id='$user_id'");

if ($cek->num_rows == 0) {
    // Redirect ke keranjang.php (di folder yang sama)
    echo "<script>alert('Barang tidak ditemukan atau bukan milik Anda'); window.location='keranjang.php';</script>";
    exit;
}

// Hapus barang
// PERHATIAN: Query ini menggunakan string interpolasi, sebaiknya gunakan prepared statement
$conn->query("DELETE FROM keranjang WHERE keranjang_id='$id' AND user_id='$user_id'");

// Redirect ke keranjang.php (di folder yang sama)
echo "<script>alert('Barang berhasil dihapus dari keranjang'); window.location='keranjang.php';</script>";
exit;
?>