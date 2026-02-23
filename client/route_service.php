<?php
session_start();

function clean_phone($v) {
    $v = trim($v ?? '');
    // remove spaces, dashes, parentheses
    $v = preg_replace('/[\s\-\(\)]/', '', $v);
    return $v;
}

$firstname  = trim($_POST['firstname'] ?? '');
$middlename = trim($_POST['middlename'] ?? '');
$lastname   = trim($_POST['lastname'] ?? '');
$address    = trim($_POST['address'] ?? '');
$cp_no      = clean_phone($_POST['cp_no'] ?? '');
$service    = $_POST['service'] ?? '';

$validService = in_array($service, ['cert','tax','svc'], true);

// ✅ validate phone properly
$validPhone = preg_match('/^(09\d{9}|\+63\d{10})$/', $cp_no);

if ($firstname === '' || $lastname === '' || $address === '' || !$validService || !$validPhone) {
    header("Location: index.php?error=Please+fill+all+required+fields");
    exit();
}

$_SESSION['client_info'] = [
    'firstname'  => $firstname,
    'middlename' => $middlename,
    'lastname'   => $lastname,
    'address'    => $address,
    'cp_no'      => $cp_no
];

$_SESSION['selected_service'] = $service;

if ($service === 'cert') {
    header("Location: select_cert.php");
    exit();
}

if ($service === 'tax') {
    header("Location: thank_you_tax.php");
    exit();
}

// svc
header("Location: select_service.php");
exit();