<?php

session_start();

include "../database/database.php";


// Faculty Check

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty'){

    header("Location: ../login.php");

    exit();

}


$name = $_SESSION['name'];

$user_id = $_SESSION['user_id'];




// Total Orders

$order_query = mysqli_query(

    $conn,

    "SELECT COUNT(*) AS total 

     FROM orders 

     WHERE user_id='$user_id'"

);


$total_orders = mysqli_fetch_assoc($order_query)['total'];





// Cart Items

$cart_query = mysqli_query(

    $conn,

    "SELECT COUNT(*) AS total 

     FROM cart 

     WHERE user_id='$user_id'"

);


$cart_items = mysqli_fetch_assoc($cart_query)['total'];






// Pending Orders

$pending_query = mysqli_query(

    $conn,

    "SELECT COUNT(*) AS total 

     FROM orders 

     WHERE user_id='$user_id'

     AND status='Pending'"

);


$pending_orders = mysqli_fetch_assoc($pending_query)['total'];



?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Faculty Dashboard | CampusBite</title>



<!-- CSS -->

<link rel="stylesheet" href="../css/style.css">



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

Welcome Faculty,

<?php echo $name; ?> 👋

</h1>


<p>

Welcome to Faculty Panel - CampusBite

</p>


</div>







<div class="cards">



<div class="card blue">


<h3>

Total Orders

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