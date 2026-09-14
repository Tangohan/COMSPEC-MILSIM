<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Repositories\TenantRepository;
use App\Support\DiscordWebhookCatalog;

/**
 * Résout le salon Discord d’un événement communauté et y publie un message.
 */
final class DiscordEventRelayService
{
    public const SETTINGS_EVENTS_KEY = 'discord_event_webhooks';

    /** @var array<string, true> */
    private static array $sentThisRequest = [];

    public function __construct(
        private ?TenantRepository $tenants = null,
        private ?DiscordWebhookService $discord = null,
    ) {
        $this->tenants ??= new TenantRepository();
        $this->discord ??= new DiscordWebhookService();
    }

    /**
     * @return array{default_url:string, events:array<string, array{mode:string, url:string}>}
     */
    public function state(int $tenantId): array
    {
        $settings = $this->tenants->getSettings($tenantId);
        $integrations = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
        $default = trim((string) ($integrations['discord_webhook_url'] ?? ''));
        $rawEvents = is_array($integrations[self::SETTINGS_EVENTS_KEY] ?? null)
            ? $integrations[self::SETTINGS_EVENTS_KEY]
            : [];
        $events = [];
        foreach (DiscordWebhookCatalog::events() as $meta) {
            $key = $meta['key'];
            $row = is_array($rawEvents[$key] ?? null) ? $rawEvents[$key] : [];
            $mode = trim((string) ($row['mode'] ?? ''));
            if (!in_array($mode, [DiscordWebhookCatalog::MODE_OFF, DiscordWebhookCatalog::MODE_DEFAULT, DiscordWebhookCatalog::MODE_CUSTOM], true)) {
                $mode = $meta['default_mode'];
            }
            $events[$key] = [
                'mode' => $mode,
                'url' => trim((string) ($row['url'] ?? '')),
            ];
        }

        return [
            'default_url' => $default,
            'events' => $events,
        ];
    }

    /**
     * @param array<string, array{mode?:string, url?:string}> $eventsInput
     * @return array{ok:bool, message:string}
     */
    public function save(int $tenantId, string $defaultUrl, array $eventsInput): array
    {
        $defaultUrl = trim($defaultUrl);
        if ($defaultUrl !== '' && !$this->discord->isValidWebhookUrl($defaultUrl)) {
            return [
                'ok' => false,
                'message' => 'Le lien du salon commun n’est pas reconnu. Dans Discord, ouvrez les paramètres du salon → Intégrations, créez un relais, puis collez ici le lien copié.',
            ];
        }

        $events = [];
        foreach (DiscordWebhookCatalog::events() as $meta) {
            $key = $meta['key'];
            $row = is_array($eventsInput[$key] ?? null) ? $eventsInput[$key] : [];
            $mode = trim((string) ($row['mode'] ?? $meta['default_mode']));
            if (!in_array($mode, [DiscordWebhookCatalog::MODE_OFF, DiscordWebhookCatalog::MODE_DEFAULT, DiscordWebhookCatalog::MODE_CUSTOM], true)) {
                $mode = $meta['default_mode'];
            }
            $url = trim((string) ($row['url'] ?? ''));
            if ($mode === DiscordWebhookCatalog::MODE_CUSTOM) {
                if ($url === '' || !$this->discord->isValidWebhookUrl($url)) {
                    return [
                        'ok' => false,
                        'message' => 'Le lien de l’autre salon pour « ' . $meta['label'] . ' » n’est pas reconnu. Collez le lien copié depuis Discord.',
                    ];
                }
            } else {
                $url = $url !== '' && $this->discord->isValidWebhookUrl($url) ? $url : '';
            }
            $events[$key] = ['mode' => $mode, 'url' => $url];
        }

        $settings = $this->tenants->getSettings($tenantId);
        $integrations = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
        $integrations['discord_webhook_url'] = $defaultUrl !== '' ? $defaultUrl : null;
        $integrations[self::SETTINGS_EVENTS_KEY] = $events;
        $this->tenants->mergeSettings($tenantId, ['integrations' => $integrations]);

        return ['ok' => true, 'message' => 'Les relais Discord ont été enregistrés.'];
    }

    public function resolveUrl(int $tenantId, string $eventKey): ?string
    {
        if ($tenantId < 2 || !DiscordWebhookCatalog::isKnown($eventKey)) {
            return null;
        }
        $state = $this->state($tenantId);
        $mode = $state['events'][$eventKey]['mode'] ?? DiscordWebhookCatalog::defaultMode($eventKey);
        if ($mode === DiscordWebhookCatalog::MODE_OFF) {
            return null;
        }
        if ($mode === DiscordWebhookCatalog::MODE_CUSTOM) {
            $url = trim((string) ($state['events'][$eventKey]['url'] ?? ''));

            return $this->discord->isValidWebhookUrl($url) ? $url : null;
        }
        $default = trim($state['default_url']);

        return $this->discord->isValidWebhookUrl($default) ? $default : null;
    }

