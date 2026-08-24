<?php
session_start();

// Database Connection
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Handle Ajax Request for Add to Cart
if (isset($_POST['ajax_add_to_cart'])) {
    $food_id = intval($_POST['food_id']);
    $quantity = intval($_POST['quantity']);
    $user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 1;

    if ($food_id > 0 && $quantity > 0) {
        $check_cart = mysqli_query($conn, "SELECT * FROM cart WHERE user_id = '$user_id' AND food_id = '$food_id'");
        
        if ($check_cart && mysqli_num_rows($check_cart) > 0) {
            $sql = "UPDATE cart SET quantity = quantity + $quantity WHERE user_id = '$user_id' AND food_id = '$food_id'";
        } else {
            $sql = "INSERT INTO cart (user_id, food_id, quantity) VALUES ('$user_id', '$food_id', '$quantity')";
        }

        if (mysqli_query($conn, $sql)) {
            echo json_encode(['status' => 'success', 'message' => 'Item added to cart successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid food ID or quantity!']);
    }
    exit();
}

// Fetch foods from database
$result = mysqli_query($conn, "SELECT * FROM foods");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusBite | Food Menu</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { display: flex; background: #f8fafc; color: #333; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 240px; background: #fff; padding: 20px; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; }
        .brand { font-size: 20px; font-weight: bold; color: #4f46e5; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 10px 14px; text-decoration: none; color: #64748b; font-weight: 500; border-radius: 8px; }
        .nav-item.active a, .nav-item a:hover { background: #4f46e5; color: #fff; }
        .logout-link { text-decoration: none; color: #ef4444; font-weight: bold; display: flex; align-items: center; gap: 8px; }

        /* Main Content */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .food-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }

        .food-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; }
        .img-box { position: relative; width: 100%; height: 150px; background: #eee; }
        .img-box img { width: 100%; height: 100%; object-fit: cover; }
        .cat-tag { position: absolute; top: 10px; left: 10px; background: rgba(255, 255, 255, 0.95); padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: #475569; }

        .card-body { padding: 15px; display: flex; flex-direction: column; gap: 8px; flex: 1; justify-content: space-between; }
        .food-name { font-size: 15px; font-weight: bold; color: #0f172a; }
        .price-row { display: flex; justify-content: space-between; align-items: center; }
        .price { font-size: 16px; font-weight: 800; color: #059669; }
        .total-price { font-size: 11px; color: #94a3b8; }

        .qty-box { display: flex; justify-content: center; align-items: center; gap: 12px; background: #f8fafc; padding: 4px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .qty-btn { border: none; background: #fff; width: 24px; height: 24px; border-radius: 4px; font-weight: bold; cursor: pointer; border: 1px solid #cbd5e1; }
        .qty-btn:hover { background: #e2e8f0; }

        .add-btn { width: 100%; background: #4f46e5; color: white; border: none; padding: 10px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 5px; transition: 0.2s; }
        .add-btn:hover { background: #4338ca; }

        /* Notification Popup */
        #notification { position: fixed; top: 20px; right: 20px; padding: 14px 22px; border-radius: 8px; color: white; font-weight: bold; display: none; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div>
            <div class="brand"><i class="fa-solid fa-utensils"></i> CampusBite</div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item active"><a href="menu.php"><i class="fa-solid fa-list"></i> Food Menu</a></li>
                <li class="nav-item"><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> My Cart</a></li>
                <li class="nav-item"><a href="checkout.php"><i class="fa-solid fa-credit-card"></i> Checkout</a></li>
                <li class="nav-item"><a href="orders.php"><i class="fa-solid fa-clock-rotate-left"></i> Orders</a></li>
                <li class="nav-item"><a href="feedback.php"><i class="fa-solid fa-comment-dots"></i> Feedback</a></li>
            </ul>
        </div>
        <a href="../logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>

    <div class="main-content">
        <h2 style="margin-bottom: 20px;">Food Menu</h2>
        
        <div class="food-grid">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($food = mysqli_fetch_assoc($result)): ?>
                    <?php 
                        $raw_name = trim($food['image'] ?? '');
                        $base_dir = __DIR__ . '/../assets/images/';
                        $web_path = '../assets/images/';
                        $final_src = '';

                        if (filter_var($raw_name, FILTER_VALIDATE_URL)) {
                            $final_src = $raw_name;
                        } elseif (!empty($raw_name)) {
                            $clean_name = pathinfo($raw_name, PATHINFO_FILENAME);
                            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                                if (file_exists($base_dir . $clean_name . '.' . $ext)) {
                                    $final_src = $web_path . $clean_name . '.' . $ext;
                                    break;
                                }
                            }
                            if (empty($final_src) && file_exists($base_dir . $raw_name)) {
                                $final_src = $web_path . $raw_name;
                            }
                        }

                        if (empty($final_src)) {
                            $final_src = "https://placehold.co/300x200?text=" . urlencode($food['food_name']);
                        }

                        $food_id = $food['food_id'] ?? $food['id'];
                        $price = floatval($food['price']);
                    ?>
                    <div class="food-card" data-price="<?= $price; ?>">
                        <div class="img-box">
                            <span class="cat-tag"><?= htmlspecialchars($food['category'] ?? 'Food'); ?></span>
                            <img src="<?= htmlspecialchars($final_src); ?>" alt="<?= htmlspecialchars($food['food_name']); ?>">
                        </div>
                        <div class="card-body">
                            <div class="food-name"><?= htmlspecialchars($food['food_name']); ?></div>
                            <div class="price-row">
                                <span class="price">৳<?= $price; ?></span>
                                <span class="total-price">Total: ৳<span class="total-val"><?= $price; ?></span></span>
                            </div>

                            <div class="qty-box">
                                <button type="button" class="qty-btn minus-btn">-</button>
                                <span class="qty-val">1</span>
                                <button type="button" class="qty-btn plus-btn">+</button>
                            </div>

                            <button type="button" class="add-btn" onclick="addToCart(<?= $food_id; ?>, this)">
                                <i class="fa-solid fa-cart-shopping"></i> Add To Cart
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Popup Notification Container -->
    <div id="notification"></div>

    <script>
        // Quantity Plus/Minus functionality
        document.querySelectorAll('.food-card').forEach(card => {
            const minusBtn = card.querySelector('.minus-btn');
            const plusBtn = card.querySelector('.plus-btn');
            const qtyVal = card.querySelector('.qty-val');
            const totalVal = card.querySelector('.total-val');
            const price = parseFloat(card.dataset.price);

            plusBtn.addEventListener('click', () => {
                let q = parseInt(qtyVal.textContent) + 1;
                qtyVal.textContent = q;
                totalVal.textContent = (price * q).toFixed(0);
            });

            minusBtn.addEventListener('click', () => {
                let q = parseInt(qtyVal.textContent);
                if (q > 1) {
                    q--;
                    qtyVal.textContent = q;
                    totalVal.textContent = (price * q).toFixed(0);
                }
            });
        });

        // Add to Cart via AJAX (Without reloading the page)
        function addToCart(foodId, btnElement) {
            const card = btnElement.closest('.food-card');
            const quantity = parseInt(card.querySelector('.qty-val').textContent);

            const formData = new FormData();
            formData.append('ajax_add_to_cart', '1');
            formData.append('food_id', foodId);
            formData.append('quantity', quantity);

            fetch('menu.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                showNotification(data.message, data.status === 'success' ? '#10b981' : '#ef4444');
            })
            .catch(err => {
                showNotification('Something went wrong!', '#ef4444');
            });
        }

        function showNotification(msg, color) {
            const notif = document.getElementById('notification');
            notif.style.background = color;
            notif.textContent = msg;
            notif.style.display = 'block';

            setTimeout(() => {
                notif.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>