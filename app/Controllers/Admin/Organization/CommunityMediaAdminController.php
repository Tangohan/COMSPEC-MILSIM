<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityMediaRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\CommunityMediaUploadService;
use App\Support\CommunityMediaDetails;
use App\Support\CommunityMediaStaffAccess;

final class CommunityMediaAdminController
{
    public function __construct(
        private CommunityMediaRepository $media,
        private CommunityMediaUploadService $uploads,
        private AuthService $authService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if ($denied = $this->denyUnlessAccess()) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $gate = Gate::getInstance();

        return Response::view('layout.main', [
            'title' => 'Médias de la communauté',
            'content' => 'admin.organization.media_index',
            'backOfficePageCss' => ['back-office-media.css'],
            'mediaItems' => $this->media->listItems($tenantId),
            'mediaCollections' => $this->media->listCollections($tenantId),
            'canUpload' => CommunityMediaStaffAccess::canUpload($gate),
            'canManageCollections' => CommunityMediaStaffAccess::canManageCollections($gate),
            'canPublish' => CommunityMediaStaffAccess::canPublish($gate),
            'kindLabels' => CommunityMediaDetails::kindLabels(),
            'statusLabels' => CommunityMediaDetails::statusLabels(),
            'blurModeLabels' => CommunityMediaDetails::blurModeLabels(),
        ]);
    }

    public function storeCollection(Request $request, array $params = []): Response
    {
        if ($denied = $this->denyUnlessCollections()) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/media'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->authService->user();
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'Donnez un nom à la collection.');

