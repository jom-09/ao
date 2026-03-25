<?php
ob_start();
require_once "../includes/auth_check.php";
require_once "../config/database.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $current_role = 'ADMIN';
    $markRead = isset($_GET['mark_read']) && $_GET['mark_read'] == '1';

    if ($markRead) {
        $stmtRead = $conn->prepare("
            UPDATE messages
            SET is_read = 1
            WHERE receiver_role = ? AND is_read = 0
        ");
        if (!$stmtRead) {
            throw new Exception('Prepare read failed: ' . $conn->error);
        }
        $stmtRead->bind_param("s", $current_role);
        $stmtRead->execute();
        $stmtRead->close();
    }

    $stmt = $conn->prepare("
        SELECT 
            id,
            sender_role,
            receiver_role,
            message,
            DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') AS created_at,
            is_read
        FROM messages
        WHERE 
            (sender_role='ADMIN' AND receiver_role='TREASURY')
            OR
            (sender_role='TREASURY' AND receiver_role='ADMIN')
        ORDER BY id ASC
        LIMIT 100
    ");

    if (!$stmt) {
        throw new Exception('Prepare fetch failed: ' . $conn->error);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $messages = [];
    while ($row = $res->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();

    $stmtUnread = $conn->prepare("
        SELECT COUNT(*) AS unread_count
        FROM messages
        WHERE receiver_role = ? AND is_read = 0
    ");

    if (!$stmtUnread) {
        throw new Exception('Prepare unread failed: ' . $conn->error);
    }

    $stmtUnread->bind_param("s", $current_role);
    $stmtUnread->execute();
    $unreadRes = $stmtUnread->get_result()->fetch_assoc();
    $unread = (int)$unreadRes['unread_count'];
    $stmtUnread->close();

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'unread_count' => $unread
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'messages' => [],
        'unread_count' => 0
    ]);
}
exit;