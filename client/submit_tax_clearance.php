<?php
session_start();
require_once "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?error=Invalid request");
    exit();
}

$firstname  = trim($_POST['firstname'] ?? '');
$middlename = trim($_POST['middlename'] ?? '');
$lastname   = trim($_POST['lastname'] ?? '');
$purpose    = trim($_POST['purpose'] ?? 'Tax Clearance');

$address = trim($_SESSION['client_info']['address'] ?? '');
$cp_no   = trim($_SESSION['client_info']['cp_no'] ?? '');

if ($address === '') $address = 'N/A';
if ($cp_no === '')   $cp_no   = 'N/A';

if ($firstname === '' || $lastname === '') {
    header("Location: index.php?error=First name and last name are required");
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO clients (firstname, middlename, lastname, address, cp_no, purpose)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("ssssss", $firstname, $middlename, $lastname, $address, $cp_no, $purpose);

if ($stmt->execute()) {
    header("Location: index.php?success=Tax Clearance request submitted successfully");
    exit();
} else {
    header("Location: index.php?error=Failed to submit request");
    exit();
}
?>