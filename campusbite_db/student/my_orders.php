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

$orders_query = "SELECT o.order_id, o.total_amount, o.payment_method, o.status, o.created_at,
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
                'status'         => $row['status'] ?? 'Pending',
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
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | EWU Cafeteria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-emerald-50/40 text-slate-800 flex min-h-screen antialiased">

    <aside class="w-64 bg-white/80 backdrop-blur-md border-r border-emerald-100/80 flex flex-col justify-between p-6 fixed h-full z-20">
        <div>
            <a href="#" class="flex items-center gap-3 mb-10 group">
                <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center text-white shadow-lg shadow-emerald-200 transition-transform group-hover:scale-105">
                    <i class="fa-solid fa-utensils text-lg"></i>
                </div>
                <span class="text-xl font-extrabold text-slate-900 tracking-tight">EWU <span class="text-emerald-600">Cafeteria</span></span>
            </a>

            <nav class="space-y-2">
                <a href="dashboard.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-semibold text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all text-sm">
                    <i class="fa-solid fa-gauge text-slate-400 w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="menu.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-semibold text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all text-sm">
                    <i class="fa-solid fa-list-ul text-slate-400 w-5"></i>
                    <span>Food Menu</span>
                </a>
                <a href="cart.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-semibold text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all text-sm">
                    <i class="fa-solid fa-cart-shopping text-slate-400 w-5"></i>
                    <span>My Cart</span>
                </a>
                <a href="checkout.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-semibold text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all text-sm">
                    <i class="fa-regular fa-credit-card text-slate-400 w-5"></i>
                    <span>Checkout</span>
                </a>
                <a href="orders.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-bold text-white bg-emerald-600 shadow-md shadow-emerald-200 transition-all text-sm">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i>
                    <span>Orders</span>
                </a>
            </nav>
        </div>

        <div class="pt-6 border-t border-slate-100">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl font-semibold text-rose-600 hover:bg-rose-50 transition-all text-sm">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="ml-64 flex-1 p-8 max-w-5xl mx-auto">
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                    <i class="fa-solid fa-clock-rotate-left text-emerald-600"></i>
                    My Order History
                </h1>
                <p class="text-sm font-medium text-slate-500 mt-1">Track and review your recent cafeteria orders</p>
            </div>

            <div class="flex items-center gap-3">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="searchInput" onkeyup="filterOrders()" placeholder="Search items or Order ID..." class="pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-48 md:w-60 transition-all shadow-sm">
                </div>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-2xl p-12 text-center border border-emerald-100/60 shadow-sm">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fa-solid fa-basket-shopping"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">No Orders Found</h3>
                <p class="text-slate-500 text-sm max-w-sm mx-auto mb-6">Looks like you haven't ordered anything yet from the cafeteria.</p>
                <a href="menu.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white rounded-xl font-semibold text-sm shadow-md hover:bg-emerald-700 transition-all">
                    Browse Food Menu <i class="fa-solid fa-arrow-right text-xs"></i>
                </a>
            </div>
        <?php else: ?>
            <div id="ordersList" class="space-y-5">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card bg-white rounded-2xl border border-emerald-100/80 p-6 shadow-sm hover:shadow-md transition-all duration-200">
                        
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                            <div>
                                <div class="flex items-center gap-3">
                                    <h3 class="text-base font-extrabold text-slate-900 order-id-text">Order #<?= $order['order_id']; ?></h3>
                                    
                                    <?php
                                        $status = strtolower($order['status']);
                                        $badgeClasses = 'bg-slate-100 text-slate-600 border-slate-200';
                                        if ($status === 'completed') {
                                            $badgeClasses = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                        } elseif ($status === 'pending') {
                                            $badgeClasses = 'bg-amber-50 text-amber-700 border-amber-200';
                                        } elseif ($status === 'cancelled') {
                                            $badgeClasses = 'bg-rose-50 text-rose-700 border-rose-200';
                                        }
                                    ?>
                                    <span class="px-2.5 py-0.5 text-xs font-bold rounded-full border <?= $badgeClasses; ?> uppercase tracking-wider">
                                        <?= htmlspecialchars($order['status']); ?>
                                    </span>
                                </div>
                                <p class="text-xs font-semibold text-slate-400 mt-1 flex items-center gap-1.5">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= date('M d, Y • h:i A', strtotime($order['created_at'])); ?>
                                </p>
                            </div>

                            <span class="text-xs font-bold px-3 py-1 bg-slate-50 text-slate-600 rounded-lg border border-slate-100 flex items-center gap-1.5">
                                <i class="fa-solid fa-wallet text-slate-400"></i>
                                <?= htmlspecialchars($order['payment_method'] ?? 'Cash on Delivery'); ?>
                            </span>
                        </div>

                        <div class="py-4 space-y-2.5">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="flex items-center justify-between text-sm item-row">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span class="font-bold text-slate-800 item-name"><?= htmlspecialchars($item['food_name']); ?></span>
                                        <span class="text-xs font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">x<?= $item['quantity']; ?></span>
                                    </div>
                                    <span class="font-semibold text-slate-600">৳<?= number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="pt-4 border-t border-dashed border-slate-200 flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-400">Total Bill Amount</span>
                            <div class="text-right">
                                <span class="text-lg font-black text-emerald-700">৳<?= number_format((float)$order['total_amount'], 2); ?></span>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <script>
        function filterOrders() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.order-card');

            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>

</body>
</html>
