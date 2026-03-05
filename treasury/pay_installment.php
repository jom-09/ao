<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function out($data){ echo json_encode($data); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Invalid request']);

$taxId = (int)($_POST['tax_request_id'] ?? 0);
$q     = (int)($_POST['quarter'] ?? 0);

$discountRate  = (float)($_POST['discount_rate'] ?? 0);
$penaltyMonths = (int)($_POST['penalty_months'] ?? 0);

if ($taxId <= 0) out(['error' => 'Invalid tax request id']);
if ($q < 1 || $q > 4) out(['error' => 'Invalid quarter']);

if (!in_array($discountRate, [0, 0.10, 0.20], true)) $discountRate = 0;
if ($penaltyMonths < 0) $penaltyMonths = 0;
if ($penaltyMonths > 36) $penaltyMonths = 36;

try {

  // Get term amount + started year (for schedule)
  $stmt = $conn->prepare("
    SELECT term_amount, COALESCE(paid_at, created_at) AS started_at
    FROM tax_requests
    WHERE id=? AND status='INSTALLMENT'
    LIMIT 1
  ");
  $stmt->bind_param("i", $taxId);
  $stmt->execute();
  $req = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$req) out(['error' => 'Installment record not found']);

  $termAmount = (float)($req['term_amount'] ?? 0);
  if ($termAmount <= 0) out(['error' => 'Invalid term amount']);

  $year = (int)date('Y', strtotime($req['started_at'] ?? date('Y-m-d')));

  // Ensure schedule row exists (safety)
  $quarters = [
    1 => ['coverage' => 'Jan - Mar', 'due' => sprintf('%d-03-31', $year)],
    2 => ['coverage' => 'Apr - Jun', 'due' => sprintf('%d-06-30', $year)],
    3 => ['coverage' => 'Jul - Sep', 'due' => sprintf('%d-09-30', $year)],
    4 => ['coverage' => 'Oct - Dec', 'due' => sprintf('%d-12-31', $year)],
  ];

  $ins = $conn->prepare("
    INSERT IGNORE INTO tax_installments
    (tax_request_id, year, quarter, coverage, due_date, status)
    VALUES (?,?,?,?,?,'PENDING')
  ");
  foreach ($quarters as $qq => $meta) {
    $cov = $meta['coverage'];
    $due = $meta['due'];
    $ins->bind_param("iiiss", $taxId, $year, $qq, $cov, $due);
    $ins->execute();
  }
  $ins->close();

  // Get installment row
  $stmt = $conn->prepare("
    SELECT id, status
    FROM tax_installments
    WHERE tax_request_id=? AND year=? AND quarter=?
    LIMIT 1
  ");
  $stmt->bind_param("iii", $taxId, $year, $q);
  $stmt->execute();
  $inst = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$inst) out(['error' => 'Installment row not found']);
  if (($inst['status'] ?? '') === 'PAID') out(['error' => 'Quarter already paid']);

  // Compute manual totals
  $discountAmt = $termAmount * $discountRate;
  $afterDiscount = $termAmount - $discountAmt;
  $penaltyAmt = $afterDiscount * (0.02 * $penaltyMonths);
  $totalPaid = $afterDiscount + $penaltyAmt;

  // Mark as PAID + store breakdown
  $stmt = $conn->prepare("
    UPDATE tax_installments
    SET status='PAID',
        discount_rate=?,
        penalty_months=?,
        discount_amount=?,
        penalty_amount=?,
        total_paid=?,
        paid_at=NOW()
    WHERE id=? AND status='PENDING'
    LIMIT 1
  ");
  $stmt->bind_param(
    "didddi",
    $discountRate,
    $penaltyMonths,
    $discountAmt,
    $penaltyAmt,
    $totalPaid,
    $inst['id']
  );
  $stmt->execute();
  $affected = $stmt->affected_rows;
  $stmt->close();

  if ($affected <= 0) out(['error' => 'Unable to save payment (maybe already paid).']);

  // If all quarters paid => mark tax_requests PAID
  $stmt = $conn->prepare("
    SELECT COUNT(*) c
    FROM tax_installments
    WHERE tax_request_id=? AND year=? AND status='PENDING'
  ");
  $stmt->bind_param("ii", $taxId, $year);
  $stmt->execute();
  $pending = (int)$stmt->get_result()->fetch_assoc()['c'];
  $stmt->close();

  if ($pending === 0) {
    $stmt = $conn->prepare("UPDATE tax_requests SET status='PAID', paid_at=NOW() WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $taxId);
    $stmt->execute();
    $stmt->close();
  }

  out([
    'ok' => true,
    'quarter' => $q,
    'term_amount' => round($termAmount, 2),
    'discount_rate' => $discountRate,
    'discount_amount' => round($discountAmt, 2),
    'penalty_months' => $penaltyMonths,
    'penalty_amount' => round($penaltyAmt, 2),
    'total_paid' => round($totalPaid, 2),
    'all_paid' => ($pending === 0)
  ]);

} catch (Throwable $e) {
  out(['error' => $e->getMessage()]);
}