<?php

session_start();

include "../database/database.php";


// Faculty Check

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty'){

    header("Location: ../login.php");

    exit();

}


$user_id = $_SESSION['user_id'];




// Remove Cart Item


if(isset($_GET['remove'])){


    $cart_id = $_GET['remove'];


    mysqli_query(

        $conn,

        "DELETE FROM cart 

         WHERE cart_id='$cart_id'

         AND user_id='$user_id'"

    );


    header("Location: cart.php");

    exit();

}




// Get Cart Items


$result = mysqli_query(

    $conn,

    "SELECT 

    cart.*,

    foods.food_name,

    foods.price,

    foods.image

    FROM cart

    JOIN foods

    ON cart.food_id = foods.food_id

    WHERE cart.user_id='$user_id'"

);



$total = 0;


?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Faculty Cart | CampusBite</title>



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

🛒 My Cart

</h1>


<p>

Review your selected food items

</p>


</div>







<table>


<tr>


<th>

Image

</th>


<th>

Food Name

</th>


<th>

Price

</th>


<th>

Quantity

</th>


<th>

Subtotal

</th>


<th>

Action

</th>


</tr>






<?php while($row=mysqli_fetch_assoc($result)){ 



$subtotal = $row['price'] * $row['quantity'];

$total += $subtotal;


?>



<tr>


<td>


<img 

src="../assets/images/<?php echo $row['image']; ?>"

width="80"

height="60"

style="object-fit:cover;border-radius:8px;"

>


</td>




<td>

<?php echo $row['food_name']; ?>

</td>




<td>

৳<?php echo $row['price']; ?>

</td>




<td>

<?php echo $row['quantity']; ?>

</td>




<td>

৳<?php echo $subtotal; ?>

</td>




<td>


<a 

class="btn"

href="cart.php?remove=<?php echo $row['id']; ?>"

onclick="return confirm('Remove this item from cart?')">


🗑️ Remove


</a>



</td>



</tr>



<?php } ?>





</table>






<br>




<div class="welcome">


<h2>

Total Amount:

৳<?php echo $total; ?>

</h2>




<br>




<a 

class="btn"

href="checkout.php">


💳 Proceed to Checkout


</a>



</div>







</div>







<script src="../js/script.js"></script>



</body>


</html>