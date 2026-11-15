<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\MemberQualificationDisplayController;
use App\Controllers\Api\AtakApiController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\QualificationAwardRepository;
use App\Services\Audit\AuditAction;
use App\Services\Personnel\QualificationDisplayService as Display;
use App\Services\PublicCv\PublicCvDataLoader;
use App\Support\MemberFicheTabs;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Qualifications d'un membre : interrupteurs « Visible sur Arma » et « Visible sur le profil ».
 * Base SQLite réelle (vraie migration), vrai contrôleur, vrai journal d'audit, vraie lecture de l'API du jeu.
 */
final class QualificationDisplayTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const TENANT = 1;
    private const OTHER_TENANT = 2;
    private const MEMBER = 7;
    private const MANAGER = 4;
    private const THIRD = 8;
    private const STRANGER = 9;

    private MysqlDdlSqlitePdo $pdo;
    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = self::database(true);

        $started = new \ReflectionProperty(Session::class, 'started');
        $dbPdo = new \ReflectionProperty(Database::class, 'pdo');
        $instances = new \ReflectionProperty(Container::class, 'instances');
        $this->saved = [
            'started' => $started->getValue(),
            'pdo' => $dbPdo->getValue(),
            'instances' => $instances->getValue(),
            'session' => $_SESSION ?? [],
            'post' => $_POST,
            'server' => $_SERVER,
        ];
        $started->setValue(null, true);
        $dbPdo->setValue(null, $this->pdo);
        $instances->setValue(null, [QualificationAwardRepository::class => new QualificationAwardRepository($this->pdo)]);
        $_SERVER['HTTP_HOST'] = 'athena-comspec.eu';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->actAs(self::MEMBER, []);
    }

    protected function tearDown(): void
    {
        if ($this->saved === []) {
            return;
        }
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->saved['started']);
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->saved['pdo']);
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $this->saved['instances']);
        $_SESSION = $this->saved['session'];
        $_POST = $this->saved['post'];
        $_SERVER = $this->saved['server'];
        Gate::reset();
    }

    // —— Jeu de données ——

    private static function database(bool $migrated): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
        foreach ([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT)',
            'CREATE TABLE personnel_qualifications (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, definition_id INTEGER, qualification_level_id INTEGER, issuer_id INTEGER, qualification_name TEXT, level TEXT, status TEXT, admin_status TEXT, visibility_level TEXT, is_primary INTEGER DEFAULT 0, obtained_at TEXT, expires_at TEXT)',
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, code TEXT, short_name TEXT, badge_media_path TEXT, grace_period_days INTEGER, alert_before_expiry_days INTEGER, is_permanent INTEGER, uses_levels INTEGER, category_id INTEGER)',
            'CREATE TABLE qualification_levels (id INTEGER PRIMARY KEY, name TEXT, short_name TEXT, badge_media_path TEXT)',
            'CREATE TABLE qualification_issuers (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT)',
        ] as $sql) {
            $pdo->exec($sql);
        }
        if ($migrated) {
            self::migrate($pdo);
        }
        foreach ([[self::MEMBER, self::TENANT], [self::MANAGER, self::TENANT], [self::THIRD, self::TENANT], [self::STRANGER, self::OTHER_TENANT]] as [$id, $tenant]) {
            $pdo->prepare('INSERT INTO users (id, tenant_id, display_name) VALUES (?, ?, ?)')->execute([$id, $tenant, 'Membre ' . $id]);
        }
        $add = $pdo->prepare("INSERT INTO personnel_qualifications (id, tenant_id, user_id, qualification_name, status, admin_status, visibility_level, obtained_at) VALUES (?, ?, ?, ?, 'valid', 'obtained', 'normal', '2025-03-01')");
        foreach ([
            [1, self::TENANT, self::MEMBER, 'Secourisme de combat'],
            [2, self::TENANT, self::MEMBER, 'Opérateur radio'],
            [3, self::TENANT, self::MEMBER, 'Chef de groupe'],
            [10, self::TENANT, self::THIRD, 'Tireur de précision'],
            [20, self::OTHER_TENANT, self::STRANGER, 'Pilote'],
            // Même membre, mais attribution posée dans une autre communauté.
            [21, self::OTHER_TENANT, self::MEMBER, 'Qualification d’ailleurs'],
        ] as $row) {
            $add->execute($row);
        }

        return $pdo;
    }

    /** Applique la vraie migration SQL. */
    private static function migrate(PDO $pdo): void
    {
        $sql = preg_replace('/^--.*$/m', '', self::read('migrations/2026_11_20_100_qualification_display_prefs.sql')) ?? '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . '/' . $path));
    }

    private function service(?PDO $pdo = null): Display
    {
        $pdo ??= $this->pdo;

        return new Display($pdo, static function (int $userId, int $tenantId) use ($pdo): bool {
            $st = $pdo->prepare('SELECT 1 FROM users WHERE id = ? AND tenant_id = ?');
            $st->execute([$userId, $tenantId]);

            return (bool) $st->fetchColumn();
        });
    }

    /** @param list<string> $permissions */
    private function actAs(int $userId, array $permissions, int $tenantId = self::TENANT): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => $userId, 'tenant_id' => $tenantId];
        Gate::reset();
        Gate::getInstance()->setPermissions($permissions);
    }

    /**
     * @param array<string, string> $post
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function post(string $action, array $post, array $params = [], ?Display $service = null): array
    {
        $_POST = $post;
        $controller = new MemberQualificationDisplayController($service ?? $this->service());
        $response = $controller->$action(new Request(), $params);

        return [$response->statusCode(), (array) json_decode($response->body(), true)];
    }

    /** @return array<string, string> */
    private static function body(string $award, string $surface, bool $visible, bool $csrf = true): array
    {
        return ['qualification_id' => $award, 'surface' => $surface, 'visible' => $visible ? '1' : '0'] + ($csrf ? ['_csrf_token' => str_repeat('a', 64)] : []);
    }

    private function rowCount(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    /** @param list<array<string, mixed>> $rows @return list<int> */
    private static function ids(array $rows): array
    {
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    // —— Stockage ——

    public function testEverythingIsVisibleByDefaultAndTheTwoFlagsAreIndependent(): void
    {
        $svc = $this->service();
        self::assertTrue($svc->ready());
        self::assertSame([], $svc->states([1, 2, 3]), 'aucune ligne : tout est visible');

        $r = $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 1, Display::GAME, false);
        self::assertTrue($r['ok']);
        self::assertSame(1, $r['changed']);
        self::assertSame(['game' => false, 'profile' => true], $r['states'][1]);
        self::assertSame(['game' => false, 'profile' => true], $svc->states([1])[1]);

        $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 1, Display::PROFILE, false);
        $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 1, Display::GAME, true);
        self::assertSame(['game' => true, 'profile' => false], $svc->states([1])[1], 'chaque bascule ne touche que sa colonne');
        self::assertSame(1, $this->rowCount(Display::TABLE), 'une seule ligne par attribution');

        $same = $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 1, Display::GAME, true);
        self::assertTrue($same['ok']);
        self::assertSame(0, $same['changed'], 'rebasculer vers le même état ne change rien');
    }

    public function testBulkActionSwitchesAWholeColumnForThisMemberInThisCommunityOnly(): void
    {
        $svc = $this->service();
        $r = $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, null, Display::PROFILE, false);
        self::assertTrue($r['ok']);
        self::assertSame(3, $r['changed']);
        self::assertSame([1, 2, 3], array_keys($r['states']), 'ni la qualification d’un autre membre, ni celle d’une autre communauté');
        foreach ($r['states'] as $state) {
            self::assertSame(['game' => true, 'profile' => false], $state);
        }
        $back = $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, null, Display::PROFILE, true);
        self::assertSame(3, $back['changed']);
        self::assertSame([], array_filter($svc->states([1, 2, 3]), static fn (array $s): bool => !$s['profile']));
    }

    public function testMigrationStepIsIdempotentSqliteFriendlyAndHasARollback(): void
    {
        $pdo = self::database(false);
        $root = self::ROOT;
        ob_start();
        require self::ROOT . '/database/steps/4820_qualification_display_prefs.php';
        $first = (string) ob_get_clean();
        self::assertStringContainsString('Table créée', $first);
        self::assertTrue($this->service($pdo)->ready());
        $changes = $pdo->changes;
        ob_start();
        require self::ROOT . '/database/steps/4820_qualification_display_prefs.php';
        $second = (string) ob_get_clean();
        self::assertStringContainsString('déjà présente', $second);
        self::assertSame($changes, $pdo->changes, 'relancer l’étape ne modifie plus le schéma');

        $sql = self::read('migrations/2026_11_20_100_qualification_display_prefs.sql');
        self::assertStringContainsString('PRIMARY KEY (qualification_id)', $sql, 'unicité dans le CREATE TABLE');
        self::assertSame(0, preg_match('/ALTER\s+TABLE/i', $sql));
        self::assertStringContainsString('DROP TABLE IF EXISTS personnel_qualification_display', self::read('migrations/rollback/qualification_display_prefs_down.sql'));
        $registry = require self::ROOT . '/database/steps/registry.php';
        $entry = array_values(array_filter($registry, static fn (array $s): bool => ($s['id'] ?? '') === 'qualification_display_prefs'));
        self::assertCount(1, $entry);
        self::assertSame('4820_qualification_display_prefs.php', $entry[0]['file']);
    }

    // —— Droits ——

    public function testMemberSwitchesOwnQualificationsAndAManagerSwitchesForAMember(): void
    {
        $svc = $this->service();
        self::assertTrue($svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 2, Display::GAME, false)['ok'], 'soi-même, sans aucun droit de gestion');
        self::assertTrue($svc->apply(self::TENANT, self::MANAGER, true, self::MEMBER, 3, Display::PROFILE, false)['ok'], 'responsable');
        self::assertSame(['game' => false, 'profile' => true], $svc->states([2])[2]);
        self::assertSame(['game' => true, 'profile' => false], $svc->states([3])[3]);
    }

    public function testThirdPartyWithoutRightIsRefusedAndNothingIsStored(): void
    {
        $r = $this->service()->apply(self::TENANT, self::THIRD, false, self::MEMBER, 1, Display::GAME, false);
        self::assertFalse($r['ok']);
        self::assertSame(403, $r['status']);
        self::assertSame('forbidden', $r['code']);
        self::assertSame(0, $this->rowCount(Display::TABLE));
        self::assertSame(0, $this->rowCount('audit_logs'));
    }

    public function testAnotherMembersOrAnotherCommunitysQualificationCannotBeReached(): void
    {
        $svc = $this->service();
        // IDOR : le membre vise l'attribution d'un autre membre en passant par sa propre fiche.
        $idor = $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 10, Display::GAME, false);
        self::assertSame([404, 'not_found'], [$idor['status'], $idor['code']]);
        // Attribution du même membre, posée dans une autre communauté que celle de la session.
        $elsewhere = $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 21, Display::GAME, false);
        self::assertSame(404, $elsewhere['status']);
        // Un responsable ne bascule pas pour un membre d'une autre communauté.
        $foreign = $svc->apply(self::TENANT, self::MANAGER, true, self::STRANGER, 20, Display::GAME, false);
        self::assertSame(404, $foreign['status']);
        $foreignAll = $svc->apply(self::TENANT, self::MANAGER, true, self::STRANGER, null, Display::GAME, false);
        self::assertSame(404, $foreignAll['status']);
        self::assertSame(0, $this->rowCount(Display::TABLE));

        self::assertSame(422, $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 1, 'partout', false)['status'], 'endroit inconnu');
        self::assertSame(401, $svc->apply(0, self::MEMBER, false, self::MEMBER, 1, Display::GAME, false)['status'], 'sans communauté de session');
    }

    // —— Contrôleur : session, jeton, droits existants ——

    public function testControllerRequiresTheCsrfToken(): void
    {
        [$status, $json] = $this->post('toggleMine', self::body('1', Display::GAME, false, false));
        self::assertSame(403, $status);
        self::assertSame('csrf', $json['code']);
        self::assertSame(0, $this->rowCount(Display::TABLE));

        $_SERVER['HTTP_X_CSRF_TOKEN'] = str_repeat('b', 64);
        [$status] = $this->post('toggleMine', self::body('1', Display::GAME, false, false));
        self::assertSame(403, $status, 'mauvais jeton en en-tête');
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        [$status, $json] = $this->post('toggleMine', self::body('1', Display::GAME, false));
        self::assertSame(200, $status);
        self::assertTrue($json['ok']);
        self::assertSame(['game' => false, 'profile' => true], $json['states']['1']);
    }

    public function testMemberRouteOnlyEverTargetsTheSessionMember(): void
    {
        // Un identifiant de membre glissé dans la requête est ignoré : la route « moi » lit la session.
        [$status] = $this->post('toggleMine', self::body('10', Display::GAME, false) + ['id' => (string) self::THIRD, 'user_id' => (string) self::THIRD], ['id' => (string) self::THIRD]);
        self::assertSame(404, $status, 'l’attribution 10 appartient à un autre membre');
        [$status, $json] = $this->post('toggleMine', self::body('all', Display::PROFILE, false), ['id' => (string) self::THIRD]);
        self::assertSame(200, $status);
        self::assertSame(['1', '2', '3'], array_map('strval', array_keys($json['states'])));
    }

    public function testFicheRouteUsesExistingRightsAndTheSessionCommunity(): void
    {
        // Lecteur de la fiche sans droit de gestion des fiches (gestion des grades seulement) : refusé.
        $this->actAs(self::THIRD, ['personnel.grades.manage']);
        [$status, $json] = $this->post('toggle', self::body('1', Display::GAME, false), ['id' => (string) self::MEMBER]);
        self::assertSame(403, $status);
        self::assertSame('forbidden', $json['code']);

        // Responsable des fiches (droit existant personnel.profile.update) : accepté.
        $this->actAs(self::MANAGER, ['personnel.profile.update']);
        [$status, $json] = $this->post('toggle', self::body('1', Display::GAME, false), ['id' => (string) self::MEMBER]);
        self::assertSame(200, $status);
        self::assertTrue($json['ok']);

        // Le membre sur sa propre fiche, sans droit de gestion : accepté.
        $this->actAs(self::THIRD, ['organization.effectifs.hub.view']);
        [$status] = $this->post('toggle', self::body('10', Display::PROFILE, false), ['id' => (string) self::THIRD]);
        self::assertSame(200, $status);

        // Responsable d'une autre communauté : le membre visé n'existe pas dans la communauté de SA session.
        $this->actAs(self::STRANGER, ['admin.organization'], self::OTHER_TENANT);
        [$status] = $this->post('toggle', self::body('1', Display::GAME, true), ['id' => (string) self::MEMBER]);
        self::assertSame(404, $status);
        self::assertSame(['game' => false, 'profile' => true], $this->service()->states([1])[1], 'état inchangé');

        // Sans session : 401.
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        [$status] = $this->post('toggle', self::body('1', Display::GAME, true), ['id' => (string) self::MEMBER]);
        self::assertSame(401, $status);
    }

    public function testNoNewRightIsDeclaredAndManagersAreTheExistingOnes(): void
    {
        foreach ([['personnel.profile.update'], ['admin.organization'], ['admin.access']] as $permissions) {
            Gate::reset();
            Gate::getInstance()->setPermissions($permissions);
            self::assertTrue(MemberQualificationDisplayController::canManage(Gate::getInstance()), $permissions[0]);
        }
        foreach ([[], ['organization.effectifs.hub.view'], ['personnel.grades.manage'], ['personnel.assignments.manage']] as $permissions) {
            Gate::reset();
            Gate::getInstance()->setPermissions($permissions);
            self::assertFalse(MemberQualificationDisplayController::canManage(Gate::getInstance()));
        }
        self::assertStringNotContainsString('permissions', self::read('database/steps/4820_qualification_display_prefs.php'), 'aucun droit créé');
    }

    // —— Effet réel ——

    public function testHiddenOnProfileDisappearsFromSharedViewsButNotFromTheGame(): void
    {
        $svc = $this->service();
        $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 2, Display::PROFILE, false);
        $awards = (new QualificationAwardRepository($this->pdo))->listForUser(self::MEMBER, self::TENANT);
        self::assertSame([1, 2, 3], self::ids($awards), 'la fiche Effectifs du responsable continue de tout lire');

        $profile = $svc->visibleOn($awards, Display::PROFILE);
        sort($profile);
        self::assertSame([1, 3], self::ids($profile));
        self::assertCount(3, $svc->visibleOn($awards, Display::GAME), 'toujours transmise au jeu');

        // C.V. public : même règle, par le chargeur réel.
        $loader = new PublicCvDataLoader($this->pdo);
        $method = new \ReflectionMethod($loader, 'qualifications');
        $cv = self::ids($method->invoke($loader, self::MEMBER, self::TENANT));
        sort($cv);
        self::assertSame([1, 3], $cv);

        // La fiche garde la ligne, avec l'indication « masquée sur le profil ».
        $panel = $svc->panel($awards, '/x', true);
        $byId = array_column($panel['rows'], null, 'id');
        self::assertFalse($byId[2]['profile']);
        self::assertTrue($byId[2]['game']);
        self::assertTrue($byId[1]['profile']);
    }

    public function testHiddenOnArmaIsAbsentFromTheGameApiWithoutChangingItsFormat(): void
    {
        $api = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(AtakApiController::class, 'operatorQualifications');
        $before = $method->invoke($api, self::TENANT, self::MEMBER);
        self::assertSame(['Chef de groupe', 'Opérateur radio', 'Secourisme de combat'], self::sorted(array_column($before, 'name')));
        self::assertSame(['name', 'level', 'state', 'expires_at'], array_keys($before[0]));

        $this->service()->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 2, Display::GAME, false);
        // Lecture directe à chaque appel : aucun cache serveur à vider, l'effet est immédiat.
        $after = $method->invoke($api, self::TENANT, self::MEMBER);
        self::assertSame(['Chef de groupe', 'Secourisme de combat'], self::sorted(array_column($after, 'name')));
        self::assertSame(['name', 'level', 'state', 'expires_at'], array_keys($after[0]), 'format de la réponse inchangé');

        $this->service()->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 2, Display::PROFILE, false);
        $this->service()->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 2, Display::GAME, true);
        self::assertCount(3, $method->invoke($api, self::TENANT, self::MEMBER), 'masquée du profil seulement : de retour en jeu');
    }

    public function testHidingIsDisplayOnlyTheQualificationItselfIsUntouched(): void
    {
        $before = $this->pdo->query('SELECT * FROM personnel_qualifications ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->service()->apply(self::TENANT, self::MEMBER, false, self::MEMBER, null, Display::GAME, false);
        $this->service()->apply(self::TENANT, self::MEMBER, false, self::MEMBER, null, Display::PROFILE, false);
        self::assertSame($before, $this->pdo->query('SELECT * FROM personnel_qualifications ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'statut, validité et détention inchangés : les contrôles internes la comptent toujours');
        // Les lectures internes (prérequis, accès, kits, alertes) n'appellent pas le service d'affichage.
        foreach ([
            'app/Services/Personnel/JobPrerequisiteService.php',
            'app/Services/Sse/SseAccessSubjects.php',
            'app/Repositories/GameRoleKitRepository.php',
            'app/Services/Tactical/AirCrewSpecialtyService.php',
            'app/Services/Attendance/CommunityEventSlotService.php',
        ] as $file) {
            self::assertStringNotContainsString('QualificationDisplayService', self::read($file), $file);
        }
    }

    // —— Mode dégradé ——

    public function testWithoutTheMigrationEverythingStaysVisibleAndSwitchesAreOff(): void
    {
        $pdo = self::database(false);
        $svc = $this->service($pdo);
        self::assertFalse($svc->ready());
        $awards = (new QualificationAwardRepository($pdo))->listForUser(self::MEMBER, self::TENANT);
        self::assertCount(3, $svc->visibleOn($awards, Display::GAME));
        self::assertCount(3, $svc->visibleOn($awards, Display::PROFILE));
        $r = $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 1, Display::GAME, false);
        self::assertSame([409, 'not_ready'], [$r['status'], $r['code']]);

        $panel = $svc->panel($awards, '/x', true);
        self::assertFalse($panel['ready']);
        $html = self::render($panel);
        self::assertStringContainsString('Réglage indisponible tant que la mise à jour du site n’est pas appliquée', $html);
        self::assertSame(6, substr_count($html, 'role="switch"'));
        self::assertSame(6, substr_count($html, 'aria-checked="true"'), 'tout est affiché comme visible');
        self::assertSame(6, preg_match_all('/data-qd-switch="[a-z]+" disabled/', $html));
        self::assertStringNotContainsString('data-qd-url', $html, 'aucun envoi possible');
    }

    // —— Audit ——

    public function testEachSwitchIsAuditedWithActorTargetAndOldAndNewState(): void
    {
        $this->service()->apply(self::TENANT, self::MANAGER, true, self::MEMBER, 3, Display::PROFILE, false);
        $rows = $this->pdo->query('SELECT * FROM audit_logs ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $rows);
        self::assertSame(AuditAction::QUALIFICATION_DISPLAY_UPDATED, $rows[0]['action']);
        self::assertSame([self::TENANT, self::MANAGER, 'personnel_qualification', 3], [(int) $rows[0]['tenant_id'], (int) $rows[0]['user_id'], $rows[0]['entity_type'], (int) $rows[0]['entity_id']]);
        $old = (array) json_decode((string) $rows[0]['old_value'], true);
        $new = (array) json_decode((string) $rows[0]['new_value'], true);
        self::assertSame([self::MEMBER, 'Chef de groupe', 'profile', true], [$old['member_user_id'], $old['qualification'], $old['surface'], $old['visible']]);
        self::assertFalse($new['visible']);

        // Sans changement réel : pas de nouvelle ligne. En masse : une ligne par qualification réellement basculée.
        $this->service()->apply(self::TENANT, self::MANAGER, true, self::MEMBER, 3, Display::PROFILE, false);
        self::assertSame(1, $this->rowCount('audit_logs'));
        $this->service()->apply(self::TENANT, self::MEMBER, false, self::MEMBER, null, Display::PROFILE, false);
        self::assertSame(3, $this->rowCount('audit_logs'), 'deux qualifications restaient visibles');
    }

    // —— Interface ——

    public function testPanelRendersAccessibleSwitchesBulkActionsAndAReadOnlyState(): void
    {
        $svc = $this->service();
        $svc->apply(self::TENANT, self::MEMBER, false, self::MEMBER, 1, Display::GAME, false);
        $awards = (new QualificationAwardRepository($this->pdo))->listForUser(self::MEMBER, self::TENANT);

        $html = self::render($svc->panel($awards, '/back-office/ressources/effectifs/membres/7/qualifications/affichage', true));
        self::assertSame(6, substr_count($html, 'role="switch"'));
        self::assertSame(3, substr_count($html, 'aria-label="Visible sur Arma" aria-describedby='));
        self::assertSame(3, substr_count($html, 'aria-label="Visible sur le profil" aria-describedby='));
        self::assertSame(1, substr_count($html, 'aria-checked="false"'));
        self::assertStringContainsString('<button type="button" class="qd-sw" role="switch"', $html, 'bouton natif : clavier (Espace, Entrée)');
        self::assertSame(2, substr_count($html, '>Tout afficher</button>'));
        self::assertSame(2, substr_count($html, '>Tout masquer</button>'));
        self::assertStringContainsString('data-qd-url="/back-office/ressources/effectifs/membres/7/qualifications/affichage"', $html);
        self::assertStringContainsString('Obtenue le 01/03/2025', $html);
        self::assertStringContainsString('elle reste acquise et compte toujours pour les accès et les formations', $html);
        self::assertStringNotContainsString(' disabled', $html);

        // Lecteur sans droit : même état, interrupteurs désactivés, raison écrite, aucune adresse d'envoi.
        $readOnly = self::render($svc->panel($awards, '/x', false));
        self::assertSame(6, substr_count($readOnly, 'role="switch"'));
        self::assertSame(1, substr_count($readOnly, 'aria-checked="false"'));
        self::assertSame(6, preg_match_all('/data-qd-switch="[a-z]+" disabled/', $readOnly));
        self::assertStringContainsString('Lecture seule', $readOnly);
        self::assertStringNotContainsString('Tout masquer', $readOnly);
        self::assertStringNotContainsString('data-qd-url', $readOnly);
        self::assertStringNotContainsString('Action non autorisée', $readOnly);

        // Aucune qualification : un message, pas de tableau vide.
        $empty = self::render($svc->panel([], '/x', true));
        self::assertStringContainsString('Aucune qualification enregistrée', $empty);
        self::assertStringNotContainsString('role="switch"', $empty);
        // Une seule qualification : pas d'action de masse.
        self::assertStringNotContainsString('Tout masquer', self::render($svc->panel(array_slice($awards, 0, 1), '/x', true)));
    }

    public function testWiringRoutesFicheMemberPageAndHouseStyle(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("\$router->post('/back-office/ressources/effectifs/membres/{id}/qualifications/affichage', [\\App\\Controllers\\Admin\\MemberQualificationDisplayController::class, 'toggle'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]);", $routes);
        self::assertStringContainsString("\$router->post('/jnet/moi/qualifications/affichage', \$moi(\\App\\Controllers\\Admin\\MemberQualificationDisplayController::class, 'toggleMine'), \$mwMoi);", $routes);
        self::assertSame('qualifications', MemberFicheTabs::tabForAnchor('qualifications-affichage'));

        $fiche = self::read('views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString("require base_path('views/partials/qualification_display.php');", $fiche);
        self::assertStringContainsString('>Masquée sur le profil</span>', $fiche);
        self::assertStringContainsString('>Masquée sur Arma</span>', $fiche);
        self::assertStringContainsString("require base_path('views/partials/qualification_display.php');", self::read('views/admin/member_situation/qualifications.php'));

        // Effet : chaque vue partagée et l'API du jeu passent par le service ; aucune autre lecture ne filtre.
        self::assertStringContainsString('QualificationDisplayService::GAME', self::read('app/Controllers/Api/AtakApiController.php'));
        foreach (['app/Controllers/Web/PersonnelController.php', 'app/Services/PublicCv/PublicCvDataLoader.php', 'app/Services/Jnet/JnetDashboardService.php'] as $file) {
            self::assertStringContainsString('QualificationDisplayService::PROFILE', self::read($file), $file);
        }

        // Style du back-office : pas de design system SSE, textes de 13 px au moins, cibles de 44 px.
        $css = self::read('public/assets/css/qualification-display.css');
        $partial = self::read('views/partials/qualification_display.php');
        foreach ([$css, $partial, self::read('public/assets/js/qualification-display.js')] as $source) {
            self::assertSame(0, preg_match('/sse-ds|\.sse-|class="sse-|Archivo/', $source));
        }
        preg_match_all('/font(?:-size)?:\s*(?:\d+\s+)?(\d+(?:\.\d+)?)px/', $css, $sizes);
        self::assertNotEmpty($sizes[1]);
        self::assertGreaterThanOrEqual(13.0, min(array_map('floatval', $sizes[1])));
        self::assertSame(2, preg_match_all('/min-height: 44px;/', $css), 'interrupteur et boutons de masse');
        self::assertStringContainsString('@container (max-width: 620px)', $css);
    }

    /** @param list<string> $values @return list<string> */
    private static function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    /** @param array<string, mixed> $panel */
    private static function render(array $panel): string
    {
        $qualificationDisplay = $panel;
        $qdHeading = 'h3';
        $qdClass = 'eff-fm-card';
        $qdId = 'qualifications-affichage';
        ob_start();
        require self::ROOT . '/views/partials/qualification_display.php';

        return (string) ob_get_clean();
    }
}
