<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/init.php';

try {
  if (!($pdo instanceof PDO)) {
    throw new RuntimeException('DB_NOT_AVAILABLE');
  }

  farhuaad_rate_limit_request('platform_stats', 300, 60);
  farhuaad_ensure_disputes_table($pdo);
  farhuaad_ensure_dispute_bets_table($pdo);
  farhuaad_ensure_presence_table($pdo);

  $stats = farhuaad_get_platform_stats($pdo);
  $totalVolume = (float)($stats['total_volume'] ?? 0);
  $onlineUsers = farhuaad_online_users_count();
  $totalDisputes = farhuaad_total_disputes_count();
  $user = farhuaad_current_user();
  $userBalance = null;
  if (is_array($user) && !empty($user['id'])) {
    $userBalance = farhuaad_get_balance((string)$user['id']);
  }

  farhuaad_json_response(200, [
    'ok' => true,
    'stats' => [
      'total_volume' => round($totalVolume, 2),
      'online_users' => $onlineUsers,
      'total_disputes' => $totalDisputes,
    ],
    'user' => [
      'authorized' => is_array($user) && !empty($user['id']),
      'balance' => $userBalance !== null ? round((float)$userBalance, 2) : null,
    ],
    'updated_at' => date('c'),
  ]);
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'RATE_LIMIT') {
    farhuaad_json_response(429, ['ok' => false, 'error' => 'RATE_LIMIT']);
  }
  farhuaad_json_response(503, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'PLATFORM_STATS_FAILED');
}

