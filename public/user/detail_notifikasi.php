<?php
session_start();
// PATH FIX: Go up two levels (from public/user/) to tekno-aer/config/
include '../../config/db.php';

// --- MAIN NOTIFICATION DETAIL LOGIC ---

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    // PATH FIX: Redirect to login.php in the same folder (public/user/)
    echo "<script>alert('Please login first.'); window.location='login.php';</script>";
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$notif_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($notif_id === 0) {
    header("Location: notifikasi.php");
    exit;
}

// 1. Detect the correct ID, Read Status, and Date columns
function detect_column_names_detail($conn) {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM notifikasi_user");
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
    }
    $idKeys = ['id', 'id_notifikasi', 'id_notif', 'notif_id'];
    // PERBAIKAN: Menambahkan 'status_baca' secara eksplisit ke dalam array deteksi
    $readKeys = ['status_baca', 'is_read']; 
    $dateKeys = ['dibuat_pada', 'dibuat', 'tanggal', 'created_at']; 
    
    $foundId = null;
    $foundRead = null;
    $foundDate = null;

    foreach ($idKeys as $k) if (in_array($k, $cols)) { $foundId = $k; break; }
    foreach ($readKeys as $k) if (in_array($k, $cols)) { $foundRead = $k; break; }
    foreach ($dateKeys as $k) if (in_array($k, $cols)) { $foundDate = $k; break; }
    
    if (!$foundId && count($cols)>0) $foundId = $cols[0];
    return ['idKey' => $foundId, 'readKey' => $foundRead, 'dateKey' => $foundDate];
}

$meta = detect_column_names_detail($conn);
$idKey = $meta['idKey'];
$readKey = $meta['readKey'];
$dateKey = $meta['dateKey']; 

if (!$idKey) {
    die("Error: Could not detect the ID column in the notifikasi_user table.");
}


