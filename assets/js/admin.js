// ../assets/js/admin.js

(function () {
  "use strict";

  /* ===============================
     SIDEBAR TOGGLE (optional)
     - If you still have #sidebar, #mainContent, #menuToggle in your layout
     =============================== */
  document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");
    const main = document.getElementById("mainContent");
    const toggle = document.getElementById("menuToggle");

    if (toggle && sidebar) {
      toggle.addEventListener("click", () => {
        sidebar.classList.toggle("is-collapsed");
        if (main) main.classList.toggle("is-expanded");
      });
    }
  });

  /* ===============================
     CONFIRM LINKS (.js-confirm)
     usage:
     <a class="js-confirm" data-confirm="Delete this record?" href="...">Delete</a>
     =============================== */
  document.addEventListener("click", (e) => {
    const a = e.target.closest(".js-confirm");
    if (!a) return;

    const msg = a.getAttribute("data-confirm") || "Are you sure?";
    if (!confirm(msg)) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  /* ===============================
     BULK ADD ROWS (FAAS Bulk)
     Requires:
     - #bulkRows container
     - #addRowBtn button
     - row template has .bulk-row and .row-number, and remove button [data-role="remove-row"]
     - input/select names use [] arrays, like arp_no[], declared_owner[], row_barangay[], etc.
     =============================== */
  document.addEventListener("DOMContentLoaded", () => {
    const bulkRows = document.getElementById("bulkRows");
    const addBtn = document.getElementById("addRowBtn");

    if (!bulkRows || !addBtn) return;

    // Take the first row as template
    const firstRow = bulkRows.querySelector(".bulk-row");
    if (!firstRow) return;

    const templateHTML = firstRow.outerHTML;

    function renumber() {
      const rows = bulkRows.querySelectorAll(".bulk-row");
      rows.forEach((row, idx) => {
        const n = row.querySelector(".row-number");
        if (n) n.textContent = String(idx + 1);

        // If only one row left, disable remove button
        const removeBtn = row.querySelector('[data-role="remove-row"]');
        if (removeBtn) {
          removeBtn.disabled = rows.length === 1;
          removeBtn.style.opacity = rows.length === 1 ? "0.55" : "1";
          removeBtn.style.cursor = rows.length === 1 ? "not-allowed" : "pointer";
        }
      });
    }

    function clearInputs(row) {
      // clear all inputs/selects/textareas inside the row
      row.querySelectorAll("input, select, textarea").forEach((el) => {
        const tag = el.tagName.toLowerCase();
        if (tag === "select") {
          el.selectedIndex = 0;
        } else if (el.type === "checkbox" || el.type === "radio") {
          el.checked = false;
        } else {
          el.value = "";
        }
      });
    }

    addBtn.addEventListener("click", () => {
      const wrap = document.createElement("div");
      wrap.innerHTML = templateHTML;

      const newRow = wrap.firstElementChild;
      if (!newRow) return;

      clearInputs(newRow);
      bulkRows.appendChild(newRow);
      renumber();
    });

    bulkRows.addEventListener("click", (e) => {
      const btn = e.target.closest('[data-role="remove-row"]');
      if (!btn) return;

      const rows = bulkRows.querySelectorAll(".bulk-row");
      if (rows.length <= 1) return; // keep at least one row

      const row = btn.closest(".bulk-row");
      if (row) row.remove();
      renumber();
    });

    // init
    renumber();
  });

  /* ===============================
     DATATABLES AUTO-INIT (optional)
     - only if jQuery + DataTables is loaded and you want it
     - add class="js-dt" to tables you want to be datatables
     =============================== */
  document.addEventListener("DOMContentLoaded", () => {
    if (!window.jQuery) return;
    const $ = window.jQuery;

    if (!$.fn || !$.fn.DataTable) return;

    $(".js-dt").each(function () {
      // avoid double init
      if ($.fn.DataTable.isDataTable(this)) return;

      $(this).DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [],
      });
    });
  });
})();