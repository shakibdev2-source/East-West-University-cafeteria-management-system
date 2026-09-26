<?php
$host = "127.0.0.1";
$username = "root";
$password = "";
$database = "campusbite_db";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $imgQuery = $conn->query("SELECT image FROM foods WHERE food_id = $delete_id");
    if ($imgRow = $imgQuery->fetch_assoc()) {
        if (!empty($imgRow['image'])) {
            if (file_exists("uploads/" . $imgRow['image'])) {
                unlink("uploads/" . $imgRow['image']);
            } elseif (file_exists("../uploads/" . $imgRow['image'])) {
                unlink("../uploads/" . $imgRow['image']);
            }
        }
    }
    $conn->query("DELETE FROM foods WHERE food_id = $delete_id");
    header("Location: manage_food.php?msg=deleted");
    exit();
}

$query = "SELECT f.*, c.category_name FROM foods f LEFT JOIN categories c ON f.category_id = c.category_id ORDER BY f.food_id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Food Items - CampusBite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-[#f8fafc]">

    <aside class="w-64 bg-white border-r border-slate-200/80 flex flex-col justify-between shrink-0 z-20">
        <div>
            <div class="h-20 flex items-center px-6 gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-600 flex items-center justify-center text-white text-xl shadow-lg shadow-purple-500/20">
                    <i class="fa-solid fa-utensils"></i>
                </div>
                <span class="text-xl font-bold tracking-wide text-slate-800">EWU Cafeteria</span>
            </div>

            <nav class="p-4 space-y-1.5">
                <a href="dashboard.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-purple-600 transition-all">
                    <i class="fa-solid fa-chart-pie w-5"></i> Dashboard
                </a>
                <a href="add_food.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-purple-600 transition-all">
                    <i class="fa-solid fa-plus-circle w-5"></i> Add Food
                </a>
                <a href="manage_food.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium bg-purple-50 text-purple-600 shadow-sm transition-all">
                    <i class="fa-solid fa-burger w-5"></i> Manage Food
                </a>
                <a href="manage_orders.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-purple-600 transition-all">
                    <i class="fa-solid fa-clipboard-list w-5"></i> Manage Orders
                </a>
                <a href="manage_staff.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-purple-600 transition-all">
                    <i class="fa-solid fa-users-gear w-5"></i> Manage Staff
                </a>
                <a href="reports.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-purple-600 transition-all">
                    <i class="fa-solid fa-chart-line w-5"></i> Reports
                </a>
                <a href="settings.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-purple-600 transition-all">
                    <i class="fa-solid fa-gear w-5"></i> Settings
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-slate-100">
            <a href="logout.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium text-red-500 hover:bg-red-50 transition-all">
                <i class="fa-solid fa-right-from-bracket w-5"></i> Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        
        <div class="p-8 pb-4">
            <div id="topHeaderCard" class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600 text-xl font-bold shadow-inner">
                        <i class="fa-solid fa-burger"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-800">Manage Food Items</h1>
                        <p class="text-xs text-slate-500 mt-0.5">EWU CAFETERIA • View, edit, or delete items from the menu list.</p>
                    </div>
                </div>
                
                <a href="add_food.php" class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-lg shadow-purple-600/20 flex items-center justify-center gap-2 transition-all transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-plus"></i> Add New Item
                </a>
            </div>
        </div>

        <div class="px-8 pb-8 max-w-7xl w-full mx-auto">
            
            <div id="tableCard" class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 text-xs font-semibold uppercase tracking-wider bg-slate-50/50">
                                <th class="py-4 px-6">Image</th>
                                <th class="py-4 px-6">Food Name</th>
                                <th class="py-4 px-6">Category</th>
                                <th class="py-4 px-6">Price</th>
                                <th class="py-4 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-600 text-sm">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="food-row hover:bg-slate-50/80 transition-colors">
                                        <td class="py-4 px-6">
                                            <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shadow-sm">
                                                <?php 
                                                $img_name = $row['image'];
                                                $path1 = "uploads/" . $img_name;
                                                $path2 = "../uploads/" . $img_name;

                                                if (!empty($img_name) && file_exists($path1)): 
                                                ?>
                                                    <img src="<?php echo $path1; ?>" alt="Food" class="w-full h-full object-cover">
                                                <?php elseif (!empty($img_name) && file_exists($path2)): ?>
                                                    <img src="<?php echo $path2; ?>" alt="Food" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <i class="fa-solid fa-utensils text-slate-400"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 font-semibold text-slate-800">
                                            <?php echo htmlspecialchars($row['food_name']); ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="bg-purple-50 text-purple-600 border border-purple-100 px-3 py-1 rounded-full text-xs font-medium">
                                                <?php echo htmlspecialchars($row['category_name'] ?? $row['category'] ?? 'General'); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 font-bold text-emerald-600">
                                            ৳<?php echo number_format($row['price'], 2); ?>
                                        </td>
                                        <td class="py-4 px-6 text-right space-x-2">
                                            <a href="edit_food.php?id=<?php echo $row['food_id']; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg text-xs font-medium transition-all shadow-sm">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </a>
                                            <button onclick="deleteFood(<?php echo $row['food_id']; ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-lg text-xs font-medium transition-all shadow-sm cursor-pointer">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-16 text-center text-slate-400">
                                        <i class="fa-solid fa-box-open text-4xl mb-3 text-slate-300"></i>
                                        <p class="font-medium">No food items found in the database.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        function deleteFood(foodId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this item!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!',
                background: '#ffffff',
                customClass: {
                    popup: 'rounded-2xl shadow-xl border border-slate-100',
                    confirmButton: 'px-4 py-2.5 rounded-xl font-medium text-sm',
                    cancelButton: 'px-4 py-2.5 rounded-xl font-medium text-sm'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `manage_food.php?delete_id=${foodId}`;
                }
            })
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'deleted') {
            Swal.fire({
                icon: 'success',
                title: 'Deleted!',
                text: 'Food item has been removed successfully.',
                timer: 2000,
                showConfirmButton: false,
                customClass: {
                    popup: 'rounded-2xl shadow-xl border border-slate-100'
                }
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        document.addEventListener("DOMContentLoaded", () => {
            gsap.from("aside", { x: -50, opacity: 0, duration: 0.8, ease: "power3.out" });
            gsap.from("#topHeaderCard", { y: -30, opacity: 0, duration: 0.7, ease: "power3.out", delay: 0.2 });
            gsap.from("#tableCard", { y: 30, opacity: 0, duration: 0.8, ease: "power3.out", delay: 0.4 });

            const rows = document.querySelectorAll(".food-row");
            if(rows.length > 0) {
                gsap.from(rows, { x: -20, opacity: 0, duration: 0.5, stagger: 0.08, ease: "power2.out", delay: 0.6 });
            }
        });
    </script>
</body>
</html>