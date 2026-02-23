document.addEventListener('DOMContentLoaded', function () {
  // ✅ SHOW THE CARD (important if CSS starts it hidden)
  const card = document.querySelector('.client-card');
  if (card) {
    card.style.opacity = '1';
    card.style.transform = 'translateY(0)';
  }

  const address = document.getElementById('address');
  const charCount = document.getElementById('charCount');
  const form = document.getElementById('clientForm');
  const submitBtn = document.querySelector('.btn-submit');

  // Character counter
  if (address && charCount) {
    const updateCount = () => {
      let len = address.value.length;
      if (len > 255) {
        address.value = address.value.substring(0, 255);
        len = 255;
      }
      charCount.textContent = String(len);
    };
    address.addEventListener('input', updateCount);
    updateCount();
  }

  // Optional loading state
  if (form && submitBtn) {
    form.addEventListener('submit', function () {
      if (!form.checkValidity()) return;

      submitBtn.disabled = true;
      const txt = submitBtn.querySelector('.btn-text');
      const loading = submitBtn.querySelector('.btn-loading');
      if (txt) txt.classList.add('d-none');
      if (loading) loading.classList.remove('d-none');
    });
  }
});