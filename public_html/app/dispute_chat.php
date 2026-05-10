<?php

declare(strict_types=1);

require_once __DIR__ . '/dispute_chat_geo_redact.php';

/** Сообщения старше этого интервала удаляются (часов). */
const FARHUAAD_DISPUTE_CHAT_TTL_HOURS = 24;

/** Максимум символов в сообщении. */
const FARHUAAD_DISPUTE_CHAT_MAX_LEN = 500;

/** Не чаще одного сообщения от пользователя в одном споре (секунды). */
const FARHUAAD_DISPUTE_CHAT_COOLDOWN_SEC = 5;

/** Префикс зашифрованного тела в колонке `body` (AES-256-GCM). */
const FARHUAAD_DISPUTE_CHAT_CIPHER_PREFIX = 'c1:';

function farhuaad_dispute_chat_crypto_key(): string
{
  static $key = null;
  if ($key !== null) {
    return $key;
  }
  $secret = (string)((($_ENV['FARHUAAD_DISPUTE_CHAT_SECRET'] ?? getenv('FARHUAAD_DISPUTE_CHAT_SECRET')) ?: ''));
  if ($secret === '') {
    $secret = (string)((($_ENV['FARHUAAD_APP_SECRET'] ?? getenv('FARHUAAD_APP_SECRET')) ?: ''));
  }
  if ($secret === '') {
    error_log('FARHUAAD_DISPUTE_CHAT_SECRET is not set — chat bodies use a dev-only derived key; set it in .env for production.');
    $secret = 'farhuaad-dev-chat-secret-change-me';
  }
  $key = hash('sha256', 'farhuaad_dispute_chat_v1|' . $secret, true);
  return $key;
}

/**
 * Шифрует текст для хранения в БД.
 */
function farhuaad_dispute_chat_encrypt_body(string $plain): string
{
  if ($plain === '') {
    return '';
  }
  $key = farhuaad_dispute_chat_crypto_key();
  $iv = random_bytes(12);
  $tag = '';
  $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
  if ($ct === false || $tag === '') {
    throw new RuntimeException('CHAT_ENCRYPT_FAILED');
  }
  return FARHUAAD_DISPUTE_CHAT_CIPHER_PREFIX . base64_encode($iv . $tag . $ct);
}

/**
 * Расшифровывает тело из БД; старые записи без префикса возвращаются как есть.
 */
function farhuaad_dispute_chat_decrypt_body(string $stored): string
{
  if ($stored === '' || !str_starts_with($stored, FARHUAAD_DISPUTE_CHAT_CIPHER_PREFIX)) {
    return $stored;
  }
  $bin = base64_decode(substr($stored, strlen(FARHUAAD_DISPUTE_CHAT_CIPHER_PREFIX)), true);
  if ($bin === false || strlen($bin) < 12 + 16) {
    error_log('dispute_chat: corrupt ciphertext');
    return '';
  }
  $iv = substr($bin, 0, 12);
  $tag = substr($bin, 12, 16);
  $ct = substr($bin, 28);
  $key = farhuaad_dispute_chat_crypto_key();
  $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
  if ($plain === false) {
    error_log('dispute_chat: decrypt failed (wrong FARHUAAD_DISPUTE_CHAT_SECRET?)');
    return '';
  }
  return $plain;
}

/**
 * Полная маска только для похожих на личные имена фрагментов в тексте сообщения (не для ников).
 */
function farhuaad_dispute_chat_mask_full(string $value): string
{
  $value = trim($value);
  if ($value === '') {
    return '****';
  }
  if (function_exists('mb_strlen')) {
    $len = mb_strlen($value);
    $n = min(max($len, 4), 32);
  } else {
    $len = strlen($value);
    $n = min(max($len, 4), 32);
  }

  return str_repeat('*', $n);
}

/**
 * Подпись автора в чате — как ник: первые символы + звёздочки (farhuaad_mask_account_name).
 */
function farhuaad_dispute_chat_mask_author_label(string $label): string
{
  return farhuaad_mask_account_name($label);
}

/**
 * Маскирует @никнеймы так же, как отображается ник (не полным скрытием).
 */
function farhuaad_dispute_chat_redact_at_mentions(string $body): string
{
  $out = preg_replace_callback(
    '/@([\p{L}\p{N}_]{2,})/u',
    static function (array $m): string {
      $h = (string)($m[1] ?? '');
      if ($h === '') {
        return (string)($m[0] ?? '');
      }
      return '@' . farhuaad_mask_account_name($h);
    },
    $body
  );
  return is_string($out) ? $out : $body;
}

/**
 * Только имя «баха» (кириллица и латиница bakha), любой регистр.
 */
function farhuaad_dispute_chat_redact_bakha_forms(string $body): string
{
  if ($body === '') {
    return $body;
  }
  $out = preg_replace('/(?<![\p{L}\p{N}])баха(?![\p{L}\p{N}])/ui', '****', $body);
  $out = is_string($out) ? $out : $body;
  $out2 = preg_replace('/(?<![\p{L}\p{N}])bakha(?![\p{L}\p{N}])/ui', '****', $out);

  return is_string($out2) ? $out2 : $out;
}

/**
 * Ссылки (http(s), www, домены, мессенджеры), кодоподобные фрагменты и типичные XSS/JS-вставки в тексте.
 */
