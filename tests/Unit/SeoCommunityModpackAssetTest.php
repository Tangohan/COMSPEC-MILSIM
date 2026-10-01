<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SeoCommunityModpackAssetTest extends TestCase
{
    public function testSeoMetaSupportsExtraJsonLdAndCanonicalOverride(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/seo_meta.php');
        self::assertStringContainsString('$seo_json_ld', $src);
        self::assertStringContainsString('$seo_canonical', $src);
        self::assertStringContainsString('$seo_og_type', $src);
        self::assertStringContainsString('application/ld+json', $src);
    }

    public function testSitemapIncludesCommunitySubpagesAndUnits(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SeoController.php');
        self::assertStringContainsString("'/medias'", $src);
        self::assertStringContainsString("'/reels'", $src);
        self::assertStringContainsString('listPublicForTenant', $src);
        self::assertStringContainsString("Disallow: /admin/", $src);
        self::assertStringContainsString("Disallow: /modpacks/", $src);
    }

    public function testCommunityShowcaseCtaLabelsAreDistinct(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/community/show_showcase.php');
        self::assertStringContainsString("'rejoindre' => 'Rejoindre'", $view);
        self::assertStringContainsString("'candidater' => 'Candidater'", $view);
        self::assertStringContainsString("'contacter' => 'Nous contacter'", $view);
        self::assertStringContainsString('$ctaPrimaryHint', $view);
        self::assertStringContainsString('cl-hero__cta-hint', $view);
        self::assertStringNotContainsString("'rejoindre' => 'Déposer une candidature'", $view);
    }

    public function testCommunityControllerBuildsPublicJsonLd(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/CommunityController.php');
        self::assertStringContainsString('buildCommunityPublicJsonLd', $src);
        self::assertStringContainsString("'@type' => 'FAQPage'", $src);
        self::assertStringContainsString("'@type' => 'CollectionPage'", $src);
        self::assertStringContainsString("'@type' => 'JobPosting'", $src);
        self::assertStringContainsString("'meta_description' => \$unitDesc", $src);
        self::assertStringContainsString("'meta_description' => \$mediaDesc", $src);
    }

    public function testPrivateShellsGetNoindexInMainLayout(): void
    {
        $layout = (string) file_get_contents(dirname(__DIR__, 2) . '/views/layout/main.php');
        self::assertStringContainsString("noindex,nofollow", $layout);
        self::assertStringContainsString('$isBackOfficeShell', $layout);
    }

    public function testModpackAdminChunkedUploadIsWired(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/AdminModpackController.php');
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/modpack-admin-upload.js');
        $create = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/modpacks/create.php');
        $download = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/ModpackController.php');

        self::assertStringContainsString('function uploadInit', $controller);
        self::assertStringContainsString('function uploadChunk', $controller);
        self::assertStringContainsString('function uploadFinalize', $controller);
        self::assertStringContainsString('staged_upload_id', $controller);
        self::assertStringContainsString("normalizeExternalUrl", $controller);
        self::assertStringContainsString('/admin/modpacks/upload/init', $routes);
        self::assertStringContainsString('/admin/modpacks/upload/chunk', $routes);
        self::assertStringContainsString('/admin/modpacks/upload/finalize', $routes);
        self::assertStringContainsString('data-modpack-upload', $create);
        self::assertStringContainsString('name="url"', $create);
        self::assertStringContainsString('uploadChunked', $js);
        self::assertStringContainsString('setBodyStream', $download);
    }
}
