<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QuickPictureDiscordEmbed;
use PHPUnit\Framework\TestCase;

/**
 * Embed Discord des photos : détails de la prise de vue envoyés par le jeu (photo_extra, COMSPEC Link 2.0.81),
 * grade de l'auteur, terminal et lien vers la photo sur Athena.
 */
final class QuickPictureDiscordDetailsTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    /** @param array<string, mixed> $embed @return array<string, string> */
    private function fields(array $embed): array
    {
        $out = [];
        foreach ($embed['fields'] as $f) {
            $out[$f['name']] = $f['value'];
        }

        return $out;
    }

    public function testParseExtraKeepsKnownKeysOnly(): void
    {
        $extra = QuickPictureDiscordEmbed::parseExtra('zoom=2|evil=@everyone|mission=Op Marteau @here|zoom=9|broken|lens=');
        self::assertSame(['zoom' => '2', 'mission' => 'Op Marteau'], $extra);
        self::assertSame([], QuickPictureDiscordEmbed::parseExtra(''));
    }

    public function testPhonePhotoShowsAllDetails(): void
    {
        $meta = [
            'author_callsign' => 'TA1',
            'author_grade' => 'Sgt',
            'grid_ref' => '33S XU 51120 93113',
            'heading' => 319,
            'altitude' => 23.4,
            'device_type' => 'CTAB',
            'link_version' => '2.0.81',
            'extra' => QuickPictureDiscordEmbed::parseExtra(
                'lens=selfie|zoom=2|flash=on|model=s7|atak=1.74.0|mission=Op Marteau|server=COMSPEC #1|agl=1.7|pitch=-3'
                . '|target_dist=145|target_grid=33S XU 51200 93250|overcast=62|rain=20|fog=0|wind=4.2|wind_dir=90'
            ),
        ];
        $embed = QuickPictureDiscordEmbed::build($meta, ['attachment' => 'a.jpg', 'viewer_url' => 'https://athena.ttrd.fr/public/p.jpg']);
        $f = $this->fields($embed);
        self::assertSame('Sgt TA1', $f['Opérateur']);
        self::assertSame('Op Marteau · Serveur COMSPEC #1', $f['Mission']);
        self::assertSame('23 m · sol +1,7 m', $f['Altitude']);
        self::assertSame('site −3° · point visé à 145 m · Grille 33S XU 51200 93250', $f['Visée']);
        self::assertSame('Selfie (objectif avant) · zoom x2 · flash', $f['Prise de vue']);
        // windDir vers lequel souffle le vent (090) → vent venant du 270.
        self::assertSame('couvert 62 % · pluie 20 % · vent 4,2 m/s du 270°', $f['Météo']);
        self::assertSame('Samsung Galaxy S7 · ATAK 1.74.0 · Link 2.0.81', $f['Terminal']);
        self::assertStringContainsString('https://athena.ttrd.fr/public/p.jpg', $f['Athena']);
        self::assertSame('https://athena.ttrd.fr/public/p.jpg', $embed['url']);
    }

    public function testDronePhotoDetailsAndConfidentialHidesAim(): void
    {
        $meta = [
            'author_callsign' => 'Viper 1',
            'photo_source' => 'drone',
            'drone_name' => 'AR-2 Darter',
            'title' => 'Pont nord',
            'device_type' => 'UAV',
            'extra' => QuickPictureDiscordEmbed::parseExtra('vision=ti|zoom=4.0|target_dist=1240|operator_dist=850|pitch=-35|model=s7'),
        ];
        $f = $this->fields(QuickPictureDiscordEmbed::build($meta, []));
        self::assertSame('site −35° · point visé à 1,2 km · opérateur à 850 m', $f['Visée']);
        self::assertSame('Thermique · zoom x4', $f['Prise de vue']);
        self::assertArrayNotHasKey('Terminal', $f);

        $conf = QuickPictureDiscordEmbed::build($meta + ['is_confidential' => 1], ['viewer_url' => 'https://athena.ttrd.fr/x']);
        $cf = $this->fields($conf);
        self::assertArrayNotHasKey('Visée', $cf);
        self::assertArrayNotHasKey('Athena', $cf);
        self::assertArrayNotHasKey('url', $conf);
    }

    public function testOldGameWithoutDetailsKeepsFormerEmbed(): void
    {
        $f = $this->fields(QuickPictureDiscordEmbed::build(['author_callsign' => 'TA1', 'grid_ref' => '33S XU 1 2'], []));
        self::assertSame(['Opérateur', 'Position'], array_keys($f));
    }

    public function testChainCarriesDetailsFromGameToSite(): void
    {
        $sqf = $this->read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf');
        self::assertStringContainsString('"target_dist"', $sqf);
        self::assertStringContainsString('_extraStr', $sqf);
        $dll = $this->read('mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('AddOptionalForm(multipart, "photo_extra", args, 27);', $dll);
        $ctrl = $this->read('app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString("QuickPictureDiscordEmbed::parseExtra((string) (\$_POST['photo_extra']", $ctrl);
        self::assertStringContainsString('reconAuthorGrade', $ctrl);
        $cam = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_photoCam.sqf');
        self::assertStringContainsString('false, false, true, _meta] call comspec_overwatch_connect_fnc_captureReconImage', $cam);
    }
}
