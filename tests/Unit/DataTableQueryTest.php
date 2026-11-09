<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BackOffice\Tables\DataTableQuery;
use App\Services\BackOffice\Tables\DataTableSources;
use PHPUnit\Framework\TestCase;

/**
 * Constructeur de tableaux de données : définition normalisée, filtres, tri, regroupement, CSV sûr, instantané tableur.
 */
final class DataTableQueryTest extends TestCase
{
    /** @return array<string, array{label: string, type: string}> */
    private static function columns(): array
    {
        return [
            'name' => ['label' => 'Nom', 'type' => 'text'],
            'unit' => ['label' => 'Unité', 'type' => 'text'],
            'since' => ['label' => 'Arrivée', 'type' => 'date'],
            'days' => ['label' => 'Jours', 'type' => 'number'],
        ];
    }

    /** @return list<array<string, string|float>> */
    private static function rows(): array
    {
        return [
            ['name' => 'Émile', 'unit' => 'Alpha', 'since' => '2025-03-01', 'days' => 10.0],
            ['name' => 'Bruno', 'unit' => 'Alpha', 'since' => '2024-01-15', 'days' => 4.0],
            ['name' => 'Chloé', 'unit' => 'Bravo', 'since' => '', 'days' => ''],
            ['name' => '=CMD()', 'unit' => '', 'since' => '2026-01-01', 'days' => 100.0],
        ];
    }

    public function testNormalizeIgnoresUnknownKeysAndOperators(): void
    {
        $def = DataTableQuery::normalize([
            'columns' => ['name', 'password_hash', 'unit', 'name'],
            'filters' => [
                ['col' => 'email', 'op' => 'contains', 'value' => 'x'],
                ['col' => 'unit', 'op' => 'DROP TABLE', 'value' => 'x'],
                ['col' => 'unit', 'op' => 'eq', 'value' => 'Alpha'],
            ],
            'sort' => ['col' => 'secret', 'dir' => 'desc'],
            'group' => ['col' => 'unit', 'agg_col' => 'name', 'agg_fn' => 'sum'],
        ], 'effectifs', self::columns());

        self::assertSame(['name', 'unit'], $def['columns']);
        self::assertCount(1, $def['filters']);
        self::assertSame('Alpha', $def['filters'][0]['value']);
        self::assertSame('', $def['sort']['col']);
        self::assertSame('unit', $def['group']['col']);
        self::assertSame('', $def['group']['agg_col'], 'on ne calcule que sur une colonne numérique');
    }

    public function testNormalizeDefaultsAndLimits(): void
    {
        $many = [];
        for ($i = 0; $i < 30; $i++) {
            $many['c' . $i] = ['label' => 'C' . $i, 'type' => 'text'];
        }
        $def = DataTableQuery::normalize(['columns' => array_keys($many)], 'x', $many);
        self::assertCount(DataTableQuery::MAX_COLUMNS, $def['columns']);
        $empty = DataTableQuery::normalize([], 'x', self::columns());
        self::assertSame(['name', 'unit', 'since', 'days'], $empty['columns']);
    }

    public function testFilterSortAndDisplay(): void
    {
        $def = DataTableQuery::normalize([
            'columns' => ['name', 'days'],
            'filters' => [['col' => 'days', 'op' => 'gt', 'value' => '3']],
            'sort' => ['col' => 'days', 'dir' => 'desc'],
        ], 'x', self::columns());
        $res = DataTableQuery::run(self::rows(), $def, self::columns());
        self::assertSame(3, $res['total']);
        self::assertSame([['=CMD()', '100'], ['Émile', '10'], ['Bruno', '4']], $res['rows']);
    }

    public function testEmptyValuesSortLastAndTextIgnoresAccents(): void
    {
        $def = DataTableQuery::normalize(['columns' => ['name', 'since'], 'sort' => ['col' => 'since', 'dir' => 'asc']], 'x', self::columns());
        $res = DataTableQuery::run(self::rows(), $def, self::columns());
        self::assertSame('', $res['rows'][3][1]);
        $def = DataTableQuery::normalize(['columns' => ['name'], 'sort' => ['col' => 'name', 'dir' => 'asc']], 'x', self::columns());
        $names = array_column(DataTableQuery::run(self::rows(), $def, self::columns())['rows'], 0);
        self::assertSame(['=CMD()', 'Bruno', 'Chloé', 'Émile'], $names);
    }

