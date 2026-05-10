<?php
declare(strict_types=1);

/** Доля от суммы выплаты победителю, начисляемая пригласившему этого победителя (1%). */
const FARHUAAD_REFERRAL_WIN_FRACTION = 0.01;

/**
 * Доля общего пула по рынку, удерживаемая платформой (букмекер) перед распределением между победителями.
 * Победители делят (1 − это значение) от суммы всех ставок в споре.
 */
const FARHUAAD_BOOKMAKER_POOL_FRACTION = 0.05;

const FARHUAAD_REF_COOKIE = 'farhuaad_ref';
const FARHUAAD_REF_COOKIE_DAYS = 90;

/** Длина публичного реферального кода в пути /r/… */
const FARHUAAD_REFERRAL_CODE_LEN = 12;

function farhuaad_random_referral_code(): string
{
  $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  $len = strlen($chars);
  $out = '';
  for ($i = 0; $i < FARHUAAD_REFERRAL_CODE_LEN; $i++) {
    $out .= $chars[random_int(0, $len - 1)];
  }
  return $out;
}

/**
 * Нормализация ref из URL: буквенно-цифровой код 8–20 символов или legacy numeric id.
 *
 * @return non-empty-string|null
 */
function farhuaad_normalize_referral_token(mixed $raw): ?string
{
  if ($raw === null || $raw === '') {
    return null;
  }
  $s = trim((string)$raw);
  if ($s === '') {
    return null;
  }
  $lower = strtolower($s);
  if (preg_match('/^[a-z0-9]{8,20}$/', $lower) === 1) {
    return $lower;
  }
  if (preg_match('/^\d{1,10}$/', $s) === 1) {
    return $s;
  }
  return null;
}

function farhuaad_resolve_referrer_id_from_token(PDO $pdo, string $token): ?int
{
  $token = trim($token);
  if ($token === '') {
    return null;
  }
  try {
    if (preg_match('/^\d+$/', $token) === 1) {
      $id = (int)$token;
      if ($id <= 0) {
        return null;
      }
      $st = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
      $st->execute([$id]);
      return $st->fetch() ? $id : null;
    }
    $st = $pdo->prepare('SELECT id FROM users WHERE referral_code = ? LIMIT 1');
    $st->execute([strtolower($token)]);
    $row = $st->fetch();
    return is_array($row) ? (int)($row['id'] ?? 0) ?: null : null;
  } catch (Throwable $e) {
    return null;
  }
}

function farhuaad_ensure_referral_code_for_user(PDO $pdo, int $userId): void
{
  if ($userId <= 0) {
    return;
  }
  try {
    $chk = $pdo->prepare('SELECT referral_code FROM users WHERE id = ? LIMIT 1');
    $chk->execute([$userId]);
    $row = $chk->fetch();
    if (!is_array($row)) {
      return;
    }
    $existing = trim((string)($row['referral_code'] ?? ''));
    if ($existing !== '') {
      return;
    }
  } catch (Throwable $e) {
    return;
  }

  for ($attempt = 0; $attempt < 48; $attempt++) {
    $code = farhuaad_random_referral_code();
    try {
      $up = $pdo->prepare(
        'UPDATE users SET referral_code = ? WHERE id = ? AND (referral_code IS NULL OR referral_code = \'\')'
      );
      $up->execute([$code, $userId]);
      if ($up->rowCount() > 0) {
        return;
      }
      $chk2 = $pdo->prepare('SELECT referral_code FROM users WHERE id = ? LIMIT 1');
      $chk2->execute([$userId]);
      $r2 = $chk2->fetch();
      if (is_array($r2) && trim((string)($r2['referral_code'] ?? '')) !== '') {
        return;
      }
      continue;
    } catch (PDOException $e) {
      $sqlState = (string)$e->getCode();
      if ($sqlState === '23000' || str_contains(strtolower($e->getMessage()), 'duplicate')) {
        continue;
      }
      error_log('farhuaad_ensure_referral_code_for_user: ' . $e->getMessage());
      return;
    }
  }
}

