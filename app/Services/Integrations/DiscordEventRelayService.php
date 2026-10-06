<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Repositories\AtakMapRepository;
use App\Repositories\AtakTerrainRepository;
use App\Repositories\TenantRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Tactical\MissionWeatherService;
use App\Support\DiscordChannelInput;
use App\Support\DiscordWebhookCatalog;
use App\Support\QuickPictureDiscordEmbed;
use App\Support\ReconImageStorage;

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
        if ($defaultUrl !== '') {
            $problem = DiscordChannelInput::problem($defaultUrl, 'Salon commun');
            if ($problem !== null) {
                return ['ok' => false, 'message' => $problem];
            }
            $defaultUrl = DiscordChannelInput::webhookUrl($defaultUrl);
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
                if ($url === '') {
                    return [
                        'ok' => false,
                        'message' => '« ' . $meta['label'] . ' » est réglé sur « Autre salon » mais aucun lien webhook n’est renseigné. Collez l’URL du webhook de ce salon, ou choisissez « Salon commun ».',
                    ];
                }
                $problem = DiscordChannelInput::problem($url, '« ' . $meta['label'] . ' » (autre salon)');
                if ($problem !== null) {
                    return ['ok' => false, 'message' => $problem];
                }
                $url = DiscordChannelInput::webhookUrl($url);
            } else {
                $url = DiscordChannelInput::webhookUrl($url);
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
     * Appelée une fois la photo enregistrée sur Athena (fin de POST /api/recon/images) :
     * la photo est jointe au message (copie JPEG allégée) et affichée dans un embed avec
     * opérateur, position, zone, date/heure en jeu et réelle. Une même photo n'est publiée qu'une fois.
     *
     * @param array<string, mixed> $meta données de la photo (+ map_id, world_name, location, game_date, game_time facultatifs)
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
        if ($filePath === '' || !is_file($filePath)) {
            error_log('[discord/quick-picture] photo absente au moment du relais : ' . basename($filePath));
        }

        // Renvois réseau du jeu : même cliché (nom d'origine + auteur + heure) publié une seule fois.
        $dedupKey = 'discord_qp|' . $tenantId . '|' . hash('sha256', implode('|', [
            (string) ($meta['source_name'] ?? basename($filePath)),
            (string) ($meta['author_callsign'] ?? ''),
            (string) ($meta['captured_at'] ?? ''),
        ]));
        if (isset(self::$sentThisRequest[$dedupKey])) {
            return;
        }
        $limiter = null;
        try {
            $limiter = new FileRateLimiter();
            if ($limiter->tooManyAttempts($dedupKey, 1, 900)) {
                return;
            }
        } catch (\Throwable) {
            $limiter = null;
        }
        self::$sentThisRequest[$dedupKey] = true;

        $ctx = $this->quickPictureContext($tenantId, $meta);
        $sent = false;
        $prepared = $this->prepareDiscordPhoto($filePath);
        if ($prepared !== null) {
            $leaf = 'quick-picture-' . substr(hash('crc32b', $filePath), 0, 8) . '.jpg';
            $embed = QuickPictureDiscordEmbed::build($meta, $ctx + ['attachment' => $leaf]);
            try {
                $res = $this->discord->sendEmbedWithFile($url, $embed, $prepared['path'], $leaf, 'Athena');
                $sent = !empty($res['ok']);
                if (!$sent) {
                    error_log('[discord/quick-picture] envoi photo refusé : ' . (string) ($res['error'] ?? ''));
                }
            } catch (\Throwable $e) {
                error_log('[discord/quick-picture] ' . $e->getMessage());
            } finally {
                if ($prepared['temp']) {
                    @unlink($prepared['path']);
                }
            }
        }
        if (!$sent) {
            // Repli : embed avec lien public de la photo (si le site est en https), sinon texte seul.
            $embed = QuickPictureDiscordEmbed::build($meta, $ctx + ['image_url' => $this->publicPhotoUrl($filePath)]);
            try {
                $res = $this->discord->sendRawEmbed($url, $embed, 'Athena');
                $sent = !empty($res['ok']);
            } catch (\Throwable) {
                $sent = false;
            }
        }
        if (!$sent) {
            unset(self::$sentThisRequest[$dedupKey]);
            if ($limiter !== null) {
                $limiter->clear($dedupKey);
            }
        }
    }

    /**
     * Carte, lieu, date et heure en jeu pour l'embed Quick Picture.
     *
     * @param array<string, mixed> $meta
     * @return array{map_label:string, world_name:string, location:string, game_date:string, game_time:string, game_time_approx:bool, real_unix:int, timezone:string}
     */
    private function quickPictureContext(int $tenantId, array $meta): array
    {
        $mapId = (int) ($meta['map_id'] ?? 0);
        $world = trim((string) ($meta['world_name'] ?? ''));
        $mapLabel = '';
        if ($mapId > 0) {
            try {
                foreach ((new AtakMapRepository())->getAll() as $m) {
                    if ((int) ($m['id'] ?? 0) === $mapId) {
                        $mapLabel = trim((string) ($m['label'] ?? $m['name'] ?? ''));
                        break;
                    }
                }
            } catch (\Throwable) {
            }
            if ($world === '') {
                try {
                    $grid = (new AtakTerrainRepository())->getGrid($tenantId, $mapId, false);
                    $world = trim((string) ($grid['world_name'] ?? ''));
                } catch (\Throwable) {
                }
            }
        }

        $gameTime = trim((string) ($meta['game_time'] ?? ''));
        $approx = false;
        if ($gameTime === '' && $mapId > 0) {
            // Heure du jour remontée par le jeu avec la météo mission (instantané récent seulement).
            try {
                $weather = (new MissionWeatherService())->get($tenantId, $mapId);
                $updated = strtotime((string) ($weather['updated_at'] ?? '')) ?: 0;
                $age = time() - $updated;
                if ($updated > 0 && $age >= 0 && $age <= 3 * 3600 && is_numeric($weather['daytime'] ?? null)) {
                    $gameTime = QuickPictureDiscordEmbed::clockFromDaytime($weather['daytime']);
                    $approx = $age > 600;
                }
            } catch (\Throwable) {
            }
        }
        $captured = $meta['captured_at'] ?? null;
        $realUnix = is_numeric($captured) ? (int) $captured : (is_string($captured) ? (int) (strtotime($captured) ?: 0) : 0);
        if ($realUnix < 1_000_000_000) {
            $realUnix = time();
        }

        return [
            'map_label' => $mapLabel,
            'world_name' => $world,
            'location' => trim((string) ($meta['location'] ?? '')),
            'game_date' => trim((string) ($meta['game_date'] ?? '')),
            'game_time' => $gameTime,
            'game_time_approx' => $approx,
            'real_unix' => $realUnix,
            'timezone' => $this->tenantTimezone($tenantId),
        ];
    }

    private function tenantTimezone(int $tenantId): string
    {
        try {
            return trim((string) ($this->tenants->getSettings($tenantId)['timezone'] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Copie JPEG allégée (1920 px max) pour rester sous la limite Discord : une capture PNG
     * du jeu dépasse souvent 8 à 10 Mo et Discord la refusait (seul le texte partait).
     *
     * @return array{path:string, temp:bool}|null
     */
    private function prepareDiscordPhoto(string $filePath): ?array
    {
        if ($filePath === '' || !is_file($filePath) || !is_readable($filePath)) {
            return null;
        }
        $size = (int) @filesize($filePath);
        if ($size < 32) {
            return null;
        }
        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $bin = @file_get_contents($filePath);
            $im = is_string($bin) && $bin !== '' ? @imagecreatefromstring($bin) : false;
            unset($bin);
            if ($im !== false) {
                $w = imagesx($im);
                $h = imagesy($im);
                $max = 1920;
                if ($w > $max || $h > $max) {
                    $ratio = min($max / $w, $max / $h);
                    $nw = max(1, (int) round($w * $ratio));
                    $nh = max(1, (int) round($h * $ratio));
                    $dst = imagecreatetruecolor($nw, $nh);
                    if ($dst !== false) {
                        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        imagedestroy($im);
                        $im = $dst;
                    }
                } else {
                    // Fond opaque (PNG avec transparence → JPEG).
                    $dst = imagecreatetruecolor($w, $h);
                    if ($dst !== false) {
                        imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 0, 0, 0));
                        imagecopy($dst, $im, 0, 0, 0, 0, $w, $h);
                        imagedestroy($im);
                        $im = $dst;
                    }
                }
                $tmp = tempnam(sys_get_temp_dir(), 'qp_');
                if ($tmp !== false) {
                    $ok = @imagejpeg($im, $tmp, 85);
                    imagedestroy($im);
                    if ($ok && (int) @filesize($tmp) >= 32 && (int) @filesize($tmp) <= DiscordWebhookService::MAX_ATTACHMENT_BYTES) {
                        return ['path' => $tmp, 'temp' => true];
                    }
                    @unlink($tmp);
                } else {
                    imagedestroy($im);
                }
            }
        }
        // Sans GD : fichier d'origine s'il tient dans la limite Discord.
        $ext = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        if ($size <= DiscordWebhookService::MAX_ATTACHMENT_BYTES && in_array($ext, ['jpg', 'jpeg'], true)) {
            return ['path' => $filePath, 'temp' => false];
        }

        return null;
    }

    private function publicPhotoUrl(string $filePath): string
    {
        try {
            $u = ReconImageStorage::publicUrl(basename($filePath));
            if (!preg_match('#^https?://#i', $u) && function_exists('url')) {
                $u = url(ltrim($u, '/'));
            }

            return str_starts_with($u, 'https://') ? $u : '';
        } catch (\Throwable) {
            return '';
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
