<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminIntelIntakeController;
use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelPlayerFeedService;
use App\Services\Intel\IntelPlayerIdentity;
use PHPUnit\Framework\TestCase;

final class IntelPlayerIdentityTest extends TestCase
{
    private function identity(): IntelPlayerIdentity
    {
        return new IntelPlayerIdentity(
            [
                ['id' => 42, 'display_name' => 'Jean Dupont', 'callsign' => 'Alpha 1-1', 'steam_id' => '76561198000000042'],
                ['id' => 7, 'display_name' => 'Marie', 'callsign' => '', 'steam_id' => null],
            ],
            [
                // Profil de jeu rattaché au compte 7 : son indicatif en jeu et son Steam mènent au compte.
                ['steam_id' => '76561198000000007', 'user_id' => 7, 'callsign' => 'Écho 2'],
                // Profil de jeu sans compte : l'indicatif mène au Steam.
                ['steam_id' => '76561198000000099', 'user_id' => null, 'callsign' => 'Ghost'],
            ],
            [
                ['user_id' => 42, 'steam_id' => '76561198000000043'],
            ]
        );
    }

    public function testAccountWinsThenSteamThenCallsign(): void
    {
        $id = $this->identity();

        self::assertSame('u42', $id->resolve(42, '76561198000000099', 'Ghost'), 'le compte l’emporte');
        self::assertSame('u42', $id->resolve(null, '76561198000000042', 'n’importe'), 'Steam du compte');
        self::assertSame('u42', $id->resolve(0, '76561198000000043', ''), 'Steam vu en session de jeu');
        self::assertSame('u7', $id->resolve(null, '76561198000000007', null), 'Steam du profil de jeu rattaché');
        self::assertSame('s76561198000000099', $id->resolve(null, '76561198000000099', 'Ghost'), 'Steam sans compte');
        self::assertSame('s76561198123456789', $id->resolve('', '76561198123456789', 'Inconnu'), 'Steam inconnu');
    }

    public function testCallsignsMergeAcrossSpellings(): void
    {
        $id = $this->identity();

        self::assertSame('u42', $id->resolve(null, null, 'ALPHA1-1'));
        self::assertSame('u42', $id->resolve(null, '', ' alpha 1-1 '));
        self::assertSame('u7', $id->resolve(null, null, 'echo 2'), 'accents ignorés, indicatif du profil de jeu');
        self::assertSame('s76561198000000099', $id->resolve(null, null, 'GHOST'), 'indicatif d’un profil sans compte');
        self::assertSame('c-bravo2-3', $id->resolve(null, null, 'Bravo 2-3'));
        self::assertSame($id->resolve(null, null, 'Bravo 2-3'), $id->resolve(null, null, 'bravo2-3'));
        self::assertSame(IntelPlayerIdentity::UNKNOWN, $id->resolve(null, null, '  '));
        self::assertSame(IntelPlayerIdentity::UNKNOWN, $id->resolve(0, '0', null));
    }

    public function testNormalizers(): void
    {
        self::assertNull(IntelPlayerIdentity::normalizeSteam(''));
        self::assertNull(IntelPlayerIdentity::normalizeSteam('0'));
        self::assertSame('76561198000000042', IntelPlayerIdentity::normalizeSteam(' 76561198000000042 '));
        self::assertSame('alpha1-1', IntelPlayerIdentity::normalizeCallsign('Alpha 1-1'));
        self::assertSame('ecoeur', IntelPlayerIdentity::normalizeCallsign('Écœur'));
        self::assertMatchesRegularExpression('/^h[0-9a-f]{10}$/', IntelPlayerIdentity::normalizeCallsign('Сокол'));
    }

    public function testKeysAreUrlSafeAndValidated(): void
    {
        $id = $this->identity();
        foreach ([$id->resolve(42), $id->resolve(null, '76561198000000099'), $id->resolve(null, null, 'Bravo 2-3'), $id->resolve(null, null, 'Сокол')] as $key) {
            self::assertTrue(IntelPlayerIdentity::validKey($key), $key);
            self::assertSame($key, rawurlencode($key));
        }
        self::assertFalse(IntelPlayerIdentity::validKey('u0'));
        self::assertFalse(IntelPlayerIdentity::validKey('../etc'));
        self::assertFalse(IntelPlayerIdentity::validKey("c-a' OR 1=1"));
    }

