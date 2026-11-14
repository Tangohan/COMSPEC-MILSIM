<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\JnetLinksRepository;
use App\Services\Jnet\JnetLinkRules as R;
use App\Services\Jnet\JnetLinkService;
use App\Services\Jnet\JnetLinkViewer;
use App\Services\Jnet\JnetSpaceRules;
use App\Support\JnetSpaceTree;
use PHPUnit\Framework\TestCase;

/**
 * JNET : liaisons entre unités (appui, partenaire, rattachement temporaire) et liens entre contenus. Matrice de droits
 * (auteur, lecteur, cible masquée), création refusée, « Lien masqué », échéance d'une liaison, migration, câblage, CSRF.
 */
final class JnetLinksTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** @return array{type: string, id: string, title: string, sub: string, href: string, group: string, space?: int} */
    private static function item(string $type, string $id, string $title, string $group = 'autres', ?int $space = null, string $sub = ''): array
    {
        $o = ['type' => $type, 'id' => $id, 'title' => $title, 'sub' => $sub, 'href' => '/x/' . $type . '/' . $id, 'group' => $group];
        if ($space !== null) {
            $o['space'] = $space;
        }

        return $o;
    }

    /** Un lecteur qui voit ce qu'on lui montre : tout le reste est masqué. */
    private static function viewer(int $user, array $visible, array $over = []): FakeLinkViewer
    {
        $v = new FakeLinkViewer($user);
        foreach ($visible as $item) {
            $v->visible[$item['type'] . ':' . $item['id']] = $item;
        }
        foreach ($over as $k => $val) {
            $v->$k = $val;
        }

        return $v;
    }

    // ——— Références et paires ———

    public function testReferencesAreValidatedPerType(): void
    {
        self::assertSame(['type' => 'operation', 'id' => '12'], R::parseRef('operation:12'));
        self::assertSame(['type' => 'cible', 'id' => 'di-4'], R::parseRef('cible:di-4'));
        self::assertNull(R::parseRef('cible:12'), 'une cible porte un identifiant di- ou wl-');
        self::assertNull(R::parseRef('operation:0'));
        self::assertNull(R::parseRef('operation:1;DROP'));
        self::assertNull(R::parseRef('inconnu:3'));
        self::assertNull(R::parseRef('operation'));
        self::assertNull(R::parseRef('page:12345678901'));
        self::assertSame(8, count(R::TYPES));
    }

    public function testPairsAreNeverSelfNorTwoUnitsNorTwoMembers(): void
    {
        self::assertFalse(R::canPair('page', '3', 'page', '3'));
        self::assertFalse(R::canPair('unite', '3', 'unite', '4'), 'deux unités : c’est la liaison d’unités');
        self::assertFalse(R::canPair('membre', '3', 'membre', '4'));
        self::assertTrue(R::canPair('page', '3', 'page', '4'));
        self::assertTrue(R::canPair('operation', '3', 'cible', 'wl-4'));
        self::assertFalse(R::canPair('operation', '3', 'cible', '4'));
    }

    // ——— Création : l'auteur doit voir la source et la cible ———

    public function testCreateNeedsAuthorToSeeBothEndsAndSaysNothingMore(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $op = self::item('operation', '5', 'OP SPECTRE');
        $order = self::item('echange', '9', 'Ordre préparatoire', 'ordres');
        $target = self::item('cible', 'di-2', 'Dossier X', 'cibles');

        $author = self::viewer(7, [$op, $order], ['writeSpaces' => [4]]);
        $ok = $svc->create($author, 'operation:5', 'echange:9', 4);
        self::assertNull($ok['error']);
        self::assertSame(1, count($repo->links));

        // La cible (renseignement) n'est pas vue de l'auteur : refus, même message qu'un objet inexistant.
        $r1 = $svc->create($author, 'operation:5', 'cible:di-2', 4);
        $r2 = $svc->create($author, 'operation:5', 'echange:404', 4);
        self::assertSame('Élément introuvable.', $r1['error']);
        self::assertSame($r1['error'], $r2['error'], 'rien ne distingue « masqué » de « inexistant »');
        // La source non vue est refusée de même.
        self::assertSame('Élément introuvable.', $svc->create(self::viewer(8, [$order], ['writeSpaces' => [4]]), 'operation:5', 'echange:9', 4)['error']);
        self::assertSame(1, count($repo->links), 'aucun lien posé par un refus');
        // Un auteur qui voit la cible peut la lier.
        self::assertNull($svc->create(self::viewer(7, [$op, $target], ['writeSpaces' => [4]]), 'operation:5', 'cible:di-2', 4)['error']);
    }

    public function testCreateNeedsWriteInTheSpaceAndMembershipOutsideSpaces(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $items = [self::item('operation', '5', 'OP'), self::item('echange', '9', 'Ordre', 'ordres'), self::item('operation', '6', 'OP 2')];

        $reader = self::viewer(7, $items, ['writeSpaces' => []]);
        self::assertStringContainsString('l’écriture', $svc->create($reader, 'echange:9', 'operation:5', 4)['error']);
        // Hors espace (fiche d'opération) : membre d'une unité ou encadrement.
        self::assertStringContainsString('membres d’une unité', $svc->create($reader, 'operation:5', 'echange:9', 0)['error']);
        self::assertNull($svc->create(self::viewer(7, $items, ['own' => [4]]), 'operation:5', 'echange:9', 0)['error']);
        self::assertNull($svc->create(self::viewer(8, $items, ['orgManager' => true]), 'echange:9', 'operation:6', 0)['error'], 'l’encadrement pose aussi des liens hors espace');
        self::assertSame(2, count($repo->links));
    }

    public function testSpaceOfALinkOnAPageOrTaskIsTheServersNotTheClients(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $page = self::item('page', '3', 'Briefing', 'pages', 11);
        $op = self::item('operation', '5', 'OP');
        // L'auteur écrit dans l'unité 11 (celle de la page), pas dans la 99 que le client prétend.
        $v = self::viewer(7, [$page, $op], ['writeSpaces' => [11]]);
        self::assertNull($svc->create($v, 'page:3', 'operation:5', 99)['error']);
        self::assertSame(11, $repo->links[1]['space_id']);
        // Sans écriture dans l'unité de la page, la revendication d'une autre unité ne sert à rien.
        $other = self::viewer(8, [$page, $op], ['writeSpaces' => [99]]);
        self::assertNotNull($svc->create($other, 'page:3', 'tache:1', 99)['error']);
        self::assertStringContainsString('écriture', (string) $svc->create($other, 'page:3', 'operation:5', 99)['error']);
    }

    public function testDuplicatesAndCapAreRefused(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $v = self::viewer(7, [self::item('operation', '5', 'OP'), self::item('echange', '9', 'Ordre')], ['writeSpaces' => [4]]);
        self::assertNull($svc->create($v, 'operation:5', 'echange:9', 4)['error']);
        self::assertSame('Ces deux éléments sont déjà liés.', $svc->create($v, 'operation:5', 'echange:9', 4)['error']);
        self::assertSame('Ces deux éléments sont déjà liés.', $svc->create($v, 'echange:9', 'operation:5', 4)['error'], 'même paire dans l’autre sens');
        self::assertNotNull($svc->create($v, 'operation:5', 'operation:5', 4)['error']);
        $repo->capped = true;
        self::assertStringContainsString((string) R::LINKS_PER_OBJECT_MAX, (string) $svc->create(self::viewer(7, [self::item('operation', '6', 'OP2'), self::item('echange', '10', 'E')], ['writeSpaces' => [4]]), 'operation:6', 'echange:10', 4)['error']);
    }

    public function testNothingIsWrittenWhileTheTablesAreMissing(): void
    {
        $repo = new MemLinksRepo();
        $repo->ready = false;
        $svc = new JnetLinkService($repo);
        $v = self::viewer(7, [self::item('operation', '5', 'OP'), self::item('echange', '9', 'Ordre')], ['writeSpaces' => [4]]);
        self::assertStringContainsString('migration', (string) $svc->create($v, 'operation:5', 'echange:9', 4)['error']);
        self::assertSame([], $svc->linksOf($v, 'operation', '5'));
        self::assertStringContainsString('prochaine mise à jour', (string) $svc->createUnitLink($v, 4, 5, ['kind' => 'appui', 'direction' => 'out']));
    }

    // ——— Affichage : droits de la cible, au moment de l'affichage ———

    public function testReaderWhoCannotSeeTheLinkedObjectGetsAMaskedLinkWithNothingInIt(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $op = self::item('operation', '5', 'OP SPECTRE');
        $secret = self::item('cible', 'di-2', 'Nom de code CORBEAU', 'cibles', null, 'Objectif prioritaire');
        $order = self::item('echange', '9', 'Ordre préparatoire', 'ordres');
        $repo->seed('operation', '5', 'cible', 'di-2', 4, 7);
        $repo->seed('operation', '5', 'echange', '9', 4, 7);

        $author = self::viewer(7, [$op, $secret, $order], ['writeSpaces' => [4]]);
        $inside = $svc->linksOf($author, 'operation', '5');
        self::assertSame(2, count($inside));
        self::assertSame('Ordre préparatoire', $inside[0]['title']);
        self::assertSame('Nom de code CORBEAU', $inside[1]['title']);

        // Un autre lecteur ne passe pas la porte du renseignement : « Lien masqué », sans titre, sans type, sans adresse.
        $reader = self::viewer(8, [$op, $order]);
        $seen = $svc->linksOf($reader, 'operation', '5');
        self::assertSame(2, count($seen));
        $masked = $seen[1];
        self::assertTrue($masked['hidden']);
        self::assertSame(['id', 'hidden', 'canRemove'], array_keys($masked), 'aucune fuite : ni titre, ni type, ni adresse, ni auteur');
        self::assertFalse($masked['canRemove']);
        self::assertStringNotContainsString('CORBEAU', (string) json_encode($seen, JSON_UNESCAPED_UNICODE));
        self::assertStringNotContainsString('di-2', (string) json_encode($seen));
        self::assertFalse($seen[0]['hidden']);
    }

    public function testLinkVisibilityFollowsTheReaderNotTheAuthorAtDisplayTime(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $repo->seed('page', '3', 'operation', '5', 11, 7);
        $page = self::item('page', '3', 'Briefing', 'pages', 11);
        $op = self::item('operation', '5', 'OP SPECTRE');
        // L'objet lié devient invisible (unité masquée, droit retiré) : le lien se masque, sans être supprimé.
        self::assertFalse($svc->linksOf(self::viewer(7, [$page, $op]), 'page', '3')[0]['hidden']);
        self::assertTrue($svc->linksOf(self::viewer(7, [$page]), 'page', '3')[0]['hidden']);
        self::assertSame(1, count($repo->links));
        // Le lien se lit aussi depuis la cible.
        self::assertSame('Briefing', $svc->linksOf(self::viewer(8, [$page, $op]), 'operation', '5')[0]['title']);
    }

    public function testBatchForExchangesKeepsEachExchangeSeparate(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $repo->seed('echange', '1', 'operation', '5', 4, 7);
        $repo->seed('echange', '2', 'page', '3', 4, 7);
        $v = self::viewer(7, [self::item('operation', '5', 'OP'), self::item('page', '3', 'Briefing', 'pages', 4)]);
        $by = $svc->linksOfMany($v, 'echange', [1, 2, 3]);
        self::assertSame('OP', $by['1'][0]['title']);
        self::assertSame('Briefing', $by['2'][0]['title']);
        self::assertArrayNotHasKey('3', $by);
    }

    public function testOperationOverviewGroupsEverythingThatTouchesIt(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $rows = [
            ['echange', '1', 'ordres', 'Ordre 1'], ['echange', '2', 'comptes_rendus', 'CR 2'], ['tache', '3', 'taches', 'Tâche 3'],
            ['page', '4', 'pages', 'Page 4'], ['document', '5', 'photos', 'Photo 5'], ['cible', 'di-6', 'cibles', 'Cible 6'], ['unite', '7', 'unites', 'Unité 7'],
        ];
        $items = [self::item('operation', '5', 'OP')];
        foreach ($rows as [$t, $i, $g, $title]) {
            $repo->seed('operation', '5', $t, $i, 0, 7);
            $items[] = self::item($t, $i, $title, $g);
        }
        $repo->seed('operation', '5', 'membre', '8', 0, 7);
        $ov = $svc->overview(self::viewer(7, $items), 'operation', '5');
        self::assertSame(['ordres', 'comptes_rendus', 'taches', 'pages', 'photos', 'cibles', 'unites'], array_keys($ov['groups']));
        self::assertSame(8, $ov['total']);
        self::assertSame(1, count($ov['hidden']), 'le membre non vu reste un lien masqué, à part');
        self::assertSame(['ordres', 'comptes_rendus'], [R::groupOf('echange', 'ordre'), R::groupOf('echange', 'compte_rendu')]);
        self::assertSame('photos', R::groupOf('document', '', true));
    }

    // ——— Suppression ———

    public function testOnlyTheAuthorOrTheSpaceChiefRemovesALink(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $repo->seed('page', '3', 'operation', '5', 11, 7);
        $repo->seed('operation', '5', 'echange', '9', 0, 7);
        $first = array_key_first($repo->links);

        self::assertSame('Seuls l’auteur du lien et le chef de l’espace le retirent.', $svc->remove(self::viewer(8, [], ['writeSpaces' => [11]]), $first));
        self::assertSame(2, count($repo->links));
        self::assertNull($svc->remove(self::viewer(9, [], ['moderateSpaces' => [11]]), $first), 'le chef de l’espace du lien');
        self::assertSame(1, count($repo->links));
        // Un lien posé hors espace : l'auteur ou l'encadrement, pas le chef d'une unité quelconque.
        $second = array_key_first($repo->links);
        self::assertNotNull($svc->remove(self::viewer(9, [], ['moderateSpaces' => [11], 'chiefSpaces' => [11]]), $second));
        self::assertNull($svc->remove(self::viewer(10, [], ['orgManager' => true]), $second));
        self::assertSame(0, count($repo->links));
        self::assertSame('Lien introuvable.', $svc->remove(self::viewer(7, []), 99));
    }

    public function testAuthorCanRemoveAMaskedLinkOfHisOwn(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $repo->seed('operation', '5', 'cible', 'di-2', 0, 7);
        $items = $svc->linksOf(self::viewer(7, [self::item('operation', '5', 'OP')]), 'operation', '5');
        self::assertTrue($items[0]['hidden']);
        self::assertTrue($items[0]['canRemove'], 'l’auteur peut retirer son lien même s’il ne voit plus la cible');
        self::assertFalse($svc->linksOf(self::viewer(8, [self::item('operation', '5', 'OP')]), 'operation', '5')[0]['canRemove']);
    }

    // ——— Citations dans les pages ———

    public function testMentionsAreParsedSanitizedAndRenderedForTheReader(): void
    {
        $text = "Voir [[operation:5|OP SPECTRE]] et [[unite:7|2e Section]], puis [[cible:di-2|secret]] et [[page:3|<b>x</b>]].";
        $m = R::mentions($text);
        self::assertSame(3, count($m), 'les cibles ne se citent pas dans une page');
        self::assertSame(['operation', 'unite', 'page'], array_column($m, 'type'));

        // À l'enregistrement : ce que l'auteur ne voit pas redevient du texte simple.
        $clean = R::sanitizeMentions($text, static fn (string $t, string $i): bool => $t !== 'unite');
        self::assertStringContainsString('[[operation:5|OP SPECTRE]]', $clean);
        self::assertStringNotContainsString('[[unite:7', $clean);
        self::assertStringContainsString('2e Section', $clean);

        // À l'affichage : le titre vient du serveur, jamais du libellé tapé ; un objet non vu est « Lien masqué ».
        $html = R::renderMentions($text, [
            'operation:5' => ['title' => 'OP SPECTRE (titre actuel)', 'href' => '/jnet/operations/5'],
            'unite:7' => null,
            'page:3' => ['title' => '<script>alert(1)</script>', 'href' => '/p?a=1&b=2'],
        ]);
        self::assertStringContainsString('<a class="jl-mention" href="/jnet/operations/5">OP SPECTRE (titre actuel)</a>', $html);
        self::assertStringContainsString('Lien masqué', $html);
        self::assertStringNotContainsString('2e Section', $html, 'le libellé tapé ne fuit pas');
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('href="/p?a=1&amp;b=2"', $html);
        self::assertSame('Voir OP SPECTRE', R::plain('Voir [[operation:5|OP SPECTRE]]'));
        self::assertSame('a<br />' . "\n" . 'b', R::renderMentions("a\nb", []));
        // Une citation mal formée reste du texte.
        self::assertSame(0, count(R::mentions('[[operation:abc|x]] [[operation:5]] [[autre:5|x]]')));
    }

    public function testSanitizeKeepsAtMostTheMentionCapAndNeverTrustsTheLabel(): void
    {
        $many = str_repeat('[[unite:1|U]] ', R::MENTIONS_PER_SAVE_MAX + 5);
        $out = R::sanitizeMentions($many, static fn (): bool => true);
        self::assertSame(R::MENTIONS_PER_SAVE_MAX, substr_count($out, '[[unite:1|U]]'));
        self::assertSame('[[membre:3|Cpl Echo]]', R::mentionToken('membre', 3, "Cpl\n|Echo]"));
        self::assertSame(R::LABEL_MAX, mb_strlen(R::cleanLabel(str_repeat('é', 200))));
    }

    public function testMentionsResolveThroughTheViewersRights(): void
    {
        $svc = new JnetLinkService(new MemLinksRepo());
        $v = self::viewer(7, [self::item('operation', '5', 'OP'), self::item('unite', '7', 'Section')]);
        $r = $svc->resolveMentions($v, ['[[operation:5|a]] [[page:3|b]]', '[[unite:7|c]]']);
        self::assertSame('OP', $r['operation:5']['title']);
        self::assertNull($r['page:3']);
        self::assertSame('Section', $r['unite:7']['title']);
        self::assertSame('x [[unite:7|Section]]', $svc->sanitizeMentions($v, 'x [[unite:7|Section]]'));
        self::assertSame('x Dossier', $svc->sanitizeMentions($v, 'x [[page:3|Dossier]]'));
    }

    // ——— Liaisons d'unités ———

    public function testStatusFollowsDatesAndManualEnd(): void
    {
        $today = '2026-11-15';
        self::assertSame('active', R::statusOf(['starts_on' => null, 'ends_on' => null, 'ended_at' => null], $today));
        self::assertSame('active', R::statusOf(['starts_on' => '2026-11-01', 'ends_on' => '2026-11-15', 'ended_at' => null], $today), 'le dernier jour compte');
        self::assertSame('expired', R::statusOf(['starts_on' => '2026-11-01', 'ends_on' => '2026-11-14', 'ended_at' => null], $today));
        self::assertSame('planned', R::statusOf(['starts_on' => '2026-11-20', 'ends_on' => null, 'ended_at' => null], $today));
        self::assertSame('expired', R::statusOf(['starts_on' => null, 'ends_on' => null, 'ended_at' => '2026-11-10 09:00:00'], $today), 'terminée à la main');
        self::assertTrue(R::isEffective('active'));
        self::assertFalse(R::isEffective('planned'));
        self::assertFalse(R::isEffective('expired'));
    }

    public function testUnitLinkFormIsValidated(): void
    {
        $today = '2026-11-15';
        self::assertSame('Choisissez le type de liaison.', R::normalizeUnitLink(['kind' => 'x'], $today)['error']);
        self::assertSame('Précisez le sens de la liaison.', R::normalizeUnitLink(['kind' => 'appui', 'direction' => 'x'], $today)['error']);
        self::assertSame('La date de début n’est pas valide.', R::normalizeUnitLink(['kind' => 'appui', 'direction' => 'out', 'starts_on' => '2026-02-31'], $today)['error']);
        self::assertSame('La date de fin est déjà passée.', R::normalizeUnitLink(['kind' => 'rattachement', 'direction' => 'in', 'ends_on' => '2026-11-01'], $today)['error']);
        self::assertSame('La fin ne peut pas précéder le début.', R::normalizeUnitLink(['kind' => 'appui', 'direction' => 'out', 'starts_on' => '2026-12-10', 'ends_on' => '2026-12-01'], $today)['error']);
        $ok = R::normalizeUnitLink(['kind' => 'partenaire', 'direction' => 'out', 'note' => "  <b>Appui</b>  feu\n", 'grant_read' => '1', 'starts_on' => '2026-11-20', 'ends_on' => '2026-12-20'], $today);
        self::assertNull($ok['error']);
        self::assertSame('mutual', $ok['values']['direction'], 'un partenariat est réciproque');
        self::assertSame('Appui feu', $ok['values']['note']);
        self::assertTrue($ok['values']['grant_read']);
        self::assertFalse(R::normalizeUnitLink(['kind' => 'appui', 'direction' => 'out'], $today)['values']['grant_read'], 'rien n’est ouvert par défaut');
        self::assertSame('1re Section appuie 2e Section', R::sentence('appui', 'out', '1re Section', '2e Section'));
        self::assertSame('1re Section est rattachée temporairement à 2e Section', R::sentence('rattachement', 'in', '1re Section', '2e Section'));
        self::assertSame('in', R::oppositeRole('out'));
        self::assertSame('du 12/11/2026 au 20/11/2026', R::periodLabel('2026-11-12', '2026-11-20'));
        self::assertSame('sans date de fin', R::periodLabel(null, null));
    }

    public function testOnlyTheChiefCreatesAUnitLinkAndTheHierarchyIsLeftAlone(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $form = ['kind' => 'appui', 'direction' => 'out'];
        $chief = self::viewer(7, [], ['labels' => [4 => '1re Section', 5 => '2e Section', 6 => 'Etat-major'], 'chiefSpaces' => [4]]);

        self::assertNull($svc->createUnitLink($chief, 4, 5, $form));
        self::assertSame(1, count($repo->unit));
        self::assertSame('Cette liaison existe déjà.', $svc->createUnitLink($chief, 4, 5, $form));
        // Un membre qui n'est pas chef : refus côté serveur, même si le formulaire lui a été montré.
        $member = self::viewer(8, [], ['labels' => [4 => '1re Section', 5 => '2e Section'], 'chiefSpaces' => []]);
        self::assertStringContainsString('Seuls le chef', (string) $svc->createUnitLink($member, 4, 5, ['kind' => 'partenaire']));
        // Le chef d'une autre unité ne crée pas depuis l'unité d'un autre.
        $otherChief = self::viewer(9, [], ['labels' => [4 => 'A', 5 => 'B'], 'chiefSpaces' => [5]]);
        self::assertStringContainsString('Seuls le chef', (string) $svc->createUnitLink($otherChief, 4, 5, $form));
        // Unité qu'il ne voit pas : introuvable. Soi-même : refus. Filiation : la hiérarchie règle déjà.
        self::assertSame('Choisissez une autre unité de l’organisation.', $svc->createUnitLink($chief, 4, 99, $form));
        self::assertSame('Choisissez une autre unité de l’organisation.', $svc->createUnitLink($chief, 4, 4, $form));
        $chief->related = [[4, 6]];
        self::assertStringContainsString('filiation', (string) $svc->createUnitLink($chief, 4, 6, $form));
        self::assertSame(1, count($repo->unit));
    }

    public function testExpiredUnitLinkProducesNoEffectAndOnlyTheChiefSeesItsHistory(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $chief = self::viewer(7, [], ['labels' => [4 => 'A', 5 => 'B'], 'chiefSpaces' => [4], 'today' => '2026-11-15']);
        self::assertNull($svc->createUnitLink($chief, 4, 5, ['kind' => 'rattachement', 'direction' => 'out', 'ends_on' => '2026-11-20', 'grant_read' => '1']));

        $list = $svc->unitLinksOf($chief, 4);
        self::assertSame('active', $list[0]['status']);
        self::assertTrue($list[0]['grantRead']);
        self::assertSame('A accueille, en rattachement temporaire, B', $list[0]['sentence']);

        // Le temps passe : la liaison est échue, plus aucun effet (lecture accordée, marquage), historique réservé au chef.
        $chief->today = '2026-11-21';
        $later = $svc->unitLinksOf($chief, 4);
        self::assertSame('expired', $later[0]['status']);
        self::assertFalse($later[0]['grantRead'] ?? false, 'la lecture accordée cesse avec la liaison');
        self::assertSame('Échue', $later[0]['statusLabel']);
        self::assertFalse($later[0]['canEnd']);
        $member = self::viewer(8, [], ['labels' => [4 => 'A', 5 => 'B'], 'today' => '2026-11-21']);
        self::assertSame([], $svc->unitLinksOf($member, 4), 'un membre ne voit pas les liaisons échues');
        self::assertSame([], $svc->hubMarks($member), 'une liaison échue ne marque plus aucune unité');
        self::assertSame(1, count($svc->hubMarks(self::viewer(8, [], ['labels' => [4 => 'A', 5 => 'B'], 'today' => '2026-11-18']))[4] ?? []));
    }

    public function testEndingALinkIsForTheChiefOfEitherUnitOnly(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $chief = self::viewer(7, [], ['labels' => [4 => 'A', 5 => 'B'], 'chiefSpaces' => [4]]);
        $svc->createUnitLink($chief, 4, 5, ['kind' => 'appui', 'direction' => 'out']);
        $id = array_key_first($repo->unit);

        self::assertStringContainsString('Seul le chef', (string) $svc->endUnitLink(self::viewer(8, [], ['labels' => [4 => 'A', 5 => 'B']]), $id));
        self::assertNull($svc->endUnitLink(self::viewer(9, [], ['labels' => [4 => 'A', 5 => 'B'], 'chiefSpaces' => [5]]), $id), 'le chef du partenaire peut y mettre fin');
        self::assertNotNull($repo->unit[$id]['ended_at']);
        self::assertSame('Cette liaison est déjà échue.', $svc->endUnitLink($chief, $id));
        self::assertSame('Liaison introuvable.', $svc->endUnitLink($chief, 99));
        // Après la fin, plus de marquage, et le chef recrée si besoin.
        self::assertSame([], $svc->hubMarks($chief));
        self::assertNull($svc->createUnitLink($chief, 4, 5, ['kind' => 'appui', 'direction' => 'out']));
    }

    public function testHiddenPartnerLeavesNoNameAndNoMark(): void
    {
        $repo = new MemLinksRepo();
        $svc = new JnetLinkService($repo);
        $chief = self::viewer(7, [], ['labels' => [4 => 'A', 5 => 'Unité confidentielle'], 'chiefSpaces' => [4]]);
        $svc->createUnitLink($chief, 4, 5, ['kind' => 'partenaire']);
        // Un lecteur de l'unité A ne voit pas l'unité 5 dans son ORBAT : rien de son nom ni de son identifiant.
        $reader = self::viewer(8, [], ['labels' => [4 => 'A']]);
        $list = $svc->unitLinksOf($reader, 4);
        self::assertTrue($list[0]['hidden']);
        self::assertSame(0, $list[0]['otherId']);
        self::assertStringNotContainsString('confidentielle', (string) json_encode($list, JSON_UNESCAPED_UNICODE));
        self::assertSame([], $svc->hubMarks($reader));
        self::assertSame(2, count($svc->hubMarks(self::viewer(8, [], ['labels' => [4 => 'A', 5 => 'Unité confidentielle']]))), 'visible des deux côtés : les deux unités sont marquées');
    }

    // ——— Une liaison ne donne jamais plus de droits que le modèle ———

    public function testLinkReadOpensReadingOnlyAndOnlyWhereTheOrbatAlreadyShowsTheUnit(): void
    {
        $tree = JnetSpaceTree::fromRows([[1, 0, 'A'], [2, 0, 'B'], [3, 0, 'C'], [4, 2, 'B1']]);
        $rights = static fn (int $space, array $viewerUnits, array $rules, ?JnetSpaceTree $t = null): array => JnetSpaceRules::rights($t ?? $tree, $space, 5, $viewerUnits, [], false, false, false, $rules);
        $closed = ['access' => [3 => 'read']];

        // L'espace A est réservé à C : un membre de B n'y lit pas.
        self::assertFalse($rights(1, [2], $closed)['read']);
        // La liaison « lecture » cochée par le chef de A l'ouvre à B et à ses sous-unités : lecture seule.
        $linked = $closed + ['link_read' => [2]];
        $r = $rights(1, [2], $linked);
        self::assertTrue($r['read']);
        self::assertFalse($r['write'], 'jamais l’écriture');
        self::assertFalse($r['publish'], 'jamais la publication d’un ordre');
        self::assertFalse($r['lead']);
        self::assertTrue($rights(1, [4], $linked)['read'], 'les sous-unités du partenaire suivent');
        // Une autre unité, non liée, reste dehors.
        self::assertFalse($rights(1, [3 + 10], $linked)['read'] ?? false);
        self::assertSame(['read' => false, 'write' => false, 'lead' => false, 'publish' => false, 'chief' => false], $rights(1, [2], $closed + ['link_read' => []]));
        // Une unité masquée par l'ORBAT du lecteur (absente de son arbre) reste invisible malgré la liaison.
        $hidden = JnetSpaceTree::fromRows([[2, 0, 'B'], [3, 0, 'C']]);
        self::assertFalse($rights(1, [2], $linked, $hidden)['read']);
        // Un espace ouvert à tous ne change pas : la liaison n'ajoute rien et ne retire rien.
        self::assertSame($rights(1, [2], []), $rights(1, [2], ['link_read' => [2]]));
        // Un droit d'écriture venu de l'ORBAT n'est pas retiré par la liaison.
        self::assertTrue($rights(2, [2], ['link_read' => [1]])['write']);
    }

    public function testShareViaLinkIsAnInformationNeverAnOrderAndNeverRelayed(): void
    {
        $svc = $this->read('app/Services/Jnet/JnetSpaceService.php');
        $start = (int) strpos($svc, 'public function shareViaLink(');
        $body = substr($svc, $start, (int) strpos($svc, 'public function viewerExchange(') - $start);
        self::assertStringContainsString("'info', \$title, \$body, \$link !== '' ? \$link : null, false, [\$targetId]", $body, 'une information, sans accusé de lecture exigé');
        self::assertStringNotContainsString("'ordre'", $body);
        self::assertStringNotContainsString('autoRelay', $body);
        self::assertStringContainsString("['publish']", $body, 'seul qui publie déjà dans l’espace peut partager');
        self::assertStringContainsString('activeUnitLinks', $body, 'seule une liaison active compte');
        // Les unités liées n'entrent dans « Partager » que par une liaison active, et marquées.
        $targets = substr($svc, (int) strpos($svc, 'public function shareTargets('), 2400);
        self::assertStringContainsString("'via' => 'liaison'", $targets);
        self::assertStringContainsString('activeUnitLinks', $targets);
        // Le contrôleur n'envoie par la liaison que ce que shareTargets a proposé.
        $ctl = $this->read('app/Controllers/Web/JnetUnitWorkspaceController.php');
        self::assertStringContainsString("(\$viaLink[\$target] ?? '') === 'liaison'", $ctl);
        self::assertStringContainsString('shareViaLink', $ctl);
    }

    public function testEffectsOfALinkAreOnlyReadFromActiveLinks(): void
    {
        $svc = $this->read('app/Services/Jnet/JnetSpaceService.php');
        $active = substr($svc, (int) strpos($svc, 'public function activeUnitLinks('), 1500);
        self::assertStringContainsString('JnetLinkRules::isEffective(JnetLinkRules::statusOf(', $active);
        self::assertStringContainsString("'grant_read' =>", $active);
        $rules = $this->read('app/Services/Jnet/JnetSpaceRules.php');
        self::assertStringContainsString("\$rules['link_read']", $rules);
        // La lecture accordée ne touche ni l'écriture ni la publication.
        self::assertSame(1, substr_count($rules, "\$rules['link_read']"), 'un seul endroit : le calcul de la lecture');
        $ctl = $this->read('app/Controllers/Web/JnetLinksController.php');
        self::assertStringNotContainsString('canPostIn', $ctl);
    }

    // ——— Câblage, CSRF, vues, migration ———

    public function testWritesArePostWithCsrfAndServerSideRightsAndNoClientRedirect(): void
    {
        $routes = $this->read('routes/web.php');
        foreach (['/jnet/liens' => 'create', '/jnet/liens/{id}/supprimer' => 'remove', '/jnet/u/{id}/liaisons' => 'createUnitLink', '/jnet/u/{id}/liaisons/{lid}/fin' => 'endUnitLink'] as $path => $method) {
            self::assertMatchesRegularExpression('#\$router->post\(\'' . preg_quote($path, '#') . '\', \[\\\\App\\\\Controllers\\\\Web\\\\JnetLinksController::class, \'' . $method . '\'\], \[AuthMiddleware::class\]\);#', $routes, $path);
            self::assertStringNotContainsString("\$router->get('" . $path . "'", $routes, $path);
        }
        self::assertStringContainsString("\$router->get('/jnet/liens/recherche', [\\App\\Controllers\\Web\\JnetLinksController::class, 'search']", $routes);
        self::assertStringContainsString("\$router->get('/jnet/liens/nouveau', [JnetPortalController::class, 'linkNew']", $routes);

        $ctl = $this->read('app/Controllers/Web/JnetLinksController.php');
        self::assertStringContainsString('Csrf::validate', $ctl);
        self::assertSame(4, preg_match_all('/\$this->guard\(\$request\)/', $ctl), 'chaque écriture passe par le même garde-fou');
        self::assertStringNotContainsString("input('back'", $ctl);
        self::assertStringNotContainsString("input('redirect'", $ctl);
        self::assertStringContainsString('isSpaceChief', $this->read('app/Services/Jnet/JnetLinkAccess.php'), 'même droit que « Personnaliser »');
        // La recherche est une lecture : GET, query(), et chaque résultat repasse par le contrôle de droits.
        $search = substr($ctl, (int) strpos($ctl, 'public function search('), 1200);
        self::assertStringNotContainsString('->input(', $search);
        self::assertStringContainsString('->search(', $search);
        // Le service ne décide d'aucun droit général : il ne connaît ni Gate ni permission.
        $svc = $this->read('app/Services/Jnet/JnetLinkService.php') . $this->read('app/Services/Jnet/JnetLinkRules.php');
        self::assertStringNotContainsString('Gate::', $svc);
        self::assertStringNotContainsString('->allows(\'jnet', $svc . $ctl);
    }

    public function testEveryLinkSourceGoesThroughTheExistingDoors(): void
    {
        $a = $this->read('app/Services/Jnet/JnetLinkAccess.php');
        self::assertStringContainsString('mayReadIntelligence()', $a, 'cibles et échanges de renseignement : porte du renseignement SSE');
        self::assertStringContainsString('viewerExchange(', $a, 'échanges : unité émettrice et destinataires visibles du lecteur');
        self::assertStringContainsString('canRead(', $a, 'unités, pages, tâches : lecture de l’espace');
        self::assertStringContainsString("'hidden_modules'", $a, 'rubrique masquée par le chef');
        self::assertStringContainsString('fileForViewer(', $a, 'documents : droits du partage de fichiers');
        self::assertStringContainsString('->tree()->has(', $a . '', 'ORBAT confidentiel : unité absente de l’arbre du lecteur');
        self::assertStringContainsString('catch (\Throwable)', $a, 'en cas de doute : non vu');
        $files = $this->read('app/Services/Jnet/JnetUnitFiles.php');
        self::assertStringContainsString('fileLevel($f) < FileShareAccess::VIEW', $files);
        // Toutes les requêtes sont bornées par communauté.
        $repo = $this->read('app/Repositories/JnetLinksRepository.php');
        self::assertSame(0, preg_match('/(SELECT|DELETE|UPDATE)[^\']*jnet_(unit_)?links[^\']*WHERE (?!tenant_id)/', $repo));
        self::assertStringContainsString('DELETE FROM jnet_links WHERE tenant_id = ? AND id = ?', $repo);
        self::assertStringContainsString('UPDATE jnet_unit_links SET ended_at = NOW(), ended_by = ? WHERE tenant_id = ? AND id = ?', $repo);
    }

    public function testMigrationIsIdempotentAndReversible(): void
    {
        $sql = $this->read('migrations/2026_11_14_070_jnet_links.sql');
        self::assertSame(2, preg_match_all('/CREATE TABLE IF NOT EXISTS (jnet_unit_links|jnet_links)\b/', $sql));
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('INSERT', $sql, 'aucune donnée ajoutée');
        self::assertStringContainsString('UNIQUE KEY uq_jnet_links_pair (tenant_id, src_type, src_id, dst_type, dst_id)', $sql);
        self::assertStringContainsString('ON DELETE CASCADE', $sql);
        $step = $this->read('database/steps/4550_jnet_links.php');
        self::assertStringContainsString('tableExists', $step);
        self::assertStringContainsString('2026_11_14_070_jnet_links.sql', $step);
        $down = $this->read('migrations/rollback/jnet_links_down.sql');
        self::assertStringContainsString('DROP TABLE IF EXISTS jnet_links;', $down);
        self::assertStringContainsString('DROP TABLE IF EXISTS jnet_unit_links;', $down);
        $registry = $this->read('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'jnet_links'", $registry);
        self::assertStringContainsString('4550_jnet_links.php', $registry);
        self::assertFileExists(self::ROOT . 'database/steps/4550_jnet_links.php');
        // Les écrans tolèrent l'absence des tables.
        $repo = $this->read('app/Repositories/JnetLinksRepository.php');
        self::assertStringContainsString("tableExists('jnet_unit_links')", $repo);
        self::assertStringContainsString("tableExists('jnet_links')", $repo);
        self::assertStringContainsString("'SELECT 1 FROM ' . \$table . ' LIMIT 1'", $repo);
        $ctl = $this->read('app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString("'ready' => \$this->links->linksReady()", $ctl);
        self::assertStringContainsString('if (!$this->links->unitLinksReady())', $ctl);
        self::assertStringContainsString('if (!$this->links->linksReady())', $ctl);
    }

    public function testViewsAreQuietHonestAndEachFormCarriesItsToken(): void
    {
        $files = ['views/jnet/_linked.php', 'views/jnet/unit/_unit_links.php', 'views/jnet/link_new.php', 'views/jnet/operation_show.php', 'views/jnet/target_show.php', 'public/assets/js/jnet_links.js', 'public/assets/css/jnet_links.css'];
        foreach ($files as $file) {
            $src = $this->read($file);
            self::assertStringNotContainsString('alert(', $src, $file);
            self::assertStringNotContainsString('confirm(', $src, $file);
            self::assertStringNotContainsString('innerHTML', $src, $file);
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src), 'aucun emoji : ' . $file);
            self::assertStringNotContainsString('Lorem', $src, $file);
        }
        foreach (['views/jnet/_linked.php', 'views/jnet/unit/_unit_links.php', 'views/jnet/link_new.php'] as $view) {
            $src = $this->read($view);
            self::assertSame(substr_count($src, 'method="post"'), substr_count($src, 'Csrf::field()'), 'un jeton par formulaire : ' . $view);
            self::assertStringContainsString('htmlspecialchars', $src, $view);
        }
        // Un lien masqué n'affiche que « Lien masqué ».
        $linked = $this->read('views/jnet/_linked.php');
        self::assertStringContainsString('Lien masqué', $linked);
        self::assertSame(0, preg_match('/is-hidden[^\n]*\$it\[\'title\'\]/', $linked));
        // Les écrans qui montrent les liens importent ce qu'ils utilisent.
        self::assertStringContainsString('use App\Services\Jnet\JnetLinkRules;', $this->read('views/jnet/unit/_unit_links.php'));
        self::assertStringContainsString('use App\Services\Jnet\JnetLinkRules;', $this->read('views/jnet/unit/_page.php'));
        // Les sorties du bloc « Liés à » passent par le contrôle des droits : elles viennent de JnetLinkService.
        $portal = $this->read('app/Controllers/Web/JnetPortalController.php');
        foreach (['linksOfMany(', 'linksOf(', 'overview(', 'resolveMentions(', 'unitLinksOf(', 'hubMarks('] as $call) {
            self::assertStringContainsString('->' . $call, $portal, $call);
        }
        // Les pages de l'espace : rendu des citations, bloc « Liés à », liaison dans la rubrique et le registre.
        $page = $this->read('views/jnet/unit/_page.php');
        self::assertStringContainsString('JnetLinkRules::renderMentions', $page);
        self::assertStringContainsString("\$linkBlockFor('page'", $page);
        self::assertStringContainsString('jnet/liens/recherche', $page);
        self::assertStringContainsString('data-uw-mention-search', $page);
        self::assertStringContainsString("\$linkBlockFor('tache'", $this->read('views/jnet/unit/_tasks.php'));
        self::assertStringContainsString("'liaisons' => ['Unités liées'", $this->read('views/jnet/unit_space.php'));
        self::assertStringContainsString("\$ulMode = 'block'", $this->read('views/jnet/unit/_home.php'));
        self::assertStringContainsString('$linkMarks($u)', $this->read('views/jnet/hub.php'));
        self::assertStringContainsString('data-hb-chip="linked"', $this->read('views/jnet/hub.php'));
        // Le titre de chaque bloc est un titre, sans petit libellé au-dessus.
        $units = $this->read('views/jnet/unit/_unit_links.php');
        self::assertStringNotContainsString('kicker', $units . $linked);
        self::assertStringNotContainsString('eyebrow', $units . $linked);
    }

    public function testEditorMentionsAreWiredWithAFallbackWithoutScript(): void
    {
        $js = $this->read('public/assets/js/jnet_unit_workspace.js');
        self::assertStringContainsString("data-uw-mention-search", $js);
        self::assertStringContainsString("'@'", $js);
        self::assertStringContainsString("'[['", $js);
        self::assertStringContainsString("'unite,membre,operation,page'", $js);
        self::assertStringContainsString("'[[' + r.type + ':'", $js);
        self::assertStringContainsString('window.JnetLinks', $js);
        // Le texte garde sa marque simple : la page se lit et se modifie sans script (la marque se tape à la main).
        self::assertStringContainsString('[[type:identifiant|libellé]]', $js);
        $shell = $this->read('views/jnet/unit_space.php');
        self::assertLessThan(strpos($shell, 'jnet_unit_workspace.js'), strpos($shell, 'jnet_links.js'), 'le moteur de recherche est chargé avant l’éditeur');
        // Côté serveur, les citations d'une page sont reprises à l'enregistrement.
        $ctl = $this->read('app/Controllers/Web/JnetUnitWorkspaceController.php');
        self::assertStringContainsString('withoutUnseenMentions', $ctl);
        self::assertStringContainsString('sanitizeMentions', $ctl);
        // Sans JavaScript, « Lier à… » mène à une page de recherche.
        self::assertStringContainsString("'newUrl' => url('jnet/liens/nouveau?src='", $this->read('views/jnet/unit_space.php'));
        self::assertStringContainsString('data-jl-add', $this->read('views/jnet/_linked.php'));
        self::assertStringContainsString('hasDialog', $this->read('public/assets/js/jnet_links.js'));
    }

    public function testNoOtherPageIsBrokenByTheSharedPartials(): void
    {
        // _exchange.php est repris tel quel : les liens sont ajoutés autour, par l'espace d'unité.
        self::assertStringNotContainsString('jl-', $this->read('views/jnet/_exchange.php'));
        self::assertStringNotContainsString('JnetLink', $this->read('views/jnet/_exchange.php'));
        // Une page toujours rendue sans $linkBlock (anciens appels) n'affiche rien de plus.
        self::assertStringContainsString('if ($lb === null) {', $this->read('views/jnet/_linked.php'));
        // Le contrôleur d'espace compte toujours ses quinze gardes (aucune écriture ajoutée à côté).
        self::assertSame(15, preg_match_all('/\$this->guard\(\$request, \$params,/', $this->read('app/Controllers/Web/JnetUnitWorkspaceController.php')));
    }
}

