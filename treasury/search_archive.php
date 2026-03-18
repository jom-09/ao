<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database_tax_archive.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {

    $search = "%$q%";

    $stmt = $taxConn->prepare("
        SELECT 
            id, date, name, period, or_no, td_no, name_brgy, total
        FROM taxpayer_raw_imports
        WHERE 
            name LIKE ?
            OR td_no LIKE ?
            OR or_no LIKE ?
            OR name_brgy LIKE ?
            OR period LIKE ?
        ORDER BY date DESC
        LIMIT 1000
    ");

    $stmt->bind_param("sssss", $search, $search, $search, $search, $search);
    $stmt->execute();
    $results = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Search Tax Archive</title>
<link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
</head>
<body>

<div class="container py-4">

    <h3 class="mb-3">Search Tax Archive</h3>

    <form method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" name="q" class="form-control"
                   placeholder="Search name, TD No, OR No, Barangay..."
                   value="<?= h($q) ?>">
            <button class="btn btn-primary">Search</button>
        </div>
    </form>

    <?php if($q !== ''): ?>
        <p><strong>Results for:</strong> <?= h($q) ?></p>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>TD No</th>
                    <th>OR No</th>
                    <th>Barangay</th>
                    <th>Period</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            <?php if($results && $results->num_rows > 0): ?>
                <?php while($row = $results->fetch_assoc()): ?>
                <tr>
                    <td><?= h($row['date']) ?></td>
                    <td><?= h($row['name']) ?></td>
                    <td><?= h($row['td_no']) ?></td>
                    <td><?= h($row['or_no']) ?></td>
                    <td><?= h($row['name_brgy']) ?></td>
                    <td><?= h($row['period']) ?></td>
                    <td>₱<?= number_format($row['total'],2) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">No results found</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>