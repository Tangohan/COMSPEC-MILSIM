<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CooperationDictionary as D;
use PHPUnit\Framework\TestCase;

final class CooperationConsentTest extends TestCase
{
    public function testThreeGroupsCoverEveryDataFamilyExactlyOnce(): void
    {
        $groups = D::dataSharingFamilyGroups();
        self::assertSame(['operational', 'personnel', 'documents'], array_keys($groups));
        $keys = D::dataSharingFamilyKeys();
        self::assertCount(13, $keys);
        self::assertSame($keys, array_values(array_unique($keys)));
        foreach ($keys as $k) {
            self::assertNotSame('Autre donnée listée', D::dataSharingFamilyLabel($k), $k);
        }
    }

    public function testSensitiveFamiliesAreIdentityQualificationsAndCertifications(): void
    {
        $sensitive = array_values(array_filter(D::dataSharingFamilyKeys(), [D::class, 'isSensitiveDataFamily']));
        self::assertSame(['identity', 'qualification', 'cert_excerpt'], $sensitive);
    }

    public function testServerRequiresAJustificationAndRespectsExpiry(): void
    {
        $root = dirname(__DIR__, 2);
        $c = (string) file_get_contents($root . '/app/Controllers/Web/InterteamMissionWebController.php');
        self::assertStringContainsString("isSensitiveDataFamily", $c);
        self::assertStringContainsString("mb_strlen(\$justification) < 10", $c);
        $repo = (string) file_get_contents($root . '/app/Repositories/InterteamMissionRepository.php');
        self::assertStringContainsString("return \$this->consentStatus(\$missionId, \$userId)['state'] === 'valid';", $repo);
        $mw = (string) file_get_contents($root . '/app/Middleware/InterteamCooperationConsentMiddleware.php');
        self::assertStringContainsString("'expired'", $mw);
        $view = (string) file_get_contents($root . '/views/back_office/cooperation/missions/consent.php');
        self::assertStringContainsString('autocomplete="one-time-code"', $view);
        self::assertStringContainsString('data-consent-all', $view);
    }
}
