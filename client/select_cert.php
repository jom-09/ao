<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['client_info']) || !is_array($_SESSION['client_info'])) {
  header("Location: index.php?error=Session+expired.+Please+try+again.");
  exit();
}

if (($_SESSION['selected_service'] ?? '') !== 'cert') {
  header("Location: index.php?error=Invalid+flow.");
  exit();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===============================
   1) ALIAS MAPPING (Appointment → Office)
   Left side MUST match certificates.certificate_name in office DB
================================== */
$CERT_ALIAS_MAP = [
  'Certificate of Actual Location' => [
    'Actual Location',
    'Certificate of Actual Location',
  ],

  'Issuance of True Copy of Tax Declaration' => [
    'Tax Declaration',
    'Tax Declaration (Authorized Personnel)',
    'Tax Declaration (Bought)',
    'Tax Declaration (Donation)',
    'Tax Declaration (Own)',
    'Issuance of True Copy of Tax Declaration',
  ],

  'No Improvement Certification' => [
    'No Improvement',
    'No Improvement Certification',
  ],

  'With Improvement' => [
    'With Improvement',
    'With Improvement Certification',
  ],

  'Annotation or Cancellation of Improvements' => [
    'Annotation or Cancellation of Improvement',
    'Annotation or Cancellation of Improvements',
    'Annotation / Cancellation of Improvement',
    'Annotation or Cancellation of Improvement(s)',
  ],
];

/* ===============================
   2) Helper normalize (for smart matching)
================================== */
function norm($s){
  $s = mb_strtolower(trim((string)$s));
  $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s); // remove punctuation
  $s = preg_replace('/\s+/', ' ', $s);
  return trim($s);
}

/* ===============================
   3) Convert QR labels to office certificate_name list
================================== */
$qrLabels = $_SESSION['qr_cert_labels'] ?? [];
$qrLabels = is_array($qrLabels) ? $qrLabels : [];

$officeNamesWanted = []; // certificate_name values we want to auto-check

if (!empty($qrLabels)) {
  // Build reverse lookup: normalized alias -> office cert name
  $aliasToOffice = [];

  foreach ($CERT_ALIAS_MAP as $officeName => $aliases) {
    foreach ($aliases as $a) {
      $aliasToOffice[norm($a)] = $officeName;
    }
    // also allow officeName itself as alias
    $aliasToOffice[norm($officeName)] = $officeName;
  }

  foreach ($qrLabels as $lbl) {
    $n = norm($lbl);

    // Direct alias hit
    if (isset($aliasToOffice[$n])) {
      $officeNamesWanted[] = $aliasToOffice[$n];
      continue;
    }

    // Smart contains match (fallback)
    foreach ($aliasToOffice as $aliasNorm => $officeName) {
      if ($aliasNorm !== '' && (str_contains($n, $aliasNorm) || str_contains($aliasNorm, $n))) {
        $officeNamesWanted[] = $officeName;
        break;
      }
    }
  }

  $officeNamesWanted = array_values(array_unique($officeNamesWanted));
}

/* ===============================
   4) Fetch active certificates
================================== */
$certs = [];
$res = $conn->query("SELECT id, certificate_name, price FROM certificates WHERE status='active' ORDER BY certificate_name ASC");
while ($row = $res->fetch_assoc()) $certs[] = $row;

/* ===============================
   5) Resolve wanted office names to IDs
================================== */
$prefillIds = [];
if (!empty($officeNamesWanted)) {
  $placeholders = implode(',', array_fill(0, count($officeNamesWanted), '?'));
  $sql = "SELECT id FROM certificates WHERE certificate_name IN ($placeholders) AND status='active'";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $types = str_repeat('s', count($officeNamesWanted));
    $stmt->bind_param($types, ...$officeNamesWanted);
    $stmt->execute();
    $stmt->bind_result($cid);
    while ($stmt->fetch()) $prefillIds[] = (int)$cid;
    $stmt->close();
  }
}

$_SESSION['qr_prefill_cert_ids'] = $prefillIds;

// UI vars
$fromQr = isset($_GET['from']) && $_GET['from'] === 'qr';
$error = '';
if (isset($_GET['error'])) $error = h($_GET['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Select Certificates</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
  <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <h4 class="mb-0">Select Certificates</h4>
              <div class="text-muted small">Choose certificate(s) to request.</div>
            </div>
            <a href="<?= $fromQr ? 'scan.php' : 'index.php' ?>" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-arrow-left"></i> Back
            </a>
          </div>

          <?php if($error): ?>
            <div class="alert alert-danger small"><?= $error ?></div>
          <?php endif; ?>

          <?php if($fromQr): ?>
            <div class="alert alert-info small">
              <i class="bi bi-qr-code-scan me-1"></i>
              Certificates were pre-selected from the scanned QR. You can still adjust them.
            </div>
          <?php endif; ?>

          <div class="mb-3 small">
            <div><strong>Client:</strong> <?= h($_SESSION['client_info']['firstname'].' '.$_SESSION['client_info']['lastname']) ?></div>
            <div><strong>Contact:</strong> <?= h($_SESSION['client_info']['cp_no']) ?></div>
          </div>

          <form method="POST" action="submit_request.php" id="certForm">
            <div class="list-group mb-3">
              <?php foreach ($certs as $c): ?>
                <?php
                  $cid = (int)$c['id'];
                  $checked = in_array($cid, $prefillIds, true);
                ?>
                <label class="list-group-item d-flex justify-content-between align-items-center">
                  <div class="form-check m-0">
                    <input class="form-check-input me-2" type="checkbox"
                           name="certificates[]" value="<?= $cid ?>" <?= $checked ? 'checked' : '' ?>>
                    <span class="fw-semibold"><?= h($c['certificate_name']) ?></span>
                    <div class="text-muted small">₱<?= number_format((float)$c['price'], 2) ?></div>
                  </div>
                  <?php if($checked): ?>
                    <span class="badge bg-primary">From QR</span>
                  <?php endif; ?>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-between">
              <a href="<?= $fromQr ? 'scan.php' : 'index.php' ?>" class="btn btn-outline-secondary">Back</a>
              <button type="submit" class="btn btn-primary">
                Continue <i class="bi bi-arrow-right ms-1"></i>
              </button>
            </div>
          </form>

          <?php if($fromQr && empty($prefillIds) && !empty($qrLabels)): ?>
            <div class="alert alert-warning small mt-3 mb-0">
              <strong>Heads up:</strong> Walang na-match na certificate from QR.
              Please select manually.
            </div>
          <?php endif; ?>

        </div>
      </div>

    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>