<?php
ob_start();
require_once "../includes/auth_check.php";
require_once "../config/database.php";

$_POST['mode'] = $_POST['mode'] ?? '';

// FAAS allowed barangay tables
$allowed_tables = [
    'alicia','cabugao','dagupan','diodol','dumabel','dungo',
    'guinalbin','nagabgaban','palacian','pinaripad_norte',
    'pinaripad_sur','progreso','ramos','rangayan',
    'san_antonio','san_benigno','san_francisco','san_leonardo',
    'san_manuel','san_ramon','victoria',
    'villa_pagaduan','villa_santiago','villa_ventura'
];

$tab = $_GET['tab'] ?? 'dashboard';

/* ===============================
   DASHBOARD COUNTS + CHART DATA (PENDING + PAID only)
================================== */
if($tab == 'dashboard') {

    // Request status counts
    $pending_count  = (int)$conn->query("SELECT COUNT(*) c FROM requests WHERE status='PENDING'")->fetch_assoc()['c'];
    $paid_count     = (int)$conn->query("SELECT COUNT(*) c FROM requests WHERE status='PAID'")->fetch_assoc()['c'];

    // Total FAAS records (sum of barangay tables)
    $total_faas = 0;
    foreach($allowed_tables as $table) {
        $count = (int)$conn->query("SELECT COUNT(*) c FROM `$table`")->fetch_assoc()['c'];
        $total_faas += $count;
    }

    // ✅ Total land_holdings_master
    $master_total = (int)$conn->query("SELECT COUNT(*) c FROM land_holdings_master")->fetch_assoc()['c'];

    // ✅ Pie: Certificates vs Services (count of requested items for PENDING/PAID requests)
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

    // ✅ Weekly Requests (last 8 weeks)
    $weekly_labels = [];
    $weekly_counts = [];

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
        $wk = (string)$r['wk']; // ex: 202609
        $year = substr($wk, 0, 4);
        $week = substr($wk, 4, 2);
        $tmp[] = [
            'label' => "Wk {$week} {$year}",
            'count' => (int)$r['c']
        ];
    }
    $tmp = array_reverse($tmp);
    foreach($tmp as $t){
        $weekly_labels[] = $t['label'];
        $weekly_counts[] = $t['count'];
    }

    // For JS
    $chart_pie_labels = ['Certificates', 'Services'];
    $chart_pie_data   = [$cert_items, $service_items];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - T.R.A.C.S</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/admin.css">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
