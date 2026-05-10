<?php
require __DIR__ . '/../app/init.php';

$purpose = (string)($_GET['purpose'] ?? '');
$email = (string)($_GET['email'] ?? '');
$email = mb_strtolower(trim($email));

if ($purpose !== 'login' && $purpose !== 'register') {
  header('Location: ' . farhuaad_url('index.php'));
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: ' . ($purpose === 'login' ? farhuaad_url('pages/login.php') : farhuaad_url('pages/register.php')));
  exit;
}

$nextRaw = (string)($_POST['next'] ?? $_GET['next'] ?? '');
$nextToken = farhuaad_validate_login_next($nextRaw);

$error = null;
$code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    farhuaad_verify_csrf(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null);
  } catch (Throwable $e) {
    if ($e->getMessage() === 'CSRF_FAILED') {
      $error = __('err.csrf');
    } else {
      $error = __('err.generic_action');
    }
  }

  if ($error === null) {
  $action = (string)($_POST['action'] ?? 'verify');
  if ($action === 'resend') {
    try {
      farhuaad_send_otp($email, $purpose);
      $resendLoc = farhuaad_url('pages/verify.php') . '?purpose=' . urlencode($purpose) . '&email=' . urlencode($email);
      if ($nextToken !== null) {
        $resendLoc .= '&next=' . rawurlencode($nextToken);
      }
      header('Location: ' . $resendLoc);
      exit;
    } catch (Throwable $e) {
      $msg = $e->getMessage();
      if ($msg === 'TOO_FAST') $error = __('err.wait_30');
      elseif ($msg === 'RATE_LIMIT_IP' || $msg === 'RATE_LIMIT_BURST') {
        $error = __('err.rate_limit');
      }
      elseif ($msg === 'EMAIL_SEND_FAILED') $error = __('err.email_send');
      else $error = __('err.generic_send');
    }
  } else {
    $code = (string)($_POST['code'] ?? '');
    $code = preg_replace('/\s+/', '', $code);
    try {
      farhuaad_verify_otp($email, $code, $purpose);

      session_regenerate_id(true);

      if ($purpose === 'register') {
        $u = farhuaad_register_email_user($email);
        farhuaad_set_current_user([
          'id' => (string)$u['id'],
          'name' => (string)$u['name'],
          'email' => (string)$u['email'],
          'authMethod' => 'email',
        ]);
      } else {
        $u = farhuaad_login_email($email);
        farhuaad_set_current_user($u);
      }

      if (function_exists('farhuaad_cancel_scheduled_user_delete') && isset($u['id'])) {
        farhuaad_cancel_scheduled_user_delete((string)$u['id']);
      }

      $nextPost = (string)($_POST['next'] ?? '');
      header('Location: ' . farhuaad_redirect_url_for_login_next($nextPost));
      exit;
    } catch (Throwable $e) {
      $msg = $e->getMessage();
      if ($msg === 'OTP_EXPIRED') $error = __('err.otp_expired');
      elseif ($msg === 'OTP_INVALID') $error = __('err.otp_invalid');
      elseif ($msg === 'OTP_TOO_MANY_ATTEMPTS') $error = __('err.otp_attempts');
      elseif ($msg === 'EMAIL_TAKEN' && $purpose === 'register') $error = __('err.email_taken');
      else $error = __('err.verify_failed');
    }
  }
  }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('verify.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('verify.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(farhuaad_asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <main class="main">
      <section class="auth">
        <div class="auth-card">
          <div class="auth-top">
            <h1 class="auth-title"><?php echo htmlspecialchars(__('verify.title'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="auth-subtitle">
              <?php echo htmlspecialchars(__('verify.code_sent_intro'), ENT_QUOTES, 'UTF-8'); ?><strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>
            </p>
          </div>

          <?php if ($error): ?>
            <div class="auth-alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <form method="post" class="auth-form" autocomplete="one-time-code">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(farhuaad_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" />
            <?php if ($nextToken !== null): ?>
              <input type="hidden" name="next" value="<?php echo htmlspecialchars($nextToken, ENT_QUOTES, 'UTF-8'); ?>" />
            <?php endif; ?>
            <div class="field">
              <label class="label" for="code"><?php echo htmlspecialchars(__('verify.code_label'), ENT_QUOTES, 'UTF-8'); ?></label>
              <input class="input" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" placeholder="123456" required value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <div class="auth-actions">
              <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('verify.confirm'), ENT_QUOTES, 'UTF-8'); ?></button>
              <a class="btn btn-outline" href="<?php echo htmlspecialchars($purpose === 'login' ? farhuaad_url('pages/login.php') : farhuaad_url('pages/register.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('verify.back'), ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
          </form>

          <div class="auth-divider"></div>

          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(farhuaad_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="action" value="resend" />
            <?php if ($nextToken !== null): ?>
              <input type="hidden" name="next" value="<?php echo htmlspecialchars($nextToken, ENT_QUOTES, 'UTF-8'); ?>" />
            <?php endif; ?>
            <button class="btn btn-outline auth-wallet-btn" type="submit"><?php echo htmlspecialchars(__('verify.resend'), ENT_QUOTES, 'UTF-8'); ?></button>
          </form>
        </div>
      </section>
    </main>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>
  <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/main.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
