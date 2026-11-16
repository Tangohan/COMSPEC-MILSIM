<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminIntelIntakeController;
use App\Controllers\Web\SseIntakeController;
use App\Core\Session;
use App\Repositories\DiscordBotRepository;
use App\Services\Integrations\DiscordBotBureau;
use App\Services\Intel\IntelIntakeAdvisor;
use App\Services\Intel\IntelIntakeConversion;
use App\Services\Intel\IntelIntakeDesk;
use App\Services\Intel\IntelIntakeDiscord;
use App\Services\Intel\IntelIntakePresenter;
use App\Services\Intel\IntelIntakeService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\IntelIntakeSqlite;

/**
 * Remontées : traitement commun au back-office et au bureau SSE (IntelIntakeDesk), sur une base sqlite réelle.
 * Rendu jour et nuit, pastilles, cycle de prise en compte, propositions de Cerbère, conversion, liens, notification
 * Discord (aucun envoi : le bot est joué par une classe écrite à la main), droits, jeton, cloisonnement des communautés.
 */
final class IntelIntakeDeskTest extends TestCase
{
    private const ALL = ['treat' => true, 'manage' => true, 'redact' => true, 'steam' => true, 'convert' => true, 'link' => true, 'discord' => true];
    private const ME = ['id' => 7, 'label' => 'TA1'];

