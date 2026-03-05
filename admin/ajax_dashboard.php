<?php
require_once "../includes/auth_check.php";
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

header('Content-Type: application/json; charset=utf-8');

$allowed_tables = [
  'alicia','cabugao','dagupan','diodol','dumabel','dungo',
  'guinalbin','nagabgaban','palacian','pinaripad_norte',
  'pinaripad_sur','progreso','ramos','rangayan',
  'san_antonio','san_benigno','san_francisco','san_leonardo',
  'san_manuel','san_ramon','victoria',
  'villa_pagaduan','villa_santiago','villa_ventura'
];

try {
  $pending_count  = (int)$conn->query("SELECT COUNT(*) c FROM requests WHERE status='PENDING'")->fetch_assoc()['c'];
  $paid_count     = (int)$conn->query("SELECT COUNT(*) c FROM requests WHERE status='PAID'")->fetch_assoc()['c'];

  $total_faas = 0;
  foreach($allowed_tables as $table){
    $total_faas += (int)$conn->query("SELECT COUNT(*) c FROM `$table`")->fetch_assoc()['c'];
  }

  $master_total = (int)$conn->query("SELECT COUNT(*) c FROM land_holdings_master")->fetch_assoc()['c'];

  $cert_items = (int)$conn->query("
    SELECT COUNT(*) c
    FROM request_items ri
    JOIN requests r ON r.id = ri.request_id
    WHERE r.status IN ('PENDING','PAID')
  ")->fetch_assoc()['c'];

  $service_items = (int)$conn->query("
    SELECT COUNT(*) c
    FROM requested_services rs
    JOIN requests r ON r.id = rs.request_id
    WHERE r.status IN ('PENDING','PAID')
  ")->fetch_assoc()['c'];

  // weekly (last 8 weeks)
  $wkRes = $conn->query("
    SELECT YEARWEEK(created_at, 1) AS wk, COUNT(*) AS c
    FROM requests
    WHERE status IN ('PENDING','PAID')
    GROUP BY wk
    ORDER BY wk DESC
    LIMIT 8
  ");

  $tmp = [];
  while($r = $wkRes->fetch_assoc()){
    $wk = (string)$r['wk'];
    $year = substr($wk, 0, 4);
    $week = substr($wk, 4, 2);
    $tmp[] = ['label'=>"Wk {$week} {$year}", 'count'=>(int)$r['c']];
  }
  $tmp = array_reverse($tmp);

  $weekly_labels = array_map(fn($x)=>$x['label'], $tmp);
  $weekly_counts = array_map(fn($x)=>$x['count'], $tmp);

  echo json_encode([
    'ok' => true,
    'pending_count' => $pending_count,
    'paid_count' => $paid_count,
    'total_faas' => $total_faas,
    'master_total' => $master_total,
    'cert_items' => $cert_items,
    'service_items' => $service_items,
    'weekly_labels' => $weekly_labels,
    'weekly_counts' => $weekly_counts,
  ]);

} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Server error']);
}