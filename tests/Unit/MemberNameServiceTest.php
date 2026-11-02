<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\Personnel\MemberNameService;
use App\Services\Personnel\MemberNameSettings;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Trigrammes uniques par communauté : étape de migration idempotente, remplissage sans écraser, collisions,
 * saisie à la main avec contrôle d’unicité, stabilité quand le nom change, réglage d’affichage.
 */
final class MemberNameServiceTest extends TestCase
{
    private MysqlDdlSqlitePdo $pdo;

    private mixed $previousPdo;

    protected function setUp(): void
    {
        $this->previousPdo = (new ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->pdo = new MysqlDdlSqlitePdo();
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, settings TEXT)');
        $this->pdo->exec("INSERT INTO tenants (id, name, settings) VALUES (1, 'Alpha', '{}'), (2, 'Bravo', NULL), (3, 'Charlie', '{\"member_names\":{\"list_format\":\"full\"}}')");
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
        $this->pdo->exec('CREATE TABLE user_community_memberships (user_id INTEGER, tenant_id INTEGER, status TEXT)');
        $this->pdo->exec('CREATE TABLE document_variables_catalog (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, code TEXT, label TEXT, source_type TEXT, description TEXT, category TEXT, is_active INTEGER)');
        $users = [
            [10, 1, 'Jean Dupont', 'Viper', 'Jean', 'Dupont'],
            [11, 1, 'Julie Dupuis', '', 'Julie', 'Dupuis'],
            [12, 1, 'Ghost', 'Reaper', '', ''],
            [13, 2, 'Jean Dupont', '', 'Jean', 'Dupont'],
            [14, 1, 'Compte supprimé', '', '', ''],
        ];
        foreach ($users as [$id, $tid, $dn, $cs, $fn, $ln]) {
            $this->pdo->prepare('INSERT INTO users (id, tenant_id, display_name, callsign) VALUES (?, ?, ?, ?)')->execute([$id, $tid, $dn, $cs]);
            $this->pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $fn, $ln]);
        }
        // Membre de Bravo venu d’Alpha (adhésion active).
        $this->pdo->exec("INSERT INTO user_community_memberships (user_id, tenant_id, status) VALUES (10, 2, 'active')");
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
        MemberNameService::forgetSchema();
        MemberNameSettings::forget();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        MemberNameService::forgetSchema();
        MemberNameSettings::forget();
    }

    private function migrate(): string
    {
        $pdo = $this->pdo;
        $root = dirname(__DIR__, 2);
        ob_start();
        try {
            require $root . '/database/steps/3600_member_names_trigrams.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    /** @return array<int, string> */
    private function trigrams(int $tenantId): array
    {
        $st = $this->pdo->prepare('SELECT user_id, trigram FROM member_trigrams WHERE tenant_id = ? ORDER BY user_id');
        $st->execute([$tenantId]);

        return array_map('strval', array_column($st->fetchAll(\PDO::FETCH_ASSOC), 'trigram', 'user_id'));
    }

    /** @param array<string, mixed> $value */
    private function setting(array $value): void
    {
        $this->pdo->prepare('UPDATE tenants SET settings = ? WHERE id = 1')->execute([json_encode(['member_names' => $value])]);
        MemberNameSettings::forget();
    }

    public function testStepCreatesTableFillsUniquePerCommunityAndReplayChangesNothing(): void
    {
        $out = $this->migrate();
        self::assertStringContainsString('member_trigrams créée', $out);
        // Alpha : Jean Dupont (le plus ancien) garde JDU, Julie Dupuis prend la variante suivante, Ghost son pseudo.
        self::assertSame([10 => 'JDU', 11 => 'JDP', 12 => 'GHO'], $this->trigrams(1));
        // Bravo : la même personne et un homonyme ; l’unicité est propre à chaque communauté.
        self::assertSame([10 => 'JDU', 13 => 'JDP'], $this->trigrams(2));

        $this->pdo->changes = 0;
        $this->pdo->exec("UPDATE member_trigrams SET trigram = 'ZZZ', source = 'manual' WHERE tenant_id = 1 AND user_id = 12");
        $replay = $this->migrate();
        self::assertSame(0, $this->pdo->changes, 'la structure n’est pas retouchée');
        self::assertStringContainsString('déjà présente', $replay);
        self::assertSame([10 => 'JDU', 11 => 'JDP', 12 => 'ZZZ'], $this->trigrams(1), 'un trigramme existant n’est jamais remplacé');
    }

    public function testStepSeedsDisplaySettingWithoutOverwritingAndAddsDocumentVariablesOnce(): void
    {
        $this->migrate();
        $this->migrate();
        $settings = array_column($this->pdo->query('SELECT id, settings FROM tenants')->fetchAll(\PDO::FETCH_ASSOC), 'settings', 'id');
        self::assertSame(MemberNameSettings::DEFAULTS, json_decode((string) $settings[1], true)['member_names']);
        self::assertSame(MemberNameSettings::DEFAULTS, json_decode((string) $settings[2], true)['member_names']);
        self::assertSame(['list_format' => 'full'], json_decode((string) $settings[3], true)['member_names'], 'réglage existant conservé');
        $codes = $this->pdo->query('SELECT code FROM document_variables_catalog ORDER BY code')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['superior.trigram', 'user.short_name', 'user.trigram'], $codes);
    }

    public function testManualTrigramIsUniqueAndStaysWhenTheNameChanges(): void
    {
        $this->migrate();
        $svc = new MemberNameService($this->pdo);

        $taken = $svc->setTrigram(1, 11, 'jdu');
        self::assertFalse($taken['ok']);
        self::assertStringContainsString('déjà attribué', (string) $taken['error']);
        self::assertFalse($svc->setTrigram(1, 11, 'J1')['ok']);
        self::assertSame(['ok' => true, 'trigram' => 'JUL'], $svc->setTrigram(1, 11, 'jul'));
        // Le même trigramme reste libre dans une autre communauté.
        self::assertTrue($svc->setTrigram(2, 13, 'JUL')['ok']);

        // Changement de nom : le trigramme ne bouge pas, sauf recalcul demandé.
        $this->pdo->exec("UPDATE user_profiles SET first_name = 'Marc', last_name = 'Durand' WHERE user_id = 10");
        self::assertSame('JDU', $svc->ensureTrigram(1, 10));
        self::assertSame('MDU', $svc->regenerate(1, 10));
        self::assertSame('MDU', $svc->trigramFor(1, 10));
    }

    public function testNewMemberGetsTrigramAndLabelsUseCommunityFormat(): void
    {
        $this->migrate();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, display_name, callsign) VALUES (20, 1, 'Jo', 'Hawk')");
        $this->pdo->exec("INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (20, 'Jérôme', 'Dupré')");
        $svc = new MemberNameService($this->pdo);
        self::assertSame('JDE', $svc->ensureTrigram(1, 20), 'JDU et JDP sont pris dans la communauté');
        self::assertSame(['assigned' => 0, 'failed' => 0], $svc->assignMissing(1));

        self::assertSame([10 => 'Jean D.', 12 => 'Ghost'], $svc->labelsFor(1, [10, 12]));
        self::assertSame([10 => 'Jean DUPONT'], $svc->labelsFor(1, [10], 'card'));
        $this->setting(['list_format' => 'trigram', 'show_grade' => true, 'trigrams_enabled' => true]);
        self::assertSame([10 => 'Sgt JDU'], $svc->labelsFor(1, [10], 'list', [10 => 'Sgt']));
        $this->setting(['list_format' => 'trigram', 'trigrams_enabled' => false]);
        self::assertSame([10 => 'Jean D.'], $svc->labelsFor(1, [10]), 'trigrammes coupés : le nom abrégé revient');
    }
}
