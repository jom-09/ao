<?php
require_once "../config/database.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barangay   = $_POST['barangay'] ?? '';
    $owner_name = trim((string)($_POST['owner_name'] ?? ''));

    $allowed_tables = [
        'alicia','cabugao','dagupan','diodol','dumabel','dungo',
        'guinalbin','nagabgaban','palacian','pinaripad_norte',
        'pinaripad_sur','progreso','ramos','rangayan',
        'san_antonio','san_benigno','san_francisco','san_leonardo',
        'san_manuel','san_ramon','victoria',
        'villa_pagaduan','villa_santiago','villa_ventura'
    ];

    if (!in_array($barangay, $allowed_tables, true)) {
        echo json_encode(['error' => 'Invalid barangay']);
        exit;
    }

    if ($owner_name === '') {
        echo json_encode([]);
        exit;
    }

    /**
     * ✅ Return: owner, address, arp no, assessed_value
     * NOTE: AV column name varies per FAAS export.
     * We'll try common column names and fall back to 0 if not found.
     */

    // Inspect columns to find assessed value column safely
    $cols = [];
    try {
        $resCols = $conn->query("SHOW COLUMNS FROM `$barangay`");
        while ($c = $resCols->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
    } catch (Throwable $e) {
        echo json_encode(['error' => 'Unable to read table columns']);
        exit;
    }

    // ✅ Common AV column candidates (add more if needed)
    $avCandidates = [
        'assessed_value',
        'Assessed_Value',
        'ASSESSED_VALUE',
        'Assessed Value',
        'ASSESSED VALUE',
        'AssessedValue',
        'Assessed_Val',
        'assessed_val',
        'AV',
        'av'
    ];

    $avField = null;
    foreach ($avCandidates as $cand) {
        if (in_array($cand, $cols, true)) {
            $avField = $cand;
            break;
        }
    }

    // Build safe AV select
    $avSelect = $avField ? ("`".$avField."` AS assessed_value") : "0 AS assessed_value";

    // ✅ Return: name, address, arp no + assessed value
    $sql = "
        SELECT
            declared_owner AS owner,
            owner_address  AS address,
            `ARP_No.`      AS arp_no,
            {$avSelect}
        FROM `$barangay`
        WHERE declared_owner LIKE ?
        ORDER BY declared_owner ASC
        LIMIT 200
    ";

    $stmt = $conn->prepare($sql);
    $searchTerm = "%{$owner_name}%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();

    $result = $stmt->get_result();
    $records = [];

    while ($row = $result->fetch_assoc()) {
        // normalize assessed_value to numeric (remove commas/spaces)
        $raw = (string)($row['assessed_value'] ?? '0');
        $raw = str_replace([',', ' '], '', $raw);
        $row['assessed_value'] = is_numeric($raw) ? (float)$raw : 0.0;

        $records[] = $row;
    }

    echo json_encode($records);
    exit;
}

echo json_encode(['error' => 'Invalid request']);