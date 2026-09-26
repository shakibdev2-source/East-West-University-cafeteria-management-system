<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$total_foods = 0;
$total_orders = 0;
$total_staff = 0;
$total_students = 0;
$total_revenue = 0;
$pending_orders = 0;

$food_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM foods");
if ($food_res) {
    $row = mysqli_fetch_assoc($food_res);
    $total_foods = $row['count'];
}

$order_res = mysqli_query($conn, "SELECT COUNT(*) as count, SUM(total_amount) as revenue FROM orders");
if ($order_res) {
    $row = mysqli_fetch_assoc($order_res);
    $total_orders = $row['count'] ?? 0;
    $total_revenue = $row['revenue'] ?? 0;
}

$pending_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'");
if ($pending_res) {
    $row = mysqli_fetch_assoc($pending_res);
    $pending_orders = $row['count'] ?? 0;
}

$staff_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM staffs");
if ($staff_res) {
    $row = mysqli_fetch_assoc($staff_res);
    $total_staff = $row['count'];
}

$student_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM students");
if ($student_res) {
    $row = mysqli_fetch_assoc($student_res);
    $total_students = $row['count'];
}

$recent_orders_query = "SELECT * FROM orders ORDER BY order_id DESC LIMIT 5";
$recent_orders_res = mysqli_query($conn, $recent_orders_query);

$low_stock_query = "SELECT * FROM foods WHERE stock_quantity <= 10 LIMIT 5";
$low_stock_res = mysqli_query($conn, $low_stock_query);

