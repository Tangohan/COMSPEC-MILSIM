<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakAdminControlRepository as Fleet;
use App\Services\Atak\AtakGameAdminService;
use App\Support\AtakCompromiseNoticeMeta;
use App\Support\AtakCompromisePolicy as Policy;
use PHPUnit\Framework\TestCase;

/**
 * Compromission d'un ATAK : l'appareil compromis ne retrouve aucun accès, le joueur repart sur un nouvel identifiant
 * (docs/bugs/2026-11-08-atak-compromission-nouvel-appareil.md).
 */
final class AtakCompromisePolicyTest extends TestCase
{
    private const FIELD = ['compromise_state' => 'compromised', 'compromise_reason' => 'Compromission validée sur le terrain (C1, ATAK de TA1) : révocation du certificat', 'user_id' => 7];

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testPositionNeverClearsAFieldValidatedCompromise(): void
    {
        self::assertFalse(Policy::positionMayChange(self::FIELD, 'none'));
        self::assertFalse(Policy::positionMayChange(self::FIELD, 'captured'));
        self::assertFalse(Policy::positionMayChange(['compromise_state' => 'captured', 'compromise_reason' => null], 'none'));
    }

    public function testZeusEffectAndFieldReportCanStillBeClearedFromTheGame(): void
    {
        self::assertTrue(Policy::positionMayChange(['compromise_state' => 'captured', 'compromise_reason' => 'Action terrain / Zeus'], 'none'));
        self::assertTrue(Policy::positionMayChange(['compromise_state' => 'compromised', 'compromise_reason' => 'Remonté depuis le terrain'], 'none'));
    }

    public function testPositionRaisesASoundTerminalButIgnoresAnUnknownOrUnchangedOne(): void
    {
        self::assertTrue(Policy::positionMayChange(['compromise_state' => 'none'], 'captured'));
        self::assertFalse(Policy::positionMayChange(['compromise_state' => 'none'], 'none'));
        self::assertFalse(Policy::positionMayChange(null, 'captured'));
    }

    public function testCompromisedDeviceCannotRegisterAgainButANewIdentifierCan(): void
    {
        self::assertSame('terminal_compromised', Policy::registrationRefusal(self::FIELD, 7));
        self::assertNull(Policy::registrationRefusal(null, 7));
        self::assertNull(Policy::registrationRefusal(['compromise_state' => 'none', 'user_id' => 7], 7));
    }

    public function testIdentifierOfAnotherAccountIsRefused(): void
    {
        self::assertSame('terminal_uid_taken', Policy::registrationRefusal(['compromise_state' => 'none', 'user_id' => 7], 8));
        self::assertNull(Policy::registrationRefusal(['compromise_state' => 'none', 'user_id' => 0], 8));
        self::assertNull(Policy::registrationRefusal(['compromise_state' => 'none', 'user_id' => 7], 0));
        // Client plus ancien : il ne saurait pas changer d'identifiant.
        self::assertNull(Policy::registrationRefusal(['compromise_state' => 'none', 'user_id' => 7], 8, false));
        self::assertSame('terminal_compromised', Policy::registrationRefusal(self::FIELD, 7, false));
    }

    public function testControllersApplyThePolicyToEveryGamePath(): void
    {
        $api = $this->read('app/Controllers/Api/AtakRealismApiController.php');
        self::assertSame(1, substr_count($api, 'AtakCompromisePolicy::registrationRefusal('));
        // Certificat et changement d'état : mêmes refus depuis le jeu.
        self::assertSame(2, substr_count($api, '!AtakCompromisePolicy::gameMayChange('));
        self::assertStringContainsString('AtakCompromisePolicy::positionMayChange($existing, $compromisePos)', $this->read('app/Controllers/Api/AtakApiController.php'));
    }

    public function testCompromisedRowIsNeverMergedIntoTheNewDevice(): void
    {
        self::assertMatchesRegularExpression(
            '/function listOperatorPhysicalTwins.*?AtakCompromisePolicy::isCompromised\(\$row\).*?continue;.*?\$same = false;/s',
            $this->read('app/Repositories/AtakRealismRepository.php')
        );
    }

    public function testDeviceReplacedNoticeNamesTheOldTerminal(): void
    {
        self::assertSame('terminal=OW-1', AtakCompromiseNoticeMeta::build(['kind' => 'device_replaced', 'terminal_uid' => 'OW-1'], []));
    }

    /** @param array<string, mixed> $row */
    private function service(array $row, ?callable $setup = null): AtakGameAdminService
    {
        $fleet = $this->createMock(Fleet::class);
        $fleet->method('ready')->willReturn(true);
        $fleet->method('findTerminal')->willReturn($row);
        if ($setup !== null) {
            $setup($fleet);
        }

        return new AtakGameAdminService($fleet);
    }

