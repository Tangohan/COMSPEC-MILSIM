<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\MilitaryUnitRepository;
use App\Services\Community\MilitaryReferentialService;

/**
 * Administration du référentiel militaire global (SOF).
 */
final class SystemMilitaryReferentialController
{
    public function __construct(
        private MilitaryUnitRepository $repo,
        private MilitaryReferentialService $referential
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->repo->tablesReady()) {
            Session::flash('error', 'Le référentiel militaire n’est pas encore installé. Exécutez les migrations.');

            return Response::redirect(url('admin'));
        }

        $q = trim((string) $request->query('q', ''));
        $country = strtoupper(trim((string) $request->query('country', '')));
        $units = $q !== ''
            ? $this->repo->search($q, $country !== '' ? $country : null, 200)
            : ($country !== ''
                ? $this->repo->listByCountryIso2($country, false)
                : $this->repo->listAll(false));

        return Response::view('layout.main', [
            'content' => 'admin.system.military_referential_index',
            'title' => 'Référentiel militaire',
            'militaryUnits' => $units,
            'militaryCountries' => $this->repo->listCountries(false),
            'searchQuery' => $q,
            'filterCountry' => $country,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->repo->tablesReady()) {
            return Response::redirect(url('admin'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.military_referential_form',
            'title' => 'Nouvelle entité militaire',
            'militaryUnit' => null,
            'militaryCountries' => $this->repo->listCountries(true),
            'militaryServices' => $this->repo->listServices(null, true),
            'militaryEntityTypes' => $this->repo->listEntityTypes(),
            'militaryParents' => $this->repo->listAll(true),
            'militaryFunctions' => $this->repo->listFunctions(),
            'militarySpecialties' => $this->repo->listSpecialties(),
            'militaryDomains' => $this->repo->listDomains(),
            'militaryClassifications' => $this->repo->listClassifications(),
            'militarySources' => $this->repo->listSources(),
            'unitAliases' => [],
            'unitFunctionIds' => [],
            'unitSpecialtyIds' => [],
            'unitDomainIds' => [],
            'unitClassificationIds' => [],
            'unitSources' => [],
            'hierarchyLabels' => [],
            'formAction' => url('admin/system/military-referential'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/military-referential/create'));
        }
        $payload = $this->normalizeUnitPayload($request);
        if ($payload === null) {
            return Response::redirect(url('admin/system/military-referential/create'));
        }
        if ($this->repo->findByCode($payload['code']) !== null) {
            Session::flash('error', 'Une entité porte déjà ce code.');

            return Response::redirect(url('admin/system/military-referential/create'));
        }
        $id = $this->repo->create($payload);
        $this->syncRelations($id, $request);
        Session::flash('success', 'Entité créée.');

        return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $unit = $this->repo->findById($id);
        if ($unit === null) {
            Session::flash('error', 'Entité introuvable.');

            return Response::redirect(url('admin/system/military-referential'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.system.military_referential_form',
            'title' => 'Modifier l’entité militaire',
            'militaryUnit' => $unit,
            'militaryCountries' => $this->repo->listCountries(false),
            'militaryServices' => $this->repo->listServices(null, false),
            'militaryEntityTypes' => $this->repo->listEntityTypes(),
            'militaryParents' => array_values(array_filter(
                $this->repo->listAll(false),
                static fn (array $u): bool => (int) $u['id'] !== $id
            )),
            'militaryFunctions' => $this->repo->listFunctions(),
            'militarySpecialties' => $this->repo->listSpecialties(),
            'militaryDomains' => $this->repo->listDomains(),
            'militaryClassifications' => $this->repo->listClassifications(),
            'militarySources' => $this->repo->listSources(),
            'unitAliases' => $this->repo->listAliases($id),
            'unitFunctionIds' => $this->repo->getUnitFunctionIds($id),
            'unitSpecialtyIds' => $this->repo->getUnitSpecialtyIds($id),
            'unitDomainIds' => $this->repo->getUnitDomainIds($id),
            'unitClassificationIds' => $this->repo->getUnitClassificationIds($id),
            'unitSources' => $this->repo->listUnitSources($id),
            'hierarchyLabels' => $this->referential->hierarchyBreadcrumbLabels($id),
            'formAction' => url('admin/system/military-referential/' . $id . '/update'),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
        }
        if ($this->repo->findById($id) === null) {
            Session::flash('error', 'Entité introuvable.');

            return Response::redirect(url('admin/system/military-referential'));
        }
        $payload = $this->normalizeUnitPayload($request, $id);
        if ($payload === null) {
            return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
        }
        $other = $this->repo->findByCode($payload['code']);
        if ($other !== null && (int) $other['id'] !== $id) {
            Session::flash('error', 'Une autre entité porte déjà ce code.');

            return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
        }
        $this->repo->update($id, $payload);
        $this->syncRelations($id, $request);
        Session::flash('success', 'Entité mise à jour.');

        return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
    }

    public function addAlias(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
        }
        $alias = trim((string) $request->input('alias', ''));
        $type = strtoupper(trim((string) $request->input('alias_type', 'COMMON_NAME')));
        if ($alias === '') {
            Session::flash('error', 'Indiquez l’alias.');

            return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
        }
        $allowed = ['SHORT_NAME', 'ACRONYM', 'COMMON_NAME', 'FORMER_NAME', 'ENGLISH_NAME', 'FRENCH_NAME', 'NICKNAME', 'CODE_NAME', 'ALTERNATIVE_SPELLING'];
        if (!in_array($type, $allowed, true)) {
            $type = 'COMMON_NAME';
        }
        $this->repo->addAlias($id, $alias, $type, trim((string) $request->input('language', '')) ?: null);
        Session::flash('success', 'Alias ajouté.');

        return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
    }

    public function deleteAlias(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $aliasId = (int) ($params['aliasId'] ?? 0);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
        }
        $this->repo->deleteAlias($aliasId);
        Session::flash('success', 'Alias retiré.');

        return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
    }

    public function addSource(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
        }
        $sourceId = (int) $request->input('source_id', 0);
        $infoType = strtoupper(trim((string) $request->input('information_type', 'IDENTITY')));
        $allowed = ['IDENTITY', 'HIERARCHY', 'MISSION', 'FUNCTION', 'SPECIALTY', 'HISTORY', 'STATUS'];
        if (!in_array($infoType, $allowed, true)) {
            $infoType = 'IDENTITY';
        }
        if ($sourceId <= 0) {
            $name = trim((string) $request->input('new_source_name', ''));
            if ($name === '') {
                Session::flash('error', 'Choisissez ou créez une source.');

                return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
            }
            $sourceId = $this->repo->createSource([
                'name' => $name,
                'publisher' => trim((string) $request->input('new_source_publisher', '')) ?: null,
                'url' => trim((string) $request->input('new_source_url', '')) ?: null,
                'source_type' => 'institutional',
                'checked_at' => date('Y-m-d'),
            ]);
        }
        $this->repo->addUnitSource($id, $sourceId, $infoType, trim((string) $request->input('notes', '')) ?: null);
        Session::flash('success', 'Source associée.');

        return Response::redirect(url('admin/system/military-referential/' . $id . '/edit'));
    }

    /** @return array<string, mixed>|null */
    private function normalizeUnitPayload(Request $request, ?int $existingId = null): ?array
    {
        $official = trim((string) $request->input('official_name', ''));
        $display = trim((string) $request->input('display_name', ''));
        $code = strtolower(trim((string) $request->input('code', '')));
        $countryId = (int) $request->input('country_id', 0);
        $entityTypeId = (int) $request->input('entity_type_id', 0);
        if ($official === '' || $display === '' || $code === '' || $countryId <= 0 || $entityTypeId <= 0) {
            Session::flash('error', 'Renseignez au minimum le titre officiel, le titre affiché, le code, le pays et le type.');

            return null;
        }
        if (!preg_match('/^[a-z0-9][a-z0-9\-_]{1,62}$/', $code)) {
            Session::flash('error', 'Le code doit contenir des lettres minuscules, chiffres, tirets ou tirets bas.');

            return null;
        }
        $parentId = (int) $request->input('parent_id', 0);
        if ($parentId > 0 && $existingId !== null && $parentId === $existingId) {
            Session::flash('error', 'Une entité ne peut pas être son propre rattachement.');

            return null;
        }
        $serviceId = (int) $request->input('service_id', 0);
        $short = trim((string) $request->input('short_name', ''));
        $slug = trim((string) $request->input('slug', ''));
        if ($slug === '') {
            $slug = $code;
        }
        $status = strtolower(trim((string) $request->input('status', 'active')));
        if (!in_array($status, ['active', 'inactive', 'dissolved'], true)) {
            $status = 'active';
        }
        $active = (string) $request->input('active', '1') === '1';
        $dissolved = trim((string) $request->input('dissolved_at', ''));
        if ($status === 'dissolved' && $dissolved === '') {
            $dissolved = date('Y-m-d');
        }

        $hierarchyLevel = (int) $request->input('hierarchy_level', 0);
        if ($parentId > 0) {
            $parent = $this->repo->findById($parentId);
            if ($parent) {
                $hierarchyLevel = (int) ($parent['hierarchy_level'] ?? 0) + 1;
            }
        }

        $payload = [
            'parent_id' => $parentId > 0 ? $parentId : null,
            'country_id' => $countryId,
            'service_id' => $serviceId > 0 ? $serviceId : null,
            'entity_type_id' => $entityTypeId,
            'code' => $code,
            'slug' => $slug,
            'official_name' => $official,
            'short_name' => $short !== '' ? $short : null,
            'display_name' => $display,
            'international_name' => trim((string) $request->input('international_name', '')) ?: null,
            'description_short' => trim((string) $request->input('description_short', '')) ?: null,
            'description_long' => trim((string) $request->input('description_long', '')) ?: null,
            'mission_summary' => trim((string) $request->input('mission_summary', '')) ?: null,
            'functions_summary' => trim((string) $request->input('functions_summary', '')) ?: null,
            'status' => $status,
            'active' => $active ? 1 : 0,
            'hierarchy_level' => $hierarchyLevel,
            'sort_order' => (int) $request->input('sort_order', 0),
            'founded_at' => trim((string) $request->input('founded_at', '')) ?: null,
            'dissolved_at' => $dissolved !== '' ? $dissolved : null,
            'official_website' => trim((string) $request->input('official_website', '')) ?: null,
        ];
        if ((string) $request->input('mark_verified', '0') === '1') {
            $payload['verified_at'] = date('Y-m-d H:i:s');
        }

        return $payload;
    }

    private function syncRelations(int $unitId, Request $request): void
    {
        $toIds = static function (mixed $raw): array {
            if (!is_array($raw)) {
                return [];
            }

            return array_values(array_filter(array_map('intval', $raw), static fn (int $v): bool => $v > 0));
        };
        $this->repo->syncUnitFunctions($unitId, $toIds($request->input('function_ids', [])));
        $this->repo->syncUnitSpecialties($unitId, $toIds($request->input('specialty_ids', [])));
        $this->repo->syncUnitDomains($unitId, $toIds($request->input('domain_ids', [])));
        $this->repo->syncUnitClassifications($unitId, $toIds($request->input('classification_ids', [])));
    }
}
