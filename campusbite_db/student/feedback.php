<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    header('Content-Type: application/json');
    $del_id = intval($_POST['delete_id']);
    $del_sql = "DELETE FROM feedback WHERE id = '$del_id' AND user_id = '$user_id'";
    if (mysqli_query($conn, $del_sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Feedback deleted successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete feedback.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit'])) {
    header('Content-Type: application/json');
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject'] ?? ''));
    $message = mysqli_real_escape_string($conn, trim($_POST['message'] ?? ''));
    $rating = intval($_POST['rating'] ?? 5);

    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Please write your feedback message!']);
        exit;
    }

    $insert_sql = "INSERT INTO feedback (user_id, subject, message, rating) VALUES ('$user_id', '$subject', '$message', '$rating')";
    
    if (mysqli_query($conn, $insert_sql)) {
        $new_id = mysqli_insert_id($conn);
        $date_formatted = date('M d, Y - h:i A');
        $stars_html = str_repeat('<i class="fa-solid fa-star"></i>', $rating) . str_repeat('<i class="fa-regular fa-star" style="color:#cbd5e1;"></i>', 5 - $rating);
        $sub_text = htmlspecialchars($subject ?: 'General Feedback');
        $msg_text = htmlspecialchars($message);

        $sentiment_class = $rating >= 4 ? 'sentiment-positive' : ($rating == 3 ? 'sentiment-neutral' : 'sentiment-negative');
        $sentiment_text = $rating >= 4 ? 'Positive' : ($rating == 3 ? 'Average' : 'Attention');

        $html_item = "
        <div class='feedback-item' id='feedback-item-{$new_id}' data-id='{$new_id}' data-rating='{$rating}' data-text='" . strtolower($sub_text . ' ' . $msg_text) . "'>
            <div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;'>
                <div class='stars'>{$stars_html}</div>
                <div style='display: flex; align-items: center; gap: 8px;'>
                    <span class='sentiment-badge {$sentiment_class}'>{$sentiment_text}</span>
                    <button onclick='deleteFeedback({$new_id})' title='Delete Feedback' style='background:none; border:none; color:var(--text-sub); cursor:pointer; font-size:13px; transition:all 0.3s cubic-bezier(0.4,0,0.2,1);' onmouseover='this.style.color=\"#ef4444\"; this.style.transform=\"scale(1.2)\"' onmouseout='this.style.color=\"var(--text-sub)\"; this.style.transform=\"scale(1)\"'>
                        <i class='fa-solid fa-trash-can'></i>
                    </button>
                </div>
            </div>
            <div style='font-weight: 700; font-size: 14.5px; color: var(--text-main);'>{$sub_text}</div>
            <div style='font-size: 13.5px; color: var(--text-sub); margin-top: 6px; line-height: 1.6;'>{$msg_text}</div>
            <div style='font-size: 11.5px; color: var(--text-sub); margin-top: 10px; display: flex; justify-content: space-between; align-items: center;'>
                <span style='display: flex; align-items: center; gap: 6px;'><i class='fa-regular fa-clock'></i> {$date_formatted}</span>
                <span style='font-size: 11px; font-weight:700; color:var(--primary); background:var(--primary-light); padding:2px 8px; border-radius:6px; animation: pulseBadge 2s infinite;'>Just Now</span>
            </div>
        </div>";

        echo json_encode(['status' => 'success', 'html' => $html_item, 'message' => 'Feedback submitted successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . mysqli_error($conn)]);
    }
    exit;
}

$feedback_query = "SELECT * FROM feedback WHERE user_id = '$user_id' ORDER BY 1 DESC";
$feedback_result = mysqli_query($conn,$feedback_query);

$total_feedbacks = 0;
$total_rating_sum = 0;
$rating_counts = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];

$all_feedbacks_array = [];
if ($feedback_result) {
    while ($row = mysqli_fetch_array($feedback_result)) {
        $all_feedbacks_array[] =$row;
        $total_feedbacks++;
        $r_val = (int)$row['rating'];
        $total_rating_sum += $r_val;
        if(isset($rating_counts[$r_val])) {
            $rating_counts[$r_val]++;
        }
    }
}
$avg_rating = $total_feedbacks > 0 ? number_format($total_rating_sum / $total_feedbacks, 1) : '0.0';
$satisfaction_rate = $total_feedbacks > 0 ? round((($rating_counts[5] + $rating_counts[4]) /$total_feedbacks) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Feedback Hub Pro | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #10b981;
            --primary-hover: #059669;
            --primary-light: #ecfdf5;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-sub: #64748b;
            --border: #e2e8f0;
            --shadow: 0 20px 40px -15px rgba(16, 185, 129, 0.1), 0 10px 20px -10px rgba(0, 0, 0, 0.03);
            --radius: 24px;
        }

        [data-theme="dark"] {
            --bg-main: #030712;
            --card-bg: #0f172a;
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
            --border: #1e293b;
            --primary-light: rgba(16, 185, 129, 0.15);
            --shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; transition: background-color 0.4s cubic-bezier(0.16, 1, 0.3, 1), color 0.4s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        body { display: flex; background-color: var(--bg-main); color: var(--text-main); min-height: 100vh; overflow-x: hidden; }

        /* Sidebar Glassmorphism */
        .sidebar { width: 260px; background: var(--card-bg); padding: 24px 20px; border-right: 1px solid var(--border); display: flex; flex-direction: column; justify-content: space-between; position: fixed; height: 100vh; z-index: 100; backdrop-filter: blur(16px); animation: slideInLeft 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes slideInLeft { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .brand { font-size: 20px; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 10px; margin-bottom: 24px; }
        .brand-icon { background: linear-gradient(135deg, var(--primary), #34d399); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35); animation: pulseGlow 3s infinite ease-in-out; }
        @keyframes pulseGlow { 0%, 100% { transform: scale(1); box-shadow: 0 0 15px rgba(16,185,129,0.4); } 50% { transform: scale(1.05); box-shadow: 0 0 25px rgba(16,185,129,0.7); } }

        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--text-sub); font-size: 14px; font-weight: 600; border-radius: 14px; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .nav-item a:hover { transform: translateX(8px); background: var(--primary-light); color: var(--primary); }
        .nav-item.active a { background: var(--primary-light); color: var(--primary); font-weight: 700; transform: translateX(4px); box-shadow: 0 4px 15px rgba(16,185,129,0.15); }

        /* Main Content Fluid Animation */
        .main-content { flex: 1; margin-left: 260px; padding: 40px 50px; overflow-y: auto; max-width: 1400px; animation: fadeInScale 0.7s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.98) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
        .page-title { font-size: 28px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 14px; letter-spacing: -0.5px; }
        .page-title i { color: var(--primary); background: var(--primary-light); padding: 12px; border-radius: 16px; font-size: 20px; box-shadow: 0 8px 20px rgba(16,185,129,0.2); animation: floatIcon 3s ease-in-out infinite; }
        @keyframes floatIcon { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }

        .header-actions { display: flex; gap: 12px; align-items: center; }
        .action-btn { background: var(--card-bg); border: 1.5px solid var(--border); padding: 11px 18px; border-radius: 14px; display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-main); font-size: 13.5px; font-weight: 700; box-shadow: var(--shadow); transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .action-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-4px) scale(1.02); box-shadow: 0 15px 30px rgba(16,185,129,0.25); }

        /* Analytics Cards */
        .analytics-strip { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
        .analytic-card { background: var(--card-bg); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow); transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); position: relative; overflow: hidden; }
        .analytic-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--primary); opacity: 0; transition: opacity 0.3s ease; }
        .analytic-card:hover { transform: translateY(-6px); border-color: var(--primary); box-shadow: 0 25px 50px -12px rgba(16,185,129,0.25); }
        .analytic-card:hover::before { opacity: 1; }
        
        .analytic-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .analytic-card:hover .analytic-icon { transform: scale(1.15) rotate(10deg); }
        .analytic-icon.blue { background: #e0f2fe; color: #0284c7; }
        .analytic-icon.amber { background: #fef3c7; color: #d97706; }
        .analytic-icon.green { background: var(--primary-light); color: var(--primary); }
        .analytic-icon.purple { background: #f3e8ff; color: #9333ea; }

        /* Breakdown Card */
        .breakdown-card { background: var(--card-bg); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 28px; box-shadow: var(--shadow); display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: center; transition: all 0.4s ease; }
        .breakdown-card:hover { border-color: var(--primary); box-shadow: 0 25px 50px -15px rgba(16,185,129,0.15); }
        .bar-row { display: flex; align-items: center; gap: 12px; font-size: 12.5px; font-weight: 700; color: var(--text-sub); margin-bottom: 8px; }
        .bar-track { flex: 1; height: 10px; background: var(--bg-main); border-radius: 6px; overflow: hidden; border: 1.5px solid var(--border); }
        .bar-fill { height: 100%; background: linear-gradient(90deg, var(--primary), #34d399); border-radius: 6px; transition: width 1s cubic-bezier(0.16, 1, 0.3, 1); }

        /* Toast Notifications */
        .toast-container { position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; flex-direction: column; gap: 12px; }
        .toast { padding: 14px 24px; border-radius: 16px; font-size: 13.5px; font-weight: 700; box-shadow: 0 20px 40px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 12px; animation: slideInToast 0.45s cubic-bezier(0.16, 1, 0.3, 1), fadeOutToast 0.45s cubic-bezier(0.16, 1, 0.3, 1) 2.6s forwards; color: #fff; backdrop-filter: blur(10px); }
        .toast.success { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4); }
        .toast.error { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 15px 30px rgba(239, 68, 68, 0.4); }
        @keyframes slideInToast { from { transform: translateX(120%) scale(0.9); opacity: 0; } to { transform: translateX(0) scale(1); opacity: 1; } }
        @keyframes fadeOutToast { to { opacity: 0; transform: translateY(20px) scale(0.95); } }

        .feedback-grid { display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 28px; align-items: start; }
        .card { background: var(--card-bg); border-radius: var(--radius); border: 1.5px solid var(--border); padding: 32px; box-shadow: var(--shadow); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .card:hover { transform: translateY(-3px); box-shadow: 0 30px 60px -20px rgba(16,185,129,0.18); border-color: rgba(16,185,129,0.3); }
        
        .form-group { margin-bottom: 22px; position: relative; }
        .form-label { display: block; font-size: 12px; font-weight: 800; color: var(--text-sub); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.8px; }
        .form-control { width: 100%; padding: 14px 18px; background: var(--bg-main); border: 1.5px solid var(--border); border-radius: 14px; font-size: 14px; outline: none; color: var(--text-main); transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .form-control:focus { border-color: var(--primary); background: var(--card-bg); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.15); transform: translateY(-2px); }
        textarea.form-control { resize: vertical; min-height: 130px; }

        .preset-tags { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .preset-tag { background: var(--bg-main); border: 1.5px solid var(--border); padding: 6px 12px; border-radius: 10px; font-size: 11.5px; font-weight: 700; color: var(--text-sub); cursor: pointer; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .preset-tag:hover { background: var(--primary-light); color: var(--primary); border-color: var(--primary); transform: translateY(-3px) scale(1.05); box-shadow: 0 6px 15px rgba(16,185,129,0.2); }

        .char-count { position: absolute; right: 14px; bottom: 12px; font-size: 11px; color: var(--text-sub); font-weight: 700; }

        /* Star Rating Animation */
        .star-rating { display: flex; gap: 10px; font-size: 26px; cursor: pointer; flex-direction: row-reverse; justify-content: flex-end; }
        .star-rating input { display: none; }
        .star-rating label { color: var(--border); transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.2s ease; }
        .star-rating label:hover { transform: scale(1.4) rotate(12deg); }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #f59e0b; filter: drop-shadow(0 0 8px rgba(245, 158, 11, 0.5)); }

        .btn-submit { background: linear-gradient(135deg, var(--primary), #059669); color: #ffffff; border: none; padding: 16px 22px; border-radius: 14px; font-weight: 800; font-size: 14.5px; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 12px 30px rgba(16, 185, 129, 0.4); transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); position: relative; overflow: hidden; }
        .btn-submit::after { content: ''; position: absolute; width: 100%; height: 100%; top: 0; left: -100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: 0.5s; }
        .btn-submit:hover::after { left: 100%; }
        .btn-submit:hover { transform: translateY(-4px); box-shadow: 0 18px 35px rgba(16, 185, 129, 0.6); filter: brightness(1.08); }
        .btn-submit:active { transform: translateY(-1px); }

        .search-filter-box { display: flex; gap: 10px; margin-bottom: 14px; }
        .search-input { flex: 1; padding: 12px 16px; background: var(--bg-main); border: 1.5px solid var(--border); border-radius: 12px; font-size: 13.5px; outline: none; color: var(--text-main); transition: all 0.35s ease; }
        .search-input:focus { border-color: var(--primary); background: var(--card-bg); box-shadow: 0 0 0 5px rgba(16,185,129,0.15); transform: translateY(-1px); }

        .filter-bar { display: flex; gap: 8px; margin-bottom: 18px; overflow-x: auto; padding-bottom: 6px; }
        .filter-btn { background: var(--bg-main); border: 1.5px solid var(--border); padding: 7px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; color: var(--text-sub); cursor: pointer; white-space: nowrap; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .filter-btn:hover { transform: translateY(-2px); color: var(--primary); border-color: var(--primary); }
        .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 6px 15px rgba(16,185,129,0.35); transform: translateY(-2px); }

        .feedback-scroll { max-height: 420px; overflow-y: auto; padding-right: 6px; }
        .feedback-scroll::-webkit-scrollbar { width: 6px; }
        .feedback-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

        /* Item Entry Animation */
        .feedback-item { background: var(--bg-main); border: 1.5px solid var(--border); padding: 18px; border-radius: 16px; margin-bottom: 14px; animation: fadeInUpItem 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .feedback-item:hover { border-color: var(--primary); box-shadow: 0 15px 35px rgba(16,185,129,0.12); transform: translateY(-4px) scale(1.01); }
        .stars { color: #f59e0b; font-size: 13px; }

        .sentiment-badge { font-size: 10.5px; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.6px; animation: fadeInScale 0.3s ease; }
        .sentiment-positive { background: #dcfce7; color: #15803d; }
        .sentiment-neutral { background: #fef3c7; color: #b45309; }
        .sentiment-negative { background: #fee2e2; color: #b91c1c; }

        @keyframes fadeInUpItem { from { opacity: 0; transform: translateY(25px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes pulseBadge { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
    </style>
</head>
<body>

    <div class="toast-container" id="toastContainer"></div>

    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                <span>CampusBite</span>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item"><a href="menu.php"><i class="fa-solid fa-list"></i> Food Menu</a></li>
                <li class="nav-item"><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> My Cart</a></li>
                <li class="nav-item"><a href="checkout.php"><i class="fa-solid fa-credit-card"></i> Checkout</a></li>
                <li class="nav-item active"><a href="feedback.php"><i class="fa-solid fa-comment-dots"></i> Feedback</a></li>
                <li class="nav-item"><a href="orders.php"><i class="fa-solid fa-clock-rotate-left"></i> Orders</a></li>
            </ul>
        </div>
        <div style="font-size: 11.5px; color: var(--text-sub); text-align: center; padding-top: 18px; border-top: 1px solid var(--border);">
            CampusBite Fluid v15.0 Pro
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <i class="fa-solid fa-comment-dots"></i> Enterprise Feedback Hub
            </div>
            <div class="header-actions">
                <button class="action-btn" id="exportBtn" title="Export Feedback Data"><i class="fa-solid fa-download"></i> Export CSV</button>
                <button class="action-btn" id="themeToggle" title="Toggle Theme"><i class="fa-solid fa-moon"></i></button>
            </div>
        </div>

        <div class="analytics-strip">
            <div class="analytic-card">
                <div class="analytic-icon blue"><i class="fa-solid fa-comments"></i></div>
                <div>
                    <div style="font-size: 10.5px; font-weight: 800; color: var(--text-sub); text-transform: uppercase;">Total</div>
                    <div style="font-size: 20px; font-weight: 800; color: var(--text-main);" id="statTotal"><?= $total_feedbacks; ?></div>
                </div>
            </div>
            <div class="analytic-card">
                <div class="analytic-icon amber"><i class="fa-solid fa-star"></i></div>
                <div>
                    <div style="font-size: 10.5px; font-weight: 800; color: var(--text-sub); text-transform: uppercase;">Average</div>
                    <div style="font-size: 20px; font-weight: 800; color: var(--text-main);" id="statAvg"><?= $avg_rating; ?> / 5</div>
                </div>
            </div>
            <div class="analytic-card">
                <div class="analytic-icon green"><i class="fa-solid fa-shield-heart"></i></div>
                <div>
                    <div style="font-size: 10.5px; font-weight: 800; color: var(--text-sub); text-transform: uppercase;">Satisfaction</div>
                    <div style="font-size: 20px; font-weight: 800; color: var(--text-main);" id="statSat"><span id="statSatVal"><?= $satisfaction_rate; ?></span>%</div>
                </div>
            </div>
            <div class="analytic-card">
                <div class="analytic-icon purple"><i class="fa-solid fa-brain"></i></div>
                <div>
                    <div style="font-size: 10.5px; font-weight: 800; color: var(--text-sub); text-transform: uppercase;">AI Sentiment</div>
                    <div style="font-size: 20px; font-weight: 800; color: var(--text-main);" id="aiSentimentStatus">
                        <?= $satisfaction_rate >= 70 ? 'Excellent' : 'Stable'; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="breakdown-card">
            <div>
                <h4 style="font-size: 13.5px; font-weight: 800; color: var(--text-main); margin-bottom: 14px;"><i class="fa-solid fa-chart-pie" style="color:var(--primary)"></i> Rating Distribution</h4>
                <?php for($i=5; $i>=1; $i--):$count = $rating_counts[$i];
                    $pct =$total_feedbacks > 0 ? round(($count / $total_feedbacks) * 100) : 0;
                ?>
                <div class="bar-row">
                    <span style="width: 38px;"><?= $i; ?> ⭐</span>
                    <div class="bar-track"><div class="bar-fill" style="width: <?= $pct; ?>%;"></div></div>
                    <span style="width: 38px; text-align: right;"><?= $count; ?></span>
                </div>
                <?php endfor; ?>
            </div>
            <div style="border-left: 1.5px solid var(--border); padding-left: 24px; display: flex; flex-direction: column; justify-content: center;">
                <div style="font-size: 12px; font-weight: 800; color: var(--text-sub); text-transform: uppercase; margin-bottom: 8px;">Draft Status</div>
                <div id="draftStatusIndicator" style="font-size: 14.5px; font-weight: 700; color: var(--text-main);">No unsaved drafts</div>
                <button onclick="clearDraft()" style="margin-top: 12px; background: none; border: 1.5px solid var(--border); padding: 8px 14px; border-radius: 10px; font-size: 11.5px; font-weight: 700; color: var(--text-sub); cursor: pointer; width: fit-content; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);" onmouseover="this.style.borderColor='var(--primary)'; this.style.color='var(--primary)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text-sub)'; this.style.transform='translateY(0)'">Clear Draft</button>
            </div>
        </div>

        <div class="feedback-grid">
            <div class="card">
                <h3 style="font-size: 17px; font-weight: 800; margin-bottom: 20px; color: var(--text-main); display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Share Your Experience
                </h3>
                <form id="feedbackForm">
                    <div class="form-group">
                        <label class="form-label">Subject / Topic</label>
                        <div class="preset-tags">
                            <span class="preset-tag" onclick="setSubject('Food Quality')">Food Quality</span>
                            <span class="preset-tag" onclick="setSubject('Delivery Speed')">Delivery Speed</span>
                            <span class="preset-tag" onclick="setSubject('Customer Service')">Customer Service</span>
                            <span class="preset-tag" onclick="setSubject('Pricing & Value')">Pricing & Value</span>
                        </div>
                        <input type="text" name="subject" id="subject" class="form-control" placeholder="e.g. Food Quality, Delivery Speed">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Rating Experience</label>
                        <div class="star-rating">
                            <input type="radio" id="star5" name="rating" value="5" checked><label for="star5" title="5 Stars"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 Stars"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 Stars"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 Stars"><i class="fa-solid fa-star"></i></label>
                            <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 Star"><i class="fa-solid fa-star"></i></label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Feedback Message *</label>
                        <textarea name="message" id="message" class="form-control" placeholder="Tell us what you liked or how we can improve..." maxlength="500" required></textarea>
                        <div class="char-count"><span id="charCount">0</span>/500</div>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-submit">
                        Submit Feedback <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>

            <div class="card">
                <h3 style="font-size: 17px; font-weight: 800; margin-bottom: 16px; color: var(--text-main); display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> My Previous Feedback
                </h3>

                <div class="search-filter-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search feedbacks...">
                </div>

                <div class="filter-bar">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="5">5 ⭐</button>
                    <button class="filter-btn" data-filter="4">4 ⭐</button>
                    <button class="filter-btn" data-filter="3">3 ⭐</button>
                    <button class="filter-btn" data-filter="1,2">Low (1-2 ⭐)</button>
                </div>

                <div class="feedback-scroll" id="feedbackContainer">
                    <?php if (!empty($all_feedbacks_array)): ?>
                        <?php foreach ($all_feedbacks_array as$fb): ?>
                            <?php 
                                $r = (int)$fb['rating'];
                                $s_class =$r >= 4 ? 'sentiment-positive' : ($r == 3 ? 'sentiment-neutral' : 'sentiment-negative');$s_text = $r >= 4 ? 'Positive' : ($r == 3 ? 'Average' : 'Attention');
                            ?>
                            <div class="feedback-item" id="feedback-item-<?= $fb['id']; ?>" data-id="<?= $fb['id']; ?>" data-rating="<?= $r; ?>" data-text="<?= strtolower(htmlspecialchars($fb['subject'] . ' ' .$fb['message'])); ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <div class="stars">
                                        <?= str_repeat('<i class="fa-solid fa-star"></i>', $r); ?>
                                        <?= str_repeat('<i class="fa-regular fa-star" style="color:#cbd5e1;"></i>', 5 - $r); ?>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="sentiment-badge <?= $s_class; ?>"><?= $s_text; ?></span>
                                        <button onclick="deleteFeedback(<?= $fb['id']; ?>)" title="Delete Feedback" style="background:none; border:none; color:var(--text-sub); cursor:pointer; font-size:12.5px; transition:all 0.3s cubic-bezier(0.4,0,0.2,1);" onmouseover='this.style.color="#ef4444"; this.style.transform="scale(1.2)"' onmouseout='this.style.color="var(--text-sub)"; this.style.transform="scale(1)"'>
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </div>
                                <div style="font-weight: 700; font-size: 14.5px; color: var(--text-main);">
                                    <?= htmlspecialchars($fb['subject'] ?: 'General Feedback'); ?>
                                </div>
                                <div style="font-size: 13.5px; color: var(--text-sub); margin-top: 6px; line-height: 1.6;">
                                    <?= htmlspecialchars($fb['message']); ?>
                                </div>
                                <div style="font-size: 11.5px; color: var(--text-sub); margin-top: 10px; display: flex; align-items: center; gap: 6px;">
                                    <i class="fa-regular fa-clock"></i> <?= date('M d, Y - h:i A', strtotime($fb['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div id="noFeedbackText" style="text-align: center; padding: 45px 0; color: var(--text-sub);">
                            <i class="fa-regular fa-comments" style="font-size: 40px; margin-bottom: 12px; color: var(--border);"></i>
                            <p style="font-size: 13.5px; font-weight: 700;">You haven't submitted any feedback yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setSubject(val) {
            document.getElementById('subject').value = val;
            saveDraft();
        }

        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', targetTheme);
            themeToggle.innerHTML = targetTheme === 'dark' ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
        });

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i> ${message}`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 2800);
        }

        const messageInput = document.getElementById('message');
        const subjectInput = document.getElementById('subject');
        const charCount = document.getElementById('charCount');
        const draftIndicator = document.getElementById('draftStatusIndicator');

        function saveDraft() {
            localStorage.setItem('cb_feedback_sub', subjectInput.value);
            localStorage.setItem('cb_feedback_msg', messageInput.value);
            if(messageInput.value.length > 0 || subjectInput.value.length > 0) {
                draftIndicator.innerText = "Draft saved locally 💾";
            } else {
                draftIndicator.innerText = "No unsaved drafts";
            }
        }

        function clearDraft() {
            localStorage.removeItem('cb_feedback_sub');
            localStorage.removeItem('cb_feedback_msg');
            subjectInput.value = '';
            messageInput.value = '';
            charCount.innerText = '0';
            draftIndicator.innerText = "No unsaved drafts";
            showToast('Draft cleared!', 'success');
        }

        window.addEventListener('DOMContentLoaded', () => {
            const savedSub = localStorage.getItem('cb_feedback_sub');
            const savedMsg = localStorage.getItem('cb_feedback_msg');
            if (savedSub) subjectInput.value = savedSub;
            if (savedMsg) {
                messageInput.value = savedMsg;
                charCount.innerText = savedMsg.length;
                draftIndicator.innerText = "Draft loaded from storage 📂";
            }
        });

        messageInput.addEventListener('input', () => {
            charCount.innerText = messageInput.value.length;
            saveDraft();
        });

        subjectInput.addEventListener('input', saveDraft);

        const feedbackForm = document.getElementById('feedbackForm');
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Submitting...`;

            const formData = new FormData(feedbackForm);
            formData.append('ajax_submit', '1');

            fetch('feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `Submit Feedback <i class="fa-solid fa-paper-plane"></i>`;

                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    feedbackForm.reset();
                    clearDraft();
                    charCount.innerText = '0';

                    const container = document.getElementById('feedbackContainer');
                    const noText = document.getElementById('noFeedbackText');
                    if (noText) noText.remove();

                    container.insertAdjacentHTML('afterbegin', data.html);
                    
                    let statTotal = document.getElementById('statTotal');
                    statTotal.innerText = parseInt(statTotal.innerText) + 1;
                    
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `Submit Feedback <i class="fa-solid fa-paper-plane"></i>`;
                showToast('Something went wrong!', 'error');
            });
        });

        function deleteFeedback(id) {
            if (!confirm('Are you sure you want to delete this feedback?')) return;

            const formData = new FormData();
            formData.append('delete_id', id);

            fetch('feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    const item = document.getElementById(`feedback-item-${id}`);
                    if (item) {
                        item.style.transform = 'scale(0.9) translateX(40px)';
                        item.style.opacity = '0';
                        setTimeout(() => {
                            item.remove();
                            let statTotal = document.getElementById('statTotal');
                            let currentTotal = parseInt(statTotal.innerText) - 1;
                            statTotal.innerText = currentTotal >= 0 ? currentTotal : 0;
                            
                            const container = document.getElementById('feedbackContainer');
                            if (container.querySelectorAll('.feedback-item').length === 0) {
                                container.innerHTML = `
                                    <div id="noFeedbackText" style="text-align: center; padding: 45px 0; color: var(--text-sub);">
                                        <i class="fa-regular fa-comments" style="font-size: 40px; margin-bottom: 12px; color: var(--border);"></i>
                                        <p style="font-size: 13.5px; font-weight: 700;">You haven't submitted any feedback yet.</p>
                                    </div>`;
                            }
                        }, 350);
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(() => {
                showToast('Failed to delete feedback!', 'error');
            });
        }

        const searchInput = document.getElementById('searchInput');
        const filterBtns = document.querySelectorAll('.filter-btn');
        let currentFilter = 'all';

        function applyFilterAndSearch() {
            const query = searchInput.value.toLowerCase().trim();
            const items = document.querySelectorAll('.feedback-item');

            items.forEach(item => {
                const rating = item.getAttribute('data-rating');
                const text = item.getAttribute('data-text');

                const matchesFilter = (currentFilter === 'all' || currentFilter.includes(rating));
                const matchesSearch = text.includes(query);

                if (matchesFilter && matchesSearch) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', applyFilterAndSearch);

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentFilter = btn.getAttribute('data-filter');
                applyFilterAndSearch();
            });
        });

        document.getElementById('exportBtn').addEventListener('click', () => {
            const items = document.querySelectorAll('.feedback-item');
            if (items.length === 0) {
                showToast('No feedback data available to export!', 'error');
                return;
            }

            let csv = 'Subject,Rating,Date\n';
            items.forEach(item => {
                const subject = item.querySelector('div[style*="font-weight: 700"]').innerText.replace(/,/g, '');
                const rating = item.getAttribute('data-rating');
                const date = item.querySelector('div[style*="font-size: 11.5px"]').innerText.trim();
                csv += `"${subject}",${rating},"${date}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.setAttribute('download', 'campusbite_feedbacks_pro.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            showToast('Feedback data exported successfully!', 'success');
        });
    </script>
</body>
</html>
