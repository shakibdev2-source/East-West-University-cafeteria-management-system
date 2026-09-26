<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = @mysqli_connect("localhost", "root", "", "campusbite_db");
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 1;

$menu_count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM foods");
$total_menu_items = mysqli_fetch_assoc($menu_count_res)['total'] ?? 0;

$cart_count_res = mysqli_query($conn, "SELECT SUM(quantity) as total FROM cart WHERE user_id = $current_user_id");
$total_cart_items = $cart_count_res ? (mysqli_fetch_assoc($cart_count_res)['total'] ?? 0) : 0;

$orders_count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE user_id = $current_user_id");
$total_orders = mysqli_fetch_assoc($orders_count_res)['total'] ?? 0;

$recent_orders_query = "SELECT * FROM orders WHERE user_id = $current_user_id ORDER BY order_id DESC LIMIT 4";
$recent_orders_result = mysqli_query($conn, $recent_orders_query);

$featured_foods_query = "SELECT * FROM foods ORDER BY food_id DESC LIMIT 4";
$featured_foods_result = mysqli_query($conn, $featured_foods_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EWU Cafeteria | Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #059669;
            --primary-hover: #047857;
            --primary-light: #ecfdf5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --gray-text: #64748b;
            --border: #e2e8f0;
            --bg-main: #f8fafc;
            --white: #ffffff;
            --shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.04);
            --shadow-hover: 0 20px 40px -15px rgba(5, 150, 105, 0.15);
        }

        [data-theme="dark"] {
            --primary: #10b981;
            --primary-hover: #059669;
            --primary-light: rgba(16, 185, 129, 0.15);
            --dark: #f8fafc;
            --gray-text: #94a3b8;
            --border: #334155;
            --bg-main: #0f172a;
            --white: #1e293b;
            --shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.3);
            --shadow-hover: 0 20px 40px -15px rgba(16, 185, 129, 0.25);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease; }
        body { display: flex; background: var(--bg-main); color: var(--dark); min-height: 100vh; overflow-x: hidden; }

        .sidebar { width: 260px; background: var(--white); border-right: 1px solid var(--border); padding: 24px 18px; display: flex; flex-direction: column; justify-content: space-between; position: fixed; top: 0; left: 0; height: 100vh; z-index: 10; }
        .brand { font-size: 17px; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 10px; margin-bottom: 28px; letter-spacing: -0.5px; }
        .brand-icon { background: var(--primary); width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 15px; box-shadow: 0 6px 15px rgba(5, 150, 105, 0.3); }

        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; justify-content: space-between; padding: 11px 14px; text-decoration: none; color: var(--gray-text); font-size: 13.5px; font-weight: 600; border-radius: 10px; }
        .nav-item a span { display: flex; align-items: center; gap: 12px; }
        .nav-item.active a { background: var(--primary-light); color: var(--primary); font-weight: 700; }
        .nav-item.active a i { color: var(--primary); }
        .nav-item a:hover { background: var(--primary-light); color: var(--primary); transform: translateX(2px); }
        .cart-badge { background: var(--primary); color: #ffffff; font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 700; }

        /* FIXED: Removed z-index from main-content so spotlighted elements can properly break out of stacking context */
        .main-content { flex: 1; margin-left: 260px; padding: 24px 32px; width: calc(100% - 260px); max-width: 1400px; position: relative; }

        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; background: var(--white); padding: 12px 20px; border-radius: 14px; border: 1px solid var(--border); box-shadow: var(--shadow); }
        .header-search-trigger { background: var(--bg-main); border: 1px solid var(--border); border-radius: 9px; padding: 8px 14px; font-size: 13px; color: var(--gray-text); display: flex; align-items: center; gap: 10px; cursor: pointer; width: 240px; justify-content: space-between; }
        .header-search-trigger:hover { border-color: var(--primary); }
        .header-search-trigger kbd { background: var(--white); border: 1px solid var(--border); padding: 2px 6px; border-radius: 5px; font-size: 10px; font-weight: 700; }

        .header-actions { display: flex; align-items: center; gap: 10px; }
        .action-icon-btn { background: var(--bg-main); border: 1px solid var(--border); width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center; color: var(--dark); cursor: pointer; }
        .action-icon-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-1px); }

        .ai-tour-btn { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: #fff; border: none; padding: 8px 14px; border-radius: 9px; font-size: 12.5px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25); }
        .ai-tour-btn:hover { opacity: 0.9; transform: translateY(-1px); }

        .live-clock { font-size: 12px; font-weight: 700; color: var(--dark); background: var(--bg-main); padding: 7px 14px; border-radius: 10px; border: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
        .pulse-dot { width: 7px; height: 7px; background: var(--success); border-radius: 50%; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); animation: pulse 1.6s infinite; }
        @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }

        .grid-container { display: grid; grid-template-columns: repeat(12, 1fr); gap: 20px; margin-bottom: 20px; }
        .card { background: var(--white); border: 1px solid var(--border); border-radius: 16px; padding: 22px; box-shadow: var(--shadow); transition: transform 0.25s ease, box-shadow 0.25s ease; }
        .card:hover { border-color: rgba(5, 150, 105, 0.3); box-shadow: var(--shadow-hover); transform: translateY(-2px); }

        .col-span-8 { grid-column: span 8; }
        .col-span-4 { grid-column: span 4; }
        .col-span-12 { grid-column: span 12; }

        @media(max-width: 1100px) { 
            .col-span-8, .col-span-4 { grid-column: span 12; }
        }

        .welcome-banner { background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #ffffff; border: none; }
        .welcome-banner h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.5px; }
        .welcome-banner p { font-size: 13.5px; color: #ecfdf5; margin-bottom: 18px; font-weight: 500; }
        .btn-primary { background: #ffffff; color: var(--primary); padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 800; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .btn-primary:hover { background: #f8fafc; transform: translateY(-1px); }

        .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .stat-header span { font-size: 11.5px; font-weight: 700; color: var(--gray-text); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-icon { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .stat-value h3 { font-size: 24px; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }

        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .card-header h3 { font-size: 15.5px; font-weight: 800; color: var(--dark); }
        .view-all { font-size: 12px; font-weight: 700; color: var(--primary); text-decoration: none; }

        .custom-table { width: 100%; border-collapse: collapse; }
        .custom-table th { text-align: left; padding: 10px; font-size: 11.5px; font-weight: 700; color: var(--gray-text); border-bottom: 1px solid var(--border); text-transform: uppercase; }
        .custom-table td { padding: 12px 10px; font-size: 13px; font-weight: 600; color: var(--dark); border-bottom: 1px solid var(--border); }
        .status-badge { background: var(--primary-light); color: var(--primary); padding: 4px 10px; border-radius: 8px; font-size: 11.5px; font-weight: 700; display: inline-block; }

        .quick-links-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .quick-link-item { background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; padding: 12px; text-decoration: none; display: flex; align-items: center; gap: 10px; color: var(--dark); font-weight: 700; font-size: 13px; }
        .quick-link-item:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); transform: translateY(-1px); }
        .quick-link-item i { color: var(--primary); font-size: 14px; }

        .popular-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 10px; }
        .food-card { background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; padding: 10px; text-decoration: none; display: flex; gap: 10px; align-items: center; }
        .food-card:hover { border-color: var(--primary); }
        .food-img { width: 46px; height: 46px; border-radius: 8px; object-fit: cover; background: var(--border); flex-shrink: 0; }
        .food-info h4 { font-size: 13px; font-weight: 800; color: var(--dark); margin-bottom: 2px; }
        .food-info p { font-size: 12px; font-weight: 700; color: var(--primary); }

        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(5px); z-index: 999; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
        .modal-overlay.show { opacity: 1; pointer-events: auto; }
        .modal-box { background: var(--white); border: 1px solid var(--border); width: 420px; border-radius: 20px; padding: 30px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); transform: translateY(15px); transition: transform 0.3s ease; }
        .modal-overlay.show .modal-box { transform: translateY(0); }
        .modal-icon { width: 56px; height: 56px; background: var(--primary-light); color: var(--primary); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 16px; }
        .modal-box h2 { font-size: 20px; font-weight: 800; color: var(--dark); margin-bottom: 8px; }
        .modal-box p { font-size: 13.5px; color: var(--gray-text); line-height: 1.5; margin-bottom: 20px; }
        .modal-actions { display: flex; gap: 10px; }
        .m-btn { flex: 1; padding: 11px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; border: 1px solid var(--border); }
        .m-btn-secondary { background: var(--bg-main); color: var(--dark); }
        .m-btn-secondary:hover { background: var(--border); }
        .m-btn-primary { background: var(--primary); color: #fff; border-color: transparent; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
        .m-btn-primary:hover { opacity: 0.9; }

        .tour-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(4px); z-index: 2999; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
        .tour-backdrop.show { opacity: 1; pointer-events: auto; }

        /* FIXED: Perfect Spotlight Effect sitting fully above backdrop */
        .spotlight-active { 
            position: relative !important; 
            z-index: 3000 !important; 
            background: var(--white) !important;
            box-shadow: 0 0 0 4px var(--primary), 0 0 40px 10px rgba(5, 150, 105, 0.4), 0 20px 40px rgba(0, 0, 0, 0.3) !important; 
            transform: scale(1.01) !important; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; 
            border-radius: 16px;
        }

        /* Modern Tooltip with Clean Arrow */
        .tour-tooltip { 
            position: fixed; 
            z-index: 3500; 
            background: #ffffff; 
            border: 1px solid rgba(5, 150, 105, 0.25); 
            width: 400px; 
            max-width: 90vw;
            border-radius: 18px; 
            padding: 0; 
            box-shadow: 0 20px 50px -10px rgba(15, 23, 42, 0.35); 
            display: none; 
            opacity: 0; 
            transform: translateY(10px); 
            transition: opacity 0.25s ease, transform 0.25s ease; 
        }
        .tour-tooltip.show { display: block; opacity: 1; transform: translateY(0); }

        .tour-tooltip::before {
            content: '';
            position: absolute;
            width: 0; 
            height: 0; 
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 8px solid rgba(5, 150, 105, 0.25);
            top: -9px;
            left: 32px;
        }
        .tour-tooltip::after {
            content: '';
            position: absolute;
            width: 0; 
            height: 0; 
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-bottom: 7px solid #ffffff;
            top: -7px;
            left: 33px;
        }

        .tour-progress-bar { height: 4px; background: #e2e8f0; width: 100%; position: relative; overflow: hidden; margin-bottom: 15px; border-radius: 4px; }
        .tour-progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--success)); width: 0%; transition: width 0.4s ease; }

        .tour-tooltip-content { padding: 22px; }
        .tour-tooltip-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .tour-header-badges { display: flex; align-items: center; gap: 8px; }
        .tour-ai-badge { background: var(--primary-light); color: var(--primary); font-size: 11px; font-weight: 800; padding: 3px 9px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px; }
        
        .tour-wave { display: inline-flex; align-items: center; gap: 2px; height: 12px; }
        .tour-wave span { display: block; width: 2px; height: 100%; background: var(--primary); animation: waveAnim 1s infinite ease-in-out; }
        .tour-wave span:nth-child(2) { animation-delay: 0.2s; }
        .tour-wave span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes waveAnim { 0%, 100% { height: 4px; } 50% { height: 12px; } }

        .tour-actions-top { display: flex; gap: 6px; align-items: center; }
        .tour-icon-action { background: var(--bg-main); border: 1px solid var(--border); color: var(--gray-text); font-size: 12px; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .tour-icon-action:hover { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }
        .tour-icon-action.active { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }

        .tour-tooltip-body { 
            font-size: 13.5px; 
            color: #64748b; 
            line-height: 1.6; 
            margin-bottom: 20px; 
            min-height: 48px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .tour-tooltip-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 14px; }
        .tour-step-dots { display: flex; gap: 5px; align-items: center; }
        .tour-dot { width: 6px; height: 6px; background: #cbd5e1; border-radius: 50%; transition: all 0.3s; cursor: pointer; }
        .tour-dot.active { width: 18px; border-radius: 4px; background: var(--primary); }

        .tour-btns-group { display: flex; gap: 8px; }
        .t-btn { padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700; cursor: pointer; border: 1px solid var(--border); }
        .t-btn-prev { background: var(--bg-main); color: var(--dark); }
        .t-btn-prev:hover { background: var(--border); }
        .t-btn-next { background: var(--primary); color: #fff; border-color: transparent; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
        .t-btn-next:hover { opacity: 0.9; }

        .tour-thinking { display: none; font-size: 12px; color: var(--primary); font-weight: 700; align-items: center; gap: 6px; margin-bottom: 8px; }
        .tour-thinking.active { display: flex; }
        .spinner-mini { width: 12px; height: 12px; border: 2px solid var(--primary-light); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .cmd-modal { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); z-index: 1000; display: none; align-items: flex-start; justify-content: center; padding-top: 12vh; }
        .cmd-modal.show { display: flex; }
        .cmd-box { background: var(--white); border: 1px solid var(--border); width: 500px; border-radius: 12px; box-shadow: 0 15px 35px rgba(0,0,0,0.15); overflow: hidden; }
        .cmd-input { width: 100%; padding: 14px 18px; border: none; border-bottom: 1px solid var(--border); background: transparent; font-size: 13.5px; color: var(--dark); outline: none; }
        .cmd-list { padding: 8px; max-height: 250px; overflow-y: auto; }
        .cmd-item { padding: 10px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--dark); text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .cmd-item:hover { background: var(--primary-light); color: var(--primary); }
    </style>
</head>
<body data-theme="light">

    <div class="modal-overlay show" id="welcomeModal">
        <div class="modal-box">
            <div class="modal-icon"><i class="fa-solid fa-utensils"></i></div>
            <h2>Welcome to EWU Cafeteria</h2>
            <p>Manage your food orders, check daily menus, and explore advanced AI guide tour features.</p>
            <div class="modal-actions">
                <button class="m-btn m-btn-secondary" id="skipWelcomeBtn">Skip</button>
                <button class="m-btn m-btn-primary" id="startWelcomeBtn"><i class="fa-solid fa-wand-magic-sparkles"></i> Start AI Tour</button>
            </div>
        </div>
    </div>

    <div class="sidebar" id="tourSidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                <span>EWU Cafeteria</span>
            </div>
            <ul class="nav-menu">
                <li class="nav-item active"><a href="dashboard.php"><span><i class="fa-solid fa-house"></i> <span>Dashboard</span></span></a></li>
                <li class="nav-item"><a href="menu.php"><span><i class="fa-solid fa-book-open"></i> <span>Food Menu</span></span></a></li>
                <li class="nav-item">
                    <a href="cart.php">
                        <span><i class="fa-solid fa-cart-shopping"></i> <span>My Cart</span></span>
                        <span class="cart-badge"><?= $total_cart_items; ?></span>
                    </a>
                </li>
                <li class="nav-item"><a href="checkout.php"><span><i class="fa-solid fa-credit-card"></i> <span>Checkout</span></span></a></li>
                <li class="nav-item"><a href="orders.php"><span><i class="fa-solid fa-clock-rotate-left"></i> <span>My Orders</span></span></a></li>
                <li class="nav-item"><a href="feedback.php"><span><i class="fa-solid fa-comment"></i> <span>Feedback</span></span></a></li>
            </ul>
        </div>
        <div class="nav-menu">
            <li class="nav-item"><a href="../logout.php" style="color: var(--danger);"><span style="color: var(--danger);"><i class="fa-solid fa-right-from-bracket"></i> <span>Sign Out</span></span></a></li>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header" id="tourHeader">
            <div class="header-search-trigger" id="openCmdPalette">
                <span><i class="fa-solid fa-magnifying-glass"></i> Search menu...</span>
                <kbd>Ctrl+K</kbd>
            </div>
            <div class="header-actions">
                <button class="ai-tour-btn" id="startAiTour"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Tour</button>
                <div class="live-clock">
                    <span class="pulse-dot"></span>
                    <span id="clockTime">00:00:00 AM</span>
                </div>
                <button class="action-icon-btn" id="themeToggle" title="Toggle Theme">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>

        <div class="grid-container">
            <div class="card col-span-8 welcome-banner" id="tourHero">
                <div>
                    <h1>East West University Cafeteria Portal</h1>
                    <p>Order fresh meals, avoid queues, and pick up your food on time.</p>
                </div>
                <div>
                    <a href="menu.php" class="btn-primary">
                        <i class="fa-solid fa-utensils"></i> Browse Food Menu
                    </a>
                </div>
            </div>

            <div class="card col-span-4" id="tourShortcuts">
                <div class="card-header" style="margin-bottom: 10px;">
                    <h3>Quick Shortcuts</h3>
                </div>
                <div class="quick-links-grid">
                    <a href="menu.php" class="quick-link-item"><i class="fa-solid fa-book-open"></i> Menu</a>
                    <a href="cart.php" class="quick-link-item"><i class="fa-solid fa-cart-shopping"></i> Cart</a>
                    <a href="orders.php" class="quick-link-item"><i class="fa-solid fa-clock-rotate-left"></i> Orders</a>
                    <a href="feedback.php" class="quick-link-item"><i class="fa-solid fa-comment"></i> Feedback</a>
                </div>
            </div>

            <div class="card col-span-4" id="tourMetrics">
                <div class="stat-header">
                    <span>Menu Items</span>
                    <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);"><i class="fa-solid fa-utensils"></i></div>
                </div>
                <div class="stat-value"><h3><?= $total_menu_items; ?></h3></div>
            </div>

            <div class="card col-span-4">
                <div class="stat-header">
                    <span>Cart Items</span>
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);"><i class="fa-solid fa-cart-shopping"></i></div>
                </div>
                <div class="stat-value"><h3><?= $total_cart_items; ?></h3></div>
            </div>

            <div class="card col-span-4">
                <div class="stat-header">
                    <span>Total Orders</span>
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);"><i class="fa-solid fa-bag-shopping"></i></div>
                </div>
                <div class="stat-value"><h3><?= $total_orders; ?></h3></div>
            </div>

            <div class="card col-span-12" id="tourOrders">
                <div class="card-header">
                    <h3>Recent Orders</h3>
                    <a href="orders.php" class="view-all">View All</a>
                </div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Items</th>
                            <th>Total Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_orders_result && mysqli_num_rows($recent_orders_result) > 0): ?>
                            <?php while ($order = mysqli_fetch_assoc($recent_orders_result)): ?>
                                <tr>
                                    <td>#<?= $order['order_id']; ?></td>
                                    <td><?= $order['total_items'] ?? '1'; ?></td>
                                    <td>৳<?= number_format($order['total_price'] ?? 0, 2); ?></td>
                                    <td><span class="status-badge"><?= ucfirst($order['status'] ?? 'Pending'); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align: center; padding: 20px; color: var(--gray-text);">No recent orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card col-span-12" id="tourPopular">
                <div class="card-header" style="margin-bottom: 10px;">
                    <h3>Popular Items</h3>
                    <a href="menu.php" class="view-all">View Menu</a>
                </div>
                <div class="popular-grid">
                    <?php if ($featured_foods_result && mysqli_num_rows($featured_foods_result) > 0): ?>
                        <?php while ($food = mysqli_fetch_assoc($featured_foods_result)): ?>
                            <?php 
                                $img_name = trim($food['image']);
                                $img = !empty($img_name) ? ((strpos($img_name, 'http') === 0) ? $img_name : "../uploads/" . $img_name) : "https://placehold.co/150x150?text=Food";
                            ?>
                            <a href="menu.php" class="food-card">
                                <img src="<?= htmlspecialchars($img); ?>" alt="Food" class="food-img">
                                <div class="food-info">
                                    <h4><?= htmlspecialchars($food['food_name']); ?></h4>
                                    <p>৳<?= number_format(floatval($food['price'] ?? 0), 2); ?></p>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--gray-text); font-size: 13px; grid-column: span 4; text-align: center; padding: 10px;">No menu items available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="tour-backdrop" id="tourBackdrop"></div>
    <div class="tour-tooltip" id="tourTooltip">
        <div class="tour-tooltip-content">
            <div class="tour-tooltip-header">
                <div class="tour-header-badges">
                    <div class="tour-ai-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Guide</div>
                    <div class="tour-wave"><span></span><span></span><span></span></div>
                </div>
                <div class="tour-actions-top">
                    <button class="tour-icon-action active" id="tourVoiceToggle" title="Toggle Voice Output"><i class="fa-solid fa-volume-high" id="voiceIcon"></i></button>
                    <button class="tour-icon-action" id="tourCloseBtn" title="Close Tour"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            
            <div class="tour-progress-bar">
                <div class="tour-progress-fill" id="tourProgressFill"></div>
            </div>

            <div class="tour-thinking" id="tourThinking">
                <div class="spinner-mini"></div> AI analyzing element...
            </div>
            <h4 id="tooltipTitle" style="margin-bottom: 8px; font-size: 16.5px; font-weight: 800; color: #1e293b;">Title</h4>
            <div class="tour-tooltip-body" id="tooltipDesc">Description</div>
            <div class="tour-tooltip-footer">
                <div class="tour-step-dots" id="tourStepDots"></div>
                <div class="tour-btns-group">
                    <button class="t-btn t-btn-prev" id="tourPrevBtn" style="display: none;">Back</button>
                    <button class="t-btn t-btn-next" id="tourNextBtn">Next</button>
                </div>
            </div>
        </div>
    </div>

    <div class="cmd-modal" id="cmdModal">
        <div class="cmd-box">
            <input type="text" class="cmd-input" placeholder="Type to search..." id="cmdInput">
            <div class="cmd-list">
                <a href="dashboard.php" class="cmd-item"><i class="fa-solid fa-house"></i> Dashboard</a>
                <a href="menu.php" class="cmd-item"><i class="fa-solid fa-book-open"></i> Food Menu</a>
                <a href="cart.php" class="cmd-item"><i class="fa-solid fa-cart-shopping"></i> My Cart</a>
                <a href="orders.php" class="cmd-item"><i class="fa-solid fa-clock-rotate-left"></i> My Orders</a>
                <a href="feedback.php" class="cmd-item"><i class="fa-solid fa-comment"></i> Feedback</a>
            </div>
        </div>
    </div>

    <script>
        const welcomeModal = document.getElementById('welcomeModal');
        document.getElementById('skipWelcomeBtn').addEventListener('click', () => welcomeModal.classList.remove('show'));
        document.getElementById('startWelcomeBtn').addEventListener('click', () => {
            welcomeModal.classList.remove('show');
            setTimeout(() => { startAiTourFn(); }, 300);
        });

        function updateClock() {
            document.getElementById('clockTime').innerText = new Date().toLocaleTimeString();
        }
        setInterval(updateClock, 1000);
        updateClock();

        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            const body = document.body;
            if (body.getAttribute('data-theme') === 'light') {
                body.setAttribute('data-theme', 'dark');
                themeToggle.innerHTML = '<i class="fa-solid fa-sun"></i>';
            } else {
                body.setAttribute('data-theme', 'light');
                themeToggle.innerHTML = '<i class="fa-solid fa-moon"></i>';
            }
        });

        const tourSteps = [
            { elementId: 'tourSidebar', title: "Navigation Sidebar", desc: "Easily switch between Dashboard, Food Menu, Cart, and Orders here with smooth real-time navigation." },
            { elementId: 'tourHeader', title: "Header & Quick Controls", desc: "Access global search, live active system clock, dark/light theme toggle, and restart this AI tour anytime." },
            { elementId: 'tourHero', title: "Welcome Portal Banner", desc: "Quickly access the food menu and manage your university cafeteria food portal features directly." },
            { elementId: 'tourShortcuts', title: "Interactive Shortcuts", desc: "Jump directly to key cafeteria sections with these quick action tiles." },
            { elementId: 'tourMetrics', title: "Live Analytics Metrics", desc: "Track menu items count, active cart contents, and order status statistics in real-time." },
            { elementId: 'tourOrders', title: "Recent Orders Tracker", desc: "Check the live status of your recent cafeteria food orders instantly right here." }
        ];

        let currentTourIndex = 0;
        let typingInterval = null;
        let isVoiceEnabled = true;

        const tourBackdrop = document.getElementById('tourBackdrop');
        const tourTooltip = document.getElementById('tourTooltip');
        const startAiTourBtn = document.getElementById('startAiTour');
        const tooltipTitle = document.getElementById('tooltipTitle');
        const tooltipDesc = document.getElementById('tooltipDesc');
        const tourProgressFill = document.getElementById('tourProgressFill');
        const tourStepDots = document.getElementById('tourStepDots');
        const tourPrevBtn = document.getElementById('tourPrevBtn');
        const tourNextBtn = document.getElementById('tourNextBtn');
        const tourCloseBtn = document.getElementById('tourCloseBtn');
        const tourThinking = document.getElementById('tourThinking');
        const tourVoiceToggle = document.getElementById('tourVoiceToggle');
        const voiceIcon = document.getElementById('voiceIcon');

        function speakText(text) {
            if (!isVoiceEnabled || !('speechSynthesis' in window)) return;
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = 1.0;
            utterance.pitch = 1.0;
            window.speechSynthesis.speak(utterance);
        }

        tourVoiceToggle.onclick = function() {
            isVoiceEnabled = !isVoiceEnabled;
            if (isVoiceEnabled) {
                tourVoiceToggle.classList.add('active');
                voiceIcon.className = "fa-solid fa-volume-high";
                speakText(tourSteps[currentTourIndex].desc);
            } else {
                tourVoiceToggle.classList.remove('active');
                voiceIcon.className = "fa-solid fa-volume-xmark";
                if ('speechSynthesis' in window) window.speechSynthesis.cancel();
            }
        };

        function renderStepDots() {
            tourStepDots.innerHTML = '';
            tourSteps.forEach((_, i) => {
                const dot = document.createElement('div');
                dot.className = `tour-dot ${i === currentTourIndex ? 'active' : ''}`;
                dot.onclick = () => {
                    currentTourIndex = i;
                    showTourStep(currentTourIndex);
                };
                tourStepDots.appendChild(dot);
            });
        }

        function typeWriterEffect(text) {
            if (typingInterval) clearInterval(typingInterval);
            tooltipDesc.textContent = '';
            let i = 0;
            typingInterval = setInterval(() => {
                if (i < text.length) {
                    tooltipDesc.textContent += text.charAt(i);
                    i++;
                } else {
                    clearInterval(typingInterval);
                }
            }, 12);
        }

        function showTourStep(index) {
            document.querySelectorAll('.spotlight-active').forEach(el => el.classList.remove('spotlight-active'));
            
            tourThinking.classList.add('active');
            tooltipTitle.innerText = "AI Processing...";
            tooltipDesc.textContent = "";

            setTimeout(() => {
                tourThinking.classList.remove('active');
                const step = tourSteps[index];
                const targetEl = document.getElementById(step.elementId);
                if (!targetEl) return;

                targetEl.classList.add('spotlight-active');
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

                setTimeout(() => {
                    const rect = targetEl.getBoundingClientRect();
                    const tooltipWidth = 400;
                    const tooltipHeight = 240;

                    let topPos = rect.bottom + 16;
                    let leftPos = rect.left + (rect.width / 2) - 40;

                    if (topPos + tooltipHeight > window.innerHeight - 20) {
                        topPos = rect.top - tooltipHeight - 16;
                    }

                    if (leftPos < 20) leftPos = 20;
                    if (leftPos + tooltipWidth > window.innerWidth - 20) {
                        leftPos = window.innerWidth - tooltipWidth - 20;
                    }

                    tourTooltip.style.top = `${topPos}px`;
                    tourTooltip.style.left = `${leftPos}px`;
                }, 150);

                tooltipTitle.innerHTML = `<i class="fa-solid fa-wand-magic-sparkles" style="color:var(--primary);"></i> ${step.title}`;
                typeWriterEffect(step.desc);
                speakText(step.desc);
                
                const progressPercent = ((index + 1) / tourSteps.length) * 100;
                tourProgressFill.style.width = `${progressPercent}%`;

                renderStepDots();

                tourPrevBtn.style.display = index === 0 ? 'none' : 'block';
                tourNextBtn.innerText = index === tourSteps.length - 1 ? 'Finish Tour' : 'Next';
            }, 350);
        }

        function startAiTourFn() {
            currentTourIndex = 0;
            tourBackdrop.classList.add('show');
            tourTooltip.classList.add('show');
            showTourStep(currentTourIndex);
        }

        startAiTourBtn.addEventListener('click', startAiTourFn);

        tourNextBtn.onclick = function() {
            if (currentTourIndex < tourSteps.length - 1) {
                currentTourIndex++;
                showTourStep(currentTourIndex);
            } else {
                endTour();
            }
        };

        tourPrevBtn.onclick = function() {
            if (currentTourIndex > 0) {
                currentTourIndex--;
                showTourStep(currentTourIndex);
            }
        };

        tourCloseBtn.onclick = endTour;
        tourBackdrop.onclick = endTour;

        function endTour() {
            if (typingInterval) clearInterval(typingInterval);
            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
            tourBackdrop.classList.remove('show');
            tourTooltip.classList.remove('show');
            tourThinking.classList.remove('active');
            document.querySelectorAll('.spotlight-active').forEach(el => el.classList.remove('spotlight-active'));
            currentTourIndex = 0;
        }

        window.addEventListener('keydown', (e) => {
            if (tourBackdrop.classList.contains('show')) {
                if (e.key === 'ArrowRight' || e.key === 'Enter') {
                    tourNextBtn.click();
                } else if (e.key === 'ArrowLeft') {
                    tourPrevBtn.click();
                } else if (e.key === 'Escape') {
                    endTour();
                }
            }
        });

        const cmdModal = document.getElementById('cmdModal');
        const openCmdPalette = document.getElementById('openCmdPalette');
        const cmdInput = document.getElementById('cmdInput');

        openCmdPalette.addEventListener('click', () => cmdModal.classList.add('show'));
        window.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                cmdModal.classList.toggle('show');
                cmdInput.focus();
            }
            if (e.key === 'Escape' && cmdModal.classList.contains('show')) {
                cmdModal.classList.remove('show');
            }
        });
        cmdModal.addEventListener('click', (e) => {
            if (e.target === cmdModal) cmdModal.classList.remove('show');
        });
    </script>
</body>
</html>