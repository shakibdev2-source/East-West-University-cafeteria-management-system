<?php
session_start();

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: ../login.php");
    exit();
}

if (file_exists('../config.php')) {
    include '../config.php';
} elseif (file_exists('../db.php')) {
    include '../db.php';
} elseif (file_exists('../db_connect.php')) {
    include '../db_connect.php';
} else {
    $conn = mysqli_connect("localhost", "root", "", "campusbite_db");
}

$total_foods = 0;
$total_orders = 0;
$total_cart = 0;

if ($conn) {
    $foods_q = mysqli_query($conn, "SELECT * FROM foods");
    if ($foods_q) { $total_foods = mysqli_num_rows($foods_q); }

    $orders_q = mysqli_query($conn, "SELECT * FROM orders");
    if ($orders_q) { $total_orders = mysqli_num_rows($orders_q); }

    $cart_q = mysqli_query($conn, "SELECT * FROM cart");
    if ($cart_q) { $total_cart = mysqli_num_rows($cart_q); }
}

$recent_orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY order_id DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusBite | Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
        
        .sidebar { width: 240px; min-width: 240px; background: #ffffff; padding: 20px; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; }
        .brand { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: bold; color: #4f46e5; margin-bottom: 30px; }
        .brand i { background: #4f46e5; color: #fff; padding: 8px; border-radius: 8px; font-size: 16px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 10px 14px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 500; border-radius: 8px; transition: 0.2s; }
        .nav-item.active a, .nav-item a:hover { background: #4f46e5; color: #ffffff; }
        .logout-link { text-decoration: none; color: #ef4444; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; padding: 10px; }

        .main-content { flex: 1; padding: 25px 35px; overflow-y: auto; }
        .container { max-width: 1250px; margin: 0 auto; }

        .welcome-card { background: linear-gradient(135deg, #4f46e5, #3730a3); color: white; padding: 24px; border-radius: 16px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.15); }
        .welcome-card h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .welcome-card p { opacity: 0.9; font-size: 13px; }
        .order-now-btn { background: #ffffff; color: #4f46e5; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.2s; }
        .order-now-btn:hover { background: #f1f5f9; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 25px; }
        .stat-card { background: #ffffff; padding: 18px; border-radius: 14px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
        .stat-info div { font-size: 12px; color: #64748b; font-weight: 600; }
        .stat-info h2 { font-size: 22px; font-weight: 800; color: #0f172a; margin-top: 4px; }
        .stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        
        .icon-purple { background: #e0e7ff; color: #4f46e5; }
        .icon-green { background: #d1fae5; color: #059669; }
        .icon-orange { background: #ffedd5; color: #ea580c; }
        .icon-blue { background: #e0f2fe; color: #0284c7; }

        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .card-box { background: #ffffff; border-radius: 14px; padding: 20px; border: 1px solid #e2e8f0; }
        .section-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }

        .quick-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .quick-card { background: #f8fafc; padding: 14px; border-radius: 10px; border: 1px solid #f1f5f9; text-decoration: none; color: inherit; transition: 0.2s; display: flex; align-items: center; gap: 12px; }
        .quick-card:hover { background: #e0e7ff; border-color: #c7d2fe; }
        .quick-card i { font-size: 20px; color: #4f46e5; }
        .quick-card h3 { font-size: 13px; font-weight: 700; }
        .quick-card p { font-size: 11px; color: #64748b; margin-top: 2px; }

        .table-container { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { text-align: left; padding: 10px 12px; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
        th { color: #64748b; font-weight: 600; background: #f8fafc; }
        .status-pill { padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; display: inline-block; text-transform: capitalize; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-completed { background: #d1fae5; color: #059669; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div>
            <div class="brand">
                <i class="fa-solid fa-utensils"></i> CampusBite
            </div>
            <ul class="nav-menu">
                <li class="nav-item active"><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item"><a href="menu.php"><i class="fa-solid fa-list"></i> Food Menu</a></li>
                <li class="nav-item"><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> My Cart</a></li>
                <li class="nav-item"><a href="checkout.php"><i class="fa-solid fa-credit-card"></i> Checkout</a></li>
                <li class="nav-item"><a href="orders.php"><i class="fa-solid fa-clock-rotate-left"></i> Orders</a></li>
                <li class="nav-item"><a href="feedback.php"><i class="fa-solid fa-comment-dots"></i> Feedback</a></li>
            </ul>
        </div>
        <a href="../logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="welcome-card">
                <div>
                    <h1>Welcome back, Student! 👋</h1>
                    <p>Hungry? Explore today's fresh campus meal menu and order now.</p>
                </div>
                <a href="menu.php" class="order-now-btn"><i class="fa-solid fa-burger"></i> Order Food</a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <div>Total Menu Items</div>
                        <h2><?= $total_foods; ?></h2>
                    </div>
                    <div class="stat-icon icon-purple"><i class="fa-solid fa-utensils"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <div>Cart Items</div>
                        <h2><?= $total_cart; ?></h2>
                    </div>
                    <div class="stat-icon icon-green"><i class="fa-solid fa-cart-shopping"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <div>Total Orders</div>
                        <h2><?= $total_orders; ?></h2>
                    </div>
                    <div class="stat-icon icon-orange"><i class="fa-solid fa-bag-shopping"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <div>Account Status</div>
                        <h2>Active</h2>
                    </div>
                    <div class="stat-icon icon-blue"><i class="fa-solid fa-circle-check"></i></div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card-box">
                    <div class="section-title">
                        <span>Recent Order Status</span>
                        <a href="orders.php" style="font-size: 12px; color: #4f46e5; text-decoration: none;">View All</a>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Qty</th>
                                    <th>Table</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_orders && mysqli_num_rows($recent_orders) > 0): ?>
                                    <?php while($row = mysqli_fetch_assoc($recent_orders)): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($row['order_id']); ?></td>
                                            <td><?= htmlspecialchars($row['quantity']); ?></td>
                                            <td><?= htmlspecialchars($row['table_number'] ?? 'N/A'); ?></td>
                                            <td>৳<?= htmlspecialchars($row['total_price']); ?></td>
                                            <td>
                                                <span class="status-pill <?= strtolower($row['status']) == 'completed' ? 'status-completed' : 'status-pending'; ?>">
                                                    <?= htmlspecialchars($row['status'] ?? 'Pending'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: #94a3b8; padding: 15px;">No recent orders placed yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-box">
                    <div class="section-title">Quick Actions</div>
                    <div class="quick-grid">
                        <a href="menu.php" class="quick-card">
                            <i class="fa-solid fa-list"></i>
                            <div>
                                <h3>Food Menu</h3>
                                <p>Explore Items</p>
                            </div>
                        </a>

                        <a href="cart.php" class="quick-card">
                            <i class="fa-solid fa-cart-shopping"></i>
                            <div>
                                <h3>My Cart</h3>
                                <p>View Selected</p>
                            </div>
                        </a>

                        <a href="orders.php" class="quick-card">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <div>
                                <h3>History</h3>
                                <p>Track Status</p>
                            </div>
                        </a>

                        <a href="feedback.php" class="quick-card">
                            <i class="fa-solid fa-comment-dots"></i>
                            <div>
                                <h3>Feedback</h3>
                                <p>Review Us</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>