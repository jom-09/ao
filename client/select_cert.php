<?php
session_start();
require_once "../config/database.php";

/* ===============================
   SAVE CLIENT INFO FROM PREVIOUS PAGE
================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname  = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $lastname   = trim($_POST['lastname'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $cp_no      = trim($_POST['cp_no'] ?? '');

    // Handle multi-select purpose (always array)
    $purposeInput = $_POST['purpose'] ?? [];
    $purpose = is_array($purposeInput) ? $purposeInput : [$purposeInput];
    $purpose = array_values(array_filter($purpose));

    // Required validation
    if (!$firstname || !$lastname || !$address || !$cp_no || empty($purpose)) {
        header("Location: index.php?error=Please+fill+all+required+fields");
        exit();
    }

    // CP number validation (PH formats)
    if (!preg_match('/^(09\d{9}|\+63\d{10})$/', $cp_no)) {
        header("Location: index.php?error=Invalid+contact+number");
        exit();
    }

    $_SESSION['client_info'] = [
        'firstname'  => $firstname,
        'middlename' => $middlename,
        'lastname'   => $lastname,
        'address'    => $address,
        'cp_no'      => $cp_no,
        'purpose'    => $purpose
    ];

} else {
    // Direct access guard
    if (!isset($_SESSION['client_info']) || !is_array($_SESSION['client_info'])) {
        header("Location: index.php");
        exit();
    }
}

/* ===============================
   FETCH ACTIVE CERTIFICATES
================================== */
$certificates = [];
$sql = "SELECT * FROM certificates WHERE status='active' ORDER BY certificate_name ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $certificates[] = $row;
    }
}

$client = $_SESSION['client_info'];
$purposeDisplay = implode(', ', $client['purpose']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Certificates | Certificate System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Framework CSS -->
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
    <link href="../assets/bootstrap/css/style.css" rel="stylesheet">

    <!-- Bootstrap Icons (if CSP blocks CDN, download locally later) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<div class="cert-wrapper">

    <!-- Progress Steps -->
    <div class="progress-container">
        <div class="progress-step completed" data-step="1">
            <div class="step-circle"><i class="bi bi-check-lg"></i></div>
            <span class="step-label">Info</span>
        </div>
        <div class="progress-line active"></div>
        <div class="progress-step active" data-step="2">
            <div class="step-circle">2</div>
            <span class="step-label">Certificates</span>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="3">
            <div class="step-circle">3</div>
            <span class="step-label">Confirm</span>
        </div>
    </div>

    <div class="cert-card card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">

            <!-- Header -->
            <div class="text-center mb-4">
                <div class="brand-icon mb-3">
                    <i class="bi bi-patch-check fs-1"></i>
                </div>
                <h4 class="fw-bold mb-1">Select Certificates</h4>
                <p class="text-muted small">Choose the certificates you need</p>
            </div>

            <!-- Client Summary -->
            <div class="client-summary card bg-primary-subtle border-0 mb-4">
                <div class="card-body py-3 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-circle">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-semibold">
                                <?php echo htmlspecialchars($client['firstname'] . ' ' . $client['lastname']); ?>
                            </h6>
                            <small class="text-muted d-block">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?php echo htmlspecialchars($client['address']); ?>
                            </small>
                            <small class="text-muted d-block mt-1">
                                <i class="bi bi-telephone me-1"></i>
                                <?php echo htmlspecialchars($client['cp_no']); ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <small class="d-block text-muted">Purpose</small>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($purposeDisplay); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certificate Selection -->
            <form action="submit_request.php" method="POST" id="certForm">

                <!-- Search -->
                <div class="cert-controls mb-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="certSearch"
                               placeholder="Search certificates...">
                    </div>

                    <div class="filter-chips mt-3">
                        <button type="button" class="filter-chip active" data-filter="all">All</button>
                    </div>
                </div>

                <!-- Certificate Grid -->
                <div class="cert-grid" id="certGrid">
                    <?php if (empty($certificates)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No certificates available at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($certificates as $cert): ?>
                            <label class="cert-item"
                                   data-name="<?php echo strtolower(htmlspecialchars($cert['certificate_name'])); ?>">
                                <input type="checkbox" name="certificates[]"
                                       value="<?php echo (int)$cert['id']; ?>"
                                       class="cert-checkbox d-none"
                                       data-price="<?php echo (float)$cert['price']; ?>">

                                <div class="cert-card-inner">
                                    <div class="cert-badge">
                                        <i class="bi bi-star-fill"></i>
                                    </div>
                                    <div class="cert-icon">
                                        <i class="bi bi-file-earmark-check"></i>
                                    </div>
                                    <h6 class="cert-title"><?php echo htmlspecialchars($cert['certificate_name']); ?></h6>
                                    <p class="cert-desc"><?php echo htmlspecialchars($cert['description']); ?></p>
                                    <div class="cert-price">₱<?php echo number_format((float)$cert['price'], 2); ?></div>
                                    <div class="cert-check">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Total & Actions -->
                <div class="cert-summary mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Selected:</span>
                        <span class="fw-semibold" id="selectedCount">0 items</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fs-5 fw-bold">Total Amount:</span>
                        <span class="fs-4 fw-bold text-primary" id="totalAmount">₱0.00</span>
                    </div>

                    <div class="d-flex gap-3 justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </a>

                        <button type="submit" class="btn btn-submit btn-lg px-5" id="submitBtn" disabled>
                            <span class="btn-text">Continue <i class="bi bi-arrow-right ms-1"></i></span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Processing...
                            </span>
                        </button>
                    </div>
                </div>
            </form>

            <div class="help-section mt-4 pt-3 border-top">
                <small class="text-muted d-flex align-items-center">
                    <i class="bi bi-info-circle me-2"></i>
                    Select one or more certificates. You can change your selection on the next page.
                </small>
            </div>

        </div>
        <div class="card-footer-custom"></div>
    </div>

    <div class="bg-decoration"></div>
</div>

<!-- Framework JS -->
<script src="../assets/js/bootstrap.bundle.min.js" defer></script>
<script src="../assets/js/select_cert.js" defer></script>

</body>
</html>