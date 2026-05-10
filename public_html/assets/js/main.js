/** Плейсхолдер для клиентских фильтров до ответа API; реальные рынки — в `generatedDisputes`. */
const markets = [];

let generatedDisputes = [];
let activeCategorySlug = "all";
let activeSortKey = "new";
let activeCreationSourceFilter = "all";
const DISPUTE_FALLBACK_IMAGE = "assets/pattern/1.svg";
const ACTUAL_DISPUTES_PREVIEW_LIMIT = 6; /* «Показать все»; на главной кнопки нет — лента со скроллом */
const HERO_STATS_REFRESH_INTERVAL_MS = 30000;
const PRESENCE_HEARTBEAT_INTERVAL_MS = 20000;
let showAllActualDisputes = false;
let disputeChatPollTimer = null;

function clearDisputeChatPoll() {
  if (disputeChatPollTimer !== null) {
    window.clearInterval(disputeChatPollTimer);
    disputeChatPollTimer = null;
  }
}

function getBasePath() {
  return (typeof window !== "undefined" && String(window.FARHUAAD_BASE || "").trim()) || "";
}

/** Токен из header.php для SameSite-cookie сессии + проверка на сервере (ставки, чат, кошелёк). */
function farhuaadCsrfToken() {
  const t = typeof window !== "undefined" ? window.FARHUAAD_CSRF : "";
  return typeof t === "string" ? t : "";
}

function farhuaadWithCsrfHeaders(headers) {
  const h = { ...(headers && typeof headers === "object" ? headers : {}) };
  const token = farhuaadCsrfToken();
  if (token) {
    h["X-CSRF-Token"] = token;
  }
  return h;
}

function getLocale() {
  return (typeof window !== "undefined" && window.FARHUAAD_LOCALE) || "ru-RU";
}

/** Синхронизация языка с PHP (cookie + явный query для /api/*). */
function farhuaadApiLangQuery() {
  const lang = typeof window !== "undefined" && window.FARHUAAD_LANG === "en" ? "en" : "ru";
  return `lang=${encodeURIComponent(lang)}`;
}

function farhuaadT(key, vars = {}) {
  const bag = (typeof window !== "undefined" && window.FARHUAAD_I18N) || {};
  let s = bag[key] || key;
  Object.keys(vars).forEach((k) => {
    s = s.split(`{${k}}`).join(String(vars[k]));
  });
  return s;
}

/** Подпись пачки токенов для чипов (100, 500, …) с учётом локали. */
function farhuaadTokenPackLabel(n) {
  return farhuaadT("token_pack_label", {
    n: Number(n).toLocaleString(getLocale()),
  });
}

/** При копировании текста со страницы в буфер добавляется строка с © Farhuaad (не мешает полям ввода). */
function initCopyAttribution() {
  document.addEventListener("copy", (e) => {
    const a = document.activeElement;
    if (
      a instanceof HTMLInputElement ||
      a instanceof HTMLTextAreaElement ||
      (a instanceof HTMLElement && a.isContentEditable)
    ) {
      return;
    }
    const text = window.getSelection()?.toString() ?? "";
    if (!String(text).trim()) return;
    const suffix = "\n\n" + farhuaadT("copy_attr_line");
    e.clipboardData?.setData("text/plain", text + suffix);
    e.preventDefault();
  });
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function categorySlugFromMarket(cat) {
  const n = normalize(cat);
  const map = {
    политика: "politics",
    politics: "politics",
    крипто: "crypto",
    crypto: "crypto",
    экономика: "economy",
    economy: "economy",
    спорт: "sport",
    sport: "sport",
    sports: "sport",
    технологии: "tech",
    technologies: "tech",
    technology: "tech",
    событие: "event",
    event: "event",
    general: "event",
  };
  return map[n] || n;
}

function resolveDisputeImage(url) {
  const raw = String(url || "").trim();
  const base = getBasePath();
  const fallback = base ? `${base}/assets/pattern/1.svg` : "assets/pattern/1.svg";

  if (!raw) return fallback;
  if (/^https?:\/\//i.test(raw) || raw.startsWith("data:")) return raw;

  if (raw.startsWith("/")) {
    return base ? `${base}${raw}` : raw;
  }
  if (raw.startsWith("assets/")) {
    return base ? `${base}/${raw}` : raw;
  }
  return raw;
}

function normalize(str) {
  return String(str || "").trim().toLowerCase();
}

function formatPool(value) {
  return `${Number(value || 0).toLocaleString(getLocale())} A`;
}

function formatCompactNumber(value) {
  const n = Number(value || 0);
  return n.toLocaleString(getLocale());
}

function formatMoney(value) {
  const n = Number(value || 0);
  return `${n.toLocaleString(getLocale(), { maximumFractionDigits: 0 })} A`;
}

function formatBalance(value) {
  return formatMoney(value);
}

function syncHeaderBalanceNodes(text) {
  document.querySelectorAll(".js-header-balance").forEach((el) => {
    el.textContent = text;
  });
}

function extractHost(url) {
  try {
    const parsed = new URL(url);
    return parsed.hostname.replace(/^www\./, "");
  } catch {
    return farhuaadT("source");
  }
}

function creationSourceLabel(value) {
  const key = String(value || "").trim().toLowerCase();
  return key === "manual" ? farhuaadT("manual") : farhuaadT("ai");
}

function openDisputePage(id) {
  const disputeId = Number(id || 0);
  if (disputeId <= 0) return;
  const base = (typeof window !== "undefined" && window.FARHUAAD_BASE) || "";
  if (typeof window !== "undefined" && window.FARHUAAD_AUTH !== true) {
    const next = encodeURIComponent(`pages/dispute.php?id=${disputeId}`);
    window.location.href = `${base}/pages/login.php?next=${next}`;
    return;
  }
  window.location.href = `${base}/pages/dispute.php?id=${disputeId}`;
}

function createMarketCard(market) {
  const card = document.createElement("article");
  card.className = "market-card";
  card.tabIndex = 0;
  card.dataset.disputeId = String(market.id || 0);

  const yesPercent = Number(market.yes_percent ?? Math.round((market.probability || 0.5) * 100));
  const noPercent = Number(market.no_percent ?? 100 - yesPercent);
  const isDynamic = Number(market.id || 0) > 0;
  const trendClass = yesPercent === noPercent
    ? "prob-neutral"
    : (yesPercent > noPercent ? "prob-up" : "prob-down");
  const poolLabel = market.total_pool != null ? formatPool(market.total_pool) : (market.volume || "—");
  const expiresLabel = market.expires_at
    ? new Date(market.expires_at).toLocaleDateString(getLocale())
    : (market.expiry || "—");
  const liquidityLabel = market.liquidity ? `${farhuaadT("liquidity_prefix")} ${market.liquidity}` : "";
  const sourceLabel = creationSourceLabel(market.creation_source);
  const descRaw = String(market.short_description || "").trim();
  const descHtml = escapeHtml(descRaw || farhuaadT("open_market_hint"));

  card.innerHTML = `
    <div class="market-image-wrapper">
      <div class="market-image">
        <img src="${resolveDisputeImage(market.image || DISPUTE_FALLBACK_IMAGE)}" alt="${market.title}" onerror="this.onerror=null;this.src='${resolveDisputeImage(DISPUTE_FALLBACK_IMAGE)}'" />
      </div>
    </div>
    <div class="market-title">${market.title}</div>
    <div class="market-meta">
      <span class="market-tag">${market.category || farhuaadT("event_fallback")}</span>
      <span class="market-tag">${farhuaadT("created_by")}: ${sourceLabel}</span>
      <span class="market-volume">${farhuaadT("pool")}: <strong>${poolLabel}</strong></span>
      <span class="dot-live" aria-label="${farhuaadT("active_market_aria")}"></span>
    </div>
    <p class="market-description">${descHtml}</p>
    <div class="market-body">
      <div class="probability">
        <span class="probability-label">${farhuaadT("market_prob")}</span>
        <span class="probability-value">${yesPercent.toFixed(1)}%</span>
        <span class="probability-trend ${trendClass}">
          ${farhuaadT("yes")} ${yesPercent.toFixed(1)}% / ${farhuaadT("no")} ${noPercent.toFixed(1)}%
        </span>
      </div>
      <div class="market-actions">
        <span class="side-chip yes-chip">${farhuaadT("yes")}</span>
        <span class="side-chip no-chip">${farhuaadT("no")}</span>
      </div>
    </div>
    <footer class="market-footer">
      <span class="expiry">${farhuaadT("closes")}: <strong>${expiresLabel}</strong></span>
      <span class="liquidity">${liquidityLabel}</span>
    </footer>
  `;

  if (isDynamic) {
    card.addEventListener("click", () => openDisputePage(market.id));
    card.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        openDisputePage(market.id);
      }
    });
  }
  return card;
}

