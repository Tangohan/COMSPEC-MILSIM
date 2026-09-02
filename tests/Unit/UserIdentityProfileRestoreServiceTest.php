<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Identity\UserIdentityProfileRestoreService;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserIdentityProfileRestoreServiceTest extends TestCase
{
    public function testRestoreCopiesAbsorbedPersonnelAndCivilProfileOntoEmptySurvivor(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT, display_name TEXT, callsign TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE user_identity_merges (
            id INTEGER PRIMARY KEY,
            survivor_user_id INTEGER,
            absorbed_user_id INTEGER,
            email TEXT,
            absorbed_tenant_id INTEGER,
            steam_collision INTEGER DEFAULT 0,
            absorbed_steam_id TEXT,
            absorbed_snapshot TEXT
        )');
        $pdo->exec('CREATE TABLE personnel_profiles (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            tenant_id INTEGER,
            character_name TEXT,
            callsign TEXT
        )');
        $pdo->exec('CREATE TABLE user_profiles (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            first_name TEXT,
            last_name TEXT,
            bio TEXT
        )');
        $pdo->exec("INSERT INTO users (id, tenant_id, email, display_name, callsign, status) VALUES
            (1, 1, 'a@example.test', 'Compte A', '', 'active'),
            (2, 7, 'merged+2@merged.invalid', 'Compte fusionné', 'FALCON', 'merged')");
        $pdo->exec("INSERT INTO user_identity_merges (survivor_user_id, absorbed_user_id, email, absorbed_tenant_id, absorbed_snapshot)
            VALUES (1, 2, 'a@example.test', 7, '{\"callsign\":\"FALCON\",\"status\":\"active\"}')");
        $pdo->exec("INSERT INTO personnel_profiles (id, user_id, tenant_id, character_name, callsign) VALUES
            (11, 1, 1, '', ''),
            (12, 2, 7, 'Jean Falcon', 'FALCON')");
        $pdo->exec("INSERT INTO user_profiles (id, user_id, first_name, last_name, bio) VALUES
            (21, 1, '', '', ''),
            (22, 2, 'Jean', 'Falcon', 'Ancien bio')");

        $out = (new UserIdentityProfileRestoreService($pdo))->restoreAll();
        self::assertSame(1, $out['merges']);
        self::assertGreaterThanOrEqual(1, $out['personnel']);
        self::assertGreaterThanOrEqual(1, $out['user_profiles']);

        $personnel = $pdo->query('SELECT * FROM personnel_profiles WHERE user_id = 1 AND tenant_id = 7')->fetch(PDO::FETCH_ASSOC);
        if (!$personnel) {
            $personnel = $pdo->query('SELECT * FROM personnel_profiles WHERE user_id = 1 ORDER BY id DESC')->fetch(PDO::FETCH_ASSOC);
        }
        self::assertNotFalse($personnel);
        self::assertSame('Jean Falcon', (string) $personnel['character_name']);
        self::assertSame('FALCON', (string) $personnel['callsign']);

        $civil = $pdo->query('SELECT * FROM user_profiles WHERE user_id = 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('Jean', (string) $civil['first_name']);
        self::assertSame('Falcon', (string) $civil['last_name']);
        self::assertSame('Ancien bio', (string) $civil['bio']);
    }

    public function testRestoreDoesNotOverwriteAlreadyFilledSurvivorFields(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, email TEXT)');
        $pdo->exec('CREATE TABLE user_identity_merges (
            id INTEGER PRIMARY KEY,
            survivor_user_id INTEGER,
            absorbed_user_id INTEGER,
            email TEXT,
            absorbed_tenant_id INTEGER,
            steam_collision INTEGER DEFAULT 0,
            absorbed_steam_id TEXT,
            absorbed_snapshot TEXT
        )');
        $pdo->exec('CREATE TABLE user_profiles (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            first_name TEXT,
            last_name TEXT
        )');
        $pdo->exec('INSERT INTO user_identity_merges (survivor_user_id, absorbed_user_id, email, absorbed_tenant_id)
            VALUES (1, 2, \'a@example.test\', 3)');
        $pdo->exec("INSERT INTO user_profiles (id, user_id, first_name, last_name) VALUES
            (1, 1, 'Keep', ''),
            (2, 2, 'Other', 'Name')");

        (new UserIdentityProfileRestoreService($pdo))->restoreAll();
        $row = $pdo->query('SELECT * FROM user_profiles WHERE user_id = 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('Keep', (string) $row['first_name']);
        self::assertSame('Name', (string) $row['last_name']);
    }
}
