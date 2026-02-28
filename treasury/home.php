<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database.php";
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
        <div class="user-badge">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
        </div>
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>

<div class="main-container">

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
                    'villa_pagaduan','villa_santiago','villa_ventura'
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
    <div class="records-section" id="recordsSection" style="display: none;">
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
                        <th>Property Location</th>
                        <th>Title</th>
                        <th>Lot</th>
                        <th>ARP No.</th>
                        <th>PIN No.</th>
                        <th>Classification</th>
                        <th>Actual Use</th>
                        <th>Area</th>
                        <th>MV</th>
                        <th>AV</th>
                        <th>Taxability</th>
                        <th>Effectivity</th>
                        <th>Cancellation</th>
                    </tr>
                </thead>
                <tbody id="recordsTableBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Pending Requests Card -->
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
                    if (!empty($row['certificate_list'])) {
                        $items = $row['certificate_list'];
                    } elseif (!empty($row['service_list'])) {
                        $items = $row['service_list'];
                    }
                ?>
                    <tr>
                        <td><span class="id-badge">#<?php echo (int)$row['id']; ?></span></td>
                        <td><span class="client-name"><?php echo htmlspecialchars($row['fullname']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td><span class="certs-list"><?php echo htmlspecialchars($items); ?></span></td>
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

    <!-- Transaction History Card -->
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
                    if (!empty($row['certificate_list'])) {
                        $items2 = $row['certificate_list'];
                    } elseif (!empty($row['service_list'])) {
                        $items2 = $row['service_list'];
                    }
                ?>
                    <tr>
                        <td><span class="id-badge">#<?php echo (int)$row['id']; ?></span></td>
                        <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                        <td><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($items2); ?></td>
                        <td><span class="amount">₱<?php echo number_format((float)$row['total_amount'],2); ?></span></td>
                        <td><span class="control-no"><?php echo htmlspecialchars($row['control_number']); ?></span></td>
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
</div>

<!-- Scripts - Load in correct order -->
<script src="../assets/js/jquery-3.7.1.min.js"></script>
<script src="../assets/js/datatables.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    console.log("jQuery loaded successfully!");

    // Update pending count
    var pendingCount = $('#pendingTable tbody tr').length;
    $('#pendingCount').text(pendingCount);

    // Initialize DataTables
    if ($('#pendingTable').length > 0) {
        $('#pendingTable').DataTable({
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            language: {
                search: "<i class='fas fa-search'></i>",
                searchPlaceholder: "Search pending requests..."
            },
            dom: '<"table-toolbar"f>rtip',
            initComplete: function() {
                $('#pendingTable_filter input').attr('placeholder', 'Search pending...');
            }
        });
    }

    if ($('#historyTable').length > 0) {
        var historyTable = $('#historyTable').DataTable({
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            language: {
                search: "<i class='fas fa-search'></i>",
                searchPlaceholder: "Search transactions..."
            },
            dom: '<"table-toolbar"f>rtip',
            initComplete: function() {
                $('#historyTable_filter input').attr('placeholder', 'Search history...');
            }
        });

        // Status filter
        $('#statusFilter').on('change', function() {
            var status = $(this).val();
            if(status === '') {
                historyTable.column(6).search('').draw();
            } else {
                historyTable.column(6).search('^' + status + '$', true, false).draw();
            }
        });
    }

    // Person search
    $('#searchPersonBtn').click(function() {
        var barangay = $('#barangaySelect').val();
        var ownerName = $('#personSearch').val().trim();

        if(!barangay) {
            alert('Please select a barangay');
            return;
        }
        if(!ownerName) {
            alert('Please enter owner name');
            return;
        }

        $('#searchPersonBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Searching...');

        $.ajax({
            url: 'search_person_records.php',
            method: 'POST',
            data: {
                barangay: barangay,
                owner_name: ownerName
            },
            dataType: 'json',
            success: function(response) {
                displayPersonRecords(response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error searching records. Please try again.');
            },
            complete: function() {
                $('#searchPersonBtn').prop('disabled', false).html('<i class="fas fa-search"></i> Search Records');
            }
        });
    });

    function displayPersonRecords(records) {
        var tbody = $('#recordsTableBody');
        tbody.empty();

        if(records && records.length > 0) {
            $('#recordsSection').show();
            $('#recordCount').text(records.length + ' record(s) found');

            $.each(records, function(index, record) {
                var row = '<tr>' +
                    '<td>' + (record.declared_owner || '') + '</td>' +
                    '<td>' + (record.owner_address || '') + '</td>' +
                    '<td>' + (record.property_location || '') + '</td>' +
                    '<td>' + (record.title || '') + '</td>' +
                    '<td>' + (record.lot || '') + '</td>' +
                    '<td>' + (record['ARP_No.'] || '') + '</td>' +
                    '<td>' + (record['PIN_No.'] || '') + '</td>' +
                    '<td>' + (record.classification || '') + '</td>' +
                    '<td>' + (record.actual_use || '') + '</td>' +
                    '<td>' + (record.area || '') + '</td>' +
                    '<td>' + (record.mv || '') + '</td>' +
                    '<td>' + (record.av || '') + '</td>' +
                    '<td>' + (record.taxability || '') + '</td>' +
                    '<td>' + (record.effectivity || '') + '</td>' +
                    '<td>' + (record.cancellation || '') + '</td>' +
                '</tr>';
                tbody.append(row);
            });
        } else {
            $('#recordsSection').show();
            $('#recordCount').text('No records found');
            tbody.append('<tr><td colspan="15" class="no-records">No property records found for this owner</td></tr>');
        }
    }

    $('#personSearch').on('keyup', function(e) {
        if(e.key === 'Enter') {
            $('#searchPersonBtn').click();
        }
    });

    $('#barangaySelect').on('change', function() {
        if($(this).val()) {
            $(this).addClass('selected');
        } else {
            $(this).removeClass('selected');
        }
    });
});

window.onerror = function(msg, url, line) {
    if(msg.indexOf('jQuery') !== -1 || msg.indexOf('$ is not defined') !== -1) {
        alert('Error loading jQuery. Please refresh the page or check your internet connection.');
    }
    return false;
};
</script>

<noscript>
    <div style="background: #dc3545; color: white; padding: 10px; text-align: center;">
        JavaScript is required for this application to work properly. Please enable JavaScript in your browser.
    </div>
</noscript>

</body>
</html>