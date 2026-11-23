<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AirBriefService;
use App\Services\Tactical\BriefReference;
use PHPUnit\Framework\TestCase;

/**
 * Nom libre et référence automatique d'un brief air (CAS-23-04) : format dit à la radio, lecture d'une référence tapée ou
 * dictée, nettoyage et refus du nom, attribution sans collision ni réutilisation (simulation de créations simultanées),
 * colonnes SQF ajoutées sans changer l'ancienne forme, étape 4980 et ses garde-fous, mod et traductions. Aucune base :
 * l'attribution réelle sur sqlite est dans AtakBriefNameReferenceDbTest.
 */
final class AtakBriefReferenceTest extends TestCase
{
    private static function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testReferenceIsReadableOnTheRadio(): void
    {
        $day = new \DateTimeImmutable('2026-11-23 14:05:00');
        self::assertSame('CAS-23-01', BriefReference::format('cas', $day, 1));
        self::assertSame('CAS-23-04', BriefReference::format('fiveline', $day, 4));
        self::assertSame('MED-23-12', BriefReference::format('medevac', $day, 12));
        self::assertSame('CAS-23-105', BriefReference::format('cas', $day, 105));
        self::assertSame('CAS-05-01', BriefReference::format('cas', new \DateTimeImmutable('2026-12-05'), 0));
    }

    public function testParseAcceptsWhatIsSaidOrTyped(): void
    {
        foreach (['CAS-23-04', 'cas 23 04', 'Cas-23-4', 'cas.23.4', 'CAS2304', ' cas  23  04 ', 'cas_23_04'] as $said) {
            self::assertSame('CAS-23-04', BriefReference::parse($said), $said);
        }
        self::assertSame('MED-07-12', BriefReference::parse('med 7 12'));
        foreach (['Pont nord', '42', 'CAS', 'CAS-23', 'CAS-23-', 'XYZ-23-04', '', 'CAS-2-3-4', "CAS-23-04\n--"] as $other) {
            self::assertNull(BriefReference::parse($other), json_encode($other));
        }
    }

    public function testNameIsCleanedAndLimitedToFortyCharacters(): void
    {
        self::assertSame('Pont nord', BriefReference::cleanName("  Pont \n  nord  "));
        self::assertDoesNotMatchRegularExpression('/[<>]/', BriefReference::cleanName('<script>alert(1)</script>'));
        self::assertStringNotContainsString('<', BriefReference::cleanName('<b>Pont</b>'));
        self::assertStringNotContainsString('|', BriefReference::cleanName('Pont|nord'));
        self::assertSame('', BriefReference::cleanName(null));
        self::assertSame('', BriefReference::cleanName(['x']));
        $long = BriefReference::cleanName(str_repeat('é', 80));
        self::assertSame(40, mb_strlen($long));
        self::assertLessThanOrEqual(40, mb_strlen(BriefReference::cleanName(str_repeat('a b ', 30))));
    }

    public function testNameCannotPassForAReferenceOrANumber(): void
    {
        self::assertNull(BriefReference::nameError(''));
        self::assertNull(BriefReference::nameError('Pont nord'));
        self::assertNull(BriefReference::nameError('Colline 4'));
        foreach (['42', 'A42', 'CAS-23-04', 'cas 23 4', 'med-01-02'] as $bad) {
            self::assertNotNull(BriefReference::nameError($bad), $bad);
        }
    }

    public function testAllocationTakesTheNextNumberOfTheDay(): void
    {
        $day = new \DateTimeImmutable('2026-11-23');
        $taken = ['CAS-23-01' => true, 'CAS-23-02' => true];
        $ref = BriefReference::allocate('cas', $day, static fn (): int => 2, static function (string $r, int $n) use (&$taken): bool {
            if (isset($taken[$r])) {
                return false;
            }
            $taken[$r] = true;

            return true;
        });
        self::assertSame('CAS-23-03', $ref);
    }