    private PDO $pdo;
    /** @var array{a: int, b: int, cr: int, case: int, me: int, analyst: int} */
    private array $ids;
    private int $now;
    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];
    /** @var list<array{0: int, 1: int, 2: string, 3: array<string, mixed>}> */
    private array $notified = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'tenant_id' => 1, 'user_id' => 7];
        $GLOBALS['__app_locale'] = 'fr';
        $this->now = time();
        $this->pdo = IntelIntakeSqlite::open();
        $this->ids = IntelIntakeSqlite::seed($this->pdo, $this->now);
        $this->notified = [];
    }

    protected function tearDown(): void
    {
        IntelIntakeSqlite::close();
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        unset($GLOBALS['__app_locale']);
    }

    private function intake(): IntelIntakeService
    {
        return new IntelIntakeService($this->pdo);
    }

    private function discord(): IntelIntakeDiscord
    {
        return new IntelIntakeDiscord($this->pdo, new DiscordBotRepository($this->pdo), $this->intake(), static fn (string $p): string => 'https://athena.test/' . $p);
    }

    private function desk(): IntelIntakeDesk
    {
        $intake = $this->intake();

        return new IntelIntakeDesk(
            $intake,
            new IntelIntakeAdvisor($this->pdo),
            new IntelIntakeConversion($intake),
            $this->discord(),
            ArraySseLinkViewer::full(),
            function (int $tenant, int $user, string $type, array $payload): void {
                $this->notified[] = [$tenant, $user, $type, $payload];
            },
        );
    }

    /** @param array<string, mixed> $input */
    private function act(string $source, int $id, string $action, array $input = [], ?array $rights = null, int $tenant = 1): array
    {
        return $this->desk()->perform($tenant, $source, $id, $action, $input, self::ME, $rights ?? self::ALL, 'tres_restreint');
    }

    /** @param array<string, mixed> $vars */
    private function render(string $view, array $vars): string
    {
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

        return $render(base_path('views/' . $view . '.php'), $vars);
    }

    /** @param array<string, bool> $rights */
    private function showPage(string $source, int $id, array $rights = self::ALL, string $level = 'tres_restreint'): string
    {
        $desk = $this->desk()->view(1, $source, $id, ['id' => 7, 'level' => $level], $rights, $this->now);
        self::assertNotNull($desk);

        return self::own($this->render('atak/sse/intake_show', [
            'intakeDesk' => $desk,
            'intakeRights' => IntelIntakeDesk::rights($rights),
            'intakeTypes' => IntelIntakeService::typeOptions($source),
            'intakeMembers' => $this->intake()->members(1),
            'intakeLevels' => \App\Repositories\SseCaseRepository::CLASSIFICATION_LABELS,
            'intakeSenderKey' => null,
            'intakeCanSeeMembers' => false,
            'csrfToken' => str_repeat('a', 64),
        ]), '<div class="rmt rmt--sse rmt-show"');
    }

    /** Le contenu propre à la page du bureau, sans la coque du portail ni la fenêtre « Rattacher » (rendue une fois par page). */
    private static function own(string $html, string $root): string
    {
        $start = strpos($html, $root);
        self::assertNotFalse($start, 'la page est rendue dans la coque du bureau');
        $ends = array_filter([strpos($html, 'id="ra-window"', (int) $start), strpos($html, '</main>', (int) $start)], static fn ($p): bool => $p !== false);

        return substr($html, (int) $start, ($ends === [] ? strlen($html) : min($ends)) - (int) $start);
    }

    private static function css(string $name): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(base_path('public/assets/css/' . $name)));
    }

    // ------------------------------------------------------------------ mode nuit, lisibilité

    public function testPageStylesheetHasNoColourOutsideItsTokens(): void
    {
        $css = self::css('remontees.css');
        $noComments = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        // La cause du défaut : des jetons du site public (--ds-color-*), toujours définis et jamais assombris.
        self::assertStringNotContainsString('--ds-color', $noComments);
        // Une fois retirées les déclarations de jetons (« --nom: valeur; »), il ne reste aucune couleur écrite en dur.
        $withoutTokens = (string) preg_replace('/--[a-z0-9-]+\s*:[^;]*;/i', '', $noComments);
        self::assertSame(0, preg_match('/#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i', $withoutTokens, $m), 'couleur en dur : ' . ($m[0] ?? ''));
        // Les jetons de teinte sont redéfinis pour la nuit.
        self::assertSame(1, preg_match('/html\[data-bo-theme="dark"\] \.rmt \{[^}]*--rm-accent:[^}]*--rm-danger-fg:/s', $css));
        self::assertSame(1, preg_match('/:root \.rmt \{[^}]*--rm-ink: var\(--ath-ink/s', $css));
    }

    public function testOldStylesheetAndItsNightRulesAreGone(): void
    {
        self::assertFalse(is_file(base_path('public/assets/css/back-office-intel-intake.css')));
        self::assertStringNotContainsString('.rmt', self::css('back-office-dark.generated.css'));
        self::assertStringNotContainsString('.rmt', self::css('back-office-dark.css'));
        // Les pages vivent au bureau SSE : leurs vues chargent la feuille et les jetons du thème « bureau ».
        foreach (['intake_index', 'intake_show', 'intake_player', 'intake_settings'] as $view) {
            $src = (string) file_get_contents(base_path('views/atak/sse/' . $view . '.php'));
            self::assertStringContainsString("asset_url('assets/css/remontees.css')", $src, $view);
            self::assertStringContainsString("asset_url('assets/css/sse-remontees.css')", $src, $view);
            self::assertStringContainsString("require __DIR__ . '/_layout.php'", $src, $view);
        }
        $config = (string) file_get_contents(base_path('config/back_office_pages.php'));
        self::assertStringNotContainsString("'css' => ['remontees.css']", $config, 'plus de page Remontées dans le back-office');
    }

    public function testEveryButtonOfThePageHasAnAccessibleLabel(): void
    {
        foreach ([['fiche', $this->ids['a']], ['fiche', $this->ids['b']], ['cr', $this->ids['cr']]] as [$source, $id]) {
            $html = $this->showPage($source, $id);
            self::assertGreaterThan(20, preg_match_all('~<(button|summary)\b([^>]*)>(.*?)</\1>~s', $html, $all, PREG_SET_ORDER));
            foreach ($all as $m) {
                $text = trim(html_entity_decode(strip_tags($m[3])));
                $labelled = preg_match('/aria-label="[^"]+"/', $m[2]) === 1;
                self::assertTrue($text !== '' || $labelled, 'bouton sans libellé : ' . mb_substr($m[0], 0, 160));
            }
            // Chaque champ a son libellé.
            preg_match_all('~<(?:select|textarea|input(?![^>]*type="(?:hidden|radio|checkbox)"))[^>]*\bid="([^"]+)"~', $html, $fields);
            foreach ($fields[1] as $fieldId) {
                self::assertStringContainsString('for="' . $fieldId . '"', $html, 'champ sans libellé : ' . $fieldId);
            }
        }
    }

    public function testThemesAreReadablePillsNeverRawJson(): void
    {
        $html = $this->showPage('fiche', $this->ids['a']);
        self::assertStringNotContainsString('["ARMEMENT"', $html);
        self::assertStringNotContainsString('&quot;ARMEMENT&quot;', $html);
        foreach (['Armement / Matériel', 'Logistique', 'Sécurité / Protection'] as $label) {
            self::assertSame(1, preg_match('~<li class="rmt-theme rmt-theme--[a-z]+"[^>]*>' . preg_quote($label, '~') . '</li>~', $html), $label);
        }
        self::assertStringContainsString('C3 — Source assez sûre · Information possible', $html);
        self::assertSame([], IntelIntakePresenter::themes('pas du json'));
        self::assertSame('Terrorisme', IntelIntakePresenter::themes('["TERROR","inconnu"]')[0]['label']);
    }

    public function testPageHasNoEyebrowAndFormsLiveInDialogs(): void
    {
        $html = $this->showPage('fiche', $this->ids['b']);
        self::assertStringNotContainsString('kicker', $html);
        self::assertSame(0, preg_match('/text-transform\s*:\s*uppercase/', self::css('remontees.css')));
        foreach (['transmettre', 'doublon', 'clore', 'convertir', 'discord', 'modifier', 'supprimer', 'caviarder'] as $dlg) {
            self::assertSame(1, preg_match('~<dialog class="rmt-dialog[^"]*" id="rmt-dlg-' . $dlg . '"~', $html), $dlg);
        }
        // Rattacher une fiche passe par la fenêtre « Rattacher » du bureau.
        self::assertStringContainsString('data-ra-open data-ra-pieces="note:' . $this->ids['b'] . '"', $html);
        self::assertStringNotContainsString('rmt-dlg-relier', $html);
        // Aucun formulaire de transmission, de doublon ou de suppression hors d'une fenêtre.
        $outside = (string) preg_replace('~<dialog\b.*?</dialog>~s', '', $html);
        self::assertStringNotContainsString('name="target"', $outside);
        self::assertStringNotContainsString('/delete"', $outside);
        self::assertStringNotContainsString('/edit"', $outside);
        // Chaque formulaire porte le jeton.
        self::assertSame(preg_match_all('~<form\b[^>]*method="post"~', $html), preg_match_all('~name="_csrf_token"~', $html));
        // Cinq onglets, une ligne de description sous chaque titre de section.
        self::assertSame(5, preg_match_all('~role="tab"~', $html));
        self::assertSame(5, preg_match_all('~<h2 class="rmt-h2">[^<]+</h2>\s*<p class="rmt-desc">~', $html));
    }

    public function testSteamIdentifierIsMaskedUntilAnAuthorisedReaderAsksForIt(): void
    {
        $steam = '76561198000117733';
        $html = $this->showPage('fiche', $this->ids['b']);
        self::assertStringNotContainsString($steam, $html);
        self::assertStringContainsString('7656•••••••••7733', $html);
        self::assertStringContainsString('data-rmt-steam-show', $html);

        $noRight = ['steam' => false] + self::ALL;
        self::assertStringNotContainsString('data-rmt-steam-show', $this->showPage('fiche', $this->ids['b'], $noRight));
        $denied = $this->act('fiche', $this->ids['b'], 'steam', [], $noRight);
        self::assertFalse($denied['ok']);
        self::assertStringNotContainsString($steam, $denied['message']);

        $shown = $this->act('fiche', $this->ids['b'], 'steam', ['mode' => 'copy']);
        self::assertTrue($shown['ok']);
        self::assertSame($steam, $shown['data']['steam']);
        $events = $this->intake()->events(1, 'fiche', $this->ids['b']);
        $last = end($events);
        self::assertSame('steam', $last['kind']);
        self::assertSame('copie', $last['meta']['mode']);
        self::assertSame('', IntelIntakePresenter::maskSteam(''));
    }

    // ------------------------------------------------------------------ cycle de prise en compte, pastilles

    public function testTakingInChargeAssignsStampsAndTellsTheSender(): void
    {
        $before = $this->intake()->find(1, 'fiche', $this->ids['b']);
        self::assertSame('a_traiter', $before['state']);
        $delay = IntelIntakePresenter::delay($before, $this->now);
        self::assertTrue($delay['late'], 'urgente, reçue il y a 3 h, objectif 1 h');
        self::assertSame(3600, $delay['target']);

        $res = $this->act('fiche', $this->ids['b'], 'take');
        self::assertTrue($res['ok']);
        $after = $this->intake()->find(1, 'fiche', $this->ids['b']);
        self::assertSame('prise_en_compte', $after['state']);
        self::assertSame(7, $after['assignee']['id']);
        self::assertSame('prise_en_compte', $after['row']['status'], 'le statut que lit l’émetteur suit');
        self::assertNotSame('', $after['ack_at']);
        self::assertFalse(IntelIntakePresenter::delay($after, $this->now)['late']);
        $cycle = array_column(IntelIntakePresenter::cycle($after), 'status', 'key');
        self::assertSame(['recue' => 'done', 'prise_en_compte' => 'current', 'en_cours' => 'todo', 'issue' => 'todo'], $cycle);
        // Accusé à l'émetteur (compte n° 8), par le centre de notifications du site.
        self::assertCount(1, $this->notified);
        self::assertSame([1, 8, IntelIntakeDesk::NOTIFY_ACK], array_slice($this->notified[0], 0, 3));
        self::assertSame('FR-2026-000041', $this->notified[0][3]['ref']);

        self::assertFalse($this->act('fiche', $this->ids['b'], 'take')['ok'], 'déjà prise');
        self::assertCount(1, $this->notified);

        self::assertTrue($this->act('fiche', $this->ids['b'], 'state', ['state' => 'en_cours'])['ok']);
        self::assertTrue($this->act('fiche', $this->ids['b'], 'state', ['state' => 'exploitee'])['ok']);
        $done = $this->intake()->find(1, 'fiche', $this->ids['b']);
        self::assertSame(['done', 'done', 'done', 'done'], array_column(IntelIntakePresenter::cycle($done), 'status'));
        self::assertSame('Exploitée', IntelIntakePresenter::cycle($done)[3]['label']);
    }

    public function testSenderIsNotNotifiedWithoutAnAccountOrWhenTreatingTheirOwnReport(): void
    {
        $this->pdo->exec("UPDATE atak_tactical_reports SET submitter_user_id = 7 WHERE id = " . $this->ids['cr']);
        self::assertTrue($this->act('cr', $this->ids['cr'], 'take')['ok']);
        self::assertSame([], $this->notified);
        self::assertSame('ACKNOWLEDGED', $this->intake()->find(1, 'cr', $this->ids['cr'])['row']['status']);
    }

    public function testBadgesAreTheSameOnThePageAndInTheList(): void
    {
        $desk = $this->desk()->view(1, 'fiche', $this->ids['b'], ['id' => 7, 'level' => 'tres_restreint'], self::ALL, $this->now);
        $page = array_column($desk['badges'], 'label', 'key');
        self::assertSame('À traiter', $page['etat']);
        self::assertSame('En retard', $page['retard']);
        self::assertSame('Urgent', $page['urgence']);
        self::assertSame('Confidentiel (C)', $page['diffusion']);
        self::assertSame('ATAK', $page['origine']);
        self::assertSame('Caviardée', $page['caviardee']);
        self::assertSame('2 pièces', $page['pieces']);
        self::assertSame('Liée à un dossier', $page['liee']);
        self::assertSame('4 lecteurs · 13 lectures', $page['lectures']);

        $refined = IntelIntakeDesk::refine($this->intake()->listItems(1, ['state' => 'all'], 7), '', 'recu', 'desc', $this->now);
        $row = array_values(array_filter($refined['items'], fn (array $i): bool => $i['source'] === 'fiche' && $i['id'] === $this->ids['b']))[0];
        self::assertSame($page, array_column($row['badges'], 'label', 'key'));
        self::assertSame(['retard' => 2, 'urgente' => 2, 'pieces' => 1, 'caviardee' => 1, 'liee' => 1, 'non_attribuee' => 2], $refined['counts']);

        $late = IntelIntakeDesk::refine($this->intake()->listItems(1, ['state' => 'all'], 7), 'caviardee', 'recu', 'desc', $this->now);
        self::assertSame(['FR-2026-000041'], array_column($late['items'], 'ref'));
        $byWait = IntelIntakeDesk::refine($this->intake()->listItems(1, ['state' => 'all'], 7), '', 'attente', 'desc', $this->now);
        self::assertSame('SPOT-2026-0117', $byWait['items'][0]['ref'], 'la plus longue attente d’abord');
        $bySender = IntelIntakeDesk::refine($this->intake()->listItems(1, ['state' => 'all'], 7), '', 'emetteur', 'asc', $this->now);
        self::assertSame('TA1', $bySender['items'][0]['author']);
    }

    public function testListPageShowsBadgesSortableColumnsAndNoSteamIdentifier(): void
    {
        $refined = IntelIntakeDesk::refine($this->intake()->listItems(1, ['state' => 'all'], 7), '', 'recu', 'desc', $this->now);
        $html = $this->render('atak/sse/intake_index', [
            'intakeRights' => self::ALL, 'intakeFigures' => ['todo' => 2, 'late' => 2, 'week' => 1, 'open' => 3, 'mine' => 0],
            'intakeReady' => true, 'intakeItems' => $refined['items'], 'intakeBadgeCounts' => $refined['counts'],
            'intakeCounts' => $this->intake()->counts(1, [], 7), 'intakeTab' => 'all',
            'intakeFilters' => ['source' => '', 'type' => '', 'q' => '', 'assignee' => ''],
            'intakeTypes' => IntelIntakeService::typeOptions('fiche'), 'intakeMembers' => [],
            'intakeBadge' => '', 'intakeSort' => 'recu', 'intakeDir' => 'desc',
        ]);
        self::assertStringContainsString('rmt-badge--danger', $html);
        self::assertStringContainsString('aria-sort="descending"', $html);
        self::assertSame(1, preg_match('~pastille=retard[^"]*"[^>]*>En retard <strong>2</strong>~', $html));
        self::assertStringContainsString('tri=urgence', $html);
        self::assertStringNotContainsString('76561198', $html);
        self::assertStringNotContainsString('kicker', $html);
    }

    // ------------------------------------------------------------------ propositions de Cerbère

    public function testProposalsAreExplainedAndNeverAppliedSilently(): void
    {
        $snapshot = fn (): array => $this->pdo->query('SELECT note_kind, themes, urgency FROM sse_field_notes WHERE id = ' . $this->ids['b'])->fetch(PDO::FETCH_ASSOC);
        $before = $snapshot();
        $events = count($this->intake()->events(1, 'fiche', $this->ids['b']));
        $desk = $this->desk()->view(1, 'fiche', $this->ids['b'], ['id' => 7, 'level' => 'tres_restreint'], self::ALL, $this->now);
        $html = $this->showPage('fiche', $this->ids['b']);

        self::assertSame($before, $snapshot(), 'afficher la page n’écrit rien');
        self::assertCount($events, $this->intake()->events(1, 'fiche', $this->ids['b']));
        $byKey = array_column($desk['proposals'], null, 'key');
        self::assertArrayHasKey('type', $byKey);
        self::assertSame('FRT', $byKey['type']['value']);
        self::assertArrayHasKey('themes', $byKey);
        foreach ($desk['proposals'] as $p) {
            self::assertStringStartsWith('Le texte parle de : ', $p['reason']);
            self::assertStringContainsString($p['reason'], html_entity_decode($html));
        }
        self::assertGreaterThanOrEqual(3, substr_count($html, 'Proposé par Cerbère'));
        // Attribution : personne n'a encore traité ce joueur, la charge la plus faible l'emporte, avec sa raison.
        self::assertNotNull($desk['assignee_proposal']);
        self::assertContains($desk['assignee_proposal']['user_id'], [9, 11]);
        self::assertNotSame('', $desk['assignee_proposal']['reason']);
        // Doublons proposés : le compte rendu du même émetteur, au même carroyage, le même jour.
        self::assertSame('SPOT-2026-0117', $desk['duplicates'][0]['ref']);
        self::assertStringContainsString('même émetteur', mb_strtolower($desk['duplicates'][0]['reason']));

        // Appliquer une proposition est un geste : la même action que la saisie à la main, notée dans le fil.
        $res = $this->act('fiche', $this->ids['b'], $byKey['themes']['action'], $byKey['themes']['fields']);
        self::assertTrue($res['ok']);
        self::assertNotSame($before['themes'], $snapshot()['themes']);
        self::assertStringContainsString('TERROR', $snapshot()['themes'], 'le thème d’origine est gardé');
    }

    public function testAssigneeProposalPrefersTheLastHandlerOfThatSender(): void
    {
        self::assertTrue($this->act('cr', $this->ids['cr'], 'assign', ['user_id' => 11])['ok']);
        $proposal = (new IntelIntakeAdvisor($this->pdo))->assignee(1, $this->intake()->find(1, 'fiche', $this->ids['b']));
        self::assertSame(11, $proposal['user_id']);
        self::assertStringContainsString('VIPER', $proposal['reason']);
    }

    public function testForwardTargetIsValidatedNeverFreeText(): void
    {
        $advisor = new IntelIntakeAdvisor($this->pdo);
        self::assertSame('Cellule renseignement (S2)', $advisor->validForward(1, 'cellule RENSEIGNEMENT (s2)'));
        self::assertSame('SSE-2026-0007', $advisor->validForward(1, 'sse-2026-0007 — Dépôt de la vallée de Nidasos'));
        self::assertNull($advisor->validForward(1, 'Le cousin du chef'));
        self::assertNull($advisor->validForward(1, 'SSE-2026-0001'), 'dossier d’une autre communauté');
        // Recherche sans accents ni casse.
        self::assertSame(['Échelon supérieur'], array_column($advisor->forwardTargets(1, 'echelon sup'), 'value'));
        self::assertSame(['SSE-2026-0007'], array_column($advisor->forwardTargets(1, 'depot de la vallee'), 'value'));

        $refused = $this->act('fiche', $this->ids['a'], 'state', ['state' => 'transmise', 'target' => 'Le cousin du chef']);
        self::assertFalse($refused['ok']);
        self::assertSame('close', $this->intake()->find(1, 'fiche', $this->ids['a'])['state']);
        $ok = $this->act('fiche', $this->ids['a'], 'state', ['state' => 'transmise', 'target' => 'SSE-2026-0007', 'note' => 'À verser au dossier en cours']);
        self::assertTrue($ok['ok']);
        $item = $this->intake()->find(1, 'fiche', $this->ids['a']);
        self::assertSame('transmise', $item['state']);
        self::assertSame($this->ids['case'], $item['state_info']['case_id']);
        self::assertTrue($item['linked']);
    }

    public function testDuplicateNeedsAnExistingOtherReport(): void
    {
        self::assertFalse($this->act('fiche', $this->ids['b'], 'state', ['state' => 'doublon', 'target' => 'fiche/' . $this->ids['b']])['ok'], 'elle-même');
        self::assertFalse($this->act('fiche', $this->ids['b'], 'state', ['state' => 'doublon', 'target' => 'FR-2026-000900'])['ok'], 'autre communauté');
        self::assertTrue($this->act('fiche', $this->ids['b'], 'state', ['state' => 'doublon', 'target' => 'cr/' . $this->ids['cr']])['ok']);
        $item = $this->intake()->find(1, 'fiche', $this->ids['b']);
        self::assertSame('SPOT-2026-0117', $item['state_info']['duplicate_of']['ref']);
        $origin = $this->intake()->events(1, 'cr', $this->ids['cr']);
        self::assertSame('duplicate', end($origin)['kind']);
    }

    // ------------------------------------------------------------------ conversion, liens

    public function testConversionKeepsProvenanceAndNeverDuplicates(): void
    {
        $original = $this->pdo->query('SELECT title, body, status, themes FROM sse_field_notes WHERE id = ' . $this->ids['a'])->fetch(PDO::FETCH_ASSOC);
        $cases = (int) $this->pdo->query('SELECT COUNT(*) FROM sse_cases WHERE tenant_id = 1')->fetchColumn();

        $first = $this->act('fiche', $this->ids['a'], 'convert', ['kind' => 'dossier']);
        self::assertTrue($first['ok']);
        self::assertFalse($first['data']['existing']);
        self::assertSame($cases + 1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_cases WHERE tenant_id = 1')->fetchColumn());
        $caseId = (int) $this->pdo->query('SELECT MAX(id) FROM sse_cases WHERE tenant_id = 1')->fetchColumn();
        self::assertSame('atak/sse/dossiers/' . $caseId, $first['data']['url']);
        // Provenance : lien « issu de », manuel, avec son auteur.
        $link = $this->pdo->query("SELECT * FROM sse_entity_links WHERE source_type = 'case' AND source_id = '{$caseId}'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['note', (string) $this->ids['a'], 'created_from', 'manual.link', 'user', 7], [$link['target_type'], $link['target_id'], $link['relation'], $link['rule_code'], $link['created_by'], (int) $link['created_by_user_id']]);
        self::assertStringContainsString('FR-2026-000002', (string) $this->pdo->query("SELECT summary FROM sse_cases WHERE id = {$caseId}")->fetchColumn());
        // La remontée d'origine est intacte (elle est seulement versée à son dossier, comme le fait le bureau).
        self::assertSame($original, $this->pdo->query('SELECT title, body, status, themes FROM sse_field_notes WHERE id = ' . $this->ids['a'])->fetch(PDO::FETCH_ASSOC));

        $second = $this->act('fiche', $this->ids['a'], 'convert', ['kind' => 'dossier']);
        self::assertTrue($second['ok']);
        self::assertTrue($second['data']['existing']);
        self::assertSame($first['data']['url'], $second['data']['url']);
        self::assertSame($cases + 1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_cases WHERE tenant_id = 1')->fetchColumn(), 'pas de second dossier');

        self::assertFalse($this->act('fiche', $this->ids['a'], 'convert', ['kind' => 'fiche'])['ok'], 'une fiche ne se convertit pas en fiche');
        self::assertFalse($this->act('fiche', $this->ids['a'], 'convert', ['kind' => 'tache_rh'])['ok']);
        self::assertTrue($this->act('fiche', $this->ids['a'], 'convert', ['kind' => 'collecte'])['ok']);
        self::assertTrue($this->act('fiche', $this->ids['a'], 'convert', ['kind' => 'interet'])['ok']);
    }

    public function testReportBecomesAFieldNoteWithoutLeakingRedactedText(): void
    {
        $this->intake()->addRedaction(1, 'cr', $this->ids['cr'], self::ME, 'details', 'Deux hommes armés', 'tres_restreint', [], 'Source');
        $res = $this->desk()->perform(1, 'cr', $this->ids['cr'], 'convert', ['kind' => 'fiche'], ['id' => 9, 'label' => 'ORION'], self::ALL, 'interne');
        self::assertTrue($res['ok']);
        $note = $this->pdo->query('SELECT * FROM sse_field_notes WHERE tenant_id = 1 ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('Colis suspect au pont nord', $note['title']);
        self::assertStringNotContainsString('Deux hommes armés', (string) $note['body'], 'ORION ne lit pas ce passage : il ne le recopie pas');
        self::assertStringContainsString('█', (string) $note['body']);
        self::assertSame('urgent', $note['urgency']);
        self::assertSame('VIPER', $note['author_label']);
        $thread = $this->intake()->events(1, 'fiche', (int) $note['id']);
        self::assertSame('SPOT-2026-0117', $thread[0]['meta']['origin']['ref']);
        self::assertTrue($this->desk()->perform(1, 'cr', $this->ids['cr'], 'convert', ['kind' => 'fiche'], self::ME, self::ALL, 'tres_restreint')['data']['existing']);
    }

    public function testLinkGoesThroughTheSseLinkServiceWithItsRules(): void
    {
        $caseRef = 'case:' . $this->ids['case'];
        self::assertFalse($this->act('fiche', $this->ids['a'], 'link', ['to' => $caseRef, 'relation' => 'filed_in', 'reason' => 'bof'])['ok'], 'motif trop court');
        self::assertFalse($this->act('cr', $this->ids['cr'], 'link', ['to' => $caseRef, 'relation' => 'related', 'reason' => 'Même affaire que le dépôt.'])['ok'], 'un compte rendu n’a pas de type de lien');
        $ok = $this->act('fiche', $this->ids['a'], 'link', ['to' => $caseRef, 'relation' => 'filed_in', 'reason' => 'Même affaire que le dépôt.']);
        self::assertTrue($ok['ok'], $ok['message']);
        $links = $this->desk()->links($this->ids['a']);
        self::assertSame(['SSE-2026-0007'], array_column($links['items'], 'ref'));
        self::assertFalse($links['items'][0]['auto']);
        self::assertFalse($this->act('fiche', $this->ids['a'], 'link', ['to' => $caseRef, 'relation' => 'filed_in', 'reason' => 'Même affaire que le dépôt.'])['ok'], 'déjà liés');
        self::assertSame('case:' . $this->ids['case'], $this->desk()->linkSearch('nidasos')[0]['value']);
    }

    // ------------------------------------------------------------------ Discord

    public function testDiscordIsCleanlyDisabledWithoutConfiguration(): void
    {
        $status = $this->discord()->status(1, $this->now);
        self::assertFalse($status['ready']);
        self::assertNotSame('', $status['reason']);
        self::assertSame([], $this->discord()->pending(1, $this->now));
        $res = $this->act('fiche', $this->ids['b'], 'discord');
        self::assertFalse($res['ok']);
        self::assertSame($status['reason'], $res['message']);
        $html = $this->showPage('fiche', $this->ids['b']);
        self::assertSame(1, preg_match('~<button type="submit" class="rmt-btn rmt-btn--primary" disabled>Envoyer la notification</button>~', $html));
        self::assertStringContainsString(htmlspecialchars($status['reason'], ENT_QUOTES), $html);
        // Serveur relié mais notifications non activées, puis bot muet : toujours une explication, jamais une erreur.
        IntelIntakeSqlite::linkDiscord($this->pdo, $this->now);
        self::assertStringContainsString('ne sont pas activées', $this->discord()->status(1, $this->now)['reason']);
        self::assertFalse($this->discord()->save(1, ['enabled' => 1, 'channel_id' => '123456789'])['ok'], 'salon inconnu du serveur');
        self::assertFalse($this->discord()->save(1, ['enabled' => 1, 'channel_id' => ''])['ok']);
        self::assertTrue($this->discord()->save(1, ['enabled' => 1, 'channel_id' => '800000000000000001', 'events' => ['urgent']], $this->now)['ok']);
        self::assertTrue($this->discord()->status(1, $this->now)['ready']);
        self::assertFalse($this->discord()->status(1, $this->now + 3 * 3600)['ready'], 'bot silencieux depuis plus de deux heures');
    }

    public function testDiscordMessagesCarryNoSensitiveDataAndAreSentOnce(): void
    {
        IntelIntakeSqlite::linkDiscord($this->pdo, $this->now);
        // Activé il y a cinq heures : les deux remontées récentes sont concernées, la fiche de septembre non.
        self::assertTrue($this->discord()->save(1, ['enabled' => 1, 'channel_id' => '800000000000000001', 'role_id' => '700000000000000001', 'events' => ['new', 'urgent', 'late', 'ack', 'forward']], $this->now - 5 * 3600)['ok']);
        self::assertTrue($this->act('fiche', $this->ids['b'], 'discord')['ok']);
        self::assertFalse($this->act('fiche', $this->ids['b'], 'discord')['ok'], 'pas deux demandes en dix minutes');

        $bureau = new DiscordBotBureau($this->pdo, new DiscordBotRepository($this->pdo), null, static fn (int $t, array $s): array => [], static fn (string $p): string => 'https://athena.test/' . $p);
        $bot = new IntelIntakeFakeBot($bureau, '900000000000000001');
        $sent = $bot->run($this->now);
        $kinds = array_column($sent, 'kind');
        self::assertSame(['remontee'], array_values(array_unique($kinds)), 'les rappels de gestion sont coupés dans ce jeu de données');
        $events = array_map(static fn (array $m): string => $m['data']['event'] . ' ' . $m['data']['ref'], $sent);
        sort($events);
        self::assertSame(['forward FR-2026-000041', 'forward FR-2026-000041', 'late FR-2026-000041', 'late SPOT-2026-0117', 'manual FR-2026-000041', 'urgent FR-2026-000041', 'urgent SPOT-2026-0117'], $events);
        $all = json_encode($sent, JSON_UNESCAPED_UNICODE);
        foreach (['colis relié', 'Karimi', 'blessé', '76561198', 'Léa Morel', 'Thomas Armand', 'Section Alpha', 'Engin suspect sous le pont nord'] as $secret) {
            self::assertStringNotContainsString($secret, (string) $all, $secret);
        }
        foreach ($sent as $m) {
            self::assertSame('800000000000000001', $m['to']);
            self::assertSame('700000000000000001', $m['data']['role']);
            self::assertSame(['event', 'ref', 'type', 'urgency', 'urgency_label', 'emitter', 'title', 'link', 'role'], array_keys($m['data']));
            if ($m['data']['ref'] === 'FR-2026-000041') {
                // Diffusion « Confidentiel » et passage caviardé : ni émetteur ni titre.
                self::assertSame(['', ''], [$m['data']['emitter'], $m['data']['title']]);
                self::assertSame('https://athena.test/atak/sse/remontees/fiche/' . $this->ids['b'], $m['data']['link'], 'le lien mène au bureau SSE');
            } else {
                self::assertSame(['VIPER', 'Colis suspect au pont nord'], [$m['data']['emitter'], $m['data']['title']]);
            }
        }
        // Anti-doublon : ce qui est acquitté ne repart pas ; le journal de la fiche le montre.
        self::assertSame([], $bot->run($this->now + 60));
        self::assertSame(7, (int) $this->pdo->query("SELECT COUNT(*) FROM discord_bot_reminders WHERE kind = 'remontee'")->fetchColumn());
        $journal = $this->discord()->journal(1, $this->intake()->find(1, 'fiche', $this->ids['b']));
        self::assertContains('manual', array_column($journal, 'event'));
        self::assertNotContains('', array_column($journal, 'sent_at'));

        // Un envoi qui échoue n'est pas acquitté : il reste dû, sans rien bloquer.
        self::assertTrue($this->act('cr', $this->ids['cr'], 'take')['ok']);
        $bot->failing = true;
        self::assertSame([], $bot->run($this->now + 120));
        $bot->failing = false;
        self::assertSame(['ack SPOT-2026-0117'], array_map(static fn (array $m): string => $m['data']['event'] . ' ' . $m['data']['ref'], $bot->run($this->now + 180)));
    }

    public function testNothingFromBeforeActivationIsSent(): void
    {
        IntelIntakeSqlite::linkDiscord($this->pdo, $this->now);
        self::assertTrue($this->discord()->save(1, ['enabled' => 1, 'channel_id' => '800000000000000001', 'events' => ['new', 'urgent', 'late']], $this->now)['ok']);
        self::assertSame([], $this->discord()->pending(1, $this->now + 30));
        self::assertTrue($this->discord()->save(1, ['enabled' => 0, 'channel_id' => '800000000000000001'], $this->now)['ok']);
        self::assertSame([], $this->discord()->pending(1, $this->now + 30));
    }

    // ------------------------------------------------------------------ droits, jeton, cloisonnement

    public function testEachActionNeedsItsRight(): void
    {
        $none = IntelIntakeDesk::rights([]);
        foreach (array_keys(IntelIntakeDesk::ACTION_RIGHT) as $action) {
            $res = $this->act('fiche', $this->ids['b'], $action, ['state' => 'close', 'body' => 'x', 'kind' => 'dossier', 'confirm' => 'FR-2026-000041'], $none);
            self::assertFalse($res['ok'], $action);
            self::assertNotSame('Action non autorisée', $res['message']);
            self::assertNotSame('', $res['message']);
        }
        $item = $this->intake()->find(1, 'fiche', $this->ids['b']);
        self::assertSame('a_traiter', $item['state']);
        self::assertFalse($item['deleted']);
        self::assertCount(10, $item['events'], 'rien n’a été écrit');

        // Traiter sans gérer : commenter oui, supprimer, modifier et caviarder non.
        $treat = ['treat' => true];
        self::assertTrue($this->act('fiche', $this->ids['b'], 'comment', ['body' => 'Je regarde.'], $treat)['ok']);
        self::assertFalse($this->act('fiche', $this->ids['b'], 'delete', ['confirm' => 'FR-2026-000041'], $treat)['ok']);
        self::assertFalse($this->act('fiche', $this->ids['b'], 'edit', ['title' => 'Autre'], $treat)['ok']);
        self::assertFalse($this->act('fiche', $this->ids['b'], 'redact', ['field' => 'body', 'phrase' => 'colis'], $treat)['ok']);
        self::assertSame('Action impossible.', $this->act('fiche', $this->ids['b'], 'inconnue')['message']);
    }

    public function testReaderPageOffersNoActionAndBarsRedactedText(): void
    {
        $html = $this->showPage('fiche', $this->ids['b'], IntelIntakeDesk::rights([]), 'interne');
        self::assertSame(0, preg_match_all('~<form\b~', $html));
        self::assertSame(0, preg_match_all('~<dialog\b~', $html));
        self::assertStringNotContainsString('chez Karimi', $html);
        self::assertStringContainsString('rmt-bar', $html);
        self::assertStringNotContainsString('Proposé par Cerbère', $html);
        // L'état, le cycle et les pastilles restent lisibles.
        self::assertStringContainsString('rmt-steps', $html);
        self::assertStringContainsString('En retard', $html);
    }

    public function testDeletionNeedsTheReferenceTypedBack(): void
    {
        self::assertFalse($this->act('fiche', $this->ids['a'], 'delete', ['reason' => 'Essai du téléphone'])['ok']);
        self::assertFalse($this->act('fiche', $this->ids['a'], 'delete', ['reason' => 'Essai', 'confirm' => 'FR-2026-000041'])['ok']);
        self::assertFalse($this->intake()->find(1, 'fiche', $this->ids['a'])['deleted']);
        $res = $this->act('fiche', $this->ids['a'], 'delete', ['reason' => 'Essai du téléphone', 'confirm' => 'fr-2026-000002']);
        self::assertTrue($res['ok']);
        self::assertTrue($res['deleted']);
        self::assertTrue($this->intake()->find(1, 'fiche', $this->ids['a'])['deleted']);
        self::assertTrue($this->act('fiche', $this->ids['a'], 'restore')['ok']);
    }

    public function testAnotherCommunityNeverReachesAReport(): void
    {
        self::assertNull($this->desk()->view(2, 'fiche', $this->ids['b'], ['id' => 90, 'level' => 'tres_restreint'], self::ALL));
        foreach (['take', 'comment', 'state', 'delete', 'steam', 'convert', 'discord'] as $action) {
            $res = $this->act('fiche', $this->ids['b'], $action, ['body' => 'x', 'state' => 'close', 'kind' => 'dossier', 'confirm' => 'FR-2026-000041'], null, 2);
            self::assertSame([false, 'Remontée introuvable.'], [$res['ok'], $res['message']], $action);
        }
        self::assertSame('a_traiter', $this->intake()->find(1, 'fiche', $this->ids['b'])['state']);
        self::assertSame([], AdminIntelIntakeController::suggest($this->desk(), 2, 'fiche', $this->ids['b'], 'transmettre', '', true));
        // Attribuer à un membre d'une autre communauté est refusé.
        self::assertFalse($this->act('fiche', $this->ids['b'], 'assign', ['user_id' => 90])['ok']);
        self::assertNull($this->intake()->find(1, 'fiche', $this->ids['b'])['assignee']);
        // Les listes des fenêtres restent dans la communauté.
        $dup = AdminIntelIntakeController::suggest($this->desk(), 1, 'fiche', $this->ids['b'], 'doublon', 'FR-2026', true);
        self::assertSame(['fiche/' . $this->ids['a']], array_column($dup, 'value'));
        self::assertStringNotContainsString('munitions', json_encode($dup, JSON_UNESCAPED_UNICODE), 'référence et type seulement');
        self::assertSame([], AdminIntelIntakeController::suggest($this->desk(), 1, 'cr', $this->ids['cr'], 'lien', 'nidasos', true));
    }

    public function testRoutesAndTokenGuards(): void
    {
        $routes = str_replace("\r\n", "\n", (string) file_get_contents(base_path('routes/web.php')));
        foreach ([
            "get('/back-office/remontees/{source}/{id}'", "post('/back-office/remontees/{source}/{id}/{action}'",
            "get('/back-office/remontees/{source}/{id}/suggestions'", "get('/atak/sse/remontees'", "get('/atak/sse/remontees/{source}/{id}'",
            "post('/atak/sse/remontees/{source}/{id}/{action}'", "post('/back-office/discord-bot/remontees'",
            "get('/atak/sse/remontees/joueurs'", "get('/atak/sse/remontees/joueurs/{key}'", "get('/atak/sse/remontees/reglages'", "post('/atak/sse/remontees/reglages'",
        ] as $route) {
            self::assertSame(1, substr_count($routes, '$router->' . $route), $route);
        }
        // Back-office : le jeton est exigé par l'intergiciel commun à tout /back-office/.
        $middleware = (string) file_get_contents(base_path('app/Middleware/CsrfPostMiddleware.php'));
        self::assertStringContainsString("'/back-office/',", $middleware);
        // Portail SSE : le contrôleur valide lui-même le jeton avant toute action ; les droits de chaque action sont ceux du service.
        $sse = (string) file_get_contents(base_path('app/Controllers/Web/SseIntakeController.php'));
        self::assertLessThan(strpos($sse, '->perform('), strpos($sse, 'Csrf::validate($token)'));
        self::assertStringContainsString('IntelIntakeDesk::ACTION_RIGHT[$action]', $sse);
        // Une session invitée n'a aucun droit sur les remontées.
        self::assertSame(IntelIntakeDesk::rights([]), SseIntakeController::sessionRights(true, 1));
        self::assertSame('terrain', \App\Support\SseAccessCatalog::sectionForPath('/atak/sse/remontees/cr/4'));
    }

    // ------------------------------------------------------------------ vue du bureau SSE

    public function testSsePanelShowsTheSameStateAndActionsFollowRights(): void
    {
        $vars = fn (array $rights, string $part): array => [
            'intakeDesk' => $this->desk()->view(1, 'fiche', $this->ids['b'], ['id' => 7, 'level' => 'interne'], $rights, $this->now),
            'intakeMembers' => $this->intake()->members(1), 'intakeTypes' => [], 'intakeBackOffice' => false, 'intakePart' => $part,
        ];
        $treat = ['treat' => true, 'convert' => true, 'discord' => true];
        $panel = $this->render('atak/sse/_intake_suivi', $vars($treat, 'panel'));
        self::assertStringContainsString('À traiter', $panel);
        self::assertStringContainsString('En retard de', $panel);
        self::assertStringContainsString('Caviardée', $panel);
        self::assertStringContainsString('action="' . url('atak/sse/remontees/fiche/' . $this->ids['b'] . '/take') . '"', $panel);
        self::assertStringContainsString('Proposé par Cerbère', $panel);
        self::assertStringContainsString('Qui prend ? Le délai est dépassé.', $panel);
        self::assertStringNotContainsString('back-office/remontees', $panel, 'pas de lien vers le back-office sans y avoir accès');
        self::assertStringNotContainsString('sse-ds', $panel);
        $dialogs = $this->render('atak/sse/_intake_suivi', $vars($treat, 'dialogs'));
        self::assertSame(6, preg_match_all('~<dialog class="fnr-dialog~', $dialogs));
        self::assertSame(preg_match_all('~<form\b~', $dialogs), preg_match_all('~name="_csrf_token"~', $dialogs));
        self::assertStringNotContainsString('/delete', $dialogs . $panel);
        self::assertStringNotContainsString('76561198', $dialogs . $panel);

        // Lecteur : l'état, le cycle et les pastilles, sans fil ni action.
        $reader = $this->render('atak/sse/_intake_suivi', $vars([], 'panel'));
        self::assertStringContainsString('À traiter', $reader);
        self::assertStringContainsString('fnr-cycle', $reader);
        self::assertSame(0, preg_match_all('~<form\b|<button\b~', $reader));
        self::assertStringNotContainsString('Qui prend', $reader);
        self::assertSame('', trim($this->render('atak/sse/_intake_suivi', $vars([], 'dialogs'))));

        // Le traitement fait depuis le portail est celui du back-office : même service, même fil.
        self::assertTrue($this->act('fiche', $this->ids['b'], 'take', [], $treat)['ok']);
        self::assertStringContainsString('Prise en compte', $this->showPage('fiche', $this->ids['b']));
        $host = (string) file_get_contents(base_path('views/atak/sse/field_note_show.php'));
        self::assertSame(2, substr_count($host, "require __DIR__ . '/_intake_suivi.php'"));
        self::assertStringContainsString("'path' => 'atak/sse/remontees'", (string) file_get_contents(base_path('app/Support/SsePortalNav.php')));
    }
}

/**
 * Bot Discord joué à la main : il vient chercher ses rappels comme le vrai (DiscordBotBureau::reminders), « envoie »
 * en mémoire puis acquitte. Aucun appel réseau.
 */
final class IntelIntakeFakeBot
{
    public bool $failing = false;

    public function __construct(private DiscordBotBureau $bureau, private string $guild)
    {
    }

    /** @return list<array<string, mixed>> messages envoyés pendant ce passage */
    public function run(int $now): array
    {
        $sent = [];
        foreach ($this->bureau->reminders($this->guild, $now) as $item) {
            if ($this->failing) {
                continue;
            }
            $sent[] = $item;
            $this->bureau->acknowledge($this->guild, (string) $item['kind'], (string) $item['ref']);
        }

        return $sent;
    }
}
