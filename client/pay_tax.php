<?php
session_start();
require_once "../config/database.php";

/* ===============================
   Guards
================================= */
if (!isset($_SESSION['client_info']) || !is_array($_SESSION['client_info'])) {
  header("Location: index.php?error=Session+expired.+Please+try+again.");
  exit();
}
if (($_SESSION['selected_service'] ?? '') !== 'tax') {
  header("Location: index.php?error=Invalid+flow.");
  exit();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$client = $_SESSION['client_info'];

$last   = strtoupper(trim($client['last_name'] ?? ''));
$first  = strtoupper(trim($client['first_name'] ?? ''));
$middle = strtoupper(trim($client['middle_name'] ?? ''));

$fullName = trim(
  ($client['last_name'] ?? '') . ', ' .
  ($client['first_name'] ?? '') . ' ' .
  ($client['middle_name'] ?? '')
);

$error = "";
$rows  = [];

/* ===============================
   PROPERTY SEARCH
================================= */
try {

  if ($middle !== '') {
    $sql = "
      SELECT declared_owner, `ARP_No.` AS arp_no, av
      FROM land_holdings_master
      WHERE UPPER(declared_owner) LIKE CONCAT('%', ?, '%')
        AND UPPER(declared_owner) LIKE CONCAT('%', ?, '%')
        AND UPPER(declared_owner) LIKE CONCAT('%', ?, '%')
      ORDER BY declared_owner ASC
      LIMIT 500
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $last, $first, $middle);
  } else {
    $sql = "
      SELECT declared_owner, `ARP_No.` AS arp_no, av
      FROM land_holdings_master
      WHERE UPPER(declared_owner) LIKE CONCAT('%', ?, '%')
        AND UPPER(declared_owner) LIKE CONCAT('%', ?, '%')
      ORDER BY declared_owner ASC
      LIMIT 500
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $last, $first);
  }

  $stmt->execute();
  $res  = $stmt->get_result();
  $rows = $res->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

} catch (Throwable $e) {
  $error = "Failed loading property records: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pay Tax | Properties</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
<link href="../assets/bootstrap/css/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>

<body>

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="fw-bold mb-1">
        <i class="bi bi-cash-coin me-2"></i>Pay Tax
      </h4>
      <small class="text-muted">
        Client: <strong><?= h($fullName) ?></strong>
      </small>
    </div>

    <a href="index.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm border-0">
    <div class="card-body">

      <!-- SUBMIT FORM (MULTI SELECT) -->
      <form action="tax_submit.php" method="POST" id="payTaxForm">
        <input type="hidden" name="selected_json" id="selected_json" value="[]">

        <div class="table-responsive">
          <table id="propTable" class="table table-striped align-middle">
            <thead>
              <tr>
                <th class="text-center" style="width:60px;">
                  <input type="checkbox" class="form-check-input" id="checkAll" title="Select all on this page">
                </th>
                <th>Declared Owner</th>
                <th>ARP No.</th>
                <th class="text-end">Assessed Value</th>
                <th class="text-end">Tax Due (2%)</th>
                <th class="text-center">Summary</th>
              </tr>
            </thead>

            <tbody>
            <?php foreach ($rows as $r):

              $owner = (string)$r['declared_owner'];
              $arp   = (string)$r['arp_no'];

              $avRaw = (string)$r['av'];
              $avNum = (float)str_replace([',',' '], '', $avRaw);

              $taxTotal = $avNum * 0.02;
              $taxDueFixed = number_format($taxTotal, 2, '.', '');
              $avFixed     = number_format($avNum, 2, '.', '');
            ?>
              <tr>
                <td class="text-center">
                  <input
                    type="checkbox"
                    class="form-check-input js-pay-check"
                    data-owner="<?= h($owner) ?>"
                    data-arp="<?= h($arp) ?>"
                    data-av="<?= h($avFixed) ?>"
                    data-tax="<?= h($taxDueFixed) ?>"
                    aria-label="Select property"
                  >
                </td>

                <td><?= h($owner) ?></td>
                <td><?= h($arp) ?></td>
                <td class="text-end"><?= number_format($avNum,2) ?></td>
                <td class="text-end fw-semibold"><?= number_format((float)$taxDueFixed,2) ?></td>

                <td class="text-center">
                  <button
                    type="button"
                    class="btn btn-primary btn-sm js-summary"
                    data-owner="<?= h($owner) ?>"
                    data-arp="<?= h($arp) ?>"
                    data-av="<?= h($avFixed) ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#summaryModal"
                  >
                    <i class="bi bi-receipt"></i> Summary
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- TOTAL + SUBMIT -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3">
          <div class="p-3 rounded border bg-light w-100">
            <div class="d-flex justify-content-between">
              <span class="text-muted">Checked Properties</span>
              <strong id="checkedCount">0</strong>
            </div>
            <div class="d-flex justify-content-between mt-1">
              <span class="fw-semibold">TOTAL Tax Due</span>
              <strong class="fs-5" id="checkedTotal">₱0.00</strong>
            </div>
            <small class="text-muted d-block mt-1">
              Tip: piliin mo lang yung property/properties na babayaran mo.
            </small>
          </div>

          <div class="d-grid d-md-block">
            <button type="submit" class="btn btn-success btn-lg" id="submitBtn" disabled>
              <i class="bi bi-send-check me-1"></i> Submit to Treasury
            </button>
          </div>
        </div>

      </form>

    </div>
  </div>
</div>

<!-- ===============================
     SUMMARY MODAL (BREAKDOWN)
================================= -->
<div class="modal fade" id="summaryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">

      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-file-earmark-text me-2"></i>
          Tax Computation Summary
        </h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="card bg-light border-0 mb-3">
          <div class="card-body py-3">
            <div class="mb-2">
              <span class="text-muted small">Declared Owner</span>
              <div class="fw-bold fs-5" id="mOwner"></div>
            </div>
            <div>
              <span class="text-muted small">ARP Number</span>
              <div class="fw-semibold" id="mArp"></div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm">
          <div class="card-body">

            <div class="d-flex justify-content-between mb-2">
              <span>Assessed Value (AV)</span>
              <strong id="mAv"></strong>
            </div>

            <hr>

            <div class="d-flex justify-content-between mb-2">
              <span>AV × Basic Tax (1%)</span>
              <strong id="mBasic"></strong>
            </div>

            <div class="d-flex justify-content-between mb-2">
              <span>AV × Special Educational Fund (1%)</span>
              <strong id="mSef"></strong>
            </div>

            <hr>

            <div class="d-flex justify-content-between fs-5">
              <strong>Total Tax (AV × 2%)</strong>
              <strong id="mTotal"></strong>
            </div>

          </div>
        </div>

        <div class="alert alert-warning mt-3 mb-0">
          <i class="bi bi-camera me-2"></i>
          You can screenshot this breakdown if needed for reference.
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Close
        </button>
      </div>

    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/datatables.min.js"></script>
<script src="../assets/js/pay_tax.js" defer></script>

</body>
</html>