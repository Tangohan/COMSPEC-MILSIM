<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\AtakMobileOverwatchAccess;
use PHPUnit\Framework\TestCase;

/** Style tactique de /m/carte : branchement, choix gardé sur l'appareil, texte échappé, droits inchangés, pings non lus (pas de filtre de camp). */
final class AtakMobileMapTacticalTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testStyleChoiceIsOfferedOnTheMapPageOnly(): void
    {
        self::assertStringContainsString('$mapStyleChoice = true', self::read('views/m/carte.php'));
        self::assertStringNotContainsString('mapStyleChoice', self::read('views/m/briefs.php'));
        $map = self::read('views/m/_map.php');
        self::assertStringContainsString('value="tactique"', $map);
        self::assertStringContainsString('value="classique"', $map);
        $foot = self::read('views/m/_foot.php');
        self::assertStringContainsString("(\$mobileView ?? '') === 'carte'", $foot);
        self::assertStringContainsString('atak-m-tactique.js', $foot);
        self::assertStringContainsString('tactical: true', self::read('public/assets/js/atak-m.js'));
    }

    public function testChoiceDefaultsToTacticalAndStaysOnTheDevice(): void
    {
        $js = self::read('public/assets/js/atak-m-tactique.js');
        self::assertStringContainsString("localStorage.getItem(STYLE_KEY) === 'classique' ? 'classique' : 'tactique'", $js);
        self::assertStringContainsString('localStorage.setItem(STYLE_KEY, name)', $js);
    }

    public function testNoThirdPartyPhotoIsLoaded(): void
    {
        $js = self::read('public/assets/js/atak-m-tactique.js');
        self::assertStringContainsString("l.id === 'photo-comspec'", $js);
        self::assertStringNotContainsString("'aerial'", $js);
        self::assertStringNotContainsString('https://', str_replace('http://www.w3.org/2000/svg', '', $js));
    }

    public function testPlatesAreEscapedAndNotTranslated(): void
    {
        $js = self::read('public/assets/js/atak-m-tactique.js');
        self::assertStringContainsString('translate="no" style="--gh:\' + o.half + \'px">\' + esc(text)', $js);
        self::assertStringContainsString('<b translate="no">\' + esc(view.team.letter)', $js);
    }

    public function testIconsAreOnlyRebuiltWhenTheyChange(): void
    {
        $kit = self::read('public/assets/js/atak-overwatch-mapkit.js');
        self::assertStringContainsString('if (nextIcon) existing.setIcon(nextIcon);', $kit);
        $js = self::read('public/assets/js/atak-m-tactique.js');
        self::assertStringContainsString('existing.options.icon.options.mmSig === sig) return null', $js);
        self::assertStringNotContainsString('setInterval', $js);
    }

    public function testPhoneOnlyGainsThePingsReadOfTheMemberOverwatch(): void
    {
        self::assertNotContains('/api/pings', AtakMobileOverwatchAccess::READ_PATHS);
        self::assertFalse(AtakMobileOverwatchAccess::covers('GET', '/api/pings'));
        self::assertFalse(AtakMobileOverwatchAccess::covers('POST', '/api/pings'));
        self::assertFalse(AtakMobileOverwatchAccess::covers('DELETE', '/api/pings'));
    }

    public function testIconsFollowTheZoomAndStacksShowOneLabel(): void
    {
        $js = self::read('public/assets/js/atak-m.js');
        // Taille bornée : 100 % de près, 70 % au plus loin ; noms masqués au plus loin.
        self::assertStringContainsString('Math.max(0.7, Math.min(1, 1 - 0.15 * (d - 3)))', $js);
        self::assertStringContainsString("wrap.style.setProperty('--mm-k'", $js);
        self::assertStringContainsString("wrap.classList.toggle('mm-z-far', k <= 0.7)", $js);
        // Pile : un seul nom, « +N », plaques tactiques placées après.
        self::assertStringContainsString("l.el.setAttribute('data-mm-more', '+' + l.n)", $js);
        self::assertStringContainsString("it.el.classList.toggle('mm-grp-off', !!lead)", $js);
        self::assertStringContainsString('tac.declutter(used)', $js);
        self::assertStringContainsString("scale: iconScale", $js);
        $tac = self::read('public/assets/js/atak-m-tactique.js');
        self::assertStringContainsString('function declutter(taken)', $tac);
        self::assertStringNotContainsString("map.on('moveend'", $tac);

        $css = self::read('public/assets/css/atak-m.css');
        self::assertStringContainsString('scale: var(--mm-k, 1)', $css);
        self::assertStringContainsString('.mm-map .mmt--me .mmt__g { scale: max(.9, var(--mm-k, 1)); }', $css);
        self::assertStringContainsString('content: attr(data-mm-more)', $css);
        self::assertStringContainsString('.mm-map .leaflet-marker-icon.mm-grp-off { z-index: 1 !important; }', $css);
        // Symboles de 32 px au plus (anneau), pion de 28 px : mon pion et son anneau orange restent sous 38 px.
        self::assertMatchesRegularExpression('/\.mmt-ring \{[^}]*width: 32px; height: 32px;/', $css);
        self::assertMatchesRegularExpression('/\.mmt-pawn \{[^}]*width: 28px; height: 28px;/', $css);
        self::assertStringNotContainsString('width: 40px; height: 40px; border-radius: 50%', $css);
        // Noms : blanc à contour sombre, sur une ligne.
        self::assertMatchesRegularExpression('/\.mm-map \.nato-sidc-label \{[^}]*white-space: nowrap;[^}]*color: #fff;[^}]*text-shadow: 0 0 2px #000/s', $css);
    }

    public function testArmaMarkerNameStaysOnTheServer(): void
    {
        $repo = self::read('app/Repositories/AtakDataRepository.php');
        self::assertStringContainsString("\$row['trace'] = true;", $repo);
        self::assertStringNotContainsString("'arma_name' => \$r['arma_name']", $repo);
    }
}
