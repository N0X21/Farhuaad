(function (w) {
  "use strict";
  var KEY = "farhuaad_theme";

  function getTheme() {
    var a = document.documentElement.getAttribute("data-theme");
    if (a === "light") return "light";
    if (a === "dark") return "dark";
    return "light";
  }

  function syncFavicon(t) {
    var L = w.FARHUAAD_FAVICON_LIGHT;
    var D = w.FARHUAAD_FAVICON_DARK;
    if (!L || !D) return;
    var el = document.getElementById("farhuaad-favicon");
    if (!el) {
      el = document.createElement("link");
      el.id = "farhuaad-favicon";
      el.rel = "icon";
      document.head.appendChild(el);
    }
    el.href = t === "light" ? L : D;
  }

  function setTheme(t) {
    if (t !== "light" && t !== "dark") return;
    document.documentElement.setAttribute("data-theme", t);
    try {
      localStorage.setItem(KEY, t);
    } catch (e) {}
    syncFavicon(t);
  }

  function syncSegments(root) {
    var cur = getTheme();
    root.querySelectorAll("[data-theme-value]").forEach(function (btn) {
      var v = btn.getAttribute("data-theme-value");
      var on = v === cur;
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });
  }

  function initProfileToggle() {
    var root = document.getElementById("profile-theme-switch");
    if (!root) return;
    syncSegments(root);
    root.addEventListener("click", function (e) {
      var btn = e.target && e.target.closest("[data-theme-value]");
      if (!btn || !root.contains(btn)) return;
      var v = btn.getAttribute("data-theme-value");
      if (v !== "light" && v !== "dark") return;
      setTheme(v);
      syncSegments(root);
    });
    w.addEventListener("storage", function (ev) {
      if (ev.key === KEY) syncSegments(root);
    });
  }

  w.farhuaadGetTheme = getTheme;
  w.farhuaadSetTheme = setTheme;
  w.farhuaadInitProfileThemeToggle = initProfileToggle;
})(window);
