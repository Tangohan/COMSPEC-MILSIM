<?php

declare(strict_types=1);

namespace App\Services\Email;

/**
 * Règles d'envoi des e-mails automatiques face au quota quotidien du fournisseur (Hostinger : 100 / jour).
 *
 *   - essentiel  : lien d'inscription, code de connexion, mot de passe, invitation… part toujours tout de suite ;
 *   - exploitation : alertes à la boîte de l'exploitation (incidents, nouveaux comptes, signalements du mod…)
 *                    regroupées dans un récapitulatif toutes les N minutes ;
 *   - membre     : le reste ; s'arrête quand le quota (moins la réserve des essentiels) est atteint,
 *                  et repart le lendemain depuis la file d'attente au lieu d'être perdu.
 *
 * Réglages (platform_settings, page Administration > Envois d'e-mails) :
 *   mail_daily_limit         quota quotidien (MAIL_DAILY_LIMIT, 90 par défaut ; 0 = pas de quota)
 *   mail_critical_reserve    envois gardés pour les essentiels (15 par défaut)
 *   mail_ops_digest_minutes  cadence du récapitulatif exploitation (120 par défaut ; 0 = alertes une par une)
 *   mail_muted_events        types coupés, séparés par des virgules (jamais les essentiels)
 */
final class MailBudget
{
    public const PRIORITY_CRITICAL = 'critical';
    public const PRIORITY_DIGEST = 'digest';
    public const PRIORITY_OPS = 'ops';
    public const PRIORITY_NORMAL = 'normal';

    public const SEND = 'send';
    public const DEFER = 'defer';
    public const GROUP = 'group';
    public const MUTE = 'mute';

    public const SETTING_DAILY_LIMIT = 'mail_daily_limit';
    public const SETTING_RESERVE = 'mail_critical_reserve';
    public const SETTING_DIGEST_MINUTES = 'mail_ops_digest_minutes';
    public const SETTING_MUTED = 'mail_muted_events';

    public const DEFAULT_DAILY_LIMIT = 90;
    public const DEFAULT_RESERVE = 15;
    public const DEFAULT_DIGEST_MINUTES = 120;

    /** Cadences proposées pour le récapitulatif (minutes ; 0 = une alerte par e-mail). */
    public const DIGEST_CHOICES = [0, 60, 120, 180, 360, 720, 1440];

    /** Messages attendus dans la minute par la personne : jamais retenus ni coupés. */
    public const CRITICAL_EVENTS = [
        EmailEvents::EMAIL_VERIFICATION,
        EmailEvents::USER_REGISTER_CONFIRMATION,
        EmailEvents::PASSWORD_RESET,
        EmailEvents::LOGIN_SECURITY_OTP,
        EmailEvents::LOGIN_OTP_MAILBOX_SELF_TEST,
        EmailEvents::INTERTEAM_COOPERATION_OTP,
        EmailEvents::TENANT_USER_SETUP,
        EmailEvents::COMMUNITY_INVITATION,
    ];

