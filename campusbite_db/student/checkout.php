<?php
session_start();

// ১. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// ২. ইউজার আইডি
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3;

// ইউজার এর কার্ট চেক
$cart_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM cart WHERE user_id = '$user_id'");
$cart_row = mysqli_fetch_assoc($cart_check);

if ($cart_row['total'] == 0) {
    $user_id = 3; // কার্ট খালি থাকলে ডিফল্ট আইডি ৩ ধরবে
}

$error_msg = "";

// ৩. কার্ট আইটেম রিড করা
$cart_query = "SELECT c.cart_id, c.food_id, c.quantity, f.price, f.food_name 
                FROM cart c 
                JOIN foods f ON c.food_id = f.food_id 
                WHERE c.user_id = '$user_id'";
$cart_result = mysqli_query($conn, $cart_query);

$cart_items = [];
$grand_total = 0;

if ($cart_result) {
    while ($row = mysqli_fetch_assoc($cart_result)) {
        $cart_items[] = $row;
        $grand_total += ($row['price'] * $row['quantity']);
    }
}

// ৪. অর্ডার প্লেস করা
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table_room = mysqli_real_escape_string($conn, $_POST['table_room'] ?? '');
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? 'Cash on Delivery');

    if (empty($cart_items)) {
        $error_msg = "Your cart is empty!";
    } elseif (empty($table_room)) {
        $error_msg = "Please enter Table Number or Room!";
    } else {
        // orders টেবিলে ডেটা ইনসার্ট
        $order_sql = "INSERT INTO orders (user_id, total_amount, payment_method, status) 
                      VALUES ('$user_id', '$grand_total', '$payment_method', 'Pending')";

        if (mysqli_query($conn, $order_sql)) {
            $order_id = mysqli_insert_id($conn);

            $item_failed = false;
            foreach ($cart_items as $item) {
                $food_id = $item['food_id'];
                $quantity = $item['quantity'];
                $price = $item['price'];

                $item_sql = "INSERT INTO order_items (order_id, food_id, quantity, price) 
                             VALUES ('$order_id', '$food_id', '$quantity', '$price')";

                if (!mysqli_query($conn, $item_sql)) {
                    $item_failed = true;
                    $error_msg = "order_items error: " . mysqli_error($conn);
                    break;
                }
            }

            if (!$item_failed) {
                mysqli_query($conn, "DELETE FROM cart WHERE user_id = '$user_id'");
                header("Location: orders.php?status=success");
                exit();
            }
        } else {
            // আসল SQL error দেখাবে
            $error_msg = "orders table error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Express Checkout | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #1e293b; min-height: 100vh; }

        .sidebar { width: 240px; background: #ffffff; padding: 24px 20px; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; }
        .brand { font-size: 22px; font-weight: 800; color: #4f46e5; display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 10px; }
        .nav-item.active a { background: #4f46e5; color: #ffffff; }

        .main-content { flex: 1; padding: 35px 45px; overflow-y: auto; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-title { font-size: 28px; font-weight: 800; color: #0f172a; }

        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }

        .checkout-grid { display: grid; grid-template-columns: 1fr 360px; gap: 28px; }
        .card { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; outline: none; }

        .payment-options { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .payment-option { border: 2px solid #e2e8f0; padding: 16px; border-radius: 12px; cursor: pointer; }
        .payment-option.active { border-color: #4f46e5; background: #eeefff; }

        .order-summary { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; }
        .summary-item { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; }
        .summary-row.total { font-size: 18px; font-weight: 800; color: #0f172a; border-top: 1px dashed #e2e8f0; padding-top: 14px; margin-top: 14px; display: flex; justify-content: space-between; }

        .btn-place-order { width: 100%; background: #059669; color: #ffffff; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 20px; }
        .btn-place-order:hover { background: #047857; }
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
                <li class="nav-item active"><a href="checkout.php"><i class="fa-solid fa-credit-card"></i> Checkout</a></li>
                <li class="nav-item"><a href="orders.php"><i class="fa-solid fa-clock-rotate-left"></i> Orders</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="header-bar">
            <h1 class="page-title"><i class="fa-solid fa-shield-halved" style="color:#4f46e5;"></i> Express Checkout</h1>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="checkout.php">
            <div class="checkout-grid">
                <div>
                    <div class="card">
                        <div class="form-group">
                            <label class="form-label">Table Number / Campus Room *</label>
                            <input type="text" name="table_room" class="form-control" placeholder="e.g. Table 12 or Room 402" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Special Instructions (Optional)</label>
                            <input type="text" name="instructions" class="form-control" placeholder="e.g. Less spicy, Extra ketchup please">
                        </div>
                    </div>

                    <div class="card">
                        <label class="form-label">Payment Method</label>
                        <div class="payment-options">
                            <label class="payment-option active">
                                <input type="radio" name="payment_method" value="Cash on Delivery" checked> Cash on Delivery
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Mobile Banking"> Mobile Banking
                            </label>
                        </div>
                    </div>
                </div>

                <div class="order-summary">
                    <h3 style="margin-bottom:15px;">Order Summary</h3>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="summary-item">
                            <span><?= htmlspecialchars($item['food_name']); ?> x <?= $item['quantity']; ?></span>
                            <strong>৳<?= number_format($item['price'] * $item['quantity'], 2); ?></strong>
                        </div>
                    <?php endforeach; ?>

                    <div class="summary-row total">
                        <span>Total Payable</span>
                        <span style="color:#059669;">৳<?= number_format($grand_total, 2); ?></span>
                    </div>

                    <button type="submit" class="btn-place-order">Place Order Now <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>
        </form>
    </div>

</body>
</html>