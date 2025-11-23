<?php
session_start();
// PERBAIKAN PATH: Keluar satu tingkat (dari public/) ke tekno-aer/config/
include '../config/db.php';

// =========================================================
// 1. Verifikasi Login & Input
// =========================================================
if (!isset($_SESSION['user_id'])) {
    // Asumsi user/login.php berada di root project/user/login.php
    header("Location: user/login.php"); 
    exit;
}

// Pastikan ada data POST yang dikirim (baik dari beli_sekarang.php atau keranjang.php)
// Cek juga apakah alamat pengiriman telah diisi
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['alamat_pengiriman']) || empty(trim($_POST['alamat_pengiriman']))) {
    // Alamat pengiriman wajib diisi
    echo "<script>alert('Alamat pengiriman wajib diisi.'); window.history.back();</script>";
    exit;
}

$uid = $_SESSION['user_id'];
$alamat = trim($_POST['alamat_pengiriman']);
$success_message = 'Checkout berhasil! Pesanan Anda sedang diproses. Silakan cek riwayat pesanan.';

// --- 1.1 Ambil nama pelanggan dari tabel user (Menggunakan Prepared Statement) ---
$nama_pelanggan = "Tidak diketahui";
// Menggunakan kolom nama_lengkap sesuai asumsi di file detail.php sebelumnya
$stmt_user = $conn->prepare("SELECT nama_lengkap FROM user WHERE user_id = ?"); 
$stmt_user->bind_param("i", $uid);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
if ($user = $res_user->fetch_assoc()) {
    $nama_pelanggan = $user['nama_lengkap'];
}
$stmt_user->close();

// =========================================================
// 2. LOGIKA GROUPING: Tentukan ID Pesanan/Transaksi BARU
// =========================================================
// Ambil ID pesanan terakhir dan tambahkan 1
$next_pesanan_id = 1; 
$res_max_id = $conn->query("SELECT MAX(id_pesanan) AS max_id FROM pesanan_pelanggan");

if ($res_max_id) {
    $row_max_id = $res_max_id->fetch_assoc();
    
    if ($row_max_id && $row_max_id['max_id'] !== NULL) {
        $next_pesanan_id = $row_max_id['max_id'] + 1;
    }
}


