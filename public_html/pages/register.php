<?php
require __DIR__ . '/../app/init.php';

$current = farhuaad_current_user();
if ($current) {
  header('Location: ' . farhuaad_url('pages/portfolio.php'));
  exit;
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $honeypot = trim((string)($_POST['website_url'] ?? ''));
  $recaptchaResponse = (string)($_POST['g-recaptcha-response'] ?? '');
  try {
    farhuaad_verify_csrf(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null);
  } catch (Throwable $e) {
    if ($e->getMessage() === 'CSRF_FAILED') {
      $error = __('err.csrf');
    } else {
      $error = __('err.generic_send');
    }
  }

  if ($error === null) {

  // Honeypot: если скрытое поле заполнено – это бот
  if ($honeypot !== '') {
    $error = __('err.generic_send');
  }

  // Проверка Google reCAPTCHA (v3).
  if ($error === null) {
    $secret = (string)($_ENV['RECAPTCHA_SECRET'] ?? '');
    if ($secret === '' || $recaptchaResponse === '') {
      $error = __('err.captcha_invalid');
    } else {
      $endpoint = 'https://www.google.com/recaptcha/api/siteverify';
      $postData = http_build_query([
        'secret' => $secret,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
      ]);

      $verify = false;

      if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_POST => true,
          CURLOPT_POSTFIELDS => $postData,
          CURLOPT_TIMEOUT => 5,
        ]);
        $verify = curl_exec($ch);
        curl_close($ch);
      } else {
        $verify = @file_get_contents($endpoint . '?' . $postData);
      }

      $captcha = json_decode((string)$verify, true);
      $ok = is_array($captcha) && !empty($captcha['success']);

      // Для reCAPTCHA v3 дополнительно проверяем только score.
      if ($ok && isset($captcha['score'])) {
        $score = (float)$captcha['score'];
        if ($score < 0.5) { // порог ужесточён с 0.3 до 0.5
          $ok = false;
        }
      }

      if (!$ok) {
        $error = __('err.captcha_invalid');
      }
    }
  }

  if ($error === null) {

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = __('err.email_invalid');
  } else {
    try {
      $existing = farhuaad_find_user_by_email($email);
      if ($existing) {
        farhuaad_send_otp($email, 'login');
        header('Location: ' . farhuaad_url('pages/verify.php') . '?purpose=login&email=' . urlencode($email));
        exit;
      }

      farhuaad_send_otp($email, 'register');
      header('Location: ' . farhuaad_url('pages/verify.php') . '?purpose=register&email=' . urlencode($email));
      exit;
    } catch (Throwable $e) {
      $code = $e->getMessage();
      if ($code === 'TOO_FAST') $error = __('err.wait_30');
      elseif ($code === 'RATE_LIMIT_IP' || $code === 'RATE_LIMIT_BURST') {
        $error = __('err.rate_limit');
      }
      elseif ($code === 'EMAIL_SEND_FAILED') $error = __('err.email_send');
      else $error = __('err.generic_send');
    }
  }
  }
  }
}
$privacyUrl = htmlspecialchars(farhuaad_url('pages/privacy.php'), ENT_QUOTES, 'UTF-8');
$cookiesUrl = htmlspecialchars(farhuaad_url('pages/cookies.php'), ENT_QUOTES, 'UTF-8');
$privacyText = htmlspecialchars(__('footer.privacy'), ENT_QUOTES, 'UTF-8');
$cookiesText = htmlspecialchars(__('footer.cookies'), ENT_QUOTES, 'UTF-8');
$recaptchaSiteKeyRaw = (string)($_ENV['RECAPTCHA_SITE_KEY'] ?? '');
$recaptchaSiteKey = htmlspecialchars($recaptchaSiteKeyRaw, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('login.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('login.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(farhuaad_asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <?php if ($recaptchaSiteKeyRaw !== ''): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo $recaptchaSiteKey; ?>"></script>
    <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/auth-recaptcha.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
  <?php endif; ?>
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <main class="main">
      <section class="auth">
        <div class="auth-card">
          <div class="auth-top">
            <h1 class="auth-title"><?php echo htmlspecialchars(__('login.title'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="auth-subtitle"><?php echo htmlspecialchars(__('login.subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
          </div>

          <?php if ($error): ?>
            <div class="auth-alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <form method="post" class="auth-form" autocomplete="on"<?php echo $recaptchaSiteKeyRaw !== '' ? ' data-recaptcha-site-key="' . htmlspecialchars($recaptchaSiteKeyRaw, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(farhuaad_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="field">
              <label class="label" for="email"><?php echo htmlspecialchars(__('login.email'), ENT_QUOTES, 'UTF-8'); ?></label>
              <input class="input" id="email" name="email" type="email" required value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <!-- Honeypot поле: скрыто от людей, видно ботам -->
            <div class="auth-honeypot" hidden>
              <label for="website_url">Website</label>
              <input id="website_url" name="website_url" type="text" autocomplete="off" />
            </div>

            <div class="auth-actions">
              <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('login.submit'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <div class="auth-consents">
              <p class="auth-consent-text"><?php echo sprintf(__('login.agree_privacy_fmt'), $privacyUrl, $privacyText); ?></p>
              <p class="auth-consent-text"><?php echo sprintf(__('login.agree_cookies_fmt'), $cookiesUrl, $cookiesText); ?></p>
            </div>
          </form>
        </div>
      </section>
    </main>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>
  <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/main.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