/** Dépôt en mémoire : mêmes méthodes que JnetLinksRepository, sans base. */
final class MemLinksRepo extends JnetLinksRepository
{
    public bool $ready = true;
    public bool $capped = false;

    /** @var array<int, array<string, mixed>> */
    public array $links = [];

    /** @var array<int, array<string, mixed>> */
    public array $unit = [];

    private int $seq = 0;

    public function __construct()
    {
    }

    public function unitLinksReady(): bool
    {
        return $this->ready;
    }

    public function linksReady(): bool
    {
        return $this->ready;
    }

    public function seed(string $st, string $si, string $dt, string $di, int $space, int $by): void
    {
        $id = ++$this->seq;
        $this->links[$id] = ['id' => $id, 'tenant_id' => 1, 'src_type' => $st, 'src_id' => $si, 'dst_type' => $dt, 'dst_id' => $di, 'space_id' => $space, 'created_by' => $by, 'created_at' => '2026-11-14 10:00:00'];
    }

    public function linksOf(int $tenantId, string $type, string $id, int $limit = 100): array
    {
        // Du plus récent au plus ancien, comme la requête.
        return array_values(array_reverse(array_filter($this->links, static fn (array $l): bool => ($l['src_type'] === $type && $l['src_id'] === $id) || ($l['dst_type'] === $type && $l['dst_id'] === $id))));
    }

