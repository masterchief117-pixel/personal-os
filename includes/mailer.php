<?php

declare(strict_types=1);

function sendInvitationEmail(string $recipient, string $acceptUrl, string $role): string
{
    $host = trim((string) (getenv('MAIL_HOST') ?: ''));
    $portValue = trim((string) (getenv('MAIL_PORT') ?: ''));
    $username = (string) (getenv('MAIL_USERNAME') ?: '');
    $password = (string) (getenv('MAIL_PASSWORD') ?: '');
    $encryption = strtolower(trim((string) (getenv('MAIL_ENCRYPTION') ?: '')));
    $fromAddress = trim((string) (getenv('MAIL_FROM_ADDRESS') ?: ''));
    $fromName = trim((string) (getenv('MAIL_FROM_NAME') ?: 'Personal OS'));

    if ($host === '' || $portValue === '' || $username === '' || $password === '' ||
        $encryption === '' || $fromAddress === '') {
        return 'not_configured';
    }

    $port = filter_var($portValue, FILTER_VALIDATE_INT);
    if ($port === false || $port < 1 || $port > 65535 ||
        !in_array($encryption, ['tls', 'ssl'], true) ||
        filter_var($recipient, FILTER_VALIDATE_EMAIL) === false ||
        filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false ||
        preg_match('/[\r\n]/', $host . $username . $fromName) === 1) {
        return 'invalid_configuration';
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('Invitation email cannot be sent because Composer dependencies are missing.');
        return 'not_configured';
    }
    require_once $autoload;

    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->Port = $port;
    $mailer->SMTPAuth = true;
    $mailer->Username = $username;
    $mailer->Password = $password;
    $mailer->SMTPSecure = $encryption === 'ssl'
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->SMTPAutoTLS = $encryption === 'tls';
    $mailer->Timeout = 10;
    $mailer->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
    $mailer->setFrom($fromAddress, $fromName);
    $mailer->addAddress($recipient);
    $mailer->Subject = 'You have been invited to Personal OS';
    $mailer->Body = "You have been invited to Personal OS as a {$role}.\n\n"
        . "Open this one-time link to accept your invitation within seven days:\n"
        . $acceptUrl . "\n";

    try {
        $mailer->send();
        return 'sent';
    } catch (PHPMailer\PHPMailer\Exception $exception) {
        error_log('Invitation email delivery failed: ' . $exception->getMessage());
        return 'failed';
    }
}
