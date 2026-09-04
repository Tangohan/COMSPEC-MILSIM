<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelMobilityRequestRepository;
use PHPUnit\Framework\TestCase;

final class DashboardMemberHubAssetTest extends TestCase
{
    public function testDashboardSurfacesExistingOffersRhAndArticles(): void
    {
        $root = dirname(__DIR__, 2);
        $cc = (string) file_get_contents($root . '/views/partials/dashboard_command_center.php');
        $offers = (string) file_get_contents($root . '/views/partials/dashboard_org_offers.php');
        $rh = (string) file_get_contents($root . '/views/partials/dashboard_member_rh.php');
        $parcours = (string) file_get_contents($root . '/views/partials/dashboard_rh_parcours.php');
        $articles = (string) file_get_contents($root . '/views/partials/dashboard_quick_articles.php');
        $aside = (string) file_get_contents($root . '/views/partials/dashboard_aside.php');
        $home = (string) file_get_contents($root . '/app/Controllers/Web/HomeController.php');
        $rhCtrl = (string) file_get_contents($root . '/app/Controllers/Web/RhWorkspaceController.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $launcher = (string) file_get_contents($root . '/app/Controllers/Web/PublicationLauncherController.php');
        $css = (string) file_get_contents($root . '/public/assets/css/dashboard-impact.css');

        self::assertStringNotContainsString('dashboard_org_offers.php', $cc);
        self::assertStringNotContainsString('dashboard_member_rh.php', $cc);
        self::assertStringContainsString('dashboard_rh_parcours.php', $cc);
        self::assertStringContainsString('dashboard_quick_articles.php', $cc);
        self::assertStringContainsString('id="dashboard-org-offers"', $offers);
        self::assertStringContainsString('id="dashboard-org-offers"', $parcours);
        self::assertStringContainsString('Offres de l’organisation', $offers);
        self::assertStringContainsString('Offres de l’organisation', $parcours);
        self::assertStringContainsString('listPublishedForTenant', $home);
        self::assertStringContainsString('Candidater', $offers);
        self::assertStringNotContainsString('endpoint', $offers);

        self::assertStringContainsString('id="dashboard-member-rh"', $rh);
        self::assertStringContainsString('id="dashboard-member-rh"', $parcours);
        self::assertStringContainsString('Demande d’élévation', $rh);
        self::assertStringContainsString('Demande d’avancement', $rh);
        self::assertStringContainsString('personnel/mon-espace-rh/elevation', $rh);
        self::assertStringContainsString('personnel/mon-espace-rh/mobilite', $rh);
        self::assertStringContainsString('elevation_request_fields.php', $rh);
        self::assertStringContainsString('requestSelfElevation', $rhCtrl);
        self::assertStringContainsString("\$returnTo === 'dashboard'", $rhCtrl);
        self::assertStringContainsString("'/personnel/mon-espace-rh/elevation'", $routes);
        self::assertSame('En attente', PersonnelMobilityRequestRepository::STATUS_LABELS['pending']);
        self::assertContains('career_wish', PersonnelMobilityRequestRepository::TYPES);

        self::assertStringContainsString('id="dashboard-quick-articles"', $articles);
        self::assertStringContainsString('back-office/articles/create', $articles);
        self::assertStringContainsString("can('admin.organization')", $launcher);
        self::assertStringContainsString('can_publish_dashboard_articles', $home);
        self::assertStringContainsString('Nouveau mini-article', $articles);
        self::assertStringContainsString('dashboard_mini_articles.php', $cc);
        self::assertStringContainsString('TenantMiniArticleRepository', $home);

        self::assertStringContainsString('#dashboard-org-offers', $aside);
        self::assertStringContainsString('#mon-dossier-rh', $aside);
        self::assertStringNotContainsString('#dashboard-member-rh', $aside);
        self::assertStringContainsString('back-office/articles/create', $aside);
        self::assertStringContainsString('.dash-hub-stack', $css);
        self::assertStringContainsString('.dash-rh-form', $css);
        $eff = (string) file_get_contents($root . '/views/dashboard_effectifs.php');
        self::assertStringContainsString('dashboard_member_rh.php', $eff);
        self::assertStringContainsString('dashboard_quick_articles.php', $eff);
    }

    public function testArticleComposerStaysPermissionGated(): void
    {
        $root = dirname(__DIR__, 2);
        $articles = (string) file_get_contents($root . '/views/partials/dashboard_quick_articles.php');
        $home = (string) file_get_contents($root . '/app/Controllers/Web/HomeController.php');

        self::assertStringContainsString('can_publish_dashboard_articles', $articles);
        self::assertStringContainsString('if (!$canPublish)', $articles);
        self::assertStringContainsString("allows('admin.organization')", $home);
        self::assertStringContainsString("allows('admin.access')", $home);
        self::assertStringContainsString("allows('site.support')", $home);
        self::assertStringContainsString('publier', $articles);
        self::assertStringNotContainsString('forum/new-topic', $articles);
    }
}
