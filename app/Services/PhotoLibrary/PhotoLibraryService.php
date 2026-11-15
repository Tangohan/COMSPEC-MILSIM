<?php

declare(strict_types=1);

namespace App\Services\PhotoLibrary;

use App\Repositories\ContentReportRepository;
use App\Repositories\MemberNotificationRepository;
use App\Repositories\PhotoLibraryRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Audit\AuditService;
use App\Support\ContentReportReason;

/**
 * Photothèque : tout ce que l'écran lit ou écrit passe ici, et chaque appel refait les contrôles de droits côté serveur
 * (PhotoPolicy) : lecture, visage, masque, commentaire, étiquette, versement SSE. Une photo qu'on ne peut pas voir est
 * « introuvable » (jamais « interdit »), comme une personne SSE hors de la porte SSE.
 *
 * Étiquetage de visages : MANUEL. Aucun calcul biométrique, aucune reconnaissance automatique, aucun envoi à un service
 * extérieur : un humain pose le cadre et choisit qui c'est (membre, sujet des fichiers SSE, ou inconnu).
 *
 * Les écritures renvoient {ok, error?, status, …} ; l'écran et le contrôleur n'ont rien à décider.
 */
final class PhotoLibraryService
{
    public const PER_PAGE = 24;
    public const COMMENT_MAX = 2000;
    public const MAX_LABELS = 12;

    private PhotoLibraryRepository $repo;
    private PhotoVault $vault;
    private ?PhotoSseTransfer $sse;

    /** @var \Closure(string, int, int, int, array<string, mixed>): void journal d'audit : action, communauté, compte, photo, détails */
    private \Closure $audit;

    /** @var \Closure(int, int, string, array<string, mixed>): void cloche : communauté, destinataire, type, contenu */
    private \Closure $notifier;

    /** @var \Closure(int, int, string, string, ?string, string, string): int signalement : communauté, auteur, motif, type, commentaire, adresse, nature */
    private \Closure $reporter;

    /** @var array<string, int|null> */
    private array $authorMemo = [];

    public function __construct(
        ?PhotoLibraryRepository $repo = null,
        ?PhotoVault $vault = null,
        ?PhotoSseTransfer $sse = null,
        ?\Closure $audit = null,
        ?\Closure $notifier = null,
        ?\Closure $reporter = null,
    ) {
        $this->repo = $repo ?? new PhotoLibraryRepository();
        $this->vault = $vault ?? new PhotoVault();
        $this->sse = $sse;
        $this->audit = $audit ?? static function (string $action, int $tenantId, int $userId, int $photoId, array $data): void {
            (new AuditService())->log($action, $tenantId, $userId, 'recon_image', $photoId, null, json_encode($data, JSON_UNESCAPED_UNICODE) ?: null);
        };
        $this->notifier = $notifier ?? static function (int $tenantId, int $userId, string $type, array $payload): void {
            (new MemberNotificationRepository())->create($tenantId, $userId, $type, $payload);
        };
        $this->reporter = $reporter ?? static fn (int $tenantId, int $reporter, string $reason, string $type, ?string $comment, string $url, string $kind): int => (new ContentReportRepository())->create($tenantId, $reporter, $reason, $type, $comment, $url, $kind);
    }

    public function repo(): PhotoLibraryRepository
    {
        return $this->repo;
    }

    private function sse(): PhotoSseTransfer
    {
        return $this->sse ??= new PhotoSseTransfer($this->repo);
    }

    // ------------------------------------------------------------------ Lecture

    /** @param array<string, mixed> $row @return array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} */
    public function facts(array $row): array
    {
        $author = (int) ($row['author_user_id'] ?? 0);
        if ($author < 1) {
            $key = (int) ($row['tenant_id'] ?? 0) . '|' . mb_strtolower((string) ($row['author_callsign'] ?? ''));
            if (!array_key_exists($key, $this->authorMemo)) {
                $this->authorMemo[$key] = $this->repo->uniqueMemberByCallsign((int) ($row['tenant_id'] ?? 0), (string) ($row['author_callsign'] ?? ''));
            }
            $author = (int) ($this->authorMemo[$key] ?? 0);
        }

        return PhotoPolicy::facts($row, $author > 0 ? $author : null, !empty($row['sse_versed']));
    }

    /**
     * Photos de la grille.
     *
     * @param array<string, string> $filters
     * @return array{ready: bool, rows: list<array<string, mixed>>, total: int, page: int, pages: int, facets: array<string, list<string>>, canMaskHere: bool}
     */
    public function browse(PhotoViewer $v, array $filters, string $sort, int $page): array
    {
        $out = ['ready' => false, 'rows' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'facets' => ['authors' => [], 'units' => [], 'albums' => [], 'labels' => []], 'canMaskHere' => PhotoMaskRenderer::available()];
        if (!$v->isMember() || !$this->repo->photosReady()) {
            return $out;
        }
        $out['ready'] = $this->repo->ready();
        $page = max(1, $page);
        $res = $this->repo->listPhotos($v->tenantId, $filters, $sort, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $v->sseDoor, $v->clearConfidential);
        $rows = [];
        foreach ($res['rows'] as $r) {
            if (!PhotoPolicy::canView($v, $this->facts($r))) {
                continue;
            }
            $rows[] = $this->card($r, $v);
        }
        $out['rows'] = $rows;
        $out['total'] = $res['total'];
        $out['page'] = $page;
        $out['pages'] = (int) ceil($res['total'] / self::PER_PAGE);
        $out['facets'] = $this->repo->facets($v->tenantId);

        return $out;
    }

