<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

// ১. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$msg = "";
$error_msg = "";

// অ্যাডমিন ইমেইল বা আইডি সেশন থেকে অথবা ডিফল্ট ধরা
$admin_email = $_SESSION['admin_email'] ?? 'admin@gmail.com';

// ডাটাবেজ থেকে অ্যাডমিন তথ্য ফেচ করা
$admin_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$admin_email' OR role = 'admin' LIMIT 1");
if (!$admin_query || mysqli_num_rows($admin_query) == 0) {
    $admin_query = mysqli_query($conn, "SELECT * FROM admin LIMIT 1");
}

$admin_data = ($admin_query && mysqli_num_rows($admin_query) > 0) ? mysqli_fetch_assoc($admin_query) : [];

$admin_name = $admin_data['name'] ?? $admin_data['username'] ?? 'Admin';
$admin_email_curr = $admin_data['email'] ?? 'admin@gmail.com';
$admin_id = $admin_data['id'] ?? $admin_data['admin_id'] ?? 1;

// ২. পাসওয়ার্ড চেঞ্জ করার প্রসেস
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $old_pass = mysqli_real_escape_string($conn, trim($_POST['old_password'] ?? ''));
    $new_pass = mysqli_real_escape_string($conn, trim($_POST['new_password'] ?? ''));
    $confirm_pass = mysqli_real_escape_string($conn, trim($_POST['confirm_password'] ?? ''));

    if (empty($old_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error_msg = "All password fields are required.";
    } elseif ($new_pass !== $confirm_pass) {
        $error_msg = "New password and confirm password do not match.";
    } else {
        // পাসওয়ার্ড আপডেট
        $check_users = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
        if ($check_users && mysqli_num_rows($check_users) > 0) {
            $update_sql = "UPDATE users SET password = '$new_pass' WHERE email = '$admin_email_curr' OR role = 'admin'";
        } else {
            $update_sql = "UPDATE admin SET password = '$new_pass' WHERE id = $admin_id";
        }

        if (mysqli_query($conn, $update_sql)) {
            $msg = "Password updated successfully!";
        } else {
            $error_msg = "Failed to update password: " . mysqli_error($conn);
        }
    }
}

