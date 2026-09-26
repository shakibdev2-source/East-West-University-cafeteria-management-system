<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$food_count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM foods");
$total_foods = mysqli_fetch_assoc($food_count_query)['total'] ?? 0;

$order_count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders");
$total_orders = mysqli_fetch_assoc($order_count_query)['total'] ?? 0;

$sales_query = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM orders WHERE status != 'Cancelled'");
$total_sales = mysqli_fetch_assoc($sales_query)['total'] ?? 0;

$user_count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
$total_users = mysqli_fetch_assoc($user_count_query)['total'] ?? 0;

$today_sales_query = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM orders WHERE status != 'Cancelled' AND DATE(created_at) = CURDATE()");
$today_sales = mysqli_fetch_assoc($today_sales_query)['total'] ?? 0;

$completed_rev_q = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM orders WHERE status = 'Completed'");
$completed_revenue = mysqli_fetch_assoc($completed_rev_q)['total'] ?? 0;

$pending_rev_q = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM orders WHERE status = 'Pending'");
$pending_revenue = mysqli_fetch_assoc($pending_rev_q)['total'] ?? 0;

$aov_query = mysqli_query($conn, "SELECT AVG(total_amount) as avg_val FROM orders WHERE status != 'Cancelled'");
$avg_order_value = mysqli_fetch_assoc($aov_query)['avg_val'] ?? 0;

$chart_dates = [];
$chart_amounts = [];
for ($i = 6; $i >= 0; $i--) {
    $date_str = date('Y-m-d', strtotime("-$i days"));
    $chart_dates[] = date('d M', strtotime($date_str));
    
    $daily_q = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM orders WHERE status != 'Cancelled' AND DATE(created_at) = '$date_str'");
    $daily_r = mysqli_fetch_assoc($daily_q);
    $chart_amounts[] = $daily_r['total'] ?? 0;
}

$top_customers_q = mysqli_query($conn, "SELECT u.full_name as name, SUM(o.total_amount) as spent, COUNT(o.order_id) as order_count FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status != 'Cancelled' GROUP BY u.id ORDER BY spent DESC LIMIT 4");

$status_filter = $_GET['status'] ?? 'all';
$date_range = $_GET['date_range'] ?? 'all';
$search_query = $_GET['search'] ?? '';

$query = "SELECT o.*, u.full_name as name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1";

if ($status_filter != 'all') {
    $status_esc = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND o.status = '$status_esc'";
}

if ($date_range == 'today') {
    $query .= " AND DATE(o.created_at) = CURDATE()";
} elseif ($date_range == 'week') {
    $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
} elseif ($date_range == 'month') {
    $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
} elseif ($date_range == 'year') {
    $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
}

