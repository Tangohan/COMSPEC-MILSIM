<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseIdImageService;
use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseSheetFactsService;
use App\Services\Sse\SseSheetPdf;
use App\Services\Sse\SseSheetService;
use App\Services\Sse\SseWantedAccess;
use App\Support\SseAlertLevel;
use App\Support\SseAlertReportTo;
use App\Support\SseImageStorage;
use App\Support\SseWantedRealNames;
use App\Support\TrainingCertificatePdfEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfText;
use Tests\Support\SseAlertSqlite;

/**
 * PDF de la fiche de vigilance (document d'avertissement), émis par le vrai service sur une base sqlite : bloc d'alerte du niveau
 * (libellé, rang n/4), consignes codifiées, motifs, bandeaux de classification, service à informer, adresse de signalement imprimée,
 * aucune mention de jeu, noms longs, sujet membre sans photo, autres modèles de fiche intacts.
 */
final class SseSheetVigilancePdfTest extends TestCase
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
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.files.admin', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        // Aucune dérogation hiérarchique : l'image d'un membre reste refusée.
        SseMemberImageGate::useForSession(new SseMemberImageGate(null, null, static fn (): array => [], static fn (): bool => false, static function (): void {
        }, static function (): void {
        }, null, null, static fn (): ?int => 7));
        (new SseWantedSettingsRepository())->save(1, SseWantedSettingsRepository::normalize([
            'contact_label' => 'Cellule de coordination des recherches', 'contact_detail' => 'Fréquence 40.500 · indicatif SABRE-ACTUAL', 'default_classification' => 'encadrement',
        ]), 7);
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

    private static function access(): SseSheetAccess
    {
        return new SseSheetAccess(new SseWantedAccess(false, true, true, true, 1, 7, 'RAVEN-6', true, true), static fn (string $s, string $l): bool => true, true, 'confidentiel');
    }

    private static function portrait(): string
    {
        $im = imagecreatetruecolor(320, 400);
        imagefilledrectangle($im, 0, 0, 320, 400, (int) imagecolorallocate($im, 70, 86, 104));
        imagefilledellipse($im, 160, 170, 120, 160, (int) imagecolorallocate($im, 150, 152, 150));
        ob_start();
        imagejpeg($im, null, 88);

        return (string) ob_get_clean();
    }

    /**
     * Une personne et son identité du registre avec profil, liens et, au choix, un portrait.
     *
     * @param array<string, mixed> $o last, first, alias, photo (bool), member (int), links, place, grid, marks
     * @return array<string, mixed> sujet de fiche
     */
    private function subject(array $o = []): array
    {
        $last = (string) ($o['last'] ?? 'Karimzadeh');
        $first = (string) ($o['first'] ?? 'Dariush');
        $cols = ['tenant_id' => 1, 'last_name' => $last, 'first_name' => $first, 'alias' => (string) ($o['alias'] ?? 'Le Chauffeur'), 'sex_apparent' => 'M', 'age_estimated' => 41,
            'birth_date' => '1984-03-12', 'birth_place' => 'Kavala', 'nationality' => 'TAK', 'affiliation' => 'Réseau du Nord-Est',
            'distinguishing_marks' => (string) ($o['marks'] ?? 'Cicatrice à la lèvre supérieure'), 'grid_reference' => (string) ($o['grid'] ?? '045 123'),
            'location_description' => (string) ($o['place'] ?? 'Marché de Georgetown, côté nord'), 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-12 18:40:00'];
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));
        $pid = (int) $this->pdo->lastInsertId();
        $row = ['last_name' => $last, 'first_name' => $first, 'kind' => 'civil', 'person_id' => $pid, 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK', 'alias' => (string) ($o['alias'] ?? 'Le Chauffeur')];
        if (!empty($o['member'])) {
            $row['user_id'] = (int) $o['member'];
        }
        $iid = (new SseIdentityRepository())->create(1, $row);
        $records = new SseFileRecordRepository();
        $records->saveProfile(1, $iid, ['height_cm' => 178, 'weight_kg' => 82, 'build' => 'athletic', 'hair' => 'black', 'eyes' => 'brown', 'marks' => $cols['distinguishing_marks']], 7);
        foreach ((array) ($o['links'] ?? [['vehicle', 'Pick-up gris, plaque KA-4471-TZ'], ['associate', 'Samir Haddad'], ['address', '14 rue du Port, Georgetown']]) as [$kind, $value]) {
            $records->addLink(1, $iid, ['kind' => $kind, 'value' => $value], 7);
        }
        if (($o['photo'] ?? false) === true) {
            $stored = (new SseIdImageService())->storeBytes(self::portrait(), 'photo', 1);
            self::assertTrue($stored['ok'], 'portrait de synthèse refusé');
            $this->files[] = (string) SseImageStorage::absolute($stored['path']);
            $this->pdo->prepare('UPDATE sse_identities SET photo_path = ? WHERE id = ?')->execute([$stored['path'], $iid]);
        }
        $subject = (new SseSheetFactsService())->subject(1, 'person', $pid, self::access());
        self::assertNotNull($subject);

        return $subject;
    }

    /**
     * Émet la fiche de vigilance par le vrai service.
     *
     * @param array<string, mixed> $input
     * @return array{bytes: string, ref: string, text: string, id: int, pages: int}
     */
    private function issue(array $subject, array $input = []): array
    {
        $res = (new SseSheetService())->issue(1, $subject, 'vigilance', $input + ['lang' => 'fr', 'avec_notice' => '0', 'classification' => 'confidentiel', 'faces' => 'auto', 'validity' => 14, 'reasons' => ['M-INS-01'], 'measures' => ['A-NOI', 'A-QRF', 'A-REP']], self::access());
        self::assertTrue($res['ok'], implode(' | ', $res['errors']));
        self::assertStringStartsWith('%PDF-', (string) $res['bytes']);

        return ['bytes' => (string) $res['bytes'], 'ref' => (string) $res['ref'], 'text' => PdfText::of((string) $res['bytes']), 'id' => (int) $res['id'], 'pages' => (int) $res['pages']];
    }

    private static function images(string $pdf): int
    {
        return (int) preg_match_all('~/Subtype\s*/Image\b~', $pdf);
    }

    private static function squash(string $text): string
    {
        return mb_strtoupper((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $text), 'UTF-8');
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: int}> menace, armement, niveau, rang */
    private static function levels(): array
    {
        return [
            'watch' => ['low', 'no', 'watch', 1],
            'reinforced' => ['medium', 'unknown', 'reinforced', 2],
            'caution' => ['high', 'unknown', 'caution', 3],
            'danger' => ['critical', 'yes', 'danger', 4],
        ];
    }

    public function testEachLevelPrintsItsTitleRankCodedMeasuresReasonBannersServiceAndReportAddress(): void
    {
        $subject = $this->subject(['photo' => true]);
        foreach (self::levels() as $name => [$threat, $armed, $key, $rank]) {
            $doc = $this->issue($subject, ['threat' => $threat, 'armed' => $armed, 'reasons' => ['M-INS-01', 'M-TRF-01'], 'measures' => ['A-NOI', 'A-QRF', 'A-CIV']]);
            $text = $doc['text'];
            $squash = self::squash($text);
            self::assertStringContainsString(self::squash(SseAlertLevel::LEVELS[$key]['title'][0]), $squash, $name . ' : titre du niveau');
            self::assertStringContainsString($rank . '/4', $text, $name . ' : rang n/4');
            self::assertStringNotContainsString(($rank === 4 ? '3' : '4') . '/4 ' . SseAlertLevel::LEVELS[$key]['title'][0], $text);
            foreach (['A-NOI', 'A-QRF', 'A-CIV'] as $code) {
                self::assertStringContainsString($code, $text, $name . ' consigne ' . $code);
            }
            foreach (['M-INS-01', 'M-TRF-01'] as $code) {
                self::assertStringContainsString($code, $text, $name . ' motif ' . $code);
            }
            self::assertGreaterThanOrEqual(2, substr_count($text, 'SECRET//NOFORN'), $name . ' : bandeaux de classification haut et bas');
            self::assertStringContainsString('KARIMZADEH', mb_strtoupper($text, 'UTF-8'), $name . ' : sujet');
            self::assertStringContainsString('Cellule de coordination des recherches', $text, $name . ' : service à informer');
            self::assertStringContainsString('SABRE', $text, $name . ' : canal du service');
            self::assertStringContainsString($doc['ref'], $text, $name . ' : référence à citer');
            self::assertSame(1, $doc['pages'], $name . ' : une page');
        }
    }

    public function testTheReportAddressIsPrintedWithItsOpaqueTokenAndMatchesTheStoredLink(): void
    {
        $subject = $this->subject();
        $first = $this->issue($subject);
        $second = $this->issue($subject);
        $tokens = $this->pdo->query('SELECT token FROM sse_alert_links ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(2, $tokens);
        self::assertNotSame($tokens[0], $tokens[1], 'un jeton par fiche');
        foreach ([[$first, (string) $tokens[0]], [$second, (string) $tokens[1]]] as [$doc, $token]) {
            self::assertTrue(\App\Repositories\SseAlertRepository::validToken($token));
            self::assertStringContainsString('athena.example', $doc['text'], 'domaine de l’adresse de signalement');
            self::assertStringContainsString(SseAlertReportTo::path(''), $doc['text'], 'chemin de signalement');
            self::assertStringContainsString($token, preg_replace('/\s+/', '', $doc['text']) ?? '', 'jeton imprimé en clair');
        }
        // Le jeton d'une fiche ne figure pas sur l'autre.
        self::assertStringNotContainsString((string) $tokens[1], preg_replace('/\s+/', '', $first['text']) ?? '');
        // Le QR code est dessiné en vectoriel : une fiche sans photo ne contient aucune image.
        self::assertSame(0, self::images($first['bytes']));
    }

    public function testEnglishSheetUsesEnglishTitlesAndLabels(): void
    {
        $subject = $this->subject();
        foreach (self::levels() as $name => [$threat, $armed, $key, $rank]) {
            $doc = $this->issue($subject, ['lang' => 'en', 'threat' => $threat, 'armed' => $armed]);
            $text = $doc['text'];
            self::assertStringContainsString(self::squash(SseAlertLevel::LEVELS[$key]['title'][1]), self::squash($text), $name . ' : titre anglais');
            self::assertStringContainsString($rank . '/4', $text);
            self::assertStringContainsString('LEVEL', $text);
            self::assertStringContainsStringIgnoringCase('Action to take', $text);
            self::assertStringContainsStringIgnoringCase('Valid until', $text);
            self::assertStringContainsStringIgnoringCase('Issued', $text);
            self::assertStringContainsString('A-NOI', $text);
        }
    }

    public function testNoVariantCarriesAFictionMentionOrNamesARealOrganisation(): void
    {
        $subject = $this->subject(['photo' => true]);
        foreach (['fr', 'en'] as $lang) {
            foreach (self::levels() as $name => [$threat, $armed]) {
                foreach (['1', '2'] as $faces) {
                    $doc = $this->issue($subject, ['lang' => $lang, 'threat' => $threat, 'armed' => $armed, 'faces' => $faces]);
                    self::assertNull(PdfText::fictionMention($doc['bytes']), "$lang $name $faces : mention de jeu");
                    self::assertNull(SseWantedRealNames::find($doc['text']), "$lang $name $faces : organisation réelle");
                    self::assertStringNotContainsStringIgnoringCase('spécimen', $doc['text']);
                    self::assertStringNotContainsStringIgnoringCase('specimen', $doc['text']);
                    self::assertStringNotContainsStringIgnoringCase('simulation', $doc['text']);
                    self::assertStringNotContainsStringIgnoringCase('fiction', $doc['text']);
                    self::assertStringNotContainsStringIgnoringCase('jeu de r', $doc['text']);
                }
            }
        }
        $preview = (new SseSheetService())->preview(1, $subject, 'vigilance', ['lang' => 'fr'], self::access());
        self::assertTrue($preview['ok'], implode(' | ', $preview['errors']));
        self::assertNull(PdfText::fictionMention((string) $preview['bytes']), 'l’aperçu n’est pas non plus marqué « spécimen »');
    }

    public function testLongNamesAndTextsRenderWithoutExceptionAndStayBounded(): void
    {
        $subject = $this->subject([
            'last' => 'Karimzadeh-Vasileiou-Papadopoulos de la Fontaine', 'first' => 'Élodie-Marguerite Alexandrina', 'alias' => 'La Chauffeuse du réseau du Nord-Est',
            'marks' => str_repeat('Cicatrice à la lèvre supérieure, tatouage en forme de spirale au poignet gauche. ', 3),
            'place' => 'Entrepôt frigorifique désaffecté du quartier portuaire de Georgetown, accès par la ruelle derrière la station-service',
            'links' => [['vehicle', 'Camionnette frigorifique blanche à bandes bleues, plaque KA-4471-TZ, rétroviseur droit cassé, autocollant d’un transporteur local'], ['vehicle', 'Moto'], ['vehicle', 'Pick-up gris'], ['vehicle', 'Berline noire'],
                ['associate', 'Aleksandr Konstantinopoulos-Wojciechowski'], ['associate', 'Samir Haddad'], ['associate', 'Yusuf Okonkwo'], ['address', '14 rue du Port, Georgetown'], ['phone', '555-0142']],
        ]);
        $reason = str_repeat('Impliquée dans la préparation d’attaques par véhicules piégés contre les convois de ravitaillement. ', 6);
        foreach (['fr', 'en'] as $lang) {
            $doc = $this->issue($subject, [
                'lang' => $lang, 'threat' => 'critical', 'armed' => 'yes', 'validity' => 90,
                'reasons' => ['M-TER-05', 'M-TRF-05', 'M-INS-04', 'M-TER-02', 'M-ESP-02', 'M-FIN-04'], 'measures' => ['A-DNG', 'A-NOI', 'A-QRF', 'A-SJA', 'A-BOD', 'A-IED'],
                'service' => 'OTHER', 'service_other' => 'Cellule interarmées de coordination des recherches et du ciblage de la task force du secteur nord',
                'report_channel' => 'Canal SABRE 40.500 · indicatif SABRE-ACTUAL · relais KESTREL-MAIN 31.250 · ligne 555-0147 · permanence@coordination-recherches.invalid',
                'texts' => ['reason' => mb_substr($reason, 0, 700)],
            ]);
            self::assertGreaterThanOrEqual(1, $doc['pages']);
            self::assertLessThanOrEqual(SseSheetPdf::MAX_PAGES, $doc['pages'], $lang);
            self::assertStringContainsString('PAPADOPOULOS', mb_strtoupper($doc['text'], 'UTF-8'), $lang . ' : nom long imprimé');
            self::assertStringContainsString('DANGER', mb_strtoupper($doc['text'], 'UTF-8'));
            self::assertStringContainsString('4/4', $doc['text']);
            foreach (['A-DNG', 'A-NOI', 'A-QRF', 'A-SJA', 'A-BOD', 'A-IED'] as $code) {
                self::assertStringContainsString($code, $doc['text'], $lang . ' ' . $code);
            }
            self::assertNull(PdfText::fictionMention($doc['bytes']));
        }
    }

    public function testAMemberSubjectWithoutAuthorisationNeverShowsItsPhotoNorTheRealUserData(): void
    {
        $open = $this->issue($this->subject(['photo' => true, 'last' => 'Marlowe', 'first' => 'Idris']));
        self::assertGreaterThanOrEqual(1, self::images($open['bytes']), 'un sujet qui n’est pas membre garde son portrait');

        $member = $this->subject(['photo' => true, 'member' => 10, 'last' => 'Delacroix', 'first' => 'Mathieu', 'links' => []]);
        $doc = $this->issue($member);
        self::assertSame(0, self::images($doc['bytes']), 'aucune image d’un membre sans autorisation hiérarchique');
        self::assertStringNotContainsString('Vrainom', $doc['text'], 'nom du compte du membre');
        self::assertStringNotContainsString('76561198000000010', $doc['text'], 'identifiant Steam du membre');
        self::assertStringContainsString('M-INS-01', $doc['text'], 'codes et consignes restent');
        self::assertNull(PdfText::fictionMention($doc['bytes']));
    }

    public function testASubjectWithoutPhotoShowsAnExplicitPlaceholder(): void
    {
        $doc = $this->issue($this->subject(['photo' => false]));
        self::assertSame(0, self::images($doc['bytes']));
        self::assertStringContainsString('PHOTO', mb_strtoupper($doc['text'], 'UTF-8'));
    }

    public function testAFictionalServiceIsAcceptedAndARealOrganisationIsRefusedBeforeAnyIssue(): void
    {
        $subject = $this->subject();
        $bad = (new SseSheetService())->issue(1, $subject, 'vigilance', ['lang' => 'fr', 'service' => 'OTHER', 'service_other' => 'Interpol'], self::access());
        self::assertFalse($bad['ok']);
        self::assertNotSame([], $bad['errors']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues')->fetchColumn(), 'rien n’est émis');
        $ok = $this->issue($subject, ['service' => 'OTHER', 'service_other' => 'Cellule interarmées du secteur nord']);
        self::assertStringContainsString('Cellule interarmées du secteur nord', $ok['text']);
    }

    public function testOtherSheetModelsStillRenderTheirOwnTemplate(): void
    {
        $subject = $this->subject(['photo' => true]);
        foreach (['identification', 'profile', 'control'] as $model) {
            foreach (['fr', 'en'] as $lang) {
                $res = (new SseSheetService())->preview(1, $subject, $model, ['lang' => $lang], self::access());
                self::assertTrue($res['ok'], "$model $lang : " . implode(' | ', $res['errors']));
                $text = PdfText::of((string) $res['bytes']);
                self::assertStringStartsWith('%PDF-', (string) $res['bytes']);
                self::assertStringNotContainsString('4/4', $text, "$model : pas de bloc d’alerte");
                self::assertStringNotContainsString('A-NOI', $text, "$model : pas de consignes de vigilance");
                self::assertStringNotContainsString('SIGNALEMENT EN LIGNE', $text, "$model : pas d’adresse de signalement");
                self::assertNull(PdfText::fictionMention((string) $res['bytes']), "$model $lang");
                self::assertGreaterThanOrEqual(1, (int) preg_match_all('~/Type\s*/Page\b(?!s)~', (string) $res['bytes']));
            }
        }
    }

    /** @return list<string> valeurs de la personne qui ne doivent jamais figurer dans un document masqué à C */
    private static function secrets(): array
    {
        return ['Karimzadeh', 'KARIMZADEH', 'Dariush', 'Le Chauffeur', 'Kavala', '1984'];
    }

    public function testTheRedactionMentionIsCompactAndNeverCarriesAMaskedValue(): void
    {
        $subject = $this->subject(['photo' => true]);
        foreach (['fr', 'en'] as $lang) {
            $doc = $this->issue($subject, ['lang' => $lang, 'classification' => 'encadrement', 'threat' => 'critical', 'armed' => 'yes']);
            $text = $doc['text'];
            self::assertSame(1, $doc['pages'], $lang . ' : la mention compacte laisse la fiche sur une page');
            self::assertStringContainsString('PORTIONS WITHHELD', $text);
            self::assertStringContainsString('CERTAINS PASSAGES ONT ÉTÉ MASQUÉS', $text);
            self::assertLessThanOrEqual(2, substr_count($text, 'PORTIONS WITHHELD'), $lang . ' : la mention compacte et la légende du pied, rien de plus');
            self::assertStringContainsString($lang === 'en' ? '(a) Identity: requires S' : '(a) Identité : requiert S', $text);
            self::assertStringContainsString($lang === 'en' ? 'request release at a higher level' : 'demander la communication à un niveau supérieur', $text);
            self::assertStringNotContainsString($lang === 'en' ? 'Withholding depends on' : 'Le masquage dépend', $text, $lang . ' : l’explication longue a disparu');
            self::assertStringNotContainsString('Autres données', $text);
            self::assertStringNotContainsString('non conservée', $text);
            foreach (self::secrets() as $secret) {
                self::assertStringNotContainsString($secret, $text, $lang . ' ' . $secret . ' : texte');
                self::assertStringNotContainsString($secret, $doc['bytes'], $lang . ' ' . $secret . ' : octets');
            }
            // La légende discrète du pied de page garde la lettre de la catégorie.
            self::assertStringContainsString($lang === 'en' ? 'PORTIONS WITHHELD : (a) Identity' : 'PASSAGES MASQUÉS : (a) Identité', preg_replace('/\s+/', ' ', str_replace(' :', ' :', $text)) ?? $text);
        }
        // Rien n'est masqué à S : ni mention ni légende.
        $open = $this->issue($subject, ['classification' => 'confidentiel']);
        self::assertStringNotContainsString('PASSAGES', $open['text']);
        self::assertStringNotContainsString('requiert', $open['text']);
    }

    public function testTheStoredRedactionCountDoesNotChangeWithTheLayout(): void
    {
        $subject = $this->subject(['photo' => true]);
        $doc = $this->issue($subject, ['classification' => 'encadrement']);
        $snap = (array) json_decode((string) $this->pdo->query('SELECT snapshot_json FROM sse_doc_issues WHERE id = ' . $doc['id'])->fetchColumn(), true);
        // Nom, alias, naissance (date, lieu, nationalité), personnes liées et texte du motif : neuf barres, toutes de la catégorie identité.
        self::assertSame(9, $snap['redaction']['total']);
        self::assertSame(['identite' => 9], $snap['redaction']['by']);
    }

    public function testAGridCodeIsPresentedWithItsDateAndFillerTextIsNeverPrinted(): void
    {
        $subject = $this->subject(['place' => '151173', 'grid' => '', 'links' => []]);
        foreach (['fr' => ['Grille 151 173', 'GRILLE 151173'], 'en' => ['Grid 151 173', 'GRID 151173']] as $lang => [$wanted, $raw]) {
            $doc = $this->issue($subject, ['lang' => $lang, 'texts' => ['reason' => 'Dernier lieu connu : 151173. Comportement observé : à compléter.', 'behaviour' => 'Comportement observé : à compléter.']]);
            self::assertStringContainsString($wanted, $doc['text'], $lang);
            self::assertStringContainsString($lang === 'en' ? 'November 12, 2026' : '12 novembre 2026', $doc['text'], $lang . ' : la date suit le lieu');
            self::assertStringNotContainsString('à compléter', $doc['text']);
            self::assertStringNotContainsString($lang === 'en' ? 'OBSERVED BEHAVIOUR' : 'COMPORTEMENT OBSERVÉ', mb_strtoupper($doc['text'], 'UTF-8'), $lang . ' : une rubrique sans donnée disparaît');
            self::assertSame(1, preg_match_all('/151\s?173/', $doc['text']), $lang . ' : le code n’est écrit qu’une fois, formaté');
        }
    }

    public function testRubricsWithoutDataDisappearInsteadOfPrintingFiller(): void
    {
        $doc = $this->issue($this->subject(['links' => [], 'marks' => '', 'place' => '', 'grid' => '']));
        $upper = mb_strtoupper($doc['text'], 'UTF-8');
        foreach (['VÉHICULES', 'PERSONNES ASSOCIÉES', 'ADRESSES', 'AUCUN CONNU', 'NON COMMUNIQUÉ', 'À COMPLÉTER'] as $needle) {
            self::assertStringNotContainsString($needle, $upper, $needle);
        }
        self::assertStringContainsString('CONDUITE À TENIR', $upper);
        self::assertStringContainsString('SERVICE À INFORMER', $upper);
    }

    public function testRubricsAreNumberedInReadingOrder(): void
    {
        $doc = $this->issue($this->subject(['photo' => true]), ['threat' => 'critical', 'armed' => 'yes']);
        $flat = mb_strtoupper(preg_replace('/\s+/', ' ', $doc['text']) ?? '', 'UTF-8');
        $last = -1;
        foreach (['1 SUJET', '2 CONDUITE À TENIR', '3 SERVICE À INFORMER', '4 MOTIF DE LA VIGILANCE'] as $title) {
            $pos = strpos($flat, $title);
            self::assertNotFalse($pos, $title);
            self::assertGreaterThan($last, $pos, $title . ' : ordre de lecture');
            $last = (int) $pos;
        }
    }
}
