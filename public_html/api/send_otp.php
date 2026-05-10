<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  farhuaad_json_response(405, ['ok' => false, 'success' => false, 'error' => 'METHOD_NOT_ALLOWED']);
}

$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$purpose = isset($_POST['purpose']) ? (string)$_POST['purpose'] : 'register';
if ($purpose !== 'login' && $purpose !== 'register') {
  $purpose = 'register';
}

try {
  farhuaad_verify_csrf(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null);
  farhuaad_send_otp($email, $purpose);
  farhuaad_json_response(200, ['ok' => true, 'success' => true]);
} catch (RuntimeException $e) {
  farhuaad_json_response(400, ['ok' => false, 'success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'REQUEST_FAILED');
}
