<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$staff_name = $_SESSION['staff_name'] ?? 'Staff Member';

if (isset($_POST['ajax_update_id']) && isset($_POST['new_status'])) {
    $up_id = intval($_POST['ajax_update_id']);
    $new_st = mysqli_real_escape_string($conn, $_POST['new_status']);
    mysqli_query($conn, "UPDATE orders SET status = '$new_st' WHERE order_id = $up_id");
    exit('success');
}

if (isset($_GET['export']) && $_GET['export'] == 'true') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=orders_report.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Order ID', 'Customer ID', 'Total Price', 'Status', 'Order Time'));
    $rows = mysqli_query($conn, "SELECT order_id, user_id, total_price, status, created_at FROM orders ORDER BY order_id DESC");
    while ($r = mysqli_fetch_assoc($rows)) {
        fputcsv($output, $r);
    }
    fclose($output);
    exit();
}

$total_orders_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders");
$total_orders = $total_orders_query ? (mysqli_fetch_assoc($total_orders_query)['total'] ?? 0) : 0;

$pending_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status = 'Pending'");
$pending_orders = $pending_query ? (mysqli_fetch_assoc($pending_query)['total'] ?? 0) : 0;

$processing_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status = 'Processing'");
$processing_orders = $processing_query ? (mysqli_fetch_assoc($processing_query)['total'] ?? 0) : 0;

$completed_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status = 'Completed'");
$completed_orders = $completed_query ? (mysqli_fetch_assoc($completed_query)['total'] ?? 0) : 0;

$revenue_query = mysqli_query($conn, "SELECT SUM(total_price) as revenue FROM orders");
$revenue_row = $revenue_query ? mysqli_fetch_assoc($revenue_query) : null;
$total_revenue = isset($revenue_row['revenue']) ? (float)$revenue_row['revenue'] : 0.00;

