<?php

session_start();

include "../database/database.php";


// Staff Check

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff'){

    header("Location: ../login.php");

    exit();

}


$message = "";




// Update Order Status


if(isset($_POST['update_order'])){


    $order_id = $_POST['order_id'];

    $status = $_POST['status'];



    $update = mysqli_query(

        $conn,

        "UPDATE orders

         SET status='$status'

         WHERE id='$order_id'"

    );



    if($update){


        $message = "Order status updated successfully!";


    }

    else{


        $message = "Something went wrong!";


    }


}




// Get Order Details


if(isset($_GET['id'])){


    $id = $_GET['id'];



    $result = mysqli_query(

        $conn,

        "SELECT 

        orders.*,

        users.full_name,

        users.email

        FROM orders

        JOIN users

        ON orders.user_id = users.id

        WHERE orders.id='$id'"

    );



    $order = mysqli_fetch_assoc($result);



}



?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Update Order | Staff | CampusBite</title>



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

✏️ Update Order

</h1>


<p>

Change order preparation status

</p>


</div>







<?php if($message!=""){ ?>


<div class="message">

<?php echo $message; ?>

</div>


<?php } ?>








<div class="login-box">



<?php if(isset($order)){ ?>



<h2>

Order #<?php echo $order['id']; ?>

</h2>


<br>



<p>

<b>Customer:</b>

<?php echo $order['full_name']; ?>

</p>



<br>



<p>

<b>Email:</b>

<?php echo $order['email']; ?>

</p>



<br>



<p>

<b>Food:</b>

<?php echo $order['food_name']; ?>

</p>



<br>



<p>

<b>Quantity:</b>

<?php echo $order['quantity']; ?>

</p>



<br>



<p>

<b>Total:</b>

৳<?php echo $order['total_price']; ?>

</p>


<br><br>






<form method="POST">



<input

type="hidden"

name="order_id"

value="<?php echo $order['id']; ?>"

>





<select name="status">



<option value="Pending"

<?php if($order['status']=="Pending") echo "selected"; ?>>

Pending

</option>





<option value="Preparing"

<?php if($order['status']=="Preparing") echo "selected"; ?>>

Preparing

</option>





<option value="Ready"

<?php if($order['status']=="Ready") echo "selected"; ?>>

Ready

</option>





<option value="Served"

<?php if($order['status']=="Served") echo "selected"; ?>>

Served

</option>



</select>





<button

type="submit"

name="update_order">


✏️ Update Status


</button>




</form>




<?php } else { ?>



<p>

No order selected!

</p>



<?php } ?>





</div>







</div>







<script src="../js/script.js"></script>



</body>


</html>