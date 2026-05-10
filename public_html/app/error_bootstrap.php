<?php
declare(strict_types=1);

/**
 * Минимальная загрузка для страниц ошибок (без БД и тяжёлых зависимостей).
 */
function farhuaad_error_base_url(): string
{
  $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  $script = str_replace('\\', '/', $script);
  $dir = rtrim(dirname($script), '/');

  if (preg_match('#/(pages|api)$#', $dir)) {
    $dir = preg_replace('#/(pages|api)$#', '', $dir) ?? $dir;
  }

  if ($dir === '/' || $dir === '\\') {
    return '';
  }

  return $dir;
}

function farhuaad_error_url(string $path): string
{
  $base = farhuaad_error_base_url();
  $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
  if (strcasecmp($path, '/index.php') === 0) {
    return $base === '' ? '/' : $base . '/';
  }
  return $base . $path;
}

function farhuaad_error_lang(): string
{
  $c = (string)($_COOKIE['farhuaad_lang'] ?? '');
  return $c === 'en' ? 'en' : 'ru';
}
