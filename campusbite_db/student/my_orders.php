<?php
session_start();

// ১. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// ২. ইউজার আইডি সেটিং
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3;

// ৩. ইউজারের সব অর্ডার ফেচ করা
$orders_query = "SELECT * FROM orders WHERE user_id = '$user_id' ORDER BY order_id DESC";
$orders_result = mysqli_query($conn, $orders_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #1e293b; min-height: 100vh; }

        .sidebar { width: 240px; background: #ffffff; padding: 24px 20px; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; }
        .brand { font-size: 22px; font-weight: 800; color: #4f46e5; display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 10px; transition: 0.2s; }
        .nav-item.active a, .nav-item a:hover { background: #4f46e5; color: #ffffff; }

        .main-content { flex: 1; padding: 35px 45px; overflow-y: auto; }
        .page-title { font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .page-title i { color: #4f46e5; }

        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #15803d; padding: 14px 18px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; display: flex; align-items: center; gap: 10px; font-weight: 600; }

        .order-card { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 20px 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .order-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 16px; }
        .order-id { font-weight: 800; font-size: 16px; color: #0f172a; }
        .order-date { font-size: 12px; color: #94a3b8; margin-top: 2px; }

        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .status-Pending { background: #fef3c7; color: #d97706; }
        .status-Completed { background: #dcfce7; color: #15803d; }
        .status-Cancelled { background: #fee2e2; color: #dc2626; }

        .item-list { list-style: none; margin-bottom: 16px; }
        .item-row { display: flex; justify-content: space-between; font-size: 14px; padding: 6px 0; color: #475569; }

        .order-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 14px; }
        .total-price { font-weight: 800; font-size: 16px; color: #059669; }
        .payment-type { font-size: 12px; background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-weight: 600; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div>
            <div class="brand"><i class="fa-solid fa-utensils"></i> CampusBite</div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item"><a href="menu.php"><i class="fa-solid fa-list"></i> Food Menu</a></li>
                <li class="nav-item"><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> My Cart</a></li>
                <li class="nav-item"><a href="checkout.php"><i class="fa-solid fa-credit-card"></i> Checkout</a></li>
                <li class="nav-item active"><a href="orders.php"><i class="fa-solid fa-clock-rotate-left"></i> Orders</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="page-title"><i class="fa-solid fa-clock-rotate-left"></i> My Order History</div>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> Order placed successfully! Your food will be prepared shortly.
            </div>
        <?php endif; ?>

        <?php if ($orders_result && mysqli_num_rows($orders_result) > 0): ?>
            <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                <?php $current_order_id = $order['order_id']; ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-id">Order #<?= $order['order_id']; ?></div>
                            <div class="order-date"><i class="fa-regular fa-clock"></i> <?= date('M d, Y - h:i A', strtotime($order['created_at'])); ?></div>
                        </div>
                        <span class="status-badge status-<?= $order['status']; ?>"><?= $order['status']; ?></span>
                    </div>

                    <ul class="item-list">
                        <?php
                        $items_query = "SELECT oi.quantity, oi.price, f.food_name 
                                        FROM order_items oi 
                                        JOIN foods f ON oi.food_id = f.food_id 
                                        WHERE oi.order_id = '$current_order_id'";
                        $items_result = mysqli_query($conn, $items_query);
                        ?>
                        <?php if ($items_result && mysqli_num_rows($items_result) > 0): ?>
                            <?php while ($item = mysqli_fetch_assoc($items_result)): ?>
                                <li class="item-row">
                                    <span><?= htmlspecialchars($item['food_name']); ?> <strong>x <?= $item['quantity']; ?></strong></span>
                                    <span>৳<?= number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </li>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </ul>

                    <div class="order-footer">
                        <span class="payment-type"><i class="fa-solid fa-wallet"></i> <?= htmlspecialchars($order['payment_method']); ?></span>
                        <div class="total-price">Total: ৳<?= number_format($order['total_amount'], 2); ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="background: #ffffff; padding: 50px; text-align: center; border-radius: 16px; border: 1px solid #e2e8f0; color: #94a3b8;">
                <i class="fa-solid fa-basket-shopping" style="font-size: 48px; margin-bottom: 12px; color: #cbd5e1;"></i>
                <p>You haven't placed any orders yet.</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>