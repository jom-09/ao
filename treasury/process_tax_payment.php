<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function back($msg, $ok=false){
  $k = $ok ? "success" : "error";
  header("Location: home.php?$k=" . urlencode($msg));
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  back("Invalid request.");
}

$tax_id         = isset($_POST["tax_request_id"]) ? (int)$_POST["tax_request_id"] : 0;
$control_number = trim((string)($_POST["control_number"] ?? ""));
$discount_rate  = (float)($_POST["discount_rate"] ?? 0);
$penalty_months = (int)($_POST["penalty_months"] ?? 0);

$payment_option = strtoupper(trim((string)($_POST["payment_option"] ?? "ANNUALLY"))); // ANNUALLY / QUARTERLY

// values from JS (optional)
$computed_total_due  = (float)($_POST["computed_total_due"] ?? 0);
$computed_term_amt   = (float)($_POST["computed_term_amount"] ?? 0);

if ($tax_id <= 0) back("Missing tax request ID.");
if ($control_number === "") back("Control number is required.");

if (!in_array($payment_option, ["ANNUALLY","QUARTERLY"], true)) {
  $payment_option = "ANNUALLY";
}

if ($penalty_months < 0) $penalty_months = 0;
if ($penalty_months > 36) $penalty_months = 36;

try {
  // ✅ get assessed value
  $stmt = $conn->prepare("SELECT assessed_value FROM tax_requests WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $tax_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row) back("Tax request not found.");

  $avRaw = (string)($row["assessed_value"] ?? "0");
  $av    = (float)str_replace([","," "], "", $avRaw);

  $basic = $av * 0.01;
  $sef   = $av * 0.01;
  $base  = $basic + $sef;

  // clamp discount
  if (!in_array($discount_rate, [0, 0.10, 0.20], true)) $discount_rate = 0;

  $discountAmt = $base * $discount_rate;
  $afterDisc   = $base - $discountAmt;

  $penaltyAmt  = $afterDisc * (0.02 * $penalty_months);
  $totalDue    = $afterDisc + $penaltyAmt;

  $termAmount  = ($payment_option === "QUARTERLY") ? ($totalDue / 4) : $totalDue;

  $newStatus = ($payment_option === "QUARTERLY") ? "INSTALLMENT" : "PAID";

  if ($newStatus === "PAID") {
    // ✅ Full payment
    $stmt = $conn->prepare("
      UPDATE tax_requests
      SET status=?,
          control_number=?,
          payment_option=?,
          total_due=?,
          term_amount=?,
          paid_at=NOW()
      WHERE id=?
      LIMIT 1
    ");
    $stmt->bind_param(
      "sssddi",
      $newStatus,
      $control_number,
      $payment_option,
      $totalDue,
      $termAmount,
      $tax_id
    );
    $stmt->execute();
    $stmt->close();

    back("Tax payment saved as PAID.", true);

  } else {
    // ✅ INSTALLMENT
    // IMPORTANT: set paid_at=NOW() as "started_at" for schedule basis
    $stmt = $conn->prepare("
      UPDATE tax_requests
      SET status=?,
          control_number=?,
          payment_option=?,
          total_due=?,
          term_amount=?,
          paid_at=NOW()
      WHERE id=?
      LIMIT 1
    ");
    $stmt->bind_param(
      "sssddi",
      $newStatus,
      $control_number,
      $payment_option,
      $totalDue,
      $termAmount,
      $tax_id
    );
    $stmt->execute();
    $stmt->close();

// ✅ Auto-create installment schedule rows (Q1..Q4) FIXED Jan-Dec
$year = (int)date('Y'); // use current year (or year of NOW())

$quarters = [
  1 => ['coverage' => 'Jan - Mar', 'due' => sprintf('%d-03-31', $year)],
  2 => ['coverage' => 'Apr - Jun', 'due' => sprintf('%d-06-30', $year)],
  3 => ['coverage' => 'Jul - Sep', 'due' => sprintf('%d-09-30', $year)],
  4 => ['coverage' => 'Oct - Dec', 'due' => sprintf('%d-12-31', $year)],
];

$ins = $conn->prepare("
  INSERT IGNORE INTO tax_installments (tax_request_id, year, quarter, coverage, due_date, status)
  VALUES (?,?,?,?,?,'PENDING')
");

foreach ($quarters as $q => $meta) {
  $cov = $meta['coverage'];
  $due = $meta['due'];
  $ins->bind_param("iiiss", $tax_id, $year, $q, $cov, $due);
  $ins->execute();
}
$ins->close();

    back("Tax payment saved as INSTALLMENT (Quarterly).", true);
  }

} catch (Throwable $e) {
  back("Save failed: " . $e->getMessage());
}