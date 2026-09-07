<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelFileHubAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testPublicFileUsesFiveRubricsAndRemembersTheOpenOne(): void
    {
        $root = $this->root();
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $js = (string) file_get_contents($root . '/public/assets/js/personnel-file-hub.js');
        $css = (string) file_get_contents($root . '/public/assets/css/personnel-file.css');
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $bilans = (string) file_get_contents($root . '/views/partials/personnel/file_bilans_tab.php');
        $tableau = (string) file_get_contents($root . '/views/partials/personnel/file_tableau_admin_tab.php');
        $suivi = (string) file_get_contents($root . '/views/partials/personnel/file_suivi_complet.php');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertFileExists($root . '/public/assets/js/personnel-file-hub.js');
        self::assertStringContainsString('data-file-hub', $file);
        self::assertStringContainsString('personnelFileTabs', $file);
        self::assertStringContainsString("setTab('resume')", $file);
        self::assertStringContainsString("setTab('ops')", $file);
        self::assertStringContainsString("setTab('formation')", $file);
        self::assertStringContainsString("setTab('historique')", $file);
        self::assertStringContainsString("setTab('administratif')", $file);
        self::assertStringContainsString('Portrait', $file);
        self::assertStringContainsString('Unité', $file);
        self::assertStringContainsString('Parcours', $file);
        self::assertStringContainsString('Suivi', $file);
        self::assertStringContainsString('Dossier', $file);
        self::assertStringContainsString('$personnelFileNoticesIncludeOperatorTabs = false', $file);
        self::assertStringContainsString("'seniorite' => 'ops'", $file);
        self::assertStringContainsString("'bilans' => 'historique'", $file);
        self::assertStringContainsString("'tableau' => 'administratif'", $file);
        self::assertStringContainsString('file_suivi_complet.php', $file);
        self::assertStringContainsString('Suivi du dossier', $suivi);
        self::assertStringContainsString('suivi-complet', $suivi);

        self::assertStringNotContainsString('Vue d’ensemble</button>', $file);
        self::assertStringNotContainsString('Tableau administratif</button>', $file);
        self::assertStringNotContainsString('Back-office roleplay', $file);
        self::assertStringNotContainsString('Identifiant Athena', $file);
        self::assertStringNotContainsString("x-data=\"{ tab:", $file);
        self::assertStringNotContainsString("tab = 'seniorite'", $file);
        self::assertStringNotContainsString("tab = 'bilans'", $file);
        self::assertStringNotContainsString("tab = 'tableau'", $file);
        self::assertStringNotContainsString("tab = 'logistique'", $file);

        self::assertStringContainsString("x-show=\"tab === 'formation'\"", $file);
        self::assertStringContainsString("x-show=\"tab === 'historique'\"", $bilans);
        self::assertStringContainsString("tab === \\'administratif\\'", $tableau);
        self::assertStringContainsString('Vue regroupée du dossier', $tableau);

        self::assertStringContainsString('personnel-file-tab', $js);
        self::assertStringContainsString('localStorage', $js);
        self::assertStringContainsString("onglet", $js);
        self::assertStringContainsString("'suivi-complet': 'historique'", $js);
        self::assertStringContainsString("'parcours-rh': 'historique'", $js);
        self::assertStringContainsString('personnel-file-hub.js', $layout);
        $hubJsPos = strpos($layout, 'personnel-file-hub.js');
        $alpinePos = strpos($layout, 'alpine.min.js');
        self::assertNotFalse($hubJsPos);
        self::assertNotFalse($alpinePos);
        self::assertLessThan($alpinePos, $hubJsPos);

        self::assertStringContainsString('.personnel-file-hub-tabs', $css);
        self::assertStringContainsString('var(--athena-header-h', $css);
        self::assertStringContainsString('La fiche d’un opérateur se lit en cinq rubriques', $dispatch);
    }

    public function testSidebarPhotosAndRecapBannerAreGone(): void
    {
        $file = (string) file_get_contents($this->root() . '/views/personnel/file.php');

        self::assertStringNotContainsString('<!-- Sidebar', $file);
        self::assertStringNotContainsString('<!-- Récap -->', $file);
        self::assertStringNotContainsString('lg:col-span-3 lg:sticky lg:top-32', $file);
        self::assertStringContainsString('personnel-file-hero__meta-label">Grade', $file);
    }
}