function farhuaad_dispute_chat_redact_links_and_codes(string $body): string
{
  if ($body === '') {
    return $body;
  }
  $out = $body;
  $scriptish = [
    '#(?:var|let|const)\s+\$?[a-zA-Z_][\w$]*\s*=\s*[^\n;]{0,240};\s*alert\s*\([^)]{0,500}\)#iu',
    '#["\']\s*;\s*alert\s*\([^)]{0,500}\)#iu',
    '#\balert\s*\([^)]{0,500}\)#iu',
    '#\beval\s*\([^)]{0,500}\)#iu',
    '#\bnew\s+Function\s*\([^)]{0,500}\)#iu',
    '#\b(?:setTimeout|setInterval)\s*\(\s*["\'][^"\']{0,300}["\']#iu',
    '#\bdocument\.(?:cookie|write|domain|body|referrer)\b(?:\s*\([^)]{0,500}\))?#iu',
    '#\bwindow\.(?:location|open|eval)\b[^;\n]{0,160}#iu',
    '#\blocation\.(?:href|replace|assign)\s*=[^;\n]{0,160}#iu',
    '#\bString\.fromCharCode\s*\([^)]{0,600}\)#iu',
    '#<script\b[\s\S]{0,8000}?</script>#iu',
    '#<script\b[\s\S]{0,4000}$#imu',
    '#</script>#iu',
    '#javascript:\S+#iu',
    '#\bdata:text/html[^,\s]{0,200}#iu',
    '#\bon[a-z]+\s*=\s*[^\s>]{0,160}#iu',
    '#\b(?:import|require)\s*\(\s*["\'][^"\']{8,}["\']\s*\)#iu',
  ];
  foreach ($scriptish as $p) {
    $r = preg_replace($p, '****', $out);
    if (is_string($r)) {
      $out = $r;
    }
  }
  $urlish = [
    '#https?://[^\s<>"\'\)\]\}]+#iu',
    '#\bftp://[^\s<>"\'\)\]\}]+#iu',
    '#\bwww\.[^\s<>"\'\)\]\}]+#iu',
    '#(?<![\w/])(?:t\.me|telegram\.me|tg://|wa\.me|api\.whatsapp\.com|discord\.gg|discord\.com/invite|youtu\.be|youtube\.com|instagram\.com|vk\.com|ok\.ru|x\.com|twitter\.com|facebook\.com|fb\.me)/[^\s<>"\'\)\]\}]*#iu',
    '#\b[a-z0-9][a-z0-9.-]*\.(?:ru|com|org|net|io|gg|me|app|xyz|info|biz|tv|cc|su|рф|online|site|click|link)(?:/[^\s<>"\'\)\]\}]*)?#iu',
  ];
  foreach ($urlish as $p) {
    $r = preg_replace($p, '****', $out);
    if (is_string($r)) {
      $out = $r;
    }
  }
  $r = preg_replace('/\b0x[a-fA-F0-9]{8,}\b/', '****', $out);
  $out = is_string($r) ? $r : $out;
  $r = preg_replace('/\b[a-fA-F0-9]{16,}\b/', '****', $out);
  $out = is_string($r) ? $r : $out;
  $r = preg_replace('/\b[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\b/i', '****', $out);
  $out = is_string($r) ? $r : $out;
  $r = preg_replace('/\b[A-Z0-9]{2,}(?:-[A-Z0-9]{2,}){2,}\b/', '****', $out);
  $out = is_string($r) ? $r : $out;
  $r = preg_replace('#\b[A-Za-z0-9+/]{28,}={0,2}\b#', '****', $out);

  return is_string($r) ? $r : $out;
}

/**
 * @param array{name?:string,email?:string,walletAddress?:string,wallet_address?:string,id?:string|int,user_id?:string|int} $row
 */
function farhuaad_dispute_chat_author_display(array $row): string
{
  $labelRow = [
    'id' => (string)($row['user_id'] ?? $row['id'] ?? ''),
    'name' => trim((string)($row['name'] ?? '')),
    'email' => trim((string)($row['email'] ?? '')),
    'walletAddress' => trim((string)($row['wallet_address'] ?? $row['walletAddress'] ?? '')),
  ];
  $label = farhuaad_user_label_from_row($labelRow);
  return farhuaad_dispute_chat_mask_author_label($label);
}

/**
 * Токены для подчистки в теле сообщения: email (локальная часть), кошелёк. Имя/ник из профиля не трогаем — ник виден в подписи и @.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<string> от длинных к коротким
 */
function farhuaad_dispute_chat_participant_tokens_for_redact(array $rows): array
{
  $tokens = [];
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $email = trim((string)($row['email'] ?? ''));
    $at = strpos($email, '@');
    if ($at !== false && $at > 0) {
      $local = substr($email, 0, $at);
      $llen = function_exists('mb_strlen') ? mb_strlen($local) : strlen($local);
      if ($local !== '' && $llen >= 2) {
        $tokens[$local] = true;
      }
    }
    $wallet = trim((string)($row['wallet_address'] ?? $row['walletAddress'] ?? ''));
    if (strlen($wallet) >= 10) {
      $tokens[$wallet] = true;
    }
  }
  $list = array_keys($tokens);
  usort($list, function ($a, $b) {
    $la = function_exists('mb_strlen') ? mb_strlen($a) : strlen($a);
    $lb = function_exists('mb_strlen') ? mb_strlen($b) : strlen($b);
    return $lb <=> $la;
  });
  return $list;
}

/**
 * Объединяет списки токенов для маскировки и сортирует от длинных к коротким.
 *
 * @param list<string> ...$lists
 * @return list<string>
 */
function farhuaad_dispute_chat_redact_merge_tokens_by_length(array ...$lists): array
{
  $set = [];
  foreach ($lists as $list) {
    foreach ($list as $t) {
      $t = trim((string)$t);
      if ($t === '') {
        continue;
      }
      $len = function_exists('mb_strlen') ? mb_strlen($t) : strlen($t);
      if ($len < 2) {
        continue;
      }
      $set[$t] = true;
    }
  }
  $keys = array_keys($set);
  usort($keys, function ($a, $b) {
    $la = function_exists('mb_strlen') ? mb_strlen($a) : strlen($a);
    $lb = function_exists('mb_strlen') ? mb_strlen($b) : strlen($b);
    if ($lb !== $la) {
      return $lb <=> $la;
    }
    return strcmp($b, $a);
  });
  return $keys;
}

/**
 * Маскирует в тексте: email/кошелёк участников, гео; ссылки и коды; только слово «баха»/bakha; @ники.
 *
 * @param list<string> $tokensLongestFirst
 */
