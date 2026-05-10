<?php
require __DIR__ . '/../app/init.php';
$user = farhuaad_current_user();
if (!$user) {
  header('Location: ' . farhuaad_url('pages/login.php'));
  exit;
}
$defaultStatsCategories = ['Крипто', 'Экономика', 'Политика', 'Технологии', 'Спорт', 'Событие'];
$stats = [
  'total_volume' => 0.0,
  'active_markets' => 0,
  'unique_traders' => 0,
  'top_markets' => [],
  'stats_categories' => $defaultStatsCategories,
];
if ($pdo instanceof PDO) {
  farhuaad_ensure_disputes_table($pdo);
  farhuaad_ensure_dispute_bets_table($pdo);
  farhuaad_migrate_disputes_table($pdo);
  if (function_exists('farhuaad_migrate_users_referral_column')) {
    farhuaad_migrate_users_referral_column($pdo);
  }
  $stats = farhuaad_get_platform_stats($pdo);
}
$topMarkets = is_array($stats['top_markets'] ?? null) ? $stats['top_markets'] : [];
$statsCategories = is_array($stats['stats_categories'] ?? null) ? $stats['stats_categories'] : $defaultStatsCategories;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('stats.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('stats.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(farhuaad_asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <main class="main">
      <section class="page-hero">
        <div class="page-hero-left">
          <h1 class="page-title"><?php echo htmlspecialchars(__('stats.title'), ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="page-subtitle"><?php echo htmlspecialchars(__('stats.subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="page-hero-right">
          <button class="chip chip-outline" type="button" data-stats-period="24h">24h</button>
          <button class="chip chip-outline" type="button" data-stats-period="7d">7d</button>
          <button class="chip chip-filled chip-active" type="button" data-stats-period="30d">30d</button>
        </div>
      </section>

      <section class="stats-row">
        <div class="stat-card">
          <div class="stat-label"><?php echo htmlspecialchars(__('stats.volume'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="stat-value"><?php echo number_format((float)($stats['total_volume'] ?? 0), 2, '.', ' '); ?> A</div>
          <div class="stat-sub"><?php echo htmlspecialchars(__('stats.volume_sub'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label"><?php echo htmlspecialchars(__('stats.active'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="stat-value"><?php echo (int)($stats['active_markets'] ?? 0); ?></div>
          <div class="stat-sub"><?php echo htmlspecialchars(__('stats.active_sub'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label"><?php echo htmlspecialchars(__('stats.traders'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="stat-value"><?php echo number_format((int)($stats['unique_traders'] ?? 0), 0, '.', ' '); ?></div>
          <div class="stat-sub"><?php echo htmlspecialchars(__('stats.traders_sub'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-top">
          <div class="panel-title"><?php echo htmlspecialchars(__('stats.top'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="panel-actions">
            <button class="chip chip-filled chip-active" type="button" data-stats-category="Все"><?php echo htmlspecialchars(__('stats.filter_all'), ENT_QUOTES, 'UTF-8'); ?></button>
            <?php foreach ($statsCategories as $cat): ?>
              <button class="chip chip-outline" type="button" data-stats-category="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(farhuaad_category_label($cat), ENT_QUOTES, 'UTF-8'); ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="table table-stats">
          <div class="table-row table-head">
            <div><?php echo htmlspecialchars(__('stats.col_market'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('stats.col_cat'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('stats.col_status'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('stats.col_outcome'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('stats.col_prob'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('stats.col_volume'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('stats.col_liq'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>

          <?php if ($topMarkets): ?>
            <?php foreach ($topMarkets as $market): ?>
              <?php
              $mcat = (string)($market['category'] ?? 'Событие');
              $dealOpen = !empty($market['deal_open']);
              $statusLabel = (string)($market['status_label'] ?? '—');
              $outcomeLabel = (string)($market['outcome_label'] ?? '—');
              $resTitle = trim((string)($market['resolution_title'] ?? ''));
              ?>
              <div class="table-row" data-market-category="<?php echo htmlspecialchars($mcat, ENT_QUOTES, 'UTF-8'); ?>" data-dispute-id="<?php echo (int)($market['id'] ?? 0); ?>" data-deal-open="<?php echo $dealOpen ? '1' : '0'; ?>">
                <div class="table-strong"><?php echo htmlspecialchars((string)($market['title'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><?php echo htmlspecialchars(farhuaad_category_label($mcat), ENT_QUOTES, 'UTF-8'); ?></div>
                <div>
                  <span class="stats-status <?php echo $dealOpen ? 'stats-status-open' : 'stats-status-closed'; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                  <div class="table-sub"><?php echo $dealOpen ? htmlspecialchars(__('stats.trading_open'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(__('stats.trading_closed'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div>
                  <span class="table-strong"><?php echo htmlspecialchars($outcomeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php if ($resTitle !== '' && !$dealOpen): ?>
                    <div class="table-sub"><?php echo htmlspecialchars($resTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php elseif ($dealOpen): ?>
                    <div class="table-sub"><?php echo htmlspecialchars(__('stats.outcome_pending'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php endif; ?>
                </div>
                <div><?php echo number_format((float)($market['probability'] ?? 50), 1, '.', ' '); ?>%</div>
                <div><?php echo number_format((float)($market['volume'] ?? 0), 2, '.', ' '); ?> A</div>
                <div><?php echo number_format((float)($market['liquidity'] ?? 0), 2, '.', ' '); ?> A</div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="table-empty">
              <?php echo htmlspecialchars(__('stats.empty'), ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </main>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>

  <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/main.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
