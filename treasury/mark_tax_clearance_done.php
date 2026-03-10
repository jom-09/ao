<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    $clientId = (int)($_POST['id'] ?? 0);
    if ($clientId <= 0) {
        throw new Exception('Invalid client ID.');
    }

    $stmt = $conn->prepare("
        SELECT id, purpose
        FROM clients
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $res = $stmt->get_result();
    $client = $res->fetch_assoc();

    if (!$client) {
        throw new Exception('Client not found.');
    }

    if (trim((string)$client['purpose']) !== 'Tax Clearance') {
        throw new Exception('This record is not a Tax Clearance request.');
    }

    $chk = $conn->prepare("
        SELECT id
        FROM requests
        WHERE client_id = ?
          AND status = 'PAID'
        LIMIT 1
    ");
    $chk->bind_param("i", $clientId);
    $chk->execute();
    $chkRes = $chk->get_result();

    if ($chkRes->fetch_assoc()) {
        echo json_encode([
            'ok' => true,
            'message' => 'Already marked as done.'
        ]);
        exit;
    }

    $controlNo = 'TC-' . date('YmdHis') . '-' . $clientId;

    $ins = $conn->prepare("
        INSERT INTO requests (client_id, total_amount, control_number, status, paid_at, created_at)
        VALUES (?, 0.00, ?, 'PAID', NOW(), NOW())
    ");
    $ins->bind_param("is", $clientId, $controlNo);
    $ins->execute();

    echo json_encode([
        'ok' => true,
        'message' => 'Tax Clearance marked as done.'
    ]);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}