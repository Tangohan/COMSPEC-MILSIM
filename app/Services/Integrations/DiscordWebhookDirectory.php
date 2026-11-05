<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Repositories\TenantRepository;
use App\Support\DiscordChannelInput;
use App\Support\SecretCipher;

/**
 * Carnet des webhooks Discord d'une communauté (Intégrations › Discord).
 *
 * - Le webhook « Organisation » (integrations.discord_webhook_url) sert à tous les usages qui n'ont pas de salon à part.
 * - Les webhooks nommés (integrations.discord_webhooks) servent aux usages réglés sur un salon précis (mode avancé).
 *
 * Les liens sont chiffrés au repos (SecretCipher, APP_KEY) et ne ressortent jamais vers l'écran : seule une forme
 * masquée (discord.com/api/webhooks/1234…/••••) est affichée. Un lien encore en clair (ancien réglage) reste lu.
 */
final class DiscordWebhookDirectory
{
    public const ORGANISATION = 'organisation';
    public const MAX_WEBHOOKS = 12;

    private const KEY_URL = 'discord_webhook_url';
    private const KEY_META = 'discord_webhook_meta';
    private const KEY_LIST = 'discord_webhooks';

    public function __construct(private ?TenantRepository $tenants = null)
    {
        $this->tenants ??= new TenantRepository();
    }

    /** Lien en clair d'un réglage stocké (chiffré ou ancien réglage en clair), ou '' s'il est absent ou illisible. */
    public static function reveal(mixed $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }
        $plain = str_starts_with($stored, 'sc1:') ? (string) (SecretCipher::decrypt($stored) ?? '') : $stored;

