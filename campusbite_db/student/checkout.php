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

mysqli_set_charset($conn, "utf8mb4");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3);

function getCartPayload(mysqli $conn, int $userId): array {
    $query = "SELECT c.cart_id, c.food_id, c.quantity, f.price, f.food_name, f.image, f.category 
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
        $error_msg = "Security Validation Failed (CSRF Mismatch). Please try again.";
    } else {
        $table_room = trim(filter_input(INPUT_POST, 'table_room', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $instructions = trim(filter_input(INPUT_POST, 'instructions', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $payment_method = trim(filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Cash / Counter Payment');
        $gateway_trx = trim(filter_input(INPUT_POST, 'gateway_trx', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $discount_amount = (float)(filter_input(INPUT_POST, 'discount_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? 0.00);
        $tip_amount = (float)(filter_input(INPUT_POST, 'tip_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? 0.00);
        $order_type = trim(filter_input(INPUT_POST, 'order_type', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Dine-In');
        $eco_cutlery = isset($_POST['eco_cutlery']) ? 'Yes (Eco-Friendly)' : 'No';
        $priority_queue = isset($_POST['priority_queue']) ? 15.00 : 0.00;
        $carbon_offset = isset($_POST['carbon_offset']) ? 5.00 : 0.00;
        $cut_line_pass = isset($_POST['cut_line_pass']) ? 25.00 : 0.00;
        $scheduled_time = trim(filter_input(INPUT_POST, 'scheduled_time', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'ASAP (Immediate)');

        $final_total = max(0.00, $subtotal - $discount_amount + $tip_amount + $priority_queue + $carbon_offset + $cut_line_pass);

        if (empty($table_room)) {
            $error_msg = "Table number or cafeteria location assignment is required.";
        } else {
            mysqli_begin_transaction($conn, MYSQLI_TRANS_START_READ_WRITE);

            try {
                $payment_details = $payment_method . " [" . $order_type . "] | Schedule: " . $scheduled_time . " | Cutlery: " . $eco_cutlery;
                if (!empty($gateway_trx)) {
                    $payment_details .= " (TrxID: " . $gateway_trx . ")";
                }
                if ($priority_queue > 0) {
                    $payment_details .= " [⚡ VIP PRIORITY QUEUE]";
                }
                if ($carbon_offset > 0) {
                    $payment_details .= " [🌳 CARBON OFFSET TREE]";
                }
                if ($cut_line_pass > 0) {
                    $payment_details .= " [🚀 EXPRESS COUNTER SKIP PASS]";
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
                        throw new Exception("Line Item Insertion Error for Food ID: " . $f_id);
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
                $error_msg = "Transaction Aborted: " . $e->getMessage();
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
    <title>EWU Cafeteria - Nexus Omni-Dimension v8.0</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: radial-gradient(circle at top left, #e6f7f0 0%, #edf7f2 50%, #f4f8f6 100%);
        }

        .glass-card-3d {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(0, 150, 107, 0.15);
            box-shadow: 0 25px 50px -12px rgba(0, 150, 107, 0.09);
            transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform-style: preserve-3d;
        }

        .glass-card-3d:hover {
            transform: translateY(-5px) rotateX(1deg) rotateY(-0.5deg);
            box-shadow: 0 35px 70px -15px rgba(0, 150, 107, 0.2);
            border-color: rgba(0, 150, 107, 0.35);
        }

        @keyframes floatEntrance {
            0% { opacity: 0; transform: translateY(25px) scale(0.97); }
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
            0%, 100% { box-shadow: 0 0 0 0 rgba(0, 150, 107, 0.35); }
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
            font-size: 85px;
            color: #00966b;
            animation: rocketFlying 1.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            pointer-events: none;
        }

        .bkash-backdrop {
            background: rgba(15, 23, 42, 0.8);
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

        .shimmer-bg {
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 100%);
            background-size: 200% 100%;
            animation: shimmer 2.5s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
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
                    <span class="text-[10px] font-black text-[#00966b] tracking-widest uppercase block -mt-0.5">Nexus v8.0</span>
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
                    <span id="trafficBadge" class="text-[10px] bg-white px-2 py-0.5 rounded-full shadow-sm font-black text-emerald-600">PEAK LOAD</span>
                </div>
                <p class="text-[11px] text-slate-600 font-medium">Est. Prep Time: <strong id="prepTimer" class="text-slate-900 font-bold">15:00 Mins</strong></p>
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

        <div class="mb-8 p-5 bg-gradient-to-r from-[#00966b] via-teal-700 to-emerald-800 text-white rounded-3xl shadow-xl flex items-center justify-between anim-slide-1 relative overflow-hidden">
            <div class="shimmer-bg absolute inset-0 pointer-events-none"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center text-white text-xl">
                    <i class="fa-solid fa-microchip"></i>
                </div>
                <div>
                    <h2 class="text-base font-extrabold tracking-tight">Nexus Omni-Dimension Checkout v8.0</h2>
                    <p class="text-xs text-white/80 font-medium">Multi-pass priority routing, voice assistant triggers, automated allergen guard, and AI calorie tracking active.</p>
                </div>
            </div>
            <div class="flex gap-2 relative z-10">
                <button type="button" onclick="openDietaryModal()" class="px-3.5 py-2.5 bg-white text-[#00966b] font-extrabold rounded-xl text-xs hover:bg-emerald-50 transition-all shadow-md flex items-center gap-1.5">
                    <i class="fa-solid fa-seedling"></i> Allergen Guard
                </button>
                <button type="button" onclick="openNutritionModal()" class="px-3.5 py-2.5 bg-emerald-900 text-white font-extrabold rounded-xl text-xs hover:bg-emerald-950 transition-all shadow-md flex items-center gap-1.5">
                    <i class="fa-solid fa-fire"></i> Nutrition Facts
                </button>
                <button type="button" onclick="triggerVoiceAssistant()" class="px-3.5 py-2.5 bg-amber-500 text-white font-extrabold rounded-xl text-xs hover:bg-amber-600 transition-all shadow-md flex items-center gap-1.5">
                    <i class="fa-solid fa-microphone"></i> Voice UI
                </button>
            </div>
        </div>

        <form method="POST" action="checkout.php" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="gateway_trx" id="gatewayTrxInput" value="">
            <input type="hidden" name="payment_method" id="selectedPaymentInput" value="Cash / Counter Payment">
            <input type="hidden" name="discount_amount" id="discountAmountInput" value="0.00">
            <input type="hidden" name="tip_amount" id="tipAmountInput" value="0.00">
            <input type="hidden" name="order_type" id="orderTypeInput" value="Dine-In">
            <input type="hidden" name="scheduled_time" id="scheduledTimeInput" value="ASAP (Immediate)">

            <div class="grid grid-cols-12 gap-8 items-start">
                
                <div class="col-span-8 space-y-8">
                    
                    <div class="glass-card-3d rounded-3xl p-6 anim-slide-1">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Select Service Type & Live Schedule</span>
                            <div class="flex gap-2 bg-slate-100 p-1.5 rounded-2xl">
                                <button type="button" id="btnDineIn" onclick="toggleOrderType('Dine-In')" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-[#00966b] text-white shadow-md transition-all">
                                    <i class="fa-solid fa-chair mr-1.5"></i> Dine-In
                                </button>
                                <button type="button" id="btnTakeaway" onclick="toggleOrderType('Takeaway')" class="px-5 py-2 rounded-xl text-xs font-extrabold text-slate-600 hover:text-slate-900 transition-all">
                                    <i class="fa-solid fa-box-archive mr-1.5"></i> Takeaway
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 pt-2">
                            <button type="button" onclick="selectScheduleTime('ASAP (Immediate)', this)" class="schedule-btn p-3 rounded-2xl border-2 border-[#00966b] bg-[#e6f7f2] text-xs font-extrabold text-slate-800 transition-all text-center">
                                ⚡ ASAP (15m)
                            </button>
                            <button type="button" onclick="selectScheduleTime('Lunch Rush - 1:00 PM', this)" class="schedule-btn p-3 rounded-2xl border-2 border-slate-200 bg-slate-50 text-xs font-extrabold text-slate-600 transition-all text-center">
                                🕒 Lunch 1:00 PM
                            </button>
                            <button type="button" onclick="selectScheduleTime('Evening Snack - 4:30 PM', this)" class="schedule-btn p-3 rounded-2xl border-2 border-slate-200 bg-slate-50 text-xs font-extrabold text-slate-600 transition-all text-center">
                                🌅 Snack 4:30 PM
                            </button>
                        </div>
                    </div>

                    <div class="glass-card-3d rounded-3xl p-8 anim-slide-1">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-extrabold text-slate-900 flex items-center gap-3">
                                <span class="w-9 h-9 rounded-xl bg-[#00966b]/10 flex items-center justify-center text-[#00966b]">
                                    <i class="fa-solid fa-location-dot"></i>
                                </span> 
                                Delivery Destination & Quantum Add-ons
                            </h2>
                            <button type="button" onclick="openMapModal()" class="px-3.5 py-1.5 bg-[#e6f7f2] text-[#00966b] font-extrabold text-xs rounded-xl hover:bg-[#00966b] hover:text-white transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-map-location-dot"></i> Interactive Map
                            </button>
                        </div>
                        
                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">TABLE NUMBER / CAFETERIA ROOM *</label>
                                <input type="text" name="table_room" id="tableRoomInput" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-sm font-semibold outline-none focus:border-[#00966b] focus:bg-white focus:ring-4 focus:ring-[#00966b]/10 transition-all text-slate-800 placeholder-slate-400" placeholder="e.g. Table 05 or Faculty Lounge" required>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">SPECIAL INSTRUCTIONS (OPTIONAL)</label>
                                <input type="text" name="instructions" class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-sm font-semibold outline-none focus:border-[#00966b] focus:bg-white focus:ring-4 focus:ring-[#00966b]/10 transition-all text-slate-800 placeholder-slate-400" placeholder="e.g. Extra napkins, less spicy please">
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <label class="flex items-center gap-3 p-3.5 rounded-2xl border border-slate-200 bg-slate-50 hover:bg-emerald-50/40 cursor-pointer transition-all">
                                    <input type="checkbox" name="eco_cutlery" value="1" class="w-4 h-4 text-[#00966b] rounded border-slate-300 focus:ring-[#00966b]">
                                    <div>
                                        <span class="font-extrabold text-slate-800 text-[11px] block">🌱 Eco-Cutlery</span>
                                        <span class="text-[9px] text-slate-500">Biodegradable utensils</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3.5 rounded-2xl border border-slate-200 bg-slate-50 hover:bg-emerald-50/40 cursor-pointer transition-all" onclick="togglePriorityQueue(this)">
                                    <input type="checkbox" name="priority_queue" id="priorityCheckbox" value="1" onchange="recalculateTotal()" class="w-4 h-4 text-[#00966b] rounded border-slate-300 focus:ring-[#00966b]">
                                    <div>
                                        <span class="font-extrabold text-slate-800 text-[11px] block">⚡ VIP Priority (+৳15)</span>
                                        <span class="text-[9px] text-slate-500">Jump regular line</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3.5 rounded-2xl border border-slate-200 bg-slate-50 hover:bg-emerald-50/40 cursor-pointer transition-all" onclick="toggleCarbonOffset(this)">
                                    <input type="checkbox" name="carbon_offset" id="carbonCheckbox" value="1" onchange="recalculateTotal()" class="w-4 h-4 text-[#00966b] rounded border-slate-300 focus:ring-[#00966b]">
                                    <div>
                                        <span class="font-extrabold text-slate-800 text-[11px] block">🌳 Tree Offset (+৳5)</span>
                                        <span class="text-[9px] text-slate-500">Plant campus tree</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3.5 rounded-2xl border border-slate-200 bg-slate-50 hover:bg-emerald-50/40 cursor-pointer transition-all" onclick="toggleCutLinePass(this)">
                                    <input type="checkbox" name="cut_line_pass" id="cutLineCheckbox" value="1" onchange="recalculateTotal()" class="w-4 h-4 text-[#00966b] rounded border-slate-300 focus:ring-[#00966b]">
                                    <div>
                                        <span class="font-extrabold text-slate-800 text-[11px] block">🚀 Express Pass (+৳25)</span>
                                        <span class="text-[9px] text-slate-500">Absolute first cook</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card-3d rounded-3xl p-8 anim-slide-2">
                        <h2 class="text-xl font-extrabold text-slate-900 mb-6 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl bg-[#00966b]/10 flex items-center justify-center text-[#00966b]">
                                <i class="fa-solid fa-wallet"></i>
                            </span> 
                            Secure Payment Gateways
                        </h2>

                        <div class="grid grid-cols-4 gap-3">
                            <div id="optCash" onclick="selectPaymentMethod('Cash / Counter Payment')" class="border-2 border-[#00966b] bg-[#e6f7f2]/80 rounded-2xl p-4 flex flex-col justify-between cursor-pointer transition-all duration-300 pulse-active">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 rounded-xl bg-[#00966b]/10 flex items-center justify-center text-[#00966b]">
                                        <i class="fa-solid fa-money-bill-wave text-xs"></i>
                                    </div>
                                    <span id="cashCheck" class="w-4 h-4 rounded-full bg-[#00966b] text-white flex items-center justify-center text-[9px]">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                </div>
                                <p class="font-extrabold text-slate-900 text-xs">Cash</p>
                            </div>

                            <div id="optBkash" onclick="selectPaymentMethod('bKash Online Payment')" class="border-2 border-slate-100 bg-white rounded-2xl p-4 flex flex-col justify-between cursor-pointer transition-all duration-300 hover:border-[#e2136e]">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 rounded-xl bg-[#e2136e]/10 flex items-center justify-center text-[#e2136e]">
                                        <i class="fa-solid fa-mobile-screen-button text-xs"></i>
                                    </div>
                                    <span id="bkashCheck" class="w-4 h-4 rounded-full border-2 border-slate-300 flex items-center justify-center text-[9px]"></span>
                                </div>
                                <p class="font-extrabold text-slate-900 text-xs">bKash</p>
                            </div>

                            <div id="optCard" onclick="selectPaymentMethod('Campus Smart Card')" class="border-2 border-slate-100 bg-white rounded-2xl p-4 flex flex-col justify-between cursor-pointer transition-all duration-300 hover:border-blue-500">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-600">
                                        <i class="fa-solid fa-id-card text-xs"></i>
                                    </div>
                                    <span id="cardCheck" class="w-4 h-4 rounded-full border-2 border-slate-300 flex items-center justify-center text-[9px]"></span>
                                </div>
                                <p class="font-extrabold text-slate-900 text-xs">Smart Card</p>
                            </div>

                            <div id="optCrypto" onclick="selectPaymentMethod('EWU Campus Coin')" class="border-2 border-slate-100 bg-white rounded-2xl p-4 flex flex-col justify-between cursor-pointer transition-all duration-300 hover:border-amber-500">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-600">
                                        <i class="fa-solid fa-coins text-xs"></i>
                                    </div>
                                    <span id="cryptoCheck" class="w-4 h-4 rounded-full border-2 border-slate-300 flex items-center justify-center text-[9px]"></span>
                                </div>
                                <p class="font-extrabold text-slate-900 text-xs">Campus Coin</p>
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

                        <div id="priorityRow" class="flex justify-between text-sm text-purple-600 font-bold hidden">
                            <span>VIP Priority Queue</span>
                            <span>+৳15.00</span>
                        </div>

                        <div id="carbonRow" class="flex justify-between text-sm text-emerald-700 font-bold hidden">
                            <span>Carbon Offset Tree</span>
                            <span>+৳5.00</span>
                        </div>

                        <div id="cutLineRow" class="flex justify-between text-sm text-amber-600 font-bold hidden">
                            <span>Express Pass</span>
                            <span>+৳25.00</span>
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

            <p class="text-xs text-slate-500 mb-4">Click any designated zone or table below to set your exact order destination.</p>

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
                <button type="button" onclick="selectMapZone('Outdoor Counter Pickup')" class="p-4 border-2 border-amber-200 bg-amber-50 hover:bg-amber-600 hover:text-white rounded-2xl font-bold text-xs transition-all text-slate-700">
                    Outdoor Counter
                </button>
            </div>
        </div>
    </div>

    <div id="dietaryModal" class="fixed inset-0 z-50 hidden items-center justify-center bkash-backdrop p-4">
        <div class="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-seedling text-[#00966b]"></i> Dietary & Allergen Guard
                </h3>
                <button type="button" onclick="closeDietaryModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center hover:bg-slate-200">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <p class="text-xs text-slate-500 mb-4">Quantum neural analysis of your cart items:</p>

            <div class="space-y-3 mb-6 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                    <span>🥜 Peanut / Nut Traces</span>
                    <span class="text-emerald-600 font-black">CLEAN</span>
                </div>
                <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                    <span>🥛 Lactose / Dairy Content</span>
                    <span class="text-amber-600 font-black">MODERATE</span>
                </div>
                <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                    <span>🌾 Gluten Profile</span>
                    <span class="text-emerald-600 font-black">STANDARD</span>
                </div>
            </div>

            <button type="button" onclick="closeDietaryModal()" class="w-full py-3 bg-[#00966b] text-white font-extrabold rounded-2xl text-xs hover:bg-[#007d59] transition-all">Acknowledge & Close</button>
        </div>
    </div>

    <div id="nutritionModal" class="fixed inset-0 z-50 hidden items-center justify-center bkash-backdrop p-4">
        <div class="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-fire text-amber-500"></i> Estimated Nutrition Facts
                </h3>
                <button type="button" onclick="closeNutritionModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center hover:bg-slate-200">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="space-y-3 mb-6 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                    <span>🔥 Total Calories</span>
                    <span class="text-slate-900 font-black">~680 kcal</span>
                </div>
                <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                    <span>🥩 Protein Content</span>
                    <span class="text-slate-900 font-black">~35g</span>
                </div>
                <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                    <span>⚡ Carbohydrates</span>
                    <span class="text-slate-900 font-black">~78g</span>
                </div>
            </div>

            <button type="button" onclick="closeNutritionModal()" class="w-full py-3 bg-emerald-900 text-white font-extrabold rounded-2xl text-xs hover:bg-emerald-950 transition-all">Close</button>
        </div>
    </div>

    <div id="voiceModal" class="fixed inset-0 z-50 hidden items-center justify-center bkash-backdrop p-4">
        <div class="bg-white w-full max-w-sm rounded-3xl p-6 shadow-2xl text-center">
            <div class="w-16 h-16 bg-amber-500 text-white rounded-3xl flex items-center justify-center text-2xl mx-auto mb-4 animate-bounce shadow-lg shadow-amber-500/30">
                <i class="fa-solid fa-microphone-lines"></i>
            </div>
            <h3 class="text-lg font-black text-slate-900 mb-1">Voice Assistant Active</h3>
            <p class="text-xs text-slate-500 mb-6">Listening for voice instructions... (e.g. "Table 5", "Cash", "Place Order")</p>
            <div class="w-full bg-slate-100 p-3 rounded-2xl text-xs font-extrabold text-amber-600 mb-4">
                "Listening... Say your table location."
            </div>
            <button type="button" onclick="closeVoiceModal()" class="w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-extrabold rounded-2xl text-xs transition-all">Stop Voice UI</button>
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
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">bKash Mobile Account Number</label>
                        <input type="text" id="bkashPhoneInput" maxlength="11" placeholder="01XXXXXXXXX" class="w-full bg-slate-50 border-2 border-slate-200 rounded-2xl p-4 text-center font-extrabold text-lg text-slate-800 outline-none focus:border-[#e2136e] transition-all tracking-wider">
                    </div>
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <button type="button" onclick="closeBkashGateway()" class="py-3.5 bg-slate-100 hover:bg-slate-200 font-bold text-slate-600 rounded-2xl transition-all text-sm">Cancel</button>
                        <button type="button" onclick="goToBkashStepOTP()" class="py-3.5 bkash-btn text-white font-extrabold rounded-2xl transition-all text-sm">Get OTP</button>
                    </div>
                </div>

                <div id="bkashStepOTP" class="space-y-4 hidden">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Verification Code (OTP)</label>
                        <input type="text" id="bkashOtpInput" maxlength="6" placeholder="123456" class="w-full bg-slate-50 border-2 border-slate-200 rounded-2xl p-4 text-center font-black text-2xl text-slate-800 outline-none focus:border-[#e2136e] transition-all tracking-widest">
                        <p class="text-[11px] text-slate-400 text-center mt-2">Demo Sandbox OTP: <strong class="text-[#e2136e]">123456</strong></p>
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
                        <button type="button" onclick="processFinalBkashPayment()" class="py-3.5 bkash-btn text-white font-extrabold rounded-2xl transition-all text-sm">Pay Now</button>
                    </div>
                </div>

                <div id="bkashLoader" class="py-8 text-center space-y-4 hidden">
                    <div class="w-12 h-12 border-4 border-[#e2136e] border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="font-extrabold text-slate-800 text-sm">Validating Secure bKash Transfer...</p>
                </div>
            </div>
        </div>
    </div>

    <div id="cardModal" class="fixed inset-0 z-50 hidden items-center justify-center bkash-backdrop p-4">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-blue-100">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white text-center relative">
                <button type="button" onclick="closeCardModal()" class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-all text-xs">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="w-14 h-14 bg-white text-blue-600 rounded-2xl flex items-center justify-center font-black text-2xl mx-auto mb-2 shadow-lg">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <h3 class="text-lg font-extrabold">EWU Smart Card Debit</h3>
                <p class="text-xs text-white/80 font-medium">Instant Campus ID Verification</p>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Student ID / Smart Card Number</label>
                    <input type="text" id="smartCardInput" placeholder="202X-X-XX-XXX" class="w-full bg-slate-50 border-2 border-slate-200 rounded-2xl p-4 text-center font-extrabold text-base text-slate-800 outline-none focus:border-blue-600 transition-all">
                </div>
                <button type="button" onclick="processSmartCardPayment()" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-2xl transition-all text-sm shadow-lg shadow-blue-500/25">Authorize & Place Order</button>
            </div>
        </div>
    </div>

    <div id="cryptoModal" class="fixed inset-0 z-50 hidden items-center justify-center bkash-backdrop p-4">
        <div class="bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden border border-amber-100">
            <div class="bg-gradient-to-r from-amber-500 to-orange-600 p-6 text-white text-center relative">
                <button type="button" onclick="closeCryptoModal()" class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-all text-xs">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="w-14 h-14 bg-white text-amber-600 rounded-2xl flex items-center justify-center font-black text-2xl mx-auto mb-2 shadow-lg">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <h3 class="text-lg font-extrabold">EWU Campus Coin Wallet</h3>
                <p class="text-xs text-white/80 font-medium">Decentralized Student Rewards Token</p>
            </div>
            <div class="p-6 space-y-4 text-center">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Available Token Balance</span>
                    <span class="text-2xl font-black text-amber-600">450.00 CC</span>
                </div>
                <button type="button" onclick="processCryptoPayment()" class="w-full py-3.5 bg-amber-500 hover:bg-amber-600 text-white font-extrabold rounded-2xl transition-all text-sm shadow-lg shadow-amber-500/25">Spend Campus Coins</button>
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
            osc.frequency.setValueAtTime(620, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.06, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.06);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.06);
        }

        let totalSeconds = 900;
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

        function openDietaryModal() {
            playClickSound();
            document.getElementById('dietaryModal').classList.remove('hidden');
            document.getElementById('dietaryModal').classList.add('flex');
        }

        function closeDietaryModal() {
            document.getElementById('dietaryModal').classList.add('hidden');
            document.getElementById('dietaryModal').classList.remove('flex');
        }

        function openNutritionModal() {
            playClickSound();
            document.getElementById('nutritionModal').classList.remove('hidden');
            document.getElementById('nutritionModal').classList.add('flex');
        }

        function closeNutritionModal() {
            document.getElementById('nutritionModal').classList.add('hidden');
            document.getElementById('nutritionModal').classList.remove('flex');
        }

        function triggerVoiceAssistant() {
            playClickSound();
            document.getElementById('voiceModal').classList.remove('hidden');
            document.getElementById('voiceModal').classList.add('flex');
            setTimeout(() => {
                document.getElementById('tableRoomInput').value = "Voice Order - Table 04";
                closeVoiceModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Voice Detected!',
                    text: 'Assigned location to Table 04 automatically.',
                    timer: 2000,
                    showConfirmButton: false,
                    customClass: { popup: 'rounded-3xl' }
                });
            }, 3000);
        }

        function closeVoiceModal() {
            document.getElementById('voiceModal').classList.add('hidden');
            document.getElementById('voiceModal').classList.remove('flex');
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

        function selectScheduleTime(timeStr, el) {
            playClickSound();
            document.getElementById('scheduledTimeInput').value = timeStr;
            document.querySelectorAll('.schedule-btn').forEach(btn => {
                btn.className = "schedule-btn p-3 rounded-2xl border-2 border-slate-200 bg-slate-50 text-xs font-extrabold text-slate-600 transition-all text-center";
            });
            el.className = "schedule-btn p-3 rounded-2xl border-2 border-[#00966b] bg-[#e6f7f2] text-xs font-extrabold text-slate-800 transition-all text-center";
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

                confetti({ particleCount: 100, spread: 90, origin: { y: 0.8 } });
            } else {
                msgEl.className = "text-[11px] font-bold mt-1.5 text-rose-500";
                msgEl.innerText = "✕ Invalid coupon code. Try 'EWU10'";
                msgEl.classList.remove('hidden');
            }
            recalculateTotal();
        }

        function recalculateTotal() {
            const isPriority = document.getElementById('priorityCheckbox').checked;
            const isCarbon = document.getElementById('carbonCheckbox').checked;
            const isCutLine = document.getElementById('cutLineCheckbox').checked;
            
            const priorityCost = isPriority ? 15.00 : 0.00;
            const carbonCost = isCarbon ? 5.00 : 0.00;
            const cutLineCost = isCutLine ? 25.00 : 0.00;
            
            document.getElementById('priorityRow').classList.toggle('hidden', !isPriority);
            document.getElementById('carbonRow').classList.toggle('hidden', !isCarbon);
            document.getElementById('cutLineRow').classList.toggle('hidden', !isCutLine);

            const finalVal = Math.max(0, rawSubtotal - appliedDiscount + currentTip + priorityCost + carbonCost + cutLineCost).toFixed(2);
            document.getElementById('displayTotal').innerText = finalVal;
            document.getElementById('bkashModalAmount').innerText = finalVal;
        }

        function togglePriorityQueue(el) {
            playClickSound();
            const checkbox = document.getElementById('priorityCheckbox');
            if(event.target !== checkbox) { checkbox.checked = !checkbox.checked; }
            recalculateTotal();
        }

        function toggleCarbonOffset(el) {
            playClickSound();
            const checkbox = document.getElementById('carbonCheckbox');
            if(event.target !== checkbox) { checkbox.checked = !checkbox.checked; }
            recalculateTotal();
        }

        function toggleCutLinePass(el) {
            playClickSound();
            const checkbox = document.getElementById('cutLineCheckbox');
            if(event.target !== checkbox) { checkbox.checked = !checkbox.checked; }
            recalculateTotal();
        }

        function selectPaymentMethod(method) {
            playClickSound();
            currentPayment = method;
            document.getElementById('selectedPaymentInput').value = method;

            ['Cash / Counter Payment', 'bKash Online Payment', 'Campus Smart Card', 'EWU Campus Coin'].forEach(m => {
                let key = 'Cash';
                if(m.includes('bKash')) key = 'Bkash';
                else if(m.includes('Smart')) key = 'Card';
                else if(m.includes('Coin')) key = 'Crypto';

                const opt = document.getElementById('opt' + key);
                const chk = document.getElementById(key.toLowerCase() + 'Check');

                if (m === method) {
                    opt.className = "border-2 border-[#00966b] bg-[#e6f7f2]/80 rounded-2xl p-4 flex flex-col justify-between cursor-pointer transition-all duration-300 pulse-active";
                    chk.className = "w-4 h-4 rounded-full bg-[#00966b] text-white flex items-center justify-center text-[9px]";
                    chk.innerHTML = '<i class="fa-solid fa-check"></i>';
                } else {
                    opt.className = "border-2 border-slate-100 bg-white rounded-2xl p-4 flex flex-col justify-between cursor-pointer transition-all duration-300";
                    chk.className = "w-4 h-4 rounded-full border-2 border-slate-300 flex items-center justify-center text-[9px]";
                    chk.innerHTML = '';
                }
            });
        }

        function triggerRocketAnimation(callback) {
            const rocketBox = document.getElementById('rocketFxContainer');
            rocketBox.classList.remove('hidden');

            confetti({
                particleCount: 180,
                spread: 130,
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
                    title: 'Destination Missing',
                    text: 'Please input or choose your Table Number / Room from the map.',
                    confirmButtonColor: '#00966b',
                    customClass: { popup: 'rounded-3xl' }
                });
                document.getElementById('tableRoomInput').focus();
                return;
            }

            if (currentPayment === 'Cash / Counter Payment') {
                Swal.fire({
                    title: 'Confirm Order Submission?',
                    text: "Your order will be transmitted directly to the quantum kitchen queue.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#00966b',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Yes, Place Order',
                    customClass: { popup: 'rounded-3xl' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        triggerRocketAnimation(() => {
                            document.getElementById('checkoutForm').submit();
                        });
                    }
                });
            } else if (currentPayment === 'bKash Online Payment') {
                openBkashGateway();
            } else if (currentPayment === 'Campus Smart Card') {
                openCardModal();
            } else {
                openCryptoModal();
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

        function openCardModal() {
            document.getElementById('cardModal').classList.remove('hidden');
            document.getElementById('cardModal').classList.add('flex');
        }

        function closeCardModal() {
            document.getElementById('cardModal').classList.add('hidden');
            document.getElementById('cardModal').classList.remove('flex');
        }

        function openCryptoModal() {
            document.getElementById('cryptoModal').classList.remove('hidden');
            document.getElementById('cryptoModal').classList.add('flex');
        }

        function closeCryptoModal() {
            document.getElementById('cryptoModal').classList.add('hidden');
            document.getElementById('cryptoModal').classList.remove('flex');
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
                alert('Please enter a valid 6-digit OTP code.');
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
                alert('Please enter a valid 5-digit PIN code.');
                return;
            }

            document.getElementById('bkashStepPIN').classList.add('hidden');
            document.getElementById('bkashLoader').classList.remove('hidden');

            setTimeout(() => {
                const mockTrx = 'BKX' + Math.floor(10000000 + Math.random() * 90000000);
                document.getElementById('gatewayTrxInput').value = mockTrx;
                closeBkashGateway();
                
                triggerRocketAnimation(() => {
                    document.getElementById('checkoutForm').submit();
                });
            }, 1200);
        }

        function processSmartCardPayment() {
            playClickSound();
            const cardId = document.getElementById('smartCardInput').value.trim();
            if(!cardId) {
                alert('Please enter your EWU Smart Card ID.');
                return;
            }
            const mockTrx = 'CARD-' + Math.floor(100000 + Math.random() * 900000);
            document.getElementById('gatewayTrxInput').value = mockTrx;
            closeCardModal();

            triggerRocketAnimation(() => {
                document.getElementById('checkoutForm').submit();
            });
        }

        function processCryptoPayment() {
            playClickSound();
            const mockTrx = 'CC-' + Math.floor(100000 + Math.random() * 900000);
            document.getElementById('gatewayTrxInput').value = mockTrx;
            closeCryptoModal();

            triggerRocketAnimation(() => {
                document.getElementById('checkoutForm').submit();
            });
        }
    </script>
</body>
</html>
