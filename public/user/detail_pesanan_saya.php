<?php
session_start();
// --- Database Connection ---
// PATH: Go up two levels (from public/user/) to tekno-aer/config/
include '../../config/db.php'; 

// =========================================================
// 1. Login Verification & Get Order ID
// =========================================================
$uid = $_SESSION['user_id'] ?? null; 

if (!$uid) {
    header("Location: login.php");
    exit;
}
$user_id = $uid;

// --- USER INFO & NOTIFICATION LOGIC ---
$photo_path_header = '../../assets/uploads/profiles/default.jpg'; 
$nama_user_header = $_SESSION['nama'] ?? 'Guest User';

if ($conn) { 
    // Get full name and profile photo (Secure)
    $stmt_photo = $conn->prepare("SELECT nama_lengkap, foto_profil FROM user WHERE user_id = ?");
    if ($stmt_photo) {
        $stmt_photo->bind_param("i", $uid);
        $stmt_photo->execute();
        $result_photo = $stmt_photo->get_result();
        
        if ($result_photo->num_rows > 0) {
            $user_data_header = $result_photo->fetch_assoc();
            
            $foto_profil_file = $user_data_header['foto_profil'] ?? 'default.jpg';
            $actual_path = __DIR__ . '/../../assets/uploads/profiles/' . $foto_profil_file; 
            
            if (!empty($foto_profil_file) && $foto_profil_file !== 'default.jpg' && file_exists($actual_path)) {
                $photo_path_header = '../../assets/uploads/profiles/' . htmlspecialchars($foto_profil_file);
            }
            $nama_user_header = $user_data_header['nama_lengkap'];
            $_SESSION['nama'] = $nama_user_header;
        }
        $stmt_photo->close();
    }
}
// --------------------------------------------------------

// Get Order ID and TYPE from URL parameters
$group_id = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'pesanan'; // 'pesanan' (order) or 'kirim' (shipping/sent)

if (!$group_id) {
    echo "<script>alert('Invalid Order ID.'); window.location='pesanan.php';</script>";
    exit;
}
$group_id = htmlspecialchars($group_id); 


// =========================================================
// 1.5. LOGIC PENENTUAN TABEL & KOLOM HARGA (FINAL FIXED)
// =========================================================
$table_name = '';
$id_column = '';
$status_fallback = 'Processing'; 
$is_riwayat = false; 
$date_column = ''; 
$status_column = 'status'; 
$price_columns = []; 

if ($type === 'pesanan') {
    $table_name = 'pesanan_pelanggan';
    $id_column = 'id_pesanan';
    $status_fallback = 'Being Packaged';
    $date_column = 'tanggal_pesanan'; 
    
    // Sesuai DB: pesanan_pelanggan memiliki 'harga' (satuan) dan 'total' (total item)
    $price_columns = [
        'harga_unit' => 'p.harga', 
        'total_calculated' => 'p.total'
    ];
    
} elseif ($type === 'kirim') {
    // Check in riwayat_pengiriman (shipment history) first
    $stmt_check_riwayat = $conn->prepare("SELECT id_kirim FROM riwayat_pengiriman WHERE id_kirim = ? AND user_id = ? LIMIT 1");
    
    if ($stmt_check_riwayat) {
        $stmt_check_riwayat->bind_param("si", $group_id, $user_id);
        $stmt_check_riwayat->execute();
        $res_check = $stmt_check_riwayat->get_result();
        $stmt_check_riwayat->close();

        if ($res_check->num_rows > 0) {
            $table_name = 'riwayat_pengiriman';
            $id_column = 'id_kirim';
            $is_riwayat = true;
            $status_fallback = 'Completed';
            $date_column = 'waktu_selesai'; 
            
            // Sesuai DB: riwayat_pengiriman memiliki 'harga' (satuan) dan 'total' (total item)
            $price_columns = [
                'harga_unit' => 'p.harga', 
                'total_calculated' => 'p.total'
            ]; 
        } else {
            // Check in pesanan_dikirim (sent orders)
            $table_name = 'pesanan_dikirim';
            $id_column = 'id_kirim';
            $status_fallback = 'Being Shipped';
            $date_column = 'waktu'; 
            $status_column = 'status'; 

            // Sesuai DB: pesanan_dikirim memiliki 'harga_total' dan 'jumlah'
            $price_columns = [
                'harga_unit' => '(p.harga_total / p.jumlah)', 
                'total_calculated' => 'p.harga_total' 
            ]; 
        }
    } else {
        // Fallback jika prepare check riwayat gagal (asumsi: pesanan_dikirim)
        $table_name = 'pesanan_dikirim';
        $id_column = 'id_kirim';
        $status_fallback = 'Being Shipped';
        $date_column = 'waktu'; 
        $status_column = 'status';
        $price_columns = [
             'harga_unit' => '(p.harga_total / p.jumlah)', 
             'total_calculated' => 'p.harga_total'
        ]; 
    }
} else {
    // Invalid type
    header("Location: pesanan.php");
    exit;
}

