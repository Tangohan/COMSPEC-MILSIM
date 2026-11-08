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
use App\Support\ReconPhotoBlur;
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
        private ?DiscordWebhookDirectory $directory = null,
    ) {
        $this->tenants ??= new TenantRepository();
        $this->discord ??= new DiscordWebhookService();
    }

    /**
     * Réglage de chaque usage : coupé (off), salon de l'organisation (default) ou salon précis (custom + webhook).
     * « url » ne subsiste que pour un ancien réglage « Autre salon » pas encore réenregistré.
     *
     * @return array{default_url:string, never_saved:bool, events:array<string, array{mode:string, webhook:string, url:string}>}
     */
    public function state(int $tenantId): array
    {
        $settings = $this->tenants->getSettings($tenantId);
        $integrations = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
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
                'webhook' => trim((string) ($row['webhook'] ?? '')),
                'url' => trim((string) ($row['url'] ?? '')),
            ];
        }

        return [
            'default_url' => DiscordWebhookDirectory::reveal($integrations['discord_webhook_url'] ?? ''),
            'never_saved' => !isset($integrations[self::SETTINGS_EVENTS_KEY]),
            'events' => $events,
        ];
    }

    /**
     * Enregistre les usages. Pour chaque clé du catalogue : on = '1' (publier) et dest = 'organisation' ou l'identifiant
     * d'un salon nommé. Un usage absent du formulaire est coupé. Les anciens liens « Autre salon » sont repris dans le
     * carnet (chiffrés) avant d'être retirés du réglage.
     *
     * @param array<string, mixed> $eventsInput
     * @return array{ok:bool, message:string}
     */
    public function saveEvents(int $tenantId, array $eventsInput): array
    {
        $settings = $this->tenants->getSettings($tenantId);
        $integrations = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
        $previous = $this->state($tenantId)['events'];
        $known = [];
        foreach ((is_array($integrations['discord_webhooks'] ?? null) ? $integrations['discord_webhooks'] : []) as $w) {
            if (is_array($w) && isset($w['id'])) {
                $known[(string) $w['id']] = true;
            }
        }
        $events = [];
        foreach (DiscordWebhookCatalog::events() as $meta) {
            $key = $meta['key'];
            $row = is_array($eventsInput[$key] ?? null) ? $eventsInput[$key] : [];
            if ((string) ($row['on'] ?? '') !== '1') {
                $events[$key] = ['mode' => DiscordWebhookCatalog::MODE_OFF, 'webhook' => ''];
                continue;
            }
            $dest = trim((string) ($row['dest'] ?? DiscordWebhookDirectory::ORGANISATION));
            if ($dest === 'legacy') {
                $dest = DiscordWebhookDirectory::adoptLegacyUrl($integrations, (string) ($previous[$key]['url'] ?? ''), 'Salon « ' . $meta['label'] . ' »');
                $known[$dest] = $dest !== '';
            }
            if ($dest !== '' && $dest !== DiscordWebhookDirectory::ORGANISATION && !empty($known[$dest])) {
                $events[$key] = ['mode' => DiscordWebhookCatalog::MODE_CUSTOM, 'webhook' => $dest];
            } else {
                $events[$key] = ['mode' => DiscordWebhookCatalog::MODE_DEFAULT, 'webhook' => ''];
            }
        }
        $integrations[self::SETTINGS_EVENTS_KEY] = $events;
        $this->tenants->mergeSettings($tenantId, ['integrations' => $integrations]);

        return ['ok' => true, 'message' => 'Les usages Discord ont été enregistrés.'];
    }

    /**
     * Salon d'un usage. Salon précis disparu ou illisible → salon de l'organisation (« quand rien de spécifique n'est
     * défini, tout part sur le salon de l'organisation »).
     */
    public function resolveUrl(int $tenantId, string $eventKey): ?string
    {
        if ($tenantId < 2 || !DiscordWebhookCatalog::isKnown($eventKey)) {
            return null;
        }
        $state = $this->state($tenantId);
        $row = $state['events'][$eventKey] ?? ['mode' => DiscordWebhookCatalog::defaultMode($eventKey), 'webhook' => '', 'url' => ''];
        if ($row['mode'] === DiscordWebhookCatalog::MODE_OFF) {
            return null;
        }
        if ($row['mode'] === DiscordWebhookCatalog::MODE_CUSTOM) {
            $url = $row['webhook'] !== ''
                ? (string) ($this->directory()->urlFor($tenantId, $row['webhook']) ?? '')
                : $row['url'];
            if ($this->discord->isValidWebhookUrl($url)) {
                return $url;
            }
        }
        $default = $state['default_url'];

        return $this->discord->isValidWebhookUrl($default) ? $default : null;
    }

    private function directory(): DiscordWebhookDirectory
    {
        return $this->directory ??= new DiscordWebhookDirectory($this->tenants);
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
     * opérateur, mission, position, altitude, visée, prise de vue, zone, date/heure en jeu et réelle, météo, terminal
     * et lien vers la photo sur Athena. Une même photo n'est publiée qu'une fois.
     * Clichés du drone (app Drone du téléphone) : même salon. Fiche SSE : image floutée ; fiche confidentielle :
     * aucune image, seulement le message, le lien de lecture sur Athena (read_url) et une note de confidentialité.
     *
     * @param array<string, mixed> $meta données de la photo (+ map_id, world_name, location, game_date, game_time, read_url facultatifs)
     */
    public function notifyQuickPicture(int $tenantId, string $filePath, array $meta): void
    {
        $device = strtoupper(trim((string) ($meta['device_type'] ?? '')));
        if ($device !== '' && !in_array($device, ['CTAB', 'TABLET', 'PHONE', 'ATAK'], true)
            && !QuickPictureDiscordEmbed::isDronePhoto($meta)) {
            return;
        }
        $mode = QuickPictureDiscordEmbed::mode($meta);
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
        $readUrl = trim((string) ($meta['read_url'] ?? ''));
        if ($readUrl !== '' && !preg_match('#^https?://#i', $readUrl) && function_exists('url')) {
            $readUrl = url(ltrim($readUrl, '/'));
        }
        $ctx['read_url'] = preg_match('#^https?://#i', $readUrl) === 1 ? $readUrl : '';
        $ctx['viewer_url'] = $this->photoViewerUrl($meta, $mode, $ctx['read_url']);
        $sent = false;
        // Confidentiel : aucune image ne part (ni jointe, ni lien public). SSE : copie floutée seulement.
        $prepared = $mode === QuickPictureDiscordEmbed::MODE_CONFIDENTIAL
            ? null
            : $this->prepareDiscordPhoto($filePath, $mode === QuickPictureDiscordEmbed::MODE_SSE);
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
            // Jamais pour un cliché SSE ou confidentiel (le lien public montrerait l'image nette).
            $embed = QuickPictureDiscordEmbed::build($meta, $ctx + [
                'image_url' => $mode === QuickPictureDiscordEmbed::MODE_CLEAR ? $this->publicPhotoUrl($filePath) : '',
            ]);
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

    /** Vrai si cette photo est du ressort du salon « Quick Picture » (téléphone, tablette, drone). */
    public static function isQuickPictureDevice(array $meta): bool
    {
        $device = strtoupper(trim((string) ($meta['device_type'] ?? '')));

        return $device === '' || in_array($device, ['CTAB', 'TABLET', 'PHONE', 'ATAK'], true)
            || QuickPictureDiscordEmbed::isDronePhoto($meta);
    }

    /** Le salon Quick Picture de la communauté est-il réglé ? Sinon rien n'est à envoyer. */
    public function hasQuickPictureChannel(int $tenantId): bool
    {
        return $this->resolveUrl($tenantId, DiscordWebhookCatalog::KEY_QUICK_PICTURE) !== null;
    }

    /**
     * Un seul message Discord pour plusieurs photos d'un même joueur dans une même mission (10 au plus) :
     * le premier embed porte tous les détails, les suivants la photo, la position et la zone.
     * Confidentiel : sans image ; SSE : image floutée. Rend vrai quand Discord a accepté le message.
     *
     * @param list<array{path:string, meta:array<string, mixed>}> $items
     */
    public function sendQuickPictureGroup(int $tenantId, array $items): bool
    {
        $url = $this->resolveUrl($tenantId, DiscordWebhookCatalog::KEY_QUICK_PICTURE);
        if ($url === null) {
            return true;
        }
        $items = array_slice(array_values($items), 0, 10);
        if ($items === []) {
            return true;
        }
        $embeds = [];
        $files = [];
        $temps = [];
        try {
            $count = count($items);
            foreach ($items as $i => $item) {
                $meta = $item['meta'];
                $path = $item['path'];
                $mode = QuickPictureDiscordEmbed::mode($meta);
                $ctx = $this->quickPictureContext($tenantId, $meta);
                $readUrl = trim((string) ($meta['read_url'] ?? ''));
                if ($readUrl !== '' && !preg_match('#^https?://#i', $readUrl) && function_exists('url')) {
                    $readUrl = url(ltrim($readUrl, '/'));
                }
                $ctx['read_url'] = preg_match('#^https?://#i', $readUrl) === 1 ? $readUrl : '';
                $ctx['viewer_url'] = $this->photoViewerUrl($meta, $mode, $ctx['read_url']);
                $prepared = $mode === QuickPictureDiscordEmbed::MODE_CONFIDENTIAL
                    ? null
                    : $this->prepareDiscordPhoto($path, $mode === QuickPictureDiscordEmbed::MODE_SSE);
                if ($prepared !== null) {
                    $leaf = 'quick-picture-' . ($i + 1) . '-' . substr(hash('crc32b', $path), 0, 8) . '.jpg';
                    $files[] = ['path' => $prepared['path'], 'name' => $leaf];
                    if ($prepared['temp']) {
                        $temps[] = $prepared['path'];
                    }
                    $embed = QuickPictureDiscordEmbed::build($meta, $ctx + ['attachment' => $leaf]);
                } else {
                    $embed = QuickPictureDiscordEmbed::build($meta, $ctx + [
                        'image_url' => $mode === QuickPictureDiscordEmbed::MODE_CLEAR ? $this->publicPhotoUrl($path) : '',
                    ]);
                }
                if ($count > 1) {
                    $embed['title'] = mb_substr(($i + 1) . '/' . $count . ' · ' . (string) ($embed['title'] ?? ''), 0, 256);
                    if ($i > 0) {
                        $embed['fields'] = array_values(array_filter(
                            is_array($embed['fields'] ?? null) ? $embed['fields'] : [],
                            static fn (array $f): bool => in_array((string) ($f['name'] ?? ''), ['Position', 'Zone', 'Altitude'], true)
                        ));
                        unset($embed['description']);
                    }
                }
                $embeds[] = $embed;
            }
            $res = $this->discord->sendEmbedsWithFiles($url, $embeds, $files, 'Athena');
            if (empty($res['ok'])) {
                error_log('[discord/quick-picture] envoi groupé refusé : ' . (string) ($res['error'] ?? ''));
            }

            return !empty($res['ok']);
        } catch (\Throwable $e) {
            error_log('[discord/quick-picture] groupé ' . $e->getMessage());

            return false;
        } finally {
            foreach ($temps as $t) {
                @unlink($t);
            }
        }
    }

    /**
     * Lien « Ouvrir la photo sur Athena » de l'embed : lecteur d'images du site quand il existe
     * (ReconImageStorage::viewerUrl), sinon la photo elle-même, seulement pour un cliché sans restriction
     * (l'adresse publique d'un cliché SSE montrerait l'image nette). Toujours en https.
     *
     * @param array<string, mixed> $meta
     */
    private function photoViewerUrl(array $meta, string $mode, string $readUrl): string
    {
        if ($mode !== QuickPictureDiscordEmbed::MODE_CLEAR) {
            return '';
        }
        $id = (int) ($meta['photo_id'] ?? 0);
        $url = '';
        if ($id > 0 && method_exists(ReconImageStorage::class, 'viewerUrl')) {
            try {
                $url = trim((string) ReconImageStorage::viewerUrl($id));
            } catch (\Throwable) {
                $url = '';
            }
        }
        if ($url === '') {
            $url = $readUrl;
        }

        return str_starts_with(strtolower($url), 'https://') ? $url : '';
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
     * $blur (cliché SSE) : copie fortement floutée (ReconPhotoBlur) ; sans GD ni Imagick, ou si le flou
     * échoue, null (le message part sans image, jamais l'original net).
     *
     * @return array{path:string, temp:bool}|null
     */
    private function prepareDiscordPhoto(string $filePath, bool $blur = false): ?array
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
                if ($blur) {
                    $blurred = ReconPhotoBlur::blurGd($im);
                    imagedestroy($im);
                    if ($blurred === null) {
                        return null;
                    }
                    $im = $blurred;
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
        if ($blur) {
            // Sans GD (ou image illisible par GD) : Imagick, sinon pas d'image.
            $tmp = tempnam(sys_get_temp_dir(), 'qp_');
            if ($tmp === false) {
                return null;
            }
            if (ReconPhotoBlur::blurFileImagick($filePath, $tmp) && (int) @filesize($tmp) <= DiscordWebhookService::MAX_ATTACHMENT_BYTES) {
                return ['path' => $tmp, 'temp' => true];
            }
            @unlink($tmp);

            return null;
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
            return ['ok' => false, 'message' => 'Aucun salon ne reçoit cet usage. Renseignez le salon de l’organisation ou activez l’usage, puis enregistrez.'];
        }
        $label = DiscordWebhookCatalog::byKey()[$key]['label'] ?? 'essai';
        $result = $this->discord->send($url, 'Essai Athena : relais « ' . $label . ' » opérationnel.');
        if (!empty($result['ok'])) {
            return ['ok' => true, 'message' => 'Un message d’essai a été envoyé dans le salon.'];
        }

        return ['ok' => false, 'message' => (string) ($result['error'] ?? 'Discord a refusé le message d’essai.')];
    }

    /**
     * Bouton « Tester » d'un salon : « organisation », identifiant d'un salon nommé, ou lien pas encore enregistré
     * ($rawUrl, vérifié avant envoi). Le résultat est gardé pour l'affichage (dernier essai réussi / refusé).
     *
     * @return array{ok:bool, message:string}
     */
    public function testDestination(int $tenantId, string $id, string $rawUrl = '', string $communityName = ''): array
    {
        $rawUrl = trim($rawUrl);
        if ($rawUrl !== '') {
            $url = DiscordChannelInput::webhookUrl($rawUrl);
            if ($url === '') {
                return ['ok' => false, 'message' => DiscordChannelInput::problem($rawUrl, 'Ce lien') ?? 'Lien non reconnu.'];
            }
        } else {
            $url = (string) ($this->directory()->urlFor($tenantId, $id) ?? '');
            if ($url === '') {
                return ['ok' => false, 'message' => 'Aucun lien n’est enregistré pour ce salon. Collez le lien webhook puis enregistrez.'];
            }
        }
        $embed = [
            'title' => 'Essai Athena',
            'description' => 'Ce salon est bien relié à ' . ($communityName !== '' ? $communityName : 'votre communauté') . ' sur Athena. Vous pouvez supprimer ce message.',
            'color' => 0x34D399,
            'timestamp' => gmdate('c'),
        ];
        try {
            $result = $this->discord->sendRawEmbed($url, $embed, 'Athena');
        } catch (\Throwable) {
            $result = ['ok' => false, 'error' => 'Discord injoignable pour le moment.'];
        }
        $ok = !empty($result['ok']);
        $message = $ok
            ? 'Message d’essai publié. Vérifiez qu’il est apparu dans le bon salon.'
            : (string) ($result['error'] ?? 'Discord a refusé le message d’essai.');
        if ($rawUrl === '') {
            try {
                $this->directory()->recordTest($tenantId, $id, $ok, $message);
            } catch (\Throwable) {
            }
        }

        return ['ok' => $ok, 'message' => $message];
    }
}
