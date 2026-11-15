<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseAccessRuleRepository;
use App\Services\Sse\SseAccessDecision;
use App\Services\Sse\SseAccessDirectory;
use App\Services\Sse\SseAccessMatrix;
use App\Services\Sse\SseAccessMemberRights;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseAccessRuleService;
use App\Services\Sse\SseAccessSubjects;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseMemberClearance;
use App\Support\AdministrativePositionCatalog;
use App\Support\SseAccessCatalog as C;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Droits d'accès SSE simples : bénéficiaires « position de service », « fonction », « qualification » (valide seulement) et
 * « identité » (un membre), qui peut quoi, modèles en situation et bloc « Accès SSE » de la fiche du membre.
 *
 * Base SQLite en mémoire (mêmes colonnes que la production) ; droits d'origine, porte et horloge injectés.
 */
final class SseAccessSimpleSubjectsTest extends TestCase
{
    private const T = 1;
    private const NOW = '2026-11-15 12:00:00';

    // Comptes : 10 gestionnaire, 15 second gestionnaire (sans export), 11 enquêteur, 12 membre habilité (position active, unité 100),
    // 13 membre habilité (position active, unité 102, qualification Analyste valide), 14 membre habilité (qualification Analyste
    // expirée), 16 membre habilité (qualification suspendue), 17 membre habilité (fonction Chef de section), 18 sans droit d'entrer,
    // 20 d'une autre communauté.
    private PDO $pdo;
    private SseAccessRuleRepository $rules;
    private SseAccessPolicy $policy;
    private SseAccessRuleService $service;

    /** @var array<int, list<string>> */
    private array $perms = [];
    /** @var array<int, array<string, mixed>> */
    private array $audit = [];
    /** @var array<int, ?string> niveau d'habilitation inscrit sur la fiche de chaque membre */
    private array $profileLevels = [];
    private string $ceiling = 'tres_restreint';

