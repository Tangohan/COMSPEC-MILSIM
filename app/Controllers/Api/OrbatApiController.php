<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\OrbatChartTypeRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Support\OrbatChartDisplay;
use App\Support\OrbatMaskMode;
use App\Support\OrbatRosterPayload;

/**
 * ORBAT : lecture JSON, mise à jour unité et opérations de structure pour les gérants.
 */
final class OrbatApiController
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UserRepository $userRepository,
        private OrbatChartTypeRepository $orbatChartTypeRepository
    ) {}

    public function roster(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $gate = Gate::getInstance();
        $canBypass = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('organization.orbat.manage');

        $payload = OrbatRosterPayload::buildForTenant($this->unitRepository, $tenantId, $userId, $canBypass);

        return Response::json([
            'success' => true,
            'roster' => $payload,
        ]);
    }

    /** Liste plate des unités pour rattachement (gestionnaires uniquement). */
    public function structureOptions(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !Session::get('user_id')) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access') && !$gate->allows('organization.orbat.manage')) {
            return Response::json(['success' => false, 'message' => 'Droits insuffisants'], 403);
        }

        return Response::json([
            'success' => true,
            'units' => $this->unitRepository->listFlatForStructure($tenantId),
            'maskModes' => [
                ['id' => OrbatMaskMode::NONE, 'label' => 'Aucun masque'],
                ['id' => OrbatMaskMode::HIDDEN_ALL, 'label' => 'Masquer toute la branche aux personnes extérieures'],
                ['id' => OrbatMaskMode::SCOPE_SECTION, 'label' => 'Limiter comme une section (noms protégés hors périmètre)'],
                ['id' => OrbatMaskMode::SCOPE_TEAM, 'label' => 'Limiter comme une équipe (noms protégés hors périmètre)'],
                ['id' => OrbatMaskMode::SCOPE_ROLE, 'label' => 'Protéger selon les affectations (hors unité)'],
                ['id' => OrbatMaskMode::ANONYMIZE, 'label' => 'Anonymisation simple des noms'],
            ],
            'structTypes' => $this->structTypeOptions(),
            'chartDisplayTypes' => $this->mergedChartDisplayTypes($tenantId),
        ]);
    }

    /**
     * Création / suppression d’un type d’affichage personnalisé pour l’organigramme.
     * Corps : action = create | delete, label (create), slug (delete ou optionnel create)
     */
    public function chartType(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access') && !$gate->allows('organization.orbat.manage')) {
            return Response::json(['success' => false, 'message' => 'Droits insuffisants'], 403);
        }
        if (!$this->orbatChartTypeRepository->tableExists()) {
            return Response::json(['success' => false, 'message' => 'Fonctionnalité non disponible sur cette base.'], 503);
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Session expirée, rechargez la page'], 403);
        }

        $action = strtolower(trim((string) $request->input('action', '')));
        if ($action === 'create') {
            $label = trim((string) $request->input('label', ''));
            if ($label === '' || mb_strlen($label) > 120) {
                return Response::json(['success' => false, 'message' => 'Indiquez un nom lisible pour ce type.'], 400);
            }
            $slug = trim((string) $request->input('slug', ''));
            $slug = $slug !== '' ? OrbatChartDisplay::sanitizeSlug($slug) : OrbatChartDisplay::slugFromLabel($label);
            if ($slug === '' || in_array($slug, OrbatChartDisplay::BUILTIN_SLUGS, true)) {
                return Response::json(['success' => false, 'message' => 'Ce nom produit une référence réservée ou invalide. Choisissez un autre libellé.'], 400);
            }
            if ($this->orbatChartTypeRepository->findBySlug($tenantId, $slug) !== null) {
                return Response::json(['success' => false, 'message' => 'Un type avec cette référence existe déjà.'], 400);
            }
            if (!$this->orbatChartTypeRepository->create($tenantId, $slug, $label)) {
                return Response::json(['success' => false, 'message' => 'Enregistrement impossible.'], 400);
            }

            return Response::json([
                'success' => true,
                'chartDisplayTypes' => $this->mergedChartDisplayTypes($tenantId),
            ]);
        }
        if ($action === 'delete') {
            $slug = OrbatChartDisplay::sanitizeSlug((string) $request->input('slug', ''));
            if ($slug === '' || in_array($slug, OrbatChartDisplay::BUILTIN_SLUGS, true)) {
                return Response::json(['success' => false, 'message' => 'Type non supprimable.'], 400);
            }
            if ($this->unitRepository->countUnitsWithOrbatDisplayType($tenantId, $slug) > 0) {
                return Response::json([
                    'success' => false,
                    'message' => 'Des unités utilisent encore ce type. Changez leur style sur l’organigramme avant de supprimer.',
                ], 400);
            }
            if (!$this->orbatChartTypeRepository->delete($tenantId, $slug)) {
                return Response::json(['success' => false, 'message' => 'Suppression impossible ou type introuvable.'], 400);
            }

            return Response::json([
                'success' => true,
                'chartDisplayTypes' => $this->mergedChartDisplayTypes($tenantId),
            ]);
        }

        return Response::json(['success' => false, 'message' => 'Action non reconnue'], 400);
    }

    /** Envoi d’icône (PNG, ICO) ou d’image de carte (PNG, JPG) pour une unité. */
    public function uploadUnitMedia(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access') && !$gate->allows('organization.orbat.manage')) {
            return Response::json(['success' => false, 'message' => 'Droits insuffisants'], 403);
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Session expirée, rechargez la page'], 403);
        }
        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId < 1) {
            return Response::json(['success' => false, 'message' => 'Unité non valide'], 400);
        }
        $unit = $this->unitRepository->findById($unitId, $tenantId);
        if (!$unit) {
            return Response::json(['success' => false, 'message' => 'Unité introuvable'], 404);
        }
        if (!$this->unitRepository->hasTableColumn('units', 'orbat_icon_path')
            || !$this->unitRepository->hasTableColumn('units', 'orbat_image_path')) {
            return Response::json(['success' => false, 'message' => 'Fonctionnalité non disponible sur cette base.'], 503);
        }

        $slot = strtolower(trim((string) $request->input('slot', 'icon')));
        if (!in_array($slot, ['icon', 'image'], true)) {
            return Response::json(['success' => false, 'message' => 'Emplacement non reconnu'], 400);
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return Response::json(['success' => false, 'message' => 'Aucun fichier reçu.'], 400);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return Response::json(['success' => false, 'message' => 'Fichier invalide.'], 400);
        }
        if ((int) ($file['size'] ?? 0) > 2_500_000) {
            return Response::json(['success' => false, 'message' => 'Fichier trop volumineux (limite 2,5 Mo).'], 400);
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedExt = $slot === 'icon' ? ['png', 'ico', 'jpg', 'jpeg'] : ['png', 'jpg', 'jpeg'];
        if (!in_array($ext, $allowedExt, true)) {
            return Response::json(['success' => false, 'message' => 'Format non accepté pour cet emplacement.'], 400);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowedMime = $slot === 'icon'
            ? ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/jpeg']
            : ['image/png', 'image/jpeg'];
        $mimeOk = in_array($mime, $allowedMime, true);
        if (!$mimeOk && $slot === 'icon' && $ext === 'ico' && ($mime === 'application/octet-stream' || $mime === '')) {
            $mimeOk = true;
        }
        if (!$mimeOk) {
            return Response::json(['success' => false, 'message' => 'Le contenu du fichier ne correspond pas à une image attendue.'], 400);
        }

        $baseDir = base_path('public/uploads/orbat/' . $tenantId);
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
            return Response::json(['success' => false, 'message' => 'Stockage fichier indisponible.'], 500);
        }

        $safe = 'u' . $unitId . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $destFs = $baseDir . '/' . $safe;
        if (!@move_uploaded_file($tmp, $destFs)) {
            return Response::json(['success' => false, 'message' => 'Enregistrement du fichier impossible.'], 500);
        }

        $webPath = '/uploads/orbat/' . $tenantId . '/' . $safe;
        $field = $slot === 'icon' ? 'orbat_icon_path' : 'orbat_image_path';
        $this->unitRepository->update($unitId, $tenantId, [$field => $webPath]);

        $canBypass = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('organization.orbat.manage');

        return Response::json([
            'success' => true,
            'path' => $webPath,
            'roster' => OrbatRosterPayload::buildForTenant($this->unitRepository, $tenantId, $userId, $canBypass),
        ]);
    }

    /** @return list<array{id: string, label: string, builtin: bool}> */
    private function mergedChartDisplayTypes(int $tenantId): array
    {
        $out = OrbatChartDisplay::builtinOptionsForUi();
        foreach ($this->orbatChartTypeRepository->listForTenant($tenantId) as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $out[] = [
                'id' => $slug,
                'label' => (string) ($row['label'] ?? $slug),
                'builtin' => false,
            ];
        }

        return $out;
    }

    private function isAllowedChartDisplaySlug(int $tenantId, string $raw): bool
    {
        $slug = OrbatChartDisplay::sanitizeSlug($raw);
        if ($slug === '') {
            return false;
        }
        if (in_array($slug, OrbatChartDisplay::BUILTIN_SLUGS, true)) {
            return true;
        }

        return $this->orbatChartTypeRepository->findBySlug($tenantId, $slug) !== null;
    }

    /**
     * Corps JSON ou form : action = create | delete | move | set_mask
     */
    public function structure(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access') && !$gate->allows('organization.orbat.manage')) {
            return Response::json(['success' => false, 'message' => 'Droits insuffisants'], 403);
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Session expirée, rechargez la page'], 403);
        }

        $action = strtolower(trim((string) $request->input('action', '')));

        return match ($action) {
            'create' => $this->structureCreate($request, $tenantId),
            'delete' => $this->structureDelete($request, $tenantId),
            'move' => $this->structureMove($request, $tenantId),
            'set_mask' => $this->structureSetMask($request, $tenantId),
            default => Response::json(['success' => false, 'message' => 'Action non reconnue'], 400),
        };
    }

    private function structureCreate(Request $request, int $tenantId): Response
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '' || mb_strlen($name) > 255) {
            return Response::json(['success' => false, 'message' => 'Le nom de l’unité est requis.'], 400);
        }

        $defaultType = (string) config('units.default_type', 'unit');
        $structType = strtolower(trim((string) $request->input('struct_type', $defaultType)));
        $allowedTypes = array_keys(config('units.types', []));
        if ($allowedTypes !== [] && !in_array($structType, $allowedTypes, true)) {
            return Response::json(['success' => false, 'message' => 'Type d’unité non reconnu.'], 400);
        }

        $parentRaw = $request->input('parent_id');
        $parentId = $parentRaw === null || $parentRaw === '' ? null : (int) $parentRaw;
        if ($parentId !== null && $parentId > 0) {
            $p = $this->unitRepository->findById($parentId, $tenantId);
            if (!$p) {
                return Response::json(['success' => false, 'message' => 'Unité parente introuvable.'], 400);
            }
        } else {
            $parentId = null;
        }

        $slug = $this->unitRepository->uniqueSlugForTenant($tenantId, $name);

        $data = [
            'name' => $name,
            'slug' => $slug,
            'type' => $structType,
            'parent_id' => $parentId,
            'display_order' => 0,
            'show_on_public_page' => 1,
        ];

        $this->unitRepository->create($tenantId, $data);

        return $this->rosterSuccess($tenantId, (int) Session::get('user_id'));
    }

    private function structureDelete(Request $request, int $tenantId): Response
    {
        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId < 1) {
            return Response::json(['success' => false, 'message' => 'Unité non valide.'], 400);
        }
        $unit = $this->unitRepository->findById($unitId, $tenantId);
        if (!$unit) {
            return Response::json(['success' => false, 'message' => 'Unité introuvable.'], 404);
        }
        if ($this->unitRepository->countChildren($unitId, $tenantId) > 0) {
            return Response::json([
                'success' => false,
                'message' => 'Cette unité contient des sous-unités. Déplacez-les ou supprimez-les d’abord.',
            ], 400);
        }

        if (!$this->unitRepository->delete($unitId, $tenantId)) {
            return Response::json(['success' => false, 'message' => 'Suppression impossible.'], 400);
        }

        return $this->rosterSuccess($tenantId, (int) Session::get('user_id'));
    }

    private function structureMove(Request $request, int $tenantId): Response
    {
        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId < 1) {
            return Response::json(['success' => false, 'message' => 'Unité non valide.'], 400);
        }
        $unit = $this->unitRepository->findById($unitId, $tenantId);
        if (!$unit) {
            return Response::json(['success' => false, 'message' => 'Unité introuvable.'], 404);
        }

        $parentRaw = $request->input('parent_id');
        $newParent = $parentRaw === null || $parentRaw === '' ? null : (int) $parentRaw;
        if ($newParent !== null && $newParent < 1) {
            $newParent = null;
        }

        if ($newParent !== null) {
            $p = $this->unitRepository->findById($newParent, $tenantId);
            if (!$p) {
                return Response::json(['success' => false, 'message' => 'Unité cible introuvable.'], 400);
            }
            if ($this->wouldCreateCycle($unitId, $newParent, $tenantId)) {
                return Response::json(['success' => false, 'message' => 'Ce rattachement créerait une boucle dans la hiérarchie.'], 400);
            }
        }

        $this->unitRepository->update($unitId, $tenantId, ['parent_id' => $newParent]);

        return $this->rosterSuccess($tenantId, (int) Session::get('user_id'));
    }

    private function structureSetMask(Request $request, int $tenantId): Response
    {
        if (!$this->unitRepository->hasTableColumn('units', 'orbat_mask_mode')) {
            return Response::json(['success' => false, 'message' => 'Fonctionnalité non disponible sur cette base.'], 503);
        }

        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId < 1) {
            return Response::json(['success' => false, 'message' => 'Unité non valide.'], 400);
        }
        $unit = $this->unitRepository->findById($unitId, $tenantId);
        if (!$unit) {
            return Response::json(['success' => false, 'message' => 'Unité introuvable.'], 404);
        }

        $mode = OrbatMaskMode::normalize((string) $request->input('orbat_mask_mode', ''));
        $this->unitRepository->update($unitId, $tenantId, ['orbat_mask_mode' => $mode]);

        return $this->rosterSuccess($tenantId, (int) Session::get('user_id'));
    }

    private function rosterSuccess(int $tenantId, int $userId): Response
    {
        $gate = Gate::getInstance();
        $canBypass = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('organization.orbat.manage');

        return Response::json([
            'success' => true,
            'roster' => OrbatRosterPayload::buildForTenant($this->unitRepository, $tenantId, $userId, $canBypass),
        ]);
    }

    private function wouldCreateCycle(int $unitId, int $newParentId, int $tenantId): bool
    {
        if ($newParentId === $unitId) {
            return true;
        }
        $current = $newParentId;
        $guard = 0;
        while ($current > 0 && $guard++ < 8000) {
            if ($current === $unitId) {
                return true;
            }
            $row = $this->unitRepository->findById($current, $tenantId);
            if (!$row) {
                break;
            }
            $current = (int) ($row['parent_id'] ?? 0);
        }

        return false;
    }

    /** @return list<array{id: string, label: string}> */
    private function structTypeOptions(): array
    {
        $types = config('units.types', []);
        $out = [];
        foreach ($types as $id => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $out[] = [
                'id' => (string) $id,
                'label' => (string) ($meta['label'] ?? $id),
            ];
        }

        return $out;
    }

    public function updateUnit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access') && !$gate->allows('organization.orbat.manage')) {
            return Response::json(['success' => false, 'message' => 'Droits insuffisants'], 403);
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Session expirée, rechargez la page'], 403);
        }

        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId < 1) {
            return Response::json(['success' => false, 'message' => 'Unité non valide'], 400);
        }

        $unit = $this->unitRepository->findById($unitId, $tenantId);
        if (!$unit) {
            return Response::json(['success' => false, 'message' => 'Unité introuvable'], 404);
        }

        $data = [];
        if ($request->input('name') !== null) {
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                return Response::json(['success' => false, 'message' => 'Le nom de l’unité est requis'], 400);
            }
            if (mb_strlen($name) > 255) {
                return Response::json(['success' => false, 'message' => 'Nom trop long'], 400);
            }
            $data['name'] = $name;
        }

        if ($request->input('code') !== null) {
            $code = trim((string) $request->input('code', ''));
            $data['code'] = $code === '' ? null : mb_substr($code, 0, 20);
        }

        if ($request->input('public_blurb') !== null) {
            $blurb = trim((string) $request->input('public_blurb', ''));
            $data['public_blurb'] = $blurb === '' ? null : mb_substr($blurb, 0, 8000);
        }

        if ($request->input('orbat_type') !== null) {
            $t = strtolower(trim((string) $request->input('orbat_type', '')));
            if (!$this->isAllowedChartDisplaySlug($tenantId, $t)) {
                return Response::json(['success' => false, 'message' => 'Type d’affichage non reconnu'], 400);
            }
            $slug = OrbatChartDisplay::sanitizeSlug($t);
            if ($this->unitRepository->hasTableColumn('units', 'orbat_display_type')) {
                $data['orbat_display_type'] = $slug;
            } else {
                $data['type'] = $slug;
            }
        }

        if ($request->input('orbat_details') !== null && $this->unitRepository->hasTableColumn('units', 'orbat_details')) {
            $det = trim((string) $request->input('orbat_details', ''));
            $data['orbat_details'] = $det === '' ? null : mb_substr($det, 0, 16000);
        }

        if ($request->input('clear_chart_icon') === '1' && $this->unitRepository->hasTableColumn('units', 'orbat_icon_path')) {
            $data['orbat_icon_path'] = null;
        }
        if ($request->input('clear_chart_image') === '1' && $this->unitRepository->hasTableColumn('units', 'orbat_image_path')) {
            $data['orbat_image_path'] = null;
        }

        if ($request->input('commander_user_id') !== null) {
            $raw = $request->input('commander_user_id');
            if ($raw === '' || $raw === null) {
                $data['commander_user_id'] = null;
            } else {
                $cid = (int) $raw;
                if ($cid < 1) {
                    $data['commander_user_id'] = null;
                } else {
                    $cmd = $this->userRepository->findById($cid, $tenantId);
                    if (!$cmd || (string) ($cmd['status'] ?? '') !== 'active') {
                        return Response::json(['success' => false, 'message' => 'Chef d’unité introuvable ou compte inactif'], 400);
                    }
                    $data['commander_user_id'] = $cid;
                }
            }
        }

        if ($data === []) {
            return Response::json(['success' => true, 'message' => null]);
        }

        $this->unitRepository->update($unitId, $tenantId, $data);

        $canBypass = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('organization.orbat.manage');

        return Response::json([
            'success' => true,
            'roster' => OrbatRosterPayload::buildForTenant($this->unitRepository, $tenantId, $userId, $canBypass),
        ]);
    }
}
