<?php

session_start();

include "../database/database.php";

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {

    header("Location: ../login.php");
    exit();
}

// Check food ID
if (!isset($_GET['food_id'])) {

    die("Invalid request.");
}

$food_id = intval($_GET['food_id']);

// Get image name
$image_query = mysqli_query(
    $conn,
    "SELECT image FROM foods WHERE food_id = '$food_id'"
);

if (!$image_query) {

    die(mysqli_error($conn));
}

if (mysqli_num_rows($image_query) > 0) {

    $food = mysqli_fetch_assoc($image_query);

    $image = $food['image'];

    // Delete image file
    if (!empty($image) && file_exists("../assets/images/" . $image)) {

        unlink("../assets/images/" . $image);
    }
}

// Delete food
$delete_query = mysqli_query(
    $conn,
    "DELETE FROM foods WHERE food_id = '$food_id'"
);

if (!$delete_query) {

    die(mysqli_error($conn));
}

// Redirect
header("Location: manage_food.php");
exit();

?>