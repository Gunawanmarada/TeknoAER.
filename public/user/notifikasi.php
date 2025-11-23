<?php
session_start();
// PATH FIX: Go up two levels (from public/user/) to tekno-aer/config/
include '../../config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    // PATH FIX: Redirect to login.php in the same folder (public/user/)
    echo "<script>alert('Please login first.'); window.location='login.php';</script>";
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// helper: detect column names for ID, date, and read status in the notifikasi_user table
function detect_column_names($conn) {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM notifikasi_user");
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
    }
    // search for id, date, and read status columns
    $idKeys = ['id', 'id_notifikasi', 'id_notif', 'notif_id'];
    $dateKeys = ['dibuat_pada', 'dibuat', 'tanggal', 'created_at'];
    $readKeys = ['status_baca', 'is_read']; // PERHATIAN: Memasukkan kolom status_baca/is_read
    
    $foundId = null;
    $foundDate = null;
    $foundRead = null;

    foreach ($idKeys as $k) if (in_array($k, $cols)) { $foundId = $k; break; }
    foreach ($dateKeys as $k) if (in_array($k, $cols)) { $foundDate = $k; break; }
    foreach ($readKeys as $k) if (in_array($k, $cols)) { $foundRead = $k; break; } // Deteksi kolom status baca

    // fallback ID
    if (!$foundId && count($cols)>0) $foundId = $cols[0];
    
    return [
        'idKey' => $foundId, 
        'dateKey' => $foundDate, 
        'readKey' => $foundRead // column name for status_baca/is_read
    ];
}

$meta = detect_column_names($conn);
$idKey = $meta['idKey'];        // column ID name used
$dateKey = $meta['dateKey'];    // column date name
$readKey = $meta['readKey'];    // column read status name

// If ID column is not detected, stop execution
if (!$idKey) {
    die("Error: Could not detect the ID column in the notifikasi_user table.");
}

// process deleting a single notification (with ownership check)
if (isset($_GET['hapus'])) {
    $delId = intval($_GET['hapus']);
    
    // Secure deletion using prepared statements
    $stmt = $conn->prepare("DELETE FROM notifikasi_user WHERE $idKey = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $delId, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: notifikasi.php");
    exit;
}

