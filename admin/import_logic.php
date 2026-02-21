<?php
require_once "../config/database.php";
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_POST['import'])) {
    die("Invalid request.");
}

// barangay tables allowed
$allowed_tables = [
    'alicia','cabugao','dagupan','diodol','dumabel','dungo',
    'guinalbin','nagabgaban','palacian','pinaripad_norte',
    'pinaripad_sur','progreso','ramos','rangayan',
    'san_antonio','san_benigno','san_francisco','san_leonardo',
    'san_manuel','san_ramon','victoria',
    'villa_pagaduan','villa_santiago','villa_ventura'
];

$destination = $_POST['destination'] ?? '';
$table = '';

// decide target table
if ($destination === 'master') {
    $table = 'land_holdings_master';
} elseif ($destination === 'barangay') {
    $table = $_POST['barangay'] ?? '';
    if (!in_array($table, $allowed_tables)) {
        die("Invalid barangay selected.");
    }
} else {
    die("Invalid destination.");
}

// perf limits
ini_set('memory_limit', '1024M');
set_time_limit(300);

// file
$file = $_FILES['excel']['tmp_name'] ?? '';
if (!$file) {
    die("No file uploaded.");
}

$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray();

$conn->begin_transaction();

$success = 0;
$failed = 0;

// ✅ Prepared statement (safe + fast)
$sql = "INSERT INTO `$table` (
            declared_owner,
            owner_address,
            property_location,
            title,
            lot,
            `ARP_No.`,
            `PIN_No.`,
            classification,
            actual_use,
            area,
            mv,
            av,
            taxability,
            effectivity,
            cancellation
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if(!$stmt){
    $conn->rollback();
    die("Prepare failed: " . $conn->error);
}

foreach ($rows as $index => $row) {

    // skip header
    if ($index == 0) continue;

    // skip blank row
    if (empty(array_filter($row))) continue;

    // map excel columns (0-14)
    $declared_owner    = trim($row[0] ?? '');
    $owner_address     = trim($row[1] ?? '');
    $property_location = trim($row[2] ?? '');
    $title             = trim($row[3] ?? '');
    $lot               = trim($row[4] ?? '');
    $arp               = trim($row[5] ?? '');
    $pin               = trim($row[6] ?? '');
    $classification    = trim($row[7] ?? '');
    $actual_use        = trim($row[8] ?? '');
    $area              = trim($row[9] ?? '');
    $mv                = trim($row[10] ?? '');
    $av                = trim($row[11] ?? '');
    $taxability        = trim($row[12] ?? '');
    $effectivity       = trim($row[13] ?? '');
    $cancellation      = trim($row[14] ?? '');

    // optional: skip if ARP is empty
    if ($arp === '') {
        $failed++;
        continue;
    }

    $stmt->bind_param(
        "sssssssssssssss",
        $declared_owner,
        $owner_address,
        $property_location,
        $title,
        $lot,
        $arp,
        $pin,
        $classification,
        $actual_use,
        $area,
        $mv,
        $av,
        $taxability,
        $effectivity,
        $cancellation
    );

    if ($stmt->execute()) {
        $success++;
    } else {
        $failed++;
    }
}

$stmt->close();
$conn->commit();

echo "<h3>Import Finished</h3>";
echo "Destination Table: <b>" . htmlspecialchars($table) . "</b><br>";
echo "Success: $success <br>";
echo "Failed: $failed";
?>