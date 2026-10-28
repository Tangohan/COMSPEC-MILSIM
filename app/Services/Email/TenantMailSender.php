<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Repositories\TenantRepository;
use App\Support\SecretCipher;

/**
 * Compte d'envoi des e-mails propre à une communauté (Back-office > Intégrations > E-mails de la communauté).
 *
 * Chaque communauté saisit son expéditeur et son serveur SMTP (ou un relais Brevo, SendGrid, Mailgun, Amazon SES) :
 * les e-mails liés à cette communauté partent de son compte, à son nom et sur son quota, pas sur celui de la plateforme.
 * Sans réglage (ou réglage coupé), rien ne change : l'envoi de la plateforme s'applique s'il existe, sinon rien ne part.
 * Le mot de passe est chiffré en base (SecretCipher) et n'est jamais réaffiché.
 * Seuls les ports SMTP usuels et les serveurs publics sont acceptés (pas d'adresse du réseau local du serveur).
 *
 * PLATFORM (0) : même compte pour l'envoi commun de la plateforme (e-mails sans communauté, communautés sans compte),
 * réglé par l'administrateur dans Administration › Intégrations de la plateforme et rangé dans platform_settings. Une fois actif, il remplace
 * les réglages MAIL_* du .env (email_config()), qui ne servent plus que de secours.
 */
final class TenantMailSender
{
    public const SETTINGS_KEY = 'mail_sender';
    public const PLATFORM = 0;
    public const PLATFORM_SETTING_KEY = 'mail.platform_sender';

