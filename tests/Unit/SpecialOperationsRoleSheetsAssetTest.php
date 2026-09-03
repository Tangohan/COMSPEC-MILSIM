<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SpecialOperationsRoleSheetsAssetTest extends TestCase
{
    public function testRoleSheetsCoverRequestedProfilesAndSourceCaveats(): void
    {
        $path = dirname(__DIR__, 2) . '/docs/REFERENTIEL-METIERS-OPERATIONS-SPECIALES-US.md';

        self::assertFileExists($path);
        $document = (string) file_get_contents($path);

        foreach (['AFSC 1Z2X1', 'AFSC 1Z1X1', 'AFSC 1Z3X1', '160th SOAR', 'B Squadron'] as $profile) {
            self::assertStringContainsString($profile, $document);
        }

        self::assertStringContainsString('DA PAM 611-21', $document);
        self::assertStringContainsString('JP 3-05', $document);
        self::assertStringContainsString('source ouverte historique', $document);
        self::assertStringContainsString('Tous les TACP ne sont pas automatiquement JTAC', $document);
        self::assertStringContainsString('n’est pas un MOS', $document);
    }
}
