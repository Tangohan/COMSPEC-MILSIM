<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\AtakMedicalRosterService;
use PHPUnit\Framework\TestCase;

/**
 * Fiches médicales de la communauté dans l'app Médical de l'ATAK natif (groupe sanguin, poids, allergies…).
 */
final class AtakMedicalRosterTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testRouteNeedsThePlayersOwnGameSession(): void
    {
        self::assertStringContainsString("'/api/atak/medical-roster', [AtakApiController::class, 'medicalRoster']", $this->read('routes/web.php'));
        // La clé d'accès commune du serveur ne dit pas qui joue.
        $api = $this->read('app/Controllers/Api/AtakApiController.php');
        self::assertMatchesRegularExpression('/function medicalRoster.*?ComspecApiKeyAuth::matchedUserId\(\).*?session_required.*?->role\(.*?forbidden/s', $api);
    }

    public function testNoRightWithoutCommunityOrAccount(): void
    {
        $service = new AtakMedicalRosterService();
        self::assertNull($service->role(0, 42));
        self::assertNull($service->role(3, null));
        self::assertNull($service->role(3, 0));
        self::assertSame([], $service->rows(0));
    }

    public function testMedicalJobsAreRecognised(): void
    {
        foreach (['Médecin', 'Combat Medic', 'Infirmier-chef', 'Hospital Corpsman', 'CLS', 'Combat Lifesaver', 'Service de santé', 'Pararescue (PJ)', 'medic_68w', 'Secouriste'] as $job) {
            self::assertTrue(AtakMedicalRosterService::isMedicalJob($job), $job);
        }
        foreach (['', 'Tireur d\'élite', 'Chef de groupe', 'Pilote', 'Cycles', 'Radio'] as $job) {
            self::assertFalse(AtakMedicalRosterService::isMedicalJob($job), $job);
        }
    }

    public function testOnlyReadsTheSessionCommunityMembers(): void
    {
        $service = $this->read('app/Services/Atak/AtakMedicalRosterService.php');
        self::assertStringContainsString("listForTenant(\$tenantId, null, 'active')", $service);
        self::assertStringContainsString('medicalForUsers($tenantId, $ids)', $service);
        // La porte RBAC est rendue telle quelle après le contrôle.
        self::assertStringContainsString('$gate->setFullRbacState($before[0], $before[1]);', $service);
        self::assertStringNotContainsString('->update(', $service);
    }

    public function testPhoneReadsRecordsThroughLinkAndKeepsThemOnThePc(): void
    {
        $ext = $this->read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('if (function == "MedicalRoster" || function == "MedicalRosterInfo") return MedicalRosterCommand(function, args, token);', $ext);
        self::assertStringContainsString(',MedicalRoster";', $ext);
        $cache = $this->read('mod/UptoDate/COMSPECExtension/Extension_MedicalRoster.cs');
        self::assertStringContainsString('"medical_roster.json"', $cache);
        self::assertStringContainsString('/api/atak/medical-roster', $cache);
        // Droit retiré sur le site : la copie du PC est effacée.
        self::assertMatchesRegularExpression('/forbidden.*?MedicalRosterForget\(\)/s', $cache);
        // La DLL de l'ATAK natif compile le fichier aussi (Extension.cs y appelle MedicalRosterCommand).
        self::assertStringContainsString('Extension_MedicalRoster.cs', $this->read('mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj'));
        $main = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
        self::assertStringContainsString('class medRoster {};', $this->read($main . 'config.cpp'));
        $page = $this->read($main . 'functions/pages/fn_pageMedical.sqf');
        self::assertStringContainsString('["DOSSIERS", "RECORDS"] call _tabBtn', $page);
        self::assertStringContainsString('["load", true] call comspec_atak_native_fnc_medRoster;', $page);
        self::assertStringContainsString('["find", _uid] call comspec_atak_native_fnc_medRoster;', $page);
        self::assertStringContainsString('"MedicalRoster", [str _start, _mode]', $this->read($main . 'functions/pages/fn_medRoster.sqf'));
    }
}