    public function linksOfMany(int $tenantId, string $type, array $ids): array
    {
        $ids = array_map('strval', $ids);

        return array_values(array_filter($this->links, static fn (array $l): bool => ($l['src_type'] === $type && in_array($l['src_id'], $ids, true)) || ($l['dst_type'] === $type && in_array($l['dst_id'], $ids, true))));
    }

    public function link(int $tenantId, int $linkId): ?array
    {
        return $this->links[$linkId] ?? null;
    }

    public function pairExists(int $tenantId, string $aType, string $aId, string $bType, string $bId): bool
    {
        foreach ($this->links as $l) {
            if (($l['src_type'] === $aType && $l['src_id'] === $aId && $l['dst_type'] === $bType && $l['dst_id'] === $bId) || ($l['src_type'] === $bType && $l['src_id'] === $bId && $l['dst_type'] === $aType && $l['dst_id'] === $aId)) {
                return true;
            }
        }

        return false;
    }

    public function countOf(int $tenantId, string $type, string $id): int
    {
        return $this->capped ? 9999 : count($this->linksOf($tenantId, $type, $id));
    }

    public function addLink(int $tenantId, string $srcType, string $srcId, string $dstType, string $dstId, int $spaceId, int $userId): int
    {
        $this->seed($srcType, $srcId, $dstType, $dstId, $spaceId, $userId);

        return $this->seq;
    }

