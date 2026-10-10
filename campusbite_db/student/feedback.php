<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3;

$success_msg = "";
$error_msg = "";

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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #1e293b; min-height: 100vh; }

        .sidebar { width: 260px; background: #ffffff; padding: 24px 20px; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; position: fixed; height: 100vh; }
        .brand { font-size: 20px; font-weight: 800; color: #4f46e5; display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }
        .brand-icon { background: linear-gradient(135deg, #4f46e5, #6366f1); width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 8px 18px rgba(79, 70, 229, 0.25); }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 12px; transition: 0.2s; }
        .nav-item.active a, .nav-item a:hover { background: #eef2ff; color: #4f46e5; font-weight: 700; }

        .main-content { flex: 1; margin-left: 260px; padding: 35px 45px; overflow-y: auto; max-width: 1400px; }
        .page-title { font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; }
        .page-title i { color: #4f46e5; background: #eef2ff; padding: 10px; border-radius: 12px; font-size: 20px; }

        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #15803d; padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(22,163,74,0.05); }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(220,38,38,0.05); }

        .feedback-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 28px; align-items: start; }
        .card { background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; padding: 28px; box-shadow: 0 4px 20px -4px rgba(0,0,0,0.03); transition: transform 0.3s ease; }
        .card:hover { box-shadow: 0 10px 30px -5px rgba(0,0,0,0.06); }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; outline: none; transition: all 0.3s; color: #1e293b; }
        .form-control:focus { border-color: #4f46e5; background: #ffffff; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); }
        textarea.form-control { resize: vertical; min-height: 120px; }

        /* Modern Star Rating Design */
        .star-rating { display: flex; gap: 8px; font-size: 24px; cursor: pointer; flex-direction: row-reverse; justify-content: flex-end; }
        .star-rating input { display: none; }
        .star-rating label { color: #cbd5e1; transition: color 0.2s; }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #f59e0b; }

        .btn-submit { background: linear-gradient(135deg, #4f46e5, #6366f1); color: #ffffff; border: none; padding: 14px 20px; border-radius: 12px; font-weight: 700; font-size: 14px; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3); transition: all 0.3s; }
        .btn-submit:hover { background: linear-gradient(135deg, #4338ca, #4f46e5); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4); }

        .feedback-scroll { max-height: 500px; overflow-y: auto; padding-right: 4px; }
        .feedback-scroll::-webkit-scrollbar { width: 5px; }
        .feedback-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

        .feedback-item { background: #f8fafc; border: 1px solid #f1f5f9; padding: 16px; border-radius: 14px; margin-bottom: 14px; transition: transform 0.2s; }
        .feedback-item:hover { border-color: #cbd5e1; }
        .stars { color: #f59e0b; font-size: 12px; margin-bottom: 6px; }
    </style>
</head>
<body>

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
        <div style="font-size: 12px; color: #94a3b8; text-align: center; padding-top: 20px; border-top: 1px solid #f1f5f9;">
            CampusBite v2.5
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
            <!-- Submit Feedback Form -->
            <div class="card">
                <h3 style="font-size: 17px; font-weight: 800; margin-bottom: 20px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-pen-to-square" style="color: #4f46e5;"></i> Share Your Experience
                </h3>
                <form method="POST" action="feedback.php">
                    <div class="form-group">
                        <label class="form-label">Subject / Topic</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g. Food Quality, Delivery Speed">
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
                        <label class="form-label">Your Feedback / Comments *</label>
                        <textarea name="message" class="form-control" placeholder="Tell us what you liked or how we can improve..." required></textarea>
                    </div>

                    <button type="submit" name="submit_feedback" class="btn-submit">
                        Submit Feedback <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>

            <!-- Previous Feedbacks -->
            <div class="card">
                <h3 style="font-size: 17px; font-weight: 800; margin-bottom: 20px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-clock-rotate-left" style="color: #4f46e5;"></i> My Previous Feedback
                </h3>
                <div class="feedback-scroll">
                    <?php if ($feedback_result && mysqli_num_rows($feedback_result) > 0): ?>
                        <?php while ($fb = mysqli_fetch_assoc($feedback_result)): ?>
                            <div class="feedback-item">
                                <div class="stars">
                                    <?= str_repeat('<i class="fa-solid fa-star"></i>', $fb['rating']); ?>
                                    <?= str_repeat('<i class="fa-regular fa-star" style="color:#cbd5e1;"></i>', 5 - $fb['rating']); ?>
                                </div>
                                <div style="font-weight: 700; font-size: 14px; color: #0f172a;">
                                    <?= htmlspecialchars($fb['subject'] ?: 'General Feedback'); ?>
                                </div>
                                <div style="font-size: 13px; color: #475569; margin-top: 4px; line-height: 1.5;">
                                    <?= htmlspecialchars($fb['message']); ?>
                                </div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 8px; display: flex; align-items: center; gap: 4px;">
                                    <i class="fa-regular fa-calendar"></i> <?= date('M d, Y - h:i A', strtotime($fb['created_at'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 0; color: #94a3b8;">
                            <i class="fa-regular fa-comments" style="font-size: 36px; margin-bottom: 10px; color: #cbd5e1;"></i>
                            <p style="font-size: 14px; font-weight: 600;">You haven't submitted any feedback yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
