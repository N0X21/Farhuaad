<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/error_bootstrap.php';
require_once __DIR__ . '/../app/security_headers.php';

$code = (int)($_GET['c'] ?? $_SERVER['REDIRECT_STATUS'] ?? 0);
if ($code < 400 || $code > 599) {
  $code = 404;
}

http_response_code($code);
farhuaad_security_headers();
header('X-Robots-Tag: noindex, nofollow', true);

$lang = farhuaad_error_lang();
$isEn = $lang === 'en';

$titles = [
  400 => ['ru' => 'Некорректный запрос', 'en' => 'Bad request'],
  401 => ['ru' => 'Нужна авторизация', 'en' => 'Authorization required'],
  403 => ['ru' => 'Доступ запрещён', 'en' => 'Forbidden'],
  404 => ['ru' => 'Страница не найдена', 'en' => 'Page not found'],
  500 => ['ru' => 'Ошибка сервера', 'en' => 'Server error'],
  502 => ['ru' => 'Плохой шлюз', 'en' => 'Bad gateway'],
  503 => ['ru' => 'Сервис недоступен', 'en' => 'Service unavailable'],
];
if (isset($titles[$code])) {
  $h1 = $isEn ? $titles[$code]['en'] : $titles[$code]['ru'];
} else {
  $h1 = $isEn ? 'Something went wrong' : 'Что-то пошло не так';
}

$hints = [
  400 => [
    'ru' => 'Сервер не смог обработать запрос в таком виде.',
    'en' => 'The server could not understand this request.',
  ],
  401 => [
    'ru' => 'Войдите в аккаунт, чтобы продолжить.',
    'en' => 'Please sign in to continue.',
  ],
  403 => [
    'ru' => 'У вас нет прав на этот ресурс.',
    'en' => 'You do not have permission to access this resource.',
  ],
  404 => [
    'ru' => 'Ссылка устарела, или страница ещё не создана.',
    'en' => 'The link may be outdated, or the page does not exist.',
  ],
  500 => [
    'ru' => 'Мы уже разбираемся. Попробуйте чуть позже.',
    'en' => 'We are working on it. Please try again later.',
  ],
  502 => [
    'ru' => 'Промежуточный сервер ответил с ошибкой. Попробуйте позже.',
    'en' => 'An upstream server returned an error. Try again later.',
  ],
  503 => [
    'ru' => 'Технический перерыв. Загляните через несколько минут.',
    'en' => 'Temporary downtime. Please check back shortly.',
  ],
];
if (isset($hints[$code])) {
  $hint = $isEn ? $hints[$code]['en'] : $hints[$code]['ru'];
} else {
  $hint = $isEn ? 'Please return to the home page.' : 'Вернитесь на главную страницу.';
}

/** Цитаты — лица с банкнот USD (стилизация, без изображений купюр). */
$quotes = [
  [
    'denom' => '$1',
    'author' => ['ru' => 'Джордж Вашингтон', 'en' => 'George Washington'],
    'text' => [
      'ru' => '«Лучше не давать никакого оправдания, чем давать плохое.»',
      'en' => '«It is better to offer no excuse than a bad one.»',
    ],
  ],
  [
    'denom' => '$2',
    'author' => ['ru' => 'Томас Джефферсон', 'en' => 'Thomas Jefferson'],
    'text' => [
      'ru' => '«Я не могу жить без книг.»',
      'en' => '«I cannot live without books.»',
    ],
  ],
  [
    'denom' => '$5',
    'author' => ['ru' => 'Авраам Линкольн', 'en' => 'Abraham Lincoln'],
    'text' => [
      'ru' => '«Кем бы ты ни был, будь лучшим в этом.»',
      'en' => '«Whatever you are, be a good one.»',
    ],
  ],
  [
    'denom' => '$10',
    'author' => ['ru' => 'Александр Гамильтон', 'en' => 'Alexander Hamilton'],
    'text' => [
      'ru' => '«Тот, кто ни за что не стоит, готов поверить во что угодно.»',
      'en' => '«Those who stand for nothing fall for anything.»',
    ],
  ],
  [
    'denom' => '$20',
    'author' => ['ru' => 'Эндрю Джексон', 'en' => 'Andrew Jackson'],
    'text' => [
      'ru' => '«Обдумывай спокойно, но когда пришло время действовать — действуй, не колеблясь.»',
      'en' => '«Take time to deliberate; but when the time for action arrives, stop thinking and go in.»',
    ],
  ],
  [
    'denom' => '$50',
    'author' => ['ru' => 'Улисс Грант', 'en' => 'Ulysses S. Grant'],
    'text' => [
      'ru' => '«Всякий раз, в каждой битве приходит момент, когда обе стороны считают себя побеждёнными; тот, кто продолжает наступление, побеждает.»',
      'en' => '«In every battle there comes a time when both sides consider themselves beaten; then he who continues the attack wins.»',
    ],
  ],
  [
    'denom' => '$100',
    'author' => ['ru' => 'Бенджамин Франклин', 'en' => 'Benjamin Franklin'],
    'text' => [
      'ru' => '«Инвестиции в знания дают наибольший процент.»',
      'en' => '«An investment in knowledge pays the best interest.»',
    ],
  ],
];