    /** Alertes destinées à l'exploitation de la plateforme : regroupées dans le récapitulatif. */
    public const OPS_EVENTS = [
        'error_alert',
        EmailEvents::PLATFORM_ACCOUNT_CREATED,
        EmailEvents::PLATFORM_ACCOUNT_EMAIL_VERIFIED,
        EmailEvents::PLATFORM_NEW_COMMUNITY,
        EmailEvents::ATAK_MOD_PLAYER_REPORT,
        EmailEvents::SECURITY_ALERT,
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'error_alert' => 'Incident technique (erreur 500)',
        EmailEvents::OPS_DIGEST => 'Récapitulatif exploitation',
        EmailEvents::PLATFORM_ACCOUNT_CREATED => 'Nouveau compte créé (exploitation)',
        EmailEvents::PLATFORM_ACCOUNT_EMAIL_VERIFIED => 'Adresse vérifiée (exploitation)',
        EmailEvents::PLATFORM_NEW_COMMUNITY => 'Nouvelle communauté (exploitation)',
        EmailEvents::ATAK_MOD_PLAYER_REPORT => 'Signalement du mod (exploitation)',
        EmailEvents::SECURITY_ALERT => 'Alerte sécurité (exploitation)',
        EmailEvents::EMAIL_VERIFICATION => 'Vérification d’adresse',
        EmailEvents::USER_REGISTER_CONFIRMATION => 'Confirmation d’inscription',
        EmailEvents::REGISTER_SECURITY_COMPANION => 'Sécurité et démarrage rapide (après inscription)',
        EmailEvents::PASSWORD_RESET => 'Mot de passe oublié',
        EmailEvents::LOGIN_SECURITY_OTP => 'Code de connexion',
        EmailEvents::LOGIN_OTP_MAILBOX_SELF_TEST => 'Code de test (préférences)',
        EmailEvents::INTERTEAM_COOPERATION_OTP => 'Code de coopération',
        EmailEvents::TENANT_USER_SETUP => 'Compte créé par la communauté',
        EmailEvents::COMMUNITY_INVITATION => 'Invitation dans une communauté',
        EmailEvents::NEW_DEVICE_LOGIN => 'Connexion depuis un nouvel appareil',
        EmailEvents::MULTIPLE_LOGIN_ATTEMPTS => 'Tentatives de connexion répétées',
        EmailEvents::NEW_COMMUNITY_MEMBER => 'Nouveau membre (responsables)',
        EmailEvents::MEMBER_LEFT_COMMUNITY_STAFF => 'Départ d’un membre (responsables)',
        EmailEvents::MEMBER_LEFT_COMMUNITY_CONFIRMATION => 'Départ confirmé (membre)',
        EmailEvents::ATTENDANCE_RSVP_CONFIRM => 'Participation confirmée',
        EmailEvents::ATTENDANCE_RSVP_ORGANIZER => 'Participation (organisateur)',
        EmailEvents::ATTENDANCE_REMINDER => 'Rappel d’activité',
        EmailEvents::ATTENDANCE_EVENT_CANCELLED => 'Activité annulée',
        EmailEvents::ATTENDANCE_CHECKIN_CONFIRM => 'Présence enregistrée',
        EmailEvents::ATAK_MOD_REPORT_REPLY => 'Réponse à un signalement du mod',
        EmailEvents::ATAK_PLAYER_ACCESS_REFUSED => 'Accès ATAK refusé (joueur)',
        EmailEvents::PLAN_GIFT_GRANTED => 'Formule offerte',
        EmailEvents::PLAN_END_WARNING => 'Fin de formule proche',
        EmailEvents::YEAR_REVIEW_READY => 'Rétrospective annuelle prête',
        EmailEvents::PLAN_PAYMENT_DECLARED => 'Paiement PayPal à vérifier (admin)',
        EmailEvents::PLAN_PAYMENT_DECIDED => 'Paiement PayPal confirmé ou refusé',
        EmailEvents::TENANT_INTERNAL_MESSAGE_THREAD => 'Messagerie interne',
        EmailEvents::PROFILE_INCOMPLETE_REMINDER => 'Rappel profil incomplet',
        EmailEvents::PLATFORM_ALERT_BROADCAST => 'Annonce plateforme (diffusion)',
        EmailEvents::MAINTENANCE_MEMBER_BROADCAST => 'Annonce maintenance (diffusion)',
        EmailEvents::ENLISTMENT_SUBMITTED_STAFF => 'Candidature reçue (recrutement)',
        EmailEvents::ENLISTMENT_PORTAL_CANDIDATE_REPLY_STAFF => 'Message d’un candidat (recrutement)',
        EmailEvents::ENLISTMENT_PORTAL_UPDATE_CANDIDATE => 'Suivi de candidature (candidat)',
        EmailEvents::COMMUNITY_CONTACT => 'Contact d’une communauté',
    ];