// ৩. প্রোফাইল ইনফো আপডেট പ്രসেস
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name  = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $new_email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));

    if (!empty($new_name) && !empty($new_email)) {
        $check_users = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
        if ($check_users && mysqli_num_rows($check_users) > 0) {
            $up_prof = "UPDATE users SET name = '$new_name', email = '$new_email' WHERE email = '$admin_email_curr' OR role = 'admin'";
        } else {
            $up_prof = "UPDATE admin SET username = '$new_name', email = '$new_email' WHERE id = $admin_id";
        }

        if (mysqli_query($conn, $up_prof)) {
            $msg = "Profile updated successfully!";
            $admin_name = $new_name;
            $admin_email_curr = $new_email;
            $_SESSION['admin_email'] = $new_email;
        } else {
            $error_msg = "Failed to update profile: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #0f172a; min-height: 100vh; }

        /* Premium Left Navigation Sidebar */
        .sidebar { 
            width: 260px; 
            background: #0f172a; 
            padding: 28px 18px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            position: fixed; 
            height: 100vh; 
            z-index: 100;
            border-left: 5px solid #4f46e5;
            box-shadow: 4px 0 25px rgba(0,0,0,0.05);
        }
        
        .brand { font-size: 20px; font-weight: 800; color: #ffffff; display: flex; align-items: center; gap: 12px; margin-bottom: 35px; padding-left: 6px; }
        .brand-icon { background: #4f46e5; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; }
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 14px; text-decoration: none; color: #94a3b8; font-size: 13.5px; font-weight: 600; border-radius: 10px; transition: all 0.2s ease; }
        .nav-item.active a { background: #1e293b; color: #ffffff; border-left: 3px solid #6366f1; }
        .nav-item.active a i { color: #6366f1; }
        .nav-item a:hover { background: #1e293b; color: #ffffff; }

        /* Main Workspace Container */
        .main-content { flex: 1; margin-left: 260px; padding: 32px 40px; }

        /* Top Header Card */
        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            background: #ffffff; 
            padding: 16px 24px; 
            border-radius: 16px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
        }
        .page-title h1 { font-size: 22px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .page-title p { font-size: 12.5px; color: #64748b; margin-top: 2px; }

        /* Notifications */
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #15803d; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        /* Layout Grid */
        .settings-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; }

        /* Admin Profile Card */
        .profile-card { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 28px 24px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .avatar-box { width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #4f46e5); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 34px; font-weight: 800; margin: 0 auto 16px; box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25); }
        .profile-name { font-size: 18px; font-weight: 800; color: #0f172a; }
        .profile-email { font-size: 13px; color: #64748b; margin-top: 3px; }
        .role-badge { display: inline-flex; align-items: center; gap: 6px; background: #eff6ff; color: #2563eb; padding: 5px 14px; border-radius: 20px; font-size: 11.5px; font-weight: 700; margin-top: 14px; text-transform: uppercase; }

        .info-list { margin-top: 24px; border-top: 1px solid #f1f5f9; padding-top: 18px; text-align: left; display: flex; flex-direction: column; gap: 12px; }
        .info-item { display: flex; justify-content: space-between; font-size: 13px; }
        .info-item span:first-child { color: #64748b; font-weight: 600; }
        .info-item span:last-child { color: #0f172a; font-weight: 700; }

        /* Form Card */
        .card-box { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); margin-bottom: 24px; }
        .card-header { font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; }

        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px; }
        
        .form-control { width: 100%; padding: 11px 14px 11px 40px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 13.5px; color: #0f172a; outline: none; transition: all 0.2s; }
        .form-control:focus { border-color: #4f46e5; background: #ffffff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

        .btn-primary { background: #4f46e5; color: #ffffff; border: none; padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-primary:hover { background: #4338ca; }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                CampusBite
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item"><a href="add_food.php"><i class="fa-solid fa-plus-circle"></i> Add Food</a></li>
                <li class="nav-item"><a href="manage_food.php"><i class="fa-solid fa-bowl-food"></i> Manage Food</a></li>
                <li class="nav-item"><a href="manage_orders.php"><i class="fa-solid fa-receipt"></i> Manage Orders</a></li>
                <li class="nav-item"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li class="nav-item active"><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
            </ul>
        </div>
        <div class="nav-menu">
            <li class="nav-item"><a href="../logout.php" style="color:#ef4444;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-gear" style="color:#4f46e5;"></i> Admin Settings</h1>
                <p>Manage your account settings, security preferences, and system profile.</p>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Profile Preview Card -->
            <div>
                <div class="profile-card">
                    <div class="avatar-box">
                        <?= strtoupper(substr($admin_name, 0, 1)); ?>
                    </div>
                    <div class="profile-name"><?= htmlspecialchars($admin_name); ?></div>
                    <div class="profile-email"><?= htmlspecialchars($admin_email_curr); ?></div>
                    <div class="role-badge"><i class="fa-solid fa-shield-halved"></i> System Administrator</div>

                    <div class="info-list">
                        <div class="info-item">
                            <span>Account Status</span>
                            <span style="color:#16a34a;"><i class="fa-solid fa-circle-check"></i> Active</span>
                        </div>
                        <div class="info-item">
                            <span>System Role</span>
                            <span>Super Admin</span>
                        </div>
                        <div class="info-item">
                            <span>Cafeteria System</span>
                            <span>CampusBite v1.0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Forms Workspace -->
            <div>
                <!-- Update Profile Details Card -->
                <div class="card-box">
                    <div class="card-header">
                        <i class="fa-solid fa-id-card" style="color:#4f46e5;"></i> Account Information
                    </div>
                    <form method="POST" action="settings.php">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Admin Display Name</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-user"></i>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($admin_name); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-envelope"></i>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin_email_curr); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 8px;">
                            <button type="submit" name="update_profile" class="btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> Save Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security & Password Change Card -->
                <div class="card-box">
                    <div class="card-header">
                        <i class="fa-solid fa-lock" style="color:#4f46e5;"></i> Security & Password
                    </div>
                    <form method="POST" action="settings.php">
                        <div class="form-group">
                            <label>Current Password</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-key"></i>
                                <input type="password" name="old_password" class="form-control" placeholder="Enter current password" required>
                            </div>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>New Password</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-lock"></i>
                                    <input type="password" name="new_password" class="form-control" placeholder="••••••••" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-shield-cat"></i>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 8px;">
                            <button type="submit" name="update_password" class="btn-primary">
                                <i class="fa-solid fa-arrows-rotate"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>