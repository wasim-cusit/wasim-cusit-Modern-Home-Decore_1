<?php
$host = "localhost"; // or the actual host provided by your hosting service
$user = "root";
$password = "";
$dbname = "u742242489_decore"; // match the full database name if needed

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
