<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseMemberImageGate;
use App\Services\Sse\SsePersonViewer;
use App\Services\Sse\SsePersonWorkService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SsePersonWorkSqlite;

/**
 * Rendu de la page Personnes (liste, tiroir, fenêtres modales) sur une base réelle : l'avertissement de constat n'apparaît que dans
 * le tiroir, signature et avis sont lisibles sur chaque ligne, une ligne restreinte ne dit rien, aucune capitale en tête de titre,
 * textes d'au moins 13 px, cibles de 44 px, raccourcis présents, aucune mention de simulation.
 */
final class SsePersonWorkViewTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SsePersonWorkSqlite::open();
        SseMemberImageGate::useForSession(new SseMemberImageGate(null, null, static fn (): array => [], static fn (): bool => false, static function (array $row): void {
        }, null, null, null, null));
    }

    protected function tearDown(): void
    {
        SsePersonWorkSqlite::close();
        SseMemberImageGate::useForSession(null);
    }

    private function viewer(string $level = 'confidentiel', bool $write = true): SsePersonViewer
    {
        return new SsePersonViewer(1, 7, false, true, $write, true, true, $level, true, true, 'ANALYSTE', true);
    }

    /** @param array<string, mixed> $extra */
    private function person(string $last, array $extra = []): int
    {
        $row = $extra + ['tenant_id' => 1, 'last_name' => $last, 'first_name' => 'Test', 'created_at' => gmdate('Y-m-d H:i:s', time() - 86400), 'updated_at' => gmdate('Y-m-d H:i:s', time() - 3600)];
        $this->pdo->prepare('INSERT INTO sse_persons (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $query */
    private function page(array $query = [], ?SsePersonViewer $viewer = null): string
    {
        $viewer ??= $this->viewer();
        $listing = (new SsePersonWorkService())->listing($viewer, $query);
        $csrf = 'jeton';
        $caseOptions = [];
        $now = time();
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        ob_start();
        require self::ROOT . '/views/atak/sse/persons/_content.php';

        return (string) ob_get_clean();
    }

    private function drawer(int $id, ?SsePersonViewer $viewer = null): string
    {
        $viewer ??= $this->viewer();
        $d = (new SsePersonWorkService())->detail($viewer, $id);
        self::assertNotNull($d);
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        ob_start();
        require self::ROOT . '/views/atak/sse/persons/_drawer.php';

        return (string) ob_get_clean();
    }

    public function testObservationWarningAppearsOnceAndOnlyInTheDrawer(): void
    {
        $medical = json_encode(['etat_label' => 'Conscient', 'pouls' => 80, 'lesions' => ['Plaie']], JSON_UNESCAPED_UNICODE);
        $a = $this->person('Alpha', ['medical_context_json' => $medical]);
        $this->person('Bravo', ['medical_context_json' => $medical]);
        $list = $this->page();
        self::assertStringNotContainsString('bilan médical', $list, 'Plus d’encadré répété sur chaque carte');
        self::assertStringNotContainsString('Constat d’observation', $list);
        $drawer = $this->drawer($a);
        self::assertSame(1, substr_count($drawer, 'Constat d’observation — ne remplace pas un bilan médical.'));
        self::assertStringContainsString('class="pe-note"', $drawer);
        self::assertStringNotContainsString('sse-note', $list . $drawer);
    }

    public function testEveryRowShowsSignatureNoticeAndStatus(): void
    {
        $a = $this->person('Alpha', ['signed_by_callsign' => 'TA1', 'signed_terminal_uid' => 'T-9', 'signed_at' => '2026-11-01 10:00:00', 'status' => 'detenu']);
        $b = $this->person('Bravo');
        $identity = (new \App\Repositories\SseIdentityRepository())->create(1, ['last_name' => 'X', 'first_name' => 'Y', 'kind' => 'civil', 'person_id' => $b]);
        $this->pdo->prepare("INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, ref_year, ref_seq, notice_type, threat_level, armed, status, active_key, issued_at, created_at) VALUES (1, ?, 'A-0042/2026', 2026, 42, 'poi', 'critical', 'unknown', 'active', 1, '2026-11-10', '2026-11-10')")->execute([$identity]);
        $html = $this->page();
        self::assertStringContainsString('Signé', $html);
        self::assertStringContainsString('TA1', $html);
        self::assertStringContainsString('Terminal T-9', $html);
        self::assertStringContainsString('Non signé', $html);
        self::assertStringContainsString('Recherché', $html);
        self::assertStringContainsString('Critique', $html);
        self::assertStringContainsString('papiers/identites/' . $identity, $html, 'Lien direct vers l’avis');
        self::assertStringContainsString('Placer un avis de recherche', $html, 'Bouton pour la personne sans avis');
        self::assertStringContainsString('Détenu', $html);
        self::assertStringContainsString('IDN-' . str_pad((string) $a, 5, '0', STR_PAD_LEFT), $html);
        // Dates d'ouverture et de lecture.
        self::assertStringContainsString('Jamais ouverte', $html);
        self::assertStringContainsString('Ouverte · lue', $html);
        self::assertStringContainsString('Modifiée', $html);
        $drawer = $this->drawer($b);
        self::assertStringContainsString('Recherché · Critique', $drawer);
        self::assertStringContainsString('Ouvrir l’avis', $drawer);
        self::assertStringContainsString('Non signé', $drawer);
        self::assertStringContainsString('Aucune signature du terminal', $drawer);
    }

    private function wantedPerson(string $last, string $threat, ?string $expires, string $armed = 'unknown'): int
    {
        $p = $this->person($last, ['alias' => 'Corbeau']);
        $identity = (new \App\Repositories\SseIdentityRepository())->create(1, ['last_name' => $last, 'first_name' => 'Y', 'kind' => 'civil', 'person_id' => $p]);
        $this->pdo->prepare("INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, ref_year, ref_seq, notice_type, threat_level, armed, status, active_key, issued_at, expires_at, created_at) VALUES (1, ?, ?, 2026, ?, 'arrest', ?, ?, 'active', 1, '2026-11-10', ?, '2026-11-10')")->execute([$identity, 'A-00' . $p . '/2026', $p, $threat, $armed, $expires]);

        return $p;
    }

    public function testListShowsSeparateImageNameFirstNameAndAliasColumns(): void
    {
        $this->person('Dupont', ['first_name' => 'Marie', 'alias' => 'Faucon']);
        $html = $this->page();
        foreach (['pe-c-img">Image', 'pe-c-person"><a', 'pe-c-first">Prénom', 'pe-c-alias">Alias'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        self::assertMatchesRegularExpression('/<td class="pe-c-first">Marie<\/td>/', $html);
        self::assertMatchesRegularExpression('/<td class="pe-c-alias">Faucon<\/td>/', $html);
        self::assertMatchesRegularExpression('/class="pe-name"[^>]*>Dupont<\/a>/', $html, 'La colonne Nom ne porte que le nom de famille');
        self::assertStringContainsString('sort=nom', $html, 'Le tri par nom reste');
    }

    public function testWantedIsALetterRWithColourLevelEndDateAndTooltip(): void
    {
        // Échéance relative à aujourd'hui : une date fixe finit par être passée et l'avis n'est alors plus actif.
        $end = new \DateTimeImmutable('+30 days 12:00:00');
        $this->wantedPerson('Alpha', 'medium', $end->format('Y-m-d H:i:s'));
        $this->wantedPerson('Bravo', 'critical', null, 'yes');
        $html = $this->page();
        self::assertMatchesRegularExpression('/class="pe-r"[^>]*>\s*<span aria-hidden="true">R<\/span>/', $html);
        self::assertStringContainsString('fin ' . $end->format('d/m'), $html, 'Fin de validité en mention courte');
        self::assertStringContainsString('jusqu’à levée', $html);
        self::assertStringContainsString('role="tooltip"', $html);
        self::assertStringContainsString('aria-describedby="pe-tip-w-t', $html);
        self::assertStringContainsString('Fin de validité : ' . $end->format('d/m/Y'), $html);
        self::assertStringContainsString('Légende', $html);
        self::assertStringContainsString('R = personne recherchée', $html);
        self::assertStringContainsString('Niveau 2 sur 4', $html, 'Moyen : vigilance renforcée');
        self::assertStringContainsString('Niveau 4 sur 4', $html, 'Armé : danger');
        self::assertStringContainsString('--lv-bg:#F08C00', $html, 'Couleur de l’échelle SseAlertLevel');
        self::assertStringNotContainsString('Recherché<small>', $html, 'Plus de badge long');
    }

    public function testDangerColumnClimbsInFourStepsAndWritesItsLevel(): void
    {
        $this->wantedPerson('Alpha', 'low', null);
        $this->wantedPerson('Bravo', 'critical', null);
        $html = $this->page();
        self::assertStringContainsString('pe-c-danger">Danger', $html);
        self::assertStringContainsString('aria-label="Dangerosité : Faible, niveau 1 sur 4"', $html);
        self::assertStringContainsString('aria-label="Dangerosité : Critique, niveau 4 sur 4"', $html);
        self::assertGreaterThanOrEqual(4, preg_match_all('/<span class="pe-lv" aria-hidden="true">(<i( class="on")?><\/i>){4}<\/span>/', $html), 'Quatre barres par indicateur');
    }

    public function testSignatureIsSOrNsWithTooltipDetail(): void
    {
        $this->person('Signee', ['signed_by_callsign' => 'TA1', 'signed_terminal_uid' => 'T-9', 'signed_at' => '2026-11-01 10:00:00']);
        $this->person('Libre');
        $html = $this->page();
        self::assertMatchesRegularExpression('/class="pe-sg pe-sg--s"[^>]*aria-label="Signé par TA1"><span aria-hidden="true">S<\/span>/', $html);
        self::assertMatchesRegularExpression('/class="pe-sg pe-sg--ns"[^>]*aria-label="Non signé"><span aria-hidden="true">NS<\/span>/', $html);
        self::assertStringContainsString('Par : TA1', $html);
        self::assertStringContainsString('S = signé, NS = non signé.', $html);
        self::assertStringNotContainsString('pe-badge--ok', $html);
    }

    public function testNewColumnsKeepAccessibleSizesAndNoDesignSystemDependency(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/css/sse-persons.css');
        self::assertMatchesRegularExpression('/\.pe-tip \{[^}]*min-width: 44px; min-height: 44px/', $css, 'Cible tactile de 44 px');
        self::assertStringContainsString('.pe-tip.is-open > .pe-tip__box', $css);
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/sse-persons.js');
        self::assertStringContainsString('focusin', $js, 'Info-bulle au focus clavier');
        self::assertStringContainsString("'touch'", $js, 'Info-bulle au toucher');
    }

    public function testBiometryIsThreePillsNeverBars(): void
    {
        $a = $this->person('Alpha');
        $this->pdo->prepare("INSERT INTO sse_biometric_samples (person_id, tenant_id, kind, quality) VALUES (?, 1, 'iris', 40)")->execute([$a]);
        $html = $this->page();
        self::assertSame(2 * 3, substr_count($html, 'class="pe-pill pe-pill--'), 'Trois pastilles dans la ligne et trois dans la carte');
        self::assertStringContainsString('pe-pill--partial', $html);
        self::assertStringNotContainsString('sse-gauge', $html);
        self::assertStringNotContainsString('Relevés biométriques', $html, 'Le détail est dans le tiroir');
    }

    public function testRestrictedRowTellsNothing(): void
    {
        $this->person('Verrouillée-Secrète', ['classification' => 'tres_restreint', 'locked_at' => '2026-11-01 00:00:00', 'alias' => 'Ombre']);
        $this->person('Visible');
        $html = $this->page();
        self::assertStringContainsString('Élément restreint', $html);
        self::assertStringNotContainsString('Verrouillée-Secrète', $html);
        self::assertStringNotContainsString('Ombre', $html);
        self::assertSame(1 * 2, substr_count($html, 'pe-avatar--lock'), 'Une ligne et une carte, sans lien ni case à cocher');
        self::assertSame(2, substr_count($html, 'data-pe-select'), 'Seule la fiche visible se coche (ligne et carte)');
        self::assertStringContainsString('Visible', $html);
    }

    public function testClassificationAndLockAreShownAndShortcutsAreListed(): void
    {
        $this->person('Alpha', ['classification' => 'confidentiel', 'locked_at' => '2026-11-01 00:00:00']);
        $html = $this->page();
        self::assertStringContainsString('pe-class--warn', $html);
        self::assertStringContainsString('>S<', $html);
        self::assertStringContainsString('pe-lockmark', $html);
        foreach (['<kbd>j</kbd> <kbd>k</kbd>', '<kbd>Entrée</kbd>', '<kbd>o</kbd>', '<kbd>x</kbd>', '<kbd>c</kbd>', '<kbd>l</kbd>', '<kbd>e</kbd>', '<kbd>a</kbd>', '<kbd>s</kbd>', '<kbd>/</kbd>', '<kbd>g</kbd> puis <kbd>p</kbd>', '<kbd>g</kbd> puis <kbd>f</kbd>', '<kbd>?</kbd>', 'data-pe-keys-toggle'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/sse-persons.js');
        foreach (["case 'j'", "case 'k'", "case 'Enter'", "case 'o'", "case 'x'", "case 'c'", "case 'l'", "case 'e'", "case 'a'", "case 's'", "case '/'", "case 'g'", "case '?'", "k === 'p'", "k === 'f'"] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        self::assertStringContainsString('typing(e.target)', $js, 'Aucun raccourci pendant une saisie');
        self::assertStringContainsString("$('dialog[open]')", $js, 'Aucun raccourci pendant une fenêtre ouverte');
        self::assertStringContainsString('pe.keys', $js, 'Désactivables et mémorisés');
    }

    public function testTabsHaveCountersAndEveryActionHasItsDialog(): void
    {
        $this->person('Alpha');
        $html = $this->page();
        foreach (['Toutes', 'Recherchées', 'Non signées', 'Classifiées', 'Verrouillées', 'Closes', 'Récentes'] as $tab) {
            self::assertStringContainsString('<span>' . $tab . '</span><span class="pe-count">', $html);
        }
        foreach (['classify', 'lock', 'unlock', 'close', 'reopen', 'settings', 'help'] as $dialog) {
            self::assertStringContainsString('id="pe-dlg-' . $dialog . '"', $html);
        }
        foreach (['Résolu', 'Neutralisé', 'Détenu transféré', 'Décédé', 'Doublon fusionné', 'Erreur'] as $reason) {
            self::assertStringContainsString('>' . $reason . '</option>', $html);
        }
        self::assertStringContainsString('name="_csrf_token" value="jeton"', $html);
        self::assertStringContainsString('Tableau', $html);
        self::assertStringContainsString('Cartes compactes', $html);
        self::assertStringContainsString('Exporter CSV', $html);
        // Rattacher une sélection passe par la fenêtre universelle (SseAttachWindow), plus par une liste déroulante de dossiers.
        self::assertStringContainsString('data-ra-open data-ra-type="person" data-ra-collect=".pe-check input:checked"', $html);
        self::assertStringNotContainsString('id="pe-dlg-link"', $html);
        self::assertStringNotContainsString('Ajouter à un dossier', $html);
    }

    public function testDialogsOnlyOfferLevelsUpToTheViewersClearance(): void
    {
        $this->person('Alpha');
        $html = $this->page([], $this->viewer('encadrement'));
        self::assertStringContainsString('value="encadrement"', $html);
        $classify = substr($html, (int) strpos($html, 'id="pe-dlg-classify"'), 1400);
        self::assertStringContainsString('value="interne"', $classify);
        self::assertStringNotContainsString('value="confidentiel"', $classify);
        self::assertStringNotContainsString('value="tres_restreint"', $classify);
        $settings = substr($html, (int) strpos($html, 'id="pe-dlg-settings"'), 1500);
        self::assertStringNotContainsString('value="tres_restreint"', $settings);
    }

    public function testActionsAreDisabledWithAClearMessageWhenNotAllowed(): void
    {
        $this->person('Alpha');
        $readOnly = $this->page([], $this->viewer('confidentiel', false));
        self::assertStringContainsString('Droit manquant : écrire dans « Fichiers et sujets ».', $readOnly);
        self::assertStringNotContainsString('data-pe-act="classify" data-ids="1">', $readOnly);

        SsePersonWorkSqlite::close();
        $this->pdo = SsePersonWorkSqlite::open(false, false);
        $this->person('Alpha');
        $degraded = $this->page();
        self::assertStringContainsString('seront disponibles après la mise à jour de la base de données', $degraded);
        self::assertStringContainsString('Disponible après la mise à jour de la base de données', $degraded);
    }

    public function testEmptyStatesExplain(): void
    {
        $empty = $this->page();
        self::assertStringContainsString('Aucune personne enregistrée', $empty);
        $this->person('Alpha');
        self::assertStringContainsString('Aucune personne ne correspond', $this->page(['q' => 'zzz']));
        self::assertStringContainsString('Aucun dossier clos', $this->page(['tab' => 'closes']));
        self::assertStringContainsString('Aucune fiche verrouillée', $this->page(['tab' => 'verrouillees']));
        self::assertStringContainsString('Retirer ce filtre', $this->page(['q' => 'zzz']));
    }

    public function testClosedFilesAreHiddenFromTheMainViewButTheEmptyStateSaysWhere(): void
    {
        $this->person('Close', ['closed_at' => '2026-11-01 00:00:00', 'close_reason' => 'resolu']);
        $main = $this->page();
        self::assertStringContainsString('Toutes les fiches sont closes', $main);
        self::assertStringNotContainsString('>Close Test<', $main);
        $closed = $this->page(['tab' => 'closes']);
        self::assertStringContainsString('Clos', $closed);
        self::assertStringContainsString('Résolu', $closed);
        self::assertStringContainsString('data-pe-act="reopen"', $closed);
    }

    public function testNoEyebrowSmallTextOrSimulationWording(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/css/sse-persons.css');
        $files = [
            'css' => $css,
            'js' => (string) file_get_contents(self::ROOT . '/public/assets/js/sse-persons.js'),
        ];
        foreach (glob(self::ROOT . '/views/atak/sse/persons/*.php') ?: [] as $f) {
            $files[basename($f)] = (string) file_get_contents($f);
        }
        $files['persons.php'] = (string) file_get_contents(self::ROOT . '/views/atak/sse/persons.php');
        foreach ($files as $name => $src) {
            self::assertDoesNotMatchRegularExpression('/simulation|simulé|simulee|simulée/i', $src, $name . ' ne parle pas de simulation');
            self::assertStringNotContainsString('eyebrow', strtolower($src), $name);
            self::assertStringNotContainsString('kicker', strtolower($src), $name);
        }
        self::assertStringNotContainsString('uppercase', $css, 'Aucun libellé en capitales au-dessus d’un titre');
        // Taille de texte : jamais sous 13 px.
        preg_match_all('/font-size:\s*([0-9.]+)(px|rem|em)/', $css, $m, PREG_SET_ORDER);
        self::assertNotEmpty($m);
        foreach ($m as $size) {
            $px = $size[2] === 'px' ? (float) $size[1] : (float) $size[1] * 16;
            self::assertGreaterThanOrEqual(13.0, $px, 'Texte trop petit : ' . $size[0]);
        }
        // Cibles de 44 px : boutons, cases, onglets, actions de ligne.
        foreach (['.pe-btn, .pe-act, .pe-seg__btn' => 'min-height: 44px', '.pe-check {' => 'width: 44px; height: 44px', '.pe-tab {' => 'min-height: 48px', '.pe-chip {' => 'min-height: 44px'] as $selector => $rule) {
            $at = strpos($css, $selector);
            self::assertNotFalse($at, $selector);
            self::assertStringContainsString($rule, substr($css, $at, 420), $selector);
        }
        self::assertStringContainsString('--pe-row: 60px', $css, 'Lignes de 56 à 64 px');
        self::assertStringNotContainsString('sse-ds', $css . $files['persons.php'], 'Pas de dépendance au design system SSE retiré');
        self::assertStringNotContainsString('Archivo', $css);
    }

    public function testDrawerHasTabsAFoldedCustodyChainAndActions(): void
    {
        $a = $this->person('Alpha');
        $this->pdo->exec("INSERT INTO sse_custody_events (tenant_id, person_id, event_type, label, actor_callsign, created_at) VALUES (1, {$a}, 'capture', 'Personne enregistrée', 'TA1', '2026-11-12 09:10:00')");
        $drawer = $this->drawer($a);
        foreach (['Résumé', 'Biométrie', 'Suivi'] as $tab) {
            self::assertStringContainsString('>' . $tab . '</button>', $drawer);
        }
        self::assertStringContainsString('role="tablist"', $drawer);
        self::assertStringContainsString('<details class="pe-fold">', $drawer);
        self::assertStringContainsString('Chaîne de possession (1)', $drawer);
        self::assertStringContainsString('Ouvrir la fiche', $drawer);
        foreach (['classify', 'lock', 'close'] as $act) {
            self::assertStringContainsString('data-pe-act="' . $act . '" data-ids="' . $a . '"', $drawer);
        }
        // Une ligne de description sous chaque titre de section.
        self::assertMatchesRegularExpression('#<h3>Identité et circonstances</h3>\s*<p class="pe-dr__desc">#', $drawer);
        self::assertMatchesRegularExpression('#<h3>Relevés biométriques</h3>\s*<p class="pe-dr__desc">#', $drawer);
        self::assertMatchesRegularExpression('#<h3>Suivi du dossier</h3>\s*<p class="pe-dr__desc">#', $drawer);
    }
}
