<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 3;

// Handle Delete Request via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    header('Content-Type: application/json');
    $del_id = intval($_POST['delete_id']);$del_sql = "DELETE FROM feedback WHERE id = '$del_id' AND user_id = '$user_id'";
    if (mysqli_query($conn,$del_sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Feedback deleted successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete feedback.']);
    }
    exit;
}

// Handle Insert Request via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit'])) {
    header('Content-Type: application/json');
    $subject = mysqli_real_escape_string($conn, trim($_POST['subject'] ?? ''));$message = mysqli_real_escape_string($conn, trim($_POST['message'] ?? ''));
    $rating = intval($_POST['rating'] ?? 5);

    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Please write your feedback message!']);
        exit;
    }

    $insert_sql = "INSERT INTO feedback (user_id, subject, message, rating) VALUES ('$user_id', '$subject', '$message', '$rating')";
    
    if (mysqli_query($conn,$insert_sql)) {
        $new_id = mysqli_insert_id($conn);
        $date_formatted = date('M d, Y - h:i A');$stars_html = str_repeat('<i class="fa-solid fa-star"></i>', $rating) . str_repeat('<i class="fa-regular fa-star" style="color:#cbd5e1;"></i>', 5 - $rating);
        $sub_text = htmlspecialchars($subject ?: 'General Feedback');
        $msg_text = htmlspecialchars($message);

        $sentiment_class =$rating >= 4 ? 'sentiment-positive' : ($rating == 3 ? 'sentiment-neutral' : 'sentiment-negative');$sentiment_text = $rating >= 4 ? 'Positive' : ($rating == 3 ? 'Average' : 'Attention');

        $html_item = "
        <div class='feedback-item' id='feedback-item-{$new_id}' data-id='{$new_id}' data-rating='{$rating}' data-text='" . strtolower($sub_text . ' ' .$msg_text) . "'>
            <div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;'>
                <div class='stars'>{$stars_html}</div>
                <div style='display: flex; align-items: center; gap: 8px;'>
                    <span class='sentiment-badge {$sentiment_class}'>{$sentiment_text}</span>
                    <button onclick='deleteFeedback({$new_id})' title='Delete Feedback' style='background:none; border:none; color:#94a3b8; cursor:pointer; font-size:13px; transition:0.2s;' onmouseover='this.style.color=\"#ef4444\"' onmouseout='this.style.color=\"#94a3b8\"'>
                        <i class='fa-solid fa-trash-can'></i>
                    </button>
                </div>
            </div>
            <div style='font-weight: 700; font-size: 14.5px; color: #0f172a;'>{$sub_text}</div>
            <div style='font-size: 13.5px; color: #475569; margin-top: 6px; line-height: 1.6;'>{$msg_text}</div>
            <div style='font-size: 11.5px; color: #94a3b8; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;'>
                <span style='display: flex; align-items: center; gap: 6px;'><i class='fa-regular fa-clock'></i> {$date_formatted}</span>
                <span style='font-size: 11px; font-weight:700; color:#4f46e5; background:#eef2ff; padding:2px 8px; border-radius:6px;'>Just Now</span>
            </div>
        </div>";

        echo json_encode(['status' => 'success', 'html' => $html_item, 'message' => 'Feedback submitted successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . mysqli_error($conn)]);
    }
    exit;
}

$feedback_query = "SELECT * FROM feedback WHERE user_id = '$user_id' ORDER BY id DESC";
$feedback_result = mysqli_query($conn,$feedback_query);

$total_feedbacks = 0;
$total_rating_sum = 0;
$rating_counts = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];

