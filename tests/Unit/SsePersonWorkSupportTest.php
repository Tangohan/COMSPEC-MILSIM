<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SsePersonUi;
use App\Support\SsePersonWork as W;
use PHPUnit\Framework\TestCase;

/**
 * Règles pures de la page Personnes : rang et plafond de classification, verrouillage en cours (manuel, date de fin, règle
 * automatique), pastilles biométriques, paramètres d'adresse et dates courtes.
 */
final class SsePersonWorkSupportTest extends TestCase
{
    public function testClassificationRanksFollowTheStoredScaleWithoutRenamingAnyCode(): void
    {
        self::assertSame(-1, W::rank(null));
        self::assertSame(-1, W::rank(''));
        self::assertSame(-1, W::rank('inconnu'));
        self::assertSame(0, W::rank('interne'));
        self::assertSame(1, W::rank('encadrement'));
        self::assertSame(2, W::rank('confidentiel'));
        self::assertSame(3, W::rank(' Tres_Restreint '));
        self::assertSame(['interne', 'encadrement', 'confidentiel', 'tres_restreint'], \App\Support\SseUsClassification::codes());
        self::assertSame('CUI', W::classificationBadge('interne'));
        self::assertSame('TS', W::classificationBadge('tres_restreint'));
        self::assertSame('—', W::classificationBadge(null));
        self::assertSame('Non classifiée', W::classificationLabel(null));
        self::assertSame('Not classified', W::classificationLabel(null, 'en'));
        self::assertNull(W::cleanClassification('SECRET'));
        self::assertSame('confidentiel', W::cleanClassification('CONFIDENTIEL'));
    }

    public function testAssignableLevelsNeverExceedTheEditorsClearance(): void
    {
        self::assertSame(['interne'], W::assignableLevels('interne'));
        self::assertSame(['interne', 'encadrement', 'confidentiel'], W::assignableLevels('confidentiel'));
        self::assertCount(4, W::assignableLevels('tres_restreint'));
        self::assertSame([], W::assignableLevels('n’importe quoi'), 'Une habilitation inconnue ne permet de classer à aucun niveau');
    }

    public function testLockStateManualWithEndDateAndAutomaticRule(): void
    {
        $now = 1_795_000_000;
        $base = ['classification' => 'confidentiel'];
        self::assertFalse(W::lockState($base, null, $now)['locked']);

        $manual = W::lockState($base + ['locked_at' => '2026-11-01 00:00:00', 'locked_by' => 4, 'lock_reason' => 'Raison'], null, $now);
        self::assertTrue($manual['locked']);
        self::assertTrue($manual['manual']);
        self::assertSame(2, $manual['required']);
        self::assertSame(4, $manual['by']);

        $future = gmdate('Y-m-d H:i:s', $now + 60);
        $past = gmdate('Y-m-d H:i:s', $now - 60);
        self::assertTrue(W::lockState($base + ['locked_at' => '2026-11-01', 'lock_until' => $future], null, $now)['locked']);
        self::assertFalse(W::lockState($base + ['locked_at' => '2026-11-01', 'lock_until' => $past], null, $now)['locked'], 'Verrou échu');

        // « Au-delà du niveau X » : strictement au-dessus.
        self::assertTrue(W::lockState($base, W::rank('encadrement'), $now)['auto']);
        self::assertFalse(W::lockState($base, W::rank('confidentiel'), $now)['locked']);
        self::assertFalse(W::lockState(['classification' => null], 0, $now)['locked']);
        self::assertNull(W::autoThreshold(''));
        self::assertSame(1, W::autoThreshold('encadrement'));
    }

    public function testGameNeverReceivesClosedOrLockedFiles(): void
    {
        self::assertTrue(W::hiddenFromGame(['closed_at' => '2026-11-01 00:00:00'], null));
        self::assertTrue(W::hiddenFromGame(['classification' => 'interne', 'locked_at' => '2026-11-01'], null));
        self::assertTrue(W::hiddenFromGame(['classification' => 'tres_restreint'], 1));
        self::assertFalse(W::hiddenFromGame(['classification' => 'encadrement'], 1));
        self::assertFalse(W::hiddenFromGame([], null));
    }

