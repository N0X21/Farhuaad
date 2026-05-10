<?php
require __DIR__ . '/../app/init.php';

$nextRaw = (string)($_GET['next'] ?? '');
$target = farhuaad_url('pages/register.php');
if ($nextRaw !== '') {
  $target .= '?next=' . rawurlencode($nextRaw);
}

header('Location: ' . $target);
exit;
