<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// ১. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// ২. রিপোর্ট কার্ড সমূহের তথ্য হিসাব করা
// Total Foods
$total_foods = 0;
$q_foods = mysqli_query($conn, "SELECT COUNT(*) AS total FROM foods");
if ($q_foods && $r = mysqli_fetch_assoc($q_foods)) {
    $total_foods = $r['total'];
}

// Total Orders
$total_orders = 0;
$q_orders = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders");
if ($q_orders && $r = mysqli_fetch_assoc($q_orders)) {
    $total_orders = $r['total'];
}

// Total Sales Amount
$total_sales = 0;
$q_sales = mysqli_query($conn, "SELECT SUM(total_amount) AS total FROM orders");
if (!$q_sales || mysqli_num_rows($q_sales) == 0) {
    $q_sales = mysqli_query($conn, "SELECT SUM(total_price) AS total FROM orders");
}
if ($q_sales && $r = mysqli_fetch_assoc($q_sales)) {
    $total_sales = $r['total'] ?? 0;
}

// Total Users / Staffs
$total_users = 0;
$q_users = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
if (!$q_users) {
    $q_users = mysqli_query($conn, "SELECT COUNT(*) AS total FROM staffs");
}
if ($q_users && $r = mysqli_fetch_assoc($q_users)) {
    $total_users = $r['total'];
}

// ৩. রিসেন্ট অর্ডার ডাটা ফেচ করা
$orders_result = mysqli_query($conn, "SELECT * FROM orders ORDER BY 1 DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Performance | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #0f172a; min-height: 100vh; }

        /* Sidebar Navigation */
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

        /* Main Content Workspace */
        .main-content { flex: 1; margin-left: 260px; padding: 32px 40px; }

        /* Top Bar Header */
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

        /* Stats Grid Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #ffffff; padding: 22px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 18px; }
        .stat-icon { width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        
        .icon-blue { background: #eff6ff; color: #2563eb; }
        .icon-green { background: #dcfce7; color: #16a34a; }
        .icon-amber { background: #fef3c7; color: #d97706; }
        .icon-rose { background: #ffe4e6; color: #e11d48; }

        .stat-info .title { font-size: 12.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-info .value { font-size: 24px; font-weight: 800; color: #0f172a; margin-top: 4px; }

        /* Report Table Card */
        .card-table { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .table-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; font-size: 16px; font-weight: 800; color: #0f172a; display: flex; align-items: center; justify-content: space-between; }
        
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        .custom-table th { background: #f8fafc; padding: 14px 20px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .custom-table td { padding: 16px 20px; font-size: 13.5px; color: #1e293b; border-bottom: 1px solid #f1f5f9; font-weight: 500; vertical-align: middle; }
        .custom-table tr:hover { background: #f8fafc; }

        /* Badges */
        .badge { padding: 5px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-Pending { background: #fef3c7; color: #d97706; }
        .badge-Completed { background: #dcfce7; color: #15803d; }
        .badge-Cancelled { background: #fee2e2; color: #dc2626; }
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
                <li class="nav-item"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item active"><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
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
                <h1><i class="fa-solid fa-chart-line" style="color:#4f46e5;"></i> CampusBite Reports</h1>
                <p>System overview and performance reports</p>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fa-solid fa-bowl-food"></i></div>
                <div class="stat-info">
                    <div class="title">Total Foods</div>
                    <div class="value"><?= $total_foods; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fa-solid fa-cart-shopping"></i></div>
                <div class="stat-info">
                    <div class="title">Total Orders</div>
                    <div class="value"><?= $total_orders; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-amber"><i class="fa-solid fa-bangladeshi-taka-sign"></i></div>
                <div class="stat-info">
                    <div class="title">Total Sales</div>
                    <div class="value">৳<?= number_format($total_sales, 2); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-rose"><i class="fa-solid fa-users"></i></div>
                <div class="stat-info">
                    <div class="title">Total Users</div>
                    <div class="value"><?= $total_users; ?></div>
                </div>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="card-table">
            <div class="table-header">
                <span><i class="fa-solid fa-clock-rotate-left" style="color:#4f46e5; margin-right: 8px;"></i> Recent Orders</span>
            </div>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Food Details</th>
                        <th style="text-align: center;">Quantity</th>
                        <th>Amount</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders_result && mysqli_num_rows($orders_result) > 0): ?>
                        <?php while ($ord = mysqli_fetch_assoc($orders_result)): ?>
                            <?php 
                                // Safe array key handling to fix PHP Warnings
                                $order_id = $ord['order_id'] ?? $ord['id'] ?? 'N/A';
                                $customer = $ord['customer_name'] ?? $ord['name'] ?? $ord['email'] ?? $ord['user_id'] ?? 'Student';
                                $food_item = $ord['food_name'] ?? $ord['food_details'] ?? $ord['items'] ?? 'Food Item';
                                $qty       = $ord['quantity'] ?? $ord['qty'] ?? 1;
                                $amount    = $ord['total_amount'] ?? $ord['total_price'] ?? $ord['price'] ?? 0;
                                $status    = $ord['status'] ?? 'Pending';
                            ?>
                            <tr>
                                <td><strong style="color:#4f46e5;">#<?= $order_id; ?></strong></td>
                                <td><strong style="color:#0f172a;"><?= htmlspecialchars($customer); ?></strong></td>
                                <td><?= htmlspecialchars($food_item); ?></td>
                                <td style="text-align: center; font-weight: 700; color:#475569;"><?= $qty; ?></td>
                                <td style="font-weight: 800; color: #059669;">৳<?= number_format($amount, 2); ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $status; ?>"><?= $status; ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #94a3b8; padding: 30px;">No recent orders available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>