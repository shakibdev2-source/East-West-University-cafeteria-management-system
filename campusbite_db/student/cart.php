<?php
session_start();

// ১. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// ২. ইউজার অথেন্টিকেশন (ডাটাবেজে user_id = 3 আছে)
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3;

// ৩. কার্ট আপডেট ও ডিলিট অ্যাকশন
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $cart_id = intval($_POST['cart_id'] ?? 0);
    $action  = $_POST['action'];

    if ($cart_id > 0) {
        if ($action === 'increase') {
            $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = quantity + 1 WHERE cart_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
            mysqli_stmt_execute($stmt);
        } elseif ($action === 'decrease') {
            $stmt = mysqli_prepare($conn, "SELECT quantity FROM cart WHERE cart_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);

            if ($row && $row['quantity'] > 1) {
                $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = quantity - 1 WHERE cart_id = ? AND user_id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
                mysqli_stmt_execute($stmt);
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
                mysqli_stmt_execute($stmt);
            }
        } elseif ($action === 'remove') {
            $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
            mysqli_stmt_execute($stmt);
        }
    }
    header("Location: cart.php");
    exit();
}

// ৪. অনলাইন ও লোকাল ফাইল হ্যান্ডলিংয়ের জন্য স্মার্ট ইমেজ ফাংশন
function getFoodImage($img_name, $food_name) {
    if (empty($img_name)) {
        return "https://placehold.co/120x120/e2e8f0/4f46e5?text=" . urlencode($food_name);
    }

    // ইমেজ যদি অনলাইন URL হয় (যেমন Unsplash)
    if (strpos($img_name, 'http://') === 0 || strpos($img_name, 'https://') === 0) {
        return $img_name;
    }

    // লোকাল ফাইল চেক করা (assets/images/)
    $clean_file = basename($img_name);
    $web_path = '../assets/images/' . $clean_file;
    $sys_path = __DIR__ . '/../assets/images/' . $clean_file;

    if (file_exists($sys_path)) {
        return $web_path;
    }
    
    // এক্সটেনশন ছাড়া থাকলে (যেমন: cappuccino -> cappuccino.jpg)
    if (file_exists($sys_path . '.jpg')) {
        return $web_path . '.jpg';
    }

    return "https://placehold.co/120x120/e2e8f0/4f46e5?text=" . urlencode($food_name);
}

// ৫. কার্ট ডাটা ফেচ (food_id দিয়ে নিখুঁত JOIN)
$cart_query = "SELECT c.cart_id, c.quantity, f.food_name, f.price, f.image, f.category 
                FROM cart c 
                JOIN foods f ON c.food_id = f.food_id
                WHERE c.user_id = ?";
