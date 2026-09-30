<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

$conn = @mysqli_connect("localhost", "root", "", "campusbite_db");
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3;

if (isset($_POST['ajax_add_to_cart'])) {
    header('Content-Type: application/json');
    $food_id = intval($_POST['food_id']);
    $qty = max(1, intval($_POST['quantity']));
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');

    $check_q = mysqli_query($conn, "SELECT cart_id, quantity FROM cart WHERE user_id = $current_user_id AND food_id = $food_id");
    if ($check_q && mysqli_num_rows($check_q) > 0) {
        $row = mysqli_fetch_assoc($check_q);
        $new_qty = $row['quantity'] + $qty;
        mysqli_query($conn, "UPDATE cart SET quantity = $new_qty WHERE cart_id = {$row['cart_id']}");
    } else {
        mysqli_query($conn, "INSERT INTO cart (user_id, food_id, quantity) VALUES ($current_user_id, $food_id, $qty)");
    }

    $total_res = mysqli_query($conn, "SELECT SUM(quantity) as total FROM cart WHERE user_id = $current_user_id");
    $new_total = $total_res ? (mysqli_fetch_assoc($total_res)['total'] ?? 0) : 0;

    echo json_encode(['status' => 'success', 'cart_total' => (int)$new_total]);
    exit;
}

$cart_count_res = mysqli_query($conn, "SELECT SUM(quantity) as total FROM cart WHERE user_id = $current_user_id");
$total_cart_items = $cart_count_res ? (mysqli_fetch_assoc($cart_count_res)['total'] ?? 0) : 0;

