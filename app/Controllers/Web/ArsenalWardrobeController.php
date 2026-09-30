<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\EquipmentClassRepository;
use App\Repositories\EquipmentItemDefinitionRepository;
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
        private ?EquipmentItemDefinitionRepository $definitions = null,
    ) {
        $this->repo ??= new ArsenalWardrobeRepository();
        $this->featureGate ??= \App\Core\Container::get(FeatureGateService::class);
        $this->classes ??= new EquipmentClassRepository();
        $this->definitions ??= new EquipmentItemDefinitionRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId, $userId] = $gate;
        if (!$this->repo->tablesReady()) {
            return $this->hubView(true, [], [], [], []);
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
        $dotation = [];
        try {
            $dotation = $this->definitions->listForTenant($tenantId, false);
        } catch (\Throwable) {
            $dotation = [];
        }

        return $this->hubView(false, $wardrobes, $collections, $classes, $dotation);
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

        if ($this->wantsJson($request)) {
            $all = $this->repo->listAccessibleWardrobes($tenantId, $userId);
            $selectedIds = [];
            foreach ($all as $w) {
                if ((int) ($w['collection_id'] ?? 0) === $id && !empty($w['mine'])) {
                    $selectedIds[] = (int) $w['id'];
                }
            }
            $mine = array_values(array_filter($all, static fn (array $w): bool => !empty($w['mine'])));

            return Response::json([
                'ok' => true,
                'collection' => [
                    'id' => (int) ($collection['id'] ?? 0),
                    'name' => (string) ($collection['name'] ?? ''),
                    'description' => (string) ($collection['description'] ?? ''),
                    'visibility' => (string) ($collection['visibility'] ?? 'personal'),
                    'cover_url' => $collection['cover_url'] ?? null,
                    'mine' => $ownerId === $userId,
                    'selected_ids' => $selectedIds,
                ],
                'mine_wardrobes' => array_map(static function (array $w): array {
                    return [
                        'id' => (int) ($w['id'] ?? 0),
                        'name' => (string) ($w['name'] ?? ''),
                        'display_name' => (string) ($w['display_name'] ?? ''),
                        'cover_url' => $w['cover_url'] ?? null,
                        'kinds' => array_values(is_array($w['kinds'] ?? null) ? $w['kinds'] : []),
                    ];
                }, $mine),
                'csrf' => Csrf::token(),
                'urls' => [
                    'update' => url('equipment/collections/' . $id),
                    'delete' => url('equipment/collections/' . $id . '/delete'),
                ],
            ]);
        }

        $editQs = ($ownerId === $userId && $request->query('edit') === '1') ? '&edit_collection=1' : '';

        return Response::redirect(url('equipment') . '?collection=' . $id . $editQs);
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
            foreach ($row['gallery_urls'] ?? [] as $url) {
                if (is_string($url) && $url !== '' && !in_array($url, $gallery, true)) {
                    $gallery[] = $url;
                }
            }

            return Response::json([
                'ok' => true,
                'wardrobe' => [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'display_name' => (string) ($row['display_name'] ?? ArsenalLoadoutItems::formatWardrobeTitle((string) ($row['name'] ?? ''))),
                    'description' => (string) ($row['description'] ?? ''),
                    'cover_url' => $row['cover_url'] ?? null,
                    'gallery' => $gallery,
                    'gallery_paths' => array_values(is_array($row['gallery_paths'] ?? null) ? $row['gallery_paths'] : []),
                    'collection_id' => $row['collection_id'] ?? null,
                    'collection_name' => $row['collection_name'] ?? null,
                    'notes' => $row['mine'] ? (string) ($row['notes'] ?? '') : '',
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
        $this->repo->updateWardrobeDescription($tenantId, $userId, $id, trim((string) $request->input('description', '')));

        $cover = $this->maybeStoreCover($tenantId, 'wardrobe', $_FILES['cover'] ?? []);
        if ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);

            return $this->wardrobeMutateRedirect($request, $id);
        }
        if ($cover['path'] !== null) {
            $this->repo->setWardrobeCover($tenantId, $userId, $id, $cover['path']);
        }

        $galleryPaths = array_values(is_array($row['gallery_paths'] ?? null) ? $row['gallery_paths'] : []);
        $remove = $request->input('remove_gallery', []);
        if (!is_array($remove)) {
            $remove = [];
        }
        $removeSet = [];
        foreach ($remove as $p) {
            $removeSet[trim((string) $p)] = true;
        }
        $kept = [];
        foreach ($galleryPaths as $path) {
            if (!isset($removeSet[$path])) {
                $kept[] = $path;
            } else {
                EquipmentCoverStorage::delete($path);
            }
        }
        foreach ($this->normalizeUploadList($_FILES['gallery'] ?? null) as $file) {
            if (count($kept) >= 5) {
                break;
            }
            $stored = $this->maybeStoreCover($tenantId, 'wardrobe', $file);
            if ($stored['error'] !== null) {
                Session::flash('error', $stored['error']);
                break;
            }
            if ($stored['path'] !== null) {
                $kept[] = $stored['path'];
            }
        }
        $this->repo->setWardrobeGallery($tenantId, $userId, $id, $kept);
        Session::flash('success', 'Tenue mise à jour.');

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
            foreach ($row['gallery_paths'] ?? [] as $path) {
                EquipmentCoverStorage::delete(is_string($path) ? $path : null);
            }
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

    public function storeFiche(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        if (!$this->canManageCatalog()) {
            Session::flash('error', 'Droits insuffisants pour créer une fiche matériel.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Donnez un nom à la fiche.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        $slug = $this->classes->slugify($name);
        $base = $slug;
        $n = 2;
        while ($this->classes->slugExists($tenantId, $slug)) {
            $slug = $base . '-' . $n;
            $n++;
        }
        $id = $this->classes->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => $slug,
            'category' => trim((string) $request->input('category', '')) ?: null,
            'description' => trim((string) $request->input('description', '')) ?: null,
        ]);
        $cover = $this->maybeStoreCover($tenantId, 'figure', $_FILES['cover'] ?? []);
        if ($cover['path'] !== null) {
            $this->classes->setCover($id, $tenantId, $cover['path']);
        } elseif ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);
        }
        Session::flash('success', 'Fiche matériel créée.');

        return Response::redirect(url('equipment') . '?tab=fiches&fiche=' . $id);
    }

    public function updateFiche(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManageCatalog()) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?tab=fiches&fiche=' . $id);
        }
        $existing = $this->classes->findById($id, $tenantId);
        if ($existing === null) {
            Session::flash('error', 'Fiche introuvable.');

            return Response::redirect(url('equipment') . '?tab=fiches');
        }
        $name = trim((string) $request->input('name', $existing['name'] ?? ''));
        $slug = $this->classes->slugify($name !== '' ? $name : (string) ($existing['name'] ?? 'equipment'));
        if ($this->classes->slugExists($tenantId, $slug, $id)) {
            $slug = (string) ($existing['slug'] ?? $slug);
        }
        $this->classes->update($id, $tenantId, [
            'name' => $name,
            'slug' => $slug,
            'category' => trim((string) $request->input('category', '')) ?: null,
            'description' => trim((string) $request->input('description', '')) ?: null,
        ]);
        $cover = $this->maybeStoreCover($tenantId, 'figure', $_FILES['cover'] ?? []);
        if ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);
        } elseif ($cover['path'] !== null) {
            $this->classes->setCover($id, $tenantId, $cover['path']);
        }
        Session::flash('success', 'Fiche mise à jour.');

        return Response::redirect(url('equipment') . '?tab=fiches&fiche=' . $id);
    }

    public function showFiche(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->classes->findById($id, $tenantId) : null;
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }

        return Response::json([
            'ok' => true,
            'fiche' => [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'cover_url' => $row['cover_url'] ?? null,
                'can_edit' => $this->canManageCatalog(),
            ],
            'csrf' => Csrf::token(),
            'urls' => [
                'update' => url('equipment/fiches/' . $id),
                'page' => url('equipment/' . ($row['slug'] ?? '')),
            ],
        ]);
    }

    public function storeDotationItem(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        if (!$this->canManageDotation()) {
            Session::flash('error', 'Droits insuffisants pour créer un article de dotation.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($code === '' || $name === '') {
            Session::flash('error', 'Code et nom obligatoires.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        $id = $this->definitions->create($tenantId, [
            'code' => $code,
            'name' => $name,
            'category' => $request->input('category'),
            'description' => $request->input('description'),
        ], (int) Session::get('user_id'));
        $cover = $this->maybeStoreCover($tenantId, 'figure', $_FILES['cover'] ?? []);
        if ($cover['path'] !== null) {
            $this->definitions->setCover($tenantId, $id, $cover['path']);
        } elseif ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);
        }
        Session::flash('success', 'Article de dotation créé.');

        return Response::redirect(url('equipment') . '?tab=dotation&dotation=' . $id);
    }

    public function updateDotationItem(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        if (!$this->canManageDotation()) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('equipment') . '?tab=dotation&dotation=' . $id);
        }
        $existing = $this->definitions->find($tenantId, $id);
        if ($existing === null) {
            Session::flash('error', 'Article introuvable.');

            return Response::redirect(url('equipment') . '?tab=dotation');
        }
        $this->definitions->update($tenantId, $id, [
            'code' => $request->input('code', $existing['code'] ?? ''),
            'name' => $request->input('name', $existing['name'] ?? ''),
            'category' => $request->input('category', $existing['category'] ?? ''),
            'description' => $request->input('description', $existing['description'] ?? ''),
        ]);
        $cover = $this->maybeStoreCover($tenantId, 'figure', $_FILES['cover'] ?? []);
        if ($cover['error'] !== null) {
            Session::flash('error', $cover['error']);
        } elseif ($cover['path'] !== null) {
            $this->definitions->setCover($tenantId, $id, $cover['path']);
        }
        Session::flash('success', 'Article mis à jour.');

        return Response::redirect(url('equipment') . '?tab=dotation&dotation=' . $id);
    }

    public function showDotationItem(Request $request, array $params = []): Response
    {
        $gate = $this->gate();
        if ($gate instanceof Response) {
            return $gate;
        }
        [$tenantId] = $gate;
        $id = (int) ($params['id'] ?? 0);
        $row = $id > 0 ? $this->definitions->find($tenantId, $id) : null;
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }

        return Response::json([
            'ok' => true,
            'item' => [
                'id' => (int) ($row['id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'cover_url' => $row['cover_url'] ?? null,
                'can_edit' => $this->canManageDotation(),
                'admin_url' => url('back-office/referentiels/dotation/' . $id),
            ],
            'csrf' => Csrf::token(),
            'urls' => [
                'update' => url('equipment/dotation/' . $id),
            ],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $wardrobes
     * @param list<array<string, mixed>> $collections
     * @param list<array<string, mixed>> $classes
     * @param list<array<string, mixed>> $dotation
     */
    private function hubView(
        bool $migrationMissing,
        array $wardrobes,
        array $collections,
        array $classes,
        array $dotation = [],
    ): Response {
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
            'dotationItems' => $dotation,
            'canManageCatalog' => $this->canManageCatalog(),
            'canManageDotation' => $this->canManageDotation(),
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

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeUploadList(mixed $files): array
    {
        if (!is_array($files) || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $out = [];
        foreach ($files['name'] as $i => $name) {
            $out[] = [
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }

        return $out;
    }

    private function canManageCatalog(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.equipment.manage')
            || $gate->allows('site.support');
    }

    private function canManageDotation(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('personnel.equipment.manage')
            || $gate->allows('personnel.assignments.manage')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access');
    }
}
