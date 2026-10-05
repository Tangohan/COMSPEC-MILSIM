<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelRedaction;
use PHPUnit\Framework\TestCase;

final class IntelIntakeTest extends TestCase
{
    private const RED = ['field' => 'body', 'phrase' => 'chez Karimi', 'clear_level' => 'confidentiel', 'allowed_user_ids' => '[42]'];

    public function testRedactedPhraseIsBarredBelowItsLevel(): void
    {
        $text = 'Le chef loge chez Karimi, rue 4. Vu CHEZ KARIMI hier.';

        $low = IntelRedaction::apply($text, [self::RED], 'encadrement', 7);
        self::assertStringNotContainsString('karimi', mb_strtolower($low));
        self::assertStringContainsString('Le chef loge ', $low);
        self::assertStringContainsString('█', $low);

        self::assertSame($text, IntelRedaction::apply($text, [self::RED], 'confidentiel', 7), 'niveau atteint');
        self::assertSame($text, IntelRedaction::apply($text, [self::RED], 'interne', 42), 'membre nommé');
    }

    public function testSplitPrefersLongestPhraseAndKeepsOrder(): void
    {
        $parts = IntelRedaction::split('A B C D', [
            ['phrase' => 'B'],
            ['phrase' => 'B C'],
        ]);

        self::assertSame(['A ', 'B C', ' D'], array_column($parts, 0));
        self::assertNull($parts[0][1]);
        self::assertSame('B C', $parts[1][1]['phrase']);
    }

    public function testApplyRowOnlyTouchesKnownFields(): void
    {
        $row = IntelRedaction::applyRow('cr', ['summary' => 'cache chez Karimi', 'details' => 'rien', 'report_type' => 'chez Karimi'], [
            ['field' => 'summary', 'phrase' => 'chez Karimi', 'clear_level' => 'tres_restreint', 'allowed_user_ids' => '[]'],
        ], 'interne', 0);

        self::assertStringNotContainsString('Karimi', $row['summary']);
        self::assertSame('chez Karimi', $row['report_type']);
    }

    public function testStatesFollowSourceStatus(): void
    {
        self::assertSame('exploitee', IntelIntakeService::defaultState('fiche', 'exploitee'));
        self::assertSame('non_exploitable', IntelIntakeService::defaultState('fiche', 'sans_suite'));
        self::assertSame('en_cours', IntelIntakeService::defaultState('cr', 'ACKNOWLEDGED'));
        self::assertSame('close', IntelIntakeService::defaultState('cr', 'ARCHIVED'));
        self::assertSame('a_traiter', IntelIntakeService::defaultState('cr', 'SUBMITTED'));
        self::assertSame('ACTIONED', IntelIntakeService::sourceStatusFor('cr', 'exploitee'));
        self::assertSame('prise_en_compte', IntelIntakeService::sourceStatusFor('fiche', 'en_cours'));
    }

    public function testDataChangesKeepOnlyRealValidEdits(): void
    {
        $changes = IntelIntakeService::dataChanges('fiche', ['title' => 'Ancien', 'body' => 'Texte', 'urgency' => 'routine'], [
            'title' => '  Nouveau titre ',
            'body' => '',
            'urgency' => 'n/importe',
            'reference_code' => 'HACK',
        ]);

        self::assertSame(['title' => 'Nouveau titre'], $changes, 'texte vide refusé, urgence inconnue ramenée à routine (inchangée), champ hors liste ignoré');

        $cr = IntelIntakeService::dataChanges('cr', ['priority' => 'ROUTINE'], ['priority' => 'flash']);
        self::assertSame(['priority' => 'FLASH'], $cr);
        self::assertSame([], IntelIntakeService::dataChanges('cr', ['priority' => 'ROUTINE'], ['priority' => 'TOUT DE SUITE']));
    }
}
