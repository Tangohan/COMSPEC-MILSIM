<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantCustomMapRepository;
use App\Services\Auth\AuthService;
use App\Services\Maps\TenantCustomMapStorage;

/**
 * CRUD cartes custom (fond image) — Overwatch / TACMAP.
 */
final class CustomMapsApiController
{
    public function __construct(
        private AuthService $authService,
        private TenantCustomMapRepository $repo,
        private TenantCustomMapStorage $storage,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $ctx = $this->requireMember();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;
        $rows = $this->repo->listActiveForTenant($tenantId);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->present($row, $userId);
        }

        return Response::json(['maps' => $out]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $ctx = $this->requireMember();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;

        if (!$this->csrfOk($request)) {
            return Response::json(['error' => 'Session expirée. Rechargez la page.'], 419);
        }

        $label = trim((string) $request->input('label'));
        if (function_exists('mb_substr')) {
            $label = mb_substr($label, 0, 120);
        } else {
            $label = substr($label, 0, 120);
        }
        if ($label === '' || (function_exists('mb_strlen') ? mb_strlen($label) : strlen($label)) < 2) {
            return Response::json(['error' => 'Indiquez un nom de carte (au moins 2 caractères).'], 422);
        }

        $file = $_FILES['image'] ?? null;
        if (!is_array($file)) {
            return Response::json(['error' => 'Choisissez une image de fond.'], 422);
        }

        $stored = $this->storage->storeUpload($tenantId, $file);
        if (!$stored['ok']) {
            return Response::json(['error' => $stored['error']], 422);
        }

        $slug = $this->uniqueSlug($tenantId, $label);
        try {
            $id = $this->repo->create(
                $tenantId,
                $userId,
                $label,
                $slug,
                $stored['path'],
                $stored['width'],
                $stored['height']
            );
        } catch (\Throwable) {
            $this->storage->deleteFile($stored['path']);

            return Response::json(['error' => 'Impossible d’enregistrer la carte. Réessayez.'], 500);
        }

        $row = $this->repo->findByIdForTenant($id, $tenantId);
        if ($row === null) {
            return Response::json(['error' => 'Carte créée mais introuvable.'], 500);
        }

        return Response::json(['map' => $this->present($row, $userId)], 201);
    }

    public function update(Request $request, array $params = []): Response
    {
        $ctx = $this->requireMember();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;

        if (!$this->csrfOk($request)) {
            return Response::json(['error' => 'Session expirée. Rechargez la page.'], 419);
        }

        $id = (int) ($params['id'] ?? 0);
        $row = $this->repo->findByIdForTenant($id, $tenantId);
        if ($row === null || empty($row['is_active'])) {
            return Response::json(['error' => 'Carte introuvable.'], 404);
        }
        if (!$this->canManage($row, $userId)) {
            return Response::json(['error' => 'Vous ne pouvez pas modifier cette carte.'], 403);
        }

        $label = trim((string) $request->input('label'));
        if (function_exists('mb_substr')) {
            $label = mb_substr($label, 0, 120);
        } else {
            $label = substr($label, 0, 120);
        }
        if ($label === '' || (function_exists('mb_strlen') ? mb_strlen($label) : strlen($label)) < 2) {
            return Response::json(['error' => 'Indiquez un nom de carte (au moins 2 caractères).'], 422);
        }

        if (!$this->repo->updateLabel($id, $tenantId, $label)) {
            return Response::json(['error' => 'Modification impossible.'], 500);
        }
        $updated = $this->repo->findByIdForTenant($id, $tenantId);

        return Response::json(['map' => $this->present($updated ?? $row, $userId)]);
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $ctx = $this->requireMember();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $userId] = $ctx;

        if (!$this->csrfOk($request)) {
            return Response::json(['error' => 'Session expirée. Rechargez la page.'], 419);
        }

        $id = (int) ($params['id'] ?? 0);
        $row = $this->repo->findByIdForTenant($id, $tenantId);
        if ($row === null || empty($row['is_active'])) {
            return Response::json(['error' => 'Carte introuvable.'], 404);
        }
        if (!$this->canManage($row, $userId)) {
            return Response::json(['error' => 'Vous ne pouvez pas supprimer cette carte.'], 403);
        }

        if (!$this->repo->softDelete($id, $tenantId)) {
            return Response::json(['error' => 'Suppression impossible.'], 500);
        }
        $this->storage->deleteFile(isset($row['image_path']) ? (string) $row['image_path'] : null);

        return Response::json(['ok' => true]);
    }

    /**
     * @return array{0:int,1:int}|Response
     */
    private function requireMember(): array|Response
    {
        $user = $this->authService->user();
        if (!$user) {
            return Response::json(['error' => 'Connexion requise.'], 401);
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) ($user['id'] ?? 0);
        if ($tenantId <= 0 || $userId <= 0) {
            return Response::json(['error' => 'Aucune communauté active.'], 403);
        }

        return [$tenantId, $userId];
    }

    private function csrfOk(Request $request): bool
    {
        $token = (string) ($request->input('_csrf_token')
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? '');

        return Csrf::validate($token);
    }

    /** @param array<string, mixed> $row */
    private function canManage(array $row, int $userId): bool
    {
        if ((int) ($row['created_by'] ?? 0) === $userId) {
            return true;
        }
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access');
    }

    private function uniqueSlug(int $tenantId, string $label): string
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $label) ?? ''));
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'carte';
        }
        if (function_exists('mb_substr')) {
            $base = mb_substr($base, 0, 48);
        } else {
            $base = substr($base, 0, 48);
        }
        $slug = 'custom-' . $base;
        $n = 0;
        while ($this->repo->findBySlugForTenant($slug, $tenantId) !== null) {
            $n++;
            $slug = 'custom-' . $base . '-' . $n;
            if ($n > 50) {
                $slug = 'custom-' . bin2hex(random_bytes(4));
                break;
            }
        }

        return $slug;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row, int $viewerId): array
    {
        $w = (int) ($row['image_width'] ?? 0);
        $h = (int) ($row['image_height'] ?? 0);
        $path = (string) ($row['image_path'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'mapId' => (int) ($row['map_id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'type' => 'image',
            'imageUrl' => $path !== '' ? TenantCustomMapStorage::publicUrl($path) : '',
            'imageWidth' => $w,
            'imageHeight' => $h,
            'bounds' => [[0, 0], [$h, $w]],
            'center' => [$h / 2, $w / 2],
            'minZoom' => -2,
            'maxZoom' => 4,
            'defaultZoom' => 0,
            'canManage' => $this->canManage($row, $viewerId),
        ];
    }
}
