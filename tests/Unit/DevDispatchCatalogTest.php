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
        self::assertSame(44, $byKind['update']);
        self::assertCount(48, $all);
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
