<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DevDispatchCatalog;
use PHPUnit\Framework\TestCase;

final class DevDispatchCatalogTest extends TestCase
{
    public function testCatalogHasSpotrepTechrepAndOneUpdatePerWaveItem(): void
    {
        $all = DevDispatchCatalog::all();
        $byKind = ['spotrep' => 0, 'techrep' => 0, 'update' => 0];
        foreach ($all as $row) {
            $kind = (string) ($row['kind'] ?? '');
            self::assertArrayHasKey($kind, $byKind);
            $byKind[$kind]++;
        }

        self::assertSame(2, $byKind['spotrep']);
        self::assertSame(2, $byKind['techrep']);
        self::assertSame(56, $byKind['update']);
        self::assertCount(60, $all);
    }

    public function testFeaturedIsLatestSpotrep(): void
    {
        $featured = DevDispatchCatalog::featured();
        self::assertNotNull($featured);
        self::assertSame('spotrep', $featured['kind']);
        self::assertSame('00002', $featured['number_pad']);
        self::assertTrue($featured['featured']);
        self::assertSame('Athena Operations', $featured['reporter']);
        self::assertSame('on August 24, 2026', $featured['reported_on']);
        self::assertStringContainsString('nouveautes/spotrep/00002', (string) $featured['href']);
    }

    public function testFindResolvesPaddedAndRawNumbers(): void
    {
        $a = DevDispatchCatalog::find('spotrep', '2');
        $b = DevDispatchCatalog::find('SPOTREP', '00002');
        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertSame($a['id'], $b['id']);

        $update = DevDispatchCatalog::find('update', '198');
        self::assertNotNull($update);
        self::assertSame('00198', $update['number_pad']);
        $hub = DevDispatchCatalog::find('update', '225');
        self::assertNotNull($hub);
        self::assertSame('00225', $hub['number_pad']);
        self::assertStringContainsString('hub', strtolower((string) $hub['title']));
        $pause = DevDispatchCatalog::find('update', '227');
        self::assertNotNull($pause);
        self::assertSame('00227', $pause['number_pad']);
        self::assertStringContainsString('affichage', strtolower((string) $pause['title']));
        $verify = DevDispatchCatalog::find('update', '228');
        self::assertNotNull($verify);
        self::assertSame('00228', $verify['number_pad']);
        self::assertStringContainsString('relev', strtolower((string) $verify['title']));
        $toolbar = DevDispatchCatalog::find('update', '229');
        self::assertNotNull($toolbar);
        self::assertSame('00229', $toolbar['number_pad']);
        self::assertStringContainsString('barre', strtolower((string) $toolbar['title']));
        $coverage = DevDispatchCatalog::find('update', '230');
        self::assertNotNull($coverage);
        self::assertSame('00230', $coverage['number_pad']);
        self::assertStringContainsString('affichage', strtolower((string) $coverage['title']));
        $modules = DevDispatchCatalog::find('update', '231');
        self::assertNotNull($modules);
        self::assertSame('00231', $modules['number_pad']);
        self::assertStringContainsString('modules carte', strtolower((string) $modules['title']));
        $roster = DevDispatchCatalog::find('update', '232');
        self::assertNotNull($roster);
        self::assertSame('00232', $roster['number_pad']);
        self::assertStringContainsString('terminaux', strtolower((string) $roster['title']));
        $reports = DevDispatchCatalog::find('update', '233');
        self::assertNotNull($reports);
        self::assertSame('00233', $reports['number_pad']);
        self::assertStringContainsString('signalement', strtolower((string) $reports['title']));
        $deferred = DevDispatchCatalog::find('update', '234');
        self::assertNotNull($deferred);
        self::assertSame('00234', $deferred['number_pad']);
        self::assertStringContainsString('diff', strtolower((string) $deferred['title']));
        $lostLink = DevDispatchCatalog::find('update', '235');
        self::assertNotNull($lostLink);
        self::assertSame('00235', $lostLink['number_pad']);
        self::assertStringContainsString('liaison perdue', strtolower((string) $lostLink['title']));
        $cascade = DevDispatchCatalog::find('update', '236');
        self::assertNotNull($cascade);
        self::assertSame('00236', $cascade['number_pad']);
        self::assertStringContainsString('carte', strtolower((string) $cascade['title']));
        $sceneCount = DevDispatchCatalog::find('update', '237');
        self::assertNotNull($sceneCount);
        self::assertSame('00237', $sceneCount['number_pad']);
        self::assertStringContainsString('bâtiment', strtolower((string) $sceneCount['title']));
        $dispatchSheet = DevDispatchCatalog::find('update', '238');
        self::assertNotNull($dispatchSheet);
        self::assertSame('00238', $dispatchSheet['number_pad']);
        self::assertStringContainsString('fiche de mise à jour', strtolower((string) $dispatchSheet['title']));
        $toolbarCss = DevDispatchCatalog::find('update', '243');
        self::assertNotNull($toolbarCss);
        self::assertSame('00243', $toolbarCss['number_pad']);
        self::assertStringContainsString('barre', strtolower((string) $toolbarCss['title']));
        $relief3d = DevDispatchCatalog::find('update', '239');
        self::assertNotNull($relief3d);
        self::assertSame('00239', $relief3d['number_pad']);
        self::assertStringContainsString('relief', strtolower((string) $relief3d['title']));
        self::assertNull(DevDispatchCatalog::find('spotrep', '999'));
        self::assertNull(DevDispatchCatalog::find('memo', '1'));
    }

    public function testPublicTextsStayHumanReadable(): void
    {
        $corpus = DevDispatchCatalog::publicCorpus();
        foreach ([
            'sqf',
            'endpoint',
            '/api/',
            'json',
            'sql',
            'mariadb',
            'pbo',
            'cfgfunctions',
            'post /',
            'get /',
            'rebuild',
        ] as $banned) {
            self::assertStringNotContainsString($banned, $corpus, $banned);
        }
        self::assertStringContainsString('ombrage', $corpus);
        self::assertStringContainsString('organigramme', $corpus);
    }
}