function farhuaad_dispute_chat_redact_body_for_display(string $body, array $tokensLongestFirst): string
{
  $tokensLongestFirst = farhuaad_dispute_chat_redact_merge_tokens_by_length(
    $tokensLongestFirst,
    farhuaad_dispute_chat_geo_redact_tokens()
  );
  $out = $body;
  foreach ($tokensLongestFirst as $token) {
    $token = trim($token);
    if ($token === '') {
      continue;
    }
    $q = preg_quote($token, '/');
    $isWallet = strlen($token) >= 20 && preg_match('/^0x[0-9a-f]+$/i', $token) === 1;
    $mask = $isWallet ? farhuaad_dispute_chat_mask_full($token) : farhuaad_mask_account_name($token);
    $replaced = preg_replace('/(?<![\p{L}\p{N}])' . $q . '(?![\p{L}\p{N}])/ui', $mask, $out);
    if (is_string($replaced)) {
      $out = $replaced;
    }
  }
  $out = preg_replace(
    '/\b[a-z0-9][a-z0-9._%+\-]*@[a-z0-9][a-z0-9.\-]*\.[a-z]{2,}\b/i',
    '***@***',
    $out
  );
  if (!is_string($out)) {
    return $body;
  }
  $out = farhuaad_dispute_chat_redact_links_and_codes($out);
  $out = farhuaad_dispute_chat_redact_bakha_forms($out);

  return farhuaad_dispute_chat_redact_at_mentions($out);
}

function farhuaad_ensure_dispute_chat_table(PDO $pdo): void
{
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS dispute_chat_messages (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      dispute_id BIGINT UNSIGNED NOT NULL,
      user_id BIGINT UNSIGNED NOT NULL,
      body TEXT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_dispute_created (dispute_id, created_at),
      INDEX idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
  try {
    $pdo->exec('ALTER TABLE dispute_chat_messages MODIFY body TEXT NOT NULL');
  } catch (Throwable $e) {
    // Таблицы может не быть в тестах / уже TEXT.
  }
}

function farhuaad_dispute_chat_purge_expired(PDO $pdo): void
{
  $pdo->exec(
    'DELETE FROM dispute_chat_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . (int)FARHUAAD_DISPUTE_CHAT_TTL_HOURS . ' HOUR)'
  );
  farhuaad_dispute_chat_purge_policy_violations($pdo, 350, 'DESC');
  farhuaad_dispute_chat_purge_policy_violations($pdo, 350, 'ASC');
}

/**
 * Удаляет сообщения за период TTL, не проходящие модерацию (в т.ч. уже сохранённые ранее).
 */
function farhuaad_dispute_chat_purge_policy_violations(PDO $pdo, int $batchSize = 400, string $order = 'DESC'): int
{
  $batchSize = max(50, min(800, $batchSize));
  $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
  $stmt = $pdo->query(
    'SELECT m.id, m.body, d.category
     FROM dispute_chat_messages m
     INNER JOIN disputes d ON d.id = m.dispute_id
     WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)FARHUAAD_DISPUTE_CHAT_TTL_HOURS . ' HOUR)
     ORDER BY m.id ' . $order . '
     LIMIT ' . $batchSize
  );
  if ($stmt === false) {
    return 0;
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!is_array($rows) || $rows === []) {
    return 0;
  }
  $del = $pdo->prepare('DELETE FROM dispute_chat_messages WHERE id = ?');
  $deleted = 0;
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $id = (int)($row['id'] ?? 0);
    $stored = (string)($row['body'] ?? '');
    if ($id <= 0) {
      continue;
    }
    $plain = farhuaad_dispute_chat_decrypt_body($stored);
    if ($stored !== '' && $plain === '' && str_starts_with($stored, FARHUAAD_DISPUTE_CHAT_CIPHER_PREFIX)) {
      continue;
    }
    $category = trim((string)($row['category'] ?? ''));
    $catLower = function_exists('mb_strtolower') ? mb_strtolower($category) : strtolower($category);
    $isPolitics = strpos($catLower, 'политик') !== false || strpos($catLower, 'politics') !== false;

    if (farhuaad_dispute_chat_moderation_reject($plain, $isPolitics) !== null) {
      $del->execute([$id]);
      $deleted++;
    }
  }
  return $deleted;
}

/**
 * Реклама и ссылки: лимит URL, мессенджеры, сокращатели, обходы без схемы.
 *
 * @return null|string код отказа (SPAM_LINKS)
 */
function farhuaad_dispute_chat_spam_links_reject(string $t, string $ascii, string $decodedLower): ?string
{
  $bundles = array_values(array_unique([$t, $ascii, $decodedLower]));

  if (preg_match_all('#https?://#i', $t) > 1) {
    return 'SPAM_LINKS';
  }

  foreach ($bundles as $hay) {
    if ($hay === '') {
      continue;
    }
    if (preg_match('/\bwww\./i', $hay) === 1) {
      return 'SPAM_LINKS';
    }
    if (preg_match('/\bftp:\/\//i', $hay) === 1) {
      return 'SPAM_LINKS';
    }
    if (preg_match('/\bmailto:/i', $hay) === 1) {
      return 'SPAM_LINKS';
    }
    if (preg_match('/\b(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})\b/', $hay) === 1) {
      return 'SPAM_LINKS';
    }
  }

  $messengersAndSocial = '/(?:^|[^\p{L}0-9\/])(?:t\.me\/|telegram\.me\/|tg:\/\/|wa\.me\/|api\.whatsapp\.com\/|discord\.gg\/|discord\.com\/invite|youtu\.be\/|youtube\.com\/(?:watch|live|shorts)\/?|tiktok\.com\/@?|instagram\.com\/|ok\.ru\/|vk\.com\/|facebook\.com\/|fb\.me\/|m\.facebook\.com\/|(?:x|twitter)\.com\/|threads\.net\/|snapchat\.com\/|onlyfans\.com\/)/iu';
  foreach ($bundles as $hay) {
    if ($hay !== '' && preg_match($messengersAndSocial, $hay) === 1) {
      return 'SPAM_LINKS';
    }
  }

  $shorteners = '/\b(?:bit\.ly|goo\.gl|tinyurl\.com|t\.co|ow\.ly|is\.gd|cutt\.ly|clck\.ru|tiny\.cc|short\.link|buff\.ly|rebrand\.ly|rb\.gy|shorturl\.at)\b/i';
  foreach ($bundles as $hay) {
    if ($hay !== '' && preg_match($shorteners, $hay) === 1) {
      return 'SPAM_LINKS';
    }
  }

  foreach ($bundles as $hay) {
    if ($hay !== '' && preg_match('/\butm_(?:source|medium|campaign|content|term)=/i', $hay) === 1) {
      return 'SPAM_LINKS';
    }
  }

  $adCue = [
    '/пишите\s+(в\s+)?(telegram|телеграм|t\.me|инстаграм|instagram|whatsapp|вотсап|дискорд|discord)/ui',
    '/переходите.{0,140}(telegram|телеграм|t\.me|инстаграм|instagram|whatsapp|вотсап|по\s+ссылке)/ui',
    '/подписывайтесь.{0,100}(на\s+)?(канал|telegram|телеграм|t\.me|инстаграм)/ui',
    '/(?:заказать|купить|скидка|промокод|промо[\s-]?код).{0,80}(telegram|телеграм|t\.me|whatsapp|вотсап|сайт|ссылк)/ui',
  ];
  foreach ($adCue as $p) {
    if (preg_match($p, $t) === 1 || preg_match($p, $decodedLower) === 1) {
      return 'SPAM_LINKS';
    }
  }

  return null;
}

