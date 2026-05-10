<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/init.php';

try {
  farhuaad_rate_limit_request('daily_disputes', 60, 60);
  $items = farhuaad_get_daily_disputes(false);

  farhuaad_json_response(200, [
    'ok' => true,
    'date' => farhuaad_disputes_moscow_today(),
    'items' => $items,
  ]);
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'RATE_LIMIT') {
    farhuaad_json_response(429, ['ok' => false, 'error' => 'RATE_LIMIT']);
  }
  farhuaad_json_response(400, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'DAILY_DISPUTES_FAILED');
}
