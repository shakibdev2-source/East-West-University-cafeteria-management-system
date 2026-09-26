<?php
session_start();

// ১. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// ২. ইউজার আইডি
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3;

$success_msg = "";
$error_msg = "";

// ৩. ফিডব্যাক সাবমিট
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject'] ?? ''));
    $message = mysqli_real_escape_string($conn, trim($_POST['message'] ?? ''));
    $rating = intval($_POST['rating'] ?? 5);

    if (empty($message)) {
        $error_msg = "Please write your feedback message!";
    } else {
        $insert_sql = "INSERT INTO feedback (user_id, subject, message, rating) VALUES ('$user_id', '$subject', '$message', '$rating')";
        
        if (mysqli_query($conn, $insert_sql)) {
            $success_msg = "Thank you! Your feedback has been submitted successfully.";
        } else {
            $error_msg = "Database Error: " . mysqli_error($conn);
        }
    }
}

// ৪. ইউজারের আগের ফিডব্যাক ফেচ (ORDER BY 1 DESC ১ম কলাম অনুযায়ী সর্ট করবে)
$feedback_query = "SELECT * FROM feedback WHERE user_id = '$user_id' ORDER BY 1 DESC";
$feedback_result = mysqli_query($conn, $feedback_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | CampusBite</title>
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

        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #15803d; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }

        .feedback-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; align-items: start; }
        .card { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; }
        textarea.form-control { resize: vertical; min-height: 100px; }

        .rating-select { display: flex; gap: 10px; margin-top: 6px; }
        .rating-select label { cursor: pointer; font-size: 18px; color: #e2e8f0; }
        
        .btn-submit { background: #4f46e5; color: #ffffff; border: none; padding: 12px 20px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; width: 100%; }
        .btn-submit:hover { background: #4338ca; }

        .feedback-item { border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 14px; }
        .feedback-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .stars { color: #f59e0b; font-size: 13px; margin-bottom: 4px; }
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
                <li class="nav-item active"><a href="feedback.php"><i class="fa-solid fa-comment-dots"></i> Feedback</a></li>
                <li class="nav-item"><a href="orders.php"><i class="fa-solid fa-clock-rotate-left"></i> Orders</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="page-title"><i class="fa-solid fa-comment-dots"></i> Send Us Feedback</div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="feedback-grid">
            <div class="card">
                <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 18px;">Share Your Experience</h3>
                <form method="POST" action="feedback.php">
                    <div class="form-group">
                        <label class="form-label">Subject (Optional)</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g. Food Quality / Service">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-control">
                            <option value="5">⭐⭐⭐⭐⭐ (5 - Excellent)</option>
                            <option value="4">⭐⭐⭐⭐ (4 - Good)</option>
                            <option value="3">⭐⭐⭐ (3 - Average)</option>
                            <option value="2">⭐⭐ (2 - Poor)</option>
                            <option value="1">⭐ (1 - Very Bad)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Your Feedback / Comments *</label>
                        <textarea name="message" class="form-control" placeholder="Write your valuable feedback here..." required></textarea>
                    </div>

                    <button type="submit" name="submit_feedback" class="btn-submit">
                        Submit Feedback <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>

            <div class="card">
                <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 18px;">My Previous Feedback</h3>
                <?php if ($feedback_result && mysqli_num_rows($feedback_result) > 0): ?>
                    <?php while ($fb = mysqli_fetch_assoc($feedback_result)): ?>
                        <div class="feedback-item">
                            <div class="stars">
                                <?= str_repeat('<i class="fa-solid fa-star"></i> ', $fb['rating']); ?>
                            </div>
                            <div style="font-weight:700; font-size:14px; color:#0f172a;">
                                <?= htmlspecialchars($fb['subject'] ?: 'General Feedback'); ?>
                            </div>
                            <div style="font-size:13px; color:#475569; margin-top:4px;">
                                <?= htmlspecialchars($fb['message']); ?>
                            </div>
                            <div style="font-size:11px; color:#94a3b8; margin-top:6px;">
                                <?= date('M d, Y', strtotime($fb['created_at'])); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:#94a3b8; font-size:14px; text-align:center; padding: 20px 0;">You haven't submitted any feedback yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>