<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envoi synchrone via PHPMailer : mode fichier (dev) ou SMTP (fournisseurs type SendGrid/Mailgun/SES en relais SMTP).
 */
final class EmailTransportResolver
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct()
    {
        $this->config = \email_config();
    }

    /**
     * @return array{ok: bool, transport: string, error?: string, provider_id?: string}
     */
    public function sendMessage(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        ?string $replyTo = null
    ): array {
        $fromEmail = (string) ($this->config['from_address'] ?? 'noreply@localhost');
        $fromName = (string) ($this->config['from_name'] ?? '');
        $effectiveReply = $replyTo;
        if ($effectiveReply === null || trim($effectiveReply) === '') {
            $cfgRt = $this->config['reply_to'] ?? null;
            if ($cfgRt !== null && trim((string) $cfgRt) !== '') {
                $effectiveReply = trim((string) $cfgRt);
            }
        }

        if (!class_exists(PHPMailer::class)) {
            error_log('EmailTransportResolver: PHPMailer absent — exécuter `composer install` à la racine du projet (dépendance phpmailer/phpmailer).');

            return [
                'ok' => false,
                'transport' => 'phpmailer',
                'error' => 'Service e-mail indisponible sur le serveur (dépendances manquantes).',
            ];
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;

        try {
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);
            if ($effectiveReply !== null && $effectiveReply !== '') {
                $mail->addReplyTo($effectiveReply);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'transport' => 'phpmailer', 'error' => $e->getMessage()];
        }

        $mailerType = strtolower((string) ($this->config['default_mailer'] ?? 'file'));

        if ($mailerType === 'file') {
            return $this->sendToFile($mail);
        }

        try {
            $this->configureSmtp($mail, $mailerType);
            $mail->send();

            return ['ok' => true, 'transport' => $mailerType, 'provider_id' => null];
        } catch (\Throwable $e) {
            $err = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();

            return ['ok' => false, 'transport' => $mailerType, 'error' => $err];
        }
    }

    /**
     * @return array{ok: bool, transport: string, error?: string, provider_id?: string}
     */
    private function sendToFile(PHPMailer $mail): array
    {
        $dir = (string) ($this->config['file_path'] ?? dirname(__DIR__, 2) . '/storage/mail-outbox');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.eml';
        $path = $dir . DIRECTORY_SEPARATOR . $name;

        $mail->Mailer = 'mail';
        if (!$mail->preSend()) {
            return ['ok' => false, 'transport' => 'file', 'error' => $mail->ErrorInfo ?: 'preSend failed'];
        }
        $raw = $mail->getSentMIMEMessage();
        if (@file_put_contents($path, $raw) === false) {
            return ['ok' => false, 'transport' => 'file', 'error' => 'Impossible d\'écrire ' . $path];
        }

        return ['ok' => true, 'transport' => 'file', 'provider_id' => $name];
    }

    private function configureSmtp(PHPMailer $mail, string $mailerType): void
    {
        $mail->isSMTP();
        $smtp = $this->config['smtp'] ?? [];
        $host = trim((string) ($smtp['host'] ?? ''));
        $port = (int) ($smtp['port'] ?? 587);
        $user = trim((string) ($smtp['username'] ?? ''));
        $pass = trim((string) ($smtp['password'] ?? ''));
        $enc = strtolower((string) ($smtp['encryption'] ?? 'tls'));

        if ($host === '' && in_array($mailerType, ['sendgrid', 'mailgun', 'ses'], true)) {
            $host = match ($mailerType) {
                'sendgrid' => 'smtp.sendgrid.net',
                'mailgun' => 'smtp.mailgun.org',
                'ses' => trim((string) \env('AWS_SES_SMTP_HOST', 'email-smtp.eu-west-1.amazonaws.com')),
                default => '',
            };
        }
        if ($host === '') {
            $host = '127.0.0.1';
        }

        $mail->Host = $host;
        $mail->Port = $port > 0 ? $port : 587;
        $timeout = (int) ($smtp['timeout'] ?? 30);
        $mail->Timeout = $timeout > 0 ? $timeout : 30;
        $mail->SMTPAuth = ($user !== '' || $pass !== '');
        $mail->Username = $user;
        $mail->Password = $pass;

        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
        }

        $verifyPeer = (bool) ($smtp['ssl_verify_peer'] ?? true);
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => $verifyPeer,
                'verify_peer_name' => $verifyPeer,
                'allow_self_signed' => !$verifyPeer,
            ],
        ];
    }
}
