<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminSseAccessController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAccessCodeRepository;
use App\Repositories\SseAccessRuleRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessDirectory;
use App\Services\Sse\SseAccessHub;
use App\Services\Sse\SseAccessMatrix;
use App\Services\Sse\SseAccessMemberRights;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseAccessRuleService;
use App\Services\Sse\SseAccessSubjects;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseMemberClearance;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Centre de gestion des accès au renseignement : liste des personnels (entrée au portail et pourquoi, habilitation,
 * droits, dérogations, état), filtres, vue d'ensemble (chiffres, alertes), et les nouveaux POST (habilitation plafonnée
 * avec motif et journal, pas d'auto-élévation, accès par section, retrait d'une dérogation, droits exacts du bloc
 * « Accès SSE » de la fiche effectifs), puis le rendu réel des onglets selon les droits. Base SQLite en mémoire,
 * vrais services (politique, règles, annuaire restreint par une sous-classe, habilitation par fonctions injectées).
 */
final class SseAccessHubTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const TOKEN = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';
    private const NOW = '2026-11-16 12:00:00';

    private PDO $pdo;
    private SseAccessRuleRepository $rules;
    private SseAccessPolicy $policy;
    /** @var array<int, list<string>> */
    private array $perms = [];
    /** @var array<int, ?string> */
    private array $profileLevels = [];
    /** @var list<array<string, mixed>> */
    private array $audit = [];
    private string $ceiling = 'confidentiel';
    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $saved = [];
    private ?PDO $savedPdo = null;

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->saved = ['session' => $_SESSION ?? [], 'server' => $_SERVER, 'post' => $_POST, 'get' => $_GET];
        $_SESSION = ['_csrf_token' => self::TOKEN, 'tenant_id' => 1, 'user_id' => 10, 'callsign' => 'Dix'];
        $_GET = [];
        Gate::reset();
        Gate::getInstance()->setPermissions(['atak.sse.grant', 'admin.organization']);
        SseAccessPolicy::useForSession(null);

        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        foreach ([
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
            "CREATE TABLE sse_access_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT NOT NULL, code_hash TEXT NOT NULL, code_hint TEXT NOT NULL DEFAULT '',
                label TEXT NOT NULL DEFAULT '', grant_type TEXT NOT NULL DEFAULT 'member', clearance_level TEXT NOT NULL DEFAULT 'interne', case_id INT NULL, created_by INT NULL,
                expires_at TEXT NOT NULL, session_ttl_minutes INT NOT NULL DEFAULT 240, max_uses INT NOT NULL DEFAULT 1, uses_count INT NOT NULL DEFAULT 0, revoked_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE sse_access_grants_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INT NOT NULL, access_code_id INT NULL, case_id INT NULL,
                actor_user_id INT NULL, actor_label TEXT NULL, event_type TEXT NOT NULL DEFAULT 'redeem', detail TEXT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        foreach ([10 => 'Gestionnaire', 11 => 'Analyste', 12 => 'Marie Durand', 13 => 'Invité de passage', 14 => 'Protégé', 15 => 'Sans droit'] as $id => $name) {
            $this->pdo->exec("INSERT INTO users (id, tenant_id, role_id, display_name, status) VALUES ($id, 1, NULL, '$name', 'active')");
        }
        $this->savedPdo = (new \ReflectionProperty(Database::class, 'pdo'))->getValue();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);

        $this->perms = [
            10 => ['atak.sse.grant', 'atak.sse.access', 'atak.sse.case.manage', 'atak.sse.export'],
            11 => ['atak.sse.access', 'atak.sse.case.manage'],
            12 => ['atak.sse.access'],
            13 => [],
            15 => [],
        ];
        $this->profileLevels = [12 => 'confidentiel'];
        $this->audit = [];
        $this->ceiling = 'confidentiel';
        $this->rules = new SseAccessRuleRepository($this->pdo);
        $this->policy = new SseAccessPolicy(
            $this->rules,
            new SseAccessSubjects($this->pdo),
            fn (int $u, int $t, string $slug): bool => $t === 1 && in_array($slug, $this->perms[$u] ?? [], true),
            fn (?int $u, int $t, string $mode): bool => $u !== null && array_intersect(['atak.sse.access', 'atak.sse.grant'], $this->perms[$u] ?? []) !== [],
            fn (): string => self::NOW,
            fn (int $u, int $t): bool => false,
        );
        SseAccessPolicy::useForSession($this->policy);
        (new FileRateLimiter())->clear('sse_access_rules|w|1|10');
    }

    protected function tearDown(): void
    {
        (new FileRateLimiter())->clear('sse_access_rules|w|1|10');
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->saved['session'];
        $_SERVER = $this->saved['server'];
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        SseAccessPolicy::useForSession(null);
        Gate::reset();
    }

    private function directory(): SseAccessDirectory
    {
        return new class extends SseAccessDirectory {
            public function __construct()
            {
            }

            private const NAMED = [10 => ['Gestionnaire', 'Section'], 11 => ['Analyste', 'Section'], 12 => ['Marie Durand', 'Appui'], 13 => ['Invité de passage', ''], 15 => ['Sans droit', 'Appui']];

            public function member(int $tenantId, int $userId): ?array
            {
                return $tenantId === 1 && isset(self::NAMED[$userId]) ? ['user_id' => $userId, 'label' => self::NAMED[$userId][0], 'unit' => self::NAMED[$userId][1]] : null;
            }

            public function visible(int $tenantId, string $query = '', int $limit = 300): array
            {
                $named = [];
                foreach (self::NAMED as $id => [$label, $unit]) {
                    $named[] = ['user_id' => $id, 'label' => $label, 'unit' => $unit];
                }

                return $tenantId === 1 ? ['named' => $named, 'anonymized' => 1, 'anonymized_ids' => [14]] : ['named' => [], 'anonymized' => 0, 'anonymized_ids' => []];
            }
        };
    }

    private function clearance(): SseMemberClearance
    {
        return new SseMemberClearance(
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
    }

    private function rights(): SseAccessMemberRights
    {
        $service = new SseAccessRuleService($this->rules, new SseAccessSubjects($this->pdo), $this->policy, $this->directory(), function (array $row): void {
            $this->audit[] = $row;
        });

        return new SseAccessMemberRights($this->policy, $service, new SseAccessMatrix($this->policy, $this->directory()), $this->directory(), $this->clearance(), fn (): string => $this->ceiling);
    }

    /** @return list<array<string, mixed>> codes de la communauté */
    private function codes(): array
    {
        return [
            ['id' => 5, 'label' => 'Passage', 'active' => true, 'status' => 'active', 'uses_count' => 1, 'expires_at' => '2026-11-17 12:00:00'],
            ['id' => 6, 'label' => 'Réserve', 'active' => true, 'status' => 'active', 'uses_count' => 0, 'expires_at' => '2026-12-30 12:00:00'],
            ['id' => 7, 'label' => 'Ancien', 'active' => false, 'status' => 'expired', 'uses_count' => 1, 'expires_at' => '2026-10-01 12:00:00'],
        ];
    }

    private function hub(): SseAccessHub
    {
        return new SseAccessHub($this->policy, $this->directory(), $this->clearance(), static fn (int $t): array => [
            ['user_id' => 13, 'code_id' => 5, 'event_type' => 'redeem', 'detail' => 'session_membre', 'created_at' => '2026-11-16 09:00:00'],
            ['user_id' => 12, 'code_id' => null, 'event_type' => 'confidentiality_ack', 'detail' => 'version:3', 'created_at' => '2026-11-15 18:30:00'],
            ['user_id' => 15, 'code_id' => 7, 'event_type' => 'redeem', 'detail' => 'session_membre', 'created_at' => '2026-09-01 10:00:00'],
        ], static fn (int $t): array => [
            ['id' => 1, 'name' => 'Analystes', 'slugs' => ['atak.sse.access', 'atak.sse.case.manage'], 'members' => 3],
            ['id' => 2, 'name' => 'Commandement', 'slugs' => ['atak.sse.grant'], 'members' => 1],
            ['id' => 3, 'name' => 'Lecteurs habilités', 'slugs' => ['atak.sse.clearance.encadrement'], 'members' => 4],
        ]);
    }

    private function addRule(string $section, string $level, int $user, string $effect, ?string $end = null, ?string $start = null): int
    {
        $id = $this->rules->insert(1, [
            'section' => $section, 'level' => $level, 'subject_type' => 'user', 'subject_id' => $user, 'effect' => $effect,
            'starts_at' => $start, 'ends_at' => $end, 'reason' => 'test', 'source' => '',
        ], 10, '2026-11-01 00:00:00');
        $this->policy->reset();

        return $id;
    }

    /** @return array<int, array<string, mixed>> */
    private function byId(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['user_id']] = $r;
        }

        return $out;
    }

    private function seedRules(): void
    {
        $this->addRule('dossiers', 'write', 12, 'deny');
        $this->addRule('analyse', 'read', 11, 'allow', '2026-11-19 23:59:59');
        $this->addRule('analyse', 'read', 15, 'allow', '2026-11-01 23:59:59');
    }

    // ------------------------------------------------------------------ Liste des personnels

    public function testPeopleShowEntryReasonClearanceRightsAndState(): void
    {
        $this->seedRules();
        $res = $this->hub()->people(1, 10, $this->ceiling, $this->codes());
        self::assertSame(1, $res['anonymized'], 'un membre anonymisé n’est que compté');
        $p = $this->byId($res['rows']);
        self::assertSame([12, 15, 11, 10, 13], array_keys($p), 'membres nommés seulement, rangés par unité puis par nom (sans unité à la fin)');
        self::assertArrayNotHasKey(14, $p);

        self::assertSame(['command', true, 'tres_restreint', 'role', true, false], [$p[10]['why'], $p[10]['entry'], $p[10]['level'], $p[10]['level_source'], $p[10]['self'], $p[10]['may_change']]);
        self::assertSame(['role', 'confidentiel', 'role'], [$p[11]['why'], $p[11]['level'], $p[11]['level_source']]);
        self::assertSame(['role', 'confidentiel', 'profile', 'restricted'], [$p[12]['why'], $p[12]['level'], $p[12]['level_source'], $p[12]['state']]);
        self::assertSame('2026-11-15 18:30:00', $p[12]['last_entry']);
        self::assertSame(['code', false, true, 'active', 'interne'], [$p[13]['why'], $p[13]['entry'], $p[13]['by_code'], $p[13]['state'], $p[13]['level']]);
        self::assertSame(['none', 'none', false], [$p[15]['why'], $p[15]['state'], $p[15]['by_code']], 'le code de 15 a expiré : plus d’accès par code');

        self::assertContains('restreint', $p[12]['flags']);
        self::assertSame(['echeance'], $p[11]['flags']);
        self::assertSame(1, $p[11]['soon']);
        self::assertSame('2026-11-19 23:59:59', $p[11]['next_end']);
        self::assertSame(['par-code', 'sans-habilitation'], $p[13]['flags']);
        self::assertSame(['sans-acces', 'echue', 'sans-habilitation'], $p[15]['flags']);

        // Droits résumés : seulement sur les sections que la personne voit ; un membre sans entrée n'a aucun droit.
        self::assertGreaterThan(0, $p[12]['rights']['voir']);
        self::assertSame(0, $p[12]['rights']['gerer']);
        self::assertSame(['voir' => 0, 'ecrire' => 0, 'gerer' => 0], $p[15]['rights']);
        self::assertGreaterThan($p[12]['rights']['ecrire'], $p[11]['rights']['ecrire'], 'le refus d’écrire dans les dossiers compte');

        // Plafond : un membre inscrit au-dessus de la personne ne se modifie pas ; ni sa propre ligne.
        $this->profileLevels[13] = 'tres_restreint';
        $p = $this->byId($this->hub()->people(1, 10, $this->ceiling, $this->codes())['rows']);
        self::assertFalse($p[13]['may_change']);
        self::assertTrue($p[12]['may_change']);
        self::assertSame([], $this->hub()->people(2, 10, $this->ceiling, [])['rows'], 'jamais une autre communauté');
    }

    public function testFiltersByUnitLevelStateAndSearch(): void
    {
        $this->seedRules();
        $rows = $this->hub()->people(1, 10, $this->ceiling, $this->codes())['rows'];
        $ids = static fn (array $r): array => array_map(static fn (array $x): int => (int) $x['user_id'], $r);

        self::assertSame([12, 15], $ids(SseAccessHub::filter($rows, ['unite' => 'Appui'])));
        self::assertSame([12, 11], $ids(SseAccessHub::filter($rows, ['niveau' => 'confidentiel'])));
        self::assertSame([15], $ids(SseAccessHub::filter($rows, ['etat' => 'sans-acces'])));
        self::assertSame([13], $ids(SseAccessHub::filter($rows, ['etat' => 'par-code'])));
        self::assertSame([11], $ids(SseAccessHub::filter($rows, ['etat' => 'echeance'])));
        self::assertSame([15], $ids(SseAccessHub::filter($rows, ['etat' => 'echue'])));
        self::assertSame([12], $ids(SseAccessHub::filter($rows, ['q' => 'durand'])));
        self::assertSame([12], $ids(SseAccessHub::filter($rows, ['unite' => 'Appui', 'niveau' => 'confidentiel'])));
        self::assertCount(5, SseAccessHub::filter($rows, ['etat' => 'inconnu', 'niveau' => 'faux']), 'filtre inconnu ignoré');
    }

    public function testOverviewFiguresAndActionableAlerts(): void
    {
        $this->seedRules();
        $rows = $this->hub()->people(1, 10, $this->ceiling, $this->codes())['rows'];
        $now = (int) strtotime(self::NOW . ' UTC');
        $log = [['ts' => $now - 3600], ['ts' => $now - 30 * 86400]];
        $o = SseAccessHub::overview($rows, $this->codes(), $log, $now);

        self::assertSame([5, 4, 1, 1], [$o['members'], $o['with'], $o['without'], $o['restricted']]);
        self::assertSame(['interne' => 2, 'encadrement' => 0, 'confidentiel' => 2, 'tres_restreint' => 1], $o['levels']);
        self::assertSame([2, 1, 1, 1], [$o['codes_valid'], $o['codes_soon'], $o['members_soon'], $o['recent']]);
        $alerts = [];
        foreach ($o['alerts'] as $a) {
            $alerts[$a['key']] = $a['n'];
        }
        self::assertSame(['echeance' => 1, 'code-sans-habilitation' => 1, 'restreint' => 1, 'echue' => 1, 'code-inutilise' => 1], $alerts);

        // Sans la liste des personnels : ni chiffres de membres ni alertes de membres.
        $blind = SseAccessHub::overview(null, $this->codes(), [], $now);
        self::assertNull($blind['members']);
        self::assertSame(['code-inutilise'], array_column($blind['alerts'], 'key'));
        self::assertSame([], SseAccessHub::overview([], [], [], $now)['alerts']);
    }

    public function testRolesCarryingClearanceAndDocumentsMatrix(): void
    {
        $roles = $this->hub()->clearanceRoles(1);
        self::assertSame(['Commandement', 'Analystes', 'Lecteurs habilités'], array_column($roles, 'name'), 'du plus haut au plus bas');
        self::assertSame(['tres_restreint', 'confidentiel', 'encadrement'], array_column($roles, 'level'));
        self::assertSame([], $this->hub()->clearanceRoles(0));

        $docs = SseAccessHub::documents(0);
        self::assertSame(['notice', 'identification', 'vigilance', 'profile', 'control'], array_column($docs, 'model'));
        foreach ($docs as $d) {
            self::assertSame(['identite', 'lieu', 'biometrie', 'source', 'horodatage'], array_keys($d['levels']));
            self::assertSame('tres_restreint', $d['levels']['source'], 'la source reste au plus haut');
        }
        self::assertSame([['section' => 'Fichiers et sujets', 'level' => 'Exporter']], array_map(static fn (array $n): array => ['section' => $n['section'], 'level' => $n['level']], $docs[1]['needs']));
    }

    // ------------------------------------------------------------------ Écritures

    private function controller(): AdminSseAccessController
    {
        $access = new SseAccessCodeService(new SseAccessCodeRepository(new Database()));

        return new AdminSseAccessController($access, new SseAccessCodeRepository(new Database()), new SseClearanceService($access), $this->rights(), $this->hub(), fn (int $t): array => []);
    }

    /** @param array<string, mixed> $post */
    private function post(array $post, string $uri = '/back-office/renseignement/acces/personnels/habilitation'): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $uri;
        $_POST = $post;

        return new Request();
    }

    private static function location(Response $r): string
    {
        return (string) $r->headerValue('Location');
    }

    public function testClearanceIsCappedNeedsAReasonIsJournalledAndWorksInBulk(): void
    {
        $c = $this->controller();
        $r = $c->peopleClearance($this->post(['_csrf_token' => self::TOKEN, 'member_ids' => ['12', '15'], 'clearance_level' => 'encadrement', 'reason' => 'Revue trimestrielle']));
        self::assertStringEndsWith('back-office/renseignement/acces?onglet=personnels', self::location($r));
        self::assertSame('Habilitation enregistrée : 2 membre(s).', Session::getFlash('success'));
        self::assertSame(['encadrement', 'encadrement'], [$this->profileLevels[12], $this->profileLevels[15]]);
        self::assertCount(2, $this->audit);
        self::assertSame(['member_clearance.set', 'member_clearance', 12, 'Revue trimestrielle', ['level' => 'confidentiel'], ['level' => 'encadrement'], 10, 'Dix'],
            [$this->audit[0]['action'], $this->audit[0]['object_type'], $this->audit[0]['object_id'], $this->audit[0]['reason'], $this->audit[0]['before'], $this->audit[0]['after'], $this->audit[0]['actor_user_id'], $this->audit[0]['actor_label']]);

        // Plafond : jamais au-dessus de l'habilitation de la personne.
        $c->peopleClearance($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '12', 'clearance_level' => 'tres_restreint', 'reason' => 'Mission spéciale']));
        self::assertSame('Vous ne pouvez pas accorder un niveau plus haut que votre propre habilitation.', Session::getFlash('error'));
        // Motif obligatoire.
        $c->peopleClearance($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '12', 'clearance_level' => 'confidentiel', 'reason' => '']));
        self::assertSame('Un motif est obligatoire (5 caractères au moins).', Session::getFlash('error'));
        // Fiche inscrite au-dessus de la personne : intouchable.
        $this->profileLevels[13] = 'tres_restreint';
        $c->peopleClearance($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '13', 'clearance_level' => '', 'reason' => 'Nettoyage des fiches']));
        self::assertSame('Le niveau inscrit pour ce membre dépasse votre habilitation : vous ne pouvez pas le modifier.', Session::getFlash('error'));
        // Membre protégé par l'annuaire ou d'une autre communauté : introuvable.
        $c->peopleClearance($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '14', 'clearance_level' => 'encadrement', 'reason' => 'Mission spéciale']));
        self::assertSame('Membre introuvable.', Session::getFlash('error'));

        self::assertSame(['encadrement', 'tres_restreint'], [$this->profileLevels[12], $this->profileLevels[13]]);
        self::assertCount(2, $this->audit, 'aucun refus n’écrit au journal');

        // Retrait (niveau vide) : journalisé comme tel.
        $c->peopleClearance($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '15', 'clearance_level' => '', 'reason' => 'Fin de mission']));
        self::assertSame('Habilitation retirée : 1 membre(s).', Session::getFlash('success'));
        self::assertNull($this->profileLevels[15]);
        self::assertSame('member_clearance.remove', $this->audit[2]['action']);
    }

    public function testNoSelfElevation(): void
    {
        $c = $this->controller();
        $c->peopleClearance($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '10', 'clearance_level' => 'encadrement', 'reason' => 'Je me promeus']));
        self::assertSame('Vous ne pouvez pas modifier votre propre habilitation.', Session::getFlash('error'));
        self::assertArrayNotHasKey(10, $this->profileLevels);
        // Le service le refuse aussi, quel que soit l'appelant.
        self::assertFalse($this->rights()->changeClearance(1, 10, 'Dix', 10, 'encadrement', 'Je me promeus')['ok']);
        self::assertFalse($this->clearance()->change(1, 10, 'Dix', 10, 'encadrement', 'Je me promeus', 'tres_restreint')['ok']);
        self::assertFalse($this->rights()->overview(1, 10, 10)['clearance']['may_change'], 'sa propre fiche ne propose pas le changement');

        $c->peopleAccess($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '10', 'sections' => ['dossiers'], 'access' => 'gerer', 'reason' => 'Je me donne tout'], '/back-office/renseignement/acces/personnels/acces'));
        self::assertSame('Vous ne pouvez pas modifier vos propres accès.', Session::getFlash('error'));
        self::assertSame(0, $this->rules->countLive(1));
        self::assertSame([], $this->audit);
    }

    public function testAccessGrantRestrictAndRevokeGoThroughTheMemberRightsGuards(): void
    {
        $c = $this->controller();
        $c->peopleAccess($this->post(['_csrf_token' => self::TOKEN, 'member_ids' => ['12', '15'], 'sections' => ['analyse'], 'access' => 'voir', 'effect' => 'allow', 'ends_at' => '2026-12-31', 'reason' => 'Renfort cellule'], '/back-office/renseignement/acces/personnels/acces'));
        self::assertSame('Accès accordé : 2 membre(s).', Session::getFlash('success'));
        $live = $this->rules->liveRules(1, true);
        self::assertSame([12, 12, 15, 15], array_map(static fn (array $r): int => (int) $r['subject_id'], $live));
        self::assertSame('2026-12-31 23:59:59', $live[0]['ends_at']);
        self::assertSame(4, count($this->rules->history(1)));

        $c->peopleAccess($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '12', 'sections' => ['dossiers'], 'access' => 'ecrire', 'effect' => 'deny', 'reason' => ''], '/back-office/renseignement/acces/personnels/acces'));
        self::assertStringContainsString('Un motif est obligatoire', (string) Session::getFlash('error'));

        // Retrait d'une dérogation : motif obligatoire, seulement une règle de CE membre.
        $rule12 = (int) $live[0]['id'];
        $rule15 = (int) $live[2]['id'];
        $c->peopleRuleRevoke($this->post(['_csrf_token' => self::TOKEN, 'reason' => ''], '/x'), ['id' => '12', 'ruleId' => (string) $rule12]);
        self::assertSame('Un motif est obligatoire (5 caractères au moins).', Session::getFlash('error'));
        $c->peopleRuleRevoke($this->post(['_csrf_token' => self::TOKEN, 'reason' => 'Fin du renfort'], '/x'), ['id' => '12', 'ruleId' => (string) $rule15]);
        self::assertSame('Règle introuvable ou déjà retirée.', Session::getFlash('error'));
        $r = $c->peopleRuleRevoke($this->post(['_csrf_token' => self::TOKEN, 'reason' => 'Fin du renfort'], '/x'), ['id' => '12', 'ruleId' => (string) $rule12]);
        self::assertTrue((bool) Session::getFlash('success'));
        self::assertStringEndsWith('?onglet=personnels&membre=12', self::location($r));
        self::assertNotNull($this->rules->find(1, $rule12)['revoked_at']);
        self::assertNull($this->rules->find(1, $rule15)['revoked_at']);
    }

    public function testWritesNeedExactlyTheEffectifsBlockRightsAndCsrf(): void
    {
        // Droit de gestion SSE mais pas l'accès aux effectifs (OrganizationAdminMiddleware) : comme sur la fiche effectifs, refusé.
        Gate::reset();
        Gate::getInstance()->setPermissions(['atak.sse.grant']);
        $c = $this->controller();
        foreach ([
            fn (): Response => $c->peopleClearance($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '12', 'clearance_level' => 'encadrement', 'reason' => 'Revue trimestrielle'])),
            fn (): Response => $c->peopleAccess($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '12', 'sections' => ['analyse'], 'access' => 'voir', 'reason' => 'Renfort cellule'])),
            fn (): Response => $c->peopleRuleRevoke($this->post(['_csrf_token' => self::TOKEN, 'reason' => 'Fin du renfort']), ['id' => '12', 'ruleId' => '1']),
        ] as $call) {
            self::assertStringEndsWith('back-office/renseignement/acces', self::location($call()));
            self::assertSame('Action non autorisée.', Session::getFlash('error'));
        }
        // Sans aucun droit d'accès à la page : la garde commune.
        Gate::reset();
        Gate::getInstance()->setPermissions(['personnel.profile.update']);
        $r = $c->peopleClearance($this->post(['_csrf_token' => self::TOKEN, 'member_id' => '12', 'clearance_level' => 'encadrement', 'reason' => 'Revue trimestrielle']));
        self::assertStringEndsWith('back-office', self::location($r));
        self::assertSame('Seul le commandement peut gérer les accès au renseignement.', Session::getFlash('error'));
        // Jeton CSRF faux.
        Gate::reset();
        Gate::getInstance()->setPermissions(['atak.sse.grant', 'admin.organization']);
        $c->peopleClearance($this->post(['_csrf_token' => 'faux', 'member_id' => '12', 'clearance_level' => 'encadrement', 'reason' => 'Revue trimestrielle']));
        self::assertSame('Session expirée. Réessayez.', Session::getFlash('error'));

        self::assertSame('confidentiel', $this->profileLevels[12]);
        self::assertSame(0, $this->rules->countLive(1));
        self::assertSame([], $this->audit);
    }

    public function testMemberDrawerFragmentAndUniform404(): void
    {
        $this->addRule('analyse', 'read', 12, 'allow', '2026-12-31 23:59:59');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['onglet' => 'personnels', 'membre' => '12', 'fragment' => 'fiche'];
        $r = $this->controller()->index(new Request());
        self::assertSame(200, $r->statusCode());
        $html = $r->body();
        self::assertStringContainsString('<h2 class="bo-dialog__title" id="sseh-member-title">Marie Durand</h2>', $html);
        foreach (['Habilitation', 'Droits par section', 'Dérogations individuelles', 'Historique'] as $title) {
            self::assertStringContainsString('>' . $title . '</h3>', $html);
        }
        self::assertStringContainsString('Inscrite sur sa fiche opérateur.', $html);
        self::assertMatchesRegularExpression('#action="[^"]*/personnels/12/regles/\d+/retirer"#', $html);
        self::assertStringContainsString('name="_csrf_token" value="' . self::TOKEN . '"', $html);
        self::assertStringContainsString('data-bo-dialog-open="sseh-clearance" data-sseh-member="12"', $html);
        self::assertStringContainsString('acces-sse', $html, 'lien vers la fiche effectifs');

        foreach (['14', '20', '0', 'abc'] as $id) {
            $_GET = ['onglet' => 'personnels', 'membre' => $id, 'fragment' => 'fiche'];
            $r = $this->controller()->index(new Request());
            self::assertSame([404, ''], [$r->statusCode(), $r->body()], 'membre ' . $id);
        }

        // Sa propre fiche : aucune action.
        $_GET = ['onglet' => 'personnels', 'membre' => '10', 'fragment' => 'fiche'];
        $self = $this->controller()->index(new Request())->body();
        self::assertStringNotContainsString('data-bo-dialog-open', $self);
        self::assertStringContainsString('se font régler par un autre gestionnaire', $self);

        // Sans le droit d'écrire : la fiche se lit, sans formulaire.
        Gate::reset();
        Gate::getInstance()->setPermissions(['atak.sse.grant']);
        $_GET = ['onglet' => 'personnels', 'membre' => '12', 'fragment' => 'fiche'];
        $ro = $this->controller()->index(new Request())->body();
        self::assertStringContainsString('Marie Durand', $ro);
        self::assertStringNotContainsString('<form', $ro);
        self::assertStringNotContainsString('data-bo-dialog-open', $ro);
    }

    // ------------------------------------------------------------------ Rendu des onglets

    /** @param array<string, mixed> $vars */
    private function render(array $vars): string
    {
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render(self::ROOT . '/views/admin/sse_access/index.php', $vars);
    }

    /** @return array<string, mixed> */
    private function pageVars(bool $canPeople, bool $canWrite, array $filters = []): array
    {
        $this->seedRules();
        $hub = $this->hub();
        $people = $canPeople ? $hub->people(1, 10, $this->ceiling, $this->codes()) : null;
        $codes = $this->codes();

        return [
            'canManage' => true,
            'canPeople' => $canPeople,
            'canWrite' => $canWrite,
            'canFiles' => false,
            'selfId' => 10,
            'tabs' => array_values(array_filter(AdminSseAccessController::TABS, static fn (string $t): bool => $canPeople || !in_array($t, AdminSseAccessController::TABS_MANAGE, true))),
            'activeTab' => $canPeople ? 'personnels' : 'ensemble',
            'maxLevel' => $this->ceiling,
            'codes' => $codes,
            'cases' => [],
            'actionLog' => [],
            'people' => $people,
            'peopleRows' => $people !== null ? SseAccessHub::filter($people['rows'], $filters) : [],
            'filters' => $filters,
            'overview' => SseAccessHub::overview($people['rows'] ?? null, $codes, [], (int) strtotime(self::NOW . ' UTC')),
            'grantable' => $this->clearance()->grantable($this->ceiling),
            'sections' => $canPeople ? $hub->sections(1, 10) : [],
            'board' => $canPeople ? $hub->board(1, 10) : [],
            'clearanceRoles' => $hub->clearanceRoles(1),
            'documents' => SseAccessHub::documents(0),
            'journal' => [
                ['ts' => 1763290000, 'action' => 'member_clearance.set', 'subject' => 'Marie Durand', 'actor' => 'Dix', 'detail' => 'Revue trimestrielle'],
                ['ts' => 1763280000, 'action' => 'redeem', 'subject' => 'Passage', 'actor' => 'Invité de passage', 'detail' => 'session_membre'],
            ],
        ];
    }

    private static function hits(string $pattern, string $html): int
    {
        return preg_match_all($pattern, $html);
    }

    public function testTabsFollowRightsAndPeopleTabHasFiltersSelectionAndActions(): void
    {
        $html = $this->render($this->pageVars(true, true, ['unite' => 'Appui']));

        self::assertSame(8, self::hits('/role="tab"/', $html));
        self::assertSame(8, self::hits('/role="tabpanel"/', $html));
        self::assertSame(7, self::hits('/role="tabpanel"[^>]*\shidden>/', $html));
        self::assertMatchesRegularExpression('/id="sseacc-tab-personnels"[^>]*aria-selected="true"/', $html);
        foreach (['01' => 'Vue d’ensemble', '02' => 'Personnels', '03' => 'Documents', '04' => 'Droits SSE et portail', '05' => 'Codes émis', '06' => 'Délivrer un code', '07' => 'Niveaux de diffusion', '08' => 'Journal'] as $i => $title) {
            self::assertMatchesRegularExpression('#<span class="bo-section__index" aria-hidden="true">/ ' . $i . '</span>\s*<h2 class="bo-section__title">' . $title . '</h2>#u', $html);
        }
        // Les onglets sont de vrais liens (?onglet=…), sans JavaScript.
        self::assertMatchesRegularExpression('#role="tab" id="sseacc-tab-documents" href="[^"]*back-office/renseignement/acces\?onglet=documents"#', $html);

        // Filtres GET et lignes : filtrées côté serveur (hidden), toutes présentes pour le filtre instantané.
        self::assertMatchesRegularExpression('#<form class="sseh-filters" method="get"[^>]*>#', $html);
        self::assertStringContainsString('<input type="hidden" name="onglet" value="personnels">', $html);
        self::assertSame(5, self::hits('/<tr data-sseh-row /', $html));
        self::assertSame(3, self::hits('/<tr data-sseh-row [^>]*\shidden>/', $html), 'unité Appui : deux lignes visibles');
        self::assertMatchesRegularExpression('/<option value="Appui" selected>/', $html);
        self::assertStringContainsString('Accès par code (1)', $html);

        // Sélection multiple et actions groupées ; pas de case pour sa propre ligne.
        self::assertSame(4, self::hits('/data-sseh-pick/', $html));
        self::assertDoesNotMatchRegularExpression('/value="10" data-sseh-pick/', $html);
        self::assertSame(3, self::hits('/data-sseh-bulk-open/', $html));
        self::assertMatchesRegularExpression('#<dialog class="bo-dialog sseacc-dialog" id="sseh-clearance" aria-labelledby="sseh-clearance-title">#', $html);
        self::assertMatchesRegularExpression('#<dialog class="bo-dialog sseacc-dialog" id="sseh-access" aria-labelledby="sseh-access-title">#', $html);
        self::assertMatchesRegularExpression('#action="[^"]*back-office/renseignement/acces/personnels/habilitation"#', $html);
        self::assertMatchesRegularExpression('#action="[^"]*back-office/renseignement/acces/personnels/acces"#', $html);
        self::assertSame(2, self::hits('/<textarea name="reason" required minlength="5"/', $html), 'motif obligatoire dans les deux fenêtres');
        // Plafond : au-dessus de l'habilitation de la personne (Secret), Très secret est grisé.
        preg_match('#<dialog[^>]*id="sseh-clearance".*?</dialog>#s', $html, $m);
        self::assertMatchesRegularExpression('/value="tres_restreint" required disabled/', $m[0] ?? '');
        self::assertDoesNotMatchRegularExpression('/value="confidentiel" required disabled/', $m[0] ?? '');
        // Bouton « Habilitation » par ligne : jamais sur sa propre ligne.
        self::assertSame(4, self::hits('/data-bo-dialog-open="sseh-clearance" data-sseh-member=/', $html));
        self::assertStringNotContainsString('data-sseh-member="10"', $html);
        // Tiroir prêt à recevoir une fiche ; lien de repli vers ?membre=ID.
        self::assertMatchesRegularExpression('#<dialog class="bo-dialog sseh-drawer" id="sseh-member" aria-labelledby="sseh-member-title"#', $html);
        self::assertMatchesRegularExpression('#href="[^"]*onglet=personnels&amp;membre=12" data-sseh-fiche="12"#', $html);
        self::assertStringContainsString('membre(s) protégé(s) par l’annuaire', $html);

        // Consignes d'interface.
        self::assertDoesNotMatchRegularExpression('/eyebrow|kicker|overline|tracking-widest|uppercase/i', $html);
        self::assertDoesNotMatchRegularExpression('/fiction|simulation|usage RP/i', $html);
        self::assertSame(1, self::hits('/<h1\b/', $html));
    }

    public function testWithoutWriteRightTheListIsReadOnlyAndWithoutManageRightThereIsNoPeopleTab(): void
    {
        $ro = $this->render($this->pageVars(true, false));
        self::assertStringContainsString('id="sseacc-panel-personnels"', $ro);
        self::assertStringNotContainsString('data-sseh-pick', $ro);
        self::assertStringNotContainsString('data-sseh-bulk', $ro);
        self::assertStringNotContainsString('id="sseh-clearance"', $ro);
        self::assertStringNotContainsString('id="sseh-access"', $ro);
        self::assertStringNotContainsString('/personnels/habilitation', $ro);

        $Gate = Gate::getInstance();
        $Gate->setPermissions([]);
        $none = $this->render($this->pageVars(false, false));
        self::assertSame(7, self::hits('/role="tab"/', $none));
        self::assertStringNotContainsString('sseacc-tab-personnels', $none);
        self::assertStringNotContainsString('id="sseacc-panel-personnels"', $none);
        self::assertStringNotContainsString('id="sseh-member"', $none);
        self::assertStringNotContainsString('Membres avec accès', $none);
        self::assertStringNotContainsString('atak/sse/droits', $none);
        self::assertMatchesRegularExpression('#/ 01</span>\s*<h2 class="bo-section__title">Vue d’ensemble</h2>#u', $none);
        self::assertMatchesRegularExpression('#/ 02</span>\s*<h2 class="bo-section__title">Documents</h2>#u', $none);
    }

    public function testOverviewDocumentsSseAndJournalPanels(): void
    {
        $vars = $this->pageVars(true, true);
        $vars['activeTab'] = 'ensemble';
        $html = $this->render($vars);

        // Vue d'ensemble : tuiles chiffrées, alertes qui ouvrent la liste filtrée.
        self::assertMatchesRegularExpression('#<strong class="sseh-tile__v">4</strong><span class="sseh-tile__l">Membres avec accès</span>#u', $html);
        self::assertMatchesRegularExpression('#<strong class="sseh-tile__v">1</strong><span class="sseh-tile__l">Sans accès</span>#u', $html);
        self::assertMatchesRegularExpression('#<strong class="sseh-tile__v">2</strong><span class="sseh-tile__l">Codes valides</span>#u', $html);
        self::assertSame(5, self::hits('/class="sseh-alert sseh-alert--/', $html));
        self::assertMatchesRegularExpression('#href="[^"]*onglet=personnels&amp;etat=echeance"><strong class="sseh-alert__n">1</strong>#', $html);
        self::assertMatchesRegularExpression('#onglet=personnels&amp;etat=par-code&amp;niveau=interne"><strong class="sseh-alert__n">1</strong> <span class="sseh-alert__text">membre\(s\) sans habilitation entrent par un code encore valide#u', $html);
        // Échelle des habilitations dans la bande.
        self::assertSame(4, self::hits('/class="sseh-ladder__seg sseh-tone--/', $html));

        // Documents : modèles × rubriques, images des membres fermées par défaut, réglage par modèle.
        self::assertSame(5 * 5 + 1, self::hits('/class="sseh-cell sseh-tone--/', $html));
        self::assertStringContainsString('Images des membres', $html);
        self::assertMatchesRegularExpression('#href="[^"]*atak/sse/fiches/gestion">Régler par modèle#', $html);
        self::assertStringContainsString('Réservé à la gestion des fichiers SSE.', $html, 'consultations : droit des fichiers SSE');

        // Droits SSE : grille « qui peut quoi » avec lien de réglage, rôles qui portent une habilitation.
        self::assertGreaterThan(5, self::hits('/<tr>\s*<th scope="row"><strong class="sseacc-table__main">/', $html));
        self::assertMatchesRegularExpression('#href="[^"]*atak/sse/droits\?section=analyse">Régler#', $html);
        self::assertStringContainsString('Commandement', $html);
        self::assertStringContainsString('Lecteurs habilités', $html);

        // Journal : filtres par action et par membre concerné, détail lisible.
        self::assertMatchesRegularExpression('#<select data-sseacc-subject>#', $html);
        self::assertMatchesRegularExpression('#<option value="member_clearance.set">Habilitation inscrite</option>#u', $html);
        self::assertMatchesRegularExpression('#<option value="Marie Durand">Marie Durand</option>#u', $html);
        self::assertStringContainsString('Session membre', $html);
    }

    public function testNewTextsHaveTheirEnglishEntry(): void
    {
        $en = require self::ROOT . '/lang/en/site_auto.php';
        foreach (['Vue d’ensemble', 'Personnels', 'Documents', 'Droits SSE', 'Droits SSE et portail', 'À traiter', 'Habilitations par niveau', 'Changer l’habilitation',
            'Accorder un accès', 'Restreindre', 'Fiche du membre', 'Dérogations individuelles', 'Images des membres', 'Rôles qui portent une habilitation',
            'Vous ne pouvez pas modifier votre propre habilitation.', 'Vous ne pouvez pas modifier vos propres accès.', 'Habilitation enregistrée : %1 membre(s).',
            'Accès accordé : %1 membre(s).', 'Accès restreint : %1 membre(s).', 'Habilitation retirée : %1 membre(s).'] as $fr) {
            self::assertArrayHasKey($fr, $en, $fr);
        }
    }
}
