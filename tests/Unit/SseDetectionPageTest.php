<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseDetectionController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\SseDetectionRepository;
use App\Services\Sse\Detection\SseDetectionService;
use App\Support\SsePortalNav;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\IntelIntakeSqlite;
use Tests\Support\SseDetectionSqlite;

/**
 * Page « Détections Cerbère » du bureau SSE, rendue par le VRAI contrôleur et la vraie coque sur une base sqlite : règles par
 * thème avec état, poids, exclusions, déclenchements sur 30 jours et taux de rejet ; « Testez un texte » (page et réponse au
 * script) ; réglage d'une règle et lexique livré rétabli (droit « gérer », jeton, débit, journal) ; lecteur sans droit de
 * gestion ; sans droit de lecture ; base sans l'étape. Règles d'interface : onglets, fenêtres, pas de petit libellé au-dessus
 * d'un titre, pas du design system rejeté, textes de 13 px, aucune mention de jeu. Fichiers lus sans dépendre des fins de ligne.
 */
final class SseDetectionPageTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1, 'sse_tenant_id' => 1, 'callsign' => 'ANALYSTE'];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/atak/sse/detections';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        Csrf::token();
        $this->pdo = IntelIntakeSqlite::open();
        IntelIntakeSqlite::seed($this->pdo, time());
        IntelIntakeSqlite::insert($this->pdo, 'sse_field_notes', [
            'tenant_id' => 1, 'reference_code' => 'FR-2026-000077', 'note_kind' => 'FRT', 'themes' => '["ARMEMENT"]',
            'title' => SseDetectionEngineTest::CAPTURE_TITLE, 'body' => SseDetectionEngineTest::CAPTURE_BODY,
            'status' => 'transmise', 'created_at' => gmdate('Y-m-d H:i:s', time() - 3600),
        ]);
        SseDetectionRepository::forgetReady();
    }

    protected function tearDown(): void
    {
        IntelIntakeSqlite::close();
        SseDetectionRepository::forgetReady();
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    private static function read(string $relative): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative));
    }

    private function controller(bool $manage = true, bool $read = true, bool $guest = false, bool $throttled = false): SseDetectionController
    {
        $rights = static fn (): array => ['tenant' => 1, 'user' => $manage ? 7 : 9, 'guest' => $guest, 'read' => $read, 'manage' => $manage, 'label' => $manage ? 'ANALYSTE' : 'LECTEUR'];

        return new SseDetectionController(new SseDetectionService($this->pdo), $rights, static fn (): bool => $throttled);
    }

    private static function own(string $html): string
    {
        $start = strpos($html, '<div class="cdx-page">');
        self::assertNotFalse($start, 'la page est rendue');
        $end = strpos($html, '</main>', (int) $start);

        return substr($html, (int) $start, ($end === false ? strlen($html) : $end) - (int) $start);
    }

    private function page(bool $manage = true): string
    {
        $res = $this->controller($manage)->index(new Request(), []);
        self::assertSame(200, $res->statusCode());

        return self::own(str_replace("\r\n", "\n", $res->body()));
    }

    public function testManagerSeesRulesByThemeWithFiguresTabsAndModalForms(): void
    {
        SseDetectionSqlite::install($this->pdo);
        // Une mauvaise détection notée sur R-14 compte dans son taux de rejet.
        (new SseDetectionRepository($this->pdo))->recordDecision(1, 'fiche', 1, 'theme', 'SECUR', ['R-14'], 58, 'rejected', 'Mot pris dans un autre sens', ['id' => 7, 'label' => 'ANALYSTE'], 'x');
        $html = $this->page();
        foreach (['<h1>Détections Cerbère</h1>', 'Règles', 'Testez un texte', 'Historique', 'Thèmes', 'Urgence', 'Type de fiche', 'Faux amis vérifiés', 'Rétablir le lexique livré'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertSame(3, substr_count($html, 'role="tab"'));
        self::assertStringContainsString('id="regle-R-14"', $html);
        self::assertSame(1, preg_match('~id="regle-R-14".*?taux de rejet <strong>100 %</strong>~s', $html));
        self::assertSame(1, preg_match('~id="regle-R-14".*?<strong>1</strong> déclenchement sur 30 jours~s', $html), 'la fiche du jour déclenche R-14');
        self::assertSame(1, preg_match('~id="regle-R-14".*?Mot pris dans un autre sens~s', $html));
        self::assertSame(1, preg_match('~id="regle-R-12".*?Exclusions : barrage routier~s', $html));
        self::assertStringContainsString('data-cdx-open="cdx-dlg-rule"', $html);
        self::assertSame(1, preg_match('~<dialog class="cdx-dialog" id="cdx-dlg-rule" aria-labelledby="cdx-dlg-rule-title">~', $html));
        self::assertStringContainsString('id="cdx-dlg-reset"', $html);
        self::assertSame(count(\App\Services\Sse\Detection\SseDetectionLexicon::shipped()['rules']), substr_count($html, 'data-cdx-fill='), 'chaque règle se règle dans la fenêtre');
    }

    public function testReaderCanTestButNotEditAndNoReadRightIsRedirected(): void
    {
        SseDetectionSqlite::install($this->pdo);
        $html = $this->page(false);
        self::assertStringContainsString('Lecture seule', $html);
        self::assertStringNotContainsString('cdx-dlg-rule', $html);
        self::assertStringNotContainsString('Rétablir le lexique livré', $html);
        self::assertStringContainsString('name="text"', $html);

        $res = $this->controller(false, false)->index(new Request(), []);
        self::assertSame(302, $res->statusCode());
        self::assertStringContainsString('atak/sse/accueil', (string) $res->headerValue('Location'));

        // Écrire sans le droit « gérer » : refusé, rien n'est enregistré.
        $_POST = ['_csrf_token' => Csrf::token(), 'rule_id' => 'R-14', 'enabled' => '1', 'weight' => '20', 'terms' => 'sabotage'];
        $this->controller(false)->saveRule(new Request(), []);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_detection_rule_settings')->fetchColumn());
        $this->controller(true, true, true)->saveRule(new Request(), []);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_detection_rule_settings')->fetchColumn(), 'jamais en session invitée');
    }

    public function testLiveTestShowsDetectionsWithHighlightsOnThePageAndForTheScript(): void
    {
        $_POST = ['_csrf_token' => Csrf::token(), 'text' => SseDetectionEngineTest::CAPTURE_BODY . ' Pas de prise d’otage.', 'kind' => 'FRT'];
        $res = $this->controller()->test(new Request(), []);
        self::assertSame(200, $res->statusCode());
        $html = self::own(str_replace("\r\n", "\n", $res->body()));
        self::assertStringContainsString('aria-selected="true" data-cdx-tab="tester"', $html);
        self::assertStringContainsString('Thème : Sécurité / Protection', $html);
        self::assertStringContainsString('<mark>sabotage</mark>', $html);
        self::assertStringNotContainsString('Urgence : Critique', $html);
        self::assertStringContainsString('Écarté par les garde-fous', $html);
        self::assertStringContainsString('négation', $html);

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'fetch';
        $json = json_decode($this->controller()->test(new Request(), [])->body(), true);
        self::assertIsArray($json);
        self::assertStringContainsString('<mark>sabotage</mark>', $json['html']);
        self::assertStringNotContainsString('<html', $json['html']);

        // Sans jeton : rien n'est lu.
        $_POST['_csrf_token'] = 'faux';
        self::assertSame(419, $this->controller()->test(new Request(), [])->statusCode());
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        self::assertSame(302, $this->controller()->test(new Request(), [])->statusCode());
    }

    public function testSavingARuleAppliesAtOnceIsLoggedAndCanBeReset(): void
    {
        SseDetectionSqlite::install($this->pdo);
        $service = new SseDetectionService($this->pdo);
        $before = $service->liveTest(1, 'Sabotage du groupe électrogène.');
        self::assertContains('SECUR', array_column($before['detections'], 'value'));

        $_POST = ['_csrf_token' => Csrf::token(), 'rule_id' => 'R-14', 'enabled' => '1', 'weight' => '60', 'terms' => "sabotage\nsabote(e|es)", 'exclusions' => 'sabotage du groupe', 'requires' => '', 'reason' => 'Faux positif sur le groupe électrogène'];
        $res = $this->controller()->saveRule(new Request(), []);
        self::assertSame(302, $res->statusCode());
        self::assertStringContainsString('#regle-R-14', (string) $res->headerValue('Location'));
        $after = (new SseDetectionService($this->pdo))->liveTest(1, 'Sabotage du groupe électrogène.');
        self::assertNotContains('SECUR', array_column($after['detections'], 'value'), 'l’exclusion s’applique tout de suite');
        $row = $this->pdo->query("SELECT weight, exclusions_json, terms_json FROM sse_detection_rule_settings WHERE rule_id = 'R-14'")->fetch(PDO::FETCH_ASSOC);
        self::assertNull($row['weight'], 'le poids livré n’est pas recopié');
        self::assertSame('["sabotage du groupe"]', $row['exclusions_json']);
        $html = $this->page();
        self::assertSame(1, preg_match('~Règle R-14 : termes − [^<]*saboteur[^<]* ; exclusions \+ sabotage du groupe~u', html_entity_decode($html)), 'l’historique dit ce qui a changé');
        self::assertStringContainsString('Faux positif sur le groupe électrogène', $html);
        self::assertSame(1, preg_match('~id="regle-R-14".*?Réglée~s', $html));

        // Jeton faux, débit dépassé : refusés.
        $_POST['_csrf_token'] = 'faux';
        $_POST['weight'] = '10';
        $this->controller()->saveRule(new Request(), []);
        $_POST['_csrf_token'] = Csrf::token();
        $this->controller(true, true, false, true)->saveRule(new Request(), []);
        self::assertNull($this->pdo->query("SELECT weight FROM sse_detection_rule_settings WHERE rule_id = 'R-14'")->fetchColumn());

        // Rétablir : confirmation exigée, puis tous les réglages retirés et journalisés.
        $_POST = ['_csrf_token' => Csrf::token()];
        $this->controller()->reset(new Request(), []);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_detection_rule_settings')->fetchColumn());
        $_POST['confirm'] = '1';
        $this->controller()->reset(new Request(), []);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_detection_rule_settings')->fetchColumn());
        self::assertSame(['cerbere.lecture.rule', 'cerbere.lecture.reset'], $this->pdo->query("SELECT action FROM sse_audit_log WHERE action LIKE 'cerbere.lecture.%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
        self::assertStringContainsString('Lexique livré rétabli', $this->page());
    }

    public function testWithoutTheStepThePageIsReadOnlyOnTheShippedLexicon(): void
    {
        $html = $this->page();
        self::assertStringContainsString('Lexique livré, sans réglage', $html);
        self::assertStringNotContainsString('cdx-dlg-rule', $html);
        self::assertStringContainsString('id="regle-R-03"', $html);
    }

    public function testNavigationRouteAndInterfaceRules(): void
    {
        $ids = [];
        foreach (SsePortalNav::sections(true, true, false, true, true) as $s) {
            foreach ($s['items'] as $i) {
                $ids[$i['id']] = $i;
            }
        }
        self::assertSame('atak/sse/detections', $ids['cerbere-detections']['path']);
        self::assertSame('Détections Cerbère', $ids['cerbere-detections']['label']);
        $routes = self::read('routes/web.php');
        foreach (["get('/atak/sse/detections'", "post('/atak/sse/detections/tester'", "post('/atak/sse/detections/regle'", "post('/atak/sse/detections/retablir'"] as $r) {
            self::assertStringContainsString($r, $routes);
        }
        foreach (['views/atak/sse/cerbere_detections.php', 'views/atak/sse/_cerbere_detection_result.php', 'views/partials/cerbere_proposals.php', 'public/assets/css/cerbere-detections.css', 'public/assets/js/cerbere-detections.js'] as $file) {
            $src = self::read($file);
            foreach (['sse-ds', 'Archivo', 'eyebrow', 'kicker', 'fiction', 'simulation'] as $banned) {
                self::assertStringNotContainsString($banned, mb_strtolower($src) === $src ? $src : mb_strtolower($src), $file . ' : ' . $banned);
            }
            self::assertSame(0, preg_match('/class="sse-/', $src), $file . ' : classes .sse-* du design system rejeté');
        }
        $css = self::read('public/assets/css/cerbere-detections.css');
        preg_match_all('/font-size:\s*([0-9.]+)rem/', $css, $m);
        foreach ($m[1] as $size) {
            self::assertGreaterThanOrEqual(0.8125, (float) $size, 'texte de 13 px au moins');
        }
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertStringContainsString('@media (max-width: 720px)', $css);
        $js = self::read('public/assets/js/cerbere-detections.js');
        self::assertStringContainsString("ev.key !== 'Tab'", $js, 'focus gardé dans la fenêtre');
        self::assertStringContainsString('opener.focus()', $js, 'retour au bouton');
        self::assertSame(0, preg_match('/[àâéèêëîïôùûç]/u', preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $js) ?? ''), 'aucun texte dans le script');
    }
}