        return DiscordChannelInput::webhookUrl($plain);
    }

    /** discord.com/api/webhooks/1234…/•••• : reconnaissable, inutilisable. */
    public static function mask(string $url): string
    {
        if (preg_match('#webhooks/(\d{4})\d*/#', $url, $m) === 1) {
            return 'discord.com/api/webhooks/' . $m[1] . '…/••••';
        }

        return $url !== '' ? 'Lien enregistré' : '';
    }

    /** @return array<string, mixed> */
    private function integrations(int $tenantId): array
    {
        $settings = $this->tenants->getSettings($tenantId);

        return is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
    }

    /** @param array<string, mixed> $integrations */
    private function persist(int $tenantId, array $integrations): void
    {
        $this->tenants->mergeSettings($tenantId, ['integrations' => $integrations]);
    }

    public function organisationUrl(int $tenantId): ?string
    {
        if ($tenantId < 2) {
            return null;
        }
        $url = self::reveal($this->integrations($tenantId)[self::KEY_URL] ?? '');

        return $url !== '' ? $url : null;
    }

    /**
     * @return array{configured:bool, masked:string, name:string, channel:string, last_test_at:string, last_test_ok:?bool, last_test_message:string, encrypted:bool}
     */
    public function organisation(int $tenantId): array
    {
        $integrations = $this->integrations($tenantId);
        $url = self::reveal($integrations[self::KEY_URL] ?? '');
        $meta = is_array($integrations[self::KEY_META] ?? null) ? $integrations[self::KEY_META] : [];

        return [
            'configured' => $url !== '',
            'masked' => self::mask($url),
            'name' => (string) ($meta['name'] ?? ''),
            'channel' => (string) ($meta['channel'] ?? ''),
            'last_test_at' => (string) ($meta['last_test_at'] ?? ''),
            'last_test_ok' => isset($meta['last_test_ok']) ? (bool) $meta['last_test_ok'] : null,
            'last_test_message' => (string) ($meta['last_test_message'] ?? ''),
            'encrypted' => str_starts_with((string) ($integrations[self::KEY_URL] ?? ''), 'sc1:'),
        ];
    }

    /**
     * Webhooks nommés, sans leur lien.
     *
     * @return list<array{id:string, name:string, channel:string, masked:string, created_at:string, last_test_at:string, last_test_ok:?bool, last_test_message:string}>
     */
    public function list(int $tenantId): array
    {
        $out = [];
        foreach ($this->rawList($this->integrations($tenantId)) as $row) {
            $out[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'channel' => $row['channel'],
                'masked' => self::mask(self::reveal($row['url'])),
                'created_at' => $row['created_at'],
                'last_test_at' => $row['last_test_at'],
                'last_test_ok' => $row['last_test_ok'],
                'last_test_message' => $row['last_test_message'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $integrations
     * @return list<array{id:string, name:string, channel:string, url:string, created_at:string, last_test_at:string, last_test_ok:?bool, last_test_message:string}>
     */
    private function rawList(array $integrations): array
    {
        $raw = is_array($integrations[self::KEY_LIST] ?? null) ? $integrations[self::KEY_LIST] : [];
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row) || preg_match('/^[a-z0-9]{6,20}$/', (string) ($row['id'] ?? '')) !== 1) {
                continue;
            }
            $out[] = [
                'id' => (string) $row['id'],
                'name' => (string) ($row['name'] ?? 'Salon Discord'),
                'channel' => (string) ($row['channel'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'last_test_at' => (string) ($row['last_test_at'] ?? ''),
                'last_test_ok' => isset($row['last_test_ok']) ? (bool) $row['last_test_ok'] : null,
                'last_test_message' => (string) ($row['last_test_message'] ?? ''),
            ];
        }

        return $out;
    }

    /** Lien en clair d'un webhook (« organisation » ou identifiant nommé), pour l'envoi côté serveur uniquement. */
    public function urlFor(int $tenantId, string $id): ?string
    {
        if ($id === self::ORGANISATION) {
            return $this->organisationUrl($tenantId);
        }
        foreach ($this->rawList($this->integrations($tenantId)) as $row) {
            if ($row['id'] === $id) {
                $url = self::reveal($row['url']);

                return $url !== '' ? $url : null;
            }
        }

        return null;
    }

    public function exists(int $tenantId, string $id): bool
    {
        if ($id === self::ORGANISATION) {
            return $this->organisationUrl($tenantId) !== null;
        }
        foreach ($this->rawList($this->integrations($tenantId)) as $row) {
            if ($row['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Webhook Organisation. $rawUrl vide = garder le lien enregistré (il n'est jamais réaffiché) ; $clear = le retirer.
     *
     * @return array{ok:bool, message:string}
     */
    public function saveOrganisation(int $tenantId, string $rawUrl, string $name, string $channel, bool $clear = false): array
    {
        $integrations = $this->integrations($tenantId);
        $meta = is_array($integrations[self::KEY_META] ?? null) ? $integrations[self::KEY_META] : [];
        $rawUrl = trim($rawUrl);
        if ($clear) {
            $integrations[self::KEY_URL] = null;
            $meta = [];
        } elseif ($rawUrl !== '') {
            $problem = DiscordChannelInput::problem($rawUrl, 'Salon de l’organisation');
            $url = DiscordChannelInput::webhookUrl($rawUrl);
            if ($problem !== null || $url === '') {
                return ['ok' => false, 'message' => $problem ?? 'Lien webhook non reconnu.'];
            }
            $integrations[self::KEY_URL] = SecretCipher::encrypt($url);
            unset($meta['last_test_at'], $meta['last_test_ok'], $meta['last_test_message']);
        } elseif (self::reveal($integrations[self::KEY_URL] ?? '') !== '' && !str_starts_with((string) $integrations[self::KEY_URL], 'sc1:')) {
            // Ancien lien en clair : chiffré au premier enregistrement.
            $integrations[self::KEY_URL] = SecretCipher::encrypt(self::reveal($integrations[self::KEY_URL]));
        }
        $meta['name'] = mb_substr(trim($name), 0, 80);
        $meta['channel'] = self::cleanChannel($channel);
        $integrations[self::KEY_META] = $meta;
        $this->persist($tenantId, $integrations);

        if ($clear) {
            return ['ok' => true, 'message' => 'Le salon de l’organisation a été retiré. Les usages qui en dépendaient ne publient plus.'];
        }

        return ['ok' => true, 'message' => 'Salon de l’organisation enregistré.'];
    }

    /**
     * @return array{ok:bool, message:string, id?:string}
     */
    public function add(int $tenantId, string $name, string $channel, string $rawUrl): array
    {
        $url = DiscordChannelInput::webhookUrl($rawUrl);
        if ($url === '') {
            return ['ok' => false, 'message' => DiscordChannelInput::problem($rawUrl, 'Lien du salon') ?? 'Collez le lien webhook du salon.'];
        }
        $integrations = $this->integrations($tenantId);
        $list = $this->rawList($integrations);
        if (count($list) >= self::MAX_WEBHOOKS) {
            return ['ok' => false, 'message' => 'Douze salons maximum. Retirez-en un avant d’en ajouter un autre.'];
        }
        foreach ($list as $row) {
            if (hash_equals(self::reveal($row['url']), $url)) {
                return ['ok' => false, 'message' => 'Ce salon est déjà dans la liste (« ' . $row['name'] . ' »).'];
            }
        }
        $id = bin2hex(random_bytes(6));
        $name = mb_substr(trim($name), 0, 80);
        $list[] = [
            'id' => $id,
            'name' => $name !== '' ? $name : 'Salon Discord',
            'channel' => self::cleanChannel($channel),
            'url' => SecretCipher::encrypt($url),
            'created_at' => gmdate('c'),
            'last_test_at' => '',
            'last_test_ok' => null,
            'last_test_message' => '',
        ];
        $integrations[self::KEY_LIST] = $list;
        $this->persist($tenantId, $integrations);

        return ['ok' => true, 'message' => 'Salon ajouté. Choisissez maintenant les usages qui y partent.', 'id' => $id];
    }

    /** @return array{ok:bool, message:string} */
    public function remove(int $tenantId, string $id): array
    {
        $integrations = $this->integrations($tenantId);
        $list = $this->rawList($integrations);
        $kept = array_values(array_filter($list, static fn (array $r): bool => $r['id'] !== $id));
        if (count($kept) === count($list)) {
            return ['ok' => false, 'message' => 'Salon introuvable.'];
        }
        $integrations[self::KEY_LIST] = $kept;
        $this->persist($tenantId, $integrations);

        return ['ok' => true, 'message' => 'Salon retiré. Les usages qui y partaient passent sur le salon de l’organisation.'];
    }

    /**
     * Ancien réglage « Autre salon » (lien collé par événement) → webhook nommé chiffré. Renvoie son identifiant.
     *
     * @param array<string, mixed> $integrations modifié en place
     */
    public static function adoptLegacyUrl(array &$integrations, string $url, string $label): string
    {
        $url = DiscordChannelInput::webhookUrl($url);
        if ($url === '') {
            return '';
        }
        $list = is_array($integrations[self::KEY_LIST] ?? null) ? $integrations[self::KEY_LIST] : [];
        foreach ($list as $row) {
            if (is_array($row) && hash_equals(self::reveal($row['url'] ?? ''), $url)) {
                return (string) ($row['id'] ?? '');
            }
        }
        $id = bin2hex(random_bytes(6));
        $list[] = [
            'id' => $id,
            'name' => mb_substr($label, 0, 80),
            'channel' => '',
            'url' => SecretCipher::encrypt($url),
            'created_at' => gmdate('c'),
        ];
        $integrations[self::KEY_LIST] = $list;

        return $id;
    }

    /** @return array{ok:bool, message:string} */
    public function refreshChannel(int $tenantId, string $id): array
    {
        $url = $this->urlFor($tenantId, $id);
        if ($url === null) {
            return ['ok' => false, 'message' => __('events.discord_missing_webhook')];
        }
        try {
            $token = (new DiscordEventAttendanceService())->botToken($tenantId);
            $companion = new \App\Repositories\DiscordCompanionRepository();
            if ($token !== '') {
                $target = (new DiscordWebhookChannelService())->resolve($url, $token);
            } elseif ($companion->forTenant($tenantId) !== null) {
                $info = (new DiscordEventClient())->webhookInfo($url);
                $name = $companion->knownChannel($tenantId, (string) ($info['channel_id'] ?? ''));
                if ($name === null) throw new \RuntimeException('events.channel_unavailable');
                $target = ['channel' => $name];
            } else {
                throw new \RuntimeException('events.channel_needs_bot');
            }
            // Relire après le réseau, pour conserver les autres réglages enregistrés entre-temps.
            $integrations = $this->integrations($tenantId);
            if ($id === self::ORGANISATION) {
                if (!hash_equals($url, self::reveal($integrations[self::KEY_URL] ?? ''))) {
                    throw new \RuntimeException('events.channel_unavailable');
                }
                $meta = is_array($integrations[self::KEY_META] ?? null) ? $integrations[self::KEY_META] : [];
                $meta['channel'] = $target['channel'];
                $integrations[self::KEY_META] = $meta;
            } else {
                $list = $this->rawList($integrations);
                $found = false;
                foreach ($list as &$row) {
                    if ($row['id'] === $id && hash_equals($url, self::reveal($row['url']))) {
                        $row['channel'] = $target['channel'];
                        $found = true;
                        break;
                    }
                }
                unset($row);
                if (!$found) {
                    throw new \RuntimeException('events.channel_unavailable');
                }
                $integrations[self::KEY_LIST] = $list;
            }
            $this->persist($tenantId, $integrations);

            return ['ok' => true, 'message' => __('events.channel_refreshed', ['channel' => $target['channel']])];
        } catch (\Throwable $e) {
            $key = in_array($e->getMessage(), ['events.channel_needs_bot', 'events.channel_unavailable', 'events.discord_rate_limited', 'events.discord_failed', 'events.discord_invalid_webhook'], true)
                ? $e->getMessage() : 'events.channel_unavailable';

            return ['ok' => false, 'message' => __($key)];
        }
    }

    public function recordTest(int $tenantId, string $id, bool $ok, string $message): void
    {
        $integrations = $this->integrations($tenantId);
        $stamp = ['last_test_at' => gmdate('c'), 'last_test_ok' => $ok, 'last_test_message' => mb_substr($message, 0, 200)];
        if ($id === self::ORGANISATION) {
            $meta = is_array($integrations[self::KEY_META] ?? null) ? $integrations[self::KEY_META] : [];
            $integrations[self::KEY_META] = $stamp + $meta;
        } else {
            $list = $this->rawList($integrations);
            foreach ($list as $i => $row) {
                if ($row['id'] === $id) {
                    $list[$i] = $stamp + $row;
                }
            }
            $integrations[self::KEY_LIST] = $list;
        }
        $this->persist($tenantId, $integrations);
    }

    /** Modèle du message « lien pour compléter le profil » (variables entre accolades). */
    public function inviteTemplate(int $tenantId): string
    {
        return \App\Support\ProfileInviteDiscordMessage::normalizeTemplate((string) ($this->integrations($tenantId)['discord_invite_template'] ?? ''));
    }

    public function saveInviteTemplate(int $tenantId, string $template): void
    {
        $integrations = $this->integrations($tenantId);
        $template = trim(str_replace("\r\n", "\n", $template));
        $integrations['discord_invite_template'] = $template === '' || $template === \App\Support\ProfileInviteDiscordMessage::DEFAULT_TEMPLATE
            ? null
            : mb_substr($template, 0, \App\Support\ProfileInviteDiscordMessage::MAX_TEMPLATE);
        $this->persist($tenantId, $integrations);
    }

    /** « #recrutement », « recrutement » → « #recrutement » (libellé d'aide, jamais utilisé pour publier). */
    public static function cleanChannel(string $channel): string
    {
        $channel = trim(preg_replace('/[\x00-\x1F<>@]+/u', '', $channel) ?? '');
        $channel = ltrim($channel, '#');

        return $channel === '' ? '' : '#' . mb_substr($channel, 0, 60);
    }
}
