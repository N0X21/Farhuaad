<?php
declare(strict_types=1);

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isActive = static function (string $file) use ($currentPage): bool {
  return $currentPage === $file;
};
$user = function_exists('farhuaad_current_user') ? farhuaad_current_user() : null;
$linkClass = isset($linkClass) && is_string($linkClass) && $linkClass !== '' ? $linkClass : 'nav-link';
$activeClass = $linkClass === 'nav-link' ? 'nav-link-active' : 'is-active';

?>
<a href="<?php echo htmlspecialchars(farhuaad_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'); ?><?php echo $isActive('index.php') ? ' ' . $activeClass : ''; ?>"><?php echo htmlspecialchars(__('nav.markets'), ENT_QUOTES, 'UTF-8'); ?></a>
<?php if ($user): ?>
  <a href="<?php echo htmlspecialchars(farhuaad_url('pages/submit_dispute.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'); ?><?php echo $isActive('submit_dispute.php') ? ' ' . $activeClass : ''; ?>"><?php echo htmlspecialchars(__('nav.submit_dispute'), ENT_QUOTES, 'UTF-8'); ?></a>
  <a href="<?php echo htmlspecialchars(farhuaad_url('pages/portfolio.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'); ?><?php echo $isActive('portfolio.php') ? ' ' . $activeClass : ''; ?>"><?php echo htmlspecialchars(__('nav.portfolio'), ENT_QUOTES, 'UTF-8'); ?></a>
  <a href="<?php echo htmlspecialchars(farhuaad_url('pages/stats.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'); ?><?php echo $isActive('stats.php') ? ' ' . $activeClass : ''; ?>"><?php echo htmlspecialchars(__('nav.stats'), ENT_QUOTES, 'UTF-8'); ?></a>
  <a href="<?php echo htmlspecialchars(farhuaad_url('pages/leaderboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'); ?><?php echo $isActive('leaderboard.php') ? ' ' . $activeClass : ''; ?>"><?php echo htmlspecialchars(__('nav.leaders'), ENT_QUOTES, 'UTF-8'); ?></a>
<?php endif; ?>
