document.addEventListener('DOMContentLoaded', function () {
  const checkboxes = document.querySelectorAll('.cert-checkbox');
  const totalSpan = document.getElementById('totalAmount');
  const countSpan = document.getElementById('selectedCount');
  const submitBtn = document.getElementById('submitBtn');
  const certSearch = document.getElementById('certSearch');
  const filterChips = document.querySelectorAll('.filter-chip');
  const certItems = document.querySelectorAll('.cert-item');
  const certForm = document.getElementById('certForm');

  if (!certForm) return;

  const formatCurrency = (amount) => {
    const num = isNaN(amount) ? 0 : parseFloat(amount);
    return '₱' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
  };

  function updateSummary() {
    let total = 0;
    let count = 0;

    checkboxes.forEach(cb => {
      const item = cb.closest('.cert-item');
      if (cb.checked) {
        total += parseFloat(cb.dataset.price || 0);
        count++;
        item.classList.add('selected');
      } else {
        item.classList.remove('selected');
      }
    });

    totalSpan.textContent = formatCurrency(total);

    countSpan.textContent = count === 0 ? '0 items' : `${count} item${count > 1 ? 's' : ''}`;
    countSpan.className = count > 0 ? 'fw-semibold text-success' : 'fw-semibold text-muted';

    submitBtn.disabled = count === 0;
    submitBtn.style.opacity = count === 0 ? '0.6' : '1';
    submitBtn.style.cursor = count === 0 ? 'not-allowed' : 'pointer';
  }

  // Search
  if (certSearch) {
    certSearch.addEventListener('input', function () {
      const term = (this.value || '').toLowerCase();
      certItems.forEach(item => {
        const name = (item.dataset.name || '');
        item.style.display = name.includes(term) ? '' : 'none';
      });
    });
  }

  // Filter chips (placeholders - optional)
  filterChips.forEach(chip => {
    chip.addEventListener('click', function () {
      filterChips.forEach(c => c.classList.remove('active'));
      this.classList.add('active');

      const filter = this.dataset.filter;

      certItems.forEach(item => {
        if (filter === 'all') {
          item.style.display = '';
        } else if (filter === 'popular') {
          item.style.display = item.dataset.popular === 'true' ? '' : 'none';
        } else if (filter === 'new') {
          item.style.display = item.dataset.new === 'true' ? '' : 'none';
        }
      });
    });
  });

  // Toggle behavior
  checkboxes.forEach(cb => {
    const item = cb.closest('.cert-item');

    cb.addEventListener('change', updateSummary);

    if (item) {
      item.addEventListener('click', function (e) {
        // prevent double toggling if clicked directly on checkbox
        if (e.target && e.target.matches('input[type="checkbox"]')) return;

        e.preventDefault();
        cb.checked = !cb.checked;
        cb.dispatchEvent(new Event('change'));
      });
    }
  });

  // Submit
  certForm.addEventListener('submit', function (e) {
    const selected = Array.from(checkboxes).filter(cb => cb.checked);
    if (selected.length === 0) {
      e.preventDefault();
      return;
    }

    submitBtn.disabled = true;
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    if (btnText) btnText.classList.add('d-none');
    if (btnLoading) btnLoading.classList.remove('d-none');
  });

  // Init
  updateSummary();
});