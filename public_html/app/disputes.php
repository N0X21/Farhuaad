<?php
const FARHUAAD_SCHEMA_CHECK_INTERVAL = 86400;
/** Интервал между проходами авто-резолва через Claude (сек.). */
const FARHUAAD_NEWS_CHECK_INTERVAL = 1800;
/** Минимум секунд между попытками догнать дневную квоту споров (3 шт. по дате Москвы). */
const FARHUAAD_GENERATION_RETRY_INTERVAL = 900;
const FARHUAAD_METADATA_BACKFILL_INTERVAL = 1800;
const FARHUAAD_PATTERN_BACKFILL_INTERVAL = 300;
const FARHUAAD_DESCRIPTION_BACKFILL_INTERVAL = 1800;
/** Интервал дозаполнения англ. заголовков/описаний для старых споров (сек.). */
const FARHUAAD_EN_BACKFILL_INTERVAL = 1200;
const FARHUAAD_AI_IMAGE_RETRY_COUNT = 3;
const FARHUAAD_DISPUTE_FALLBACK_IMAGE = '/assets/pattern/1.svg';
/** Время по Москве, с которого начинается «новый день» для дневной квоты споров. */
const FARHUAAD_DISPUTES_DAY_START_HOUR_MOSCOW = 15;
const FARHUAAD_DISPUTES_DAY_START_MINUTE_MOSCOW = 35;
/** Сколько активных споров отдаём в ленту/API (защита от раздувания выборки). */
const FARHUAAD_ACTIVE_DISPUTES_FEED_CAP = 500;
/** Минимальное число активных споров, которое стараемся поддерживать в ленте. */
const FARHUAAD_MIN_ACTIVE_DISPUTES = 6;
/** Макс. длина описания спора (страница спора, TEXT в БД). Карточки в ленте обрезаются в CSS. */
const FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN = 8000;

function farhuaad_env_int(string $name, int $default, int $min, int $max): int
{
  $raw = trim((string)($_ENV[$name] ?? ''));
  if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
    return $default;
  }
  $value = (int)$raw;
  if ($value < $min || $value > $max) {
    return $default;
  }
  return $value;
}

function farhuaad_disputes_day_start_hour_moscow(): int
{
  return farhuaad_env_int(
    'DISPUTES_DAY_START_HOUR_MOSCOW',
    FARHUAAD_DISPUTES_DAY_START_HOUR_MOSCOW,
    0,
    23
  );
}

function farhuaad_disputes_day_start_minute_moscow(): int
{
  return farhuaad_env_int(
    'DISPUTES_DAY_START_MINUTE_MOSCOW',
    FARHUAAD_DISPUTES_DAY_START_MINUTE_MOSCOW,
    0,
    59
  );
}

function farhuaad_get_pattern_images(): array
{
  static $cached = null;
  if (is_array($cached)) {
    return $cached;
  }

  $dir = dirname(__DIR__) . '/assets/pattern';
  $files = glob($dir . '/*.{svg,png,jpg,jpeg,webp,gif}', GLOB_BRACE);
  if (!is_array($files)) {
    $cached = [];
    return $cached;
  }

  $images = [];
  foreach ($files as $file) {
    if (!is_string($file) || !is_file($file)) {
      continue;
    }
    $basename = basename($file);
    if ($basename === '') {
      continue;
    }
    $images[] = '/assets/pattern/' . $basename;
  }

  $cached = array_values(array_unique($images));
  return $cached;
}

function farhuaad_normalize_pattern_image_path(string $path): string
{
  $value = trim($path);
  if ($value === '') {
    return '';
  }
  if (str_starts_with($value, '/api/assets/pattern/')) {
    return '/assets/pattern/' . ltrim(substr($value, strlen('/api/assets/pattern/')), '/');
  }
  if (str_starts_with($value, 'api/assets/pattern/')) {
    return '/assets/pattern/' . ltrim(substr($value, strlen('api/assets/pattern/')), '/');
  }
  return $value;
}

function farhuaad_pick_random_pattern_image(): string
{
  $images = farhuaad_get_pattern_images();
  if (!$images) {
    return FARHUAAD_DISPUTE_FALLBACK_IMAGE;
  }
  $idx = random_int(0, count($images) - 1);
  return (string)$images[$idx];
}

function farhuaad_fallback_disputes(): array
{
  return [
    [
      'title' => 'Введут ли страны G7 новые санкции против ИИ-чипов до конца месяца?',
      'title_en' => 'Will G7 countries announce new sanctions on AI chips by the end of the month?',
      'image' => farhuaad_pick_random_pattern_image(),
      'short_description' => 'Официально объявят ли новые ограничения на поставки и экспорт ИИ-чипов до конца текущего месяца.',
      'short_description_en' => 'Whether new restrictions on AI chip supply and exports will be officially announced before the end of the current month.',
      'category' => 'Политика',
      'source_links' => [],
    ],
    [
      'title' => 'Снизит ли ФРС ключевую ставку на ближайшем заседании?',
      'title_en' => 'Will the Fed cut the federal funds rate at its next meeting?',
      'image' => farhuaad_pick_random_pattern_image(),
      'short_description' => 'Примет ли ФРС решение о снижении ставки на ближайшем плановом заседании по монетарной политике.',
      'short_description_en' => 'Whether the Federal Reserve will decide to lower the rate at its next scheduled monetary policy meeting.',
      'category' => 'Экономика',
      'source_links' => [],
    ],
    [
      'title' => 'Подпишут ли в этом месяце международное соглашение о прекращении огня в крупном конфликте?',
      'title_en' => 'Will an international ceasefire agreement in a major conflict be signed this month?',
      'image' => farhuaad_pick_random_pattern_image(),
      'short_description' => 'Будет ли подписано официальное международное соглашение о прекращении огня до конца текущего месяца.',
      'short_description_en' => 'Whether a formal international ceasefire agreement will be signed before the end of the current month.',
      'category' => 'Политика',
      'source_links' => [],
    ],
    [
      'title' => 'Утвердит ли SEC новый спотовый крипто-ETF до конца текущего квартала?',
      'title_en' => 'Will the SEC approve a new spot crypto ETF by the end of the current quarter?',
      'image' => farhuaad_pick_random_pattern_image(),
      'short_description' => 'Опубликует ли SEC официальное решение об одобрении нового спотового ETF на криптоактив до завершения текущего квартала.',
      'short_description_en' => 'Whether the SEC will publish an official decision approving a new spot crypto ETF before the current quarter ends.',
      'category' => 'Крипто',
      'source_links' => [],
    ],
    [
      'title' => 'Покажет ли инфляция в США снижение два месяца подряд в текущем квартале?',
      'title_en' => 'Will US inflation fall for two consecutive months in the current quarter?',
      'image' => farhuaad_pick_random_pattern_image(),
      'short_description' => 'Будут ли два последовательных ежемесячных отчета CPI в США демонстрировать снижение годового темпа инфляции в рамках текущего квартала.',
      'short_description_en' => 'Whether two back-to-back monthly US CPI reports will show a lower year-over-year inflation rate within the current quarter.',
      'category' => 'Экономика',
      'source_links' => [],
    ],
    [
      'title' => 'Запустит ли одна из стран G20 национальный пилот цифровой валюты до конца года?',
      'title_en' => 'Will a G20 country launch a national digital currency pilot by year-end?',
      'image' => farhuaad_pick_random_pattern_image(),
      'short_description' => 'Объявит ли государственный регулятор или центральный банк страны G20 о фактическом запуске национального пилота CBDC до конца года.',
      'short_description_en' => 'Whether a G20 central bank or regulator will announce the actual launch of a national CBDC pilot before year-end.',
      'category' => 'Технологии',
      'source_links' => [],
    ],
    [
      'title' => 'Выйдет ли новый международный пакет торговых ограничений против высокотеха до конца месяца?',
      'title_en' => 'Will a new international trade-restriction package targeting high tech be adopted by month-end?',
      'image' => farhuaad_pick_random_pattern_image(),
      'short_description' => 'Будет ли до конца месяца официально принят новый пакет торговых ограничений, затрагивающих поставки высокотехнологичной продукции.',
      'short_description_en' => 'Whether a new package of trade restrictions affecting high-tech goods will be formally adopted before the end of the month.',
      'category' => 'Политика',
      'source_links' => [],
    ],
    [
      'title' => 'Примет ли крупнейший мировой автопроизводитель решение о повышении годового прогноза продаж электромобилей?',
      'title_en' => 'Will a top global automaker raise its annual EV sales guidance?',
      'image' => farhuaad_pick_random_pattern_image(),
      'short_description' => 'Опубликует ли один из крупнейших мировых автопроизводителей официальный пересмотр годового guidance по продажам EV в сторону повышения.',
      'short_description_en' => 'Whether one of the world’s largest automakers will officially revise full-year EV sales guidance upward.',
      'category' => 'Экономика',
      'source_links' => [],
    ],
  ];
}

/**
 * Резервные рынки для API с учётом языка интерфейса (cookie).
 *
 * @return list<array<string, mixed>>
 */
function farhuaad_fallback_disputes_api_slice(int $limit): array
{
  if ($limit <= 0) {
    return [];
  }
  $raw = array_slice(farhuaad_fallback_disputes(), 0, $limit);
  $out = [];
  foreach ($raw as $item) {
    if (!is_array($item)) {
      continue;
    }
    $out[] = farhuaad_dispute_localize_public_fields($item);
  }
  return $out;
}

function farhuaad_get_claude_key(): string
{
  $candidates = [
    $_ENV['CLAUDE_API_KEY'] ?? '',
    $_ENV['ANTHROPIC_API_KEY'] ?? '',
    $_ENV['claude_API'] ?? '',
  ];
  foreach ($candidates as $key) {
    $trimmed = trim((string)$key);
    if ($trimmed !== '') {
      return $trimmed;
    }
  }
  return '';
}

function farhuaad_get_openai_key(): string
{
  $candidates = [
    $_ENV['OPENAI_API_KEY'] ?? '',
    $_ENV['openai_API'] ?? '',
  ];
  foreach ($candidates as $key) {
    $trimmed = trim((string)$key);
    if ($trimmed !== '') {
      return $trimmed;
    }
  }
  return '';
}

function farhuaad_http_post_json(string $url, array $payload, array $headers = [], int $timeoutSeconds = 15): ?array
{
  $baseHeaders = [
    'Content-Type: application/json',
    'Accept: application/json',
  ];
  $finalHeaders = array_merge($baseHeaders, $headers);

  $ch = curl_init($url);
  if ($ch === false) {
    return null;
  }

  $timeoutSeconds = max(5, min(120, $timeoutSeconds));

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $finalHeaders,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => $timeoutSeconds,
  ]);

  $raw = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if (!is_string($raw) || $status < 200 || $status >= 300) {
    return null;
  }

  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : null;
}

/**
 * Текст из ответа Anthropic messages API (или пустая строка).
 */
function farhuaad_claude_response_text(?array $response): string
{
  if (!is_array($response)) {
    return '';
  }
  $content = '';
  $parts = $response['content'] ?? null;
  if (!is_array($parts)) {
    return '';
  }
  foreach ($parts as $part) {
    if (is_array($part) && (string)($part['type'] ?? '') === 'text') {
      $content .= (string)($part['text'] ?? '');
    }
  }
  return $content;
}

/**
 * Резервный перевод RU→EN без ключа (MyMemory). Ограничен квотой; для продакшена лучше Claude.
 */
function farhuaad_fallback_translate_ru_to_en(string $text): string
{
  $text = trim($text);
  if ($text === '') {
    return '';
  }
  if (!preg_match('/\p{Cyrillic}/u', $text)) {
    return $text;
  }
  if (function_exists('mb_strlen') && mb_strlen($text) > 400) {
    $text = mb_substr($text, 0, 400);
  } elseif (strlen($text) > 400) {
    $text = substr($text, 0, 400);
  }
  $url = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($text) . '&langpair=ru|en';
  $res = farhuaad_http_get_json($url);
  if (!is_array($res)) {
    return '';
  }
  $out = trim((string)($res['responseData']['translatedText'] ?? ''));
  if ($out === '' || preg_match('/^MYMEMORY\s+WARNING/i', $out) === 1) {
    return '';
  }
  return $out;
}

/** Разрешить сохранить EN-перевод (исходный RU уже проходил модерацию при создании). */
function farhuaad_dispute_en_translation_save_ok(string $titleEn, string $descEn): bool
{
  if (trim($titleEn) === '') {
    return true;
  }
  return !farhuaad_dispute_topic_policy_blocked($titleEn, $descEn);
}

function farhuaad_http_get_json(string $url, array $headers = []): ?array
{
  $baseHeaders = ['Accept: application/json'];
  $finalHeaders = array_merge($baseHeaders, $headers);

  $ch = curl_init($url);
  if ($ch === false) {
    return null;
  }

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $finalHeaders,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 8,
  ]);

  $raw = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if (!is_string($raw) || $status < 200 || $status >= 300) {
    return null;
  }

  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : null;
}

function farhuaad_http_get_text(string $url, array $headers = []): ?string
{
  $baseHeaders = ['Accept: application/rss+xml, application/xml, text/xml, */*'];
  $finalHeaders = array_merge($baseHeaders, $headers);

  $ch = curl_init($url);
  if ($ch === false) {
    return null;
  }

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $finalHeaders,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 5,
  ]);

  $raw = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
    return null;
  }
  return $raw;
}

function farhuaad_runtime_cache_path(): string
{
  return dirname(__DIR__) . '/data/disputes_runtime_cache.json';
}

