<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Repositories\PhotoLibraryRepository;
use App\Services\PhotoLibrary\PhotoGeometry;

/**
 * Photothèque en mémoire pour les tests et les rendus locaux : mêmes méthodes publiques que PhotoLibraryRepository, sans
 * base. Les photos (lignes recon_images) et les membres sont posés par le test ; les filtres reprennent ceux du SQL.
 */
final class FakePhotoLibraryRepository extends PhotoLibraryRepository
{
    /** @var array<int, array<string, mixed>> */
    public array $photos = [];
    /** @var array<int, array<string, mixed>> */
    public array $tags = [];
    /** @var array<int, array<string, mixed>> */
    public array $masks = [];
    /** @var array<int, array<string, mixed>> */
    public array $comments = [];
    /** @var list<array<string, mixed>> */
    public array $labels = [];
    /** @var array<int, array<string, mixed>> */
    public array $states = [];
    /** @var array<int, array<string, mixed>> */
    public array $transfers = [];
    /** @var array<int, array{id: int, name: string, callsign: string, tenant: int}> */
    public array $users = [];
    public bool $migrated = true;
    private int $seq = 0;

    public function __construct()
    {
        parent::__construct(null);
    }

    private function next(): int
    {
        return ++$this->seq;
    }

    public function tableExists(string $table): bool
    {
        return $table === 'recon_images' || $this->migrated;
    }

    public function ready(): bool
    {
        return $this->migrated;
    }

    public function photosReady(): bool
    {
        return true;
    }

    public function reconHasColumn(string $column): bool
    {
        return true;
    }

    /** @param array<string, mixed> $row */
    public function addPhoto(array $row): int
    {
        $id = (int) ($row['id'] ?? $this->next());
        $this->photos[$id] = $row + ['id' => $id, 'tenant_id' => 1, 'author_callsign' => 'Unknown', 'unit_name' => null, 'mission_id' => null, 'caption' => null,
            'grid_ref' => null, 'device_type' => 'CTAB', 'captured_at' => '2026-11-14 21:00:00', 'created_at' => '2026-11-14 21:00:00', 'is_confidential' => 0, 'is_sse' => 0,
            'deleted_at' => null, 'author_user_id' => null, 'image_path' => '', 'thumb_path' => null, 'title' => null, 'description' => null];

        return $id;
    }

