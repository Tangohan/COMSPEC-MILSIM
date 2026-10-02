<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CooperationSearchApiAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testEndpointsAreBehindTheCooperationMiddlewareAndScoped(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("'/back-office/cooperation/api/tenants/search', [\\App\\Controllers\\Web\\CooperationSearchApiController::class, 'tenants'], \$interteamMw", $routes);
        self::assertStringContainsString("'/back-office/cooperation/api/members/search', [\\App\\Controllers\\Web\\CooperationSearchApiController::class, 'members'], \$interteamMw", $routes);

        $c = $this->read('app/Controllers/Web/CooperationSearchApiController.php');
        self::assertStringContainsString('tenantCanPilotMission($missionId, $tenantId)', $c);
        self::assertStringContainsString("Response::json(['error' => 'forbidden'], 403)", $c);
        // Membres : uniquement l’unité connectée, jamais d’adresse e-mail renvoyée.
        self::assertStringContainsString('listForTenant($tenantId, $q', $c);
        self::assertStringNotContainsString("'email'", $c);
        self::assertStringContainsString('Cache-Control', $c);
    }

    public function testComboboxIsProgressiveAndAccessible(): void
    {
        $js = $this->read('public/assets/js/cooperation/combobox.js');
        foreach (["role: 'combobox'", "'aria-expanded'", "'aria-controls'", 'aria-activedescendant', "role: 'listbox'", 'DEBOUNCE_MS = 250', 'Aucun résultat'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        $show = $this->read('views/back_office/cooperation/missions/show.php');
        self::assertStringContainsString('data-coop-combobox', $show);
        self::assertStringContainsString('data-multiple-name="partner_tenant_ids[]"', $show);
        self::assertStringContainsString('skeleton.php', $this->read('views/back_office/cooperation/missions/_combobox_assets.php'));
    }
}
