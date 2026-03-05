<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

header('Content-Type: application/json; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {

  // -------------------------
  // Pending Requests (CERT/SVC)
  // -------------------------
  $sql = "
    SELECT
      r.id,
      CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname,
      c.address,
      c.purpose,
      r.total_amount,
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
      ) AS service_list
    FROM requests r
    JOIN clients c ON r.client_id = c.id
    WHERE r.status='PENDING'
    ORDER BY r.created_at DESC
    LIMIT 500
  ";
  $res = $conn->query($sql);

  $pending = [];
  while($row = $res->fetch_assoc()){
    $items = "-";
    if (!empty($row['certificate_list'])) $items = $row['certificate_list'];
    elseif (!empty($row['service_list'])) $items = $row['service_list'];

    $pending[] = [
      'id' => (int)$row['id'],
      'fullname' => (string)$row['fullname'],
      'address' => (string)($row['address'] ?? ''),
      'purpose' => (string)$row['purpose'],
      'items' => (string)$items,
      'total_amount' => (float)$row['total_amount'],
      'created_at' => (string)$row['created_at'],
      'date_text' => date('M d, Y', strtotime($row['created_at']))
    ];
  }

  // -------------------------
  // Pending Tax Requests
  // -------------------------
  $taxSql = "
    SELECT id, declared_owner, arp_no, assessed_value, created_at, status
    FROM tax_requests
    WHERE status='PENDING'
    ORDER BY created_at DESC
    LIMIT 500
  ";
  $taxRes = $conn->query($taxSql);

  $tax_pending = [];
  while($t = $taxRes->fetch_assoc()){
    $avRaw = (string)($t['assessed_value'] ?? '0');
    $av    = (float)str_replace([',',' '], '', $avRaw);
    $base  = $av * 0.02;

    $tax_pending[] = [
      'id' => (int)$t['id'],
      'declared_owner' => (string)$t['declared_owner'],
      'arp_no' => (string)$t['arp_no'],
      'assessed_value' => (float)$av,
      'base_tax' => (float)$base,
      'status' => (string)($t['status'] ?? 'PENDING'),
      'created_at' => (string)$t['created_at'],
      'date_text' => date('M d, Y', strtotime($t['created_at']))
    ];
  }

  echo json_encode([
    'ok' => true,
    'pending_count' => count($pending),
    'tax_pending_count' => count($tax_pending),
    'pending' => $pending,
    'tax_pending' => $tax_pending
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Server error.',
  ]);
}