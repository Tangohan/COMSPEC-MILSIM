<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Page « Première liaison » : parcours de l'ATAK natif (@COMSPEC_ATAK_Native) seulement,
 * connexion par /lier, version lue comme /api/atak/version, dépannage et envoi des logs, textes traduits.
 */
final class AtakFirstLinkPageTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testViewTargetsTheNativeAtak(): void
    {
        $view = (string) file_get_contents($this->root() . '/views/atak/first_link.php');

        self::assertStringContainsString('@COMSPEC_ATAK_Native', $view);
        self::assertStringContainsString('$lierUrl', $view);
        self::assertStringContainsString('data-version-url', $view);
        self::assertStringContainsString('Envoyer_mes_logs.bat', $view);
        self::assertStringContainsString('Dépannage liaison', $view);
        self::assertStringContainsString('https://athena.ttrd.fr/public', $view);
        self::assertStringNotContainsString('Installer le pack Overwatch', $view);
        self::assertStringNotContainsString('clé d’accès', $view);
        self::assertStringContainsString('fl-game-link-btn', $view);
        self::assertStringContainsString('fl-presence-btn', $view);
    }

    public function testControllerPassesVersionsAndSeo(): void
    {
        $ctrl = (string) file_get_contents($this->root() . '/app/Controllers/Web/AtakFirstLinkController.php');

        self::assertStringContainsString('AtakReleaseInfo::latest()', $ctrl);
        self::assertStringContainsString("'meta_description'", $ctrl);
        self::assertStringContainsString("'lier_url'", $ctrl);
    }

    public function testEnglishDictionaryCoversTheMainTexts(): void
    {
        $dict = require $this->root() . '/lang/en/site_auto.php';

        foreach ([
            'Relier votre téléphone ATAK à Athena',
            'Se connecter à Athena',
            'Vérifier la liaison',
            'Envoyer mes logs',
            'Dépannage liaison, dans le jeu',
        ] as $fr) {
            self::assertArrayHasKey($fr, $dict);
        }
    }
}