function marketMatchesQuery(market, query) {
  const q = normalize(query);
  if (!q) return true;
  const hay = `${market.title || ""} ${market.category || ""} ${market.short_description || ""}`.toLowerCase();
  return hay.includes(q);
}

function applyDisputeFilters(items, query) {
  let list = (Array.isArray(items) ? items : []).filter((m) => marketMatchesQuery(m, query));
  if (activeCreationSourceFilter !== "all") {
    list = list.filter((m) => normalize(m.creation_source || "ai") === activeCreationSourceFilter);
  }
  if (activeCategorySlug && activeCategorySlug !== "all") {
    list = list.filter((m) => categorySlugFromMarket(m.category || "") === activeCategorySlug);
  }
  if (activeSortKey === "volume") {
    list = list.slice().sort((a, b) => Number(b.total_pool || 0) - Number(a.total_pool || 0));
  } else if (activeSortKey === "closing") {
    list = list.slice().sort((a, b) => {
      const ta = new Date(a.expires_at || 0).getTime();
      const tb = new Date(b.expires_at || 0).getTime();
      return ta - tb;
    });
  } else {
    list = list.slice().sort((a, b) => Number(b.id || 0) - Number(a.id || 0));
  }
  return list;
}

function renderMarkets(listData = markets) {
  const grid = document.querySelector(".markets-grid");
  const empty = document.getElementById("markets-empty");
  const count = document.getElementById("market-search-count");
  if (!grid) return;
  grid.replaceChildren();
  if (count) count.textContent = farhuaadT("events_count", { n: listData.length });
  if (empty) empty.hidden = listData.length !== 0;
  listData.forEach((market) => grid.appendChild(createMarketCard(market)));
}

function updateShowAllDisputesButton(total) {
  const btn = document.getElementById("show-all-disputes-btn");
  if (!btn) return;
  const max = Number(total || 0);
  if (max <= ACTUAL_DISPUTES_PREVIEW_LIMIT) {
    btn.hidden = true;
    return;
  }
  btn.hidden = false;
  btn.textContent = showAllActualDisputes ? farhuaadT("hide") : farhuaadT("show_all");
}

function renderSearchDisputes(listData = markets) {
  const container = document.getElementById("search-disputes-list");
  if (!container) return;
  container.replaceChildren();
  updateShowAllDisputesButton(listData.length);
  if (!listData.length) {
    const q = normalize(document.getElementById("market-search")?.value || "");
    const item = document.createElement("span");
    item.className = "dispute-chip search-disputes-empty-msg";
    item.textContent = q ? farhuaadT("no_disputes_query") : farhuaadT("disputes_soon");
    container.appendChild(item);
    return;
  }
  listData.forEach((market) => {
    const yesPercent = Math.max(0, Math.min(100, Number(market.yes_percent ?? 50)));
    const noPercent = Math.max(0, Math.min(100, Number(market.no_percent ?? 50)));
    const card = document.createElement("article");
    card.className = "dispute-chip dispute-chip-market";
    card.title = market.title;
    const image = document.createElement("img");
    image.className = "dispute-chip-image";
    image.src = resolveDisputeImage(market.image || DISPUTE_FALLBACK_IMAGE);
    image.alt = market.title;
    image.loading = "lazy";
    image.onerror = () => {
      image.onerror = null;
      image.src = resolveDisputeImage(DISPUTE_FALLBACK_IMAGE);
    };
    const text = document.createElement("span");
    text.className = "dispute-chip-text";
    text.textContent = market.title;
    const top = document.createElement("div");
    top.className = "dispute-chip-top";
    top.appendChild(image);
    top.appendChild(text);
    const desc = document.createElement("div");
    desc.className = "dispute-chip-description";
    desc.textContent = String(market.short_description || "").trim() || farhuaadT("open_market_hint");
    const stats = document.createElement("div");
    stats.className = "dispute-chip-stats";
    stats.innerHTML = `<span class="yes-chip">${farhuaadT("yes_pct", { v: yesPercent.toFixed(1) })}</span><span class="no-chip">${farhuaadT("no_pct", { v: noPercent.toFixed(1) })}</span>`;
    const source = document.createElement("div");
    source.className = "dispute-chip-description dispute-chip-description--meta";
    source.textContent = `${farhuaadT("created_by")}: ${creationSourceLabel(market.creation_source)}`;
    card.appendChild(top);
    card.appendChild(stats);
    card.appendChild(desc);
    card.appendChild(source);
    if (Number(market.id || 0) > 0) {
      card.style.cursor = "pointer";
      card.addEventListener("click", () => openDisputePage(market.id));
    }
    container.appendChild(card);
  });
}

function parseBetAmount(value) {
  return Number(String(value ?? "").replace(",", ".").trim());
}

function showToast(message, type = "success") {
  if (typeof document === "undefined") return;
  if (typeof window.farhuaadShowToast === "function") {
    window.farhuaadShowToast(message, type);
    return;
  }
  const toast = document.createElement("div");
  toast.className = "farhuaad-toast farhuaad-toast--" + (type === "error" ? "error" : "success");
  toast.textContent = message;
  document.body.appendChild(toast);
  requestAnimationFrame(() => toast.classList.add("is-visible"));
  window.setTimeout(() => {
    toast.classList.remove("is-visible");
    window.setTimeout(() => toast.remove(), 240);
  }, type === "error" ? 3800 : 2600);
}

function initAmountStepControls(root) {
  if (!(root instanceof HTMLElement)) return;
  const buttons = Array.from(root.querySelectorAll("[data-step-input][data-step-dir]"));
  buttons.forEach((btn) => {
    btn.addEventListener("click", () => {
      const inputId = String(btn.getAttribute("data-step-input") || "").trim();
      const dir = String(btn.getAttribute("data-step-dir") || "").trim();
      if (!inputId || (dir !== "up" && dir !== "down")) return;
      const input = root.querySelector(`#${inputId}`);
      if (!(input instanceof HTMLInputElement)) return;

      const step = Number(input.step || 1) || 1;
      const min = Number(input.min || 0);
      const current = parseBetAmount(input.value);
      const base = Number.isFinite(current) ? current : 0;
      const next = dir === "up" ? base + step : base - step;
      input.value = String(Math.max(min, next));
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.focus();
    });
  });
}