$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$query = "SELECT * FROM orders WHERE 1=1";
if ($status_filter !== 'all') {
    $status_esc = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND status = '$status_esc'";
}
if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " AND (order_id LIKE '%$search_esc%' OR user_id LIKE '%$search_esc%')";
}
$query .= " ORDER BY order_id DESC";
$orders_query = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate Realtime Staff Dashboard | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

        .sidebar { 
            width: 280px; background: #ffffff; border-right: 1px solid #e2e8f0;
            padding: 30px 20px; display: flex; flex-direction: column; justify-content: space-between; 
            position: fixed; height: 100vh; z-index: 100;
        }
        .brand { font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 14px; margin-bottom: 40px; padding-left: 6px; }
        .brand-icon { background: linear-gradient(135deg, #6366f1, #a855f7); width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.25); }
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 14px; padding: 13px 18px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 14px; transition: all 0.3s ease; }
        .nav-item.active a { background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1)); color: #7c3aed; border: 1px solid rgba(168, 85, 247, 0.2); font-weight: 700; }
        .nav-item a:hover { background: #f1f5f9; color: #0f172a; transform: translateX(6px); }

        .main-content { flex: 1; margin-left: 280px; padding: 40px 50px; background: radial-gradient(circle at top right, rgba(168, 85, 247, 0.08) 0%, rgba(248, 250, 252, 1) 60%); min-height: 100vh; }

        .top-header { 
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; 
            background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); padding: 22px 32px; 
            border-radius: 24px; border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 10px 30px rgba(0,0,0,0.02); 
        }
        .page-title h1 { font-size: 22px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .user-welcome { font-size: 13.5px; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 10px; }
        .live-indicator { width: 10px; height: 10px; background: #10b981; border-radius: 50%; display: inline-block; box-shadow: 0 0 10px #10b981; animation: pulse 1.5s infinite; }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 30px; }
        .stat-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); border: 1px solid rgba(226, 232, 240, 0.8); padding: 18px; border-radius: 20px; display: flex; align-items: center; gap: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff; }
        .stat-info h3 { font-size: 18px; font-weight: 800; color: #0f172a; }
        .stat-info p { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; }

        .filter-toolbar { display: flex; justify-content: space-between; align-items: center; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); padding: 16px 24px; border-radius: 20px; border: 1px solid rgba(226, 232, 240, 0.8); margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .tab-btn { padding: 9px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; text-decoration: none; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .tab-btn.active, .tab-btn:hover { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border-color: transparent; }

        .toolbar-right { display: flex; gap: 12px; align-items: center; }
        .export-btn { background: #10b981; color: #fff; padding: 10px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: background 0.3s; }
        .export-btn:hover { background: #059669; }

        .search-box { display: flex; align-items: center; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 6px 16px; }
        .search-box input { border: none; outline: none; background: transparent; padding: 4px; font-size: 13.5px; color: #0f172a; width: 180px; }
        .search-box button { background: none; border: none; color: #7c3aed; cursor: pointer; }

        .table-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 24px; border: 1px solid rgba(226, 232, 240, 0.9); padding: 24px 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; padding: 16px 14px; border-bottom: 2px solid #f1f5f9; }
        td { padding: 18px 14px; font-size: 14px; color: #1e293b; border-bottom: 1px solid #f8fafc; font-weight: 500; }
        tr:hover td { background: rgba(248, 250, 252, 0.6); }
        .no-data { text-align: center; padding: 50px; color: #94a3b8; font-weight: 600; }

        .status-select { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; border: 1px solid #cbd5e1; outline: none; cursor: pointer; background: #fff; }
        .action-badge { padding: 5px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; background: #f1f5f9; color: #475569; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; }
        .action-badge:hover { background: #e2e8f0; }
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
                <li class="nav-item active"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
            </ul>
        </div>
        <div class="nav-menu">
            <li class="nav-item"><a href="../logout.php" style="color:#dc2626;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-chart-pie" style="color:#7c3aed;"></i> Realtime Advanced Staff Dashboard</h1>
            </div>
            <div class="user-welcome">
                <span class="live-indicator"></span> Welcome, <span style="color:#7c3aed; font-weight:700;"><?= htmlspecialchars($staff_name); ?></span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #a855f7);"><i class="fa-solid fa-shopping-bag"></i></div>
                <div class="stat-info">
                    <h3><?= $total_orders; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?= $pending_orders; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);"><i class="fa-solid fa-spinner"></i></div>
                <div class="stat-info">
                    <h3><?= $processing_orders; ?></h3>
                    <p>Processing</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="fa-solid fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?= $completed_orders; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ec4899, #be185d);"><i class="fa-solid fa-wallet"></i></div>
                <div class="stat-info">
                    <h3>৳<?= number_format($total_revenue, 2); ?></h3>
                    <p>Revenue</p>
                </div>
            </div>
        </div>

        <div class="filter-toolbar">
            <div class="filter-tabs">
                <a href="dashboard.php?status=all" class="tab-btn <?= $status_filter === 'all' ? 'active' : ''; ?>">All Orders</a>
                <a href="dashboard.php?status=Pending" class="tab-btn <?= $status_filter === 'Pending' ? 'active' : ''; ?>">Pending</a>
                <a href="dashboard.php?status=Processing" class="tab-btn <?= $status_filter === 'Processing' ? 'active' : ''; ?>">Processing</a>
                <a href="dashboard.php?status=Completed" class="tab-btn <?= $status_filter === 'Completed' ? 'active' : ''; ?>">Completed</a>
            </div>
            <div class="toolbar-right">
                <a href="dashboard.php?export=true" class="export-btn"><i class="fa-solid fa-file-excel"></i> Export CSV</a>
                <form method="GET" class="search-box">
                    <input type="text" name="search" placeholder="Search order ID..." value="<?= htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fa-solid fa-search"></i></button>
                </form>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer ID</th>
                        <th>Total Amount</th>
                        <th>Status Action</th>
                        <th>Order Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders_query && mysqli_num_rows($orders_query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($orders_query)): 
                            $status = $row['status'] ?? 'Pending';
                        ?>
                            <tr>
                                <td style="font-weight: 700; color: #7c3aed;">#<?= $row['order_id']; ?></td>
                                <td><?= htmlspecialchars($row['user_id'] ?? 'N/A'); ?></td>
                                <td><span style="font-weight: 700; color: #059669;">৳<?= number_format((float)($row['total_price'] ?? 0), 2); ?></span></td>
                                <td>
                                    <select class="status-select" data-id="<?= $row['order_id']; ?>">
                                        <option value="Pending" <?= $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Processing" <?= $status === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="Completed" <?= $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </td>
                                <td><?= htmlspecialchars($row['created_at'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="action-badge" onclick="Swal.fire('Order Details', 'Order ID: #<?= $row['order_id']; ?>\nCustomer ID: <?= $row['user_id']; ?>\nAmount: ৳<?= $row['total_price']; ?>', 'info')">
                                        <i class="fa-solid fa-eye"></i> View
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data"><i class="fa-regular fa-folder-open" style="font-size: 24px; display: block; margin-bottom: 8px;"></i> No orders found matching your criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('.status-select').on('change', function() {
                let orderId = $(this).data('id');
                let newStatus = $(this).val();

                $.ajax({
                    url: 'dashboard.php',
                    type: 'POST',
                    data: { ajax_update_id: orderId, new_status: newStatus },
                    success: function(response) {
                        if(response.trim() === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Status Updated Realtime!',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 1500
                            });
                        }
                    }
                });
            });

            setInterval(function() {
                if (!document.hidden) {
                    location.reload();
                }
            }, 10000);
        });
    </script>
</body>
</html>