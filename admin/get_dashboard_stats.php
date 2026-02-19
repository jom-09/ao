<?php
require_once "../config/database.php";
require_once "../includes/auth_check.php";

header('Content-Type: application/json');

$stats = [
    'pending' => 0,
    'paid' => 0,
    'prepared' => 0
];

// Get pending requests count
$result = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='PENDING'");
if ($result) {
    $stats['pending'] = $result->fetch_assoc()['count'];
}

// Get paid requests count
$result = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='PAID'");
if ($result) {
    $stats['paid'] = $result->fetch_assoc()['count'];
}

// Get prepared requests count
$result = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='PREPARED'");
if ($result) {
    $stats['prepared'] = $result->fetch_assoc()['count'];
}

echo json_encode($stats);
$conn->close();
?>