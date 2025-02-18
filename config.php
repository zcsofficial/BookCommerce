<?php
$host = "localhost"; // Change if needed
$user = "adnan"; // Your DB username
$password = "Adnan@66202"; // Your DB password
$dbname = "bookcommerce"; // Your DB name

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
