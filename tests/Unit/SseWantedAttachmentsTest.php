<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseWantedNoticeController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedAttachmentRepository;
use App\Repositories\SseWantedExtrasRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedAttachments;
use App\Services\Sse\SseWantedNoticeData;
use App\Services\Sse\SseWantedService;
use App\Support\SseImageStorage;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseWantedSqlite;

/**
 * Pièces jointes des avis de recherche : types vérifiés par le contenu, taille, métadonnées retirées, miniature, espace privé,
 * route contrôlée, droits, identité de membre (CONFIDENTIEL ÉLEVÉ), anti-traversée, historique de l'identité, photothèque, et
 * signalement instantané (la fiche et l'avis gardent chacun leur copie).
 */
final class SseWantedAttachmentsTest extends TestCase
{
    private const OWNER = 10;
    private const CHEF = 20;
    private const PEER = 11;

    private PDO $pdo;

    private string $private;

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('gd')) {
            self::markTestSkipped('pdo_sqlite et gd requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER, 'files' => $_FILES ?? []];
        \App\Core\Csrf::token();
        $this->pdo = SseWantedSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        Gate::reset();
        SseMemberImageGate::useForSession(null);
        $this->private = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wanted-att-' . bin2hex(random_bytes(6));
        mkdir($this->private, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->realFiles as $f) {
            @unlink($f);
        }
        $this->realFiles = [];
        SseWantedSqlite::close();
        SseMemberImageGate::useForSession(null);
        self::rmTree($this->private);
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_FILES = $this->saved['files'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    private static function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    // ------------------------------------------------------------------ outils

    private static function manager(): SseWantedAccess
    {
        return new SseWantedAccess(false, true, true, true, 1, 7, 'ANALYSTE');
    }

    /** Porte des images de membres : lecteur et chaîne hiérarchique fixés par le test, journal retenu. */
    private function gate(int $viewer): SseMemberImageGate
    {
        return new SseMemberImageGate(
            null,
            null,
            static fn (int $t, int $owner): array => $owner === self::OWNER ? [self::CHEF] : [],
            static fn (int $t, int $v): bool => false,
            function (array $row): void {
                $this->audit[] = $row;
            },
            static function (): void {
            },
            static fn (): string => '2026-11-15 12:00:00',
            null,
            static fn (): ?int => $viewer > 0 ? $viewer : null
        );
    }

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    private function service(int $viewer = 7): SseWantedAttachments
    {
        $gate = $this->gate($viewer);
        SseMemberImageGate::useForSession($gate);

        return new SseWantedAttachments(null, null, $gate, null, null, null, $this->private);
    }

    private function identity(?int $userId = null, string $last = 'Karimzadeh'): int
    {
        return (new SseIdentityRepository())->create(1, ['last_name' => $last, 'first_name' => 'Élodie', 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK', 'user_id' => $userId]);
    }

    /** @return array<string, mixed> ligne d'avis */
    private function notice(int $identityId, array $extra = []): array
    {
        $res = (new SseWantedService())->place(1, $identityId, $extra + ['notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'yes', 'reason' => 'Participation présumée à une attaque.'], self::manager());
        self::assertTrue($res['ok']);

        return (new SseWantedNoticeRepository())->find(1, $res['id']);
    }

    /** Image générée par code : dégradé et formes, jamais un vrai visage. */
    private static function image(string $format = 'png', int $w = 320, int $h = 240, bool $withExif = false): string
    {
        $im = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            imagefilledrectangle($im, 0, $y, $w, $y, (int) imagecolorallocate($im, min(255, 40 + (int) ($y / 4)), 70, 120));
        }
        imagefilledellipse($im, (int) ($w / 2), (int) ($h / 2), (int) ($w / 2), (int) ($h / 2), (int) imagecolorallocate($im, 220, 200, 120));
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($im, null, 90),
            'webp' => imagewebp($im, null, 80),
            'gif' => imagegif($im),
            default => imagepng($im),
        };
        $bytes = (string) ob_get_clean();
        imagedestroy($im);
        if ($withExif && $format === 'jpeg') {
            $payload = "Exif\0\0" . 'GPS-SECRET-LAT-48.8566 CAMERA-SERIAL-ABC123';
            $bytes = "\xFF\xD8" . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($bytes, 2);
        }

        return $bytes;
    }

    private function tmpFile(string $bytes): string
    {
        $f = tempnam(sys_get_temp_dir(), 'wimg');
        file_put_contents($f, $bytes);

        return $f;
    }

    /** @return list<string> */
    private function privateFiles(): array
    {
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->private, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $out[] = str_replace('\\', '/', $f->getPathname());
        }
        sort($out);

        return $out;
    }

    // ------------------------------------------------------------------ ajout, types, taille, métadonnées

    public function testPngJpegAndWebpAreAcceptedAndStoredInThePrivateSpaceOnly(): void
    {
        $notice = $this->notice($this->identity());
        $svc = $this->service();
        $ids = [];
        foreach (['png', 'jpeg', 'webp'] as $fmt) {
            $res = $svc->addFile(1, $notice, $this->tmpFile(self::image($fmt)), ['kind' => 'vehicle', 'caption' => 'Pick-up gris ' . $fmt], self::manager());
            self::assertTrue($res['ok'], $fmt . ' : ' . json_encode($res));
            $ids[] = $res['id'];
        }
        $rows = (new SseWantedAttachmentRepository())->forNotice(1, $notice['id']);
        self::assertCount(3, $rows);
        self::assertTrue($rows[0]['is_main'], 'la première image est la principale');
        self::assertFalse($rows[1]['is_main']);
        foreach ($rows as $r) {
            self::assertTrue(SseImageStorage::isPrivatePath($r['path']), 'chemin privé : ' . $r['path']);
            self::assertStringStartsWith('sse-private/sse-wanted/1_', $r['path']);
            self::assertNotNull($r['thumb_path']);
            self::assertFileExists((string) SseImageStorage::absolute($r['path'], null, $this->private));
            self::assertSame('vehicle', $r['kind']);
            self::assertNotNull($r['width']);
            self::assertNotNull($r['sha1']);
            // Le fichier est sous l'espace privé du test, jamais sous public/.
            self::assertStringStartsWith(str_replace('\\', '/', $this->private), str_replace('\\', '/', (string) SseImageStorage::absolute($r['path'], null, $this->private)));
        }
        foreach ($this->privateFiles() as $f) {
            self::assertStringNotContainsString('/public/', $f);
        }
        self::assertCount(6, $this->privateFiles(), 'image et miniature par pièce');
        // L'image principale, puis la désignation d'une autre.
        self::assertTrue($svc->makeMain(1, $notice, $ids[2], self::manager())['ok']);
        $mains = array_values(array_filter((new SseWantedAttachmentRepository())->forNotice(1, $notice['id']), static fn (array $a): bool => $a['is_main']));
        self::assertCount(1, $mains);
        self::assertSame($ids[2], $mains[0]['id']);
        self::assertSame($ids[2], SseWantedAttachments::forNotice($notice['id'], 1, $svc)[0]['id'], 'la principale vient en premier pour le PDF');
    }

    public function testTheRealTypeIsReadFromTheContentNotFromTheNameOrTheClient(): void
    {
        $notice = $this->notice($this->identity());
        $svc = $this->service();
        $bad = [
            'gif' => self::image('gif'),
            'texte déguisé' => '<?php echo "pwned"; ?>',
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'png tronqué' => substr(self::image('png'), 0, 8) . 'xxxx',
            'vide' => '',
        ];
        foreach ($bad as $name => $bytes) {
            $f = $this->tmpFile($bytes);
            rename($f, $f . '.png'); // l'extension annonce une image : le contenu décide
            $res = $svc->addFile(1, $notice, $f . '.png', ['kind' => 'other'], self::manager());
            self::assertFalse($res['ok'], $name);
            unlink($f . '.png');
        }
        self::assertSame([], (new SseWantedAttachmentRepository())->forNotice(1, $notice['id']));
        self::assertSame([], $this->privateFiles(), 'rien n’est rangé pour un fichier refusé');
    }

    public function testFilesOverFiveMegabytesAreRefusedAndTheLimitIsSixImages(): void
    {
        $notice = $this->notice($this->identity());
        $svc = $this->service();
        $big = $this->tmpFile(self::image('png') . str_repeat("\0", 5 * 1024 * 1024));
        $res = $svc->addFile(1, $notice, $big, [], self::manager());
        self::assertFalse($res['ok']);
        self::assertStringContainsString('5 Mo', $res['error']);
        for ($i = 0; $i < 6; $i++) {
            self::assertTrue($svc->addFile(1, $notice, $this->tmpFile(self::image('png', 80 + $i, 60)), ['kind' => 'place'], self::manager())['ok']);
        }
        $seventh = $svc->addFile(1, $notice, $this->tmpFile(self::image('png')), [], self::manager());
        self::assertFalse($seventh['ok']);
        self::assertStringContainsString('Six images', $seventh['error']);
        self::assertCount(6, (new SseWantedAttachmentRepository())->forNotice(1, $notice['id']));
    }

    public function testExifAndGpsMetadataAreStrippedAndThumbnailsAreSmaller(): void
    {
        $bytes = self::image('jpeg', 1000, 800, true);
        self::assertStringContainsString('GPS-SECRET-LAT', $bytes);
        $notice = $this->notice($this->identity());
        $svc = $this->service();
        $res = $svc->addFile(1, $notice, $this->tmpFile($bytes), ['kind' => 'portrait', 'caption' => 'Capture de caméra'], self::manager());
        self::assertTrue($res['ok']);
        $att = (new SseWantedAttachmentRepository())->find(1, $res['id']);
        $full = (string) file_get_contents((string) SseImageStorage::absolute($att['path'], null, $this->private));
        $thumb = (string) file_get_contents((string) SseImageStorage::absolute((string) $att['thumb_path'], null, $this->private));
        foreach ([$full, $thumb] as $file) {
            self::assertStringNotContainsString('GPS-SECRET-LAT', $file);
            self::assertStringNotContainsString('CAMERA-SERIAL', $file);
            self::assertStringNotContainsString('Exif', $file);
            self::assertSame('image/jpeg', SseImageStorage::verifiedMime(self::tmpPath($file)));
        }
        self::assertLessThan(strlen($full), strlen($thumb), 'la miniature est plus légère');
        [$tw, $th] = getimagesizefromstring($thumb);
        self::assertLessThanOrEqual(360, max($tw, $th));
        self::assertSame('Capture de caméra', $att['caption']);
    }

    /** @var list<string> fichiers rangés dans l'espace privé réel par un test d'écran, retirés à la fin */
    private array $realFiles = [];

    /** @return list<string> */
    private function rowPaths(): array
    {
        $out = [];
        foreach ($this->pdo->query('SELECT path, thumb_path FROM sse_wanted_attachments')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            foreach ([$r['path'], $r['thumb_path']] as $p) {
                $abs = $p !== null ? SseImageStorage::absolute((string) $p) : null;
                if ($abs !== null) {
                    $out[] = $abs;
                }
            }
        }

        return $out;
    }

    private static function tmpPath(string $bytes): string
    {
        $f = tempnam(sys_get_temp_dir(), 'wchk');
        file_put_contents($f, $bytes);

        return $f;
    }

    public function testKindAndCaptionAreNormalizedAndHistoryIsKeptInTheIdentityTimeline(): void
    {
        $iid = $this->identity();
        $notice = $this->notice($iid);
        $svc = $this->service();
        $res = $svc->addFile(1, $notice, $this->tmpFile(self::image()), ['kind' => 'weapon<script>', 'caption' => "  Tatouage\navant-bras " . str_repeat('x', 300)], self::manager());
        $att = (new SseWantedAttachmentRepository())->find(1, $res['id']);
        self::assertSame('other', $att['kind'], 'type inconnu : « autre »');
        self::assertSame(160, mb_strlen((string) $att['caption']));
        self::assertStringStartsWith('Tatouage avant-bras', (string) $att['caption']);
        $mark = $svc->addFile(1, $notice, $this->tmpFile(self::image()), ['kind' => 'mark'], self::manager());
        self::assertTrue($svc->remove(1, $notice, $mark['id'], self::manager())['ok']);
        $events = (new SseIdentityEventRepository())->listForIdentity(1, $iid);
        $types = array_column($events, 'type');
        self::assertSame(2, count(array_filter($types, static fn (string $t): bool => $t === 'notice.attachment_added')));
        self::assertContains('notice.attachment_removed', $types);
        self::assertContains('notice.placed', $types);
        // Retrait : les fichiers disparaissent du disque et la principale se reporte.
        self::assertCount(2, $this->privateFiles());
        self::assertTrue((new SseWantedAttachmentRepository())->find(1, $res['id'])['is_main']);
        self::assertNull((new SseWantedAttachmentRepository())->find(1, $mark['id']));
        // Mêmes catalogues FR et EN pour les types de pièce.
        foreach (SseWantedAttachments::KINDS as $labels) {
            self::assertCount(2, $labels);
        }
    }

    // ------------------------------------------------------------------ droits et communauté

    public function testNoRightNoOtherCommunityNoForeignAttachment(): void
    {
        $notice = $this->notice($this->identity());
        $svc = $this->service();
        $bytes = $this->tmpFile(self::image());
        $reader = new SseWantedAccess(false, false, false, false, 1, 7);
        self::assertFalse($svc->addFile(1, $notice, $bytes, [], $reader)['ok'], 'lecture seule : refusé');
        self::assertFalse($svc->addFile(1, $notice, $bytes, [], new SseWantedAccess(false, true, true, true, 2, 7))['ok'], 'autre communauté : refusé');
        self::assertFalse($svc->addFile(2, $notice, $bytes, [], new SseWantedAccess(false, true, true, true, 2, 7))['ok'], 'avis d’une autre communauté : refusé');
        self::assertSame([], (new SseWantedAttachmentRepository())->forNotice(1, $notice['id']));

        $ok = $svc->addFile(1, $notice, $bytes, [], self::manager());
        self::assertTrue($ok['ok']);
        self::assertFalse($svc->remove(1, $notice, $ok['id'], $reader)['ok']);
        self::assertFalse($svc->remove(2, $notice, $ok['id'], new SseWantedAccess(false, true, true, true, 2, 7))['ok']);
        self::assertNull($svc->resolve(2, $ok['id'], false), 'autre communauté : introuvable');
        // Une pièce d'un autre avis ne se retire pas par l'identifiant d'un avis voisin.
        $other = $this->notice($this->identity(null, 'Voisin'));
        self::assertFalse($svc->remove(1, $other, $ok['id'], self::manager())['ok']);
        self::assertCount(1, (new SseWantedAttachmentRepository())->forNotice(1, $notice['id']));
    }

    public function testMemberIdentityIsLockedForAddServeAndPdfUnlessTheHierarchyAllows(): void
    {
        $member = $this->identity(self::OWNER, 'Membre');
        $notice = $this->notice($member);
        $bytes = $this->tmpFile(self::image());

        // Un gestionnaire sans lien hiérarchique : rien n'est joint.
        $stranger = $this->service(self::PEER);
        $refused = $stranger->addFile(1, $notice, $bytes, [], self::manager());
        self::assertFalse($refused['ok']);
        self::assertStringContainsString(AppServicesSseSseMemberImageGate::LABEL, $refused['error']);
        self::assertSame([], $this->privateFiles());

        // Le supérieur du membre peut joindre ; les autres ne voient, ne servent et n'exposent rien.
        $chief = $this->service(self::CHEF);
        $added = $chief->addFile(1, $notice, $bytes, ['kind' => 'portrait'], self::manager());
        self::assertTrue($added['ok']);
        self::assertCount(1, $chief->listFor(1, $notice));
        self::assertCount(1, SseWantedAttachments::forNotice($notice['id'], 1, $chief));
        $served = $chief->resolve(1, $added['id'], false);
        self::assertNotNull($served);
        self::assertTrue($served['confidential']);
        self::assertSame('hierarchy', $served['reason']);

        $peer = $this->service(self::PEER);
        self::assertSame([], $peer->listFor(1, $notice));
        self::assertSame([], SseWantedAttachments::forNotice($notice['id'], 1, $peer), 'le PDF n’embarque rien');
        self::assertNull($peer->resolve(1, $added['id'], false));
        self::assertNull($peer->resolve(1, $added['id'], true));
        self::assertFalse($peer->remove(1, $notice, $added['id'], self::manager())['ok']);
        self::assertFalse($peer->makeMain(1, $notice, $added['id'], self::manager())['ok']);
        self::assertSame('member_image.denied', $this->audit[count($this->audit) - 1]['action'], 'refus journalisé');
        // Le jeu ne reçoit ni image ni légende pour une identité de membre.
        self::assertSame([], SseWantedNoticeData::forGame(1, 80, $this->gate(self::PEER))[0]['images']);
        self::assertSame([], SseWantedNoticeData::forGame(1, 80, $this->gate(self::CHEF))[0]['images']);
    }

    public function testControllerServesImagesThroughTheGateWithSafeHeadersAndRefusesTheRest(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $iid = $this->identity();
        $notice = $this->notice($iid);
        $svc = $this->service();
        $res = $svc->addFile(1, $notice, $this->tmpFile(self::image('jpeg')), ['kind' => 'place'], self::manager());
        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::manager(), $svc);

        $full = $controller->attachmentImage(new Request(), ['id' => (string) $res['id']]);
        self::assertSame(200, $full->statusCode());
        self::assertSame('image/jpeg', $full->headerValue('Content-Type'));
        self::assertStringContainsString('no-store', (string) $full->headerValue('Cache-Control'));
        self::assertSame('nosniff', $full->headerValue('X-Content-Type-Options'));
        self::assertStringStartsWith("\xFF\xD8\xFF", self::bytes($full));
        $_GET = ['v' => 'mini'];
        $mini = $controller->attachmentImage(new Request(), ['id' => (string) $res['id']]);
        self::assertSame(200, $mini->statusCode());
        self::assertStringStartsWith(chr(0xFF) . chr(0xD8) . chr(0xFF), self::bytes($mini));
        $_GET = [];

        // Identifiant inconnu, d'un autre type ou négatif : 404 sans rien révéler.
        foreach (['9999', '0', '-1', 'abc', '../../etc/passwd'] as $id) {
            $r = $controller->attachmentImage(new Request(), ['id' => $id]);
            self::assertSame(404, $r->statusCode(), $id);
            self::assertSame('Not found', $r->body());
        }
        // Lecteur sans droit de lecture du portail : 404.
        $blind = new SseWantedNoticeController(null, null, null, null, null, null, null, new SseWantedAccess(false, false, false, false, 1, 7, '', false), $svc);
        self::assertSame(404, $blind->attachmentImage(new Request(), ['id' => (string) $res['id']])->statusCode());
        // Autre communauté : 404.
        $foreign = new SseWantedNoticeController(null, null, null, null, null, null, null, new SseWantedAccess(false, true, true, true, 2, 7), $svc);
        self::assertSame(404, $foreign->attachmentImage(new Request(), ['id' => (string) $res['id']])->statusCode());
    }

    public function testControllerWritesNeedCsrfAndTheManageRight(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $notice = $this->notice($this->identity());
        $svc = $this->service();
        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::manager(), $svc);
        $added = $svc->addFile(1, $notice, $this->tmpFile(self::image()), [], self::manager());

        $_POST = ['_csrf_token' => 'mauvais'];
        $controller->attachmentRemove(new Request(), ['id' => (string) $added['id']]);
        self::assertSame(SseWantedNoticeController::TOKEN_EXPIRED, \App\Core\Session::getFlash('error'));
        self::assertNotNull((new SseWantedAttachmentRepository())->find(1, $added['id']));
        $controller->attachmentMain(new Request(), ['id' => (string) $added['id']]);
        self::assertSame(SseWantedNoticeController::TOKEN_EXPIRED, \App\Core\Session::getFlash('error'));
        $controller->attachmentAdd(new Request(), ['id' => (string) $notice['id']]);
        self::assertSame(SseWantedNoticeController::TOKEN_EXPIRED, \App\Core\Session::getFlash('error'));

        $_POST = ['_csrf_token' => 'jeton'];
        $reader = new SseWantedNoticeController(null, null, null, null, null, null, null, new SseWantedAccess(false, false, false, false, 1, 7), $svc);
        $reader->attachmentRemove(new Request(), ['id' => (string) $added['id']]);
        self::assertSame(SseWantedNoticeController::NEED_MANAGE, \App\Core\Session::getFlash('error'));
        self::assertNotNull((new SseWantedAttachmentRepository())->find(1, $added['id']));

        // Un « fichier » qui n'a pas été reçu par HTTP (chemin choisi par le client) n'est jamais lu.
        $_FILES = ['att_file' => ['name' => ['a.png'], 'tmp_name' => [__FILE__], 'error' => [UPLOAD_ERR_OK], 'size' => [10]]];
        $controller->attachmentAdd(new Request(), ['id' => (string) $notice['id']]);
        self::assertStringContainsString('n’a pas pu être reçue', (string) \App\Core\Session::getFlash('error'));
        self::assertCount(1, (new SseWantedAttachmentRepository())->forNotice(1, $notice['id']));

        $controller->attachmentRemove(new Request(), ['id' => (string) $added['id']]);
        self::assertNull((new SseWantedAttachmentRepository())->find(1, $added['id']));
    }

    // ------------------------------------------------------------------ anti-traversée

    public function testTraversalAndForeignPathsNeverResolve(): void
    {
        $notice = $this->notice($this->identity());
        $svc = $this->service();
        file_put_contents($this->private . '/secret.txt', 'SECRET');
        foreach (['sse-private/sse-wanted/../../secret.txt', 'sse-private/sse-wanted/..%2f..%2fsecret.txt', '../secret.txt', '/etc/passwd', 'sse-private/sse-id-photos/1_aaaaaaaaaaaaaaaa.jpg/../../../secret.txt', "sse-private/sse-wanted/1_aaaaaaaaaaaaaaaa.jpg\0.png", 'C:\\Windows\\win.ini', 'sse-private/sse-wanted/1_zzzzzzzzzzzzzzzz.jpg'] as $evil) {
            self::assertNull(SseImageStorage::absolute($evil, null, $this->private), $evil);
            $this->pdo->exec("DELETE FROM sse_wanted_attachments");
            $stmt = $this->pdo->prepare("INSERT INTO sse_wanted_attachments (tenant_id, notice_id, identity_id, kind, path, thumb_path, mime, is_main, created_at) VALUES (1, :n, :i, 'other', :p, :p, 'image/jpeg', 1, '2026-11-15 10:00:00')");
            $stmt->execute(['n' => $notice['id'], 'i' => $notice['identity_id'], 'p' => $evil]);
            $id = (int) $this->pdo->lastInsertId();
            self::assertNull($svc->resolve(1, $id, false), 'ligne piégée : ' . $evil);
            self::assertNull($svc->resolve(1, $id, true), 'miniature piégée : ' . $evil);
            self::assertSame([], $svc->listFor(1, $notice), 'la liste ignore le chemin piégé');
        }
        self::assertFileExists($this->private . '/secret.txt');
        // Les chemins valides ont une forme stricte.
        self::assertTrue(SseImageStorage::isPrivatePath('sse-private/sse-wanted/1_0123456789abcdef.jpg'));
        self::assertFalse(SseImageStorage::isPrivatePath('sse-private/sse-wanted/1_0123456789abcdef.php'));
        self::assertFalse(SseImageStorage::isPrivatePath('sse-private/sse-wanted/sub/1_0123456789abcdef.jpg'));
    }

    // ------------------------------------------------------------------ photothèque

    public function testAttachFromThePhotoLibraryCopiesOnlyPhotosOfTheSameIdentity(): void
    {
        $iid = $this->identity();
        $other = $this->identity(null, 'Autre');
        $notice = $this->notice($iid);
        $svc = $this->service();
        // Photos de la photothèque : une de l'identité, une d'une autre, une masquée, une d'un membre.
        $dir = $this->private . '/sse-wanted';
        $stored = (new \App\Services\Sse\SseIdImageService(null, null, $this->private))->storeBytes(self::image('jpeg'), 'photo', 1);
        self::assertTrue($stored['ok']);
        $records = new SseFileRecordRepository();
        $mine = $records->addPhoto(1, ['identity_id' => $iid, 'path' => $stored['path'], 'mime' => 'image/jpeg', 'kind' => 'front', 'source' => 'upload', 'caption' => 'Face, poste nord'], null);
        $foreign = $records->addPhoto(1, ['identity_id' => $other, 'path' => $stored['path'], 'mime' => 'image/jpeg', 'kind' => 'front', 'source' => 'upload'], null);

        $ok = $svc->addFromPhoto(1, $notice, $mine, ['kind' => 'portrait', 'main' => true], self::manager());
        self::assertTrue($ok['ok'], json_encode($ok));
        $att = (new SseWantedAttachmentRepository())->find(1, $ok['id']);
        self::assertSame($mine, $att['source_photo_id']);
        self::assertSame('Face, poste nord', $att['caption'], 'la légende de la photo est reprise');
        self::assertStringStartsWith('sse-private/sse-wanted/', $att['path'], 'copie propre à l’avis, pas la photo d’origine');
        self::assertNotSame($stored['path'], $att['path']);

        $bad = $svc->addFromPhoto(1, $notice, $foreign, [], self::manager());
        self::assertFalse($bad['ok'], 'photo d’une autre identité');
        self::assertFalse($svc->addFromPhoto(1, $notice, 99999, [], self::manager())['ok']);
        self::assertFalse($svc->addFromPhoto(2, $notice, $mine, [], new SseWantedAccess(false, true, true, true, 2, 7))['ok']);
        // La photo d'origine reste en place si on retire la pièce.
        self::assertTrue($svc->remove(1, $notice, $ok['id'], self::manager())['ok']);
        self::assertFileExists((string) SseImageStorage::absolute($stored['path'], null, $this->private));
        // Photothèque d'un membre : refusée sans l'autorisation.
        $member = $this->identity(self::OWNER, 'Membre');
        $mNotice = $this->notice($member);
        $mPhoto = $records->addPhoto(1, ['identity_id' => $member, 'path' => $stored['path'], 'mime' => 'image/jpeg', 'kind' => 'front', 'source' => 'upload'], null);
        self::assertFalse($this->service(self::PEER)->addFromPhoto(1, $mNotice, $mPhoto, [], self::manager())['ok']);
        self::assertTrue($dir !== '');
    }

    // ------------------------------------------------------------------ signalement instantané

    public function testThePhysicalDescriptionIsASnapshotIndependentOfTheRecord(): void
    {
        $iid = $this->identity();
        $records = new SseFileRecordRepository();
        $records->saveProfile(1, $iid, ['height_cm' => 178, 'hair' => 'brown'], 7);
        $res = (new SseWantedService())->place(1, $iid, ['notice_type' => 'poi', 'threat_level' => 'low', 'armed' => 'no', 'reason' => 'Contact connu.', 'physical' => \App\Support\SseWantedPhysical::fromProfile($records->profile(1, $iid), false) + ['tattoos' => 'Aigle']], self::manager());
        self::assertTrue($res['ok']);
        $extras = new SseWantedExtrasRepository();
        self::assertSame(178, $extras->forNotice(1, $res['id'])['physical']['height_cm']);

        // La fiche change : l'avis garde sa copie.
        $records->saveProfile(1, $iid, ['height_cm' => 190, 'hair' => 'black'], 7);
        $snap = $extras->forNotice(1, $res['id'])['physical'];
        self::assertSame(178, $snap['height_cm']);
        self::assertSame('brown', $snap['hair']);
        self::assertSame('Aigle', $snap['tattoos']);
        // L'avis change (autre signalement) : la fiche garde la sienne.
        $extras->save(1, $res['id'], null, \App\Support\SseWantedPhysical::normalize(['height_cm' => 165]));
        self::assertSame(190, $records->profile(1, $iid)['height_cm']);
        self::assertSame(165, $extras->forNotice(1, $res['id'])['physical']['height_cm']);
    }

    public function testViewsShowTheGalleryForAuthorizedReadersAndALockForMembers(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $iid = $this->identity();
        $notice = $this->notice($iid);
        // Les écrans lisent l'espace privé réel : les fichiers de ce test y sont rangés puis retirés.
        $gate = $this->gate(7);
        SseMemberImageGate::useForSession($gate);
        $svc = new SseWantedAttachments(null, null, $gate);
        $svc->addFile(1, $notice, $this->tmpFile(self::image('jpeg')), ['kind' => 'vehicle', 'caption' => 'Pick-up gris'], self::manager());
        $svc->addFile(1, $notice, $this->tmpFile(self::image('png')), ['kind' => 'mark', 'caption' => 'Tatouage au poignet'], self::manager());
        $this->realFiles = array_merge($this->realFiles, $this->rowPaths());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $body = (new \App\Controllers\Web\SseIdentityRegistryController())->identityShow(new Request(), ['id' => $iid])->body();
        foreach (['Images jointes (2)', 'Pick-up gris', 'Tatouage au poignet', 'data-wc-gal-open', 'atak/sse/avis/pieces/', 'sse-wanted-gallery.js', 'name="att_file[]"'] as $needle) {
            self::assertStringContainsString($needle, $body, $needle);
        }
        self::assertStringNotContainsString('storage/private', $body, 'aucun chemin de fichier dans la page');
        self::assertStringNotContainsString('sse-private/', $body);
        self::assertStringNotContainsString('/public/uploads', $body);
        self::assertStringNotContainsString('Warning', $body);

        // Fiche d'un membre : la galerie est verrouillée pour qui n'a pas l'autorisation, sans aucune adresse d'image.
        $member = $this->identity(self::OWNER, 'Membre');
        $mNotice = $this->notice($member);
        $chiefGate = $this->gate(self::CHEF);
        (new SseWantedAttachments(null, null, $chiefGate))->addFile(1, $mNotice, $this->tmpFile(self::image()), ['caption' => 'Photo de membre'], self::manager());
        $this->realFiles = array_merge($this->realFiles, $this->rowPaths());
        SseMemberImageGate::useForSession($this->gate(self::PEER));
        $locked = (new \App\Controllers\Web\SseIdentityRegistryController())->identityShow(new Request(), ['id' => $member])->body();
        self::assertStringContainsString(\App\Services\Sse\SseMemberImageGate::LABEL, $locked);
        self::assertStringNotContainsString('Photo de membre', $locked);
        self::assertStringNotContainsString('data-wc-gal-open', $locked);
        // Formulaire d'un membre : pas de joint, pas de préremplissage.
        $form = (new SseWantedNoticeController(null, null, null, null, null, null, null, self::manager(), new SseWantedAttachments(null, null, $this->gate(self::PEER), null, null, null, $this->private)))->placeForm(new Request(), ['id' => (string) $this->identity(self::OWNER, 'Membre bis')]);
        self::assertStringContainsString('aucune image n’est jointe sans l’autorisation de la hiérarchie', $form->body());
        self::assertStringNotContainsString('data-wc-add-file', $form->body());
    }

    private static function bytes(Response $resp): string
    {
        $prop = new \ReflectionProperty($resp, 'bodyStream');
        $stream = $prop->getValue($resp);
        if (!is_callable($stream)) {
            return $resp->body();
        }
        ob_start();
        $stream();

        return (string) ob_get_clean();
    }
}
