<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DataQuality\DataQualityAnalyzer;
use App\Services\DataQuality\DataQualityCatalog;
use App\Services\DataQuality\DataQualityTables;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryDataQualitySource;

/**
 * Tableaux de la qualité des données : taux de remplissage, vérifications d'anomalies, agrégats par unité, tri, filtre,
 * pagination, portée par communauté et rendu échappé.
 */
final class DataQualityTablesTest extends TestCase
{
    private const T1 = 7;
    private const T2 = 8;

    private InMemoryDataQualitySource $source;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->source = new InMemoryDataQualitySource();
        $this->now = new \DateTimeImmutable('2026-11-15 10:00:00');
    }

    /** @param array<string, mixed> $over */
    private function row(int $id, array $over = []): array
    {
        return $over + [
            'id' => $id,
            'display_name' => 'Membre ' . $id,
            'callsign' => 'Indic' . $id,
            'email' => 'm' . $id . '@example.org',
            'steam_id' => '7656119' . $id,
            'athena_identifier' => 'ATH' . $id,
            'avatar_url' => '/a.png',
            'grade_id' => 3,
            'grade_tenant' => self::T1,
            'grade_name' => 'Sergent',
            'grade_short' => 'SGT',
            'last_login_at' => '2026-11-01 08:00:00',
            'created_at' => '2024-01-01 08:00:00',
            'updated_at' => '2026-10-01 08:00:00',
            'tenant_member_number' => 'M' . $id,
            'pp_id' => $id,
            'rank_display' => 'Sergent',
            'primary_unit_id' => 10,
            'primary_unit_tenant' => self::T1,
            'enlistment_date' => '2023-05-01',
            'matricule_internal' => 'D' . $id,
            'character_portrait_path' => 'p.png',
            'pp_updated' => '2026-10-02 08:00:00',
            'service_number' => 'S' . $id,
            'first_name' => 'Prenom' . $id,
            'last_name' => 'Nom' . $id,
            'phone' => '0600000000',
            'birth_date' => '1990-01-01',
            'uli_updated' => '2026-09-01 08:00:00',
            'unit_count' => 1,
            'main_unit_id' => 10,
        ];
    }

    /** @return array<string, mixed> */
    private function build(int $tenant = self::T1, array $query = []): array
    {
        return (new DataQualityTables($this->source))->build($tenant, $query, $this->now);
    }

    /** @param array<string, mixed> $table */
    private function ids(array $table): array
    {
        return array_map(static fn (array $r): int => (int) ($r['id'] ?? $r['user_id']), $table['rows']);
    }

    /** @return list<string> codes de vérification de ce membre */
    private function codesOf(array $out, int $id): array
    {
        $all = DataQualityTables::table($out['findings']['rows'], [], 'zz', ['name'], 'name', 'asc', null, null, 1000);
        $codes = [];
        foreach ($all['rows'] as $f) {
            if ($f['user_id'] === $id) {
                $codes[] = $f['code'];
            }
        }
        sort($codes);

        return $codes;
    }

    public function testCompletenessCountsFilledFieldsAndNamesMissingBlocks(): void
    {
        $full = $this->row(1);
        $poor = $this->row(2, ['phone' => '', 'first_name' => null, 'matricule_internal' => '', 'steam_id' => ' ']);
        self::assertSame(100, DataQualityAnalyzer::member($full, self::T1)['percent']);
        $m = DataQualityAnalyzer::member($poor, self::T1);
        self::assertSame(15, $m['total']);
        self::assertSame(11, $m['filled_count']);
        self::assertSame(73, $m['percent']);
        self::assertSame(['first_name', 'phone', 'matricule_internal', 'steam_id'], $m['missing']);
        self::assertSame(80, $m['block_percent'][DataQualityCatalog::BLOCK_IDENTITY]);
        self::assertSame(50, $m['block_percent'][DataQualityCatalog::BLOCK_CONTACT]);
        self::assertSame('2026-10-02 08:00:00', $m['updated_at']);
    }

    public function testFieldAbsentFromSourceIsNotCounted(): void
    {
        $row = $this->row(1);
        unset($row['tenant_member_number'], $row['phone']);
        $m = DataQualityAnalyzer::member($row, self::T1);
        self::assertSame(13, $m['total']);
        self::assertSame(100, $m['percent']);
    }

    public function testDuplicatesAndIdentityChecks(): void
    {
        $this->source->rows[self::T1] = [
            $this->row(1, ['callsign' => 'Wolf', 'steam_id' => '111']),
            $this->row(2, ['callsign' => ' WOLF ', 'steam_id' => '111', 'tenant_member_number' => 'M1']),
            $this->row(3, ['callsign' => 'Prenom3', 'display_name' => 'a@b.fr', 'email' => 'pas-un-mail']),
            $this->row(4, ['first_name' => 'Jean', 'last_name' => 'Dupont', 'birth_date' => '1991-02-03']),
            $this->row(5, ['first_name' => 'jean', 'last_name' => 'DUPONT', 'birth_date' => '1991-02-03']),
        ];
        $out = $this->build();
        self::assertSame(['duplicate_callsign', 'duplicate_member_number', 'duplicate_steam'], $this->codesOf($out, 2));
        self::assertSame(['duplicate_callsign', 'duplicate_member_number', 'duplicate_steam'], $this->codesOf($out, 1));
        self::assertSame(['callsign_is_real_name', 'display_name_is_email', 'email_invalid'], $this->codesOf($out, 3));
        self::assertSame(['duplicate_identity'], $this->codesOf($out, 4));
        self::assertSame(['duplicate_identity'], $this->codesOf($out, 5));
        $dup = null;
        foreach ($out['findings']['rows'] as $f) {
            if ($f['code'] === 'duplicate_steam' && $f['user_id'] === 1) {
                $dup = $f;
            }
        }
        self::assertSame([2], $dup['others']);
    }

    public function testGradeUnitDateAndAccountChecks(): void
    {
        $this->source->rows[self::T1] = [
            $this->row(1, ['grade_tenant' => self::T2]),
            $this->row(2, ['rank_display' => 'Capitaine']),
            $this->row(3, ['primary_unit_tenant' => self::T2]),
            $this->row(4, ['primary_unit_id' => null, 'main_unit_id' => null, 'unit_count' => 0]),
            $this->row(5, ['enlistment_date' => '2027-01-01', 'birth_date' => '2030-01-01']),
            $this->row(6, ['enlistment_date' => '1970-01-01', 'birth_date' => null]),
            $this->row(7, ['enlistment_date' => '2010-01-01', 'birth_date' => '2012-06-01']),
            $this->row(8, ['pp_id' => null]),
            $this->row(9, ['last_login_at' => null, 'created_at' => '2026-01-01 00:00:00']),
            $this->row(10, ['last_login_at' => null, 'created_at' => '2026-11-10 00:00:00']),
            $this->row(11, ['grade_id' => 0, 'grade_tenant' => null, 'rank_display' => '']),
        ];
        $this->source->qualifications[self::T1] = [11 => ['bad_range' => 1, 'future' => 2]];
        $out = $this->build();
        self::assertSame(['grade_unknown'], $this->codesOf($out, 1));
        self::assertSame(['grade_display_mismatch'], $this->codesOf($out, 2));
        self::assertSame(['unit_foreign'], $this->codesOf($out, 3));
        self::assertSame(['unit_missing'], $this->codesOf($out, 4));
        self::assertSame(['date_birth_implausible', 'date_enlistment_before_birth', 'date_enlistment_future'], $this->codesOf($out, 5));
        self::assertSame(['date_enlistment_old'], $this->codesOf($out, 6));
        self::assertSame(['date_enlistment_before_birth'], $this->codesOf($out, 7));
        self::assertSame(['account_without_profile'], $this->codesOf($out, 8));
        self::assertSame(['never_connected'], $this->codesOf($out, 9));
        self::assertSame([], $this->codesOf($out, 10));
        self::assertSame(['qualification_dates', 'qualification_future'], $this->codesOf($out, 11));
    }

    public function testEveryCheckHasARowEvenWithoutFindings(): void
    {
        $this->source->rows[self::T1] = [$this->row(1)];
        $out = $this->build();
        self::assertSame(count(DataQualityCatalog::CHECKS), $out['checks']['all']);
        self::assertSame(0, $out['findings']['all']);
        $hit = $this->build(self::T1, ['ch_f' => 'hit']);
        self::assertSame(0, $hit['checks']['total']);
        $ok = $this->build(self::T1, ['ch_f' => 'ok']);
        self::assertSame(count(DataQualityCatalog::CHECKS), $ok['checks']['total']);
    }

    public function testScopeIsLimitedToTheCommunity(): void
    {
        $this->source->rows[self::T1] = [$this->row(1)];
        $own = ['grade_tenant' => self::T2, 'primary_unit_tenant' => self::T2];
        $this->source->rows[self::T2] = [$this->row(2, ['grade_tenant' => self::T1] + $own), $this->row(3, $own), $this->row(4, $own)];
        $out = $this->build(self::T2);
        self::assertSame([self::T2], $this->source->asked);
        self::assertSame(3, $out['summary']['members']);
        self::assertSame(1, $out['findings']['all']);
        self::assertSame('grade_unknown', $out['findings']['rows'][0]['code']);
        self::assertSame(1, $this->build(self::T1)['summary']['members']);
    }

    public function testPeopleTableCombinesCampaignConfirmationAndAnomalies(): void
    {
        $this->source->rows[self::T1] = [
            $this->row(1, ['display_name' => 'Alpha']),
            $this->row(2, ['display_name' => 'Bravo', 'phone' => '', 'updated_at' => '2024-01-01 00:00:00', 'pp_updated' => '2024-01-02 00:00:00', 'uli_updated' => null, 'callsign' => 'Xy', 'first_name' => 'Xy']),
            $this->row(3, ['display_name' => 'Charlie']),
        ];
        $this->source->confirmations[self::T1] = [1 => '2026-05-05 09:00:00'];
        $this->source->campaigns[self::T1] = [
            'campaign' => ['id' => 4, 'title' => 'Fiabilisation 2026', 'status' => 'open', 'due_on' => '2026-12-31'],
            'members' => [
                ['user_id' => 1, 'confirmed' => true, 'open_flags' => 0],
                ['user_id' => 2, 'confirmed' => true, 'open_flags' => 2],
            ],
        ];
        $out = $this->build();
        self::assertSame(4, $out['campaign']['id']);
        $by = [];
        foreach ($out['people']['rows'] as $r) {
            $by[$r['id']] = $r;
        }
        self::assertSame('confirmed', $by[1]['campaign']);
        self::assertSame('flagged', $by[2]['campaign']);
        self::assertSame(2, $by[2]['open_flags']);
        self::assertSame('out', $by[3]['campaign']);
        self::assertSame('2026-05-05 09:00:00', $by[1]['confirmed_at']);
        self::assertSame('', $by[3]['confirmed_at']);
        self::assertTrue($by[2]['stale']);
        self::assertFalse($by[1]['stale']);
        self::assertSame(1, $by[2]['anomalies']);
        self::assertSame(['contact'], $this->sorted($by[2]['missing_blocks']));
        self::assertSame(1, $out['summary']['stale']);
        self::assertSame(1, $out['summary']['with_anomalies']);
        // Le tableau est trié du moins rempli au plus rempli par défaut.
        self::assertSame(2, $out['people']['rows'][0]['id']);
        self::assertSame([2], $this->ids($this->build(self::T1, ['pe_f' => 'flagged'])['people']));
        self::assertSame([3], $this->ids($this->build(self::T1, ['pe_f' => 'out'])['people']));
        self::assertSame([2], $this->ids($this->build(self::T1, ['pe_f' => 'anomaly'])['people']));
        self::assertSame([1, 3], $this->sorted($this->ids($this->build(self::T1, ['pe_f' => 'complete'])['people'])));
    }

    /** @param list<mixed> $v @return list<mixed> */
    private function sorted(array $v): array
    {
        sort($v);

        return $v;
    }

    public function testUnitsTableAggregatesPrimaryUnit(): void
    {
        $this->source->units[self::T1] = [10 => 'Alpha', 11 => 'Bravo'];
        $this->source->rows[self::T1] = [
            $this->row(1),
            $this->row(2, ['phone' => '', 'first_name' => '', 'last_name' => '']),
            $this->row(3, ['primary_unit_id' => 11, 'main_unit_id' => 11]),
            $this->row(4, ['primary_unit_id' => null, 'main_unit_id' => null, 'unit_count' => 0]),
        ];
        $this->source->campaigns[self::T1] = [
            'campaign' => ['id' => 1, 'title' => 'C', 'status' => 'open'],
            'members' => [['user_id' => 1, 'confirmed' => true], ['user_id' => 2, 'confirmed' => false], ['user_id' => 3, 'confirmed' => true]],
        ];
        $units = [];
        foreach ($this->build()['units']['rows'] as $r) {
            $units[$r['unit_id']] = $r;
        }
        self::assertSame(3, count($units));
        self::assertSame(2, $units[10]['members']);
        self::assertSame(90, $units[10]['percent']);
        self::assertSame(50, $units[10]['confirmed_percent']);
        self::assertSame(1, $units[10]['none']);
        self::assertSame(100, $units[11]['confirmed_percent']);
        self::assertSame(-1, $units[0]['confirmed_percent']);
        self::assertSame('Alpha', $units[10]['label']);
    }

    public function testFieldsTableAndBlocks(): void
    {
        $this->source->rows[self::T1] = [$this->row(1), $this->row(2, ['phone' => '']), $this->row(3, ['phone' => null])];
        $out = $this->build();
        $fields = [];
        foreach ($out['fields']['rows'] as $r) {
            $fields[$r['key']] = $r;
        }
        self::assertSame(15, count($fields));
        self::assertSame(1, $fields['phone']['filled']);
        self::assertSame(3, $fields['phone']['total']);
        self::assertSame(33, $fields['phone']['percent']);
        self::assertSame('phone', $out['fields']['rows'][0]['key']);
        $contact = array_values(array_filter($out['blocks'], static fn (array $b): bool => $b['key'] === 'contact'));
        self::assertSame(67, $contact[0]['percent']);
        self::assertSame(['email', 'phone'], $this->sorted(array_column($this->build(self::T1, ['da_f' => 'contact'])['fields']['rows'], 'key')));
    }

    public function testTableSortsFiltersPaginatesAndIgnoresUnknownSort(): void
    {
        $rows = [];
        for ($i = 1; $i <= 60; $i++) {
            $rows[] = ['id' => $i, 'name' => 'Nom ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'percent' => $i % 7, 'unit' => $i % 2 === 0 ? 'Pair' : 'Impair'];
        }
        $t = DataQualityTables::table($rows, ['pe_p' => '2'], 'pe', ['name', 'percent'], 'name', 'asc', null, null);
        self::assertSame(3, $t['pages']);
        self::assertSame(2, $t['page']);
        self::assertSame(25, count($t['rows']));
        self::assertSame(26, $t['rows'][0]['id']);
        $desc = DataQualityTables::table($rows, ['pe_s' => 'name', 'pe_d' => 'desc'], 'pe', ['name'], 'name', 'asc', null, null);
        self::assertSame(60, $desc['rows'][0]['id']);
        $bad = DataQualityTables::table($rows, ['pe_s' => 'id; DROP TABLE users', 'pe_d' => 'sideways'], 'pe', ['name'], 'name', 'asc', null, null);
        self::assertSame('name', $bad['sort']);
        self::assertSame('asc', $bad['dir']);
        $search = DataQualityTables::table($rows, ['pe_q' => ' NOM 07 '], 'pe', ['name'], 'name', 'asc', null, static fn (array $r, string $q): bool => str_contains(mb_strtolower($r['name']), $q));
        self::assertSame([7], array_column($search['rows'], 'id'));
        $beyond = DataQualityTables::table($rows, ['pe_p' => '999'], 'pe', ['name'], 'name', 'asc', null, null);
        self::assertSame(3, $beyond['page']);
        self::assertSame(10, count($beyond['rows']));
    }

    public function testOnlyTableParametersAreKeptForLinks(): void
    {
        $this->source->rows[self::T1] = [$this->row(1)];
        $out = $this->build(self::T1, ['pe_q' => 'abc', 'simuler' => '1', 'evil' => 'x', 'pe_zz' => 'y', 'an_u' => ['x']]);
        self::assertSame(['pe_q' => 'abc', 'simuler' => '1'], $out['query']);
    }

    public function testFindingsAreFilteredByMemberAndSeverity(): void
    {
        $this->source->rows[self::T1] = [
            $this->row(1, ['grade_tenant' => self::T2, 'rank_display' => 'Capitaine']),
            $this->row(2, ['rank_display' => 'Capitaine']),
        ];
        self::assertSame(2, $this->build()['findings']['all']);
        self::assertSame([1], $this->ids($this->build(self::T1, ['an_u' => '1'])['findings']));
        self::assertSame([1], $this->ids($this->build(self::T1, ['an_v' => 'high'])['findings']));
        self::assertSame([2], $this->ids($this->build(self::T1, ['an_f' => 'grade_display_mismatch'])['findings']));
        self::assertSame('grade_unknown', $this->build()['findings']['rows'][0]['code']);
    }

    public function testPartialEscapesHtmlAndExposesNoPersonalValues(): void
    {
        $this->source->rows[self::T1] = [
            $this->row(1, ['display_name' => '<script>alert(1)</script>', 'first_name' => 'SecretPrenom', 'last_name' => 'SecretNom', 'phone' => '0699999999', 'callsign' => 'SecretPrenom']),
        ];
        $this->source->units[self::T1] = [10 => '<b>Unité</b>'];
        $html = $this->render($this->build());
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;b&gt;Unité&lt;/b&gt;', $html);
        self::assertStringNotContainsString('SecretNom', $html);
        self::assertStringNotContainsString('0699999999', $html);
        self::assertStringContainsString('id="dq-personnes"', $html);
        self::assertStringContainsString('id="dq-unites"', $html);
        self::assertStringContainsString('id="dq-verifications"', $html);
        self::assertStringContainsString('id="dq-champs"', $html);
        self::assertStringContainsString('personnel/1/edit', $html);
        self::assertStringContainsString('Corriger la fiche', $html);
    }

    public function testPartialRendersActiveFiltersWithResetAndSortLinks(): void
    {
        $this->source->rows[self::T1] = [$this->row(1), $this->row(2, ['phone' => '', 'first_name' => '', 'last_name' => '', 'matricule_internal' => '', 'steam_id' => '', 'service_number' => ''])];
        $html = $this->render($this->build(self::T1, ['pe_q' => 'membre', 'pe_f' => 'incomplete', 'simuler' => '1', 'an_u' => '2']));
        self::assertStringContainsString('Réinitialiser', $html);
        self::assertStringContainsString('name="simuler" value="1"', $html);
        self::assertStringContainsString('pe_s=percent', $html);
        self::assertStringContainsString('name="pe_q" value="membre"', $html);
        self::assertStringContainsString('<option value="incomplete" selected>', $html);
        self::assertStringContainsString('Retirer le filtre sur le membre n° 2', $html);
    }

    public function testPartialShowsAnUnavailableNoticeWhenTablesCannotBeBuilt(): void
    {
        $html = $this->render(['ready' => false]);
        self::assertStringContainsString('id="dq-personnes"', $html);
        self::assertStringContainsString('n’a pas pu être préparé', $html);
    }

    /** @param array<string, mixed> $tables */
    private function render(array $tables): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $icon = static fn (string $name): string => '<svg data-icon="' . $name . '"></svg>';
        $fmtDate = static fn (mixed $raw, bool $withTime = true): string => (string) $raw;
        if (!function_exists('url')) {
            require_once self::ROOT . '/app/Support/helpers.php';
        }
        ob_start();
        try {
            require self::ROOT . '/views/admin/organization/partials/data_quality_tables.php';
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    private const ROOT = __DIR__ . '/../..';

    public function testRouteKeepsOrganizationAdminMiddlewareAndControllerFeedsTheView(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertMatchesRegularExpression('#\$router->get\(\'/back-office/organisation/qualite-donnees\', \[OrganizationDataQualityController::class, \'index\'\], \[AuthMiddleware::class, OrganizationAdminMiddleware::class\]\)#', $routes);
        $controller = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/OrganizationDataQualityController.php');
        self::assertStringContainsString("'tables' => \$this->tables(\$tenantId, \$request->queryParams())", $controller);
        $view = (string) file_get_contents(self::ROOT . '/views/admin/organization/data_quality.php');
        self::assertStringContainsString('partials/data_quality_tables.php', $view);
        foreach (['personnes', 'unites', 'verifications', 'champs'] as $id) {
            self::assertStringContainsString("'id' => '" . $id . "'", $view);
        }
    }

    public function testSourceQueriesAreScopedAndBatched(): void
    {
        $sql = (string) file_get_contents(self::ROOT . '/app/Services/DataQuality/DatabaseDataQualitySource.php');
        self::assertStringContainsString('sqlMemberOfTenantPredicate', $sql);
        self::assertStringContainsString('LIMIT', $sql);
        self::assertStringContainsString('tenant_id = ?', $sql);
        self::assertStringNotContainsString('foreach ($ids', $sql);
    }

    public function testNoEyebrowHeadingsInTheNewPartial(): void
    {
        $view = (string) file_get_contents(self::ROOT . '/views/admin/organization/partials/data_quality_tables.php');
        self::assertStringNotContainsString('eyebrow', $view);
        self::assertStringNotContainsString('kicker', $view);
    }
}
