<?php
require_once __DIR__ . '/env.php';

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

use PHPMailer\PHPMailer\PHPMailer;

function isEmailDeliveryConfigured() {
    foreach (['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'MAIL_FROM'] as $setting) {
        if (getenv($setting) === false || getenv($setting) === '') {
            return false;
        }
    }
    return true;
}

function sendConfiguredEmail($recipient, $recipientName, $subject, $htmlBody, $textBody) {
    $required = ['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'MAIL_FROM'];
    foreach ($required as $setting) {
        if (getenv($setting) === false || getenv($setting) === '') {
            error_log('Email notification is not configured: missing ' . $setting);
            return false;
        }
    }

    if (!class_exists(PHPMailer::class)) {
        error_log('Email notification is not configured: PHPMailer missing. Run "composer install" on the server.');
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USERNAME');
        $mail->Password = getenv('SMTP_PASSWORD');
        $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
        $encryption = strtolower((string)(getenv('SMTP_ENCRYPTION') ?: 'tls'));
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption !== 'none') {
            throw new RuntimeException('SMTP_ENCRYPTION must be tls, ssl, or none.');
        }

        $from = getenv('MAIL_FROM');
        $fromName = getenv('MAIL_FROM_NAME') ?: 'RiRa Cakes';
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($from, $fromName);
        $mail->addAddress($recipient, $recipientName ?: $recipient);
        $mail->addReplyTo($from, $fromName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Email notification failed: ' . $e->getMessage());
        return false;
    }
}

function sendAdminNotificationEmail($subject, $htmlBody, $textBody) {
    $recipient = getenv('ADMIN_NOTIFICATION_EMAIL');
    if (!$recipient) {
        error_log('Email notification is not configured: missing ADMIN_NOTIFICATION_EMAIL');
        return false;
    }
    return sendConfiguredEmail($recipient, 'Admin RiRa Cakes', $subject, $htmlBody, $textBody);
}

function emailEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
