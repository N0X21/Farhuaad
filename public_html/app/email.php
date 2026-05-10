<?php
declare(strict_types=1);

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function farhuaad_send_email(string $to, string $subject, string $body): bool {

  $mail = new PHPMailer(true);

  try {

    $mail->isSMTP();

    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->SMTPKeepAlive = true;

    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int)$_ENV['SMTP_PORT'];

    $mail->CharSet = 'UTF-8';

    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_NAME']);
    $mail->addAddress($to);

    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();

    return true;

  } catch (Exception $e) {

    return false;

  }
}