/**
 * Site-styled toasts and dialogs (replaces alert / confirm / prompt).
 */
(function (global) {
  "use strict";

  var UI_DEFAULTS = {
    cancel: { en: "Cancel", ru: "Отмена" },
    ui_confirm: { en: "Confirm", ru: "Подтвердить" },
    ui_ok: { en: "OK", ru: "ОК" },
  };

  function uiT(key) {
    var o = global.FARHUAAD_I18N || {};
    if (o[key]) return o[key];
    var lang = global.FARHUAAD_LANG === "en" ? "en" : "ru";
    var d = UI_DEFAULTS[key];
    return d ? d[lang] : key;
  }

  function removeToasts() {
    document.querySelectorAll(".farhuaad-toast").forEach(function (n) {
      n.remove();
    });
  }

  /**
   * @param {string} message
   * @param {'success'|'error'|'info'} [type]
   */
  function farhuaadShowToast(message, type) {
    if (typeof document === "undefined") return;
    removeToasts();
    var toast = document.createElement("div");
    toast.className = "farhuaad-toast farhuaad-toast--" + (type === "error" ? "error" : type === "info" ? "info" : "success");
    toast.setAttribute("role", "status");
    toast.setAttribute("aria-live", "polite");
    toast.textContent = String(message || "");
    document.body.appendChild(toast);
    requestAnimationFrame(function () {
      toast.classList.add("is-visible");
    });
    var ms = type === "error" ? 3800 : 2600;
    global.setTimeout(function () {
      toast.classList.remove("is-visible");
      global.setTimeout(function () {
        toast.remove();
      }, 240);
    }, ms);
  }

  function ensureDialog() {
    var id = "farhuaad-dialog-scrim";
    var el = document.getElementById(id);
    if (el) return el;
    el = document.createElement("div");
    el.id = id;
    el.className = "farhuaad-dialog-scrim";
    el.setAttribute("hidden", "");
    el.innerHTML =
      '<div class="farhuaad-dialog-card" role="dialog" aria-modal="true" aria-labelledby="farhuaad-dialog-title">' +
      '<h2 class="farhuaad-dialog-title" id="farhuaad-dialog-title"></h2>' +
      '<p class="farhuaad-dialog-message" id="farhuaad-dialog-message"></p>' +
      '<div class="farhuaad-dialog-prompt-wrap" id="farhuaad-dialog-prompt-wrap" hidden>' +
      '<label class="label farhuaad-dialog-label" for="farhuaad-dialog-input" id="farhuaad-dialog-label"></label>' +
      '<input type="text" class="input farhuaad-dialog-input" id="farhuaad-dialog-input" autocomplete="off" />' +
      "</div>" +
      '<div class="farhuaad-dialog-actions">' +
      '<button type="button" class="btn btn-outline farhuaad-dialog-btn-cancel" data-farhuaad-dialog="cancel"></button>' +
      '<button type="button" class="btn btn-primary farhuaad-dialog-btn-ok" data-farhuaad-dialog="ok"></button>' +
      "</div>" +
      "</div>";
    document.body.appendChild(el);
    return el;
  }

  function closeDialog(scrim, onKey) {
    if (onKey) document.removeEventListener("keydown", onKey);
    scrim.classList.remove("is-visible");
    scrim.setAttribute("hidden", "");
    scrim.onclick = null;
    var input = scrim.querySelector("#farhuaad-dialog-input");
    if (input) {
      input.value = "";
      input.onkeydown = null;
    }
    var c = scrim.querySelector(".farhuaad-dialog-btn-cancel");
    var o = scrim.querySelector(".farhuaad-dialog-btn-ok");
    if (c) c.onclick = null;
    if (o) o.onclick = null;
  }

  /**
   * @param {{ message: string, title?: string, confirmText?: string, cancelText?: string, danger?: boolean }} opts
   * @returns {Promise<boolean>}
   */
  function farhuaadConfirm(opts) {
    opts = opts || {};
    var message = String(opts.message || "");
    return new Promise(function (resolve) {
      var scrim = ensureDialog();
      var titleEl = scrim.querySelector("#farhuaad-dialog-title");
      var msgEl = scrim.querySelector("#farhuaad-dialog-message");
      var promptWrap = scrim.querySelector("#farhuaad-dialog-prompt-wrap");
      var btnCancel = scrim.querySelector(".farhuaad-dialog-btn-cancel");
      var btnOk = scrim.querySelector(".farhuaad-dialog-btn-ok");
      if (!titleEl || !msgEl || !promptWrap || !btnCancel || !btnOk) {
        resolve(false);
        return;
      }
      promptWrap.hidden = true;
      titleEl.hidden = false;
      var confirmTitle = String(opts.title || "").trim();
      titleEl.textContent = confirmTitle || uiT("ui_confirm");
      msgEl.textContent = message;
      msgEl.hidden = !message;
      btnCancel.textContent = opts.cancelText || uiT("cancel");
      btnOk.textContent = opts.confirmText || uiT("ui_confirm");
      btnOk.className =
        "btn farhuaad-dialog-btn-ok " + (opts.danger ? "btn-danger" : "btn-primary");

      var done = function (v) {
        closeDialog(scrim, onKey);
        resolve(v);
      };

      var onKey = function (e) {
        if (e.key === "Escape") {
          e.preventDefault();
          done(false);
        }
      };

      var onCancel = function () {
        done(false);
      };
      var onOk = function () {
        done(true);
      };

      btnCancel.onclick = onCancel;
      btnOk.onclick = onOk;
      scrim.onclick = function (e) {
        if (e.target === scrim) onCancel();
      };
      document.addEventListener("keydown", onKey);
      scrim.removeAttribute("hidden");
      requestAnimationFrame(function () {
        scrim.classList.add("is-visible");
        btnOk.focus();
      });
    });
  }

  /**
   * @param {{ message: string, defaultValue?: string, label?: string, okText?: string, cancelText?: string }} opts
   * @returns {Promise<string|null>}
   */
  function farhuaadPrompt(opts) {
    opts = opts || {};
    var message = String(opts.message || "");
    return new Promise(function (resolve) {
      var scrim = ensureDialog();
      var titleEl = scrim.querySelector("#farhuaad-dialog-title");
      var msgEl = scrim.querySelector("#farhuaad-dialog-message");
      var promptWrap = scrim.querySelector("#farhuaad-dialog-prompt-wrap");
      var labelEl = scrim.querySelector("#farhuaad-dialog-label");
      var input = scrim.querySelector("#farhuaad-dialog-input");
      var btnCancel = scrim.querySelector(".farhuaad-dialog-btn-cancel");
      var btnOk = scrim.querySelector(".farhuaad-dialog-btn-ok");
      if (!titleEl || !msgEl || !promptWrap || !labelEl || !input || !btnCancel || !btnOk) {
        resolve(null);
        return;
      }
      promptWrap.hidden = false;
      var promptTitle = String(opts.title || "").trim();
      titleEl.textContent = promptTitle;
      titleEl.hidden = !promptTitle;
      msgEl.textContent = message;
      msgEl.hidden = !message;
      labelEl.textContent = opts.label || "";
      labelEl.hidden = !String(opts.label || "").trim();
      input.value = String(opts.defaultValue != null ? opts.defaultValue : "");
      btnCancel.textContent = opts.cancelText || uiT("cancel");
      btnOk.textContent = opts.okText || uiT("ui_ok");
      btnOk.className = "btn btn-primary farhuaad-dialog-btn-ok";

      var finished = false;
      var finish = function (v) {
        if (finished) return;
        finished = true;
        closeDialog(scrim, onKey);
        resolve(v);
      };

      var onKey = function (e) {
        if (e.key === "Escape") {
          e.preventDefault();
          finish(null);
        }
      };

      btnCancel.onclick = function () {
        finish(null);
      };
      btnOk.onclick = function () {
        finish(input.value);
      };
      input.onkeydown = function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          finish(input.value);
        }
      };
      scrim.onclick = function (e) {
        if (e.target === scrim) finish(null);
      };
      document.addEventListener("keydown", onKey);
      scrim.removeAttribute("hidden");
      requestAnimationFrame(function () {
        scrim.classList.add("is-visible");
        input.focus();
        input.select();
      });
    });
  }

  global.farhuaadShowToast = farhuaadShowToast;
  global.farhuaadConfirm = farhuaadConfirm;
  global.farhuaadPrompt = farhuaadPrompt;
})(typeof window !== "undefined" ? window : this);
