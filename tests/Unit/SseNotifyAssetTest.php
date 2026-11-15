<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Bureau SSE — composant unique de messages : zone d'alerte du layout, erreurs persistantes,
 * rôles ARIA, libellés fournis par la vue (aucun texte français dans le JavaScript).
 */
final class SseNotifyAssetTest extends TestCase
{
    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    /** Rend la zone de messages comme le layout (même partiel, mêmes variables). */
    private function render(?string $error, ?string $success): string
    {
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        ob_start();
        (static function () use ($error, $success, $h): void {
            require dirname(__DIR__, 2) . '/views/atak/sse/_notify.php';
        })();

        return (string) ob_get_clean();
    }

    public function testLayoutEmitsTheNotifyZoneAboveThePageContentAndLoadsTheComponent(): void
    {
        $layout = $this->read('views/atak/sse/_layout.php');
        self::assertStringContainsString("require __DIR__ . '/_notify.php';", $layout);
        self::assertLessThan(strpos($layout, '<?= $sseContent ?>'), strpos($layout, '/_notify.php'));
        self::assertStringNotContainsString('class="flash flash--error"', $layout);
        self::assertStringContainsString("assets/css/sse_notify.css", $layout);
        self::assertGreaterThan(strpos($layout, 'sse_ui.css'), strpos($layout, 'sse_notify.css'));
        self::assertMatchesRegularExpression('#assets/js/sse-notify\.js\'\)\) \?>" defer></script>#', $layout);
    }

    public function testServerErrorIsRenderedAsPersistentAlertWithIconTitleDetailAndClose(): void
    {
        $html = $this->render('Le titre est obligatoire.', null);
        self::assertStringContainsString('data-sse-stack="error" role="alert"', $html);
        self::assertStringContainsString('data-sse-note="error"', $html);
        self::assertStringContainsString('<svg', $html);
        self::assertStringContainsString('sse-note__title', $html);
        self::assertStringContainsString('Le titre est obligatoire.', $html);
        self::assertStringContainsString('data-sse-close aria-label="Fermer ce message"', $html);
        self::assertStringNotContainsString('data-sse-note="success"', $html);
        // Une erreur n'a aucune minuterie de fermeture : le serveur n'émet aucun délai.
        self::assertStringNotContainsString('data-timeout', $html);
        self::assertStringNotContainsString('data-autoclose', $html);
    }

