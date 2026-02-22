<?php
require_once "../includes/auth_check.php";
require_once "../config/database.php";

// FAAS allowed barangay tables
$allowed_tables = [
    'alicia','cabugao','dagupan','diodol','dumabel','dungo',
    'guinalbin','nagabgaban','palacian','pinaripad_norte',
    'pinaripad_sur','progreso','ramos','rangayan',
    'san_antonio','san_benigno','san_francisco','san_leonardo',
    'san_manuel','san_ramon','victoria',
    'villa_pagaduan','villa_santiago','villa_ventura'
];

// Handle Admin Actions
if(isset($_GET['prepare'])){
    $req_id = intval($_GET['prepare']);
    $stmt = $conn->prepare("UPDATE requests SET status='PREPARED' WHERE id=? AND status='PAID'");
    $stmt->bind_param("i",$req_id);
    $stmt->execute();
    $stmt->close();
    header("Location: home.php?tab=requests");
    exit();
}
if(isset($_GET['release'])){
    $req_id = intval($_GET['release']);
    $stmt = $conn->prepare("UPDATE requests SET status='RELEASED' WHERE id=? AND status='PREPARED'");
    $stmt->bind_param("i",$req_id);
    $stmt->execute();
    $stmt->close();
    header("Location: home.php?tab=requests");
    exit();
}

$tab = $_GET['tab'] ?? 'dashboard';

