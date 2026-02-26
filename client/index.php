<?php
session_start();

// Clear any previous session data
unset($_SESSION['client_info']);
unset($_SESSION['selected_service']);

$error = "";
$success = "";
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);
if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Information | Certificate System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Framework CSS -->
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
    <link href="../assets/bootstrap/css/style.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<div class="client-wrapper">
    <!-- Progress Steps -->
    <div class="progress-container">
        <div class="progress-step active" data-step="1">
            <div class="step-circle">1</div>
            <span class="step-label">Info</span>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="2">
            <div class="step-circle">2</div>
            <span class="step-label">Select</span>
        </div>
        <div class="progress-line"></div>
        <div class="progress-step" data-step="3">
            <div class="step-circle">3</div>
            <span class="step-label">Confirm</span>
        </div>
    </div>

    <div class="client-card card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">

            <!-- Header -->
            <div class="text-center mb-4">
                <div class="brand-icon mb-3">
                    <i class="bi bi-person-vcard fs-1"></i>
                </div>
                <h4 class="fw-bold mb-1">Client Information</h4>
                <p class="text-muted small">Please fill in your details to proceed</p>
            </div>

            <!-- Alerts -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

                <a href="scan.php" class="btn btn-outline-primary w-100 mb-3">
                    <i class="bi bi-qr-code-scan me-2"></i>Scan Appointment QR
                </a>

            <!-- ✅ IMPORTANT: route_service.php ang target -->
            <form action="route_service.php" method="POST" id="clientForm" novalidate>
                

                <!-- Name -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-floating">
                            <input type="text" name="firstname" class="form-control" id="firstname"
                                   placeholder="First Name" required pattern="[A-Za-z\s]{2,50}"
                                   autocomplete="given-name">
                            <label for="firstname">First Name</label>
                            <div class="invalid-feedback">Please enter a valid first name (2-50 letters)</div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-floating">
                            <input type="text" name="middlename" class="form-control" id="middlename"
                                   placeholder="Middle Name" pattern="[A-Za-z\s]{0,50}"
                                   autocomplete="additional-name">
                            <label for="middlename">Middle Name</label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-floating">
                            <input type="text" name="lastname" class="form-control" id="lastname"
                                   placeholder="Last Name" required pattern="[A-Za-z\s]{2,50}"
                                   autocomplete="family-name">
                            <label for="lastname">Last Name</label>
                            <div class="invalid-feedback">Please enter a valid last name (2-50 letters)</div>
                        </div>
                    </div>
                </div>

                <!-- Address -->
                <div class="form-floating mb-3 mt-4">
                    <textarea name="address" class="form-control" id="address"
                              placeholder="Address" required rows="3" style="height: 100px;"
                              autocomplete="street-address"></textarea>
                    <label for="address"><i class="bi bi-geo-alt me-1"></i>Complete Address</label>
                    <div class="invalid-feedback">Please enter your complete address</div>
                    <div class="form-text text-end"><span id="charCount">0</span>/255 characters</div>
                </div>

                <!-- Contact -->
                <div class="form-floating mb-3">
                    <input type="text" name="cp_no" class="form-control" id="cp_no"
                    placeholder="Contact Number" required
                    inputmode="numeric" autocomplete="tel"
                    pattern="^(09\d{9}|\+63\d{10})$">
                    <label for="cp_no">Contact Number (09xxxxxxxxx)</label>
                    <div class="invalid-feedback">Use 09xxxxxxxxx or +63xxxxxxxxxx</div>
                </div>

                <!-- Transaction Selection (radio) -->
                <div class="mb-4">
                    <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-check me-1"></i>Select Transaction</span>
                    </label>

                    <div class="purpose-options" id="purposeContainer">

                        <!-- Certification Issuance -->
                        <label class="purpose-option" data-value="cert">
                            <input type="radio" name="service" value="cert" class="purpose-checkbox" required>
                            <div class="option-card">
                                <div class="option-check"><i class="bi bi-check-lg"></i></div>
                                <span>Certification Issuance</span>
                            </div>
                        </label>

                        <!-- Pay Tax -->
                        <label class="purpose-option" data-value="tax">
                            <input type="radio" name="service" value="tax" class="purpose-checkbox" required>
                            <div class="option-card">
                                <div class="option-check"><i class="bi bi-check-lg"></i></div>
                                <span>Pay Tax</span>
                            </div>
                        </label>

                        <!-- Services -->
                        <label class="purpose-option" data-value="svc">
                            <input type="radio" name="service" value="svc" class="purpose-checkbox" required>
                            <div class="option-card">
                                <div class="option-check"><i class="bi bi-check-lg"></i></div>
                                <span>Services</span>
                            </div>
                        </label>

                    </div>

                    <div class="invalid-feedback d-block">Please select one option</div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-3 justify-content-between">
                    <a href="../index.php" class="btn btn-outline-secondary px-4">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                    <button type="submit" class="btn btn-submit btn-lg px-5">
                        <span class="btn-text">Next <i class="bi bi-arrow-right ms-1"></i></span>
                        <span class="btn-loading d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Processing...
                        </span>
                    </button>
                </div>
            </form>

            <!-- Help -->
            <div class="help-section mt-4 pt-3 border-top">
                <small class="text-muted d-flex align-items-center">
                    <i class="bi bi-info-circle me-2"></i>
                    All fields are required. Select one transaction to proceed.
                </small>
            </div>

        </div>

        <div class="card-footer-custom"></div>
    </div>

    <div class="bg-decoration"></div>
</div>

<!-- Framework JS -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>

<!-- ✅ CSP-SAFE external JS (create this file) -->
<script src="../assets/js/client_index.js" defer></script>

</body>
</html>