$categories_result = mysqli_query($conn, "SELECT DISTINCT category FROM foods WHERE category IS NOT NULL AND category != ''");
$foods_result = mysqli_query($conn, "SELECT * FROM foods ORDER BY food_id DESC");
$total_food_count = $foods_result ? mysqli_num_rows($foods_result) : 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafeteria | AI Dynamic Food Menu</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #16a34a;
            --primary-hover: #15803d;
            --primary-light: #dcfce7;
            --primary-glow: rgba(22, 163, 74, 0.35);
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-sub: #64748b;
            --border: #e2e8f0;
            --shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
        }

        [data-theme="dark"] {
            --bg-main: #0b1120;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
            --border: #334155;
            --primary-light: rgba(22, 163, 74, 0.15);
            --shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; transition: background-color 0.4s ease, color 0.4s ease, border-color 0.4s ease; }
        body { display: flex; background: var(--bg-main); color: var(--text-main); min-height: 100vh; overflow-x: hidden; }

        .sidebar { width: 260px; background: var(--card-bg); border-right: 1px solid var(--border); padding: 24px 18px; display: flex; flex-direction: column; justify-content: space-between; position: fixed; height: 100vh; z-index: 100; }
        .brand { font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
        .brand-icon { background: linear-gradient(135deg, var(--primary), #22c55e); width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; box-shadow: 0 8px 18px var(--primary-glow); }

        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; justify-content: space-between; padding: 11px 14px; text-decoration: none; color: var(--text-sub); font-size: 13.5px; font-weight: 600; border-radius: 12px; }
        .nav-item.active a { background: var(--primary-light); color: var(--primary); font-weight: 700; }
        .nav-item a:hover { background: var(--primary-light); color: var(--primary); }

        .cart-badge { background: linear-gradient(135deg, var(--primary), #22c55e); color: #fff; font-size: 11px; padding: 2px 9px; border-radius: 20px; font-weight: 800; display: inline-block; }
        
        .cart-target-shake {
            animation: cartShake 0.7s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        @keyframes cartShake {
            10%, 90% { transform: scale(1.2) translate3d(-1px, 0, 0); }
            20%, 80% { transform: scale(1.3) translate3d(2px, -3px, 0); }
            30%, 50%, 70% { transform: scale(1.3) translate3d(-4px, -2px, 0); }
            40%, 60% { transform: scale(1.2) translate3d(4px, 0, 0); }
            100% { transform: scale(1) translate3d(0, 0, 0); }
        }

        .main-content { flex: 1; margin-left: 260px; padding: 24px 32px; width: calc(100% - 260px); max-width: 1440px; }

        .ai-banner { background: linear-gradient(135deg, #16a34a, #15803d); border-radius: 20px; padding: 20px 24px; color: #fff; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; box-shadow: 0 12px 30px var(--primary-glow); position: relative; overflow: hidden; }
        .ai-banner::after { content: ''; position: absolute; right: -20px; top: -20px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; }

        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: var(--card-bg); padding: 12px 22px; border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--shadow); position: relative; }
        .header-actions { display: flex; align-items: center; gap: 12px; }

        .search-box { position: relative; width: 300px; }
        .search-box input { width: 100%; background: var(--bg-main); border: 1px solid var(--border); border-radius: 11px; padding: 9px 38px 9px 38px; font-size: 13px; color: var(--text-main); outline: none; }
        .search-box .search-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--text-sub); }
        .search-box .mic-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--primary); cursor: pointer; font-size: 14px; transition: color 0.3s, transform 0.3s; }
        .search-box .mic-btn.listening { color: #ef4444; animation: pulse 1.5s infinite ease-in-out; }

        @keyframes pulse {
            0% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.25); }
            100% { transform: translateY(-50%) scale(1); }
        }

        .search-autocomplete { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 50; display: none; overflow: hidden; max-height: 220px; overflow-y: auto; }
        .autocomplete-item { padding: 10px 14px; font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); transition: background-color 0.3s ease; }
        .autocomplete-item:hover { background: var(--primary-light); color: var(--primary); }

        .action-icon-btn { background: var(--bg-main); border: 1px solid var(--border); width: 36px; height: 36px; border-radius: 11px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-main); font-size: 14px; }

        .filter-wrapper { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 16px; flex-wrap: wrap; }
        .category-container { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; }
        .cat-btn { background: var(--card-bg); border: 1px solid var(--border); padding: 8px 16px; border-radius: 30px; font-size: 12.5px; font-weight: 700; color: var(--text-sub); cursor: pointer; display: flex; align-items: center; gap: 6px; white-space: nowrap; transition: all 0.3s ease; }
        .cat-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        .price-filter { display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; background: var(--card-bg); padding: 6px 14px; border-radius: 12px; border: 1px solid var(--border); }

        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .food-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 18px; padding: 16px; box-shadow: var(--shadow); position: relative; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.4s ease, box-shadow 0.4s ease; }
        .food-card:hover { border-color: var(--primary); transform: translateY(-6px); box-shadow: 0 15px 35px -10px var(--primary-glow); }

        .badge-group { position: absolute; top: 14px; left: 14px; display: flex; align-items: center; gap: 6px; z-index: 2; }
        .rating-badge { background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px); color: #f59e0b; font-size: 11px; font-weight: 800; padding: 4px 8px; border-radius: 10px; display: flex; align-items: center; gap: 4px; }
        .bestseller-badge { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; font-size: 10px; font-weight: 800; padding: 4px 8px; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3); }

        .fav-btn { position: absolute; top: 14px; right: 14px; width: 32px; height: 32px; background: var(--card-bg); border-radius: 50%; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-sub); z-index: 2; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.3s ease; }
        .fav-btn.active { color: #ef4444; border-color: #ef4444; transform: scale(1.1); }

        .food-image-wrapper { width: 100%; height: 170px; border-radius: 14px; background: var(--bg-main); margin-bottom: 12px; display: flex; align-items: center; justify-content: center; padding: 8px; overflow: hidden; }
        .food-image-wrapper img { max-width: 100%; max-height: 100%; object-fit: cover; border-radius: 10px; transition: transform 0.5s ease-out; }

        .flying-square-item {
            position: fixed;
            z-index: 99999;
            object-fit: cover;
            border-radius: 16px;
            box-shadow: 0 20px 40px var(--primary-glow);
            border: 3px solid var(--primary);
            pointer-events: none;
            will-change: transform, opacity;
            top: 0;
            left: 0;
        }

        .quantity-controller { display: flex; align-items: center; background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; padding: 2px; gap: 4px; margin: 10px 0; }
        .q-btn { width: 26px; height: 26px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 6px; color: var(--text-main); font-weight: 700; cursor: pointer; transition: background-color 0.2s ease; }
        .q-input { width: 100%; text-align: center; border: none; background: transparent; font-size: 13px; font-weight: 800; color: var(--text-main); outline: none; }

        .btn-add-cart { width: 100%; background: linear-gradient(135deg, var(--primary), #22c55e); color: #fff; border: none; padding: 10px; border-radius: 11px; font-size: 13px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px var(--primary-glow); transition: all 0.4s ease; }
        
        .btn-add-cart.added-success { 
            background: linear-gradient(135deg, #0f172a, #334155) !important; 
            transform: scale(0.96); 
        }

        .cart-drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 2000; opacity: 0; pointer-events: none; transition: opacity 0.5s ease; }
        .cart-drawer-overlay.active { opacity: 1; pointer-events: auto; }
        .cart-drawer { position: fixed; top: 0; right: -380px; width: 360px; height: 100vh; background: var(--card-bg); z-index: 2001; padding: 24px; box-shadow: -10px 0 30px rgba(0,0,0,0.2); transition: right 0.5s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; justify-content: space-between; }
        .cart-drawer.active { right: 0; }

        .drawer-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border); }

        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; }
        .toast { background: #0f172a; color: #fff; padding: 12px 18px; border-radius: 12px; font-size: 13px; font-weight: 700; box-shadow: 0 10px 25px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; animation: slideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1), fadeOut 0.5s cubic-bezier(0.16, 1, 0.3, 1) 2.5s forwards; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { to { opacity: 0; transform: translateY(10px); } }

        .ai-tour-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(8px); z-index: 10000; opacity: 0; pointer-events: none; transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
        .ai-tour-backdrop.active { opacity: 1; pointer-events: auto; }

        .tour-highlighted-element { position: relative !important; z-index: 10001 !important; box-shadow: 0 0 0 4px var(--primary), 0 0 35px var(--primary-glow) !important; border-radius: 16px !important; transition: all 0.4s ease; }

        .ai-tour-card { position: fixed; z-index: 10002; width: 360px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 22px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35), 0 0 30px var(--primary-glow); transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; gap: 14px; }

        .ai-tour-progress-bar { width: 100%; height: 4px; background: var(--border); border-radius: 10px; overflow: hidden; }
        .ai-tour-progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), #22c55e); width: 20%; transition: width 0.4s ease; }

        .ai-tour-top { display: flex; align-items: center; justify-content: space-between; }
        .ai-badge { background: var(--primary-light); color: var(--primary); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 6px; }
        .ai-wave-icon { display: inline-block; width: 8px; height: 8px; background: var(--primary); border-radius: 50%; animation: pulseWave 1.5s infinite; }
        
        @keyframes pulseWave {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 var(--primary-glow); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(22, 163, 74, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
        }

        .ai-tour-title { font-size: 15px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; margin-top: 4px; }
        .ai-tour-body { font-size: 13px; color: var(--text-sub); line-height: 1.6; font-weight: 500; }
        
        .ai-tour-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
        .ai-tour-dots { display: flex; gap: 6px; }
        .ai-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--border); transition: all 0.4s ease; }
        .ai-dot.active { width: 18px; border-radius: 10px; background: var(--primary); }

        .voice-control-btn { background: var(--bg-main); border: 1px solid var(--border); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-main); font-size: 12px; }

        .confirm-modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(5px); z-index: 10005; display: none; align-items: center; justify-content: center; }
        .confirm-modal-overlay.active { display: flex; }
        .confirm-modal-card { background: var(--card-bg); border: 1px solid var(--border); width: 380px; border-radius: 20px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); text-align: center; animation: modalPop 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes modalPop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .confirm-modal-title { font-size: 16px; font-weight: 800; margin-bottom: 8px; color: var(--text-main); }
        .confirm-modal-body { font-size: 13px; color: var(--text-sub); margin-bottom: 20px; line-height: 1.5; }
        .confirm-modal-actions { display: flex; gap: 10px; justify-content: center; }
        .btn-confirm-yes { background: linear-gradient(135deg, var(--primary), #22c55e); color: #fff; border: none; padding: 10px 24px; border-radius: 11px; font-weight: 800; cursor: pointer; font-size: 13px; flex: 1; }
        .btn-confirm-no { background: var(--bg-main); color: var(--text-sub); border: 1px solid var(--border); padding: 10px 24px; border-radius: 11px; font-weight: 800; cursor: pointer; font-size: 13px; flex: 1; }
    </style>
</head>
<body>

    <div class="toast-container" id="toastContainer"></div>

    <div class="confirm-modal-overlay" id="confirmModalOverlay">
        <div class="confirm-modal-card">
            <div style="font-size: 32px; color: var(--primary); margin-bottom: 10px;"><i class="fa-solid fa-circle-question"></i></div>
            <div class="confirm-modal-title" id="confirmModalTitle">Add Item to Cart?</div>
            <div class="confirm-modal-body" id="confirmModalBody">Item price is ৳0. Do you want to add this to your cart?</div>
            <div class="confirm-modal-actions">
                <button class="btn-confirm-no" id="confirmBtnNo">No</button>
                <button class="btn-confirm-yes" id="confirmBtnYes">Yes</button>
            </div>
        </div>
    </div>

    <div class="ai-tour-backdrop" id="aiTourBackdrop"></div>
    <div class="ai-tour-card" id="aiTourCard" style="display: none;">
        <div class="ai-tour-progress-bar">
            <div class="ai-tour-progress-fill" id="aiTourProgressFill"></div>
        </div>
        <div class="ai-tour-top">
            <div class="ai-badge"><span class="ai-wave-icon"></span> AI Voice Assistant</div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button class="voice-control-btn" id="aiVoiceToggle" title="Toggle Voice Voiceover"><i class="fa-solid fa-volume-high"></i></button>
                <button id="aiTourSkip" style="background: none; border: none; font-size: 16px; color: var(--text-sub); cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div>
            <div class="ai-tour-title" id="aiTourTitle"><i class="fa-solid fa-wand-magic-sparkles" style="color: var(--primary);"></i> Loading...</div>
            <div class="ai-tour-body" id="aiTourText">Please wait...</div>
        </div>
        <div class="ai-tour-actions">
            <div class="ai-tour-dots" id="aiTourDots">
                <span class="ai-dot active"></span>
                <span class="ai-dot"></span>
                <span class="ai-dot"></span>
                <span class="ai-dot"></span>
                <span class="ai-dot"></span>
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="action-icon-btn" id="aiTourPrev" style="width: auto; padding: 0 12px; font-weight: 700; font-size: 12px;">Back</button>
                <button class="btn-add-cart" id="aiTourNext" style="width: auto; padding: 8px 16px; font-size: 12px;">Next <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>
    </div>

    <div class="cart-drawer-overlay" id="drawerOverlay"></div>
    <div class="cart-drawer" id="cartDrawer">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-weight: 800;"><i class="fa-solid fa-bag-shopping" style="color: var(--primary);"></i> Live Cart Overview</h3>
                <button id="closeDrawer" style="background: none; border: none; font-size: 18px; color: var(--text-sub); cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="drawerItemsList" style="max-height: 60vh; overflow-y: auto;"></div>
        </div>

        <div>
            <div style="display: flex; justify-content: space-between; font-weight: 800; margin-bottom: 14px; font-size: 15px;">
                <span>Total Amount:</span>
                <span id="drawerTotalAmount" style="color: var(--primary);">৳0.00</span>
            </div>
            <a href="checkout.php" class="btn-add-cart" style="text-decoration: none; text-align: center;">Proceed to Checkout <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>

    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                <span>Cafeteria</span>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><span><i class="fa-solid fa-house"></i> <span>Dashboard</span></span></a></li>
                <li class="nav-item active"><a href="menu.php"><span><i class="fa-solid fa-book-open"></i> <span>Food Menu</span></span></a></li>
                <li class="nav-item" id="tourCartNav">
                    <a href="#" id="openCartDrawer">
                        <span id="cartNavTarget"><i class="fa-solid fa-cart-shopping"></i> <span id="cartIconNav">My Cart</span></span>
                        <span class="cart-badge" id="cartBadgeCount"><?= $total_cart_items; ?></span>
                    </a>
                </li>
                <li class="nav-item"><a href="checkout.php"><span><i class="fa-solid fa-credit-card"></i> <span>Checkout</span></span></a></li>
                <li class="nav-item"><a href="orders.php"><span><i class="fa-solid fa-clock-rotate-left"></i> <span>My Orders</span></span></a></li>
            </ul>
        </div>
        <div class="nav-menu">
            <li class="nav-item"><a href="../logout.php" style="color: #ef4444;"><span><i class="fa-solid fa-right-from-bracket"></i> <span>Sign Out</span></span></a></li>
        </div>
    </div>

    <div class="main-content">
        <div class="ai-banner" id="tourAiBanner">
            <div>
                <span style="background: rgba(255,255,255,0.2); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.5px;"><i class="fa-solid fa-wand-magic-sparkles"></i> AI AUTO-ORDER ASSISTANT</span>
                <h2 style="font-size: 18px; font-weight: 800; margin-top: 6px;">Say Food Name, AI will tell price & ask to buy!</h2>
                <p style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Try saying "Chicken", "Burger", or any food name.</p>
            </div>
            <button id="startAiTourBtn" type="button" style="background: #fff; color: var(--primary); border: none; padding: 9px 16px; border-radius: 12px; font-weight: 800; font-size: 12px; cursor: pointer; position: relative; z-index: 10; box-shadow: 0 4px 10px rgba(0,0,0,0.1);"><i class="fa-solid fa-play"></i> Start AI Tour</button>
        </div>

        <div class="top-header">
            <div style="font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-utensils" style="color: var(--primary);"></i> Menu Catalog</div>
            
            <div class="header-actions">
                <div class="search-box" id="tourSearchBox">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="menuSearchInput" placeholder="Type food & press enter..." autocomplete="off">
                    <button class="mic-btn" id="voiceSearchBtn" title="Speak to Order"><i class="fa-solid fa-microphone"></i></button>
                    <div class="search-autocomplete" id="searchAutocomplete"></div>
                </div>

                <button class="action-icon-btn" id="soundToggle" title="Toggle Sound Effect"><i class="fa-solid fa-volume-high" style="color: var(--primary);"></i></button>
                <button class="action-icon-btn" id="themeToggle" title="Toggle Dark Mode"><i class="fa-solid fa-moon"></i></button>
            </div>
        </div>

        <div class="filter-wrapper" id="tourFilterSection">
            <div class="category-container">
                <button class="cat-btn active" data-category="all">
                    <i class="fa-solid fa-border-all"></i> All Items 
                    <span style="background: rgba(0,0,0,0.08); font-size: 11px; padding: 1px 6px; border-radius: 10px;"><?= $total_food_count; ?></span>
                </button>
                <?php if ($categories_result && mysqli_num_rows($categories_result) > 0): ?>
                    <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                        <button class="cat-btn" data-category="<?= htmlspecialchars(strtolower($cat['category'])); ?>">
                            <?= htmlspecialchars(ucfirst($cat['category'])); ?>
                        </button>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>

            <div class="price-filter">
                <span>Max Price: ৳<span id="priceValue">500</span></span>
                <input type="range" id="priceRange" min="10" max="500" value="500" step="10" style="accent-color: var(--primary); cursor: pointer;">
            </div>
        </div>

        <div class="menu-grid" id="foodGrid">
            <?php if ($foods_result && mysqli_num_rows($foods_result) > 0): ?>
                <?php while ($food = mysqli_fetch_assoc($foods_result)): ?>
                    <?php 
                        $category_clean = strtolower($food['category'] ?? 'beverage');
                        $food_name = $food['food_name'] ?? 'Food Item';
                        $img_name = trim($food['image'] ?? '');
                        $img = !empty($img_name) ? ((strpos($img_name, 'http') === 0) ? $img_name : "../uploads/" . $img_name) : "https://placehold.co/300x300?text=Food";
                        $price = floatval($food['price'] ?? 0);
                        $rating = number_format(rand(42, 50) / 10, 1);
                        $is_bestseller = ($rating >= 4.7);
                    ?>
                    <div class="food-card" id="foodCard_<?= $food['food_id']; ?>" data-id="<?= $food['food_id']; ?>" data-price="<?= $price; ?>" data-category="<?= htmlspecialchars($category_clean); ?>" data-name="<?= htmlspecialchars(strtolower($food_name)); ?>">
                        
                        <div class="badge-group">
                            <div class="rating-badge"><i class="fa-solid fa-star"></i> <?= $rating; ?></div>
                            <?php if ($is_bestseller): ?>
                                <div class="bestseller-badge">Popular</div>
                            <?php endif; ?>
                        </div>

                        <button class="fav-btn" type="button"><i class="fa-solid fa-heart"></i></button>

                        <div class="food-image-wrapper">
                            <img class="food-img" src="<?= htmlspecialchars($img); ?>" alt="<?= htmlspecialchars($food_name); ?>">
                        </div>

                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                <h3 class="card-food-name" style="font-size: 15px; font-weight: 800;"><?= htmlspecialchars($food_name); ?></h3>
                                <span style="font-size: 15px; font-weight: 800; color: var(--primary);">৳<?= number_format($price, 2); ?></span>
                            </div>

                            <div class="quantity-controller">
                                <button class="q-btn btn-minus" type="button">-</button>
                                <input type="number" class="q-input" value="1" min="1" readonly data-base-price="<?= $price; ?>">
                                <button class="q-btn btn-plus" type="button">+</button>
                            </div>

                            <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: var(--text-sub); margin-bottom: 10px;">
                                <span>Total:</span>
                                <span class="calculated-price" style="color: var(--text-main);">৳<?= number_format($price, 2); ?></span>
                            </div>
                        </div>

                        <button class="btn-add-cart" data-id="<?= $food['food_id']; ?>" data-name="<?= htmlspecialchars($food_name); ?>">
                            <i class="fa-solid fa-cart-shopping"></i> Add To Cart
                        </button>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let isSoundEnabled = true;
        let isVoiceEnabled = true;
        let localCartState = [];
        let pendingFoodCard = null;
        let waitingForFoodConfirmation = false;

        const confirmModal = document.getElementById('confirmModalOverlay');
        const confirmTitle = document.getElementById('confirmModalTitle');
        const confirmBody = document.getElementById('confirmModalBody');
        const confirmYesBtn = document.getElementById('confirmBtnYes');
        const confirmNoBtn = document.getElementById('confirmBtnNo');

        function showConfirmationModal(card) {
            pendingFoodCard = card;
            waitingForFoodConfirmation = true;

            const addBtn = card.querySelector('.btn-add-cart');
            const foodName = addBtn.getAttribute('data-name');
            const price = card.getAttribute('data-price');

            confirmTitle.innerText = `Add ${foodName}?`;
            confirmBody.innerText = `${foodName} price is ৳${price}. Do you want to add this to your cart?`;
            confirmModal.classList.add('active');

            const promptMsg = `${foodName} price is ${price} Taka. Do you want to add this to cart?`;
            speakText(promptMsg);
        }

        function handleModalDecision(isConfirmed) {
            confirmModal.classList.remove('active');
            if (isConfirmed && pendingFoodCard) {
                const addBtn = pendingFoodCard.querySelector('.btn-add-cart');
                const foodName = addBtn.getAttribute('data-name');
                
                pendingFoodCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                addBtn.click();

                speakText(`${foodName} is successfully added to your cart!`);
                showToast(`🤖 AI Assistant: Added ${foodName} to cart!`);
            } else if (!isConfirmed && waitingForFoodConfirmation) {
                speakText("Alright, order cancelled.");
                showToast("Order cancelled.");
            }
            waitingForFoodConfirmation = false;
            pendingFoodCard = null;
        }

        confirmYesBtn.addEventListener('click', () => handleModalDecision(true));
        confirmNoBtn.addEventListener('click', () => handleModalDecision(false));

        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        function playBeep(freq = 600, duration = 0.08) {
            if (!isSoundEnabled) return;
            if (audioCtx.state === 'suspended') audioCtx.resume();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.frequency.value = freq;
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            gain.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + duration);
            osc.stop(audioCtx.currentTime + duration);
        }

        const soundBtn = document.getElementById('soundToggle');
        soundBtn.addEventListener('click', () => {
            isSoundEnabled = !isSoundEnabled;
            soundBtn.innerHTML = isSoundEnabled ? 
                '<i class="fa-solid fa-volume-high" style="color: var(--primary);"></i>' : 
                '<i class="fa-solid fa-volume-xmark" style="color: #ef4444;"></i>';
            showToast(isSoundEnabled ? "Sound Enabled" : "Sound Muted");
        });

        function showToast(msg) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `<i class="fa-solid fa-circle-check" style="color: var(--primary);"></i> ${msg}`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3200);
        }

        const themeBtn = document.getElementById('themeToggle');
        themeBtn.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            const target = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', target);
            themeBtn.innerHTML = target === 'dark' ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
        });

        const searchInput = document.getElementById('menuSearchInput');
        const autoBox = document.getElementById('searchAutocomplete');

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            filterGrid();

            if (query.length < 1) {
                autoBox.style.display = 'none';
                return;
            }

            autoBox.innerHTML = '';
            let matches = 0;

            document.querySelectorAll('.food-card').forEach(card => {
                const name = card.getAttribute('data-name');
                const price = card.getAttribute('data-price');

                if (name.includes(query) && matches < 5) {
                    matches++;
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item';
                    item.innerHTML = `<span>${card.querySelector('.card-food-name').innerText}</span> <span style="color: var(--primary);">৳${price}</span>`;
                    item.addEventListener('click', () => {
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        card.style.borderColor = 'var(--primary)';
                        autoBox.style.display = 'none';
                        searchInput.value = card.querySelector('.card-food-name').innerText;
                    });
                    autoBox.appendChild(item);
                }
            });

            autoBox.style.display = matches > 0 ? 'block' : 'none';
        });

        function handleAiAutoBuy(inputText) {
            const text = inputText.toLowerCase().trim();

            if (waitingForFoodConfirmation) {
                const positiveKeywords = ['yes', 'হ্যাঁ', 'ha', 'ok', 'sure', 'yep', 'ya', 'ha0'];
                const negativeKeywords = ['no', 'না', 'nah', 'not'];

                const isYes = positiveKeywords.some(word => text.includes(word));
                const isNo = negativeKeywords.some(word => text.includes(word));

                if (isYes) {
                    handleModalDecision(true);
                    return;
                } else if (isNo) {
                    handleModalDecision(false);
                    return;
                }
            }

            let cleanQuery = text
                .replace(/please/g, '')
                .replace(/add/g, '')
                .replace(/to cart/g, '')
                .replace(/buy/g, '')
                .replace(/order/g, '')
                .replace(/i want/g, '')
                .replace(/give me/g, '')
                .trim();

            let matchedCard = null;
            let highestMatchScore = 0;

            document.querySelectorAll('.food-card').forEach(card => {
                const foodName = card.getAttribute('data-name');
                if (foodName.includes(text) || foodName.includes(cleanQuery) || text.includes(foodName)) {
                    let score = foodName.length;
                    if (score > highestMatchScore) {
                        highestMatchScore = score;
                        matchedCard = card;
                    }
                }
            });

            if (matchedCard) {
                matchedCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                matchedCard.style.borderColor = 'var(--primary)';
                setTimeout(() => { matchedCard.style.borderColor = 'var(--border)'; }, 2000);
                showConfirmationModal(matchedCard);
            } else {
                speakText(`Sorry, I could not find any food item matching ${inputText}.`);
                showToast(`🤖 AI Assistant: Item "${inputText}" not found.`);
            }
        }

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const queryVal = searchInput.value.trim();
                if (queryVal) {
                    searchInput.value = '';
                    autoBox.style.display = 'none';
                    handleAiAutoBuy(queryVal);
                }
            }
        });

        const voiceSearchBtn = document.getElementById('voiceSearchBtn');
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (SpeechRecognition) {
            const recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = 'en-US';

            voiceSearchBtn.addEventListener('click', () => {
                try { recognition.start(); } catch (e) { recognition.stop(); }
            });

            recognition.onstart = () => {
                voiceSearchBtn.classList.add('listening');
                showToast("AI Listening... Speak food name");
            };

            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                searchInput.value = transcript;
                handleAiAutoBuy(transcript);
            };

            recognition.onerror = (event) => {
                showToast("Voice recognition error: " + event.error);
                voiceSearchBtn.classList.remove('listening');
            };

            recognition.onend = () => {
                voiceSearchBtn.classList.remove('listening');
            };
        }

        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target)) autoBox.style.display = 'none';
        });

        const priceRange = document.getElementById('priceRange');
        const priceValue = document.getElementById('priceValue');

        priceRange.addEventListener('input', (e) => {
            priceValue.innerText = e.target.value;
            filterGrid();
        });

        function filterGrid() {
            const query = searchInput.value.toLowerCase().trim();
            const maxPrice = parseFloat(priceRange.value);
            const activeCat = document.querySelector('.cat-btn.active').getAttribute('data-category');

            document.querySelectorAll('.food-card').forEach(card => {
                const name = card.getAttribute('data-name');
                const price = parseFloat(card.getAttribute('data-price'));
                const cat = card.getAttribute('data-category');

                const matchesSearch = name.includes(query);
                const matchesPrice = price <= maxPrice;
                const matchesCat = (activeCat === 'all' || cat === activeCat);

                card.style.display = (matchesSearch && matchesPrice && matchesCat) ? 'flex' : 'none';
            });
        }

        document.querySelectorAll('.cat-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                filterGrid();
            });
        });

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-plus') || e.target.classList.contains('btn-minus')) {
                playBeep(400, 0.05);
                const card = e.target.closest('.food-card');
                const input = card.querySelector('.q-input');
                const priceElem = card.querySelector('.calculated-price');
                const basePrice = parseFloat(input.getAttribute('data-base-price'));
                let currentVal = parseInt(input.value);

                if (e.target.classList.contains('btn-plus')) currentVal++;
                else if (e.target.classList.contains('btn-minus') && currentVal > 1) currentVal--;

                input.value = currentVal;
                priceElem.innerText = `৳${(basePrice * currentVal).toFixed(2)}`;
            }

            if (e.target.closest('.fav-btn')) {
                playBeep(800, 0.06);
                const btn = e.target.closest('.fav-btn');
                btn.classList.toggle('active');
                showToast(btn.classList.contains('active') ? "Saved to Favorites!" : "Removed from Favorites");
            }
        });

        const drawer = document.getElementById('cartDrawer');
        const overlay = document.getElementById('drawerOverlay');

        document.getElementById('openCartDrawer').addEventListener('click', (e) => {
            e.preventDefault();
            renderLiveCart();
            drawer.classList.add('active');
            overlay.classList.add('active');
        });

        document.getElementById('closeDrawer').addEventListener('click', closeCartDrawer);
        overlay.addEventListener('click', closeCartDrawer);

        function closeCartDrawer() {
            drawer.classList.remove('active');
            overlay.classList.remove('active');
        }

        function updateCartDrawerUI(name, price, qty) {
            const existing = localCartState.find(item => item.name === name);
            if (existing) {
                existing.qty += qty;
            } else {
                localCartState.push({ name, price, qty });
            }
            renderLiveCart();
        }

        function renderLiveCart() {
            const list = document.getElementById('drawerItemsList');
            const totalElem = document.getElementById('drawerTotalAmount');

            if (localCartState.length === 0) {
                list.innerHTML = `
                    <div style="font-size: 13px; color: var(--text-sub); text-align: center; padding: 40px 0;">
                        <i class="fa-solid fa-basket-shopping" style="font-size: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>Your cart is empty.</p>
                    </div>`;
                totalElem.innerText = `৳0.00`;
                return;
            }

            let total = 0;
            list.innerHTML = '';
            localCartState.forEach((item, index) => {
                const sub = item.price * item.qty;
                total += sub;
                
                const div = document.createElement('div');
                div.className = 'drawer-item';
                div.innerHTML = `
                    <div>
                        <div style="font-weight: 800; font-size: 13px;">${item.name}</div>
                        <div style="font-size: 11px; color: var(--text-sub);">৳${item.price} x ${item.qty}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-weight: 800; font-size: 13px; color: var(--primary);">৳${sub.toFixed(2)}</span>
                        <button onclick="removeCartItem(${index})" style="background: none; border: none; color: #ef4444; cursor: pointer;"><i class="fa-solid fa-trash"></i></button>
                    </div>
                `;
                list.appendChild(div);
            });

            totalElem.innerText = `৳${total.toFixed(2)}`;
        }

        function removeCartItem(index) {
            localCartState.splice(index, 1);
            renderLiveCart();
            showToast("Item Removed");
        }

        function flyToCart(card) {
            const imgElem = card.querySelector('.food-img');
            const targetNav = document.getElementById('tourCartNav');

            if (!imgElem || !targetNav) return;

            const imgRect = imgElem.getBoundingClientRect();
            const targetRect = targetNav.getBoundingClientRect();

            const initialWidth = imgRect.width;
            const initialHeight = imgRect.height;

            const startX = imgRect.left;
            const startY = imgRect.top;

            const endX = targetRect.left + (targetRect.width / 2) - 20;
            const endY = targetRect.top + (targetRect.height / 2) - 20;

            const flyer = document.createElement('img');
            flyer.src = imgElem.src;
            flyer.className = 'flying-square-item';

            flyer.style.width = `${initialWidth}px`;
            flyer.style.height = `${initialHeight}px`;

            document.body.appendChild(flyer);

            const duration = 850;
            const startTime = performance.now();

            function cubicBezier(t) {
                return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
            }

            function animateFlyer(now) {
                const elapsed = now - startTime;
                let progress = Math.min(elapsed / duration, 1);
                const ease = cubicBezier(progress);

                const currX = startX + (endX - startX) * ease;
                const arcY = -180 * Math.sin(progress * Math.PI);
                const currY = startY + (endY - startY) * ease + arcY;

                const scale = 1 - (progress * 0.75);
                const rotation = progress * 360;
                const rotateY = progress * 180;
                const opacity = progress > 0.85 ? (1 - progress) / 0.15 : 1;

                flyer.style.transform = `translate3d(${currX}px, ${currY}px, 0) scale(${scale}) rotate(${rotation}deg) rotateY(${rotateY}deg)`;
                flyer.style.opacity = opacity;

                if (progress < 1) {
                    requestAnimationFrame(animateFlyer);
                } else {
                    flyer.remove();
                    triggerTargetCartShake();
                }
            }

            requestAnimationFrame(animateFlyer);
        }

        function triggerTargetCartShake() {
            const targetNav = document.getElementById('tourCartNav');
            playBeep(1200, 0.08);

            if (targetNav) {
                targetNav.classList.remove('cart-target-shake');
                void targetNav.offsetWidth;
                targetNav.classList.add('cart-target-shake');
            }
        }

        document.querySelectorAll('.btn-add-cart').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.id === 'aiTourNext') return;
                playBeep(900, 0.1);
                const card = btn.closest('.food-card');
                const quantity = parseInt(card.querySelector('.q-input').value) || 1;
                const foodId = btn.getAttribute('data-id');
                const foodName = btn.getAttribute('data-name');
                const foodPrice = parseFloat(card.getAttribute('data-price'));

                flyToCart(card);

                const originalBtnText = btn.innerHTML;
                btn.classList.add('added-success');
                btn.innerHTML = `<i class="fa-solid fa-check"></i> Added!`;

                setTimeout(() => {
                    btn.classList.remove('added-success');
                    btn.innerHTML = originalBtnText;
                }, 1800);

                updateCartDrawerUI(foodName, foodPrice, quantity);
                showToast(`Added ${quantity}x ${foodName} to Cart`);

                const formData = new FormData();
                formData.append('ajax_add_to_cart', '1');
                formData.append('food_id', foodId);
                formData.append('quantity', quantity);

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            document.getElementById('cartBadgeCount').innerText = data.cart_total;
                        }
                    })
                    .catch(err => {
                        console.error('AJAX Error:', err);
                    });
            });
        });

        const tourSteps = [
            {
                element: '#tourAiBanner',
                title: 'AI Auto-Order Assistant',
                text: 'Try speaking any food name to test the conversational price prompt and confirmation feature!',
                speech: 'Try speaking any food name to test the conversational price prompt and confirmation feature!'
            },
            {
                element: '#tourSearchBox',
                title: 'AI Smart Input',
                text: 'Type food name and press Enter, or use the microphone.',
                speech: 'Type food name and press Enter, or use the microphone.'
            },
            {
                element: '#tourFilterSection',
                title: 'Dynamic Filter System',
                text: 'Filter menu items by category or price range easily.',
                speech: 'Filter menu items by category or price range easily.'
            },
            {
                element: '.food-card',
                title: 'Interactive Food Cards',
                text: 'Check item ratings and add to cart with clean animations.',
                speech: 'Check item ratings and add to cart with clean animations.'
            },
            {
                element: '#tourCartNav',
                title: 'Live Cart Drawer',
                text: 'Open cart side drawer anytime to review and checkout.',
                speech: 'Open cart side drawer anytime to review and checkout.'
            }
        ];

        let currentTourIndex = 0;
        let speechSynth = window.speechSynthesis;
        let selectedVoice = null;

        function loadBangladeshiAccentVoice() {
            if (!speechSynth) return;
            const voices = speechSynth.getVoices();
            selectedVoice = voices.find(v => v.lang === 'en-BD' || v.lang.includes('BD')) || 
                            voices.find(v => v.lang === 'en-IN' || v.lang.includes('IN')) || 
                            voices.find(v => v.lang.includes('en')) || null;
        }

        if (speechSynth) {
            loadBangladeshiAccentVoice();
            if (speechSynth.onvoiceschanged !== undefined) {
                speechSynth.onvoiceschanged = loadBangladeshiAccentVoice;
            }
        }

        function speakText(text) {
            if (!speechSynth || !isVoiceEnabled) return;
            speechSynth.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            if (selectedVoice) utterance.voice = selectedVoice;
            utterance.rate = 0.95;
            utterance.pitch = 1.0;
            speechSynth.speak(utterance);
        }

        const backdrop = document.getElementById('aiTourBackdrop');
        const card = document.getElementById('aiTourCard');
        const titleEl = document.getElementById('aiTourTitle');
        const textEl = document.getElementById('aiTourText');
        const fillEl = document.getElementById('aiTourProgressFill');
        const voiceToggleBtn = document.getElementById('aiVoiceToggle');

        voiceToggleBtn.addEventListener('click', () => {
            isVoiceEnabled = !isVoiceEnabled;
            voiceToggleBtn.innerHTML = isVoiceEnabled ? 
                '<i class="fa-solid fa-volume-high"></i>' : 
                '<i class="fa-solid fa-volume-xmark" style="color: #ef4444;"></i>';
            if (!isVoiceEnabled && speechSynth) speechSynth.cancel();
            else speakText(tourSteps[currentTourIndex].speech);
        });

        function startAiTour() {
            currentTourIndex = 0;
            backdrop.classList.add('active');
            card.style.display = 'flex';
            renderTourStep();
        }

        function clearHighlightedElements() {
            document.querySelectorAll('.tour-highlighted-element').forEach(el => {
                el.classList.remove('tour-highlighted-element');
            });
        }

        function renderTourStep() {
            playBeep(750, 0.08);
            clearHighlightedElements();

            const step = tourSteps[currentTourIndex];
            const targetEl = document.querySelector(step.element);

            if (!targetEl) return;

            targetEl.classList.add('tour-highlighted-element');
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

            setTimeout(() => {
                const rect = targetEl.getBoundingClientRect();
                
                let popLeft = rect.left;
                let popTop = rect.bottom + 18;

                if (popTop + 240 > window.innerHeight) {
                    popTop = rect.top - 250;
                }
                if (popLeft + 360 > window.innerWidth) {
                    popLeft = window.innerWidth - 380;
                }

                card.style.left = `${Math.max(20, popLeft)}px`;
                card.style.top = `${Math.max(20, popTop)}px`;

                titleEl.innerHTML = `<i class="fa-solid fa-wand-magic-sparkles" style="color: var(--primary);"></i> ${step.title}`;
                textEl.innerText = step.text;

                const progressPercent = ((currentTourIndex + 1) / tourSteps.length) * 100;
                fillEl.style.width = `${progressPercent}%`;

                const dots = document.querySelectorAll('.ai-dot');
                dots.forEach((dot, index) => {
                    if (index === currentTourIndex) dot.classList.add('active');
                    else dot.classList.remove('active');
                });

                speakText(step.speech);

                document.getElementById('aiTourPrev').style.display = currentTourIndex === 0 ? 'none' : 'block';
                document.getElementById('aiTourNext').innerHTML = currentTourIndex === tourSteps.length - 1 ? 'Finish <i class="fa-solid fa-check"></i>' : 'Next <i class="fa-solid fa-arrow-right"></i>';
            }, 250);
        }

        function closeAiTour() {
            if (speechSynth) speechSynth.cancel();
            clearHighlightedElements();
            backdrop.classList.remove('active');
            card.style.display = 'none';
            showToast("AI Tour Completed!");
        }

        document.addEventListener('DOMContentLoaded', () => {
            const tourBtn = document.getElementById('startAiTourBtn');
            if (tourBtn) {
                tourBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    startAiTour();
                });
            }

            document.getElementById('aiTourNext').addEventListener('click', () => {
                if (currentTourIndex < tourSteps.length - 1) {
                    currentTourIndex++;
                    renderTourStep();
                } else {
                    closeAiTour();
                }
            });

            document.getElementById('aiTourPrev').addEventListener('click', () => {
                if (currentTourIndex > 0) {
                    currentTourIndex--;
                    renderTourStep();
                }
            });

            document.getElementById('aiTourSkip').addEventListener('click', closeAiTour);
        });
    </script>
</body>
</html>
