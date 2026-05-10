<?php
require __DIR__ . '/../app/init.php';
$user = farhuaad_current_user();
if (!$user || empty($user['id'])) {
  $id = (int)($_GET['id'] ?? 0);
  $nextPath = $id > 0 ? ('pages/dispute.php?id=' . $id) : 'pages/dispute.php';
  header('Location: ' . farhuaad_url('pages/login.php') . '?next=' . rawurlencode($nextPath));
  exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('dispute.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('dispute.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(farhuaad_asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../partials/header.php'; ?>
    <main class="main">
      <section class="panel" id="dispute-detail-root">
        <div class="table-empty"><?php echo htmlspecialchars(__('dispute.loading'), ENT_QUOTES, 'UTF-8'); ?></div>
      </section>
    </main>
    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>
  <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/main.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
