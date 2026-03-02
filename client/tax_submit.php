<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['client_info']) || !is_array($_SESSION['client_info'])) {
  header("Location: index.php?error=Session+expired.+Please+try+again.");
  exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function s($v){ return trim((string)($v ?? '')); }

$client = $_SESSION['client_info'];

// client info from your form/session (NO login)
$first  = s($client['first_name'] ?? '');
$middle = s($client['middle_name'] ?? '');
$last   = s($client['last_name'] ?? '');
$address= s($client['address'] ?? $client['address_line'] ?? '');
$contact= s($client['cp_no'] ?? $client['contact_no'] ?? '');

if ($first === '' || $last === '' || $address === '' || $contact === '') {
  header("Location: pay_tax.php?error=Missing+client+information+(name/address/contact).");
  exit();
}

// decode selected properties
$selectedJson = (string)($_POST['selected_json'] ?? '[]');
$items = json_decode($selectedJson, true);

if (!is_array($items) || count($items) === 0) {
  header("Location: pay_tax.php?error=Please+select+at+least+one+property.");
  exit();
}

// clean selected rows
$clean = [];
foreach ($items as $it) {
  $declaredOwner = s($it['owner'] ?? '');
  $arp           = s($it['arp'] ?? '');
  $av            = (float)($it['av'] ?? 0);
  $tax           = (float)($it['tax_due'] ?? 0);

  if ($declaredOwner === '' || $arp === '' || $av <= 0 || $tax <= 0) continue;

  $clean[] = [
    'declared_owner' => $declaredOwner,
    'arp_no' => $arp,
    'assessed_value' => $av,
    'tax_due' => $tax
  ];
}

if (count($clean) === 0) {
  header("Location: pay_tax.php?error=Invalid+selection.");
  exit();
}

try {
  $conn->begin_transaction();

  // Insert EACH selected property as PENDING in tax_requests
  $stmt = $conn->prepare("
    INSERT INTO tax_requests
      (first_name, middle_name, last_name, address, contact_no,
       declared_owner, arp_no, assessed_value, tax_due, status, created_at)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW())
  ");

  foreach ($clean as $r) {
    $stmt->bind_param(
      "sssssssdd",
      $first,
      $middle,
      $last,
      $address,
      $contact,
      $r['declared_owner'],
      $r['arp_no'],
      $r['assessed_value'],
      $r['tax_due']
    );
    $stmt->execute();
  }

  $stmt->close();
  $conn->commit();

  header("Location: index.php?success=Tax+request(s)+submitted+to+Treasury.");
  exit();

} catch (Throwable $e) {
  $conn->rollback();
  header("Location: pay_tax.php?error=" . urlencode("Submit failed: " . $e->getMessage()));
  exit();
}