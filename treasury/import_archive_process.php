<?php
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

require_once "../includes/auth_treasury.php";
require_once "../config/database_tax_archive.php";
require_once "../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function cleanText($value): string {
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

function cleanDecimal($value): float {
    if ($value === null || $value === '') return 0.00;
    $value = str_replace(',', '', trim((string)$value));
    return is_numeric($value) ? (float)$value : 0.00;
}

function normalizeHeader($value): string {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    $value = trim($value, '_');
    return $value;
}

function normalizeDateValue($value): string {
    if ($value === null || $value === '') return '';

    if (is_numeric($value)) {
        try {
            return ExcelDate::excelToDateTimeObject($value)->format('m/d/y');
        } catch (Throwable $e) {
            return trim((string)$value);
        }
    }

    $value = trim((string)$value);

    $formats = [
        'm/d/y', 'm/d/Y', 'n/j/y', 'n/j/Y',
        'Y-m-d', 'd/m/Y', 'd-m-Y', 'm-d-Y'
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('m/d/y');
        }
    }

    return $value;
}

function buildRowHash(array $data): string {
    return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: import_archive.php?error=Invalid request.");
    exit;
}

if (!isset($_FILES['archive_file']) || $_FILES['archive_file']['error'] !== UPLOAD_ERR_OK) {
    header("Location: import_archive.php?error=Please upload a valid file.");
    exit;
}

$batchName  = cleanText($_POST['batch_name'] ?? '');
$remarks    = cleanText($_POST['remarks'] ?? '');
$importedBy = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Treasury User';

if ($batchName === '') {
    header("Location: import_archive.php?error=Batch name is required.");
    exit;
}

$originalFileName = $_FILES['archive_file']['name'];
$tmpFile          = $_FILES['archive_file']['tmp_name'];
$extension        = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

$allowed = ['xlsx', 'xls', 'csv'];
if (!in_array($extension, $allowed, true)) {
    header("Location: import_archive.php?error=Invalid file type. Allowed only xlsx, xls, csv.");
    exit;
}

$expectedHeaders = [
    'type', 'date', 'name', 'period', 'or_no', 'td_no', 'name_brgy',
    'r1', 'r2', 'r3', 'r4', 'r5', 'r6', 'r7', 'r8', 'r9', 'r10',
    'r11', 'r12', 'r13', 'r14', 'total'
];