function farhuaad_migrate_users_referral_code_column(PDO $pdo): void
{
  try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'referral_code'");
    $row = $stmt ? $stmt->fetch() : null;
    if (!is_array($row)) {
      $pdo->exec(
        'ALTER TABLE users ADD COLUMN referral_code VARCHAR(24) NULL DEFAULT NULL'
      );
      $pdo->exec('CREATE UNIQUE INDEX idx_users_referral_code ON users (referral_code)');
    }
  } catch (Throwable $e) {
    error_log('farhuaad_migrate_users_referral_code_column: ' . $e->getMessage());
    return;
  }
  try {
    $fill = $pdo->query(
      "SELECT id FROM users WHERE referral_code IS NULL OR referral_code = '' ORDER BY id ASC"
    );
    $ids = $fill ? $fill->fetchAll(PDO::FETCH_COLUMN) : [];
    foreach ($ids as $uid) {
      farhuaad_ensure_referral_code_for_user($pdo, (int)$uid);
    }
  } catch (Throwable $e) {
    error_log('farhuaad_migrate_users_referral_code backfill: ' . $e->getMessage());
  }
}

function farhuaad_get_public_referral_url(PDO $pdo, int $userId): string
{
  if ($userId <= 0) {
    return '';
  }
  farhuaad_ensure_referral_code_for_user($pdo, $userId);
  try {
    $st = $pdo->prepare('SELECT referral_code FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    $code = trim((string)($row['referral_code'] ?? ''));
    if ($code === '') {
      return '';
    }
    return farhuaad_site_origin() . farhuaad_url('r/' . $code);
  } catch (Throwable $e) {
    return '';
  }
}

function farhuaad_migrate_users_referral_column(PDO $pdo): void
{
  try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'referred_by_user_id'");
    $row = $stmt ? $stmt->fetch() : null;
    if (!is_array($row)) {
      $pdo->exec(
        'ALTER TABLE users ADD COLUMN referred_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL'
      );
      $pdo->exec('CREATE INDEX idx_users_referred_by ON users (referred_by_user_id)');
    }
  } catch (Throwable $e) {
    error_log('farhuaad_migrate_users_referral_column: ' . $e->getMessage());
  }
  farhuaad_migrate_users_referral_code_column($pdo);
}

/**
 * Сохраняет реферальный токен из ?ref= или /r/КОД (только для гостей, first-touch).
 */
function farhuaad_capture_referral_from_request(): void
{
  if (farhuaad_current_user()) {
    return;
  }
  $token = farhuaad_normalize_referral_token($_GET['ref'] ?? null);
  if ($token === null) {
    return;
  }

  if (!empty($_SESSION['farhuaad_ref_pending'])) {
    return;
  }
  $cookieRaw = isset($_COOKIE[FARHUAAD_REF_COOKIE])
    ? trim((string)$_COOKIE[FARHUAAD_REF_COOKIE])
    : '';
  if ($cookieRaw !== '' && farhuaad_normalize_referral_token($cookieRaw) !== null) {
    return;
  }

  $_SESSION['farhuaad_ref_pending'] = $token;

  $params = session_get_cookie_params();
  $secure = (bool)($params['secure'] ?? false);
  $samesite = $params['samesite'] ?? 'Lax';
  if (!is_string($samesite) || $samesite === '') {
    $samesite = 'Lax';
  }
  $expires = time() + FARHUAAD_REF_COOKIE_DAYS * 86400;
  setcookie(FARHUAAD_REF_COOKIE, $token, [
    'expires' => $expires,
    'path' => $params['path'] ?: '/',
    'domain' => (string)($params['domain'] ?? ''),
    'secure' => $secure,
    'httponly' => true,
    'samesite' => $samesite,
  ]);
  $_COOKIE[FARHUAAD_REF_COOKIE] = $token;
}

/**
 * Привязать реферера к новому пользователю (один раз, при регистрации).
 */
