<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/env.php';
load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

ini_set('expose_php', '0');

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/api_http.php';
farhuaad_security_headers();

$appEnv = strtolower((string)($_ENV['APP_ENV'] ?? 'production'));
$isDebug = in_array($appEnv, ['local', 'dev', 'development'], true);

ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('display_startup_errors', $isDebug ? '1' : '0');

require_once __DIR__.'/db.php';
require_once __DIR__.'/security.php';
require_once __DIR__.'/otp.php';
require_once __DIR__.'/email.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/disputes.php';
require_once __DIR__ . '/dispute_chat.php';
require_once __DIR__ . '/platform.php';
require_once __DIR__ . '/user_delete.php';
require_once __DIR__ . '/balance_reset.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  $sessionLifetime = 60 * 60 * 24 * 30;
  ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
  session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

if (function_exists('farhuaad_capture_referral_from_request')) {
  farhuaad_capture_referral_from_request();
}

require_once __DIR__ . '/i18n.php';
farhuaad_init_i18n();

if (function_exists('farhuaad_touch_presence')) {
  farhuaad_touch_presence();
}

if (function_exists('farhuaad_run_user_delete_garbage_collector')) {
  farhuaad_run_user_delete_garbage_collector();
}

if (function_exists('farhuaad_auto_reset_balances_if_due')) {
  farhuaad_auto_reset_balances_if_due();
}

function farhuaad_base_url(): string {
  $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  $script = str_replace('\\', '/', $script);
  $dir = rtrim(dirname($script), '/');

  // If current script is inside /pages or /api, go one level up.
  if (preg_match('#/(pages|api)$#', $dir)) {
    $dir = preg_replace('#/(pages|api)$#', '', $dir) ?? $dir;
  }

  // For web root, dirname() may return '/'.
  if ($dir === '/' || $dir === '\\') {
    return '';
  }

  return $dir;
}

function farhuaad_url(string $path): string {
  $base = farhuaad_base_url();
  $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
  if (strcasecmp($path, '/index.php') === 0) {
    return $base === '' ? '/' : $base . '/';
  }
  return $base . $path;
}

/**
 * URL к файлу в корне сайта с ?v=mtime — можно отдавать CSS/JS с длинным Cache-Control без «залипания» старой версии.
 */
function farhuaad_asset_url(string $relativePath): string {
  $relativePath = str_replace('\\', '/', $relativePath);
  $relativePath = ltrim($relativePath, '/');
  if ($relativePath === '' || str_contains($relativePath, '..')) {
    return farhuaad_url($relativePath);
  }
  $full = dirname(__DIR__) . '/' . $relativePath;
  if (!is_file($full)) {
    return farhuaad_url($relativePath);
  }
  $url = farhuaad_url($relativePath);
  return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode((string)filemtime($full));
}

/**
 * Разрешённый относительный путь после входа (только pages/*.php), без open-redirect.
 */
function farhuaad_validate_login_next(string $next): ?string {
  $next = trim($next);
  if ($next === '') {
    return null;
  }
  if (preg_match('/[\r\n\0]/', $next)) {
    return null;
  }
  if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $next)) {
    return null;
  }
  $path = ltrim($next, '/');
  $baseTrim = ltrim(farhuaad_base_url(), '/');
  if ($baseTrim !== '' && str_starts_with($path, $baseTrim . '/')) {
    $path = substr($path, strlen($baseTrim) + 1);
  }
  if (!preg_match('#^pages/[a-zA-Z0-9_.-]+\.php(\?[a-zA-Z0-9_=&.%+-]*)?$#', $path)) {
    return null;
  }
  return $path;
}

function farhuaad_redirect_url_for_login_next(string $next): string {
  $validated = farhuaad_validate_login_next($next);
  if ($validated === null) {
    return farhuaad_url('pages/portfolio.php');
  }
  $qPos = strpos($validated, '?');
  $file = $qPos === false ? $validated : substr($validated, 0, $qPos);
  $qs = $qPos === false ? '' : substr($validated, $qPos);
  return farhuaad_url($file) . $qs;
}