    /**
     * @param list<string> $opsInboxes adresses de l'exploitation (ERROR_ALERT_EMAIL, REGISTRATION_ALERT_EMAIL…)
     */
    public static function priority(string $eventCode, string $recipient = '', array $opsInboxes = []): string
    {
        if (in_array($eventCode, self::CRITICAL_EVENTS, true)) {
            return self::PRIORITY_CRITICAL;
        }
        if ($eventCode === EmailEvents::OPS_DIGEST) {
            return self::PRIORITY_DIGEST;
        }
        if (in_array($eventCode, self::OPS_EVENTS, true)) {
            return self::PRIORITY_OPS;
        }
        $to = strtolower(trim($recipient));
        if ($to !== '') {
            foreach ($opsInboxes as $inbox) {
                if (strtolower(trim($inbox)) === $to) {
                    return self::PRIORITY_OPS;
                }
            }
        }

        return self::PRIORITY_NORMAL;
    }

    /**
     * @param array{limit:int, reserve:int, digest_minutes:int, muted:list<string>} $config
     */
    public static function decide(string $eventCode, string $priority, int $sentToday, array $config): string
    {
        if ($priority === self::PRIORITY_CRITICAL) {
            return self::SEND;
        }
        if (in_array($eventCode, $config['muted'], true)) {
            return self::MUTE;
        }
        if ($priority === self::PRIORITY_OPS && $config['digest_minutes'] > 0) {
            return self::GROUP;
        }
        $limit = $config['limit'];
        if ($limit <= 0) {
            return self::SEND;
        }
        // Le récapitulatif peut entamer la réserve : un seul e-mail pour toutes les alertes.
        $ceiling = $priority === self::PRIORITY_DIGEST ? $limit : max(0, $limit - $config['reserve']);

        return $sentToday < $ceiling ? self::SEND : self::DEFER;
    }

    /**
     * @param array<string, string> $settings valeurs brutes de platform_settings
     * @return array{limit:int, reserve:int, digest_minutes:int, muted:list<string>}
     */
    public static function configFrom(array $settings, ?int $envLimit = null): array
    {
        $raw = static fn (string $k): ?string => isset($settings[$k]) && trim($settings[$k]) !== '' ? trim($settings[$k]) : null;
        $limit = $raw(self::SETTING_DAILY_LIMIT);
        $reserve = $raw(self::SETTING_RESERVE);
        $digest = $raw(self::SETTING_DIGEST_MINUTES);

        $limitVal = $limit !== null ? (int) $limit : ($envLimit ?? self::DEFAULT_DAILY_LIMIT);
        $limitVal = max(0, min(100000, $limitVal));
        $reserveVal = max(0, min(1000, $reserve !== null ? (int) $reserve : self::DEFAULT_RESERVE));
        if ($limitVal > 0) {
            $reserveVal = min($reserveVal, $limitVal);
        }

        return [
            'limit' => $limitVal,
            'reserve' => $reserveVal,
            'digest_minutes' => max(0, min(1440, $digest !== null ? (int) $digest : self::DEFAULT_DIGEST_MINUTES)),
            'muted' => self::parseMuted((string) ($settings[self::SETTING_MUTED] ?? '')),
        ];
    }

    /** @return list<string> */
    public static function parseMuted(string $csv): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $csv) ?: [] as $code) {
            $code = trim($code);
            if ($code !== '' && preg_match('/^[A-Za-z0-9_]{1,80}$/', $code) && self::canMute($code)) {
                $out[$code] = true;
            }
        }

        return array_keys($out);
    }

    public static function canMute(string $eventCode): bool
    {
        return !in_array($eventCode, self::CRITICAL_EVENTS, true) && $eventCode !== EmailEvents::OPS_DIGEST;
    }

    public static function label(string $eventCode): string
    {
        if (isset(self::LABELS[$eventCode])) {
            return self::LABELS[$eventCode];
        }
        $s = strtolower(str_replace('_', ' ', $eventCode));

        return ucfirst($s);
    }

    public static function priorityLabel(string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_CRITICAL => 'Essentiel',
            self::PRIORITY_OPS => 'Exploitation',
            self::PRIORITY_DIGEST => 'Récapitulatif',
            default => 'Membres',
        };
    }

    /** Début de la journée de quota suivante (00:05, heure du serveur) pour un envoi reporté. */
    public static function nextWindow(?int $now = null): \DateTimeImmutable
    {
        $now ??= time();

        return (new \DateTimeImmutable('@' . $now))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->modify('tomorrow')
            ->setTime(0, 5);
    }
}
