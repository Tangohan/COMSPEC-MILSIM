<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GameItemIconRepository;
use App\Services\GameEquipment\PaaDecoder;
use PHPUnit\Framework\TestCase;

/**
 * Icônes d’objets : décodage .paa (DXT5 + LZO réels du dépôt, ARGB8888 brut, fichiers piégés) et conversion PNG.
 */
final class PaaDecoderTest extends TestCase
{
    private static function fixture(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** PAA minimal : type, aucune balise, palette vide, un mipmap, fin. */
    private static function buildPaa(int $type, int $w, int $h, string $data): string
    {
        $len = strlen($data);

        return pack('v', $type) . pack('v', 0) . pack('vv', $w, $h)
            . chr($len & 0xFF) . chr(($len >> 8) & 0xFF) . chr(($len >> 16) & 0xFF) . $data . pack('vv', 0, 0);
    }

    public function testDecodesRealDxt5LzoTextureFromRepository(): void
    {
        $img = PaaDecoder::decode(self::fixture('mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native/logo.paa'), 256);
        $w = imagesx($img);
        self::assertGreaterThan(63, $w);
        self::assertSame($w, imagesy($img));
        $colors = [];
        for ($i = 0; $i < $w; $i += 4) {
            $colors[imagecolorat($img, $i, intdiv($w, 2))] = true;
        }
        self::assertGreaterThan(3, count($colors), 'image décodée non uniforme');
    }

    public function testPicksFirstMipmapThatFits(): void
    {
        $img = PaaDecoder::decode(self::fixture('mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native/logo.paa'), 64);
        self::assertLessThanOrEqual(64, imagesx($img));
    }

    public function testDecodesRawArgb8888WithAlpha(): void
    {
        // 2×1 : rouge opaque puis bleu transparent (ordre B, G, R, A).
        $paa = self::buildPaa(0x8888, 2, 1, "\x00\x00\xFF\xFF" . "\xFF\x00\x00\x00");
        $img = PaaDecoder::decode($paa);
        $a = imagecolorsforindex($img, imagecolorat($img, 0, 0));
        $b = imagecolorsforindex($img, imagecolorat($img, 1, 0));
        self::assertSame([255, 0, 0, 0], [$a['red'], $a['green'], $a['blue'], $a['alpha']]);
        self::assertSame(127, $b['alpha']);
    }

    public function testDecodesDxt1Block(): void
    {
        // Un bloc 4×4 : couleur 0 = blanc, couleur 1 = noir, tous les pixels sur l’index 0.
        $paa = self::buildPaa(0xFF01, 4, 4, pack('vv', 0xFFFF, 0x0000) . "\x00\x00\x00\x00");
        $c = imagecolorsforindex(PaaDecoder::decode($paa), imagecolorat(PaaDecoder::decode($paa), 3, 3));
        self::assertSame([255, 255, 255, 0], [$c['red'], $c['green'], $c['blue'], $c['alpha']]);
    }

    public function testLzssRoundTrip(): void
    {
        // Drapeaux 0b00000111 : 3 littéraux « abc » puis renvoi distance 3, longueur 6 → « abcabcabc ».
        $out = PaaDecoder::lzssDecompress("\x07abc\x03\x03", 9);
        self::assertSame('abcabcabc', $out);
    }

    public function testRejectsGarbageAndHostileStreams(): void
    {
        foreach (['', 'not a paa at all', str_repeat("\x01\xFF", 20)] as $bad) {
            try {
                PaaDecoder::decode($bad);
                self::fail('décodage accepté : ' . bin2hex(substr($bad, 0, 8)));
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
        }
        // LZO qui renvoie avant le début de la sortie.
        $this->expectException(\RuntimeException::class);
        PaaDecoder::lzo1xDecompress("\x12abc\x00\x00\x00", 64);
    }

    public function testPaaToPngIsBoundedAndHashable(): void
    {
        $png = GameItemIconRepository::paaToPng(self::fixture('mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native/logo.paa'));
        self::assertLessThanOrEqual(GameItemIconRepository::MAX_SIDE, max($png['width'], $png['height']));
        self::assertStringStartsWith("\x89PNG", $png['body']);
        $info = getimagesizefromstring($png['body']);
        self::assertIsArray($info);
        self::assertSame(IMAGETYPE_PNG, $info[2]);
    }

    public function testClassAndHashValidation(): void
    {
        self::assertSame('rhs_weap_m4a1', GameItemIconRepository::validClass(' rhs_weap_m4a1 '));
        self::assertNull(GameItemIconRepository::validClass('../etc/passwd'));
        self::assertNull(GameItemIconRepository::validClass(['x']));
        $repo = new GameItemIconRepository(null, '/tmp/icons');
        self::assertSame('/tmp/icons/ab/' . str_repeat('ab', 32) . '.png', $repo->pathForHash(str_repeat('ab', 32)));
        $this->expectException(\InvalidArgumentException::class);
        $repo->pathForHash('../../x');
    }

    public function testIconsAreWiredIntoEquipmentViews(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/equipment/icones/{file}'", $routes);
        self::assertStringContainsString("'/api/atak/item-icons'", $routes);
        self::assertStringContainsString('GameItemIconRepository::urlFor', (string) file_get_contents($root . '/views/admin/organization/game_equipment.php'));
        self::assertStringContainsString('GameItemIconRepository::urlFor', (string) file_get_contents($root . '/views/admin/organization/game_role_kits.php'));
        self::assertStringContainsString("\$it['icon']", (string) file_get_contents($root . '/views/admin/organization/partials/game_player_kits.php'));
        self::assertStringContainsString('it.icon', (string) file_get_contents($root . '/public/assets/js/equipment-catalog.js'));
        // Sans communauté en session : pas d’icône, pas de requête.
        self::assertNull(GameItemIconRepository::urlFor('arifle_MX_F'));
        // Repli : icônes livrées pour nos objets SSE.
        self::assertStringContainsString('assets/img/game-items/comspec_sse_camera.png', (string) GameItemIconRepository::urlFor('COMSPEC_SSE_Camera'));
        self::assertNull(GameItemIconRepository::bundledUrl('../../config'));
    }
}