</head>
<body>
<div class="wrapper">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <div class="sidebar-brand">
            <div class="brand-logos">
              <img src="../assets/img/sample.png" alt="Logo 1">
              <img src="../assets/img/sample.png" alt="Logo 2">
            </div>
            <div class="brand-title">T.R.A.C.S</div>
          </div>
        </div>

        <nav class="nav flex-column">
            <a href="home.php" class="nav-link <?= $tab=='dashboard'?'active':'' ?>"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a>
            <a href="home.php?tab=requests" class="nav-link <?= $tab=='requests'?'active':'' ?>"><i class="fas fa-clipboard-list"></i><span>Requests</span></a>
            <a href="home.php?tab=history" class="nav-link <?= $tab=='history'?'active':'' ?>"><i class="fas fa-history"></i><span>Transaction History</span></a>
            <a href="home.php?tab=import" class="nav-link <?= $tab=='import'?'active':'' ?>"><i class="fas fa-file-import"></i><span>Import Data</span></a>
            <a href="home.php?tab=find" class="nav-link <?= $tab=='find'?'active':'' ?>"><i class="fas fa-search"></i><span>Find Record</span></a>
            <a href="home.php?tab=faas" class="nav-link <?= $tab=='faas'?'active':'' ?>"><i class="fas fa-folder-tree"></i><span>FAAS Management</span></a>
            <a href="home.php?tab=certificates" class="nav-link <?= $tab=='certificates'?'active':'' ?>"><i class="fas fa-certificate"></i><span>Certificates</span></a>
            <a href="home.php?tab=services" class="nav-link <?= $tab=='services'?'active':'' ?>"><i class="fas fa-concierge-bell"></i><span>Services</span></a>
            <a href="home.php?tab=notice_assessment" class="nav-link <?= $tab=='notice_assessment'?'active':'' ?>"><i class="fas fa-bullhorn"></i><span>Notice of Assessment</span></a>
        </nav>

        <div class="mt-auto">
            <hr>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt me-2"></i><span>Logout</span></a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="menu-toggle-btn" id="menuToggle" type="button" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
                <?php
                $pageTitles = [
                    'dashboard' => 'Dashboard',
                    'requests' => 'Requests',
                    'history' => 'Transaction History',
                    'import' => 'Import Data',
                    'find' => 'Find Record',
                    'faas' => ' Manage Field Appraisal and Assessment Sheet',
                    'certificates' => 'Certificates',
                    'services' => 'Services',
                    'notice_assessment' => 'Notice of Assessment',
                ];
                ?>
                <span class="page-title"><?= htmlspecialchars($pageTitles[$tab] ?? ucfirst(str_replace('_',' ',$tab))) ?></span>
            </div>

            <div class="user-profile-pill">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?></span>
            </div>
        </nav>

        <div class="content-area">

            <?php if($tab=='dashboard'): ?>

                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h3><?= (int)$pending_count ?></h3>
                                <p>Pending Requests</p>
                            </div>
                            <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h3><?= (int)$paid_count ?></h3>
                                <p>Paid Requests</p>
                            </div>
                            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h3><?= number_format((int)$total_faas) ?></h3>
                                <p>Total FAAS Records</p>
                            </div>
                            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h3><?= number_format((int)$master_total) ?></h3>
                                <p>Master List Records</p>
                            </div>
                            <div class="stat-icon"><i class="fas fa-database"></i></div>
                        </div>
                    </div>
                </div>

                <div class="modern-card">
                    <div class="card-header"><i class="fas fa-chart-line me-2"></i> Welcome Back!</div>
                    <div class="card-body">
                        <h5 class="mb-1">Hello, <?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?> 👋</h5>
                        <p class="text-muted mb-0">You have <?= (int)$pending_count ?> pending requests.</p>
                    </div>
                </div>

                <!-- ✅ CHARTS -->
                <div class="row g-4 mt-2">
                    <div class="col-lg-5">
                        <div class="modern-card h-100">
                            <div class="card-header">
                                <i class="fas fa-chart-pie me-2"></i> Certificates vs Services (Pending/Paid)
                            </div>
                            <div class="card-body">
                                <div style="height:320px;">
                                    <canvas id="pieCertSvc"></canvas>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    Certificates: <b><?= (int)$cert_items ?></b> • Services: <b><?= (int)$service_items ?></b>
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="modern-card h-100">
                            <div class="card-header">
                                <i class="fas fa-chart-line me-2"></i> Weekly Requests (Last 8 Weeks)
                            </div>
                            <div class="card-body">
                                <div style="height:320px;">
                                    <canvas id="weeklyRequests"></canvas>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    Based on requests with status <b>PENDING</b> and <b>PAID</b>.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif($tab=='requests'): ?>

                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clipboard-list me-2"></i> Requests (Pending & Paid)</span>

                        <a href="process_certificate.php" class="modern-btn modern-btn-warning modern-btn-sm">
                            <i class="fas fa-cogs me-1"></i> Process
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>ID</th><th>Client</th><th>Purpose</th><th>Certificates/Services</th>
                                        <th>Total</th><th>Control No</th><th>Status</th><th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $sql = "
                                    SELECT
                                        r.id,
                                        CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname,
                                        c.purpose,
                                        r.total_amount,
                                        r.control_number,
                                        r.status,
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
                                ";
                                $result = $conn->query($sql);

                                while($row = $result->fetch_assoc()):
                                    $certCount    = (int)$row['cert_count'];
                                    $serviceCount = (int)$row['service_count'];

                                    $items = "-";
                                    if ($certCount > 0 && !empty($row['certificate_list'])) {
                                        $items = $row['certificate_list'];
                                    } elseif ($serviceCount > 0 && !empty($row['service_list'])) {
                                        $items = $row['service_list'];
                                    }
                                ?>
                                <tr>
                                    <td>#<?= (int)$row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['fullname'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['purpose'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($items) ?></td>
                                    <td><strong>₱<?= number_format((float)$row['total_amount'], 2) ?></strong></td>
                                    <td><?= htmlspecialchars($row['control_number'] ?? '-') ?></td>
                                    <td>
                                        <span class="status-badge <?= strtolower((string)$row['status']) ?>">
                                            <?= htmlspecialchars((string)$row['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '-' ?></td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif($tab=='history'): ?>

                <div class="modern-card">
                    <div class="card-header"><i class="fas fa-history me-2"></i> Transaction History</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>ID</th><th>Client</th><th>Purpose</th><th>Certificates</th>
                                        <th>Total</th><th>Control No</th><th>Status</th><th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $sql2 = "
                                    SELECT
                                        r.id,
                                        CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname,
                                        c.purpose,
                                        r.total_amount,
                                        r.control_number,
                                        r.status,
                                        r.created_at
                                    FROM requests r
                                    JOIN clients c ON r.client_id=c.id
                                    WHERE r.status='PAID'
                                    ORDER BY r.created_at DESC
                                ";
                                $res2 = $conn->query($sql2);

                                while($row = $res2->fetch_assoc()):
                                    $cert_sql = "
                                        SELECT c.certificate_name
                                        FROM request_items ri
                                        JOIN certificates c ON ri.certificate_id = c.id
                                        WHERE ri.request_id=".(int)$row['id']."
                                    ";
                                    $cert_res = $conn->query($cert_sql);
                                    $certs = [];
                                    while($c = $cert_res->fetch_assoc()) $certs[] = $c['certificate_name'];
                                ?>
                                <tr>
                                    <td>#<?= (int)$row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['fullname'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['purpose'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(implode(", ", $certs)) ?></td>
                                    <td><strong>₱<?= number_format((float)$row['total_amount'],2) ?></strong></td>
                                    <td><?= htmlspecialchars($row['control_number'] ?? '-') ?></td>
                                    <td>
                                        <?php
                                        $status = (string)($row['status'] ?? '');
                                        $status_class = in_array($status,['PENDING','PAID'], true) ? strtolower($status) : '';
                                        ?>
                                        <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($status) ?></span>
                                    </td>
                                    <td><?= !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '-' ?></td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif($tab=='import'): ?>

                <div class="modern-card">
                    <div class="card-header"><i class="fas fa-file-import me-2"></i> Import Excel Data</div>
                    <div class="card-body">
                        <?php if(isset($_GET['success'])): ?>
                            <div class="modern-alert modern-alert-success"><i class="fas fa-check-circle me-2"></i>Import successful!</div>
                        <?php endif; ?>
                        <?php if(isset($_GET['error'])): ?>
                            <div class="modern-alert modern-alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?></div>
                        <?php endif; ?>

                        <form action="import_logic.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label class="modern-form-label">Import Destination:</label>
                                <select name="destination" class="modern-form-select" required>
                                    <option value="">-- Select Destination --</option>
                                    <option value="barangay">Barangay Table</option>
                                    <option value="master">Land Holdings Master</option>
                                </select>
                                <small class="text-muted d-block mt-1">
                                    Barangay Table = imports into selected barangay. Master = imports into land_holdings_master.
                                </small>
                            </div>

                            <div class="mb-4">
                                <label class="modern-form-label">Choose Barangay (only if Barangay Table):</label>
                                <select name="barangay" class="modern-form-select">
                                    <option value="">-- Select Barangay --</option>
                                    <?php foreach($allowed_tables as $table): ?>
                                        <option value="<?= $table ?>"><?= ucwords(str_replace('_',' ',$table)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="modern-form-label">Select Excel File:</label>
                                <input type="file" name="excel" class="modern-form-control" accept=".xlsx,.xls" required>
                            </div>

                            <button type="submit" name="import" class="modern-btn modern-btn-success">
                                <i class="fas fa-upload me-2"></i> Import Data
                            </button>
                        </form>
                    </div>
                </div>

            <?php elseif($tab=='find'): ?>

<?php
$results_barangay = [];
$results_master   = [];

$selected_barangay = $_GET['barangay'] ?? '';
$search_name       = trim($_GET['search'] ?? '');
$search_global     = trim($_GET['search_global'] ?? '');

// PER-BARANGAY SEARCH
if ($selected_barangay !== '' && $search_name !== '' && in_array($selected_barangay, $allowed_tables, true)) {
    $stmt = $conn->prepare("SELECT * FROM `$selected_barangay` WHERE declared_owner LIKE CONCAT('%', ?, '%')");
    $stmt->bind_param("s", $search_name);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $results_barangay[] = $row;
    $stmt->close();
}

// GLOBAL SEARCH (land_holdings_master)
if ($search_global !== '') {
    $sql = "SELECT
                declared_owner, owner_address, property_location, title, lot,
                `ARP_No.` AS `ARP_No.`, `PIN_No.` AS `PIN_No.`,
                classification, actual_use, area, mv, av, taxability, effectivity, cancellation
            FROM `land_holdings_master`
            WHERE declared_owner LIKE CONCAT('%', ?, '%')
            ORDER BY declared_owner ASC, property_location ASC";

    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param("s", $search_global);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while($row = $res2->fetch_assoc()) $results_master[] = $row;
    $stmt2->close();
}

$display_fields_find = [
    'declared_owner','owner_address','property_location','title','lot',
    'ARP_No.','PIN_No.','classification','actual_use','area',
    'mv','av','taxability','effectivity','cancellation'
];
?>

<div class="scroll-indicator"><i class="fas fa-arrow-left me-2"></i> Swipe to scroll table <i class="fas fa-arrow-right ms-2"></i></div>

<div class="modern-card mb-4">
    <div class="card-header"><i class="fas fa-search me-2"></i> Find Record</div>
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="tab" value="find">

            <div class="row g-3">
                <div class="col-md-4 col-sm-12">
                    <label class="modern-form-label">Barangay (Optional for Barangay Search)</label>
                    <select name="barangay" class="modern-form-select">
                        <option value="">-- Select Barangay --</option>
                        <?php foreach($allowed_tables as $table): ?>
                            <option value="<?= $table ?>" <?= $selected_barangay==$table ? "selected" : "" ?>>
                                <?= ucwords(str_replace('_',' ',$table)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-sm-12">
                    <label class="modern-form-label">Search Declared Owner (Barangay)</label>
                    <input type="text" name="search" class="modern-form-control"
                           value="<?= htmlspecialchars($search_name) ?>"
                           placeholder="Search owner inside selected barangay">
                </div>

                <div class="col-md-4 col-sm-12">
                    <label class="modern-form-label">Global Search Declared Owner (Master List)</label>
                    <input type="text" name="search_global" class="modern-form-control"
                           value="<?= htmlspecialchars($search_global) ?>"
                           placeholder="Search owner in land_holdings_master (all locations)">
                </div>

                <div class="col-md-2 col-sm-12 d-flex align-items-end">
                    <button type="submit" class="modern-btn modern-btn-primary w-100">
                        <i class="fas fa-search me-2"></i> Search
                    </button>
                </div>
            </div>

            <small class="text-muted d-block mt-2">
                Tip: You can use <b>Barangay search</b>, <b>Global search</b>, or <b>both</b> at the same time.
            </small>
        </form>
    </div>
</div>

<?php if($search_global !== ''): ?>
    <?php if(!empty($results_master)): ?>
        <div class="modern-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-globe me-2"></i> Global Results (land_holdings_master)</span>
                <span class="badge bg-primary badge-pill">
                    <i class="fas fa-database me-1"></i> Found: <?= count($results_master) ?> record(s)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="scrollable-container">
                    <div class="table-responsive">
                        <table class="modern-table find-record-table">
                            <thead>
                                <tr>
                                    <th>Declared Owner</th><th>Owner Address</th><th>Property Location</th><th>Title</th><th>Lot</th>
                                    <th>ARP No.</th><th>PIN No.</th><th>Classification</th><th>Actual Use</th><th>Area</th>
                                    <th>Market Value</th><th>Assessed Value</th><th>Taxability</th><th>Effectivity</th><th>Cancellation</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($results_master as $row): ?>
                                <tr>
                                    <?php foreach($display_fields_find as $field): ?>
                                        <td title="<?= htmlspecialchars($row[$field] ?? '') ?>">
                                            <?= htmlspecialchars($row[$field] ?? '') ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="scroll-hint"><i class="fas fa-arrow-left"></i><span class="mx-2">Scroll horizontally to see more columns</span><i class="fas fa-arrow-right"></i></div>
            </div>
        </div>
    <?php else: ?>
        <div class="modern-alert modern-alert-warning mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No global records found for "<?= htmlspecialchars($search_global) ?>" in land_holdings_master.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if($selected_barangay !== '' && $search_name !== ''): ?>
    <?php if(!empty($results_barangay)): ?>
        <div class="modern-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i> Barangay Results (<?= ucwords(str_replace('_',' ',$selected_barangay)) ?>)</span>
                <span class="badge bg-primary badge-pill">
                    <i class="fas fa-database me-1"></i> Found: <?= count($results_barangay) ?> record(s)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="scrollable-container">
                    <div class="table-responsive">
                        <table class="modern-table find-record-table">
                            <thead>
                                <tr>
                                    <th>Declared Owner</th><th>Owner Address</th><th>Property Location</th><th>Title</th><th>Lot</th>
                                    <th>ARP No.</th><th>PIN No.</th><th>Classification</th><th>Actual Use</th><th>Area</th>
                                    <th>Market Value</th><th>Assessed Value</th><th>Taxability</th><th>Effectivity</th><th>Cancellation</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($results_barangay as $row): ?>
                                <tr>
                                    <?php foreach($display_fields_find as $field): ?>
                                        <td title="<?= htmlspecialchars($row[$field] ?? '') ?>">
                                            <?= htmlspecialchars($row[$field] ?? '') ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="scroll-hint"><i class="fas fa-arrow-left"></i><span class="mx-2">Scroll horizontally to see more columns</span><i class="fas fa-arrow-right"></i></div>
            </div>
        </div>
    <?php else: ?>
        <div class="modern-alert modern-alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No records found for "<?= htmlspecialchars($search_name) ?>" in <?= ucwords(str_replace('_',' ',$selected_barangay)) ?>.
        </div>
    <?php endif; ?>
<?php endif; ?>

            <?php elseif($tab=='faas'): ?>

<?php
$selected_barangay = $_GET['barangay'] ?? '';
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;

$search_owner = trim($_GET['search_owner'] ?? '');

if($selected_barangay && !in_array($selected_barangay, $allowed_tables, true)) die("Invalid barangay.");

// input names (safe)
$input_fields = [
    'arp_no','declared_owner',
    'owner_address','property_location','title','lot',
    'pin_no','classification','actual_use','area',
    'mv','av','taxability','effectivity','cancellation'
];

$labels = [
    'ARP No.','Declared Owner',
    'Owner Address','Property Location','Title','Lot',
    'PIN No.','Classification','Actual Use','Area',
    'Market Value','Assessed Value','Taxability','Effectivity','Cancellation'
];

// display fields (DB)
$display_fields = [
    'ARP_No.','declared_owner',
    'owner_address','property_location','title','lot',
    'PIN_No.','classification','actual_use','area',
    'mv','av','taxability','effectivity','cancellation'
];

/* DELETE */
if($action=='delete' && $selected_barangay && $id > 0){
    $stmt = $conn->prepare("DELETE FROM `$selected_barangay` WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $stmt->close();

    $qs = "tab=faas&barangay=".urlencode($selected_barangay)."&page=".$page;
    if($search_owner !== '') $qs .= "&search_owner=".urlencode($search_owner);
    header("Location: home.php?$qs");
    exit();
}

/* SAVE ADD/EDIT */
if(isset($_POST['save_faas'])){
    $mode = $_POST['mode'] ?? '';

    // ADD: table destination comes from barangay select (required)
    // EDIT: table destination is the loaded barangay
    $brgy = $_POST['barangay'] ?? '';
    if($mode === 'edit') $brgy = $selected_barangay;

    if(!in_array($brgy, $allowed_tables, true)) die("Invalid barangay.");

    $data = [];
    foreach($input_fields as $field){
        $data[$field] = trim($_POST[$field] ?? '');
    }

    if($mode=='add'){
        $stmt = $conn->prepare("
            INSERT INTO `$brgy`
            (declared_owner,owner_address,property_location,title,lot,`ARP_No.`,`PIN_No.`,classification,actual_use,area,mv,av,taxability,effectivity,cancellation)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "sssssssssssssss",
            $data['declared_owner'],
            $data['owner_address'],
            $data['property_location'],
            $data['title'],
            $data['lot'],
            $data['arp_no'],
            $data['pin_no'],
            $data['classification'],
            $data['actual_use'],
            $data['area'],
            $data['mv'],
            $data['av'],
            $data['taxability'],
            $data['effectivity'],
            $data['cancellation']
        );
        $stmt->execute();
        $faas_id = (int)$conn->insert_id;
        $stmt->close();

        // LOG (only for ADD)
        $week_key = (int)$conn->query("SELECT YEARWEEK(CURDATE(), 1) AS wk")->fetch_assoc()['wk'];
        $owner = $data['declared_owner'];
        $created_by = $_SESSION['fullname'] ?? 'Admin';
        $owner_key = strtolower(preg_replace('/\s+/', ' ', $owner));

        $log = $conn->prepare("
            INSERT INTO notice_of_assessment_logs
            (barangay, declared_owner, owner_address, owner_key, arp_no, pin_no, property_location, classification, mv, av, created_by, week_key, faas_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, ?, ?)
        ");
        $mv = (float)($data['mv'] !== '' ? $data['mv'] : 0);
        $av = (float)($data['av'] !== '' ? $data['av'] : 0);

        $log->bind_param(
            "ssssssssddsii",
            $brgy,
            $owner,
            $data['owner_address'],
            $owner_key,
            $data['arp_no'],
            $data['pin_no'],
            $data['property_location'],
            $data['classification'],
            $mv,
            $av,
            $created_by,
            $week_key,
            $faas_id
        );
        $log->execute();
        $log->close();

    } elseif($mode=='edit'){
        $original_id = (int)($_POST['original_id'] ?? 0);
        if($original_id <= 0) die("Missing original id.");

        $stmt = $conn->prepare("
            UPDATE `$brgy`
            SET declared_owner=?,owner_address=?,property_location=?,title=?,lot=?,
                `ARP_No.`=?,`PIN_No.`=?,classification=?,actual_use=?,area=?,
                mv=?,av=?,taxability=?,effectivity=?,cancellation=?
            WHERE id=?
        ");
        $stmt->bind_param(
            "sssssssssssssssi",
            $data['declared_owner'],
            $data['owner_address'],
            $data['property_location'],
            $data['title'],
            $data['lot'],
            $data['arp_no'],
            $data['pin_no'],
            $data['classification'],
            $data['actual_use'],
            $data['area'],
            $data['mv'],
            $data['av'],
            $data['taxability'],
            $data['effectivity'],
            $data['cancellation'],
            $original_id
        );
        $stmt->execute();
        $stmt->close();
    }

    $qs = "tab=faas";
    if($selected_barangay) $qs .= "&barangay=".urlencode($selected_barangay);
    $qs .= "&page=".$page;
    if($search_owner !== '') $qs .= "&search_owner=".urlencode($search_owner);
    header("Location: home.php?$qs");
    exit();
}

/* BULK ADD (PER-ROW BARANGAY DESTINATION via row_barangay[]) */
if(isset($_POST['bulk_add_faas'])){

    $arp_no_arr          = $_POST['arp_no'] ?? [];
    $declared_owner_arr  = $_POST['declared_owner'] ?? [];
    $owner_address_arr   = $_POST['owner_address'] ?? [];
    $row_barangay_arr    = $_POST['row_barangay'] ?? []; // per-row table destination
    $title_arr           = $_POST['title'] ?? [];
    $lot_arr             = $_POST['lot'] ?? [];
    $pin_no_arr          = $_POST['pin_no'] ?? [];
    $classification_arr  = $_POST['classification'] ?? [];
    $actual_use_arr      = $_POST['actual_use'] ?? [];
    $area_arr            = $_POST['area'] ?? [];
    $mv_arr              = $_POST['mv'] ?? [];
    $av_arr              = $_POST['av'] ?? [];
    $taxability_arr      = $_POST['taxability'] ?? [];
    $effectivity_arr     = $_POST['effectivity'] ?? [];
    $cancellation_arr    = $_POST['cancellation'] ?? [];

    $week_key   = (int)$conn->query("SELECT YEARWEEK(CURDATE(), 1) AS wk")->fetch_assoc()['wk'];
    $created_by = $_SESSION['fullname'] ?? 'Admin';

    $ok = 0;
    $conn->begin_transaction();

    try {
        $n = max(count($arp_no_arr), count($declared_owner_arr), count($row_barangay_arr));

        for($i=0; $i<$n; $i++){
            $arp   = trim($arp_no_arr[$i] ?? '');
            $owner = trim($declared_owner_arr[$i] ?? '');
            $brgy  = trim($row_barangay_arr[$i] ?? '');

            if($arp === '' || $owner === '' || $brgy === '') continue;
            if(!in_array($brgy, $allowed_tables, true)) continue;

            $owner_address = trim($owner_address_arr[$i] ?? '');
            $title         = trim($title_arr[$i] ?? '');
            $lot           = trim($lot_arr[$i] ?? '');
            $pin           = trim($pin_no_arr[$i] ?? '');
            $classification= trim($classification_arr[$i] ?? '');
            $actual_use    = trim($actual_use_arr[$i] ?? '');
            $area          = trim($area_arr[$i] ?? '');
            $mv            = trim($mv_arr[$i] ?? '');
            $av            = trim($av_arr[$i] ?? '');
            $taxability    = trim($taxability_arr[$i] ?? '');
            $effectivity   = trim($effectivity_arr[$i] ?? '');
            $cancellation  = trim($cancellation_arr[$i] ?? '');

            $property_location = ucwords(str_replace('_',' ', $brgy));

            $sqlIns = "
                INSERT INTO `$brgy`
                (declared_owner,owner_address,property_location,title,lot,`ARP_No.`,`PIN_No.`,classification,actual_use,area,mv,av,taxability,effectivity,cancellation)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ";
            $ins = $conn->prepare($sqlIns);
            $ins->bind_param(
                "sssssssssssssss",
                $owner,
                $owner_address,
                $property_location,
                $title,
                $lot,
                $arp,
                $pin,
                $classification,
                $actual_use,
                $area,
                $mv,
                $av,
                $taxability,
                $effectivity,
                $cancellation
            );
            $ins->execute();
            $faas_id = (int)$conn->insert_id;
            $ins->close();

            $owner_key = strtolower(preg_replace('/\s+/', ' ', $owner));
            $mv_f = (float)($mv !== '' ? $mv : 0);
            $av_f = (float)($av !== '' ? $av : 0);

            $log = $conn->prepare("
                INSERT INTO notice_of_assessment_logs
                (barangay, declared_owner, owner_address, owner_key, arp_no, pin_no, property_location, classification, mv, av, created_by, week_key, faas_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?, ?, ?)
            ");
            $log->bind_param(
                "ssssssssddsii",
                $brgy,
                $owner,
                $owner_address,
                $owner_key,
                $arp,
                $pin,
                $property_location,
                $classification,
                $mv_f,
                $av_f,
                $created_by,
                $week_key,
                $faas_id
            );
            $log->execute();
            $log->close();

            $ok++;
        }

        $conn->commit();
    } catch(Exception $e){
        $conn->rollback();
        die("Bulk add failed: ".$e->getMessage());
    }

    header("Location: home.php?tab=faas&success=".urlencode("Bulk added: $ok record(s)"));
    exit();
}
?>

<!-- ======= FAAS UI (same as your existing) ======= -->
<div class="modern-card mb-4">
    <div class="card-header"><i class="fas fa-folder-tree me-2"></i> FAAS Management</div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="tab" value="faas">

            <div class="col-md-6">
                <select name="barangay" class="modern-form-select">
                    <option value="">-- Select Barangay (Optional) --</option>
                    <?php foreach($allowed_tables as $table): ?>
                        <option value="<?= $table ?>" <?= $selected_barangay==$table ? "selected" : "" ?>>
                            <?= ucwords(str_replace('_',' ',$table)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <button class="modern-btn modern-btn-primary w-100" type="submit">
                    <i class="fas fa-sync-alt me-2"></i> Load
                </button>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <a href="home.php?tab=faas&action=add<?= $selected_barangay ? '&barangay='.urlencode($selected_barangay) : '' ?>"
                   class="modern-btn modern-btn-success w-100">
                    <i class="fas fa-plus-circle me-2"></i> Add FAAS
                </a>

                <a href="home.php?tab=faas&action=bulk<?= $selected_barangay ? '&barangay='.urlencode($selected_barangay) : '' ?>"
                   class="modern-btn modern-btn-secondary w-100">
                    <i class="fas fa-layer-group me-2"></i> Bulk
                </a>
            </div>
        </form>
    </div>
</div>

<?php if(isset($_GET['success'])): ?>
    <div class="modern-alert modern-alert-success mb-3">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_GET['success']) ?>
    </div>
<?php endif; ?>

<?php if($action==='bulk'): ?>
<div class="modern-card mb-4">
    <div class="card-header is-secondary">
        <i class="fas fa-layer-group me-2"></i> Bulk Add FAAS (Per Row Barangay Table)
    </div>

    

        <form method="POST" id="bulkFaasForm">
            <div id="bulkRows">
                <div class="border rounded p-3 mb-3 bulk-row">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong><i class="fas fa-list me-2"></i>Row <span class="row-number">1</span></strong>
                        <button type="button" class="modern-btn modern-btn-danger modern-btn-sm remove-row" data-role="remove-row">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <input name="arp_no[]" class="modern-form-control" placeholder="ARP No." required>
                        </div>
                        <div class="col-md-6">
                            <input name="declared_owner[]" class="modern-form-control" placeholder="Declared Owner" required>
                        </div>

                        <div class="col-md-6">
                            <input name="owner_address[]" class="modern-form-control" placeholder="Owner Address">
                        </div>

                        <div class="col-md-6">
                            <select name="row_barangay[]" class="modern-form-select" required>
                                <option value="">-- Property Location (Barangay) --</option>
                                <?php foreach($allowed_tables as $t): ?>
                                    <option value="<?= htmlspecialchars($t) ?>">
                                        <?= ucwords(str_replace('_',' ', $t)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <input name="title[]" class="modern-form-control" placeholder="Title">
                        </div>
                        <div class="col-md-6">
                            <input name="lot[]" class="modern-form-control" placeholder="Lot">
                        </div>

                        <div class="col-md-6">
                            <input name="pin_no[]" class="modern-form-control" placeholder="PIN No.">
                        </div>
                        <div class="col-md-6">
                            <input name="classification[]" class="modern-form-control" placeholder="Classification">
                        </div>

                        <div class="col-md-6">
                            <input name="actual_use[]" class="modern-form-control" placeholder="Actual Use">
                        </div>
                        <div class="col-md-6">
                            <input name="area[]" class="modern-form-control" placeholder="Area">
                        </div>

                        <div class="col-md-6">
                            <input name="mv[]" class="modern-form-control" placeholder="Market Value">
                        </div>
                        <div class="col-md-6">
                            <input name="av[]" class="modern-form-control" placeholder="Assessed Value">
                        </div>

                        <div class="col-md-6">
                            <input name="taxability[]" class="modern-form-control" placeholder="Taxability">
                        </div>
                        <div class="col-md-6">
                            <input name="effectivity[]" class="modern-form-control" placeholder="Effectivity">
                        </div>

                        <div class="col-md-12">
                            <input name="cancellation[]" class="modern-form-control" placeholder="Cancellation">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="modern-btn modern-btn-secondary" id="addRowBtn">
                    <i class="fas fa-plus me-2"></i> Add Row
                </button>

                <button type="submit" name="bulk_add_faas" class="modern-btn modern-btn-primary">
                    <i class="fas fa-save me-2"></i> Save All
                </button>

                <a href="home.php?tab=faas<?= $selected_barangay ? '&barangay='.urlencode($selected_barangay) : '' ?>"
                   class="modern-btn modern-btn-secondary">
                   <i class="fas fa-times me-2"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if($selected_barangay && $action!='add' && $action!='edit' && $action!='bulk'): ?>
<div class="modern-card mb-4">
    <div class="card-header"><i class="fas fa-search me-2"></i> Search Person</div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="tab" value="faas">
            <input type="hidden" name="barangay" value="<?= htmlspecialchars($selected_barangay) ?>">
            <input type="hidden" name="page" value="1">

            <div class="col-md-9">
                <input type="text" name="search_owner" class="modern-form-control"
                       placeholder="Search Declared Owner (ex: Juan Dela Cruz)"
                       value="<?= htmlspecialchars($search_owner) ?>">
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button class="modern-btn modern-btn-primary w-100" type="submit">
                    <i class="fas fa-search me-2"></i> Search
                </button>

                <a href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>" class="modern-btn modern-btn-secondary w-100">
                    <i class="fas fa-times me-2"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if(($action=='add' || $action=='edit')):
    $edit_row = [];
    if($action=='edit'){
        if(!$selected_barangay || !in_array($selected_barangay, $allowed_tables, true)) die("Missing/Invalid barangay for edit.");
        if($id>0){
            $stmt = $conn->prepare("SELECT * FROM `$selected_barangay` WHERE id=?");
            $stmt->bind_param("i",$id);
            $stmt->execute();
            $edit_row = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        }
    }
?>
<div class="modern-card mb-4">
    <div class="card-header <?= $action=='add' ? 'is-success' : 'is-warning' ?>">
        <i class="fas fa-<?= $action=='add' ? 'plus' : 'edit' ?>-circle me-2"></i>
        <?= $action=='add' ? 'Add New FAAS' : 'Edit FAAS Record' ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="mode" value="<?= htmlspecialchars($action) ?>">

            <?php if($action==='edit'): ?>
                <input type="hidden" name="barangay" value="<?= htmlspecialchars($selected_barangay) ?>">
                <input type="hidden" name="original_id" value="<?= (int)($edit_row['id'] ?? 0) ?>">
            <?php else: ?>
                <div class="mb-3">
                    <label class="modern-form-label">Save To Barangay (Table)</label>
                    <select name="barangay" class="modern-form-select" required>
                        <option value="">-- Select Barangay --</option>
                        <?php foreach($allowed_tables as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= ($selected_barangay===$t?'selected':'') ?>>
                                <?= ucwords(str_replace('_',' ', $t)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block mt-1">Required. Ito ang table kung saan isi-save ang record.</small>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <?php foreach($input_fields as $i=>$field): ?>
                    <?php
                        $val = '';
                        if($field==='arp_no') $val = $edit_row['ARP_No.'] ?? '';
                        elseif($field==='pin_no') $val = $edit_row['PIN_No.'] ?? '';
                        else $val = $edit_row[$field] ?? '';
                    ?>
                    <div class="col-md-6">
                        <input name="<?= htmlspecialchars($field) ?>" class="modern-form-control"
                               placeholder="<?= htmlspecialchars($labels[$i]) ?>"
                               value="<?= htmlspecialchars($val) ?>">
                    </div>
                <?php endforeach; ?>

                <div class="col-md-12 mt-4">
                    <button type="submit" name="save_faas" class="modern-btn modern-btn-primary">
                        <i class="fas fa-save me-2"></i> <?= $action=='add' ? 'Save' : 'Update' ?>
                    </button>
                    <a href="home.php?tab=faas<?= $selected_barangay ? '&barangay='.urlencode($selected_barangay) : '' ?>"
                       class="modern-btn modern-btn-secondary ms-2">
                       <i class="fas fa-times me-2"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if($selected_barangay && $action!='add' && $action!='edit' && $action!='bulk'):

    $limit = 15;
    $offset = ($page - 1) * $limit;

    if($search_owner !== ''){
        $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM `$selected_barangay` WHERE declared_owner LIKE CONCAT('%', ?, '%')");
        $stmtCount->bind_param("s", $search_owner);
        $stmtCount->execute();
        $total_records = (int)$stmtCount->get_result()->fetch_assoc()['total'];
        $stmtCount->close();

        $total_pages = (int)ceil($total_records / $limit);

        $stmt = $conn->prepare("SELECT * FROM `$selected_barangay`
                                WHERE declared_owner LIKE CONCAT('%', ?, '%')
                                ORDER BY declared_owner ASC
                                LIMIT ? OFFSET ?");
        $stmt->bind_param("sii", $search_owner, $limit, $offset);
    } else {
        $count = $conn->query("SELECT COUNT(*) as total FROM `$selected_barangay`");
        $total_records = (int)$count->fetch_assoc()['total'];
        $total_pages = (int)ceil($total_records / $limit);

        $stmt = $conn->prepare("SELECT * FROM `$selected_barangay` ORDER BY declared_owner ASC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii",$limit,$offset);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $search_qs = ($search_owner !== '') ? '&search_owner='.urlencode($search_owner) : '';
?>

<div class="scroll-indicator"><i class="fas fa-arrow-left me-2"></i> Swipe to scroll table <i class="fas fa-arrow-right ms-2"></i></div>

<div class="modern-card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
        <span class="mb-2 mb-md-0">
            <i class="fas fa-table me-2"></i>
            FAAS Records - <?= ucwords(str_replace('_',' ',$selected_barangay)) ?>
            <?php if($search_owner !== ''): ?>
                <span class="badge bg-warning text-dark ms-2"><?= htmlspecialchars($search_owner) ?></span>
            <?php endif; ?>
        </span>

        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-primary badge-pill">
                <i class="fas fa-database me-1"></i> Total: <?= number_format($total_records) ?> records
            </span>

            <a href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&action=add"
               class="modern-btn modern-btn-success modern-btn-sm">
                <i class="fas fa-plus-circle me-1"></i> Add New
            </a>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="scrollable-container">
            <div class="table-responsive">
                <table class="modern-table faas-table">
                    <thead>
                        <tr>
                            <?php foreach($labels as $label): ?>
                                <th><?= htmlspecialchars($label) ?></th>
                            <?php endforeach; ?>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($row=$res->fetch_assoc()): ?>
                        <?php $is_cancelled = !empty(trim($row['cancellation'] ?? '')); ?>
                        <tr class="<?= $is_cancelled ? 'faas-cancelled-row' : '' ?>">
                            <?php foreach($display_fields as $f): ?>
                                <td title="<?= htmlspecialchars($row[$f] ?? '') ?>"><?= htmlspecialchars($row[$f] ?? '') ?></td>
                            <?php endforeach; ?>
                            <td>
                                <div class="action-buttons">
                                    <a href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&action=edit&id=<?= (int)$row['id'] ?>&page=<?= $page ?><?= $search_qs ?>"
                                       class="modern-btn modern-btn-warning modern-btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i> <span class="d-none d-md-inline ms-1">Edit</span>
                                    </a>

                                    <a href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&action=delete&id=<?= (int)$row['id'] ?>&page=<?= $page ?><?= $search_qs ?>"
                                       class="modern-btn modern-btn-danger modern-btn-sm js-confirm"
                                       data-confirm="Delete this record?"
                                       title="Delete">
                                        <i class="fas fa-trash"></i> <span class="d-none d-md-inline ms-1">Delete</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="scroll-hint"><i class="fas fa-arrow-left"></i><span class="mx-2">Scroll horizontally to see all columns</span><i class="fas fa-arrow-right"></i></div>
    </div>

    <?php if($total_pages > 1): ?>
    <div class="card-footer">
        <nav aria-label="Page navigation">
            <ul class="pagination mb-0 justify-content-center flex-wrap">
                <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&page=1<?= $search_qs ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&page=<?= $page-1 ?><?= $search_qs ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <li class="page-item <?= $i==$page ? 'active' : '' ?>">
                        <a class="page-link" href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&page=<?= $i ?><?= $search_qs ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&page=<?= $page+1 ?><?= $search_qs ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&page=<?= $total_pages ?><?= $search_qs ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
<?php $stmt->close(); endif; ?>

            <?php elseif($tab=='certificates'):
                $action = $_GET['action'] ?? '';
                $id = $_GET['id'] ?? '';
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                if($page < 1) $page = 1;

                if($action=='delete' && $id){
                    $stmt = $conn->prepare("DELETE FROM certificates WHERE id=?");
                    $stmt->bind_param("i",$id);
                    $stmt->execute();
                    $stmt->close();
                    header("Location: home.php?tab=certificates&page=$page");
                    exit();
                }

                if(isset($_POST['save_certificate'])){
                    $name = $_POST['certificate_name'] ?? '';
                    $description = $_POST['description'] ?? '';
                    $price = (float)($_POST['price'] ?? 0);
                    $status = $_POST['status'] ?? 'active';
                    $mode = $_POST['mode'] ?? 'add';
                    $original_id = (int)($_POST['original_id'] ?? 0);

                    if($mode=='add'){
                        $stmt = $conn->prepare("INSERT INTO certificates (certificate_name, description, price, status) VALUES (?,?,?,?)");
                        $stmt->bind_param("ssds",$name,$description,$price,$status);
                    } elseif($mode=='edit'){
                        $stmt = $conn->prepare("UPDATE certificates SET certificate_name=?, description=?, price=?, status=? WHERE id=?");
                        $stmt->bind_param("ssdsi",$name,$description,$price,$status,$original_id);
                    } else {
                        die("Invalid mode.");
                    }

                    $stmt->execute();
                    $stmt->close();
                    header("Location: home.php?tab=certificates&page=$page");
                    exit();
                }

                $edit_row = [];
                if($action=='edit' && $id){
                    $stmt = $conn->prepare("SELECT * FROM certificates WHERE id=?");
                    $stmt->bind_param("i",$id);
                    $stmt->execute();
                    $edit_row = $stmt->get_result()->fetch_assoc() ?: [];
                    $stmt->close();
                }

                $limit = 15;
                $offset = ($page - 1) * $limit;
                $count = $conn->query("SELECT COUNT(*) as total FROM certificates");
                $total_records = (int)$count->fetch_assoc()['total'];
                $total_pages = (int)ceil($total_records / $limit);

                $stmt = $conn->prepare("SELECT * FROM certificates ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $stmt->bind_param("ii",$limit, $offset);
                $stmt->execute();
                $certificates = $stmt->get_result();
            ?>

            <?php if($action=='add' || ($action=='edit' && $id)): ?>
            <div class="modern-card mb-4">
                <div class="card-header <?= $action=='add' ? 'is-success' : 'is-warning' ?>">
                    <i class="fas fa-<?= $action=='add' ? 'plus' : 'edit' ?>-circle me-2"></i>
                    <?= $action=='add' ? 'Add New Certificate' : 'Edit Certificate' ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="mode" value="<?= $action ?>">
                        <input type="hidden" name="original_id" value="<?= (int)($edit_row['id'] ?? 0) ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="modern-form-label">Certificate Name</label>
                                <input type="text" name="certificate_name" class="modern-form-control" value="<?= htmlspecialchars($edit_row['certificate_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="modern-form-label">Price (₱)</label>
                                <input type="number" step="0.01" name="price" class="modern-form-control" value="<?= htmlspecialchars($edit_row['price'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="modern-form-label">Status</label>
                                <select name="status" class="modern-form-select" required>
                                    <option value="active" <?= (isset($edit_row['status']) && $edit_row['status']=='active') ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= (isset($edit_row['status']) && $edit_row['status']=='inactive') ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="modern-form-label">Description</label>
                                <textarea name="description" class="modern-form-control" rows="3"><?= htmlspecialchars($edit_row['description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-12 mt-4">
                                <button type="submit" name="save_certificate" class="modern-btn modern-btn-primary">
                                    <i class="fas fa-save me-2"></i> <?= $action=='add' ? 'Save' : 'Update' ?>
                                </button>
                                <a href="home.php?tab=certificates" class="modern-btn modern-btn-secondary ms-2">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="modern-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-certificate me-2"></i> Certificates Management</span>
                    <a href="home.php?tab=certificates&action=add" class="modern-btn modern-btn-success modern-btn-sm">
                        <i class="fas fa-plus-circle me-1"></i> Add New Certificate
                    </a>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Certificate Name</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if($certificates->num_rows > 0): ?>
                                <?php while($row = $certificates->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['certificate_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(substr($row['description'] ?? '', 0, 50)) . (strlen($row['description'] ?? '') > 50 ? '...' : '') ?></td>
                                    <td><strong>₱<?= number_format((float)($row['price'] ?? 0),2) ?></strong></td>
                                    <td>
                                        <span class="status-badge <?= strtolower((string)($row['status'] ?? 'inactive')) ?>">
                                            <?= htmlspecialchars(ucfirst((string)($row['status'] ?? 'inactive'))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="home.php?tab=certificates&action=edit&id=<?= (int)$row['id'] ?>&page=<?= $page ?>" class="modern-btn modern-btn-warning modern-btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <a href="home.php?tab=certificates&action=delete&id=<?= (int)$row['id'] ?>&page=<?= $page ?>"
                                           class="modern-btn modern-btn-danger modern-btn-sm js-confirm"
                                           data-confirm="Delete this certificate?"
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-certificate"></i>
                                            <p>No certificates found. Click "Add New Certificate" to create one.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if($total_pages > 1): ?>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4">
                        <div class="mb-3 mb-md-0">
                            <span class="text-muted">Showing page <?= $page ?> of <?= $total_pages ?> (<?= number_format($total_records) ?> total certificates)</span>
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0 flex-wrap">
                                <?php if($page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="home.php?tab=certificates&page=1"><i class="fas fa-angle-double-left"></i></a></li>
                                    <li class="page-item"><a class="page-link" href="home.php?tab=certificates&page=<?= $page-1 ?>"><i class="fas fa-chevron-left"></i></a></li>
                                <?php endif; ?>
                                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                    <li class="page-item <?= $i==$page ? 'active' : '' ?>"><a class="page-link" href="home.php?tab=certificates&page=<?= $i ?>"><?= $i ?></a></li>
                                <?php endfor; ?>
                                <?php if($page < $total_pages): ?>
                                    <li class="page-item"><a class="page-link" href="home.php?tab=certificates&page=<?= $page+1 ?>"><i class="fas fa-chevron-right"></i></a></li>
                                    <li class="page-item"><a class="page-link" href="home.php?tab=certificates&page=<?= $total_pages ?>"><i class="fas fa-angle-double-right"></i></a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php $stmt->close(); ?>

            <?php elseif($tab=='services'):

                $action = $_GET['action'] ?? '';
                $id = $_GET['id'] ?? '';
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                if($page < 1) $page = 1;

                if($action=='delete' && $id){
                    $stmt = $conn->prepare("DELETE FROM services WHERE id=?");
                    $stmt->bind_param("i",$id);
                    $stmt->execute();
                    $stmt->close();
                    header("Location: home.php?tab=services&page=$page");
                    exit();
                }

                if(isset($_POST['save_service'])){
                    $name = trim($_POST['service_name'] ?? '');
                    $description = $_POST['description'] ?? '';
                    $price = (float)($_POST['price'] ?? 0);
                    $status = $_POST['status'] ?? 'active';
                    $mode = $_POST['mode'] ?? 'add';
                    $original_id = (int)($_POST['original_id'] ?? 0);

                    if($mode=='add'){
                        $stmt = $conn->prepare("INSERT INTO services (service_name, description, price, status) VALUES (?,?,?,?)");
                        $stmt->bind_param("ssds",$name,$description,$price,$status);
                    } elseif($mode=='edit'){
                        $stmt = $conn->prepare("UPDATE services SET service_name=?, description=?, price=?, status=? WHERE id=?");
                        $stmt->bind_param("ssdsi",$name,$description,$price,$status,$original_id);
                    } else {
                        die("Invalid mode.");
                    }
                    $stmt->execute();
                    $stmt->close();
                    header("Location: home.php?tab=services&page=$page");
                    exit();
                }

                $edit_row = [];
                if($action=='edit' && $id){
                    $stmt = $conn->prepare("SELECT * FROM services WHERE id=?");
                    $stmt->bind_param("i",$id);
                    $stmt->execute();
                    $edit_row = $stmt->get_result()->fetch_assoc() ?: [];
                    $stmt->close();
                }

                $limit = 15;
                $offset = ($page - 1) * $limit;

                $count = $conn->query("SELECT COUNT(*) as total FROM services");
                $total_records = (int)$count->fetch_assoc()['total'];
                $total_pages = (int)ceil($total_records / $limit);

                $stmt = $conn->prepare("SELECT * FROM services ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $stmt->bind_param("ii",$limit, $offset);
                $stmt->execute();
                $services = $stmt->get_result();
            ?>

            <?php if($action=='add' || ($action=='edit' && $id)): ?>
            <div class="modern-card mb-4">
                <div class="card-header <?= $action=='add' ? 'is-success' : 'is-warning' ?>">
                    <i class="fas fa-<?= $action=='add' ? 'plus' : 'edit' ?>-circle me-2"></i>
                    <?= $action=='add' ? 'Add New Service' : 'Edit Service' ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="mode" value="<?= $action ?>">
                        <input type="hidden" name="original_id" value="<?= (int)($edit_row['id'] ?? 0) ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="modern-form-label">Service Name</label>
                                <input type="text" name="service_name" class="modern-form-control"
                                       value="<?= htmlspecialchars($edit_row['service_name'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="modern-form-label">Price (₱)</label>
                                <input type="number" step="0.01" name="price" class="modern-form-control"
                                       value="<?= htmlspecialchars($edit_row['price'] ?? '0.00') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="modern-form-label">Status</label>
                                <select name="status" class="modern-form-select" required>
                                    <option value="active" <?= (isset($edit_row['status']) && $edit_row['status']=='active') ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= (isset($edit_row['status']) && $edit_row['status']=='inactive') ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="modern-form-label">Description</label>
                                <textarea name="description" class="modern-form-control" rows="3"><?= htmlspecialchars($edit_row['description'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-12 mt-4">
                                <button type="submit" name="save_service" class="modern-btn modern-btn-primary">
                                    <i class="fas fa-save me-2"></i> <?= $action=='add' ? 'Save' : 'Update' ?>
                                </button>
                                <a href="home.php?tab=services" class="modern-btn modern-btn-secondary ms-2">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="modern-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-concierge-bell me-2"></i> Services Management</span>
                    <a href="home.php?tab=services&action=add" class="modern-btn modern-btn-success modern-btn-sm">
                        <i class="fas fa-plus-circle me-1"></i> Add New Service
                    </a>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Service Name</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if($services->num_rows > 0): ?>
                                <?php while($row = $services->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['service_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(substr($row['description'] ?? '', 0, 50)) . (strlen($row['description'] ?? '') > 50 ? '...' : '') ?></td>
                                    <td><strong>₱<?= number_format((float)($row['price'] ?? 0),2) ?></strong></td>
                                    <td>
                                        <span class="status-badge <?= strtolower((string)($row['status'] ?? 'inactive')) ?>">
                                            <?= htmlspecialchars(ucfirst((string)($row['status'] ?? 'inactive'))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="home.php?tab=services&action=edit&id=<?= (int)$row['id'] ?>&page=<?= $page ?>"
                                           class="modern-btn modern-btn-warning modern-btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <a href="home.php?tab=services&action=delete&id=<?= (int)$row['id'] ?>&page=<?= $page ?>"
                                           class="modern-btn modern-btn-danger modern-btn-sm js-confirm"
                                           data-confirm="Delete this service?"
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-concierge-bell"></i>
                                            <p>No services found. Click "Add New Service" to create one.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if($total_pages > 1): ?>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4">
                        <div class="mb-3 mb-md-0">
                            <span class="text-muted">Showing page <?= $page ?> of <?= $total_pages ?> (<?= number_format($total_records) ?> total services)</span>
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0 flex-wrap">
                                <?php if($page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="home.php?tab=services&page=1"><i class="fas fa-angle-double-left"></i></a></li>
                                    <li class="page-item"><a class="page-link" href="home.php?tab=services&page=<?= $page-1 ?>"><i class="fas fa-chevron-left"></i></a></li>
                                <?php endif; ?>

                                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                    <li class="page-item <?= $i==$page ? 'active' : '' ?>">
                                        <a class="page-link" href="home.php?tab=services&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if($page < $total_pages): ?>
                                    <li class="page-item"><a class="page-link" href="home.php?tab=services&page=<?= $page+1 ?>"><i class="fas fa-chevron-right"></i></a></li>
                                    <li class="page-item"><a class="page-link" href="home.php?tab=services&page=<?= $total_pages ?>"><i class="fas fa-angle-double-right"></i></a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php $stmt->close(); ?>

            <?php elseif($tab=='notice_assessment'):

                $week_key = (int)$conn->query("SELECT YEARWEEK(CURDATE(), 1) AS wk")->fetch_assoc()['wk'];

                $selected_barangay = $_GET['barangay'] ?? '';
                $search = trim($_GET['search'] ?? '');

                $where  = "WHERE week_key = ?";
                $types  = "i";
                $params = [$week_key];

                if($selected_barangay !== ''){
                    $where .= " AND barangay = ?";
                    $types .= "s";
                    $params[] = $selected_barangay;
                }

                if($search !== ''){
                    $where .= " AND (
                        declared_owner LIKE CONCAT('%', ?, '%')
                        OR owner_address LIKE CONCAT('%', ?, '%')
                        OR arp_no LIKE CONCAT('%', ?, '%')
                        OR pin_no LIKE CONCAT('%', ?, '%')
                        OR property_location LIKE CONCAT('%', ?, '%')
                        OR classification LIKE CONCAT('%', ?, '%')
                    )";
                    $types .= "ssssss";
                    array_push($params, $search, $search, $search, $search, $search, $search);
                }

                $sql = "
                    SELECT
                        id,
                        owner_key,
                        declared_owner,
                        owner_address,
                        barangay,
                        arp_no,
                        pin_no,
                        property_location,
                        classification,
                        mv,
                        av,
                        created_at
                    FROM notice_of_assessment_logs
                    $where
                    ORDER BY declared_owner ASC, owner_key ASC, created_at DESC
                ";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $rows = $stmt->get_result();

                $groups = [];
                while($r = $rows->fetch_assoc()){
                    $okey = (string)($r['owner_key'] ?? '');
                    if($okey === '') $okey = 'unknown';

                    if(!isset($groups[$okey])){
                        $groups[$okey] = [
                            'owner_key' => $okey,
                            'declared_owner' => (string)($r['declared_owner'] ?? ''),
                            'owner_address' => (string)($r['owner_address'] ?? ''),
                            'items' => []
                        ];
                    }
                    $groups[$okey]['items'][] = $r;
                }
            ?>

            <div class="modern-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-bullhorn me-2"></i> Notice of Assessment (This Week)</span>
                    <span class="badge bg-primary badge-pill">
                        Week Key: <?= (int)$week_key ?>
                    </span>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="tab" value="notice_assessment">

                        <div class="col-md-4">
                            <label class="modern-form-label">Barangay</label>
                            <select name="barangay" class="modern-form-select">
                                <option value="">-- All Barangay --</option>
                                <?php foreach($allowed_tables as $t): ?>
                                    <option value="<?= $t ?>" <?= $selected_barangay===$t?'selected':'' ?>>
                                        <?= ucwords(str_replace('_',' ',$t)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="modern-form-label">Search (Owner / Address / ARP / PIN / Location / Class)</label>
                            <input type="text" name="search" class="modern-form-control"
                                   value="<?= htmlspecialchars($search) ?>"
                                   placeholder="e.g. Juan Dela Cruz / ARP / PIN / Location">
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button class="modern-btn modern-btn-primary w-100" type="submit">
                                <i class="fas fa-search me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modern-card">
                <div class="card-header">
                    <i class="fas fa-table me-2"></i> Weekly Logs (Per Owner Table)
                </div>

                <div class="card-body">
                    <?php if(!empty($groups)): ?>
                        <?php foreach($groups as $g): ?>
                            <?php
                                $owner_key = $g['owner_key'];
                                $owner_name = $g['declared_owner'] ?: '-';
                                $owner_address = $g['owner_address'] ?: '-';
                                $count_items = count($g['items']);
                            ?>
                            <div class="modern-card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($owner_name) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($owner_address) ?></small>
                                    </div>

                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="badge bg-primary badge-pill">
                                            <?= (int)$count_items ?> <?= $count_items===1?'Entry':'Entries' ?>
                                        </span>

                                        <a class="modern-btn modern-btn-success modern-btn-sm"
                                           href="export_noa_excel.php?week_key=<?= (int)$week_key ?>&owner_key=<?= urlencode($owner_key) ?>">
                                            <i class="fas fa-download me-1"></i> Export
                                        </a>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="modern-table">
                                            <thead>
                                                <tr>
                                                    <th>Barangay</th>
                                                    <th>ARP No.</th>
                                                    <th>PIN No.</th>
                                                    <th>Property Location</th>
                                                    <th>Classification</th>
                                                    <th>MV</th>
                                                    <th>AV</th>
                                                    <th>Added</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach($g['items'] as $r): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(ucwords(str_replace('_',' ', $r['barangay'] ?? '-'))) ?></td>
                                                    <td><?= htmlspecialchars($r['arp_no'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($r['pin_no'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($r['property_location'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($r['classification'] ?? '-') ?></td>
                                                    <td><strong>₱<?= number_format((float)($r['mv'] ?? 0), 2) ?></strong></td>
                                                    <td><strong>₱<?= number_format((float)($r['av'] ?? 0), 2) ?></strong></td>
                                                    <td><?= !empty($r['created_at']) ? date('M d, Y h:i A', strtotime($r['created_at'])) : '-' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state text-center py-4">
                            <i class="fas fa-bullhorn"></i>
                            <p>No logs found for this week.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php $stmt->close(); ?>

            <?php endif; ?>

        </div><!-- /.content-area -->
    </main>
</div><!-- /.wrapper -->

<!-- Required Scripts (LOCAL) -->
<script src="../assets/js/jquery-3.7.1.min.js" defer></script>
<script src="../assets/js/datatables.min.js" defer></script>
<script src="../assets/js/bootstrap.bundle.min.js" defer></script>

<!-- ✅ Chart.js (NO defer para safe) -->
<script src="../vendor/chart.js/chart.umd.min.js"></script>

<?php if($tab==='dashboard'): ?>
<script>
(function(){
    // PIE
    const pieEl = document.getElementById('pieCertSvc');
    if (pieEl) {
        const pieLabels = <?= json_encode($chart_pie_labels ?? []) ?>;
        const pieData   = <?= json_encode($chart_pie_data ?? []) ?>;

        new Chart(pieEl, {
            type: 'pie',
            data: {
                labels: pieLabels,
                datasets: [{ data: pieData }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // WEEKLY LINE
    const wkEl = document.getElementById('weeklyRequests');
    if (wkEl) {
        const labels = <?= json_encode($weekly_labels ?? []) ?>;
        const counts = <?= json_encode($weekly_counts ?? []) ?>;

        new Chart(wkEl, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Requests',
                    data: counts,
                    tension: 0.35,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                },
                plugins: { legend: { display: true } }
            }
        });
    }
})();
</script>
<?php endif; ?>
<script src="../assets/js/admin.js" defer></script>

</body>
</html>
<?php ob_end_flush(); ?>