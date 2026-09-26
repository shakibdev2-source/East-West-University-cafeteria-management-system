<?php
declare(strict_types=1);
session_start();

class Database {
    private static ?mysqli $instance = null;

    public static function getConnection(): mysqli {
        if (self::$instance === null) {
            self::$instance = new mysqli("localhost", "root", "", "campusbite_db");
            if (self::$instance->connect_error) {
                http_response_code(500);
                die(json_encode(['status' => 'error', 'message' => 'Database connection failed.']));
            }
            self::$instance->set_charset("utf8mb4");
        }
        return self::$instance;
    }
}

class CartManager {
    private mysqli $db;
    private int $userId;

    public function __construct(int $userId) {$this->db = Database::getConnection();
        $this->userId =$userId;
    }

    public function updateQuantity(int $cartId, string$action): array {
        if ($action === 'increase') {
            $stmt =$this->db->prepare("UPDATE cart SET quantity = quantity + 1 WHERE cart_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cartId, $this->userId);$stmt->execute();
        } elseif ($action === 'decrease') {
            $stmt =$this->db->prepare("SELECT quantity FROM cart WHERE cart_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cartId, $this->userId);$stmt->execute();
            $qty =$stmt->get_result()->fetch_assoc()['quantity'] ?? 0;

            if ($qty > 1) {
                $stmt =$this->db->prepare("UPDATE cart SET quantity = quantity - 1 WHERE cart_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $cartId, $this->userId);$stmt->execute();
            } else {
                return $this->removeItem($cartId);
            }
        }
        return ['status' => 'success'];
    }

    public function removeItem(int $cartId): array {
        $stmt =$this->db->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cartId, $this->userId);$stmt->execute();
        return ['status' => 'success', 'removed' => true];
    }

    public function clearCart(): bool {
        $stmt =$this->db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $this->userId);
        return $stmt->execute();
    }

    public function getCartItems(): array {
        $stmt =$this->db->prepare("
            SELECT c.cart_id, c.quantity, f.food_name, f.price, f.image 
            FROM cart c 
            JOIN foods f ON c.food_id = f.food_id 
            WHERE c.user_id = ?
        ");
        $stmt->bind_param("i", $this->userId);$stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$userId =$_SESSION['user_id'] ?? 3;
$cartManager = new CartManager($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    header('Content-Type: application/json');
    $actionType =$_POST['action_type'];

    if ($actionType === 'update_qty') {
        $cartId = intval($_POST['cart_id']);
        $act =$_POST['act'];
        echo json_encode($cartManager->updateQuantity($cartId,$act));
        exit();
    }

    if ($actionType === 'remove_item') {
        $cartId = intval($_POST['cart_id']);
        echo json_encode($cartManager->removeItem($cartId));
        exit();
    }

    if ($actionType === 'apply_promo') {
        $code = trim($_POST['promo_code'] ?? '');
        if (strcasecmp($code, 'Sha2') === 0 || strcasecmp($code, 'Sh2') === 0) {$_SESSION['discount_pct'] = 30;
            $_SESSION['applied_promo'] = strtoupper($code);
            echo json_encode(['status' => 'success', 'message' => '30% Discount Applied Successfully!', 'code' => strtoupper($code)]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or Expired Promo Code']);
        }
        exit();
    }

    if ($actionType === 'remove_promo') {
        unset($_SESSION['discount_pct'],$_SESSION['applied_promo']);
        echo json_encode(['status' => 'success', 'message' => 'Promo code has been removed']);
        exit();
    }
}

if (isset($_POST['clear_all'])) {$cartManager->clearCart();
    unset($_SESSION['discount_pct'],$_SESSION['applied_promo']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$cartItems =$cartManager->getCartItems();
$discountPct =$_SESSION['discount_pct'] ?? 0;
$appliedPromo =$_SESSION['applied_promo'] ?? '';
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EWU Cafeteria - Cart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="h-full text-slate-800 antialiased flex">

    <aside class="w-64 bg-white border-r border-slate-100 p-6 flex flex-col justify-between fixed h-full z-30">
        <div>
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-[#00966b] rounded-2xl flex items-center justify-center text-white shadow-md">
                    <i class="fa-solid fa-utensils text-lg"></i>
                </div>
                <span class="text-lg font-bold text-slate-900 tracking-tight">EWU Cafeteria</span>
            </div>

            <nav class="space-y-2">
                <a href="dashboard.php" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-semibold text-slate-500 hover:bg-[#e6f7f2] hover:text-[#00966b] transition-all">
                    <i class="fa-solid fa-house text-base w-5"></i>
                    <span class="text-sm">Dashboard</span>
                </a>

                <a href="menu.php" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-semibold text-slate-500 hover:bg-[#e6f7f2] hover:text-[#00966b] transition-all">
                    <i class="fa-solid fa-book-open text-base w-5"></i>
                    <span class="text-sm">Food Menu</span>
                </a>

                <a href="cart.php" class="flex items-center justify-between px-4 py-3 rounded-2xl font-semibold text-[#00966b] bg-[#e6f7f2] transition-all">
                    <div class="flex items-center gap-3.5">
                        <i class="fa-solid fa-cart-shopping text-base w-5"></i>
                        <span class="text-sm">My Cart</span>
                    </div>
                    <span id="badge-count" class="bg-[#00966b] text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold">
                        <?= array_sum(array_column($cartItems, 'quantity')) ?>
                    </span>
                </a>

                <a href="checkout.php" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-semibold text-slate-500 hover:bg-[#e6f7f2] hover:text-[#00966b] transition-all">
                    <i class="fa-regular fa-credit-card text-base w-5"></i>
                    <span class="text-sm">Checkout</span>
                </a>

                <a href="orders.php" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-semibold text-slate-500 hover:bg-[#e6f7f2] hover:text-[#00966b] transition-all">
                    <i class="fa-solid fa-rotate-left text-base w-5"></i>
                    <span class="text-sm">My Orders</span>
                </a>

                <a href="feedback.php" class="flex items-center gap-3.5 px-4 py-3 rounded-2xl font-semibold text-slate-500 hover:bg-[#e6f7f2] hover:text-[#00966b] transition-all">
                    <i class="fa-solid fa-comment text-base w-5"></i>
                    <span class="text-sm">Feedback</span>
                </a>
            </nav>
        </div>

        <div class="pt-4 border-t border-slate-100">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 font-bold text-red-500 hover:bg-red-50 rounded-2xl transition-all text-sm">
                <i class="fa-solid fa-right-from-bracket text-base"></i>
                <span>Sign Out</span>
            </a>
        </div>
    </aside>

    <main class="ml-64 flex-1 p-8">
        
        <header class="flex items-center justify-between mb-8">
            <div class="relative w-96">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" placeholder="Search food items..." class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-2xl text-sm font-medium focus:outline-none focus:border-[#00966b] focus:ring-4 focus:ring-[#00966b]/10 transition-all shadow-sm">
            </div>

            <div class="flex items-center gap-4">
                <button class="w-11 h-11 bg-white border border-slate-200 rounded-2xl flex items-center justify-center text-slate-600 hover:text-[#00966b] transition-all shadow-sm">
                    <i class="fa-regular fa-bell"></i>
                </button>
                <div class="w-11 h-11 bg-[#00966b] rounded-2xl text-white font-extrabold flex items-center justify-center shadow-md">
                    S
                </div>
            </div>
        </header>

        <?php if (!empty($cartItems)): ?>
        <div class="grid grid-cols-12 gap-8 items-start">
            
            <div class="col-span-8 bg-white rounded-3xl border border-slate-100 p-8 shadow-sm">
                <div class="flex items-center justify-between pb-6 border-b border-slate-100 mb-6">
                    <h1 class="text-2xl font-extrabold text-slate-900 flex items-center gap-3">
                        <i class="fa-solid fa-cart-shopping text-[#00966b]"></i> My Shopping Cart
                    </h1>
                    <form method="POST" onsubmit="return confirm('Clear entire cart?');">
                        <button type="submit" name="clear_all" class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                            <i class="fa-solid fa-trash-can"></i> Clear All
                        </button>
                    </form>
                </div>

                <div class="space-y-4" id="cart-container">
                    <?php 
                    $subtotal = 0;
                    foreach ($cartItems as $item):$itemTotal = $item['price'] *$item['quantity'];
                        $subtotal +=$itemTotal;
                    ?>
                    <div id="item-row-<?= $item['cart_id'] ?>" class="flex items-center justify-between p-4 bg-slate-50/70 hover:bg-slate-50 border border-slate-100 rounded-2xl transition-all">
                        <div class="flex items-center gap-4 w-5/12">
                            <div class="w-16 h-16 rounded-xl bg-white p-1 border border-slate-100 shadow-sm flex-shrink-0">
                                <img src="../uploads/<?= htmlspecialchars($item['image'] ?: 'default.jpg') ?>" class="w-full h-full object-cover rounded-lg" alt="Food">
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 leading-tight"><?= htmlspecialchars($item['food_name']) ?></h3>
                                <p class="text-xs text-slate-400 font-semibold mt-1">৳<span class="unit-price" data-price="<?= $item['price'] ?>"><?= number_format((float)$item['price'], 2) ?></span></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-1 shadow-sm">
                            <button onclick="handleQty(<?= $item['cart_id'] ?>, 'decrease')" class="w-8 h-8 rounded-lg bg-slate-50 hover:bg-[#00966b] hover:text-white font-bold text-slate-600 transition-all flex items-center justify-center">-</button>
                            <span id="qty-val-<?= $item['cart_id'] ?>" class="font-extrabold text-sm px-2 item-qty"><?= $item['quantity'] ?></span>
                            <button onclick="handleQty(<?= $item['cart_id'] ?>, 'increase')" class="w-8 h-8 rounded-lg bg-slate-50 hover:bg-[#00966b] hover:text-white font-bold text-slate-600 transition-all flex items-center justify-center">+</button>
                        </div>

                        <div class="text-right">
                            <span class="font-extrabold text-[#00966b] text-base">৳<span id="item-total-<?= $item['cart_id'] ?>" class="item-subtotal"><?= number_format((float)$itemTotal, 2) ?></span></span>
                        </div>

                        <button onclick="removeItem(<?= $item['cart_id'] ?>)" class="w-8 h-8 bg-rose-50 hover:bg-rose-500 text-rose-500 hover:text-white rounded-xl flex items-center justify-center transition-all">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-8 pt-6 border-t border-slate-100">
                    <a href="menu.php" class="text-slate-500 hover:text-[#00966b] font-bold text-sm flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            </div>

            <div class="col-span-4 bg-white rounded-3xl border border-slate-100 p-8 shadow-sm sticky top-10">
                <h2 class="text-xl font-extrabold text-slate-900 mb-6">Order Summary</h2>

                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 mb-6 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#e6f7f2] text-[#00966b] flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-bolt text-lg"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase">Estimated Pickup</p>
                        <p class="text-sm font-extrabold text-slate-800">10 - 15 Mins</p>
                    </div>
                </div>

                <div class="space-y-3 border-b border-slate-100 pb-4 mb-4">
                    <div class="flex justify-between text-sm font-semibold text-slate-500">
                        <span>Items Total (<?= array_sum(array_column($cartItems, 'quantity')) ?>)</span>
                        <span class="font-bold text-slate-800">৳<span id="summary-subtotal"><?= number_format((float)$subtotal, 2) ?></span></span>
                    </div>

                    <div id="discount-row" class="flex justify-between text-sm font-semibold text-[#00966b] <?= $discountPct > 0 ? '' : 'hidden' ?>">
                        <span>Discount (<?= $discountPct ?>%)</span>
                        <span class="font-bold">-৳<span id="summary-discount"><?= number_format(($subtotal * $discountPct) / 100, 2) ?></span></span>
                    </div>

                    <div class="flex justify-between text-sm font-semibold text-slate-500">
                        <span>Vat / Tax</span>
                        <span class="font-bold text-slate-800">৳0.00</span>
                    </div>
                </div>

                <div class="mb-6">
                    <div id="promo-input-box" class="<?= $discountPct > 0 ? 'hidden' : '' ?> flex gap-2">
                        <input type="text" id="promo-input" placeholder="PROMO CODE" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold uppercase focus:outline-none focus:border-[#00966b]">
                        <button onclick="applyPromo()" class="px-5 bg-slate-900 hover:bg-[#00966b] text-white font-bold text-xs rounded-xl transition-all">Apply</button>
                    </div>
                    <div id="promo-applied-box" class="<?= $discountPct > 0 ? '' : 'hidden' ?> flex items-center justify-between p-3 bg-[#e6f7f2] border border-[#00966b]/20 rounded-xl text-xs font-bold text-[#00966b]">
                        <span><i class="fa-solid fa-ticket mr-1"></i> <span id="applied-code-text"><?= $appliedPromo ?></span> Applied</span>
                        <button onclick="removePromo()" class="text-rose-500 hover:text-rose-700 font-extrabold"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>

                <?php $grandTotal =$subtotal - (($subtotal * $discountPct) / 100); ?>
                <div class="flex justify-between items-center mb-6 pt-2">
                    <span class="text-lg font-extrabold text-slate-900">Grand Total</span>
                    <span class="text-2xl font-black text-[#00966b]">৳<span id="summary-grand-total"><?= number_format((float)$grandTotal, 2) ?></span></span>
                </div>

                <a href="checkout.php" class="w-full py-4 bg-[#00966b] hover:bg-[#007d59] text-white font-extrabold rounded-2xl shadow-lg shadow-[#00966b]/20 flex items-center justify-center gap-3 transition-all">
                    Proceed to Checkout <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <?php else: ?>
        <div class="bg-white rounded-3xl border border-slate-100 p-16 text-center shadow-sm max-w-xl mx-auto mt-10">
            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mx-auto mb-6 text-2xl">
                <i class="fa-solid fa-basket-shopping"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900 mb-2">Your Cart is Empty</h2>
            <p class="text-slate-400 text-sm font-medium mb-8">Looks like you haven't added any items to your cart yet.</p>
            <a href="menu.php" class="inline-flex items-center gap-2 px-8 py-3.5 bg-[#00966b] hover:bg-[#007d59] text-white font-extrabold text-sm rounded-xl transition-all shadow-md">
                Explore Menu
            </a>
        </div>
        <?php endif; ?>

    </main>

    <script>
        let discountPct = <?= $discountPct ?>;

        function handleQty(cartId, act) {
            const qtySpan = document.getElementById(`qty-val-${cartId}`);
            let qty = parseInt(qtySpan.innerText);

            if (act === 'increase') qty++;
            if (act === 'decrease') qty--;

            if (qty <= 0) {
                removeItem(cartId);
                return;
            }

            qtySpan.innerText = qty;
            const unitPrice = parseFloat(document.querySelector(`#item-row-${cartId} .unit-price`).dataset.price);
            document.getElementById(`item-total-${cartId}`).innerText = (unitPrice * qty).toFixed(2);

            recalculateTotals();
            sendCartAjax('update_qty', { cart_id: cartId, act: act });
        }

        function removeItem(cartId) {
            const row = document.getElementById(`item-row-${cartId}`);
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                recalculateTotals();
                if (document.querySelectorAll('#cart-container > div').length === 0) {
                    location.reload();
                }
            }, 200);

            sendCartAjax('remove_item', { cart_id: cartId });
        }

        function recalculateTotals() {
            let totalQty = 0;
            let subtotal = 0;

            document.querySelectorAll('#cart-container > div').forEach(row => {
                const qty = parseInt(row.querySelector('.item-qty').innerText);
                const price = parseFloat(row.querySelector('.unit-price').dataset.price);
                totalQty += qty;
                subtotal += qty * price;
            });

            document.getElementById('badge-count').innerText = totalQty;
            document.getElementById('summary-subtotal').innerText = subtotal.toFixed(2);

            const discount = (subtotal * discountPct) / 100;
            document.getElementById('summary-discount').innerText = discount.toFixed(2);
            document.getElementById('summary-grand-total').innerText = (subtotal - discount).toFixed(2);
        }

        function applyPromo() {
            const promoInput = document.getElementById('promo-input');
            const promo = promoInput.value.trim();

            if (!promo) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Empty Field',
                    text: 'Please enter a promo code!',
                    confirmColor: '#00966b'
                });
                return;
            }

            sendCartAjax('apply_promo', { promo_code: promo }, (res) => {
                if (res.status === 'success') {
                    discountPct = 30;
                    document.getElementById('promo-input-box').classList.add('hidden');
                    document.getElementById('promo-applied-box').classList.remove('hidden');
                    document.getElementById('applied-code-text').innerText = res.code;
                    document.getElementById('discount-row').classList.remove('hidden');
                    recalculateTotals();

                    Swal.fire({
                        icon: 'success',
                        title: '30% Discount Applied!',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Promo Code',
                        text: res.message,
                        confirmColor: '#00966b'
                    });
                }
            });
        }

        function removePromo() {
            sendCartAjax('remove_promo', {}, (res) => {
                discountPct = 0;
                document.getElementById('promo-input-box').classList.remove('hidden');
                document.getElementById('promo-applied-box').classList.add('hidden');
                document.getElementById('discount-row').classList.add('hidden');
                document.getElementById('promo-input').value = '';
                recalculateTotals();

                Swal.fire({
                    icon: 'info',
                    title: 'Removed',
                    text: res.message,
                    timer: 1500,
                    showConfirmButton: false
                });
            });
        }

        function sendCartAjax(actionType, data, callback) {
            const formData = new FormData();
            formData.append('action_type', actionType);
            for (let key in data) formData.append(key, data[key]);

            fetch(window.location.href, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => callback && callback(res));
        }
    </script>
</body>
</html>