function openBetModal({ disputeId, side, title }) {
  if (typeof document === "undefined") return;
  const base = (typeof window !== "undefined" && window.FARHUAAD_BASE) || "";
  if (typeof window !== "undefined" && window.FARHUAAD_AUTH !== true) {
    const id = Number(disputeId || 0);
    const next =
      id > 0
        ? encodeURIComponent(`pages/dispute.php?id=${id}`)
        : encodeURIComponent("pages/dispute.php");
    window.location.href = `${base}/pages/login.php?next=${next}`;
    return;
  }
  const old = document.getElementById("bet-modal-overlay");
  if (old) old.remove();

  const sideLabel = side === "yes" ? farhuaadT("yes") : farhuaadT("no");
  const overlay = document.createElement("div");
  overlay.id = "bet-modal-overlay";
  overlay.className = "bet-modal-overlay";
  overlay.innerHTML = `
    <div class="bet-modal-card" role="dialog" aria-modal="true" aria-label="${farhuaadT("bet_modal_aria")}">
      <div class="bet-modal-title">${farhuaadT("bet_modal_title")}</div>
      <div class="bet-modal-subtitle">${title || farhuaadT("bet_modal_default_title")}</div>
      <div class="bet-modal-side">${farhuaadT("bet_modal_side")} <strong>${sideLabel}</strong></div>
      <p class="bet-token-rate-hint">${farhuaadT("bet_tokens_rate")}</p>
      <label class="label" for="bet-modal-amount">${farhuaadT("amount")}</label>
      <div class="bet-input-wrap">
        <input class="input bet-amount-input" id="bet-modal-amount" type="number" min="1" step="1" placeholder="${farhuaadT("amount_placeholder")}" />
        <span class="bet-currency">${farhuaadT("token_input_suffix")}</span>
        <div class="bet-stepper" aria-label="${farhuaadT("stepper_aria")}">
          <button type="button" class="bet-step-btn" data-step-input="bet-modal-amount" data-step-dir="up" aria-label="${farhuaadT("step_up_aria")}">▲</button>
          <button type="button" class="bet-step-btn" data-step-input="bet-modal-amount" data-step-dir="down" aria-label="${farhuaadT("step_down_aria")}">▼</button>
        </div>
      </div>
      <div class="bet-quick-row">
        <button type="button" class="chip chip-outline" data-modal-quick="100">${farhuaadTokenPackLabel(100)}</button>
        <button type="button" class="chip chip-outline" data-modal-quick="500">${farhuaadTokenPackLabel(500)}</button>
        <button type="button" class="chip chip-outline" data-modal-quick="1000">${farhuaadTokenPackLabel(1000)}</button>
      </div>
      <div class="bet-modal-actions">
        <button type="button" class="btn btn-ghost" id="bet-modal-cancel">${farhuaadT("cancel")}</button>
        <button type="button" class="btn btn-primary" id="bet-modal-submit">${farhuaadT("place_bet")}</button>
      </div>
    </div>
  `;
  document.body.appendChild(overlay);
  initAmountStepControls(overlay);

  const amountInput = overlay.querySelector("#bet-modal-amount");
  const submitBtn = overlay.querySelector("#bet-modal-submit");
  const cancelBtn = overlay.querySelector("#bet-modal-cancel");
  const quickButtons = Array.from(overlay.querySelectorAll("[data-modal-quick]"));
  const close = () => overlay.remove();

  if (amountInput instanceof HTMLInputElement) {
    amountInput.value = "100";
    amountInput.focus();
    amountInput.select();
  }

  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) close();
  });
  cancelBtn?.addEventListener("click", close);

  quickButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      const value = Number(btn.getAttribute("data-modal-quick") || 0);
      if (!(amountInput instanceof HTMLInputElement) || value <= 0) return;
      amountInput.value = String(value);
      amountInput.focus();
    });
  });

  submitBtn?.addEventListener("click", async () => {
    if (!(amountInput instanceof HTMLInputElement) || !(submitBtn instanceof HTMLButtonElement)) return;
    const amount = parseBetAmount(amountInput.value);
    if (!Number.isFinite(amount) || amount <= 0) {
      showToast(farhuaadT("enter_valid_amount"), "error");
      amountInput.focus();
      return;
    }
    submitBtn.disabled = true;
    submitBtn.textContent = farhuaadT("sending");
    const ok = await placeGeneratedBet(disputeId, side, amount, { skipSuccessToast: true });
    submitBtn.disabled = false;
    submitBtn.textContent = farhuaadT("place_bet");
    if (!ok) return;
    close();
    showToast(farhuaadT("bet_accepted"), "success");
  });
}

async function placeGeneratedBet(disputeId, side, amountInput, options = {}) {
  let amount = Number(amountInput);
  if (!Number.isFinite(amount) || amount <= 0) {
    let amountRaw = null;
    if (typeof window.farhuaadPrompt === "function") {
      amountRaw = await window.farhuaadPrompt({
        message: farhuaadT("prompt_bet_amount"),
        defaultValue: "100",
        label: farhuaadT("amount"),
        okText: farhuaadT("place_bet"),
      });
    } else {
      amountRaw = window.prompt(farhuaadT("prompt_bet_amount"), "100");
    }
    if (amountRaw === null) return false;
    amount = parseBetAmount(amountRaw);
  }
  if (!Number.isFinite(amount) || amount <= 0) {
    showToast(farhuaadT("enter_valid_amount"), "error");
    return false;
  }

  const base = (typeof window !== "undefined" && window.FARHUAAD_BASE) || "";
  const res = await fetch(`${base}/api/place_bet.php`, {
    method: "POST",
    headers: farhuaadWithCsrfHeaders({
      Accept: "application/json",
      "Content-Type": "application/json",
    }),
    cache: "no-store",
    body: JSON.stringify({
      dispute_id: disputeId,
      side,
      amount,
      csrf: farhuaadCsrfToken(),
    }),
  });

  const data = await res.json().catch(() => null);
  if (!res.ok || !data?.ok) {
    const errorCode = String(data?.error || "UNKNOWN_ERROR");
    if (errorCode === "UNAUTHORIZED") {
      showToast(farhuaadT("err_login_required"), "error");
      return false;
    }
    if (errorCode === "INSUFFICIENT_BALANCE") {
      showToast(farhuaadT("err_insufficient_balance"), "error");
      return false;
    }
    if (errorCode === "DISPUTE_NOT_ACTIVE" || errorCode === "DISPUTE_EXPIRED") {
      showToast(farhuaadT("err_dispute_closed"), "error");
      await loadDailyDisputes();
      return false;
    }
    showToast(farhuaadT("err_bet_failed", { code: errorCode }), "error");
    return false;
  }

  if (!options.skipSuccessToast) {
    showToast(farhuaadT("bet_accepted"), "success");
  }
  const pool = data?.result?.pool;
  if (pool && Number(disputeId) > 0) {
    const totalPool = Number(pool.total_pool || 0);
    const yesPool = Number(pool.yes_pool || 0);
    const noPool = Number(pool.no_pool || 0);
    const yesPercent = totalPool > 0 ? Math.max(0, Math.min(100, (yesPool / totalPool) * 100)) : 50;
    const noPercent = totalPool > 0 ? Math.max(0, Math.min(100, (noPool / totalPool) * 100)) : 50;
    generatedDisputes = generatedDisputes.map((item) => {
      if (Number(item?.id || 0) !== Number(disputeId)) return item;
      return {
        ...item,
        yes_pool: yesPool,
        no_pool: noPool,
        total_pool: totalPool,
        yes_percent: yesPercent,
        no_percent: noPercent,
      };
    });
  }
  if (data?.result?.balance_after != null) {
    const balanceText = formatBalance(data.result.balance_after);
    const profileBalanceEl = document.querySelector(".profile-balance");
    syncHeaderBalanceNodes(balanceText);
    if (profileBalanceEl) profileBalanceEl.textContent = balanceText;
  }

  const query = document.getElementById("market-search")?.value || "";
  const source = generatedDisputes.length ? applyDisputeFilters(generatedDisputes, query) : applyDisputeFilters(markets, query);
  renderMarkets(source);
  if (generatedDisputes.length) {
    renderGeneratedDisputes(source, query);
  } else {
    renderSearchDisputes(source);
  }

  void loadDailyDisputes();
  return true;
}

