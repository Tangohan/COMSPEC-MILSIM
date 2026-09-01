<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakCombatJournalAssetTest extends TestCase
{
    public function testGameAggregatesShotsInsteadOfSendingEachRound(): void
    {
        $note = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_noteCombatEvent.sqf');
        $init = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initCombatJournal.sqf');
        $flush = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf');
        $cfg = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');

        self::assertStringContainsString('FiredMan', $init);
        self::assertStringContainsString('IncomingMissile', $init);
        self::assertStringContainsString('2.6', $note);
        self::assertStringContainsString('COMSPEC_CombatQueue', $note);
        self::assertStringContainsString('combatEventsJson', $flush);
        self::assertStringContainsString('_combatUrgent', $flush);
        self::assertStringContainsString('class initCombatJournal {};', $cfg);
        self::assertStringContainsString('class noteCombatEvent {};', $cfg);
        self::assertStringContainsString('1.5.0', $cfg);
    }

    public function testAnalysisJournalShowsCombatLinesAndMapHint(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-intel-timeline.js');
        $map = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map.js');
        $php = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Tactical/AtakCombatEventPresenter.php');

        self::assertStringContainsString('ouvre le feu', $php);
        self::assertStringContainsString('échange de feu', $php);
        self::assertStringContainsString('Tentative de missile', $php);
        self::assertStringContainsString('addTemporaryPingMarker', $js);
        self::assertStringContainsString('[Tir]', $js);
        self::assertStringContainsString("label: 'Tir'", $map);
        self::assertStringNotContainsString('Fired EH', $js);
        self::assertStringNotContainsString('endpoint', $js);
        self::assertStringContainsString('function clearView()', $js);
        self::assertStringContainsString('mutedBeforeId', $js);
    }
}
