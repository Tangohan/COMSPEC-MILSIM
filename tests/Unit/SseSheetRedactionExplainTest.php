<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseSheetController;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\SseSheetRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseSheetAccess;
use App\Services\Sse\SseSheetFactsService;
use App\Services\Sse\SseSheetReissueService;
use App\Services\Sse\SseSheetService;
use App\Services\Sse\SseWantedAccess;
use App\Support\SseRedactionExplain;
use App\Support\TrainingCertificatePdfEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfText;
use Tests\Support\SseSheetSqlite;

/**
 * Documents caviardés expliqués : le PDF dit combien de passages sont masqués, quelle catégorie cache chaque barre et quel niveau la
 * rend lisible (jamais la valeur ni sa longueur) ; la page des documents émis montre la pastille et la fenêtre d'explication ; la
 * réémission à un niveau supérieur respecte l'habilitation, le motif, le lien avec l'ancien document, l'audit, la communauté et le
 * jeton CSRF ; l'éditeur montre avant l'émission ce qui sera masqué.
 */
final class SseSheetRedactionExplainTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    private const SECRETS = ['Øksnevad', 'Zoë', 'Kavala', 'Pyrgos', 'La Pie', '1987-03-12'];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        \App\Core\Csrf::token();
        $this->pdo = SseSheetSqlite::open();
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sse_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, actor_user_id INTEGER, actor_label TEXT, action TEXT, object_type TEXT, object_id INTEGER, object_uuid TEXT, reason TEXT, before_json TEXT, after_json TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
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
    private function subject(int $personId, ?SseSheetAccess $access = null): array
    {
        $s = (new SseSheetFactsService())->subject(1, 'person', $personId, $access ?? self::access('tres_restreint'));
        self::assertNotNull($s);

        return $s;
    }

    /** Émet un document (habilitation Très secret) et rend son PDF. @return array{id: int, ref: string, bytes: string} */
    private function issue(int $person, string $model, string $level, string $lang = 'fr'): array
    {
        $a = self::access('tres_restreint');
        $res = (new SseSheetService())->issue(1, $this->subject($person), $model, ['lang' => $lang, 'classification' => $level, 'faces' => 'auto'], $a);
        self::assertTrue($res['ok'], implode(' | ', $res['errors']));

        return ['id' => (int) $res['id'], 'ref' => (string) $res['ref'], 'bytes' => (string) $res['bytes']];
    }

    private function snapshot(int $id): array
    {
        return (array) json_decode((string) $this->pdo->query('SELECT snapshot_json FROM sse_doc_issues WHERE id = ' . $id)->fetchColumn(), true);
    }

    // ------------------------------------------------------------ Le PDF

    public function testTheBlockExplainsTheWithheldPassagesAndIsAbsentWhenNothingIsMasked(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        $text = PdfText::of($c['bytes']);
        self::assertStringContainsString('CERTAINS PASSAGES ONT ÉTÉ MASQUÉS', $text);
        self::assertStringContainsString('PORTIONS WITHHELD', $text);
        self::assertStringContainsString('Confidentiel (C)', $text);
        self::assertStringContainsString('(a) Identité : requiert S', $text);
        self::assertStringContainsString('(b) Biométrie : requiert S', $text);
        self::assertStringContainsString('demander la communication à un niveau supérieur', $text);
        self::assertStringContainsString($c['ref'], $text);
        // Légende en bas de page.
        self::assertStringContainsString('PASSAGES MASQUÉS : (a) Identité', $text);

        // Aucune donnée masquée : ni bloc ni légende.
        foreach (['confidentiel', 'tres_restreint'] as $level) {
            $open = PdfText::of($this->issue($p, 'identification', $level)['bytes']);
            self::assertStringNotContainsString('PORTIONS WITHHELD', $open, $level);
            self::assertStringNotContainsString('PASSAGES MASQUÉS', $open, $level);
            self::assertStringNotContainsString('requiert', $open, $level);
        }
    }

    public function testCategoriesLevelsAndCountsAreExactAndStoredWithTheIssue(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        // Diffusion « interne » : identité (S), lieu (C), biométrie (S) et horodatage (C) sont masqués.
        $i = $this->issue($p, 'profile', 'interne');
        $snap = $this->snapshot($i['id']);
        self::assertArrayHasKey('redaction', $snap);
        $by = $snap['redaction']['by'];
        self::assertSame(['identite', 'lieu', 'biometrie', 'horodatage'], array_keys($by));
        self::assertSame(array_sum($by), $snap['redaction']['total']);
        self::assertSame(3, $by['biometrie']);
        $text = PdfText::of($i['bytes']);
        foreach (['(a) Identité : requiert S', '(b) Lieu : requiert C', '(c) Biométrie : requiert S', '(d) Horodatage : requiert C'] as $line) {
            self::assertStringContainsString($line, $text, $line);
        }
        self::assertStringContainsString('(' . $by['identite'] . ')', $text);
        self::assertStringContainsString($snap['redaction']['total'] . ' passages masqués', $text);
        self::assertStringContainsString('Information non classifiée contrôlée (CUI)', $text);

        // À C : seulement identité et biométrie ; la page lit le décompte stocké.
        $c = $this->issue($p, 'identification', 'encadrement');
        $x = SseRedactionExplain::forIssue(['classification' => 'encadrement', 'snapshot' => $this->snapshot($c['id']), 'texts' => []]);
        self::assertTrue($x['exact']);
        self::assertSame(['identite', 'biometrie'], array_keys($x['by']));
    }

    public function testNeitherTheMaskedValueNorItsLengthReachesThePdfOrTheExplanation(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        $text = PdfText::of($c['bytes']);
        foreach (['Øksnevad', 'Zoë', 'Élodie', 'La Pie', '1987-03-12', 'Kavala'] as $secret) {
            self::assertStringNotContainsString($secret, $text, $secret);
        }
        // Lieu visible à C : seul ce qui est lisible apparaît.
        self::assertStringContainsString('Pyrgos', $text);
        // Les barres sont de largeur fixe : deux noms de longueurs très différentes donnent le même contenu de bloc.
        $this->pdo->exec("UPDATE sse_persons SET last_name = 'Li', first_name = 'Ao', alias = 'X', birth_place = 'Ys' WHERE id = " . $p);
        $short = PdfText::of($this->issue($p, 'identification', 'encadrement')['bytes']);
        $strip = static fn (string $t): string => (string) preg_replace('/FI-\d{4}-\d{4}/', 'REF', (string) preg_replace('/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2} UTC|DTG \S+ \S+ \S+/', '', $t));
        self::assertSame(
            substr_count($strip($text), 'requiert'),
            substr_count($strip($short), 'requiert')
        );
        self::assertStringContainsString('(a) Identité : requiert S (', $short);
    }

    public function testEnglishDocumentsExplainInEnglishAndNoDocumentMentionsFiction(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        foreach (['identification', 'vigilance', 'profile', 'control'] as $model) {
            foreach (['fr', 'en'] as $lang) {
                $pdf = $this->issue($p, $model, 'encadrement', $lang)['bytes'];
                self::assertNull(PdfText::fictionMention($pdf), "$model $lang");
                $text = PdfText::of($pdf);
                self::assertStringContainsString('PORTIONS WITHHELD', $text, "$model $lang");
                if ($lang === 'en') {
                    self::assertStringContainsString('requires S', $text, $model);
                    self::assertStringContainsString('request release at a higher level', $text, $model);
                    self::assertStringContainsString('Confidential (C)', $text, $model);
                    self::assertStringNotContainsString('requiert', $text, $model);
                    self::assertStringContainsString('(a) Identity: requires S', $text, $model);
                } else {
                    self::assertStringContainsString('requiert S', $text, $model);
                }
            }
        }
        $root = dirname(__DIR__, 2) . '/app/';
        foreach (['Services/Sse/SseSheetPdfRedaction.php', 'Support/SseRedactionExplain.php', 'Services/Sse/SseSheetReissueService.php', 'Services/Sse/SseSheetIssueView.php'] as $file) {
            $source = (string) file_get_contents($root . $file);
            foreach (['Document de fiction', 'Fictional document', 'role-play', 'jeu de rôle', 'interpol', 'europol', 'gendarmerie', 'scotland yard'] as $word) {
                self::assertStringNotContainsStringIgnoringCase($word, $source, $file);
            }
        }
    }

    public function testBarsCarryTheirCategoryAndOlderDocumentsAreReadFromTheirFields(): void
    {
        $facts = new SseSheetFactsService();
        $raw = $facts->raw(1, $this->subject($this->person()), self::access('tres_restreint'), 'fr');
        $f = $facts->mask($raw, 'encadrement')['facts'];
        self::assertSame('identite', SseRedactionExplain::categoryOfRun((string) $f['last_name']));
        self::assertSame('biometrie', SseRedactionExplain::categoryOfRun((string) $f['bio']['prints']));
        self::assertSame(['identite' => 7, 'biometrie' => 3], SseRedactionExplain::scanData($f), 'noms, naissance, nationalité, alias… et trois relevés');
        // Une suite d'un ancien document (quatre blocs ou plus, multiple de quatre) n'a pas de catégorie.
        self::assertSame('', SseRedactionExplain::categoryOfRun(SseRedactionService::bar('Zoë')));
        $old = $f;
        $old['last_name'] = SseRedactionService::bar('Øksnevad-Çelik');
        $old['bio']['prints'] = SseRedactionService::bar('x');
        $re = SseRedactionExplain::retag($old);
        self::assertSame('identite', SseRedactionExplain::categoryOfRun((string) $re['last_name']));
        self::assertSame('biometrie', SseRedactionExplain::categoryOfRun((string) $re['bio']['prints']));
        // Décompte d'un document sans `redaction` : relu sur les données et textes imprimés.
        $x = SseRedactionExplain::forIssue(['classification' => 'encadrement', 'snapshot' => ['data' => ['facts' => $old]], 'texts' => ['summary' => 'Cible ' . SseRedactionService::bar('Zoë') . ' vue.']]);
        self::assertFalse($x['exact']);
        self::assertSame(1, $x['by']['']);
        self::assertSame(7, $x['by']['identite']);
    }

    public function testAnOlderIssueKeepsItsContentAndIsExplainedWithoutBeingRewritten(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        // On simule un document émis avant cette version : barres sans catégorie, pas de décompte stocké.
        $snap = $this->snapshot($c['id']);
        unset($snap['redaction']);
        $json = (string) json_encode($snap, JSON_UNESCAPED_UNICODE);
        $json = (string) preg_replace_callback('/█+/u', static fn (array $m): string => str_repeat('█', 8), $json);
        $this->pdo->prepare('UPDATE sse_doc_issues SET snapshot_json = ? WHERE id = ?')->execute([$json, $c['id']]);
        $before = (string) $this->pdo->query('SELECT snapshot_json || texts_json FROM sse_doc_issues WHERE id = ' . $c['id'])->fetchColumn();

        $dl = (new SseSheetService())->reissue(1, $c['id'], self::access());
        self::assertTrue($dl['ok']);
        $text = PdfText::of((string) $dl['bytes']);
        self::assertStringContainsString('CERTAINS PASSAGES ONT ÉTÉ MASQUÉS', $text);
        self::assertStringContainsString('(a) Identité : requiert S', $text);
        self::assertStringContainsString('(b) Biométrie : requiert S', $text);
        foreach (['Øksnevad', 'Zoë', 'Kavala', 'La Pie', '1987-03-12'] as $s) {
            self::assertStringNotContainsString($s, $text);
        }
        self::assertSame($before, (string) $this->pdo->query('SELECT snapshot_json || texts_json FROM sse_doc_issues WHERE id = ' . $c['id'])->fetchColumn(), 'Le re-téléchargement n’écrit rien');
    }

    // ------------------------------------------------------------ La page

    private function page(SseSheetAccess $access, int $person, string $level = ''): string
    {
        $_GET = $level !== '' ? ['classification' => $level] : [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $r = (new SseSheetController(null, null, null, null, null, $access))->editor(new Request(), ['kind' => 'personne', 'id' => $person, 'model' => 'identification']);
        self::assertSame(200, $r->statusCode());

        return $r->body();
    }

    /** @return list<string> les fenêtres <dialog> de la page */
    private static function dialogs(string $html): array
    {
        preg_match_all('~<dialog\b.*?</dialog>~s', $html, $m);

        return $m[0];
    }

    public function testTheIssuedDocumentsShowTheLevelPillTheMaskedPillAndTheExplanationWindow(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        $this->issue($p, 'profile', 'confidentiel');
        $html = $this->page(self::access('confidentiel'), $p);
        $total = $this->snapshot($c['id'])['redaction']['total'];
        self::assertStringContainsString('class="shx-lvl"', $html);
        self::assertStringContainsString('Confidentiel (C)', $html);
        self::assertStringContainsString('--lv-bg:rgb(0,51,160)', $html, 'couleur du niveau C de l’échelle américaine');
        self::assertStringContainsString($total . ' passage(s) masqué(s)', $html);
        self::assertStringContainsString('data-shx-open="shx-explain-' . $c['id'] . '"', $html);
        self::assertStringContainsString('Pourquoi ces passages sont masqués', $html);
        self::assertStringContainsString('Le caviardage dépend du niveau de diffusion choisi à l’émission, pas de la classification de la personne.', $html);
        self::assertStringContainsString('Requiert le niveau <strong>S</strong>', $html);
        self::assertStringContainsString('Aucun passage masqué', $html, 'le document sans barre n’a pas de pastille d’alerte');
        // La fenêtre ne contient que des catégories, des niveaux et des nombres.
        $dialogs = implode("\n", self::dialogs($html));
        self::assertStringContainsString('Identité', $dialogs);
        self::assertStringContainsString('Biométrie', $dialogs);
        foreach (self::SECRETS as $s) {
            self::assertStringNotContainsString($s, $dialogs, $s);
        }
        self::assertStringNotContainsString('█', $dialogs);
        // Aucun design system « SSE DS » dans la fenêtre.
        self::assertStringNotContainsString('sse-ds', $dialogs);
    }

    public function testTheReissueActionIsLimitedToTheOperatorsClearance(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        // Habilité Secret : action et fenêtre, niveaux au-dessus du document et jusqu’à l’habilitation.
        $html = $this->page(self::access('confidentiel'), $p);
        self::assertStringContainsString('data-shx-open="shx-reissue-' . $c['id'] . '"', $html);
        self::assertStringContainsString('action="' . url('atak/sse/identites/emissions/' . $c['id'] . '/reemettre') . '"', $html);
        self::assertStringContainsString('name="reason"', $html);
        self::assertStringContainsString('required', $html);
        preg_match('~<select[^>]*data-shx-level.*?</select>~s', $html, $sel);
        self::assertStringContainsString('value="confidentiel"', $sel[0]);
        self::assertStringNotContainsString('value="tres_restreint"', $sel[0], 'jamais au-dessus de l’habilitation');
        self::assertStringNotContainsString('value="encadrement"', $sel[0], 'seulement au-dessus du niveau du document');
        // Habilité Confidentiel (C) seulement : pas de bouton, le niveau à demander est dit.
        $low = $this->page(self::access('encadrement'), $p);
        self::assertStringNotContainsString('/reemettre', $low);
        self::assertStringNotContainsString('data-shx-open="shx-reissue-', $low);
        self::assertStringContainsString('Niveau requis : S — demander à un responsable habilité', $low);
        // Pas d’habilitation Secret ni Très secret : le document Très secret reste fermé.
        $this->issue($p, 'profile', 'tres_restreint');
        $locked = $this->page(self::access('confidentiel'), $p);
        self::assertStringContainsString('Classé au-dessus de votre habilitation', $locked);
    }

    public function testTheEditorShowsWhatWillBeMaskedBeforeIssuing(): void
    {
        $p = $this->person();
        $html = $this->page(self::access('confidentiel'), $p, 'encadrement');
        self::assertStringContainsString('Ce qui sera masqué à ce niveau', $html);
        self::assertStringContainsString('data-level="encadrement"', $html);
        // À C : identité (9 champs) et biométrie (3) masquées, avertissement car le niveau est sous celui des données les plus sensibles.
        preg_match('~data-level="encadrement"[^>]*>(.*?)</div>\s*<div class="shx-redact__lvl"~s', $html, $block);
        $counts = SseRedactionExplain::fieldCounts((new SseSheetFactsService())->raw(1, $this->subject($p), self::access('confidentiel'), 'fr'));
        self::assertSame(3, $counts['biometrie']);
        self::assertGreaterThan(3, $counts['identite']);
        self::assertStringContainsString(($counts['identite'] + $counts['biometrie']) . ' champ(s)', $block[1]);
        self::assertStringContainsString('inférieur à celui des données les plus sensibles de la fiche (S)', $block[1]);
        self::assertStringContainsString('requiert S · ' . $counts['identite'] . ' champ(s)', preg_replace('/\s+/', ' ', $block[1]));
        // À S : rien n’est masqué, pas d’avertissement.
        preg_match('~data-level="confidentiel"[^>]*>(.*?)</section>~s', $html, $open);
        self::assertStringContainsString('Rien ne sera masqué', $open[1]);
        self::assertStringNotContainsString('inférieur à celui', $open[1]);
        // Le niveau par défaut est expliqué, sans changer la règle ni la valeur par défaut.
        self::assertStringContainsString('Les émissions en un clic utilisent le niveau par défaut de la communauté', $html);
        foreach (self::SECRETS as $s) {
            self::assertStringNotContainsString($s, implode("\n", array_map(static fn (string $b): string => $b, [$block[1]])), $s);
        }
    }

    public function testTheOneClickMenuSaysWhatTheDefaultLevelHides(): void
    {
        $p = $this->person();
        $menu = \App\Services\Sse\SseSheetMenu::build(1, $this->subject($p), 'personne', self::access('confidentiel'), false);
        self::assertSame('encadrement', $menu['default_level'], 'défaut de la communauté, ramené à l’habilitation');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $html = (static function () use ($menu): string {
            ob_start();
            $sheetMenu = $menu;
            require dirname(__DIR__, 2) . '/views/atak/sse/_sheet_menu.php';

            return (string) ob_get_clean();
        })();
        self::assertStringContainsString('En un clic, le document est diffusé au niveau <strong>C</strong>', $html);
        self::assertStringContainsString('Passages masqués : Identité, Biométrie, Source.', preg_replace('/\s+/', ' ', $html));
    }

    // -------------------------------------------------------- La réémission

    public function testReissueNeedsAHigherLevelWithinTheClearanceAndAReason(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        $svc = new SseSheetReissueService();
        $reason = 'Remise au partenaire habilité.';
        // Au-dessus de l’habilitation : refusé, rien n’est émis.
        $r = $svc->reissue(1, $c['id'], 'tres_restreint', $reason, self::access('confidentiel'));
        self::assertFalse($r['ok']);
        self::assertSame('ceiling', $r['error']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues')->fetchColumn());
        // Même niveau ou niveau inférieur : refusé.
        foreach (['encadrement', 'interne', 'inconnu'] as $bad) {
            self::assertSame('level', $svc->reissue(1, $c['id'], $bad, $reason, self::access('tres_restreint'))['error'], $bad);
        }
        // Motif obligatoire.
        foreach (['', '   ', 'ok'] as $why) {
            self::assertSame('reason', $svc->reissue(1, $c['id'], 'confidentiel', $why, self::access('confidentiel'))['error']);
        }
        // Un opérateur Confidentiel (C) ne peut rien réémettre plus haut que lui.
        self::assertSame('ceiling', $svc->reissue(1, $c['id'], 'confidentiel', $reason, self::access('encadrement'))['error']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues')->fetchColumn());
        self::assertSame([], SseSheetReissueService::levelsAbove('tres_restreint', self::access('tres_restreint')));
        self::assertSame(['confidentiel'], SseSheetReissueService::levelsAbove('encadrement', self::access('confidentiel')));
    }

    public function testReissueCreatesALinkedDocumentKeepsTheOldOneAndIsAudited(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        $oldRow = (string) $this->pdo->query('SELECT snapshot_json || texts_json || classification || ref FROM sse_doc_issues WHERE id = ' . $c['id'])->fetchColumn();

        $r = (new SseSheetReissueService())->reissue(1, $c['id'], 'confidentiel', "  Remise   au partenaire\nhabilité.  ", self::access('confidentiel'));
        self::assertTrue($r['ok'], $r['message']);
        self::assertSame($c['ref'], $r['old_ref']);
        self::assertNotSame($c['ref'], $r['ref']);
        // L’ancien document est intact.
        self::assertSame($oldRow, (string) $this->pdo->query('SELECT snapshot_json || texts_json || classification || ref FROM sse_doc_issues WHERE id = ' . $c['id'])->fetchColumn());
        // Le nouveau porte son lien, son niveau et un PDF lisible sans barre.
        $new = (new SseSheetRepository())->findIssue(1, $r['id']);
        self::assertSame('confidentiel', $new['classification']);
        self::assertSame($c['id'], $new['snapshot']['reissue']['of']);
        self::assertSame($c['ref'], $new['snapshot']['reissue']['ref']);
        self::assertSame('Remise au partenaire habilité.', $new['snapshot']['reissue']['reason']);
        self::assertSame(0, $new['snapshot']['redaction']['total']);
        $dl = (new SseSheetService())->reissue(1, $r['id'], self::access('confidentiel'));
        $text = PdfText::of((string) $dl['bytes']);
        self::assertStringContainsString('Øksnevad', $text);
        self::assertStringNotContainsString('PORTIONS WITHHELD', $text);
        // Audit.
        $audit = $this->pdo->query("SELECT * FROM sse_audit_log WHERE action = 'document.reissued_higher'")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $audit);
        self::assertSame($c['id'], (int) $audit[0]['object_id']);
        self::assertSame('Remise au partenaire habilité.', $audit[0]['reason']);
        self::assertSame(7, (int) $audit[0]['actor_user_id']);
        self::assertStringContainsString($c['ref'], (string) $audit[0]['before_json']);
        self::assertStringContainsString($r['ref'], (string) $audit[0]['after_json']);
        // La liste relie les deux documents.
        $html = $this->page(self::access('confidentiel'), $p);
        self::assertStringContainsString('Réémission de', $html);
        self::assertStringContainsString('Réémis sous', $html);
    }

    public function testReissueKeepsHandTypedTextsAndNeverLeaksIntoTheNewTextsWhatWasMasked(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $a = self::access('tres_restreint');
        $res = (new SseSheetService())->issue(1, $this->subject($p), 'profile', ['lang' => 'fr', 'classification' => 'encadrement', 'texts' => ['summary' => 'Synthèse écrite à la main, sans donnée sensible.']], $a);
        self::assertTrue($res['ok'], implode(' | ', $res['errors']));
        $r = (new SseSheetReissueService())->reissue(1, (int) $res['id'], 'confidentiel', 'Besoin du nom complet.', self::access('confidentiel'));
        self::assertTrue($r['ok'], $r['message']);
        $new = (new SseSheetRepository())->findIssue(1, $r['id']);
        self::assertSame('Synthèse écrite à la main, sans donnée sensible.', $new['texts']['summary']);
        foreach ($new['texts'] as $t) {
            self::assertStringNotContainsString('█', (string) $t);
        }
    }

    public function testReissueIsScopedToTheCommunityAndTheCsrfTokenIsRequired(): void
    {
        self::requirePdfEngine();
        $p = $this->person();
        $c = $this->issue($p, 'identification', 'encadrement');
        // Document d’une autre communauté : introuvable, rien n’est émis.
        $foreign = $this->person(2);
        $other = (new SseSheetRepository())->createIssue(2, [
            'person_id' => $foreign, 'identity_id' => null, 'model' => 'identification', 'lang' => 'fr', 'classification' => 'encadrement', 'faces' => 'auto', 'texts' => [], 'snapshot' => ['params' => []],
            'sensitive' => [], 'user_id' => 7, 'label' => 'AUTRE',
        ], ['ref' => 'FI-2026-0001', 'year' => 2026, 'seq' => 1]);
        $svc = new SseSheetReissueService();
        self::assertSame('notfound', $svc->reissue(1, $other, 'confidentiel', 'Motif valable.', self::access('confidentiel'))['error']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues WHERE tenant_id = 1 AND id <> ' . $c['id'])->fetchColumn());

        $controller = new SseSheetController(null, null, null, null, null, self::access('confidentiel'));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        // Sans jeton : rien n’est émis.
        $_POST = ['_csrf_token' => 'mauvais', 'classification' => 'confidentiel', 'reason' => 'Motif valable.'];
        $r = $controller->reissueHigher(new Request(), ['id' => $c['id']]);
        self::assertSame(302, $r->statusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues WHERE tenant_id = 1')->fetchColumn());
        // Document d’une autre communauté par l’adresse : refusé, même avec le jeton.
        $_POST['_csrf_token'] = 'jeton';
        $controller->reissueHigher(new Request(), ['id' => $other]);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues WHERE tenant_id = 1')->fetchColumn());
        // Sans motif : refusé.
        $_POST['reason'] = '';
        $controller->reissueHigher(new Request(), ['id' => $c['id']]);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues WHERE tenant_id = 1')->fetchColumn());
        // La communauté n’est jamais lue de la requête.
        $_POST = ['_csrf_token' => 'jeton', 'classification' => 'confidentiel', 'reason' => 'Motif valable.', 'tenant_id' => '2'];
        $r = $controller->reissueHigher(new Request(), ['id' => $c['id']]);
        self::assertSame(302, $r->statusCode());
        self::assertStringContainsString('#documents-emis', (string) $r->headerValue('Location'));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues WHERE tenant_id = 1')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues WHERE tenant_id = 2 AND id <> ' . $other)->fetchColumn());
        // Un lecteur sans habilitation suffisante ne réémet pas par l’adresse.
        $low = new SseSheetController(null, null, null, null, null, self::access('encadrement'));
        $_POST = ['_csrf_token' => 'jeton', 'classification' => 'confidentiel', 'reason' => 'Motif valable.'];
        $low->reissueHigher(new Request(), ['id' => $c['id']]);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues WHERE tenant_id = 1')->fetchColumn());
        // Invité : jamais.
        $guest = new SseSheetController(null, null, null, null, null, new SseSheetAccess(new SseWantedAccess(true, true, true, true, 1, null, 'INVITE'), static fn (): bool => true, false, 'tres_restreint'));
        $guest->reissueHigher(new Request(), ['id' => $c['id']]);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_doc_issues WHERE tenant_id = 1')->fetchColumn());
    }

    public function testNoticeAndMissingDocumentsCannotBeReissued(): void
    {
        $svc = new SseSheetReissueService();
        self::assertSame('notfound', $svc->reissue(1, 999, 'confidentiel', 'Motif valable.', self::access('confidentiel'))['error']);
        $id = (new SseSheetRepository())->createIssue(1, [
            'person_id' => null, 'identity_id' => 3, 'model' => 'notice', 'lang' => 'fr', 'classification' => 'encadrement', 'faces' => 'auto', 'texts' => [], 'snapshot' => [],
            'sensitive' => [], 'user_id' => 7, 'label' => 'X',
        ], ['ref' => 'AVIS-1', 'year' => 2026, 'seq' => 1]);
        self::assertSame('notfound', $svc->reissue(1, $id, 'confidentiel', 'Motif valable.', self::access('confidentiel'))['error']);
    }
}