function renderGeneratedDisputes(disputes, query = "") {
  const container = document.getElementById("search-disputes-list");
  if (!container) return;

  const q = normalize(query);
  const filtered = (Array.isArray(disputes) ? disputes : []).filter((item) => {
    const title = String(item?.title || "").toLowerCase();
    return !q || title.includes(q);
  });

  container.replaceChildren();
  updateShowAllDisputesButton(filtered.length);

  if (!filtered.length) {
    const item = document.createElement("span");
    item.className = "dispute-chip search-disputes-empty-msg";
    item.textContent = q ? farhuaadT("no_disputes_query") : farhuaadT("disputes_soon");
    container.appendChild(item);
    return;
  }

  filtered.forEach((item) => {
    const card = document.createElement("article");
    card.className = "dispute-chip dispute-chip-market";
    card.title = item.title;

    const image = document.createElement("img");
    image.className = "dispute-chip-image";
    image.src = resolveDisputeImage(item.image || DISPUTE_FALLBACK_IMAGE);
    image.alt = item.title;
    image.loading = "lazy";
    image.onerror = () => {
      image.onerror = null;
      image.src = resolveDisputeImage(DISPUTE_FALLBACK_IMAGE);
    };

    const text = document.createElement("span");
    text.className = "dispute-chip-text";
    text.textContent = item.title;

    const top = document.createElement("div");
    top.className = "dispute-chip-top";
    top.appendChild(image);
    top.appendChild(text);

    const yesPercent = Number(item?.yes_percent ?? 50);
    const noPercent = Number(item?.no_percent ?? 50);
    const clampedYes = Math.max(0, Math.min(100, yesPercent));
    const clampedNo = Math.max(0, Math.min(100, noPercent));

    const stats = document.createElement("div");
    stats.className = "dispute-chip-stats";
    stats.innerHTML = `
      <span class="yes-chip">${farhuaadT("yes_pct", { v: clampedYes.toFixed(1) })}</span>
      <span class="no-chip">${farhuaadT("no_pct", { v: clampedNo.toFixed(1) })}</span>
    `;

    const actions = document.createElement("div");
    actions.className = "dispute-chip-actions";

    const yesBtn = document.createElement("button");
    yesBtn.type = "button";
    yesBtn.className = "btn-mini btn-mini-yes";
    yesBtn.textContent = farhuaadT("bet_yes");
    yesBtn.disabled = Number(item.id || 0) <= 0;
    yesBtn.addEventListener("click", () => {
      openBetModal({
        disputeId: Number(item.id || 0),
        side: "yes",
        title: item.title,
      });
    });

    const noBtn = document.createElement("button");
    noBtn.type = "button";
    noBtn.className = "btn-mini btn-mini-no";
    noBtn.textContent = farhuaadT("bet_no");
    noBtn.disabled = Number(item.id || 0) <= 0;
    noBtn.addEventListener("click", () => {
      openBetModal({
        disputeId: Number(item.id || 0),
        side: "no",
        title: item.title,
      });
    });

    actions.appendChild(yesBtn);
    actions.appendChild(noBtn);

    const desc = document.createElement("div");
    desc.className = "dispute-chip-description";
    desc.textContent = String(item.short_description || "").trim() || farhuaadT("open_market_hint");
    const origin = document.createElement("div");
    origin.className = "dispute-chip-description dispute-chip-description--meta";
    origin.textContent = `${farhuaadT("created_by")}: ${creationSourceLabel(item.creation_source)}`;

    const sources = document.createElement("div");
    sources.className = "dispute-chip-sources";
    const sourceLinks = Array.isArray(item.source_links) ? item.source_links.slice(0, 2) : [];
    if (sourceLinks.length) {
      sourceLinks.forEach((url, idx) => {
        const a = document.createElement("a");
        a.className = "dispute-source-link";
        a.href = url;
        a.target = "_blank";
        a.rel = "noopener noreferrer";
        a.textContent = extractHost(url);
        a.addEventListener("click", (e) => e.stopPropagation());
        sources.appendChild(a);
      });
    } else {
      const span = document.createElement("span");
      span.className = "dispute-source-empty";
      span.textContent = farhuaadT("no_sources");
      sources.appendChild(span);
    }

    card.appendChild(top);
    card.appendChild(stats);
    card.appendChild(desc);
    card.appendChild(origin);
    card.appendChild(sources);
    card.appendChild(actions);
    if (Number(item.id || 0) > 0) {
      card.style.cursor = "pointer";
      card.addEventListener("click", (e) => {
        const target = e.target;
        if (target instanceof HTMLElement && target.closest("button, a")) {
          return;
        }
        openDisputePage(item.id);
      });
    }
    container.appendChild(card);
  });
}

function initShowAllDisputesToggle() {
  const btn = document.getElementById("show-all-disputes-btn");
  if (!btn) return;
  btn.addEventListener("click", () => {
    showAllActualDisputes = !showAllActualDisputes;
    const input = document.getElementById("market-search");
    const query = input?.value || "";
    const source = generatedDisputes.length ? applyDisputeFilters(generatedDisputes, query) : applyDisputeFilters(markets, query);
    if (generatedDisputes.length) {
      renderGeneratedDisputes(source, query);
    } else {
      renderSearchDisputes(source);
    }
  });
}

function needsHomeMarketsUi() {
  return !!(
    document.querySelector(".markets-grid") ||
    document.getElementById("search-disputes-list") ||
    document.getElementById("market-search")
  );
}

function pageWantsPlatformStatsPolling() {
  if (
    document.getElementById("hero-total-volume") ||
    document.getElementById("hero-online-users") ||
    document.getElementById("hero-total-disputes")
  ) {
    return true;
  }
  if (document.querySelector(".profile-balance")) return true;
  if (typeof window !== "undefined" && window.FARHUAAD_AUTH === true && document.querySelector(".js-header-balance")) {
    return true;
  }
  return false;
}

async function loadDailyDisputes() {
  if (!needsHomeMarketsUi()) return;
  const base = (typeof window !== "undefined" && window.FARHUAAD_BASE) || "";

  try {
    const res = await fetch(`${base}/api/daily_disputes.php?${farhuaadApiLangQuery()}`, {
      headers: { Accept: "application/json" },
      credentials: "same-origin",
      cache: "no-store",
    });
    if (!res.ok) throw new Error("DISPUTES_HTTP_ERROR");
    const data = await res.json();
    const items = Array.isArray(data?.items) ? data.items : [];
    generatedDisputes = items
      .map((item) => ({
        id: Number(item?.id || 0),
        title: String(item?.title || "").trim(),
        image: String(item?.image || "").trim(),
        short_description: String(item?.short_description || "").trim(),
        category: String(item?.category || farhuaadT("event_fallback")).trim(),
        source_links: Array.isArray(item?.source_links) ? item.source_links : [],
        creation_source: String(item?.creation_source || "ai").trim(),
        total_pool: Number(item?.total_pool || 0),
        expires_at: String(item?.expires_at || "").trim(),
        yes_percent: Number(item?.yes_percent ?? 50),
        no_percent: Number(item?.no_percent ?? 50),
      }))
      .filter((item) => item.title);
  } catch (e) {
    generatedDisputes = [];
  }

  const searchInput = document.getElementById("market-search");
  const query = searchInput?.value || "";
  const source = generatedDisputes.length ? applyDisputeFilters(generatedDisputes, query) : applyDisputeFilters(markets, query);
  renderMarkets(source);
  if (generatedDisputes.length) {
    renderGeneratedDisputes(source, query);
  } else {
    renderSearchDisputes(source);
  }
}