function farhuaad_runtime_cache_read(): array
{
  $path = farhuaad_runtime_cache_path();
  if (!is_file($path)) {
    return [];
  }
  $raw = @file_get_contents($path);
  if (!is_string($raw) || trim($raw) === '') {
    return [];
  }
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

function farhuaad_runtime_cache_write(array $cache): void
{
  $path = farhuaad_runtime_cache_path();
  $dir = dirname($path);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  @file_put_contents($path, json_encode($cache, JSON_UNESCAPED_UNICODE));
}

function farhuaad_should_run_job(string $key, int $intervalSeconds): bool
{
  $cache = farhuaad_runtime_cache_read();
  $last = (int)($cache['jobs'][$key]['last_run_ts'] ?? 0);
  return (time() - $last) >= $intervalSeconds;
}

function farhuaad_mark_job_run(string $key): void
{
  $cache = farhuaad_runtime_cache_read();
  if (!isset($cache['jobs']) || !is_array($cache['jobs'])) {
    $cache['jobs'] = [];
  }
  $cache['jobs'][$key] = ['last_run_ts' => time()];
  farhuaad_runtime_cache_write($cache);
}

function farhuaad_is_dispute_auto_generation_enabled(): bool
{
  $cache = farhuaad_runtime_cache_read();
  return !isset($cache['flags']['dispute_auto_generation_enabled'])
    || (bool)$cache['flags']['dispute_auto_generation_enabled'] === true;
}

function farhuaad_set_dispute_auto_generation_enabled(bool $enabled): void
{
  $cache = farhuaad_runtime_cache_read();
  if (!isset($cache['flags']) || !is_array($cache['flags'])) {
    $cache['flags'] = [];
  }
  $cache['flags']['dispute_auto_generation_enabled'] = $enabled;
  $cache['flags']['dispute_auto_generation_updated_at'] = time();
  farhuaad_runtime_cache_write($cache);
}

function farhuaad_disputes_moscow_timezone(): DateTimeZone
{
  static $tz = null;
  if ($tz instanceof DateTimeZone) {
    return $tz;
  }
  $tz = new DateTimeZone('Europe/Moscow');
  return $tz;
}

/**
 * Дата «дня споров» для source_date и квоты 3 шт. — Europe/Moscow, смена в 15:35 МСК.
 * До этого времени считается ещё предыдущий календарный день.
 */
function farhuaad_disputes_moscow_today(): string
{
  $now = new DateTimeImmutable('now', farhuaad_disputes_moscow_timezone());
  $startHour = farhuaad_disputes_day_start_hour_moscow();
  $startMinute = farhuaad_disputes_day_start_minute_moscow();
  $currentHour = (int)$now->format('G');
  $currentMinute = (int)$now->format('i');
  $isBeforeDayStart = $currentHour < $startHour
    || ($currentHour === $startHour && $currentMinute < $startMinute);
  if ($isBeforeDayStart) {
    $now = $now->modify('-1 day');
  }
  return $now->format('Y-m-d');
}

function farhuaad_disputes_moscow_now(): DateTimeImmutable
{
  return new DateTimeImmutable('now', farhuaad_disputes_moscow_timezone());
}

function farhuaad_fetch_recent_news_context(int $limit = 12): array
{
  if ($limit <= 0) {
    return [];
  }

  $rssUrl = 'https://news.google.com/rss?hl=ru&gl=RU&ceid=RU:ru';
  $xmlRaw = farhuaad_http_get_text($rssUrl);
  if (!is_string($xmlRaw) || trim($xmlRaw) === '') {
    return [];
  }

  $xml = @simplexml_load_string($xmlRaw);
  if (!$xml || !isset($xml->channel->item)) {
    return [];
  }

  $headlines = [];
  foreach ($xml->channel->item as $item) {
    $headline = trim((string)($item->title ?? ''));
    if ($headline === '') {
      continue;
    }
    $headlines[] = $headline;
    if (count($headlines) >= $limit) {
      break;
    }
  }
  return $headlines;
}

function farhuaad_normalize_dispute_title_key(string $title): string
{
  $value = trim($title);
  if ($value === '') {
    return '';
  }
  $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
  $value = preg_replace('/\s+/u', ' ', $value) ?? '';
  $value = preg_replace('/[^\p{L}\p{N}\s]/u', '', $value) ?? '';
  return trim($value);
}

function farhuaad_generate_titles_from_news_context(int $limit): array
{
  if ($limit <= 0) {
    return [];
  }
  $headlines = farhuaad_fetch_recent_news_context(max(20, $limit * 3));
  if (!$headlines) {
    return [];
  }

  $deadlineLabel = farhuaad_disputes_moscow_now()->modify('+30 days')->format('d.m.Y');
  $result = [];
  $seen = [];
  foreach ($headlines as $headline) {
    $h = trim((string)$headline);
    if ($h === '') {
      continue;
    }
    // Remove trailing source chunk (often " ... - Издание").
    $h = preg_replace('/\s+[-|]\s+[^-|]{1,80}$/u', '', $h) ?? $h;
    $h = trim(preg_replace('/\s+/', ' ', $h) ?? $h);
    if ($h === '') {
      continue;
    }
    if (function_exists('mb_substr')) {
      $h = mb_substr($h, 0, 88);
    } else {
      $h = substr($h, 0, 88);
    }
    $title = 'Подтвердится ли "' . $h . '" до ' . $deadlineLabel . '?';
    if (function_exists('mb_substr')) {
      $title = mb_substr($title, 0, 140);
    } else {
      $title = substr($title, 0, 140);
    }
    if (strpos($title, '?') === false) {
      $title .= '?';
    }
    if (farhuaad_dispute_topic_policy_blocked($title, '')) {
      continue;
    }
    $key = farhuaad_normalize_dispute_title_key($title);
    if ($key === '' || isset($seen[$key])) {
      continue;
    }
    $seen[$key] = true;
    $result[] = ['title' => $title];
    if (count($result) >= $limit) {
      break;
    }
  }
  return $result;
}

function farhuaad_extract_json_array(string $content): ?array
{
  $content = trim($content);
  if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```/iu', $content, $fence) === 1) {
    $content = trim((string)($fence[1] ?? ''));
  }

  $decoded = json_decode($content, true);
  if (is_array($decoded)) {
    return $decoded;
  }

  if (preg_match('/\[[\s\S]*\]/', $content, $m) !== 1) {
    return null;
  }

  $decoded = json_decode($m[0], true);
  return is_array($decoded) ? $decoded : null;
}

function farhuaad_extract_json_object(string $content): ?array
{
  $trim = trim($content);
  $decoded = json_decode($trim, true);
  if (is_array($decoded) && isset($decoded['outcome'])) {
    return $decoded;
  }
  if (preg_match('/\{[\s\S]*"outcome"[\s\S]*\}/', $content, $m) === 1) {
    $decoded = json_decode($m[0], true);
    if (is_array($decoded) && isset($decoded['outcome'])) {
      return $decoded;
    }
  }
  return null;
}

function farhuaad_claude_resolve_min_confidence(): float
{
  $raw = trim((string)($_ENV['CLAUDE_RESOLVE_MIN_CONFIDENCE'] ?? ''));
  if ($raw === '' || !is_numeric($raw)) {
    return 0.78;
  }
  $v = (float)$raw;
  if ($v < 0.5 || $v > 1.0) {
    return 0.78;
  }
  return $v;
}

function farhuaad_claude_resolve_batch_size(): int
{
  return farhuaad_env_int('CLAUDE_RESOLVE_BATCH', 4, 1, 12);
}

/**
 * Заголовки из Google News RSS по теме спора (контекст для Claude).
 *
 * @return list<string>
 */
function farhuaad_fetch_dispute_news_headlines(string $title, int $limit = 10): array
{
  $query = trim($title);
  if ($query === '' || $limit <= 0) {
    return [];
  }

  $rssUrl = 'https://news.google.com/rss/search?q=' . rawurlencode($query . ' новости') . '&hl=ru&gl=RU&ceid=RU:ru';
  $xmlRaw = farhuaad_http_get_text($rssUrl);
  if (!is_string($xmlRaw) || trim($xmlRaw) === '') {
    return [];
  }

  $xml = @simplexml_load_string($xmlRaw);
  if (!$xml || !isset($xml->channel->item)) {
    return [];
  }

  $out = [];
  foreach ($xml->channel->item as $item) {
    $headline = trim((string)($item->title ?? ''));
    if ($headline === '') {
      continue;
    }
    $out[] = $headline;
    if (count($out) >= $limit) {
      break;
    }
  }
  return $out;
}

/**
 * @return array{outcome:string,confidence:float,reason:string}|null
 */
function farhuaad_claude_classify_dispute_outcome(string $title, string $shortDescription, array $headlines): ?array
{
  $apiKey = farhuaad_get_claude_key();
  if ($apiKey === '') {
    return null;
  }

  $title = trim($title);
  $short = trim($shortDescription);
  if (function_exists('mb_substr')) {
    $short = $short !== '' ? mb_substr($short, 0, 600) : '';
  } else {
    $short = $short !== '' ? substr($short, 0, 600) : '';
  }

  $newsBlock = '- (нет свежих заголовков в ленте)';
  if ($headlines) {
    $lines = [];
    foreach (array_slice($headlines, 0, 12) as $h) {
      $h = trim((string)$h);
      if ($h !== '') {
        $lines[] = '- ' . $h;
      }
    }
    if ($lines) {
      $newsBlock = implode("\n", $lines);
    }
  }

  $prompt = "Ты аналитик рынков прогнозов (да/нет). По формулировке рынка и заголовкам новостей определи исход.\n\n" .
    "Вопрос рынка: {$title}\n" .
    "Уточнение условий исхода: " . ($short !== '' ? $short : '(не задано)') . "\n\n" .
    "Заголовки из ленты новостей:\n{$newsBlock}\n\n" .
    "Верни СТРОГО один JSON-объект без markdown и без текста вокруг:\n" .
    '{"outcome":"yes"|"no"|"unknown","confidence":число от 0 до 1,"reason":"кратко по-русски до 200 символов"}' . "\n\n" .
    "Правила:\n" .
    "- yes: событие уже свершилось или исход однозначно «да» по формулировке вопроса.\n" .
    "- no: однозначно «нет», отмена, исход не в пользу «да».\n" .
    "- unknown: мало данных, противоречивые новости, преждевременно, неоднозначная формулировка.\n" .
    "- confidence: твоя уверенность в выбранном outcome (не путай с вероятностью «да»).";

  $payload = [
    'model' => 'claude-3-5-sonnet-20241022',
    'temperature' => 0.1,
    'max_tokens' => 400,
    'messages' => [
      ['role' => 'user', 'content' => $prompt],
    ],
  ];

  $ch = curl_init('https://api.anthropic.com/v1/messages');
  if ($ch === false) {
    return null;
  }
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Accept: application/json',
      'x-api-key: ' . $apiKey,
      'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 55,
  ]);
  $raw = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if (!is_string($raw) || $status < 200 || $status >= 300) {
    return null;
  }

  $response = json_decode($raw, true);
  if (!is_array($response)) {
    return null;
  }
  $content = '';
  $parts = $response['content'] ?? null;
  if (is_array($parts)) {
    foreach ($parts as $part) {
      if (is_array($part) && (string)($part['type'] ?? '') === 'text') {
        $content .= (string)($part['text'] ?? '');
      }
    }
  }
  if (trim($content) === '') {
    return null;
  }

  $obj = farhuaad_extract_json_object($content);
  if (!is_array($obj)) {
    return null;
  }
  $outcome = strtolower(trim((string)($obj['outcome'] ?? '')));
  if ($outcome !== 'yes' && $outcome !== 'no' && $outcome !== 'unknown') {
    return null;
  }
  $confidence = $obj['confidence'] ?? 0;
  if (is_string($confidence)) {
    $confidence = (float)str_replace(',', '.', $confidence);
  } elseif (!is_numeric($confidence)) {
    $confidence = 0.0;
  } else {
    $confidence = (float)$confidence;
  }
  $reason = trim((string)($obj['reason'] ?? ''));
  if (function_exists('mb_substr')) {
    $reason = mb_substr($reason, 0, 220);
  } else {
    $reason = substr($reason, 0, 220);
  }
  if ($reason === '') {
    $reason = 'Без пояснения';
  }

  return [
    'outcome' => $outcome,
    'confidence' => $confidence,
    'reason' => $reason,
  ];
}

function farhuaad_resolve_disputes_with_claude(PDO $pdo): void
{
  $apiKey = farhuaad_get_claude_key();
  if ($apiKey === '') {
    return;
  }

  $batch = farhuaad_claude_resolve_batch_size();
  $stmt = $pdo->query(
    'SELECT id, title, COALESCE(short_description, \'\') AS short_description
     FROM disputes
     WHERE status = \'active\'
     ORDER BY created_at ASC
     LIMIT ' . (int)$batch
  );
  $active = $stmt ? $stmt->fetchAll() : [];
  if (!$active) {
    return;
  }

  $minConf = farhuaad_claude_resolve_min_confidence();
  $update = $pdo->prepare(
    'UPDATE disputes
     SET status = \'resolved\',
         winning_side = :winning_side,
         resolution_source = :resolution_source,
         resolution_title = :resolution_title,
         resolution_meta = :resolution_meta,
         resolved_at = NOW()
     WHERE id = :id AND status = \'active\''
  );

  foreach ($active as $row) {
    $id = (int)($row['id'] ?? 0);
    $title = trim((string)($row['title'] ?? ''));
    $short = trim((string)($row['short_description'] ?? ''));
    if ($id <= 0 || $title === '') {
      continue;
    }

    $headlines = farhuaad_fetch_dispute_news_headlines($title, 12);
    $ai = farhuaad_claude_classify_dispute_outcome($title, $short, $headlines);
    if (!is_array($ai)) {
      continue;
    }
    if (($ai['outcome'] ?? '') === 'unknown') {
      continue;
    }
    if (($ai['confidence'] ?? 0) < $minConf) {
      continue;
    }

    $side = (string)$ai['outcome'];
    $reason = (string)$ai['reason'];
    $resolutionTitle = $reason;
    if (function_exists('mb_substr')) {
      $resolutionTitle = mb_substr($resolutionTitle, 0, 255);
    } else {
      $resolutionTitle = substr($resolutionTitle, 0, 255);
    }

    $meta = [
      'method' => 'claude',
      'confidence' => round((float)$ai['confidence'], 4),
      'reason' => $reason,
      'headlines_used' => count($headlines),
      'model' => 'claude-3-5-sonnet-20241022',
      'resolved_at_iso' => date('c'),
    ];
    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
    if ($metaJson === false) {
      continue;
    }

    $update->execute([
      ':winning_side' => $side,
      ':resolution_source' => 'claude',
      ':resolution_title' => $resolutionTitle,
      ':resolution_meta' => $metaJson,
      ':id' => $id,
    ]);
  }
}

/**
 * Ручное закрытие спора (админка). Только status = active.
 *
 * @throws RuntimeException NOT_FOUND | NO_CHANGE
 */
function farhuaad_admin_resolve_dispute(PDO $pdo, int $disputeId, string $side, string $note): void
{
  $side = strtolower(trim($side));
  if ($side !== 'yes' && $side !== 'no') {
    throw new RuntimeException('INVALID_SIDE');
  }
  $note = trim($note);
  if (function_exists('mb_substr')) {
    $note = mb_substr($note, 0, 500);
  } else {
    $note = substr($note, 0, 500);
  }

  $check = $pdo->prepare('SELECT id FROM disputes WHERE id = ? AND status = \'active\' LIMIT 1');
  $check->execute([$disputeId]);
  if (!$check->fetch()) {
    throw new RuntimeException('NOT_FOUND');
  }

  $title = $note !== '' ? $note : 'Исход зафиксирован вручную (админка)';
  if (function_exists('mb_substr')) {
    $title = mb_substr($title, 0, 255);
  } else {
    $title = substr($title, 0, 255);
  }

  $meta = json_encode([
    'method' => 'admin',
    'note' => $note,
    'resolved_at_iso' => date('c'),
  ], JSON_UNESCAPED_UNICODE);
  if ($meta === false) {
    throw new RuntimeException('META_FAILED');
  }

  $stmt = $pdo->prepare(
    'UPDATE disputes SET
      status = \'resolved\',
      winning_side = ?,
      resolution_source = \'admin\',
      resolution_title = ?,
      resolution_meta = ?,
      resolved_at = NOW()
     WHERE id = ? AND status = \'active\''
  );
  $stmt->execute([$side, $title, $meta, $disputeId]);
  if ($stmt->rowCount() !== 1) {
    throw new RuntimeException('NO_CHANGE');
  }
}

function farhuaad_dispute_admin_password_configured(): bool
{
  return trim((string)($_ENV['DISPUTE_ADMIN_PASSWORD'] ?? '')) !== '';
}

function farhuaad_dispute_admin_login_expected(): string
{
  $login = trim((string)($_ENV['DISPUTE_ADMIN_LOGIN'] ?? ''));
  return $login !== '' ? $login : 'admin';
}

function farhuaad_dispute_admin_login_configured(): bool
{
  return farhuaad_dispute_admin_login_expected() !== '';
}

function farhuaad_dispute_admin_check_login(string $login): bool
{
  $expected = farhuaad_dispute_admin_login_expected();
  if ($expected === '') {
    return false;
  }
  return hash_equals(
    function_exists('mb_strtolower') ? mb_strtolower($expected) : strtolower($expected),
    function_exists('mb_strtolower') ? mb_strtolower(trim($login)) : strtolower(trim($login))
  );
}

function farhuaad_dispute_admin_check_password(string $password): bool
{
  $expected = (string)($_ENV['DISPUTE_ADMIN_PASSWORD'] ?? '');
  if (trim($expected) === '') {
    return false;
  }
  return hash_equals($expected, $password);
}

function farhuaad_dispute_admin_session_ok(): bool
{
  return !empty($_SESSION['farhuaad_dispute_admin']);
}

function farhuaad_dispute_admin_set_session(bool $ok): void
{
  if ($ok) {
    $_SESSION['farhuaad_dispute_admin'] = true;
  } else {
    unset($_SESSION['farhuaad_dispute_admin']);
  }
}

function farhuaad_generate_titles_with_claude(int $count): array
{
  $apiKey = farhuaad_get_claude_key();
  if ($apiKey === '' || $count <= 0) {
    return [];
  }

  $news = farhuaad_fetch_recent_news_context();
  $newsLines = $news ? implode("\n- ", $news) : 'нет доступного новостного контекста';

  $prompt = "Ниже список свежих заголовков мировых новостей.\n" .
    "- {$newsLines}\n\n" .
    "Сгенерируй {$count} кратких и реалистичных вопросов для рынка прогнозов на русском языке по этим новостям.\n" .
    "Требования:\n" .
    "1) Каждый вопрос строго да/нет и заканчивается '?'\n" .
    "2) В каждом вопросе должен быть проверяемый исход по новости или событию и явный срок проверки (дата/месяц/квартал/до конца года)\n" .
    "3) Исход должен быть определяем по публичному факту (релиз регулятора, отчет компании, официальный анонс, итог матча и т.д.)\n" .
    "4) Длина каждого вопроса не более 140 символов\n" .
    "5) Не дублируй темы между вопросами\n" .
    "6) Не предлагай вопросы про СВО, спецоперацию, конфликт России и Украины, ДНР/ЛНР и смежную военно-политическую повестку\n" .
    "Верни строго JSON-массив объектов формата [{\"title\":\"...\"}] без markdown и без пояснений.";

  $response = farhuaad_http_post_json(
    'https://api.anthropic.com/v1/messages',
    [
      'model' => 'claude-3-5-sonnet-20241022',
      'temperature' => 0.4,
      'max_tokens' => 700,
      'messages' => [
        ['role' => 'user', 'content' => $prompt],
      ],
    ],
    [
      'x-api-key: ' . $apiKey,
      'anthropic-version: 2023-06-01',
    ],
    45
  );

  $content = farhuaad_claude_response_text($response);
  if ($content === '') {
    return [];
  }

  $decoded = farhuaad_extract_json_array($content);
  if (!is_array($decoded)) {
    return [];
  }

  $result = [];
  foreach ($decoded as $item) {
    if (!is_array($item)) {
      continue;
    }
    $title = trim((string)($item['title'] ?? ''));
    if (function_exists('mb_substr')) {
      $title = mb_substr($title, 0, 140);
    } else {
      $title = substr($title, 0, 140);
    }
    if ($title === '' || strpos($title, '?') === false) {
      continue;
    }
    if (farhuaad_dispute_topic_policy_blocked($title, '')) {
      continue;
    }
    $result[] = ['title' => $title];
    if (count($result) >= $count) {
      break;
    }
  }
  return $result;
}

function farhuaad_generate_image_queries_with_claude(array $titles): array
{
  $apiKey = farhuaad_get_claude_key();
  if ($apiKey === '' || !$titles) {
    return [];
  }

  $cleanTitles = [];
  foreach ($titles as $title) {
    $value = trim((string)$title);
    if ($value !== '') {
      $cleanTitles[] = $value;
    }
  }
  if (!$cleanTitles) {
    return [];
  }

  $titlesJson = json_encode(array_values($cleanTitles), JSON_UNESCAPED_UNICODE);
  $prompt = "Для каждого заголовка спора создай короткий поисковый запрос для поиска нейтральной иллюстрации в фотостоках/commons.\n" .
    "Верни строго JSON-массив объектов формата [{\"title\":\"...\",\"image_query\":\"...\"}] без markdown.\n" .
    "Правила: image_query на английском, 2-6 слов, без брендов и без вопросительного знака.\n" .
    "Заголовки:\n{$titlesJson}";

  $response = farhuaad_http_post_json(
    'https://api.anthropic.com/v1/messages',
    [
      'model' => 'claude-3-5-sonnet-20241022',
      'temperature' => 0.2,
      'max_tokens' => 700,
      'messages' => [
        ['role' => 'user', 'content' => $prompt],
      ],
    ],
    [
      'x-api-key: ' . $apiKey,
      'anthropic-version: 2023-06-01',
    ],
    45
  );

  $content = farhuaad_claude_response_text($response);
  if ($content === '') {
    return [];
  }

  $decoded = farhuaad_extract_json_array($content);
  if (!is_array($decoded)) {
    return [];
  }

  $map = [];
  foreach ($decoded as $item) {
    if (!is_array($item)) {
      continue;
    }
    $title = trim((string)($item['title'] ?? ''));
    $query = trim((string)($item['image_query'] ?? ''));
    if ($title === '' || $query === '') {
      continue;
    }
    $map[$title] = $query;
  }
  return $map;
}

function farhuaad_generate_dispute_meta_with_claude(array $titles): array
{
  $apiKey = farhuaad_get_claude_key();
  if ($apiKey === '' || !$titles) {
    return [];
  }

  $cleanTitles = [];
  foreach ($titles as $title) {
    $value = trim((string)$title);
    if ($value !== '') {
      $cleanTitles[] = $value;
    }
  }
  if (!$cleanTitles) {
    return [];
  }

  $titlesJson = json_encode(array_values($cleanTitles), JSON_UNESCAPED_UNICODE);
  $prompt = "Верни JSON-массив объектов для рынков прогнозов без markdown.\n" .
    "Формат каждого объекта: {\"title\":\"...\",\"short_description\":\"...\",\"title_en\":\"...\",\"short_description_en\":\"...\",\"category\":\"...\",\"source_links\":[\"https://...\"],\"image_query\":\"...\"}\n" .
    "Требования:\n" .
    "- short_description: полные правила исхода на рус.; разбивай на абзацы пустой строкой между смысловыми блоками; целевой объём — развёрнутый понятный текст (ориентир 1200–4000 символов, максимум " . FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN . ")\n" .
    "- title_en: точный перевод того же да/нет-вопроса на английский, до ~140 символов, заканчивается '?'\n" .
    "- short_description_en: тот же смысл и структура абзацев что short_description; нейтральный англ.; тот же ориентир по объёму\n" .
    "- В short_description явно и подробно: что считается «Да», что «Нет», дедлайн, какие источники/документы принимаются, спорные и пограничные случаи\n" .
    "- Не используй общие фразы вроде 'актуальная новость', 'подтвержденные публикации', 'бинарный рынок прогноза' без конкретики\n" .
    "- Пиши нейтрально и фактически, без оценочных формулировок\n" .
    "- category: одно из [Крипто, Экономика, Политика, Технологии, Спорт]\n" .
    "- source_links: 1-3 реальных новостных ссылки по теме (https)\n" .
    "- image_query: англ., 2-6 слов для фотопоиска\n" .
    "Заголовки:\n{$titlesJson}";

  $response = farhuaad_http_post_json(
    'https://api.anthropic.com/v1/messages',
    [
      'model' => 'claude-3-5-sonnet-20241022',
      'temperature' => 0.2,
      'max_tokens' => 8192,
      'messages' => [
        ['role' => 'user', 'content' => $prompt],
      ],
    ],
    [
      'x-api-key: ' . $apiKey,
      'anthropic-version: 2023-06-01',
    ],
    90
  );

  $content = farhuaad_claude_response_text($response);
  if ($content === '') {
    return [];
  }

  $decoded = farhuaad_extract_json_array($content);
  if (!is_array($decoded)) {
    return [];
  }

  $allowedCategories = ['Крипто', 'Экономика', 'Политика', 'Технологии', 'Спорт'];
  $result = [];
  foreach ($decoded as $item) {
    if (!is_array($item)) {
      continue;
    }
    $title = trim((string)($item['title'] ?? ''));
    if ($title === '') {
      continue;
    }
    $description = trim((string)($item['short_description'] ?? ''));
    if ($description === '' || farhuaad_is_watery_description($description)) {
      $fallbackMeta = farhuaad_build_basic_dispute_meta($title);
      $description = trim((string)($fallbackMeta['short_description'] ?? ''));
    }
    if (function_exists('mb_substr')) {
      $description = mb_substr($description, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    } else {
      $description = substr($description, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    }
    $category = trim((string)($item['category'] ?? ''));
    if (!in_array($category, $allowedCategories, true)) {
      $category = 'Политика';
    }
    $imageQuery = trim((string)($item['image_query'] ?? ''));
    $titleEn = trim((string)($item['title_en'] ?? ''));
    $descriptionEn = trim((string)($item['short_description_en'] ?? ''));
    if (function_exists('mb_substr')) {
      $titleEn = mb_substr($titleEn, 0, 255);
      $descriptionEn = mb_substr($descriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    } else {
      $titleEn = substr($titleEn, 0, 255);
      $descriptionEn = substr($descriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    }
    $links = [];
    $rawLinks = $item['source_links'] ?? [];
    if (is_array($rawLinks)) {
      foreach ($rawLinks as $url) {
        $value = trim((string)$url);
        if ($value !== '' && (str_starts_with($value, 'https://') || str_starts_with($value, 'http://'))) {
          $links[] = $value;
        }
        if (count($links) >= 3) {
          break;
        }
      }
    }

    $links = farhuaad_sanitize_source_links_storage($links, 3);

    $result[$title] = [
      'short_description' => $description,
      'title_en' => $titleEn,
      'short_description_en' => $descriptionEn,
      'category' => $category,
      'source_links' => $links,
      'image_query' => $imageQuery,
    ];
  }

  return $result;
}

function farhuaad_build_basic_dispute_meta(string $title): array
{
  $cleanTitle = trim($title);
  $lower = function_exists('mb_strtolower') ? mb_strtolower($cleanTitle) : strtolower($cleanTitle);

  $category = 'Политика';
  if (preg_match('/биткоин|крипто|etf|sol|btc|ethereum|криптов/i', $lower)) {
    $category = 'Крипто';
  } elseif (preg_match('/фрс|ставк|инфляц|ввп|рецесс|рынк|санкц|эконом/i', $lower)) {
    $category = 'Экономика';
  } elseif (preg_match('/ии|ai|чип|техн|спутник|космос|стартап/i', $lower)) {
    $category = 'Технологии';
  } elseif (preg_match('/матч|чемпион|турнир|футбол|хоккей|спорт/i', $lower)) {
    $category = 'Спорт';
  }

  $description = 'В этом рынке проверяется, наступит ли указанное в заголовке событие в установленный срок. Исход "Да" фиксируется только при наличии прямого подтверждения в официальных сообщениях регуляторов, госорганов, эмитентов или в публикациях крупных деловых СМИ. Исход "Нет" фиксируется, если до дедлайна подтверждение не опубликовано либо новости содержат лишь обсуждения без факта наступления события.';
  if ($cleanTitle !== '') {
    $description = 'Рынок проверяет событие: "' . $cleanTitle . '". Исход "Да" засчитывается, когда до дедлайна появляется официальное подтверждение результата (релиз регулятора, заявление ведомства, отчет компании или подтвержденная публикация ведущих деловых СМИ). Исход "Нет" засчитывается, если к моменту дедлайна такого подтверждения нет, событие переносится за пределы срока или формулировка результата не позволяет однозначно подтвердить наступление события.';
  }
  return [
    'short_description' => $description,
    'title_en' => '',
    'short_description_en' => '',
    'category' => $category,
    'source_links' => farhuaad_default_ru_sources($cleanTitle, 3),
    'image_query' => $cleanTitle,
  ];
}

/**
 * Если в БД попало усечённое шаблонное описание (из-за VARCHAR/TINYTEXT), дописывает полный текст и сохраняет.
 *
 * @return non-empty-string|null полный текст при исправлении, иначе null
 */
function farhuaad_maybe_heal_truncated_fallback_description(PDO $pdo, int $disputeId, string $title, string $storedDesc): ?string
{
  if ($disputeId <= 0 || trim($title) === '' || trim($storedDesc) === '') {
    return null;
  }
  $storedDesc = trim($storedDesc);
  $meta = farhuaad_build_basic_dispute_meta($title);
  $full = trim((string)($meta['short_description'] ?? ''));
  if ($full === '') {
    return null;
  }
  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    $ls = mb_strlen($storedDesc);
    $lf = mb_strlen($full);
    if ($ls >= $lf) {
      return null;
    }
    if (mb_substr($full, 0, $ls) !== $storedDesc) {
      return null;
    }
  } else {
    $ls = strlen($storedDesc);
    $lf = strlen($full);
    if ($ls >= $lf) {
      return null;
    }
    if (strncmp($full, $storedDesc, $ls) !== 0) {
      return null;
    }
  }
  $upd = $pdo->prepare('UPDATE disputes SET short_description = :d WHERE id = :id LIMIT 1');
  $upd->execute([':d' => $full, ':id' => $disputeId]);
  return $full;
}

function farhuaad_is_watery_description(string $description): bool
{
  $text = trim($description);
  if ($text === '') {
    return true;
  }
  $lower = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
  $bannedPhrases = [
    'актуальн',
    'бинарн',
    'подтвержденн',
    'надежных сми',
    'рынок прогноза по актуальной новости',
  ];
  foreach ($bannedPhrases as $phrase) {
    if (str_contains($lower, $phrase)) {
      return true;
    }
  }
  $hasOutcomeMarker = str_contains($lower, 'да') || str_contains($lower, 'нет');
  $hasTimeMarker = preg_match('/\d{4}|\d{1,2}\s*[.:]\s*\d{2}|месяц|заседан|срок|до конца|квартал/u', $lower) === 1;
  return !$hasOutcomeMarker || !$hasTimeMarker;
}

/**
 * Политика площадки: не публиковать споры вокруг СВО и военно-политического конфликта России и Украины.
 */
function farhuaad_dispute_topic_policy_blocked(string $title, string $description = ''): bool
{
  $combined = trim($title . "\n" . $description);
  if ($combined === '') {
    return false;
  }
  $t = function_exists('mb_strtolower') ? mb_strtolower($combined) : strtolower($combined);

  if (preg_match('/(?<![\p{L}\p{N}])сво(?![\p{L}\p{N}])/u', $t) === 1) {
    return true;
  }
  if (preg_match('/\b(smo|svo)\b/i', $combined) === 1) {
    return true;
  }
  if (preg_match('/спецоперац|специальной\s+военной\s+операц|специальн[ао][яюй]\s+военн[ао][яюй]\s+операц/ui', $t) === 1) {
    return true;
  }
  if (preg_match('/special\s+military\s+operation/i', $combined) === 1) {
    return true;
  }
  if (preg_match('/\b(днр|лнр)\b/ui', $t) === 1) {
    return true;
  }

  $hasUa = preg_match('/украин|україн|ukrain/i', $t) === 1;
  $hasRuSide = preg_match('/росси|путин|кремл|\brussia\b|\brussias\b|\brussian\b|\brussians\b/ui', $t) === 1;
  if ($hasUa && $hasRuSide) {
    return true;
  }

  if (preg_match('/украин\w*.{0,100}войн\w*|войн\w*.{0,100}украин\w*/ui', $t) === 1) {
    return true;
  }
  if (preg_match('/ukrain\w*.{0,100}\bwar\b|\bwar\w*.{0,100}ukrain\w*/i', $combined) === 1) {
    return true;
  }
  if (preg_match('/ukrain\w*.{0,100}russia|russia.{0,100}ukrain\w*/i', $combined) === 1) {
    return true;
  }

  return false;
}

function farhuaad_dispute_content_is_legal_safe(string $title, string $description = ''): bool
{
  $text = trim($title . ' ' . $description);
  if ($text === '') {
    return false;
  }
  $t = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);

  if (farhuaad_dispute_topic_policy_blocked($title, $description)) {
    return false;
  }

  // Block obviously risky/illegal topics and "how-to" harm framing.
  $blocked = [
    'как сделать бомбу', 'как взломать', 'как украсть', 'как отмыть',
    'наркот', 'закладк', 'производств[оа] наркот', 'купить наркот',
    'террор', 'теракт', 'экстрем', 'призыв к насилию', 'массовые беспоряд',
    'порн', 'child porn', 'цп', 'детск.*порн',
    'суицид', 'самоубийств',
    'убийств', 'заказное убийство',
    'подделка документов', 'фальшивые документы', 'фальшивый паспорт',
    'обход санкций', 'уклонени[ея] от санкц',
  ];
  foreach ($blocked as $pattern) {
    if (preg_match('/' . $pattern . '/u', $t) === 1) {
      return false;
    }
  }

  // Keep generator focused on public-news verifiable outcomes.
  $allowedMarkers = ['официаль', 'регулятор', 'отчет', 'релиз', 'заседан', 'дедлайн', 'до конца', 'квартал', 'месяц'];
  foreach ($allowedMarkers as $marker) {
    if (str_contains($t, $marker)) {
      return true;
    }
  }
  return true;
}

function farhuaad_backfill_watery_descriptions(PDO $pdo, int $limit = 20): int
{
  if ($limit <= 0) {
    return 0;
  }
  $stmt = $pdo->prepare(
    "SELECT id, title, short_description
     FROM disputes
     WHERE status = 'active'
     ORDER BY created_at DESC
     LIMIT :limit"
  );
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();
  if (!$rows) {
    return 0;
  }

  $update = $pdo->prepare("UPDATE disputes SET short_description = :short_description WHERE id = :id");
  $updated = 0;
  foreach ($rows as $row) {
    $id = (int)($row['id'] ?? 0);
    $title = trim((string)($row['title'] ?? ''));
    $desc = trim((string)($row['short_description'] ?? ''));
    if ($id <= 0 || $title === '' || !farhuaad_is_watery_description($desc)) {
      continue;
    }
    $meta = farhuaad_build_basic_dispute_meta($title);
    $newDescription = trim((string)($meta['short_description'] ?? ''));
    if ($newDescription === '') {
      continue;
    }
    $update->execute([
      ':short_description' => $newDescription,
      ':id' => $id,
    ]);
    $updated++;
  }
  return $updated;
}

function farhuaad_blocked_source_domains_ru(): array
{
  return [
    'meduza.io',
    'theins.ru',
    'bbc.com',
    'bbc.co.uk',
    'dw.com',
    'currenttime.tv',
    'svoboda.org',
    'radiofreeeurope.org',
    'ovd.news',
    'tvrain.ru',
    'facebook.com',
    'instagram.com',
    'x.com',
    'twitter.com',
  ];
}

function farhuaad_source_allowed_for_ru(string $url): bool
{
  $host = farhuaad_extract_host($url);
  if ($host === '') {
    return false;
  }
  foreach (farhuaad_blocked_source_domains_ru() as $blocked) {
    $blocked = strtolower(trim($blocked));
    if ($blocked === '') {
      continue;
    }
    if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
      return false;
    }
  }
  return true;
}

function farhuaad_filter_sources_for_ru(array $links, int $limit = 3): array
{
  $result = [];
  foreach ($links as $url) {
    $value = trim((string)$url);
    if ($value === '') {
      continue;
    }
    if (!str_starts_with($value, 'https://') && !str_starts_with($value, 'http://')) {
      continue;
    }
    if (!farhuaad_source_allowed_for_ru($value)) {
      continue;
    }
    $host = farhuaad_extract_host($value);
    // Keep only Russian sources/domains.
    $isRussianHost = str_ends_with($host, '.ru')
      || str_ends_with($host, '.xn--p1ai')
      || $host === 'news.google.com'
      || in_array($host, ['tass.ru', 'ria.ru', 'rbc.ru', 'kommersant.ru', 'vedomosti.ru', 'interfax.ru', 'iz.ru', 'lenta.ru'], true);
    if (!$isRussianHost) {
      continue;
    }
    $result[] = $value;
    if (count($result) >= $limit) {
      break;
    }
  }
  return array_values(array_unique($result));
}

/**
 * Сохранение в БД: только валидные URL, без геофильтра .ru (иначе ИИ-ссылки на мировые СМИ обнулялись).
 */
function farhuaad_sanitize_source_links_storage(array $links, int $limit = 5): array
{
  $result = [];
  foreach ($links as $url) {
    $value = trim((string)$url);
    if ($value === '') {
      continue;
    }
    if (!str_starts_with($value, 'https://') && !str_starts_with($value, 'http://')) {
      continue;
    }
    $host = farhuaad_extract_host($value);
    if ($host === '' || preg_match('/^(localhost|127\.0\.0\.1)$/i', $host)) {
      continue;
    }
    $result[] = $value;
    if (count($result) >= $limit) {
      break;
    }
  }

  return array_values(array_unique($result));
}

/** Для EN-локали в интерфейсе показываем сохранённые https-источники без ограничения .ru */
function farhuaad_filter_sources_for_en(array $links, int $limit = 5): array
{
  return farhuaad_sanitize_source_links_storage($links, $limit);
}

function farhuaad_filter_sources_for_locale(array $links, int $limit = 5): array
{
  if (function_exists('farhuaad_lang') && farhuaad_lang() === 'en') {
    return farhuaad_filter_sources_for_en($links, $limit);
  }

  $ru = farhuaad_filter_sources_for_ru($links, $limit);
  if ($ru !== []) {
    return $ru;
  }

  // Fallback: если RU-фильтр отсеял всё (частый кейс для международных ссылок),
  // всё равно показываем валидные источники, чтобы ссылки не пропадали в интерфейсе.
  return farhuaad_sanitize_source_links_storage($links, $limit);
}

function farhuaad_default_ru_sources(string $title, int $limit = 3): array
{
  $query = trim($title);
  if ($query === '' || $limit <= 0) {
    return [];
  }

  $links = farhuaad_fetch_related_news_links($query, $limit);
  if ($links !== []) {
    return $links;
  }

  $fallback = [
    'https://news.google.com/search?q=' . rawurlencode($query) . '&hl=ru&gl=RU&ceid=RU:ru',
    'https://yandex.ru/news/search?text=' . rawurlencode($query),
  ];
  return farhuaad_sanitize_source_links_storage($fallback, $limit);
}

function farhuaad_is_public_domain_license(string $license): bool
{
  $value = function_exists('mb_strtolower')
    ? mb_strtolower(trim($license))
    : strtolower(trim($license));
  if ($value === '') {
    return false;
  }
  return str_contains($value, 'public domain')
    || str_contains($value, 'cc0')
    || str_contains($value, 'cc by')
    || str_contains($value, 'cc-by')
    || str_contains($value, 'creative commons');
}

function farhuaad_build_image_search_query(string $title, string $category = '', string $hint = ''): string
{
  $seed = trim($hint) !== '' ? trim($hint) : trim($title);
  $lower = function_exists('mb_strtolower') ? mb_strtolower($seed) : strtolower($seed);
  $tokens = [];

  $categoryMap = [
    'эконом' => ['economy', 'finance', 'global-market'],
    'полит' => ['politics', 'government', 'diplomacy'],
    'крипт' => ['crypto', 'blockchain', 'digital-asset'],
    'технолог' => ['technology', 'innovation', 'ai'],
    'спорт' => ['sport', 'competition', 'tournament'],
  ];
  foreach ($categoryMap as $needle => $mapped) {
    if (str_contains($lower, $needle)) {
      $tokens = array_merge($tokens, $mapped);
      break;
    }
  }

  $keywordMap = [
    'g7' => 'g7',
    'санкц' => 'sanctions',
    'чип' => 'semiconductors',
    'фрс' => 'federal-reserve',
    'ставк' => 'interest-rate',
    'соглашен' => 'agreement',
    'огн' => 'ceasefire',
    'конфликт' => 'conflict',
    'выбор' => 'election',
  ];
  foreach ($keywordMap as $needle => $mapped) {
    if (str_contains($lower, $needle)) {
      $tokens[] = $mapped;
    }
  }
  // Match AI only as standalone token, not as part of random words.
  if (preg_match('/(^|[^\\p{L}\\p{N}])(ии|ai)([^\\p{L}\\p{N}]|$)/ui', $seed) === 1) {
    $tokens[] = 'artificial-intelligence';
  }

  $categoryLower = function_exists('mb_strtolower') ? mb_strtolower($category) : strtolower($category);
  if ($categoryLower !== '' && !$tokens) {
    if (str_contains($categoryLower, 'эконом')) $tokens = ['economy', 'finance', 'news'];
    elseif (str_contains($categoryLower, 'полит')) $tokens = ['politics', 'world-news', 'government'];
    elseif (str_contains($categoryLower, 'крипт')) $tokens = ['crypto', 'market', 'digital-asset'];
    elseif (str_contains($categoryLower, 'технолог')) $tokens = ['technology', 'ai', 'industry'];
    elseif (str_contains($categoryLower, 'спорт')) $tokens = ['sport', 'match', 'tournament'];
  }

  if (!$tokens) {
    $tokens = ['world-news', 'editorial-photo'];
  }
  return implode(',', array_values(array_unique($tokens)));
}

function farhuaad_image_text_is_safe(string $text): bool
{
  $t = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
  $blocked = [
    'наркот', 'суицид', 'экстрем', 'террор', '18+', 'nsfw',
    'porn', 'nude', 'gore', 'beheading', 'weapon shooting'
  ];
  foreach ($blocked as $word) {
    if (str_contains($t, $word)) {
      return false;
    }
  }
  return true;
}

function farhuaad_ai_image_prompt(string $title, string $queryHint = '', string $shortDescription = '', string $category = ''): string
{
  $parts = [];
  $title = trim(preg_replace('/\s+/', ' ', $title));
  if ($title !== '') {
    $parts[] = 'Prediction market theme: ' . $title . '.';
  }
  $shortDescription = trim(preg_replace('/\s+/', ' ', $shortDescription));
  if ($shortDescription !== '') {
    $parts[] = 'Context: ' . $shortDescription . '.';
  }
  $category = trim(preg_replace('/\s+/', ' ', $category));
  if ($category !== '') {
    $parts[] = 'Category: ' . $category . '.';
  }
  $queryHint = trim(preg_replace('/\s+/', ' ', $queryHint));
  if ($queryHint !== '') {
    $parts[] = 'Visual keywords: ' . $queryHint . '.';
  }

  $parts[] = 'Create a realistic, neutral editorial-style horizontal image.';
  $parts[] = 'No text, no logos, no watermarks, no UI elements, no flags as main focus.';
  $parts[] = 'Clear subject-object relation that directly reflects the event context.';
  $parts[] = 'Photorealistic lighting, high detail, modern news visual style, 16:9 composition.';

  return implode(' ', $parts);
}

function farhuaad_store_generated_image(string $binary): string
{
  if ($binary === '') {
    return '';
  }
  $dir = dirname(__DIR__) . '/assets/img/generated';
  if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
    return '';
  }
  $name = 'dispute_' . date('Ymd_His') . '_' . substr(sha1($binary . microtime(true)), 0, 12) . '.png';
  $fullPath = $dir . '/' . $name;
  if (@file_put_contents($fullPath, $binary) === false) {
    return '';
  }
  $relative = 'assets/img/generated/' . $name;
  if (function_exists('farhuaad_url')) {
    return farhuaad_url($relative);
  }
  return '/' . $relative;
}

function farhuaad_generate_image_with_openai(
  string $title,
  string $queryHint = '',
  string $shortDescription = '',
  string $category = ''
): string {
  $apiKey = farhuaad_get_openai_key();
  if ($apiKey === '') {
    return '';
  }
  $basePrompt = farhuaad_ai_image_prompt($title, $queryHint, $shortDescription, $category);
  if (!farhuaad_image_text_is_safe($basePrompt)) {
    return '';
  }

  for ($attempt = 1; $attempt <= FARHUAAD_AI_IMAGE_RETRY_COUNT; $attempt++) {
    $attemptPrompt = $basePrompt . ' Attempt #' . $attempt . ': ensure the scene strongly matches the described event context.';
    $response = farhuaad_http_post_json(
      'https://api.openai.com/v1/images/generations',
      [
        'model' => 'gpt-image-1',
        'prompt' => $attemptPrompt,
        'size' => '1536x1024',
        'quality' => 'high',
      ],
      [
        'Authorization: Bearer ' . $apiKey,
      ]
    );
    if (!is_array($response)) {
      continue;
    }
    $imageData = $response['data'][0]['b64_json'] ?? '';
    if (!is_string($imageData) || trim($imageData) === '') {
      continue;
    }
    $binary = base64_decode($imageData, true);
    if (!is_string($binary) || $binary === '') {
      continue;
    }
    $stored = farhuaad_store_generated_image($binary);
    if ($stored !== '') {
      return $stored;
    }
  }

  return '';
}

function farhuaad_pick_image_from_commons(
  string $title,
  string $queryHint = '',
  string $shortDescription = '',
  string $category = ''
): string
{
  return farhuaad_pick_random_pattern_image();
}

function farhuaad_ensure_disputes_table(PDO $pdo): void
{
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS disputes (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      source_date DATE NOT NULL,
      creation_source VARCHAR(20) NOT NULL DEFAULT 'ai',
      title VARCHAR(255) NOT NULL,
      short_description TEXT DEFAULT NULL,
      category VARCHAR(80) DEFAULT NULL,
      source_links JSON DEFAULT NULL,
      image_path VARCHAR(255) DEFAULT NULL,
      expires_at DATETIME NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      winning_side VARCHAR(5) DEFAULT NULL,
      resolution_source VARCHAR(500) DEFAULT NULL,
      resolution_title VARCHAR(255) DEFAULT NULL,
      resolved_at DATETIME DEFAULT NULL,
      payout_processed_at DATETIME DEFAULT NULL,
      resolution_meta JSON DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_source_date (source_date),
      INDEX idx_expires_at (expires_at),
      INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

function farhuaad_ensure_dispute_bets_table(PDO $pdo): void
{
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS dispute_bets (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      dispute_id BIGINT UNSIGNED NOT NULL,
      user_id BIGINT UNSIGNED NOT NULL,
      side VARCHAR(5) NOT NULL,
      amount DECIMAL(18,2) NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_dispute_side (dispute_id, side),
      INDEX idx_dispute_user (dispute_id, user_id),
      INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

function farhuaad_ensure_dispute_submissions_table(PDO $pdo): void
{
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS dispute_submissions (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NOT NULL,
      title VARCHAR(255) NOT NULL,
      short_description MEDIUMTEXT NOT NULL,
      category VARCHAR(80) DEFAULT NULL,
      source_links JSON DEFAULT NULL,
      expires_at DATETIME NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      admin_note VARCHAR(500) DEFAULT NULL,
      reviewed_by BIGINT UNSIGNED DEFAULT NULL,
      reviewed_at DATETIME DEFAULT NULL,
      approved_dispute_id BIGINT UNSIGNED DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_submission_status (status),
      INDEX idx_submission_user (user_id),
      INDEX idx_submission_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

function farhuaad_migrate_disputes_table(PDO $pdo): void
{
  $columnsStmt = $pdo->query("SHOW COLUMNS FROM disputes");
  $columns = $columnsStmt ? $columnsStmt->fetchAll() : [];
  $names = [];
  foreach ($columns as $col) {
    $name = (string)($col['Field'] ?? '');
    if ($name !== '') {
      $names[$name] = true;
    }
  }

  if (!isset($names['status'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER expires_at");
    $pdo->exec("CREATE INDEX idx_status ON disputes (status)");
  }
  if (!isset($names['creation_source'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN creation_source VARCHAR(20) NOT NULL DEFAULT 'ai' AFTER source_date");
  }
  if (!isset($names['resolution_source'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN resolution_source VARCHAR(500) DEFAULT NULL AFTER status");
  }
  if (!isset($names['resolution_title'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN resolution_title VARCHAR(255) DEFAULT NULL AFTER resolution_source");
  }
  if (!isset($names['resolved_at'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN resolved_at DATETIME DEFAULT NULL AFTER resolution_title");
  }
  if (!isset($names['winning_side'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN winning_side VARCHAR(5) DEFAULT NULL AFTER status");
  }
  if (!isset($names['short_description'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN short_description TEXT DEFAULT NULL AFTER title");
  } else {
    foreach ($columns as $col) {
      if ((string)($col['Field'] ?? '') === 'short_description') {
        $typeRaw = strtolower((string)($col['Type'] ?? ''));
        $base = trim(explode('(', $typeRaw, 2)[0]);
        /* VARCHAR(500)/TINYTEXT и т.п. обрезали длинные правила посередине слова */
        if (!in_array($base, ['text', 'mediumtext', 'longtext'], true)) {
          $pdo->exec('ALTER TABLE disputes MODIFY COLUMN short_description MEDIUMTEXT NULL');
        }
        break;
      }
    }
  }
  if (!isset($names['category'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN category VARCHAR(80) DEFAULT NULL AFTER short_description");
  }
  if (!isset($names['source_links'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN source_links JSON DEFAULT NULL AFTER category");
  }
  if (!isset($names['payout_processed_at'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN payout_processed_at DATETIME DEFAULT NULL AFTER resolved_at");
  }
  if (!isset($names['resolution_meta'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN resolution_meta JSON DEFAULT NULL AFTER payout_processed_at");
  }
  if (!isset($names['title_en'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN title_en VARCHAR(255) DEFAULT NULL AFTER title");
  }
  if (!isset($names['short_description_en'])) {
    $pdo->exec("ALTER TABLE disputes ADD COLUMN short_description_en TEXT DEFAULT NULL AFTER short_description");
  } else {
    foreach ($columns as $col) {
      if ((string)($col['Field'] ?? '') === 'short_description_en') {
        $typeRaw = strtolower((string)($col['Type'] ?? ''));
        $base = trim(explode('(', $typeRaw, 2)[0]);
        if (!in_array($base, ['text', 'mediumtext', 'longtext'], true)) {
          $pdo->exec('ALTER TABLE disputes MODIFY COLUMN short_description_en MEDIUMTEXT NULL');
        }
        break;
      }
    }
  }
}

function farhuaad_migrate_dispute_submissions_table(PDO $pdo): void
{
  $columnsStmt = $pdo->query("SHOW COLUMNS FROM dispute_submissions");
  $columns = $columnsStmt ? $columnsStmt->fetchAll() : [];
  $names = [];
  foreach ($columns as $col) {
    $name = (string)($col['Field'] ?? '');
    if ($name !== '') {
      $names[$name] = true;
    }
  }

  if (!isset($names['status'])) {
    $pdo->exec("ALTER TABLE dispute_submissions ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER expires_at");
    $pdo->exec("CREATE INDEX idx_submission_status ON dispute_submissions (status)");
  }
  if (!isset($names['admin_note'])) {
    $pdo->exec("ALTER TABLE dispute_submissions ADD COLUMN admin_note VARCHAR(500) DEFAULT NULL AFTER status");
  }
  if (!isset($names['reviewed_by'])) {
    $pdo->exec("ALTER TABLE dispute_submissions ADD COLUMN reviewed_by BIGINT UNSIGNED DEFAULT NULL AFTER admin_note");
  }
  if (!isset($names['reviewed_at'])) {
    $pdo->exec("ALTER TABLE dispute_submissions ADD COLUMN reviewed_at DATETIME DEFAULT NULL AFTER reviewed_by");
  }
  if (!isset($names['approved_dispute_id'])) {
    $pdo->exec("ALTER TABLE dispute_submissions ADD COLUMN approved_dispute_id BIGINT UNSIGNED DEFAULT NULL AFTER reviewed_at");
  }
  if (!isset($names['updated_at'])) {
    $pdo->exec("ALTER TABLE dispute_submissions ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
  }
}

function farhuaad_delete_local_image_if_needed(string $path): void
{
  if (
    $path === ''
    || str_starts_with($path, 'assets/img/markets/')
    || str_starts_with($path, 'http://')
    || str_starts_with($path, 'https://')
  ) {
    return;
  }
  $abs = dirname(__DIR__) . '/' . ltrim($path, '/');
  if (is_file($abs)) {
    @unlink($abs);
  }
}

function farhuaad_expire_disputes_by_deadline(PDO $pdo): void
{
  $update = $pdo->prepare(
    "UPDATE disputes
     SET status = 'expired',
         winning_side = COALESCE(winning_side, 'no'),
         resolution_source = COALESCE(resolution_source, ''),
         resolution_title = COALESCE(resolution_title, 'Срок спора истек'),
         resolved_at = COALESCE(resolved_at, NOW())
     WHERE status = 'active' AND expires_at <= NOW()"
  );
  $update->execute();
}

function farhuaad_read_active_disputes(PDO $pdo): array
{
  $cap = max(1, FARHUAAD_ACTIVE_DISPUTES_FEED_CAP);
  $stmt = $pdo->query(
    "SELECT title, title_en, short_description, short_description_en, category, source_links, image_path, expires_at, creation_source
    , id
     FROM disputes
     WHERE status = 'active'
     ORDER BY created_at DESC
     LIMIT " . $cap
  );
  $items = $stmt ? $stmt->fetchAll() : [];
  $seenTitles = [];
  $rowsToShow = [];
  foreach ($items as $row) {
    if (!is_array($row)) {
      continue;
    }
    $titleKey = function_exists('mb_strtolower')
      ? mb_strtolower(trim((string)($row['title'] ?? '')))
      : strtolower(trim((string)($row['title'] ?? '')));
    if ($titleKey === '' || isset($seenTitles[$titleKey])) {
      continue;
    }
    $seenTitles[$titleKey] = true;
    $rowsToShow[] = $row;
    if (count($rowsToShow) >= $cap) {
      break;
    }
  }

  $idsForPools = [];
  foreach ($rowsToShow as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id > 0) {
      $idsForPools[] = $id;
    }
  }
  $pools = farhuaad_get_dispute_pool_stats_batch($pdo, $idsForPools);

  $result = [];
  foreach ($rowsToShow as $row) {
    $title = (string)($row['title'] ?? '');
    $shortDescription = (string)($row['short_description'] ?? '');
    $creationSource = (string)($row['creation_source'] ?? 'ai');
    if ($creationSource !== 'manual' && !farhuaad_dispute_description_matches_title($title, $shortDescription)) {
      continue;
    }
    $image = farhuaad_normalize_pattern_image_path((string)($row['image_path'] ?? ''));
    if ($image === '') {
      $image = farhuaad_pick_image_from_commons(
        $title,
        (string)($row['category'] ?? '') . ' ' . $title,
        $shortDescription,
        (string)($row['category'] ?? '')
      );
    }
    $disputeId = (int)($row['id'] ?? 0);
    $pool = $pools[$disputeId] ?? [
      'yes_pool' => 0.0,
      'no_pool' => 0.0,
      'total_pool' => 0.0,
    ];
    $total = (float)($pool['total_pool'] ?? 0);
    $yesPool = (float)($pool['yes_pool'] ?? 0);
    $noPool = (float)($pool['no_pool'] ?? 0);
    $yesPercent = $total > 0 ? round(($yesPool / $total) * 100, 1) : 50.0;
    $noPercent = $total > 0 ? round(($noPool / $total) * 100, 1) : 50.0;
    $item = [
      'id' => $disputeId,
      'title' => $title,
      'short_description' => $shortDescription,
      'title_en' => (string)($row['title_en'] ?? ''),
      'short_description_en' => (string)($row['short_description_en'] ?? ''),
      'category' => (string)($row['category'] ?? 'Событие'),
      'creation_source' => $creationSource,
      'source_links' => (static function () use ($row): array {
        $raw = is_string($row['source_links'] ?? null)
          ? (json_decode((string)$row['source_links'], true) ?: [])
          : (is_array($row['source_links'] ?? null) ? $row['source_links'] : []);
        $links = farhuaad_filter_sources_for_locale($raw, 3);

        return $links;
      })(),
      'image' => $image,
      'expires_at' => (string)$row['expires_at'],
      'yes_pool' => round($yesPool, 2),
      'no_pool' => round($noPool, 2),
      'total_pool' => round($total, 2),
      'yes_percent' => $yesPercent,
      'no_percent' => $noPercent,
    ];
    $result[] = farhuaad_dispute_localize_public_fields($item);
  }
  return $result;
}

function farhuaad_extract_host(string $url): string
{
  $host = (string)parse_url($url, PHP_URL_HOST);
  $host = strtolower(trim($host));
  if (str_starts_with($host, 'www.')) {
    $host = substr($host, 4);
  }
  return $host;
}

/**
 * Базовая проверка соответствия заголовка и описания.
 * Если пересечения по ключевым словам нет — спор скрывается из выдачи.
 */
function farhuaad_dispute_description_matches_title(string $title, string $description): bool
{
  $title = trim($title);
  $description = trim($description);
  if ($title === '' || $description === '') {
    return false;
  }

  $normalizeWords = static function (string $value): array {
    $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    $value = preg_replace('/[^a-zа-я0-9\s]+/iu', ' ', $value) ?? '';
    $parts = preg_split('/\s+/u', trim($value)) ?: [];
    $words = [];
    foreach ($parts as $part) {
      $word = trim((string)$part);
      if ($word === '') {
        continue;
      }
      $len = function_exists('mb_strlen') ? mb_strlen($word) : strlen($word);
      if ($len < 4) {
        continue;
      }
      $words[$word] = true;
    }
    return array_keys($words);
  };

  $titleWords = $normalizeWords($title);
  $descriptionWords = $normalizeWords($description);
  $overlap = 0;
  if ($titleWords !== [] && $descriptionWords !== []) {
    $titleSet = array_fill_keys($titleWords, true);
    foreach ($descriptionWords as $word) {
      if (isset($titleSet[$word])) {
        $overlap++;
      }
    }
  }

  $lower = function_exists('mb_strtolower') ? mb_strtolower($description) : strtolower($description);
  $hasOutcomeMarker = str_contains($lower, 'исход "да"')
    || str_contains($lower, 'исход "нет"')
    || str_contains($lower, 'засчитывается')
    || str_contains($lower, 'фиксируется');
  $hasTimeMarker = preg_match('/\d{4}|дедлайн|до конца|квартал|месяц|заседан/u', $lower) === 1;
  $hasVerificationMarker = preg_match('/официаль|релиз|регулятор|отчет|анонс|итог/u', $lower) === 1;

  if ($overlap >= 1) {
    return true;
  }

  // Если описание явно содержит проверяемые критерии, считаем его валидным даже без лексического совпадения.
  return $hasOutcomeMarker && $hasTimeMarker && $hasVerificationMarker;
}

function farhuaad_fetch_related_news_links(string $title, int $limit = 3): array
{
  $query = trim($title);
  if ($query === '' || $limit <= 0) {
    return [];
  }

  $rssUrl = 'https://news.google.com/rss/search?q=' . rawurlencode($query) . '&hl=ru&gl=RU&ceid=RU:ru';
  $xmlRaw = farhuaad_http_get_text($rssUrl);
  if (!is_string($xmlRaw) || trim($xmlRaw) === '') {
    return [];
  }

  $xml = @simplexml_load_string($xmlRaw);
  if (!$xml || !isset($xml->channel->item)) {
    return [];
  }

  $links = [];
  foreach ($xml->channel->item as $item) {
    $link = trim((string)($item->link ?? ''));
    $picked = $link;
    if ($picked === '') {
      continue;
    }
    $links[] = $picked;
    if (count($links) >= $limit) {
      break;
    }
  }
  $filtered = farhuaad_sanitize_source_links_storage($links, $limit);
  if ($filtered) {
    return $filtered;
  }
  return [];
}

function farhuaad_get_dispute_pool_stats(PDO $pdo, int $disputeId): array
{
  $stmt = $pdo->prepare(
    "SELECT
      COALESCE(SUM(CASE WHEN b.side = 'yes' THEN b.amount ELSE 0 END), 0) AS yes_pool,
      COALESCE(SUM(CASE WHEN b.side = 'no' THEN b.amount ELSE 0 END), 0) AS no_pool,
      COALESCE(SUM(b.amount), 0) AS total_pool
     FROM dispute_bets b
     INNER JOIN users u ON u.id = b.user_id
     WHERE b.dispute_id = :dispute_id"
  );
  $stmt->execute([':dispute_id' => $disputeId]);
  $row = $stmt->fetch();

  return [
    'yes_pool' => (float)($row['yes_pool'] ?? 0),
    'no_pool' => (float)($row['no_pool'] ?? 0),
    'total_pool' => (float)($row['total_pool'] ?? 0),
  ];
}

/**
 * Пулы ставок для нескольких споров одним запросом (без N+1 в ленте).
 *
 * @param list<int> $disputeIds
 * @return array<int, array{yes_pool: float, no_pool: float, total_pool: float}>
 */
function farhuaad_get_dispute_pool_stats_batch(PDO $pdo, array $disputeIds): array
{
  $unique = [];
  foreach ($disputeIds as $id) {
    $id = (int)$id;
    if ($id > 0) {
      $unique[$id] = true;
    }
  }
  $idList = array_keys($unique);
  if ($idList === []) {
    return [];
  }
  sort($idList);
  $placeholders = implode(',', array_fill(0, count($idList), '?'));
  $stmt = $pdo->prepare(
    "SELECT b.dispute_id,
      COALESCE(SUM(CASE WHEN b.side = 'yes' THEN b.amount ELSE 0 END), 0) AS yes_pool,
      COALESCE(SUM(CASE WHEN b.side = 'no' THEN b.amount ELSE 0 END), 0) AS no_pool,
      COALESCE(SUM(b.amount), 0) AS total_pool
     FROM dispute_bets b
     INNER JOIN users u ON u.id = b.user_id
     WHERE b.dispute_id IN ($placeholders)
     GROUP BY b.dispute_id"
  );
  $stmt->execute($idList);
  $out = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!is_array($row)) {
      continue;
    }
    $did = (int)($row['dispute_id'] ?? 0);
    if ($did <= 0) {
      continue;
    }
    $out[$did] = [
      'yes_pool' => (float)($row['yes_pool'] ?? 0),
      'no_pool' => (float)($row['no_pool'] ?? 0),
      'total_pool' => (float)($row['total_pool'] ?? 0),
    ];
  }
  $empty = ['yes_pool' => 0.0, 'no_pool' => 0.0, 'total_pool' => 0.0];
  foreach ($idList as $id) {
    if (!isset($out[$id])) {
      $out[$id] = $empty;
    }
  }
  return $out;
}

function farhuaad_get_dispute_by_id(PDO $pdo, int $disputeId): ?array
{
  if ($disputeId <= 0) {
    return null;
  }
  $stmt = $pdo->prepare(
    "SELECT id, title, title_en, short_description, short_description_en, category, source_links, image_path, expires_at, status, winning_side, resolution_source, resolution_title, resolved_at, created_at, creation_source
     FROM disputes
     WHERE id = :id
     LIMIT 1"
  );
  $stmt->execute([':id' => $disputeId]);
  $row = $stmt->fetch();
  if (!is_array($row)) {
    return null;
  }

  if (
    farhuaad_lang() === 'en'
    && farhuaad_get_claude_key() !== ''
    && trim((string)($row['title_en'] ?? '')) === ''
  ) {
    farhuaad_backfill_dispute_en_for_ids($pdo, [$disputeId]);
    $stmt->execute([':id' => $disputeId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
      return null;
    }
  }

  $creationSource = (string)($row['creation_source'] ?? 'ai');
  if ($creationSource !== 'manual'
    && !farhuaad_dispute_description_matches_title((string)($row['title'] ?? ''), (string)($row['short_description'] ?? ''))) {
    return null;
  }

  $healed = farhuaad_maybe_heal_truncated_fallback_description(
    $pdo,
    $disputeId,
    (string)($row['title'] ?? ''),
    (string)($row['short_description'] ?? '')
  );
  if ($healed !== null) {
    $row['short_description'] = $healed;
  }

  $pool = farhuaad_get_dispute_pool_stats($pdo, $disputeId);
  $total = (float)($pool['total_pool'] ?? 0);
  $yesPool = (float)($pool['yes_pool'] ?? 0);
  $noPool = (float)($pool['no_pool'] ?? 0);
  $yesPercent = $total > 0 ? round(($yesPool / $total) * 100, 1) : 50.0;
  $noPercent = $total > 0 ? round(($noPool / $total) * 100, 1) : 50.0;

  $image = farhuaad_normalize_pattern_image_path((string)($row['image_path'] ?? ''));
  if ($image === '') {
    $image = farhuaad_pick_image_from_commons(
      (string)($row['title'] ?? ''),
      (string)($row['category'] ?? '') . ' ' . (string)($row['title'] ?? ''),
      (string)($row['short_description'] ?? ''),
      (string)($row['category'] ?? '')
    );
  }

  $links = [];
  if (is_string($row['source_links'] ?? null)) {
    $decoded = json_decode((string)$row['source_links'], true);
    if (is_array($decoded)) {
      $links = $decoded;
    }
  } elseif (is_array($row['source_links'] ?? null)) {
    $links = $row['source_links'];
  }

  $item = [
    'id' => (int)$row['id'],
    'title' => (string)$row['title'],
    'short_description' => (string)($row['short_description'] ?? ''),
    'title_en' => (string)($row['title_en'] ?? ''),
    'short_description_en' => (string)($row['short_description_en'] ?? ''),
    'category' => (string)($row['category'] ?? 'Событие'),
    'creation_source' => $creationSource,
    'source_links' => (static function () use ($links): array {
      $clean = array_values(array_filter($links, static fn ($v) => is_string($v) && trim($v) !== ''));

      return farhuaad_filter_sources_for_locale($clean, 5);
    })(),
    'image' => $image,
    'expires_at' => (string)$row['expires_at'],
    'status' => (string)$row['status'],
    'winning_side' => (string)($row['winning_side'] ?? ''),
    'resolution_source' => (string)($row['resolution_source'] ?? ''),
    'resolution_title' => (string)($row['resolution_title'] ?? ''),
    'resolved_at' => (string)($row['resolved_at'] ?? ''),
    'created_at' => (string)($row['created_at'] ?? ''),
    'yes_pool' => round($yesPool, 2),
    'no_pool' => round($noPool, 2),
    'total_pool' => round($total, 2),
    'yes_percent' => $yesPercent,
    'no_percent' => $noPercent,
  ];
  return farhuaad_dispute_localize_public_fields($item);
}

function farhuaad_place_dispute_bet(PDO $pdo, int $disputeId, int $userId, string $side, float $amount): array
{
  $side = strtolower(trim($side));
  if ($disputeId <= 0) {
    throw new RuntimeException('INVALID_DISPUTE_ID');
  }
  if ($userId <= 0) {
    throw new RuntimeException('UNAUTHORIZED');
  }
  if ($side !== 'yes' && $side !== 'no') {
    throw new RuntimeException('INVALID_SIDE');
  }
  $amount = round($amount, 2);
  if ($amount <= 0) {
    throw new RuntimeException('INVALID_AMOUNT');
  }

  $disputeStmt = $pdo->prepare(
    "SELECT id, status, expires_at
     FROM disputes
     WHERE id = :id
     LIMIT 1"
  );
  $disputeStmt->execute([':id' => $disputeId]);
  $dispute = $disputeStmt->fetch();
  if (!is_array($dispute)) {
    throw new RuntimeException('DISPUTE_NOT_FOUND');
  }
  if ((string)($dispute['status'] ?? '') !== 'active') {
    throw new RuntimeException('DISPUTE_NOT_ACTIVE');
  }
  $expiresAt = strtotime((string)($dispute['expires_at'] ?? ''));
  if ($expiresAt !== false && $expiresAt <= time()) {
    throw new RuntimeException('DISPUTE_EXPIRED');
  }

  $pdo->beginTransaction();
  try {
    $debit = $pdo->prepare(
      "UPDATE users
       SET balance = ROUND(balance - :debit_amount, 2)
       WHERE id = :user_id AND balance >= :balance_required"
    );
    $debit->execute([
      ':debit_amount' => $amount,
      ':balance_required' => $amount,
      ':user_id' => $userId,
    ]);
    if ($debit->rowCount() !== 1) {
      throw new RuntimeException('INSUFFICIENT_BALANCE');
    }

    $insert = $pdo->prepare(
      "INSERT INTO dispute_bets (dispute_id, user_id, side, amount)
       VALUES (:dispute_id, :user_id, :side, :amount)"
    );
    $insert->execute([
      ':dispute_id' => $disputeId,
      ':user_id' => $userId,
      ':side' => $side,
      ':amount' => $amount,
    ]);

    $balanceStmt = $pdo->prepare("SELECT COALESCE(balance, 0) AS balance FROM users WHERE id = :id LIMIT 1");
    $balanceStmt->execute([':id' => $userId]);
    $balanceAfter = (float)$balanceStmt->fetchColumn();

    $pool = farhuaad_get_dispute_pool_stats($pdo, $disputeId);
    $pdo->commit();

    return [
      'dispute_id' => $disputeId,
      'side' => $side,
      'amount' => $amount,
      'balance_after' => $balanceAfter,
      'pool' => $pool,
    ];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

function farhuaad_get_user_portfolio_positions(PDO $pdo, int $userId): array
{
  if ($userId <= 0) {
    return [];
  }

  $stmt = $pdo->prepare(
    "SELECT
      b.dispute_id,
      d.title,
      d.title_en,
      d.status,
      d.expires_at,
      d.winning_side,
      b.side,
      COUNT(*) AS bets_count,
      COALESCE(SUM(b.amount), 0) AS total_amount
     FROM dispute_bets b
     INNER JOIN disputes d ON d.id = b.dispute_id
     WHERE b.user_id = :user_id
     GROUP BY b.dispute_id, d.id, d.title, d.title_en, d.status, d.expires_at, d.winning_side, b.side
     ORDER BY MAX(b.created_at) DESC"
  );
  $stmt->execute([':user_id' => $userId]);
  $rows = $stmt->fetchAll();
  if (!is_array($rows) || !$rows) {
    return [];
  }

  $result = [];
  foreach ($rows as $row) {
    $disputeId = (int)($row['dispute_id'] ?? 0);
    $side = strtolower(trim((string)($row['side'] ?? '')));
    $status = trim((string)($row['status'] ?? 'active'));
    $winningSide = strtolower(trim((string)($row['winning_side'] ?? '')));
    $amount = round((float)($row['total_amount'] ?? 0), 2);
    $pnl = 0.0;

    if ($status === 'resolved' || $status === 'expired') {
      if (($winningSide === 'yes' || $winningSide === 'no') && $winningSide === $side) {
        $sumStmt = $pdo->prepare(
          "SELECT
            COALESCE(SUM(b.amount), 0) AS total_pool,
            COALESCE(SUM(CASE WHEN b.side = :winning_side THEN b.amount ELSE 0 END), 0) AS winning_pool
           FROM dispute_bets b
           INNER JOIN users u ON u.id = b.user_id
           WHERE b.dispute_id = :dispute_id"
        );
        $sumStmt->execute([
          ':winning_side' => $winningSide,
          ':dispute_id' => $disputeId,
        ]);
        $sum = $sumStmt->fetch();
        $totalPool = (float)($sum['total_pool'] ?? 0);
        $winningPool = (float)($sum['winning_pool'] ?? 0);
        if ($totalPool > 0 && $winningPool > 0) {
          $distributable = function_exists('farhuaad_distributable_pool_after_bookmaker')
            ? farhuaad_distributable_pool_after_bookmaker($totalPool)
            : round($totalPool, 2);
          $payout = ($amount / $winningPool) * $distributable;
          $pnl = round($payout - $amount, 2);
        }
      } else {
        $pnl = round(-$amount, 2);
      }
    }

    $titleRow = farhuaad_dispute_localize_copy_fields([
      'title' => (string)($row['title'] ?? ''),
      'title_en' => (string)($row['title_en'] ?? ''),
      'short_description' => '',
      'short_description_en' => '',
    ]);
    $result[] = [
      'dispute_id' => $disputeId,
      'title' => (string)($titleRow['title'] ?? ''),
      'status' => $status,
      'expires_at' => (string)($row['expires_at'] ?? ''),
      'side' => $side,
      'winning_side' => $winningSide,
      'market_open' => $status === 'active',
      'bets_count' => (int)($row['bets_count'] ?? 0),
      'amount' => $amount,
      'avg_price' => 1.0,
      'pnl' => $pnl,
    ];
  }

  return $result;
}

function farhuaad_get_user_portfolio_overview(PDO $pdo, int $userId): array
{
  $positions = farhuaad_get_user_portfolio_positions($pdo, $userId);
  $totalStaked = 0.0;
  $openPositions = 0;
  $closedPnl = 0.0;

  foreach ($positions as $position) {
    $amount = (float)($position['amount'] ?? 0);
    $isOpen = !empty($position['market_open']);
    if ($isOpen) {
      $totalStaked += $amount;
      $openPositions++;
    }
    $closedPnl += (float)($position['pnl'] ?? 0);
  }

  return [
    'positions' => $positions,
    'total_staked' => round($totalStaked, 2),
    'open_positions' => $openPositions,
    'closed_pnl' => round($closedPnl, 2),
  ];
}

function farhuaad_stats_dispute_status_label(string $status): string
{
  $status = strtolower(trim($status));
  switch ($status) {
    case 'active':
      return __('stats.status.active');
    case 'resolved':
      return __('stats.status.resolved');
    case 'expired':
      return __('stats.status.expired');
    default:
      return $status !== '' ? $status : '—';
  }
}

function farhuaad_stats_dispute_outcome_short(string $status, string $winningSide): string
{
  $status = strtolower(trim($status));
  if ($status === 'active') {
    return '—';
  }
  $w = strtolower(trim($winningSide));
  if ($w === 'yes') {
    return __('yes');
  }
  if ($w === 'no') {
    return __('no');
  }
  return '—';
}

function farhuaad_get_platform_stats(PDO $pdo): array
{
  // Объём: только ставки пользователей, которые ещё есть в users (сиротские строки после ручного удаления не считаем).
  $volumeStmt = $pdo->query("
    SELECT COALESCE(SUM(b.amount), 0) AS total_volume
    FROM dispute_bets b
    INNER JOIN users u ON u.id = b.user_id
    WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  ");
  $totalVolume = (float)($volumeStmt ? $volumeStmt->fetchColumn() : 0);

  $activeStmt = $pdo->query("SELECT title, short_description, creation_source FROM disputes WHERE status = 'active'");
  $activeRowsForCount = $activeStmt ? $activeStmt->fetchAll() : [];
  $activeMarkets = 0;
  foreach ($activeRowsForCount as $row) {
    $title = (string)($row['title'] ?? '');
    $shortDescription = (string)($row['short_description'] ?? '');
    $creationSource = (string)($row['creation_source'] ?? 'ai');
    if ($creationSource !== 'manual' && !farhuaad_dispute_description_matches_title($title, $shortDescription)) {
      continue;
    }
    $activeMarkets++;
  }

  $tradersStmt = $pdo->query(
    "SELECT COUNT(DISTINCT b.user_id)
     FROM dispute_bets b
     INNER JOIN users u ON u.id = b.user_id"
  );
  $uniqueTraders = (int)($tradersStmt ? $tradersStmt->fetchColumn() : 0);

  $topStmt = $pdo->query(
    "SELECT
      d.id,
      d.title,
      d.title_en,
      d.short_description,
      d.short_description_en,
      d.category,
      d.creation_source,
      d.status,
      d.winning_side,
      d.resolution_title,
      COALESCE(v.volume, 0) AS volume
     FROM disputes d
     LEFT JOIN (
       SELECT b.dispute_id, SUM(b.amount) AS volume
       FROM dispute_bets b
       INNER JOIN users u ON u.id = b.user_id
       GROUP BY b.dispute_id
     ) v ON v.dispute_id = d.id
     ORDER BY COALESCE(v.volume, 0) DESC, d.id DESC
     LIMIT 50"
  );
  $topRows = $topStmt ? $topStmt->fetchAll() : [];
  $topMarkets = [];
  $seenTitleKeys = [];
  $categorySet = [];

  foreach ($topRows as $row) {
    $title = trim((string)($row['title'] ?? ''));
    if ($title === '') {
      continue;
    }
    $shortDescription = (string)($row['short_description'] ?? '');
    $creationSource = (string)($row['creation_source'] ?? 'ai');
    if ($creationSource !== 'manual' && !farhuaad_dispute_description_matches_title($title, $shortDescription)) {
      continue;
    }
    $titleKey = function_exists('mb_strtolower')
      ? mb_strtolower($title)
      : strtolower($title);
    if (isset($seenTitleKeys[$titleKey])) {
      continue;
    }
    $seenTitleKeys[$titleKey] = true;

    $disputeId = (int)($row['id'] ?? 0);
    $pool = $disputeId > 0 ? farhuaad_get_dispute_pool_stats($pdo, $disputeId) : ['yes_pool' => 0, 'no_pool' => 0, 'total_pool' => 0];
    $total = (float)($pool['total_pool'] ?? 0);
    $yesPool = (float)($pool['yes_pool'] ?? 0);
    $yesPercent = $total > 0 ? round(($yesPool / $total) * 100, 1) : 50.0;

    $dStatus = (string)($row['status'] ?? 'active');
    $winSide = (string)($row['winning_side'] ?? '');
    $resTitle = trim((string)($row['resolution_title'] ?? ''));
    $cat = trim((string)($row['category'] ?? ''));
    if ($cat === '') {
      $cat = 'Событие';
    }
    $categorySet[$cat] = true;

    $locRow = farhuaad_dispute_localize_copy_fields([
      'title' => $title,
      'title_en' => (string)($row['title_en'] ?? ''),
      'short_description' => $shortDescription,
      'short_description_en' => (string)($row['short_description_en'] ?? ''),
    ]);

    $topMarkets[] = [
      'id' => $disputeId,
      'title' => (string)($locRow['title'] ?? $title),
      'category' => $cat,
      'status' => $dStatus,
      'status_label' => farhuaad_stats_dispute_status_label($dStatus),
      'deal_open' => strtolower($dStatus) === 'active',
      'winning_side' => $winSide,
      'outcome_label' => farhuaad_stats_dispute_outcome_short($dStatus, $winSide),
      'resolution_title' => $resTitle,
      'probability' => $yesPercent,
      'volume' => round((float)($row['volume'] ?? 0), 2),
      'liquidity' => round($total, 2),
    ];
    if (count($topMarkets) >= 50) {
      break;
    }
  }

  $defaultCategories = ['Крипто', 'Экономика', 'Политика', 'Технологии', 'Спорт', 'Событие'];
  foreach ($defaultCategories as $dc) {
    $categorySet[$dc] = true;
  }
  $statsCategories = array_keys($categorySet);
  natcasesort($statsCategories);
  $statsCategories = array_values($statsCategories);

  return [
    'total_volume' => round($totalVolume, 2),
    'active_markets' => $activeMarkets,
    'unique_traders' => $uniqueTraders,
    'top_markets' => $topMarkets,
    'stats_categories' => $statsCategories,
  ];
}

function farhuaad_settle_dispute_payouts(PDO $pdo): void
{
  $closedStmt = $pdo->query(
    "SELECT id, status, winning_side
     FROM disputes
     WHERE status IN ('resolved', 'expired')
       AND payout_processed_at IS NULL
     ORDER BY resolved_at ASC, id ASC
     LIMIT 20"
  );
  $closed = $closedStmt ? $closedStmt->fetchAll() : [];
  if (!$closed) {
    return;
  }

  foreach ($closed as $row) {
    $disputeId = (int)($row['id'] ?? 0);
    if ($disputeId <= 0) {
      continue;
    }

    $winningSide = strtolower(trim((string)($row['winning_side'] ?? '')));
    if ($winningSide !== 'yes' && $winningSide !== 'no') {
      $status = (string)($row['status'] ?? '');
      $winningSide = $status === 'resolved' ? 'yes' : 'no';
    }

    $pdo->beginTransaction();
    try {
      $sumStmt = $pdo->prepare(
        "SELECT
          COALESCE(SUM(b.amount), 0) AS total_pool,
          COALESCE(SUM(CASE WHEN b.side = :winning_side THEN b.amount ELSE 0 END), 0) AS winning_pool
         FROM dispute_bets b
         INNER JOIN users u ON u.id = b.user_id
         WHERE b.dispute_id = :dispute_id"
      );
      $sumStmt->execute([
        ':winning_side' => $winningSide,
        ':dispute_id' => $disputeId,
      ]);
      $sum = $sumStmt->fetch();
      $totalPool = (float)($sum['total_pool'] ?? 0);
      $winningPool = (float)($sum['winning_pool'] ?? 0);
      $distributablePool = function_exists('farhuaad_distributable_pool_after_bookmaker')
        ? farhuaad_distributable_pool_after_bookmaker($totalPool)
        : round($totalPool, 2);

      if ($totalPool > 0 && $winningPool > 0) {
        $betsStmt = $pdo->prepare(
          "SELECT b.user_id, b.amount
           FROM dispute_bets b
           INNER JOIN users u ON u.id = b.user_id
           WHERE b.dispute_id = :dispute_id AND b.side = :winning_side"
        );
        $betsStmt->execute([
          ':dispute_id' => $disputeId,
          ':winning_side' => $winningSide,
        ]);
        $winningBets = $betsStmt->fetchAll();

        $winnerIds = [];
        foreach ($winningBets as $bet) {
          $wu = (int)($bet['user_id'] ?? 0);
          if ($wu > 0) {
            $winnerIds[$wu] = true;
          }
        }
        $referrerOfWinner = [];
        if ($winnerIds) {
          try {
            $ids = array_keys($winnerIds);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $refMapStmt = $pdo->prepare(
              "SELECT u.id AS winner_id, rb.id AS referrer_id
               FROM users u
               INNER JOIN users rb ON rb.id = u.referred_by_user_id
               WHERE u.id IN ($ph)"
            );
            $refMapStmt->execute($ids);
            foreach ($refMapStmt->fetchAll() as $rrow) {
              if (!is_array($rrow)) {
                continue;
              }
              $wid = (int)($rrow['winner_id'] ?? 0);
              $rid = (int)($rrow['referrer_id'] ?? 0);
              if ($wid > 0 && $rid > 0 && $rid !== $wid) {
                $referrerOfWinner[$wid] = $rid;
              }
            }
          } catch (Throwable $e) {
            // колонка referred_by_user_id может отсутствовать до миграции
          }
        }

        $credit = $pdo->prepare(
          "UPDATE users
           SET balance = ROUND(balance + :payout, 2)
           WHERE id = :user_id"
        );
        foreach ($winningBets as $bet) {
          $bi = (float)($bet['amount'] ?? 0);
          $uid = (int)($bet['user_id'] ?? 0);
          if ($bi <= 0 || $uid <= 0) {
            continue;
          }
          // Wi = (Bi / ΣB) * P_к_распределению (после удержания комиссии букмекера с пула).
          $wi = round(($bi / $winningPool) * $distributablePool, 2);
          if ($wi <= 0) {
            continue;
          }
          $credit->execute([
            ':payout' => $wi,
            ':user_id' => $uid,
          ]);

          // 1% только пригласившему этого победителя (его referred_by_user_id), не с чужих выплат.
          $refUid = $referrerOfWinner[$uid] ?? 0;
          if ($refUid > 0 && $refUid !== $uid && defined('FARHUAAD_REFERRAL_WIN_FRACTION')) {
            $bonus = round($wi * (float)FARHUAAD_REFERRAL_WIN_FRACTION, 2);
            if ($bonus > 0) {
              $credit->execute([
                ':payout' => $bonus,
                ':user_id' => $refUid,
              ]);
            }
          }
        }
      }

      $markProcessed = $pdo->prepare(
        "UPDATE disputes
         SET winning_side = :winning_side,
             payout_processed_at = NOW()
         WHERE id = :id"
      );
      $markProcessed->execute([
        ':winning_side' => $winningSide,
        ':id' => $disputeId,
      ]);

      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
    }
  }
}

function farhuaad_insert_generated_disputes(PDO $pdo, int $limit): int
{
  if ($limit <= 0) {
    return 0;
  }

  $target = max($limit * 4, 24);
  $generated = [];
  $generated = array_merge($generated, farhuaad_generate_titles_with_claude($target));
  if (count($generated) < $limit) {
    $generated = array_merge($generated, farhuaad_generate_titles_with_claude($target));
  }
  $generated = array_merge($generated, farhuaad_generate_titles_from_news_context($target));
  if (count($generated) < $limit) {
    // last resort reserve pool
    $generated = array_merge($generated, farhuaad_fallback_disputes());
  }

  $existingStmt = $pdo->query("SELECT title FROM disputes");
  $existingRows = $existingStmt ? $existingStmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $existingTitleKeys = [];
  foreach ($existingRows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $key = farhuaad_normalize_dispute_title_key((string)($row['title'] ?? ''));
    if ($key !== '') {
      $existingTitleKeys[$key] = true;
    }
  }

  $moscowNow = farhuaad_disputes_moscow_now();
  $expiresAt = $moscowNow->modify('+30 days')->format('Y-m-d H:i:s');
  $today = farhuaad_disputes_moscow_today();
  $insert = $pdo->prepare(
    "INSERT INTO disputes (source_date, creation_source, title, title_en, short_description, short_description_en, category, source_links, image_path, expires_at)
     VALUES (:source_date, :creation_source, :title, :title_en, :short_description, :short_description_en, :category, :source_links, :image_path, :expires_at)"
  );
  $inserted = 0;
  $titlesForImageHint = [];
  $candidateTitleKeys = [];
  foreach ($generated as $item) {
    $title = trim((string)($item['title'] ?? ''));
    if ($title === '') continue;
    $title = preg_replace('/\s+/', ' ', $title);
    if (strpos((string)$title, '?') === false) {
      $title .= '?';
    }
    $key = farhuaad_normalize_dispute_title_key((string)$title);
    if ($key === '' || isset($candidateTitleKeys[$key]) || isset($existingTitleKeys[$key])) {
      continue;
    }
    $candidateTitleKeys[$key] = true;
    $titlesForImageHint[] = (string)$title;
  }
  $metaByTitle = farhuaad_generate_dispute_meta_with_claude($titlesForImageHint);
  $imageHintsByTitle = farhuaad_generate_image_queries_with_claude($titlesForImageHint);

  foreach ($titlesForImageHint as $title) {
    if ($inserted >= $limit) {
      break;
    }
    if (!farhuaad_dispute_content_is_legal_safe($title, '')) {
      continue;
    }
    $meta = $metaByTitle[$title] ?? farhuaad_build_basic_dispute_meta($title);
    // Prioritize Claude image_query from meta payload for per-market visual relevance.
    $imageHint = trim((string)($meta['image_query'] ?? ''));
    if ($imageHint === '') {
      $imageHint = trim((string)($imageHintsByTitle[$title] ?? ''));
    }
    if ($imageHint === '') {
      $imageHint = $title;
    }
    $imagePath = farhuaad_pick_image_from_commons(
      $title,
      $imageHint,
      (string)($meta['short_description'] ?? ''),
      (string)($meta['category'] ?? '')
    );
    $titleEn = trim((string)($meta['title_en'] ?? ''));
    $descriptionEn = trim((string)($meta['short_description_en'] ?? ''));
    $shortDescription = trim((string)($meta['short_description'] ?? ''));
    if ($shortDescription === '' || farhuaad_is_watery_description($shortDescription)) {
      $fallbackMeta = farhuaad_build_basic_dispute_meta($title);
      $shortDescription = trim((string)($fallbackMeta['short_description'] ?? ''));
      if ($descriptionEn === '') {
        $descriptionEn = trim((string)($fallbackMeta['short_description_en'] ?? ''));
      }
      if ($titleEn === '') {
        $titleEn = trim((string)($fallbackMeta['title_en'] ?? ''));
      }
    }
    if (function_exists('mb_substr')) {
      $shortDescription = mb_substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
      $titleEn = mb_substr($titleEn, 0, 255);
      $descriptionEn = mb_substr($descriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    } else {
      $shortDescription = substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
      $titleEn = substr($titleEn, 0, 255);
      $descriptionEn = substr($descriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    }
    if (!farhuaad_dispute_content_is_legal_safe($title, $shortDescription)) {
      continue;
    }
    if ($titleEn !== '' && !farhuaad_dispute_en_translation_save_ok($titleEn, $descriptionEn)) {
      $titleEn = '';
      $descriptionEn = '';
    }
    $category = trim((string)($meta['category'] ?? 'Событие'));
    $sourceLinks = $meta['source_links'] ?? [];
    if (!is_array($sourceLinks)) {
      $sourceLinks = [];
    }
    $sourceLinks = farhuaad_sanitize_source_links_storage($sourceLinks, 5);
    if (count($sourceLinks) === 0) {
      $sourceLinks = farhuaad_default_ru_sources($title, 3);
    }
    $sourceLinksJson = json_encode(array_values($sourceLinks), JSON_UNESCAPED_UNICODE);
    $insert->execute([
      ':source_date' => $today,
      ':creation_source' => 'ai',
      ':title' => $title,
      ':title_en' => $titleEn !== '' ? $titleEn : null,
      ':short_description' => $shortDescription,
      ':short_description_en' => $descriptionEn !== '' ? $descriptionEn : null,
      ':category' => $category,
      ':source_links' => $sourceLinksJson !== false ? $sourceLinksJson : '[]',
      ':image_path' => $imagePath,
      ':expires_at' => $expiresAt,
    ]);
    $inserted++;
    $existingTitleKeys[farhuaad_normalize_dispute_title_key($title)] = true;
  }

  return $inserted;
}

/**
 * Ручное создание спора из админки.
 *
 * @throws RuntimeException INVALID_INPUT|DUPLICATE_ACTIVE|LEGAL_BLOCKED
 */
function farhuaad_admin_create_dispute(
  PDO $pdo,
  string $title,
  string $shortDescription,
  string $category,
  array $sourceLinks,
  string $expiresAt,
  string $titleEn = '',
  string $shortDescriptionEn = ''
): int {
  $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
  $shortDescription = trim(preg_replace('/\s+/', ' ', $shortDescription) ?? '');
  $category = trim(preg_replace('/\s+/', ' ', $category) ?? '');
  $expiresAt = trim($expiresAt);
  $titleEn = trim(preg_replace('/\s+/', ' ', $titleEn) ?? '');
  $shortDescriptionEn = trim(preg_replace('/\s+/', ' ', $shortDescriptionEn) ?? '');

  if ($shortDescription === '') {
    $fallbackMeta = farhuaad_build_basic_dispute_meta($title);
    $shortDescription = trim((string)($fallbackMeta['short_description'] ?? ''));
    if ($shortDescriptionEn === '') {
      $shortDescriptionEn = trim((string)($fallbackMeta['short_description_en'] ?? ''));
    }
  }

  if ($title === '' || $shortDescription === '' || $expiresAt === '') {
    throw new RuntimeException('INVALID_INPUT');
  }
  if (function_exists('mb_substr')) {
    $title = mb_substr($title, 0, 255);
    $shortDescription = mb_substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    $category = mb_substr($category !== '' ? $category : 'Событие', 0, 80);
    $titleEn = mb_substr($titleEn, 0, 255);
    $shortDescriptionEn = mb_substr($shortDescriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
  } else {
    $title = substr($title, 0, 255);
    $shortDescription = substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    $category = substr($category !== '' ? $category : 'Событие', 0, 80);
    $titleEn = substr($titleEn, 0, 255);
    $shortDescriptionEn = substr($shortDescriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
  }
  $allowedCategories = ['Событие', 'Крипто', 'Экономика', 'Политика', 'Технологии', 'Спорт'];
  if (!in_array($category, $allowedCategories, true)) {
    $category = 'Событие';
  }

  $ts = strtotime($expiresAt);
  if ($ts === false) {
    throw new RuntimeException('INVALID_INPUT');
  }
  if (!farhuaad_dispute_content_is_legal_safe($title, $shortDescription)) {
    throw new RuntimeException('LEGAL_BLOCKED');
  }
  if ($titleEn !== '' && !farhuaad_dispute_en_translation_save_ok($titleEn, $shortDescriptionEn)) {
    throw new RuntimeException('LEGAL_BLOCKED');
  }
  $expiresAtSql = date('Y-m-d H:i:s', $ts);

  $existingStmt = $pdo->query("SELECT title FROM disputes");
  $existingRows = $existingStmt ? $existingStmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $newKey = farhuaad_normalize_dispute_title_key($title);
  $isDuplicate = false;
  foreach ($existingRows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $existingKey = farhuaad_normalize_dispute_title_key((string)($row['title'] ?? ''));
    if ($existingKey !== '' && $existingKey === $newKey) {
      $isDuplicate = true;
      break;
    }
  }
  if ($isDuplicate) {
    throw new RuntimeException('DUPLICATE_ACTIVE');
  }

  $cleanLinks = farhuaad_sanitize_source_links_storage(is_array($sourceLinks) ? $sourceLinks : [], 5);
  $sourceLinksJson = json_encode($cleanLinks, JSON_UNESCAPED_UNICODE);
  if ($sourceLinksJson === false) {
    $sourceLinksJson = '[]';
  }

  $imagePath = farhuaad_pick_image_from_commons($title, $title, $shortDescription, $category);
  $sourceDate = farhuaad_disputes_moscow_today();
  $insert = $pdo->prepare(
    "INSERT INTO disputes
      (source_date, creation_source, title, title_en, short_description, short_description_en, category, source_links, image_path, expires_at)
     VALUES
      (:source_date, 'manual', :title, :title_en, :short_description, :short_description_en, :category, :source_links, :image_path, :expires_at)"
  );
  $insert->execute([
    ':source_date' => $sourceDate,
    ':title' => $title,
    ':title_en' => $titleEn !== '' ? $titleEn : null,
    ':short_description' => $shortDescription,
    ':short_description_en' => $shortDescriptionEn !== '' ? $shortDescriptionEn : null,
    ':category' => $category,
    ':source_links' => $sourceLinksJson,
    ':image_path' => $imagePath,
    ':expires_at' => $expiresAtSql,
  ]);

  return (int)$pdo->lastInsertId();
}

function farhuaad_admin_update_dispute(
  PDO $pdo,
  int $id,
  string $title,
  string $shortDescription,
  array $sourceLinks,
  string $titleEn = '',
  string $shortDescriptionEn = ''
): void {
  if ($id <= 0) {
    throw new RuntimeException('INVALID_INPUT');
  }

  $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
  $shortDescription = trim((string)$shortDescription);
  $titleEn = trim(preg_replace('/\s+/', ' ', $titleEn) ?? '');
  $shortDescriptionEn = trim((string)$shortDescriptionEn);

  if ($title === '' || $shortDescription === '') {
    throw new RuntimeException('INVALID_INPUT');
  }

  if (function_exists('mb_substr')) {
    $title = mb_substr($title, 0, 255);
    $shortDescription = mb_substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    $titleEn = mb_substr($titleEn, 0, 255);
    $shortDescriptionEn = mb_substr($shortDescriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
  } else {
    $title = substr($title, 0, 255);
    $shortDescription = substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    $titleEn = substr($titleEn, 0, 255);
    $shortDescriptionEn = substr($shortDescriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
  }

  if (!farhuaad_dispute_content_is_legal_safe($title, $shortDescription)) {
    throw new RuntimeException('LEGAL_BLOCKED');
  }
  if ($titleEn !== '' && !farhuaad_dispute_en_translation_save_ok($titleEn, $shortDescriptionEn)) {
    throw new RuntimeException('LEGAL_BLOCKED');
  }

  $cleanLinks = farhuaad_sanitize_source_links_storage($sourceLinks, 5);
  $sourceLinksJson = json_encode($cleanLinks, JSON_UNESCAPED_UNICODE);
  if ($sourceLinksJson === false) {
    $sourceLinksJson = '[]';
  }

  $update = $pdo->prepare(
    "UPDATE disputes
     SET title = :title,
         short_description = :short_description,
         source_links = :source_links,
         title_en = :title_en,
         short_description_en = :short_description_en
     WHERE id = :id
     LIMIT 1"
  );
  $update->execute([
    ':id' => $id,
    ':title' => $title,
    ':short_description' => $shortDescription,
    ':source_links' => $sourceLinksJson,
    ':title_en' => $titleEn !== '' ? $titleEn : null,
    ':short_description_en' => $shortDescriptionEn !== '' ? $shortDescriptionEn : null,
  ]);

  if ($update->rowCount() < 1) {
    $check = $pdo->prepare("SELECT id FROM disputes WHERE id = :id LIMIT 1");
    $check->execute([':id' => $id]);
    if (!$check->fetch()) {
      throw new RuntimeException('NOT_FOUND');
    }
  }
}

/**
 * @throws RuntimeException INVALID_INPUT|LEGAL_BLOCKED
 */
function farhuaad_submit_dispute_for_moderation(
  PDO $pdo,
  int $userId,
  string $title,
  string $shortDescription,
  string $category,
  array $sourceLinks,
  string $expiresAt
): int {
  if ($userId <= 0) {
    throw new RuntimeException('INVALID_INPUT');
  }

  $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
  $shortDescription = trim((string)$shortDescription);
  $category = trim(preg_replace('/\s+/', ' ', $category) ?? '');
  $expiresAt = trim($expiresAt);

  if ($title === '' || $shortDescription === '' || $expiresAt === '') {
    throw new RuntimeException('INVALID_INPUT');
  }

  if (function_exists('mb_substr')) {
    $title = mb_substr($title, 0, 255);
    $shortDescription = mb_substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    $category = mb_substr($category !== '' ? $category : 'Событие', 0, 80);
  } else {
    $title = substr($title, 0, 255);
    $shortDescription = substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    $category = substr($category !== '' ? $category : 'Событие', 0, 80);
  }

  $allowedCategories = ['Событие', 'Крипто', 'Экономика', 'Политика', 'Технологии', 'Спорт'];
  if (!in_array($category, $allowedCategories, true)) {
    $category = 'Событие';
  }

  $ts = strtotime($expiresAt);
  if ($ts === false) {
    throw new RuntimeException('INVALID_INPUT');
  }

  if (!farhuaad_dispute_content_is_legal_safe($title, $shortDescription)) {
    throw new RuntimeException('LEGAL_BLOCKED');
  }

  $cleanLinks = farhuaad_sanitize_source_links_storage($sourceLinks, 5);
  $sourceLinksJson = json_encode($cleanLinks, JSON_UNESCAPED_UNICODE);
  if ($sourceLinksJson === false) {
    $sourceLinksJson = '[]';
  }

  $insert = $pdo->prepare(
    "INSERT INTO dispute_submissions
      (user_id, title, short_description, category, source_links, expires_at, status)
     VALUES
      (:user_id, :title, :short_description, :category, :source_links, :expires_at, 'pending')"
  );
  $insert->execute([
    ':user_id' => $userId,
    ':title' => $title,
    ':short_description' => $shortDescription,
    ':category' => $category,
    ':source_links' => $sourceLinksJson,
    ':expires_at' => date('Y-m-d H:i:s', $ts),
  ]);

  return (int)$pdo->lastInsertId();
}

/**
 * @throws RuntimeException INVALID_INPUT|NOT_FOUND
 */
function farhuaad_admin_update_dispute_submission(
  PDO $pdo,
  int $submissionId,
  string $title,
  string $shortDescription,
  string $category,
  array $sourceLinks,
  string $expiresAt
): void {
  if ($submissionId <= 0) {
    throw new RuntimeException('INVALID_INPUT');
  }

  $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
  $shortDescription = trim((string)$shortDescription);
  $category = trim(preg_replace('/\s+/', ' ', $category) ?? '');
  $expiresAt = trim($expiresAt);

  if ($title === '' || $shortDescription === '' || $expiresAt === '') {
    throw new RuntimeException('INVALID_INPUT');
  }

  $ts = strtotime($expiresAt);
  if ($ts === false) {
    throw new RuntimeException('INVALID_INPUT');
  }

  if (function_exists('mb_substr')) {
    $title = mb_substr($title, 0, 255);
    $shortDescription = mb_substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    $category = mb_substr($category !== '' ? $category : 'Событие', 0, 80);
  } else {
    $title = substr($title, 0, 255);
    $shortDescription = substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    $category = substr($category !== '' ? $category : 'Событие', 0, 80);
  }

  $allowedCategories = ['Событие', 'Крипто', 'Экономика', 'Политика', 'Технологии', 'Спорт'];
  if (!in_array($category, $allowedCategories, true)) {
    $category = 'Событие';
  }

  $cleanLinks = farhuaad_sanitize_source_links_storage($sourceLinks, 5);
  $sourceLinksJson = json_encode($cleanLinks, JSON_UNESCAPED_UNICODE);
  if ($sourceLinksJson === false) {
    $sourceLinksJson = '[]';
  }

  $update = $pdo->prepare(
    "UPDATE dispute_submissions
     SET title = :title,
         short_description = :short_description,
         category = :category,
         source_links = :source_links,
         expires_at = :expires_at
     WHERE id = :id
     LIMIT 1"
  );
  $update->execute([
    ':id' => $submissionId,
    ':title' => $title,
    ':short_description' => $shortDescription,
    ':category' => $category,
    ':source_links' => $sourceLinksJson,
    ':expires_at' => date('Y-m-d H:i:s', $ts),
  ]);

  if ($update->rowCount() < 1) {
    $check = $pdo->prepare("SELECT id FROM dispute_submissions WHERE id = :id LIMIT 1");
    $check->execute([':id' => $submissionId]);
    if (!$check->fetch()) {
      throw new RuntimeException('NOT_FOUND');
    }
  }
}

/**
 * @throws RuntimeException INVALID_INPUT|NOT_FOUND|ALREADY_REVIEWED|LEGAL_BLOCKED|DUPLICATE_ACTIVE
 */
function farhuaad_admin_accept_dispute_submission(PDO $pdo, int $submissionId, int $adminUserId = 0, string $note = ''): int
{
  if ($submissionId <= 0) {
    throw new RuntimeException('INVALID_INPUT');
  }

  $get = $pdo->prepare(
    "SELECT id, title, short_description, category, source_links, expires_at, status
     FROM dispute_submissions
     WHERE id = :id
     LIMIT 1"
  );
  $get->execute([':id' => $submissionId]);
  $row = $get->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) {
    throw new RuntimeException('NOT_FOUND');
  }
  if ((string)($row['status'] ?? '') !== 'pending') {
    throw new RuntimeException('ALREADY_REVIEWED');
  }

  $sourceLinksRaw = (string)($row['source_links'] ?? '[]');
  $decodedLinks = json_decode($sourceLinksRaw, true);
  $sourceLinks = is_array($decodedLinks) ? $decodedLinks : [];

  $pdo->beginTransaction();
  try {
    $newDisputeId = farhuaad_admin_create_dispute(
      $pdo,
      (string)($row['title'] ?? ''),
      (string)($row['short_description'] ?? ''),
      (string)($row['category'] ?? 'Событие'),
      $sourceLinks,
      (string)($row['expires_at'] ?? ''),
      '',
      ''
    );

    $update = $pdo->prepare(
      "UPDATE dispute_submissions
       SET status = 'approved',
           admin_note = :note,
           reviewed_by = :reviewed_by,
           reviewed_at = NOW(),
           approved_dispute_id = :approved_dispute_id
       WHERE id = :id
       LIMIT 1"
    );
    $update->execute([
      ':id' => $submissionId,
      ':note' => trim($note) !== '' ? trim($note) : null,
      ':reviewed_by' => $adminUserId > 0 ? $adminUserId : null,
      ':approved_dispute_id' => $newDisputeId,
    ]);
    $pdo->commit();
    return $newDisputeId;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    if ($e instanceof RuntimeException) {
      throw $e;
    }
    throw new RuntimeException('INVALID_INPUT');
  }
}

/**
 * @throws RuntimeException INVALID_INPUT|NOT_FOUND|ALREADY_REVIEWED
 */
function farhuaad_admin_reject_dispute_submission(PDO $pdo, int $submissionId, int $adminUserId = 0, string $note = ''): void
{
  if ($submissionId <= 0) {
    throw new RuntimeException('INVALID_INPUT');
  }

  $check = $pdo->prepare("SELECT status FROM dispute_submissions WHERE id = :id LIMIT 1");
  $check->execute([':id' => $submissionId]);
  $row = $check->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row)) {
    throw new RuntimeException('NOT_FOUND');
  }
  if ((string)($row['status'] ?? '') !== 'pending') {
    throw new RuntimeException('ALREADY_REVIEWED');
  }

  $update = $pdo->prepare(
    "UPDATE dispute_submissions
     SET status = 'rejected',
         admin_note = :note,
         reviewed_by = :reviewed_by,
         reviewed_at = NOW()
     WHERE id = :id
     LIMIT 1"
  );
  $update->execute([
    ':id' => $submissionId,
    ':note' => trim($note) !== '' ? trim($note) : null,
    ':reviewed_by' => $adminUserId > 0 ? $adminUserId : null,
  ]);
}

/**
 * @param list<int> $ids
 */
function farhuaad_admin_delete_disputes(PDO $pdo, array $ids): int
{
  $normalized = [];
  foreach ($ids as $id) {
    $value = (int)$id;
    if ($value > 0) {
      $normalized[$value] = true;
    }
  }
  $idList = array_keys($normalized);
  if (!$idList) {
    return 0;
  }

  $placeholders = implode(',', array_fill(0, count($idList), '?'));
  $imageStmt = $pdo->prepare("SELECT image_path FROM disputes WHERE id IN ($placeholders)");
  $imageStmt->execute($idList);
  $rows = $imageStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as $row) {
    $imagePath = trim((string)($row['image_path'] ?? ''));
    if ($imagePath !== '' && str_starts_with($imagePath, '/assets/img/manual/')) {
      $abs = dirname(__DIR__) . '/' . ltrim($imagePath, '/');
      if (is_file($abs)) {
        @unlink($abs);
      }
    }
  }

  $pdo->beginTransaction();
  try {
    $deleteBets = $pdo->prepare("DELETE FROM dispute_bets WHERE dispute_id IN ($placeholders)");
    $deleteBets->execute($idList);
    $deleteDisputes = $pdo->prepare("DELETE FROM disputes WHERE id IN ($placeholders)");
    $deleteDisputes->execute($idList);
    $deleted = (int)$deleteDisputes->rowCount();
    $pdo->commit();
    return $deleted;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

function farhuaad_admin_delete_all_disputes(PDO $pdo): int
{
  $stmt = $pdo->query("SELECT id FROM disputes");
  $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $ids = [];
  foreach ($rows as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id > 0) {
      $ids[] = $id;
    }
  }
  $deleted = farhuaad_admin_delete_disputes($pdo, $ids);

  try {
    $leftStmt = $pdo->query("SELECT COUNT(*) FROM disputes");
    $left = (int)($leftStmt ? $leftStmt->fetchColumn() : 0);
    if ($left === 0) {
      $pdo->exec("ALTER TABLE disputes AUTO_INCREMENT = 1");
      $pdo->exec("ALTER TABLE dispute_bets AUTO_INCREMENT = 1");
    }
  } catch (Throwable $e) {
    // Если БД не дала сбросить счётчик — это не блокирует основное удаление.
  }

  return $deleted;
}

/**
 * Перевод пакета споров RU→EN через Claude (для дозаполнения title_en / short_description_en).
 *
 * @param list<array{id:int,title:string,short_description:string}> $rows
 * @return array<int, array{title_en: string, short_description_en: string}>
 */
function farhuaad_claude_translate_disputes_en_batch(array $rows): array
{
  $apiKey = farhuaad_get_claude_key();
  if ($apiKey === '' || !$rows) {
    return [];
  }

  $payload = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE);
  $prompt = "Translate Russian prediction-market fields to English. Return ONLY a JSON array of objects: "
    . "{\"id\": number, \"title_en\": string, \"short_description_en\": string}.\n"
    . "title_en: preserve yes/no meaning and deadlines from \"title\"; max 255 chars; end with ? if the Russian title ends with ?.\n"
    . 'short_description_en: same facts as "short_description"; match length; max ' . FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN . " chars; neutral factual English.\n"
    . "Input:\n{$payload}";

  $response = farhuaad_http_post_json(
    'https://api.anthropic.com/v1/messages',
    [
      'model' => 'claude-3-5-sonnet-20241022',
      'temperature' => 0.15,
      'max_tokens' => 8192,
      'messages' => [
        ['role' => 'user', 'content' => $prompt],
      ],
    ],
    [
      'x-api-key: ' . $apiKey,
      'anthropic-version: 2023-06-01',
    ],
    75
  );

  $content = farhuaad_claude_response_text($response);
  $decoded = $content !== '' ? farhuaad_extract_json_array($content) : null;

  $out = [];
  if (is_array($decoded)) {
    foreach ($decoded as $item) {
      if (!is_array($item)) {
        continue;
      }
      $id = (int)($item['id'] ?? 0);
      if ($id <= 0) {
        continue;
      }
      $te = trim((string)($item['title_en'] ?? ''));
      $sde = trim((string)($item['short_description_en'] ?? ''));
      if (function_exists('mb_substr')) {
        $te = mb_substr($te, 0, 255);
        $sde = mb_substr($sde, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
      } else {
        $te = substr($te, 0, 255);
        $sde = substr($sde, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
      }
      $out[$id] = ['title_en' => $te, 'short_description_en' => $sde];
    }
  }

  if (!$out && $rows) {
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) {
        continue;
      }
      $te = farhuaad_fallback_translate_ru_to_en((string)($row['title'] ?? ''));
      $sde = farhuaad_fallback_translate_ru_to_en((string)($row['short_description'] ?? ''));
      if (function_exists('mb_substr')) {
        $te = mb_substr($te, 0, 255);
        $sde = mb_substr($sde, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
      } else {
        $te = substr($te, 0, 255);
        $sde = substr($sde, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
      }
      if ($te !== '' || $sde !== '') {
        $out[$id] = ['title_en' => $te, 'short_description_en' => $sde];
      }
      usleep(150000);
    }
  }

  return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 */
function farhuaad_backfill_dispute_en_apply_batch(PDO $pdo, array $rows): int
{
  $batch = [];
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
      continue;
    }
    $batch[] = [
      'id' => $id,
      'title' => (string)($row['title'] ?? ''),
      'short_description' => (string)($row['short_description'] ?? ''),
    ];
  }
  if (!$batch) {
    return 0;
  }

  $map = farhuaad_claude_translate_disputes_en_batch($batch);
  if (!$map) {
    return 0;
  }

  $update = $pdo->prepare(
    "UPDATE disputes
     SET title_en = COALESCE(NULLIF(:title_en, ''), title_en),
         short_description_en = COALESCE(NULLIF(:short_description_en, ''), short_description_en)
     WHERE id = :id"
  );
  $updated = 0;
  foreach ($batch as $item) {
    $id = (int)($item['id'] ?? 0);
    if ($id <= 0 || !isset($map[$id])) {
      continue;
    }
    $te = (string)($map[$id]['title_en'] ?? '');
    $sde = (string)($map[$id]['short_description_en'] ?? '');
    if ($te === '' && $sde === '') {
      continue;
    }
    if ($te !== '' && !farhuaad_dispute_en_translation_save_ok($te, $sde)) {
      continue;
    }
    $update->execute([
      ':title_en' => $te,
      ':short_description_en' => $sde,
      ':id' => $id,
    ]);
    $updated++;
  }

  return $updated;
}

function farhuaad_backfill_dispute_en_fields(PDO $pdo, int $limit = 8): int
{
  if ($limit <= 0) {
    return 0;
  }

  $stmt = $pdo->prepare(
    "SELECT id, title, short_description
     FROM disputes
     WHERE status = 'active'
       AND (title_en IS NULL OR title_en = '')
       AND title IS NOT NULL AND TRIM(title) <> ''
       AND short_description IS NOT NULL AND TRIM(short_description) <> ''
     ORDER BY created_at DESC
     LIMIT :limit"
  );
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();
  if (!is_array($rows) || !$rows) {
    return 0;
  }

  return farhuaad_backfill_dispute_en_apply_batch($pdo, $rows);
}

/**
 * Дозаполнить EN для указанных id (например при открытии карточки на EN).
 *
 * @param list<int> $ids
 */
function farhuaad_backfill_dispute_en_for_ids(PDO $pdo, array $ids): int
{
  $clean = [];
  foreach ($ids as $v) {
    $i = (int)$v;
    if ($i > 0) {
      $clean[$i] = true;
    }
  }
  $idList = array_keys($clean);
  if (!$idList) {
    return 0;
  }

  $placeholders = implode(',', array_fill(0, count($idList), '?'));
  $stmt = $pdo->prepare(
    "SELECT id, title, short_description
     FROM disputes
     WHERE id IN ($placeholders)
       AND status = 'active'
       AND (title_en IS NULL OR title_en = '')
       AND title IS NOT NULL AND TRIM(title) <> ''
       AND short_description IS NOT NULL AND TRIM(short_description) <> ''"
  );
  $stmt->execute($idList);
  $rows = $stmt->fetchAll();
  if (!is_array($rows) || !$rows) {
    return 0;
  }

  return farhuaad_backfill_dispute_en_apply_batch($pdo, $rows);
}

function farhuaad_backfill_missing_dispute_metadata(PDO $pdo, int $limit = 10): int
{
  if ($limit <= 0) {
    return 0;
  }

  $stmt = $pdo->prepare(
    "SELECT id, title, image_path
     FROM disputes
     WHERE status = 'active'
       AND (
         short_description IS NULL OR short_description = ''
         OR category IS NULL OR category = ''
         OR source_links IS NULL OR COALESCE(JSON_LENGTH(source_links), 0) = 0
       )
     ORDER BY created_at DESC
     LIMIT :limit"
  );
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();
  if (!$rows) {
    return 0;
  }

  $titles = [];
  foreach ($rows as $row) {
    $title = trim((string)($row['title'] ?? ''));
    if ($title !== '') {
      $titles[] = $title;
    }
  }
  if (!$titles) {
    return 0;
  }

  $metaByTitle = farhuaad_generate_dispute_meta_with_claude($titles);

  $updated = 0;
  $updateStmt = $pdo->prepare(
    "UPDATE disputes
     SET short_description = :short_description,
         category = :category,
         source_links = :source_links,
         image_path = :image_path,
         title_en = IFNULL(NULLIF(:title_en, ''), title_en),
         short_description_en = IFNULL(NULLIF(:short_description_en, ''), short_description_en)
     WHERE id = :id"
  );

  foreach ($rows as $row) {
    $id = (int)($row['id'] ?? 0);
    $title = trim((string)($row['title'] ?? ''));
    if ($id <= 0 || $title === '') {
      continue;
    }
    $meta = $metaByTitle[$title] ?? farhuaad_build_basic_dispute_meta($title);
    $shortDescription = trim((string)($meta['short_description'] ?? ''));
    $titleEn = trim((string)($meta['title_en'] ?? ''));
    $descriptionEn = trim((string)($meta['short_description_en'] ?? ''));
    if ($shortDescription === '' || farhuaad_is_watery_description($shortDescription)) {
      $fallbackMeta = farhuaad_build_basic_dispute_meta($title);
      $shortDescription = trim((string)($fallbackMeta['short_description'] ?? ''));
      if ($titleEn === '') {
        $titleEn = trim((string)($fallbackMeta['title_en'] ?? ''));
      }
      if ($descriptionEn === '') {
        $descriptionEn = trim((string)($fallbackMeta['short_description_en'] ?? ''));
      }
    }
    if (function_exists('mb_substr')) {
      $shortDescription = mb_substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
      $titleEn = mb_substr($titleEn, 0, 255);
      $descriptionEn = mb_substr($descriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    } else {
      $shortDescription = substr($shortDescription, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
      $titleEn = substr($titleEn, 0, 255);
      $descriptionEn = substr($descriptionEn, 0, FARHUAAD_DISPUTE_DESCRIPTION_MAX_LEN);
    }
    if ($titleEn !== '' && !farhuaad_dispute_en_translation_save_ok($titleEn, $descriptionEn)) {
      $titleEn = '';
      $descriptionEn = '';
    }
    $category = trim((string)($meta['category'] ?? 'Событие'));
    $sourceLinks = $meta['source_links'] ?? [];
    if (!is_array($sourceLinks)) {
      $sourceLinks = [];
    }
    $sourceLinks = farhuaad_sanitize_source_links_storage($sourceLinks, 5);
    if (count($sourceLinks) === 0) {
      $sourceLinks = farhuaad_default_ru_sources($title, 3);
    }
    $sourceLinksJson = json_encode(array_values($sourceLinks), JSON_UNESCAPED_UNICODE);

    $currentImage = trim((string)($row['image_path'] ?? ''));
    $imageHint = trim((string)($meta['image_query'] ?? ''));
    $imagePath = $currentImage;
    if (
      $imagePath === ''
      || str_contains($imagePath, 'picsum.photos')
      || str_contains($imagePath, 'source.unsplash.com')
      || str_contains($imagePath, 'loremflickr.com')
      || str_contains($imagePath, 'image.pollinations.ai')
    ) {
      $imagePath = farhuaad_pick_image_from_commons(
        $title,
        $imageHint !== '' ? $imageHint : $title,
        $shortDescription,
        $category
      );
    }

    $updateStmt->execute([
      ':short_description' => $shortDescription,
      ':category' => $category,
      ':source_links' => $sourceLinksJson !== false ? $sourceLinksJson : '[]',
      ':image_path' => $imagePath,
      ':title_en' => $titleEn,
      ':short_description_en' => $descriptionEn,
      ':id' => $id,
    ]);
    $updated++;
  }

  return $updated;
}

function farhuaad_backfill_pattern_images(PDO $pdo, int $limit = 25): int
{
  if ($limit <= 0) {
    return 0;
  }

  $stmt = $pdo->prepare(
    "SELECT id, image_path
     FROM disputes
     WHERE image_path IS NULL
        OR image_path = ''
        OR image_path LIKE '/api/assets/pattern/%'
        OR image_path LIKE 'api/assets/pattern/%'
        OR image_path NOT LIKE '%/assets/pattern/%'
        OR image_path LIKE '%source.unsplash.com%'
        OR image_path LIKE '%picsum.photos%'
        OR image_path LIKE '%loremflickr.com%'
        OR image_path LIKE '%image.pollinations.ai%'
     ORDER BY created_at DESC
     LIMIT :limit"
  );
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();
  if (!$rows) {
    return 0;
  }

  $update = $pdo->prepare("UPDATE disputes SET image_path = :image_path WHERE id = :id");
  $updated = 0;
  foreach ($rows as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
      continue;
    }
    $update->execute([
      ':image_path' => farhuaad_pick_random_pattern_image(),
      ':id' => $id,
    ]);
    $updated++;
  }

  return $updated;
}

function farhuaad_get_daily_disputes(bool $forceRefresh = false): array
{
  global $pdo;

  if (!($pdo instanceof PDO)) {
    return farhuaad_fallback_disputes_api_slice(3);
  }

  farhuaad_ensure_disputes_table($pdo);
  farhuaad_ensure_dispute_bets_table($pdo);

  $isCronContext = defined('FARHUAAD_CRON_CONTEXT') && FARHUAAD_CRON_CONTEXT === true;
  $allowHeavyJobs = $forceRefresh || $isCronContext;

  if ($forceRefresh || farhuaad_should_run_job('schema_migrate', FARHUAAD_SCHEMA_CHECK_INTERVAL)) {
    farhuaad_migrate_disputes_table($pdo);
    if (function_exists('farhuaad_migrate_users_referral_column')) {
      farhuaad_migrate_users_referral_column($pdo);
    }
    farhuaad_mark_job_run('schema_migrate');
  }
  if ($allowHeavyJobs && ($forceRefresh || farhuaad_should_run_job('claude_resolve', FARHUAAD_NEWS_CHECK_INTERVAL))) {
    farhuaad_resolve_disputes_with_claude($pdo);
    farhuaad_mark_job_run('claude_resolve');
  }
  farhuaad_expire_disputes_by_deadline($pdo);
  farhuaad_settle_dispute_payouts($pdo);

  if (farhuaad_lang() === 'en' && farhuaad_get_claude_key() !== '') {
    try {
      $missStmt = $pdo->query(
        "SELECT COUNT(*) FROM disputes WHERE status = 'active' AND (title_en IS NULL OR title_en = '')"
      );
      $miss = (int)($missStmt ? $missStmt->fetchColumn() : 0);
      if ($miss > 0 && farhuaad_should_run_job('warm_en_feed_request', 12)) {
        farhuaad_backfill_dispute_en_fields($pdo, min(16, $miss));
        farhuaad_mark_job_run('warm_en_feed_request');
      }
    } catch (Throwable $e) {
      // колонок ещё нет или БД недоступна
    }
  }

  $today = farhuaad_disputes_moscow_today();
  $countStmt = $pdo->prepare("SELECT COUNT(*) FROM disputes WHERE source_date = :source_date");
  $countStmt->execute([':source_date' => $today]);
  $createdToday = (int)$countStmt->fetchColumn();

  $active = farhuaad_read_active_disputes($pdo);
  // Каждый московский день — строго до 3 новых споров с source_date = этот день.
  $missingForMoscowDay = max(0, 3 - $createdToday);
  $requiredToGenerate = $missingForMoscowDay;

  $canRunGenerationJob = $forceRefresh
    || ($isCronContext && $requiredToGenerate > 0)
    || farhuaad_should_run_job('generate_disputes', FARHUAAD_GENERATION_RETRY_INTERVAL);
  $allowDisputeGeneration = farhuaad_is_dispute_auto_generation_enabled()
    && ($forceRefresh || ($isCronContext && $requiredToGenerate > 0 && $canRunGenerationJob));

  if ($allowDisputeGeneration && $requiredToGenerate > 0) {
    farhuaad_insert_generated_disputes($pdo, $requiredToGenerate);
    farhuaad_mark_job_run('generate_disputes');
    $active = farhuaad_read_active_disputes($pdo);
  }

  if ($allowHeavyJobs && ($forceRefresh || farhuaad_should_run_job('backfill_dispute_metadata', FARHUAAD_METADATA_BACKFILL_INTERVAL))) {
    farhuaad_backfill_missing_dispute_metadata($pdo, 10);
    farhuaad_mark_job_run('backfill_dispute_metadata');
    $active = farhuaad_read_active_disputes($pdo);
  }

  if ($forceRefresh || farhuaad_should_run_job('backfill_pattern_images', FARHUAAD_PATTERN_BACKFILL_INTERVAL)) {
    farhuaad_backfill_pattern_images($pdo, 50);
    farhuaad_mark_job_run('backfill_pattern_images');
    $active = farhuaad_read_active_disputes($pdo);
  }

  if ($allowHeavyJobs && ($forceRefresh || farhuaad_should_run_job('backfill_watery_descriptions', FARHUAAD_DESCRIPTION_BACKFILL_INTERVAL))) {
    farhuaad_backfill_watery_descriptions($pdo, 30);
    farhuaad_mark_job_run('backfill_watery_descriptions');
    $active = farhuaad_read_active_disputes($pdo);
  }

  if ($allowHeavyJobs && ($forceRefresh || farhuaad_should_run_job('backfill_dispute_en', FARHUAAD_EN_BACKFILL_INTERVAL))) {
    farhuaad_backfill_dispute_en_fields($pdo, 8);
    farhuaad_mark_job_run('backfill_dispute_en');
    $active = farhuaad_read_active_disputes($pdo);
  }

  if (count($active) === 0) {
    return [];
  }

  return $active;
}
