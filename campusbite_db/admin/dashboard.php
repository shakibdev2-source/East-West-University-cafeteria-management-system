<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

// ১. সিকিউরিটি চেক: ইউজার লগইন করা আছে কি না এবং ইউজারটি Admin কি না তা যাচাই করা
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    // যদি লগইন না থাকে অথবা ইউজার admin না হয় (যেমন student), তবে তাকে লগইন পেজে রিডাইরেক্ট করা হবে
    header("Location: ../login.php");
    exit();
}

// ২. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// ৩. সেফ কুয়েরি ফাংশন
function get_count($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return $row['total'] ?? 0;
    }
    return 0;
}

// ৪. ড্যাশবোর্ড মেট্রিক্স ফেচ করা
$total_foods    = get_count($conn, "SELECT COUNT(*) as total FROM foods");
$total_orders   = get_count($conn, "SELECT COUNT(*) as total FROM orders");

$total_staff    = get_count($conn, "SELECT COUNT(*) as total FROM staffs");
if ($total_staff == 0) {
    $total_staff = get_count($conn, "SELECT COUNT(*) as total FROM users WHERE role='staff'");
}

$total_students = get_count($conn, "SELECT COUNT(*) as total FROM students");
if ($total_students == 0) {
    $total_students = get_count($conn, "SELECT COUNT(*) as total FROM users WHERE role='student' OR role='user'");
}