/**
 * Заменяет латинские буквы, похожие на кириллицу — типичный обход словаря мата (x→х, c→с и т.д.).
 * Используется только внутри модерации чата.
 */
function farhuaad_dispute_chat_fold_latin_homoglyphs(string $s): string
{
  static $map = [
    'a' => 'а',
    'c' => 'с',
    'e' => 'е',
    'f' => 'ф',
    'h' => 'н',
    'k' => 'к',
    'm' => 'м',
    'n' => 'н',
    'o' => 'о',
    'p' => 'р',
    't' => 'т',
    'x' => 'х',
    'y' => 'у',
  ];

  return strtr($s, $map);
}

/**
 * Наборы regex: маскируемые (мат/пошлость) и только отклонение (геополитика в чате).
 *
 * @return array{redact: list<string>, geo_reject: list<string>}
 */
function farhuaad_dispute_chat_moderation_pattern_sets(): array
{
  static $cache = null;
  if ($cache !== null) {
    return $cache;
  }
  $redact = [
    // «нахуй/похуй» и т.п.: после «на/по» буква «а» стоит перед «х», обычный (?<!\p{L})хуй это не ловит
    '/наху[йеяию]/ui',
    '/на\s+хуй/ui',
    '/на\s+хуя/ui',
    '/поху[йеяию]/ui',
    '/по\s+хуй/ui',
    '/ниху[йеяию]/ui',
    '/хуй(сос|соси|ло|ня|ище|егол|еплёт|еплет|нут|ство)|хуесос|хуеплёт|хуеплет/ui',
    '/(?<![\p{L}\p{N}])хуй(?=[\p{L}])/ui',
    '/хуяч/ui',
    '/(?<![\p{L}\p{N}])(пизд|пёзд)(?=[\p{L}])/ui',
    '/(?<![\p{L}\p{N}])(еб|ёб)(?!ырь)(?=[\p{L}])/ui',
    '/пид(ор|арас|ор|оры)|пёдор|пидарас/ui',
    '/долбо[её]б|долба[её]б|долбоеб|долбаеб/ui',
    '/\bdolboeb\b/i',
    '/еблан|ёблан|мудак|мудач|гандон|бляд|блять|сука/ui',
    '/дроч/ui',
    '/дрюч/ui',
    '/пенис/ui',
    '/фаллос/ui',
    '/мастурб/ui',
    '/сперм/ui',
    '/эякул/ui',
    '/минет/ui',
    '/(?<![\p{L}\p{N}])(хуй|хуя|хуе|хуи|хую|пизд|ебан|ёбан|ебёт|ебать|ебал|бляд|блять|сука|мудак|гандон|срать|дерьмо)(?![\p{L}\p{N}])/ui',
    '/\b(fuck|shit|cunt|dick|cock|pussy|whore|fucker|motherfucker|penis|jizz|cumshot)\b/i',
    '/\bjerk[\s-]*off\b/i',
    '/\bmasturbat/i',
    '/(?<![\p{L}\p{N}])секс(?![\p{L}\p{N}])/ui',
    '/\bsex\b/i',
    '/\bs[\W_]*e[\W_]*x\b/i',
    '/(?<![\p{L}\p{N}])у3бан(?![\p{L}\p{N}])/ui',
    '/у[\s\._\-]*3[\s\._\-]*бан/ui',
    '/(?<![\p{L}\p{N}])у[еёe]бан(?![\p{L}\p{N}])/ui',
    '/у[\s\._\-]*[еёe][\s\._\-]*бан/ui',
    '/\bu[\s\._\-]*[e3ё][\s\._\-]*ban\b/ui',
    '/\byeban\b/i',
  ];
  $geoReject = [
    '/(?<![\p{L}\p{N}])бахмут(?![\p{L}\p{N}])/ui',
    '/\bbakhmut\b/i',
    '/(?<![\p{L}\p{N}])соледар(?![\p{L}\p{N}])/ui',
    '/\bsoledar\b/i',
  ];
  $cache = ['redact' => $redact, 'geo_reject' => $geoReject];

  return $cache;
}

/**
 * @param list<array{0:int,1:int}> $ranges
 * @return list<array{0:int,1:int}>
 */
