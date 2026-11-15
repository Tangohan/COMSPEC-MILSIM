<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseFileRepository;
use App\Services\Media\SignatureImageService;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SseSignatureStrokes;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFilesSqlite;

/**
 * Touche SIGN du SEEK II : ouvre le pad de signature, envoie les traits avec la fiche seek_record, le site les valide
 * (SseSignatureStrokes), range le PNG et le rend avec la fiche. Câblage du jeu relu dans les sources, fiche et rangement
 * vérifiés de bout en bout sur la base de test.
 */
final class SeekSignKeyTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const CONNECT = self::ROOT . 'mod/UptoDate/Sources/comspec-overwatch-addons/connect/';

    private ?PDO $pdo = null;

    private string $dir = '';

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            SseFilesSqlite::close();
            $this->pdo = null;
        }
        if ($this->dir !== '' && is_dir($this->dir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($this->dir);
        }
    }

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents($path));
    }

    /** @return array<string, mixed> */
    private static function signature(): array
    {
        $pts = [];
        for ($i = 0; $i < 40; $i++) {
            $pts[] = [$i * 20, 500 + (int) (300 * sin($i / 4))];
        }

        return ['strokes' => [$pts, [[100, 100], [300, 800], [500, 200]]], 'aspect' => 2.5];
    }

    public function testSignKeyOpensThePadThenSendsTheStrokesWithTheRecord(): void
    {
        $send = self::read(self::CONNECT . 'functions/fn_seekSend.sqf');
        // Sans tracé validé, SIGN ouvre le pad au lieu d'envoyer ; la ligne d'état du pad parle (pas de toast).
        self::assertStringContainsString('COMSPEC_SsePerson_SignStrokes', $send);
        self::assertStringContainsString('["open", [true]] call comspec_overwatch_connect_fnc_seekSignPad', $send);
        self::assertStringContainsString('["signature", createHashMapFromArray [["strokes", _strokesSaved select 0], ["aspect", _strokesSaved select 1]]]', $send);
        self::assertStringNotContainsString('systemChat', $send);

        $pad = self::read(self::CONNECT . 'functions/fn_seekSignPad.sqf');
        self::assertStringContainsString('_st set ["sendAfter", _args param [0, false]]', $pad);
        self::assertStringContainsString('["send"] call comspec_overwatch_connect_fnc_seekSend', $pad);
        self::assertStringNotContainsString('systemChat', $pad);
        self::assertStringNotContainsString('hintSilent', $pad);

        // Les trois entrées SIGN (bouton du contrôle rapide, touche logicielle, touche du châssis) passent par seekSend.
        $hpp = self::read(self::CONNECT . 'display_sse_person.hpp');
        self::assertSame(3, substr_count($hpp, "action = \"['send'] call comspec_overwatch_connect_fnc_seekSend;\";"));
        self::assertStringContainsString("['open'] call comspec_overwatch_connect_fnc_seekSignPad", $hpp);
        self::assertStringContainsString('class KeySign: KeyA1', $hpp);
        self::assertStringContainsString('case (_key in [0x3E, 0x1F]): { { ["send"] call comspec_overwatch_connect_fnc_seekSend } };', self::read(self::CONNECT . 'functions/fn_sseKeyBlock.sqf'));
        self::assertStringContainsString('class seekSignPad {};', self::read(self::CONNECT . 'config.cpp'));
    }

    public function testSiteValidatesRendersAndShowsTheSeekSignature(): void
    {
        $svc = self::read(self::ROOT . 'app/Services/Sse/SseFileQueryService.php');
        self::assertStringContainsString("SseSignatureStrokes::store(\$body['signature'], \$tenantId)", $svc);
        self::assertStringContainsString('setSeekSignatureImage(', $svc);
        self::assertStringContainsString('signature_image', self::read(self::ROOT . 'views/atak/sse/files/seek.php'));
        self::assertStringContainsString('signature_image', self::read(self::ROOT . 'views/atak/sse/papers/_seek_images.php'));
        self::assertStringContainsString('sse_seek_records', self::read(self::ROOT . 'database/steps/4595_sse_persons_signature_image.php'));
        self::assertStringContainsString('sse_seek_records DROP COLUMN signature_image', self::read(self::ROOT . 'migrations/rollback/sse_persons_signature_image_down.sql'));
    }

    public function testSeekRecordKeepsTheDrawnSignatureOnTheRecord(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseFilesSqlite::open();
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'seeksign_' . bin2hex(random_bytes(4));
        mkdir($this->dir);

        $files = new SseFileRepository();
        $stored = $files->upsertSeek(1, [
            'rec' => 'REC-20261115-0001', 'idempotency_key' => 'SEEK-SIGN-1', 'verdict' => 'concordant', 'subject_unknown' => 0, 'enrolled' => 0,
            'mod_fingers' => 1, 'mod_iris' => 0, 'mod_face' => 0, 'controller_callsign' => 'VIPER 1-1',
        ]);
        $res = SseSignatureStrokes::store(self::signature(), 1, new SignatureImageService(new SseIdImageService($this->dir, false)));
        self::assertTrue($res['ok'], (string) ($res['error'] ?? ''));
        self::assertFileExists($this->dir . '/' . $res['path']);

        self::assertTrue($files->setSeekSignatureImage($stored['id'], 1, (string) $res['path']));
        $row = $files->findSeek($stored['id'], 1);
        self::assertIsArray($row);
        self::assertSame($res['path'], SseFileRepository::hydrateSeek($row)['signature_image']);
        // Autre communauté : fiche introuvable ; chemin hors dossier ou autre extension : refusé.
        self::assertNull($files->findSeek($stored['id'], 2));
        self::assertFalse($files->setSeekSignatureImage($stored['id'], 1, '../../etc/passwd'));
        self::assertFalse($files->setSeekSignatureImage($stored['id'], 1, 'uploads/sse-id-signatures/x.php'));
    }

    public function testRefusedStrokesNeverStoreAnything(): void
    {
        $svc = new SignatureImageService(new SseIdImageService(sys_get_temp_dir(), false));
        self::assertFalse(SseSignatureStrokes::store(['strokes' => [[[5000, 1], [2, 2]]]], 1, $svc)['ok']);
        self::assertFalse(SseSignatureStrokes::store(['strokes' => 'x'], 1, $svc)['ok']);
    }
}
