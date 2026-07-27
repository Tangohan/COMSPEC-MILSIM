<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Account\AccountPurgeService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Suppression définitive : ce qui décrit la personne doit disparaître, ce qui appartient
 * à des tiers doit survivre.
 *
 * La distinction n’est pas cosmétique. Supprimer les lignes que la personne a *signées*
 * effacerait des pièces du dossier d’autres membres — la sanction prononcée par un cadre
 * partirait avec le départ du cadre. Ces tests verrouillent la frontière.
 *
 * Le schéma de travail est un SQLite jetable : la purge s’appuie sur l’introspection du
 * schéma, donc elle se rejoue telle quelle sur n’importe quelle base.
 */
final class AccountPurgeServiceTest extends TestCase
{
    public function testOwnershipColumnsAreClassifiedForDeletion(): void
    {
        foreach (['user_id', 'target_user_id', 'author_user_id', 'owner_user_id', 'submitter_user_id'] as $column) {
            self::assertSame('ownership', AccountPurgeService::classifyColumn($column, true), $column);
        }
    }

    public function testAttributionColumnsAreClassifiedForDetaching(): void
    {
        foreach (['created_by_user_id', 'actor_user_id', 'deleted_by', 'assigned_by'] as $column) {
            self::assertSame('attribution', AccountPurgeService::classifyColumn($column, true), $column);
        }
    }

    /**
     * Le schéma invente régulièrement des noms de signataire. Une liste nominative les
     * manquerait en silence et laisserait une référence pointée sur un compte effacé.
     */
    public function testUnlistedByUserIdColumnsAreStillDetached(): void
    {
        foreach (['issued_by_user_id', 'escalated_by_user_id', 'archived_by_user_id'] as $column) {
            self::assertSame('attribution', AccountPurgeService::classifyColumn($column, true), $column);
        }
    }

    /**
     * `sort_by` et consorts sont du texte : les mettre à NULL casserait des écrans. D’où
     * un suffixe `_by_user_id`, et non `_by`.
     */
    public function testTextualByColumnsAreLeftAlone(): void
    {
        self::assertNull(AccountPurgeService::classifyColumn('sort_by', true));
        self::assertNull(AccountPurgeService::classifyColumn('ordered_by', true));
    }

    public function testNotNullAttributionColumnIsLeftAlone(): void
    {
        // Un UPDATE ... = NULL échouerait sur la contrainte : mieux vaut ne pas y toucher.
        self::assertNull(AccountPurgeService::classifyColumn('created_by_user_id', false));
        self::assertSame('ownership', AccountPurgeService::classifyColumn('user_id', false));
    }

    public function testUnrelatedColumnsAreNeverTouched(): void
    {
        foreach (['id', 'tenant_id', 'unit_id', 'steam_id', 'title', 'created_at'] as $column) {
            self::assertNull(AccountPurgeService::classifyColumn($column, true), $column);
        }
    }

    public function testPurgeRemovesTheAccountAndEverythingDescribingIt(): void
    {
        $pdo = $this->schema();
        $report = (new AccountPurgeService($pdo))->purge(29, [30]);

        self::assertTrue($report['ok'], implode(' | ', $report['errors']));
        self::assertSame([29, 30], $report['purged_user_ids']);
        self::assertSame([], $report['errors']);

        self::assertSame(0, $this->count($pdo, 'SELECT COUNT(*) FROM users WHERE id IN (29, 30)'));
        self::assertSame(0, $this->count($pdo, 'SELECT COUNT(*) FROM user_profiles WHERE user_id IN (29, 30)'));
        self::assertSame(0, $this->count($pdo, 'SELECT COUNT(*) FROM forum_posts WHERE author_user_id = 29'));
    }

    public function testPurgeLeavesThirdPartyRecordsIntact(): void
    {
        $pdo = $this->schema();
        (new AccountPurgeService($pdo))->purge(29, [30]);

        self::assertSame(1, $this->count($pdo, 'SELECT COUNT(*) FROM users WHERE id = 40'));
        self::assertSame(1, $this->count($pdo, 'SELECT COUNT(*) FROM user_profiles WHERE user_id = 40'));
        self::assertSame(1, $this->count($pdo, 'SELECT COUNT(*) FROM forum_posts WHERE author_user_id = 40'));
        self::assertSame(40, (int) $pdo->query('SELECT created_by_user_id FROM documents WHERE id = 2')->fetchColumn());
    }

    /**
     * Le cas qui justifie tout le service : une sanction prononcée contre quelqu’un
     * d’autre reste au dossier de ce dernier, mais n’attribue plus rien.
     */
    public function testSanctionIssuedAgainstSomeoneElseSurvivesButIsDetached(): void
    {
        $pdo = $this->schema();
        (new AccountPurgeService($pdo))->purge(29, [30]);

        self::assertSame(0, $this->count($pdo, 'SELECT COUNT(*) FROM member_sanctions WHERE id = 1'));
        self::assertSame(1, $this->count($pdo, 'SELECT COUNT(*) FROM member_sanctions WHERE id = 2'));
        self::assertNull($pdo->query('SELECT issued_by_user_id FROM member_sanctions WHERE id = 2')->fetchColumn());
    }

