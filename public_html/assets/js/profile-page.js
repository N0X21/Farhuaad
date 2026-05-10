(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var cfg = window.__FARHUAAD_PROFILE__ || {};

    if (typeof window.farhuaadInitProfileThemeToggle === "function") {
      window.farhuaadInitProfileThemeToggle();
    }

    var form = document.getElementById("profile-account-delete-form");
    if (form && cfg.deleteConfirm && cfg.deleteBtnLabel) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        function go(ok) {
          if (ok) form.submit();
        }
        if (typeof window.farhuaadConfirm === "function") {
          window
            .farhuaadConfirm({
              message: cfg.deleteConfirm,
              danger: true,
              confirmText: cfg.deleteBtnLabel,
            })
            .then(go);
        } else {
          go(window.confirm(cfg.deleteConfirm));
        }
      });
    }

    var btn = document.getElementById("profile-ref-copy");
    var inp = document.getElementById("profile-ref-url");
    if (!btn || !inp || !cfg.referralCopied) return;

    btn.addEventListener("click", function () {
      var url = inp.value;
      function done() {
        if (typeof window.farhuaadShowToast === "function") window.farhuaadShowToast(cfg.referralCopied, "success");
        else {
          try {
            window.alert(cfg.referralCopied);
          } catch (e2) {}
        }
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(fallback);
      } else {
        fallback();
      }
      function fallback() {
        inp.removeAttribute("readonly");
        inp.select();
        inp.setSelectionRange(0, 99999);
        try {
          document.execCommand("copy");
        } catch (e) {}
        inp.setAttribute("readonly", "readonly");
        done();
      }
    });
  });
})();
