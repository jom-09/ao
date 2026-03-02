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

// values from JS (optional, but we’ll still validate basic)
$computed_total_due  = (float)($_POST["computed_total_due"] ?? 0);
$computed_term_amt   = (float)($_POST["computed_term_amount"] ?? 0);

if ($tax_id <= 0) back("Missing tax request ID.");
if ($control_number === "") back("Control number is required.");

if (!in_array($payment_option, ["ANNUALLY","QUARTERLY"], true)) {
  $payment_option = "ANNUALLY";
}

if ($penalty_months < 0) $penalty_months = 0;
if ($penalty_months > 36) $penalty_months = 36;

// OPTIONAL: Server-side recompute (recommended for security)
// We will recompute total_due based on assessed_value in DB,
// so kahit ma-tamper yung JS value, safe pa rin.

try {
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

  // clamp discount to allowed
  if (!in_array($discount_rate, [0, 0.10, 0.20], true)) $discount_rate = 0;

  $discountAmt = $base * $discount_rate;
  $afterDisc   = $base - $discountAmt;

  $penaltyAmt  = $afterDisc * (0.02 * $penalty_months);
  $totalDue    = $afterDisc + $penaltyAmt;

  $termAmount  = ($payment_option === "QUARTERLY") ? ($totalDue / 4) : $totalDue;

  // status logic
  // - ANNUALLY  => PAID (paid_at now)
  // - QUARTERLY => INSTALLMENT (paid_at NULL or keep created_at)
  $newStatus = ($payment_option === "QUARTERLY") ? "INSTALLMENT" : "PAID";

  if ($newStatus === "PAID") {
    $stmt = $conn->prepare("
      UPDATE tax_requests
      SET status=?,
          control_number=?,
          payment_option=?,
          total_due=?,
          term_amount=?,
          paid_at=NOW()
      WHERE id=?
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
    // INSTALLMENT
    $stmt = $conn->prepare("
      UPDATE tax_requests
      SET status=?,
          control_number=?,
          payment_option=?,
          total_due=?,
          term_amount=?,
          paid_at=NULL
      WHERE id=?
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

    back("Tax payment saved as INSTALLMENT (Quarterly).", true);
  }

} catch (Throwable $e) {
  // If enum doesn't allow INSTALLMENT or columns missing, you'll land here.
  back("Save failed: " . $e->getMessage());
}