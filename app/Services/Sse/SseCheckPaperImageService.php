<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Services\Security\FileRateLimiter;
use App\Support\SseImageStorage;

/**
 * Photo du papier présenté lors d'un contrôle d'identité, prise en jeu par le contrôleur (visionneuse du mod SSE) et
 * envoyée par COMSPEC Link 2.0.127 (POST /api/sse/identity/checks/image, même canal que les autres photos du mod).
 *
 * Réception :
 *  - le contrôle est cherché dans la communauté de la clé de jeu (identifiant ou clé d'idempotence) : introuvable sinon,
 *    qu'il n'existe pas ou qu'il soit d'une autre communauté ;
 *  - 1,5 Mo au plus, vrai type lu dans le contenu (JPEG, PNG ou WebP), image réencodée (métadonnées EXIF, GPS et XMP
 *    perdues), miniature, rangement PRIVÉ (SseImageStorage, dossier « sse-id-checks », jamais sous public/) ;
 *  - débit borné par communauté et par compte ; chaque réception est notée au journal d'audit SSE ;
 *  - une nouvelle photo du même contrôle remplace la précédente ; le même fichier renvoyé n'est rangé qu'une fois.
 *
 * Rangement : sous « paper_image » des résultats du contrôle (sse_id_checks.results_json), à côté de « paper » : aucune
 * colonne, aucune migration. SseIdCheckRepository::hydrate retire cette entrée des résultats lus (« has_paper_image »
 * seulement) : les chemins ne sortent jamais par un écran ou une API qui recopie les résultats. Le papier consigné depuis le contrôle (sse_id_checks.document_id) retrouve la photo par
 * ce rattachement, sans copie.
 *
 * Lecture : seulement par la route contrôlée /atak/sse/papiers/controles/{id}/photo-papier. Si l'identité du contrôle
 * (ou du papier rattaché) est celle d'un MEMBRE (TRÈS SECRET (TS), SseMemberImageGate), la photo n'est ni listée ni
 * servie sans l'autorisation hiérarchique du lecteur ; un papier secret (couverture, faux) demande l'habilitation qui
 * ouvre la vérité de jeu.
 */
final class SseCheckPaperImageService
{
    public const MAX_BYTES = 1_572_864;

    public const RESULT_KEY = 'paper_image';

    public const RATE_MAX = 30;

    public const RATE_WINDOW = 600;

    private const MAX_RESULTS_BYTES = 60000;

    private Database $db;

    private SseIdCheckRepository $checks;

    /**
     * @param (\Closure(): bool)|null $truth le lecteur a-t-il l'habilitation qui ouvre la vérité de jeu (papiers secrets) ?
     */
    public function __construct(
        ?Database $db = null,
        ?SseIdCheckRepository $checks = null,
        private ?SseIdImageService $images = null,
        private ?SseMemberImageGate $gate = null,
        private ?FileRateLimiter $limiter = null,
        private ?string $privateDir = null,
        private ?\Closure $truth = null,
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->checks = $checks ?? new SseIdCheckRepository($this->db);
    }

    private function images(): SseIdImageService
    {
        return $this->images ??= new SseIdImageService(null, null, $this->privateDir);
    }

    private function gate(): SseMemberImageGate
    {
        return $this->gate ??= SseMemberImageGate::forSession();
    }

    /** Clé du plafond de débit : par communauté et par compte relié (0 : clé de communauté seule). */
    public static function rateKey(int $tenantId, ?int $userId): string
    {
        return 'sse_check_paper_image|' . $tenantId . '|' . (int) $userId;
    }

    /**
     * Range la photo du papier d'un contrôle de cette communauté.
     *
     * @param array{check_id?: mixed, idempotency_key?: mixed} $ref désignation du contrôle
     * @return array{ok: true, id: int, ref: string, duplicate: bool}|array{ok: false, error: string, status: int}
     */
    public function ingest(int $tenantId, array $ref, string $bytes, ?int $userId = null): array
    {
        if ($tenantId < 1) {
            return ['ok' => false, 'error' => 'not_found', 'status' => 404];
        }
        $limiter = $this->limiter ??= new FileRateLimiter();
        if ($limiter->tooManyAttempts(self::rateKey($tenantId, $userId), self::RATE_MAX, self::RATE_WINDOW)) {
            return ['ok' => false, 'error' => 'too_many_requests', 'status' => 429];
        }
        $check = $this->locate($tenantId, $ref);
        if ($check === null) {
            return ['ok' => false, 'error' => 'not_found', 'status' => 404];
        }
        if ($bytes === '') {
            return ['ok' => false, 'error' => 'image_required', 'status' => 422];
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'too_large', 'status' => 413];
        }
        if (SseIdImageService::sniff($bytes) === null) {
            return ['ok' => false, 'error' => 'invalid_type', 'status' => 415];
        }
        $sha1 = sha1($bytes);
        $previous = $this->entries($tenantId, [(int) $check['id']])[(int) $check['id']] ?? null;
        if ($previous !== null && $previous['source_sha1'] === $sha1 && SseImageStorage::absolute($previous['path'], null, $this->privateDir) !== null) {
            return ['ok' => true, 'id' => (int) $check['id'], 'ref' => (string) $check['ref'], 'duplicate' => true];
        }

