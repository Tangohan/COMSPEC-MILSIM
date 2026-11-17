<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\MilitarySpecialtyService;
use App\Support\EffectifsLmsAccess;
use InvalidArgumentException;

/** Nomenclatures nationales dans le bureau RH ; aucun effet sur grades, droits ou affectations. */
final class MilitarySpecialtyController
{
    public function __construct(
        private ?MilitarySpecialtyService $specialties = null,
        private ?UserRepository $users = null,
        private ?PersonnelJobRoleRepository $jobs = null,
        private ?QualificationDefinitionRepository $definitions = null,
        private ?PersonnelQualificationRepository $awards = null,
    ) {}

    private function service(): MilitarySpecialtyService
    {
        return $this->specialties ??= new MilitarySpecialtyService();
    }

    public static function canManageJobs(Gate $gate): bool
    {
        return EffectifsLmsAccess::canManageAssignments($gate) || EffectifsLmsAccess::canManageRoles($gate);
    }

    /** @return array{ready:bool,rows:array,url:string} */
    public function memberPanel(int $tenantId, int $userId): array
    {
        $ready = $this->service()->ready();
        return ['ready' => $ready, 'rows' => $ready ? $this->service()->listForMember($tenantId, $userId) : [],
            'url' => effectifs_workspace_url('specialites') . '?member=' . $userId];
    }

    public function qualificationPanel(int $tenantId, int $definitionId): array
    {
        return $this->service()->ready() ? $this->service()->qualificationTargets($tenantId, $definitionId) : [];
    }

    private function guard(): ?Response
    {
        if ((int) Session::get('tenant_id') < 1 || (int) Session::get('user_id') < 1) {
            return Response::json(['ok' => false, 'message' => 'Reconnectez-vous pour ouvrir les spécialités.'], 401);
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            return Response::json(['ok' => false, 'message' => 'Accès réservé au pilotage des effectifs.'], 403);
        }
        return null;
    }

    /** @return array<string,mixed>|Response */
    public function pageData(Request $request): array|Response
    {
        if ($denied = $this->guard()) return $denied;
        $tenant = (int) Session::get('tenant_id');
        $memberId = max(0, (int) $request->query('member', 0));
        $jobId = max(0, (int) $request->query('job', 0));
        $definitionId = max(0, (int) $request->query('qualification', 0));
        $member = $job = $definition = null;
        if ($memberId > 0) {
            $users = $this->users ??= new UserRepository();
            if (!$users->isMemberOfTenant($memberId, $tenant) || !($member = $users->findById($memberId, $tenant))) {
                return Response::json(['ok' => false, 'message' => 'Membre introuvable.'], 404);
            }
        } elseif ($jobId > 0) {
            $job = ($this->jobs ??= new PersonnelJobRoleRepository())->findRoleById($jobId, $tenant);
            if ($job === null) return Response::json(['ok' => false, 'message' => 'Emploi introuvable.'], 404);
        } elseif ($definitionId > 0) {
            $definition = ($this->definitions ??= new QualificationDefinitionRepository())->find($tenant, $definitionId);
            if ($definition === null) return Response::json(['ok' => false, 'message' => 'Qualification introuvable.'], 404);
        }
        $ready = $this->service()->ready();
        $catalog = $ready ? $this->service()->catalog() : [];
        $countries = [];
        foreach ($catalog as $code) $countries[(string) $code['country_code']] = (string) $code['country_name_fr'];
        $country = strtoupper(trim((string) $request->query('country', '')));
        $search = mb_strtolower(trim((string) $request->query('q', '')));
        $selected = null;
        foreach ($catalog as $code) {
            if ((int) $code['id'] === (int) $request->query('code', 0)) $selected = $code;
        }
        if ($ready && (int) $request->query('code', 0) > 0) {
            $selected = $this->service()->findCode((int) $request->query('code', 0));
        }
        $gate = Gate::getInstance();
        $memberRows = $ready && $member !== null ? $this->service()->listForMember($tenant, $memberId) : [];
        $jobIds = $ready && $job !== null ? $this->service()->jobCodeIds($tenant, $jobId) : [];
        $jobOptions = $catalog;
        foreach (array_diff($jobIds, array_map('intval', array_column($catalog, 'id'))) as $oldId) {
            $oldCode = $this->service()->findCode((int) $oldId);
            if ($oldCode !== null) $jobOptions[] = $oldCode;
        }

        return [
            'title' => 'Spécialités militaires', 'effectifsNav' => 'specialties', 'jobsReferenceWorkspace' => true,
            'specialtyReady' => $ready, 'specialtyCountries' => $countries, 'specialtyCountry' => $country,
            'specialtySearch' => (string) $request->query('q', ''), 'specialtySelectedCode' => $selected,
            'specialtyCatalog' => array_values(array_filter($catalog, static function (array $row) use ($country, $search): bool {
                return ($country === '' || $row['country_code'] === $country)
                    && ($search === '' || str_contains(mb_strtolower(implode(' ', array_map('strval', array_intersect_key($row, array_flip(['code', 'label_fr', 'label_en', 'official_title', 'system_name', 'service_name', 'description']))))), $search));
            })),
            'specialtyAllCodes' => $jobOptions, 'specialtyMember' => $member, 'specialtyJob' => $job,
            'specialtyDefinition' => $definition, 'specialtyMemberRows' => $memberRows,
            'specialtyJobIds' => $jobIds,
            'specialtyTargets' => $ready && $definition !== null ? $this->service()->qualificationTargets($tenant, $definitionId) : [],
            'specialtyCanMember' => EffectifsLmsAccess::canEditProfiles($gate),
            'specialtyCanJob' => self::canManageJobs($gate),
            'specialtyCanQualification' => MemberQualificationSuggestionsController::canManageReferential($gate),
            'specialtyEvidence' => $ready && $member !== null && EffectifsLmsAccess::canEditProfiles($gate)
                ? ($this->awards ??= new PersonnelQualificationRepository())->listForUser($memberId, $tenant) : [],
            'csrfToken' => Csrf::token(),
        ];
    }

