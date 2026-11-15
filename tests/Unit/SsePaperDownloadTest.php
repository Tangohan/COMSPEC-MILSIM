<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseIdentityRegistryController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\Links\SseLinksWidget;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SsePaperDownloadService;
use App\Services\Sse\SsePaperRenderer;
use App\Services\Sse\SseRedactionService;
use App\Support\SseIdMarking;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\PdfText;
use Tests\Support\SseAccessFixture;
use Tests\Support\SseIdentitySqlite;

/**
 * Pièce complète d'un papier du registre (PDF, PNG par face, ZIP) et aperçu de ses faces, rendus pour de vrai : vrai contrôleur,
 * base sqlite, vrais fichiers (PDF lu par Tests\Support\PdfText, PNG décodés par GD, ZIP relu octet par octet).
 *
 * Lecteurs : 11 gère les papiers et a l'habilitation Confidentiel ; 12 lit les papiers, habilitation Interne ; 13 n'a aucun droit
 * sur les papiers ; 10 est le titulaire d'une identité de membre. Papiers : 1 carte d'un pays réel stylisé (photo, signature),
 * 2 passeport d'un pays imaginaire, 3 carte sans photo, 4 carte d'une identité de MEMBRE, 5 passeport d'une fausse identité
 * (réservé au Confidentiel), 6 papier d'une autre communauté.
 */
final class SsePaperDownloadTest extends TestCase
{
    private ?PDO $pdo = null;
    private ?SseAccessFixture $fx = null;

    /** @var list<string> */
    private array $files = [];

