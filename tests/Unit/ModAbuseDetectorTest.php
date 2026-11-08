<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Watch\ModAbuseDetector;
use App\Services\Watch\ModSignals;
use App\Services\Watch\WatchSettings;
use App\Services\Watch\WatchSpool;
use App\Services\Watch\WatchState;
use PHPUnit\Framework\TestCase;

/**
 * Règles de détection sur les envois du mod : chaque règle se déclenche sur le cas visé, reste muette sur l'usage
 * normal, et ne produit qu'une détection (aucune sanction).
 */
final class ModAbuseDetectorTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/watch-mod-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);
        WatchSpool::setDir($this->dir);
        WatchState::forget();
    }

    protected function tearDown(): void
    {
        WatchSpool::setDir(null);
        WatchState::forget();
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    /** @param list<list<string>> $rows @return list<array<string, mixed>> */
    private function detect(array $rows, ?array &$state = null): array
    {
        $result = ModAbuseDetector::analyse($rows, $state ?? [], WatchSettings::defaults(), self::NOW);
        $state = $result['state'];

        return $result['detections'];
    }

    /** @return list<string> */
    private function rules(array $detections): array
    {
        return array_map(static fn (array $d): string => (string) $d['rule'], $detections);
    }

    private function pos(int $ts, string $steam, int $x, int $y, int $map = 1, int $tenant = 7): array
    {
        return ['P', (string) $ts, (string) $tenant, $steam, (string) $map, (string) $x, (string) $y];
    }

    public function testImpossibleJumpsRaiseOneDetectionAndNormalMovementDoesNot(): void
    {
        $t = self::NOW - 300;
        $rows = [];
        // 20 km en 10 s, quatre fois : sans rapport avec une vitesse possible.
        for ($i = 0; $i < 4; $i++) {
            $rows[] = $this->pos($t + $i * 10, '76561190000000001', $i % 2 === 0 ? 1000 : 21000, 1000);
        }
        $found = $this->detect($rows);
        self::assertSame(['position_impossible'], $this->rules($found));
        self::assertSame(7, $found[0]['tenant_id']);
        self::assertSame('76561190000000001', $found[0]['steam_uid']);
        self::assertSame(3, $found[0]['measured']['sauts']);
        self::assertSame(2000, $found[0]['measured']['vitesse_max_ms']);

        // Joueur à pied (5 m/s), véhicule rapide (40 m/s) : rien.
        $walk = [];
        for ($i = 0; $i < 20; $i++) {
            $walk[] = $this->pos($t + $i * 10, '76561190000000002', 1000 + $i * 400, 1000);
        }
        self::assertSame([], $this->detect($walk));
    }

    public function testJumpsAcrossMapsOrLongGapsAreNotJudged(): void
    {
        $t = self::NOW - 400;
        $differentMap = [];
        for ($i = 0; $i < 6; $i++) {
            $differentMap[] = $this->pos($t + $i * 10, '76561190000000003', $i % 2 === 0 ? 1000 : 21000, 1000, $i % 2 === 0 ? 1 : 2);
        }
        self::assertSame([], $this->detect($differentMap));
        $gaps = [];
        for ($i = 0; $i < 6; $i++) {
            $gaps[] = $this->pos($t + $i * 200, '76561190000000004', $i % 2 === 0 ? 1000 : 21000, 1000);
        }
        self::assertSame([], $this->detect($gaps));
    }

    public function testOutOfMapPositionsAreRepeatedBeforeAnAlert(): void
    {
        $t = self::NOW - 100;
        $two = [$this->pos($t, '76561190000000005', 99000, 100), $this->pos($t + 5, '76561190000000005', 99000, 100)];
        self::assertSame([], $this->detect($two));
        $three = array_merge($two, [$this->pos($t + 10, '76561190000000005', 99000, 100)]);
        $found = $this->detect($three);
        self::assertSame(['position_hors_carte'], $this->rules($found));
        self::assertSame(99000, $found[0]['measured']['coordonnee_max_m']);
    }

    public function testVolumeNeedsSeveralMinutesAboveTheThreshold(): void
    {
        $limit = (int) WatchSettings::defaults()[WatchSettings::VOLUME_PER_MIN];
        $minute = intdiv(self::NOW, 60) * 60;
        $burst = static function (int $at, int $n): array {
            $rows = [];
            for ($i = 0; $i < $n; $i++) {
                $rows[] = ['S', (string) ($at + ($i % 59)), '7', '76561190000000006', 'k', '-', '-', '-', '-', 'write'];
            }

            return $rows;
        };
        self::assertSame([], $this->detect($burst($minute - 60, $limit + 10)), 'une minute isolée');
        $found = $this->detect(array_merge($burst($minute - 120, $limit + 5), $burst($minute - 60, $limit + 5)));
        self::assertSame(['volume_anormal'], $this->rules($found));
        self::assertSame(2, $found[0]['measured']['minutes_au_dessus']);
    }

    public function testVolumeOfAnonymousSendersIsNeverAttributedToAPlayer(): void
    {
        $limit = (int) WatchSettings::defaults()[WatchSettings::VOLUME_PER_MIN];
        $minute = intdiv(self::NOW, 60) * 60;
        $rows = [];
        foreach ([120, 60] as $back) {
            for ($i = 0; $i < $limit + 5; $i++) {
                $rows[] = ['S', (string) ($minute - $back + ($i % 59)), '7', '-', 'k', '-', '-', '-', '-', 'write'];
            }
        }
        self::assertSame([], $this->detect($rows));
    }

    public function testOneAccountTokenWithSeveralSteamIdsIsDetectedButTheSharedCommunityKeyIsNot(): void
    {
        $t = self::NOW - 200;
        $token = [];
        foreach (['76561190000000011', '76561190000000012', '76561190000000013'] as $i => $steam) {
            $token[] = ['S', (string) ($t + $i), '7', $steam, 't', 'a1b2c3d4e5f6', '-', '-', '-', 'write'];
        }
        $found = $this->detect($token);
        self::assertSame(['identifiant_multi_steam'], $this->rules($found));
        self::assertSame(3, $found[0]['measured']['steams_distincts']);

        // Clé de communauté : tous les joueurs la présentent, c'est normal.
        $key = [];
        for ($i = 0; $i < 40; $i++) {
            $key[] = ['S', (string) ($t + $i), '7', '7656119000000' . (1000 + $i), 'k', '-', '-', '-', '-', 'write'];
        }
        self::assertSame([], $this->detect($key));
    }

    public function testDeclaredSteamDifferentFromTheTokenSteamIsDetectedWhenRepeated(): void
    {
        $t = self::NOW - 100;
        $rows = [];
        for ($i = 0; $i < 3; $i++) {
            $rows[] = ['S', (string) ($t + $i), '7', '76561190000000021', 's', 'ffeeddccbbaa', '76561190000000022', '-', '-', 'write'];
        }
        $found = $this->detect($rows);
        self::assertSame(['identifiant_steam_decale'], $this->rules($found));
        self::assertSame('76561190000000021', $found[0]['steam_uid']);
    }

    public function testRefusedWritesInABurstAreDetectedWithTheirReasons(): void
    {
        $limit = (int) WatchSettings::defaults()[WatchSettings::REFUSALS_PER_MIN];
        $minute = intdiv(self::NOW, 60) * 60 - 60;
        $rows = [];
        for ($i = 0; $i < $limit; $i++) {
            $rows[] = ['R', (string) ($minute + ($i % 50)), '7', '76561190000000031', $i % 3 === 0 ? 'steam_spoof' : 'rate_limited', 'write'];
        }
        $found = $this->detect($rows);
        self::assertSame(['ecritures_refusees'], $this->rules($found));
        self::assertSame($limit, $found[0]['measured']['refus_dans_la_minute']);
        self::assertSame(['rate_limited', 'steam_spoof'], array_keys($found[0]['measured']['motifs']));
        self::assertSame([], $this->detect(array_slice($rows, 0, $limit - 1)));
    }

    public function testVersionMismatchAndFlappingAreDetected(): void
    {
        $t = self::NOW - 100;
        $mismatch = [];
        for ($i = 0; $i < 3; $i++) {
            $mismatch[] = ['S', (string) ($t + $i), '7', '76561190000000041', 'k', '-', '-', '2.0.96', '2.0.95', 'write'];
        }
        $found = $this->detect($mismatch);
        self::assertSame(['version_incoherente'], $this->rules($found));
        self::assertSame('2.0.96', $found[0]['measured']['version_en_tete']);

        $same = [];
        for ($i = 0; $i < 10; $i++) {
            $same[] = ['S', (string) ($t + $i), '7', '76561190000000042', 'k', '-', '-', '2.0.96', '2.0.96+abc', 'write'];
        }
        self::assertSame([], $this->detect($same), 'suffixe de build ignoré');

        $flap = [];
        foreach (['2.0.96', '2.0.90', '2.0.99'] as $i => $version) {
            $flap[] = ['S', (string) ($t + $i), '7', '76561190000000043', 'k', '-', '-', $version, '-', 'write'];
        }
        $found = $this->detect($flap);
        self::assertSame(['version_incoherente'], $this->rules($found));
        self::assertSame(3, $found[0]['measured']['versions_distinctes']);
    }

    public function testADetectionIsNotRepeatedByTheNextPassWithoutNewEvidence(): void
    {
        $t = self::NOW - 300;
        $rows = [];
        for ($i = 0; $i < 4; $i++) {
            $rows[] = $this->pos($t + $i * 10, '76561190000000051', $i % 2 === 0 ? 1000 : 21000, 1000);
        }
        $state = [];
        self::assertSame(['position_impossible'], $this->rules($this->detect($rows, $state)));
        self::assertSame([], $this->detect([], $state));
    }

    public function testNoDetectionCarriesAnAddressOrAnEmail(): void
    {
        $t = self::NOW - 300;
        $rows = [];
        for ($i = 0; $i < 4; $i++) {
            $rows[] = $this->pos($t + $i * 10, '76561190000000061', $i % 2 === 0 ? 1000 : 21000, 1000);
        }
        $json = json_encode($this->detect($rows));
        self::assertDoesNotMatchRegularExpression('/@|\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', (string) $json);
    }

    public function testSignalsNeverWriteTheSecretNorAnAddress(): void
    {
        ModSignals::actor(7, '76561190000000071', ModSignals::CRED_ACCOUNT_TOKEN, 'SECRET-TOKEN-VALUE', '76561190000000071', '2.0.96', '2.0.96', 'write', self::NOW);
        ModSignals::actor(7, '76561190000000072', ModSignals::CRED_COMMUNITY_KEY, 'COMMUNITY-KEY-VALUE', null, '', '', 'write', self::NOW);
        ModSignals::refusal(7, '76561190000000071', 'steam_spoof', 'write', self::NOW);
        ModSignals::position(7, '76561190000000071', 1, 1234.6, 99.2, self::NOW);
        $files = glob($this->dir . '/mod-*.log') ?: [];
        self::assertCount(1, $files);
        $text = (string) file_get_contents($files[0]);
        self::assertStringNotContainsString('SECRET-TOKEN-VALUE', $text);
        self::assertStringNotContainsString('COMMUNITY-KEY-VALUE', $text);
        $lines = explode("\n", trim($text));
        self::assertCount(4, $lines);
        $token = explode('|', $lines[0]);
        self::assertSame('S', $token[0]);
        self::assertSame(12, strlen($token[5]), 'empreinte tronquée du jeton de compte');
        $key = explode('|', $lines[1]);
        self::assertSame('-', $key[5], 'la clé de communauté (partagée) n’a pas d’empreinte');
        self::assertSame('P|' . self::NOW . '|7|76561190000000071|1|1235|99', $lines[3]);
    }

    public function testSignalsAreSilentWhenDetectionIsSwitchedOff(): void
    {
        WatchState::write(['at' => time(), 'detect_enabled' => false, 'level' => 0]);
        ModSignals::refusal(7, '76561190000000081', 'steam_spoof', 'write', self::NOW);
        self::assertSame([], glob($this->dir . '/mod-*.log') ?: []);
    }
}
