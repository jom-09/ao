<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// helper safe
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// view switch
$view = $_GET['view'] ?? 'home';
$view = in_array($view, ['home','tax_history','transactions','installments'], true) ? $view : 'home';
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
    <!-- ✅ NAV LINKS -->
    <div class="d-flex align-items-center gap-2 me-3">
      <a href="home.php?view=home"
         class="btn btn-sm <?= $view==='home' ? 'btn-light' : 'btn-outline-light' ?>">
        <i class="fas fa-home me-1"></i> Home
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

  <!-- ✅ Alerts -->
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


  <?php if ($view === 'home'): ?>

    <!-- =========================
         HOME: Search + Requests
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
            'villa_pagaduan','villa_santiago','villa_ventura', 'ligaya'
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

    <!-- ✅ Person Records Table (NOW includes AV) -->
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

    <!-- Pending Requests Card (CERT/SVC) -->
    <div class="dashboard-card">
      <div class="card-header pending-header">
        <div class="header-left">
          <i class="fas fa-clock"></i>
          <h5>Pending Requests</h5>
        </div>
        <span class="badge pending-badge" id="pendingCount">0</span>
      </div>

      <div class="card-body">
        <table class="modern-table" id="pendingTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Client</th>
              <th>Address</th>
              <th>Purpose</th>
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
          ";
          $result = $conn->query($sql);

          while($row=$result->fetch_assoc()):
            $items = "-";
            if (!empty($row['certificate_list'])) $items = $row['certificate_list'];
            elseif (!empty($row['service_list'])) $items = $row['service_list'];
          ?>
            <tr>
              <td><span class="id-badge">#<?php echo (int)$row['id']; ?></span></td>
              <td><span class="client-name"><?php echo h($row['fullname']); ?></span></td>
              <td><?php echo h($row['address'] ?? ''); ?></td>
              <td><?php echo h($row['purpose']); ?></td>
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

    <!-- ✅ Tax Payment Requests Card (PENDING) -->
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
        <div class="filter-group">
          <select class="filter-select" id="statusFilter">
            <option value="">All Status</option>
            <option value="PAID">Paid</option>
            <option value="DECLINED">Declined</option>
          </select>
          <input type="text" class="filter-input" id="dateFilter" placeholder="Filter by date...">
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
            WHERE r.status IN('PAID','DECLINED')
            ORDER BY r.created_at DESC
          ";
          $result2 = $conn->query($sql2);

          while($row=$result2->fetch_assoc()):
            $items2 = "-";
            if (!empty($row['certificate_list'])) $items2 = $row['certificate_list'];
            elseif (!empty($row['service_list'])) $items2 = $row['service_list'];
          ?>
            <tr>
              <td><span class="id-badge">#<?php echo (int)$row['id']; ?></span></td>
              <td><?php echo h($row['fullname']); ?></td>
              <td><?php echo h($row['address'] ?? ''); ?></td>
              <td><?php echo h($row['purpose']); ?></td>
              <td><?php echo h($items2); ?></td>
              <td><span class="amount">₱<?php echo number_format((float)$row['total_amount'],2); ?></span></td>
              <td><span class="control-no"><?php echo h($row['control_number']); ?></span></td>
              <td>
                <?php if($row['status']=='PAID'): ?>
                  <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                <?php elseif($row['status']=='DECLINED'): ?>
                  <span class="status-badge declined"><i class="fas fa-times-circle"></i> DECLINED</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="date">
                  <?php
                    $dateToShow = $row['paid_at'] ? $row['paid_at'] : date('Y-m-d');
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
              <label class="form-label fw-semibold">Control Number</label>
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

