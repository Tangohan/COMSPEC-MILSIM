<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SsePortalController;
use App\Core\Request;
use App\Repositories\SseCaseRepository;
use App\Services\Sse\SseHomeBoard;
use App\Services\Sse\SseTriageInbox;
use App\Support\HtmlTranslator;
use App\Support\ShortCache;
use App\Support\SsePortalNav;
use PHPUnit\Framework\TestCase;
use Tests\Support\CountingSqlitePdo;
use Tests\Support\SseHomeSqlite;

/**
 * Accueil du bureau SSE en bandes : la VRAIE page (contrôleur, service, vue, mise en page) rendue sur une base sqlite, pour
 * trois lecteurs : bureau chargé, bureau vide, lecteur à habilitation basse dont deux sections sont fermées par de vraies
 * règles. Vérifie les chiffres, les droits, le masquage des identités de membres, la forme (index à côté du titre, aucun
 * petit libellé au-dessus d'un titre, aucune mention de jeu) et le coût en requêtes.
 */
final class SseHomeBoardTest extends TestCase
{
    private const FULL = ['atak.sse.access', 'atak.sse.case.manage', 'atak.sse.grant', 'atak.sse.export'];
    private const LOW = ['atak.sse.access'];

    private CountingSqlitePdo $pdo;

    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/atak/sse/accueil';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->pdo = SseHomeSqlite::open();
    }

    protected function tearDown(): void
    {
        SseHomeSqlite::signOut();
        SseHomeSqlite::close();
        $_SERVER = $this->server;
    }

    private static function root(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    /** Page complète rendue par le vrai contrôleur pour ce lecteur. */
    private function page(array $permissions, int $userId = 7): string
    {
        SseHomeSqlite::signIn($permissions, $userId);

        return (new SsePortalController())->home(new Request())->body();
    }

    /** La partie « bandes » de la page (phrase d'accroche comprise), sans la barre latérale ni la liste des espaces. */
    private static function board(string $html): string
    {
        $from = strpos($html, 'class="ab-hero');
        $to = strpos($html, 'id="ab-spaces-title"');
        self::assertNotFalse($from, 'phrase d’accroche rendue');
        self::assertNotFalse($to, 'liste des espaces rendue');

        return substr($html, (int) $from, (int) $to - (int) $from);
    }

    /** @return list<string> clés des bandes, dans l'ordre de la page */
    private static function bands(string $html): array
    {
        preg_match_all('/data-ab-band="([a-z]+)"/', $html, $m);

        return $m[1];
    }

    /** Chiffre clé d'une bande. */
    private static function figure(string $html, string $band): string
    {
        self::assertSame(1, preg_match('/data-ab-band="' . $band . '".*?class="ab-band__figure">\s*<strong>([^<]*)<\/strong>/s', $html, $m), 'chiffre clé de ' . $band);

        return $m[1];
    }

    public function testBusyDeskShowsSixBandsOnRealData(): void
    {
        SseHomeSqlite::seedBusy($this->pdo, time());
        $html = $this->page(self::FULL);
        $board = self::board($html);

        self::assertSame(['tri', 'dossiers', 'avis', 'terrain', 'cerbere', 'valider'], self::bands($board));
        self::assertSame('22', self::figure($board, 'tri'), 'éléments à trier, sans doublon');
        self::assertSame('14', self::figure($board, 'dossiers'), '9 dossiers validés et 5 dossiers d’intérêt actifs');
        self::assertSame('3', self::figure($board, 'avis'));
        self::assertSame('9', self::figure($board, 'terrain'), '4 transmissions, 3 contrôles, 2 fiches en sept jours');
        self::assertSame('4', self::figure($board, 'cerbere'), '3 liens et 1 détection en 24 h');
        self::assertSame('3', self::figure($board, 'valider'), '2 documents en relecture et 1 compte rendu de contrôle');

        // Phrase d'accroche : les trois chiffres, dans la ligne du titre, et un seul lien fléché.
        self::assertSame(1, preg_match('/<h1 class="ab-hero__title"[^>]*>(.*?)<\/h1>/s', $board, $h1));
        foreach (['<b>22</b><span>éléments à trier</span>', '<b>3</b><span>avis actifs</span>', '<b>2</b><span>dossiers en retard</span>'] as $chip) {
            self::assertStringContainsString($chip, $h1[1]);
        }
        self::assertSame(3, substr_count($h1[1], 'class="ab-chip '));
        self::assertSame(1, substr_count($board, 'class="ab-hero__link"'));
        self::assertStringContainsString('/atak/sse/a-trier"><span>Ouvrir « À trier »</span>', $board);

        // Éléments réels et cliquables : 3 par bande, les retards et la plus haute priorité d'abord.
        self::assertSame(18, substr_count($board, '<a class="ab-item'));
        foreach (['DOS-0031 · Atelier de Pyrgos', 'DI-2026-0007 · Homme au blouson gris', 'Karim Benali', 'Armé et dangereux', 'Deux véhicules à l’arrêt devant l’entrepôt nord', 'CTRL-1', 'Synthèse hebdomadaire du port', 'Cerbère-Liaison'] as $text) {
            self::assertStringContainsString($text, $board);
        }
        self::assertStringContainsString('/atak/sse/interet/1"', $board);
        self::assertStringContainsString('En retard de 4 j', $board);
        self::assertStringContainsString('<b>1</b> <span>armé et dangereux</span>', $board);
        self::assertStringContainsString('<b>2</b> <span>dossiers en retard</span>', $board);
        self::assertStringContainsString('/atak/sse/cerberes">Voir les Cerbères</a>', $board);

        // Chaque bande : une action pleine, au plus une discrète ; miroir une bande sur deux.
        self::assertSame(6, substr_count($board, 'class="ab-btn ab-btn--solid"'));
        self::assertSame(3, substr_count($board, ' is-mirror'));
        self::assertSame(1, preg_match('/class="ab-band ab-band--amber" style="--ab-i: 0" data-ab-band="tri"/', $board), 'la première bande n’est pas en miroir');
        self::assertSame(1, preg_match('/class="ab-band ab-band--green is-mirror" style="--ab-i: 1" data-ab-band="dossiers"/', $board));
        self::assertSame(0, substr_count($board, ' is-empty'));

        // La barre latérale reprend la boîte lue par la page ; la liste des espaces garde ses ancres (fil d'Ariane).
        self::assertStringContainsString('id="renseignement"', $html);
        self::assertStringContainsString('sse-accueil-bandes.css', $html);
        self::assertStringContainsString('sse-accueil-bandes.js', $html);
    }

    public function testEmptyDeskKeepsReducedBandsWithExplicitTexts(): void
    {
        $board = self::board($this->page(self::FULL));

        self::assertSame(['tri', 'dossiers', 'avis', 'terrain', 'cerbere', 'valider'], self::bands($board), 'une bande vide se réduit, elle ne disparaît pas');
        self::assertSame(6, substr_count($board, ' is-empty'));
        self::assertSame(0, substr_count($board, '<a class="ab-item'));
        foreach (['Rien à trier : aucune décision ne vous attend.', 'Aucun dossier en cours', 'Aucun avis de recherche actif.', 'Aucune remontée depuis sept jours.', 'Cerbère n’a encore rien eu à rattacher.', 'Aucun document n’attend de validation.'] as $text) {
            self::assertStringContainsString($text, $board);
        }
        self::assertStringContainsString('Le bureau est à jour :', $board);
        self::assertStringContainsString('rien n’attend de décision', $board);
        self::assertStringContainsString('ab-hero is-calm', $board);
        self::assertSame('0', self::figure($board, 'tri'));
        self::assertSame(1, substr_count($board, 'class="ab-hero__link"'));
    }

    public function testReaderWithLowClearanceOnlySeesReadableSections(): void
    {
        SseHomeSqlite::seedBusy($this->pdo, time());
        SseHomeSqlite::denySections($this->pdo, ['fichiers', 'analyse']);
        $board = self::board($this->page(self::LOW));

        // « Fichiers » (avis de recherche) et « Analyse » (Cerbère) sont fermées : leurs bandes n'existent pas, même vides.
        self::assertSame(['tri', 'dossiers', 'terrain', 'valider'], self::bands($board));
        foreach (['Avis de recherche', 'Ce que Cerbère a fait', 'Karim Benali', 'AVR-2026', 'avis actifs', 'Cerbère-Liaison'] as $hidden) {
            self::assertStringNotContainsString($hidden, $board, $hidden);
        }
        // Numérotation continue des bandes visibles.
        self::assertSame(1, preg_match('/À valider<\/h3><span class="ab-band__index" aria-hidden="true">\/ 04<\/span>/', $board));

        // Habilitation Confidentiel (C) : les 3 dossiers tenus au-dessus ne sont ni comptés ni nommés.
        self::assertSame('11', self::figure($board, 'dossiers'));
        foreach (['Atelier de faux papiers', 'Cellule de Kavala', 'Filière de recrutement', 'DOS-0019', 'DOS-0023', 'DOS-0027'] as $title) {
            self::assertStringNotContainsString($title, $board, $title);
        }
        // La boîte ne compte que les files lisibles (plus d'anomalies ni de propositions).
        self::assertSame('12', self::figure($board, 'tri'));
        self::assertStringNotContainsString('Anomalies', $board);
        self::assertStringContainsString('<b>12</b><span>éléments à trier</span>', $board);
    }

    public function testMemberIdentityIsNeverNamedForAReaderWithoutTheRight(): void
    {
        SseHomeSqlite::seedBusy($this->pdo, time());
        $html = $this->page(self::FULL);
        $board = self::board($html);

        // L'avis le plus menaçant vise l'identité d'un membre : il est listé, jamais nommé.
        self::assertStringContainsString('Identité de membre (accès restreint)', $board);
        self::assertStringContainsString('Menace critique', $board);
        self::assertStringNotContainsString(SseHomeSqlite::MEMBER_NAME, $html, 'ni dans les bandes, ni ailleurs dans la page');
        // Ni l'indicatif du contrôleur, ni le nom présenté à un contrôle, ni le motif d'un lien de Cerbère (qui cite un nom).
        self::assertStringNotContainsString(SseHomeSqlite::CONTROLLER, $board);
        self::assertStringNotContainsString('Kovac', $board);
        self::assertStringNotContainsString('Même numéro de papier', $board);
        self::assertStringNotContainsString('<img', $board, 'aucune image dans les bandes');
    }

    public function testMemberSeesTheirOwnIdentityNamed(): void
    {
        SseHomeSqlite::seedBusy($this->pdo, time());
        $board = self::board($this->page(self::FULL, SseHomeSqlite::MEMBER_USER));

        self::assertStringContainsString('Marc ' . SseHomeSqlite::MEMBER_NAME, $board, 'le titulaire voit son identité');
        self::assertStringNotContainsString('Identité de membre (accès restreint)', $board);
    }

    public function testIndexSitsBesideTheTitleAndNothingStandsAboveIt(): void
    {
        SseHomeSqlite::seedBusy($this->pdo, time());
        $board = self::board($this->page(self::FULL));

        // Le titre ouvre le bloc d'identité de la bande ; l'index le suit sur la même ligne.
        self::assertSame(6, preg_match_all('/<div class="ab-band__identity">\s*(?:<\?php[^>]*\?>\s*)?<div class="ab-band__head">\s*<h3 class="ab-band__title" id="ab-band-[a-z]+">[^<]+<\/h3><span class="ab-band__index" aria-hidden="true">\/ 0[1-6]<\/span>/', $board));
        // Sections : le titre d'abord, sa ligne de description ensuite.
        self::assertSame(1, preg_match('/<header class="ab-section__head">\s*<h2 id="ab-bands-title">[^<]+<\/h2>\s*<p>[^<]+<\/p>/', $board));
        self::assertSame(6, preg_match_all('/<\/div>\s*<p class="ab-band__lead">[^<]+<\/p>/', $board), 'une ligne de description sous chaque titre de bande');

        $css = self::root('public/assets/css/sse-accueil-bandes.css');
        self::assertSame(1, preg_match('/\.ab-band__head\s*\{\s*display:\s*block;/', $css));
        self::assertSame(1, preg_match('/\.ab-band__title\s*\{\s*display:\s*inline;/', $css));
        self::assertSame(1, preg_match('/\.ab-band__index\s*\{[^}]*white-space:\s*nowrap;/', $css), 'l’index ne passe jamais seul à la ligne');
        self::assertSame(0, preg_match('/\.ab-band__head[^{]*\{[^}]*(column|order)/', $css));
    }

    public function testNoEyebrowNoDesignSystemNoGameMention(): void
    {
        SseHomeSqlite::seedBusy($this->pdo, time());
        $board = self::board($this->page(self::FULL));
        $sources = [
            'page rendue' => $board,
            'vue' => self::root('views/atak/sse/home.php'),
            'feuille' => self::root('public/assets/css/sse-accueil-bandes.css'),
            'script' => self::root('public/assets/js/sse-accueil-bandes.js'),
            'service' => self::root('app/Services/Sse/SseHomeBoard.php'),
        ];
        foreach ($sources as $name => $text) {
            foreach (['eyebrow', 'kicker', 'overline', 'sse-ds', 'Archivo', 'class="sse-', 'sse_fiction_footer', 'sx-home'] as $banned) {
                self::assertStringNotContainsString($banned, $text, $name . ' : ' . $banned);
            }
            // Aucune classe du design system « .sse-* » (« body.sse-iw » est la coque du portail).
            self::assertSame(0, preg_match('/\.sse-(?!iw["\s.{:,])/', $text), $name . ' : classe .sse-*');
            self::assertSame(0, preg_match('/simulation|fictif|fictive|fiction|jeu de r[ôo]le|roleplay|\bRP\b|Arma 3/iu', $text), $name . ' : mention de jeu');
        }
        self::assertStringNotContainsString('http', $sources['feuille'], 'aucune ressource externe');
    }

    public function testStylesheetHandlesMotionMirrorMobileAndSizes(): void
    {
        $css = self::root('public/assets/css/sse-accueil-bandes.css');

        self::assertSame(1, preg_match('/@media \(prefers-reduced-motion: reduce\)\s*\{(.*)\}\s*$/s', $css, $reduced));
        self::assertStringContainsString('animation: none !important', $reduced[1]);
        self::assertStringContainsString('transition: none !important', $reduced[1]);
        self::assertStringContainsString('.ab-band:hover', $reduced[1]);
        self::assertStringContainsString('transform: none', $reduced[1]);
        self::assertStringContainsString('.ab-bands.is-armed .ab-band:not(.is-inview) { opacity: 1;', $reduced[1], 'sans mouvement, une bande n’attend pas d’être révélée');
        self::assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", self::root('public/assets/js/sse-accueil-bandes.js'));

        // Couches de la bande et halo renforcé au survol.
        foreach (['.ab-band__media', '.ab-band__glow', '.ab-band__veil', '.ab-band__body', '.ab-band:hover .ab-band__glow', 'animation-delay: calc(var(--ab-d, 0) * 70ms)'] as $rule) {
            self::assertStringContainsString($rule, $css);
        }
        // Miroir à partir de 900 px seulement ; une colonne en dessous.
        self::assertSame(1, preg_match('/@media \(min-width: 900px\)\s*\{.*?\.ab-band\.is-mirror \.ab-band__body/s', $css));
        self::assertSame(1, preg_match('/@media \(max-width: 899\.98px\)\s*\{(.*?)\n\}/s', $css, $mobile));
        self::assertStringNotContainsString('is-mirror', $mobile[1]);
        self::assertSame(0, preg_match('/^\.ab-band\.is-mirror \.ab-band__(body|identity|rail)/m', $css), 'aucune règle de miroir hors requête de largeur');

        // Textes d'au moins 13 px, cibles d'au moins 44 px.
        preg_match_all('/font-size:\s*([\d.]+)px/', $css, $px);
        self::assertNotSame([], $px[1]);
        self::assertGreaterThanOrEqual(13.0, min(array_map('floatval', $px[1])));
        preg_match_all('/font-size:\s*(?:clamp\()?([\d.]+)rem/', $css, $rem);
        self::assertGreaterThanOrEqual(0.9, min(array_map('floatval', $rem[1])));
        foreach (['.ab-chip', '.ab-btn', '.ab-hero__link', '.ab-space__pages a'] as $target) {
            self::assertSame(1, preg_match('/' . preg_quote($target, '/') . '\s*\{[^}]*min-height:\s*(\d+)px/', $css, $m), $target);
            self::assertGreaterThanOrEqual(44, (int) $m[1], $target);
        }
        self::assertSame(1, preg_match('/\.ab-item\s*\{[^}]*min-height:\s*(\d+)px/', $css, $m));
        self::assertGreaterThanOrEqual(44, (int) $m[1]);
    }

    public function testBandsCostAFixedNumberOfQueries(): void
    {
        SseHomeSqlite::seedBusy($this->pdo, time());
        SseHomeSqlite::signIn(self::FULL);
        $measure = function (): int {
            $nav = SsePortalNav::forSession(true, true, false);
            $ids = [];
            foreach ($nav['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    $ids[] = (string) $item['id'];
                }
            }
            $snapshot = SseTriageInbox::forSession()->snapshot(1, 7, $ids);
            $cases = (new SseCaseRepository())->listForTenant(1, null);
            $this->pdo->reset();
            $board = (new SseHomeBoard())->build(1, $snapshot, $cases, $ids, false);
            self::assertSame([], $board['errors'], 'aucune lecture en échec');
            self::assertCount(6, $board['bands']);

            return $this->pdo->queries;
        };
        $measure(); // première lecture : sondes de présence des tables, gardées ensuite pour la connexion
        $first = $measure();
        self::assertLessThanOrEqual(30, $first, 'six bandes : la boîte et les dossiers sont déjà lus, le reste est borné');

        // Dix fois plus de données : pas une lecture de plus (aucune requête par élément).
        $at = gmdate('Y-m-d H:i:s', time() - 3600);
        $case = $this->pdo->prepare("INSERT INTO sse_cases (tenant_id, is_folder, reference_code, title, classification, status, created_at, updated_at) VALUES (1, 0, ?, ?, 'interne', 'ouvert', ?, ?)");
        $note = $this->pdo->prepare("INSERT INTO sse_field_notes (tenant_id, reference_code, title, body, note_kind, urgency, themes, status, origin, observed_at, created_at, updated_at) VALUES (1, ?, ?, 'x', 'OBSERVATION', 'routine', '[]', 'classee', 'web', ?, ?, ?)");
        $event = $this->pdo->prepare("INSERT INTO sse_intel_events (event_uuid, tenant_id, event_type, source_system, event_time, summary, created_at) VALUES (?, 1, 'SPOTREP', 'ARMA_SSE', ?, ?, ?)");
        $identity = $this->pdo->prepare("INSERT INTO sse_identities (id, tenant_id, kind, last_name, first_name, created_at) VALUES (?, 1, 'civil', ?, 'X', ?)");
        $notice = $this->pdo->prepare("INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, ref_year, ref_seq, notice_type, threat_level, armed, status, active_key, issued_at, created_at, updated_at) VALUES (1, ?, ?, 2026, ?, 'poi', 'low', 'no', 'active', 1, ?, ?, ?)");
        for ($i = 0; $i < 60; $i++) {
            $case->execute(['DOS-9' . $i, 'Dossier ' . $i, $at, $at]);
            $note->execute(['FR-9' . $i, 'Fiche ' . $i, $at, $at, $at]);
            $event->execute(['vol-' . $i, $at, 'Transmission ' . $i, $at]);
            $identity->execute([500 + $i, 'Nom' . $i, $at]);
            $notice->execute([500 + $i, 'AVR-9-' . $i, 100 + $i, $at, $at, $at]);
        }
        self::assertSame($first, $measure());
    }

    public function testBoardIsKeptForTheSessionLikeTheInbox(): void
    {
        SseHomeSqlite::seedBusy($this->pdo, time());
        SseHomeSqlite::signIn(self::FULL);
        ShortCache::setEnabled(true);
        ShortCache::bump(SseTriageInbox::NS);
        try {
            $first = SseHomeBoard::forSession(1, 7, [], [], ['dossiers', 'interet', 'cerberes'], false);
            $this->pdo->reset();
            $again = SseHomeBoard::forSession(1, 7, [], [], ['dossiers', 'interet', 'cerberes'], false);
            self::assertSame(0, $this->pdo->queries, 'les bandes gardées ne relisent rien pendant 30 s');
            self::assertSame($first['bands'], $again['bands']);

            // Une session invitée n'est jamais servie depuis ce qui a été gardé : son périmètre tient à son code.
            $this->pdo->reset();
            SseHomeBoard::forSession(1, 0, [], [], ['dossiers', 'interet', 'cerberes'], true);
            self::assertGreaterThan(0, $this->pdo->queries);

            // Une écriture réussie du portail périme les bandes avec la boîte.
            ShortCache::bump(SseTriageInbox::NS);
            $this->pdo->reset();
            SseHomeBoard::forSession(1, 7, [], [], ['dossiers', 'interet', 'cerberes'], false);
            self::assertGreaterThan(0, $this->pdo->queries);
        } finally {
            ShortCache::bump(SseTriageInbox::NS);
            ShortCache::setEnabled(false);
        }
    }

    public function testPageIsTranslatedInEnglish(): void
    {
        SseHomeSqlite::seedBusy($this->pdo, time());
        $board = HtmlTranslator::translate(self::board($this->page(self::FULL)));

        foreach (['Today:', 'items to sort', 'overdue cases', 'Field reports', 'What Cerbère did', 'Sort now', 'armed and dangerous', 'Critical threat', 'field reports in 7 days', 'Member identity (restricted access)'] as $text) {
            self::assertStringContainsString($text, $board);
        }
        foreach (['éléments à trier', 'Remontées du terrain', 'Trier maintenant', 'Il y a', 'En retard de', 'Menace'] as $french) {
            self::assertStringNotContainsString($french, $board, $french);
        }
        // Les noms propres et les titres saisis ne sont pas traduits.
        self::assertStringContainsString('<strong translate="no">DOS-0031 · Atelier de Pyrgos</strong>', $board);
    }
}
