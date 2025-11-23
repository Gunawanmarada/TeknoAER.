<?php
session_start();
// --- Database Connection ---
// Pastikan path ke file db.php sudah benar
include '../config/db.php'; 

// --- READ STATUS COLUMN DETECTION FUNCTION ---
function detect_read_key($conn) {
    $readKeys = ['status_baca', 'is_read'];
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM notifikasi_user");
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
    }
    foreach ($readKeys as $k) {
        if (in_array($k, $cols)) {
            return $k;
        }
    }
    return null; 
}

// --- MAIN LOGIC USER INFO & NOTIFICATIONS ---
$photo_path_header = '../assets/uploads/profiles/default.png'; 
$nama_user_header = 'Guest User';
$notif_count = 0;
$uid = $_SESSION['user_id'] ?? null; 

if (!$uid) {
    // If not logged in, redirect to login
    header("Location: user/login.php");
    exit;
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
            $actual_path = __DIR__ . '/../assets/uploads/profiles/' . $foto_profil_file; 
            
            if (!empty($foto_profil_file) && $foto_profil_file !== 'default.jpg' && file_exists($actual_path)) {
                $photo_path_header = '../assets/uploads/profiles/' . htmlspecialchars($foto_profil_file);
            }
            $nama_user_header = $user_data_header['nama_lengkap'];
            $_SESSION['nama'] = $nama_user_header;
        }
        $stmt_photo->close();
    }
    
    // Count Notifications (Secure: using detect_read_key)
    $readKey = detect_read_key($conn);

    if ($readKey) {
        $stmt_notif = $conn->prepare("SELECT COUNT(*) AS jml FROM notifikasi_user WHERE user_id = ? AND {$readKey} = 0"); 
    } else {
        // Fallback if read status column is not detected
        $stmt_notif = $conn->prepare("SELECT COUNT(*) AS jml FROM notifikasi_user WHERE user_id = ?");
    }

    if (isset($stmt_notif) && $stmt_notif) {
        $stmt_notif->bind_param("i", $uid);
        $stmt_notif->execute();
        $notif_query = $stmt_notif->get_result();
        if ($notif_query && $notif_query->num_rows > 0) {
            $notif_count = (int)$notif_query->fetch_assoc()['jml'];
        }
        $stmt_notif->close();
    }
}

