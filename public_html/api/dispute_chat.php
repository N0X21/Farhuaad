<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/dispute_chat.php';

try {
  if (!($pdo instanceof PDO)) {
    throw new RuntimeException('DB_NOT_AVAILABLE');
  }

  farhuaad_ensure_dispute_chat_table($pdo);

  $disputeId = (int)($_GET['dispute_id'] ?? $_GET['id'] ?? 0);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    farhuaad_rate_limit_request('dispute_chat_get', 90, 60);
    if ($disputeId <= 0) {
      throw new RuntimeException('INVALID_ID');
    }
    if (!farhuaad_dispute_chat_dispute_is_open($pdo, $disputeId)) {
      $exists = $pdo->prepare('SELECT id FROM disputes WHERE id = ? LIMIT 1');
      $exists->execute([$disputeId]);
      if (!$exists->fetchColumn()) {
        throw new RuntimeException('NOT_FOUND');
      }
    }
    $viewer = farhuaad_current_user();
    $viewerId = (int)($viewer['id'] ?? 0);
    $messages = farhuaad_dispute_chat_fetch_messages($pdo, $disputeId, 120, $viewerId);
    farhuaad_json_response(200, ['ok' => true, 'messages' => $messages]);
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    farhuaad_json_response(405, ['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
  }

  $user = farhuaad_current_user();
  $userId = (int)($user['id'] ?? 0);
  if ($userId <= 0) {
    throw new RuntimeException('UNAUTHORIZED');
  }

  $raw = file_get_contents('php://input');
  $data = json_decode((string)$raw, true);
  if (!is_array($data)) {
    $data = $_POST;
  }

  farhuaad_verify_csrf_from_json_or_header(is_array($data) ? $data : null);

  $disputeId = (int)($data['dispute_id'] ?? $data['id'] ?? 0);
  $action = trim((string)($data['action'] ?? ''));

  if ($action === 'delete') {
    farhuaad_rate_limit_request('dispute_chat_delete', 40, 60);
    if ($disputeId <= 0) {
      throw new RuntimeException('INVALID_ID');
    }
    $messageId = (int)($data['message_id'] ?? 0);
    if ($messageId <= 0) {
      throw new RuntimeException('INVALID_INPUT');
    }
    farhuaad_dispute_chat_delete_own_message($pdo, $disputeId, $messageId, $userId);
    farhuaad_json_response(200, ['ok' => true]);
  }

  farhuaad_rate_limit_request('dispute_chat_post', 25, 60);
  $body = (string)($data['body'] ?? '');
  $msg = farhuaad_dispute_chat_post_message($pdo, $disputeId, $userId, $body);
  farhuaad_json_response(200, ['ok' => true, 'message' => $msg]);
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'RATE_LIMIT') {
    farhuaad_json_response(429, ['ok' => false, 'error' => 'RATE_LIMIT']);
  }
  if ($e->getMessage() === 'CSRF_FAILED') {
    farhuaad_json_response(403, ['ok' => false, 'error' => 'CSRF_FAILED']);
  }
  $code = $e->getMessage();
  $map = [
    'UNAUTHORIZED' => 401,
    'NOT_FOUND' => 404,
    'INVALID_INPUT' => 400,
    'INVALID_ID' => 400,
    'DISPUTE_CLOSED' => 403,
    'CHAT_ENCRYPT_FAILED' => 500,
  ];
  $http = $map[$code] ?? 400;
  farhuaad_json_response($http, ['ok' => false, 'error' => $code]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'DISPUTE_CHAT_FAILED');
}
