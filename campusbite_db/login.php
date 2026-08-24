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

if (empty($_SESSION['captcha_student'])) {
    $_SESSION['captcha_student'] = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 5);
}
if (empty($_SESSION['captcha_admin'])) {
    $_SESSION['captcha_admin'] = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 5);
}

if (isset($_GET['action']) && $_GET['action'] === 'refresh_captcha') {
    $role = $_GET['role'] ?? 'student';
    $code = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 5);
    if ($role === 'admin') {
        $_SESSION['captcha_admin'] = $code;
    } else {
        $_SESSION['captcha_student'] = $code;
    }
    header('Content-Type: application/json');
    echo json_encode(['code' => $code]);
    exit();
}

$error_msg = "";

function is_brute_force_blocked($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        return false;
    }
    $attempts = $_SESSION['login_attempts'][$email];
    if ($attempts['count'] >= 5) {
        if (time() - $attempts['last_time'] < 900) {
            return true;
        } else {
            unset($_SESSION['login_attempts'][$email]);
        }
    }
    return false;
}

function record_failed_attempt($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        $_SESSION['login_attempts'][$email] = ['count' => 1, 'last_time' => time()];
    } else {
        $_SESSION['login_attempts'][$email]['count']++;
        $_SESSION['login_attempts'][$email]['last_time'] = time();
    }
}

function clear_failed_attempts($email) {
    unset($_SESSION['login_attempts'][$email]);
}

if (isset($_POST['login_student'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security Validation Failed (CSRF Mismatch)!");
    }

    // Email converted to lowercase so upper/lower case doesn't break login
    $login_input = strtolower(trim($_POST['student_id']));
    $password = trim($_POST['student_password']);
    $captcha_input = strtoupper(trim($_POST['captcha'] ?? ''));

    if ($captcha_input !== $_SESSION['captcha_student']) {
        $error_msg = "Invalid Security Verification Code!";
        $_SESSION['captcha_student'] = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 5);
    } 
    elseif (is_brute_force_blocked($login_input)) {
        $error_msg = "Too many failed attempts! Account temporarily locked for 15 minutes.";
    } 
    else {
        $stmt = $conn->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ? AND role = 'student'");
        $stmt->bind_param("s", $login_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            
            // Checking password securely (supports hashed & exact match case-sensitive plain text)
            if (password_verify($password, $row['password']) || strcmp($password, $row['password']) === 0) {
                clear_failed_attempts($login_input);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['user_name'] = $row['full_name'];
                
                unset($_SESSION['csrf_token'], $_SESSION['captcha_student']);
                header("Location: student/dashboard.php");
                exit();
            } else {
                record_failed_attempt($login_input);
                $error_msg = "Invalid email or password!";
            }
        } else {
            record_failed_attempt($login_input);
            $error_msg = "Invalid email or password!";
        }
        $stmt->close();
    }
}