function farhuaad_apply_referral_to_new_user(PDO $pdo, int $newUserId): void
{
  if ($newUserId <= 0) {
    return;
  }

  $token = '';
  if (!empty($_SESSION['farhuaad_ref_pending'])) {
    $token = trim((string)$_SESSION['farhuaad_ref_pending']);
  } elseif (isset($_COOKIE[FARHUAAD_REF_COOKIE])) {
    $token = trim((string)$_COOKIE[FARHUAAD_REF_COOKIE]);
  }

  unset($_SESSION['farhuaad_ref_pending']);

  $params = session_get_cookie_params();
  $secure = (bool)($params['secure'] ?? false);
  $samesite = $params['samesite'] ?? 'Lax';
  if (!is_string($samesite) || $samesite === '') {
    $samesite = 'Lax';
  }
  setcookie(FARHUAAD_REF_COOKIE, '', [
    'expires' => time() - 3600,
    'path' => $params['path'] ?: '/',
    'domain' => (string)($params['domain'] ?? ''),
    'secure' => $secure,
    'httponly' => true,
    'samesite' => $samesite,
  ]);
  unset($_COOKIE[FARHUAAD_REF_COOKIE]);

  $refId = farhuaad_resolve_referrer_id_from_token($pdo, $token);
  if ($refId === null || $refId <= 0 || $refId === $newUserId) {
    return;
  }

  try {
    $upd = $pdo->prepare(
      'UPDATE users SET referred_by_user_id = ? WHERE id = ? AND referred_by_user_id IS NULL'
    );
    $upd->execute([$refId, $newUserId]);
  } catch (Throwable $e) {
    error_log('farhuaad_apply_referral_to_new_user: ' . $e->getMessage());
  }
}

/**
 * Сумма пула, которую делят победители после удержания комиссии букмекера.
 */
function farhuaad_distributable_pool_after_bookmaker(float $totalPool): float
{
  if ($totalPool <= 0) {
    return 0.0;
  }
  if (!defined('FARHUAAD_BOOKMAKER_POOL_FRACTION')) {
    return round($totalPool, 2);
  }
  $fee = (float)FARHUAAD_BOOKMAKER_POOL_FRACTION;
  if ($fee <= 0) {
    return round($totalPool, 2);
  }
  $fee = min($fee, 0.99);
  return round($totalPool * (1.0 - $fee), 2);
}

/**
 * Пользователи, зарегистрировавшиеся по ссылке этого реферера (фиксированный список по БД).
 *
 * @return array<int, array{id:int, label:string, registered_at:string}>
 */
function farhuaad_get_invited_users_by_referrer(PDO $pdo, int $referrerId, int $limit = 500): array
{
  if ($referrerId <= 0) {
    return [];
  }
  $limit = max(1, min(500, $limit));
  try {
    $stmt = $pdo->prepare(
      'SELECT id, COALESCE(name, \'\') AS name, COALESCE(email, \'\') AS email, created_at
       FROM users
       WHERE referred_by_user_id = :rid
       ORDER BY id ASC
       LIMIT ' . $limit
    );
    $stmt->bindValue(':rid', $referrerId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
  } catch (Throwable $e) {
    return [];
  }
  if (!is_array($rows)) {
    return [];
  }
  $out = [];
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
      continue;
    }
    $label = farhuaad_mask_account_name(farhuaad_user_label_from_row($row));
    $created = $row['created_at'] ?? '';
    $registeredAt = '';
    if ($created !== null && $created !== '') {
      $ts = strtotime((string)$created);
      $registeredAt = $ts !== false ? date('d.m.Y', $ts) : (string)$created;
    }
    $out[] = [
      'id' => $id,
      'label' => $label,
      'registered_at' => $registeredAt,
    ];
  }
  return $out;
}

function farhuaad_site_origin(): string
{
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
  $scheme = $https ? 'https' : 'http';
  $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
  if ($host === '') {
    $host = 'localhost';
  }
  return $scheme . '://' . $host;
}

function farhuaad_users_file(): string {
  return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'users.json';
}