// === Cleaning the price columns (Ensure no unexpected whitespace) ===
if (!empty($price_columns)) {
    $price_columns['harga_unit'] = trim($price_columns['harga_unit']);
    $price_columns['total_calculated'] = trim($price_columns['total_calculated']);
}


// =========================================================
// 2. Fetch Order Details 
// =========================================================

// Determine the query to be used
$sql_query = "";

if ($type === 'pesanan') {
    // Query for pesanan_pelanggan (customer orders)
    $sql_query = "
        SELECT 
            p.id_pesanan AS order_group_id,
            p.barang_id,
            p.user_id,
            p.jumlah,
            p.alamat_pengiriman,
            " . $price_columns['harga_unit'] . " AS harga_unit, 
            " . $price_columns['total_calculated'] . " AS total_calculated,
            p.{$date_column} AS tanggal_order,
            '{$status_fallback}' AS status_final, 
            b.gambar,
            b.nama_barang
        FROM {$table_name} p
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        WHERE p.id_pesanan = ? AND p.user_id = ?
        ORDER BY tanggal_order DESC
    ";
} elseif ($type === 'kirim') {
    // Query for pesanan_dikirim / riwayat_pengiriman
    // Perhatian: Tidak ada spasi non-ASCII di sekitar . AS
    $sql_query = "
        SELECT 
            p.id_kirim AS order_group_id,
            p.barang_id,
            p.user_id,
            p.jumlah,
            p.alamat_pengiriman,
            " . $price_columns['harga_unit'] . " AS harga_unit, 
            " . $price_columns['total_calculated'] . " AS total_calculated, 
            p.{$date_column} AS tanggal_order, 
            p.{$status_column} AS status_final, 
            b.gambar,
            b.nama_barang
        FROM {$table_name} p
        LEFT JOIN barang b ON p.barang_id = b.barang_id
        WHERE p.id_kirim = ? AND p.user_id = ?
        ORDER BY tanggal_order DESC
    ";
}

if (empty($sql_query)) {
    die("Error: Undefined order type.");
}

// BARIS 200: Prepare statement
$trimmed_sql_query = trim($sql_query);
$stmt_detail = $conn->prepare($trimmed_sql_query);

if (!$stmt_detail) {
    // DEBUGGING: Tampilkan error MySQLi jika prepare gagal
    $debug_query = str_replace(["\n", "\r", "  "], " ", $sql_query);
    die("Error Prepare Detail (Code: " . $conn->errno . "): " . $conn->error . ". SQL GAGAL di query: " . htmlspecialchars($debug_query)); 
}

// Parameter binding: String (ID) and Integer (User ID)
$stmt_detail->bind_param("si", $group_id, $user_id); 
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();
$stmt_detail->close();

$detail_item = [];
$total_harga_pesanan = 0;
$tanggal_pesanan = null; 
$alamat_pengiriman = null;
$status_pesanan = $status_fallback; 
$status_class = ''; 
$single_barang_id = 0; 

