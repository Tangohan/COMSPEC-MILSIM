<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SiteUpdateInjector;
use PHPUnit\Framework\TestCase;

/**
 * Passerelle des liens externes : toute page HTML reçoit le script, qui laisse 10 secondes pour valider la sortie,
 * décompose l'adresse, signale les pièges courants et explique comment lire une URL ; les annonces et alertes
 * rendent leurs adresses cliquables sans HTML interprété.
 */
final class ExternalLinkGateTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->root() . '/' . $path);
    }

    public function testEveryHtmlPageGetsTheGateScriptOnceBeforeTheHeadEnds(): void
    {
        $html = '<!DOCTYPE html><html lang="fr"><head><title>x</title></head><body><a href="https://exemple.test/">x</a></body></html>';
        $out = SiteUpdateInjector::apply($html);

        self::assertSame(1, substr_count($out, 'athena-external-links.js'));
        self::assertLessThan(stripos($out, '</head>'), strpos($out, 'athena-external-links.js'));
        self::assertMatchesRegularExpression('~<script src="[^"]*athena-external-links\.js\?v=[^"]+"></script>~', $out);
        self::assertSame($out, SiteUpdateInjector::apply($out), 'idempotent');
    }

    public function testAPageThatAlreadyLoadsTheGateKeepsASingleCopy(): void
    {
        $html = '<html><head><script src="/assets/js/athena-external-links.js?v=1"></script></head><body></body></html>';
        $out = SiteUpdateInjector::apply($html);

        self::assertSame(1, substr_count($out, 'athena-external-links.js'));
    }

    public function testFragmentsAndJsonAreLeftAlone(): void
    {
        self::assertSame('<a href="https://exemple.test/">x</a>', SiteUpdateInjector::apply('<a href="https://exemple.test/">x</a>'));
        self::assertSame('{"url":"https://exemple.test/"}', SiteUpdateInjector::apply('{"url":"https://exemple.test/"}'));
    }

    public function testTheScriptGivesTenSecondsAndStaysOnTheSiteWhenTheDelayEnds(): void
    {
        $js = $this->read('public/assets/js/athena-external-links.js');

        self::assertStringContainsString('var DELAY = 10;', $js);
        self::assertStringContainsString('function expire()', $js);
        self::assertStringContainsString("'noopener,noreferrer'", $js);
        // Choix par défaut le plus sûr : le focus est sur « Rester », Échap ferme sans partir.
        self::assertStringContainsString('stay.focus();', $js);
        self::assertStringContainsString("e.key === 'Escape'", $js);
        // Un clic normal, un clic milieu et un clic avec Ctrl / Cmd passent tous par la fenêtre.
        self::assertStringContainsString("document.addEventListener('click', onClick, true);", $js);
        self::assertStringContainsString("document.addEventListener('auxclick'", $js);
        self::assertStringContainsString('data-no-gate', $js);
    }

    public function testTheScriptFlagsTheCommonUrlTraps(): void
    {
        $js = $this->read('public/assets/js/athena-external-links.js');

        foreach (['f_http', 'f_puny', 'f_ip', 'f_user', 'f_short', 'f_port', 'f_deep', 'f_brand'] as $flag) {
            self::assertStringContainsString($flag . ':', $js, $flag);
        }
        self::assertStringContainsString('xn--', $js);
        self::assertStringContainsString("'bit.ly'", $js);
        self::assertStringContainsString('guideTitle', $js);
        // Seuls http et https sont concernés : javascript:, mailto:, tel: ne passent jamais par la fenêtre.
        self::assertStringContainsString("u.protocol !== 'http:' && u.protocol !== 'https:'", $js);
    }

    public function testTheScriptSpeaksBothLanguagesAndNeverInterpretsHtml(): void
    {
        $js = $this->read('public/assets/js/athena-external-links.js');

        self::assertStringContainsString('Vous quittez Athena', $js);
        self::assertStringContainsString('You are leaving Athena', $js);
        self::assertStringNotContainsString('innerHTML', $js);
        self::assertStringNotContainsString('document.write', $js);
        self::assertStringNotContainsString('eval(', $js);
        self::assertStringContainsString('noopener noreferrer nofollow ugc', $js);
    }

    public function testAlertsRenderTheirUrlsAsLinks(): void
    {
        foreach (['views/partials/dashboard_popup_modal.php', 'views/partials/alert_banners.php', 'views/partials/navbar_info_banners.php'] as $view) {
            $src = $this->read($view);
            self::assertStringContainsString('window.AthenaLinks.linkify(', $src, $view);
            // Repli sans le script : texte brut, jamais de HTML.
            self::assertStringContainsString('.textContent = a.body', $src, $view);
            self::assertStringNotContainsString('.innerHTML = a.body', $src, $view);
        }
    }
}