// --- CART LOGIC ---
// Get cart data according to user (Secure)
$stmt_cart = $conn->prepare("
    SELECT k.*, b.nama_barang, b.harga, b.gambar
    FROM keranjang k
    LEFT JOIN barang b ON k.barang_id = b.barang_id
    WHERE k.user_id = ?
");
$stmt_cart->bind_param("i", $uid);
$stmt_cart->execute();
$result_cart = $stmt_cart->get_result();
$stmt_cart->close();

$total_all = 0;
$item_count = $result_cart->num_rows; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - TeknoAER</title>
    <link rel="icon" type="image/jpeg" href="../assets/uploads/logo/logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
    /* ================================================= */
    /* GENERAL CSS FROM INDEX.PHP + CART IMPROVEMENTS */
    /* ================================================= */
    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        background: #ebebeb; 
        margin: 0; 
        padding: 0; 
        overflow-x: hidden; 
        /* Full Page Fade In Animation */
        opacity: 0; 
        transition: opacity 0.5s ease-in; 
    }
    body.loaded {
        opacity: 1;
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
        transition: background 0.2s, transform 0.2s; /* Add Transform */
        position: relative;
    }
    .sidebar-item:hover, .sidebar-item.active { 
        background: #006666; 
        transform: scale(1.05); /* Hover Effect */
    }
    .sidebar-item i { font-size: 24px; }
    
    /* NOTIFICATION BADGE STYLE */
    .notif-container { position: relative; color: #333; font-size: 20px; transition: color 0.2s, transform 0.3s; }
    .notif-container:hover { color: #008080; transform: rotate(10deg); } /* Hover Effect */
    .notif-badge {
        position: absolute; top: -5px; right: -10px; background-color: #dc3545; color: white;
        border-radius: 50%; padding: 2px 6px; font-size: 0.7em; font-weight: bold;
        min-width: 10px; text-align: center; line-height: 1; border: 1px solid #ffffff; 
        z-index: 10;
    }
    .header-actions { display: flex; align-items: center; gap: 20px; }
    .user-info { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    .profile-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #008080; transition: transform 0.3s ease; }
    .user-info:hover .profile-photo { transform: scale(1.1); }
    .user-role { font-size: 0.85em; color: #666; }
    /* END NOTIFICATION BADGE STYLE */

    .content { flex-grow: 1; padding: 0; overflow-y: auto; }
    .catalog-container { padding: 20px; }
    
    .title-icon { color: #008080; }

    /* SPECIFIC CART GRID */
    .grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
    @media (min-width: 768px) {
        .grid { grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); }
    }
    
    /* CARD MODIFIED FOR CART ITEM (Added Animation) */
    .card { 
        background: #ffffff; 
        padding: 15px; 
        border-radius: 10px; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
        display: flex; 
        align-items: flex-start;
        gap: 15px;
        transition: transform 0.3s ease, box-shadow 0.3s ease; 
        min-width: 0; 
        overflow: hidden; 
        cursor: pointer; /* So it can be clicked for Modal */
        
        /* --- FADE-IN ANIMATION FROM INDEX --- */
        opacity: 0; 
        transform: translateY(20px); 
        animation-name: cardFadeIn; 
        animation-duration: 0.5s;
        animation-fill-mode: forwards;
        animation-timing-function: ease-out;
    }
    
    /* Keyframe for Card Load Animation */
    @keyframes cardFadeIn {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card:hover { 
        box-shadow: 0 10px 20px rgba(0,0,0,0.15); 
        transform: translateY(-3px); 
    }
    .card img { 
        width: 80px; height: 80px; object-fit: cover; border-radius: 8px; flex-shrink: 0; 
    }
    .item-details { flex-grow: 1; min-width: 0; overflow: hidden; word-wrap: break-word; }
    .item-details h3 { margin: 0 0 5px 0; font-size: 1.1em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .item-details p { margin: 2px 0; font-size: 0.9em; color: #555; }
    .card .price { font-size: 16px; font-weight: bold; color: #dc3545; margin: 8px 0; }
    
    /* ACTIONS */
    .card-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; flex-shrink: 0; }
    .buy-form { display: flex; align-items: center; justify-content: flex-end; gap: 5px; }
    .qty-input { width: 50px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; text-align: center; }

    /* NEW CHECKOUT SUMMARY CARD (Retained) */
    .summary-card { margin-top: 20px; padding: 25px; border-top: 2px solid #008080; display: flex; justify-content: space-between; align-items: center; }
    .summary-card-left { display: flex; align-items: baseline; gap: 15px; }
    .summary-card-left h3 { font-size: 1.5em; color: #333; margin: 0; }
    .summary-card-left .total { font-size: 1.8em; font-weight: bold; color: #dc3545; margin: 0; }
    .summary-buttons { display: flex; justify-content: flex-end; gap: 10px; flex-grow: 1; }
    .btn-action { flex: 1; padding: 10px 15px; border-radius: 5px; color: white; border: none; cursor: pointer; transition: background 0.2s; text-decoration: none; display: flex; align-items: center; justify-content: center; font-weight: bold; box-sizing: border-box; flex-shrink: 0; gap: 5px; }
    .btn-default { background: #008080; } 
    .btn-green { background: #28a745; } 
    .btn-red { background: #dc3545; } 


    /* --- SLIDE-UP MODAL FROM INDEX.PHP --- */
    .detail-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0, 0, 0, 0.6); z-index: 999; display: none; 
        justify-content: center; align-items: flex-end; opacity: 0;
        transition: opacity 0.4s ease;
    }
    .detail-modal-overlay.active {
        display: flex; opacity: 1;
    }
    .detail-modal-content {
        background: white; width: 100%; max-width: 700px; position: absolute; 
        bottom: 0; max-height: calc(100vh - 50px); height: fit-content; 
        border-top-left-radius: 20px; border-top-right-radius: 20px;
        box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.2); padding: 0; 
        display: flex; flex-direction: column;
        transform: translateY(100%); 
        transition: transform 0.5s cubic-bezier(0.165, 0.84, 0.44, 1); 
    }
    .detail-modal-overlay.active .detail-modal-content {
        transform: translateY(0); 
    }
    .modal-drag-handle {
        width: 50px; height: 5px; background: #ccc; border-radius: 5px;
        margin: 8px auto 15px auto; cursor: grab; touch-action: none; 
        flex-shrink: 0; 
    }
    .loaded-detail-content {
        padding: 0 20px 20px 20px; flex-grow: 1; overflow-y: auto; 
    }
    .loading-content {
        text-align: center; padding: 50px; color: #008080;
    }
    
    @media (max-width: 768px) {
        .sidebar { display: none; } 
        .main-layout { display: block; }
        .header .search-box input { width: 100px; }
        .summary-card { flex-direction: column; align-items: flex-start; }
        .summary-card-left { margin-bottom: 15px; }
        .summary-buttons { flex-direction: column; align-items: stretch; width: 100%; }
        .btn-action { width: 100%; }
    }
</style>
</head>
<body class="loaded">

<div class="main-layout">
    <div class="sidebar">
        <div class="top-icon" style="padding: 10px 0;"><i class="fas fa-leaf"></i></div>
        <a href="index.php" class="sidebar-item" title="Catalog"><i class="fas fa-store"></i></a>
        <a href="keranjang.php" class="sidebar-item active" title="Cart"><i class="fas fa-shopping-cart"></i></a>
        <a href="user/pesanan.php" class="sidebar-item" title="Orders"><i class="fas fa-box"></i></a>
        <a href="user/logout.php" class="sidebar-item" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>

    <div class="content">
        <div class="header" id="mainHeader">
            <div style="display:flex; align-items:center; gap:10px;">
                <span class="logo">TeknoAER</span>
            </div>
            
            <div class="header-actions">
                <a href="user/notifikasi.php" class="notif-container" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notif_count > 0): ?>
                        <span class="notif-badge"><?= $notif_count > 99 ? '99+' : $notif_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="user/dashboard.php" style="color: inherit; text-decoration: none;">
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
            <h2><i class="fas fa-shopping-cart title-icon"></i> Your Shopping Cart (<?= $item_count; ?> Items)</h2>

            <?php if ($item_count > 0): ?>
            <div class="grid">
                <?php 
                $item_index = 0; // Index for staggered animation
                // Reset query result pointer
                $result_cart->data_seek(0);
                while ($row = $result_cart->fetch_assoc()): 
                    $item_index++;
                ?>
                <?php
                    $harga = (int)$row['harga'];
                    $jumlah = (int)$row['jumlah'];
                    $subtotal = $harga * $jumlah;
                    $total_all += $subtotal; // Total calculation still runs
                    $keranjang_id = (int)$row['keranjang_id'];
                    $barang_id = (int)$row['barang_id']; // Get item ID for modal
                    $hapus_link = "hapus_keranjang.php?id=" . $keranjang_id; 
                ?>

                <div 
                    class="card" 
                    onclick="showProductDetail(<?= $barang_id; ?>)"
                    style="animation-delay: <?= $item_index * 0.1; ?>s;"
                >
                    
                    <?php 
                    $image_path = '../private/assets/uploads/' . $row['gambar'];
                    if (!empty($row['gambar']) && file_exists(__DIR__ . '/../private/assets/uploads/' . $row['gambar'])): ?>
                        <img src="<?= $image_path; ?>" alt="<?= htmlspecialchars($row['nama_barang']); ?>">
                    <?php else: ?>
                        <div style="width:80px;height:80px;background:#eee;display:flex;align-items:center;justify-content:center;border-radius:8px; flex-shrink: 0;">
                            No Image
                        </div>
                    <?php endif; ?>
                    
                    <div class="item-details">
                        <h3><?= htmlspecialchars($row['nama_barang']); ?></h3>
                        <p>Unit Price: Rp <?= number_format($harga, 0, ',', '.'); ?></p>
                        <div class="price">Subtotal: Rp <?= number_format($subtotal, 0, ',', '.'); ?></div>
                    </div>

                    <div class="card-actions" onclick="event.stopPropagation()">
                        <form method="POST" action="update_keranjang.php" class="buy-form">
                            <input type="hidden" name="keranjang_id" value="<?= $keranjang_id; ?>">
                            <label for="jumlah_<?= $keranjang_id; ?>">Qty:</label>
                            <input 
                                type="number" 
                                name="jumlah" 
                                id="jumlah_<?= $keranjang_id; ?>"
                                value="<?= $jumlah; ?>" 
                                min="1" 
                                class="qty-input"
                                onchange="this.form.submit()"
                            >
                        </form>

                        <a href="<?= $hapus_link ?>" class="btn-action btn-red"
                            onclick="return confirm('Remove this item from the cart?')" style="width: 100px; text-align: center; font-weight: normal; flex-shrink: unset;">
                            <i class="fas fa-trash-alt"></i> Remove
                        </a>
                    </div>

                </div>
                <?php endwhile; ?>
            </div>
            
            <div class="summary-card card">
                <div class="summary-card-left">
                    <h3>Total Purchase</h3>
                    <div class="total">Rp <?= number_format($total_all, 0, ',', '.') ?></div>
                </div>
                
                <div class="summary-buttons">
                    <a href="index.php" class="btn-action btn-default">
                      <i class="fas fa-store"></i> Continue Shopping
                    </a>
                    
                    <form method="GET" action="beli_sekarang.php" style="margin: 0; flex: 1;">
                        <input type="hidden" name="from" value="cart">
                        <button type="submit" class="btn-action btn-green">
                            <i class="fas fa-shipping-fast"></i> Proceed to Shipping
                        </button>
                    </form>
                </div>
            </div>

            <?php else: ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 10px;">
                    <i class="fas fa-shopping-cart fa-3x title-icon" style="margin-bottom: 15px;"></i>
                    <p style="font-size: 1.2em;">Your cart is empty. Come on, find your dream items!</p>
                    <div style="display: flex; justify-content: center; margin-top: 20px;">
                        <a href="index.php" class="btn-action btn-default" style="flex: unset; width: 250px;">
                            <i class="fas fa-store"></i> Start Shopping Now
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
        
    </div> 

</div> 

<div class="detail-modal-overlay" id="detailModalOverlay" onclick="closeDetailModal(event)">
    <div class="detail-modal-content" id="detailModalContent" onclick="event.stopPropagation()">
        <div class="modal-drag-handle" id="modalDragHandle"></div> 
        <div id="loadedDetailContent" class="loaded-detail-content">
            <div class="loading-content">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p>Loading product details...</p>
            </div>
        </div>
    </div>
</div>

<script>
// =====================================================
// SCRIPT LOGIC (MODAL & ANIMATION) FROM INDEX.PHP
// =====================================================

const detailModalOverlay = document.getElementById('detailModalOverlay');
const detailModalContent = document.getElementById('detailModalContent');
const loadedDetailContent = document.getElementById('loadedDetailContent');
const modalDragHandle = document.getElementById('modalDragHandle');

let isDragging = false;
let startY = 0;
let originalTranslateY = 0;

// ========================================
// OPEN DETAIL MODAL
// ========================================
async function showProductDetail(id) {

    // Reset transition and initial position for entry animation
    detailModalContent.style.transition = "none";
    detailModalContent.style.transform = "translateY(100%)";

    loadedDetailContent.innerHTML = `
        <div class="loading-content">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p>Loading product details...</p>
        </div>
    `;

    detailModalOverlay.classList.add("active");
    document.body.style.overflow = "hidden";

    // Entry animation
    setTimeout(() => {
        detailModalContent.style.transition = "transform .45s cubic-bezier(0.165,0.84,0.44,1)";
        detailModalContent.style.transform = "translateY(0)";
    }, 20);

    try {
        // Assume detail.php file is in the same directory
        const res = await fetch(`detail.php?id=${id}`);
        const html = await res.text();
        loadedDetailContent.innerHTML = html;

    } catch (err) {
        loadedDetailContent.innerHTML = `
            <div style="padding:20px;text-align:center;color:red;">
                <h2>Loading Error</h2>
                <p>Failed to load product details.</p>
            </div>
        `;
    }
}


// ========================================
// CLOSE MODAL
// ========================================
function closeDetailModal(e) {
    if (e && e.target.id !== "detailModalOverlay") return;

    detailModalContent.style.transition = "transform .4s cubic-bezier(0.165,0.84,0.44,1)";
    detailModalContent.style.transform = "translateY(100%)";

    setTimeout(() => {
        detailModalOverlay.classList.remove("active");
        loadedDetailContent.innerHTML = "";
        document.body.style.overflow = "";
    }, 400);
}


// ========================================
// DRAG HANDLERS (To close modal by dragging down)
// ========================================
function handleDragStart(e) {
    if (!detailModalOverlay.classList.contains("active")) return;
    isDragging = true;
    startY = e.touches ? e.touches[0].clientY : e.clientY;
    
    // Get current Y position
    const transformStyle = detailModalContent.style.transform;
    const match = transformStyle.match(/translateY\(([-0-9.]+)px\)/);
    originalTranslateY = match ? parseFloat(match[1]) : 0;
    
    detailModalContent.style.transition = "none";
    e.preventDefault();
}

function handleDragMove(e) {
    if (!isDragging) return;

    const currentY = e.touches ? e.touches[0].clientY : e.clientY;
    const deltaY = currentY - startY;

    let newY = originalTranslateY + deltaY;

    // Limit so modal cannot go up (newY must not be less than 0)
    newY = Math.max(newY, 0);

    detailModalContent.style.transform = `translateY(${newY}px)`;
    e.preventDefault();
}

function handleDragEnd() {
    if (!isDragging) return;
    isDragging = false;

    detailModalContent.style.transition = "transform .25s cubic-bezier(0.165,0.84,0.44,1)";

    const match =
        detailModalContent.style.transform.match(/translateY\(([-0-9.]+)px\)/);
    if (!match) {
        detailModalContent.style.transform = "translateY(0)";
        return;
    }

    const currentY = parseFloat(match[1]);

    // If dragged down more than 40% of modal height, close modal
    if (currentY > detailModalContent.offsetHeight * 0.40) {
        closeDetailModal();
        return;
    }

    // Return to default position (0)
    detailModalContent.style.transform = "translateY(0)";
}


// ========================================
// MODAL DRAG EVENT LISTENERS
// ========================================
modalDragHandle.addEventListener("mousedown", handleDragStart);
modalDragHandle.addEventListener("touchstart", handleDragStart);

document.addEventListener("mousemove", handleDragMove, { passive: false });
document.addEventListener("touchmove", handleDragMove, { passive: false });

document.addEventListener("mouseup", handleDragEnd);
document.addEventListener("touchend", handleDragEnd);

// ========================================
// BODY LOAD ANIMATION
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('loaded');
});

</script>

</body>
</html>