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

global $pdo;
if (!($pdo instanceof PDO)) {
  header('Location: ' . farhuaad_url('pages/profile.php'));
  exit;
}

$uid = (int)$user['id'];
try {
  $stmt = $pdo->prepare('DELETE FROM wallets WHERE user_id = ?');
  $stmt->execute([$uid]);
} catch (Throwable $e) {
  // ignore DB errors, just redirect back
}

header('Location: ' . farhuaad_url('pages/profile.php'));
exit;

