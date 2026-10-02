<?php
$servername = "192.168.7.5";
$username = "root";
$password = "";
$dbname = "transfer_wrhs";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
