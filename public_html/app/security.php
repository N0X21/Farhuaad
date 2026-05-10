<?php
declare(strict_types=1);

/**
 * IP для лимитов: по умолчанию REMOTE_ADDR. За доверенным прокси задайте в .env TRUST_X_FORWARDED_FOR=1.
 */
function farhuaad_client_ip(): string
{
  if (trim((string)($_ENV['TRUST_X_FORWARDED_FOR'] ?? '')) === '1') {
    $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($xff !== '') {
      $parts = array_map('trim', explode(',', $xff));
      $first = $parts[0] ?? '';
      if (filter_var($first, FILTER_VALIDATE_IP)) {
        return $first;
      }
    }
  }
  $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
  return $ip !== '' ? $ip : '0.0.0.0';
}

function farhuaad_csrf_token(): string
{
  if (session_status() !== PHP_SESSION_ACTIVE) {
    throw new RuntimeException('CSRF_FAILED');
  }
  $t = $_SESSION['_farhuaad_csrf'] ?? '';
  if (!is_string($t) || strlen($t) < 32) {
    $t = bin2hex(random_bytes(32));
    $_SESSION['_farhuaad_csrf'] = $t;
  }
  return $t;
}

/**
 * @throws RuntimeException CSRF_FAILED
 */
function farhuaad_verify_csrf(?string $posted): void
{
  if (session_status() !== PHP_SESSION_ACTIVE) {
    throw new RuntimeException('CSRF_FAILED');
  }
  $expected = $_SESSION['_farhuaad_csrf'] ?? '';
  if (!is_string($expected) || $expected === '') {
    throw new RuntimeException('CSRF_FAILED');
  }
  $posted = $posted ?? '';
  if ($posted === '' || !hash_equals($expected, $posted)) {
    throw new RuntimeException('CSRF_FAILED');
  }
}

/**
 * CSRF для JSON API и fetch: тело { "csrf": "..." } или заголовок X-CSRF-Token.
 *
 * @param array<string, mixed>|null $parsedJson Уже распарсенный php://input (если есть).
 */
function farhuaad_verify_csrf_from_json_or_header(?array $parsedJson): void
{
  $token = null;
  if (is_array($parsedJson) && isset($parsedJson['csrf']) && is_string($parsedJson['csrf'])) {
    $t = trim($parsedJson['csrf']);
    if ($t !== '') {
      $token = $t;
    }
  }
  if ($token === null) {
    $h = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($h)) {
      $t = trim($h);
      if ($t !== '') {
        $token = $t;
      }
    }
  }
  farhuaad_verify_csrf($token);
}

/**
 * Лимиты отправки кода на почту с одного IP (анти-DDoS / анти-спам по чужим ящикам).
 *
 * @throws RuntimeException RATE_LIMIT_IP | RATE_LIMIT_BURST
 */
function farhuaad_rate_limit_otp_send(): void
{
  $ip = farhuaad_client_ip();
  $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'otp_ip_sends.json';
  $dir = dirname($file);
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }

  $now = time();
  $fp = fopen($file, 'c+');
  if ($fp === false) {
    return;
  }

  try {
    if (!flock($fp, LOCK_EX)) {
      return;
    }
    $raw = stream_get_contents($fp);
    $state = [];
    if (is_string($raw) && trim($raw) !== '') {
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) {
        $state = $decoded;
      }
    }

    /** @var array<string, list<int>> $byIp */
    $byIp = [];
    foreach ($state as $key => $list) {
      if (!is_string($key) || !is_array($list)) {
        continue;
      }
      $timestamps = [];
      foreach ($list as $ts) {
        if (is_int($ts) || (is_string($ts) && ctype_digit($ts))) {
          $t = (int)$ts;
          if ($t > $now - 3600) {
            $timestamps[] = $t;
          }
        }
      }
      if ($timestamps !== []) {
        $byIp[$key] = $timestamps;
      }
    }

    $list = $byIp[$ip] ?? [];
    $hourly = count($list);
    $burst = 0;
    foreach ($list as $ts) {
      if ($ts > $now - 60) {
        ++$burst;
      }
    }

    if ($burst >= 6) {
      throw new RuntimeException('RATE_LIMIT_BURST');
    }
    if ($hourly >= 20) {
      throw new RuntimeException('RATE_LIMIT_IP');
    }

    $list[] = $now;
    $byIp[$ip] = $list;

    $json = json_encode($byIp, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
      return;
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $json);
    fflush($fp);
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

/**
 * Simple file-based rate limiter by key (for public API endpoints).
 *
 * @throws RuntimeException RATE_LIMIT
 */
function farhuaad_rate_limit_request(string $bucketKey, int $maxRequests, int $windowSeconds): void
{
  $bucketKey = trim($bucketKey);
  if ($bucketKey === '') {
    $bucketKey = 'default';
  }
  $maxRequests = max(1, $maxRequests);
  $windowSeconds = max(1, $windowSeconds);

  $ip = farhuaad_client_ip();
  $stateKey = $bucketKey . '|' . $ip;
  $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'rate_limit.json';
  $dir = dirname($file);
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }

  $now = time();
  $fp = fopen($file, 'c+');
  if ($fp === false) {
    return;
  }

  try {
    if (!flock($fp, LOCK_EX)) {
      return;
    }

    $raw = stream_get_contents($fp);
    $state = [];
    if (is_string($raw) && trim($raw) !== '') {
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) {
        $state = $decoded;
      }
    }

    $threshold = $now - $windowSeconds;
    $nextState = [];
    foreach ($state as $key => $list) {
      if (!is_string($key) || !is_array($list)) {
        continue;
      }
      $kept = [];
      foreach ($list as $ts) {
        if ((is_int($ts) || (is_string($ts) && ctype_digit($ts))) && (int)$ts > $threshold) {
          $kept[] = (int)$ts;
        }
      }
      if ($kept !== []) {
        $nextState[$key] = $kept;
      }
    }

    $list = $nextState[$stateKey] ?? [];
    if (count($list) >= $maxRequests) {
      throw new RuntimeException('RATE_LIMIT');
    }
    $list[] = $now;
    $nextState[$stateKey] = $list;

    $json = json_encode($nextState, JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
      ftruncate($fp, 0);
      rewind($fp);
      fwrite($fp, $json);
      fflush($fp);
    }
  } finally {
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}
