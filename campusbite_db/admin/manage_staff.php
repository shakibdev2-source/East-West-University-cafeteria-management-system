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

// ২. স্টাফ যুক্ত করার হ্যান্ডলিং
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $password = mysqli_real_escape_string($conn, trim($_POST['password'] ?? ''));

    if (!empty($name) && !empty($email) && !empty($password)) {
        // ডাটাবেজ টেবিল চেক (staffs নাকি users)
        $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'staffs'");
        if ($check_table && mysqli_num_rows($check_table) > 0) {
            $sql = "INSERT INTO staffs (name, email, password) VALUES ('$name', '$email', '$password')";
        } else {
            $sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', 'staff')";
        }

        if (mysqli_query($conn, $sql)) {
            $msg = "New staff member added successfully!";
        } else {
            $error_msg = "Error adding staff: " . mysqli_error($conn);
        }
    } else {
        $error_msg = "Please fill in all fields.";
    }
}

// ৩. স্টাফ ডিলিট করার হ্যান্ডলিং
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'staffs'");
    if ($check_table && mysqli_num_rows($check_table) > 0) {
        $del_sql = "DELETE FROM staffs WHERE staff_id = $delete_id OR id = $delete_id";
    } else {
        $del_sql = "DELETE FROM users WHERE id = $delete_id";
    }

    if (mysqli_query($conn, $del_sql)) {
        $msg = "Staff member removed successfully!";
    }
}

// ৪. স্টাফ তালিকা ফেচ করা
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'staffs'");
if ($check_table && mysqli_num_rows($check_table) > 0) {
    $staffs_result = mysqli_query($conn, "SELECT * FROM staffs ORDER BY 1 DESC");
} else {
    $staffs_result = mysqli_query($conn, "SELECT * FROM users WHERE role = 'staff' ORDER BY id DESC");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #0f172a; min-height: 100vh; }

        /* Left Side Accent Sidebar */
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

        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 32px 40px; }

        /* Top Header */
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

        /* Alerts */
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #15803d; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        /* Form Card */
        .card-box { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); margin-bottom: 25px; }
        .card-title { font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 14px; align-items: end; }
        .form-group label { display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 11px 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 13.5px; color: #0f172a; outline: none; transition: all 0.2s; }
        .form-control:focus { border-color: #4f46e5; background: #ffffff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

        .btn-submit { background: #4f46e5; color: #ffffff; border: none; padding: 11px 20px; border-radius: 10px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; height: 42px; }
        .btn-submit:hover { background: #4338ca; }

        /* Table Card */
        .card-table { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        .custom-table th { background: #f8fafc; padding: 14px 20px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .custom-table td { padding: 16px 20px; font-size: 13.5px; color: #1e293b; border-bottom: 1px solid #f1f5f9; font-weight: 500; vertical-align: middle; }
        .custom-table tr:hover { background: #f8fafc; }

        .btn-delete { background: #fee2e2; color: #dc2626; text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s; }
        .btn-delete:hover { background: #fca5a5; }
    </style>
</head>
<body>

    <!-- Sidebar Menu -->
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
                <li class="nav-item active"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li class="nav-item"><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
            </ul>
        </div>
        <div class="nav-menu">
            <li class="nav-item"><a href="../logout.php" style="color:#ef4444;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </div>
    </div>

    <!-- Main Content Panel -->
    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-users-gear" style="color:#4f46e5;"></i> Manage Staff Members</h1>
                <p>Add and manage cafeteria staff accounts.</p>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <!-- Add Staff Form Card -->
        <div class="card-box">
            <div class="card-title"><i class="fa-solid fa-user-plus" style="color:#4f46e5;"></i> Add New Staff Member</div>
            <form method="POST" action="manage_staff.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Staff Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <label>Staff Email</label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <div>
                        <button type="submit" name="add_staff" class="btn-submit">
                            <i class="fa-solid fa-plus"></i> Add Staff
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Staff List Table -->
        <div class="card-table">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Staff Name</th>
                        <th>Email Address</th>
                        <th style="width: 120px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($staffs_result && mysqli_num_rows($staffs_result) > 0): ?>
                        <?php while ($staff = mysqli_fetch_assoc($staffs_result)): ?>
                            <?php 
                                $id = $staff['staff_id'] ?? $staff['id'] ?? 'N/A';
                                $s_name = $staff['name'] ?? $staff['username'] ?? 'Staff Member';
                            ?>
                            <tr>
                                <td><strong style="color:#4f46e5;">#<?= $id; ?></strong></td>
                                <td><strong style="color:#0f172a;"><?= htmlspecialchars($s_name); ?></strong></td>
                                <td style="color:#64748b;"><?= htmlspecialchars($staff['email']); ?></td>
                                <td style="text-align: center;">
                                    <a href="manage_staff.php?delete_id=<?= $id; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to remove this staff member?');">
                                        <i class="fa-solid fa-trash-can"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #94a3b8; padding: 30px;">No staff members found. Add your first staff member using the form above.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>