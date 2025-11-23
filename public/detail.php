<?php
session_start();
// PATH FIX: Go up one level (from public/) to tekno-aer/config/
include '../config/db.php';

// Get item ID
if (!isset($_GET['id'])) {
    echo "<div style='text-align: center; padding: 30px; color: #dc3545;'>Item not found.</div>";
    exit;
}

$barang_id = intval($_GET['id']);

// Get item data
$stmt_barang = $conn->prepare("SELECT * FROM barang WHERE barang_id = ?");
$stmt_barang->bind_param("i", $barang_id);
$stmt_barang->execute();
$barang = $stmt_barang->get_result();

if ($barang->num_rows == 0) {
    echo "<div style='text-align: center; padding: 30px; color: #dc3545;'>Item not found.</div>";
    exit;
}

$data = $barang->fetch_assoc();
$stmt_barang->close();


// =========================
// 1. Check if user is allowed to review & Check if user has reviewed
//    **FIXED**: Menggunakan COUNT(*) agar tidak bergantung pada nama kolom 'id'.
// =========================

$user_id = $_SESSION['user_id'] ?? null;
$bisa_review = false;
$existing_review = null; 

if ($user_id) {
    // A. Check if user has purchased (Required)
    // MENGGUNAKAN COUNT(*) untuk efisiensi dan MENGHINDARI error 'Unknown column'
    $sql_cek_beli = "SELECT COUNT(*) AS total FROM pesanan_pelanggan WHERE user_id = ? AND barang_id = ?";
    $stmt_beli = $conn->prepare($sql_cek_beli);
    
    if ($stmt_beli) {
        $stmt_beli->bind_param("ii", $user_id, $barang_id);
        $stmt_beli->execute();
        $cek_beli_result = $stmt_beli->get_result();
        
        // Ambil hasil COUNT
        $row = $cek_beli_result->fetch_assoc();
        $stmt_beli->close();

        // If at least one purchase record is found (total > 0)
        if ($row['total'] > 0) { 
            $bisa_review = true; 
        }
    } else {
        // Ini adalah fallback jika prepare gagal, bukan error 'Unknown column'
        // echo "Error prepare: " . $conn->error;
    }
    
    if ($bisa_review) {
        // B. Check if user has already written a review
        $stmt_review = $conn->prepare("SELECT * FROM review WHERE user_id = ? AND barang_id = ? LIMIT 1");
        $stmt_review->bind_param("ii", $user_id, $barang_id);
        $stmt_review->execute();
        $cek_review = $stmt_review->get_result();
        
        if ($cek_review->num_rows > 0) {
            $existing_review = $cek_review->fetch_assoc();
        }
        $stmt_review->close();
    }
}


// =========================
// 2. Get user name column (auto detect)
// =========================

$colRes = $conn->query("SHOW COLUMNS FROM user");
$userCols = [];
while ($c = $colRes->fetch_assoc()) $userCols[] = $c['Field'];

$col_nama_user = 'username'; 
$col_foto_user = 'foto_profil'; 

foreach (['nama', 'nama_lengkap', 'fullname', 'user_name'] as $opt) {
    if (in_array($opt, $userCols)) {
        $col_nama_user = $opt;
        break;
    }
}


// =========================
// 3. Fetch reviews
// =========================

$sql_review = "
    SELECT 
        r.*, 
        u.$col_nama_user AS nama_user, 
        u.$col_foto_user AS foto_profil,
        a.nama_lengkap AS nama_admin, 
        a.role AS role_admin       
    FROM review r
    LEFT JOIN user u ON r.user_id = u.user_id
    LEFT JOIN admin a ON r.admin_id = a.admin_id 
    WHERE r.barang_id= ?
    ORDER BY r.id_review DESC
";

$stmt_review_fetch = $conn->prepare($sql_review);
$stmt_review_fetch->bind_param("i", $barang_id);
$stmt_review_fetch->execute();
$review = $stmt_review_fetch->get_result();
$stmt_review_fetch->close();