function farhuaad_read_users(): array {
  $file = farhuaad_users_file();
  if (!file_exists($file)) {
    return [];
  }

  $raw = file_get_contents($file);
  if ($raw === false || trim($raw) === '') {
    return [];
  }

  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function farhuaad_write_users(array $users): void {
  $file = farhuaad_users_file();
  $dir = dirname($file);
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }

  $json = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) {
    throw new RuntimeException('Failed to encode users json');
  }

  $fp = fopen($file, 'c+');
  if ($fp === false) {
    throw new RuntimeException('Failed to open users file');
  }

  try {
    if (!flock($fp, LOCK_EX)) {
      throw new RuntimeException('Failed to lock users file');
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
  } finally {
    fclose($fp);
  }
}

function farhuaad_find_user_by_email(string $email): ?array {
  $email = mb_strtolower(trim($email));
  global $pdo;

  if ($pdo instanceof PDO) {
    try {
      $stmt = $pdo->prepare("SELECT id, name, email, balance FROM users WHERE email = ? LIMIT 1");
      $stmt->execute([$email]);
      $row = $stmt->fetch();
      if (is_array($row)) {
        return [
          'id' => (string)($row['id'] ?? ''),
          'name' => (string)($row['name'] ?? ''),
          'email' => (string)($row['email'] ?? ''),
          'balance' => isset($row['balance']) ? (float)$row['balance'] : 1000.0,
          'authMethod' => 'email',
        ];
      }
    } catch (Throwable $e) {
      // fallback to json
    }
  }

  $users = farhuaad_read_users();
  foreach ($users as $u) {
    if (!is_array($u)) continue;
    if (isset($u['email']) && mb_strtolower((string)$u['email']) === $email) {
      return $u;
    }
  }
  return null;
}

function farhuaad_find_user_by_wallet(string $walletAddress): ?array {
  $addr = strtolower(trim($walletAddress));
  global $pdo;

  if ($pdo instanceof PDO) {
    try {
      $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.balance, w.address AS walletAddress
        FROM wallets w
        INNER JOIN users u ON u.id = w.user_id
        WHERE w.address = ?
        LIMIT 1
      ");
      $stmt->execute([$addr]);
      $row = $stmt->fetch();
      if (is_array($row)) {
        return [
          'id' => (string)($row['id'] ?? ''),
          'name' => (string)($row['name'] ?? ''),
          'email' => (string)($row['email'] ?? ''),
          'walletAddress' => (string)($row['walletAddress'] ?? ''),
          'balance' => isset($row['balance']) ? (float)$row['balance'] : 1000.0,
          'authMethod' => 'wallet',
        ];
      }
    } catch (Throwable $e) {
      // fallback to json
    }
  }

  $users = farhuaad_read_users();
  foreach ($users as $u) {
    if (!is_array($u)) continue;
    if (isset($u['walletAddress']) && strtolower((string)$u['walletAddress']) === $addr) {
      return $u;
    }
  }
  return null;
}

