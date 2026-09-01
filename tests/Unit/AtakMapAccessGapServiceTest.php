<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakMapAccessGapService;
use PHPUnit\Framework\TestCase;

final class AtakMapAccessGapServiceTest extends TestCase
{
    public function testCatalogLabelsStayHuman(): void
    {
        $labels = [];
        foreach (AtakMapAccessGapService::FEATURES as $feature) {
            self::assertNotSame('', (string) $feature['label']);
            self::assertNotSame('', (string) $feature['hint']);
            $labels[] = mb_strtolower($feature['label'] . ' ' . $feature['hint']);
        }
        $corpus = implode(' ', $labels);
        foreach (['personnel.profile', 'atak.sse', 'documents.view', 'admin.access', 'json', 'slug', 'sql'] as $banned) {
            self::assertStringNotContainsString($banned, $corpus);
        }
        self::assertStringContainsString('fiches des opérateurs', $corpus);
        self::assertStringContainsString('renseignement', $corpus);
        self::assertStringContainsString('documents de mission', $corpus);
    }

    public function testGapsOnlyWhenProfileLacksTheView(): void
    {
        $none = AtakMapAccessGapService::gapsForAllows(static fn (string $slug): bool => false);
        self::assertCount(3, $none);

        $full = AtakMapAccessGapService::gapsForAllows(static fn (string $slug): bool => true);
        self::assertSame([], $full);

        $noSse = AtakMapAccessGapService::gapsForAllows(static function (string $slug): bool {
            return $slug !== 'atak.sse.access'
                && $slug !== 'atak.sse.case.manage'
                && $slug !== 'atak.sse.grant'
                && $slug !== 'admin.access';
        });
        $ids = array_column($noSse, 'id');
        self::assertSame(['sse_intel'], $ids);
        self::assertSame('Renseignement sur la carte', $noSse[0]['label']);
    }

    public function testRequestNoteExplainsMapAndRhCircuit(): void
    {
        $note = AtakMapAccessGapService::formatRequestNote([
            ['label' => 'Fiches des opérateurs'],
            ['label' => 'Renseignement sur la carte'],
        ]);
        self::assertStringContainsString('carte du poste', $note);
        self::assertStringContainsString('liaison en jeu', $note);
        self::assertStringContainsString('Fiches des opérateurs', $note);
        self::assertStringContainsString('bureau effectifs', $note);
        self::assertStringNotContainsString('personnel.profile', $note);
        self::assertStringNotContainsString('endpoint', $note);
    }

    public function testStaffBypassSlugsAreRecognized(): void
    {
        self::assertTrue(AtakMapAccessGapService::allowsAny(
            static fn (string $slug): bool => $slug === 'admin.organization',
            ['admin.access', 'admin.organization']
        ));
        self::assertFalse(AtakMapAccessGapService::allowsAny(
            static fn (string $slug): bool => $slug === 'forum.view',
            ['admin.access', 'admin.organization']
        ));
    }
}
