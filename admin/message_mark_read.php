<?php
require_once "../includes/auth_check.php";
require_once "../config/database.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $current_role = 'ADMIN';

    $stmt = $conn->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE receiver_role = ? AND is_read = 0
    ");
    $stmt->bind_param("s", $current_role);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}