function farhuaad_dispute_chat_merge_codepoint_ranges(array $ranges): array
{
  if ($ranges === []) {
    return [];
  }
  usort($ranges, static function (array $a, array $b): int {
    return $a[0] <=> $b[0];
  });
  $out = [];
  foreach ($ranges as $r) {
    $s = $r[0];
    $l = $r[1];
    if ($l <= 0) {
      continue;
    }
    if ($out === []) {
      $out[] = [$s, $l];
      continue;
    }
    $li = count($out) - 1;
    $lastS = $out[$li][0];
    $lastE = $lastS + $out[$li][1];
    if ($s <= $lastE) {
      $newE = max($lastE, $s + $l);
      $out[$li][1] = $newE - $lastS;
    } else {
      $out[] = [$s, $l];
    }
  }

  return $out;
}

/**
 * Интерпретатор Python для scripts/obscene_redact.py.
 * FARHUAAD_PYTHON_CMD: путь или имя (python3, py …), пусто = авто-поиск; 0|off|php = только PHP.
 */
function farhuaad_dispute_chat_python_binary_for_redact(): ?string
{
  static $resolved = false;
  if ($resolved !== false) {
    return $resolved === '' ? null : $resolved;
  }
  $explicit = trim((string)(($_ENV['FARHUAAD_PYTHON_CMD'] ?? getenv('FARHUAAD_PYTHON_CMD')) ?: ''));
  if ($explicit === '0' || strcasecmp($explicit, 'off') === 0 || strcasecmp($explicit, 'php') === 0) {
    $resolved = '';

    return null;
  }
  if ($explicit !== '') {
    $resolved = $explicit;

    return $explicit;
  }
  $candidates = ['python3', 'python'];
  if (DIRECTORY_SEPARATOR === '\\') {
    array_unshift($candidates, 'py');
  }
  foreach ($candidates as $bin) {
    $descriptorspec = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];
    $proc = @proc_open([$bin, '-c', 'print(1)'], $descriptorspec, $pipes, null, null, ['bypass_shell' => true]);
    if (is_resource($proc)) {
      fclose($pipes[0]);
      stream_get_contents($pipes[1]);
      fclose($pipes[1]);
      fclose($pipes[2]);
      $code = proc_close($proc);
      if ($code === 0) {
        $resolved = $bin;

        return $bin;
      }
    }
  }
  $resolved = '';

  return null;
}

/**
 * Маскировка через scripts/obscene_redact.py (тот же алгоритм, что в утилите).
 *
 * @return null при сбое или недоступности Python
 */
function farhuaad_dispute_chat_redact_obscene_via_python(string $text): ?string
{
  if ($text === '') {
    return '';
  }
  $bin = farhuaad_dispute_chat_python_binary_for_redact();
  if ($bin === null) {
    return null;
  }
  $script = dirname(__DIR__) . '/scripts/obscene_redact.py';
  if (!is_readable($script)) {
    return null;
  }
  $descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
  ];
  $cwd = dirname(__DIR__);
  @putenv('PYTHONIOENCODING=utf-8');
  @putenv('PYTHONUTF8=1');
  $scriptReal = realpath($script);
  if ($scriptReal !== false) {
    $script = $scriptReal;
  }
  $proc = @proc_open([$bin, $script], $descriptorspec, $pipes, $cwd, null, ['bypass_shell' => true]);
  if (!is_resource($proc) && DIRECTORY_SEPARATOR === '\\') {
    $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($script);
    $proc = @proc_open($cmd, $descriptorspec, $pipes, $cwd, null);
  }
  if (!is_resource($proc)) {
    return null;
  }
  fwrite($pipes[0], $text);
  fclose($pipes[0]);
  $out = stream_get_contents($pipes[1]);
  $err = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  proc_close($proc);
  if ($err !== '' && $err !== false) {
    $trimErr = trim((string)$err);
    if ($trimErr !== '') {
      error_log('obscene_redact.py: ' . $trimErr);
    }
  }
  if (!is_string($out)) {
    return null;
  }
  $out = preg_replace('/\r?\n$/', '', $out);
  if ($out === '' && $text !== '') {
    return null;
  }
  $wLen = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
  $oLen = function_exists('mb_strlen') ? mb_strlen($out, 'UTF-8') : strlen($out);
  if ($wLen !== $oLen) {
    return null;
  }

  return $out;
}

/**
 * Маскирует мат и похожую лексику звёздочками по символам; совпадения ищутся в «сложённой» строке (латиница→кириллица),
 * замена применяется к исходному тексту (сохраняется обход x→х и т.д.).
 * Если в PATH есть Python — вызывается scripts/obscene_redact.py; иначе встроенная PHP-логика.
 */
function farhuaad_dispute_chat_redact_obscene_tokens(string $text): string
{
  $work = str_replace("\0", '', $text);
  $work = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $work) ?? $work;
  if ($work === '') {
    return $work;
  }
  $viaPy = farhuaad_dispute_chat_redact_obscene_via_python($work);
  if ($viaPy !== null) {
    return $viaPy;
  }
  $lower = function_exists('mb_strtolower') ? mb_strtolower($work, 'UTF-8') : strtolower($work);
  $folded = farhuaad_dispute_chat_fold_latin_homoglyphs($lower);
  $wLen = function_exists('mb_strlen') ? mb_strlen($work, 'UTF-8') : strlen($work);
  $fLen = function_exists('mb_strlen') ? mb_strlen($folded, 'UTF-8') : strlen($folded);
  if ($wLen !== $fLen) {
    return $work;
  }
  $ranges = [];
  $sets = farhuaad_dispute_chat_moderation_pattern_sets();
  foreach ($sets['redact'] as $pattern) {
    $offset = 0;
    $fBytes = strlen($folded);
    while ($offset < $fBytes && preg_match($pattern, $folded, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
      $bytePos = (int)$m[0][1];
      $matched = (string)$m[0][0];
      $prefix = substr($folded, 0, $bytePos);
      $cpStart = function_exists('mb_strlen') ? mb_strlen($prefix, 'UTF-8') : strlen($prefix);
      $cpLen = function_exists('mb_strlen') ? mb_strlen($matched, 'UTF-8') : strlen($matched);
      if ($cpLen > 0) {
        $ranges[] = [$cpStart, $cpLen];
      }
      $offset = $bytePos + strlen($matched);
      if ($offset <= $bytePos) {
        break;
      }
    }
  }
  $merged = farhuaad_dispute_chat_merge_codepoint_ranges($ranges);
  if ($merged === []) {
    return $work;
  }
  $chars = preg_split('//u', $work, -1, PREG_SPLIT_NO_EMPTY);
  if (!is_array($chars)) {
    return $work;
  }
  foreach ($merged as $r) {
    $s = $r[0];
    $l = $r[1];
    for ($i = 0; $i < $l; $i++) {
      $idx = $s + $i;
      if (isset($chars[$idx])) {
        $chars[$idx] = '*';
      }
    }
  }

  return implode('', $chars);
}

