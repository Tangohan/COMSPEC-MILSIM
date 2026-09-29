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
        self::assertStringContainsString('OPÉRATEUR · PILOTAGE', $controller);
        self::assertStringContainsString("'title' => 'Tableau de bord'", $controller);
        self::assertStringContainsString('primaryBilletsForUser', $controller);

        self::assertStringContainsString('Ce qui vous concerne', $view);
        self::assertStringContainsString('Agenda opérationnel', $view);
        self::assertStringContainsString('Rien de prévu pour l’instant', $view);
        self::assertStringContainsString('Votre arrivée n’est pas terminée', $view);
        self::assertStringContainsString('Ma liaison ATAK', $view);
        self::assertStringContainsString('Communauté', $view);
        self::assertStringContainsString('Mes démarches', $view);
        self::assertStringContainsString('Votre parcours', $view);
        self::assertStringContainsString('Ouvrir le suivi complet', $view);
        self::assertStringContainsString('back-office/ma-situation/ma-fiche', $view);
        self::assertStringContainsString('back-office/ma-situation/unite', $view);
        self::assertStringContainsString('back-office/ma-situation/qualifications', $view);
        self::assertStringContainsString('back-office/ma-situation/evenements', $view);
        self::assertStringContainsString('url(\'boite-reception\')', $view);
        self::assertStringContainsString('En service', $view);
        self::assertStringContainsString('Il ne donne aucun droit d’administration.', $view);
        self::assertStringContainsString('badge_media_path', $view);
        self::assertStringNotContainsString('Voir la liaison', $view);
        self::assertStringNotContainsString('Ouvrir Mes démarches', $view);

        self::assertStringNotContainsString('>Tenant<', $view);
        self::assertStringNotContainsString('Mes données RP', $view);
        self::assertStringNotContainsString('pairing_token', $view);
        self::assertStringNotContainsString('terminal_uid', $view);
        self::assertStringNotContainsString('ext. <?= $value', $view);

        self::assertStringContainsString("'key' => 'personnel'", $nav);
        self::assertStringContainsString("'key' => 'terrain'", $nav);
        self::assertStringContainsString("'key' => 'administratif'", $nav);
        self::assertStringContainsString("'key' => 'organisation'", $nav);
        self::assertStringContainsString("'label' => 'TERRAIN'", $nav);
        self::assertStringContainsString("'label' => 'ADMINISTRATIF'", $nav);
        self::assertStringContainsString("'label' => 'Mes démarches'", $nav);
        self::assertStringContainsString("'label' => 'Mon suivi'", $nav);
        self::assertStringContainsString("url('account/preferences')", $nav);
        self::assertStringNotContainsString("'key' => 'ma-situation'", $nav);
        self::assertStringContainsString('$opInboxBadge', $nav);
        self::assertStringContainsString('ath-sidebar__item-badge--notif', $nav);
        self::assertStringContainsString('$isOperatorBoNav', $sidebar);
        self::assertStringContainsString('ESPACE OPÉRATEUR', $sidebar);
        self::assertStringContainsString('messages_unread', $sidebar);

        $topbar = (string) file_get_contents($root . '/views/partials/back_office_topbar.php');
        self::assertStringContainsString('events_rsvp_pending', $topbar);
        self::assertStringContainsString("'label' => 'MESSAGES'", $topbar);

        $shellCss = (string) file_get_contents($root . '/public/assets/css/back-office-shell.css');
        self::assertStringContainsString('.ath-sidebar__item-badge--notif', $shellCss);
    }
}
