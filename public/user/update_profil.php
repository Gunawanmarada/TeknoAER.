<?php
session_start(); 
// PERBAIKAN PATH: Keluar dua tingkat (dari public/user/) ke tekno-aer/config/
include '../../config/db.php'; 

if (!isset($_SESSION['user_id']) || !isset($_POST['simpan'])) {
    // Jika tidak login atau akses langsung tanpa submit form
    // Redirect ke dashboard.php (di folder yang sama)
    echo "<script>alert('Akses tidak sah.'); window.location='dashboard.php';</script>";
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$update_photo_column = false;
$new_file_name = null;
$error_message = '';
// PERBAIKAN PATH: Keluar dua tingkat (../..) ke folder tekno-aer/, lalu masuk ke assets/uploads/profiles/
$target_dir = "../../assets/uploads/profiles/"; 

// =========================================================
// 1. Ambil dan Bersihkan Data Teks
// =========================================================
$nama = trim($_POST['nama']);
$username = trim($_POST['username']);
$email = trim($_POST['email']);


if ($nama == "" || $username == "" || $email == "") {
    // Redirect ke dashboard.php (di folder yang sama)
    echo "<script>alert('Semua kolom wajib diisi!'); window.location='dashboard.php';</script>";
    exit;
}

// 2. Validasi Duplikasi Username/Email (Menggunakan nama kolom sebenarnya)
$stmt_check = $conn->prepare("SELECT user_id FROM user WHERE (username=? OR email=?) AND user_id != ?");
$stmt_check->bind_param("ssi", $username, $email, $user_id);
$stmt_check->execute();
$check = $stmt_check->get_result();
$stmt_check->close();

if ($check->num_rows > 0) {
    // Redirect ke dashboard.php (di folder yang sama)
    echo "<script>alert('Username atau Email sudah digunakan oleh pengguna lain!'); window.location='dashboard.php';</script>";
    exit;
}

// =========================================================
// 3. PROSES UPLOAD FOTO PROFIL (Jika ada file yang diupload)
// =========================================================
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_pic'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $allowed_ext = array('jpg', 'jpeg', 'png');
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file_ext, $allowed_ext)) {
        $error_message = 'Hanya format JPG, JPEG, atau PNG yang diizinkan.';
    } elseif ($file_size > $max_size) {
        $error_message = 'Ukuran file maksimum 2MB.';
    } else {
        // Buat Nama File Unik: user_ID_timestamp.ext
        $new_file_name = "user_" . $user_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            $update_photo_column = true;
            
            // Ambil dan Hapus Foto Lama (Opsional, untuk menghemat ruang server)
            $q_old = $conn->query("SELECT foto_profil FROM user WHERE user_id='$user_id'");
            $old_data = $q_old->fetch_assoc();
            $old_file_name = $old_data['foto_profil'];
            
            // Perhatian: Pastikan Anda menjalankan ALTER TABLE untuk menambahkan kolom 'foto_profil'
            if ($old_file_name && $old_file_name !== 'default.jpg' && file_exists($target_dir . $old_file_name)) {
                // Hapus foto lama di path baru
                unlink($target_dir . $old_file_name);
            }
        } else {
            $error_message = 'Gagal memindahkan file ke server. Cek izin folder.';
        }
    }

    // Jika ada error saat upload, hentikan proses dan berikan pesan
    if ($error_message) {
        // Redirect ke dashboard.php (di folder yang sama)
        echo "<script>alert('Gagal Upload Foto: {$error_message}'); window.location='dashboard.php';</script>";
        exit;
    }
}

// =========================================================
// 4. UPDATE DATABASE (Data Teks + Foto jika ada)
// =========================================================

// Query dasar hanya untuk data teks
$sql = "UPDATE user SET nama_lengkap=?, username=?, email=?";
$types = "sss"; // Tipe data untuk 3 parameter string
$params = [$nama, $username, $email];

if ($update_photo_column) {
    // Tambahkan update foto jika file baru berhasil diupload
    $sql .= ", foto_profil=?";
    $types .= "s";
    $params[] = $new_file_name;
}

// Tambahkan klausa WHERE
$sql .= " WHERE user_id=?";
$types .= "i"; // Tipe data integer untuk user_id
$params[] = $user_id;

$stmt = $conn->prepare($sql);

// Memanggil bind_param secara dinamis
// Penggunaan '...' (splat operator) memerlukan PHP versi 5.6 ke atas
$stmt->bind_param($types, ...$params); 

if ($stmt->execute()) {
    // Update data sesi jika nama berubah
    $_SESSION['nama'] = $nama; 
    // Redirect ke dashboard.php (di folder yang sama)
    echo "<script>alert('Profil berhasil diperbarui!'); window.location='dashboard.php';</script>";
} else {
    // Jika update DB gagal, dan ada file baru yang diupload, hapus file baru tersebut
    if ($update_photo_column && file_exists($target_file)) {
         unlink($target_file);
    }
    // Redirect ke dashboard.php (di folder yang sama)
    echo "<script>alert('Gagal memperbarui profil: " . addslashes($conn->error) . "'); window.location='dashboard.php';</script>";
}
$stmt->close();

?>