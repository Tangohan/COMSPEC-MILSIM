<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PhotoLibrary\PhotoGeometry;
use App\Services\PhotoLibrary\PhotoLibraryService;
use App\Services\PhotoLibrary\PhotoMaskRenderer;
use App\Services\PhotoLibrary\PhotoPolicy;
use App\Services\PhotoLibrary\PhotoSseTransfer;
use App\Services\PhotoLibrary\PhotoVault;
use App\Services\PhotoLibrary\PhotoViewer;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakePhotoLibraryRepository;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Photothèque (extranet JNET) : matrice de droits, masques appliqués dans l'image servie (lecteur sans droit : masquée ;
 * auteur, gestionnaire, porte SSE : original), visages étiquetés à la main, commentaires, versement SSE et ses portes,
 * validation des rectangles, jeton CSRF sur chaque écriture, étape de migration, vues rendues avec leurs classes importées.
 *
 * Aucun faux objet généré : un dépôt en mémoire (Tests\Support\FakePhotoLibraryRepository) et de vrais fichiers dans un
 * dossier temporaire.
 */
final class PhotoLibraryRefontTest extends TestCase
{
    private string $tmp = '';

    /** @var list<array<string, mixed>> */
    private array $events = [];

    /** @var list<array{int, int, string}> */
    private array $notes = [];

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/photolib_' . bin2hex(random_bytes(4));
        foreach (['recon', 'originals', 'cache'] as $d) {
            mkdir($this->tmp . '/' . $d, 0777, true);
        }
        $this->events = [];
        $this->notes = [];
    }

    protected function tearDown(): void
    {
        $this->rm($this->tmp);
    }

    private function rm(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            is_dir($dir . '/' . $f) ? $this->rm($dir . '/' . $f) : @unlink($dir . '/' . $f);
        }
        @rmdir($dir);
    }

    private function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function needGd(): void
    {
        if (!PhotoMaskRenderer::available()) {
            self::markTestSkipped('GD requis pour appliquer un masque.');
        }
    }

    // ——— Aides ———

    /** Photo 800 × 600 : fond vert, carré rouge vif en (0.1..0.3, 0.1..0.4) qui sert de « visage ». */
    private function makeJpeg(string $path): void
    {
        $im = imagecreatetruecolor(800, 600);
        imagefilledrectangle($im, 0, 0, 799, 599, (int) imagecolorallocate($im, 30, 140, 60));
        imagefilledrectangle($im, 80, 60, 239, 239, (int) imagecolorallocate($im, 250, 20, 20));
        for ($i = 0; $i < 40; $i++) {
            imageline($im, 80 + $i * 4, 60, 80 + $i * 4, 239, (int) imagecolorallocate($im, 250, 20 + ($i % 2) * 200, 20));
        }
        imagejpeg($im, $path, 95);
    }

    /** @return array{0: PhotoLibraryService, 1: FakePhotoLibraryRepository, 2: PhotoVault} */
    private function world(): array
    {
        $repo = new FakePhotoLibraryRepository();
        foreach ([[10, 'VIPER'], [11, 'ECHO'], [12, 'HAWK'], [13, 'FOX']] as [$id, $cs]) {
            $repo->users[$id] = ['id' => $id, 'name' => $cs . ' nom', 'callsign' => $cs, 'tenant' => 1];
        }
        $repo->users[99] = ['id' => 99, 'name' => 'Autre', 'callsign' => 'OTHER', 'tenant' => 2];
        $vault = new PhotoVault($this->tmp . '/originals', $this->tmp . '/cache', $this->tmp . '/recon');
        $sse = new class($repo) extends PhotoSseTransfer {
            public function __construct(private FakePhotoLibraryRepository $f)
            {
            }

            public function identityUsable(int $tenantId, int $identityId): bool
            {
                return $tenantId === 1 && $identityId === 77;
            }

            public function transfer(array $row, array $tags, ?int $identityId, int $userId, string $actorLabel, PhotoVault $vault, ?string $originalRelative): array
            {
                $this->f->addTransfer(1, (int) $row['id'], null, $identityId ?? $this->singleSubject($tags), $userId, false, false);

                return ['ok' => true, 'identity_id' => $identityId];
            }

            public function withdraw(array $row, array $transfer, int $userId, string $actorLabel, PhotoVault $vault): array
            {
                $this->f->withdrawTransfer(1, (int) $transfer['id'], $userId);

                return ['ok' => true];
            }
        };
        $service = new PhotoLibraryService(
            $repo,
            $vault,
            $sse,
            function (string $action, int $tenant, int $user, int $photo, array $data): void {
                $this->events[] = ['action' => $action, 'user' => $user, 'photo' => $photo] + $data;
            },
            function (int $tenant, int $user, string $type, array $payload): void {
                $this->notes[] = [$user, $tenant, $type];
            },
            static fn (int $tenant, int $reporter, string $reason, string $type, ?string $comment, string $url, string $kind): int => 4242,
        );

        return [$service, $repo, $vault];
    }

    private function photo(FakePhotoLibraryRepository $repo, int $id, array $over = []): int
    {
        $this->makeJpeg($this->tmp . '/recon/photo_' . $id . '.jpg');

        return $repo->addPhoto($over + ['id' => $id, 'tenant_id' => 1, 'author_callsign' => 'VIPER', 'author_user_id' => 10, 'image_path' => 'recon/photo_' . $id . '.jpg', 'caption' => 'Checkpoint']);
    }

    private function viewer(string $who): PhotoViewer
    {
        return match ($who) {
            'author' => new PhotoViewer(10, 1),
            'stranger' => new PhotoViewer(11, 1),
            'manager' => new PhotoViewer(13, 1, true),
            'door' => new PhotoViewer(12, 1, false, true, false, true),
            'sseadmin' => new PhotoViewer(13, 1, true, true, true, true),
            'clear' => new PhotoViewer(12, 1, false, false, false, true),
            'other' => new PhotoViewer(99, 2),
            default => new PhotoViewer(0, 0),
        };
    }

    /** @return array{int, int, int} rouge, vert, bleu du pixel (x, y) d'octets d'image */
    private function px(string $bytes, int $x, int $y): array
    {
        $im = imagecreatefromstring($bytes);
        self::assertNotFalse($im);
        $c = imagecolorat($im, $x, $y);

        return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
    }

    /** @param array{int, int, int} $a @param array{int, int, int} $b */
    private function near(array $a, array $b, int $tolerance): bool
    {
        return abs($a[0] - $b[0]) <= $tolerance && abs($a[1] - $b[1]) <= $tolerance && abs($a[2] - $b[2]) <= $tolerance;
    }

    // ——— Droits ———

    public function testPolicyMatrix(): void
    {
        $row = ['tenant_id' => 1, 'image_path' => 'recon/a.jpg', 'is_confidential' => 0, 'deleted_at' => null];
        $open = PhotoPolicy::facts($row, 10, false);
        $conf = PhotoPolicy::facts(['is_confidential' => 1] + $row, 10, false);
        $private = PhotoPolicy::facts(['image_path' => 'recon-private/abc.jpg'] + $row, 10, false);
        $sse = PhotoPolicy::facts($row, 10, true);
        $gone = PhotoPolicy::facts(['deleted_at' => '2026-01-01 00:00:00'] + $row, 10, false);

        $author = $this->viewer('author');
        $stranger = $this->viewer('stranger');
        $manager = $this->viewer('manager');
        $door = $this->viewer('door');
        $sseadmin = $this->viewer('sseadmin');
        $clear = $this->viewer('clear');
        $other = $this->viewer('other');
        $anon = $this->viewer('anon');

        // Lire
        self::assertTrue(PhotoPolicy::canView($stranger, $open));
        self::assertFalse(PhotoPolicy::canView($other, $open), 'autre communauté : absente');
        self::assertFalse(PhotoPolicy::canView($anon, $open), 'sans compte : absente');
        self::assertFalse(PhotoPolicy::canView($stranger, $gone), 'supprimée : absente');
        self::assertFalse(PhotoPolicy::canView($stranger, $conf), 'confidentiel sans habilitation : absente');
        self::assertFalse(PhotoPolicy::canView($stranger, $private), 'rangée en privé : absente');
        self::assertTrue(PhotoPolicy::canView($clear, $conf), 'habilitation « confidentiel »');
        self::assertFalse(PhotoPolicy::canView($stranger, $sse), 'versée au SSE : absente sans porte');
        self::assertFalse(PhotoPolicy::canView($author, $sse), 'même l\'auteur : la porte SSE décide');
        self::assertFalse(PhotoPolicy::canView($manager, $sse), 'même le gestionnaire du site : la porte SSE décide');
        self::assertTrue(PhotoPolicy::canView($door, $sse));

        // Voir sous les masques : auteur, gestionnaire, porte SSE
        self::assertTrue(PhotoPolicy::canSeeUnderMask($author, $open));
        self::assertTrue(PhotoPolicy::canSeeUnderMask($manager, $open));
        self::assertTrue(PhotoPolicy::canSeeUnderMask($door, $open));
        self::assertFalse(PhotoPolicy::canSeeUnderMask($stranger, $open));
        self::assertFalse(PhotoPolicy::canSeeUnderMask($other, $open));

        // Masquer : auteur ou gestionnaire seulement
        self::assertTrue(PhotoPolicy::canMask($author, $open));
        self::assertTrue(PhotoPolicy::canMask($manager, $open));
        self::assertFalse(PhotoPolicy::canMask($stranger, $open));
        self::assertFalse(PhotoPolicy::canMask($door, $open), 'la porte SSE ne donne pas le droit de masquer');
        self::assertTrue(PhotoPolicy::canRemoveMask($stranger, $open, ['author_user_id' => 11]), 'l\'auteur du masque peut le retirer');
        self::assertFalse(PhotoPolicy::canRemoveMask($stranger, $open, ['author_user_id' => 10]));

        // Visages
        self::assertTrue(PhotoPolicy::canTag($stranger, $open));
        self::assertFalse(PhotoPolicy::canTag($other, $open));
        self::assertFalse(PhotoPolicy::canTagSse($stranger, $open), 'personne SSE : porte SSE exigée');
        self::assertTrue(PhotoPolicy::canTagSse($door, $open));
        $sseTag = ['target_type' => 'sse', 'author_user_id' => 12];
        $memberTag = ['target_type' => 'member', 'author_user_id' => 10];
        self::assertFalse(PhotoPolicy::canSeeTag($stranger, $open, $sseTag), 'une personne SSE n\'existe pas hors de la porte SSE');
        self::assertTrue(PhotoPolicy::canSeeTag($stranger, $open, $memberTag));
        self::assertTrue(PhotoPolicy::canEditTag($author, $open, $memberTag), 'l\'auteur du cadre');
        self::assertTrue(PhotoPolicy::canEditTag($manager, $open, $memberTag), 'le gestionnaire');
        self::assertFalse(PhotoPolicy::canEditTag($stranger, $open, $memberTag));
        self::assertFalse(PhotoPolicy::canEditTag($manager, $open, $sseTag), 'gestionnaire du site sans porte SSE : cadre SSE hors d\'atteinte');
        self::assertTrue(PhotoPolicy::canEditTag($sseadmin, $open, ['target_type' => 'sse', 'author_user_id' => 1]), 'gestion SSE');

        // Commentaires
        self::assertTrue(PhotoPolicy::canComment($stranger, $open));
        self::assertFalse(PhotoPolicy::canComment($other, $open));
        self::assertTrue(PhotoPolicy::canEditComment($stranger, $open, ['author_user_id' => 11]));
        self::assertFalse(PhotoPolicy::canEditComment($manager, $open, ['author_user_id' => 11]), 'on ne réécrit pas le texte d\'un autre');
        self::assertTrue(PhotoPolicy::canDeleteComment($manager, $open, ['author_user_id' => 11]));
        self::assertFalse(PhotoPolicy::canDeleteComment($stranger, $open, ['author_user_id' => 12]));

        // Versement SSE
        self::assertFalse(PhotoPolicy::canTransferToSse($author, $open), 'sans porte SSE : pas de versement');
        self::assertFalse(PhotoPolicy::canTransferToSse($manager, $open));
        self::assertTrue(PhotoPolicy::canTransferToSse($door, $open));
        self::assertFalse(PhotoPolicy::canTransferToSse($door, $sse), 'déjà versée');
        self::assertFalse(PhotoPolicy::canWithdrawFromSse($door, $sse), 'retrait : droit de gestion SSE');
        self::assertTrue(PhotoPolicy::canWithdrawFromSse($sseadmin, $sse));
        self::assertFalse(PhotoPolicy::canWithdrawFromSse($sseadmin, $open), 'rien à retirer');
    }

    public function testRectanglesAndInputsAreValidated(): void
    {
        self::assertSame(['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.4], PhotoGeometry::rect(0.1, 0.2, 0.3, 0.4));
        self::assertSame(['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.4], PhotoGeometry::rect('0.1', '0.2', '0.3', '0.4'));
        self::assertNotNull(PhotoGeometry::rect(0, 0, 1, 1));
        self::assertSame(0.999, PhotoGeometry::rect(0.001, 0, 1.0, 1.0)['w'], 'léger dépassement ramené au bord');
        self::assertNull(PhotoGeometry::rect(0.9, 0.1, 0.3, 0.2), 'déborde à droite');
        self::assertNull(PhotoGeometry::rect(-0.1, 0.1, 0.3, 0.2), 'x négatif');
        self::assertNull(PhotoGeometry::rect(0.1, 0.1, 0, 0.2), 'largeur nulle');
        self::assertNull(PhotoGeometry::rect(0.1, 0.1, -0.2, 0.2), 'largeur négative');
        self::assertNull(PhotoGeometry::rect(0.1, 0.1, 0.005, 0.2), 'trop petit');
        self::assertNull(PhotoGeometry::rect(NAN, 0.1, 0.2, 0.2));
        self::assertNull(PhotoGeometry::rect(INF, 0.1, 0.2, 0.2));
        self::assertNull(PhotoGeometry::rect('abc', 0.1, 0.2, 0.2));
        self::assertNull(PhotoGeometry::rect('1e3', 0.1, 0.2, 0.2), 'pas de notation scientifique');
        self::assertNull(PhotoGeometry::rect(null, 0.1, 0.2, 0.2));
        self::assertNull(PhotoGeometry::rect([0.1], 0.1, 0.2, 0.2));
        self::assertSame('blur', PhotoGeometry::mode(' Blur '));
        self::assertNull(PhotoGeometry::mode('css'));
        self::assertNull(PhotoGeometry::mode(['blur']));
        self::assertSame('checkpoint', PhotoGeometry::label('checkpoint'));
        self::assertSame('Poste 2', PhotoGeometry::label('#Poste 2'));
        self::assertNull(PhotoGeometry::label('<b>x</b>'));
        self::assertNull(PhotoGeometry::label('a'));
        self::assertNull(PhotoGeometry::label(str_repeat('a', 41)));
        self::assertSame('etiquette ete', PhotoGeometry::labelNorm('Étiquette  Été'));
        $box = PhotoGeometry::pixels(['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2], 800, 600);
        self::assertLessThanOrEqual(80, $box[0], 'la boîte couvre le bord gauche');
        self::assertGreaterThan(239, $box[2]);
        self::assertSame(PhotoGeometry::hash([['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2, 'mode' => 'blur'], ['x' => 0.5, 'y' => 0.5, 'w' => 0.2, 'h' => 0.2, 'mode' => 'fill']]), PhotoGeometry::hash([['x' => 0.5, 'y' => 0.5, 'w' => 0.2, 'h' => 0.2, 'mode' => 'fill'], ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2, 'mode' => 'blur']]), 'ordre indifférent');
        self::assertNotSame(PhotoGeometry::hash([['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2, 'mode' => 'blur']]), PhotoGeometry::hash([['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2, 'mode' => 'fill']]));
        self::assertSame(['membre', 'ECHO'], PhotoLibraryService::mentionNames('salut @membre et @ECHO, a@b.fr'), 'une adresse e-mail n\'est pas une mention');
        self::assertSame("a\nb", PhotoLibraryService::cleanText("a\x00\r\nb\x07", 100));
    }

    // ——— Masques réels ———

    public function testMaskRendererReallyChangesPixels(): void
    {
        $this->needGd();
        $path = $this->tmp . '/src.jpg';
        $this->makeJpeg($path);
        $bytes = (string) file_get_contents($path);
        $zones = [['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.3, 'mode' => 'fill']];
        $out = PhotoMaskRenderer::apply($bytes, $zones, 'jpeg', 90);
        self::assertNotNull($out);
        self::assertTrue($this->near([0, 0, 0], $this->px($out, 160, 150), 8), 'aplat noir sous la zone');
        self::assertTrue($this->near($this->px($bytes, 700, 500), $this->px($out, 700, 500), 8), 'hors zone : inchangé');
        foreach (['pixel', 'blur'] as $mode) {
            $m = PhotoMaskRenderer::apply($bytes, [['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.3, 'mode' => $mode]], 'jpeg', 95);
            self::assertNotNull($m);
            // Les raies fines du « visage » ne survivent pas : l'écart entre deux colonnes voisines disparaît.
            $a = $this->px($bytes, 84, 150);
            $b = $this->px($bytes, 88, 150);
            self::assertNotSame($a, $b, 'l\'original a du détail');
            $ma = $this->px($m, 84, 150);
            $mb = $this->px($m, 88, 150);
            self::assertLessThan(60, abs($ma[1] - $mb[1]), $mode . ' : détail effacé');
        }
        self::assertNull(PhotoMaskRenderer::apply('pas une image', $zones));
        $thumb = PhotoMaskRenderer::apply($bytes, $zones, 'jpeg', 80, 200);
        self::assertNotNull($thumb);
        self::assertSame([200, 150], array_slice((array) getimagesizefromstring($thumb), 0, 2), 'miniature réduite');
        self::assertSame('png', PhotoMaskRenderer::formatFor('a.PNG'));
        self::assertSame('jpeg', PhotoMaskRenderer::formatFor('a.jpg'));
    }

    public function testServedImageIsMaskedForReadersWithoutRightAndOriginalForAuthor(): void
    {
        $this->needGd();
        [$service, $repo] = $this->world();
        $id = $this->photo($repo, 1);
        $original = (string) file_get_contents($this->tmp . '/recon/photo_1.jpg');
        $face = [160, 150];
        $before = $this->px($original, ...$face);

        $res = $service->addMask($this->viewer('author'), $id, ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.3, 'mode' => 'fill']);
        self::assertTrue($res['ok'], json_encode($res));
        self::assertSame('photo_library.mask_add', $this->events[0]['action'], 'journalisé');

        // Le fichier servi à tous (aussi par l'ancienne adresse publique) est déjà masqué : aucun original dessus.
        $onDisk = (string) file_get_contents($this->tmp . '/recon/photo_1.jpg');
        self::assertTrue($this->near([0, 0, 0], $this->px($onDisk, ...$face), 8), 'le fichier public porte le masque');
        self::assertNotSame($before, $this->px($onDisk, ...$face));
        // L'original est à l'abri, hors du dossier des photos.
        $kept = glob($this->tmp . '/originals/1/*.jpg') ?: [];
        self::assertCount(1, $kept);
        self::assertSame($before, $this->px((string) file_get_contents($kept[0]), ...$face), 'original conservé tel quel');
        self::assertTrue($this->near([0, 0, 0], $this->px($onDisk, ...$face), 8));

        foreach (['full', 'thumb', 'file'] as $variant) {
            $img = $service->image($this->viewer('stranger'), $id, $variant);
            self::assertNotNull($img, $variant);
            $k = $variant === 'thumb' ? 480 / 800 : 1;
            self::assertTrue($this->near([0, 0, 0], $this->px($img['bytes'], (int) (160 * $k), (int) (150 * $k)), 10), 'lecteur sans droit : masqué (' . $variant . ')');
        }
        foreach (['author', 'manager', 'door'] as $who) {
            $img = $service->image($this->viewer($who), $id, 'file');
            self::assertNotNull($img, $who);
            self::assertSame($before, $this->px($img['bytes'], ...$face), $who . ' : original');
        }
        $thumbAuthor = $service->image($this->viewer('author'), $id, 'thumb');
        self::assertNotNull($thumbAuthor);
        self::assertFalse($this->near([0, 0, 0], $this->px($thumbAuthor['bytes'], 96, 90), 40), 'miniature de l\'auteur : sans masque');
        // Des caches séparés : la miniature masquée n'est pas servie à l'auteur et inversement.
        $thumbStranger = $service->image($this->viewer('stranger'), $id, 'thumb');
        self::assertNotNull($thumbStranger);
        self::assertNotSame($thumbAuthor['bytes'], $thumbStranger['bytes']);

        // Détail : le lecteur sans droit est prévenu, l'auteur a le lien vers l'original.
        $dS = $service->detail($this->viewer('stranger'), $id);
        $dA = $service->detail($this->viewer('author'), $id);
        self::assertNotNull($dS);
        self::assertNotNull($dA);
        self::assertTrue($dS['maskedForYou']);
        self::assertNull($dS['photo']['original']);
        self::assertFalse($dA['maskedForYou']);
        self::assertNotNull($dA['photo']['original']);

        // Retrait du dernier masque : l'original reprend sa place, le cache est invalidé.
        $maskId = (int) $dA['masks'][0]['id'];
        self::assertFalse($service->removeMask($this->viewer('stranger'), $id, $maskId)['ok'], 'un tiers ne retire pas le masque');
        self::assertTrue($service->removeMask($this->viewer('author'), $id, $maskId)['ok']);
        self::assertSame($before, $this->px((string) file_get_contents($this->tmp . '/recon/photo_1.jpg'), ...$face), 'original rendu');
        self::assertSame([], glob($this->tmp . '/originals/1/*.jpg') ?: []);
        $again = $service->image($this->viewer('stranger'), $id, 'thumb');
        self::assertNotNull($again);
        self::assertFalse($this->near([0, 0, 0], $this->px($again['bytes'], 96, 90), 40), 'cache invalidé après le retrait');
    }

    public function testOnlyAuthorOrManagerMaskAndInvalidZonesAreRefused(): void
    {
        $this->needGd();
        [$service, $repo] = $this->world();
        $id = $this->photo($repo, 1);
        $zone = ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2, 'mode' => 'blur'];
        $r = $service->addMask($this->viewer('stranger'), $id, $zone);
        self::assertFalse($r['ok']);
        self::assertSame(403, $r['status']);
        self::assertSame([], $repo->masks, 'rien d\'enregistré');
        self::assertSame(404, $service->addMask($this->viewer('other'), $id, $zone)['status'], 'autre communauté : photo absente');
        self::assertSame(422, $service->addMask($this->viewer('author'), $id, ['x' => 0.9, 'y' => 0.1, 'w' => 0.5, 'h' => 0.2, 'mode' => 'blur'])['status']);
        self::assertSame(422, $service->addMask($this->viewer('author'), $id, ['mode' => 'css'] + $zone)['status']);
        self::assertTrue($service->addMask($this->viewer('manager'), $id, $zone)['ok'], 'le gestionnaire peut masquer');
        self::assertCount(1, $repo->masks(1, $id));
        self::assertSame([], $repo->masks(1, 999));
        $maskId = (int) $repo->masks(1, $id)[0]['id'];
        self::assertFalse($service->removeMask($this->viewer('door'), $id, $maskId)['ok'], 'ni auteur du masque, ni auteur de la photo, ni gestionnaire');
        self::assertTrue($service->removeMask($this->viewer('author'), $id, $maskId)['ok'], 'l\'auteur de la photo retire le masque d\'un gestionnaire');
        self::assertSame([], $repo->masks(1, $id));
    }

    // ——— Visages, SSE, commentaires ———

    public function testFaceTagsAreManualAndSseTargetsNeedTheGate(): void
    {
        [$service, $repo] = $this->world();
        $id = $this->photo($repo, 1);
        $rect = ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2];
        $stranger = $this->viewer('stranger');

        self::assertTrue($service->addTag($stranger, $id, $rect + ['type' => 'member', 'target' => 12])['ok'], 'membre de la communauté');
        self::assertTrue($service->addTag($stranger, $id, $rect + ['type' => 'unknown'])['ok'], 'inconnu');
        self::assertFalse($service->addTag($stranger, $id, $rect + ['type' => 'member', 'target' => 99])['ok'], 'membre d\'une autre communauté');
        self::assertFalse($service->addTag($stranger, $id, $rect + ['type' => 'member', 'target' => 'abc'])['ok']);
        $sse = $service->addTag($stranger, $id, $rect + ['type' => 'sse', 'target' => 77]);
        self::assertFalse($sse['ok'], 'personne SSE sans porte : refusée');
        self::assertSame('Personne introuvable.', $sse['error'], 'sans révéler si la fiche existe');
        self::assertFalse($service->addTag($this->viewer('door'), $id, $rect + ['type' => 'sse', 'target' => 5])['ok'], 'fiche SSE inexistante');
        self::assertTrue($service->addTag($this->viewer('door'), $id, ['x' => 0.5] + $rect + ['type' => 'sse', 'target' => 77])['ok'], 'avec la porte SSE');
        self::assertFalse($service->addTag($stranger, $id, ['w' => 0.0] + $rect + ['type' => 'unknown'])['ok'], 'rectangle invalide');
        self::assertFalse($service->addTag($this->viewer('other'), $id, $rect + ['type' => 'unknown'])['ok'], 'autre communauté');

        // Le lecteur sans porte ne voit ni le cadre SSE ni un commentaire ancré dessus.
        $tags = $repo->tags(1, $id);
        $sseTag = array_values(array_filter($tags, static fn (array $t): bool => $t['target_type'] === 'sse'))[0];
        self::assertTrue($service->addComment($this->viewer('door'), $id, ['body' => 'Même sujet qu\'hier.', 'anchor' => 'tag', 'tag' => (int) $sseTag['id']])['ok']);
        self::assertFalse($service->addComment($stranger, $id, ['body' => 'x', 'anchor' => 'tag', 'tag' => (int) $sseTag['id']])['ok'], 'ancre sur un visage SSE : refusée sans porte');
        $dStranger = $service->detail($stranger, $id);
        $dDoor = $service->detail($this->viewer('door'), $id);
        self::assertNotNull($dStranger);
        self::assertNotNull($dDoor);
        self::assertCount(2, $dStranger['tags'], 'membre + inconnu seulement');
        self::assertCount(3, $dDoor['tags']);
        self::assertSame([], $dStranger['comments'], 'commentaire ancré sur la personne SSE : absent');
        self::assertCount(1, $dDoor['comments']);
        self::assertStringNotContainsString('sse', strtolower((string) json_encode($dStranger['tags'])), 'aucune trace du sujet SSE');

        // Modifier ou retirer : l'auteur du cadre ou le gestionnaire.
        $mine = array_values(array_filter($tags, static fn (array $t): bool => $t['target_type'] === 'unknown'))[0];
        self::assertFalse($service->removeTag($this->viewer('door'), $id, (int) $mine['id'])['ok'], 'ni auteur ni gestionnaire');
        self::assertTrue($service->updateTag($stranger, $id, (int) $mine['id'], ['type' => 'member', 'target' => 10])['ok']);
        self::assertSame('member', $repo->tag(1, $id, (int) $mine['id'])['target_type']);
        self::assertFalse($service->removeTag($this->viewer('manager'), $id, (int) $sseTag['id'])['ok'], 'gestionnaire du site sans porte SSE : cadre SSE hors d\'atteinte');
        self::assertTrue($service->removeTag($this->viewer('manager'), $id, (int) $mine['id'])['ok'], 'le gestionnaire retire un cadre d\'autrui');
        self::assertTrue($service->removeTag($this->viewer('sseadmin'), $id, (int) $sseTag['id'])['ok'], 'gestion SSE');
    }

    public function testSseTransferNeedsTheGateAndMakesThePhotoVanishForOthers(): void
    {
        [$service, $repo] = $this->world();
        $id = $this->photo($repo, 1);
        $other = $this->photo($repo, 2);

        $r = $service->transferToSse($this->viewer('author'), $id, null, 'VIPER');
        self::assertFalse($r['ok']);
        self::assertSame(404, $r['status'], 'sans porte SSE : la fonction n\'existe pas');
        self::assertSame(404, $service->transferToSse($this->viewer('manager'), $id, null, 'FOX')['status']);
        self::assertSame([], $repo->transfers);
        self::assertTrue($service->transferToSse($this->viewer('door'), $id, 77, 'HAWK')['ok']);
        self::assertSame('photo_library.sse_transfer', end($this->events)['action'], 'journalisé');
        self::assertFalse($service->transferToSse($this->viewer('door'), $id, null, 'HAWK')['ok'], 'déjà versée');

        // Absente pour qui n'a pas la porte : grille, fiche, image, écritures.
        foreach (['author', 'stranger', 'manager'] as $who) {
            $v = $this->viewer($who);
            self::assertNull($service->detail($v, $id), $who . ' : fiche absente');
            self::assertNull($service->image($v, $id, 'thumb'), $who . ' : image absente');
            self::assertSame(404, $service->addComment($v, $id, ['body' => 'x'])['status'], $who . ' : écriture refusée comme une photo inconnue');
            $ids = array_map(static fn (array $c): int => $c['id'], $service->browse($v, [], 'recent', 1)['rows']);
            self::assertSame([$other], $ids, $who . ' : grille sans la photo versée');
        }
        $ids = array_map(static fn (array $c): int => $c['id'], $service->browse($this->viewer('door'), [], 'recent', 1)['rows']);
        self::assertContains($id, $ids);
        $card = array_values(array_filter($service->browse($this->viewer('door'), [], 'recent', 1)['rows'], static fn (array $c): bool => $c['id'] === $id))[0];
        self::assertTrue($card['sse'], 'pastille SSE');
        // Filtre « statut SSE » réservé à la porte.
        self::assertCount(1, $service->browse($this->viewer('door'), ['sse' => 'sse'], 'recent', 1)['rows']);
        self::assertSame([$other], array_map(static fn (array $c): int => $c['id'], $service->browse($this->viewer('stranger'), ['sse' => 'sse'], 'recent', 1)['rows']), 'sans porte, le filtre n\'ouvre rien');

        // Retrait : gestion SSE seulement.
        self::assertSame(404, $service->withdrawFromSse($this->viewer('door'), $id, 'HAWK')['status'], 'porte sans gestion : refusé');
        self::assertTrue($service->withdrawFromSse($this->viewer('sseadmin'), $id, 'FOX')['ok']);
        self::assertNotNull($service->detail($this->viewer('stranger'), $id), 'de nouveau visible après retrait');
    }

    public function testConfidentialAndOtherTenantPhotosAreAbsent(): void
    {
        [$service, $repo] = $this->world();
        $open = $this->photo($repo, 1);
        $conf = $this->photo($repo, 2, ['is_confidential' => 1]);
        $private = $this->photo($repo, 3, ['image_path' => 'recon-private/photo_3.jpg']);
        $foreign = $this->photo($repo, 4, ['tenant_id' => 2]);
        $deleted = $this->photo($repo, 5, ['deleted_at' => '2026-11-01 00:00:00']);
        $feed = $this->photo($repo, 6, ['unit_name' => 'helmet:abc']);

        $ids = static fn (array $page): array => array_map(static fn (array $c): int => $c['id'], $page['rows']);
        self::assertSame([$open], $ids($service->browse($this->viewer('stranger'), [], 'recent', 1)));
        $seen = $ids($service->browse($this->viewer('clear'), [], 'recent', 1));
        sort($seen);
        self::assertSame([$open, $conf, $private], $seen, 'habilité « confidentiel » : les clichés confidentiels, jamais les autres communautés ni les flux');
        foreach ([$conf, $private, $foreign, $deleted, $feed] as $hidden) {
            self::assertNull($service->detail($this->viewer('stranger'), $hidden), 'photo ' . $hidden . ' absente');
            self::assertNull($service->image($this->viewer('stranger'), $hidden, 'full'));
        }
        self::assertNull($service->detail($this->viewer('anon'), $open), 'sans compte');
        self::assertSame(0, $service->browse($this->viewer('anon'), [], 'recent', 1)['total']);
    }

    public function testCommentsMentionsNotificationsAndReports(): void
    {
        [$service, $repo] = $this->world();
        $id = $this->photo($repo, 1);
        $stranger = $this->viewer('stranger');

        self::assertSame(422, $service->addComment($stranger, $id, ['body' => '  '])['status']);
        $res = $service->addComment($stranger, $id, ['body' => 'Bien vu @HAWK et @inconnu, merci @ECHO.']);
        self::assertTrue($res['ok']);
        $users = array_map(static fn (array $n): int => $n[0], $this->notes);
        self::assertContains(12, $users, 'HAWK cité : cloche');
        self::assertContains(10, $users, 'l\'auteur de la photo est prévenu');
        self::assertNotContains(11, $users, 'on ne se notifie pas soi-même');
        self::assertCount(2, $this->notes);
        self::assertSame('12,11', $repo->comments(1, $id)[0]['mentions'], 'seul un nom qui désigne un membre est une mention');

        // Ancre invalide
        self::assertSame(422, $service->addComment($stranger, $id, ['body' => 'x', 'anchor' => 'zone', 'x' => 2, 'y' => 0, 'w' => 1, 'h' => 1])['status']);
        self::assertSame(422, $service->addComment($stranger, $id, ['body' => 'x', 'anchor' => 'tag', 'tag' => 999])['status']);
        self::assertTrue($service->addComment($stranger, $id, ['body' => 'Ici.', 'anchor' => 'zone', 'x' => 0.2, 'y' => 0.2, 'w' => 0.2, 'h' => 0.2])['ok']);

        // Modifier / supprimer
        $cid = (int) $res['id'];
        self::assertFalse($service->editComment($this->viewer('manager'), $id, $cid, ['body' => 'réécrit'])['ok'], 'pas le texte d\'un autre');
        self::assertTrue($service->editComment($stranger, $id, $cid, ['body' => 'Corrigé'])['ok']);
        self::assertSame('Corrigé', $repo->comment(1, $id, $cid)['body']);
        self::assertFalse($service->deleteComment($this->viewer('door'), $id, $cid)['ok'], 'ni auteur ni gestionnaire');
        self::assertTrue($service->reportComment($this->viewer('door'), $id, $cid, 'inappropriate', 'hors sujet')['ok'], 'signalement par le mécanisme existant');
        self::assertTrue($service->deleteComment($this->viewer('manager'), $id, $cid)['ok'], 'le gestionnaire supprime');
        self::assertNull($repo->comment(1, $id, $cid));
        self::assertSame(404, $service->reportComment($this->viewer('door'), $id, $cid, 'other', '')['status']);

        // Photo confidentielle : jamais de cloche.
        $conf = $this->photo($repo, 2, ['is_confidential' => 1]);
        $this->notes = [];
        self::assertTrue($service->addComment($this->viewer('clear'), $conf, ['body' => '@VIPER regarde'])['ok']);
        self::assertSame([], $this->notes, 'aucune notification sur une photo confidentielle');
    }

    public function testLabelsAreValidatedAndFiltered(): void
    {
        [$service, $repo] = $this->world();
        $a = $this->photo($repo, 1);
        $b = $this->photo($repo, 2);
        $v = $this->viewer('stranger');
        self::assertTrue($service->addLabel($v, $a, 'Checkpoint nord')['ok']);
        self::assertFalse($service->addLabel($v, $a, '<script>')['ok']);
        self::assertTrue($service->addLabel($v, $a, 'checkpoint NORD')['ok'], 'même étiquette : sans doublon');
        self::assertSame(['Checkpoint nord'], $repo->labels(1, $a));
        self::assertSame([$a], array_map(static fn (array $c): int => $c['id'], $service->browse($v, ['label' => 'CHECKPOINT nord'], 'recent', 1)['rows']));
        self::assertSame([$b, $a], array_map(static fn (array $c): int => $c['id'], $service->browse($v, ['sse' => 'none'], 'recent', 1)['rows']));
        self::assertTrue($service->removeLabel($v, $a, 'CHECKPOINT nord')['ok']);
        self::assertSame([], $repo->labels(1, $a));
    }

    public function testNotMigratedStateReadsButDoesNotWrite(): void
    {
        [$service, $repo] = $this->world();
        $id = $this->photo($repo, 1);
        $repo->migrated = false;
        $v = $this->viewer('author');
        self::assertFalse($service->browse($v, [], 'recent', 1)['ready']);
        self::assertCount(1, $service->browse($v, [], 'recent', 1)['rows'], 'la grille reste lisible sans les nouvelles tables');
        $d = $service->detail($v, $id);
        self::assertNotNull($d);
        self::assertFalse($d['ready']);
        $w = $service->addTag($v, $id, ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2, 'type' => 'unknown']);
        self::assertSame(503, $w['status']);
    }

    // ——— Écritures protégées, câblage ———

    public function testEveryWriteGoesThroughCsrfSessionAndRateLimit(): void
    {
        $src = $this->read('app/Controllers/Web/PhotoLibraryController.php');
        self::assertMatchesRegularExpression('/private function write\(.*?Csrf::verify\(\$request\).*?tooManyAttempts.*?\$do\(\$v, \$id\)/s', $src, 'jeton avant tout, puis plafond, puis le service');
        self::assertLessThan(strpos($src, '$do($v, $id)'), strpos($src, 'Csrf::verify($request)'));
        preg_match_all('/public function (\w+)\(Request \$request, array \$params = \[\]\): Response\s*\{(.*?)\n    \}/s', $src, $m, PREG_SET_ORDER);
        $writes = ['tagAdd', 'tagUpdate', 'tagRemove', 'maskAdd', 'maskRemove', 'commentAdd', 'commentEdit', 'commentDelete', 'commentReport', 'labelAdd', 'labelRemove', 'sseTransfer', 'sseWithdraw'];
        $seen = [];
        foreach ($m as $fn) {
            if (in_array($fn[1], $writes, true)) {
                self::assertStringContainsString('$this->write(', $fn[2], $fn[1] . ' passe par le cadre commun');
                $seen[] = $fn[1];
            }
        }
        self::assertSame([], array_diff($writes, $seen));
        $routes = $this->read('routes/web.php');
        foreach ($writes as $w) {
            self::assertMatchesRegularExpression('/\$router->post\(\'\/jnet\/photos\/[^\']+\', \[\\\\App\\\\Controllers\\\\Web\\\\PhotoLibraryController::class, \'' . $w . '\'\], \[AuthMiddleware::class\]\);/', $routes, $w . ' est une route POST authentifiée');
        }
        self::assertDoesNotMatchRegularExpression('/\$router->get\(\'\/jnet\/photos[^\']*\', \[\\\\App\\\\Controllers\\\\Web\\\\PhotoLibraryController::class, \'(?:tag|mask|comment|label|sse)[A-Za-z]*\'\]/', $routes, 'aucune écriture en GET');
        self::assertLessThan(strpos($routes, "'/jnet/photos/{id}'"), strpos($routes, "'/jnet/photos/cibles'"), 'cibles avant {id}');
        foreach (['/jnet/photos', '/jnet/photos/{id}', '/jnet/photos/{id}/image', '/jnet/photos/{id}/donnees', '/jnet/photos/cibles'] as $get) {
            self::assertStringContainsString("\$router->get('" . $get . "', [\\App\\Controllers\\Web\\PhotoLibraryController::class", $routes);
        }
        // Chaque route lit le lecteur depuis la session, jamais depuis la requête.
        self::assertStringNotContainsString("input('user_id'", $src);
        self::assertStringNotContainsString("query('tenant", $src);
    }

    public function testViewerRightsComeFromTheSameSseGatesAsJnet(): void
    {
        $src = $this->read('app/Controllers/Web/PhotoLibraryController.php');
        self::assertStringContainsString('canEnterWithoutCode() && $sse->hasAcceptedConfidentiality()', $src, 'même porte que JnetDashboardService::mayReadIntelligence');
        self::assertStringContainsString('!$sse->isGuest()', $src);
        self::assertStringContainsString('SseCaseRepository::CLASS_CONFIDENTIAL', $src, 'clichés confidentiels : même habilitation que le poste ATAK');
        self::assertStringContainsString('moderation_user_can_moderate()', $src);
        $dash = $this->read('app/Services/Jnet/JnetDashboardService.php');
        self::assertStringContainsString('$sse->canEnterWithoutCode() && $sse->hasAcceptedConfidentiality()', $dash);
        // Le contrôleur reprend la coque de JNET : si render() change de forme, ce test le dit.
        $m = new \ReflectionMethod(\App\Controllers\Web\JnetPortalController::class, 'render');
        self::assertTrue($m->isPrivate());
        self::assertSame(['view', 'title', 'extra', 'activeNav', 'moreCss', 'moreJs'], array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $m->getParameters()));
    }

    public function testNothingIsSentOutsideAndNoBiometrics(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/app/Services/PhotoLibrary/*.php') as $file) {
            $code = (string) file_get_contents($file);
            // Hors commentaires : aucun appel réseau, aucune bibliothèque de reconnaissance.
            $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $code);
            foreach (['curl_', 'fsockopen', 'stream_socket_client', 'Http', 'Guzzle', 'file_get_contents(\'http', 'dlib', 'opencv', 'face_', 'Rekognition', 'getimagesizefromstring($url'] as $bad) {
                self::assertStringNotContainsString($bad, $code, basename($file) . ' : ' . $bad);
            }
        }
        $service = $this->read('app/Services/PhotoLibrary/PhotoLibraryService.php');
        self::assertStringContainsString('MANUEL', $service);
        self::assertStringContainsString('Aucun calcul biométrique', $service);
    }

    public function testScriptNeverBuildsHtmlFromData(): void
    {
        $js = $this->read('public/assets/js/jnet_photos.js');
        foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval('] as $bad) {
            self::assertStringNotContainsString($bad, $js, $bad);
        }
        self::assertStringContainsString("'X-CSRF-Token': csrf", $js);
        self::assertStringContainsString("body.append('_csrf_token', csrf)", $js);
        self::assertStringContainsString("key === 'Escape'", $js);
        self::assertStringContainsString("e.key === 'ArrowLeft'", $js);
        self::assertStringContainsString("e.key === 'ArrowRight'", $js);
        // Aucun masque par calque : l'image ne reçoit ni filtre ni zone peinte par-dessus pour cacher.
        self::assertStringNotContainsString('backdrop-filter', $this->read('public/assets/css/jnet_photos.css'));
        self::assertStringNotContainsString('filter: blur', $this->read('public/assets/css/jnet_photos.css'));
        self::assertStringContainsString('dans l’image servie aux autres', $js, 'dit que le masque est dans l\'image');
    }

    // ——— Migration ———

    public function testMigrationStepIsIdempotentAndReversible(): void
    {
        $sql = $this->read('migrations/2026_11_15_080_photo_library.sql');
        foreach (['photo_library_tags', 'photo_library_masks', 'photo_library_comments', 'photo_library_labels', 'photo_library_state', 'photo_library_sse'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table . ' (', $sql);
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $table . ';', $this->read('migrations/rollback/photo_library_down.sql'));
        }
        self::assertStringNotContainsString('INSERT', $sql, 'aucune donnée ajoutée');
        self::assertStringNotContainsString('DROP', $sql);
        self::assertStringContainsString('ON DELETE CASCADE', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_photo_library_labels', $sql);
        $registry = (string) file_get_contents(dirname(__DIR__, 2) . '/database/steps/registry.php');
        self::assertStringContainsString("'id' => 'photo_library'", $registry);
        self::assertStringContainsString('4570_photo_library.php', $registry);
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/4570_photo_library.php');
        $steps = array_map('basename', glob(dirname(__DIR__, 2) . '/database/steps/4*.php') ?: []);
        sort($steps);
        // Numéro unique : d'autres étapes peuvent suivre (les numéros croissent), aucune ne reprend 4570.
        self::assertContains('4570_photo_library.php', $steps);
        self::assertCount(1, array_filter($steps, static fn (string $f): bool => str_starts_with($f, '4570_')), 'numéro d\'étape unique');
        $step = $this->read('database/steps/4570_photo_library.php');
        self::assertStringContainsString('tableExists', $step);
        self::assertStringContainsString('addColumnIfMissing(\'recon_images\', \'author_user_id\'', $step);
        self::assertStringContainsString('migrations/rollback/photo_library_down.sql', $step);

        if (!extension_loaded('pdo_sqlite')) {
            self::markTestIncomplete('pdo_sqlite requis pour rejouer l\'étape.');
        }
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE recon_images (id INTEGER PRIMARY KEY, tenant_id INTEGER, image_path TEXT)');
        $pdo->changes = 0;
        $pdo->ddl = [];
        $first = $this->runStep($pdo);
        self::assertStringContainsString('Tables créées', $first);
        $schema = new \App\Support\Migrations\SchemaInspector($pdo);
        foreach (['photo_library_tags', 'photo_library_masks', 'photo_library_comments', 'photo_library_labels', 'photo_library_state', 'photo_library_sse'] as $table) {
            self::assertTrue($schema->tableExists($table), $table);
        }
        self::assertTrue($schema->columnExists('recon_images', 'author_user_id'));
        $pdo->changes = 0;
        $pdo->ddl = [];
        $second = $this->runStep($pdo);
        self::assertSame(0, $pdo->changes, 'second passage : ' . implode(' | ', $pdo->ddl));
        self::assertStringContainsString('déjà présentes', $second);
        // Sans recon_images (base sans ATAK) l'étape passe aussi.
        $bare = new MysqlDdlSqlitePdo();
        $this->runStep($bare);
        self::assertTrue((new \App\Support\Migrations\SchemaInspector($bare))->tableExists('photo_library_sse'));
    }

    private function runStep(\PDO $pdo): string
    {
        $root = dirname(__DIR__, 2);
        ob_start();
        try {
            require $root . '/database/steps/4570_photo_library.php';
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    // ——— Vues ———

    public function testViewsImportTheClassesTheyCall(): void
    {
        $files = ['views/jnet/photos.php', 'app/Controllers/Web/PhotoLibraryController.php', 'app/Services/PhotoLibrary/PhotoLibraryService.php',
            'app/Services/PhotoLibrary/PhotoSseTransfer.php', 'app/Services/PhotoLibrary/PhotoVault.php', 'app/Repositories/PhotoLibraryRepository.php', 'app/Services/PhotoLibrary/PhotoMaskRenderer.php'];
        foreach ($files as $rel) {
            $code = $this->read($rel);
            preg_match('/^namespace ([\w\\\\]+);/m', $code, $ns);
            $namespace = $ns[1] ?? '';
            $uses = [];
            preg_match_all('/^use ([\w\\\\]+)(?: as (\w+))?;/m', $code, $um, PREG_SET_ORDER);
            foreach ($um as $u) {
                $uses[$u[2] ?? substr(strrchr('\\' . $u[1], '\\'), 1)] = true;
            }
            $stripped = (string) preg_replace('~/\*.*?\*/|//[^\n]*|\'(?:[^\'\\\\]|\\\\.)*\'~s', '', $code);
            preg_match_all('/(?<![\\\\\w$>:])([A-Z][A-Za-z0-9_]+)(?:::|\s*\()/', $stripped, $refs);
            foreach (array_unique($refs[1]) as $class) {
                if (isset($uses[$class]) || in_array($class, ['self', 'static', 'parent', 'PHP_EOL', 'JSON_UNESCAPED_UNICODE'], true)) {
                    continue;
                }
                if (preg_match('/^[A-Z_]+$/', $class) === 1) {
                    continue;
                }
                $sibling = $namespace !== '' ? dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($namespace, 4)) . '/' . $class . '.php' : '';
                self::assertTrue($sibling !== '' && is_file($sibling), $rel . ' appelle ' . $class . ' sans « use »');
            }
        }
        self::assertStringContainsString('use App\\Core\\Csrf;', $this->read('views/jnet/photos.php'));
    }

    /** @param array<string, mixed> $vars */
    private function renderView(array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require dirname(__DIR__, 2) . '/views/jnet/photos.php';
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    public function testViewRendersGridEmptyStateAndEscapesEverything(): void
    {
        $evil = '<script>alert(1)</script>"\'&';
        $card = ['id' => 7, 'title' => $evil, 'author' => $evil, 'unit' => '1re', 'album' => $evil, 'grid' => $evil, 'when' => '2026-11-14 21:00:00', 'sse' => true,
            'confidential' => false, 'tags' => 2, 'comments' => 1, 'masks' => 1, 'thumb' => '/jnet/photos/7/image?t=thumb&v=ab', 'href' => '/jnet/photos/7'];
        $base = ['photos' => ['ready' => true, 'rows' => [$card], 'total' => 31, 'page' => 2, 'pages' => 3, 'facets' => ['authors' => [$evil], 'units' => [], 'albums' => [$evil], 'labels' => [$evil]], 'canMaskHere' => true],
            'photoFilters' => ['q' => $evil, 'label' => $evil, 'person' => 'm5'], 'photoSort' => 'auteur', 'photoOpen' => ['photo' => ['title' => '</script><b>x']], 'photoViewer' => ['sse' => true, 'manager' => false],
            'photoCsrf' => 'tok', 'photoBase' => '/jnet/photos'];
        $html = $this->renderView($base);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('</script><b>', $html, 'la fiche JSON ne referme pas la balise script');
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('data-ph-open="7"', $html);
        self::assertStringContainsString('ph-pill--sse', $html, 'pastille SSE');
        self::assertStringContainsString('name="sse"', $html, 'filtre statut SSE pour la porte');
        self::assertStringContainsString('Page 2 sur 3', $html);
        self::assertStringContainsString('data-csrf="tok"', $html);
        self::assertStringNotContainsString('name="sse"', $this->renderView(['photoViewer' => ['sse' => false, 'manager' => false]] + $base), 'filtre SSE absent sans porte');

        $empty = $this->renderView(['photos' => ['ready' => false, 'rows' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'facets' => ['authors' => [], 'units' => [], 'albums' => [], 'labels' => []], 'canMaskHere' => false],
            'photoFilters' => [], 'photoSort' => 'recent', 'photoOpen' => null, 'photoViewer' => ['sse' => false, 'manager' => false], 'photoCsrf' => 't', 'photoBase' => '/jnet/photos']);
        self::assertStringContainsString('Pas encore de photo', $empty);
        self::assertStringContainsString('seront disponibles après la prochaine mise à jour de la base', $empty, 'migration pas passée : dit pourquoi');
        self::assertStringContainsString('Réinitialiser les filtres', $this->renderView(['photoFilters' => ['q' => 'zzz']] + $base));
        $noMatch = $this->renderView(['photos' => ['rows' => [], 'total' => 0, 'pages' => 0] + $base['photos'], 'photoFilters' => ['q' => 'zzz']] + $base);
        self::assertStringContainsString('Aucune photo ne correspond', $noMatch);
        // Pas de surtitre au-dessus d'un titre, pas d'emoji.
        self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html . $empty));
    }

    public function testCssAndScriptCoverTheMobileAndKeyboardPromises(): void
    {
        $css = $this->read('public/assets/css/jnet_photos.css');
        self::assertStringContainsString('@media (max-width: 640px)', $css);
        self::assertStringContainsString('repeat(2, minmax(0, 1fr))', $css, 'deux colonnes sur 375 px');
        self::assertStringContainsString('var(--ja-', $css, 'jetons de jnet_athena.css');
        self::assertStringNotContainsString('eyebrow', $css);
        $js = $this->read('public/assets/js/jnet_photos.js');
        self::assertStringContainsString('window.addEventListener(\'popstate\'', $js, 'le bouton Précédent du navigateur ferme le lecteur');
        self::assertStringContainsString('role="dialog" aria-modal="true"', $this->read('views/jnet/photos.php'));
    }
}