// 2. MARK AS READ (LOGIKA DIPERBAIKI)
// Logika ini hanya akan berjalan jika $readKey (misalnya 'status_baca') ditemukan
if ($readKey) {
    // Hanya perbarui jika saat ini belum dibaca (untuk efisiensi)
    $stmt = $conn->prepare("UPDATE notifikasi_user SET $readKey = 1 WHERE $idKey = ? AND user_id = ? AND $readKey = 0");
    if ($stmt) {
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}


// 3. Fetch Notification Details
$detail = null;
// Gunakan Prepared Statement untuk keamanan dan pemeriksaan kepemilikan
$stmt = $conn->prepare("SELECT * FROM notifikasi_user WHERE $idKey = ? AND user_id = ?");

if ($stmt) {
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $detail = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$detail) {
    echo "<script>alert('Notification not found or does not belong to you.'); window.location='notifikasi.php';</script>";
    exit;
}

// Add icon based on notification type (if type column exists)
$iconClass = 'fa-bell';
if (isset($detail['tipe'])) {
    $tipe = strtolower($detail['tipe']);
    if (strpos($tipe, 'transaksi') !== false) $iconClass = 'fa-shopping-cart';
    else if (strpos($tipe, 'kirim') !== false || strpos(mb_strtolower($detail['judul']), 'status') !== false) $iconClass = 'fa-shipping-fast';
    else if (strpos($tipe, 'review') !== false) $iconClass = 'fa-star';
    else if (strpos($tipe, 'promo') !== false) $iconClass = 'fa-tag';
    else if (strpos($tipe, 'info') !== false) $iconClass = 'fa-info-circle';
}

// --- USER INFO & NOTIFICATION LOGIC (For Header) ---
$photo_path_header = '../../assets/uploads/profiles/default.png'; 
$nama_user_header = $_SESSION['nama'] ?? 'Guest User';
$user_role_display = $_SESSION['role'] ?? 'Customer';
$uid = $_SESSION['user_id'] ?? null; 
$notif_count = 0;

if ($conn && $uid) { 
    // Fetch profile photo
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
        }
        $stmt_photo->close();
    }
    
    // Count Unread Notifications
    if ($readKey) {
        // Notif count sekarang akan menampilkan angka yang sudah diperbarui
        $stmt_notif = $conn->prepare("SELECT COUNT(*) AS jml FROM notifikasi_user WHERE user_id = ? AND {$readKey} = 0"); 
    } else {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notification Detail - TeknoAER</title>
<link rel="icon" type="image/jpeg" href="../../assets/uploads/logo/logo.jpg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    /* ================================================= */
    /* GENERAL & HEADER CSS (FROM index.php) */
    /* ================================================= */
    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        background: #ebebeb; 
        margin: 0; 
        padding: 0; 
        overflow-x: hidden; 
        opacity: 0; 
        transition: opacity 0.5s ease-in; 
    }
    body.loaded { opacity: 1; }
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
    .main-layout { display: flex; min-height: 100vh; flex-direction: column; } 
    .content { flex-grow: 1; padding: 20px; overflow-y: auto; }
    
    .header-actions { display: flex; align-items: center; gap: 20px; }
    .user-info { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    .user-info:hover .profile-photo { transform: scale(1.1); }
    .profile-photo {
        width: 40px; height: 40px; border-radius: 50%; object-fit: cover; 
        border: 2px solid #008080; transition: transform 0.3s ease;
    }
    .user-role { font-size: 0.85em; color: #666; }

    /* Notification - Hover Animation */
    .notif-container {
        position: relative;
        color: #333; 
        font-size: 20px;
        transition: color 0.2s, transform 0.3s;
    }
    .notif-container:hover {
        color: #008080;
        transform: rotate(10deg); 
    }
    .notif-badge {
        position: absolute; top: -5px; right: -10px;
        background-color: #dc3545; color: white;
        border-radius: 50%; padding: 2px 6px;
        font-size: 0.7em; font-weight: bold;
        min-width: 10px; text-align: center; line-height: 1;
        border: 1px solid #ffffff; z-index: 10;
        transition: transform 0.2s; 
    }
    .notif-badge:hover { transform: scale(1.1); }
    
    /* Tombol BACK BARU */
    .btn-back-header {
        background: #008080; 
        color: white; 
        padding: 8px 15px; 
        border-radius: 5px; 
        font-weight: 600;
        display: flex; 
        align-items: center; 
        gap: 8px;
        transition: background 0.3s, transform 0.2s;
        box-shadow: 0 2px 5px rgba(0, 128, 128, 0.3);
    }
    .btn-back-header:hover {
        background: #006666;
        transform: translateY(-1px);
    }
    .btn-back-header .fas {
        font-size: 16px; 
    }
    /* Akhir Tombol BACK BARU */
    
    /* ================================================= */
    /* DETAIL SPECIFIC STYLES (Card Style) */
    /* ================================================= */
    .detail-card {
        background: #ffffff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05); /* Matching index.php Card style */
        width: 90%; 
        max-width: 800px; 
        margin: 0 auto; 
        min-height: 400px;
        /* Animation: Add load animation for this main card */
        opacity: 0; 
        transform: translateY(20px); 
        animation: cardFadeIn 0.5s ease-out forwards 0.1s;
    }
    @keyframes cardFadeIn {
        to { opacity: 1; transform: translateY(0); }
    }
    .notif-title {
        font-size: 28px;
        color: #008080; /* TeknoAER main color */
        margin-bottom: 10px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
    }
    .notif-content {
        font-size: 16px;
        line-height: 1.7;
        color: #444;
        padding: 15px 0;
        white-space: pre-wrap; /* Preserve nl2br formatting */
    }
    .notif-meta {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #eee;
        font-size: 13px;
        color: #777;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    /* Action Buttons */
    .btn-action { 
        padding: 10px 15px; 
        border-radius: 5px; 
        background: #dc3545; /* Default: Delete color */
        color: white; 
        border: none; 
        cursor: pointer; 
        transition: background 0.2s, transform 0.2s; 
        text-decoration: none; 
        display: inline-flex; 
        align-items: center;
        gap: 8px;
        font-weight: 600;
    }
    .btn-action:hover {
        background: #a71d2a; 
        transform: translateY(-2px);
    }
    .btn-secondary {
        background: #6c757d;
    }
    .btn-secondary:hover {
        background: #5a6268;
    }
    .btn-info {
        background: #28a745;
    }
    .btn-info:hover {
        background: #1e7e34;
    }
    .action-group {
        margin-top: 30px;
        display: flex;
        gap: 10px;
    }

</style>
</head>
<body class="loaded">

<div class="main-layout">
    <div class="header">
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="notifikasi.php" class="btn-back-header">
                <span>Back to List</span>
            </a>
            </div>
        
        <div class="header-actions">
            <a href="notifikasi.php" class="notif-container" title="Your Notifications List">
                <i class="fas fa-bell" style="color: #008080;"></i>
                <?php if ($notif_count > 0): ?>
                    <span class="notif-badge"><?= $notif_count > 99 ? '99+' : $notif_count; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="dashboard.php" style="color: inherit; text-decoration: none;">
                <div class="user-info">
                    <div style="text-align:right;">
                        <span style="font-weight:600; display:block;"><?= htmlspecialchars($nama_user_header); ?></span>
                        <span class="user-role">
                            <?= htmlspecialchars($user_role_display); ?>
                        </span>
                    </div>
                    <img src="<?= $photo_path_header; ?>" alt="Profile Photo" class="profile-photo">
                </div>
            </a>
        </div>
    </div>

    <div class="content">
        <div class="detail-card">
            <div class="notif-title">
                <i class="fas <?= $iconClass; ?>"></i>
                <?= htmlspecialchars($detail['judul'] ?? 'Message Detail'); ?>
            </div>
            
            <div class="notif-content">
                <?= nl2br(htmlspecialchars($detail['pesan'])); ?>
            </div>
            
            <div class="notif-meta">
                <span title="Notification ID">
                    <i class="fas fa-hashtag"></i> #<?= htmlspecialchars($detail[$idKey] ?? 'N/A'); ?>
                </span>
                <span>
                    <i class="far fa-clock"></i> 
                    <?php 
                        // Get the valid date column name
                        $dateColName = $dateKey && array_key_exists($dateKey, $detail) ? $dateKey : null;

                        if ($dateColName) {
                            echo date('F d, Y, H:i', strtotime($detail[$dateColName]));
                        } else {
                            echo 'Date Unavailable'; 
                        }
                    ?>
                </span>
            </div>

            <div class="action-group">
                
                <a href="notifikasi.php" class="btn-action btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Notifications List
                </a>
                
                <?php if (!empty($detail['link'])): ?>
                    <a href="<?= htmlspecialchars($detail['link']); ?>" target="_blank" class="btn-action btn-info">
                        View Related Detail <i class="fas fa-external-link-alt"></i>
                    </a>
                <?php endif; ?>
                
                <a href="notifikasi.php?hapus=<?= intval($notif_id); ?>" 
                    class="btn-action" 
                    style="background: #dc3545;"
                    onclick="return confirm('Delete this notification? This action cannot be undone.')">
                    <i class="fas fa-trash-alt"></i> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Logic to add 'loaded' class to body when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('loaded');
});
</script>

</body>
</html>