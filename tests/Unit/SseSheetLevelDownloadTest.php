<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseFieldNoteAdminController;
use App\Controllers\Web\SseSheetController;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\SseSheetRepository;
use App\Repositories\SseTagRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseSheetFactsService;
use App\Services\Sse\SseSheetLevelService;
use App\Services\Sse\SseSheetService;
use App\Services\Sse\SseSheetVisibility;
use App\Services\Sse\SseWantedAccess;
use App\Support\TrainingCertificatePdfEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfText;
use Tests\Support\SseFieldNoteTagsFixture as Fx;
use Tests\Support\SseFieldNoteTagsSqlite as Sq;
use Tests\Support\SseSheetSqlite;

/**
 * Télécharger un document émis à un niveau de diffusion, et régler la visibilité par fiche : niveau plafonné à l'habilitation,
 * copie rendue depuis l'instantané (rien d'émis, original intact), valeur masquée absente du PDF (données, textes imprimés,
 * propriétés), niveau plus haut seulement si le document contient déjà les données, audit, débit, 404 uniformes, plancher de
 * sécurité, réglage du modèle, surcharge d'émission, repli sans l'étape des réglages.
 */
final class SseSheetLevelDownloadTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    private const SECRETS = ['Øksnevad', 'Zoë', 'Élodie', 'Kavala', 'Pyrgos', 'La Pie', '1987-03-12'];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        Csrf::token();
        $this->pdo = SseSheetSqlite::open();
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sse_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, actor_user_id INTEGER, actor_label TEXT, action TEXT, object_type TEXT, object_id INTEGER, object_uuid TEXT, reason TEXT, before_json TEXT, after_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE sse_attach_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, name TEXT, slug TEXT, scope TEXT, family TEXT, code TEXT, name_fr TEXT, status TEXT, origin TEXT)');
        $this->pdo->exec('CREATE TABLE sse_tag_links (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER)');
        $this->pdo->exec('CREATE TABLE sse_field_note_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, setting_key TEXT NOT NULL, value_json TEXT, updated_by_user_id INTEGER, updated_at TEXT, UNIQUE (tenant_id, setting_key))');
        SseTagRepository::forgetReady();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
    }

    protected function tearDown(): void
    {
        SseTagRepository::forgetReady();
        Sq::close();
        SseSheetSqlite::close();
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    private static function access(string $level = 'confidentiel', int $tenant = 1): SseSheetAccess
    {
        return new SseSheetAccess(new SseWantedAccess(false, true, true, true, $tenant, 7, 'ANALYSTE'), static fn (): bool => true, true, $level);
    }

    private static function requirePdfEngine(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
    }

    private function person(int $tenant = 1): int
    {
        $cols = ['tenant_id' => $tenant, 'last_name' => 'Øksnevad-Çelik', 'first_name' => 'Zoë-Élodie', 'alias' => 'La Pie', 'sex_apparent' => 'F', 'age_estimated' => 38, 'birth_date' => '1987-03-12',
            'birth_place' => 'Kavala', 'nationality' => 'TAK', 'affiliation' => 'Réseau du Nord-Est', 'distinguishing_marks' => 'Cicatrice sous l’œil gauche',
            'grid_reference' => '072 118', 'location_description' => 'Pyrgos, marché couvert', 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-12 14:32:00'];
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function subject(int $personId): array
    {
        $s = (new SseSheetFactsService())->subject(1, 'person', $personId, self::access('tres_restreint'));
        self::assertNotNull($s);

        return $s;
    }

    /**
     * Émet un document (habilitation Très secret). @param array<string, mixed> $input
     *
     * @return array{id: int, ref: string, bytes: string}
     */
    private function issue(int $person, string $model, string $level, array $input = []): array
    {
        $res = (new SseSheetService())->issue(1, $this->subject($person), $model, $input + ['lang' => 'fr', 'classification' => $level, 'faces' => 'auto'], self::access('tres_restreint'));
        self::assertTrue($res['ok'], implode(' | ', $res['errors']));

        return ['id' => (int) $res['id'], 'ref' => (string) $res['ref'], 'bytes' => (string) $res['bytes']];
    }

    private function fingerprint(int $id): string
    {
        return (string) $this->pdo->query('SELECT snapshot_json || texts_json || classification || ref FROM sse_doc_issues WHERE id = ' . $id)->fetchColumn();
    }

    private function issueCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues')->fetchColumn();
    }

    private static function levels(SseSheetLevelService $svc, array $issue, SseSheetAccess $a): array
    {
        $out = [];
        foreach ($svc->options($issue, $a) as $o) {
            $out[$o['level']] = $o['state'];
        }

        return $out;
    }

    // ------------------------------------------------------------ Niveau et habilitation

    public function testTheLevelMenuAndTheDownloadNeverGoAboveTheOperatorsClearance(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        $issue = (new SseSheetRepository())->findIssue(1, $c['id']);
        $svc = new SseSheetLevelService();
        $menu = self::levels($svc, $issue, self::access('confidentiel'));
        self::assertSame(['interne', 'encadrement', 'confidentiel'], array_keys($menu), 'du plus bas à l’habilitation, jamais au-dessus');
        self::assertSame('lower', $menu['interne']);
        self::assertSame('origin', $menu['encadrement']);
        self::assertSame('reissue', $menu['confidentiel'], 'ce niveau rendrait lisibles des passages absents du document');
        $count = $this->issueCount();

        self::assertSame('ceiling', $svc->download(1, $c['id'], 'tres_restreint', self::access('confidentiel'))['error']);
        self::assertSame('reissue', $svc->download(1, $c['id'], 'confidentiel', self::access('confidentiel'))['error']);
        self::assertSame('level', $svc->download(1, $c['id'], 'public', self::access('confidentiel'))['error']);
        self::assertSame('level', $svc->download(1, $c['id'], '', self::access('confidentiel'))['error']);
        self::assertSame('format', $svc->download(1, $c['id'], 'interne', self::access('confidentiel'), 'docx')['error']);
        $ok = $svc->download(1, $c['id'], 'interne', self::access('confidentiel'));
        self::assertTrue($ok['ok']);
        self::assertSame($count, $this->issueCount(), 'aucun nouveau document n’est émis');
    }

    public function testAHigherLevelIsOfferedOnlyWhenTheDocumentAlreadyHoldsTheData(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'confidentiel');
        $issue = (new SseSheetRepository())->findIssue(1, $c['id']);
        $svc = new SseSheetLevelService();
        $menu = self::levels($svc, $issue, self::access('tres_restreint'));
        self::assertSame('higher', $menu['tres_restreint'], 'aucun passage masqué : le niveau plus haut ne change que le marquage');
        $res = $svc->download(1, $c['id'], 'tres_restreint', self::access('tres_restreint'));
        self::assertTrue($res['ok']);
        $text = PdfText::of((string) $res['bytes']);
        self::assertStringContainsString('Øksnevad', $text, 'les données du document restent lisibles');
        self::assertStringContainsString('COPIE À UN NIVEAU DE DIFFUSION', $text);
        self::assertStringContainsString('TOP SECRET', $text, 'le bandeau est celui du niveau choisi');
        self::assertStringContainsString($c['ref'], $text, 'même référence : ce n’est pas un nouveau document');
    }

    // ------------------------------------------------------------ Valeur masquée absente

    public function testMaskedValuesAreAbsentFromThePdfAtALowerLevelAndTheOriginalIsUntouched(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $typed = 'Synthèse : Øksnevad-Çelik (née à Kavala le 1987-03-12, alias La Pie) a été vue à Pyrgos par le réseau.';
        $c = $this->issue($p, 'profile', 'confidentiel', ['texts' => ['summary' => $typed]]);
        $open = PdfText::of($c['bytes']);
        foreach (['Øksnevad', 'Kavala', 'Pyrgos'] as $s) {
            self::assertStringContainsString($s, $open, $s . ' est lisible à l’émission (niveau C)');
        }
        $before = $this->fingerprint($c['id']);
        $count = $this->issueCount();

        $svc = new SseSheetLevelService();
        $res = $svc->download(1, $c['id'], 'interne', self::access('confidentiel'));
        self::assertTrue($res['ok']);
        $bytes = (string) $res['bytes'];
        $text = PdfText::of($bytes);
        foreach (self::SECRETS as $secret) {
            self::assertStringNotContainsString($secret, $text, $secret . ' : texte du PDF');
            self::assertStringNotContainsString($secret, $bytes, $secret . ' : octets du PDF');
        }
        self::assertStringContainsString('CERTAINS PASSAGES ONT ÉTÉ MASQUÉS', $text);
        self::assertStringContainsString('COPIE À UN NIVEAU DE DIFFUSION', $text);
        self::assertStringContainsString('(a) Identité', $text, 'les barres gardent la lettre de leur catégorie');
        self::assertSame($before, $this->fingerprint($c['id']), 'l’original n’est jamais modifié');
        self::assertSame($count, $this->issueCount());
        // Les textes saisis sont aussi nettoyés dans le récapitulatif : aucune valeur, seulement des catégories et des nombres.
        $issue = (new SseSheetRepository())->findIssue(1, $c['id']);
        $lower = array_values(array_filter($svc->options($issue, self::access('confidentiel')), static fn (array $o): bool => $o['level'] === 'interne'))[0];
        self::assertGreaterThan(0, $lower['total']);
        self::assertContains('identite', array_keys($lower['by']));
        self::assertStringNotContainsString('Øksnevad', json_encode($lower, JSON_UNESCAPED_UNICODE));
    }

    public function testThreeLevelsOfTheSameDocumentDifferOnlyByWhatTheirLevelAllows(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'confidentiel');
        $svc = new SseSheetLevelService();
        $texts = [];
        foreach (['interne', 'encadrement', 'confidentiel'] as $level) {
            $res = $svc->download(1, $c['id'], $level, self::access('confidentiel'));
            self::assertTrue($res['ok'], $level);
            $texts[$level] = PdfText::of((string) $res['bytes']);
        }
        self::assertStringNotContainsString('Pyrgos', $texts['interne'], 'le lieu exige C');
        self::assertStringNotContainsString('Øksnevad', $texts['interne']);
        self::assertStringContainsString('Pyrgos', $texts['encadrement'], 'le lieu est lisible à C');
        self::assertStringNotContainsString('Øksnevad', $texts['encadrement'], 'l’identité exige S');
        self::assertStringContainsString('Øksnevad', $texts['confidentiel']);
        self::assertStringNotContainsString('COPIE À UN NIVEAU DE DIFFUSION', $texts['confidentiel'], 'le niveau d’émission est le document lui-même');
        foreach (['interne', 'encadrement'] as $level) {
            self::assertStringContainsString('COPIE À UN NIVEAU DE DIFFUSION', $texts[$level], $level);
        }
    }

    // ------------------------------------------------------------ Audit, débit, 404

    public function testEveryDownloadAtALevelIsAuditedWithWhoWhichDocumentWhichLevelAndFormat(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'confidentiel');
        (new SseSheetLevelService())->download(1, $c['id'], 'encadrement', self::access('confidentiel'));
        (new SseSheetLevelService())->download(1, $c['id'], 'confidentiel', self::access('confidentiel', 1), 'xls');
        $rows = $this->pdo->query("SELECT * FROM sse_audit_log WHERE action = 'document.downloaded_at_level'")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $rows, 'un format refusé n’écrit rien');
        self::assertSame(7, (int) $rows[0]['actor_user_id']);
        self::assertSame('ANALYSTE', $rows[0]['actor_label']);
        self::assertSame($c['id'], (int) $rows[0]['object_id']);
        $after = json_decode((string) $rows[0]['after_json'], true);
        self::assertSame($c['ref'], $after['ref']);
        self::assertSame('encadrement', $after['level_code']);
        self::assertSame('pdf', $after['format']);
        self::assertSame('identification', $after['model']);
    }

    public function testNotFoundIsUniformForUnknownForeignAndAboveClearanceDocumentsAndTheRateIsLimited(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        $secret = $this->issue($p, 'profile', 'tres_restreint');
        $foreign = (new SseSheetRepository())->createIssue(2, [
            'person_id' => $this->person(2), 'identity_id' => null, 'model' => 'identification', 'lang' => 'fr', 'classification' => 'encadrement', 'faces' => 'auto', 'texts' => [], 'snapshot' => ['params' => []],
            'sensitive' => [], 'user_id' => 7, 'label' => 'AUTRE',
        ], ['ref' => 'FI-2026-0001', 'year' => 2026, 'seq' => 1]);
        $controller = new SseSheetController(null, null, null, null, null, self::access('confidentiel'));
        $get = static function (SseSheetController $ctl, int $id, string $level): \App\Core\Response {
            $_GET = ['niveau' => $level];

            return $ctl->download(new Request(), ['id' => $id]);
        };
        $unknown = $get($controller, 99999, 'interne');
        $other = $get($controller, $foreign, 'interne');
        $above = $get($controller, $secret['id'], 'interne');
        foreach ([$unknown, $other, $above] as $r) {
            self::assertSame(404, $r->statusCode());
            self::assertSame($unknown->body(), $r->body());
            self::assertSame('Not found', $r->body());
        }
        // Lecteur sans le droit de lire les fichiers (invité) : la même réponse.
        $guest = new SseSheetController(null, null, null, null, null, new SseSheetAccess(new SseWantedAccess(true, true, true, true, 1, null, 'INVITE'), static fn (): bool => true, false, 'tres_restreint'));
        self::assertSame(404, $get($guest, $c['id'], 'interne')->statusCode());
        // Niveau au-dessus de l'habilitation ou refusé : retour à la fiche avec un message, pas de PDF.
        self::assertSame(302, $get($controller, $c['id'], 'tres_restreint')->statusCode());
        // Le téléchargement réussi est un PDF en pièce jointe, au nom marqué du niveau.
        $ok = $get($controller, $c['id'], 'interne');
        self::assertSame(200, $ok->statusCode());
        self::assertSame('application/pdf', $ok->headerValue('Content-Type'));
        self::assertStringContainsString('-CUI.pdf', (string) $ok->headerValue('Content-Disposition'));
        self::assertStringStartsWith('%PDF', $ok->body());
        // Débit limité.
        Session::set('sse_sheet_level_downloads', array_fill(0, SseSheetLevelService::LIMIT, time()));
        self::assertSame(429, $get($controller, $c['id'], 'interne')->statusCode());
        Session::set('sse_sheet_level_downloads', []);
        // Sans niveau demandé : le téléchargement d'origine, inchangé.
        $_GET = [];
        self::assertSame(200, $controller->download(new Request(), ['id' => $c['id']])->statusCode());
    }

    // ------------------------------------------------------------ Visibilité par fiche

    public function testTheSecurityFloorIsInTheCodeAndOnlyMovesUpward(): void
    {
        $clean = SseSheetVisibility::clean(['biometrie' => 'interne', 'source' => 'confidentiel', 'identite' => 'public', 'lieu' => 'interne', 'horodatage' => 'tres_restreint', 'inconnue' => 'interne']);
        self::assertSame('confidentiel', $clean['biometrie'], 'la biométrie ne descend pas sous son plancher');
        self::assertSame('tres_restreint', $clean['source'], 'la source humaine non plus');
        self::assertArrayNotHasKey('identite', $clean, 'un niveau inconnu est ignoré');
        self::assertSame('interne', $clean['lieu'], 'le lieu peut devenir lisible au plus bas niveau');
        self::assertSame('tres_restreint', $clean['horodatage'], 'vers le haut, sans limite');
        self::assertArrayNotHasKey('inconnue', $clean);
        foreach (SseSheetVisibility::FLOORS as $cat => $floor) {
            self::assertGreaterThanOrEqual(SseRedactionService::levelRank($floor), SseRedactionService::levelRank(SseSheetVisibility::effective(['x' => 1], [$cat => 'interne'])[$cat]), $cat);
            self::assertLessThanOrEqual(SseRedactionService::levelRank(SseRedactionService::requiredLevel($cat)), SseRedactionService::levelRank($floor), 'les niveaux livrés respectent le plancher');
        }
        self::assertSame(SseSheetVisibility::defaults(), SseSheetVisibility::effective(), 'sans réglage : les niveaux de CATEGORIES');
    }

    public function testTheCommunityAndTheModelSettingsApplyToTheNextDocumentsAndAreAudited(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $vis = new SseSheetVisibility();
        self::assertSame(SseSheetVisibility::defaults(), $vis->forModel(1, 'identification'));
        // Communauté : le lieu n'est lisible qu'à S ; modèle : la biométrie tente de descendre sous son plancher.
        self::assertTrue($vis->save(1, SseSheetVisibility::ALL, ['lieu' => 'confidentiel', 'biometrie' => 'interne'], self::access()));
        self::assertTrue($vis->save(1, 'identification', ['horodatage' => 'confidentiel'], self::access()));
        $identification = $vis->forModel(1, 'identification');
        self::assertSame('confidentiel', $identification['lieu']);
        self::assertSame('confidentiel', $identification['biometrie'], 'plancher');
        self::assertSame('confidentiel', $identification['horodatage']);
        $profile = $vis->forModel(1, 'profile');
        self::assertSame('confidentiel', $profile['lieu'], 'le modèle sans réglage reprend la communauté');
        self::assertSame('encadrement', $profile['horodatage']);
        // Une autre communauté n'est pas concernée.
        self::assertSame(SseSheetVisibility::defaults(), $vis->forModel(2, 'identification'));
        // Le réglage s'applique à l'émission : le lieu, lisible à C par défaut, ne l'est plus à C.
        $c = $this->issue($p, 'identification', 'encadrement');
        $text = PdfText::of($c['bytes']);
        self::assertStringNotContainsString('Pyrgos', $text);
        self::assertSame('confidentiel', $this->snapshotVisibility($c['id'])['lieu']);
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_audit_log WHERE action = 'document.visibility_saved'")->fetchColumn());
        $row = $this->pdo->query("SELECT * FROM sse_audit_log WHERE action = 'document.visibility_saved' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        self::assertSame(7, (int) $row['actor_user_id']);
        // Les réglages ne dépendent d'aucun fichier : ils vivent dans sse_field_note_settings, avec la communauté.
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_field_note_settings WHERE tenant_id = 1 AND setting_key = 'sheet_visibility'")->fetchColumn());
        // On ne garde que ce qui diffère des niveaux livrés.
        $vis->save(1, 'identification', ['horodatage' => 'encadrement'], self::access());
        $stored = json_decode((string) $this->pdo->query("SELECT value_json FROM sse_field_note_settings WHERE tenant_id = 1")->fetchColumn(), true);
        self::assertArrayNotHasKey('identification', $stored);
        self::assertSame(['lieu' => 'confidentiel'], $stored['*'], 'la biométrie ramenée à son plancher est le niveau livré : rien à stocker');
    }

    /** @return array<string, string> */
    private function snapshotVisibility(int $id): array
    {
        $snap = (array) json_decode((string) $this->pdo->query('SELECT snapshot_json FROM sse_doc_issues WHERE id = ' . $id)->fetchColumn(), true);

        return (array) ($snap['data']['visibility'] ?? []);
    }

    public function testTheEmissionOverrideAppliesToThatDocumentOnlyAndNeverGoesBelowTheFloor(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        // Par défaut, à C : le lieu est lisible. Surcharge : il faut S, et l'identité tente de devenir lisible dès le niveau le plus bas.
        $c = $this->issue($p, 'identification', 'encadrement', ['visibility' => ['lieu' => 'confidentiel', 'identite' => 'interne', 'biometrie' => 'interne']]);
        $text = PdfText::of($c['bytes']);
        self::assertStringNotContainsString('Pyrgos', $text, 'surcharge : lieu relevé à S pour ce document');
        foreach (['Øksnevad', 'Zoë', 'Kavala'] as $secret) {
            self::assertStringNotContainsString($secret, $text, $secret . ' : l’identité reste sous son plancher (S)');
        }
        $vis = $this->snapshotVisibility($c['id']);
        self::assertSame('confidentiel', $vis['lieu']);
        self::assertSame('confidentiel', $vis['identite']);
        self::assertSame('confidentiel', $vis['biometrie']);
        $snap = (array) json_decode((string) $this->pdo->query('SELECT snapshot_json FROM sse_doc_issues WHERE id = ' . $c['id'])->fetchColumn(), true);
        self::assertSame('confidentiel', $snap['params']['visibility']['lieu'], 'la surcharge est stockée dans l’instantané');
        // Le PDF explique le niveau requis réglé pour ce document.
        self::assertStringContainsString('(b) Lieu : requiert S', $text);
        // Un document émis ensuite, sans surcharge, retrouve les niveaux livrés.
        $d = $this->issue($p, 'identification', 'encadrement');
        self::assertStringContainsString('Pyrgos', PdfText::of($d['bytes']));
        self::assertSame('encadrement', $this->snapshotVisibility($d['id'])['lieu']);
        // Le téléchargement à un niveau suit le réglage du document : à S, le lieu s'affiche (il reste masqué à l'émission).
        $svc = new SseSheetLevelService();
        $issue = (new SseSheetRepository())->findIssue(1, $c['id']);
        self::assertSame('reissue', self::levels($svc, $issue, self::access('confidentiel'))['confidentiel']);
        $lower = $svc->download(1, $d['id'], 'interne', self::access('confidentiel'));
        self::assertStringNotContainsString('Pyrgos', PdfText::of((string) $lower['bytes']));
    }

    public function testWithoutTheSettingsTableTheDeliveredLevelsApplyAndNothingIsSaved(): void
    {
        self::requirePdfEngine();
        $this->pdo->exec('DROP TABLE sse_field_note_settings');
        SseTagRepository::forgetReady();
        $vis = new SseSheetVisibility();
        self::assertFalse(SseSheetVisibility::canStore());
        self::assertSame(SseSheetVisibility::defaults(), $vis->forModel(1, 'identification'));
        self::assertFalse($vis->save(1, 'identification', ['lieu' => 'confidentiel'], self::access()));
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        self::assertStringContainsString('Pyrgos', PdfText::of($c['bytes']));
        // La surcharge d'émission, elle, ne dépend pas de la table.
        $d = $this->issue($p, 'identification', 'encadrement', ['visibility' => ['lieu' => 'confidentiel']]);
        self::assertStringNotContainsString('Pyrgos', PdfText::of($d['bytes']));
        // Le menu et le téléchargement à un niveau fonctionnent sans elle.
        self::assertTrue((new SseSheetLevelService())->download(1, $c['id'], 'interne', self::access('confidentiel'))['ok']);
    }

    // ------------------------------------------------------------ Interface

    /** @return array{0: string, 1: array<string, mixed>} */
    private function editor(string $model, SseSheetAccess $access, int $person): array
    {
        $_GET = [];
        $r = (new SseSheetController(null, null, null, null, null, $access))->editor(new Request(), ['kind' => 'personne', 'id' => $person, 'model' => $model]);
        self::assertSame(200, $r->statusCode());

        return [$r->body(), []];
    }

    public function testTheEditorOffersTheVisibilityModalWithRadiosFloorsAndPreviewButtons(): void
    {
        $p = $this->person();
        foreach (['identification', 'vigilance'] as $model) {
            [$html] = $this->editor($model, self::access('confidentiel'), $p);
            self::assertStringContainsString('data-shx-open="shx-vis"', $html, $model);
            self::assertStringContainsString('Visibilité par niveau', $html, $model);
            preg_match('~<dialog class="svl-dialog".*?</dialog>~s', $html, $m);
            self::assertNotEmpty($m, $model);
            $dialog = $m[0];
            foreach (array_keys(SseRedactionService::CATEGORIES) as $cat) {
                self::assertStringContainsString('name="visibility[' . $cat . ']"', $dialog, $cat);
            }
            // Plancher : la biométrie et l'identité ne peuvent pas être choisies sous S (cases verrouillées).
            self::assertMatchesRegularExpression('~id="shx-vis-biometrie-interne"[^>]*disabled~', $dialog);
            self::assertDoesNotMatchRegularExpression('~id="shx-vis-lieu-interne"[^>]*disabled~', $dialog);
            // Aperçu du PDF à chaque niveau de l'opérateur, pas au-dessus.
            self::assertStringContainsString('name="preview_level" value="confidentiel"', $dialog);
            self::assertStringNotContainsString('name="preview_level" value="tres_restreint"', $dialog);
            self::assertStringContainsString('svl-legend', $dialog);
            self::assertStringNotContainsString('sse-ds', $dialog);
            foreach (self::SECRETS as $secret) {
                self::assertStringNotContainsString($secret, $dialog, $secret);
            }
        }
    }

    public function testTheIssuedDocumentsOfferTheLevelMenuWithoutValuesOrLevelsAboveTheClearance(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        [$html] = $this->editor('identification', self::access('confidentiel'), $p);
        foreach ([$c['id']] as $id) {
            self::assertStringContainsString('data-shx-open="shx-dl-' . $id . '"', $html);
            preg_match('~<dialog class="shx-dialog" id="shx-dl-' . $id . '".*?</dialog>~s', $html, $m);
            self::assertNotEmpty($m);
            $dialog = $m[0];
            self::assertStringContainsString('method="get"', $dialog);
            self::assertStringContainsString('name="niveau"', $dialog);
            self::assertStringNotContainsString('value="tres_restreint"', $dialog);
            self::assertStringContainsString('value="interne"', $dialog);
            self::assertStringContainsString('réémission requise', $dialog);
            self::assertStringContainsString('passage(s) masqué(s)', $dialog);
            self::assertStringNotContainsString('█', $dialog);
            foreach (self::SECRETS as $secret) {
                self::assertStringNotContainsString($secret, $dialog, $secret);
            }
        }
        // La personne vue par un lecteur habilité S à C : le niveau d'émission est celui qui est sélectionné.
        self::assertMatchesRegularExpression('~<option value="encadrement" data-state="origin" selected>~', $html);
    }

    public function testTheManagementPageEditsTheModelSettingsWithTheManageRightOnly(): void
    {
        // Base réaliste des étiquettes de fiches (étape 4850 passée) : la vraie page est rendue.
        SseSheetSqlite::close();
        $this->pdo = Sq::open();
        Fx::seed($this->pdo);
        $_SESSION['sse_tenant_id'] = 1;
        $rights = static fn (bool $manage): \Closure => static fn (): array => ['tenant' => 1, 'user' => 7, 'guest' => false, 'read' => true, 'manage' => $manage, 'label' => 'GESTION'];
        $controller = static fn (bool $manage): SseFieldNoteAdminController => new SseFieldNoteAdminController(null, $rights($manage), static fn (): bool => false);
        $post = static function (SseFieldNoteAdminController $c, array $data): \App\Core\Response {
            $_POST = $data + ['_csrf_token' => Csrf::token()];
            $_SERVER['REQUEST_METHOD'] = 'POST';

            return $c->save(new Request(), ['action' => 'visibilite']);
        };
        // Sans le droit de gestion : rien n'est enregistré.
        $post($controller(false), ['scope' => 'identification', 'visibility' => ['lieu' => 'confidentiel']]);
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_field_note_settings WHERE setting_key = 'sheet_visibility'")->fetchColumn());
        // Avec le droit : le réglage du modèle est enregistré et la page le montre ; plancher respecté, avis de retour.
        $r = $post($controller(true), ['scope' => 'identification', 'visibility' => ['lieu' => 'confidentiel', 'biometrie' => 'interne']]);
        self::assertSame(302, $r->statusCode());
        self::assertStringEndsWith('#modeles', (string) $r->headerValue('Location'));
        $stored = (new SseSheetVisibility())->stored(1);
        self::assertSame(['lieu' => 'confidentiel'], $stored['identification'], 'la biométrie à « interne » est ramenée au plancher, qui est le niveau livré : rien à stocker');
        // Portée inconnue (avis de recherche, modèle inventé) : rien.
        $post($controller(true), ['scope' => 'notice', 'visibility' => ['lieu' => 'confidentiel']]);
        $post($controller(true), ['scope' => 'inventé', 'visibility' => ['lieu' => 'confidentiel']]);
        self::assertSame(['identification'], array_keys((new SseSheetVisibility())->stored(1)));
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $page = $controller(true)->index(new Request(), [])->body();
        self::assertStringContainsString('Visibilité par niveau des fiches PDF', $page);
        self::assertStringContainsString('id="svl-dlg-identification"', $page);
        self::assertStringContainsString('id="svl-dlg-all"', $page);
        self::assertStringContainsString('name="visibility[lieu]"', $page);
        self::assertStringContainsString('Lieu : S', $page, 'la ligne du modèle résume ce qui est réglé');
        self::assertStringNotContainsString('svl-dlg-notice', $page);
        // Lecture seule : le tableau est visible, pas la fenêtre.
        $read = $controller(false)->index(new Request(), [])->body();
        self::assertStringContainsString('Visibilité par niveau des fiches PDF', $read);
        self::assertStringNotContainsString('id="svl-dlg-identification"', $read);
        // Journal.
        self::assertGreaterThan(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_audit_log WHERE action = 'document.visibility_saved'")->fetchColumn());
    }
}
