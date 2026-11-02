<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cron\CronSchedule;
use App\Services\Personnel\AcademyQualificationImporter;
use App\Services\Personnel\QualificationAwardRuleService as Svc;
use App\Services\Personnel\QualificationCriteriaEngine as Eng;
use App\Services\Personnel\QualificationTemporalStatusService as T;
use App\Support\QualificationCriteriaCatalog as Cat;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Qualifications de toute nature : règle automatique (seuils mesurés, par appareil, ET / OU), états (délivrance,
 * renouvellement, jamais redonnée après retrait), reprise des qualifications Academy et câblage.
 */
final class QualificationCriteriaEngineTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @param array<string, float> $values clé « metric|scope » => valeur */
    private static function evalRule(array $rule, array $values, array $held = [], array $courses = []): array
    {
        return Eng::evaluate(
            Eng::normalize($rule),
            static fn (array $c): float => $values[$c['metric'] . '|' . $c['scope']] ?? 0.0,
            static fn (int $id): bool => in_array($id, $held, true),
            static fn (int $id): bool => in_array($id, $courses, true),
        );
    }

    public function testNormalizeKeepsOnlyKnownMeasuredCriteria(): void
    {
        $rule = Eng::normalize(json_encode(['op' => 'any', 'groups' => [
            ['op' => 'all', 'items' => [
                ['type' => 'metric', 'metric' => 'playtime_hours', 'min' => '12,5', 'window_days' => 90],
                ['type' => 'metric', 'metric' => 'kills_count', 'min' => 10],
                ['type' => 'metric', 'metric' => 'missions_played', 'min' => 0],
                ['type' => 'metric', 'metric' => 'missions_played', 'min' => 5, 'window_days' => 45],
                ['type' => 'academy', 'course_id' => 0],
                ['type' => 'qualification', 'definition_id' => 7],
            ]],
            ['op' => 'all', 'items' => []],
        ]]));
        self::assertSame('any', $rule['op']);
        self::assertCount(1, $rule['groups']);
        $items = $rule['groups'][0]['items'];
        self::assertCount(3, $items, 'critère inconnu, seuil nul et formation vide écartés');
        self::assertSame(12.5, $items[0]['min']);
        self::assertSame(0, $items[0]['window_days'], 'les heures de jeu du mod ne sont pas datées : pas de période');
        self::assertSame(0, $items[1]['window_days'], 'période hors liste ramenée à « depuis toujours »');
        self::assertSame(['type' => 'qualification', 'definition_id' => 7], $items[2]);
        self::assertSame([], Eng::normalize(''));
        self::assertSame([], Eng::normalize(['groups' => [['items' => [['type' => 'metric', 'metric' => 'nope', 'min' => 3]]]]]));
        // Forme courte : une liste de critères = un seul bloc.
        self::assertCount(1, Eng::normalize(['items' => [['type' => 'academy', 'course_id' => 4]]])['groups']);
        // Périmètres contrôlés.
        self::assertSame('family:uh60', Cat::normalizeScope('flight_hours', 'FAMILY:UH60'));
        self::assertSame('', Cat::normalizeScope('flight_hours', 'drop table'));
        self::assertSame('server', Cat::normalizeScope('playtime_hours', 'server'));
        self::assertSame('kind:secondary', Cat::normalizeScope('item_days', 'kind:secondary'));
    }

    public function testThresholdsAndProgress(): void
    {
        $rule = ['groups' => [['items' => [['type' => 'metric', 'metric' => 'playtime_hours', 'min' => 20]]]]];
        $half = self::evalRule($rule, ['playtime_hours|' => 10]);
        self::assertFalse($half['met']);
        self::assertSame(50, $half['pct']);
        self::assertSame(10.0, $half['groups'][0]['items'][0]['current']);
        $done = self::evalRule($rule, ['playtime_hours|' => 25]);
        self::assertTrue($done['met']);
        self::assertSame(100, $done['pct']);
        self::assertFalse(self::evalRule($rule, [])['met'], 'donnée absente = 0');
    }

    public function testFlightHoursPerAircraftFamilyAndExactClass(): void
    {
        $rows = [7 => [
            ['vehicle_class' => 'RHS_UH60M', 'vehicle_label' => 'UH-60M', 'vehicle_kind' => 'heli', 'seconds' => 3 * 3600, 'last_flown_at' => '2026-10-20 10:00:00'],
            ['vehicle_class' => 'B_Heli_Transport_01_F', 'vehicle_label' => 'Ghost Hawk', 'vehicle_kind' => 'heli', 'seconds' => 2 * 3600, 'last_flown_at' => '2026-10-22 10:00:00'],
            ['vehicle_class' => 'RHS_AH64D', 'vehicle_label' => 'AH-64D', 'vehicle_kind' => 'heli', 'seconds' => 3600, 'last_flown_at' => '2026-10-01 10:00:00'],
            ['vehicle_class' => 'RHS_C130J', 'vehicle_label' => 'C-130J', 'vehicle_kind' => 'plane', 'seconds' => 1800, 'last_flown_at' => '2026-10-01 10:00:00'],
        ]];
        // Vol du carnet contresigné : compte sur l'appareil et la famille.
        $log = [7 => [['status' => 'countersigned', 'source' => 'pilot', 'vehicle_class' => 'RHS_AH64D', 'vehicle_label' => 'AH-64D', 'vehicle_kind' => 'heli', 'seconds' => 3600, 'flown_on' => '2026-10-25', 'night_seconds' => 1800]]];
        $t = Svc::flightScopes($rows, $log)[7];
        self::assertSame(5 * 3600, $t['family:uh60']['seconds'], 'Ghost Hawk rangé avec le Black Hawk');
        self::assertSame(2 * 3600, $t['vclass:rhs_ah64d']['seconds']);
        self::assertSame(1800, $t['cond:night:heli']['seconds']);
        $measure = static function (array $c) use ($t): float {
            return $c['scope'] === ''
                ? (($t['kind:heli']['seconds'] ?? 0) + ($t['kind:plane']['seconds'] ?? 0)) / 3600
                : ($t[$c['scope']]['seconds'] ?? 0) / 3600;
        };
        $rule = Eng::normalize(['groups' => [['op' => 'all', 'items' => [
            ['type' => 'metric', 'metric' => 'flight_hours', 'scope' => 'family:uh60', 'min' => 4],
            ['type' => 'metric', 'metric' => 'flight_hours', 'scope' => 'vclass:rhs_ah64d', 'min' => 4],
            ['type' => 'metric', 'metric' => 'flight_hours', 'scope' => '', 'min' => 7.5], // 7 h d'hélicoptère (carnet compris) + 30 min d'avion
        ]]]]);
        $ev = Eng::evaluate($rule, $measure, static fn (): bool => false, static fn (): bool => false);
        self::assertFalse($ev['met']);
        self::assertSame([true, false, true], array_map(static fn (array $i): bool => $i['met'], $ev['groups'][0]['items']));
        self::assertSame(50, $ev['pct'], 'ET : avancement du critère le moins avancé');
    }

    public function testAndOrCombinations(): void
    {
        $rule = ['op' => 'all', 'groups' => [
            ['op' => 'any', 'items' => [
                ['type' => 'academy', 'course_id' => 3],
                ['type' => 'metric', 'metric' => 'missions_played', 'min' => 10],
            ]],
            ['op' => 'all', 'items' => [['type' => 'qualification', 'definition_id' => 9]]],
        ]];
        self::assertFalse(self::evalRule($rule, ['missions_played|' => 4])['met']);
        $orByMissions = self::evalRule($rule, ['missions_played|' => 10], [9]);
        self::assertTrue($orByMissions['met'], 'bloc OU rempli par les missions, bloc ET par la qualification');
        self::assertTrue(self::evalRule($rule, [], [9], [3])['met'], 'bloc OU rempli par la formation Academy');
        self::assertFalse(self::evalRule($rule, ['missions_played|' => 50], [], [3])['met'], 'il manque le bloc ET');
        $partial = self::evalRule($rule, ['missions_played|' => 5]);
        self::assertSame(0, $partial['pct']);
        self::assertSame(50, $partial['groups'][0]['pct'], 'OU : avancement du critère le plus avancé');

        $rule['op'] = 'any';
        self::assertTrue(self::evalRule($rule, [], [9])['met'], 'racine OU : un seul bloc suffit');
    }

    public function testStatesNeverAwardTwiceNeverReawardAfterWithdrawalAndRenewExpiring(): void
    {
        self::assertSame(Eng::ELIGIBLE, Eng::state(null, true, true));
        self::assertSame(Eng::PROGRESS, Eng::state(null, true, false), 'prérequis manquant');
        self::assertSame(Eng::PROGRESS, Eng::state(null, false, true));
        self::assertSame(Eng::HELD, Eng::state(['admin_status' => 'obtained'], true, true, T::VALID), 'déjà titulaire : rien de plus');
        self::assertSame(Eng::PENDING, Eng::state(['admin_status' => 'in_evaluation'], true, true), 'proposée : attend un responsable');
        foreach (['revoked', 'failed', 'suspended'] as $s) {
            self::assertSame(Eng::BLOCKED, Eng::state(['admin_status' => $s], true, true), $s . ' : jamais redonnée toute seule');
        }
        self::assertSame(Eng::RENEW, Eng::state(['admin_status' => 'obtained'], true, true, T::EXPIRED));
        self::assertSame(Eng::RENEW, Eng::state(['admin_status' => 'obtained'], true, true, T::EXPIRING_SOON));
        self::assertSame(Eng::HELD, Eng::state(['admin_status' => 'obtained'], false, true, T::EXPIRED), 'règle plus remplie : expire normalement');

        // Idempotence : après un passage, la meilleure attribution est la nouvelle, valide → plus rien à faire.
        $rows = [
            ['id' => 10, 'definition_id' => 4, 'admin_status' => 'obtained', 'expires_at' => '2026-01-01'],
            ['id' => 11, 'definition_id' => 4, 'admin_status' => 'obtained', 'expires_at' => '2027-12-01'],
            ['id' => 12, 'definition_id' => 4, 'admin_status' => 'revoked'],
        ];
        self::assertSame(11, (int) Svc::bestAwards($rows)[4]['id']);
        // Attestation Academy ancienne (pas d'admin_status) : obtenue, éventuellement expirée.
        self::assertSame('obtained', Svc::adminStatus(['status' => 'expired']));
        self::assertSame('in_training', Svc::adminStatus(['status' => 'in_progress']));
        $temporal = new T();
        self::assertSame(T::EXPIRED, $temporal->resolve(['admin_status' => 'obtained', 'expires_at' => '2020-01-01'])['code']);
    }

    public function testAcademyOnlyRulesAttachCertificatesInsteadOfDuplicating(): void
    {
        self::assertSame([5], Eng::academyOnlyCourses(Eng::normalize(['items' => [['type' => 'academy', 'course_id' => 5]]])));
        self::assertSame([5, 6], Eng::academyOnlyCourses(Eng::normalize(['groups' => [['op' => 'any', 'items' => [['type' => 'academy', 'course_id' => 5], ['type' => 'academy', 'course_id' => 6]]]]])));
        self::assertSame([], Eng::academyOnlyCourses(Eng::normalize(['groups' => [['op' => 'all', 'items' => [['type' => 'academy', 'course_id' => 5], ['type' => 'academy', 'course_id' => 6]]]]])), 'deux formations exigées : attribution à part');
        self::assertSame([], Eng::academyOnlyCourses(Eng::normalize(['items' => [['type' => 'academy', 'course_id' => 5], ['type' => 'metric', 'metric' => 'missions_played', 'min' => 3]]])));
    }

    public function testModesAreDerivedFromSettings(): void
    {
        self::assertSame([Cat::MODE_MANUAL], Eng::modes(null, false), 'sans réglage : comme avant, à la main');
        $academy = ['allow_manual' => 1, 'auto_enabled' => 1, 'rule_json' => json_encode(AcademyQualificationImporter::rule(3))];
        self::assertSame([Cat::MODE_MANUAL, Cat::MODE_ACADEMY], Eng::modes($academy, false));
        $auto = ['allow_manual' => 0, 'auto_enabled' => 1, 'rule_json' => json_encode(['items' => [['type' => 'metric', 'metric' => 'halo_jumps', 'min' => 5]]])];
        self::assertSame([Cat::MODE_AUTO, Cat::MODE_PILOT], Eng::modes($auto, true));
        self::assertSame([Cat::MODE_MANUAL], Eng::modes(['allow_manual' => 1, 'auto_enabled' => 0, 'rule_json' => $auto['rule_json']], false), 'règle désactivée');
    }

    public function testCriteriaAreDescribedPlainly(): void
    {
        $c = ['type' => 'metric', 'metric' => 'flight_hours', 'scope' => 'family:uh60', 'min' => 20.0, 'window_days' => 0];
        self::assertSame('20 h — Heures de vol (UH-60 Black Hawk)', Cat::describe($c, ['scopes' => ['family:uh60' => 'UH-60 Black Hawk']]));
        $parts = Cat::parts(['type' => 'metric', 'metric' => 'events_attended', 'scope' => 'operation', 'min' => 5, 'window_days' => 90]);
        self::assertSame(['value' => '5', 'unit' => 'présences', 'label' => 'Présences aux événements', 'scope' => 'Opérations', 'window' => 90], $parts);
        self::assertSame('Réussir la formation Academy « JTAC »', Cat::describe(['type' => 'academy', 'course_id' => 2], ['courses' => [2 => 'JTAC']]));
        self::assertSame('2,5', Cat::formatNumber(2.5));
        // Chaque critère proposé correspond à une donnée réellement lue par le dépôt.
        $repo = (string) file_get_contents(self::ROOT . '/app/Repositories/QualificationAwardRuleRepository.php');
        foreach (array_keys(Cat::METRICS) as $metric) {
            if (str_starts_with($metric, 'flight_')) {
                continue;
            }
            self::assertStringContainsString("'" . $metric . "'", $repo, $metric);
        }
    }

    private static function academyBase(): MysqlDdlSqlitePdo
    {
        $pdo = new MysqlDdlSqlitePdo();
        foreach ([
            'CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE personnel_qualification_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, name TEXT, description TEXT, category_id INTEGER, is_active INTEGER, archived_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE qualification_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE training_courses (id INTEGER PRIMARY KEY, tenant_id INTEGER, title TEXT)',
            'CREATE TABLE personnel_qualifications (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, qualification_name TEXT, training_course_id INTEGER, definition_id INTEGER, source TEXT, status TEXT, expires_at TEXT)',
            "INSERT INTO tenants (id, name) VALUES (1, 'SOAR'), (2, 'Phantom')",
            "INSERT INTO training_courses (id, tenant_id, title) VALUES (12, 1, 'JTAC — contrôleur air avancé')",
            "INSERT INTO personnel_qualification_definitions (id, tenant_id, code, name) VALUES (40, 2, 'ACAD-30', 'Déjà là')",
            "INSERT INTO personnel_qualifications (tenant_id, user_id, qualification_name, training_course_id, definition_id, source, status) VALUES
                (1, 5, 'JTAC', 12, NULL, 'training', 'valid'),
                (1, 6, 'JTAC', 12, NULL, 'training', 'expired'),
                (1, 7, 'Saisie', NULL, NULL, 'manual', 'valid'),
                (2, 8, 'Radio', 30, NULL, 'training', 'valid')",
        ] as $sql) {
            $pdo->exec($sql);
        }
        $pdo->changes = 0;

        return $pdo;
    }

    /** Exécute une étape comme run-migrations.php. */
    private static function runStep(string $file, \PDO $pdo): string
    {
        $root = self::ROOT;
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        require $root . '/database/steps/_context.php';
        ob_start();
        try {
            require $root . '/database/steps/' . $file;
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    public function testMigrationCreatesTheTableThenTakesOverAcademyQualificationsOnce(): void
    {
        $pdo = self::academyBase();
        $out = self::runStep('3430_qualification_award_rules.php', $pdo);
        self::assertStringContainsString('créée', $out);
        self::assertSame(1, $pdo->changes);
        $out = self::runStep('3435_qualification_academy_reprise.php', $pdo);
        self::assertStringContainsString('1 qualification(s) créée(s), 1 reprise(s), 3 attestation(s) rattachée(s)', $out);

        $def = $pdo->query("SELECT * FROM personnel_qualification_definitions WHERE code = 'ACAD-12'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('JTAC — contrôleur air avancé', $def['name'], 'nom repris de la formation');
        self::assertSame(1, (int) $def['tenant_id']);
        self::assertSame('Academy', $pdo->query('SELECT name FROM qualification_categories WHERE id = ' . (int) $def['category_id'])->fetchColumn());
        $linked = $pdo->query('SELECT user_id, definition_id FROM personnel_qualifications ORDER BY user_id')->fetchAll(\PDO::FETCH_KEY_PAIR);
        self::assertSame((int) $def['id'], (int) $linked[5]);
        self::assertSame((int) $def['id'], (int) $linked[6], 'attestation expirée rattachée aussi (historique)');
        self::assertNull($linked[7], 'saisie manuelle hors Academy : intacte');
        self::assertSame(40, (int) $linked[8], 'qualification existante réutilisée');
        $rule = json_decode((string) $pdo->query('SELECT rule_json FROM qualification_award_rules WHERE definition_id = ' . (int) $def['id'])->fetchColumn(), true);
        self::assertSame(AcademyQualificationImporter::rule(12), $rule);
        self::assertSame([12], Eng::academyOnlyCourses(Eng::normalize($rule)));

        // Relance : rien ne bouge.
        $pdo->changes = 0;
        $counts = static fn (): array => [
            (int) $pdo->query('SELECT COUNT(*) FROM personnel_qualification_definitions')->fetchColumn(),
            (int) $pdo->query('SELECT COUNT(*) FROM qualification_award_rules')->fetchColumn(),
            (int) $pdo->query('SELECT COUNT(*) FROM qualification_categories')->fetchColumn(),
        ];
        $before = $counts();
        self::assertStringContainsString('déjà présente', self::runStep('3430_qualification_award_rules.php', $pdo));
        self::assertStringContainsString('0 qualification(s) créée(s), 0 reprise(s), 0 attestation(s)', self::runStep('3435_qualification_academy_reprise.php', $pdo));
        self::assertSame($before, $counts());
        self::assertSame(0, $pdo->changes);
    }

    public function testMigrationSkipsWithoutTheQualificationReferential(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        self::assertStringContainsString('[OK]', self::runStep('3430_qualification_award_rules.php', $pdo));
        self::assertStringContainsString('[OK]', self::runStep('3435_qualification_academy_reprise.php', $pdo));
        self::assertSame(0, $pdo->changes);
    }

    public function testPagesCronAndMigrationAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        foreach (["qualifications/matrice/case'", "qualifications/{id}/evaluer'", "qualifications/propositions/{awardId}'", "qualifications/academy/rattacher'", "qualifications/{id}', [QualificationRulesController::class, 'show']"] as $path) {
            self::assertStringContainsString($path, $routes);
        }
        // La fiche se déclare après les pages fixes (create, attribuer, cursus-pilote…) : elle ne les masque pas.
        self::assertGreaterThan(strpos($routes, "qualifications/attribuer'"), strpos($routes, "QualificationRulesController::class, 'show'"));
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('self::get(\App\Services\Cron\Jobs\QualificationAutoRulesCronJob::class)', $container);
        self::assertSame(60, CronSchedule::intervalMinutes('qualification_auto_rules'));
        $registry = (string) file_get_contents(self::ROOT . '/database/steps/registry.php');
        self::assertStringContainsString("'file' => '3430_qualification_award_rules.php'", $registry);
        self::assertStringContainsString("'file' => '3435_qualification_academy_reprise.php'", $registry);
        self::assertLessThan(strpos($registry, '3435_'), strpos($registry, '3430_'));
        $sql = (string) file_get_contents(self::ROOT . '/migrations/2026_11_02_010_qualification_award_rules.sql');
        foreach (['uq_qual_award_rules_definition', 'idx_qual_award_rules_tenant_auto', 'fk_qual_award_rules_tenant', 'fk_qual_award_rules_definition'] as $name) {
            self::assertStringContainsString('`' . $name . '`', $sql);
        }
        $doc = (string) file_get_contents(self::ROOT . '/docs/technical/migrations-active-queue.md');
        self::assertStringContainsString('qualification_award_rules', $doc);
        self::assertStringContainsString('qualification_academy_reprise', $doc);
        // La page Qualifications des effectifs est la matrice, avec un lien vers l'Academy (plus de renvoi).
        $view = (string) file_get_contents(self::ROOT . '/views/admin/effectifs_workspace/qualifications.php');
        self::assertStringContainsString("url('academy')", $view);
        self::assertStringContainsString('data-qmx-cell', $view);
        self::assertStringNotContainsString('délivrées automatiquement', $view);
    }
}