async function loadHeroStats() {
  const base = (typeof window !== "undefined" && window.FARHUAAD_BASE) || "";
  const volumeEl = document.getElementById("hero-total-volume");
  const onlineEl = document.getElementById("hero-online-users");
  const totalDisputesEl = document.getElementById("hero-total-disputes");
  const profileBalanceEl = document.querySelector(".profile-balance");
  const hasHeaderBalance = document.querySelector(".js-header-balance");
  if (!volumeEl && !onlineEl && !totalDisputesEl && !hasHeaderBalance && !profileBalanceEl) return;

  try {
    const res = await fetch(`${base}/api/platform_stats.php`, {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    if (!res.ok) throw new Error("PLATFORM_STATS_HTTP_ERROR");
    const data = await res.json();
    const stats = data?.stats || {};
    if (volumeEl) {
      volumeEl.textContent = formatMoney(stats.total_volume || 0);
    }
    if (onlineEl) {
      onlineEl.textContent = formatCompactNumber(stats.online_users || 0);
    }
    if (totalDisputesEl) {
      totalDisputesEl.textContent = formatCompactNumber(stats.total_disputes || 0);
    }
    const user = data?.user || null;
    if (user && user.authorized === true && user.balance != null) {
      const balanceText = formatBalance(user.balance);
      syncHeaderBalanceNodes(balanceText);
      if (profileBalanceEl) {
        profileBalanceEl.textContent = balanceText;
      }
    }
  } catch (e) {
    // Avoid stale "frozen" online counter on repeated API errors.
    if (onlineEl) {
      onlineEl.textContent = "0";
    }
  }
}

async function pingPresence() {
  if (typeof window === "undefined" || window.FARHUAAD_AUTH !== true) return;
  const base = window.FARHUAAD_BASE || "";
  try {
    await fetch(`${base}/api/presence_ping.php`, {
      method: "POST",
      headers: { Accept: "application/json" },
      cache: "no-store",
      keepalive: true,
    });
  } catch (e) {
    // Presence ping is non-critical.
  }
}

function startPresenceHeartbeat() {
  if (typeof window === "undefined" || window.FARHUAAD_AUTH !== true) return;
  void pingPresence();
  window.setInterval(() => {
    void pingPresence();
  }, PRESENCE_HEARTBEAT_INTERVAL_MS);

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      void pingPresence();
    }
  });
}

function syncHeaderCategoryFilterOutline() {
  if (typeof document === "undefined" || !document.querySelector("[data-cat-slug]")) return;
  const on = String(activeCategorySlug || "all").toLowerCase() !== "all";
  document.body.classList.toggle("market-category-filter-on", on);
}

function initMarketSearch() {
  const input = document.getElementById("market-search");
  const clear = document.getElementById("market-search-clear");
  if (!input) return;

  const apply = () => {
    const q = input.value || "";
    const source = generatedDisputes.length ? generatedDisputes : markets;
    const filtered = applyDisputeFilters(source, q);
    renderMarkets(filtered);
    if (generatedDisputes.length) {
      renderGeneratedDisputes(filtered, q);
    } else {
      renderSearchDisputes(filtered);
    }
  };

  input.addEventListener("input", apply);
  clear?.addEventListener("click", () => {
    input.value = "";
    input.focus();
    const source = generatedDisputes.length ? generatedDisputes : markets;
    renderMarkets(source);
    if (generatedDisputes.length) {
      renderGeneratedDisputes(source);
    } else {
      renderSearchDisputes(source);
    }
  });

  input.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      input.value = "";
      const source = generatedDisputes.length ? generatedDisputes : markets;
      renderMarkets(source);
      if (generatedDisputes.length) {
        renderGeneratedDisputes(source);
      } else {
        renderSearchDisputes(source);
      }
    }
  });

  const categoryButtons = Array.from(document.querySelectorAll("[data-cat-slug]"));
  categoryButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      activeCategorySlug = String(btn.getAttribute("data-cat-slug") || "all");
      categoryButtons.forEach((b) => {
        b.classList.remove("chip-active", "chip-filled");
        b.classList.add("chip-outline");
      });
      btn.classList.remove("chip-outline");
      btn.classList.add("chip-active", "chip-filled");
      apply();
      syncHeaderCategoryFilterOutline();
    });
  });

  const sortButtons = Array.from(document.querySelectorAll("[data-sort-key]"));
  sortButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      activeSortKey = String(btn.getAttribute("data-sort-key") || "new");
      sortButtons.forEach((b) => {
        b.classList.remove("chip-active", "chip-filled");
        b.classList.add("chip-outline");
      });
      btn.classList.remove("chip-outline");
      btn.classList.add("chip-active", "chip-filled");
      apply();
    });
  });

  const sourceButtons = Array.from(document.querySelectorAll("[data-creation-source]"));
  sourceButtons.forEach((btn) => {
    const source = String(btn.getAttribute("data-creation-source") || "all").trim();
    if (!["all", "ai", "manual"].includes(source)) return;
    btn.addEventListener("click", () => {
      activeCreationSourceFilter = source;
      sourceButtons.forEach((b) => b.classList.remove("chip-active", "chip-filled"));
      sourceButtons.forEach((b) => b.classList.add("chip-outline"));
      btn.classList.remove("chip-outline");
      btn.classList.add("chip-active", "chip-filled");
      apply();
    });
  });

  syncHeaderCategoryFilterOutline();
}

function initInstructionCarousel() {
  const carousel = document.getElementById("instruction-carousel");
  if (!carousel) return;

  const slides = Array.from(carousel.querySelectorAll(".instruction-slide"));
  if (!slides.length) return;

  const prevBtn = document.getElementById("instruction-prev");
  const nextBtn = document.getElementById("instruction-next");
  const dotsWrap = document.getElementById("instruction-dots");
  const dots = [];
  let currentIndex = 0;

  const setSlide = (index) => {
    currentIndex = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      slide.classList.toggle("is-active", i === currentIndex);
    });
    dots.forEach((dot, i) => {
      dot.classList.toggle("is-active", i === currentIndex);
    });
  };

  if (dotsWrap) {
    dotsWrap.replaceChildren();
    slides.forEach((_, index) => {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.className = "instruction-dot";
      dot.setAttribute("aria-label", farhuaadT("slide_aria", { n: String(index + 1) }));
      dot.addEventListener("click", () => setSlide(index));
      dotsWrap.appendChild(dot);
      dots.push(dot);
    });
  }

  prevBtn?.addEventListener("click", () => setSlide(currentIndex - 1));
  nextBtn?.addEventListener("click", () => setSlide(currentIndex + 1));

  carousel.addEventListener("keydown", (e) => {
    if (e.key === "ArrowLeft") setSlide(currentIndex - 1);
    if (e.key === "ArrowRight") setSlide(currentIndex + 1);
  });

  setSlide(0);
}

function initHeroActionButtons() {
  const startTradingBtn = document.querySelector('[data-hero-action="start-trading"]');
  const exploreMarketsBtn = document.querySelector('[data-hero-action="explore-markets"]');
  if (!startTradingBtn && !exploreMarketsBtn) return;

  const base = (typeof window !== "undefined" && window.FARHUAAD_BASE) || "";
  const focusSearch = () => {
    const input = document.getElementById("market-search");
    if (input) {
      input.scrollIntoView({ behavior: "smooth", block: "center" });
      setTimeout(() => input.focus(), 220);
      return true;
    }
    return false;
  };

  startTradingBtn?.addEventListener("click", () => {
    const hasMarkets = document.querySelector(".markets-grid .market-card");
    if (hasMarkets && focusSearch()) {
      return;
    }
    window.location.href = `${base}/pages/login.php`;
  });

  exploreMarketsBtn?.addEventListener("click", () => {
    if (focusSearch()) {
      return;
    }
    window.location.href = `${base}/`;
  });
}

