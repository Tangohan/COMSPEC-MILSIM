<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIntelEventRepository;
use App\Repositories\TenantRepository;
use App\Services\Integrations\DiscordWebhookService;

/**
 * Relais Discord du journal des transmissions terrain.
 * Les adresses restent côté serveur ; l’UI n’affiche que l’intitulé.
 */
final class SseTransmissionDiscordService
{
    public const SETTINGS_KEY = 'sse_transmissions';
    private const EMBED_COLOR = 0x35d6a1;
    private const MAX_RELAYS = 8;

    public function __construct(
        private ?TenantRepository $tenants = null,
        private ?DiscordWebhookService $discord = null,
    ) {
        $this->tenants ??= new TenantRepository();
        $this->discord ??= new DiscordWebhookService();
    }

    /**
     * @return array{use_community_relay:bool,relays:list<array{id:string,label:string,url:string,created_at:string}>}
     */
    public function config(int $tenantId): array
    {
        return self::normalizeConfig($this->tenants->getSettings($tenantId));
    }

    /**
     * @return list<array{id:string,label:string,masked:string}>
     */
    public function publicRelays(int $tenantId): array
    {
        $out = [];
        foreach ($this->config($tenantId)['relays'] as $relay) {
            $out[] = [
                'id' => $relay['id'],
                'label' => $relay['label'],
                'masked' => self::maskRelayUrl($relay['url']),
            ];
        }

        return $out;
    }

    public function usesCommunityRelay(int $tenantId): bool
    {
        return $this->config($tenantId)['use_community_relay'];
    }