// =========================
// 4. Calculate Average Rating
// =========================
$sql_avg_rating = "SELECT AVG(rating) as avg_rating, COUNT(id_review) as total_reviews FROM review WHERE barang_id = ?";
$stmt_avg = $conn->prepare($sql_avg_rating);
$stmt_avg->bind_param("i", $barang_id);
$stmt_avg->execute();
$result_avg = $stmt_avg->get_result();
$rating_data = $result_avg->fetch_assoc();
$stmt_avg->close();

$avg_rating = round($rating_data['avg_rating'], 1);
$total_reviews = $rating_data['total_reviews'];

?>

<style>
    /* VARS & Base Styling */
    :root {
        --primary-color: #008080; /* Teal/Cyan */
        --secondary-color: #006666;
        --accent-color: #FFC300; /* Gold for stars */
        --success-color: #28a745;
        --danger-color: #dc3545;
        --soft-bg: #f9f9f9;
        --border-color: #eee;
        --shadow-light: 0 2px 10px rgba(0, 0, 0, 0.05);
        --shadow-medium: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    /* Overall Layout Structure */
    .detail-content-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding-bottom: 15px; 
        border-bottom: 3px solid var(--primary-color); 
        margin-bottom: 25px;
    }
    .detail-content-header h2 {
        margin: 0; 
        color: var(--primary-color); 
        font-weight: 700;
        font-size: 1.8em;
    }

    /* Close Button */
    .close-btn { 
        background: var(--soft-bg); 
        border: 1px solid var(--border-color); 
        font-size: 16px; 
        cursor: pointer; 
        color: #555; 
        padding: 8px 12px; 
        border-radius: 8px; 
        transition: all 0.2s;
    }
    .close-btn:hover {
        background: #ddd;
        color: #333;
    }

    /* Main Product Grid (Two Columns) */
    .product-main-container {
        display: grid;
        grid-template-columns: 1fr 350px; /* Detail on Left, Buy Action on Right (350px fixed) */
        gap: 30px;
    }
    @media (max-width: 992px) {
        .product-main-container {
            grid-template-columns: 1fr; /* Single column on mobile/tablet */
        }
    }

    /* Left Column: Product Details */
    .product-detail-column {
        padding: 0;
    }
    .product-title {
        font-size: 2.5em;
        font-weight: 800;
        color: #333;
        margin-top: 0;
        margin-bottom: 10px;
        line-height: 1.2;
    }
    
    .rating-summary {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    .rating-summary .avg-score {
        font-size: 1.8em;
        font-weight: bold;
        color: var(--primary-color);
    }
    .rating-summary .total-reviews {
        font-size: 0.9em;
        color: #777;
    }

    .product-image {
        width: 100%; 
        max-width: 500px;
        aspect-ratio: 4/3; 
        object-fit: cover; 
        border-radius: 12px; 
        box-shadow: var(--shadow-medium);
        border: 1px solid var(--border-color);
        margin-bottom: 30px;
    }
    
    .product-info-grid {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 8px 15px;
        font-size: 1em;
        padding: 15px;
        background: #f0f8ff; /* Light blue background */
        border-radius: 8px;
        border: 1px solid #d4eaf7;
    }
    .product-info-grid b {
        color: var(--secondary-color);
    }
    
    /* Right Column: Buy Action & Price */
    .product-action-column {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    
    /* Buy Form (Floating Card) */
    .buy-form-section { 
        padding: 25px;
        background: #ffffff; 
        border-radius: 12px;
        border: 1px solid #ddd;
        box-shadow: var(--shadow-medium); /* Elevated Look */
    }
    
    .price-card {
        text-align: center;
        margin-bottom: 20px;
        padding: 15px;
        background: var(--soft-bg);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    .price-card label {
        display: block;
        font-size: 0.9em;
        color: #555;
        margin-bottom: 5px;
    }
    .price-display {
        font-size: 38px;
        font-weight: 700;
        color: var(--danger-color); /* Red/Bold for price */
        letter-spacing: -0.5px;
    }
    .form-group-flex {
        display: flex; 
        gap: 15px; 
        align-items: center; 
        width: 100%;
        margin-bottom: 15px;
    }
    .form-group-flex label {
        font-weight: bold;
        color: var(--secondary-color);
    }
    .form-group-flex input[type="number"] {
        width: 70px; 
        padding: 10px; 
        border: 2px solid var(--primary-color); 
        border-radius: 6px;
        text-align: center;
        font-weight: 600;
        flex-shrink: 0;
    }
    
    /* Buttons */
    .btn-action { 
        padding: 12px 20px; 
        border-radius: 8px; 
        text-decoration: none; 
        border: none; 
        cursor: pointer; 
        display: flex; 
        justify-content: center;
        align-items: center;
        transition: background 0.2s, transform 0.1s; 
        font-weight: 600;
        gap: 8px;
        width: 100%; /* Full width */
    }
    .btn-checkout { 
        background: var(--primary-color); 
        color: white; 
        font-size: 1.1em;
        box-shadow: 0 4px 10px rgba(0, 128, 128, 0.4);
    }
    .btn-checkout:hover {
        background: var(--secondary-color);
        transform: translateY(-1px);
    }
    .btn-cart-secondary {
        background: var(--soft-bg); 
        color: var(--primary-color); 
        border: 1px solid var(--primary-color);
        margin-top: 10px;
    }
    .btn-cart-secondary:hover {
        background: #e0e0e0;
    }

    /* Product Description */
    .description-box {
        background: #ffffff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: var(--shadow-light);
    }
    .description-box h3 {
        color: var(--secondary-color);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 8px;
        margin-top: 0;
        font-size: 1.4em;
    }
    .description-box p {
        white-space: pre-wrap;
        line-height: 1.6;
        color: #333;
    }


    /* Review Section */
    .review-header {
        color: var(--primary-color); 
        border-bottom: 2px solid var(--primary-color); 
        padding-bottom: 10px; 
        margin-top: 30px;
        font-size: 1.8em;
        font-weight: 700;
    }
    .review-button-section {
        margin: 20px 0;
        padding: 15px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .btn-edit, .btn-write { 
        padding: 8px 15px;
        border-radius: 6px;
        font-size: 0.95em;
    }
    .btn-edit { 
        background: var(--accent-color); 
        color: #333; 
    }
    .btn-write { 
        background: var(--success-color); 
        color: white; 
    }
    .info-review-only {
        color: #777;
        font-size: 0.9em;
        border-left: 3px solid var(--primary-color);
        padding-left: 10px;
    }
    
    /* Review Box */
    .review-box { 
        background: #ffffff; 
        padding: 20px; 
        border-radius: 10px; 
        margin-bottom: 15px;
        border: 1px solid #ddd;
        box-shadow: var(--shadow-light);
    }
    .reviewer-info { 
        display: flex; 
        align-items: center; 
        margin-bottom: 10px; 
        padding-bottom: 8px;
    }
    .reviewer-photo { 
        width: 40px; 
        height: 40px; 
        border-radius: 50%; 
        object-fit: cover; 
        margin-right: 12px;
        border: 2px solid var(--primary-color);
    }
    .review-rating .star { 
        color: var(--accent-color); 
        font-size: 20px; 
        margin-right: 2px;
    }
    .review-comment {
        margin-top: 15px;
        color: #333;
    }
    .review-date { 
        font-size: 0.85em; 
        color: #888; 
        margin-left: 15px;
    }
    .admin-reply-box { 
        border-left: 4px solid var(--secondary-color); 
        background: #e6f5f5; 
    }
    /* FIX CSS for inactive stars */
    .star-rating .star-inactive {
        color: #ccc !important; 
    }
</style>

<div class="detail-content-header">
    <h2>Product Details</h2>
    <button class="close-btn" onclick="closeDetailModal()"> 
        <i class="fas fa-times"></i> Close
    </button>
</div>

<div class="product-detail-section">
    
    <div class="product-main-container">
        
        <div class="product-detail-column">
            
            <h1 class="product-title"><?= htmlspecialchars($data['nama_barang']); ?></h1>

            <div class="rating-summary">
                <?php if ($total_reviews > 0): ?>
                    <span class="avg-score"><?= number_format($avg_rating, 1); ?></span>
                    <div class="star-rating">
                        <?php for ($i=1; $i<=5; $i++): ?>
                            <?= ($i <= round($avg_rating)) ? "<span class='star'>★</span>" : "<span class='star star-inactive'>★</span>" ?>
                        <?php endfor; ?>
                    </div>
                    <span class="total-reviews">(<?= $total_reviews; ?> Reviews)</span>
                <?php else: ?>
                    <span class="total-reviews">No reviews yet.</span>
                <?php endif; ?>
            </div>

            <?php 
            $gambar_path = '../private/assets/uploads/' . $data['gambar'];
            // Pastikan menggunakan jalur absolut untuk pengecekan file
            if (!empty($data['gambar']) && file_exists(__DIR__ . '/../private/assets/uploads/' . $data['gambar'])): ?> 
                <img src="<?= $gambar_path; ?>" alt="<?= htmlspecialchars($data['nama_barang']); ?>" class="product-image">
            <?php else: ?>
                <div class="product-image" style="background:var(--soft-bg); display:flex; align-items:center; justify-content:center; color:#777; font-size: 1.5em;">
                    <i class="fas fa-box-open"></i> Image not available
                </div>
            <?php endif; ?>

            <div class="description-box" style="margin-bottom: 30px;">
                <h3><i class="fas fa-info-circle"></i> Product Description</h3>
                <p><?= htmlspecialchars($data['keterangan']); ?></p>
            </div>
            
            <div class="product-info-grid">
                <b><i class="fas fa-tags"></i> Category</b><span>: <?= htmlspecialchars($data['kategori']); ?></span>
                <b><i class="fas fa-check-circle"></i> Condition</b><span>: <?= htmlspecialchars($data['kondisi']); ?></span>
            </div>
            
        </div>
        <div class="product-action-column">

            <div class="buy-form-section">
                
                <div class="price-card">
                    <label>Product Price</label>
                    <div class="price-display">Rp <?= number_format($data['harga'],0,',','.'); ?></div>
                </div>

                <form action="beli_sekarang.php" method="GET" class="form-group-flex">
                    <input type="hidden" name="barang_id" value="<?= $barang_id; ?>">
                    
                    <label for="jumlah_beli" class="flex-shrink-0">Units:</label>
                    <input 
                        type="number" 
                        id="jumlah_beli" 
                        name="jumlah" 
                        min="1" 
                        value="1" 
                        required
                        class="flex-shrink-0"
                    >

                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <button type="button" class="btn-action btn-checkout" onclick="showLoginPopup()">
                            <i class="fas fa-sign-in-alt"></i> Log in to Checkout
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn-action btn-checkout">
                            <i class="fas fa-money-check-alt"></i> Checkout Now
                        </button>
                    <?php endif; ?>
                </form>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <form action="beli.php" method="POST" style="width: 100%;" onsubmit="
                        // Mengambil nilai kuantitas dari input checkout saat form cart disubmit
                        const jumlahBeli = document.getElementById('jumlah_beli').value;
                        const inputJumlah = document.createElement('input');
                        inputJumlah.type = 'hidden';
                        inputJumlah.name = 'jumlah';
                        inputJumlah.value = jumlahBeli;
                        this.appendChild(inputJumlah);
                        return true; 
                    ">
                        <input type="hidden" name="barang_id" value="<?= $barang_id; ?>">
                        <button type="submit" class="btn-action btn-cart-secondary">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </form>
                <?php else: ?>
                      <div style="width: 100%;">
                          <button type="button" class="btn-action btn-cart-secondary" onclick="showLoginPopup()">
                            <i class="fas fa-cart-plus"></i> Log in to Add to Cart
                          </button>
                      </div>
                <?php endif; ?>
                
            </div>
            
        </div>
        </div>
    <hr style="margin: 40px 0;">

    <h2 class="review-header">
        <i class="fas fa-star" style="color: var(--accent-color);"></i> Customer Reviews (<?= $total_reviews; ?>)
    </h2>

    <div class="review-button-section">
    <?php 
    if ($bisa_review): 
        if ($existing_review):
            ?>
            <div style="font-weight: bold; color: var(--success-color);"><i class="fas fa-check-circle"></i> You have already submitted a review.</div>
            <a href="user/edit_review.php?id=<?= $existing_review['id_review']; ?>&barang_id=<?= $barang_id; ?>" class="btn-action btn-edit">
                <i class="fas fa-pencil-alt"></i> Edit Your Review
            </a>
            <?php
        else:
            ?>
            <span style="font-weight: 500; color: #555;">Write your review after purchasing this product.</span>
            <a href="user/tambah_review.php?barang_id=<?= $barang_id; ?>" class="btn-action btn-write">
                <i class="fas fa-pen"></i> Write New Review
            </a>
            <?php
        endif;
    else: 
        ?>
        <div class="info-review-only">
             <i class="fas fa-info-circle"></i> You must **log in** and **complete the purchase** of this item to write a review.
        </div>
        <?php
    endif;
    ?>
    </div>

    <div class="review-box-container">
    <?php if ($review->num_rows == 0): ?>
        <p style="text-align: center; color: #777; padding: 20px; background: #f0f0f0; border-radius: 8px;">
            <i class="fas fa-frown"></i> No reviews have been submitted for this product yet.
        </p>
    <?php else: ?>
        <?php while ($r = $review->fetch_assoc()): 
            $reviewer_photo_file = $r['foto_profil'] ?? 'default.png';
            // Pastikan path ini benar relatif terhadap detail.php
            $reviewer_photo_path = '../assets/uploads/profiles/' . htmlspecialchars($reviewer_photo_file); 
            
            // Pengecekan keberadaan file
            if (!file_exists(__DIR__ . '/' . $reviewer_photo_path) || empty($r['foto_profil'])) {
                $reviewer_photo_path = '../assets/uploads/profiles/default.jpg'; 
            }
        ?>
            <div class="review-box">
                
                <div class="reviewer-info">
                    <img src="<?= $reviewer_photo_path; ?>" alt="Reviewer Photo" class="reviewer-photo">
                    <span class="reviewer-name"><?= htmlspecialchars($r['nama_user']); ?></span> 
                    <span class="review-date">Reviewed on <?= date('d M Y', strtotime($r['tanggal'])); ?></span>
                    <span class="review-actions" style="margin-left: auto;">
                        <?php if ($user_id && $r['user_id'] == $user_id): ?>
                            <a href="user/edit_review.php?id=<?= $r['id_review']; ?>&barang_id=<?= $barang_id; ?>" 
                                class="action-link edit-link" style="color: var(--secondary-color);">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            
                            <a href="user/hapus_review.php?id=<?= $r['id_review']; ?>&barang_id=<?= $barang_id; ?>" 
                                class="action-link delete-link" style="color: var(--danger-color);"
                                onclick="return confirm('Are you sure you want to delete this review? This action cannot be undone.');">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="review-rating">
                    <?php for ($i=1; $i<=5; $i++): ?>
                        <?= ($i <= $r['rating']) ? "<span class='star'>★</span>" : "<span class='star star-inactive'>★</span>" ?>
                    <?php endfor; ?>
                </div>

                <p class="review-comment"><?= nl2br(htmlspecialchars($r['komentar'])); ?></p>
                
                <?php if (!empty($r['balasan_admin'])): ?>
                    <div class="admin-reply-box">
                        <p style="margin: 0; font-weight: bold; color: var(--secondary-color);">Reply from TeknoAER Team:</p>
                        <p style="margin-top: 5px;"><?= nl2br(htmlspecialchars($r['balasan_admin'])); ?></p>
                        <div class="admin-info">
                            — Replied by **<?= htmlspecialchars($r['nama_admin'] ?? 'Admin'); ?>** on <?= date('d/m/Y H:i', strtotime($r['waktu_balas'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endwhile; ?>
    <?php endif; ?>
    </div>
</div>

<script>
    // FUNGSI JAVASCRIPT UNTUK KLIK (TIDAK ADA PERUBAHAN LOGIKA DARI SINI)
    
    // Fungsi dummy agar tombol "Close" berfungsi
    function closeDetailModal() {
        if (window.parent && typeof window.parent.closeModal === 'function') {
             window.parent.closeModal();
        } else {
             console.warn("Modal closing function not found in parent.");
        }
    }
    
    function showLoginPopup() {
        alert("Please log in first to perform this action.");
        // window.location.href = 'login.php'; 
    }
</script>