$weekly_revenue = [0, 0, 0, 0, 0, 0, 0];
$days_query = mysqli_query($conn, "SELECT DAYOFWEEK(created_at) as day_num, SUM(total_amount) as daily_total FROM orders GROUP BY DAYOFWEEK(created_at)");
if ($days_query) {
    while ($row = mysqli_fetch_assoc($days_query)) {
        $day_index = $row['day_num'] - 1; 
        if ($day_index >= 0 && $day_index < 7) {
            $weekly_revenue[$day_index] = (float)$row['daily_total'];
        }
    }
}
$chart_data_json = json_encode(array_values($weekly_revenue));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Futuristic Enterprise Dashboard | EWU Cafeteria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background: #fafbfc; color: #0f172a; min-height: 100vh; overflow-x: hidden; }

        /* Dynamic Animated Background Aura */
        .ambient-glow-1 {
            position: fixed;
            top: -200px;
            right: -200px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.07) 0%, rgba(255, 255, 255, 0) 70%);
            z-index: 0;
            pointer-events: none;
        }
        .ambient-glow-2 {
            position: fixed;
            bottom: -200px;
            left: 100px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.05) 0%, rgba(255, 255, 255, 0) 70%);
            z-index: 0;
            pointer-events: none;
        }

        .sidebar { 
            width: 280px; 
            background: #ffffff;
            padding: 30px 20px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            position: fixed; 
            height: 100vh; 
            z-index: 100;
            border-right: 1px solid #f1f5f9;
            box-shadow: 20px 0 50px rgba(0, 0, 0, 0.012);
            will-change: transform;
        }
        
        .brand { font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 14px; margin-bottom: 40px; padding-left: 6px; }
        .brand-icon { background: linear-gradient(135deg, #7c3aed, #4f46e5); width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3); }
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 14px; padding: 14px 18px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 14px; transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); position: relative; overflow: hidden; }
        .nav-item a::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background: #7c3aed; opacity: 0; transition: opacity 0.3s; }
        .nav-item.active a { background: linear-gradient(135deg, rgba(124, 58, 237, 0.08), rgba(79, 70, 229, 0.04)); color: #7c3aed; font-weight: 700; border: 1px solid rgba(124, 58, 237, 0.15); box-shadow: 0 4px 20px rgba(124, 58, 237, 0.04); }
        .nav-item.active a::before { opacity: 1; }
        .nav-item.active a i { color: #7c3aed; }
        .nav-item a:hover { background: #f8fafc; color: #0f172a; transform: translateX(6px); }

        .main-content { flex: 1; margin-left: 280px; padding: 40px 50px; opacity: 0; min-height: 100vh; background: #fafbfc; position: relative; z-index: 1; }

        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 35px; 
            background: #ffffff;
            padding: 22px 32px; 
            border-radius: 24px; 
            border: 1px solid #f1f5f9; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.012); 
            will-change: transform, opacity;
        }
        .page-title h1 { font-size: 22px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }

        .header-right { display: flex; align-items: center; gap: 15px; }
        .live-clock { font-size: 13px; font-weight: 700; color: #475569; background: #f8fafc; padding: 10px 16px; border-radius: 12px; display: flex; align-items: center; gap: 8px; border: 1px solid #f1f5f9; }
        .live-clock i { color: #7c3aed; }
        
        .action-btn { background: linear-gradient(135deg, #7c3aed, #4f46e5); color: #fff; border: none; padding: 11px 20px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); box-shadow: 0 8px 25px rgba(124, 58, 237, 0.25); }
        .action-btn:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(124, 58, 237, 0.35); }

        .admin-profile { display: flex; align-items: center; gap: 14px; }
        .admin-info { text-align: right; }
        .admin-name { font-size: 14px; font-weight: 700; color: #0f172a; }
        .admin-role { font-size: 11px; color: #64748b; font-weight: 600; }
        .admin-avatar { width: 45px; height: 45px; background: linear-gradient(135deg, #7c3aed, #4f46e5); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 16px; box-shadow: 0 6px 20px rgba(124, 58, 237, 0.25); }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 35px; }
        .stat-card { 
            background: #ffffff; 
            border-radius: 24px; 
            border: 1px solid #f1f5f9; 
            padding: 24px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.012); 
            transition: border-color 0.4s ease, box-shadow 0.4s ease; 
            position: relative; 
            overflow: hidden; 
            will-change: transform, opacity;
        }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, #7c3aed, #4f46e5); opacity: 0; transition: opacity 0.3s; }
        .stat-card:hover { box-shadow: 0 22px 45px rgba(124, 58, 237, 0.08); border-color: rgba(124, 58, 237, 0.3); }
        .stat-card:hover::before { opacity: 1; }
        .stat-top { display: flex; justify-content: space-between; align-items: center; }
        .stat-meta span { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }
        .stat-meta h3 { font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); }
        .stat-icon.foods { background: rgba(99, 102, 241, 0.08); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.15); }
        .stat-icon.orders { background: rgba(16, 185, 129, 0.08); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.15); }
        .stat-icon.revenue { background: rgba(236, 72, 153, 0.08); color: #ec4899; border: 1px solid rgba(236, 72, 153, 0.15); }
        .stat-icon.pending { background: rgba(245, 158, 11, 0.08); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.15); }
        .stat-icon.staff { background: rgba(14, 165, 233, 0.08); color: #0ea5e9; border: 1px solid rgba(14, 165, 233, 0.15); }
        .stat-icon.students { background: rgba(139, 92, 246, 0.08); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.15); }

        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px; }
        .dashboard-grid-full { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }

        .content-card { 
            background: #ffffff; 
            border-radius: 24px; 
            border: 1px solid #f1f5f9; 
            padding: 30px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.012); 
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1); 
            will-change: transform, opacity;
        }
        .content-card:hover { border-color: rgba(124, 58, 237, 0.25); box-shadow: 0 18px 40px rgba(124, 58, 237, 0.05); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .card-title { font-size: 15px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; letter-spacing: -0.3px; }
        .card-title i { color: #7c3aed; }
        .view-all-btn { font-size: 12px; font-weight: 700; color: #7c3aed; text-decoration: none; transition: color 0.2s; }
        .view-all-btn:hover { color: #6d28d9; text-decoration: underline; }

        .table-responsive { width: 100%; overflow-x: auto; }
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        .custom-table th { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; padding: 12px 16px; border-bottom: 2px solid #f8fafc; }
        .custom-table td { font-size: 13px; font-weight: 600; color: #334155; padding: 16px; border-bottom: 1px solid #fcfcfc; transition: background 0.2s; }
        .custom-table tbody tr { transition: all 0.2s ease; }
        .custom-table tbody tr:hover td { background: #f8fafc; color: #0f172a; }
        .empty-msg { text-align: center; padding: 30px; color: #94a3b8; font-size: 13px; font-weight: 600; }

        .quick-actions-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .quick-action-item { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; padding: 20px; background: #fafafa; border: 1px solid #f1f5f9; border-radius: 20px; text-decoration: none; color: #334155; font-size: 13px; font-weight: 700; transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); }
        .quick-action-item:hover { background: rgba(124, 58, 237, 0.04); border-color: rgba(124, 58, 237, 0.25); color: #7c3aed; transform: translateY(-4px); box-shadow: 0 12px 25px rgba(124, 58, 237, 0.08); }
        .quick-action-item i { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 16px; background: #ffffff; color: #7c3aed; border: 1px solid #f1f5f9; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }

        .badge-low-stock { background: rgba(239, 68, 68, 0.08); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.15); padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; }
        .badge-status { background: rgba(16, 185, 129, 0.08); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.15); padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; }

        /* Floating Toast Notification Box */
        #toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #ffffff;
            border: 1px solid rgba(124, 58, 237, 0.2);
            padding: 16px 24px;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(30px);
            pointer-events: none;
        }
        #toast-notification i { color: #7c3aed; font-size: 18px; }
        #toast-notification span { font-size: 13px; font-weight: 700; color: #0f172a; }
    </style>
</head>
<body>

    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <div id="toast-notification">
        <i class="fa-solid fa-circle-check"></i>
        <span>System Synchronized Successfully!</span>
    </div>

    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                EWU Cafeteria
            </div>
            <ul class="nav-menu">
                <li class="nav-item active"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item"><a href="add_food.php"><i class="fa-solid fa-circle-plus"></i> Add Food</a></li>
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

    <div class="main-content" id="mainContent">
        <div class="top-header">
            <div class="page-title">
                <h1>Welcome Back, Admin 👋</h1>
            </div>
            <div class="header-right">
                <div class="live-clock" id="liveClock">
                    <i class="fa-regular fa-clock"></i> <span id="timeString">--:--:--</span>
                </div>
                <button class="action-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Report</button>
                <div class="admin-profile">
                    <div class="admin-info">
                        <div class="admin-name">System Admin</div>
                        <div class="admin-role">Super User</div>
                    </div>
                    <div class="admin-avatar">A</div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-meta">
                        <span>Total Foods</span>
                        <h3 class="counter" data-target="<?= $total_foods; ?>">0</h3>
                    </div>
                    <div class="stat-icon foods"><i class="fa-solid fa-burger"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-meta">
                        <span>Total Orders</span>
                        <h3 class="counter" data-target="<?= $total_orders; ?>">0</h3>
                    </div>
                    <div class="stat-icon orders"><i class="fa-solid fa-bag-shopping"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-meta">
                        <span>Total Revenue</span>
                        <h3>৳<span class="counter" data-target="<?= $total_revenue; ?>">0</span></h3>
                    </div>
                    <div class="stat-icon revenue"><i class="fa-solid fa-bangladeshi-taka-sign"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-meta">
                        <span>Pending Orders</span>
                        <h3 class="counter" data-target="<?= $pending_orders; ?>">0</h3>
                    </div>
                    <div class="stat-icon pending"><i class="fa-solid fa-hourglass-half"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-meta">
                        <span>Total Staff</span>
                        <h3 class="counter" data-target="<?= $total_staff; ?>">0</h3>
                    </div>
                    <div class="stat-icon staff"><i class="fa-solid fa-user-tie"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-top">
                    <div class="stat-meta">
                        <span>Total Students</span>
                        <h3 class="counter" data-target="<?= $total_students; ?>">0</h3>
                    </div>
                    <div class="stat-icon students"><i class="fa-solid fa-graduation-cap"></i></div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Orders</div>
                    <a href="manage_orders.php" class="view-all-btn">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_orders_res && mysqli_num_rows($recent_orders_res) > 0): ?>
                                <?php while ($order = mysqli_fetch_assoc($recent_orders_res)): ?>
                                    <tr>
                                        <td>#<?= $order['order_id']; ?></td>
                                        <td><?= htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                        <td>৳<?= number_format($order['total_amount'] ?? 0, 2); ?></td>
                                        <td><span class="badge-status"><?= htmlspecialchars($order['status'] ?? 'Pending'); ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-msg">No recent orders available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-bolt"></i> Quick Actions</div>
                </div>
                <div class="quick-actions-grid">
                    <a href="add_food.php" class="quick-action-item">
                        <i class="fa-solid fa-plus"></i> Add Food Item
                    </a>
                    <a href="manage_orders.php" class="quick-action-item">
                        <i class="fa-solid fa-receipt"></i> Manage Orders
                    </a>
                    <a href="manage_staff.php" class="quick-action-item">
                        <i class="fa-solid fa-users-gear"></i> Manage Staffs
                    </a>
                    <a href="reports.php" class="quick-action-item">
                        <i class="fa-solid fa-chart-line"></i> View Reports
                    </a>
                </div>
            </div>
        </div>

        <div class="dashboard-grid-full">
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-chart-area"></i> Revenue Trend (Weekly Overview)</div>
                </div>
                <div style="height: 250px; position: relative;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Warning</div>
                    <a href="manage_food.php" class="view-all-btn">Inventory</a>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Food Name</th>
                                <th>Stock</th>
                                <th>Alert</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($low_stock_res && mysqli_num_rows($low_stock_res) > 0): ?>
                                <?php while ($food = mysqli_fetch_assoc($low_stock_res)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($food['food_name']); ?></td>
                                        <td><?= $food['stock_quantity']; ?> pcs</td>
                                        <td><span class="badge-low-stock">Critically Low</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="empty-msg">All items are sufficiently stocked!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            let ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            document.getElementById('timeString').innerText = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        }
        setInterval(updateClock, 1000);
        updateClock();

        document.addEventListener("DOMContentLoaded", () => {
            // Advanced GSAP Master Timeline Animation
            const masterTimeline = gsap.timeline({ defaults: { ease: "power3.out" } });

            // 1. Sidebar Entry Slide
            masterTimeline.fromTo(".sidebar", 
                { x: -140, opacity: 0 }, 
                { x: 0, opacity: 1, duration: 0.9, ease: "power4.out" }
            )
            // 2. Main Content Container Fade
            .to("#mainContent", { opacity: 1, duration: 0.3 }, "-=0.6")
            // 3. Header Drop Animation
            .fromTo(".top-header", 
                { y: -30, opacity: 0, scale: 0.98 }, 
                { y: 0, opacity: 1, scale: 1, duration: 0.7 }, 
                "-=0.5"
            )
            // 4. Staggered Stat Cards Pop-in Spring Animation
            .fromTo(".stat-card", 
                { y: 40, opacity: 0, scale: 0.94 }, 
                { y: 0, opacity: 1, scale: 1, duration: 0.6, stagger: 0.07, ease: "back.out(1.5)" }, 
                "-=0.4"
            )
            // 5. Grid Cards staggered entry
            .fromTo(".content-card", 
                { y: 50, opacity: 0 }, 
                { y: 0, opacity: 1, duration: 0.7, stagger: 0.1 }, 
                "-=0.4"
            );

            // Trigger Live Toast Notification Animation after load
            gsap.to("#toast-notification", {
                opacity: 1,
                y: 0,
                duration: 0.6,
                delay: 1.5,
                ease: "back.out(1.7)",
                onComplete: () => {
                    gsap.to("#toast-notification", {
                        opacity: 0,
                        y: 30,
                        duration: 0.5,
                        delay: 3.5,
                        ease: "power2.in"
                    });
                }
            });

            // 3D Tilt Hover Interactive Physics for Cards
            const cards = document.querySelectorAll('.stat-card, .content-card');
            cards.forEach(card => {
                card.addEventListener('mousemove', (e) => {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left - rect.width / 2;
                    const y = e.clientY - rect.top - rect.height / 2;
                    
                    gsap.to(card, {
                        rotationY: x * 0.015,
                        rotationX: -y * 0.015,
                        transformPerspective: 1000,
                        duration: 0.3,
                        ease: "power2.out"
                    });
                });

                card.addEventListener('mouseleave', () => {
                    gsap.to(card, {
                        rotationY: 0,
                        rotationX: 0,
                        duration: 0.5,
                        ease: "elastic.out(1, 0.5)"
                    });
                });
            });

            // Counter Engine Animation
            const counters = document.querySelectorAll('.counter');
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                gsap.to(counter, {
                    innerText: target,
                    duration: 2.2,
                    snap: { innerText: 1 },
                    ease: "power2.out",
                    delay: 0.4,
                    modifiers: {
                        innerText: function(innerText) {
                            return Math.round(innerText).toLocaleString();
                        }
                    }
                });
            });

            // Interactive Ambient Glow Parallax Movement
            window.addEventListener('mousemove', (e) => {
                const xPos = (e.clientX / window.innerWidth - 0.5) * 50;
                const yPos = (e.clientY / window.innerHeight - 0.5) * 50;
                gsap.to('.ambient-glow-1', { x: xPos, y: yPos, duration: 1, ease: "power2.out" });
                gsap.to('.ambient-glow-2', { x: -xPos, y: -yPos, duration: 1, ease: "power2.out" });
            });

            // Chart Setup with Ultra-Smooth Gradient Render
            const weeklyData = <?= $chart_data_json; ?>;
            const ctx = document.getElementById('revenueChart').getContext('2d');
            
            let gradient = ctx.createLinearGradient(0, 0, 0, 250);
            gradient.addColorStop(0, 'rgba(124, 58, 237, 0.25)');
            gradient.addColorStop(1, 'rgba(124, 58, 237, 0.0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                    datasets: [{
                        label: 'Revenue (৳)',
                        data: weeklyData,
                        borderColor: '#7c3aed',
                        backgroundColor: gradient,
                        borderWidth: 3.5,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#7c3aed',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 9
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#c084fc',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: '#f8fafc' },
                            ticks: { color: '#64748b', font: { family: "'Plus Jakarta Sans', sans-serif" } }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { color: '#64748b', font: { family: "'Plus Jakarta Sans', sans-serif" } }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>