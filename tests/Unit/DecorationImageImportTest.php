<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DecorationImageImport as I;
use PHPUnit\Framework\TestCase;

final class DecorationImageImportTest extends TestCase
{
    public function testNameFromFilename(): void
    {
        self::assertSame('Ranger Tab', I::nameFromFilename('ranger_tab.png'));
        self::assertSame('Air Assault Badge', I::nameFromFilename('Air-Assault-Badge (1).png'));
        self::assertSame('Special Forces Tab', I::nameFromFilename('C:\\fakepath\\Special Forces Tab.webp'));
        self::assertSame('Brevet Para', I::nameFromFilename('brevet.para.PNG'));
        self::assertSame('SC1', I::nameFromFilename('SC1.png'));
        self::assertSame('Équipe De Choc', I::nameFromFilename('équipe_de_choc.jpg'));
        self::assertSame('', I::nameFromFilename('.png'));
    }

    public function testCodeFromName(): void
    {
        self::assertSame('RANGER_TAB', I::codeFromName('Ranger Tab'));
        self::assertSame('BREVET_DE_CHEF_D_EQUIPE', I::codeFromName('Brevet de chef d’équipe'));
        self::assertSame('PATHFINDER_BADGE', I::codeFromName('  Pathfinder   Badge '));
        self::assertSame(32, strlen(I::codeFromName(str_repeat('Très long nom ', 10))));
        self::assertSame('', I::codeFromName('—'));
    }

    public function testSanitizeCode(): void
    {
        self::assertSame('SF_TAB', I::sanitizeCode(' sf tab '));
        self::assertSame('AIR-ASSAULT', I::sanitizeCode('air-assault'));
        self::assertSame('', I::sanitizeCode('***'));
    }

    public function testNormalizeUploadsMultiple(): void
    {
        $files = I::normalizeUploads([
            'name' => ['a.png', 'b.png'],
            'type' => ['image/png', 'image/png'],
            'tmp_name' => ['/tmp/a', '/tmp/b'],
            'error' => [0, 4],
            'size' => [10, 0],
        ]);
        self::assertCount(2, $files);
        self::assertSame('b.png', $files[1]['name']);
        self::assertSame(4, $files[1]['error']);
        self::assertSame([], I::normalizeUploads(null));
        self::assertCount(1, I::normalizeUploads(['name' => 'x.png', 'tmp_name' => '/t', 'error' => 0, 'size' => 3]));
    }

    public function testBuildRowsDerivesAndDeduplicates(): void
    {
        $f = static fn (string $n, int $err = 0): array => ['name' => $n, 'type' => 'image/png', 'tmp_name' => '/tmp/' . $n, 'error' => $err, 'size' => 10];
        $out = I::buildRows(
            [$f('ranger_tab.png'), $f('skip.png', UPLOAD_ERR_NO_FILE), $f('sapper_tab.png'), $f('ranger-tab.png')],
            ['', '', 'Sapper Tab', ''],
            ['', '', 'sapper', ''],
            ['Infantry', '', 'Engineer', ''],
            ['Ranger School', '', '', '']
        );
        self::assertCount(2, $out['rows']);
        self::assertSame('Ranger Tab', $out['rows'][0]['name']);
        self::assertSame('RANGER_TAB', $out['rows'][0]['code']);
        self::assertSame('Infantry', $out['rows'][0]['branch']);
        self::assertSame('Ranger School', $out['rows'][0]['criterion']);
        self::assertSame('SAPPER', $out['rows'][1]['code']);
        self::assertSame('Engineer', $out['rows'][1]['branch']);
        self::assertCount(1, $out['skipped']);
        self::assertStringContainsString('RANGER_TAB', $out['skipped'][0]);
    }

    public function testBuildRowsLimit(): void
    {
        $files = [];
        for ($i = 0; $i < I::MAX_FILES + 2; $i++) {
            $files[] = ['name' => 'badge' . $i . '.png', 'type' => 'image/png', 'tmp_name' => '/t' . $i, 'error' => 0, 'size' => 1];
        }
        $out = I::buildRows($files, [], [], [], []);
        self::assertCount(I::MAX_FILES, $out['rows']);
        self::assertCount(2, $out['skipped']);
    }
}
