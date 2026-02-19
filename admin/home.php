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
    $pending_count = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='PENDING'")->fetch_assoc()['count'];
    $paid_count = $conn->query("SELECT COUNT(*) as count FROM requests WHERE status='PAID'")->fetch_assoc()['count'];
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
    <title>Admin Dashboard - Barangay System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
</head>
<body>

<div class="wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-building me-2"></i>Barangay System</h4>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="home.php" class="nav-link <?php echo ($tab=='dashboard')?'active':''; ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="home.php?tab=requests" class="nav-link <?php echo ($tab=='requests')?'active':''; ?>">
                    <i class="fas fa-clipboard-list"></i> Requests
                </a>
            </li>
            <li class="nav-item">
                <a href="home.php?tab=history" class="nav-link <?php echo ($tab=='history')?'active':''; ?>">
                    <i class="fas fa-history"></i> Transaction History
                </a>
            </li>
            <li class="nav-item">
                <a href="home.php?tab=import" class="nav-link <?php echo ($tab=='import')?'active':''; ?>">
                    <i class="fas fa-file-import"></i> Import Data
                </a>
            </li>
            <li class="nav-item">
                <a href="home.php?tab=find" class="nav-link <?php echo ($tab=='find')?'active':''; ?>">
                    <i class="fas fa-search"></i> Find Record
                </a>
            </li>
            <li class="nav-item">
                <a href="home.php?tab=faas" class="nav-link <?php echo ($tab=='faas')?'active':''; ?>">
                    <i class="fas fa-folder-tree"></i> FAAS Management
                </a>
            </li>
            <li class="nav-item">
    <a href="home.php?tab=certificates" class="nav-link <?php echo ($tab=='certificates')?'active':''; ?>">
        <i class="fas fa-certificate"></i> Certificates
    </a>
</li>

        </ul>

        <div class="mt-auto">
            <hr class="bg-white opacity-25">
            <a href="../logout.php" class="logout-btn d-block text-center">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content" id="mainContent">
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div>
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="ms-3 fw-bold" style="color: var(--primary-dark);"><?php echo ucfirst($tab); ?></span>
            </div>
            <span class="user-badge">
                <i class="fas fa-user-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['fullname']); ?>
            </span>
        </nav>

        <div class="content-area">

        <?php if($tab=='dashboard'): ?>
            <!-- Dashboard Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo $pending_count; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo $paid_count + $prepared_count + $released_count; ?></h3>
                            <p>Processed Requests</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><?php echo number_format($total_faas); ?></h3>
                            <p>Total FAAS Records</p>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modern-card">
                <div class="card-header">
                    <i class="fas fa-chart-line me-2"></i> Welcome Back!
                </div>
                <div class="card-body">
                    <h5>Hello, <?php echo htmlspecialchars($_SESSION['fullname']); ?> 👋</h5>
                    <p class="text-muted">You have <?php echo $pending_count; ?> pending requests that need attention.</p>
                </div>
            </div>