    public function testTwoSimultaneousCreationsNeverGetTheSameReference(): void
    {
        $day = new \DateTimeImmutable('2026-11-23');
        $taken = ['CAS-23-01' => true];
        $reserve = static function (string $r, int $n) use (&$taken): bool {
            if (isset($taken[$r])) {
                return false;
            }
            $taken[$r] = true;

            return true;
        };
        // Les deux lisent « dernier numéro = 1 » avant que l'un n'écrive : le registre départage.
        $a = BriefReference::allocate('cas', $day, static fn (): int => 1, $reserve);
        $b = BriefReference::allocate('cas', $day, static fn (): int => 1, $reserve);
        self::assertSame('CAS-23-02', $a);
        self::assertSame('CAS-23-03', $b);
        self::assertNotSame($a, $b);
    }

    public function testAReferenceIsNeverReusedEvenAfterTheNextMonthOrADeletedBrief(): void
    {
        // Registre jamais purgé : CAS-23-01 et 02 de novembre existent encore, le 23 décembre repart après eux.
        $taken = ['CAS-23-01' => true, 'CAS-23-02' => true];
        $ref = BriefReference::allocate('cas', new \DateTimeImmutable('2026-12-23'), static fn (): int => 0, static function (string $r) use (&$taken): bool {
            if (isset($taken[$r])) {
                return false;
            }
            $taken[$r] = true;

            return true;
        });
        self::assertSame('CAS-23-03', $ref);
    }

    public function testAllocationGivesUpInsteadOfLoopingForever(): void
    {
        $calls = 0;
        $ref = BriefReference::allocate('cas', new \DateTimeImmutable('2026-11-23'), static fn (): int => 0, static function () use (&$calls): bool {
            $calls++;

            return false;
        });
        self::assertNull($ref);
        self::assertLessThanOrEqual(100, $calls);
        self::assertGreaterThan(1, $calls);
    }

    public function testClosedStatusesFreezeTheName(): void
    {
        foreach (['COMPLETE', 'aborted', 'CANCELLED'] as $st) {
            self::assertTrue(BriefReference::isClosed(['status' => $st]), $st);
        }
        foreach (['SUBMITTED', 'INBOUND', '', 'active'] as $st) {
            self::assertFalse(BriefReference::isClosed(['status' => $st]), $st);
        }
    }

    public function testLookupKeepsReferencesNumbersAndNamesApart(): void
    {
        self::assertSame('CAS-23-04', AirBriefService::normalizeLookup('CAS-23-04'));
        self::assertSame('42', AirBriefService::normalizeLookup('42'));
        self::assertSame('Pont nord', AirBriefService::normalizeLookup('Pont%20nord'));
        self::assertSame('Pont nord', AirBriefService::normalizeLookup('Pont nord'));
        self::assertNull(AirBriefService::normalizeLookup(''));
        self::assertNull(AirBriefService::normalizeLookup('<script>'));
        self::assertNull(AirBriefService::normalizeLookup('a|b'));
        // L'ancienne règle des identifiants ne change pas.
        self::assertNull(AirBriefService::normalizeRef('a b'));
    }

    public function testSqfKeepsTheOldShapeWithoutNameOrReferenceAndAppendsThemOtherwise(): void
    {
        $item = ['ref' => 'R', 'id' => 1, 'format' => '9line', 'from' => 'J', 'time' => '12:00', 'lines' => ['a'], 'remarks' => '', 'status' => 'sent', 'recipient' => 'HOG 21'];
        self::assertSame('[["R",1,"9line","J","12:00",["a"],"","sent","HOG 21"]]', AirBriefService::inboxSqf([$item]));
        $withBoth = AirBriefService::inboxSqf([$item + ['brief_name' => 'Pont "nord"', 'brief_ref' => 'CAS-23-04']]);
        self::assertSame('[["R",1,"9line","J","12:00",["a"],"","sent","HOG 21","","Pont ""nord""","CAS-23-04"]]', $withBoth);
        $authored = AirBriefService::inboxSqf([$item + ['source' => 'web', 'brief_ref' => 'CAS-23-04']]);
        self::assertSame('[["R",1,"9line","J","12:00",["a"],"","sent","HOG 21","web","","CAS-23-04"]]', $authored);
        self::assertSame('[["R",1,"9line","J","12:00",["a"],"","sent","HOG 21","web"]]', AirBriefService::inboxSqf([$item + ['source' => 'web']]));
    }