    public function testGroupCountsAndAggregates(): void
    {
        $def = DataTableQuery::normalize([
            'columns' => ['name'],
            'group' => ['col' => 'unit', 'agg_col' => 'days', 'agg_fn' => 'avg'],
            'sort' => ['col' => '_count', 'dir' => 'desc'],
        ], 'x', self::columns());
        $res = DataTableQuery::run(self::rows(), $def, self::columns());
        self::assertSame(['Unité', 'Effectif', 'Moyenne · Jours'], array_column($res['cols'], 'label'));
        self::assertSame(['Alpha', '2', '7'], $res['rows'][0]);
        $labels = array_column($res['rows'], 0);
        self::assertContains('(vide)', $labels);
        self::assertCount(3, $res['rows']);
    }

    public function testCsvNeutralisesFormulaInjection(): void
    {
        $cols = [['key' => 'a', 'label' => '=HYPERLINK("x")', 'type' => 'text'], ['key' => 'b', 'label' => 'N', 'type' => 'number']];
        $csv = DataTableQuery::toCsv($cols, [["=1+1", '-5'], ['+cmd', '@SUM(A1)'], ["\tx", 'Jean'], ['-12,5', '3']]);
        self::assertStringStartsWith("\u{FEFF}", $csv);
        self::assertStringContainsString("'=HYPERLINK", $csv);
        self::assertStringContainsString("'=1+1;-5", $csv, 'un nombre négatif reste un nombre');
        self::assertStringContainsString("'+cmd;'@SUM(A1)", $csv);
        self::assertStringContainsString("'\tx", $csv);
        self::assertStringContainsString('-12,5;3', $csv);
    }

    public function testSheetSnapshotIsBoundedAndNeverStartsWithFormula(): void
    {
        $cols = [];
        for ($i = 0; $i < 15; $i++) {
            $cols[] = ['key' => 'c' . $i, 'label' => $i === 0 ? '=A' : 'C' . $i, 'type' => 'text'];
        }
        $rows = [];
        for ($r = 0; $r < 250; $r++) {
            $rows[] = array_fill(0, 15, '=SOMME(A1:A2)');
        }
        $sheet = DataTableQuery::toSheet($cols, $rows);
        self::assertTrue($sheet['truncated']);
        self::assertCount(12, $sheet['data']['cols']);
        self::assertCount(200, $sheet['data']['rows']);
        self::assertSame("'=A", $sheet['data']['cols'][0]['name']);
        self::assertSame("'=SOMME(A1:A2)", $sheet['data']['rows'][0][0]);
    }

    public function testCatalogExposesNoSensitiveColumnAndEverySourceHasARight(): void
    {
        $forbidden = ['email', 'password', 'steam_id', 'phone', 'birth', 'notes', 'medical', 'token'];
        foreach (DataTableSources::catalog() as $key => $src) {
            self::assertNotSame([], $src['columns'], $key);
            foreach ($src['columns'] as $colKey => $col) {
                self::assertContains($col['type'], ['text', 'number', 'date'], $key . '.' . $colKey);
                foreach ($forbidden as $bad) {
                    self::assertStringNotContainsString($bad, $colKey, $key . '.' . $colKey);
                }
            }
        }
        $nobody = new \App\Core\Gate();
        foreach (array_keys(DataTableSources::catalog()) as $key) {
            self::assertFalse(DataTableSources::allowed($key, $nobody), 'sans aucun droit : ' . $key . ' refusé');
        }
        self::assertFalse(DataTableSources::allowed('users; DROP TABLE x', $nobody));
        self::assertSame([], DataTableSources::availableFor($nobody));
    }

    public function testSourceRightsAreDistinct(): void
    {
        $hr = new \App\Core\Gate();
        $hr->setPermissions(['personnel.profile.update']);
        self::assertTrue(DataTableSources::allowed('effectifs', $hr));
        self::assertTrue(DataTableSources::allowed('sans_comptes', $hr));
        self::assertFalse(DataTableSources::allowed('presences', $hr), 'les présences demandent le droit événements ou administrateur');
        self::assertFalse(DataTableSources::allowed('materiel', $hr));
        $admin = new \App\Core\Gate();
        $admin->setPermissions(['admin.organization']);
        self::assertSame(array_keys(DataTableSources::catalog()), array_keys(DataTableSources::availableFor($admin)));
    }
}
