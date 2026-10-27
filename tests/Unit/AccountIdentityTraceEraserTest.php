<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Account\AccountIdentityTraceEraser;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Un compte supprimé ne doit rien laisser qu’un nouveau compte ouvert avec la même adresse
 * pourrait reprendre : compte de jeu, rôles du site, invitations, candidatures.
 */
final class AccountIdentityTraceEraserTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE athena_accounts (id INTEGER PRIMARY KEY, email TEXT, steam_id TEXT)');
        $this->pdo->exec('CREATE TABLE account_tenant_memberships (id INTEGER PRIMARY KEY, account_id INTEGER, tenant_id INTEGER, user_id INTEGER)');
        $this->pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, account_id INTEGER, user_id INTEGER)');
        $this->pdo->exec('CREATE TABLE atak_trusted_devices (id INTEGER PRIMARY KEY, account_id INTEGER, terminal_uid TEXT)');
        $this->pdo->exec('CREATE TABLE site_role_assignments (id INTEGER PRIMARY KEY, email_normalized TEXT, role_id INTEGER)');
        $this->pdo->exec('CREATE TABLE community_invitations (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE platform_account_bans (id INTEGER PRIMARY KEY, email_norm TEXT)');

        $this->pdo->exec("INSERT INTO users (id, email) VALUES (10, 'parti@x.fr'), (20, 'reste@x.fr')");
        $this->pdo->exec("INSERT INTO athena_accounts (id, email) VALUES (1, 'parti@x.fr'), (2, 'reste@x.fr')");
        $this->pdo->exec('INSERT INTO account_tenant_memberships (account_id, tenant_id, user_id) VALUES (1, 2, 10), (2, 2, 20)');
        $this->pdo->exec('INSERT INTO game_sessions (account_id, user_id) VALUES (1, 10), (2, 20)');
        $this->pdo->exec("INSERT INTO atak_trusted_devices (account_id, terminal_uid) VALUES (1, 'S7-A'), (2, 'S7-B')");
        $this->pdo->exec("INSERT INTO site_role_assignments (email_normalized, role_id) VALUES ('parti@x.fr', 5), ('reste@x.fr', 5)");
        $this->pdo->exec("INSERT INTO community_invitations (tenant_id, email, status) VALUES (2, 'Parti@x.fr', 'pending'), (2, 'parti@x.fr', 'accepted')");
        $this->pdo->exec("INSERT INTO platform_account_bans (email_norm) VALUES ('parti@x.fr')");
    }

    private function countRows(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    public function testEraseRemovesEverythingKeyedByEmailOrGameAccount(): void
    {
        $report = (new AccountIdentityTraceEraser($this->pdo))->erase('parti@x.fr', [10]);

        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM athena_accounts WHERE email = 'parti@x.fr'"));
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM account_tenant_memberships WHERE account_id = 1'));
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM game_sessions WHERE account_id = 1'));
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM atak_trusted_devices WHERE account_id = 1'));
        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM site_role_assignments WHERE email_normalized = 'parti@x.fr'"));
        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM community_invitations WHERE status = 'pending'"));
        self::assertSame(1, $report['athena_accounts']);
    }

    public function testEraseKeepsOtherPeopleAndProtections(): void
    {
        (new AccountIdentityTraceEraser($this->pdo))->erase('parti@x.fr', [10]);

        self::assertSame(1, $this->countRows("SELECT COUNT(*) FROM athena_accounts WHERE email = 'reste@x.fr'"));
        self::assertSame(1, $this->countRows('SELECT COUNT(*) FROM atak_trusted_devices WHERE account_id = 2'));
        self::assertSame(1, $this->countRows("SELECT COUNT(*) FROM site_role_assignments WHERE email_normalized = 'reste@x.fr'"));
        self::assertSame(1, $this->countRows("SELECT COUNT(*) FROM community_invitations WHERE status = 'accepted'"), 'L’historique d’invitation acceptée reste à la communauté.');
        self::assertSame(1, $this->countRows('SELECT COUNT(*) FROM platform_account_bans'), 'Un bannissement survit à la suppression du compte.');
    }

    public function testOrphanGameAccountIsErasedOnlyWithoutLiveMember(): void
    {
        $eraser = new AccountIdentityTraceEraser($this->pdo);

        self::assertSame(0, $eraser->eraseOrphanGameAccount('parti@x.fr'), 'La fiche 10 est encore vivante.');

        // Suppression faite avant ce correctif : la fiche est anonymisée, le compte de jeu est resté.
        $this->pdo->exec("UPDATE users SET email = 'deleted-10@deleted.invalid', deleted_at = '2026-10-01' WHERE id = 10");

        self::assertSame(1, $eraser->eraseOrphanGameAccount('parti@x.fr'));
        self::assertSame(0, $this->countRows("SELECT COUNT(*) FROM athena_accounts WHERE email = 'parti@x.fr'"));
        self::assertSame(0, $this->countRows('SELECT COUNT(*) FROM atak_trusted_devices WHERE account_id = 1'));
    }
}