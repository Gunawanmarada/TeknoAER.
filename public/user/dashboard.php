<?php
session_start();
// PATH FIX: Go up two levels to reach config/
include '../../config/db.php'; 

if (!isset($_SESSION['user_id'])) {
    // Redirect to login.php in the same folder (user/)
    echo "<script>alert('Please login first!'); window.location='login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch complete user data (using Prepared Statement)
$stmt_user = $conn->prepare("SELECT nama_lengkap, username, email, foto_profil FROM user WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user_data) {
    echo "<script>alert('User data not found.'); window.location='logout.php';</script>";
    exit;
}

// Prepare data to display in the form
$nama_lengkap = $user_data['nama_lengkap'];
$username_user = $user_data['username'];
$email_user = $user_data['email'];
// Get role from session, default 'Customer'
$role_user = $_SESSION['role'] ?? 'Customer'; 

// Determine the current profile photo path
$current_photo = $user_data['foto_profil'] ?? 'default.png';
// Path must go up two levels (../../) to reach assets/uploads/profiles/
$photo_path = '../../assets/uploads/profiles/' . htmlspecialchars($current_photo); 

// We assume detect_read_key and notification functions are not needed here, 
// but we prepare the notif_count variable for the header
$notif_count = 0; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - TeknoAER</title>
    <link rel="icon" type="image/jpeg" href="../../assets/uploads/logo/logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ==============================================
           BASE CSS (UNCHANGED)
           ============================================== */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #ebebeb; 
            margin: 0; 
            padding: 0; 
            overflow-x: hidden; 
            opacity: 0; /* Starting point for fade-in */
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); /* Slight shadow */
        } 
        .header .logo { font-size: 24px; font-weight: bold; color: #008080; }
        .main-layout { display: flex; min-height: 100vh; } 
        
        /* SIDEBAR (Transition added) */
        .sidebar { 
            width: 70px; 
            background: #008080; 
            color: white; 
            padding: 10px 0; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            flex-shrink: 0; 
            transition: width 0.3s ease-in-out; 
        }
        .sidebar-item { 
            padding: 15px 0; 
            cursor: pointer; 
            width: 100%; 
            text-align: center; 
            transition: background 0.2s, transform 0.2s; /* Added transform */
            position: relative;
        }
        .sidebar-item:hover, .sidebar-item.active { 
            background: #006666; 
            transform: scale(1.05); /* Zoom effect on hover */
        }
        .sidebar-item i { font-size: 24px; }

        .content { 
            flex-grow: 1; 
            padding: 0; 
            overflow-y: auto; 
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px; 
        }

        /* Notifications (Animation added) */
        .notif-container {
            position: relative;
            color: #333; 
            font-size: 20px;
            transition: color 0.2s, transform 0.3s;
        }
        .notif-container:hover {
            color: #008080;
            transform: rotate(15deg); /* Tilt/shake effect on hover */
        }
        .notif-badge { 
             position: absolute; top: -5px; right: -10px; 
             background-color: #dc3545; color: white; 
             border-radius: 50%; padding: 2px 6px; font-size: 10px; 
             font-weight: bold; 
        }
        
        /* User Info & Profile Photo (Transition added) */
        .user-info { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            flex-shrink: 0; 
            transition: opacity 0.3s;
        }
        .user-info:hover .profile-photo {
            transform: scale(1.1);
        }
        .profile-photo {
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid #008080; 
            transition: transform 0.3s ease; /* Transition for zoom */
        }
        .user-role {
            font-size: 0.85em; 
            color: #666;
        }
        @media (max-width: 768px) {
            .sidebar { display: none; } 
            .main-layout { display: block; }
        }

        /* CUSTOM CSS FOR THIS PAGE */
        /* Profile Box (Added Fade-In Animation and Shadow) */
        .profile-container { 
            width: 90%; 
            max-width: 700px; 
            margin: 40px auto; 
            background: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15); /* Deeper shadow */
            opacity: 0;
            transform: translateY(20px);
            animation: slideIn 0.8s forwards 0.3s; /* Slide and fade animation after 0.3s */
        }
        h2 { 
            color: #008080; 
            border-bottom: 2px solid #eee; 
            padding-bottom: 15px; 
            margin-bottom: 25px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-img-lg { 
            width: 120px; 
            height: 120px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 5px solid #008080; 
            margin-bottom: 15px; 
            transition: transform 0.4s ease; /* Transition for zoom/rotation */
        }
        .profile-img-lg:hover {
            transform: scale(1.05) rotate(2deg);
        }

        label { 
            display: block; 
            margin-top: 15px; 
            font-weight: 600; 
            color: #333;
            font-size: 0.95em;
        }
        input[type="text"], input[type="email"] { 
            width: 95%; 
            padding: 12px; 
            margin-top: 8px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            box-sizing: border-box; 
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input[type="text"]:focus, input[type="email"]:focus {
            border-color: #008080;
            box-shadow: 0 0 8px rgba(0, 128, 128, 0.4); /* More prominent focus effect */
            outline: none;
        }
        input[type="file"] { 
            width: 95%; 
            padding: 10px; 
            margin-top: 8px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            box-sizing: border-box; 
            background: #f9f9f9;
        }
        button[type="submit"] { 
            padding: 12px 25px; 
            background: #28a745; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            margin-top: 30px; 
            font-weight: bold;
            transition: background 0.2s, transform 0.2s; /* Button transition */
        }
        button[type="submit"]:hover {
            background: #1e7e34;
            transform: translateY(-2px); /* Lift up effect */
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .file-upload-section {
            text-align: center; 
            margin-bottom: 30px; 
            padding: 20px; 
            border: 2px dashed #008080;
            border-radius: 10px;
            background: #f0fffe; 
            transition: box-shadow 0.3s;
        }
        .file-upload-section:hover {
            box-shadow: 0 0 10px rgba(0, 128, 128, 0.3);
        }

        /* ==============================================
           NEW ANIMATION KEYFRAMES
           ============================================== */
        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="loaded"> <div class="main-layout">
    <div class="sidebar">
        <div class="top-icon" style="padding: 10px 0;"><a href="../../index.php" title="Home"><i class="fas fa-leaf"></i></a></div> 
        <a href="../index.php" class="sidebar-item" title="Catalog"><i class="fas fa-store"></i></a>
        <a href="../keranjang.php" class="sidebar-item" title="Cart"><i class="fas fa-shopping-cart"></i></a>
        <a href="pesanan.php" class="sidebar-item" title="Orders"><i class="fas fa-box"></i></a>
        <a href="logout.php" class="sidebar-item" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>

    <div class="content">
        <div class="header" id="mainHeader">
            <div style="display:flex; align-items:center; gap:10px;">
                <a href="../../index.php" class="logo">TeknoAER</a>
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
                            <span style="font-weight:600; display:block;"><?= htmlspecialchars($nama_lengkap); ?></span>
                           <span class="user-role">
                                <?= htmlspecialchars($role_user); ?>
                            </span>
                        </div>
                        <img src="<?= $photo_path; ?>" alt="Profile Photo" class="profile-photo">
                    </div>
                </a>
            </div>
        </div>
        
        <div class="profile-container">
            <h2><i class="fas fa-user-cog"></i> Your Profile Settings</h2>
            
            <form action="update_profil.php" method="POST" enctype="multipart/form-data">
                
                <div class="file-upload-section">
                    <p>Current Profile Photo:</p>
                    <img src="<?= $photo_path; ?>" alt="Profile Photo" class="profile-img-lg"><br>
                    
                    <label for="profile_pic" style="font-weight: normal; margin-top: 10px;">Select New Photo (.jpg, .png, Max 2MB):</label>
                    <input type="file" name="profile_pic" id="profile_pic" accept="image/jpeg, image/png">
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;">
                
                <input type="hidden" name="col_nama" value="nama_lengkap">
                <input type="hidden" name="col_username" value="username">
                <input type="hidden" name="col_email" value="email">
                
                <label for="nama">Full Name:</label>
                <input type="text" id="nama" name="nama" value="<?= htmlspecialchars($nama_lengkap); ?>" required>
                
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($username_user); ?>" required>
                
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email_user); ?>" required>

                <br>
                <button type="submit" name="simpan"><i class="fas fa-save"></i> Save Profile Changes</button>
            </form>
        </div>
        
    </div> 
</div> 

<script>
    // Add 'loaded' class after the DOM is fully loaded to start the body transition
    document.addEventListener('DOMContentLoaded', function() {
        document.body.classList.add('loaded');
    });
</script>

</body>
</html>