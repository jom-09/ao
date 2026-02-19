<?php
require_once "../config/database.php";
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_POST['import'])) {

    $allowed_tables = [
        'alicia','cabugao','dagupan','diodol','dumabel','dungo',
        'guinalbin','nagabgaban','palacian','pinaripad_norte',
        'pinaripad_sur','progreso','ramos','rangayan',
        'san_antonio','san_benigno','san_francisco','san_leonardo',
        'san_manuel','san_ramon','victoria',
        'villa_pagaduan','villa_santiago','villa_ventura'
    ];

    $table = $_POST['barangay'];

    if (!in_array($table, $allowed_tables)) {
        die("Invalid barangay selected.");
    }

    ini_set('memory_limit', '1024M');

    set_time_limit(300);

    $file = $_FILES['excel']['tmp_name'];

    if (!$file) {
        die("No file uploaded.");
    }

    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    $conn->begin_transaction();

    $success = 0;
    $failed = 0;

    foreach ($rows as $index => $row) {

        if ($index == 0) continue;
        if (empty(array_filter($row))) continue;

        $declared_owner    = $conn->real_escape_string($row[0] ?? '');
        $owner_address     = $conn->real_escape_string($row[1] ?? '');
        $property_location = $conn->real_escape_string($row[2] ?? '');
        $title             = $conn->real_escape_string($row[3] ?? '');
        $lot               = $conn->real_escape_string($row[4] ?? '');
        $arp               = $conn->real_escape_string($row[5] ?? '');
        $pin               = $conn->real_escape_string($row[6] ?? '');
        $classification    = $conn->real_escape_string($row[7] ?? '');
        $actual_use        = $conn->real_escape_string($row[8] ?? '');
        $area              = $conn->real_escape_string($row[9] ?? '');
        $mv                = $conn->real_escape_string($row[10] ?? '');
        $av                = $conn->real_escape_string($row[11] ?? '');
        $taxability        = $conn->real_escape_string($row[12] ?? '');
        $effectivity       = $conn->real_escape_string($row[13] ?? '');
        $cancellation      = $conn->real_escape_string($row[14] ?? '');

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
                ) VALUES (
                    '$declared_owner',
                    '$owner_address',
                    '$property_location',
                    '$title',
                    '$lot',
                    '$arp',
                    '$pin',
                    '$classification',
                    '$actual_use',
                    '$area',
                    '$mv',
                    '$av',
                    '$taxability',
                    '$effectivity',
                    '$cancellation'
                )";

        if ($conn->query($sql)) {
            $success++;
        } else {
            $failed++;
        }
    }

    $conn->commit();

    echo "<h3>Import Finished for " . strtoupper($table) . "</h3>";
    echo "Success: $success <br>";
    echo "Failed: $failed";
}
?>
