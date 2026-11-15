<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseIdentityPhotoGallery as Gallery;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SseIdWorkshopService;
use App\Services\Sse\SseSeekPhotoService;
use PHPUnit\Framework\TestCase;

/**
 * Onglet Photos de la fiche identité : regroupement par source, absence de doublon, tri, compteurs, états limites
 * (0, 1 et 40 photos, photo masquée, image sans dimensions), reprise d'une photo de la photothèque comme portrait, et
 * câblage de la vue (aucune image sans chargement différé, aucun texte sous 11 px, visionneuse, droits).
 */
final class SseIdentityPhotoGalleryTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'idgal_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($this->dir);
        }
    }

    /** @return array<string, mixed> */
    private static function photo(int $id, string $kind, string $source = 'upload', string $at = '2026-11-01 10:00:00', string $vis = 'known', ?string $ref = null, ?string $path = null): array
    {
        return [
            'id' => $id, 'identity_id' => 15, 'path' => $path ?? ($source === 'seek' ? 'sse-seek/1/0123456789abcdef.jpg' : 'uploads/sse/p' . $id . '.jpg'),
            'mime' => 'image/jpeg', 'width' => 600, 'height' => 800, 'kind' => $kind, 'source' => $source, 'source_ref' => $ref,
            'caption' => null, 'visibility' => $vis, 'created_at' => $at,
        ];
    }

    private static function urlOf(): callable
    {
        return static fn (array $p): ?string => 'https://exemple.test/' . $p['path'];
    }

    /** @return list<int> */
    private static function ids(array $group): array
    {
        return array_map(static fn (array $i): int => (int) $i['id'], $group['items']);
    }

    public function testGroupOfFollowsSourceThenKind(): void
    {
        self::assertSame('seek', Gallery::groupOf('seek', 'front'));
        self::assertSame('terrain', Gallery::groupOf('atak', 'front'));
        self::assertSame('terrain', Gallery::groupOf('upload', 'field'));
        self::assertSame('fiche', Gallery::groupOf('upload', 'front'));
        self::assertSame('fiche', Gallery::groupOf('upload', 'profile'));
        self::assertSame('biblio', Gallery::groupOf('upload', 'document'));
        self::assertSame('biblio', Gallery::groupOf('upload', 'inconnu'));
    }

    public function testEmptyIdentityHasNoPhotoAndZeroCounters(): void
    {
        $g = Gallery::build([], [], [], self::urlOf());
        self::assertTrue($g['isEmpty']);
        self::assertSame(0, $g['total']);
        self::assertNull($g['holder']);
        self::assertNull($g['signature']);
        foreach (Gallery::GROUPS as $key) {
            self::assertSame(0, $g['groups'][$key]['count']);
            self::assertSame([], $g['groups'][$key]['items']);
        }
    }

    public function testSinglePhotoLandsInItsGroup(): void
    {
        $g = Gallery::build([], [self::photo(1, 'front')], [], self::urlOf());
        self::assertFalse($g['isEmpty']);
        self::assertSame(1, $g['total']);
        self::assertSame(1, $g['groups']['fiche']['count']);
        self::assertSame('https://exemple.test/uploads/sse/p1.jpg', $g['groups']['fiche']['items'][0]['src']);
        self::assertSame('p1', $g['groups']['fiche']['items'][0]['uid']);
    }

    public function testHolderPortraitCountsInFicheAndSignatureNeedsItsPath(): void
    {
        $identity = ['photo_path' => 'uploads/sse-id-photos/1_a.jpg', 'signature_path' => 'uploads/sse-id-signatures/1_b.png'];
        $g = Gallery::build($identity, [self::photo(1, 'profile')], [], self::urlOf(), 'https://exemple.test/h.jpg', 'https://exemple.test/s.png');
        self::assertSame('holder', $g['holder']['uid']);
        self::assertTrue($g['holder']['holder']);
        self::assertSame(2, $g['groups']['fiche']['count'], 'portrait + photo de profil');
        self::assertCount(1, $g['groups']['fiche']['items'], 'le portrait est mis en avant à part, hors de la grille');
        self::assertSame(2, $g['total']);
        self::assertSame('https://exemple.test/s.png', $g['signature']);
        // Sans chemin en base, l'adresse seule ne suffit pas.
        $none = Gallery::build(['photo_path' => '', 'signature_path' => null], [], [], self::urlOf(), 'https://exemple.test/h.jpg', 'https://exemple.test/s.png');
        self::assertNull($none['holder']);
        self::assertNull($none['signature']);
        self::assertTrue($none['isEmpty']);
    }

    public function testSeekImagesAreNotCountedTwice(): void
    {
        $seekRow = self::photo(7, 'front', 'seek', '2026-11-08 19:44:00', 'known', 'REC-4471');
        $seek = $seekRow + ['seek' => ['rec' => 'REC-4471', 'grid' => 'GH 442 187', 'verdict_label' => 'Concordance forte'], 'url' => 'https://exemple.test/atak/sse/fichiers/photos/7/image'];
        // Même cliché dans les photos du dossier (lien public inexact) et dans les images SEEK II (lien protégé).
        $g = Gallery::build([], [self::photo(1, 'front'), $seekRow], [$seek], self::urlOf());
        self::assertSame(2, $g['total']);
        self::assertSame(1, $g['groups']['seek']['count']);
        $item = $g['groups']['seek']['items'][0];
        self::assertSame('https://exemple.test/atak/sse/fichiers/photos/7/image', $item['src'], 'le lien protégé de la route du portail prime');
        self::assertSame('GH 442 187', $item['place']);
        self::assertSame('REC-4471', $item['ref']);
        self::assertSame('Concordance forte', $item['seek']['verdict_label']);
    }

    public function testPrivatePathsNeverGoThroughThePublicFolder(): void
    {
        if (!function_exists('url') || !function_exists('user_media_public_url')) {
            self::markTestSkipped('helpers du site requis');
        }
        $g = Gallery::build([], [self::photo(9, 'front', 'seek', '2026-11-01 10:00:00', 'known', 'REC-1')], []);
        self::assertStringContainsString('atak/sse/fichiers/photos/9/image', $g['groups']['seek']['items'][0]['src']);
        self::assertStringNotContainsString('sse-seek/', $g['groups']['seek']['items'][0]['src']);
    }

    public function testOrderIsFrontBeforeProfileThenNewestFirstAndStable(): void
    {
        $photos = [
            self::photo(1, 'profile', 'upload', '2026-11-09 10:00:00'),
            self::photo(2, 'front', 'upload', '2026-11-01 10:00:00'),
            self::photo(3, 'front', 'upload', '2026-11-05 10:00:00'),
            self::photo(4, 'field', 'upload', '2026-11-02 10:00:00'),
            self::photo(5, 'field', 'atak', '2026-11-06 10:00:00'),
            self::photo(6, 'field', 'upload', '2026-11-06 10:00:00'),
        ];
        $g = Gallery::build([], $photos, [], self::urlOf());
        self::assertSame([3, 2, 1], self::ids($g['groups']['fiche']), 'face (récent d’abord), puis profil');
        self::assertSame([6, 5, 4], self::ids($g['groups']['terrain']), 'à date égale, l’identifiant le plus récent d’abord');
        // Le même jeu dans un autre ordre donne le même rangement.
        $again = Gallery::build([], array_reverse($photos), [], self::urlOf());
        self::assertSame(self::ids($g['groups']['fiche']), self::ids($again['groups']['fiche']));
        self::assertSame(self::ids($g['groups']['terrain']), self::ids($again['groups']['terrain']));
    }

    public function testMaskedPhotosAreFlaggedAndCounted(): void
    {
        $g = Gallery::build([], [self::photo(1, 'front'), self::photo(2, 'front', 'upload', '2026-11-02 10:00:00', 'hidden')], [], self::urlOf());
        self::assertSame(1, $g['masked']);
        self::assertSame(2, $g['groups']['fiche']['count']);
        self::assertTrue($g['groups']['fiche']['items'][0]['masked']);
        self::assertFalse($g['groups']['fiche']['items'][1]['masked']);
    }

    public function testFortyPhotosKeepExactCountersPerGroup(): void
    {
        $photos = [];
        for ($i = 1; $i <= 40; ++$i) {
            $photos[] = match ($i % 4) {
                0 => self::photo($i, 'front', 'seek', '2026-10-' . sprintf('%02d', $i % 28 + 1) . ' 09:00:00', 'known', 'REC-' . $i),
                1 => self::photo($i, 'front'),
                2 => self::photo($i, 'field'),
                default => self::photo($i, 'document'),
            };
        }
        $g = Gallery::build([], $photos, [], self::urlOf());
        self::assertSame(40, $g['total']);
        self::assertSame([10, 10, 10, 10], array_map(static fn (array $x): int => $x['count'], array_values($g['groups'])));
        $seen = [];
        foreach ($g['groups'] as $group) {
            self::assertSame($group['count'], count($group['items']));
            foreach ($group['items'] as $item) {
                $seen[$item['id']] = true;
            }
        }
        self::assertCount(40, $seen, 'chaque photo apparaît une fois et une seule');
        self::assertGreaterThan(Gallery::VISIBLE, 10 + 5, 'le repli « Afficher les autres » part de 12 vignettes');
    }

    public function testMissingDimensionsAndUnknownKindDoNotBreakAnything(): void
    {
        $p = self::photo(1, 'zzz');
        $p['width'] = null;
        $p['height'] = 0;
        $g = Gallery::build([], [$p, ['id' => 0, 'path' => 'x'], ['path' => '']], [], self::urlOf());
        self::assertSame(1, $g['total'], 'une ligne sans identifiant est ignorée');
        self::assertNull($g['groups']['biblio']['items'][0]['width']);
        self::assertNull($g['groups']['biblio']['items'][0]['height']);
    }

    // ------------------------------------------------------------ portrait depuis la photothèque

    private static function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);
    }

    public function testSetAsPortraitOnlyAcceptsPhotosOfThatIdentity(): void
    {
        $images = new SseIdImageService($this->dir, false);
        $denied = 'Cette photo n’est pas rattachée à cette identité.';
        self::assertSame(['ok' => false, 'error' => $denied], SseIdWorkshopService::copyFilePhoto(1, 15, null, $images));
        self::assertSame(['ok' => false, 'error' => $denied], SseIdWorkshopService::copyFilePhoto(1, 15, ['identity_id' => 99, 'path' => 'uploads/x.png'] + self::photo(1, 'front'), $images));
        self::assertSame(['ok' => false, 'error' => $denied], SseIdWorkshopService::copyFilePhoto(1, 15, ['identity_id' => null] + self::photo(1, 'front'), $images));
        self::assertSame(['ok' => false, 'error' => $denied], SseIdWorkshopService::copyFilePhoto(1, 15, self::photo(1, 'front', 'upload', '2026-11-01 10:00:00', 'hidden'), $images), 'une photo masquée ne devient pas portrait');
        $missing = SseIdWorkshopService::copyFilePhoto(1, 15, self::photo(1, 'front'), $images);
        self::assertFalse($missing['ok']);
        self::assertSame('Le fichier de cette photo est introuvable.', $missing['error']);
    }

    public function testSetAsPortraitCopiesAPrivateSeekShotAndRefusesAnotherCommunity(): void
    {
        $base = $this->dir . DIRECTORY_SEPARATOR . 'seek';
        mkdir($base . DIRECTORY_SEPARATOR . '1', 0777, true);
        file_put_contents($base . DIRECTORY_SEPARATOR . '1' . DIRECTORY_SEPARATOR . '0123456789abcdef.png', self::png());
        $seek = new SseSeekPhotoService(null, null, $base);
        $images = new SseIdImageService($this->dir . DIRECTORY_SEPARATOR . 'public', false);
        $row = self::photo(5, 'front', 'seek', '2026-11-01 10:00:00', 'known', 'REC-9', 'sse-seek/1/0123456789abcdef.png');

        $ok = SseIdWorkshopService::copyFilePhoto(1, 15, $row, $images, $seek);
        self::assertTrue($ok['ok'], (string) ($ok['error'] ?? ''));
        self::assertStringStartsWith('uploads/sse-id-photos/1_', (string) $ok['path']);
        self::assertFileExists($this->dir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $ok['path']));

        $other = SseIdWorkshopService::copyFilePhoto(2, 15, $row, $images, $seek);
        self::assertFalse($other['ok'], 'le cliché d’une autre communauté ne se copie pas');
    }

    // ------------------------------------------------------------ câblage de la vue et des ressources

    private static function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testIdentityPageUsesTheGalleryInsteadOfTheThreeOldBlocks(): void
    {
        $show = self::read('views/atak/sse/papers/identity_show.php');
        self::assertStringContainsString("require __DIR__ . '/_dossier_photos.php';", $show);
        self::assertStringContainsString('SseIdentityPhotoGallery::build(', $show);
        self::assertStringContainsString('assets/css/sse-identity-photos.css', $show);
        self::assertStringContainsString('assets/js/sse-identity-photos.js', $show);
        self::assertStringNotContainsString('_seek_images.php', $show, 'les clichés SEEK II sont dans la galerie, plus dans un bloc à part');
        self::assertStringNotContainsString('dos-photos', $show);
        self::assertStringContainsString("require __DIR__ . '/_holder_images.php';", self::read('views/atak/sse/papers/_dossier_photos.php'));
        // Sans habilitation, l'en-tête ne montre pas non plus le portrait d'une identité couverture ou fausse.
        self::assertStringContainsString('if ($secret && !$canSeeTruth) {', $show);
    }

    public function testViewerOffersEveryRequestedAction(): void
    {
        $v = self::read('views/atak/sse/papers/_dossier_photos.php');
        foreach (['data-idg-viewer', 'role="dialog"', 'aria-modal="true"', 'data-idv="prev"', 'data-idv="next"', 'data-idv="zoom-in"', 'data-idv="zoom-out"', 'data-idv="compare"', 'data-idv="close"', 'data-idv-download', 'data-idv-library', 'name="photo_library"', 'Définir comme portrait', 'Voir dans la photothèque'] as $needle) {
            self::assertStringContainsString($needle, $v, $needle);
        }
        // Téléchargement et portrait : réservés à ceux qui gèrent les fichiers et l'atelier ; jeton CSRF sur le formulaire.
        self::assertStringContainsString('if ($gManage): ?><a class="btn btn--ghost btn--sm" href="#" data-idv-download', $v);
        self::assertStringContainsString('<?php if ($gEdit): ?>', $v);
        self::assertStringContainsString('Csrf::field()', $v);
        $js = self::read('public/assets/js/sse-identity-photos.js');
        foreach (['Escape', 'ArrowLeft', 'ArrowRight', "'Tab'", 'wheel', 'photo-', 'is-broken'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        self::assertSame(0, preg_match('/[éèêàçùâîôû]/u', (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $js)), 'le script n’écrit aucune phrase : tout le texte vient de la page, donc traduit avec elle');
    }

    public function testEveryImageIsLazyWithFixedSizeAndNoSmallText(): void
    {
        $v = self::read('views/atak/sse/papers/_dossier_photos.php');
        // Chaque balise image du gabarit (écrite ou construite en PHP) porte son chargement différé.
        self::assertGreaterThan(3, substr_count($v, '<img'));
        for ($at = strpos($v, '<img'); $at !== false; $at = strpos($v, '<img', $at + 1)) {
            self::assertStringContainsString('loading="lazy"', substr($v, $at, 220), 'image sans chargement différé');
        }
        self::assertStringContainsString('width="', $v);
        self::assertStringContainsString('height="', $v);
        $css = self::read('public/assets/css/sse-identity-photos.css') . self::read('public/assets/css/sse-identity-dossier.css');
        preg_match_all('/font-size:\s*([\d.]+)(px|rem)/', $css, $m, PREG_SET_ORDER);
        foreach ($m as [$all, $n, $unit]) {
            $px = $unit === 'rem' ? (float) $n * 16 : (float) $n;
            self::assertGreaterThanOrEqual(11.0, $px, 'texte sous 11 px : ' . $all);
        }
        self::assertStringNotContainsString('eyebrow', $css . $v);
        self::assertStringContainsString('object-fit: cover', $css, 'vignettes recadrées, jamais étirées');
        self::assertStringContainsString('repeat(2, minmax(0, 1fr))', $css, 'deux colonnes sur téléphone');
    }

    public function testControllerAndDossierServiceAreWired(): void
    {
        $c = self::read('app/Controllers/Web/SseIdWorkshopController.php');
        self::assertStringContainsString("input('photo_library', 0)", $c);
        self::assertStringContainsString('SseIdWorkshopService::copyFilePhoto(', $c);
        $d = self::read('app/Services/Sse/SseIdentityDossierService.php');
        self::assertStringContainsString('|| $manage', $d, 'une photo masquée reste visible pour qui gère les fichiers');
    }

    public function testEnglishDictionaryCoversTheNewTexts(): void
    {
        $en = self::read('lang/en/site_auto.php');
        foreach (['Aucune photo pour cette identité', 'Définir comme portrait', 'Voir dans la photothèque', 'Afficher les %1 autres', 'Photos réservées à l’habilitation Secret (S)', 'Visionneuse de photos'] as $fr) {
            self::assertStringContainsString("'" . $fr . "' =>", $en, $fr);
        }
    }
}
