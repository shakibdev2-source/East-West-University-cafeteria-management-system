=<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

// ১. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$success_msg = "";
$error_msg = "";

// ২. খাবার আইটেম যুক্ত করার প্রসেস
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_food'])) {
    $food_name = mysqli_real_escape_string($conn, trim($_POST['food_name'] ?? ''));
    $category  = mysqli_real_escape_string($conn, trim($_POST['category'] ?? ''));
    $price     = floatval($_POST['price'] ?? 0);

    // ইমেজ আপলোড হ্যান্ডলিং
    $image_name = "";
    if (isset($_FILES['image']['name']) && $_FILES['image']['name'] != "") {
        $image_name = time() . '_' . $_FILES['image']['name'];
        $target = "../uploads/" . $image_name;
        
        // ফোল্ডার না থাকলে অটো তৈরি করবে
        if (!file_exists('../uploads/')) {
            mkdir('../uploads/', 0777, true);
        }
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
    }

    if (empty($food_name) || empty($price)) {
        $error_msg = "Please fill in all required fields (Food Name & Price).";
    } else {
        $query = "INSERT INTO foods (food_name, category, price, image) VALUES ('$food_name', '$category', '$price', '$image_name')";
        if (mysqli_query($conn, $query)) {
            $success_msg = "New food item added successfully!";
        } else {
            $error_msg = "Database Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Food | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #0f172a; min-height: 100vh; }

        /* Left Side Accent Line & Sidebar */
        .sidebar { 
            width: 260px; 
            background: #0f172a; 
            padding: 28px 18px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            position: fixed; 
            height: 100vh; 
            z-index: 100;
            border-left: 5px solid #4f46e5;
            box-shadow: 4px 0 25px rgba(0,0,0,0.05);
        }
        
        .brand { font-size: 20px; font-weight: 800; color: #ffffff; display: flex; align-items: center; gap: 12px; margin-bottom: 35px; padding-left: 6px; }
        .brand-icon { background: #4f46e5; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; }
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 12px; padding: 12px 14px; text-decoration: none; color: #94a3b8; font-size: 13.5px; font-weight: 600; border-radius: 10px; transition: all 0.2s ease; }
        .nav-item.active a { background: #1e293b; color: #ffffff; border-left: 3px solid #6366f1; }
        .nav-item.active a i { color: #6366f1; }
        .nav-item a:hover { background: #1e293b; color: #ffffff; }

        /* Main Content Panel */
        .main-content { flex: 1; margin-left: 260px; padding: 32px 40px; }

        /* Top Header */
        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            background: #ffffff; 
            padding: 16px 24px; 
            border-radius: 16px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
        }
        .page-title h1 { font-size: 22px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .page-title p { font-size: 12.5px; color: #64748b; margin-top: 2px; }

        /* Alerts */
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #15803d; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        /* Form Container Card */
        .form-card { 
            background: #ffffff; 
            border-radius: 16px; 
            border: 1px solid #e2e8f0; 
            padding: 32px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
            max-width: 800px; 
        }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: span 2; }

        .form-label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 12px 16px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; color: #0f172a; outline: none; transition: all 0.2s; }
        .form-control:focus { border-color: #4f46e5; background: #ffffff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

        /* File Upload Customization */
        .file-input-wrapper { position: relative; }
        .file-input-wrapper input[type="file"] { padding: 9px 12px; background: #f8fafc; }

        /* Buttons */
        .btn-submit { background: #4f46e5; color: #ffffff; border: none; padding: 13px 26px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
        .btn-submit:hover { background: #4338ca; }
        .btn-cancel { background: #f1f5f9; color: #475569; text-decoration: none; padding: 13px 22px; border-radius: 10px; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; margin-left: 10px; }
        .btn-cancel:hover { background: #e2e8f0; }
    </style>
</head>
<body>

    <!-- Sidebar Menu -->
    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                CampusBite
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item active"><a href="add_food.php"><i class="fa-solid fa-plus-circle"></i> Add Food</a></li>
                <li class="nav-item"><a href="manage_food.php"><i class="fa-solid fa-bowl-food"></i> Manage Food</a></li>
                <li class="nav-item"><a href="manage_orders.php"><i class="fa-solid fa-receipt"></i> Manage Orders</a></li>
                <li class="nav-item"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li class="nav-item"><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
            </ul>
        </div>
        <div class="nav-menu">
            <li class="nav-item"><a href="../logout.php" style="color:#ef4444;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-utensils" style="color:#4f46e5;"></i> Add New Food Item</h1>
                <p>Create and publish new items to the CampusBite cafeteria menu.</p>
            </div>
            <a href="manage_food.php" class="btn-cancel"><i class="fa-solid fa-arrow-left" style="margin-right:6px;"></i> Back to List</a>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <!-- Professional Form Card -->
        <div class="form-card">
            <form method="POST" action="add_food.php" enctype="multipart/form-data">
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label class="form-label">Food Name *</label>
                        <input type="text" name="food_name" class="form-control" placeholder="e.g. Chicken Burger" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="Fast Food">Fast Food</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Snacks">Snacks</option>
                            <option value="Lunch/Dinner">Lunch / Dinner</option>
                            <option value="Desserts">Desserts</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Price (৳) *</label>
                        <input type="number" step="0.01" name="price" class="form-control" placeholder="e.g. 120.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Food Image</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>

                </div>

                <div style="margin-top: 10px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                    <button type="submit" name="add_food" class="btn-submit">
                        <i class="fa-solid fa-plus-circle"></i> Save & Add Food
                    </button>
                    <a href="manage_food.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>