    public function testMigrationStep4980IsRegisteredOnceWithItsRollback(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = require $root . '/database/steps/registry.php';
        $numbers = array_map(static fn (array $s): string => substr((string) $s['file'], 0, 4), $registry);
        self::assertSame(count($numbers), count(array_unique($numbers)), 'aucun numéro d’étape ne se répète');
        $hits = array_values(array_filter($registry, static fn (array $s): bool => $s['file'] === '4980_atak_brief_name_reference.php'));
        self::assertCount(1, $hits);
        self::assertTrue($hits[0]['rerun']);
        self::assertSame('schema', $hits[0]['group']);
        self::assertFileExists($root . '/database/steps/4980_atak_brief_name_reference.php');
        $down = self::read('migrations/rollback/atak_brief_name_reference_down.sql');
        foreach (['brief_name', 'brief_ref', 'atak_brief_refs'] as $needle) {
            self::assertStringContainsString($needle, $down, $needle);
        }
        $step = self::read('database/steps/4980_atak_brief_name_reference.php');
        foreach (['addColumnIfMissing', 'UNIQUE', 'uq_brief_refs_ref', 'uq_brief_refs_day_seq'] as $needle) {
            self::assertStringContainsString($needle, $step, $needle);
        }
    }

    public function testRoutesApiAndPageAreWired(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("\$router->post('/9-line/{id}/nom', [\\App\\Controllers\\Web\\AtakNineLineViewController::class, 'rename']", $routes);
        $api = self::read('app/Controllers/Api/AtakApiController.php');
        foreach (['findByBriefLabel', "query('q')", 'AirBriefService::normalizeLookup(', 'renameCas', 'author_only'] as $needle) {
            self::assertStringContainsString($needle, $api, $needle);
        }
        $view = self::read('views/atak/nine_line_view.php');
        self::assertStringContainsString('name="name"', $view);
        $js = self::read('public/assets/js/atak-nine-line.js');
        foreach (['brief_ref', 'brief_name', "'/9-line/' + it.id + '/nom'", 'can_rename'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
    }

    public function testAckFormatOfTheGameIsUnchangedAndShowsTheReferenceOnTheScreenOnly(): void
    {
        $tactical = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical/';
        $air = self::read($tactical . 'fn_airBrief.sqf');
        // « WILCO:HOG 21 » : format de SendCASState inchangé, la référence ne s'ajoute qu'à l'affichage.
        self::assertStringContainsString('format ["%1:%2", _status, _to]', $air);
        self::assertStringContainsString('format ["%1:%2", toUpper _st, _to]', $air);
        foreach (['_aref', '"aref"', '"name":"%1"', '_srcCol'] as $needle) {
            self::assertStringContainsString($needle, $air, $needle);
        }
        $page = self::read($tactical . 'fn_pageJtac.sqf');
        self::assertStringContainsString('RÉFÉRENCE · NOM', $page);
        self::assertStringContainsString('"jName"', $page);
        self::assertStringContainsString('jName', self::read($tactical . 'fn_jtacAction.sqf'));
    }

    public function testModTranslationsAreInsertedInTheMiddleOfBothFiles(): void
    {
        $en = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        foreach (['RÉFÉRENCE · NOM', 'Nom du brief (facultatif)'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, $fr);
            self::assertStringContainsString('["' . $fr . '", "", "', $xx, $fr);
        }
        self::assertStringEndsWith(']', rtrim($en));
        self::assertStringNotContainsString('Nom du brief', substr(rtrim($en), -200));
        self::assertStringNotContainsString('Nom du brief', substr(rtrim($xx), -200));
    }

    public function testSiteTranslationsAreAppended(): void
    {
        $site = self::read('lang/en/site_auto.php');
        foreach (['Nom du brief (facultatif)' => 'Brief name (optional)', 'Référence %1.' => 'Reference %1.', 'Nom retiré.' => 'Name removed.'] as $fr => $en) {
            self::assertStringContainsString("'" . $fr . "' => '" . $en . "',", $site, $fr);
        }
    }
}
