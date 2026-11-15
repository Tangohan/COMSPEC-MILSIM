<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseAlertLevel;
use App\Support\SseWantedRealNames;
use PHPUnit\Framework\TestCase;

/**
 * Échelle d'alerte des fiches de vigilance et couleurs de notice : rang, couleur, forme et libellés FR/EN, jamais la couleur seule,
 * consignes dominantes, lignes « à ne pas faire », échéance périssable et couleur de notice d'un avis.
 */
final class SseAlertLevelTest extends TestCase
{
    /** @param array{0: int, 1: int, 2: int} $c */
    private static function luminance(array $c): float
    {
        $lin = static function (int $v): float {
            $s = $v / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $lin($c[0]) + 0.7152 * $lin($c[1]) + 0.0722 * $lin($c[2]);
    }

    /** @param array{0: int, 1: int, 2: int} $a @param array{0: int, 1: int, 2: int} $b */
    private static function contrast(array $a, array $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public function testFourLevelsAreRankedOneToFourWithDistinctTitlesInBothLanguages(): void
    {
        self::assertSame(['watch', 'reinforced', 'caution', 'danger'], array_keys(SseAlertLevel::LEVELS));
        $ranks = [];
        $titles = [];
        foreach (SseAlertLevel::LEVELS as $key => $level) {
            $ranks[] = $level['rank'];
            foreach ([0, 1] as $i) {
                self::assertNotSame('', trim($level['title'][$i]), $key . ' titre ' . $i);
                self::assertNotSame('', trim($level['hint'][$i]), $key . ' explication ' . $i);
                $titles[] = $level['title'][$i];
            }
            self::assertNotSame($level['title'][0], $level['title'][1], $key . ' : titres FR et EN distincts');
        }
        self::assertSame([1, 2, 3, 4], $ranks);
        self::assertCount(8, array_unique($titles), 'huit titres tous différents');
    }

    public function testTheLevelNeverDependsOnColourAlone(): void
    {
        // Rang (1 à 4), titre écrit et forme : le niveau se lit sans la couleur ; le danger armé a sa propre forme.
        $shapes = [];
        $colours = [];
        foreach (SseAlertLevel::LEVELS as $key => $level) {
            self::assertNotSame('', $level['shape'], $key);
            $shapes[$key] = $level['shape'];
            $colours[] = implode(',', $level['rgb']);
        }
        self::assertCount(4, array_unique($colours), 'quatre couleurs différentes');
        self::assertNotSame($shapes['danger'], $shapes['caution']);
        self::assertNotSame($shapes['danger'], $shapes['watch']);
        self::assertNotSame($shapes['caution'], $shapes['watch']);
        self::assertGreaterThanOrEqual(3, count(array_unique($shapes)));
    }

    public function testInkIsReadableOnEachLevelAndNoticeColour(): void
    {
        foreach (SseAlertLevel::LEVELS as $key => $level) {
            self::assertGreaterThanOrEqual(3.0, self::contrast($level['rgb'], $level['ink']), 'contraste du niveau ' . $key);
        }
        foreach (SseAlertLevel::NOTICE_COLORS as $key => $c) {
            self::assertGreaterThanOrEqual(3.0, self::contrast($c['rgb'], $c['ink']), 'contraste de la notice ' . $key);
        }
    }

    public function testKeyIsRaisedByArmamentAndFollowsTheThreat(): void
    {
        self::assertSame('watch', SseAlertLevel::key('low', 'no'));
        self::assertSame('watch', SseAlertLevel::key(null, null));
        self::assertSame('watch', SseAlertLevel::key('inconnue', 'unknown'));
        self::assertSame('reinforced', SseAlertLevel::key('medium', 'unknown'));
        self::assertSame('caution', SseAlertLevel::key('high', 'no'));
        self::assertSame('caution', SseAlertLevel::key('critical', 'unknown'));
        foreach (['low', 'medium', 'high', 'critical', null] as $threat) {
            self::assertSame('danger', SseAlertLevel::key($threat, 'yes'), 'armé : danger quelle que soit la menace');
        }
        $of = SseAlertLevel::of('critical', 'yes');
        self::assertSame('danger', $of['key']);
        self::assertSame(4, $of['rank']);
        self::assertSame(SseAlertLevel::LEVELS['danger']['title'], $of['title']);
        self::assertSame(1, SseAlertLevel::of('low', 'no')['rank']);
        self::assertSame(2, SseAlertLevel::of('medium', 'no')['rank']);
        self::assertSame(3, SseAlertLevel::of('high', 'no')['rank']);
    }

    public function testHexIsAValidColourAndFallsBackOnUnknownKeys(): void
    {
        foreach (array_keys(SseAlertLevel::LEVELS) as $key) {
            self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', SseAlertLevel::hex($key));
        }
        self::assertSame(SseAlertLevel::hex('watch'), SseAlertLevel::hex('nimporte'));
        foreach (array_keys(SseAlertLevel::NOTICE_COLORS) as $key) {
            self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', SseAlertLevel::noticeHex($key));
        }
        self::assertSame(SseAlertLevel::noticeHex('red'), SseAlertLevel::noticeHex('nimporte'));
    }

    public function testDominantOrderComesFromTheCodedMeasures(): void
    {
        self::assertSame('hold', SseAlertLevel::order(['A-NOI', 'A-REP']));
        self::assertSame('hold', SseAlertLevel::order(['a-obs']));
        self::assertSame('order', SseAlertLevel::order(['A-NOI', 'A-ARR']), 'une mesure d’intervention retire « ne pas appréhender »');
        self::assertSame('order', SseAlertLevel::order(['A-LIV']));
        self::assertSame('', SseAlertLevel::order(['A-REP', 'A-QRF']));
        self::assertSame('', SseAlertLevel::order([]));
        foreach (SseAlertLevel::ORDERS as $titles) {
            self::assertNotSame($titles[0], $titles[1]);
        }
    }

    public function testDontsAreBoundedTranslatedAndDeduplicated(): void
    {
        $fr = SseAlertLevel::donts(['A-NOI', 'A-OBS', 'A-ARR', 'A-NEU', 'A-IED'], 'fr');
        self::assertCount(3, $fr, '3 lignes au plus');
        self::assertSame('Ne pas intervenir seul.', $fr[0]);
        $en = SseAlertLevel::donts(['A-NOI', 'A-NOI', 'a-civ'], 'en');
        self::assertSame(['Do not engage alone.', 'Do not act in populated areas.'], $en);
        self::assertSame([], SseAlertLevel::donts(['A-REP', 'A-QRF'], 'fr'));
        foreach (SseAlertLevel::DONTS as $code => $texts) {
            self::assertMatchesRegularExpression('/^A-[A-Z]{3}$/', $code);
            self::assertNotSame($texts[0], $texts[1]);
        }
    }

    public function testDefaultMeasuresAreCodedAndFollowTheLevel(): void
    {
        self::assertSame(array_keys(SseAlertLevel::LEVELS), array_keys(SseAlertLevel::DEFAULT_MEASURES));
        foreach (SseAlertLevel::DEFAULT_MEASURES as $level => $codes) {
            self::assertGreaterThanOrEqual(3, count($codes), $level);
            self::assertLessThanOrEqual(6, count($codes), $level . ' : 3 à 6 consignes');
            foreach ($codes as $code) {
                self::assertMatchesRegularExpression('/^A-[A-Z]{3}$/', $code);
            }
        }
        self::assertContains('A-DNG', SseAlertLevel::DEFAULT_MEASURES['danger']);
    }

    public function testReportDelaysCertaintyAndStatusesAreTranslated(): void
    {
        foreach ([SseAlertLevel::DELAYS, SseAlertLevel::CERTAINTY, SseAlertLevel::REPORT_STATUSES] as $table) {
            self::assertNotSame([], $table);
            foreach ($table as $key => $labels) {
                self::assertNotSame('', $labels[0], (string) $key);
                self::assertNotSame('', $labels[1], (string) $key);
            }
        }
        self::assertArrayHasKey('now', SseAlertLevel::DELAYS);
    }

    public function testPerishableMeansEndingWithinSeventyTwoHoursButNotAlreadyEnded(): void
    {
        $now = (int) strtotime('2026-11-15 12:00:00 UTC');
        self::assertTrue(SseAlertLevel::perishable('2026-11-17', $now), 'fin dans deux jours');
        self::assertTrue(SseAlertLevel::perishable('2026-11-18 11:00:00', $now), 'fin dans moins de 72 h');
        self::assertFalse(SseAlertLevel::perishable('2026-11-19 12:00:01', $now), 'au-delà de 72 h');
        self::assertFalse(SseAlertLevel::perishable('2026-11-14', $now), 'déjà échue');
        self::assertFalse(SseAlertLevel::perishable(null, $now));
        self::assertFalse(SseAlertLevel::perishable('  ', $now));
        self::assertFalse(SseAlertLevel::perishable('pas une date', $now));
    }

    public function testNoticeColoursAreNamedInFullInBothLanguagesAndNameNoRealOrganisation(): void
    {
        foreach (['red', 'blue', 'green', 'yellow', 'orange', 'black'] as $key) {
            self::assertArrayHasKey($key, SseAlertLevel::NOTICE_COLORS);
        }
        $titles = [];
        foreach (SseAlertLevel::NOTICE_COLORS as $key => $c) {
            self::assertStringContainsString('NOTICE', $c['title'][0] . ' ' . $c['title'][1], $key);
            self::assertSame(mb_strtoupper($c['title'][0], 'UTF-8'), $c['title'][0]);
            self::assertSame(mb_strtoupper($c['title'][1], 'UTF-8'), $c['title'][1]);
            self::assertNotSame('', $c['label'][0]);
            self::assertNull(SseWantedRealNames::find($c['title'][0] . ' ' . $c['title'][1] . ' ' . $c['label'][0] . ' ' . $c['label'][1]), $key);
            $titles[] = $c['title'][0];
        }
        self::assertSame(['NOTICE ROUGE', 'RED NOTICE'], SseAlertLevel::NOTICE_COLORS['red']['title']);
        self::assertCount(6, array_unique($titles));
    }

    public function testNoticeColourOfATypeDependsOnTheBannerSetting(): void
    {
        $red = SseAlertLevel::noticeColor('hvt');
        self::assertNotNull($red);
        self::assertSame('red', $red['key']);
        self::assertSame(['NOTICE ROUGE', 'RED NOTICE'], $red['title']);
        self::assertSame(['CIBLE DE HAUTE VALEUR', 'HIGH-VALUE TARGET'], $red['subtitle']);
        $blue = SseAlertLevel::noticeColor('poi');
        self::assertNotNull($blue);
        self::assertSame('blue', $blue['key']);
        $green = SseAlertLevel::noticeColor('surveillance');
        self::assertNotNull($green);
        self::assertSame('green', $green['key']);
        foreach (array_keys(SseAlertLevel::TYPE_COLORS) as $type) {
            $c = SseAlertLevel::noticeColor($type, ['banner' => 'red']);
            self::assertNotNull($c, $type);
            self::assertArrayHasKey($c['key'], SseAlertLevel::NOTICE_COLORS);
            self::assertNotSame('', $c['subtitle'][0]);
            self::assertNotSame('', $c['subtitle'][1]);
        }
        // Un type propre à la communauté prend la notice rouge.
        $custom = SseAlertLevel::noticeColor('type_maison');
        self::assertNotNull($custom);
        self::assertSame('red', $custom['key']);
        // Sans bannière « notice » (avis classique) : aucune couleur, l'avis garde son gabarit.
        self::assertNull(SseAlertLevel::noticeColor('hvt', ['banner' => 'wanted']));
        self::assertNull(SseAlertLevel::noticeColor('poi', ['banner' => 'autre']));
    }
}
