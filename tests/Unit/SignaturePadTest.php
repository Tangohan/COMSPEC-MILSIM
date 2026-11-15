<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseIdWorkshopController;
use App\Core\Request;
use App\Services\Courrier\DocumentSignatureService;
use App\Services\Media\SignatureImageService;
use App\Services\Sse\SseIdImageService;
use App\Support\HtmlTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Pad de signature : contrôle serveur du dessin (data URL PNG), rangement identique à l'envoi de fichier, priorité du
 * dessin sur le fichier, vues qui incluent le partial, textes anglais, aucun texte français codé en dur dans le JS.
 */
final class SignaturePadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigpad_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $_POST = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_FILES = [];
        $this->rrmdir($this->dir);
        HtmlTranslator::reset();
        unset($GLOBALS['__sigpad_count'], $GLOBALS['__sigpad_assets']);
    }

    private function rrmdir(string $d): void
    {
        if (!is_dir($d)) {
            return;
        }
        foreach (scandir($d) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $d . DIRECTORY_SEPARATOR . $f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($d);
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    // ------------------------------------------------------------------ fabrique de PNG (sans GD)

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    /**
     * PNG 8 bits sans entrelacement. $px(x, y) rend les octets d'un pixel ; $filter 0 à 4 : filtre réellement appliqué.
     *
     * @param callable(int, int): list<int> $px
     * @param list<array{0: string, 1: string}> $extra blocs ajoutés avant IDAT
     */
    private static function png(int $w, int $h, int $color, callable $px, int $filter = 0, array $extra = []): string
    {
        $bpp = [0 => 1, 2 => 3, 4 => 2, 6 => 4][$color];
        $rows = [];
        for ($y = 0; $y < $h; $y++) {
            $row = [];
            for ($x = 0; $x < $w; $x++) {
                foreach ($px($x, $y) as $v) {
                    $row[] = $v;
                }
            }
            $rows[] = $row;
        }
        $raw = '';
        $prev = array_fill(0, $w * $bpp, 0);
        foreach ($rows as $row) {
            $out = [];
            foreach ($row as $i => $v) {
                $a = $i >= $bpp ? $row[$i - $bpp] : 0;
                $b = $prev[$i];
                $c = $i >= $bpp ? $prev[$i - $bpp] : 0;
                $pred = match ($filter) {
                    1 => $a,
                    2 => $b,
                    3 => ($a + $b) >> 1,
                    4 => (static function () use ($a, $b, $c): int {
                        $p = $a + $b - $c;
                        $pa = abs($p - $a);
                        $pb = abs($p - $b);
                        $pc = abs($p - $c);

                        return ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
                    })(),
                    default => 0,
                };
                $out[] = ($v - $pred) & 255;
            }
            $raw .= chr($filter) . pack('C*', ...$out);
            $prev = $row;
        }
        $png = "\x89PNG\r\n\x1a\n" . self::chunk('IHDR', pack('NNCCCCC', $w, $h, 8, $color, 0, 0, 0));
        foreach ($extra as [$type, $data]) {
            $png .= self::chunk($type, $data);
        }

        return $png . self::chunk('IDAT', (string) gzcompress($raw, 6)) . self::chunk('IEND', '');
    }

    /** Trait diagonal bleu foncé opaque sur fond transparent (ce que produit le canevas du pad). */
    private static function inkPng(int $filter = 0, array $extra = []): string
    {
        return self::png(120, 60, 6, static fn (int $x, int $y): array => abs($x - 2 * $y) < 3 ? [10, 20, 60, 255] : [0, 0, 0, 0], $filter, $extra);
    }

    private static function url(string $png): string
    {
        return SignatureImageService::PREFIX . base64_encode($png);
    }

    // ------------------------------------------------------------------ service : dessin accepté

    public function testValidDrawingIsAcceptedAndRebuiltCleanWithEveryPngFilter(): void
    {
        foreach ([0, 1, 2, 3, 4] as $filter) {
            $res = SignatureImageService::normalize(self::url(self::inkPng($filter)));
            self::assertTrue($res['ok'], 'filtre ' . $filter . ' : ' . ($res['error'] ?? ''));
            $png = (string) ($res['png'] ?? '');
            self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
            self::assertSame([120, 60], array_slice((array) getimagesizefromstring($png) ?: [0, 0], 0, 2));
        }
    }

    public function testMetadataAndHiddenChunksDoNotSurviveTheRebuild(): void
    {
        $dirty = self::inkPng(0, [
            ['tEXt', "Comment\0<?php system(\$_GET['c']); ?>"],
            ['eXIf', 'MM' . str_repeat('A', 40)],
            ['tIME', "\x07\xea\x01\x01\x00\x00\x00"],
            ['zzZz', 'donnees cachees'],
        ]);
        self::assertStringContainsString('<?php', $dirty);
        $res = SignatureImageService::normalize(self::url($dirty));
        self::assertTrue($res['ok']);
        $png = (string) $res['png'];
        foreach (['<?php', 'tEXt', 'eXIf', 'tIME', 'zzZz', 'donnees cachees'] as $needle) {
            self::assertStringNotContainsString($needle, $png);
        }
        self::assertSame(1, substr_count($png, 'IDAT'));
    }

    public function testWhiteBackgroundDrawingAndRgbTypeAreAccepted(): void
    {
        $rgb = self::png(100, 40, 2, static fn (int $x, int $y): array => $y > 15 && $y < 20 ? [10, 10, 10] : [255, 255, 255], 2);
        self::assertTrue(SignatureImageService::normalize(self::url($rgb))['ok']);
        $gray = self::png(100, 40, 0, static fn (int $x, int $y): array => $y > 15 && $y < 20 ? [20] : [255], 4);
        self::assertTrue(SignatureImageService::normalize(self::url($gray))['ok']);
    }

    // ------------------------------------------------------------------ service : refus

    public function testEmptyImagesAreRefused(): void
    {
        $transparent = self::png(120, 60, 6, static fn (): array => [0, 0, 0, 0]);
        $white = self::png(120, 60, 6, static fn (): array => [255, 255, 255, 255]);
        $whiteRgb = self::png(120, 60, 2, static fn (): array => [255, 255, 255]);
        $speck = self::png(120, 60, 6, static fn (int $x, int $y): array => $x < 2 && $y < 3 ? [0, 0, 0, 255] : [0, 0, 0, 0]);
        foreach ([$transparent, $white, $whiteRgb, $speck] as $i => $png) {
            $res = SignatureImageService::normalize(self::url($png));
            self::assertFalse($res['ok'], 'image vide n° ' . $i);
            self::assertStringContainsString('vide', (string) ($res['error'] ?? ''));
        }
    }

    public function testOnlyThePngDataUrlPrefixIsAccepted(): void
    {
        $b64 = base64_encode(self::inkPng());
        foreach ([
            'data:image/jpeg;base64,' . $b64,
            'data:image/png;charset=utf-8;base64,' . $b64,
            'data:image/svg+xml;base64,' . $b64,
            'DATA:image/png;base64,' . $b64,
            'data:text/html;base64,' . $b64,
            'https://exemple.test/signature.png',
            $b64,
            '',
            SignatureImageService::PREFIX,
        ] as $value) {
            self::assertFalse(SignatureImageService::normalize($value)['ok'], substr($value, 0, 40));
        }
        self::assertFalse(SignatureImageService::normalize(SignatureImageService::PREFIX . '@@@' . $b64)['ok'], 'base64 strict');
        self::assertFalse(SignatureImageService::normalize(SignatureImageService::PREFIX . 'AAAA')['ok']);
    }

    public function testSizeLimitsAreEnforcedOnTheDecodedImage(): void
    {
        $big = self::inkPng(0, [['tEXt', str_repeat('x', SignatureImageService::MAX_BYTES)]]);
        self::assertTrue(strlen($big) > SignatureImageService::MAX_BYTES);
        self::assertFalse(SignatureImageService::normalize(self::url($big))['ok']);
        // Dimensions annoncées trop grandes : refusées avant toute décompression.
        $huge = self::png(10, 10, 6, static fn (): array => [0, 0, 0, 255]);
        $huge = str_replace(pack('N', 10) . pack('N', 10), pack('N', 5000) . pack('N', 5000), $huge);
        self::assertFalse(SignatureImageService::normalize(self::url($huge))['ok']);
        $tiny = self::png(4, 4, 6, static fn (): array => [0, 0, 0, 255]);
        self::assertFalse(SignatureImageService::normalize(self::url($tiny))['ok']);
        $wide = self::png(SignatureImageService::MAX_SIDE + 1, 10, 0, static fn (): array => [0]);
        self::assertFalse(SignatureImageService::normalize(self::url($wide))['ok']);
    }

    public function testFakeAndPolyglotImagesAreRefused(): void
    {
        $png = self::inkPng();
        $cases = [
            'JPEG sous préfixe PNG' => "\xFF\xD8\xFF\xE0" . str_repeat('A', 300),
            'SVG sous préfixe PNG' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' . str_repeat(' ', 100),
            'PHP sous préfixe PNG' => '<?php system($_GET["c"]); ?>' . str_repeat('A', 100),
            'octets après IEND' => $png . "<?php echo 'x'; ?>",
            'octets avant le PNG' => "GIF89a" . $png,
            'IEND absent' => substr($png, 0, -12),
            'CRC faux' => substr($png, 0, 40) . chr(ord($png[40]) ^ 0xFF) . substr($png, 41),
        ];
        foreach ($cases as $name => $bytes) {
            self::assertFalse(SignatureImageService::normalize(self::url($bytes))['ok'], $name);
        }
    }

    public function testUnsupportedPngVariantsAndDecompressionBombsAreRefused(): void
    {
        $base = self::inkPng();
        // profondeur 16 bits, entrelacé, palette : un canevas n'en produit jamais
        foreach (['depth' => [8, 8, 16], 'interlace' => [12, 12, 1]] as $name => [$off, , $val]) {
            $ihdrData = substr($base, 16, 13);
            $mod = $ihdrData;
            $mod[$name === 'depth' ? 8 : 12] = chr($val);
            $bad = substr($base, 0, 8) . self::chunk('IHDR', $mod) . substr($base, 33);
            self::assertFalse(SignatureImageService::normalize(self::url($bad))['ok'], $name);
        }
        $palette = substr($base, 0, 8) . self::chunk('IHDR', pack('NNCCCCC', 120, 60, 8, 3, 0, 0, 0)) . substr($base, 33);
        self::assertFalse(SignatureImageService::normalize(self::url($palette))['ok'], 'palette');
        // IDAT qui se décompresse en bien plus que ce que l'IHDR annonce
        $bomb = "\x89PNG\r\n\x1a\n" . self::chunk('IHDR', pack('NNCCCCC', 120, 60, 8, 6, 0, 0, 0))
            . self::chunk('IDAT', (string) gzcompress(str_repeat("\0", 5000000), 9)) . self::chunk('IEND', '');
        self::assertTrue(strlen($bomb) < SignatureImageService::MAX_BYTES);
        self::assertFalse(SignatureImageService::normalize(self::url($bomb))['ok']);
        // filtre de ligne inconnu
        $raw = '';
        for ($y = 0; $y < 60; $y++) {
            $raw .= chr(9) . str_repeat("\x10\x20\x30\xff", 120);
        }
        $badFilter = "\x89PNG\r\n\x1a\n" . self::chunk('IHDR', pack('NNCCCCC', 120, 60, 8, 6, 0, 0, 0))
            . self::chunk('IDAT', (string) gzcompress($raw)) . self::chunk('IEND', '');
        self::assertFalse(SignatureImageService::normalize(self::url($badFilter))['ok']);
    }

    // ------------------------------------------------------------------ service : rangement

    public function testFromDataUrlStoresLikeAFileUploadWithAServerChosenName(): void
    {
        $images = new SseIdImageService($this->dir);
        $svc = new SignatureImageService($images);
        $res = $svc->fromDataUrl(self::url(self::inkPng()), 7, 'sse_identity');
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        $path = (string) ($res['path'] ?? '');
        self::assertMatchesRegularExpression('#^uploads/sse-id-signatures/7_[0-9a-f]{16}\.png$#', $path);
        $file = $this->dir . '/' . $path;
        self::assertTrue(is_file($file));
        $stored = (string) file_get_contents($file);
        self::assertSame('image/png', SseIdImageService::sniff($stored));
        self::assertStringNotContainsString('<?php', $stored);
        // même dossier, même format de nom que l'envoi de fichier
        $viaFile = $images->storeFile($file, 'signature', 7);
        self::assertMatchesRegularExpression('#^uploads/sse-id-signatures/7_[0-9a-f]{16}\.png$#', (string) ($viaFile['path'] ?? ''));
        // deux envois : deux noms différents, jamais un nom fourni
        $again = $svc->fromDataUrl(self::url(self::inkPng()), 7, 'sse_identity');
        self::assertNotSame($path, $again['path'] ?? null);
    }

    public function testFromDataUrlRefusesUnknownPurposeTenantAndBadDrawings(): void
    {
        $svc = new SignatureImageService(new SseIdImageService($this->dir));
        self::assertFalse($svc->fromDataUrl(self::url(self::inkPng()), 7, 'autre')['ok']);
        self::assertFalse($svc->fromDataUrl(self::url(self::inkPng()), 0, 'sse_identity')['ok']);
        self::assertFalse($svc->fromDataUrl('data:image/png;base64,AAAA', 7, 'sse_identity')['ok']);
        self::assertSame([], glob($this->dir . '/uploads/*/*') ?: []);
    }

    // ------------------------------------------------------------------ contrôleur : dessin ou fichier

    private function storeSignature(): array
    {
        $controller = (new \ReflectionClass(SseIdWorkshopController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, 'storeSignature');
        /** @var array<string, mixed> $res */
        $res = $method->invoke($controller, new Request(), new SseIdImageService($this->dir), 7);

        return $res;
    }

    public function testControllerStoresTheDrawingAndItWinsOverAFileSentAtTheSameTime(): void
    {
        $_POST['signature_data_url'] = self::url(self::inkPng());
        $_FILES['signature'] = ['name' => 'evil.php', 'type' => 'image/png', 'tmp_name' => $this->dir . '/absent.png', 'error' => UPLOAD_ERR_OK, 'size' => 10];
        $res = $this->storeSignature();
        self::assertTrue($res['ok']);
        self::assertMatchesRegularExpression('#^uploads/sse-id-signatures/7_[0-9a-f]{16}\.png$#', (string) $res['path']);
    }

    public function testControllerRefusesABadDrawingEvenWithAValidFile(): void
    {
        $_POST['signature_data_url'] = 'data:image/png;base64,AAAA';
        $res = $this->storeSignature();
        self::assertFalse($res['ok']);
        self::assertArrayHasKey('error', $res);
    }

    public function testControllerKeepsTheFileUploadPathWhenNoDrawingIsSent(): void
    {
        $res = $this->storeSignature();
        self::assertFalse($res['ok']);
        self::assertTrue($res['none'] ?? false, 'rien envoyé : aucune signature, comme avant');
        $_FILES['signature'] = ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $this->dir . '/absent.png', 'error' => UPLOAD_ERR_OK, 'size' => 10];
        $res = $this->storeSignature();
        self::assertFalse($res['ok'], 'un fichier qui ne vient pas d’un envoi HTTP reste refusé par storeUpload');
        self::assertArrayHasKey('error', $res);
        $ctrl = self::read('app/Controllers/Web/SseIdWorkshopController.php');
        self::assertStringContainsString("storeUpload(\$_FILES['signature'] ?? null, 'signature', \$tenantId)", $ctrl);
        self::assertSame(2, substr_count($ctrl, "\$request->inputString('signature_remove') === '1'"), 'retrait de la signature inchangé');
        self::assertSame(2, substr_count($ctrl, '$this->storeSignature($request, $images, $tenantId)'));
    }

    // ------------------------------------------------------------------ Bureau Courrier et documents

    public function testCourrierServiceNowValidatesDrawingsBeforeStoringThem(): void
    {
        $ref = new \ReflectionClass(DocumentSignatureService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $m = new \ReflectionMethod($svc, 'decodeBase64Image');
        $png = self::inkPng();
        self::assertStringStartsWith("\x89PNG", (string) $m->invoke($svc, self::url($png)));
        self::assertStringStartsWith("\x89PNG", (string) $m->invoke($svc, base64_encode($png)), 'base64 nu accepté s’il est un vrai PNG');
        self::assertNull($m->invoke($svc, 'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF" . str_repeat('A', 200))));
        self::assertNull($m->invoke($svc, 'data:image/svg+xml;base64,' . base64_encode('<svg onload="x()"/>')));
        self::assertNull($m->invoke($svc, self::url(self::png(120, 60, 6, static fn (): array => [255, 255, 255, 255]))), 'signature blanche refusée');
    }

    public function testDocumentSignatureEndpointUsesTheValidatorAndOnlyTheMembersOwnSavedSignature(): void
    {
        $ctrl = self::read('app/Controllers/Web/DocumentsController.php');
        $action = substr($ctrl, (int) strpos($ctrl, 'public function signature('), 5200);
        self::assertStringContainsString('SignatureImageService::normalize(', $action);
        self::assertStringNotContainsString('strlen($raw) < 100', $action, 'plus de contrôle de taille minimale seul');
        self::assertStringContainsString("'doc_' . \$id . '_u_' . \$userId . '_'", $action, 'nom de fichier choisi par le serveur');
        self::assertStringContainsString('findById($signatureId, $userId, $tenantId)', $ctrl, 'jamais la signature d’un autre membre');
        self::assertStringContainsString("str_starts_with(\$full, \$base . DIRECTORY_SEPARATOR)", $ctrl);
        self::assertStringContainsString('csrfGuard($request)', $action);
        self::assertStringContainsString("(int) Session::get('tenant_id')", $action);
    }

    // ------------------------------------------------------------------ vues

    /** @param array<string, mixed> $sigpad */
    private function renderPartial(array $sigpad): string
    {
        $file = dirname(__DIR__, 2) . '/views/partials/signature_pad.php';
        ob_start();
        (static function (string $__file, array $sigpad): void {
            require $__file;
        })($file, $sigpad);

        return (string) ob_get_clean();
    }

    public function testPartialRendersEveryControlAndKeepsTheNativeFileField(): void
    {
        $html = $this->renderPartial(['id' => 'p1', 'file_name' => 'signature', 'file_attrs' => 'data-bind-img="signature"', 'required' => true, 'theme' => 'dark', 'current_url' => '/uploads/x.png', 'name_hint' => 'Jean Dupont']);
        foreach (['data-sigpad', 'data-sigpad-canvas', 'data-sigpad-undo', 'data-sigpad-clear', 'data-sigpad-type', 'data-sigpad-name', 'data-sigpad-out',
            'name="signature_data_url"', 'type="file"', 'name="signature"', 'data-bind-img="signature"', 'data-required="1"', 'data-background="transparent"',
            'sigpad--dark', 'Annuler le dernier trait', 'Effacer', 'Charger un fichier', 'Taper mon nom', 'value="Jean Dupont"', '/uploads/x.png',
            'data-sigpad-t="empty"', 'data-sigpad-t="required"', 'assets/js/signature-pad.js', 'assets/css/signature-pad.css'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testPartialWithoutFileNameOffersNoFileFieldAndSupportsSeveralInstances(): void
    {
        $a = $this->renderPartial(['name' => 'image_base64', 'background' => 'white', 'ink' => '#112233', 'height' => 200]);
        $b = $this->renderPartial(['name' => 'autre']);
        self::assertStringNotContainsString('type="file"', $a);
        self::assertStringContainsString('name="image_base64"', $a);
        self::assertStringContainsString('data-background="white"', $a);
        self::assertStringContainsString('data-ink="#112233"', $a);
        self::assertStringContainsString('data-height="200"', $a);
        self::assertStringContainsString('assets/js/signature-pad.js', $a);
        self::assertStringNotContainsString('assets/js/signature-pad.js', $b, 'script et feuille de style chargés une seule fois par page');
        preg_match('/id="(sigpad-\d+)"/', $a, $ma);
        preg_match('/id="(sigpad-\d+)"/', $b, $mb);
        self::assertNotSame($ma[1] ?? '', $mb[1] ?? '', 'identifiants uniques');
        $bad = $this->renderPartial(['ink' => 'red;background:url(x)', 'id' => 'x"><script>']);
        self::assertStringContainsString('data-ink="#0b1220"', $bad, 'couleur invalide remplacée');
        self::assertStringNotContainsString('<script>alert', $bad);
        self::assertStringContainsString('id="x&quot;&gt;&lt;script&gt;"', $bad);
    }

    public function testPartialAndScreensAreWiredAndEveryClassUsedIsImported(): void
    {
        $partial = self::read('views/partials/signature_pad.php');
        self::assertSame(0, preg_match('/\\\\App\\\\|new [A-Z]|[A-Z][A-Za-z]+::/', $partial), 'le partial n’appelle aucune classe');
        $editor = self::read('views/atak/sse/papers/workshop_editor.php');
        self::assertStringContainsString("/partials/signature_pad.php", $editor);
        self::assertStringContainsString("'file_name' => 'signature'", $editor);
        self::assertStringContainsString('name="signature_remove"', $editor);
        self::assertStringContainsString('<input id="idw-signature" type="file" name="signature"', $editor, 'envoi de fichier gardé en lecture seule');
        $holder = self::read('views/atak/sse/papers/_holder_images.php');
        self::assertStringContainsString("/partials/signature_pad.php", $holder);
        self::assertStringContainsString("'file_name' => 'signature'", $holder);
        self::assertStringContainsString('name="signature_remove"', $holder);
        self::assertStringContainsString('enctype="multipart/form-data"', $holder);
        $doc = self::read('views/documents/show.php');
        self::assertStringContainsString("views/partials/signature_pad.php", $doc);
        self::assertStringNotContainsString('<canvas id="doc-sign-pad"', $doc);
        self::assertStringContainsString('user_signature_id', $doc);
        self::assertStringContainsString('/partials/signature_pad.php', self::read('views/courrier/signature.php'));
        self::assertStringContainsString('/partials/signature_pad.php', self::read('views/courrier/partials/signature-modal.php'));
        self::assertStringContainsString('signaturepad:change', self::read('public/assets/js/sse-id-workshop.js'));
    }

    public function testRenderedTextsHaveEnglishEntries(): void
    {
        /** @var array<string, string> $dict */
        $dict = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        HtmlTranslator::useDictionary($dict);
        $html = HtmlTranslator::translate($this->renderPartial(['file_name' => 'signature', 'required' => true]));
        foreach (['Undo last stroke', 'Clear', 'Load a file', 'Type my name', 'Use this name', 'Empty signature'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        foreach (['Annuler le dernier trait', 'Charger un fichier', 'Utiliser ce nom', 'Signature vide'] as $french) {
            self::assertStringNotContainsString('>' . $french . '<', $html);
        }
        self::assertStringContainsString('Signature ready', $html);
        self::assertStringContainsString('Sign here with the mouse', $html);
        self::assertStringContainsString('The signature is not a valid PNG image.', HtmlTranslator::translate('<p>La signature n’est pas une image PNG valide.</p>'));
    }

    public function testJavascriptHoldsNoFrenchText(): void
    {
        $js = self::read('public/assets/js/signature-pad.js');
        $code = (string) preg_replace(['~/\*.*?\*/~s', '~(^|[^:\'"])//[^\n]*~'], ['', '$1'], $js);
        preg_match_all("~'((?:[^'\\\n]|\\.)*)'~", $code, $m);
        $literals = $m[1];
        self::assertTrue(count($literals) > 20);
        foreach ($literals as $text) {
            self::assertSame(0, preg_match('/[àâäçéèêëîïôöûùüœÀÉÈÊ]/u', $text), 'accent dans « ' . $text . ' »');
            self::assertSame(0, preg_match('/\b(le|la|les|du|des|votre|vous|dessin|fichier|effacer|signature|annuler|charger|taper)\b/iu', $text), 'mot français dans « ' . $text . ' »');
        }
        self::assertStringContainsString('data-sigpad-t', $js);
        $css = self::read('public/assets/css/signature-pad.css');
        self::assertSame(1, substr_count($css, 'touch-action'), 'touch-action sur le canevas seulement');
        self::assertStringContainsString('touch-action: none', $css);
        self::assertStringContainsString("addEventListener('pointerdown'", $js);
        self::assertStringContainsString('devicePixelRatio', $js);
        self::assertStringContainsString('setPointerCapture', $js);
        self::assertStringContainsString('quadraticCurveTo', $js);
        self::assertStringContainsString('pressure', $js);
    }
}
