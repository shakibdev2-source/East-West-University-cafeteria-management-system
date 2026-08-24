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





// Submit Feedback


if(isset($_POST['submit_feedback'])){


    $feedback = mysqli_real_escape_string(

        $conn,

        $_POST['feedback']

    );



    mysqli_query(

        $conn,

        "INSERT INTO feedback

        (user_id,feedback)

        VALUES

        ('$user_id','$feedback')"

    );



    $message = "Feedback submitted successfully!";


}






// Get Feedback


$result = mysqli_query(

    $conn,

    "SELECT * FROM feedback

     WHERE user_id='$user_id'

     ORDER BY user_id DESC"

);



?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Feedback | Faculty | CampusBite</title>



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

📝 Feedback

</h1>


<p>

Share your experience with CampusBite

</p>


</div>








<?php if($message!=""){ ?>


<div class="message">

<?php echo $message; ?>

</div>


<?php } ?>







<div class="login-box">



<h2>

Send Feedback

</h2>


<br>



<form method="POST">



<textarea

name="feedback"

rows="5"

placeholder="Write your feedback..."

required></textarea>




<button

type="submit"

name="submit_feedback">


📩 Submit Feedback


</button>



</form>



</div>







<br><br>






<h2>

Your Previous Feedback

</h2>



<br>





<table>


<tr>


<th>

Feedback

</th>


<th>

Date

</th>


</tr>






<?php while($row=mysqli_fetch_assoc($result)){ ?>



<tr>


<td>

<?php echo $row['message']; ?>

</td>

<td>

<?php echo $row['rating']; ?>

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