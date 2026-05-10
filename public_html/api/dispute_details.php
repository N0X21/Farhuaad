<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/init.php';

try {
  if (!($pdo instanceof PDO)) {
    throw new RuntimeException('DB_NOT_AVAILABLE');
  }

  farhuaad_rate_limit_request('dispute_details', 120, 60);
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) {
    throw new RuntimeException('INVALID_ID');
  }

  farhuaad_ensure_disputes_table($pdo);
  farhuaad_ensure_dispute_bets_table($pdo);
  farhuaad_migrate_disputes_table($pdo);
  if (function_exists('farhuaad_migrate_users_referral_column')) {
    farhuaad_migrate_users_referral_column($pdo);
  }

  $item = farhuaad_get_dispute_by_id($pdo, $id);
  if (!is_array($item)) {
    farhuaad_json_response(404, ['ok' => false, 'error' => 'NOT_FOUND']);
  }

  farhuaad_json_response(200, ['ok' => true, 'item' => $item]);
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'RATE_LIMIT') {
    farhuaad_json_response(429, ['ok' => false, 'error' => 'RATE_LIMIT']);
  }
  farhuaad_json_response(400, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'DISPUTE_DETAILS_FAILED');
}
