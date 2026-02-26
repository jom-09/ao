<?php
session_start();

$error = "";
if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scan QR | Certificate System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
  <link href="../assets/bootstrap/css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-7">

      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <h4 class="mb-0">Scan Appointment QR</h4>
              <div class="text-muted small">Auto-fill client info and certificates.</div>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-arrow-left"></i> Back
            </a>
          </div>

          <?php if($error): ?>
            <div class="alert alert-danger small mb-3">
              <i class="bi bi-exclamation-triangle me-1"></i><?= $error ?>
            </div>
          <?php endif; ?>

          <div id="reader" style="width:100%;"></div>

          <div class="alert alert-info small mt-3 mb-0">
            <i class="bi bi-camera-video me-1"></i>
            Allow camera permission when prompted.
          </div>

          <!-- Hidden form auto submit -->
          <form id="qrForm" method="POST" action="qr_receive.php">
            <input type="hidden" name="qr_payload" id="qr_payload">
          </form>

          <div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-outline-primary" id="restartBtn">
              <i class="bi bi-arrow-repeat me-1"></i> Scan Again
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
(function(){
  const payloadEl = document.getElementById('qr_payload');
  const form = document.getElementById('qrForm');
  const restartBtn = document.getElementById('restartBtn');

  let scanner;

  async function startScanner(){
    scanner = new Html5Qrcode("reader");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };

    try {
      const cameras = await Html5Qrcode.getCameras();
      if (!cameras || cameras.length === 0) {
        alert("No camera found.");
        return;
      }

      const backCam = cameras.find(c => /back|rear|environment/i.test(c.label));
      const camId = (backCam ? backCam.id : cameras[0].id);

      await scanner.start(
        camId,
        config,
        async (decodedText) => {
          try { await scanner.stop(); } catch(e){}
          payloadEl.value = decodedText;
          form.submit();
        },
        () => {}
      );
    } catch (err) {
      alert("Camera error: " + err);
    }
  }

  restartBtn.addEventListener('click', async () => {
    try { if (scanner) await scanner.stop(); } catch(e){}
    document.getElementById('reader').innerHTML = "";
    startScanner();
  });

  startScanner();
})();
</script>
</body>
</html>