        $stored = $this->images()->storeBytes($bytes, 'check_paper', $tenantId);
        if (!$stored['ok'] || !isset($stored['path'])) {
            return ['ok' => false, 'error' => 'invalid_type', 'status' => 415];
        }
        $thumb = $this->images()->storeBytes($bytes, 'check_paper_thumb', $tenantId);
        $abs = (string) SseImageStorage::absolute($stored['path'], null, $this->privateDir);
        $info = $abs !== '' ? @getimagesize($abs) : false;
        $entry = [
            'path' => $stored['path'],
            'thumb_path' => ($thumb['ok'] ?? false) ? ($thumb['path'] ?? null) : null,
            'width' => is_array($info) ? (int) $info[0] : 0,
            'height' => is_array($info) ? (int) $info[1] : 0,
            'bytes' => $abs !== '' ? (int) filesize($abs) : 0,
            'source_sha1' => $sha1,
            'received_at' => gmdate('Y-m-d H:i:s'),
            'by_user_id' => $userId !== null && $userId > 0 ? $userId : null,
        ];
        if (!$this->write($tenantId, (int) $check['id'], $entry)) {
            $this->unlink([$entry['path'], $entry['thumb_path']]);

            return ['ok' => false, 'error' => 'unavailable', 'status' => 503];
        }
        if ($previous !== null) {
            $this->unlink([$previous['path'], $previous['thumb_path']]);
        }
        $this->audit($tenantId, $userId, (int) $check['id'], [
            'check_ref' => $check['ref'], 'document_id' => $check['document_id'] ?? null, 'identity_id' => $check['identity_id'] ?? null,
            'bytes' => $entry['bytes'], 'width' => $entry['width'], 'height' => $entry['height'], 'replaced' => $previous !== null,
        ]);

