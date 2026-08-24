<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

// ১. ডাটাবেজ কানেকশন
$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$msg = "";

// ২. খাবার আইটেম ডিলিট করার হ্যান্ডলিং
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // ছবি মুছে ফেলার আগে ফোল্ডার থেকে ফাইল খোঁজা
    $img_query = mysqli_query($conn, "SELECT image FROM foods WHERE food_id = $delete_id");
    if ($img_query && $row = mysqli_fetch_assoc($img_query)) {
        if (!empty($row['image']) && file_exists("../uploads/" . $row['image'])) {
            unlink("../uploads/" . $row['image']);
        }
    }
    
    // ডাটাবেজ থেকে মুছে ফেলা
    $del_query = mysqli_query($conn, "DELETE FROM foods WHERE food_id = $delete_id");
    if ($del_query) {
        $msg = "Food item deleted successfully!";
    }
}

// ৩. সব ফুড ডেটা ফেচ করা
$foods_result = mysqli_query($conn, "SELECT * FROM foods ORDER BY food_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Food Items | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background-color: #f8fafc; color: #0f172a; min-height: 100vh; }

        /* Left Side Accent Sidebar */
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

        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 32px 40px; }

        /* Top Header */
        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            background: #ffffff; 
            padding: 16px 24px; 
            border-radius: 16px; 
            border: 1px solid #e2e8f0; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.02); 
        }
        .page-title h1 { font-size: 22px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .page-title p { font-size: 12.5px; color: #64748b; margin-top: 2px; }

        /* Alert */
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #15803d; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        /* Table Card */
        .card-table { background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        .custom-table th { background: #f8fafc; padding: 14px 20px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .custom-table td { padding: 16px 20px; font-size: 14px; color: #1e293b; border-bottom: 1px solid #f1f5f9; font-weight: 500; vertical-align: middle; }
        .custom-table tr:hover { background: #f8fafc; }

        /* Food Image Style */
        .food-img { width: 52px; height: 52px; border-radius: 12px; object-fit: cover; border: 1px solid #e2e8f0; background: #f1f5f9; }
        .no-img { width: 52px; height: 52px; border-radius: 12px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 20px; border: 1px solid #e2e8f0; }

        /* Badges & Buttons */
        .category-badge { background: #eff6ff; color: #2563eb; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; }
        
        .btn-add { background: #4f46e5; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 13.5px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-add:hover { background: #4338ca; }

        .btn-edit { background: #fef3c7; color: #d97706; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; margin-right: 6px; }
        .btn-edit:hover { background: #fde68a; }

        .btn-delete { background: #fee2e2; color: #dc2626; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
        .btn-delete:hover { background: #fca5a5; }
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
                <li class="nav-item"><a href="add_food.php"><i class="fa-solid fa-plus-circle"></i> Add Food</a></li>
                <li class="nav-item active"><a href="manage_food.php"><i class="fa-solid fa-bowl-food"></i> Manage Food</a></li>
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

    <!-- Main Content Panel -->
    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-bowl-food" style="color:#4f46e5;"></i> Manage Food Items</h1>
                <p>View, edit, or delete items from the cafeteria menu list.</p>
            </div>
            <a href="add_food.php" class="btn-add"><i class="fa-solid fa-plus-circle"></i> Add New Item</a>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <!-- Professional Table Card -->
        <div class="card-table">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Image</th>
                        <th>Food Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th style="width: 180px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($foods_result && mysqli_num_rows($foods_result) > 0): ?>
                        <?php while ($food = mysqli_fetch_assoc($foods_result)): ?>
                            <tr>
                                <td>
                                    <?php 
                                        $image_path = "../uploads/" . $food['image'];
                                        if (!empty($food['image']) && file_exists($image_path)): 
                                    ?>
                                        <img src="<?= $image_path; ?>" class="food-img" alt="Food Image">
                                    <?php else: ?>
                                        <div class="no-img"><i class="fa-solid fa-utensils"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><strong style="color:#0f172a; font-size:15px;"><?= htmlspecialchars($food['food_name']); ?></strong></td>
                                <td><span class="category-badge"><?= htmlspecialchars($food['category'] ?? 'General'); ?></span></td>
                                <td style="font-weight: 800; color: #059669;">৳<?= number_format($food['price'], 2); ?></td>
                                <td style="text-align: center;">
                                    <a href="edit_food.php?id=<?= $food['food_id']; ?>" class="btn-edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                    <a href="manage_food.php?delete_id=<?= $food['food_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this food item?');"><i class="fa-solid fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8; padding: 30px;">No food items available in the menu.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>