<?php
session_start();
// PERBAIKAN PATH: Keluar dua tingkat (dari public/user/) ke tekno-aer/config/
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    // PERBAIKAN PATH: Redirect ke login.php di folder yang sama (public/user/)
    echo "<script>alert('Silakan login terlebih dahulu.'); window.location='login.php';</script>";
    exit;
}
if (!isset($_GET['id'])) {
    // PERBAIKAN PATH: Redirect ke pesanan.php di folder yang sama (public/user/)
    echo "<script>alert('ID pesanan tidak ditemukan.'); window.location='pesanan.php';</script>";
    exit;
}

$id = intval($_GET['id']);
$user_id = intval($_SESSION['user_id']); // Ambil user ID dari session

// 1. Ambil data dari pesanan_dikirim dan pastikan milik user yang login
// Perbaikan utama: Mengganti pesanan_saya menjadi pesanan_dikirim
// PERHATIAN: Query ini menggunakan string interpolasi, yang berpotensi SQL Injection. 
// Disarankan menggunakan prepared statement meskipun ini hanya SELECT.
$ps = $conn->query("SELECT * FROM pesanan_dikirim WHERE id_pesanan = '$id' AND user_id = '$user_id'")->fetch_assoc(); 

if (!$ps) {
    // PERBAIKAN PATH: Redirect ke pesanan.php di folder yang sama (public/user/)
    echo "<script>alert('Pesanan tidak ditemukan atau bukan milik Anda.'); window.location='pesanan.php';</script>";
    exit;
}

// 2. Pindahkan ke pesanan_selesai (Prepared Statement sudah aman)
$stmt = $conn->prepare("INSERT INTO pesanan_selesai (user_id, barang_id, nama_barang, jumlah, harga_total) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisis", $ps['user_id'], $ps['barang_id'], $ps['nama_barang'], $ps['jumlah'], $ps['harga_total']);
$stmt->execute();
$stmt->close();

// 3. Tambahkan ke data_keuangan (transaksi final) (Prepared Statement sudah aman)
$stmt2 = $conn->prepare("INSERT INTO data_keuangan (user_id, nama_pelanggan, nama_barang, total) VALUES (?, ?, ?, ?)");
$nama_pelanggan = $_SESSION['nama'] ?? '';
$stmt2->bind_param("issi", $ps['user_id'], $nama_pelanggan, $ps['nama_barang'], $ps['harga_total']);
$stmt2->execute();
$stmt2->close();

// 4. Hapus dari pesanan_dikirim (Menggunakan string interpolasi - rentan, tapi di sini aman karena $id dan $user_id sudah di-intval)
// Perbaikan utama: Mengganti pesanan_saya menjadi pesanan_dikirim
$conn->query("DELETE FROM pesanan_dikirim WHERE id_pesanan = '$id' AND user_id = '$user_id'");

// 5. Kirim notifikasi ke user (opsional) (Prepared Statement sudah aman)
$notif = "Pesanan Anda ({$ps['nama_barang']}) telah diterima. Terima kasih!";
$stmt3 = $conn->prepare("INSERT INTO notifikasi_user (user_id, pesan) VALUES (?, ?)");
$stmt3->bind_param("is", $ps['user_id'], $notif);
$stmt3->execute();
$stmt3->close();

// PERBAIKAN PATH: Redirect sukses ke pesanan.php di folder yang sama (public/user/)
header("Location: pesanan.php?success=konfirmasi");
exit;
?>