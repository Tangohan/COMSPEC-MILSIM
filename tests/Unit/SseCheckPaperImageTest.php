<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseCheckPaperImageService;
use App\Services\Sse\SseCheckPaperSelectionService;
use App\Services\Sse\SseMemberImageGate;
use App\Support\SseImageStorage;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseLinksSqlite;

/**
 * Photo du papier présenté à un contrôle d'identité, remontée du jeu (COMSPEC Link 2.0.127) : réception (type, taille,
 * métadonnées, communauté, débit, remplacement), lecture (identité de membre, papier secret, autre communauté) et
 * rattachement au papier consigné depuis le contrôle. Vraies tables sqlite, vrais dépôts, vrais fichiers.
 */
final class SseCheckPaperImageTest extends TestCase
{
    private const OWNER = 21;
    private const CHEF = 22;
    private const PEER = 23;

    private PDO $pdo;

    private string $private = '';

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<array<string, mixed>> */
    private array $gateAudit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('gd')) {
            self::markTestSkipped('pdo_sqlite et gd requis');
        }
        $this->pdo = SseLinksSqlite::open();
        $this->saved = ['session' => $_SESSION ?? null];
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        Gate::reset();
        SseMemberImageGate::useForSession(null);
        $this->private = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'check-paper-' . bin2hex(random_bytes(6));
        mkdir($this->private, 0775, true);
        $this->clearRate();
    }

    protected function tearDown(): void
    {
        $this->clearRate();
        SseLinksSqlite::close();
        SseMemberImageGate::useForSession(null);
        if (is_dir($this->private)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->private, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($this->private);
        }
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    // ------------------------------------------------------------------ outils

    private function clearRate(): void
    {
        $limiter = new FileRateLimiter();
        foreach ([[1, null], [1, 7], [2, null], [9, 7]] as [$t, $u]) {
            $limiter->clear(SseCheckPaperImageService::rateKey($t, $u));
        }
    }

    private function gate(int $viewer): SseMemberImageGate
    {
        return new SseMemberImageGate(
            null,
            null,
            static fn (int $t, int $owner): array => $owner === self::OWNER ? [self::CHEF] : [],
            static fn (int $t, int $v): bool => false,
            function (array $row): void {
                $this->gateAudit[] = $row;
            },
            static function (): void {
            },
            static fn (): string => '2026-11-15 12:00:00',
            null,
            static fn (): ?int => $viewer > 0 ? $viewer : null
        );
    }

    private function service(int $viewer = 7, bool $truth = true): SseCheckPaperImageService
    {
        $gate = $this->gate($viewer);
        SseMemberImageGate::useForSession($gate);

        return new SseCheckPaperImageService(null, null, null, $gate, null, $this->private, static fn (): bool => $truth);
    }

    /** @param array<string, mixed> $over */
    private function check(string $key, array $over = [], int $tenant = 1): int
    {
        $row = $over + [
            'context_id' => 1, 'idempotency_key' => $key, 'doc_type' => 'passport', 'country_code' => 'USA', 'doc_number' => 'US47036925',
            'presented_last' => 'Karimzadeh', 'presented_first' => 'Élodie', 'controller_callsign' => 'Alpha 2-1', 'grid' => '045112',
            'levels' => 'N0', 'results_json' => json_encode(['N0' => ['state' => 'ok'], 'paper' => ['issued' => '2024-02-01']]), 'verdict' => 'ras',
        ];

        return (new SseIdCheckRepository())->store($tenant, $row)['id'];
    }

    /** Image générée par code : dégradé et forme, jamais un vrai papier. */
    private static function image(string $format = 'jpeg', int $w = 900, int $h = 600, bool $withExif = false, int $tint = 120): string
    {
        $im = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y += 4) {
            imagefilledrectangle($im, 0, $y, $w, $y + 3, (int) imagecolorallocate($im, min(255, 40 + (int) ($y / 4)), 70, $tint));
        }
        imagefilledrectangle($im, (int) ($w / 8), (int) ($h / 8), (int) ($w / 2), (int) ($h / 2), (int) imagecolorallocate($im, 220, 200, 120));
        ob_start();
        match ($format) {
            'png' => imagepng($im),
            'gif' => imagegif($im),
            default => imagejpeg($im, null, 90),
        };
        $bytes = (string) ob_get_clean();
        imagedestroy($im);
        if ($withExif && $format === 'jpeg') {
            $payload = "Exif\0\0" . 'GPS-SECRET-LAT-48.8566 CAMERA-SERIAL-ABC123';
            $bytes = "\xFF\xD8" . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($bytes, 2);
        }

        return $bytes;
    }

    /** @return list<string> fichiers rangés dans le dossier privé des photos de contrôle */
    private function storedFiles(): array
    {
        $dir = $this->private . DIRECTORY_SEPARATOR . 'sse-id-checks';

        return is_dir($dir) ? array_values(array_filter(scandir($dir) ?: [], static fn (string $f): bool => $f !== '.' && $f !== '..')) : [];
    }

    private function rawResults(int $checkId): array
    {
        $json = $this->pdo->query('SELECT results_json FROM sse_id_checks WHERE id = ' . $checkId)->fetchColumn();

        return (array) json_decode((string) $json, true);
    }

    // ------------------------------------------------------------------ réception

    public function testThePhotoIsStoredPrivatelyWithoutMetadataWithAThumbnailAndAnAuditLine(): void
    {
        $id = $this->check('IDCHK-765-1-100');
        $res = $this->service()->ingest(1, ['idempotency_key' => 'IDCHK-765-1-100'], self::image('jpeg', 2400, 1600, true), 7);

        self::assertTrue($res['ok']);
        self::assertSame($id, $res['id']);
        self::assertFalse($res['duplicate']);
        $entry = $this->rawResults($id)[SseCheckPaperImageService::RESULT_KEY];
        self::assertTrue(SseImageStorage::isPrivatePath($entry['path']), 'chemin de l’espace privé, jamais public/uploads');
        self::assertStringStartsWith('sse-private/sse-id-checks/1_', $entry['path']);
        self::assertStringNotContainsString('uploads', $entry['path']);
        $abs = SseImageStorage::absolute($entry['path'], null, $this->private);
        $thumb = SseImageStorage::absolute((string) $entry['thumb_path'], null, $this->private);
        self::assertNotNull($abs);
        self::assertNotNull($thumb);
        $bytes = (string) file_get_contents((string) $abs);
        self::assertStringNotContainsString('Exif', $bytes);
        self::assertStringNotContainsString('GPS-SECRET', $bytes);
        self::assertStringNotContainsString('CAMERA-SERIAL', (string) file_get_contents((string) $thumb));
        self::assertLessThanOrEqual(1600, max((int) $entry['width'], (int) $entry['height']), 'plus grand côté ramené à 1600 px');
        $t = getimagesize((string) $thumb);
        self::assertLessThanOrEqual(360, max($t[0], $t[1]));
        self::assertSame('image/jpeg', SseImageStorage::verifiedMime((string) $abs));
        // Le reste des résultats du contrôle n'a pas bougé.
        self::assertSame('2024-02-01', $this->rawResults($id)['paper']['issued']);
        self::assertSame('ok', $this->rawResults($id)['N0']['state']);

        $audit = $this->pdo->query("SELECT action, object_type, object_id, actor_user_id FROM sse_audit_log WHERE action = 'id_check.paper_image_received'")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $audit);
        self::assertSame('sse_id_check', $audit[0]['object_type']);
        self::assertSame($id, (int) $audit[0]['object_id']);
        self::assertSame(7, (int) $audit[0]['actor_user_id']);
    }

    public function testTheStorageEntryNeverLeavesWithTheCheckResults(): void
    {
        $id = $this->check('IDCHK-765-2-100');
        $this->service()->ingest(1, ['check_id' => $id], self::image(), 7);
        $check = (new SseIdCheckRepository())->find($id, 1);

        self::assertTrue($check['has_paper_image']);
        self::assertFalse(array_key_exists(SseCheckPaperImageService::RESULT_KEY, $check['results']));
        self::assertStringNotContainsString('sse-private', (string) json_encode($check));
        self::assertSame('2024-02-01', $check['results']['paper']['issued']);
        self::assertFalse((new SseIdCheckRepository())->find($this->check('IDCHK-765-2-101'), 1)['has_paper_image']);
    }

    public function testTheRealTypeIsReadFromTheContent(): void
    {
        $id = $this->check('IDCHK-765-3-100');
        $svc = $this->service();

        self::assertSame(['invalid_type', 415], array_slice(array_values($svc->ingest(1, ['check_id' => $id], '<?php echo "x"; ?>' . str_repeat('A', 200), 7)), 1));
        self::assertSame('invalid_type', $svc->ingest(1, ['check_id' => $id], self::image('gif'), 7)['error']);
        // Une extension ou un type annoncé ne comptent pas : un faux JPEG (en-tête seul) est refusé.
        self::assertSame('invalid_type', $svc->ingest(1, ['check_id' => $id], "\xFF\xD8\xFF\xE0" . str_repeat("\0", 64), 7)['error']);
        self::assertSame(['image_required', 422], array_slice(array_values($svc->ingest(1, ['check_id' => $id], '', 7)), 1));
        self::assertSame([], $this->storedFiles());
        self::assertFalse((new SseIdCheckRepository())->find($id, 1)['has_paper_image']);

        self::assertTrue($svc->ingest(1, ['check_id' => $id], self::image('png', 640, 400), 7)['ok']);
    }

    public function testAnImageOverTheSizeLimitIsRefused(): void
    {
        $id = $this->check('IDCHK-765-4-100');
        $big = self::image('jpeg', 400, 300) . str_repeat("\0", SseCheckPaperImageService::MAX_BYTES);
        $res = $this->service()->ingest(1, ['check_id' => $id], $big, 7);

        self::assertFalse($res['ok']);
        self::assertSame('too_large', $res['error']);
        self::assertSame(413, $res['status']);
        self::assertSame(1_572_864, SseCheckPaperImageService::MAX_BYTES);
        self::assertSame([], $this->storedFiles());
    }

    public function testACheckOfAnotherCommunityIsNotFoundByIdNorByKey(): void
    {
        $foreign = $this->check('IDCHK-999-1-100', [], 2);
        $svc = $this->service();

        foreach ([['check_id' => $foreign], ['idempotency_key' => 'IDCHK-999-1-100'], ['check_id' => 987654], ['idempotency_key' => '../../etc'], []] as $ref) {
            $res = $svc->ingest(1, $ref, self::image(), 7);
            self::assertFalse($res['ok']);
            self::assertSame('not_found', $res['error']);
            self::assertSame(404, $res['status']);
        }
        self::assertSame([], $this->storedFiles());
        self::assertFalse(array_key_exists(SseCheckPaperImageService::RESULT_KEY, $this->rawResults($foreign)));

        // La photo d'une communauté n'est ni décrite ni servie à une autre.
        self::assertTrue($svc->ingest(2, ['check_id' => $foreign], self::image(), null)['ok']);
        self::assertNull($svc->resolve(1, $foreign, false));
        self::assertNull($svc->view(1, (new SseIdCheckRepository())->find($foreign, 2)));
        self::assertNotNull($svc->resolve(2, $foreign, false));
    }

    public function testTheRateIsBoundedPerCommunityAndAccount(): void
    {
        $id = $this->check('IDCHK-765-5-100');
        $svc = $this->service();
        $image = self::image('jpeg', 320, 200);
        for ($i = 0; $i < SseCheckPaperImageService::RATE_MAX; $i++) {
            self::assertTrue($svc->ingest(9, ['check_id' => 1], '', 7)['error'] !== 'too_many_requests', 'envoi ' . $i);
        }
        $res = $svc->ingest(9, ['check_id' => 1], $image, 7);
        self::assertSame('too_many_requests', $res['error']);
        self::assertSame(429, $res['status']);
        // Une autre communauté n'est pas freinée par celle-là.
        self::assertTrue($svc->ingest(1, ['check_id' => $id], $image, 7)['ok']);
    }

    public function testTheSameFileIsStoredOnceAndANewPhotoReplacesTheOldOne(): void
    {
        $id = $this->check('IDCHK-765-6-100');
        $svc = $this->service();
        $first = self::image('jpeg', 800, 500);

        self::assertFalse($svc->ingest(1, ['check_id' => $id], $first, 7)['duplicate']);
        $files = $this->storedFiles();
        self::assertCount(2, $files, 'image et miniature');
        self::assertTrue($svc->ingest(1, ['check_id' => $id], $first, 7)['duplicate']);
        self::assertSame($files, $this->storedFiles());

        self::assertFalse($svc->ingest(1, ['check_id' => $id], self::image('jpeg', 800, 500, false, 30), 7)['duplicate']);
        $after = $this->storedFiles();
        self::assertCount(2, $after, 'les fichiers de l’ancienne photo sont retirés');
        self::assertSame([], array_values(array_intersect($files, $after)));
    }

    // ------------------------------------------------------------------ lecture

    public function testANonMemberPaperPhotoIsShownThroughTheControlledRouteOnly(): void
    {
        $subject = (new SseIdentityRepository())->create(1, ['last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'kind' => 'civil']);
        $id = $this->check('IDCHK-765-7-100', ['identity_id' => $subject]);
        $svc = $this->service(self::PEER);
        $svc->ingest(1, ['check_id' => $id], self::image(), 7);

        $view = $svc->view(1, (new SseIdCheckRepository())->find($id, 1));
        self::assertNotNull($view);
        self::assertStringContainsString('atak/sse/papiers/controles/' . $id . '/photo-papier', $view['url']);
        self::assertStringContainsString('v=mini', $view['thumb_url']);
        self::assertStringNotContainsString('sse-private', (string) json_encode($view));
        self::assertStringNotContainsString('uploads', (string) json_encode($view));

        $full = $svc->resolve(1, $id, false);
        $mini = $svc->resolve(1, $id, true);
        self::assertSame('image/jpeg', $full['mime']);
        self::assertFalse($full['confidential']);
        self::assertNotSame($full['file'], $mini['file']);
        self::assertLessThan(filesize($full['file']), filesize($mini['file']));
        self::assertStringStartsWith(realpath($this->private), (string) realpath($full['file']));
    }

    public function testAMemberIdentityPhotoIsNeitherListedNorServedWithoutHierarchy(): void
    {
        $member = (new SseIdentityRepository())->create(1, ['last_name' => 'Marchetti', 'first_name' => 'Ugo', 'kind' => 'civil', 'user_id' => self::OWNER]);
        $id = $this->check('IDCHK-765-8-100', ['identity_id' => $member]);
        $this->service()->ingest(1, ['check_id' => $id], self::image(), 7);
        $check = (new SseIdCheckRepository())->find($id, 1);

        $peer = $this->service(self::PEER);
        self::assertNull($peer->view(1, $check), 'pas listée');
        self::assertSame([], $peer->views(1, [$check]));
        $this->gateAudit = [];
        self::assertNull($peer->resolve(1, $id, false), 'pas servie');
        self::assertNull($peer->resolve(1, $id, true));
        self::assertCount(2, $this->gateAudit, 'refus notés au journal');

        self::assertNull($this->service(0)->resolve(1, $id, false), 'session sans compte');

        foreach ([self::OWNER, self::CHEF] as $allowed) {
            $svc = $this->service($allowed);
            self::assertNotNull($svc->view(1, $check));
            $res = $svc->resolve(1, $id, false);
            self::assertNotNull($res);
            self::assertTrue($res['confidential']);
            self::assertSame(self::OWNER, $res['owner']);
        }
    }

    public function testThePhotoOfAMemberPaperFollowsThePaperIdentityWhenTheCheckHasNone(): void
    {
        $member = (new SseIdentityRepository())->create(1, ['last_name' => 'Marchetti', 'first_name' => 'Ugo', 'kind' => 'civil', 'user_id' => self::OWNER]);
        $doc = (new SseIdDocumentRepository())->create(1, ['identity_id' => $member, 'doc_type' => 'passport', 'country_code' => 'USA', 'doc_number' => 'US11112222'], 7);
        $id = $this->check('IDCHK-765-9-100', ['document_id' => $doc, 'doc_number' => 'US11112222']);
        $this->service()->ingest(1, ['check_id' => $id], self::image(), 7);

        self::assertNull($this->service(self::PEER)->resolve(1, $id, false));
        self::assertSame([], $this->service(self::PEER)->forDocument(1, $doc));
        self::assertCount(1, $this->service(self::CHEF)->forDocument(1, $doc));
    }

    public function testASecretPaperPhotoNeedsTheClearanceThatOpensGameTruth(): void
    {
        $subject = (new SseIdentityRepository())->create(1, ['last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'kind' => 'civil']);
        $doc = (new SseIdDocumentRepository())->create(1, ['identity_id' => $subject, 'doc_type' => 'passport', 'country_code' => 'USA', 'doc_number' => 'US33334444', 'st_origin' => 'forged'], 7);
        $hydrated = (new SseIdDocumentRepository())->find($doc, 1);
        self::assertTrue(MemberIdentityPapersService::docIsSecret($hydrated));
        $id = $this->check('IDCHK-765-10-100', ['document_id' => $doc, 'identity_id' => $subject, 'doc_number' => 'US33334444']);
        $this->service()->ingest(1, ['check_id' => $id], self::image(), 7);
        $check = (new SseIdCheckRepository())->find($id, 1);

        self::assertNull($this->service(7, false)->view(1, $check));
        self::assertNull($this->service(7, false)->resolve(1, $id, false));
        self::assertNotNull($this->service(7, true)->resolve(1, $id, false));
    }

    // ------------------------------------------------------------------ papier consigné

    public function testThePhotoFollowsThePaperRecordedFromTheCheck(): void
    {
        $subject = (new SseIdentityRepository())->create(1, ['last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'kind' => 'civil', 'person_id' => 5]);
        $identity = (new SseIdentityRepository())->find($subject, 1);
        $id = $this->check('IDCHK-765-11-100');
        $other = $this->check('IDCHK-765-11-101', ['doc_number' => 'US99990000']);
        $svc = $this->service();
        $svc->ingest(1, ['idempotency_key' => 'IDCHK-765-11-100'], self::image(), 7);

        // Modale « Consigner depuis les contrôles » : la remontée sait qu'elle a une photo, l'aperçu passe par la route contrôlée.
        $rows = (new SseCheckPaperSelectionService())->candidates(1, $identity);
        $byId = array_column($rows, null, 'id');
        self::assertTrue($byId[$id]['has_paper_image']);
        self::assertFalse($byId[$other]['has_paper_image']);
        $previews = $svc->views(1, array_values(array_filter($rows, static fn (array $r): bool => $r['has_paper_image'])));
        self::assertSame([$id], array_keys($previews));
        self::assertStringContainsString('/photo-papier?v=mini', $previews[$id]['thumb_url']);

        $out = (new SseCheckPaperSelectionService())->consign(1, $identity, [$id => []], 7);
        self::assertCount(1, $out['created']);
        $docId = $out['created'][0]['doc_id'];

        $images = $svc->forDocument(1, $docId);
        self::assertCount(1, $images, 'le papier consigné retrouve la photo par le contrôle rattaché');
        self::assertSame($id, $images[0]['check_id']);
        self::assertSame([], $svc->forDocument(2, $docId), 'jamais pour une autre communauté');
        self::assertSame([], $svc->forDocument(1, 0));
        // Le rattachement n'a pas déplacé ni recopié le fichier.
        self::assertCount(2, $this->storedFiles());
        self::assertNotNull($svc->resolve(1, $id, false));
    }

    public function testViewsNeverPrintAFilePathAndRoutesAreControlled(): void
    {
        $root = dirname(__DIR__, 2);
        $read = static fn (string $rel): string => str_replace("\r\n", "\n", (string) file_get_contents($root . '/' . $rel));
        $routes = $read('routes/web.php');
        self::assertMatchesRegularExpression('#\$router->post\(\'/api/sse/identity/checks/image\', \[\\\\App\\\\Controllers\\\\Api\\\\SseIdentityApiController::class, \'checkImageStore\'\]\);#', $routes);
        self::assertMatchesRegularExpression('#/atak/sse/papiers/controles/\{id\}/photo-papier\', \[[^\]]+\'checkPaperImage\'\], \$mwSsePortal\);#', $routes);

        $api = $read('app/Controllers/Api/SseIdentityApiController.php');
        $method = substr($api, (int) strpos($api, 'public function checkImageStore'), 900);
        self::assertStringContainsString('$this->guard($request, SseCaseRepository::CLASS_COMMAND)', $method, 'même garde que l’envoi du contrôle');
        self::assertStringContainsString('is_uploaded_file', $api);

        foreach (['views/atak/sse/papers/check_show.php', 'views/atak/sse/papers/document_show.php', 'views/atak/sse/papers/_remontees_dialog.php'] as $view) {
            $src = $read($view);
            self::assertStringContainsString("['thumb_url']", $src, $view);
            self::assertStringNotContainsString('sse-private', $src, $view);
            self::assertStringNotContainsString('sse-ds', $src, $view);
            foreach (['fiction', 'SIMULATION', 'spécimen', 'specimen', 'jeu de rôle'] as $banned) {
                self::assertStringNotContainsString($banned, $src, $view . ' : ' . $banned);
            }
        }
        $web = $read('app/Controllers/Web/SseIdentityRegistryController.php');
        $serve = substr($web, (int) strpos($web, 'public function checkPaperImage'), 2600);
        self::assertStringContainsString("SseAccessPolicy::sessionAllows('papiers', SseAccessCatalog::LEVEL_READ", $serve);
        self::assertStringContainsString('recordConsultation', $serve);
        self::assertStringContainsString("'private, no-store'", $serve);
        self::assertStringContainsString('nosniff', $serve);
        self::assertContains('sse-id-checks', SseImageStorage::FOLDERS);
    }
}