    /** @param array<string, mixed> $r @return array<string, mixed> */
    private function card(array $r, PhotoViewer $v): array
    {
        $id = (int) $r['id'];
        $when = trim((string) ($r['captured_at'] ?? '')) !== '' && !str_starts_with((string) $r['captured_at'], '0000') ? (string) $r['captured_at'] : (string) ($r['created_at'] ?? '');

        return [
            'id' => $id,
            'title' => trim((string) ($r['title'] ?? '')) !== '' ? (string) $r['title'] : trim((string) ($r['caption'] ?? '')),
            'author' => (string) ($r['author_callsign'] ?? ''),
            'unit' => (string) ($r['unit_name'] ?? ''),
            'album' => (string) ($r['mission_id'] ?? ''),
            'grid' => (string) ($r['grid_ref'] ?? ''),
            'when' => $when,
            'sse' => !empty($r['sse_versed']),
            'confidential' => !empty($r['is_confidential']),
            'tags' => (int) ($r['tags_n'] ?? 0),
            'comments' => (int) ($r['comments_n'] ?? 0),
            'masks' => (int) ($r['masks_n'] ?? 0),
            'thumb' => url('jnet/photos/' . $id . '/image') . '?t=thumb&v=' . $this->version($r),
            'href' => url('jnet/photos/' . $id),
        ];
    }

    /** Version d'URL de l'image (change quand les zones ou le rangement changent). @param array<string, mixed> $r */
    private function version(array $r): string
    {
        return substr(sha1((string) ($r['id'] ?? '') . '|' . (string) ($r['image_path'] ?? '') . '|' . (string) ($r['masks_last'] ?? '') . '|' . (!empty($r['sse_versed']) ? 's' : '')), 0, 8);
    }

