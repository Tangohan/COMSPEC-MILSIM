<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OperatorBackOfficeClarityAssetTest extends TestCase
{
    public function testOperatorOverviewShowsPersonalSituationWithoutAdminJargon(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/organization/operator_overview.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        $nav = (string) file_get_contents($root . '/views/partials/ath_sidebar_nav.php');
        $sidebar = (string) file_get_contents($root . '/views/partials/back_office_sidebar.php');

        self::assertStringContainsString('upcomingForTenantWithUserRsvp', $controller);
        self::assertStringContainsString('listActiveForUser', $controller);
        self::assertStringContainsString('operatorOnboardingRemaining', $controller);
        self::assertStringContainsString('operatorFollowup', $controller);
        self::assertStringContainsString('OPÉRATEUR · MA SITUATION', $controller);

        self::assertStringContainsString('Ce qui vous concerne', $view);
        self::assertStringContainsString('Prochaines manœuvres', $view);
        self::assertStringContainsString('Ma liaison ATAK', $view);
        self::assertStringContainsString('Communauté', $view);
        self::assertStringContainsString('Mes démarches', $view);
        self::assertStringContainsString('Votre parcours', $view);
        self::assertStringContainsString('Ouvrir le suivi complet', $view);
        self::assertStringContainsString('url(\'personnel/me\')', $view);
        self::assertStringContainsString('url(\'evenements\')', $view);
        self::assertStringContainsString('url(\'boite-reception\')', $view);
        self::assertStringContainsString('En service', $view);
        self::assertStringContainsString('Il ne donne aucun droit d’administration.', $view);

        self::assertStringNotContainsString('>Tenant<', $view);
        self::assertStringNotContainsString('Mes données RP', $view);
        self::assertStringNotContainsString('pairing_token', $view);
        self::assertStringNotContainsString('terminal_uid', $view);
        self::assertStringNotContainsString('ext. <?= $value', $view);

        self::assertStringContainsString("'key' => 'ma-situation'", $nav);
        self::assertStringContainsString("'label' => 'Mes démarches'", $nav);
        self::assertStringContainsString("'label' => 'Mon suivi'", $nav);
        self::assertStringContainsString('$isOperatorBoNav', $sidebar);
        self::assertStringContainsString('ESPACE OPÉRATEUR', $sidebar);
    }
}