// ৫. সাম্প্রতিক ৫টি অর্ডার ফেচ করা
$recent_orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY order_id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #0f172a; min-height: 100vh; }

        /* Left Side Accent Line & Sidebar */
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
        
        .brand { 
            font-size: 20px; 
            font-weight: 800; 
            color: #ffffff; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            margin-bottom: 35px; 
            padding-left: 6px;
        }
        .brand-icon { 
            background: #4f46e5; 
            width: 38px; 
            height: 38px; 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: #fff; 
            font-size: 16px; 
        }
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 12px 14px; 
            text-decoration: none; 
            color: #94a3b8; 
            font-size: 13.5px; 
            font-weight: 600; 
            border-radius: 10px; 
            transition: all 0.2s ease; 
        }
        
        /* Active & Hover Options */
        .nav-item.active a { background: #1e293b; color: #ffffff; border-left: 3px solid #6366f1; }
        .nav-item.active a i { color: #6366f1; }
        .nav-item a:hover { background: #1e293b; color: #ffffff; }

        /* Main Content Area */
        .main-content { flex: 1; margin-left: 260px; padding: 32px 40px; }

        /* Top Bar Header */
        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            background: #ffffff; 
            padding: 16px 24px; 
            border-radius: 16px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
        }
        .welcome-text h1 { font-size: 22px; font-weight: 800; color: #0f172a; }
        .welcome-text p { font-size: 12.5px; color: #64748b; margin-top: 2px; }
        
        .admin-profile { display: flex; align-items: center; gap: 12px; }
        .avatar { width: 40px; height: 40px; background: #4f46e5; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }

        /* Modern Grid Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { 
            background: #ffffff; 
            padding: 22px; 
            border-radius: 16px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            transition: transform 0.2s ease; 
        }
        .stat-card:hover { transform: translateY(-3px); }
        
        .stat-info h3 { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-info .value { font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 6px; }
        
        .icon-box { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .icon-blue { background: #eff6ff; color: #2563eb; }
        .icon-green { background: #f0fdf4; color: #16a34a; }
        .icon-amber { background: #fffbeb; color: #d97706; }
        .icon-rose { background: #fff1f2; color: #e11d48; }

        /* Dashboard Lower Section */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .card-box { background: #ffffff; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .card-title { font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; }

        /* Clean Table Style */
        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table th { text-align: left; padding: 10px 12px; font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; }
        .custom-table td { padding: 14px 12px; font-size: 13.5px; color: #334155; border-bottom: 1px solid #f8fafc; font-weight: 500; }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; }
        .badge-Pending { background: #fef3c7; color: #d97706; }
        .badge-Completed { background: #dcfce7; color: #15803d; }
        .badge-Cancelled { background: #fee2e2; color: #dc2626; }

        /* Quick Action Buttons */
        .quick-actions { display: flex; flex-direction: column; gap: 10px; }
        .action-btn { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; text-decoration: none; color: #334155; font-weight: 700; font-size: 13.5px; transition: all 0.2s ease; }
        .action-btn:hover { background: #4f46e5; color: #ffffff; border-color: #4f46e5; }
    </style>
</head>
<body>

    <!-- Professional Sidebar -->
    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                CampusBite
            </div>
            <ul class="nav-menu">
                <li class="nav-item active"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item"><a href="add_food.php"><i class="fa-solid fa-plus-circle"></i> Add Food</a></li>
                <li class="nav-item"><a href="manage_food.php"><i class="fa-solid fa-bowl-food"></i> Manage Food</a></li>
                <li class="nav-item"><a href="manage_orders.php"><i class="fa-solid fa-receipt"></i> Manage Orders</a></li>
                <li class="nav-item"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
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
        <!-- Header -->
        <div class="top-header">
            <div class="welcome-text">
                <h1>Welcome Back, Admin 👋</h1>
                <p>CampusBite System Overview & Performance</p>
            </div>
            <div class="admin-profile">
                <div style="text-align: right;">
                    <div style="font-weight: 800; font-size: 13.5px;">System Admin</div>
                    <div style="font-size: 11px; color: #64748b;">Super User</div>
                </div>
                <div class="avatar">A</div>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Foods</h3>
                    <div class="value"><?= number_format($total_foods); ?></div>
                </div>
                <div class="icon-box icon-blue"><i class="fa-solid fa-burger"></i></div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Orders</h3>
                    <div class="value"><?= number_format($total_orders); ?></div>
                </div>
                <div class="icon-box icon-green"><i class="fa-solid fa-shopping-bag"></i></div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Staff</h3>
                    <div class="value"><?= number_format($total_staff); ?></div>
                </div>
                <div class="icon-box icon-amber"><i class="fa-solid fa-user-tie"></i></div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Students</h3>
                    <div class="value"><?= number_format($total_students); ?></div>
                </div>
                <div class="icon-box icon-rose"><i class="fa-solid fa-graduation-cap"></i></div>
            </div>
        </div>

        <!-- Recent Table & Actions Grid -->
        <div class="dashboard-grid">
            <div class="card-box">
                <div class="card-title">
                    <span><i class="fa-solid fa-clock-rotate-left" style="color:#4f46e5; margin-right:6px;"></i> Recent Orders</span>
                    <a href="manage_orders.php" style="font-size:12px; color:#4f46e5; text-decoration:none; font-weight:700;">View All</a>
                </div>

                <?php if ($recent_orders && mysqli_num_rows($recent_orders) > 0): ?>
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Total Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ord = mysqli_fetch_assoc($recent_orders)): ?>
                                <tr>
                                    <td><strong>#<?= $ord['order_id']; ?></strong></td>
                                    <td style="color:#059669; font-weight:700;">৳<?= number_format($ord['total_amount'], 2); ?></td>
                                    <td><?= htmlspecialchars($ord['payment_method'] ?? 'COD'); ?></td>
                                    <td><span class="badge badge-<?= $ord['status']; ?>"><?= $ord['status']; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:#94a3b8; font-size:13.5px; text-align:center; padding: 20px 0;">No recent orders available.</p>
                <?php endif; ?>
            </div>

            <div class="card-box">
                <div class="card-title"><i class="fa-solid fa-bolt" style="color:#f59e0b; margin-right:6px;"></i> Quick Actions</div>
                <div class="quick-actions">
                    <a href="add_food.php" class="action-btn">
                        <i class="fa-solid fa-plus-circle" style="color:#4f46e5;"></i> Add Food Item
                    </a>
                    <a href="manage_orders.php" class="action-btn">
                        <i class="fa-solid fa-list-check" style="color:#16a34a;"></i> Manage Orders
                    </a>
                    <a href="manage_staff.php" class="action-btn">
                        <i class="fa-solid fa-user-plus" style="color:#d97706;"></i> Manage Staffs
                    </a>
                    <a href="reports.php" class="action-btn">
                        <i class="fa-solid fa-chart-pie" style="color:#e11d48;"></i> View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>