if (isset($_POST['login_admin'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security Validation Failed (CSRF Mismatch)!");
    }

    // Email converted to lowercase so upper/lower case doesn't break login
    $email = strtolower(trim($_POST['admin_id']));
    $password = trim($_POST['admin_password']);
    $captcha_input = strtoupper(trim($_POST['captcha'] ?? ''));

    if ($captcha_input !== $_SESSION['captcha_admin']) {
        $error_msg = "Invalid Security Verification Code!";
        $_SESSION['captcha_admin'] = substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, 5);
    } 
    elseif (is_brute_force_blocked($email)) {
        $error_msg = "Too many failed attempts! Account temporarily locked for 15 minutes.";
    } 
    else {
        $stmt = $conn->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ? AND (role = 'admin' OR role = 'staff')");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            
            // Checking password securely (supports hashed & exact match case-sensitive plain text)
            if (password_verify($password, $row['password']) || strcmp($password, $row['password']) === 0) {
                clear_failed_attempts($email);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['user_name'] = $row['full_name'];
                
                unset($_SESSION['csrf_token'], $_SESSION['captcha_admin']);
                
                if ($row['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: staff/dashboard.php");
                }
                exit();
            } else {
                record_failed_attempt($email);
                $error_msg = "Invalid admin/staff email or password!";
            }
        } else {
            record_failed_attempt($email);
            $error_msg = "Invalid admin/staff email or password!";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EWU Cafeteria Portal</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">

    <style>
        .role-tabs {
            position: relative;
            display: flex;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 5px;
            margin-bottom: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
        }

        .tab-btn {
            flex: 1;
            padding: 12px 16px;
            border: none;
            background: transparent;
            color: #9ca3af;
            font-weight: 500;
            font-size: 0.92rem;
            cursor: pointer;
            z-index: 2;
            transition: color 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            outline: none;
        }

        .tab-btn.active {
            color: #ffffff;
            font-weight: 600;
        }

        .tab-glider {
            position: absolute;
            top: 5px;
            left: 5px;
            height: calc(100% - 10px);
            width: calc(50% - 5px);
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 9px;
            transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1), 
                        background-color 0.5s ease, 
                        box-shadow 0.5s ease;
            z-index: 1;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35);
            will-change: transform;
        }

        .tab-glider.admin {
            transform: translate3d(100%, 0, 0);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.35);
        }

        .auth-form {
            display: none;
            opacity: 0;
            will-change: transform, opacity;
        }

        .auth-form.active {
            display: block;
            animation: smoothFadeIn 0.45s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        @keyframes smoothFadeIn {
            0% {
                opacity: 0;
                transform: translate3d(0, 10px, 0) scale(0.98);
            }
            100% {
                opacity: 1;
                transform: translate3d(0, 0, 0) scale(1);
            }
        }
    </style>

    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js"></script>
    <script type="text/javascript">
        (function(){
            emailjs.init("gEYtYuIPa0vaUWoVW");
        })();
    </script>
</head>
<body>

    <div class="glass-card">
        <div style="text-align: center; margin-bottom: 15px;">
            <img src="css/logo.png" alt="EWU Logo" class="brand-logo" style="max-height: 80px; width: auto;">
        </div>

        <h2>Welcome Back</h2>
        <p class="sub-title">East West University Cafeteria Portal</p>

        <?php if (!empty($error_msg)): ?>
            <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; padding: 10px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="role-tabs">
            <div id="tabGlider" class="tab-glider"></div>
            <button id="studentBtn" class="tab-btn active" type="button" onclick="switchRole('student')">
                <i class="fa-solid fa-graduation-cap"></i> Student
            </button>
            <button id="adminBtn" class="tab-btn" type="button" onclick="switchRole('admin')">
                <i class="fa-solid fa-user-shield"></i> Admin / Staff
            </button>
        </div>

        <form id="studentForm" action="login.php" method="POST" class="auth-form active" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="input-group">
                <label>Student Email</label>
                <div class="input-field">
                    <i class="fa-solid fa-user"></i>
                    <input type="email" name="student_id" placeholder="e.g. shakibhossain@gmail.com" required autocomplete="new-password">
                </div>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="input-field">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="student_password" id="studentPass" placeholder="Enter password" required autocomplete="new-password">
                    <i class="fa-solid fa-eye eye-icon" onclick="togglePass('studentPass')"></i>
                </div>
            </div>

            <div class="input-group">
                <label>Security Verification</label>
                <div class="captcha-flex">
                    <div class="captcha-badge">
                        <span id="studentCaptchaDisplay"><?= $_SESSION['captcha_student']; ?></span>
                        <i class="fa-solid fa-rotate-right refresh-btn" onclick="refreshCaptcha('student')" title="Refresh Code"></i>
                    </div>
                    <input type="text" name="captcha" placeholder="Enter Code" required autocomplete="off">
                </div>
            </div>

            <div class="form-actions">
                <label><input type="checkbox" name="remember"> Remember me</label>
                <a href="javascript:void(0)" class="forgot-link" onclick="openForgotModal()">Forgot password?</a>
            </div>

            <button type="submit" name="login_student" class="btn-submit btn-green">
                Login <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>

            <p style="margin-top: 1.2rem; font-size: 0.82rem; color: #d1d5db;">
                Don't have an account? 
                <a href="register.php" class="forgot-link">Register Now</a>
            </p>
        </form>

        <form id="adminForm" action="login.php" method="POST" class="auth-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="input-group">
                <label>Admin / Staff Email</label>
                <div class="input-field">
                    <i class="fa-solid fa-user-gear"></i>
                    <input type="email" name="admin_id" placeholder="Enter email" required autocomplete="new-password">
                </div>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="input-field">
                    <i class="fa-solid fa-key"></i>
                    <input type="password" name="admin_password" id="adminPass" placeholder="Enter password" required autocomplete="new-password">
                    <i class="fa-solid fa-eye eye-icon" onclick="togglePass('adminPass')"></i>
                </div>
            </div>

            <div class="input-group">
                <label>Security Verification</label>
                <div class="captcha-flex">
                    <div class="captcha-badge">
                        <span id="adminCaptchaDisplay"><?= $_SESSION['captcha_admin']; ?></span>
                        <i class="fa-solid fa-rotate-right refresh-btn" onclick="refreshCaptcha('admin')" title="Refresh Code"></i>
                    </div>
                    <input type="text" name="captcha" placeholder="Enter Code" required autocomplete="off">
                </div>
            </div>

            <div class="form-actions" style="justify-content: space-between; align-items: center;">
                <label><input type="checkbox" name="remember"> Remember me</label>
                <span style="font-size: 0.78rem; color: #9ca3af;" title="Contact System Administrator to reset password">
                    <i class="fa-solid fa-circle-info"></i> Contact Admin for Reset
                </span>
            </div>

            <button type="submit" name="login_admin" class="btn-submit btn-blue">
                Login as Admin / Staff <i class="fa-solid fa-shield-halved"></i>
            </button>
        </form>
    </div>

    <div id="forgotModal" class="modal-overlay">
        <div class="modal-card">
            <i class="fa-solid fa-xmark close-btn" onclick="closeForgotModal()"></i>
            <div class="modal-icon"><i class="fa-solid fa-key"></i></div>
            <h3>Reset Password</h3>
            <p>Enter your institutional email to receive a password reset link.</p>
            
            <form onsubmit="handleResetPassword(event)">
                <div class="input-group" style="text-align: left; margin-top: 1.2rem;">
                    <label>Institutional Email</label>
                    <div class="input-field">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" placeholder="example@gmail.com" required autocomplete="off">
                    </div>
                </div>
                <button type="submit" class="btn-submit btn-green" style="margin-top: 1.2rem;">
                    Send Reset Link
                </button>
            </form>
        </div>
    </div>

    <script>
        function refreshCaptcha(role) {
            fetch('login.php?action=refresh_captcha&role=' + role)
                .then(response => response.json())
                .then(data => {
                    document.getElementById(role + 'CaptchaDisplay').innerText = data.code;
                });
        }

        function switchRole(role) {
            const studentForm = document.getElementById('studentForm');
            const adminForm = document.getElementById('adminForm');
            const studentBtn = document.getElementById('studentBtn');
            const adminBtn = document.getElementById('adminBtn');
            const glider = document.getElementById('tabGlider');

            if (role === 'student') {
                if (studentForm.classList.contains('active')) return;

                glider.classList.remove('admin');
                studentBtn.classList.add('active');
                adminBtn.classList.remove('active');

                adminForm.classList.remove('active');
                studentForm.classList.add('active');
            } else {
                if (adminForm.classList.contains('active')) return;

                glider.classList.add('admin');
                adminBtn.classList.add('active');
                studentBtn.classList.remove('active');

                studentForm.classList.remove('active');
                adminForm.classList.add('active');
            }
        }

        function togglePass(id) {
            const field = document.getElementById(id);
            field.type = field.type === 'password' ? 'text' : 'password';
        }

        function openForgotModal() {
            document.getElementById('forgotModal').classList.add('active');
        }

        function closeForgotModal() {
            document.getElementById('forgotModal').classList.remove('active');
        }

        function handleResetPassword(e) {
            e.preventDefault();

            const emailInput = e.target.querySelector('input[type="email"]');
            const userEmail = emailInput.value.trim();
            const submitBtn = e.target.querySelector('button[type="submit"]');

            submitBtn.innerText = 'Sending Link...';
            submitBtn.disabled = true;

            const resetUrl = window.location.origin + window.location.pathname.replace('login.php', 'reset-password.php');

            const templateParams = {
                to_email: userEmail,
                reset_link: resetUrl
            };

            emailjs.send("service_18p785j", "template_g4109np", templateParams)
                .then(function(response) {
                    alert('Success! Password reset link sent to: ' + userEmail);
                    emailInput.value = '';
                    closeForgotModal();
                }, function(error) {
                    alert('Email sending failed! Error: ' + JSON.stringify(error));
                })
                .finally(function() {
                    submitBtn.innerText = 'Send Reset Link';
                    submitBtn.disabled = false;
                });
        }
    </script>
</body>
</html>