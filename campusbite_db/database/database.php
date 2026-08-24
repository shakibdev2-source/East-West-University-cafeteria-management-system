<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "campusbite_db";

// Database connection

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);

// Connection check

if (!$conn) {

    die("Database connection failed: " . mysqli_connect_error());

}

?>