            return Response::redirect(url('back-office/media'));
        }
        $this->media->createCollection($tenantId, [
            'title' => $title,
            'description' => trim((string) $request->input('description', '')),
            'is_public' => $request->input('is_public') ? 1 : 0,
            'sort_order' => (int) $request->input('sort_order', 0),
        ], $user ? (int) $user['id'] : null);
        Session::flash('success', 'Collection créée.');

        return Response::redirect(url('back-office/media'));
    }

    public function updateCollection(Request $request, array $params = []): Response
    {
        if ($denied = $this->denyUnlessCollections()) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/media'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($this->media->findCollection($id, $tenantId) === null) {
            Session::flash('error', 'Collection introuvable.');

            return Response::redirect(url('back-office/media'));
        }
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Session::flash('error', 'Donnez un nom à la collection.');

            return Response::redirect(url('back-office/media'));
        }
        $this->media->updateCollection($id, $tenantId, [
            'title' => $title,
            'description' => trim((string) $request->input('description', '')),
            'is_public' => $request->input('is_public') ? 1 : 0,
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
        Session::flash('success', 'Collection mise à jour.');

        return Response::redirect(url('back-office/media'));
    }

    public function deleteCollection(Request $request, array $params = []): Response
    {
        if ($denied = $this->denyUnlessCollections()) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/media'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if ($this->media->findCollection($id, $tenantId) === null) {
            Session::flash('error', 'Collection introuvable.');

            return Response::redirect(url('back-office/media'));
        }
        $this->media->deleteCollection($id, $tenantId);
        Session::flash('success', 'Collection supprimée. Les médias associés restent dans la bibliothèque.');

        return Response::redirect(url('back-office/media'));
    }

    public function storeItem(Request $request, array $params = []): Response
    {
        if ($denied = $this->denyUnlessUpload()) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/media'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $user = $this->authService->user();
        $kind = (string) $request->input('media_kind', CommunityMediaDetails::KIND_IMAGE);
        if (!isset(CommunityMediaDetails::kindLabels()[$kind])) {
            Session::flash('error', 'Type de média non reconnu.');

            return Response::redirect(url('back-office/media'));
        }

        $title = trim((string) $request->input('title', ''));
        $payload = [
            'media_kind' => $kind,
            'title' => $title !== '' ? $title : CommunityMediaDetails::kindLabel($kind),
            'caption' => trim((string) $request->input('caption', '')),
            'collection_id' => (int) $request->input('collection_id', 0),
            'sort_order' => (int) $request->input('sort_order', 0),
            'blur_mode' => CommunityMediaDetails::BLUR_NONE,
            'blur_regions_json' => null,
            'status' => CommunityMediaDetails::STATUS_DRAFT,
            'show_on_public_page' => 0,
            'is_hero' => 0,
        ];

        if ($kind === CommunityMediaDetails::KIND_LONG_VIDEO) {
            $norm = $this->uploads->normalizeLongVideoUrl((string) $request->input('external_url', ''));
            if ($norm['error'] !== null) {
                Session::flash('error', (string) $norm['error']);

                return Response::redirect(url('back-office/media'));
            }
            $payload['external_url'] = $norm['url'];
        } elseif ($kind === CommunityMediaDetails::KIND_SHORT_VIDEO) {
            $file = $_FILES['media_file'] ?? null;
            if (!is_array($file)) {
                Session::flash('error', 'Sélectionnez une vidéo courte à téléverser.');

                return Response::redirect(url('back-office/media'));
            }
            $stored = $this->uploads->storeShortVideo($file, $tenantId);
            if ($stored['error'] !== null) {
                Session::flash('error', (string) $stored['error']);

                return Response::redirect(url('back-office/media'));
            }
            $payload['storage_path'] = $stored['path'];
            $payload['mime_type'] = $stored['mime'];
            $payload['file_size'] = $stored['size'];
            $hint = (int) $request->input('duration_seconds', 0);
            if ($hint > 0) {
                $payload['duration_seconds'] = min($hint, CommunityMediaUploadService::MAX_SHORT_VIDEO_SECONDS_HINT * 2);
            }
        } else {
            $file = $_FILES['media_file'] ?? null;
            if (!is_array($file)) {
                Session::flash('error', 'Sélectionnez une image à téléverser.');

                return Response::redirect(url('back-office/media'));
            }
            $stored = $this->uploads->storeImage($file, $tenantId);
            if ($stored['error'] !== null) {
                Session::flash('error', (string) $stored['error']);

                return Response::redirect(url('back-office/media'));
            }
            $payload['storage_path'] = $stored['path'];
            $payload['mime_type'] = $stored['mime'];
            $payload['file_size'] = $stored['size'];
            $payload['width'] = $stored['width'];
            $payload['height'] = $stored['height'];
        }

        $id = $this->media->createItem($tenantId, $payload, $user ? (int) $user['id'] : null);
        Session::flash('success', 'Média ajouté. Vous pouvez maintenant le flouter et le publier.');

        return Response::redirect(url('back-office/media/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        if ($denied = $this->denyUnlessAccess()) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $item = $this->media->findItem($id, $tenantId);
        if ($item === null) {
            Session::flash('error', 'Média introuvable.');

            return Response::redirect(url('back-office/media'));
        }
        $gate = Gate::getInstance();

        return Response::view('layout.main', [
            'title' => 'Éditer un média',
            'content' => 'admin.organization.media_edit',
            'backOfficePageCss' => ['back-office-media.css'],
            'mediaItem' => $item,
            'mediaCollections' => $this->media->listCollections($tenantId),
            'canUpload' => CommunityMediaStaffAccess::canUpload($gate),
            'canPublish' => CommunityMediaStaffAccess::canPublish($gate),
            'kindLabels' => CommunityMediaDetails::kindLabels(),
            'statusLabels' => CommunityMediaDetails::statusLabels(),
            'blurModeLabels' => CommunityMediaDetails::blurModeLabels(),
            'blurRegions' => CommunityMediaDetails::parseBlurRegions($item['blur_regions_json'] ?? null),
            'publicMediaUrl' => CommunityMediaDetails::publicUrl(isset($item['storage_path']) ? (string) $item['storage_path'] : null),
            'embedUrl' => CommunityMediaDetails::embedUrl(isset($item['external_url']) ? (string) $item['external_url'] : null),
            'faceBlurAutoAvailable' => true,
        ]);
    }

    public function updateItem(Request $request, array $params = []): Response
    {
        if ($denied = $this->denyUnlessUpload()) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/media'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $item = $this->media->findItem($id, $tenantId);
        if ($item === null) {
            Session::flash('error', 'Média introuvable.');

            return Response::redirect(url('back-office/media'));
        }

        $blurMode = (string) $request->input('blur_mode', CommunityMediaDetails::BLUR_NONE);
        if (!isset(CommunityMediaDetails::blurModeLabels()[$blurMode])) {
            $blurMode = CommunityMediaDetails::BLUR_NONE;
        }
        $regions = CommunityMediaDetails::parseBlurRegions((string) $request->input('blur_regions_json', ''));
        $regionsJson = $regions !== [] ? json_encode($regions, JSON_UNESCAPED_UNICODE) : null;

        $status = (string) $request->input('status', CommunityMediaDetails::STATUS_DRAFT);
        if (!isset(CommunityMediaDetails::statusLabels()[$status])) {
            $status = CommunityMediaDetails::STATUS_DRAFT;
        }

        $showPublic = $request->input('show_on_public_page') ? 1 : 0;
        $isHero = $request->input('is_hero') ? 1 : 0;
        if (($showPublic || $isHero || $status === CommunityMediaDetails::STATUS_PUBLISHED)
            && !CommunityMediaStaffAccess::canPublish(Gate::getInstance())
        ) {
            Session::flash('error', 'Vous n’avez pas le droit de publier sur la page publique.');

            return Response::redirect(url('back-office/media/' . $id));
        }

        $data = [
            'title' => trim((string) $request->input('title', '')),
            'caption' => trim((string) $request->input('caption', '')),
            'collection_id' => (int) $request->input('collection_id', 0),
            'sort_order' => (int) $request->input('sort_order', 0),
            'blur_mode' => $blurMode,
            'blur_regions_json' => $regionsJson,
            'status' => $status,
            'show_on_public_page' => $showPublic,
            'is_hero' => $isHero,
            'media_kind' => $item['media_kind'],
            'storage_path' => $item['storage_path'] ?? null,
            'external_url' => $item['external_url'] ?? null,
            'mime_type' => $item['mime_type'] ?? null,
            'file_size' => $item['file_size'] ?? null,
            'duration_seconds' => $item['duration_seconds'] ?? null,
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
        ];

        if ((string) ($item['media_kind'] ?? '') === CommunityMediaDetails::KIND_LONG_VIDEO) {
            $norm = $this->uploads->normalizeLongVideoUrl((string) $request->input('external_url', (string) ($item['external_url'] ?? '')));
            if ($norm['error'] !== null) {
                Session::flash('error', (string) $norm['error']);

                return Response::redirect(url('back-office/media/' . $id));
            }
            $data['external_url'] = $norm['url'];
        }

        $flash = 'Média enregistré.';
        if ($blurMode === CommunityMediaDetails::BLUR_AUTO_FACE && $regions === []) {
            $data['blur_regions_json'] = json_encode([
                ['x' => 35.0, 'y' => 12.0, 'w' => 30.0, 'h' => 28.0],
            ], JSON_UNESCAPED_UNICODE);
            $flash = 'Média enregistré. Aucun visage détecté automatiquement : une zone indicative a été proposée (ajustez-la manuellement si besoin).';
        }

        $this->media->updateItem($id, $tenantId, $data);
        Session::flash('success', $flash);

        return Response::redirect(url('back-office/media/' . $id));
    }

    public function deleteItem(Request $request, array $params = []): Response
    {
        if ($denied = $this->denyUnlessUpload()) {
            return $denied;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/media'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $path = $this->media->deleteItem($id, $tenantId);
        if ($path !== null) {
            $this->uploads->deleteStoredFile($path);
        }
        Session::flash('success', 'Média supprimé.');

        return Response::redirect(url('back-office/media'));
    }

    private function denyUnlessAccess(): ?Response
    {
        if (CommunityMediaStaffAccess::allows(Gate::getInstance())) {
            return null;
        }
        Session::flash('error', 'Accès réservé à l’équipe médias de la communauté.');

        return Response::redirect(url('dashboard'));
    }

    private function denyUnlessUpload(): ?Response
    {
        if ($denied = $this->denyUnlessAccess()) {
            return $denied;
        }
        if (CommunityMediaStaffAccess::canUpload(Gate::getInstance())) {
            return null;
        }
        Session::flash('error', 'Vous n’avez pas le droit d’ajouter ou modifier des médias.');

        return Response::redirect(url('back-office/media'));
    }

    private function denyUnlessCollections(): ?Response
    {
        if ($denied = $this->denyUnlessAccess()) {
            return $denied;
        }
        if (CommunityMediaStaffAccess::canManageCollections(Gate::getInstance())) {
            return null;
        }
        Session::flash('error', 'Vous n’avez pas le droit de gérer les collections.');

        return Response::redirect(url('back-office/media'));
    }
}
