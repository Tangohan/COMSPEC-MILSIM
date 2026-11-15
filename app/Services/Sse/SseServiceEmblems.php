<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseServiceEmblemRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Security\FileRateLimiter;
use App\Support\SseImageStorage;
use App\Support\SseWantedServiceAdmin;
use App\Support\SseWantedServices;

/**
 * Emblème téléversé d'un service demandeur (avis de recherche, notices, fiches de vigilance).
 *
 * Contrôles d'un téléversement : droit « Gérer » de la section et communauté de la session, service connu de la communauté, case de
 * confirmation cochée (« emblème de votre communauté ou création originale »), débit limité, 1 Mo au plus, vrai type lu dans le
 * CONTENU (PNG, JPEG ou WebP ; un SVG est refusé), réencodage GD (aucune métadonnée gardée, EXIF compris), plus grand côté ramené
 * à 512 px, miniature de 96 px, transparence conservée. Les fichiers vont dans l'espace privé du portail (SseImageStorage, dossier
 * « sse-service-emblems ») et ne sortent que par la route contrôlée des services. Chaque pose et chaque retrait sont écrits au
 * journal d'audit avec l'auteur.
 *
 * Sans la table sse_service_emblems (étape de base de données en attente) : aucun emblème, téléversement fermé avec un message
 * clair, emblème générique partout.
 */
final class SseServiceEmblems
{
    public const FOLDER = 'sse-service-emblems';

    public const MAX_BYTES = 1048576;

    public const MAX_SIDE = 512;

    public const THUMB_SIDE = 96;

    public const MIN_SIDE = 32;

    /** Plus grand côté accepté à l'entrée (au-delà : image refusée avant tout décodage). */
    public const MAX_SOURCE_SIDE = 4096;

    public const RATE_MAX = 12;

    public const RATE_WINDOW = 600;

    /** Règle affichée dans la fenêtre de téléversement, à confirmer par celui qui téléverse. */
    public const RULE = 'Emblème de votre communauté ou création originale : pas de sceau, logo ou insigne officiel réel.';

    public const CONFIRM_LABEL = 'Je confirme que cet emblème appartient à ma communauté ou est une création originale.';

    public const NOT_READY = 'Le téléversement d’un emblème demande l’étape de base de données « alertes et emblèmes de service », encore en attente : les services gardent leur emblème générique.';

    private const TYPE_ERROR = 'Image refusée : envoyez un fichier PNG, JPEG ou WebP (un fichier SVG n’est pas accepté).';

    private const SIZE_ERROR = 'Image refusée : 1 Mo au plus.';

    private string $privateDir;

    public function __construct(
        private ?SseServiceEmblemRepository $repo = null,
        ?string $privateDir = null,
        private ?SseWantedSettingsRepository $settings = null,
        private ?FileRateLimiter $limiter = null,
        private ?SseAuditLogRepository $audit = null,
    ) {
        $this->privateDir = SseImageStorage::privateRoot($privateDir);
    }

    /** La table des emblèmes existe-t-elle (étape de base de données passée) ? */
    public static function ready(): bool
    {
        return SseServiceEmblemRepository::ready();
    }

    /** Adresse contrôlée de l'emblème d'un service (jamais une adresse de fichier). */
    public static function url(string $code, bool $thumb = false, int $version = 0): string
    {
        $path = 'atak/sse/avis/services/' . rawurlencode($code) . '/embleme';
        $query = array_filter(['v' => $thumb ? 'mini' : '', 'e' => $version > 0 ? (string) $version : '']);
        $path .= $query !== [] ? '?' . http_build_query($query) : '';

        return function_exists('url') ? (string) url($path) : '/' . $path;
    }