$seed = (string)($code . ($_SERVER['REQUEST_URI'] ?? '') . ($_SERVER['HTTP_HOST'] ?? ''));
$pick = abs(crc32($seed)) % count($quotes);
$q = $quotes[$pick];
$quoteText = $isEn ? $q['text']['en'] : $q['text']['ru'];
$author = $isEn ? $q['author']['en'] : $q['author']['ru'];

$homeUrl = htmlspecialchars(farhuaad_error_url('index.php'), ENT_QUOTES, 'UTF-8');
$cssUrl = htmlspecialchars(farhuaad_error_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8');
$htmlLang = $isEn ? 'en' : 'ru';
$metaTitle = $isEn ? "Error {$code} — Farhuaad" : "Ошибка {$code} — Farhuaad";
$back = $isEn ? 'Home' : 'На главную';
$codeLabel = $isEn ? 'Code' : 'Код';
?>
<!DOCTYPE html>
<html lang="<?php echo $htmlLang; ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex,nofollow" />
  <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="<?php echo $cssUrl; ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style<?php echo farhuaad_csp_nonce_attr(); ?>>
    .error-bill-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px max(20px, env(safe-area-inset-right)) max(32px, env(safe-area-inset-bottom))
        max(20px, env(safe-area-inset-left));
      background: #000;
      color: var(--text-main);
    }
    .error-bill-wrap {
      width: 100%;
      max-width: 520px;
      position: relative;
    }
    .error-bill {
      position: relative;
      border-radius: 18px;
      border: 1px solid rgba(74, 222, 128, 0.35);
      background: linear-gradient(165deg, rgba(20, 83, 45, 0.25) 0%, rgba(0, 0, 0, 0.92) 45%, #000 100%);
      box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.06) inset,
        0 24px 48px rgba(0, 0, 0, 0.65);
      padding: 28px 26px 24px;
      overflow: hidden;
    }
    .error-bill::before {
      content: "<?php echo htmlspecialchars($q['denom'], ENT_QUOTES, 'UTF-8'); ?>";
      position: absolute;
      right: -8px;
      top: 50%;
      transform: translateY(-50%) rotate(-12deg);
      font-size: clamp(6rem, 28vw, 9rem);
      font-weight: 700;
      color: rgba(74, 222, 128, 0.07);
      line-height: 1;
      pointer-events: none;
      user-select: none;
    }
    .error-bill-corners {
      position: absolute;
      inset: 10px;
      border: 1px solid rgba(134, 239, 172, 0.12);
      border-radius: 12px;
      pointer-events: none;
    }
    .error-bill-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: rgba(187, 247, 208, 0.85);
      margin-bottom: 14px;
    }
    .error-bill-badge span {
      opacity: 0.7;
    }
    .error-bill-code {
      font-size: clamp(2.5rem, 10vw, 3.5rem);
      font-weight: 700;
      color: #fff;
      line-height: 1;
      margin: 0 0 8px;
    }
    .error-bill-h1 {
      font-size: 1.15rem;
      font-weight: 600;
      color: #e5e7eb;
      margin: 0 0 10px;
    }
    .error-bill-hint {
      font-size: 0.9rem;
      color: #9ca3af;
      line-height: 1.5;
      margin: 0 0 22px;
    }
    .error-bill-quote {
      position: relative;
      z-index: 1;
      margin: 0;
      padding: 16px 14px 16px 18px;
      border-left: 3px solid rgba(74, 222, 128, 0.55);
      background: rgba(0, 0, 0, 0.45);
      border-radius: 0 12px 12px 0;
    }
    .error-bill-quote p {
      margin: 0 0 12px;
      font-size: 1rem;
      line-height: 1.55;
      color: #f3f4f6;
      font-style: italic;
    }
    .error-bill-quote footer {
      font-size: 0.85rem;
      color: #86efac;
      font-style: normal;
    }
    .error-bill-quote footer cite {
      font-style: normal;
      font-weight: 600;
    }
    .error-bill-quote .denom {
      color: rgba(187, 247, 208, 0.65);
      font-weight: 500;
    }
    .error-bill-actions {
      margin-top: 22px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      position: relative;
      z-index: 1;
    }
    .error-bill-actions .btn {
      border-radius: 999px;
    }
  </style>
</head>
<body class="error-bill-page">
  <div class="error-bill-wrap">
    <article class="error-bill" aria-labelledby="error-title">
      <div class="error-bill-corners" aria-hidden="true"></div>
      <div class="error-bill-badge">
        Farhuaad <span aria-hidden="true">·</span> <span><?php echo htmlspecialchars($codeLabel, ENT_QUOTES, 'UTF-8'); ?> <?php echo (int)$code; ?></span>
      </div>
      <p class="error-bill-code" aria-hidden="true"><?php echo (int)$code; ?></p>
      <h1 class="error-bill-h1" id="error-title"><?php echo htmlspecialchars($h1, ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="error-bill-hint"><?php echo htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?></p>
      <blockquote class="error-bill-quote">
        <p><?php echo htmlspecialchars($quoteText, ENT_QUOTES, 'UTF-8'); ?></p>
        <footer>
          <cite><?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></cite>
          <span class="denom"> · <?php echo htmlspecialchars($q['denom'], ENT_QUOTES, 'UTF-8'); ?></span>
        </footer>
      </blockquote>
      <div class="error-bill-actions">
        <a class="btn btn-primary" href="<?php echo $homeUrl; ?>"><?php echo htmlspecialchars($back, ENT_QUOTES, 'UTF-8'); ?></a>
      </div>
    </article>
  </div>
</body>
</html>
