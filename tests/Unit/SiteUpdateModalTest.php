<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BuildVersion;
use App\Support\SiteUpdateInjector;
use PHPUnit\Framework\TestCase;

final class SiteUpdateModalTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        BuildVersion::reset();
        $this->tmp = sys_get_temp_dir() . '/athena-build-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/refs/heads', 0777, true);
    }

    protected function tearDown(): void
    {
        BuildVersion::reset();
        exec('rm -rf ' . escapeshellarg($this->tmp));
    }

    public function testGitHeadFollowsBranchRef(): void
    {
        $sha = str_repeat('ab12', 10);
        file_put_contents($this->tmp . '/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->tmp . '/refs/heads/main', $sha . "\n");

        self::assertSame($sha, BuildVersion::gitHead($this->tmp));
    }

    public function testGitHeadReadsPackedRefsAndDetachedHead(): void
    {
        $sha = str_repeat('cd34', 10);
        file_put_contents($this->tmp . '/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->tmp . '/packed-refs', "# pack-refs with: peeled\n" . $sha . " refs/heads/main\n");
        self::assertSame($sha, BuildVersion::gitHead($this->tmp));

        $detached = str_repeat('ef56', 10);
        file_put_contents($this->tmp . '/HEAD', $detached . "\n");
        self::assertSame($detached, BuildVersion::gitHead($this->tmp));

        self::assertSame('', BuildVersion::gitHead($this->tmp . '/absent'));
    }

    public function testBuildIdIsShortAndStable(): void
    {
        $id = BuildVersion::id();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9._-]{1,64}$/', $id);
        self::assertSame($id, BuildVersion::id());
        self::assertStringContainsString(BuildVersion::id(), asset_url('assets/css/design-system.css'));
    }

    public function testEveryHtmlPageGetsBuildMetaAndModalScript(): void
    {
        $html = '<!DOCTYPE html><html lang="fr"><head><title>x</title></head><body><p>ok</p></body></html>';
        $out = SiteUpdateInjector::apply($html);

        self::assertStringContainsString('<meta name="athena-build" content="' . BuildVersion::id() . '"', $out);
        self::assertStringContainsString('app-version-check.js?v=' . BuildVersion::id(), $out);
        self::assertStringContainsString('app-update-modal.css?v=', $out);
        self::assertSame(1, substr_count($out, 'name="athena-build"'));
        self::assertSame($out, SiteUpdateInjector::apply($out), 'idempotent');
    }

    public function testPageThatAlreadyLoadsTheModalDoesNotLoadItTwice(): void
    {
        $html = '<html><head><link href="/assets/css/app-update-modal.css?v=1" rel="stylesheet">'
            . '<script defer src="/assets/js/app-version-check.js?v=1"></script></head><body></body></html>';
        $out = SiteUpdateInjector::apply($html);

        self::assertSame(1, substr_count($out, 'app-version-check.js'));
        self::assertSame(1, substr_count($out, 'app-update-modal.css'));
        self::assertStringContainsString('name="athena-build"', $out);
    }

    public function testFragmentsAndNonHtmlAreLeftAlone(): void
    {
        self::assertSame('<div>fragment</div>', SiteUpdateInjector::apply('<div>fragment</div>'));
        self::assertSame('{"a":1}', SiteUpdateInjector::apply('{"a":1}'));
    }

    public function testUnversionedLocalAssetsGetTheBuildSuffix(): void
    {
        $html = '<script src="/assets/js/a.js"></script>'
            . '<link rel="stylesheet" href="/assets/css/b.css">'
            . '<script src="/assets/js/c.js?v=9"></script>'
            . "<script src='https://cdn.jsdelivr.net/npm/x/assets/d.js'></script>"
            . '<img src="/assets/img/e.png">';
        $out = SiteUpdateInjector::versionAssets($html, 'abc123');

        self::assertStringContainsString('src="/assets/js/a.js?v=abc123"', $out);
        self::assertStringContainsString('href="/assets/css/b.css?v=abc123"', $out);
        self::assertStringContainsString('src="/assets/js/c.js?v=9"', $out);
        self::assertStringContainsString("src='https://cdn.jsdelivr.net/npm/x/assets/d.js'", $out);
        self::assertStringContainsString('src="/assets/img/e.png"', $out);
    }

    public function testApplicationInjectsOnEveryHtmlResponse(): void
    {
        $root = dirname(__DIR__, 2);
        $app = (string) file_get_contents($root . '/app/Core/Application.php');
        $js = (string) file_get_contents($root . '/public/assets/js/app-version-check.js');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/SystemVersionController.php');

        self::assertStringContainsString('SiteUpdateInjector::apply', $app);
        self::assertStringContainsString("'build' => \\App\\Support\\BuildVersion::id()", $api);
        self::assertStringContainsString('no-store', $api);
        self::assertStringContainsString('meta[name="athena-build"]', $js);
        self::assertStringContainsString('visibilitychange', $js);
        self::assertStringContainsString('Update available', $js);
        self::assertStringContainsString('caches.delete', $js);
        self::assertStringContainsString('hasUnsavedInput', $js);
    }
}
