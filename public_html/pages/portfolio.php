<?php
require __DIR__ . '/../app/init.php';
$user = farhuaad_current_user();
if (!$user) {
  header('Location: ' . farhuaad_url('pages/login.php'));
  exit;
}
$wallets = [];
$walletConnected = false;
if ($user && isset($user['id'])) {
  $wallets = farhuaad_get_user_wallets((string)$user['id']);
  $walletConnected = count($wallets) > 0;
}
$portfolio = ['positions' => [], 'total_staked' => 0.0, 'open_positions' => 0, 'closed_pnl' => 0.0];
if (($pdo instanceof PDO) && isset($user['id'])) {
  farhuaad_ensure_disputes_table($pdo);
  farhuaad_ensure_dispute_bets_table($pdo);
  farhuaad_migrate_disputes_table($pdo);
  if (function_exists('farhuaad_migrate_users_referral_column')) {
    farhuaad_migrate_users_referral_column($pdo);
  }
  $portfolio = farhuaad_get_user_portfolio_overview($pdo, (int)$user['id']);
}
$positions = is_array($portfolio['positions'] ?? null) ? $portfolio['positions'] : [];
$totalStaked = (float)($portfolio['total_staked'] ?? 0);
$openPositions = (int)($portfolio['open_positions'] ?? 0);
$closedPnl = (float)($portfolio['closed_pnl'] ?? 0);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('portfolio.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('portfolio.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
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
          <h1 class="page-title"><?php echo htmlspecialchars(__('portfolio.title'), ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="page-subtitle"><?php echo htmlspecialchars(__('portfolio.subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="page-hero-right">
          <button class="btn btn-outline"><?php echo htmlspecialchars(__('portfolio.deposit'), ENT_QUOTES, 'UTF-8'); ?></button>
          <button
            class="btn btn-primary"
            type="button"
            data-wallet-auth
            <?php echo $walletConnected ? 'disabled aria-disabled="true"' : ''; ?>
          >
            <?php
            echo htmlspecialchars(
              $walletConnected ? __('profile.wallet_connected_btn') : __('portfolio.connect_wallet'),
              ENT_QUOTES,
              'UTF-8'
            );
            ?>
          </button>
        </div>
      </section>

      <section class="stats-row">
        <div class="stat-card">
          <div class="stat-label"><?php echo htmlspecialchars(__('portfolio.staked'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="stat-value"><?php echo number_format($totalStaked, 2, '.', ' '); ?> A</div>
          <div class="stat-sub"><?php echo htmlspecialchars(__('portfolio.staked_sub'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label"><?php echo htmlspecialchars(__('portfolio.pnl'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="stat-value"><?php echo ($closedPnl >= 0 ? '+' : '') . number_format($closedPnl, 2, '.', ' '); ?> A</div>
          <div class="stat-sub"><?php echo htmlspecialchars(__('portfolio.pnl_sub'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label"><?php echo htmlspecialchars(__('portfolio.open_pos'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="stat-value"><?php echo $openPositions; ?></div>
          <div class="stat-sub"><?php echo htmlspecialchars(__('portfolio.open_pos_sub'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label"><?php echo htmlspecialchars(__('portfolio.wallet_status'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="stat-value"><?php echo $walletConnected ? htmlspecialchars(__('portfolio.wallet_on'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(__('portfolio.wallet_off'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="stat-sub">
            <?php if ($walletConnected): ?>
              <?php
              $first = (string)($wallets[0]['address'] ?? '');
              echo htmlspecialchars(substr($first, 0, 6) . '...' . substr($first, -4), ENT_QUOTES, 'UTF-8');
              ?>
            <?php else: ?>
              <?php echo htmlspecialchars(__('portfolio.wallet_hint'), ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-top">
          <div class="panel-title"><?php echo htmlspecialchars(__('portfolio.positions'), ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="panel-actions">
            <button type="button" class="chip chip-filled chip-active" data-portfolio-filter="all"><?php echo htmlspecialchars(__('portfolio.f_all'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-portfolio-filter="open"><?php echo htmlspecialchars(__('portfolio.f_open'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="chip chip-outline" data-portfolio-filter="closed"><?php echo htmlspecialchars(__('portfolio.f_closed'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
        </div>

        <div class="table table-portfolio">
          <div class="table-row table-head">
            <div><?php echo htmlspecialchars(__('portfolio.col_market'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('portfolio.col_status'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('portfolio.col_side'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('portfolio.col_shares'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('portfolio.col_avg'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('portfolio.col_pnl'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <?php if ($positions): ?>
            <?php foreach ($positions as $pos): ?>
              <?php
              $did = (int)($pos['dispute_id'] ?? 0);
              $marketOpen = !empty($pos['market_open']);
              $filterSlug = $marketOpen ? 'open' : 'closed';
              $statusRaw = strtolower(trim((string)($pos['status'] ?? 'active')));
              $statusLabel = function_exists('farhuaad_stats_dispute_status_label')
                ? farhuaad_stats_dispute_status_label($statusRaw)
                : $statusRaw;
              $winning = strtolower(trim((string)($pos['winning_side'] ?? '')));
              $side = strtolower(trim((string)($pos['side'] ?? '')));
              $pnlVal = (float)($pos['pnl'] ?? 0);
              ?>
              <div
                class="table-row portfolio-position-row"
                data-portfolio-status="<?php echo htmlspecialchars($filterSlug, ENT_QUOTES, 'UTF-8'); ?>"
                data-dispute-id="<?php echo $did; ?>"
                role="button"
                tabindex="0"
              >
                <div class="table-strong"><?php echo htmlspecialchars((string)($pos['title'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div>
                  <span class="stats-status <?php echo $marketOpen ? 'stats-status-open' : 'stats-status-closed'; ?>">
                    <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                  <?php if ($marketOpen): ?>
                    <div class="table-sub"><?php echo htmlspecialchars(__('portfolio.sub_trading'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php elseif ($winning === 'yes' || $winning === 'no'): ?>
                    <div class="table-sub"><?php echo htmlspecialchars($winning === $side ? __('portfolio.sub_won') : __('portfolio.sub_lost'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php else: ?>
                    <div class="table-sub"><?php echo htmlspecialchars(__('portfolio.sub_settled'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php endif; ?>
                </div>
                <div><?php echo $side === 'yes' ? htmlspecialchars(__('yes'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(__('no'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><?php echo number_format((float)($pos['amount'] ?? 0), 2, '.', ' '); ?> A</div>
                <div><?php echo number_format((float)($pos['avg_price'] ?? 1), 2, '.', ' '); ?></div>
                <div>
                  <?php if ($marketOpen): ?>
                    <span class="portfolio-pnl-pending" aria-hidden="true">—</span>
                  <?php else: ?>
                    <?php echo ($pnlVal >= 0 ? '+' : '') . number_format($pnlVal, 2, '.', ' '); ?> A
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <div class="table-empty portfolio-filter-empty" hidden><?php echo htmlspecialchars(__('portfolio.filter_empty'), ENT_QUOTES, 'UTF-8'); ?></div>
          <?php else: ?>
            <div class="table-empty">
              <?php echo htmlspecialchars(__('portfolio.empty'), ENT_QUOTES, 'UTF-8'); ?>
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