    /** @return array{ok:bool, skipped:bool, error?:string} */
    public function notify(int $tenantId, string $eventKey, string $content, ?string $username = null): array
    {
        $url = $this->resolveUrl($tenantId, $eventKey);
        if ($url === null) {
            return ['ok' => true, 'skipped' => true];
        }
        $content = trim($content);
        if ($content === '') {
            return ['ok' => true, 'skipped' => true];
        }
        $fingerprint = $tenantId . '|' . $eventKey . '|' . hash('sha256', $content);
        if (isset(self::$sentThisRequest[$fingerprint])) {
            return ['ok' => true, 'skipped' => true];
        }
        try {
            $result = $this->discord->send($url, $content, $username);
        } catch (\Throwable) {
            return ['ok' => false, 'skipped' => false, 'error' => 'Envoi Discord impossible.'];
        }
        if (!empty($result['ok'])) {
            self::$sentThisRequest[$fingerprint] = true;

            return ['ok' => true, 'skipped' => false];
        }

        return ['ok' => false, 'skipped' => false, 'error' => (string) ($result['error'] ?? '')];
    }

    /**
     * Photo Quick Picture (téléphone / tablette) vers le salon Discord de la communauté.
     *
     * @param array<string, mixed> $meta
     */
    public function notifyQuickPicture(int $tenantId, string $filePath, array $meta): void
    {
        $device = strtoupper(trim((string) ($meta['device_type'] ?? '')));
        if ($device !== '' && !in_array($device, ['CTAB', 'TABLET', 'PHONE', 'ATAK'], true)) {
            return;
        }
        $url = $this->resolveUrl($tenantId, DiscordWebhookCatalog::KEY_QUICK_PICTURE);
        if ($url === null) {
            return;
        }
        $author = trim((string) ($meta['author_callsign'] ?? ''));
        $grid = trim((string) ($meta['grid_ref'] ?? ''));
        $caption = trim((string) ($meta['caption'] ?? ''));
        $unit = trim((string) ($meta['unit_name'] ?? ''));
        $lines = ['**Quick Picture**'];
        if ($author !== '') {
            $lines[] = 'Opérateur : ' . $author;
        }
        if ($unit !== '' && strcasecmp($unit, $author) !== 0) {
            $lines[] = 'Unité : ' . $unit;
        }
        if ($grid !== '') {
            $lines[] = 'Grille : ' . $grid;
        }
        if ($caption !== '') {
            $lines[] = $caption;
        }
        $content = implode("\n", $lines);
        $fingerprint = $tenantId . '|quick_picture|' . hash('sha256', $filePath . '|' . $content);
        if (isset(self::$sentThisRequest[$fingerprint])) {
            return;
        }
        try {
            $result = $this->discord->sendWithFile($url, $content, $filePath, basename($filePath), 'Athena');
        } catch (\Throwable) {
            return;
        }
        if (!empty($result['ok'])) {
            self::$sentThisRequest[$fingerprint] = true;
        }
    }

    public static function relayFromEmail(int $tenantId, string $eventCode, string $subject, string $textBody): void
    {
        if ($tenantId < 2 || !DiscordWebhookCatalog::isKnown($eventCode)) {
            return;
        }
        $subject = trim($subject);
        $excerpt = trim(preg_replace('/\s+/', ' ', $textBody) ?? $textBody);
        if (mb_strlen($excerpt) > 500) {
            $excerpt = mb_substr($excerpt, 0, 497) . '…';
        }
        $content = $subject !== '' ? '**' . $subject . '**' : '';
        if ($excerpt !== '') {
            $content = $content !== '' ? $content . "\n" . $excerpt : $excerpt;
        }
        if ($content === '') {
            return;
        }
        try {
            (new self())->notify($tenantId, $eventCode, $content);
        } catch (\Throwable) {
        }
    }

    /** @return array{ok:bool, message:string} */
    public function sendTest(int $tenantId, string $eventKey = ''): array
    {
        $key = $eventKey !== '' && DiscordWebhookCatalog::isKnown($eventKey)
            ? $eventKey
            : DiscordWebhookCatalog::KEY_ANNOUNCEMENTS;
        $url = $this->resolveUrl($tenantId, $key);
        if ($url === null) {
            return ['ok' => false, 'message' => 'Aucun salon n’est configuré pour cet événement. Choisissez le salon commun ou un autre salon, puis enregistrez.'];
        }
        $label = DiscordWebhookCatalog::byKey()[$key]['label'] ?? 'essai';
        $result = $this->discord->send($url, 'Essai Athena : relais « ' . $label . ' » opérationnel.');
        if (!empty($result['ok'])) {
            return ['ok' => true, 'message' => 'Un message d’essai a été envoyé dans le salon.'];
        }

        return ['ok' => false, 'message' => (string) ($result['error'] ?? 'Discord a refusé le message d’essai.')];
    }
}
