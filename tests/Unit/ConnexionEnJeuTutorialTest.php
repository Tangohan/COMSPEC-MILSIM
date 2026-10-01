<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ConnexionEnJeuTutorialTest extends TestCase
{
    public function testPlayerTutorialDescribesRealInGamePath(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak-tuto.php');
        self::assertStringContainsString('Connexion en jeu', $view);
        self::assertStringContainsString('Appairer', $view);
        self::assertStringContainsString('Connexion Athena', $view);
        self::assertStringContainsString('COMSPEC Athena', $view);
        self::assertStringContainsString('Lier le jeu (code Appairer)', $view);
        self::assertStringContainsString('Entrer', $view);
        self::assertStringContainsString('Associer ce terminal', $view);
        self::assertStringContainsString('adresse du site', $view);
        self::assertStringNotContainsString('Desktop</strong> → <strong>Connexion Athena', $view);
    }

    public function testFirstLinkMentionsAcePath(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak/first_link.php');
        self::assertStringContainsString('COMSPEC Athena', $view);
        self::assertStringContainsString('Connexion Athena', $view);
        self::assertStringContainsString('Lier le jeu (code Appairer)', $view);
    }

    public function testTutorialCssExists(): void
    {
        $cssPath = dirname(__DIR__, 2) . '/public/assets/css/atak-connexion-tuto.css';
        self::assertFileExists($cssPath);
        $css = (string) file_get_contents($cssPath);
        self::assertStringContainsString('.at-cx', $css);
    }
}