    /** Fournisseurs proposés : hôte, port, chiffrement et identifiant par défaut. */
    public const PROVIDERS = [
        'smtp' => ['label' => 'Serveur SMTP de votre hébergeur', 'host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => ''],
        'brevo' => ['label' => 'Brevo', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'encryption' => 'tls', 'username' => ''],
        'sendgrid' => ['label' => 'SendGrid', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls', 'username' => 'apikey'],
        'mailgun' => ['label' => 'Mailgun', 'host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls', 'username' => ''],
        'ses' => ['label' => 'Amazon SES', 'host' => 'email-smtp.eu-west-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls', 'username' => ''],
    ];

    public const PORTS = [25, 465, 587, 2525];

    /** @var \Closure(string): list<string> */
    private \Closure $resolveHost;

    /**
     * @param (\Closure(string): list<string>)|null $resolveHost adresses IP d'un hôte (tests)
     * @param (\Closure(): string)|null $platformRead réglage JSON de l'envoi commun (tests)
     * @param (\Closure(string): void)|null $platformWrite enregistrement JSON de l'envoi commun (tests)
     */
    public function __construct(
        private ?TenantRepository $tenants = null,
        ?\Closure $resolveHost = null,
        private ?\Closure $platformRead = null,
        private ?\Closure $platformWrite = null
    ) {
        $this->resolveHost = $resolveHost ?? static function (string $host): array {
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return [$host];
            }
            $ips = @gethostbynamel($host);

            return is_array($ips) ? $ips : [];
        };
    }

    /**
     * Réglage affichable (sans le mot de passe).
     *
     * @return array{enabled:bool, provider:string, host:string, port:int, encryption:string, username:string,
     *   has_password:bool, from_address:string, from_name:string, reply_to:string, ready:bool}
     */
    public function state(int $tenantId): array
    {
        $row = $this->raw($tenantId);
        $provider = array_key_exists((string) ($row['provider'] ?? ''), self::PROVIDERS) ? (string) $row['provider'] : 'smtp';
        $state = [
            'enabled' => !empty($row['enabled']),
            'provider' => $provider,
            'host' => (string) ($row['host'] ?? ''),
            'port' => (int) ($row['port'] ?? self::PROVIDERS[$provider]['port']),
            'encryption' => in_array($row['encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? (string) $row['encryption'] : 'tls',
            'username' => (string) ($row['username'] ?? ''),
            'has_password' => (string) ($row['password'] ?? '') !== '',
            'from_address' => (string) ($row['from_address'] ?? ''),
            'from_name' => (string) ($row['from_name'] ?? ''),
            'reply_to' => (string) ($row['reply_to'] ?? ''),
        ];
        $state['ready'] = $state['enabled'] && $state['from_address'] !== '' && self::hostFor($state) !== '';

        return $state;
    }

    /**
     * @param array<string, mixed> $input champs du formulaire (password vide = mot de passe inchangé)
     * @return array{ok:bool, message:string}
     */
    public function save(int $tenantId, array $input): array
    {
        $current = $this->raw($tenantId);
        $str = static fn (string $k, int $max): string => mb_substr(trim((string) (is_scalar($input[$k] ?? null) ? $input[$k] : '')), 0, $max);
        $provider = $str('provider', 20);
        if (!array_key_exists($provider, self::PROVIDERS)) {
            $provider = 'smtp';
        }
        $preset = self::PROVIDERS[$provider];
        $enabled = in_array($str('enabled', 5), ['1', 'on', 'true'], true);
        $host = strtolower($str('host', 190));
        $port = (int) $str('port', 6);
        $encryption = $str('encryption', 5);
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = $preset['encryption'];
        }
        $row = [
            'enabled' => $enabled,
            'provider' => $provider,
            'host' => $host,
            'port' => $port > 0 ? $port : $preset['port'],
            'encryption' => $encryption,
            'username' => $str('username', 190),
            'password' => (string) ($current['password'] ?? ''),
            'from_address' => $str('from_address', 190),
            'from_name' => $str('from_name', 80),
            'reply_to' => $str('reply_to', 190),
        ];
        if (in_array($str('clear_password', 5), ['1', 'on'], true)) {
            $row['password'] = '';
        }
        $password = (string) (is_scalar($input['password'] ?? null) ? $input['password'] : '');
        if ($password !== '') {
            $row['password'] = SecretCipher::encrypt(mb_substr($password, 0, 500));
        }

        if ($enabled) {
            $problem = $this->problem($row);
            if ($problem !== null) {
                return ['ok' => false, 'message' => $problem];
            }
        } elseif ($row['from_address'] !== '' && !filter_var($row['from_address'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'L’adresse d’expéditeur n’est pas une adresse e-mail valide.'];
        }

        if ($tenantId === self::PLATFORM) {
            try {
                $this->platformWriter()((string) json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } catch (\Throwable) {
                return ['ok' => false, 'message' => 'Le compte d’envoi de la plateforme n’a pas pu être enregistré (table platform_settings absente ?).'];
            }

            return [
                'ok' => true,
                'message' => $enabled
                    ? 'Le compte d’envoi de la plateforme est enregistré : il remplace les réglages MAIL_* du .env. Envoyez un e-mail d’essai pour vérifier.'
                    : 'Réglage enregistré. Le compte d’envoi de la plateforme est coupé : les réglages MAIL_* du .env s’appliquent s’ils existent.',
            ];
        }
        if ($tenantId < 2) {
            return ['ok' => false, 'message' => 'Aucune communauté sélectionnée.'];
        }
        $this->repo()->mergeSettings($tenantId, [self::SETTINGS_KEY => $row]);

        return [
            'ok' => true,
            'message' => $enabled
                ? 'Le compte d’envoi de la communauté est enregistré. Envoyez un e-mail d’essai pour vérifier.'
                : 'Réglage enregistré. Le compte d’envoi de la communauté est coupé.',
        ];
    }

    /**
     * Configuration au format email_config() pour EmailTransportResolver, ou null si la communauté n'a pas de compte actif.
     *
     * @return array<string, mixed>|null
     */
    public function transportConfig(int $tenantId): ?array
    {
        if ($tenantId < 2 && $tenantId !== self::PLATFORM) {
            return null;
        }
        $row = $this->raw($tenantId);
        $state = $this->state($tenantId);
        if (!$state['ready'] || $this->problem($row + ['reply_to' => '']) !== null) {
            return null;
        }
        $password = (string) ($row['password'] ?? '');
        $plain = $password !== '' ? SecretCipher::decrypt($password) : '';
        if ($plain === null) {
            error_log('[mail] ' . ($tenantId === self::PLATFORM ? 'plateforme' : 'communauté ' . $tenantId) . ' : mot de passe SMTP illisible (APP_KEY changée ?)');

            return null;
        }

        return [
            'default_mailer' => 'smtp',
            // « platform:… » compte dans le quota de la plateforme, « community:… » non.
            'transport_label' => ($tenantId === self::PLATFORM ? 'platform:' : 'community:') . $state['provider'],
            'from_address' => $state['from_address'],
            'from_name' => $state['from_name'],
            'reply_to' => $state['reply_to'] !== '' ? $state['reply_to'] : null,
            'smtp' => [
                'host' => self::hostFor($state),
                'port' => $state['port'],
                'username' => $state['username'] !== '' ? $state['username'] : self::PROVIDERS[$state['provider']]['username'],
                'password' => $plain,
                'encryption' => $state['encryption'],
                'timeout' => 20,
                'ssl_verify_peer' => true,
            ],
        ];
    }

    /**
     * E-mail d'essai envoyé tout de suite avec le compte de la communauté (hors quota et hors file d'attente).
     *
     * @return array{ok:bool, message:string}
     */
    public function sendTest(int $tenantId, string $to, string $tenantName): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Votre compte n’a pas d’adresse e-mail valide pour recevoir l’essai.'];
        }
        $state = $this->state($tenantId);
        if (!$state['enabled']) {
            return ['ok' => false, 'message' => $tenantId === self::PLATFORM
                ? 'Activez d’abord le compte d’envoi de la plateforme, puis enregistrez.'
                : 'Activez d’abord le compte d’envoi de la communauté, puis enregistrez.'];
        }
        $config = $this->transportConfig($tenantId);
        if ($config === null) {
            return ['ok' => false, 'message' => 'Réglage incomplet : vérifiez l’expéditeur, le serveur et le mot de passe, puis enregistrez.'];
        }
        $name = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
        $html = '<p>Bonjour,</p><p>Ce message d’essai confirme que les e-mails de <strong>' . $name . '</strong> partent bien de ce compte d’envoi.</p>'
            . '<p lang="en">This test message confirms that e-mails from <strong>' . $name . '</strong> are sent from this account.</p>'
            . '<p>Athena</p>';
        $text = "Ce message d'essai confirme que les e-mails de " . $tenantName . " partent bien de ce compte d'envoi.\n\n"
            . 'This test message confirms that e-mails from ' . $tenantName . " are sent from this account.\n\nAthena";
        $result = (new \App\Services\EmailTransportResolver($config))
            ->sendMessage($to, 'Essai d’envoi · Test e-mail — ' . $tenantName, $html, $text);
        if ($result['ok']) {
            return ['ok' => true, 'message' => 'E-mail d’essai envoyé à ' . $to . ' depuis ' . $state['from_address'] . '. Vérifiez aussi le dossier indésirables.'];
        }

        return ['ok' => false, 'message' => 'Échec de l’envoi : ' . mb_substr((string) ($result['error'] ?? 'erreur inconnue'), 0, 300)];
    }

    /** @param array{provider:string, host:string} $state */
    private static function hostFor(array $state): string
    {
        return $state['host'] !== '' ? $state['host'] : (string) (self::PROVIDERS[$state['provider']]['host'] ?? '');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function problem(array $row): ?string
    {
        if (!filter_var($row['from_address'], FILTER_VALIDATE_EMAIL)) {
            return 'Renseignez l’adresse d’expéditeur (par exemple no-reply@votre-domaine.fr).';
        }
        if ($row['reply_to'] !== '' && !filter_var($row['reply_to'], FILTER_VALIDATE_EMAIL)) {
            return 'L’adresse de réponse n’est pas une adresse e-mail valide.';
        }
        if (!in_array((int) $row['port'], self::PORTS, true)) {
            return 'Port SMTP non accepté. Utilisez 587 (le plus courant), 465, 2525 ou 25.';
        }
        $host = self::hostFor(['provider' => (string) $row['provider'], 'host' => (string) $row['host']]);
        if ($host === '') {
            return 'Renseignez le serveur SMTP (par exemple smtp.votre-hebergeur.fr).';
        }
        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) !== 1
            && !filter_var($host, FILTER_VALIDATE_IP)) {
            return 'Le serveur SMTP doit être un nom d’hôte, par exemple smtp.votre-hebergeur.fr.';
        }
        $ips = ($this->resolveHost)($host);
        if ($ips === []) {
            return 'Le serveur SMTP « ' . $host . ' » est introuvable. Vérifiez son nom.';
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'Le serveur SMTP doit être un serveur public sur Internet.';
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function raw(int $tenantId): array
    {
        if ($tenantId === self::PLATFORM) {
            try {
                $decoded = json_decode(($this->platformReader())(), true);
            } catch (\Throwable) {
                return [];
            }

            return is_array($decoded) ? $decoded : [];
        }
        if ($tenantId < 2) {
            return [];
        }
        $settings = $this->repo()->getSettings($tenantId);

        return is_array($settings[self::SETTINGS_KEY] ?? null) ? $settings[self::SETTINGS_KEY] : [];
    }

    /** @return \Closure(): string */
    private function platformReader(): \Closure
    {
        return $this->platformRead ??= static fn (): string => (new \App\Repositories\PlatformSettingsRepository())->get(self::PLATFORM_SETTING_KEY, '');
    }

    /** @return \Closure(string): void */
    private function platformWriter(): \Closure
    {
        return $this->platformWrite ??= static function (string $json): void {
            $repo = new \App\Repositories\PlatformSettingsRepository();
            if (!$repo->tableExists()) {
                throw new \RuntimeException('platform_settings');
            }
            $repo->setMany([self::PLATFORM_SETTING_KEY => $json]);
        };
    }

    /**
     * Envoi commun de la plateforme réglé en base, au format email_config(), ou null (les MAIL_* du .env s'appliquent).
     *
     * @return array<string, mixed>|null
     */
    public static function platformOverride(): ?array
    {
        try {
            return (new self())->transportConfig(self::PLATFORM);
        } catch (\Throwable) {
            return null;
        }
    }

    private function repo(): TenantRepository
    {
        return $this->tenants ??= new TenantRepository();
    }
}
