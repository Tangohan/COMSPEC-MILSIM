<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseFileRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Sse\SseSeekPhotoService;
use App\Support\TerrainUploadedImage;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFilesSqlite;

/**
 * Images des fiches SEEK II, de bout en bout sur fixtures : réception du cliché envoyé avec la référence REC (le jeu ne
 * crée pas de fiche personne avec « SIGNER ET ENVOYER »), rangement hors du dossier public, lien à l'identité (même si la
 * photo arrive avant la fiche), adresse protégée, isolation des communautés, types et tailles, chemins hors dossier,
 * états vides, et câblage du site et du mod (route protégée, droits, source SQF).
 */
final class SseSeekImagesTest extends TestCase
{
    private string $dir = '';

    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seekimg_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            SseFilesSqlite::close();
            $this->pdo = null;
        }
        if ($this->dir !== '' && is_dir($this->dir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($this->dir);
        }
    }

    private function db(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }

        return $this->pdo ??= SseFilesSqlite::open();
    }

    /** JPEG minimal (sans GD) avec un segment EXIF portant une position secrète, comme une capture de téléphone. */
    private static function jpegWithExif(): string
    {
        $jpeg = "\xFF\xD8"
            . "\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00" . 'imagedata'
            . "\xFF\xD9";
        $payload = "Exif\0\0GPSLatitude 48.8566 SECRET-POSITION";

        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);
    }

    /** @return array{name: string, tmp_name: string, size: int, error: int} */
    private function upload(string $bytes, ?int $declaredSize = null): array
    {
        $p = $this->dir . DIRECTORY_SEPARATOR . 'up_' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($p, $bytes);

        return ['name' => 'COMSPEC_SSE_Face_1.png', 'tmp_name' => $p, 'size' => $declaredSize ?? strlen($bytes), 'error' => UPLOAD_ERR_OK];
    }

    private function service(): SseSeekPhotoService
    {
        $this->db();

        return new SseSeekPhotoService(new SseFileRecordRepository(), new SseFileRepository(), $this->dir . DIRECTORY_SEPARATOR . 'store');
    }

    private function identity(int $tenant, string $last, string $first): int
    {
        return (new SseIdentityRepository())->create($tenant, ['last_name' => $last, 'first_name' => $first, 'birth_date' => '1991-03-14', 'sex' => 'M', 'nationality' => 'USA']);
    }

    /** Fiche SEEK II du jeu, rattachée (ou non) à une identité comme le fait le contrôle N4. */
    private function seekRecord(int $tenant, string $rec, ?int $identityId): int
    {
        $files = new SseFileRepository();
        $stored = $files->upsertSeek($tenant, [
            'rec' => $rec, 'idempotency_key' => 'SEEK-' . $rec . '-1', 'bio_key' => 'BIO-' . $rec, 'last_name' => 'Haddad', 'first_name' => 'Karim',
            'subject_unknown' => 0, 'verdict' => 'concordant', 'enrolled' => 0, 'mod_fingers' => 1, 'mod_iris' => 0, 'mod_face' => 1,
            'grid' => '123456', 'game_time' => '2026-11-15 14:20', 'controller_callsign' => 'VIPER 1-1', 'unit' => 'Alpha',
        ]);
        if ($identityId !== null) {
            $files->linkSeek($stored['id'], $tenant, $identityId, null, null, null);
        }

        return $stored['id'];
    }

    // ------------------------------------------------------------------ réception

    public function testRecReferenceIsNotANumericPersonId(): void
    {
        self::assertTrue(SseSeekPhotoService::isRecReference('REC-261115-4821'));
        self::assertTrue(SseSeekPhotoService::isRecReference('rec-261115-4821'), 'la casse est normalisée');
        self::assertSame('REC-261115-4821', SseSeekPhotoService::normalizeRec(' rec-261115-4821 '));
        self::assertFalse(SseSeekPhotoService::isRecReference('1234'), 'un identifiant de fiche personne reste une fiche personne');
        self::assertFalse(SseSeekPhotoService::isRecReference(''));
        self::assertFalse(SseSeekPhotoService::isRecReference('../../etc/passwd'));
        self::assertFalse(SseSeekPhotoService::isRecReference('REC 1; DROP TABLE'));
        self::assertFalse(SseSeekPhotoService::isRecReference(str_repeat('A', 41)));
    }

    public function testPhotoBeforeRecordIsStoredPrivatelyThenLinkedToTheIdentity(): void
    {
        $this->db();
        $svc = $this->service();
        $identity = $this->identity(1, 'Haddad', 'Karim');

        // 1. Le cliché arrive d'abord (file hors ligne du jeu : la fiche est en retard).
        $r = $svc->store(1, 'REC-261115-4821', $this->upload(self::jpegWithExif()), ['angle' => 'face@portrait,q=88', 'author' => 'VIPER 1-1', 'grid' => '123456']);
        self::assertArrayNotHasKey('error', $r);
        self::assertFalse($r['duplicate']);
        self::assertNull($r['identity_id'], 'fiche pas encore reçue : photo à rattacher, jamais perdue');
        self::assertSame([], $svc->forIdentity(1, $identity), 'pas de lien connu : l’identité n’affiche rien');

        // Rangée à l'abri, hors du dossier public, sans la position secrète des métadonnées.
        $row = $this->db()->query('SELECT * FROM sse_file_photos WHERE id = ' . (int) $r['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('seek', $row['source']);
        self::assertSame('REC-261115-4821', $row['source_ref']);
        self::assertTrue(SseSeekPhotoService::isPrivatePath((string) $row['path']));
        self::assertStringNotContainsString('uploads/', (string) $row['path']);
        $abs = $svc->absolutePath(['path' => $row['path']], 1);
        self::assertNotNull($abs);
        self::assertStringContainsString($this->dir, (string) $abs);
        self::assertStringNotContainsString('SECRET-POSITION', (string) file_get_contents((string) $abs), 'EXIF retiré');

        // 2. La fiche arrive, rattachée à l'identité (papier ou clé biométrique) : la photo la rejoint.
        $this->seekRecord(1, 'REC-261115-4821', $identity);
        $svc->attachForRec(1, 'REC-261115-4821', $identity);
        $photos = $svc->forIdentity(1, $identity);
        self::assertCount(1, $photos);
        self::assertSame('REC-261115-4821', $photos[0]['source_ref']);
        self::assertSame('concordant', $photos[0]['seek']['verdict']);
        self::assertSame('123456', $photos[0]['seek']['grid']);
        self::assertSame('VIPER 1-1', $photos[0]['seek']['controller_callsign']);
        self::assertStringContainsString('/image', (string) $photos[0]['url']);
    }

    public function testRecordBeforePhotoShowsTheImageWithoutAnyAttachStep(): void
    {
        $svc = $this->service();
        $identity = $this->identity(1, 'Haddad', 'Karim');
        $this->seekRecord(1, 'REC-261115-0001', $identity);
        $r = $svc->store(1, 'REC-261115-0001', $this->upload(self::jpegWithExif()));
        self::assertSame($identity, $r['identity_id'], 'la photo prend l’identité de la fiche déjà reçue');
        self::assertCount(1, $svc->forIdentity(1, $identity));
        $byRec = $svc->forRecs(1, ['REC-261115-0001', 'REC-AUCUNE']);
        self::assertCount(1, $byRec['REC-261115-0001']);
        self::assertArrayNotHasKey('REC-AUCUNE', $byRec);
    }

    public function testSamePhotoSentTwiceIsStoredOnce(): void
    {
        $svc = $this->service();
        $bytes = self::jpegWithExif();
        $first = $svc->store(1, 'REC-261115-0002', $this->upload($bytes));
        $second = $svc->store(1, 'REC-261115-0002', $this->upload($bytes));
        self::assertFalse($first['duplicate']);
        self::assertTrue($second['duplicate'], 'renvoi du jeu : idempotent');
        self::assertSame($first['id'], $second['id']);
        $n = (int) $this->db()->query("SELECT COUNT(*) FROM sse_file_photos WHERE source_ref = 'REC-261115-0002'")->fetchColumn();
        self::assertSame(1, $n);
    }

    public function testRefusesFakeTypesOversizedAndBadRecs(): void
    {
        $svc = $this->service();
        $text = $svc->store(1, 'REC-261115-0003', $this->upload("<?php echo 'x';"));
        self::assertSame('invalid_image', $text['error'] ?? null);
        $svg = $svc->store(1, 'REC-261115-0003', $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'));
        self::assertSame('invalid_image', $svg['error'] ?? null);
        $big = $svc->store(1, 'REC-261115-0003', $this->upload(self::jpegWithExif(), TerrainUploadedImage::MAX_FILE_BYTES + 1));
        self::assertSame('file_too_large', $big['error'] ?? null);
        $bad = $svc->store(1, '12', $this->upload(self::jpegWithExif()));
        self::assertSame('invalid_rec', $bad['error'] ?? null);
        $failed = $svc->store(1, 'REC-261115-0003', ['name' => 'x', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE]);
        self::assertSame('upload_failed', $failed['error'] ?? null);
        self::assertSame(0, (int) $this->db()->query('SELECT COUNT(*) FROM sse_file_photos')->fetchColumn(), 'rien n’est enregistré sur un refus');
    }

    // ------------------------------------------------------------------ lecture protégée

    public function testImageIsReadableOnlyInsideItsOwnCommunityFolder(): void
    {
        $svc = $this->service();
        $r = $svc->store(1, 'REC-261115-0004', $this->upload(self::jpegWithExif()));
        $row = $this->db()->query('SELECT * FROM sse_file_photos WHERE id = ' . (int) $r['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertNotNull($svc->absolutePath(['path' => $row['path']], 1));
        self::assertNull($svc->absolutePath(['path' => $row['path']], 2), 'autre communauté : introuvable');
        self::assertNull($svc->absolutePath(['path' => 'sse-seek/1/../../../etc/passwd'], 1));
        self::assertNull($svc->absolutePath(['path' => 'sse-seek/1/0000000000000000.jpg'], 1), 'fichier absent');
        self::assertNull($svc->absolutePath(['path' => 'uploads/sse-id-photos/1_abcd.jpg'], 1), 'photo publique : pas de cette route');
        self::assertNull($svc->absolutePath(['path' => ''], 1));
    }

    public function testIdentityOfAnotherCommunityNeverSeesThePhoto(): void
    {
        $svc = $this->service();
        $alpha = $this->identity(1, 'Haddad', 'Karim');
        $bravo = $this->identity(2, 'Haddad', 'Karim');
        $this->seekRecord(1, 'REC-261115-0005', $alpha);
        $svc->store(1, 'REC-261115-0005', $this->upload(self::jpegWithExif()));
        self::assertCount(1, $svc->forIdentity(1, $alpha));
        self::assertSame([], $svc->forIdentity(2, $bravo));
        self::assertSame([], $svc->forIdentity(2, $alpha), 'identifiant de l’autre communauté : rien');
        self::assertSame([], $svc->forRecs(2, ['REC-261115-0005']));
    }

    public function testFilesSubjectPageGetsTheSeekPhotoThroughTheRecordWhenAttachmentIsMissing(): void
    {
        $svc = $this->service();
        $identity = $this->identity(1, 'Haddad', 'Karim');
        $svc->store(1, 'REC-261115-0006', $this->upload(self::jpegWithExif()));
        self::assertSame([], (new SseFileRecordRepository())->photosFor(1, [$identity]));
        $this->seekRecord(1, 'REC-261115-0006', $identity);
        $photos = (new SseFileRecordRepository())->photosFor(1, [$identity])[$identity] ?? [];
        self::assertCount(1, $photos, 'la fiche Fichiers › sujet montre aussi le cliché SEEK II');
        self::assertSame('seek', $photos[0]['source']);
        self::assertStringContainsString('/atak/sse/fichiers/photos/' . $photos[0]['id'] . '/image', (string) SseSeekPhotoService::photoUrl($photos[0]));
    }

    public function testPhotoUrlIsProtectedForPrivatePathsAndPublicForOlderOnes(): void
    {
        if (!function_exists('url') || !function_exists('user_media_public_url')) {
            self::markTestSkipped('helpers du site requis');
        }
        self::assertStringContainsString('atak/sse/fichiers/photos/9/image', (string) SseSeekPhotoService::photoUrl(['id' => 9, 'path' => 'sse-seek/1/0123456789abcdef.jpg']));
        self::assertStringContainsString('uploads/sse-id-photos/1_ab.jpg', (string) SseSeekPhotoService::photoUrl(['id' => 9, 'path' => 'uploads/sse-id-photos/1_ab.jpg']));
        self::assertNull(SseSeekPhotoService::photoUrl(['id' => 9, 'path' => '']));
        self::assertTrue(SseSeekPhotoService::isPrivatePath('sse-seek/12/0123456789abcdef.webp'));
        self::assertFalse(SseSeekPhotoService::isPrivatePath('sse-seek/12/../0123456789abcdef.webp'));
        self::assertFalse(SseSeekPhotoService::isPrivatePath('sse-seek/12/0123456789abcdef.php'));
        self::assertStringNotContainsString('public', SseSeekPhotoService::PRIVATE_DIR, 'jamais sous le dossier public');
    }

    // ------------------------------------------------------------------ écrans

    public function testIdentityPanelRendersThumbnailsCaptionAndHonestEmptyState(): void
    {
        if (!function_exists('url')) {
            self::markTestSkipped('helpers du site requis');
        }
        $render = static function (array $seekImages, array $fileMentions = []): string {
            ob_start();
            require dirname(__DIR__, 2) . '/views/atak/sse/papers/_seek_images.php';

            return (string) ob_get_clean();
        };
        $empty = $render([]);
        self::assertStringContainsString('Aucune image SEEK II pour cette identité', $empty);
        self::assertStringContainsString('photographie le visage', $empty, 'dit ce qui fait remonter une image');
        self::assertStringNotContainsString('<img', $empty);

        $html = $render([[
            'id' => 12, 'url' => 'https://exemple.test/atak/sse/fichiers/photos/12/image', 'source_ref' => 'REC-261115-4821', 'kind' => 'front',
            'caption' => 'Photo du visage', 'created_at' => '2026-11-15 14:21:00',
            'seek' => ['rec' => 'REC-261115-4821', 'verdict_label' => 'Concordant', 'grid' => '123456', 'game_time' => '2026-11-15 14:20', 'controller_callsign' => 'VIPER 1-1', 'unit' => 'Alpha'],
        ]], [['mention_label' => 'Recherché', 'note' => 'mandat']]);
        self::assertStringContainsString('href="#seek-img-12"', $html, 'miniature qui ouvre l’agrandissement');
        self::assertStringContainsString('id="seek-img-12"', $html);
        self::assertStringContainsString('REC-261115-4821', $html);
        self::assertStringContainsString('Concordant', $html);
        self::assertStringContainsString('123456', $html);
        self::assertStringContainsString('VIPER 1-1', $html);
        self::assertStringContainsString('Mention : Recherché', $html);
        self::assertStringNotContainsString('uploads/', $html, 'jamais d’adresse de dossier public');
    }

    // ------------------------------------------------------------------ câblage site et mod

    public function testRouteIsBehindThePortalGateAndTheControllerChecksCommunityAndSecrets(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertMatchesRegularExpression("#get\\('/atak/sse/fichiers/photos/\\{id\\}/image', \\[\\\$sseRec, 'photoImage'\\], \\\$mwSsePortal\\)#", $routes);
        $ctl = (string) file_get_contents($root . '/app/Controllers/Web/SseFileRecordController.php');
        $body = substr($ctl, (int) strpos($ctl, 'public function photoImage'));
        $body = substr($body, 0, (int) strpos($body, 'public function compare'));
        self::assertStringContainsString('->ctx->tenantId()', $body);
        self::assertStringContainsString("'cover', 'forged'", $body, 'couverture ou faux : habilitation Confidentiel requise');
        self::assertStringContainsString('CLASS_CONFIDENTIAL', $body);
        self::assertStringContainsString("=== 'hidden'", $body);
        self::assertStringContainsString('canLore', $body, 'lore de session respecté');
        self::assertStringContainsString('X-Content-Type-Options', $body);
        self::assertStringContainsString('->absolutePath(', $body, 'chemin contrôlé, jamais construit à partir de la requête');
    }

    public function testApiRoutesRecReferencesToTheSeekPhotoServiceBeforeTheNumericPersonLookup(): void
    {
        $root = dirname(__DIR__, 2);
        $api = (string) file_get_contents($root . '/app/Controllers/Api/SseApiController.php');
        $start = (int) strpos($api, 'public function personsPhotoStore');
        $end = (int) strpos($api, 'private function seekPhotoStore');
        $block = substr($api, $start, $end - $start);
        self::assertLessThan((int) strpos($block, 'findById'), (int) strpos($block, 'isRecReference'), 'REC examiné avant la fiche personne numérique');
        self::assertStringContainsString('seekPhotoStore', $block);
        $svc = (string) file_get_contents($root . '/app/Services/Sse/SseSeekPhotoService.php');
        self::assertStringNotContainsString("base_path('public", $svc);
        self::assertStringContainsString('storage/private/sse-seek', $svc);
        $query = (string) file_get_contents($root . '/app/Services/Sse/SseFileQueryService.php');
        self::assertStringContainsString('attachForRec', $query, 'la fiche reçue rattache les clichés déjà arrivés');
    }

    public function testSigningAndSendingInTheGameNowUploadsTheFacePhotoWithTheRec(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions';
        $send = (string) file_get_contents($root . '/fn_seekSend.sqf');
        $after = substr($send, (int) strpos($send, '[_payload] call comspec_sse_fnc_fileSubmit;'));
        self::assertStringContainsString('comspec_overwatch_connect_fnc_sseUploadFacePhoto', $after);
        self::assertStringContainsString('[_rec, _stem, _callsign', $after, 'la photo part avec le REC de la fiche signée');
        self::assertStringContainsString('comspec_seek_photoSent', $after, 'un cliché ne part qu’une fois');
        $capture = (string) file_get_contents($root . '/fn_sseCaptureFacePhoto.sqf');
        self::assertStringContainsString('comspec_seek_rec', $capture, 'photo reprise après la signature : REC');
        self::assertStringContainsString('comspec_sse_facePhotoAngle', $capture);
        $upload = (string) file_get_contents($root . '/fn_sseUploadFacePhoto.sqf');
        self::assertStringNotContainsString('parseNumber', $upload, 'la DLL reçoit le REC tel quel');
    }

    public function testPhotothequeViewsUseTheProtectedUrlHelper(): void
    {
        $dir = dirname(__DIR__, 2) . '/views/atak/sse/files/';
        foreach (['subject.php', 'subject_print.php', 'photobook.php', 'compare.php', '_hit.php'] as $f) {
            $src = (string) file_get_contents($dir . $f);
            self::assertStringContainsString('SseSeekPhotoService::photoUrl', $src, $f);
            self::assertStringNotContainsString("user_media_public_url((string) \$ph['path'])", $src, $f);
            self::assertStringNotContainsString("user_media_public_url((string) \$photo['path'])", $src, $f);
        }
        $seek = (string) file_get_contents($dir . 'seek.php');
        self::assertStringContainsString('image non reçue', $seek, 'fiche avec visage relevé mais sans image : dit pourquoi');
    }
}
