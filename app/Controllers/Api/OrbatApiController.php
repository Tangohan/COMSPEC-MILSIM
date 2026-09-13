<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\OrbatChartTypeRepository;
use App\Repositories\OrbatBilletRepository;
use App\Repositories\OrganizationVisibilityHistoryRepository;
use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Organization\OrbatBilletService;
use App\Support\OrbatChartDisplay;
use App\Support\OrbatMaskMode;
use App\Support\OrbatRosterPayload;
use App\Support\OrgVisibilityCapabilities;
use App\Support\UnitAdminStatus;
use App\Support\VisibilityLevel;
use App\Services\Organization\OrgVisibilityService;

/**
 * ORBAT : lecture JSON, mise à jour unité et opérations de structure pour les gérants.
 */
final class OrbatApiController
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UserRepository $userRepository,
        private OrbatChartTypeRepository $orbatChartTypeRepository,
        private PersonnelOrgHistoryRepository $personnelOrgHistoryRepository,
        private OrganizationVisibilityHistoryRepository $visibilityHistoryRepository,
        private ?OrbatBilletRepository $billetRepository = null,
        private ?OrbatBilletService $billetService = null,
    ) {}

    public function roster(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'message' => 'Authentification requise'], 401);
        }

        $gate = Gate::getInstance();
        if (!$gate->allows('organization.orbat.view')) {
            return Response::json(['success' => false, 'message' => 'Vous n’avez pas accès à l’organigramme.'], 403);
        }
        $realCaps = OrgVisibilityCapabilities::fromGate($gate);
        $previewAs = null;
        if ($realCaps->managePersonnelVisibility || $realCaps->bypassAll) {
            $rawPreview = strtolower(trim((string) $request->query('preview_as', '')));
            if (in_array($rawPreview, ['member', 'cadre', 'command'], true)) {
                $previewAs = $rawPreview;
            }
        }
        $caps = $realCaps->asPreview($previewAs);
        $canBypass = $caps->bypassAll || $caps->viewHiddenUnits || ($previewAs === null && $gate->allows('organization.orbat.manage'));

        $statusFilter = null;
        $rawFilter = trim((string) $request->query('admin_status', ''));
        if ($rawFilter !== '') {
            $parts = array_filter(array_map('trim', explode(',', $rawFilter)));
            $statusFilter = [];
            foreach ($parts as $p) {
                $statusFilter[] = UnitAdminStatus::normalize($p);
            }
            if ($statusFilter === []) {
                $statusFilter = null;
            }
        } elseif ($request->query('include_archived') === '1' || $request->query('archives') === '1') {
            $statusFilter = UnitAdminStatus::ALL;
        }

        $payload = OrbatRosterPayload::buildForTenant(
            $this->unitRepository,
            $tenantId,
            $userId,
            $canBypass,
            $caps,
            $statusFilter
        );

        return Response::json([
            'success' => true,
            'roster' => $payload,
            'adminStatuses' => UnitAdminStatus::options(),
            'visibilityLevels' => $this->visibilityLevelOptions(),
            'previewAs' => $previewAs,
            'previewAvailable' => $realCaps->managePersonnelVisibility || $realCaps->bypassAll,
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
            'maskModes' => $this->visibilityLevelOptionsAsMaskModes(),
            'visibilityLevels' => $this->visibilityLevelOptions(),
            'adminStatuses' => UnitAdminStatus::options(),
            'structTypes' => $this->structTypeOptions(),
            'chartDisplayTypes' => $this->mergedChartDisplayTypes($tenantId),
            'capabilities' => [
                'mask_editing' => $this->unitRepository->hasTableColumn('units', 'orbat_mask_mode')
                    || $this->unitRepository->hasTableColumn('units', 'visibility_level'),
                'admin_status' => $this->unitRepository->hasTableColumn('units', 'admin_status'),
                'visibility_level' => $this->unitRepository->hasTableColumn('units', 'visibility_level'),
                'struct_type_edit' => true,
                'custom_chart_types' => $this->orbatChartTypeRepository->tableExists(),
                'chart_media_upload' => $this->unitRepository->hasTableColumn('units', 'orbat_icon_path')
                    && $this->unitRepository->hasTableColumn('units', 'orbat_image_path'),
                'details_field' => $this->unitRepository->hasTableColumn('units', 'orbat_details'),
            ],
        ]);
    }

    /**
     * @return list<array{id: string, label: string, consequence: string}>
     */
    private function visibilityLevelOptions(): array
    {
        $out = [];
        foreach (VisibilityLevel::ALL as $id) {
            $out[] = [
                'id' => $id,
                'label' => VisibilityLevel::label($id),
                'consequence' => VisibilityLevel::consequence($id, 'unit'),
            ];
        }

        return $out;
    }

    /**
     * Options de confidentialité (compat UI maskModes) alignées sur VisibilityLevel.
     *
     * @return list<array{id: string, label: string, consequence?: string}>
     */
    private function visibilityLevelOptionsAsMaskModes(): array
    {
        return [
            [
                'id' => OrbatMaskMode::NONE,
                'label' => 'Normale — affichage complet',
                'consequence' => VisibilityLevel::consequence(VisibilityLevel::NORMAL),
                'visibility' => VisibilityLevel::NORMAL,
            ],
            [
                'id' => OrbatMaskMode::ANONYMIZE,
                'label' => 'Anonymisée — libellé générique',
                'consequence' => VisibilityLevel::consequence(VisibilityLevel::ANONYMIZED),
                'visibility' => VisibilityLevel::ANONYMIZED,
            ],
            [
                'id' => OrbatMaskMode::SCOPE_SECTION,
                'label' => 'Restreinte — informations limitées',
                'consequence' => VisibilityLevel::consequence(VisibilityLevel::RESTRICTED),
                'visibility' => VisibilityLevel::RESTRICTED,
            ],
            [
                'id' => OrbatMaskMode::HIDDEN_ALL,
                'label' => 'Masquée — absente pour les non autorisés',
                'consequence' => VisibilityLevel::consequence(VisibilityLevel::HIDDEN),
                'visibility' => VisibilityLevel::HIDDEN,
            ],
        ];
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
            return Response::json([
                'success' => false,
                'code' => 'orbat_schema',
                'message' => 'Les types d’affichage personnalisés ne sont pas encore disponibles sur cet environnement. L’équipe d’hébergement doit appliquer les mises à jour prévues avec la version déployée.',
            ], 503);
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
            return Response::json([
                'success' => false,
                'code' => 'orbat_schema',
                'message' => 'L’envoi d’icônes ou d’images pour les cartes n’est pas encore disponible ici. L’équipe d’hébergement doit appliquer les mises à jour prévues avec la version déployée.',
            ], 503);
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
            'set_visibility' => $this->structureSetVisibility($request, $tenantId),
            'set_status' => $this->structureSetStatus($request, $tenantId),
            'archive' => $this->structureArchive($request, $tenantId),
            'billet_create' => $this->billetCreate($request, $tenantId),
            'billet_update' => $this->billetUpdate($request, $tenantId),
            'billet_delete' => $this->billetDelete($request, $tenantId),
            'billet_occupy' => $this->billetOccupy($request, $tenantId),
            'billet_vacate' => $this->billetVacate($request, $tenantId),
            'billet_restore' => $this->billetRestore($request, $tenantId),
            'structure_sheet' => $this->structureSheet($request, $tenantId),
            'data_quality' => $this->dataQuality($request, $tenantId),
            'orbat_snapshot' => $this->orbatSnapshot($request, $tenantId),
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

        $created = $this->unitRepository->create($tenantId, $data);
        $this->recordUnitCreationHistory($tenantId, $created, $parentId);

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
                'message' => 'Cette unité contient des sous-unités. Déplacez-les ou archivez la structure.',
                'suggest_archive' => true,
            ], 400);
        }

        // Préférer l’archivage si demandé ou si la structure a déjà des membres
        $preferArchive = $request->input('prefer_archive') === '1'
            || $request->input('archive_instead') === '1';
        $memberIds = $this->unitRepository->listActiveUserIdsForUnits($tenantId, [$unitId]);
        if (($preferArchive || $memberIds !== [])
            && $this->unitRepository->hasTableColumn('units', 'admin_status')
            && $request->input('force_delete') !== '1') {
            $oldStatus = UnitAdminStatus::normalize((string) ($unit['admin_status'] ?? UnitAdminStatus::ACTIVE));
            $this->unitRepository->update($unitId, $tenantId, [
                'admin_status' => UnitAdminStatus::ARCHIVED,
                'admin_status_note' => 'Archivé depuis l’ORBAT (préféré à la suppression)',
            ]);
            $this->visibilityHistoryRepository->recordUnitStatus(
                $tenantId,
                $unitId,
                $oldStatus,
                UnitAdminStatus::ARCHIVED,
                (int) Session::get('user_id'),
                'Archivage préféré à la suppression'
            );

            return $this->rosterSuccess($tenantId, (int) Session::get('user_id'));
        }

        if (!$this->unitRepository->delete($unitId, $tenantId)) {
            return Response::json([
                'success' => false,
                'message' => 'Suppression impossible. Archivez plutôt la structure.',
                'suggest_archive' => true,
            ], 400);
        }
        $this->recordUnitDeletionHistory($tenantId, $unit);

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
            return Response::json([
                'success' => false,
                'code' => 'orbat_schema',
                'message' => 'Les réglages de confidentialité sur l’organigramme ne sont pas encore disponibles ici. L’équipe d’hébergement doit appliquer les mises à jour prévues avec la version déployée.',
            ], 503);
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
        $visibility = VisibilityLevel::fromOrbatMaskMode($mode);
        // Accepter aussi visibility_level directement
        if ($request->input('visibility_level') !== null && trim((string) $request->input('visibility_level')) !== '') {
            $visibility = VisibilityLevel::normalize((string) $request->input('visibility_level'));
            $mode = VisibilityLevel::toOrbatMaskMode($visibility);
        }

        $oldMask = OrbatMaskMode::normalize((string) ($unit['orbat_mask_mode'] ?? ''));
        $oldVis = array_key_exists('visibility_level', $unit)
            ? VisibilityLevel::normalize((string) ($unit['visibility_level'] ?? ''))
            : VisibilityLevel::fromOrbatMaskMode($oldMask);

        $data = ['orbat_mask_mode' => $mode];
        if ($this->unitRepository->hasTableColumn('units', 'visibility_level')) {
            $data['visibility_level'] = $visibility;
        }
        if ($request->input('visibility_propagate') !== null && $this->unitRepository->hasTableColumn('units', 'visibility_propagate')) {
            $data['visibility_propagate'] = $request->input('visibility_propagate') ? 1 : 0;
        }
        $this->unitRepository->update($unitId, $tenantId, $data);

        $actorId = (int) Session::get('user_id');
        $reason = trim((string) $request->input('reason', ''));
        $this->visibilityHistoryRepository->record(
            $tenantId,
            'unit',
            $unitId,
            'visibility_level',
            $oldVis,
            $visibility,
            $actorId,
            $reason !== '' ? $reason : null
        );

        return $this->rosterSuccess($tenantId, $actorId);
    }

    private function structureSetVisibility(Request $request, int $tenantId): Response
    {
        if (!$this->unitRepository->hasTableColumn('units', 'visibility_level')
            && !$this->unitRepository->hasTableColumn('units', 'orbat_mask_mode')) {
            return Response::json([
                'success' => false,
                'code' => 'orbat_schema',
                'message' => 'La gestion de visibilité n’est pas encore disponible sur cet environnement.',
            ], 503);
        }

        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId < 1) {
            return Response::json(['success' => false, 'message' => 'Unité non valide.'], 400);
        }
        $unit = $this->unitRepository->findById($unitId, $tenantId);
        if (!$unit) {
            return Response::json(['success' => false, 'message' => 'Unité introuvable.'], 404);
        }

        $visibility = VisibilityLevel::normalize((string) $request->input('visibility_level', VisibilityLevel::NORMAL));
        $mode = VisibilityLevel::toOrbatMaskMode($visibility);
        $oldVis = array_key_exists('visibility_level', $unit)
            ? VisibilityLevel::normalize((string) ($unit['visibility_level'] ?? ''))
            : VisibilityLevel::fromOrbatMaskMode((string) ($unit['orbat_mask_mode'] ?? ''));

        $data = [];
        if ($this->unitRepository->hasTableColumn('units', 'visibility_level')) {
            $data['visibility_level'] = $visibility;
        }
        if ($this->unitRepository->hasTableColumn('units', 'orbat_mask_mode')) {
            $data['orbat_mask_mode'] = $mode;
        }
        if ($request->input('visibility_propagate') !== null && $this->unitRepository->hasTableColumn('units', 'visibility_propagate')) {
            $data['visibility_propagate'] = $request->input('visibility_propagate') ? 1 : 0;
        }
        if ($request->input('strength_display_mode') !== null && $this->unitRepository->hasTableColumn('units', 'strength_display_mode')) {
            $data['strength_display_mode'] = (string) $request->input('strength_display_mode');
        }
        $this->unitRepository->update($unitId, $tenantId, $data);

        $actorId = (int) Session::get('user_id');
        $reason = trim((string) $request->input('reason', ''));
        $this->visibilityHistoryRepository->record(
            $tenantId,
            'unit',
            $unitId,
            'visibility_level',
            $oldVis,
            $visibility,
            $actorId,
            $reason !== '' ? $reason : null
        );

        return $this->rosterSuccess($tenantId, $actorId);
    }

    private function structureSetStatus(Request $request, int $tenantId): Response
    {
        if (!$this->unitRepository->hasTableColumn('units', 'admin_status')) {
            return Response::json([
                'success' => false,
                'code' => 'orbat_schema',
                'message' => 'Les statuts administratifs ne sont pas encore disponibles sur cet environnement.',
            ], 503);
        }

        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId < 1) {
            return Response::json(['success' => false, 'message' => 'Unité non valide.'], 400);
        }
        $unit = $this->unitRepository->findById($unitId, $tenantId);
        if (!$unit) {
            return Response::json(['success' => false, 'message' => 'Unité introuvable.'], 404);
        }

        $newStatus = UnitAdminStatus::normalize((string) $request->input('admin_status', ''));
        $oldStatus = UnitAdminStatus::normalize((string) ($unit['admin_status'] ?? UnitAdminStatus::ACTIVE));
        $note = trim((string) $request->input('admin_status_note', $request->input('reason', '')));
        $comment = trim((string) $request->input('comment', ''));
        $effectiveAt = trim((string) $request->input('effective_at', ''));

        $data = ['admin_status' => $newStatus];
        if ($this->unitRepository->hasTableColumn('units', 'admin_status_note')) {
            $data['admin_status_note'] = $note !== '' ? $note : null;
        }
        $this->unitRepository->update($unitId, $tenantId, $data);

        $actorId = (int) Session::get('user_id');
        $this->visibilityHistoryRepository->recordUnitStatus(
            $tenantId,
            $unitId,
            $oldStatus,
            $newStatus,
            $actorId,
            $note !== '' ? $note : null,
            $comment !== '' ? $comment : null,
            $effectiveAt !== '' ? $effectiveAt : null
        );

        // Signalement d'incohérence parent/enfant (sans correction auto)
        $warnings = [];
        $parentId = (int) ($unit['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = $this->unitRepository->findById($parentId, $tenantId);
            if ($parent) {
                $updated = array_merge($unit, $data);
                $warnings = OrgVisibilityService::detectStatusInconsistencies($updated, $parent);
            }
        }

        $resp = $this->rosterSuccess($tenantId, $actorId);
        // rosterSuccess returns Response — we need to enrich. Rebuild lightly:
        $gate = Gate::getInstance();
        $caps = OrgVisibilityCapabilities::fromGate($gate);
        $canBypass = $caps->bypassAll || $gate->allows('organization.orbat.manage');

        return Response::json([
            'success' => true,
            'roster' => OrbatRosterPayload::buildForTenant($this->unitRepository, $tenantId, $actorId, $canBypass, $caps),
            'warnings' => $warnings,
        ]);
    }

    private function structureArchive(Request $request, int $tenantId): Response
    {
        if (!$this->unitRepository->hasTableColumn('units', 'admin_status')) {
            return Response::json([
                'success' => false,
                'code' => 'orbat_schema',
                'message' => 'L’archivage administratif n’est pas encore disponible sur cet environnement.',
            ], 503);
        }

        $unitId = (int) $request->input('unit_id', 0);
        if ($unitId < 1) {
            return Response::json(['success' => false, 'message' => 'Unité non valide.'], 400);
        }
        $unit = $this->unitRepository->findById($unitId, $tenantId);
        if (!$unit) {
            return Response::json(['success' => false, 'message' => 'Unité introuvable.'], 404);
        }

        $oldStatus = UnitAdminStatus::normalize((string) ($unit['admin_status'] ?? UnitAdminStatus::ACTIVE));
        $newStatus = UnitAdminStatus::ARCHIVED;
        $reason = trim((string) $request->input('reason', 'Archivage depuis l’ORBAT'));

        $data = ['admin_status' => $newStatus];
        if ($this->unitRepository->hasTableColumn('units', 'admin_status_note')) {
            $data['admin_status_note'] = $reason !== '' ? $reason : null;
        }
        $this->unitRepository->update($unitId, $tenantId, $data);

        $actorId = (int) Session::get('user_id');
        $this->visibilityHistoryRepository->recordUnitStatus(
            $tenantId,
            $unitId,
            $oldStatus,
            $newStatus,
            $actorId,
            $reason !== '' ? $reason : null
        );

        return $this->rosterSuccess($tenantId, $actorId);
    }

    private function resolveBilletService(): OrbatBilletService
    {
        if ($this->billetService instanceof OrbatBilletService) {
            return $this->billetService;
        }
        $repo = $this->billetRepository ?? new OrbatBilletRepository();
        $this->billetService = new OrbatBilletService($repo, $this->unitRepository, $this->visibilityHistoryRepository);

        return $this->billetService;
    }

    private function billetCreate(Request $request, int $tenantId): Response
    {
        $actorId = (int) Session::get('user_id');
        $result = $this->resolveBilletService()->createBillet($tenantId, [
            'unit_id' => (int) $request->input('unit_id', 0),
            'code' => (string) $request->input('code', ''),
            'title' => (string) $request->input('title', ''),
            'authorized_slots' => (int) $request->input('authorized_slots', 1),
            'org_callsign' => (string) $request->input('org_callsign', ''),
            'function_label' => (string) $request->input('function_label', ''),
            'is_critical' => (bool) $request->input('is_critical', false),
            'is_key_post' => (bool) $request->input('is_key_post', false),
            'key_post_kind' => (string) $request->input('key_post_kind', ''),
            'required_pack_id' => $request->input('required_pack_id'),
            'job_role_id' => $request->input('job_role_id'),
            'notes' => (string) $request->input('notes', ''),
            'sort_order' => (int) $request->input('sort_order', 0),
        ], $actorId);
        if (!$result['ok']) {
            return Response::json(['success' => false, 'message' => $result['message'] ?? 'Erreur'], 400);
        }

        return $this->rosterSuccess($tenantId, $actorId);
    }

    private function billetUpdate(Request $request, int $tenantId): Response
    {
        $actorId = (int) Session::get('user_id');
        $billetId = (int) $request->input('billet_id', 0);
        $data = [];
        foreach ([
            'code', 'title', 'org_callsign', 'function_label', 'status', 'key_post_kind', 'notes',
            'effective_from', 'effective_to',
        ] as $k) {
            if ($request->input($k) !== null) {
                $data[$k] = $request->input($k);
            }
        }
        foreach (['authorized_slots', 'sort_order', 'job_role_id', 'required_pack_id', 'min_grade_id', 'unit_id'] as $k) {
            if ($request->input($k) !== null) {
                $data[$k] = (int) $request->input($k);
            }
        }
        foreach (['is_critical', 'is_key_post', 'is_active'] as $k) {
            if ($request->input($k) !== null) {
                $data[$k] = (bool) $request->input($k);
            }
        }
        $result = $this->resolveBilletService()->updateBillet($tenantId, $billetId, $data, $actorId);
        if (!$result['ok']) {
            return Response::json(['success' => false, 'message' => $result['message'] ?? 'Erreur'], 400);
        }

        return $this->rosterSuccess($tenantId, $actorId);
    }

    private function billetDelete(Request $request, int $tenantId): Response
    {
        $actorId = (int) Session::get('user_id');
        $billetId = (int) $request->input('billet_id', 0);
        $repo = $this->billetRepository ?? new OrbatBilletRepository();
        if (!$repo->softDelete($tenantId, $billetId)) {
            return Response::json(['success' => false, 'message' => 'Suppression du poste impossible.'], 400);
        }

        return $this->rosterSuccess($tenantId, $actorId);
    }

    private function billetOccupy(Request $request, int $tenantId): Response
    {
        $actorId = (int) Session::get('user_id');
        $result = $this->resolveBilletService()->occupy($tenantId, (int) $request->input('billet_id', 0), [
            'user_id' => (int) $request->input('user_id', 0),
            'occupancy_type' => (string) $request->input('occupancy_type', 'primary'),
            'holder_role' => (string) $request->input('holder_role', 'PRIMARY'),
            'starts_at' => (string) $request->input('starts_at', date('Y-m-d')),
            'ends_at' => (string) $request->input('ends_at', ''),
            'effective_at' => (string) $request->input('effective_at', ''),
            'movement_reason' => (string) $request->input('movement_reason', ''),
            'notes' => (string) $request->input('notes', ''),
            'keeps_organic_billet' => (bool) $request->input('keeps_organic_billet', false),
            'organic_billet_id' => $request->input('organic_billet_id'),
        ], $actorId);
        if (!$result['ok']) {
            return Response::json(['success' => false, 'message' => $result['message'] ?? 'Erreur'], 400);
        }
        $gate = Gate::getInstance();
        $caps = OrgVisibilityCapabilities::fromGate($gate);

        return Response::json([
            'success' => true,
            'warnings' => $result['warnings'] ?? [],
            'roster' => OrbatRosterPayload::buildForTenant(
                $this->unitRepository,
                $tenantId,
                $actorId,
                true,
                $caps
            ),
        ]);
    }

    private function billetVacate(Request $request, int $tenantId): Response
    {
        $actorId = (int) Session::get('user_id');
        $result = $this->resolveBilletService()->vacate(
            $tenantId,
            (int) $request->input('holder_id', 0),
            (int) $request->input('user_id', 0) ?: null,
            (int) $request->input('billet_id', 0) ?: null,
            $actorId,
            (string) $request->input('movement_reason', '') ?: null
        );
        if (!$result['ok']) {
            return Response::json(['success' => false, 'message' => $result['message'] ?? 'Erreur'], 400);
        }

        return $this->rosterSuccess($tenantId, $actorId);
    }

    private function billetRestore(Request $request, int $tenantId): Response
    {
        $actorId = (int) Session::get('user_id');
        $result = $this->resolveBilletService()->restoreBillet(
            $tenantId,
            (int) $request->input('billet_id', 0),
            $actorId
        );
        if (!$result['ok']) {
            return Response::json(['success' => false, 'message' => $result['message'] ?? 'Erreur'], 400);
        }

        return $this->rosterSuccess($tenantId, $actorId);
    }

    private function structureSheet(Request $request, int $tenantId): Response
    {
        $unitId = (int) $request->input('unit_id', 0);
        $asOf = trim((string) $request->input('as_of', ''));
        $sheet = $this->resolveBilletService()->structureSheet(
            $tenantId,
            $unitId,
            $asOf !== '' ? $asOf : null
        );
        if (empty($sheet['ok'])) {
            return Response::json(['success' => false, 'message' => $sheet['message'] ?? 'Structure introuvable'], 404);
        }

        return Response::json(['success' => true, 'sheet' => $sheet]);
    }

    private function dataQuality(Request $request, int $tenantId): Response
    {
        return Response::json([
            'success' => true,
            'quality' => $this->resolveBilletService()->dataQualitySummary($tenantId),
        ]);
    }

    private function orbatSnapshot(Request $request, int $tenantId): Response
    {
        $actorId = (int) Session::get('user_id');
        $result = $this->resolveBilletService()->snapshotOrbat(
            $tenantId,
            (string) $request->input('label', 'Snapshot ORBAT'),
            (string) $request->input('kind', 'manual'),
            trim((string) $request->input('effective_at', '')) ?: null,
            $actorId,
            trim((string) $request->input('notes', '')) ?: null
        );
        if (!$result['ok']) {
            return Response::json(['success' => false, 'message' => $result['message'] ?? 'Erreur'], 400);
        }

        return Response::json(['success' => true, 'id' => $result['id'] ?? 0]);
    }

    private function rosterSuccess(int $tenantId, int $userId): Response
    {
        $gate = Gate::getInstance();
        $caps = OrgVisibilityCapabilities::fromGate($gate);
        $canBypass = $caps->bypassAll || $caps->viewHiddenUnits
            || $gate->allows('organization.orbat.manage');

        return Response::json([
            'success' => true,
            'roster' => OrbatRosterPayload::buildForTenant($this->unitRepository, $tenantId, $userId, $canBypass, $caps),
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
            // TEXT : plafond de sécurité large (plus de limite 8000 trop restrictive)
            $data['public_blurb'] = $blurb === '' ? null : mb_substr($blurb, 0, 100000);
        }

        if ($request->input('public_founded_on') !== null && $this->unitRepository->hasTableColumn('units', 'public_founded_on')) {
            $data['public_founded_on'] = trim((string) $request->input('public_founded_on', ''));
        }
        if ($request->input('public_custom_date') !== null && $this->unitRepository->hasTableColumn('units', 'public_custom_date')) {
            $data['public_custom_date'] = trim((string) $request->input('public_custom_date', ''));
        }
        if ($request->input('public_custom_date_label') !== null && $this->unitRepository->hasTableColumn('units', 'public_custom_date_label')) {
            $label = trim((string) $request->input('public_custom_date_label', ''));
            $data['public_custom_date_label'] = $label === '' ? null : mb_substr($label, 0, 80);
        }

        if ($request->input('show_on_public_page') !== null) {
            $data['show_on_public_page'] = $request->input('show_on_public_page') ? 1 : 0;
            // Fiche publique : garantir une adresse courte si l’unité n’en a pas encore.
            if ((int) $data['show_on_public_page'] === 1) {
                $currentSlug = trim((string) ($unit['slug'] ?? ''));
                if ($currentSlug === '') {
                    $data['slug'] = $this->unitRepository->uniqueSlugForTenant(
                        $tenantId,
                        (string) ($data['name'] ?? $unit['name'] ?? 'unite')
                    );
                }
            }
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

        // Type structurel (groupe, équipe, squad…) — modifiable après création
        if ($request->input('struct_type') !== null) {
            $structType = strtolower(trim((string) $request->input('struct_type', '')));
            $allowedTypes = array_keys(config('units.types', []));
            if ($allowedTypes !== [] && !in_array($structType, $allowedTypes, true)) {
                return Response::json(['success' => false, 'message' => 'Type d’unité non reconnu.'], 400);
            }
            $data['type'] = $structType;
        }

        if ($request->input('admin_status') !== null && $this->unitRepository->hasTableColumn('units', 'admin_status')) {
            $data['admin_status'] = UnitAdminStatus::normalize((string) $request->input('admin_status'));
        }
        if ($request->input('admin_status_note') !== null && $this->unitRepository->hasTableColumn('units', 'admin_status_note')) {
            $note = trim((string) $request->input('admin_status_note', ''));
            $data['admin_status_note'] = $note === '' ? null : mb_substr($note, 0, 500);
        }
        if ($request->input('visibility_level') !== null) {
            $visibility = VisibilityLevel::normalize((string) $request->input('visibility_level'));
            if ($this->unitRepository->hasTableColumn('units', 'visibility_level')) {
                $data['visibility_level'] = $visibility;
            }
            if ($this->unitRepository->hasTableColumn('units', 'orbat_mask_mode')) {
                $data['orbat_mask_mode'] = VisibilityLevel::toOrbatMaskMode($visibility);
            }
        }
        if ($request->input('strength_display_mode') !== null && $this->unitRepository->hasTableColumn('units', 'strength_display_mode')) {
            $data['strength_display_mode'] = (string) $request->input('strength_display_mode');
        }

        if ($request->input('orbat_details') !== null && $this->unitRepository->hasTableColumn('units', 'orbat_details')) {
            $det = trim((string) $request->input('orbat_details', ''));
            // TEXT : plus de troncature agressive — plafond de sécurité très large
            $data['orbat_details'] = $det === '' ? null : mb_substr($det, 0, 100000);
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
        $this->recordUnitRenameHistory($tenantId, $unit, $data);

        // Historiser statut / visibilité si changés via updateUnit
        $actorId = (int) Session::get('user_id');
        if (isset($data['admin_status'])) {
            $oldStatus = UnitAdminStatus::normalize((string) ($unit['admin_status'] ?? UnitAdminStatus::ACTIVE));
            $this->visibilityHistoryRepository->recordUnitStatus(
                $tenantId,
                $unitId,
                $oldStatus,
                UnitAdminStatus::normalize((string) $data['admin_status']),
                $actorId
            );
        }
        if (isset($data['visibility_level'])) {
            $oldVis = array_key_exists('visibility_level', $unit)
                ? VisibilityLevel::normalize((string) ($unit['visibility_level'] ?? ''))
                : VisibilityLevel::fromOrbatMaskMode((string) ($unit['orbat_mask_mode'] ?? ''));
            $this->visibilityHistoryRepository->record(
                $tenantId,
                'unit',
                $unitId,
                'visibility_level',
                $oldVis,
                VisibilityLevel::normalize((string) $data['visibility_level']),
                $actorId
            );
        }

        $canBypass = $gate->allows('admin.organization') || $gate->allows('admin.access')
            || $gate->allows('organization.orbat.manage');
        $caps = OrgVisibilityCapabilities::fromGate($gate);

        return Response::json([
            'success' => true,
            'roster' => OrbatRosterPayload::buildForTenant($this->unitRepository, $tenantId, $userId, $canBypass, $caps),
        ]);
    }

    /**
     * @param array<string, mixed> $unit
     */
    private function recordUnitCreationHistory(int $tenantId, array $unit, ?int $parentId): void
    {
        if (!$this->personnelOrgHistoryRepository->schemaReady()) {
            return;
        }
        $unitName = trim((string) ($unit['name'] ?? ''));
        if ($unitName === '') {
            return;
        }
        $actorId = (int) Session::get('user_id');
        $actorLabel = $this->actorDisplayName($tenantId, $actorId);

        $parentLabel = 'aucun rattachement parent';
        if ($parentId !== null && $parentId > 0) {
            $parent = $this->unitRepository->findById($parentId, $tenantId);
            $parentName = trim((string) ($parent['name'] ?? ''));
            $parentLabel = $parentName !== '' ? $parentName : ('unité #' . $parentId);
        }

        $summary = 'Structure ORBAT : unité ajoutée « ' . $unitName . ' »'
            . ' (rattachement : ' . $parentLabel . ')'
            . ' — par ' . $actorLabel;
        foreach ($this->collectConcernedUserIds($tenantId, $unit) as $uid) {
            $this->personnelOrgHistoryRepository->append($tenantId, $uid, $actorId > 0 ? $actorId : null, $summary);
        }
    }

    /**
     * @param array<string, mixed> $unit
     */
    private function recordUnitDeletionHistory(int $tenantId, array $unit): void
    {
        if (!$this->personnelOrgHistoryRepository->schemaReady()) {
            return;
        }
        $unitName = trim((string) ($unit['name'] ?? ''));
        if ($unitName === '') {
            $unitName = 'unité #' . (int) ($unit['id'] ?? 0);
        }
        $actorId = (int) Session::get('user_id');
        $actorLabel = $this->actorDisplayName($tenantId, $actorId);
        $summary = 'Structure ORBAT : unité supprimée « ' . $unitName . ' » — par ' . $actorLabel;
        foreach ($this->collectConcernedUserIds($tenantId, $unit) as $uid) {
            $this->personnelOrgHistoryRepository->append($tenantId, $uid, $actorId > 0 ? $actorId : null, $summary);
        }
    }

    /**
     * @param array<string, mixed> $beforeUnit
     * @param array<string, mixed> $newData
     */
    private function recordUnitRenameHistory(int $tenantId, array $beforeUnit, array $newData): void
    {
        $newName = trim((string) ($newData['name'] ?? ''));
        $oldName = trim((string) ($beforeUnit['name'] ?? ''));
        if ($newName === '' || $oldName === '' || $newName === $oldName) {
            return;
        }
        if (!$this->personnelOrgHistoryRepository->schemaReady()) {
            return;
        }
        $actorId = (int) Session::get('user_id');
        $actorLabel = $this->actorDisplayName($tenantId, $actorId);
        $summary = 'Structure ORBAT : renommage unité « ' . $oldName . ' » → « ' . $newName . ' » — par ' . $actorLabel;
        foreach ($this->collectConcernedUserIds($tenantId, $beforeUnit) as $uid) {
            $this->personnelOrgHistoryRepository->append($tenantId, $uid, $actorId > 0 ? $actorId : null, $summary);
        }
    }

    /**
     * @param array<string, mixed> $unit
     * @return list<int>
     */
    private function collectConcernedUserIds(int $tenantId, array $unit): array
    {
        $unitId = (int) ($unit['id'] ?? 0);
        $ids = [];
        if ($unitId > 0) {
            foreach ($this->unitRepository->listActiveUserIdsForUnits($tenantId, [$unitId]) as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $ids[$uid] = true;
                }
            }
        }
        $commanderId = (int) ($unit['commander_user_id'] ?? 0);
        if ($commanderId > 0) {
            $ids[$commanderId] = true;
        }

        return array_map('intval', array_keys($ids));
    }

    private function actorDisplayName(int $tenantId, int $actorId): string
    {
        if ($actorId < 1) {
            return 'Encadrement';
        }
        $actor = $this->userRepository->findById($actorId, $tenantId);
        if (!$actor) {
            return 'Encadrement';
        }
        $label = trim((string) ($actor['display_name'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $callsign = trim((string) ($actor['callsign'] ?? ''));
        if ($callsign !== '') {
            return $callsign;
        }
        $email = trim((string) ($actor['email'] ?? ''));

        return $email !== '' ? $email : 'Encadrement';
    }
}
