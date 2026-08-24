<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "campusbite_db";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Database Connection Failed.");
}

$conn->set_charset("utf8mb4");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_msg = "";
$success_msg = "";

if (isset($_POST['register'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security Validation Failed (CSRF Mismatch)!");
    }

    $name = trim(filter_var($_POST['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $student_id = trim(filter_var($_POST['student_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $email = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
    $password = trim($_POST['password']);

    if (empty($name) || empty($student_id) || empty($email) || empty($password)) {
        $error_msg = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Invalid email format!";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_msg = "This Email is already registered!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $role = 'student';

            $conn->begin_transaction();

            try {
                // ১. users টেবিলে ইনসার্ট
                $stmt1 = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt1->bind_param("ssss", $name, $email, $hashed_password, $role);
                $stmt1->execute();
                
                // নতুন তৈরি হওয়া ইউজার ID নেওয়া
                $new_user_id = $stmt1->insert_id;
                $stmt1->close();

                // ২. students টেবিলে user_id এবং student_number ইনসার্ট
                $stmt2 = $conn->prepare("INSERT INTO students (user_id, student_number) VALUES (?, ?)");
                $stmt2->bind_param("is", $new_user_id, $student_id);
                $stmt2->execute();
                $stmt2->close();

                $conn->commit();
                $success_msg = "Registration successful! You can now login.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - EWU Cafeteria Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>

    <div class="glass-card">
        <img src="assets/images/logo.png" alt="EWU Logo" class="brand-logo">
        <h2>Create Account</h2>
        <p class="sub-title">East West University Cafeteria Portal</p>

        <?php if (!empty($error_msg)): ?>
            <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; padding: 10px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div style="background: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; color: #4ade80; padding: 10px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="auth-form active" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="input-group">
                <label>Full Name</label>
                <div class="input-field">
                    <i class="fa-solid fa-id-card"></i>
                    <input type="text" name="name" placeholder="Enter your full name" required autocomplete="off">
                </div>
            </div>

            <div class="input-group">
                <label>Student ID</label>
                <div class="input-field">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <input type="text" name="student_id" placeholder="e.g. 2024-3-60-676" required autocomplete="off">
                </div>
            </div>

            <div class="input-group">
                <label>Institutional Email</label>
                <div class="input-field">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" placeholder="example@std.ewubd.edu" required autocomplete="off" oninput="this.value = this.value.replace(/\s+/g, '')">
                </div>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="input-field">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="regPass" placeholder="Enter new password" required autocomplete="new-password">
                    <i class="fa-solid fa-eye eye-icon" onclick="togglePass('regPass')"></i>
                </div>
            </div>

            <button type="submit" name="register" class="btn-submit btn-green" style="margin-top: 1rem;">
                Register Now <i class="fa-solid fa-user-plus"></i>
            </button>

            <p style="margin-top: 1.2rem; font-size: 0.82rem; color: #d1d5db;">
                Already have an account? 
                <a href="login.php" class="forgot-link">Login Here</a>
            </p>
        </form>
    </div>

    <script>
        function togglePass(id) {
            const field = document.getElementById(id);
            field.type = field.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>