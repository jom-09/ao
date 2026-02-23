<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['selected_service']) || $_SESSION['selected_service'] !== 'svc') {
    header("Location: index.php");
    exit();
}

$services = [];
$res = $conn->query("SELECT id, service_name, description, price FROM services WHERE status='active' ORDER BY service_name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) $services[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Select Services</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
  <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <h4 class="mb-1">Select Services</h4>
          <p class="text-muted mb-4">Choose one or more services to avail.</p>

          <form action="submit_service.php" method="POST">
            <?php if (empty($services)): ?>
              <div class="alert alert-secondary">No services available right now.</div>
            <?php else: ?>
              <div class="list-group mb-4">
                <?php foreach ($services as $svc): ?>
                  <label class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="me-3">
                      <input class="form-check-input me-2" type="checkbox" name="services[]" value="<?php echo (int)$svc['id']; ?>">
                      <strong><?php echo htmlspecialchars($svc['service_name']); ?></strong>
                      <div class="small text-muted"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></div>
                    </div>
                    <span class="badge bg-primary rounded-pill">
                      ₱<?php echo number_format((float)$svc['price'], 2); ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>

              <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-outline-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Next</button>
              </div>
            <?php endif; ?>
          </form>

        </div>
      </div>

    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>