<?php

function farhuaad_reset_balances_all(PDO $pdo): void {
  $pdo->exec("UPDATE users SET balance = 1000");

  $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'balance_reset.json';
  $dir = dirname($file);
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }
  $state = [
    'last_reset_at' => time(),
  ];
  file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function farhuaad_auto_reset_balances_if_due(): void {
  global $pdo;
  if (!($pdo instanceof PDO)) {
    return;
  }
  $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'balance_reset.json';
  $last = null;
  if (is_file($file)) {
    $raw = file_get_contents($file);
    $data = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($data) && isset($data['last_reset_at']) && is_int($data['last_reset_at'])) {
      $last = $data['last_reset_at'];
    }
  }

  $now = time();
  $interval = 90 * 24 * 60 * 60; // 90 дней

  if ($last !== null && ($now - $last) < $interval) {
    return;
  }

  // Если never reseted или прошло >= 90 дней — сбрасываем и обновляем дату.
  farhuaad_reset_balances_all($pdo);
}

function farhuaad_days_until_balance_reset(): ?int {
  $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'balance_reset.json';
  $last = null;
  if (is_file($file)) {
    $raw = file_get_contents($file);
    $data = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($data) && isset($data['last_reset_at']) && is_int($data['last_reset_at'])) {
      $last = $data['last_reset_at'];
    }
  }

  $interval = 90 * 24 * 60 * 60; // 90 дней
  $now = time();

  if ($last === null) {
    return null;
  }

  $next = $last + $interval;
  $diff = $next - $now;
  if ($diff <= 0) {
    return 0;
  }
  return (int)ceil($diff / (24 * 60 * 60));
}

