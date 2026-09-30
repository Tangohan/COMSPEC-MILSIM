<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\EquipmentClassRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\ArsenalLoadoutItems;
use App\Support\EquipmentCoverStorage;
use App\Support\PlanFeatureDenial;

class ArsenalWardrobeController
{
    public function __construct(
        private ?ArsenalWardrobeRepository $repo = null,
        private ?FeatureGateService $featureGate = null,
        private ?EquipmentClassRepository $classes = null,
    ) {
        $this->repo ??= new ArsenalWardrobeRepository();
        $this->featureGate ??= \App\Core\Container::get(FeatureGateService::class);
        $this->classes ??= new EquipmentClassRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!$this->repo->tablesReady()) {
            return $this->hubView(true, [], [], []);
        }
        $wardrobes = $this->repo->listAccessibleWardrobes($tenantId, $userId);
        $collections = $this->repo->listCollections($tenantId, $userId);
        $collections = $this->attachCollectionMosaics($collections, $wardrobes);
        $classes = [];
        try {
            $classes = $this->classes->listForTenant($tenantId);
        } catch (\Throwable) {
            $classes = [];
        }

        return $this->hubView(false, $wardrobes, $collections, $classes);
    }

    public function redirectHub(Request $request, array $params = []): Response
    {
        return Response::redirect(url('equipment'));
    }

    public function streamCover(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $coverTenantId = (int) ($params['tenantId'] ?? 0);
        $file = basename(rawurldecode((string) ($params['file'] ?? '')));

        return EquipmentCoverStorage::streamCover($tenantId, $coverTenantId, $file);
    }

    public function showCollection(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        $collection = $id > 0 ? $this->repo->findCollection($tenantId, $id) : null;
        if ($collection === null) {
            Session::flash('error', 'Cette collection n’existe pas.');

            return Response::redirect(url('equipment'));
        }
        $ownerId = (int) ($collection['owner_user_id'] ?? 0);
        $visibility = (string) ($collection['visibility'] ?? 'personal');
        if ($ownerId !== $userId && $visibility === 'personal') {
            Session::flash('error', 'Cette collection n’est pas partagée.');

            return Response::redirect(url('equipment'));
        }

        $wantEdit = $request->query('edit') === '1' && $ownerId === $userId;
        if (!$wantEdit) {
            return Response::redirect(url('equipment') . '?collection=' . $id);
        }

        $all = $this->repo->listAccessibleWardrobes($tenantId, $userId);
        $wardrobes = array_values(array_filter(
            $all,
            static fn (array $w): bool => (int) ($w['collection_id'] ?? 0) === $id
        ));
        $mine = array_values(array_filter(
            $all,
            static fn (array $w): bool => !empty($w['mine'])
        ));

        return Response::view('layout.main', [
            'content' => 'equipment.collection',
            'title' => (string) $collection['name'],
            'equipmentHubPage' => true,
            'collection' => $collection,
            'wardrobes' => $wardrobes,
            'mineWardrobes' => $mine,
            'canEdit' => true,
            'csrfToken' => Csrf::token(),
            'flash_success' => Session::getFlash('success'),
            'flash_error' => Session::getFlash('error'),
        ]);
    }

    public function showWardrobe(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findWardrobe($tenantId, $id) : null;
        if ($row === null) {
            if ($this->wantsJson($request)) {
                return Response::json(['ok' => false, 'error' => 'not_found'], 404);
            }
            Session::flash('error', 'Cette tenue n’existe pas.');

            return Response::redirect(url('equipment'));
        }
        $row['mine'] = (int) ($row['user_id'] ?? 0) === $userId;
        $loadoutItems = ArsenalLoadoutItems::grouped((string) ($row['payload_text'] ?? ''));
        $kinds = ArsenalLoadoutItems::presentKinds((string) ($row['payload_text'] ?? ''));
        unset($row['payload_text']);

        if ($this->wantsJson($request)) {
            $collections = $row['mine'] ? $this->repo->listCollections($tenantId, $userId) : [];
            $gallery = [];
            if (!empty($row['cover_url'])) {
                $gallery[] = (string) $row['cover_url'];
            }

            return Response::json([
                'ok' => true,
                'wardrobe' => [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'display_name' => (string) ($row['display_name'] ?? ArsenalLoadoutItems::formatWardrobeTitle((string) ($row['name'] ?? ''))),
                    'cover_url' => $row['cover_url'] ?? null,
                    'gallery' => $gallery,
                    'collection_id' => $row['collection_id'] ?? null,
                    'collection_name' => $row['collection_name'] ?? null,
                    'notes' => $row['mine'] ? (string) ($row['notes'] ?? '') : '',
                    'public_notes' => !$row['mine'] ? '' : '',
                    'owner_label' => (string) ($row['owner_label'] ?? ''),
                    'mine' => !empty($row['mine']),
                    'can_edit' => !empty($row['mine']),
                    'kinds' => $kinds,
                    'loadout_items' => $loadoutItems,
                    'usage_hint' => 'Cette tenue s’envoie et se récupère depuis l’arsenal en jeu, bandeau Athena en haut de l’écran d’équipement.',
                ],
                'collections' => array_map(static function (array $c): array {
                    return [
                        'id' => (int) ($c['id'] ?? 0),
                        'name' => (string) ($c['name'] ?? ''),
                    ];
                }, $collections),
                'csrf' => Csrf::token(),
                'urls' => [
                    'update' => url('equipment/tenues/' . $id),
                    'delete' => url('equipment/tenues/' . $id . '/delete'),
                ],
            ]);
        }

        // Lien direct → catalogue avec quick-view (préserve la logique hub).
        return Response::redirect(url('equipment') . '?tenue=' . $id);
    }

    public function storeCollection(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment'));
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Donnez un nom à la collection.');

            return Response::redirect(url('equipment'));
        }
        try {
            $ids = $request->input('wardrobe_ids', []);
            if (!is_array($ids)) {
                $ids = [];
            }
            $created = $this->repo->upsertCollection($tenantId, $userId, [
                'name' => $name,
                'description' => trim((string) $request->input('description', '')),
                'visibility' => (string) $request->input('visibility', 'personal'),
                'wardrobe_ids' => $ids,
            ]);
            $cover = $this->maybeStoreCover($tenantId, 'collection', $_FILES['cover'] ?? []);
            if ($cover['error'] !== null) {
                Session::flash('error', $cover['error']);
            } elseif ($cover['path'] !== null && (int) ($created['id'] ?? 0) > 0) {
                $this->repo->setCollectionCover($tenantId, $userId, (int) $created['id'], $cover['path']);
            }
            Session::flash('success', 'Collection créée.');
            if ((int) ($created['id'] ?? 0) > 0) {
                return Response::redirect(url('equipment') . '?collection=' . (int) $created['id']);
            }
        } catch (\Throwable) {
            Session::flash('error', 'Impossible de créer la collection.');
        }

        return Response::redirect(url('equipment'));
    }

    public function updateCollection(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?collection=' . $id);
        }
        $existing = $this->repo->findCollection($tenantId, $id);
        if ($existing === null || (int) ($existing['owner_user_id'] ?? 0) !== $userId) {
            Session::flash('error', 'Vous ne pouvez pas modifier cette collection.');

            return Response::redirect(url('equipment'));
        }
        $ids = $request->input('wardrobe_ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }
        try {
            $this->repo->upsertCollection($tenantId, $userId, [
                'id' => $id,
                'name' => trim((string) $request->input('name', $existing['name'] ?? '')),
                'description' => trim((string) $request->input('description', '')),
                'visibility' => (string) $request->input('visibility', $existing['visibility'] ?? 'personal'),
                'wardrobe_ids' => $ids,
            ]);
            $cover = $this->maybeStoreCover($tenantId, 'collection', $_FILES['cover'] ?? []);
            if ($cover['error'] !== null) {
                Session::flash('error', $cover['error']);
            } elseif ($cover['path'] !== null) {
                $this->repo->setCollectionCover($tenantId, $userId, $id, $cover['path']);
            }
            Session::flash('success', 'Collection mise à jour.');
        } catch (\Throwable) {
            Session::flash('error', 'Impossible d’enregistrer la collection.');
        }

        return Response::redirect(url('equipment') . '?collection=' . $id);
    }

    public function updateWardrobe(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $this->wardrobeMutateRedirect($request, $id);
        }
        $row = $this->repo->findWardrobe($tenantId, $id, $userId);
        if ($row === null) {
            Session::flash('error', 'Vous ne pouvez modifier que vos propres tenues.');

            return Response::redirect(url('equipment'));
        }
        $collectionId = (int) $request->input('collection_id', 0);
        $this->repo->assignWardrobeCollection($tenantId, $userId, $id, $collectionId > 0 ? $collectionId : null);
        $this->repo->updateWardrobeNotes($tenantId, $userId, $id, trim((string) $request->input('notes', '')));
        $cover = $this->maybeStoreCover($tenantId, 'wardrobe', $_FILES['cover'] ?? []);
        if ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);
        } elseif ($cover['path'] !== null) {
            $this->repo->setWardrobeCover($tenantId, $userId, $id, $cover['path']);
            Session::flash('success', 'Photo de présentation enregistrée.');
        } else {
            Session::flash('success', 'Tenue mise à jour.');
        }

        return $this->wardrobeMutateRedirect($request, $id);
    }

    public function destroyWardrobe(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findWardrobe($tenantId, $id, $userId) : null;
        if ($row !== null) {
            EquipmentCoverStorage::delete(isset($row['cover_image_path']) ? (string) $row['cover_image_path'] : null);
            $this->repo->deleteWardrobe($tenantId, $userId, $id);
            Session::flash('success', 'Tenue retirée.');
        }

        return Response::redirect(url('equipment'));
    }

    public function destroyCollection(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment'));
        }
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->repo->findCollection($tenantId, $id) : null;
        if ($row !== null && (int) ($row['owner_user_id'] ?? 0) === $userId) {
            EquipmentCoverStorage::delete(isset($row['cover_image_path']) ? (string) $row['cover_image_path'] : null);
            $this->repo->deleteCollection($tenantId, $userId, $id);
            Session::flash('success', 'Collection retirée.');
        }

        return Response::redirect(url('equipment'));
    }

    /**
     * @return array{0:int,1:int}|Response
     */
    private function gate(): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows($tenantId, 'equipment')) {
            return PlanFeatureDenial::upgradeView('equipment', 'Gratuit');
        }

        return [$tenantId, $userId];
    }

    /**
     * @param list<array<string, mixed>> $wardrobes
     * @param list<array<string, mixed>> $collections
     * @param list<array<string, mixed>> $classes
     */
    private function hubView(bool $migrationMissing, array $wardrobes, array $collections, array $classes): Response
    {
        $mine = array_values(array_filter($wardrobes, static fn (array $w): bool => !empty($w['mine'])));

        return Response::view('layout.main', [
            'content' => 'equipment.hub',
            'title' => 'Équipement',
            'equipmentHubPage' => true,
            'migrationMissing' => $migrationMissing,
            'wardrobes' => $wardrobes,
            'mineWardrobes' => $mine,
            'collections' => $collections,
            'equipmentClasses' => $classes,
            'equipmentKindLabels' => ArsenalLoadoutItems::kindLabels(),
            'csrfToken' => Csrf::token(),
            'flash_success' => Session::getFlash('success'),
            'flash_error' => Session::getFlash('error'),
            'coverHint' => EquipmentCoverStorage::hintText(),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param list<array<string, mixed>> $wardrobes
     * @return list<array<string, mixed>>
     */
    private function attachCollectionMosaics(array $collections, array $wardrobes): array
    {
        $byCollection = [];
        foreach ($wardrobes as $w) {
            $cid = (int) ($w['collection_id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            $url = trim((string) ($w['cover_url'] ?? ''));
            if ($url === '') {
                continue;
            }
            if (!isset($byCollection[$cid])) {
                $byCollection[$cid] = [];
            }
            if (count($byCollection[$cid]) < 4) {
                $byCollection[$cid][] = $url;
            }
        }
        foreach ($collections as &$c) {
            $id = (int) ($c['id'] ?? 0);
            $c['mosaic'] = $byCollection[$id] ?? [];
        }
        unset($c);

        return $collections;
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $format = strtolower(trim((string) $request->query('format', '')));

        return $format === 'json'
            || str_contains($accept, 'application/json')
            || $xhr === 'xmlhttprequest';
    }

    private function wardrobeMutateRedirect(Request $request, int $id): Response
    {
        $back = trim((string) $request->input('_return', ''));
        if ($back === 'hub' || $back === 'catalog') {
            return Response::redirect(url('equipment') . '?tenue=' . $id);
        }

        return Response::redirect(url('equipment') . '?tenue=' . $id);
    }

    /**
     * @param array<string, mixed> $file
     * @return array{path:?string, error:?string}
     */
    private function maybeStoreCover(int $tenantId, string $kind, mixed $file): array
    {
        if (!is_array($file)) {
            return ['path' => null, 'error' => null];
        }

        return EquipmentCoverStorage::storeFromUpload($tenantId, $kind, $file);
    }
}
