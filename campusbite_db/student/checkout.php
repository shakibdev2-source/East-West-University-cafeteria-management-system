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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3);

function getCartPayload(mysqli $conn, int $userId): array {
    $query = "SELECT c.cart_id, c.food_id, c.quantity, f.price, f.food_name, f.image 
              FROM cart c 
              INNER JOIN foods f ON c.food_id = f.food_id 
              WHERE c.user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $items = [];
    $subtotal = 0.00;

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
            $subtotal += ((float)$row['price'] * (int)$row['quantity']);
        }
    }
    return ['items' => $items, 'subtotal' => $subtotal];
}

$cartData = getCartPayload($conn, $user_id);
$cart_items = $cartData['items'];
$subtotal = $cartData['subtotal'];

if (empty($cart_items)) {
    header("Location: cart.php?status=empty_cart");
    exit();
}

$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'table_room'");
$hasTableRoomCol = ($colCheck && mysqli_num_rows($colCheck) > 0);

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $error_msg = "Security Validation Failed. Please try again.";
    } else {
        $table_room = trim(filter_input(INPUT_POST, 'table_room', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $instructions = trim(filter_input(INPUT_POST, 'instructions', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $payment_method = trim(filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Cash / Counter Payment');
        $gateway_trx = trim(filter_input(INPUT_POST, 'gateway_trx', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $discount_amount = (float)(filter_input(INPUT_POST, 'discount_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? 0.00);
        $tip_amount = (float)(filter_input(INPUT_POST, 'tip_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? 0.00);
        $order_type = trim(filter_input(INPUT_POST, 'order_type', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Dine-In');

        $final_total = max(0.00, $subtotal - $discount_amount + $tip_amount);

        if (empty($table_room)) {
            $error_msg = "Table or Location assignment is required.";
        } else {
            mysqli_begin_transaction($conn, MYSQLI_TRANS_START_READ_WRITE);

            try {
                $payment_details = $payment_method . " [" . $order_type . "]";
                if (!empty($gateway_trx)) {
                    $payment_details .= " (TrxID: " . $gateway_trx . ")";
                }

                if ($hasTableRoomCol) {
                    $sql = "INSERT INTO orders (user_id, subtotal, total_amount, payment_method, table_room, instructions, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "iddsss", $user_id, $subtotal, $final_total, $payment_details, $table_room, $instructions);
                } else {
                    $sql = "INSERT INTO orders (user_id, subtotal, total_amount, payment_method, status, created_at) 
                            VALUES (?, ?, ?, ?, 'Pending', NOW())";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "idds", $user_id, $subtotal, $final_total, $payment_details);
                }

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Order Execution Error: " . mysqli_error($conn));
                }

                $order_id = mysqli_insert_id($conn);

                $item_sql = "INSERT INTO order_items (order_id, food_id, quantity, price) VALUES (?, ?, ?, ?)";
                $item_stmt = mysqli_prepare($conn, $item_sql);

                foreach ($cart_items as $item) {
                    $f_id = (int)$item['food_id'];
                    $qty = (int)$item['quantity'];
                    $prc = (float)$item['price'];
                    mysqli_stmt_bind_param($item_stmt, "iiid", $order_id, $f_id, $qty, $prc);
                    
                    if (!mysqli_stmt_execute($item_stmt)) {
                        throw new Exception("Line Item Insertion Error.");
                    }
                }

                $purge = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
                mysqli_stmt_bind_param($purge, "i", $user_id);
                mysqli_stmt_execute($purge);

                mysqli_commit($conn);
                unset($_SESSION['csrf_token']);

                header("Location: orders.php?status=success&order_id=" . $order_id);
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_msg = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-[#f0fdf4]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EWU Cafeteria - Design by Shakib</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: radial-gradient(circle at top left, #e6f7f0 0%, #f4f8f6 100%);
        }

        .glass-card-3d {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 150, 107, 0.12);
            box-shadow: 0 20px 40px -10px rgba(0, 150, 107, 0.08);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease;
            transform-style: preserve-3d;
        }

        .glass-card-3d:hover {
            transform: translateY(-6px) rotateX(2deg) rotateY(-1deg);
            box-shadow: 0 30px 60px -12px rgba(0, 150, 107, 0.18);
            border-color: rgba(0, 150, 107, 0.3);
        }

        @keyframes floatEntrance {
            0% { opacity: 0; transform: translateY(30px) scale(0.97); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        .anim-slide-1 { animation: floatEntrance 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .anim-slide-2 { animation: floatEntrance 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .anim-slide-3 { animation: floatEntrance 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

        @keyframes float3D {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-6px) rotate(4deg); }
        }

        .floating-3d-icon { animation: float3D 3s ease-in-out infinite; }

        @keyframes borderPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(0, 150, 107, 0.3); }
            50% { box-shadow: 0 0 0 10px rgba(0, 150, 107, 0); }
        }

        .pulse-active { animation: borderPulse 2s infinite; }

        @keyframes rocketFlying {
            0% { transform: translate(-50%, 100vh) rotate(-45deg) scale(0.5); opacity: 1; }
            50% { transform: translate(-50%, -10vh) rotate(-45deg) scale(1.3); opacity: 1; }
            100% { transform: translate(-50%, -120vh) rotate(-45deg) scale(1.8); opacity: 0; }
        }

        .rocket-launch-fx {
            position: fixed;
            left: 50%;
            bottom: 0;
            z-index: 999;
            font-size: 90px;
            color: #00966b;
            animation: rocketFlying 1.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            pointer-events: none;
        }

        .bkash-backdrop {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
        }

        .bkash-btn {
            background: linear-gradient(135deg, #e2136e 0%, #b80d57 100%);
            box-shadow: 0 10px 25px rgba(226, 19, 110, 0.35);
        }
        .bkash-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 35px rgba(226, 19, 110, 0.5);
        }
    </style>
</head>
<body class="h-full text-slate-800 antialiased flex selection:bg-[#00966b]/20 selection:text-[#00966b]">

    <div id="rocketFxContainer" class="hidden">
        <i class="fa-solid fa-rocket rocket-launch-fx"></i>
    </div>

    <aside class="w-64 bg-white/95 backdrop-blur-xl border-r border-emerald-100/80 p-6 flex flex-col justify-between fixed h-full z-30 shadow-sm">
        <div>
            <div class="flex items-center gap-3.5 mb-10">
                <div class="w-10 h-10 bg-[#00966b] rounded-2xl flex items-center justify-center text-white shadow-lg shadow-[#00966b]/25 floating-3d-icon">
                    <i class="fa-solid fa-utensils text-lg"></i>
                </div>
                <div>
                    <span class="text-lg font-extrabold text-slate-900 tracking-tight block">EWU Cafeteria</span>
                    <span class="text-[10px] font-black text-[#00966b] tracking-widest uppercase block -mt-0.5">Pro Express v4.0</span>
                </div>
            </div>

            <nav class="space-y-2">
                <a href="dashboard.php" onclick="playClickSound()" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-semibold text-slate-500 hover:bg-[#e6f7f2] hover:text-[#00966b] transition-all">
                    <i class="fa-solid fa-house text-base w-5"></i>
                    <span class="text-sm">Dashboard</span>
                </a>
                <a href="menu.php" onclick="playClickSound()" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-semibold text-slate-500 hover:bg-[#e6f7f2] hover:text-[#00966b] transition-all">
                    <i class="fa-solid fa-book-open text-base w-5"></i>
                    <span class="text-sm">Food Menu</span>
                </a>
                <a href="cart.php" onclick="playClickSound()" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-semibold text-slate-500 hover:bg-[#e6f7f2] hover:text-[#00966b] transition-all">
                    <i class="fa-solid fa-cart-shopping text-base w-5"></i>
                    <span class="text-sm">My Cart</span>
                </a>
                <a href="checkout.php" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-bold text-[#00966b] bg-[#e6f7f2] transition-all shadow-sm">
                    <i class="fa-regular fa-credit-card text-base w-5"></i>
                    <span class="text-sm">Checkout</span>
                </a>
                <a href="orders.php" onclick="playClickSound()" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-semibold text-slate-500 hover:bg-[#e6f7f2] hover:text-[#00966b] transition-all">
                    <i class="fa-solid fa-rotate-left text-base w-5"></i>
                    <span class="text-sm">My Orders</span>
                </a>
            </nav>
        </div>

        <div class="space-y-4">
            <div class="bg-gradient-to-br from-[#e6f7f2] to-emerald-50 border border-[#00966b]/20 rounded-2xl p-3.5 text-xs text-slate-700">
                <div class="flex items-center justify-between font-extrabold text-[#00966b] mb-1">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-[#00966b] animate-ping"></span>
                        Kitchen Traffic
                    </span>
                    <span class="text-[10px] bg-white px-2 py-0.5 rounded-full shadow-sm font-black text-emerald-600">LIGHT</span>
                </div>
                <p class="text-[11px] text-slate-600 font-medium">Est. Prep Time: <strong id="prepTimer" class="text-slate-900 font-bold">12:00 Mins</strong></p>
            </div>

            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 font-bold text-rose-500 hover:bg-rose-50 rounded-2xl transition-all text-sm">
                <i class="fa-solid fa-right-from-bracket text-base"></i>
                <span>Sign Out</span>
            </a>
        </div>
    </aside>

    <main class="ml-64 flex-1 p-10 max-w-7xl">
        
        <?php if (!empty($error_msg)): ?>
            <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 font-semibold text-sm flex items-center gap-3 shadow-sm anim-slide-1">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error_msg); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="checkout.php" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="gateway_trx" id="gatewayTrxInput" value="">
            <input type="hidden" name="payment_method" id="selectedPaymentInput" value="Cash / Counter Payment">
            <input type="hidden" name="discount_amount" id="discountAmountInput" value="0.00">
            <input type="hidden" name="tip_amount" id="tipAmountInput" value="0.00">
            <input type="hidden" name="order_type" id="orderTypeInput" value="Dine-In">

            <div class="grid grid-cols-12 gap-8 items-start">
                
                <div class="col-span-8 space-y-8">
                    
                    <div class="glass-card-3d rounded-3xl p-6 anim-slide-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Select Service Type</span>
                            <div class="flex gap-2 bg-slate-100 p-1.5 rounded-2xl">
                                <button type="button" id="btnDineIn" onclick="toggleOrderType('Dine-In')" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-[#00966b] text-white shadow-md transition-all">
                                    <i class="fa-solid fa-chair mr-1.5"></i> Dine-In
                                </button>
                                <button type="button" id="btnTakeaway" onclick="toggleOrderType('Takeaway')" class="px-5 py-2 rounded-xl text-xs font-extrabold text-slate-600 hover:text-slate-900 transition-all">
                                    <i class="fa-solid fa-box-archive mr-1.5"></i> Takeaway
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card-3d rounded-3xl p-8 anim-slide-1">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-extrabold text-slate-900 flex items-center gap-3">
                                <span class="w-9 h-9 rounded-xl bg-[#00966b]/10 flex items-center justify-center text-[#00966b]">
                                    <i class="fa-solid fa-location-dot"></i>
                                </span> 
                                Delivery Location
                            </h2>
                            <button type="button" onclick="openMapModal()" class="px-3.5 py-1.5 bg-[#e6f7f2] text-[#00966b] font-extrabold text-xs rounded-xl hover:bg-[#00966b] hover:text-white transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-[#00966b] fa-map"></i> Choose From Map
                            </button>
                        </div>
                        
                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">TABLE NUMBER / CAFETERIA ROOM *</label>
                                <input type="text" name="table_room" id="tableRoomInput" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-sm font-semibold outline-none focus:border-[#00966b] focus:bg-white focus:ring-4 focus:ring-[#00966b]/10 transition-all text-slate-800 placeholder-slate-400" placeholder="e.g. Table 5 or Faculty Lounge" required>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">SPECIAL INSTRUCTIONS (OPTIONAL)</label>
                                <input type="text" name="instructions" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-sm font-semibold outline-none focus:border-[#00966b] focus:bg-white focus:ring-4 focus:ring-[#00966b]/10 transition-all text-slate-800 placeholder-slate-400" placeholder="e.g. Extra napkins, less spicy please">
                            </div>
                        </div>
                    </div>

                    <div class="glass-card-3d rounded-3xl p-8 anim-slide-2">
                        <h2 class="text-xl font-extrabold text-slate-900 mb-6 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl bg-[#00966b]/10 flex items-center justify-center text-[#00966b]">
                                <i class="fa-solid fa-wallet"></i>
                            </span> 
                            Payment Method
                        </h2>

                        <div class="space-y-4">
                            <div id="optCash" onclick="selectPaymentMethod('Cash / Counter Payment')" class="border-2 border-[#00966b] bg-[#e6f7f2]/80 rounded-2xl p-5 flex items-center justify-between cursor-pointer transition-all duration-300 pulse-active">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-[#00966b]/10 flex items-center justify-center text-[#00966b]">
                                        <i class="fa-solid fa-money-bill-wave text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="font-extrabold text-slate-900 text-sm">Cash / Counter Payment</p>
                                        <p class="text-xs text-slate-500 mt-0.5">Pay cash at counter or delivery</p>
                                    </div>
                                </div>
                                <span id="cashCheck" class="w-6 h-6 rounded-full bg-[#00966b] text-white flex items-center justify-center text-xs">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                            </div>

                            <div id="optBkash" onclick="selectPaymentMethod('bKash Online Payment')" class="border-2 border-slate-100 bg-white rounded-2xl p-5 flex items-center justify-between cursor-pointer transition-all duration-300 hover:border-[#e2136e]">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-2xl bg-[#e2136e]/10 flex items-center justify-center text-[#e2136e]">
                                        <i class="fa-solid fa-mobile-screen-button text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="font-extrabold text-slate-900 text-sm flex items-center gap-2">
                                            <span>bKash Automated Gateway</span>
                                            <span class="text-[9px] bg-[#e2136e] text-white px-2 py-0.5 rounded-full font-bold">INSTANT</span>
                                        </p>
                                        <p class="text-xs text-slate-500 mt-0.5">Pay online using bKash account</p>
                                    </div>
                                </div>
                                <span id="bkashCheck" class="w-6 h-6 rounded-full border-2 border-slate-300 flex items-center justify-center text-xs"></span>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="col-span-4 glass-card-3d rounded-3xl p-8 sticky top-8 anim-slide-3">
                    <h2 class="text-xl font-extrabold text-slate-900 mb-6 flex items-center justify-between">
                        <span>Order Summary</span>
                        <span class="text-xs font-bold text-[#00966b] bg-[#e6f7f2] px-3 py-1 rounded-full"><?= count($cart_items) ?> Items</span>
                    </h2>
                    
                    <div class="space-y-3 mb-6 max-h-48 overflow-y-auto pr-1">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="flex items-center justify-between text-sm py-2 border-b border-slate-100">
                            <div class="flex items-center gap-2.5">
                                <span class="font-extrabold text-[#00966b]"><?= $item['quantity'] ?>x</span>
                                <span class="font-semibold text-slate-700"><?= htmlspecialchars($item['food_name']) ?></span>
                            </div>
                            <span class="font-extrabold text-slate-900">৳<?= number_format((float)$item['price'] * (int)$item['quantity'], 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-5 bg-slate-50 p-3.5 rounded-2xl border border-slate-200/80">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Apply Student Voucher</label>
                        <div class="flex gap-2">
                            <input type="text" id="promoCodeInput" placeholder="Code: EWU10" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold uppercase text-slate-800 outline-none focus:border-[#00966b]">
                            <button type="button" onclick="applyStudentPromo()" class="px-4 py-2 bg-[#00966b] text-white font-extrabold rounded-xl text-xs hover:bg-[#007d59] transition-all">Apply</button>
                        </div>
                        <p id="promoStatusMsg" class="text-[11px] font-bold mt-1.5 hidden"></p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Staff Courtesy Tip</label>
                        <div class="grid grid-cols-4 gap-2">
                            <button type="button" onclick="addStaffTip(0)" class="tip-btn border border-slate-200 rounded-xl py-1.5 text-xs font-bold text-slate-600 hover:border-[#00966b] bg-white">৳0</button>
                            <button type="button" onclick="addStaffTip(10)" class="tip-btn border border-slate-200 rounded-xl py-1.5 text-xs font-bold text-slate-600 hover:border-[#00966b] bg-white">৳10</button>
                            <button type="button" onclick="addStaffTip(20)" class="tip-btn border border-slate-200 rounded-xl py-1.5 text-xs font-bold text-slate-600 hover:border-[#00966b] bg-white">৳20</button>
                            <button type="button" onclick="addStaffTip(50)" class="tip-btn border border-slate-200 rounded-xl py-1.5 text-xs font-bold text-slate-600 hover:border-[#00966b] bg-white">৳50</button>
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-slate-100 pt-4 mb-8">
                        <div class="flex justify-between text-sm text-slate-500 font-medium">
                            <span>Subtotal</span>
                            <span class="font-bold text-slate-800">৳<span id="displaySubtotal"><?= number_format($subtotal, 2) ?></span></span>
                        </div>
                        
                        <div id="discountRow" class="flex justify-between text-sm text-emerald-600 font-bold hidden">
                            <span>Student Discount (10%)</span>
                            <span>-৳<span id="displayDiscount">0.00</span></span>
                        </div>

                        <div id="tipRow" class="flex justify-between text-sm text-slate-600 font-bold hidden">
                            <span>Staff Tip</span>
                            <span>+৳<span id="displayTip">0.00</span></span>
                        </div>

                        <div class="flex justify-between text-xl font-black text-slate-900 border-t border-slate-100 pt-3">
                            <span>Total Payable</span>
                            <span class="text-[#00966b]">৳<span id="displayTotal"><?= number_format($subtotal, 2) ?></span></span>
                        </div>
                    </div>

                    <button type="button" onclick="handleCheckoutSubmission()" class="w-full py-4 bg-[#00966b] hover:bg-[#007d59] active:scale-[0.98] text-white font-extrabold rounded-2xl shadow-xl shadow-[#00966b]/25 transition-all duration-300 text-center flex items-center justify-center gap-2 text-sm tracking-wide">
                        <span>Place Order Now</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>

            </div>
        </form>
    </main>

    <div id="mapModal" class="fixed inset-0 z-50 hidden items-center justify-center bkash-backdrop p-4">
        <div class="bg-white w-full max-w-xl rounded-3xl p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-map-location-dot text-[#00966b]"></i> Interactive Cafeteria Floor Plan
                </h3>
                <button type="button" onclick="closeMapModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center hover:bg-slate-200">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <p class="text-xs text-slate-500 mb-4">Click any designated zone or table below to set your order destination.</p>

            <div class="grid grid-cols-3 gap-3">
                <button type="button" onclick="selectMapZone('Main Hall - Table 01')" class="p-4 border-2 border-emerald-200 bg-emerald-50/50 hover:bg-[#00966b] hover:text-white rounded-2xl font-bold text-xs transition-all text-slate-700">
                    Table 01
                </button>
                <button type="button" onclick="selectMapZone('Main Hall - Table 02')" class="p-4 border-2 border-emerald-200 bg-emerald-50/50 hover:bg-[#00966b] hover:text-white rounded-2xl font-bold text-xs transition-all text-slate-700">
                    Table 02
                </button>
                <button type="button" onclick="selectMapZone('Main Hall - Table 05')" class="p-4 border-2 border-emerald-200 bg-emerald-50/50 hover:bg-[#00966b] hover:text-white rounded-2xl font-bold text-xs transition-all text-slate-700">
                    Table 05
                </button>
                <button type="button" onclick="selectMapZone('Faculty Zone - Room 402')" class="p-4 border-2 border-purple-200 bg-purple-50 hover:bg-purple-600 hover:text-white rounded-2xl font-bold text-xs transition-all text-slate-700">
                    Faculty Room 402
                </button>
                <button type="button" onclick="selectMapZone('Library Zone - Booth 3')" class="p-4 border-2 border-blue-200 bg-blue-50 hover:bg-blue-600 hover:text-white rounded-2xl font-bold text-xs transition-all text-slate-700">
                    Library Booth 3
                </button>
                <button type="button" onclick="selectMapZone('Outdoor Counter')" class="p-4 border-2 border-amber-200 bg-amber-50 hover:bg-amber-600 hover:text-white rounded-2xl font-bold text-xs transition-all text-slate-700">
                    Outdoor Counter
                </button>
            </div>
        </div>
    </div>

    <div id="bkashGatewayModal" class="fixed inset-0 z-50 hidden items-center justify-center bkash-backdrop p-4">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-pink-100">
            
            <div class="bg-gradient-to-r from-[#e2136e] to-[#b80d57] p-6 text-white text-center relative">
                <button type="button" onclick="closeBkashGateway()" class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-all text-xs">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                
                <div class="w-14 h-14 bg-white text-[#e2136e] rounded-2xl flex items-center justify-center font-black text-2xl mx-auto mb-2 shadow-lg">
                    ৳
                </div>
                <h3 class="text-lg font-extrabold">bKash Payment Gateway</h3>
                <p class="text-xs text-white/80 font-medium">Merchant: EWU Cafeteria Express</p>
            </div>

            <div class="p-6">
                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 text-center mb-6">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Total Chargeable Amount</span>
                    <span class="text-3xl font-black text-slate-900">৳<span id="bkashModalAmount"><?= number_format($subtotal, 2); ?></span></span>
                </div>

                <div id="bkashStep1" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">bKash Mobile Account</label>
                        <input type="text" id="bkashPhoneInput" maxlength="11" placeholder="01XXXXXXXXX" class="w-full bg-slate-50 border-2 border-slate-200 rounded-2xl p-4 text-center font-extrabold text-lg text-slate-800 outline-none focus:border-[#e2136e] transition-all tracking-wider">
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <button type="button" onclick="closeBkashGateway()" class="py-3.5 bg-slate-100 hover:bg-slate-200 font-bold text-slate-600 rounded-2xl transition-all text-sm">Cancel</button>
                        <button type="button" onclick="goToBkashStepOTP()" class="py-3.5 bkash-btn text-white font-extrabold rounded-2xl transition-all text-sm">Get Verification OTP</button>
                    </div>
                </div>

                <div id="bkashStepOTP" class="space-y-4 hidden">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Verification Code (OTP)</label>
                        <input type="text" id="bkashOtpInput" maxlength="6" placeholder="123456" class="w-full bg-slate-50 border-2 border-slate-200 rounded-2xl p-4 text-center font-black text-2xl text-slate-800 outline-none focus:border-[#e2136e] transition-all tracking-widest">
                        <p class="text-[11px] text-slate-400 text-center mt-2">Use Sandbox Demo OTP: <strong class="text-[#e2136e]">123456</strong></p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <button type="button" onclick="backToBkashStep1()" class="py-3.5 bg-slate-100 hover:bg-slate-200 font-bold text-slate-600 rounded-2xl transition-all text-sm">Back</button>
                        <button type="button" onclick="goToBkashStepPIN()" class="py-3.5 bkash-btn text-white font-extrabold rounded-2xl transition-all text-sm">Confirm OTP</button>
                    </div>
                </div>

                <div id="bkashStepPIN" class="space-y-4 hidden">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">bKash Account PIN</label>
                        <input type="password" id="bkashPinInput" maxlength="5" placeholder="•••••" class="w-full bg-slate-50 border-2 border-slate-200 rounded-2xl p-4 text-center font-black text-2xl text-slate-800 outline-none focus:border-[#e2136e] transition-all tracking-widest">
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <button type="button" onclick="backToBkashStepOTP()" class="py-3.5 bg-slate-100 hover:bg-slate-200 font-bold text-slate-600 rounded-2xl transition-all text-sm">Back</button>
                        <button type="button" onclick="processFinalBkashPayment()" class="py-3.5 bkash-btn text-white font-extrabold rounded-2xl transition-all text-sm">Complete Payment</button>
                    </div>
                </div>

                <div id="bkashLoader" class="py-8 text-center space-y-4 hidden">
                    <div class="w-12 h-12 border-4 border-[#e2136e] border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="font-extrabold text-slate-800 text-sm">Processing Secure Payment...</p>
                </div>

            </div>
        </div>
    </div>

    <script>
        const rawSubtotal = <?= (float)$subtotal ?>;
        let appliedDiscount = 0.00;
        let currentTip = 0.00;
        let currentPayment = 'Cash / Counter Payment';
        let currentOrderType = 'Dine-In';

        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        function playClickSound() {
            if (audioCtx.state === 'suspended') { audioCtx.resume(); }
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(600, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.08);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.08);
        }

        let totalSeconds = 720;
        setInterval(() => {
            if(totalSeconds > 0) {
                totalSeconds--;
                const mins = Math.floor(totalSeconds / 60);
                const secs = totalSeconds % 60;
                document.getElementById('prepTimer').innerText = `${mins}:${secs < 10 ? '0' : ''}${secs} Mins`;
            }
        }, 1000);

        function openMapModal() {
            playClickSound();
            document.getElementById('mapModal').classList.remove('hidden');
            document.getElementById('mapModal').classList.add('flex');
        }

        function closeMapModal() {
            document.getElementById('mapModal').classList.add('hidden');
            document.getElementById('mapModal').classList.remove('flex');
        }

        function selectMapZone(zoneName) {
            playClickSound();
            document.getElementById('tableRoomInput').value = zoneName;
            closeMapModal();
        }

        function toggleOrderType(type) {
            playClickSound();
            currentOrderType = type;
            document.getElementById('orderTypeInput').value = type;

            const btnDine = document.getElementById('btnDineIn');
            const btnTake = document.getElementById('btnTakeaway');

            if (type === 'Dine-In') {
                btnDine.className = "px-5 py-2 rounded-xl text-xs font-extrabold bg-[#00966b] text-white shadow-md transition-all";
                btnTake.className = "px-5 py-2 rounded-xl text-xs font-extrabold text-slate-600 hover:text-slate-900 transition-all";
            } else {
                btnTake.className = "px-5 py-2 rounded-xl text-xs font-extrabold bg-[#00966b] text-white shadow-md transition-all";
                btnDine.className = "px-5 py-2 rounded-xl text-xs font-extrabold text-slate-600 hover:text-slate-900 transition-all";
            }
        }

        function addStaffTip(amount) {
            playClickSound();
            currentTip = amount;
            document.getElementById('tipAmountInput').value = amount.toFixed(2);
            document.getElementById('displayTip').innerText = amount.toFixed(2);

            const tipRow = document.getElementById('tipRow');
            if (amount > 0) {
                tipRow.classList.remove('hidden');
            } else {
                tipRow.classList.add('hidden');
            }
            recalculateTotal();
        }

        function applyStudentPromo() {
            playClickSound();
            const code = document.getElementById('promoCodeInput').value.trim().toUpperCase();
            const msgEl = document.getElementById('promoStatusMsg');

            if (code === 'EWU10') {
                appliedDiscount = rawSubtotal * 0.10;
                document.getElementById('discountAmountInput').value = appliedDiscount.toFixed(2);
                document.getElementById('displayDiscount').innerText = appliedDiscount.toFixed(2);
                
                document.getElementById('discountRow').classList.remove('hidden');

                msgEl.className = "text-[11px] font-bold mt-1.5 text-emerald-600";
                msgEl.innerText = "✓ Voucher Applied! 10% Student Discount.";
                msgEl.classList.remove('hidden');

                confetti({ particleCount: 70, spread: 60, origin: { y: 0.8 } });
            } else {
                msgEl.className = "text-[11px] font-bold mt-1.5 text-rose-500";
                msgEl.innerText = "✕ Invalid coupon code. Try 'EWU10'";
                msgEl.classList.remove('hidden');
            }
            recalculateTotal();
        }

        function recalculateTotal() {
            const finalVal = Math.max(0, rawSubtotal - appliedDiscount + currentTip).toFixed(2);
            document.getElementById('displayTotal').innerText = finalVal;
            document.getElementById('bkashModalAmount').innerText = finalVal;
        }

        function selectPaymentMethod(method) {
            playClickSound();
            currentPayment = method;
            document.getElementById('selectedPaymentInput').value = method;

            const optCash = document.getElementById('optCash');
            const optBkash = document.getElementById('optBkash');
            const cashCheck = document.getElementById('cashCheck');
            const bkashCheck = document.getElementById('bkashCheck');

            if (method === 'Cash / Counter Payment') {
                optCash.className = "border-2 border-[#00966b] bg-[#e6f7f2]/80 rounded-2xl p-5 flex items-center justify-between cursor-pointer transition-all duration-300 pulse-active";
                optBkash.className = "border-2 border-slate-100 bg-white rounded-2xl p-5 flex items-center justify-between cursor-pointer transition-all duration-300 hover:border-[#e2136e]";
                
                cashCheck.className = "w-6 h-6 rounded-full bg-[#00966b] text-white flex items-center justify-center text-xs";
                cashCheck.innerHTML = '<i class="fa-solid fa-check"></i>';
                
                bkashCheck.className = "w-6 h-6 rounded-full border-2 border-slate-300 flex items-center justify-center text-xs";
                bkashCheck.innerHTML = '';
            } else {
                optBkash.className = "border-2 border-[#e2136e] bg-[#e2136e]/5 rounded-2xl p-5 flex items-center justify-between cursor-pointer transition-all duration-300 pulse-active";
                optCash.className = "border-2 border-slate-100 bg-white rounded-2xl p-5 flex items-center justify-between cursor-pointer transition-all duration-300 hover:border-[#00966b]";

                bkashCheck.className = "w-6 h-6 rounded-full bg-[#e2136e] text-white flex items-center justify-center text-xs";
                bkashCheck.innerHTML = '<i class="fa-solid fa-check"></i>';

                cashCheck.className = "w-6 h-6 rounded-full border-2 border-slate-300 flex items-center justify-center text-xs";
                cashCheck.innerHTML = '';
            }
        }

        function triggerRocketAnimation(callback) {
            const rocketBox = document.getElementById('rocketFxContainer');
            rocketBox.classList.remove('hidden');

            confetti({
                particleCount: 140,
                spread: 90,
                origin: { y: 0.6 }
            });

            setTimeout(() => {
                callback();
            }, 1400);
        }

        function handleCheckoutSubmission() {
            playClickSound();
            const tableRoom = document.getElementById('tableRoomInput').value.trim();
            
            if (!tableRoom) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Location Missing',
                    text: 'Please input or choose your Table Number / Room.',
                    confirmButtonColor: '#00966b',
                    customClass: { popup: 'rounded-3xl' }
                });
                document.getElementById('tableRoomInput').focus();
                return;
            }

            if (currentPayment === 'Cash / Counter Payment') {
                Swal.fire({
                    title: 'Confirm Order?',
                    text: "Your order will be sent directly to the kitchen queue.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#00966b',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Yes, Submit Order',
                    customClass: { popup: 'rounded-3xl' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        triggerRocketAnimation(() => {
                            document.getElementById('checkoutForm').submit();
                        });
                    }
                });
            } else {
                openBkashGateway();
            }
        }

        function openBkashGateway() {
            document.getElementById('bkashGatewayModal').classList.remove('hidden');
            document.getElementById('bkashGatewayModal').classList.add('flex');
            document.getElementById('bkashStep1').classList.remove('hidden');
            document.getElementById('bkashStepOTP').classList.add('hidden');
            document.getElementById('bkashStepPIN').classList.add('hidden');
            document.getElementById('bkashLoader').classList.add('hidden');
        }

        function closeBkashGateway() {
            document.getElementById('bkashGatewayModal').classList.add('hidden');
            document.getElementById('bkashGatewayModal').classList.remove('flex');
        }

        function goToBkashStepOTP() {
            playClickSound();
            const num = document.getElementById('bkashPhoneInput').value.trim();
            if (!num || num.length !== 11 || !num.startsWith('01')) {
                alert('Please enter a valid 11-digit bKash Mobile Number.');
                return;
            }
            document.getElementById('bkashStep1').classList.add('hidden');
            document.getElementById('bkashStepOTP').classList.remove('hidden');
        }

        function backToBkashStep1() {
            playClickSound();
            document.getElementById('bkashStepOTP').classList.add('hidden');
            document.getElementById('bkashStep1').classList.remove('hidden');
        }

        function goToBkashStepPIN() {
            playClickSound();
            const otp = document.getElementById('bkashOtpInput').value.trim();
            if (otp.length < 6) {
                alert('Please enter a valid 6-digit OTP.');
                return;
            }
            document.getElementById('bkashStepOTP').classList.add('hidden');
            document.getElementById('bkashStepPIN').classList.remove('hidden');
        }

        function backToBkashStepOTP() {
            playClickSound();
            document.getElementById('bkashStepPIN').classList.add('hidden');
            document.getElementById('bkashStepOTP').classList.remove('hidden');
        }

        function processFinalBkashPayment() {
            playClickSound();
            const pin = document.getElementById('bkashPinInput').value.trim();
            if (pin.length < 5) {
                alert('Please enter a valid 5-digit PIN.');
                return;
            }

            document.getElementById('bkashStepPIN').classList.add('hidden');
            document.getElementById('bkashLoader').classList.remove('hidden');

            setTimeout(() => {
                const mockTrx = 'BK' + Math.floor(10000000 + Math.random() * 90000000);
                document.getElementById('gatewayTrxInput').value = mockTrx;
                closeBkashGateway();
                
                triggerRocketAnimation(() => {
                    document.getElementById('checkoutForm').submit();
                });
            }, 1200);
        }
    </script>
</body>
</html>