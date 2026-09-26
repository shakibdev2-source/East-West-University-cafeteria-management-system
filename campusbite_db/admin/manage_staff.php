<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $staff_name = trim($_POST['staff_name']);
    $email = trim($_POST['email']);
    $raw_password = trim($_POST['password']);
    $phone = trim($_POST['phone']);
    $position = trim($_POST['position']);
    $salary = floatval($_POST['salary']);
    $shift = trim($_POST['shift']);

    if (!empty($staff_name) && !empty($email) && !empty($raw_password) && !empty($phone) && !empty($position)) {
        mysqli_begin_transaction($conn);
        try {
            $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
            $role = 'staff';$dept = 'Staff';

            $stmt_user = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password, role, department) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_user, "sssss", $staff_name, $email,$hashed_password, $role,$dept);
            mysqli_stmt_execute($stmt_user);
            $user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_user);

            $is_active = 1;
            $stmt_staff = mysqli_prepare($conn, "INSERT INTO staffs (user_id, phone, position, salary, shift, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_staff, "issdsi", $user_id,$phone, $position,$salary, $shift,$is_active);
            mysqli_stmt_execute($stmt_staff);
            mysqli_stmt_close($stmt_staff);

            $status_str = 'active';
            $stmt_staff2 = mysqli_prepare($conn, "INSERT INTO staff2 (full_name, email, password, phone, status) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_staff2, "sssss", $staff_name, $email,$hashed_password, $phone,$status_str);
            mysqli_stmt_execute($stmt_staff2);
            mysqli_stmt_close($stmt_staff2);

            mysqli_commit($conn);
            header("Location: manage_staff.php?status=added");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            header("Location: manage_staff.php?status=error");
            exit();
        }
    }
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    mysqli_begin_transaction($conn);
    try {
        $stmt_get = mysqli_prepare($conn, "SELECT user_id, phone FROM staffs WHERE staff_id = ?");
        mysqli_stmt_bind_param($stmt_get, "i", $delete_id);
        mysqli_stmt_execute($stmt_get);
        $result = mysqli_stmt_get_result($stmt_get);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $user_id =$row['user_id'];
            $phone =$row['phone'];
            
            $stmt_del_staff = mysqli_prepare($conn, "DELETE FROM staffs WHERE staff_id = ?");
            mysqli_stmt_bind_param($stmt_del_staff, "i", $delete_id);
            mysqli_stmt_execute($stmt_del_staff);
            mysqli_stmt_close($stmt_del_staff);

            $stmt_del_user = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt_del_user, "i", $user_id);
            mysqli_stmt_execute($stmt_del_user);
            mysqli_stmt_close($stmt_del_user);

            $stmt_del_staff2 = mysqli_prepare($conn, "DELETE FROM staff2 WHERE phone = ?");
            mysqli_stmt_bind_param($stmt_del_staff2, "s", $phone);
            mysqli_stmt_execute($stmt_del_staff2);
            mysqli_stmt_close($stmt_del_staff2);
        }
        mysqli_commit($conn);
        header("Location: manage_staff.php?status=deleted");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: manage_staff.php?status=error");
        exit();
    }
}