    public function testPreviewCountsWithoutDeletingAnything(): void
    {
        $pdo = $this->schema();
        $service = new AccountPurgeService($pdo);

        $preview = $service->preview(29, [30]);

        self::assertSame(2, $preview['rows']['user_profiles'] ?? 0);
        self::assertSame(3, $this->count($pdo, 'SELECT COUNT(*) FROM user_profiles'));
        self::assertSame(3, $this->count($pdo, 'SELECT COUNT(*) FROM users'));
    }

    public function testPurgeAnonymizedAccountsClearsTheBacklog(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, email TEXT, display_name TEXT)');
        $pdo->exec('CREATE TABLE user_profiles (id INTEGER PRIMARY KEY, user_id INT, bio TEXT)');
        $pdo->exec(
            "INSERT INTO users (id, tenant_id, email, display_name) VALUES
             (1, 1, 'deleted-29-170000@deleted.invalid', 'Compte supprimé'),
             (2, 1, 'deleted-31-170001@deleted.invalid', 'Compte supprimé'),
             (3, 1, 'vivant@example.com', 'Vivant')"
        );
        $pdo->exec('INSERT INTO user_profiles (id, user_id, bio) VALUES (1, 1, "orphelin"), (2, 3, "vivant")');

        $result = (new AccountPurgeService($pdo))->purgeAnonymizedAccounts();

        self::assertTrue($result['ok'], implode(' | ', $result['errors']));
        self::assertSame(2, $result['purged']);
        self::assertSame(0, $result['failed']);
        self::assertSame(0, $this->count($pdo, "SELECT COUNT(*) FROM users WHERE email LIKE '%@deleted.invalid'"));
        self::assertSame(1, $this->count($pdo, 'SELECT COUNT(*) FROM users WHERE id = 3'));
        self::assertSame(1, $this->count($pdo, 'SELECT COUNT(*) FROM user_profiles WHERE user_id = 3'));
    }

    public function testInvalidOrUnknownAccountIsReportedAsFailure(): void
    {
        $pdo = $this->schema();
        $service = new AccountPurgeService($pdo);

        self::assertFalse($service->purge(0)['ok']);
        self::assertSame([], $service->purge(0)['purged_user_ids']);
        self::assertFalse($service->purge(99999)['ok']);
        self::assertSame(3, $this->count($pdo, 'SELECT COUNT(*) FROM users'));
    }

    private function count(PDO $pdo, string $sql): int
    {
        return (int) $pdo->query($sql)->fetchColumn();
    }

    private function schema(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, email TEXT, display_name TEXT)');
        $pdo->exec('CREATE TABLE user_profiles (id INTEGER PRIMARY KEY, user_id INT, bio TEXT)');
        $pdo->exec('CREATE TABLE forum_posts (id INTEGER PRIMARY KEY, author_user_id INT, body TEXT)');
        $pdo->exec('CREATE TABLE member_sanctions (id INTEGER PRIMARY KEY, target_user_id INT, issued_by_user_id INT, motif TEXT)');
        $pdo->exec('CREATE TABLE documents (id INTEGER PRIMARY KEY, title TEXT, created_by_user_id INT)');
        $pdo->exec('CREATE TABLE audit_log (id INTEGER PRIMARY KEY, action TEXT, actor_user_id INT)');
        $pdo->exec('CREATE TABLE invoices (id INTEGER PRIMARY KEY, amount INT, created_by_user_id INT NOT NULL)');
        $pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, name TEXT)');

        $pdo->exec(
            "INSERT INTO users (id, tenant_id, email, display_name) VALUES
             (29, 1, 'jean@example.com', 'Jean'),
             (30, 2, 'jean@example.com', 'Jean (2e communauté)'),
             (40, 1, 'autre@example.com', 'Quelqu''un d''autre')"
        );
        $pdo->exec('INSERT INTO user_profiles (id, user_id, bio) VALUES (1, 29, "a"), (2, 30, "b"), (3, 40, "c")');
        $pdo->exec('INSERT INTO forum_posts (id, author_user_id, body) VALUES (1, 29, "A"), (2, 40, "B")');
        $pdo->exec(
            'INSERT INTO member_sanctions (id, target_user_id, issued_by_user_id, motif) VALUES
             (1, 29, 40, "reçue par 29"),
             (2, 40, 29, "prononcée par 29 contre 40")'
        );
        $pdo->exec('INSERT INTO documents (id, title, created_by_user_id) VALUES (1, "SOP", 29), (2, "OPORD", 40)');
        $pdo->exec('INSERT INTO audit_log (id, action, actor_user_id) VALUES (1, "login", 29), (2, "login", 40)');
        $pdo->exec('INSERT INTO invoices (id, amount, created_by_user_id) VALUES (1, 100, 29)');
        $pdo->exec('INSERT INTO units (id, name) VALUES (1, "1re section")');

        return $pdo;
    }
}