if (!empty($search_query)) {
    $search_esc = mysqli_real_escape_string($conn, $search_query);
    $query .= " AND (u.full_name LIKE '%$search_esc%' OR o.order_id LIKE '%$search_esc%')";
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;

$total_query = mysqli_query($conn, str_replace("SELECT o.*, u.full_name as name", "SELECT COUNT(*) as count", $query));
$total_row = mysqli_fetch_assoc($total_query);
$total_records = $total_row['count'] ?? 0;
$total_pages = ceil($total_records / $limit);

$query .= " ORDER BY o.order_id DESC LIMIT $start, $limit";
$reports_query = mysqli_query($conn, $query);

if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=CampusBite_Advanced_BI_Report.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Order ID', 'Customer', 'Total Amount', 'Status', 'Date & Time'));
    $export_result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($export_result)) {
        fputcsv($output, array($row['order_id'], $row['name'], $row['total_amount'], $row['status'], $row['created_at']));
    }
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> BI Analytics & Reports | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

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
        .brand-icon { background: linear-gradient(135deg, #6366f1, #a855f7); width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.25); }
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 14px; padding: 13px 18px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 14px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item.active a { background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1)); color: #7c3aed; border: 1px solid rgba(168, 85, 247, 0.2); font-weight: 700; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.08); }
        .nav-item.active a i { color: #7c3aed; }
        .nav-item a:hover { background: #f1f5f9; color: #0f172a; transform: translateX(6px); }

        .main-content { flex: 1; margin-left: 280px; padding: 40px 50px; opacity: 0; background: radial-gradient(circle at top right, rgba(168, 85, 247, 0.08) 0%, rgba(248, 250, 252, 1) 60%); min-height: 100vh; }

        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            padding: 24px 32px; 
            border-radius: 24px; 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02); 
        }
        .page-title h1 { font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }
        .page-title p { font-size: 13px; color: #64748b; margin-top: 4px; font-weight: 500; }

        .header-actions { display: flex; gap: 12px; }
        .btn-action { background: #0f172a; color: #fff; border: none; padding: 11px 20px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; text-decoration: none; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2); }
        .btn-action:hover { background: #1e293b; transform: translateY(-2px); }
        .btn-export { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25); }
        .btn-export:hover { background: linear-gradient(135deg, #059669, #047857); }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 24px 48px rgba(0, 0, 0, 0.06); }
        .stat-icon { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .stat-info span { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; }
        .stat-info h3 { font-size: 22px; font-weight: 800; color: #0f172a; margin-top: 4px; }

        .filter-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            padding: 24px 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.03);
            margin-bottom: 25px;
        }
        .filter-form { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 180px; }
        .filter-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; }
        .filter-control { padding: 11px 16px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 13px; color: #0f172a; outline: none; transition: all 0.3s ease; width: 100%; }
        .filter-control:focus { border-color: #a855f7; background: #fff; box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1); }
        
        .btn-filter { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border: none; padding: 11px 24px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 8px 16px rgba(99, 102, 241, 0.25); height: 43px; display: flex; align-items: center; gap: 8px; }
        .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 12px 20px rgba(99, 102, 241, 0.35); }
        .btn-reset { background: #f1f5f9; color: #64748b; border: 1.5px solid #e2e8f0; padding: 11px 20px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; height: 43px; transition: all 0.3s ease; }
        .btn-reset:hover { background: #e2e8f0; color: #0f172a; }

        .analytics-section { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 25px; }
        .chart-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 24px; border: 1px solid rgba(226, 232, 240, 0.9); padding: 24px 32px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.03); }
        .chart-card h3 { font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .leaderboard-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 24px; border: 1px solid rgba(226, 232, 240, 0.9); padding: 24px 32px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.03); margin-bottom: 25px; }
        .leaderboard-card h3 { font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .table-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            padding: 24px 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        .table-card h3 { font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; justify-content: space-between; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; padding: 16px 14px; border-bottom: 2px solid #f1f5f9; }
        td { padding: 18px 14px; font-size: 14px; color: #1e293b; border-bottom: 1px solid #f8fafc; font-weight: 500; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(248, 250, 252, 0.6); }
        .no-data { text-align: center; padding: 40px; color: #94a3b8; font-weight: 600; font-size: 14px; }

        .badge-status { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge-completed { background: rgba(16, 185, 129, 0.1); color: #059669; }
        .badge-pending { background: rgba(245, 158, 11, 0.1); color: #d97706; }
        .badge-cancelled { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

        .pagination { display: flex; justify-content: flex-end; align-items: center; gap: 6px; margin-top: 24px; }
        .page-link { padding: 8px 14px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; font-size: 13px; font-weight: 700; color: #64748b; text-decoration: none; transition: all 0.3s ease; }
        .page-link.active, .page-link:hover { background: #7c3aed; color: #fff; border-color: #7c3aed; }

        @media print {
            .sidebar, .top-header, .filter-card, .header-actions, .pagination { display: none !important; }
            .main-content { margin: 0; padding: 0; background: #fff !important; }
            .table-card, .leaderboard-card { box-shadow: none; border: none; padding: 0; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                EWU cafeteria
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item"><a href="add_food.php"><i class="fa-solid fa-circle-plus"></i> Add Food</a></li>
                <li class="nav-item"><a href="manage_food.php"><i class="fa-solid fa-bowl-food"></i> Manage Food</a></li>
                <li class="nav-item"><a href="manage_orders.php"><i class="fa-solid fa-receipt"></i> Manage Orders</a></li>
                <li class="nav-item"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item"><a href="staff_attendance.php"><i class="fa-solid fa-clipboard-user"></i> Staff Attendance</a></li>
                <li class="nav-item active"><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li class="nav-item"><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
            </ul>
        </div>
        <div class="nav-menu">
            <li class="nav-item"><a href="../logout.php" style="color:#dc2626;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-chart-line" style="color:#7c3aed;"></i>  BI Analytics & Reports</h1>
                <p></p>
            </div>
            <div class="header-actions">
                <a href="reports.php?status=<?= $status_filter; ?>&date_range=<?= $date_range; ?>&search=<?= $search_query; ?>&export=csv" class="btn-action btn-export"><i class="fa-solid fa-file-excel"></i> Export CSV</a>
                <button onclick="window.print()" class="btn-action"><i class="fa-solid fa-print"></i> Print</button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fa-solid fa-wallet"></i></div>
                <div class="stat-info">
                    <span>Total Net Revenue</span>
                    <h3>৳<?= number_format($total_sales, 2); ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;"><i class="fa-solid fa-coins"></i></div>
                <div class="stat-info">
                    <span>Today's Revenue</span>
                    <h3>৳<?= number_format($today_sales, 2); ?></h3>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="stat-info">
                    <span>Average Order Value</span>
                    <h3>৳<?= number_format($avg_order_value, 2); ?></h3>
                </div>
            </div>
        </div>

        <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); margin-bottom: 20px;">
            <div class="stat-card" style="padding: 18px;">
                <div class="stat-info">
                    <span>Completed Rev</span>
                    <h3 style="font-size: 17px; color: #059669;">৳<?= number_format($completed_revenue, 2); ?></h3>
                </div>
            </div>
            <div class="stat-card" style="padding: 18px;">
                <div class="stat-info">
                    <span>Pending Rev</span>
                    <h3 style="font-size: 17px; color: #d97706;">৳<?= number_format($pending_revenue, 2); ?></h3>
                </div>
            </div>
            <div class="stat-card" style="padding: 18px;">
                <div class="stat-info">
                    <span>Total Foods</span>
                    <h3 style="font-size: 17px;"><?= $total_foods; ?></h3>
                </div>
            </div>
            <div class="stat-card" style="padding: 18px;">
                <div class="stat-info">
                    <span>Total Orders</span>
                    <h3 style="font-size: 17px;"><?= $total_orders; ?></h3>
                </div>
            </div>
            <div class="stat-card" style="padding: 18px;">
                <div class="stat-info">
                    <span>Total Users</span>
                    <h3 style="font-size: 17px;"><?= $total_users; ?></h3>
                </div>
            </div>
        </div>

        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label class="filter-label">Search Query</label>
                    <input type="text" name="search" class="filter-control" placeholder="Customer name or ID..." value="<?= htmlspecialchars($search_query); ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Order Status</label>
                    <select name="status" class="filter-control">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Completed" <?= $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?= $status_filter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Timeline Range</label>
                    <select name="date_range" class="filter-control">
                        <option value="all" <?= $date_range == 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?= $date_range == 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?= $date_range == 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?= $date_range == 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="year" <?= $date_range == 'year' ? 'selected' : ''; ?>>This Year</option>
                    </select>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Apply</button>
                    <a href="reports.php" class="btn-reset">Reset</a>
                </div>
            </form>
        </div>

        <div class="analytics-section">
            <div class="chart-card">
                <h3><i class="fa-solid fa-chart-area" style="color:#7c3aed;"></i> Last 7 Days Revenue Trend</h3>
                <canvas id="performanceChart" height="110"></canvas>
            </div>
            <div class="chart-card">
                <h3><i class="fa-solid fa-chart-pie" style="color:#7c3aed;"></i> Ecosystem Share</h3>
                <canvas id="metricsChart" height="210"></canvas>
            </div>
        </div>

        <div class="leaderboard-card">
            <h3><i class="fa-solid fa-crown" style="color:#f59e0b;"></i> Top Performing Customers (Leaderboard)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Total Orders</th>
                        <th>Total Lifetime Spent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($top_customers_q && mysqli_num_rows($top_customers_q) > 0): ?>
                        <?php while ($tc = mysqli_fetch_assoc($top_customers_q)): ?>
                            <tr>
                                <td style="font-weight: 700; color: #0f172a;"><i class="fa-solid fa-user-check" style="color:#10b981; margin-right: 8px;"></i> <?= htmlspecialchars($tc['name'] ?? 'N/A'); ?></td>
                                <td style="font-weight: 700; color: #6366f1;"><?= $tc['order_count']; ?> Orders</td>
                                <td style="font-weight: 700; color: #059669;">৳<?= number_format($tc['spent'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="no-data">No customer data available yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <h3>
                <span><i class="fa-solid fa-clock-rotate-left" style="color:#7c3aed;"></i> Detailed Audit Log</span>
                <span style="font-size: 12px; color: #64748b; font-weight: 600;">Total Records: <?= $total_records; ?></span>
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reports_query && mysqli_num_rows($reports_query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($reports_query)): ?>
                            <?php 
                                $badge_class = 'badge-pending';
                                if ($row['status'] == 'Completed' || $row['status'] == 'Delivered') $badge_class = 'badge-completed';
                                if ($row['status'] == 'Cancelled') $badge_class = 'badge-cancelled';
                            ?>
                            <tr>
                                <td style="font-weight: 700; color: #7c3aed;">#<?= $row['order_id']; ?></td>
                                <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['name'] ?? 'N/A'); ?></td>
                                <td style="font-weight: 700; color: #059669;">৳<?= number_format($row['total_amount'], 2); ?></td>
                                <td><span class="badge-status <?= $badge_class; ?>"><?= htmlspecialchars($row['status']); ?></span></td>
                                <td style="color: #64748b; font-size: 13px;"><?= htmlspecialchars($row['created_at'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-data"><i class="fa-regular fa-folder-open" style="font-size: 24px; display: block; margin-bottom: 8px;"></i> No analytical data found matching current parameters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="reports.php?status=<?= $status_filter; ?>&date_range=<?= $date_range; ?>&search=<?= $search_query; ?>&page=<?= $i; ?>" class="page-link <?= $page == $i ? 'active' : ''; ?>"><?= $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            gsap.to("#mainContent", { opacity: 1, duration: 0.8, y: 0, ease: "power3.out", startAt: { y: 20 } });

            const ctx1 = document.getElementById('performanceChart').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_dates); ?>,
                    datasets: [{
                        label: 'Daily Sales (৳)',
                        data: <?= json_encode($chart_amounts); ?>,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.08)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
                }
            });

            const ctx2 = document.getElementById('metricsChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Foods', 'Orders', 'Users'],
                    datasets: [{
                        data: [<?= $total_foods; ?>, <?= $total_orders; ?>, <?= $total_users; ?>],
                        backgroundColor: ['#6366f1', '#10b981', '#a855f7'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        });
    </script>
</body>
</html>