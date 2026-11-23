<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Administration du site, Alertes : aperçu RÉEL des annonces (mêmes éléments et mêmes feuilles de style que le portail, à
 * leur taille réelle) à la place des maquettes réduites, et galerie de tous les types sur la page de liste.
 */
final class AlertPreviewAssetTest extends TestCase
{
    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testPreviewScriptBuildsTheProductionElementsWithoutInterpretingHtml(): void
    {
        $js = $this->read('public/assets/js/alert-preview.js');

        // Classes de production : bandeau, barres sous le menu, bandeau défilant, fenêtre du tableau de bord.
        foreach (['ds-alert ds-alert--', 'mini-banner ', 'nib-breaking', 'nib-ticker-track', 'dash-popup-panel', 'banner-tag', 'banner-content'] as $class) {
            self::assertStringContainsString($class, $js, $class);
        }
        self::assertStringNotContainsString('innerHTML', $js);
        self::assertStringContainsString('AthenaLinks.linkify', $js);
        self::assertStringContainsString('window.AthenaAlertPreview', $js);
        // Aucun envoi réseau ni enregistrement : l'aperçu est purement local.
        self::assertStringNotContainsString('fetch(', $js);
        self::assertStringNotContainsString('XMLHttpRequest', $js);
    }

    public function testToneMappingMatchesTheRealBanners(): void
    {
        $js = $this->read('public/assets/js/alert-preview.js');
        $real = $this->read('views/partials/alert_banners.php');

        preg_match('/var toneOf = \{([^}]*)\}/s', $real, $a);
        preg_match('/var TONE = \{([^}]*)\}/s', $js, $b);
        self::assertNotEmpty($a);
        self::assertNotEmpty($b);
        $norm = static fn (string $s): array => array_filter(array_map('trim', explode(',', preg_replace('/\s+/', ' ', $s) ?? '')));
        self::assertEqualsCanonicalizing($norm($a[1]), $norm($b[1]), 'les tons de l’aperçu suivent ceux du portail');
    }

    public function testFormAndIndexUseTheRealPreview(): void
    {
        $form = $this->read('views/admin/system/platform_alerts_form.php');
        $index = $this->read('views/admin/system/platform_alerts_index.php');

        self::assertStringContainsString('assets/js/alert-preview.js', $form);
        self::assertStringContainsString('AthenaAlertPreview.mount(live', $form);
        self::assertStringContainsString('assets/css/dsfr-service.css', $form);
        self::assertStringContainsString('assets/css/navbar-info-banners.css', $form);
        self::assertStringContainsString('id="ap-gallery"', $index);
        self::assertStringContainsString('AthenaAlertPreview.gallery', $index);
        self::assertSame(1, substr_count($index, '<h1'), 'un seul titre de page');
    }
}