    public function removeLink(int $tenantId, int $linkId): void
    {
        unset($this->links[$linkId]);
    }

    public function unitLinksOf(int $tenantId, int $spaceId): array
    {
        return array_values(array_reverse(array_filter($this->unit, static fn (array $l): bool => $l['unit_id'] === $spaceId || $l['partner_unit_id'] === $spaceId)));
    }

    public function openUnitLinks(int $tenantId): array
    {
        return array_values(array_filter($this->unit, static fn (array $l): bool => $l['ended_at'] === null));
    }

    public function unitLink(int $tenantId, int $linkId): ?array
    {
        return $this->unit[$linkId] ?? null;
    }

    public function addUnitLink(int $tenantId, int $unitId, int $partnerId, array $v, int $userId): int
    {
        $id = ++$this->seq;
        $this->unit[$id] = ['id' => $id, 'unit_id' => $unitId, 'partner_unit_id' => $partnerId, 'kind' => $v['kind'], 'direction' => $v['direction'], 'starts_on' => $v['starts_on'], 'ends_on' => $v['ends_on'], 'note' => $v['note'], 'grant_read' => $v['grant_read'], 'created_by' => $userId, 'ended_at' => null];

        return $id;
    }

    public function endUnitLink(int $tenantId, int $linkId, int $userId): void
    {
        $this->unit[$linkId]['ended_at'] = '2026-11-15 12:00:00';
    }
}

