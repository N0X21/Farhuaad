<?php
declare(strict_types=1);

require __DIR__ . '/../app/init.php';

$lang = (string)($_GET['lang'] ?? '');
if ($lang !== 'ru' && $lang !== 'en') {
  header('Location: ' . farhuaad_url('index.php'));
  exit;
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
setcookie('farhuaad_lang', $lang, [
  'expires' => time() + 365 * 86400,
  'path' => '/',
  'secure' => $https,
  'httponly' => false,
  'samesite' => 'Lax',
]);

$target = farhuaad_url('index.php');
$ref = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
if ($ref !== '') {
  $refHost = parse_url($ref, PHP_URL_HOST);
  $selfHost = $_SERVER['HTTP_HOST'] ?? '';
  if ($refHost && $selfHost && strcasecmp((string)$refHost, (string)$selfHost) === 0) {
    $target = $ref;
  }
}

header('Location: ' . $target);
exit;
