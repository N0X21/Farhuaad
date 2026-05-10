(function () {
  document.addEventListener("DOMContentLoaded", function () {
    function attachConfirm(form, msg, danger, confirmText) {
      if (!form || !msg) return;
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        function go(ok) {
          if (ok) form.submit();
        }
        if (typeof window.farhuaadConfirm === "function") {
          window.farhuaadConfirm({
            message: msg,
            danger: !!danger,
            confirmText: confirmText || "Подтвердить",
          }).then(go);
        } else {
          go(window.confirm(msg));
        }
      });
    }

    var resetForm = document.getElementById("admin-reset-balances-form");
    if (resetForm) {
      attachConfirm(resetForm, resetForm.getAttribute("data-confirm-reset"), true, "Сбросить");
    }

    document.querySelectorAll("form[data-confirm-message]").forEach(function (form) {
      var msg = form.getAttribute("data-confirm-message");
      var danger = form.getAttribute("data-confirm-danger") === "1";
      var confirmText = form.getAttribute("data-confirm-text") || "Подтвердить";
      attachConfirm(form, msg, danger, confirmText);
    });

    var selectAllBtn = document.getElementById("admin-select-all-disputes");
    var clearAllBtn = document.getElementById("admin-clear-all-disputes");
    var checkboxes = document.querySelectorAll(".admin-delete-checkbox");
    if (selectAllBtn && checkboxes.length) {
      selectAllBtn.addEventListener("click", function () {
        checkboxes.forEach(function (cb) { cb.checked = true; });
      });
    }
    if (clearAllBtn && checkboxes.length) {
      clearAllBtn.addEventListener("click", function () {
        checkboxes.forEach(function (cb) { cb.checked = false; });
      });
    }

    function bindListSearch(inputId, countId, emptyId) {
      var input = document.getElementById(inputId);
      if (!input) return;
      var listSelector = input.getAttribute("data-search-target");
      if (!listSelector) return;
      var list = document.querySelector(listSelector);
      if (!list) return;
      var rows = Array.prototype.slice.call(list.querySelectorAll(".admin-row"));
      var countNode = document.getElementById(countId);
      var emptyNode = document.getElementById(emptyId);

      function parseStrictIdQuery(rawQuery) {
        var q = String(rawQuery || "").trim().toLowerCase();
        if (!q) return null;

        var hashMatch = q.match(/^#\s*(\d+)$/);
        if (hashMatch) {
          return { mode: "num", value: hashMatch[1] };
        }

        var idMatch = q.match(/^(id|айди)\s*[:#]?\s*(\d+)$/);
        if (idMatch) {
          return { mode: "id", value: idMatch[2] };
        }

        return null;
      }

      function applySearch() {
        var q = String(input.value || "").trim().toLowerCase();
        var strict = parseStrictIdQuery(q);
        var visible = 0;
        rows.forEach(function (row) {
          var hay = String(row.getAttribute("data-search-text") || "").toLowerCase();
          var rowId = String(row.getAttribute("data-search-id") || "").trim();
          var rowNum = String(row.getAttribute("data-search-num") || "").trim();
          var ok = false;

          if (q === "") {
            ok = true;
          } else if (strict && strict.mode === "id") {
            ok = rowId === strict.value;
          } else if (strict && strict.mode === "num") {
            ok = rowNum === strict.value || rowId === strict.value;
          } else {
            ok = hay.indexOf(q) !== -1;
          }

          row.classList.toggle("is-hidden-by-search", !ok);
          if (ok) visible += 1;
        });
        if (countNode) {
          countNode.textContent = String(visible);
        }
        if (emptyNode) {
          emptyNode.classList.toggle("is-visible", visible === 0);
        }
      }

      input.addEventListener("input", applySearch);
      applySearch();
    }

    bindListSearch("admin-submissions-search", "admin-submissions-search-count", "admin-submissions-empty");
    bindListSearch("admin-active-disputes-search", "admin-active-disputes-search-count", "admin-active-disputes-empty");
  });
})();