function initPortfolioPageInteractions() {
  const filterButtons = Array.from(document.querySelectorAll("[data-portfolio-filter]"));
  const rows = Array.from(document.querySelectorAll(".table-portfolio .table-row[data-portfolio-status]"));
  const emptyFilter = document.querySelector(".portfolio-filter-empty");
  if (!filterButtons.length) {
    return;
  }

  const applyPortfolioFilter = (filter) => {
    const f = filter === "open" || filter === "closed" ? filter : "all";
    let visible = 0;
    rows.forEach((row) => {
      const st = String(row.getAttribute("data-portfolio-status") || "").trim();
      const show = f === "all" || st === f;
      row.style.display = show ? "" : "none";
      if (show) visible += 1;
    });
    if (emptyFilter instanceof HTMLElement) {
      const hideEmpty = visible > 0;
      emptyFilter.hidden = hideEmpty;
      emptyFilter.style.display = hideEmpty ? "none" : "";
    }
  };

  filterButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      const filter = String(btn.getAttribute("data-portfolio-filter") || "all");
      filterButtons.forEach((b) => {
        b.classList.remove("chip-filled", "chip-active");
        b.classList.add("chip-outline");
      });
      btn.classList.remove("chip-outline");
      btn.classList.add("chip-filled", "chip-active");
      applyPortfolioFilter(filter);
    });
  });

  rows.forEach((row) => {
    const id = Number(row.getAttribute("data-dispute-id") || 0);
    if (id <= 0) return;
    const go = () => openDisputePage(id);
    row.addEventListener("click", go);
    row.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        go();
      }
    });
  });
}

function initStatsPageInteractions() {
  const periodButtons = Array.from(document.querySelectorAll("[data-stats-period]"));
  const categoryButtons = Array.from(document.querySelectorAll("[data-stats-category]"));
  const rows = Array.from(document.querySelectorAll(".table .table-row[data-market-category]"));
  if (!periodButtons.length && !categoryButtons.length) return;

  const applyCategoryFilter = (category) => {
    rows.forEach((row) => {
      const rowCategory = String(row.getAttribute("data-market-category") || "").trim();
      const visible = category === "Все" || rowCategory === category;
      row.style.display = visible ? "" : "none";
    });
  };

  periodButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      periodButtons.forEach((b) => b.classList.remove("chip-filled", "chip-active"));
      periodButtons.forEach((b) => b.classList.add("chip-outline"));
      btn.classList.remove("chip-outline");
      btn.classList.add("chip-filled", "chip-active");
    });
  });

  categoryButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      const category = String(btn.getAttribute("data-stats-category") || "Все");
      categoryButtons.forEach((b) => b.classList.remove("chip-filled", "chip-active"));
      categoryButtons.forEach((b) => b.classList.add("chip-outline"));
      btn.classList.remove("chip-outline");
      btn.classList.add("chip-filled", "chip-active");
      applyCategoryFilter(category);
    });
  });

  rows.forEach((row) => {
    const id = Number(row.getAttribute("data-dispute-id") || 0);
    if (id <= 0) return;
    row.style.cursor = "pointer";
    row.addEventListener("click", () => openDisputePage(id));
  });
}

async function connectWallet() {
  if (!window.ethereum || !window.ethereum.request) {
    throw new Error("NO_WALLET");
  }

  const accounts = await window.ethereum.request({
    method: "eth_requestAccounts",
  });

  const address = Array.isArray(accounts) ? accounts[0] : null;
  if (!address) {
    throw new Error("NO_ACCOUNTS");
  }

  return address;
}

async function walletAuth() {
  const base = (typeof window !== "undefined" && window.FARHUAAD_BASE) || "";
  const address = await connectWallet();
  const body = new URLSearchParams({
    walletAddress: address,
    csrf: farhuaadCsrfToken(),
  });

  const res = await fetch(`${base}/pages/wallet_auth.php`, {
    method: "POST",
    headers: farhuaadWithCsrfHeaders({
      "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
    }),
    body: body.toString(),
  });

  if (!res.ok) {
    throw new Error("AUTH_FAILED");
  }

  const data = await res.json().catch(() => null);
  if (!data || data.ok !== true) {
    throw new Error("AUTH_FAILED");
  }
}

function initWalletButtons() {
  const buttons = document.querySelectorAll("[data-wallet-auth]");
  if (!buttons.length) return;

  buttons.forEach((btn) => {
    const originalText = btn.textContent || farhuaadT("wallet_login_default");
    btn.addEventListener("click", async () => {
      try {
        btn.disabled = true;
        btn.textContent = farhuaadT("wallet_connecting");
        await walletAuth();
        window.location.reload();
      } catch (e) {
        btn.disabled = false;
        btn.textContent = originalText;
        showToast(farhuaadT("wallet_connect_fail"), "error");
      }
    });
  });
}

function initDisputeBetPanel(disputeId) {
  const panel = document.getElementById("dispute-bet-panel");
  if (!panel) return;
  initAmountStepControls(panel);

  const sideButtons = Array.from(panel.querySelectorAll("[data-bet-side]"));
  const amountInput = panel.querySelector("#bet-amount-input");
  const quickButtons = Array.from(panel.querySelectorAll("[data-bet-quick]"));
  const submitBtn = panel.querySelector("#bet-submit-btn");
  const selectedSideLabel = panel.querySelector("#bet-selected-side");
  const footerHint = panel.querySelector("#bet-footer-hint");
  let selectedSide = "yes";

  const setHint = (text) => {
    if (footerHint) footerHint.textContent = text;
  };

  const updateSideUi = () => {
    sideButtons.forEach((btn) => {
      const side = String(btn.getAttribute("data-bet-side") || "");
      btn.classList.toggle("is-active", side === selectedSide);
    });
    if (selectedSideLabel) {
      selectedSideLabel.textContent = selectedSide === "yes" ? farhuaadT("yes") : farhuaadT("no");
    }
    setHint(
      farhuaadT("bet_hint_side", {
        side: selectedSide === "yes" ? farhuaadT("yes") : farhuaadT("no"),
      })
    );
  };

  sideButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      selectedSide = String(btn.getAttribute("data-bet-side") || "yes");
      updateSideUi();
    });
  });

  quickButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      const value = Number(btn.getAttribute("data-bet-quick") || 0);
      if (!(amountInput instanceof HTMLInputElement)) return;
      if (!Number.isFinite(value) || value <= 0) return;
      amountInput.value = String(value);
      amountInput.focus();
      amountInput.dispatchEvent(new Event("input", { bubbles: true }));
    });
  });

  submitBtn?.addEventListener("click", async () => {
    if (!(amountInput instanceof HTMLInputElement)) return;
    const amount = parseBetAmount(amountInput.value);
    if (!Number.isFinite(amount) || amount <= 0) {
      setHint(farhuaadT("enter_amount_gt_zero"));
      amountInput.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = farhuaadT("sending");
    const ok = await placeGeneratedBet(disputeId, selectedSide, amount);
    submitBtn.disabled = false;
    submitBtn.textContent = farhuaadT("place_bet");
    if (!ok) return;

    amountInput.value = "";
    setHint(
      farhuaadT("bet_accepted_detail", {
        amount: Number(amount).toLocaleString(getLocale()),
        side: selectedSide === "yes" ? farhuaadT("yes") : farhuaadT("no"),
      })
    );
    await renderDisputeDetailsPage();
  });

  updateSideUi();
}