/**
 * Проверка до маскировки: пусто, длина, HTML, дети.
 */
function farhuaad_dispute_chat_moderation_precheck(string $text): ?string
{
  $t = str_replace("\0", '', $text);
  $t = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $t) ?? $t;
  $t = trim($t);
  if ($t === '') {
    return 'EMPTY';
  }
  $len = function_exists('mb_strlen') ? mb_strlen($t) : strlen($t);
  if ($len < 2) {
    return 'TOO_SHORT';
  }
  if ($len > FARHUAAD_DISPUTE_CHAT_MAX_LEN) {
    return 'TOO_LONG';
  }
  $ascii = strtolower($t);
  $decoded = $t;
  for ($i = 0; $i < 6; $i++) {
    $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!is_string($next) || $next === $decoded) {
      break;
    }
    $decoded = $next;
  }
  $probes = array_values(array_unique([$t, $ascii, $decoded]));
  foreach ($probes as $probe) {
    if ($probe === '') {
      continue;
    }
    if (preg_match('/<\s*\/?\s*[a-z][a-z0-9:-]*\b/i', $probe) === 1) {
      return 'HTML';
    }
    if (preg_match('/<\s*!\s*(doctype|--|\[)/i', $probe) === 1) {
      return 'HTML';
    }
    if (preg_match('/<\s*\?/i', $probe) === 1) {
      return 'HTML';
    }
    if (preg_match('/<\s*%/i', $probe) === 1) {
      return 'HTML';
    }
    if (preg_match('/\bjavascript\s*:/i', $probe) === 1) {
      return 'HTML';
    }
    if (preg_match('/\bdata\s*:\s*text\s*\/\s*html/i', $probe) === 1) {
      return 'HTML';
    }
    if (preg_match('/\bon[a-z]+\s*=/i', $probe) === 1) {
      return 'HTML';
    }
  }
  $minorPatterns = [
    '/педофил/ui',
    '/педо(?!метр|метри)/ui',
    '/детск[а-я]*\s*(секс|порно|голы|интим)/ui',
    '/(child|kids?)\s*(sex|porn)/i',
    '/\b(cp|csam|jailbait)\b/i',
    '/цп\s/ui',
    '/школьниц.*(гол|секс|18-)/ui',
    '/несовершеннолетн.*(секс|порно)/ui',
  ];
  foreach ($minorPatterns as $p) {
    if (preg_match($p, $t) === 1 || preg_match($p, $ascii) === 1) {
      return 'SEXUAL_MINORS';
    }
  }

  return null;
}

/**
 * После маскировки мата: геополитика, политика, спам.
 */
function farhuaad_dispute_chat_moderation_postcheck(string $text, bool $isPolitics = false): ?string
{
  $t = str_replace("\0", '', $text);
  $t = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $t) ?? $t;
  $t = trim($t);
  if ($t === '') {
    return 'EMPTY';
  }
  $len = function_exists('mb_strlen') ? mb_strlen($t) : strlen($t);
  if ($len < 2) {
    return 'TOO_SHORT';
  }
  if ($len > FARHUAAD_DISPUTE_CHAT_MAX_LEN) {
    return 'TOO_LONG';
  }

  $lower = function_exists('mb_strtolower') ? mb_strtolower($t) : strtolower($t);
  $ascii = strtolower($t);
  $decoded = $t;
  for ($i = 0; $i < 6; $i++) {
    $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!is_string($next) || $next === $decoded) {
      break;
    }
    $decoded = $next;
  }
  $decodedLower = function_exists('mb_strtolower') ? mb_strtolower($decoded) : strtolower($decoded);
  $foldLower = farhuaad_dispute_chat_fold_latin_homoglyphs($lower);
  $foldDecodedLower = farhuaad_dispute_chat_fold_latin_homoglyphs($decodedLower);

  $sets = farhuaad_dispute_chat_moderation_pattern_sets();
  foreach ($sets['geo_reject'] as $p) {
    foreach ([$lower, $ascii, $decodedLower, $foldLower, $foldDecodedLower] as $hay) {
      if ($hay !== '' && preg_match($p, $hay) === 1) {
        return 'OBSCENE';
      }
    }
  }

  return farhuaad_dispute_chat_moderation_political_and_spam($t, $isPolitics);
}

/**
 * Политика и ссылки (без гео-триггеров — они в postcheck выше).
 */
