<?php
require_once "../config/database.php";
header('Content-Type: application/json');

// count pending TAX requests
$tax = (int)$conn->query("SELECT COUNT(*) AS c FROM tax_requests WHERE status='PENDING'")
                 ->fetch_assoc()['c'];

// count pending normal requests (cert/service)
$pending = (int)$conn->query("SELECT COUNT(*) AS c FROM requests WHERE status='PENDING'")
                     ->fetch_assoc()['c'];

echo json_encode([
  "tax" => $tax,
  "pending" => $pending
]);