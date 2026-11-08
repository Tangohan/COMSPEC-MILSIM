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

    public function testArmaMarkerNameStaysOnTheServer(): void
    {
        $repo = self::read('app/Repositories/AtakDataRepository.php');
        self::assertStringContainsString("\$row['trace'] = true;", $repo);
        self::assertStringNotContainsString("'arma_name' => \$r['arma_name']", $repo);
    }
}