    public function testBiometricPillsAreThreeStatesAndCompleteMeansAllPresent(): void
    {
        $states = W::bioStates([['kind' => 'empreintes', 'quality' => 90], ['kind' => 'iris', 'quality' => 59], ['kind' => 'iris', 'quality' => 61]]);
        self::assertSame(['empreintes' => 'present', 'iris' => 'present', 'adn' => 'absent'], $states, 'Le meilleur relevé d’une modalité compte');
        self::assertFalse(W::bioComplete($states));
        self::assertSame('partial', W::bioStates([['kind' => 'adn', 'quality' => 30]])['adn']);
        self::assertSame('present', W::bioStates([['kind' => 'adn', 'quality' => null]])['adn'], 'Sans qualité transmise : présent');
        self::assertTrue(W::bioComplete(W::bioStates([['kind' => 'empreintes'], ['kind' => 'iris'], ['kind' => 'adn']])));
        self::assertSame(['empreintes' => 'absent', 'iris' => 'absent', 'adn' => 'absent'], W::bioStates([]));
    }

    public function testPriorityOrdersNoticeThenStatusThenUnsigned(): void
    {
        $civil = ['status' => 'civil'];
        self::assertGreaterThan(W::priority($civil, 'low', true), W::priority($civil, 'critical', true));
        self::assertGreaterThan(W::priority(['status' => 'prioritaire'], null, true), W::priority($civil, 'low', true));
        self::assertGreaterThan(W::priority($civil, null, true), W::priority(['status' => 'detenu'], null, true));
        self::assertGreaterThan(W::priority($civil, null, true), W::priority($civil, null, false), 'À poids égal, une fiche non signée passe devant');
    }

    public function testQueryIsCleanedAndRebuiltWithoutDefaults(): void
    {
        $q = W::cleanQuery(['q' => '  Karim  ', 'tab' => 'closes', 'status' => 'detenu', 'cls' => 'none', 'wanted' => '1', 'signed' => 'x', 'bio' => 'full', 'ctx' => '3', 'period' => '7j', 'sort' => 'nom', 'p' => '2', 'vue' => 'cartes', 'open' => '12']);
        self::assertSame('Karim', $q['q']);
        self::assertSame('closes', $q['tab']);
        self::assertSame('none', $q['cls']);
        self::assertSame('', $q['signed']);
        self::assertSame(3, $q['ctx']);
        self::assertSame(2, $q['p']);
        $rebuilt = W::queryString($q);
        parse_str($rebuilt, $back);
        self::assertSame(W::cleanQuery($back), $q, 'L’adresse partagée redonne les mêmes critères');
        self::assertSame('', W::queryString(W::cleanQuery([])), 'Valeurs par défaut omises');
        self::assertSame('tab=closes', W::queryString(W::cleanQuery(['tab' => 'closes', 'p' => '1'])));
        $bad = W::cleanQuery(['tab' => ['x'], 'sort' => 'DROP', 'p' => 'abc', 'q' => str_repeat('é', 300)]);
        self::assertSame('toutes', $bad['tab']);
        self::assertSame('ouverture', $bad['sort']);
        self::assertSame(1, $bad['p']);
        self::assertSame(120, mb_strlen($bad['q']));
    }

    public function testCloseReasonsAreTheSixRequestedOnes(): void
    {
        self::assertSame(['resolu', 'neutralise', 'detenu_transfere', 'decede', 'doublon_fusionne', 'erreur'], array_keys(W::CLOSE_REASONS));
        self::assertSame('Doublon fusionné', W::closeReasonLabel('doublon_fusionne'));
        self::assertSame('Deceased', W::closeReasonLabel('decede', 'en'));
        self::assertSame('', W::closeReasonLabel('x'));
    }

    public function testShortDates(): void
    {
        $now = strtotime('2026-11-15 15:00:00 UTC');
        self::assertSame('—', SsePersonUi::when(null, $now));
        self::assertSame('—', SsePersonUi::when('0000-00-00 00:00:00', $now));
        self::assertStringStartsWith('Auj. ', SsePersonUi::when('2026-11-15 12:00:00', $now));
        self::assertStringStartsWith('Hier ', SsePersonUi::when('2026-11-14 12:00:00', $now));
        self::assertStringContainsString('nov.', SsePersonUi::when('2026-11-03 12:00:00', $now));
        self::assertStringContainsString('2025', SsePersonUi::when('2025-03-03 12:00:00', $now));
        self::assertSame('Auj.', SsePersonUi::day('2026-11-15 12:00:00', $now));
        self::assertSame('Hier', SsePersonUi::day('2026-11-14 12:00:00', $now));
        self::assertSame('3 nov.', SsePersonUi::day('2026-11-03 12:00:00', $now));
        self::assertSame('', SsePersonUi::full(''));
        self::assertSame('Critique', SsePersonUi::threat('critical'));
        self::assertSame('bad', SsePersonUi::threatTone('critical'));
        self::assertSame('info', SsePersonUi::threatTone('low'));
        self::assertSame('bad', SsePersonUi::classTone('tres_restreint'));
        self::assertSame('none', SsePersonUi::classTone(null));
    }
}
