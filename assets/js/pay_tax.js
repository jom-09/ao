document.addEventListener("DOMContentLoaded", () => {
  const tableEl = document.getElementById("propTable");
  if (!tableEl) return;

  const selected = new Map(); // key: arp

  const selectedJsonEl = document.getElementById("selected_json");
  const checkedCountEl = document.getElementById("checkedCount");
  const checkedTotalEl = document.getElementById("checkedTotal");
  const submitBtn = document.getElementById("submitBtn");
  const checkAllEl = document.getElementById("checkAll");

  function money(n) {
    const num = Number(n || 0);
    try {
      return new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(num);
    } catch {
      return "₱" + num.toFixed(2);
    }
  }

  function computeUI() {
    let total = 0;
    for (const v of selected.values()) total += Number(v.tax_due || 0);

    if (checkedCountEl) checkedCountEl.textContent = String(selected.size);
    if (checkedTotalEl) checkedTotalEl.textContent = money(total);

    if (selectedJsonEl) selectedJsonEl.value = JSON.stringify(Array.from(selected.values()));
    if (submitBtn) submitBtn.disabled = selected.size === 0;
  }

  function restoreChecks() {
    const checks = document.querySelectorAll(".js-pay-check");
    checks.forEach(cb => {
      const arp = cb.dataset.arp || "";
      cb.checked = selected.has(arp);
    });

    if (!checkAllEl) return;

    const visibleChecks = Array.from(document.querySelectorAll(".js-pay-check"));
    if (visibleChecks.length === 0) {
      checkAllEl.checked = false;
      checkAllEl.indeterminate = false;
      return;
    }

    const checkedVisible = visibleChecks.filter(c => c.checked).length;
    checkAllEl.checked = checkedVisible === visibleChecks.length;
    checkAllEl.indeterminate = checkedVisible > 0 && checkedVisible < visibleChecks.length;
  }

  // ✅ Single checkbox handler (event delegation)
  document.addEventListener("change", (e) => {
    const cb = e.target.closest(".js-pay-check");
    if (!cb) return;

    const owner = cb.dataset.owner || "";
    const arp = cb.dataset.arp || "";
    const av = Number(cb.dataset.av || 0);
    const tax_due = Number(cb.dataset.tax || 0);

    if (!arp) return;

    if (cb.checked) selected.set(arp, { owner, arp, av, tax_due });
    else selected.delete(arp);

    restoreChecks();
    computeUI();
  });

  // ✅ Check All (current page only)
  if (checkAllEl) {
    checkAllEl.addEventListener("change", () => {
      const visibleChecks = document.querySelectorAll(".js-pay-check");
      visibleChecks.forEach(cb => {
        const owner = cb.dataset.owner || "";
        const arp = cb.dataset.arp || "";
        const av = Number(cb.dataset.av || 0);
        const tax_due = Number(cb.dataset.tax || 0);

        cb.checked = checkAllEl.checked;

        if (!arp) return;
        if (cb.checked) selected.set(arp, { owner, arp, av, tax_due });
        else selected.delete(arp);
      });

      restoreChecks();
      computeUI();
    });
  }

  // ✅ Summary modal fill
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".js-summary");
    if (!btn) return;

    const owner = btn.dataset.owner || "";
    const arp = btn.dataset.arp || "";
    const av = Number(btn.dataset.av || 0);

    const basic = av * 0.01;
    const sef = av * 0.01;
    const total = av * 0.02;

    const mOwner = document.getElementById("mOwner");
    const mArp = document.getElementById("mArp");
    const mAv = document.getElementById("mAv");
    const mBasic = document.getElementById("mBasic");
    const mSef = document.getElementById("mSef");
    const mTotal = document.getElementById("mTotal");

    if (mOwner) mOwner.textContent = owner;
    if (mArp) mArp.textContent = arp;
    if (mAv) mAv.textContent = money(av);
    if (mBasic) mBasic.textContent = money(basic);
    if (mSef) mSef.textContent = money(sef);
    if (mTotal) mTotal.textContent = money(total);
  });

  // ✅ DataTables compatibility (auto detect)
  // If jQuery DataTables exists
  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) {
    const $ = window.jQuery;
    const dt = $("#propTable").DataTable({
      pageLength: 10,
      order: [[1, "asc"]]
    });

    $("#propTable").on("draw.dt", () => {
      restoreChecks();
      computeUI();
    });
  }
  // Else if Vanilla DataTable exists
  else if (window.DataTable) {
    const dt = new DataTable("#propTable", {
      pageLength: 10,
      order: [[1, "asc"]]
    });

    dt.on("draw", () => {
      restoreChecks();
      computeUI();
    });
  }

  // init
  restoreChecks();
  computeUI();
});