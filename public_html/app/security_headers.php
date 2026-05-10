<?php
declare(strict_types=1);

/**
 * HTML-страницы (не /api/) получают CSP с одним nonce на скрипты и inline-стили.
 */
function farhuaad_csp_nonce(): string
{
  static $n = null;
  if ($n === null) {
    $n = bin2hex(random_bytes(16));
  }
  return $n;
}

/** Атрибут для тегов <script> и <style>: nonce="..." */
function farhuaad_csp_nonce_attr(): string
{
  return ' nonce="' . htmlspecialchars(farhuaad_csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
}

function farhuaad_sends_html_content_security_policy(): bool
{
  $s = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  return !str_contains($s, '/api/');
}

/**
 * Базовые заголовки; для HTML — Content-Security-Policy (nonce для script/style).
 */
function farhuaad_security_headers(): void
{
  if (headers_sent()) {
    return;
  }

  header('X-Frame-Options: SAMEORIGIN');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()');

  if (!farhuaad_sends_html_content_security_policy()) {
    return;
  }

  $nonce = farhuaad_csp_nonce();
  $directives = [
    "default-src 'self'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'self'",
    "object-src 'none'",
    'upgrade-insecure-requests',
    "script-src 'self' 'nonce-{$nonce}' https://www.google.com https://www.gstatic.com",
    "style-src 'self' 'nonce-{$nonce}' 'unsafe-inline' https://fonts.googleapis.com",
    "font-src 'self' https://fonts.gstatic.com data:",
    "img-src 'self' data: https: blob:",
    "connect-src 'self' https://www.google.com https://www.gstatic.com",
    'frame-src https://www.google.com',
  ];
  header('Content-Security-Policy: ' . implode('; ', $directives));
}
