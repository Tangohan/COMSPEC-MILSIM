<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Container;
use App\Repositories\PlatformAccountBanRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * E-mails de suivi envoyés à la personne sanctionnée : accusé de réception, puis décision.
 * Le message ne contient ni la contestation ni la réponse de la modération : elles se lisent sur la page protégée
 * par un lien confidentiel (voir PlatformBanAppealController).
 */
final class PlatformBanAppealMailer
{
    public const RECEIVED = 'received';
    public const LIFTED = 'lift';
    public const UPHELD = 'uphold';

    public function __construct(private ?EmailService $email = null)
    {
    }

    /** @param array<string, mixed> $ban ligne platform_account_bans */
    public function notify(array $ban, string $kind): bool
    {
        if (!in_array($kind, [self::RECEIVED, self::LIFTED, self::UPHELD], true)) {
            return false;
        }
        $address = PlatformAccountBanRepository::normalizeEmail((string) ($ban['email_norm'] ?? ''));
        if ($address === '' || strlen($address) > 255 || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            $email = $this->email ??= Container::get(EmailService::class);
            $language = $email->recipientLocale($address) === 'en' ? 'en' : 'fr';

            return $email->sendTemplated(
                EmailEvents::PLATFORM_BAN_APPEAL_UPDATE,
                'platform_ban_appeal_update',
                $address,
                \__('moderation.mail_update_subject_' . $kind, [], $language),
                [
                    'updateKind' => $kind,
                    'appealUrl' => url('contester-sanction'),
                    'appealLanguage' => $language,
                ],
                forceImmediate: true,
                locale: $language,
            );
        } catch (\Throwable) {
            error_log('[ban_appeal.mail] Suivi de contestation non envoyé.');

            return false;
        }
    }
}