        return ['ok' => true, 'id' => (int) $check['id'], 'ref' => (string) $check['ref'], 'duplicate' => false];
    }

    /**
     * Entrée « paper_image » lue dans les résultats bruts d'un contrôle : null sans photo, ou si le chemin gardé n'est
     * pas un chemin du dossier privé des photos de contrôle.
     *
     * @return array{path: string, thumb_path: ?string, width: int, height: int, bytes: int, source_sha1: string, received_at: string}|null
     */
    public static function parse(?string $resultsJson): ?array
    {
        $results = json_decode((string) $resultsJson, true);
        $e = is_array($results) ? ($results[self::RESULT_KEY] ?? null) : null;
        if (!is_array($e) || !is_string($e['path'] ?? null) || !SseImageStorage::isPrivatePath($e['path']) || !str_contains($e['path'], '/sse-id-checks/')) {
            return null;
        }
        $thumb = is_string($e['thumb_path'] ?? null) && SseImageStorage::isPrivatePath($e['thumb_path']) && str_contains($e['thumb_path'], '/sse-id-checks/') ? $e['thumb_path'] : null;

        return [
            'path' => $e['path'], 'thumb_path' => $thumb,
            'width' => (int) ($e['width'] ?? 0), 'height' => (int) ($e['height'] ?? 0), 'bytes' => (int) ($e['bytes'] ?? 0),
            'source_sha1' => (string) ($e['source_sha1'] ?? ''), 'received_at' => (string) ($e['received_at'] ?? ''),
        ];
    }

    /**
     * Le lecteur de la session peut-il voir la photo du papier de ce contrôle ?
     *
     * @param array<string, mixed> $check
     * @return array{allowed: bool, confidential: bool, reason: string, owner: ?int}
     */
    public function visibility(int $tenantId, array $check): array
    {
        $identityId = (int) ($check['identity_id'] ?? 0);
        $docId = (int) ($check['document_id'] ?? 0);
        $doc = $docId > 0 ? (new SseIdDocumentRepository())->find($docId, $tenantId) : null;
        if ($docId > 0 && $doc === null) {
            // Papier rattaché illisible : rien n'est montré (refus par défaut).
            return ['allowed' => false, 'confidential' => true, 'reason' => 'unreadable', 'owner' => null];
        }
        if ($doc !== null) {
            if (MemberIdentityPapersService::docIsSecret($doc) && !$this->truthAllowed()) {
                return ['allowed' => false, 'confidential' => true, 'reason' => 'clearance', 'owner' => null];
            }
            $identityId = $identityId > 0 ? $identityId : (int) ($doc['identity_id'] ?? 0);
        }
        if ($identityId < 1) {
            return ['allowed' => true, 'confidential' => false, 'reason' => '', 'owner' => null];
        }
        $r = $this->gate()->forIdentity($tenantId, $identityId);

        return ['allowed' => (bool) $r['allowed'], 'confidential' => (bool) $r['confidential'], 'reason' => (string) $r['reason'], 'owner' => $r['owner'] ?? null];
    }

    /**
     * Ce que les écrans affichent pour un contrôle : adresses de la route contrôlée (jamais un fichier), ou null si le
     * contrôle n'a pas de photo, si le fichier manque, ou si le lecteur n'y a pas droit (rien n'est alors listé).
     *
     * @param array<string, mixed> $check contrôle hydraté
     * @param array<string, mixed>|null $entry entrée déjà lue (listes), sinon lue ici
     * @return array{check_id: int, check_ref: string, url: string, thumb_url: string, width: int, height: int, received_at: string}|null
     */
    public function view(int $tenantId, array $check, ?array $entry = null): ?array
    {
        $id = (int) ($check['id'] ?? 0);
        $image = $entry ?? ($this->entries($tenantId, [$id])[$id] ?? null);
        if ($image === null || SseImageStorage::absolute($image['path'], null, $this->privateDir) === null) {
            return null;
        }
        try {
            if (!$this->visibility($tenantId, $check)['allowed']) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return [
            'check_id' => $id, 'check_ref' => (string) ($check['ref'] ?? ''),
            'url' => self::imageUrl($id, false), 'thumb_url' => self::imageUrl($id, true),
            'width' => $image['width'], 'height' => $image['height'], 'received_at' => $image['received_at'],
        ];
    }

    /**
     * Photos visibles d'une liste de contrôles, par identifiant de contrôle.
     *
     * @param list<array<string, mixed>> $checks
     * @return array<int, array{check_id: int, check_ref: string, url: string, thumb_url: string, width: int, height: int, received_at: string}>
     */
    public function views(int $tenantId, array $checks): array
    {
        $out = [];
        $entries = $this->entries($tenantId, array_map(static fn (array $c): int => (int) ($c['id'] ?? 0), $checks));
        foreach ($checks as $c) {
            $entry = $entries[(int) ($c['id'] ?? 0)] ?? null;
            $v = $entry !== null ? $this->view($tenantId, $c, $entry) : null;
            if ($v !== null) {
                $out[(int) $c['id']] = $v;
            }
        }

        return $out;
    }

    /**
     * Photos du papier consigné : celles des contrôles rattachés à ce papier, la plus récente d'abord.
     *
     * @return list<array{check_id: int, check_ref: string, url: string, thumb_url: string, width: int, height: int, received_at: string}>
     */
    public function forDocument(int $tenantId, int $documentId): array
    {
        if ($documentId < 1) {
            return [];
        }

        return array_values($this->views($tenantId, $this->checks->list($tenantId, ['document_id' => $documentId, 'limit' => 50])));
    }

    /** Adresse de la route contrôlée (jamais un fichier public). */
    public static function imageUrl(int $checkId, bool $thumb): string
    {
        $path = 'atak/sse/papiers/controles/' . $checkId . '/photo-papier' . ($thumb ? '?v=mini' : '');

        return function_exists('url') ? (string) url($path) : '/' . $path;
    }

    /**
     * Fichier à servir à ce lecteur : null si le contrôle est introuvable dans la communauté, sans photo, ou si le lecteur
     * n'y a pas droit (refus noté pour une image de membre).
     *
     * @return array{check: array<string, mixed>, file: string, mime: string, confidential: bool, reason: string, owner: ?int}|null
     */
    public function resolve(int $tenantId, int $checkId, bool $thumb): ?array
    {
        $check = $checkId > 0 ? $this->checks->find($checkId, $tenantId) : null;
        $image = $check !== null ? ($this->entries($tenantId, [$checkId])[$checkId] ?? null) : null;
        if ($check === null || $image === null) {
            return null;
        }
        $visible = $this->visibility($tenantId, $check);
        if (!$visible['allowed']) {
            if ($visible['confidential'] && $visible['reason'] !== 'clearance') {
                $this->gate()->recordDenied($tenantId, $this->gate()->viewerId(), 'check_paper_image', $checkId, $visible['owner']);
            }

            return null;
        }
        $path = $thumb && $image['thumb_path'] !== null ? $image['thumb_path'] : $image['path'];
        $abs = SseImageStorage::absolute($path, null, $this->privateDir);
        $mime = $abs !== null ? SseImageStorage::verifiedMime($abs) : null;
        if ($abs === null || $mime === null) {
            return null;
        }

        return ['check' => $check, 'file' => $abs, 'mime' => $mime, 'confidential' => $visible['confidential'], 'reason' => $visible['reason'], 'owner' => $visible['owner']];
    }

    // ------------------------------------------------------------------ Interne

    /**
     * Entrées « paper_image » de contrôles de la communauté, en une requête.
     *
     * @param list<int> $ids
     * @return array<int, array{path: string, thumb_path: ?string, width: int, height: int, bytes: int, source_sha1: string, received_at: string}>
     */
    private function entries(int $tenantId, array $ids): array
    {
        $ids = array_slice(array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0))), 0, 500);
        if ($ids === []) {
            return [];
        }
        $params = ['t' => $tenantId, 'k' => '%"' . self::RESULT_KEY . '"%'];
        $marks = [];
        foreach ($ids as $i => $id) {
            $marks[] = ':c' . $i;
            $params['c' . $i] = $id;
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT id, results_json FROM sse_id_checks WHERE tenant_id = :t AND results_json LIKE :k AND id IN (' . implode(', ', $marks) . ')', $params) as $r) {
            $e = self::parse((string) ($r['results_json'] ?? ''));
            if ($e !== null) {
                $out[(int) $r['id']] = $e;
            }
        }

        return $out;
    }

    /**
     * @param array{check_id?: mixed, idempotency_key?: mixed} $ref
     * @return array<string, mixed>|null
     */
    private function locate(int $tenantId, array $ref): ?array
    {
        $id = is_scalar($ref['check_id'] ?? null) ? (int) $ref['check_id'] : 0;
        if ($id > 0) {
            return $this->checks->find($id, $tenantId);
        }
        $key = is_scalar($ref['idempotency_key'] ?? null) ? trim((string) $ref['idempotency_key']) : '';

        return SseIdCheckService::validKey($key) ? $this->checks->findByKey($tenantId, $key) : null;
    }

    /** @param array<string, mixed> $entry */
    private function write(int $tenantId, int $checkId, array $entry): bool
    {
        $row = $this->db->fetchOne('SELECT results_json FROM sse_id_checks WHERE id = :id AND tenant_id = :t', ['id' => $checkId, 't' => $tenantId]);
        if ($row === null) {
            return false;
        }
        $results = json_decode((string) ($row['results_json'] ?? ''), true);
        $results = is_array($results) ? $results : [];
        $results[self::RESULT_KEY] = $entry;
        $json = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > self::MAX_RESULTS_BYTES) {
            return false;
        }
        $this->db->execute('UPDATE sse_id_checks SET results_json = :j WHERE id = :id AND tenant_id = :t', ['j' => $json, 'id' => $checkId, 't' => $tenantId]);

        return true;
    }

    /** @param list<?string> $paths */
    private function unlink(array $paths): void
    {
        foreach ($paths as $p) {
            $abs = is_string($p) ? SseImageStorage::absolute($p, null, $this->privateDir) : null;
            if ($abs !== null) {
                @unlink($abs);
            }
        }
    }

    private function truthAllowed(): bool
    {
        try {
            return $this->truth !== null ? (bool) ($this->truth)() : (new SseClearanceService())->allows(\App\Repositories\SseCaseRepository::CLASS_CONFIDENTIAL);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $after */
    private function audit(int $tenantId, ?int $userId, int $checkId, array $after): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'actor_label' => 'COMSPEC Link',
                'action' => 'id_check.paper_image_received',
                'object_type' => 'sse_id_check',
                'object_id' => $checkId,
                'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Photo du papier d’un contrôle : audit indisponible : ' . $e->getMessage());
        }
    }
}
