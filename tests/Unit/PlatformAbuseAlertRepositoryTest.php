<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlatformAbuseAlertRepository as Repo;
use App\Services\Watch\WatchSchema;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Alertes d'activité anormale : regroupement des doublons, silence après « ignorer », décision humaine, données gardées.
 */
final class PlatformAbuseAlertRepositoryTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private MysqlDdlSqlitePdo $pdo;

    private Repo $repo;

    protected function setUp(): void
    {
        $this->pdo = new MysqlDdlSqlitePdo();
        WatchSchema::ensure($this->pdo);
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, steam_id TEXT, display_name TEXT, callsign TEXT, email TEXT)');
        $this->pdo->exec("INSERT INTO tenants (id, name) VALUES (7, 'Task Force Phantom')");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name, callsign, email) VALUES (42, 7, '76561190000000001', 'Jean Dupont', 'VIPER', 'jean@example.test')");
        $this->repo = new Repo($this->pdo);
    }

    /** @return array<string, mixed> */
    private function detection(string $steam = '76561190000000001', array $measured = ['sauts' => 3]): array
    {
        return ['kind' => Repo::KIND_MOD, 'rule' => 'position_impossible', 'tenant_id' => 7, 'steam_uid' => $steam, 'key' => '7:' . $steam, 'measured' => $measured];
    }

    private function countAlerts(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM platform_abuse_alerts')->fetchColumn();
    }

    public function testFirstDetectionIsStoredWithRulePlayerCommunityValuesAndTime(): void
    {
        self::assertSame('created', $this->repo->raise($this->detection(), self::NOW));
        $rows = $this->repo->recent('open', Repo::KIND_MOD);
        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertSame('position_impossible', $row['rule']);
        self::assertSame('Task Force Phantom', $row['tenant_name']);
        self::assertSame('VIPER', $row['user_callsign']);
        self::assertSame('76561190000000001', $row['steam_uid']);
        self::assertSame(['sauts' => 3], json_decode((string) $row['measured_json'], true));
        self::assertSame(gmdate('Y-m-d H:i:s', self::NOW), $row['first_seen_at']);
        // Aucune adresse ni e-mail dans ce qui est lu.
        self::assertArrayNotHasKey('email', $row);
        self::assertArrayNotHasKey('ip', $row);
    }

    public function testRepeatedDetectionsAreGroupedInsteadOfFloodingTheList(): void
    {
        self::assertSame('created', $this->repo->raise($this->detection(), self::NOW));
        self::assertSame('merged', $this->repo->raise($this->detection('76561190000000001', ['sauts' => 5]), self::NOW + 600));
        self::assertSame('merged', $this->repo->raise($this->detection(), self::NOW + 1200));
        self::assertSame(1, $this->countAlerts());
        $row = $this->repo->recent('open')[0];
        self::assertSame(3, (int) $row['occurrences']);
        self::assertSame(gmdate('Y-m-d H:i:s', self::NOW + 1200), $row['last_seen_at']);
        // Un autre joueur reste une autre alerte.
        self::assertSame('created', $this->repo->raise($this->detection('76561190000000002'), self::NOW + 1300));
        self::assertSame(2, $this->countAlerts());
        // Après plus de deux heures sans nouvelle, un nouvel épisode.
        self::assertSame('created', $this->repo->raise($this->detection(), self::NOW + 1200 + Repo::MERGE_ACTIVE_SECONDS + 1));
        self::assertSame(3, $this->countAlerts());
    }

    public function testDismissedCaseStaysSilentForADayThenComesBack(): void
    {
        $this->repo->raise($this->detection(), self::NOW);
        $id = (int) $this->repo->recent('open')[0]['id'];
        self::assertTrue($this->repo->setStatus($id, Repo::STATUS_DISMISSED, 5, self::NOW + 10));
        self::assertSame('skipped', $this->repo->raise($this->detection(), self::NOW + 3600));
        self::assertSame(1, $this->countAlerts());
        self::assertSame('created', $this->repo->raise($this->detection(), self::NOW + Repo::MERGE_DISMISSED_SECONDS + 60));
        self::assertSame(2, $this->countAlerts());
    }

    public function testHumanDecisionIsRecordedAndNothingElseHappens(): void
    {
        $this->repo->raise($this->detection(), self::NOW);
        $id = (int) $this->repo->recent('open')[0]['id'];
        self::assertFalse($this->repo->setStatus($id, 'banni', 5), 'aucun statut de sanction');
        self::assertFalse($this->repo->setStatus(9999, Repo::STATUS_ACK, 5));
        self::assertTrue($this->repo->setStatus($id, Repo::STATUS_ACK, 5, self::NOW + 60));
        $alert = $this->repo->find($id);
        self::assertSame('ack', $alert['status']);
        self::assertSame(5, (int) $alert['handled_by']);
        self::assertSame(['open' => 0, 'ack' => 1, 'dismissed' => 0], $this->repo->countsByStatus(Repo::KIND_MOD));
        self::assertSame([], $this->repo->recent('open'));
    }

    public function testLoadAlertsAreKeptApartFromModAlerts(): void
    {
        $this->repo->raise($this->detection(), self::NOW);
        $this->repo->raise(['kind' => Repo::KIND_LOAD, 'rule' => 'charge_elevee', 'key' => 'platform', 'measured' => ['palier' => 1]], self::NOW);
        self::assertCount(1, $this->repo->recent('', Repo::KIND_MOD));
        self::assertCount(1, $this->repo->recent('', Repo::KIND_LOAD));
        self::assertCount(2, $this->repo->recent(''));
    }

    public function testFingerprintDependsOnRuleCommunityAndActorOnly(): void
    {
        self::assertSame(Repo::fingerprint('mod', 'a', 7, 'k'), Repo::fingerprint('mod', 'a', 7, 'k'));
        self::assertNotSame(Repo::fingerprint('mod', 'a', 7, 'k'), Repo::fingerprint('mod', 'b', 7, 'k'));
        self::assertNotSame(Repo::fingerprint('mod', 'a', 7, 'k'), Repo::fingerprint('mod', 'a', 8, 'k'));
        self::assertNotSame(Repo::fingerprint('mod', 'a', 7, 'k'), Repo::fingerprint('mod', 'a', 7, 'z'));
    }
}
