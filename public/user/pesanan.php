<?php
session_start();
// --- Database Connection ---
// PATH: Go up two levels (from public/user/) to tekno-aer/config/
include '../../config/db.php'; 

// Check connection early
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// --- MAIN LOGIC USER INFO & NOTIFICATIONS ---
$photo_path_header = '../../private/assets/logo/default.png'; 
$nama_user_header = 'Guest User';
$notif_count = 0;
$uid = $_SESSION['user_id'] ?? null; 

if (!$uid) {
    // If not logged in, redirect to login in the same folder (public/user/)
    echo "<script>alert('Please log in first'); window.location='login.php';</script>";
    exit;
}
$user_id = $uid;


// --- NEW FUNCTION: DETECT READ STATUS COLUMN ---
function detect_read_key($conn) {
    $readKeys = ['status_baca', 'is_read'];
    $cols = [];
    // Only check if $conn is available
    if ($conn) {
        $res = $conn->query("SHOW COLUMNS FROM notifikasi_user");
        if ($res) {
            while ($c = $res->fetch_assoc()) {
                $cols[] = $c['Field'];
            }
        }
    }
    foreach ($readKeys as $k) {
        if (in_array($k, $cols)) {
            return $k;
        }
    }
    return null;
}

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
    
    // COUNT UNREAD NOTIFICATIONS
    $readKey = detect_read_key($conn);

    if ($readKey) {
        $stmt_notif = $conn->prepare("SELECT COUNT(*) AS jml FROM notifikasi_user WHERE user_id = ? AND {$readKey} = 0"); 
    } else {
        $stmt_notif = $conn->prepare("SELECT COUNT(*) AS jml FROM notifikasi_user WHERE user_id = ?");
    }
    
    if (isset($stmt_notif) && $stmt_notif) {
        $stmt_notif->bind_param("i", $uid);
        if ($stmt_notif->execute()) {
            $notif_query = $stmt_notif->get_result();
            if ($notif_query && $notif_query->num_rows > 0) {
                $notif_count = (int)$notif_query->fetch_assoc()['jml'];
            }
        }
        $stmt_notif->close();
    } else {
        $notif_count = 0; 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - TeknoAER</title>
    <link rel="icon" type="image/jpeg" href="../../assets/uploads/logo/logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* CSS STYLES (Retained and localized) */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #ebebeb; 
            margin: 0; 
            padding: 0; 
            overflow-x: hidden; 
        }
        
        a { text-decoration: none; color: inherit; }
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
        
        .main-layout { display: flex; min-height: 100vh; } 
        .sidebar { width: 70px; background: #008080; color: white; padding: 10px 0; display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
        .sidebar-item { 
            padding: 15px 0; 
            cursor: pointer; 
            width: 100%; 
            text-align: center; 
            transition: background 0.2s, transform 0.2s; 
        }
        .sidebar-item:hover, .sidebar-item.active { 
            background: #006666; 
            transform: scale(1.05); 
        }
        .sidebar-item i { font-size: 24px; }
        
        .content { 
            flex-grow: 1; 
            padding: 0; 
            overflow-y: scroll !important; 
            scrollbar-width: none !important; 
            -webkit-overflow-scrolling: touch;
        }
        .content::-webkit-scrollbar {
            width: 0 !important;
            background: transparent !important;
        }

        .catalog-container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .title-icon { color: #204969; margin-right: 8px; }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px; 
        }

        .notif-container {
            position: relative;
            color: #333; 
            font-size: 20px;
            transition: color 0.2s, transform 0.3s; 
        }
        .notif-container:hover {
            color: #008080;
            transform: rotate(5deg); 
        }

        .notif-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: #dc3545; 
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7em;
            font-weight: bold;
            min-width: 10px;
            text-align: center;
            line-height: 1;
            border: 1px solid #ffffff; 
            z-index: 10;
            transition: transform 0.2s; 
        }
        .notif-badge:hover {
            transform: scale(1.1);
        }
        
        /* ================================================= */
        /* CARD STYLES & ANIMATION */
        /* ================================================= */

        /* Keyframe Card Load Animation */
        @keyframes cardFadeIn {
            from {
                opacity: 0; 
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .order-card { 
            background: #ffffff; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
            margin-bottom: 15px;
            border-left: 5px solid #008080; 
            transition: box-shadow 0.3s ease, transform 0.3s ease; 

            /* --- FADE-IN ANIMATION CLASS --- */
            opacity: 0; 
            transform: translateY(20px); 
            animation-name: cardFadeIn; 
            animation-duration: 0.5s;
            animation-fill-mode: forwards;
            animation-timing-function: ease-out;
        }
        .order-card:hover { 
            box-shadow: 0 8px 15px rgba(0,0,0,0.1); 
            transform: translateY(-3px); 
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .order-header h3 { margin: 0; font-size: 1.1em; color: #333; }
        .status { font-weight: bold; padding: 4px 8px; border-radius: 5px; color: white; font-size: 0.9em; }
        .packed { background: #6c757d; } 
        .shipped { background: #008080; } 
        .completed { background: #28a745; } 
        .failed { background: #dc3545; } 

        .item-list p { margin: 5px 0; font-size: 0.95em; color: #555; }
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        .total-price { font-size: 1.2em; font-weight: bold; color: #dc3545; }
        .btn-action, .btn { 
            padding: 8px 12px; border-radius: 5px; text-decoration: none; color: white; 
            margin-left: 5px; display: inline-flex; align-items: center; gap: 5px;
            font-weight: 600; transition: background 0.2s, transform 0.2s; 
        }
        .btn-action:hover, .btn:hover {
            transform: translateY(-2px); 
        }
        .btn-confirm { background: #008080; }
        .btn-confirm:hover { background: #006666; }
        .btn-review { background: #ffc107; color:#333; }
        .btn-review:hover { background: #e0a800; }
        .btn-detail { background: #006666; }
        .btn-detail:hover { background: #0056b3; }
        
        .user-info { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            flex-shrink: 0; 
        }
        .profile-photo {
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid #008080; 
            transition: transform 0.3s; 
        }
        .profile-photo:hover {
            transform: rotate(360deg); 
        }
        .user-role { font-size: 0.85em; color: #666; }

        @media (max-width: 768px) {
            .sidebar { display: none; } 
            .main-layout { display: block; }
            .order-footer { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
        
    </style>
</head>
<body>

<div class="main-layout">
    <div class="sidebar">
        <div class="top-icon" style="padding: 10px 0;"><i class="fas fa-leaf"></i></div> 
        <a href="../index.php" class="sidebar-item" title="Catalog"><i class="fas fa-store"></i></a>
        <a href="../keranjang.php" class="sidebar-item" title="Cart"><i class="fas fa-shopping-cart"></i></a>
        <a href="pesanan.php" class="sidebar-item active" title="Orders"><i class="fas fa-box"></i></a>
        <a href="logout.php" class="sidebar-item" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>

    <div class="content">
        <div class="header" id="mainHeader">
            <div style="display:flex; align-items:center; gap:10px;">
                <span class="logo">TeknoAER</span>
            </div>
            
            <div class="header-actions">
                
                <a href="notifikasi.php" class="notif-container" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notif_count > 0): ?>
                        <span class="notif-badge"><?= $notif_count > 99 ? '99+' : $notif_count; ?></span>
                    <?php endif; ?>
                </a>

                <a href="dashboard.php" style="color: inherit; text-decoration: none;">
                    <div class="user-info">
                        <div style="text-align:right;">
                            <span style="font-weight:600; display:block;"><?= htmlspecialchars($nama_user_header); ?></span>
                            <span class="user-role">
                                <?= isset($_SESSION['user_id']) ? ($_SESSION['role'] ?? 'Customer') : 'Guest'; ?>
                            </span>
                        </div>
                        <img src="<?= $photo_path_header; ?>" alt="Profile Photo" class="profile-photo">
                    </div>
                </a>
            </div>
        </div>

        <div class="catalog-container">
            <h2><i class="fas fa-receipt title-icon"></i> My Orders</h2>

            <h3><i class="fas fa-box-open title-icon"></i> Currently Being Packed</h3>
            <?php
            $stmt_packed = $conn->prepare("
                SELECT 
                    id_pesanan,
                    GROUP_CONCAT(CONCAT(nama_barang, ' (', jumlah, 'x)') SEPARATOR '||') AS item_list, 
                    SUM(harga * jumlah) AS total_order_rp  
                FROM 
                    pesanan_pelanggan 
                WHERE 
                    user_id=? 
                GROUP BY 
                    id_pesanan 
                ORDER BY 
                    id_pesanan DESC
            ");

            $global_item_index = 0; // Global index for staggered animation
            
            if (!$stmt_packed) {
                echo "<p style='color: red; padding: 15px; background: white; border-radius: 8px;'>⚠️ Prepare Error (Packed): " . $conn->error . "</p>";
            } else {
                $stmt_packed->bind_param("i", $user_id);
                
                if (!$stmt_packed->execute()) {
                    echo "<p style='color: red; padding: 15px; background: white; border-radius: 8px;'>❌ Execute Error (Packed): " . $stmt_packed->error . "</p>";
                } else {
                    $packed_orders = $stmt_packed->get_result();

                    if ($packed_orders->num_rows == 0) {
                        // Add animation properties to placeholder
                        $global_item_index++;
                        echo "<p class='order-card' style='border-left: 5px solid #6c757d; animation-delay: {$global_item_index}s; animation-duration: 0.3s; opacity: 0; transform: translateY(10px);'>No orders are currently being packed.</p>";
                    } else {
                        while ($p = $packed_orders->fetch_assoc()):
                            $global_item_index++;
            ?>
            <div class="order-card" 
                style="border-left-color: #6c757d; animation-delay: <?= $global_item_index * 0.1; ?>s;">
                <div class="order-header">
                    <h3>Order #<?= htmlspecialchars($p['id_pesanan']); ?></h3>
                    <span class="status packed">Being Packed</span>
                </div>
                
                <div class="item-list">
                    <?php 
                        $items = explode('||', $p['item_list']); 
                        foreach($items as $item) {
                            echo "<p>• ". htmlspecialchars($item) ."</p>";
                        }
                    ?>
                </div>

                <div class="order-footer">
                    <div class="total-price">
                        Total: IDR <?= number_format($p['total_order_rp'] ?? 0, 0, ',', '.'); ?>
                    </div>
                    <div>
                        <a href="detail_pesanan_saya.php?id=<?= htmlspecialchars($p['id_pesanan']); ?>&type=pesanan" class="btn btn-detail"><i class="fas fa-info-circle"></i> Detail</a>
                    </div>
                </div>
            </div>
            <?php endwhile; }
                } // End else execute
                $stmt_packed->close();
            } // End else prepare packed
            ?>
            
            <hr style="border: 0; border-top: 1px solid #ddd; margin: 30px 0;">

            <h3><i class="fas fa-shipping-fast title-icon"></i> Currently Shipped</h3>
            <?php
            $stmt_shipped = $conn->prepare("
                SELECT 
                    id_kirim, 
                    GROUP_CONCAT(CONCAT(nama_barang, ' (', jumlah, 'x)') SEPARATOR '||') AS item_list, 
                    SUM(harga_total) AS total_order_rp
                FROM 
                    pesanan_dikirim 
                WHERE 
                    user_id=? 
                GROUP BY 
                    id_kirim 
                ORDER BY 
                    id_kirim DESC
            ");
            
            if (!$stmt_shipped) {
                echo "<p style='color: red; padding: 15px; background: white; border-radius: 8px;'>⚠️ Prepare Error (Shipped): " . $conn->error . "</p>";
            } else {
                $stmt_shipped->bind_param("i", $user_id);
                if (!$stmt_shipped->execute()) {
                    echo "<p style='color: red; padding: 15px; background: white; border-radius: 8px;'>❌ Execute Error (Shipped): " . $stmt_shipped->error . "</p>";
                } else {
                    $shipped_orders = $stmt_shipped->get_result();

                    if ($shipped_orders->num_rows == 0) {
                        $global_item_index++;
                        echo "<p class='order-card' style='border-left: 5px solid #008080; animation-delay: {$global_item_index}s; animation-duration: 0.3s; opacity: 0; transform: translateY(10px);'>No orders are currently in transit.</p>";
                    } else {
                        while ($p = $shipped_orders->fetch_assoc()):
                            $global_item_index++;
            ?>
            <div class="order-card" 
                style="border-left-color: #008080; animation-delay: <?= $global_item_index * 0.1; ?>s;">
                <div class="order-header">
                    <h3>Shipment #<?= htmlspecialchars($p['id_kirim']); ?></h3>
                    <span class="status shipped">Shipped</span>
                </div>
                
                <div class="item-list">
                    <?php 
                        $items = explode('||', $p['item_list']); 
                        foreach($items as $item) {
                            echo "<p>• ". htmlspecialchars($item) ."</p>";
                        }
                    ?>
                </div>

                <div class="order-footer">
                    <div class="total-price">
                        Total: IDR <?= number_format($p['total_order_rp'] ?? 0, 0, ',', '.'); ?>
                    </div>
                    <div>
                        <a href="detail_pesanan_saya.php?id=<?= htmlspecialchars($p['id_kirim']); ?>&type=kirim" class="btn btn-detail">
                            <i class="fas fa-info-circle"></i> Detail
                        </a>
                        
                        <a href="konfirmasi.php?id=<?= $p['id_kirim']; ?>" class="btn btn-confirm">
                            <i class="fas fa-check-circle" style="color:#ffffff;"></i> Confirm Arrival
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; }
                } // End else execute
                $stmt_shipped->close();
            } // End else prepare shipped
            ?>
            
            <hr style="border: 0; border-top: 1px solid #ddd; margin: 30px 0;">

            <h3><i class="fas fa-history title-icon"></i> Completed / Failed History</h3>
            <?php
            $stmt_history = $conn->prepare("
                SELECT 
                    id_kirim, 
                    GROUP_CONCAT(CONCAT(nama_barang, ' (', jumlah, 'x)') SEPARATOR '||') AS item_list, 
                    SUM(total) AS total_order_rp,
                    MAX(barang_id) AS single_barang_id,
                    MAX(status) AS final_status 
                FROM 
                    riwayat_pengiriman
                WHERE 
                    user_id=? 
                GROUP BY 
                    id_kirim
                ORDER BY 
                    id_kirim DESC
            ");

            if (!$stmt_history) {
                echo "<p style='color: red; padding: 15px; background: white; border-radius: 8px;'>⚠️ Prepare Error (History): " . $conn->error . "</p>";
            } else {
                $stmt_history->bind_param("i", $user_id);
                
                if (!$stmt_history->execute()) {
                    echo "<p style='color: red; padding: 15px; background: white; border-radius: 8px;'>❌ Execute Error (History): " . $stmt_history->error . "</p>";
                } else {
                    $history_orders = $stmt_history->get_result();

                    if ($history_orders->num_rows == 0) {
                        $global_item_index++;
                        echo "<p class='order-card' style='border-left: 5px solid #28a745; animation-delay: {$global_item_index}s; animation-duration: 0.3s; opacity: 0; transform: translateY(10px);'>No completed or failed order history yet.</p>";
                    } else {
                        while ($p = $history_orders->fetch_assoc()):
                            $global_item_index++;
                            $status_class = ($p['final_status'] == 'selesai') ? 'completed' : 'failed';
                            $status_text = ($p['final_status'] == 'selesai') ? 'Order Completed' : 'Delivery Failed';
            ?>
            <div class="order-card" 
                style="border-left-color: <?= ($p['final_status'] == 'selesai') ? '#28a745' : '#dc3545'; ?>; animation-delay: <?= $global_item_index * 0.1; ?>s;">
                <div class="order-header">
                    <h3>Shipment #<?= htmlspecialchars($p['id_kirim']); ?></h3>
                    <span class="status <?= $status_class; ?>"><?= $status_text; ?></span>
                </div>
                
                <div class="item-list">
                    <?php 
                        $items = explode('||', $p['item_list']); 
                        foreach($items as $item) {
                            echo "<p>• ". htmlspecialchars($item) ."</p>";
                        }
                    ?>
                </div>

                <div class="order-footer">
                    <div class="total-price">
                        Total: IDR <?= number_format($p['total_order_rp'] ?? 0, 0, ',', '.'); ?>
                    </div>
                    <div>
                        <a href="detail_pesanan_saya.php?id=<?= htmlspecialchars($p['id_kirim']); ?>&type=kirim" class="btn btn-detail">
                            <i class="fas fa-info-circle"></i> Detail
                        </a>
                        
                        <?php if ($p['final_status'] == 'selesai'): ?>
                        <a href="tambah_review.php?barang_id=<?= $p['single_barang_id']; ?>" class="btn btn-review">
                            <i class="fas fa-star" style="color:#333;"></i> Write Review
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; } 
                } // End else execute
                $stmt_history->close();
            } // End else prepare history
            ?>
            
        </div>
    </div> 

</div> 

</body>
</html>