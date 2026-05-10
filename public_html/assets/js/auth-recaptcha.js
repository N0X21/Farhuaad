(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var form = document.querySelector(".auth-form[data-recaptcha-site-key]");
    if (!form) return;
    var siteKey = form.getAttribute("data-recaptcha-site-key");
    if (!siteKey) return;

    form.addEventListener("submit", function (e) {
      var existing = document.getElementById("g-recaptcha-response");
      if (existing && existing.value) return;
      if (!window.grecaptcha || typeof window.grecaptcha.execute !== "function") return;
      e.preventDefault();
      window.grecaptcha.ready(function () {
        window.grecaptcha.execute(siteKey, { action: "login" }).then(function (token) {
          var input = document.getElementById("g-recaptcha-response");
          if (!input) {
            input = document.createElement("input");
            input.type = "hidden";
            input.name = "g-recaptcha-response";
            input.id = "g-recaptcha-response";
            form.appendChild(input);
          }
          input.value = token;
          form.submit();
        });
      });
    });
  });
})();
