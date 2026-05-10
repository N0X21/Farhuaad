<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/init.php';

try {
  farhuaad_rate_limit_request('presence_ping', 240, 60);
  farhuaad_touch_presence();

  $user = farhuaad_current_user();
  $isAuthed = is_array($user) && !empty($user['id']);

  farhuaad_json_response(200, [
    'ok' => true,
    'authorized' => $isAuthed,
  ]);
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'RATE_LIMIT') {
    farhuaad_json_response(429, ['ok' => false, 'error' => 'RATE_LIMIT']);
  }
  farhuaad_json_response(400, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'PRESENCE_PING_FAILED');
}

