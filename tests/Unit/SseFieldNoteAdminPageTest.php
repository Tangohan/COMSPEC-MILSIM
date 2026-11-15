<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseFieldNoteAdminController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseTagRepository;
use App\Services\Sse\SseFieldNoteTagService;
use App\Support\SsePortalNav;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFieldNoteTagsFixture as Fx;
use Tests\Support\SseFieldNoteTagsSqlite as Sq;

/**
 * Page « Gestion des fiches » et étiquettes sur les pages des fiches : les VRAIES vues sont rendues avec la vraie coque sur une
 * base sqlite réaliste (communauté réglée, communauté neuve, lecteur sans droit de gestion, base non migrée). Écritures par le
 * vrai contrôleur : droit « gérer », jeton, débit limité, communauté de la session, journal SSE. Règles d'interface : style du
 * portail, aucun petit libellé au-dessus d'un titre, formulaires en fenêtres, textes de 13 px au moins, aucune mention de jeu.
 * Les fichiers sont lus sans dépendre des fins de ligne du dépôt.
 */
final class SseFieldNoteAdminPageTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $ids = [];

    protected function setUp(): void
    {
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1, 'sse_tenant_id' => 1, 'callsign' => 'ANALYSTE'];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SERVER['REQUEST_URI'] = '/atak/sse/fiches/gestion';
        Csrf::token();
        $this->pdo = Sq::open();
        $this->ids = Fx::seed($this->pdo);
    }

    protected function tearDown(): void
    {
        \App\Services\Sse\Links\SseLinksWidget::useViewer();
        Sq::close();
        $_SESSION = [];
        $_POST = [];
    }

    private static function read(string $relative): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relative));
    }

    private function controller(int $tenant = 1, bool $manage = true, bool $read = true, bool $guest = false, bool $throttled = false): SseFieldNoteAdminController
    {
        return new SseFieldNoteAdminController(Fx::service(), Fx::rights($tenant, $manage, $read, $guest), static fn (): bool => $throttled);
    }

    private function page(int $tenant = 1, bool $manage = true): string
    {
        $response = $this->controller($tenant, $manage)->index(new Request(), []);
        self::assertSame(200, $response->statusCode());

        return str_replace("\r\n", "\n", $response->body());
    }

    /** Le contenu propre à la page (sans la coque). */
    private static function own(string $html): string
    {
        $start = strpos($html, '<div class="fnr fng"');
        self::assertNotFalse($start, 'la page est rendue');
        $end = strpos($html, '</main>', (int) $start);

        return substr($html, (int) $start, ($end === false ? strlen($html) : $end) - (int) $start);
    }

    /** @param array<string, mixed> $post */
    private function post(string $action, array $post, ?SseFieldNoteAdminController $controller = null, bool $withToken = true): Response
    {
        $_POST = $post + ($withToken ? ['_csrf_token' => Csrf::token()] : []);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        try {
            return ($controller ?? $this->controller())->save(new Request(), ['action' => $action]);
        } finally {
            $_POST = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }
    }

    private function scalar(string $sql): mixed
    {
        return $this->pdo->query($sql)->fetchColumn();
    }

    private static function view(string $name, array $data): string
    {
        $level = ob_get_level();
        ob_start();
        try {
            (static function (array $__data, string $__file): void {
                extract($__data);
                require $__file;
            })($data, dirname(__DIR__, 2) . '/views/atak/sse/' . $name . '.php');

            return str_replace("\r\n", "\n", (string) ob_get_clean());
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    public function testManagerSeesSixTabsDenseTablesAndModalForms(): void
    {
        $html = $this->page();
        self::assertStringContainsString('<!DOCTYPE html>', $html, 'la vraie coque du portail');
        $page = self::own($html);
        self::assertMatchesRegularExpression('#<span class="fng-strip__index" aria-hidden="true">F\.10</span>\s*<div>\s*<h1 class="fng-title">Gestion des fiches</h1>\s*<p class="fng-strip__desc">#', $page);
        self::assertMatchesRegularExpression('#<p class="fng-key"><strong>35</strong><span>étiquettes actives</span></p>#', $page);
        foreach (['<dd>8</dd>', '<dd>3</dd>', '<dd>60</dd>', '<dd>4</dd>'] as $figure) {
            self::assertStringContainsString($figure, $page);
        }
        // Six onglets, chacun son titre et UNE ligne de description.
        self::assertSame(6, substr_count($page, 'role="tab"') - substr_count($page, 'class="fnp-fam"'));
        foreach (SseFieldNoteAdminController::TABS as $key => $label) {
            self::assertStringContainsString('data-fnr-tab="' . $key . '">' . $label . '</button>', $page);
            self::assertMatchesRegularExpression('#id="fng-panel-' . $key . '"[^>]*>\s*<div class="fng-panel__head">\s*<div>\s*<h2 class="fng-h2">' . preg_quote($label, '#') . '</h2>\s*<p class="fng-desc">[^<]+</p>\s*</div>#', $page, $key);
        }
        self::assertSame(1, preg_match_all('#<section class="fng-panel"[^>]*data-fnr-panel="[a-z]+">#', $page), 'un seul onglet ouvert, les autres repliés');
        // Tableaux denses, triables et filtrables.
        self::assertSame(4, substr_count($page, 'data-fng-table="'));
        self::assertGreaterThan(15, substr_count($page, 'data-fng-sort='));
        foreach (['data-fng-filter="tags"', 'data-fng-attr="family"', 'data-fng-attr="status"', 'data-fng-attr="origin"', 'data-fng-count="tags"', 'data-fng-filter="cats"'] as $needle) {
            self::assertStringContainsString($needle, $page, $needle);
        }
        foreach (['HUMINT', 'PIR', 'SPOTREP', 'NOFORN', 'PERISHABLE', 'ROUTE WATCH', 'LTIOV', 'OP HIVER', 'Besoin prioritaire du chef de corps', 'Priority intelligence requirement', 'Stupéfiants', 'Armement / Matériel', 'Fiche d’entretien', 'Effectif :', 'REL TO USA, ALTIS', 'Usually reliable', 'Probably true', 'IMMEDIATE'] as $text) {
            self::assertStringContainsString($text, $page, $text);
        }
        self::assertSame(3, substr_count($page, 'fng-state--archived'));
        // Création, modification, état, fusion, rétablissement, type, trame, règles, étiquettes obligatoires, import : en fenêtres.
        $dialogs = ['fng-dlg-etiquette', 'fng-dlg-etat', 'fng-dlg-fusion', 'fng-dlg-retablir', 'fng-dlg-type', 'fng-dlg-modele', 'fng-dlg-regles', 'fng-dlg-requis', 'fng-dlg-import'];
        self::assertSame(count($dialogs), substr_count($page, '<dialog class="fnr-dialog'));
        foreach ($dialogs as $id) {
            self::assertSame(1, substr_count($page, ' id="' . $id . '" aria-labelledby="' . $id . '-title"'), $id);
            self::assertStringContainsString('data-fnr-open="' . $id . '"', $page, $id);
        }
        self::assertSame(substr_count($page, '<form '), substr_count($page, 'name="_csrf_token"'), 'chaque formulaire porte son jeton');
        $outside = (string) preg_replace('#<dialog.*?</dialog>#s', '', $page);
        self::assertStringNotContainsString('<form', $outside, 'aucun formulaire hors des fenêtres');
        self::assertStringContainsString('Rétablir le catalogue livré ?', $page);
        self::assertStringContainsString('formaction="' . url('atak/sse/fiches/gestion/supprimer') . '"', $page);
        // La mention de diffusion ne donne aucun droit : c'est dit.
        self::assertStringContainsString('Elle ne change pas qui peut la lire', $page);
        self::assertStringContainsString('href="' . url('atak/sse/fiches/gestion/export') . '"', $page);
    }

    public function testReaderNewCommunityAndUnmigratedDatabase(): void
    {
        // Lecteur sans droit de gestion : tout se lit, rien ne s'écrit.
        $page = self::own($this->page(1, false));
        self::assertStringContainsString('<strong>Lecture seule</strong>', $page);
        self::assertStringContainsString('ROUTE WATCH', $page);
        foreach (['<dialog', '<form', 'data-fnr-open=', 'data-fng-row=', '/gestion/export', 'name="_csrf_token"'] as $needle) {
            self::assertStringNotContainsString($needle, $page, $needle);
        }
        // Communauté sans réglage : le catalogue livré, rien d'une autre communauté.
        $page = self::own($this->page(2, true));
        self::assertMatchesRegularExpression('#<p class="fng-key"><strong>82</strong>#', $page);
        self::assertStringNotContainsString('ROUTE WATCH', $page);
        self::assertStringNotContainsString('Besoin prioritaire du chef de corps', $page);
        self::assertStringContainsString('Besoin prioritaire en renseignement', $page);
        self::assertStringContainsString('<dialog', $page);
        // Session sans lecture des fiches : renvoyée à l'accueil.
        $denied = $this->controller(1, false, false)->index(new Request(), []);
        self::assertSame(302, $denied->statusCode());
        Session::getFlash('error');

        // Base où l'étape n'est pas passée : catalogue livré en lecture seule, message clair, même pour qui gère.
        Sq::close();
        $this->pdo = Sq::open(false);
        Fx::seed($this->pdo, false);
        $page = self::own($this->page(1, true));
        self::assertStringContainsString('<strong>Catalogue livré en lecture seule</strong>', $page);
        self::assertStringContainsString('HUMINT', $page);
        foreach (['<dialog', '<form', 'data-fnr-open='] as $needle) {
            self::assertStringNotContainsString($needle, $page, $needle);
        }
        $r = $this->post('etiquette', ['code' => 'NEW ONE', 'family' => 'theme', 'label_fr' => 'Nouvelle', 'color' => 'blue']);
        self::assertSame(302, $r->statusCode());
        self::assertStringContainsString('lecture seule', (string) Session::getFlash('error'));
    }

    public function testWritesNeedManageRightTokenAndStayInTheSessionCommunity(): void
    {
        $create = ['code' => 'night ops', 'family' => 'theme', 'label_fr' => 'Opérations de nuit', 'label_en' => 'Night operations', 'desc_fr' => 'Activité de nuit.', 'color' => 'blue', 'sort' => '0', 'qualifier' => ''];
        $count = fn (): int => (int) $this->scalar("SELECT COUNT(*) FROM sse_attach_tags WHERE slug = 'night-ops'");
        // Lecteur, invité, jeton absent, débit dépassé : refus, rien d'écrit.
        foreach ([
            [$this->controller(1, false), true], [$this->controller(1, true, true, true), true], [$this->controller(), false], [$this->controller(1, true, true, false, true), true],
        ] as [$controller, $withToken]) {
            $r = $this->post('etiquette', $create, $controller, $withToken);
            self::assertSame(302, $r->statusCode());
            self::assertNotSame('', (string) Session::getFlash('error'));
            self::assertSame(0, $count());
        }
        $audit = (int) $this->scalar('SELECT COUNT(*) FROM sse_audit_log');
        // Gestionnaire : créé dans SA communauté, quoi que dise le formulaire, et journalisé.
        $r = $this->post('etiquette', $create + ['tenant_id' => '2', 'tenant' => '2']);
        self::assertSame(302, $r->statusCode());
        self::assertStringContainsString('/atak/sse/fiches/gestion#etiquettes', (string) $r->headerValue('Location'));
        self::assertSame('Étiquette créée.', Session::getFlash('success'));
        self::assertSame('1', (string) $this->scalar("SELECT tenant_id FROM sse_attach_tags WHERE slug = 'night-ops'"));
        self::assertSame($audit + 1, (int) $this->scalar('SELECT COUNT(*) FROM sse_audit_log'));
        self::assertSame('field_note.tag.create|7|Analyste Sept|1', (string) $this->scalar("SELECT action || '|' || actor_user_id || '|' || actor_label || '|' || tenant_id FROM sse_audit_log ORDER BY id DESC LIMIT 1"));

        // Anti-IDOR : le gestionnaire de la communauté 2 ne touche pas une étiquette de la communauté 1.
        $other = $this->controller(2, true);
        foreach ([['etat', ['key' => 'route-watch', 'status' => 'archived']], ['supprimer', ['key' => 'night-ops']], ['fusion', ['from' => 'checkpoint', 'into' => 'route-watch']], ['etiquette', ['key' => 'route-watch', 'label_fr' => 'Pris', 'color' => 'red']]] as [$action, $post]) {
            $this->post($action, $post, $other);
            self::assertStringContainsString('introuvable', (string) Session::getFlash('error'), $action);
        }
        self::assertSame('active|Surveillance d’itinéraire', (string) $this->scalar("SELECT status || '|' || name FROM sse_attach_tags WHERE tenant_id = 1 AND slug = 'route-watch'"));
        self::assertSame(1, $count());

        // Les autres actions passent par le même contrôleur.
        $this->post('etat', ['key' => 'night-ops', 'status' => 'archived', 'tab' => 'etiquettes']);
        self::assertStringContainsString('archivée', (string) Session::getFlash('success'));
        $this->post('supprimer', ['key' => 'night-ops']);
        self::assertSame('Étiquette supprimée.', Session::getFlash('success'));
        self::assertSame(0, $count());
        $this->post('supprimer', ['key' => 'route-watch']);
        self::assertStringContainsString('archivez-la', (string) Session::getFlash('error'));
        $r = $this->post('fusion', ['from' => 'checkpoint', 'into' => 'route-watch']);
        self::assertStringContainsString('Fusion faite', (string) Session::getFlash('success'));
        $r = $this->post('etat', ['key' => 'narco', 'status' => 'disabled', 'tab' => 'categories']);
        self::assertStringContainsString('#categories', (string) $r->headerValue('Location'));
        Session::getFlash('success');
        $this->post('retablir', ['family' => 'discipline']);
        self::assertStringContainsString('rétabli', (string) Session::getFlash('success'));
        $this->post('type', ['kind' => 'frc', 'label' => 'Fiche de liaison', 'enabled' => '1', 'sort' => '5', 'suggested' => ['humint', 'inconnue'], 'required_fields' => ['title', 'pirate']]);
        self::assertSame('Type de fiche enregistré.', Session::getFlash('success'));
        $this->post('modele', ['kind' => 'FRM', 'template' => "Qui :\nQuoi :\nOù :"]);
        $this->post('requis', ['kind' => 'FRO', 'families' => ['family:product', 'family:pirate'], 'required' => ['salute', 'cat-armement']]);
        $this->post('regles', ['default_validity' => 'corroborated', 'precedence' => ['critique' => 'FLASH', 'urgent' => 'IMMEDIATE', 'normal' => 'ROUTINE', 'routine' => 'ROUTINE'], 'rel_to' => 'usa']);
        self::assertSame('Règles enregistrées.', Session::getFlash('success'));
        $service = new SseFieldNoteTagService(new SseTagRepository());
        $kinds = $service->kinds(1);
        self::assertSame(['Fiche de liaison', 5, ['humint'], ['title']], [$kinds['FRC']['label'], $kinds['FRC']['sort'], $kinds['FRC']['suggested'], $kinds['FRC']['required_fields']]);
        self::assertSame("Qui :\nQuoi :\nOù :", $kinds['FRM']['template']);
        self::assertSame(['family:product', 'salute'], $kinds['FRO']['required_tags']);
        self::assertSame(['corroborated', 'ROUTINE', 'USA'], [$service->rules(1)['default_validity'], $service->rules(1)['precedence']['normal'], $service->rules(1)['rel_to']]);
        $this->post('import', ['json' => '{"format":"x"}']);
        self::assertStringContainsString('format ou version', (string) Session::getFlash('error'));
        $this->post('import', ['json' => '']);
        self::assertStringContainsString('Choisissez un fichier', (string) Session::getFlash('error'));
        $this->post('inconnue', []);
        self::assertSame('Action inconnue.', Session::getFlash('error'));

        // Export : réservé à qui gère ; un JSON de la communauté de la session.
        self::assertSame(302, $this->controller(1, false)->export(new Request(), [])->statusCode());
        Session::getFlash('error');
        $export = $this->controller()->export(new Request(), []);
        self::assertSame(200, $export->statusCode());
        self::assertStringContainsString('attachment; filename="referentiel-fiches-', (string) $export->headerValue('Content-Disposition'));
        $data = json_decode($export->body(), true);
        self::assertSame(SseFieldNoteTagService::FORMAT, $data['format']);
        self::assertContains('ROUTE WATCH', array_column($data['tags'], 'code'));
        self::assertSame([], json_decode($this->controller(2, true)->export(new Request(), [])->body(), true)['tags']);
    }

    public function testNoteDocumentCarriesTheUsMarkings(): void
    {
        $_SERVER['REQUEST_URI'] = '/atak/sse/fiches/' . $this->ids['long'];
        $html = self::view('field_note_show', Fx::showData(1, $this->ids['long']));
        $start = (int) strpos($html, '<article class="fnr-doc"');
        $doc = substr($html, $start, (int) strpos($html, '</article>', $start) - $start);
        // Bandeau haut et bas : marquage de diffusion à la suite de la classification.
        self::assertSame(2, substr_count($doc, '<span class="fnr-band__marking">SECRET//NOFORN/ORCON</span>'));
        self::assertStringContainsString('<td colspan="3" class="fnr-marking">SECRET//NOFORN/ORCON</td>', $doc);
        self::assertStringContainsString('<span class="fnr-us" title="Menace imminente : passe avant tout le reste.">FLASH</span>', $doc);
        foreach (['<th scope="row">Besoin</th>', '<b>PIR 2</b>', '<th scope="row">Produit</th>', '<b>SPOTREP</b>', '<th scope="row">Discipline</th>', '<b>HUMINT</b>', '<b>HVI</b> Personne de haute valeur', '<b>ROUTE WATCH</b>', '<p class="fnr-validity"><strong>Validité</strong>', '<b>TIME-SENSITIVE</b>', 'valable jusqu’au 20/11/2026 à 18:00', '(Usually reliable)', '(Probably true)', 'Armement / Matériel'] as $text) {
            self::assertStringContainsString($text, $doc, $text);
        }
        // Colonne : onglet « Étiquettes » filtrable, par famille, avec la mention sur les droits.
        $side = substr($html, (int) strpos($html, '<aside class="fnr-side"'));
        self::assertStringContainsString('data-fnr-tab="etiquettes">Étiquettes</button>', $side);
        self::assertStringContainsString('data-fnt-filter="fnr-tag-list"', $side);
        self::assertSame(6, substr_count($side, 'data-fnt-group'));
        self::assertSame(9, substr_count($side, 'data-fnt-item'));
        self::assertStringContainsString('elle ne change pas qui peut la lire', $side);
        self::assertStringContainsString('atak/sse/fiches?tag=pir', $side);
        // Fenêtre de choix : par famille, recherche, suggestions, compléments, jeton.
        self::assertSame(1, substr_count($html, 'id="fnr-dlg-etiquettes"'));
        $dialog = substr($html, (int) strpos($html, 'id="fnr-dlg-etiquettes"'));
        $dialog = substr($dialog, 0, (int) strpos($dialog, '</dialog>'));
        foreach (['action="' . url('atak/sse/fiches/' . $this->ids['long'] . '/etiquettes') . '"', 'name="_csrf_token"', 'data-fnp-search', 'data-fnp-suggest-box', 'name="tags[]" value="pir"', 'name="tag_qualifier[pir]" value="2"', 'type="datetime-local"', 'data-fnp-relto="USA, ALTIS"'] as $needle) {
            self::assertStringContainsString($needle, $dialog, $needle);
        }
        self::assertSame(7, substr_count($dialog, 'class="fnp-fam"'));
        self::assertStringNotContainsString('type="checkbox"', $dialog, 'pas de mur de cases');
        self::assertStringNotContainsString('data-key="geoint"', $dialog, 'une étiquette désactivée n’est pas proposée');

        // Lecteur : il lit les étiquettes, ne les modifie pas.
        $reader = self::view('field_note_show', Fx::showData(1, $this->ids['long'], false, false));
        self::assertStringContainsString('<b>PIR 2</b>', $reader);
        self::assertStringNotContainsString('fnr-dlg-etiquettes', $reader);
        // Fiche sans étiquette d'une communauté neuve : état vide explicite, document inchangé hors la ligne de diffusion.
        $_SERVER['REQUEST_URI'] = '/atak/sse/fiches/' . $this->ids['other'][0];
        $plain = self::view('field_note_show', Fx::showData(2, $this->ids['other'][0]));
        self::assertStringContainsString('<strong>Aucune étiquette</strong>', $plain);
        self::assertStringNotContainsString('fnr-band__marking', $plain);
        self::assertStringNotContainsString('<th scope="row">Besoin</th>', $plain);
        self::assertStringContainsString('<td colspan="3" class="fnr-marking">CUI</td>', $plain);
    }

    public function testListAndComposerOfferTagsByFamily(): void
    {
        $_SERVER['REQUEST_URI'] = '/atak/sse/fiches';
        $list = self::view('field_notes', Fx::listData(1, ['tag_family' => 'dissemination']));
        foreach (['name="tag_family"', 'name="tag"', '<optgroup label="Diffusion et contrôle" data-family="dissemination">', 'data-fnt-family-select="fiche-tag"', 'une étiquette de la famille <strong>Diffusion et contrôle</strong>', 'code d’étiquette'] as $needle) {
            self::assertStringContainsString($needle, $list, $needle);
        }
        self::assertGreaterThan(20, substr_count($list, '<a class="fnt" style="--fnt:'));
        self::assertStringContainsString('class="fnt__more"', $list, 'au-delà de quatre pastilles : un compteur');
        self::assertStringContainsString('REL TO USA, ALTIS', $list);
        $composer = self::view('field_note_composer', Fx::composerData(1, ['note_kind' => 'FRO', 'tags' => [['slug' => 'pir', 'qualifier' => '2']]]));
        foreach (['data-fnp ', 'data-fnp-search', 'name="tags[]" value="pir"', 'name="tag_qualifier[pir]" value="2"', 'data-fn-templates=', 'Insérer la trame dans le texte', 'sse-fiches-etiquettes.js', '&quot;FRT&quot;:[&quot;techint&quot;'] as $needle) {
            self::assertStringContainsString($needle, $composer, $needle);
        }
        self::assertStringNotContainsString('value="FRA"', $composer, 'un type retiré de la rédaction n’est plus proposé');
        self::assertStringNotContainsString('value="TERROR"', $composer, 'une catégorie désactivée n’est plus proposée');
        self::assertStringContainsString('value="ARMEMENT"', $composer);
        self::assertStringContainsString('value="FRA"', self::view('field_note_composer', Fx::composerData(2)));
        // Base non migrée : la rédaction et la liste fonctionnent sans étiquettes.
        Sq::close();
        $this->pdo = Sq::open(false);
        Fx::seed($this->pdo, false);
        $composer = self::view('field_note_composer', Fx::composerData(1));
        self::assertStringNotContainsString('data-fnp ', $composer);
        self::assertStringContainsString('value="TERROR"', $composer);
        self::assertStringNotContainsString('class="fnt"', self::view('field_notes', Fx::listData(1)));
    }

    public function testInterfaceRulesOnViewsAndAssets(): void
    {
        $views = [
            'admin' => self::own($this->page()),
            'vue' => self::read('views/atak/sse/field_note_admin.php'),
            'choix' => self::read('views/atak/sse/partials/field_note_tag_picker.php'),
        ];
        $css = self::read('public/assets/css/sse-fiches-etiquettes.css');
        $js = self::read('public/assets/js/sse-fiches-etiquettes.js');
        foreach ($views + ['css' => $css, 'js' => $js] as $name => $text) {
            foreach (['sse-ds', 'Archivo', 'eyebrow', 'kicker', 'overline', 'surtitre'] as $banned) {
                self::assertStringNotContainsString($banned, $text, $name . ' : ' . $banned);
            }
            foreach (['fictif', 'fiction', 'roleplay', 'jeu de rôle', 'simulation', ' RP '] as $word) {
                self::assertStringNotContainsString($word, mb_strtolower($text), $name . ' : ' . $word);
            }
        }
        self::assertSame(0, preg_match('~class="[^"]*\bsse-[a-z]~', $views['vue'] . $views['choix']), 'aucune classe du design system écarté');
        // Aucun petit libellé au-dessus d'un titre : un titre n'est jamais précédé d'un texte court dans son bloc.
        self::assertSame(0, preg_match('~<(span|p|small|div)[^>]*>[^<]{1,40}</\1>\s*<h[1-3]\b~', $views['admin']), 'aucun libellé au-dessus d’un titre');
        // CSS : préfixes de la page, variables du portail, 13 px au moins, cibles de 44 px.
        self::assertStringNotContainsString('@import', $css);
        self::assertSame(0, preg_match('~^\s*\.sse-~m', $css));
        foreach (['var(--surface', 'var(--border', 'var(--text-strong', 'var(--green-bright', '.fng-strip__halo', '.fng-strip__veil', '.fng-strip__index', '.fng-key'] as $token) {
            self::assertStringContainsString($token, $css, $token);
        }
        preg_match_all('~font-size:\s*([0-9.]+)(rem|px)~', $css, $m, PREG_SET_ORDER);
        self::assertGreaterThan(40, count($m));
        foreach ($m as [$decl, $size, $unit]) {
            self::assertGreaterThanOrEqual(13.0, $unit === 'rem' ? (float) $size * 16 : (float) $size, $decl);
        }
        self::assertGreaterThan(8, substr_count($css, 'min-height: 44px'));
        // Script : aucun texte français (les libellés viennent du HTML).
        self::assertSame(0, preg_match('~[\'"][^\'"\n]*[àâçéèêëîïôùûœ][^\'"\n]*[\'"]~u', (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $js)));

        // Routes : « gestion » avant /{id}, sous la garde du portail ; navigation : Production.
        $routes = self::read('routes/web.php');
        foreach (["get('/atak/sse/fiches/gestion', [\\App\\Controllers\\Web\\SseFieldNoteAdminController::class, 'index'], \$mwSsePortal)", "get('/atak/sse/fiches/gestion/export'", "post('/atak/sse/fiches/gestion/{action}'", "post('/atak/sse/fiches/{id}/etiquettes', [SseFieldNoteController::class, 'tags'], \$mwSsePortal)"] as $route) {
            self::assertStringContainsString($route, $routes, $route);
        }
        self::assertLessThan(strpos($routes, "'/atak/sse/fiches/{id}'"), strpos($routes, "'/atak/sse/fiches/gestion'"));
        self::assertLessThan(strpos($routes, "'/atak/sse/fiches/{id}/pieces'"), strpos($routes, "'/atak/sse/fiches/gestion/{action}'"));
        $sections = SsePortalNav::sections(true, true);
        $found = SsePortalNav::locate($sections, 'fiches-gestion');
        self::assertNotNull($found);
        self::assertSame(['production', 'Gestion des fiches', 'atak/sse/fiches/gestion'], [$found[0]['id'], $found[1]['label'], $found[1]['path']]);
        self::assertSame('fiches-gestion', SsePortalNav::activeId($sections, '/public/atak/sse/fiches/gestion'));
        self::assertSame('fiches', SsePortalNav::activeId($sections, '/atak/sse/fiches/12'));
        self::assertSame('terrain', \App\Support\SseAccessCatalog::sectionForPath('atak/sse/fiches/gestion'), 'la page suit les droits des fiches');
        // Contrôleur des fiches : étiquetage sous jeton, droit d'écriture, fiche lisible par la session, débit limité.
        $controller = self::read('app/Controllers/Web/SseFieldNoteController.php');
        $tags = substr($controller, (int) strpos($controller, 'public function tags('), 2600);
        foreach (['$this->canWrite()', 'Csrf::validate(', '$this->tenantId()', 'findForTenant($tenantId, $noteId)', '$this->present($tenantId, [$note]) === []', 'tooManyAttempts(', 'setNoteTags($tenantId, $noteId'] as $needle) {
            self::assertStringContainsString($needle, $tags, $needle);
        }
        // API du jeu : champs ajoutés, jamais remplacés.
        $api = self::read('app/Controllers/Api/SseFieldNoteApiController.php');
        self::assertStringContainsString('SseFieldNoteCatalog::clientCatalog() + $extra', $api);
        self::assertStringContainsString("\$body['tags'] ?? []", $api);
        self::assertSame(3, substr_count($api, '$this->withTags($tenant, '));
    }
}
