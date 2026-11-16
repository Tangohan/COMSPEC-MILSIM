<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseIntakeController;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\DiscordBotRepository;
use App\Repositories\SseDetectionRepository;
use App\Services\Intel\IntelIntakeAdvisor;
use App\Services\Intel\IntelIntakeConversion;
use App\Services\Intel\IntelIntakeDesk;
use App\Services\Intel\IntelIntakeDiscord;
use App\Services\Intel\IntelIntakeService;
use App\Services\Sse\Detection\SseDetectionService;
use App\Services\Sse\Detection\SseLegacyKeywordDetector;
use App\Services\Sse\Detection\SseRedetection;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\IntelIntakeSqlite;
use Tests\Support\SseDetectionSqlite;

/**
 * Cerbère-Lecture sur de vraies remontées (base sqlite, tables créées par la VRAIE étape sse_detection_rules) : la fiche
 * signalée (« sabotage ») ne propose plus Terrorisme ni Critique, les propositions sont expliquées sur la carte et dans
 * « Pourquoi ? », Appliquer / Ignorer / Mauvaise détection sont gardés et journalisés, les droits et le jeton sont vérifiés,
 * une urgence Critique ne s'applique pas sans vérification, les propositions déjà appliquées ne changent jamais, mode dégradé
 * sans l'étape, re-détection en simulation puis --apply, idempotente.
 */
final class SseDetectionFeedbackTest extends TestCase
{
    private const ALL = ['treat' => true, 'manage' => true, 'redact' => true, 'steam' => true, 'convert' => true, 'link' => true, 'discord' => true];
    private const ME = ['id' => 7, 'label' => 'TA1'];