    public function testCardDescribesTheKey(): void
    {
        $id = $this->identity();

        $u = $id->card('u7');
        self::assertSame('user', $u['kind']);
        self::assertSame('Marie', $u['account']);
        self::assertSame('Écho 2', $u['callsign'], 'indicatif du profil de jeu quand le compte n’en a pas');
        self::assertSame('76561198000000007', $u['steam']);

        $s = $id->card('s76561198000000099');
        self::assertSame('steam', $s['kind']);
        self::assertSame('Ghost', $s['callsign']);
        self::assertNull($s['user_id']);
    }

    public function testAccumulateMergesGroups(): void
    {
        $p = IntelPlayerFeedService::emptySummary();
        IntelPlayerFeedService::accumulate($p, 'cr', ['u' => 42, 's' => null, 'c' => 'Alpha 1-1', 'sub' => 'SPOTREP', 'n' => 3, 'last' => '2026-10-19 10:00:00']);
        IntelPlayerFeedService::accumulate($p, 'cr', ['u' => null, 's' => null, 'c' => 'ALPHA1-1', 'sub' => 'SALUTE', 'n' => 2, 'last' => '2026-10-20 08:00:00']);
        IntelPlayerFeedService::accumulate($p, 'perte', ['u' => null, 's' => '76561198000000042', 'c' => '', 'sub' => 'kia', 'n' => 1, 'last' => '2026-10-18 08:00:00']);

        self::assertSame(6, $p['total']);
        self::assertSame(5, $p['counts']['cr']);
        self::assertSame(1, $p['counts']['perte']);
        self::assertSame(['SPOTREP' => 3, 'SALUTE' => 2], $p['subs']['cr']);
        self::assertSame('2026-10-20 08:00:00', $p['last']);
        self::assertSame(['Alpha 1-1' => 3, 'ALPHA1-1' => 2], $p['callsigns']);
        self::assertArrayHasKey('76561198000000042', $p['steams']);
    }

    public function testPeriodBounds(): void
    {
        $now = strtotime('2026-10-20 12:00:00');
        self::assertSame('2026-10-19 12:00:00', IntelPlayerFeedService::periodStart('24h', $now));
        self::assertSame('2026-10-13 12:00:00', IntelPlayerFeedService::periodStart('7j', $now));
        self::assertNull(IntelPlayerFeedService::periodStart('tout', $now));
        self::assertSame('30j', IntelPlayerFeedService::normalizePeriod('n/importe'));
    }

    public function testPlayersFilterAndSort(): void
    {
        $mk = static fn (string $key, string $cs, int $total, int $cr, string $last, array $seen = []): array => [
            'key' => $key, 'callsign' => $cs, 'account' => '', 'steam' => '', 'total' => $total, 'last' => $last,
            'counts' => ['cr' => $cr], 'callsigns_seen' => $seen,
        ];
        $rows = [$mk('c-a', 'Alpha', 3, 3, '2026-10-01'), $mk('c-b', 'Bravo', 9, 1, '2026-10-19', ['Viper']), $mk('c-c', 'Charlie', 5, 5, '2026-10-10')];

        self::assertSame(['c-b', 'c-c', 'c-a'], array_column(AdminIntelIntakeController::sortPlayers($rows, 'total'), 'key'));
        self::assertSame(['c-c', 'c-a', 'c-b'], array_column(AdminIntelIntakeController::sortPlayers($rows, 'cr'), 'key'));
        self::assertSame(['c-b', 'c-c', 'c-a'], array_column(AdminIntelIntakeController::sortPlayers($rows, 'last'), 'key'));
        self::assertSame(['c-a', 'c-b', 'c-c'], array_column(AdminIntelIntakeController::sortPlayers($rows, 'name'), 'key'));
        self::assertSame(['c-b'], array_column(AdminIntelIntakeController::filterPlayers($rows, 'viper'), 'key'), 'indicatif vu');
    }

    public function testNewStatesAreClosedAndMapToSourceStatus(): void
    {
        foreach (['doublon', 'transmise'] as $s) {
            self::assertArrayHasKey($s, IntelIntakeService::STATES);
            self::assertArrayHasKey($s, IntelIntakeService::STATE_HINTS);
            self::assertNotContains($s, IntelIntakeService::OPEN_STATES);
        }
        self::assertSame('ARCHIVED', IntelIntakeService::sourceStatusFor('cr', 'doublon'));
        self::assertSame('sans_suite', IntelIntakeService::sourceStatusFor('fiche', 'doublon'));
        self::assertSame('ACKNOWLEDGED', IntelIntakeService::sourceStatusFor('cr', 'transmise'));
        self::assertSame('prise_en_compte', IntelIntakeService::sourceStatusFor('fiche', 'transmise'));
        self::assertSame('a_traiter', IntelIntakeService::defaultState('cr', 'SUBMITTED'), 'états existants inchangés');
    }
}
