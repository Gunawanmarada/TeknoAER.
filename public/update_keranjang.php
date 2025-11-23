<?php
session_start();
// PERBAIKAN PATH: Sesuaikan path koneksi database Anda
include '../config/db.php'; 

// Cek Login
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) {
    // Jika sesi berakhir, tetap tampilkan alert dan redirect ke login
    echo "<script>alert('Sesi berakhir. Silakan login ulang.'); window.location='user/login.php';</script>";
    exit;
}

// 1. Ambil Data Input dari POST
$keranjang_id = (int)($_POST['keranjang_id'] ?? 0);
$jumlah = (int)($_POST['jumlah'] ?? 0); 

// Validasi ID Keranjang
if ($keranjang_id <= 0) {
    // Jika ID tidak valid, redirect tanpa alert
    header("Location: keranjang.php");
    exit;
}

// 2. Logika Update atau Hapus
if ($jumlah > 0) {
    // A. UPDATE JUMLAH (Jika jumlah > 0)
    
    // Siapkan query untuk update jumlah berdasarkan keranjang_id dan user_id
    $stmt = $conn->prepare("UPDATE keranjang SET jumlah = ? WHERE keranjang_id = ? AND user_id = ?");
    
    if (!$stmt) {
        // Jika persiapan query gagal, redirect tanpa alert (opsional: tambahkan logging error)
        header("Location: keranjang.php");
        exit;
    }

    $stmt->bind_param("iii", $jumlah, $keranjang_id, $uid);
    $stmt->execute();
    $stmt->close();

} else {
    // B. HAPUS BARANG (Jika jumlah <= 0)
    
    // Siapkan query untuk menghapus item keranjang berdasarkan keranjang_id dan user_id
    $stmt = $conn->prepare("DELETE FROM keranjang WHERE keranjang_id = ? AND user_id = ?");

    if (!$stmt) {
        // Jika persiapan query gagal, redirect tanpa alert
        header("Location: keranjang.php");
        exit;
    }

    $stmt->bind_param("ii", $keranjang_id, $uid);
    $stmt->execute();
    $stmt->close();
}

// 3. Redirect langsung ke halaman keranjang tanpa menampilkan alert
// Ini adalah baris penting yang menggantikan `echo "<script>alert...`
header("Location: keranjang.php");
exit;
?>