$search_query = trim($_GET['search'] ?? '');$query = "SELECT s.*, u.full_name, u.email FROM staffs s LEFT JOIN users u ON s.user_id = u.id WHERE 1=1";
if (!empty($search_query)) {$search_esc = mysqli_real_escape_string($conn,$search_query);
    $query .= " AND (s.position LIKE '\%$search_esc%' OR s.phone LIKE '%$search_esc\%' OR s.shift LIKE '\%$search_esc%' OR u.full_name LIKE '%$search_esc\%' OR u.email LIKE '\%$search_esc%')";
}
$query .= " ORDER BY s.staff_id DESC";
$staff_query = mysqli_query($conn,$query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff | CampusBite</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

        .sidebar { 
            width: 280px; 
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
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
        
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 14px; padding: 13px 18px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 14px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item.active a { background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1)); color: #7c3aed; border: 1px solid rgba(168, 85, 247, 0.2); font-weight: 700; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.08); }
        .nav-item.active a i { color: #7c3aed; }
        .nav-item a:hover { background: #f1f5f9; color: #0f172a; transform: translateX(6px); }

        .main-content { flex: 1; margin-left: 280px; padding: 40px 50px; opacity: 0; background: radial-gradient(circle at top right, rgba(168, 85, 247, 0.08) 0%, rgba(248, 250, 252, 1) 60%); min-height: 100vh; }

        .top-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            padding: 22px 32px; 
            border-radius: 24px; 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02); 
        }
        .page-title h1 { font-size: 22px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }

        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            padding: 28px 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.03);
            margin-bottom: 25px;
        }

        .form-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px; }
        .form-grid-bottom { display: grid; grid-template-columns: repeat(4, 1fr) auto; gap: 16px; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; }
        .form-control { padding: 13px 18px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; font-size: 13.5px; color: #0f172a; outline: none; transition: all 0.3s ease; }
        .form-control:focus { border-color: #a855f7; background: #fff; box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1); }

        .btn-submit { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border: none; padding: 0 26px; border-radius: 14px; font-weight: 700; font-size: 13.5px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.25); height: 48px; display: flex; align-items: center; gap: 8px; justify-content: center; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 14px 24px rgba(99, 102, 241, 0.35); }

        .toolbar { display: flex; justify-content: flex-end; margin-bottom: 20px; }
        .search-box { display: flex; align-items: center; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 6px 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); transition: all 0.3s ease; }
        .search-box:focus-within { border-color: #a855f7; box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1); }
        .search-box input { border: none; outline: none; padding: 6px; font-size: 14px; width: 240px; color: #0f172a; background: transparent; }
        .search-box button { background: none; border: none; color: #7c3aed; cursor: pointer; font-size: 14px; padding-left: 6px; }

        .table-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            padding: 24px 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; padding: 16px 14px; border-bottom: 2px solid #f1f5f9; }
        td { padding: 18px 14px; font-size: 14px; color: #1e293b; border-bottom: 1px solid #f8fafc; font-weight: 500; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(248, 250, 252, 0.6); }
        .no-data { text-align: center; padding: 50px; color: #94a3b8; font-weight: 600; font-size: 14px; }

        .badge-shift { background: rgba(99, 102, 241, 0.1); color: #6366f1; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge-salary { background: rgba(16, 185, 129, 0.1); color: #059669; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; display: inline-block; }

        .btn-delete { background: rgba(239, 68, 68, 0.08); color: #dc2626; border: none; padding: 8px 16px; border-radius: 10px; font-size: 12.5px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px; }
        .btn-delete:hover { background: #dc2626; color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-utensils"></i></div>
                EWU cafeteria
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
                <li class="nav-item"><a href="add_food.php"><i class="fa-solid fa-circle-plus"></i> Add Food</a></li>
                <li class="nav-item"><a href="manage_food.php"><i class="fa-solid fa-bowl-food"></i> Manage Food</a></li>
                <li class="nav-item"><a href="manage_orders.php"><i class="fa-solid fa-receipt"></i> Manage Orders</a></li>
                <li class="nav-item active"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item"><a href="staff_attendance.php"><i class="fa-solid fa-clipboard-user"></i> Staff Attendance</a></li>
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
                <h1><i class="fa-solid fa-users-gear" style="color:#7c3aed;"></i> Manage Staff Members</h1>
            </div>
        </div>

        <div class="form-card">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Staff Name *</label>
                        <input type="text" name="staff_name" class="form-control" placeholder="Enter Staff Name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" placeholder="staff@ewu.edu" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="Set Password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" class="form-control" placeholder="Phone Number" required>
                    </div>
                </div>
                <div class="form-grid-bottom">
                    <div class="form-group">
                        <label class="form-label">Position *</label>
                        <input type="text" name="position" class="form-control" placeholder="e.g. Chef, Cashier" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Salary *</label>
                        <input type="number" step="0.01" name="salary" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Shift *</label>
                        <input type="text" name="shift" class="form-control" placeholder="e.g. Morning" required>
                    </div>
                    <div></div>
                    <div>
                        <button type="submit" name="add_staff" class="btn-submit"><i class="fa-solid fa-user-plus"></i> Add Staff</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="toolbar">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Search name, position..." value="<?= htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fa-solid fa-search"></i></button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Staff Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Position</th>
                        <th>Salary</th>
                        <th>Shift</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($staff_query && mysqli_num_rows($staff_query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($staff_query)): ?>
                            <tr>
                                <td style="font-weight: 700; color: #7c3aed;">#<?= $row['staff_id']; ?></td>
                                <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['full_name'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($row['phone']); ?></td>
                                <td><span style="font-weight: 600; color: #475569;"><?= htmlspecialchars($row['position']); ?></span></td>
                                <td><span class="badge-salary">৳<?= number_format($row['salary'], 2); ?></span></td>
                                <td><span class="badge-shift"><?= htmlspecialchars($row['shift']); ?></span></td>
                                <td>
                                    <button onclick="confirmDelete(<?= $row['staff_id']; ?>)" class="btn-delete"><i class="fa-solid fa-trash"></i> Delete</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-data"><i class="fa-regular fa-folder-open" style="font-size: 24px; display: block; margin-bottom: 8px;"></i> No staff members found. Add your first staff member using the form above.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            gsap.to("#mainContent", { opacity: 1, duration: 0.8, y: 0, ease: "power3.out", startAt: { y: 20 } });

            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            
            if (status === 'added') {
                Swal.fire({
                    icon: 'success',
                    title: 'Staff Added!',
                    text: 'New staff member has been added successfully.',
                    timer: 2000,
                    showConfirmButton: false,
                    background: '#fff',
                    iconColor: '#10b981'
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            } else if (status === 'deleted') {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted!',
                    text: 'Staff member has been removed successfully.',
                    timer: 2000,
                    showConfirmButton: false,
                    background: '#fff',
                    iconColor: '#ef4444'
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            } else if (status === 'error') {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Something went wrong. Please try again.',
                    background: '#fff'
                }).then(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                });
            }
        });

        function confirmDelete(staffId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this staff account!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `manage_staff.php?delete_id=${staffId}`;
                }
            })
        }
    </script>
</body>
</html>