<?php
session_start();
// FIX: The path for db.php is now 2 levels up to config/
include '../../config/db.php';

// Check if the user is already logged in; if so, redirect to the dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$username_value = ''; // Variable to save the username input so it's not lost on error

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $username_value = $username; // Save the entered username value

    // Using Prepared Statement (Good Practice)
    $stmt = $conn->prepare("SELECT user_id, nama_lengkap, password FROM user WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // !!! SECURITY WARNING: Still using plain-text comparison (INSECURE)
        // Logic remains the same, only a warning is added.
        if ($password === $row['password']) { 
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['nama'] = $row['nama_lengkap'];
            $_SESSION['role'] = 'user'; // Set user role

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "❌ Incorrect username or password!";
        }
    } else {
        $error = "❌ Incorrect username or password!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Login - TeknoAER</title>
<link rel="icon" type="image/jpeg" href="../../assets/uploads/logo/logo.jpg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
/* =========================================================
    ROOT & GLOBAL STYLE (Consistent Teal Color)
========================================================= */
:root {
    --color-primary: #008080; /* Teal (Main catalog color) */
    --color-secondary: #004d40;
    --color-light: #ffffff;
    --color-error: #dc3545; /* Red for error */
    --font-poppins: 'Poppins', sans-serif;
}

body {
    margin: 0;
    padding: 0;
    height: 100vh;
    display: flex;
    font-family: var(--font-poppins);
    /* Use a simple gradient if a background image is not available */
    background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); 
    overflow: hidden;
    justify-content: center;
    align-items: center;
}

/* =========================================================
    LOGIN CARD (Single Column)
========================================================= */
.login-card {
    width: 90%;
    max-width: 450px;
    background: var(--color-light);
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    animation: fadeIn 0.8s ease-out;
}

.login-card h2 {
    color: var(--color-primary);
    text-align: center;
    font-size: 32px;
    margin-bottom: 30px;
    font-weight: 700;
}

/* =========================================================
    FORM INPUTS & BUTTON
========================================================= */
.input-group {
    position: relative;
    margin-bottom: 20px; 
}

.login-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
}

.login-input {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    border: 1px solid #ddd; 
    border-radius: 8px;
    outline: none;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    box-sizing: border-box; /* Important for padding */
}

/* Focus effect */
.login-input:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 5px rgba(0, 128, 128, 0.3);
}

/* Login Button */
.btn-login {
    width: 100%;
    padding: 14px;
    background: var(--color-primary);
    border: none;
    border-radius: 8px;
    color: var(--color-light); 
    font-size: 18px;
    cursor: pointer;
    margin-top: 15px;
    font-weight: 600;
    transition: 0.3s;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn-login:hover {
    background: var(--color-secondary);
    transform: translateY(-1px);
}

/* =========================================================
    ERROR STATE
========================================================= */
.error-message {
    font-size: 14px;
    color: var(--color-error); 
    margin-top: 15px;
    padding: 10px;
    border: 1px solid var(--color-error);
    background: #f8d7da;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    transition: opacity 0.3s;
}

/* LINKS & OPTIONS */
.login-options {
    text-align: center;
    margin-top: 25px;
    font-size: 14px;
}

.login-options a {
    color: var(--color-primary);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
}

.login-options a:hover {
    color: var(--color-secondary);
    text-decoration: underline;
}

/* Password icon */
.toggle-password {
    position: absolute;
    right: 10px;
    top: 38px; /* Adjusting icon position */
    color: #999;
    cursor: pointer;
    padding: 5px;
    transition: color 0.2s;
}

.toggle-password:hover {
    color: #555;
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

</style>
</head>
<body>

<div class="login-card">
    <h2><i class="fas fa-user-circle" style="margin-right: 10px;"></i> Customer Login</h2>

    <?php if (!empty($error)): ?>
        <p class="error-message"><?= $error; ?></p>
    <?php endif; ?>

    <form method="post">
        
        <div class="input-group">
            <label for="username" class="login-label">Username</label>
            <input 
                type="text" 
                name="username" 
                id="username" 
                required 
                placeholder="Enter your username"
                class="login-input"
                value="<?= htmlspecialchars($username_value); ?>"
                autocomplete="username"
            >
        </div>

        <div class="input-group">
            <label for="password" class="login-label">Password</label>
            <input 
                type="password" 
                name="password" 
                id="password-input" 
                required 
                placeholder="Enter your password"
                class="login-input"
                autocomplete="current-password"
            >
            <i class="fas fa-eye toggle-password" id="toggle-password-icon"></i>
        </div>

        <button type="submit" name="login" class="btn-login"><i class="fas fa-sign-in-alt"></i> LOG IN</button>
    </form>

    <div class="login-options">
        Don't have an account? <a href="register.php">Register here</a><br>
        <a href="../index.php" style="margin-top: 10px; display: block;">Back to Catalog</a>
    </div>
</div>

<script>
    // TOGGLE PASSWORD FUNCTIONALITY
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password-input');
        const toggleIcon = document.getElementById('toggle-password-icon');

        toggleIcon.addEventListener('click', function() {
            // Toggle type between 'password' and 'text'
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Change the icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Focus on the username input if there was an error
        <?php if (!empty($error)): ?>
            document.getElementById('username').focus();
        <?php endif; ?>
    });
</script>

</body>
</html>