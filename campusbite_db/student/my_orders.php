<?php
declare(strict_types=1);
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'campusbite_db');

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Database Connection Error: " . mysqli_connect_error());
}

$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 1);

// Grouping duplicate items together to fix the repeat issue
$orders_query = "SELECT o.order_id, o.total_amount, o.payment_method, o.created_at,
                        oi.food_id, SUM(oi.quantity) AS quantity, oi.price, f.food_name
                 FROM orders o
                 INNER JOIN order_items oi ON o.order_id = oi.order_id
                 LEFT JOIN foods f ON oi.food_id = f.food_id
                 WHERE o.user_id = ?
                 GROUP BY o.order_id, oi.food_id
                 ORDER BY o.created_at DESC";

$stmt = mysqli_prepare($conn, $orders_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$orders = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $oid = $row['order_id'];
        if (!isset($orders[$oid])) {
            $orders[$oid] = [
                'order_id'       => $row['order_id'],
                'total_amount'   => $row['total_amount'],
                'payment_method' => $row['payment_method'],
                'created_at'     => $row['created_at'],
                'items'          => []
            ];
        }
        $orders[$oid]['items'][] = [
            'food_name' => $row['food_name'] ?? 'Food Item',
            'quantity'  => (int)$row['quantity'],
            'price'     => (float)$row['price']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | EWU Cafeteria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-60 bg-white border-r border-slate-100 flex flex-col justify-between p-6 fixed h-full">
        <div>
            <!-- Logo -->
            <div class="flex items-center gap-2.5 mb-8">
                <i class="fa-solid fa-utensils text-[#4f46e5] text-xl"></i>
                <span class="text-xl font-extrabold text-[#4f46e5] tracking-tight">EWU Cafeteria</span>
            </div>

            <!-- Navigation Links -->
            <nav class="space-y-1.5">
                <a href="dashboard.php" class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl font-medium text-slate-600 hover:bg-slate-50 transition-all text-sm">
                    <i class="fa-solid fa-gauge text-slate-500 w-4"></i>
                    <span>Dashboard</span>
                </a>
                <a href="menu.php" class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl font-medium text-slate-600 hover:bg-slate-50 transition-all text-sm">
                    <i class="fa-solid fa-list-ul text-slate-500 w-4"></i>
                    <span>Food Menu</span>
                </a>
                <a href="cart.php" class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl font-medium text-slate-600 hover:bg-slate-50 transition-all text-sm">
                    <i class="fa-solid fa-cart-shopping text-slate-500 w-4"></i>
                    <span>My Cart</span>
                </a>
                <a href="checkout.php" class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl font-medium text-slate-600 hover:bg-slate-50 transition-all text-sm">
                    <i class="fa-regular fa-credit-card text-slate-500 w-4"></i>
                    <span>Checkout</span>
                </a>
                <a href="orders.php" class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl font-bold text-white bg-[#4f46e5] transition-all text-sm shadow-sm">
                    <i class="fa-solid fa-clock-rotate-left w-4"></i>
                    <span>Orders</span>
                </a>
            </nav>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="ml-60 flex-1 p-10 max-w-6xl">
        
        <!-- Header -->
        <div class="flex items-center gap-3 mb-8">
            <i class="fa-solid fa-clock-rotate-left text-[#4f46e5] text-2xl"></i>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">My Order History</h1>
        </div>

        <!-- Orders Container -->
        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-2xl p-8 text-center border border-slate-100 shadow-sm">
                <p class="text-slate-500 font-medium">No orders found.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                        
                        <!-- Order Top Header -->
                        <div class="mb-4">
                            <h3 class="text-base font-bold text-slate-900">Order #<?= $order['order_id']; ?></h3>
                            <p class="text-xs font-semibold text-slate-400 mt-1 flex items-center gap-1">
                                <i class="fa-regular fa-clock text-slate-400"></i>
                                <?= date('M d, Y - h:i A', strtotime($order['created_at'])); ?>
                            </p>
                        </div>

                        <!-- Order Items -->
                        <div class="space-y-2 py-3 border-t border-slate-100">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-700"><?= htmlspecialchars($item['food_name']); ?></span>
                                        <span class="font-extrabold text-slate-800">x <?= $item['quantity']; ?></span>
                                    </div>
                                    <span class="font-medium text-slate-600">৳<?= number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Order Footer -->
                        <div class="pt-4 mt-2 border-t border-dashed border-slate-200 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 rounded-lg text-xs font-bold text-slate-600">
                                <i class="fa-solid fa-wallet text-slate-400"></i>
                                <?= htmlspecialchars($order['payment_method'] ?? 'Cash on Delivery'); ?>
                            </span>

                            <div class="text-right">
                                <span class="text-sm font-black text-slate-800 mr-1">Total:</span>
                                <span class="text-sm font-black text-[#059669]">৳<?= number_format((float)$order['total_amount'], 2); ?></span>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

</body>
</html>