(function () {
  function onReady(fn) {
    if (document.readyState !== "loading") fn();
    else document.addEventListener("DOMContentLoaded", fn);
  }
  onReady(function () {
    var burger = document.getElementById("header-burger");
    var panel = document.getElementById("header-mobile-panel");
    var scrim = document.getElementById("header-mobile-scrim");
    if (!burger || !panel || !scrim) return;
    function setOpen(open) {
      burger.setAttribute("aria-expanded", open ? "true" : "false");
      burger.classList.toggle("is-open", open);
      panel.hidden = !open;
      scrim.hidden = !open;
      document.body.classList.toggle("header-menu-open", open);
    }
    burger.addEventListener("click", function () {
      setOpen(!!panel.hidden);
    });
    scrim.addEventListener("click", function () {
      setOpen(false);
    });
    panel.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        setOpen(false);
      });
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") setOpen(false);
    });
    var resizeT = 0;
    window.addEventListener("resize", function () {
      window.clearTimeout(resizeT);
      resizeT = window.setTimeout(function () {
        if (window.innerWidth > 768) setOpen(false);
      }, 120);
    });
  });
})();
