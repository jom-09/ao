<?php
session_start();
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: index.php");
  exit();
}

if (!isset($_SESSION['client_info']) || !is_array($_SESSION['client_info'])) {
  header("Location: index.php?error=Session+expired.+Please+try+again.");
  exit();
}
if (($_SESSION['selected_service'] ?? '') !== 'tax') {
  header("Location: index.php?error=Invalid+flow.");
  exit();
}

$client = $_SESSION['client_info'];

$last   = trim((string)($client['last_name'] ?? ''));
$first  = trim((string)($client['first_name'] ?? ''));
$middle = trim((string)($client['middle_name'] ?? ''));
$addr   = trim((string)($client['address'] ?? ''));
$cp     = trim((string)($client['cp_no'] ?? ''));

$declared_owner = trim((string)($_POST['declared_owner'] ?? ''));
$arp_no         = trim((string)($_POST['arp_no'] ?? ''));
$avRaw          = trim((string)($_POST['av'] ?? '0'));

$assessed_value = (float)str_replace([',',' '], '', $avRaw);

if ($last === '' || $first === '' || $addr === '' || $cp === '') {
  header("Location: index.php?error=Missing+client+information.");
  exit();
}
if ($declared_owner === '' || $arp_no === '') {
  header("Location: pay_tax.php?error=Missing+property+information.");
  exit();
}

try {
  $stmt = $conn->prepare("
    INSERT INTO tax_requests
      (client_last_name, client_first_name, client_middle_name, client_address, client_contact,
       declared_owner, arp_no, assessed_value, status)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
  ");
  $stmt->bind_param(
    "sssssssd",
    $last, $first, $middle, $addr, $cp,
    $declared_owner, $arp_no, $assessed_value
  );
  $stmt->execute();
  $newId = $stmt->insert_id;
  $stmt->close();

  // optional: show a success page or back to client home
  header("Location: pay_tax.php?success=Submitted+to+Treasury.+Ref+No.+".$newId);
  exit();

} catch (Throwable $e) {
  header("Location: pay_tax.php?error=" . urlencode("Failed to submit. " . $e->getMessage()));
  exit();
}