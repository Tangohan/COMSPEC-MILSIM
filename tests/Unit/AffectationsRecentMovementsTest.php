<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelAssignmentRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Page Effectifs « Affectations » : panneau « Mouvements récents » (lecture seule) lu dans personnel_assignments.
 */
final class AffectationsRecentMovementsTest extends TestCase
{
    public function testListsPrimaryMovesOfTheCommunityWithPreviousUnitAndReason(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach ([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, status TEXT, display_name TEXT, callsign TEXT)',
            'CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT)',
            'CREATE TABLE personnel_assignments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, unit_id INTEGER, role_name TEXT,
                change_reason TEXT, is_primary INTEGER DEFAULT 1, started_at TEXT, ended_at TEXT, status TEXT, created_at TEXT)',
            "INSERT INTO users VALUES (7, 3, 'active', 'Viper', 'VIPER'), (9, 4, 'active', 'Ailleurs', '')",
            "INSERT INTO units VALUES (31, 3, 'Alpha'), (32, 3, 'Bravo'), (41, 4, 'Autre')",
            "INSERT INTO personnel_assignments (user_id, unit_id, role_name, change_reason, is_primary, started_at, ended_at, status, created_at) VALUES
                (7, 31, 'Membre', NULL, 1, '2026-09-01', '2026-10-01', 'inactive', '2026-09-01 10:00:00'),
                (7, 31, 'Chef', NULL, 1, '2026-10-01', '2026-10-20', 'inactive', '2026-10-01 10:00:00'),
                (7, 32, 'Chef', 'Renfort', 1, '2026-10-20', NULL, 'active', '2026-10-20 10:00:00'),
                (9, 41, 'Membre', NULL, 1, '2026-10-25', NULL, 'active', '2026-10-25 10:00:00')",
        ] as $sql) {
            $pdo->exec($sql);
        }

        $moves = (new PersonnelAssignmentRepository($pdo))->listRecentPrimaryMovementsForTenant(3, 30);

        self::assertCount(2, $moves, 'Le changement de rôle dans la même unité n’est pas une mutation ; l’autre communauté est exclue.');
        self::assertSame([32, 31, '2026-10-20', 'Renfort'], [$moves[0]['unit_id'], $moves[0]['from_unit_id'], $moves[0]['date'], $moves[0]['reason']]);
        self::assertSame([31, 0], [$moves[1]['unit_id'], $moves[1]['from_unit_id']]);
        self::assertSame('Viper', $moves[1]['display_name']);
    }
}
