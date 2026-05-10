<?php
declare(strict_types=1);

/**
 * Ежедневное создание споров: дата «дня» по Europe/Moscow сменяется в 15:35 МСК.
 * Планировщик: 35 12 * * * (UTC) ≈ 15:35 МСК.
 */

require_once __DIR__ . '/../app/init.php';

define('FARHUAAD_CRON_CONTEXT', true);

function farhuaad_read_cron_token(): string
{
  $configured = trim((string)($_ENV['DISPUTES_CRON_TOKEN'] ?? $_ENV['CRON_TOKEN'] ?? ''));
  if ($configured === '') {
    return '';
  }

  $provided = trim((string)($_GET['token'] ?? ''));
  if ($provided === '') {
    $provided = trim((string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));
  }

  if ($provided === '' || !hash_equals($configured, $provided)) {
    return '';
  }

  return $provided;
}

try {
  if (farhuaad_read_cron_token() === '') {
    farhuaad_json_response(403, ['ok' => false, 'error' => 'Forbidden']);
  }

  farhuaad_get_daily_disputes(false);

  $activeStmt = $pdo->query("SELECT COUNT(*) FROM disputes WHERE status = 'active'");
  $resolvedStmt = $pdo->query("SELECT COUNT(*) FROM disputes WHERE status = 'resolved'");
  $expiredStmt = $pdo->query("SELECT COUNT(*) FROM disputes WHERE status = 'expired'");

  farhuaad_json_response(200, [
    'ok' => true,
    'ran_at' => date('c'),
    'moscow_date' => farhuaad_disputes_moscow_today(),
    'stats' => [
      'active' => (int)$activeStmt->fetchColumn(),
      'resolved' => (int)$resolvedStmt->fetchColumn(),
      'expired' => (int)$expiredStmt->fetchColumn(),
    ],
  ]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'CRON_DISPUTES_FAILED');
}
