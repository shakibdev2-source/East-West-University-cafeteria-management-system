<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$success_msg = "";
$error_msg = "";
$success_flag = false; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_food'])) {
    $food_name = trim($_POST['food_name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Available');

    if (!in_array($status, ['Available', 'Out of Stock'])) {
        $status = 'Available';
    }

    $category_name = '';
    if ($category_id > 0) {
        $stmt_cat = mysqli_prepare($conn, "SELECT category_name FROM categories WHERE category_id = ?");
        mysqli_stmt_bind_param($stmt_cat, "i", $category_id);
        mysqli_stmt_execute($stmt_cat);
        $cat_res = mysqli_stmt_get_result($stmt_cat);
        if ($cat_res && $row_cat = mysqli_fetch_assoc($cat_res)) {
            $category_name = $row_cat['category_name'];
        }
        mysqli_stmt_close($stmt_cat);
    }

    $image_name = "";
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = $_FILES['image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        $check = getimagesize($file_tmp);
        if ($check !== false && in_array($file_ext, $allowed_extensions)) {
            $image_name = time() . '_' . uniqid() . '.' . $file_ext;
            $target = "../uploads/" . $image_name;
            
            if (!file_exists('../uploads/')) {
                mkdir('../uploads/', 0777, true);
            }
            move_uploaded_file($file_tmp, $target);
        } else {
            $error_msg = "Invalid image format. Please upload a valid JPG, PNG, or WEBP image.";
        }
    }

    if (empty($error_msg)) {
        if (empty($food_name) || empty($price) || $category_id <= 0) {
            $error_msg = "Please fill in all required fields (Food Name, Category & Price).";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO foods (food_name, category_id, category, price, description, status, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sisdsss", $food_name, $category_id, $category_name, $price, $description, $status, $image_name);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "New food item added successfully!";
                $success_flag = true; 
            } else {
                $error_msg = "Database Error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Food | EWU Cafeteria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

        .sidebar { 
            width: 280px; 
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(226, 232, 240, 0.8);
            padding: 30px 20px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            position: fixed; 
            height: 100vh; 
            z-index: 100;
        }
        
        .brand { font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 14px; margin-bottom: 40px; padding-left: 6px; }
        .brand-icon { background: linear-gradient(135deg, #6366f1, #a855f7); width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.25); }
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .nav-item a { display: flex; align-items: center; gap: 14px; padding: 14px 18px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 14px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item.active a { background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1)); color: #7c3aed; border: 1px solid rgba(168, 85, 247, 0.2); font-weight: 700; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.08); }
        .nav-item.active a i { color: #7c3aed; }
        .nav-item a:hover { background: #f1f5f9; color: #0f172a; transform: translateX(6px); }

        .main-content { flex: 1; margin-left: 280px; padding: 40px 50px; opacity: 0; background: radial-gradient(circle at top right, #ede9fe 0%, #f8fafc 60%); min-height: 100vh; }

        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 35px; 
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(16px);
            padding: 24px 32px; 
            border-radius: 24px; 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03); 
        }
        .page-title h1 { font-size: 24px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }
        .page-title p { font-size: 12px; color: #7c3aed; font-weight: 700; margin-top: 6px; letter-spacing: 1.5px; text-transform: uppercase; }

        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #dc2626; padding: 16px 20px; border-radius: 16px; margin-bottom: 25px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 12px; backdrop-filter: blur(10px); }

        .form-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start; max-width: 1150px; }

        .form-card, .preview-card { 
            background: rgba(255, 255, 255, 0.9); 
            backdrop-filter: blur(20px);
            border-radius: 28px; 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            padding: 38px; 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04); 
            transition: box-shadow 0.4s ease;
        }
        .form-card:hover, .preview-card:hover { box-shadow: 0 30px 60px rgba(124, 58, 237, 0.1); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .form-group { margin-bottom: 6px; position: relative; }
        .form-group.full-width { grid-column: span 2; }
        .form-label { display: block; font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .form-control { width: 100%; padding: 15px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 14px; color: #0f172a; outline: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .form-control:focus { border-color: #a855f7; background: #fff; box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.12); }
        textarea.form-control { resize: vertical; min-height: 90px; }

        .custom-select-wrapper { position: relative; width: 100%; cursor: pointer; }
        .custom-select-trigger { width: 100%; padding: 15px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 14px; color: #64748b; display: flex; justify-content: space-between; align-items: center; transition: all 0.3s ease; }
        .custom-select-wrapper.open .custom-select-trigger { border-color: #a855f7; background: #fff; box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.12); color: #0f172a; }
        .custom-options { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; display: none; z-index: 50; max-height: 220px; overflow-y: auto; }
        .custom-option { padding: 12px 20px; font-size: 14px; color: #0f172a; transition: background 0.2s; }
        .custom-option:hover { background: #f3e8ff; color: #7c3aed; }

        .file-upload-box { border: 2px dashed #cbd5e1; background: #f8fafc; border-radius: 16px; padding: 18px; text-align: center; cursor: pointer; position: relative; transition: all 0.3s ease; }
        .file-upload-box.dragover { border-color: #7c3aed; background: #f3e8ff; transform: scale(1.02); }
        .file-upload-box input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }

        .preview-card { text-align: center; transform-style: preserve-3d; perspective: 1000px; }
        .preview-title { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; text-align: left; }
        .preview-box { width: 100%; height: 190px; border-radius: 20px; background: #f8fafc; border: 2px dashed #cbd5e1; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; position: relative; margin-bottom: 20px; }
        .preview-box img { width: 100%; height: 100%; object-fit: cover; display: none; }
        .preview-placeholder { color: #94a3b8; font-size: 13px; display: flex; flex-direction: column; gap: 8px; align-items: center; }
        .preview-placeholder i { font-size: 28px; color: #a855f7; }

        .live-info { text-align: left; background: #f8fafc; padding: 16px; border-radius: 16px; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
        .live-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .live-category-badge { display: inline-block; background: #ede9fe; color: #7c3aed; font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        .live-status-badge { font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #059669; }
        .live-name { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .live-desc { font-size: 12px; color: #64748b; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .live-price { font-size: 18px; font-weight: 800; color: #7c3aed; }

        .btn-submit { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border: none; padding: 16px 32px; border-radius: 16px; font-weight: 700; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.35); transition: all 0.3s ease; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(168, 85, 247, 0.45); }
        .btn-cancel { background: #f1f5f9; color: #64748b; text-decoration: none; padding: 16px 28px; border-radius: 16px; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; margin-left: 12px; border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .btn-cancel:hover { background: #e2e8f0; color: #0f172a; transform: translateY(-2px); }
        .btn-reset { background: #fff; color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 16px 20px; border-radius: 16px; font-weight: 700; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; margin-left: 12px; transition: all 0.3s ease; }
        .btn-reset:hover { background: rgba(239, 68, 68, 0.05); transform: translateY(-2px); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                EWU Cafeteria
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item active"><a href="add_food.php"><i class="fa-solid fa-circle-plus"></i> Add Food</a></li>
                <li class="nav-item"><a href="manage_food.php"><i class="fa-solid fa-bowl-food"></i> Manage Food</a></li>
                <li class="nav-item"><a href="manage_orders.php"><i class="fa-solid fa-receipt"></i> Manage Orders</a></li>
                <li class="nav-item"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
                <li class="nav-item"><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
            </ul>
        </div>
        <div class="nav-menu">
            <li class="nav-item"><a href="../logout.php" style="color:#dc2626;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="top-header">
            <div class="page-title">
                <h1><i class="fa-solid fa-utensils" style="color:#7c3aed;"></i> Add New Food Item</h1>
                <p>EWU Cafeteria</p>
            </div>
            <a href="manage_food.php" class="btn-cancel" style="margin:0;"><i class="fa-solid fa-arrow-left" style="margin-right:8px;"></i> Back to List</a>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation" style="font-size:18px;"></i> <?= htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="form-layout">
            <div class="form-card">
                <form method="POST" action="add_food.php" enctype="multipart/form-data" id="foodForm">
                    <div class="form-grid">
                        
                        <div class="form-group">
                            <label class="form-label">Food Name *</label>
                            <input type="text" id="foodNameInput" name="food_name" class="form-control" placeholder="e.g. Chicken Burger" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <input type="hidden" name="category_id" id="categoryIdInput" required>
                            <div class="custom-select-wrapper" id="customSelect">
                                <div class="custom-select-trigger">
                                    <span id="selectedCategoryText">Select Category</span>
                                    <i class="fa-solid fa-chevron-down" style="font-size: 12px; color: #7c3aed;"></i>
                                </div>
                                <div class="custom-options" id="customOptions">
                                    <div class="custom-option" data-value="">Select Category</div>
                                    <?php
                                    $cat_query = mysqli_query($conn, "SELECT * FROM categories");
                                    if ($cat_query && mysqli_num_rows($cat_query) > 0) {
                                        while ($cat = mysqli_fetch_assoc($cat_query)) {
                                            echo '<div class="custom-option" data-value="' . $cat['category_id'] . '">' . htmlspecialchars($cat['category_name']) . '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Price (৳) *</label>
                            <input type="number" id="foodPriceInput" step="0.01" name="price" class="form-control" placeholder="e.g. 120.00" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Availability Status</label>
                            <input type="hidden" name="status" id="statusInput" value="Available">
                            <div class="custom-select-wrapper" id="statusSelect">
                                <div class="custom-select-trigger">
                                    <span id="selectedStatusText" style="color:#0f172a;">Available</span>
                                    <i class="fa-solid fa-chevron-down" style="font-size: 12px; color: #7c3aed;"></i>
                                </div>
                                <div class="custom-options" id="statusOptions">
                                    <div class="custom-option" data-value="Available">Available</div>
                                    <div class="custom-option" data-value="Out of Stock">Out of Stock</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Description</label>
                            <textarea id="foodDescInput" name="description" class="form-control" placeholder="Write a short description about this food item..."></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Food Image</label>
                            <div class="file-upload-box" id="fileUploadBox">
                                <i class="fa-solid fa-cloud-arrow-up" style="color:#7c3aed; font-size:20px; margin-bottom:4px;"></i>
                                <span id="fileNameText" style="display:block; font-size:12px; color:#64748b; font-weight:600;">Browse or Drag Image</span>
                                <input type="file" id="imageInput" name="image" accept="image/*">
                            </div>
                        </div>

                    </div>

                    <div style="margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 25px; display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <button type="submit" name="add_food" class="btn-submit" id="submitBtn">
                            <i class="fa-solid fa-circle-plus"></i> Save & Publish Food
                        </button>
                        <button type="reset" class="btn-reset" id="resetBtn">
                            <i class="fa-solid fa-rotate-right"></i> Reset
                        </button>
                        <a href="manage_food.php" class="btn-cancel" style="margin:0;">Cancel</a>
                    </div>
                </form>
            </div>

            <div class="preview-card" id="previewCard">
                <div class="preview-title">Live Preview</div>
                <div class="preview-box">
                    <div class="preview-placeholder" id="previewPlaceholder">
                        <i class="fa-solid fa-image"></i>
                        <span>No image selected</span>
                    </div>
                    <img id="imagePreview" src="" alt="Preview">
                </div>
                <div class="live-info">
                    <div class="live-header-row">
                        <div class="live-category-badge" id="liveCategoryBadge">Category</div>
                        <div class="live-status-badge" id="liveStatusBadge">Available</div>
                    </div>
                    <div class="live-name" id="liveNameDisplay">Food Name</div>
                    <div class="live-desc" id="liveDescDisplay">Description will appear here...</div>
                    <div class="live-price" id="livePriceDisplay">৳0.00</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        <?php if ($success_flag): ?>
        Swal.fire({
            icon: 'success',
            title: 'Successfully Added!',
            text: 'New food item has been added to the menu.',
            timer: 2000,
            showConfirmButton: false,
            customClass: {
                popup: 'rounded-2xl shadow-xl border border-slate-100'
            }
        }).then(() => {
            window.location.href = 'manage_food.php';
        });
        <?php endif; ?>

        document.addEventListener("DOMContentLoaded", () => {
            gsap.to("#mainContent", { opacity: 1, duration: 0.9, y: 0, ease: "power3.out", startAt: { y: 25 } });
            gsap.from(".nav-item", { opacity: 0, x: -25, duration: 0.6, stagger: 0.07, ease: "power2.out", delay: 0.2 });
            gsap.from(".form-card, .preview-card", { opacity: 0, y: 35, duration: 0.7, stagger: 0.15, ease: "power3.out", delay: 0.35 });
        });

        const previewCard = document.getElementById('previewCard');
        previewCard.addEventListener('mousemove', (e) => {
            const rect = previewCard.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            gsap.to(previewCard, { rotateY: x * 0.03, rotateX: -y * 0.03, ease: "power2.out", duration: 0.3, transformPerspective: 1000 });
        });
        previewCard.addEventListener('mouseleave', () => {
            gsap.to(previewCard, { rotateY: 0, rotateX: 0, ease: "power2.out", duration: 0.5 });
        });

        const customSelect = document.getElementById('customSelect');
        const customSelectTrigger = customSelect.querySelector('.custom-select-trigger');
        const customOptions = document.getElementById('customOptions');
        const selectedCategoryText = document.getElementById('selectedCategoryText');
        const categoryIdInput = document.getElementById('categoryIdInput');
        const liveCategoryBadge = document.getElementById('liveCategoryBadge');

        customSelectTrigger.addEventListener('click', () => {
            const isOpen = customSelect.classList.toggle('open');
            if (isOpen) {
                customOptions.style.display = 'block';
                gsap.fromTo(customOptions, { opacity: 0, y: -10 }, { opacity: 1, y: 0, duration: 0.3, ease: "power2.out" });
            } else {
                gsap.to(customOptions, { opacity: 0, y: -10, duration: 0.2, ease: "power2.in", onComplete: () => { customOptions.style.display = 'none'; } });
            }
        });

        document.querySelectorAll('#customOptions .custom-option').forEach(option => {
            option.addEventListener('click', () => {
                const value = option.getAttribute('data-value');
                const text = option.textContent;
                selectedCategoryText.textContent = text;
                selectedCategoryText.style.color = value ? '#0f172a' : '#64748b';
                categoryIdInput.value = value;
                
                liveCategoryBadge.textContent = value ? text : 'Category';
                gsap.fromTo(liveCategoryBadge, { scale: 0.9 }, { scale: 1, duration: 0.25, ease: "back.out(1.5)" });

                customSelect.classList.remove('open');
                gsap.to(customOptions, { opacity: 0, y: -10, duration: 0.2, ease: "power2.in", onComplete: () => { customOptions.style.display = 'none'; } });
            });
        });

        const statusSelect = document.getElementById('statusSelect');
        const statusSelectTrigger = statusSelect.querySelector('.custom-select-trigger');
        const statusOptions = document.getElementById('statusOptions');
        const selectedStatusText = document.getElementById('selectedStatusText');
        const statusInput = document.getElementById('statusInput');
        const liveStatusBadge = document.getElementById('liveStatusBadge');

        statusSelectTrigger.addEventListener('click', () => {
            const isOpen = statusSelect.classList.toggle('open');
            if (isOpen) {
                statusOptions.style.display = 'block';
                gsap.fromTo(statusOptions, { opacity: 0, y: -10 }, { opacity: 1, y: 0, duration: 0.3, ease: "power2.out" });
            } else {
                gsap.to(statusOptions, { opacity: 0, y: -10, duration: 0.2, ease: "power2.in", onComplete: () => { statusOptions.style.display = 'none'; } });
            }
        });

        document.querySelectorAll('#statusOptions .custom-option').forEach(option => {
            option.addEventListener('click', () => {
                const value = option.getAttribute('data-value');
                selectedStatusText.textContent = value;
                statusInput.value = value;
                liveStatusBadge.textContent = value;
                
                if(value === 'Available') {
                    liveStatusBadge.style.background = 'rgba(16, 185, 129, 0.15)';
                    liveStatusBadge.style.color = '#059669';
                } else {
                    liveStatusBadge.style.background = 'rgba(239, 68, 68, 0.15)';
                    liveStatusBadge.style.color = '#dc2626';
                }

                gsap.fromTo(liveStatusBadge, { scale: 0.9 }, { scale: 1, duration: 0.25, ease: "back.out(1.5)" });

                statusSelect.classList.remove('open');
                gsap.to(statusOptions, { opacity: 0, y: -10, duration: 0.2, ease: "power2.in", onComplete: () => { statusOptions.style.display = 'none'; } });
            });
        });

        window.addEventListener('click', (e) => {
            if (!customSelect.contains(e.target)) {
                customSelect.classList.remove('open');
                customOptions.style.display = 'none';
            }
            if (!statusSelect.contains(e.target)) {
                statusSelect.classList.remove('open');
                statusOptions.style.display = 'none';
            }
        });

        const fileUploadBox = document.getElementById('fileUploadBox');
        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadBox.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                fileUploadBox.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadBox.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                fileUploadBox.classList.remove('dragover');
            }, false);
        });

        fileUploadBox.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                if(!files[0].type.startsWith('image/')) {
                    alert('Please drop a valid image file.');
                    return;
                }
                imageInput.files = files;
                const event = new Event('change', { bubbles: true });
                imageInput.dispatchEvent(event);
            }
        });

        const foodNameInput = document.getElementById('foodNameInput');
        const foodPriceInput = document.getElementById('foodPriceInput');
        const foodDescInput = document.getElementById('foodDescInput');
        const imageInput = document.getElementById('imageInput');
        
        const liveNameDisplay = document.getElementById('liveNameDisplay');
        const livePriceDisplay = document.getElementById('livePriceDisplay');
        const liveDescDisplay = document.getElementById('liveDescDisplay');
        const imagePreview = document.getElementById('imagePreview');
        const previewPlaceholder = document.getElementById('previewPlaceholder');
        const fileNameText = document.getElementById('fileNameText');
        const submitBtn = document.getElementById('submitBtn');
        const resetBtn = document.getElementById('resetBtn');

        foodNameInput.addEventListener('input', (e) => {
            liveNameDisplay.textContent = e.target.value.trim() ? e.target.value : 'Food Name';
            gsap.fromTo(liveNameDisplay, { scale: 0.96 }, { scale: 1, duration: 0.2, ease: "power2.out" });
        });

        foodPriceInput.addEventListener('input', (e) => {
            let val = e.target.value;
            livePriceDisplay.textContent = val ? '৳' + parseFloat(val).toFixed(2) : '৳0.00';
            gsap.fromTo(livePriceDisplay, { scale: 0.95 }, { scale: 1, duration: 0.2, ease: "power2.out" });
        });

        foodDescInput.addEventListener('input', (e) => {
            liveDescDisplay.textContent = e.target.value.trim() ? e.target.value : 'Description will appear here...';
        });

        imageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                if(!file.type.startsWith('image/')) {
                    alert('Please select a valid image file.');
                    return;
                }
                fileNameText.textContent = file.name;
                const reader = new FileReader();
                reader.onload = function(event) {
                    imagePreview.src = event.target.result;
                    imagePreview.style.display = 'block';
                    previewPlaceholder.style.display = 'none';
                    gsap.fromTo("#imagePreview", { scale: 0.85, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.5, ease: "back.out(1.7)" });
                }
                reader.readAsDataURL(file);
            } else {
                fileNameText.textContent = 'Browse or Drag Image';
                imagePreview.src = '';
                imagePreview.style.display = 'none';
                previewPlaceholder.style.display = 'flex';
            }
        });

        resetBtn.addEventListener('click', () => {
            selectedCategoryText.textContent = 'Select Category';
            selectedCategoryText.style.color = '#64748b';
            categoryIdInput.value = '';
            liveCategoryBadge.textContent = 'Category';
            
            selectedStatusText.textContent = 'Available';
            statusInput.value = 'Available';
            liveStatusBadge.textContent = 'Available';
            liveStatusBadge.style.background = 'rgba(16, 185, 129, 0.15)';
            liveStatusBadge.style.color = '#059669';

            liveNameDisplay.textContent = 'Food Name';
            liveDescDisplay.textContent = 'Description will appear here...';
            livePriceDisplay.textContent = '৳0.00';
            
            fileNameText.textContent = 'Browse or Drag Image';
            imagePreview.src = '';
            imagePreview.style.display = 'none';
            previewPlaceholder.style.display = 'flex';
        });

        submitBtn.addEventListener('click', (e) => {
            if(foodNameInput.value && foodPriceInput.value && categoryIdInput.value) {
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            }
        });
    </script>
</body>
</html>