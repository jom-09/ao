<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['client_info']) || !is_array($_SESSION['client_info'])) {
  header("Location: index.php?error=Session+expired.+Please+try+again.");
  exit();
}

if (($_SESSION['selected_service'] ?? '') !== 'svc') {
  header("Location: index.php?error=Invalid+flow.");
  exit();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ===============================
   1) SERVICE ALIAS MAP (Appointment → Office)
   Left side MUST match services.service_name in office DB
   Right side = appointment system labels that map to that office service
================================== */
$SERVICE_ALIAS_MAP = [
  // OFFICE service_name => APPOINTMENT labels that should map to it
  'Annotation or Cancellation of Improvements' => [
    'Annotation or Cancellation of Improvement',
    'Annotation or Cancellation of Improvements',
  ],

  'Assessment Verification (Per Property)' => [
    'Assessment Verification Fee (per property)',
    'Assessment Verification (Per Property)',
    'Assessment Verification Fee',
  ],

  'Consolidation or Subdivision Assessment Review' => [
    'Consolidation or Subdivision Assessment Review',
  ],

  'GIS Data Extraction or Lot Reference' => [
    'GIS Data Extraction or Lot Reference',
    'GIS Data Extraction',
    'Lot Reference',
  ],

  'GIS-Generated Parcel Map Print' => [
    'GIS-Generated Parcel Map Print (Per lot or parcel)',
    'GIS-Generated Parcel Map Print',
    'GIS Generated Parcel Map Print',
  ],

  'Manual Sketch or Vicinity Map' => [
    'Manual Sketch or Vicinity Map',
    'Manual Sketch',
    'Vicinity Map',
  ],

  'New Building or Improvement Assessment' => [
    'New Building or Improvement Assessment',
  ],

  'Research and Archival Verification' => [
    'Research and Archival Verification',
  ],
];
/* ===============================
   2) normalize helper
================================== */
function norm($s){
  $s = mb_strtolower(trim((string)$s));
  $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return trim($s);
}

/* ===============================
   3) Get service labels from QR
================================== */
$items = $_SESSION['qr_items'] ?? [];
$items = is_array($items) ? $items : [];

$qrSvcLabels = [];
foreach ($items as $it) {
  if (($it['type'] ?? '') === 'service') {
    $lbl = trim((string)($it['label'] ?? ''));
    if ($lbl !== '') $qrSvcLabels[] = $lbl;
  }
}
$qrSvcLabels = array_values(array_unique($qrSvcLabels));

/* ===============================
   4) Convert QR labels to office service_name list (via alias map)
================================== */
$officeSvcNamesWanted = [];

if (!empty($qrSvcLabels)) {

  // Build reverse map: normalized alias -> officeName
  $aliasToOffice = [];

  foreach ($SERVICE_ALIAS_MAP as $officeName => $aliases) {
    foreach ($aliases as $a) {
      $aliasToOffice[norm($a)] = $officeName;
    }
    // also allow office name itself
    $aliasToOffice[norm($officeName)] = $officeName;
  }

  foreach ($qrSvcLabels as $lbl) {
    $n = norm($lbl);

    // 1) exact normalized match
    if (isset($aliasToOffice[$n])) {
      $officeSvcNamesWanted[] = $aliasToOffice[$n];
      continue;
    }

    // 2) smart contains (handles parentheses differences)
    $matched = false;
    foreach ($aliasToOffice as $aliasNorm => $officeName) {
      if ($aliasNorm !== '' && (str_contains($n, $aliasNorm) || str_contains($aliasNorm, $n))) {
        $officeSvcNamesWanted[] = $officeName;
        $matched = true;
        break;
      }
    }

    // 3) fallback: try to match by removing common words
    if (!$matched) {
      $n2 = str_replace(['per property','per lot','lot','parcel','fee'], '', $n);
      $n2 = trim(preg_replace('/\s+/', ' ', $n2));
      foreach ($aliasToOffice as $aliasNorm => $officeName) {
        if ($aliasNorm !== '' && $n2 !== '' && (str_contains($n2, $aliasNorm) || str_contains($aliasNorm, $n2))) {
          $officeSvcNamesWanted[] = $officeName;
          break;
        }
      }
    }
  }

  $officeSvcNamesWanted = array_values(array_unique($officeSvcNamesWanted));
}

