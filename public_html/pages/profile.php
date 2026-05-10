<?php require __DIR__ . '/../app/init.php';

$user = farhuaad_current_user();
if (!$user) {
  header('Location: ' . farhuaad_url('pages/login.php'));
  exit;
}

$userId = (string)($user['id'] ?? '');
$dbUser = $userId !== '' ? farhuaad_get_user_by_id($userId) : null;
$email = (string)($dbUser['email'] ?? $user['email'] ?? '');
$username = $email !== '' ? (explode('@', $email)[0] ?: 'user') : (string)($dbUser['name'] ?? $user['name'] ?? 'user');
$balance = $userId !== '' ? farhuaad_get_balance($userId) : 1000.0;
$wallets = $userId !== '' ? farhuaad_get_user_wallets($userId) : [];
$rank = $userId !== '' ? farhuaad_get_user_rank($userId) : null;
$refUid = $userId !== '' ? (int)$userId : 0;

global $pdo;
$refUrl = '';
$invitedList = [];
if ($refUid > 0 && $pdo instanceof PDO && function_exists('farhuaad_migrate_users_referral_column')) {
  farhuaad_migrate_users_referral_column($pdo);
  $refUrl = farhuaad_get_public_referral_url($pdo, $refUid);
  $invitedList = farhuaad_get_invited_users_by_referrer($pdo, $refUid);
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('profile.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('profile.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(farhuaad_asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <main class="main">
      <div class="profile-page">
      <section class="page-hero">
        <div class="page-hero-left">
          <h1 class="page-title"><?php echo htmlspecialchars(__('profile.title'), ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="page-subtitle"><?php echo htmlspecialchars(__('profile.subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      </section>

      <section class="profile-layout<?php echo $refUrl === '' ? ' profile-layout-single' : ''; ?>">
        <div class="profile-card profile-card-main">
        <div class="profile-row">
          <span class="profile-label"><?php echo htmlspecialchars(__('profile.email'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="profile-value"><?php echo htmlspecialchars($email !== '' ? $email : '—', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="profile-row">
          <span class="profile-label"><?php echo htmlspecialchars(__('profile.username'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="profile-value"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="profile-row profile-row-balance">
          <span class="profile-label"><?php echo htmlspecialchars(__('profile.balance'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="profile-value profile-balance"><?php echo number_format($balance, 0, '.', ' '); ?> A</span>
        </div>
        <div class="profile-row">
          <span class="profile-label"><?php echo htmlspecialchars(__('profile.rank'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="profile-value"><?php echo $rank !== null ? ('#' . (int)$rank) : '—'; ?></span>
        </div>
        <div class="profile-row">
          <span class="profile-label"><?php echo htmlspecialchars(__('profile.wallets'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="profile-value">
            <?php if (!$wallets): ?>
              —
            <?php else: ?>
              <?php
              $walletLabels = array_map(static function ($w) {
                $a = (string)($w['address'] ?? '');
                if ($a === '') return null;
                return substr($a, 0, 6) . '...' . substr($a, -4);
              }, $wallets);
              $walletLabels = array_values(array_filter($walletLabels));
              echo htmlspecialchars(implode(', ', $walletLabels), ENT_QUOTES, 'UTF-8');
              ?>
            <?php endif; ?>
          </span>
        </div>
        <div class="profile-row">
          <span class="profile-label"><?php echo htmlspecialchars(__('profile.wallet_connect'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="profile-value">
            <?php if ($wallets): ?>
              <form method="post" class="profile-wallet-inline" action="<?php echo htmlspecialchars(farhuaad_url('pages/wallet_detach.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(farhuaad_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" />
                <button class="btn btn-outline" type="submit">
                  <?php echo htmlspecialchars(__('profile.wallet_detach_btn'), ENT_QUOTES, 'UTF-8'); ?>
                </button>
              </form>
            <?php else: ?>
              <button
                class="btn btn-outline"
                type="button"
                data-wallet-auth
              >
                <?php echo htmlspecialchars(__('profile.wallet_connect_btn'), ENT_QUOTES, 'UTF-8'); ?>
              </button>
            <?php endif; ?>
          </span>
        </div>
        <div class="profile-row">
          <span class="profile-label"><?php echo htmlspecialchars(__('profile.theme'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="profile-value">
            <div
              id="profile-theme-switch"
              class="theme-switch"
              role="group"
              aria-label="<?php echo htmlspecialchars(__('profile.theme_aria'), ENT_QUOTES, 'UTF-8'); ?>"
            >
              <button type="button" class="theme-switch-seg" data-theme-value="light"><?php echo htmlspecialchars(__('profile.theme_light'), ENT_QUOTES, 'UTF-8'); ?></button>
              <button type="button" class="theme-switch-seg" data-theme-value="dark"><?php echo htmlspecialchars(__('profile.theme_dark'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
          </span>
        </div>
        <p class="profile-note"><?php echo htmlspecialchars(__('profile.note'), ENT_QUOTES, 'UTF-8'); ?></p>
        <hr class="profile-divider" />
        <div class="profile-row profile-row-danger">
          <div class="profile-delete-left">
            <div class="profile-label"><?php echo htmlspecialchars(__('profile.delete_account'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="profile-delete-note">
              <?php echo htmlspecialchars(__('profile.delete_account_note'), ENT_QUOTES, 'UTF-8'); ?>
            </div>
          </div>
          <div class="profile-delete-right">
            <form id="profile-account-delete-form" method="post" action="<?php echo htmlspecialchars(farhuaad_url('pages/account_delete.php'), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(farhuaad_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" />
              <button class="btn btn-outline btn-danger" type="submit">
                <?php echo htmlspecialchars(__('profile.delete_account'), ENT_QUOTES, 'UTF-8'); ?>
              </button>
            </form>
          </div>
        </div>
        </div>
        <?php if ($refUrl !== ''): ?>
        <aside class="profile-card profile-card-referral" aria-label="<?php echo htmlspecialchars(__('profile.referral_title'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="profile-referral">
          <div class="profile-row profile-referral-head">
            <span class="profile-label"><?php echo htmlspecialchars(__('profile.referral_title'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <p class="profile-referral-hint"><?php echo htmlspecialchars(__('profile.referral_hint'), ENT_QUOTES, 'UTF-8'); ?></p>
          <div class="profile-referral-row">
            <input type="text" readonly class="input profile-referral-input" id="profile-ref-url" value="<?php echo htmlspecialchars($refUrl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__('profile.referral_title'), ENT_QUOTES, 'UTF-8'); ?>" />
            <button type="button" class="btn btn-outline" id="profile-ref-copy"><?php echo htmlspecialchars(__('profile.referral_copy'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
          <div class="profile-invited">
            <div class="profile-label profile-invited-title"><?php echo htmlspecialchars(__('profile.invited_title'), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if (!$invitedList): ?>
              <p class="profile-invited-empty"><?php echo htmlspecialchars(__('profile.invited_empty'), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php else: ?>
              <div class="profile-invited-scroll" tabindex="0">
                <div class="profile-invited-head">
                  <span><?php echo htmlspecialchars(__('profile.invited_col_user'), ENT_QUOTES, 'UTF-8'); ?></span>
                  <span class="profile-invited-col-id"><?php echo htmlspecialchars(__('profile.invited_col_id'), ENT_QUOTES, 'UTF-8'); ?></span>
                  <span><?php echo htmlspecialchars(__('profile.invited_col_date'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php foreach ($invitedList as $inv): ?>
                  <div class="profile-invited-row">
                    <span class="profile-invited-label"><?php echo htmlspecialchars((string)($inv['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="profile-invited-col-id">#<?php echo (int)($inv['id'] ?? 0); ?></span>
                    <span class="profile-invited-date"><?php echo htmlspecialchars((string)($inv['registered_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        </aside>
        <?php endif; ?>
        <script<?php echo farhuaad_csp_nonce_attr(); ?>>
        window.__FARHUAAD_PROFILE__ = <?php echo json_encode(
          [
            'deleteConfirm' => __('profile.delete_confirm'),
            'deleteBtnLabel' => __('profile.delete_account'),
            'referralCopied' => __('profile.referral_copied'),
          ],
          JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
        ); ?>;
        </script>
        <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/theme.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
        <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/profile-page.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
      </section>
      </div>
    </main>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>
  <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/main.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
