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




// Add To Cart


if(isset($_POST['add_to_cart'])){


    $food_id = $_POST['food_id'];

    $quantity = $_POST['quantity'];



    // Check already exists


    $check = mysqli_query(

        $conn,

        "SELECT * FROM cart

         WHERE user_id='$user_id'

         AND food_id='$food_id'"

    );




    if(mysqli_num_rows($check)>0){



        mysqli_query(

            $conn,

            "UPDATE cart

             SET quantity = quantity + '$quantity'

             WHERE user_id='$user_id'

             AND food_id='$food_id'"

        );



    }

    else{


        mysqli_query(

            $conn,

            "INSERT INTO cart

            (user_id,food_id,quantity)

            VALUES

            ('$user_id',

            '$food_id',

            '$quantity')"

        );


    }



    $message="Food added to cart!";



}







// Get Foods


$foods = mysqli_query(

    $conn,

    "SELECT * FROM foods ORDER BY food_id DESC"

);



?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Food Menu | Faculty | CampusBite</title>



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

🍽️ Food Menu

</h1>


<p>

Choose your favourite food

</p>


</div>








<?php if($message!=""){ ?>


<div class="message">

<?php echo $message; ?>

</div>


<?php } ?>







<div class="food-container">






<?php while($row=mysqli_fetch_assoc($foods)){ ?>





<div class="food-card">



<img

src="../assets/images/<?php echo $row['image']; ?>"

alt="<?php echo $row['food_name']; ?>"

>




<h3>

<?php echo $row['food_name']; ?>

</h3>



<p>

Category:

<?php echo $row['category']; ?>

</p>




<h3>

৳<?php echo $row['price']; ?>

</h3>







<form method="POST">



<input

type="hidden"

name="food_id"

value="<?php echo $row['food_id']; ?>"

>




<input

type="number"

name="quantity"

value="1"

min="1"

required

>





<button

type="submit"

name="add_to_cart">


🛒 Add To Cart


</button>



</form>






</div>






<?php } ?>





</div>







</div>








<script src="../js/script.js"></script>



</body>


</html>