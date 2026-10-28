<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Floutage des noms inappropriés : branchements (dépôts, pages publiques, administration du site). */
final class NameFilterWiringAssetTest extends TestCase
{
    private function src(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testNamesAreReviewedWhenSaved(): void
    {
        self::assertSame(2, substr_count($this->src('app/Repositories/TenantRepository.php'), 'NameModerationService::afterSave('));
        self::assertSame(2, substr_count($this->src('app/Repositories/UnitRepository.php'), 'NameModerationService::afterSave('));
    }

    public function testPublicPagesBlurNames(): void
    {
        self::assertStringContainsString('name_mask_text(', $this->src('app/Support/helpers.php'));
        self::assertStringContainsString('maskPublicNames(', $this->src('app/Controllers/Web/CommunityController.php'));
        foreach ([
            'views/home/index.php',
            'views/community/registry.php',
            'views/community/unit_show.php',
            'views/community/show_showcase.php',
            'views/community/show_legacy.php',
        ] as $view) {
            self::assertStringContainsString('name_blur_html(', $this->src($view), $view);
        }
    }

    public function testAdminPageIsPlatformAdminOnly(): void
    {
        $routes = $this->src('routes/web.php');
        $count = 0;
        foreach (explode("\n", $routes) as $line) {
            if (str_contains($line, "'/admin/system/noms-inappropries")) {
                $count++;
                self::assertStringContainsString('SystemAdminMiddleware::class', $line);
            }
        }
        self::assertSame(8, $count);
        self::assertStringContainsString("'admin/system/noms-inappropries'", $this->src('views/partials/platform_admin_sidebar.php'));
    }
}