// process deleting all notifications belonging to the user
if (isset($_GET['hapus_semua'])) {
    // Secure mass deletion using prepared statements
    $stmt = $conn->prepare("DELETE FROM notifikasi_user WHERE user_id = ?");
    if($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: notifikasi.php");
    exit;
}

// Fetch user notifications (sorted by date column if available, otherwise by id desc)
$orderBy = $dateKey ? $dateKey . " DESC" : $idKey . " DESC";
$sql = "SELECT * FROM notifikasi_user WHERE user_id = '$user_id' ORDER BY $orderBy";
$result = $conn->query($sql);


// --- USER INFO & NOTIFICATION LOGIC (For Header) ---
$photo_path_header = '../../assets/uploads/profiles/default.png'; 
$nama_user_header = $_SESSION['nama'] ?? 'Guest User';
$user_role_display = $_SESSION['role'] ?? 'Customer';
$uid = $_SESSION['user_id'] ?? null; 
$notif_count = 0;

if ($conn && $uid) { 
    // Fetch full name and profile photo (Secure)
    $stmt_photo = $conn->prepare("SELECT nama_lengkap, foto_profil FROM user WHERE user_id = ?");
    if ($stmt_photo) {
        $stmt_photo->bind_param("i", $uid);
        $stmt_photo->execute();
        $result_photo = $stmt_photo->get_result();
        
        if ($result_photo->num_rows > 0) {
            $user_data_header = $result_photo->fetch_assoc();
            $foto_profil_file = $user_data_header['foto_profil'] ?? 'default.jpg';
            // Relative path from notifikasi.php to profiles folder
            $actual_path = __DIR__ . '/../../assets/uploads/profiles/' . $foto_profil_file; 
            
            if (!empty($foto_profil_file) && $foto_profil_file !== 'default.jpg' && file_exists($actual_path)) {
                $photo_path_header = '../../assets/uploads/profiles/' . htmlspecialchars($foto_profil_file);
            }
            $nama_user_header = $user_data_header['nama_lengkap'];
            $_SESSION['nama'] = $nama_user_header;
        }
        $stmt_photo->close();
    }
    
    // Count Notifications (UNREAD)
    // Reuse the $readKey variable detected earlier
    if ($readKey) {
        // HANYA hitung yang statusnya 0 (belum dibaca)
        $stmt_notif = $conn->prepare("SELECT COUNT(*) AS jml FROM notifikasi_user WHERE user_id = ? AND {$readKey} = 0"); 
    } else {
        // Fallback: count all if read status column is not detected
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
<title>My Notifications - TeknoAER</title>
<link rel="icon" type="image/jpeg" href="../../assets/uploads/logo/logo.jpg">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    /* ================================================= */
    /* GENERAL CSS (FROM index.php) */
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
    .content { flex-grow: 1; padding: 0; overflow-y: auto; }
    
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
    /* NOTIFICATION SPECIFIC STYLES */
    /* ================================================= */
    .notif-wrapper {
        width: 90%; 
        max-width: 900px; 
        margin: 30px auto; 
        padding: 0;
    }
    h2 { 
        text-align: left; 
        color: #333; 
        margin-bottom: 20px;
        font-size: 1.8em;
        font-weight: 600;
    }
    .title-icon { color: #008080; margin-right: 8px; }

    /* Card Load Animation (FROM index.php) */
    @keyframes cardFadeIn {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-card {
        background: #ffffff;
        padding: 15px 20px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05); /* Matching index.php card style */
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        transition: box-shadow 0.3s, transform 0.3s, background-color 0.3s;
        cursor: pointer;
        
        /* Base Animation */
        opacity: 0; 
        transform: translateY(20px); 
        animation-name: cardFadeIn; 
        animation-duration: 0.5s;
        animation-fill-mode: forwards;
        animation-timing-function: ease-out;
    }
    .notification-card:hover {
        box-shadow: 0 10px 20px rgba(0,0,0,0.15); /* Matching index.php card hover */
        transform: translateY(-2px);
    }

    /* Style for UNREAD notifications */
    .unread-card {
        background-color: #eaf2ff; /* Light blue color */
        border-left: 5px solid #008080;
        font-weight: 500;
    }
    .unread-card:hover {
        background-color: #dbe8ff; 
    }
    .read-card {
        border-left: 5px solid #ccc;
    }

    /* Card Content */
    .notif-icon-col { font-size: 24px; color: #008080; margin-right: 15px; flex-shrink: 0; }
    .notif-content-col { flex-grow: 1; }
    .notif-action-col { flex-shrink: 0; margin-left: 15px; }

    .notif-title { font-weight: bold; font-size: 1.1em; color: #333; margin: 0; }
    .notif-message { 
        font-size: 0.9em; 
        color: #666; 
        margin: 2px 0; 
        /* Display read status below message */
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .notif-date { font-size: 0.75em; color: #999; display: block; margin-top: 4px; }
    
    /* Delete Button */
    .btn-delete {
        background: none; 
        border: none; 
        color: #dc3545; 
        cursor: pointer; 
        font-size: 1.1em;
        padding: 5px;
        transition: color 0.2s, transform 0.2s;
    }
    .btn-delete:hover {
        color: #a71d2a;
        transform: scale(1.1);
    }

    .btn-group {
        text-align: center;
        margin-top: 30px;
    }
    .btn-delete-all {
        background: #dc3545; 
        color: white; 
        padding: 10px 20px; 
        border-radius: 8px; 
        font-weight: 600;
        transition: background 0.2s, transform 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        cursor: pointer;
    }
    .btn-delete-all:hover {
        background: #a71d2a;
        transform: translateY(-2px);
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        color: #555;
        margin-top: 30px;
    }
    
    .status-badge {
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.8em;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-left: 10px;
    }
    .badge-unread {
        background-color: #dc3545;
        color: white;
    }
    .badge-read {
        background-color: #28a745;
        color: white;
    }

</style>
</head>
<body class="loaded">

<div class="main-layout">
    <div class="header">
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="../index.php" class="btn-back-header">
                <i class="fas fa-home"></i> 
                <span>Back to Home</span>
            </a>
            </div>
        
        <div class="header-actions">
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
        <div class="notif-wrapper">
            <h2><i class="fas fa-bell title-icon"></i> My Notifications List</h2>

            <?php 
            $global_item_index = 0; 
            if ($result && $result->num_rows > 0): 
            ?>
            <div class="notification-list">
                <?php
                while ($row = $result->fetch_assoc()):
                    $global_item_index++;
                    $notifId = $row[$idKey] ?? null; 
                    
                    // --- READ STATUS LOGIC ---
                    // isRead = 0 (Unread) hanya jika kolom status baca terdeteksi DAN nilainya 0
                    $isRead = ($readKey && isset($row[$readKey]) && (int)$row[$readKey] === 0) ? 0 : 1; 
                    
                    // Determine date
                    $tanggalTxt = '-';
                    if ($dateKey && isset($row[$dateKey])) {
                        $tanggalTxt = date('M d, Y, H:i', strtotime($row[$dateKey]));
                    } else {
                        foreach (['tanggal','created_at','dibuat','waktu'] as $dk) {
                            if (isset($row[$dk])) { $tanggalTxt = date('M d, Y, H:i', strtotime($row[$dk])); break; }
                        }
                    }
                    
                    // Safely retrieve Title and Message 
                    $judulPesan = htmlspecialchars($row['judul'] ?? 'New Message'); 
                    $isiPesan = htmlspecialchars($row['pesan'] ?? 'No message details.'); 
                    
                    // Add icon based on notification type (if type column exists)
                    $iconClass = 'fa-bell';
                    if (isset($row['tipe'])) {
                        $tipe = strtolower($row['tipe']);
                        if (strpos($tipe, 'transaksi') !== false) $iconClass = 'fa-shopping-cart';
                        else if (strpos($tipe, 'kirim') !== false || strpos($tipe, 'status') !== false) $iconClass = 'fa-shipping-fast';
                        else if (strpos($tipe, 'review') !== false) $iconClass = 'fa-star';
                        else if (strpos($tipe, 'promo') !== false) $iconClass = 'fa-tag';
                        else if (strpos($tipe, 'info') !== false) $iconClass = 'fa-info-circle';
                    }
                ?>
                <div class="notification-card <?= $isRead === 0 ? 'unread-card' : 'read-card'; ?>"
                    style="animation-delay: <?= $global_item_index * 0.1; ?>s;">
                    
                    <div class="notif-icon-col">
                        <i class="fas <?= $iconClass; ?>"></i>
                    </div>

                    <div class="notif-content-col" 
                        <?php if ($notifId): ?>
                            onclick="window.location.href='detail_notifikasi.php?id=<?= intval($notifId); ?>';"
                        <?php endif; ?>>
                        
                        <p class="notif-title"><?= $judulPesan; ?></p>
                        <p class="notif-message">
                            <?= $isiPesan; ?> 
                            <span class="status-badge <?= $isRead === 0 ? 'badge-unread' : 'badge-read'; ?>">
                                <i class="fas <?= $isRead === 0 ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                <?= $isRead === 0 ? 'Unread' : 'Read'; ?>
                            </span>
                        </p>
                        <span class="notif-date"><i class="far fa-clock"></i> <?= $tanggalTxt; ?></span>
                    </div>
                    
                    <div class="notif-action-col">
                        <?php if ($notifId): ?>
                            <a href="notifikasi.php?hapus=<?= intval($notifId); ?>" 
                                class="btn-delete" 
                                title="Delete Notification"
                                onclick="event.stopPropagation(); return confirm('Delete this notification?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        <?php else: ?>
                            <span style="color:#ccc; font-size:1.1em;"><i class="fas fa-trash-alt"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="btn-group" style="animation: cardFadeIn 0.5s ease-out forwards <?= $global_item_index * 0.1 + 0.1; ?>s; opacity: 0; transform: translateY(20px);">
                <a href="notifikasi.php?hapus_semua=1" 
                    class="btn-delete-all"
                    onclick="return confirm('Are you sure you want to delete all notifications? This action cannot be undone.')">
                    <i class="fas fa-trash-alt"></i> Delete All Notifications (<?= $result->num_rows; ?>)
                </a>
            </div>
            
        <?php else: ?>
            <div class="empty-state" style="animation: cardFadeIn 0.5s ease-out forwards 0.1s; opacity: 0; transform: translateY(20px);">
                <i class="fas fa-inbox" style="font-size: 40px; color: #ccc; margin-bottom: 15px;"></i>
                <p>You have no notifications at the moment.</p>
                <a href="../index.php" style="color: #008080; font-weight: 600;"><i class="fas fa-store"></i> Back to Product Catalog</a>
            </div>
        <?php endif; ?>
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