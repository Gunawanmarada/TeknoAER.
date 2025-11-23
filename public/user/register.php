<?php
// PATH FIX: Go out two levels (from public/user/) to tekno-aer/config/
include '../../config/db.php';
session_start();

// If the user is already logged in, redirect to the dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$nama_value = '';
$username_value = '';
$email_value = '';

if (isset($_POST['register'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password']; // Plain-text password from the form

    $nama_value = $nama;
    $username_value = $username;
    $email_value = $email;

    // --- SECURITY IMPROVEMENT: Use Prepared Statements ---
    
    // 1. Check if the username is already taken
    $stmt_check_user = $conn->prepare("SELECT user_id FROM user WHERE username = ?");
    $stmt_check_user->bind_param("s", $username);
    $stmt_check_user->execute();
    $result_check_user = $stmt_check_user->get_result();

    if ($result_check_user->num_rows > 0) {
        $message = "❌ Username **" . htmlspecialchars($username) . "** is already taken! Please choose another one.";
        $stmt_check_user->close();
    } else {
        $stmt_check_user->close();

        // 2. Check if the email is already registered (NEW LOGIC)
        $stmt_check_email = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $result_check_email = $stmt_check_email->get_result();

        if ($result_check_email->num_rows > 0) {
            $message = "❌ Email **" . htmlspecialchars($email) . "** is already registered! Please log in or use a different email.";
            $stmt_check_email->close();
        } else {
            $stmt_check_email->close();

            // 3. Insert data into the database (WITHOUT PASSWORD HASHING)
            // ⚠️ WARNING: $password (plain text) is stored directly!
            $stmt_insert = $conn->prepare("INSERT INTO user (nama_lengkap, username, email, password) VALUES (?, ?, ?, ?)");
            $stmt_insert->bind_param("ssss", $nama, $username, $email, $password);

            if ($stmt_insert->execute()) {
                // Redirect directly to login.php upon successful registration
                $_SESSION['registration_success'] = "✅ Registration successful! Please log in with your new account.";
                header("Location: login.php");
                exit;
            } else {
                $message = "❌ Failed to register: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Account - TeknoAER</title>
<link rel="icon" type="image/jpeg" href="../../assets/uploads/logo/logo.jpg">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
/* =========================================================
    ROOT & GLOBAL STYLE (Consistent Teal Color)
========================================================= */
:root {
    --color-primary: #008080; /* Teal */
    --color-secondary: #004d40;
    --color-light: #ffffff;
    --color-error: #dc3545; /* Red for error */
    --color-success: #28a745; /* Green for success */
    --font-poppins: 'Poppins', sans-serif;
}

body {
    margin: 0;
    padding: 0;
    height: 100vh;
    display: flex;
    font-family: var(--font-poppins);
    background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%); 
    overflow: auto;
    justify-content: center;
    align-items: center;
}

/* =========================================================
    REGISTER CARD
========================================================= */
.register-card {
    width: 90%;
    max-width: 450px;
    background: var(--color-light);
    padding: 40px;
    margin: 30px 0; /* Provide some margin for scrolling on small devices */
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    animation: fadeIn 0.8s ease-out;
}

.register-card h2 {
    color: var(--color-primary);
    text-align: center;
    font-size: 28px;
    margin-bottom: 25px;
    font-weight: 700;
}

/* =========================================================
    FORM INPUTS & BUTTON
========================================================= */
.input-group {
    position: relative;
    margin-bottom: 20px; 
}

.register-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
}

.register-input {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    border: 1px solid #ddd; 
    border-radius: 8px;
    outline: none;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    box-sizing: border-box;
}

.register-input:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 5px rgba(0, 128, 128, 0.3);
}

.btn-register {
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

.btn-register:hover {
    background: var(--color-secondary);
    transform: translateY(-1px);
}

/* =========================================================
    MESSAGE/ALERT STATE
========================================================= */
.alert-message {
    font-size: 14px;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    font-weight: 600;
    margin-bottom: 20px;
}

.alert-message.error {
    color: var(--color-error); 
    border: 1px solid var(--color-error);
    background: #f8d7da;
}

.alert-message.success {
    /* Success state is used for unhandled errors since redirect occurs on real success */
    color: var(--color-success); 
    border: 1px solid var(--color-success);
    background: #d4edda;
}


/* LINKS & OPTIONS */
.register-options {
    text-align: center;
    margin-top: 25px;
    font-size: 14px;
}

.register-options a {
    color: var(--color-primary);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
}

.register-options a:hover {
    color: var(--color-secondary);
    text-decoration: underline;
}

/* Password icon */
.toggle-password {
    position: absolute;
    right: 10px;
    top: 38px; 
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

<div class="register-card">
    <h2><i class="fas fa-user-plus" style="margin-right: 10px;"></i> Create New Account</h2>

    <?php 
    // Only display message for errors (success now redirects)
    if ($message) {
        $class = strpos($message, '✅') !== false ? 'success' : 'error';
        echo "<p class='alert-message $class'>$message</p>";
    }
    ?>

    <form method="post">
        
        <div class="input-group">
            <label for="nama" class="register-label">Full Name</label>
            <input 
                type="text" 
                name="nama" 
                id="nama" 
                placeholder="Your full name" 
                required
                class="register-input"
                value="<?= htmlspecialchars($nama_value); ?>"
                autocomplete="name"
            >
        </div>

        <div class="input-group">
            <label for="username" class="register-label">Username</label>
            <input 
                type="text" 
                name="username" 
                id="username" 
                placeholder="Username (no spaces)" 
                required
                class="register-input"
                value="<?= htmlspecialchars($username_value); ?>"
                autocomplete="username"
            >
        </div>
        
        <div class="input-group">
            <label for="email" class="register-label">Email</label>
            <input 
                type="email" 
                name="email" 
                id="email" 
                placeholder="Active email address" 
                required
                class="register-input"
                value="<?= htmlspecialchars($email_value); ?>"
                autocomplete="email"
            >
        </div>

        <div class="input-group">
            <label for="password-input" class="register-label">Password</label>
            <input 
                type="password" 
                name="password" 
                id="password-input" 
                placeholder="Minimum 6 characters" 
                required
                class="register-input"
                autocomplete="new-password"
            >
            <i class="fas fa-eye toggle-password" id="toggle-password-icon"></i>
        </div>

        <button type="submit" name="register" class="btn-register"><i class="fas fa-user-plus"></i> REGISTER NOW</button>
    </form>

    <div class="register-options">
        Already have an account? <a href="login.php">Log in here</a>
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
        
        // Focus on the input field if there was a validation error
        const hasErrorMessage = "<?php echo $message !== '' ? 'true' : 'false'; ?>";
        const isSuccessMessage = "<?php echo (strpos($message, '✅') !== false) ? 'true' : 'false'; ?>";
        
        if (hasErrorMessage === 'true' && isSuccessMessage === 'false') {
            // Focus on the input related to the error
            if ("<?php echo strpos($message, 'Username is already taken'); ?>" !== "false") {
                document.getElementById('username').focus();
            } else if ("<?php echo strpos($message, 'Email is already registered'); ?>" !== "false") {
                document.getElementById('email').focus();
            } else {
                // Focus on name for other errors
                document.getElementById('nama').focus();
            }
        }
    });
</script>

</body>
</html>