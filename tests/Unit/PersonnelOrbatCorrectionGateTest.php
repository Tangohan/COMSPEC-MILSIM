<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelCorrectionRequestService;
use PHPUnit\Framework\TestCase;

final class PersonnelOrbatCorrectionGateTest extends TestCase
{
    public function testCanonicalUnitAssignmentsIgnoreEmptyAndKeepOnePrimary(): void
    {
        $json = PersonnelCorrectionRequestService::canonicalizeUnitAssignments([
            ['unit_id' => 0, 'role_name' => 'X', 'is_primary' => true],
            ['unit_id' => 7, 'role_name' => 'Chef', 'is_primary' => false],
            ['unit_id' => 7, 'role_name' => 'Doublon', 'is_primary' => true],
            ['unit_id' => 3, 'role_name' => 'Membre', 'is_primary' => false],
        ]);
        $rows = PersonnelCorrectionRequestService::decodeAssignmentRows($json);

        self::assertCount(2, $rows);
        self::assertSame(7, (int) $rows[0]['unit_id']);
        self::assertSame(1, (int) $rows[0]['is_primary']);
        self::assertSame('Chef', $rows[0]['role_name']); // le premier libellé est conservé, le drapeau principal du doublon aussi
        self::assertSame(3, (int) $rows[1]['unit_id']);
        self::assertSame(0, (int) $rows[1]['is_primary']);
    }

    public function testCanonicalJobRolesNormalizeAliases(): void
    {
        $json = PersonnelCorrectionRequestService::canonicalizeJobRoles([
            ['personnel_job_role_id' => 12, 'role_detail' => 'JTAC', 'is_primary' => true],
            ['role_id' => 4, 'detail' => '', 'is_primary' => false],
        ]);
        $rows = PersonnelCorrectionRequestService::decodeJobRoleRows($json);

        self::assertCount(2, $rows);
        self::assertSame(12, (int) $rows[0]['role_id']);
        self::assertSame('JTAC', $rows[0]['detail']);
        self::assertSame(1, (int) $rows[0]['is_primary']);
        self::assertSame(4, (int) $rows[1]['role_id']);
    }

    public function testOrbatKeysStayOutOfSensitiveAccountFields(): void
    {
        $labels = PersonnelCorrectionRequestService::fieldLabels();
        foreach (PersonnelCorrectionRequestService::ORBAT_KEYS as $key) {
            self::assertArrayHasKey($key, $labels);
        }
        self::assertArrayNotHasKey('email', $labels);
        self::assertArrayNotHasKey('clearance_level', $labels);
    }
}
