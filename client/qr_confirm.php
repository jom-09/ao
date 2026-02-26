<?php
session_start();

$client = $_SESSION['client_info'] ?? null;
$items  = $_SESSION['qr_items'] ?? [];

if (!$client) {
  header("Location: scan.php?error=Session+expired");
  exit();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Confirm QR Data</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
  <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">

      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <h4 class="mb-1">Confirm Client Information</h4>
          <p class="text-muted mb-3">Data is loaded from the QR code.</p>

          <div class="mb-3">
            <div class="small text-muted">Full Name</div>
            <div class="fw-semibold">
              <?= h(trim($client['firstname']." ".$client['middlename']." ".$client['lastname'])) ?>
            </div>
          </div>

          <div class="mb-3">
            <div class="small text-muted">Address</div>
            <div class="fw-semibold"><?= h($client['address']) ?></div>
          </div>

          <div class="mb-3">
            <div class="small text-muted">Contact</div>
            <div class="fw-semibold"><?= h($client['cp_no']) ?></div>
          </div>

          <hr>

          <div class="mb-3">
            <div class="small text-muted mb-2">Availed Items</div>
            <?php if (empty($items)): ?>
              <div class="alert alert-warning small mb-0">No items found in QR.</div>
            <?php else: ?>
              <ul class="list-group">
                <?php foreach ($items as $it): ?>
                  <li class="list-group-item d-flex justify-content-between">
                    <span><?= h($it['label'] ?? $it['key'] ?? 'Item') ?></span>
                    <span class="badge bg-secondary"><?= h($it['type'] ?? '') ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <div class="d-flex justify-content-between">
            <a href="scan.php" class="btn btn-outline-secondary">Scan Again</a>

            <!-- Proceed to your normal flow -->
            <form method="POST" action="route_service.php" class="mb-0">
              <input type="hidden" name="firstname" value="<?= h($client['firstname']) ?>">
              <input type="hidden" name="middlename" value="<?= h($client['middlename']) ?>">
              <input type="hidden" name="lastname" value="<?= h($client['lastname']) ?>">
              <input type="hidden" name="address" value="<?= h($client['address']) ?>">
              <input type="hidden" name="cp_no" value="<?= h($client['cp_no']) ?>">

              <?php
              // Decide service type from items:
              // If any item.type == certificate => cert, else if service => svc
              $service = 'cert';
              foreach ($items as $it) {
                if (($it['type'] ?? '') === 'service') { $service = 'svc'; break; }
              }
              ?>
              <input type="hidden" name="service" value="<?= h($service) ?>">

              <button type="submit" class="btn btn-primary">
                Proceed <i class="bi bi-arrow-right ms-1"></i>
              </button>
            </form>

          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>