<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\UserProfileRepository;
use App\Services\DataQuality\DataQualityAnalyzer;
use App\Services\DataQuality\DatabaseDataQualitySource;
use App\Services\LegacyData\LegacyDataCounter;
use App\Services\LegacyData\LegacyDataNotice;
use App\Support\ShortCache;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyTenantFixture as F;

/**
 * « Membre sans prénom ni nom » (Données anciennes à vérifier, Qualité des données) : le signalement se lève dès que le
 * prénom et le nom du personnage sont saisis (fiche membre › État civil du personnage → user_profiles), sans attendre
 * la fin du cache, et l'identité légale ne compte plus comme le nom affiché.
 *
 * Base réelle en mémoire (jeu « vieille communauté ») complétée de la table user_profiles, branchée sur Database le temps
 * du test pour passer par le vrai UserProfileRepository::upsert.
 */
final class CivilNameAnomalyLiftTest extends TestCase
{
    private ?PDO $previousPdo = null;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = F::create();
        $this->pdo->exec('CREATE TABLE user_profiles (user_id int NOT NULL PRIMARY KEY, first_name varchar(100), last_name varchar(100), created_at datetime, updated_at datetime)');
        // Alice (1) : nom du personnage saisi ; Marie (5) : prénom seul ; Viper (2), Hawk (3), Ghost (4) : rien.
        // Ghost a une ligne vide faite d'espaces : vide quand même.
        $st = $this->pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)');
        foreach ([[1, 'Alice', 'Martin'], [4, '  ', ' '], [5, 'Marie', '']] as $row) {
            $st->execute($row);
        }
        // NOW() de MySQL, utilisé par UserProfileRepository à la mise à jour d'une ligne existante.
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $prop = new \ReflectionProperty(Database::class, 'pdo');
        $prev = $prop->getValue();
        $this->previousPdo = $prev instanceof PDO ? $prev : null;
        $prop->setValue(null, $this->pdo);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        ShortCache::setEnabled(null);
    }

    /** @return list<int> */
    private function unnamedIds(): array
    {
        return array_map(static fn (array $m): int => $m['id'], (new LegacyDataCounter($this->pdo))->membersWithoutCivilName(F::TENANT));
    }

    public function testCounterReadsTheCharacterNameNotTheLegalIdentity(): void
    {
        $counter = new LegacyDataCounter($this->pdo);
        self::assertSame('user_profiles', $counter->civilNameTable());
        self::assertSame([2, 3, 4], $this->unnamedIds());
        self::assertSame(3, $counter->count(F::TENANT, F::now())['no_civil_name']);

        // Un nom légal seul ne fait pas un nom affiché : Hawk reste signalé.
        $this->pdo->exec("INSERT INTO user_legal_identities (tenant_id, user_id, first_name, last_name) VALUES (7, 3, 'Jean', 'Dupont')");
        self::assertContains(3, $this->unnamedIds());
        // …et n'est plus « reprenable » (la reprise ne remplit qu'une identité légale vide) : Ghost seul l'est encore.
        self::assertSame(1, $counter->count(F::TENANT, F::now())['importable_names']);
    }

    public function testSavingFirstAndLastNameLiftsTheAnomalyAtOnceEvenWithTheCacheOn(): void
    {
        ShortCache::setEnabled(true);
        LegacyDataNotice::forgetCivilNames(); // génération neuve : aucune valeur d'un autre passage
        $counter = new LegacyDataCounter($this->pdo);
        self::assertSame(3, LegacyDataNotice::counts(F::TENANT, $counter)['no_civil_name']);

        // Ce que fait la fenêtre « État civil du personnage » pour Viper (2) : écriture par le dépôt des profils.
        (new UserProfileRepository())->upsert(2, ['first_name' => 'Victor', 'last_name' => 'Perez']);

        self::assertSame(['Victor', 'Perez'], array_values($this->pdo->query('SELECT first_name, last_name FROM user_profiles WHERE user_id = 2')->fetch(PDO::FETCH_NUM)));
        self::assertNotContains(2, $this->unnamedIds());
        self::assertSame(2, LegacyDataNotice::counts(F::TENANT, $counter)['no_civil_name'], 'avis recompté sans attendre ses 10 minutes');

        // Un enregistrement qui ne touche ni au prénom ni au nom ne vide pas le cache (aucun recalcul inutile).
        $this->pdo->exec("UPDATE user_profiles SET first_name = 'Hugo', last_name = 'Hawk' WHERE user_id = 4");
        (new UserProfileRepository())->upsert(2, ['first_name' => 'Victor']);
        self::assertSame(2, LegacyDataNotice::counts(F::TENANT, $counter)['no_civil_name']);
    }

    public function testDataQualityFillRateUsesTheCharacterName(): void
    {
        $rows = array_map(static fn (array $r): array => $r + ['email' => 'x@example.test'], [
            // Telles que DatabaseDataQualitySource les lit désormais : prénom et nom du personnage, légaux à part.
            ['id' => 2, 'callsign' => 'Viper', 'first_name' => 'Victor', 'last_name' => 'Perez', 'legal_first_name' => null, 'legal_last_name' => null, 'birth_date' => null],
            ['id' => 3, 'callsign' => 'Jean', 'first_name' => '', 'last_name' => '', 'legal_first_name' => 'Jean', 'legal_last_name' => 'Dupont', 'birth_date' => '1990-01-01'],
        ]);
        $m = DataQualityAnalyzer::member($rows[0], F::TENANT);
        self::assertTrue($m['filled']['first_name']);
        self::assertTrue($m['filled']['last_name']);
        self::assertFalse(DataQualityAnalyzer::member($rows[1], F::TENANT)['filled']['first_name']);
        // L'indicatif qui reprend le prénom légal reste signalé.
        $findings = DataQualityAnalyzer::analyze($rows, F::TENANT, [], new \DateTimeImmutable('2026-11-15'))['findings'];
        $codesOf = static fn (int $uid): array => array_column(array_filter($findings, static fn (array $f): bool => (int) $f['user_id'] === $uid), 'code');
        self::assertContains('callsign_is_real_name', $codesOf(3));
        self::assertNotContains('callsign_is_real_name', $codesOf(2));

        $src = (string) file_get_contents(__DIR__ . '/../../app/Services/DataQuality/DatabaseDataQualitySource.php');
        self::assertStringContainsString("'up.first_name', 'up.last_name'", $src);
        self::assertStringContainsString("'uli.first_name AS legal_first_name'", $src);
    }
}
