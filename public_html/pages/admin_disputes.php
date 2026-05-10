<?php
declare(strict_types=1);

require __DIR__ . '/../app/init.php';

header('X-Robots-Tag: noindex, nofollow');

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    farhuaad_verify_csrf(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null);
  } catch (Throwable $e) {
    $error = 'Сессия устарела. Обновите страницу.';
  }

  if ($error === null) {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'logout') {
      farhuaad_dispute_admin_set_session(false);
      $message = 'Вы вышли.';
    } elseif ($action === 'login') {
      $login = (string)($_POST['login'] ?? '');
      $pw = (string)($_POST['password'] ?? '');
      if (!farhuaad_dispute_admin_password_configured()) {
        $error = 'Задайте DISPUTE_ADMIN_PASSWORD в .env на сервере.';
      } elseif (!farhuaad_dispute_admin_login_configured()) {
        $error = 'Логин администратора не настроен.';
      } elseif (farhuaad_dispute_admin_check_login($login) && farhuaad_dispute_admin_check_password($pw)) {
        farhuaad_dispute_admin_set_session(true);
        $message = 'Вход выполнен.';
      } else {
        $error = 'Неверный логин или пароль.';
      }
    } elseif ($action === 'resolve') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        $id = (int)($_POST['dispute_id'] ?? 0);
        $side = (string)($_POST['winning_side'] ?? '');
        $note = (string)($_POST['note'] ?? '');
        try {
          farhuaad_admin_resolve_dispute($pdo, $id, $side, $note);
          if (function_exists('farhuaad_settle_dispute_payouts')) {
            farhuaad_settle_dispute_payouts($pdo);
          }
          $message = 'Спор #' . $id . ' закрыт: сторона «' . $side . '». Выплаты по этому и другим закрытым спорам обработаны.';
        } catch (RuntimeException $e) {
          $code = $e->getMessage();
          if ($code === 'NOT_FOUND') {
            $error = 'Спор не найден или уже закрыт.';
          } elseif ($code === 'INVALID_SIDE') {
            $error = 'Некорректная сторона.';
          } else {
            $error = 'Не удалось сохранить исход.';
          }
        }
      }
    } elseif ($action === 'create') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        $title = (string)($_POST['title'] ?? '');
        $shortDescription = (string)($_POST['short_description'] ?? '');
        $category = (string)($_POST['category'] ?? 'Событие');
        $expiresAtRaw = trim((string)($_POST['expires_at'] ?? ''));
        $expiresAt = str_replace('T', ' ', $expiresAtRaw);
        $linksRaw = (string)($_POST['source_links'] ?? '');
        $sourceLinks = preg_split('/\r\n|\r|\n/', $linksRaw) ?: [];

        if ($error === null) {
          try {
            $newId = farhuaad_admin_create_dispute(
              $pdo,
              $title,
              $shortDescription,
              $category,
              $sourceLinks,
              $expiresAt,
              (string)($_POST['title_en'] ?? ''),
              (string)($_POST['short_description_en'] ?? '')
            );
            $message = 'Спор создан вручную. ID: ' . $newId . '.';
          } catch (RuntimeException $e) {
            $code = $e->getMessage();
            if ($code === 'DUPLICATE_ACTIVE') {
              $error = 'Активный спор с таким заголовком уже существует.';
            } elseif ($code === 'LEGAL_BLOCKED') {
              $error = 'Спор отклонен фильтром безопасности. Измените формулировку на нейтральную и проверяемую по публичным источникам.';
            } else {
              $error = 'Проверьте поля формы: заголовок и дедлайн обязательны.';
            }
          }
        }
      }
    } elseif ($action === 'edit_submission') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $title = (string)($_POST['title'] ?? '');
        $shortDescription = (string)($_POST['short_description'] ?? '');
        $category = (string)($_POST['category'] ?? 'Событие');
        $expiresAtRaw = trim((string)($_POST['expires_at'] ?? ''));
        $expiresAt = str_replace('T', ' ', $expiresAtRaw);
        $linksRaw = (string)($_POST['source_links'] ?? '');
        $sourceLinks = preg_split('/\r\n|\r|\n/', $linksRaw) ?: [];
        try {
          farhuaad_admin_update_dispute_submission(
            $pdo,
            $submissionId,
            $title,
            $shortDescription,
            $category,
            $sourceLinks,
            $expiresAt
          );
          $message = 'Заявка обновлена. ID: ' . $submissionId . '.';
        } catch (RuntimeException $e) {
          $code = $e->getMessage();
          if ($code === 'NOT_FOUND') {
            $error = 'Заявка не найдена.';
          } else {
            $error = 'Не удалось обновить заявку. Проверьте поля.';
          }
        }
      }
    } elseif ($action === 'approve_submission') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $note = (string)($_POST['note'] ?? '');
        try {
          $newDisputeId = farhuaad_admin_accept_dispute_submission($pdo, $submissionId, 0, $note);
          $message = 'Заявка #' . $submissionId . ' принята и опубликована как спор #' . $newDisputeId . '.';
        } catch (RuntimeException $e) {
          $code = $e->getMessage();
          if ($code === 'NOT_FOUND') {
            $error = 'Заявка не найдена.';
          } elseif ($code === 'ALREADY_REVIEWED') {
            $error = 'Эта заявка уже обработана.';
          } elseif ($code === 'DUPLICATE_ACTIVE') {
            $error = 'Похожий спор уже есть. Отредактируйте текст заявки.';
          } elseif ($code === 'LEGAL_BLOCKED') {
            $error = 'Текст заявки заблокирован фильтром безопасности.';
          } else {
            $error = 'Не удалось принять заявку.';
          }
        }
      }
    } elseif ($action === 'reject_submission') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $note = (string)($_POST['note'] ?? '');
        try {
          farhuaad_admin_reject_dispute_submission($pdo, $submissionId, 0, $note);
          $message = 'Заявка #' . $submissionId . ' отклонена.';
        } catch (RuntimeException $e) {
          $code = $e->getMessage();
          if ($code === 'NOT_FOUND') {
            $error = 'Заявка не найдена.';
          } elseif ($code === 'ALREADY_REVIEWED') {
            $error = 'Эта заявка уже обработана.';
          } else {
            $error = 'Не удалось отклонить заявку.';
          }
        }
      }
    } elseif ($action === 'generate_ai') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        $count = (int)($_POST['count'] ?? 3);
        $count = max(1, min(10, $count));
        try {
          $inserted = farhuaad_insert_generated_disputes($pdo, $count);
          if ($inserted > 0) {
            $message = 'Сгенерировано ИИ споров: ' . $inserted . '.';
          } else {
            $error = 'ИИ не вернул новых уникальных споров. Попробуйте еще раз.';
          }
        } catch (Throwable $e) {
          $error = 'Не удалось сгенерировать ИИ споры.';
        }
      }
    } elseif ($action === 'toggle_auto_generation') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } else {
        $enabled = ((string)($_POST['enabled'] ?? '1')) === '1';
        farhuaad_set_dispute_auto_generation_enabled($enabled);
        $message = $enabled
          ? 'Автоматическая генерация споров включена.'
          : 'Автоматическая генерация споров остановлена.';
      }
    } elseif ($action === 'delete_selected') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        $ids = $_POST['delete_ids'] ?? [];
        if (!is_array($ids) || !$ids) {
          $error = 'Выберите хотя бы один спор для удаления.';
        } else {
          try {
            $deleted = farhuaad_admin_delete_disputes($pdo, array_map('intval', $ids));
            $message = 'Удалено споров: ' . $deleted . '.';
          } catch (Throwable $e) {
            $error = 'Не удалось удалить выбранные споры.';
          }
        }
      }
    } elseif ($action === 'delete_all_disputes') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        try {
          $deleted = farhuaad_admin_delete_all_disputes($pdo);
          $message = 'Удалены все споры. Количество: ' . $deleted . '.';
        } catch (Throwable $e) {
          $error = 'Не удалось удалить все споры.';
        }
      }
    } elseif ($action === 'edit_dispute') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        $id = (int)($_POST['dispute_id'] ?? 0);
        $title = (string)($_POST['title'] ?? '');
        $shortDescription = (string)($_POST['short_description'] ?? '');
        $titleEn = (string)($_POST['title_en'] ?? '');
        $shortDescriptionEn = (string)($_POST['short_description_en'] ?? '');
        $linksRaw = (string)($_POST['source_links'] ?? '');
        $sourceLinks = preg_split('/\r\n|\r|\n/', $linksRaw) ?: [];
        try {
          farhuaad_admin_update_dispute(
            $pdo,
            $id,
            $title,
            $shortDescription,
            $sourceLinks,
            $titleEn,
            $shortDescriptionEn
          );
          $message = 'Спор обновлен. ID: ' . $id . '.';
        } catch (RuntimeException $e) {
          $code = $e->getMessage();
          if ($code === 'NOT_FOUND') {
            $error = 'Спор не найден.';
          } elseif ($code === 'LEGAL_BLOCKED') {
            $error = 'Текст отклонен фильтром безопасности. Сделайте формулировку нейтральной и проверяемой.';
          } else {
            $error = 'Не удалось обновить спор. Проверьте заголовок и описание.';
          }
        }
      }
    } elseif ($action === 'reset_balances') {
      if (!farhuaad_dispute_admin_session_ok()) {
        $error = 'Нужен вход.';
      } elseif (!($pdo instanceof PDO)) {
        $error = 'База данных недоступна.';
      } else {
        try {
          if (function_exists('farhuaad_reset_balances_all')) {
            farhuaad_reset_balances_all($pdo);
          } else {
            $pdo->exec("UPDATE users SET balance = 1000");
          }
          $message = 'Баланс всех пользователей сброшен до 1000 A.';
        } catch (Throwable $e) {
          $error = 'Не удалось сбросить балансы.';
        }
      }
    }
  }
}

