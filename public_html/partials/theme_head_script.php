<?php
declare(strict_types=1);
/** Ранняя установка темы из localStorage (до отрисовки). По умолчанию — светлая. Favicon под тему. Требует init.php и farhuaad_csp_nonce_attr(). */
if (!function_exists('farhuaad_csp_nonce_attr')) {
  return;
}
$iconLight = function_exists('farhuaad_asset_url') ? farhuaad_asset_url('assets/img/web_bl.ico') : '';
$iconDark = function_exists('farhuaad_asset_url') ? farhuaad_asset_url('assets/img/web.ico') : '';
?>
<?php if ($iconLight !== '' || $iconDark !== ''): ?>
<link
  id="farhuaad-favicon"
  rel="icon"
  type="image/x-icon"
  href="<?php echo htmlspecialchars($iconLight !== '' ? $iconLight : $iconDark, ENT_QUOTES, 'UTF-8'); ?>"
/>
<?php endif; ?>
<script<?php echo farhuaad_csp_nonce_attr(); ?>>
(function () {
  var ICON_LIGHT = <?php echo json_encode($iconLight, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  var ICON_DARK = <?php echo json_encode($iconDark, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  try {
    var t = localStorage.getItem("farhuaad_theme");
    if (t !== "light" && t !== "dark") {
      t = "light";
    }
    document.documentElement.setAttribute("data-theme", t);
    window.FARHUAAD_FAVICON_LIGHT = ICON_LIGHT;
    window.FARHUAAD_FAVICON_DARK = ICON_DARK;
    var href = t === "light" ? ICON_LIGHT : ICON_DARK;
    if (href) {
      var prev = document.getElementById("farhuaad-favicon");
      if (prev) {
        prev.remove();
      }
      var link = document.createElement("link");
      link.id = "farhuaad-favicon";
      link.rel = "icon";
      link.href = href;
      document.head.appendChild(link);
    }
  } catch (e) {}
})();
</script>
