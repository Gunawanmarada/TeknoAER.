<?php
session_start();
// PERBAIKAN PATH: Keluar dua tingkat (dari public/user/) ke tekno-aer/config/
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    // PERBAIKAN PATH: Redirect ke login.php di folder yang sama (public/user/)
    echo "<script>alert('Harap login terlebih dahulu!'); window.location='login.php';</script>";
    exit;
}

$user = $_SESSION['user_id'];
$barang = $_POST['barang_id'];
$komentar = trim($_POST['komentar']);

// PERHATIAN: Query ini TIDAK AMAN! Sebaiknya gunakan prepared statement.
// Dalam konteks perbaikan path, kita hanya fokus pada jalur.
// Simpan review
$conn->query("INSERT INTO review (user_id, barang_id, komentar) VALUES ('$user','$barang','$komentar')");

// PERBAIKAN PATH: Redirect ke detail.php (keluar satu tingkat ke public/)
echo "<script>alert('Review berhasil dikirim!'); window.location='../detail.php?id=$barang';</script>";
?>