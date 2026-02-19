<?php
session_start();
require_once "../config/database.php";

if(!isset($_SESSION['client_info'])){
    header("Location: index.php");
    exit();
}

if(!isset($_POST['certificates']) || empty($_POST['certificates'])){
    header("Location: select_cert.php?error=Select at least one certificate");
    exit();
}

$client = $_SESSION['client_info'];

// Insert client
$stmt = $conn->prepare("INSERT INTO clients (firstname,middlename,lastname,address,purpose) VALUES (?,?,?,?,?)");
$stmt->bind_param("sssss",$client['firstname'],$client['middlename'],$client['lastname'],$client['address'],$client['purpose']);
$stmt->execute();
$client_id = $stmt->insert_id;
$stmt->close();

// Calculate total and insert request
$total = 0;
$cert_ids = $_POST['certificates'];

foreach($cert_ids as $cid){
    $cid = intval($cid);
    $res = $conn->query("SELECT price FROM certificates WHERE id=$cid");
    if($row=$res->fetch_assoc()) $total += $row['price'];
}

$stmt = $conn->prepare("INSERT INTO requests (client_id,total_amount) VALUES (?,?)");
$stmt->bind_param("id",$client_id,$total);
$stmt->execute();
$request_id = $stmt->insert_id;
$stmt->close();

// Insert request_items
$stmt = $conn->prepare("INSERT INTO request_items (request_id,certificate_id,price_at_time) VALUES (?,?,?)");
foreach($cert_ids as $cid){
    $cid = intval($cid);
    $res = $conn->query("SELECT price FROM certificates WHERE id=$cid");
    if($row=$res->fetch_assoc()){
        $price = $row['price'];
        $stmt->bind_param("iid",$request_id,$cid,$price);
        $stmt->execute();
    }
}
$stmt->close();
$conn->close();

// Clear session
unset($_SESSION['client_info']);

echo "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<title>Request Submitted</title>
<link href='../assets/bootstrap/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
<div class='container mt-5'>
<div class='alert alert-success text-center'>
<h4>Request Submitted Successfully!</h4>
<p>Your request has been recorded. Please wait for Treasury to process your request.</p>
<a href='index.php' class='btn btn-primary'>Back to Start</a>
</div>
</div>
</body>
</html>";
