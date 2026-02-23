<?php
session_start();
require_once "../config/database.php";

// DEV (remove later)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

/* ===============================
   GUARDS
================================== */
if (!isset($_SESSION['client_info'], $_SESSION['svc_cart'])) {
    header("Location: index.php");
    exit();
}

$client = $_SESSION['client_info'];
$cart   = $_SESSION['svc_cart'];

$serviceIds = $cart['ids'] ?? [];
$total      = isset($cart['total']) ? (float)$cart['total'] : 0.0;

if (empty($serviceIds)) {
    header("Location: select_service.php?error=Cart+empty");
    exit();
}

/* ===============================
   CLIENT DATA
================================== */
$firstname  = trim($client['firstname'] ?? '');
$middlename = trim($client['middlename'] ?? '');
$lastname   = trim($client['lastname'] ?? '');
$address    = trim($client['address'] ?? '');
$cp_no      = trim($client['cp_no'] ?? '');

if (!$firstname || !$lastname || !$address || !$cp_no) {
    header("Location: index.php?error=Please+fill+all+required+fields");
    exit();
}

if (!preg_match('/^(09\d{9}|\+63\d{10})$/', $cp_no)) {
    header("Location: index.php?error=Invalid+contact+number");
    exit();
}

$stmt = null;
$insert = null;

try {
    $conn->begin_transaction();

    /* 1) INSERT CLIENT */
    $purpose = "Services";

    $stmt = $conn->prepare("
        INSERT INTO clients
        (firstname, middlename, lastname, address, cp_no, purpose, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("ssssss", $firstname, $middlename, $lastname, $address, $cp_no, $purpose);
    $stmt->execute();
    $clientId = $stmt->insert_id;
    $stmt->close();
    $stmt = null;

    /* 2) INSERT REQUEST HEADER (requests table) */
    $stmt = $conn->prepare("
        INSERT INTO requests
        (client_id, total_amount, status, created_at)
        VALUES (?, ?, 'PENDING', NOW())
    ");
    $stmt->bind_param("id", $clientId, $total);
    $stmt->execute();
    $requestId = $stmt->insert_id;
    $stmt->close();
    $stmt = null;

    /* 3) FETCH SERVICES (buffer results to avoid out-of-sync) */
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $types = str_repeat('i', count($serviceIds));

    $stmt = $conn->prepare("
        SELECT id, price
        FROM services
        WHERE id IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$serviceIds);
    $stmt->execute();

    // buffer result set (prevents Commands out of sync)
    $stmt->store_result();

    $stmt->bind_result($sid, $price);

    /* 4) INSERT requested_services details */
    $insert = $conn->prepare("
        INSERT INTO requested_services
        (request_id, service_id, price_at_time)
        VALUES (?, ?, ?)
    ");

    // bind once, execute many
    $insert->bind_param("iid", $requestId, $sid, $price);

    while ($stmt->fetch()) {
        $insert->execute();
    }

    $insert->close();
    $insert = null;

    $stmt->close();
    $stmt = null;

    $conn->commit();

    unset($_SESSION['svc_cart'], $_SESSION['client_info']);

    header("Location: success_service.php?ref=" . $requestId);
    exit();

} catch (Throwable $e) {

    // Close statements first, then rollback (avoids out-of-sync)
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    if ($insert instanceof mysqli_stmt) { $insert->close(); }

    try { $conn->rollback(); } catch (Throwable $rollbackErr) {}

    // DEV only (remove later)
    die("DB ERROR: " . $e->getMessage());
}