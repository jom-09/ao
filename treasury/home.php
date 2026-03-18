<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// helper safe
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// view switch
$view = $_GET['view'] ?? 'dashboard';
$view = in_array($view, ['dashboard','requests','tax_history','transactions','installments','import_archive'], true) ? $view : 'dashboard';

/* ===============================
   DASHBOARD DATA (COUNTS ONLY)
================================= */
$dash_paid_services_count = 0;    // total service items from PAID requests (requested_services)
$dash_paid_tax_count      = 0;    // PAID in tax_requests
$dash_paid_cert_count     = 0;    // total certificate items from PAID requests (request_items)

$top_barangay_labels = [];
$top_barangay_values = [];

if ($view === 'dashboard') {

  // 1) Count PAID Services (sum of service items under PAID requests)
  try {
    $dash_paid_services_count = (int)$conn->query("
      SELECT COUNT(*) AS c
      FROM requested_services rs
      JOIN requests r ON r.id = rs.request_id
      WHERE r.status='PAID'
    ")->fetch_assoc()['c'];
  } catch (Throwable $e) { $dash_paid_services_count = 0; }

  // 2) Count PAID Tax
  try {
    $dash_paid_tax_count = (int)$conn->query("
      SELECT COUNT(*) AS c
      FROM tax_requests
      WHERE status='PAID'
    ")->fetch_assoc()['c'];
  } catch (Throwable $e) { $dash_paid_tax_count = 0; }

  // 3) Count Certificates Released (sum of certificate items under PAID requests)
  try {
    $dash_paid_cert_count = (int)$conn->query("
      SELECT COUNT(*) AS c
      FROM request_items ri
      JOIN requests r ON r.id = ri.request_id
      WHERE r.status='PAID'
    ")->fetch_assoc()['c'];
  } catch (Throwable $e) { $dash_paid_cert_count = 0; }

  // 4) Top Barangay that pays tax (COUNT per address/barangay)
  try {
    $topRes = $conn->query("
      SELECT
        TRIM(address) AS barangay,
        COUNT(*) AS pay_count
      FROM tax_requests
      WHERE status='PAID'
        AND address IS NOT NULL
        AND TRIM(address) <> ''
      GROUP BY TRIM(address)
      ORDER BY pay_count DESC
      LIMIT 10
    ");

    while($r = $topRes->fetch_assoc()){
      $top_barangay_labels[] = $r['barangay'];
      $top_barangay_values[] = (int)$r['pay_count'];
    }
  } catch (Throwable $e) {
    $top_barangay_labels = [];
    $top_barangay_values = [];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Treasury Dashboard</title>
  <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Modern Navigation -->
<nav class="modern-navbar">
  <div class="nav-brand">
    <i class="fas fa-landmark"></i>
    <span>Treasury Dashboard</span>
  </div>

  <div class="nav-items">
    <div class="d-flex align-items-center gap-2 me-3">

      <a href="home.php?view=dashboard"
         class="btn btn-sm <?= $view==='dashboard' ? 'btn-light' : 'btn-outline-light' ?>">
        <i class="fas fa-chart-column me-1"></i> Home
      </a>

      <a href="home.php?view=requests"
         class="btn btn-sm <?= $view==='requests' ? 'btn-light' : 'btn-outline-light' ?>">
        <i class="fas fa-inbox me-1"></i> Requests
      </a>

      <a href="home.php?view=tax_history"
         class="btn btn-sm <?= $view==='tax_history' ? 'btn-light' : 'btn-outline-light' ?>">
        <i class="fas fa-receipt me-1"></i> Tax Payment History
      </a>

      <a href="home.php?view=installments"
         class="btn btn-sm <?= $view==='installments' ? 'btn-light' : 'btn-outline-light' ?>">
        <i class="fas fa-calendar-check me-1"></i> Installments
      </a>

      <a href="home.php?view=transactions"
         class="btn btn-sm <?= $view==='transactions' ? 'btn-light' : 'btn-outline-light' ?>">
        <i class="fas fa-history me-1"></i> Transaction History
      </a>

      <a href="search_archive.php"
        class="btn btn-sm btn-outline-light">
        <i class="fas fa-search me-1"></i> Archive Search
      </a>

      <a href="import_archive.php"
        class="btn btn-sm btn-outline-light">
      <i class="fas fa-file-import me-1"></i> Import Archive
      </a>
    </div>

    <div class="user-badge">
      <i class="fas fa-user-circle"></i>
      <span><?php echo h($_SESSION['fullname'] ?? 'Treasury'); ?></span>
    </div>

    <a href="../logout.php" class="logout-btn">
      <i class="fas fa-sign-out-alt"></i>
      <span>Logout</span>
    </a>
  </div>
</nav>

<div class="main-container">

  <!-- Alerts -->
  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="fas fa-check-circle me-2"></i><?php echo h($_GET['success']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($_GET['error']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>


  <?php if ($view === 'dashboard'): ?>
    <!-- =========================
         DASHBOARD (COUNTS)
    ========================== -->

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="dashboard-card">
          <div class="card-header history-header">
            <div class="header-left">
              <i class="fas fa-handshake-angle"></i>
              <h5>Paid Services</h5>
            </div>
          </div>
          <div class="card-body">
            <div class="display-6 fw-bold"><?php echo (int)$dash_paid_services_count; ?></div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="dashboard-card">
          <div class="card-header history-header">
            <div class="header-left">
              <i class="fas fa-certificate"></i>
              <h5>Certificates Released</h5>
            </div>
          </div>
          <div class="card-body">
            <div class="display-6 fw-bold"><?php echo (int)$dash_paid_cert_count; ?></div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="dashboard-card">
          <div class="card-header history-header">
            <div class="header-left">
              <i class="fas fa-receipt"></i>
              <h5>Paid Tax</h5>
            </div>
          </div>
          <div class="card-body">
            <div class="display-6 fw-bold"><?php echo (int)$dash_paid_tax_count; ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="dashboard-card">
      <div class="card-header history-header">
        <div class="header-left">
          <i class="fas fa-chart-bar"></i>
          <h5>Top Barangay (Tax Paid Count)</h5>
        </div>
      </div>
      <div class="card-body">
        <div style="height: 360px;">
          <canvas id="topBarangayChart"></canvas>
        </div>
      </div>
    </div>

  <?php endif; ?>


  <?php if ($view === 'requests'): ?>
    <!-- =========================
         REQUESTS VIEW
    ========================== -->

    <!-- Person Search Section -->
    <div class="search-section">
      <div class="search-header">
        <i class="fas fa-search"></i>
        <h3>Search Person & Records</h3>
      </div>
      <div class="search-container">
        <select class="barangay-select" id="barangaySelect">
          <option value="">Select Barangay</option>
          <?php
          $allowed_tables = [
            'alicia','cabugao','dagupan','diodol','dumabel','dungo',
            'guinalbin','nagabgaban','palacian','pinaripad_norte',
            'pinaripad_sur','progreso','ramos','rangayan',
            'san_antonio','san_benigno','san_francisco','san_leonardo',
            'san_manuel','san_ramon','victoria',
            'villa_pagaduan','villa_santiago','villa_ventura','ligaya'
          ];
          foreach($allowed_tables as $table) {
            echo "<option value='".$table."'>".ucfirst(str_replace('_', ' ', $table))."</option>";
          }
          ?>
        </select>

        <div class="search-input-wrapper">
          <i class="fas fa-user search-icon"></i>
          <input type="text" class="person-search" id="personSearch" placeholder="Enter owner name...">
        </div>

        <button class="search-btn" id="searchPersonBtn">
          <i class="fas fa-search"></i>
          <span>Search Records</span>
        </button>
      </div>
    </div>

    <!-- Person Records Table -->
    <div class="records-section" id="recordsSection" style="display:none;">
      <div class="section-header">
        <h4><i class="fas fa-database"></i> Property Records</h4>
        <span class="record-count" id="recordCount"></span>
      </div>
      <div class="table-responsive">
        <table class="modern-table" id="personRecordsTable">
          <thead>
            <tr>
              <th>Owner</th>
              <th>Address</th>
              <th>ARP No.</th>
              <th class="text-end">Assessed Value</th>
            </tr>
          </thead>
          <tbody id="recordsTableBody"></tbody>
        </table>
      </div>
    </div>

    <!-- Pending Requests Card (CERT/SVC ONLY) -->
    <div class="dashboard-card">
      <div class="card-header pending-header">
        <div class="header-left">
          <i class="fas fa-clock"></i>
          <h5>Pending Requests</h5>
        </div>
        <?php
          $pendingCertSvcCount = 0;
          try {
            $pendingCertSvcCount = (int)$conn->query("
              SELECT COUNT(*) AS c
              FROM requests r
              JOIN clients c ON r.client_id = c.id
              WHERE r.status='PENDING'
                AND c.purpose <> 'Tax Clearance'
            ")->fetch_assoc()['c'];
          } catch (Throwable $e) { $pendingCertSvcCount = 0; }
        ?>
        <span class="badge pending-badge" id="pendingCount"><?php echo (int)$pendingCertSvcCount; ?></span>
      </div>

      <div class="card-body">
        <table class="modern-table" id="pendingTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Client</th>
              <th>Address</th>
              <th>Purpose</th>
              <th>ARP No.</th>
              <th>Area</th>
              <th>Certificates / Services</th>
              <th>Total</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $sql = "
            SELECT
              r.id,
              CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname,
              c.address,
              c.purpose,
              r.total_amount,
              r.created_at,
              rld.arp_no,
              rld.area,
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
            LEFT JOIN request_land_details rld ON rld.request_id = r.id
            WHERE r.status='PENDING'
              AND c.purpose <> 'Tax Clearance'
            ORDER BY r.created_at DESC
          ";
          $result = $conn->query($sql);

          while($row=$result->fetch_assoc()):
            $items = "-";
            if (!empty($row['certificate_list'])) {
              $items = $row['certificate_list'];
            } elseif (!empty($row['service_list'])) {
              $items = $row['service_list'];
            }
          ?>
            <tr>
              <td><span class="id-badge">#<?php echo (int)$row['id']; ?></span></td>
              <td><span class="client-name"><?php echo h($row['fullname']); ?></span></td>
              <td><?php echo h($row['address'] ?? ''); ?></td>
              <td><?php echo h($row['purpose']); ?></td>
              <td><?php echo h($row['arp_no'] ?? '-'); ?></td>
              <td><?php echo h($row['area'] ?? '-'); ?></td>
              <td><span class="certs-list"><?php echo h($items); ?></span></td>
              <td><span class="amount">₱<?php echo number_format((float)$row['total_amount'],2); ?></span></td>
              <td><span class="date"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></span></td>
              <td class="actions">
                <a href="process_request.php?id=<?php echo (int)$row['id'];?>" class="action-btn accept" title="Accept / Mark Paid">
                  <i class="fas fa-check"></i>
                </a>
                <a href="process_request.php?decline=<?php echo (int)$row['id'];?>" class="action-btn decline" title="Decline">
                  <i class="fas fa-times"></i>
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tax Clearance Requests Card (FROM CLIENTS TABLE ONLY) -->
    <div class="dashboard-card">
      <div class="card-header pending-header">
        <div class="header-left">
          <i class="fas fa-file-alt"></i>
          <h5>Tax Clearance Requests</h5>
        </div>
        <?php
          $taxClearanceCount = 0;
          try {
            $taxClearanceCount = (int)$conn->query("
              SELECT COUNT(*) AS c
              FROM clients c
              WHERE c.purpose='Tax Clearance'
              AND NOT EXISTS (
                SELECT 1
                FROM requests r
                WHERE r.client_id = c.id
                AND r.status = 'PAID'
              )
            ")->fetch_assoc()['c'];
          } catch (Throwable $e) { $taxClearanceCount = 0; }
        ?>
        <span class="badge pending-badge" id="taxClearanceCount"><?php echo (int)$taxClearanceCount; ?></span>
      </div>

      <div class="card-body">
        <table class="modern-table" id="taxClearanceTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Requester</th>
              <th>Address</th>
              <th>Contact</th>
              <th>Purpose</th>
              <th>Requirement</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php
          try {
            $sqlTC = "
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
              WHERE c.purpose='Tax Clearance'
                AND NOT EXISTS (
                  SELECT 1
                  FROM requests r
                  WHERE r.client_id = c.id
                    AND r.status = 'PAID'
                )
              ORDER BY c.created_at DESC
            ";
            $resultTC = $conn->query($sqlTC);

            while($rowTC = $resultTC->fetch_assoc()):
              $fullname = trim(
                ($rowTC['firstname'] ?? '') . ' ' .
                ($rowTC['middlename'] ?? '') . ' ' .
                ($rowTC['lastname'] ?? '')
              );
          ?>
            <tr>
              <td><span class="id-badge">#<?php echo (int)$rowTC['id']; ?></span></td>
              <td><span class="client-name"><?php echo h($fullname); ?></span></td>
              <td><?php echo h($rowTC['address'] ?? ''); ?></td>
              <td><?php echo h($rowTC['cp_no'] ?? ''); ?></td>
              <td><?php echo h($rowTC['purpose'] ?? ''); ?></td>
              <td><span class="certs-list">Prepare latest receipt</span></td>
              <td><span class="date"><?php echo date('M d, Y', strtotime($rowTC['created_at'])); ?></span></td>
              <td class="actions">
                <button
                  type="button"
                  class="action-btn accept btnDoneTaxClearance"
                  title="Mark as Done"
                  data-id="<?php echo (int)$rowTC['id']; ?>"
                >
                  <i class="fas fa-check"></i>
                </button>
              </td>
            </tr>
          <?php
            endwhile;
          } catch (Throwable $e) { /* keep empty */ }
          ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tax Payment Requests Card (PENDING) -->
    <div class="dashboard-card">
      <div class="card-header pending-header">
        <div class="header-left">
          <i class="fas fa-receipt"></i>
          <h5>Tax Payment Requests</h5>
        </div>
        <?php
          $taxPendingCount = 0;
          try {
            $taxPendingCount = (int)$conn->query("SELECT COUNT(*) c FROM tax_requests WHERE status='PENDING'")->fetch_assoc()['c'];
          } catch (Throwable $e) { $taxPendingCount = 0; }
        ?>
        <span class="badge pending-badge" id="taxPendingCount"><?php echo (int)$taxPendingCount; ?></span>
      </div>

      <div class="card-body">
        <table class="modern-table" id="taxPendingTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Declared Owner</th>
              <th>ARP No.</th>
              <th class="text-end">Assessed Value</th>
              <th class="text-end">Base Tax (2%)</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php
          try {
            $taxSql = "SELECT id, declared_owner, arp_no, assessed_value, created_at, status
                       FROM tax_requests
                       WHERE status='PENDING'
                       ORDER BY created_at DESC";
            $taxRes = $conn->query($taxSql);

            while($t = $taxRes->fetch_assoc()):
              $avRaw  = (string)($t['assessed_value'] ?? '0');
              $av     = (float)str_replace([',',' '], '', $avRaw);
              $base   = $av * 0.02;
              $status = (string)($t['status'] ?? 'PENDING');
          ?>
            <tr>
              <td><span class="id-badge">#<?php echo (int)$t['id']; ?></span></td>
              <td><?php echo h($t['declared_owner']); ?></td>
              <td><?php echo h($t['arp_no']); ?></td>
              <td class="text-end">₱<?php echo number_format($av, 2); ?></td>
              <td class="text-end fw-semibold">₱<?php echo number_format($base, 2); ?></td>
              <td><span class="status-badge pending"><i class="fas fa-clock"></i> <?php echo h($status); ?></span></td>
              <td><span class="date"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></span></td>
              <td class="actions">
                <button
                  type="button"
                  class="action-btn accept btnProcessTax"
                  title="Process Payment"
                  data-id="<?php echo (int)$t['id']; ?>"
                  data-owner="<?php echo h($t['declared_owner']); ?>"
                  data-arp="<?php echo h($t['arp_no']); ?>"
                  data-av="<?php echo h(number_format($av, 2, '.', '')); ?>"
                >
                  <i class="fas fa-cash-register"></i>
                </button>
              </td>
            </tr>
          <?php
            endwhile;
          } catch (Throwable $e) { /* keep empty */ }
          ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?>


  <?php if ($view === 'tax_history'): ?>
    <!-- TAX PAYMENT HISTORY VIEW -->
    <div class="dashboard-card">
      <div class="card-header history-header">
        <div class="header-left">
          <i class="fas fa-receipt"></i>
          <h5>Tax Payment History</h5>
        </div>
      </div>

      <div class="card-body">
        <table class="modern-table" id="taxHistoryTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Declared Owner</th>
              <th>ARP No.</th>
              <th class="text-end">Assessed Value</th>
              <th class="text-end">Base Tax (2%)</th>
              <th>Control No.</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
          <?php
          try {
            $histSql = "SELECT id, declared_owner, arp_no, assessed_value, control_number, status, paid_at, created_at
                        FROM tax_requests
                        WHERE status IN('PAID','DECLINED','INSTALLMENT')
                        ORDER BY COALESCE(paid_at, created_at) DESC";
            $histRes = $conn->query($histSql);

            while($t = $histRes->fetch_assoc()):
              $avRaw = (string)($t['assessed_value'] ?? '0');
              $av    = (float)str_replace([',',' '], '', $avRaw);
              $base  = $av * 0.02;

              $status   = (string)($t['status'] ?? '');
              $dateShow = $t['paid_at'] ? $t['paid_at'] : $t['created_at'];
          ?>
            <tr>
              <td><span class="id-badge">#<?php echo (int)$t['id']; ?></span></td>
              <td><?php echo h($t['declared_owner']); ?></td>
              <td><?php echo h($t['arp_no']); ?></td>
              <td class="text-end">₱<?php echo number_format($av, 2); ?></td>
              <td class="text-end fw-semibold">₱<?php echo number_format($base, 2); ?></td>
              <td><?php echo h($t['control_number'] ?? ''); ?></td>
              <td>
                <?php if ($status === 'PAID'): ?>
                  <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                <?php elseif ($status === 'INSTALLMENT'): ?>
                  <span class="status-badge pending"><i class="fas fa-calendar-alt"></i> INSTALLMENT</span>
                <?php else: ?>
                  <span class="status-badge declined"><i class="fas fa-times-circle"></i> DECLINED</span>
                <?php endif; ?>
              </td>
              <td><span class="date"><?php echo date('M d, Y', strtotime($dateShow)); ?></span></td>
            </tr>
          <?php
            endwhile;
          } catch (Throwable $e) { /* keep empty */ }
          ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>


  <?php if ($view === 'installments'): ?>
    <!-- INSTALLMENTS VIEW -->
    <div class="dashboard-card">
      <div class="card-header history-header">
        <div class="header-left">
          <i class="fas fa-calendar-check"></i>
          <h5>Installment Payments</h5>
        </div>
      </div>

      <div class="card-body">
        <table class="modern-table" id="installmentsTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Declared Owner</th>
              <th>ARP No.</th>
              <th class="text-end">Assessed Value</th>
              <th class="text-end">Base Tax (2%)</th>
              <th>Control No.</th>
              <th>Date Started</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php
          try {
            $instSql = "SELECT id, declared_owner, arp_no, assessed_value, control_number,
                               COALESCE(paid_at, created_at) AS started_at
                        FROM tax_requests
                        WHERE status='INSTALLMENT'
                        ORDER BY COALESCE(paid_at, created_at) DESC";
            $instRes = $conn->query($instSql);

            while($t = $instRes->fetch_assoc()):
              $avRaw = (string)($t['assessed_value'] ?? '0');
              $av    = (float)str_replace([',',' '], '', $avRaw);
              $base  = $av * 0.02;
              $startedAt = $t['started_at'] ?? null;
          ?>
            <tr>
              <td><span class="id-badge">#<?php echo (int)$t['id']; ?></span></td>
              <td><?php echo h($t['declared_owner']); ?></td>
              <td><?php echo h($t['arp_no']); ?></td>
              <td class="text-end">₱<?php echo number_format($av, 2); ?></td>
              <td class="text-end fw-semibold">₱<?php echo number_format($base, 2); ?></td>
              <td><?php echo h($t['control_number'] ?? ''); ?></td>
              <td><span class="date"><?php echo $startedAt ? date('M d, Y', strtotime($startedAt)) : ''; ?></span></td>
              <td class="actions">
                <button
                  type="button"
                  class="action-btn accept btnViewInstallment"
                  title="View Installment Schedule"
                  data-id="<?php echo (int)$t['id']; ?>"
                  data-owner="<?php echo h($t['declared_owner']); ?>"
                  data-arp="<?php echo h($t['arp_no']); ?>"
                  data-control="<?php echo h($t['control_number'] ?? ''); ?>"
                >
                  <i class="fas fa-list-check"></i>
                </button>
              </td>
            </tr>
          <?php
            endwhile;
          } catch (Throwable $e) { /* keep empty */ }
          ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>


  <?php if ($view === 'transactions'): ?>
    <!-- TRANSACTION HISTORY VIEW -->
    <div class="dashboard-card">
      <div class="card-header history-header">
        <div class="header-left">
          <i class="fas fa-history"></i>
          <h5>Transaction History</h5>
        </div>
      </div>

      <div class="card-body">
        <table class="modern-table" id="historyTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Client</th>
              <th>Address</th>
              <th>Purpose</th>
              <th>ARP No.</th>
              <th>Area</th>
              <th>Certificates / Services</th>
              <th>Total</th>
              <th>Control No</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $sql2 = "
            SELECT
              r.id,
              CONCAT(c.firstname,' ',c.middlename,' ',c.lastname) AS fullname,
              c.address,
              c.purpose,
              r.total_amount,
              r.control_number,
              r.status,
              r.paid_at,
              r.created_at,
              rld.arp_no,
              rld.area,
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
            LEFT JOIN request_land_details rld ON rld.request_id = r.id
            WHERE r.status IN('PAID','DECLINED')
            ORDER BY r.created_at DESC
          ";
          $result2 = $conn->query($sql2);

          while($row=$result2->fetch_assoc()):
            $items2 = "-";
            if (!empty($row['certificate_list'])) {
              $items2 = $row['certificate_list'];
            } elseif (!empty($row['service_list'])) {
              $items2 = $row['service_list'];
            } elseif (($row['purpose'] ?? '') === 'Tax Clearance') {
              $items2 = 'Prepare latest receipt';
            }
          ?>
            <tr>
              <td><span class="id-badge">#<?php echo (int)$row['id']; ?></span></td>
              <td><?php echo h($row['fullname']); ?></td>
              <td><?php echo h($row['address'] ?? ''); ?></td>
              <td><?php echo h($row['purpose']); ?></td>
              <td><?php echo h($row['arp_no'] ?? '-'); ?></td>
              <td><?php echo h($row['area'] ?? '-'); ?></td>
              <td><?php echo h($items2); ?></td>
              <td>
                <span class="amount">
                  <?php echo ((float)$row['total_amount'] > 0) ? '₱'.number_format((float)$row['total_amount'],2) : '-'; ?>
                </span>
              </td>
              <td><span class="control-no"><?php echo h($row['control_number']); ?></span></td>
              <td>
                <?php if($row['status']=='PAID'): ?>
                  <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                <?php else: ?>
                  <span class="status-badge declined"><i class="fas fa-times-circle"></i> DECLINED</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="date">
                  <?php
                    $dateToShow = $row['paid_at'] ? $row['paid_at'] : $row['created_at'];
                    echo date('M d, Y', strtotime($dateToShow));
                  ?>
                </span>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div><!-- /.main-container -->


<!-- ✅ TAX PAYMENT MODAL -->
<div class="modal fade" id="taxPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="fas fa-file-invoice-dollar me-2"></i>Process Tax Payment
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form action="process_tax_payment.php" method="POST" id="taxPayForm">
        <div class="modal-body">

          <input type="hidden" name="tax_request_id" id="tx_request_id">
          <input type="hidden" name="computed_total_due" id="tx_total_due_input" value="0">
          <input type="hidden" name="computed_term_amount" id="tx_term_amount_input" value="0">

          <div class="row g-3 mb-3">
            <div class="col-md-7">
              <div class="small text-muted">Declared Owner</div>
              <div class="fw-bold fs-5" id="tx_owner"></div>
            </div>
            <div class="col-md-5">
              <div class="small text-muted">ARP No.</div>
              <div class="fw-semibold" id="tx_arp"></div>
            </div>
          </div>

          <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
              <div class="d-flex justify-content-between mb-2">
                <span>Assessed Value (AV)</span>
                <strong id="tx_av"></strong>
              </div>
              <hr class="my-2">
              <div class="d-flex justify-content-between mb-2">
                <span>AV × Basic Tax (1%)</span>
                <strong id="tx_basic"></strong>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span>AV × SEF (1%)</span>
                <strong id="tx_sef"></strong>
              </div>
              <hr class="my-2">
              <div class="d-flex justify-content-between mb-2">
                <span class="fw-semibold">Base Total (AV × 2%)</span>
                <strong id="tx_base"></strong>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Discount</label>
              <select class="form-select" name="discount_rate" id="tx_discount">
                <option value="0">No Discount</option>
                <option value="0.10">10% Discount</option>
                <option value="0.20">20% Discount</option>
              </select>
              <div class="small text-muted mt-1">Applied to Base Total.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold">Penalty Months</label>
              <input type="number" class="form-control" name="penalty_months" id="tx_penalty_months" value="0" min="0" max="36">
              <div class="small text-muted mt-1">2% per month (max 36).</div>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold">OR. no</label>
              <input type="text" class="form-control" name="control_number" id="tx_control" required>
            </div>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Payment Option</label>
              <select class="form-select" name="payment_option" id="tx_payment_option">
                <option value="ANNUALLY">Annually (Full Payment)</option>
                <option value="QUARTERLY">Quarterly (Installment)</option>
              </select>
              <div class="small text-muted mt-1">Quarterly will split Total Due into 4 equal parts.</div>
            </div>

            <div class="col-md-6" id="tx_term_wrap" style="display:none;">
              <label class="form-label fw-semibold">Quarterly Amount</label>
              <input type="text" class="form-control" id="tx_term_amount" readonly>
              <div class="small text-muted mt-1">This is per quarter (x4).</div>
            </div>
          </div>

          <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
              <div class="d-flex justify-content-between mb-2">
                <span>Discount Amount</span>
                <strong id="tx_discount_amt"></strong>
              </div>

              <div class="d-flex justify-content-between mb-2">
                <span>Penalty Amount</span>
                <strong id="tx_penalty_amt"></strong>
              </div>

              <div class="d-flex justify-content-between mb-2" id="tx_quarterly_total_wrap" style="display:none;">
                <span>Quarterly Total (x4)</span>
                <strong id="tx_quarterly_total"></strong>
              </div>

              <hr class="my-2">

              <div class="d-flex justify-content-between fs-5">
                <span class="fw-bold">TOTAL DUE</span>
                <span class="fw-bold" id="tx_total_due"></span>
              </div>
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" id="tx_submit_btn">Mark as Paid</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ✅ INSTALLMENT SCHEDULE MODAL -->
<div class="modal fade" id="installmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="fas fa-calendar-alt me-2"></i>Installment Schedule
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="inst_tax_request_id" value="0">

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <div class="small text-muted">Declared Owner</div>
            <div class="fw-bold" id="inst_owner"></div>
          </div>
          <div class="col-md-3">
            <div class="small text-muted">ARP No.</div>
            <div class="fw-semibold" id="inst_arp"></div>
          </div>
          <div class="col-md-3">
            <div class="small text-muted">Control No.</div>
            <div class="fw-semibold" id="inst_control"></div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:140px;">Quarter</th>
                <th>Coverage</th>
                <th style="width:170px;">Due Date</th>
                <th style="width:140px;">Status</th>
                <th style="width:160px;" class="text-end">Quarter Amount</th>
                <th style="width:140px;">Action</th>
              </tr>
            </thead>
            <tbody id="instScheduleBody">
              <tr><td colspan="6" class="text-center text-muted">No data</td></tr>
            </tbody>
          </table>
        </div>

        <div class="small text-muted">
          * Quarterly amount is computed from total due ÷ 4.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ✅ QUARTER PAYMENT MODAL -->
<div class="modal fade" id="quarterPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">

      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="fas fa-coins me-2"></i>Quarter Payment
        </h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="pay_tax_id" value="0">
        <input type="hidden" id="pay_quarter" value="0">

        <div class="mb-3">
          <label class="form-label fw-semibold">Quarter Amount</label>
          <input type="text" id="quarter_amount" class="form-control" readonly>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Discount</label>
          <select id="discount_rate" class="form-select">
            <option value="0">None</option>
            <option value="0.10">10%</option>
            <option value="0.20">20%</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Penalty (Months)</label>
          <input type="number" id="penalty_months" class="form-control" min="0" max="36" value="0">
          <div class="small text-muted mt-1">2% per month.</div>
        </div>

        <hr>

        <div class="d-flex justify-content-between mb-1">
          <span>Discount Amount</span>
          <strong id="qp_discount_amt">₱0.00</strong>
        </div>
        <div class="d-flex justify-content-between mb-1">
          <span>Penalty Amount</span>
          <strong id="qp_penalty_amt">₱0.00</strong>
        </div>
        <div class="d-flex justify-content-between fs-5 mt-2">
          <span class="fw-bold">TOTAL PAYABLE</span>
          <span class="fw-bold" id="total_payable">₱0.00</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="btnSaveQuarter">
          <i class="fas fa-save me-1"></i>Save Payment
        </button>
      </div>

    </div>
  </div>
</div>


<!-- Scripts -->
<script src="../assets/js/jquery-3.7.1.min.js"></script>
<script src="../assets/js/datatables.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Notification Sound -->
<audio id="notifSound" preload="auto">
  <source src="../assets/sounds/notif.mp3" type="audio/mpeg">
</audio>

<script>
$(document).ready(function() {

  /* ==========================
     CONFIG: NOTIF MODE
  ========================== */
  const NOTIF_MODE = "TAX"; // pwede mo palitan ng "PENDING"

  /* ==========================
     HELPERS
  ========================== */
  function peso(n){
    return "₱" + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function playNotif(){
    const a = document.getElementById('notifSound');
    if(!a) return;
    a.currentTime = 0;
    a.play().catch(()=>{});
  }

  $(document).one('click keydown', function(){ playNotif(); });

  /* ==========================
     DATATABLES
  ========================== */
  let pendingDT = null;
  let taxClearanceDT = null;
  let taxDT = null;

  if ($('#pendingTable').length) {
    pendingDT = $('#pendingTable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
      language: { search: "<i class='fas fa-search'></i>", searchPlaceholder: "Search pending requests..." },
      dom: '<"table-toolbar"f>rtip',
      initComplete: function() { $('#pendingTable_filter input').attr('placeholder', 'Search pending...'); }
    });
  }

  if ($('#taxClearanceTable').length) {
    taxClearanceDT = $('#taxClearanceTable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
      language: {
        search: "<i class='fas fa-search'></i>",
        searchPlaceholder: "Search tax clearance requests...",
        emptyTable: "No tax clearance requests."
      },
      dom: '<"table-toolbar"f>rtip',
      initComplete: function() {
        $('#taxClearanceTable_filter input').attr('placeholder', 'Search tax clearance requests...');
      }
    });
  }

  if ($('#taxPendingTable').length) {
    taxDT = $('#taxPendingTable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
      language: { search: "<i class='fas fa-search'></i>", searchPlaceholder: "Search tax requests...", emptyTable: "No pending tax requests." },
      dom: '<"table-toolbar"f>rtip',
      initComplete: function() { $('#taxPendingTable_filter input').attr('placeholder', 'Search tax requests...'); }
    });
  }

  if ($('#taxHistoryTable').length) {
    $('#taxHistoryTable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
      language: { search: "<i class='fas fa-search'></i>", searchPlaceholder: "Search tax history...", emptyTable: "No tax payments yet." },
      dom: '<"table-toolbar"f>rtip',
      initComplete: function() { $('#taxHistoryTable_filter input').attr('placeholder', 'Search tax history...'); }
    });
  }

  if ($('#installmentsTable').length) {
    $('#installmentsTable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
      language: { search: "<i class='fas fa-search'></i>", searchPlaceholder: "Search installments...", emptyTable: "No installment records yet." },
      dom: '<"table-toolbar"f>rtip',
      initComplete: function() { $('#installmentsTable_filter input').attr('placeholder', 'Search installments...'); }
    });
  }

  if ($('#historyTable').length) {
    $('#historyTable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
      language: { search: "<i class='fas fa-search'></i>", searchPlaceholder: "Search transactions..." },
      dom: '<"table-toolbar"f>rtip',
      initComplete: function() { $('#historyTable_filter input').attr('placeholder', 'Search history...'); }
    });
  }

  /* ==========================
     DASHBOARD CHART (COUNT)
  ========================== */
  const chartCanvas = document.getElementById('topBarangayChart');
  if (chartCanvas) {
    const labels = <?php echo json_encode($top_barangay_labels, JSON_UNESCAPED_UNICODE); ?>;
    const values = <?php echo json_encode($top_barangay_values, JSON_UNESCAPED_UNICODE); ?>;

    new Chart(chartCanvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'No. of PAID Tax Payments',
          data: values,
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  /* ==========================
     PERSON SEARCH
  ========================== */
  $('#searchPersonBtn').on('click', function() {
    const barangay = $('#barangaySelect').val();
    const ownerName = $('#personSearch').val().trim();

    if(!barangay){ alert('Please select a barangay'); return; }
    if(!ownerName){ alert('Please enter owner name'); return; }

    $('#searchPersonBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Searching...');

    $.ajax({
      url: 'search_person_records.php',
      method: 'POST',
      data: { barangay: barangay, owner_name: ownerName },
      dataType: 'json',
      success: function(response) { displayPersonRecords(response); },
      error: function() { alert('Error searching records. Please try again.'); },
      complete: function() {
        $('#searchPersonBtn').prop('disabled', false).html('<i class="fas fa-search"></i> Search Records');
      }
    });
  });

  function displayPersonRecords(records) {
    const tbody = $('#recordsTableBody');
    tbody.empty();
    $('#recordsSection').show();

    if (records && records.length > 0 && !records.error) {
      $('#recordCount').text(records.length + ' record(s) found');
      $.each(records, function(index, record) {
        const av = Number(record.assessed_value || 0);
        tbody.append(
          '<tr>' +
            '<td>' + (record.owner || '') + '</td>' +
            '<td>' + (record.address || '') + '</td>' +
            '<td>' + (record.arp_no || '') + '</td>' +
            '<td class="text-end">₱' + av.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) + '</td>' +
          '</tr>'
        );
      });
    } else if (records && records.error) {
      $('#recordCount').text('Error');
      tbody.append('<tr><td colspan="4" class="no-records">' + records.error + '</td></tr>');
    } else {
      $('#recordCount').text('No records found');
      tbody.append('<tr><td colspan="4" class="no-records">No property records found for this owner</td></tr>');
    }
  }

  $('#personSearch').on('keyup', function(e){
    if(e.key === 'Enter') $('#searchPersonBtn').click();
  });

  /* ==========================
     TAX MODAL COMPUTE + OPEN
  ========================== */
  let currentAV = 0;

  $(document).on('click', '.btnProcessTax', function(e) {
    e.preventDefault();
    e.stopPropagation();

    const id = Number($(this).data('id')) || 0;
    const owner = $(this).data('owner') || '';
    const arp = $(this).data('arp') || '';
    const av = Number($(this).data('av')) || 0;

    currentAV = av;

    $('#tx_request_id').val(id);
    $('#tx_owner').text(owner);
    $('#tx_arp').text(arp);

    $('#tx_discount').val('0');
    $('#tx_penalty_months').val('0');
    $('#tx_control').val('');

    $('#tx_payment_option').val('ANNUALLY');
    $('#tx_term_wrap').hide();
    $('#tx_term_amount').val('');
    $('#tx_quarterly_total_wrap').hide();
    $('#tx_quarterly_total').text('');
    $('#tx_submit_btn').text('Mark as Paid');

    computeTax();

    new bootstrap.Modal(document.getElementById('taxPaymentModal')).show();
  });

  $('#tx_discount, #tx_penalty_months, #tx_payment_option').on('change keyup', computeTax);

  function computeTax() {
    if (!$('#tx_request_id').length) return;

    const basic = currentAV * 0.01;
    const sef   = currentAV * 0.01;
    const base  = basic + sef;

    const discRate = Number($('#tx_discount').val()) || 0;

    let months = parseInt($('#tx_penalty_months').val(), 10);
    if (isNaN(months)) months = 0;
    if (months < 0) months = 0;
    if (months > 36) months = 36;
    $('#tx_penalty_months').val(months);

    const discountAmt = base * discRate;
    const afterDisc = base - discountAmt;

    const penaltyAmt = afterDisc * (0.02 * months);
    const totalDue = afterDisc + penaltyAmt;

    const option = ($('#tx_payment_option').val() || 'ANNUALLY').toUpperCase();
    let termAmount = totalDue;

    if (option === 'QUARTERLY') {
      termAmount = totalDue / 4;
      $('#tx_term_wrap').show();
      $('#tx_term_amount').val(peso(termAmount));
      $('#tx_submit_btn').text('Save as Installment');

      $('#tx_quarterly_total_wrap').show();
      $('#tx_quarterly_total').text(peso(termAmount * 4));
    } else {
      $('#tx_term_wrap').hide();
      $('#tx_term_amount').val('');
      $('#tx_submit_btn').text('Mark as Paid');

      $('#tx_quarterly_total_wrap').hide();
      $('#tx_quarterly_total').text('');
    }

    $('#tx_av').text(peso(currentAV));
    $('#tx_basic').text(peso(basic));
    $('#tx_sef').text(peso(sef));
    $('#tx_base').text(peso(base));
    $('#tx_discount_amt').text(peso(discountAmt));
    $('#tx_penalty_amt').text(peso(penaltyAmt));
    $('#tx_total_due').text(peso(totalDue));

    $('#tx_total_due_input').val(totalDue.toFixed(2));
    $('#tx_term_amount_input').val(termAmount.toFixed(2));
  }

  /* ==========================
     INSTALLMENT MODAL LOGIC
  ========================== */
  function renderScheduleRows(schedule, taxId){
    const body = $('#instScheduleBody');
    body.empty();

    if (!Array.isArray(schedule) || schedule.length === 0){
      body.append('<tr><td colspan="6" class="text-center text-muted">No data</td></tr>');
      return;
    }

    schedule.forEach(function(row){
      const q = Number(row.quarter || 0);
      const coverage = $('<div>').text(row.coverage || '').html();
      const due = $('<div>').text(row.due_date || '').html();
      const status = (row.status || '').toString().toUpperCase();
      const amt = Number(row.amount || 0);

      let statusBadge = `<span class="badge bg-secondary">${status || 'N/A'}</span>`;
      if (status === 'PAID') statusBadge = `<span class="badge bg-success">PAID</span>`;
      if (status === 'UNPAID') statusBadge = `<span class="badge bg-warning text-dark">UNPAID</span>`;
      if (status === 'OVERDUE') statusBadge = `<span class="badge bg-danger">OVERDUE</span>`;

      let actionHtml = `<span class="text-muted">—</span>`;
      if (row.can_pay) {
        actionHtml = `
          <button type="button"
            class="btn btn-sm btn-primary btnPayQuarter"
            data-tax-id="${taxId}"
            data-quarter="${q}"
            data-amount="${amt.toFixed(2)}">
            Pay
          </button>
        `;
      }

      body.append(`
        <tr>
          <td class="fw-semibold">Q${q}</td>
          <td>${coverage}</td>
          <td>${due}</td>
          <td>${statusBadge}</td>
          <td class="text-end">${peso(amt)}</td>
          <td>${actionHtml}</td>
        </tr>
      `);
    });
  }

  function loadInstallmentSchedule(taxId){
    if(!taxId) return;

    $('#instScheduleBody').html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');

    $.ajax({
      url: 'ajax_installment_schedule.php',
      method: 'GET',
      dataType: 'json',
      cache: false,
      data: { tax_request_id: taxId },
      success: function(res){
        if(!res || !res.ok){
          const msg = (res && res.message) ? res.message : 'Failed to load schedule.';
          $('#instScheduleBody').html('<tr><td colspan="6" class="text-center text-danger">'+$('<div>').text(msg).html()+'</td></tr>');
          return;
        }
        renderScheduleRows(res.schedule || [], taxId);
      },
      error: function(){
        $('#instScheduleBody').html('<tr><td colspan="6" class="text-center text-danger">Server error loading schedule.</td></tr>');
      }
    });
  }

  $(document).on('click', '.btnViewInstallment', function(){
    const id = Number($(this).data('id')) || 0;
    const owner = $(this).data('owner') || '';
    const arp = $(this).data('arp') || '';
    const control = $(this).data('control') || '';

    $('#inst_tax_request_id').val(id);
    $('#inst_owner').text(owner);
    $('#inst_arp').text(arp);
    $('#inst_control').text(control);

    loadInstallmentSchedule(id);
    new bootstrap.Modal(document.getElementById('installmentModal')).show();
  });

  /* ==========================
     QUARTER PAYMENT MODAL LOGIC
  ========================== */
  let qpBaseAmount = 0;

  function computeQuarterPayable(){
    const discRate = Number($('#discount_rate').val()) || 0;
    let months = parseInt($('#penalty_months').val(), 10);
    if (isNaN(months)) months = 0;
    if (months < 0) months = 0;
    if (months > 36) months = 36;
    $('#penalty_months').val(months);

    const discountAmt = qpBaseAmount * discRate;
    const afterDisc = qpBaseAmount - discountAmt;
    const penaltyAmt = afterDisc * (0.02 * months);
    const total = afterDisc + penaltyAmt;

    $('#qp_discount_amt').text(peso(discountAmt));
    $('#qp_penalty_amt').text(peso(penaltyAmt));
    $('#total_payable').text(peso(total));

    return {
      discount_rate: discRate,
      penalty_months: months,
      discount_amount: discountAmt,
      penalty_amount: penaltyAmt,
      total_payable: total
    };
  }

  $(document).on('click', '.btnPayQuarter', function(){
    const taxId = Number($(this).data('tax-id')) || 0;
    const quarter = Number($(this).data('quarter')) || 0;
    const amount = Number($(this).data('amount')) || 0;

    $('#pay_tax_id').val(taxId);
    $('#pay_quarter').val(quarter);

    qpBaseAmount = amount;

    $('#quarter_amount').val(peso(amount));
    $('#discount_rate').val('0');
    $('#penalty_months').val('0');
    computeQuarterPayable();

    new bootstrap.Modal(document.getElementById('quarterPaymentModal')).show();
  });

  $('#discount_rate, #penalty_months').on('change keyup', computeQuarterPayable);

  $('#btnSaveQuarter').on('click', function(){
    const taxId = Number($('#pay_tax_id').val()) || 0;
    const quarter = Number($('#pay_quarter').val()) || 0;
    if(!taxId || !quarter){
      alert('Missing tax id / quarter.');
      return;
    }

    const calc = computeQuarterPayable();

    const btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');

    $.ajax({
      url: 'ajax_save_quarter_payment.php',
      method: 'POST',
      dataType: 'json',
      data: {
        tax_request_id: taxId,
        quarter: quarter,
        quarter_amount: qpBaseAmount.toFixed(2),
        discount_rate: calc.discount_rate,
        penalty_months: calc.penalty_months,
        total_payable: calc.total_payable.toFixed(2)
      },
      success: function(res){
        if(!res || !res.ok){
          alert((res && res.message) ? res.message : 'Failed to save quarter payment.');
          return;
        }

        const qmEl = document.getElementById('quarterPaymentModal');
        const qm = bootstrap.Modal.getInstance(qmEl);
        if (qm) qm.hide();

        loadInstallmentSchedule(taxId);
      },
      error: function(){
        alert('Server error saving quarter payment.');
      },
      complete: function(){
        btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Payment');
      }
    });
  });

  /* ==========================
     AUTO REFRESH + NOTIF SOUND
  ========================== */
  const qp = new URLSearchParams(window.location.search);
  const currentView = qp.get('view') || 'dashboard';
  const isRequestsView = (currentView === 'requests');

  let lastPendingTopId = 0;
  let lastTaxClearanceTopId = 0;
  let lastTaxTopId = 0;

  let lastPendingCount = Number($('#pendingCount').text() || 0);
  let lastTaxClearanceCount = Number($('#taxClearanceCount').text() || 0);
  let lastTaxCount = Number($('#taxPendingCount').text() || 0);

  function buildPendingRow(r){
    const id = Number(r.id || 0);
    const urlAccept = `process_request.php?id=${id}`;
    const urlDecline = `process_request.php?decline=${id}`;

    return [
      `<span class="id-badge">#${id}</span>`,
      `<span class="client-name">${$('<div>').text(r.fullname || '').html()}</span>`,
      `${$('<div>').text(r.address || '').html()}`,
      `${$('<div>').text(r.purpose || '').html()}`,
      `${$('<div>').text(r.arp_no || '-').html()}`,
      `${$('<div>').text(r.area || '-').html()}`,
      `<span class="certs-list">${$('<div>').text(r.items || '-').html()}</span>`,
      `<span class="amount">${peso(r.total_amount || 0)}</span>`,
      `<span class="date">${$('<div>').text(r.date_text || '').html()}</span>`,
      `
        <a href="${urlAccept}" class="action-btn accept" title="Accept / Mark Paid"><i class="fas fa-check"></i></a>
        <a href="${urlDecline}" class="action-btn decline" title="Decline"><i class="fas fa-times"></i></a>
      `
    ];
  }

  function buildTaxClearanceRow(r){
    const id = Number(r.id || 0);

    return [
      `<span class="id-badge">#${id}</span>`,
      `<span class="client-name">${$('<div>').text(r.fullname || '').html()}</span>`,
      `${$('<div>').text(r.address || '').html()}`,
      `${$('<div>').text(r.cp_no || '').html()}`,
      `${$('<div>').text(r.purpose || '').html()}`,
      `<span class="certs-list">Prepare latest receipt</span>`,
      `<span class="date">${$('<div>').text(r.date_text || '').html()}</span>`,
      `
        <button
          type="button"
          class="action-btn accept btnDoneTaxClearance"
          title="Mark as Done"
          data-id="${id}">
          <i class="fas fa-check"></i>
        </button>
      `
    ];
  }

  function buildTaxRow(t){
    const id = Number(t.id || 0);
    const owner = $('<div>').text(t.declared_owner || '').html();
    const arp   = $('<div>').text(t.arp_no || '').html();
    const status = $('<div>').text(t.status || 'PENDING').html();
    const avRaw = Number(t.assessed_value || 0).toFixed(2);

    return [
      `<span class="id-badge">#${id}</span>`,
      owner,
      arp,
      `<span class="text-end d-block">₱${Number(t.assessed_value || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</span>`,
      `<span class="text-end d-block fw-semibold">₱${Number(t.base_tax || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</span>`,
      `<span class="status-badge pending"><i class="fas fa-clock"></i> ${status}</span>`,
      `<span class="date">${$('<div>').text(t.date_text || '').html()}</span>`,
      `
      <button type="button" class="action-btn accept btnProcessTax"
        title="Process Payment"
        data-id="${id}"
        data-owner="${(t.declared_owner || '').replace(/"/g,'&quot;')}"
        data-arp="${(t.arp_no || '').replace(/"/g,'&quot;')}"
        data-av="${avRaw}">
        <i class="fas fa-cash-register"></i>
      </button>
      `
    ];
  }

  function shouldBeep(pendingArr, taxClearanceArr, taxArr, pendingCountNow, taxClearanceCountNow, taxCountNow){
    const topPendingId = pendingArr.length ? Number(pendingArr[0].id || 0) : 0;
    const topTaxClearanceId = taxClearanceArr.length ? Number(taxClearanceArr[0].id || 0) : 0;
    const topTaxId = taxArr.length ? Number(taxArr[0].id || 0) : 0;

    const pendingNewById = (topPendingId && topPendingId > lastPendingTopId);
    const taxClearanceNewById = (topTaxClearanceId && topTaxClearanceId > lastTaxClearanceTopId);
    const taxNewById = (topTaxId && topTaxId > lastTaxTopId);

    const pendingNewByCount = (pendingCountNow > lastPendingCount);
    const taxClearanceNewByCount = (taxClearanceCountNow > lastTaxClearanceCount);
    const taxNewByCount = (taxCountNow > lastTaxCount);

    if (NOTIF_MODE === "TAX") {
      return (taxNewById || taxNewByCount);
    }
    if (NOTIF_MODE === "PENDING") {
      return (pendingNewById || pendingNewByCount || taxClearanceNewById || taxClearanceNewByCount);
    }
    return false;
  }

  function refreshRequestsTables(){
    if (!isRequestsView) return;

    $.ajax({
      url: 'ajax_home_pending.php',
      method: 'GET',
      dataType: 'json',
      cache: false,
      success: function(data){
        if (!data || !data.ok) return;

        const pendingArr = Array.isArray(data.pending) ? data.pending : [];
        const taxClearanceArr = Array.isArray(data.tax_clearance) ? data.tax_clearance : [];
        const taxArr = Array.isArray(data.tax_pending) ? data.tax_pending : [];

        const pendingCountNow = Number(data.pending_count || 0);
        const taxClearanceCountNow = Number(data.tax_clearance_count || 0);
        const taxCountNow = Number(data.tax_pending_count || 0);

        const isInitial = (lastPendingTopId === 0 && lastTaxTopId === 0 && lastTaxClearanceTopId === 0);

        if (!isInitial && shouldBeep(pendingArr, taxClearanceArr, taxArr, pendingCountNow, taxClearanceCountNow, taxCountNow)) {
          playNotif();
        }

        $('#pendingCount').text(pendingCountNow);
        $('#taxClearanceCount').text(taxClearanceCountNow);
        $('#taxPendingCount').text(taxCountNow);

        const topPendingId = pendingArr.length ? Number(pendingArr[0].id || 0) : 0;
        const topTaxClearanceId = taxClearanceArr.length ? Number(taxClearanceArr[0].id || 0) : 0;
        const topTaxId = taxArr.length ? Number(taxArr[0].id || 0) : 0;

        if (topPendingId) lastPendingTopId = topPendingId;
        if (topTaxClearanceId) lastTaxClearanceTopId = topTaxClearanceId;
        if (topTaxId) lastTaxTopId = topTaxId;

        lastPendingCount = pendingCountNow;
        lastTaxClearanceCount = taxClearanceCountNow;
        lastTaxCount = taxCountNow;

        if (pendingDT) {
          pendingDT.clear();
          pendingDT.rows.add(pendingArr.map(buildPendingRow));
          pendingDT.draw(false);
        }

        if (taxClearanceDT) {
          taxClearanceDT.clear();
          taxClearanceDT.rows.add(taxClearanceArr.map(buildTaxClearanceRow));
          taxClearanceDT.draw(false);
        }

        if (taxDT) {
          taxDT.clear();
          taxDT.rows.add(taxArr.map(buildTaxRow));
          taxDT.draw(false);
        }
      }
    });
  }

  if (isRequestsView) {
    setTimeout(function(){
      lastPendingCount = Number($('#pendingCount').text() || 0);
      lastTaxClearanceCount = Number($('#taxClearanceCount').text() || 0);
      lastTaxCount = Number($('#taxPendingCount').text() || 0);

      const pFirst = $('#pendingTable tbody tr:first .id-badge').text().replace('#','');
      const tcFirst = $('#taxClearanceTable tbody tr:first .id-badge').text().replace('#','');
      const tFirst = $('#taxPendingTable tbody tr:first .id-badge').text().replace('#','');

      lastPendingTopId = Number(pFirst || 0) || 0;
      lastTaxClearanceTopId = Number(tcFirst || 0) || 0;
      lastTaxTopId = Number(tFirst || 0) || 0;

      refreshRequestsTables();
    }, 700);

    setInterval(refreshRequestsTables, 7000);
  }

  $(document).on('click', '.btnDoneTaxClearance', function() {
    const btn = $(this);
    const id = Number(btn.data('id')) || 0;

    if (!id) {
      alert('Invalid client ID.');
      return;
    }

    if (!confirm('Mark this Tax Clearance request as done?')) {
      return;
    }

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.ajax({
      url: 'mark_tax_clearance_done.php',
      method: 'POST',
      dataType: 'json',
      data: { id: id },
      success: function(res) {
        if (!res || !res.ok) {
          alert((res && res.message) ? res.message : 'Failed to mark as done.');
          btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
          return;
        }

        refreshRequestsTables();
      },
      error: function(xhr) {
        let msg = 'Server error while marking as done.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
        btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
      }
    });
  });

});
</script>

<noscript>
  <div style="background:#dc3545;color:white;padding:10px;text-align:center;">
    JavaScript is required for this application to work properly. Please enable JavaScript in your browser.
  </div>
</noscript>

</body>
</html>