<?php
require __DIR__ . '/../app/init.php';

$user = farhuaad_current_user();
if (!$user || !isset($user['id'])) {
  header('Location: ' . farhuaad_url('pages/login.php'));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . farhuaad_url('pages/profile.php'));
  exit;
}

try {
  farhuaad_verify_csrf(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null);
} catch (Throwable $e) {
  header('Location: ' . farhuaad_url('pages/profile.php'));
  exit;
}

farhuaad_schedule_user_delete((string)$user['id']);
farhuaad_logout();

header('Location: ' . farhuaad_url('index.php'));
exit;