$stmt = mysqli_prepare($conn, $cart_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$cart_result = mysqli_stmt_get_result($stmt);

$total_items_count = 0;
$grand_total = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #1e293b; min-height: 100vh; }

        .sidebar { width: 240px; background: #ffffff; padding: 24px 20px; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; }
        .brand { font-size: 22px; font-weight: 800; color: #4f46e5; display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 10px; transition: 0.2s; }
        .nav-item.active a, .nav-item a:hover { background: #4f46e5; color: #ffffff; }
        .badge { background: #ef4444; color: #fff; font-size: 11px; padding: 2px 7px; border-radius: 10px; margin-left: auto; }

        .main-content { flex: 1; padding: 35px 45px; overflow-y: auto; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-title { display: flex; align-items: center; gap: 12px; font-size: 28px; font-weight: 800; color: #0f172a; }
        .page-title i { color: #4f46e5; }

        .btn-back { display: flex; align-items: center; gap: 8px; text-decoration: none; color: #475569; background: #ffffff; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .btn-back:hover { background: #f1f5f9; }

        .cart-grid { display: grid; grid-template-columns: 1fr 340px; gap: 28px; align-items: start; }
        .cart-card { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th { text-align: left; font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9; }
        .cart-table td { padding: 18px 0; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

        .item-cell { display: flex; align-items: center; gap: 14px; }
        .food-thumb { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; background: #f1f5f9; border: 1px solid #f1f5f9; }
        .item-name { font-weight: 700; font-size: 15px; color: #0f172a; }
        .item-cat { font-size: 12px; color: #94a3b8; margin-top: 2px; }

        .price-text { font-weight: 800; font-size: 15px; color: #0f172a; }
        .subtotal-text { font-weight: 800; font-size: 15px; color: #059669; }

        .qty-control { display: flex; align-items: center; background: #f1f5f9; border-radius: 8px; width: fit-content; padding: 2px; }
        .qty-btn { border: none; background: transparent; width: 28px; height: 28px; font-weight: bold; cursor: pointer; color: #475569; border-radius: 6px; transition: 0.2s; }
        .qty-btn:hover { background: #e2e8f0; color: #0f172a; }
        .qty-val { width: 28px; text-align: center; font-size: 13px; font-weight: 700; }

        .btn-trash { border: none; background: transparent; color: #94a3b8; font-size: 16px; cursor: pointer; transition: 0.2s; }
        .btn-trash:hover { color: #ef4444; }

        .summary-card { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; }
        .summary-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 20px; }
        .summary-row { display: flex; justify-content: space-between; font-size: 14px; color: #64748b; margin-bottom: 14px; font-weight: 600; }
        .summary-row.total { font-size: 20px; font-weight: 800; color: #0f172a; border-top: 1px dashed #e2e8f0; padding-top: 16px; margin-top: 16px; }
        .grand-price { color: #059669; }

        .btn-checkout { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: #4f46e5; color: #ffffff; border: none; padding: 14px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; margin-top: 20px; transition: 0.2s; }
        .btn-checkout:hover { background: #4338ca; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div>
            <div class="brand"><i class="fa-solid fa-utensils"></i> CampusBite</div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item"><a href="menu.php"><i class="fa-solid fa-list"></i> Food Menu</a></li>
                <li class="nav-item active"><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> My Cart <span class="badge" id="cartCount">0</span></a></li>
                <li class="nav-item"><a href="checkout.php"><i class="fa-solid fa-credit-card"></i> Checkout</a></li>
                <li class="nav-item"><a href="orders.php"><i class="fa-solid fa-clock-rotate-left"></i> Orders</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="header-bar">
            <div class="page-title"><i class="fa-solid fa-bag-shopping"></i> Shopping Cart</div>
            <a href="menu.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Menu</a>
        </div>

        <div class="cart-grid">
            <div class="cart-card">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cart_result && mysqli_num_rows($cart_result) > 0): ?>
                            <?php while ($item = mysqli_fetch_assoc($cart_result)): ?>
                                <?php 
                                    $quantity = intval($item['quantity']);
                                    $price = floatval($item['price']);
                                    $subtotal = $price * $quantity;
                                    
                                    $total_items_count += $quantity;
                                    $grand_total += $subtotal;

                                    $img_src = getFoodImage($item['image'], $item['food_name']);
                                ?>
                                <tr>
                                    <td>
                                        <div class="item-cell">
                                            <img src="<?= htmlspecialchars($img_src); ?>" alt="<?= htmlspecialchars($item['food_name']); ?>" class="food-thumb">
                                            <div>
                                                <div class="item-name"><?= htmlspecialchars($item['food_name']); ?></div>
                                                <div class="item-cat"><?= htmlspecialchars($item['category'] ?? 'General'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="price-text">৳<?= number_format($price, 2); ?></td>
                                    <td>
                                        <div class="qty-control">
                                            <form method="POST" action="cart.php" style="display:inline;">
                                                <input type="hidden" name="cart_id" value="<?= $item['cart_id']; ?>">
                                                <input type="hidden" name="action" value="decrease">
                                                <button type="submit" class="qty-btn">-</button>
                                            </form>
                                            <span class="qty-val"><?= $quantity; ?></span>
                                            <form method="POST" action="cart.php" style="display:inline;">
                                                <input type="hidden" name="cart_id" value="<?= $item['cart_id']; ?>">
                                                <input type="hidden" name="action" value="increase">
                                                <button type="submit" class="qty-btn">+</button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="subtotal-text">৳<?= number_format($subtotal, 2); ?></td>
                                    <td>
                                        <form method="POST" action="cart.php">
                                            <input type="hidden" name="cart_id" value="<?= $item['cart_id']; ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <button type="submit" class="btn-trash" onclick="return confirm('Are you sure you want to remove this item?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 50px;">Your cart is currently empty.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="summary-card">
                <div class="summary-title">Order Summary</div>
                <div class="summary-row">
                    <span>Total Items</span>
                    <span><?= $total_items_count; ?> Pcs</span>
                </div>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>৳<?= number_format($grand_total, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Campus Delivery</span>
                    <span style="color: #059669;">FREE</span>
                </div>
                
                <div class="summary-row total">
                    <span>Grand Total</span>
                    <span class="grand-price">৳<?= number_format($grand_total, 2); ?></span>
                </div>

                <?php if ($grand_total > 0): ?>
                    <a href="checkout.php" class="btn-checkout">Proceed to Checkout <i class="fa-solid fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('cartCount').textContent = '<?= $total_items_count; ?>';
    </script>
</body>
</html>