<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$msg = "";
$error = "";

$admin_id = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    $profile_pic_query = "";
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['profile_pic']['name'];
        $filetmp = $_FILES['profile_pic']['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $new_filename = "admin_" . $admin_id . "_" . time() . "." . $ext;
            $upload_dir = "uploads/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($filetmp, $upload_path)) {
                $profile_pic_query = ", profile_pic = '$upload_path'";
            } else {
                $error = "Failed to upload profile picture.";
            }
        } else {
            $error = "Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.";
        }
    }

    if (empty($error) && !empty($full_name) && !empty($email)) {
        $update_query = "UPDATE users SET full_name = '$full_name', email = '$email' $profile_pic_query WHERE id = $admin_id";
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $msg = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile information.";
        }
    } else if (empty($error)) {
        $error = "Name and email fields are required.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (!empty($current_pass) && !empty($new_pass) && !empty($confirm_pass)) {
        $res = mysqli_query($conn, "SELECT password FROM users WHERE id = $admin_id");
        if ($res && mysqli_num_rows($res) > 0) {
            $user_data = mysqli_fetch_assoc($res);
            $db_password = $user_data['password'];

            $is_match = false;
            if (password_verify($current_pass, $db_password) || $current_pass === $db_password) {
                $is_match = true;
            }

            if ($is_match) {
                if ($new_pass === $confirm_pass) {
                    $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
                    $pass_update = "UPDATE users SET password = '$hashed_password' WHERE id = $admin_id";
                    if (mysqli_query($conn, $pass_update)) {
                        $msg = "Password updated securely!";
                    } else {
                        $error = "Failed to update password in database.";
                    }
                } else {
                    $error = "New password and confirmation do not match.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        } else {
            $error = "User not found.";
        }
    } else {
        $error = "All password fields are required.";
    }
}

$user_q = mysqli_query($conn, "SELECT * FROM users WHERE id = $admin_id");
$current_user = ($user_q && mysqli_num_rows($user_q) > 0) ? mysqli_fetch_assoc($user_q) : [
    'full_name' => 'System Admin',
    'email' => 'admin@gmail.com',
    'role' => 'admin',
    'profile_pic' => ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Settings | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background: #f1f5f9; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

        .sidebar { 
            width: 280px; 
            background: #ffffff; 
            border-right: 1px solid #e2e8f0;
            padding: 30px 20px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            position: fixed; 
            height: 100vh; 
            z-index: 100;
        }
        
        .brand { font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 14px; margin-bottom: 40px; padding-left: 6px; }
        .brand-icon { background: linear-gradient(135deg, #6366f1, #a855f7); width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3); }
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 14px; padding: 13px 18px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 14px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item.active a { background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1)); color: #7c3aed; border: 1px solid rgba(168, 85, 247, 0.2); font-weight: 700; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.08); }
        .nav-item.active a i { color: #7c3aed; }
        .nav-item a:hover { background: #f8fafc; color: #0f172a; transform: translateX(6px); }

        .main-content { flex: 1; margin-left: 280px; padding: 40px 45px; opacity: 0; min-height: 100vh; }

        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            background: #ffffff;
            padding: 24px 32px; 
            border-radius: 20px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02); 
        }
        .page-title h1 { font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-title p { font-size: 13px; color: #64748b; margin-top: 4px; font-weight: 500; }

        .settings-grid { display: grid; grid-template-columns: 1fr 1.4fr; gap: 25px; align-items: start; }
        
        .settings-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
            margin-bottom: 25px;
            position: relative;
        }

        .toast {
            position: fixed;
            top: 25px;
            right: 30px;
            z-index: 1000;
            padding: 14px 22px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .toast.success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #059669; }
        .toast.error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .profile-avatar-section { display: flex; flex-direction: column; align-items: center; text-align: center; padding-top: 10px; margin-bottom: 25px; }
        .avatar-container { position: relative; margin-bottom: 16px; }
        .avatar-circle { width: 110px; height: 110px; background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 42px; font-weight: 800; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3); border: 4px solid #fff; overflow: hidden; }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-badge-icon { position: absolute; bottom: 2px; right: 2px; background: #059669; color: #fff; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; border: 3px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        .profile-name { font-size: 18px; font-weight: 800; color: #0f172a; }
        .profile-email { font-size: 13px; color: #64748b; margin-top: 2px; }
        .role-badge { background: rgba(124, 58, 237, 0.08); color: #7c3aed; font-size: 11px; font-weight: 700; padding: 6px 16px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px; margin-top: 12px; border: 1px solid rgba(124, 58, 237, 0.15); }

        .info-list { display: flex; flex-direction: column; gap: 14px; border-top: 1px solid #f1f5f9; padding-top: 20px; }
        .info-item { display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
        .info-label { color: #64748b; font-weight: 600; }
        .info-val { color: #0f172a; font-weight: 700; }

        .section-title { font-size: 15px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
        .form-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; }
        .form-control { padding: 13px 18px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 13px; color: #0f172a; outline: none; width: 100%; transition: all 0.3s ease; }
        .form-control:focus { border-color: #7c3aed; background: #fff; box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.06); }

        .file-upload-box { border: 2px dashed #cbd5e1; background: #f8fafc; padding: 14px 18px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.3s ease; }
        .file-upload-box:hover, .file-upload-box.dragover { border-color: #7c3aed; background: #fcfbff; }
        .file-upload-box input[type="file"] { display: none; }
        .file-upload-text { font-size: 12px; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 10px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 230px; }
        .file-upload-btn { background: #e2e8f0; color: #1e293b; padding: 7px 14px; border-radius: 8px; font-size: 11px; font-weight: 700; transition: all 0.3s; flex-shrink: 0; }
        .file-upload-box:hover .file-upload-btn { background: #7c3aed; color: #fff; }

        .password-strength { display: flex; gap: 6px; margin-top: 6px; }
        .strength-bar { flex: 1; height: 3px; background: #e2e8f0; border-radius: 2px; transition: all 0.3s; }
        .strength-text { font-size: 11px; font-weight: 700; color: #64748b; margin-top: 4px; text-align: right; }

        .btn-action { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border: none; padding: 13px 26px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); display: inline-flex; align-items: center; gap: 8px; }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(99, 102, 241, 0.4); }
    </style>
</head>
<body>

    <?php if (!empty($msg)): ?>
        <div class="toast success" id="toastMsg"><i class="fa-solid fa-circle-check fa-lg"></i> <?= $msg; ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="toast error" id="toastMsg"><i class="fa-solid fa-triangle-exclamation fa-lg"></i> <?= $error; ?></div>
    <?php endif; ?>

    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                CampusBite
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item"><a href="add_food.php"><i class="fa-solid fa-circle-plus"></i> Add Food</a></li>
                <li class="nav-item"><a href="manage_food.php"><i class="fa-solid fa-bowl-food"></i> Manage Food</a></li>
                <li class="nav-item"><a href="manage_orders.php"><i class="fa-solid fa-receipt"></i> Manage Orders</a></li>
                <li class="nav-item"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item"><a href="staff_attendance.php"><i class="fa-solid fa-clipboard-user"></i> Staff Attendance</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li class="nav-item active"><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
            </ul>
        </div>
        <div class="nav-menu">
            <li class="nav-item"><a href="../logout.php" style="color:#dc2626;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-sliders" style="color:#7c3aed;"></i> System Settings</h1>
                <p>Customize your administrative credentials and advanced security parameters.</p>
            </div>
        </div>

        <div class="settings-grid">
            <div class="settings-card">
                <div class="profile-avatar-section">
                    <div class="avatar-container">
                        <div class="avatar-circle" id="avatarPreviewContainer">
                            <?php if (!empty($current_user['profile_pic']) && file_exists($current_user['profile_pic'])): ?>
                                <img src="<?= $current_user['profile_pic']; ?>" alt="Admin Profile">
                            <?php else: ?>
                                <span><?= strtoupper(substr($current_user['full_name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="avatar-badge-icon"><i class="fa-solid fa-check"></i></div>
                    </div>
                    <div class="profile-name"><?= htmlspecialchars($current_user['full_name']); ?></div>
                    <div class="profile-email"><?= htmlspecialchars($current_user['email']); ?></div>
                    <div><span class="role-badge"><i class="fa-solid fa-shield-halved"></i> SYSTEM ADMINISTRATOR</span></div>
                </div>
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">Account Status</span>
                        <span class="info-val" style="color:#059669;"><i class="fa-solid fa-circle-dot" style="font-size:10px;"></i> Active</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">System Role</span>
                        <span class="info-val"><?= ucfirst($current_user['role']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Cafeteria Version</span>
                        <span class="info-val">CampusBite v1.0</span>
                    </div>
                </div>
            </div>

            <div>
                <div class="settings-card">
                    <div class="section-title"><i class="fa-solid fa-user-gear" style="color:#7c3aed;"></i> Account Information</div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Admin Display Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($current_user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($current_user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Profile Picture</label>
                            <label class="file-upload-box" id="dropZone">
                                <div class="file-upload-text">
                                    <i class="fa-solid fa-cloud-arrow-up" style="color:#7c3aed;"></i>
                                    <span id="fileNameDisplay">Choose a file or drag here...</span>
                                </div>
                                <span class="file-upload-btn">Browse</span>
                                <input type="file" name="profile_pic" id="profilePicInput" accept="image/*">
                            </label>
                        </div>
                        <div style="text-align: right; margin-top: 20px;">
                            <button type="submit" name="update_profile" class="btn-action"><i class="fa-solid fa-floppy-disk"></i> Save Profile</button>
                        </div>
                    </form>
                </div>

                <div class="settings-card">
                    <div class="section-title"><i class="fa-solid fa-shield-keyhole" style="color:#7c3aed;"></i> Security & Password</div>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" placeholder="••••••••••••" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" id="newPasswordInput" class="form-control" placeholder="••••••••••••" required>
                                <div class="password-strength">
                                    <div class="strength-bar" id="bar1"></div>
                                    <div class="strength-bar" id="bar2"></div>
                                    <div class="strength-bar" id="bar3"></div>
                                    <div class="strength-bar" id="bar4"></div>
                                </div>
                                <div class="strength-text" id="strengthText">Strength</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••••••" required>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 20px;">
                            <button type="submit" name="update_password" class="btn-action"><i class="fa-solid fa-key"></i> Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            gsap.to("#mainContent", { opacity: 1, duration: 0.8, y: 0, ease: "power3.out", startAt: { y: 20 } });
            
            const toast = document.getElementById('toastMsg');
            if(toast) {
                setTimeout(() => {
                    gsap.to(toast, { opacity: 0, y: -20, duration: 0.5, onComplete: () => toast.remove() });
                }, 4000);
            }

            const profilePicInput = document.getElementById('profilePicInput');
            const avatarPreviewContainer = document.getElementById('avatarPreviewContainer');
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const dropZone = document.getElementById('dropZone');

            if(profilePicInput) {
                profilePicInput.addEventListener('change', function(event) {
                    const file = event.target.files[0];
                    if (file) {
                        fileNameDisplay.textContent = file.name;
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            avatarPreviewContainer.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => { e.preventDefault(); dropZone.classList.add('dragover'); }, false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); }, false);
            });

            const newPasswordInput = document.getElementById('newPasswordInput');
            const strengthText = document.getElementById('strengthText');
            const bars = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3'), document.getElementById('bar4')];

            if(newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    const val = this.value;
                    let score = 0;
                    if(val.length > 5) score++;
                    if(val.length > 8) score++;
                    if(/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
                    if(/[^A-Za-z0-9]/.test(val)) score++;

                    bars.forEach((bar, idx) => {
                        if(idx < score) {
                            bar.style.background = score <= 2 ? '#f59e0b' : '#059669';
                        } else {
                            bar.style.background = '#e2e8f0';
                        }
                    });

                    const texts = ['Very Weak', 'Weak', 'Good', 'Strong'];
                    strengthText.textContent = val.length > 0 ? texts[score - 1] || 'Very Weak' : 'Strength';
                });
            }
        });
    </script>
</body>
</html>