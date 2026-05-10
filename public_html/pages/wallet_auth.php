<?php
declare(strict_types=1);

require __DIR__ . '/../app/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  farhuaad_json_response(405, ['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
}

try {
  farhuaad_verify_csrf(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null);
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'CSRF_FAILED') {
    farhuaad_json_response(403, ['ok' => false, 'error' => 'CSRF_FAILED']);
  }
  farhuaad_json_response(400, ['ok' => false, 'error' => $e->getMessage()]);
}

$walletAddress = (string)($_POST['walletAddress'] ?? '');

try {
  $current = farhuaad_current_user();
  if (!$current || !isset($current['id'])) {
    throw new RuntimeException('UNAUTHORIZED');
  }

  farhuaad_attach_wallet_to_user((string)$current['id'], $walletAddress);
  $fresh = farhuaad_get_user_by_id((string)$current['id']);
  farhuaad_set_current_user([
    'id' => (string)($fresh['id'] ?? $current['id']),
    'name' => (string)($fresh['name'] ?? ($current['name'] ?? '')),
    'email' => (string)($fresh['email'] ?? ($current['email'] ?? '')),
    'authMethod' => 'email',
  ]);

  farhuaad_json_response(200, ['ok' => true]);
} catch (RuntimeException $e) {
  $code = $e->getMessage();
  $status = $code === 'UNAUTHORIZED' ? 401 : 400;
  farhuaad_json_response($status, ['ok' => false, 'error' => $code]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'WALLET_ATTACH_FAILED');
}

