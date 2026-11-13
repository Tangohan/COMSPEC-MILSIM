<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Overwatch en thème nuit (défaut) et jour : les contrôles natifs (select/option, champs, boutons nus,
 * placeholder, désactivé, focus, défilement) passent par des jetons dont les paires texte/fond sont lisibles,
 * et aucune règle « nuit » ne pose un fond clair en dur. Voir public/assets/css/atak-overwatch-theme.css.
 */
final class OverwatchThemeContrastTest extends TestCase
{
    /** Fonds clairs voulus en nuit : pastille de marque, animation de flash. */
    private const LIGHT_BACKGROUND_ALLOWED = [
        '.ow-brand>b',
        '50%,100%',
        '.owi-ring-label i',
    ];

    private static function css(string $name): string
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/' . $name);
        self::assertIsString($css);

        return (string) preg_replace('~/\*.*?\*/~s', '', $css);
    }

    private static function lum(string $hex): float
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        $c = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
        foreach ($c as &$v) {
            $v /= 255;
            $v = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private static function ratio(string $a, string $b): float
    {
        $la = self::lum($a);
        $lb = self::lum($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** @return array<string, string> jetons --ow-* du bloc dont le sélecteur commence par $selector. */
    private static function tokens(string $css, string $selector): array
    {
        self::assertSame(1, preg_match('~(?:^|\})\s*' . preg_quote($selector, '~') . '\s*\{([^{}]*)\}~', $css, $m), $selector);
        preg_match_all('~(--[\w-]+)\s*:\s*(#[0-9a-fA-F]{3,6})\b~', $m[1], $t, PREG_SET_ORDER);
        $out = [];
        foreach ($t as $row) {
            $out[$row[1]] = $row[2];
        }

        return $out;
    }

    public function testThemeDeclaresColorSchemeAndNativeControlStates(): void
    {
        $css = self::css('atak-overwatch-theme.css');
        self::assertMatchesRegularExpression('~:root\s*\{[^}]*color-scheme:\s*dark~', $css);
        self::assertMatchesRegularExpression('~body\.ow-theme-day\s*\{[^}]*color-scheme:\s*light~', $css);
        self::assertStringContainsString('select option,select optgroup{background-color:var(--ow-menu-bg)', $css);
        self::assertStringContainsString('select option:checked', $css);
        self::assertStringContainsString('::placeholder{color:var(--ow-field-ph)', $css);
        self::assertStringContainsString(':disabled', $css);
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('scrollbar-color:var(--ow-scroll-thumb)', $css);
        self::assertStringContainsString(':-webkit-autofill', $css);
        self::assertStringContainsString('.leaflet-tooltip{background:var(--ow-tip-bg)', $css);
        self::assertStringContainsString('.ow-ctx-danger', $css);
        // Les contrôles du panneau de replay héritent de color-scheme.
        self::assertStringContainsString('color-scheme:dark', self::css('atak-overwatch-replay.css'));
    }

    public function testViewLoadsThemeLastAndReplayAssets(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/views/atak-overwatch-beta.php');
        self::assertIsString($view);
        $refresh = strpos($view, '/assets/css/atak-overwatch-refresh.css?v=');
        $theme = strpos($view, '/assets/css/atak-overwatch-theme.css?v=');
        self::assertNotFalse($refresh);
        self::assertNotFalse($theme);
        self::assertGreaterThan($refresh, $theme, 'Le thème doit être chargé après les feuilles Overwatch.');
        self::assertStringContainsString('atak-overwatch-replay.css', $view);
        self::assertStringContainsString('atak-overwatch-replay.js', $view);
    }

    /**
     * @return iterable<string, array{string, string, float}>
     */
    public static function tokenPairs(): iterable
    {
        foreach (['night' => ':root', 'day' => 'body.ow-theme-day'] as $theme => $selector) {
            yield $theme . ' champ' => [$selector, '--ow-field-fg|--ow-field-bg', 4.5];
            yield $theme . ' placeholder' => [$selector, '--ow-field-ph|--ow-field-bg', 4.5];
            yield $theme . ' désactivé' => [$selector, '--ow-field-off-fg|--ow-field-off-bg', 3.0];
            yield $theme . ' bouton' => [$selector, '--ow-btn-fg|--ow-btn-bg', 4.5];
            yield $theme . ' bouton survol' => [$selector, '--ow-btn-fg|--ow-btn-hover', 4.5];
            yield $theme . ' liste déroulante' => [$selector, '--ow-field-fg|--ow-menu-bg', 4.5];
            yield $theme . ' option choisie' => [$selector, '--ow-menu-sel-fg|--ow-menu-sel', 4.5];
            yield $theme . ' infobulle' => [$selector, '--ow-tip-fg|--ow-tip-bg', 4.5];
            yield $theme . ' défilement' => [$selector, '--ow-scroll-thumb|--ow-scroll-track', 1.5];
            yield $theme . ' bordure de champ' => [$selector, '--ow-field-line|--ow-field-bg', 1.3];
        }
    }

    /**
     * @dataProvider tokenPairs
     */
    public function testTokenPairsAreReadable(string $selector, string $pair, float $min): void
    {
        $tokens = self::tokens(self::css('atak-overwatch-theme.css'), $selector);
        [$fg, $bg] = explode('|', $pair);
        self::assertArrayHasKey($fg, $tokens);
        self::assertArrayHasKey($bg, $tokens);
        self::assertGreaterThanOrEqual($min, self::ratio($tokens[$fg], $tokens[$bg]), $selector . ' ' . $pair);
    }

    public function testNightTokensAreDarkAndDayTokensAreLight(): void
    {
        $css = self::css('atak-overwatch-theme.css');
        foreach (self::tokens($css, ':root') as $name => $hex) {
            if (preg_match('~^--ow-(field-bg|field-off-bg|btn-bg|btn-hover|menu-bg|tip-bg|scroll-track)$~', $name)) {
                self::assertLessThan(0.1, self::lum($hex), 'Nuit : ' . $name . ' doit rester sombre.');
            }
        }
        foreach (self::tokens($css, 'body.ow-theme-day') as $name => $hex) {
            if (preg_match('~^--ow-(field-bg|field-hover|menu-bg|tip-bg|scroll-track)$~', $name)) {
                self::assertGreaterThan(0.7, self::lum($hex), 'Jour : ' . $name . ' doit rester clair.');
            }
        }
    }

    public function testNoLightHardcodedBackgroundInNightRules(): void
    {
        $offenders = [];
        foreach (glob(dirname(__DIR__, 2) . '/public/assets/css/atak-overwatch-*.css') ?: [] as $file) {
            $css = self::css(basename($file));
            preg_match_all('~([^{}]+)\{([^{}]*)\}~', $css, $rules, PREG_SET_ORDER);
            foreach ($rules as $rule) {
                $selector = trim((string) preg_replace('~\s+~', ' ', $rule[1]));
                if (str_contains($selector, 'ow-theme-day') || in_array($selector, self::LIGHT_BACKGROUND_ALLOWED, true)) {
                    continue;
                }
                if (preg_match_all('~(?<![-\w])background(?:-color)?\s*:\s*(#[0-9a-fA-F]{3,8})\b~', $rule[2], $bg)) {
                    foreach ($bg[1] as $hex) {
                        if (self::lum(substr($hex, 0, 7)) > 0.5) {
                            $offenders[] = basename($file) . ' | ' . $selector . ' | ' . $hex;
                        }
                    }
                }
            }
        }
        self::assertSame([], $offenders, "Fond clair en dur dans une règle nuit :\n" . implode("\n", $offenders));
    }

    public function testNoDarkTextOnUnsetBackgroundInNightRules(): void
    {
        $offenders = [];
        foreach (glob(dirname(__DIR__, 2) . '/public/assets/css/atak-overwatch-*.css') ?: [] as $file) {
            $css = self::css(basename($file));
            preg_match_all('~([^{}]+)\{([^{}]*)\}~', $css, $rules, PREG_SET_ORDER);
            foreach ($rules as $rule) {
                $selector = trim((string) preg_replace('~\s+~', ' ', $rule[1]));
                if (str_contains($selector, 'ow-theme-day') || !preg_match('~(?<![-\w])color\s*:\s*(#[0-9a-fA-F]{3,6})\b~', $rule[2], $c)) {
                    continue;
                }
                if (self::lum($c[1]) < 0.05 && !preg_match('~(?<![-\w])background(?:-color)?\s*:~', $rule[2])) {
                    $offenders[] = basename($file) . ' | ' . $selector . ' | ' . $c[1];
                }
            }
        }
        self::assertSame([], $offenders, "Texte foncé sans fond posé dans la même règle :\n" . implode("\n", $offenders));
    }
}
