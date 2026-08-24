<?php

session_start();

include "../database/database.php";


// Staff Check

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){

    header("Location: ../login.php");

    exit();

}





// Served Orders


$result = mysqli_query(

    $conn,

    "SELECT 

    orders.*,

    users.full_name,

    users.email

    FROM orders

    JOIN users

    ON orders.user_id = users.id

    WHERE orders.status='Served'

    ORDER BY orders.food_id DESC"

);



?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Served Orders | Staff | CampusBite</title>



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



<a href="pending_orders.php">

⏳ Pending Orders

</a>



<a href="served_orders.php">

✅ Served Orders

</a>



<a href="update_order.php">

✏️ Update Orders

</a>



<a href="../logout.php">

🚪 Logout

</a>



</div>







<!-- Main Content -->


<div class="main-content">



<div class="welcome">


<h1>

✅ Served Orders

</h1>


<p>

Successfully completed orders

</p>


</div>







<table>


<tr>


<th>

Order ID

</th>


<th>

Customer

</th>


<th>

Email

</th>


<th>

Food

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


</tr>







<?php while($row=mysqli_fetch_assoc($result)){ ?>



<tr>


<td>

#<?php echo $row['id']; ?>

</td>




<td>

<?php echo $row['full_name']; ?>

</td>




<td>

<?php echo $row['email']; ?>

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


<span>

<?php echo $row['status']; ?>

</span>



</td>



</tr>



<?php } ?>



</table>







</div>







<script src="../js/script.js"></script>



</body>


</html>