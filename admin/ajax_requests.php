<?php
require_once "../includes/auth_check.php";
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

header('Content-Type: application/json; charset=utf-8');

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

try {

  $sql = "
    SELECT
      r.id,
      CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname,
      c.address,
      c.purpose,
      r.total_amount,
      r.control_number,
      r.status,
      r.is_done,
      r.done_at,
      r.created_at,

      (
        SELECT GROUP_CONCAT(cert.certificate_name SEPARATOR ', ')
        FROM request_items ri
        JOIN certificates cert ON cert.id = ri.certificate_id
        WHERE ri.request_id = r.id
      ) AS certificate_list,

      (
        SELECT GROUP_CONCAT(s.service_name SEPARATOR ', ')
        FROM requested_services rs
        JOIN services s ON s.id = rs.service_id
        WHERE rs.request_id = r.id
      ) AS service_list,

      (SELECT COUNT(*) FROM request_items ri WHERE ri.request_id = r.id) AS cert_count,
      (SELECT COUNT(*) FROM requested_services rs WHERE rs.request_id = r.id) AS service_count

    FROM requests r
    JOIN clients c ON r.client_id = c.id
    WHERE r.status IN ('PENDING','PAID')
    ORDER BY r.created_at DESC
    LIMIT 500
  ";

  $res = $conn->query($sql);

  $rows = [];
  while($row = $res->fetch_assoc()){

    $certCount    = (int)($row['cert_count'] ?? 0);
    $serviceCount = (int)($row['service_count'] ?? 0);

    $items = "-";
    if ($certCount > 0 && !empty($row['certificate_list'])) {
      $items = $row['certificate_list'];
    } elseif ($serviceCount > 0 && !empty($row['service_list'])) {
      $items = $row['service_list'];
    }

    $createdAt = $row['created_at'] ?? null;

    $rows[] = [
      'id' => (int)$row['id'],
      'fullname' => h($row['fullname'] ?? '-'),
      'address' => h($row['address'] ?? '-'),
      'purpose' => h($row['purpose'] ?? '-'),
      'items' => h($items),
      'total_amount' => (float)($row['total_amount'] ?? 0),
      'control_number' => h($row['control_number'] ?? '-'),
      'status' => h($row['status'] ?? ''),
      'is_done' => ((int)($row['is_done'] ?? 0) === 1),
      'done_at' => $row['done_at'],
      'created_at' => $createdAt,
      'created_at_text' => $createdAt ? date('M d, Y', strtotime($createdAt)) : '-',
    ];
  }

  echo json_encode([
    'ok' => true,
    'rows' => $rows
  ]);

} catch (Throwable $e) {

  echo json_encode([
    'ok' => false,
    'error' => 'Server error: ' . $e->getMessage()
  ]);
}