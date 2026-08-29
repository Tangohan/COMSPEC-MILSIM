<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EmailTokenRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\RoleRepository;
use App\Repositories\GradeCategoryRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PositionRepository;
use App\Repositories\RoleSetRepository;
use App\Services\Admin\ProfileCompletenessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Email\EmailEvents;
use App\Services\Email\EmailTokenPurpose;
use App\Services\EmailService;
use App\Services\GradeValidationService;
use App\Services\Personnel\PersonnelCompletenessService;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Personnel\PersonnelOrgHistoryRecorder;
use App\Services\Personnel\PersonnelStructureChangeNotificationService;
use App\Support\Audit\AuditFieldSnapshot;
use App\Support\OrganizationRoleLabels;
use App\Services\Community\TenantTypeConfig;
use App\Services\Steam\SteamWebApiService;

class UserAdminController
{
    private const SETUP_TOKEN_HOURS = 72;

    private const RESEND_VERIFICATION_COOLDOWN_SEC = 90;

    private function organizationRoleLabelModeForTenant(int $tenantId): string
    {
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $tenant = $this->tenantRepository->findById($tenantId) ?: [];

        return OrganizationRoleLabels::mode($community, $tenant);
    }

    /** URL de retour après erreur de création (évite le hub structure bloqué en profil Carte ATAK). */
    private function memberCreateEntryUrl(int $tenantId): string
    {
        $tenant = $this->tenantRepository->findById($tenantId) ?: [];
        $type = TenantTypeConfig::normalizeType((string) ($tenant['tenant_type'] ?? 'full'));
        if (TenantTypeConfig::moduleAllowed($type, 'personnel')) {
            return url('back-office/organisation/structure?ouvrir=membre');
        }

        return url('back-office/users/create');
    }