// Get counts for dashboard
if($tab == 'dashboard') {
    $pending_count  = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='PENDING'")->fetch_assoc()['count'];
    $paid_count     = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='PAID'")->fetch_assoc()['count'];
    $prepared_count = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='PREPARED'")->fetch_assoc()['count'];
    $released_count = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='RELEASED'")->fetch_assoc()['count'];
    $total_faas = 0;
    foreach($allowed_tables as $table) {
        $count = $conn->query("SELECT COUNT(*) as count FROM `$table`")->fetch_assoc()['count'];
        $total_faas += $count;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - T.R.A.C.S</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Required Stylesheets -->
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-building me-2"></i><span>T.R.A.C.S</span></h4>
        </div>
        <nav class="nav flex-column">
            <a href="home.php" class="nav-link <?= $tab=='dashboard'?'active':'' ?>"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a>
            <a href="home.php?tab=requests" class="nav-link <?= $tab=='requests'?'active':'' ?>"><i class="fas fa-clipboard-list"></i><span>Requests</span></a>
            <a href="home.php?tab=history" class="nav-link <?= $tab=='history'?'active':'' ?>"><i class="fas fa-history"></i><span>Transaction History</span></a>
            <a href="home.php?tab=import" class="nav-link <?= $tab=='import'?'active':'' ?>"><i class="fas fa-file-import"></i><span>Import Data</span></a>
            <a href="home.php?tab=find" class="nav-link <?= $tab=='find'?'active':'' ?>"><i class="fas fa-search"></i><span>Find Record</span></a>
            <a href="home.php?tab=faas" class="nav-link <?= $tab=='faas'?'active':'' ?>"><i class="fas fa-folder-tree"></i><span>FAAS Management</span></a>
            <a href="home.php?tab=certificates" class="nav-link <?= $tab=='certificates'?'active':'' ?>"><i class="fas fa-certificate"></i><span>Certificates</span></a>
        </nav>
        <div class="mt-auto">
            <hr class="bg-white opacity-25">
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt me-2"></i><span>Logout</span></a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="menu-toggle-btn" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="page-title"><?= ucfirst($tab) ?></span>
            </div>

            <div class="user-profile-pill">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span class="user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
            </div>
        </nav>

        <div class="content-area">
            <?php if($tab=='dashboard'): ?>

                <!-- Dashboard Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-info"><h3><?= $pending_count ?></h3><p>Pending Requests</p></div>
                            <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-info"><h3><?= $paid_count + $prepared_count + $released_count ?></h3><p>Processed Requests</p></div>
                            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-info"><h3><?= number_format($total_faas) ?></h3><p>Total FAAS Records</p></div>
                            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                        </div>
                    </div>
                </div>

                <div class="modern-card">
                    <div class="card-header"><i class="fas fa-chart-line me-2"></i> Welcome Back!</div>
                    <div class="card-body">
                        <h5>Hello, <?= htmlspecialchars($_SESSION['fullname']) ?> 👋</h5>
                        <p class="text-muted">You have <?= $pending_count ?> pending requests that need attention.</p>
                    </div>
                </div>

            <?php elseif($tab=='requests'): ?>

                <!-- REQUESTS -->
                <div class="modern-card">
                    <div class="card-header"><i class="fas fa-clipboard-list me-2"></i> All Requests</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>ID</th><th>Client</th><th>Purpose</th><th>Certificates</th>
                                        <th>Total</th><th>Control No</th><th>Status</th><th>Action</th><th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $sql = "SELECT r.id, CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname, c.purpose, r.total_amount, r.control_number, r.status, r.created_at
                                        FROM requests r
                                        JOIN clients c ON r.client_id = c.id
                                        ORDER BY r.created_at DESC";
                                $result = $conn->query($sql);
                                while($row = $result->fetch_assoc()):
                                    $cert_sql = "SELECT c.certificate_name
                                                 FROM request_items ri
                                                 JOIN certificates c ON ri.certificate_id = c.id
                                                 WHERE ri.request_id=".$row['id'];
                                    $cert_res = $conn->query($cert_sql);
                                    $certs = [];
                                    while($c = $cert_res->fetch_assoc()) $certs[] = $c['certificate_name'];
                                ?>
                                <tr>
                                    <td>#<?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['fullname']) ?></td>
                                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                                    <td><?= htmlspecialchars(implode(", ", $certs)) ?></td>
                                    <td><strong>₱<?= number_format($row['total_amount'],2) ?></strong></td>
                                    <td><?= htmlspecialchars($row['control_number']) ?></td>
                                    <td><span class="status-badge <?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
                                    <td>
                                        <?php if($row['status']=='PAID'): ?>
                                            <a href="home.php?prepare=<?= $row['id'];?>" class="modern-btn modern-btn-info modern-btn-sm">
                                                <i class="fas fa-check-circle me-1"></i> Prepared
                                            </a>
                                        <?php elseif($row['status']=='PREPARED'): ?>
                                            <a href="home.php?release=<?= $row['id'];?>" class="modern-btn modern-btn-primary modern-btn-sm">
                                                <i class="fas fa-check-double me-1"></i> Released
                                            </a>
                                        <?php else: ?>
                                            <a href="process_certificate.php?request_id=<?= $row['id']; ?>"
                                               class="modern-btn modern-btn-warning modern-btn-sm">
                                                <i class="fas fa-cogs me-1"></i> Process
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif($tab=='history'): ?>

                <!-- Transaction History -->
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
                                $sql2 = "SELECT r.id, CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname, c.purpose, r.total_amount, r.control_number, r.status, r.created_at
                                        FROM requests r
                                        JOIN clients c ON r.client_id=c.id
                                        WHERE r.status IN('PAID','DECLINED','PREPARED','RELEASED')
                                        ORDER BY r.created_at DESC";
                                $res2 = $conn->query($sql2);
                                while($row = $res2->fetch_assoc()):
                                    $cert_sql = "SELECT c.certificate_name
                                                 FROM request_items ri
                                                 JOIN certificates c ON ri.certificate_id = c.id
                                                 WHERE ri.request_id=".$row['id'];
                                    $cert_res = $conn->query($cert_sql);
                                    $certs = [];
                                    while($c = $cert_res->fetch_assoc()) $certs[] = $c['certificate_name'];
                                ?>
                                <tr>
                                    <td>#<?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['fullname']) ?></td>
                                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                                    <td><?= htmlspecialchars(implode(", ", $certs)) ?></td>
                                    <td><strong>₱<?= number_format($row['total_amount'],2) ?></strong></td>
                                    <td><?= htmlspecialchars($row['control_number']) ?></td>
                                    <td>
                                        <?php
                                        $status = $row['status'];
                                        $status_class = in_array($status,['PENDING','PAID','DECLINED','PREPARED','RELEASED']) ? strtolower($status) : '';
                                        ?>
                                        <span class="status-badge <?= $status_class ?>"><?= $status ?></span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif($tab=='import'): ?>

                <!-- Import Data -->
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
                $results = [];
                $selected_barangay = $_GET['barangay'] ?? '';
                $search_name = trim($_GET['search'] ?? '');
                if (!empty($selected_barangay) && !empty($search_name) && in_array($selected_barangay, $allowed_tables)) {
                    $stmt = $conn->prepare("SELECT * FROM `$selected_barangay` WHERE declared_owner LIKE CONCAT('%', ?, '%')");
                    $stmt->bind_param("s", $search_name);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while($row = $res->fetch_assoc()) $results[] = $row;
                    $stmt->close();
                }
                ?>

                <div class="scroll-indicator"><i class="fas fa-arrow-left me-2"></i> Swipe to scroll table <i class="fas fa-arrow-right ms-2"></i></div>
                <div class="modern-card mb-4">
                    <div class="card-header"><i class="fas fa-search me-2"></i> Find Record</div>
                    <div class="card-body">
                        <form method="GET">
                            <input type="hidden" name="tab" value="find">
                            <div class="row g-3">
                                <div class="col-md-5 col-sm-12">
                                    <label class="modern-form-label">Barangay</label>
                                    <select name="barangay" class="modern-form-select" required>
                                        <option value="">-- Select Barangay --</option>
                                        <?php foreach($allowed_tables as $table): ?>
                                            <option value="<?= $table ?>" <?= $selected_barangay==$table ? "selected" : "" ?>><?= ucwords(str_replace('_',' ',$table)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5 col-sm-12">
                                    <label class="modern-form-label">Search Declared Owner</label>
                                    <input type="text" name="search" class="modern-form-control" value="<?= htmlspecialchars($search_name) ?>" placeholder="Enter declared owner name" required>
                                </div>
                                <div class="col-md-2 col-sm-12 d-flex align-items-end">
                                    <button type="submit" class="modern-btn modern-btn-primary w-100"><i class="fas fa-search me-2"></i> Search</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if(!empty($results)): ?>
                    <div class="modern-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-list me-2"></i> Search Results</span>
                            <span class="badge bg-primary" style="font-size: 0.9rem; padding: 8px 15px; border-radius: 30px;">
                                <i class="fas fa-database me-1"></i> Found: <?= count($results) ?> record(s)
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
                                        <?php foreach($results as $row): ?>
                                            <tr>
                                                <?php foreach(['declared_owner','owner_address','property_location','title','lot','ARP_No.','PIN_No.','classification','actual_use','area','mv','av','taxability','effectivity','cancellation'] as $field): ?>
                                                    <td title="<?= htmlspecialchars($row[$field]) ?>"><?= htmlspecialchars($row[$field]) ?></td>
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
                <?php elseif(!empty($search_name)): ?>
                    <div class="modern-alert modern-alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> No records found for "<?= htmlspecialchars($search_name) ?>" in <?= ucwords(str_replace('_',' ',$selected_barangay)) ?>.
                    </div>
                <?php endif; ?>

            <?php elseif($tab=='faas'): ?>

                <?php
                $selected_barangay = $_GET['barangay'] ?? '';
                $action = $_GET['action'] ?? '';
                $id = $_GET['id'] ?? '';
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                if($page < 1) $page = 1;

                // search term
                $search_owner = trim($_GET['search_owner'] ?? '');

                if($selected_barangay && !in_array($selected_barangay,$allowed_tables)) die("Invalid barangay.");

                $fields = [
                'ARP_No.','declared_owner',
                'owner_address','property_location','title','lot',
                'PIN_No.','classification','actual_use','area',
                'mv','av','taxability','effectivity','cancellation'
                ];

                $labels = [
                'ARP No.','Declared Owner',
                'Owner Address','Property Location','Title','Lot',
                'PIN No.','Classification','Actual Use','Area',
                'Market Value','Assessed Value','Taxability','Effectivity','Cancellation'
            ];

                // Delete
                if($action=='delete' && $selected_barangay && $id){
                    $stmt = $conn->prepare("DELETE FROM `$selected_barangay` WHERE `PIN_No.`=?");
                    $stmt->bind_param("s",$id);
                    $stmt->execute();
                    $stmt->close();

                    $qs = "tab=faas&barangay=".urlencode($selected_barangay)."&page=".$page;
                    if($search_owner !== '') $qs .= "&search_owner=".urlencode($search_owner);
                    header("Location: home.php?$qs");
                    exit();
                }

                // Save Add/Edit
                if(isset($_POST['save_faas'])){
                    $brgy = $_POST['barangay'];
                    if(!in_array($brgy,$allowed_tables)) die("Invalid barangay.");
                    $data = [];
                    foreach($fields as $field) $data[$field] = $_POST[$field] ?? '';

                    if($_POST['mode']=='add'){
                        $stmt = $conn->prepare("INSERT INTO `$brgy` (declared_owner,owner_address,property_location,title,lot,`ARP_No.`,`PIN_No.`,classification,actual_use,area,mv,av,taxability,effectivity,cancellation)
                                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $stmt->bind_param(
                            "sssssssssssssss",
                            $data['declared_owner'],$data['owner_address'],$data['property_location'],$data['title'],$data['lot'],
                            $data['ARP_No.'],$data['PIN_No.'],$data['classification'],$data['actual_use'],$data['area'],
                            $data['mv'],$data['av'],$data['taxability'],$data['effectivity'],$data['cancellation']
                        );
                    } elseif($_POST['mode']=='edit'){
                        $original_pin = $_POST['original_pin'];
                        $stmt = $conn->prepare("UPDATE `$brgy`
                                                SET declared_owner=?,owner_address=?,property_location=?,title=?,lot=?,`ARP_No.`=?,`PIN_No.`=?,classification=?,actual_use=?,area=?,mv=?,av=?,taxability=?,effectivity=?,cancellation=?
                                                WHERE `PIN_No.`=?");
                        $stmt->bind_param(
                            "ssssssssssssssss",
                            $data['declared_owner'],$data['owner_address'],$data['property_location'],$data['title'],$data['lot'],
                            $data['ARP_No.'],$data['PIN_No.'],$data['classification'],$data['actual_use'],$data['area'],
                            $data['mv'],$data['av'],$data['taxability'],$data['effectivity'],$data['cancellation'],
                            $original_pin
                        );
                    }
                    $stmt->execute();
                    $stmt->close();
                    header("Location: home.php?tab=faas&barangay=".urlencode($brgy)."&page=".$page);
                    exit();
                }
                ?>

                <!-- Barangay Select -->
                <div class="modern-card mb-4">
                    <div class="card-header"><i class="fas fa-folder-tree me-2"></i> FAAS Management</div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="tab" value="faas">
                            <div class="col-md-6">
                                <select name="barangay" class="modern-form-select" required>
                                    <option value="">-- Select Barangay --</option>
                                    <?php foreach($allowed_tables as $table): ?>
                                        <option value="<?= $table ?>" <?= $selected_barangay==$table ? "selected" : "" ?>><?= ucwords(str_replace('_',' ',$table)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="modern-btn modern-btn-primary w-100"><i class="fas fa-sync-alt me-2"></i> Load</button>
                            </div>
                            <?php if($selected_barangay): ?>
                                <div class="col-md-3">
                                    <a href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&action=add" class="modern-btn modern-btn-success w-100">
                                        <i class="fas fa-plus-circle me-2"></i> Add FAAS
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Search Person -->
                <?php if($selected_barangay && $action!='add' && $action!='edit'): ?>
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
                                    <button class="modern-btn modern-btn-primary w-100">
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

                <!-- Add/Edit Form -->
                <?php if(($action=='add' || $action=='edit') && $selected_barangay):
                    $edit_row = [];
                    if($action=='edit' && $id){
                        $stmt = $conn->prepare("SELECT * FROM `$selected_barangay` WHERE `PIN_No.`=?");
                        $stmt->bind_param("s",$id);
                        $stmt->execute();
                        $edit_row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                    }
                ?>
                <div class="modern-card mb-4">
                    <div class="card-header" style="background: <?= $action=='add' ? 'linear-gradient(135deg, #28a745, #20c997)' : 'linear-gradient(135deg, #ffc107, #fd7e14)' ?>; color: white;">
                        <i class="fas fa-<?= $action=='add' ? 'plus' : 'edit' ?>-circle me-2"></i><?= $action=='add' ? 'Add New FAAS' : 'Edit FAAS Record' ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="mode" value="<?= $action ?>">
                            <input type="hidden" name="barangay" value="<?= $selected_barangay ?>">
                            <input type="hidden" name="original_pin" value="<?= $edit_row['PIN_No.'] ?? '' ?>">
                            <div class="row g-3">
                                <?php foreach($fields as $i=>$field): ?>
                                    <div class="col-md-6">
                                        <input name="<?= $field ?>" class="modern-form-control" placeholder="<?= $labels[$i] ?>" value="<?= htmlspecialchars($edit_row[$field] ?? '') ?>">
                                    </div>
                                <?php endforeach; ?>
                                <div class="col-md-12 mt-4">
                                    <button type="submit" name="save_faas" class="modern-btn modern-btn-primary"><i class="fas fa-save me-2"></i> <?= $action=='add' ? 'Save' : 'Update' ?></button>
                                    <a href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>" class="modern-btn modern-btn-secondary ms-2"><i class="fas fa-times me-2"></i> Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Table with Pagination -->
                <?php if($selected_barangay && $action!='add' && $action!='edit'):

                    $limit = 15;
                    $offset = ($page - 1) * $limit;

                    if($search_owner !== ''){
                        $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM `$selected_barangay` WHERE declared_owner LIKE CONCAT('%', ?, '%')");
                        $stmtCount->bind_param("s", $search_owner);
                        $stmtCount->execute();
                        $total_records = $stmtCount->get_result()->fetch_assoc()['total'];
                        $stmtCount->close();

                        $total_pages = ceil($total_records / $limit);

                        $stmt = $conn->prepare("SELECT * FROM `$selected_barangay`
                                                WHERE declared_owner LIKE CONCAT('%', ?, '%')
                                                ORDER BY declared_owner ASC
                                                LIMIT ? OFFSET ?");
                        $stmt->bind_param("sii", $search_owner, $limit, $offset);
                    } else {
                        $count = $conn->query("SELECT COUNT(*) as total FROM `$selected_barangay`");
                        $total_records = $count->fetch_assoc()['total'];

                        $total_pages = ceil($total_records / $limit);

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
                                <span class="badge bg-warning text-dark ms-2">Filtered: <?= htmlspecialchars($search_owner) ?></span>
                            <?php endif; ?>
                        </span>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-primary" style="font-size: 0.9rem; padding: 8px 15px; border-radius: 30px;">
                                <i class="fas fa-database me-1"></i> Total: <?= number_format($total_records) ?> records
                            </span>
                            <a href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&action=add" class="modern-btn modern-btn-success modern-btn-sm">
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
                                                <th><?= $label ?></th>
                                            <?php endforeach; ?>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php while($row=$res->fetch_assoc()): ?>
                                        <?php $is_cancelled = !empty(trim($row['cancellation'] ?? '')); ?>
                                        <tr class="<?= $is_cancelled ? 'faas-cancelled-row' : '' ?>">
                                            <?php foreach($fields as $field): ?>
                                                <td title="<?= htmlspecialchars($row[$field]) ?>"><?= htmlspecialchars($row[$field]) ?></td>
                                            <?php endforeach; ?>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&action=edit&id=<?= urlencode($row['PIN_No.']) ?>&page=<?= $page ?><?= $search_qs ?>"
                                                       class="modern-btn modern-btn-warning modern-btn-sm" title="Edit">
                                                        <i class="fas fa-edit"></i> <span class="d-none d-md-inline ms-1">Edit</span>
                                                    </a>

                                                    <a href="home.php?tab=faas&barangay=<?= urlencode($selected_barangay) ?>&action=delete&id=<?= urlencode($row['PIN_No.']) ?>&page=<?= $page ?><?= $search_qs ?>"
                                                       class="modern-btn modern-btn-danger modern-btn-sm" title="Delete"
                                                       onclick="return confirm('Delete this record?');">
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
                    <div class="card-footer bg-transparent">
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0 justify-content-center flex-wrap" style="gap: 3px;">
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

                // Delete
                if($action=='delete' && $id){
                    $stmt = $conn->prepare("DELETE FROM certificates WHERE id=?");
                    $stmt->bind_param("i",$id);
                    $stmt->execute();
                    $stmt->close();
                    header("Location: home.php?tab=certificates&page=$page");
                    exit();
                }

                // Add/Edit
                if(isset($_POST['save_certificate'])){
                    $name = $_POST['certificate_name'];
                    $description = $_POST['description'];
                    $price = $_POST['price'];
                    $status = $_POST['status'];
                    $mode = $_POST['mode'];
                    $original_id = $_POST['original_id'];
                    if($mode=='add'){
                        $stmt = $conn->prepare("INSERT INTO certificates (certificate_name, description, price, status) VALUES (?,?,?,?)");
                        $stmt->bind_param("ssds",$name,$description,$price,$status);
                    } elseif($mode=='edit'){
                        $stmt = $conn->prepare("UPDATE certificates SET certificate_name=?, description=?, price=?, status=? WHERE id=?");
                        $stmt->bind_param("ssdsi",$name,$description,$price,$status,$original_id);
                    }
                    $stmt->execute();
                    $stmt->close();
                    header("Location: home.php?tab=certificates&page=$page");
                    exit();
                }

                // Fetch edit row
                $edit_row = [];
                if($action=='edit' && $id){
                    $stmt = $conn->prepare("SELECT * FROM certificates WHERE id=?");
                    $stmt->bind_param("i",$id);
                    $stmt->execute();
                    $edit_row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }

                // Pagination
                $limit = 15;
                $offset = ($page - 1) * $limit;
                $count = $conn->query("SELECT COUNT(*) as total FROM certificates");
                $total_records = $count->fetch_assoc()['total'];
                $total_pages = ceil($total_records / $limit);
                $stmt = $conn->prepare("SELECT * FROM certificates ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $stmt->bind_param("ii",$limit, $offset);
                $stmt->execute();
                $certificates = $stmt->get_result();
            ?>

            <!-- Add/Edit Certificate Form -->
            <?php if($action=='add' || ($action=='edit' && $id)): ?>
            <div class="modern-card mb-4">
                <div class="card-header" style="background: <?= $action=='add' ? 'linear-gradient(135deg, #28a745, #20c997)' : 'linear-gradient(135deg, #ffc107, #fd7e14)' ?>; color: white;">
                    <i class="fas fa-<?= $action=='add' ? 'plus' : 'edit' ?>-circle me-2"></i><?= $action=='add' ? 'Add New Certificate' : 'Edit Certificate' ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="mode" value="<?= $action ?>">
                        <input type="hidden" name="original_id" value="<?= $edit_row['id'] ?? '' ?>">
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
                                <button type="submit" name="save_certificate" class="modern-btn modern-btn-primary"><i class="fas fa-save me-2"></i> <?= $action=='add' ? 'Save' : 'Update' ?></button>
                                <a href="home.php?tab=certificates" class="modern-btn modern-btn-secondary ms-2"><i class="fas fa-times me-2"></i> Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Certificates Table -->
            <div class="modern-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-certificate me-2"></i> Certificates Management</span>
                    <a href="home.php?tab=certificates&action=add" class="modern-btn modern-btn-success modern-btn-sm"><i class="fas fa-plus-circle me-1"></i> Add New Certificate</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead><tr><th>Certificate Name</th><th>Description</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php if($certificates->num_rows > 0): ?>
                                <?php while($row = $certificates->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['certificate_name']) ?></td>
                                    <td><?= htmlspecialchars(substr($row['description'] ?? '', 0, 50)) . (strlen($row['description'] ?? '') > 50 ? '...' : '') ?></td>
                                    <td><strong>₱<?= number_format($row['price'],2) ?></strong></td>
                                    <td><span class="status-badge <?= strtolower($row['status']) ?>"><?= ucfirst($row['status']) ?></span></td>
                                    <td>
                                        <a href="home.php?tab=certificates&action=edit&id=<?= $row['id'] ?>&page=<?= $page ?>" class="modern-btn modern-btn-warning modern-btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="home.php?tab=certificates&action=delete&id=<?= $row['id'] ?>&page=<?= $page ?>" class="modern-btn modern-btn-danger modern-btn-sm" title="Delete" onclick="return confirm('Are you sure you want to delete this certificate?');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
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

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4">
                        <div class="mb-3 mb-md-0"><span class="text-muted">Showing page <?= $page ?> of <?= $total_pages ?> (<?= number_format($total_records) ?> total certificates)</span></div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0 flex-wrap" style="gap: 3px;">
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
            <?php $stmt->close(); endif; ?>
        </div>
    </main>
</div>

<!-- Required Scripts -->
<script src="../assets/js/jquery-3.7.1.min.js"></script>
<script src="../assets/js/datatables.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    // Toggle sidebar
    menuToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    });

    // Auto-collapse on mobile
    const handleResize = () => {
        if (window.innerWidth <= 992) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        } else {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
        }
    };
    handleResize();
    window.addEventListener('resize', handleResize);

    // Show scroll hints for wide tables
    const checkScroll = () => {
        document.querySelectorAll('.scrollable-container').forEach(container => {
            const hint = container.parentElement.querySelector('.scroll-hint');
            if (hint) hint.style.display = (container.scrollWidth > container.clientWidth) ? 'flex' : 'none';
        });
    };
    checkScroll();
    window.addEventListener('resize', checkScroll);
});

// Optional: Initialize DataTables if needed
$(document).ready(function() {
    // $('.modern-table').DataTable({ paging: false, searching: true });
});
</script>
</body>
</html>