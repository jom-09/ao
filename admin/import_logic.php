<?php
require_once "../config/database.php";
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_POST['import'])) {
	die("Invalid request.");
}

$allowed_tables = [
	'alicia','cabugao','dagupan','diodol','dumabel','dungo',
	'guinalbin','nagabgaban','palacian','pinaripad_norte',
	'pinaripad_sur','progreso','ramos','rangayan',
	'san_antonio','san_benigno','san_francisco','san_leonardo',
	'san_manuel','san_ramon','victoria',
	'villa_pagaduan','villa_santiago','villa_ventura','ligaya'
];

$destination = $_POST['destination'] ?? '';
$table = '';

if ($destination === 'master') {
	$table = 'land_holdings_master';
} elseif ($destination === 'barangay') {
	$table = $_POST['barangay'] ?? '';

	if (!in_array($table, $allowed_tables, true)) {
		die("Invalid barangay selected.");
	}
} else {
	die("Invalid destination.");
}

ini_set('memory_limit', '1024M');
set_time_limit(300);

$file = $_FILES['excel']['tmp_name'] ?? '';

if (!$file) {
	die("No file uploaded.");
}

$spreadsheet = IOFactory::load($file);
$sheet = $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, false);

$conn->begin_transaction();

$success = 0;
$failed = 0;

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
	cancellation,
	north,
	east,
	south,
	west,
	ref_td,
	ref_pin,
	beneficial_user,
	ass_level,
	rec_app,
	app_by,
	prev_td,
	prev_ass,
	prev_owner,
	memo
) VALUES (
	?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
	?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
	$conn->rollback();
	die("Prepare failed: " . $conn->error);
}

foreach ($rows as $index => $row) {

	if ($index === 0) {
		continue;
	}

	if (empty(array_filter($row))) {
		continue;
	}

	$declared_owner    = trim((string)($row[0] ?? ''));
	$owner_address     = trim((string)($row[1] ?? ''));
	$property_location = trim((string)($row[2] ?? ''));
	$title             = trim((string)($row[3] ?? ''));
	$lot               = trim((string)($row[4] ?? ''));
	$arp               = trim((string)($row[5] ?? ''));
	$pin               = trim((string)($row[6] ?? ''));
	$classification    = trim((string)($row[7] ?? ''));
	$actual_use        = trim((string)($row[8] ?? ''));
	$area              = trim((string)($row[9] ?? ''));
	$mv                = trim((string)($row[10] ?? ''));
	$av                = trim((string)($row[11] ?? ''));
	$taxability        = trim((string)($row[12] ?? ''));
	$effectivity       = trim((string)($row[13] ?? ''));
	$cancellation      = trim((string)($row[14] ?? ''));

	$north             = trim((string)($row[15] ?? ''));
	$east              = trim((string)($row[16] ?? ''));
	$south             = trim((string)($row[17] ?? ''));
	$west              = trim((string)($row[18] ?? ''));
	$ref_td            = trim((string)($row[19] ?? ''));
	$ref_pin           = trim((string)($row[20] ?? ''));
	$beneficial_user   = trim((string)($row[21] ?? ''));
	$ass_level         = trim((string)($row[22] ?? ''));
	$rec_app           = trim((string)($row[23] ?? ''));
	$app_by            = trim((string)($row[24] ?? ''));
	$prev_td           = trim((string)($row[25] ?? ''));
	$prev_ass          = trim((string)($row[26] ?? ''));
	$prev_owner        = trim((string)($row[27] ?? ''));
	$memo              = trim((string)($row[28] ?? ''));

	if ($arp === '') {
		$failed++;
		continue;
	}

	$stmt->bind_param(
		"sssssssssssssssssssssssssssss",
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
		$cancellation,
		$north,
		$east,
		$south,
		$west,
		$ref_td,
		$ref_pin,
		$beneficial_user,
		$ass_level,
		$rec_app,
		$app_by,
		$prev_td,
		$prev_ass,
		$prev_owner,
		$memo
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
echo "Success: " . (int)$success . "<br>";
echo "Failed: " . (int)$failed;
?>