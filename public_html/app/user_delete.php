<?php

/**
 * Помечает пользователя на удаление через 24 часа (soft delete).
 */
function farhuaad_schedule_user_delete(string $userId): void {
  $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'user_delete_queue.json';
  $dir = dirname($file);
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }

  $queue = [];
  if (is_file($file)) {
    $raw = file_get_contents($file);
    $data = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($data)) {
      $queue = $data;
    }
  }

  $queue = array_values(array_filter($queue, static function ($item) use ($userId) {
    return !is_array($item) || (string)($item['id'] ?? '') !== $userId;
  }));

  $queue[] = [
    'id' => $userId,
    'delete_at' => time() + 86400,
  ];

  file_put_contents($file, json_encode($queue, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * Отменяет отложенное удаление (если пользователь восстановился).
 */
function farhuaad_cancel_scheduled_user_delete(string $userId): void {
  $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'user_delete_queue.json';
  if (!is_file($file)) {
    return;
  }
  $raw = file_get_contents($file);
  $data = $raw !== false ? json_decode($raw, true) : null;
  if (!is_array($data)) {
    return;
  }
  $queue = array_values(array_filter($data, static function ($item) use ($userId) {
    return !is_array($item) || (string)($item['id'] ?? '') !== $userId;
  }));
  file_put_contents($file, json_encode($queue, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * Выполняет отложенное удаление аккаунтов, у которых прошли 24 часа.
 */
function farhuaad_run_user_delete_garbage_collector(): void {
  global $pdo;
  if (!($pdo instanceof PDO)) {
    return;
  }
  $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'user_delete_queue.json';
  if (!is_file($file)) {
    return;
  }
  $raw = file_get_contents($file);
  $data = $raw !== false ? json_decode($raw, true) : null;
  if (!is_array($data)) {
    return;
  }

  $now = time();
  $keep = [];

  foreach ($data as $item) {
    if (!is_array($item)) {
      continue;
    }
    $id = (string)($item['id'] ?? '');
    $deleteAt = (int)($item['delete_at'] ?? 0);
    if ($id === '' || $deleteAt <= 0) {
      continue;
    }
    if ($deleteAt > $now) {
      $keep[] = $item;
      continue;
    }

    try {
      $uid = (int)$id;
      $pdo->beginTransaction();
      $pdo->prepare('DELETE FROM wallet_nonces WHERE address IN (SELECT address FROM wallets WHERE user_id = ?)')->execute([$uid]);
      $pdo->prepare('DELETE FROM wallets WHERE user_id = ?')->execute([$uid]);
      $pdo->prepare('DELETE FROM dispute_bets WHERE user_id = ?')->execute([$uid]);
      $pdo->prepare('DELETE FROM dispute_chat_messages WHERE user_id = ?')->execute([$uid]);
      $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $keep[] = $item;
    }
  }

  file_put_contents($file, json_encode($keep, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

