<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Jnet\JnetCommonRules;
use App\Services\Jnet\JnetLinkViewer;
use App\Services\Jnet\JnetPinRules;
use App\Services\Jnet\JnetPinService;
use App\Services\Jnet\JnetPinStore;
use App\Services\Jnet\JnetUnitBoard;
use App\Support\JnetSpaceTree;
use PHPUnit\Framework\TestCase;

/**
 * JNET : espace commun « centre de commandement » : salons, tableau des unités (compteurs sur jeux de données de test),
 * voie hiérarchique, épinglés (droits, visibilité revérifiée, plafond, ordre), porte du renseignement, CSRF, migration,
 * vues qui importent leurs classes, textes anglais.
 */
final class JnetCommonSpaceTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** @param list<int> $members */
    private static function node(int $id, string $label, string $echelon, string $leader, int $leaderId, array $members, array $children = []): array
    {
        return [
            'unitId' => $id, 'label' => $label, 'echelon' => $echelon, 'leader' => $leader, 'commanderUserId' => $leaderId,
            'members' => array_map(static fn (int $u): array => ['user_id' => $u, 'label' => 'M' . $u], $members), 'children' => $children,
        ];
    }

    private static function tree(): JnetSpaceTree
    {
        return JnetSpaceTree::fromOrbat(['unitId' => 0, 'children' => [
            self::node(1, 'Bataillon', 'bataillon', 'Cdt. Leroy', 11, [11, 12], [
                self::node(2, 'Compagnie', 'compagnie', 'Cpt. Martin', 21, [21, 22, 23], [
                    self::node(3, 'Section A', 'section', 'Sgt. Viper', 23, [24]),
                ]),
            ]),
            self::node(5, 'Escadron', 'escadron', '', 0, [31]),
        ]], 'Organisation');
    }

    /** @return array<string, mixed> */
    private static function exchange(int $id, string $kind, int $from, array $targets, int $age, bool $ack): array
    {
        return ['id' => $id, 'kind' => $kind, 'title' => 'Échange ' . $id, 'from_unit_id' => $from, 'targets' => $targets, 'requires_ack' => $ack, 'author_user_id' => 11, 'created_at' => date('Y-m-d H:i:s', 1_800_000_000 - $age)];
    }

    // ——— Salons ———

    public function testSixChannelsAreDeclaredWithTheirSectionsAndTheIntelligenceDoor(): void
    {
        self::assertSame(['annonces', 'taches', 'voie', 'renseignements', 'cibles', 'epingles'], array_keys(JnetCommonRules::SALONS));
        foreach (JnetCommonRules::SALONS as $key => $meta) {
            self::assertArrayHasKey($meta['section'], JnetCommonRules::SECTIONS, $key);
            self::assertNotSame('', $meta['label']);
            self::assertNotSame('', $meta['desc']);
        }
        self::assertTrue(JnetCommonRules::SALONS['renseignements']['sse']);
        self::assertTrue(JnetCommonRules::SALONS['cibles']['sse']);
        self::assertFalse(JnetCommonRules::SALONS['annonces']['sse']);
        self::assertSame('annonces', JnetCommonRules::salon(''));
        self::assertSame('annonces', JnetCommonRules::salon('../../etc'));
        self::assertSame('voie', JnetCommonRules::salon(' VOIE '));
    }

    public function testChannelPathUsesAWhitelistOfParameters(): void
    {
        self::assertSame('jnet?commun=1&salon=taches&etat=late', JnetCommonRules::path('taches', ['etat' => 'late', 'evil' => 'x', 'q' => '']));
        self::assertSame('jnet?commun=1&salon=annonces', JnetCommonRules::path('nimporte'));
        self::assertStringContainsString('q=a%20b', JnetCommonRules::path('voie', ['q' => 'a b']));
    }

    public function testFeedsAreGroupedByDayLikeADiscussion(): void
    {
        $now = 1_800_000_000;
        $groups = JnetCommonRules::groupByDay([
            ['at' => date('Y-m-d H:i:s', $now - 60)],
            ['at' => date('Y-m-d H:i:s', $now - 120)],
            ['at' => date('Y-m-d H:i:s', $now - 86400)],
            ['at' => ''],
        ], 'at', $now);
        self::assertCount(3, $groups);
        self::assertSame('Aujourd’hui', $groups[0]['label']);
        self::assertCount(2, $groups[0]['items']);
        self::assertSame('Hier', $groups[1]['label']);
        self::assertSame('Sans date', $groups[2]['label']);
        self::assertSame('Ordres des 30 jours', JnetCommonRules::fmt('Ordres des %1 jours', 30));
    }

    // ——— Tableau des unités ———

    /** @return list<array<string, mixed>> */
    private static function board(): array
    {
        $now = 1_800_000_000;
        $exchanges = [
            self::exchange(1, 'ordre', 0, [0], 3600, true),            // toute l'organisation, accusé demandé
            self::exchange(2, 'ordre', 1, [2], 2 * 86400, true),        // du bataillon à la compagnie
            self::exchange(3, 'ordre', 0, [0], 40 * 86400, true),       // trop ancien : hors fenêtre de 30 jours
            self::exchange(4, 'info', 0, [0], 100, false),              // pas un ordre
            self::exchange(5, 'ordre', 2, [3], 20 * 86400, false),      // de la compagnie à la section, sans accusé
        ];
        $readers = [1 => [11, 12, 21, 22, 23, 24, 31], 2 => [21], 5 => []];
        $counts = [2 => ['todo' => 3, 'doing' => 1, 'done' => 4, 'late' => 2], 3 => ['todo' => 1, 'doing' => 0, 'done' => 0, 'late' => 0], 5 => ['todo' => 9, 'doing' => 9, 'done' => 9, 'late' => 9]];
        $open = [
            ['id' => 1, 'unit_id' => 2, 'title' => 'Récente', 'status' => 'doing', 'updated_at' => date('Y-m-d H:i:s', $now - 86400), 'assignee_label' => ''],
            ['id' => 2, 'unit_id' => 2, 'title' => 'Ancienne', 'status' => 'todo', 'updated_at' => date('Y-m-d H:i:s', $now - 30 * 86400), 'assignee_label' => ''],
            ['id' => 3, 'unit_id' => 5, 'title' => 'Invisible', 'status' => 'todo', 'updated_at' => date('Y-m-d H:i:s', $now - 30 * 86400), 'assignee_label' => ''],
        ];
        $strength = [1 => ['total' => 8, 'available' => 7], 2 => ['total' => 4, 'available' => 3], 3 => ['total' => 1, 'available' => 1], 5 => ['total' => 1, 'available' => 1]];

        return JnetUnitBoard::build(
            self::tree(), $exchanges, $readers, $counts, $open, $strength,
            [1 => true, 2 => true, 3 => true, 5 => false], [1 => true, 2 => true, 3 => true, 5 => false], $now,
            static fn (string $k): string => strtoupper($k)
        );
    }

    /** @param list<array<string, mixed>> $rows */
    private static function row(array $rows, int $id): array
    {
        foreach ($rows as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        self::fail('unité absente ' . $id);
    }

    public function testBoardCountsOrdersAcknowledgementsAndTasksFromGroupedReads(): void
    {
        $rows = self::board();
        self::assertSame([1, 2, 3, 5], array_map(static fn (array $r): int => $r['id'], $rows), 'ordre de l’ORBAT');
        $company = self::row($rows, 2);
        // Ordres 1 (organisation) et 2 (bataillon) : l'ordre 3 est trop ancien, l'ordre 5 vient d'elle, l'info n'est pas un ordre.
        self::assertSame(2, $company['orders']['received']);
        self::assertSame(2, $company['orders']['recent']);
        // L'ordre 1 est acquitté par tous ; l'ordre 2 attend encore 22, 23 et 24.
        self::assertSame(1, $company['orders']['pending']);
        self::assertSame(2, $company['orders']['top'][0]['id'], 'l’ordre sans accusé complet passe en premier');
        self::assertSame(3, $company['orders']['top'][0]['missing']);
        $section = self::row($rows, 3);
        self::assertSame(3, $section['orders']['received'], 'ordres 1, 2 et 5 descendent jusqu’à la section');
        self::assertSame(1, $section['orders']['pending'], 'le membre 24 n’a pas lu l’ordre 2');
        self::assertSame(['todo' => 3, 'doing' => 1, 'done' => 4, 'late' => 2], array_intersect_key($company['tasks'], array_flip(['todo', 'doing', 'done', 'late'])));
        self::assertSame('Ancienne', $company['tasks']['top'][0]['title'], 'les tâches en retard d’abord');
        self::assertTrue($company['tasks']['top'][0]['late']);
        self::assertSame('late', $company['state']);
        self::assertGreaterThan(0, $company['lastTs']);
    }

    public function testBoardHidesTasksOfUnitsTheViewerCannotReach(): void
    {
        $esc = self::row(self::board(), 5);
        self::assertFalse($esc['tasksVisible']);
        self::assertSame(['todo' => 0, 'doing' => 0, 'done' => 0, 'late' => 0], array_intersect_key($esc['tasks'], array_flip(['todo', 'doing', 'done', 'late'])), 'aucun compteur ne fuit');
        self::assertSame([], $esc['tasks']['top']);
        self::assertFalse($esc['openable']);
    }

    public function testBoardFiltersSortsAndTotals(): void
    {
        $rows = self::board();
        self::assertCount(1, JnetUnitBoard::apply($rows, ['echelon' => 'escadron']));
        self::assertSame([2], array_map(static fn (array $r): int => $r['id'], JnetUnitBoard::apply($rows, ['retard' => true])));
        self::assertSame([2], array_map(static fn (array $r): int => $r['id'], JnetUnitBoard::apply($rows, ['q' => 'MARTIN'])), 'recherche par chef, sans accents ni casse');
        self::assertSame([2], array_map(static fn (array $r): int => $r['id'], JnetUnitBoard::apply($rows, ['etat' => 'late'])));
        self::assertSame(2, JnetUnitBoard::apply($rows, [], 'retard', true)[0]['id'], 'tri par retards, plus grand d’abord');
        self::assertSame([5, 3, 2, 1], array_map(static fn (array $r): int => $r['id'], JnetUnitBoard::apply($rows, [], 'unite', true)));
        $totals = JnetUnitBoard::totals($rows);
        self::assertSame(4, $totals['units']);
        self::assertSame(4, $totals['todo'], 'à faire : 3 (compagnie) + 1 (section)');
        self::assertSame(2, $totals['late']);
        self::assertSame(4, $totals['done']);
        self::assertSame(1, JnetUnitBoard::totals(JnetUnitBoard::apply($rows, ['echelon' => 'escadron']))['units']);
    }

    // ——— Voie hiérarchique ———

    public function testHierarchyKeepsAncestorsOfAMatchAndNeverLinksAMaskedIdentity(): void
    {
        $people = [21 => ['name' => 'Capitaine Martin', 'callsign' => 'MARTIN 6', 'grade' => 'Capitaine', 'function' => 'Chef', 'href' => '/jnet/personnel/21']];
        $all = JnetCommonRules::hierarchy(self::tree(), $people, [], '', static fn (string $k): string => $k);
        self::assertSame([1, 2, 3, 5], array_map(static fn (array $r): int => $r['id'], $all));
        self::assertSame(2, $all[2]['depth']);
        self::assertSame('MARTIN 6', $all[1]['leader']['callsign']);
        self::assertSame('/jnet/personnel/21', $all[1]['leader']['href']);
        self::assertSame(0, $all[3]['leader']['id'], 'identité absente de l’annuaire : aucun lien');
        self::assertSame('', $all[3]['leader']['href']);
        self::assertSame(['Organisation', 'Bataillon', 'Compagnie'], $all[1]['chain']);
        $found = JnetCommonRules::hierarchy(self::tree(), $people, [], 'martin 6', static fn (string $k): string => $k);
        self::assertSame([1, 2], array_map(static fn (array $r): int => $r['id'], $found), 'la compagnie et son bataillon');
        self::assertTrue($found[1]['match']);
    }

    // ——— Épinglés ———

    private function pins(array $visible, bool $manage, ?JnetMemoryPinStore $store = null, bool $intel = true): array
    {
        $store ??= new JnetMemoryPinStore();
        $viewer = new JnetPinTestViewer(7, $visible);
        $service = new JnetPinService(
            $store,
            $viewer,
            $manage,
            static fn (string $id): ?array => $intel && $id === 'ev-3' ? ['title' => 'Contact radio', 'sub' => 'Événement', 'href' => '/atak/sse'] : null,
            static fn (array $ids): array => array_combine($ids, array_map(static fn (int $i): string => 'U' . $i, $ids)) ?: []
        );

        return [$service, $store];
    }

    private static function seen(string $type, string $id, string $title): array
    {
        return [$type . ':' . $id => ['type' => $type, 'id' => $id, 'title' => $title, 'sub' => 'x', 'href' => '/x/' . $id, 'group' => 'autres']];
    }

    public function testOnlyThoseWhoPublishForTheOrganisationManagePins(): void
    {
        [$reader, $store] = $this->pins(self::seen('echange', '9', 'Ordre'), false);
        self::assertFalse($reader->canManage());
        self::assertNotNull($reader->pin('echange', '9'));
        self::assertNotNull($reader->unpin(1));
        self::assertNotNull($reader->move(1, -1));
        self::assertNotNull($reader->reorder([1]));
        self::assertSame([], $store->all(1), 'aucune écriture');
        [$admin] = $this->pins(self::seen('echange', '9', 'Ordre'), true, $store);
        self::assertNull($admin->pin('echange', '9'));
        self::assertCount(1, $store->all(1));
        self::assertSame(JnetPinRules::SCOPE, 'commun');
    }

    public function testAPinnedElementTheReaderCannotSeeIsMaskedWithoutTitle(): void
    {
        $store = new JnetMemoryPinStore();
        $store->add(1, 'echange', '9', 3);
        $store->add(1, 'cible', 'di-2', 3);
        $store->add(1, 'unite', '4', 3);
        [$reader] = $this->pins(self::seen('echange', '9', 'Ordre visible'), false, $store);
        $list = $reader->pins();
        self::assertTrue($list[0]['visible']);
        self::assertSame('Ordre visible', $list[0]['title']);
        foreach ([1, 2] as $i) {
            self::assertFalse($list[$i]['visible']);
            self::assertSame('', $list[$i]['title']);
            self::assertSame('', $list[$i]['typeLabel'], 'rien ne trahit la nature de l’élément masqué');
            self::assertSame('', $list[$i]['href']);
        }
        self::assertSame('U3', $list[0]['by']);
    }

    public function testIntelligencePinsAreBehindTheIntelligenceDoor(): void
    {
        $store = new JnetMemoryPinStore();
        [$open] = $this->pins([], true, $store, true);
        self::assertNull($open->pin('renseignement', 'ev-3'));
        self::assertSame('Contact radio', $open->pins()[0]['title']);
        [$closed] = $this->pins([], true, $store, false);
        self::assertFalse($closed->pins()[0]['visible'], 'porte fermée : élément masqué');
        self::assertSame('Élément introuvable.', $closed->pin('renseignement', 'ev-3'), 'même message que pour un élément inexistant');
        self::assertSame('Élément introuvable.', $closed->pin('renseignement', 'ev-999'));
    }

    public function testPinsAreCappedValidatedAndNotDuplicated(): void
    {
        $visible = [];
        for ($i = 1; $i <= 30; $i++) {
            $visible += self::seen('unite', (string) $i, 'Unité ' . $i);
        }
        [$svc, $store] = $this->pins($visible, true);
        for ($i = 1; $i <= JnetPinRules::MAX; $i++) {
            self::assertNull($svc->pin('unite', (string) $i), (string) $i);
        }
        self::assertStringContainsString('limite de 25', (string) $svc->pin('unite', '26'));
        self::assertCount(25, $store->all(1));
        self::assertSame('Cet élément est déjà épinglé.', $svc->pin('unite', '3'));
        self::assertSame('Élément introuvable.', $svc->pin('unite', '0;DROP'));
        self::assertSame('Élément introuvable.', $svc->pin('inconnu', '1'));
        self::assertFalse(JnetPinRules::validId('cible', '12'));
        self::assertTrue(JnetPinRules::validId('cible', 'wl-12'));
        self::assertTrue(JnetPinRules::validId('renseignement', 'fn-8'));
        self::assertNull(JnetPinRules::parseRef('page:abc'));
    }

    public function testPinsCanBeMovedAndReordered(): void
    {
        $visible = self::seen('unite', '1', 'A') + self::seen('unite', '2', 'B') + self::seen('unite', '3', 'C');
        [$svc, $store] = $this->pins($visible, true);
        foreach (['1', '2', '3'] as $id) {
            $svc->pin('unite', $id);
        }
        $order = static fn (): array => array_map(static fn (array $r): string => $r['target_id'], $store->all(1));
        self::assertSame(['1', '2', '3'], $order());
        self::assertNull($svc->move(3, -1));
        self::assertSame(['1', '3', '2'], $order());
        self::assertNull($svc->move(1, -1), 'déjà en tête : rien ne change');
        self::assertSame(['1', '3', '2'], $order());
        self::assertNull($svc->reorder([2, 3, 1]));
        self::assertSame(['2', '3', '1'], $order());
        self::assertNotNull($svc->reorder([2, 3]), 'liste incomplète refusée');
        self::assertNotNull($svc->reorder([2, 2, 1]));
        self::assertNotNull($svc->reorder([2, 3, 9]));
        self::assertSame('Épingle introuvable.', $svc->unpin(99));
        self::assertNull($svc->unpin(2));
        self::assertCount(2, $store->all(1));
        self::assertSame([3, 5, 4], JnetPinRules::swap([3, 4, 5], 5, -1));
    }

    // ——— Câblage, CSRF, porte, migration ———

    public function testWritesAreProtectedByCsrfAndReturnToAServerBuiltAddress(): void
    {
        $ctl = $this->read('app/Controllers/Web/JnetCommonController.php');
        self::assertStringContainsString('Csrf::validate', $ctl);
        self::assertStringContainsString('JnetCommonRules::salon(', $ctl, 'le salon de retour est validé');
        self::assertStringNotContainsString("\$request->input('back'", $ctl);
        self::assertStringNotContainsString("\$request->input('redirect'", $ctl);
        $routes = $this->read('routes/web.php');
        foreach (["post('/jnet/commun/epingles'", "post('/jnet/commun/epingles/ordre'", "post('/jnet/commun/epingles/{id}/retirer'", "post('/jnet/commun/epingles/{id}/deplacer'"] as $r) {
            self::assertStringContainsString($r, $routes);
        }
        self::assertStringContainsString("'/jnet/commun/'", $this->read('app/Middleware/CsrfPostMiddleware.php'));
        // Le formulaire d'épinglage porte le jeton.
        self::assertStringContainsString('Csrf::field()', $this->read('views/jnet/commun/_pin.php'));
        self::assertStringContainsString('Csrf::field()', $this->read('views/jnet/commun/_epingles.php'));
    }

    public function testIntelligenceSalonsAreOnlyReadBehindTheDoor(): void
    {
        $dash = $this->read('app/Services/Jnet/JnetDashboardService.php');
        self::assertMatchesRegularExpression('/function intelItems\([^)]*\): array\s*\{\s*if \(!\$this->mayReadIntelligence\(\)\)/', $dash);
        self::assertMatchesRegularExpression('/function findIntelItem\([^)]*\): \?array\s*\{\s*if \(!\$this->mayReadIntelligence\(\)/', $dash);
        self::assertMatchesRegularExpression('/function loadTargetsUncached\([^)]*\): array\s*\{[^}]*?if \(!\$this->mayReadIntelligence\(\)\)/s', $dash);
        $svc = $this->read('app/Services/Jnet/JnetCommonSpaceService.php');
        self::assertStringContainsString("return ['locked' => true];", $svc);
        // Le service ne décide d'aucun droit de lecture des échanges : il reprend ceux de JnetSpaceService.
        self::assertStringContainsString('visibleExchangesFor', $svc);
        self::assertStringContainsString('isOrgWideReader', $svc);
        self::assertStringContainsString("rights(\$tenantId, \$userId, 0)['publish']", $svc, 'publier au niveau de l’organisation');
        // Lectures groupées : aucune requête à l'intérieur de la boucle des unités.
        self::assertSame(1, substr_count($svc, 'taskCountsByUnit('));
        self::assertSame(1, substr_count($svc, 'openTasks('));
        // Calcul paresseux : un seul salon est construit par requête.
        self::assertStringContainsString("match (\$salon) {", $svc);
    }

    public function testMigrationIsIdempotentReversibleAndRegistered(): void
    {
        $sql = $this->read('migrations/2026_11_15_090_jnet_pins.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS jnet_pins', $sql);
        foreach (['tenant_id', 'scope', 'target_type', 'target_id', '`position`', 'pinned_by', 'pinned_at'] as $col) {
            self::assertStringContainsString($col, $sql, $col);
        }
        self::assertStringContainsString('UNIQUE KEY', $sql);
        self::assertStringContainsString('DROP TABLE IF EXISTS jnet_pins', $this->read('migrations/rollback/jnet_pins_down.sql'));
        $registry = $this->read('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'jnet_pins'", $registry);
        self::assertStringContainsString('4575_jnet_pins.php', $registry);
        self::assertTrue(is_file(self::ROOT . 'database/steps/4575_jnet_pins.php'));
        self::assertStringContainsString('tableExists', $this->read('database/steps/4575_jnet_pins.php'));
        $repo = $this->read('app/Repositories/JnetPinRepository.php');
        self::assertStringContainsString('SELECT 1 FROM jnet_pins', $repo, 'les écrans tolèrent l’absence de la table');
    }

    // ——— Vues ———

    public function testEveryCommonViewImportsTheClassesItCalls(): void
    {
        $files = array_merge(['views/jnet/home.php'], array_map(static fn (string $f): string => 'views/jnet/commun/' . basename($f), glob(self::ROOT . 'views/jnet/commun/*.php') ?: []));
        foreach ($files as $file) {
            $src = (string) preg_replace('#/\*.*?\*/#s', '', $this->read($file));
            $uses = [];
            if (preg_match_all('/^use ([\w\\\\]+);/m', $src, $m)) {
                foreach ($m[1] as $fq) {
                    $uses[] = substr($fq, (int) strrpos($fq, '\\') + 1);
                }
            }
            // Appels statiques « Classe::méthode » (hors noms qualifiés, self, static, parent).
            preg_match_all('/(?<![\\\\\w$])([A-Z]\w+)::/', $src, $calls);
            foreach (array_unique($calls[1]) as $class) {
                if (in_array($class, ['self', 'static', 'parent'], true)) {
                    continue;
                }
                self::assertContains($class, $uses, $file . ' appelle ' . $class . ' sans l’importer');
            }
        }
    }

    public function testViewsHaveNoEyebrowEmojiOrCenteredColumn(): void
    {
        $css = $this->read('public/assets/css/jnet_commun.css');
        self::assertStringNotContainsString('max-width: 1320px', $css);
        self::assertStringContainsString('@media (max-width: 899px)', $css);
        self::assertStringContainsString('@media (max-width: 1279px)', $css);
        self::assertStringContainsString('prefers-reduced-motion', $css);
        foreach (glob(self::ROOT . 'views/jnet/commun/*.php') ?: [] as $file) {
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', (string) file_get_contents($file)), basename($file) . ' : pas d’emoji');
        }
        $home = $this->read('views/jnet/home.php');
        self::assertSame(1, substr_count($home, '<h1'), 'un seul titre de page');
        self::assertStringContainsString('aria-current="page"', $this->read('views/jnet/commun/_rail.php'));
        self::assertStringContainsString('aria-label="Salons"', $this->read('views/jnet/commun/_rail.php'));
        self::assertStringContainsString('jc-drawer', $home, 'tiroir des salons sur mobile');
    }

    public function testEnglishTextsAreInTheDictionary(): void
    {
        $en = require self::ROOT . 'lang/en/site_auto.php';
        foreach ([
            'Annonces & ordres du jour', 'Ordres et tâches par unité', 'Voie hiérarchique & contacts', 'Renseignements', 'Cibles', 'Épinglés',
            'Élément masqué', 'Salon réservé', 'Tâches en retard', 'Ordres à accuser', 'Aujourd’hui', 'Hier', 'Retards', 'Accusés attendus',
            'Désépingler', 'Élément introuvable.', 'Cet élément est déjà épinglé.',
        ] as $fr) {
            self::assertArrayHasKey($fr, $en, $fr);
            self::assertNotSame('', $en[$fr]);
        }
        foreach (JnetCommonRules::SALONS as $meta) {
            self::assertArrayHasKey($meta['label'], $en, $meta['label']);
            self::assertArrayHasKey($meta['desc'], $en, $meta['desc']);
        }
        foreach (JnetUnitBoard::STATES as $label) {
            self::assertArrayHasKey($label, $en, $label);
        }
        foreach (JnetPinRules::TYPES as $label) {
            self::assertArrayHasKey($label, $en, $label);
        }
    }

    public function testExistingScreensKeepTheirRoutesAndTheCommonSpaceLinksThem(): void
    {
        $routes = $this->read('routes/web.php');
        foreach (['/jnet/operations', '/jnet/renseignement', '/jnet/cibles', '/jnet/exploitation', '/jnet/personnel', '/jnet/unite', '/jnet/bibliotheque'] as $route) {
            self::assertStringContainsString("'" . $route . "'", $routes, $route);
        }
        $rail = $this->read('views/jnet/commun/_rail.php');
        foreach (['jnet/unite', 'jnet/personnel', 'jnet/operations', 'jnet/exploitation', 'jnet/bibliotheque'] as $path) {
            self::assertStringContainsString("url('" . $path . "')", $rail, $path);
        }
        $ctl = $this->read('app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString("['jnet_commun.css'], ['jnet_commun.js']", $ctl);
        self::assertStringContainsString("\$query['salon'] = 'annonces';", $ctl, 'l’ancienne adresse ?type= ouvre les annonces');
    }
}

/** Stockage des épingles en mémoire. */
final class JnetMemoryPinStore implements JnetPinStore
{
    /** @var list<array{id: int, target_type: string, target_id: string, position: int, pinned_by: int, pinned_at: string}> */
    private array $rows = [];
    private int $seq = 0;

    public function ready(): bool
    {
        return true;
    }

    public function all(int $tenantId): array
    {
        $rows = $this->rows;
        usort($rows, static fn (array $a, array $b): int => [$a['position'], $a['id']] <=> [$b['position'], $b['id']]);

        return $rows;
    }

    public function add(int $tenantId, string $type, string $id, int $userId): int
    {
        foreach ($this->rows as $r) {
            if ($r['target_type'] === $type && $r['target_id'] === $id) {
                return 0;
            }
        }
        $this->seq++;
        $this->rows[] = ['id' => $this->seq, 'target_type' => $type, 'target_id' => $id, 'position' => $this->seq, 'pinned_by' => $userId, 'pinned_at' => '2026-11-15 10:00:00'];

        return $this->seq;
    }

    public function remove(int $tenantId, int $pinId): void
    {
        $this->rows = array_values(array_filter($this->rows, static fn (array $r): bool => $r['id'] !== $pinId));
    }

    public function setOrder(int $tenantId, array $ids): void
    {
        foreach ($this->rows as $i => $r) {
            $rank = array_search($r['id'], $ids, true);
            $this->rows[$i]['position'] = $rank === false ? 1000 + $i : (int) $rank + 1;
        }
    }
}

/** Lecteur de test : voit seulement ce qu'on lui montre. */
final class JnetPinTestViewer implements JnetLinkViewer
{
    /** @param array<string, array<string, mixed>> $visible */
    public function __construct(private int $user, private array $visible)
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
        return '2026-11-15';
    }

    public function see(string $type, string $id): ?array
    {
        return $this->visible[$type . ':' . $id] ?? null;
    }

    public function canWriteIn(int $spaceId): bool
    {
        return false;
    }

    public function isChief(int $spaceId): bool
    {
        return false;
    }

    public function canModerate(int $spaceId): bool
    {
        return false;
    }

    public function isOrgManager(): bool
    {
        return false;
    }

    public function inOwnUnits(): bool
    {
        return false;
    }

    public function unitLabel(int $unitId): ?string
    {
        return null;
    }

    public function related(int $a, int $b): bool
    {
        return false;
    }

    public function ownUnitIds(): array
    {
        return [];
    }

    public function search(string $q, array $types, int $spaceId): array
    {
        return [];
    }

    public function authors(array $userIds): array
    {
        return [];
    }
}
