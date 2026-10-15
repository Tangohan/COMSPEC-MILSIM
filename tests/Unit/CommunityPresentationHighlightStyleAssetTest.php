<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Régression : GET /back-office/community/presentation plantait en 500
 * quand settings.community.public_highlight_style était absent.
 */
final class CommunityPresentationHighlightStyleAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testPresentationViewDoesNotReReadMissingHighlightStyleKey(): void
    {
        $view = (string) file_get_contents($this->root() . '/views/admin/organization/community_presentation.php');

        self::assertStringNotContainsString(
            "? (string) \$c['public_highlight_style']",
            $view,
            'Le ternaire ne doit pas relire la clé après ?? (ErrorException si absente).'
        );
        self::assertStringContainsString('$hlStyleRaw = strtolower(trim((string) ($c[\'public_highlight_style\'] ?? \'info\')))', $view);
        self::assertStringContainsString('? $hlStyleRaw : \'info\'', $view);
    }
}
