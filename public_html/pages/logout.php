<?php
require __DIR__ . '/../app/init.php';

farhuaad_logout();
header('Location: ' . farhuaad_url('index.php'));
exit;

