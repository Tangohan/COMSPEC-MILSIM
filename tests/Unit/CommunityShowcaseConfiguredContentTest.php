<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CommunityShowcaseConfiguredContentTest extends TestCase
{
    public function testConfiguredPublicContentHasDedicatedRendering(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/community/show_showcase.php');

        self::assertStringContainsString("\$publicMission = trim((string) (\$sv['publicMission'] ?? ''));", $view);
        self::assertStringContainsString("\$publicDoctrine = trim((string) (\$sv['publicDoctrine'] ?? ''));", $view);
        self::assertStringContainsString("\$commandChain = is_array(\$sv['commandChain'] ?? null)", $view);
        self::assertStringContainsString("\$contactIntro = trim((string) (\$cp['contactIntro'] ?? ''));", $view);
        self::assertStringContainsString('id="mission"', $view);
        self::assertStringContainsString('id="modules"', $view);
        self::assertStringContainsString('id="commandement"', $view);
        self::assertStringContainsString('aria-label="Spécialités"', $view);
        self::assertStringContainsString('aria-label="Modules publics activés"', $view);
        self::assertStringContainsString("if (\$contactIntro !== '')", $view);
    }

    public function testMissionDoctrineAndSpecialtiesAreNotOnlyFallbackContent(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/community/show_showcase.php');

        self::assertStringNotContainsString("\$aboutTitle = trim((string) (\$sv['publicDoctrine'] ?? ''));", $view);
        self::assertStringNotContainsString("\$aboutBody = trim((string) (\$sv['publicMission'] ?? ''));", $view);
        self::assertStringNotContainsString("\$pitchPoints[] = ['t' => trim(\$sp), 'b' => ''];", $view);
    }
}
