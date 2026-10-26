<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakAdminControlRepository as Fleet;
use App\Services\Atak\AtakGameAdminService;
use PHPUnit\Framework\TestCase;

/**
 * App Admin de l'ATAK natif : parc de la communauté géré en jeu par la gestion, ATAK de la partie et debug de l'IA.
 */
final class AtakGameAdminTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testGameApiIsWiredFromSiteToPhone(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("'/api/atak/game-admin/fleet'", $routes);
        self::assertStringContainsString("'/api/atak/game-admin/action'", $routes);
        $ext = $this->read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('"GameAdmin.Fleet"', $ext);
        self::assertStringContainsString('/api/atak/game-admin/action', $ext);
        $client = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/admin/fn_adminClient.sqf');
        self::assertStringContainsString('"GameAdmin.Fleet"', $client);
        self::assertStringContainsString('"GameAdmin.Action"', $client);
        $config = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/config.cpp');
        self::assertStringContainsString('class pageAdmin {}', $config);
        self::assertStringContainsString('function="comspec_atak_native_fnc_pageAdmin"', $config);
        self::assertFileExists(dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/data/app_admin.paa');
    }

    public function testManagerNeedsHisOwnGameSession(): void
    {
        // La clé d'accès commune du serveur ne dit pas qui joue : la session de jeu du gestionnaire est exigée.
        $api = $this->read('app/Controllers/Api/AtakApiController.php');
        self::assertMatchesRegularExpression('/function gameAdminCaller.*?ComspecApiKeyAuth::matchedUserId\(\).*?session_required/s', $api);
        self::assertNull((new AtakGameAdminService())->role(3, null));
        self::assertNull((new AtakGameAdminService())->role(0, 42));
    }

    public function testInGameActionsStayWithinCommunityManagement(): void
    {
        // Retrait définitif et blocage toutes communautés restent sur le site.
        self::assertNotContains('retire', AtakGameAdminService::ACTIONS);
        self::assertNotContains('block_all', AtakGameAdminService::ACTIONS);
        $service = $this->read('app/Services/Atak/AtakGameAdminService.php');
        self::assertStringContainsString("'tenant_id' => \$tenantId,", $service);
        self::assertStringContainsString("\$role !== 'platform'", $service);
        self::assertArrayHasKey('ingame', Fleet::COMPROMISE_SOURCES);
    }

    public function testInGameRightsAreCheckedByTheServer(): void
    {
        $main = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
        self::assertStringContainsString('comspec_atak_native_fnc_adminAllowed', $this->read($main . 'functions/admin/fn_adminServer.sqf'));
        self::assertStringContainsString('comspec_atak_native_fnc_adminAllowed', $this->read($main . 'functions/zeus/fn_zeusServer.sqf'));
        self::assertStringContainsString('["init"] call comspec_atak_native_fnc_adminServer;', $this->read($main . 'XEH_postInitServer.sqf'));
        self::assertStringContainsString('comspec_atak_native_admin_uids', $this->read($main . 'XEH_preInit.sqf'));
        // L'app n'apparaît qu'à qui y a droit.
        self::assertStringContainsString('"Admin"', $this->read($main . 'functions/ui/fn_appVisible.sqf'));
    }
}
