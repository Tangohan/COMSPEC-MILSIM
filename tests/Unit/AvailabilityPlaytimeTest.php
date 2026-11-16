<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ArmaPlaytimeRepository;
use App\Repositories\MemberAvailabilityCheckRepository;
use App\Services\Effectifs\Availability\AvailabilityCheck as Avc;
use App\Services\Effectifs\Availability\AvailabilityCheckRecorder;
use App\Services\Effectifs\Availability\AvailabilityFactsCollector;
use App\Services\Effectifs\Availability\PlaytimeAvailability as Pt;
use App\Services\Personnel\PlayerSessionService as Sessions;
use App\Support\AdministrativePositionCatalog;
use App\Support\RosterAvailabilityFilter;
use PHPUnit\Framework\TestCase;
use Tests\Support\CountingSqlitePdo;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Disponibilité et heures de jeu remontées par le mod (user_arma_playtime, un cumul) : les quatre jeux de données (cumul seul,
 * sessions seules, les deux, aucun), la conclusion, l'instantané enregistré, l'absence de requête par membre et le rendu.
 */
final class AvailabilityPlaytimeTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const TZ = 'Europe/Paris';
    private const TENANT = 1;

    private int $now;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->now = (new \DateTimeImmutable('2026-11-16 12:00:00', new \DateTimeZone(self::TZ)))->getTimestamp();
        $this->resetRepositoryCache();
    }

    protected function tearDown(): void
    {
        $this->resetRepositoryCache();
    }

    private function resetRepositoryCache(): void
    {
        foreach (['tableReady', 'contextColumnsReady'] as $prop) {
            (new \ReflectionProperty(ArmaPlaytimeRepository::class, $prop))->setValue(null, null);
        }
    }

    private function ago(int $days): string
    {
        return date('Y-m-d H:i:s', $this->now - $days * 86400);
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . '/' . $path));
    }

    /**
     * @param array<string, mixed> $playtime
     * @return array<string, mixed> faits tels que les relève le collecteur, hors sessions
     */
    private function facts(?array $playtime, ?array $sessionsSummary, array $over = []): array
    {
        return $over + [
            'now' => $this->now,
            'tz' => self::TZ,
            'rules' => ['min_minutes' => 60, 'gap_minutes' => 25],
            'sessions' => $sessionsSummary === null ? ['ready' => true, 'summary' => Sessions::summarize([], 3600, $this->now, self::TZ, true), 'recent' => []] : ['ready' => true, 'summary' => $sessionsSummary, 'recent' => []],
            'playtime' => $playtime,
            'last_login_at' => $this->ago(90),
            'account_status' => 'active',
            'site_activity_at' => false,
            'absences' => [],
            'position' => ['slug' => AdministrativePositionCatalog::ACTIVE, 'label' => 'En service actif', 'service' => 'Oui'],
            'events' => null,
            'sanctions' => null,
            'followup' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function cumul(int $hours, int $daysAgo, array $over = []): array
    {
        return $over + ['total_seconds' => $hours * 3600, 'server_seconds' => (int) ($hours * 3600 * 0.8), 'zeus_seconds' => (int) ($hours * 3600 * 0.1), 'editor_seconds' => (int) ($hours * 3600 * 0.1), 'last_report_at' => $this->ago($daysAgo), 'previous' => null];
    }

    /** @param list<array{0: int, 1: int}> $sessions [jours, durée en secondes] @return array<string, mixed> */
    private function summary(array $sessions): array
    {
        $list = [];
        foreach ($sessions as [$days, $duration]) {
            $start = $this->now - $days * 86400 - 3 * 3600;
            $list[] = ['started_at' => $start, 'last_seen_at' => $start + $duration, 'ended_at' => $start + $duration, 'duration_s' => $duration, 'aberrant' => false, 'source' => 'heartbeat', 'ongoing' => false];
        }

        return Sessions::summarize($list, 3600, $this->now, self::TZ, true);
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

    // —— Règle du cumul ——

    public function testActivityFollowsTheRecencyOfTheCumulativeUpdate(): void
    {
        $min = 3600;
        self::assertSame(Avc::AVAILABLE, Pt::activity($this->cumul(40, 3), $min, $this->now, self::TZ)['key']);
        self::assertSame(Avc::AVAILABLE, Pt::activity($this->cumul(40, 7), $min, $this->now, self::TZ)['key'], '7 jours inclus');
        self::assertSame(Avc::LOW, Pt::activity($this->cumul(40, 8), $min, $this->now, self::TZ)['key']);
        self::assertSame(Avc::LOW, Pt::activity($this->cumul(40, 90), $min, $this->now, self::TZ)['key']);
        self::assertSame(Avc::INACTIVE, Pt::activity($this->cumul(40, 91), $min, $this->now, self::TZ)['key']);
        self::assertNull(Pt::activity($this->cumul(0, 1), $min, $this->now, self::TZ)['key'], 'aucun cumul : pas de conclusion');
        self::assertNull(Pt::activity(['total_seconds' => 1800, 'last_report_at' => $this->ago(1)], $min, $this->now, self::TZ)['key'], 'cumul sous la durée qui compte');
        self::assertNull(Pt::activity(['total_seconds' => 7200, 'last_report_at' => null], $min, $this->now, self::TZ)['key'], 'date inconnue');
        self::assertSame(Avc::LOW, Pt::activity($this->cumul(40, 8), 3600, $this->now, self::TZ)['key']);
    }

    public function testGrowthSinceTheLastCheckPromotesAnOlderUpdate(): void
    {
        $previous = ['total_seconds' => 36 * 3600, 'checked_at' => $this->ago(20)];
        $grown = Pt::activity($this->cumul(40, 12), 3600, $this->now, self::TZ, $previous);
        self::assertSame(Avc::AVAILABLE, $grown['key'], '+4 h depuis la dernière vérification, mise à jour il y a 12 jours');
        self::assertSame(4 * 3600, $grown['gained']);
        self::assertStringContainsString('cumul en hausse de 4 h 00', $grown['rule']);

        $same = Pt::activity($this->cumul(36, 12), 3600, $this->now, self::TZ, $previous);
        self::assertSame(Avc::LOW, $same['key'], 'cumul inchangé : la règle de récence seule s’applique');

        $small = Pt::activity($this->cumul(36, 12, ['total_seconds' => 36 * 3600 + 600]), 3600, $this->now, self::TZ, $previous);
        self::assertSame(Avc::LOW, $small['key'], '+10 min ne suffit pas');

        $stale = Pt::activity($this->cumul(40, 45), 3600, $this->now, self::TZ, $previous);
        self::assertSame(Avc::LOW, $stale['key'], 'la hausse ne suffit pas si la dernière mise à jour a plus de 30 jours');
    }

    // —— Étape et conclusion, quatre jeux de données ——

    public function testCumulativeHoursAloneReplaceInactiveAndSayTheirLimit(): void
    {
        $facts = $this->facts($this->cumul(42, 3), null);
        $steps = Avc::steps($facts);
        self::assertSame(['sessions', 'last_session', 'playtime', 'site_login', 'absences', 'position'], array_column($steps, 'key'));
        $k = self::byKey($steps);
        self::assertSame('Heures de jeu remontées', $k['playtime']['label']);
        self::assertSame(Avc::STATE_OK, $k['playtime']['state']);
        self::assertSame('Heures de jeu remontées par le mod (user_arma_playtime)', $k['playtime']['source']['label']);
        self::assertSame(date('d/m/Y', $this->now - 3 * 86400), $k['playtime']['source']['date'], 'date de la dernière mise à jour');
        $text = implode(' ', $k['playtime']['detail']);
        self::assertStringContainsString('Cumul : 42 h 00', $text);
        self::assertStringContainsString('Dernière mise à jour du cumul le ' . date('d/m/Y', $this->now - 3 * 86400) . ' (il y a 3 jours)', $text);
        self::assertStringContainsString('cumul sans détail par session', $text, 'limite de la source dite honnêtement');
        self::assertStringContainsString('Sans session détaillée, ce cumul sert à la conclusion proposée.', $text);

        $v = Avc::verdict($facts);
        self::assertSame(Avc::AVAILABLE, $v['key'], 'plus « Inactif » alors que le mod remonte des heures');
        self::assertStringContainsString('Règle, sans session détaillée : cumul d’heures mis à jour il y a 3 jour(s)', $v['detail']);

        self::assertSame(Avc::LOW, Avc::verdict($this->facts($this->cumul(42, 40), null))['key']);
        self::assertSame(Avc::INACTIVE, Avc::verdict($this->facts($this->cumul(42, 200), null))['key'], 'cumul ancien : toujours inactif');
    }

    public function testDetailedSessionsStayFirstWhenTheyExist(): void
    {
        $withSessions = $this->summary([[2, 7200]]);
        $facts = $this->facts($this->cumul(42, 1), $withSessions);
        $v = Avc::verdict($facts);
        self::assertSame(Avc::AVAILABLE, $v['key']);
        self::assertStringStartsWith('Règle : au moins 1 session', $v['detail'], 'la règle des sessions, pas celle du cumul');
        self::assertStringContainsString('Des sessions détaillées existent : elles restent prioritaires', implode(' ', self::byKey(Avc::steps($facts))['playtime']['detail']));

        // Seulement des sessions trop courtes : des sessions existent, elles décident (Inactif), le cumul ne les remplace pas.
        $short = $this->facts($this->cumul(42, 1), $this->summary([[2, 600]]));
        self::assertSame(Avc::INACTIVE, Avc::verdict($short)['key']);
        self::assertSame('Règle : aucune session qui compte sur 90 jours.', Avc::verdict($short)['detail']);

        // Sessions seules, aucune table du cumul : aucune étape, règle inchangée.
        $only = $this->facts(null, $withSessions);
        self::assertArrayNotHasKey('playtime', self::byKey(Avc::steps($only)));
        self::assertSame(Avc::AVAILABLE, Avc::verdict($only)['key']);
    }

    public function testNothingAnywhereStaysInactiveAndTheStepSaysSo(): void
    {
        $none = $this->facts(['total_seconds' => 0, 'server_seconds' => 0, 'zeus_seconds' => 0, 'editor_seconds' => 0, 'last_report_at' => null, 'previous' => null], null);
        $k = self::byKey(Avc::steps($none));
        self::assertSame(Avc::STATE_NONE, $k['playtime']['state']);
        self::assertSame(['Aucune heure de jeu remontée par le mod pour ce membre.'], $k['playtime']['detail']);
        $v = Avc::verdict($none);
        self::assertSame(Avc::INACTIVE, $v['key']);
        self::assertSame('Règle : aucune session qui compte sur 90 jours.', $v['detail']);
    }

    public function testADeclaredAbsenceStillWinsOverTheCumulativeHours(): void
    {
        $absence = ['starts_on' => date('Y-m-d', $this->now - 86400), 'ends_on' => date('Y-m-d', $this->now + 5 * 86400), 'reason' => 'sante', 'status' => 'active'];
        self::assertSame(Avc::ABSENT, Avc::verdict($this->facts($this->cumul(42, 1), null, ['absences' => [$absence]]))['key']);
    }

    // —— Lecture réelle et instantané ——

    /** Base avec la vraie migration des vérifications ; information_schema y est servi par l'état réel (comme en production). */
    private function database(bool $withPlaytime = true): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, status TEXT, steam_id TEXT, last_login_at TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE player_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, steam_uid TEXT, started_at TEXT, ended_at TEXT, last_seen_at TEXT, duration_s INTEGER, aberrant INTEGER DEFAULT 0, source TEXT, server_id INTEGER, created_at TEXT)');
        if ($withPlaytime) {
            $pdo->exec(self::PLAYTIME_DDL);
        }
        $sql = preg_replace('/^--.*$/m', '', self::read('migrations/2026_11_22_100_member_availability_checks.sql')) ?? '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'test');
        if ($withPlaytime) {
            $pdo->exec("INSERT INTO information_schema.TABLES VALUES ('test', 'user_arma_playtime')");
            $pdo->exec("INSERT INTO information_schema.COLUMNS VALUES ('test', 'user_arma_playtime', 'server_seconds')");
        }

        return $pdo;
    }

    private const PLAYTIME_DDL = 'CREATE TABLE user_arma_playtime (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, total_seconds INTEGER DEFAULT 0, server_seconds INTEGER DEFAULT 0, zeus_seconds INTEGER DEFAULT 0, editor_seconds INTEGER DEFAULT 0, last_report_at TEXT, created_at TEXT, updated_at TEXT)';

    private function quiet(callable $fn): mixed
    {
        $log = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        try {
            return $fn();
        } finally {
            ini_set('error_log', $log === false ? '' : $log);
        }
    }

    public function testCollectorReadsTheCumulativeHoursAndTheSnapshotOfTheLastCheck(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, status, last_login_at, created_at) VALUES (7, 1, 'Sgt Marchand', 'active', '" . $this->ago(80) . "', '2025-01-01 10:00:00')");
        $pdo->exec("INSERT INTO user_arma_playtime (tenant_id, user_id, total_seconds, server_seconds, zeus_seconds, editor_seconds, last_report_at) VALUES (1, 7, " . (40 * 3600) . ', ' . (30 * 3600) . ', 0, ' . (10 * 3600) . ", '" . $this->ago(12) . "')");
        $collector = new AvailabilityFactsCollector($pdo);
        $rules = ['min_minutes' => 60, 'gap_minutes' => 25];

        $first = $this->quiet(fn (): array => $collector->collect(self::TENANT, 7, $rules, $this->now, self::TZ));
        self::assertSame(40 * 3600, $first['playtime']['total_seconds']);
        self::assertSame($this->ago(12), $first['playtime']['last_report_at']);
        self::assertNull($first['playtime']['previous'], 'aucune vérification enregistrée');
        self::assertSame(Avc::LOW, Avc::verdict($first)['key'], 'mise à jour il y a 12 jours, sans instantané');

        // Enregistrement d'une vérification avec l'instantané du cumul (même chemin que la validation du responsable).
        $checks = new MemberAvailabilityCheckRepository($pdo);
        $run = ['steps' => Avc::steps($first), 'verdict' => Avc::verdict($first), 'rules' => $rules, 'playtime' => array_diff_key($first['playtime'], ['previous' => 1])];
        $recorded = (new AvailabilityCheckRecorder($checks))->record(self::TENANT, 4, 7, $run, Avc::LOW, '', AvailabilityCheckRecorder::FOLLOW_NONE, 0, null, date('Y-m-d', $this->now - 10 * 86400));
        self::assertTrue($recorded['ok'], $recorded['message']);
        $stored = json_decode((string) $checks->listForUser(self::TENANT, 7, 1)[0]['facts_json'], true);
        self::assertSame(40 * 3600, $stored['playtime']['total_seconds'], 'instantané du cumul dans les données relevées');
        self::assertArrayNotHasKey('previous', $stored['playtime']);

        // Le cumul a augmenté depuis : la vérification suivante le voit.
        $pdo->exec('UPDATE user_arma_playtime SET total_seconds = ' . (44 * 3600) . ", last_report_at = '" . $this->ago(10) . "' WHERE user_id = 7");
        $second = $this->quiet(fn (): array => $collector->collect(self::TENANT, 7, $rules, $this->now, self::TZ));
        self::assertSame(40 * 3600, $second['playtime']['previous']['total_seconds']);
        self::assertSame(Avc::AVAILABLE, Avc::verdict($second)['key'], '+4 h depuis la dernière vérification');
        self::assertStringContainsString('cumul en hausse de 4 h 00', Avc::verdict($second)['detail']);
        self::assertStringContainsString('Depuis la vérification du', implode(' ', self::byKey(Avc::steps($second))['playtime']['detail']));
    }

    public function testWithoutTheCumulativeTableTheStepIsAbsentAndNothingBreaks(): void
    {
        $pdo = $this->database(false);
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, status, last_login_at, created_at) VALUES (7, 1, 'Sgt Marchand', 'active', '" . $this->ago(5) . "', '2025-01-01 10:00:00')");
        $facts = $this->quiet(fn (): array => (new AvailabilityFactsCollector($pdo))->collect(self::TENANT, 7, ['min_minutes' => 60, 'gap_minutes' => 25], $this->now, self::TZ));
        self::assertNull($facts['playtime']);
        self::assertArrayNotHasKey('playtime', self::byKey(Avc::steps($facts)));
        self::assertSame(Avc::LOW, Avc::verdict($facts)['key'], 'sans sessions ni cumul : connecté au site il y a 5 jours');
    }

    // —— Tableau : une seule lecture groupée ——

    public function testRosterReadsTheCumulativeHoursInOneGroupedQuery(): void
    {
        $pdo = new CountingSqlitePdo();
        $pdo->exec(self::PLAYTIME_DDL);
        $pdo->syncInformationSchema();
        $repo = new ArmaPlaytimeRepository($pdo);
        $ids = [];
        for ($i = 1; $i <= 40; $i++) {
            $ids[] = $i;
            $pdo->exec("INSERT INTO user_arma_playtime (tenant_id, user_id, total_seconds, server_seconds, zeus_seconds, editor_seconds, last_report_at) VALUES (1, {$i}, " . ($i * 3600) . ', 0, 0, 0, ' . ($i % 2 === 0 ? "'" . $this->ago($i) . "'" : 'NULL') . ')');
        }
        $repo->summariesForUsers(self::TENANT, [1, 2]);
        $pdo->reset();
        $few = $repo->summariesForUsers(self::TENANT, [1, 2, 3]);
        $queriesFew = $pdo->queries;
        $pdo->reset();
        $many = $repo->summariesForUsers(self::TENANT, $ids);
        self::assertSame($queriesFew, $pdo->queries, 'le nombre de requêtes ne dépend pas du nombre de membres');
        self::assertLessThanOrEqual(1, $pdo->queries);
        self::assertCount(3, $few);
        self::assertCount(40, $many);

        // La cellule vient du résumé déjà lu : aucune requête de plus.
        $pdo->reset();
        $cells = [];
        foreach ($many as $uid => $sum) {
            $cells[$uid] = Pt::cell($sum, 3600, $this->now, self::TZ);
        }
        self::assertSame(0, $pdo->queries);
        self::assertSame('2 h', $cells[2]['hours_label']);
        self::assertSame(date('d/m', $this->now - 2 * 86400), $cells[2]['last_label']);
        self::assertSame(Avc::AVAILABLE, $cells[2]['key']);
        self::assertSame('Disponible', $cells[2]['state_label']);
        self::assertSame('', $cells[1]['last_label'], 'date inconnue : pas de date affichée');
        self::assertNull($cells[1]['key']);
        self::assertSame(Avc::LOW, $cells[40]['key'], 'mise à jour il y a 40 jours');
    }

    public function testCellIsNullWithoutAnyHour(): void
    {
        self::assertNull(Pt::cell(['total_seconds' => 0, 'last_report_at' => null], 3600, $this->now, self::TZ));
        self::assertNull(Pt::cell([], 3600, $this->now, self::TZ));
    }

    public function testRosterToVerifyFilterSkipsMembersWhoseHoursAreRecent(): void
    {
        $srv = ['sessions' => 0, 'seconds' => 0, 'short' => 0, 'active' => false, 'linked' => true, 'has_data' => false, 'min_minutes' => 60, 'last_at' => null];
        $recent = Pt::cell($this->cumul(10, 3), 3600, $this->now, self::TZ);
        $rows = [
            ['id' => 1, 'server_sessions' => $srv, 'playtime_availability' => $recent],
            ['id' => 2, 'server_sessions' => $srv],
        ];
        $kept = RosterAvailabilityFilter::apply($rows, [], 30, $this->now);
        self::assertSame([2], array_map(static fn (array $r): int => (int) $r['id'], $kept));
    }

    // —— Rendu ——

    /** @param array<string, mixed> $vars */
    private function render(string $view, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include self::ROOT . '/' . $view;
        } finally {
            $html = (string) ob_get_clean();
        }

        return str_replace("\r\n", "\n", $html);
    }

    /** @param array<string, mixed>|null $panel */
    private function tab(?array $panel, ?array $pt): string
    {
        return $this->render('views/admin/effectifs_workspace/partials/member_availability.php', [
            'memberAvailability' => $panel,
            'memberPlaytime' => $pt,
            'availabilitySettingsUrl' => '',
            'id' => 7,
            'name' => 'Sgt Marchand',
        ]);
    }

    /** @return array<string, mixed> */
    private function panel(array $sessions): array
    {
        return ['ready' => true, 'min_minutes' => 60, 'gap_minutes' => 25, 'tz' => self::TZ, 'summary' => $this->summary($sessions), 'recent' => []];
    }

    /** @return array<string, mixed> */
    private function ptView(int $hours, int $daysAgo): array
    {
        $sum = $this->cumul($hours, $daysAgo);

        return ['summary' => $sum, 'cell' => Pt::cell($sum, 3600, $this->now, self::TZ), 'tz' => self::TZ];
    }

    public function testTabShowsTheCumulativeHoursInsteadOfAMisleadingZero(): void
    {
        $html = $this->tab($this->panel([]), $this->ptView(42, 3));
        self::assertStringContainsString('Heures remontées : 42 h (dernière activité ' . date('d/m', $this->now - 3 * 86400) . ')', $html);
        self::assertStringContainsString('Disponible d’après les heures remontées', $html);
        self::assertStringContainsString('user_arma_playtime', $html);
        self::assertStringContainsString('ne disent ni quand ni combien de temps d’affilée', $html);
        self::assertStringNotContainsString('Pas de session qui compte', $html);
        self::assertStringNotContainsString('Aucune sur 90 jours', $html, 'plus de tableau de zéros');
        self::assertStringContainsString('Les sessions détaillées viennent des signes de vie du mod', $html, 'aide : sessions détaillées et heures cumulées');
        self::assertStringContainsString('cumul de tout l’historique', $html);
    }

    public function testTabKeepsDetailedSessionsWhenTheyExistAndTheEmptyStateWhenNothingExists(): void
    {
        $detailed = $this->tab($this->panel([[2, 7200]]), $this->ptView(42, 3));
        self::assertStringNotContainsString('disponibilite-heures', $detailed, 'les sessions détaillées passent avant le cumul');
        self::assertStringContainsString('Actif sur le serveur', $detailed);

        $nothing = $this->tab($this->panel([]), null);
        self::assertStringNotContainsString('Heures remontées', $nothing);
        self::assertStringContainsString('Pas de session qui compte', $nothing);

        $unlinked = $this->tab(['ready' => true, 'min_minutes' => 60, 'gap_minutes' => 25, 'tz' => self::TZ, 'summary' => Sessions::summarize([], 3600, $this->now, self::TZ, false), 'recent' => []], $this->ptView(5, 20));
        self::assertStringContainsString('Heures remontées : 5 h', $unlinked, 'cumul affiché même sans compte Steam relié');
        self::assertStringContainsString('Steam', $unlinked);
    }

    public function testRosterColumnShowsTheCumulativeHoursNotAZero(): void
    {
        $src = self::read('views/admin/effectifs_workspace/roster.php');
        self::assertStringContainsString('Heures remontées : %1', $src);
        self::assertStringContainsString('(dernière activité %1)', $src);
        self::assertStringContainsString('Aucune session qui compte', $src);
        self::assertStringContainsString("row['playtime_availability']", $src);
        $ctl = self::read('app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString("PlaytimeAvailability::cell(\$sum, \$playtimeMin, \$playtimeNow, \$playtimeTz)", $ctl, 'cellule bâtie sur le cumul déjà lu (summariesForUsers)');
        self::assertSame(1, substr_count($ctl, 'ArmaPlaytimeRepository::class)->summariesForUsers($tenantId, $ids)'), 'une seule lecture groupée du cumul');
        foreach ([self::read('app/Services/Effectifs/Availability/PlaytimeAvailability.php'), self::read('views/admin/effectifs_workspace/partials/member_availability.php')] as $code) {
            self::assertDoesNotMatchRegularExpression('/(eyebrow|kicker|fiction|simulation|\bRP\b|sse-ds|Archivo)/i', $code);
        }
        foreach (['public/assets/css/back-office-effectifs-maquette.css', 'public/assets/css/back-office-effectifs-fiche.css'] as $css) {
            self::assertDoesNotMatchRegularExpression('/\.eff-(mq-sv--txt|mq-sv__pt|fm-av__pt)[^{]*\{[^}]*font-size:\s*(0\.[0-7]\d*rem|1[0-2]px)/', self::read($css), 'textes de 13 px au moins');
        }
    }

    public function testEnglishDictionaryCoversTheNewSentences(): void
    {
        $GLOBALS['__app_site_lang'] = 'en';
        try {
            $facts = $this->facts($this->cumul(42, 3), null);
            $json = \App\Support\HtmlTranslator::translateJson((string) json_encode(['steps' => Avc::steps($facts), 'verdict' => Avc::verdict($facts)], JSON_UNESCAPED_UNICODE));
            $data = json_decode($json, true);
            $k = self::byKey($data['steps']);
            self::assertSame('Reported play hours', $k['playtime']['label']);
            $text = implode(' ', $k['playtime']['detail']);
            self::assertStringNotContainsString('Cumul', $text);
            self::assertStringNotContainsString('Limite', $text);
            self::assertStringNotContainsString('Dernière', $text);
            self::assertStringNotContainsString('Règle', $data['verdict']['detail']);
            $html = \App\Support\HtmlTranslator::translate($this->tab($this->panel([]), $this->ptView(42, 3)));
            self::assertStringContainsString('Reported hours: 42 h', $html);
            self::assertStringNotContainsString('Heures remontées', $html);
            self::assertStringNotContainsString('Cumul total', $html);
        } finally {
            unset($GLOBALS['__app_site_lang']);
        }
    }
}