    public function listPhotos(int $tenantId, array $f, string $sort, int $limit, int $offset, bool $door, bool $clearConfidential): array
    {
        $rows = [];
        foreach ($this->photos as $p) {
            if ((int) $p['tenant_id'] !== $tenantId || !empty($p['deleted_at']) || preg_match('/^(chest|helmet|drone):/i', (string) ($p['unit_name'] ?? '')) === 1) {
                continue;
            }
            $versed = $this->activeTransfer($tenantId, (int) $p['id']) !== null;
            if (!$clearConfidential && (!empty($p['is_confidential']) || str_starts_with((string) $p['image_path'], 'recon-private/'))) {
                continue;
            }
            if (!$door && $versed) {
                continue;
            }
            $q = mb_strtolower(trim((string) ($f['q'] ?? '')));
            if ($q !== '') {
                $hay = mb_strtolower(implode(' ', [(string) $p['caption'], (string) $p['author_callsign'], (string) $p['mission_id'], (string) $p['grid_ref'], (string) $p['title'], (string) $p['description'], implode(' ', $this->labels((int) $p['tenant_id'], (int) $p['id']))]));
                if (!str_contains($hay, $q)) {
                    continue;
                }
            }
            foreach (['author' => 'author_callsign', 'unit' => 'unit_name', 'album' => 'mission_id'] as $k => $col) {
                if (($f[$k] ?? '') !== '' && (string) $p[$col] !== (string) $f[$k]) {
                    continue 2;
                }
            }
            $when = (string) ($p['captured_at'] ?: $p['created_at']);
            if (($f['from'] ?? '') !== '' && $when < $f['from'] . ' 00:00:00') {
                continue;
            }
            if (($f['to'] ?? '') !== '' && $when > $f['to'] . ' 23:59:59') {
                continue;
            }
            if (($f['grid'] ?? '') !== '' && !str_starts_with((string) $p['grid_ref'], (string) $f['grid'])) {
                continue;
            }
            $label = PhotoGeometry::labelNorm((string) ($f['label'] ?? ''));
            if ($label !== '' && !array_filter($this->labels, static fn (array $l): bool => $l['photo_id'] === (int) $p['id'] && $l['label_norm'] === $label)) {
                continue;
            }
            $ptags = array_filter($this->tags, static fn (array $t): bool => (int) $t['photo_id'] === (int) $p['id']);
            $person = (string) ($f['person'] ?? '');
            if ($person === 'any' && array_filter($ptags, static fn (array $t): bool => $door || $t['target_type'] !== 'sse') === []) {
                continue;
            }
            if ($person === 'unknown' && array_filter($ptags, static fn (array $t): bool => $t['target_type'] === 'unknown') === []) {
                continue;
            }
            if (preg_match('/^m(\d+)$/', $person, $m) === 1 && array_filter($ptags, static fn (array $t): bool => $t['target_type'] === 'member' && (int) $t['target_id'] === (int) $m[1]) === []) {
                continue;
            }
            if ($door && preg_match('/^s(\d+)$/', $person, $m) === 1 && array_filter($ptags, static fn (array $t): bool => $t['target_type'] === 'sse' && (int) $t['target_id'] === (int) $m[1]) === []) {
                continue;
            }
            if ($door && ($f['sse'] ?? '') === 'sse' && !$versed) {
                continue;
            }
            if (($f['sse'] ?? '') === 'none' && $versed) {
                continue;
            }
            $pm = array_filter($this->masks, static fn (array $m): bool => (int) $m['photo_id'] === (int) $p['id']);
            $rows[] = $p + [
                'sse_versed' => $versed ? 1 : 0,
                'tags_n' => count(array_filter($ptags, static fn (array $t): bool => $door || $t['target_type'] !== 'sse')),
                'comments_n' => count(array_filter($this->comments, static fn (array $c): bool => (int) $c['photo_id'] === (int) $p['id'] && empty($c['deleted_at']))),
                'masks_n' => count($pm),
                'masks_last' => $pm === [] ? 0 : max(array_map(static fn (array $m): int => (int) $m['id'], $pm)),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => $sort === 'ancien'
            ? [(string) $a['captured_at'], $a['id']] <=> [(string) $b['captured_at'], $b['id']]
            : [(string) $b['captured_at'], $b['id']] <=> [(string) $a['captured_at'], $a['id']]);

        return ['rows' => array_slice($rows, $offset, $limit), 'total' => count($rows)];
    }

    public function photo(int $tenantId, int $photoId): ?array
    {
        $p = $this->photos[$photoId] ?? null;
        if ($p === null || (int) $p['tenant_id'] !== $tenantId) {
            return null;
        }

        return $p + ['sse_versed' => $this->activeTransfer($tenantId, $photoId) !== null ? 1 : 0];
    }

    public function facets(int $tenantId): array
    {
        $col = static fn (array $rows, string $k): array => array_values(array_unique(array_filter(array_map(static fn (array $r): string => (string) ($r[$k] ?? ''), $rows))));
        $rows = array_filter($this->photos, static fn (array $p): bool => (int) $p['tenant_id'] === $tenantId && empty($p['is_confidential']));

        return ['authors' => $col($rows, 'author_callsign'), 'units' => $col($rows, 'unit_name'), 'albums' => $col($rows, 'mission_id'), 'labels' => array_values(array_unique(array_map(static fn (array $l): string => (string) $l['label'], $this->labels)))];
    }

    public function updatePhotoColumns(int $tenantId, int $photoId, array $set): bool
    {
        if (isset($this->photos[$photoId]) && (int) $this->photos[$photoId]['tenant_id'] === $tenantId) {
            $this->photos[$photoId] = $set + $this->photos[$photoId];
            foreach ($set as $k => $v) {
                $this->photos[$photoId][$k] = $v;
            }
        }

        return true;
    }

    public function tags(int $tenantId, int $photoId): array
    {
        return array_values(array_filter($this->tags, static fn (array $t): bool => (int) $t['tenant_id'] === $tenantId && (int) $t['photo_id'] === $photoId));
    }

    public function tag(int $tenantId, int $photoId, int $tagId): ?array
    {
        $t = $this->tags[$tagId] ?? null;

        return $t !== null && (int) $t['tenant_id'] === $tenantId && (int) $t['photo_id'] === $photoId ? $t : null;
    }

    public function countTags(int $tenantId, int $photoId): int
    {
        return count($this->tags($tenantId, $photoId));
    }

    public function addTag(int $tenantId, int $photoId, array $r, string $type, ?int $targetId, int $userId): int
    {
        $id = $this->next();
        $this->tags[$id] = ['id' => $id, 'tenant_id' => $tenantId, 'photo_id' => $photoId] + $r + ['target_type' => $type, 'target_id' => $targetId, 'author_user_id' => $userId, 'created_at' => '2026-11-15 10:00:00'];

        return $id;
    }

    public function updateTag(int $tenantId, int $photoId, int $tagId, ?array $r, string $type, ?int $targetId): bool
    {
        if ($this->tag($tenantId, $photoId, $tagId) === null) {
            return false;
        }
        $this->tags[$tagId] = ($r ?? []) + ['target_type' => $type, 'target_id' => $targetId] + $this->tags[$tagId];

        return true;
    }

    public function deleteTag(int $tenantId, int $photoId, int $tagId): bool
    {
        $ok = $this->tag($tenantId, $photoId, $tagId) !== null;
        unset($this->tags[$tagId]);

        return $ok;
    }

    public function masks(int $tenantId, int $photoId): array
    {
        return array_values(array_filter($this->masks, static fn (array $m): bool => (int) $m['tenant_id'] === $tenantId && (int) $m['photo_id'] === $photoId));
    }

    public function mask(int $tenantId, int $photoId, int $maskId): ?array
    {
        $m = $this->masks[$maskId] ?? null;

        return $m !== null && (int) $m['tenant_id'] === $tenantId && (int) $m['photo_id'] === $photoId ? $m : null;
    }

    public function addMask(int $tenantId, int $photoId, array $r, string $mode, int $userId): int
    {
        $id = $this->next();
        $this->masks[$id] = ['id' => $id, 'tenant_id' => $tenantId, 'photo_id' => $photoId] + $r + ['mode' => $mode, 'author_user_id' => $userId];

        return $id;
    }

    public function deleteMask(int $tenantId, int $photoId, int $maskId): bool
    {
        $ok = $this->mask($tenantId, $photoId, $maskId) !== null;
        unset($this->masks[$maskId]);

        return $ok;
    }

    public function state(int $tenantId, int $photoId): ?array
    {
        return $this->states[$photoId] ?? null;
    }

    public function saveState(int $tenantId, int $photoId, ?string $originalPath, ?string $zonesHash): void
    {
        if ($originalPath === null) {
            unset($this->states[$photoId]);

            return;
        }
        $this->states[$photoId] = ['photo_id' => $photoId, 'tenant_id' => $tenantId, 'original_path' => $originalPath, 'zones_hash' => $zonesHash];
    }

    public function comments(int $tenantId, int $photoId): array
    {
        return array_values(array_filter($this->comments, static fn (array $c): bool => (int) $c['tenant_id'] === $tenantId && (int) $c['photo_id'] === $photoId && empty($c['deleted_at'])));
    }

    public function comment(int $tenantId, int $photoId, int $commentId): ?array
    {
        $c = $this->comments[$commentId] ?? null;

        return $c !== null && (int) $c['tenant_id'] === $tenantId && (int) $c['photo_id'] === $photoId && empty($c['deleted_at']) ? $c : null;
    }

    public function addComment(int $tenantId, int $photoId, string $body, array $anchor, array $mentions, int $userId): int
    {
        $id = $this->next();
        $this->comments[$id] = ['id' => $id, 'tenant_id' => $tenantId, 'photo_id' => $photoId, 'body' => $body, 'anchor_kind' => $anchor['kind'],
            'anchor_x' => $anchor['x'] ?? null, 'anchor_y' => $anchor['y'] ?? null, 'anchor_w' => $anchor['w'] ?? null, 'anchor_h' => $anchor['h'] ?? null,
            'anchor_tag_id' => $anchor['tag'] ?? null, 'mentions' => implode(',', $mentions), 'author_user_id' => $userId, 'created_at' => '2026-11-15 10:05:00', 'edited_at' => null, 'deleted_at' => null];

        return $id;
    }

    public function editComment(int $tenantId, int $photoId, int $commentId, string $body, array $mentions): bool
    {
        if ($this->comment($tenantId, $photoId, $commentId) === null) {
            return false;
        }
        $this->comments[$commentId]['body'] = $body;
        $this->comments[$commentId]['mentions'] = implode(',', $mentions);
        $this->comments[$commentId]['edited_at'] = '2026-11-15 10:10:00';

        return true;
    }

    public function deleteComment(int $tenantId, int $photoId, int $commentId, int $userId): bool
    {
        if ($this->comment($tenantId, $photoId, $commentId) === null) {
            return false;
        }
        $this->comments[$commentId]['deleted_at'] = '2026-11-15 10:15:00';

        return true;
    }

    public function labels(int $tenantId, int $photoId): array
    {
        return array_values(array_map(static fn (array $l): string => (string) $l['label'], array_filter($this->labels, static fn (array $l): bool => $l['tenant_id'] === $tenantId && $l['photo_id'] === $photoId)));
    }

    public function countLabels(int $tenantId, int $photoId): int
    {
        return count($this->labels($tenantId, $photoId));
    }

    public function addLabel(int $tenantId, int $photoId, string $label, string $norm, int $userId): bool
    {
        foreach ($this->labels as $l) {
            if ($l['tenant_id'] === $tenantId && $l['photo_id'] === $photoId && $l['label_norm'] === $norm) {
                return false;
            }
        }
        $this->labels[] = ['tenant_id' => $tenantId, 'photo_id' => $photoId, 'label' => $label, 'label_norm' => $norm];

        return true;
    }

    public function removeLabel(int $tenantId, int $photoId, string $norm): bool
    {
        $before = count($this->labels);
        $this->labels = array_values(array_filter($this->labels, static fn (array $l): bool => !($l['tenant_id'] === $tenantId && $l['photo_id'] === $photoId && $l['label_norm'] === $norm)));

        return count($this->labels) < $before;
    }

    public function activeTransfer(int $tenantId, int $photoId): ?array
    {
        foreach (array_reverse($this->transfers) as $t) {
            if ((int) $t['tenant_id'] === $tenantId && (int) $t['photo_id'] === $photoId && empty($t['withdrawn_at'])) {
                return $t;
            }
        }

        return null;
    }

    public function addTransfer(int $tenantId, int $photoId, ?int $ssePhotoId, ?int $identityId, int $userId, bool $prevConfidential, bool $prevSse): int
    {
        $id = $this->next();
        $this->transfers[$id] = ['id' => $id, 'tenant_id' => $tenantId, 'photo_id' => $photoId, 'sse_photo_id' => $ssePhotoId, 'identity_id' => $identityId, 'actor_user_id' => $userId,
            'prev_confidential' => $prevConfidential ? 1 : 0, 'prev_sse' => $prevSse ? 1 : 0, 'created_at' => '2026-11-15 11:00:00', 'withdrawn_at' => null];

        return $id;
    }

    public function withdrawTransfer(int $tenantId, int $transferId, int $userId): bool
    {
        if (!isset($this->transfers[$transferId])) {
            return false;
        }
        $this->transfers[$transferId]['withdrawn_at'] = '2026-11-15 12:00:00';

        return true;
    }

    public function members(int $tenantId, array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $u = $this->users[(int) $id] ?? null;
            if ($u !== null && $u['tenant'] === $tenantId) {
                $out[(int) $id] = ['id' => $u['id'], 'name' => $u['callsign'] !== '' ? $u['callsign'] : $u['name'], 'callsign' => $u['callsign']];
            }
        }

        return $out;
    }

    public function searchMembers(int $tenantId, string $q, int $limit = 8): array
    {
        $out = [];
        foreach ($this->users as $u) {
            if ($u['tenant'] === $tenantId && ($q === '' || stripos($u['callsign'] . ' ' . $u['name'], $q) !== false)) {
                $out[] = ['id' => $u['id'], 'name' => $u['callsign'] !== '' ? $u['callsign'] : $u['name'], 'callsign' => $u['callsign']];
            }
        }

        return array_slice($out, 0, $limit);
    }

    public function uniqueMemberByCallsign(int $tenantId, string $callsign): ?int
    {
        $found = [];
        foreach ($this->users as $u) {
            if ($u['tenant'] === $tenantId && (strcasecmp($u['callsign'], trim($callsign)) === 0 || strcasecmp($u['name'], trim($callsign)) === 0)) {
                $found[] = $u['id'];
            }
        }

        return count($found) === 1 && trim($callsign) !== '' ? $found[0] : null;
    }
}
