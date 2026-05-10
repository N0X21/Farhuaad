<?php
require __DIR__ . '/../app/init.php';
$rows = farhuaad_get_leaderboard(20);
$daysUntilReset = function_exists('farhuaad_days_until_balance_reset') ? farhuaad_days_until_balance_reset() : null;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('leader.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('leader.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
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
          <h1 class="page-title"><?php echo htmlspecialchars(__('leader.title'), ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="page-subtitle"><?php echo htmlspecialchars(__('leader.subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <?php if ($daysUntilReset !== null): ?>
          <div class="page-hero-right">
            <div class="leader-reset-hint">
              <?php
              $daysLabel = max(0, (int)$daysUntilReset);
              echo htmlspecialchars(__('leader.reset_hint', ['days' => (string)$daysLabel]), ENT_QUOTES, 'UTF-8');
              ?>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel-top">
          <div class="panel-title"><?php echo htmlspecialchars(__('leader.panel'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <div class="table">
          <div class="table-row table-head">
            <div><?php echo htmlspecialchars(__('leader.col_place'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('leader.col_user'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('leader.col_balance'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div><?php echo htmlspecialchars(__('leader.col_profit'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>

          <?php if (!$rows): ?>
            <div class="table-empty">
              <?php echo htmlspecialchars(__('leader.empty'), ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php else: ?>
            <?php foreach (array_values($rows) as $i => $r): ?>
              <div class="table-row">
                <div class="table-strong">#<?php echo $i + 1; ?></div>
                <div><?php echo htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div><?php echo number_format($r['balance'], 2, '.', ' '); ?> A</div>
                <div><?php echo $r['profit'] >= 0 ? '+' : ''; ?><?php echo number_format($r['profit'], 2, '.', ' '); ?> A</div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </main>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>

  <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/main.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
