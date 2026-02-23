<?php
session_start();

// Optional: clear sessions
unset($_SESSION['client_info']);
unset($_SESSION['selected_service']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Thank You</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
  <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">

      <div class="card shadow-sm border-0">
        <div class="card-body p-4 text-center">
          <h4 class="fw-bold mb-2">Thank you!</h4>
          <p class="text-muted mb-4">Your Pay Tax request has been noted.</p>

          <a href="index.php" class="btn btn-primary">Back to Start</a>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>