if ($result_detail->num_rows > 0) {
    while ($row = $result_detail->fetch_assoc()) {
        $detail_item[] = $row;
        
        $item_price = $row['harga_unit'] ?? 0;
        $item_total = $row['total_calculated'] ?? 0;
        $total_harga_pesanan += $item_total;
        
        if ($tanggal_pesanan === null) {
            $tanggal_pesanan = $row['tanggal_order']; 
            $alamat_pengiriman = $row['alamat_pengiriman'];
            $status_pesanan = $row['status_final'];
        }
        
        if ($single_barang_id == 0) {
            $single_barang_id = $row['barang_id'];
        }
    }
    
    // Determine Status Class for CSS display
    $status_pesanan = strtolower($status_pesanan); 
    $status_pesanan_clean = str_replace('_', ' ', $status_pesanan);
    $status_class = str_replace(' ', '_', $status_pesanan_clean);
    
    // Mapping status ke kelas CSS
    if ($status_class == 'being_packaged' || $status_class == 'pending') $status_class = 'kemas';
    if ($status_class == 'being_shipped' || $status_class == 'dikirim') $status_class = 'kirim';
    if ($status_class == 'completed' || $status_class == 'selesai') $status_class = 'selesai';
    if ($status_class == 'failed' || $status_class == 'dibatalkan') $status_class = 'gagal';

} else {
    // Order not found or does not belong to this user
    echo "<script>alert('Order details not found or you are not authorized to view them.'); window.location='pesanan.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Detail #<?= $group_id; ?> - TeknoAER</title>
    <link rel="icon" type="image/jpeg" href="../../assets/uploads/logo/logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* CSS Styles */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #ebebeb; margin: 0; padding: 0; }
        a { text-decoration: none; color: inherit; }
        .main-layout { display: block; min-height: 100vh; } 
        .content { flex-grow: 1; padding: 0; overflow-y: auto; }
        .header { 
            background: #ffffff; 
            padding: 10px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #eee; 
            height: 50px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header .logo { font-size: 24px; font-weight: bold; color: #008080; }
        .user-info { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .profile-photo { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid #008080; 
            transition: transform 0.3s; 
        }
        .profile-photo:hover { transform: rotate(360deg); } 

        .title-icon { color: #204969; }
        .detail-container { padding: 20px; max-width: 900px; margin: 20px auto; }
        
        /* ================================================= */
        /* NEW FADE-IN ANIMATION */
        /* ================================================= */
        @keyframes fadeInSlide {
            from {
                opacity: 0; 
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card { 
            background: #ffffff; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
            margin-bottom: 20px;
            /* Animation Initialization */
            opacity: 0; 
            transform: translateY(20px); 
            animation-name: fadeInSlide; 
            animation-duration: 0.5s;
            animation-fill-mode: forwards;
            animation-timing-function: ease-out;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 8px 15px rgba(0,0,0,0.1); 
            transform: translateY(-3px); 
        }

        /* Animation Delay (Staggered Effect) */
        .card:nth-child(2) { animation-delay: 0.1s; } /* Summary */
        .card:nth-child(3) { animation-delay: 0.2s; } /* Product List */

        /* Animation on List Items inside Card */
        .item-list .item-row { 
            display: flex; 
            align-items: center; 
            padding: 15px 0; 
            border-bottom: 1px solid #eee; 
            /* Fade In Initialization for list item */
            opacity: 0;
            transform: translateY(10px);
            animation-name: fadeInSlide;
            animation-duration: 0.4s;
            animation-fill-mode: forwards;
            animation-timing-function: ease-out;
        }
        .item-list .item-row:last-child { border-bottom: none; }
        
        /* Staggered Delay for each Item Row */
        <?php for($i = 1; $i <= 10; $i++): ?>
        .item-list .item-row:nth-child(<?= $i; ?>) { animation-delay: <?= 0.3 + ($i * 0.1); ?>s; }
        <?php endfor; ?>

        /* Other Styles (kept) */
        .order-summary { border-left: 5px solid #008080; padding-left: 15px; }
        .order-summary h3 { margin-top: 0; color: #333; }
        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 5px; font-weight: bold; color: white; font-size: 0.9em; }
        .kemas { background: #6c757d; } /* Being Packaged/Pending */
        .kirim { background: #008080; } /* Being Shipped/Sent */
        .selesai { background: #28a745; } /* Completed */
        .gagal { background: #dc3545; } /* Failed/Cancelled */
        .item-list img { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; margin-right: 15px; }
        .item-info { flex-grow: 1; }
        .item-qty-price { text-align: right; font-weight: 600; }
        .item-qty-price .total-item { color: #dc3545; font-size: 1.1em; }
        .final-total { 
            text-align: right; 
            font-size: 1.5em; 
            font-weight: bold; 
            color: #204969; 
            padding-top: 15px; 
            border-top: 2px solid #ddd;
            transition: color 0.3s ease; 
        }
        .final-total:hover { color: #008080; } 
        .final-total span { color: #dc3545; }
        
        .btn-action { 
            padding: 8px 12px; 
            border-radius: 5px; 
            text-decoration: none; 
            color: white; 
            margin-left: 5px; 
            display: inline-flex; 
            align-items: center; 
            gap: 5px; 
            font-weight: 600; 
            transition: background 0.2s, transform 0.2s; 
            border: none; 
            cursor: pointer;
        }
        .btn-action:hover {
            transform: translateY(-2px); 
        }
        .btn-konfirmasi { background: #008080; }
        .btn-konfirmasi:hover { background: #006666; }
        .btn-review { background: #ffc107; color:#333; }
        .btn-review:hover { background: #e0a800; }
        .btn-detail { background: #006666; color:white; }
        .btn-detail:hover { background: #0056b3; }

        /* New Back Button Style */
        .btn-back-to-list {
            display: inline-flex;
            align-items: center;
            padding: 10px 15px;
            margin-bottom: 20px;
            border: 2px solid #008080; 
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            color: #008080; 
            background-color: #ffffff; 
            transition: background-color 0.3s, color 0.3s, transform 0.2s, box-shadow 0.3s;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-back-to-list i {
            margin-right: 8px;
        }

        .btn-back-to-list:hover {
            background-color: #008080; 
            color: white; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* ================================================= */
        /* DETAIL MODAL (BOTTOM SHEET) CSS - Kept Available */
        /* ================================================= */
        .detail-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.4); display: none; z-index: 9999;
            align-items: flex-end; 
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .detail-modal-overlay.active {
            display: flex; opacity: 1;
        }

        .detail-modal-content {
            background-color: #fefefe; width: 100%; max-width: 900px; height: 95vh;
            border-top-left-radius: 20px; border-top-right-radius: 20px;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.2);
            transform: translateY(100%); 
            transition: transform .45s cubic-bezier(0.165,0.84,0.44,1);
            position: relative; overflow: hidden; margin: 0 auto; 
            display: flex; flex-direction: column;
        }

        .detail-modal-overlay.active .detail-modal-content {
            transform: translateY(0); 
        }

        .modal-drag-handle {
            width: 40px; height: 4px; background-color: #ccc; border-radius: 2px;
            margin: 10px auto; cursor: grab; touch-action: none; flex-shrink: 0;
        }

        .loaded-detail-content {
            padding: 0 20px 20px 20px; height: 100%; overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        #scrollTopBtn {
            transition: opacity 0.3s, transform 0.3s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        #scrollTopBtn:hover {
            background-color: #006666;
            transform: scale(1.1);
        }
        
        /* END DETAIL MODAL CSS */
    </style>
</head>
<body>

<div class="main-layout">
    <div class="content">
        <div class="header">
            <a href="../index.php" style="color: inherit;"><span class="logo">TeknoAER</span></a>
            <a href="dashboard.php" style="color: inherit; text-decoration: none;">
                <div class="user-info">
                    <div style="text-align:right;">
                        <span style="font-weight:600; display:block;"><?= htmlspecialchars($nama_user_header); ?></span>
                        <span class="user-role">
                            <?= isset($_SESSION['user_id']) ? ($_SESSION['role'] ?? 'Customer') : 'Guest'; ?>
                        </span>
                    </div>
                    <img src="<?= $photo_path_header; ?>" alt="Profile" class="profile-photo">
                </div>
            </a>
        </div>

        <div class="detail-container">
            <h2><i class="fas fa-file-invoice title-icon"></i> Order Detail #<?= $group_id; ?></h2>
            
            <a href="pesanan.php" class="btn-back-to-list">
                <i class="fas fa-arrow-left"></i> Back to Order List
            </a>

            <div class="card order-summary">
                <h3>Transaction Summary</h3>
                <p><strong>Transaction ID:</strong> #<?= htmlspecialchars($group_id); ?></p>
                <p><strong>Order Date:</strong> <?= date('d M Y H:i:s', strtotime($tanggal_pesanan)); ?></p>
                <p>
                    <strong>Status:</strong> 
                    <span class="status-badge <?= $status_class; ?>">
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $status_pesanan))); ?>
                    </span>
                </p>
                <p><strong>Shipping Address:</strong> <?= nl2br(htmlspecialchars($alamat_pengiriman)); ?></p>
            </div>

            <div class="card">
                <h3>Product List</h3>
                <div class="item-list">
                    <?php 
                    foreach ($detail_item as $index => $item): 
                        
                        // --- CORRECTED IMAGE PATH ---
                        $image_path = '../../private/assets/uploads/' . $item['gambar'];
                        $actual_path = __DIR__ . '/../../private/assets/uploads/' . $item['gambar'];
                        
                        $img_src = (isset($item['gambar']) && !empty($item['gambar']) && file_exists($actual_path)) 
                            ? $image_path 
                            : 'https://via.placeholder.com/50?text=No+Image'; 

                        $item_price = $item['harga_unit'] ?? 0;
                        $item_total = $item['total_calculated'] ?? 0;
                    ?>
                    <div class="item-row">
                        <img src="<?= $img_src; ?>" alt="<?= htmlspecialchars($item['nama_barang']); ?>" style="transition: transform 0.3s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                        <div class="item-info">
                            <strong><?= htmlspecialchars($item['nama_barang']); ?></strong>
                            <p style="margin:0; font-size:0.9em; color:#777;">@ Rp <?= number_format($item_price, 0, ',', '.'); ?></p>
                        </div>
                        <div class="item-qty-price">
                            <p style="margin:0;">Quantity: x<?= $item['jumlah']; ?></p>
                            <p class="total-item" style="margin:0;">Rp <?= number_format($item_total, 0, ',', '.'); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="final-total">
                    FINAL TOTAL: <span>Rp <?= number_format($total_harga_pesanan, 0, ',', '.'); ?></span>
                </div>
            </div>
            
            <div style="text-align: right; animation: fadeInSlide 0.5s ease-out forwards 0.3s; opacity: 0; transform: translateY(20px);">
                <?php if ($status_pesanan == 'being shipped' && $type == 'kirim'): ?>
                    <a href="konfirmasi.php?id=<?= $group_id; ?>" class="btn-action btn-konfirmasi">
                        <i class="fas fa-check-circle"></i> Confirm Item Received
                    </a>
                <?php elseif ($status_pesanan == 'completed' && $is_riwayat): ?>
                    <?php if ($single_barang_id > 0): ?>
                    <a href="tambah_review.php?barang_id=<?= $single_barang_id; ?>" class="btn-action btn-review">
                        <i class="fas fa-star"></i> Write Review
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </div> 
    
    <div class="detail-modal-overlay" id="detailModalOverlay" onclick="closeDetailModal(event)">
        <div class="detail-modal-content" id="detailModalContent">
            <div class="modal-drag-handle" id="modalDragHandle"></div>
            <div class="loaded-detail-content" id="loadedDetailContent">
                <div class="loading-content">Loading details...</div>
            </div>
        </div>
    </div>

    <button onclick="scrollToTop()" id="scrollTopBtn" title="Back to Top" style="display: none; position: fixed; bottom: 20px; right: 30px; z-index: 99; border: none; outline: none; background-color: #008080; color: white; cursor: pointer; padding: 10px 15px; border-radius: 8px; font-size: 16px;">
        <i class="fas fa-arrow-up"></i>
    </button>
    <div id="loginPopup" style="display: none;"></div>

</div> 

<script>
    // ========================================
    // SCROLL TO TOP LOGIC
    // ========================================
    window.onscroll = function() {scrollFunction()};

    function scrollFunction() {
        const scrollTopBtn = document.getElementById("scrollTopBtn");
        if (scrollTopBtn) {
            if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
                scrollTopBtn.style.display = "block";
            } else {
                scrollTopBtn.style.display = "none";
            }
        }
    }

    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // ========================================
    // MODAL/BOTTOM SHEET LOGIC (Simple)
    // ========================================
    // This function will close the modal if the overlay (background) is clicked.
    function closeDetailModal(event) {
        const overlay = document.getElementById('detailModalOverlay');
        const content = document.getElementById('detailModalContent');
        
        // Only close if the click occurred on the overlay, NOT on the modal content
        if (event.target === overlay) {
            overlay.classList.remove('active');
            // Clear content after animation ends (optional)
            setTimeout(() => {
                document.getElementById('loadedDetailContent').innerHTML = '<div class="loading-content">Loading details...</div>';
            }, 500);
        }
    }

</script>

</body>
</html>