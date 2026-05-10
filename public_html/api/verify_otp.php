<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  farhuaad_json_response(405, ['ok' => false, 'success' => false, 'error' => 'METHOD_NOT_ALLOWED']);
}

$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$code = isset($_POST['code']) ? (string)$_POST['code'] : '';
$purpose = isset($_POST['purpose']) ? (string)$_POST['purpose'] : 'register';
if ($purpose !== 'login' && $purpose !== 'register') {
  $purpose = 'register';
}

try {
  farhuaad_rate_limit_request('verify_otp', 12, 60);
  farhuaad_verify_csrf(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null);
  farhuaad_verify_otp($email, $code, $purpose);

  session_regenerate_id(true);

  if ($purpose === 'register') {
    $u = farhuaad_register_email_user($email);
    farhuaad_set_current_user([
      'id' => (string)$u['id'],
      'name' => (string)$u['name'],
      'email' => (string)$u['email'],
      'authMethod' => 'email',
    ]);
  } else {
    $u = farhuaad_login_email($email);
    farhuaad_set_current_user($u);
  }

  farhuaad_json_response(200, ['ok' => true, 'success' => true]);
} catch (RuntimeException $e) {
  $msg = $e->getMessage();
  $status = $msg === 'RATE_LIMIT' ? 429 : 400;
  farhuaad_json_response($status, ['ok' => false, 'success' => false, 'error' => $msg]);
} catch (Throwable $e) {
  farhuaad_json_server_error($e, 'REQUEST_FAILED');
}