$configured = farhuaad_dispute_admin_password_configured() && farhuaad_dispute_admin_login_configured();
$authed = farhuaad_dispute_admin_session_ok();
$autoGenerationEnabled = farhuaad_is_dispute_auto_generation_enabled();
$activeRows = [];
$allRows = [];
$pendingSubmissions = [];

if ($authed && $pdo instanceof PDO) {
  try {
    farhuaad_ensure_disputes_table($pdo);
    farhuaad_migrate_disputes_table($pdo);
    farhuaad_ensure_dispute_submissions_table($pdo);
    farhuaad_migrate_dispute_submissions_table($pdo);
    if (function_exists('farhuaad_migrate_users_referral_column')) {
      farhuaad_migrate_users_referral_column($pdo);
    }
    $stmt = $pdo->query(
      "SELECT id, title, title_en, short_description, short_description_en, source_links, expires_at, status, created_at, creation_source
       FROM disputes
       WHERE status = 'active'
       ORDER BY id DESC
       LIMIT 500"
    );
    $activeRows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $allStmt = $pdo->query(
      "SELECT id, title, expires_at, status, created_at, creation_source
       FROM disputes
       ORDER BY id DESC
       LIMIT 500"
    );
    $allRows = $allStmt ? $allStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $pendingStmt = $pdo->query(
      "SELECT id, user_id, title, short_description, category, source_links, expires_at, created_at
       FROM dispute_submissions
       WHERE status = 'pending'
       ORDER BY id DESC
       LIMIT 500"
    );
    $pendingSubmissions = $pendingStmt ? $pendingStmt->fetchAll(PDO::FETCH_ASSOC) : [];
  } catch (Throwable $e) {
    $error = $error ?? 'Ошибка чтения споров.';
  }
}