try {
    $reader = IOFactory::createReaderForFile($tmpFile);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($tmpFile);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);

    if (!$rows || count($rows) < 2) {
        throw new Exception("The uploaded file is empty or has no data rows.");
    }

    $headerRow = $rows[0];
    $normalizedHeaders = array_map('normalizeHeader', $headerRow);

    $missingHeaders = [];
    foreach ($expectedHeaders as $expected) {
        if (!in_array($expected, $normalizedHeaders, true)) {
            $missingHeaders[] = $expected;
        }
    }

    if (!empty($missingHeaders)) {
        throw new Exception("Missing header(s): " . implode(', ', $missingHeaders));
    }

    $headerMap = [];
    foreach ($normalizedHeaders as $index => $headerName) {
        $headerMap[$headerName] = $index;
    }

    $taxConn->begin_transaction();

    $stmtBatch = $taxConn->prepare("
        INSERT INTO import_batches (batch_name, source_file, remarks, imported_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmtBatch->bind_param("ssss", $batchName, $originalFileName, $remarks, $importedBy);
    $stmtBatch->execute();
    $batchId = $taxConn->insert_id;

    $stmtInsert = $taxConn->prepare("
        INSERT INTO taxpayer_raw_imports (
            batch_id, row_num, type, date, name, period, or_no, td_no, name_brgy,
            r1, r2, r3, r4, r5, r6, r7, r8, r9, r10, r11, r12, r13, r14, total, row_hash
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $totalRows     = 0;
    $insertedRows  = 0;
    $skippedRows   = 0;
    $duplicateRows = 0; // for display only, not blocking insert

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        $type      = cleanText($row[$headerMap['type']] ?? '');
        $date      = normalizeDateValue($row[$headerMap['date']] ?? '');
        $name      = cleanText($row[$headerMap['name']] ?? '');
        $period    = cleanText($row[$headerMap['period']] ?? '');
        $orNo      = cleanText($row[$headerMap['or_no']] ?? '');
        $tdNo      = cleanText($row[$headerMap['td_no']] ?? '');
        $name_brgy = cleanText($row[$headerMap['name_brgy']] ?? '');

        $r1  = cleanDecimal($row[$headerMap['r1']] ?? 0);
        $r2  = cleanDecimal($row[$headerMap['r2']] ?? 0);
        $r3  = cleanDecimal($row[$headerMap['r3']] ?? 0);
        $r4  = cleanDecimal($row[$headerMap['r4']] ?? 0);
        $r5  = cleanDecimal($row[$headerMap['r5']] ?? 0);
        $r6  = cleanDecimal($row[$headerMap['r6']] ?? 0);
        $r7  = cleanDecimal($row[$headerMap['r7']] ?? 0);
        $r8  = cleanDecimal($row[$headerMap['r8']] ?? 0);
        $r9  = cleanDecimal($row[$headerMap['r9']] ?? 0);
        $r10 = cleanDecimal($row[$headerMap['r10']] ?? 0);
        $r11 = cleanDecimal($row[$headerMap['r11']] ?? 0);
        $r12 = cleanDecimal($row[$headerMap['r12']] ?? 0);
        $r13 = cleanDecimal($row[$headerMap['r13']] ?? 0);
        $r14 = cleanDecimal($row[$headerMap['r14']] ?? 0);
        $total = cleanDecimal($row[$headerMap['total']] ?? 0);

        $isRowEmpty =
            $type === '' &&
            $date === '' &&
            $name === '' &&
            $period === '' &&
            $orNo === '' &&
            $tdNo === '' &&
            $name_brgy === '' &&
            $r1 == 0 && $r2 == 0 && $r3 == 0 && $r4 == 0 && $r5 == 0 &&
            $r6 == 0 && $r7 == 0 && $r8 == 0 && $r9 == 0 && $r10 == 0 &&
            $r11 == 0 && $r12 == 0 && $r13 == 0 && $r14 == 0 && $total == 0;

        if ($isRowEmpty) {
            $skippedRows++;
            continue;
        }

        $totalRows++;

        $hashPayload = [
            'type'      => $type,
            'date'      => $date,
            'name'      => $name,
            'period'    => $period,
            'or_no'     => $orNo,
            'td_no'     => $tdNo,
            'name_brgy' => $name_brgy,
            'r1'        => $r1,
            'r2'        => $r2,
            'r3'        => $r3,
            'r4'        => $r4,
            'r5'        => $r5,
            'r6'        => $r6,
            'r7'        => $r7,
            'r8'        => $r8,
            'r9'        => $r9,
            'r10'       => $r10,
            'r11'       => $r11,
            'r12'       => $r12,
            'r13'       => $r13,
            'r14'       => $r14,
            'total'     => $total
        ];

        $rowHash = buildRowHash($hashPayload);
        $rowNum  = $i + 1;

        $stmtInsert->bind_param(
            "iisssssssddddddddddddddds",
            $batchId, $rowNum, $type, $date, $name, $period, $orNo, $tdNo, $name_brgy,
            $r1, $r2, $r3, $r4, $r5, $r6, $r7, $r8, $r9, $r10, $r11, $r12, $r13, $r14, $total, $rowHash
        );
        $stmtInsert->execute();
        $insertedRows++;

        if ($insertedRows % 1000 === 0) {
            $taxConn->commit();
            $taxConn->begin_transaction();
        }
    }

    $stmtUpdateBatch = $taxConn->prepare("
        UPDATE import_batches
        SET total_rows = ?, inserted_rows = ?, skipped_rows = ?, duplicate_rows = ?
        WHERE id = ?
    ");
    $stmtUpdateBatch->bind_param("iiiii", $totalRows, $insertedRows, $skippedRows, $duplicateRows, $batchId);
    $stmtUpdateBatch->execute();

    $taxConn->commit();

    $msg = "Import completed. Batch ID #{$batchId}. Total rows: {$totalRows}, Inserted: {$insertedRows}, Skipped: {$skippedRows}, Duplicates: {$duplicateRows}";
    header("Location: import_archive.php?success=" . urlencode($msg));
    exit;

} catch (Throwable $e) {
    try {
        $taxConn->rollback();
    } catch (Throwable $ignored) {}

    header("Location: import_archive.php?error=" . urlencode("Import failed: " . $e->getMessage()));
    exit;
}