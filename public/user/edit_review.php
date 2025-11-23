<?php
session_start();
// PERBAIKAN PATH: Keluar dua tingkat (dari public/user/) ke tekno-aer/config/
include '../../config/db.php'; 

// =========================================================
// 1. Verifikasi Login User (LOGIKA TETAP)
// =========================================================
if (!isset($_SESSION['user_id'])) {
    // PERBAIKAN PATH: Redirect ke login.php di folder yang sama (public/user/)
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// =========================================================
// 2. Ambil dan Validasi ID Review & ID Barang (LOGIKA TETAP)
// =========================================================
if (!isset($_GET['id']) || !isset($_GET['barang_id']) || !is_numeric($_GET['id']) || !is_numeric($_GET['barang_id'])) {
    die("ID Review atau ID Barang tidak valid.");
}

$id_review = intval($_GET['id']);
$barang_id = intval($_GET['barang_id']);

// =========================================================
// 3. Ambil Data Review dan Verifikasi Kepemilikan (LOGIKA TETAP)
// =========================================================

// Gunakan Prepared Statement untuk keamanan
$stmt = $conn->prepare("
    SELECT r.komentar, r.rating, b.nama_barang 
    FROM review r
    JOIN barang b ON r.barang_id = b.barang_id
    WHERE r.id_review = ? AND r.user_id = ? AND r.barang_id = ?
");
$stmt->bind_param("iii", $id_review, $user_id, $barang_id);
$stmt->execute();
$result = $stmt->get_result();
$review_data = $result->fetch_assoc();
$stmt->close();

if (!$review_data) {
    die("Review tidak ditemukan atau Anda tidak memiliki izin untuk mengedit review ini.");
}

// =========================================================
// 4. Proses Update Review (POST Request) (LOGIKA TETAP)
// =========================================================
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_review'])) {
    
    $new_rating = intval($_POST['rating']);
    $new_komentar = trim($_POST['komentar']);

    if ($new_rating < 1 || $new_rating > 5 || empty($new_komentar)) {
        $message = "Rating harus 1-5 dan Komentar tidak boleh kosong.";
    } else {
        // Update data review di database
        $update_stmt = $conn->prepare("
            UPDATE review 
            SET rating = ?, komentar = ? 
            WHERE id_review = ? AND user_id = ?
        ");
        $update_stmt->bind_param("isii", $new_rating, $new_komentar, $id_review, $user_id);
        
        if ($update_stmt->execute()) {
            $message = "Review berhasil diperbarui!";
            // PERBAIKAN PATH: Redirect ke detail.php yang ada di public/ (keluar satu tingkat)
            header("Location: ../detail.php?id=" . $barang_id . "&msg=" . urlencode($message));
            exit;
        } else {
            $message = "Gagal memperbarui review: " . $conn->error;
        }
        $update_stmt->close();
    }
}
// Jika ada pesan error dari POST, pertahankan input user
if (!empty($message) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_data['komentar'] = $_POST['komentar'];
    $review_data['rating'] = $_POST['rating'];
}


// =========================================================
// 5. Tampilan Form (DESAIN ULANG)
// =========================================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Review: <?= htmlspecialchars($review_data['nama_barang']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Palet Warna: #008080 (Teal/Cyan) sebagai warna utama */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #e9eff1; /* Latar belakang sangat terang */
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
        }
        .container {
            width: 90%; 
            max-width: 600px; /* Ukuran container lebih besar */
            padding: 30px; 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        h2 { 
            color: #008080; 
            border-bottom: 2px solid #e0f0f0; 
            padding-bottom: 15px; 
            margin-top: 0; 
            font-weight: 600;
        }
        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        /* Bintang Rating */
        .rating-stars { 
            font-size: 30px; /* Bintang lebih besar */
            cursor: pointer; 
            margin-bottom: 10px;
        }
        .rating-stars span { 
            color: #ccc; 
            transition: color 0.2s; 
            padding: 0 2px;
        }
        .rating-stars span.active { 
            color: #FFC300; /* Warna Emas */
        }
        
        /* Form Element */
        textarea { 
            width: 100%; 
            height: 120px; 
            padding: 12px; 
            box-sizing: border-box; 
            resize: vertical; 
            border-radius: 8px; 
            border: 1px solid #ddd;
            transition: border-color 0.2s;
        }
        textarea:focus {
            border-color: #008080;
            outline: none;
        }
        
        /* Tombol */
        input[type="submit"], .btn-back {
            padding: 12px 25px; 
            background: #008080; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            transition: background 0.2s, box-shadow 0.2s;
        }
        input[type="submit"]:hover { 
            background: #006666; 
            box-shadow: 0 4px 10px rgba(0, 128, 128, 0.3);
        }
        .btn-back {
            background: #6c757d; /* Warna abu-abu yang lebih netral */
            margin-bottom: 25px;
        }
        .btn-back:hover {
            background: #5a6268;
        }

        /* Alert */
        .alert-error { 
            padding: 15px; 
            background: #fdf2f2; 
            color: #c0392b; 
            border: 1px solid #c0392b; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="container">
    <a href="../index.php?id=<?= $barang_id; ?>" class="btn-back">
        <i class="fas fa-arrow-left" style="margin-right: 8px;"></i> Kembali ke Detail Barang
    </a>

    <h2><i class="fas fa-edit" style="margin-right: 10px;"></i> Edit Ulasan untuk: <?= htmlspecialchars($review_data['nama_barang']); ?></h2>

    <?php if (!empty($message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="alert-error"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="edit_review.php?id=<?= $id_review; ?>&barang_id=<?= $barang_id; ?>">
        
        <label>Rating Anda:</label>
        <div class="rating-stars" id="rating-stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span data-value="<?= $i; ?>" class="<?= ($i <= $review_data['rating']) ? 'active' : ''; ?>">★</span>
            <?php endfor; ?>
        </div>
        <input type="hidden" name="rating" id="rating-input" value="<?= htmlspecialchars($review_data['rating']); ?>" required>

        <label for="komentar">Komentar Ulasan:</label>
        <textarea name="komentar" id="komentar" required placeholder="Tuliskan komentar Anda mengenai produk ini..."><?= htmlspecialchars($review_data['komentar']); ?></textarea>
        
        <br><br>
        <input type="submit" name="edit_review" value="Perbarui Ulasan">
    </form>
</div>

<script>
// JavaScript untuk interaksi bintang tetap dipertahankan
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('#rating-stars span');
    const ratingInput = document.getElementById('rating-input');

    // Fungsi untuk mengaktifkan bintang
    function setRating(rating) {
        stars.forEach(star => {
            if (parseInt(star.dataset.value) <= rating) {
                star.classList.add('active');
            } else {
                star.classList.remove('active');
            }
        });
        ratingInput.value = rating;
    }

    // Event listener untuk klik
    stars.forEach(star => {
        star.addEventListener('click', function() {
            setRating(parseInt(this.dataset.value));
        });
    });

    // Event listener untuk hover
    stars.forEach(star => {
        star.addEventListener('mouseover', function() {
            const hoverRating = parseInt(this.dataset.value);
            stars.forEach(s => {
                if (parseInt(s.dataset.value) <= hoverRating) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
            // Hapus pengembalian warna hover, biarkan JS di bawah yang menangani
            // s.style.color = (parseInt(s.dataset.value) <= parseInt(ratingInput.value) ? 'gold' : '#ccc');
        });
    });

    // Event listener saat mouse meninggalkan area rating
    document.getElementById('rating-stars').addEventListener('mouseout', function() {
        setRating(parseInt(ratingInput.value)); // Kembalikan ke nilai yang sudah dipilih
    });
});
</script>

</body>
</html>