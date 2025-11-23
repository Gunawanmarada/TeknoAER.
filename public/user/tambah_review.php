<?php
session_start();
// PERBAIKAN PATH: Keluar dua tingkat (dari public/user/) ke tekno-aer/config/
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Silakan login terlebih dahulu.'); window.location='login.php';</script>";
    exit;
}

$barang_id = intval($_GET['barang_id'] ?? 0);
$user_id = intval($_SESSION['user_id']);
$nama_barang = "Produk Ini"; // Default

// =========================================================
// 1. Verifikasi Kepemilikan Pembelian dan Ambil Nama Barang
// =========================================================

$stmt = $conn->prepare("
    SELECT b.nama_barang
    FROM pesanan_selesai ps
    JOIN barang b ON ps.barang_id = b.barang_id
    WHERE ps.user_id = ? AND ps.barang_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $user_id, $barang_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<script>alert('Anda belum menyelesaikan pembelian untuk barang ini atau barang tidak ditemukan.'); window.location='pesanan.php';</script>";
    exit;
}

$data = $result->fetch_assoc();
$nama_barang = htmlspecialchars($data['nama_barang']);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berikan Ulasan: <?= $nama_barang; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Gaya Konsisten */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #e9eff1; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
        }
        .container {
            width: 90%; 
            max-width: 550px; 
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
            font-size: 30px; 
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
        
        /* Tombol Aksi */
        .button-group {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .btn-submit, .btn-skip {
            padding: 12px 25px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-submit { 
            background: #008080; 
            color: white; 
        }
        .btn-submit:hover { 
            background: #006666; 
            box-shadow: 0 4px 10px rgba(0, 128, 128, 0.3);
        }
        .btn-skip {
            background: #f0f0f0; 
            color: #6c757d; 
            border: 1px solid #ddd;
        }
        .btn-skip:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-star" style="margin-right: 10px;"></i> Berikan Ulasan</h2>

    <p style="color:#555; border-left: 3px solid #008080; padding-left: 10px;">
        Kami ingin mendengar pengalaman Anda tentang produk: **<?= $nama_barang; ?>**
    </p>

    <form method="post" action="tambah_review_submit.php">
        <input type="hidden" name="barang_id" value="<?= $barang_id; ?>">
        
        <label>Rating Anda (Wajib):</label>
        <div class="rating-stars" id="rating-stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span data-value="<?= $i; ?>" class="active">★</span>
            <?php endfor; ?>
        </div>
        <input type="hidden" name="rating" id="rating-input" value="5" required>

        <label for="komentar">Komentar Ulasan (Opsional):</label>
        <textarea name="komentar" id="komentar" placeholder="Bagaimana pengalaman Anda menggunakan produk ini?"></textarea>
        
        <div class="button-group">
            <button type="submit" class="btn-submit">
                <i class="fas fa-check" style="margin-right: 8px;"></i> Kirim Ulasan
            </button>
            <a href="pesanan.php" class="btn-skip">
                <i class="fas fa-forward" style="margin-right: 8px;"></i> Lewati
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('#rating-stars span');
    const ratingInput = document.getElementById('rating-input');

    // Fungsi untuk mengatur tampilan bintang dan nilai input
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

    // Set default rating to 5 on load
    setRating(5); 

    // Event listener untuk klik
    stars.forEach(star => {
        star.addEventListener('click', function() {
            setRating(parseInt(this.dataset.value));
        });
    });

    // Event listener untuk hover (memberi feedback visual sebelum klik)
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
        });
    });

    // Event listener saat mouse meninggalkan area rating (kembali ke nilai yang dipilih)
    document.getElementById('rating-stars').addEventListener('mouseout', function() {
        setRating(parseInt(ratingInput.value)); 
    });
});
</script>