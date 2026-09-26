<?php

session_start();

include "../database/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['food_id'])) {
    die("Invalid request.");
}

$food_id = intval($_GET['food_id']);

$stmt = mysqli_prepare($conn, "SELECT image FROM foods WHERE food_id = ?");
mysqli_stmt_bind_param($stmt, "i", $food_id);
mysqli_stmt_execute($stmt);
$image_query = mysqli_stmt_get_result($stmt);

if (!$image_query) {
    die(mysqli_error($conn));
}

if (mysqli_num_rows($image_query) > 0) {
    $food = mysqli_fetch_assoc($image_query);
    $image = $food['image'];

    if (!empty($image) && file_exists("../assets/images/" . $image)) {
        unlink("../assets/images/" . $image);
    }
}
mysqli_stmt_close($stmt);

$delete_stmt = mysqli_prepare($conn, "DELETE FROM foods WHERE food_id = ?");
mysqli_stmt_bind_param($delete_stmt, "i", $food_id);
$delete_query = mysqli_stmt_execute($delete_stmt);

if (!$delete_query) {
    die(mysqli_error($conn));
}
mysqli_stmt_close($delete_stmt);

header("Location: manage_food.php");
exit();

?>