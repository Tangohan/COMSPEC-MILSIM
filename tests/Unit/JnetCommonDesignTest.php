<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use App\Services\Jnet\JnetAgenda;
use App\Services\Jnet\JnetCommonOverview;
use App\Services\Jnet\JnetCommonRules;
use App\Services\Jnet\JnetUnitBoard;
use App\Support\JnetSpaceTree;
use App\Support\JnetUi;
use PHPUnit\Framework\TestCase;

/**
 * JNET : espace commun, refonte « Claude Design » (bandeau, salons en onglets, vue d'ensemble, tiroir d'unité, agenda,
 * recherche rapide). Les vues sont rendues pour de vrai avec des données de test : une vue qui appelle une classe sans
 * « use » plante en production. Aucune donnée d'exemple du design n'est affichée, aucun surtitre n'est posé au-dessus d'un
 * titre, les photos d'équipe existent sur disque et sont dans les photos génériques.
 */
final class JnetCommonDesignTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const NOW = 1_800_000_000;

    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        unset($GLOBALS['__app_locale']);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** @param array<string, mixed> $cm */
    private function render(array $cm): string
    {
        $render = static function (string $__file, array $__cm): string {
            $cm = $__cm;
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render(base_path('views/jnet/home.php'), $cm);
    }

    // ——— Données de test ———

    private static function node(int $id, string $label, string $echelon, string $leader, int $leaderId, array $members, array $children = [], string $accent = ''): array
    {
        return [
            'unitId' => $id, 'label' => $label, 'echelon' => $echelon, 'leader' => $leader, 'commanderUserId' => $leaderId, 'accentColor' => $accent,
            'members' => array_map(static fn (int $u): array => ['user_id' => $u, 'label' => 'M' . $u], $members), 'children' => $children,
        ];
    }

    private static function tree(): JnetSpaceTree
    {
        return JnetSpaceTree::fromOrbat(['unitId' => 0, 'children' => [
            self::node(1, '1er Bataillon', 'bataillon', 'Cdt. Leroy', 11, [11, 12], [
                self::node(2, '2e Compagnie de combat', 'compagnie', 'Cpt. Martin', 21, [21, 22, 23], [
                    self::node(3, 'Section Alpha', 'section', 'Sgt. Viper', 23, [24, 25]),
                    self::node(4, 'Section Bravo', 'section', '', 0, [26]),
                ], '#e0a64a'),
            ], '#34d399'),
            self::node(5, 'Détachement SSE', 'escadron', 'Lt. Echo', 41, [42]),
        ]], 'Organisation d’essai');
    }

    /** @return array<string, mixed> */
    private static function exchange(int $id, string $kind, int $from, array $targets, int $age, bool $ack, string $title): array
    {
        return ['id' => $id, 'kind' => $kind, 'title' => $title, 'body' => 'Texte ' . $id, 'from_unit_id' => $from, 'targets' => $targets, 'requires_ack' => $ack, 'author_user_id' => 11, 'author_callsign' => 'YA1', 'created_at' => date('Y-m-d H:i:s', self::NOW - $age)];
    }

    /** @return list<array<string, mixed>> */
    private static function exchanges(): array
    {
        return [
            self::exchange(1, 'ordre', 0, [0], 7200, true, 'Ordre d’essai'),
            self::exchange(2, 'info', 1, [0], 86400, false, 'Information d’essai'),
            self::exchange(3, 'document', 0, [0], 3 * 86400, true, 'Document d’essai'),
            self::exchange(4, 'ordre', 1, [2], 4 * 86400, true, 'Ordre de la compagnie'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function rows(JnetSpaceTree $tree): array
    {
        $open = [['id' => 1, 'unit_id' => 2, 'title' => 'Tâche ancienne', 'status' => 'doing', 'updated_at' => date('Y-m-d H:i:s', self::NOW - 20 * 86400), 'assignee_label' => 'Sgt. Viper']];
        $strength = [1 => ['total' => 9, 'available' => 7], 2 => ['total' => 5, 'available' => 4], 3 => ['total' => 2, 'available' => 2]];
        $access = [];
        foreach ($tree->unitIds() as $id) {
            $access[$id] = $id !== 5;
        }
        $rows = JnetUnitBoard::build(
            $tree, self::exchanges(), [1 => [12, 21], 3 => [11, 12, 21, 22, 23]],
            [2 => ['todo' => 3, 'doing' => 2, 'done' => 5, 'late' => 1], 1 => ['todo' => 0, 'doing' => 1, 'done' => 2, 'late' => 0]],
            $open, $strength, $access, $access, self::NOW, static fn (string $k): string => JnetUi::echelonLabel($k)
        );
        foreach ($rows as $i => $r) {
            $rows[$i]['leaderId'] = $r['id'] === 2 ? 21 : 0;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function events(): array
    {
        $out = [];
        foreach ([[1, 'Formation d’essai', 'formation', 2, ''], [2, 'Opération d’essai', 'operation', 5, 'maybe']] as [$id, $title, $type, $days, $rsvp]) {
            $out[] = JnetAgenda::normalize(['id' => $id, 'title' => $title, 'event_type' => $type, 'starts_at' => date('Y-m-d', self::NOW + $days * 86400) . ' 21:00:00', 'ends_at' => null, 'location' => 'Lieu d’essai', 'rsvp_status' => $rsvp], self::NOW, ['yes' => 3, 'maybe' => 1, 'no' => 0]);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function decorated(JnetSpaceTree $tree): array
    {
        $out = [];
        foreach (self::exchanges() as $e) {
            $from = (array) $tree->node((int) $e['from_unit_id']);
            $out[] = [
                'id' => $e['id'], 'kind' => $e['kind'], 'kindLabel' => ['ordre' => 'Ordre', 'info' => 'Information', 'document' => 'Document'][$e['kind']], 'title' => $e['title'], 'body' => $e['body'],
                'link' => '', 'when' => 'il y a 2 h', 'stamp' => '141430Z NOV 26', 'createdAt' => $e['created_at'],
                'from' => ['id' => $e['from_unit_id'], 'label' => (string) ($from['label'] ?? ''), 'accent' => '', 'href' => '#'], 'to' => [['id' => 0, 'label' => 'Organisation', 'accent' => '', 'href' => '#']],
                'author' => 'YA1', 'authorPhoto' => null, 'authorInitials' => 'YA', 'authorHref' => '', 'requiresAck' => $e['requires_ack'], 'source' => '', 'sourceLabel' => '', 'image' => '', 'protectedImage' => false,
                'audience' => 12, 'readCount' => 5, 'readByMe' => false, 'direction' => 'down', 'pinId' => 0, 'relayHref' => $e['id'] === 1 ? 'https://exemple.test/jnet/u/2?vue=echanges&direct=1#ex-1' : '',
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function cm(string $salon, bool $locked = false, bool $empty = false): array
    {
        $tree = self::tree();
        $rows = $empty ? [] : self::rows($tree);
        $byId = [];
        foreach ($rows as $r) {
            $byId[$r['id']] = $r;
        }
        $events = $empty ? [] : self::events();
        $dec = $empty ? [] : self::decorated($tree);
        $pending = $empty ? [] : JnetCommonOverview::pendingForViewer($tree, self::exchanges(), [1 => [12, 21]], 22);
        $href = static fn (string $s): string => url(JnetCommonRules::path($s));
        $states = JnetUnitBoard::STATES;
        $view = match ($salon) {
            'apercu' => ['order' => JnetCommonOverview::orderOfTheDay($dec), 'pulse' => JnetCommonOverview::pulse($rows), 'pulseTotal' => count($rows), 'rowsById' => $byId, 'todo' => JnetCommonOverview::todo($pending, array_values(array_filter($rows, static fn (array $r): bool => $r['id'] === 2)), $events, self::NOW, $href), 'events' => $events, 'agendaEnabled' => true, 'states' => $states, 'workReady' => true, 'unitPins' => []],
            'annonces' => ['ready' => true, 'type' => '', 'scope' => '', 'scopeAll' => false, 'kinds' => ['ordre' => 'Ordre', 'info' => 'Information', 'document' => 'Document'], 'counts' => ['ordre' => 1, 'info' => 1, 'document' => 1], 'total' => 3, 'days' => JnetCommonRules::groupByDay($dec, 'createdAt', self::NOW), 'count' => count($dec), 'canPost' => false, 'postTargets' => []],
            'taches' => ['rows' => $rows, 'rowsById' => $byId, 'totals' => JnetUnitBoard::totals($rows), 'allCount' => count($rows), 'echelons' => ['compagnie' => 'Compagnie'], 'filters' => ['echelon' => '', 'etat' => '', 'retard' => false, 'q' => ''], 'sort' => 'unite', 'desc' => false, 'open' => 2, 'workReady' => true, 'exchangesReady' => true, 'staleDays' => 14, 'recentDays' => 7, 'windowDays' => 30, 'states' => $states, 'asTree' => true, 'unitPins' => []],
            'voie' => self::voie($tree, $empty),
            'agenda' => ['enabled' => true, 'type' => '', 'types' => JnetAgenda::TYPES, 'counts' => ['formation' => 1, 'operation' => 1], 'total' => count($events), 'groups' => JnetAgenda::group($events, '', self::NOW), 'rsvp' => JnetAgenda::RSVP],
            'renseignements', 'cibles' => ['locked' => true],
            default => ['pinsAll' => true],
        };
        $salons = [];
        $n = 0;
        foreach (JnetCommonRules::SALONS as $key => $meta) {
            $salons[] = $meta + ['key' => $key, 'num' => $meta['sse'] ? '' : str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'href' => url(JnetCommonRules::path($key)), 'badge' => $key === 'annonces' ? 2 : 0, 'badgeIsUnread' => $key === 'annonces', 'active' => $key === $salon, 'locked' => $meta['sse'] && $locked];
            if (!$meta['sse']) {
                $n++;
            }
        }
        $index = [];
        foreach ($tree->unitIds() as $id) {
            $index[] = ['id' => $id, 'label' => (string) ($tree->node($id)['label'] ?? ''), 'code' => '', 'href' => url(JnetCommonRules::path('taches', ['unite' => $id])) . '#jc-unit-card'];
        }

        return [
            'salon' => $salon, 'salonMeta' => JnetCommonRules::SALONS[$salon], 'salons' => $salons, 'sections' => JnetCommonRules::SECTIONS, 'orgLabel' => 'Organisation d’essai', 'orgBadge' => '',
            'unitsTotal' => count($tree->unitIds()), 'posture' => ['key' => 'green', 'label' => 'Posture verte'], 'hero' => JnetUi::forwardGroup(1),
            'kpis' => ['ack' => ['value' => count($pending), 'salon' => 'annonces'], 'late' => ['value' => 1, 'salon' => 'taches', 'known' => true], 'active' => ['value' => 2, 'of' => count($rows), 'salon' => 'taches'], 'next' => ['value' => $empty ? '' : 'J-5', 'sub' => '', 'salon' => 'agenda']],
            'intelOpen' => !$locked, 'view' => $view, 'pins' => [], 'pinsReady' => true, 'canPin' => true, 'pinsMax' => 25,
            'feed' => $empty ? [] : JnetCommonOverview::feed(self::exchanges(), $tree), 'unitIndex' => $index, 'now' => self::NOW,
        ];
    }

    /** @return array<string, mixed> */
    private static function voie(JnetSpaceTree $tree, bool $empty): array
    {
        $people = [11 => ['name' => 'Léon Leroy', 'callsign' => 'YA1', 'grade' => 'Commandant', 'function' => 'Chef de bataillon', 'href' => 'https://exemple.test/jnet/personnel/11']];
        $rows = $empty ? [] : JnetCommonRules::hierarchy($tree, $people, [1 => ['total' => 9, 'available' => 7]], '', static fn (string $k): string => JnetUi::echelonLabel($k));
        foreach ($rows as $i => $r) {
            $rows[$i]['openable'] = true;
            $rows[$i]['href'] = 'https://exemple.test/jnet/u/' . $r['id'];
        }
        $mine = [];
        foreach (array_reverse($tree->chain(3)) as $id) {
            if ($id === 0) {
                continue;
            }
            $n = (array) $tree->node($id);
            $mine[] = ['id' => $id, 'label' => (string) $n['label'], 'accent' => (string) $n['accent'], 'badge' => '', 'leader' => JnetCommonRules::person((int) $n['leaderId'], (string) $n['leader'], $people)];
        }

        return ['rows' => $rows, 'q' => '', 'branch' => false, 'hasBranch' => true, 'mine' => $empty ? [] : $mine, 'orgLabel' => 'Organisation d’essai', 'total' => 5, 'viewerId' => 23];
    }

    // ——— Rendu des salons ———

    public function testEverySalonRendersTheDesignFrame(): void
    {
        foreach (array_keys(JnetCommonRules::SALONS) as $salon) {
            $html = $this->render(self::cm($salon));
            self::assertSame(1, substr_count($html, '<h1'), $salon . ' : un seul titre de page');
            foreach (['class="jc-hero"', 'class="jc-kpis"', 'class="jc-sal"', 'data-jc-rail', 'class="jc-grid"', 'id="jc-aside"', 'data-jc-drawer', 'data-jc-pal', 'data-jc-toast', 'data-jc-t', 'data-jc-units'] as $needle) {
                self::assertStringContainsString($needle, $html, $salon . ' : ' . $needle);
            }
            self::assertSame(4, substr_count($html, '<a class="jc-kpi'), $salon . ' : quatre indicateurs');
            self::assertSame(1, substr_count($html, 'aria-current="page"'), $salon . ' : un seul salon actif');
        }
    }

    public function testHeroUsesAForwardGroupPhotoWithSrcsetAndNeutralAlt(): void
    {
        $html = $this->render(self::cm('apercu'));
        self::assertMatchesRegularExpression('#<img class="jc-hero__bg" src="[^"]*assets/images/jnet/forward-group-\d\.jpg[^"]*" srcset="[^"]*forward-group-\d-960\.jpg[^"]* 960w, [^"]*forward-group-\d\.jpg[^"]* \d+w" sizes="100vw" width="\d+" height="\d+" alt="" loading="lazy" decoding="async">#', $html);
        $css = $this->read('public/assets/css/jnet_commun.css');
        self::assertStringNotContainsString('url(', $css, 'aucune image de fond posée d’emblée en CSS : l’image du bandeau est une image à chargement différé');
    }

    public function testOverviewShowsTheOrderPulseTodoAndNextDeadlines(): void
    {
        $html = $this->render(self::cm('apercu'));
        foreach (['class="jc-odj"', 'Ordre d’essai', 'class="jc-pulse"', 'class="jc-todo"', 'class="jc-evl"', 'class="jc-rsvp"', 'data-jc-rsvp="1"', 'Accuser réception', 'Pouls des unités', 'À traiter', 'Prochaines échéances'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        // Un tiroir préparé par unité du pouls, sans requête par unité.
        self::assertSame(substr_count($html, 'class="jc-pt '), substr_count($html, '<template data-jc-unit-tpl="'));
        // Les données viennent du serveur : aucun salon n'embarque de bloc de données d'exemple.
        self::assertStringNotContainsString('const SEED', $html);
    }

    public function testOverviewEmptyStatesAreHonest(): void
    {
        $html = $this->render(self::cm('apercu', false, true));
        foreach (['Aucun ordre en cours.', 'Rien en attente.', 'Rien de prévu.', 'L’organigramme n’est pas encore renseigné', 'Aucun échange récent.'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertStringNotContainsString('class="jc-pt ', $html);
        foreach (['annonces', 'taches', 'voie', 'agenda'] as $salon) {
            $this->render(self::cm($salon, false, true));
        }
        self::assertTrue(true);
    }

    public function testAnnouncementsCardsKeepAcknowledgementPinAndRelay(): void
    {
        $html = $this->render(self::cm('annonces'));
        foreach (['class="jc-an jc-an--ordre"', 'jc-pill--k-ordre', 'data-jc-ack', 'jnet/echanges/1/lu', 'Relayer', 'ont accusé réception', 'Pour information', 'Mon unité', 'Toutes les unités', 'Organisation'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertStringContainsString('jnet/commun/epingles', $html);
        self::assertSame(1, preg_match('#<h2 id="jc-title" class="jc-vh__t">Ce qui descend\.</h2>#', $html));
    }

    public function testUnitBoardIsATreeWithADrawerPerUnit(): void
    {
        $html = $this->render(self::cm('taches'));
        foreach (['data-jc-tree-table', 'data-jc-tg="1"', 'class="jc-tbar"', 'data-jc-expand-all', 'id="jc-unit-card"', 'data-jc-unit-card="2"', 'aria-sort="ascending"'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertSame(5, substr_count($html, '<template data-jc-unit-tpl="'));
        // Le détail contient chiffres, commandement, sous-unités, ordres et tâches les plus urgents, actions.
        foreach (['class="jc-dg"', 'Commandement', 'Sous-unités · 2', 'Ordres les plus urgents', 'Tâches les plus urgentes', 'Écrire au chef', 'Ouvrir l’espace de l’unité'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
    }

    public function testHierarchyShowsMyLineAndBranchSwitch(): void
    {
        $html = $this->render(self::cm('voie'));
        foreach (['class="jc-chain"', 'Ma voie', 'Mon unité', 'Chef direct', 'Tout l’arbre', 'Ma branche', 'data-jc-copy="YA1"', 'jnet/courrier/nouveau?membre=11'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        // Le titre « Ma voie » vient avant la chaîne, jamais un libellé posé au-dessus d'un titre.
        self::assertLessThan(strpos($html, 'class="jc-chain"'), strpos($html, '>Ma voie<'));
    }

    public function testAgendaGroupsEventsByDayWithTheMemberAnswer(): void
    {
        $html = $this->render(self::cm('agenda'));
        foreach (['class="jc-evc ', 'Formation d’essai', 'Opération d’essai', 'class="jc-agday', 'data-jc-rsvp="2" data-status="maybe"', 'aria-pressed="true"', 'api/events/2/rsvp', 'evenements/rsvp'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertSame(2 * 3, substr_count($html, 'data-jc-rsvp="'), 'trois réponses par événement');
    }

    public function testIntelligenceChannelsStayLockedWithoutTheDoor(): void
    {
        foreach (['renseignements', 'cibles'] as $salon) {
            $html = $this->render(self::cm($salon, true));
            self::assertStringContainsString('class="jc-locked"', $html, $salon);
            self::assertStringContainsString('Salon réservé', $html);
            self::assertStringContainsString('is-locked', $html);
            self::assertStringNotContainsString('class="jc-note-item', $html);
            self::assertStringNotContainsString('class="jc-target', $html);
        }
    }

    // ——— Règles pures ———

    public function testOverviewRulesComputePendingPulseAndOrderOfTheDay(): void
    {
        $tree = self::tree();
        $readers = [1 => [12, 21], 3 => [22]];
        // Le membre 22 (compagnie) doit accuser l'ordre 1 (organisation) et l'ordre 4 (compagnie), pas le document 3 déjà lu.
        $pending = JnetCommonOverview::pendingForViewer($tree, self::exchanges(), $readers, 22);
        self::assertSame([1, 4], array_map(static fn (array $e): int => (int) $e['id'], $pending));
        // Celui qui a publié n'a rien à accuser.
        self::assertSame([], JnetCommonOverview::pendingForViewer($tree, self::exchanges(), $readers, 11));
        // Le membre du détachement SSE n'est pas destinataire de l'ordre de la compagnie.
        self::assertSame([1, 3], array_map(static fn (array $e): int => (int) $e['id'], JnetCommonOverview::pendingForViewer($tree, self::exchanges(), [], 42)));

        $rows = self::rows($tree);
        $pulse = JnetCommonOverview::pulse($rows);
        self::assertSame('late', $pulse[0]['state'], 'les retards d’abord');
        self::assertCount(2, JnetCommonOverview::pulse($rows, 2));
        self::assertSame(count($rows), JnetCommonOverview::activeUnits($rows, self::NOW, 365) + count(array_filter($rows, static fn (array $r): bool => (int) $r['lastTs'] === 0)));

        $dec = self::decorated($tree);
        $order = JnetCommonOverview::orderOfTheDay($dec);
        self::assertNotNull($order);
        self::assertSame(1, $order['id'], 'le dernier ordre à accuser');
        self::assertNull(JnetCommonOverview::orderOfTheDay([]));
        foreach ($dec as $i => $d) {
            $dec[$i]['readByMe'] = true;
        }
        self::assertSame(1, JnetCommonOverview::orderOfTheDay($dec)['id'], 'tout accusé : le dernier ordre de l’organisation');
    }

    public function testTodoListIsCappedAndPointsToTheRightPlace(): void
    {
        $tree = self::tree();
        $rows = self::rows($tree);
        $late = array_values(array_filter($rows, static fn (array $r): bool => $r['id'] === 2));
        $href = static fn (string $s): string => 'https://exemple.test/' . JnetCommonRules::path($s);
        $todo = JnetCommonOverview::todo(self::exchanges(), $late, self::events(), self::NOW, $href);
        self::assertLessThanOrEqual(JnetCommonOverview::TODO_LIMIT, count($todo));
        self::assertSame('bad', $todo[0]['tone']);
        self::assertSame('annonces', $todo[0]['salon']);
        self::assertStringContainsString('#ex-1', $todo[0]['href']);
        $unit = array_values(array_filter($todo, static fn (array $t): bool => $t['unit'] === 2));
        self::assertCount(1, $unit);
        self::assertStringContainsString('unite=2#jc-unit-card', $unit[0]['href']);
        self::assertSame('1 tâche en retard', $unit[0]['title']);
        $answers = array_values(array_filter($todo, static fn (array $t): bool => $t['salon'] === 'agenda'));
        self::assertCount(1, $answers, 'seul l’événement sans réponse demande une action');
        self::assertSame([], JnetCommonOverview::todo([], [], [], self::NOW, $href));
    }

    public function testFeedListsTheLatestExchangesWithTheirUnit(): void
    {
        $feed = JnetCommonOverview::feed(self::exchanges(), self::tree(), 2);
        self::assertCount(2, $feed);
        self::assertSame('YA1', $feed[0]['author']);
        self::assertTrue($feed[0]['orgLevel']);
        self::assertSame('1er Bataillon', $feed[1]['label']);
        self::assertSame([], JnetCommonOverview::feed([], self::tree()));
    }

    public function testAgendaRulesNormalizeGroupAndCount(): void
    {
        $e = self::events();
        self::assertSame('formation', $e[0]['typeKey']);
        self::assertSame('maybe', $e[1]['rsvp']);
        self::assertSame('', $e[0]['rsvp']);
        self::assertSame('evenement', JnetAgenda::typeKey('inconnu'));
        $groups = JnetAgenda::group($e, '', self::NOW);
        self::assertCount(2, $groups);
        self::assertCount(1, JnetAgenda::group($e, 'operation', self::NOW));
        self::assertSame([], JnetAgenda::group($e, 'autre', self::NOW));
        self::assertSame(5, JnetAgenda::daysUntil($e[1]['startTs'], self::NOW));
        self::assertSame('J-5', JnetAgenda::countdown(5, false));
        self::assertSame('Aujourd’hui', JnetAgenda::countdown(0, false));
        self::assertSame('En cours', JnetAgenda::countdown(0, true));
        $next = JnetCommonOverview::nextOperation($e, self::NOW);
        self::assertNotNull($next);
        self::assertSame(2, $next['id']);
        self::assertNull(JnetCommonOverview::nextOperation([$e[0]], self::NOW), 'sans opération : aucun compte à rebours inventé');
    }

    public function testBranchAndLineHelpers(): void
    {
        $tree = self::tree();
        self::assertSame(3, JnetCommonRules::lowestUnit($tree, [1, 3, 5]));
        self::assertSame(0, JnetCommonRules::lowestUnit($tree, []));
        $ids = JnetCommonRules::branchIds($tree, [2]);
        self::assertSame([1, 2, 3, 4], array_keys($ids));
        self::assertSame([], JnetCommonRules::branchIds($tree, [999]));
    }

    // ——— Fond, images, logos ———

    public function testForwardGroupPhotosAreOnDiskSizedForTheWebAndInGenericPhotos(): void
    {
        self::assertCount(3, JnetUi::FORWARD_GROUP_PHOTOS);
        foreach (JnetUi::FORWARD_GROUP_PHOTOS as [$large, $small, $w, $h]) {
            foreach ([$large, $small] as $path) {
                self::assertTrue(is_file(self::ROOT . 'public/' . $path), $path);
                self::assertLessThanOrEqual(450_000, (int) filesize(self::ROOT . 'public/' . $path), $path . ' : poids');
            }
            $big = getimagesize(self::ROOT . 'public/' . $large);
            $mobile = getimagesize(self::ROOT . 'public/' . $small);
            self::assertIsArray($big);
            self::assertIsArray($mobile);
            self::assertSame('image/jpeg', $big['mime']);
            self::assertLessThanOrEqual(1920, $big[0]);
            self::assertSame($w, $big[0], 'la largeur déclarée est celle du fichier');
            self::assertSame($h, $big[1]);
            self::assertSame(960, $mobile[0]);
            self::assertContains($large, JnetUi::GENERIC_PHOTOS, 'photo ajoutée aux fonds génériques d’unité');
        }
        // Rien n'a été retiré des photos génériques d'avant.
        foreach (['assets/images/fog-team.jpg', 'assets/images/night-team.jpg', 'assets/images/hero-explosion.jpg', 'assets/images/login-accueil-nvg-forest.jpg'] as $old) {
            self::assertContains($old, JnetUi::GENERIC_PHOTOS, $old);
        }
        self::assertSame(JnetUi::genericPhoto(9), JnetUi::genericPhoto(9));
        self::assertSame(JnetUi::forwardGroup(4), JnetUi::forwardGroup(4));
        self::assertSame(JnetUi::forwardGroup(1), JnetUi::forwardGroup(-2), 'toujours une des trois, même avec une graine négative');
    }

    public function testEagleLogosAreShippedButNotWiredAsASeasonalLogo(): void
    {
        foreach (['public/assets/images/athena-eagle-mark-white.png', 'public/assets/images/athena-eagle-xmas.png'] as $logo) {
            $info = getimagesize(self::ROOT . $logo);
            self::assertIsArray($info, $logo);
            self::assertSame('image/png', $info['mime'], $logo);
        }
        // Type de couleur 6 de l'en-tête PNG : couleurs avec canal alpha (fond blanc rendu transparent).
        $header = (string) file_get_contents(self::ROOT . 'public/assets/images/athena-eagle-xmas.png', false, null, 0, 33);
        self::assertSame(6, ord($header[25]), 'le logo de Noël a un canal de transparence');
        // Suite possible, pas branchée : aucune vue, feuille ou script ne les référence.
        foreach (['app', 'views', 'public/assets/css', 'public/assets/js'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'css', 'js'], true)) {
                    continue;
                }
                $src = (string) file_get_contents($file->getPathname());
                self::assertStringNotContainsString('athena-eagle-xmas', $src, $file->getFilename());
                self::assertStringNotContainsString('athena-eagle-mark-white', $src, $file->getFilename());
            }
        }
    }

    // ——— Conformité aux décisions ———

    public function testNoSampleDataFromTheDesignIsEverShown(): void
    {
        $files = array_merge(
            ['views/jnet/home.php', 'public/assets/js/jnet_commun.js', 'public/assets/css/jnet_commun.css'],
            array_map(static fn (string $f): string => 'views/jnet/commun/' . basename($f), glob(self::ROOT . 'views/jnet/commun/*.php') ?: []),
            ['app/Services/Jnet/JnetCommonOverview.php', 'app/Services/Jnet/JnetAgenda.php', 'app/Services/Jnet/JnetCommonSpaceService.php']
        );
        $sample = ['Brume d', 'OPORD-2026', 'Jake Gylenhall', 'Liam K', 'Damian Stones', 'Kill House', 'SOP Radio', 'Recrutement CNOS', '160th SOAR', '24th Special', 'Special Operations Action Regiments', 'const SEED', 'const U=[', "localStorage.getItem('ec.", 'Pyrgos'];
        foreach ($files as $file) {
            $src = $this->read($file);
            foreach ($sample as $needle) {
                self::assertStringNotContainsString($needle, $src, $file . ' : donnée d’exemple « ' . $needle . ' »');
            }
        }
    }

    public function testNoEyebrowAboveATitleAndNoEmoji(): void
    {
        foreach (array_merge(['views/jnet/home.php'], array_map(static fn (string $f): string => 'views/jnet/commun/' . basename($f), glob(self::ROOT . 'views/jnet/commun/*.php') ?: [])) as $file) {
            $src = $this->read($file);
            foreach (['class="kk"', 'kicker', 'eyebrow', 'class="num"', 'surtitre'] as $needle) {
                self::assertStringNotContainsString($needle, $src, $file . ' : ' . $needle);
            }
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src), $file . ' : pas d’emoji');
        }
        $html = $this->render(self::cm('apercu'));
        // Le titre est le premier élément de son bloc : rien ne le précède.
        self::assertSame(1, preg_match('#<div class="jc-hero__id">\s*<h1 id="jc-h1"#', $html));
        self::assertSame(1, preg_match('#<article class="jc-odj"[^>]*>\s*<h3 id="jc-odj-t"#', $html));
        $html = $this->render(self::cm('taches'));
        self::assertSame(1, preg_match('#<header class="jc-vh">\s*<h2 id="jc-title"#', $html));
        $html = $this->render(self::cm('voie'));
        self::assertSame(1, preg_match('#<section class="jc-mine"[^>]*>\s*<h3 id="jc-mine-t"#', $html));
        self::assertSame(0, preg_match('/\.kk\b|\.num\b|text-transform: uppercase;\s*color: var\(--u\);\s*\}\s*\.jc-eyebrow/', $this->read('public/assets/css/jnet_commun.css')));
    }

    public function testNoExternalLibraryAndNoRequestPerElement(): void
    {
        $js = $this->read('public/assets/js/jnet_commun.js');
        $css = $this->read('public/assets/css/jnet_commun.css');
        self::assertSame(0, preg_match('#https?://#', $js), 'aucune adresse externe dans le script');
        self::assertSame(0, preg_match('#https?://|@import#', $css), 'aucune adresse externe ni import dans la feuille');
        self::assertStringNotContainsString('jquery', strtolower($js));
        // Le détail d'une unité vient de gabarits rendus avec la page : le tiroir ne lit rien d'autre.
        self::assertStringContainsString('data-jc-unit-tpl', $js);
        self::assertSame(0, preg_match('/fetch\([^)]*unite/i', $js));
        // Page, feuille et script sont séparés et versionnés par asset_url (routeur : jnet_commun.css / jnet_commun.js).
        self::assertStringContainsString("['jnet_commun.css'], ['jnet_commun.js']", $this->read('app/Controllers/Web/JnetPortalController.php'));
        self::assertStringContainsString("asset_url('assets/js/'", $this->read('views/jnet/_page.php'));
        // Aucun texte français écrit dans le script : les libellés viennent de la vue (donc de sa traduction).
        self::assertSame(0, preg_match('/[àâçéèêëîïôùûü]/u', preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $js) ?? ''), 'le script ne contient aucun texte français');
    }

    public function testAccessibilityAndResponsiveContracts(): void
    {
        $css = $this->read('public/assets/css/jnet_commun.css');
        foreach (['@media (max-width: 1279px)', '@media (max-width: 899px)', '@media (max-width: 540px)', '@media (prefers-reduced-motion: reduce)', ':focus-visible', 'overflow-x: auto'] as $needle) {
            self::assertStringContainsString($needle, $css, $needle);
        }
        self::assertStringNotContainsString('1320', $css);
        self::assertStringNotContainsString('margin: 0 auto', $css, 'jamais de colonne centrée');
        $js = $this->read('public/assets/js/jnet_commun.js');
        foreach (["key === 'Escape'", "e.key === '/'", 'altKey', 'ArrowRight', "e.key === 'Tab'", 'focus('] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        $html = $this->render(self::cm('apercu'));
        foreach (['role="dialog" aria-modal="true"', 'role="status" aria-live="polite"', 'aria-keyshortcuts="/"', 'aria-label="Salons"', 'role="combobox"', 'role="listbox"'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
    }

    public function testEnglishTextsOfTheDesignAreInTheDictionary(): void
    {
        $en = require self::ROOT . 'lang/en/site_auto.php';
        $texts = ['Vue d’ensemble', 'Agenda', 'Pouls des unités', 'À traiter', 'Prochaines échéances', 'Fil d’activité', 'a diffusé', 'Ma voie', 'Ma branche', 'Tout l’arbre', 'Mon unité', 'Relayer', 'Écrire', 'Tâches ouvertes', 'Effectif disponible', 'Salon réservé', 'Rien en attente.', 'Rien de prévu.', 'Accuser réception : %1', '%1 tâche en retard', '%1 tâches en retard', 'Répondre : %1', 'Voir les %1 unités', 'Sous-unités · %1', 'L’ordre du jour', 'ont accusé réception', 'sous-unités', 'Indicatif copié'];
        foreach ([JnetCommonRules::SALONS, ] as $salons) {
            foreach ($salons as $meta) {
                $texts[] = $meta['label'];
                $texts[] = $meta['desc'];
                if ($meta['tag'] !== '') {
                    $texts[] = $meta['tag'];
                }
            }
        }
        foreach ([JnetAgenda::TYPES, JnetAgenda::RSVP, JnetUnitBoard::STATES, JnetAgenda::WEEKDAYS_SHORT, JnetAgenda::WEEKDAYS_LONG] as $list) {
            foreach ($list as $label) {
                $texts[] = $label;
            }
        }
        foreach ($texts as $fr) {
            self::assertArrayHasKey($fr, $en, $fr);
            self::assertNotSame('', $en[$fr]);
        }
    }
}
