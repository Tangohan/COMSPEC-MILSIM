<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BackOffice\Home\HomeLayout;
use App\Services\BackOffice\Home\SheetData;
use PHPUnit\Framework\TestCase;

/**
 * Accueil personnalisable du back-office : disposition et tableaux.
 */
final class BackOfficeHomeTest extends TestCase
{
    public function testDefaultLayoutDependsOnRole(): void
    {
        $manager = HomeLayout::defaults(HomeLayout::ROLE_MANAGER);
        $support = HomeLayout::defaults(HomeLayout::ROLE_SUPPORT);
        self::assertSame('operation', $manager[0]['id']);
        self::assertFalse($manager[0]['hidden']);
        self::assertSame('tasks', $support[0]['id']);
        $hiddenForSupport = array_column(array_filter($support, static fn (array $i): bool => $i['hidden']), 'id');
        self::assertContains('operation', $hiddenForSupport);
        self::assertSame(array_keys(HomeLayout::catalog()), array_values(array_unique(array_merge(array_column($manager, 'id'), array_keys(HomeLayout::catalog())))));
    }

    public function testNormalizeDropsUnknownAndUnreadableAndAddsNewBlocks(): void
    {
        $raw = [
            'items' => [
                ['id' => 'kpis', 'hidden' => false, 'size' => 'énorme'],
                ['id' => 'inconnu'],
                ['id' => 'sheet:7', 'hidden' => false, 'size' => 'full'],
                ['id' => 'sheet:9'],
                ['id' => 'kpis', 'hidden' => true],
                ['id' => 'feed', 'hidden' => true, 'size' => 'wide'],
            ],
            'known' => ['kpis', 'feed', 'tasks'],
        ];
        $out = HomeLayout::normalize($raw, HomeLayout::ROLE_MANAGER, [7]);
        $ids = array_column($out, 'id');
        self::assertSame(['kpis', 'sheet:7', 'feed'], array_slice($ids, 0, 3));
        self::assertNotContains('sheet:9', $ids, 'Un tableau qui n’est plus lisible disparaît.');
        self::assertNotContains('inconnu', $ids);
        self::assertSame('full', $out[0]['size'], 'Largeur invalide : celle du bloc.');
        $byId = array_column($out, null, 'id');
        self::assertTrue($byId['tasks']['hidden'], 'Bloc connu mais absent : retiré par la personne, reste masqué.');
        self::assertFalse($byId['shortcuts']['hidden'], 'Bloc apparu depuis : visible.');
        self::assertSame(HomeLayout::defaults(HomeLayout::ROLE_MANAGER), HomeLayout::normalize(null, HomeLayout::ROLE_MANAGER, []));
    }

    public function testFormMoveAndNewSheet(): void
    {
        $stored = HomeLayout::fromForm(['feed', 'kpis', 'sheet:3'], ['kpis', 'sheet:3'], ['kpis' => 'wide'], HomeLayout::ROLE_MANAGER, [3]);
        self::assertSame(1, $stored['v']);
        self::assertSame(['feed', 'kpis', 'sheet:3'], array_slice(array_column($stored['items'], 'id'), 0, 3));
        self::assertTrue($stored['items'][0]['hidden']);
        self::assertSame('wide', $stored['items'][1]['size']);
        $moved = HomeLayout::move($stored['items'], 'kpis', -1);
        self::assertSame(['kpis', 'feed'], array_slice(array_column($moved, 'id'), 0, 2));
        self::assertSame($moved, HomeLayout::move($moved, 'kpis', -1));
        $with = HomeLayout::withSheet($moved, 12);
        self::assertSame(['id' => 'sheet:12', 'hidden' => false, 'size' => 'full'], $with[0]);
        self::assertFalse(HomeLayout::isSheet('sheet:0'));
        self::assertFalse(HomeLayout::isSheet('sheet:1x'));
    }

    public function testSheetLimitsAndSanitize(): void
    {
        $raw = ['cols' => array_fill(0, 20, ['name' => str_repeat('x', 90)]), 'rows' => array_fill(0, 300, ["a\tb", ['nested'], 3])];
        $data = SheetData::sanitize($raw);
        self::assertSame(SheetData::MAX_COLS, \count($data['cols']));
        self::assertSame(SheetData::MAX_ROWS, \count($data['rows']));
        self::assertSame(SheetData::MAX_HEADER, mb_strlen($data['cols'][0]['name']));
        self::assertSame(['a b', '', '3'], array_slice($data['rows'][0], 0, 3));
        self::assertSame(SheetData::MAX_COLS, \count($data['rows'][0]));
        self::assertSame('L', SheetData::letter(11));
    }

