<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Repositories\TenantGradeOverrideRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Moderation\SystemModeratorAccountService;
use App\Support\SqlText;
use PDO;

final class TenantBootstrapService
{
    /** Codes communauté interdits (routes / confusion). */
    private const RESERVED_COMMUNITY_CODES = [
        'JOIN', 'LOGIN', 'REGISTER', 'API', 'ADMIN', 'C', 'DASHBOARD', 'HUB', 'FORUM', 'SYSTEM',
        'DEFAULT', 'WWW', 'ENLISTMENT', 'COMMUNITIES', 'INVITATIONS', 'LOGOUT', 'ACCOUNT', 'ATAK',
    ];

    public function __construct(
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private \App\Repositories\ReferralRepository $referralRepository,
        private SystemModeratorAccountService $systemModeratorAccountService
    ) {}

    /**
     * Crée une communauté (tenant), seeds forum/documents, duplique le créateur comme propriétaire communauté (rôle site séparé).
     *
     * @return array{tenant_id: int, user_id: int}
     */
    public function createCommunity(int $creatorUserId, string $name, string $slug, array $options = []): array
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            $base = TenantSlugService::normalizeFromName($name);
            $slug = TenantSlugService::ensureUnique($base, fn (string $s) => $this->tenantRepository->slugExists($s));
        } else {
            if (!TenantSlugService::isValidFormat($slug)) {
                throw new \InvalidArgumentException('L’adresse courte ne peut contenir que des lettres minuscules, des chiffres et des tirets (50 caractères maximum).');
            }
            if (TenantSlugService::isReserved($slug)) {
                throw new \InvalidArgumentException('Cette adresse courte est réservée.');
            }
            if ($this->tenantRepository->slugExists($slug)) {
                throw new \RuntimeException('Une communauté utilise déjà cette adresse courte.');
            }
        }

        $pdo = Database::getPdo();
        $pdo->beginTransaction();
        try {
            $planSlug = $this->normalizePlanSlug((string) ($options['plan_slug'] ?? 'free'));
            $tenantType = TenantTypeConfig::normalizeType((string) ($options['tenant_type'] ?? 'full'));
            $tenantId = $this->tenantRepository->create($name, $slug, $planSlug, $tenantType);

            $gov = \App\Services\Community\TenantDefaultRoleDefinitions::governanceRoles();
            $co = $gov[0];
            $ta = $gov[1];
            $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, 1, 1, \'community\', NOW())')
                ->execute([$tenantId, $co['name'], $co['slug'], $co['description']]);
            $communityOwnerRoleId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, 1, 0, \'community\', NOW())')
                ->execute([$tenantId, $ta['name'], $ta['slug'], $ta['description']]);
            $tenantAdminRoleId = (int) $pdo->lastInsertId();

            $wizard = is_array($options['wizard_normalized'] ?? null) ? $options['wizard_normalized'] : null;
            $gradeSystemCode = $wizard !== null ? (string) $wizard['grade_system_code'] : 'FR_CLASSIC';
            $founderGradeId = $wizard !== null ? (int) $wizard['founder_grade_id'] : 0;
            $rolesTemplate = $wizard !== null ? (string) $wizard['roles_template'] : 'quick';

            $gradeId = $this->resolveDefaultGradeIdForNewCommunity($pdo, $tenantId, $gradeSystemCode, $founderGradeId);

            $seedConfig = TenantTypeConfig::getSeedConfig($tenantType);

            if ($tenantType === TenantTypeConfig::TYPE_FULL) {
                TenantSeedHelper::seedForumAndRoles($pdo, $tenantId);
                TenantSeedHelper::ensureOrganizationForumSection($pdo, $tenantId);
                TenantSeedHelper::seedDocumentsEquipment($pdo, $tenantId);
                TenantSeedHelper::ensureSystemAdminPermissions($pdo, $tenantId);
                TenantSeedHelper::ensureTenantPermissionCatalog($pdo, $tenantId);
                if ($wizard !== null) {
                    TenantSeedHelper::applyWizardCommunityRoles($pdo, $tenantId, $rolesTemplate);
                    $customRoles = $wizard['custom_roles'] ?? [];
                    if (is_array($customRoles) && $customRoles !== []) {
                        TenantSeedHelper::applyWizardCustomRoles($pdo, $tenantId, $customRoles);
                    }
                }
                TenantSeedHelper::ensurePersonnelPanelsAndMatricule($pdo, $tenantId);
                (new \App\Services\Personnel\PersonnelJobRoleBootstrapService(
                    new \App\Repositories\PersonnelJobRoleRepository()
                ))->ensureDefaultsForTenant($pdo, $tenantId);
            } else {
                $this->seedSimplifiedTenant($pdo, $tenantId, $tenantType, $communityOwnerRoleId, $tenantAdminRoleId);
                if (!empty($seedConfig['seed_forum'])) {
                    TenantSeedHelper::seedForumAndRoles($pdo, $tenantId);
                    TenantSeedHelper::ensureOrganizationForumSection($pdo, $tenantId);
                }
            }

            $st = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, permission_id FROM role_permissions WHERE role_id = ?');
            $st->execute([$communityOwnerRoleId, $tenantAdminRoleId]);

            $newUserId = $this->userRepository->cloneUserToTenant($creatorUserId, $tenantId, $communityOwnerRoleId, $gradeId);

            TenantSeedHelper::ensureOnboardingPortalCourse($pdo, $tenantId, $newUserId);
            TenantSeedHelper::ensureRolesOrgCourse($pdo, $tenantId, $newUserId);
            TenantSeedHelper::ensureBureauRecrutementCourse($pdo, $tenantId, $newUserId);
            TenantSeedHelper::ensureAtakCourse($pdo, $tenantId, $newUserId);

            $this->tenantRepository->setOwner($tenantId, $newUserId);
            $communitySettings = [
                'registration_mode' => TenantCommunityProfileService::normalizeRegistrationMode($options['registration_mode'] ?? TenantCommunityProfileService::REGISTRATION_MODE_MILSIM),
                'community_locked' => !empty($options['community_locked']),
                'require_ai_ack' => array_key_exists('require_ai_ack', $options) ? (bool) $options['require_ai_ack'] : true,
                'refuse_other_community_members' => !empty($options['refuse_other_community_members']),
                'welcome_text' => trim((string) ($options['welcome_text'] ?? '')),
            ];
            $phs = trim((string) ($options['public_hero_subtitle'] ?? ''));
            if ($phs !== '') {
                $communitySettings['public_hero_subtitle'] = mb_substr($phs, 0, 600);
            }
            $pdoctrine = trim((string) ($options['public_doctrine'] ?? ''));
            if ($pdoctrine !== '') {
                $communitySettings['public_doctrine'] = mb_substr($pdoctrine, 0, 200);
            }
            if ($wizard !== null) {
                $communitySettings['default_locale'] = (string) $wizard['default_locale'];
                $communitySettings['orbat_visibility'] = (string) $wizard['orbat_visibility'];
                $communitySettings['default_guest_role_slug'] = 'invite';
                $cp = $wizard['community_profile'] ?? [];
                if (is_array($cp) && $cp !== []) {
                    $logoUrl = isset($cp['logo_url']) ? trim((string) $cp['logo_url']) : '';
                    unset($cp['logo_url']);
                    if ($logoUrl !== '') {
                        $this->tenantRepository->updateLogoUrl($tenantId, $logoUrl);
                    }
                    foreach ($cp as $k => $v) {
                        if ($v === null) {
                            continue;
                        }
                        if ($k === 'enlistment_milsim' && !is_array($v)) {
                            continue;
                        }
                        if (is_string($v) && trim($v) === '' && $k !== 'simple_body' && $k !== 'expectations') {
                            continue;
                        }
                        if ($v === [] && $k !== 'style_badges' && $k !== 'enlistment_milsim') {
                            continue;
                        }
                        $communitySettings[$k] = $v;
                    }
                }
            }
            $ppl = (string) ($options['public_page_layout'] ?? '');
            if ($ppl === 'showcase' || $ppl === 'legacy') {
                $communitySettings['public_page_layout'] = $ppl === 'showcase' ? 'showcase' : 'legacy';
            }
            $this->tenantRepository->updateSettings($tenantId, [
                'community' => $communitySettings,
            ]);
            $aff = $communitySettings['unit_affiliation'] ?? null;
            if (is_array($aff)) {
                try {
                    $ref = \App\Core\Container::get(\App\Services\Community\MilitaryReferentialService::class);
                    if (!empty($aff['is_real'])) {
                        $ref->syncTenantAffiliationsFromCodes(
                            $tenantId,
                            is_array($aff['unit_ids'] ?? null) ? $aff['unit_ids'] : []
                        );
                    } else {
                        $ref->syncTenantAffiliationsFromCodes($tenantId, []);
                    }
                } catch (\Throwable) {
                    // Migrations référentiel absentes
                }
            }
            $merge = [
                'founder_trial_ends_at' => date('c', strtotime('+30 days')),
            ];
            if ($wizard !== null) {
                $merge['grade_system_code'] = $gradeSystemCode;
                $merge['timezone'] = (string) $wizard['timezone'];
                $merge['onboarding_wizard_version'] = 2;
                $merge['onboarding_completed_at'] = date('c');
            }
            $this->tenantRepository->mergeSettings($tenantId, $merge);

            if ($wizard !== null) {
                $this->insertUnitsFromWizard($pdo, $tenantId, $wizard['units']);
                $ov = $wizard['grade_overrides'] ?? [];
                if ($ov !== []) {
                    (new TenantGradeOverrideRepository())->replaceForTenant($tenantId, $ov);
                }
            }

            $communityCode = $this->generateUniqueCommunityCode($slug);
            $this->tenantRepository->updateCommunityCode($tenantId, $communityCode);

            $pdo->commit();

            try {
                $this->systemModeratorAccountService->ensureForTenant($tenantId);
            } catch (\Throwable $e) {
                // Ne pas faire échouer la création communauté si le compte technique est en défaut
            }

            try {
                $realism = new \App\Repositories\AtakRealismRepository();
                $realism->ensureDefaultCryptoDomain($tenantId);
                $atakCfg = new \App\Repositories\TenantAtakConfigRepository();
                $cfg = $atakCfg->getRoleplayConfig($tenantId);
                $cfg['intel_scramble_reviewed'] = true;
                $atakCfg->updateRoleplayConfig($tenantId, $cfg);
            } catch (\Throwable $e) {
                // Schéma realism absent : non bloquant
            }

            try {
                $photoHud = new \App\Services\Media\ReconPhotoHudService();
                $defaults = $photoHud->defaults();
                $defaults['reviewed'] = true;
                $photoHud->put($tenantId, $defaults);
            } catch (\Throwable $e) {
                // Colonne photo_hud_config absente : non bloquant
            }

            try {
                $kitCode = $wizard !== null ? trim((string) ($wizard['catalog_kit_code'] ?? '')) : '';
                if ($kitCode !== '') {
                    $catalog = \App\Core\Container::get(\App\Services\OrganizationCatalog\OrganizationCatalogService::class);
                    $catalog->apply($tenantId, $kitCode, [], $newUserId);
                }
            } catch (\Throwable $e) {
                // Le modèle est optionnel : la communauté reste créée.
            }

            try {
                $tplRepo = \App\Core\Container::get(\App\Repositories\MemberIntegrationTemplateRepository::class);
                $tplRepo->ensureDefaultRecruitTemplate($tenantId, $newUserId);
            } catch (\Throwable $e) {
                // Schéma d’intégration absent : non bloquant
            }

            try {
                $kitSvc = \App\Core\Container::get(\App\Services\Personnel\PersonnelFunctionKitService::class);
                $kitSvc->markReviewedKeepingFullCatalog($tenantId, $newUserId);
            } catch (\Throwable $e) {
                // Table absente : non bloquant
            }

            try {
                $configSvc = \App\Core\Container::get(\App\Services\ConfigurationUpdate\ConfigurationUpdateService::class);
                $configSvc->markSatisfiedForNewTenant($tenantId, $newUserId);
                // Portail SSE : rôles seedés + module prêt — pas d’action humaine obligatoire à la création.
                $configSvc->markCompleted($tenantId, 'SSE_PORTAL_V1', $newUserId);
                // Laboratoire numérique : schéma/UI prêts dès la création (pas de réglage humain requis).
                $configSvc->markCompleted($tenantId, 'SSE_DIGITAL_LAB_V1', $newUserId);
                $configSvc->markCompleted($tenantId, 'SSE_DOMEX_QUEUE_V1', $newUserId);
                $configSvc->markCompleted($tenantId, 'SSE_DOMEX_ZEUS_LIVE_V1', $newUserId);
                $configSvc->markCompleted($tenantId, 'MISSION_PLANNING_V1', $newUserId);
                $configSvc->markCompleted($tenantId, 'AAR_CUSTOM_TEMPLATES_V1', $newUserId);
                $configSvc->markCompleted($tenantId, 'LOGIN_ACCUEIL_IMAGES_V1', $newUserId);
            } catch (\Throwable $e) {
                // Tables absentes ou moteur non déployé : non bloquant
            }

            try {
                $duty = \App\Core\Container::get(\App\Services\Personnel\PersonnelDutyPositionService::class);
                $duty->applyActiveDuty($tenantId, $newUserId, $newUserId);
            } catch (\Throwable $e) {
                // Rôles absents : non bloquant
            }

            $referrerId = isset($options['referrer_user_id']) ? (int) $options['referrer_user_id'] : 0;
            if ($referrerId > 0 && $referrerId !== $creatorUserId) {
                $this->referralRepository->recordAttribution($referrerId, $tenantId, 'community_created');
            }

            try {
                (new \App\Services\Monitoring\ErrorReportMailer())->notifyCommunityCreated([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'slug' => $slug,
                    'community_code' => $communityCode,
                    'plan_slug' => $planSlug,
                    'tenant_type' => $tenantType,
                    'creator_user_id' => $creatorUserId,
                ]);
            } catch (\Throwable) {
                // L’alerte exploitation ne doit pas faire échouer la création.
            }

            return ['tenant_id' => $tenantId, 'user_id' => $newUserId];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Ancien schéma : une ligne `grades` par tenant. Référentiel : `grades` globale (FK grade_systems).
     */
    private function resolveDefaultGradeIdForNewCommunity(PDO $pdo, int $tenantId, string $gradeSystemCode, int $founderGradeId): int
    {
        $stmt = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'tenant_id' LIMIT 1");
        if ($stmt && $stmt->fetchColumn()) {
            $pdo->prepare('INSERT INTO grades (tenant_id, name, short_name, rank_order, created_at) VALUES (?, ?, ?, 10, NOW())')
                ->execute([$tenantId, 'Officer', 'OFR']);

            return (int) $pdo->lastInsertId();
        }

        if ($founderGradeId > 0) {
            $check = $pdo->prepare(
                'SELECT g.id FROM grades g
                 INNER JOIN grade_systems gs ON gs.id = g.grade_system_id
                 WHERE g.id = ? AND gs.code = ? AND g.is_active = 1 LIMIT 1'
            );
            $check->execute([$founderGradeId, $gradeSystemCode]);
            $ok = $check->fetchColumn();
            if ($ok !== false) {
                return (int) $ok;
            }
            throw new \InvalidArgumentException('Le grade sélectionné pour le fondateur ne correspond pas au référentiel choisi.');
        }

        $defaultCode = $gradeSystemCode === 'US_CLASSIC' ? 'CPT' : 'CNE';
        $pick = $pdo->prepare(
            "SELECT g.id FROM grades g
             INNER JOIN grade_systems gs ON gs.id = g.grade_system_id
             WHERE gs.code = ? AND g.code = ? AND g.is_active = 1 LIMIT 1"
        );
        $pick->execute([$gradeSystemCode, $defaultCode]);
        $id = $pick->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $pick = $pdo->prepare(
            'SELECT g.id FROM grades g
             INNER JOIN grade_systems gs ON gs.id = g.grade_system_id
             WHERE gs.code = ? AND g.is_active = 1
             ORDER BY g.sort_order ASC LIMIT 1'
        );
        $pick->execute([$gradeSystemCode]);
        $id = $pick->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $pick = $pdo->query('SELECT id FROM grades WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $id = $pick ? $pick->fetchColumn() : false;
        if ($id !== false) {
            return (int) $id;
        }

        throw new \RuntimeException(
            'Aucun grade référentiel en base. Exécutez les migrations (seed grades FR/US) puis réessayez.'
        );
    }

    /**
     * @param list<array{key: string, parent_key: string, name: string, slug: string, type: string, display_order: int}> $units
     */
    private function insertUnitsFromWizard(PDO $pdo, int $tenantId, array $units): void
    {
        /** @var array<string, int> $keyToId */
        $keyToId = [];
        $remaining = $units;
        $guard = 0;
        while ($remaining !== [] && $guard++ < 1000) {
            $next = [];
            $progress = false;
            foreach ($remaining as $u) {
                $pk = $u['parent_key'] ?? '';
                $parentId = null;
                if ($pk !== '') {
                    if (!isset($keyToId[$pk])) {
                        $next[] = $u;

                        continue;
                    }
                    $parentId = $keyToId[$pk];
                }
                $ins = $pdo->prepare(
                    'INSERT INTO units (tenant_id, parent_id, name, slug, type, code, commander_user_id, display_order, updated_at) VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, NOW())'
                );
                $ins->execute([
                    $tenantId,
                    $parentId,
                    $u['name'],
                    $u['slug'],
                    $u['type'],
                    (int) ($u['display_order'] ?? 0),
                ]);
                $keyToId[$u['key']] = (int) $pdo->lastInsertId();
                $progress = true;
            }
            if (!$progress && $next !== []) {
                throw new \RuntimeException('Impossible de créer l’ORBAT : hiérarchie des unités invalide.');
            }
            $remaining = $next;
        }
        if ($remaining !== []) {
            throw new \RuntimeException('Impossible de créer l’ORBAT : unités restantes non résolues.');
        }
    }

    private function normalizePlanSlug(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === 'premium') {
            return 'standard';
        }
        if (in_array($raw, ['free', 'standard', 'pro', 'pro_plus'], true)) {
            return $raw;
        }

        return 'free';
    }

    private function generateUniqueCommunityCode(string $slug): string
    {
        $base = TenantRepository::normalizeCommunityCode($slug);
        if ($base === '' || strlen($base) < 3) {
            $base = 'UNIT';
        }
        $candidate = $base;
        $n = 0;
        while (
            $this->tenantRepository->isCommunityCodeTaken($candidate)
            || $this->isReservedCommunityCode($candidate)
        ) {
            $n++;
            $candidate = $base . '-' . $n;
        }

        return $candidate;
    }

    private function isReservedCommunityCode(string $normalized): bool
    {
        return in_array($normalized, self::RESERVED_COMMUNITY_CODES, true);
    }

    /**
     * Seeds minimaux pour les tenants simplifiés (effectifs ou ATAK).
     */
    private function seedSimplifiedTenant(PDO $pdo, int $tenantId, string $tenantType, int $communityOwnerRoleId, int $tenantAdminRoleId): void
    {
        $permissions = TenantTypeConfig::basePermissionsByType()[$tenantType] ?? [];
        $roles = TenantTypeConfig::baseRolesByType()[$tenantType] ?? [];

        $permIds = [];
        foreach ($permissions as $p) {
            $stmt = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, scope, rbac_scope, created_at) VALUES (?, ?, ?, ?, \'community\', \'tenant\', NOW())');
            $stmt->execute([$tenantId, $p['name'], $p['slug'], $p['module']]);
            $permIds[$p['slug']] = (int) $pdo->lastInsertId();
        }

        $slugEq = SqlText::equals($pdo, 'slug');
        foreach ($roles as $r) {
            $stmt = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . $slugEq . ' LIMIT 1');
            $stmt->execute([$tenantId, $r['slug']]);
            if (!$stmt->fetch()) {
                $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, role_layer, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
                    ->execute([
                        $tenantId,
                        $r['name'],
                        $r['slug'],
                        $r['description'],
                        $r['is_system'],
                        $r['is_locked'],
                        $r['role_layer'],
                    ]);
                $roleId = (int) $pdo->lastInsertId();

                foreach ($permIds as $slug => $pid) {
                    $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                    $link->execute([$roleId, $pid]);
                }
            }
        }

        foreach ($permIds as $pid) {
            $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            $link->execute([$communityOwnerRoleId, $pid]);
            $link->execute([$tenantAdminRoleId, $pid]);
        }

        if ($tenantType === TenantTypeConfig::TYPE_EFFECTIFS) {
            TenantSeedHelper::ensurePersonnelPanelsAndMatricule($pdo, $tenantId);
        }
    }
}
