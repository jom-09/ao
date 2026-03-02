<?php
session_start();
require_once "../config/database.php";
//require_once "../includes/csrf.php";
//require_once "../includes/helpers.php"; // if you have clean_name/clean_phone etc.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: index.php?error=Invalid+request.");
  exit();
}

// If you already have CSRF token in your form, enable this.
// Otherwise you can comment it out for now.
// Csrf::verify($_POST['_csrf'] ?? null);

$firstname  = trim((string)($_POST['firstname'] ?? ''));
$middlename = trim((string)($_POST['middlename'] ?? ''));
$lastname   = trim((string)($_POST['lastname'] ?? ''));
$address    = trim((string)($_POST['address'] ?? ''));
$cp_no      = trim((string)($_POST['cp_no'] ?? ''));
$service    = trim((string)($_POST['service'] ?? ''));

if ($firstname === '' || $lastname === '' || $address === '' || $cp_no === '' || $service === '') {
  header("Location: index.php?error=Please+complete+all+required+fields.");
  exit();
}

function normalize($s){
    return strtoupper(trim(preg_replace('/\s+/', ' ', $s)));
}

$_SESSION['client_info'] = [
  'last_name'   => normalize($lastname),
  'first_name'  => normalize($firstname),
  'middle_name' => normalize($middlename),
  'address'     => $address,
  'cp_no'       => $cp_no,
];

$_SESSION['selected_service'] = $service;

if ($service === 'tax') {
  header("Location: pay_tax.php");
  exit();
}

if ($service === 'cert') {
  header("Location: select_certificate.php");
  exit();
}

if ($service === 'svc') {
  header("Location: select_service.php");
  exit();
}

header("Location: index.php?error=Invalid+transaction.");
exit();