function disputeChatErrorMessage(code, httpStatus) {
  if (httpStatus === 429 || code === "RATE_LIMIT") return farhuaadT("chat_err_rate");
  if (code === "SPAM_COOLDOWN") return farhuaadT("chat_err_cooldown");
  if (code === "SPAM_HOURLY") return farhuaadT("chat_err_hourly");
  if (code === "DISPUTE_CLOSED") return farhuaadT("chat_err_closed");
  if (code === "UNAUTHORIZED") return farhuaadT("chat_login_hint");
  if (code === "MODERATION_SPAM_LINKS") return farhuaadT("chat_err_spam_links");
  if (typeof code === "string" && code.startsWith("MODERATION_")) return farhuaadT("chat_err_moderation");
  if (code === "NOT_FOUND") return farhuaadT("chat_delete_fail");
  return farhuaadT("chat_err_generic");
}

function initDisputeChatPanel(disputeId, isChatOpen) {
  clearDisputeChatPoll();
  const listEl = document.getElementById("dispute-chat-messages");
  const textarea = document.getElementById("dispute-chat-input");
  const sendBtn = document.getElementById("dispute-chat-send");
  const errEl = document.getElementById("dispute-chat-error");
  if (!listEl) return;

  const base = getBasePath();
  const auth = typeof window !== "undefined" && window.FARHUAAD_AUTH === true;
  let loadFailed = false;
  let disputeChatFirstPaint = true;

  function scrollChatToBottom() {
    listEl.scrollTop = listEl.scrollHeight;
  }

  function disputeChatApplyScrollAfterLoad() {
    window.requestAnimationFrame(() => {
      const maxScroll = Math.max(0, listEl.scrollHeight - listEl.clientHeight);
      if (disputeChatFirstPaint) {
        listEl.scrollTop = listEl.scrollHeight;
        disputeChatFirstPaint = false;
        return;
      }
      if (disputeChatStickToBottom) {
        listEl.scrollTop = listEl.scrollHeight;
        return;
      }
      const r = Math.max(0, Math.min(1, disputeChatScrollRatio));
      listEl.scrollTop = r * maxScroll;
    });
  }

  let disputeChatStickToBottom = true;
  let disputeChatScrollRatio = 1;

  listEl.addEventListener("click", async (e) => {
    const btn = e.target && e.target.closest ? e.target.closest("[data-chat-delete-id]") : null;
    if (!(btn instanceof HTMLElement) || !auth) return;
    const msgId = btn.getAttribute("data-chat-delete-id");
    if (!msgId) return;
    e.preventDefault();
    const ok =
      typeof window.farhuaadConfirm === "function"
        ? await window.farhuaadConfirm({
            message: farhuaadT("chat_delete_confirm"),
            confirmText: farhuaadT("chat_delete"),
            danger: true,
          })
        : window.confirm(farhuaadT("chat_delete_confirm"));
    if (!ok) return;
    showChatErr("");
    btn.disabled = true;
    const res = await fetch(`${base}/api/dispute_chat.php`, {
      method: "POST",
      credentials: "same-origin",
      headers: farhuaadWithCsrfHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
      body: JSON.stringify({
        action: "delete",
        dispute_id: disputeId,
        message_id: Number(msgId),
        csrf: farhuaadCsrfToken(),
      }),
    });
    const data = await res.json().catch(() => null);
    btn.disabled = false;
    if (res.ok && data?.ok) {
      await loadMessages();
      return;
    }
    const code = data?.error || "ERROR";
    showChatErr(disputeChatErrorMessage(code, res.status));
  });

  listEl.addEventListener("scroll", () => {
    const max = listEl.scrollHeight - listEl.clientHeight;
    if (max <= 0) {
      disputeChatStickToBottom = true;
      disputeChatScrollRatio = 1;
      return;
    }
    disputeChatScrollRatio = listEl.scrollTop / max;
    disputeChatStickToBottom = max - listEl.scrollTop < 56;
  });

  async function loadMessages() {
    const maxBefore = Math.max(0, listEl.scrollHeight - listEl.clientHeight);
    const ratioBefore = maxBefore > 0 ? listEl.scrollTop / maxBefore : 1;
    const nearBottomBefore = maxBefore <= 0 || maxBefore - listEl.scrollTop < 56;

    const res = await fetch(
      `${base}/api/dispute_chat.php?dispute_id=${encodeURIComponent(String(disputeId))}&${farhuaadApiLangQuery()}`,
      { credentials: "same-origin", headers: { Accept: "application/json" } }
    );
    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.ok) {
      if (!loadFailed) {
        loadFailed = true;
        listEl.innerHTML = `<div class="dispute-chat-list-empty">${escapeHtml(farhuaadT("chat_err_load"))}</div>`;
      }
      return;
    }
    loadFailed = false;
    const msgs = Array.isArray(data.messages) ? data.messages : [];
    if (msgs.length === 0) {
      listEl.innerHTML = `<div class="dispute-chat-list-empty">${escapeHtml(farhuaadT("chat_empty"))}</div>`;
    } else {
      listEl.innerHTML = msgs
        .map((m) => {
          const timeRaw = m.created_at ? new Date(m.created_at).toLocaleString(getLocale()) : "";
          const delBtn =
            auth && m.is_mine
              ? `<button type="button" class="dispute-chat-delete" data-chat-delete-id="${Number(
                  m.id
                )}" title="${escapeHtml(farhuaadT("chat_delete"))}">${escapeHtml(farhuaadT("chat_delete"))}</button>`
              : "";
          return `<div class="dispute-chat-msg"><div class="dispute-chat-msg-meta"><div class="dispute-chat-msg-meta-main"><span class="dispute-chat-author">${escapeHtml(
            m.author || ""
          )}</span><time datetime="${escapeHtml(m.created_at || "")}">${escapeHtml(timeRaw)}</time></div>${delBtn}</div><div class="dispute-chat-body">${escapeHtml(
            m.body || ""
          )}</div></div>`;
        })
        .join("");
    }

    if (disputeChatFirstPaint || nearBottomBefore) {
      disputeChatStickToBottom = true;
      disputeChatScrollRatio = 1;
    } else {
      disputeChatStickToBottom = false;
      disputeChatScrollRatio = ratioBefore;
    }
    disputeChatApplyScrollAfterLoad();
  }

  listEl.innerHTML = `<div class="dispute-chat-loading">${escapeHtml(farhuaadT("chat_loading"))}</div>`;
  loadMessages();
  disputeChatPollTimer = window.setInterval(loadMessages, 8000);

  const canPost = auth && isChatOpen;
  if (textarea instanceof HTMLTextAreaElement) {
    textarea.disabled = !canPost;
    textarea.placeholder = !auth
      ? farhuaadT("chat_login_hint")
      : !isChatOpen
        ? farhuaadT("chat_err_closed")
        : farhuaadT("chat_placeholder");
  }
  if (sendBtn instanceof HTMLButtonElement) {
    sendBtn.disabled = !canPost;
  }

  function showChatErr(msg) {
    if (errEl) {
      errEl.textContent = msg;
      errEl.hidden = !msg;
    }
  }

  async function sendMessage() {
    if (!canPost || !(textarea instanceof HTMLTextAreaElement) || !(sendBtn instanceof HTMLButtonElement)) return;
    const body = textarea.value.trim();
    if (!body) return;
    showChatErr("");
    sendBtn.disabled = true;
    const res = await fetch(`${base}/api/dispute_chat.php`, {
      method: "POST",
      credentials: "same-origin",
      headers: farhuaadWithCsrfHeaders({ "Content-Type": "application/json", Accept: "application/json" }),
      body: JSON.stringify({ dispute_id: disputeId, body, csrf: farhuaadCsrfToken() }),
    });
    const data = await res.json().catch(() => null);
    sendBtn.disabled = !canPost;
    if (res.ok && data?.ok) {
      textarea.value = "";
      await loadMessages();
      return;
    }
    const code = data?.error || "ERROR";
    showChatErr(disputeChatErrorMessage(code, res.status));
  }

  sendBtn?.addEventListener("click", sendMessage);
  textarea?.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });
}

