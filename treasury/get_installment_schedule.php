<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function out($data){
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['error' => 'Invalid request']);
}

$taxId = (int)($_POST['tax_request_id'] ?? 0);
if ($taxId <= 0) {
    out(['error' => 'Invalid tax request id']);
}

try {

    // Get tax request info
    $stmt = $conn->prepare("
        SELECT id, status, term_amount, COALESCE(paid_at, created_at) AS started_at
        FROM tax_requests
        WHERE id=? AND status='INSTALLMENT'
        LIMIT 1
    ");
    $stmt->bind_param("i", $taxId);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$req) {
        out(['error' => 'Installment record not found']);
    }

    $termAmount = (float)$req['term_amount'];

    // Determine year
    $startedAt = $req['started_at'] ?? date('Y-m-d H:i:s');
    $year = (int)date('Y', strtotime($startedAt));

    // Fixed quarter schedule
    $quarters = [
        1 => ['coverage' => 'Jan - Mar', 'due' => sprintf('%d-03-31', $year)],
        2 => ['coverage' => 'Apr - Jun', 'due' => sprintf('%d-06-30', $year)],
        3 => ['coverage' => 'Jul - Sep', 'due' => sprintf('%d-09-30', $year)],
        4 => ['coverage' => 'Oct - Dec', 'due' => sprintf('%d-12-31', $year)],
    ];

    // Ensure installment rows exist
    $ins = $conn->prepare("
        INSERT IGNORE INTO tax_installments 
        (tax_request_id, year, quarter, coverage, due_date, status)
        VALUES (?,?,?,?,?,'PENDING')
    ");

    foreach ($quarters as $q => $meta) {
        $cov = $meta['coverage'];
        $due = $meta['due'];
        $ins->bind_param("iiiss", $taxId, $year, $q, $cov, $due);
        $ins->execute();
    }

    $ins->close();

    // Update rows if schedule changed
    $upd = $conn->prepare("
        UPDATE tax_installments
        SET coverage=?, due_date=?
        WHERE tax_request_id=? AND year=? AND quarter=? AND status='PENDING'
    ");

    foreach ($quarters as $q => $meta) {
        $cov = $meta['coverage'];
        $due = $meta['due'];
        $upd->bind_param("ssiii", $cov, $due, $taxId, $year, $q);
        $upd->execute();
    }

    $upd->close();

    // Fetch installment schedule
    $stmt = $conn->prepare("
        SELECT quarter, coverage, due_date, status, paid_at
        FROM tax_installments
        WHERE tax_request_id=? AND year=?
        ORDER BY quarter ASC
    ");

    $stmt->bind_param("ii", $taxId, $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];

    while ($row = $res->fetch_assoc()) {

        $dueTxt = $row['due_date']
            ? date('M d, Y', strtotime($row['due_date']))
            : '';

        $data[] = [
            'quarter'        => (int)$row['quarter'],
            'coverage'       => (string)$row['coverage'],
            'due_date'       => (string)$row['due_date'],
            'due_date_text'  => $dueTxt,
            'status'         => (string)$row['status'],
            'paid_at'        => $row['paid_at'],
            'amount'         => $termAmount
        ];
    }

    $stmt->close();

    out($data);

} catch (Throwable $e) {

    out(['error' => $e->getMessage()]);

}