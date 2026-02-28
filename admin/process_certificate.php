<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();
require_once "../includes/auth_check.php";
require_once "../config/database.php";

$allowed_tables = [
    'alicia','cabugao','dagupan','diodol','dumabel','dungo',
    'guinalbin','nagabgaban','palacian','pinaripad_norte',
    'pinaripad_sur','progreso','ramos','rangayan',
    'san_antonio','san_benigno','san_francisco','san_leonardo',
    'san_manuel','san_ramon','victoria',
    'villa_pagaduan','villa_santiago','villa_ventura'
];

require '../vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

$errors = [];

$certificates = [
    'tax_dec'        => 'Tax Declaration on Real Property',
    'no_improvement' => 'Certificate of No Improvement',
    'no_declared'    => 'Certificate of No Declared Property',
    'total_land'     => 'Total Land Holding',
    'actual_use'     => 'Actual Location',
];

if (isset($_POST['generate'])) {

    $cert_type      = $_POST['cert_type'] ?? '';
    $barangay       = $_POST['barangay'] ?? '';
    $arp_no         = trim($_POST['arp_no'] ?? '');
    $owner_name     = trim($_POST['owner_name'] ?? '');
    $owner_addressF = trim($_POST['owner_address_filter'] ?? '');

    // validations
    if (!isset($certificates[$cert_type])) $errors[] = "Invalid certificate type.";

    if ($cert_type === 'total_land') {
        if ($owner_name === '') $errors[] = "Owner name is required for Total Land Holding.";
    } else {
        if (!in_array($barangay, $allowed_tables, true)) $errors[] = "Invalid barangay.";
        if ($arp_no === '') $errors[] = "ARP No is required.";
    }

    if (empty($errors)) {

        $data = null;

        // ==========================
        // FETCH DATA PER CERT TYPE
        // ==========================
        if ($cert_type === 'total_land') {

            $nameLike = "%{$owner_name}%";

            if ($owner_addressF !== '') {
                $addrLike = "%{$owner_addressF}%";

                $stmt = $conn->prepare("
                    SELECT declared_owner, owner_address,
                           `ARP_No.` AS td_no,
                           property_location,
                           area,
                           classification,
                           mv,
                           av
                    FROM land_holdings_master
                    WHERE declared_owner LIKE ?
                      AND owner_address LIKE ?
                    ORDER BY declared_owner, owner_address, property_location, `ARP_No.`
                ");
                $stmt->bind_param("ss", $nameLike, $addrLike);
            } else {
                $stmt = $conn->prepare("
                    SELECT declared_owner, owner_address,
                           `ARP_No.` AS td_no,
                           property_location,
                           area,
                           classification,
                           mv,
                           av
                    FROM land_holdings_master
                    WHERE declared_owner LIKE ?
                    ORDER BY declared_owner, owner_address, property_location, `ARP_No.`
                ");
                $stmt->bind_param("s", $nameLike);
            }

            $stmt->execute();
            $res = $stmt->get_result();

            $holdings = [];
            $picked_owner = null;

            while ($r = $res->fetch_assoc()) {
                if ($picked_owner === null) {
                    $picked_owner = [
                        'declared_owner' => $r['declared_owner'],
                        'owner_address'  => $r['owner_address'],
                    ];
                }

                // avoid mixing same-name persons
                if ($r['declared_owner'] === $picked_owner['declared_owner'] &&
                    $r['owner_address'] === $picked_owner['owner_address']) {
                    $holdings[] = $r;
                }
            }

            if (!$picked_owner || count($holdings) === 0) {
                $errors[] = "No holdings found for that owner search. Try adding Address filter.";
            } else {
                $data = [
                    'declared_owner' => $picked_owner['declared_owner'],
                    'owner_address'  => $picked_owner['owner_address'],
                    'holdings'       => $holdings
                ];
            }

        } else {

            $stmt = $conn->prepare("SELECT * FROM `$barangay` WHERE `ARP_No.` = ?");
            $stmt->bind_param("s", $arp_no);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();

            if (!$data) {
                $errors[] = "No record found for ARP No: {$arp_no} in barangay {$barangay}";
            }
        }

        // ==========================
        // GENERATE DOCX
        // ==========================
        if (empty($errors) && $data) {

            switch ($cert_type) {

                case 'tax_dec': {
                    $template_path = '../templates/tax_declaration_template.docx';
                    if(!file_exists($template_path)) { $errors[] = "Template not found: $template_path"; break; }

                    $arp_final = $data['ARP_No.'] ?? '';
                    $cancels_final = '';

                    if (isset($data['cancellation']) && trim($data['cancellation']) !== '') {
                        $cancels_final = $arp_final;
                        $arp_final = $data['cancellation'];
                    }

                    $template = new TemplateProcessor($template_path);

                    $template->setValue('arp_no', $arp_final);
                    $template->setValue('cancels_td_no', $cancels_final);

                    $template->setValue('pin_no', $data['PIN_No.'] ?? '');
                    $template->setValue('declared_owner', $data['declared_owner'] ?? '');
                    $template->setValue('owner_address', $data['owner_address'] ?? '');
                    $template->setValue('property_location', $data['property_location'] ?? '');
                    $template->setValue('title', $data['title'] ?? '');
                    $template->setValue('lot', $data['lot'] ?? '');
                    $template->setValue('classification', $data['classification'] ?? '');
                    $template->setValue('actual_use', $data['actual_use'] ?? '');
                    $template->setValue('area', $data['area'] ?? '');
                    $template->setValue('mv', $data['mv'] ?? '');
                    $template->setValue('av', $data['av'] ?? '');

                    $filename = "Tax_Declaration_" . ($data['ARP_No.'] ?? 'record') . ".docx";

                    ob_clean();
                    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                    header("Content-Disposition: attachment; filename=\"$filename\"");
                    $template->saveAs("php://output");
                    exit;
                }

                case 'no_improvement': {
                    $template_path = '../templates/no_improvement_template.docx';
                    if(!file_exists($template_path)) { $errors[] = "Template not found: $template_path"; break; }

                    $template = new TemplateProcessor($template_path);

                    $template->setValue('arp_no', $data['ARP_No.'] ?? '');
                    $template->setValue('pin_no', $data['PIN_No.'] ?? '');
                    $template->setValue('declared_owner', $data['declared_owner'] ?? '');
                    $template->setValue('owner_address', $data['owner_address'] ?? '');
                    $template->setValue('property_location', $data['property_location'] ?? '');
                    $template->setValue('title', $data['title'] ?? '');
                    $template->setValue('lot', $data['lot'] ?? '');
                    $template->setValue('classification', $data['classification'] ?? '');
                    $template->setValue('actual_use', $data['actual_use'] ?? '');
                    $template->setValue('area', $data['area'] ?? '');

                    $template->setValue('day', date('d'));
                    $template->setValue('month', date('F'));
                    $template->setValue('year', date('Y'));

                    $filename = "No_Improvement_" . ($data['ARP_No.'] ?? 'record') . ".docx";

                    ob_clean();
                    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                    header("Content-Disposition: attachment; filename=\"$filename\"");
                    $template->saveAs("php://output");
                    exit;
                }

                case 'no_declared': {
                    $template_path = '../templates/no_declared_template.docx';
                    if(!file_exists($template_path)) { $errors[] = "Template not found: $template_path"; break; }

                    $template = new TemplateProcessor($template_path);

                    $template->setValue('declared_owner', $data['declared_owner'] ?? '');
                    $template->setValue('owner_address', $data['owner_address'] ?? '');

                    $template->setValue('day', date('d'));
                    $template->setValue('month', date('F'));
                    $template->setValue('year', date('Y'));

                    $filename = "No_Declared_" . (($data['declared_owner'] ?? 'record')) . ".docx";

                    ob_clean();
                    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                    header("Content-Disposition: attachment; filename=\"$filename\"");
                    $template->saveAs("php://output");
                    exit;
                }

                case 'total_land': {
                    $template_path = '../templates/total_land_template.docx';
                    if(!file_exists($template_path)) { $errors[] = "Template not found: $template_path"; break; }

                    if (!isset($data['holdings']) || !is_array($data['holdings']) || count($data['holdings']) === 0) {
                        $errors[] = "No holdings found for this owner.";
                        break;
                    }

                    $template = new TemplateProcessor($template_path);

                    $template->setValue('declared_owner', $data['declared_owner'] ?? '');
                    $template->setValue('owner_address', $data['owner_address'] ?? '');

                    $template->setValue('day', date('d'));
                    $template->setValue('month', date('F'));
                    $template->setValue('year', date('Y'));

                    $holdings = $data['holdings'];
                    $count = count($holdings);

                    $template->cloneRow('td_no', $count);

                    for ($i = 1; $i <= $count; $i++) {
                        $row = $holdings[$i - 1];
                        $template->setValue("td_no#$i", $row['td_no'] ?? '');
                        $template->setValue("property_location#$i", $row['property_location'] ?? '');
                        $template->setValue("area#$i", $row['area'] ?? '');
                        $template->setValue("classification#$i", $row['classification'] ?? '');
                        $template->setValue("mv#$i", $row['mv'] ?? '');
                        $template->setValue("av#$i", $row['av'] ?? '');
                    }

                    $safeOwner = preg_replace('/[^A-Za-z0-9_\- ]/', '', $data['declared_owner'] ?? '');
                    $safeOwner = str_replace(' ', '_', trim($safeOwner));
                    $filename = "Total_Land_Holding_" . ($safeOwner ?: 'record') . ".docx";

                    ob_clean();

                    $tmpFile = tempnam(sys_get_temp_dir(), 'docx_') . '.docx';
                    $template->saveAs($tmpFile);

                    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                    header('Content-Disposition: attachment; filename="'.$filename.'"');
                    header('Content-Length: ' . filesize($tmpFile));
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');

                    readfile($tmpFile);
                    @unlink($tmpFile);
                    exit;
                }

                case 'actual_use': {
                    // IMPORTANT: confirm your real template filename here
                    // If your template is ../templates/actual_use.docx, change this to that.
                    $template_path = '../templates/actual_location.docx';
                    if(!file_exists($template_path)) { $errors[] = "Template not found: $template_path"; break; }

                    $template = new TemplateProcessor($template_path);

                    $template->setValue('declared_owner', $data['declared_owner'] ?? '');
                    $template->setValue('arp_no',         $data['ARP_No.'] ?? '');
                    $template->setValue('title',          $data['title'] ?? '');
                    $template->setValue('lot',            $data['lot'] ?? '');
                    $template->setValue('property_location', $data['property_location'] ?? '');
                    $template->setValue('area',           $data['area'] ?? '');

                    $template->setValue('day', date('d'));
                    $template->setValue('month', date('F'));
                    $template->setValue('year', date('Y'));

                    $filename = "Actual_Location_" . (($data['ARP_No.'] ?? 'record')) . ".docx";

                    ob_clean();
                    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                    header("Content-Disposition: attachment; filename=\"$filename\"");
                    $template->saveAs("php://output");
                    exit;
                }

                default:
                    $errors[] = "Certificate template not yet configured.";
                    break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Process Certificate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/process_certificate.css">
</head>
<body>

<div class="page-wrap">
    <div class="app-shell w-100">

        <div class="glass">

            <div class="headbar">
                <div class="title">
                    <div class="title-badge">PC</div>
                    <div>
                        <div>Process Certificate</div>
                        <p class="subtitle">Generate and download certificate</p>
                    </div>
                </div>

                <a href="home.php?tab=requests" class="btn btn-outline-secondary">Back</a>
            </div>

            <div class="content">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row g-3">

                    <div class="col-lg-8">
                        <div class="form-card">
                            <div class="card-body">

                                <form method="POST" class="row g-3">

                                    <div class="col-md-6">
                                        <label class="form-label">Kind of Certification</label>
                                        <select name="cert_type" class="form-select" required>
                                            <option value="">-- Select Certificate --</option>
                                            <?php foreach($certificates as $key => $label): ?>
                                                <option value="<?= htmlspecialchars($key) ?>"
                                                    <?= (($_POST['cert_type'] ?? '') === $key) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Barangay</label>
                                        <select name="barangay" class="form-select">
                                            <option value="">-- Select Barangay --</option>
                                            <?php foreach($allowed_tables as $b): ?>
                                                <option value="<?= htmlspecialchars($b) ?>"
                                                    <?= (($_POST['barangay'] ?? '') === $b) ? 'selected' : '' ?>>
                                                    <?= strtoupper(str_replace('_',' ', $b)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="help mt-1">
                                            Required for Tax Dec / No Improvement / No Declared / Actual Location.
                                            For Total Land Holding, leave blank.
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">ARP No.</label>
                                        <input type="text" name="arp_no" class="form-control"
                                               value="<?= htmlspecialchars($_POST['arp_no'] ?? '') ?>"
                                               placeholder="Enter ARP No.">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Owner Name (Total Land)</label>
                                        <input type="text" name="owner_name" class="form-control"
                                               value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>"
                                               placeholder="e.g. JUAN DELA CRUZ">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Address Filter (optional)</label>
                                        <input type="text" name="owner_address_filter" class="form-control"
                                               value="<?= htmlspecialchars($_POST['owner_address_filter'] ?? '') ?>"
                                               placeholder="optional - helps avoid same names">
                                    </div>

                                    <div class="col-12 d-flex flex-wrap gap-2">
                                        <button type="submit" name="generate" class="btn btn-primary">
                                            Generate &amp; Download
                                        </button>
                                        <a href="home.php?tab=requests" class="btn btn-outline-secondary">
                                            Cancel
                                        </a>
                                    </div>

                                </form>

                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="grid-note">
                            <div class="fw-bold mb-2">Quick Guide</div>
                            <div class="help">
                                <b>Barangay-based certificates</b><br>
                                • Select Barangay<br>
                                • Enter ARP No.<br><br>
                                <b>Total Land Holding</b><br>
                                • Enter Owner Name<br>
                                • Optional Address Filter
                            </div>
                        </div>
                    </div>

                </div><!-- row -->

            </div><!-- content -->
        </div><!-- glass -->

        <div class="page-footer">
            © <?= date('Y') ?> iRPTAS
        </div>

    </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>