    protected function setUp(): void
    {
        SseAccessPolicy::useForSession(null);
        AdministrativePositionCatalog::setCustomForTests(1, [['slug' => 'status_custom_chef', 'label' => 'Chef de poste', 'summary' => '', 'detail' => '', 'mission' => '', 'service' => '', 'strength' => '', 'tone' => 'info', 'display_order' => 1]]);
        AdministrativePositionCatalog::setCustomForTests(2, []);
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY)',
            'CREATE TABLE roles (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT, slug TEXT)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, role_id INT NULL, display_name TEXT, status TEXT)',
            'CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INT, parent_id INT NULL, name TEXT)',
            'CREATE TABLE user_units (user_id INT, unit_id INT, ended_at TEXT NULL)',
            'CREATE TABLE tenant_user_roles (user_id INT, tenant_id INT, role_id INT, org_unit_id INT NULL, valid_from TEXT NULL, valid_until TEXT NULL)',
            'CREATE TABLE permissions (id INTEGER PRIMARY KEY, slug TEXT)',
            'CREATE TABLE role_permissions (role_id INT, permission_id INT)',
            'CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT)',
            'CREATE TABLE personnel_profile_job_roles (tenant_id INT, user_id INT, personnel_job_role_id INT)',
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT, archived_at TEXT NULL, is_active INT DEFAULT 1)',
            'CREATE TABLE personnel_qualifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, definition_id INT NULL, status TEXT, admin_status TEXT NULL, expires_at TEXT NULL)',
            'CREATE TABLE sse_access_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, section TEXT, level TEXT, subject_type TEXT, subject_id INT, effect TEXT,
                starts_at TEXT NULL, ends_at TEXT NULL, reason TEXT, source TEXT, created_by INT, created_at TEXT, revoked_at TEXT NULL, revoked_by INT NULL)',
            'CREATE TABLE sse_access_rule_history (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, rule_id INT NULL, action TEXT, actor_user_id INT, actor_label TEXT,
                section TEXT, level TEXT, subject_type TEXT, subject_id INT, subject_label TEXT, effect TEXT, starts_at TEXT NULL, ends_at TEXT NULL, reason TEXT, created_at TEXT)',
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $this->pdo->exec('INSERT INTO tenants (id) VALUES (1), (2)');
        // Rôles : 1 niveau d'accès ; 50 « En service actif », 51 « En service non actif », 52 « Chef de poste » (position de la communauté) ;
        // 59 « En service actif » d'une autre communauté.
        $this->pdo->exec("INSERT INTO roles (id, tenant_id, name, slug) VALUES (1, 1, 'Enquêteurs', 'enq'), (50, 1, 'En service actif', 'status_active_duty'),
            (51, 1, 'En service non actif', 'status_inactive_duty'), (52, 1, 'Chef de poste', 'status_custom_chef'), (59, 2, 'En service actif', 'status_active_duty')");
        $this->pdo->exec("INSERT INTO units (id, tenant_id, parent_id, name) VALUES (100, 1, NULL, 'Section'), (102, 1, NULL, 'Autre unité'), (900, 2, NULL, 'Étrangère')");
        foreach ([10 => 'Gestionnaire', 11 => 'Enquêteur', 12 => 'Marie Durand', 13 => 'Paul Martin', 14 => 'Analyste expiré', 15 => 'Second gestionnaire',
            16 => 'Analyste suspendu', 17 => 'Chef de section', 18 => 'Sans droit'] as $id => $name) {
            $this->pdo->exec("INSERT INTO users (id, tenant_id, role_id, display_name, status) VALUES ($id, 1, NULL, '$name', 'active')");
        }
        $this->pdo->exec("INSERT INTO users (id, tenant_id, role_id, display_name, status) VALUES (20, 2, 59, 'Étranger', 'active')");
        $this->pdo->exec('INSERT INTO tenant_user_roles (user_id, tenant_id, role_id) VALUES (12, 1, 50), (13, 1, 50), (14, 1, 51), (16, 1, 50), (17, 1, 52), (11, 1, 1)');
        $this->pdo->exec('INSERT INTO user_units (user_id, unit_id) VALUES (12, 100), (13, 102)');
        $this->pdo->exec("INSERT INTO permissions (id, slug) VALUES (1, 'atak.sse.grant'), (2, 'admin.access')");
        $this->pdo->exec("INSERT INTO personnel_job_roles (id, tenant_id, name) VALUES (7, 1, 'Chef de section'), (8, 2, 'Chef étranger')");
        $this->pdo->exec('INSERT INTO personnel_profile_job_roles (tenant_id, user_id, personnel_job_role_id) VALUES (1, 17, 7)');
        $this->pdo->exec("INSERT INTO personnel_qualification_definitions (id, tenant_id, name) VALUES (30, 1, 'Analyste'), (31, 1, 'Infirmier'), (39, 2, 'Analyste étranger')");
        $this->pdo->exec("INSERT INTO personnel_qualifications (user_id, definition_id, status, admin_status, expires_at) VALUES
            (13, 30, 'valid', NULL, '2027-06-01'),
            (12, 31, 'valid', NULL, NULL),
            (14, 30, 'valid', NULL, '2026-11-01'),
            (16, 30, 'valid', 'suspended', NULL),
            (11, 30, 'in_progress', NULL, NULL),
            (18, 30, 'expired', NULL, NULL),
            (20, 39, 'valid', NULL, NULL)");

        $this->perms = [
            10 => ['atak.sse.grant', 'atak.sse.access', 'atak.sse.case.manage', 'atak.sse.export'],
            11 => ['atak.sse.access', 'atak.sse.case.manage'],
            12 => ['atak.sse.access'], 13 => ['atak.sse.access'], 14 => ['atak.sse.access'], 16 => ['atak.sse.access'], 17 => ['atak.sse.access'],
            15 => ['atak.sse.grant', 'atak.sse.access'],
            18 => [],
            20 => ['atak.sse.access'],
        ];
        $this->audit = [];
        $this->profileLevels = [];
        $this->ceiling = 'tres_restreint';
        $this->rules = new SseAccessRuleRepository($this->pdo);
        $this->policy = $this->makePolicy();
        $this->service = $this->makeService();
    }

    protected function tearDown(): void
    {
        SseAccessPolicy::useForSession(null);
        AdministrativePositionCatalog::forget();
    }

    private function makePolicy(): SseAccessPolicy
    {
        return new SseAccessPolicy(
            $this->rules,
            new SseAccessSubjects($this->pdo),
            fn (int $u, int $t, string $slug): bool => in_array($slug, $this->perms[$u] ?? [], true),
            fn (?int $u, int $t, string $mode): bool => $u !== null && $u > 0 && $this->enters($u),
            fn (): string => self::NOW,
            fn (int $u, int $t): bool => false,
        );
    }

    private function enters(int $u): bool
    {
        return array_intersect(['atak.sse.access', 'atak.sse.grant', 'admin.access'], $this->perms[$u] ?? []) !== [];
    }

    private function directory(): SseAccessDirectory
    {
        return new class extends SseAccessDirectory {
            public function __construct()
            {
            }

            public function member(int $tenantId, int $userId): ?array
            {
                // 14 est masqué par l'annuaire ; 20 est d'une autre communauté.
                return $tenantId === 1 && $userId >= 10 && $userId <= 18 && $userId !== 14 ? ['user_id' => $userId, 'label' => 'm' . $userId, 'unit' => ''] : null;
            }

            public function visible(int $tenantId, string $query = '', int $limit = 300): array
            {
                $named = [];
                foreach ([10, 11, 12, 13, 15, 16, 17, 18] as $id) {
                    $named[] = ['user_id' => $id, 'label' => 'm' . $id, 'unit' => ''];
                }

                return ['named' => $named, 'anonymized' => 1, 'anonymized_ids' => [14]];
            }
        };
    }

    private function makeService(): SseAccessRuleService
    {
        return new SseAccessRuleService($this->rules, new SseAccessSubjects($this->pdo), $this->policy, $this->directory(), function (array $row): void {
            $this->audit[] = $row;
        });
    }

    /** @return array<string, mixed> */
    private function ctx(array $extra = []): array
    {
        return ['tenant_id' => self::T, 'guest' => false] + $extra;
    }

    private function allow(int $user, string $section, string $level): bool
    {
        return $this->policy->allows($user, $section, $level, $this->ctx());
    }

    private function addRule(string $section, string $level, string $type, int $id, string $effect, ?string $end = null, int $tenant = 1): int
    {
        $rid = $this->rules->insert($tenant, [
            'section' => $section, 'level' => $level, 'subject_type' => $type, 'subject_id' => $id, 'effect' => $effect,
            'starts_at' => null, 'ends_at' => $end, 'reason' => 'test', 'source' => '',
        ], 10, self::NOW);
        $this->policy->reset();

        return $rid;
    }

    // ------------------------------------------------------------------ Position de service

    public function testPositionRuleReachesEveryHolderWhateverTheirUnit(): void
    {
        self::assertFalse($this->allow(12, 'dossiers', 'write'));
        self::assertFalse($this->allow(13, 'dossiers', 'write'));
        $this->addRule('dossiers', 'write', 'position', 50, 'allow');
        self::assertTrue($this->allow(12, 'dossiers', 'write'), 'unité 100');
        self::assertTrue($this->allow(13, 'dossiers', 'write'), 'unité 102 : la position ne dépend pas de l’unité');
        self::assertFalse($this->allow(14, 'dossiers', 'write'), 'autre position (non actif)');
        self::assertSame(SseAccessDecision::ALLOW_RULE, $this->policy->decide(12, 'dossiers', 'write', $this->ctx())->source);
    }

    public function testCommunityCreatedPositionsWork(): void
    {
        $this->addRule('analyse', 'read', 'position', 52, 'allow');
        self::assertSame('Chef de poste', $this->policy->subjects()->label(1, 'position', 52));
        self::assertTrue($this->allow(17, 'analyse', 'read'));
        self::assertTrue($this->allow(12, 'analyse', 'read'), 'lire l’analyse était déjà ouvert à toute personne entrée : le droit d’origine reste');
        self::assertSame(SseAccessDecision::LEGACY, $this->policy->decide(12, 'analyse', 'read', $this->ctx())->source);
    }

    public function testAPositionRoleOfAnotherCommunityOrAnAccessLevelIsNotAPosition(): void
    {
        self::assertNull($this->policy->subjects()->label(1, 'position', 59), 'rôle d’une autre communauté');
        self::assertNull($this->policy->subjects()->label(1, 'position', 1), 'un niveau d’accès n’est pas une position');
        $plan = $this->service->plan(1, 10, ['section' => 'dossiers', 'levels' => ['write'], 'subject_type' => 'position', 'subject_id' => 59, 'effect' => 'allow']);
        self::assertSame(['Bénéficiaire inconnu.'], $plan['errors']);
    }

    public function testAJobIsAServicePositionToo(): void
    {
        $this->addRule('dossiers', 'write', 'job', 7, 'allow');
        self::assertTrue($this->allow(17, 'dossiers', 'write'));
        self::assertFalse($this->allow(12, 'dossiers', 'write'));
        self::assertNull($this->policy->subjects()->label(1, 'job', 8), 'fonction d’une autre communauté');
    }

    // ------------------------------------------------------------------ Qualification

    public function testOnlyValidQualificationsGiveAccess(): void
    {
        $this->addRule('dossiers', 'write', 'qualif', 30, 'allow');
        self::assertTrue($this->allow(13, 'dossiers', 'write'), 'qualification valide');
        self::assertFalse($this->allow(14, 'dossiers', 'write'), 'qualification expirée par sa date');
        self::assertFalse($this->allow(16, 'dossiers', 'write'), 'qualification suspendue (statut administratif)');
        self::assertSame(SseAccessDecision::LEGACY, $this->policy->decide(11, 'dossiers', 'write', $this->ctx())->source, 'en cours : la règle ne donne rien, le droit d’origine du compte reste');
        self::assertFalse($this->allow(18, 'dossiers', 'write'), 'qualification expirée et pas de droit d’entrer');
        self::assertFalse($this->allow(12, 'dossiers', 'write'), 'autre qualification');
    }

    public function testAQualificationThatExpiresStopsGivingAccessWithoutAnyChangeToTheRule(): void
    {
        $this->addRule('dossiers', 'write', 'qualif', 30, 'allow');
        self::assertTrue($this->allow(13, 'dossiers', 'write'));
        $this->pdo->exec("UPDATE personnel_qualifications SET expires_at = '2026-11-14' WHERE user_id = 13");
        $this->policy->reset();
        self::assertFalse($this->allow(13, 'dossiers', 'write'));
        $this->pdo->exec("UPDATE personnel_qualifications SET expires_at = '2027-01-01', admin_status = 'revoked' WHERE user_id = 13");
        $this->policy->reset();
        self::assertFalse($this->allow(13, 'dossiers', 'write'), 'révoquée');
    }

    public function testQualificationsOfAnotherCommunityNeverMatch(): void
    {
        self::assertNull($this->policy->subjects()->label(1, 'qualif', 39));
        $plan = $this->service->plan(1, 10, ['section' => 'dossiers', 'levels' => ['write'], 'subject_type' => 'qualif', 'subject_id' => 39, 'effect' => 'allow']);
        self::assertSame(['Bénéficiaire inconnu.'], $plan['errors']);
        // Même une règle forcée en base pour la définition d'une autre communauté ne donne rien : sa définition n'est pas de la communauté.
        $this->addRule('dossiers', 'write', 'qualif', 39, 'allow');
        self::assertFalse($this->allow(20, 'dossiers', 'write'));
        self::assertFalse($this->allow(12, 'dossiers', 'write'));
    }

    public function testMembersOfAQualificationAreOnlyTheValidHolders(): void
    {
        self::assertSame([13], $this->policy->subjects()->membersOf(1, 'qualif', 30));
        self::assertSame([12], $this->policy->subjects()->membersOf(1, 'qualif', 31));
        $positions = $this->policy->subjects()->membersOf(1, 'position', 50);
        sort($positions);
        self::assertSame([12, 13, 16], $positions);
        self::assertSame([17], $this->policy->subjects()->membersOf(1, 'job', 7));
    }

    // ------------------------------------------------------------------ Identité

    public function testIdentityRuleTargetsOneMemberAndNeedsAReason(): void
    {
        $plan = $this->service->plan(1, 10, ['section' => 'dossiers', 'levels' => ['write'], 'subject_type' => 'user', 'subject_id' => 12, 'effect' => 'allow']);
        self::assertSame([], $plan['rules'], 'motif obligatoire');
        $plan = $this->service->plan(1, 10, ['section' => 'dossiers', 'levels' => ['write'], 'subject_type' => 'user', 'subject_id' => 12, 'effect' => 'allow', 'reason' => 'Renfort enquête Alpha']);
        self::assertCount(1, $plan['rules']);
        self::assertSame('Marie Durand', $this->policy->subjects()->label(1, 'user', 12));
        $this->service->commit(1, 10, 'dix', $plan['rules']);
        self::assertTrue($this->allow(12, 'dossiers', 'write'));
        self::assertFalse($this->allow(13, 'dossiers', 'write'));
        // Une identité masquée par l'annuaire ou d'une autre communauté ne se nomme pas.
        foreach ([14, 20] as $hidden) {
            $p = $this->service->plan(1, 10, ['section' => 'dossiers', 'levels' => ['write'], 'subject_type' => 'user', 'subject_id' => $hidden, 'effect' => 'allow', 'reason' => 'Renfort enquête Alpha']);
            self::assertSame(['Bénéficiaire inconnu.'], $p['errors'], "membre $hidden");
        }
    }

    // ------------------------------------------------------------------ Cumul, refus, sans règle

    public function testRulesCumulateAndEachCanBeRemovedAlone(): void
    {
        $byPosition = $this->addRule('dossiers', 'write', 'position', 50, 'allow');
        $byQualif = $this->addRule('dossiers', 'write', 'qualif', 30, 'allow');
        $d = $this->policy->decide(13, 'dossiers', 'write', $this->ctx());
        self::assertSame([$byPosition, $byQualif], $d->ruleIds, 'deux origines pour le même droit');
        $this->rules->revoke(1, $byPosition, 10, self::NOW);
        $this->policy->reset();
        self::assertTrue($this->allow(13, 'dossiers', 'write'), 'la qualification suffit encore');
        self::assertFalse($this->allow(12, 'dossiers', 'write'), 'celui qui n’avait que la position perd le droit');
    }

    public function testExplicitDenyBeatsAPositionAndAQualificationAllow(): void
    {
        $this->addRule('dossiers', 'write', 'position', 50, 'allow');
        $this->addRule('dossiers', 'write', 'qualif', 30, 'allow');
        $deny = $this->addRule('dossiers', 'write', 'qualif', 31, 'deny');
        self::assertTrue($this->allow(13, 'dossiers', 'write'));
        self::assertFalse($this->allow(12, 'dossiers', 'write'), 'Infirmier : refus explicite, il l’emporte sur la position');
        self::assertSame([$deny], $this->policy->decide(12, 'dossiers', 'write', $this->ctx())->ruleIds);
        // Un refus sur une identité l’emporte aussi sur le droit d’origine.
        $this->addRule('dossiers', 'write', 'user', 11, 'deny');
        self::assertFalse($this->allow(11, 'dossiers', 'write'));
    }

    public function testWithoutRuleOfTheNewKindsNothingChangesAndNoFacetQueryRuns(): void
    {
        $before = [];
        foreach ([10, 11, 12, 13, 14, 15, 16, 17, 18] as $u) {
            foreach (C::SECTIONS as $key => $def) {
                foreach (C::levelKeys() as $lv) {
                    $before["$u.$key.$lv"] = $this->allow($u, $key, $lv);
                }
            }
        }
        // Règles d'un type ancien seulement : les appartenances nouvelles ne sont jamais lues.
        $this->addRule('papiers', 'write', 'role', 1, 'allow');
        SseCountingStatement::watch($this->pdo, ['pq.definition_id', 'FROM personnel_profile_job_roles WHERE tenant_id']);
        $policy = $this->makePolicy();
        foreach ([12, 13, 17] as $u) {
            $policy->allows($u, 'dossiers', 'write', $this->ctx());
        }
        self::assertSame(0, SseCountingStatement::$hits);
        self::assertTrue($before['12.analyse.read'] === $this->allow(12, 'analyse', 'read'));
        self::assertFalse($before['12.dossiers.write']);
    }

    public function testEvaluatingAWholeCommunityReadsQualificationsInOneQueryNotOnePerMember(): void
    {
        $this->addRule('dossiers', 'write', 'qualif', 30, 'allow');
        SseCountingStatement::watch($this->pdo, ['pq.definition_id']);
        $matrix = new SseAccessMatrix($this->makePolicy(), $this->directory());
        $who = $matrix->whoHas(1, 'dossiers', 'write');
        self::assertSame(1, SseCountingStatement::$hits, 'une requête pour tous les membres évalués');
        self::assertContains(13, array_column($who['members'], 'user_id'));
        self::assertNotContains(12, array_column($who['members'], 'user_id'));
    }

    public function testForgettingAMemberReReadsHisPositionAndQualifications(): void
    {
        $policy = $this->makePolicy();
        SseAccessPolicy::useForSession($policy);
        $this->rules->insert(1, ['section' => 'dossiers', 'level' => 'write', 'subject_type' => 'qualif', 'subject_id' => 31, 'effect' => 'allow', 'starts_at' => null, 'ends_at' => null, 'reason' => '', 'source' => ''], 10, self::NOW);
        self::assertFalse($policy->allows(13, 'dossiers', 'write', $this->ctx()));
        $this->pdo->exec("INSERT INTO personnel_qualifications (user_id, definition_id, status) VALUES (13, 31, 'valid')");
        self::assertFalse($policy->allows(13, 'dossiers', 'write', $this->ctx()), 'lu une fois par requête');
        SseAccessPolicy::forgetMember(0, 13);
        self::assertTrue($policy->allows(13, 'dossiers', 'write', $this->ctx()), 'oublié après le changement de qualification');
    }

    // ------------------------------------------------------------------ Écriture simple, sécurité

    /** @return array<string, mixed> */
    private function simple(array $over = []): array
    {
        return $over + ['sections' => ['dossiers'], 'access' => 'ecrire', 'subject_type' => 'qualif', 'subject_id' => 30, 'effect' => 'allow', 'starts_at' => '', 'ends_at' => '', 'reason' => ''];
    }

    public function testSimpleAccessLevelsAndSections(): void
    {
        $plan = $this->service->planSimple(1, 10, $this->simple(['sections' => ['dossiers', 'terrain']]));
        self::assertSame([], $plan['errors']);
        $levels = array_unique(array_map(static fn (array $r): string => $r['level'], $plan['rules']));
        sort($levels);
        self::assertSame(['add', 'exists', 'read', 'write'], $levels, 'écrire donne aussi voir et verser');
        self::assertCount(8, $plan['rules']);
        $all = $this->service->planSimple(1, 10, $this->simple(['sections' => ['*'], 'access' => 'voir']));
        self::assertGreaterThan(8, count($all['rules']));
        self::assertSame(['Choisissez au moins une section.'], $this->service->planSimple(1, 10, $this->simple(['sections' => []]))['errors']);
        self::assertNotSame([], $this->service->planSimple(1, 10, $this->simple(['access' => 'tout']))['errors']);
    }

    public function testNoEscalationWithTheSimpleForm(): void
    {
        // 15 gère les accès mais n'a pas le droit d'export : « gérer » sur les dossiers ne lui permet pas d'accorder l'export.
        $plan = $this->service->planSimple(1, 15, $this->simple(['access' => 'gerer', 'reason' => 'Responsable du bureau']));
        $levels = array_map(static fn (array $r): string => $r['level'], $plan['rules']);
        self::assertNotContains('export', $levels);
        self::assertContains('manage', $levels);
        self::assertNotSame([], $plan['skipped']);
        // Un simple enquêteur n'écrit aucune règle.
        self::assertSame([], $this->service->planSimple(1, 11, $this->simple())['rules']);
    }

    public function testADenyForAPositionCannotCloseReadingToEveryManager(): void
    {
        $this->pdo->exec('INSERT INTO tenant_user_roles (user_id, tenant_id, role_id) VALUES (10, 1, 50), (15, 1, 50)');
        $plan = $this->service->planSimple(1, 10, $this->simple(['subject_type' => 'position', 'subject_id' => 50, 'effect' => 'deny', 'access' => 'voir', 'reason' => 'Fermeture totale']));
        self::assertSame([], $plan['rules']);
        self::assertStringContainsString('verrouiller', implode(' ', $plan['errors']));
    }

    public function testCommittedRulesAreJournalledInTheHistory(): void
    {
        $plan = $this->service->planSimple(1, 10, $this->simple(['subject_type' => 'position', 'subject_id' => 50]));
        $ids = $this->service->commit(1, 10, 'Dix', $plan['rules']);
        self::assertCount(count($plan['rules']), $ids);
        $history = $this->rules->history(1, 50);
        self::assertCount(count($ids), $history);
        self::assertSame('position', $history[0]['subject_type']);
        self::assertSame('En service actif', $history[0]['subject_label']);
        self::assertSame('access_rule.create', $this->audit[0]['action']);
    }

    public function testRecipesNeedTheRightKindOfBeneficiary(): void
    {
        $bad = $this->service->planRecipe(1, 10, 'renseignement', ['0' => 'qualif:30', '1' => 'position:50'], []);
        self::assertSame([], $bad['rules']);
        self::assertCount(2, $bad['errors'], 'positions attendues pour la première étape, qualification pour la seconde');
        $ok = $this->service->planRecipe(1, 10, 'renseignement', ['0' => 'position:50', '1' => 'qualif:30'], ['reason' => '']);
        self::assertSame([], $ok['errors']);
        $bySource = array_unique(array_map(static fn (array $r): string => (string) $r['source'], $ok['rules']));
        self::assertSame(['recipe:renseignement'], array_values($bySource));
        $voir = array_filter($ok['rules'], static fn (array $r): bool => $r['subject_type'] === 'position' && $r['level'] === 'read');
        self::assertCount(count(array_filter(array_keys(C::SECTIONS), static fn (string $k): bool => $k !== 'lore')), $voir, 'voir : toutes les sections que le gestionnaire voit (pas le lore, qu’il ne possède pas)');
        $ecrire = array_filter($ok['rules'], static fn (array $r): bool => $r['subject_type'] === 'qualif' && $r['level'] === 'write');
        self::assertCount(7, $ecrire);
        self::assertSame('Renseignement', C::sourceLabel('recipe:renseignement'));
        self::assertSame([], $this->service->planRecipe(1, 10, 'inconnu', [], [])['rules']);
    }

    public function testARecipeStepAlreadyInPlaceDoesNotBlockTheOtherSteps(): void
    {
        $first = $this->service->planRecipe(1, 10, 'renseignement', ['0' => 'position:50', '1' => 'qualif:30'], []);
        $this->service->commit(1, 10, 'Dix', $first['rules'], 'template');
        $again = $this->service->planRecipe(1, 10, 'renseignement', ['0' => 'position:50', '1' => 'qualif:31'], []);
        self::assertSame([], $again['errors']);
        self::assertSame(['qualif'], array_values(array_unique(array_map(static fn (array $r): string => $r['subject_type'], $again['rules']))));
        $none = $this->service->planRecipe(1, 10, 'renseignement', ['0' => 'position:50', '1' => 'qualif:30'], []);
        self::assertSame([], $none['rules']);
        self::assertNotSame([], $none['errors'], 'rien du tout à créer : signalé');
    }
    public function testSimulationAndEffectLineCountThePeopleWhoWouldGainAccess(): void
    {
        $matrix = new SseAccessMatrix($this->policy, $this->directory());
        $plan = $this->service->planSimple(1, 10, $this->simple(['subject_type' => 'position', 'subject_id' => 50]));
        $sim = $matrix->simulate(1, $plan['rules']);
        self::assertSame(3, $sim['gained'], '12, 13 et 16 : trois membres en service actif, quelle que soit leur unité ou leur qualification');
        $line = SseAccessMatrix::effectLine($sim);
        self::assertStringContainsString('personnes gagneraient l’accès', $line);
        self::assertSame('1 personne gagnerait l’accès.', SseAccessMatrix::effectLine(['gained' => 1, 'lost' => 0]));
        self::assertStringContainsString('Aucun changement', SseAccessMatrix::effectLine(['gained' => 0, 'lost' => 0]));
    }

    public function testBoardListsSimpleBeneficiariesFirstWithTheirKind(): void
    {
        $this->addRule('dossiers', 'write', 'role', 1, 'allow');
        $this->addRule('dossiers', 'write', 'qualif', 30, 'allow');
        $this->addRule('dossiers', 'write', 'position', 50, 'deny');
        $this->addRule('dossiers', 'manage', 'user', 12, 'allow', '2026-12-31 23:59:59');
        $this->addRule('dossiers', 'write', 'user', 13, 'allow', '2026-01-01 00:00:00');
        $board = (new SseAccessMatrix($this->policy, $this->directory()))->board(1, static fn (string $key): bool => $key !== 'lore');
        self::assertNotContains('lore', array_column($board, 'key'));
        $dossiers = array_values(array_filter($board, static fn (array $r): bool => $r['key'] === 'dossiers'))[0];
        $write = $dossiers['columns']['ecrire'];
        self::assertSame(['qualif', 'position', 'role'], array_column($write, 'type'), 'qualification, puis refus de position, puis niveau d’accès discret');
        self::assertCount(1, $dossiers['columns']['gerer']);
        self::assertSame('user', $dossiers['columns']['gerer'][0]['type']);
        self::assertSame('Marie Durand', $dossiers['columns']['gerer'][0]['label']);
        self::assertSame([], $dossiers['columns']['voir']);
        self::assertNotContains('user', array_column($write, 'type'), 'la règle échue n’apparaît plus');
    }

    // ------------------------------------------------------------------ Fiche du membre

    private function rights(): SseAccessMemberRights
    {
        $clearance = new SseMemberClearance(
            fn (int $u, int $t, string $slug): bool => in_array($slug, $this->perms[$u] ?? [], true),
            fn (int $u): ?string => $this->profileLevels[$u] ?? null,
            function (int $u, ?string $level): bool {
                $this->profileLevels[$u] = $level;

                return true;
            },
            function (array $row): void {
                $this->audit[] = $row;
            },
            fn (int $t, int $u): ?array => null,
        );

        return new SseAccessMemberRights($this->policy, $this->service, new SseAccessMatrix($this->policy, $this->directory()), $this->directory(), $clearance, fn (): string => $this->ceiling);
    }

    public function testMemberBlockIsInvisibleToEveryoneButSseManagers(): void
    {
        self::assertNull($this->rights()->overview(1, 11, 12), 'enquêteur');
        self::assertNull($this->rights()->overview(1, 12, 12), 'le membre lui-même');
        self::assertNull($this->rights()->overview(1, 0, 12));
        self::assertNotNull($this->rights()->overview(1, 10, 12));
        self::assertNull($this->rights()->overview(1, 10, 14), 'membre masqué par l’annuaire');
        self::assertNull($this->rights()->overview(1, 10, 20), 'membre d’une autre communauté');
        self::assertNull($this->rights()->overview(2, 10, 12), 'le gestionnaire n’a aucun droit dans une autre communauté (garde : hors communauté)');
    }

    public function testMemberBlockExplainsWhereEachRightComesFrom(): void
    {
        $this->addRule('dossiers', 'write', 'position', 50, 'allow');
        $this->addRule('analyse', 'read', 'qualif', 31, 'allow');
        $own = $this->addRule('dossiers', 'manage', 'user', 12, 'allow', '2026-12-31 23:59:59');
        $view = $this->rights()->overview(1, 10, 12);
        self::assertNotNull($view);
        $dossiers = array_values(array_filter($view['rows'], static fn (array $r): bool => $r['key'] === 'dossiers'))[0];
        $types = array_column($dossiers['cells']['ecrire']['origins'], 'type');
        self::assertSame(['position'], $types);
        self::assertSame('En service actif', $dossiers['cells']['ecrire']['origins'][0]['label']);
        self::assertTrue($dossiers['cells']['ecrire']['allowed']);
        self::assertSame([$own], $dossiers['cells']['gerer']['own']);
        self::assertSame('user', $dossiers['cells']['gerer']['origins'][0]['type']);
        $legacy = array_column($dossiers['cells']['voir']['origins'], 'kind');
        self::assertSame(['legacy'], $legacy);
        self::assertCount(1, $view['own']);
    }

    public function testMemberBlockGivesAndWithdrawsAccessThroughTheSameGuards(): void
    {
        $rights = $this->rights();
        $preview = $rights->preview(1, 10, 12, ['sections' => ['dossiers'], 'access' => 'ecrire', 'reason' => 'Renfort enquête Alpha', 'ends_at' => '2027-01-31']);
        self::assertSame([], $preview['errors']);
        self::assertStringContainsString('1 personne gagnerait l’accès', $preview['line']);
        self::assertSame(0, $this->rules->countLive(1), 'l’aperçu n’écrit rien');

        $res = $rights->save(1, 10, 'Dix', 12, ['sections' => ['dossiers'], 'access' => 'ecrire', 'reason' => 'Renfort enquête Alpha', 'ends_at' => '2027-01-31']);
        self::assertTrue($res['ok']);
        self::assertTrue($this->allow(12, 'dossiers', 'write'));
        self::assertSame('2027-01-31 23:59:59', $this->rules->find(1, $res['ids'][0])['ends_at']);
        $view = $rights->overview(1, 10, 12);
        self::assertGreaterThan(0, count($view['history']));
        self::assertSame('user', $view['history'][0]['subject_type']);

        // Retirer en un clic : les règles du membre seulement.
        foreach ($res['ids'] as $ruleId) {
            self::assertTrue($rights->revoke(1, 10, 'Dix', 12, $ruleId)['ok']);
        }
        self::assertFalse($this->allow(12, 'dossiers', 'write'));
        self::assertSame('revoke', $rights->overview(1, 10, 12)['history'][0]['action']);
    }

    public function testMemberBlockRefusesEscalationMissingReasonAndForeignRules(): void
    {
        $rights = $this->rights();
        self::assertFalse($rights->save(1, 10, 'Dix', 12, ['sections' => ['dossiers'], 'access' => 'ecrire', 'reason' => ''])['ok'], 'motif obligatoire');
        self::assertFalse($rights->save(1, 11, 'Onze', 12, ['sections' => ['dossiers'], 'access' => 'ecrire', 'reason' => 'Renfort enquête Alpha'])['ok'], 'pas gestionnaire');
        self::assertFalse($rights->save(1, 10, 'Dix', 14, ['sections' => ['dossiers'], 'access' => 'ecrire', 'reason' => 'Renfort enquête Alpha'])['ok'], 'membre masqué');
        self::assertFalse($rights->save(1, 10, 'Dix', 20, ['sections' => ['dossiers'], 'access' => 'ecrire', 'reason' => 'Renfort enquête Alpha'])['ok'], 'membre d’une autre communauté');
        self::assertFalse($rights->save(1, 10, 'Dix', 12, ['sections' => [], 'access' => 'ecrire', 'reason' => 'Renfort enquête Alpha'])['ok'], 'aucune section');
        // Escalade : 15 ne possède pas l'export des dossiers.
        $res = $rights->save(1, 15, 'Quinze', 13, ['sections' => ['dossiers'], 'access' => 'gerer', 'reason' => 'Responsable du bureau']);
        self::assertTrue($res['ok']);
        $levels = array_map(fn (int $id): string => $this->rules->find(1, $id)['level'], $res['ids']);
        self::assertNotContains('export', $levels);

        // Anti-IDOR : une règle d'un autre membre, d'un autre bénéficiaire ou d'une autre communauté ne se retire pas par la fiche.
        $other = $this->addRule('dossiers', 'write', 'user', 13, 'allow');
        $positionRule = $this->addRule('dossiers', 'write', 'position', 50, 'allow');
        $foreign = $this->addRule('dossiers', 'write', 'user', 20, 'allow', null, 2);
        self::assertFalse($rights->revoke(1, 10, 'Dix', 12, $other)['ok'], 'règle d’un autre membre');
        self::assertFalse($rights->revoke(1, 10, 'Dix', 12, $positionRule)['ok'], 'règle d’une position');
        self::assertFalse($rights->revoke(1, 10, 'Dix', 20, $foreign)['ok'], 'règle d’une autre communauté');
        self::assertNull($this->rules->find(2, $foreign)['revoked_at']);
        self::assertTrue($rights->revoke(1, 10, 'Dix', 13, $other)['ok']);
    }

    private static function read(string $relative): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    }

    public function testEveryNewTextHasItsEnglishEntry(): void
    {
        $en = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        $texts = array_values(C::SUBJECT_TYPES);
        foreach (C::SIMPLE_ACCESS as $def) {
            $texts[] = $def['label'];
        }
        foreach (C::RECIPES as $recipe) {
            $texts[] = $recipe['label'];
            $texts[] = $recipe['hint'];
            foreach ($recipe['steps'] as $step) {
                $texts[] = $step['label'];
            }
        }
        foreach (['Qui peut quoi', 'Ajouter un bénéficiaire', 'Accès SSE', 'Donner un accès', 'Retirer un accès', 'Choisissez au moins une section.'] as $t) {
            $texts[] = $t;
        }
        foreach ($texts as $t) {
            self::assertArrayHasKey($t, $en, 'traduction anglaise manquante : ' . $t);
        }
    }

    public function testRoutesCsrfAndGuardsOfTheNewScreens(): void
    {
        $routes = self::read('routes/web.php');
        foreach (['/atak/sse/droits/simple', '/atak/sse/droits/situation'] as $path) {
            self::assertStringContainsString("\$router->post('" . $path . "'", $routes);
        }
        self::assertStringContainsString("\$router->get('/atak/sse/droits/personnes'", $routes);
        foreach (['', '/apercu', '/{ruleId}/retirer'] as $suffix) {
            self::assertStringContainsString("/back-office/ressources/effectifs/membres/{id}/acces-sse" . $suffix . "'", $routes);
        }
        $ctl = self::read('app/Controllers/Admin/MemberSseAccessController.php');
        self::assertStringContainsString('Csrf::validate(', $ctl);
        self::assertStringContainsString('isPost()', $ctl);
        self::assertStringContainsString('FileRateLimiter', $ctl);
        self::assertStringContainsString("Session::get('tenant_id')", $ctl, 'la communauté vient de la session');
        self::assertStringNotContainsString("input('tenant", $ctl, 'jamais lue dans la requête');
        self::assertStringNotContainsString('tenant_id\')', str_replace("Session::get('tenant_id')", '', $ctl));
        $svc = self::read('app/Services/Sse/SseAccessMemberRights.php');
        self::assertStringContainsString('canManageRights(', $svc);
        $view = self::read('views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString("'sseAccess'", self::read('app/Controllers/Admin/EffectifsWorkspaceController.php'));
        self::assertStringContainsString('member_sse_access.php', $view);
        self::assertStringContainsString("'acces-sse': 'eff-dlg-acces-sse'", self::read('public/assets/js/eff-member.js'));
    }

    public function testNewViewsAndStylesHaveNoEyebrowNoEmojiAndLazyImages(): void
    {
        $files = [
            'views/atak/sse/rights/board.php', 'views/atak/sse/rights/_beneficiary.php', 'views/admin/effectifs_workspace/partials/member_sse_access.php',
            'public/assets/css/eff-member-sse-access.css', 'public/assets/css/sse-access-rights.css', 'public/assets/js/sse-access-picker.js', 'public/assets/js/eff-member-sse-access.js',
        ];
        foreach ($files as $file) {
            $src = self::read($file);
            self::assertDoesNotMatchRegularExpression('/kicker|eyebrow|tracking-widest/i', $src, $file . ' : aucun surtitre');
            self::assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src, $file . ' : pas d’emoji');
            self::assertDoesNotMatchRegularExpression('/<img(?![^>]*loading="lazy")/i', $src, $file . ' : images en loading="lazy"');
            self::assertDoesNotMatchRegularExpression('/font-size:\s*(?:[0-9]|1[01])px/', $src, $file . ' : aucun texte sous 12 px');
        }
    }
    // ------------------------------------------------------------------ Niveau d'habilitation sur la fiche

    public function testClearanceLevelOfAMemberFollowsTheSessionRule(): void
    {
        $this->perms[17] = ['atak.sse.access', 'atak.sse.clearance.confidentiel'];
        $this->profileLevels[13] = 'confidentiel';
        $view = fn (int $id): array => $this->rights()->overview(1, 10, $id)['clearance'];
        self::assertSame('tres_restreint', $view(10)['level']);
        self::assertSame('role', $view(10)['source'], 'report du droit d’attribution des accès');
        self::assertSame('encadrement', $view(12)['level']);
        self::assertSame('confidentiel', $view(17)['level']);
        self::assertSame('explicit', $view(17)['source']);
        self::assertSame('confidentiel', $view(13)['level'], 'la fiche l’emporte sur le report plus bas');
        self::assertSame('profile', $view(13)['source']);
        self::assertSame('Confidentiel', $view(13)['label']);
        self::assertSame('floor', $view(18)['source'], 'sans aucun droit SSE : diffusion interne');
        self::assertSame('confidentiel', $view(11)['level'], 'gestion des dossiers : report en confidentiel');
        // Même règle que la session : la fonction partagée donne la même réponse.
        $can = static fn (string $slug): bool => $slug === 'atak.sse.access';
        self::assertSame(['level' => 'encadrement', 'source' => 'role'], SseClearanceService::resolve($can, null));
        self::assertSame(['level' => 'tres_restreint', 'source' => 'profile'], SseClearanceService::resolve($can, 'tres_restreint'));
        self::assertNull(SseClearanceService::normalizeProfileLevel('inconnu'));
        self::assertSame('confidentiel', SseClearanceService::normalizeProfileLevel(' Confidentiel '));
    }

    public function testGrantingAClearanceNeedsTheRightReasonAndNeverExceedsYourOwn(): void
    {
        $rights = $this->rights();
        $this->ceiling = 'confidentiel';
        self::assertFalse($rights->changeClearance(1, 10, 'Dix', 12, 'tres_restreint', 'Mission spéciale')['ok'], 'pas plus haut que son propre plafond');
        self::assertFalse($rights->changeClearance(1, 10, 'Dix', 12, 'confidentiel', '')['ok'], 'motif obligatoire');
        self::assertFalse($rights->changeClearance(1, 10, 'Dix', 12, 'nimporte', 'Mission spéciale')['ok'], 'niveau inconnu');
        self::assertFalse($rights->changeClearance(1, 11, 'Onze', 12, 'encadrement', 'Mission spéciale')['ok'], 'pas gestionnaire');
        self::assertFalse($rights->changeClearance(1, 10, 'Dix', 14, 'encadrement', 'Mission spéciale')['ok'], 'membre masqué par l’annuaire');
        self::assertFalse($rights->changeClearance(1, 10, 'Dix', 20, 'encadrement', 'Mission spéciale')['ok'], 'membre d’une autre communauté');
        self::assertSame([], $this->profileLevels);
        self::assertSame([], $this->audit);

        self::assertTrue($rights->changeClearance(1, 10, 'Dix', 12, 'confidentiel', 'Mission spéciale')['ok']);
        self::assertSame('confidentiel', $this->profileLevels[12]);
        self::assertSame('member_clearance.set', $this->audit[0]['action']);
        self::assertSame('member_clearance', $this->audit[0]['object_type']);
        self::assertSame(12, $this->audit[0]['object_id']);
        self::assertSame(['level' => 'confidentiel'], $this->audit[0]['after']);
        self::assertSame('confidentiel', $rights->overview(1, 10, 12)['clearance']['level']);
        self::assertFalse($rights->changeClearance(1, 10, 'Dix', 12, 'confidentiel', 'Mission spéciale')['ok'], 'déjà ce niveau');
    }

    public function testAMemberAboveYourClearanceCannotBeChangedOrRemoved(): void
    {
        $rights = $this->rights();
        $this->profileLevels[13] = 'tres_restreint';
        $this->ceiling = 'confidentiel';
        $view = $rights->overview(1, 10, 13)['clearance'];
        self::assertFalse($view['may_change']);
        self::assertSame(['encadrement', 'confidentiel'], array_keys($view['options']), 'jamais au-dessus de son propre plafond');
        self::assertFalse($rights->changeClearance(1, 10, 'Dix', 13, null, 'Retrait demandé')['ok']);
        self::assertSame('tres_restreint', $this->profileLevels[13]);
        $this->ceiling = 'tres_restreint';
        self::assertTrue($rights->changeClearance(1, 10, 'Dix', 13, null, 'Fin de mission')['ok']);
        self::assertNull($this->profileLevels[13]);
        self::assertSame('member_clearance.remove', $this->audit[0]['action']);
    }

    public function testTheClearanceBadgeAndFormAreWiredAndHiddenFromNonManagers(): void
    {
        self::assertNull($this->rights()->overview(1, 11, 12), 'ni badge ni habilitation pour qui ne gère pas les accès SSE');
        self::assertStringContainsString("acces-sse/habilitation'", self::read('routes/web.php'));
        self::assertStringContainsString('changeClearance(', self::read('app/Controllers/Admin/MemberSseAccessController.php'));
        self::assertStringContainsString('eff-sse-badge--hero', self::read('views/admin/effectifs_workspace/member.php'));
        $partial = self::read('views/admin/effectifs_workspace/partials/member_sse_access.php');
        self::assertStringContainsString("/habilitation'", $partial);
        self::assertStringContainsString('_csrf_token', $partial);
        $en = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        foreach (['Habilitation SSE : %1', 'Enregistrer l’habilitation', 'Aucun (retirer l’habilitation de la fiche)'] as $t) {
            self::assertArrayHasKey($t, $en);
        }
    }

    public function testNoMigrationIsNeededTheTypesFitTheExistingColumn(): void
    {
        foreach ([C::SUBJECT_POSITION, C::SUBJECT_JOB, C::SUBJECT_QUALIF, C::SUBJECT_USER] as $type) {
            self::assertLessThanOrEqual(12, strlen($type), "subject_type est VARCHAR(12) : $type");
        }
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_11_15_100_sse_access_rules.sql');
        self::assertStringContainsString('subject_type VARCHAR(12)', $sql);
        self::assertSame(['position', 'job', 'qualif'], array_values(array_diff(array_keys(C::SUBJECT_TYPES), ['role', 'unit', 'unit_tree', 'user'])));
    }
}

/** Compte les requêtes dont le texte contient l'un des motifs (SQLite en mémoire). */
final class SseCountingStatement extends \PDOStatement
{
    public static int $hits = 0;

    /** @var list<string> */
    public static array $patterns = [];

    protected function __construct()
    {
    }

    /** @param list<string> $patterns */
    public static function watch(PDO $pdo, array $patterns): void
    {
        self::$hits = 0;
        self::$patterns = $patterns;
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [self::class]);
    }

    public function execute(?array $params = null): bool
    {
        foreach (self::$patterns as $pattern) {
            if (str_contains($this->queryString, $pattern)) {
                ++self::$hits;
                break;
            }
        }

        return parent::execute($params);
    }
}
