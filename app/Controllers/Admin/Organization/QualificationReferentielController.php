<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\QualificationIssuerRepository;
use App\Repositories\QualificationReferentielRepository;
use App\Services\Personnel\QualificationAwardRuleService;
use App\Services\Personnel\QualificationBadgeStorageService;
use App\Services\Personnel\QualificationCatalogImporter;
use App\Services\Personnel\QualificationReferenceWriter;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Services\Personnel\QualificationCriteriaEngine;
use App\Services\Personnel\QualificationCertificatePdfService;
use App\Services\Personnel\QualificationStatusTransitionService;
use App\Services\Personnel\QualificationTemporalStatusService;
use App\Support\QualificationAdminStatus;
use App\Support\QualificationExamples;
use App\Support\VisibilityLevel;
use Throwable;

final class QualificationReferentielController
{
    private const ISSUERS = 'back-office/referentiels/qualifications/emetteurs';

    private ?QualificationIssuerRepository $issuerRepository = null;

    public function __construct(
        private QualificationDefinitionRepository $definitions,
        private QualificationReferentielRepository $referentiel,
        private QualificationAwardRepository $awards,
        private QualificationStatusTransitionService $transitions,
        private QualificationTemporalStatusService $temporal,
        private QualificationBadgeStorageService $badges,
        private QualificationCertificatePdfService $certificates,
        private ?QualificationAwardRuleService $rules = null,
        private ?GradeRepository $grades = null,
        private ?PersonnelJobRoleRepository $jobRoles = null,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        $definitions = $this->definitions->listForTenant($tenantId, true);
        $issuerFilter = null;
        $issuerFilterId = (int) $request->query('emetteur', 0);
        if ($issuerFilterId > 0) {
            $issuerFilter = $this->issuerRepo()->find($tenantId, $issuerFilterId);
            if ($issuerFilter !== null) {
                $linked = $this->issuerRepo()->definitionsByIssuer($tenantId)[$issuerFilterId] ?? [];
                $definitions = array_values(array_filter($definitions, static fn (array $d): bool => isset($linked[(int) ($d['id'] ?? 0)])));
            }
        }
        $badgeUrls = [];
        foreach ($definitions as $d) {
            if (trim((string) ($d['badge_media_path'] ?? '')) !== '') {
                $badgeUrls[(int) ($d['id'] ?? 0)] = $this->badges->publicUrl((string) $d['badge_media_path']);
            }
        }

        $modesByDef = [];
        $settingsAll = [];
        $pilotDefs = [];
        try {
            $settingsAll = (new \App\Repositories\QualificationAwardRuleRepository())->listForTenant($tenantId);
            foreach ((new \App\Repositories\PilotQualificationRuleRepository())->listForTenant($tenantId) as $pr) {
                $pilotDefs[(int) $pr['definition_id']] = true;
            }
        } catch (Throwable) {
        }
        foreach ($definitions as $d) {
            $did = (int) ($d['id'] ?? 0);
            $modesByDef[$did] = QualificationCriteriaEngine::modes($settingsAll[$did] ?? null, isset($pilotDefs[$did]));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.index',
            'modesByDef' => $modesByDef,
            'issuerFilter' => $issuerFilter,
            'backOfficePageCss' => ['referentiel-qualifications.css'],
            'badgeUrls' => $badgeUrls,
            'title' => 'Référentiel des qualifications',
            'boPageKicker' => 'PERSONNEL · QUALIFICATIONS',
            'boPageTitle' => 'Qualifications',
            'boPageSubtitle' => 'Ce que les membres savent faire : définissez les qualifications et la façon de les obtenir (à la main, par une formation Academy ou automatiquement d’après les heures de jeu, de vol, les missions…), puis suivez qui les détient.',
            'definitions' => $definitions,
            'categories' => $this->referentiel->listCategories($tenantId),
            'types' => $this->referentiel->listTypes($tenantId),
            'temporal' => $this->temporal,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.form',
            'title' => 'Nouvelle qualification',
            'backOfficePageCss' => ['referentiel-qualifications.css'],
            'boSkipPageHead' => true,
            'qualificationExamples' => QualificationExamples::all(),
            'definition' => null,
            'categories' => $this->referentiel->listCategories($tenantId),
            'types' => $this->referentiel->listTypes($tenantId),
            'templates' => $this->referentiel->listCertificateTemplates($tenantId),
            'levels' => [],
            'prerequisites' => [],
            'customFields' => [],
            'permissionGrants' => [],
            'holders' => [],
        ] + $this->obtentionViewData($tenantId, 0));
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/referentiels/qualifications/create'));
        }
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($code === '' || $name === '') {
            Session::flash('error', 'Le code et le nom sont obligatoires.');

            return Response::redirect(url('back-office/referentiels/qualifications/create'));
        }
        if ($this->definitions->findByCode($tenantId, $code) !== null) {
            Session::flash('error', 'Ce code est déjà utilisé dans votre communauté.');

            return Response::redirect(url('back-office/referentiels/qualifications/create'));
        }
        try {
            $id = $this->definitions->create($tenantId, $this->definitionPayload($request), $this->actorId());
            Session::flash('success', 'Qualification créée.');
            $this->saveObtention($tenantId, $id, $request);

            return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit'));
        } catch (Throwable $e) {
            Session::flash('error', 'Création impossible : ' . $e->getMessage());

            return Response::redirect(url('back-office/referentiels/qualifications/create'));
        }
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        $definition = $this->definitions->find($tenantId, $id);
        if ($definition === null) {
            Session::flash('error', 'Qualification introuvable.');

            return Response::redirect(url('back-office/referentiels/qualifications'));
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.form',
            'title' => 'Modifier la qualification',
            'backOfficePageCss' => ['referentiel-qualifications.css'],
            'boSkipPageHead' => true,
            'qualificationExamples' => QualificationExamples::all(),
            'definition' => $definition,
            'categories' => $this->referentiel->listCategories($tenantId),
            'types' => $this->referentiel->listTypes($tenantId),
            'templates' => $this->referentiel->listCertificateTemplates($tenantId),
            'levels' => $this->referentiel->listLevels($tenantId, $id),
            'prerequisites' => $this->referentiel->listPrerequisites($tenantId, $id),
            'customFields' => $this->referentiel->listCustomFields($tenantId, $id),
            'permissionGrants' => $this->referentiel->listPermissionGrants($tenantId, $id),
            'holders' => $this->awards->listHolders($tenantId, $id),
            'allDefinitions' => $this->definitions->listForTenant($tenantId),
            'badgeUrl' => $this->badges->publicUrl($definition['badge_media_path'] ?? null),
            'temporal' => $this->temporal,
        ] + $this->obtentionViewData($tenantId, $id));
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit'));
        }
        try {
            $this->definitions->update($tenantId, $id, $this->definitionPayload($request), $this->actorId());
            Session::flash('success', 'Qualification mise à jour.');
            $this->saveObtention($tenantId, $id, $request);
        } catch (Throwable $e) {
            Session::flash('error', 'Mise à jour impossible : ' . $e->getMessage());
        }

        return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit'));
    }

    public function archive(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/referentiels/qualifications'));
        }
        $this->definitions->archive($tenantId, $id, $this->actorId());
        Session::flash('success', 'Qualification archivée. Les attributions existantes sont conservées.');

        return Response::redirect(url('back-office/referentiels/qualifications'));
    }

    public function storeCategory(Request $request, array $params = []): Response
    {
        return $this->withTenantPost($request, function (int $tenantId) use ($request) {
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                Session::flash('error', 'Le nom de catégorie est obligatoire.');

                return Response::redirect(url('back-office/referentiels/qualifications'));
            }
            $parent = (int) $request->input('parent_category_id', 0);
            $this->referentiel->createCategory(
                $tenantId,
                $name,
                $parent > 0 ? $parent : null,
                (int) $request->input('sort_order', 0)
            );
            Session::flash('success', 'Catégorie ajoutée.');

            return Response::redirect(url('back-office/referentiels/qualifications'));
        });
    }

    public function storeType(Request $request, array $params = []): Response
    {
        return $this->withTenantPost($request, function (int $tenantId) use ($request) {
            $name = trim((string) $request->input('name', ''));
            $code = strtoupper(trim((string) $request->input('code', '')));
            if ($name === '' || $code === '') {
                Session::flash('error', 'Nom et code du type sont obligatoires.');

                return Response::redirect(url('back-office/referentiels/qualifications'));
            }
            $this->referentiel->createType($tenantId, $name, $code);
            Session::flash('success', 'Type ajouté.');

            return Response::redirect(url('back-office/referentiels/qualifications'));
        });
    }

    public function storeIssuer(Request $request, array $params = []): Response
    {
        return $this->withTenantPost($request, function (int $tenantId) use ($request) {
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                Session::flash('error', 'Le nom de l’émetteur est obligatoire.');

                return Response::redirect(url(self::ISSUERS) . '#nouvel-emetteur');
            }
            try {
                $id = $this->issuerRepo()->create($tenantId, $this->issuerPayload($request), $this->actorId());
                $this->saveIssuerExtras($tenantId, $id, $request);
                Session::flash('success', 'Émetteur « ' . $name . ' » ajouté.');

                return Response::redirect(url(self::ISSUERS . '/' . $id));
            } catch (Throwable $e) {
                Session::flash('error', 'Création impossible : ' . $e->getMessage());

                return Response::redirect(url(self::ISSUERS));
            }
        });
    }

    private function catalogImporter(): QualificationCatalogImporter
    {
        return new QualificationCatalogImporter(new QualificationReferenceWriter(\App\Core\Database::getPdo()));
    }

    /** Catalogue OTAN : qualifications de référence à importer dans le référentiel (choix, sans doublon). */
    public function catalog(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $lang = QualificationCatalog::lang(function_exists('locale') ? (string) locale() : 'fr');
        $pack = QualificationCatalog::pack('nato');
        $imported = [];
        try {
            $imported = $this->catalogImporter()->importedCodes($tenantId);
        } catch (Throwable) {
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.catalog',
            'title' => 'Catalogue OTAN des qualifications',
            'backOfficePageCss' => ['referentiel-qualifications.css'],
            'boSkipPageHead' => true,
            'catalogLang' => $lang,
            'catalogPack' => $pack,
            'catalogEntries' => $pack !== null ? QualificationCatalog::entries($lang, 'nato') : [],
            'catalogImported' => $imported,
        ]);
    }

    public function importCatalog(Request $request, array $params = []): Response
    {
        return $this->withTenantPost($request, function (int $tenantId) use ($request) {
            $back = url('back-office/referentiels/qualifications/catalogue-otan');
            $raw = $request->input('ids', []);
            $ids = is_array($raw) ? array_values(array_filter(array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $raw))) : [];
            if ((string) $request->input('all', '') === '1') {
                $ids = array_map(static fn (array $e): string => (string) ($e['id'] ?? ''), QualificationCatalog::rawEntries('nato'));
            }
            if ($ids === []) {
                Session::flash('error', 'Cochez au moins une qualification à importer.');

                return Response::redirect($back);
            }
            try {
                $lang = function_exists('locale') ? (string) locale() : 'fr';
                $result = $this->catalogImporter()->import($tenantId, 'nato', array_slice($ids, 0, 200), $lang, $this->actorId());
            } catch (Throwable $e) {
                Session::flash('error', 'Import impossible : ' . $e->getMessage());

                return Response::redirect($back);
            }
            $created = count($result['created']);
            $skipped = count($result['skipped']);
            if ($created === 0) {
                Session::flash('success', $skipped > 0 ? 'Ces qualifications sont déjà dans votre référentiel : rien à importer.' : 'Aucune qualification importée.');

                return Response::redirect($back);
            }
            Session::flash('success', $skipped > 0
                ? $created . ' qualification(s) importée(s) du catalogue OTAN, ' . $skipped . ' déjà présente(s) ignorée(s). Qualifications de jeu, sans valeur réelle : réglez ensuite leur mode d’obtention.'
                : $created . ' qualification(s) importée(s) du catalogue OTAN. Qualifications de jeu, sans valeur réelle : réglez ensuite leur mode d’obtention.');

            return Response::redirect(url('back-office/referentiels/qualifications') . ($result['issuer_id'] ? '?emetteur=' . (int) $result['issuer_id'] : ''));
        });
    }

    public function issuers(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $repo = $this->issuerRepo();
        $issuers = $repo->listForTenant($tenantId);
        $logoUrls = [];
        foreach ($issuers as $i) {
            if (trim((string) ($i['logo_media_path'] ?? '')) !== '') {
                $logoUrls[(int) $i['id']] = $this->badges->publicUrl((string) $i['logo_media_path']);
            }
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.issuers',
            'title' => 'Émetteurs des qualifications',
            'boSkipPageHead' => true,
            'backOfficePageCss' => ['effectifs-kit.css', 'qualification-issuers.css'],
            'issuers' => $issuers,
            'logoUrls' => $logoUrls,
            'stats' => $repo->awardStats($tenantId),
            'definitionsByIssuer' => $repo->definitionsByIssuer($tenantId),
            'authorizations' => $repo->authorizationsByIssuer($tenantId),
            'definitions' => $this->definitions->listForTenant($tenantId),
            'units' => $repo->unitOptions($tenantId),
            'enriched' => $repo->enrichedReady(),
            'linkReady' => $repo->linkReady(),
        ]);
    }

    public function showIssuer(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $repo = $this->issuerRepo();
        $id = (int) ($params['issuerId'] ?? 0);
        $issuer = $repo->find($tenantId, $id);
        if ($issuer === null) {
            Session::flash('error', 'Émetteur introuvable.');

            return Response::redirect(url(self::ISSUERS));
        }
        $members = [];
        try {
            foreach ((new \App\Repositories\UserRepository())->listForTenant($tenantId) as $u) {
                if (in_array(strtolower((string) ($u['status'] ?? 'active')), ['inactive', 'banned', 'deleted'], true)) {
                    continue;
                }
                $cs = trim((string) ($u['callsign'] ?? ''));
                $dn = trim((string) ($u['display_name'] ?? ''));
                $members[] = ['id' => (int) $u['id'], 'label' => $cs !== '' && $dn !== '' && $cs !== $dn ? $cs . ' · ' . $dn : ($cs !== '' ? $cs : ($dn !== '' ? $dn : '#' . (int) $u['id']))];
            }
            usort($members, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        } catch (Throwable) {
        }
        $jobs = [];
        try {
            $jobs = $this->jobRoles !== null && $this->jobRoles->tablesExist() ? $this->jobRoles->listRoleOptionsForSelect($tenantId) : [];
        } catch (Throwable) {
        }
        $stats = $repo->awardStats($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.issuer_show',
            'title' => (string) $issuer['name'],
            'boSkipPageHead' => true,
            'backOfficePageCss' => ['effectifs-kit.css', 'qualification-issuers.css'],
            'issuer' => $issuer,
            'issuers' => $repo->listForTenant($tenantId),
            'logoUrl' => trim((string) ($issuer['logo_media_path'] ?? '')) !== '' ? $this->badges->publicUrl((string) $issuer['logo_media_path']) : '',
            'stat' => $stats[$id] ?? ['total' => 0, 'recent' => 0, 'definitions' => 0, 'last_at' => ''],
            'linkedDefinitions' => $repo->definitionsByIssuer($tenantId)[$id] ?? [],
            'authorizations' => $repo->authorizationsByIssuer($tenantId)[$id] ?? [],
            'recentAwards' => $repo->recentAwards($tenantId, $id, 15),
            'definitions' => $this->definitions->listForTenant($tenantId),
            'units' => $repo->unitOptions($tenantId),
            'members' => $members,
            'jobs' => $jobs,
            'enriched' => $repo->enrichedReady(),
            'linkReady' => $repo->linkReady(),
            'authorizationsReady' => $repo->authorizationsReady(),
        ]);
    }

    public function updateIssuer(Request $request, array $params = []): Response
    {
        $id = (int) ($params['issuerId'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($request, $id) {
            $back = url(self::ISSUERS . '/' . $id);
            if ($this->issuerRepo()->find($tenantId, $id) === null) {
                Session::flash('error', 'Émetteur introuvable.');

                return Response::redirect(url(self::ISSUERS));
            }
            if (trim((string) $request->input('name', '')) === '') {
                Session::flash('error', 'Le nom de l’émetteur est obligatoire.');

                return Response::redirect($back . '#modifier');
            }
            try {
                $this->issuerRepo()->update($tenantId, $id, $this->issuerPayload($request));
                $this->saveIssuerExtras($tenantId, $id, $request);
                Session::flash('success', 'Émetteur enregistré.');
            } catch (Throwable $e) {
                Session::flash('error', 'Enregistrement impossible : ' . $e->getMessage());
            }

            return Response::redirect($back);
        });
    }

    public function archiveIssuer(Request $request, array $params = []): Response
    {
        return $this->toggleIssuerArchive($request, (int) ($params['issuerId'] ?? 0), true);
    }

    public function restoreIssuer(Request $request, array $params = []): Response
    {
        return $this->toggleIssuerArchive($request, (int) ($params['issuerId'] ?? 0), false);
    }

    public function storeIssuerAuthorization(Request $request, array $params = []): Response
    {
        $id = (int) ($params['issuerId'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($request, $id) {
            $back = url(self::ISSUERS . '/' . $id) . '#habilites';
            if ($this->issuerRepo()->find($tenantId, $id) === null) {
                return Response::redirect(url(self::ISSUERS));
            }
            $who = (string) $request->input('who', '');
            $userId = str_starts_with($who, 'u:') ? (int) substr($who, 2) : 0;
            $jobId = str_starts_with($who, 'j:') ? (int) substr($who, 2) : 0;
            if ($userId <= 0 && $jobId <= 0) {
                Session::flash('error', 'Choisissez un membre ou une fonction.');

                return Response::redirect($back);
            }
            try {
                $added = $this->issuerRepo()->addAuthorization($tenantId, $id, $userId, $jobId, (string) $request->input('role', 'instructor'), $this->actorId());
                Session::flash($added ? 'success' : 'error', $added ? 'Habilitation ajoutée.' : 'Cette habilitation existe déjà.');
            } catch (Throwable $e) {
                Session::flash('error', 'Habilitation impossible : ' . $e->getMessage());
            }

            return Response::redirect($back);
        });
    }

    public function deleteIssuerAuthorization(Request $request, array $params = []): Response
    {
        $id = (int) ($params['issuerId'] ?? 0);
        $authId = (int) ($params['authId'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($id, $authId) {
            $removed = $this->issuerRepo()->removeAuthorization($tenantId, $id, $authId);
            Session::flash($removed ? 'success' : 'error', $removed ? 'Habilitation retirée. Les brevets déjà délivrés ne changent pas.' : 'Habilitation introuvable.');

            return Response::redirect(url(self::ISSUERS . '/' . $id) . '#habilites');
        });
    }

    private function toggleIssuerArchive(Request $request, int $id, bool $archive): Response
    {
        return $this->withTenantPost($request, function (int $tenantId) use ($id, $archive) {
            if ($this->issuerRepo()->find($tenantId, $id) === null) {
                return Response::redirect(url(self::ISSUERS));
            }
            if (!$this->issuerRepo()->setArchived($tenantId, $id, $archive)) {
                Session::flash('error', 'L’archivage arrive avec la prochaine mise à jour du site.');
            } else {
                Session::flash('success', $archive
                    ? 'Émetteur archivé : il n’est plus proposé pour les nouvelles délivrances. Les brevets déjà délivrés restent à son nom.'
                    : 'Émetteur réactivé.');
            }

            return Response::redirect(url(self::ISSUERS . '/' . $id));
        });
    }

    /** @return array<string, mixed> */
    private function issuerPayload(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'short_name' => $request->input('short_name'),
            'issuer_kind' => (string) $request->input('issuer_kind', 'unit'),
            'parent_issuer_id' => $request->input('parent_issuer_id'),
            'description' => $request->input('description'),
            'unit_id' => $request->input('unit_id'),
            'signatory_name' => $request->input('signatory_name'),
            'signatory_title' => $request->input('signatory_title'),
        ];
    }

    /** Qualifications rattachées et logo envoyés avec le formulaire de l’émetteur. */
    private function saveIssuerExtras(int $tenantId, int $id, Request $request): void
    {
        if ($request->input('definitions_form') !== null) {
            $this->issuerRepo()->syncDefinitions($tenantId, $id, array_map('intval', (array) $request->input('definition_ids', [])));
        }
        $file = $_FILES['logo'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $path = $this->badges->storeUpload($tenantId, $id, $file, 'issuer');
                $this->issuerRepo()->setLogo($tenantId, $id, $path);
            } catch (Throwable $e) {
                Session::flash('error', 'Logo non enregistré : ' . $e->getMessage());
            }
        }
    }

    private function issuerRepo(): QualificationIssuerRepository
    {
        return $this->issuerRepository ??= new QualificationIssuerRepository();
    }

    public function storeLevel(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($request, $id) {
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                Session::flash('error', 'Le nom du niveau est obligatoire.');

                return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit'));
            }
            $prev = (int) $request->input('previous_level_id', 0);
            $this->referentiel->createLevel($tenantId, $id, [
                'name' => $name,
                'short_name' => $request->input('short_name', ''),
                'sort_order' => $request->input('sort_order', 0),
                'description' => $request->input('description', ''),
                'previous_level_id' => $prev > 0 ? $prev : null,
            ]);
            Session::flash('success', 'Niveau ajouté.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit') . '#niveaux');
        });
    }

    public function deleteLevel(Request $request, array $params = []): Response
    {
        $qualId = (int) ($params['id'] ?? 0);
        $levelId = (int) ($params['levelId'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($qualId, $levelId) {
            $this->referentiel->deleteLevel($tenantId, $levelId);
            Session::flash('success', 'Niveau retiré.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $qualId . '/edit') . '#niveaux');
        });
    }

    public function storePrerequisite(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($request, $id) {
            $required = (int) $request->input('required_qualification_id', 0);
            if ($required <= 0 || $required === $id) {
                Session::flash('error', 'Prérequis invalide.');

                return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit'));
            }
            $minLevel = (int) $request->input('minimum_level_id', 0);
            $this->referentiel->addPrerequisite(
                $tenantId,
                $id,
                $required,
                $minLevel > 0 ? $minLevel : null,
                (string) $request->input('requirement_type', 'obtention')
            );
            Session::flash('success', 'Prérequis ajouté.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit') . '#prerequis');
        });
    }

    public function deletePrerequisite(Request $request, array $params = []): Response
    {
        $qualId = (int) ($params['id'] ?? 0);
        $prereqId = (int) ($params['prereqId'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($qualId, $prereqId) {
            $this->referentiel->removePrerequisite($tenantId, $prereqId);
            Session::flash('success', 'Prérequis retiré.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $qualId . '/edit') . '#prerequis');
        });
    }

    public function storeCustomField(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($request, $id) {
            $name = trim((string) $request->input('name', ''));
            $code = strtoupper(trim((string) $request->input('code', '')));
            if ($name === '' || $code === '') {
                Session::flash('error', 'Nom et code du champ sont obligatoires.');

                return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit'));
            }
            $this->referentiel->createCustomField($tenantId, $id, [
                'name' => $name,
                'code' => $code,
                'field_type' => $request->input('field_type', 'text_short'),
                'is_required' => $request->input('is_required'),
                'default_value' => $request->input('default_value'),
                'sort_order' => $request->input('sort_order', 0),
                'visibility' => $request->input('visibility', 'normal'),
            ]);
            Session::flash('success', 'Champ personnalisé ajouté.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit') . '#champs');
        });
    }

    public function deleteCustomField(Request $request, array $params = []): Response
    {
        $qualId = (int) ($params['id'] ?? 0);
        $fieldId = (int) ($params['fieldId'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($qualId, $fieldId) {
            $this->referentiel->deleteCustomField($tenantId, $fieldId);
            Session::flash('success', 'Champ retiré.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $qualId . '/edit') . '#champs');
        });
    }

    public function storePermissionGrant(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($request, $id) {
            $code = trim((string) $request->input('permission_code', ''));
            $grants = new \App\Services\Personnel\QualificationPermissionGrantService($this->referentiel);
            if (!$grants->isPermissionAllowed($code)) {
                Session::flash('error', 'Cette permission ne peut pas être accordée via une qualification (accès d’administration interdit).');

                return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit') . '#droits');
            }
            $level = (int) $request->input('qualification_level_id', 0);
            $this->referentiel->addPermissionGrant($tenantId, $id, $code, $level > 0 ? $level : null);
            Session::flash('success', 'Droit lié à la qualification.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit') . '#droits');
        });
    }

    public function deletePermissionGrant(Request $request, array $params = []): Response
    {
        $qualId = (int) ($params['id'] ?? 0);
        $grantId = (int) ($params['grantId'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($qualId, $grantId) {
            $this->referentiel->removePermissionGrant($tenantId, $grantId);
            Session::flash('success', 'Lien de droit retiré.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $qualId . '/edit') . '#droits');
        });
    }

    public function uploadBadge(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($request, $id) {
            try {
                $file = $_FILES['badge'] ?? [];
                $path = $this->badges->storeUpload($tenantId, $id, is_array($file) ? $file : []);
                $this->definitions->setBadgePath($tenantId, $id, $path);
                Session::flash('success', 'Insigne enregistré.');
            } catch (Throwable $e) {
                Session::flash('error', $e->getMessage());
            }

            return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit') . '#badge');
        });
    }

    public function awardForm(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $userId = (int) $request->query('user_id', 0);
        $definitionId = (int) $request->query('definition_id', 0);
        $renewalOf = (int) $request->query('renewal_of', 0);
        $prefill = null;
        if ($renewalOf > 0) {
            $prefill = $this->awards->find($renewalOf, $tenantId);
            if ($prefill !== null) {
                $userId = (int) $prefill['user_id'];
                $definitionId = (int) ($prefill['definition_id'] ?? 0);
            }
        }
        $definition = $definitionId > 0 ? $this->definitions->find($tenantId, $definitionId) : null;

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.award_form',
            'title' => $renewalOf > 0 ? 'Renouveler une qualification' : 'Attribuer une qualification',
            'definitions' => $this->definitions->listForTenant($tenantId),
            'issuers' => $this->issuerRepo()->listForTenant($tenantId),
            'issuerPreset' => (int) $request->query('issuer_id', 0),
            'levels' => $definition ? $this->referentiel->listLevels($tenantId, (int) $definition['id']) : [],
            'customFields' => $definition ? $this->referentiel->listCustomFields($tenantId, (int) $definition['id']) : [],
            'userId' => $userId,
            'definitionId' => $definitionId,
            'definition' => $definition,
            'renewalOf' => $renewalOf,
            'prefill' => $prefill,
            'adminStatuses' => QualificationAdminStatus::ALL,
            'visibilityLevels' => VisibilityLevel::ALL,
            'suggestedExpires' => $definition
                ? $this->temporal->computeDefaultExpiresAt(
                    null,
                    isset($definition['default_validity_months']) ? (int) $definition['default_validity_months'] : null,
                    !empty($definition['is_permanent'])
                )
                : null,
        ]);
    }

    public function awardStore(Request $request, array $params = []): Response
    {
        return $this->withTenantPost($request, function (int $tenantId) use ($request) {
            $userId = (int) $request->input('user_id', 0);
            $definitionId = (int) $request->input('definition_id', 0);
            if ($userId <= 0 || $definitionId <= 0) {
                Session::flash('error', 'Membre et qualification sont obligatoires.');

                return Response::redirect(url('back-office/referentiels/qualifications/attribuer'));
            }
            try {
                $custom = [];
                foreach ((array) $request->input('custom_values', []) as $fid => $val) {
                    $custom[(int) $fid] = $val;
                }
                $panel = [];
                foreach ((array) $request->input('panel_member_ids', []) as $uid) {
                    $panel[] = ['user_id' => (int) $uid, 'role' => 'evaluator'];
                }
                $president = (int) $request->input('panel_president_id', 0);
                if ($president > 0) {
                    $panel[] = ['user_id' => $president, 'role' => 'president'];
                }
                $data = [
                    'definition_id' => $definitionId,
                    'qualification_level_id' => $request->input('qualification_level_id'),
                    'issuer_id' => $request->input('issuer_id'),
                    'admin_status' => $request->input('admin_status', QualificationAdminStatus::OBTAINED),
                    'obtained_at' => $request->input('obtained_at'),
                    'expires_at' => $request->input('expires_at'),
                    'expires_at_manual' => $request->input('expires_at') !== null && $request->input('expires_at') !== '',
                    'certificate_number' => $request->input('certificate_number'),
                    'reference' => $request->input('reference'),
                    'notes' => $request->input('notes'),
                    'visibility_level' => $request->input('visibility_level', VisibilityLevel::NORMAL),
                    'is_primary' => $request->input('is_primary'),
                    'is_retrospective' => $request->input('is_retrospective'),
                    'renewal_of_id' => $request->input('renewal_of_id') ?: null,
                    'custom_values' => $custom,
                    'panel_members' => $panel,
                ];
                $id = $this->transitions->award($tenantId, $userId, $data, $this->actorId());
                Session::flash('success', 'Qualification attribuée.');

                return Response::redirect(url('back-office/ressources/effectifs/membres/' . $userId) . '#qualifications');
            } catch (Throwable $e) {
                Session::flash('error', $e->getMessage());

                return Response::redirect(
                    url('back-office/referentiels/qualifications/attribuer')
                    . '?user_id=' . $userId . '&definition_id=' . $definitionId
                );
            }
        });
    }

    public function awardTransition(Request $request, array $params = []): Response
    {
        $awardId = (int) ($params['awardId'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($request, $awardId) {
            try {
                $this->transitions->transition(
                    $tenantId,
                    $awardId,
                    (string) $request->input('admin_status', ''),
                    $this->actorId(),
                    (string) $request->input('revocation_reason', '')
                );
                Session::flash('success', 'Statut mis à jour.');
            } catch (Throwable $e) {
                Session::flash('error', $e->getMessage());
            }
            $award = $this->awards->find($awardId, $tenantId);
            $userId = $award ? (int) $award['user_id'] : 0;

            return Response::redirect(
                $userId > 0
                    ? url('back-office/ressources/effectifs/membres/' . $userId) . '#qualifications'
                    : url('back-office/referentiels/qualifications')
            );
        });
    }

    public function generateCertificate(Request $request, array $params = []): Response
    {
        $awardId = (int) ($params['awardId'] ?? 0);

        return $this->withTenantPost($request, function (int $tenantId) use ($awardId) {
            try {
                $res = $this->certificates->generate($tenantId, $awardId, $this->actorId());
                $holder = trim((string) ($res['holder_name'] ?? ''));
                Session::flash(
                    'success',
                    'Brevet généré'
                    . ($holder !== '' ? ' au nom de ' . $holder : '')
                    . ' (n° ' . $res['certificate_number'] . ').'
                );
                $download = url('back-office/referentiels/qualifications/brevets/' . $awardId . '/telecharger');

                return Response::redirect($download);
            } catch (Throwable $e) {
                Session::flash('error', $e->getMessage());
                $award = $this->awards->find($awardId, $tenantId);
                $userId = $award ? (int) $award['user_id'] : 0;

                return Response::redirect(
                    $userId > 0
                        ? url('back-office/ressources/effectifs/membres/' . $userId) . '#qualifications'
                        : url('back-office/referentiels/qualifications')
                );
            }
        });
    }

    public function generateCertificateBatch(Request $request, array $params = []): Response
    {
        return $this->withTenantPost($request, function (int $tenantId) use ($request) {
            $ids = array_map('intval', (array) $request->input('award_ids', []));
            $ids = array_values(array_filter($ids, static fn (int $i): bool => $i > 0));
            $results = $this->certificates->generateBatch($tenantId, $ids, $this->actorId());
            $ok = count(array_filter($results, static fn (array $r): bool => !empty($r['ok'])));
            Session::flash('success', $ok . ' brevet(s) généré(s) sur ' . count($results) . '.');

            return Response::redirect(url('back-office/ressources/effectifs/qualifications'));
        });
    }

    public function downloadCertificate(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $awardId = (int) ($params['awardId'] ?? 0);
        $award = $this->awards->find($awardId, $tenantId);
        if ($award === null || empty($award['certificate_document_path'])) {
            Session::flash('error', 'Aucun brevet disponible pour cette attribution.');

            return Response::redirect(url('back-office/referentiels/qualifications'));
        }
        $abs = base_path('storage/uploads/' . ltrim((string) $award['certificate_document_path'], '/'));
        if (!is_file($abs)) {
            Session::flash('error', 'Fichier de brevet introuvable.');

            return Response::redirect(url('back-office/referentiels/qualifications'));
        }
        $downloadName = 'brevet-' . preg_replace('/[^A-Za-z0-9_\\-]/', '', (string) ($award['certificate_number'] ?? $awardId)) . '.pdf';
        $response = new Response();
        $response->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $downloadName . '"')
            ->setBodyStream(static function () use ($abs): void {
                $fh = fopen($abs, 'rb');
                if ($fh !== false) {
                    fpassthru($fh);
                    fclose($fh);
                }
            });

        return $response;
    }

    /** Modes d'obtention, prérequis et visibilité envoyés avec le formulaire de la qualification. */
    private function saveObtention(int $tenantId, int $definitionId, Request $request): void
    {
        if ($this->rules === null || $request->input('obtention_form') === null) {
            return;
        }
        try {
            $this->rules->saveFromInput($tenantId, $definitionId, [
                'allow_manual' => $request->input('allow_manual'),
                'auto_enabled' => $request->input('auto_enabled'),
                'auto_mode' => $request->input('auto_mode'),
                'rule_json' => $request->input('rule_json'),
                'prereq_grade_id' => $request->input('prereq_grade_id'),
                'prereq_job_role_id' => $request->input('prereq_job_role_id'),
                'show_on_profile' => $request->input('show_on_profile'),
                'show_on_orbat' => $request->input('show_on_orbat'),
                'min_holders_per_unit' => $request->input('min_holders_per_unit'),
            ], $this->actorId());
        } catch (Throwable $e) {
            Session::flash('error', 'Modes d’obtention non enregistrés : ' . $e->getMessage());
        }
    }

    /**
     * Données du bloc « Comment l’obtenir » du formulaire : réglages, critères disponibles et listes de choix.
     *
     * @return array<string, mixed>
     */
    private function obtentionViewData(int $tenantId, int $definitionId): array
    {
        if ($this->rules === null) {
            return ['obtention' => null];
        }
        $grades = [];
        $jobs = [];
        try {
            $grades = $this->grades?->listForTenant($tenantId) ?? [];
        } catch (Throwable) {
        }
        try {
            $jobs = $this->jobRoles !== null && $this->jobRoles->tablesExist() ? $this->jobRoles->listRoleOptionsForSelect($tenantId) : [];
        } catch (Throwable) {
        }
        $names = $this->rules->names($tenantId);

        return ['obtention' => [
            'ready' => $this->rules->schemaReady(),
            'settings' => $definitionId > 0 ? $this->rules->settings($tenantId, $definitionId) : QualificationAwardRuleService::defaults() + ['rule' => []],
            'grades' => $grades,
            'jobs' => $jobs,
            'courses' => $names['courses'],
            'builtInCourses' => $names['builtin'] ?? [],
            'definitions' => array_filter($names['definitions'], static fn (int $id): bool => $id !== $definitionId, ARRAY_FILTER_USE_KEY),
            'aircraft' => $this->rules->aircraftOptions($tenantId),
            'items' => $this->rules->itemOptions($tenantId),
            'sessionCategories' => $this->rules->sessionCategories($tenantId),
        ]];
    }

    /** @return array<string, mixed> */
    private function definitionPayload(Request $request): array
    {
        return [
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'short_name' => $request->input('short_name'),
            'description' => $request->input('description'),
            'category_id' => $request->input('category_id'),
            'type_id' => $request->input('type_id'),
            'uses_levels' => $request->input('uses_levels'),
            'is_permanent' => $request->input('is_permanent'),
            'default_validity_months' => $request->input('default_validity_months'),
            'alert_before_expiry_days' => $request->input('alert_before_expiry_days'),
            'grace_period_days' => $request->input('grace_period_days'),
            'enforce_level_progression' => $request->input('enforce_level_progression'),
            'requires_panel' => $request->input('requires_panel'),
            'qualification_scope' => $request->input('qualification_scope', 'global'),
            'certificate_template_id' => $request->input('certificate_template_id'),
            'certificate_number_format' => $request->input('certificate_number_format'),
            'currency_days' => $request->input('currency_days'),
            'requires_exam' => $request->input('requires_exam'),
            'renewal_required' => $request->input('renewal_required'),
        ];
    }

    private function tenantIdOrRedirect(): int|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }

        return $tenantId;
    }

    private function actorId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    /** @param callable(int): Response $fn */
    private function withTenantPost(Request $request, callable $fn): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/referentiels/qualifications'));
        }

        return $fn($tenantId);
    }
}