<script>
$(document).ready(function() {

  /* ==========================
     DATATABLES
  ========================== */

  if ($('#pendingTable').length) {
    $('#pendingCount').text($('#pendingTable tbody tr').length);
    $('#pendingTable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
      language: { search: "<i class='fas fa-search'></i>", searchPlaceholder: "Search pending requests..." },
      dom: '<"table-toolbar"f>rtip',
      initComplete: function() { $('#pendingTable_filter input').attr('placeholder', 'Search pending...'); }
    });
  }

  if ($('#taxPendingTable').length) {
    $('#taxPendingTable').DataTable({
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
    const historyTable = $('#historyTable').DataTable({
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
      language: { search: "<i class='fas fa-search'></i>", searchPlaceholder: "Search transactions..." },
      dom: '<"table-toolbar"f>rtip',
      initComplete: function() { $('#historyTable_filter input').attr('placeholder', 'Search history...'); }
    });

    $('#statusFilter').on('change', function() {
      const status = $(this).val();
      if (status === '') historyTable.column(7).search('').draw();
      else historyTable.column(7).search('^' + status + '$', true, false).draw();
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
      error: function() {
        alert('Error searching records. Please try again.');
      },
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
     TAX MODAL COMPUTE
     ✅ FIX: single declaration only
  ========================== */
  function peso(n){
    return "₱" + Number(n).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  let currentAV = 0;

  $(document).on('click', '.btnProcessTax', function() {
    const id = $(this).data('id');
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
     INSTALLMENT MODAL (Schedule)
  ========================== */
  $(document).on('click', '.btnViewInstallment', function() {
    const id = Number($(this).data('id')) || 0;
    $('#inst_tax_request_id').val(id);

    $('#inst_owner').text($(this).data('owner') || '');
    $('#inst_arp').text($(this).data('arp') || '');
    $('#inst_control').text($(this).data('control') || '');

    $('#instScheduleBody').html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');

    $.ajax({
      url: 'get_installment_schedule.php',
      method: 'POST',
      dataType: 'json',
      data: { tax_request_id: id },
      success: function(res) {
        if (!res || res.error) {
          $('#instScheduleBody').html('<tr><td colspan="6" class="text-center text-danger">' + (res.error || 'Error') + '</td></tr>');
          return;
        }

        let html = '';
        res.forEach(function(r){
          const qLabel = (r.quarter === 1 ? '1st' : r.quarter === 2 ? '2nd' : r.quarter === 3 ? '3rd' : '4th');
          const statusBadge = (r.status === 'PAID')
            ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>PAID</span>'
            : '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>PENDING</span>';

          const amount = Number(r.amount || 0);
          const amountText = amount.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});

          let actionBtn = '';
          if (r.status === 'PAID') {
            actionBtn = '<button class="btn btn-sm btn-outline-secondary" disabled>Paid</button>';
          } else {
            actionBtn = '<button class="btn btn-sm btn-primary btnPayQuarter" data-q="'+r.quarter+'" data-amount="'+amount+'"><i class="fas fa-coins me-1"></i>Pay</button>';
          }

          html += `
            <tr>
              <td><strong>${qLabel} Quarter</strong></td>
              <td>${r.coverage}</td>
              <td>${r.due_date_text}</td>
              <td>${statusBadge}</td>
              <td class="text-end">₱${amountText}</td>
              <td>${actionBtn}</td>
            </tr>
          `;
        });

        $('#instScheduleBody').html(html);
      },
      error: function() {
        $('#instScheduleBody').html('<tr><td colspan="6" class="text-center text-danger">Request failed.</td></tr>');
      }
    });

    new bootstrap.Modal(document.getElementById('installmentModal')).show();
  });

  /* ==========================
     QUARTER PAYMENT (Manual)
  ========================== */
  const fmt = (n) => "₱" + Number(n).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});

  function computeQuarterTotal(){
    let amount = Number($('#quarter_amount').data('raw')) || 0;
    let discount = Number($('#discount_rate').val()) || 0;

    let months = parseInt($('#penalty_months').val(), 10);
    if (isNaN(months)) months = 0;
    if (months < 0) months = 0;
    if (months > 36) months = 36;
    $('#penalty_months').val(months);

    const discountAmt = amount * discount;
    const afterDiscount = amount - discountAmt;
    const penaltyAmt = afterDiscount * (0.02 * months);
    const total = afterDiscount + penaltyAmt;

    $('#qp_discount_amt').text(fmt(discountAmt));
    $('#qp_penalty_amt').text(fmt(penaltyAmt));
    $('#total_payable').text(fmt(total));
  }

  $(document).on('click', '.btnPayQuarter', function() {
    const taxId = Number($('#inst_tax_request_id').val()) || 0;
    const quarter = Number($(this).data('q')) || 0;
    const amount = Number($(this).data('amount')) || 0;

    if (!taxId || !quarter) return;

    $('#pay_tax_id').val(taxId);
    $('#pay_quarter').val(quarter);

    $('#quarter_amount')
      .val(fmt(amount))
      .data('raw', amount);

    $('#discount_rate').val('0');
    $('#penalty_months').val('0');

    computeQuarterTotal();

    new bootstrap.Modal(document.getElementById('quarterPaymentModal')).show();
  });

  $('#discount_rate, #penalty_months').on('change keyup', computeQuarterTotal);

  // Save quarter payment
  $('#btnSaveQuarter').on('click', function() {
    const taxId = Number($('#pay_tax_id').val()) || 0;
    const quarter = Number($('#pay_quarter').val()) || 0;
    const discountRate = Number($('#discount_rate').val()) || 0;

    let months = parseInt($('#penalty_months').val(), 10);
    if (isNaN(months)) months = 0;
    if (months < 0) months = 0;
    if (months > 36) months = 36;
    $('#penalty_months').val(months);

    if (!taxId || !quarter) return;

    const btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');

    $.ajax({
      url: 'pay_installment.php',
      method: 'POST',
      dataType: 'json',
      data: {
        tax_request_id: taxId,
        quarter: quarter,
        discount_rate: discountRate,
        penalty_months: months
      },
      success: function(res) {
        if (!res || res.error) {
          alert(res.error || 'Error saving payment');
          btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Payment');
          return;
        }

        // close payment modal
        const pm = bootstrap.Modal.getInstance(document.getElementById('quarterPaymentModal'));
        if (pm) pm.hide();

        // refresh schedule
        $('.btnViewInstallment[data-id="'+taxId+'"]').trigger('click');

        btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Payment');
      },
      error: function() {
        alert('Request failed.');
        btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Payment');
      }
    });
  });

  /* ==========================
     AUTO REFRESH (HOME ONLY)
     ✅ FIX: removed duplicate peso() here (we already have it above)
  ========================== */
  const isHomeView = new URLSearchParams(window.location.search).get('view') === null
                 || new URLSearchParams(window.location.search).get('view') === 'home';

  let pendingDT = null;
  let taxDT = null;

  if ($('#pendingTable').length) {
    pendingDT = $.fn.DataTable.isDataTable('#pendingTable')
      ? $('#pendingTable').DataTable()
      : null;
  }

  if ($('#taxPendingTable').length) {
    taxDT = $.fn.DataTable.isDataTable('#taxPendingTable')
      ? $('#taxPendingTable').DataTable()
      : null;
  }

  let lastPendingTopId = 0;
  let lastTaxTopId = 0;

  function buildPendingRow(r){
    const id = Number(r.id || 0);
    const urlAccept = `process_request.php?id=${id}`;
    const urlDecline = `process_request.php?decline=${id}`;

    return [
      `<span class="id-badge">#${id}</span>`,
      `<span class="client-name">${$('<div>').text(r.fullname || '').html()}</span>`,
      `${$('<div>').text(r.address || '').html()}`,
      `${$('<div>').text(r.purpose || '').html()}`,
      `<span class="certs-list">${$('<div>').text(r.items || '-').html()}</span>`,
      `<span class="amount">${peso(r.total_amount || 0)}</span>`,
      `<span class="date">${$('<div>').text(r.date_text || '').html()}</span>`,
      `
        <a href="${urlAccept}" class="action-btn accept" title="Accept / Mark Paid">
          <i class="fas fa-check"></i>
        </a>
        <a href="${urlDecline}" class="action-btn decline" title="Decline">
          <i class="fas fa-times"></i>
        </a>
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
      <button
        type="button"
        class="action-btn accept btnProcessTax"
        title="Process Payment"
        data-id="${id}"
        data-owner="${(t.declared_owner || '').replace(/"/g,'&quot;')}"
        data-arp="${(t.arp_no || '').replace(/"/g,'&quot;')}"
        data-av="${avRaw}"
      >
        <i class="fas fa-cash-register"></i>
      </button>
      `
    ];
  }

  function refreshHomeTables(){
    if (!isHomeView) return;

    $.ajax({
      url: 'ajax_home_pending.php',
      method: 'GET',
      dataType: 'json',
      cache: false,
      success: function(data){
        if (!data || !data.ok) return;

        $('#pendingCount').text(Number(data.pending_count || 0));
        $('#taxPendingCount').text(Number(data.tax_pending_count || 0));

        const pendingArr = Array.isArray(data.pending) ? data.pending : [];
        const taxArr     = Array.isArray(data.tax_pending) ? data.tax_pending : [];

        const topPendingId = pendingArr.length ? Number(pendingArr[0].id || 0) : 0;
        const topTaxId     = taxArr.length ? Number(taxArr[0].id || 0) : 0;

        if (pendingDT) {
          const rows = pendingArr.map(buildPendingRow);
          pendingDT.clear();
          pendingDT.rows.add(rows);
          pendingDT.draw(false);
        }

        if (taxDT) {
          const rows2 = taxArr.map(buildTaxRow);
          taxDT.clear();
          taxDT.rows.add(rows2);
          taxDT.draw(false);
        }

        lastPendingTopId = topPendingId || lastPendingTopId;
        lastTaxTopId     = topTaxId || lastTaxTopId;
      }
    });
  }

  if (isHomeView) {
    setTimeout(refreshHomeTables, 800);
    setInterval(refreshHomeTables, 7000);
  }

});
</script>

<noscript>
  <div style="background:#dc3545;color:white;padding:10px;text-align:center;">
    JavaScript is required for this application to work properly. Please enable JavaScript in your browser.
  </div>
</noscript>

</body>
</html>