<?php
declare(strict_types=1);

const FARHUAAD_ONLINE_WINDOW_SECONDS = 35;
const FARHUAAD_PRESENCE_TOUCH_INTERVAL_SECONDS = 15;
const FARHUAAD_PRESENCE_RETENTION_HOURS = 24;

function farhuaad_ensure_presence_table(PDO $pdo): void
{
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS online_presence (
      session_id VARCHAR(128) NOT NULL PRIMARY KEY,
      user_id BIGINT UNSIGNED NULL,
      ip_address VARCHAR(45) DEFAULT NULL,
      user_agent VARCHAR(191) DEFAULT NULL,
      last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_last_seen_at (last_seen_at),
      INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

function farhuaad_touch_presence(): void
{
  global $pdo;
  if (!($pdo instanceof PDO)) {
    return;
  }
  if (session_status() !== PHP_SESSION_ACTIVE) {
    return;
  }

  $sid = session_id();
  if ($sid === '') {
    return;
  }

  try {
    $user = function_exists('farhuaad_current_user') ? farhuaad_current_user() : null;
    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0) {
      // Guests must not affect online counter and DB load.
      return;
    }

    $lastTouch = (int)($_SESSION['_farhuaad_presence_touch'] ?? 0);
    $now = time();
    if ($lastTouch > 0 && ($now - $lastTouch) < FARHUAAD_PRESENCE_TOUCH_INTERVAL_SECONDS) {
      return;
    }
    $_SESSION['_farhuaad_presence_touch'] = $now;

    farhuaad_ensure_presence_table($pdo);
    $userId = $uid;
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

    $stmt = $pdo->prepare(
      "INSERT INTO online_presence (session_id, user_id, ip_address, user_agent, last_seen_at)
       VALUES (:session_id, :user_id, :ip_address, :user_agent, NOW())
       ON DUPLICATE KEY UPDATE
         user_id = VALUES(user_id),
         ip_address = VALUES(ip_address),
         user_agent = VALUES(user_agent),
         last_seen_at = NOW()"
    );
    $stmt->execute([
      ':session_id' => $sid,
      ':user_id' => $userId,
      ':ip_address' => $ip !== '' ? $ip : null,
      ':user_agent' => $ua !== '' ? $ua : null,
    ]);

    // Opportunistic cleanup to prevent unbounded table growth under load.
    if (random_int(1, 100) === 1) {
      $cleanup = $pdo->prepare(
        "DELETE FROM online_presence
         WHERE last_seen_at < (NOW() - INTERVAL :hours HOUR)"
      );
      $cleanup->bindValue(':hours', FARHUAAD_PRESENCE_RETENTION_HOURS, PDO::PARAM_INT);
      $cleanup->execute();
    }
  } catch (Throwable $e) {
    // Non-critical stats feature must never break app flow.
  }
}

function farhuaad_online_users_count(int $windowSeconds = FARHUAAD_ONLINE_WINDOW_SECONDS): int
{
  global $pdo;
  if (!($pdo instanceof PDO)) {
    return 0;
  }
  $windowSeconds = max(20, $windowSeconds);
  try {
    farhuaad_ensure_presence_table($pdo);
    $stmt = $pdo->prepare(
      "SELECT COUNT(DISTINCT user_id) AS cnt
       FROM online_presence
       WHERE user_id IS NOT NULL
         AND last_seen_at >= (NOW() - INTERVAL :window SECOND)"
    );
    $stmt->bindValue(':window', $windowSeconds, PDO::PARAM_INT);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
  } catch (Throwable $e) {
    return 0;
  }
}

function farhuaad_total_disputes_count(): int
{
  global $pdo;
  if (!($pdo instanceof PDO)) {
    return 0;
  }
  try {
    // Открытые рынки: ручные считаем всегда, для ИИ оставляем фильтр валидности описания.
    $stmt = $pdo->query("SELECT title, short_description, creation_source FROM disputes WHERE status = 'active'");
    $rows = $stmt ? $stmt->fetchAll() : [];
    $count = 0;
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $title = (string)($row['title'] ?? '');
      $shortDescription = (string)($row['short_description'] ?? '');
      $creationSource = (string)($row['creation_source'] ?? 'ai');
      if (
        $creationSource !== 'manual'
        && function_exists('farhuaad_dispute_description_matches_title')
        && !farhuaad_dispute_description_matches_title($title, $shortDescription)
      ) {
        continue;
      }
      $count++;
    }
    return $count;
  } catch (Throwable $e) {
    return 0;
  }
}

