<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\MemberAvailabilityCheckController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\MemberAvailabilityCheckRepository;
use App\Services\Effectifs\Availability\AvailabilityCheck as Avc;
use App\Services\Effectifs\Availability\AvailabilityCheckHistory;
use App\Services\Effectifs\Availability\AvailabilityCheckRecorder as Rec;
use App\Services\Effectifs\Availability\AvailabilityFactsCollector;
use App\Services\Effectifs\Dossier\DossierCatalog;
use App\Services\Effectifs\Dossier\DossierHistoryPresenter;
use App\Services\Effectifs\Dossier\DossierStore;
use App\Services\Effectifs\Dossier\MemberDossierStepActions;
use App\Services\Effectifs\Dossier\MemberDossierTimeline;
use App\Services\Effectifs\MemberChronology;
use App\Services\Personnel\PlayerSessionService as Sessions;
use App\Support\AdministrativePositionCatalog;
use App\Support\RosterAvailabilityFilter;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\DossierFixture as F;
use Tests\Support\InMemoryDossierPorts;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Vérification de disponibilité guidée (Effectifs) : étapes et conclusion sur des jeux de données réels (actif, peu actif,
 * absence déclarée, inactif), lecture des sources sur une base SQLite, droits, jeton CSRF, enregistrement et historique,
 * validation groupée, repli sans l'étape de base de données. Vraie migration, vrais dépôts, vrai contrôleur.
 */
final class MemberAvailabilityCheckTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const ACTOR = 4;
    private const MEMBER = 7;
    private const QUIET = 9;
    private const OUTSIDER = 30;
    private const TZ = 'Europe/Paris';
    private const MANAGE = ['organization.effectifs.hub.view', 'effectifs.suivi.manage'];

    private int $now;
    private MysqlDdlSqlitePdo $pdo;
    /** @var array<string, mixed> */
    private array $saved = [];
    private InMemoryDossierPorts $ports;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->now = (new \DateTimeImmutable('2026-11-16 12:00:00', new \DateTimeZone(self::TZ)))->getTimestamp();
        $this->pdo = $this->database(true);

        $started = new \ReflectionProperty(Session::class, 'started');
        $dbPdo = new \ReflectionProperty(Database::class, 'pdo');
        $instances = new \ReflectionProperty(Container::class, 'instances');
        $this->saved = [
            'started' => $started->getValue(),
            'pdo' => $dbPdo->getValue(),
            'instances' => $instances->getValue(),
            'session' => $_SESSION ?? [],
            'post' => $_POST,
            'get' => $_GET,
            'server' => $_SERVER,
        ];
        $started->setValue(null, true);
        $dbPdo->setValue(null, $this->pdo);
        $_SERVER['HTTP_HOST'] = 'athena-comspec.eu';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->actAs(self::ACTOR, self::MANAGE);
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
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        Gate::reset();
    }

    // —— Jeu de données ——

    private function database(bool $migrated): MysqlDdlSqlitePdo
    {
        $pdo = F::pdo();
        foreach (['steam_id', 'last_login_at', 'created_at'] as $col) {
            $pdo->exec('ALTER TABLE users ADD COLUMN ' . $col);
        }
        foreach ([
            'CREATE TABLE player_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, steam_uid TEXT, started_at TEXT, ended_at TEXT, last_seen_at TEXT, duration_s INTEGER, aberrant INTEGER DEFAULT 0, source TEXT, server_id INTEGER, created_at TEXT)',
            'CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT)',
            'CREATE TABLE personnel_absences (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, starts_on TEXT, ends_on TEXT, reason TEXT, note TEXT, status TEXT, created_by INTEGER)',
            'CREATE TABLE community_events (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, title TEXT, starts_at TEXT, cancelled_at TEXT)',
            'CREATE TABLE community_event_rsvps (id INTEGER PRIMARY KEY AUTOINCREMENT, event_id INTEGER, user_id INTEGER, status TEXT, checked_in_at TEXT)',
        ] as $sql) {
            $pdo->exec($sql);
        }
        if ($migrated) {
            self::migrate($pdo);
        }
        $user = $pdo->prepare('INSERT INTO users (id, tenant_id, display_name, status, steam_id, last_login_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ([
            [self::ACTOR, F::TENANT, 'Cpt Delorme', 'active', null, $this->ago(0), '2025-01-01 10:00:00'],
            [self::MEMBER, F::TENANT, 'Sgt Marchand', 'active', '76561198000000007', $this->ago(2), '2025-02-01 10:00:00'],
            [self::QUIET, F::TENANT, 'Cal Vasseur', 'active', '76561198000000009', $this->ago(64), '2025-02-01 10:00:00'],
            [self::OUTSIDER, 2, 'Lt Ailleurs', 'active', null, null, '2025-02-01 10:00:00'],
        ] as $row) {
            $user->execute($row);
        }
        // Sgt Marchand : trois sessions qui comptent sur 10 jours, une connexion courte. Cal Vasseur : une session il y a 40 jours.
        $this->session($pdo, '76561198000000007', 9, 7200);
        $this->session($pdo, '76561198000000007', 5, 5400);
        $this->session($pdo, '76561198000000007', 2, 3900);
        $this->session($pdo, '76561198000000007', 1, 600);
        $this->session($pdo, '76561198000000009', 40, 4200);
        $pdo->prepare('INSERT INTO audit_logs (tenant_id, user_id, action, created_at) VALUES (?, ?, ?, ?)')->execute([F::TENANT, self::MEMBER, 'profile_updated', $this->ago(3)]);
        $pdo->prepare('INSERT INTO community_events (id, tenant_id, title, starts_at, cancelled_at) VALUES (1, ?, ?, ?, NULL), (2, ?, ?, ?, NULL)')
            ->execute([F::TENANT, 'Opération Héron', $this->ago(12), F::TENANT, 'Entraînement', $this->ago(30)]);
        $pdo->prepare('INSERT INTO community_event_rsvps (event_id, user_id, status, checked_in_at) VALUES (1, ?, ?, ?)')->execute([self::MEMBER, 'yes', $this->ago(12)]);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $sql = preg_replace('/^--.*$/m', '', self::read('migrations/2026_11_22_100_member_availability_checks.sql')) ?? '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . '/' . $path));
    }

    private function ago(int $days): string
    {
        return date('Y-m-d H:i:s', $this->now - $days * 86400);
    }

    private function session(PDO $pdo, string $steam, int $daysAgo, int $duration): void
    {
        $start = $this->now - $daysAgo * 86400 - 3 * 3600;
        $pdo->prepare('INSERT INTO player_sessions (tenant_id, steam_uid, started_at, ended_at, last_seen_at, duration_s, source, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([F::TENANT, $steam, date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $start + $duration), date('Y-m-d H:i:s', $start + $duration), $duration, 'heartbeat', date('Y-m-d H:i:s', $start)]);
    }

    /** @param list<string> $permissions */
    private function actAs(int $userId, array $permissions, int $tenantId = F::TENANT): void
    {
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'user_id' => $userId, 'tenant_id' => $tenantId];
        Gate::reset();
        Gate::getInstance()->setPermissions($permissions);
    }

    /**
     * Faits d'un membre, comme les relève le collecteur, avec des sessions construites par le vrai service.
     *
     * @param list<array{0: int, 1: int}> $sessions [jours, durée en secondes]
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    private function facts(array $sessions, array $over = [], bool $linked = true): array
    {
        $list = [];
        foreach ($sessions as [$days, $duration]) {
            $start = $this->now - $days * 86400 - 3 * 3600;
            $list[] = ['started_at' => $start, 'last_seen_at' => $start + $duration, 'ended_at' => $start + $duration, 'duration_s' => $duration, 'aberrant' => false, 'source' => 'heartbeat', 'ongoing' => false];
        }

        return $over + [
            'now' => $this->now,
            'tz' => self::TZ,
            'rules' => ['min_minutes' => 60, 'gap_minutes' => 25],
            'sessions' => ['ready' => true, 'summary' => Sessions::summarize($list, 3600, $this->now, self::TZ, $linked), 'recent' => []],
            'last_login_at' => $this->ago(2),
            'account_status' => 'active',
            'site_activity_at' => $this->ago(3),
            'absences' => [],
            'position' => ['slug' => AdministrativePositionCatalog::ACTIVE, 'label' => 'En service actif', 'service' => 'Oui'],
            'events' => ['sessions_90' => 3, 'present_90' => 2, 'last_present_at' => $this->ago(12)],
            'sanctions' => ['active' => 0],
            'followup' => ['open' => 1, 'late' => 0],
        ];
    }

    /** @param list<array<string, mixed>> $steps @return array<string, array<string, mixed>> */
    private static function byKey(array $steps): array
    {
        $out = [];
        foreach ($steps as $s) {
            $out[$s['key']] = $s;
        }

        return $out;
    }

    private function controller(?PDO $pdo = null): MemberAvailabilityCheckController
    {
        $pdo ??= $this->pdo;
        $timeline = new MemberDossierTimeline($pdo, new DossierStore($pdo), null, static fn (int $t, array $ids): array => array_intersect_key([4 => 'Cpt Delorme', 7 => 'Sgt Marchand', 9 => 'Cal Vasseur'], array_flip($ids)));
        $this->ports = new InMemoryDossierPorts($pdo);
        $this->ports->tenantUsers = [self::ACTOR, self::MEMBER, self::QUIET];
        $checks = new MemberAvailabilityCheckRepository($pdo);
        $recorder = new Rec($checks, $timeline->store(), new MemberDossierStepActions($timeline, $timeline->store(), $this->ports), $this->ports);
        $inTenant = static function (int $tenantId, int $memberId) use ($pdo): bool {
            $st = $pdo->prepare('SELECT 1 FROM users WHERE id = ? AND tenant_id = ?');
            $st->execute([$memberId, $tenantId]);

            return (bool) $st->fetchColumn();
        };
        $names = static fn (int $t, array $ids): array => array_intersect_key([7 => 'Sgt Marchand', 9 => 'Cal Vasseur'], array_flip($ids));

        return new MemberAvailabilityCheckController(new AvailabilityFactsCollector($pdo), $checks, $recorder, $inTenant, $names, $this->now);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function call(string $action, array $params = [], array $post = [], ?PDO $pdo = null): array
    {
        $_SERVER['REQUEST_METHOD'] = $action === 'run' ? 'GET' : 'POST';
        $_POST = $post;
        $response = $this->controller($pdo)->$action(new Request(), $params);

        return [$response->statusCode(), (array) json_decode($response->body(), true)];
    }

    private function rowCount(string $table, ?PDO $pdo = null): int
    {
        return (int) ($pdo ?? $this->pdo)->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private const CSRF = ['_csrf_token' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'];

    // —— Étapes et conclusion sur des jeux de données ——

    public function testActiveMemberIsAvailableWithEveryStepSourcedAndDated(): void
    {
        $facts = $this->facts([[9, 7200], [5, 5400], [2, 3900], [1, 600]]);
        $steps = Avc::steps($facts);
        $k = self::byKey($steps);
        self::assertSame(['sessions', 'last_session', 'site_login', 'site_activity', 'absences', 'position', 'events', 'followup'], array_column($steps, 'key'), 'ordre d’exécution');
        self::assertSame(Avc::STATE_OK, $k['sessions']['state']);
        self::assertSame(['2 sur 7 jours, 3 sur 30 jours, 3 sur 90 jours (sessions de plus d’1 h).'], $k['sessions']['detail'], 'la session de 10 minutes ne compte pas');
        self::assertStringContainsString('il y a 2 jours', $k['last_session']['detail'][0]);
        foreach ($steps as $s) {
            self::assertNotSame('', $s['source']['label'], $s['key'] . ' cite sa source');
            self::assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4}$#', $s['source']['date'], $s['key'] . ' donne sa date');
            self::assertContains($s['state'], [Avc::STATE_OK, Avc::STATE_WARN, Avc::STATE_NONE]);
        }
        $v = Avc::verdict($facts);
        self::assertSame(Avc::AVAILABLE, $v['key']);
        self::assertSame('Disponible', $v['label']);
        self::assertStringContainsString('2 sur 30 jours', $v['detail'], 'la règle qui explique la conclusion');
    }

    public function testLittleActiveMemberIsLowWithTheRule(): void
    {
        $facts = $this->facts([[40, 4200]], ['last_login_at' => $this->ago(45), 'site_activity_at' => $this->ago(50)]);
        $k = self::byKey(Avc::steps($facts));
        self::assertSame(Avc::STATE_WARN, $k['sessions']['state']);
        self::assertSame(Avc::STATE_WARN, $k['last_session']['state'], 'plus de 30 jours');
        self::assertSame(Avc::STATE_WARN, $k['site_login']['state']);
        $v = Avc::verdict($facts);
        self::assertSame(Avc::LOW, $v['key']);
        self::assertStringContainsString('au moins une sur 90 jours', $v['detail']);
    }

    public function testDeclaredAbsenceWinsOverActivityAndPositionWithoutServiceToo(): void
    {
        $absence = ['starts_on' => date('Y-m-d', $this->now - 2 * 86400), 'ends_on' => date('Y-m-d', $this->now + 5 * 86400), 'reason' => 'sante', 'status' => 'active'];
        $facts = $this->facts([[2, 7200], [1, 7200]], ['absences' => [$absence]]);
        $k = self::byKey(Avc::steps($facts));
        self::assertSame(Avc::STATE_WARN, $k['absences']['state']);
        self::assertStringContainsString('santé', $k['absences']['detail'][0]);
        self::assertSame(Avc::ABSENT, Avc::verdict($facts)['key'], 'une absence déclarée l’emporte sur des sessions récentes');

        $cancelled = $this->facts([[2, 7200]], ['absences' => [['status' => 'cancelled'] + $absence]]);
        self::assertSame(Avc::AVAILABLE, Avc::verdict($cancelled)['key'], 'une absence annulée ne compte pas');

        $upcoming = $this->facts([[2, 7200]], ['absences' => [['starts_on' => date('Y-m-d', $this->now + 10 * 86400), 'ends_on' => null, 'reason' => 'personnel', 'status' => 'active']]]);
        self::assertStringContainsString('prévue à partir du', self::byKey(Avc::steps($upcoming))['absences']['detail'][0]);
        self::assertSame(Avc::AVAILABLE, Avc::verdict($upcoming)['key']);

        $off = $this->facts([[2, 7200]], ['position' => ['slug' => AdministrativePositionCatalog::INACTIVE, 'label' => 'En service non actif', 'service' => 'Non']]);
        self::assertSame(Avc::ABSENT, Avc::verdict($off)['key']);
        self::assertSame(Avc::STATE_WARN, self::byKey(Avc::steps($off))['position']['state']);
    }

    public function testInactiveMemberHasNoQualifyingSessionOn90Days(): void
    {
        $facts = $this->facts([[3, 900]], ['last_login_at' => $this->ago(70), 'events' => ['sessions_90' => 4, 'present_90' => 0, 'last_present_at' => null]]);
        $k = self::byKey(Avc::steps($facts));
        self::assertSame(['Aucune sur les 90 derniers jours.'], $k['last_session']['detail']);
        self::assertSame(Avc::STATE_WARN, $k['events']['state']);
        $v = Avc::verdict($facts);
        self::assertSame(Avc::INACTIVE, $v['key']);
        self::assertSame('Règle : aucune session qui compte sur 90 jours.', $v['detail']);
    }

    public function testWithoutSteamTheEventsThenTheSiteLoginTakeOver(): void
    {
        $base = $this->facts([], [], false);
        self::assertSame(Avc::STATE_NONE, self::byKey(Avc::steps($base))['sessions']['state']);
        self::assertArrayNotHasKey('last_session', self::byKey(Avc::steps($base)));
        self::assertSame(Avc::AVAILABLE, Avc::verdict($base)['key'], 'présent à une convocation il y a 12 jours');
        $noEvent = $this->facts([], ['events' => null, 'last_login_at' => $this->ago(5)], false);
        self::assertSame(Avc::LOW, Avc::verdict($noEvent)['key']);
        $gone = $this->facts([], ['events' => null, 'last_login_at' => null], false);
        self::assertSame(Avc::INACTIVE, Avc::verdict($gone)['key']);
        $off = $this->facts([], ['sessions' => null]);
        self::assertStringContainsString('pas actif sur ce site', self::byKey(Avc::steps($off))['sessions']['detail'][0]);
    }

    public function testStepsWhoseSourceDoesNotExistDoNotAppear(): void
    {
        $facts = $this->facts([[2, 7200]], ['site_activity_at' => false, 'absences' => null, 'events' => ['sessions_90' => 0, 'present_90' => 0, 'last_present_at' => null], 'sanctions' => null, 'followup' => null]);
        self::assertSame(['sessions', 'last_session', 'site_login', 'position'], array_column(Avc::steps($facts), 'key'));
        $sanctioned = self::byKey(Avc::steps($this->facts([[2, 7200]], ['sanctions' => ['active' => 1], 'followup' => null])));
        self::assertSame(Avc::STATE_WARN, $sanctioned['followup']['state']);
        self::assertSame(['1 mesure(s) disciplinaire(s) en vigueur.'], $sanctioned['followup']['detail']);
    }

    // —— Lecture des sources réelles ——

    public function testCollectorReadsTheExistingTablesOfTheMember(): void
    {
        $collector = new AvailabilityFactsCollector(
            $this->pdo,
            static fn (int $t, int $u): array => ['slug' => AdministrativePositionCatalog::ACTIVE, 'label' => 'En service actif', 'service' => 'Oui'],
            null,
            static fn (int $t, int $u): array => ['open' => 2, 'late' => 1]
        );
        $this->pdo->prepare('INSERT INTO personnel_absences (tenant_id, user_id, starts_on, ends_on, reason, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([F::TENANT, self::QUIET, date('Y-m-d', $this->now - 86400), date('Y-m-d', $this->now + 86400 * 4), 'professionnel', 'active']);
        $m = $collector->collect(F::TENANT, self::MEMBER, ['min_minutes' => 60, 'gap_minutes' => 25], $this->now, self::TZ);
        self::assertSame(3, $m['sessions']['summary']['windows'][30]['sessions']);
        self::assertSame(['sessions_90' => 2, 'present_90' => 1, 'last_present_at' => $this->ago(12)], $m['events']);
        self::assertSame($this->ago(3), $m['site_activity_at']);
        self::assertNull($m['sanctions'], 'sans le droit de voir les sanctions, elles ne sont pas lues');
        self::assertSame(Avc::AVAILABLE, Avc::verdict($m)['key']);
        self::assertSame(Avc::STATE_WARN, self::byKey(Avc::steps($m))['followup']['state'], 'une étape du dossier en retard');

        $q = $collector->collect(F::TENANT, self::QUIET, ['min_minutes' => 60, 'gap_minutes' => 25], $this->now, self::TZ);
        self::assertSame(Avc::ABSENT, Avc::verdict($q)['key']);

        // Seuil réglé à 2 h : plus aucune session de Sgt Marchand ne compte sur 7 jours, une seule sur 30.
        $strict = $collector->collect(F::TENANT, self::MEMBER, ['min_minutes' => 120, 'gap_minutes' => 25], $this->now, self::TZ);
        self::assertSame(1, $strict['sessions']['summary']['windows'][30]['sessions']);
        self::assertSame(Avc::LOW, Avc::verdict($strict)['key']);
    }

    public function testCollectorMarksMissingSourcesWithoutFailing(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, status TEXT)');
        $pdo->exec("INSERT INTO users (id, tenant_id, status) VALUES (7, 1, 'active')");
        // Les sources absentes sont journalisées : journal muet pendant ce test.
        $log = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        try {
            $f = (new AvailabilityFactsCollector($pdo))->collect(1, 7, ['min_minutes' => 60, 'gap_minutes' => 25], $this->now, self::TZ);
        } finally {
            ini_set('error_log', $log === false ? '' : $log);
        }
        self::assertFalse($f['site_activity_at'], 'pas de journal des actions');
        self::assertNull($f['absences']);
        self::assertNull($f['events']);
        self::assertNull($f['last_login_at']);
        $keys = array_column(Avc::steps($f), 'key');
        self::assertNotContains('site_activity', $keys);
        self::assertNotContains('absences', $keys);
        self::assertNotContains('events', $keys);
        self::assertSame(Avc::INACTIVE, Avc::verdict($f)['key']);
    }

    // —— Droits et jeton ——

    public function testRunAndSaveNeedTheRightToManageTheDossierFollowUp(): void
    {
        foreach ([[], ['organization.effectifs.hub.view'], ['organization.effectifs.hub.view', 'effectifs.suivi.view'], ['effectifs.suivi.manage']] as $perms) {
            $this->actAs(self::ACTOR, $perms);
            [$status, $json] = $this->call('run', ['id' => (string) self::MEMBER]);
            self::assertSame(403, $status, implode(',', $perms));
            self::assertSame('forbidden', $json['code']);
            [$status] = $this->call('save', ['id' => (string) self::MEMBER], self::CSRF + ['conclusion' => 'available', 'follow_up' => 'none']);
            self::assertSame(403, $status);
        }
        self::assertSame(0, $this->rowCount('member_availability_checks'));
        foreach ([self::MANAGE, ['admin.organization'], ['admin.access']] as $perms) {
            $this->actAs(self::ACTOR, $perms);
            self::assertTrue(MemberAvailabilityCheckController::canCheck(Gate::getInstance()), implode(',', $perms));
        }
        $this->actAs(self::ACTOR, ['organization.effectifs.hub.view', 'effectifs.suivi.view']);
        self::assertTrue(MemberAvailabilityCheckController::canView(Gate::getInstance()), 'lire l’historique avec le droit de consulter le suivi');
        self::assertStringNotContainsString('permissions', self::read('database/steps/4860_member_availability_checks.php'), 'aucun droit créé');

        $_SESSION = [];
        [$status] = $this->call('run', ['id' => (string) self::MEMBER]);
        self::assertSame(401, $status);
    }

    public function testSaveRefusesAMissingOrWrongCsrfToken(): void
    {
        [$status, $json] = $this->call('save', ['id' => (string) self::MEMBER], ['conclusion' => 'available', 'follow_up' => 'none']);
        self::assertSame(403, $status);
        self::assertSame('csrf', $json['code']);
        [$status] = $this->call('save', ['id' => (string) self::MEMBER], ['_csrf_token' => str_repeat('b', 64), 'conclusion' => 'available', 'follow_up' => 'none']);
        self::assertSame(403, $status);
        [$status] = $this->call('saveMany', [], ['items' => [['id' => (string) self::MEMBER]]]);
        self::assertSame(403, $status);
        self::assertSame(0, $this->rowCount('member_availability_checks'));
    }

    public function testAMemberOfAnotherCommunityIsNeitherReadNorRecorded(): void
    {
        [$status, $json] = $this->call('run', ['id' => (string) self::OUTSIDER]);
        self::assertSame(404, $status);
        self::assertSame('member', $json['code']);
        [$status] = $this->call('save', ['id' => (string) self::OUTSIDER], self::CSRF + ['conclusion' => 'available', 'follow_up' => 'none']);
        self::assertSame(404, $status);
        self::assertSame(0, $this->rowCount('member_availability_checks'));
    }

    // —— Enregistrement et historique ——

    public function testRunReturnsTheStepsAndSaveRecordsWhoWhenWhatAndTheData(): void
    {
        [$status, $json] = $this->call('run', ['id' => (string) self::MEMBER]);
        self::assertSame(200, $status);
        self::assertTrue($json['can_save']);
        self::assertSame('Sgt Marchand', $json['member']['name']);
        self::assertSame(Avc::AVAILABLE, $json['verdict']['key']);
        self::assertSame(['ok' => 6, 'warn' => 0, 'none' => 1], $json['counts'], 'position non enregistrée sur cette base');

        [$status, $json] = $this->call('save', ['id' => (string) self::MEMBER], self::CSRF + ['conclusion' => 'available', 'note' => '', 'follow_up' => 'none']);
        self::assertSame(200, $status, (string) ($json['message'] ?? ''));
        self::assertSame('saved', $json['code']);
        $row = $this->pdo->query('SELECT * FROM member_availability_checks')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(self::ACTOR, (int) $row['checked_by']);
        self::assertSame(self::MEMBER, (int) $row['user_id']);
        self::assertSame(F::TENANT, (int) $row['tenant_id']);
        self::assertSame('available', $row['proposed']);
        self::assertSame('available', $row['conclusion']);
        self::assertSame(0, (int) $row['corrected']);
        $facts = json_decode((string) $row['facts_json'], true);
        self::assertSame('sessions', $facts['steps'][0]['key'], 'les données relevées sont gardées');
        self::assertSame(60, $facts['rules']['min_minutes']);

        // Suivi du dossier : l'événement est lisible ; audit posé.
        $events = DossierHistoryPresenter::present(F::TENANT, (new DossierStore($this->pdo))->events(F::TENANT, self::MEMBER), static fn (int $t, array $ids): array => [4 => 'Cpt Delorme']);
        self::assertSame(DossierCatalog::ACTION_CHECK, $events[0]['action']);
        self::assertSame('a enregistré l’étape', $events[0]['verb']);
        self::assertSame('Vérification de disponibilité', $events[0]['step_title']);
        self::assertSame('Conclusion : Disponible', $events[0]['change']);
        self::assertSame('check', $this->ports->audits[0]['action']);

        // Historique de la fiche et chronologie.
        $hist = AvailabilityCheckHistory::present(F::TENANT, (new MemberAvailabilityCheckRepository($this->pdo))->listForUser(F::TENANT, self::MEMBER), static fn (int $t, array $ids): array => [4 => 'Cpt Delorme']);
        self::assertSame('Disponible', $hist[0]['conclusion_label']);
        self::assertSame('Cpt Delorme', $hist[0]['by']);
        $chrono = MemberChronology::build(['availability_checks' => $hist]);
        self::assertSame('disponibilite', $chrono[0]['type']);
        self::assertSame('Disponibilité vérifiée : Disponible', $chrono[0]['title']);
        self::assertSame('disponibilite', $chrono[0]['anchor']);
    }

    public function testACorrectionNeedsANoteAndIsKeptWithTheProposedConclusion(): void
    {
        [$status, $json] = $this->call('save', ['id' => (string) self::MEMBER], self::CSRF + ['conclusion' => 'low', 'note' => '', 'follow_up' => 'none']);
        self::assertSame(422, $status);
        self::assertSame('note', $json['code']);
        [$status, $json] = $this->call('save', ['id' => (string) self::MEMBER], self::CSRF + ['conclusion' => 'nimporte', 'follow_up' => 'none']);
        self::assertSame(422, $status);
        self::assertSame('conclusion', $json['code']);
        self::assertSame(0, $this->rowCount('member_availability_checks'));

        [$status] = $this->call('save', ['id' => (string) self::MEMBER], self::CSRF + ['conclusion' => 'low', 'note' => 'Joue surtout hors du serveur en ce moment', 'follow_up' => 'none']);
        self::assertSame(200, $status);
        $row = $this->pdo->query('SELECT proposed, conclusion, corrected, note FROM member_availability_checks')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['proposed' => 'available', 'conclusion' => 'low', 'corrected' => 1, 'note' => 'Joue surtout hors du serveur en ce moment'], array_map(static fn ($v) => is_numeric($v) ? (int) $v : $v, $row));
        $events = DossierHistoryPresenter::present(F::TENANT, (new DossierStore($this->pdo))->events(F::TENANT, self::MEMBER), static fn (int $t, array $ids): array => []);
        self::assertSame('Conclusion : Peu disponible (proposée : Disponible)', $events[0]['change']);
    }

    public function testNobodyVerifiesTheirOwnAvailability(): void
    {
        $this->actAs(self::MEMBER, self::MANAGE);
        [$status, $json] = $this->call('save', ['id' => (string) self::MEMBER], self::CSRF + ['conclusion' => 'available', 'follow_up' => 'none']);
        self::assertSame(422, $status);
        self::assertSame('self', $json['code']);
    }

    public function testFollowUpsReuseTheDossierSteps(): void
    {
        [$status, $json] = $this->call('save', ['id' => (string) self::QUIET], self::CSRF + ['conclusion' => 'low', 'note' => '', 'follow_up' => 'recheck', 'recheck_days' => '14']);
        self::assertSame(200, $status, (string) ($json['message'] ?? ''));
        self::assertTrue($json['follow_up_ok']);
        $step = $this->pdo->query("SELECT title, due_on, owner_user_id, track FROM member_dossier_steps WHERE user_id = 9")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('Revérifier la disponibilité', $step['title']);
        self::assertSame(date('Y-m-d', strtotime(date('Y-m-d') . ' +14 days')), $step['due_on']);
        self::assertSame(self::ACTOR, (int) $step['owner_user_id']);
        self::assertSame(DossierCatalog::TRACK_CAREER, $step['track']);
        self::assertStringStartsWith('custom:', (string) $this->pdo->query('SELECT follow_up_ref FROM member_availability_checks')->fetchColumn());

        [$status] = $this->call('save', ['id' => (string) self::QUIET], self::CSRF + ['conclusion' => 'low', 'follow_up' => 'recheck', 'recheck_days' => '3']);
        self::assertSame(422, $status, 'délai de relance hors de la liste');

        [$status] = $this->call('save', ['id' => (string) self::QUIET], self::CSRF + ['conclusion' => 'low', 'follow_up' => 'rh']);
        self::assertSame(200, $status);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM member_dossier_steps WHERE title = 'Suivi RH : disponibilité à examiner'")->fetchColumn());

        [$status, $json] = $this->call('save', ['id' => (string) self::QUIET], self::CSRF + ['conclusion' => 'low', 'follow_up' => 'position']);
        self::assertSame(200, $status);
        self::assertStringContainsString('?onglet=statut#statut-position', $json['position_url']);
        self::assertSame(2, $this->rowCount('member_dossier_steps'), 'le changement de position n’écrit rien : il passe par le formulaire existant');
    }

    // —— Groupé ——

    public function testBulkValidationRecordsEachRowWithItsOwnConclusion(): void
    {
        [$status, $json] = $this->call('saveMany', [], self::CSRF + ['follow_up' => 'none', 'items' => [
            ['id' => (string) self::MEMBER, 'conclusion' => '', 'note' => ''],
            ['id' => (string) self::QUIET, 'conclusion' => 'inactive', 'note' => 'Plus de nouvelles depuis un mois'],
            ['id' => (string) self::OUTSIDER, 'conclusion' => '', 'note' => ''],
            ['id' => (string) self::MEMBER, 'conclusion' => '', 'note' => ''],
        ]]);
        self::assertSame(200, $status);
        self::assertSame('partial', $json['code']);
        self::assertSame(2, $json['saved']);
        self::assertCount(3, $json['rows'], 'un doublon dans la sélection n’est vérifié qu’une fois');
        $rows = $this->pdo->query('SELECT user_id, proposed, conclusion, corrected, batch_id FROM member_availability_checks ORDER BY user_id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame([self::MEMBER, 'available', 'available', 0], [(int) $rows[0]['user_id'], $rows[0]['proposed'], $rows[0]['conclusion'], (int) $rows[0]['corrected']], 'validation en bloc : la conclusion proposée');
        self::assertSame([self::QUIET, 'low', 'inactive', 1], [(int) $rows[1]['user_id'], $rows[1]['proposed'], $rows[1]['conclusion'], (int) $rows[1]['corrected']]);
        self::assertSame($rows[0]['batch_id'], $rows[1]['batch_id']);
        self::assertSame(16, strlen((string) $rows[0]['batch_id']));

        [$status, $json] = $this->call('saveMany', [], self::CSRF + ['follow_up' => 'none', 'items' => [['id' => (string) self::QUIET, 'conclusion' => 'available', 'note' => '']]]);
        self::assertSame(422, $status, 'une ligne corrigée sans note n’est pas enregistrée');
        self::assertSame('Expliquez la correction dans la note (3 caractères au moins).', $json['rows'][0]['message']);

        $many = array_map(static fn (int $i): array => ['id' => (string) $i], range(1, MemberAvailabilityCheckController::BULK_MAX + 1));
        [$status, $json] = $this->call('saveMany', [], self::CSRF + ['items' => $many]);
        self::assertSame(422, $status);
        self::assertSame('too_many', $json['code']);
    }

    // —— Repli sans la migration ——

    public function testWithoutTheMigrationTheCheckRunsButIsNotRecorded(): void
    {
        $bare = $this->database(false);
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $bare);
        [$status, $json] = $this->call('run', ['id' => (string) self::MEMBER], [], $bare);
        self::assertSame(200, $status);
        self::assertFalse($json['can_save']);
        self::assertStringContainsString('member_availability_checks', $json['notice']);
        self::assertSame(Avc::AVAILABLE, $json['verdict']['key']);
        [$status, $json] = $this->call('save', ['id' => (string) self::MEMBER], self::CSRF + ['conclusion' => 'available', 'follow_up' => 'none'], $bare);
        self::assertSame(503, $status);
        self::assertSame('schema', $json['code']);
        [$status, $json] = $this->call('saveMany', [], self::CSRF + ['items' => [['id' => (string) self::MEMBER]]], $bare);
        self::assertSame(503, $status);
        $repo = new MemberAvailabilityCheckRepository($bare);
        self::assertSame([], $repo->listForUser(F::TENANT, self::MEMBER));
        self::assertSame([], $repo->latestForUsers(F::TENANT, [self::MEMBER]));
        self::assertSame(0, (int) $bare->query("SELECT COUNT(*) FROM member_dossier_step_events WHERE action = 'check'")->fetchColumn(), 'rien d’écrit ailleurs');
    }

    public function testMigrationStepIsIdempotentSqliteFriendlyAndHasARollback(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $root = self::ROOT;
        ob_start();
        require self::ROOT . '/database/steps/4860_member_availability_checks.php';
        self::assertStringContainsString('Table créée', (string) ob_get_clean());
        self::assertTrue((new MemberAvailabilityCheckRepository($pdo))->schemaReady());
        $changes = $pdo->changes;
        ob_start();
        require self::ROOT . '/database/steps/4860_member_availability_checks.php';
        self::assertStringContainsString('déjà présente', (string) ob_get_clean());
        self::assertSame($changes, $pdo->changes);

        $sql = self::read('migrations/2026_11_22_100_member_availability_checks.sql');
        self::assertSame(0, preg_match('/ALTER\s+TABLE/i', $sql));
        self::assertStringContainsString('DROP TABLE IF EXISTS member_availability_checks', self::read('migrations/rollback/member_availability_checks_down.sql'));
        $registry = require self::ROOT . '/database/steps/registry.php';
        $entry = array_values(array_filter($registry, static fn (array $s): bool => ($s['id'] ?? '') === 'member_availability_checks'));
        self::assertCount(1, $entry);
        self::assertSame('4860_member_availability_checks.php', $entry[0]['file']);
        $numbers = array_map(static fn (array $s): string => substr((string) $s['file'], 0, 4), $registry);
        self::assertSame(1, count(array_keys($numbers, '4860', true)), 'numéro d’étape unique');
    }

    // —— Tableur ——

    public function testRosterKeepsOnlyMembersToVerifyAndShowsTheLatestCheck(): void
    {
        $repo = new MemberAvailabilityCheckRepository($this->pdo);
        $repo->insert(F::TENANT, self::MEMBER, self::ACTOR, ['proposed' => 'low', 'conclusion' => 'low', 'corrected' => false, 'note' => '', 'follow_up' => 'none', 'follow_up_due' => null, 'follow_up_ref' => null, 'facts' => [], 'batch_id' => null], $this->ago(40));
        $repo->insert(F::TENANT, self::MEMBER, self::ACTOR, ['proposed' => 'available', 'conclusion' => 'available', 'corrected' => false, 'note' => '', 'follow_up' => 'none', 'follow_up_due' => null, 'follow_up_ref' => null, 'facts' => [], 'batch_id' => null], $this->ago(1));
        $latest = $repo->latestForUsers(F::TENANT, [self::MEMBER, self::QUIET, self::OUTSIDER]);
        self::assertSame(['available'], array_column($latest, 'conclusion'));
        self::assertSame([self::MEMBER], array_keys($latest));

        $rows = [
            ['id' => 1, 'server_sessions' => ['linked' => true, 'last_at' => $this->now - 3 * 86400]],
            ['id' => 2, 'server_sessions' => ['linked' => true, 'last_at' => $this->now - 20 * 86400]],
            ['id' => 3, 'server_sessions' => ['linked' => false, 'last_at' => null]],
            ['id' => 4, 'server_sessions' => null],
            ['id' => 5, 'server_sessions' => ['linked' => true, 'last_at' => null]],
        ];
        $kept = RosterAvailabilityFilter::apply($rows, [5 => ['created_at' => date('Y-m-d H:i:s', $this->now - 2 * 86400)]], 14, $this->now);
        self::assertSame([2, 3], array_column($kept, 'id'), 'sans session depuis 14 jours et sans vérification récente');
        self::assertSame(30, RosterAvailabilityFilter::days('verifier30'));
        self::assertSame(0, RosterAvailabilityFilter::days('verifier31'));
        self::assertSame(0, RosterAvailabilityFilter::days('actif'));
    }

    // —— Câblage des pages ——

    public function testFicheAndRosterOfferTheCheckWithAnAccessibleModal(): void
    {
        $modal = self::read('views/admin/effectifs_workspace/partials/availability_check_modal.php');
        foreach (['data-avc-part="aide"', 'data-avc-part="recherche"', 'data-avc-part="validation"', 'aria-live="polite"', '<dialog', 'aria-labelledby="eff-avc-title"', 'Ce qui compte comme session', 'Ce qui sera enregistré', 'Quand l’utiliser'] as $needle) {
            self::assertStringContainsString($needle, $modal);
        }
        $member = self::read('views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString('data-avc-open data-avc-ids="<?= (int) $id ?>"', $member, 'barre d’actions de la fiche');
        self::assertStringContainsString('data-avc-open', self::read('views/admin/effectifs_workspace/partials/member_availability.php'), 'onglet Disponibilité');
        $roster = self::read('views/admin/effectifs_workspace/roster.php');
        self::assertStringContainsString('data-avc-from-selection', $roster, 'action groupée');
        self::assertStringContainsString('data-avc-ids="<?= $id ?>"', $roster, 'action par ligne');
        self::assertStringContainsString('eff-avc-pill', $roster, 'pastille de la dernière vérification');
        self::assertStringContainsString('RosterAvailabilityFilter::DAYS', self::read('views/admin/effectifs_workspace/partials/roster_toolbar.php'));
        $css = self::read('public/assets/css/eff-availability-check.css');
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertStringContainsString('@media (max-width: 640px)', $css);
        self::assertStringContainsString('prefers-reduced-motion', $css);
        self::assertDoesNotMatchRegularExpression('/font-size:\s*0\.(7|6|5)\d*rem/', $css, 'textes de 13 px au moins');
        foreach ([$modal, $css, self::read('public/assets/js/eff-availability-check.js'), self::read('views/admin/effectifs_workspace/partials/member_availability.php')] as $src) {
            self::assertDoesNotMatchRegularExpression('/(eyebrow|kicker|fiction|simulation|\bRP\b|sse-ds|\.sse-|Archivo)/i', $src);
        }
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("'/back-office/ressources/effectifs/membres/{id}/disponibilite/verification', [\\App\\Controllers\\Admin\\MemberAvailabilityCheckController::class, 'save']", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/disponibilite/verifications'", $routes);
    }

    public function testEnglishDictionaryCoversTheModalAndTheResults(): void
    {
        $GLOBALS['__app_site_lang'] = 'en';
        try {
            $html = \App\Support\HtmlTranslator::translate('<p>Vérifier la disponibilité</p><p>Ce qui compte comme session</p><p>Conclusion retenue</p>');
            self::assertStringContainsString('Check availability', $html);
            $json = \App\Support\HtmlTranslator::translateJson((string) json_encode(['steps' => Avc::steps($this->facts([[9, 7200], [5, 5400]])), 'verdict' => Avc::verdict($this->facts([[9, 7200], [5, 5400]]))], JSON_UNESCAPED_UNICODE));
            $data = json_decode($json, true);
            self::assertSame('Available', $data['verdict']['label']);
            self::assertSame('Game sessions that count', $data['steps'][0]['label']);
            self::assertStringContainsString('in 7 days', $data['steps'][0]['detail'][0]);
        } finally {
            unset($GLOBALS['__app_site_lang']);
        }
    }
}