async function renderDisputeDetailsPage() {
  clearDisputeChatPoll();
  const root = document.getElementById("dispute-detail-root");
  if (!root) return;
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get("id") || 0);
  if (id <= 0) {
    root.innerHTML = `<div class="table-empty">${farhuaadT("invalid_market_id")}</div>`;
    return;
  }
  const base = (typeof window !== "undefined" && window.FARHUAAD_BASE) || "";
  const res = await fetch(`${base}/api/dispute_details.php?id=${id}&${farhuaadApiLangQuery()}`, {
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  const data = await res.json().catch(() => null);
  if (!res.ok || !data?.ok || !data.item) {
    root.innerHTML = `<div class="table-empty">${farhuaadT("market_not_found")}</div>`;
    return;
  }
  const item = data.item;
  const descPlain = item.short_description || farhuaadT("description_unavailable");
  const descHtml = escapeHtml(String(descPlain)).replace(/\n/g, "<br>");
  const links = Array.isArray(item.source_links) ? item.source_links : [];
  const linksHtml = links.length
    ? links
        .map((url) => {
          const host = extractHost(url);
          return `<li><a class="dispute-source-link" href="${url}" target="_blank" rel="noopener noreferrer">${host}</a></li>`;
        })
        .join("")
    : `<li>${farhuaadT("no_sources")}</li>`;

  root.innerHTML = `
    <div class="panel-top dispute-page-top">
      <div class="panel-title dispute-page-title">${item.title}</div>
      <div class="panel-actions dispute-meta-actions">
        <span class="dispute-meta-chip">${item.category || farhuaadT("event_fallback")}</span>
        <span class="dispute-meta-chip">${farhuaadT("created_by")}: ${creationSourceLabel(item.creation_source)}</span>
        <span class="dispute-meta-chip">${farhuaadT("status_label")} ${item.status || "active"}</span>
      </div>
    </div>
    <div class="market-image-wrapper dispute-page-image-wrap">
      <div class="market-image dispute-page-image">
        <img src="${resolveDisputeImage(item.image || DISPUTE_FALLBACK_IMAGE)}" alt="${item.title}" onerror="this.onerror=null;this.src='${resolveDisputeImage(DISPUTE_FALLBACK_IMAGE)}'" />
      </div>
    </div>
    <div class="dispute-desc-chat-row">
      <div class="dispute-desc-text-cell dispute-desc-col">
        <div class="dispute-page-description dispute-page-description--full" role="article">${descHtml}</div>
      </div>
      <aside class="dispute-desc-chat-col dispute-chat-panel" aria-label="${farhuaadT("chat_title")}">
        <div class="dispute-chat-head">${farhuaadT("chat_title")}</div>
        <div class="dispute-chat-messages" id="dispute-chat-messages"></div>
        <div class="dispute-chat-compose">
          <textarea class="dispute-chat-input" id="dispute-chat-input" maxlength="500" rows="2"></textarea>
          <div class="dispute-chat-send-row">
            <div class="dispute-chat-error" id="dispute-chat-error" hidden></div>
            <button type="button" class="btn btn-primary" id="dispute-chat-send">${farhuaadT("chat_send")}</button>
          </div>
        </div>
        <p class="dispute-chat-hint">${farhuaadT("chat_hint_ttl")}</p>
      </aside>
    </div>
    <section class="stats-row">
      <div class="stat-card"><div class="stat-label">${farhuaadT("yes")}</div><div class="stat-value">${Number(item.yes_percent || 50).toFixed(1)}%</div><div class="stat-sub">${formatPool(item.yes_pool)}</div></div>
      <div class="stat-card"><div class="stat-label">${farhuaadT("no")}</div><div class="stat-value">${Number(item.no_percent || 50).toFixed(1)}%</div><div class="stat-sub">${formatPool(item.no_pool)}</div></div>
      <div class="stat-card"><div class="stat-label">${farhuaadT("total_pool")}</div><div class="stat-value">${formatPool(item.total_pool)}</div><div class="stat-sub">${farhuaadT("user_bets")}</div></div>
      <div class="stat-card"><div class="stat-label">${farhuaadT("closing")}</div><div class="stat-value">${item.expires_at ? new Date(item.expires_at).toLocaleDateString(getLocale()) : "—"}</div><div class="stat-sub">${item.status}</div></div>
    </section>
    <section class="bet-panel" id="dispute-bet-panel">
      <div class="bet-panel-head">
        <div class="bet-panel-title">${farhuaadT("bet_entry_title")}</div>
        <div class="bet-panel-subtitle">${farhuaadT("bet_entry_sub")} <strong id="bet-selected-side">${farhuaadT("yes")}</strong></div>
      </div>
      <div class="bet-side-tabs" role="tablist" aria-label="${farhuaadT("side_tabs_aria")}">
        <button type="button" class="bet-side-tab is-active" data-bet-side="yes">${farhuaadT("yes")}</button>
        <button type="button" class="bet-side-tab" data-bet-side="no">${farhuaadT("no")}</button>
      </div>
      <p class="bet-token-rate-hint">${farhuaadT("bet_tokens_rate")}</p>
      <div class="bet-amount-row">
        <label class="label" for="bet-amount-input">${farhuaadT("bet_amount_label")}</label>
        <div class="bet-input-wrap">
          <input class="input bet-amount-input" id="bet-amount-input" type="number" min="1" step="1" placeholder="${farhuaadT("amount_placeholder")}" />
          <span class="bet-currency">${farhuaadT("token_input_suffix")}</span>
          <div class="bet-stepper" aria-label="${farhuaadT("stepper_aria")}">
            <button type="button" class="bet-step-btn" data-step-input="bet-amount-input" data-step-dir="up" aria-label="${farhuaadT("step_up_aria")}">▲</button>
            <button type="button" class="bet-step-btn" data-step-input="bet-amount-input" data-step-dir="down" aria-label="${farhuaadT("step_down_aria")}">▼</button>
          </div>
        </div>
      </div>
      <div class="bet-quick-row" aria-label="${farhuaadT("quick_amount_aria")}">
        <button type="button" class="chip chip-outline" data-bet-quick="100">${farhuaadTokenPackLabel(100)}</button>
        <button type="button" class="chip chip-outline" data-bet-quick="500">${farhuaadTokenPackLabel(500)}</button>
        <button type="button" class="chip chip-outline" data-bet-quick="1000">${farhuaadTokenPackLabel(1000)}</button>
        <button type="button" class="chip chip-outline" data-bet-quick="3000">${farhuaadTokenPackLabel(3000)}</button>
      </div>
      <div class="bet-panel-footer">
        <span class="bet-footer-hint" id="bet-footer-hint">${farhuaadT("bet_footer_default")}</span>
        <button class="btn btn-primary" id="bet-submit-btn" type="button">${farhuaadT("place_bet")}</button>
      </div>
    </section>
    <section class="panel dispute-sources-panel">
      <div class="panel-title">${farhuaadT("sources")}</div>
      <ul class="dispute-sources-list">${linksHtml}</ul>
    </section>
  `;
  initDisputeBetPanel(id);
  const chatOpen = String(item.status || "").toLowerCase() === "active";
  initDisputeChatPanel(id, chatOpen);
}

document.addEventListener("DOMContentLoaded", () => {
  initCopyAttribution();
  renderMarkets(markets);
  renderSearchDisputes(markets);
  initShowAllDisputesToggle();
  initMarketSearch();
  initInstructionCarousel();
  initHeroActionButtons();
  initStatsPageInteractions();
  initPortfolioPageInteractions();
  initWalletButtons();
  void loadDailyDisputes();
  void loadHeroStats();
  startPresenceHeartbeat();
  if (pageWantsPlatformStatsPolling()) {
    window.setInterval(loadHeroStats, HERO_STATS_REFRESH_INTERVAL_MS);
  }
  void renderDisputeDetailsPage();
});

