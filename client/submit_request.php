<?php
session_start();
require_once "../config/database.php";

// === Configuration ===
define('LOG_ERRORS', true);

// === Helper: Log errors securely ===
function logError($message, $context = []) {
    if (!LOG_ERRORS) return;
    $logFile = __DIR__ . '/../logs/error.log';
    @mkdir(dirname($logFile), 0755, true);

    $entry = sprintf(
        "[%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $message,
        empty($context) ? '' : '| Context: ' . json_encode($context)
    );
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

// === Validation: Session & Input ===
if (!isset($_SESSION['client_info']) || !is_array($_SESSION['client_info'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_POST['certificates']) || !is_array($_POST['certificates']) || empty($_POST['certificates'])) {
    header("Location: select_cert.php?error=Please+select+at+least+one+certificate");
    exit();
}

$client = $_SESSION['client_info'];

// Sanitize and validate client data
$firstname  = trim($client['firstname'] ?? '');
$middlename = trim($client['middlename'] ?? '');
$lastname   = trim($client['lastname'] ?? '');
$address    = trim($client['address'] ?? '');
$cp_no      = trim($client['cp_no'] ?? '');
$purpose = $client['purpose'] ?? '';

// Purpose: store as STRING because clients.purpose is VARCHAR(100)
if (is_array($purpose)) {
    $purposeStr = implode(', ', array_values(array_filter($purpose)));
} else {
    $purposeStr = trim((string)$purpose);
}

// ✅ fallback for cert flow
if ($purposeStr === '') {
    $purposeStr = 'Certification Issuance';
}

// Required validation
if (!$firstname || !$lastname || !$address || !$cp_no) {
    logError("Validation failed", ['client' => $client]);
    header("Location: select_cert.php?error=Please+fill+all+required+fields");
    exit();
}

// CP number validation (PH formats)
if (!preg_match('/^(09\d{9}|\+63\d{10})$/', $cp_no)) {
    header("Location: select_cert.php?error=Invalid+contact+number");
    exit();
}

// Sanitize certificate IDs
$certIds = array_values(array_unique(array_map('intval', array_filter($_POST['certificates']))));
if (empty($certIds)) {
    header("Location: select_cert.php?error=Invalid+certificate+selection");
    exit();
}

// === Database Operations with Transaction ===
$txStarted = false;

try {
    $conn->begin_transaction();
    $txStarted = true;

    /* ===============================
       1) INSERT CLIENT (MATCHES clients TABLE)
       clients: firstname, middlename, lastname, address, cp_no, purpose, created_at
    ================================== */
    $stmt = $conn->prepare("
        INSERT INTO clients
        (firstname, middlename, lastname, address, cp_no, purpose, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) throw new Exception("Prepare failed for clients: " . $conn->error);

    $stmt->bind_param("ssssss", $firstname, $middlename, $lastname, $address, $cp_no, $purposeStr);
    $stmt->execute();

    if ($stmt->errno) throw new Exception("Client insert failed: " . $stmt->error);

    $clientId = (int)$stmt->insert_id;
    $stmt->close();

    if ($clientId <= 0) throw new Exception("Client ID not generated.");

    /* ===============================
       2) FETCH CERT PRICES (NO get_result dependency)
    ================================== */
    $total = 0;
    $validCerts = [];

    $placeholders = implode(',', array_fill(0, count($certIds), '?'));
    $stmt = $conn->prepare("
        SELECT id, certificate_name, price
        FROM certificates
        WHERE id IN ($placeholders) AND status='active'
    ");
    if (!$stmt) throw new Exception("Prepare failed for certificates: " . $conn->error);

    $types = str_repeat('i', count($certIds));
    $stmt->bind_param($types, ...$certIds);
    $stmt->execute();

    $stmt->bind_result($cid, $cname, $cprice);
    while ($stmt->fetch()) {
        $total += (float)$cprice;
        $validCerts[] = [
            'id' => (int)$cid,
            'name' => $cname,
            'price' => (float)$cprice
        ];
    }
    $stmt->close();

    if (empty($validCerts)) throw new Exception("No valid active certificates found.");

    /* ===============================
       3) INSERT REQUEST (status enum is UPPERCASE)
       requests: client_id, total_amount, status, created_at
    ================================== */
    $stmt = $conn->prepare("
        INSERT INTO requests
        (client_id, total_amount, status, created_at)
        VALUES (?, ?, 'PENDING', NOW())
    ");
    if (!$stmt) throw new Exception("Prepare failed for requests: " . $conn->error);

    $stmt->bind_param("id", $clientId, $total);
    $stmt->execute();

    if ($stmt->errno) throw new Exception("Request insert failed: " . $stmt->error);

    $requestId = (int)$stmt->insert_id;
    $stmt->close();

    if ($requestId <= 0) throw new Exception("Request ID not generated.");

    /* ===============================
       4) INSERT REQUEST ITEMS (MATCHES request_items TABLE)
       request_items: request_id, certificate_id, price_at_time
    ================================== */
    $stmt = $conn->prepare("
        INSERT INTO request_items
        (request_id, certificate_id, price_at_time)
        VALUES (?, ?, ?)
    ");
    if (!$stmt) throw new Exception("Prepare failed for request_items: " . $conn->error);

    foreach ($validCerts as $cert) {
        $stmt->bind_param("iid", $requestId, $cert['id'], $cert['price']);
        $stmt->execute();
        if ($stmt->errno) throw new Exception("Request item insert failed: " . $stmt->error);
    }
    $stmt->close();

    // ✅ Commit transaction
    $conn->commit();
    $txStarted = false;

    // === Prepare Success Data ===
    $successData = [
        'request_id'   => $requestId,
        'client_name'  => htmlspecialchars("$firstname $lastname"),
        'cp_no'        => htmlspecialchars($cp_no),
        'purpose'      => $purposeStr,
        'total_amount' => number_format($total, 2),
        'certificates' => array_column($validCerts, 'name'),
        'reference'    => 'REQ-' . strtoupper(substr(md5($requestId . time()), 0, 8))
    ];

    // Clear sensitive session data
    unset($_SESSION['client_info']);

} catch (Exception $e) {
    if ($txStarted) {
        $conn->rollback();
        $txStarted = false;
    }

    logError("Submission failed", [
        'error'  => $e->getMessage(),
        'client' => $firstname . ' ' . $lastname,
        'trace'  => $e->getTraceAsString()
    ]);

    header("Location: select_cert.php?error=Submission+failed.+Please+try+again.");
    exit();
} finally {
    if ($conn) $conn->close();
}

unset($_SESSION['qr_items'], $_SESSION['qr_cert_labels'], $_SESSION['qr_prefill_cert_ids']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Submitted | Certificate System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
    <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<div class="success-wrapper">
    <div class="success-card card shadow-lg border-0">
        <div class="card-body p-4 p-md-5 text-center">

            <div class="success-icon">
                <i class="bi bi-check-lg"></i>
            </div>

            <h4 class="fw-bold mb-2">Request Submitted!</h4>
            <p class="text-muted mb-4">Your certificate request has been successfully recorded.</p>

            <div class="mb-4">
                <small class="text-muted d-block mb-1">Reference Number</small>
                <span class="badge reference-badge fs-6 px-3 py-2">
                    <?php echo $successData['reference']; ?>
                </span>
            </div>

            <div class="text-start mb-4">
                <div class="summary-row">
                    <span class="text-muted">Applicant</span>
                    <span class="fw-semibold"><?php echo $successData['client_name']; ?></span>
                </div>
                <div class="summary-row">
                    <span class="text-muted">Contact No.</span>
                    <span class="fw-semibold"><?php echo $successData['cp_no']; ?></span>
                </div>
                <div class="summary-row">
                    <span class="text-muted">Purpose</span>
                    <span class="fw-semibold"><?php echo htmlspecialchars($successData['purpose']); ?></span>
                </div>
                <div class="summary-row">
                    <span class="text-muted">Certificates</span>
                    <span class="fw-semibold"><?php echo count($successData['certificates']); ?> item(s)</span>
                </div>
                <div class="summary-row">
                    <span class="text-muted">Total Amount</span>
                    <span class="fw-bold text-primary fs-5">₱<?php echo $successData['total_amount']; ?></span>
                </div>
            </div>

            <details class="mb-4 text-start">
                <summary class="summary-toggle">
                    <i class="bi bi-list-ul me-1"></i>View selected certificates
                </summary>
                <div class="cert-list mt-2">
                    <?php foreach ($successData['certificates'] as $cert): ?>
                        <div class="cert-item-row">
                            <i class="bi bi-check-circle-fill text-success me-2 small"></i>
                            <small><?php echo htmlspecialchars($cert); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>

            <div class="alert alert-info mb-4 py-2 small">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Next:</strong> Treasury will review your request. You'll be notified via SMS once processed.
            </div>

            <div class="d-grid gap-2">
                <a href="../index.php" class="btn btn-home btn-lg">
                    <i class="bi bi-house me-2"></i>Back to Start
                </a>
            </div>

        </div>
        <div class="card-footer-custom"></div>
    </div>

    <div class="bg-decoration"></div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js" defer></script>
<script src="../assets/js/submit_success.js" defer></script>

</body>
</html>