    public function testSuccessGoesToTheStatusRegionAndMessagesAreEscaped(): void
    {
        $html = $this->render('<b>x</b>', 'Fiche enregistrée.');
        self::assertStringContainsString('data-sse-stack="status" role="status"', $html);
        self::assertMatchesRegularExpression('#role="status">\s*<div class="sse-note sse-toast sse-note--success" data-sse-note="success"#', $html);
        self::assertStringNotContainsString('<b>x</b>', $html);
        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    public function testEmptyZoneStillExistsSoScriptsCanAnnounceMessages(): void
    {
        $html = $this->render(null, null);
        self::assertStringContainsString('id="sse-notify"', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('role="status"', $html);
        self::assertStringNotContainsString('data-sse-note=', $html);
        foreach (['error', 'success', 'warning', 'close', 'network', 'http', 'form', 'field'] as $key) {
            self::assertStringContainsString('data-k="' . $key . '"', $html);
        }
    }

    public function testErrorsStayUntilClosedAndSuccessLastsAtLeastFiveSeconds(): void
    {
        $js = $this->read('public/assets/js/sse-notify.js');
        self::assertMatchesRegularExpression("#getAttribute\\('data-sse-note'\\) === 'success'#", $js);
        self::assertSame(1, substr_count($js, 'setTimeout(function () { dismiss(note); }'), 'seule la réussite se ferme seule');
        self::assertSame(1, preg_match('#SUCCESS_MS = (\d+);#', $js, $m));
        self::assertGreaterThanOrEqual(5000, (int) $m[1]);
        self::assertStringContainsString("window.SseNotify = {", $js);
        self::assertStringNotContainsString('confirm(', $js);
        self::assertStringNotContainsString('alert(', preg_replace('#role=.alert.#', '', $js) ?? $js);
    }

    public function testFormValidationLinksFieldsAndSetsAriaState(): void
    {
        $js = $this->read('public/assets/js/sse-notify.js');
        self::assertStringContainsString("'aria-invalid'", $js);
        self::assertStringContainsString("'aria-describedby'", $js);
        self::assertStringContainsString("addEventListener('invalid'", $js);
        self::assertStringContainsString("a.href = '#' + it.el.id;", $js);
        self::assertStringContainsString("setAttribute('role', 'alert')", $js);
        self::assertStringContainsString('items[0].el.focus();', $js);
    }

    public function testComponentScriptHoldsNoFrenchText(): void
    {
        $js = $this->read('public/assets/js/sse-notify.js');
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $js);
        $code = (string) preg_replace('#^\s*//.*$#m', '', $code);
        $code = (string) preg_replace('#\s//\s.*$#m', '', $code);
        self::assertSame(0, preg_match('/[àâçéèêëîïôûùüœ]/iu', $code), 'accent dans le script : libellés à fournir par la vue');
        self::assertSame(0, preg_match('/\b(?:erreur|champ|fermer|réessayez|formulaire|serveur|attention)\b/iu', $code));
    }

    public function testStyleSheetIsAccessibleAndKeepsMotionOptional(): void
    {
        $css = $this->read('public/assets/css/sse_notify.css');
        self::assertStringContainsString('@media (prefers-reduced-motion: no-preference)', $css);
        self::assertSame(1, preg_match('#@keyframes sse-note-in#', $css));
        // L'animation n'existe que dans le bloc « no-preference ».
        $outside = (string) preg_replace('#@media \(prefers-reduced-motion: no-preference\) \{.*?\n\}\n#s', '', $css);
        self::assertStringNotContainsString('animation:', $outside);
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertStringContainsString('min-width: 44px', $css);
        self::assertStringContainsString('border: 1px solid var(--nt-line)', $css);
        self::assertStringContainsString('[aria-invalid="true"]', $css);
        self::assertStringContainsString('.is-pinned', $css);
        self::assertStringNotContainsString('eyebrow', strtolower($css));
    }

    public function testErrorColoursReachWcagAaContrastOnTheirBackground(): void
    {
        // Les jetons des messages pointent vers --sse-* (sse-ds.css) : on résout la chaîne, puis on mesure le contraste.
        $css = $this->read('public/assets/css/sse_notify.css');
        $ds = $this->read('public/assets/css/sse-ds.css');
        $token = static function (string $name) use ($ds): string {
            self::assertSame(1, preg_match('/' . preg_quote($name, '/') . ':\s*(#[0-9a-f]{6});/i', $ds, $m), $name);

            return $m[1];
        };
        $resolve = function (string $nt) use ($css, $token): string {
            self::assertSame(1, preg_match('/' . preg_quote($nt, '/') . ':\s*var\((--sse-[a-z-]+)\);/', $css, $m), $nt);

            return $token($m[1]);
        };
        foreach (['err', 'ok', 'warn'] as $tone) {
            self::assertGreaterThanOrEqual(7.0, self::contrast($resolve('--nt-' . $tone . '-bg'), $resolve('--nt-' . $tone . '-ink')), 'contraste du ton ' . $tone);
        }
        self::assertGreaterThanOrEqual(4.5, self::contrast($resolve('--nt-err-bg'), $resolve('--nt-err-icon')));
        self::assertGreaterThanOrEqual(4.5, self::contrast($resolve('--nt-warn-bg'), $resolve('--nt-warn-icon')));
        self::assertGreaterThanOrEqual(4.5, self::contrast($resolve('--nt-ok-bg'), $resolve('--nt-ok-icon')));
        self::assertGreaterThanOrEqual(4.5, self::contrast($token('--sse-raised'), $token('--sse-strong')), 'titre blanc sur la surface du toast');
    }

    public function testAjaxFailuresOfThePortalReachTheComponentInsteadOfNativeAlerts(): void
    {
        foreach (['sse-case-map.js', 'sse-id-workshop.js', 'sse-iw-graph.js', 'sse-access-picker.js', 'atak-sse-persons.js'] as $file) {
            $js = $this->read('public/assets/js/' . $file);
            self::assertStringContainsString('SseNotify.', $js, $file);
            self::assertSame(0, preg_match('/(?<![\w.])(?:window\.)?alert\(/', $js), $file . ' : alert() natif');
        }
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(static function (string $c): float {
            $v = hexdec($c) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }
}