    public function testManagerCannotDeclareHisOwnAtakFromTheGame(): void
    {
        $service = $this->service(['id' => 3, 'tenant_id' => 1, 'user_id' => 5, 'terminal_uid' => 'OW-5'], function ($fleet): void {
            $fleet->expects($this->never())->method('markCompromised');
        });
        $out = $service->act(1, 'manager', [5, 'SL'], 'compromise', ['terminal_id' => 3, 'state' => 'compromised']);

        self::assertFalse($out['ok']);
    }

    public function testReplaceDeviceActionGoesThroughTheFleetInTheCommunity(): void
    {
        $service = $this->service(['id' => 3, 'tenant_id' => 1, 'user_id' => 7, 'compromise_state' => 'compromised'], function ($fleet): void {
            $fleet->expects($this->once())->method('replaceDevice')->willReturn(true);
        });

        self::assertTrue($service->act(1, 'manager', [5, 'SL'], 'replace_device', ['terminal_id' => 3])['ok']);
    }

    public function testActionsStayIsolatedToTheCommunity(): void
    {
        $service = $this->service(['id' => 3, 'tenant_id' => 2, 'user_id' => 7, 'compromise_state' => 'compromised'], function ($fleet): void {
            $fleet->expects($this->never())->method('replaceDevice');
            $fleet->expects($this->never())->method('requestDataRepair');
        });

        self::assertFalse($service->act(1, 'manager', [5, 'SL'], 'replace_device', ['terminal_id' => 3])['ok']);
        self::assertFalse($service->act(1, 'manager', [5, 'SL'], 'repair_data', ['terminal_id' => 3])['ok']);
    }

    public function testSoundDeviceIsNotReplaced(): void
    {
        $fleet = (new \ReflectionClass(Fleet::class))->newInstanceWithoutConstructor();

        self::assertFalse($fleet->replaceDevice(['compromise_state' => 'none', 'tenant_id' => 1, 'user_id' => 7], 5, 'SL'));
    }

    public function testRepairAndNewDeviceNoticesExist(): void
    {
        self::assertArrayHasKey('device_replaced', Fleet::NOTICE_KINDS);
        self::assertArrayHasKey('repair_data', Fleet::NOTICE_KINDS);
        self::assertLessThanOrEqual(24, strlen('device_replaced'));
    }

    public function testSiteRoutesExistForBothScopes(): void
    {
        $routes = $this->read('routes/web.php');
        foreach (['/admin/parc-atak/remplacer', '/admin/parc-atak/reparer', '/back-office/atak/parc-atak/remplacer', '/back-office/atak/parc-atak/reparer'] as $path) {
            self::assertStringContainsString("'" . $path . "'", $routes);
        }
    }

    public function testModIsWiredForTheNewDeviceAndTheRepair(): void
    {
        $base = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
        self::assertStringContainsString('class deviceGuard {};', $this->read($base . 'config.cpp'));
        $boot = $this->read($base . 'XEH_postInitClient.sqf');
        self::assertStringContainsString('["boot"] call comspec_atak_native_fnc_deviceGuard', $boot);
        self::assertStringContainsString('["restorePage"] call comspec_atak_native_fnc_deviceGuard', $this->read($base . 'functions/ui/fn_displayLoad.sqf'));
        self::assertStringContainsString('["offlineOk", _sv]', $this->read($base . 'functions/network/fn_offline.sqf'));
        self::assertStringContainsString('["repair", "Réglages"]', $this->read($base . 'functions/pages/fn_pageSettings.sqf'));

        $guard = $this->read($base . 'functions/core/fn_deviceGuard.sqf');
        self::assertMatchesRegularExpression('/case "begin":.*?setVariable \["COMSPEC_ATAK_GuardStep".*?\[true\] call comspec_atak_native_fnc_saveProfile/s', $guard);
        // La réparation n'efface jamais le mode compromis ni l'identifiant du terminal.
        preg_match('/case "repair":.*?default \{/s', $guard, $repair);
        self::assertStringNotContainsString('CompromiseView', $repair[0] ?? 'CompromiseView');
        self::assertStringNotContainsString('terminal_uid', $repair[0] ?? 'terminal_uid');

        $comp = $this->read($base . 'functions/shell/fn_compromise.sqf');
        self::assertStringContainsString('"comspec_overwatch_terminal_gen", _gen + 1', $comp);
        self::assertStringContainsString('"COMSPEC_ATAK_PhoneGenSaved"', $comp);
        self::assertStringContainsString('"terminal_uid_taken"', $this->read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerAtakTerminal.sqf'));
        self::assertStringContainsString('"RepairLocalData"', $this->read('mod/UptoDate/COMSPECExtension/Extension.cs'));
    }

    public function testNewStringsAreTranslated(): void
    {
        $en = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        foreach (['RÉPARER MES DONNÉES', 'REMPLACER L\'APPAREIL', 'Nouvel appareil actif'] as $fr) {
            self::assertStringContainsString('["' . $fr . '", ', $en);
        }
        self::assertStringContainsString("'Remise en service' =>", $this->read('lang/en/site_auto.php'));
    }
}