    /**
     * @return array{ok: true, steam_id: ?string}|array{ok: false, error: string}
     */
    private function resolveSteamIdInput(string $raw, int $tenantId, ?int $excludeUserId = null): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => true, 'steam_id' => null];
        }
        $resolved = $this->steamWebApiService->resolveSteamIdFromUserInput($raw);
        if ($resolved === null) {
            return [
                'ok' => false,
                'error' => 'Impossible de reconnaître l’identifiant Steam. Indiquez le numéro Steam, le format classique, ou l’adresse du profil public.',
            ];
        }
        $existing = $this->userRepository->findBySteamIdForTenant($tenantId, $resolved);
        if ($existing && ($excludeUserId === null || (int) $existing['id'] !== $excludeUserId)) {
            return [
                'ok' => false,
                'error' => 'Cet identifiant Steam est déjà rattaché à un autre membre de la communauté.',
            ];
        }

        return ['ok' => true, 'steam_id' => $resolved];
    }

    /**
     * Synchronise uniquement la photo depuis le profil public Steam.
     * Le nom du personnage (prénom + nom) n’est jamais écrasé par le pseudo Steam.
     *
     * @return list<string> messages de résultat
     */
    private function applySteamProfileSync(int $userId, int $tenantId, string $steamId): array
    {
        $notes = [];
        if (!$this->steamWebApiService->isConfigured()) {
            $notes[] = 'Identifiant Steam enregistré. La synchronisation du profil public n’est pas configurée sur ce serveur.';

            return $notes;
        }
        $summary = $this->steamWebApiService->fetchPublicPlayer($steamId);
        if ($summary === null) {
            $notes[] = 'Identifiant Steam enregistré, mais le profil public Steam n’a pas pu être lu.';

            return $notes;
        }
        $patch = [];
        if (($summary['avatar_url'] ?? '') !== '') {
            $patch['avatar_url'] = function_exists('mb_substr')
                ? mb_substr((string) $summary['avatar_url'], 0, 500)
                : substr((string) $summary['avatar_url'], 0, 500);
        }
        if ($patch === []) {
            $notes[] = 'Identifiant Steam enregistré. Aucune donnée exploitable renvoyée par Steam.';

            return $notes;
        }
        $this->userRepository->update($userId, $tenantId, $patch);
        $notes[] = 'Photo du compte mise à jour depuis Steam.';

        return $notes;
    }

    public function __construct(
        private UserRepository $userRepository,
        private UserLegalIdentityRepository $userLegalIdentityRepository,
        private UserProfileRepository $userProfileRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelExtrasRepository $personnelExtrasRepository,
        private RoleRepository $roleRepository,
        private GradeRepository $gradeRepository,
        private GradeCategoryRepository $gradeCategoryRepository,
        private ProfileCompletenessService $profileCompletenessService,
        private PersonnelCompletenessService $personnelCompletenessService,
        private AdminAuditService $adminAuditService,
        private GradeValidationService $gradeValidationService,
        private EmailService $emailService,
        private TenantRepository $tenantRepository,
        private PasswordResetRepository $passwordResetRepository,
        private UserNotificationPreferencesRepository $userNotificationPreferencesRepository,
        private EmailTokenRepository $emailTokenRepository,
        private PositionRepository $positionRepository,
        private RoleSetRepository $roleSetRepository,
        private IndicatorBlocklistService $indicatorBlocklist,
        private PersonnelOrgHistoryRecorder $personnelOrgHistoryRecorder,
        private PersonnelStructureChangeNotificationService $structureChangeNotification,
        private SteamWebApiService $steamWebApiService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $search = $this->queryString($request->query('search'));
        $status = $this->queryString($request->query('status'));
        if ($status === 'pending') {
            $status = 'pending_verification';
        }
        $roleId = $this->positiveIntOrNull($request->query('role_id'));
        $filterIncomplete = $request->query('filter_incomplete') === '1' || $request->query('filter_incomplete') === 'true';
        $filterNoUnit = $request->query('filter_no_unit') === '1' || $request->query('filter_no_unit') === 'true';
        $filterNoRole = $request->query('filter_no_role') === '1' || $request->query('filter_no_role') === 'true';
        $excludeServiceAccounts = true;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $statusFilter = ($status !== null && $status !== '') ? $status : null;

        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $forPlatformOperator = Gate::getInstance()->allows('admin.system');

        if ($filterIncomplete) {
            $allUsers = $this->userRepository->listForTenant($tenantId, $search, $statusFilter, $roleId, null, null, $excludeServiceAccounts);
            $completenessByUser = [];
            $personnelCompletenessByUser = [];
            foreach ($allUsers as $u) {
                $uid = (int) $u['id'];
                $up = $this->userProfileRepository->getByUserId($uid);
                $pp = $this->profileCompletenessService->getCompleteness($uid, $u, $up, null, $forPlatformOperator);
                $completenessByUser[$uid] = $pp;
                $personnelCompletenessByUser[$uid] = $this->buildPersonnelCompletenessForList($uid, $u, $tenantId, $forPlatformOperator);
            }
            $filtered = array_values(array_filter($allUsers, function ($u) use ($completenessByUser, $personnelCompletenessByUser) {
                $uid = (int) $u['id'];
                $comp = $completenessByUser[$uid] ?? ['score' => 100, 'sections_critiques' => []];
                $pComp = $personnelCompletenessByUser[$uid] ?? ['score' => 100, 'sections_critiques' => []];

                return $comp['score'] < 100 || !empty($comp['sections_critiques'])
                    || $pComp['score'] < 100 || !empty($pComp['sections_critiques']);
            }));
            $total = count($filtered);
            $users = array_slice($filtered, ($page - 1) * $perPage, $perPage);
            $ids = array_map(static fn ($u) => (int) $u['id'], $users);
            $completenessByUser = array_intersect_key($completenessByUser, array_flip($ids));
            $personnelCompletenessByUser = array_intersect_key($personnelCompletenessByUser, array_flip($ids));
        } else {
            $onlyNoUnit = $filterNoUnit ? true : null;
            $onlyNoRole = $filterNoRole ? true : null;
            $total = $this->userRepository->countListForTenant($tenantId, $search, $statusFilter, $roleId, $excludeServiceAccounts, $onlyNoUnit, $onlyNoRole);
            $users = $this->userRepository->listForTenant($tenantId, $search, $statusFilter, $roleId, $perPage, ($page - 1) * $perPage, $excludeServiceAccounts, $onlyNoUnit, $onlyNoRole);
            $completenessByUser = [];
            $personnelCompletenessByUser = [];
            foreach ($users as $u) {
                $uid = (int) $u['id'];
                $up = $this->userProfileRepository->getByUserId($uid);
                $completenessByUser[$uid] = $this->profileCompletenessService->getCompleteness($uid, $u, $up, null, $forPlatformOperator);
                $personnelCompletenessByUser[$uid] = $this->buildPersonnelCompletenessForList($uid, $u, $tenantId, $forPlatformOperator);
            }
        }

        $totalPages = max(1, (int) ceil($total / $perPage));

        return Response::view('layout.main', [
            'content' => 'admin.organization.users.index',
            'title' => 'Membres',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Personnel',
            'boPageTitle' => 'Membres',
            'boPageKicker' => 'PERSONNEL · ANNUAIRE',
            'boPageSubtitle' => 'Annuaire complet de l’unité : identité, affectation, statut du compte, présence et rattachement ATAK.',
            'boPageAction' => 'Ajouter un membre',
            'boPageActionUrl' => url('back-office/users/create'),
            'boPageQuick' => [
                ['label' => 'Actifs', 'href' => url('back-office/users') . '?status=active'],
                ['label' => 'Inactifs', 'href' => url('back-office/users') . '?status=inactive'],
                ['label' => 'Exporter', 'href' => url('back-office/users') . '?export=csv'],
            ],
            'users' => $users,
            'roles' => $roles,
            'completenessByUser' => $completenessByUser,
            'personnelCompletenessByUser' => $personnelCompletenessByUser,
            'filters' => [
                'search' => $search,
                'status' => $status ?? '',
                'role_id' => $roleId,
                'filter_incomplete' => $filterIncomplete,
                'filter_no_unit' => $filterNoUnit,
                'filter_no_role' => $filterNoRole,
            ],
            'usersTotal' => $total,
            'usersPage' => $page,
            'usersPerPage' => $perPage,
            'usersTotalPages' => $totalPages,
            'athUserKpis' => $this->buildUserListKpis($tenantId),
            'backOfficePageCss' => ['back-office-users.css'],
            'showPortalFooter' => false,
        ]);
    }

    /**
     * @return list<array{label: string, value: string, delta: string, tone: string, pct: string, note: string}>
     */
    private function buildUserListKpis(int $tenantId): array
    {
        $total = $this->userRepository->countListForTenant($tenantId, null, null, null, true, null, null);
        $active = $this->userRepository->countListForTenant($tenantId, null, 'active', null, true, null, null);
        $pending = $this->userRepository->countListForTenant($tenantId, null, 'pending_verification', null, true, null, null);
        $inactive = $this->userRepository->countListForTenant($tenantId, null, 'inactive', null, true, null, null);
        $pctActive = $total > 0 ? (int) round($active / $total * 100) : 0;

        return [
            ['label' => 'INSCRITS', 'value' => (string) $total, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'toutes catégories'],
            ['label' => 'ACTIFS', 'value' => (string) $active, 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $pctActive . '%', 'note' => 'comptes actifs'],
            ['label' => 'EN ATTENTE', 'value' => (string) $pending, 'delta' => '', 'tone' => '#c98a12', 'pct' => $total > 0 ? (int) round($pending / $total * 100) . '%' : '0%', 'note' => 'validation dossier'],
            ['label' => 'INACTIFS', 'value' => (string) $inactive, 'delta' => '', 'tone' => '#1e4f80', 'pct' => $total > 0 ? (int) round($inactive / $total * 100) . '%' : '0%', 'note' => 'comptes inactifs'],
        ];
    }

    private function queryString(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return trim((string) $v);
    }

    private function positiveIntOrNull(mixed $v): ?int
    {
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('back-office/users'));
        }
        $userProfile = $this->userProfileRepository->getByUserId($id);
        $legalIdentity = $this->userLegalIdentityRepository->getByUserId($id) ?? [];
        if ($legalIdentity !== []) {
            $userProfile = is_array($userProfile) ? $userProfile : [];
            $userProfile['first_name'] = $legalIdentity['first_name'] ?? ($userProfile['first_name'] ?? '');
            $userProfile['last_name'] = $legalIdentity['last_name'] ?? ($userProfile['last_name'] ?? '');
            $userProfile['phone'] = $legalIdentity['phone'] ?? ($userProfile['phone'] ?? '');
            $userProfile['birth_date'] = $legalIdentity['birth_date'] ?? ($userProfile['birth_date'] ?? '');
            $userProfile['nationality'] = $legalIdentity['nationality'] ?? ($userProfile['nationality'] ?? '');
        }
        $personnelProfile = $this->personnelProfileRepository->getByUserId($id);
        $forPlatformOperator = Gate::getInstance()->allows('admin.system');
        $completenessAccount = $this->profileCompletenessService->getCompleteness($id, $user, $userProfile, $personnelProfile, $forPlatformOperator);
        $isService = $this->userRepository->isServiceAccount($id);
        $extras = $this->personnelExtrasRepository->getByUserId($id);
        $civilProfile = $this->personnelExtrasRepository->getProfileByUserId($id);
        $completenessPersonnel = $isService
            ? null
            : $this->personnelCompletenessService->getScoreWithMissingLabelsForAudience($id, $user, $civilProfile, $extras, $tenantId, $forPlatformOperator);
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $userRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
        if ($userRoleIds === [] && !empty($user['role_id'])) {
            $userRoleIds = [(int) $user['role_id']];
        }
        $displayName = trim((string) ($user['display_name'] ?? ''));
        $headName = $displayName !== '' ? $displayName : 'Fiche membre';
        $headEmail = trim((string) ($user['email'] ?? ''));

        return Response::view('layout.main', [
            'content' => 'admin.organization.users.show',
            'title' => $headName,
            'boPageGroup' => 'Personnel',
            'boPageKicker' => 'PERSONNEL · FICHE MEMBRE',
            'boPageTitle' => $headName,
            'boPageSubtitle' => $headEmail !== '' ? $headEmail : 'Synthèse du compte et du dossier opérationnel.',
            'boPageQuick' => [
                ['label' => 'Liste des membres', 'href' => url('back-office/users')],
                ['label' => 'Réglages du compte', 'href' => url('back-office/users/' . $id . '/edit')],
                ['label' => 'Fiche personnelle', 'href' => url('personnel/' . $id . '/edit')],
            ],
            'user' => $user,
            'userRoleIds' => $userRoleIds,
            'userProfile' => $userProfile,
            'personnelProfile' => $personnelProfile,
            'completeness' => $completenessAccount,
            'completenessAccount' => $completenessAccount,
            'completenessPersonnel' => $completenessPersonnel,
            'isServiceAccount' => $isService,
            'roles' => $roles,
            'showPlatformDiagnostics' => $forPlatformOperator,
            'backOfficePageCss' => ['back-office-users.css'],
            'showPortalFooter' => false,
        ]);
    }

    /**
     * @return list<int>
     */
    private function parseRoleIdsFromRequest(Request $request): array
    {
        $raw = $request->input('role_ids', []);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $rid) {
            $r = (int) $rid;
            if ($r > 0) {
                $out[] = $r;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array{score: int, sections_critiques: list<string>, missing_labels?: list<string>}
     */
    private function buildPersonnelCompletenessForList(int $userId, array $userRow, int $tenantId, bool $forPlatformOperator): array
    {
        if ($this->userRepository->isServiceAccount($userId)) {
            return ['score' => 100, 'sections_critiques' => [], 'missing_labels' => []];
        }
        $extras = $this->personnelExtrasRepository->getByUserId($userId);
        $civil = $this->personnelExtrasRepository->getProfileByUserId($userId);

        return $this->personnelCompletenessService->getScoreWithMissingLabelsForAudience($userId, $userRow, $civil, $extras, $tenantId, $forPlatformOperator);
    }

    public function notifyProfileIncomplete(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('back-office/users'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/users'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');

            return Response::redirect(url('back-office/users'));
        }
        if ($this->userRepository->isServiceAccount($id)) {
            Session::flash('error', 'Les comptes techniques ne reçoivent pas de courriel de rappel.');

            return Response::redirect(url('back-office/users/' . $id));
        }
        $status = (string) ($user['status'] ?? '');
        if ($status !== 'active' && $status !== 'pending_verification') {
            Session::flash('error', 'Seuls les comptes actifs ou en attente peuvent recevoir ce rappel.');

            return Response::redirect(url('back-office/users/' . $id));
        }
        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenantRow ? (string) ($tenantRow['name'] ?? 'Athena') : 'Athena';
        $displayName = trim((string) ($user['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = (string) ($user['email'] ?? 'membre');
        }
        if (!$this->userNotificationPreferencesRepository->isEmailEventEnabled($id, EmailEvents::PROFILE_INCOMPLETE_REMINDER)) {
            Session::flash('error', 'Ce membre a désactivé les e-mails de rappel de profil dans ses préférences compte.');

            return Response::redirect(url('back-office/users/' . $id));
        }
        $editUrl = url('personnel/' . $id . '/edit');
        $ok = $this->emailService->sendProfileIncompleteReminder(
            (string) $user['email'],
            $displayName,
            $tenantName,
            $editUrl,
            $tenantId,
            ['target_user_id' => $id]
        );
        if ($ok) {
            Session::flash('success', 'Courriel de rappel envoyé.');
        } else {
            $base = 'Envoi impossible (vérifiez la configuration e-mail ou l’adresse du destinataire).';
            $detail = $this->emailService->getLastSendError();
            if ($detail !== null && $detail !== '') {
                $clean = preg_replace('/\s+/u', ' ', $detail) ?? $detail;
                if (function_exists('mb_substr')) {
                    $clean = mb_substr($clean, 0, 400);
                } else {
                    $clean = substr($clean, 0, 400);
                }
                $base .= ' Détail : ' . $clean;
            }
            Session::flash('error', $base);
        }

        return Response::redirect(url('back-office/users/' . $id));
    }

    public function create(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $roleMatrix = $this->roleRepository->organizationRolesPermissionMatrix($tenantId);
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeCategories = $this->gradeCategoryRepository->listActive();
        return Response::view('layout.main', [
            'content' => 'admin.organization.users.create',
            'title' => 'Nouvel utilisateur',
            'roles' => $roles,
            'roleMatrix' => $roleMatrix,
            'grades' => $grades,
            'gradeCategories' => $gradeCategories,
            'organizationRoleLabelMode' => $this->organizationRoleLabelModeForTenant($tenantId),
            'steamWebConfigured' => $this->steamWebApiService->isConfigured(),
            'backOfficePageCss' => ['back-office-users.css'],
            'showPortalFooter' => false,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        if (!$tenantId || !$actorUserId) {
            return Response::redirect(url('login'));
        }
        $createUrl = $this->memberCreateEntryUrl($tenantId);
        $email = trim((string) $request->input('email'));
        $firstName = trim((string) $request->input('first_name'));
        $lastName = trim((string) $request->input('last_name'));
        $displayName = trim($firstName . ' ' . $lastName);
        $callsign = trim((string) $request->input('callsign'));
        $roleIds = $this->parseRoleIdsFromRequest($request);
        foreach ($roleIds as $rid) {
            if (!$this->roleRepository->canAssignInTenantAdminContext($rid, $tenantId)) {
                Session::flash('error', 'Un rôle sélectionné ne peut pas être attribué depuis l’administration communauté.');

                return Response::redirect($createUrl);
            }
        }
        $primaryRoleId = $this->userRepository->peekPrimaryRoleIdForTenant($tenantId, $roleIds);
        $gradeId = $request->input('grade_id') ? (int) $request->input('grade_id') : null;
        $nationalityCode = trim((string) $request->input('nationality_code')) ?: null;
        $preferredGradeFormat = trim((string) $request->input('preferred_grade_format'));
        if (!in_array($preferredGradeFormat, ['classic', 'otan', 'hybrid'], true)) {
            $preferredGradeFormat = 'classic';
        }
        $professionalCategoryCode = trim((string) $request->input('professional_category_code')) ?: null;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Une adresse e-mail valide est requise.');
            return Response::redirect($createUrl);
        }
        if ($firstName === '' || $lastName === '') {
            Session::flash('error', 'Le prénom et le nom du personnage sont requis.');
            return Response::redirect($createUrl);
        }
        if ($this->userRepository->emailExistsInTenant($tenantId, $email)) {
            Session::flash('error', 'Cet email est déjà utilisé.');
            return Response::redirect($createUrl);
        }

        $steamResult = $this->resolveSteamIdInput((string) $request->input('steam_id', ''), $tenantId);
        if ($steamResult['ok'] === false) {
            Session::flash('error', $steamResult['error']);

            return Response::redirect($createUrl);
        }
        $steamId = $steamResult['steam_id'];

        $gate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
        if (!$gate->canAddMember($tenantId)) {
            Session::flash('error', 'Limite de membres du plan atteinte.');
            return Response::redirect($createUrl);
        }
        $passwordPlaceholder = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);
        $userId = $this->userRepository->create($tenantId, [
            'email' => $email,
            'password_hash' => $passwordPlaceholder,
            'display_name' => $displayName !== '' ? $displayName : null,
            'callsign' => $callsign ?: null,
            'role_id' => $primaryRoleId,
            'grade_id' => $gradeId,
            'status' => 'pending_verification',
            'nationality_code' => $nationalityCode,
            'preferred_grade_format' => $preferredGradeFormat,
            'professional_category_code' => $professionalCategoryCode,
        ]);
        $this->userProfileRepository->upsert($userId, [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        try {
            $this->personnelProfileRepository->ensureRecord($userId);
            $this->personnelProfileRepository->update($userId, [
                'character_name' => $displayName,
            ]);
        } catch (\Throwable) {
        }
        try {
            $this->userRepository->syncOrganizationRoles($userId, $tenantId, $roleIds, $actorUserId);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect($createUrl);
        }

        $steamNotes = [];
        if ($steamId !== null) {
            $this->userRepository->update($userId, $tenantId, ['steam_id' => $steamId]);
            if ($request->input('sync_steam_profile') === '1') {
                $steamNotes = $this->applySteamProfileSync($userId, $tenantId, $steamId);
            }
        }

        $this->passwordResetRepository->deleteExpired();
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires = new \DateTimeImmutable('+' . self::SETUP_TOKEN_HOURS . ' hours');
        $this->passwordResetRepository->create($userId, $tokenHash, $expires);

        $setupUrl = url('reset-password') . '?token=' . rawurlencode($rawToken);
        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenantRow ? (string) ($tenantRow['name'] ?? 'Communauté') : 'Communauté';
        $sent = false;
        if ($this->userNotificationPreferencesRepository->isEmailEventEnabled($userId, EmailEvents::TENANT_USER_SETUP)) {
            $sent = $this->emailService->sendTenantUserSetupInvite(
                $email,
                $setupUrl,
                self::SETUP_TOKEN_HOURS,
                $tenantName,
                $tenantId
            );
        }

        $this->adminAuditService->logUserCreated($tenantId, $actorUserId, $userId, $email);
        if ($sent) {
            $ok = 'Compte créé. Un e-mail a été envoyé à ' . $email . ' avec un lien pour définir le mot de passe (valide ' . self::SETUP_TOKEN_HOURS . ' h). Le compte sera actif après cette étape.';
            if ($steamNotes !== []) {
                $ok .= ' ' . implode(' ', $steamNotes);
            }
            Session::flash('success', $ok);
        } else {
            $msg = 'Compte créé, mais l’e-mail d’invitation n’a pas pu être envoyé. Vous pouvez utiliser « Mot de passe oublié » sur la page de connexion pour régénérer un lien, ou vérifier la configuration e-mail.';
            if ($steamNotes !== []) {
                $msg .= ' ' . implode(' ', $steamNotes);
            }
            $detail = $this->emailService->getLastSendError();
            if ($detail !== null && $detail !== '') {
                $clean = preg_replace('/\s+/u', ' ', $detail) ?? $detail;
                $msg .= ' Détail : ' . (function_exists('mb_substr') ? mb_substr($clean, 0, 300) : substr($clean, 0, 300));
            }
            Session::flash('error', $msg);
        }

        return Response::redirect(url('back-office/users/' . $userId));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('back-office/users'));
        }
        $userProfile = $this->userProfileRepository->getByUserId($id);
        $legalIdentity = $this->userLegalIdentityRepository->getByUserId($id) ?? [];
        if ($legalIdentity !== []) {
            $userProfile = is_array($userProfile) ? $userProfile : [];
            $userProfile['first_name'] = $legalIdentity['first_name'] ?? ($userProfile['first_name'] ?? '');
            $userProfile['last_name'] = $legalIdentity['last_name'] ?? ($userProfile['last_name'] ?? '');
            $userProfile['phone'] = $legalIdentity['phone'] ?? ($userProfile['phone'] ?? '');
            $userProfile['birth_date'] = $legalIdentity['birth_date'] ?? ($userProfile['birth_date'] ?? '');
            $userProfile['nationality'] = $legalIdentity['nationality'] ?? ($userProfile['nationality'] ?? '');
        }
        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $roleMatrix = $this->roleRepository->organizationRolesPermissionMatrix($tenantId);
        $selectedRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
        if ($selectedRoleIds === [] && !empty($user['role_id'])) {
            $selectedRoleIds = [(int) $user['role_id']];
        }
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeCategories = $this->gradeCategoryRepository->listActive();
        $gradeValidationIssues = $this->gradeValidationService->validateUserProfile($user);
        $positions = $this->positionRepository->listForTenant($tenantId);
        $userActivePositions = $this->positionRepository->listActiveForUser($tenantId, $id);
        $roleSets = $this->roleSetRepository->listForTenant($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.organization.users.edit',
            'title' => 'Modifier le compte',
            'user' => $user,
            'userProfile' => $userProfile,
            'isServiceAccount' => $this->userRepository->isServiceAccount($id),
            'roles' => $roles,
            'roleMatrix' => $roleMatrix,
            'selectedRoleIds' => $selectedRoleIds,
            'grades' => $grades,
            'gradeCategories' => $gradeCategories,
            'gradeValidationIssues' => $gradeValidationIssues,
            'positionsList' => $positions,
            'userActivePositions' => $userActivePositions,
            'roleSetsList' => $roleSets,
            'organizationRoleLabelMode' => $this->organizationRoleLabelModeForTenant($tenantId),
            'steamWebConfigured' => $this->steamWebApiService->isConfigured(),
            'backOfficePageCss' => ['back-office-users.css'],
            'showPortalFooter' => false,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        $editUrl = url('back-office/users/' . $id . '/edit');
        if (!$tenantId || !$actorUserId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');
            return Response::redirect(url('back-office/users'));
        }

        $data = [];
        $syncRoles = $request->input('user_roles_form') === '1';
        $roleIds = [];
        $oldRoleIds = [];

        $identityTouched = $request->input('first_name') !== null || $request->input('last_name') !== null;
        $firstName = trim((string) $request->input('first_name'));
        $lastName = trim((string) $request->input('last_name'));
        if ($identityTouched) {
            if ($firstName === '' || $lastName === '') {
                Session::flash('error', 'Le prénom et le nom du personnage sont requis.');

                return Response::redirect($editUrl);
            }
            if (function_exists('mb_substr')) {
                $firstName = mb_substr($firstName, 0, 100);
                $lastName = mb_substr($lastName, 0, 100);
            } else {
                $firstName = substr($firstName, 0, 100);
                $lastName = substr($lastName, 0, 100);
            }
            $displayName = trim($firstName . ' ' . $lastName);
            if (function_exists('mb_substr')) {
                $displayName = mb_substr($displayName, 0, 160);
            } else {
                $displayName = substr($displayName, 0, 160);
            }
            $data['display_name'] = $displayName !== '' ? $displayName : null;
        }

        if ($request->input('callsign') !== null) {
            $callsign = trim((string) $request->input('callsign'));
            if (function_exists('mb_substr')) {
                $callsign = mb_substr($callsign, 0, 80);
            } else {
                $callsign = substr($callsign, 0, 80);
            }
            if ($callsign !== '' && $this->userRepository->callsignExistsInTenant($tenantId, $callsign, $id)) {
                Session::flash('error', 'Cet indicatif est déjà utilisé par un autre membre de la communauté.');

                return Response::redirect($editUrl);
            }
            $data['callsign'] = $callsign !== '' ? $callsign : null;
        }

        if ($request->input('email') !== null) {
            $email = trim((string) $request->input('email'));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Session::flash('error', 'Une adresse e-mail valide est requise.');

                return Response::redirect($editUrl);
            }
            if ($this->userRepository->emailExistsInTenant($tenantId, $email, $id)) {
                Session::flash('error', 'Cette adresse e-mail est déjà utilisée.');

                return Response::redirect($editUrl);
            }
            $data['email'] = $email;
        }

        if ($syncRoles) {
            $roleIds = $this->parseRoleIdsFromRequest($request);
            $oldRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
            if ($oldRoleIds === [] && !empty($user['role_id'])) {
                $oldRoleIds = [(int) $user['role_id']];
            }
            foreach ($roleIds as $rid) {
                if (!$this->roleRepository->canAssignInTenantAdminContext($rid, $tenantId)) {
                    Session::flash('error', 'Un rôle sélectionné ne peut pas être attribué depuis l’administration communauté.');

                    return Response::redirect($editUrl);
                }
            }
            $ownerRoleId = $this->roleRepository->getIdBySlug($tenantId, 'community_owner');
            if ($ownerRoleId !== null) {
                $hadOwner = in_array($ownerRoleId, $oldRoleIds, true);
                $hasOwnerNew = in_array($ownerRoleId, $roleIds, true);
                if ($hadOwner && !$hasOwnerNew) {
                    $count = $this->userRepository->countUsersWithRole($ownerRoleId);
                    if ($count <= 1) {
                        Session::flash('error', 'Impossible de retirer le rôle propriétaire communauté au dernier titulaire.');

                        return Response::redirect($editUrl);
                    }
                }
            }
        }

        if ($request->input('grade_id') !== null) {
            $rawGrade = $request->input('grade_id');
            $gradeId = $rawGrade !== '' && $rawGrade !== null ? (int) $rawGrade : null;
            if ($gradeId !== null && $gradeId > 0) {
                $allowedGradeIds = array_map(
                    static fn (array $g): int => (int) ($g['id'] ?? 0),
                    $this->gradeRepository->listForTenant($tenantId)
                );
                if (!in_array($gradeId, $allowedGradeIds, true)) {
                    Session::flash('error', 'Le grade sélectionné n’est pas disponible pour cette communauté.');

                    return Response::redirect($editUrl);
                }
                $data['grade_id'] = $gradeId;
            } else {
                $data['grade_id'] = null;
            }
        }

        if ($request->input('status') !== null) {
            $st = trim((string) $request->input('status'));
            if ($st === 'pending') {
                $st = 'pending_verification';
            }
            if (!in_array($st, ['active', 'inactive', 'pending_verification'], true)) {
                Session::flash('error', 'Statut du compte non reconnu.');

                return Response::redirect($editUrl);
            }
            if ($st === 'inactive' && $id === $actorUserId) {
                Session::flash('error', 'Vous ne pouvez pas désactiver votre propre compte.');

                return Response::redirect($editUrl);
            }
            if ($st === 'inactive') {
                $ownerRoleId = $this->roleRepository->getIdBySlug($tenantId, 'community_owner');
                if ($ownerRoleId !== null && $this->userRepository->userHasTenantRole($id, $ownerRoleId)) {
                    $count = $this->userRepository->countUsersWithRole($ownerRoleId);
                    if ($count <= 1) {
                        Session::flash('error', 'Impossible de désactiver le dernier propriétaire communauté.');

                        return Response::redirect($editUrl);
                    }
                }
            }
            $data['status'] = $st;
        }

        if ($request->input('nationality_code') !== null) {
            $v = trim((string) $request->input('nationality_code'));
            if ($v !== '' && !in_array($v, ['FR', 'US'], true)) {
                Session::flash('error', 'La nationalité / doctrine choisie n’est pas reconnue.');

                return Response::redirect($editUrl);
            }
            $data['nationality_code'] = $v !== '' ? $v : null;
        }

        if ($request->input('preferred_grade_format') !== null) {
            $v = trim((string) $request->input('preferred_grade_format'));
            $data['preferred_grade_format'] = in_array($v, ['classic', 'otan', 'hybrid'], true) ? $v : 'classic';
        }

        if ($request->input('professional_category_code') !== null) {
            $v = trim((string) $request->input('professional_category_code'));
            if ($v !== '' && $this->gradeCategoryRepository->findByCode($v) === null) {
                Session::flash('error', 'La catégorie de personnel choisie n’est pas reconnue.');

                return Response::redirect($editUrl);
            }
            $data['professional_category_code'] = $v !== '' ? $v : null;
        }

        $password = $request->input('password');
        if ($password !== null && $password !== '') {
            if (strlen((string) $password) < 6) {
                Session::flash('error', 'Le nouveau mot de passe doit contenir au moins 6 caractères.');

                return Response::redirect($editUrl);
            }
            $data['password_hash'] = password_hash((string) $password, PASSWORD_ARGON2ID);
        }

        $steamNotes = [];
        if ($request->input('steam_id') !== null) {
            $steamResult = $this->resolveSteamIdInput((string) $request->input('steam_id'), $tenantId, $id);
            if ($steamResult['ok'] === false) {
                Session::flash('error', $steamResult['error']);

                return Response::redirect($editUrl);
            }
            $data['steam_id'] = $steamResult['steam_id'];
        }

        $updatedUser = array_merge($user, $data);
        $gradeValidationIssues = $this->gradeValidationService->validateUserProfile($updatedUser);
        if ($this->gradeValidationService->hasErrors($gradeValidationIssues)) {
            foreach ($gradeValidationIssues as $i) {
                if (($i['type'] ?? '') === 'error') {
                    Session::flash('error', $i['message']);
                    return Response::redirect($editUrl);
                }
            }
        }

        $rolesSynced = false;
        $actorLabel = trim((string) Session::get('display_name'));

        if ($syncRoles) {
            $rolesChanged = count($roleIds) !== count($oldRoleIds)
                || array_diff($roleIds, $oldRoleIds) !== []
                || array_diff($oldRoleIds, $roleIds) !== [];
            if ($rolesChanged) {
                try {
                    $this->userRepository->syncOrganizationRoles($id, $tenantId, $roleIds, $actorUserId);
                } catch (\InvalidArgumentException $e) {
                    Session::flash('error', $e->getMessage());

                    return Response::redirect($editUrl);
                }
                $this->adminAuditService->logRoleAssigned(
                    $tenantId,
                    $actorUserId,
                    $id,
                    $oldRoleIds !== [] ? implode(',', $oldRoleIds) : null,
                    $roleIds !== [] ? implode(',', $roleIds) : null
                );
                $rolesSynced = true;
                $userAfterRoles = $this->userRepository->findById($id, $tenantId) ?? [];
                $historyNewOrgRoleIds = $this->userRepository->listOrganizationRoleIdsForUser($id);
                if ($historyNewOrgRoleIds === [] && !empty($userAfterRoles['role_id'])) {
                    $historyNewOrgRoleIds = [(int) $userAfterRoles['role_id']];
                }
                $this->personnelOrgHistoryRecorder->recordOrganizationRolesChange(
                    $tenantId,
                    $id,
                    $actorUserId,
                    $actorLabel,
                    $oldRoleIds,
                    $historyNewOrgRoleIds
                );
            }
        }

        // Ne persister que les champs réellement modifiés
        $changed = [];
        foreach ($data as $key => $value) {
            if ($key === 'password_hash') {
                $changed[$key] = $value;
                continue;
            }
            $before = $user[$key] ?? null;
            if ($key === 'grade_id') {
                $beforeNorm = $before !== null && $before !== '' ? (int) $before : null;
                $afterNorm = $value !== null && $value !== '' ? (int) $value : null;
            } else {
                $beforeNorm = $before === null || $before === '' ? null : (string) $before;
                $afterNorm = $value === null || $value === '' ? null : (string) $value;
            }
            if ($beforeNorm !== $afterNorm) {
                $changed[$key] = $value;
            }
        }
        $data = $changed;

        $structureBefore = null;
        if (array_key_exists('grade_id', $data)) {
            $structureBefore = $this->structureChangeNotification->snapshot($tenantId, $id);
        }

        if (!empty($data)) {
            $auditKeys = ['email', 'grade_id', 'status', 'nationality_code', 'preferred_grade_format', 'professional_category_code', 'display_name', 'callsign', 'profile_slug', 'steam_id'];
            $passwordWillChange = isset($data['password_hash']);
            $beforeAugmented = array_merge($user, ['connexion_mot_de_passe' => false]);
            $keys = $auditKeys;
            if ($passwordWillChange) {
                $keys[] = 'connexion_mot_de_passe';
            }
            $this->userRepository->update($id, $tenantId, $data);
            $afterUser = $this->userRepository->findById($id, $tenantId) ?? [];
            $afterAugmented = array_merge($afterUser, ['connexion_mot_de_passe' => $passwordWillChange]);
            [$o, $n] = AuditFieldSnapshot::diffOnly($beforeAugmented, $afterAugmented, $keys);
            [$os, $ns] = AuditFieldSnapshot::encodePair($o, $n);
            $this->adminAuditService->logUserUpdated($tenantId, $actorUserId, $id, $os, $ns);
        }

        $wantSteamSync = $request->input('sync_steam_profile') === '1';
        if ($wantSteamSync) {
            $finalSteamId = '';
            if (array_key_exists('steam_id', $data)) {
                $finalSteamId = trim((string) ($data['steam_id'] ?? ''));
            } else {
                $finalSteamId = trim((string) ($user['steam_id'] ?? ''));
            }
            if ($finalSteamId === '') {
                Session::flash('error', 'Indiquez un identifiant Steam avant de synchroniser le profil public.');

                return Response::redirect($editUrl);
            }
            $steamNotes = $this->applySteamProfileSync($id, $tenantId, $finalSteamId);
        }

        if ($identityTouched) {
            $this->userProfileRepository->upsert($id, [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);
            try {
                $this->personnelProfileRepository->ensureRecord($id);
                $this->personnelProfileRepository->update($id, [
                    'character_name' => trim($firstName . ' ' . $lastName),
                ]);
            } catch (\Throwable) {
            }
        }

        if ($structureBefore !== null) {
            try {
                $this->structureChangeNotification->notifyFromSnapshots(
                    $tenantId,
                    $id,
                    $actorUserId,
                    $structureBefore,
                    $this->structureChangeNotification->snapshot($tenantId, $id)
                );
            } catch (\Throwable) {
            }
        }

        if ($rolesSynced || !empty($data)) {
            $final = $this->userRepository->findById($id, $tenantId) ?? [];
            if ($final !== []) {
                $this->personnelOrgHistoryRecorder->recordUserTableDiff($tenantId, $user, $final, $actorUserId, $actorLabel);
            }
        }

        $extraSteam = $steamNotes !== [] ? ' ' . implode(' ', $steamNotes) : '';
        if (!empty($data) && $rolesSynced) {
            Session::flash('success', 'Compte et rôles enregistrés.' . $extraSteam);
        } elseif (!empty($data) || $identityTouched) {
            Session::flash('success', 'Compte mis à jour.' . $extraSteam);
        } elseif ($rolesSynced) {
            Session::flash('success', 'Rôles mis à jour.' . $extraSteam);
        } elseif ($wantSteamSync && $steamNotes !== []) {
            Session::flash('success', implode(' ', $steamNotes));
        } else {
            Session::flash('warning', 'Aucun changement à enregistrer.');

            return Response::redirect($editUrl);
        }

        return Response::redirect(url('back-office/users/' . $id));
    }

    public function assignPosition(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        $editUrl = url('back-office/users/' . $id . '/edit');
        if (!$tenantId || !$actorUserId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($editUrl);
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(url('back-office/users'));
        }
        if ($this->userRepository->isServiceAccount($id)) {
            Session::flash('error', 'Les comptes techniques ne peuvent pas recevoir d’affectation de poste.');

            return Response::redirect($editUrl);
        }
        $positionId = (int) $request->input('position_id', 0);
        $startsAt = trim((string) $request->input('starts_at', ''));
        $endsAt = trim((string) $request->input('ends_at', ''));
        if ($positionId < 1 || $startsAt === '') {
            Session::flash('error', 'Choisissez un poste et une date de début.');

            return Response::redirect($editUrl);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsAt)) {
            Session::flash('error', 'La date de début n’est pas valide.');

            return Response::redirect($editUrl);
        }
        if ($endsAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsAt)) {
            Session::flash('error', 'La date de fin n’est pas valide.');

            return Response::redirect($editUrl);
        }
        if ($endsAt !== '' && $endsAt < $startsAt) {
            Session::flash('error', 'La date de fin doit être postérieure ou égale à la date de début.');

            return Response::redirect($editUrl);
        }
        $knownPositionIds = array_map(
            static fn (array $p): int => (int) ($p['id'] ?? 0),
            $this->positionRepository->listForTenant($tenantId)
        );
        if (!in_array($positionId, $knownPositionIds, true)) {
            Session::flash('error', 'Ce poste n’appartient pas à votre communauté.');

            return Response::redirect($editUrl);
        }
        $ok = $this->positionRepository->assignUser($tenantId, $id, $positionId, $startsAt, $endsAt !== '' ? $endsAt : null, $actorUserId);
        if (!$ok) {
            Session::flash('error', 'Impossible d’enregistrer l’affectation.');

            return Response::redirect($editUrl);
        }

        $positionRow = $this->positionRepository->findForTenant($tenantId, $positionId);
        $packApplied = false;
        $setId = (int) ($positionRow['default_role_set_id'] ?? 0);
        if ($setId > 0) {
            $extraIds = $this->roleSetRepository->roleIdsForSet($tenantId, $setId);
            if ($extraIds !== []) {
                $canApply = true;
                foreach ($extraIds as $rid) {
                    if (!$this->roleRepository->canAssignInTenantAdminContext($rid, $tenantId)) {
                        $canApply = false;
                        break;
                    }
                }
                if ($canApply) {
                    $current = $this->userRepository->listOrganizationRoleIdsForUser($id);
                    if ($current === [] && !empty($user['role_id'])) {
                        $current = [(int) $user['role_id']];
                    }
                    $merged = array_values(array_unique(array_merge($current, $extraIds)));
                    try {
                        $this->userRepository->syncOrganizationRoles($id, $tenantId, $merged, $actorUserId);
                        $packApplied = true;
                    } catch (\InvalidArgumentException) {
                        $packApplied = false;
                    }
                }
            }
        }

        $msg = 'Affectation de poste enregistrée.';
        if ($packApplied) {
            $msg .= ' Le pack d’habilitations associé au poste a également été appliqué.';
        } elseif ($setId > 0) {
            $msg .= ' Le pack d’habilitations associé n’a pas pu être appliqué automatiquement : vérifiez-le dans la section dédiée.';
        }
        Session::flash('success', $msg);

        return Response::redirect($editUrl);
    }

    public function applyRoleSet(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        $editUrl = url('back-office/users/' . $id . '/edit');
        if (!$tenantId || !$actorUserId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($editUrl);
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Membre introuvable.');

            return Response::redirect(url('back-office/users'));
        }
        if ($this->userRepository->isServiceAccount($id)) {
            Session::flash('error', 'Les packs de rôles ne s’appliquent pas aux comptes techniques.');

            return Response::redirect($editUrl);
        }
        $setId = (int) $request->input('role_set_id', 0);
        if ($setId < 1) {
            Session::flash('error', 'Choisissez un pack de rôles.');

            return Response::redirect($editUrl);
        }
        $extraIds = $this->roleSetRepository->roleIdsForSet($tenantId, $setId);
        if ($extraIds === []) {
            Session::flash('error', 'Ce pack ne contient aucun rôle utilisable.');

            return Response::redirect($editUrl);
        }
        $current = $this->userRepository->listOrganizationRoleIdsForUser($id);
        if ($current === [] && !empty($user['role_id'])) {
            $current = [(int) $user['role_id']];
        }
        foreach ($extraIds as $rid) {
            if (!$this->roleRepository->canAssignInTenantAdminContext($rid, $tenantId)) {
                Session::flash('error', 'Un rôle du pack ne peut pas être attribué depuis l’administration communauté.');

                return Response::redirect($editUrl);
            }
        }
        $merged = array_values(array_unique(array_merge($current, $extraIds)));
        try {
            $this->userRepository->syncOrganizationRoles($id, $tenantId, $merged, $actorUserId);
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect($editUrl);
        }
        $this->adminAuditService->logRoleAssigned(
            $tenantId,
            $actorUserId,
            $id,
            $current !== [] ? implode(',', $current) : null,
            $merged !== [] ? implode(',', $merged) : null
        );
        $actorLabel = trim((string) Session::get('display_name'));
        $this->personnelOrgHistoryRecorder->recordOrganizationRolesChange(
            $tenantId,
            $id,
            $actorUserId,
            $actorLabel,
            $current,
            $merged
        );
        Session::flash('success', 'Pack de rôles appliqué (sans retirer les rôles déjà présents).');

        return Response::redirect($editUrl);
    }

    public function deactivate(Request $request, array $params = []): Response
    {
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/users'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $actorUserId = (int) Session::get('user_id');
        $id = (int) ($params['id'] ?? 0);
        if (!$tenantId || !$actorUserId || !$id) {
            return Response::redirect(url('back-office/users'));
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');
            return Response::redirect(url('back-office/users'));
        }
        $ownerRoleId = $this->roleRepository->getIdBySlug($tenantId, 'community_owner');
        if ($ownerRoleId !== null && $this->userRepository->userHasTenantRole($id, $ownerRoleId)) {
            $count = $this->userRepository->countUsersWithRole($ownerRoleId);
            if ($count <= 1) {
                Session::flash('error', 'Impossible de désactiver le dernier propriétaire communauté.');

                return Response::redirect(url('back-office/users/' . $id));
            }
        }
        $this->userRepository->update($id, $tenantId, ['status' => 'inactive']);
        $this->adminAuditService->logUserDeactivated($tenantId, $actorUserId, $id);
        $successMsg = 'Utilisateur désactivé.';
        if ($request->input('block_email_rejoin') === '1') {
            $memberEmail = strtolower(trim((string) ($user['email'] ?? '')));
            if ($memberEmail !== '' && filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    $this->indicatorBlocklist->addEmailBlock(
                        $actorUserId,
                        'tenant',
                        $tenantId,
                        $memberEmail,
                        'Suite au retrait d’accès à la communauté',
                        null,
                        null
                    );
                    $successMsg .= ' L’adresse e-mail ne pourra plus servir à rejoindre cette communauté.';
                } catch (\Throwable) {
                    Session::flash('warning', 'Accès retiré, mais la consigne sur l’adresse e-mail n’a pas pu être enregistrée. Ajoutez-la depuis la page Modération si besoin.');
                }
            }
        }
        Session::flash('success', $successMsg);

        return Response::redirect(url('back-office/users'));
    }

    public function resendVerificationEmail(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('back-office/users'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('back-office/users'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $returnList = $request->input('_return') === 'list';
        $backUsers = url('back-office/users');
        $backUser = url('back-office/users/' . $id);
        if (!$tenantId || !$id) {
            return Response::redirect($backUsers);
        }
        $user = $this->userRepository->findById($id, $tenantId);
        if (!$user) {
            Session::flash('error', 'Utilisateur introuvable.');

            return Response::redirect($backUsers);
        }
        if ($this->userRepository->isServiceAccount($id)) {
            Session::flash('error', 'Cette action ne s’applique pas aux comptes techniques.');

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        if (($user['status'] ?? '') !== 'pending_verification') {
            Session::flash('error', 'Le renvoi du lien de confirmation n’est utile que pour un compte en attente de vérification de l’e-mail.');

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        $last = $this->emailTokenRepository->getLatestTokenCreatedAtForUserPurpose($id, EmailTokenPurpose::REGISTER_CONFIRM);
        if ($last !== null && (time() - $last->getTimestamp()) < self::RESEND_VERIFICATION_COOLDOWN_SEC) {
            $wait = self::RESEND_VERIFICATION_COOLDOWN_SEC - (time() - $last->getTimestamp());
            Session::flash(
                'error',
                'Un e-mail a déjà été envoyé récemment. Veuillez patienter encore environ ' . max(1, $wait) . ' seconde(s) avant de renvoyer.'
            );

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        $tenantRow = $this->tenantRepository->findById($tenantId);
        $tenantName = (string) ($tenantRow['name'] ?? 'Communauté');
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            Session::flash('error', 'Ce compte n’a pas d’adresse e-mail : impossible d’envoyer le lien.');

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires = new \DateTimeImmutable('+15 minutes');
        $verifyUrl = url('verify-email') . '?token=' . rawurlencode($rawToken);
        $ok = $this->emailService->sendUserRegisterConfirmation(
            $email,
            (string) ($user['display_name'] ?? 'Membre'),
            $tenantName,
            $verifyUrl,
            15,
            $tenantId
        );
        if (!$ok) {
            Session::flash(
                'error',
                'L’e-mail n’a pas pu être envoyé. Vérifiez la configuration d’envoi des courriels ou réessayez plus tard.'
            );

            return Response::redirect($returnList ? $backUsers : $backUser);
        }
        $this->emailTokenRepository->deletePendingForUserPurpose($id, EmailTokenPurpose::REGISTER_CONFIRM);
        $this->emailTokenRepository->create(
            $tenantId,
            $id,
            EmailTokenPurpose::REGISTER_CONFIRM,
            $tokenHash,
            bin2hex(random_bytes(16)),
            $expires
        );
        $notice = \email_file_mailer_notice();
        if ($notice !== '') {
            Session::flash('warning', $notice);
        }
        Session::flash(
            'success',
            'Un nouveau lien de confirmation a été envoyé à l’adresse du compte. Demandez au membre de vérifier sa boîte e-mail (et les courriers indésirables).'
        );

        return Response::redirect($returnList ? $backUsers : $backUser);
    }
}
