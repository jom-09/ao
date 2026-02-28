<?php
session_start();
require_once "../config/database.php";

$ref = isset($_GET['ref']) ? (int)$_GET['ref'] : 0;
if ($ref <= 0) {
    header("Location: select_service.php");
    exit();
}

/* Fetch request + client */
$stmt = $conn->prepare("
    SELECT 
        r.id,
        r.total_amount,
        r.status,
        r.control_number,
        r.created_at,
        c.firstname,
        c.middlename,
        c.lastname,
        c.address,
        c.cp_no
    FROM requests r
    INNER JOIN clients c ON c.id = r.client_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $ref);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();
$stmt->close();

if (!$request) {
    header("Location: select_service.php?error=Invalid+reference");
    exit();
}

/* Fetch requested services */
$stmt = $conn->prepare("
    SELECT 
        s.service_name,
        s.description,
        rs.price_at_time,
        rs.created_at
    FROM requested_services rs
    INNER JOIN services s ON s.id = rs.service_id
    WHERE rs.request_id = ?
    ORDER BY rs.id ASC
");
$stmt->bind_param("i", $ref);
$stmt->execute();
$services = $stmt->get_result();
$stmt->close();

$fullname = trim(
    ($request['firstname'] ?? '') . ' ' .
    (!empty($request['middlename']) ? $request['middlename'] . ' ' : '') .
    ($request['lastname'] ?? '')
);

/* Create a nice reference string (safe fallback)
   If you already have a control number format, we’ll use it.
*/
$reference = '';
if (!empty($request['control_number'])) {
    $reference = (string)$request['control_number'];
} else {
    // similar vibe to submit_request.php reference
    $reference = 'SRV-' . strtoupper(substr(md5($request['id'] . '|' . $request['created_at']), 0, 8));
}

/* Badge color based on status (optional, still uses your existing CSS + bootstrap) */
$status = strtoupper((string)($request['status'] ?? 'PENDING'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Submitted | Service System</title>
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
            <p class="text-muted mb-4">Your service request has been successfully recorded.</p>

            <div class="mb-4">
                <small class="text-muted d-block mb-1">Reference Number</small>
                <span class="badge reference-badge fs-6 px-3 py-2">
                    <?php echo htmlspecialchars($reference); ?>
                </span>
            </div>

            <div class="text-start mb-4">
                <div class="summary-row">
                    <span class="text-muted">Applicant</span>
                    <span class="fw-semibold"><?php echo htmlspecialchars($fullname); ?></span>
                </div>

                <div class="summary-row">
                    <span class="text-muted">Contact No.</span>
                    <span class="fw-semibold"><?php echo htmlspecialchars((string)$request['cp_no']); ?></span>
                </div>

                <div class="summary-row">
                    <span class="text-muted">Address</span>
                    <span class="fw-semibold"><?php echo htmlspecialchars((string)$request['address']); ?></span>
                </div>

                <div class="summary-row">
                    <span class="text-muted">Status</span>
                    <span class="fw-semibold">
                        <?php echo htmlspecialchars((string)$request['status']); ?>
                    </span>
                </div>

                <div class="summary-row">
                    <span class="text-muted">Services</span>
                    <span class="fw-semibold"><?php echo (int)$services->num_rows; ?> item(s)</span>
                </div>

                <div class="summary-row">
                    <span class="text-muted">Total Amount</span>
                    <span class="fw-bold text-primary fs-5">
                        ₱<?php echo number_format((float)$request['total_amount'], 2); ?>
                    </span>
                </div>

                <div class="summary-row">
                    <span class="text-muted">Date Submitted</span>
                    <span class="fw-semibold">
                        <?php echo htmlspecialchars(date("M Y, d h:i A", strtotime($request['created_at']))); ?>
                    </span>
                </div>
            </div>

            <details class="mb-4 text-start">
                <summary class="summary-toggle">
                    <i class="bi bi-list-ul me-1"></i>View selected services
                </summary>

                <div class="cert-list mt-2">
                    <?php if ($services->num_rows > 0): ?>
                        <?php while ($row = $services->fetch_assoc()): ?>
                            <div class="cert-item-row">
                                <i class="bi bi-check-circle-fill text-success me-2 small"></i>
                                <small class="d-block">
                                    <strong><?php echo htmlspecialchars($row['service_name']); ?></strong>
                                    <?php if (!empty($row['description'])): ?>
                                        <span class="text-muted"> — <?php echo htmlspecialchars($row['description']); ?></span>
                                    <?php endif; ?>
                                    <span class="text-muted">
                                        (₱<?php echo number_format((float)$row['price_at_time'], 2); ?>)
                                    </span>
                                </small>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="cert-item-row">
                            <i class="bi bi-info-circle text-muted me-2 small"></i>
                            <small class="text-muted">No services found for this request.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </details>

            <div class="alert alert-info mb-4 py-2 small">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Next:</strong> Please wait for confirmation. You’ll be notified once processed.
            </div>

            <div class="d-grid gap-2">
                <a href="index.php" class="btn btn-home btn-lg">
                    <i class="bi bi-house me-2"></i>Back to Home
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