/* ===============================
   5) Fetch active services list
================================== */
$services = [];
$res = $conn->query("SELECT id, service_name, price FROM services WHERE status='active' ORDER BY service_name ASC");
while ($row = $res->fetch_assoc()) $services[] = $row;

// Build normalized index: normalized service_name => id
$serviceIndex = [];
foreach ($services as $s) {
  $serviceIndex[norm($s['service_name'])] = (int)$s['id'];
}
/* ===============================
   6) Resolve wanted names → service IDs (robust)
================================== */
$prefillSvcIds = [];

// A) Try mapping QR labels -> office service_name via alias map, then match to DB index
foreach ($qrSvcLabels as $lbl) {
  $nLbl = norm($lbl);

  // Find office name from alias map
  $officeName = null;

  foreach ($SERVICE_ALIAS_MAP as $officeSvcName => $aliases) {
    // compare normalized
    if (norm($officeSvcName) === $nLbl) { $officeName = $officeSvcName; break; }

    foreach ($aliases as $a) {
      if (norm($a) === $nLbl) { $officeName = $officeSvcName; break 2; }
    }

    // contains fallback
    foreach ($aliases as $a) {
      $na = norm($a);
      if ($na !== '' && (str_contains($nLbl, $na) || str_contains($na, $nLbl))) {
        $officeName = $officeSvcName;
        break 2;
      }
    }
  }

  // If alias matched an office name, try match it to DB
  if ($officeName !== null) {
    $key = norm($officeName);
    if (isset($serviceIndex[$key])) {
      $prefillSvcIds[] = $serviceIndex[$key];
      continue;
    }
  }

  // B) Fallback: try match QR label directly to DB service_name by normalization
  if (isset($serviceIndex[$nLbl])) {
    $prefillSvcIds[] = $serviceIndex[$nLbl];
    continue;
  }

  // C) Fallback: contains match against DB services
  foreach ($serviceIndex as $svcNorm => $sid) {
    if ($svcNorm !== '' && (str_contains($nLbl, $svcNorm) || str_contains($svcNorm, $nLbl))) {
      $prefillSvcIds[] = (int)$sid;
      break;
    }
  }
}

$prefillSvcIds = array_values(array_unique($prefillSvcIds));
$_SESSION['qr_prefill_service_ids'] = $prefillSvcIds;

$fromQr = (isset($_GET['from']) && $_GET['from'] === 'qr');
$error = '';
if (isset($_GET['error'])) $error = h($_GET['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Select Services</title>
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
              <h4 class="mb-0">Select Services</h4>
              <div class="text-muted small">Choose service(s) to request.</div>
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
              Services were pre-selected from the scanned QR. You can still adjust them.
            </div>
          <?php endif; ?>

          <div class="mb-3 small">
            <div><strong>Client:</strong> <?= h($_SESSION['client_info']['firstname'].' '.$_SESSION['client_info']['lastname']) ?></div>
            <div><strong>Contact:</strong> <?= h($_SESSION['client_info']['cp_no']) ?></div>
          </div>

          <!-- Submit to your existing submit_service.php -->
          <form method="POST" action="submit_service.php" id="svcForm">
            <div class="list-group mb-3">
              <?php foreach ($services as $s): ?>
                <?php
                  $sid = (int)$s['id'];
                  $checked = in_array($sid, $prefillSvcIds, true);
                ?>
                <label class="list-group-item d-flex justify-content-between align-items-center">
                  <div class="form-check m-0">
                    <input class="form-check-input me-2" type="checkbox"
                           name="services[]" value="<?= $sid ?>" <?= $checked ? 'checked' : '' ?>>
                    <span class="fw-semibold"><?= h($s['service_name']) ?></span>
                    <div class="text-muted small">₱<?= number_format((float)$s['price'], 2) ?></div>
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

          <?php if($fromQr && empty($prefillSvcIds) && !empty($qrSvcLabels)): ?>
            <div class="alert alert-warning small mt-3 mb-0">
              <strong>Heads up:</strong> Walang na-match na service from QR.
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