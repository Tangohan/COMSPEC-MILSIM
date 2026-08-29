<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ExtraCallsignsHelperTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';
    }

    public function testSlotsAtLeastFive(): void
    {
        self::assertGreaterThanOrEqual(5, personnel_extra_callsign_slots());
    }

    public function testNormalizeFromArrayDedupesAndSkipsPrimary(): void
    {
        $list = personnel_normalize_extra_callsigns(
            ['Alpha', ' alpha ', 'Bravo', '', 'Primary', 'Charlie', 'Delta', 'Echo', 'Foxtrot'],
            'Primary',
            5,
            100
        );
        self::assertSame(['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'], $list);
    }

    public function testDecodeJsonList(): void
    {
        $json = json_encode(['Wolf', 'Fox'], JSON_UNESCAPED_UNICODE);
        self::assertSame(['Wolf', 'Fox'], personnel_decode_extra_callsigns($json));
        self::assertSame([], personnel_decode_extra_callsigns(null));
        self::assertSame(['A'], personnel_decode_extra_callsigns(['A', '', 'A']));
    }

    public function testEditViewOffersFiveExtraSlots(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/edit.php');
        self::assertStringContainsString('name="extra_callsigns[]"', $view);
        self::assertStringContainsString('Indicatifs supplémentaires', $view);
        self::assertStringContainsString('extraCallsignSlots', $view);
    }

    public function testPreferencesViewOffersExtraSlots(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/account/preferences.php');
        self::assertStringContainsString('name="extra_callsigns[]"', $view);
        self::assertStringContainsString('Indicatifs supplémentaires', $view);
    }

    public function testRepositoryAllowsExtraCallsignsColumn(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/PersonnelProfileRepository.php');
        self::assertStringContainsString("'extra_callsigns_json'", $src);
    }

    public function testMigrationDocumentsColumn(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/20260829170000_extra_callsigns_json.sql');
        self::assertStringContainsString('extra_callsigns_json', $sql);
        $boot = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/personnel_personal_dossier_enhancements_migration.php');
        self::assertStringContainsString('extra_callsigns_json', $boot);
    }
}
