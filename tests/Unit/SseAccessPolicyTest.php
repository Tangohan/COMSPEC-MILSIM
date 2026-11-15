<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseAccessRuleRepository;
use App\Services\Sse\SseAccessDecision;
use App\Services\Sse\SseAccessDirectory;
use App\Services\Sse\SseAccessMatrix;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseAccessRuleService;
use App\Services\Sse\SseAccessSubjects;
use App\Support\SseAccessCatalog as C;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Droits d'accès du portail SSE : résolution (défaut inchangé, accord, refus prioritaire, dates, unités, niveaux
 * d'accès, membres), porte d'entrée toujours exigée, pas d'escalade, dernier gestionnaire protégé, étanchéité entre
 * communautés, historique, journal d'audit.
 *
 * Base SQLite en mémoire (les mêmes colonnes que les tables de production) ; droits d'origine, porte et horloge injectés.
 */
final class SseAccessPolicyTest extends TestCase
{
    private const T = 1;
    private const OTHER_TENANT = 2;
    private const NOW = '2026-11-15 12:00:00';

    // Comptes : 10 gestionnaire (attribution des accès), 11 enquêteur (gestion des dossiers), 12 simple membre habilité,
    // 13 sans droit d'entrer, 14 administrateur de la plateforme, 15 autre gestionnaire, 20 membre d'une autre communauté.
    private PDO $pdo;
    private SseAccessRuleRepository $rules;
    private SseAccessPolicy $policy;
    private SseAccessRuleService $service;

    /** @var array<int, list<string>> */
    private array $perms = [];
    /** @var list<int> */
    private array $platform = [];
    /** @var array<int, array<int, string>> */
    private array $audit = [];

