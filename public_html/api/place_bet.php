<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  farhuaad_json_response(405, ['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
}

try {
  if (!($pdo instanceof PDO)) {
    throw new RuntimeException('DB_NOT_AVAILABLE');
  }

  farhuaad_rate_limit_request('place_bet_cooldown', 1, 5);
  farhuaad_rate_limit_request('place_bet', 30, 60);

  $raw = file_get_contents('php://input');
  $data = json_decode((string)$raw, true);
  if (!is_array($data)) {
    $data = $_POST;
  }

  farhuaad_verify_csrf_from_json_or_header(is_array($data) ? $data : null);

  $user = farhuaad_current_user();
  $userId = (int)($user['id'] ?? 0);
  if ($userId <= 0) {
    throw new RuntimeException('UNAUTHORIZED');
  }

  $disputeId = (int)($data['dispute_id'] ?? 0);
  $side = (string)($data['side'] ?? '');
  $amount = (float)($data['amount'] ?? 0);

  $result = farhuaad_place_dispute_bet($pdo, $disputeId, $userId, $side, $amount);

  farhuaad_json_response(200, ['ok' => true, 'result' => $result]);
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'RATE_LIMIT') {
    farhuaad_json_response(429, ['ok' => false, 'error' => 'RATE_LIMIT']);
  }
  if ($e->getMessage() === 'CSRF_FAILED') {
    farhuaad_json_response(403, ['ok' => false, 'error' => 'CSRF_FAILED']);
  }
  $msg = $e->getMessage();
  $status = $msg === 'UNAUTHORIZED' ? 401 : 400;
  farhuaad_json_response($status, ['ok' => false, 'error' => $msg]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'PLACE_BET_FAILED');
}
