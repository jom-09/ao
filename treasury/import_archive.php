<?php
require_once "../includes/auth_treasury.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Tax Archive</title>
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/bootstrap/css/datatables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="home.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_GET['success']) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <strong>Upload Excel / CSV File</strong>
        </div>
        <div class="card-body">
            <form action="import_archive_process.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Batch Name</label>
                    <input type="text" name="batch_name" class="form-control" placeholder="Halimbawa: Archive File 1 (1996-2006)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Excel / CSV File</label>
                    <input type="file" name="archive_file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    <small class="text-muted">Allowed: .xlsx, .xls, .csv</small>
                </div>

                <div class="alert alert-info mb-3">
                    Expected headers:
                    <br>
                    <strong>type, date, name, period, or_no, td_no, name_brgy, r1, r2, r3, r4, r5, r6, r7, r8, r9, r10, r11, r12, r13, r14, total</strong>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-file-import"></i> Start Import
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>