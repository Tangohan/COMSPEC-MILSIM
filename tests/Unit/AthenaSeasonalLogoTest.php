<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Emblème Athena de Noël : du 1er au 31 décembre seulement, coupable par ATHENA_SEASONAL_LOGO=0,
 * image légère, et rendu inchangé hors saison.
 */
final class AthenaSeasonalLogoTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private const XMAS = 'assets/images/opt/athena-eagle-xmas-96.png';

    /** Vues où l'emblème Athena est affiché. */
    private const VIEWS = [
        'views/partials/athena_caverne_header.php',
        'views/partials/athena_header_guest.php',
        'views/partials/dashboard_aside.php',
        'views/partials/back_office_sidebar.php',
        'views/partials/platform_admin_sidebar.php',
        'views/jnet/hub.php',
        'views/jnet/_page.php',
        'views/jnet/entry.php',
        'views/jnet/unit_space.php',
        'views/partage/_page.php',
    ];

    /** @var array<string, mixed> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        $this->savedEnv = $_ENV;
        unset($_ENV['ATHENA_SEASONAL_LOGO'], $_ENV['APP_TIMEZONE']);
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        $_ENV = $this->savedEnv;
        unset($GLOBALS['__app_locale']);
    }

    private static function at(string $when, string $tz = 'Europe/Paris'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($when, new \DateTimeZone($tz));
    }

    public function testOnlyDecemberShowsTheChristmasEmblem(): void
    {
        self::assertNull(athena_seasonal_logo(self::at('2026-11-30 23:59:59')));
        self::assertSame(self::XMAS, athena_seasonal_logo(self::at('2026-12-01 00:00:00')));
        self::assertSame(self::XMAS, athena_seasonal_logo(self::at('2026-12-25 12:00:00')));
        self::assertSame(self::XMAS, athena_seasonal_logo(self::at('2026-12-31 23:59:59')));
        self::assertNull(athena_seasonal_logo(self::at('2027-01-01 00:00:00')));
        self::assertNull(athena_seasonal_logo(self::at('2026-07-14 12:00:00')));
    }

    public function testTheSiteTimezoneDecidesNotTheServerOne(): void
    {
        // 30 nov. 23 h 30 UTC = déjà le 1er décembre à Paris ; 31 déc. 23 h 30 UTC = déjà le 1er janvier.
        self::assertSame(self::XMAS, athena_seasonal_logo(self::at('2026-11-30 23:30:00', 'UTC')));
        self::assertNull(athena_seasonal_logo(self::at('2026-12-31 23:30:00', 'UTC')));
    }

    public function testEnvironmentSwitchTurnsItOffOrForcesIt(): void
    {
        $_ENV['ATHENA_SEASONAL_LOGO'] = '0';
        self::assertNull(athena_seasonal_logo(self::at('2026-12-25 12:00:00')));
        self::assertSame('', athena_seasonal_logo_img(self::at('2026-12-25 12:00:00')));

        $_ENV['ATHENA_SEASONAL_LOGO'] = 'xmas';
        self::assertSame(self::XMAS, athena_seasonal_logo(self::at('2026-07-14 12:00:00')));
    }

    public function testServedFilesExistAndAreLight(): void
    {
        foreach ([self::XMAS, str_replace('-96.png', '-192.png', self::XMAS)] as $i => $file) {
            $path = self::ROOT . '/public/' . $file;
            self::assertFileExists($path);
            self::assertLessThan(40 * 1024, filesize($path), $file);
            $size = getimagesize($path);
            self::assertIsArray($size);
            self::assertSame($i === 0 ? 96 : 192, $size[0]);
            self::assertSame($size[0], $size[1]);
            self::assertSame('image/png', $size['mime']);
        }
    }

    public function testImageTagIsDecorativeSizedAndVersioned(): void
    {
        $img = athena_seasonal_logo_img(self::at('2026-12-24 20:00:00'));
        self::assertStringContainsString('/assets/images/opt/athena-eagle-xmas-96.png?v=', $img);
        self::assertStringContainsString('/assets/images/opt/athena-eagle-xmas-192.png?v=', $img);
        self::assertStringContainsString('alt="" aria-hidden="true"', $img);
        self::assertStringContainsString('width="96" height="96"', $img);
        self::assertStringContainsString('loading="eager"', $img);
        self::assertSame('', athena_seasonal_logo_img(self::at('2026-11-30 12:00:00')));
    }

    public function testEveryAthenaEmblemViewIsWiredAndNoneNamesTheHeavySource(): void
    {
        foreach (self::VIEWS as $view) {
            $src = (string) file_get_contents(self::ROOT . '/' . $view);
            self::assertSame(1, substr_count($src, 'athena_seasonal_logo_img()'), $view);
            self::assertStringNotContainsString('athena-eagle-xmas.png', $src, $view);
        }
        self::assertStringNotContainsString('athena-eagle-xmas.png', (string) file_get_contents(self::ROOT . '/app/Support/helpers.php'));
    }

    public function testGuestHeaderRendersTheEmblemInSeasonAndTheUsualMarkOtherwise(): void
    {
        $render = static function (): string {
            ob_start();
            try {
                require base_path('views/partials/athena_header_guest.php');
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        $_ENV['ATHENA_SEASONAL_LOGO'] = 'xmas';
        $season = $render();
        self::assertStringContainsString('<span class="athena-header__brand-mark" style="background:transparent" aria-hidden="true"><img src="', $season);
        self::assertStringContainsString('athena-eagle-xmas-96.png', $season);
        self::assertStringNotContainsString('athena-header__brand-emblem', $season);

        $_ENV['ATHENA_SEASONAL_LOGO'] = '0';
        $usual = $render();
        self::assertStringContainsString('<span class="athena-header__brand-mark" aria-hidden="true"><span class="athena-header__brand-emblem"></span></span>', $usual);
        self::assertStringNotContainsString('athena-eagle-xmas', $usual);
    }
}
