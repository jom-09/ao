<?php
session_start();
require_once "../config/database.php";

$ref = isset($_GET['ref']) ? (int)$_GET['ref'] : 0;
if ($ref <= 0) {
    header("Location: select_service.php");
    exit();
}

/* Fetch request + client */
$stmt = $conn->prepare("
    SELECT 
        r.id,
        r.total_amount,
        r.status,
        r.control_number,
        r.created_at,
        c.firstname,
        c.middlename,
        c.lastname,
        c.address,
        c.cp_no
    FROM requests r
    INNER JOIN clients c ON c.id = r.client_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $ref);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();
$stmt->close();

if (!$request) {
    header("Location: select_service.php?error=Invalid+reference");
    exit();
}

/* Fetch requested services */
$stmt = $conn->prepare("
    SELECT 
        s.service_name,
        s.description,
        rs.price_at_time,
        rs.created_at
    FROM requested_services rs
    INNER JOIN services s ON s.id = rs.service_id
    WHERE rs.request_id = ?
    ORDER BY rs.id ASC
");
$stmt->bind_param("i", $ref);
$stmt->execute();
$services = $stmt->get_result();
$stmt->close();

$fullname = trim(
    $request['firstname'] . ' ' .
    ($request['middlename'] ? $request['middlename'] . ' ' : '') .
    $request['lastname']
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Request Submitted</title>
    <link href="../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">✅ Service Request Submitted</h4>
                    <div class="text-muted">Please keep your reference number.</div>
                </div>
                <div class="text-end">
                    <div class="small text-muted">Reference No.</div>
                    <div class="fs-4 fw-bold"><?php echo htmlspecialchars($request['id']); ?></div>
                </div>
            </div>

            <hr>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="small text-muted">Client Name</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($fullname); ?></div>
                </div>

                <div class="col-md-6">
                    <div class="small text-muted">Contact No.</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($request['cp_no']); ?></div>
                </div>

                <div class="col-md-6">
                    <div class="small text-muted">Address</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($request['address']); ?></div>
                </div>

                <div class="col-md-3">
                    <div class="small text-muted">Status</div>
                    <span class="badge bg-warning text-dark">
                        <?php echo htmlspecialchars($request['status']); ?>
                    </span>
                </div>

                <div class="col-md-3">
                    <div class="small text-muted">Date Submitted</div>
                    <div class="fw-semibold">
                        <?php echo htmlspecialchars(date("F d, Y h:i A", strtotime($request['created_at']))); ?>
                    </div>
                </div>
            </div>

            <hr>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="small text-muted">Total Amount</div>
                    <div class="fs-5 fw-bold">₱<?php echo number_format((float)$request['total_amount'], 2); ?></div>
                </div>

                <div class="d-flex gap-2">
                    <a href="index.php" class="btn btn-outline-secondary">
                        Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <strong>Requested Services</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 30%;">Service</th>
                            <th>Description</th>
                            <th style="width: 15%;" class="text-end">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($services->num_rows > 0): ?>
                        <?php while ($row = $services->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($row['service_name']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($row['description']); ?></td>
                                <td class="text-end">₱<?php echo number_format((float)$row['price_at_time'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                                No services found for this request.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>