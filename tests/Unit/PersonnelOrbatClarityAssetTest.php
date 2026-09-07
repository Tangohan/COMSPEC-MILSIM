<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelCorrectionRequestService;
use PHPUnit\Framework\TestCase;

final class PersonnelOrbatClarityAssetTest extends TestCase
{
    public function testEditFileAndGuideDistinguishAssignmentFromJob(): void
    {
        $root = dirname(__DIR__, 2);
        $edit = (string) file_get_contents($root . '/views/personnel/edit.php');
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $tutorials = (string) file_get_contents($root . '/views/personnel/tutorials.php');
        $member = (string) file_get_contents($root . '/views/admin/effectifs_workspace/member.php');
        $catalog = PersonnelCorrectionRequestService::fieldCatalog();

        self::assertStringContainsString('Affectation — l’équipe', $edit);
        self::assertStringContainsString('Emploi — la fonction', $edit);
        self::assertStringContainsString('dans quelle unité', $edit);
        self::assertStringContainsString('ce que la personne fait', $edit);
        self::assertStringContainsString('L’emploi n’ouvre aucun droit d’accès', $edit);
        self::assertStringContainsString('Place dans l’équipe', $edit);
        self::assertStringContainsString('Emploi principal', $edit);
        self::assertStringContainsString('Modèles de fonction', $edit);
        self::assertStringContainsString("unit_assignments[' + idx + '][role_name]", $edit);
        self::assertStringContainsString("job_roles[' + idx + '][role_id]", $edit);

        self::assertStringNotContainsString('Rôle(s) métier (référentiel)', $edit);
        self::assertStringNotContainsString('Presets de fonction', $edit);
        self::assertStringNotContainsString('codes MOS', $edit);
        self::assertStringNotContainsString('migration à exécuter', $edit);

        self::assertStringContainsString('>Emploi</h2>', $file);
        self::assertStringContainsString('Emploi principal', $file);
        self::assertStringContainsString('L’équipe dans laquelle la personne est rattachée', $file);
        self::assertStringNotContainsString('Rôle(s) métier (référentiel)', $file);

        self::assertStringContainsString('L’équipe d’un côté, la fonction de l’autre', $tutorials);
        self::assertStringContainsString('À quoi ça sert', $tutorials);

        self::assertStringContainsString('L’affectation indique dans quelle équipe', $member);
        self::assertStringContainsString('L’emploi décrit la fonction tenue', $member);

        self::assertStringContainsString('Dans quelle équipe se trouve la personne', (string) ($catalog['unit_assignments']['help'] ?? ''));
        self::assertStringContainsString('distincte de l’équipe', (string) ($catalog['job_roles']['help'] ?? ''));
        self::assertStringNotContainsString('référentiel', strtolower((string) ($catalog['job_roles']['help'] ?? '')));
    }
}