    protected function setUp(): void
    {
        SseAccessPolicy::useForSession(null);
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT, slug TEXT)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, role_id INT NULL, display_name TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INT, parent_id INT NULL, name TEXT)');
        $this->pdo->exec('CREATE TABLE user_units (user_id INT, unit_id INT, ended_at TEXT NULL)');
        $this->pdo->exec('CREATE TABLE tenant_user_roles (user_id INT, tenant_id INT, role_id INT, org_unit_id INT NULL, valid_from TEXT NULL, valid_until TEXT NULL)');
        $this->pdo->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY, slug TEXT)');
        $this->pdo->exec('CREATE TABLE role_permissions (role_id INT, permission_id INT)');
        $this->pdo->exec('CREATE TABLE sse_access_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, section TEXT, level TEXT, subject_type TEXT, subject_id INT, effect TEXT,
            starts_at TEXT NULL, ends_at TEXT NULL, reason TEXT, source TEXT, created_by INT, created_at TEXT, revoked_at TEXT NULL, revoked_by INT NULL)');
        $this->pdo->exec('CREATE TABLE sse_access_rule_history (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT, rule_id INT NULL, action TEXT, actor_user_id INT, actor_label TEXT,
            section TEXT, level TEXT, subject_type TEXT, subject_id INT, subject_label TEXT, effect TEXT, starts_at TEXT NULL, ends_at TEXT NULL, reason TEXT, created_at TEXT)');

        foreach ([self::T, self::OTHER_TENANT] as $t) {
            $this->pdo->exec("INSERT INTO tenants (id) VALUES ($t)");
        }
        // Niveaux d'accès : 1 Enquêteurs, 2 Analystes (communauté 1) ; 9 d'une autre communauté.
        $this->pdo->exec("INSERT INTO roles (id, tenant_id, name, slug) VALUES (1, 1, 'Enquêteurs', 'enq'), (2, 1, 'Analystes', 'ana'), (9, 2, 'Autre', 'x')");
        // Unités : 100 Section, 101 Équipe (sous 100), 102 Autre unité ; 900 d'une autre communauté.
        $this->pdo->exec("INSERT INTO units (id, tenant_id, parent_id, name) VALUES (100, 1, NULL, 'Section'), (101, 1, 100, 'Équipe'), (102, 1, NULL, 'Autre unité'), (900, 2, NULL, 'Étrangère')");
        foreach ([10 => 'Gestionnaire', 11 => 'Enquêteur', 12 => 'Membre', 13 => 'Sans droit', 14 => 'Admin plateforme', 15 => 'Second gestionnaire', 16 => 'Autre membre'] as $id => $name) {
            $this->pdo->exec("INSERT INTO users (id, tenant_id, role_id, display_name, status) VALUES ($id, 1, NULL, '$name', 'active')");
        }
        $this->pdo->exec("INSERT INTO users (id, tenant_id, role_id, display_name, status) VALUES (20, 2, 9, 'Étranger', 'active')");
        $this->pdo->exec('INSERT INTO tenant_user_roles (user_id, tenant_id, role_id) VALUES (11, 1, 1), (12, 1, 2)');
        $this->pdo->exec('INSERT INTO user_units (user_id, unit_id) VALUES (11, 101), (12, 102)');
        $this->pdo->exec("INSERT INTO permissions (id, slug) VALUES (1, 'atak.sse.grant'), (2, 'admin.access')");

        $this->perms = [
            10 => ['atak.sse.grant', 'atak.sse.access', 'atak.sse.case.manage', 'atak.sse.export'],
            11 => ['atak.sse.access', 'atak.sse.case.manage'],
            12 => ['atak.sse.access'],
            13 => [],
            14 => ['atak.sse.grant', 'admin.access'],
            15 => ['atak.sse.grant', 'atak.sse.access'],
            16 => ['atak.sse.access'],
            20 => ['atak.sse.access'],
        ];
        $this->platform = [14];
        $this->audit = [];

        $this->rules = new SseAccessRuleRepository($this->pdo);
        $this->policy = $this->makePolicy();
        $this->service = $this->makeService();
    }

    protected function tearDown(): void
    {
        SseAccessPolicy::useForSession(null);
    }

    private function makePolicy(): SseAccessPolicy
    {
        return new SseAccessPolicy(
            $this->rules,
            new SseAccessSubjects($this->pdo),
            fn (int $u, int $t, string $slug): bool => in_array($slug, $this->perms[$u] ?? [], true) || (in_array($u, $this->platform, true)),
            // La porte : droit d'entrer (en mode « entry ») ou session ouverte (ici : mêmes comptes).
            fn (?int $u, int $t, string $mode): bool => $u !== null && $u > 0 && $this->enters($u),
            fn (): string => self::NOW,
            fn (int $u, int $t): bool => in_array($u, $this->platform, true),
        );
    }

    private function enters(int $u): bool
    {
        foreach (['atak.sse.access', 'atak.sse.grant', 'admin.access'] as $slug) {
            if (in_array($slug, $this->perms[$u] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    private function makeService(): SseAccessRuleService
    {
        $directory = new class extends SseAccessDirectory {
            public function __construct()
            {
            }

            public function member(int $tenantId, int $userId): ?array
            {
                return $tenantId === 1 && $userId >= 10 && $userId <= 16 ? ['user_id' => $userId, 'label' => 'm' . $userId, 'unit' => ''] : null;
            }
        };
        $audit = function (array $row): void {
            $this->audit[] = $row;
        };

        return new SseAccessRuleService($this->rules, new SseAccessSubjects($this->pdo), $this->policy, $directory, $audit);
    }

    /** @return array<string, mixed> */
    private function ctx(array $extra = []): array
    {
        return ['tenant_id' => self::T, 'guest' => false] + $extra;
    }

    private function allow(int $user, string $section, string $level, array $extra = []): bool
    {
        return $this->policy->allows($user, $section, $level, $this->ctx($extra));
    }

    private function addRule(string $section, string $level, string $type, int $id, string $effect, ?string $start = null, ?string $end = null, int $tenant = 1): int
    {
        $rid = $this->rules->insert($tenant, [
            'section' => $section, 'level' => $level, 'subject_type' => $type, 'subject_id' => $id, 'effect' => $effect,
            'starts_at' => $start, 'ends_at' => $end, 'reason' => 'test', 'source' => '',
        ], 10, self::NOW);
        $this->policy->reset();

        return $rid;
    }

    // ------------------------------------------------------------------ Comportement par défaut inchangé

    /**
     * Les gardes d'origine, réécrites ici telles qu'elles étaient dans les contrôleurs avant le service : le profil du
     * catalogue doit donner la même réponse pour chaque profil type, sans aucune règle.
     *
     * @return array<string, callable(list<string>, bool, bool): bool>
     */
    private function oldGuards(): array
    {
        $can = static fn (array $p, string ...$any): bool => array_intersect($any, $p) !== [];
        $manage = static fn (array $p, bool $guest, bool $door): bool => !$guest && $can($p, 'atak.sse.case.manage', 'atak.sse.grant', 'admin.access');
        $filesManage = static fn (array $p, bool $guest, bool $door): bool => !$guest && $can($p, 'atak.sse.case.manage', 'atak.sse.grant', 'atak.sse.files.admin', 'admin.access');
        $filesAdmin = static fn (array $p, bool $guest, bool $door): bool => !$guest && $can($p, 'atak.sse.files.admin', 'admin.access');
        $grant = static fn (array $p, bool $guest, bool $door): bool => !$guest && $can($p, 'atak.sse.grant', 'admin.access');
        $export = static fn (array $p, bool $guest, bool $door): bool => !$guest && $can($p, 'atak.sse.export', 'admin.access');
        $lore = static fn (array $p, bool $guest, bool $door): bool => !$guest && $can($p, 'atak.sse.lore.manage', 'admin.access');
        $entrant = static fn (array $p, bool $guest, bool $door): bool => $door;
        $account = static fn (array $p, bool $guest, bool $door): bool => !$guest;
        $fieldWrite = static fn (array $p, bool $guest, bool $door): bool => !$guest && $can($p, 'atak.sse.access', 'atak.sse.case.manage', 'admin.access');
        $none = static fn (array $p, bool $guest, bool $door): bool => false;

        return [
            'fichiers.read' => $entrant, 'fichiers.write' => $filesManage, 'fichiers.add' => $account, 'fichiers.delete' => $filesManage, 'fichiers.export' => $entrant, 'fichiers.manage' => $filesAdmin,
            'photos.read' => $entrant, 'photos.write' => $account, 'photos.add' => $account, 'photos.delete' => $filesManage, 'photos.export' => $entrant, 'photos.manage' => $filesAdmin,
            'papiers.read' => $entrant, 'papiers.write' => $manage, 'papiers.add' => $manage, 'papiers.delete' => $manage, 'papiers.export' => $manage, 'papiers.manage' => $manage,
            'extractions.read' => $entrant, 'extractions.write' => $manage, 'extractions.add' => $manage, 'extractions.delete' => $manage, 'extractions.export' => $account, 'extractions.manage' => $filesAdmin,
            'dossiers.read' => $entrant, 'dossiers.write' => $manage, 'dossiers.add' => $manage, 'dossiers.delete' => $manage, 'dossiers.export' => $export, 'dossiers.manage' => $grant,
            'analyse.read' => $entrant, 'analyse.write' => $manage, 'analyse.add' => $manage, 'analyse.delete' => $manage, 'analyse.export' => $export, 'analyse.manage' => $grant,
            'lore.read' => $lore, 'lore.write' => $lore, 'lore.add' => $lore, 'lore.delete' => $lore, 'lore.export' => $lore, 'lore.manage' => $lore,
            'journal.read' => $entrant, 'journal.write' => $none, 'journal.add' => $none, 'journal.delete' => $none, 'journal.export' => $none, 'journal.manage' => $none,
            'terrain.read' => $entrant, 'terrain.write' => $fieldWrite, 'terrain.add' => $fieldWrite, 'terrain.delete' => $manage, 'terrain.export' => $export, 'terrain.manage' => $manage,
            'outils.read' => $entrant, 'outils.write' => $manage, 'outils.add' => $manage, 'outils.delete' => $manage, 'outils.export' => $entrant, 'outils.manage' => $manage,
        ];
    }

    public function testWithoutRulesEveryGuardKeepsItsOriginalAnswer(): void
    {
        $profiles = [
            'aucun droit' => [],
            'habilité' => ['atak.sse.access'],
            'enquêteur' => ['atak.sse.access', 'atak.sse.case.manage'],
            'gestionnaire des fichiers' => ['atak.sse.access', 'atak.sse.files.admin'],
            'attribution des accès' => ['atak.sse.grant'],
            'export seul' => ['atak.sse.access', 'atak.sse.export'],
            'lore seul' => ['atak.sse.access', 'atak.sse.lore.manage'],
            'administrateur' => ['admin.access'],
            'tous les droits SSE' => ['atak.sse.access', 'atak.sse.grant', 'atak.sse.case.manage', 'atak.sse.export', 'atak.sse.files.admin', 'atak.sse.lore.manage'],
        ];
        $checked = 0;
        foreach ($profiles as $name => $perms) {
            $this->perms[30] = $perms;
            $this->pdo->exec('INSERT OR IGNORE INTO users (id, tenant_id, role_id, display_name, status) VALUES (30, 1, NULL, \'profil\', \'active\')');
            foreach ([false, true] as $guest) {
                foreach ([true, false] as $door) {
                    $policy = new SseAccessPolicy(
                        $this->rules,
                        new SseAccessSubjects($this->pdo),
                        fn (int $u, int $t, string $slug): bool => in_array($slug, $perms, true),
                        fn (?int $u, int $t, string $mode): bool => $door,
                        fn (): string => self::NOW,
                        fn (int $u, int $t): bool => false,
                    );
                    foreach ($this->oldGuards() as $cell => $old) {
                        [$section, $level] = explode('.', $cell);
                        self::assertSame(
                            $old($perms, $guest, $door),
                            $policy->allows($guest ? null : 30, $section, $level, ['tenant_id' => 1, 'guest' => $guest]),
                            "$name · $cell · invité=" . ($guest ? 'oui' : 'non') . ' · porte=' . ($door ? 'oui' : 'non')
                        );
                        ++$checked;
                    }
                }
            }
        }
        self::assertGreaterThan(1000, $checked);
    }

    public function testCatalogCoversEveryCellAndKeepsExistenceOnTheReadProfile(): void
    {
        self::assertCount(60, array_filter($this->oldGuards(), static fn ($f): bool => true));
        foreach (C::SECTIONS as $key => $def) {
            foreach (['read', 'write', 'add', 'delete', 'export', 'manage'] as $lv) {
                self::assertTrue(isset($def['legacy'][$lv]), "$key.$lv déclaré");
                self::assertTrue(isset(C::PROFILES[$def['legacy'][$lv]]), "$key.$lv : profil connu");
            }
            self::assertSame(C::legacyProfile($key, 'read'), C::legacyProfile($key, 'exists'), "$key : l'existence suit la lecture");
        }
    }

    public function testRulesFromAnotherTenantNeverApply(): void
    {
        $this->addRule('dossiers', 'write', 'user', 12, 'allow', null, null, 2);
        self::assertFalse($this->allow(12, 'dossiers', 'write'), 'la règle d’une autre communauté est ignorée');
    }

    public function testRulesDoNothingWhenTheTablesAreMissing(): void
    {
        $this->pdo->exec('DROP TABLE sse_access_rules');
        $repo = new SseAccessRuleRepository($this->pdo);
        $policy = new SseAccessPolicy($repo, new SseAccessSubjects($this->pdo), fn (int $u, int $t, string $s): bool => in_array($s, $this->perms[$u] ?? [], true), fn (?int $u, int $t, string $m): bool => true, fn (): string => self::NOW, fn (int $u, int $t): bool => false);
        self::assertTrue($policy->allows(11, 'dossiers', 'write', $this->ctx()));
        self::assertFalse($policy->allows(12, 'dossiers', 'write', $this->ctx()));
        self::assertSame([], $repo->liveRules(1));
        self::assertSame(0, $repo->insert(1, ['section' => 'dossiers', 'level' => 'read', 'subject_type' => 'user', 'subject_id' => 1, 'effect' => 'allow', 'starts_at' => null, 'ends_at' => null, 'reason' => '', 'source' => ''], 1, self::NOW));
    }

    // ------------------------------------------------------------------ Accord, refus, dates

    public function testAllowRuleExtendsAMemberWhoPassedTheDoor(): void
    {
        self::assertFalse($this->allow(12, 'dossiers', 'write'));
        $this->addRule('dossiers', 'write', 'user', 12, 'allow');
        self::assertTrue($this->allow(12, 'dossiers', 'write'));
        self::assertFalse($this->allow(12, 'dossiers', 'add'), 'seul le niveau accordé change');
        self::assertFalse($this->allow(12, 'papiers', 'write'), 'seule la section accordée change');
        $d = $this->policy->decide(12, 'dossiers', 'write', $this->ctx());
        self::assertSame(SseAccessDecision::ALLOW_RULE, $d->source);
    }

    public function testAnExtensionNeverOpensThePortalToSomeoneWhoDidNotPassTheDoor(): void
    {
        $this->addRule('dossiers', 'read', 'user', 13, 'allow');
        $this->addRule('dossiers', 'write', 'user', 13, 'allow');
        self::assertFalse($this->allow(13, 'dossiers', 'read'));
        self::assertFalse($this->allow(13, 'dossiers', 'write'));
        self::assertSame(SseAccessDecision::DOOR_CLOSED, $this->policy->decide(13, 'dossiers', 'write', $this->ctx())->source);
        // Une rule de rôle, d'unité ou d'unité et sous-unités ne change rien non plus.
        $this->pdo->exec('INSERT INTO tenant_user_roles (user_id, tenant_id, role_id) VALUES (13, 1, 1)');
        $this->addRule('papiers', 'write', 'role', 1, 'allow');
        self::assertFalse($this->allow(13, 'papiers', 'write'));
    }

    public function testExplicitDenyBeatsEveryAllowAndTheOriginalGuard(): void
    {
        self::assertTrue($this->allow(11, 'dossiers', 'write'), 'avant : la garde d’origine ouvre');
        $this->addRule('dossiers', 'write', 'role', 1, 'deny');
        self::assertFalse($this->allow(11, 'dossiers', 'write'));
        $this->addRule('dossiers', 'write', 'user', 11, 'allow');
        self::assertFalse($this->allow(11, 'dossiers', 'write'), 'un accord ne rattrape pas un refus explicite');
        self::assertSame(SseAccessDecision::DENY_RULE, $this->policy->decide(11, 'dossiers', 'write', $this->ctx())->source);
        self::assertTrue($this->allow(11, 'dossiers', 'read'), 'les autres niveaux ne bougent pas');
    }

    public function testDenyOnReadTakesTheLevelsThatDependOnIt(): void
    {
        $this->addRule('papiers', 'read', 'user', 11, 'deny');
        foreach (['read', 'write', 'add', 'delete', 'export'] as $lv) {
            self::assertFalse($this->allow(11, 'papiers', $lv), $lv);
        }
        self::assertTrue($this->allow(11, 'papiers', 'exists'), 'on voit toujours que la section existe');
        self::assertTrue($this->allow(11, 'dossiers', 'write'), 'les autres sections ne bougent pas');
        $this->addRule('analyse', 'exists', 'user', 11, 'deny');
        foreach (['exists', 'read', 'write', 'add', 'delete', 'export'] as $lv) {
            self::assertFalse($this->allow(11, 'analyse', $lv), 'existence refusée : ' . $lv);
        }
    }

    public function testDatesStartAndEndAreJudgedAtRequestTime(): void
    {
        $this->addRule('dossiers', 'write', 'user', 12, 'allow', '2026-11-16 00:00:00', null);
        self::assertFalse($this->allow(12, 'dossiers', 'write'), 'pas encore commencée');
        $this->addRule('analyse', 'write', 'user', 12, 'allow', null, '2026-11-15 11:59:59');
        self::assertFalse($this->allow(12, 'analyse', 'write'), 'échue');
        $this->addRule('papiers', 'write', 'user', 12, 'allow', '2026-11-01 00:00:00', '2026-11-30 23:59:59');
        self::assertTrue($this->allow(12, 'papiers', 'write'), 'en cours');
        $this->addRule('papiers', 'add', 'user', 11, 'deny', '2026-11-16 00:00:00', null);
        self::assertTrue($this->allow(11, 'papiers', 'add'), 'un refus à venir ne ferme rien aujourd’hui');
    }

    public function testRevokedRulesStopApplying(): void
    {
        $id = $this->addRule('dossiers', 'write', 'user', 12, 'allow');
        self::assertTrue($this->allow(12, 'dossiers', 'write'));
        self::assertTrue($this->rules->revoke(1, $id, 10, self::NOW));
        $this->policy->reset();
        self::assertFalse($this->allow(12, 'dossiers', 'write'));
        self::assertFalse($this->rules->revoke(2, $id, 10, self::NOW), 'une autre communauté ne retire pas la règle');
    }

    // ------------------------------------------------------------------ Bénéficiaires

    public function testRoleRuleAppliesToItsMembersOnly(): void
    {
        $this->addRule('photos', 'delete', 'role', 2, 'allow');
        self::assertTrue($this->allow(12, 'photos', 'delete'), 'membre du niveau d’accès Analystes');
        self::assertFalse($this->allow(16, 'photos', 'delete'), 'un autre membre habilité n’est pas concerné');
    }

    public function testUnitRuleDoesNotReachSubUnitsButTheTreeRuleDoes(): void
    {
        $this->addRule('dossiers', 'export', 'unit', 100, 'allow');
        self::assertFalse($this->allow(11, 'dossiers', 'export'), 'le membre est dans la sous-unité 101 seulement');
        $this->addRule('dossiers', 'export', 'unit_tree', 100, 'allow');
        self::assertTrue($this->allow(11, 'dossiers', 'export'), 'avec les sous-unités');
        self::assertFalse($this->allow(12, 'dossiers', 'export'), 'une autre unité n’est pas concernée');
        $this->addRule('dossiers', 'export', 'unit', 101, 'deny');
        self::assertFalse($this->allow(11, 'dossiers', 'export'), 'le refus de l’unité directe l’emporte');
    }

    public function testGuestSessionsAndAccountlessCallersAreNeverSubjectToRules(): void
    {
        $this->addRule('dossiers', 'read', 'user', 11, 'deny');
        self::assertTrue($this->policy->allows(null, 'dossiers', 'read', ['tenant_id' => 1, 'guest' => true, 'legacy' => true]) , 'invité : la garde d’origine seule');
        self::assertFalse($this->policy->allows(null, 'dossiers', 'write', ['tenant_id' => 1, 'guest' => true]), 'un invité n’écrit jamais');
    }

    public function testPlatformAdministratorIsNeverSubjectToRules(): void
    {
        $this->addRule('dossiers', 'read', 'user', 14, 'deny');
        $this->addRule('dossiers', 'write', 'user', 14, 'deny');
        self::assertTrue($this->allow(14, 'dossiers', 'write'));
        self::assertSame(SseAccessDecision::PLATFORM, $this->policy->decide(14, 'dossiers', 'write', $this->ctx())->source);
    }

    public function testUnknownSectionLevelAndCellsWithoutObjectAreRefused(): void
    {
        self::assertFalse($this->allow(10, 'inconnue', 'read'));
        self::assertFalse($this->allow(10, 'dossiers', 'inconnu'));
        self::assertFalse($this->allow(10, 'journal', 'write'), 'le journal est en lecture seule');
        self::assertSame(SseAccessDecision::INVALID, $this->policy->decide(10, 'journal', 'delete', $this->ctx())->source);
    }

    public function testContextCanCarryTheCallersOwnOriginalGuard(): void
    {
        self::assertTrue($this->policy->allows(12, 'analyse', 'write', $this->ctx(['legacy' => true])));
        $this->addRule('analyse', 'write', 'user', 12, 'deny');
        self::assertFalse($this->policy->allows(12, 'analyse', 'write', $this->ctx(['legacy' => true])), 'le refus l’emporte aussi sur la garde de l’appelant');
    }

    public function testManagingRightsStaysWithTheExistingManagementRightAndRulesCannotChangeIt(): void
    {
        self::assertTrue($this->policy->canManageRights(10, $this->ctx()));
        self::assertFalse($this->policy->canManageRights(11, $this->ctx()));
        $this->addRule('dossiers', 'manage', 'user', 11, 'allow');
        $this->addRule('dossiers', 'manage', 'user', 10, 'deny');
        $this->addRule('dossiers', 'read', 'user', 10, 'deny');
        self::assertFalse($this->policy->canManageRights(11, $this->ctx()), 'aucune règle ne donne le droit de modifier les droits');
        self::assertTrue($this->policy->canManageRights(10, $this->ctx()), 'aucune règle ne le retire');
    }

    // ------------------------------------------------------------------ Écriture : garde-fous

    /** @return array<string, mixed> */
    private function input(array $over = []): array
    {
        return $over + [
            'section' => 'dossiers', 'levels' => ['write'], 'subject_type' => 'role', 'subject_id' => 2, 'effect' => 'allow',
            'starts_at' => '', 'ends_at' => '', 'reason' => '',
        ];
    }

    public function testOnlyTheManagementRightMayWriteRules(): void
    {
        $plan = $this->service->plan(1, 11, $this->input());
        self::assertSame([], $plan['rules']);
        self::assertStringContainsString('droit de gestion', $plan['errors'][0]);
        self::assertSame([], $this->service->commit(1, 11, 'x', [['section' => 'dossiers', 'level' => 'write', 'subject_type' => 'role', 'subject_id' => 2, 'effect' => 'allow', 'starts_at' => null, 'ends_at' => null, 'reason' => '', 'source' => '']]));
        self::assertSame(0, $this->rules->countLive(1));
        self::assertFalse($this->service->revoke(1, 11, 'x', 1)['ok']);
    }

    public function testNoEscalationYouCannotGrantWhatYouDoNotHold(): void
    {
        // 15 attribue les accès (donc gère les dossiers) mais n'a pas le droit d'export : il accorde l'écriture, pas l'export.
        $plan = $this->service->plan(1, 15, $this->input(['levels' => ['write', 'export']]));
        self::assertCount(1, $plan['rules']);
        self::assertSame('write', $plan['rules'][0]['level']);
        self::assertCount(1, $plan['skipped']);
        $okPlan = $this->service->plan(1, 10, $this->input(['levels' => ['write', 'export']]));
        self::assertCount(2, $okPlan['rules'], '10 possède les deux');
    }

    public function testCannotLiftADenyOnALevelYouDoNotHold(): void
    {
        $id = $this->addRule('dossiers', 'export', 'role', 2, 'deny');
        $res = $this->service->revoke(1, 15, 'quinze', $id);
        self::assertFalse($res['ok'], '15 n’a pas l’export : il ne lève pas le refus');
        self::assertTrue($this->service->revoke(1, 10, 'dix', $id)['ok']);
    }

    public function testReasonIsRequiredForIndividualExceptionsDeniesAndTheManageLevel(): void
    {
        self::assertSame([], $this->service->plan(1, 10, $this->input(['subject_type' => 'user', 'subject_id' => 12]))['rules']);
        self::assertSame([], $this->service->plan(1, 10, $this->input(['effect' => 'deny']))['rules']);
        self::assertSame([], $this->service->plan(1, 10, $this->input(['levels' => ['manage'], 'section' => 'dossiers']))['rules']);
        self::assertCount(1, $this->service->plan(1, 10, $this->input(['subject_type' => 'user', 'subject_id' => 12, 'reason' => 'Renfort pour l’enquête du jour']))['rules']);
        self::assertCount(1, $this->service->plan(1, 10, $this->input())['rules'], 'un niveau d’accès accordé sans motif reste permis');
    }

    public function testBeneficiariesFromAnotherCommunityOrHiddenMembersAreRejected(): void
    {
        foreach ([['role', 9], ['unit', 900], ['unit_tree', 900], ['user', 20], ['role', 777], ['bidule', 1]] as [$type, $id]) {
            $plan = $this->service->plan(1, 10, $this->input(['subject_type' => $type, 'subject_id' => $id, 'reason' => 'motif valable']));
            self::assertSame([], $plan['rules'], "$type:$id");
            self::assertSame(['Bénéficiaire inconnu.'], $plan['errors'], "$type:$id");
        }
    }

    public function testInvalidInputIsRefused(): void
    {
        self::assertSame([], $this->service->plan(1, 10, $this->input(['section' => 'nulle']))['rules']);
        self::assertSame([], $this->service->plan(1, 10, $this->input(['levels' => []]))['rules']);
        self::assertSame([], $this->service->plan(1, 10, $this->input(['levels' => ['inconnu']]))['rules']);
        self::assertSame([], $this->service->plan(1, 10, $this->input(['effect' => 'peut-etre']))['rules']);
        self::assertSame([], $this->service->plan(1, 10, $this->input(['starts_at' => '2026-13-45']))['rules']);
        self::assertSame([], $this->service->plan(1, 10, $this->input(['starts_at' => '2026-12-10', 'ends_at' => '2026-12-01']))['rules']);
        self::assertSame([], $this->service->plan(1, 10, $this->input(['ends_at' => '2026-11-01']))['rules'], 'une fin déjà passée');
        $planned = $this->service->plan(1, 10, $this->input(['levels' => ['write'], 'section' => 'journal']));
        self::assertSame([], $planned['rules'], 'le journal n’a pas d’écriture');
        self::assertCount(1, $planned['skipped']);
    }

    public function testDatesAreNormalised(): void
    {
        $plan = $this->service->plan(1, 10, $this->input(['starts_at' => '2026-11-20', 'ends_at' => '2026-12-01']));
        self::assertSame('2026-11-20 00:00:00', $plan['rules'][0]['starts_at']);
        self::assertSame('2026-12-01 23:59:59', $plan['rules'][0]['ends_at']);
        $plan = $this->service->plan(1, 10, $this->input(['starts_at' => '2026-11-20T08:30', 'ends_at' => '']));
        self::assertSame('2026-11-20 08:30:00', $plan['rules'][0]['starts_at']);
        self::assertNull($plan['rules'][0]['ends_at']);
    }

    public function testIdenticalRulesAreNotDoubled(): void
    {
        $plan = $this->service->plan(1, 10, $this->input());
        $ids = $this->service->commit(1, 10, 'Dix', $plan['rules']);
        self::assertCount(1, $ids);
        $again = $this->service->plan(1, 10, $this->input());
        self::assertSame([], $again['rules']);
        self::assertCount(1, $again['skipped']);
    }

    public function testADenyCannotRemoveReadingFromEveryManager(): void
    {
        // Les gestionnaires : 10, 14 (admin), 15 et l'auteur ; refuser la lecture aux seuls 10 et 15 laisse 14 (jamais soumis aux règles).
        $this->pdo->exec("INSERT INTO roles (id, tenant_id, name, slug) VALUES (5, 1, 'Commandement', 'cmd')");
        $this->pdo->exec('INSERT INTO role_permissions (role_id, permission_id) VALUES (5, 1)');
        $this->pdo->exec('INSERT INTO tenant_user_roles (user_id, tenant_id, role_id) VALUES (10, 1, 5), (15, 1, 5)');
        $this->platform = [];
        $this->perms[14] = [];
        // Plus d'administrateur de plateforme : refuser la lecture au niveau « Commandement » les verrouillerait tous.
        $plan = $this->service->plan(1, 10, $this->input(['section' => 'papiers', 'levels' => ['read'], 'subject_type' => 'role', 'subject_id' => 5, 'effect' => 'deny', 'reason' => 'test de verrouillage']));
        self::assertSame([], $plan['rules']);
        self::assertStringContainsString('gestionnaires SSE', $plan['errors'][0]);
        // En laissant un gestionnaire hors du refus, la règle passe.
        $plan = $this->service->plan(1, 10, $this->input(['section' => 'papiers', 'levels' => ['read'], 'subject_type' => 'user', 'subject_id' => 15, 'effect' => 'deny', 'reason' => 'test sans verrouillage']));
        self::assertCount(1, $plan['rules']);
    }

    public function testCommitWritesHistoryAndTheSseAuditLog(): void
    {
        $plan = $this->service->plan(1, 10, $this->input(['subject_type' => 'user', 'subject_id' => 12, 'reason' => 'Renfort pour l’enquête']));
        $ids = $this->service->commit(1, 10, 'Dix', $plan['rules']);
        self::assertCount(1, $ids);
        $history = $this->rules->history(1);
        self::assertCount(1, $history);
        self::assertSame('create', $history[0]['action']);
        self::assertSame('Dix', $history[0]['actor_label']);
        self::assertSame('Renfort pour l’enquête', $history[0]['reason']);
        self::assertSame('m12', 'm' . $history[0]['subject_id']);
        self::assertCount(1, $this->audit);
        self::assertSame('access_rule.create', $this->audit[0]['action']);
        self::assertSame(10, $this->audit[0]['actor_user_id']);
        self::assertSame('Renfort pour l’enquête', $this->audit[0]['reason']);

        $res = $this->service->revoke(1, 10, 'Dix', $ids[0], 'Fin de la mission');
        self::assertTrue($res['ok']);
        $history = $this->rules->history(1);
        self::assertSame('revoke', $history[0]['action']);
        self::assertSame('Fin de la mission', $history[0]['reason']);
        self::assertSame('access_rule.revoke', $this->audit[1]['action']);
        self::assertSame(0, $this->rules->countLive(1));
    }

    public function testRevokingARuleOfAnotherCommunityIsRefused(): void
    {
        $id = $this->addRule('dossiers', 'write', 'user', 20, 'allow', null, null, 2);
        $res = $this->service->revoke(1, 10, 'Dix', $id);
        self::assertFalse($res['ok']);
        self::assertNotNull($this->rules->find(2, $id));
        self::assertNull($this->rules->find(1, $id), 'la règle d’une autre communauté est introuvable');
    }

    public function testTemplatesApplyAndCanBeEditedRuleByRule(): void
    {
        $plan = $this->service->planTemplate(1, 10, 'analystes', ['subject_type' => 'role', 'subject_id' => 2, 'restrict_rest' => 1, 'reason' => 'Analystes en lecture seule']);
        self::assertSame([], $plan['errors']);
        $ids = $this->service->commit(1, 10, 'Dix', $plan['rules'], 'template');
        self::assertGreaterThan(8, count($ids));
        // Un analyste lit mais n'écrit plus, même s'il avait la gestion des dossiers.
        $this->pdo->exec('INSERT INTO tenant_user_roles (user_id, tenant_id, role_id) VALUES (11, 1, 2)');
        $this->policy->reset();
        self::assertTrue($this->allow(11, 'dossiers', 'read'));
        self::assertFalse($this->allow(11, 'dossiers', 'write'));
        self::assertSame('template', $this->rules->history(1)[0]['action']);
        // Retirer une des règles du modèle la rend modifiable.
        $one = $this->rules->liveRules(1)[0];
        self::assertTrue($this->service->revoke(1, 10, 'Dix', (int) $one['id'])['ok']);
        self::assertSame(count($ids) - 1, $this->rules->countLive(1));
        self::assertSame(['Modèle inconnu.'], $this->service->planTemplate(1, 10, 'nimporte', [])['errors']);
        $enq = $this->service->planTemplate(1, 10, 'enqueteurs', ['subject_type' => 'role', 'subject_id' => 1]);
        self::assertGreaterThan(0, count($enq['rules']));
        foreach ($enq['rules'] as $r) {
            self::assertSame('allow', $r['effect']);
            self::assertTrue(in_array($r['level'], ['exists', 'read', 'write', 'add'], true));
        }
    }

    // ------------------------------------------------------------------ Lecture pour l'écran

    public function testEffectiveRightsExplainWhereEachRightComesFrom(): void
    {
        $this->addRule('dossiers', 'export', 'user', 12, 'allow');
        $this->addRule('dossiers', 'write', 'user', 11, 'deny');
        $matrix = new SseAccessMatrix($this->policy, new class extends SseAccessDirectory {
            public function __construct()
            {
            }
        });
        $e12 = $matrix->effectiveForMember(1, 12);
        self::assertTrue($e12['entry']);
        $byKey = [];
        foreach ($e12['sections'] as $s) {
            $byKey[$s['key']] = $s['cells'];
        }
        self::assertTrue($byKey['dossiers']['export']['allowed']);
        self::assertSame(SseAccessDecision::ALLOW_RULE, $byKey['dossiers']['export']['source']);
        self::assertStringContainsString('Accordé par la règle', $byKey['dossiers']['export']['reason']);
        self::assertTrue($byKey['dossiers']['read']['allowed']);
        self::assertSame(SseAccessDecision::LEGACY, $byKey['dossiers']['read']['source']);
        self::assertFalse($byKey['dossiers']['write']['allowed']);
        $e11 = $matrix->effectiveForMember(1, 11);
        $dossiers = $e11['sections'][array_search('dossiers', array_column($e11['sections'], 'key'), true)]['cells'];
        self::assertFalse($dossiers['write']['allowed']);
        self::assertStringContainsString('le refus l’emporte', $dossiers['write']['reason']);
        $e13 = $matrix->effectiveForMember(1, 13);
        self::assertFalse($e13['entry']);
        foreach ($e13['sections'] as $s) {
            foreach ($s['cells'] as $cell) {
                self::assertFalse($cell['allowed'], 'sans droit d’entrer, aucun droit');
            }
        }
    }

    public function testSimulationCountsWhoWouldGainOrLoseAccess(): void
    {
        $matrix = new SseAccessMatrix($this->policy, new class extends SseAccessDirectory {
            public function __construct()
            {
            }

            public function visible(int $tenantId, string $query = '', int $limit = 300): array
            {
                return ['named' => [['user_id' => 12, 'label' => 'Membre', 'unit' => ''], ['user_id' => 11, 'label' => 'Enquêteur', 'unit' => '']], 'anonymized' => 0, 'anonymized_ids' => []];
            }
        });
        $plan = $this->service->plan(1, 10, $this->input(['subject_type' => 'role', 'subject_id' => 2]));
        $sim = $matrix->simulate(1, $plan['rules']);
        self::assertSame(1, $sim['gained'], 'le membre du niveau Analystes gagnerait l’écriture');
        self::assertSame(0, $sim['lost']);
        self::assertSame(['Membre'], $sim['sample_gained']);
        $deny = $this->service->plan(1, 10, $this->input(['subject_type' => 'role', 'subject_id' => 1, 'effect' => 'deny', 'reason' => 'simulation d’un refus']));
        $sim = $matrix->simulate(1, $deny['rules']);
        self::assertSame(1, $sim['lost']);
        self::assertSame(['Enquêteur'], $sim['sample_lost']);
        // Rien n'a été écrit.
        self::assertSame(0, $this->rules->countLive(1));
    }

    public function testSectionOfPathMapsPortalPages(): void
    {
        self::assertSame('photos', C::sectionForPath('/atak/sse/fichiers/photos'));
        self::assertSame('fichiers', C::sectionForPath('/public/atak/sse/fichiers/sujet/12?x=1'));
        self::assertSame('extractions', C::sectionForPath('atak/sse/fichiers/telephones/4'));
        self::assertSame('lore', C::sectionForPath('atak/sse/fichiers/lore/3/personnes'));
        self::assertSame('journal', C::sectionForPath('atak/sse/fichiers/journal'));
        self::assertSame('papiers', C::sectionForPath('atak/sse/papiers/membres/4/couverture'));
        self::assertSame('dossiers', C::sectionForPath('atak/sse/dossiers/5/pdf'));
        self::assertSame('analyse', C::sectionForPath('atak/sse/workspace'));
        self::assertSame('extractions', C::sectionForPath('atak/sse/exploitation-numerique/supports'));
        self::assertNull(C::sectionForPath('atak/sse/accueil'));
        self::assertNull(C::sectionForPath('atak/sse/droits'));
        self::assertNull(C::sectionForPath('atak/sse/acces'));
        self::assertNull(C::sectionForPath('atak/sse/recherche'));
        self::assertNull(C::sectionForPath('atak/sse'));
        self::assertNull(C::sectionForPath('dossiers'));
        self::assertSame(['role', 12], C::parseSubjectRef('role:12'));
        self::assertNull(C::parseSubjectRef('role:0'));
        self::assertNull(C::parseSubjectRef('admin:1'));
        self::assertNull(C::parseSubjectRef('user:12;DROP'));
    }
}
