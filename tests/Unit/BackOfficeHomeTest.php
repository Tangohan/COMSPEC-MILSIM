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
        self::assertSame('kpi_members', $manager[0]['id']);
        self::assertSame('operation', $manager[4]['id']);
        self::assertFalse($manager[4]['hidden']);
        self::assertSame('tasks', $support[0]['id']);
        $hiddenForSupport = array_column(array_filter($support, static fn (array $i): bool => $i['hidden']), 'id');
        self::assertContains('operation', $hiddenForSupport);
        self::assertEqualsCanonicalizing(array_keys(HomeLayout::catalog()), array_column($manager, 'id'), 'Chaque widget du catalogue figure une fois.');
    }

    public function testNormalizeDropsUnknownAndUnreadableAndAddsNewWidgets(): void
    {
        $raw = [
            'v' => 2,
            'items' => [
                ['id' => 'feed', 'w' => 99, 'h' => 0, 'hidden' => false],
                ['id' => 'inconnu', 'w' => 4, 'h' => 2],
                ['id' => 'sheet:7', 'w' => 12, 'h' => 4],
                ['id' => 'sheet:9', 'w' => 12, 'h' => 4],
                ['id' => 'feed', 'w' => 4, 'h' => 2, 'hidden' => true],
                ['id' => 'agenda', 'w' => 5, 'h' => 4, 'hidden' => true],
            ],
            'known' => ['feed', 'agenda', 'tasks'],
        ];
        $out = HomeLayout::normalize($raw, HomeLayout::ROLE_MANAGER, [7]);
        $ids = array_column($out, 'id');
        self::assertSame(['feed', 'sheet:7', 'agenda'], array_slice($ids, 0, 3));
        self::assertNotContains('sheet:9', $ids, 'Un tableau qui n’est plus lisible disparaît.');
        self::assertNotContains('inconnu', $ids);
        self::assertSame([8, 2], [$out[0]['w'], $out[0]['h']], 'Taille ramenée dans les bornes du widget.');
        self::assertSame([5, 4], [$out[2]['w'], $out[2]['h']]);
        $byId = array_column($out, null, 'id');
        self::assertTrue($byId['tasks']['hidden'], 'Widget connu mais absent : retiré par la personne, reste masqué.');
        self::assertFalse($byId['shortcuts']['hidden'], 'Widget apparu depuis : visible.');
        self::assertSame(HomeLayout::defaults(HomeLayout::ROLE_MANAGER), HomeLayout::normalize(null, HomeLayout::ROLE_MANAGER, []));
    }

    public function testNewSheetGoesFirst(): void
    {
        $with = HomeLayout::withSheet(HomeLayout::defaults(HomeLayout::ROLE_MANAGER), 12);
        self::assertSame(['id' => 'sheet:12', 'w' => 12, 'h' => 4, 'hidden' => false], $with[0]);
        self::assertSame(1, \count(array_filter($with, static fn (array $i): bool => $i['id'] === 'sheet:12')));
        self::assertFalse(HomeLayout::isSheet('sheet:0'));
        self::assertFalse(HomeLayout::isSheet('sheet:1x'));
        self::assertSame(['def' => [12, 4], 'min' => [6, 3], 'max' => [12, HomeLayout::MAX_ROWS]], HomeLayout::bounds('sheet:3'));
        self::assertNull(HomeLayout::bounds('inconnu'));
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