$csrf = farhuaad_csrf_token();
$pendingCount = count($pendingSubmissions);
$activeCount = count($activeRows);
$allCount = count($allRows);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(function_exists('farhuaad_html_lang') ? farhuaad_html_lang() : 'ru', ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include __DIR__ . '/../partials/theme_head_script.php'; ?>
  <meta name="robots" content="noindex,nofollow" />
  <title>Админка — исходы споров</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(farhuaad_asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style<?php echo farhuaad_csp_nonce_attr(); ?>>
    .admin-wrap { max-width: 920px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
    .admin-card { background: var(--card-bg, #fff); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1rem;
      box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid var(--border, #e8e8ef); }
    .admin-title { font-size: 1.35rem; font-weight: 700; margin-bottom: 0.5rem; }
    .admin-hint { color: var(--muted, #666); font-size: 0.9rem; margin-bottom: 1rem; line-height: 1.45; }
    .admin-row { display: grid; gap: 0.75rem; padding: 1rem 0; border-bottom: 1px solid var(--border, #eee); }
    .admin-row:last-child { border-bottom: 0; }
    .admin-row-title { font-weight: 600; font-size: 0.95rem; }
    .admin-row-meta { font-size: 0.8rem; color: var(--muted, #666); }
    .admin-resolve { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; margin-top: 0.5rem; }
    .admin-resolve .input { min-width: 200px; flex: 1; }
    .admin-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; }
    .admin-generate-inline { display:flex; align-items:flex-end; gap:.6rem; flex-wrap:wrap; margin-bottom:1rem; }
    .admin-outcome-help {
      font-size: 0.92rem;
      color: #111827;
      line-height: 1.5;
      margin-top: 0.55rem;
      padding: 0.6rem 0.75rem;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
    }
    .admin-outcome-help strong {
      display: inline-block;
      font-size: 0.95rem;
      font-weight: 800;
      color: #111827;
      margin-right: 0.3rem;
      text-transform: uppercase;
      letter-spacing: 0.02em;
    }
    .admin-card .input,
    .admin-card textarea.input,
    .admin-card select.input {
      background: #ffffff !important;
      color: #111827 !important;
      border: 1px solid #d1d5db !important;
      border-radius: 12px;
      box-shadow: inset 0 1px 2px rgba(0,0,0,.04);
    }
    .admin-card .input::placeholder,
    .admin-card textarea.input::placeholder {
      color: #9ca3af !important;
    }
    .admin-card .input:focus,
    .admin-card textarea.input:focus,
    .admin-card select.input:focus {
      border-color: #2563eb !important;
      box-shadow: 0 0 0 3px rgba(37,99,235,.15) !important;
      outline: none;
    }
    .admin-title--flush { margin: 0; }
    .admin-inline-form { margin: 0; }
    .admin-alert--ok { background: #e8f5e9; color: #1b5e20; border-color: #a5d6a7; }
    .admin-form-spaced { margin-bottom: 1rem; }
    .admin-resolve--stack { margin-bottom: 1rem; align-items: stretch; }
    .admin-field--grow { flex: 2; min-width: 260px; }
    .admin-field--narrow { min-width: 180px; }
    .admin-field--wide { min-width: 220px; }
    .admin-field--flat { margin: 0; }
    .admin-field--grow220 { flex: 2; min-width: 220px; margin: 0; }
    .admin-btn-compact {
      display: inline-flex;
      width: auto;
      min-width: 0;
      max-width: 100%;
      flex: 0 0 auto;
      align-self: auto;
      justify-content: center;
      white-space: nowrap;
    }
    .admin-resolve--stack .admin-btn-compact {
      align-self: flex-start;
      flex-basis: 100%;
      margin-top: 0.15rem;
    }
    .admin-btn-save {
      font-weight: 700;
    }
    .admin-danger-row { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: center; margin: 1rem 0; }
    .admin-delete-list { max-height: 360px; overflow: auto; border: 1px solid var(--border, #eee); border-radius: 10px; padding: 0.75rem; }
    .admin-delete-item { display: flex; align-items: center; gap: 0.55rem; padding: 0.35rem 0; border-bottom: 1px dashed #e5e7eb; }
    .admin-delete-item:last-child { border-bottom: 0; }
    .admin-delete-item-meta { color: var(--muted, #666); font-size: 0.82rem; }
    .admin-submission-note { margin-top: .5rem; }
    .admin-summary { display: flex; flex-wrap: wrap; gap: 0.55rem; margin: 0 0 1rem; }
    .admin-summary-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.38rem 0.7rem;
      border-radius: 999px;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
      color: #111827;
      font-size: 0.82rem;
      font-weight: 600;
    }
    .admin-divider-title {
      margin: 1.4rem 0 0.7rem;
      padding-top: 0.65rem;
      border-top: 1px solid #e5e7eb;
      font-size: 1.08rem;
      font-weight: 700;
    }
    .admin-list {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      overflow: hidden;
      margin-bottom: 1rem;
      background: #fff;
    }
    .admin-list .admin-row {
      padding: 0.9rem 1rem;
    }
    .admin-list .admin-row:nth-child(odd) {
      background: #fcfcfd;
    }
    .admin-row-title-line {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.7rem;
      flex-wrap: wrap;
    }
    .admin-search {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      margin: 0 0 0.75rem;
      flex-wrap: wrap;
    }
    .admin-search .input {
      min-width: 260px;
      flex: 1;
    }
    .admin-search-note {
      font-size: 0.82rem;
      color: #6b7280;
    }
    .admin-row.is-hidden-by-search {
      display: none;
    }
    .admin-search-empty {
      display: none;
      padding: 0.85rem 1rem;
      font-size: 0.88rem;
      color: #6b7280;
      border-top: 1px dashed #e5e7eb;
      background: #fff;
    }
    .admin-search-empty.is-visible {
      display: block;
    }
    .admin-pill {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 0.18rem 0.55rem;
      background: #eef2ff;
      color: #1e3a8a;
      font-size: 0.76rem;
      font-weight: 700;
      border: 1px solid #c7d2fe;
      white-space: nowrap;
    }
    .auth-form .btn.btn-primary {
      background: #000 !important;
      border-color: #000 !important;
      color: #fff !important;
    }
    .auth-form .btn.btn-primary:hover,
    .auth-form .btn.btn-primary:focus {
      background: #111 !important;
      border-color: #111 !important;
    }
    html[data-theme="dark"] .admin-card {
      background: #0b1220;
      border-color: #1f2937;
      box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
      color: #e5e7eb;
    }
    html[data-theme="dark"] .admin-title,
    html[data-theme="dark"] .admin-divider-title,
    html[data-theme="dark"] .admin-row-title {
      color: #f9fafb;
    }
    html[data-theme="dark"] .admin-hint,
    html[data-theme="dark"] .admin-row-meta,
    html[data-theme="dark"] .admin-search-note,
    html[data-theme="dark"] .admin-delete-item-meta {
      color: #9ca3af;
    }
    html[data-theme="dark"] .admin-divider-title,
    html[data-theme="dark"] .admin-row,
    html[data-theme="dark"] .admin-search-empty {
      border-color: #1f2937;
    }
    html[data-theme="dark"] .admin-list {
      background: #0f172a;
      border-color: #1f2937;
    }
    html[data-theme="dark"] .admin-list .admin-row:nth-child(odd) {
      background: #111b30;
    }
    html[data-theme="dark"] .admin-summary-chip {
      background: #111827;
      border-color: #374151;
      color: #e5e7eb;
    }
    html[data-theme="dark"] .admin-pill {
      background: #1e293b;
      border-color: #334155;
      color: #cbd5e1;
    }
    html[data-theme="dark"] .admin-outcome-help {
      background: #111827;
      border-color: #374151;
      color: #e5e7eb;
    }
    html[data-theme="dark"] .admin-outcome-help strong {
      color: #f3f4f6;
    }
    html[data-theme="dark"] .admin-card .input,
    html[data-theme="dark"] .admin-card textarea.input,
    html[data-theme="dark"] .admin-card select.input {
      background: #0f172a !important;
      color: #f3f4f6 !important;
      border-color: #334155 !important;
      box-shadow: inset 0 1px 2px rgba(0,0,0,.35);
    }
    html[data-theme="dark"] .admin-card .input::placeholder,
    html[data-theme="dark"] .admin-card textarea.input::placeholder {
      color: #64748b !important;
    }
    html[data-theme="dark"] .admin-card .btn.btn-outline {
      border-color: #475569 !important;
      color: #e5e7eb !important;
      background: #0f172a !important;
    }
    html[data-theme="dark"] .admin-card .btn.btn-outline:hover,
    html[data-theme="dark"] .admin-card .btn.btn-outline:focus {
      border-color: #64748b !important;
      background: #1e293b !important;
      color: #f8fafc !important;
    }
    html[data-theme="light"] .admin-card .btn {
      font-weight: 600;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }
    html[data-theme="light"] .admin-card .btn.btn-primary {
      background: #111827 !important;
      border-color: #111827 !important;
      color: #ffffff !important;
    }
    html[data-theme="light"] .admin-card .btn.btn-primary:hover,
    html[data-theme="light"] .admin-card .btn.btn-primary:focus {
      background: #1f2937 !important;
      border-color: #1f2937 !important;
      color: #ffffff !important;
      box-shadow: 0 3px 10px rgba(17, 24, 39, 0.2);
    }
    html[data-theme="light"] .admin-card .btn.btn-outline {
      background: #ffffff !important;
      border-color: #cbd5e1 !important;
      color: #111827 !important;
    }
    html[data-theme="light"] .admin-card .btn.btn-outline:hover,
    html[data-theme="light"] .admin-card .btn.btn-outline:focus {
      background: #f8fafc !important;
      border-color: #94a3b8 !important;
      color: #0f172a !important;
    }
    html[data-theme="light"] .admin-card .btn.admin-btn-save {
      background: #000000 !important;
      border-color: #000000 !important;
      color: #ffffff !important;
    }
    html[data-theme="light"] .admin-card .btn.admin-btn-save:hover,
    html[data-theme="light"] .admin-card .btn.admin-btn-save:focus {
      background: #111827 !important;
      border-color: #111827 !important;
      color: #ffffff !important;
    }
    html[data-theme="dark"] .admin-card .btn.admin-btn-save {
      background: #ffffff !important;
      border-color: #ffffff !important;
      color: #0f172a !important;
    }
    html[data-theme="dark"] .admin-card .btn.admin-btn-save:hover,
    html[data-theme="dark"] .admin-card .btn.admin-btn-save:focus {
      background: #f3f4f6 !important;
      border-color: #f3f4f6 !important;
      color: #0b1220 !important;
    }
  </style>
</head>
<body>
  <div class="app">
    <main class="main">
      <div class="admin-wrap">
        <div class="admin-top">
          <h1 class="admin-title admin-title--flush">Исходы споров</h1>
          <?php if ($authed): ?>
            <form method="post" class="admin-inline-form">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="logout" />
              <button class="btn btn-outline" type="submit">Выйти</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if ($message): ?>
          <div class="auth-alert admin-alert--ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="auth-alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!$configured): ?>
          <div class="admin-card">
            <p class="admin-hint">В файле <code>.env</code> задайте: <code>DISPUTE_ADMIN_LOGIN=admin</code> и <code>DISPUTE_ADMIN_PASSWORD=...</code>, затем откройте страницу снова.</p>
          </div>
        <?php elseif (!$authed): ?>
          <div class="admin-card auth">
            <p class="admin-hint">Вход только для администратора. Автозакрытие споров по-прежнему через Claude (cron); здесь — ручная страховка.</p>
            <form method="post" class="auth-form">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="login" />
              <div class="field">
                <label class="label" for="login">Логин</label>
                <input class="input" type="text" id="login" name="login" required autocomplete="username" />
              </div>
              <div class="field">
                <label class="label" for="password">Пароль</label>
                <input class="input" type="password" id="password" name="password" required autocomplete="current-password" />
              </div>
              <button class="btn btn-primary" type="submit">Войти</button>
            </form>
          </div>
        <?php else: ?>
          <div class="admin-card">
            <p class="admin-hint">
              Закрывайте только активные споры. После сохранения сработает <code>farhuaad_settle_dispute_payouts</code> при следующем обходе (cron или загрузка рынков).
              Порог уверенности Claude: переменная <code>CLAUDE_RESOLVE_MIN_CONFIDENCE</code> (по умолчанию 0.78).
            </p>
            <div class="admin-summary">
              <span class="admin-summary-chip">На модерации: <?php echo $pendingCount; ?></span>
              <span class="admin-summary-chip">Активных споров: <?php echo $activeCount; ?></span>
              <span class="admin-summary-chip">Всего споров: <?php echo $allCount; ?></span>
            </div>
            <form id="admin-reset-balances-form" class="admin-form-spaced" method="post" data-confirm-reset="Сбросить балансы всех пользователей до 1000 A? Это действие нельзя отменить.">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="reset_balances" />
              <button class="btn btn-outline" type="submit">Сбросить балансы до 1000 A</button>
            </form>
            <form method="post" class="admin-form-spaced">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="toggle_auto_generation" />
              <?php if ($autoGenerationEnabled): ?>
                <input type="hidden" name="enabled" value="0" />
                <button class="btn btn-outline" type="submit">Остановить автогенерацию споров</button>
              <?php else: ?>
                <input type="hidden" name="enabled" value="1" />
                <button class="btn btn-primary" type="submit">Возобновить автогенерацию споров</button>
              <?php endif; ?>
            </form>
            <h2 class="admin-divider-title">Создание и генерация</h2>
            <form method="post" class="admin-resolve admin-resolve--stack">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="create" />
              <div class="field admin-field--grow">
                <label class="label" for="create-title">Заголовок спора</label>
                <input class="input" id="create-title" name="title" type="text" maxlength="255" required placeholder="Например: Поднимется ли BTC выше $120k до 1 июля?" />
              </div>
              <div class="field admin-field--narrow">
                <label class="label" for="create-category">Категория</label>
                <select class="input" id="create-category" name="category" required>
                  <option value="Событие">Событие</option>
                  <option value="Крипто">Крипто</option>
                  <option value="Экономика">Экономика</option>
                  <option value="Политика">Политика</option>
                  <option value="Технологии">Технологии</option>
                  <option value="Спорт">Спорт</option>
                </select>
              </div>
              <div class="field admin-field--wide">
                <label class="label" for="create-expires-at">Дедлайн</label>
                <input class="input" id="create-expires-at" name="expires_at" type="datetime-local" required />
              </div>
              <div class="field admin-field--grow">
                <label class="label" for="create-short-description">Описание (правила исхода, до 8000 символов)</label>
                <textarea class="input" id="create-short-description" name="short_description" rows="6" maxlength="8000" required placeholder="Полный текст правил: что считается «Да»/«Нет», дедлайн, источники факта"></textarea>
              </div>
              <div class="field admin-field--grow">
                <label class="label" for="create-title-en">Заголовок (EN, необязательно)</label>
                <input class="input" id="create-title-en" name="title_en" type="text" maxlength="255" placeholder="English title, same yes/no question" />
              </div>
              <div class="field admin-field--grow">
                <label class="label" for="create-short-description-en">Описание EN (необязательно, до 8000 символов)</label>
                <textarea class="input" id="create-short-description-en" name="short_description_en" rows="5" maxlength="8000" placeholder="English rules text"></textarea>
              </div>
              <div class="field admin-field--grow">
                <label class="label" for="create-source-links">Источники (по одному URL на строку)</label>
                <textarea class="input" id="create-source-links" name="source_links" rows="3" placeholder="https://..."></textarea>
              </div>
              <button class="btn btn-primary admin-btn-compact" type="submit">Создать спор вручную</button>
            </form>
            <form method="post" class="admin-generate-inline">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="generate_ai" />
              <div class="field admin-field--narrow">
                <label class="label" for="generate-ai-count">Сгенерировать ИИ споров</label>
                <input class="input" id="generate-ai-count" name="count" type="number" min="1" max="10" value="3" />
              </div>
              <button class="btn btn-primary" type="submit">Сгенерировать ИИ</button>
            </form>
            <h2 class="admin-divider-title">Массовые действия</h2>
            <form method="post" class="admin-form-spaced" data-confirm-message="Удалить выбранные споры? Это действие нельзя отменить." data-confirm-danger="1" data-confirm-text="Удалить выбранные">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="delete_selected" />
              <div class="admin-danger-row">
                <button class="btn btn-outline" type="button" id="admin-select-all-disputes">Выбрать все</button>
                <button class="btn btn-outline" type="button" id="admin-clear-all-disputes">Снять выбор</button>
                <button class="btn btn-primary" type="submit">Удалить выбранные споры</button>
              </div>
              <div class="admin-delete-list">
                <?php if (!$allRows): ?>
                  <p class="admin-hint">Нет споров для удаления.</p>
                <?php else: ?>
                  <?php foreach ($allRows as $allIndex => $row): ?>
                    <?php
                    $did = (int)($row['id'] ?? 0);
                    $dtitle = (string)($row['title'] ?? '');
                    $dstatus = (string)($row['status'] ?? '');
                    $dexpires = (string)($row['expires_at'] ?? '');
                    $displayNum = (int)$allIndex + 1;
                    ?>
                    <label class="admin-delete-item">
                      <input type="checkbox" name="delete_ids[]" value="<?php echo $did; ?>" class="admin-delete-checkbox" />
                      <span>#<?php echo $displayNum; ?> — <?php echo htmlspecialchars($dtitle, ENT_QUOTES, 'UTF-8'); ?></span>
                      <span class="admin-delete-item-meta">(ID: <?php echo $did; ?> · <?php echo htmlspecialchars($dstatus, ENT_QUOTES, 'UTF-8'); ?> · до <?php echo htmlspecialchars($dexpires, ENT_QUOTES, 'UTF-8'); ?>)</span>
                    </label>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </form>
            <form method="post" class="admin-form-spaced" data-confirm-message="Удалить ВСЕ споры и ставки по ним? Это действие нельзя отменить." data-confirm-danger="1" data-confirm-text="Удалить все">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="delete_all_disputes" />
              <button class="btn btn-outline" type="submit">Удалить все споры</button>
            </form>
            <h2 class="admin-divider-title">Заявки пользователей на модерацию</h2>
            <?php if (!$pendingSubmissions): ?>
              <p class="admin-hint">Новых заявок нет.</p>
            <?php else: ?>
              <div class="admin-search">
                <input class="input" id="admin-submissions-search" type="search" placeholder="Поиск заявок: заголовок, ID, категория, пользователь..." data-search-target="#admin-submissions-list" />
                <span class="admin-search-note">Найдено: <strong id="admin-submissions-search-count"><?php echo $pendingCount; ?></strong></span>
              </div>
              <div class="admin-list" id="admin-submissions-list">
              <?php foreach ($pendingSubmissions as $submissionIndex => $submission): ?>
                <?php
                $submissionId = (int)($submission['id'] ?? 0);
                $submissionDisplayNum = (int)$submissionIndex + 1;
                $submissionTitle = (string)($submission['title'] ?? '');
                $submissionDescription = (string)($submission['short_description'] ?? '');
                $submissionCategory = (string)($submission['category'] ?? 'Событие');
                $submissionExpiresAt = (string)($submission['expires_at'] ?? '');
                $submissionSourceLinksRaw = (string)($submission['source_links'] ?? '');
                $submissionSourceLinksDecoded = json_decode($submissionSourceLinksRaw, true);
                $submissionSourceLinksList = is_array($submissionSourceLinksDecoded) ? $submissionSourceLinksDecoded : [];
                $submissionSourceLinksText = implode("\n", array_values(array_filter($submissionSourceLinksList, static fn($v) => is_string($v) && trim($v) !== '')));
                $submissionCreatedAt = (string)($submission['created_at'] ?? '');
                $submissionUserId = (int)($submission['user_id'] ?? 0);
                ?>
                <?php
                $submissionSearchText = implode(' ', [
                  'номер',
                  $submissionDisplayNum,
                  '#' . $submissionDisplayNum,
                  'id',
                  $submissionId,
                  '#' . $submissionId,
                  $submissionUserId,
                  $submissionTitle,
                  $submissionCategory,
                  $submissionCreatedAt,
                  $submissionExpiresAt,
                  $submissionDescription,
                  $submissionSourceLinksText,
                ]);
                ?>
                <div
                  class="admin-row"
                  data-search-text="<?php echo htmlspecialchars($submissionSearchText, ENT_QUOTES, 'UTF-8'); ?>"
                  data-search-id="<?php echo $submissionId; ?>"
                  data-search-num="<?php echo $submissionDisplayNum; ?>"
                >
                  <div>
                    <div class="admin-row-title-line">
                      <div class="admin-row-title">#<?php echo $submissionDisplayNum; ?> — <?php echo htmlspecialchars($submissionTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                      <span class="admin-pill">На модерации</span>
                    </div>
                    <div class="admin-row-meta">
                      Заявка ID: <?php echo $submissionId; ?>
                      · Пользователь ID: <?php echo $submissionUserId; ?>
                      · Создано: <?php echo htmlspecialchars($submissionCreatedAt, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                  </div>
                  <form method="post" class="admin-resolve admin-resolve--stack">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="edit_submission" />
                    <input type="hidden" name="submission_id" value="<?php echo $submissionId; ?>" />
                    <div class="field admin-field--grow">
                      <label class="label" for="submission-title-<?php echo $submissionId; ?>">Заголовок</label>
                      <input class="input" id="submission-title-<?php echo $submissionId; ?>" name="title" type="text" maxlength="255" required value="<?php echo htmlspecialchars($submissionTitle, ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="field admin-field--narrow">
                      <label class="label" for="submission-category-<?php echo $submissionId; ?>">Категория</label>
                      <select class="input" id="submission-category-<?php echo $submissionId; ?>" name="category" required>
                        <?php foreach (['Событие', 'Крипто', 'Экономика', 'Политика', 'Технологии', 'Спорт'] as $optionCategory): ?>
                          <option value="<?php echo htmlspecialchars($optionCategory, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $submissionCategory === $optionCategory ? ' selected' : ''; ?>><?php echo htmlspecialchars($optionCategory, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="field admin-field--wide">
                      <label class="label" for="submission-expires-<?php echo $submissionId; ?>">Дедлайн</label>
                      <input class="input" id="submission-expires-<?php echo $submissionId; ?>" name="expires_at" type="datetime-local" required value="<?php echo htmlspecialchars(str_replace(' ', 'T', $submissionExpiresAt), ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="field admin-field--grow">
                      <label class="label" for="submission-description-<?php echo $submissionId; ?>">Описание</label>
                      <textarea class="input" id="submission-description-<?php echo $submissionId; ?>" name="short_description" rows="5" maxlength="8000" required><?php echo htmlspecialchars($submissionDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="field admin-field--grow">
                      <label class="label" for="submission-links-<?php echo $submissionId; ?>">Источники (по одному URL на строку)</label>
                      <textarea class="input" id="submission-links-<?php echo $submissionId; ?>" name="source_links" rows="3"><?php echo htmlspecialchars($submissionSourceLinksText, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <button class="btn btn-outline admin-btn-compact admin-btn-save" type="submit">Сохранить изменения заявки</button>
                  </form>
                  <form method="post" class="admin-resolve">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="approve_submission" />
                    <input type="hidden" name="submission_id" value="<?php echo $submissionId; ?>" />
                    <div class="field admin-field--grow220 admin-submission-note">
                      <label class="label" for="approve-note-<?php echo $submissionId; ?>">Комментарий админа (необязательно)</label>
                      <input class="input" type="text" id="approve-note-<?php echo $submissionId; ?>" name="note" maxlength="500" placeholder="Комментарий к принятию" />
                    </div>
                    <button class="btn btn-primary" type="submit">Принять и опубликовать</button>
                  </form>
                  <form method="post" class="admin-resolve" data-confirm-message="Отклонить эту заявку?" data-confirm-danger="1" data-confirm-text="Отклонить">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="reject_submission" />
                    <input type="hidden" name="submission_id" value="<?php echo $submissionId; ?>" />
                    <div class="field admin-field--grow220 admin-submission-note">
                      <label class="label" for="reject-note-<?php echo $submissionId; ?>">Причина отклонения (необязательно)</label>
                      <input class="input" type="text" id="reject-note-<?php echo $submissionId; ?>" name="note" maxlength="500" placeholder="Например: недостаточно источников" />
                    </div>
                    <button class="btn btn-outline" type="submit">Отклонить</button>
                  </form>
                </div>
              <?php endforeach; ?>
              <div class="admin-search-empty" id="admin-submissions-empty">Ничего не найдено по этому запросу.</div>
              </div>
            <?php endif; ?>
            <h2 class="admin-divider-title">Активные споры</h2>
            <?php if (!$activeRows): ?>
              <p>Нет активных споров.</p>
            <?php else: ?>
              <div class="admin-search">
                <input class="input" id="admin-active-disputes-search" type="search" placeholder="Поиск активных споров: заголовок, ID, дедлайн, описание..." data-search-target="#admin-active-disputes-list" />
                <span class="admin-search-note">Найдено: <strong id="admin-active-disputes-search-count"><?php echo $activeCount; ?></strong></span>
              </div>
              <div class="admin-list" id="admin-active-disputes-list">
              <?php foreach ($activeRows as $activeIndex => $r): ?>
                <?php
                $rid = (int)($r['id'] ?? 0);
                $rtitle = (string)($r['title'] ?? '');
                $rshort = (string)($r['short_description'] ?? '');
                $isMismatch = !farhuaad_dispute_description_matches_title($rtitle, $rshort);
                $rexp = (string)($r['expires_at'] ?? '');
                $creationSource = (string)($r['creation_source'] ?? 'ai');
                $creationLabel = $creationSource === 'manual' ? 'Вручную' : 'ИИ';
                $rtitleEn = (string)($r['title_en'] ?? '');
                $rshortEn = (string)($r['short_description_en'] ?? '');
                $rsourceLinksRaw = (string)($r['source_links'] ?? '');
                $rsourceLinksDecoded = json_decode($rsourceLinksRaw, true);
                $rsourceLinksList = is_array($rsourceLinksDecoded) ? $rsourceLinksDecoded : [];
                $rsourceLinksText = implode("\n", array_values(array_filter($rsourceLinksList, static fn($v) => is_string($v) && trim($v) !== '')));
                $resolutionHint = $rtitle !== ''
                  ? $rtitle
                  : 'Выберите сторону, которая фактически подтвердилась.';
                $displayNum = (int)$activeIndex + 1;
                ?>
                <?php
                $activeSearchText = implode(' ', [
                  'номер',
                  $rid,
                  $displayNum,
                  '#' . $displayNum,
                  'id',
                  '#' . $rid,
                  $rtitle,
                  $rtitleEn,
                  $rshort,
                  $rshortEn,
                  $rexp,
                  $creationLabel,
                  $resolutionHint,
                  $rsourceLinksText,
                ]);
                ?>
                <div
                  class="admin-row"
                  data-search-text="<?php echo htmlspecialchars($activeSearchText, ENT_QUOTES, 'UTF-8'); ?>"
                  data-search-id="<?php echo $rid; ?>"
                  data-search-num="<?php echo $displayNum; ?>"
                >
                  <div>
                    <div class="admin-row-title">#<?php echo $displayNum; ?> — <?php echo htmlspecialchars($rtitle, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="admin-row-meta">
                      ID в базе: <?php echo $rid; ?>
                      ·
                      Дедлайн: <?php echo htmlspecialchars($rexp, ENT_QUOTES, 'UTF-8'); ?>
                      · Создано: <?php echo htmlspecialchars($creationLabel, ENT_QUOTES, 'UTF-8'); ?>
                      <?php if ($isMismatch): ?>
                        · ⚠ Описание не соответствует заголовку
                      <?php endif; ?>
                    </div>
                    <div class="admin-outcome-help">
                      <strong>Что определяем:</strong> <?php echo htmlspecialchars($resolutionHint, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                  </div>
                  <form method="post" class="admin-resolve">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="resolve" />
                    <input type="hidden" name="dispute_id" value="<?php echo $rid; ?>" />
                    <div class="field admin-field--flat">
                      <label class="label" for="side-<?php echo $rid; ?>">Победившая сторона</label>
                      <select class="input" id="side-<?php echo $rid; ?>" name="winning_side" required>
                        <option value="yes">Да (yes)</option>
                        <option value="no">Нет (no)</option>
                      </select>
                    </div>
                    <div class="field admin-field--grow220">
                      <label class="label" for="note-<?php echo $rid; ?>">Комментарий (необязательно)</label>
                      <input class="input" type="text" id="note-<?php echo $rid; ?>" name="note" maxlength="500" placeholder="Почему такой исход" />
                    </div>
                    <button class="btn btn-primary" type="submit">Зафиксировать</button>
                  </form>
                  <form method="post" class="admin-resolve admin-resolve--stack">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="action" value="edit_dispute" />
                    <input type="hidden" name="dispute_id" value="<?php echo $rid; ?>" />
                    <div class="field admin-field--grow">
                      <label class="label" for="edit-title-<?php echo $rid; ?>">Заголовок (RU)</label>
                      <input class="input" id="edit-title-<?php echo $rid; ?>" name="title" type="text" maxlength="255" required value="<?php echo htmlspecialchars($rtitle, ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="field admin-field--grow">
                      <label class="label" for="edit-title-en-<?php echo $rid; ?>">Заголовок (EN)</label>
                      <input class="input" id="edit-title-en-<?php echo $rid; ?>" name="title_en" type="text" maxlength="255" value="<?php echo htmlspecialchars($rtitleEn, ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="field admin-field--grow">
                      <label class="label" for="edit-short-<?php echo $rid; ?>">Описание (RU)</label>
                      <textarea class="input" id="edit-short-<?php echo $rid; ?>" name="short_description" rows="4" maxlength="8000" required><?php echo htmlspecialchars($rshort, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="field admin-field--grow">
                      <label class="label" for="edit-short-en-<?php echo $rid; ?>">Описание (EN)</label>
                      <textarea class="input" id="edit-short-en-<?php echo $rid; ?>" name="short_description_en" rows="3" maxlength="8000"><?php echo htmlspecialchars($rshortEn, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="field admin-field--grow">
                      <label class="label" for="edit-links-<?php echo $rid; ?>">Источники (по одному URL на строку)</label>
                      <textarea class="input" id="edit-links-<?php echo $rid; ?>" name="source_links" rows="3"><?php echo htmlspecialchars($rsourceLinksText, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <button class="btn btn-outline admin-btn-compact admin-btn-save" type="submit">Сохранить изменения</button>
                  </form>
                </div>
              <?php endforeach; ?>
              <div class="admin-search-empty" id="admin-active-disputes-empty">Ничего не найдено по этому запросу.</div>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <p class="admin-hint"><a href="<?php echo htmlspecialchars(farhuaad_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">← На главную</a></p>
      </div>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/farhuaad-ui.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
  <script src="<?php echo htmlspecialchars(farhuaad_asset_url('assets/js/admin-disputes.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
