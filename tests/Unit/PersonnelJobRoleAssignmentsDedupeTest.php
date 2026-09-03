<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelJobRoleAssignmentsDedupeTest extends TestCase
{
    public function testAssignmentMemberQueriesCannotBeMultipliedByProfileRows(): void
    {
        $repository = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Repositories/PersonnelJobRoleRepository.php'
        );

        if (!preg_match('/public function countUsersForJobRoleAssignments\b.*?^    \}/ms', $repository, $countMatch)) {
            self::fail('countUsersForJobRoleAssignments introuvable.');
        }

        self::assertStringContainsString('SELECT COUNT(*) FROM users u WHERE', $countMatch[0]);
        self::assertStringNotContainsString('JOIN personnel_profiles', $countMatch[0]);

        if (!preg_match('/private function buildAssignmentListQuery\b.*?^    \}/ms', $repository, $match)) {
            self::fail('buildAssignmentListQuery introuvable.');
        }

        self::assertStringContainsString('FROM users u', $match[0]);
        self::assertStringNotContainsString('JOIN personnel_profiles', $match[0]);
    }
}