    /**
     * Octets de l'emblème d'un service, prêts pour TCPDF (PNG ou JPEG réencodé) ; null sans emblème, sans la table ou si le
     * fichier manque.
     */
    public function bytesFor(int $tenantId, string $code): ?string
    {
        $found = $this->resolve($tenantId, $code, false);
        if ($found === null) {
            return null;
        }
        $bytes = @file_get_contents($found['file']);

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /** Étiquette de l'emblème en place (« svc:<code>:<version> »), ou chaîne vide sans emblème. */
    public function refFor(int $tenantId, string $code): string
    {
        $row = $this->metaFor($tenantId, $code);

        return $row !== null ? SseWantedServices::emblemRef((string) $row['service_code'], (int) $row['version']) : '';
    }

    /** @return array<string, mixed>|null mesures, version et confirmation de l'emblème d'un service */
    public function metaFor(int $tenantId, string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($tenantId < 1 || $code === '' || !self::ready()) {
            return null;
        }

        return $this->repo()->find($tenantId, $code);
    }

    /** @return array<string, array<string, mixed>> code du service => emblème, pour toute la communauté */
    public function listFor(int $tenantId): array
    {
        return self::ready() ? $this->repo()->forTenant($tenantId) : [];
    }

    /**
     * Fichier à servir par la route contrôlée : l'emblème de CETTE communauté seulement, type vérifié par le contenu.
     *
     * @return array{file: string, mime: string, version: int, meta: array<string, mixed>}|null
     */
    public function resolve(int $tenantId, string $code, bool $thumb): ?array
    {
        $row = $this->metaFor($tenantId, $code);
        if ($row === null || (int) $row['tenant_id'] !== $tenantId) {
            return null;
        }
        $path = $thumb && $row['thumb_path'] !== null ? (string) $row['thumb_path'] : (string) $row['path'];
        // Le nom du fichier porte la communauté : un chemin d'une autre communauté n'est jamais servi.
        if (!str_starts_with($path, SseImageStorage::privatePath(self::FOLDER, $tenantId . '_'))) {
            return null;
        }
        $abs = SseImageStorage::absolute($path, null, $this->privateDir);
        $mime = $abs !== null ? SseImageStorage::verifiedMime($abs) : null;
        if ($abs === null || !in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return ['file' => $abs, 'mime' => $mime, 'version' => (int) $row['version'], 'meta' => $row];
    }

    /**
     * Pose ou remplace l'emblème d'un service.
     *
     * @param string $sourceFile fichier reçu (déjà vérifié comme envoi par le contrôleur)
     * @param bool $confirmed case « emblème de la communauté ou création originale » cochée par celui qui téléverse
     * @return array{ok: true, version: int, ref: string, width: int, height: int}|array{ok: false, error: string}
     */
    public function store(int $tenantId, string $code, string $sourceFile, bool $confirmed, SseWantedAccess $access): array
    {
        $code = strtoupper(trim($code));
        if (!$access->canAdmin() || $tenantId < 1 || $access->tenantId() !== $tenantId) {
            return self::fail('Action non autorisée.');
        }
        if (!self::ready()) {
            return self::fail(self::NOT_READY);
        }
        $config = $this->settings()->get($tenantId);
        $service = SseWantedServices::find($code, $config);
        if ($service === null) {
            return self::fail('Service introuvable.');
        }
        if (!$confirmed) {
            return self::fail('Cochez la confirmation : l’emblème appartient à votre communauté ou est une création originale, sans sceau, logo ni insigne officiel réel.');
        }
        if (($this->limiter ??= new FileRateLimiter())->tooManyAttempts(self::rateKey($tenantId, $access->userId()), self::RATE_MAX, self::RATE_WINDOW)) {
            return self::fail('Trop de téléversements en peu de temps : réessayez dans quelques minutes.');
        }
        $size = @filesize($sourceFile);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            return self::fail(self::SIZE_ERROR);
        }
        // Vrai type : les premiers octets du fichier, jamais son nom ni le type annoncé par le navigateur.
        $mime = SseImageStorage::verifiedMime($sourceFile);
        $bytes = (string) @file_get_contents($sourceFile);
        if ($mime === null || SseIdImageService::sniff($bytes) !== $mime) {
            return self::fail(self::TYPE_ERROR);
        }
        $made = self::process($bytes, $mime);
        if (is_string($made)) {
            return self::fail($made);
        }
        $ext = $made['format'];
        $dir = $this->privateDir . DIRECTORY_SEPARATOR . self::FOLDER;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return self::fail('Enregistrement de l’emblème impossible.');
        }
        $name = $tenantId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $thumbName = $tenantId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $made['data']) === false || @file_put_contents($dir . DIRECTORY_SEPARATOR . $thumbName, $made['thumb']) === false) {
            @unlink($dir . DIRECTORY_SEPARATOR . $name);

            return self::fail('Enregistrement de l’emblème impossible.');
        }
        $before = $this->repo()->find($tenantId, $code);
        $label = $access->actorLabel() !== '' ? $access->actorLabel() : 'Gestionnaire';
        try {
            $version = $this->repo()->put($tenantId, $code, [
                'path' => SseImageStorage::privatePath(self::FOLDER, $name), 'thumb_path' => SseImageStorage::privatePath(self::FOLDER, $thumbName),
                'mime' => $ext === 'png' ? 'image/png' : 'image/jpeg', 'width' => $made['width'], 'height' => $made['height'], 'bytes' => strlen($made['data']),
            ], $access->userId(), $label);
        } catch (\Throwable $e) {
            @unlink($dir . DIRECTORY_SEPARATOR . $name);
            @unlink($dir . DIRECTORY_SEPARATOR . $thumbName);
            error_log('Emblèmes de service : enregistrement impossible : ' . $e->getMessage());

            return self::fail('Enregistrement de l’emblème impossible.');
        }
        if ($before !== null) {
            $this->unlinkFiles($before);
        }
        $this->noteVersion($tenantId, $config, $code, $version, $access->userId());
        $this->trace($tenantId, $access, 'emblem_set', 'Emblème du service ' . $code . ' téléversé (version ' . $version . ').', self::auditShape($before), [
            'code' => $code, 'version' => $version, 'mime' => $ext === 'png' ? 'image/png' : 'image/jpeg', 'width' => $made['width'], 'height' => $made['height'],
            'bytes' => strlen($made['data']), 'confirmed' => true, 'confirmed_by' => $label, 'rule' => self::RULE,
        ]);

        return ['ok' => true, 'version' => $version, 'ref' => SseWantedServices::emblemRef($code, $version), 'width' => $made['width'], 'height' => $made['height']];
    }

    /**
     * Retire l'emblème d'un service (ligne et fichiers) : le service retrouve son emblème générique.
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function remove(int $tenantId, string $code, SseWantedAccess $access): array
    {
        $code = strtoupper(trim($code));
        if (!$access->canAdmin() || $tenantId < 1 || $access->tenantId() !== $tenantId) {
            return self::fail('Action non autorisée.');
        }
        if (!self::ready()) {
            return self::fail(self::NOT_READY);
        }
        $before = $this->repo()->find($tenantId, $code);
        if ($before === null) {
            return self::fail('Ce service n’a pas d’emblème téléversé.');
        }
        $this->repo()->delete($tenantId, $code);
        $this->unlinkFiles($before);
        $this->noteVersion($tenantId, $this->settings()->get($tenantId), $code, 0, $access->userId());
        $this->trace($tenantId, $access, 'emblem_removed', 'Emblème du service ' . $code . ' retiré.', self::auditShape($before), ['code' => $code, 'version' => 0]);

        return ['ok' => true];
    }

    /** Service supprimé : son emblème part avec lui (sans contrôle de droit : l'appelant vient de supprimer le service). */
    public function purge(int $tenantId, string $code): void
    {
        $code = strtoupper(trim($code));
        try {
            $before = self::ready() ? $this->repo()->find($tenantId, $code) : null;
            if ($before !== null) {
                $this->repo()->delete($tenantId, $code);
                $this->unlinkFiles($before);
            }
        } catch (\Throwable $e) {
            error_log('Emblèmes de service : purge impossible : ' . $e->getMessage());
        }
    }

    public static function rateKey(int $tenantId, ?int $userId): string
    {
        return 'sse-service-emblem:' . $tenantId . ':' . (int) $userId;
    }

    /**
     * Réencodage GD : métadonnées perdues (EXIF compris), plus grand côté ramené à 512 px, miniature de 96 px, transparence
     * gardée (sortie PNG pour un PNG ou un WebP, JPEG pour un JPEG).
     *
     * @return array{data: string, thumb: string, format: string, width: int, height: int}|string message d'erreur
     */
    public static function process(string $bytes, string $mime): array|string
    {
        if (!function_exists('imagecreatefromstring')) {
            return 'Le traitement des images est indisponible sur ce serveur : emblème non enregistré.';
        }
        $info = @getimagesizefromstring($bytes);
        if (!is_array($info) || $info[0] < 1 || $info[1] < 1) {
            return self::TYPE_ERROR;
        }
        if (max($info[0], $info[1]) > self::MAX_SOURCE_SIDE) {
            return 'Image refusée : ' . self::MAX_SOURCE_SIDE . ' px de côté au plus.';
        }
        if (min($info[0], $info[1]) < self::MIN_SIDE) {
            return 'Image refusée : ' . self::MIN_SIDE . ' px de côté au moins.';
        }
        $format = $mime === 'image/jpeg' ? 'jpg' : 'png';
        $main = SseIdImageService::reencode($bytes, 'emblem', self::MAX_SIDE, $format);
        $thumb = SseIdImageService::reencode($bytes, 'emblem', self::THUMB_SIDE, $format);
        if ($main === null || $thumb === null) {
            return self::TYPE_ERROR;
        }
        $size = @getimagesizefromstring($main[0]);

        return ['data' => $main[0], 'thumb' => $thumb[0], 'format' => $format, 'width' => is_array($size) ? (int) $size[0] : 0, 'height' => is_array($size) ? (int) $size[1] : 0];
    }

    private function repo(): SseServiceEmblemRepository
    {
        return $this->repo ??= new SseServiceEmblemRepository();
    }

    private function settings(): SseWantedSettingsRepository
    {
        return $this->settings ??= new SseWantedSettingsRepository();
    }

    /** @param array<string, mixed> $row */
    private function unlinkFiles(array $row): void
    {
        foreach ([(string) ($row['path'] ?? ''), (string) ($row['thumb_path'] ?? '')] as $path) {
            $abs = $path !== '' ? SseImageStorage::absolute($path, null, $this->privateDir) : null;
            if ($abs !== null) {
                @unlink($abs);
            }
        }
    }

    /**
     * Répète la version de l'emblème dans les réglages de la communauté (« emblem_ref » des services, sans requête).
     *
     * @param array<string, mixed> $config
     */
    private function noteVersion(int $tenantId, array $config, string $code, int $version, ?int $userId): void
    {
        try {
            $this->settings()->save($tenantId, SseWantedServiceAdmin::setEmblemVersion($config, $code, $version), $userId);
        } catch (\Throwable $e) {
            error_log('Emblèmes de service : version non notée dans les réglages : ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    private static function auditShape(?array $row): ?array
    {
        return $row === null ? null : [
            'code' => $row['service_code'], 'version' => $row['version'], 'mime' => $row['mime'], 'width' => $row['width'], 'height' => $row['height'],
            'bytes' => $row['bytes'], 'confirmed_by' => $row['confirmed_by_label'], 'confirmed_at' => $row['confirmed_at'],
        ];
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function trace(int $tenantId, SseWantedAccess $access, string $action, string $reason, ?array $before, array $after): void
    {
        try {
            ($this->audit ??= new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $access->userId(),
                'actor_label' => $access->actorLabel() !== '' ? $access->actorLabel() : 'Avis de recherche',
                'action' => 'wanted.services.' . $action,
                'object_type' => 'sse_service_emblem',
                'object_id' => 0,
                'reason' => $reason,
                'before' => $before,
                'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Emblèmes de service : audit indisponible : ' . $e->getMessage());
        }
    }

    /** @return array{ok: false, error: string} */
    private static function fail(string $error): array
    {
        return ['ok' => false, 'error' => $error];
    }
}
