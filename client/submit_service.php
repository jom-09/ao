<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['selected_service']) || $_SESSION['selected_service'] !== 'svc') {
    header("Location: index.php");
    exit();
}

$ids = $_POST['services'] ?? [];
if (!is_array($ids) || empty($ids)) {
    header("Location: select_service.php?error=Please+select+at+least+one+service");
    exit();
}

$ids = array_values(array_map('intval', $ids));

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare("SELECT id, service_name, price FROM services WHERE id IN ($placeholders) AND status='active'");
$types = str_repeat('i', count($ids));
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
$total = 0;
while ($r = $res->fetch_assoc()) {
    $items[] = $r;
    $total += (float)$r['price'];
}
$stmt->close();

if (empty($items)) {
    header("Location: select_service.php?error=No+valid+services+found");
    exit();
}

$_SESSION['svc_cart'] = [
    'ids' => array_column($items, 'id'),
    'total' => $total
];

unset($_SESSION['qr_items'], $_SESSION['qr_cert_labels'], $_SESSION['qr_prefill_cert_ids'], $_SESSION['qr_prefill_service_ids']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Confirm Services</title>
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
          <h4 class="mb-1">Confirm</h4>
          <p class="text-muted mb-3">Review your selected services.</p>

          <ul class="list-group mb-3">
            <?php foreach ($items as $it): ?>
              <li class="list-group-item d-flex justify-content-between">
                <span><?php echo htmlspecialchars($it['service_name']); ?></span>
                <strong>₱<?php echo number_format((float)$it['price'], 2); ?></strong>
              </li>
            <?php endforeach; ?>
            <li class="list-group-item d-flex justify-content-between">
              <span class="fw-bold">Total</span>
              <span class="fw-bold">₱<?php echo number_format($total, 2); ?></span>
            </li>
          </ul>

          <div class="d-flex justify-content-between">
            <a href="select_service.php" class="btn btn-outline-secondary">Back</a>
            <a href="finalize_service.php" class="btn btn-primary">Submit</a>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>