    private PDO $pdo;
    /** @var array<string, int> */
    private array $ids;
    private int $now;
    private int $capture;
    private ?bool $sessionStarted = null;

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'tenant_id' => 1, 'user_id' => 7];
        $_POST = [];
        $GLOBALS['__app_locale'] = 'fr';
        $this->now = time();
        $this->pdo = IntelIntakeSqlite::open();
        $this->ids = IntelIntakeSqlite::seed($this->pdo, $this->now);
        // La fiche signalée par le bureau : « otage » était trouvé dans « sabotage ».
        $this->capture = IntelIntakeSqlite::insert($this->pdo, 'sse_field_notes', [
            'tenant_id' => 1, 'reference_code' => 'FR-2026-000077', 'note_kind' => 'FRT', 'themes' => '["ARMEMENT"]',
            'title' => SseDetectionEngineTest::CAPTURE_TITLE, 'body' => SseDetectionEngineTest::CAPTURE_BODY,
            'urgency' => 'routine', 'status' => 'transmise', 'origin' => 'atak', 'author_label' => 'VIPER', 'created_at' => gmdate('Y-m-d H:i:s', $this->now - 600),
        ]);
        SseDetectionRepository::forgetReady();
    }

    protected function tearDown(): void
    {
        IntelIntakeSqlite::close();
        SseDetectionRepository::forgetReady();
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = [];
        $_POST = [];
        unset($GLOBALS['__app_locale']);
    }

    private function desk(): IntelIntakeDesk
    {
        $intake = new IntelIntakeService($this->pdo);

        return new IntelIntakeDesk(
            $intake,
            new IntelIntakeAdvisor($this->pdo, new SseDetectionService($this->pdo)),
            new IntelIntakeConversion($intake),
            new IntelIntakeDiscord($this->pdo, new DiscordBotRepository($this->pdo), $intake, static fn (string $p): string => 'https://athena.test/' . $p),
            ArraySseLinkViewer::full(),
            static function (): void {
            },
        );
    }

    /** @return array<string, array<string, mixed>> « cible|valeur » => proposition */
    private function proposals(int $id): array
    {
        $view = $this->desk()->view(1, 'fiche', $id, ['id' => 7, 'level' => 'tres_restreint'], self::ALL, $this->now);
        self::assertNotNull($view);
        $out = [];
        foreach ($view['proposals'] as $p) {
            $out[$p['target'] . '|' . $p['value']] = $p;
        }

        return $out;
    }

    private function act(int $id, string $action, array $input, ?array $rights = null): array
    {
        return $this->desk()->perform(1, 'fiche', $id, $action, $input, self::ME, $rights ?? self::ALL, 'tres_restreint');
    }

    private function showPage(int $id): string
    {
        $desk = $this->desk()->view(1, 'fiche', $id, ['id' => 7, 'level' => 'tres_restreint'], self::ALL, $this->now);
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };

        // La page d'une remontée, au bureau SSE (/atak/sse/remontees/fiche/{id}).
        return str_replace("\r\n", "\n", $render(base_path('views/atak/sse/intake_show.php'), [
            'intakeDesk' => $desk, 'intakeRights' => IntelIntakeDesk::rights(self::ALL), 'intakeTypes' => IntelIntakeService::typeOptions('fiche'), 'intakeMembers' => [],
            'intakeLevels' => \App\Repositories\SseCaseRepository::CLASSIFICATION_LABELS, 'intakeSenderKey' => null,
            'intakeCanSeeMembers' => false, 'csrfToken' => str_repeat('a', 64),
        ]));
    }

    private function auditActions(): array
    {
        return $this->pdo->query("SELECT action FROM sse_audit_log WHERE action LIKE 'cerbere.lecture.%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function testTheReportedCardIsExplainedWithoutTerrorismOrCritical(): void
    {
        SseDetectionSqlite::install($this->pdo);
        $p = $this->proposals($this->capture);
        self::assertArrayNotHasKey('theme|TERROR', $p);
        self::assertArrayNotHasKey('urgency|critique', $p);
        self::assertArrayNotHasKey('urgency|urgent', $p);
        self::assertArrayHasKey('theme|SECUR', $p);
        self::assertSame('Sécurité / Protection', $p['theme|SECUR']['value_label']);
        self::assertStringStartsWith('Détecté : « trace de manipulation »', $p['theme|SECUR']['reason']);
        self::assertStringContainsString('« sabotage »', $p['theme|SECUR']['reason']);
        self::assertStringContainsString('règle R-14 · confiance ' . $p['theme|SECUR']['confidence'] . ' %', $p['theme|SECUR']['reason']);
        self::assertSame('ARMEMENT,SECUR', $p['theme|SECUR']['fields']['themes'], 'le thème choisi par l’émetteur est gardé');

        // L'ancienne lecture se trompait sur la même fiche : c'est ce que la re-détection retire.
        $legacy = array_map(static fn (array $o): string => $o['target'] . '|' . $o['value'], SseLegacyKeywordDetector::proposals((new IntelIntakeService($this->pdo))->find(1, 'fiche', $this->capture)));
        self::assertContains('theme|TERROR', $legacy);
        self::assertContains('urgency|critique', $legacy);

        $html = $this->showPage($this->capture);
        self::assertStringNotContainsString('Le texte parle de', $html);
        self::assertStringContainsString('<mark>sabotage</mark>', $html);
        self::assertStringContainsString('<mark>trace de manipulation</mark>', $html);
        self::assertStringContainsString('Pourquoi ?', $html);
        self::assertStringContainsString('Mauvaise détection', $html);
        self::assertStringContainsString('Ignorer', $html);
        $dom = $p['theme|SECUR']['dom'];
        self::assertSame(1, preg_match('~<dialog class="cdx-dialog" id="' . preg_quote($dom, '~') . '-why" aria-labelledby="' . preg_quote($dom, '~') . '-why-title">(.*?)</dialog>~s', $html, $why));
        foreach (['R-14', 'Sabotage et atteintes à la sécurité', 'Termes trouvés', 'Exclusions vérifiées', 'Calcul de la confiance', 'Désactiver ou ajuster', 'formule prudente', 'sabotage, bagage'] as $needle) {
            self::assertStringContainsString($needle, html_entity_decode($why[1]), $needle);
        }
        self::assertSame(1, preg_match('~id="' . preg_quote($dom, '~') . '-bad".*?name="reason"[^>]*required~s', $html), 'le motif d’une mauvaise détection est demandé');
        self::assertStringNotContainsString('fiction', mb_strtolower(strip_tags($why[1])));
    }

    public function testIgnoreAndRejectAreKeptLoggedAndCountedPerRule(): void
    {
        SseDetectionSqlite::install($this->pdo);
        $secur = $this->proposals($this->capture)['theme|SECUR'];

        $noReason = $this->act($this->capture, 'cerbere', $secur['feedback'] + ['decision' => 'reject', 'reason' => '']);
        self::assertFalse($noReason['ok'], 'une mauvaise détection a un motif');
        $rejected = $this->act($this->capture, 'cerbere', $secur['feedback'] + ['decision' => 'reject', 'reason' => 'Mot pris dans un autre sens']);
        self::assertTrue($rejected['ok'], $rejected['message']);
        self::assertStringContainsString('R-14', $rejected['message']);
        self::assertArrayNotHasKey('theme|SECUR', $this->proposals($this->capture), 'la proposition rejetée ne revient pas');
        $stats = (new SseDetectionRepository($this->pdo))->ruleStats(1);
        self::assertSame(1, $stats['R-14']['rejected']);
        self::assertSame(['Mot pris dans un autre sens'], $stats['R-14']['reasons']);
        self::assertSame(['cerbere.lecture.rejected'], $this->auditActions());

        // Ignorer : sur une autre fiche, gardé de la même façon.
        $b = $this->proposals($this->ids['b']);
        self::assertNotSame([], $b);
        $first = reset($b);
        self::assertTrue($this->act($this->ids['b'], 'cerbere', $first['feedback'] + ['decision' => 'ignore'])['ok']);
        self::assertArrayNotHasKey($first['target'] . '|' . $first['value'], $this->proposals($this->ids['b']));
        self::assertSame(['cerbere.lecture.rejected', 'cerbere.lecture.ignored'], $this->auditActions());

        // Une signature inventée est refusée.
        self::assertFalse($this->act($this->capture, 'cerbere', ['cerbere_target' => 'theme', 'cerbere_value' => 'INCONNU', 'cerbere_rules' => 'R-14', 'decision' => 'ignore'])['ok']);
        self::assertFalse($this->act($this->capture, 'cerbere', ['cerbere_target' => 'theme', 'cerbere_value' => 'SECUR', 'cerbere_rules' => 'Z-99', 'decision' => 'ignore'])['ok']);
    }

    public function testFeedbackNeedsTheTreatRightAndAValidToken(): void
    {
        SseDetectionSqlite::install($this->pdo);
        $secur = $this->proposals($this->capture)['theme|SECUR'];
        $denied = $this->act($this->capture, 'cerbere', $secur['feedback'] + ['decision' => 'ignore'], ['treat' => false] + self::ALL);
        self::assertFalse($denied['ok']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_detection_decisions')->fetchColumn());

        // Bureau SSE : jeton absent ou faux → rien n'est écrit, retour à la fiche.
        $_POST = $secur['feedback'] + ['decision' => 'ignore', '_csrf_token' => 'faux', 'retour' => 'fiche'];
        $res = (new SseIntakeController($this->desk()))->action(new Request(), ['source' => 'fiche', 'id' => (string) $this->capture, 'action' => 'cerbere']);
        self::assertSame(302, $res->statusCode());
        self::assertStringContainsString('fiches/' . $this->capture, (string) $res->headerValue('Location'));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_detection_decisions')->fetchColumn());
        self::assertSame([], $this->auditActions());
    }

    public function testApplyIsAHumanGestureAndAnAppliedProposalNeverChanges(): void
    {
        SseDetectionSqlite::install($this->pdo);
        $secur = $this->proposals($this->capture)['theme|SECUR'];
        self::assertSame('["ARMEMENT"]', $this->pdo->query('SELECT themes FROM sse_field_notes WHERE id = ' . $this->capture)->fetchColumn(), 'afficher n’écrit rien');
        $res = $this->act($this->capture, $secur['action'], $secur['fields']);
        self::assertTrue($res['ok'], $res['message']);
        self::assertStringContainsString('SECUR', (string) $this->pdo->query('SELECT themes FROM sse_field_notes WHERE id = ' . $this->capture)->fetchColumn());
        self::assertSame('applied', $this->pdo->query("SELECT decision FROM sse_detection_decisions WHERE target = 'theme' AND value = 'SECUR'")->fetchColumn());
        // Ni un retour ultérieur ni la re-détection ne touchent une proposition appliquée.
        $this->act($this->capture, 'cerbere', $secur['feedback'] + ['decision' => 'reject', 'reason' => 'Changement d’avis']);
        (new SseRedetection($this->pdo))->run(true);
        self::assertSame('applied', $this->pdo->query("SELECT decision FROM sse_detection_decisions WHERE target = 'theme' AND value = 'SECUR'")->fetchColumn());

        // Une urgence Critique proposée ne s'applique qu'après « J’ai vérifié le texte ».
        $crit = ['urgency' => 'critique', 'cerbere_target' => 'urgency', 'cerbere_value' => 'critique', 'cerbere_rules' => 'U-01,U-02', 'cerbere_confidence' => '90'];
        $refused = $this->act($this->ids['b'], 'edit', $crit);
        self::assertFalse($refused['ok']);
        self::assertStringContainsString('J’ai vérifié le texte', $refused['message']);
        self::assertSame('urgent', $this->pdo->query('SELECT urgency FROM sse_field_notes WHERE id = ' . $this->ids['b'])->fetchColumn());
        self::assertTrue($this->act($this->ids['b'], 'edit', $crit + ['cerbere_checked' => '1'])['ok']);
        self::assertSame('critique', $this->pdo->query('SELECT urgency FROM sse_field_notes WHERE id = ' . $this->ids['b'])->fetchColumn());
    }

    public function testWithoutTheStepProposalsStillWorkButFeedbackIsNotKept(): void
    {
        $p = $this->proposals($this->capture);
        self::assertArrayHasKey('theme|SECUR', $p);
        self::assertArrayNotHasKey('theme|TERROR', $p);
        $res = $this->act($this->capture, 'cerbere', $p['theme|SECUR']['feedback'] + ['decision' => 'ignore']);
        self::assertFalse($res['ok']);
        self::assertStringContainsString('sse_detection_rules', $res['message']);
        self::assertTrue($this->act($this->capture, $p['theme|SECUR']['action'], $p['theme|SECUR']['fields'])['ok'], 'appliquer marche sans l’étape');
    }

    public function testRedetectionSimulatesThenWithdrawsOnceAndKeepsDecisions(): void
    {
        SseDetectionSqlite::install($this->pdo);
        $this->pdo->exec("INSERT INTO intel_intake_state (tenant_id, source, source_id, state) VALUES (1, 'fiche', {$this->capture}, 'a_traiter')");
        $before = $this->pdo->query('SELECT note_kind, themes, urgency FROM sse_field_notes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        $dry = (new SseRedetection($this->pdo))->run(false, 1);
        $changes = array_map(static fn (array $c): string => $c['ref'] . ' ' . $c['target'] . '|' . $c['value'], $dry['changes']);
        self::assertContains('FR-2026-000077 theme|TERROR', $changes);
        self::assertContains('FR-2026-000077 urgency|critique', $changes);
        self::assertNotContains('FR-2026-000077 theme|SECUR', $changes, 'une proposition toujours justifiée reste');
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_detection_decisions')->fetchColumn(), 'la simulation n’écrit rien');
        self::assertSame([], $this->auditActions());

        $applied = (new SseRedetection($this->pdo))->run(true, 1);
        self::assertSame($dry['withdrawn'], $applied['withdrawn']);
        $rows = $this->pdo->query("SELECT value, decision, reason FROM sse_detection_decisions WHERE source_id = {$this->capture} AND target = 'theme' AND value = 'TERROR'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('withdrawn', $rows['decision']);
        self::assertStringContainsString('« otage »', $rows['reason']);
        self::assertContains('cerbere.lecture.withdrawn', $this->auditActions());

        $again = (new SseRedetection($this->pdo))->run(true, 1);
        self::assertSame(0, $again['withdrawn'], 'idempotent');
        self::assertSame($before, $this->pdo->query('SELECT note_kind, themes, urgency FROM sse_field_notes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'aucune remontée modifiée');
        // Une proposition retirée par la re-détection peut revenir si le lexique la justifie : seules les décisions humaines la cachent.
        self::assertArrayHasKey('theme|SECUR', $this->proposals($this->capture));
    }

    public function testRedetectToolIsADryRunByDefault(): void
    {
        $tool = str_replace("\r\n", "\n", (string) file_get_contents(base_path('tools/sse-redetect.php')));
        self::assertStringContainsString("\$apply = isset(\$opts['apply']);", $tool);
        self::assertStringContainsString('SIMULATION (aucune écriture)', $tool);
        self::assertStringContainsString('SseRedetection', $tool);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $tool);
    }
}
