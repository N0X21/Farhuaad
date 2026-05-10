<?php
// Подвал сайта Farhuaad
$contactEmail = __('footer.contact_email');
$contactMailto = 'mailto:' . $contactEmail;
$copyYear = (int)date('Y');
?>
<footer class="footer">
  <div class="footer-main-row">
  <div class="footer-left">
    <div class="logo footer-logo">
      <img src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/img/logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Farhuaad" height="28" class="logo-img logo-img--theme-dark" loading="lazy" decoding="async" />
      <img src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/img/logo_bl.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="Farhuaad" height="28" class="logo-img logo-img--theme-light" loading="lazy" decoding="async" />
    </div>
    <p class="footer-text">
      <?php echo htmlspecialchars(__('footer.tagline'), ENT_QUOTES, 'UTF-8'); ?>
    </p>
  </div>
  <div class="footer-links">
    <div class="footer-column">
      <span class="footer-title"><?php echo htmlspecialchars(__('footer.community'), ENT_QUOTES, 'UTF-8'); ?></span>
      <a href="https://t.me/farhuaad" class="footer-link" target="_blank" rel="noopener noreferrer">Telegram</a>
      <a href="https://www.youtube.com/@sk4bi-rub1-doo" class="footer-link" target="_blank" rel="noopener noreferrer">Youtube</a>
    </div>
    <div class="footer-column">
      <span class="footer-title"><?php echo htmlspecialchars(__('footer.transparency'), ENT_QUOTES, 'UTF-8'); ?></span>
      <a href="<?php echo htmlspecialchars(farhuaad_url('pages/privacy.php'), ENT_QUOTES, 'UTF-8'); ?>" class="footer-link"><?php echo htmlspecialchars(__('footer.privacy'), ENT_QUOTES, 'UTF-8'); ?></a>
      <a href="<?php echo htmlspecialchars(farhuaad_url('pages/cookies.php'), ENT_QUOTES, 'UTF-8'); ?>" class="footer-link"><?php echo htmlspecialchars(__('footer.cookies'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="footer-column">
      <span class="footer-title"><?php echo htmlspecialchars(__('footer.contact'), ENT_QUOTES, 'UTF-8'); ?></span>
      <a href="<?php echo htmlspecialchars($contactMailto, ENT_QUOTES, 'UTF-8'); ?>" class="footer-link"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
  </div>
  </div>
  <div class="footer-legal" aria-label="<?php echo htmlspecialchars(__('footer.copyright_aria'), ENT_QUOTES, 'UTF-8'); ?>">
    <p class="footer-copyright">
      © <?php echo $copyYear; ?> Farhuaad. <?php echo htmlspecialchars(__('footer.copyright_notice'), ENT_QUOTES, 'UTF-8'); ?>
    </p>
  </div>
</footer>