<?php elseif($tab=='requests'): ?>
<!-- REQUESTS -->
<div class="modern-card">
    <div class="card-header bg-secondary">
        <i class="fas fa-clipboard-list me-2"></i> All Requests
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Purpose</th>
                        <th>Certificates</th>
                        <th>Total</th>
                        <th>Control No</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sql = "SELECT r.id,
                               CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname,
                               c.purpose,
                               r.total_amount,
                               r.control_number,
                               r.status,
                               r.created_at
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
                    <td>#<?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                    <td><?php echo htmlspecialchars(implode(", ", $certs)); ?></td>
                    <td>₱<?php echo number_format($row['total_amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                    <td>
                        <span class="status-badge <?php echo strtolower($row['status']); ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($row['status']=='PAID'): ?>
                            <a href="home.php?prepare=<?php echo $row['id'];?>" class="modern-btn modern-btn-info modern-btn-sm">
                                <i class="fas fa-check-circle me-1"></i> Prepared
                            </a>
                        <?php elseif($row['status']=='PREPARED'): ?>
                            <a href="home.php?release=<?php echo $row['id'];?>" class="modern-btn modern-btn-primary modern-btn-sm">
                                <i class="fas fa-check-double me-1"></i> Released
                            </a>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
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
    <div class="card-header">
        <i class="fas fa-history me-2"></i> Transaction History
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Purpose</th>
                        <th>Certificates</th>
                        <th>Total</th>
                        <th>Control No</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $sql2 = "SELECT r.id, CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname, c.purpose, r.total_amount, r.control_number, r.status, r.created_at FROM requests r JOIN clients c ON r.client_id=c.id WHERE r.status IN('PAID','DECLINED','PREPARED','RELEASED') ORDER BY r.created_at DESC";
                $res2 = $conn->query($sql2);
                while($row = $res2->fetch_assoc()):
                    $cert_sql = "SELECT c.certificate_name FROM request_items ri JOIN certificates c ON ri.certificate_id = c.id WHERE ri.request_id=".$row['id'];
                    $cert_res = $conn->query($cert_sql);
                    $certs = [];
                    while($c = $cert_res->fetch_assoc()) $certs[] = $c['certificate_name'];
                ?>
                <tr>
                    <td>#<?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                    <td><?php echo htmlspecialchars(implode(", ", $certs)); ?></td>
                    <td>₱<?php echo number_format($row['total_amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                    <td>
                        <?php
                        $status = $row['status'];
                        $status_class = '';
                        if($status=='PENDING') $status_class = 'pending';
                        elseif($status=='PAID') $status_class = 'paid';
                        elseif($status=='DECLINED') $status_class = 'declined';
                        elseif($status=='PREPARED') $status_class = 'prepared';
                        elseif($status=='RELEASED') $status_class = 'released';
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status; ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
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
    <div class="card-header">
        <i class="fas fa-file-import me-2"></i> Import Excel Data
    </div>
    <div class="card-body">
        <?php
        if (isset($_GET['success'])) {
            echo "<div class='modern-alert modern-alert-success'><i class='fas fa-check-circle me-2'></i>Import successful!</div>";
        }
        if (isset($_GET['error'])) {
            echo "<div class='modern-alert modern-alert-danger'><i class='fas fa-exclamation-circle me-2'></i>" . htmlspecialchars($_GET['error']) . "</div>";
        }
        ?>

        <form action="import_logic.php" method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="modern-form-label">Choose Barangay:</label>
                <select name="barangay" class="modern-form-select" required>
                    <option value="">-- Select Barangay --</option>
                    <?php foreach($allowed_tables as $table): ?>
                    <option value="<?php echo $table; ?>"><?php echo ucwords(str_replace('_',' ',$table)); ?></option>
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

if (!empty($selected_barangay) && !empty($search_name)) {
    if (in_array($selected_barangay, $allowed_tables)) {
        $stmt = $conn->prepare("SELECT * FROM `$selected_barangay` WHERE declared_owner LIKE CONCAT('%', ?, '%')");
        $stmt->bind_param("s", $search_name);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) $results[] = $row;
        $stmt->close();
    }
}
?>

<div class="modern-card mb-4">
    <div class="card-header bg-info text-white">
        <i class="fas fa-search me-2"></i> Find Record
    </div>
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="tab" value="find">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="modern-form-label">Barangay</label>
                    <select name="barangay" class="modern-form-select" required>
                        <option value="">-- Select Barangay --</option>
                        <?php foreach($allowed_tables as $table): ?>
                        <option value="<?php echo $table; ?>" <?php if($selected_barangay==$table) echo "selected"; ?>>
                            <?php echo ucwords(str_replace('_',' ',$table)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="modern-form-label">Search Declared Owner</label>
                    <input type="text" name="search" class="modern-form-control"
                           value="<?php echo htmlspecialchars($search_name); ?>"
                           placeholder="Enter declared owner name" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="modern-btn modern-btn-primary w-100">
                        <i class="fas fa-search me-2"></i> Search
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if(!empty($results)): ?>
<div class="modern-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Declared Owner</th>
                        <th>Owner Address</th>
                        <th>Property Location</th>
                        <th>Title</th>
                        <th>Lot</th>
                        <th>ARP No.</th>
                        <th>PIN No.</th>
                        <th>Classification</th>
                        <th>Actual Use</th>
                        <th>Area</th>
                        <th>Market Value</th>
                        <th>Assessed Value</th>
                        <th>Taxability</th>
                        <th>Effectivity</th>
                        <th>Cancellation</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($results as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['declared_owner']); ?></td>
                    <td><?php echo htmlspecialchars($row['owner_address']); ?></td>
                    <td><?php echo htmlspecialchars($row['property_location']); ?></td>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo htmlspecialchars($row['lot']); ?></td>
                    <td><?php echo htmlspecialchars($row['ARP_No.']); ?></td>
                    <td><?php echo htmlspecialchars($row['PIN_No.']); ?></td>
                    <td><?php echo htmlspecialchars($row['classification']); ?></td>
                    <td><?php echo htmlspecialchars($row['actual_use']); ?></td>
                    <td><?php echo htmlspecialchars($row['area']); ?></td>
                    <td><?php echo htmlspecialchars($row['mv']); ?></td>
                    <td><?php echo htmlspecialchars($row['av']); ?></td>
                    <td><?php echo htmlspecialchars($row['taxability']); ?></td>
                    <td><?php echo htmlspecialchars($row['effectivity']); ?></td>
                    <td><?php echo htmlspecialchars($row['cancellation']); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif(!empty($search_name)): ?>
<div class="modern-alert modern-alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i> No records found.
</div>
<?php endif; ?>

<?php elseif($tab=='faas'): ?>

<?php
$selected_barangay = $_GET['barangay'] ?? '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;

if($selected_barangay && !in_array($selected_barangay,$allowed_tables)){
    die("Invalid barangay.");
}

/* ===============================
   COMMON FIELDS
================================== */
$fields = [
    'declared_owner','owner_address','property_location','title','lot',
    'ARP_No.','PIN_No.','classification','actual_use','area',
    'mv','av','taxability','effectivity','cancellation'
];

$labels = [
    'Declared Owner','Owner Address','Property Location','Title','Lot',
    'ARP No.','PIN No.','Classification','Actual Use','Area',
    'Market Value','Assessed Value','Taxability','Effectivity','Cancellation'
];

/* ===============================
   DELETE
================================== */
if($action=='delete' && $selected_barangay && $id){
    $stmt = $conn->prepare("DELETE FROM `$selected_barangay` WHERE `PIN_No.`=?");
    $stmt->bind_param("s",$id);
    $stmt->execute();
    $stmt->close();
    header("Location: home.php?tab=faas&barangay=$selected_barangay&page=$page");
    exit();
}

/* ===============================
   SAVE ADD / EDIT
================================== */
if(isset($_POST['save_faas'])){
    $brgy = $_POST['barangay'];
    if(!in_array($brgy,$allowed_tables)) die("Invalid barangay.");

    foreach($fields as $field){
        $data[$field] = $_POST[$field] ?? '';
    }

    if($_POST['mode']=='add'){

        $stmt = $conn->prepare("
        INSERT INTO `$brgy`
        (declared_owner,owner_address,property_location,title,lot,
        `ARP_No.`,`PIN_No.`,classification,actual_use,area,
        mv,av,taxability,effectivity,cancellation)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param("sssssssssssssss",
            $data['declared_owner'],$data['owner_address'],$data['property_location'],
            $data['title'],$data['lot'],$data['ARP_No.'],$data['PIN_No.'],
            $data['classification'],$data['actual_use'],$data['area'],
            $data['mv'],$data['av'],$data['taxability'],
            $data['effectivity'],$data['cancellation']
        );

        $stmt->execute();
        $stmt->close();

    } elseif($_POST['mode']=='edit'){

        $original_pin = $_POST['original_pin'];

        $stmt = $conn->prepare("
        UPDATE `$brgy` SET
        declared_owner=?,owner_address=?,property_location=?,title=?,lot=?,
        `ARP_No.`=?,`PIN_No.`=?,classification=?,actual_use=?,area=?,
        mv=?,av=?,taxability=?,effectivity=?,cancellation=?
        WHERE `PIN_No.`=?
        ");

        $stmt->bind_param("ssssssssssssssss",
            $data['declared_owner'],$data['owner_address'],$data['property_location'],
            $data['title'],$data['lot'],$data['ARP_No.'],$data['PIN_No.'],
            $data['classification'],$data['actual_use'],$data['area'],
            $data['mv'],$data['av'],$data['taxability'],
            $data['effectivity'],$data['cancellation'],$original_pin
        );

        $stmt->execute();
        $stmt->close();
    }

    header("Location: home.php?tab=faas&barangay=$brgy&page=$page");
    exit();
}
?>

<!-- BARANGAY SELECT -->
<div class="modern-card mb-4">
    <div class="card-header">
        <i class="fas fa-folder-tree me-2"></i> FAAS Management
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="tab" value="faas">
            <div class="col-md-6">
                <select name="barangay" class="modern-form-select" required>
                    <option value="">-- Select Barangay --</option>
                    <?php foreach($allowed_tables as $table): ?>
                    <option value="<?php echo $table;?>" <?php if($selected_barangay==$table) echo "selected";?>>
                        <?php echo ucwords(str_replace('_',' ',$table));?>
                    </option>
                    <?php endforeach;?>
                </select>
            </div>

            <div class="col-md-3">
                <button class="modern-btn modern-btn-primary w-100">
                    <i class="fas fa-sync-alt me-2"></i> Load
                </button>
            </div>

            <?php if($selected_barangay): ?>
            <div class="col-md-3">
                <a href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&action=add"
                   class="modern-btn modern-btn-success w-100">
                    <i class="fas fa-plus-circle me-2"></i> Add FAAS
                </a>
            </div>
            <?php endif;?>
        </form>
    </div>
</div>

<!-- ADD / EDIT FORM -->
<?php
if(($action=='add' || $action=='edit') && $selected_barangay):

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
    <div class="card-header" style="background: <?php echo $action=='add' ? '#10b981' : '#f59e0b'; ?>; color: white;">
        <i class="fas fa-<?php echo $action=='add' ? 'plus' : 'edit'; ?>-circle me-2"></i>
        <?php echo $action=='add' ? 'Add New FAAS' : 'Edit FAAS Record'; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="mode" value="<?php echo $action;?>">
            <input type="hidden" name="barangay" value="<?php echo $selected_barangay;?>">
            <input type="hidden" name="original_pin" value="<?php echo $edit_row['PIN_No.'] ?? '';?>">

            <div class="row g-3">
                <?php foreach($fields as $i=>$field): ?>
                <div class="col-md-6">
                    <input name="<?php echo $field;?>"
                           class="modern-form-control"
                           placeholder="<?php echo $labels[$i];?>"
                           value="<?php echo htmlspecialchars($edit_row[$field] ?? '');?>"
                           required>
                </div>
                <?php endforeach;?>
                <div class="col-md-12 mt-4">
                    <button type="submit" name="save_faas" class="modern-btn modern-btn-primary">
                        <i class="fas fa-save me-2"></i> <?php echo $action=='add' ? 'Save' : 'Update'; ?>
                    </button>
                    <a href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>" class="modern-btn modern-btn-secondary ms-2">
                        <i class="fas fa-times me-2"></i> Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- TABLE WITH PAGINATION -->
<?php
if($selected_barangay && $action!='add' && $action!='edit'):

$limit = 15;
$offset = ($page - 1) * $limit;

$count = $conn->query("SELECT COUNT(*) as total FROM `$selected_barangay`");
$total_records = $count->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$stmt = $conn->prepare("
SELECT * FROM `$selected_barangay`
ORDER BY declared_owner ASC
LIMIT ? OFFSET ?
");
$stmt->bind_param("ii",$limit,$offset);
$stmt->execute();
$res = $stmt->get_result();
?>

<div class="modern-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-2"></i> FAAS Records - <?php echo ucwords(str_replace('_',' ',$selected_barangay)); ?></span>
        <span class="badge bg-primary" style="font-size: 0.9rem; padding: 8px 15px;">
            <i class="fas fa-database me-1"></i> Total: <?php echo number_format($total_records); ?> records
        </span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <?php foreach($labels as $label): ?>
                        <th><?php echo $label;?></th>
                        <?php endforeach;?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row=$res->fetch_assoc()): ?>
                <tr>
                    <?php foreach($fields as $field): ?>
                    <td><?php echo htmlspecialchars($row[$field]);?></td>
                    <?php endforeach;?>
                    <td>
                        <a href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&action=edit&id=<?php echo urlencode($row['PIN_No.']);?>&page=<?php echo $page;?>"
                           class="modern-btn modern-btn-warning modern-btn-sm" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&action=delete&id=<?php echo urlencode($row['PIN_No.']);?>&page=<?php echo $page;?>"
                           class="modern-btn modern-btn-danger modern-btn-sm" title="Delete"
                           onclick="return confirm('Delete this record?');">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile;?>
                </tbody>
            </table>
        </div>

        <!-- IMPROVED PAGINATION WITH SCROLLABLE NUMBERS -->
        <?php if($total_pages > 1): ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4">
            <div class="mb-3 mb-md-0">
                <span class="text-muted">
                    Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                    (<?php echo number_format($total_records); ?> total records)
                </span>
            </div>
            
            <nav aria-label="Page navigation">
                <ul class="pagination mb-0 flex-wrap" style="gap: 3px;">
                    <!-- First Page Button -->
                    <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&page=1" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Previous Button -->
                    <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&page=<?php echo $page-1;?>" title="Previous">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Page Numbers with Smart Display -->
                    <?php
                    // Calculate page range to display
                    $start_page = max(1, min($page - 4, $total_pages - 9));
                    $end_page = min($total_pages, max($page + 5, 10));
                    
                    // Adjust if near the beginning
                    if($start_page <= 1) {
                        $start_page = 1;
                        $end_page = min($total_pages, 10);
                    }
                    
                    // Adjust if near the end
                    if($end_page >= $total_pages) {
                        $end_page = $total_pages;
                        $start_page = max(1, $total_pages - 9);
                    }
                    
                    // Show first page if not in range
                    if($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="home.php?tab=faas&barangay=' . $selected_barangay . '&page=1">1</a></li>';
                        if($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    // Display page numbers
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Show last page if not in range -->
                    <?php if($end_page < $total_pages): ?>
                        <?php if($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&page=<?php echo $total_pages; ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Next Button -->
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&page=<?php echo $page+1;?>" title="Next">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Last Page Button -->
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&page=<?php echo $total_pages; ?>" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <!-- Page Jump Dropdown (for quick navigation) -->
            <div class="ms-3 d-none d-lg-block">
                <select class="form-select form-select-sm" style="width: auto; border-radius: 10px;" onchange="window.location.href='home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&page=' + this.value">
                    <option value="">Jump to page</option>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($i == $page) ? 'selected' : ''; ?>>
                        Page <?php echo $i; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <!-- Mobile Quick Jump (visible only on mobile) -->
        <div class="d-block d-lg-none mt-3">
            <select class="form-select form-select-sm w-100" style="border-radius: 10px;" onchange="window.location.href='home.php?tab=faas&barangay=<?php echo $selected_barangay;?>&page=' + this.value">
                <option value="">Jump to page</option>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo ($i == $page) ? 'selected' : ''; ?>>
                    Page <?php echo $i; ?> of <?php echo $total_pages; ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<?php
$stmt->close();
endif;
?>

<?php elseif($tab=='certificates'):

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;

/* ===============================
   DELETE CERTIFICATE
================================== */
if($action=='delete' && $id){
    $stmt = $conn->prepare("DELETE FROM certificates WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $stmt->close();
    header("Location: home.php?tab=certificates&page=$page");
    exit();
}

/* ===============================
   ADD / EDIT CERTIFICATE
================================== */
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
        $stmt->execute();
        $stmt->close();
    } elseif($mode=='edit'){
        $stmt = $conn->prepare("UPDATE certificates SET certificate_name=?, description=?, price=?, status=? WHERE id=?");
        $stmt->bind_param("ssdsi",$name,$description,$price,$status,$original_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: home.php?tab=certificates&page=$page");
    exit();
}

/* ===============================
   FETCH RECORD FOR EDIT
================================== */
$edit_row = [];
if($action=='edit' && $id){
    $stmt = $conn->prepare("SELECT * FROM certificates WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $edit_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ===============================
   GET CERTIFICATES DATA WITH PAGINATION
================================== */
$limit = 15;
$offset = ($page - 1) * $limit;

$count = $conn->query("SELECT COUNT(*) as total FROM certificates");
$total_records = $count->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$stmt = $conn->prepare("
SELECT * FROM certificates
ORDER BY created_at DESC
LIMIT ? OFFSET ?
");
$stmt->bind_param("ii",$limit, $offset);
$stmt->execute();
$certificates = $stmt->get_result();
?>

<!-- ADD/EDIT CERTIFICATE FORM -->
<?php if($action=='add' || ($action=='edit' && $id)): ?>
<div class="modern-card mb-4">
    <div class="card-header" style="background: <?php echo $action=='add' ? '#10b981' : '#f59e0b'; ?>; color: white;">
        <i class="fas fa-<?php echo $action=='add' ? 'plus' : 'edit'; ?>-circle me-2"></i>
        <?php echo $action=='add' ? 'Add New Certificate' : 'Edit Certificate'; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="mode" value="<?php echo $action;?>">
            <input type="hidden" name="original_id" value="<?php echo $edit_row['id'] ?? '';?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="modern-form-label">Certificate Name</label>
                    <input type="text" name="certificate_name" class="modern-form-control" 
                           value="<?php echo htmlspecialchars($edit_row['certificate_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="modern-form-label">Price (₱)</label>
                    <input type="number" step="0.01" name="price" class="modern-form-control" 
                           value="<?php echo htmlspecialchars($edit_row['price'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="modern-form-label">Status</label>
                    <select name="status" class="modern-form-select" required>
                        <option value="active" <?php echo (isset($edit_row['status']) && $edit_row['status']=='active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($edit_row['status']) && $edit_row['status']=='inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="modern-form-label">Description</label>
                    <textarea name="description" class="modern-form-control" rows="3"><?php echo htmlspecialchars($edit_row['description'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-12 mt-4">
                    <button type="submit" name="save_certificate" class="modern-btn modern-btn-primary">
                        <i class="fas fa-save me-2"></i> <?php echo $action=='add' ? 'Save' : 'Update'; ?>
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

<!-- CERTIFICATES TABLE -->
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
                        <th>ID</th>
                        <th>Certificate Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($certificates->num_rows > 0): ?>
                    <?php while($row = $certificates->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['certificate_name']); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 50)) . (strlen($row['description'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td><strong>₱<?php echo number_format($row['price'],2); ?></strong></td>
                        <td>
                            <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="home.php?tab=certificates&action=edit&id=<?php echo $row['id'];?>&page=<?php echo $page;?>" 
                               class="modern-btn modern-btn-warning modern-btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="home.php?tab=certificates&action=delete&id=<?php echo $row['id'];?>&page=<?php echo $page;?>" 
                               class="modern-btn modern-btn-danger modern-btn-sm" title="Delete"
                               onclick="return confirm('Are you sure you want to delete this certificate?');">
                                <i class="fas fa-trash"></i>
                            </a>
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

        <!-- CERTIFICATES PAGINATION -->
        <?php if($total_pages > 1): ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4">
            <div class="mb-3 mb-md-0">
                <span class="text-muted">
                    Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                    (<?php echo number_format($total_records); ?> total certificates)
                </span>
            </div>
            
            <nav aria-label="Page navigation">
                <ul class="pagination mb-0 flex-wrap" style="gap: 3px;">
                    <!-- First Page Button -->
                    <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=certificates&page=1" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Previous Button -->
                    <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=certificates&page=<?php echo $page-1;?>" title="Previous">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Page Numbers with Smart Display -->
                    <?php
                    // Calculate page range to display
                    $start_page = max(1, min($page - 4, $total_pages - 9));
                    $end_page = min($total_pages, max($page + 5, 10));
                    
                    // Adjust if near the beginning
                    if($start_page <= 1) {
                        $start_page = 1;
                        $end_page = min($total_pages, 10);
                    }
                    
                    // Adjust if near the end
                    if($end_page >= $total_pages) {
                        $end_page = $total_pages;
                        $start_page = max(1, $total_pages - 9);
                    }
                    
                    // Show first page if not in range
                    if($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="home.php?tab=certificates&page=1">1</a></li>';
                        if($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    // Display page numbers
                    for($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="home.php?tab=certificates&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Show last page if not in range -->
                    <?php if($end_page < $total_pages): ?>
                        <?php if($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="home.php?tab=certificates&page=<?php echo $total_pages; ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Next Button -->
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=certificates&page=<?php echo $page+1;?>" title="Next">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Last Page Button -->
                    <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="home.php?tab=certificates&page=<?php echo $total_pages; ?>" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <!-- Page Jump Dropdown -->
            <div class="ms-3 d-none d-lg-block">
                <select class="form-select form-select-sm" style="width: auto; border-radius: 10px;" onchange="window.location.href='home.php?tab=certificates&page=' + this.value">
                    <option value="">Jump to page</option>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($i == $page) ? 'selected' : ''; ?>>
                        Page <?php echo $i; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <!-- Mobile Quick Jump -->
        <div class="d-block d-lg-none mt-3">
            <select class="form-select form-select-sm w-100" style="border-radius: 10px;" onchange="window.location.href='home.php?tab=certificates&page=' + this.value">
                <option value="">Jump to page</option>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo ($i == $page) ? 'selected' : ''; ?>>
                    Page <?php echo $i; ?> of <?php echo $total_pages; ?>
                </option>
                <?php endfor; ?>
            </select>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<?php
$stmt->close();
?>

<?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-hide sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    menuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    });

    // Auto-hide on mobile
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }

    // Handle resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        } else {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
        }
    });
});
</script>
</body>
</html>