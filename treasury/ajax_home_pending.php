<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

header('Content-Type: application/json; charset=utf-8');

try {

  // -------------------------
  // Pending Requests (CERT/SVC ONLY)
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
      AND TRIM(c.purpose) <> 'Tax Clearance'
    ORDER BY r.created_at DESC
    LIMIT 500
  ";
  $res = $conn->query($sql);

  $pending = [];
  while($row = $res->fetch_assoc()){
    $items = "-";
    if (!empty($row['certificate_list'])) {
      $items = $row['certificate_list'];
    } elseif (!empty($row['service_list'])) {
      $items = $row['service_list'];
    }

    $pending[] = [
      'id' => (int)$row['id'],
      'fullname' => (string)($row['fullname'] ?? ''),
      'address' => (string)($row['address'] ?? ''),
      'purpose' => (string)($row['purpose'] ?? ''),
      'items' => (string)$items,
      'total_amount' => (float)($row['total_amount'] ?? 0),
      'created_at' => (string)($row['created_at'] ?? ''),
      'date_text' => !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : ''
    ];
  }

  // -------------------------
  // Tax Clearance Requests
  // ONLY show those NOT YET marked as done
  // (no existing PAID record in requests for that client)
  // -------------------------
  $taxClearanceSql = "
    SELECT
      c.id,
      c.firstname,
      c.middlename,
      c.lastname,
      c.address,
      c.cp_no,
      c.purpose,
      c.created_at
    FROM clients c
    WHERE TRIM(c.purpose) = 'Tax Clearance'
      AND NOT EXISTS (
        SELECT 1
        FROM requests r
        WHERE r.client_id = c.id
          AND r.status = 'PAID'
      )
    ORDER BY c.created_at DESC
    LIMIT 500
  ";
  $taxClearanceRes = $conn->query($taxClearanceSql);

  $tax_clearance = [];
  while($tc = $taxClearanceRes->fetch_assoc()){
    $fullname = trim(
      (string)($tc['firstname'] ?? '') . ' ' .
      (string)($tc['middlename'] ?? '') . ' ' .
      (string)($tc['lastname'] ?? '')
    );

    $tax_clearance[] = [
      'id' => (int)$tc['id'],
      'fullname' => $fullname,
      'address' => (string)($tc['address'] ?? ''),
      'cp_no' => (string)($tc['cp_no'] ?? ''),
      'purpose' => (string)($tc['purpose'] ?? ''),
      'created_at' => (string)($tc['created_at'] ?? ''),
      'date_text' => !empty($tc['created_at']) ? date('M d, Y', strtotime($tc['created_at'])) : ''
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
      'declared_owner' => (string)($t['declared_owner'] ?? ''),
      'arp_no' => (string)($t['arp_no'] ?? ''),
      'assessed_value' => (float)$av,
      'base_tax' => (float)$base,
      'status' => (string)($t['status'] ?? 'PENDING'),
      'created_at' => (string)($t['created_at'] ?? ''),
      'date_text' => !empty($t['created_at']) ? date('M d, Y', strtotime($t['created_at'])) : ''
    ];
  }

  echo json_encode([
    'ok' => true,
    'pending_count' => count($pending),
    'tax_clearance_count' => count($tax_clearance),
    'tax_pending_count' => count($tax_pending),
    'pending' => $pending,
    'tax_clearance' => $tax_clearance,
    'tax_pending' => $tax_pending
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Server error.',
    'message' => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}