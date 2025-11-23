<?php
session_start();
// --- Koneksi Database ---
// Pastikan file ini ada di ../config/db.php dan berisi variabel $conn
include '../config/db.php'; 

// --- FUNGSI DETEKSI KOLOM STATUS BACA ---
function detect_read_key($conn) {
    if (!$conn) return null;
    $readKeys = ['status_baca', 'is_read'];
    $cols = [];
    // Mencegah error jika tabel tidak ada
    $tableExists = $conn->query("SHOW TABLES LIKE 'notifikasi_user'")->num_rows > 0;
    if (!$tableExists) return null;

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

// --- LOGIKA UTAMA KATALOG BARANG & USER INFO ---
$keyword = '';
$result_barang = null;

if (isset($_GET['keyword']) && !empty(trim($_GET['keyword']))) {
    $keyword = trim($_GET['keyword']);
    if ($conn) {
        // Menggunakan prepared statement untuk pencarian lebih aman
        $stmt_search = $conn->prepare("SELECT * FROM barang WHERE nama_barang LIKE ? ORDER BY barang_id DESC");
        if ($stmt_search) {
            $search_param = "%" . $keyword . "%";
            $stmt_search->bind_param("s", $search_param);
            $stmt_search->execute();
            $result_barang = $stmt_search->get_result();
            $stmt_search->close();
        }
    }
} else {
    // Query default jika tidak ada keyword
    $sql_default = "SELECT * FROM barang ORDER BY barang_id DESC";
    $result_barang = $conn ? $conn->query($sql_default) : null;
}


$photo_path_header = '../private/assets/logo/default.png'; 
$nama_user_header = 'Guest User';
$notif_count = 0;
$user_logged_in = isset($_SESSION['user_id']);
$user_role_display = $_SESSION['role'] ?? 'Tamu'; // Default Role

if ($user_logged_in && ($conn)) { 
    $uid = $_SESSION['user_id'];
    
    // Ambil nama lengkap dan foto profil
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
            
            // Mengambil role dari session (asumsi role disimpan saat login)
            $user_role_display = $_SESSION['role'] ?? 'Pelanggan'; 
        }
        $stmt_photo->close();
    }
    
    // Hitung Notifikasi (Hanya yang BELUM DIBACA)
    $readKey = detect_read_key($conn);

    if ($readKey) {
        $stmt_notif = $conn->prepare("SELECT COUNT(*) AS jml FROM notifikasi_user WHERE user_id = ? AND {$readKey} = 0"); 
    } else {
        // Jika kolom tidak ditemukan, hitung semua notifikasi
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TeknoAER</title>
   <link rel="icon" type="image/jpeg" href="../assets/uploads/logo/logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ================================================= */
        /* CSS UMUM + ANIMASI BARU */
        /* ================================================= */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #ebebeb; 
            margin: 0; 
            padding: 0; 
            overflow-x: hidden; 
            /* Animasi Fade In Halaman Penuh */
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
        .search-box { position: relative; }
        .search-box input { 
            padding: 5px 10px 5px 30px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            transition: box-shadow 0.3s, border-color 0.3s;
        }
        .search-box input:focus {
            border-color: #008080;
            box-shadow: 0 0 5px rgba(0, 128, 128, 0.2);
            outline: none;
        }
        .search-box i { position:absolute; left:10px; top:8px; color:#aaa; cursor: pointer; transition: color 0.3s; }
        .search-box i:hover { color: #008080; }
        .main-layout { display: flex; min-height: 100vh; } 
        
        /* Sidebar Item - Animasi Hover */
        .sidebar { width: 70px; background: #008080; color: white; padding: 10px 0; display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
        .sidebar-item { 
            padding: 15px 0; 
            cursor: pointer; 
            width: 100%; 
            text-align: center; 
            transition: background 0.2s, transform 0.2s; 
            position: relative;
        } 
        .sidebar-item:hover, .sidebar-item.active { 
            background: #006666; 
            transform: scale(1.05);
        }
        .sidebar-item i { font-size: 24px; }
        
        .content { 
            flex-grow: 1; 
            padding: 0; 
            overflow-y: auto; 
            scrollbar-width: thin; 
            scrollbar-color: rgba(0, 128, 128, 0.4) transparent; 
        }

        .content::-webkit-scrollbar { width: 8px; height: 8px; }
        .content::-webkit-scrollbar-thumb { background-color: rgba(0, 128, 128, 0.4); border-radius: 10px; }
        .content::-webkit-scrollbar-track { background: transparent; }

        .catalog-container { padding: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        
        /* Card Produk - Animasi Hover & Initial Load */
        .card { 
            background: #ffffff; 
            padding: 15px; 
            border-radius: 10px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
            text-align: center; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.5s ease; 
            display: flex; 
            flex-direction: column; 
            cursor: pointer;
            opacity: 0; 
            transform: translateY(20px); 
        }
        .card:hover { 
            box-shadow: 0 10px 20px rgba(0,0,0,0.15); 
            transform: translateY(-5px) scale(1.02); 
        }
        
        /* Animasi Card Load */
        @keyframes cardFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card img { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; }
        .card h3 { margin: 5px 0; }
        .card .price { font-size: 18px; font-weight: bold; color: #008080; margin: 10px 0 15px 0; }
        
        /* Tombol Aksi - Animasi Hover */
        .btn-action { 
            padding: 10px 15px; 
            border-radius: 5px; 
            background: #008080; 
            color: white; 
            border: none; 
            cursor: pointer; 
            margin-top: 10px; 
            transition: background 0.2s, transform 0.2s; 
            text-decoration: none; 
            display: block; 
            display: flex; /* Untuk ikon di tombol login */
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        .btn-action:hover {
            background: #006666; 
            transform: translateY(-2px);
        }
        .buy-form { display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; }
        
        /* Notifikasi - Animasi Hover */
        .header-actions { display: flex; align-items: center; gap: 20px; }
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

        /* Foto Profil Header - Animasi Hover */
        .user-info { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .user-info:hover .profile-photo {
            transform: scale(1.1);
        }
        .profile-photo {
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid #008080; 
            transition: transform 0.3s ease;
        }
        .user-role { font-size: 0.85em; color: #666; }

        /* Sidebar badge style (dikembalikan) */
        .sidebar-notif-badge {
            position: absolute; 
            top: 5px; 
            right: 5px; 
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 0.7em;
            font-weight: bold;
            line-height: 1;
            min-width: 10px;
            text-align: center;
        }
        
        /* --- MODAL SLIDE-UP (DETAIL PRODUK) --- */
        .detail-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6); 
            z-index: 999; /* Z-Index Modal Detail */
            display: none; 
            justify-content: center;
            align-items: flex-end; 
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .detail-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .detail-modal-content {
            background: white;
            width: 100%;
            max-width: 700px;
            position: absolute; 
            bottom: 0; 
            top: auto; 
            max-height: calc(100vh - 50px); 
            height: fit-content; 
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            box-shadow: 0 -8px 25px rgba(0, 0, 0, 0.2); 
            padding: 0; 
            display: flex; 
            flex-direction: column;
            transform: translateY(100%); 
            transition: transform 0.5s cubic-bezier(0.165, 0.84, 0.44, 1); 
        }
        .detail-modal-overlay.active .detail-modal-content {
            transform: translateY(0); 
        }
        .modal-drag-handle {
            width: 50px;
            height: 5px;
            background: #ccc;
            border-radius: 5px;
            margin: 8px auto 15px auto; 
            cursor: grab;
            touch-action: none; 
            flex-shrink: 0; 
        }
        .loaded-detail-content {
            padding: 0 20px 20px 20px; 
            flex-grow: 1; 
            overflow-y: auto; 
        }
        .loading-content {
            text-align: center;
            padding: 50px;
            color: #008080;
        }
        
        /* SCROLL TOP BUTTON */
        #scrollTopBtn {
            display: none; 
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 90; 
            border: none;
            outline: none;
            background-color: #008080;
            color: white;
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 50%;
            font-size: 18px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: opacity 0.3s;
        }
        #scrollTopBtn:hover {
            background-color: #006666;
        }

        /* ================================================= */
        /* POPUP LOGIN - FIXED BUG STYLING */
        /* ================================================= */
        .popup {
            position: fixed;
            z-index: 1000; /* Z-Index Pop-up Login harus lebih tinggi dari 999 */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4); 
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px); 

            /* ATURAN KUNCI UNTUK MEMPERBAIKI BUG DISPLAY/FLICKERING */
            display: flex; 
            visibility: hidden; 
            opacity: 0; 
            pointer-events: none; 
            transition: opacity 0.3s ease; 
        }
        .popup.show {
            visibility: visible;
            opacity: 1;
            pointer-events: auto; 
        }

        .popup-content {
            background-color: #fefefe;
            margin: auto;
            padding: 30px; 
            border-radius: 12px; 
            width: 90%;
            max-width: 400px; 
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
            animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; 
        }
        .popup-content h2 { 
            margin-top: 10px; 
            color: #204969; 
            font-size: 1.8em;
        }
        .popup-content p { 
            margin-bottom: 25px; 
            color: #555;
            font-size: 1.1em;
        }
        .popup-icon {
            font-size: 4em; 
            color: #008080; 
            margin-bottom: 15px;
            animation: iconBounce 1s infinite alternate; 
        }

        /* Animasi untuk masuk (Bounce In) */
        @keyframes bounceIn {
            0% { transform: scale(0.8); opacity: 0; }
            60% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); }
        }

        /* Animasi untuk ikon */
        @keyframes iconBounce {
            from { transform: translateY(0px); }
            to { transform: translateY(-5px); }
        }

        /* Penyesuaian Tombol dalam Pop-up */
        .popup-content .btn-action {
            padding: 12px 15px;
            font-size: 1.1em;
            font-weight: 700;
            margin-bottom: 15px !important; 
        }
        .btn-secondary-close {
            background: #6c757d !important; 
        }
        .btn-secondary-close:hover {
            background: #5a6268 !important;
        }
        /* ================================================= */

        @media (max-width: 768px) {
            .sidebar { display: none; } 
            .main-layout { display: block; }
        }
    </style>
</head>
<body class="loaded">

<div class="main-layout">
    <div class="sidebar">
        <div class="top-icon" style="padding: 10px 0;"><i class="fas fa-leaf"></i></div> 
        <a href="index.php" class="sidebar-item active" title="Katalog"><i class="fas fa-store"></i></a>
        <a href="keranjang.php" class="sidebar-item" title="Keranjang"><i class="fas fa-shopping-cart"></i></a>
        <a href="user/pesanan.php" class="sidebar-item" title="Pesanan"><i class="fas fa-box"></i></a>
        <?php if ($user_logged_in): ?>
            <a href="user/logout.php" class="sidebar-item" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        <?php else: ?>
            <a href="user/login.php" class="sidebar-item" title="Login"><i class="fas fa-sign-in-alt"></i></a>
        <?php endif; ?>
    </div>

    <div class="content">
        <div class="header" id="mainHeader">
            <div style="display:flex; align-items:center; gap:10px;">
                <span class="logo">TeknoAER</span>
                <form method="GET" action="index.php" id="searchForm" class="search-box">
                    <input type="text" placeholder="Cari Barang" name="keyword" value="<?= htmlspecialchars($keyword); ?>">
                    <i class="fas fa-search" onclick="document.getElementById('searchForm').submit();"></i>
                </form>
            </div>
            
            <div class="header-actions">
                
                <?php if ($user_logged_in): ?>
                <a href="user/notifikasi.php" class="notif-container" title="Notifikasi">
                    <i class="fas fa-bell"></i>
                    <?php if ($notif_count > 0): ?>
                        <span class="notif-badge"><?= $notif_count > 99 ? '99+' : $notif_count; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <a href="user/dashboard.php" style="color: inherit; text-decoration: none;">
                    <div class="user-info">
                        <div style="text-align:right;">
                            <span style="font-weight:600; display:block;"><?= htmlspecialchars($nama_user_header); ?></span>
                           <span class="user-role">
                                <?= htmlspecialchars($user_role_display); ?>
                            </span>
                        </div>
                        <img src="<?= $photo_path_header; ?>" alt="Foto Profil" class="profile-photo">
                    </div>
                </a>
            </div>
        </div>

        <div class="catalog-container">
            <h2>Latest Products
                <?php if ($keyword): ?>
                    <small style="font-size: 0.7em; color: #777;">(Hasil untuk: "<?= htmlspecialchars($keyword); ?>")</small>
                <?php endif; ?>
            </h2>

            <div class="grid">
            <?php 
            if ($result_barang && $result_barang->num_rows > 0): 
                $card_index = 0; // Inisialisasi index untuk delay animasi
                while ($row = $result_barang->fetch_assoc()): 
                    $card_index++; // Increment index
            ?>
                <div 
                    class="card" 
                    onclick="handleCardClick(<?= $row['barang_id']; ?>)"
                    style="animation: cardFadeIn 0.5s forwards ease-out <?= $card_index * 0.1; ?>s;"
                >
                    
                    <?php 
                    $image_path = '../private/assets/uploads/' . $row['gambar'];
                    if (!empty($row['gambar']) && file_exists(__DIR__ . '/../private/assets/uploads/' . $row['gambar'])): ?>
                        <img src="<?= $image_path; ?>" alt="<?= htmlspecialchars($row['nama_barang']); ?>">
                    <?php else: ?>
                        <div style="height:200px;background:#eee;display:flex;align-items:center;justify-content:center;border-radius:8px;">
                            Gambar tidak tersedia
                        </div>
                    <?php endif; ?>
                    
                    <h3><?= htmlspecialchars($row['nama_barang']); ?></h3>
                    <p><b>Categories:</b> <?= htmlspecialchars($row['kategori']); ?></p>
                    <div class="price">Rp <?= number_format($row['harga'],0,',','.'); ?></div>

                    <form action="beli.php" method="post" class="buy-form" onclick="event.stopPropagation();">
                        <input type="hidden" name="barang_id" value="<?= $row['barang_id']; ?>">
                        <label for="jumlah_<?= $row['barang_id']; ?>">Jml:</label>
                        <input type="number" id="jumlah_<?= $row['barang_id']; ?>" name="jumlah" value="1" min="1" max="99" required style="width: 50px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">

                        <?php if (!$user_logged_in): ?>
                            <button type="button" class="btn-action" onclick="showLoginPopup()">
                                <i class="fas fa-shopping-cart"></i> + Keranjang
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn-action">
                                <i class="fas fa-shopping-cart"></i> + Keranjang
                            </button>
                        <?php endif; ?>
                    </form>

                </div>
            <?php 
                endwhile; 
            else:
            ?>
                <p style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #777;">
                    <?php echo $keyword ? "Produk dengan kata kunci \"".htmlspecialchars($keyword)."\" tidak ditemukan." : "Tidak ada produk yang tersedia."; ?>
                </p>
            <?php
            endif;
            ?>
            </div>
        </div>
        
    </div> 

</div> 

<div class="detail-modal-overlay" id="detailModalOverlay" onclick="closeDetailModal(event)">
    <div class="detail-modal-content" id="detailModalContent" onclick="event.stopPropagation()">
        <div class="modal-drag-handle" id="modalDragHandle"></div> 
        <div id="loadedDetailContent" class="loaded-detail-content">
            <div class="loading-content">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p>Memuat detail barang...</p>
            </div>
        </div>
    </div>
</div>

<div class="popup" id="loginPopup">
    <div class="popup-content">
        <i class="fas fa-lock popup-icon"></i>
        
        <h2>Access Restricted</h2>
        <p>You must Log In or Register to view the product details.</p>
        
        <a href="user/login.php" class="btn-action" style="display: block;">
            <i class="fas fa-sign-in-alt"></i> Log In Now
        </a>
        
        <button 
            type="button" 
            class="btn-action btn-secondary-close" 
            id="closePopup" 
            style="display: block; width: 100%; margin-top: 5px;"
        >
            <i class="fas fa-times-circle"></i> Continue Browsing
        </button>
    </div>
</div>
<button id="scrollTopBtn" title="Kembali ke Atas" onclick="scrollToTop()">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
// =====================================================
// FINAL COMPLETE DRAGGABLE BOTTOM SHEET (ANTI-DRAG-UP)
// =====================================================

const detailModalOverlay = document.getElementById('detailModalOverlay');
const detailModalContent = document.getElementById('detailModalContent');
const loadedDetailContent = document.getElementById('loadedDetailContent');
const modalDragHandle = document.getElementById('modalDragHandle');

// --- Status Login dari PHP (ditempatkan di JS) ---
const userIsLoggedIn = <?= json_encode($user_logged_in); ?>; 

let isDragging = false;
let startY = 0;
let originalTranslateY = 0;


// ========================================
// FUNGSI UTAMA UNTUK MENGATASI BUG
// ========================================

/**
 * Menangani klik pada kartu produk.
 * Jika sudah login, tampilkan detail. Jika belum, tampilkan pop-up login.
 */
function handleCardClick(id) {
    if (userIsLoggedIn) {
        showProductDetail(id);
    } else {
        // Jika belum login, tampilkan pop-up login dan TIDAK membuka modal detail
        showLoginPopup();
    }
}


// ========================================
// BUKA/TUTUP POPUP LOGIN (FIXED BUG LOGIC)
// ========================================
const loginPopup = document.getElementById('loginPopup');
const closePopupBtn = document.getElementById('closePopup');

function showLoginPopup() {
    // HANYA Fokus pada Pop-up Login. Tidak perlu memanipulasi detail modal di sini.
    loginPopup.classList.add('show');
    document.body.style.overflow = 'hidden'; 
}

function closeLoginPopup() {
    loginPopup.classList.remove('show');
    document.body.style.overflow = ''; 
}

// Event Listeners untuk Pop-up Login
closePopupBtn.addEventListener('click', closeLoginPopup);
loginPopup.addEventListener('click', function(e) {
    if (e.target === loginPopup) {
        closeLoginPopup();
    }
});


// ========================================
// TUTUP MODAL DETAIL PRODUK
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
// BUKA MODAL DETAIL PRODUK (Hanya dipanggil jika userIsLoggedIn = true)
// ========================================
async function showProductDetail(id) {

    detailModalContent.style.transition = "none";
    detailModalContent.style.transform = "translateY(100%)";

    loadedDetailContent.innerHTML = `
        <div class="loading-content">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p>Memuat detail barang...</p>
        </div>
    `;

    detailModalOverlay.classList.add("active");
    document.body.style.overflow = "hidden"; // Mengatur overflow saat modal detail dibuka
    
    // animasi masuk
    setTimeout(() => {
        detailModalContent.style.transition = "transform .45s cubic-bezier(0.165,0.84,0.44,1)";
        detailModalContent.style.transform = "translateY(0)";
    }, 20);

    try {
        // Asumsi detail.php ada di direktori yang sama
        const res = await fetch(`detail.php?id=${id}`); 
        const html = await res.text();
        loadedDetailContent.innerHTML = html;

    } catch (err) {
        loadedDetailContent.innerHTML = `
            <div style="padding:20px;text-align:center;color:red;">
                <h2>Error Memuat</h2>
                <p>Gagal memuat detail. Pastikan file detail.php ada dan koneksi ke server berjalan.</p>
                <p>Detail Error: ${err.message}</p>
            </div>
        `;
    }
}


// ========================================
// DRAG START
// ========================================
function handleDragStart(e) {
    if (!detailModalOverlay.classList.contains("active")) return;

    isDragging = true;
    startY = e.touches ? e.touches[0].clientY : e.clientY;

    const transformStyle = detailModalContent.style.transform;
    const match = transformStyle.match(/translateY\(([-0-9.]+)px\)/);
    
    originalTranslateY = match ? parseFloat(match[1]) : 0;
    
    detailModalContent.style.transition = "none";
    e.preventDefault();
}


// ========================================
// DRAG MOVE  (ANTI DRAG UP)
// ========================================
function handleDragMove(e) {
    if (!isDragging) return;

    const currentY = e.touches ? e.touches[0].clientY : e.clientY;
    const deltaY = currentY - startY;

    let newY = originalTranslateY + deltaY;

    // Batasi agar modal tidak bisa naik (newY tidak boleh kurang dari 0)
    newY = Math.max(newY, 0);

    const maxDown = window.innerHeight + 300;
    newY = Math.min(newY, maxDown);

    detailModalContent.style.transform = `translateY(${newY}px)`;
    e.preventDefault();
}


// ========================================
// DRAG END
// ========================================
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

    // Jika di-drag ke bawah melebihi 40% tinggi viewport, tutup modal
    if (currentY > window.innerHeight * 0.40) {
        closeDetailModal();
        return;
    }

    // Kembali ke posisi default (0)
    detailModalContent.style.transform = "translateY(0)";
}


// ========================================
// EVENT LISTENERS DRAG MODAL
// ========================================
modalDragHandle.addEventListener("mousedown", handleDragStart);
modalDragHandle.addEventListener("touchstart", handleDragStart, { passive: false }); 

document.addEventListener("mousemove", handleDragMove, { passive: false });
document.addEventListener("touchmove", handleDragMove, { passive: false });

document.addEventListener("mouseup", handleDragEnd);
document.addEventListener("touchend", handleDragEnd);


// ========================================
// SCROLL TO TOP LOGIC
// ========================================

const scrollTopBtn = document.getElementById("scrollTopBtn");

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth' 
    });
}

// Menampilkan/menyembunyikan tombol saat menggulir
window.onscroll = function() {
    // Tampilkan tombol jika guliran melewati 200px
    if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
        scrollTopBtn.style.display = "block";
    } else {
        scrollTopBtn.style.display = "none";
    }
};

// ========================================
// ANIMASI CARD LOAD LOGIC 
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    // Menambahkan kelas 'loaded' untuk memicu fade-in body
    document.body.classList.add('loaded');
});

// ========================================
// END SCROLL TO TOP LOGIC
// ========================================
</script>

</body>
</html>