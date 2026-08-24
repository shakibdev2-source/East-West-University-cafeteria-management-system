<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

// ১. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$msg = "";

// ২. অর্ডার স্ট্যাটাস আপডেট প্রসেস
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status   = mysqli_real_escape_string($conn, $_POST['status']);

    $update_query = mysqli_query($conn, "UPDATE orders SET status = '$status' WHERE order_id = $order_id");
    if ($update_query) {
        $msg = "Order #$order_id status updated to $status!";
    }
}

// ৩. অর্ডার তথ্য ফেচ করা
$orders_result = mysqli_query($conn, "SELECT * FROM orders ORDER BY order_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | CampusBite</title>
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

        /* Main Content Panel */
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

        /* Alert */
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #15803d; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        /* Professional Card Table */
        .card-table { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        .custom-table th { background: #f8fafc; padding: 14px 18px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .custom-table td { padding: 16px 18px; font-size: 13.5px; color: #1e293b; border-bottom: 1px solid #f1f5f9; font-weight: 500; vertical-align: middle; }
        .custom-table tr:hover { background: #f8fafc; }

        /* Status Badges */
        .badge { padding: 6px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-Pending { background: #fef3c7; color: #d97706; }
        .badge-Completed { background: #dcfce7; color: #15803d; }
        .badge-Cancelled { background: #fee2e2; color: #dc2626; }

        /* Action Form Elements */
        .status-select { padding: 6px 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 12px; font-weight: 600; outline: none; background: #ffffff; }
        .btn-update { background: #4f46e5; color: #ffffff; border: none; padding: 7px 12px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; transition: background 0.2s; }
        .btn-update:hover { background: #4338ca; }
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
                <li class="nav-item active"><a href="manage_orders.php"><i class="fa-solid fa-receipt"></i> Manage Orders</a></li>
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
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-receipt" style="color:#4f46e5;"></i> Manage Customer Orders</h1>
                <p>View, update and manage customer order requests.</p>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <!-- Table View -->
        <div class="card-table">
            <table class="custom-table">
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
                    <?php if ($orders_result && mysqli_num_rows($orders_result) > 0): ?>
                        <?php while ($ord = mysqli_fetch_assoc($orders_result)): ?>
                            <tr>
                                <td><strong style="color:#4f46e5;">#<?= $ord['order_id']; ?></strong></td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($ord['customer_name'] ?? 'Student'); ?></div>
                                    <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($ord['email'] ?? $ord['user_id'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight:600; color:#334155;"><?= htmlspecialchars($ord['food_details'] ?? $ord['food_name'] ?? 'Order Items'); ?></div>
                                    <div style="font-size:11px; color:#94a3b8;">Qty: <?= $ord['quantity'] ?? 1; ?></div>
                                </td>
                                <td style="font-weight: 800; color: #059669;">৳<?= number_format($ord['total_amount'] ?? $ord['total_price'] ?? 0, 2); ?></td>
                                <td>
                                    <span class="badge badge-<?= $ord['status']; ?>"><?= $ord['status']; ?></span>
                                </td>
                                <td>
                                    <form method="POST" action="manage_orders.php" style="display: flex; gap: 6px; align-items: center;">
                                        <input type="hidden" name="order_id" value="<?= $ord['order_id']; ?>">
                                        <select name="status" class="status-select">
                                            <option value="Pending" <?= ($ord['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Completed" <?= ($ord['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled" <?= ($ord['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn-update">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #94a3b8; padding: 30px;">No customer orders placed yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>