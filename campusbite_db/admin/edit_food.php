<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$food_id = intval($_GET['id'] ?? 0);
$success_msg = "";
$error_msg = "";
$success_flag = false;

$result = mysqli_query($conn, "SELECT * FROM foods WHERE food_id = '$food_id'");
if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: manage_food.php");
    exit();
}
$food = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_food'])) {
    $food_name = mysqli_real_escape_string($conn, trim($_POST['food_name'] ?? ''));
    $category_id = intval($_POST['category_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $status = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Available'));

    $category_name = '';
    if ($category_id > 0) {
        $cat_res = mysqli_query($conn, "SELECT category_name FROM categories WHERE category_id = '$category_id'");
        if ($cat_res && $row_cat = mysqli_fetch_assoc($cat_res)) {
            $category_name = $row_cat['category_name'];
        }
    }

    $image_name = $food['image'];
    if (isset($_FILES['image']['name']) && $_FILES['image']['name'] != "") {
        $image_name = time() . '_' . $_FILES['image']['name'];
        $target = "../uploads/" . $image_name;
        
        if (!file_exists('../uploads/')) {
            mkdir('../uploads/', 0777, true);
        }
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            if (!empty($food['image']) && file_exists("../uploads/" . $food['image'])) {
                @unlink("../uploads/" . $food['image']);
            }
        }
    }

    if (empty($food_name) || empty($price) || $category_id <= 0) {
        $error_msg = "Please fill in all required fields.";
    } else {
        $query = "UPDATE foods SET food_name='$food_name', category_id='$category_id', category='$category_name', price='$price', description='$description', status='$status', image='$image_name' WHERE food_id='$food_id'";
        
        if (mysqli_query($conn, $query)) {
            $success_flag = true;
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
    <title>Edit Food Item | CampusBite</title>
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

        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #dc2626; padding: 16px 20px; border-radius: 16px; margin-bottom: 25px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 12px; }

        .form-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start; max-width: 1150px; }

        .form-card, .preview-card { 
            background: rgba(255, 255, 255, 0.9); 
            backdrop-filter: blur(20px);
            border-radius: 28px; 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            padding: 38px; 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04); 
        }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .form-group { margin-bottom: 6px; position: relative; }
        .form-group.full-width { grid-column: span 2; }
        .form-label { display: block; font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .form-control { width: 100%; padding: 15px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 14px; color: #0f172a; outline: none; transition: all 0.3s ease; }
        .form-control:focus { border-color: #a855f7; background: #fff; box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.12); }
        textarea.form-control { resize: vertical; min-height: 90px; }

        .custom-select-wrapper { position: relative; width: 100%; cursor: pointer; }
        .custom-select-trigger { width: 100%; padding: 15px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 14px; color: #0f172a; display: flex; justify-content: space-between; align-items: center; }
        .custom-options { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); display: none; z-index: 50; max-height: 220px; overflow-y: auto; }
        .custom-option { padding: 12px 20px; font-size: 14px; color: #0f172a; transition: background 0.2s; }
        .custom-option:hover { background: #f3e8ff; color: #7c3aed; }

        .file-upload-box { border: 2px dashed #cbd5e1; background: #f8fafc; border-radius: 16px; padding: 18px; text-align: center; cursor: pointer; position: relative; }
        .file-upload-box input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }

        .preview-title { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .preview-box { width: 100%; height: 210px; border-radius: 20px; background: #f8fafc; border: 2px dashed #cbd5e1; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; position: relative; margin-bottom: 20px; }
        .preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .preview-placeholder { color: #94a3b8; font-size: 13px; display: flex; flex-direction: column; gap: 8px; align-items: center; }

        .btn-submit { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border: none; padding: 16px 32px; border-radius: 16px; font-weight: 700; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.35); transition: all 0.3s ease; }
        .btn-submit:hover { transform: translateY(-3px); }
        .btn-cancel { background: #f1f5f9; color: #64748b; text-decoration: none; padding: 16px 28px; border-radius: 16px; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; border: 1px solid #e2e8f0; }
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
                <li class="nav-item"><a href="add_food.php"><i class="fa-solid fa-circle-plus"></i> Add Food</a></li>
                <li class="nav-item active"><a href="manage_food.php"><i class="fa-solid fa-bowl-food"></i> Manage Food</a></li>
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
                <h1><i class="fa-solid fa-pen-to-square" style="color:#7c3aed;"></i> Edit Food Item</h1>
                <p>EWU Cafeteria • Update menu details</p>
            </div>
            <a href="manage_food.php" class="btn-cancel" style="margin:0;"><i class="fa-solid fa-arrow-left" style="margin-right:8px;"></i> Back to List</a>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="form-layout">
            <div class="form-card">
                <form method="POST" action="edit_food.php?id=<?= $food_id; ?>" enctype="multipart/form-data">
                    <div class="form-grid">
                        
                        <div class="form-group full-width">
                            <label class="form-label">Food Name *</label>
                            <input type="text" name="food_name" class="form-control" value="<?= htmlspecialchars($food['food_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <input type="hidden" name="category_id" id="categoryIdInput" value="<?= $food['category_id']; ?>" required>
                            <div class="custom-select-wrapper" id="customSelect">
                                <div class="custom-select-trigger">
                                    <span id="selectedCategoryText">
                                        <?php 
                                            $curr_cat_res = mysqli_query($conn, "SELECT category_name FROM categories WHERE category_id = '".$food['category_id']."'");
                                            $curr_cat = mysqli_fetch_assoc($curr_cat_res);
                                            echo htmlspecialchars($curr_cat['category_name'] ?? 'Select Category');
                                        ?>
                                    </span>
                                    <i class="fa-solid fa-chevron-down" style="font-size: 12px; color: #7c3aed;"></i>
                                </div>
                                <div class="custom-options" id="customOptions">
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
                            <input type="number" step="0.01" name="price" class="form-control" value="<?= $food['price']; ?>" required>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control"><?= htmlspecialchars($food['description']); ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" name="status" value="Available" <?= ($food['status'] == 'Available') ? 'checked' : ''; ?> style="width:16px; height:16px; accent-color:#7c3aed;">
                                Available in menu for ordering
                            </label>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Update Food Image</label>
                            <div class="file-upload-box" id="fileUploadBox">
                                <i class="fa-solid fa-cloud-arrow-up" style="color:#7c3aed; font-size:18px; margin-bottom:4px;"></i>
                                <span id="fileNameText" style="display:block; font-size:12px; color:#64748b; font-weight:600;">Choose new file or drag here</span>
                                <input type="file" id="imageInput" name="image" accept="image/*">
                            </div>
                        </div>

                    </div>

                    <div style="margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 25px; display: flex; gap: 12px;">
                        <button type="submit" name="update_food" class="btn-submit">
                            <i class="fa-solid fa-floppy-disk"></i> Update Food Item
                        </button>
                        <a href="manage_food.php" class="btn-cancel">Cancel</a>
                    </div>
                </form>
            </div>

            <div class="preview-card">
                <div class="preview-title">Current Image</div>
                <div class="preview-box" id="previewBox">
                    <?php if (!empty($food['image']) && file_exists("../uploads/" . $food['image'])): ?>
                        <img id="currentImageDisplay" src="../uploads/<?= htmlspecialchars($food['image']); ?>" alt="Food Image">
                    <?php else: ?>
                        <div class="preview-placeholder" id="previewPlaceholder">
                            <i class="fa-solid fa-utensils" style="font-size: 28px; color: #a855f7;"></i>
                            <span>No image uploaded</span>
                        </div>
                        <img id="currentImageDisplay" src="" alt="Food Image" style="display:none; width:100%; height:100%; object-fit:cover;">
                    <?php endif; ?>
                </div>
                <p style="font-size: 12px; color: #64748b; line-height: 1.5;">Modifying this will instantly update the item on the live menu list without altering past user orders.</p>
            </div>
        </div>
    </div>

    <script>
        <?php if ($success_flag): ?>
        Swal.fire({
            icon: 'success',
            title: 'Updated Successfully!',
            text: 'Food item details have been updated.',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = 'manage_food.php';
        });
        <?php endif; ?>

        document.addEventListener("DOMContentLoaded", () => {
            gsap.to("#mainContent", { opacity: 1, duration: 0.9, y: 0, ease: "power3.out", startAt: { y: 25 } });
        });

        const customSelect = document.getElementById('customSelect');
        const customSelectTrigger = customSelect.querySelector('.custom-select-trigger');
        const customOptions = document.getElementById('customOptions');
        const selectedCategoryText = document.getElementById('selectedCategoryText');
        const categoryIdInput = document.getElementById('categoryIdInput');

        customSelectTrigger.addEventListener('click', () => {
            const isOpen = customSelect.classList.toggle('open');
            customOptions.style.display = isOpen ? 'block' : 'none';
        });

        document.querySelectorAll('#customOptions .custom-option').forEach(option => {
            option.addEventListener('click', () => {
                selectedCategoryText.textContent = option.textContent;
                categoryIdInput.value = option.getAttribute('data-value');
                customSelect.classList.remove('open');
                customOptions.style.display = 'none';
            });
        });

        const imageInput = document.getElementById('imageInput');
        const currentImageDisplay = document.getElementById('currentImageDisplay');
        const previewPlaceholder = document.getElementById('previewPlaceholder');
        const fileNameText = document.getElementById('fileNameText');

        imageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                fileNameText.textContent = file.name;
                const reader = new FileReader();
                reader.onload = function(event) {
                    currentImageDisplay.src = event.target.result;
                    currentImageDisplay.style.display = 'block';
                    if(previewPlaceholder) previewPlaceholder.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>