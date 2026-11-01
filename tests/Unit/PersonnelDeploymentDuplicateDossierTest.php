<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelDeploymentRepository;
use PHPUnit\Framework\TestCase;

/**
 * Page « Déploiement du personnel » : un membre avec plusieurs dossiers (dossiers vides laissés sans communauté,
 * ou un par communauté) apparaissait plusieurs fois. Une seule ligne doit rester, avec le dossier complet.
 */
final class PersonnelDeploymentDuplicateDossierTest extends TestCase
{
    public function testKeepsTheCompleteDossierWhenAMemberHasGhostDossiers(): void
    {
        $profiles = PersonnelDeploymentRepository::preferredProfilesByUser([
            7 => [
                ['id' => 10, 'user_id' => 7, 'tenant_id' => null, 'blood_type' => null, 'matricule_internal' => null, 'primary_unit_id' => null],
                ['id' => 11, 'user_id' => 7, 'tenant_id' => 3, 'blood_type' => 'O+', 'matricule_internal' => 'TA1', 'primary_unit_id' => 42, 'deployable' => 1],
                ['id' => 12, 'user_id' => 7, 'tenant_id' => 3, 'blood_type' => '', 'matricule_internal' => '', 'primary_unit_id' => null],
            ],
        ], 3);

        self::assertSame(11, $profiles[7]['id']);

        $rows = PersonnelDeploymentRepository::mergeProfiles(
            [['user_id' => 7, 'display_name' => 'Jake'], ['user_id' => 7, 'display_name' => 'Jake'], ['user_id' => 8, 'display_name' => 'Liam']],
            $profiles,
            [42 => 'Alpha']
        );

        self::assertCount(2, $rows);
        self::assertSame('O+', $rows[0]['profile_blood_type']);
        self::assertSame('TA1', $rows[0]['matricule_internal']);
        self::assertSame('Alpha', $rows[0]['unit_name']);
        self::assertNull($rows[1]['unit_name']);
        self::assertNull($rows[1]['matricule_internal']);
    }
}
