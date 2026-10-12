<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QuickPictureDiscordEmbed;
use App\Support\ReconPhotoBlur;
use PHPUnit\Framework\TestCase;

/**
 * Clichés du drone dans le salon Discord des Quick Picture : SSE → image floutée,
 * confidentiel → message, lien de lecture sur Athena et note, sans image.
 */
final class DronePhotoDiscordRelayTest extends TestCase
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

    public function testDronePhotoIsRecognisedAndClearOneKeepsImage(): void
    {
        $meta = [
            'author_callsign' => 'Viper 1',
            'device_type' => 'UAV',
            'photo_source' => 'drone',
            'drone_name' => 'AR-2 Darter',
            'title' => 'Pont nord',
            'description' => 'Deux véhicules garés',
            'caption' => 'Drone AR-2 Darter, grille 1519 1730, 120 m sol, cap 90°, 14:30',
            'grid_ref' => '1519 1730',
        ];
        self::assertTrue(QuickPictureDiscordEmbed::isDronePhoto($meta));
        self::assertTrue(QuickPictureDiscordEmbed::isDronePhoto(['device_type' => 'UAV', 'feed_id' => 'drone:2:15']));
        self::assertFalse(QuickPictureDiscordEmbed::isDronePhoto(['device_type' => 'UAV', 'feed_id' => 'uav:1']));
        self::assertSame(QuickPictureDiscordEmbed::MODE_CLEAR, QuickPictureDiscordEmbed::mode($meta));

        $embed = QuickPictureDiscordEmbed::build($meta, ['attachment' => 'd.jpg']);
        self::assertSame('Cliché drone — Pont nord', $embed['title']);
        self::assertSame('Deux véhicules garés', $embed['description']);
        self::assertSame('attachment://d.jpg', $embed['image']['url']);
        self::assertSame('AR-2 Darter', $this->fields($embed)['Drone']);
        self::assertSame('Athena · Drone ATAK', $embed['footer']['text']);
    }

    public function testSseUsesOnlyBlurredAttachmentNeverPublicUrl(): void
    {
        $meta = ['author_callsign' => 'Viper 1', 'photo_source' => 'drone', 'is_sse' => 1, 'sse_case_ref' => 'SSE-042'];
        self::assertSame(QuickPictureDiscordEmbed::MODE_SSE, QuickPictureDiscordEmbed::mode($meta));
        $withFile = QuickPictureDiscordEmbed::build($meta, ['attachment' => 'blur.jpg']);
        self::assertSame('attachment://blur.jpg', $withFile['image']['url']);
        self::assertStringStartsWith('[SSE] ', $withFile['title']);
        self::assertStringContainsString('floutée', $this->fields($withFile)['SSE']);
        self::assertStringContainsString('SSE-042', $this->fields($withFile)['SSE']);

        $noFile = QuickPictureDiscordEmbed::build($meta, ['image_url' => 'https://athena.example/uploads/recon/a.jpg']);
        self::assertArrayNotHasKey('image', $noFile, 'lien public = image nette : interdit en SSE');
        // Ancienne DLL : étiquette tassée dans la légende.
        self::assertSame(QuickPictureDiscordEmbed::MODE_SSE, QuickPictureDiscordEmbed::mode(['caption' => '[SSE] Pont : RAS (drone X)']));
    }

    public function testConfidentialHasMessageReadUrlAndNoteButNoImage(): void
    {
        $meta = [
            'author_callsign' => 'Viper 1',
            'photo_source' => 'drone',
            'is_confidential' => 1,
            'is_sse' => 1,
            'title' => 'Camp HVT',
            'description' => 'Cible identifiée',
            'grid_ref' => '0123 4567',
        ];
        self::assertSame(QuickPictureDiscordEmbed::MODE_CONFIDENTIAL, QuickPictureDiscordEmbed::mode($meta));
        $embed = QuickPictureDiscordEmbed::build($meta, [
            'attachment' => 'x.jpg',
            'image_url' => 'https://athena.example/uploads/recon/a.jpg',
            'read_url' => 'https://athena.example/api/recon/images/42/file',
            'real_unix' => 1_790_000_000,
        ]);
        self::assertArrayNotHasKey('image', $embed);
        self::assertSame('[CONFIDENTIEL] Cliché drone — Camp HVT', $embed['title']);
        self::assertSame('Cible identifiée', $embed['description']);
        $f = $this->fields($embed);
        self::assertSame('https://athena.example/api/recon/images/42/file', $f['Lecture sur Athena']);
        self::assertSame(QuickPictureDiscordEmbed::CONFIDENTIAL_NOTE, $f['Confidentialité']);
        self::assertStringContainsString('0123 4567', $f['Position']);
        self::assertArrayHasKey('Date / heure réelle', $f);
        self::assertSame(QuickPictureDiscordEmbed::COLOR_CONFIDENTIAL, $embed['color']);
        // Ancienne DLL : [CONFIDENTIEL] dans la légende, retiré du texte.
        $old = ['author_callsign' => 'V', 'device_type' => 'UAV', 'feed_id' => 'drone:2:1', 'caption' => '[CONFIDENTIEL] [SSE A1] Camp : RAS'];
        self::assertSame(QuickPictureDiscordEmbed::MODE_CONFIDENTIAL, QuickPictureDiscordEmbed::mode($old));
        self::assertSame('Camp : RAS', QuickPictureDiscordEmbed::build($old, [])['description']);
    }

    public function testClassicQuickPictureUnchanged(): void
    {
        $embed = QuickPictureDiscordEmbed::build(['author_callsign' => 'TA1', 'device_type' => 'PHONE'], ['attachment' => 'qp.jpg']);
        self::assertSame('Quick Picture — TA1', $embed['title']);
        self::assertSame(QuickPictureDiscordEmbed::COLOR, $embed['color']);
        self::assertSame('Athena · Téléphone ATAK', $embed['footer']['text']);
    }

    public function testGdBlurDestroysDetail(): void
    {
        if (!ReconPhotoBlur::gdAvailable()) {
            self::markTestSkipped('GD absent');
        }
        $im = imagecreatetruecolor(200, 100);
        $white = (int) imagecolorallocate($im, 255, 255, 255);
        $black = (int) imagecolorallocate($im, 0, 0, 0);
        imagefill($im, 0, 0, $white);
        // Damier fin : un flou fort doit le ramener à un gris quasi uniforme.
        for ($x = 0; $x < 200; $x += 4) {
            for ($y = 0; $y < 100; $y += 4) {
                if ((($x + $y) / 4) % 2 === 0) {
                    imagefilledrectangle($im, $x, $y, $x + 3, $y + 3, $black);
                }
            }
        }
        $out = ReconPhotoBlur::blurGd($im);
        self::assertInstanceOf(\GdImage::class, $out);
        self::assertSame(200, imagesx($out));
        self::assertSame(100, imagesy($out));
        $min = 255;
        $max = 0;
        for ($x = 20; $x < 180; $x += 7) {
            for ($y = 20; $y < 80; $y += 7) {
                $r = (imagecolorat($out, $x, $y) >> 16) & 0xFF;
                $min = min($min, $r);
                $max = max($max, $r);
            }
        }
        self::assertLessThan(40, $max - $min, 'détail encore lisible après le flou');
    }

    public function testRelayWiring(): void
    {
        $svc = $this->read('app/Services/Integrations/DiscordEventRelayService.php');
        self::assertStringContainsString('QuickPictureDiscordEmbed::isDronePhoto($meta)', $svc);
        self::assertStringContainsString('$mode === QuickPictureDiscordEmbed::MODE_CONFIDENTIAL', $svc);
        self::assertStringContainsString('ReconPhotoBlur::blurGd($im)', $svc);
        self::assertStringContainsString('ReconPhotoBlur::blurFileImagick(', $svc);
        self::assertStringContainsString("\$mode === QuickPictureDiscordEmbed::MODE_CLEAR ? \$this->publicPhotoUrl(\$filePath) : ''", $svc);

        $ctrl = $this->read('app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString("'feed_id' => mb_substr(\$feedId, 0, 120)", $ctrl);
        self::assertStringContainsString("'read_url' => (string) (\$row['url'] ?? '')", $ctrl);
        self::assertStringContainsString('notifyQuickPicture($tenantId, $path, $discordMeta)', $ctrl);
    }
}
