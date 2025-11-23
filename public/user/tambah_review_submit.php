<?php
session_start();
// PERBAIKAN PATH: Keluar dua tingkat (dari public/user/) ke tekno-aer/config/
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    // PERBAIKAN PATH: Redirect ke login.php di folder yang sama (public/user/)
    echo "<script>alert('Silakan login terlebih dahulu.'); window.location='login.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // PERBAIKAN PATH: Redirect ke pesanan.php di folder yang sama (public/user/)
    header("Location: pesanan.php");
    exit;
}

$user = intval($_SESSION['user_id']);
$barang = intval($_POST['barang_id']);
$rating = intval($_POST['rating']);
$komentar = trim($_POST['komentar'] ?? '');

// Pastikan user berhak review (ada di pesanan_selesai)
// PERHATIAN: Query ini menggunakan string interpolasi, sebaiknya gunakan prepared statement
$cek = $conn->query("SELECT * FROM pesanan_selesai WHERE user_id='$user' AND barang_id='$barang' LIMIT 1");
if ($cek->num_rows == 0) {
    // PERBAIKAN PATH: Redirect ke pesanan.php di folder yang sama (public/user/)
    echo "<script>alert('Anda tidak dapat mereview barang ini.'); window.location='pesanan.php';</script>";
    exit;
}

// Cek apakah sudah ada review user untuk barang ini (hindari duplikat)
// PERHATIAN: Query ini menggunakan string interpolasi, sebaiknya gunakan prepared statement
$cek2 = $conn->query("SELECT * FROM review WHERE user_id='$user' AND barang_id='$barang' LIMIT 1");
if ($cek2->num_rows > 0) {
    // PERBAIKAN PATH: Redirect ke detail.php (keluar satu tingkat ke public/)
    echo "<script>alert('Anda sudah pernah mereview barang ini.'); window.location='../index.php?id=$barang';</script>";
    exit;
}

// Simpan review (Menggunakan Prepared Statement - AMAN)
$stmt = $conn->prepare("INSERT INTO review (user_id, barang_id, rating, komentar) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $user, $barang, $rating, $komentar);
$stmt->execute();
$stmt->close();

// PERBAIKAN PATH: Redirect ke detail.php (keluar satu tingkat ke public/)
echo "<script>alert('Terima kasih atas review Anda!'); window.location='../index.php?id=$barang';</script>";
exit;
?>