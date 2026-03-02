<?php
require_once "../config/database.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barangay   = $_POST['barangay'] ?? '';
    $owner_name = trim($_POST['owner_name'] ?? '');

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

    // ONLY return: name, address, arp no
    $sql = "
        SELECT
            declared_owner AS owner,
            owner_address  AS address,
            `ARP_No.`      AS arp_no
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
        $records[] = $row;
    }

    echo json_encode($records);
    exit;
}
echo json_encode(['error' => 'Invalid request']);