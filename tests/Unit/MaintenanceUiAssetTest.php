<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaintenanceMarkdown;
use PHPUnit\Framework\TestCase;

final class MaintenanceUiAssetTest extends TestCase
{
    public function testPublicMaintenancePageUsesCalmAthenaLayoutAndHumanCopy(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/errors/maintenance.php');

        self::assertStringContainsString('Nous revenons bientôt', $view);
        self::assertStringContainsString('momentanément fermé', $view);
        self::assertStringContainsString('Vos données restent en sécurité', $view);
        self::assertStringContainsString('Réessayer', $view);
        self::assertStringContainsString('Athena', $view);
        self::assertStringContainsString('fog-team.jpg', $view);
        self::assertStringContainsString('MaintenanceMarkdown', $view);
        self::assertStringContainsString('linear-gradient(180deg, transparent 0%', $view);
        self::assertStringNotContainsString('Instrument Serif', $view);
        self::assertStringNotContainsString('class="brand"', $view);
        self::assertStringNotContainsString('Athena<em>', $view);
        self::assertStringNotContainsString('bar__mark', $view);
        self::assertStringNotContainsString('letter-spacing: 0.28em', $view);
        self::assertStringNotContainsString('Space Mono', $view);
        self::assertStringNotContainsString('PORTAIL OPÉRATIONNEL', $view);
        self::assertStringNotContainsString('classification', $view);
        self::assertStringNotContainsString('ATH-', $view);
        self::assertStringNotContainsString('Code maintenance', $view);
        self::assertStringNotContainsString('endpoint', $view);
        self::assertStringNotContainsString('JSON', $view);
        self::assertStringNotContainsString('les API', $view);
        self::assertStringNotContainsString('slug', strtolower($view));
    }

    public function testMaintenanceMarkdownRendersHorizontalRuleAndLanguageLabels(): void
    {
        $html = MaintenanceMarkdown::toHtml("FR\nBonjour le portail.\n\n---\n\nEN\nHello portal.");

        self::assertStringContainsString('<p class="lang">FR</p>', $html);
        self::assertStringContainsString('<p class="lang">EN</p>', $html);
        self::assertStringContainsString('<hr>', $html);
        self::assertStringContainsString('Bonjour le portail.', $html);
        self::assertStringContainsString('Hello portal.', $html);
        self::assertStringNotContainsString('---', $html);
    }

    public function testMaintenanceMarkdownEscapesHtml(): void
    {
        $html = MaintenanceMarkdown::toHtml('Hello <script>alert(1)</script> **world**');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('<strong>world</strong>', $html);
    }
}
