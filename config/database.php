<?php
$host = "localhost";
$dbname = "db_ao";
$username = "root";
$password = "";

try {
    $conn = new mysqli($host, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed.");
    }

    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    die("System temporarily unavailable.");
}
?>