function farhuaad_dispute_chat_moderation_political_and_spam(string $t, bool $isPolitics): ?string
{
  $lower = function_exists('mb_strtolower') ? mb_strtolower($t) : strtolower($t);
  $ascii = strtolower($t);
  $decoded = $t;
  for ($i = 0; $i < 6; $i++) {
    $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!is_string($next) || $next === $decoded) {
      break;
    }
    $decoded = $next;
  }
  $decodedLower = function_exists('mb_strtolower') ? mb_strtolower($decoded) : strtolower($decoded);
  $foldLower = farhuaad_dispute_chat_fold_latin_homoglyphs($lower);
  $foldDecodedLower = farhuaad_dispute_chat_fold_latin_homoglyphs($decodedLower);

  $politicalAlways = [
    '/навальн/ui',
    '/\bфбк\b/ui',
    '/умн(ое|ого|ым)\s+голосован/ui',
    '/свержени[ея]\s+власт/ui',
    '/антикремл/ui',
    '/путин\s*уб/ui',
    '/уб(ить|йте)\s+презид/ui',
    '/теракт.*росси/ui',
    '/экстремизм.*призыв/ui',
  ];
  foreach ($politicalAlways as $p) {
    foreach ([$lower, $decodedLower, $foldLower, $foldDecodedLower] as $hay) {
      if ($hay !== '' && preg_match($p, $hay) === 1) {
        return 'POLITICAL';
      }
    }
  }

  if ($isPolitics) {
    $politicalIfPolitics = [
      '/\bоппозици[яеи]\b/ui',
      '/\bпротест(ы|ный|ов|е)?\b/ui',
      '/\bмитинг(и|овый)?\b/ui',
      '/\bакци(я|и)\s+(протест|митинг)\b/ui',
      '/\bпризыв(а|ов)?\s+(к|на)?\s+(протест|митинг|выйти)\b/ui',
      '/\bпротив\s+власти\b/ui',
      '/\bантивласт/ui',
      '/\bразгон(а|е|я)\s+митинг[а-я]*\b/ui',
      '/\bсобраться\b.*\bмитинг[а-я]*\b/ui',
      '/\bвыйти\b.*\bпротест[а-я]*\b/ui',
    ];
    foreach ($politicalIfPolitics as $p) {
      foreach ([$lower, $decodedLower, $foldLower, $foldDecodedLower] as $hay) {
        if ($hay !== '' && preg_match($p, $hay) === 1) {
          return 'POLITICAL';
        }
      }
    }
  }

  $spamLinks = farhuaad_dispute_chat_spam_links_reject($t, $ascii, $decodedLower);
  if ($spamLinks !== null) {
    return $spamLinks;
  }

  return null;
}

/**
 * Полная проверка для очистки старых сообщений: мат/гео → отказ, политика/спам → отказ.
 */
function farhuaad_dispute_chat_moderation_reject(string $text, bool $isPolitics = false): ?string
{
  $early = farhuaad_dispute_chat_moderation_precheck($text);
  if ($early !== null) {
    return $early;
  }
  $t = str_replace("\0", '', $text);
  $t = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $t) ?? $t;
  $t = trim($t);
  $lower = function_exists('mb_strtolower') ? mb_strtolower($t) : strtolower($t);
  $ascii = strtolower($t);
  $decoded = $t;
  for ($i = 0; $i < 6; $i++) {
    $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!is_string($next) || $next === $decoded) {
      break;
    }
    $decoded = $next;
  }
  $decodedLower = function_exists('mb_strtolower') ? mb_strtolower($decoded) : strtolower($decoded);
  $foldLower = farhuaad_dispute_chat_fold_latin_homoglyphs($lower);
  $foldDecodedLower = farhuaad_dispute_chat_fold_latin_homoglyphs($decodedLower);
  $sets = farhuaad_dispute_chat_moderation_pattern_sets();
  foreach ($sets['redact'] as $p) {
    foreach ([$lower, $ascii, $decodedLower, $foldLower, $foldDecodedLower] as $hay) {
      if ($hay !== '' && preg_match($p, $hay) === 1) {
        return 'OBSCENE';
      }
    }
  }
  foreach ($sets['geo_reject'] as $p) {
    foreach ([$lower, $ascii, $decodedLower, $foldLower, $foldDecodedLower] as $hay) {
      if ($hay !== '' && preg_match($p, $hay) === 1) {
        return 'OBSCENE';
      }
    }
  }

  return farhuaad_dispute_chat_moderation_political_and_spam($t, $isPolitics);
}

function farhuaad_dispute_chat_dispute_is_open(PDO $pdo, int $disputeId): bool
{
  if ($disputeId <= 0) {
    return false;
  }
  $stmt = $pdo->prepare(
    "SELECT id FROM disputes WHERE id = :id AND status = 'active' LIMIT 1"
  );
  $stmt->execute([':id' => $disputeId]);
  return (bool)$stmt->fetchColumn();
}

/**
 * Удаление своего сообщения в чате спора (только автор, только в пределах TTL).
 *
 * @throws RuntimeException NOT_FOUND|INVALID_INPUT
 */
function farhuaad_dispute_chat_delete_own_message(PDO $pdo, int $disputeId, int $messageId, int $userId): void
{
  if ($disputeId <= 0 || $messageId <= 0 || $userId <= 0) {
    throw new RuntimeException('INVALID_INPUT');
  }
  farhuaad_dispute_chat_purge_expired($pdo);
  $stmt = $pdo->prepare(
    'DELETE FROM dispute_chat_messages
     WHERE id = :mid AND dispute_id = :did AND user_id = :uid
       AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)FARHUAAD_DISPUTE_CHAT_TTL_HOURS . ' HOUR)'
  );
  $stmt->execute([
    ':mid' => $messageId,
    ':did' => $disputeId,
    ':uid' => $userId,
  ]);
  if ($stmt->rowCount() < 1) {
    throw new RuntimeException('NOT_FOUND');
  }
}

/**
 * @return list<array{id:int, body:string, author:string, created_at:string, is_mine:bool}>
 */
