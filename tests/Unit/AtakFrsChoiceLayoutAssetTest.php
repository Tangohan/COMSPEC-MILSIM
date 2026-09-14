<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakFrsChoiceLayoutAssetTest extends TestCase
{
    public function testFrsChoiceRadiosAreNotStretchedLikeTextInputs(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');

        self::assertStringContainsString(
            '.frs-field input:not([type="radio"]):not([type="checkbox"])',
            $css
        );
        self::assertStringNotContainsString(".frs-field input,\n.frs-field textarea", $css);
        self::assertStringContainsString('.frs-choice input[type="radio"]', $css);
        self::assertStringContainsString('max-width: 1rem;', $css);
        self::assertStringContainsString('overflow-wrap: anywhere;', $css);
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/bugs/2026-09-13-atak-frs-choix-illisibles.md');
    }
}
