<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\QualificationReferentielRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\QualificationBadgeStorageService;
use App\Services\Personnel\QualificationCertificatePdfService;
use App\Services\Personnel\QualificationStatusTransitionService;
use App\Services\Personnel\QualificationTemporalStatusService;
use App\Support\QualificationAdminStatus;
use App\Support\VisibilityLevel;
use Throwable;

final class QualificationReferentielController
{
    public function __construct(
        private QualificationDefinitionRepository $definitions,
        private QualificationReferentielRepository $referentiel,
        private QualificationAwardRepository $awards,
        private QualificationStatusTransitionService $transitions,
        private QualificationTemporalStatusService $temporal,
        private QualificationBadgeStorageService $badges,
        private QualificationCertificatePdfService $certificates,
        private UserRepository $users,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.index',
            'title' => 'Référentiel des qualifications',
            'definitions' => $this->definitions->listForTenant($tenantId, true),
            'categories' => $this->referentiel->listCategories($tenantId),
            'types' => $this->referentiel->listTypes($tenantId),
            'temporal' => $this->temporal,
            'issuerCount' => count($this->referentiel->listIssuers($tenantId)),
            'backOfficePageCss' => ['back-office-qualifications-referentiel.css'],
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
            'definition' => null,
            'categories' => $this->referentiel->listCategories($tenantId),
            'types' => $this->referentiel->listTypes($tenantId),
            'templates' => $this->referentiel->listCertificateTemplates($tenantId),
            'levels' => [],
            'prerequisites' => [],
            'customFields' => [],
            'permissionGrants' => [],
            'holders' => [],
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!Csrf::validate($request)) {
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
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::validate($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/referentiels/qualifications/' . $id . '/edit'));
        }
        try {
            $this->definitions->update($tenantId, $id, $this->definitionPayload($request), $this->actorId());
            Session::flash('success', 'Qualification mise à jour.');
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
        if (!Csrf::validate($request)) {
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
                Session::flash('error', 'Le nom de l’organisme est obligatoire.');

                return Response::redirect(url('back-office/referentiels/qualifications/emetteurs'));
            }
            $parent = (int) $request->input('parent_issuer_id', 0);
            $this->referentiel->createIssuer(
                $tenantId,
                $name,
                (string) $request->input('short_name', ''),
                (string) $request->input('issuer_kind', 'unit'),
                $parent > 0 ? $parent : null
            );
            Session::flash('success', 'Organisme émetteur ajouté.');

            return Response::redirect(url('back-office/referentiels/qualifications/emetteurs'));
        });
    }

    public function issuers(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $q = trim((string) $request->query('q', ''));

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.issuers',
            'title' => 'Organismes émetteurs',
            'issuers' => $this->referentiel->listIssuers($tenantId, $q !== '' ? $q : null),
            'searchQuery' => $q,
            'memberSearchUrl' => url('api/admin/qualifications/members'),
            'backOfficePageCss' => ['back-office-qualifications-referentiel.css'],
        ]);
    }

    public function seedUsArmyIssuers(Request $request, array $params = []): Response
    {
        return $this->withTenantPost($request, function (int $tenantId) {
            $result = $this->referentiel->seedUsArmyExampleIssuers($tenantId);
            $created = (int) ($result['created'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            if ($created === 0 && $skipped === 0) {
                Session::flash('error', 'Impossible d’ajouter les exemples US Army.');
            } elseif ($created === 0) {
                Session::flash('success', 'Exemples US Army déjà présents (' . $skipped . ').');
            } else {
                Session::flash(
                    'success',
                    $created . ' organisme(s) US Army ajouté(s)'
                    . ($skipped > 0 ? ' (' . $skipped . ' déjà présents).' : '.')
                );
            }

            return Response::redirect(url('back-office/referentiels/qualifications/emetteurs'));
        });
    }

    public function searchMembers(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return Response::json(['users' => []], 401);
        }
        $rows = $this->users->searchMembersForQualificationAward(
            $tenantId,
            (string) $request->query('q', ''),
            20
        );
        $users = [];
        foreach ($rows as $u) {
            $users[] = [
                'id' => (int) ($u['id'] ?? 0),
                'display_name' => trim((string) ($u['display_name'] ?? '')),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'email' => (string) ($u['email'] ?? ''),
                'athena_identifier' => trim((string) ($u['athena_identifier'] ?? '')),
                'tenant_member_number' => trim((string) ($u['tenant_member_number'] ?? '')),
                'status' => (string) ($u['status'] ?? ''),
            ];
        }

        return Response::json(['users' => $users]);
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
        $selectedMember = $userId > 0 ? $this->users->findById($userId, $tenantId) : null;

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.award_form',
            'title' => $renewalOf > 0 ? 'Renouveler une qualification' : 'Attribuer une qualification',
            'definitions' => $this->definitions->listForTenant($tenantId),
            'issuers' => $this->referentiel->listIssuers($tenantId),
            'levels' => $definition ? $this->referentiel->listLevels($tenantId, (int) $definition['id']) : [],
            'customFields' => $definition ? $this->referentiel->listCustomFields($tenantId, (int) $definition['id']) : [],
            'userId' => $userId,
            'selectedMember' => $selectedMember,
            'definitionId' => $definitionId,
            'definition' => $definition,
            'renewalOf' => $renewalOf,
            'prefill' => $prefill,
            'adminStatuses' => QualificationAdminStatus::ALL,
            'visibilityLevels' => VisibilityLevel::ALL,
            'memberSearchUrl' => url('api/admin/qualifications/members'),
            'backOfficePageCss' => ['back-office-qualifications-referentiel.css'],
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
                Session::flash('success', 'Brevet généré (n° ' . $res['certificate_number'] . ').');
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
        if (!Csrf::validate($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/referentiels/qualifications'));
        }

        return $fn($tenantId);
    }
}
