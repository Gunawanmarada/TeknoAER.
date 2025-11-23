<?php
session_start();
// PERBAIKAN PATH: Keluar satu tingkat (dari public/) ke tekno-aer/config/
include '../config/db.php'; 

// --- Pengecekan Login ---
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) {
    // Baris 9 (Dihapus Karakter Tak Terlihat)
    echo "<script>alert('Silakan login terlebih dahulu untuk menambahkan ke keranjang.'); window.location='user/login.php';</script>";
    exit;
}

// --- Pengambilan Data Input (Hanya ID dan Jumlah) ---
// Menggunakan Null Coalescing Operator (??) untuk mencegah Undefined Array Key Warning
// dan memastikan data adalah integer.
$barang_id = (int)($_POST['barang_id'] ?? 0);
$jumlah = (int)($_POST['jumlah'] ?? 1); // Default jumlah 1

if ($barang_id <= 0 || $jumlah <= 0) {
    echo "<script>alert('Data barang atau jumlah tidak valid.'); window.history.back();</script>";
    exit;
}

// 1. AMBIL NAMA & HARGA DARI DATABASE (Mencegah Price Tampering)
// --------------------------------------------------------
$stmt_item = $conn->prepare("SELECT nama_barang, harga FROM barang WHERE barang_id = ?");

if (!$stmt_item) {
    echo "<script>alert('Kesalahan persiapan query database: " . $conn->error . "'); window.history.back();</script>";
    exit;
}

$stmt_item->bind_param("i", $barang_id);
$stmt_item->execute();
$result_item = $stmt_item->get_result();

if ($result_item->num_rows === 0) {
    echo "<script>alert('Barang tidak ditemukan di katalog.'); window.history.back();</script>";
    exit;
}

$item_data = $result_item->fetch_assoc();
$nama_barang_db = $item_data['nama_barang']; // Data Aman dari DB
$harga_db = $item_data['harga'];             // Data Aman dari DB
$stmt_item->close();


// 2. CEK APAKAH BARANG SUDAH ADA DI KERANJANG (Menggunakan Prepared Statement)
// -----------------------------------------------------------------------------
$stmt_cek = $conn->prepare("SELECT keranjang_id, jumlah FROM keranjang WHERE user_id = ? AND barang_id = ?");
$stmt_cek->bind_param("ii", $uid, $barang_id);
$stmt_cek->execute();
$result_cek = $stmt_cek->get_result();

if ($result_cek->num_rows > 0) {
    // 🧩 Jika sudah ada, update jumlah
    $row = $result_cek->fetch_assoc();
    $keranjang_id = $row['keranjang_id'];
    $new_jumlah = $row['jumlah'] + $jumlah;

    $stmt_update = $conn->prepare("UPDATE keranjang SET jumlah = ? WHERE keranjang_id = ?");
    $stmt_update->bind_param("ii", $new_jumlah, $keranjang_id);
    $stmt_update->execute();
    $stmt_update->close();
    $pesan = "Jumlah barang berhasil ditambahkan di keranjang!";

} else {
    // 🆕 Jika belum ada, tambahkan data baru
    $stmt_insert = $conn->prepare("INSERT INTO keranjang (user_id, barang_id, nama_barang, harga, jumlah, alamat)
                                   VALUES (?, ?, ?, ?, ?, '')");
    // Tipe data: i=integer, i=integer, s=string, i=integer, i=integer
    $stmt_insert->bind_param("iisii", $uid, $barang_id, $nama_barang_db, $harga_db, $jumlah);
    $stmt_insert->execute();
    $stmt_insert->close();
    $pesan = "Barang berhasil ditambahkan ke keranjang!";
}

$stmt_cek->close();

// 🔁 Kembali ke halaman keranjang
echo "<script>alert('{$pesan}'); window.location='keranjang.php';</script>";
exit;
?>