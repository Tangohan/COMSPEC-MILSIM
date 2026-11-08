<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Récapitulatif des modifications de la page publique et choix illustrés : les noms de champs envoyés ne changent pas.
 */
final class CommunityPresentationRecapAssetTest extends TestCase
{
    public function testSelectsKeepTheirFieldNamesAndGetAVisualPicker(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/organization/community_presentation.php');
        foreach (['public_page_layout', 'public_audience', 'public_highlight_style', 'public_stats_mode', 'registration_mode'] as $name) {
            self::assertMatchesRegularExpression('/<select[^>]*name="' . $name . '"[^>]*data-bcs-visual="/', $view, $name);
        }
        self::assertStringContainsString('community-presentation-pickers.js', $view);
        self::assertFileExists($root . '/public/assets/js/community-presentation-pickers.js');
    }

    public function testRecapShowsReadableValuesByPartAndCanRevert(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/community_presentation.php');
        self::assertStringContainsString('Vérifier avant d’enregistrer', $view);
        self::assertStringContainsString('function readable(el, values)', $view);
        self::assertStringContainsString('bcs-recap__group', $view);
        self::assertStringContainsString('Remettre comme avant', $view);
        self::assertStringContainsString('Voir le champ', $view);
    }
}
