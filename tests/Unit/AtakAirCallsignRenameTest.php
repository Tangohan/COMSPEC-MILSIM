<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakDataRepository;
use App\Services\Tactical\AirBriefService;
use App\Services\Tactical\NineLineBoardService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Indicatif d'avion changé en jeu : la page 9-LINE (mobile JTAC) le montrait toujours « ALPHA 1-3 ».
 *
 * Cause : upsertAirAsset gardait l'indicatif de la première remontée d'occupation (le groupe Arma par défaut
 * « Alpha 1-3 ») ; l'indicatif choisi ensuite par le pilote dans l'ATAK natif (COMSPEC_ATAK_AirCs) était ignoré.
 * Dépôt réel sur sqlite, relu par NineLineBoardService::aircraft comme la page.
 */
final class AtakAirCallsignRenameTest extends TestCase
{
    private const T = 7;

    private PDO $pdo;

    private AtakDataRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $this->pdo->exec('CREATE TABLE atak_air_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1,
            mission_id TEXT, callsign TEXT NOT NULL, model TEXT, aircraft_type TEXT, freq TEXT, radio_main TEXT, radio_aux TEXT, laser TEXT, auth TEXT,
            auth_code TEXT, pilot TEXT, crew TEXT, fuel_pct INTEGER, ordnance TEXT, station TEXT, eta_minutes INTEGER, bingo_fuel TEXT, checklist TEXT,
            pos_x REAL, pos_y REAL, pos_z REAL, alt REAL, heading REAL, side TEXT, status TEXT, pilot_status TEXT, aircraft_count INTEGER,
            last_update INTEGER, source TEXT, vehicle_id TEXT, updated_at TEXT, UNIQUE (tenant_id, map_id, callsign))');
        $this->pdo->exec('CREATE TABLE atak_maps (id INTEGER PRIMARY KEY, label TEXT)');
        $this->pdo->exec('CREATE TABLE atak_brief_callsigns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, scope TEXT NOT NULL,
            owner_key TEXT NOT NULL, callsign TEXT NOT NULL, updated_by_user_id INTEGER, updated_at TEXT, UNIQUE (tenant_id, scope, owner_key))');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT)');
        $this->pdo->exec('CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
        $this->repo = new AtakDataRepository($this->pdo);
    }

    /** @return array<string, mixed> */
    private static function occupancy(string $callsign, string $vehicleId, bool $explicit, float $x = 1000.0): array
    {
        $data = ['source' => 'occupancy', 'inferred' => true, 'vehicle_id' => $vehicleId, 'pos_x' => $x, 'pos_y' => 2000.0,
            'model' => 'A-10C', 'aircraft_type' => 'plane', 'pilot' => 'paul_d', 'status' => 'IN-FLIGHT'];
        if ($explicit) {
            $data['callsign_explicit'] = true;
        }

        return $data;
    }

    private function upsert(string $callsign, array $data): array
    {
        return $this->repo->upsertAirAsset(self::T, 1, $callsign, $data + ['callsign' => $callsign]);
    }

    /** @return list<string> */
    private function callsigns(): array
    {
        return array_map('strval', $this->pdo->query('SELECT callsign FROM atak_air_assets ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testPilotCallsignReplacesArmaGroupDefault(): void
    {
        $this->upsert('Alpha 1-3', self::occupancy('Alpha 1-3', '2:51', false));
        $row = $this->upsert('VIPER 1', self::occupancy('VIPER 1', '2:51', true));

        self::assertSame('VIPER 1', $row['callsign']);
        self::assertSame(['VIPER 1'], $this->callsigns());

        $board = new NineLineBoardService($this->pdo, null, null, new AirBriefService($this->pdo));
        $air = $board->aircraft(self::T);
        self::assertCount(1, $air);
        self::assertSame('VIPER 1', $air[0]['callsign']);
        self::assertSame('VIPER 1', $air[0]['label']);
    }

    public function testRenamedArmaGroupFollowsWithOlderModToo(): void
    {
        $this->upsert('Alpha 1-3', self::occupancy('Alpha 1-3', '2:51', false));
        $this->upsert('Hawg 2-1', self::occupancy('Hawg 2-1', '2:51', false));

        self::assertSame(['Hawg 2-1'], $this->callsigns());
    }

    public function testManifestCallsignKeptUnlessThePilotChoosesOne(): void
    {
        $this->upsert('HOG 21', ['source' => 'manifest', 'vehicle_id' => '2:51', 'pos_x' => 1000.0, 'pos_y' => 2000.0]);
        $this->upsert('Alpha 1-3', self::occupancy('Alpha 1-3', '2:51', false));
        self::assertSame(['HOG 21'], $this->callsigns(), 'Le groupe Arma n’écrase pas l’indicatif déclaré au manifeste.');

        $this->upsert('VIPER 1', self::occupancy('VIPER 1', '2:51', true));
        self::assertSame(['VIPER 1'], $this->callsigns(), 'L’indicatif choisi par le pilote l’emporte.');
    }

    public function testPositionOnlyMatchNeverSwapsCallsigns(): void
    {
        $this->upsert('HOG 21', self::occupancy('HOG 21', '', false));
        $this->upsert('VIPER 1', self::occupancy('VIPER 1', '', true, 1010.0));

        self::assertSame(['HOG 21'], $this->callsigns());
    }

    public function testCallsignHeldByALiveAircraftIsNotStolen(): void
    {
        $this->upsert('VIPER 1', self::occupancy('VIPER 1', '2:60', true, 8000.0));
        $this->upsert('Alpha 1-3', self::occupancy('Alpha 1-3', '2:51', false));
        $this->upsert('VIPER 1', self::occupancy('VIPER 1', '2:51', true));

        self::assertSame(['VIPER 1', 'Alpha 1-3'], $this->callsigns());
    }

    public function testStaleRowHoldingTheCallsignIsReplaced(): void
    {
        $this->upsert('VIPER 1', self::occupancy('VIPER 1', '2:60', true, 8000.0));
        $this->pdo->exec("UPDATE atak_air_assets SET updated_at = '2020-01-01 00:00:00'");
        $this->upsert('Alpha 1-3', self::occupancy('Alpha 1-3', '2:51', false));
        $this->upsert('VIPER 1', self::occupancy('VIPER 1', '2:51', true));

        self::assertSame(['VIPER 1'], $this->callsigns());
        self::assertSame('2:51', (string) $this->pdo->query('SELECT vehicle_id FROM atak_air_assets')->fetchColumn());
    }

    public function testModSendsThePilotCallsignAsExplicitAndPushesItAtOnce(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportCrewedAirAssets.sqf');
        self::assertStringContainsString('["callsign_explicit", _csExplicit]', $src);
        self::assertStringContainsString('_x getVariable ["COMSPEC_ATAK_AirCs", ""]', $src);
        // Le changement d'indicatif entre dans la signature : remonté au prochain passage (3 s), pas au battement de 7 s.
        self::assertMatchesRegularExpression('/private _sig = format \[\s*"%1\|%2\|%3\|%4\|%5\|%6"/', $src);
    }
}
