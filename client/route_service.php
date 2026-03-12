<?php
session_start();
require_once "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: index.php?error=Invalid+request.");
  exit();
}

$firstname  = trim((string)($_POST['firstname'] ?? ''));
$middlename = trim((string)($_POST['middlename'] ?? ''));
$lastname   = trim((string)($_POST['lastname'] ?? ''));
$address    = trim((string)($_POST['address'] ?? ''));
$cp_no      = trim((string)($_POST['cp_no'] ?? ''));
$service    = trim((string)($_POST['service'] ?? ''));

// required fields
$missing = [];
foreach (['firstname','lastname','address','cp_no','service'] as $k) {
  if (!isset($_POST[$k]) || trim((string)$_POST[$k]) === '') {
    $missing[] = $k;
  }
}
if ($missing) {
  header("Location: index.php?error=Missing:+".urlencode(implode(', ', $missing)));
  exit();
}

$allowed = ['tax','cert','svc'];
if (!in_array($service, $allowed, true)) {
  header("Location: index.php?error=Invalid+transaction.");
  exit();
}

function normalize_name(string $s): string {
  $s = trim(preg_replace('/\s+/', ' ', $s));
  return strtoupper($s);
}

$fn = normalize_name($firstname);
$mn = normalize_name($middlename);
$ln = normalize_name($lastname);

// save BOTH naming styles (new + old) para walang mabreak
$_SESSION['client_info'] = [
  // NEW
  'first_name'  => $fn,
  'middle_name' => $mn,
  'last_name'   => $ln,
  'address'     => $address,
  'contact_no'  => $cp_no,

  // OLD
  'firstname'   => $fn,
  'middlename'  => $mn,
  'lastname'    => $ln,
  'cp_no'       => $cp_no,
];

$_SESSION['selected_service'] = $service;

/*
|--------------------------------------------------------------------------
| ROUTING
|--------------------------------------------------------------------------
| tax  -> pay_tax.php
| cert -> cert_search.php   <-- bagong flow, global search sa land_holdings_master
| svc  -> select_service.php
*/
if ($service === 'tax') {
  header("Location: pay_tax.php");
  exit();
}

if ($service === 'cert') {
  // global search page for certification issuance
  header("Location: cert_search.php");
  exit();
}

if ($service === 'svc') {
  header("Location: select_service.php");
  exit();
}

header("Location: index.php?error=Invalid+transaction.");
exit();