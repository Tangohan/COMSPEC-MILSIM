<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedAttachments;
use App\Services\Sse\SseWantedNoticePdf;
use App\Services\Sse\SseWantedNoticePdfService;
use App\Services\Sse\SseWantedService;
use App\Support\SseUsClassification;
use App\Support\SseWantedServices;
use App\Support\TrainingCertificatePdfEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseWantedSqlite;

/**
 * Notice PDF d'un avis : motifs et mesures codifiés (code et libellé), signalement, service demandeur, bandeaux de classification
 * américains, image principale et miniatures des pièces, et aucune image d'identité de membre sans autorisation hiérarchique.
 */
final class SseWantedNoticePdfContentTest extends TestCase
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
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'server' => $_SERVER];
        \App\Core\Csrf::token();
        $this->pdo = SseWantedSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        Gate::reset();
        SseMemberImageGate::useForSession(null);
        $this->private = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wanted-pdf-' . bin2hex(random_bytes(6));
        mkdir($this->private, 0775, true);
    }

    protected function tearDown(): void
    {
        SseWantedSqlite::close();
        SseMemberImageGate::useForSession(null);
        foreach (glob($this->private . '/*/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->private . '/*/*') ?: [] as $f) {
            is_dir($f) ? @rmdir($f) : @unlink($f);
        }
        foreach (glob($this->private . '/*') ?: [] as $f) {
            is_dir($f) ? @rmdir($f) : @unlink($f);
        }
        @rmdir($this->private);
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    private static function manager(): SseWantedAccess
    {
        return new SseWantedAccess(false, true, true, true, 1, 7, 'ANALYSTE');
    }

    private function gate(int $viewer): SseMemberImageGate
    {
        return new SseMemberImageGate(
            null,
            null,
            static fn (int $t, int $owner): array => $owner === self::OWNER ? [self::CHEF] : [],
            static fn (int $t, int $v): bool => false,
            static function (array $row): void {
            },
            static function (): void {
            },
            static fn (): string => '2026-11-15 12:00:00',
            null,
            static fn (): ?int => $viewer > 0 ? $viewer : null
        );
    }

    private function attachments(int $viewer): SseWantedAttachments
    {
        $gate = $this->gate($viewer);
        SseMemberImageGate::useForSession($gate);

        return new SseWantedAttachments(null, null, $gate, null, null, null, $this->private);
    }

    private static function image(int $seed): string
    {
        $im = imagecreatetruecolor(240, 300);
        imagefilledrectangle($im, 0, 0, 240, 300, (int) imagecolorallocate($im, 30 + $seed * 40, 90, 140 - $seed * 20));
        imagefilledellipse($im, 120, 150, 80 + $seed * 12, 110, (int) imagecolorallocate($im, 220, 200 - $seed * 30, 120));
        ob_start();
        imagejpeg($im, null, 88);

        return (string) ob_get_clean();
    }

    private function tmp(string $bytes): string
    {
        $f = tempnam(sys_get_temp_dir(), 'wpdf');
        file_put_contents($f, $bytes);

        return $f;
    }

    /** @return array<string, mixed> */
    private function notice(?int $userId, array $extra = []): array
    {
        $id = (new SseIdentityRepository())->create(1, ['last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK', 'user_id' => $userId]);
        $res = (new SseWantedService())->place(1, $id, $extra + [
            'notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'yes', 'reason' => 'Participation présumée à une attaque coordonnée.',
            'codes_reasons' => ['M-TER-01', 'M-INS-01'], 'codes_measures' => ['A-DNG', 'A-NOI'],
            'physical' => ['height_cm' => '181', 'build' => 'athletic', 'hair' => 'brown', 'tattoos' => 'Aigle sur l’épaule droite'],
        ], self::manager());
        self::assertTrue($res['ok'], json_encode($res));

        return (new SseWantedNoticeRepository())->find(1, $res['id']);
    }

    private static function utf16(string $s): string
    {
        return mb_convert_encoding($s, 'UTF-16BE', 'UTF-8');
    }

    private static function imageObjects(string $pdf): int
    {
        return (int) preg_match_all('~/Subtype\s*/Image\b~', $pdf);
    }

    public function testDataCarriesCodesPhysicalServiceAndClassificationInTheRequestedLanguage(): void
    {
        $code = (string) array_key_first(SseWantedServices::catalogue());
        $notice = $this->notice(null, ['service_code' => $code, 'classification' => 'confidentiel']);
        $svc = new SseWantedNoticePdfService(null, null, null, null, null, $this->attachments(self::PEER));
        $fr = $svc->data(1, $notice, 'fr');
        $en = $svc->data(1, $notice, 'en');
        self::assertNotNull($fr);
        self::assertNotNull($en);
        self::assertSame(['M-TER-01', 'M-INS-01'], array_column($fr['reasons'], 'code'));
        self::assertSame(['A-DNG', 'A-NOI'], array_column($fr['measures'], 'code'));
        self::assertSame('Planification d’attaque', $fr['reasons'][0]['label']);
        self::assertSame('Attack planning', $en['reasons'][0]['label']);
        self::assertNotSame('', $fr['physical_line']);
        self::assertNotSame([], $fr['physical_rows']);
        self::assertSame('SECRET//NOFORN', $fr['classification']['banner']);
        self::assertSame(SseUsClassification::rgb('confidentiel'), $fr['classification']['rgb']);
        self::assertNotSame('', (string) $fr['service']['name'], 'service demandeur nommé');

        $facts = SseWantedNoticePdf::factsText($fr);
        self::assertStringContainsString('M-TER-01 Planification d’attaque', $facts);
        self::assertStringContainsString('Participation présumée', $facts, 'le texte libre reste');
        self::assertStringContainsString('Grounds : M-TER-01 Attack planning', SseWantedNoticePdf::factsText($en));
        self::assertStringContainsString('A-DNG', SseWantedNoticePdf::actionText($fr));
    }

    public function testPdfWearsTheClassificationBannerAndTheFictionMentionAndHasTwoPagesWithPieces(): void
    {
        $notice = $this->notice(null, ['classification' => 'tres_restreint']);
        $att = $this->attachments(self::PEER);
        foreach ([1, 2, 3, 4] as $i) {
            $res = $att->addFile(1, $notice, $this->tmp(self::image($i)), ['kind' => $i === 1 ? 'portrait' : 'vehicle', 'caption' => 'Pièce ' . $i], self::manager());
            self::assertTrue($res['ok'], json_encode($res));
        }
        $svc = new SseWantedNoticePdfService(null, null, null, null, null, $att);
        $data = $svc->data(1, $notice, 'fr');
        self::assertNotNull($data);
        self::assertNotNull($data['attached']['main'], 'image principale');
        self::assertCount(3, $data['attached']['thumbs'], '3 miniatures au plus');
        self::assertSame($data['attached']['main'], $data['photo'], 'sans photo d’identité, la pièce principale sert de portrait');
        self::assertTrue(SseWantedNoticePdf::needsVerso($data));

        $built = $svc->build(1, $notice, 'fr');
        self::assertNotNull($built);
        $pdf = $built['bytes'];
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThanOrEqual(4, self::imageObjects($pdf), 'portrait et 3 miniatures');
        self::assertNotFalse(strpos($pdf, self::utf16('TOP SECRET//SCI//NOFORN')), 'bandeau de classification');
        self::assertNotFalse(strpos($pdf, self::utf16('Document de fiction — jeu de rôle Arma 3')));
        self::assertSame(2, (int) preg_match_all('~/Type\s*/Page\b(?!s)~', $pdf));
    }

    public function testAMemberIdentityNeverGetsItsImagesInThePdfWithoutTheHierarchicalAuthorisation(): void
    {
        $notice = $this->notice(self::OWNER);
        // Le chef pose des pièces ; un pair sans autorisation génère ensuite l'avis.
        $boss = $this->attachments(self::CHEF);
        foreach ([1, 2] as $i) {
            $res = $boss->addFile(1, $notice, $this->tmp(self::image($i)), ['kind' => 'portrait', 'caption' => 'Membre ' . $i], self::manager());
            self::assertTrue($res['ok'], json_encode($res));
        }
        $peer = new SseWantedNoticePdfService(null, null, null, null, null, $this->attachments(self::PEER));
        $data = $peer->data(1, $notice, 'fr');
        self::assertNotNull($data);
        self::assertNull($data['attached']['main']);
        self::assertSame([], $data['attached']['thumbs']);
        self::assertNull($data['photo']);
        $built = $peer->build(1, $notice, 'fr');
        self::assertNotNull($built);
        self::assertSame(0, self::imageObjects($built['bytes']), 'aucune image de membre dans le PDF');
        // Codes et signalement (fictifs) restent présents.
        self::assertSame(['M-TER-01', 'M-INS-01'], array_column($data['reasons'], 'code'));
    }

    public function testNoRealOrganisationIsNamedAndTheTemplateStillRendersWithoutAnyNewData(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Sse/SseWantedNoticePdf.php');
        foreach (['interpol', 'europol', 'fbi', 'gendarmerie', 'scotland yard'] as $word) {
            self::assertStringNotContainsStringIgnoringCase($word, $source);
        }
        // Données anciennes (sans les nouveaux champs) : le gabarit rend toujours.
        $old = [
            'lang' => 'fr', 'banner' => ['NOTICE ROUGE', 'RED NOTICE'], 'authority' => ['Bureau fictif', 'Fictional bureau'], 'ref' => 'A-0001/2026',
            'issued_at' => '2026-11-14 10:00:00', 'expires_at' => null, 'last_name' => 'Test', 'first_name' => 'Un', 'sex' => 'M', 'birth_date' => null,
            'birth_place' => null, 'nationality' => '', 'height_cm' => null, 'weight_kg' => null, 'eyes' => '', 'hair' => '', 'marks' => '', 'aliases' => [],
            'languages' => '', 'type_label' => 'Recherche', 'threat_level' => 'low', 'threat_label' => 'Faible', 'armed' => 'no', 'reason' => 'Motif.',
            'instructions' => 'Signaler.', 'zone' => '', 'last_seen_place' => '', 'last_seen_at' => null, 'contact_label' => '', 'contact_detail' => '',
            'photo' => null, 'extra_photos' => [], 'signature' => null, 'emblem_path' => null, 'watermark' => false, 'footer' => true, 'faces' => 'auto',
            'generated_at' => '15/11/2026 10:00 UTC',
        ];
        $pdf = (new SseWantedNoticePdf())->render($old);
        self::assertIsString($pdf);
        self::assertStringStartsWith('%PDF-', $pdf);
    }
}