function farhuaad_register_email_user(string $email): array {
  $email = mb_strtolower(trim($email));
  global $pdo;

  $name = explode('@', $email)[0] ?: 'user';

  if ($pdo instanceof PDO) {
    try {
      $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $check->execute([$email]);
      $exists = $check->fetch();
      if ($exists) {
        throw new RuntimeException('EMAIL_TAKEN');
      }

      $insert = $pdo->prepare("
        INSERT INTO users (name, email, email_verified, balance, created_at)
        VALUES (?, ?, 1, 1000, NOW())
      ");
      $insert->execute([$name, $email]);
      $id = (string)$pdo->lastInsertId();
      $uid = (int)$id;
      farhuaad_apply_referral_to_new_user($pdo, $uid);
      farhuaad_ensure_referral_code_for_user($pdo, $uid);

      return [
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'authMethod' => 'email',
        'balance' => 1000.0,
      ];
    } catch (RuntimeException $e) {
      throw $e;
    } catch (Throwable $e) {
      // fallback to json
    }
  }

  $users = farhuaad_read_users();
  foreach ($users as $u) {
    if (is_array($u) && isset($u['email']) && mb_strtolower((string)$u['email']) === $email) {
      throw new RuntimeException('EMAIL_TAKEN');
    }
  }
  $user = [
    'id' => bin2hex(random_bytes(8)),
    'name' => $name,
    'email' => $email,
    'authMethod' => 'email',
    'balance' => 1000,
    'createdAt' => date('c'),
  ];

  $users[] = $user;
  farhuaad_write_users($users);

  return $user;
}

function farhuaad_get_balance(string $userId): float {
  global $pdo;

  if ($pdo instanceof PDO) {
    try {
      $stmt = $pdo->prepare("SELECT COALESCE(balance, 1000) AS balance FROM users WHERE id = ? LIMIT 1");
      $stmt->execute([$userId]);
      $row = $stmt->fetch();
      if (is_array($row)) {
        return (float)$row['balance'];
      }
    } catch (Throwable $e) {
      // fallback to json
    }
  }

  $users = farhuaad_read_users();
  foreach ($users as $u) {
    if (!is_array($u) || (string)($u['id'] ?? '') !== $userId) continue;
    $b = $u['balance'] ?? null;
    if ($b === null) {
      $u['balance'] = 1000;
      farhuaad_update_user_balance($userId, 1000);
      return 1000.0;
    }
    return (float)$b;
  }
  return 1000.0;
}

function farhuaad_update_user_balance(string $userId, float $balance): void {
  global $pdo;

  if ($pdo instanceof PDO) {
    try {
      $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
      $stmt->execute([round($balance, 2), $userId]);
      return;
    } catch (Throwable $e) {
      // fallback to json
    }
  }

  $users = farhuaad_read_users();
  foreach ($users as $i => $u) {
    if (!is_array($u) || (string)($u['id'] ?? '') !== $userId) continue;
    $users[$i]['balance'] = round($balance, 2);
    farhuaad_write_users($users);
    return;
  }
}

function farhuaad_register_wallet_user(string $walletAddress): array {
  $addr = strtolower(trim($walletAddress));
  if (!preg_match('/^0x[a-f0-9]{40}$/', $addr)) {
    throw new RuntimeException('INVALID_WALLET');
  }
  global $pdo;

  if ($pdo instanceof PDO) {
    try {
      $check = $pdo->prepare("SELECT user_id FROM wallets WHERE address = ? LIMIT 1");
      $check->execute([$addr]);
      $exists = $check->fetch();
      if ($exists) {
        throw new RuntimeException('WALLET_TAKEN');
      }

      $name = substr($addr, 2, 3) . '***' . substr($addr, -2);
      $insertUser = $pdo->prepare("
        INSERT INTO users (name, email_verified, balance, created_at)
        VALUES (?, 1, 1000, NOW())
      ");
      $insertUser->execute([$name]);
      $userId = (string)$pdo->lastInsertId();

      $insertWallet = $pdo->prepare("
        INSERT INTO wallets (user_id, address, wallet_type, created_at)
        VALUES (?, ?, 'evm', NOW())
      ");
      $insertWallet->execute([(int)$userId, $addr]);
      $wid = (int)$userId;
      farhuaad_apply_referral_to_new_user($pdo, $wid);
      farhuaad_ensure_referral_code_for_user($pdo, $wid);

      return [
        'id' => $userId,
        'name' => $name,
        'walletAddress' => $addr,
        'authMethod' => 'wallet',
        'balance' => 1000.0,
      ];
    } catch (RuntimeException $e) {
      throw $e;
    } catch (Throwable $e) {
      // fallback to json
    }
  }

  $users = farhuaad_read_users();
  foreach ($users as $u) {
    if (is_array($u) && isset($u['walletAddress']) && strtolower((string)$u['walletAddress']) === $addr) {
      throw new RuntimeException('WALLET_TAKEN');
    }
  }

  $name = substr($addr, 0, 6) . '…' . substr($addr, -4);

  $user = [
    'id' => bin2hex(random_bytes(8)),
    'name' => $name,
    'walletAddress' => $addr,
    'authMethod' => 'wallet',
    'balance' => 1000,
    'createdAt' => date('c'),
  ];

  $users[] = $user;
  farhuaad_write_users($users);

  return $user;
}

function farhuaad_login_email(string $email): array {
  $email = mb_strtolower(trim($email));
  $user = farhuaad_find_user_by_email($email);
  if (!$user) {
    throw new RuntimeException('NOT_FOUND');
  }

  $name = trim((string)($user['name'] ?? ''));
  if ($name === '') {
    $name = explode('@', $email)[0] ?: 'user';
  }

  return [
    'id' => (string)($user['id'] ?? ''),
    'name' => $name,
    'email' => (string)($user['email'] ?? ''),
    'authMethod' => (string)($user['authMethod'] ?? 'email'),
  ];
}

function farhuaad_login_wallet(string $walletAddress): array {
  $addr = strtolower(trim($walletAddress));
  if (!preg_match('/^0x[a-f0-9]{40}$/', $addr)) {
    throw new RuntimeException('INVALID_WALLET');
  }

  $user = farhuaad_find_user_by_wallet($addr);
  if (!$user) {
    // auto-register on first connect
    $user = farhuaad_register_wallet_user($addr);
  }

  return [
    'id' => (string)($user['id'] ?? ''),
    'name' => (string)($user['name'] ?? ''),
    'email' => (string)($user['email'] ?? ''),
    'walletAddress' => (string)($user['walletAddress'] ?? $addr),
    'authMethod' => 'wallet',
  ];
}

function farhuaad_get_user_wallets(string $userId): array {
  global $pdo;
  if (!($pdo instanceof PDO)) {
    return [];
  }

  try {
    $stmt = $pdo->prepare("SELECT address, wallet_type FROM wallets WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([(int)$userId]);
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
  } catch (Throwable $e) {
    return [];
  }
}

function farhuaad_attach_wallet_to_user(string $userId, string $walletAddress, string $walletType = 'evm'): void {
  global $pdo;
  $uid = (int)$userId;
  $addr = strtolower(trim($walletAddress));
  if (!($pdo instanceof PDO) || $uid <= 0) {
    throw new RuntimeException('DB_NOT_AVAILABLE');
  }
  if (!preg_match('/^0x[a-f0-9]{40}$/', $addr)) {
    throw new RuntimeException('INVALID_WALLET');
  }

  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare("SELECT user_id FROM wallets WHERE address = ? LIMIT 1");
    $stmt->execute([$addr]);
    $row = $stmt->fetch();
    if (is_array($row)) {
      $ownerId = (int)($row['user_id'] ?? 0);
      if ($ownerId !== $uid) {
        throw new RuntimeException('WALLET_TAKEN');
      }
      $pdo->commit();
      return;
    }

    $insert = $pdo->prepare("
      INSERT INTO wallets (user_id, address, wallet_type, created_at)
      VALUES (?, ?, ?, NOW())
    ");
    $insert->execute([$uid, $addr, $walletType]);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

function farhuaad_get_user_by_id(string $userId): ?array {
  global $pdo;
  if (!($pdo instanceof PDO)) {
    return null;
  }

  try {
    $stmt = $pdo->prepare("
      SELECT id, COALESCE(name, '') AS name, COALESCE(email, '') AS email, COALESCE(balance, 1000) AS balance
      FROM users
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([(int)$userId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
      return null;
    }
    return [
      'id' => (string)($row['id'] ?? ''),
      'name' => (string)($row['name'] ?? ''),
      'email' => (string)($row['email'] ?? ''),
      'balance' => (float)($row['balance'] ?? 1000),
    ];
  } catch (Throwable $e) {
    return null;
  }
}

function farhuaad_get_user_rank(string $userId): ?int {
  global $pdo;
  if (!($pdo instanceof PDO)) {
    return null;
  }

  try {
    $stmt = $pdo->prepare("SELECT COALESCE(balance, 1000) AS balance FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$userId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
      return null;
    }
    $balance = (float)$row['balance'];

    $rankStmt = $pdo->prepare("SELECT COUNT(*) + 1 AS r FROM users WHERE COALESCE(balance, 1000) > ?");
    $rankStmt->execute([$balance]);
    $rank = (int)$rankStmt->fetchColumn();
    return $rank > 0 ? $rank : null;
  } catch (Throwable $e) {
    return null;
  }
}

function farhuaad_set_current_user(array $user): void {
  $_SESSION['farhuaad_user'] = $user;
}

function farhuaad_current_user(): ?array {
  $u = $_SESSION['farhuaad_user'] ?? null;
  return is_array($u) ? $u : null;
}

function farhuaad_logout(): void {
  unset($_SESSION['farhuaad_user']);
}

function farhuaad_mask_account_name(string $value): string {
  $value = trim($value);
  if ($value === '') {
    return '??******';
  }

  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    $len = mb_strlen($value);
    $take = min(2, max(1, $len));
    $start = mb_substr($value, 0, $take);
  } else {
    $len = strlen($value);
    $take = min(2, max(1, $len));
    $start = substr($value, 0, $take);
  }

  if ($start === '') {
    return '?******';
  }

  $hidden = max(0, $len - $take);
  $starCount = $hidden > 0 ? $hidden : 3;

  return $start . str_repeat('*', $starCount);
}

function farhuaad_user_label_from_row(array $row): string {
  $name = trim((string)($row['name'] ?? ''));
  $email = trim((string)($row['email'] ?? ''));
  $wallet = trim((string)($row['walletAddress'] ?? ''));
  $id = trim((string)($row['id'] ?? ''));

  if ($name !== '') return $name;
  if ($email !== '') {
    $at = strpos($email, '@');
    return $at === false ? $email : substr($email, 0, $at);
  }
  if ($wallet !== '') return $wallet;
  if ($id !== '') return 'user' . substr($id, 0, 6);
  return 'user';
}

function farhuaad_get_leaderboard(int $limit = 20): array {
  global $pdo;
  $limit = max(1, min(100, $limit));
  $rows = [];

  if ($pdo instanceof PDO) {
    try {
      $stmt = $pdo->prepare("
        SELECT
          id,
          COALESCE(name, '') AS name,
          COALESCE(email, '') AS email,
          COALESCE(balance, 1000) AS balance
        FROM users
        ORDER BY balance DESC, id ASC
        LIMIT :limit
      ");
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->execute();
      $dbRows = $stmt->fetchAll();

      foreach ($dbRows as $row) {
        if (!is_array($row)) continue;
        $balance = isset($row['balance']) ? (float)$row['balance'] : 1000.0;
        $profit = $balance - 1000.0;
        $label = farhuaad_user_label_from_row($row);
        $rows[] = [
          'id' => (string)($row['id'] ?? ''),
          'label' => farhuaad_mask_account_name($label),
          'balance' => $balance,
          'profit' => $profit,
        ];
      }
    } catch (Throwable $e) {
      try {
        $stmt = $pdo->prepare("
          SELECT
            id,
            COALESCE(balance, 1000) AS balance
          FROM users
          ORDER BY balance DESC, id ASC
          LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $dbRows = $stmt->fetchAll();

        foreach ($dbRows as $row) {
          if (!is_array($row)) continue;
          $balance = isset($row['balance']) ? (float)$row['balance'] : 1000.0;
          $profit = $balance - 1000.0;
          $id = (string)($row['id'] ?? '');
          $label = $id !== '' ? ('user' . substr($id, 0, 6)) : 'user';
          $rows[] = [
            'id' => $id,
            'label' => farhuaad_mask_account_name($label),
            'balance' => $balance,
            'profit' => $profit,
          ];
        }
      } catch (Throwable $e2) {
        // Fallback below.
      }
    }
  }

  if (!$rows) {
    $users = farhuaad_read_users();
    foreach ($users as $u) {
      if (!is_array($u)) continue;
      $balance = isset($u['balance']) ? (float)$u['balance'] : 1000.0;
      $profit = $balance - 1000.0;
      $label = farhuaad_user_label_from_row($u);
      $rows[] = [
        'id' => (string)($u['id'] ?? ''),
        'label' => farhuaad_mask_account_name($label),
        'balance' => $balance,
        'profit' => $profit,
      ];
    }
    usort($rows, function ($a, $b) {
      return $b['balance'] <=> $a['balance'];
    });
    $rows = array_slice($rows, 0, $limit);
  }

  return $rows;
}