    public function testFormulas(): void
    {
        $data = SheetData::sanitize(['cols' => [['name' => 'Nom'], ['name' => 'Qté'], ['name' => 'Total']], 'rows' => [
            ['Gilets', '4', '=SOMME(B1:B3)'],
            ['Casques', '2,5', '=moyenne(B1:B3)'],
            ['Radios', 'six', '=NB(B1:B3)'],
            ['', '', '=SUM(C1;C3)'],
            ['', '', '=C5'],
            ['', '', '=SOMME(C6)'],
            ['', '', '=AVERAGE(A1:A2)'],
        ]]);
        $out = SheetData::computed($data);
        self::assertSame('6,5', $out[0][2]);
        self::assertSame('3,25', $out[1][2]);
        self::assertSame('2', $out[2][2]);
        self::assertSame('8,5', $out[3][2]);
        self::assertSame('#ERR', $out[4][2]);
        self::assertSame('#CYCLE', $out[5][2]);
        self::assertSame('#DIV/0', $out[6][2]);
        self::assertSame('Gilets', $out[0][0]);
        self::assertSame(1234.5, SheetData::number('1 234,5'));
        self::assertNull(SheetData::number('12a'));
        self::assertNull(SheetData::refs('Z1', 5, 5));
    }

    public function testCellConflictAndStructure(): void
    {
        $data = SheetData::blank(2, 2);
        [$data, $res] = SheetData::setCell($data, 0, 1, 'x', '');
        self::assertSame('ok', $res);
        [$same, $res, $current] = SheetData::setCell($data, 0, 1, 'y', '');
        self::assertSame('conflict', $res, 'La case a changé depuis que la personne l’a vue.');
        self::assertSame('x', $current);
        self::assertSame($data, $same);
        self::assertSame('bounds', SheetData::setCell($data, 5, 0, 'z', null)[1]);

        [$d, $err] = SheetData::structure($data, 'add_col', -1, 'Prix');
        self::assertSame('', $err);
        self::assertSame('Prix', $d['cols'][2]['name']);
        self::assertSame(['', 'x', ''], $d['rows'][0]);
        [$d] = SheetData::structure($d, 'del_col', 1);
        self::assertSame(['', ''], $d['rows'][0]);
        [$d] = SheetData::structure($d, 'add_row', 0);
        self::assertSame(3, \count($d['rows']));
        [$d, $err] = SheetData::structure($d, 'del_row', 9);
        self::assertSame('Ligne introuvable.', $err);
        [$one, $err] = SheetData::structure(SheetData::blank(1, 1), 'del_col', 0);
        self::assertNotSame('', $err);
        $full = SheetData::blank(SheetData::MAX_COLS, SheetData::MAX_ROWS);
        self::assertNotSame('', SheetData::structure($full, 'add_row', -1)[1]);
        self::assertNotSame('', SheetData::structure($full, 'add_col', -1)[1]);
        self::assertSame('Action inconnue.', SheetData::structure($d, 'drop_table', 0)[1]);
    }

    public function testCsvRoundTripIsSafe(): void
    {
        $data = SheetData::sanitize(['cols' => [['name' => 'Nom'], ['name' => 'Valeur']], 'rows' => [
            ['=cmd|calc', '3'],
            ['+33 6 12', '@x'],
            ['-ok', '=SOMME(B1:B1)'],
        ]]);
        $csv = SheetData::toCsv($data);
        self::assertStringStartsWith("\u{FEFF}Nom;Valeur", $csv);
        self::assertStringContainsString("#ERR;3", $csv, 'Une formule inconnue n’est jamais exportée telle quelle.');
        self::assertStringContainsString("\"'+33 6 12\";'@x", $csv,'Pas de formule exécutée par Excel à l’ouverture.');
        self::assertDoesNotMatchRegularExpression('/^[=+@]/m', $csv);
        self::assertStringContainsString("'-ok;3", $csv);

        [$back, $warn] = SheetData::fromCsv("Nom,Prix\nA,\"1,5\"\nB,=SOMME(B1:B1)\nC,=HYPERLINK(\"x\")\n");
        self::assertSame('', $warn);
        self::assertSame(['Nom', 'Prix'], array_column($back['cols'], 'name'));
        self::assertSame('1,5', $back['rows'][0][1]);
        self::assertSame('=SOMME(B1:B1)', $back['rows'][1][1]);
        self::assertStringStartsWith("'=", $back['rows'][2][1]);

        $big = "h\n" . str_repeat("v\n", 260);
        [$cut, $warn] = SheetData::fromCsv($big);
        self::assertSame(SheetData::MAX_ROWS, \count($cut['rows']));
        self::assertNotSame('', $warn);
    }
}
