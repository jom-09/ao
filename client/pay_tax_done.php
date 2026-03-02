<?php
session_start();
$success = htmlspecialchars((string)($_GET['success'] ?? 'Saved.'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pay Tax | Saved</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
  <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
<div class="container py-5">
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <h4 class="fw-bold mb-2"><i class="bi bi-check-circle me-2"></i>Done</h4>
      <p class="text-muted mb-3"><?= $success ?></p>
      <div class="alert alert-warning mb-4">
        <i class="bi bi-camera me-2"></i>
        Please show the summary to <strong>Treasury</strong> to continue the payment process.
      </div>
      <a href="index.php" class="btn btn-primary">Back to Home</a>
    </div>
  </div>
</div>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>