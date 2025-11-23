<?php
// Set timezone for consistency
date_default_timezone_set('Asia/Jakarta');
session_start();
// --- Database Connection ---
// Pastikan path ke db.php sudah benar (Asumsi: beli_sekarang.php ada di public/ atau root)
include '../config/db.php'; 

// =========================================================
// 1. User Login Verification & Data Retrieval
// =========================================================
if (!isset($_SESSION['user_id'])) {
    // Arahkan ke user/login.php
    header("Location: user/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
// Determine if checkout is coming from the cart
$is_from_cart = ($_GET['from'] ?? '') === 'cart';

// --- Main variables for the checkout process ---
$form_inputs = ''; // Hidden inputs to be sent to checkout.php
$product_summary_html = ''; // Product details to be displayed
$total_harga = 0;
$checkout_title = 'Single Item Checkout Confirmation';

// --- Default Address Value (You might retrieve this from the user's profile) ---
$default_alamat = ""; 
// Anda dapat menambahkan query di sini untuk mengambil alamat tersimpan user dari tabel 'user' jika tersedia.
// Contoh: 
/*
$stmt_address = $conn->prepare("SELECT alamat_default FROM user WHERE user_id = ?");
$stmt_address->bind_param("i", $user_id);
$stmt_address->execute();
$res_address = $stmt_address->get_result();
if ($addr = $res_address->fetch_assoc()) {
    $default_alamat = $addr['alamat_default'] ?? "";
}
$stmt_address->close();
*/

if ($is_from_cart) {
    // =========================================================
    // 2A. PATH: CHECKOUT FROM CART (MULTIPLE ITEMS)
    // =========================================================
    $checkout_title = 'Shopping Cart Checkout Confirmation';

    // Query to retrieve all items in the user's cart
    $stmt_cart = $conn->prepare("
        SELECT k.keranjang_id, k.jumlah, b.barang_id, b.nama_barang, b.harga, b.gambar 
        FROM keranjang k 
        JOIN barang b ON k.barang_id = b.barang_id 
        WHERE k.user_id = ?
    ");
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();
    $res_cart = $stmt_cart->get_result();
    $stmt_cart->close();
    
    if ($res_cart->num_rows === 0) {
        // Prevent checkout if cart is empty (even if 'from=cart' is set)
        echo "<div style='text-align: center; padding: 30px; color: #dc3545;'>Your cart is empty. Nothing to checkout. <a href='index.php'>Go to Shop</a></div>";
        exit;
    }

    // Build HTML summary and calculate total
    $product_summary_html .= '<div class="product-summary-container">';
    while ($row = $res_cart->fetch_assoc()) {
        $subtotal = $row['harga'] * $row['jumlah'];
        $total_harga += $subtotal;
        
        // Path gambar disesuaikan dengan asumsi struktur folder Anda
        $img_path = '../private/assets/uploads/' . htmlspecialchars($row['gambar']);
        
        $product_summary_html .= '
            <div class="product-summary-item">
                <img src="'. $img_path .'" alt="'. htmlspecialchars($row['nama_barang']) .'">
                <div class="product-info">
                    <strong>'. htmlspecialchars($row['nama_barang']) .'</strong>
                    <p>Quantity: <strong>'. $row['jumlah'] .'</strong> x IDR '. number_format($row['harga'], 0, ',', '.') .'</p>
                </div>
                <p class="subtotal-price">Subtotal: IDR '. number_format($subtotal, 0, ',', '.') .'</p>
            </div>';
    }
    $product_summary_html .= '</div>';
    
    // HIDDEN INPUTS: Untuk Cart Checkout, kita hanya perlu tahu ini dari keranjang. 
    // Data barang_id dan jumlah akan di-query ulang di checkout.php
    $form_inputs = '
        <input type="hidden" name="is_from_cart" value="1">
        <input type="hidden" name="total_harga_semua" value="'. $total_harga .'">'; 
    
} else {
    // =========================================================
    // 2B. PATH: DIRECT CHECKOUT (SINGLE ITEM)
    // =========================================================
    
    $barang_id = intval($_GET['barang_id'] ?? 0);
    $jumlah = intval($_GET['jumlah'] ?? 1);

    if ($barang_id <= 0 || $jumlah <= 0) {
        // Catch error if no valid product data is provided
        echo "<div style='text-align: center; padding: 30px; color: #dc3545;'>Invalid product data. <a href='index.php'>Back to Shop</a></div>";
        exit;
    }

    // --- Retrieve Product Data ---
    $stmt_barang = $conn->prepare("SELECT nama_barang, harga, gambar FROM barang WHERE barang_id = ?");
    $stmt_barang->bind_param("i", $barang_id);
    $stmt_barang->execute();
    $res_barang = $stmt_barang->get_result();

    if ($res_barang->num_rows == 0) {
        echo "<div style='text-align: center; padding: 30px; color: #dc3545;'>Product not found.</div>";
        exit;
    }
    $data_barang = $res_barang->fetch_assoc();
    $stmt_barang->close();

    $nama_barang = $data_barang['nama_barang'];
    $harga_satuan = $data_barang['harga'];
    $total_harga = $harga_satuan * $jumlah;
    $gambar_barang = $data_barang['gambar'];

    // Build HTML summary for single item
    $img_path = '../private/assets/uploads/' . htmlspecialchars($gambar_barang);
    $product_summary_html = '
        <div class="product-summary-container">
            <div class="product-summary-item">
                <img src="'. $img_path .'" alt="'. htmlspecialchars($nama_barang) .'">
                <div class="product-info">
                    <strong>'. htmlspecialchars($nama_barang) .'</strong>
                    <p>Quantity: <strong>'. $jumlah .'</strong> x IDR '. number_format($harga_satuan, 0, ',', '.') .'</p>
                </div>
                <p class="subtotal-price">Subtotal: IDR '. number_format($total_harga, 0, ',', '.') .'</p>
            </div>
        </div>';
        
    // HIDDEN INPUTS KRUSIAL: Kirim barang_id dan jumlah ke checkout.php
    $form_inputs = '
        <input type="hidden" name="barang_id" value="'. $barang_id .'">
        <input type="hidden" name="jumlah" value="'. $jumlah .'">';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $checkout_title; ?> - TeknoAER</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: sans-serif; background: #f4f6f9; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #008080; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        
        /* Product Summary Styling */
        .product-summary-container { 
            border: 1px solid #ddd; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            max-height: 300px; /* Max height for cart list */
            overflow-y: auto; /* Scroll if many items */
        }
        .product-summary-item { 
            display: flex; 
            gap: 15px; 
            align-items: center; 
            padding: 10px 0;
            border-bottom: 1px dotted #eee;
        }
        .product-summary-item:last-child {
            border-bottom: none;
        }
        .product-summary-item img { 
            width: 60px; 
            height: 45px; 
            object-fit: cover; 
            border-radius: 4px; 
            flex-shrink: 0;
        }
        .product-info { flex-grow: 1; }
        .product-info p { margin: 2px 0; font-size: 0.9em; }
        .subtotal-price { 
            font-size: 0.9em; 
            font-weight: bold; 
            color: #555; 
            text-align: right;
            flex-shrink: 0;
            width: 120px;
        }
        
        /* Total Section Styling */
        .total-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-top: 2px solid #008080;
            margin-top: 10px;
        }
        .total-price-label { font-size: 1.2em; font-weight: bold; color: #333; }
        .total-price { font-size: 1.5em; font-weight: bold; color: #dc3545; }
        
        /* Form Styling */
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; resize: vertical; min-height: 100px; }
        .form-group { margin-bottom: 20px; }
        
        .btn-submit { background: #008080; color: white; padding: 12px 25px; border: none; border-radius: 6px; font-size: 1.1em; cursor: pointer; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background: #006666; }
    </style>
</head>
<body>

<div class="container">
    <h1><i class="fas fa-shipping-fast"></i> <?= $checkout_title; ?></h1>

    <h2>Product Summary</h2>
    <?= $product_summary_html; ?>

    <div class="total-section">
        <span class="total-price-label">Total Payment:</span>
        <span class="total-price">IDR <?= number_format($total_harga, 0, ',', '.'); ?></span>
    </div>

    <form action="checkout.php" method="POST">
        
        <?= $form_inputs; ?>
        
        <div class="form-group">
            <label for="delivery_address"><i class="fas fa-map-marker-alt"></i> Delivery Address</label>
            <textarea 
                id="delivery_address" 
                name="alamat_pengiriman" required 
                placeholder="Enter Street Name, House Number, District, City, Postal Code"
            ><?= htmlspecialchars($default_alamat); ?></textarea>
            <small style="color: #888;">Ensure the address is correct and complete to avoid delivery issues.</small>
        </div>

        <button type="submit" name="submit_checkout" class="btn-submit">
            <i class="fas fa-lock"></i> Proceed to Payment and Order
        </button>

        <p style="text-align: center; margin-top: 15px;">
            <a href="javascript:history.back()" style="color: #008080;">Cancel and Go Back</a>
        </p>
    </form>
</div>

</body>
</html>