<?php
session_start();

/*
|--------------------------------------------------------------------------
| Receive QR Payload
|--------------------------------------------------------------------------
*/

$raw = trim($_POST['qr_payload'] ?? '');

if ($raw === '') {
    header("Location: scan.php?error=Empty+QR+data");
    exit();
}

/*
|--------------------------------------------------------------------------
| Decode JSON from QR
|--------------------------------------------------------------------------
*/

$data = json_decode($raw, true);

if (!is_array($data) || empty($data['client'])) {
    header("Location: scan.php?error=Invalid+QR+format");
    exit();
}

$c = $data['client'];

/*
|--------------------------------------------------------------------------
| Extract Client Info
|--------------------------------------------------------------------------
*/

$firstname  = trim((string)($c['first_name'] ?? ''));
$middlename = trim((string)($c['middle_name'] ?? ''));
$lastname   = trim((string)($c['last_name'] ?? ''));
$address    = trim((string)($c['address'] ?? ''));
$cp_no      = trim((string)($c['cp_no'] ?? ''));

if ($firstname === '' || $lastname === '' || $address === '' || $cp_no === '') {
    header("Location: scan.php?error=QR+missing+required+client+fields");
    exit();
}

/*
|--------------------------------------------------------------------------
| Save Client Info (MATCHES OFFICE SYSTEM STRUCTURE)
|--------------------------------------------------------------------------
*/

$_SESSION['client_info'] = [
    'firstname'  => $firstname,
    'middlename' => $middlename,
    'lastname'   => $lastname,
    'address'    => $address,
    'cp_no'      => $cp_no,
    'purpose'    => 'QR Appointment'
];

/*
|--------------------------------------------------------------------------
| Extract Items From QR
|--------------------------------------------------------------------------
*/

$items = $data['items'] ?? [];
if (!is_array($items)) {
    $items = [];
}

$_SESSION['qr_items'] = $items;

/*
|--------------------------------------------------------------------------
| Determine Flow (cert OR svc)
|--------------------------------------------------------------------------
*/

$flow = 'cert'; // default

foreach ($items as $it) {
    if (($it['type'] ?? '') === 'service') {
        $flow = 'svc';
        break;
    }
}

$_SESSION['selected_service'] = $flow;

/*
|--------------------------------------------------------------------------
| Extract Certificate Labels (for cert auto-match)
|--------------------------------------------------------------------------
*/

$qrCertLabels = [];

foreach ($items as $it) {
    if (($it['type'] ?? '') === 'certificate') {
        $lbl = trim((string)($it['label'] ?? ''));
        if ($lbl !== '') {
            $qrCertLabels[] = $lbl;
        }
    }
}

$_SESSION['qr_cert_labels'] = array_values(array_unique($qrCertLabels));

/*
|--------------------------------------------------------------------------
| Extract Service Labels (for service auto-match)
|--------------------------------------------------------------------------
*/

$qrServiceLabels = [];

foreach ($items as $it) {
    if (($it['type'] ?? '') === 'service') {
        $lbl = trim((string)($it['label'] ?? ''));
        if ($lbl !== '') {
            $qrServiceLabels[] = $lbl;
        }
    }
}

$_SESSION['qr_service_labels'] = array_values(array_unique($qrServiceLabels));

/*
|--------------------------------------------------------------------------
| Redirect Based on Flow
|--------------------------------------------------------------------------
*/

if ($flow === 'svc') {
    header("Location: select_service.php?from=qr");
    exit();
}

header("Location: select_cert.php?from=qr");
exit();