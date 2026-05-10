<?php
declare(strict_types=1);

/**
 * Путь к файлу с OTP-кодами.
 */
function farhuaad_otps_file(): string {
  return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'otps.json';
}

/**
 * Прочитать все OTP из файла.
 *
 * @return array<int, array<string,mixed>>
 */
function farhuaad_read_otps(): array {
  $file = farhuaad_otps_file();
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

/**
 * Записать OTP в файл c блокировкой.
 *
 * @param array<int, array<string,mixed>> $otps
 */
function farhuaad_write_otps(array $otps): void {
  $file = farhuaad_otps_file();
  $dir = dirname($file);
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }

  $json = json_encode($otps, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) {
    throw new RuntimeException('Failed to encode otps json');
  }

  $fp = fopen($file, 'c+');
  if ($fp === false) {
    throw new RuntimeException('Failed to open otps file');
  }

  try {
    if (!flock($fp, LOCK_EX)) {
      throw new RuntimeException('Failed to lock otps file');
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

/**
 * Отправить OTP на почту для входа или регистрации.
 *
 * @param 'login'|'register' $purpose
 *
 * @throws RuntimeException с кодами:
 *  - TOO_FAST
 *  - RATE_LIMIT_IP
 *  - RATE_LIMIT_BURST
 *  - EMAIL_SEND_FAILED
 */
function farhuaad_send_otp(string $email, string $purpose): void {
  $email = mb_strtolower(trim($email));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('INVALID_EMAIL');
  }

  if ($purpose !== 'login' && $purpose !== 'register') {
    throw new RuntimeException('INVALID_PURPOSE');
  }

  if (function_exists('farhuaad_rate_limit_otp_send')) {
    farhuaad_rate_limit_otp_send();
  }

  if (session_status() !== PHP_SESSION_ACTIVE || session_id() === '') {
    throw new RuntimeException('SESSION_REQUIRED');
  }

  $now = time();
  $otps = farhuaad_read_otps();

  // Очистка просроченных записей и поиск последней по этому email+purpose
  $filtered = [];
  $lastForEmail = null;

  foreach ($otps as $otp) {
    if (!is_array($otp)) {
      continue;
    }
    $expiresAt = (int)($otp['expiresAt'] ?? 0);
    if ($expiresAt > $now) {
      $filtered[] = $otp;
    }

    if (
      isset($otp['email'], $otp['purpose']) &&
      mb_strtolower((string)$otp['email']) === $email &&
      (string)$otp['purpose'] === $purpose
    ) {
      $lastForEmail = $otp;
    }
  }

  // Защита от частых запросов — минимум 30 секунд между отправками
  if ($lastForEmail !== null) {
    $createdAt = (int)($lastForEmail['createdAt'] ?? 0);
    if ($createdAt > 0 && ($now - $createdAt) < 30) {
      throw new RuntimeException('TOO_FAST');
    }
  }

  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

  $codeHash = password_hash($code, PASSWORD_DEFAULT);

  $record = [
    'email' => $email,
    'purpose' => $purpose,
    'codeHash' => $codeHash,
    'createdAt' => $now,
    'expiresAt' => $now + 180,
    'attempts' => 0,
    'phpSessionId' => session_id(),
  ];
  
  $filtered[] = $record;

  if (count($filtered) > 500) {
  $filtered = array_slice($filtered, -500);
  }
  
  farhuaad_write_otps($filtered);

  $subject = $purpose === 'register'
    ? 'Код подтверждения регистрации в Farhuaad'
    : 'Код для входа в Farhuaad';

  $body = "Ваш код подтверждения: {$code}\n\n"
        . "Код действителен 3 минуты.\n"
        . "Если вы не запрашивали код, просто игнорируйте это письмо.";

  if (!function_exists('farhuaad_send_email')) {
    throw new RuntimeException('EMAIL_SEND_FAILED');
  }

  $ok = farhuaad_send_email($email, $subject, $body);
  if (!$ok) {
    throw new RuntimeException('EMAIL_SEND_FAILED');
  }
}

/**
 * Проверка OTP-кода для email.
 *
 * @param 'login'|'register' $purpose
 *
 * @throws RuntimeException с кодами:
 *  - OTP_EXPIRED
 *  - OTP_INVALID
 *  - OTP_TOO_MANY_ATTEMPTS
 */
function farhuaad_verify_otp(string $email, string $code, string $purpose): void {
  usleep(random_int(200000, 800000));
  $email = mb_strtolower(trim($email));
  $code = preg_replace('/\s+/', '', (string)$code);

  if (!preg_match('/^[0-9]{6}$/', $code)) {
    throw new RuntimeException('OTP_INVALID');
  }

  if ($purpose !== 'login' && $purpose !== 'register') {
    throw new RuntimeException('INVALID_PURPOSE');
  }

  $now = time();
  $otps = farhuaad_read_otps();

  $foundIndex = null;
  $latestIndex = null;
  $latestCreatedAt = 0;

  foreach ($otps as $i => $otp) {
    if (!is_array($otp)) {
      continue;
    }

    if (
      isset($otp['email'], $otp['purpose']) &&
      mb_strtolower((string)$otp['email']) === $email &&
      (string)$otp['purpose'] === $purpose
    ) {
      $createdAt = (int)($otp['createdAt'] ?? 0);
      if ($createdAt >= $latestCreatedAt) {
    $latestCreatedAt = $createdAt;
    $latestIndex = $i;
      }
    }
  }

  if ($latestIndex === null) {
    throw new RuntimeException('OTP_INVALID');
  }

  $otp = $otps[$latestIndex];

  $boundSid = isset($otp['phpSessionId']) ? (string)$otp['phpSessionId'] : '';
  if ($boundSid !== '') {
    if (!hash_equals($boundSid, session_id())) {
      throw new RuntimeException('OTP_DEVICE_CHANGED');
    }
  } else {
    $ip = function_exists('farhuaad_client_ip') ? farhuaad_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (($otp['ip'] ?? '') !== $ip || ($otp['agent'] ?? '') !== $agent) {
      throw new RuntimeException('OTP_DEVICE_CHANGED');
    }
  }

  $attempts = (int)($otp['attempts'] ?? 0);

  if ($attempts >= 5) {
    throw new RuntimeException('OTP_TOO_MANY_ATTEMPTS');
  }

  $expiresAt = (int)($otp['expiresAt'] ?? 0);
  if ($expiresAt < $now) {
    // Можно удалить просроченную запись
    unset($otps[$latestIndex]);
    farhuaad_write_otps(array_values($otps));
    throw new RuntimeException('OTP_EXPIRED');
  }

  if (!password_verify($code, (string)($otp['codeHash'] ?? ''))) {
    $otps[$latestIndex]['attempts'] = $attempts + 1;
    farhuaad_write_otps(array_values($otps));
    throw new RuntimeException('OTP_INVALID');
  }

  // Успешное подтверждение — удаляем использованный код
  unset($otps[$latestIndex]);
  farhuaad_write_otps(array_values($otps));
}
