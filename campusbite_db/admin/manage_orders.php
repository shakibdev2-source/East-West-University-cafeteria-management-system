<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    mysqli_query($conn, "UPDATE orders SET status = '$status' WHERE order_id = '$order_id'");
    header("Location: manage_orders.php?success=status");
    exit();
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    mysqli_query($conn, "DELETE FROM orders WHERE order_id = '$delete_id'");
    header("Location: manage_orders.php?success=deleted");
    exit();
}

$status_filter = $_GET['status_filter'] ?? 'All';
$search_query = trim($_GET['search'] ?? '');

$query = "SELECT * FROM orders WHERE 1=1";

if ($status_filter !== 'All') {
    $status_filter_esc = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND status = '$status_filter_esc'";
}

if (!empty($search_query)) {
    $search_esc = mysqli_real_escape_string($conn, $search_query);
    $query .= " AND (order_id LIKE '%$search_esc%' OR customer_email LIKE '%$search_esc%')";
}

$query .= " ORDER BY order_id DESC";
$orders_query = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

        .sidebar { 
            width: 280px; 
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(226, 232, 240, 0.8);
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
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .nav-item a { display: flex; align-items: center; gap: 14px; padding: 14px 18px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 14px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item.active a { background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1)); color: #7c3aed; border: 1px solid rgba(168, 85, 247, 0.2); font-weight: 700; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.08); }
        .nav-item.active a i { color: #7c3aed; }
        .nav-item a:hover { background: #f1f5f9; color: #0f172a; transform: translateX(6px); }

        .main-content { flex: 1; margin-left: 280px; padding: 40px 50px; opacity: 0; background: radial-gradient(circle at top right, #ede9fe 0%, #f8fafc 60%); min-height: 100vh; }

        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(16px);
            padding: 24px 32px; 
            border-radius: 24px; 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03); 
        }
        .page-title h1 { font-size: 24px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }

        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 15px; flex-wrap: wrap; }
        .filter-tabs { display: flex; gap: 8px; background: rgba(255, 255, 255, 0.8); padding: 6px; border-radius: 16px; border: 1px solid #e2e8f0; }
        .filter-tab { padding: 8px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; text-decoration: none; color: #64748b; transition: all 0.2s; }
        .filter-tab.active { background: #7c3aed; color: #fff; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3); }

        .search-box { display: flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 6px 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .search-box input { border: none; outline: none; padding: 8px; font-size: 14px; width: 220px; color: #0f172a; }
        .search-box button { background: none; border: none; color: #7c3aed; cursor: pointer; font-size: 14px; }

        .table-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04);
        }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; padding: 15px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 18px 15px; font-size: 14px; color: #0f172a; border-bottom: 1px solid #f1f5f9; }
        tr:last-child td { border-bottom: none; }
        .no-data { text-align: center; padding: 40px; color: #94a3b8; font-weight: 600; }

        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .status-pending { background: rgba(234, 179, 8, 0.15); color: #ca8a04; }
        .status-processing { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        .status-completed { background: rgba(16, 185, 129, 0.15); color: #059669; }

        .action-form { display: flex; gap: 8px; align-items: center; }
        .form-select { padding: 8px 12px; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 12px; outline: none; background: #f8fafc; }
        .btn-update { background: #7c3aed; color: #fff; border: none; padding: 8px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .btn-update:hover { background: #6d28d9; }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: #dc2626; border: none; padding: 8px 12px; border-radius: 10px; font-size: 12px; cursor: pointer; transition: 0.2s; }
        .btn-delete:hover { background: #dc2626; color: #fff; }
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
                <li class="nav-item active"><a href="manage_orders.php"><i class="fa-solid fa-receipt"></i> Manage Orders</a></li>
                <li class="nav-item"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
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
                <h1><i class="fa-solid fa-receipt" style="color:#7c3aed;"></i> Manage Customer Orders</h1>
            </div>
        </div>

        <div class="toolbar">
            <div class="filter-tabs">
                <a href="manage_orders.php?status_filter=All" class="filter-tab <?= ($status_filter == 'All') ? 'active' : ''; ?>">All Orders</a>
                <a href="manage_orders.php?status_filter=Pending" class="filter-tab <?= ($status_filter == 'Pending') ? 'active' : ''; ?>">Pending</a>
                <a href="manage_orders.php?status_filter=Processing" class="filter-tab <?= ($status_filter == 'Processing') ? 'active' : ''; ?>">Processing</a>
                <a href="manage_orders.php?status_filter=Completed" class="filter-tab <?= ($status_filter == 'Completed') ? 'active' : ''; ?>">Completed</a>
            </div>

            <form method="GET" class="search-box">
                <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter); ?>">
                <input type="text" name="search" placeholder="Search ID or Email..." value="<?= htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fa-solid fa-search"></i></button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer / Email</th>
                        <th>Items / Details</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Update Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders_query && mysqli_num_rows($orders_query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($orders_query)): ?>
                            <?php 
                                $statusClass = 'status-pending';
                                if($row['status'] == 'Processing') $statusClass = 'status-processing';
                                if($row['status'] == 'Completed') $statusClass = 'status-completed';
                            ?>
                            <tr>
                                <td>#<?= $row['order_id']; ?></td>
                                <td><?= htmlspecialchars($row['customer_email'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($row['items'] ?? 'Details'); ?></td>
                                <td>৳<?= number_format($row['total_price'] ?? 0, 2); ?></td>
                                <td><span class="status-badge <?= $statusClass; ?>"><?= htmlspecialchars($row['status'] ?? 'Pending'); ?></span></td>
                                <td>
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="order_id" value="<?= $row['order_id']; ?>">
                                            <select name="status" class="form-select">
                                                <option value="Pending" <?= ($row['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Processing" <?= ($row['status'] == 'Processing') ? 'selected' : ''; ?>>Processing</option>
                                                <option value="Completed" <?= ($row['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn-update">Update</button>
                                        </form>
                                        <button onclick="confirmDelete(<?= $row['order_id']; ?>)" class="btn-delete" title="Delete Order"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data">No customer orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            gsap.to("#mainContent", { opacity: 1, duration: 0.9, y: 0, ease: "power3.out", startAt: { y: 25 } });
        });

        function confirmDelete(orderId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this order!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `manage_orders.php?delete_id=${orderId}`;
                }
            })
        }
    </script>
</body>
</html>