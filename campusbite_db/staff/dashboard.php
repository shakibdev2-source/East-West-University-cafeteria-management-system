<?php

session_start();

include "../database/database.php";


// Student Check

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student'){

    header("Location: ../login.php");

    exit();

}


$user_id = $_SESSION['user_id'];

$name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Student';




// Total Orders

$order_query = mysqli_query(

    $conn,

    "SELECT COUNT(*) AS total

     FROM orders

     WHERE user_id='$user_id'"

);


$total_orders = mysqli_fetch_assoc($order_query)['total'] ?? 0;





// Cart Items

$cart_query = mysqli_query(

    $conn,

    "SELECT COUNT(*) AS total

     FROM cart

     WHERE user_id='$user_id'"

);


$cart_items = mysqli_fetch_assoc($cart_query)['total'] ?? 0;








$pending_query = mysqli_query(

    $conn,

    "SELECT COUNT(*) AS total

     FROM orders

     WHERE user_id='$user_id'

     AND status='Pending'"

);


$pending_orders = mysqli_fetch_assoc($pending_query)['total'] ?? 0;



?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Student Dashboard | CampusBite</title>

<!-- External CSS Linked -->
<link rel="stylesheet" href="../css/styles.css">


<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
        background-color: #f0f4f8 !important;
        background-image: none !important;
        color: #1e293b;
        min-height: 100vh;
    }

   
    .sidebar {
        width: 250px;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        background-color: #0b1329;
        padding: 25px 15px;
        box-sizing: border-box;
        z-index: 999;
    }

    .sidebar .logo {
        color: #ffffff;
        font-size: 22px;
        font-weight: bold;
        margin-bottom: 25px;
        padding-left: 5px;
    }

    .sidebar a {
        display: block;
        padding: 12px 16px;
        margin-bottom: 10px;
        background-color: #17233d;
        color: #e2e8f0;
        text-decoration: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .sidebar a:hover {
        background-color: #2563eb;
        color: #ffffff;
        transform: translateX(4px);
    }

    /* Main Content Layout */
    .main-content {
        margin-left: 250px;
        padding: 35px;
        box-sizing: border-box;
    }

    /* Welcome Card */
    .welcome {
        background-color: #ffffff;
        padding: 25px 30px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        margin-bottom: 30px;
    }

    .welcome h1 {
        font-size: 24px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .welcome p {
        color: #64748b;
        font-size: 14px;
    }

    /* Cards Layout */
    .cards {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .card {
        flex: 1;
        min-width: 180px;
        padding: 22px;
        color: #ffffff;
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s ease;
    }

    .card:hover {
        transform: translateY(-5px);
    }

    .card h3 {
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 12px;
        opacity: 0.95;
    }

    .card p {
        font-size: 32px;
        font-weight: bold;
        line-height: 1;
    }

    .card.blue { background-color: #2563eb; }
    .card.green { background-color: #10b981; }
    .card.orange { background-color: #f59e0b; }
    .card.red { background-color: #ef4444; }
</style>

</head>



<body>





<!-- Sidebar -->


<div class="sidebar">


<h2 class="logo">

🍔 CampusBite

</h2>



<a href="dashboard.php">

🏠 Dashboard

</a>



<a href="menu.php">

🍽️ Food Menu

</a>



<a href="cart.php">

🛒 My Cart

</a>



<a href="checkout.php">

💳 Checkout

</a>



<a href="orders.php">

📦 My Orders

</a>



<a href="feedback.php">

📝 Feedback

</a>



<a href="../logout.php">

🚪 Logout

</a>



</div>







<!-- Main Content -->


<div class="main-content">



<div class="welcome">


<h1>

Welcome Student,

<?php echo htmlspecialchars($name); ?> 👋

</h1>


<p>

Welcome to CampusBite Student Panel

</p>


</div>







<div class="cards">



<div class="card blue">


<h3>

My Orders

</h3>


<p>

<?php echo $total_orders; ?>

</p>


</div>







<div class="card green">


<h3>

Cart Items

</h3>


<p>

<?php echo $cart_items; ?>

</p>


</div>







<div class="card orange">


<h3>

Pending Orders

</h3>


<p>

<?php echo $pending_orders; ?>

</p>


</div>







<div class="card red">


<h3>

CampusBite

</h3>


<p>

🍔

</p>


</div>



</div>







</div>







<script src="../js/script.js"></script>



</body>


</html>