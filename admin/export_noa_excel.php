<?php
require_once "../includes/auth_check.php";
require_once "../config/database.php";
require_once "../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$week_key  = isset($_GET['week_key']) ? (int)$_GET['week_key'] : 0;
$owner_key = $_GET['owner_key'] ?? '';

if ($week_key <= 0 || $owner_key === '') {
    die("Invalid export parameters.");
}

/* ===============================
   GET LOGS (ALL NEEDED FIELDS ARE HERE)
=================================*/
$stmt = $conn->prepare("
    SELECT
        barangay,
        declared_owner,
        owner_address,
        arp_no,
        pin_no,
        property_location,
        classification,
        mv,
        av,
        created_at
    FROM notice_of_assessment_logs
    WHERE week_key=? AND owner_key=?
    ORDER BY created_at ASC
");
$stmt->bind_param("is", $week_key, $owner_key);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) $items[] = $row;
$stmt->close();

if (empty($items)) {
    die("No records found.");
}

$ownerDisplay = $items[0]['declared_owner'] ?? 'Owner';
$address      = $items[0]['owner_address'] ?? '';

/* ===============================
   LOAD TEMPLATE
=================================*/
$templatePath = realpath(__DIR__ . "/../templates/NOA_TEMPLATE.xlsx");
if (!$templatePath || !file_exists($templatePath)) {
    die("Template not found: " . (__DIR__ . "/../templates/NOA_TEMPLATE.xlsx"));
}

$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getSheet(0);
$spreadsheet->setActiveSheetIndex(0);

/* ===============================
   HEADER PLACEMENT (AS YOU SPECIFIED)
   Owner = A7
   Address = A10
=================================*/
$sheet->setCellValue("F7", date('F d, Y'));
$sheet->setCellValue("A7", $ownerDisplay);
$sheet->setCellValue("A10", $address);

/* ===============================
   AUTO INSERT ROWS (UNLIMITED)
   Data starts row 17
   Template rows 17-22 (6 rows)
   Total row 23
=================================*/
$startRow     = 17;
$templateRows = 6;
$totalRow     = 23;

$count = count($items);

if ($count > $templateRows) {
    $extra = $count - $templateRows;

    // Insert rows before TOTAL row
    $sheet->insertNewRowBefore($totalRow, $extra);

    // New total row moved down
    $totalRow += $extra;

    // Copy style from last template data row (row 22)
    $styleSourceRow = $startRow + $templateRows - 1; // 22
    $sourceRange    = "A{$styleSourceRow}:F{$styleSourceRow}";
    $sourceHeight   = $sheet->getRowDimension($styleSourceRow)->getRowHeight();

    // Duplicate formatting to inserted rows
    for ($r = $styleSourceRow + 1; $r <= $totalRow - 1; $r++) {
        $sheet->duplicateStyle($sheet->getStyle($sourceRange), "A{$r}:F{$r}");
        if ($sourceHeight > 0) {
            $sheet->getRowDimension($r)->setRowHeight($sourceHeight);
        }
    }
}

/* ===============================
   WRITE DATA (AS YOU SPECIFIED)
   ARP = A17
   PIN = B17
   Location = C17 (use property_location if present else barangay)
   Classification = D17
   MV = E17
   AV = F17
=================================*/
$row = $startRow;

foreach ($items as $it) {
    $sheet->setCellValueExplicit("A{$row}", (string)($it['arp_no'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("B{$row}", (string)($it['pin_no'] ?? ''), DataType::TYPE_STRING);

    $loc = trim((string)($it['property_location'] ?? ''));
    if ($loc === '') {
        $loc = ucwords(str_replace('_', ' ', (string)($it['barangay'] ?? '')));
    }
    $sheet->setCellValue("C{$row}", $loc);

    $sheet->setCellValue("D{$row}", (string)($it['classification'] ?? ''));

    // keep numeric
    $sheet->setCellValue("E{$row}", (float)($it['mv'] ?? 0));
    $sheet->setCellValue("F{$row}", (float)($it['av'] ?? 0));

    $row++;
}

/* ===============================
   AUTO TOTAL MV & AV (always correct)
=================================*/
$lastDataRow = $startRow + $count - 1;

$sheet->setCellValue("E{$totalRow}", "=SUM(E{$startRow}:E{$lastDataRow})");
$sheet->setCellValue("F{$totalRow}", "=SUM(F{$startRow}:F{$lastDataRow})");

/* ===============================
   OUTPUT
=================================*/
while (ob_get_level() > 0) { ob_end_clean(); }

$filename = "NOA_Week{$week_key}_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $ownerDisplay) . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;