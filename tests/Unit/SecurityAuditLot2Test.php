<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Audit du back-office, lot 2 : réglages, position administrative et décorations derrière un droit interne. */
final class SecurityAuditLot2Test extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private static function src(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** Les huit écritures des réglages de la communauté vérifient un droit, et le type n'est changé que par l'administrateur. */
    public function testCommunitySettingsWritesCheckARight(): void
    {
        $s = self::src('app/Controllers/Admin/Organization/OrganizationSettingsController.php');
        self::assertSame(9, substr_count($s, 'deniedUnlessManager('), 'huit appels et la définition');
        self::assertStringContainsString("\$gate->allows('admin.organization') || \$gate->allows('admin.access')", $s);
        foreach (['updateType', 'update', 'inscriptionUpdate', 'storeLoginAccueilImage', 'replaceLoginAccueilImage', 'deleteLoginAccueilImage', 'moveLoginAccueilImage', 'saveLoginAccueilSlideshow'] as $method) {
            $body = substr($s, (int) strpos($s, 'public function ' . $method . '('));
            self::assertStringContainsString('deniedUnlessManager(', substr($body, 0, 700), $method);
        }
        $type = substr($s, (int) strpos($s, 'public function updateType('));
        self::assertStringContainsString(", true)) !== null", substr($type, 0, 700), 'updateType : administrateur de la communauté seulement');
    }

    /** L'ancienne route de position administrative a le même droit que la fiche du membre. */
    public function testLegacyDutyPositionRoutesNeedStatusRight(): void
    {
        $s = self::src('app/Controllers/Admin/Organization/UserAdminController.php');
        self::assertSame(2, substr_count($s, '\App\Support\EffectifsLmsAccess::canManageStatus(Gate::getInstance())'));
        $apply = substr($s, (int) strpos($s, 'public function applyDutyPosition('));
        self::assertStringContainsString('canManageStatus', substr($apply, 0, 1200));
        $backfill = substr($s, (int) strpos($s, 'public function backfillDutyPositions('));
        self::assertStringContainsString('canManageStatus', substr($backfill, 0, 1200));
    }

    /** Une décoration n'est attribuée qu'à un membre et avec une définition de la communauté. */
    public function testDecorationGrantChecksTheCommunity(): void
    {
        $s = self::src('app/Controllers/Admin/Organization/AwardReferentielController.php');
        $grant = substr($s, (int) strpos($s, 'public function grant('));
        self::assertStringContainsString('$this->users->findById($personnelId, $tenantId) === null || $this->definitions->find($tenantId, $definitionId) === null', substr($grant, 0, 1600));
        self::assertLessThan(strpos($grant, '$this->awards->create'), strpos($grant, 'findById($personnelId, $tenantId)'));
    }
}
