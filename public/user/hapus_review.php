<?php
session_start();
// Jalur koneksi sudah diperbaiki (Keluar dua tingkat dari public/user/ ke tekno-aer/config/db.php)
include '../../config/db.php'; 

// =========================================================
// 1. Verifikasi Login User
// =========================================================
if (!isset($_SESSION['user_id'])) {
    // Jika belum login, arahkan ke halaman login
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// =========================================================
// 2. Ambil dan Validasi ID Review & ID Barang
// =========================================================
if (!isset($_GET['id']) || !isset($_GET['barang_id']) || !is_numeric($_GET['id']) || !is_numeric($_GET['barang_id'])) {
    // Redirect kembali jika parameter tidak valid
    header("Location: ../index.php?error=" . urlencode("Parameter review tidak valid."));
    exit;
}

$id_review = intval($_GET['id']);
$barang_id = intval($_GET['barang_id']);

// =========================================================
// 3. Proses Penghapusan Review
// =========================================================

// Gunakan Prepared Statement: Hapus review HANYA jika id_review dan user_id cocok (verifikasi kepemilikan)
// Ini adalah langkah keamanan krusial untuk memastikan user hanya bisa menghapus review miliknya sendiri.
$stmt = $conn->prepare("DELETE FROM review WHERE id_review = ? AND user_id = ?");
$stmt->bind_param("ii", $id_review, $user_id);

if ($stmt->execute()) {
    $message = "Ulasan berhasil dihapus.";
} else {
    // Jika eksekusi gagal (misalnya karena masalah koneksi atau query)
    $message = "Gagal menghapus ulasan: " . $conn->error;
}
$stmt->close();

// =========================================================
// 4. Redirect Kembali ke Detail Barang
// =========================================================
// Redirect ke detail.php di public/ dan bawa pesan status
header("Location: ../index.php?id=" . $barang_id . "&msg=" . urlencode($message));
exit;
?>