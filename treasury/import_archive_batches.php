<?php
require_once "../includes/auth_treasury.php";
require_once "../config/database_tax_archive.php";

$result = $taxConn->query("
    SELECT id, batch_name, source_file, remarks, total_rows, inserted_rows, skipped_rows, duplicate_rows, imported_by, created_at
    FROM import_batches
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imported Archive Batches</title>
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Imported Archive Batches</h3>
        <a href="import_archive.php" class="btn btn-secondary">Back</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Batch Name</th>
                    <th>Source File</th>
                    <th>Remarks</th>
                    <th>Total Rows</th>
                    <th>Inserted</th>
                    <th>Skipped</th>
                    <th>Duplicates</th>
                    <th>Imported By</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= htmlspecialchars($row['batch_name']) ?></td>
                    <td><?= htmlspecialchars($row['source_file']) ?></td>
                    <td><?= htmlspecialchars($row['remarks']) ?></td>
                    <td><?= (int)$row['total_rows'] ?></td>
                    <td><?= (int)$row['inserted_rows'] ?></td>
                    <td><?= (int)$row['skipped_rows'] ?></td>
                    <td><?= (int)$row['duplicate_rows'] ?></td>
                    <td><?= htmlspecialchars($row['imported_by']) ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>