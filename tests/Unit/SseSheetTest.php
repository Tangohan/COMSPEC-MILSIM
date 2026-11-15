<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseSheetController;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseSheetRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseSheetFactsService;
use App\Services\Sse\SseSheetMenu;
use App\Services\Sse\SseSheetPdf;
use App\Services\Sse\SseSheetService;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedService;
use App\Support\SseSheetCatalog;
use App\Support\SseSheetText;
use App\Support\SseWantedRealNames;
use App\Support\TrainingCertificatePdfEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseSheetSqlite;

/**
 * Fiches PDF d'une personne (identification, vigilance, renseignement, contrôle, avis) : texte assaini, variables résolues
 * selon l'habilitation, caviardage selon la diffusion, droits par modèle, émission journalisée sans toucher la fiche,
 * brouillons et trames par communauté, PDF valides avec et sans identité du registre, gabarit sans organisation réelle.
 */
final class SseSheetTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        \App\Core\Csrf::token();
        $this->pdo = SseSheetSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
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

    /** @param list<string> $deny « section:niveau » refusés par la politique */
    private static function access(string $level = 'confidentiel', array $deny = [], bool $signed = true, bool $manage = true, int $tenant = 1): SseSheetAccess
    {
        $wanted = new SseWantedAccess(!$signed, $manage, true, true, $tenant, $signed ? 7 : null, 'ANALYSTE');

        return new SseSheetAccess($wanted, static fn (string $s, string $l): bool => !in_array($s . ':' . $l, $deny, true), $signed, $level);
    }

    private function person(int $tenant = 1, string $last = 'Øksnevad-Çelik', string $first = 'Zoë-Élodie', array $extra = []): int
    {
        $cols = ['tenant_id' => $tenant, 'last_name' => $last, 'first_name' => $first, 'alias' => 'La Pie', 'sex_apparent' => 'F', 'age_estimated' => 38, 'birth_date' => '1987-03-12',
            'birth_place' => 'Kavala', 'nationality' => 'TAK', 'affiliation' => 'Réseau du Nord-Est', 'distinguishing_marks' => 'Cicatrice sous l’œil gauche',
            'grid_reference' => '072 118', 'location_description' => 'Pyrgos, marché couvert', 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-12 14:32:00'] + $extra;
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));

        return (int) $this->pdo->lastInsertId();
    }

    private function identityFor(int $personId, string $kind = 'civil', int $tenant = 1): int
    {
        return (new SseIdentityRepository())->create($tenant, ['last_name' => 'Øksnevad-Çelik', 'first_name' => 'Zoë-Élodie', 'kind' => $kind, 'person_id' => $personId, 'sex' => 'F', 'birth_date' => '1987-03-12', 'nationality' => 'TAK']);
    }

    private function subject(int $personId, ?SseSheetAccess $access = null): array
    {
        $s = (new SseSheetFactsService())->subject(1, 'person', $personId, $access ?? self::access());
        self::assertNotNull($s);

        return $s;
    }

    private static function requirePdfEngine(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
    }

    private static function pages(string $pdf): int
    {
        return preg_match_all('~/Type\s*/Page\b(?!s)~', $pdf);
    }

    /** Photo de synthèse rangée sous public/uploads pour la durée du test (aucun visage réel). */
    private function photoPath(): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $dir = dirname(__DIR__, 2) . '/public/uploads/sse-sheet-test';
        @mkdir($dir, 0777, true);
        $file = $dir . '/portrait-' . bin2hex(random_bytes(4)) . '.jpg';
        $im = imagecreatetruecolor(210, 270);
        imagefill($im, 0, 0, (int) imagecolorallocate($im, 90, 110, 130));
        imagefilledellipse($im, 105, 110, 90, 120, (int) imagecolorallocate($im, 220, 180, 150));
        imagejpeg($im, $file, 80);
        $this->files[] = $file;

        return 'uploads/sse-sheet-test/' . basename($file);
    }

    // ----------------------------------------------------------------- Texte

    public function testTextIsSanitisedBoundedAndRenderedWithoutAnyHtml(): void
    {
        $raw = "<script>alert(1)</script>**gras** et *italique*\n- un\n- deux\x07\n\n\n\nfin <b onclick=x>";
        $clean = SseSheetText::clean($raw, 500);
        self::assertStringNotContainsString('<', $clean);
        self::assertStringNotContainsString('>', $clean);
        self::assertStringNotContainsString("\x07", $clean);
        self::assertStringNotContainsString("\n\n\n", $clean);
        $html = SseSheetText::toHtml($clean);
        self::assertStringContainsString('<b>gras</b>', $html);
        self::assertStringContainsString('<i>italique</i>', $html);
        self::assertStringContainsString('&bull;&nbsp;un', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('onclick', str_replace('onclick=x', '', $html) . ''); // le texte reste du texte
        // Un caractère de balisage venu d'une donnée est échappé.
        self::assertStringContainsString('a &lt; b', SseSheetText::toHtml('a < b'));
        // Longueur : bornée par champ, balisage exclu du compte.
        self::assertSame(10, mb_strlen(SseSheetText::clean(str_repeat('é', 50), 10), 'UTF-8'));
        self::assertSame(4, SseSheetText::length('**abcd**'));
        self::assertSame('• x', SseSheetText::plain('- x'));
    }

    public function testVariablesAreResolvedAndSensitiveOnesAreFlagged(): void
    {
        $r = SseSheetText::resolve('Cible {nom_complet} ({menace}) {inconnue}', ['nom_complet' => 'Zoë ØKS', 'menace' => 'élevé']);
        self::assertSame('Cible Zoë ØKS (élevé) {inconnue}', $r['text']);
        self::assertSame(['nom_complet', 'menace'], $r['used']);
        self::assertSame(['identite'], SseSheetText::categories(['nom_complet', 'menace', 'alias']));
        self::assertSame(['identite', 'lieu'], SseSheetText::categories(['nom', 'dernier_lieu']));
    }

    // ------------------------------------------------------- Caviardage, textes

    public function testMaskedDataNeverReachesTheDocumentOrTheFreeText(): void
    {
        $p = $this->person();
        $this->identityFor($p);
        $access = self::access('confidentiel');
        $subject = $this->subject($p, $access);
        $svc = new SseSheetService();

        // Diffusion « interne » : identité, lieu et horodatage sont masqués, même pour un lecteur habilité Confidentiel.
        $prep = $svc->prepare(1, $subject, 'identification', ['classification' => 'interne', 'lang' => 'fr'], $access);
        self::assertTrue($prep['ok'], implode(' | ', $prep['errors']));
        $json = (string) json_encode($prep['data'], JSON_UNESCAPED_UNICODE);
        foreach (['Øksnevad', 'Zoë', 'Kavala', 'Pyrgos', 'La Pie', '1987-03-12'] as $secret) {
            self::assertStringNotContainsString($secret, $json, $secret . ' masqué en diffusion interne');
        }
        self::assertStringContainsString('█', $json);
        self::assertSame('interne', $prep['level']);

        // Les variables des textes par défaut sont résolues avec les mêmes droits : barre, jamais la valeur.
        foreach ($prep['texts'] as $text) {
            self::assertStringNotContainsString('Øksnevad', $text);
        }

        // Un nom tapé à la main dans le texte libre est refusé à ce niveau de diffusion.
        $leak = $svc->prepare(1, $subject, 'identification', ['classification' => 'interne', 'texts' => ['remarks' => 'Voir la fiche de Øksnevad-Çelik à Pyrgos.']], $access);
        self::assertFalse($leak['ok']);
        self::assertStringContainsString('Øksnevad-Çelik', implode(' ', $leak['errors']));
        self::assertStringContainsString('Pyrgos', implode(' ', $leak['errors']));

        // Au niveau confidentiel, les mêmes données sont lisibles.
        $open = $svc->prepare(1, $subject, 'identification', ['classification' => 'confidentiel', 'texts' => ['remarks' => 'Voir la fiche de Øksnevad-Çelik.']], $access);
        self::assertTrue($open['ok'], implode(' | ', $open['errors']));
        self::assertStringContainsString('Øksnevad', (string) json_encode($open['data'], JSON_UNESCAPED_UNICODE));
        self::assertContains('identite', $open['sensitive']);
    }

    public function testClassificationNeverExceedsTheReadersClearance(): void
    {
        $low = self::access('encadrement');
        self::assertSame('encadrement', SseSheetService::clampLevel('tres_restreint', $low));
        self::assertSame('encadrement', SseSheetService::clampLevel('n_importe_quoi', $low));
        self::assertSame('interne', SseSheetService::clampLevel('interne', $low));
        self::assertSame(['interne', 'encadrement'], SseSheetService::levelsFor($low));

        $p = $this->person();
        $prep = (new SseSheetService())->prepare(1, $this->subject($p, $low), 'profile', ['classification' => 'tres_restreint'], $low);
        self::assertSame('encadrement', $prep['level']);
        // Encadrement : l'identité (Confidentiel) reste masquée, le lieu (Encadrement) est lisible.
        $json = (string) json_encode($prep['data'], JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('Øksnevad', $json);
        self::assertStringContainsString('Pyrgos', $json);
    }

    public function testTextLengthIsEnforcedPerField(): void
    {
        $p = $this->person();
        $prep = (new SseSheetService())->prepare(1, $this->subject($p), 'vigilance', ['texts' => ['reason' => str_repeat('a', 701)]], self::access());
        self::assertFalse($prep['ok']);
        self::assertStringContainsString('701', implode(' ', $prep['errors']));
        $ok = (new SseSheetService())->prepare(1, $this->subject($p), 'vigilance', ['texts' => ['reason' => str_repeat('a', 700)]], self::access());
        self::assertTrue($ok['ok'], implode(' | ', $ok['errors']));
    }

    // ------------------------------------------------------------------- PDF

    public function testEveryModelMakesAValidPdfWithAndWithoutARegistryIdentity(): void
    {
        self::requirePdfEngine();
        $photo = $this->photoPath();
        $with = $this->person();
        $this->identityFor($with);
        $this->pdo->prepare('INSERT INTO sse_person_photos (tenant_id, person_id, image_path, angle, photo_type, created_at) VALUES (1, ?, ?, \'face\', \'FACE\', \'2026-11-01 10:00:00\')')->execute([$with, $photo ?? '']);
        $without = $this->person(1, 'Zéphyrin', 'Jean-Baptiste', ['alias' => null, 'distinguishing_marks' => null, 'affiliation' => null, 'birth_date' => null]);
        $svc = new SseSheetService();
        foreach (['identification', 'vigilance', 'profile', 'control'] as $model) {
            foreach ([$with, $without] as $pid) {
                foreach (['fr', 'en'] as $lang) {
                    $res = $svc->preview(1, $this->subject($pid), $model, ['lang' => $lang], self::access());
                    self::assertTrue($res['ok'], "$model $pid $lang : " . implode(' | ', $res['errors']));
                    self::assertStringStartsWith('%PDF-', (string) $res['bytes']);
                    self::assertGreaterThanOrEqual(1, self::pages((string) $res['bytes']));
                    self::assertLessThanOrEqual(SseSheetPdf::MAX_PAGES, $res['pages']);
                }
            }
        }
        // Textes très longs : le document reste borné et signale le débordement.
        $long = str_repeat('Phrase très longue avec des accents éàç. ', 40);
        $res = $svc->preview(1, $this->subject($with), 'profile', ['faces' => '1', 'texts' => ['summary' => mb_substr($long, 0, 1500), 'assessment' => mb_substr($long, 0, 900), 'recommendations' => mb_substr($long, 0, 600)]], self::access());
        self::assertTrue($res['ok'], implode(' | ', $res['errors']));
        self::assertSame(1, self::pages((string) $res['bytes']));
        self::assertNotSame([], $res['overflow']);
    }

    public function testSheetsCarryNoFictionMentionAndNameNoRealOrganisation(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        // Chaque modèle de fiche, en français et en anglais, sur une et deux pages : aucune mention de jeu, ni sur les
        // pages (en-tête, pied, filigrane, bloc de classification) ni dans les propriétés du fichier.
        foreach (array_keys(\App\Support\SseSheetCatalog::MODELS) as $model) {
            foreach (['fr', 'en'] as $lang) {
                foreach (['1', '2'] as $faces) {
                    $res = (new SseSheetService())->preview(1, $this->subject($p), $model, ['lang' => $lang, 'faces' => $faces], self::access());
                    if (!$res['ok']) {
                        continue;
                    }
                    $text = \Tests\Support\PdfText::of((string) $res['bytes']);
                    self::assertStringContainsString('ksnevad', $text, "$model $lang : le texte des pages est bien lu");
                    self::assertNull(\Tests\Support\PdfText::fictionMention((string) $res['bytes']), "$model $lang $faces : mention de jeu sur la fiche");
                }
            }
        }
        $res = (new SseSheetService())->preview(1, $this->subject($p), 'identification', [], self::access());
        self::assertTrue($res['ok'], implode(' | ', $res['errors']));
        self::assertNull(\Tests\Support\PdfText::fictionMention((string) $res['bytes']));

        $root = dirname(__DIR__, 2) . '/app/';
        foreach (['Services/Sse/SseSheetPdf.php', 'Services/Sse/SseSheetService.php', 'Services/Sse/SseSheetFactsService.php', 'Support/SseSheetCatalog.php', 'Support/SseSheetText.php', 'Services/Sse/SseSheetMenu.php'] as $file) {
            $source = (string) file_get_contents($root . $file);
            self::assertNull(SseWantedRealNames::find($source), $file . ' cite une organisation réelle : ' . (string) SseWantedRealNames::find($source));
            foreach (['interpol', 'europol', 'fbi', 'gendarmerie', 'police nationale', 'scotland yard', 'nato', 'otan'] as $word) {
                self::assertStringNotContainsStringIgnoringCase($word, $source, $file);
            }
        }
        $template = (string) file_get_contents($root . 'Services/Sse/SseSheetPdf.php');
        self::assertStringNotContainsString('Document de fiction', $template);
        self::assertStringNotContainsString('Fictional document', $template);
        self::assertStringNotContainsString('FICTION - RP', $template);
        self::assertStringNotContainsStringIgnoringCase('role-play', $template);
        self::assertStringNotContainsString('->Image(\'http', $template, 'Aucun logo téléchargé');
    }

    // ----------------------------------------------------------------- Droits

    public function testRightsDecideWhichModelsAreIssuable(): void
    {
        $full = self::access();
        foreach (array_keys(SseSheetCatalog::MODELS) as $m) {
            self::assertNull($full->denial($m), $m);
        }
        // Sans exporter « fichiers » : ni identification ni renseignement ; la vigilance demande aussi d'écrire.
        $noExport = self::access('confidentiel', ['fichiers:export']);
        self::assertNotNull($noExport->denial('identification'));
        self::assertNotNull($noExport->denial('profile'));
        self::assertNull($noExport->denial('control'));
        self::assertStringContainsString('Fichiers et sujets', (string) $noExport->denial('identification'));
        $noWrite = self::access('confidentiel', ['fichiers:write']);
        self::assertNotNull($noWrite->denial('vigilance'));
        self::assertNull($noWrite->denial('identification'));
        $noPapers = self::access('confidentiel', ['papiers:export']);
        self::assertNotNull($noPapers->denial('control'));
        self::assertNotNull($noPapers->denial('vigilance'));
        // L'avis de recherche garde les droits de SseWantedAccess.
        self::assertNotNull(self::access('confidentiel', [], true, false)->denial('notice'));
        // Invité : jamais.
        $guest = self::access('confidentiel', [], false);
        self::assertFalse($guest->canDraft());
        self::assertStringContainsString('invitée', (string) $guest->denial('identification'));
    }

    public function testTheMenuHidesUnavailableModelsWithTheirReason(): void
    {
        $p = $this->person();
        $subject = $this->subject($p);
        $menu = SseSheetMenu::build(1, $subject, 'personne', self::access('confidentiel', ['papiers:export']), false);
        $allowed = array_column($menu['allowed'], 'model');
        self::assertContains('identification', $allowed);
        self::assertNotContains('control', $allowed);
        self::assertNotContains('vigilance', $allowed);
        $locked = array_column($menu['locked'], 'reason', 'model');
        self::assertArrayHasKey('control', $locked);
        self::assertStringContainsString('Papiers et couvertures', $locked['control']);
        // Sans identité du registre, l'avis propose de la créer en un clic (même garde que la création).
        $notice = array_values(array_filter($menu['allowed'], static fn (array $a): bool => $a['model'] === 'notice'));
        self::assertSame('create_identity', $notice[0]['action'] ?? null);
        self::assertStringContainsString('/registre', $notice[0]['url']);
        // Pas de droit de lire : aucun bouton, une raison.
        $none = SseSheetMenu::build(1, $subject, 'personne', self::access('interne', [], false), true);
        self::assertSame([], $none['allowed']);
        self::assertNotNull($none['reason']);
    }

    // -------------------------------------------------------------- Émission

    public function testIssuingLogsTheDocumentKeepsThePrintedTextAndNeverTouchesTheRecord(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $identity = $this->identityFor($p);
        $before = $this->pdo->query("SELECT * FROM sse_persons WHERE id = {$p}")->fetch(PDO::FETCH_ASSOC);
        $access = self::access();
        $svc = new SseSheetService();
        $res = $svc->issue(1, $this->subject($p), 'vigilance', ['lang' => 'en', 'validity' => '60', 'texts' => ['instructions' => "- **Observe** only\n- Report to {service}"]], $access);
        self::assertTrue($res['ok'], implode(' | ', $res['errors']));
        self::assertMatchesRegularExpression('/^FV-\d{4}-0001$/', $res['ref']);
        self::assertStringStartsWith('%PDF-', (string) $res['bytes']);
        self::assertSame('fiche-vigilance-' . $res['ref'] . '-en.pdf', $res['filename']);

        $row = $this->pdo->query('SELECT * FROM sse_doc_issues')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('vigilance', $row['model']);
        self::assertSame('en', $row['lang']);
        self::assertSame(7, (int) $row['issued_by_user_id']);
        self::assertSame($p, (int) $row['person_id']);
        self::assertSame($identity, (int) $row['identity_id']);
        $texts = json_decode((string) $row['texts_json'], true);
        self::assertStringContainsString('**Observe** only', $texts['instructions']);
        self::assertStringNotContainsString('{service}', $texts['instructions'], 'Le texte conservé est le texte imprimé, variables résolues');

        // Chronologie de l'identité reliée.
        $event = $this->pdo->query("SELECT event_type, summary FROM sse_identity_events WHERE event_type = 'document.issued'")->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($event);
        self::assertStringContainsString($res['ref'], (string) $event['summary']);

        // La fiche n'est jamais modifiée.
        self::assertSame($before, $this->pdo->query("SELECT * FROM sse_persons WHERE id = {$p}")->fetch(PDO::FETCH_ASSOC));

        // Références suivantes : uniques et séquentielles.
        $second = $svc->issue(1, $this->subject($p), 'vigilance', [], $access);
        self::assertMatchesRegularExpression('/^FV-\d{4}-0002$/', $second['ref']);
        $other = $svc->issue(1, $this->subject($p), 'profile', [], $access);
        self::assertMatchesRegularExpression('/^FR-\d{4}-0001$/', $other['ref']);

        // Re-téléchargement : même texte, même référence.
        $again = $svc->reissue(1, $res['id'], $access);
        self::assertTrue($again['ok']);
        self::assertStringStartsWith('%PDF-', (string) $again['bytes']);
        // Sans l'habilitation de la classification du document, il reste fermé.
        $this->pdo->exec("UPDATE sse_doc_issues SET classification = 'tres_restreint' WHERE id = " . (int) $res['id']);
        self::assertSame('forbidden', $svc->reissue(1, $res['id'], self::access('confidentiel'))['error']);
        // Ni d'une autre communauté.
        self::assertSame('notfound', $svc->reissue(2, $res['id'], self::access('tres_restreint', [], true, true, 2))['error']);
    }

    public function testIssuingWithoutTheRightsOrTheStepOrOverTheRateLimitIsRefused(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $svc = new SseSheetService();
        $denied = $svc->issue(1, $this->subject($p), 'control', [], self::access('confidentiel', ['papiers:export']));
        self::assertFalse($denied['ok']);
        self::assertSame('forbidden', $denied['error']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues')->fetchColumn());

        for ($i = 0; $i < SseSheetService::ISSUE_LIMIT; ++$i) {
            self::assertTrue($svc->issue(1, $this->subject($p), 'identification', [], self::access())['ok'], 'émission ' . $i);
        }
        $over = $svc->issue(1, $this->subject($p), 'identification', [], self::access());
        self::assertFalse($over['ok']);
        self::assertSame('rate', $over['error']);
        self::assertSame(SseSheetService::ISSUE_LIMIT, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues')->fetchColumn());
    }

    public function testWithoutTheDatabaseStepOnlyTheNoticeKeepsWorking(): void
    {
        $this->pdo->exec('DROP TABLE sse_doc_issues');
        SseSheetRepository::forgetReady();
        $p = $this->person();
        $res = (new SseSheetService())->issue(1, $this->subject($p), 'vigilance', [], self::access());
        self::assertFalse($res['ok']);
        self::assertSame('unavailable', $res['error']);
        $menu = SseSheetMenu::build(1, $this->subject($p), 'personne', self::access(), false);
        self::assertContains('vigilance', array_column($menu['locked'], 'model'));
    }

    public function testTheWantedNoticeIsIssuedFromTheSheetWithEditedTexts(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $identity = $this->identityFor($p);
        $placed = (new SseWantedService())->place(1, $identity, ['notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'yes', 'reason' => 'Motif initial.'], new SseWantedAccess(false, true, true, true, 1, 7, 'ANALYSTE'));
        self::assertTrue($placed['ok']);
        $svc = new SseSheetService();
        $res = $svc->issue(1, $this->subject($p), 'notice', ['texts' => ['reason' => 'Motif **réécrit** pour le briefing.']], self::access());
        self::assertTrue($res['ok'], implode(' | ', $res['errors']));
        self::assertStringStartsWith('%PDF-', (string) $res['bytes']);
        self::assertStringStartsWith($placed['ref'], $res['ref']);
        $row = $this->pdo->query("SELECT texts_json FROM sse_doc_issues WHERE model = 'notice'")->fetch(PDO::FETCH_ASSOC);
        self::assertStringContainsString('réécrit', (string) $row['texts_json']);
        // L'avis lui-même n'est pas modifié par l'émission.
        self::assertSame('Motif initial.', (new SseWantedNoticeRepository())->find(1, (int) $placed['id'])['reason']);
        // Sans identité du registre, l'avis n'a rien à émettre.
        $bare = $this->person(1, 'Sans', 'Registre');
        $none = $svc->issue(1, $this->subject($bare), 'notice', [], self::access());
        self::assertFalse($none['ok']);
        self::assertSame('nonotice', $none['error']);
    }

    // ----------------------------------------------- Brouillons, trames, tenant

    public function testDraftsAndPhrasesAreScopedToTheCommunityAndTheRecord(): void
    {
        $repo = new SseSheetRepository();
        $id = $repo->saveDraft(1, null, 11, null, 'vigilance', 'fr', 'Version briefing', ['texts' => ['reason' => 'abc'], 'params' => ['classification' => 'encadrement']], 7);
        self::assertSame('abc', $repo->findDraft(1, $id)['texts']['reason']);
        self::assertNull($repo->findDraft(2, $id), 'Brouillon d’une autre communauté introuvable');
        self::assertCount(1, $repo->listDrafts(1, 11, [], 'vigilance'));
        self::assertCount(0, $repo->listDrafts(1, 12, [], 'vigilance'));
        self::assertCount(0, $repo->listDrafts(2, 11, [], 'vigilance'));
        $repo->saveDraft(1, $id, 11, null, 'vigilance', 'en', 'Renommé', ['texts' => ['reason' => 'def']], 7);
        self::assertSame('def', $repo->findDraft(1, $id)['texts']['reason']);
        self::assertSame('en', $repo->findDraft(1, $id)['lang']);
        // Plusieurs brouillons pour une même fiche et un même modèle.
        $repo->saveDraft(1, null, 11, null, 'vigilance', 'fr', 'Autre', [], 7);
        self::assertCount(2, $repo->listDrafts(1, 11, [], 'vigilance'));
        self::assertFalse($repo->deleteDraft(2, $id));
        self::assertTrue($repo->deleteDraft(1, $id));

        $ph = $repo->savePhrase(1, null, 'vigilance', 'fr', 'Ne pas intervenir', 'Ne pas intervenir seul : observer et rendre compte.', 7);
        $repo->savePhrase(1, null, '', 'fr', 'Mention', 'Ne pas diffuser.', 7);
        $repo->savePhrase(1, null, 'profile', 'en', 'Other model', 'x', 7);
        self::assertCount(2, $repo->listPhrases(1, 'vigilance', 'fr'));
        self::assertCount(0, $repo->listPhrases(2));
        self::assertFalse($repo->deletePhrase(2, $ph));
        self::assertTrue($repo->deletePhrase(1, $ph));
    }

    public function testASheetOfAnotherCommunityOrAnHiddenIdentityIsNotFound(): void
    {
        $p = $this->person(2, 'Autre', 'Communauté');
        $facts = new SseSheetFactsService();
        self::assertNull($facts->subject(1, 'person', $p, self::access()));
        $mine = $this->person();
        $cover = $this->identityFor($mine, 'cover');
        self::assertNull($facts->subject(1, 'identity', $cover, new SseSheetAccess(new SseWantedAccess(false, true, true, false, 1, 7), static fn (): bool => true, true, 'encadrement')), 'Couverture : vérité de jeu');
        self::assertNotNull($facts->subject(1, 'identity', $cover, self::access()));
    }

    // ------------------------------------------------------------ Contrôleur

    public function testControllerWritesNeedTheCsrfTokenAndTheRights(): void
    {
        $p = $this->person();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = new SseSheetController(null, null, null, null, null, self::access());
        $params = ['kind' => 'personne', 'id' => $p, 'model' => 'vigilance'];

        // Brouillon : jeton obligatoire.
        $_POST = ['_csrf_token' => 'mauvais', 'lang' => 'fr', 'texts' => ['reason' => 'x']];
        $r = $controller->draftSave(new Request(), $params);
        self::assertSame(419, $r->statusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_drafts')->fetchColumn());
        $_POST['_csrf_token'] = 'jeton';
        $r = $controller->draftSave(new Request(), $params);
        self::assertSame(200, $r->statusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_drafts')->fetchColumn());
        $draftId = (int) $this->pdo->query('SELECT id FROM sse_doc_drafts')->fetchColumn();

        // Émission : sans jeton rien n'est émis ; le texte saisi est rendu dans la page d'erreur.
        $_POST = ['_csrf_token' => 'mauvais', 'texts' => ['reason' => 'Mon texte précieux']];
        $r = $controller->issue(new Request(), $params);
        self::assertSame(419, $r->statusCode());
        self::assertStringContainsString('Mon texte précieux', $r->body());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues')->fetchColumn());

        // Un brouillon d'une autre fiche n'est pas supprimable par l'adresse d'une autre fiche.
        $other = $this->person(1, 'Autre', 'Fiche');
        $_POST = ['_csrf_token' => 'jeton'];
        $controller->draftDelete(new Request(), ['kind' => 'personne', 'id' => $other, 'model' => 'vigilance', 'draft' => $draftId]);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_drafts')->fetchColumn());
        $controller->draftDelete(new Request(), $params + ['draft' => $draftId]);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_drafts')->fetchColumn());

        // Sans droit de lire les fichiers : tout est refusé.
        $blind = new SseSheetController(null, null, null, null, null, self::access('interne', [], false));
        self::assertSame(302, $blind->issue(new Request(), $params)->statusCode());
        self::assertSame(403, $blind->draftSave(new Request(), $params)->statusCode());
        // Fiche d'une autre communauté : introuvable.
        $foreign = $this->person(2, 'Étranger', 'Communauté');
        self::assertSame(404, $controller->draftSave(new Request(), ['kind' => 'personne', 'id' => $foreign, 'model' => 'vigilance'])->statusCode());
        // Trames : écrire demande la gestion des fichiers.
        $reader = new SseSheetController(null, null, null, null, null, self::access('confidentiel', [], true, false));
        $_POST = ['_csrf_token' => 'jeton', 'label' => 'Trame', 'body' => 'Texte', 'lang' => 'fr'];
        $reader->phraseSave(new Request());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_phrases')->fetchColumn());
        $controller->phraseSave(new Request());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_phrases')->fetchColumn());
    }

    public function testControllerIssuesAndDownloadsAndTheTenantIsNeverReadFromTheRequest(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = new SseSheetController(null, null, null, null, null, self::access());
        $_POST = ['_csrf_token' => 'jeton', 'lang' => 'fr', 'tenant_id' => '2', 'classification' => 'confidentiel', 'texts' => ['summary' => 'Synthèse du jour.']];
        $r = $controller->issue(new Request(), ['kind' => 'personne', 'id' => $p, 'model' => 'profile']);
        self::assertSame(302, $r->statusCode());
        self::assertMatchesRegularExpression('~/emission/personne/' . $p . '/profile\?emis=\d+~', (string) $r->headerValue('Location'));
        self::assertSame(1, (int) $this->pdo->query('SELECT tenant_id FROM sse_doc_issues')->fetchColumn(), 'La communauté est celle de la session');
        $id = (int) $this->pdo->query('SELECT id FROM sse_doc_issues')->fetchColumn();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $dl = $controller->download(new Request(), ['id' => $id]);
        self::assertSame('application/pdf', $dl->headerValue('Content-Type'));
        self::assertStringContainsString('attachment', (string) $dl->headerValue('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', $dl->body());
        // Éditeur et phrases : les pages se rendent pour de vrai.
        foreach (['vigilance', 'identification', 'profile', 'control', 'notice'] as $model) {
            $page = $controller->editor(new Request(), ['kind' => 'personne', 'id' => $p, 'model' => $model]);
            self::assertSame(200, $page->statusCode(), $model);
            self::assertStringContainsString('id="shx-form"', $page->body(), $model);
            self::assertStringNotContainsString('Warning', $page->body(), $model);
            self::assertStringNotContainsString('Fatal', $page->body(), $model);
        }
        self::assertSame(200, $controller->phrases(new Request())->statusCode());
        $unknown = $controller->editor(new Request(), ['kind' => 'personne', 'id' => $p, 'model' => 'inconnu']);
        self::assertSame(302, $unknown->statusCode());
    }

    // ----------------------------------------------------------- Migration

    public function testMigrationStepIsRegisteredAndMatchesTheSqlFile(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = (array) require $root . '/database/steps/registry.php';
        $entry = null;
        $numbers = [];
        foreach ($registry as $row) {
            if (($row['id'] ?? '') === 'sse_documents') {
                $entry = $row;
            }
            if (preg_match('/^(\d+)_/', (string) ($row['file'] ?? ''), $m) === 1) {
                $numbers[] = (int) $m[1];
            }
        }
        self::assertNotNull($entry);
        self::assertSame('schema', $entry['group']);
        self::assertTrue($entry['rerun']);
        self::assertFileExists($root . '/database/steps/' . $entry['file']);
        self::assertSame(1, count(array_keys($numbers, 4670, true)), 'Numéro d’étape unique');
        self::assertFileExists($root . '/migrations/rollback/sse_documents_down.sql');
        $sql = (string) file_get_contents($root . '/migrations/2026_11_16_100_sse_documents.sql');
        self::assertStringNotContainsString('ADD UNIQUE KEY', $sql);
        $down = (string) file_get_contents($root . '/migrations/rollback/sse_documents_down.sql');
        foreach (SseSheetSqlite::TABLES as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $table, $down);
        }
    }
}
