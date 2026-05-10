<?php
declare(strict_types=1);

require __DIR__ . '/../app/init.php';

$user = farhuaad_current_user();
if (!$user || empty($user['id'])) {
  header('Location: ' . farhuaad_url('pages/login.php') . '?next=' . rawurlencode('pages/submit_dispute.php'));
  exit;
}

$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    farhuaad_verify_csrf(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null);
  } catch (Throwable $e) {
    $error = 'Сессия устарела. Обновите страницу.';
  }

  if ($error === null) {
    if (!($pdo instanceof PDO)) {
      $error = 'База данных недоступна.';
    } else {
      $title = (string)($_POST['title'] ?? '');
      $shortDescription = (string)($_POST['short_description'] ?? '');
      $category = (string)($_POST['category'] ?? 'Событие');
      $expiresAtRaw = trim((string)($_POST['expires_at'] ?? ''));
      $expiresAt = str_replace('T', ' ', $expiresAtRaw);
      $linksRaw = (string)($_POST['source_links'] ?? '');
      $sourceLinks = preg_split('/\r\n|\r|\n/', $linksRaw) ?: [];

      try {
        farhuaad_ensure_dispute_submissions_table($pdo);
        farhuaad_migrate_dispute_submissions_table($pdo);
        $submissionId = farhuaad_submit_dispute_for_moderation(
          $pdo,
          (int)$user['id'],
          $title,
          $shortDescription,
          $category,
          $sourceLinks,
          $expiresAt
        );
        $message = 'Заявка отправлена на модерацию. Номер: #' . $submissionId . '.';
      } catch (RuntimeException $e) {
        $code = $e->getMessage();
        if ($code === 'LEGAL_BLOCKED') {
          $error = 'Текст не прошел проверку безопасности. Сделайте формулировку нейтральной и проверяемой.';
        } else {
          $error = 'Проверьте поля формы: заголовок, описание и дедлайн обязательны.';
        }
      } catch (Throwable $e) {
        $error = 'Не удалось отправить заявку. Попробуйте позже.';
      }
    }
  }
}

$csrf = farhuaad_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(farhuaad_html_lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <title><?php echo htmlspecialchars(__('submit_dispute.meta_title'), ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars(__('submit_dispute.meta_desc'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(farhuaad_asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style<?php echo farhuaad_csp_nonce_attr(); ?>>
    .submit-wrap { width: 100%; max-width: none; margin: 0; display: grid; gap: 1rem; }
    .submit-card { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 1rem; }
    .submit-grid { display: grid; gap: 0.85rem; }
    .submit-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
    html[data-theme="light"] .submit-btn-primary {
      background: #000 !important;
      border-color: #000 !important;
      color: #fff !important;
    }
    html[data-theme="light"] .submit-btn-primary:hover,
    html[data-theme="light"] .submit-btn-primary:focus {
      background: #111 !important;
      border-color: #111 !important;
      color: #fff !important;
    }
  </style>
</head>
<body>
  <div class="app">
    <?php include __DIR__ . '/../partials/header.php'; ?>
    <main class="main">
      <section class="panel submit-wrap">
        <div class="submit-card">
          <h1 class="panel-title"><?php echo htmlspecialchars(__('submit_dispute.title'), ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="panel-subtitle"><?php echo htmlspecialchars(__('submit_dispute.subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>

          <?php if ($message): ?>
            <div class="auth-alert auth-alert--ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="auth-alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <form method="post" class="submit-grid">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />

            <div class="field">
              <label class="label" for="submit-title"><?php echo htmlspecialchars(__('submit_dispute.field_title'), ENT_QUOTES, 'UTF-8'); ?></label>
              <input class="input" id="submit-title" name="title" type="text" maxlength="255" required placeholder="<?php echo htmlspecialchars(__('submit_dispute.field_title_ph'), ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <div class="field">
              <label class="label" for="submit-category"><?php echo htmlspecialchars(__('submit_dispute.field_category'), ENT_QUOTES, 'UTF-8'); ?></label>
              <select class="input" id="submit-category" name="category" required>
                <option value="Событие"><?php echo htmlspecialchars(__('cat.event'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="Крипто"><?php echo htmlspecialchars(__('cat.crypto'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="Экономика"><?php echo htmlspecialchars(__('cat.economy'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="Политика"><?php echo htmlspecialchars(__('cat.politics'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="Технологии"><?php echo htmlspecialchars(__('cat.tech'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="Спорт"><?php echo htmlspecialchars(__('cat.sport'), ENT_QUOTES, 'UTF-8'); ?></option>
              </select>
            </div>

            <div class="field">
              <label class="label" for="submit-expires"><?php echo htmlspecialchars(__('submit_dispute.field_expires'), ENT_QUOTES, 'UTF-8'); ?></label>
              <input class="input" id="submit-expires" name="expires_at" type="datetime-local" required />
            </div>

            <div class="field">
              <label class="label" for="submit-description"><?php echo htmlspecialchars(__('submit_dispute.field_description'), ENT_QUOTES, 'UTF-8'); ?></label>
              <textarea class="input" id="submit-description" name="short_description" rows="7" maxlength="8000" required placeholder="<?php echo htmlspecialchars(__('submit_dispute.field_description_ph'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
            </div>

            <div class="field">
              <label class="label" for="submit-links"><?php echo htmlspecialchars(__('submit_dispute.field_sources'), ENT_QUOTES, 'UTF-8'); ?></label>
              <textarea class="input" id="submit-links" name="source_links" rows="4" placeholder="https://..."></textarea>
            </div>

            <div class="submit-actions">
              <button class="btn btn-primary submit-btn-primary" type="submit"><?php echo htmlspecialchars(__('submit_dispute.send'), ENT_QUOTES, 'UTF-8'); ?></button>
              <a class="btn btn-outline" href="<?php echo htmlspecialchars(farhuaad_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('submit_dispute.back'), ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
          </form>
        </div>
      </section>
    </main>
    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>
</body>
</html>