    /**
     * Fiche complète d'une photo pour le lecteur plein écran ; null si elle n'existe pas ou ne lui est pas visible.
     *
     * @return array<string, mixed>|null
     */
    public function detail(PhotoViewer $v, int $photoId): ?array
    {
        $row = $this->visiblePhoto($v, $photoId);
        if ($row === null) {
            return null;
        }
        $f = $this->facts($row);
        $tenant = $v->tenantId;
        $tags = $this->repo->ready() ? $this->repo->tags($tenant, $photoId) : [];
        $masks = $this->repo->ready() ? $this->repo->masks($tenant, $photoId) : [];
        $comments = $this->repo->ready() ? $this->repo->comments($tenant, $photoId) : [];
        $visibleTags = array_values(array_filter($tags, fn (array $t): bool => PhotoPolicy::canSeeTag($v, $f, ['target_type' => (string) $t['target_type'], 'author_user_id' => (int) $t['author_user_id']])));
        $visibleTagIds = array_flip(array_map(static fn (array $t): int => (int) $t['id'], $visibleTags));
        $hiddenTagIds = [];
        foreach ($tags as $t) {
            if (!isset($visibleTagIds[(int) $t['id']])) {
                $hiddenTagIds[(int) $t['id']] = true;
            }
        }

        $memberIds = [];
        $sseIds = [];
        foreach ($visibleTags as $t) {
            if ($t['target_type'] === 'member') {
                $memberIds[] = (int) $t['target_id'];
            } elseif ($t['target_type'] === 'sse') {
                $sseIds[] = (int) $t['target_id'];
            }
        }
        $authorIds = array_merge(
            $memberIds,
            array_map(static fn (array $t): int => (int) $t['author_user_id'], $visibleTags),
            array_map(static fn (array $c): int => (int) $c['author_user_id'], $comments),
            array_map(static fn (array $m): int => (int) $m['author_user_id'], $masks),
        );
        $mention = [];
        foreach ($comments as $c) {
            foreach (array_filter(array_map('intval', explode(',', (string) $c['mentions']))) as $mid) {
                $mention[] = $mid;
            }
        }
        $people = $this->repo->members($tenant, array_merge($authorIds, $mention));
        $subjects = $v->sseDoor ? $this->subjectLabels($tenant, $sseIds) : [];

        $outTags = [];
        foreach ($visibleTags as $t) {
            $type = (string) $t['target_type'];
            $label = match ($type) {
                'member' => $people[(int) $t['target_id']]['name'] ?? 'Membre',
                'sse' => $subjects[(int) $t['target_id']]['name'] ?? 'Sujet SSE',
                default => 'Inconnu',
            };
            $outTags[] = [
                'id' => (int) $t['id'], 'x' => (float) $t['x'], 'y' => (float) $t['y'], 'w' => (float) $t['w'], 'h' => (float) $t['h'],
                'type' => $type, 'target' => (int) ($t['target_id'] ?? 0), 'label' => $label,
                'sub' => $type === 'sse' ? ($subjects[(int) $t['target_id']]['ref'] ?? '') : '',
                'by' => $people[(int) $t['author_user_id']]['name'] ?? '',
                'canEdit' => PhotoPolicy::canEditTag($v, $f, ['target_type' => $type, 'author_user_id' => (int) $t['author_user_id']]),
            ];
        }
        $outMasks = array_map(fn (array $m): array => [
            'id' => (int) $m['id'], 'x' => (float) $m['x'], 'y' => (float) $m['y'], 'w' => (float) $m['w'], 'h' => (float) $m['h'], 'mode' => (string) $m['mode'],
            'canRemove' => PhotoPolicy::canRemoveMask($v, $f, ['author_user_id' => (int) $m['author_user_id']]),
        ], $masks);
        $outComments = [];
        foreach ($comments as $c) {
            // Un commentaire ancré sur un visage de personne SSE n'existe que pour la porte SSE.
            if ($c['anchor_kind'] === 'tag' && isset($hiddenTagIds[(int) $c['anchor_tag_id']])) {
                continue;
            }
            $outComments[] = [
                'id' => (int) $c['id'], 'body' => (string) $c['body'], 'author' => $people[(int) $c['author_user_id']]['name'] ?? 'Membre',
                'when' => (string) $c['created_at'], 'edited' => !empty($c['edited_at']),
                'mine' => (int) $c['author_user_id'] === $v->userId,
                'canEdit' => PhotoPolicy::canEditComment($v, $f, ['author_user_id' => (int) $c['author_user_id']]),
                'canDelete' => PhotoPolicy::canDeleteComment($v, $f, ['author_user_id' => (int) $c['author_user_id']]),
                'anchor' => $c['anchor_kind'] === 'none' ? null : [
                    'kind' => (string) $c['anchor_kind'], 'tag' => (int) ($c['anchor_tag_id'] ?? 0),
                    'x' => $c['anchor_x'] !== null ? (float) $c['anchor_x'] : null, 'y' => $c['anchor_y'] !== null ? (float) $c['anchor_y'] : null,
                    'w' => $c['anchor_w'] !== null ? (float) $c['anchor_w'] : null, 'h' => $c['anchor_h'] !== null ? (float) $c['anchor_h'] : null,
                ],
            ];
        }
        $transfer = $v->sseDoor ? $this->repo->activeTransfer($tenant, $photoId) : null;
        $canUnder = PhotoPolicy::canSeeUnderMask($v, $f);
        $when = trim((string) ($row['captured_at'] ?? '')) !== '' && !str_starts_with((string) $row['captured_at'], '0000') ? (string) $row['captured_at'] : (string) ($row['created_at'] ?? '');

        return [
            'photo' => [
                'id' => $photoId,
                'title' => trim((string) ($row['title'] ?? '')),
                'caption' => trim((string) ($row['caption'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')),
                'author' => (string) ($row['author_callsign'] ?? ''),
                'unit' => (string) ($row['unit_name'] ?? ''),
                'album' => (string) ($row['mission_id'] ?? ''),
                'grid' => (string) ($row['grid_ref'] ?? ''),
                'when' => $when,
                'device' => (string) ($row['device_type'] ?? ''),
                'sse' => $f['sse'],
                'confidential' => $f['confidential'],
                'image' => url('jnet/photos/' . $photoId . '/image') . '?v=' . $this->version($row + ['masks_last' => $masks === [] ? 0 : (int) max(array_map(static fn (array $m): int => (int) $m['id'], $masks))]),
                'original' => $canUnder && count($masks) > 0 ? url('jnet/photos/' . $photoId . '/image') . '?t=file&v=' . $this->version($row + ['masks_last' => $masks === [] ? 0 : (int) max(array_map(static fn (array $m): int => (int) $m['id'], $masks))]) : null,
                'page' => url('jnet/photos/' . $photoId),
            ],
            'rights' => [
                'tag' => PhotoPolicy::canTag($v, $f),
                'tagSse' => PhotoPolicy::canTagSse($v, $f),
                'mask' => PhotoPolicy::canMask($v, $f) && PhotoMaskRenderer::available(),
                'maskRequested' => PhotoPolicy::canMask($v, $f),
                'comment' => PhotoPolicy::canComment($v, $f),
                'label' => PhotoPolicy::canLabel($v, $f),
                'sse' => PhotoPolicy::canTransferToSse($v, $f),
                'withdraw' => PhotoPolicy::canWithdrawFromSse($v, $f),
            ],
            'maskedForYou' => count($masks) > 0 && !$canUnder,
            'gd' => PhotoMaskRenderer::available(),
            'tags' => $outTags,
            'masks' => $outMasks,
            'comments' => $outComments,
            'labels' => $this->repo->ready() ? $this->repo->labels($tenant, $photoId) : [],
            'sseInfo' => $transfer !== null ? [
                'by' => $this->repo->members($tenant, [(int) $transfer['actor_user_id']])[(int) $transfer['actor_user_id']]['name'] ?? '',
                'when' => (string) $transfer['created_at'],
                'identity' => (int) ($transfer['identity_id'] ?? 0) > 0 ? ($this->subjectLabels($tenant, [(int) $transfer['identity_id']])[(int) $transfer['identity_id']] ?? null) : null,
            ] : null,
            'mentionNames' => array_values(array_unique(array_filter(array_map(static fn (array $p): string => $p['callsign'] !== '' ? $p['callsign'] : $p['name'], $people)))),
            'ready' => $this->repo->ready(),
        ];
    }

    /** @param list<int> $ids @return array<int, array{name: string, ref: string}> */
    private function subjectLabels(int $tenantId, array $ids): array
    {
        $out = [];
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return $out;
        }
        try {
            foreach ((new SseIdentityRepository())->findMany($tenantId, $ids) as $i) {
                $out[(int) $i['id']] = ['name' => (string) ($i['display_name'] ?? 'Sujet'), 'ref' => (string) ($i['ref'] ?? '')];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /** Photo si le lecteur peut la voir, sinon null (absente). @return array<string, mixed>|null */
    public function visiblePhoto(PhotoViewer $v, int $photoId): ?array
    {
        if (!$v->isMember() || $photoId < 1) {
            return null;
        }
        $row = $this->repo->photo($v->tenantId, $photoId);
        if ($row === null || !PhotoPolicy::canView($v, $this->facts($row))) {
            return null;
        }
        if (preg_match('/^(chest|helmet|drone):/i', (string) ($row['unit_name'] ?? '')) === 1) {
            return null;
        }

        return $row;
    }

    /**
     * Image servie : la version masquée pour ceux qui n'ont pas le droit de voir dessous (le fichier servi EST masqué),
     * l'original rangé pour les autres. Jamais de repli vers l'original quand le lecteur n'y a pas droit.
     *
     * @return array{bytes: string, mime: string}|null
     */
    public function image(PhotoViewer $v, int $photoId, string $variant): ?array
    {
        $row = $this->visiblePhoto($v, $photoId);
        if ($row === null) {
            return null;
        }
        $f = $this->facts($row);
        $state = $this->repo->ready() ? $this->repo->state($v->tenantId, $photoId) : null;
        $original = $state !== null ? (string) ($state['original_path'] ?? '') : null;
        $hash = $state !== null ? (string) ($state['zones_hash'] ?? '') : null;
        $under = PhotoPolicy::canSeeUnderMask($v, $f);
        $hasMasks = $original !== null && $original !== '';
        // Des masques posés sans pouvoir les rendre (GD absent à la lecture) : seul le fichier déjà masqué est servi.
        if ($variant === 'file') {
            if ($under && $hasMasks) {
                $bytes = $this->vault->readOriginal($row, $original);

                return $bytes !== null ? ['bytes' => $bytes, 'mime' => $this->mime($bytes)] : null;
            }
            $abs = $this->vault->currentFile($row);
            $bytes = $abs !== null ? @file_get_contents($abs) : false;

            return is_string($bytes) && $bytes !== '' ? ['bytes' => $bytes, 'mime' => $this->mime($bytes)] : null;
        }
        $derived = $this->vault->derived($row, $original, $hash, $variant === 'thumb' ? 'thumb' : 'full', $under && $hasMasks);
        if ($derived !== null) {
            return $derived;
        }
        // Sans GD : le fichier servi (déjà masqué pour ceux qui n'ont pas le droit) tel quel, jamais l'original.
        if ($under && $hasMasks) {
            $bytes = $this->vault->readOriginal($row, $original);
        } else {
            $abs = $this->vault->currentFile($row);
            $bytes = $abs !== null ? @file_get_contents($abs) : null;
        }

        return is_string($bytes) && $bytes !== '' ? ['bytes' => $bytes, 'mime' => $this->mime($bytes)] : null;
    }

    private function mime(string $bytes): string
    {
        $i = @getimagesizefromstring($bytes);

        return is_array($i) && is_string($i['mime'] ?? null) && str_starts_with($i['mime'], 'image/') ? $i['mime'] : 'application/octet-stream';
    }

    /**
     * Cibles proposées pour un visage : membres de la communauté, et sujets SSE seulement pour la porte SSE.
     *
     * @return list<array{type: string, id: int, label: string, sub: string}>
     */
    public function targets(PhotoViewer $v, string $kind, string $q): array
    {
        if (!$v->isMember()) {
            return [];
        }
        $q = mb_substr(trim($q), 0, 60);
        if ($kind === 'member') {
            return array_map(static fn (array $m): array => ['type' => 'member', 'id' => $m['id'], 'label' => $m['name'], 'sub' => ''], $this->repo->searchMembers($v->tenantId, $q, 8));
        }
        if ($kind === 'sse' && $v->sseDoor && $q !== '') {
            try {
                $rows = (new SseIdentityRepository())->list($v->tenantId, ['q' => $q, 'limit' => 8]);
            } catch (\Throwable) {
                return [];
            }
            $out = [];
            foreach ($rows as $i) {
                if (($i['kind'] ?? '') === 'forged') {
                    continue;
                }
                $out[] = ['type' => 'sse', 'id' => (int) $i['id'], 'label' => (string) $i['display_name'], 'sub' => (string) ($i['ref'] ?? '')];
            }

            return $out;
        }

        return [];
    }

    // ------------------------------------------------------------------ Visages

    /**
     * @param array<string, mixed> $in x, y, w, h, type (member|sse|unknown), target
     * @return array<string, mixed>
     */
    public function addTag(PhotoViewer $v, int $photoId, array $in): array
    {
        $g = $this->guard($v, $photoId, 'tag');
        if (isset($g['ok'])) {
            return $g;
        }
        [$row, $f] = [$g['row'], $g['f']];
        $rect = PhotoGeometry::rect($in['x'] ?? null, $in['y'] ?? null, $in['w'] ?? null, $in['h'] ?? null);
        if ($rect === null) {
            return $this->fail('Cadre invalide : tracez un rectangle dans la photo.', 422);
        }
        $target = $this->target($v, $f, (string) ($in['type'] ?? ''), $in['target'] ?? null);
        if (isset($target['error'])) {
            return $this->fail((string) $target['error'], 422);
        }
        if ($this->repo->countTags($v->tenantId, $photoId) >= PhotoGeometry::MAX_TAGS) {
            return $this->fail('Trop de visages sur cette photo.', 422);
        }
        $id = $this->repo->addTag($v->tenantId, $photoId, $rect, $target['type'], $target['id'], $v->userId);
        if ($id < 1) {
            return $this->fail('Le visage n’a pas pu être enregistré.', 500);
        }
        $this->audit($v, 'photo_library.tag_add', $photoId, ['tag' => $id, 'type' => $target['type'], 'target' => $target['id']]);

        return ['ok' => true, 'id' => $id, 'status' => 200, 'row' => $row];
    }

    /** @param array<string, mixed> $in @return array<string, mixed> */
    public function updateTag(PhotoViewer $v, int $photoId, int $tagId, array $in): array
    {
        $g = $this->guard($v, $photoId, 'tag');
        if (isset($g['ok'])) {
            return $g;
        }
        $f = $g['f'];
        $tag = $this->repo->tag($v->tenantId, $photoId, $tagId);
        if ($tag === null || !PhotoPolicy::canEditTag($v, $f, ['target_type' => (string) $tag['target_type'], 'author_user_id' => (int) $tag['author_user_id']])) {
            return $this->fail('Visage introuvable.', 404);
        }
        $rect = null;
        if (isset($in['x']) || isset($in['y']) || isset($in['w']) || isset($in['h'])) {
            $rect = PhotoGeometry::rect($in['x'] ?? null, $in['y'] ?? null, $in['w'] ?? null, $in['h'] ?? null);
            if ($rect === null) {
                return $this->fail('Cadre invalide : tracez un rectangle dans la photo.', 422);
            }
        }
        $type = (string) $tag['target_type'];
        $targetId = $tag['target_id'] !== null ? (int) $tag['target_id'] : null;
        if (isset($in['type'])) {
            $target = $this->target($v, $f, (string) $in['type'], $in['target'] ?? null);
            if (isset($target['error'])) {
                return $this->fail((string) $target['error'], 422);
            }
            [$type, $targetId] = [$target['type'], $target['id']];
        }
        $this->repo->updateTag($v->tenantId, $photoId, $tagId, $rect, $type, $targetId);
        $this->audit($v, 'photo_library.tag_update', $photoId, ['tag' => $tagId, 'type' => $type, 'target' => $targetId]);

        return ['ok' => true, 'status' => 200];
    }

    /** @return array<string, mixed> */
    public function removeTag(PhotoViewer $v, int $photoId, int $tagId): array
    {
        $g = $this->guard($v, $photoId, 'tag');
        if (isset($g['ok'])) {
            return $g;
        }
        $tag = $this->repo->tag($v->tenantId, $photoId, $tagId);
        if ($tag === null || !PhotoPolicy::canEditTag($v, $g['f'], ['target_type' => (string) $tag['target_type'], 'author_user_id' => (int) $tag['author_user_id']])) {
            return $this->fail('Visage introuvable.', 404);
        }
        $this->repo->deleteTag($v->tenantId, $photoId, $tagId);
        $this->audit($v, 'photo_library.tag_remove', $photoId, ['tag' => $tagId, 'type' => (string) $tag['target_type']]);

        return ['ok' => true, 'status' => 200];
    }

    /**
     * Cible d'un visage : membre de la communauté, sujet SSE (porte SSE et fiche existante), ou inconnu.
     *
     * @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f
     * @return array{type: string, id: ?int}|array{error: string}
     */
    private function target(PhotoViewer $v, array $f, string $type, mixed $raw): array
    {
        $type = strtolower(trim($type));
        $id = is_scalar($raw) && preg_match('/^\d{1,10}$/', (string) $raw) === 1 ? (int) $raw : 0;
        if ($type === 'unknown') {
            return ['type' => 'unknown', 'id' => null];
        }
        if ($type === 'member') {
            if ($id < 1 || !isset($this->repo->members($v->tenantId, [$id])[$id])) {
                return ['error' => 'Membre introuvable.'];
            }

            return ['type' => 'member', 'id' => $id];
        }
        if ($type === 'sse') {
            if (!PhotoPolicy::canTagSse($v, $f) || $id < 1 || !$this->sse()->identityUsable($v->tenantId, $id)) {
                return ['error' => 'Personne introuvable.'];
            }

            return ['type' => 'sse', 'id' => $id];
        }

        return ['error' => 'Choisissez un membre, une personne des fichiers SSE ou « inconnu ».'];
    }

    // ------------------------------------------------------------------ Masques

    /** @param array<string, mixed> $in x, y, w, h, mode @return array<string, mixed> */
    public function addMask(PhotoViewer $v, int $photoId, array $in): array
    {
        $g = $this->guard($v, $photoId, 'mask');
        if (isset($g['ok'])) {
            return $g;
        }
        $rect = PhotoGeometry::rect($in['x'] ?? null, $in['y'] ?? null, $in['w'] ?? null, $in['h'] ?? null);
        $mode = PhotoGeometry::mode($in['mode'] ?? 'blur');
        if ($rect === null || $mode === null) {
            return $this->fail('Zone invalide : tracez un rectangle dans la photo.', 422);
        }
        if (count($this->repo->masks($v->tenantId, $photoId)) >= PhotoGeometry::MAX_MASKS) {
            return $this->fail('Trop de zones masquées sur cette photo.', 422);
        }
        if (!PhotoMaskRenderer::available()) {
            return $this->fail('Le masquage est indisponible sur ce serveur (extension GD absente) : aucune zone n’a été posée.', 503);
        }
        $id = $this->repo->addMask($v->tenantId, $photoId, $rect, $mode, $v->userId);
        if ($id < 1) {
            return $this->fail('La zone n’a pas pu être enregistrée.', 500);
        }
        $res = $this->rebuild($v, $g['row']);
        if (!$res['ok']) {
            $this->repo->deleteMask($v->tenantId, $photoId, $id);

            return $this->fail((string) $res['error'], 500);
        }
        $this->audit($v, 'photo_library.mask_add', $photoId, ['mask' => $id, 'mode' => $mode]);

        return ['ok' => true, 'id' => $id, 'status' => 200];
    }

    /** @return array<string, mixed> */
    public function removeMask(PhotoViewer $v, int $photoId, int $maskId): array
    {
        $g = $this->guard($v, $photoId, 'view');
        if (isset($g['ok'])) {
            return $g;
        }
        $mask = $this->repo->mask($v->tenantId, $photoId, $maskId);
        if ($mask === null || !PhotoPolicy::canRemoveMask($v, $g['f'], ['author_user_id' => (int) $mask['author_user_id']])) {
            return $this->fail('Zone introuvable.', 404);
        }
        $this->repo->deleteMask($v->tenantId, $photoId, $maskId);
        $res = $this->rebuild($v, $g['row']);
        if (!$res['ok']) {
            // Le fichier reste masqué : on remet la zone plutôt que de laisser croire qu'elle est retirée.
            $this->repo->addMask($v->tenantId, $photoId, ['x' => (float) $mask['x'], 'y' => (float) $mask['y'], 'w' => (float) $mask['w'], 'h' => (float) $mask['h']], (string) $mask['mode'], (int) $mask['author_user_id']);

            return $this->fail((string) $res['error'], 500);
        }
        $this->audit($v, 'photo_library.mask_remove', $photoId, ['mask' => $maskId]);

        return ['ok' => true, 'status' => 200];
    }

    /**
     * Réécrit le fichier servi d'après les zones enregistrées (original à l'abri tant qu'il y a des zones).
     *
     * @param array<string, mixed> $row
     * @return array{ok: bool, error?: string}
     */
    public function rebuild(PhotoViewer $v, array $row): array
    {
        $photoId = (int) $row['id'];
        $zones = array_map(static fn (array $m): array => ['x' => (float) $m['x'], 'y' => (float) $m['y'], 'w' => (float) $m['w'], 'h' => (float) $m['h'], 'mode' => (string) $m['mode']], $this->repo->masks($v->tenantId, $photoId));
        $state = $this->repo->state($v->tenantId, $photoId);
        $res = $this->vault->applyZones($row, $state !== null ? (string) ($state['original_path'] ?? '') : null, $zones);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => (string) ($res['error'] ?? 'Masquage impossible.')];
        }
        $this->repo->saveState($v->tenantId, $photoId, $res['original_path'] ?? null, $res['zones_hash'] ?? null);
        if ($zones !== [] && trim((string) ($row['thumb_path'] ?? '')) !== '') {
            // Une vignette ancienne montrerait l'image d'avant le masque : elle est retirée.
            $this->repo->updatePhotoColumns($v->tenantId, $photoId, ['thumb_path' => null]);
        }

        return ['ok' => true];
    }

    // ------------------------------------------------------------------ Commentaires

    /** @param array<string, mixed> $in body, anchor (none|zone|tag), x, y, w, h, tag @return array<string, mixed> */
    public function addComment(PhotoViewer $v, int $photoId, array $in): array
    {
        $g = $this->guard($v, $photoId, 'comment');
        if (isset($g['ok'])) {
            return $g;
        }
        $body = self::cleanText((string) ($in['body'] ?? ''), self::COMMENT_MAX);
        if ($body === '') {
            return $this->fail('Écrivez un commentaire.', 422);
        }
        $anchor = $this->anchor($v, $g['f'], $photoId, $in);
        if (isset($anchor['error'])) {
            return $this->fail((string) $anchor['error'], 422);
        }
        $mentions = array_keys($this->repo->membersByHandle($v->tenantId, self::mentionNames($body)));
        $id = $this->repo->addComment($v->tenantId, $photoId, $body, $anchor, $mentions, $v->userId);
        if ($id < 1) {
            return $this->fail('Le commentaire n’a pas pu être enregistré.', 500);
        }
        $this->notify($v, $g['row'], $g['f'], $id, $body, $mentions);

        return ['ok' => true, 'id' => $id, 'status' => 200];
    }

    /** @param array<string, mixed> $in @return array<string, mixed> */
    public function editComment(PhotoViewer $v, int $photoId, int $commentId, array $in): array
    {
        $g = $this->guard($v, $photoId, 'comment');
        if (isset($g['ok'])) {
            return $g;
        }
        $c = $this->repo->comment($v->tenantId, $photoId, $commentId);
        if ($c === null || !PhotoPolicy::canEditComment($v, $g['f'], ['author_user_id' => (int) $c['author_user_id']])) {
            return $this->fail('Commentaire introuvable.', 404);
        }
        $body = self::cleanText((string) ($in['body'] ?? ''), self::COMMENT_MAX);
        if ($body === '') {
            return $this->fail('Écrivez un commentaire.', 422);
        }
        $this->repo->editComment($v->tenantId, $photoId, $commentId, $body, array_keys($this->repo->membersByHandle($v->tenantId, self::mentionNames($body))));

        return ['ok' => true, 'status' => 200];
    }

    /** @return array<string, mixed> */
    public function deleteComment(PhotoViewer $v, int $photoId, int $commentId): array
    {
        $g = $this->guard($v, $photoId, 'view');
        if (isset($g['ok'])) {
            return $g;
        }
        $c = $this->repo->comment($v->tenantId, $photoId, $commentId);
        if ($c === null || !PhotoPolicy::canDeleteComment($v, $g['f'], ['author_user_id' => (int) $c['author_user_id']])) {
            return $this->fail('Commentaire introuvable.', 404);
        }
        $this->repo->deleteComment($v->tenantId, $photoId, $commentId, $v->userId);
        $this->audit($v, 'photo_library.comment_delete', $photoId, ['comment' => $commentId, 'own' => (int) $c['author_user_id'] === $v->userId]);

        return ['ok' => true, 'status' => 200];
    }

    /** Signalement d'un commentaire par le mécanisme existant (console de modération, content_reports). @return array<string, mixed> */
    public function reportComment(PhotoViewer $v, int $photoId, int $commentId, string $category, string $details): array
    {
        $g = $this->guard($v, $photoId, 'view');
        if (isset($g['ok'])) {
            return $g;
        }
        $c = $this->repo->comment($v->tenantId, $photoId, $commentId);
        if ($c === null) {
            return $this->fail('Commentaire introuvable.', 404);
        }
        $norm = ContentReportReason::fromCategory($category !== '' ? $category : 'other', mb_substr(trim($details), 0, 500));
        $excerpt = mb_substr(preg_replace('/\s+/u', ' ', (string) $c['body']) ?? '', 0, 120);
        try {
            $reportId = ($this->reporter)(
                $v->tenantId,
                $v->userId,
                'Commentaire de photothèque signalé : « ' . $excerpt . ' » (photo n° ' . $photoId . ', commentaire n° ' . $commentId . ') — ' . $norm['reason'],
                $norm['report_type'],
                $norm['comment'],
                url('jnet/photos/' . $photoId),
                'photo_comment'
            );
        } catch (\Throwable $e) {
            error_log('Photothèque : signalement refusé : ' . $e->getMessage());

            return $this->fail('Le signalement n’a pas pu être envoyé.', 500);
        }

        return ['ok' => true, 'id' => $reportId, 'status' => 200];
    }

    /**
     * @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f
     * @param array<string, mixed> $in
     * @return array{kind: string, x?: ?float, y?: ?float, w?: ?float, h?: ?float, tag?: ?int}|array{error: string}
     */
    private function anchor(PhotoViewer $v, array $f, int $photoId, array $in): array
    {
        $kind = (string) ($in['anchor'] ?? 'none');
        if ($kind === 'none' || $kind === '') {
            return ['kind' => 'none'];
        }
        if ($kind === 'zone') {
            $r = PhotoGeometry::rect($in['x'] ?? null, $in['y'] ?? null, $in['w'] ?? null, $in['h'] ?? null);

            return $r === null ? ['error' => 'Zone du commentaire invalide.'] : ['kind' => 'zone'] + $r;
        }
        if ($kind === 'tag') {
            $tagId = is_scalar($in['tag'] ?? null) && preg_match('/^\d{1,10}$/', (string) $in['tag']) === 1 ? (int) $in['tag'] : 0;
            $tag = $tagId > 0 ? $this->repo->tag($v->tenantId, $photoId, $tagId) : null;
            if ($tag === null || !PhotoPolicy::canSeeTag($v, $f, ['target_type' => (string) $tag['target_type'], 'author_user_id' => (int) $tag['author_user_id']])) {
                return ['error' => 'Visage introuvable.'];
            }

            return ['kind' => 'tag', 'tag' => $tagId];
        }

        return ['error' => 'Ancre du commentaire inconnue.'];
    }

    /**
     * Cloche pour les membres cités et l'auteur de la photo, seulement sur une photo ni confidentielle ni versée au SSE
     * (une notification ne doit pas révéler ce que la porte SSE protège).
     *
     * @param array<string, mixed> $row
     * @param array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int} $f
     * @param list<int> $mentions
     */
    private function notify(PhotoViewer $v, array $row, array $f, int $commentId, string $body, array $mentions): void
    {
        if ($f['confidential'] || $f['sse']) {
            return;
        }
        try {
            $who = $this->repo->members($v->tenantId, [$v->userId])[$v->userId]['name'] ?? 'Un membre';
            $payload = ['photo_id' => (int) $row['id'], 'comment_id' => $commentId, 'by' => $who, 'excerpt' => mb_substr(preg_replace('/\s+/u', ' ', $body) ?? '', 0, 140), 'path' => 'jnet/photos/' . (int) $row['id']];
            $sent = [];
            foreach ($mentions as $uid) {
                if ($uid > 0 && $uid !== $v->userId) {
                    ($this->notifier)($v->tenantId, $uid, 'photo_library_mention', $payload);
                    $sent[$uid] = true;
                }
            }
            $author = $f['author_user_id'];
            if ($author > 0 && $author !== $v->userId && !isset($sent[$author])) {
                ($this->notifier)($v->tenantId, $author, 'photo_library_comment', $payload);
            }
        } catch (\Throwable $e) {
            error_log('Photothèque : notification non envoyée : ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Étiquettes libres

    /** @return array<string, mixed> */
    public function addLabel(PhotoViewer $v, int $photoId, string $label): array
    {
        $g = $this->guard($v, $photoId, 'label');
        if (isset($g['ok'])) {
            return $g;
        }
        $clean = PhotoGeometry::label($label);
        if ($clean === null) {
            return $this->fail('Étiquette invalide : 2 à 40 caractères, lettres, chiffres, espaces ou tirets.', 422);
        }
        if ($this->repo->countLabels($v->tenantId, $photoId) >= self::MAX_LABELS) {
            return $this->fail('Trop d’étiquettes sur cette photo.', 422);
        }
        $this->repo->addLabel($v->tenantId, $photoId, $clean, PhotoGeometry::labelNorm($clean), $v->userId);

        return ['ok' => true, 'label' => $clean, 'status' => 200];
    }

    /** @return array<string, mixed> */
    public function removeLabel(PhotoViewer $v, int $photoId, string $label): array
    {
        $g = $this->guard($v, $photoId, 'label');
        if (isset($g['ok'])) {
            return $g;
        }
        $this->repo->removeLabel($v->tenantId, $photoId, PhotoGeometry::labelNorm($label));

        return ['ok' => true, 'status' => 200];
    }

    // ------------------------------------------------------------------ Versement SSE

    /** @return array<string, mixed> */
    public function transferToSse(PhotoViewer $v, int $photoId, ?int $identityId, string $actorLabel): array
    {
        $g = $this->guard($v, $photoId, 'view');
        if (isset($g['ok'])) {
            return $g;
        }
        if (!PhotoPolicy::canTransferToSse($v, $g['f'])) {
            // Pas de porte SSE : la fonction n'existe pas pour ce lecteur.
            return $this->fail('Photo introuvable.', 404);
        }
        $state = $this->repo->state($v->tenantId, $photoId);
        $res = $this->sse()->transfer($g['row'], $this->repo->tags($v->tenantId, $photoId), $identityId, $v->userId, $actorLabel, $this->vault, $state !== null ? (string) ($state['original_path'] ?? '') : null);
        if (!$res['ok']) {
            return $this->fail((string) ($res['error'] ?? 'Versement impossible.'), 422);
        }
        $this->audit($v, 'photo_library.sse_transfer', $photoId, ['identity' => $res['identity_id'] ?? null]);

        return ['ok' => true, 'status' => 200];
    }

    /** @return array<string, mixed> */
    public function withdrawFromSse(PhotoViewer $v, int $photoId, string $actorLabel): array
    {
        $g = $this->guard($v, $photoId, 'view');
        if (isset($g['ok'])) {
            return $g;
        }
        if (!PhotoPolicy::canWithdrawFromSse($v, $g['f'])) {
            return $this->fail('Photo introuvable.', 404);
        }
        $transfer = $this->repo->activeTransfer($v->tenantId, $photoId);
        if ($transfer === null) {
            return $this->fail('Cette photo n’est pas versée au dossier SSE.', 422);
        }
        $res = $this->sse()->withdraw($g['row'], $transfer, $v->userId, $actorLabel, $this->vault);
        if (!$res['ok']) {
            return $this->fail((string) ($res['error'] ?? 'Retrait impossible.'), 500);
        }
        $this->audit($v, 'photo_library.sse_withdraw', $photoId, []);

        return ['ok' => true, 'status' => 200];
    }

    // ------------------------------------------------------------------ Outils

    /**
     * Photo visible et droit demandé ; sinon un résultat d'échec (« ok » absent = poursuivre).
     *
     * @return array{row: array<string, mixed>, f: array{tenant_id: int, deleted: bool, confidential: bool, sse: bool, author_user_id: int}}|array<string, mixed>
     */
    private function guard(PhotoViewer $v, int $photoId, string $right): array
    {
        if (!$v->isMember()) {
            return $this->fail('Connectez-vous avec un compte de la communauté.', 403);
        }
        if (!$this->repo->ready()) {
            return $this->fail('La photothèque n’est pas encore activée (migration à passer).', 503);
        }
        $row = $this->visiblePhoto($v, $photoId);
        if ($row === null) {
            return $this->fail('Photo introuvable.', 404);
        }
        $f = $this->facts($row);
        $allowed = match ($right) {
            'tag' => PhotoPolicy::canTag($v, $f),
            'mask' => PhotoPolicy::canMask($v, $f),
            'comment' => PhotoPolicy::canComment($v, $f),
            'label' => PhotoPolicy::canLabel($v, $f),
            default => true,
        };
        if (!$allowed) {
            return $this->fail($right === 'mask' ? 'Seuls l’auteur de la photo et la gestion peuvent masquer une zone.' : 'Action non autorisée.', 403);
        }

        return ['row' => $row, 'f' => $f];
    }

    /** @return array{ok: false, error: string, status: int} */
    private function fail(string $message, int $status): array
    {
        return ['ok' => false, 'error' => $message, 'status' => $status];
    }

    /** @param array<string, mixed> $data */
    private function audit(PhotoViewer $v, string $action, int $photoId, array $data): void
    {
        try {
            ($this->audit)($action, $v->tenantId, $v->userId, $photoId, $data);
        } catch (\Throwable $e) {
            error_log('Photothèque : audit non écrit : ' . $e->getMessage());
        }
    }

    /** Texte d'un commentaire : retours à la ligne gardés, caractères de contrôle retirés, longueur bornée. */
    public static function cleanText(string $s, int $max): string
    {
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $s));
        $s = (string) preg_replace("/\n{3,}/", "\n\n", trim($s));

        return mb_substr($s, 0, $max);
    }

    /** @return list<string> noms cités par @nom (sans l'arobase) */
    public static function mentionNames(string $body): array
    {
        if (preg_match_all('/(?<![\p{L}\p{N}_])@([\p{L}\p{N}_.\-]{2,40})/u', $body, $m) < 1) {
            return [];
        }

        return array_slice(array_values(array_unique(array_map(static fn (string $n): string => rtrim($n, '.-'), $m[1]))), 0, 10);
    }
}