$all_feedbacks_array = [];
if ($feedback_result) {
    while ($row = mysqli_fetch_assoc($feedback_result)) {
        $all_feedbacks_array[] =$row;
        $total_feedbacks++;
        $total_rating_sum += (int)$row['rating'];
        if(isset($rating_counts[(int)$row['rating']])) {
            $rating_counts[(int)$row['rating']]++;
        }
    }
}
$avg_rating = $total_feedbacks > 0 ? number_format($total_rating_sum / $total_feedbacks, 1) : '0.0';$satisfaction_rate = $total_feedbacks > 0 ? round((($rating_counts[5] + $rating_counts[4]) /$total_feedbacks) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Feedback Pro | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background-color: #f6f8fb; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

        .sidebar { width: 260px; background: #ffffff; padding: 24px 20px; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; position: fixed; height: 100vh; z-index: 100; }
        .brand { font-size: 20px; font-weight: 800; color: #4f46e5; display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }
        .brand-icon { background: linear-gradient(135deg, #4f46e5, #6366f1); width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 8px 18px rgba(79, 70, 229, 0.25); }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 12px; transition: 0.2s; }
        .nav-item.active a, .nav-item a:hover { background: #eef2ff; color: #4f46e5; font-weight: 700; }

        .main-content { flex: 1; margin-left: 260px; padding: 40px 50px; overflow-y: auto; max-width: 1440px; }
        .page-title { font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 24px; display: flex; align-items: center; gap: 14px; letter-spacing: -0.5px; }
        .page-title i { color: #4f46e5; background: #eef2ff; padding: 12px; border-radius: 14px; font-size: 20px; box-shadow: 0 4px 12px rgba(79,70,229,0.1); }

        /* Analytics Strip */
        .analytics-strip { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px; }
        .analytic-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px 24px; display: flex; align-items: center; gap: 18px; box-shadow: 0 4px 20px -4px rgba(0,0,0,0.03); transition: transform 0.3s ease; }
        .analytic-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px -5px rgba(0,0,0,0.06); }
        .analytic-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .analytic-icon.blue { background: #eef2ff; color: #4f46e5; }
        .analytic-icon.amber { background: #fef3c7; color: #d97706; }
        .analytic-icon.green { background: #dcfce7; color: #16a34a; }

        /* Toast */
        .toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { padding: 14px 22px; border-radius: 14px; font-size: 13.5px; font-weight: 700; box-shadow: 0 15px 30px rgba(0,0,0,0.12); display: flex; align-items: center; gap: 12px; animation: slideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1), fadeOut 0.35s cubic-bezier(0.16, 1, 0.3, 1) 2.6s forwards; color: #fff; }
        .toast.success { background: linear-gradient(135deg, #10b981, #059669); }
        .toast.error { background: linear-gradient(135deg, #ef4444, #dc2626); }
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { to { opacity: 0; transform: translateY(12px); } }

        .feedback-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 30px; align-items: start; }
        .card { background: #ffffff; border-radius: 24px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04); }
        
        .form-group { margin-bottom: 22px; position: relative; }
        .form-label { display: block; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.8px; }
        .form-control { width: 100%; padding: 14px 18px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; font-size: 14px; outline: none; transition: all 0.3s ease; color: #1e293b; }
        .form-control:focus { border-color: #4f46e5; background: #ffffff; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12); }
        textarea.form-control { resize: vertical; min-height: 130px; }

        .char-count { position: absolute; right: 14px; bottom: 12px; font-size: 11px; color: #94a3b8; font-weight: 700; }

        /* Star Rating */
        .star-rating { display: flex; gap: 10px; font-size: 26px; cursor: pointer; flex-direction: row-reverse; justify-content: flex-end; }
        .star-rating input { display: none; }
        .star-rating label { color: #cbd5e1; transition: color 0.2s ease, transform 0.2s ease; }
        .star-rating label:hover { transform: scale(1.15); }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #f59e0b; }

        .btn-submit { background: linear-gradient(135deg, #4f46e5, #6366f1); color: #ffffff; border: none; padding: 16px 20px; border-radius: 14px; font-weight: 800; font-size: 14.5px; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 8px 20px rgba(79, 70, 229, 0.35); transition: all 0.3s ease; }
        .btn-submit:hover { background: linear-gradient(135deg, #4338ca, #4f46e5); transform: translateY(-2px); box-shadow: 0 12px 25px rgba(79, 70, 229, 0.45); }

        /* Search & Filter */
        .search-filter-box { display: flex; gap: 10px; margin-bottom: 16px; }
        .search-input { flex: 1; padding: 11px 16px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 13.5px; outline: none; transition: all 0.3s; }
        .search-input:focus { border-color: #4f46e5; background: #fff; box-shadow: 0 0 0 4px rgba(79,70,229,0.1); }

        .filter-bar { display: flex; gap: 6px; margin-bottom: 18px; overflow-x: auto; padding-bottom: 4px; }
        .filter-btn { background: #f1f5f9; border: none; padding: 7px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #64748b; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .filter-btn.active, .filter-btn:hover { background: #4f46e5; color: #fff; box-shadow: 0 4px 12px rgba(79,70,229,0.25); }

        .feedback-scroll { max-height: 440px; overflow-y: auto; padding-right: 6px; }
        .feedback-scroll::-webkit-scrollbar { width: 6px; }
        .feedback-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        .feedback-item { background: #f8fafc; border: 1px solid #e2e8f0; padding: 18px; border-radius: 16px; margin-bottom: 14px; transition: all 0.3s ease; animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .feedback-item:hover { border-color: #cbd5e1; box-shadow: 0 6px 20px rgba(0,0,0,0.03); transform: translateY(-2px); }
        .stars { color: #f59e0b; font-size: 13px; }

        .sentiment-badge { font-size: 10.5px; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.6px; }
        .sentiment-positive { background: #dcfce7; color: #15803d; }
        .sentiment-neutral { background: #fef3c7; color: #b45309; }
        .sentiment-negative { background: #fee2e2; color: #b91c1c; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
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
        <div style="font-size: 12px; color: #94a3b8; text-align: center; padding-top: 20px; border-top: 1px solid #f1f5f9;">
            CampusBite UI Pro v5.1 Advanced
        </div>
    </div>

    <div class="main-content">
        <div class="page-title">
            <i class="fa-solid fa-comment-dots"></i> Enterprise Feedback Portal
        </div>

        <!-- Analytics Overview Strip -->
        <div class="analytics-strip">
            <div class="analytic-card">
                <div class="analytic-icon blue"><i class="fa-solid fa-comments"></i></div>
                <div>
                    <div style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Total Feedbacks</div>
                    <div style="font-size: 22px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="statTotal"><?= $total_feedbacks; ?></div>
                </div>
            </div>
            <div class="analytic-card">
                <div class="analytic-icon amber"><i class="fa-solid fa-star"></i></div>
                <div>
                    <div style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Average Rating</div>
                    <div style="font-size: 22px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="statAvg"><?= $avg_rating; ?> / 5.0</div>
                </div>
            </div>
            <div class="analytic-card">
                <div class="analytic-icon green"><i class="fa-solid fa-shield-heart"></i></div>
                <div>
                    <div style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Satisfaction Rate</div>
                    <div style="font-size: 22px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="statSat">
                        <span id="statSatVal"><?= $satisfaction_rate; ?></span>%
                    </div>
                </div>
            </div>
        </div>

        <div class="feedback-grid">
            <!-- Form Card -->
            <div class="card">
                <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 22px; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-pen-to-square" style="color: #4f46e5;"></i> Share Your Experience
                </h3>
                <form id="feedbackForm">
                    <div class="form-group">
                        <label class="form-label">Subject / Topic</label>
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
                        <label class="form-label">Your Feedback / Comments *</label>
                        <textarea name="message" id="message" class="form-control" placeholder="Tell us what you liked or how we can improve..." maxlength="500" required></textarea>
                        <div class="char-count"><span id="charCount">0</span>/500</div>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-submit">
                        Submit Feedback <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>

            <!-- List, Search & Filter Card -->
            <div class="card">
                <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 16px; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-clock-rotate-left" style="color: #4f46e5;"></i> My Previous Feedback
                </h3>

                <!-- Search Input Bar -->
                <div class="search-filter-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search feedbacks...">
                </div>

                <!-- Filter Bar -->
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
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div class="stars">
                                        <?= str_repeat('<i class="fa-solid fa-star"></i>', $r); ?>
                                        <?= str_repeat('<i class="fa-regular fa-star" style="color:#cbd5e1;"></i>', 5 - $r); ?>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="sentiment-badge <?= $s_class; ?>"><?= $s_text; ?></span>
                                        <button onclick="deleteFeedback(<?= $fb['id']; ?>)" title="Delete Feedback" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:13px; transition:0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </div>
                                <div style="font-weight: 700; font-size: 14.5px; color: #0f172a;">
                                    <?= htmlspecialchars($fb['subject'] ?: 'General Feedback'); ?>
                                </div>
                                <div style="font-size: 13.5px; color: #475569; margin-top: 6px; line-height: 1.6;">
                                    <?= htmlspecialchars($fb['message']); ?>
                                </div>
                                <div style="font-size: 11.5px; color: #94a3b8; margin-top: 10px; display: flex; align-items: center; gap: 6px;">
                                    <i class="fa-regular fa-clock"></i> <?= date('M d, Y - h:i A', strtotime($fb['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div id="noFeedbackText" style="text-align: center; padding: 50px 0; color: #94a3b8;">
                            <i class="fa-regular fa-comments" style="font-size: 40px; margin-bottom: 12px; color: #cbd5e1;"></i>
                            <p style="font-size: 14px; font-weight: 700;">You haven't submitted any feedback yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i> ${message}`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        const messageInput = document.getElementById('message');
        const charCount = document.getElementById('charCount');
        messageInput.addEventListener('input', () => {
            charCount.innerText = messageInput.value.length;
        });

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
                    charCount.innerText = '0';

                    const container = document.getElementById('feedbackContainer');
                    const noText = document.getElementById('noFeedbackText');
                    if (noText) noText.remove();

                    container.insertAdjacentHTML('afterbegin', data.html);
                    
                    // Live update counters smoothly
                    let statTotal = document.getElementById('statTotal');
                    statTotal.innerText = parseInt(statTotal.innerText) + 1;
                    
                    // Optional gentle refresh to fully sync analytics metrics
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `Submit Feedback <i class="fa-solid fa-paper-plane"></i>`;
                showToast('Something went wrong!', 'error');
            });
        });

        // Delete Feedback function via AJAX
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
                        item.style.transform = 'scale(0.9)';
                        item.style.opacity = '0';
                        setTimeout(() => {
                            item.remove();
                            let statTotal = document.getElementById('statTotal');
                            let currentTotal = parseInt(statTotal.innerText) - 1;
                            statTotal.innerText = currentTotal >= 0 ? currentTotal : 0;
                            
                            // Show placeholder if list is empty
                            const container = document.getElementById('feedbackContainer');
                            if (container.querySelectorAll('.feedback-item').length === 0) {
                                container.innerHTML = `
                                    <div id="noFeedbackText" style="text-align: center; padding: 50px 0; color: #94a3b8;">
                                        <i class="fa-regular fa-comments" style="font-size: 40px; margin-bottom: 12px; color: #cbd5e1;"></i>
                                        <p style="font-size: 14px; font-weight: 700;">You haven't submitted any feedback yet.</p>
                                    </div>`;
                            }
                        }, 300);
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
    </script>
</body>
</html>