    public function save(Request $request, array $params = []): Response
    {
        if ($denied = $this->guard()) return $denied;
        if (!Csrf::verify($request)) return Response::json(['ok' => false, 'message' => 'Session expirée. Rechargez la page.'], 419);
        $action = $request->inputString('action', '');
        $gate = Gate::getInstance();
        $allowed = match ($action) {
            'member', 'archive' => EffectifsLmsAccess::canEditProfiles($gate),
            'job' => self::canManageJobs($gate),
            'qualification', 'remove_target' => MemberQualificationSuggestionsController::canManageReferential($gate),
            default => false,
        };
        if (!$allowed) return Response::json(['ok' => false, 'message' => 'Vous n’êtes pas habilité à modifier ce volet.'], 403);
        $tenant = (int) Session::get('tenant_id');
        $actor = (int) Session::get('user_id');
        $id = max(0, (int) $request->input('target_id', 0));
        $context = in_array($action, ['member', 'archive'], true) ? 'member' : ($action === 'job' ? 'job' : 'qualification');
        try {
            if (!$this->service()->ready()) throw new InvalidArgumentException('Les spécialités seront disponibles après la mise à jour du site.');
            if ($action === 'member') {
                $this->service()->saveMember($tenant, $id, [
                    'specialty_code_id' => $request->input('specialty_code_id'),
                    'specialty_level_id' => $request->input('specialty_level_id'),
                    'status' => $request->input('status', 'declared'),
                    'obtained_at' => $request->input('obtained_at'),
                    'evidence_qualification_id' => $request->input('evidence_qualification_id'),
                ], $actor);
            } elseif ($action === 'archive') {
                $this->service()->archiveMember($tenant, $id, (int) $request->input('assignment_id', 0), $actor);
            } elseif ($action === 'job') {
                $ids = $request->input('specialty_code_ids', []);
                if (!is_array($ids)) throw new InvalidArgumentException('Choisissez les spécialités de l’emploi.');
                $this->service()->saveJobCodes($tenant, $id, $ids, $actor);
            } else {
                $codeId = (int) $request->input('specialty_code_id', 0);
                $this->service()->changeQualificationTarget($tenant, $id, $codeId, $request->input('specialty_level_id'), $actor, $action === 'remove_target');
            }
            Session::flash('success', $action === 'archive' ? 'Spécialité archivée. Son historique est conservé.' : 'Spécialités enregistrées. Les grades, emplois, affectations et qualifications se gèrent séparément.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Military specialties save: ' . $e->getMessage());
            Session::flash('error', 'L’enregistrement a échoué. Aucune modification partielle n’a été conservée.');
        }
        return Response::redirect(effectifs_workspace_url('specialites') . '?' . http_build_query([$context => $id, 'code' => max(0, (int) $request->input('specialty_code_id', 0))]));
    }
}
