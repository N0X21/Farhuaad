<?php
declare(strict_types=1);

/**
 * Единый JSON-ответ для API и JSON-страниц.
 *
 * @param array<string, mixed> $payload
 */
function farhuaad_json_response(int $httpStatus, array $payload): never
{
  if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
  }
  http_response_code($httpStatus);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function farhuaad_is_production_like(): bool
{
  $env = strtolower((string)($_ENV['APP_ENV'] ?? 'production'));
  return !in_array($env, ['local', 'dev', 'development'], true);
}

/**
 * Логирует исключение и отдаёт безопасное тело (без стека в проде).
 */
function farhuaad_json_server_error(Throwable $e, string $publicCode = 'INTERNAL_ERROR'): never
{
  error_log('[farhuaad] ' . ($e->getFile() ?? '') . ':' . ($e->getLine() ?? 0) . ' ' . $e->getMessage());
  $payload = ['ok' => false, 'error' => $publicCode];
  if (!farhuaad_is_production_like()) {
    $payload['detail'] = $e->getMessage();
  }
  farhuaad_json_response(500, $payload);
}
