<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedNoticePdf;
use App\Services\Sse\SseWantedNoticePdfService;
use App\Services\Sse\SseWantedService;
use App\Support\SseAlertLevel;
use App\Support\SseImageStorage;
use App\Support\SseWantedRealNames;
use App\Support\TrainingCertificatePdfEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfText;
use Tests\Support\SseAlertSqlite;

/**
 * Notices de couleur (rouge, bleue, verte, jaune, orange, noire) : cadre de la couleur sur CHAQUE page, titre en toutes lettres,
 * rubriques numérotées 1 à 8, service à informer et adresse de signalement, aucune mention de jeu ni organisation réelle,
 * aucun portrait de membre, noms longs sans exception, et un avis sans couleur qui garde son gabarit.
 */
final class SseWantedNoticeColorPdfTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('gd')) {
            self::markTestSkipped('pdo_sqlite et gd requis');
        }
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'server' => $_SERVER, 'env' => $_ENV, 'cookie' => $_COOKIE];
        $_ENV['APP_URL'] = 'https://athena.example';
        $_SERVER['HTTP_HOST'] = 'athena.example';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        \App\Core\Csrf::token();
        $this->pdo = SseAlertSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Mathieu Vrainom')");
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'RAVEN-6', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.files.admin']);
        SseMemberImageGate::useForSession(new SseMemberImageGate(null, null, static fn (): array => [], static fn (): bool => false, static function (): void {
        }, static function (): void {
        }, null, null, static fn (): ?int => 7));
        $this->settings([]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        SseAlertSqlite::close();
        SseMemberImageGate::useForSession(null);
        $_SERVER = $this->saved['server'];
        $_ENV = $this->saved['env'];
        $_COOKIE = $this->saved['cookie'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    /** @param array<string, mixed> $extra */
    private function settings(array $extra): void
    {
        (new SseWantedSettingsRepository())->save(1, SseWantedSettingsRepository::normalize($extra + [
            'contact_label' => 'Cellule de coordination des recherches', 'contact_detail' => 'Fréquence 40.500 · indicatif SABRE-ACTUAL', 'default_classification' => 'encadrement',
        ]), 7);
    }

    private static function wantedAccess(): SseWantedAccess
    {
        return new SseWantedAccess(false, true, true, true, 1, 7, 'RAVEN-6', true, true);
    }

    private static function portrait(): string
    {
        $im = imagecreatetruecolor(320, 400);
        imagefilledrectangle($im, 0, 0, 320, 400, (int) imagecolorallocate($im, 96, 84, 66));
        imagefilledellipse($im, 160, 170, 120, 160, (int) imagecolorallocate($im, 150, 152, 150));
        ob_start();
        imagejpeg($im, null, 88);

        return (string) ob_get_clean();
    }

    /**
     * Identité du registre avec profil, liens et, au choix, portrait ; renvoie l'identifiant de l'identité.
     *
     * @param array<string, mixed> $o
     */
    private function identity(array $o = []): int
    {
        $last = (string) ($o['last'] ?? 'Karimzadeh');
        $first = (string) ($o['first'] ?? 'Dariush');
        $cols = ['tenant_id' => 1, 'last_name' => $last, 'first_name' => $first, 'alias' => (string) ($o['alias'] ?? 'Le Chauffeur'), 'sex_apparent' => 'M', 'age_estimated' => 41,
            'birth_date' => '1984-03-12', 'nationality' => 'TAK', 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-12 18:40:00'];
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));
        $row = ['last_name' => $last, 'first_name' => $first, 'kind' => 'civil', 'person_id' => (int) $this->pdo->lastInsertId(), 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK', 'alias' => (string) ($o['alias'] ?? 'Le Chauffeur')];
        if (!empty($o['member'])) {
            $row['user_id'] = (int) $o['member'];
        }
        $iid = (new SseIdentityRepository())->create(1, $row);
        $records = new SseFileRecordRepository();
        $records->saveProfile(1, $iid, ['height_cm' => 178, 'weight_kg' => 82, 'build' => 'athletic', 'hair' => 'black', 'eyes' => 'brown'], 7);
        foreach ((array) ($o['links'] ?? [['vehicle', 'Pick-up gris, plaque KA-4471-TZ'], ['associate', 'Samir Haddad']]) as $link) {
            $records->addLink(1, $iid, ['kind' => $link[0], 'value' => $link[1]] + (isset($link[2]) ? ['linked_identity_id' => $link[2]] : []), 7);
        }
        foreach ((array) ($o['aliases'] ?? []) as $a) {
            $records->addAlias(1, $iid, $a, 'aka', 'known', 0, 7);
        }
        if (($o['photo'] ?? false) === true) {
            $stored = (new SseIdImageService())->storeBytes(self::portrait(), 'photo', 1);
            self::assertTrue($stored['ok'], 'portrait de synthèse refusé');
            $this->files[] = (string) SseImageStorage::absolute($stored['path']);
            $this->pdo->prepare('UPDATE sse_identities SET photo_path = ? WHERE id = ?')->execute([$stored['path'], $iid]);
        }

        return $iid;
    }

    /**
     * Pose un avis et le relit.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function notice(int $identity, array $extra = []): array
    {
        $res = (new SseWantedService())->place(1, $identity, $extra + [
            'notice_type' => 'hvt', 'threat_level' => 'critical', 'armed' => 'yes', 'service_code' => 'SVC-CTF-RAVEN', 'classification' => 'confidentiel',
            'codes_reasons' => ['M-INS-01', 'M-TER-01'], 'codes_measures' => ['A-DNG', 'A-NOI', 'A-QRF'],
            'zone' => 'Province de Tanoa', 'last_seen_place' => 'Marché de Georgetown', 'last_seen_at' => '2026-11-12 18:40',
            'expires_at' => gmdate('Y-m-d H:i', strtotime('+30 days')),
        ], self::wantedAccess());
        self::assertTrue($res['ok'], json_encode($res));

        return (new SseWantedNoticeRepository())->find(1, $res['id']);
    }

    private static function pageCount(string $pdf): int
    {
        return (int) preg_match_all('~/Type\s*/Page\b(?!s)~', $pdf);
    }

    private static function images(string $pdf): int
    {
        return (int) preg_match_all('~/Subtype\s*/Image\b~', $pdf);
    }

    /** Nombre de pages dont le flux de dessin remplit des aplats de cette couleur (le cadre plein). @param array{0: int, 1: int, 2: int} $rgb */
    private static function pagesFilledWith(string $pdf, array $rgb): int
    {
        $needle = sprintf('%.6F %.6F %.6F rg', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
        $n = 0;
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m) > 0) {
            foreach ($m[1] as $stream) {
                $plain = @gzuncompress($stream);
                // Un flux dont le dernier octet compressé est un retour chariot le perd dans l'expression ci-dessus : on le rend.
                $plain = $plain !== false ? $plain : @gzuncompress($stream . chr(13));
                $content = $plain !== false ? $plain : $stream;
                if (preg_match('/\b(?:Tj|TJ)\b/', $content) === 1 && str_contains($content, $needle)) {
                    ++$n;
                }
            }
        }

        return $n;
    }

    /** @return array{bytes: string, text: string, pages: int} */
    private function build(array $notice, string $lang = 'fr', string $faces = 'auto'): array
    {
        $built = (new SseWantedNoticePdfService())->build(1, $notice, $lang, $faces);
        self::assertNotNull($built);

        return ['bytes' => $built['bytes'], 'text' => PdfText::of($built['bytes']), 'pages' => self::pageCount($built['bytes'])];
    }

    /** Rend une notice dans une couleur donnée, quelle que soit celle de son type. @return array{bytes: string, text: string, pages: int} */
    private function buildInColour(array $notice, string $colour, string $lang, string $faces): array
    {
        $data = (new SseWantedNoticePdfService())->data(1, $notice, $lang, $faces);
        self::assertNotNull($data);
        $c = SseAlertLevel::NOTICE_COLORS[$colour];
        $data['notice_color'] = ['key' => $colour, 'title' => $c['title'], 'subtitle' => $data['notice_color']['subtitle'] ?? ['AVIS', 'NOTICE'], 'rgb' => $c['rgb'], 'ink' => $c['ink']];
        $data['faces'] = $faces;
        $bytes = (new SseWantedNoticePdf())->render($data);
        self::assertIsString($bytes);

        return ['bytes' => $bytes, 'text' => PdfText::of($bytes), 'pages' => self::pageCount($bytes)];
    }

    public function testRedBlueAndGreenNoticesFollowTheirTypeAndWearTheirFrameOnEveryPage(): void
    {
        $cases = [
            'hvt' => ['red', 'NOTICE ROUGE', 'RED NOTICE'],
            'poi' => ['blue', 'NOTICE BLEUE', 'BLUE NOTICE'],
            'surveillance' => ['green', 'NOTICE VERTE', 'GREEN NOTICE'],
        ];
        foreach ($cases as $type => [$colour, $titleFr, $titleEn]) {
            $notice = $this->notice($this->identity(['photo' => true, 'last' => 'Sujet' . $type]), ['notice_type' => $type]);
            foreach (['1', '2'] as $faces) {
                $fr = $this->build($notice, 'fr', $faces);
                self::assertStringContainsString($titleFr, $fr['text'], "$type $faces");
                self::assertSame((int) $faces, $fr['pages'], "$type : pages demandées");
                self::assertSame($fr['pages'], SseWantedNoticePdf::$framedPages, "$type $faces : une page encadrée par page");
                self::assertSame($colour, SseWantedNoticePdf::$framedColor);
                self::assertSame($fr['pages'], self::pagesFilledWith($fr['bytes'], SseAlertLevel::NOTICE_COLORS[$colour]['rgb']), "$type $faces : le cadre est dessiné sur chaque page");
                $en = $this->build($notice, 'en', $faces);
                self::assertStringContainsString($titleEn, $en['text']);
                self::assertSame($en['pages'], SseWantedNoticePdf::$framedPages);
                self::assertSame($en['pages'], self::pagesFilledWith($en['bytes'], SseAlertLevel::NOTICE_COLORS[$colour]['rgb']));
            }
        }
    }

    public function testEveryNoticeColourIsFramedOnBothPagesAndNamedInFull(): void
    {
        $notice = $this->notice($this->identity(['photo' => true]));
        foreach (SseAlertLevel::NOTICE_COLORS as $colour => $def) {
            foreach (['fr', 'en'] as $lang) {
                $doc = $this->buildInColour($notice, $colour, $lang, '2');
                self::assertSame(2, $doc['pages'], "$colour $lang");
                self::assertSame(2, SseWantedNoticePdf::$framedPages, "$colour $lang : cadre recto et verso");
                self::assertSame($colour, SseWantedNoticePdf::$framedColor);
                self::assertSame(2, self::pagesFilledWith($doc['bytes'], $def['rgb']), "$colour $lang : aplat de la couleur sur chaque page");
                self::assertStringContainsString($def['title'][$lang === 'en' ? 1 : 0], $doc['text'], "$colour $lang : titre en toutes lettres");
                self::assertGreaterThanOrEqual(4, substr_count($doc['text'], $def['title'][$lang === 'en' ? 1 : 0]), "$colour $lang : titre dans le cadre haut et bas de chaque page");
                self::assertNull(PdfText::fictionMention($doc['bytes']), "$colour $lang");
                self::assertNull(SseWantedRealNames::find($doc['text']), "$colour $lang");
                self::assertStringStartsWith('%PDF-', $doc['bytes']);
            }
        }
    }

    public function testSectionsOneToEightServiceAndReportAddressArePrinted(): void
    {
        $notice = $this->notice($this->identity(['photo' => true]));
        $fr = $this->build($notice, 'fr', '2');
        foreach (['1 IDENTITÉ', '2 SIGNALEMENT', '3 FAITS ET MOTIFS', '4 QUALIFICATION', '5 CONDUITE À TENIR', '6 MISE EN GARDE', '7 DERNIER LIEU CONNU', '8 VALIDITÉ ET SERVICE À INFORMER'] as $section) {
            self::assertStringContainsString($section, $fr['text']);
        }
        $en = $this->build($notice, 'en', '2');
        foreach (['1 IDENTITY', '2 DESCRIPTION', '3 FACTS AND GROUNDS', '4 CATEGORY', '5 ACTION TO TAKE', '6 CAUTION', '7 LAST KNOWN LOCATION', '8 VALIDITY AND SERVICE TO REPORT TO'] as $section) {
            self::assertStringContainsString($section, $en['text']);
        }
        foreach ([$fr, $en] as $doc) {
            foreach (['M-INS-01', 'M-TER-01', 'A-DNG', 'A-NOI', 'A-QRF', 'SECRET//NOFORN'] as $needle) {
                self::assertStringContainsString($needle, $doc['text'], $needle);
            }
            self::assertStringContainsString('Task Force RAVEN', $doc['text'], 'service à informer');
            self::assertStringContainsString('hvt@raven.invalid', $doc['text'], 'canal fictif du service');
            self::assertStringContainsString('athena.example', $doc['text']);
            self::assertStringContainsString('/atak/sse/signalement/', $doc['text'], 'adresse de signalement imprimée');
            self::assertGreaterThanOrEqual(4, substr_count($doc['text'], 'SECRET//NOFORN'), 'bandeaux de classification haut et bas de chaque page');
        }
        $token = (string) $this->pdo->query('SELECT token FROM sse_alert_links ORDER BY id DESC LIMIT 1')->fetchColumn();
        self::assertTrue(\App\Repositories\SseAlertRepository::validToken($token));
        self::assertStringContainsString($token, preg_replace('/\s+/', '', $fr['text']) ?? '', 'jeton du lien enregistré');
    }

    public function testNoNoticeCarriesAFictionMentionOrAMemberPortrait(): void
    {
        $other = $this->notice($this->identity(['photo' => true, 'last' => 'Marlowe']), ['notice_type' => 'poi']);
        self::assertGreaterThanOrEqual(1, self::images($this->build($other)['bytes']), 'un sujet qui n’est pas membre garde son portrait');

        foreach (['hvt', 'poi', 'surveillance'] as $type) {
            $member = $this->notice($this->identity(['photo' => true, 'member' => 10, 'last' => 'Delacroix' . $type, 'first' => 'Mathieu', 'links' => []]), ['notice_type' => $type]);
            foreach (['fr', 'en'] as $lang) {
                $doc = $this->build($member, $lang, '2');
                self::assertSame(0, self::images($doc['bytes']), "$type $lang : aucune image de membre sans autorisation");
                self::assertStringNotContainsString('Vrainom', $doc['text'], 'nom du compte');
                self::assertStringNotContainsString('76561198000000010', $doc['text'], 'identifiant Steam');
                self::assertNull(PdfText::fictionMention($doc['bytes']), "$type $lang");
            }
        }
    }

    public function testAnAssociateLinkedToAMemberIdentityIsNeverNamed(): void
    {
        $member = $this->identity(['member' => 10, 'last' => 'Delacroix', 'first' => 'Mathieu', 'links' => []]);
        $subject = $this->identity(['links' => [['associate', 'Samir Haddad'], ['associate', 'Yves Membrecache', $member]]]);
        $doc = $this->build($this->notice($subject), 'fr', '2');
        self::assertStringContainsString('Samir Haddad', $doc['text']);
        self::assertStringNotContainsString('Membrecache', $doc['text']);
    }

    public function testLongNamesAndTextsRenderOnEveryColourWithoutExceptionAndKeepTheFrame(): void
    {
        $identity = $this->identity([
            'last' => 'Karimzadeh-Vasileiou-Papadopoulos de la Fontaine', 'first' => 'Élodie-Marguerite Alexandrina', 'alias' => 'La Chauffeuse du réseau du Nord-Est',
            'links' => [['vehicle', 'Camionnette frigorifique blanche à bandes bleues, plaque KA-4471-TZ, rétroviseur droit cassé'], ['vehicle', 'Moto tout-terrain verte, sans plaque'],
                ['associate', 'Aleksandr Konstantinopoulos-Wojciechowski'], ['associate', 'Samir Haddad']],
            'aliases' => ['Abou Karim al-Tanoui', 'Madame K.', 'Vasilia', 'La Dame du port', 'Irina V.'], 'photo' => true,
        ]);
        $reason = str_repeat('Impliquée dans la préparation d’attaques par véhicules piégés contre les convois de ravitaillement. ', 8);
        $notice = $this->notice($identity, [
            'notice_type' => 'capture_or_kill', 'service_code' => 'SVC-SOTF-ID', 'reason' => mb_substr($reason, 0, 1200),
            'codes_reasons' => ['M-TER-05', 'M-TRF-05', 'M-INS-04', 'M-TER-02', 'M-ESP-02', 'M-FIN-04'], 'codes_measures' => ['A-DNG', 'A-NEU', 'A-NOI', 'A-QRF', 'A-IED', 'A-BOD', 'A-CHK'],
            'zone' => 'Province de Tanoa, frontière nord, secteurs Alpha à Delta',
            'last_seen_place' => 'Entrepôt frigorifique désaffecté du quartier portuaire de Georgetown, accès par la ruelle derrière la station-service',
        ]);
        foreach (array_keys(SseAlertLevel::NOTICE_COLORS) as $colour) {
            foreach (['fr', 'en'] as $lang) {
                $doc = $this->buildInColour($notice, $colour, $lang, 'auto');
                self::assertGreaterThanOrEqual(1, $doc['pages']);
                self::assertLessThanOrEqual(2, $doc['pages'], "$colour $lang : recto et verso au plus");
                self::assertSame($doc['pages'], SseWantedNoticePdf::$framedPages, "$colour $lang : cadre sur chaque page");
                self::assertSame($doc['pages'], self::pagesFilledWith($doc['bytes'], SseAlertLevel::NOTICE_COLORS[$colour]['rgb']));
                self::assertStringContainsString('PAPADOPOULOS', mb_strtoupper($doc['text'], 'UTF-8'));
                self::assertNull(PdfText::fictionMention($doc['bytes']), "$colour $lang");
            }
        }
    }

    public function testTheAddressOfACustomisedServiceIsPrintedInTheReportBlock(): void
    {
        $services = \App\Support\SseWantedServices::normalizeSettings(['overrides' => ['SVC-CTF-RAVEN' => ['address' => "Base avancée Horizon
Poste de commandement nord", 'city' => 'Georgetown', 'fr' => 'Cellule Horizon de recherche']]]);
        $this->settings(['banner' => 'red', 'services' => $services]);
        $notice = $this->notice($this->identity(['photo' => true]));
        $doc = $this->build($notice, 'fr', '2');
        self::assertStringContainsString('Base avancée Horizon', $doc['text']);
        self::assertStringContainsString('Cellule Horizon de recherche', $doc['text']);
        self::assertNull(PdfText::fictionMention($doc['bytes']));
    }

    public function testANoticeWithoutColourKeepsItsTemplateAndHasNoFrame(): void
    {
        $this->settings(['banner' => 'wanted']);
        $notice = $this->notice($this->identity(['photo' => true]));
        $data = (new SseWantedNoticePdfService())->data(1, $notice, 'fr');
        self::assertNotNull($data);
        self::assertNull($data['notice_color'], 'avis classique : pas de couleur de notice');
        $doc = $this->build($notice, 'fr', '2');
        self::assertSame(0, SseWantedNoticePdf::$framedPages, 'aucun cadre');
        self::assertSame('', SseWantedNoticePdf::$framedColor);
        self::assertSame(0, self::pagesFilledWith($doc['bytes'], SseAlertLevel::NOTICE_COLORS['red']['rgb']));
        self::assertStringNotContainsString('NOTICE ROUGE', $doc['text']);
        self::assertStringContainsString('M-INS-01', $doc['text'], 'codes et mesures restent sur le gabarit d’origine');
        self::assertNull(PdfText::fictionMention($doc['bytes']));
        self::assertSame(2, $doc['pages']);
        // Retour à la notice : le compteur du dernier rendu repart de zéro.
        $this->settings(['banner' => 'red']);
        $again = $this->build($this->notice($this->identity(['last' => 'Autre'])), 'fr', '1');
        self::assertSame(1, SseWantedNoticePdf::$framedPages);
        self::assertStringContainsString('NOTICE ROUGE', $again['text']);
    }
}