function farhuaad_dispute_chat_fetch_messages(PDO $pdo, int $disputeId, int $limit = 120, int $viewerUserId = 0): array
{
  if ($disputeId <= 0) {
    return [];
  }
  $limit = max(1, min(200, $limit));

  farhuaad_dispute_chat_purge_expired($pdo);

  $stmt = $pdo->prepare(
    "SELECT m.id, m.body, m.created_at, u.id AS user_id, u.name, u.email,
            (SELECT w.address FROM wallets w WHERE w.user_id = u.id ORDER BY w.id ASC LIMIT 1) AS wallet_address
     FROM dispute_chat_messages m
     INNER JOIN users u ON u.id = m.user_id
     WHERE m.dispute_id = :dispute_id
       AND m.created_at >= DATE_SUB(NOW(), INTERVAL " . (int)FARHUAAD_DISPUTE_CHAT_TTL_HOURS . " HOUR)
     ORDER BY m.created_at ASC, m.id ASC
     LIMIT " . $limit
  );
  $stmt->execute([':dispute_id' => $disputeId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!is_array($rows)) {
    return [];
  }

  $redactTokens = farhuaad_dispute_chat_participant_tokens_for_redact($rows);

  $out = [];
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $author = farhuaad_dispute_chat_author_display($row);
    $bodyStored = (string)($row['body'] ?? '');
    $plain = farhuaad_dispute_chat_decrypt_body($bodyStored);
    $plain = farhuaad_dispute_chat_redact_obscene_tokens($plain);
    $plain = farhuaad_dispute_chat_redact_body_for_display($plain, $redactTokens);
    $rowUserId = (int)($row['user_id'] ?? 0);
    $out[] = [
      'id' => (int)($row['id'] ?? 0),
      'body' => $plain,
      'author' => $author,
      'created_at' => (string)($row['created_at'] ?? ''),
      'is_mine' => $viewerUserId > 0 && $rowUserId === $viewerUserId,
    ];
  }
  return $out;
}

/**
 * @throws RuntimeException
 */
function farhuaad_dispute_chat_post_message(PDO $pdo, int $disputeId, int $userId, string $rawBody): array
{
  if ($disputeId <= 0 || $userId <= 0) {
    throw new RuntimeException('INVALID_INPUT');
  }
  if (!farhuaad_dispute_chat_dispute_is_open($pdo, $disputeId)) {
    throw new RuntimeException('DISPUTE_CLOSED');
  }

  $body = trim(preg_replace('/\s+/u', ' ', $rawBody) ?? '');
  $isPolitics = false;
  $catStmt = $pdo->prepare('SELECT category FROM disputes WHERE id = :id LIMIT 1');
  $catStmt->execute([':id' => $disputeId]);
  $cat = (string)($catStmt->fetchColumn() ?? '');
  $catLower = function_exists('mb_strtolower') ? mb_strtolower($cat) : strtolower($cat);
  if (strpos($catLower, 'политик') !== false || strpos($catLower, 'politics') !== false) {
    $isPolitics = true;
  }

  $reject = farhuaad_dispute_chat_moderation_precheck($body);
  if ($reject !== null) {
    throw new RuntimeException('MODERATION_' . $reject);
  }
  $redacted = trim(farhuaad_dispute_chat_redact_obscene_tokens($body));
  if (preg_replace('/[\s*]+/u', '', $redacted) === '') {
    throw new RuntimeException('MODERATION_EMPTY');
  }
  $rejectAfter = farhuaad_dispute_chat_moderation_postcheck($redacted, $isPolitics);
  if ($rejectAfter !== null) {
    throw new RuntimeException('MODERATION_' . $rejectAfter);
  }
  $body = $redacted;

  farhuaad_dispute_chat_purge_expired($pdo);

  $coolStmt = $pdo->prepare(
    "SELECT COALESCE(TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()), 9999) AS s
     FROM dispute_chat_messages
     WHERE dispute_id = :d AND user_id = :u"
  );
  $coolStmt->execute([':d' => $disputeId, ':u' => $userId]);
  $since = (int)$coolStmt->fetchColumn();
  if ($since < FARHUAAD_DISPUTE_CHAT_COOLDOWN_SEC) {
    throw new RuntimeException('SPAM_COOLDOWN');
  }

  $hourStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM dispute_chat_messages
     WHERE dispute_id = :d AND user_id = :u AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
  );
  $hourStmt->execute([':d' => $disputeId, ':u' => $userId]);
  $hourly = (int)$hourStmt->fetchColumn();
  if ($hourly >= 40) {
    throw new RuntimeException('SPAM_HOURLY');
  }

  $plainBody = function_exists('mb_substr') ? mb_substr($body, 0, FARHUAAD_DISPUTE_CHAT_MAX_LEN) : substr($body, 0, FARHUAAD_DISPUTE_CHAT_MAX_LEN);
  $storedBody = farhuaad_dispute_chat_encrypt_body($plainBody);

  $insert = $pdo->prepare(
    "INSERT INTO dispute_chat_messages (dispute_id, user_id, body) VALUES (:d, :u, :b)"
  );
  $insert->execute([
    ':d' => $disputeId,
    ':u' => $userId,
    ':b' => $storedBody,
  ]);

  $id = (int)$pdo->lastInsertId();
  $uStmt = $pdo->prepare(
    'SELECT u.id AS user_id, u.name, u.email,
            (SELECT w.address FROM wallets w WHERE w.user_id = u.id ORDER BY w.id ASC LIMIT 1) AS wallet_address
     FROM users u WHERE u.id = :id LIMIT 1'
  );
  $uStmt->execute([':id' => $userId]);
  $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
  $author = is_array($uRow) ? farhuaad_dispute_chat_author_display($uRow) : farhuaad_dispute_chat_mask_author_label('user');

  $redactStmt = $pdo->prepare(
    'SELECT DISTINCT u.name, u.email,
            (SELECT w.address FROM wallets w WHERE w.user_id = u.id ORDER BY w.id ASC LIMIT 1) AS wallet_address
     FROM dispute_chat_messages m
     INNER JOIN users u ON u.id = m.user_id
     WHERE m.dispute_id = :d
       AND m.created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)FARHUAAD_DISPUTE_CHAT_TTL_HOURS . ' HOUR)'
  );
  $redactStmt->execute([':d' => $disputeId]);
  $redactRows = $redactStmt->fetchAll(PDO::FETCH_ASSOC);
  $redactTokens = is_array($redactRows) ? farhuaad_dispute_chat_participant_tokens_for_redact($redactRows) : [];
  $bodyForClient = farhuaad_dispute_chat_redact_body_for_display($plainBody, $redactTokens);

  return [
    'id' => $id,
    'body' => $bodyForClient,
    'author' => $author,
    'created_at' => date('Y-m-d H:i:s'),
    'is_mine' => true,
  ];
}