    public function communityRelayReady(int $tenantId): bool
    {
        return $this->communityWebhookUrl($tenantId) !== null;
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function addRelay(int $tenantId, string $label, string $url): array
    {
        $label = trim($label);
        $url = trim($url);
        if ($label === '') {
            $label = 'Salon Discord';
        }
        if (!$this->discord->isValidWebhookUrl($url)) {
            return [
                'ok' => false,
                'message' => 'Le lien Discord n’est pas reconnu. Dans le salon, ouvrez Intégrations, créez un relais, puis collez ici l’adresse complète.',
            ];
        }
        $cfg = $this->config($tenantId);
        if (count($cfg['relays']) >= self::MAX_RELAYS) {
            return ['ok' => false, 'message' => 'Huit relais maximum. Retirez-en un avant d’en ajouter un autre.'];
        }
        foreach ($cfg['relays'] as $existing) {
            if (hash_equals($existing['url'], $url)) {
                return ['ok' => false, 'message' => 'Ce salon est déjà relié.'];
            }
        }
        $cfg['relays'][] = [
            'id' => bin2hex(random_bytes(6)),
            'label' => mb_substr($label, 0, 80),
            'url' => $url,
            'created_at' => gmdate('c'),
        ];
        $this->persist($tenantId, $cfg);

        return ['ok' => true, 'message' => 'Relais Discord ajouté. Les nouvelles transmissions terrain y seront publiées.'];
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function removeRelay(int $tenantId, string $relayId): array
    {
        $cfg = $this->config($tenantId);
        $kept = [];
        $found = false;
        foreach ($cfg['relays'] as $relay) {
            if ($relay['id'] === $relayId) {
                $found = true;
                continue;
            }
            $kept[] = $relay;
        }
        if (!$found) {
            return ['ok' => false, 'message' => 'Relais introuvable.'];
        }
        $cfg['relays'] = $kept;
        $this->persist($tenantId, $cfg);

        return ['ok' => true, 'message' => 'Relais retiré.'];
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function setUseCommunityRelay(int $tenantId, bool $enabled): array
    {
        if ($enabled && !$this->communityRelayReady($tenantId)) {
            return [
                'ok' => false,
                'message' => 'Aucun salon Discord n’est encore configuré pour la communauté. Renseignez-le dans Intégrations, ou ajoutez un relais ci-dessous.',
            ];
        }
        $cfg = $this->config($tenantId);
        $cfg['use_community_relay'] = $enabled;
        $this->persist($tenantId, $cfg);

        return [
            'ok' => true,
            'message' => $enabled
                ? 'Les transmissions terrain seront aussi publiées sur le salon Discord de la communauté.'
                : 'Le salon Discord de la communauté n’est plus utilisé pour ce journal.',
        ];
    }

    /**
     * @param array<string, mixed> $event
     * @return array{ok:bool, sent:int, failed:int, message:string}
     */
    public function publishEvent(int $tenantId, array $event, bool $fast = false): array
    {
        $urls = $this->destinationUrls($tenantId);
        if ($urls === []) {
            return [
                'ok' => false,
                'sent' => 0,
                'failed' => 0,
                'message' => 'Aucun relais Discord n’est encore lié à ce journal.',
            ];
        }

        $embed = $this->embedForEvent($event);
        $timeout = $fast ? 3 : 8;
        $sent = 0;
        $failed = 0;
        foreach ($urls as $url) {
            $result = $this->discord->sendEmbed(
                $url,
                $embed,
                null,
                'Athena · Transmissions',
                $timeout
            );
            if ($result['ok'] ?? false) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($sent === 0) {
            return [
                'ok' => false,
                'sent' => 0,
                'failed' => $failed,
                'message' => 'Discord n’a pas accepté le message. Vérifiez que les relais existent toujours.',
            ];
        }

        return [
            'ok' => true,
            'sent' => $sent,
            'failed' => $failed,
            'message' => $failed > 0
                ? sprintf('Publié sur %d salon%s, %d en échec.', $sent, $sent > 1 ? 's' : '', $failed)
                : sprintf('Publié sur %d salon%s Discord.', $sent, $sent > 1 ? 's' : ''),
        ];
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function sendTest(int $tenantId, string $relayId): array
    {
        $cfg = $this->config($tenantId);
        foreach ($cfg['relays'] as $relay) {
            if ($relay['id'] !== $relayId) {
                continue;
            }
            $result = $this->discord->sendEmbed(
                $relay['url'],
                [
                    'title' => 'Essai de relais — transmissions terrain',
                    'description' => 'Ce salon est bien relié au journal des transmissions Athena.',
                    'color' => self::EMBED_COLOR,
                    'footer' => ['text' => 'Athena SSE'],
                    'timestamp' => gmdate('c'),
                ],
                null,
                'Athena · Transmissions'
            );
            if ($result['ok'] ?? false) {
                return ['ok' => true, 'message' => 'Message d’essai envoyé sur « ' . $relay['label'] . ' ».'];
            }

            return ['ok' => false, 'message' => (string) ($result['error'] ?? 'Envoi Discord impossible.')];
        }

        return ['ok' => false, 'message' => 'Relais introuvable.'];
    }

    /**
     * Best-effort après une nouvelle transmission terrain — ne doit jamais casser l’ingest.
     *
     * @param array<string, mixed> $event
     */
    public function notifyNewTransmission(int $tenantId, array $event): void
    {
        $source = strtoupper(trim((string) ($event['source_system'] ?? '')));
        if (!in_array($source, SseIntelEventRepository::armaTerrainSourceSystems(), true)) {
            return;
        }
        if ($this->destinationUrls($tenantId) === []) {
            return;
        }
        $this->publishEvent($tenantId, $event, true);
    }

    public static function maskRelayUrl(string $url): string
    {
        if (preg_match('#^https://(?:www\.)?(?:discord|discordapp)\.com/api/webhooks/#i', $url) === 1) {
            return 'Salon Discord relié';
        }

        return 'Lien Discord';
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{use_community_relay:bool,relays:list<array{id:string,label:string,url:string,created_at:string}>}
     */
    public static function normalizeConfig(array $settings): array
    {
        $integrations = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
        $raw = is_array($integrations[self::SETTINGS_KEY] ?? null) ? $integrations[self::SETTINGS_KEY] : [];
        $relays = [];
        foreach (is_array($raw['relays'] ?? null) ? $raw['relays'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($id === '' || $url === '') {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $relays[] = [
                'id' => $id,
                'label' => $label !== '' ? mb_substr($label, 0, 80) : 'Salon Discord',
                'url' => $url,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return [
            'use_community_relay' => !empty($raw['use_community_relay']),
            'relays' => $relays,
        ];
    }

    /**
     * @return list<string>
     */
    private function destinationUrls(int $tenantId): array
    {
        $cfg = $this->config($tenantId);
        $urls = [];
        foreach ($cfg['relays'] as $relay) {
            if ($this->discord->isValidWebhookUrl($relay['url'])) {
                $urls[] = $relay['url'];
            }
        }
        if ($cfg['use_community_relay']) {
            $org = $this->communityWebhookUrl($tenantId);
            if ($org !== null && !in_array($org, $urls, true)) {
                $urls[] = $org;
            }
        }

        return $urls;
    }

    private function communityWebhookUrl(int $tenantId): ?string
    {
        $url = trim((string) ($this->tenants->getSettings($tenantId)['integrations']['discord_webhook_url'] ?? ''));
        if ($url === '' || !$this->discord->isValidWebhookUrl($url)) {
            return null;
        }

        return $url;
    }

    /**
     * @param array{use_community_relay:bool,relays:list<array{id:string,label:string,url:string,created_at:string}>} $cfg
     */
    private function persist(int $tenantId, array $cfg): void
    {
        $settings = $this->tenants->getSettings($tenantId);
        $integrations = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
        $integrations[self::SETTINGS_KEY] = [
            'use_community_relay' => !empty($cfg['use_community_relay']),
            'relays' => $cfg['relays'],
        ];
        $this->tenants->mergeSettings($tenantId, ['integrations' => $integrations]);
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function embedForEvent(array $event): array
    {
        $id = (int) ($event['id'] ?? 0);
        $title = (string) ($event['event_type_label'] ?? 'Transmission terrain');
        $summary = trim((string) ($event['summary'] ?? 'Sans résumé'));
        $when = substr((string) ($event['event_time'] ?? ''), 0, 16);
        $fields = [
            [
                'name' => 'Horodatage',
                'value' => $when !== '' ? $when : '—',
                'inline' => true,
            ],
            [
                'name' => 'Origine',
                'value' => (string) ($event['source_system_label'] ?? 'Terrain'),
                'inline' => true,
            ],
            [
                'name' => 'Opérateur',
                'value' => (string) (($event['author_label'] ?? '') !== '' ? $event['author_label'] : '—'),
                'inline' => true,
            ],
        ];
        $client = trim((string) ($event['client_label'] ?? ''));
        if ($client !== '') {
            $fields[] = ['name' => 'Logiciel', 'value' => $client, 'inline' => false];
        }

        $openUrl = $id > 0 ? url('atak/sse/transmissions/' . $id) : '';

        return [
            'title' => $title,
            'description' => $summary,
            'url' => $openUrl !== '' ? $openUrl : null,
            'color' => self::EMBED_COLOR,
            'fields' => $fields,
            'footer' => ['text' => 'Athena · journal des transmissions'],
            'timestamp' => gmdate('c'),
        ];
    }
}
