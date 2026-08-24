<?php

session_start();

include "../database/database.php";


// Faculty Check

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'faculty'){

    header("Location: ../login.php");

    exit();

}


$user_id = $_SESSION['user_id'];

$message = "";




// Place Order


if(isset($_POST['place_order'])){


    $cart_query = mysqli_query(

        $conn,

        "SELECT 

        cart.*,

        foods.food_name,

        foods.price

        FROM cart

        JOIN foods

        ON cart.food_id = foods.id

        WHERE cart.user_id='$user_id'"

    );



    while($item=mysqli_fetch_assoc($cart_query)){



        $food_name = $item['food_name'];

        $quantity = $item['quantity'];

        $total_price = $item['price'] * $quantity;




        mysqli_query(

            $conn,

            "INSERT INTO orders

            (user_id,food_name,quantity,total_price,status)

            VALUES

            ('$user_id',

            '$food_name',

            '$quantity',

            '$total_price',

            'Pending')"

        );



    }





    // Clear Cart


    mysqli_query(

        $conn,

        "DELETE FROM cart WHERE user_id='$user_id'"

    );



    $message = "Order placed successfully!";



}







// Cart Data


$result = mysqli_query(

    $conn,

    "SELECT 

    cart.*,

    foods.food_name,

    foods.price

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


<title>Checkout | Faculty | CampusBite</title>



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

💳 Checkout

</h1>


<p>

Confirm your order

</p>


</div>







<?php if($message!=""){ ?>


<div class="message">

<?php echo $message; ?>

</div>


<?php } ?>







<table>


<tr>


<th>

Food Name

</th>


<th>

Quantity

</th>


<th>

Price

</th>


<th>

Subtotal

</th>


</tr>






<?php while($row=mysqli_fetch_assoc($result)){ 



$subtotal = $row['price'] * $row['quantity'];

$total += $subtotal;


?>



<tr>


<td>

<?php echo $row['food_name']; ?>

</td>



<td>

<?php echo $row['quantity']; ?>

</td>



<td>

৳<?php echo $row['price']; ?>

</td>



<td>

৳<?php echo $subtotal; ?>

</td>



</tr>



<?php } ?>



</table>






<br>



<div class="welcome">


<h2>

Total Payable:

৳<?php echo $total; ?>

</h2>



<br>




<form method="POST">


<button

type="submit"

name="place_order">


✅ Place Order


</button>



</form>



</div>







</div>







<script src="../js/script.js"></script>



</body>


</html>