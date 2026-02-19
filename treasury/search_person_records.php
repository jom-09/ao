<?php
require_once "../config/database.php";

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $barangay = $_POST['barangay'];
    $owner_name = $_POST['owner_name'];
    
    // Sanitize table name (prevent SQL injection)
    $allowed_tables = [
        'alicia','cabugao','dagupan','diodol','dumabel','dungo',
        'guinalbin','nagabgaban','palacian','pinaripad_norte',
        'pinaripad_sur','progreso','ramos','rangayan',
        'san_antonio','san_benigno','san_francisco','san_leonardo',
        'san_manuel','san_ramon','victoria',
        'villa_pagaduan','villa_santiago','villa_ventura'
    ];
    
    if(in_array($barangay, $allowed_tables)) {
        $sql = "SELECT * FROM `$barangay` WHERE declared_owner LIKE ?";
        $stmt = $conn->prepare($sql);
        $searchTerm = "%$owner_name%";
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        while($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        
        echo json_encode($records);
    } else {
        echo json_encode(['error' => 'Invalid barangay']);
    }
}
?>