<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakPlanRepository;
use App\Repositories\AtakWebCommandRepository;
use App\Services\AtakToc\PlanCatalog;
use App\Services\AtakToc\PlanGameMapper;
use App\Services\AtakToc\PlanPdfExporter;
use App\Services\AtakToc\PlanService;
use App\Services\AtakToc\PlanValidator;
use App\Services\AtakToc\TocAccess;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Plans d'opération : stockage (vraie migration rejouée en sqlite), éléments (le dernier enregistré gagne),
 * verrous, phases, versions et restauration, duplication, cloisonnement entre communautés, droits et partage,
 * import et export JSON, correspondance avec l'app Plan du jeu (fil WC1), fiche PDF.
 */
final class AtakTocPlanServiceTest extends TestCase
{
    private PDO $pdo;

    private AtakPlanRepository $repo;

    /** @var list<array{cmd: string, ref: string, args: array<string, scalar>, ttl: int}> */
    private array $sent = [];

    private PlanService $svc;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = self::open();
        $this->repo = new AtakPlanRepository($this->pdo);
        $this->sent = [];
        $n = 100;
        $this->svc = new PlanService($this->repo, function (int $t, int $m, string $cmd, string $ref, array $args, int $ttl) use (&$n): ?int {
            $this->sent[] = ['cmd' => $cmd, 'ref' => $ref, 'args' => $args, 'ttl' => $ttl];

            return ++$n;
        });
    }

    public static function open(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $migrate = require dirname(__DIR__, 2) . '/bootstrap/atak_toc_plans_migration.php';
        $migrate($pdo);
        // Rejouée sans effet (idempotente).
        $migrate($pdo);

        return $pdo;
    }

    /** @param array<string, mixed> $over @return array<string, mixed> */
    private static function el(string $uid, string $kind = 'polygon', string $geom = 'area', array $over = []): array
    {
        $pts = match ($geom) {
            'point', 'circle', 'ellipse' => [[1000, 2000]],
            'rect' => [[1000, 2000], [1300, 2300]],
            'line' => [[1000, 2000], [1500, 2200], [1800, 2600]],
            default => [[1000, 2000], [1500, 2000], [1500, 2500]],
        };

        return array_replace_recursive(['uid' => $uid, 'kind' => $kind, 'geom' => $geom, 'pts' => $pts, 'props' => ['color' => '#f24d47', 'label' => 'OBJ', 'radius' => 150]], $over);
    }

    private function plan(int $tenant = 1, int $owner = 7, string $sharing = 'private'): array
    {
        $id = $this->repo->create($tenant, $owner, 'Opération RAVEN', 1, 'altis', $sharing, null, PlanValidator::meta(['side' => 'WEST']));
        $plan = $this->repo->find($tenant, $id);
        self::assertNotNull($plan);

        return $plan;
    }

    public function testMigrationCreatesAllTables(): void
    {
        foreach (['atak_plans', 'atak_plan_elements', 'atak_plan_phases', 'atak_plan_versions', 'atak_plan_shares', 'atak_plan_comments', 'atak_plan_presence', 'atak_toc_rooms', 'atak_toc_actions'] as $t) {
            self::assertNotFalse($this->pdo->query("SELECT COUNT(*) FROM {$t}"), $t);
        }
    }

    public function testElementCrudLastWriteWinsAndDeletesAreSynced(): void
    {
        $plan = $this->plan();
        $res = $this->svc->saveElements($plan, [self::el('e_one'), self::el('e_two', 'unit', 'point', ['props' => ['affil' => 'H', 'callsign' => 'ENI']])], [], 7, [], true);
        self::assertSame([], $res['rejected']);
        self::assertCount(2, $res['saved']);
        self::assertSame(1, $res['rev']);
        $first = $res['saved']['e_one'];

        // Un autre membre change le même élément : sa version gagne, l'auteur voit l'écrasement signalé à son tour.
        $res2 = $this->svc->saveElements($plan, [self::el('e_one', 'polygon', 'area', ['props' => ['label' => 'OBJ 2']])], [], 9, ['e_one' => $first], true);
        self::assertGreaterThan($first, $res2['saved']['e_one']);
        $res3 = $this->svc->saveElements($plan, [self::el('e_one', 'polygon', 'area', ['props' => ['label' => 'OBJ 3']])], [], 7, ['e_one' => $first], true);
        self::assertSame(['e_one'], $res3['overwritten']);
        $els = $this->repo->elements((int) $plan['id']);
        self::assertSame('OBJ 3', $els[0]['props']['label']);

        // Suppression : visible dans les changements « depuis » avec deleted, absente de la lecture complète.
        $since = $this->repo->lastChangeMs((int) $plan['id']);
        $del = $this->svc->saveElements($plan, [], ['e_two'], 7, [], true);
        self::assertArrayHasKey('e_two', $del['deleted']);
        $delta = $this->repo->elements((int) $plan['id'], $since, true);
        self::assertCount(1, $delta);
        self::assertTrue($delta[0]['deleted']);
        self::assertCount(1, $this->repo->elements((int) $plan['id']));
    }

    public function testInvalidElementsAreRejectedNotStored(): void
    {
        $plan = $this->plan();
        $res = $this->svc->saveElements($plan, [
            self::el('bad uid!'),
            self::el('e_kind', 'nuke'),
            self::el('e_geom', 'unit', 'line'),
            ['uid' => 'e_far', 'kind' => 'polygon', 'geom' => 'area', 'pts' => [[0, 0], [999999, 0], [5, 5]]],
            ['uid' => 'e_short', 'kind' => 'polygon', 'geom' => 'area', 'pts' => [[0, 0], [5, 5]]],
            ['uid' => 'e_txt', 'kind' => 'text', 'geom' => 'point', 'pts' => [[1, 1]], 'props' => ['label' => '']],
            self::el('e_ok', 'text', 'point', ['props' => ['label' => '<script>x</script>']]),
        ], [], 7, [], true);
        self::assertSame(['uid_invalid', 'kind_invalid', 'geom_invalid', 'points_invalid', 'points_count', 'text_empty'], array_column($res['rejected'], 'reason'));
        $els = $this->repo->elements((int) $plan['id']);
        self::assertCount(1, $els);
        // Texte gardé tel quel (échappé à l'affichage), jamais interprété.
        self::assertSame('<script>x</script>', $els[0]['props']['label']);
    }

    public function testLockedElementRefusedForOthers(): void
    {
        $plan = $this->plan();
        $this->svc->saveElements($plan, [self::el('e_lock', 'polygon', 'area', ['locked' => true])], [], 7, [], true);
        $res = $this->svc->saveElements($plan, [self::el('e_lock')], ['e_lock'], 9, [], false);
        self::assertSame(['e_lock', 'e_lock'], $res['refused']);
        $ok = $this->svc->saveElements($plan, [self::el('e_lock', 'polygon', 'area', ['locked' => false])], [], 7, [], true);
        self::assertArrayHasKey('e_lock', $ok['saved']);
    }

    public function testPhasesVersionsAndRestore(): void
    {
        $plan = $this->plan();
        $this->svc->savePhases($plan, [['uid' => 'p_1', 'name' => 'Mise en place', 'time_label' => 'H-0:30'], ['uid' => 'p_2', 'name' => 'Assaut'], ['uid' => 'x', 'name' => '']]);
        $phases = $this->repo->phases((int) $plan['id']);
        self::assertSame(['p_1', 'p_2'], array_column($phases, 'uid'));
        $this->svc->saveElements($plan, [self::el('e_a', 'polygon', 'area', ['phases' => ['p_2', 'p_unknown']])], [], 7, [], true);
        self::assertSame(['p_2'], $this->repo->elements((int) $plan['id'])[0]['phases'], 'phase inconnue retirée');

        $vid = $this->svc->saveVersion($plan, 'Avant briefing', 7);
        $this->svc->saveElements($plan, [self::el('e_b', 'line', 'line')], ['e_a'], 7, [], true);
        $this->svc->savePhases($plan, [['uid' => 'p_3', 'name' => 'Exfiltration']]);
        self::assertSame(['e_b'], array_column($this->repo->elements((int) $plan['id']), 'uid'));

        self::assertTrue($this->svc->restore($this->repo->find(1, (int) $plan['id']) ?? $plan, $vid, 7));
        self::assertSame(['e_a'], array_column($this->repo->elements((int) $plan['id']), 'uid'));
        self::assertSame(['p_1', 'p_2'], array_column($this->repo->phases((int) $plan['id']), 'uid'));
        $labels = array_column($this->repo->versions((int) $plan['id']), 'label');
        self::assertContains('Avant restauration', $labels);
        self::assertFalse($this->svc->restore($plan, 99999, 7));
    }

    public function testCommunitiesAreIsolated(): void
    {
        $plan = $this->plan(1);
        self::assertNull($this->repo->find(2, (int) $plan['id']));
        $this->repo->setShareToken(1, (int) $plan['id'], str_repeat('a', 32));
        self::assertNull($this->repo->findByToken(2, str_repeat('a', 32)));
        self::assertNotNull($this->repo->findByToken(1, str_repeat('a', 32)));
        self::assertNull($this->repo->findByToken(1, '../../etc'));
        $this->plan(2, 7, 'community');
        $mine = $this->repo->listVisible(1, 7, [], false);
        self::assertSame([(int) $plan['id']], array_column($mine, 'id'));
        $all = $this->repo->listVisible(1, 99, [], true);
        self::assertCount(1, $all, 'la configuration du poste ne voit que sa communauté');
    }

    public function testAccessBySharingAndPermissions(): void
    {
        $user = new TocAccess(static fn (string $s): bool => $s === 'atak.toc.use', static fn (int $t, int $u): array => $u === 9 ? [42] : []);
        $none = new TocAccess(static fn (string $s): bool => false, static fn (): array => []);
        $manager = new TocAccess(static fn (string $s): bool => $s === 'atak.toc.manage', static fn (): array => []);
        $private = $this->plan(1, 7, 'private');
        $community = $this->plan(1, 7, 'community');
        $unit = $this->plan(1, 7, 'unit');
        $this->repo->updatePlan(1, (int) $unit['id'], ['unit_id' => 42]);
        $unit = $this->repo->find(1, (int) $unit['id']);

        self::assertTrue($user->canView($private, 1, 7), 'auteur');
        self::assertFalse($user->canView($private, 1, 8));
        self::assertTrue($user->canView($private, 1, 8, [['scope' => 'user', 'ref_id' => 8, 'can_edit' => false]]));
        self::assertFalse($user->canEdit($private, 1, 8, [['scope' => 'user', 'ref_id' => 8, 'can_edit' => false]]));
        self::assertTrue($user->canEdit($community, 1, 8));
        self::assertFalse($none->canView($community, 1, 8), 'sans le droit du poste');
        self::assertTrue($user->canView($unit, 1, 9), 'membre de l’unité');
        self::assertFalse($user->canView($unit, 1, 8));
        self::assertFalse($user->canView($community, 2, 8), 'autre communauté');
        self::assertTrue($manager->canEdit($private, 1, 8));
        self::assertTrue($manager->canAdminister($private, 1, 8));
        self::assertFalse($user->canAdminister($community, 1, 8));
        $this->repo->updatePlan(1, (int) $community['id'], ['locked' => true]);
        $locked = $this->repo->find(1, (int) $community['id']);
        self::assertFalse($user->canEdit($locked, 1, 8), 'plan verrouillé');
        self::assertTrue($user->canEdit($locked, 1, 7));
    }

    public function testJsonExportImportRoundTripAndValidation(): void
    {
        $plan = $this->plan();
        $this->svc->savePhases($plan, [['uid' => 'p_1', 'name' => 'Assaut']]);
        $this->svc->saveElements($plan, [self::el('e_a', 'objective', 'area', ['phases' => ['p_1']]), self::el('e_u', 'unit', 'point')], [], 7, [], true);
        $doc = $this->svc->document($this->repo->find(1, (int) $plan['id']) ?? $plan);
        self::assertSame('athena-plan', $doc['format']);
        $json = json_encode($doc) ?: '';
        [$imported, $why] = PlanValidator::importDocument($json);
        self::assertSame('', $why);
        self::assertNotNull($imported);
        self::assertCount(2, $imported['elements']);
        self::assertSame(['p_1'], $imported['elements'][0]['phases']);

        self::assertSame('import_format', PlanValidator::importDocument('{"format":"autre"}')[1]);
        self::assertSame('import_format', PlanValidator::importDocument('pas du json')[1]);
        self::assertSame('import_too_large', PlanValidator::importDocument(str_repeat(' ', PlanValidator::MAX_IMPORT_BYTES + 1))[1]);
        $many = ['format' => 'athena-plan', 'version' => 1, 'elements' => array_fill(0, PlanValidator::MAX_ELEMENTS + 1, self::el('e_x'))];
        self::assertSame('import_too_many', PlanValidator::importDocument(json_encode($many) ?: '')[1]);
        $bad = ['format' => 'athena-plan', 'version' => 1, 'elements' => [self::el('e_ok'), ['uid' => 'e_bad', 'kind' => 'polygon', 'pts' => 'x']]];
        [$partial] = PlanValidator::importDocument(json_encode($bad) ?: '');
        self::assertSame(1, $partial['rejected']);
    }

    public function testDuplicateCopiesElementsPrivately(): void
    {
        $plan = $this->plan(1, 7, 'community');
        $this->svc->saveElements($plan, [self::el('e_a'), self::el('e_b', 'arrow', 'line')], [], 7, [], true);
        $copyId = $this->svc->duplicate($plan, 9);
        $copy = $this->repo->find(1, $copyId);
        self::assertSame('private', $copy['sharing']);
        self::assertSame(9, $copy['owner_user_id']);
        self::assertSame(['e_a', 'e_b'], array_column($this->repo->elements($copyId), 'uid'));
        $tpl = $this->repo->find(1, $this->svc->duplicate($plan, 9, 'Modèle', true));
        self::assertTrue($tpl['is_template']);
        self::assertSame('community', $tpl['sharing']);
    }

    public function testGameMappingDegradesAndFitsTheWire(): void
    {
        $els = [];
        foreach ([
            self::el('e1', 'unit', 'point', ['props' => ['affil' => 'F', 'callsign' => 'ALPHA 1']]),
            self::el('e2', 'unit', 'point', ['props' => ['affil' => 'N', 'callsign' => 'Village']]),
            self::el('e3', 'lz', 'circle'),
            self::el('e4', 'ao', 'rect'),
            self::el('e5', 'minefield', 'area', ['props' => ['label' => '']]),
            self::el('e6', 'axis', 'line', ['props' => ['label' => 'Axe d’été « GOLD »']]),
            self::el('e7', 'sbf', 'line'),
            self::el('e8', 'ellipse', 'ellipse', ['props' => ['rx' => 300, 'ry' => 100, 'rotation' => 45]]),
        ] as $raw) {
            [$el, $why] = PlanValidator::element($raw);
            self::assertNotNull($el, $why);
            $els[] = $el;
        }
        $items = PlanGameMapper::toGameItems(12, $els);
        $byKind = array_column($items, 'kind');
        self::assertSame(['UNITF', 'NOTE', 'HLZ', 'AO', 'ZONE', 'AXIS', 'SBF', 'ZONE'], $byKind);
        self::assertSame('NEUTRE Village', $items[1]['text']);
        self::assertSame('unit_affil', $items[1]['degraded']);
        self::assertCount(24, $items[2]['pts'], 'cercle en polygone');
        self::assertCount(4, $items[3]['pts'], 'rectangle en polygone');
        self::assertSame('MINES', $items[4]['text']);
        self::assertSame('Axe d ete GOLD', $items[5]['text'], 'texte ASCII pour le fil');
        self::assertCount(2, $items[6]['pts'], 'SBF : position et direction');
        self::assertStringStartsWith('W' . base_convert('12', 10, 36) . '_', $items[0]['id']);
        foreach ($items as $it) {
            self::assertContains($it['color'], array_keys(PlanCatalog::GAME_COLORS));
            $args = PlanGameMapper::wireArgs($it, 'WEST', 12);
            self::assertNotNull($args);
            self::assertLessThanOrEqual(480, strlen(AtakWebCommandRepository::buildWire('toc', '*', 'plnadd', $it['id'], $args)));
        }
        // Tracé à main levée de 600 points : simplifié pour tenir dans le fil.
        $free = [];
        for ($i = 0; $i < 600; $i++) {
            $free[] = [1000 + $i * 7.3, 2000 + 300 * sin($i / 9)];
        }
        [$long] = PlanValidator::element(['uid' => 'e_free', 'kind' => 'line', 'geom' => 'line', 'pts' => $free]);
        $item = PlanGameMapper::toGameItems(12, [$long])[0];
        self::assertLessThanOrEqual(PlanGameMapper::MAX_WIRE_POINTS, count($item['pts']));
        self::assertNotNull(PlanGameMapper::wireArgs($item, 'EAST', 12));
        // Une phase : seuls ses éléments (et ceux sans phase) partent.
        $phased = $els;
        $phased[0]['phases'] = ['p_x'];
        $phased[1]['phases'] = ['p_y'];
        self::assertCount(7, PlanGameMapper::toGameItems(12, $phased, 'p_x'));
        self::assertSame('RED', PlanGameMapper::nearestGameColor('#ff0000'));
        self::assertSame('BLUE', PlanGameMapper::nearestGameColor('#6cb8ff'));
    }

    public function testFromGameItemsAndPublishCommands(): void
    {
        $items = [
            ['id' => 'P1', 'kind' => 'PL', 'pts' => [[100, 200], [900, 250]], 'text' => 'PL BLUE', 'color' => 'BLUE', 'dashed' => false, 'arrow' => false, 'width' => 2],
            ['id' => 'P2', 'kind' => 'UNITH', 'pts' => [[500, 500]], 'text' => 'ENI', 'color' => 'RED'],
            ['id' => 'P3', 'kind' => 'NAI', 'pts' => [[1, 1], [2, 2]], 'text' => 'NAI', 'color' => 'YELLOW'],
            ['id' => 'P4', 'kind' => 'BOGUS', 'pts' => [[1, 1]]],
            ['id' => 'W1_3', 'kind' => 'CP', 'pts' => [[10, 10]], 'text' => 'CP1', 'color' => 'WHITE'],
        ];
        $els = PlanService::elementsFromGamePlans([['side' => 'WEST', 'personal' => false, 'items' => $items]], 'WEST', 1);
        self::assertSame(['phase_line', 'unit', 'line'], array_column($els, 'kind'), 'zone à 2 points en trait, inconnu ignoré, éléments de ce plan non réimportés');
        self::assertSame('H', $els[1]['props']['affil']);
        self::assertSame([], PlanService::elementsFromGamePlans([['side' => 'EAST', 'personal' => false, 'items' => $items]], 'WEST'));

        $plan = $this->plan();
        self::assertSame('nothing_to_send', $this->svc->publish($plan, 'WEST', null, 7, 'Adj Mercier')['reason']);
        $this->svc->saveElements($plan, [self::el('e_a'), self::el('e_b', 'route', 'line')], [], 7, [], true);
        self::assertSame('side_invalid', $this->svc->publish($plan, 'MARS', null, 7, 'x')['reason']);
        $res = $this->svc->publish($this->repo->find(1, (int) $plan['id']) ?? $plan, 'WEST', null, 7, 'Adj Mercier');
        self::assertTrue($res['ok']);
        self::assertSame(2, $res['sent']);
        self::assertSame(['plnclr', 'plnadd', 'plnadd'], array_column($this->sent, 'cmd'));
        self::assertSame(PlanGameMapper::itemPrefix((int) $plan['id']), $this->sent[0]['ref']);
        self::assertSame('WEST', $this->sent[1]['args']['s']);
        $after = $this->repo->find(1, (int) $plan['id']);
        self::assertSame('WEST', $after['published_side']);
        self::assertSame(2, $after['published_count']);
        self::assertContains('Envoyé en jeu (WEST)', array_column($this->repo->versions((int) $plan['id']), 'label'));
    }

    public function testPdfRowsAndImageValidation(): void
    {
        $plan = $this->plan();
        $this->svc->savePhases($plan, [['uid' => 'p_1', 'name' => 'Assaut', 'time_label' => 'H+0:15']]);
        $this->svc->saveElements($plan, [self::el('e_a', 'objective', 'area', ['phases' => ['p_1']]), self::el('e_u', 'unit', 'point', ['props' => ['callsign' => 'ALPHA']])], [], 7, [], true);
        $this->repo->updatePlan(1, (int) $plan['id'], ['meta' => PlanValidator::meta(['notes' => ['mission' => 'Saisir RAVEN']])]);
        $rows = PlanPdfExporter::rows($this->svc->document($this->repo->find(1, (int) $plan['id']) ?? $plan));
        self::assertSame('2', $rows['phases'][0]['count']);
        self::assertSame('0100 0200', $rows['elements'][0]['grid']);
        self::assertSame('ALPHA', $rows['elements'][1]['label']);
        self::assertSame([['2. Mission', 'Saisir RAVEN']], $rows['notes']);
        self::assertSame('042 116', PlanPdfExporter::grid(4250, 11690, 6));
        self::assertNull(PlanPdfExporter::decodeImage('data:image/png;base64,' . base64_encode('pas une image')));
        self::assertNull(PlanPdfExporter::decodeImage('data:image/svg+xml;base64,PHN2Zz4='));
        self::assertNull(PlanPdfExporter::decodeImage('javascript:alert(1)'));
    }
}