// =========================================================
// 3. FLOW BELI LANGSUNG (dari beli_sekarang.php)
// =========================================================
if (isset($_POST['barang_id'], $_POST['jumlah'])) { // Hilangkan pengecekan harga_total yang ambigu

    $barang_id = intval($_POST['barang_id']);
    $jumlah = intval($_POST['jumlah']);

    // --- 3.1 Ambil data barang (nama dan harga satuan) dari tabel barang (Secure) ---
    // Pastikan mengambil nama_barang dan harga satuan
    $stmt_barang = $conn->prepare("SELECT nama_barang, harga FROM barang WHERE barang_id = ?");
    $stmt_barang->bind_param("i", $barang_id);
    $stmt_barang->execute();
    $res_barang = $stmt_barang->get_result();
    $barang = $res_barang->fetch_assoc();
    $stmt_barang->close();

    if ($barang) {
        $nama_barang = $barang['nama_barang'];
        $harga_satuan = $barang['harga']; 
        $total_per_item = $harga_satuan * $jumlah; // Hitung total harga item
        
        // --- 3.2 Insert ke pesanan_pelanggan (TELAH DIPERBAIKI SESUAI STRUKTUR TABEL) ---
        // Menggunakan kolom: id_pesanan, user_id, nama_pelanggan, barang_id, nama_barang, jumlah, harga, total, alamat_pengiriman
        $stmt_insert = $conn->prepare("
            INSERT INTO pesanan_pelanggan 
            (id_pesanan, user_id, nama_pelanggan, barang_id, nama_barang, jumlah, harga, total, alamat_pengiriman, tanggal_pesanan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        // Tipe data: i (id_pesanan), i (uid), s (nama_pelanggan), i (barang_id), s (nama_barang), i (jumlah), i (harga_satuan), i (total_per_item), s (alamat)
        $stmt_insert->bind_param(
            "iisisiiis", // Urutan parameter: i, i, s, i, s, i, i, i, s
            $next_pesanan_id, 
            $uid,
            $nama_pelanggan,
            $barang_id,
            $nama_barang, // Kolom 5
            $jumlah,
            $harga_satuan, // Kolom 7 (Harga Satuan)
            $total_per_item, // Kolom 8 (Total Per Item)
            $alamat 
        );

        if ($stmt_insert->execute()) {
            echo "<script>alert('".$success_message."'); window.location='index.php';</script>";
            exit;
        } else {
            // Error saat insert (Pesan error yang lebih jelas)
            echo "<script>alert('Error saat memproses pesanan: " . $stmt_insert->error . "'); window.location='index.php';</script>";
            exit;
        }
        $stmt_insert->close();

    } else {
        // Error barang tidak ditemukan
        echo "<script>alert('Error: Produk tidak ditemukan.'); window.location='index.php';</script>";
        exit;
    }
} 
// =========================================================
// 4. FLOW KERANJANG 
// =========================================================
else { 
    
    // --- 4.1 Ambil keranjang user (Secure) ---
    $stmt_keranjang = $conn->prepare("SELECT barang_id, jumlah FROM keranjang WHERE user_id = ?");
    $stmt_keranjang->bind_param("i", $uid);
    $stmt_keranjang->execute();
    $res_keranjang = $stmt_keranjang->get_result();
    $stmt_keranjang->close(); 

    if ($res_keranjang->num_rows == 0) {
        echo "<script>alert('Keranjang kosong!'); window.location='index.php';</script>";
        exit;
    }

    $all_items_success = true;
    $items_to_process = [];
    while ($row = $res_keranjang->fetch_assoc()) {
        $items_to_process[] = $row;
    }


    // LOOP setiap barang di keranjang
    foreach ($items_to_process as $row) {
        $barang_id = $row['barang_id'];
        $jumlah = $row['jumlah'];

        // --- 4.2 AMBIL DATA BARANG DARI TABEL BARANG (Secure) ---
        // MEMPERBAIKI QUERY: Ambil nama_barang juga
        $stmt_barang_cart = $conn->prepare("SELECT nama_barang, harga FROM barang WHERE barang_id = ?");
        $stmt_barang_cart->bind_param("i", $barang_id);
        $stmt_barang_cart->execute();
        $barang_res = $stmt_barang_cart->get_result();
        $barang = $barang_res->fetch_assoc();
        $stmt_barang_cart->close();

        if (!$barang) {
            $all_items_success = false;
            error_log("Barang ID {$barang_id} tidak ditemukan saat checkout keranjang.");
            continue;
        }

        $nama_barang = $barang['nama_barang']; // Ambil nama barang
        $harga_satuan = $barang['harga']; 
        $total_per_item = $harga_satuan * $jumlah; // Hitung total harga per item

        // --- 4.3 SIMPAN KE PESANAN PELANGGAN (TELAH DIPERBAIKI SESUAI STRUKTUR TABEL) ---
        $stmt_insert = $conn->prepare("
            INSERT INTO pesanan_pelanggan 
            (id_pesanan, user_id, nama_pelanggan, barang_id, nama_barang, jumlah, harga, total, alamat_pengiriman, tanggal_pesanan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        // Tipe data: i (id_pesanan), i (uid), s (nama_pelanggan), i (barang_id), s (nama_barang), i (jumlah), i (harga_satuan), i (total_per_item), s (alamat)
        $stmt_insert->bind_param(
            "iisisiiis", // Urutan parameter: i, i, s, i, s, i, i, i, s
            $next_pesanan_id, // MENGGUNAKAN ID GROUP BARU
            $uid,
            $nama_pelanggan,
            $barang_id,
            $nama_barang,
            $jumlah,
            $harga_satuan,
            $total_per_item,
            $alamat 
        );

        if (!$stmt_insert->execute()) {
            $all_items_success = false;
            // Tampilkan error insert ke log/alert untuk debugging
            error_log("Gagal INSERT Pesanan ID {$next_pesanan_id} Barang {$barang_id}: " . $stmt_insert->error);
        }
        $stmt_insert->close();
    } // End of cart loop

    if ($all_items_success) {
        // --- 4.4 Hapus keranjang user (Secure) ---
        $stmt_delete = $conn->prepare("DELETE FROM keranjang WHERE user_id = ?");
        $stmt_delete->bind_param("i", $uid);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        echo "<script>alert('".$success_message."'); window.location='index.php';</script>";
        exit;
    } else {
        // Jika ada item yang gagal
        echo "<script>alert('Terjadi error saat memproses beberapa item pesanan. Silakan cek ulang keranjang Anda.'); window.location='keranjang.php';</script>";
        exit;
    }
}
?>