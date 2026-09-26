<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE);

$conn = mysqli_connect("localhost", "root", "", "campusbite_db");

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

$check_col = mysqli_query($conn, "SHOW COLUMNS FROM `staff_attendance` LIKE 'notes'");
if ($check_col && mysqli_num_rows($check_col) == 0) {
    @mysqli_query($conn, "ALTER TABLE `staff_attendance` ADD COLUMN `notes` TEXT NULL");
}

$msg = "";
$error = "";
$today_date = date('Y-m-d');
$current_user_id = $_SESSION['user_id'] ?? 1;

if (isset($_POST['bulk_action'])) {
    $action_type = $_POST['bulk_action_type'];
    $staff_query_all = mysqli_query($conn, "SELECT id FROM users WHERE role != 'student'");
    $count_affected = 0;
    
    while ($st_row = mysqli_fetch_assoc($staff_query_all)) {
        $s_id = $st_row['id'];
        $chk = mysqli_query($conn, "SELECT attendance_id FROM staff_attendance WHERE staff_id = $s_id AND date = '$today_date'");
        
        if ($action_type == 'present' && mysqli_num_rows($chk) == 0) {
            @mysqli_query($conn, "INSERT INTO staff_attendance (staff_id, date, clock_in, status, total_hours, overtime, notes) VALUES ($s_id, '$today_date', '09:00:00', 'Present', 8.00, 0.00, 'Auto-marked Present')");
            $count_affected++;
        } elseif ($action_type == 'absent' && mysqli_num_rows($chk) == 0) {
            @mysqli_query($conn, "INSERT INTO staff_attendance (staff_id, date, status, total_hours, overtime, notes) VALUES ($s_id, '$today_date', 'Absent', 0.00, 0.00, 'Auto-marked Absent')");
            $count_affected++;
        }
    }
    
    if ($action_type == 'reset') {
        mysqli_query($conn, "DELETE FROM staff_attendance WHERE date = '$today_date'");
        $msg = "Today's attendance logs successfully reset!";
    } else {
        $msg = "Bulk operation executed successfully! $count_affected records processed.";
    }
}

if (isset($_GET['export']) && $_GET['export'] == 'true') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=advanced_staff_attendance_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Staff Name', 'Position', 'Date', 'Clock In', 'Clock Out', 'Total Hours', 'Overtime', 'Status', 'Notes'));
    
    $exp_query = mysqli_query($conn, "SELECT a.*, u.full_name as staff_name, u.role as position FROM staff_attendance a JOIN users u ON a.staff_id = u.id ORDER BY a.date DESC");
    while ($erow = mysqli_fetch_assoc($exp_query)) {
        fputcsv($output, array($erow['attendance_id'], $erow['staff_name'], $erow['position'], $erow['date'], $erow['clock_in'], $erow['clock_out'], $erow['total_hours'], $erow['overtime'], $erow['status'], $erow['notes'] ?? ''));
    }
    fclose($output);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_attendance'])) {
    $staff_id = (int)$_POST['staff_id'];
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $clock_in = !empty($_POST['clock_in']) ? mysqli_real_escape_string($conn, $_POST['clock_in']) : null;
    $clock_out = !empty($_POST['clock_out']) ? mysqli_real_escape_string($conn, $_POST['clock_out']) : null;
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $total_hours = 0.00;
    $overtime = 0.00;
    
    if ($clock_in && $clock_out) {
        $in_time = strtotime($clock_in);
        $out_time = strtotime($clock_out);
        if ($out_time > $in_time) {
            $diff_seconds = $out_time - $in_time;
            $total_hours = round($diff_seconds / 3600, 2);
            if ($total_hours > 8.00) {
                $overtime = round($total_hours - 8.00, 2);
            }
        }
    }

    if ($staff_id > 0 && !empty($date)) {
        $check_existing = mysqli_query($conn, "SELECT attendance_id FROM staff_attendance WHERE staff_id = $staff_id AND date = '$date'");

        if ($check_existing && mysqli_num_rows($check_existing) > 0) {
            $row_ex = mysqli_fetch_assoc($check_existing);
            $att_id = $row_ex['attendance_id'];
            $update_sql = "UPDATE staff_attendance SET clock_in = " . ($clock_in ? "'$clock_in'" : "NULL") . ", clock_out = " . ($clock_out ? "'$clock_out'" : "NULL") . ", status = '$status', total_hours = $total_hours, overtime = $overtime, notes = '$notes' WHERE attendance_id = $att_id";
            @mysqli_query($conn, $update_sql);
            $msg = "Attendance matrix successfully synchronized!";
        } else {
            $insert_sql = "INSERT INTO staff_attendance (staff_id, date, clock_in, clock_out, status, total_hours, overtime, notes) VALUES ($staff_id, '$date', " . ($clock_in ? "'$clock_in'" : "NULL") . ", " . ($clock_out ? "'$clock_out'" : "NULL") . ", '$status', $total_hours, $overtime, '$notes')";
            @mysqli_query($conn, $insert_sql);
            $msg = "New biometric/shift log registered successfully!";
        }
    } else {
        $error = "Critical parameters missing. Please check staff selection and date.";
    }
}

