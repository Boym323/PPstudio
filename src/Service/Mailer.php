<?php
declare(strict_types=1);

namespace PPStudio\Service;

require_once __DIR__ . '/../../vendor/phpmailer/PHPMailer-6.10.0/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/PHPMailer-6.10.0/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/PHPMailer-6.10.0/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public function __construct(private array $emailConfig)
    {
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody, ?array $attachment = null): bool
    {
        try {
            $mail = $this->buildConfiguredMailer();
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            if ($attachment !== null) {
                $mail->addStringAttachment(
                    (string) $attachment['content'],
                    (string) $attachment['filename'],
                    PHPMailer::ENCODING_BASE64,
                    (string) $attachment['content_type']
                );
            }

            return $mail->send();
        } catch (Exception) {
            return false;
        }
    }

    public function buildConfiguredMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        $fromEmail = (string) ($this->emailConfig['from_email'] ?? 'noreply@example.com');
        $fromName = (string) ($this->emailConfig['from_name'] ?? \defaultSiteName());
        $replyTo = (string) ($this->emailConfig['reply_to'] ?? $fromEmail);
        $mailerType = (string) ($this->emailConfig['mailer'] ?? 'mail');

        if ($mailerType === 'smtp') {
            $mail->isSMTP();
            $mail->Host = (string) ($this->emailConfig['host'] ?? '');
            $mail->Port = (int) ($this->emailConfig['port'] ?? 587);
            $mail->SMTPAuth = (bool) ($this->emailConfig['auth'] ?? true);
            $mail->Username = (string) ($this->emailConfig['username'] ?? '');
            $mail->Password = (string) ($this->emailConfig['password'] ?? '');
            $encryption = (string) ($this->emailConfig['encryption'] ?? 'tls');

            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($replyTo);

        return $mail;
    }
}