/** Un lecteur aux droits réglables : il ne voit que ce qu'on lui a donné. */
final class FakeLinkViewer implements JnetLinkViewer
{
    /** @var array<string, array<string, mixed>> */
    public array $visible = [];
    /** @var list<int> */
    public array $writeSpaces = [];
    /** @var list<int> */
    public array $chiefSpaces = [];
    /** @var list<int> */
    public array $moderateSpaces = [];
    public bool $orgManager = false;
    /** @var list<int> */
    public array $own = [];
    /** @var array<int, string> */
    public array $labels = [];
    /** @var list<array{0: int, 1: int}> */
    public array $related = [];
    public string $today = '2026-11-15';

    public function __construct(public int $user)
    {
    }

    public function tenantId(): int
    {
        return 1;
    }

    public function userId(): int
    {
        return $this->user;
    }

    public function today(): string
    {
        return $this->today;
    }

    public function see(string $type, string $id): ?array
    {
        return $this->visible[$type . ':' . $id] ?? null;
    }

    public function canWriteIn(int $spaceId): bool
    {
        return in_array($spaceId, $this->writeSpaces, true);
    }

    public function isChief(int $spaceId): bool
    {
        return in_array($spaceId, $this->chiefSpaces, true);
    }

    public function canModerate(int $spaceId): bool
    {
        return in_array($spaceId, $this->moderateSpaces, true) || $this->isChief($spaceId);
    }

    public function isOrgManager(): bool
    {
        return $this->orgManager;
    }

    public function inOwnUnits(): bool
    {
        return $this->own !== [];
    }

    public function unitLabel(int $unitId): ?string
    {
        return $this->labels[$unitId] ?? null;
    }

    public function related(int $a, int $b): bool
    {
        foreach ($this->related as [$x, $y]) {
            if (($x === $a && $y === $b) || ($x === $b && $y === $a)) {
                return true;
            }
        }

        return false;
    }

    public function ownUnitIds(): array
    {
        return $this->own;
    }

    public function search(string $q, array $types, int $spaceId): array
    {
        return [];
    }

    public function authors(array $userIds): array
    {
        return [7 => 'VIPER', 8 => 'ECHO'];
    }
}