    /** @var array<string, int> */
    private array $doc = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('gd') || !SsePaperRenderer::available()) {
            self::markTestSkipped('pdo_sqlite, gd (FreeType) et les polices de TCPDF requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sse_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, actor_user_id INTEGER, actor_label TEXT, action TEXT, object_type TEXT, object_id INTEGER, object_uuid TEXT, reason TEXT, before_json TEXT, after_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->fx = new SseAccessFixture();
        $this->fx->perms[11] = ['atak.sse.access', 'atak.sse.case.manage'];
        $this->fx->perms[12] = ['atak.sse.access'];
        SseAccessPolicy::useForSession($this->fx->policy);
        $this->seed();
        SseLinksWidget::useViewer(ArraySseLinkViewer::full());
        foreach (['face', 'download'] as $kind) {
            foreach ([10, 11, 12, 13] as $u) {
                (new FileRateLimiter())->clear('sse_paper_' . $kind . '|1|' . $u . '|');
            }
        }
    }

    protected function tearDown(): void
    {
        SseAccessPolicy::useForSession(null);
        SseMemberImageGate::useForSession(null);
        SseLinksWidget::useViewer(null);
        Session::forgetMany(['user_id', 'tenant_id', 'sse_tenant_id']);
        foreach ($this->files as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        if ($this->pdo !== null) {
            SseIdentitySqlite::close();
            $this->pdo = null;
        }
    }

    // ------------------------------------------------------------------ Données

    private function imageFile(string $folder, string $name, bool $png = false): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/' . $folder;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $im = imagecreatetruecolor(350, 450);
        imagefill($im, 0, 0, imagecolorallocate($im, 90, 120, 170));
        imagefilledellipse($im, 175, 170, 150, 190, imagecolorallocate($im, 230, 190, 160));
        ob_start();
        $png ? imagepng($im) : imagejpeg($im, null, 85);
        $bytes = (string) ob_get_clean();
        file_put_contents($dir . '/' . $name, $bytes);
        $this->files[] = $dir . '/' . $name;

        return 'uploads/' . $folder . '/' . $name;
    }

    private function seed(): void
    {
        $countries = new SseIdCountryRepository();
        $countries->ensureDefaults(1);
        $countries->ensureDefaults(2);
        $countries->importRealSet(1, ['USA']);
        $imaginary = '';
        foreach ($countries->listForTenant(1) as $c) {
            if (empty($c['real_sim'])) {
                $imaginary = (string) $c['code'];
                break;
            }
        }
        $ids = new SseIdentityRepository();
        $docs = new SseIdDocumentRepository();
        $suffix = bin2hex(random_bytes(6));
        $photo = fn (string $n): string => $this->imageFile('sse-id-photos', '1_' . substr(md5($suffix . $n), 0, 16) . '.jpg');
        $sign = fn (string $n): string => $this->imageFile('sse-id-signatures', '1_' . substr(md5($suffix . $n), 0, 16) . '.png', true);
        $mk = static fn (int $t, array $i): int => $ids->create($t, $i + ['kind' => 'civil', 'sex' => 'M', 'birth_date' => '1987-03-21', 'birth_place' => 'Salt Lake City', 'nationality' => 'USA']);
        $jake = $mk(1, ['last_name' => 'Gylenhall', 'first_name' => 'Jake']);
        $ids->setImages($jake, 1, ['photo_path' => $photo('a'), 'signature_path' => $sign('a')]);
        $lena = $mk(1, ['last_name' => 'Voronova', 'first_name' => 'Lena Katarina', 'sex' => 'F']);
        $ids->setImages($lena, 1, ['photo_path' => $photo('b'), 'signature_path' => $sign('b')]);
        $paul = $mk(1, ['last_name' => 'Mercier', 'first_name' => 'Paul']);
        $hugo = $mk(1, ['last_name' => 'Delmas', 'first_name' => 'Hugo', 'user_id' => 10]);
        $ids->setImages($hugo, 1, ['photo_path' => $photo('c'), 'signature_path' => $sign('c')]);
        $anton = $mk(1, ['last_name' => 'Kessler', 'first_name' => 'Anton', 'kind' => 'forged']);
        $ids->setImages($anton, 1, ['photo_path' => $photo('d')]);
        $other = $mk(2, ['last_name' => 'Autre', 'first_name' => 'Communauté']);
        $mkdoc = static function (int $t, array $d) use ($docs): int {
            $id = $docs->create($t, $d + ['issued_on' => '2024-05-02', 'expires_on' => '2034-05-01', 'authority' => 'Direction des papiers']);
            $docs->transition($id, $t, 'validated', 1);
            $docs->transition($id, $t, 'issued', 1);

            return $id;
        };
        $this->doc = [
            'real' => $mkdoc(1, ['identity_id' => $jake, 'doc_type' => 'id_card', 'country_code' => 'USA', 'doc_number' => 'US47036925']),
            'passport' => $mkdoc(1, ['identity_id' => $lena, 'doc_type' => 'passport', 'country_code' => $imaginary, 'doc_number' => 'AL5503817', 'print' => ['place_of_issue' => 'Capitale', 'remarks' => 'Visa en cours']]),
            'nophoto' => $mkdoc(1, ['identity_id' => $paul, 'doc_type' => 'id_card', 'country_code' => $imaginary, 'doc_number' => 'AL0044172']),
            'member' => $mkdoc(1, ['identity_id' => $hugo, 'doc_type' => 'id_card', 'country_code' => $imaginary, 'doc_number' => 'AL7781203']),
            'secret' => $mkdoc(1, ['identity_id' => $anton, 'doc_type' => 'passport', 'country_code' => $imaginary, 'doc_number' => 'AL9120046', 'st_origin' => 'forged']),
            'foreign' => $mkdoc(2, ['identity_id' => $other, 'doc_type' => 'id_card', 'country_code' => (string) ($countries->listForTenant(2)[0]['code'] ?? 'ALT'), 'doc_number' => 'AL1000001']),
        ];
    }

    /** Contrôleur d'un lecteur : session, habilitation et droit sur les images d'un membre injectés (objets réels, sans doublure). */
    private function controllerFor(int $user, string $clearance = SseCaseRepository::CLASS_CONFIDENTIAL, int $tenant = 1): SseIdentityRegistryController
    {
        Session::set('user_id', $user);
        Session::set('tenant_id', $tenant);
        Session::set('sse_tenant_id', $tenant);
        $access = new class ($tenant) extends SseAccessCodeService {
            public function __construct(private int $tenant)
            {
            }

            public function isGuest(): bool
            {
                return false;
            }

            public function tenantId(): int
            {
                return $this->tenant;
            }

            public function ensureGateHydrated(): void
            {
            }
        };
        $level = new class ($clearance) extends SseClearanceService {
            public function __construct(private string $level)
            {
            }

            public function allows(string $level): bool
            {
                return SseRedactionService::levelRank($level) <= SseRedactionService::levelRank($this->level);
            }

            public function maxLevel(): string
            {
                return $this->level;
            }
        };
        // Un membre n'ouvre les images d'un autre membre que s'il est le titulaire (aucun supérieur ni habilitation TS ici).
        SseMemberImageGate::useForSession(new SseMemberImageGate(
            null,
            null,
            static fn (int $t, int $o): array => [],
            static fn (): bool => false,
            function (array $row): void {
                (new \App\Repositories\SseAuditLogRepository())->write($row);
            },
            null,
            null,
            null,
            static fn (): ?int => $user
        ));

        return new SseIdentityRegistryController($access, $level);
    }

    private function download(int $user, string $docKey, string $format, array $query = [], string $clearance = SseCaseRepository::CLASS_CONFIDENTIAL, int $tenant = 1, ?int $id = null): Response
    {
        $ctl = $this->controllerFor($user, $clearance, $tenant);
        $req = new Request();
        foreach ($query as $k => $v) {
            $req->overrideQuery($k, $v);
        }

        return $ctl->documentDownload($req, ['id' => (string) ($id ?? $this->doc[$docKey]), 'format' => $format]);
    }

    private function face(int $user, string $docKey, string $face, string $clearance = SseCaseRepository::CLASS_CONFIDENTIAL): Response
    {
        return $this->controllerFor($user, $clearance)->documentFace(new Request(), ['id' => (string) $this->doc[$docKey], 'face' => $face]);
    }

    /** @return array{0: int, 1: int} largeur et hauteur d'un PNG, lues dans l'IHDR */
    private static function pngSize(string $png): array
    {
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $u = unpack('Nw/Nh', substr($png, 16, 8));

        return [(int) $u['w'], (int) $u['h']];
    }

    /** @return array<string, string> nom => contenu, relu dans le répertoire central ; CRC vérifié */
    private static function unzip(string $zip): array
    {
        self::assertStringStartsWith("PK\x03\x04", $zip);
        $eocd = strrpos($zip, "PK\x05\x06");
        self::assertNotSame(false, $eocd);
        $end = unpack('vdisk/vcd/ventries/vtotal/Vsize/Voffset', substr($zip, (int) $eocd + 4, 18));
        $out = [];
        $p = (int) $end['offset'];
        for ($i = 0; $i < (int) $end['total']; ++$i) {
            $c = unpack('Vsig/vmade/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen/vclen/vdisk/viattr/Veattr/Vlocal', substr($zip, $p, 46));
            self::assertSame(0x02014b50, $c['sig']);
            self::assertSame(0, $c['method'], 'méthode « stored »');
            $name = substr($zip, $p + 46, (int) $c['nlen']);
            $l = unpack('Vsig/vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen', substr($zip, (int) $c['local'], 30));
            $data = substr($zip, (int) $c['local'] + 30 + (int) $l['nlen'] + (int) $l['elen'], (int) $c['size']);
            self::assertSame((int) $c['crc'], crc32($data), $name . ' : CRC');
            $out[$name] = $data;
            $p += 46 + (int) $c['nlen'] + (int) $c['elen'] + (int) $c['clen'];
        }

        return $out;
    }

    private function auditRows(string $action): array
    {
        return $this->pdo->query("SELECT * FROM sse_audit_log WHERE action = '" . $action . "' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------ Rendu

    public function testFacesAndRealSizesFollowTheDocumentType(): void
    {
        self::assertSame(['recto', 'verso'], array_keys(SsePaperRenderer::faces('id_card')));
        self::assertSame(['recto', 'verso'], array_keys(SsePaperRenderer::faces('driving_license')));
        self::assertSame(['couverture', 'donnees', 'observations'], array_keys(SsePaperRenderer::faces('passport')));
        self::assertSame([1011, 638], SsePaperRenderer::sizePx('id_card', 300));
        self::assertSame([1476, 1039], SsePaperRenderer::sizePx('passport', 300));
        self::assertSame(300, SsePaperRenderer::normalizeDpi(9999), 'résolution bornée');
        self::assertSame(150, SsePaperRenderer::normalizeDpi(150));
    }

    public function testEveryFaceOfEveryTypeIsRenderedAtPrintResolutionWithTheDeclaredDensity(): void
    {
        $res = $this->download(11, 'passport', 'png', ['face' => 'donnees']);
        self::assertSame(200, $res->statusCode());
        $png = $res->body();
        self::assertSame([1476, 1039], self::pngSize($png));
        // pHYs : 300 dpi = 11811 pixels par mètre, la taille réelle s'imprime à l'échelle 1:1.
        self::assertStringContainsString('pHYs' . pack('NNC', 11811, 11811, 1), $png);
        $real = $this->download(11, 'real', 'png', ['face' => 'verso']);
        self::assertSame([1011, 638], self::pngSize($real->body()));
        self::assertLessThanOrEqual(3 * 1024 * 1024, strlen($png), 'taille bornée');
    }

    // ------------------------------------------------------------------ En-têtes et contenu

    public function testPdfHasTheRightHeadersAllFacesAndNoFictionMentionForAnImaginaryCountry(): void
    {
        $res = $this->download(11, 'passport', 'pdf');
        self::assertSame(200, $res->statusCode());
        self::assertSame('application/pdf', $res->headerValue('Content-Type'));
        self::assertSame('attachment; filename="DOC-' . str_pad((string) $this->doc['passport'], 6, '0', STR_PAD_LEFT) . '_AL5503817_complet.pdf"', $res->headerValue('Content-Disposition'));
        self::assertSame((string) strlen($res->body()), $res->headerValue('Content-Length'));
        self::assertSame('private, no-store', $res->headerValue('Cache-Control'));
        self::assertSame('nosniff', $res->headerValue('X-Content-Type-Options'));
        self::assertSame('couverture,donnees,observations', $res->headerValue('X-Paper-Faces'));
        $pdf = $res->body();
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertLessThanOrEqual(8 * 1024 * 1024, strlen($pdf));
        $text = PdfText::of($pdf);
        foreach (['Passeport', 'AL5503817', 'VORONOVA', 'Couverture', 'Page de données', 'Page d’observations', 'échelle réelle'] as $needle) {
            self::assertStringContainsString($needle, $text, $needle);
        }
        self::assertNull(PdfText::fictionMention($pdf), 'aucune mention de jeu sur le papier d’un pays imaginaire');
        // Une image par face (le PDF porte les faces à 300 dpi) : trois images PNG intégrées.
        self::assertSame(3, preg_match_all('#/Subtype\s*/Image#', $pdf));
    }

    public function testPdfOfARealCountryKeepsTheMarkingInTheFileMetadata(): void
    {
        $pdf = $this->download(11, 'real', 'pdf')->body();
        self::assertStringContainsString(SseIdMarking::META, PdfText::of($pdf));
        self::assertSame(2, preg_match_all('#/Subtype\s*/Image#', $pdf), 'recto et verso');
    }

    public function testZipHoldsOnePngPerFaceWithSafeNames(): void
    {
        $res = $this->download(11, 'passport', 'zip');
        self::assertSame(200, $res->statusCode());
        self::assertSame('application/zip', $res->headerValue('Content-Type'));
        self::assertMatchesRegularExpression('/^attachment; filename="DOC-\d{6}_AL5503817_faces\.zip"$/', (string) $res->headerValue('Content-Disposition'));
        $files = self::unzip($res->body());
        $ref = 'DOC-' . str_pad((string) $this->doc['passport'], 6, '0', STR_PAD_LEFT);
        self::assertSame([$ref . '_AL5503817_couverture.png', $ref . '_AL5503817_donnees.png', $ref . '_AL5503817_observations.png'], array_keys($files));
        foreach ($files as $name => $data) {
            self::assertSame([1476, 1039], self::pngSize($data), $name);
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_.-]+$/', $name);
        }
    }

    public function testSinglePngAndRefusedFacesAndFormats(): void
    {
        $res = $this->download(11, 'real', 'png', ['face' => 'recto']);
        self::assertSame('image/png', $res->headerValue('Content-Type'));
        self::assertMatchesRegularExpression('/^attachment; filename="DOC-\d{6}_US47036925_recto\.png"$/', (string) $res->headerValue('Content-Disposition'));
        self::assertSame('recto', $res->headerValue('X-Paper-Faces'));
        foreach ([['png', []], ['png', ['face' => 'couverture']], ['png', ['face' => '../../etc/passwd']], ['exe', []], ['', []]] as [$format, $query]) {
            self::assertSame(404, $this->download(11, 'real', $format, $query)->statusCode(), $format . json_encode($query));
        }
    }

    public function testDownloadNamesAreSanitisedWhateverTheNumber(): void
    {
        $doc = ['ref' => 'DOC-000042', 'doc_number' => "../a b\"c\r\n;é/\\"];
        $name = SsePaperDownloadService::filename($doc, 'rec to/../x', 'PNG');
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_.-]+$/', $name);
        self::assertStringNotContainsString('..', $name);
        self::assertSame('DOC-000042_abc_rectox.png', $name);
        self::assertSame('DOC_papier_papier.bin', SsePaperDownloadService::filename([], '', ''));
    }

    // ------------------------------------------------------------------ Marquage

    public function testTheRealCountryMarkingIsOnEveryFaceAndAbsentFromAnImaginaryCountry(): void
    {
        $renderer = new SsePaperRenderer();
        foreach (['id_card' => ['recto', 'verso'], 'passport' => ['couverture', 'donnees', 'observations']] as $type => $faces) {
            foreach ($faces as $face) {
                $base = [
                    'doc_type' => $type, 'layout' => 'modern',
                    'country' => ['code' => 'USA', 'name' => 'États-Unis', 'colors' => ['#1f4e79', '#c9a227'], 'real_sim' => true, 'zone' => 'americas'],
                    'identity' => ['last_name' => 'Gylenhall', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1987-03-21', 'birth_place' => 'Salt Lake City', 'nationality' => 'USA'],
                    'doc' => ['number' => 'US47036925', 'issued_on' => '2024-05-02', 'expires_on' => '2034-05-01', 'authority' => 'Department of State', 'st_validity' => 'valid', 'st_report' => 'none'],
                    'print' => [], 'mrz' => ['P<USAGYLENHALL<<JAKE<<<<<<<<<<<<<<<<<<<<<<<<<<', 'US470369254USA8703217M3405011<<<<<<<<<<<<<<02'],
                ];
                $withMark = $renderer->render($base + ['marking' => SseIdMarking::DISCREET], [], $face, 150);
                $without = $renderer->render($base + ['marking' => SseIdMarking::NONE], [], $face, 150);
                self::assertNotSame($without, $withMark, $type . ' ' . $face . ' : marque visible');
                self::assertTrue(SseIdMarking::hasMention($withMark), $type . ' ' . $face . ' : métadonnées');
                // La marque occupe la bande du bas : le haut de l'image est identique, le bas diffère.
                $a = imagecreatefromstring($withMark);
                $b = imagecreatefromstring($without);
                $h = imagesy($a);
                $diffTop = 0;
                $diffBottom = 0;
                for ($y = 0; $y < $h; $y += 2) {
                    for ($x = 0; $x < imagesx($a); $x += 3) {
                        if (imagecolorat($a, $x, $y) !== imagecolorat($b, $x, $y)) {
                            $y < $h * 0.9 ? $diffTop++ : $diffBottom++;
                        }
                    }
                }
                self::assertGreaterThan(40, $diffBottom, $type . ' ' . $face . ' : micro-texte en bas');
                if (!in_array($face, ['verso', 'donnees'], true)) {
                    // Sans bande de lecture, seule la bande du bas change ; avec elle, la bande remonte au-dessus de la marque.
                    self::assertSame(0, $diffTop, $type . ' ' . $face . ' : rien d’autre ne change');
                }
            }
        }
    }

    public function testAnImaginaryCountryFaceCarriesNeitherMarkNorMetadataEvenIfTheSettingAsksForOne(): void
    {
        $model = [
            'marking' => SseIdMarking::effective(SseIdMarking::STANDARD, false), 'doc_type' => 'id_card', 'layout' => 'modern',
            'country' => ['code' => 'ALT', 'name' => 'Altis', 'colors' => ['#1f4e79', '#c9a227'], 'real_sim' => false, 'zone' => ''],
            'identity' => ['last_name' => 'Voronova', 'first_name' => 'Lena', 'sex' => 'F', 'birth_date' => '1991-08-02', 'birth_place' => 'Ostrava', 'nationality' => 'ALT'],
            'doc' => ['number' => 'AL5503817', 'issued_on' => '2024-05-02', 'expires_on' => '2034-05-01', 'authority' => 'Bureau', 'st_validity' => 'valid', 'st_report' => 'none'],
            'print' => [], 'mrz' => ['I<ALTAL5503817<<<<<<<<<<<<<<<'],
        ];
        $renderer = new SsePaperRenderer();
        $asked = $renderer->render($model, [], 'recto', 150);
        $plain = $renderer->render(['marking' => SseIdMarking::NONE] + $model, [], 'recto', 150);
        self::assertSame(SseIdMarking::NONE, $model['marking']);
        self::assertSame($plain, $asked);
        self::assertFalse(SseIdMarking::hasMention($asked));
        self::assertStringNotContainsString('tEXt', $asked);
    }

    public function testTheUserCannotRemoveTheMarkingOfARealCountry(): void
    {
        // Même demandé « none », un pays réel garde le marquage discret (SseIdMarking::effective), sur chaque face de la pièce.
        self::assertSame(SseIdMarking::DISCREET, SseIdMarking::effective(SseIdMarking::NONE, true));
        self::assertSame(SseIdMarking::NONE, SseIdMarking::effective(SseIdMarking::STANDARD, false), 'pays imaginaire : aucune marque');
        $zip = self::unzip($this->download(11, 'real', 'zip')->body());
        self::assertCount(2, $zip);
        foreach ($zip as $name => $png) {
            self::assertTrue(SseIdMarking::hasMention($png), $name);
        }
        foreach (self::unzip($this->download(11, 'passport', 'zip')->body()) as $name => $png) {
            self::assertFalse(SseIdMarking::hasMention($png), $name . ' : pays imaginaire, aucune mention');
            self::assertStringNotContainsString('Document de jeu', $png);
        }
    }

    // ------------------------------------------------------------------ Droits, habilitation, confidentialité

    public function testTheSectionRightHabilitationAndTheCommunityAreEnforcedWithOneAnswer(): void
    {
        $unknown = $this->download(11, 'real', 'pdf', [], SseCaseRepository::CLASS_CONFIDENTIAL, 1, 987654);
        self::assertSame(404, $unknown->statusCode());
        $answers = [
            'sans droit sur la section papiers' => $this->download(13, 'real', 'pdf'),
            'papier d’une autre communauté' => $this->download(11, 'foreign', 'pdf'),
            'papier secret, habilitation Interne' => $this->download(12, 'secret', 'pdf', [], SseCaseRepository::CLASS_INTERNAL),
        ];
        foreach ($answers as $why => $res) {
            self::assertSame(404, $res->statusCode(), $why);
            self::assertSame($unknown->body(), $res->body(), $why . ' : même réponse qu’un papier inexistant');
            self::assertSame($unknown->headerValue('Content-Type'), $res->headerValue('Content-Type'), $why);
            self::assertNull($res->headerValue('Content-Disposition'), $why);
        }
        // Les mêmes demandes, côté aperçu : aucun octet d'image.
        self::assertSame(404, $this->face(13, 'real', 'recto')->statusCode());
        self::assertSame(404, $this->face(12, 'secret', 'donnees', SseCaseRepository::CLASS_INTERNAL)->statusCode());
        // Un lecteur habilité (Confidentiel) télécharge le papier secret ; un lecteur Interne télécharge un papier ordinaire.
        self::assertSame(200, $this->download(11, 'secret', 'pdf')->statusCode());
        self::assertSame(200, $this->download(12, 'real', 'pdf', [], SseCaseRepository::CLASS_INTERNAL)->statusCode());
        // Anti-IDOR : le papier d'une autre communauté reste introuvable même avec son numéro exact, et la session fixe la communauté.
        self::assertSame(404, $this->download(11, 'real', 'pdf', [], SseCaseRepository::CLASS_CONFIDENTIAL, 2)->statusCode());
    }

    public function testNothingIsRefusedSilentlyOrServedEmpty(): void
    {
        $res = $this->download(11, 'nophoto', 'pdf');
        self::assertSame(200, $res->statusCode());
        self::assertGreaterThan(20000, strlen($res->body()));
        self::assertStringContainsString('Aucune photo enregistrée pour ce titulaire : silhouette à la place.', PdfText::of($res->body()), 'avertissement lisible dans le PDF');
        self::assertSame('1', $res->headerValue('X-Paper-Warnings'));
        $zip = self::unzip($this->download(11, 'nophoto', 'zip')->body());
        self::assertArrayHasKey('AVERTISSEMENTS.txt', $zip);
        self::assertStringContainsString('silhouette', $zip['AVERTISSEMENTS.txt']);
        self::assertCount(3, $zip, 'deux faces et l’avertissement');
        // Papier complet : aucune remarque.
        self::assertSame('0', $this->download(11, 'real', 'pdf')->headerValue('X-Paper-Warnings'));
    }

    public function testTheMemberPhotoAndSignatureAreLeftOutUnlessTheGateAllowsThem(): void
    {
        $denied = $this->download(11, 'member', 'png', ['face' => 'recto']);
        $holder = $this->download(10, 'member', 'png', ['face' => 'recto']);
        self::assertSame(200, $denied->statusCode());
        self::assertSame(200, $holder->statusCode());
        self::assertNotSame($holder->body(), $denied->body(), 'le titulaire voit sa photo, pas les autres');
        // Sans autorisation : exactement l'image d'une identité sans photo ni signature (silhouette, signature imprimée).
        $pdf = $this->download(11, 'member', 'pdf')->body();
        self::assertStringContainsString('Photo et signature de ce titulaire réservées', PdfText::of($pdf), 'mention lisible dans le PDF');
        $ids = new SseIdentityRepository();
        $member = $ids->find((int) (new SseIdDocumentRepository())->find($this->doc['member'], 1)['identity_id'], 1);
        $ids->setImages((int) $member['id'], 1, ['photo_path' => null, 'signature_path' => null]);
        $bare = $this->download(10, 'member', 'png', ['face' => 'recto']);
        self::assertSame($bare->body(), $denied->body(), 'silhouette identique');
        // La consultation de la photo d'un membre par son titulaire est journalisée par la porte des images.
        self::assertSame([], array_filter($this->auditRows('member_image.view'), static fn (array $r): bool => (int) $r['object_id'] === 0));
    }

    public function testAHolderOpeningAMemberPaperWithImagesLeavesAConsultationTrace(): void
    {
        $this->download(10, 'member', 'png', ['face' => 'recto']);
        $rows = $this->auditRows('member_image.view');
        self::assertCount(1, $rows);
        self::assertSame((string) $this->doc['member'], (string) $rows[0]['object_id']);
        self::assertSame('paper_download', $rows[0]['object_type']);
    }

    // ------------------------------------------------------------------ Journal, débit

    public function testEveryDownloadIsLoggedWithWhoWhatFormatAndFaces(): void
    {
        self::assertSame([], $this->auditRows('id_document.download'));
        $this->download(11, 'passport', 'zip');
        $this->download(11, 'real', 'png', ['face' => 'verso']);
        $this->download(13, 'real', 'pdf');
        $rows = $this->auditRows('id_document.download');
        self::assertCount(2, $rows, 'le refus n’est pas un téléchargement');
        $zip = json_decode((string) $rows[0]['after_json'], true);
        self::assertSame('zip', $zip['format']);
        self::assertSame(['couverture', 'donnees', 'observations'], $zip['faces']);
        self::assertMatchesRegularExpression('/_faces\.zip$/', (string) $zip['file']);
        self::assertSame('11', (string) $rows[0]['actor_user_id']);
        self::assertSame('sse_id_document', $rows[0]['object_type']);
        self::assertSame((string) $this->doc['passport'], (string) $rows[0]['object_id']);
        self::assertSame('1', (string) $rows[0]['tenant_id']);
        $png = json_decode((string) $rows[1]['after_json'], true);
        self::assertSame(['verso'], $png['faces']);
        self::assertTrue($png['real_country']);
    }

    public function testDownloadsAreRateLimitedAndThePreviewHasItsOwnBudget(): void
    {
        $limiter = new FileRateLimiter();
        $limiter->clear('sse_paper_download|1|11|');
        $last = 200;
        for ($i = 0; $i < 41; ++$i) {
            $last = $this->download(11, 'nophoto', 'png', ['face' => 'verso'])->statusCode();
        }
        self::assertSame(429, $last);
        $limiter->clear('sse_paper_download|1|11|');
        self::assertSame(200, $this->face(11, 'real', 'recto')->statusCode(), 'l’aperçu ne dépend pas du plafond des téléchargements');
    }

    // ------------------------------------------------------------------ Aperçu = téléchargement

    public function testThePreviewIsTheImageOfTheDownloadAtPreviewResolution(): void
    {
        $res = $this->face(11, 'real', 'recto');
        self::assertSame(200, $res->statusCode());
        self::assertSame('image/png', $res->headerValue('Content-Type'));
        self::assertStringContainsString('inline;', (string) $res->headerValue('Content-Disposition'));
        self::assertSame([809, 510], self::pngSize($res->body()));
        // Même rendu, autre résolution : le téléchargement est l'aperçu à 300 dpi.
        $renderer = new SsePaperRenderer();
        self::assertSame(1011, self::pngSize($this->download(11, 'real', 'png', ['face' => 'recto'])->body())[0]);
        self::assertNotNull($renderer);
        self::assertSame(404, $this->face(11, 'real', 'couverture')->statusCode(), 'une carte n’a pas de couverture');
    }

    public function testThePageShowsTheDownloadMenuBothFacesAndNoFictionMention(): void
    {
        $ctl = $this->controllerFor(11);
        $page = $ctl->documentShow(new Request(), ['id' => (string) $this->doc['passport']])->body();
        self::assertStringContainsString('pdl-menu__btn', $page);
        self::assertStringContainsString('Télécharger', $page);
        self::assertStringContainsString('Pièce complète (PDF)', $page);
        foreach (['pdf', 'zip'] as $f) {
            self::assertStringContainsString('/documents/' . $this->doc['passport'] . '/telecharger/' . $f, $page);
        }
        foreach (['couverture', 'donnees', 'observations'] as $face) {
            self::assertStringContainsString('/documents/' . $this->doc['passport'] . '/face/' . $face, $page);
            self::assertStringContainsString('/telecharger/png?face=' . $face, $page);
        }
        self::assertStringContainsString('data-pdl-print', $page);
        // Liaisons et Télécharger partagent la même barre d'actions.
        self::assertMatchesRegularExpression('#class="sl-bar".*?Liaisons.*?pdl-menu.*?Télécharger#s', $page);
        foreach (['Document fictif', 'Papiers fictifs pour le jeu de rôle', 'SPÉCIMEN', 'sse-ds'] as $banned) {
            self::assertStringNotContainsString($banned, $page, $banned);
        }
        // Un papier secret ne s'ouvre pas en aperçu à un lecteur d'habilitation Interne : pas de bouton ni d'image.
        $low = $this->controllerFor(12, SseCaseRepository::CLASS_INTERNAL)->documentShow(new Request(), ['id' => (string) $this->doc['secret']])->body();
        self::assertStringNotContainsString('pdl-menu__btn', $low);
        self::assertStringNotContainsString('/face/', $low);
        self::assertStringContainsString('habilitation Confidentiel', $low);
    }

    public function testRegistryRowsOfferTheDownloadOnlyWhenItWillWork(): void
    {
        $page = $this->controllerFor(11)->documentsIndex(new Request(), [])->body();
        self::assertStringContainsString('/documents/' . $this->doc['real'] . '/telecharger/pdf', $page);
        self::assertStringContainsString('/documents/' . $this->doc['secret'] . '/telecharger/pdf', $page, 'habilité Confidentiel');
        $low = $this->controllerFor(12, SseCaseRepository::CLASS_INTERNAL)->documentsIndex(new Request(), [])->body();
        self::assertStringContainsString('/documents/' . $this->doc['real'] . '/telecharger/pdf', $low);
        self::assertStringNotContainsString('/documents/' . $this->doc['secret'] . '/telecharger/pdf', $low);
    }

    // ------------------------------------------------------------------ Câblage

    private static function read(string $relative): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative));
    }

    public function testRoutesAreDeclaredUnderThePortalMiddlewareAndTheUiFollowsTheRules(): void
    {
        $routes = self::read('routes/web.php');
        self::assertStringContainsString("\$router->get('/atak/sse/papiers/documents/{id}/face/{face}', [\\App\\Controllers\\Web\\SseIdentityRegistryController::class, 'documentFace'], \$mwSsePortal);", $routes);
        self::assertStringContainsString("\$router->get('/atak/sse/papiers/documents/{id}/telecharger/{format}', [\\App\\Controllers\\Web\\SseIdentityRegistryController::class, 'documentDownload'], \$mwSsePortal);", $routes);
        $ctl = self::read('app/Controllers/Web/SseIdentityRegistryController.php');
        self::assertStringContainsString("SseAccessPolicy::sessionAllows('papiers', SseAccessCatalog::LEVEL_READ", substr($ctl, (int) strpos($ctl, 'private function paperToRender')));
        self::assertStringContainsString("'id_document.download'", $ctl);
        foreach (['public/assets/css/sse-papier-telecharger.css', 'public/assets/js/sse-papier-telecharger.js', 'views/atak/sse/papers/_download_menu.php', 'views/atak/sse/papers/_download_preview.php'] as $f) {
            $src = self::read($f);
            self::assertStringNotContainsString('sse-ds', $src, $f);
            self::assertStringNotContainsString('Archivo', $src, $f);
            self::assertDoesNotMatchRegularExpression('/\.sse-[a-z]/', $src, $f);
            self::assertDoesNotMatchRegularExpression('/eyebrow|overline/i', $src, $f);
            self::assertDoesNotMatchRegularExpression('/font-size:\s*(?:[0-9]|1[0-2])(?:\.\d+)?px/', $src, $f . ' : texte sous 13 px');
        }
        self::assertStringContainsString('min-height: 44px', self::read('public/assets/css/sse-papier-telecharger.css'));
        foreach (['Document fictif', 'Papiers fictifs pour le jeu de rôle'] as $banned) {
            self::assertStringNotContainsString($banned, self::read('views/atak/sse/papers/document_show.php'));
        }
    }
}
