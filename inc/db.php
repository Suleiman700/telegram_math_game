<?php


$servername = "localhost";
$username = "db_username";
$password = "db_password";
$db = "db_name";

$conn = null;
try {
    $conn = new PDO("mysql:host=$servername;dbname=$db;charset=utf8", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

?>
