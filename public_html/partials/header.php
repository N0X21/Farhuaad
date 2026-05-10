<?php
$user = function_exists('farhuaad_current_user') ? farhuaad_current_user() : null;
$balance = $user && function_exists('farhuaad_get_balance') ? farhuaad_get_balance((string)$user['id']) : 0;
?>
<header class="header" id="site-header">
  <button
    type="button"
    class="header-burger"
    id="header-burger"
    aria-expanded="false"
    aria-controls="header-mobile-panel"
    aria-label="<?php echo htmlspecialchars(__('header.menu_aria'), ENT_QUOTES, 'UTF-8'); ?>"
  >
    <span class="header-burger-lines" aria-hidden="true">
      <span class="header-burger-line"></span>
      <span class="header-burger-line"></span>
      <span class="header-burger-line"></span>
    </span>
  </button>
  <div class="header-left">
    <a href="<?php echo htmlspecialchars(farhuaad_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="logo">
      <img src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/img/logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Farhuaad" height="28" class="logo-img logo-img--theme-dark" />
      <img src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/img/logo_bl.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Farhuaad" height="28" class="logo-img logo-img--theme-light" />
    </a>
    <nav class="nav nav--desktop" aria-label="<?php echo htmlspecialchars(__('header.nav_aria'), ENT_QUOTES, 'UTF-8'); ?>">
      <?php
      $linkClass = 'nav-link';
      include __DIR__ . '/nav_links.php';
      ?>
    </nav>
  </div>
  <div class="header-right">
    <div class="header-account">
      <?php if ($user): ?>
        <span class="header-balance"><span class="header-balance-label"><?php echo htmlspecialchars(__('header.balance'), ENT_QUOTES, 'UTF-8'); ?></span> <strong class="js-header-balance" id="header-user-balance"><?php echo number_format($balance, 0, '.', ' '); ?> A</strong></span>
        <a class="btn btn-outline btn-header-compact" href="<?php echo htmlspecialchars(farhuaad_url('pages/profile.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('header.profile'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a class="btn btn-outline btn-header-compact" href="<?php echo htmlspecialchars(farhuaad_url('pages/logout.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('header.logout'), ENT_QUOTES, 'UTF-8'); ?></a>
      <?php else: ?>
        <a class="btn btn-outline btn-header-compact" href="<?php echo htmlspecialchars(farhuaad_url('pages/login.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('header.login'), ENT_QUOTES, 'UTF-8'); ?></a>
      <?php endif; ?>
    </div>
    <div class="header-lang-switch" role="group" aria-label="<?php echo htmlspecialchars(__('header.lang_aria'), ENT_QUOTES, 'UTF-8'); ?>">
      <a
        class="header-lang-seg<?php echo farhuaad_lang() === 'ru' ? ' is-active' : ''; ?>"
        href="<?php echo htmlspecialchars(farhuaad_lang_switch_url('ru'), ENT_QUOTES, 'UTF-8'); ?>"
        <?php echo farhuaad_lang() === 'ru' ? 'aria-current="true"' : ''; ?>
      ><?php echo htmlspecialchars(__('lang.ru'), ENT_QUOTES, 'UTF-8'); ?></a>
      <a
        class="header-lang-seg<?php echo farhuaad_lang() === 'en' ? ' is-active' : ''; ?>"
        href="<?php echo htmlspecialchars(farhuaad_lang_switch_url('en'), ENT_QUOTES, 'UTF-8'); ?>"
        <?php echo farhuaad_lang() === 'en' ? 'aria-current="true"' : ''; ?>
      ><?php echo htmlspecialchars(__('lang.en'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
  </div>
</header>
<div class="header-mobile-scrim" id="header-mobile-scrim" hidden></div>
<div
  class="header-mobile-panel"
  id="header-mobile-panel"
  hidden
  role="dialog"
  aria-modal="true"
  aria-label="<?php echo htmlspecialchars(__('header.menu_panel_aria'), ENT_QUOTES, 'UTF-8'); ?>"
>
  <?php if ($user): ?>
    <div class="header-mobile-balance" aria-live="polite">
      <span class="header-mobile-balance-label"><?php echo htmlspecialchars(__('header.balance'), ENT_QUOTES, 'UTF-8'); ?></span>
      <strong class="js-header-balance"><?php echo number_format($balance, 0, '.', ' '); ?> A</strong>
    </div>
  <?php endif; ?>
  <nav class="header-mobile-nav" aria-label="<?php echo htmlspecialchars(__('header.nav_aria'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $linkClass = 'header-mobile-nav__link';
    include __DIR__ . '/nav_links.php';
    ?>
  </nav>
</div>

<script<?php echo farhuaad_csp_nonce_attr(); ?>>
window.FARHUAAD_BASE = <?php echo json_encode(farhuaad_base_url(), JSON_UNESCAPED_SLASHES); ?>;
window.FARHUAAD_CSRF = <?php echo json_encode(farhuaad_csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.FARHUAAD_AUTH = <?php echo ($user && !empty($user['id'])) ? 'true' : 'false'; ?>;
window.FARHUAAD_LANG = <?php echo json_encode(farhuaad_lang(), JSON_UNESCAPED_UNICODE); ?>;
window.FARHUAAD_LOCALE = <?php echo json_encode(farhuaad_locale_tag(), JSON_UNESCAPED_UNICODE); ?>;
window.FARHUAAD_I18N = <?php echo json_encode(farhuaad_i18n_js(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/farhuaad-ui.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/site-header.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
