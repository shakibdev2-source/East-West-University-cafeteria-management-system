<?php

session_start();

include "../database/database.php";


// Admin Check

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){

    header("Location: ../login.php");

    exit();

}



if(!isset($_GET['food_id'])){

    header("Location: manage_food.php");

    exit();

}


$food_id = $_GET['food_id'];


// Fetch food data


$result = mysqli_query(

    $conn,

    "SELECT * FROM foods WHERE food_id='$food_id'"

);



$food = mysqli_fetch_assoc($result);



$message = "";




// Update Food


if(isset($_POST['update_food'])){


    $name = mysqli_real_escape_string(

        $conn,

        $_POST['food_name']

    );


    $category = mysqli_real_escape_string(

        $conn,

        $_POST['category']

    );


    $price = $_POST['price'];



    $old_image = $food['image'];



    // If new image selected


    if($_FILES['image']['name'] != ""){


        $image = $_FILES['image']['name'];

        $tmp = $_FILES['image']['tmp_name'];



        move_uploaded_file(

            $tmp,

            "../assets/images/".$image

        );



        // Delete old image


        if(file_exists("../assets/images/".$old_image)){


            unlink("../assets/images/".$old_image);


        }



    }

    else{


        $image = $old_image;


    }




    $update = mysqli_query(

        $conn,

        "UPDATE foods SET

        food_name='$name',

        category='$category',

        price='$price',

        image='$image'

        WHERE food_id='$food_id'"

    );



    if($update){


        $message="Food Updated Successfully!";


        header("refresh:1;url=manage_food.php");


    }

    else{


        $message="Update Failed!";


    }


}


?>



<!DOCTYPE html>

<html lang="en">

<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Edit Food | CampusBite</title>



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



<a href="add_food.php">

➕ Add Food

</a>



<a href="manage_food.php">

🍽️ Manage Food

</a>



<a href="manage_orders.php">

📦 Manage Orders

</a>



<a href="manage_staff.php">

👨‍🍳 Manage Staff

</a>



<a href="reports.php">

📊 Reports

</a>



<a href="settings.php">

⚙️ Settings

</a>



<a href="../logout.php">

🚪 Logout

</a>



</div>







<!-- Main Content -->


<div class="main-content">



<div class="welcome">


<h1>

✏️ Edit Food

</h1>


<p>

Update cafeteria food information

</p>


</div>






<?php if($message!=""){ ?>


<div class="message">

<?php echo $message; ?>

</div>


<?php } ?>







<div class="login-box">



<form method="POST" enctype="multipart/form-data">



<input

type="text"

name="food_name"

value="<?php echo $food['food_name']; ?>"

required

>




<input

type="text"

name="category"

value="<?php echo $food['category']; ?>"

required

>




<input

type="number"

name="price"

value="<?php echo $food['price']; ?>"

required

>




<p>

Current Image:

</p>


<img 

src="../assets/images/<?php echo $food['image']; ?>"

width="120"

style="border-radius:10px; margin:10px 0;"

>




<input

type="file"

name="image"

>




<button

type="submit"

name="update_food">


✏️ Update Food

</button>



</form>



</div>



</div>





<script src="../js/script.js"></script>


</body>

</html>