if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM staff_attendance WHERE attendance_id = $del_id");
    header("Location: staff_attendance.php");
    exit();
}

$staff_query = mysqli_query($conn, "SELECT * FROM users WHERE role != 'student'");
$staff_list = [];
if ($staff_query) {
    while($s = mysqli_fetch_assoc($staff_query)) { $staff_list[] = $s; }
}

$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';

$query = "SELECT a.*, u.full_name as staff_name, u.role as position FROM staff_attendance a JOIN users u ON a.staff_id = u.id WHERE 1=1";

if (!empty($search_query)) {
    $s_esc = mysqli_real_escape_string($conn, $search_query);
    $query .= " AND (u.full_name LIKE '%$s_esc%' OR u.role LIKE '%$s_esc%' OR a.status LIKE '%$s_esc%')";
}
if ($status_filter != 'all') {
    $st_esc = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND a.status = '$st_esc'";
}
if (!empty($date_filter)) {
    $d_esc = mysqli_real_escape_string($conn, $date_filter);
    $query .= " AND a.date = '$d_esc'";
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;

$count_q = mysqli_query($conn, "SELECT COUNT(a.attendance_id) as cnt FROM staff_attendance a JOIN users u ON a.staff_id = u.id WHERE 1=1" . (empty($search_query) ? "" : " AND (u.full_name LIKE '%$search_query%')"));
$count_r = $count_q ? mysqli_fetch_assoc($count_q) : ['cnt' => 0];
$total_records = $count_r['cnt'] ?? 0;
$total_pages = ceil($total_records / $limit);

$query .= " ORDER BY a.date DESC, a.attendance_id DESC LIMIT $start, $limit";
$attendance_result = mysqli_query($conn, $query);

$present_today_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM staff_attendance WHERE date = '$today_date' AND status = 'Present'");
$present_today = $present_today_q ? mysqli_fetch_assoc($present_today_q)['cnt'] ?? 0 : 0;

$late_today_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM staff_attendance WHERE date = '$today_date' AND status = 'Late'");
$late_today = $late_today_q ? mysqli_fetch_assoc($late_today_q)['cnt'] ?? 0 : 0;

$absent_today_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM staff_attendance WHERE date = '$today_date' AND status = 'Absent'");
$absent_today = $absent_today_q ? mysqli_fetch_assoc($absent_today_q)['cnt'] ?? 0 : 0;

$total_ot_q = mysqli_query($conn, "SELECT SUM(overtime) as total_ot FROM staff_attendance WHERE date = '$today_date'");
$total_ot_today = $total_ot_q ? mysqli_fetch_assoc($total_ot_q)['total_ot'] ?? 0.00 : 0.00;

$total_staff_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE role != 'student'");
$total_staff_count = $total_staff_q ? mysqli_fetch_assoc($total_staff_q)['cnt'] ?? 1 : 1;
$attendance_rate = round(($present_today / max($total_staff_count, 1)) * 100);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Staff Attendance | EWU cafeteria</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { display: flex; background: #f8fafc; color: #1e293b; min-height: 100vh; overflow-x: hidden; }

        .sidebar { width: 260px; background: #ffffff; border-right: 1px solid #e2e8f0; padding: 30px 20px; display: flex; flex-direction: column; justify-content: space-between; position: fixed; height: 100vh; z-index: 100; box-shadow: 4px 0 20px rgba(0,0,0,0.02); }
        .brand { font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 12px; margin-bottom: 35px; padding-left: 6px; }
        .brand-icon { background: linear-gradient(135deg, #6366f1, #a855f7); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); }
        .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .nav-item a { display: flex; align-items: center; gap: 14px; padding: 12px 16px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; border-radius: 12px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-item.active a { background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.1)); color: #7c3aed; font-weight: 700; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.08); }
        .nav-item a:hover { background: #f1f5f9; color: #0f172a; transform: translateX(4px); }

        .main-content { flex: 1; margin-left: 260px; padding: 30px 40px; background: #f8fafc; min-height: 100vh; opacity: 0; }
        
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; background: #ffffff; padding: 22px 30px; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        .page-title h1 { font-size: 20px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .page-title p { font-size: 13px; color: #64748b; margin-top: 4px; font-weight: 500; }

        .header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn-action-top { background: #ffffff; border: 1.5px solid #e2e8f0; padding: 10px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-action-top:hover { border-color: #7c3aed; color: #7c3aed; background: #faf5ff; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(124, 58, 237, 0.12); }

        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #ffffff; border-radius: 18px; border: 1px solid #e2e8f0; padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); transition: all 0.3s ease; }
        .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .stat-info span { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-info h3 { font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 2px; }

        .dashboard-row { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
        .form-card, .chart-card, .table-card { background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; padding: 24px 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
        
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 12px 18px; border-radius: 12px; font-size: 13px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 18px; border-radius: 12px; font-size: 13px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .attendance-form { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: span 2; }
        .form-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { padding: 12px 16px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 13px; color: #0f172a; outline: none; width: 100%; transition: all 0.3s; }
        .form-control:focus { border-color: #7c3aed; background: #ffffff; box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.1); }
        
        .btn-record { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border: none; padding: 14px 24px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3); margin-top: 10px; transition: all 0.3s; }
        .btn-record:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(124, 58, 237, 0.4); }

        .filter-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
        .filter-left { display: flex; gap: 12px; align-items: center; }
        .filter-select { padding: 10px 16px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 13px; color: #0f172a; outline: none; cursor: pointer; }
        .search-box { position: relative; width: 260px; }
        .search-box input { padding: 10px 16px 10px 40px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 13px; color: #0f172a; outline: none; width: 100%; }
        .search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #64748b; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; padding: 14px; border-bottom: 2px solid #f1f5f9; }
        td { padding: 16px 14px; font-size: 14px; color: #334155; border-bottom: 1px solid #f1f5f9; font-weight: 500; }
        tr:hover td { background: #fafafa; }

        .badge-status { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge-present { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .badge-late { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .badge-absent { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .badge-leave { background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; }
        .overtime-badge { background: #faf5ff; color: #9333ea; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; margin-left: 6px; border: 1px solid #e9d5ff; }

        .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.3s; }
        .btn-delete:hover { background: #dc2626; color: #fff; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3); }
        
        .pagination { display: flex; justify-content: flex-end; gap: 6px; margin-top: 24px; }
        .page-link { padding: 8px 14px; border-radius: 10px; border: 1px solid #e2e8f0; background: #ffffff; font-size: 13px; font-weight: 700; color: #64748b; text-decoration: none; transition: all 0.3s; }
        .page-link.active, .page-link:hover { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border-color: transparent; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3); }
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
                <li class="nav-item"><a href="manage_staff.php"><i class="fa-solid fa-users-gear"></i> Manage Staff</a></li>
                <li class="nav-item active"><a href="staff_attendance.php"><i class="fa-solid fa-clipboard-user"></i> Staff Attendance</a></li>
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
                <h1><i class="fa-solid fa-clipboard-user" style="color:#7c3aed;"></i>  Staff Attendance</h1>
                <p></p>
            </div>
            <div class="header-actions">
                <form method="POST" onsubmit="return confirm('Execute bulk status assignment for all unmarked staff?');" style="display:flex; gap:8px;">
                    <input type="hidden" name="bulk_action" value="true">
                    <button type="submit" name="bulk_action_type" value="present" class="btn-action-top" style="color:#059669; border-color:#a7f3d0; background:#ecfdf5;">
                        <i class="fa-solid fa-user-check"></i> Bulk Present
                    </button>
                    <button type="submit" name="bulk_action_type" value="absent" class="btn-action-top" style="color:#dc2626; border-color:#fecaca; background:#fef2f2;">
                        <i class="fa-solid fa-user-xmark"></i> Bulk Absent
                    </button>
                    <button type="submit" name="bulk_action_type" value="reset" class="btn-action-top" style="color:#d97706; border-color:#fde68a; background:#fffbeb;" onclick="return confirm('Reset all logs for today?');">
                        <i class="fa-solid fa-rotate-right"></i> Reset Today
                    </button>
                </form>
                <a href="staff_attendance.php?export=true" class="btn-action-top">
                    <i class="fa-solid fa-file-excel" style="color:#059669;"></i> Export CSV
                </a>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= $msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #ecfdf5; color: #059669;"><i class="fa-solid fa-user-check"></i></div>
                <div class="stat-info"><span>Present Today</span><h3 class="counter" data-target="<?= $present_today; ?>">0</h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fffbeb; color: #d97706;"><i class="fa-solid fa-user-clock"></i></div>
                <div class="stat-info"><span>Late Today</span><h3 class="counter" data-target="<?= $late_today; ?>">0</h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fef2f2; color: #dc2626;"><i class="fa-solid fa-user-xmark"></i></div>
                <div class="stat-info"><span>Absent Today</span><h3 class="counter" data-target="<?= $absent_today; ?>">0</h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #faf5ff; color: #9333ea;"><i class="fa-solid fa-business-time"></i></div>
                <div class="stat-info"><span>Overtime Hours</span><h3><span class="counter" data-target="<?= $total_ot_today; ?>">0</span>h</h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #eef2ff; color: #4f46e5;"><i class="fa-solid fa-chart-pie"></i></div>
                <div class="stat-info"><span>Turnout Rate</span><h3><span class="counter" data-target="<?= $attendance_rate; ?>">0</span>%</h3></div>
            </div>
        </div>

        <div class="dashboard-row">
            <div class="form-card">
                <h3 style="margin-bottom: 16px; font-size: 15px; font-weight: 800; color: #0f172a;"><i class="fa-solid fa-fingerprint" style="color:#7c3aed;"></i> Manual Shift Synchronization</h3>
                <form method="POST" class="attendance-form">
                    <div class="form-group">
                        <label class="form-label">Staff Member *</label>
                        <select name="staff_id" class="form-control" required>
                            <option value="">Select Personnel</option>
                            <?php foreach($staff_list as$st): ?>
                                <option value="<?= $st['id']; ?>"><?= htmlspecialchars($st['full_name']); ?> (<?= htmlspecialchars($st['role'] ?? 'Staff'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Clock In</label>
                        <input type="time" name="clock_in" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Clock Out</label>
                        <input type="time" name="clock_out" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-control" required>
                            <option value="Present">Present</option>
                            <option value="Late">Late</option>
                            <option value="Absent">Absent</option>
                            <option value="On Leave">On Leave</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Shift Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes...">
                    </div>
                    <div class="form-group full">
                        <button type="submit" name="record_attendance" class="btn-record"><i class="fa-solid fa-cloud-arrow-up"></i> Synchronize Attendance Matrix</button>
                    </div>
                </form>
            </div>

            <div class="chart-card">
                <h3 style="margin-bottom: 12px; font-size: 15px; font-weight: 800; color: #0f172a;"><i class="fa-solid fa-chart-pie" style="color:#7c3aed;"></i> Today Status Ratio</h3>
                <div style="height: 230px; display: flex; justify-content: center; align-items: center;">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="filter-row">
                <h3><i class="fa-solid fa-list-check" style="color:#7c3aed;"></i> Personnel Audit Logs</h3>
                <div class="filter-left">
                    <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?= $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Present" <?= $status_filter == 'Present' ? 'selected' : ''; ?>>Present</option>
                            <option value="Late" <?= $status_filter == 'Late' ? 'selected' : ''; ?>>Late</option>
                            <option value="Absent" <?= $status_filter == 'Absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="On Leave" <?= $status_filter == 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                        </select>
                        <input type="date" name="date" class="filter-select" value="<?= htmlspecialchars($date_filter); ?>" onchange="this.form.submit()">
                    </form>
                    <form method="GET" class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" placeholder="Search logs..." value="<?= htmlspecialchars($search_query); ?>" onchange="this.form.submit()">
                    </form>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Staff Name</th>
                        <th>Position</th>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Net Hours</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($attendance_result && mysqli_num_rows($attendance_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($attendance_result)): ?>
                            <?php 
                                $badge_class = 'badge-present';
                                if ($row['status'] == 'Late')$badge_class = 'badge-late';
                                if ($row['status'] == 'Absent')$badge_class = 'badge-absent';
                                if ($row['status'] == 'On Leave')$badge_class = 'badge-leave';
                            ?>
                            <tr>
                                <td style="font-weight: 700; color: #7c3aed;">#<?= $row['attendance_id']; ?></td>
                                <td>
                                    <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['staff_name']); ?></div>
                                    <?php if(!empty($row['notes'])): ?>
                                        <div style="font-size: 11px; color: #64748b; font-style: italic;">Note: <?= htmlspecialchars($row['notes']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #64748b;"><?= htmlspecialchars($row['position'] ?? 'Staff'); ?></td>
                                <td style="font-weight: 600;"><?= htmlspecialchars($row['date']); ?></td>
                                <td style="color: #059669; font-weight: 600;"><?= $row['clock_in'] ? date('h:i A', strtotime($row['clock_in'])) : '---'; ?></td>
                                <td style="color: #dc2626; font-weight: 600;"><?= $row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : '---'; ?></td>
                                <td>
                                    <span style="font-weight: 700; color: #4f46e5;"><?= $row['total_hours']; ?> hrs</span>
                                    <?php if($row['overtime'] > 0): ?>
                                        <span class="overtime-badge">+<?= $row['overtime']; ?>h OT</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge-status <?= $badge_class; ?>"><?= htmlspecialchars($row['status']); ?></span></td>
                                <td>
                                    <a href="staff_attendance.php?delete=<?= $row['attendance_id']; ?>" class="btn-delete" onclick="return confirm('Delete this log?');"><i class="fa-solid fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" style="text-align: center; padding: 40px; color: #64748b;">No matching records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="staff_attendance.php?page=<?= $i; ?>" class="page-link <?= $page == $i ? 'active' : ''; ?>"><?= $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const tl = gsap.timeline();
            tl.to("#mainContent", { opacity: 1, duration: 0.5, ease: "power2.out" })
              .from(".top-header", { duration: 0.5, opacity: 0, y: -20, ease: "power2.out" }, "-=0.2")
              .from(".stat-card", { duration: 0.4, opacity: 0, y: 20, stagger: 0.06, ease: "back.out(1.4)" }, "-=0.2")
              .from(".form-card, .chart-card", { duration: 0.5, opacity: 0, y: 25, stagger: 0.1, ease: "power2.out" }, "-=0.2")
              .from(".table-card", { duration: 0.5, opacity: 0, y: 25, ease: "power2.out" }, "-=0.2");

            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('mouseenter', () => {
                    gsap.to(card, { scale: 1.02, y: -4, borderColor: '#a855f7', duration: 0.3, ease: "power2.out" });
                });
                card.addEventListener('mouseleave', () => {
                    gsap.to(card, { scale: 1, y: 0, borderColor: '#e2e8f0', duration: 0.3, ease: "power2.out" });
                });
            });

            document.querySelectorAll('.counter').forEach(counter => {
                const target = +counter.getAttribute('data-target');
                gsap.to(counter, {
                    innerHTML: target,
                    duration: 1.5,
                    snap: { innerHTML: 0.1 },
                    ease: "power1.out"
                });
            });

            const ctx = document.getElementById('attendanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Late', 'Absent'],
                    datasets: [{
                        data: [<?= $present_today; ?>, <?= $late_today; ?>, <?=$absent_today; ?>],
                        backgroundColor: ['#059669', '#d97706', '#dc2626'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: "'Plus Jakarta Sans', sans-serif",
                                    weight: 600,
                                    size: 12
                                },
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        });
    </script>
</body>
</html>