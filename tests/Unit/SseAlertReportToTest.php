<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseAlertRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportTo;
use App\Support\SseWantedRealNames;
use App\Support\SseWantedServices;
use PHPUnit\Framework\TestCase;

/**
 * Service à informer d'un avis ou d'une fiche de vigilance : instantané du catalogue ou saisie libre, refus des noms réels,
 * coordonnées fictives (domaine « .invalid »), délai, relecture bornée et adresse de signalement à jeton opaque.
 */
final class SseAlertReportToTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function config(array $extra = []): array
    {
        return $extra + SseWantedSettingsRepository::normalize([]);
    }

    public function testACatalogueServiceBecomesASnapshotWithItsNameAcronymChannelAndFictionalContact(): void
    {
        $code = (string) array_key_first(SseWantedServices::catalogue());
        $res = SseAlertReportTo::resolve(self::config(), ['service' => strtolower($code)]);
        self::assertSame([], $res['errors']);
        $r = $res['report'];
        self::assertSame($code, $r['code']);
        self::assertNotSame('', $r['fr']);
        self::assertNotSame('', $r['en']);
        self::assertNotSame('', $r['ac']);
        self::assertNotSame('', $r['channel']);
        self::assertMatchesRegularExpression('/\.invalid\b|555/', (string) $r['channel'], 'coordonnées réservées à la fiction');
        self::assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', (string) $r['color']);
        self::assertSame(SseAlertReportTo::DEFAULT_DELAY, $r['delay']);
        self::assertNull(SseWantedRealNames::find((string) $r['fr'] . ' ' . (string) $r['en'] . ' ' . (string) $r['channel']));
        self::assertStringContainsString((string) $r['ac'], SseAlertReportTo::label($r, 'fr'));
        self::assertStringContainsString((string) $r['en'], SseAlertReportTo::label($r, 'en'));
    }

    public function testEveryCatalogueServiceResolvesWithoutErrorAndCarriesAFictionalChannel(): void
    {
        foreach (array_keys(SseWantedServices::catalogue()) as $code) {
            $res = SseAlertReportTo::resolve(self::config(), ['service' => (string) $code]);
            self::assertSame([], $res['errors'], (string) $code);
            self::assertNotSame('', (string) $res['report']['channel'], (string) $code);
            self::assertNull(SseWantedRealNames::find((string) $res['report']['fr'] . ' ' . (string) $res['report']['channel']), (string) $code);
        }
    }

    public function testWithoutAnyChoiceTheCommunityContactIsUsedThenTheReservedFallbackChannel(): void
    {
        $res = SseAlertReportTo::resolve(self::config(['contact_label' => 'Cellule des recherches', 'contact_detail' => 'Fréquence 40.500']), []);
        self::assertSame([], $res['errors']);
        self::assertSame('Fréquence 40.500', $res['report']['channel']);
        // Aucun canal nulle part : le canal livré ne désigne rien de réel.
        $none = SseAlertReportTo::resolve(self::config(['contact_detail' => '', 'report_channel' => '']), ['other' => 'Cellule maison']);
        self::assertSame([], $none['errors']);
        self::assertSame(SseAlertReportTo::FALLBACK_CHANNEL, $none['report']['channel']);
        self::assertStringContainsString('.invalid', SseAlertReportTo::FALLBACK_CHANNEL);
        self::assertStringContainsString('555-', SseAlertReportTo::FALLBACK_CHANNEL);
        self::assertSame('Cellule maison', $none['report']['fr']);
        self::assertSame('Cellule maison', $none['report']['en']);
    }

    public function testFreeTextOtherIsAcceptedWhenFictionalAndRefusedWhenItNamesARealOrganisation(): void
    {
        $ok = SseAlertReportTo::resolve(self::config(), ['service' => 'OTHER', 'other' => 'Cellule interarmées du secteur nord', 'channel' => 'Canal SABRE 40.500']);
        self::assertSame([], $ok['errors']);
        self::assertSame('Cellule interarmées du secteur nord', $ok['report']['fr']);
        self::assertSame('Canal SABRE 40.500', $ok['report']['channel']);
        foreach (['Interpol', 'FBI', 'Europol', 'Gendarmerie nationale', 'Scotland Yard'] as $real) {
            $bad = SseAlertReportTo::resolve(self::config(), ['service' => 'OTHER', 'other' => $real]);
            self::assertNotSame([], $bad['errors'], $real . ' refusé comme nom de service');
            // Le canal est lui aussi soumis à la liste de refus.
            $channel = SseAlertReportTo::resolve(self::config(), ['service' => 'OTHER', 'other' => 'Cellule maison', 'channel' => 'Ligne ' . $real]);
            self::assertNotSame([], $channel['errors'], $real . ' refusé dans le canal');
        }
    }

    public function testUnknownServiceCodeIsRefused(): void
    {
        $res = SseAlertReportTo::resolve(self::config(), ['service' => 'SVC-INEXISTANT']);
        self::assertNotSame([], $res['errors']);
    }

    public function testDelayFallsBackOnTheCommunitySettingThenOnImmediately(): void
    {
        self::assertSame('1h', SseAlertReportTo::resolve(self::config(), ['delay' => '1h'])['report']['delay']);
        self::assertSame('now', SseAlertReportTo::resolve(self::config(), ['delay' => 'dans un siècle'])['report']['delay']);
        self::assertSame('4h', SseAlertReportTo::resolve(self::config(['report_delay' => '4h']), ['delay' => 'inconnu'])['report']['delay']);
        self::assertSame('Immédiatement', SseAlertReportTo::delayLabel('now', 'fr'));
        self::assertSame('Immediately', SseAlertReportTo::delayLabel('now', 'en'));
        self::assertSame('Immédiatement', SseAlertReportTo::delayLabel('nimporte', 'fr'));
        foreach (array_keys(SseAlertLevel::DELAYS) as $delay) {
            self::assertNotSame('', SseAlertReportTo::delayLabel((string) $delay, 'en'));
        }
    }

    public function testReadingAStoredSnapshotNeverFailsAndBoundsEveryField(): void
    {
        $empty = SseAlertReportTo::read(null);
        self::assertSame('', $empty['fr']);
        self::assertSame('now', $empty['delay']);
        self::assertSame('#1F3A5F', $empty['color']);
        self::assertSame('hex', $empty['emblem']);
        $junk = SseAlertReportTo::read(['fr' => str_repeat('x', 500), 'color' => 'rouge', 'delay' => 'jamais', 'channel' => str_repeat('c', 900), 'emblem' => '']);
        self::assertLessThanOrEqual(120, mb_strlen($junk['fr']));
        self::assertLessThanOrEqual(200, mb_strlen($junk['channel']));
        self::assertSame('#1F3A5F', $junk['color'], 'couleur invalide remplacée');
        self::assertSame('now', $junk['delay']);
        $ok = SseAlertReportTo::read(['fr' => 'Cellule', 'en' => '', 'color' => '#aabbcc', 'delay' => '15m']);
        self::assertSame('Cellule', $ok['en'], 'sans libellé anglais : le français');
        self::assertSame('#AABBCC', $ok['color']);
        self::assertSame('15m', $ok['delay']);
        self::assertSame('Cellule (CELL)', SseAlertReportTo::label(['fr' => 'Cellule', 'en' => 'Cell', 'ac' => 'CELL'], 'fr'));
        self::assertSame('Cell', SseAlertReportTo::label(['fr' => 'Cellule', 'en' => 'Cell', 'ac' => ''], 'en'));
    }

    public function testTokensAreOpaqueUniqueAndValidatedAndTheAddressCarriesThem(): void
    {
        $tokens = [];
        for ($i = 0; $i < 25; ++$i) {
            $t = SseAlertRepository::newToken();
            self::assertTrue(SseAlertRepository::validToken($t), $t);
            self::assertSame(22, strlen($t));
            $tokens[$t] = true;
        }
        self::assertCount(25, $tokens, 'jetons tous différents');
        foreach (['', 'court', str_repeat('a', 21), str_repeat('a', 23), str_repeat('é', 22), 'abcdefghijklmnopqrst-_', "abcdefghijklmnopqrstuv\n"] as $bad) {
            self::assertFalse(SseAlertRepository::validToken($bad), 'refusé : ' . json_encode($bad));
        }
        $token = SseAlertRepository::newToken();
        self::assertSame('atak/sse/signalement/' . $token, SseAlertReportTo::path($token));
        self::assertSame('', SseAlertReportTo::url(null));
        self::assertSame('', SseAlertReportTo::url(''));
        $url = SseAlertReportTo::url($token);
        self::assertStringEndsWith('/atak/sse/signalement/' . $token, $url);
        self::assertStringNotContainsString('?', $url, 'jeton dans le chemin, jamais en paramètre');
    }
}
