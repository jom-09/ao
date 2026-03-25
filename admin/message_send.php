<?php
ob_start();
require_once "../includes/auth_check.php";
require_once "../config/database.php";

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $sender_role   = 'ADMIN';
    $receiver_role = strtoupper(trim($_POST['receiver_role'] ?? ''));
    $message       = trim($_POST['message'] ?? '');

    if ($receiver_role !== 'TREASURY') {
        throw new Exception('Invalid receiver role.');
    }

    if ($message === '') {
        throw new Exception('Message cannot be empty.');
    }

    $stmt = $conn->prepare("
        INSERT INTO messages (sender_role, receiver_role, message, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("sss", $sender_role, $receiver_role, $message);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully.'
    ]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;