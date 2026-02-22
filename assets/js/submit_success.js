document.addEventListener('DOMContentLoaded', () => {

  /* ===============================
     PRINT BUTTON (CSP SAFE)
  ================================== */
  const btnPrint = document.getElementById('btnPrint');
  if (btnPrint) {
    btnPrint.addEventListener('click', () => {
      window.print();
    });
  }

  /* ===============================
     AUTO REDIRECT NOTICE
  ================================== */
  setTimeout(() => {

    const notice = document.createElement('div');
    notice.className =
      'redirect-notice alert alert-secondary small py-2 px-4 rounded-pill shadow';

    notice.innerHTML =
      '<i class="bi bi-clock me-1"></i>Redirecting to home in <span id="countdown">30</span>s...';

    document.body.appendChild(notice);

    let count = 30;

    const timer = setInterval(() => {
      count--;

      const el = document.getElementById('countdown');
      if (el) el.textContent = String(count);

      if (count <= 0) {
        clearInterval(timer);
        window.location.href = '../index.php';
      }
    }, 1000);

  }, 3000);

});