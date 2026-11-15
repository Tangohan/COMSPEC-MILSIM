<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Services\Jnet\JnetDashboardService;
use App\Services\Jnet\JnetMessagingService;
use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Tests\Support\JnetMailSqlite as Db;

/**
 * JNET : écran « Nouveau message » (jnet/courrier/nouveau) refait en composeur. La vraie vue est rendue par le vrai
 * contrôleur sur une base sqlite (petite unité, grande unité, arrivée avec ou sans destinataire, membre privé d'envoi),
 * puis le formulaire rendu est posté au vrai contrôleur : mêmes champs, mêmes droits, même annuaire qu'avant.
 *
 * Processus séparés : les dépôts de la messagerie gardent en mémoire statique, pour tout le processus, la présence des
 * tables et colonnes de la base ouverte (ici sqlite), et le conteneur garde les services construits dessus.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class JnetMailComposeTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Db::signIn();
    }

    protected function tearDown(): void
    {
        Db::close();
        Db::signOut();
        $this->pdo = null;
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // —— Rendu des quatre jeux ——

    public function testSmallUnitOpenedFromAMemberCardPutsTheMemberInTheToLine(): void
    {
        $page = $this->compose(['membre' => (string) Db::KELLER]);
        $html = self::composer($page);

        // Le titre nomme le destinataire, il est déjà en pastille et sa case est la seule cochée.
        self::assertStringContainsString('<title>Message à Liam KELLER — JNET</title>', $page);
        self::assertMatchesRegularExpression('~<h1 class="ja-disp ja-disp--sm">Message à <span translate="no">Liam KELLER</span></h1>~', $html);
        self::assertSame([Db::KELLER], self::checkedMembers($html));
        self::assertSame([], self::checkedGroups($html));
        self::assertMatchesRegularExpression('~<li class="jmc-pill" data-for="mbr-35">.*?Liam KELLER.*?aria-label="Retirer Liam KELLER"~s', $html);
        self::assertSame(1, substr_count($html, '<li class="jmc-pill'));
        // Le curseur attend dans l'objet, pas dans la ligne « À ».
        self::assertMatchesRegularExpression('~<input id="jnet-subject"[^>]* autofocus>~', $html);
        self::assertDoesNotMatchRegularExpressionCompat('~<input id="jmc-to"[^>]* autofocus~', $html);
        self::assertStringContainsString('data-context="membre-35"', $html);
        self::assertStringContainsString('data-focus="subject"', $html);

        // Unité de production : 4 destinataires possibles (le lecteur n'est pas dans l'annuaire), 9 formations, 2 diffusions.
        self::assertSame([35, 36, 37, 38], self::sorted(self::memberIds($html)));
        self::assertCount(9, self::groupKeys($html, 'unit:'));
        self::assertSame(['all', 'command'], self::groupKeys($html, '', ['unit:', 'list:']));

        // Ordre naturel, corps avant l'annuaire : plus de listes de cases à dérouler avant d'écrire.
        $order = array_map(static fn (string $needle): int => (int) strpos($html, $needle), ['id="jmc-to"', 'id="jnet-subject"', 'name="precedence"', 'id="jnet-body"', 'class="jmc-send"', '<dialog class="jmc-dir"']);
        $sorted = $order;
        sort($sorted);
        self::assertSame($sorted, $order);
        self::assertNotContains(0, $order);
    }

    public function testLargeUnitListsEveryMemberAndFormationInsideTheDirectoryWindowOnly(): void
    {
        $html = self::composer($this->compose([], 'large'));

        self::assertCount(139, self::memberIds($html), '140 membres, le lecteur en moins');
        self::assertCount(30, self::groupKeys($html, 'unit:'));
        // Noms longs et accentués échappés, jamais traduits.
        self::assertStringContainsString('ROCHEFOUCAULD-MONTMORENCY', $html);
        self::assertStringContainsString('<strong translate="no">Section de transmissions et systèmes d’information</strong>', $html);
        // Toutes les cases vivent dans la fenêtre d'annuaire ; la phrase répétée sous chaque formation a disparu.
        $beforeDialog = substr($html, 0, (int) strpos($html, '<dialog class="jmc-dir"'));
        self::assertStringNotContainsString('type="checkbox"', $beforeDialog);
        self::assertSame(0, substr_count($html, 'Personnel affecté à cette formation'));
        self::assertSame(1, substr_count($html, 'Une formation comprend ses sous-ensembles.'));
        foreach (['data-jmc-tab="members"', 'data-jmc-tab="formations"', 'data-jmc-tab="general"', 'data-jmc-all', 'data-jmc-clear', 'data-jmc-dir-q', 'data-jmc-dir-n'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        // Onglets : effectifs affichés.
        self::assertMatchesRegularExpression('~data-jmc-tab="members">Membres <em data-jmc-tab-n>139</em>~', $html);
        self::assertMatchesRegularExpression('~data-jmc-tab="formations"><span translate="no">Formations</span> <em data-jmc-tab-n>30</em>~', $html);
        // Règles de diffusion données au script.
        self::assertStringContainsString('data-email-limit="25"', $html);
        self::assertStringContainsString('data-max="250"', $html);
        self::assertStringContainsString('Plus de 25 destinataires : pas d’e-mail, le message reste dans la messagerie.', $html);
    }

    public function testWithoutParameterNothingIsPickedAndTheCursorWaitsInTheToLine(): void
    {
        $page = $this->compose([]);
        $html = self::composer($page);

        self::assertStringContainsString('<title>Nouveau message — JNET</title>', $page);
        self::assertStringContainsString('<h1 class="ja-disp ja-disp--sm">Nouveau message</h1>', $html);
        self::assertSame([], self::checkedMembers($html));
        self::assertSame([], self::checkedGroups($html));
        self::assertSame(0, substr_count($html, '<li class="jmc-pill'));
        self::assertMatchesRegularExpression('~<input id="jmc-to"[^>]* autofocus>~', $html);
        self::assertStringContainsString('data-context="nouveau"', $html);
        // Routine par défaut.
        self::assertMatchesRegularExpression('~name="precedence" value="routine" checked~', $html);
        self::assertSame(1, preg_match_all('~name="precedence" value="[a-z]+" checked~', $html));
    }

    public function testAFormationOrAGroupCanBePickedFromTheAddress(): void
    {
        $html = self::composer($this->compose(['formation' => '4']));
        self::assertSame(['unit:4'], self::checkedGroups($html));
        self::assertMatchesRegularExpression('~Message à <span translate="no">Section Alpha</span>~', $html);
        self::assertMatchesRegularExpression('~<li class="jmc-pill jmc-pill--group" data-for="grp-unit-4">~', $html);
        self::assertStringContainsString('data-context="groupe-unit-4"', $html);
        Db::close();

        $html = self::composer($this->compose(['groupe' => 'all']));
        self::assertSame(['all'], self::checkedGroups($html));
        self::assertSame([], self::checkedMembers($html));
        Db::close();

        // Inconnu, d'une autre communauté, suspendu ou le lecteur lui-même : rien n'est posé, rien n'est nommé.
        foreach ([['formation' => '999'], ['groupe' => 'unit:999'], ['groupe' => ['x']], ['membre' => (string) Db::FOREIGNER], ['membre' => '60'], ['membre' => (string) Db::READER], ['membre' => 'abc']] as $query) {
            $page = $this->compose($query);
            $html = self::composer($page);
            self::assertSame([], self::checkedMembers($html), json_encode($query));
            self::assertSame([], self::checkedGroups($html), json_encode($query));
            self::assertStringContainsString('<title>Nouveau message — JNET</title>', $page);
            self::assertStringNotContainsString('Étranger', $page);
            Db::close();
        }
    }

    public function testAMemberBarredFromSendingNeverSeesTheComposerNorSends(): void
    {
        $this->pdo = Db::open('small');
        Db::muteMessages($this->pdo, Db::READER);
        $controller = Db::controller($this->pdo);

        $_GET = ['membre' => (string) Db::KELLER];
        $response = $controller->mailCompose(new Request());
        self::assertSame(302, $response->statusCode());
        self::assertStringEndsWithCompat('/jnet/courrier', (string) $response->headerValue('Location'));
        self::assertSame('Vous ne pouvez pas envoyer de message pour le moment.', $_SESSION['_flash']['error'] ?? null);

        $_POST = ['_csrf_token' => 'jeton', 'subject' => 'Objet', 'body' => 'Corps', 'precedence' => 'routine', 'members' => [(string) Db::KELLER]];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $response = $controller->mailSend(new Request());
        self::assertStringEndsWithCompat('/jnet/courrier', (string) $response->headerValue('Location'));
        self::assertSame(1, $this->rows('tenant_message_threads'), 'aucun fil créé');
    }

    // —— Champs postés et envoi réel ——

    public function testTheFormPostsExactlyWhatTheControllerReads(): void
    {
        $html = self::composer($this->compose(['membre' => (string) Db::KELLER]));
        self::assertSame(1, preg_match('~<form class="jmc-form" method="post" action="([^"]+)"[^>]*>(.*?)</form>~s', $html, $m));
        self::assertStringEndsWithCompat('/jnet/courrier/nouveau', $m[1]);
        preg_match_all('~\sname="([^"]+)"~', $m[2], $names);
        self::assertSame(['_csrf_token', 'body', 'groups[]', 'members[]', 'precedence', 'subject'], self::sorted(array_values(array_unique($names[1]))));

        // Ce sont bien les champs lus par mailSend().
        $controller = self::read('app/Controllers/Web/JnetPortalController.php');
        foreach (["input('_csrf_token')", "input('subject', '')", "input('body', '')", "input('precedence', 'routine')", "input('groups', [])", "input('members', [])"] as $read) {
            self::assertStringContainsString($read, $controller, $read);
        }
        // Urgence : les quatre niveaux du service, en boutons radio du même nom.
        preg_match_all('~<input type="radio" name="precedence" value="([a-z]+)"~', $m[2], $levels);
        self::assertSame(array_keys((new JnetMessagingService())->precedences()), $levels[1]);
        // Jeton de la session conservé, corps toujours exigé, objet limité à 180 caractères comme avant.
        self::assertStringContainsString('<input type="hidden" name="_csrf_token" value="jeton">', $m[2]);
        self::assertMatchesRegularExpression('~<textarea id="jnet-body"[^>]* name="body"[^>]* required~', $m[2]);
        self::assertMatchesRegularExpression('~<input id="jnet-subject"[^>]* name="subject"[^>]* maxlength="180"~', $m[2]);
        // La fenêtre d'annuaire est dans le formulaire : ses cases partent avec lui, même fenêtre fermée.
        self::assertStringContainsString('<dialog class="jmc-dir"', $m[2]);
        self::assertStringNotContainsString('<dialog class="jmc-confirm"', $m[2]);
    }

    public function testSendingTheRenderedFormToOneMemberCreatesTheThread(): void
    {
        $html = self::composer($this->compose(['membre' => (string) Db::KELLER]));
        $response = $this->send(self::formPost($html, ['subject' => 'Consignes de dimanche', 'body' => 'Rassemblement à 20 h 30.']));

        $threadId = (int) $this->pdo->query('SELECT MAX(id) FROM tenant_message_threads')->fetchColumn();
        self::assertSame(2, $threadId);
        self::assertStringEndsWithCompat('/jnet/courrier?fil=2', (string) $response->headerValue('Location'));
        self::assertSame('Message transmis à 1 destinataire.', $_SESSION['_flash']['success'] ?? null);
        $thread = $this->pdo->query('SELECT * FROM tenant_message_threads WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('Consignes de dimanche', $thread['subject']);
        self::assertSame('routine', $thread['precedence']);
        self::assertSame('jnet', $thread['origin']);
        self::assertSame('1 destinataire nominatif', $thread['recipients_summary']);
        self::assertSame(Db::READER, (int) $thread['created_by_user_id']);
        self::assertSame([[Db::READER, 'sender', null], [Db::KELLER, 'to', null]], $this->participants(2));
        self::assertSame('Rassemblement à 20 h 30.', $this->pdo->query('SELECT body FROM tenant_messages WHERE thread_id = 2')->fetchColumn());
    }

    public function testSendingToAGroupReachesItsMembersAndDuplicatesAreDropped(): void
    {
        $html = self::composer($this->compose(['formation' => '3']));
        // 1re Compagnie (Liam, Noé) + Liam et Élodie nommés + toute la chaîne de commandement (le lecteur seul) : 3 destinataires.
        $post = self::formPost($html, ['subject' => 'Point de situation', 'body' => 'Compte rendu attendu.', 'precedence' => 'prioritaire']);
        $post['groups'][] = 'command';
        $post['members'] = ['35', '36', '35'];
        $this->send($post);

        self::assertSame('Message transmis à 3 destinataires.', $_SESSION['_flash']['success'] ?? null);
        self::assertSame([
            [Db::READER, 'sender', null],
            [35, 'to', '1re Compagnie'],
            [36, 'to', null],
            [37, 'to', '1re Compagnie'],
        ], $this->participants(2));
        $thread = $this->pdo->query('SELECT precedence, recipients_summary FROM tenant_message_threads WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('prioritaire', $thread['precedence']);
        self::assertSame('1re Compagnie (2) · 1 destinataire nominatif', $thread['recipients_summary']);
    }

    public function testMoreThanTwentyFiveRecipientsStillGoThroughTheMessaging(): void
    {
        $html = self::composer($this->compose(['groupe' => 'all'], 'large'));
        // Le décompte donné au script exclut l'expéditeur : 139 destinataires réels pour « Toute l'unité ».
        self::assertSame(1, preg_match('~value="all" data-count="140" data-jmc-everyone\s+data-members="([0-9,]+)"~', $html, $m));
        self::assertCount(139, explode(',', $m[1]));
        self::assertNotContains((string) Db::READER, explode(',', $m[1]));

        $this->send(self::formPost($html, ['subject' => 'Calendrier du mois', 'body' => 'Le calendrier est publié.', 'precedence' => 'flash']));
        self::assertSame('Message transmis à 139 destinataires.', $_SESSION['_flash']['success'] ?? null);
        self::assertSame(140, (int) $this->pdo->query('SELECT COUNT(*) FROM tenant_message_thread_users WHERE thread_id = 2')->fetchColumn());
        self::assertSame('flash', $this->pdo->query('SELECT precedence FROM tenant_message_threads WHERE id = 2')->fetchColumn());
        self::assertGreaterThan((new JnetMessagingService())->emailNotificationLimit(), 139);
        self::assertSame(25, (new JnetMessagingService())->emailNotificationLimit());
    }

    // —— Droits, annuaire visible, jeton ——

    public function testOnlyTheDirectoryOfTheReadersCommunityIsShownAndReachable(): void
    {
        $page = $this->compose([]);
        $html = self::composer($page);
        // Ni le lecteur, ni le compte suspendu, ni le membre d'une autre communauté : dans les cases comme dans les décomptes.
        $ids = self::memberIds($html);
        foreach ([Db::READER, 60, Db::FOREIGNER] as $hidden) {
            self::assertNotContains($hidden, $ids);
        }
        self::assertStringNotContainsString('Étranger', $page);
        self::assertStringNotContainsString('Ancien', $page);
        preg_match_all('~data-members="([0-9,]*)"~', $html, $lists);
        self::assertCount(11, $lists[1]);
        $reached = array_unique(array_filter(explode(',', implode(',', $lists[1]))));
        self::assertSame([35, 36, 37, 38], self::sorted(array_map('intval', $reached)));

        // Un identifiant forgé ne reçoit rien : seul le membre de l'annuaire est servi.
        $this->send(['members' => [(string) Db::FOREIGNER, '60', (string) Db::READER, '35'], 'groups' => ['unit:999', 'list:1']] + self::formPost($html, ['subject' => 'Objet', 'body' => 'Corps']));
        self::assertSame([[Db::READER, 'sender', null], [35, 'to', null]], $this->participants(2));

        // Et seul, il est refusé : la saisie revient, aucun fil.
        $response = $this->send(['_csrf_token' => 'jeton', 'subject' => 'Objet', 'body' => 'Corps', 'members' => [(string) Db::FOREIGNER]], false);
        self::assertStringEndsWithCompat('/jnet/courrier/nouveau', (string) $response->headerValue('Location'));
        self::assertSame('Choisissez au moins un destinataire ou un groupe de diffusion.', $_SESSION['_flash']['error'] ?? null);
        self::assertSame(2, $this->rows('tenant_message_threads'));
    }

    public function testTheGroupMemberListsGivenToThePageNeverContainTheSender(): void
    {
        $this->pdo = Db::open('small');
        $users = Db::users($this->pdo);
        $units = new \App\Repositories\UnitRepository($this->pdo);
        $jnet = new JnetDashboardService($users, new \App\Repositories\TenantRepository($this->pdo), $units);
        $service = new JnetMessagingService($users, $units, null, null, $jnet);

        $lists = $service->groupMemberIds(Db::TENANT, Db::READER);
        self::assertSame(array_column($service->groups(Db::TENANT), 'key'), array_keys($lists));
        self::assertSame([35, 36, 37, 38], self::sorted($lists['all']));
        self::assertSame([], $lists['command'], 'le lecteur est le seul encadrant');
        self::assertSame([35, 37], self::sorted($lists['unit:3']), 'une formation comprend ses sous-ensembles');
        // Sans exclusion, les effectifs sont ceux des groupes (libellés « N membres »).
        self::assertSame([1, 35, 36, 37, 38], self::sorted($service->groupMemberIds(Db::TENANT)['all']));
    }

    public function testAWrongTokenSendsNothing(): void
    {
        $html = self::composer($this->compose(['membre' => (string) Db::KELLER]));
        $post = self::formPost($html, ['subject' => 'Objet', 'body' => 'Corps']);
        self::assertSame('jeton', $post['_csrf_token']);

        foreach (['mauvais', ''] as $token) {
            $response = $this->send(['_csrf_token' => $token] + $post, false);
            self::assertStringEndsWithCompat('/jnet/courrier/nouveau', (string) $response->headerValue('Location'));
            self::assertSame('Votre session a expiré, le message n’a pas été envoyé.', $_SESSION['_flash']['error'] ?? null);
        }
        unset($post['_csrf_token']);
        $this->send($post, false);
        self::assertSame(1, $this->rows('tenant_message_threads'));
        self::assertSame(1, $this->rows('tenant_messages'));
    }

    public function testARefusedSendComesBackWithTheWholeInput(): void
    {
        $html = self::composer($this->compose(['membre' => (string) Db::KELLER]));
        $post = self::formPost($html, ['subject' => 'Objet gardé', 'body' => '   ', 'precedence' => 'immediat']);
        $post['groups'] = ['unit:7'];
        $response = $this->send($post, false);
        self::assertStringEndsWithCompat('/jnet/courrier/nouveau', (string) $response->headerValue('Location'));
        self::assertSame('Le corps du message est vide.', $_SESSION['_flash']['error'] ?? null);

        // La page suivante rend la saisie : objet, urgence, pastilles ; le brouillon local ne passe pas devant.
        $_GET = ['membre' => '38'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $page = (string) Db::controller($this->pdo)->mailCompose(new Request())->body();
        $back = self::composer($page);
        self::assertStringContainsString('role="alert">Le corps du message est vide.</div>', $page);
        self::assertStringContainsString('value="Objet gardé"', $back);
        self::assertMatchesRegularExpression('~name="precedence" value="immediat" checked~', $back);
        self::assertSame([Db::KELLER], self::checkedMembers($back), 'le destinataire de l’adresse ne remplace pas la saisie rendue');
        self::assertSame(['unit:7'], self::checkedGroups($back));
        self::assertSame(2, substr_count($back, '<li class="jmc-pill'));
        self::assertStringContainsString('data-server-draft="1"', $back);
        self::assertStringContainsString('data-focus="body"', $back);
    }

    // —— Habillage : JNET, sans libellé au-dessus des titres, sans SSE DS ——

    public function testThePageKeepsTheJnetShellWithASingleCompactTitle(): void
    {
        $page = $this->compose(['membre' => (string) Db::KELLER]);

        self::assertSame(1, substr_count($page, '<h1'));
        self::assertStringNotContainsString('class="jp-head"', $page, 'plus de titre géant au-dessus du composeur');
        // Fil d'Ariane jusqu'à la messagerie, rubrique allumée, feuilles et script de la page.
        self::assertMatchesRegularExpression('~<a class="ja-tab is-on" href="[^"]*/jnet/courrier" aria-current="page">~', $page);
        self::assertMatchesRegularExpression('~<a href="[^"]*/jnet/courrier">Messagerie</a>~', $page);
        foreach (['assets/css/jnet_athena.css', 'assets/css/jnet_mail_compose.css', 'assets/css/jnet_pages.css', 'assets/js/jnet_mail_compose.js'] as $asset) {
            self::assertStringContainsString($asset, $page, $asset);
        }
        // Le menu de la messagerie reste là, avec ses compteurs.
        self::assertMatchesRegularExpression('~<a class="jmc-box" href="[^"]*/jnet/courrier">Réception<em>1</em></a>~', $page);
        self::assertMatchesRegularExpression('~<a class="jmc-box" href="[^"]*/jnet/courrier\?boite=non-lus">Non lus<em>1</em></a>~', $page);
        self::assertMatchesRegularExpression('~<a class="jmc-box" href="[^"]*/jnet/courrier\?boite=envoyes">Messages envoyés</a>~', $page);
    }

    public function testNoEyebrowNoSmallCapsLabelNoSseDesignSystem(): void
    {
        $html = self::composer($this->compose(['membre' => (string) Db::KELLER], 'large'));
        $view = self::read('views/jnet/mail_compose.php');
        $css = self::read('public/assets/css/jnet_mail_compose.css');
        $js = self::read('public/assets/js/jnet_mail_compose.js');

        foreach (['sse-ds', 'Archivo', 'class="sse-', '.sse-', 'eyebrow', 'kicker', 'overline'] as $banned) {
            foreach (['vue' => $view, 'css' => $css, 'js' => $js, 'page' => $html] as $where => $source) {
                self::assertStringNotContainsString($banned, $source, $banned . ' dans ' . $where);
            }
        }
        // Rien avant le titre dans la bande ; la phrase de description vient dessous.
        self::assertMatchesRegularExpression('~<div class="jmc-band__title">\s*<h1 ~', $html);
        self::assertLessThan(strpos($html, 'class="jmc-band__lead"'), strpos($html, '<h1'));
        self::assertMatchesRegularExpression('~<h2 id="jmc-dir-title">Annuaire</h2>\s*<p>~', $html);
        // Champs : de vrais <label>, en casse normale, 13 px au moins.
        foreach (['<label class="jmc-label" for="jmc-to">À</label>', '<label class="jmc-label" for="jnet-subject">Objet</label>', '<label class="jmc-label" for="jnet-body">Message</label>'] as $label) {
            self::assertStringContainsString($label, $html, $label);
        }
        self::assertSame(1, preg_match('~\.jmc-label \{([^}]*)\}~', $css, $rule));
        self::assertStringContainsString('text-transform: none;', $rule[1]);
        self::assertSame(1, preg_match('~font-size: ([0-9.]+)px;~', $rule[1], $size));
        self::assertGreaterThanOrEqual(13.0, (float) $size[1]);
        // Les seules capitales de la feuille sont celles du titre de la fenêtre (titre JNET), pas des libellés.
        self::assertSame(1, substr_count($css, 'text-transform: uppercase'));
        // Cibles de 44 px : boutons, onglets, boîtes de la messagerie.
        self::assertGreaterThanOrEqual(8, substr_count($css, '44px'));
        // Couleurs et polices de JNET uniquement.
        self::assertStringContainsString('var(--ja-c1)', $css);
        self::assertSame(0, preg_match('~font-family:\s*+(?!var\(--ja-)~', $css));
    }

    public function testTheScriptOnlyDrivesTheFormAndKeepsTheDraftLocal(): void
    {
        $js = self::read('public/assets/js/jnet_mail_compose.js');

        // Aucun autre envoi que le formulaire lui-même.
        foreach (['fetch(', 'XMLHttpRequest', 'sendBeacon', '.action ='] as $banned) {
            self::assertStringNotContainsString($banned, $js, $banned);
        }
        self::assertSame(1, substr_count($js, 'form.submit()'));
        // Brouillon local protégé, par membre et par destinataire d'arrivée ; avertissement avant de quitter ; Ctrl+Entrée.
        self::assertSame(6, substr_count($js, 'window.localStorage.'));
        self::assertSame(4, preg_match_all('~try \{[^}]*window\.localStorage\.~', $js), 'chaque accès au stockage est dans un try');
        self::assertStringContainsString("':envoi'", $js);
        self::assertStringContainsString("'jnetMailDraft:' + (root.getAttribute('data-user') || '0') + ':' + (root.getAttribute('data-context') || 'nouveau')", $js);
        self::assertStringContainsString("root.getAttribute('data-server-draft') === '1'", $js);
        self::assertStringContainsString("addEventListener('beforeunload'", $js);
        self::assertStringContainsString('(ev.ctrlKey || ev.metaKey)', $js);
        // Recherche sans accents, confirmation pour Flash et pour toute l'unité.
        self::assertStringContainsString(".normalize('NFD')", $js);
        self::assertStringContainsString("prec.value === 'flash' ? 'flash' : (r.everyone ? 'everyone' : '')", $js);
        // Les textes viennent de la page (traduits avec elle) : aucun mot accentué dans le code, hors commentaires.
        $code = (string) preg_replace('~/\*.*?\*/|^\h*//\V*$~ms', '', $js);
        self::assertSame(0, preg_match('~[àâçéèêëîïôùûÀÉÈ]~u', $code));
    }

    public function testEveryNewTextHasItsEnglishTranslation(): void
    {
        $dictionary = require self::ROOT . 'lang/en/site_auto.php';
        foreach ([
            'Message à %1', 'Message à', 'Choisir dans l’annuaire', 'Brouillon restauré', 'Envoyer en Flash ?', 'Envoyer à toute l’unité ?',
            'Aucun destinataire pour l’instant.', 'Ajoutez au moins un destinataire.', 'Indiquez l’objet du message.', 'destinataires', 'destinataire',
            'Plus de %1 destinataires : pas d’e-mail, le message reste dans la messagerie.', 'par groupes, doublons écartés)', '(dont',
        ] as $text) {
            self::assertArrayHasKey($text, $dictionary, $text);
            self::assertNotSame($text, $dictionary[$text], $text);
        }
    }

    // —— Outils ——

    /** @param array<string, mixed> $query */
    private function compose(array $query, string $dataset = 'small'): string
    {
        $this->pdo = Db::open($dataset);
        $_GET = $query;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = Db::controller($this->pdo)->mailCompose(new Request());
        self::assertSame(200, $response->statusCode());

        return $response->body();
    }

    /** @param array<string, mixed> $post */
    private function send(array $post, bool $expectThread = true): Response
    {
        self::assertNotNull($this->pdo);
        $before = $this->rows('tenant_message_threads');
        $_GET = [];
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SESSION['_flash']);
        $response = Db::controller($this->pdo)->mailSend(new Request());
        self::assertSame(302, $response->statusCode());
        self::assertSame($before + ($expectThread ? 1 : 0), $this->rows('tenant_message_threads'));

        return $response;
    }

    /**
     * Ce qu'un navigateur poste pour le formulaire rendu : jeton, cases cochées, urgence cochée, puis la saisie.
     *
     * @param array<string, string> $typed
     * @return array<string, mixed>
     */
    private static function formPost(string $html, array $typed): array
    {
        self::assertSame(1, preg_match('~<input type="hidden" name="_csrf_token" value="([^"]*)">~', $html, $token));
        self::assertSame(1, preg_match('~name="precedence" value="([a-z]+)" checked~', $html, $precedence));

        return $typed + [
            '_csrf_token' => $token[1],
            'subject' => '',
            'body' => '',
            'precedence' => $precedence[1],
            'groups' => self::checkedGroups($html),
            'members' => array_map('strval', self::checkedMembers($html)),
        ];
    }

    private static function composer(string $page): string
    {
        $start = strpos($page, '<div class="jmc"');
        self::assertNotFalse($start, 'composeur rendu');

        return substr($page, (int) $start);
    }

    /** @return list<int> */
    private static function memberIds(string $html): array
    {
        preg_match_all('~name="members\[\]" value="(\d+)"~', $html, $m);

        return array_map('intval', $m[1]);
    }

    /** @return list<int> */
    private static function checkedMembers(string $html): array
    {
        preg_match_all('~name="members\[\]" value="(\d+)" checked~', $html, $m);

        return array_map('intval', $m[1]);
    }

    /**
     * @param list<string> $except préfixes écartés
     * @return list<string>
     */
    private static function groupKeys(string $html, string $prefix = '', array $except = []): array
    {
        preg_match_all('~name="groups\[\]" value="([^"]+)"~', $html, $m);

        return array_values(array_filter($m[1], static function (string $key) use ($prefix, $except): bool {
            foreach ($except as $no) {
                if (str_starts_with($key, $no)) {
                    return false;
                }
            }

            return $prefix === '' || str_starts_with($key, $prefix);
        }));
    }

    /** @return list<string> */
    private static function checkedGroups(string $html): array
    {
        preg_match_all('~<input type="checkbox" id="grp-[^"]+" name="groups\[\]" value="([^"]+)"[^>]*? checked>~s', $html, $m);

        return $m[1];
    }

    /** @return list<array{0:int,1:string,2:?string}> */
    private function participants(int $threadId): array
    {
        $rows = $this->pdo->query('SELECT user_id, recipient_kind, via_label FROM tenant_message_thread_users WHERE thread_id = ' . $threadId . ' ORDER BY user_id')->fetchAll(PDO::FETCH_NUM);

        return array_map(static fn (array $r): array => [(int) $r[0], (string) $r[1], $r[2] !== null ? (string) $r[2] : null], $rows);
    }

    private function rows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    /**
     * @template T
     * @param list<T> $values
     * @return list<T>
     */
    private static function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    /** Fichier du dépôt, fins de ligne ramenées à LF : aucun test ne dépend du checkout. */
    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $path));
    }

    private static function assertStringEndsWithCompat(string $suffix, string $value): void
    {
        self::assertTrue(str_ends_with($value, $suffix), $value . ' ne finit pas par ' . $suffix);
    }

    private static function assertDoesNotMatchRegularExpressionCompat(string $pattern, string $value): void
    {
        self::assertSame(0, preg_match($pattern, $value), $pattern);
    }
}
