<?php

session_start();

include "../database/database.php";


// Faculty Check

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty'){

    header("Location: ../login.php");

    exit();

}


$user_id = $_SESSION['user_id'];




// Get Orders


$result = mysqli_query(

    $conn,

    "SELECT * FROM orders

     WHERE user_id='$user_id'

     ORDER BY food_id DESC"

);



?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>My Orders | Faculty | CampusBite</title>



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

📦 My Orders

</h1>


<p>

Track your food orders status

</p>


</div>







<table>


<tr>


<th>

Order ID

</th>


<th>

Food Name

</th>


<th>

Quantity

</th>


<th>

Total Price

</th>


<th>

Status

</th>


<th>

Date

</th>


</tr>







<?php while($row=mysqli_fetch_assoc($result)){ ?>



<tr>


<td>

#<?php echo $row['id']; ?>

</td>




<td>

<?php echo $row['food_name']; ?>

</td>




<td>

<?php echo $row['quantity']; ?>

</td>




<td>

৳<?php echo $row['total_price']; ?>

</td>




<td>


<?php 

$status = $row['status'];

echo $status;

?>



</td>




<td>

<?php echo $row['created_at']; ?>

</td>




</tr>



<?php } ?>



</table>






</div>







<script src="../js/script.js"></script>



</body>


</html>