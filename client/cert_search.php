<?php
session_start();
require_once "../config/database.php";

if (
    !isset($_SESSION['client_info'], $_SESSION['selected_service']) ||
    $_SESSION['selected_service'] !== 'cert'
) {
    header("Location: index.php?error=Please+start+your+request+again.");
    exit();
}

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

$client = $_SESSION['client_info'];

$firstname  = trim((string)($client['firstname'] ?? $client['first_name'] ?? ''));
$middlename = trim((string)($client['middlename'] ?? $client['middle_name'] ?? ''));
$lastname   = trim((string)($client['lastname'] ?? $client['last_name'] ?? ''));

if ($firstname === '' || $lastname === '') {
    header("Location: index.php?error=Missing+client+name.");
    exit();
}

/*
|--------------------------------------------------------------------------
| SEARCH KEYWORDS
|--------------------------------------------------------------------------
| Since land_holdings_master only has declared_owner,
| we'll search several patterns:
| 1. LASTNAME
| 2. FIRSTNAME
| 3. FIRSTNAME LASTNAME
| 4. LASTNAME, FIRSTNAME
*/
$searchLast      = "%" . $lastname . "%";
$searchFirst     = "%" . $firstname . "%";
$searchFull1     = "%" . $firstname . "% " . $lastname . "%";
$searchFull2     = "%" . $lastname . "%, " . $firstname . "%";
$searchFull3     = "%" . $lastname . ", " . $firstname . "%";

$results = [];

$sql = "
    SELECT
        declared_owner,
        owner_address,
        property_location,
        title,
        lot,
        `ARP_No.` AS arp_no,
        area
    FROM land_holdings_master
    WHERE
        declared_owner LIKE ?
        OR declared_owner LIKE ?
        OR declared_owner LIKE ?
        OR declared_owner LIKE ?
        OR declared_owner LIKE ?
    ORDER BY declared_owner ASC
    LIMIT 200
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL prepare error: " . $conn->error);
}

$stmt->bind_param(
    "sssss",
    $searchLast,
    $searchFirst,
    $searchFull1,
    $searchFull2,
    $searchFull3
);

$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $results[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certification Search Results</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
    <link href="../assets/bootstrap/css/style.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fb;
        }
        .search-card {
            border: 0;
            border-radius: 18px;
            overflow: hidden;
        }
        .search-header {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: #fff;
            padding: 20px;
        }
        .table thead th {
            white-space: nowrap;
            font-size: 14px;
        }
        .table tbody td {
            vertical-align: middle;
            font-size: 14px;
        }
        .badge-result {
            background: #e9f2ff;
            color: #0d6efd;
            font-weight: 600;
            border-radius: 30px;
            padding: 8px 14px;
        }
    </style>
</head>
<body>

<div class="container py-4 py-md-5">
    <div class="card shadow search-card">
        <div class="search-header">
            <h3 class="mb-1 fw-bold">Certification Issuance - Search Results</h3>
            <p class="mb-0">
                Search result for:
                <strong><?php echo h($lastname . ', ' . $firstname . ($middlename !== '' ? ' ' . $middlename : '')); ?></strong>
            </p>
        </div>

        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <span class="badge-result">
                    <?php echo count($results); ?> record(s) found
                </span>

                <a href="index.php" class="btn btn-outline-secondary">
                    Back
                </a>
            </div>

            <?php if (!empty($results)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Declared Owner</th>
                                <th>Owner Location</th>
                                <th>Property Location</th>
                                <th>Title</th>
                                <th>Lot</th>
                                <th>ARP No.</th>
                                <th>Area</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td><?php echo h($row['declared_owner']); ?></td>
                                    <td><?php echo h($row['owner_address']); ?></td>
                                    <td><?php echo h($row['property_location']); ?></td>
                                    <td><?php echo h($row['title']); ?></td>
                                    <td><?php echo h($row['lot']); ?></td>
                                    <td><?php echo h($row['arp_no']); ?></td>
                                    <td><?php echo h($row['area']); ?></td>
                                    <td>
                                        <form action="select_cert.php" method="POST" class="m-0">
                                            <input type="hidden" name="declared_owner" value="<?php echo h($row['declared_owner']); ?>">
                                            <input type="hidden" name="owner_address" value="<?php echo h($row['owner_address']); ?>">
                                            <input type="hidden" name="property_location" value="<?php echo h($row['property_location']); ?>">
                                            <input type="hidden" name="title" value="<?php echo h($row['title']); ?>">
                                            <input type="hidden" name="lot" value="<?php echo h($row['lot']); ?>">
                                            <input type="hidden" name="arp_no" value="<?php echo h($row['arp_no']); ?>">
                                            <input type="hidden" name="area" value="<?php echo h($row['area']); ?>">

                                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                                Select
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-3">
                    No matching record found in <strong>land_holdings_master</strong>.
                </div>

                <div class="d-flex gap-2">
                    <a href="index.php" class="btn btn-secondary">Back to Client Form</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>