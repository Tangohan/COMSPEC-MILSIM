<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/**
 * Réglages de la boîte de contact (config/contact_mail.php, lus dans le .env).
 * Un objet immuable : les essais le construisent avec un tableau, le site le lit dans le .env.
 */
final class ContactMailConfig
{
    /** Clés .env à renseigner (nom => obligatoire pour que la boîte fonctionne). */
    public const KEYS = [
        'CONTACT_MAIL_PASSWORD' => true,
        'CONTACT_MAIL_ADDRESS' => false,
        'CONTACT_MAIL_USER' => false,
        'CONTACT_MAIL_IMAP_HOST' => false,
        'CONTACT_MAIL_IMAP_PORT' => false,
        'CONTACT_MAIL_SMTP_HOST' => false,
        'CONTACT_MAIL_SMTP_PORT' => false,
        'CONTACT_MAIL_FROM_NAME' => false,
        'CONTACT_MAIL_SIGNATURE' => false,
        'CONTACT_MAIL_SENT_FOLDER' => false,
        'CONTACT_MAIL_SEND_PER_HOUR' => false,
        'MAIL_REPLY_TO_MODE' => false,
    ];

    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    public static function fromEnv(): self
    {
        $path = \base_path('config/contact_mail.php');
        $values = is_file($path) ? require $path : [];

        return new self(is_array($values) ? $values : []);
    }

    public function address(): string
    {
        return trim((string) ($this->values['address'] ?? ''));
    }

    public function user(): string
    {
        $u = trim((string) ($this->values['user'] ?? ''));

        return $u !== '' ? $u : $this->address();
    }

    public function password(): string
    {
        return (string) ($this->values['password'] ?? '');
    }

    public function imapHost(): string
    {
        return trim((string) ($this->values['imap_host'] ?? ''));
    }

    public function imapPort(): int
    {
        return (int) ($this->values['imap_port'] ?? 993);
    }

    public function smtpHost(): string
    {
        return trim((string) ($this->values['smtp_host'] ?? ''));
    }

    public function smtpPort(): int
    {
        return (int) ($this->values['smtp_port'] ?? 465);
    }

    public function fromName(): string
    {
        $n = trim((string) ($this->values['from_name'] ?? ''));

        return $n !== '' ? $n : 'Athena';
    }

    public function signature(): string
    {
        return (string) ($this->values['signature'] ?? '');
    }

    public function sentFolder(): string
    {
        return trim((string) ($this->values['sent_folder'] ?? ''));
    }

    public function sendPerHour(): int
    {
        return max(1, (int) ($this->values['send_per_hour'] ?? 20));
    }

    public function timeout(): int
    {
        return max(5, (int) ($this->values['timeout'] ?? 20));
    }

    /** « contact » : les réponses aux e-mails automatiques arrivent dans la boîte ; « none » : aucun Reply-To ajouté. */
    public function replyToMode(): string
    {
        return ($this->values['reply_to_mode'] ?? 'contact') === 'none' ? 'none' : 'contact';
    }

    /** Boîte utilisable : adresse valide, identifiant et mot de passe renseignés, hôtes connus. */
    public function isConfigured(): bool
    {
        return $this->missingKeys() === [];
    }

    /**
     * Clés .env à renseigner pour que la boîte fonctionne (liste vide = prête).
     *
     * @return list<string>
     */
    public function missingKeys(): array
    {
        $missing = [];
        if ($this->password() === '') {
            $missing[] = 'CONTACT_MAIL_PASSWORD';
        }
        if (!self::isValidAddress($this->address())) {
            $missing[] = 'CONTACT_MAIL_ADDRESS';
        }
        if ($this->user() === '') {
            $missing[] = 'CONTACT_MAIL_USER';
        }
        if ($this->imapHost() === '' || $this->imapPort() <= 0) {
            $missing[] = 'CONTACT_MAIL_IMAP_HOST';
        }
        if ($this->smtpHost() === '' || $this->smtpPort() <= 0) {
            $missing[] = 'CONTACT_MAIL_SMTP_HOST';
        }

        return $missing;
    }

    /** Adresse e-mail simple, sans retour à la ligne ni caractère de contrôle (anti injection d'en-tête). */
    public static function isValidAddress(string $address): bool
    {
        if ($address === '' || strlen($address) > 254 || preg_match('/[\x00-\x20\x7F<>,;"\\\\]/', $address) === 1) {
            return false;
        }

        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Adresse à mettre en Reply-To des e-mails automatiques, ou null (mode « none », adresse invalide).
     */
    public function autoReplyTo(): ?string
    {
        if ($this->replyToMode() === 'none') {
            return null;
        }
        $a = $this->address();

        return self::isValidAddress($a) ? $a : null;
    }
}
