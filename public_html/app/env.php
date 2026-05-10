<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME'])) {
  $scriptFile = realpath((string)$_SERVER['SCRIPT_FILENAME']);
  $thisFile = realpath(__FILE__);
  if ($scriptFile && $thisFile && $scriptFile === $thisFile) {
    header('Content-Type: text/plain; charset=UTF-8', true, 403);
    exit('Forbidden');
  }
}

function load_env(string $path): void
{
  $resolved = realpath($path);
  if ($resolved === false || !is_file($resolved)) {
    return;
  }

  $lines = file($resolved, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($lines === false) {
    return;
  }

  foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) {
      continue;
    }

    if (!str_contains($line, '=')) {
      continue;
    }

    [$name, $value] = explode('=', $line, 2);

    $name = trim($name);
    if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
      continue;
    }
    $value = trim($value);
    $value = trim($value, "\"'");

    $_ENV[$name] = $value;
    putenv("{$name}={$value}");
  }
}
