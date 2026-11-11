<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakPlanRepository;
use App\Services\AtakToc\PlanCatalog;
use App\Services\AtakToc\PlanGameMapper;
use App\Services\AtakToc\PlanInterop;
use App\Services\AtakToc\PlanService;
use App\Services\AtakToc\PlanTimeline;
use App\Services\AtakToc\PlanValidator;
use PHPUnit\Framework\TestCase;

/**
 * Éditeur de plans v2 : nouvelles propriétés et nouveaux types (validation, catalogue, envoi en jeu), position et
 * présence dans le temps (images clés, itinéraire suivi, phases datées), échange GeoJSON et KML dans les deux sens.
 */
final class AtakTocPlanV2Test extends TestCase
{
    /** @param array<string, mixed> $props @return array<string, mixed> */
    private static function ok(string $kind, string $geom, array $props = [], ?array $pts = null): array
    {
        $pts ??= match ($geom) {
            'point', 'circle', 'ellipse' => [[1000, 2000]],
            'rect' => [[1000, 2000], [1300, 2300]],
            'line' => [[1000, 2000], [1500, 2200]],
            default => [[1000, 2000], [1500, 2000], [1500, 2500]],
        };
        [$el, $why] = PlanValidator::element(['uid' => 'e_' . $kind, 'kind' => $kind, 'geom' => $geom, 'pts' => $pts, 'props' => $props]);
        self::assertNotNull($el, $kind . ' : ' . $why);

        return $el;
    }

    public function testElementsDAvantLaV2RessortentInchanges(): void
    {
        $unit = self::ok('unit', 'point', ['affil' => 'H', 'callsign' => 'ENI'])['props'];
        self::assertSame(['color', 'width', 'dash', 'arrow', 'fill', 'label', 'note', 'size', 'rotation', 'affil', 'echelon', 'unitType', 'callsign', 'sidc'], array_keys($unit));
        $axis = self::ok('axis', 'line')['props'];
        self::assertSame(['color', 'width', 'dash', 'arrow', 'fill', 'label', 'note', 'size', 'rotation'], array_keys($axis));
        // Valeurs par défaut envoyées telles quelles : toujours rien d'écrit.
        $same = self::ok('unit', 'point', [
            'affil' => 'H', 'callsign' => 'ENI', 'opacity' => 1, 'labelPos' => 'auto', 'labelSize' => 14, 'hidden' => false, 't0' => null, 't1' => null,
            'track' => [], 'route' => '', 'higher' => '', 'info' => '', 'status' => 'present', 'mod' => '', 'hq' => false, 'tf' => false, 'dir' => null,
            'parent' => '', 'ord' => 0, 'placed' => true, 'inconnue' => 'x',
        ])['props'];
        self::assertSame($unit, $same);
    }

    public function testProprietesCommunesAccepteesBorneesOuRetirees(): void
    {
        $p = self::ok('icon', 'point', [
            'opacity' => 0.456, 'labelPos' => 'end', 'labelSize' => 20, 'hidden' => 1, 't0' => -30, 't1' => '90.5', 'route' => 'e_route',
            'track' => [['t' => 60, 'p' => [3000, 3000]], ['t' => 0, 'p' => [1000, 2000]], ['t' => 60, 'p' => [9, 9]], ['t' => 'x', 'p' => [1, 1]], ['t' => 5, 'p' => [999999, 1]], 'n’importe quoi'],
        ])['props'];
        self::assertSame(0.46, $p['opacity']);
        self::assertSame('end', $p['labelPos']);
        self::assertSame(20, $p['labelSize']);
        self::assertTrue($p['hidden']);
        self::assertSame(-30.0, $p['t0']);
        self::assertSame(90.5, $p['t1']);
        self::assertSame('e_route', $p['route']);
        self::assertSame([['t' => 0.0, 'p' => [1000.0, 2000.0]], ['t' => 60.0, 'p' => [3000.0, 3000.0]]], $p['track'], 'triées, temps distincts, images invalides écartées');

        $clamped = self::ok('icon', 'point', ['opacity' => 0.01, 'labelSize' => 99, 't0' => -99999, 't1' => 99999, 'labelPos' => 'ailleurs', 'route' => 'pas un uid !'])['props'];
        self::assertSame(0.1, $clamped['opacity']);
        self::assertSame(32, $clamped['labelSize']);
        self::assertSame(PlanValidator::TIME_MIN, $clamped['t0']);
        self::assertSame(PlanValidator::TIME_MAX, $clamped['t1']);
        self::assertArrayNotHasKey('labelPos', $clamped);
        self::assertArrayNotHasKey('route', $clamped);

        // Images clés : seulement sur un élément ponctuel, 60 au plus.
        self::assertArrayNotHasKey('track', self::ok('line', 'line', ['track' => [['t' => 0, 'p' => [1, 1]]]])['props']);
        $many = [];
        for ($i = 0; $i < 90; $i++) {
            $many[] = ['t' => $i, 'p' => [100 + $i, 100]];
        }
        self::assertCount(PlanValidator::MAX_TRACK, self::ok('icon', 'point', ['track' => $many])['props']['track']);
    }

    public function testProprietesDesUnites(): void
    {
        $p = self::ok('unit', 'point', [
            'higher' => str_repeat('A', 50), 'info' => str_repeat('B', 50), 'status' => 'planned', 'mod' => 'reinforced', 'hq' => true, 'tf' => 1,
            'dir' => 400, 'parent' => 'e_mere', 'ord' => 12345, 'placed' => false, 'unitType' => 'airborne',
        ], [[0, 0]])['props'];
        self::assertSame(30, mb_strlen($p['higher']));
        self::assertSame(40, mb_strlen($p['info']));
        self::assertSame('planned', $p['status']);
        self::assertSame('reinforced', $p['mod']);
        self::assertTrue($p['hq']);
        self::assertTrue($p['tf']);
        self::assertSame(360.0, $p['dir']);
        self::assertSame('e_mere', $p['parent']);
        self::assertSame(9999, $p['ord']);
        self::assertFalse($p['placed']);
        self::assertSame('airborne', $p['unitType']);

        $bad = self::ok('unit', 'point', ['status' => 'mort', 'mod' => 'triple', 'dir' => 'nord', 'parent' => 'e_unit', 'ord' => -4])['props'];
        foreach (['status', 'mod', 'dir', 'parent', 'ord', 'placed', 'hq', 'tf'] as $k) {
            self::assertArrayNotHasKey($k, $bad, $k . ' (une unité ne peut pas être sa propre mère)');
        }
        // Réservées aux unités.
        self::assertArrayNotHasKey('higher', self::ok('icon', 'point', ['higher' => 'X', 'placed' => false])['props']);
        self::assertArrayNotHasKey('placed', self::ok('icon', 'point', ['placed' => false])['props']);
    }

    public function testProprietesParType(): void
    {
        self::assertSame(250.0, self::ok('axis', 'line', ['awidth' => 250])['props']['awidth']);
        self::assertSame(5000.0, self::ok('attack', 'line', ['awidth' => 90000])['props']['awidth']);
        self::assertArrayNotHasKey('awidth', self::ok('route', 'line', ['awidth' => 250])['props']);
        self::assertSame('company', self::ok('boundary', 'line', ['echelon' => 'company'])['props']['echelon']);
        self::assertArrayNotHasKey('echelon', self::ok('boundary', 'line', ['echelon' => 'armee'])['props']);
        $route = self::ok('route', 'line', ['speed' => 999, 'cp' => true])['props'];
        self::assertSame(200.0, $route['speed']);
        self::assertTrue($route['cp']);

        $ring = self::ok('ring', 'circle', ['radius' => 800, 'rings' => 9])['props'];
        self::assertSame(800.0, $ring['radius']);
        self::assertSame(5, $ring['rings']);
        self::assertArrayNotHasKey('rings', self::ok('ring', 'circle', ['rings' => 1])['props']);

        $fan = self::ok('fan', 'point')['props'];
        self::assertSame([1000.0, 0.0, 0.0, 60.0, false], [$fan['radius'], $fan['minRange'], $fan['bearing'], $fan['arc'], $fan['mask']], 'éventail : géométrie complète par défaut');
        $fan = self::ok('fan', 'point', ['radius' => 99999, 'minRange' => 50000, 'bearing' => 725, 'arc' => 1, 'mask' => 1])['props'];
        self::assertSame([40000.0, 40000.0, 360.0, 5.0, true], [$fan['radius'], $fan['minRange'], $fan['bearing'], $fan['arc'], $fan['mask']]);

        $box = self::ok('textbox', 'line', ['label' => str_repeat('é', 300)]);
        self::assertSame(240, mb_strlen($box['props']['label']));
        $raw = ['uid' => 'e_tb', 'kind' => 'textbox', 'geom' => 'line', 'pts' => [[1, 1], [2, 2]], 'props' => ['label' => '  ']];
        self::assertSame('text_empty', PlanValidator::element($raw)[1]);
        $raw['props']['label'] = 'Renvoi';
        $raw['pts'][] = [3, 3];
        self::assertSame('points_count', PlanValidator::element($raw)[1], 'texte avec renvoi : exactement 2 points');
        self::assertSame('geom_invalid', PlanValidator::element(['uid' => 'e_fan', 'kind' => 'fan', 'geom' => 'circle', 'pts' => [[1, 1]]])[1]);
    }

    public function testCatalogueExposeLesNouveauxTypes(): void
    {
        $client = PlanCatalog::forClient();
        $kinds = $client['kinds'];
        $expected = [
            'medevac' => ['NOTE', 'point'], 'ccp' => ['NOTE', 'point'], 'supply' => ['NOTE', 'point'], 'op' => ['NOTE', 'point'], 'target' => ['TRP', 'point'],
            'decon' => ['NOTE', 'point'], 'fan' => ['NOTE', 'fire'], 'fscl' => ['PL', 'line'], 'cfl' => ['PL', 'line'], 'rfl' => ['PL', 'line'], 'flot' => ['PL', 'line'],
            'obstacle' => ['FREE', 'line'], 'textbox' => ['NOTE', 'text'], 'bp' => ['ZONE', 'area'], 'tai' => ['NAI', 'area'], 'pz' => ['HLZ', 'area'],
            'dz' => ['ZONE', 'area'], 'cbrn' => ['ZONE', 'area'], 'obstacle_belt' => ['ZONE', 'area'], 'ring' => ['ZONE', 'fire'],
        ];
        foreach ($expected as $kind => [$game, $family]) {
            self::assertSame($game, $kinds[$kind]['game'] ?? null, $kind);
            self::assertSame($family, $kinds[$kind]['family'], $kind);
            self::assertSame([], array_diff($kinds[$kind]['geoms'], PlanCatalog::GEOMS), $kind);
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $kinds[$kind]['color'], $kind);
        }
        self::assertSame(['circle', 'area'], $kinds['pz']['geoms']);
        self::assertSame('dash', $kinds['cfl']['dash']);
        self::assertSame('NRBC', $kinds['cbrn']['text']);
        self::assertSame(0.0, $kinds['ring']['fill']);
        // L'import depuis le jeu ne change pas : aucun nouveau type n'y figure.
        self::assertSame([], array_intersect(array_column(PlanCatalog::FROM_GAME, 0), array_keys($expected)));
        $fns = array_column($client['unitTypes'], 'fn', 'id');
        self::assertSame('UCIA--', $fns['airborne']);
        self::assertSame('UCVU--', $fns['uav']);
        foreach ($fns as $fn) {
            self::assertMatchesRegularExpression('/^[A-Z-]{6}$/', $fn);
        }
    }

    public function testNouveauxTypesVersLeJeu(): void
    {
        $els = [
            self::ok('fan', 'point', ['radius' => 1000, 'bearing' => 90, 'arc' => 90, 'label' => 'MG']),
            self::ok('ring', 'circle', ['radius' => 500, 'rings' => 3]),
            self::ok('textbox', 'line', ['label' => 'Attention au pont']),
            self::ok('medevac', 'point'),
            self::ok('target', 'point'),
            self::ok('cbrn', 'area', ['label' => '']),
            self::ok('flot', 'line'),
            self::ok('obstacle_belt', 'rect'),
            self::ok('pz', 'circle'),
        ];
        foreach ($els as $i => $el) {
            $els[$i]['uid'] = 'e_' . $i;
            $els[$i]['z'] = $i;
        }
        $items = PlanGameMapper::toGameItems(7, $els);
        self::assertSame(['ZONE', 'ZONE', 'NOTE', 'NOTE', 'TRP', 'ZONE', 'PL', 'ZONE', 'HLZ'], array_column($items, 'kind'));
        // Éventail : centre puis arc de 45° à 135° (azimut 0 = nord, sens horaire), donc tout à l'est du centre.
        $fan = $items[0]['pts'];
        self::assertSame([1000.0, 2000.0], $fan[0]);
        self::assertCount(8, $fan);
        foreach (array_slice($fan, 1) as [$x, $y]) {
            self::assertGreaterThan(1000.0, $x);
            self::assertEqualsWithDelta(1000.0, sqrt(($x - 1000) ** 2 + ($y - 2000) ** 2), 1.5);
        }
        self::assertSame([1707.0, 2707.0], $fan[1], 'premier rayon au nord-est');
        self::assertSame('MG', $items[0]['text']);
        self::assertCount(24, $items[1]['pts'], 'cercle de portée : cercle extérieur');
        self::assertSame([[1000.0, 2000.0]], $items[2]['pts'], 'texte avec renvoi : note au point d’ancrage');
        self::assertSame('Attention au pont', $items[2]['text']);
        self::assertSame('MEDEVAC', $items[3]['text']);
        self::assertSame('NRBC', $items[5]['text'], 'zone sans titre : sigle du type');
        self::assertSame('FLOT', $items[6]['text']);
        self::assertCount(4, $items[7]['pts']);
        foreach ($items as $it) {
            self::assertNotNull(PlanGameMapper::wireArgs($it, 'WEST', 7), $it['kind']);
        }
        // Portée minimale : couronne de secteur (arc extérieur puis intérieur) ; 360° : cercle extérieur.
        $ringFan = PlanGameMapper::outline(self::ok('fan', 'point', ['radius' => 1000, 'minRange' => 400, 'arc' => 90]));
        self::assertCount(14, $ringFan);
        self::assertEqualsWithDelta(400.0, sqrt(($ringFan[13][0] - 1000) ** 2 + ($ringFan[13][1] - 2000) ** 2), 1.0);
        self::assertCount(24, PlanGameMapper::outline(self::ok('fan', 'point', ['arc' => 360, 'minRange' => 200])));
        // Masqué ou pas encore posé : jamais envoyé.
        $hidden = self::ok('medevac', 'point', ['hidden' => true]);
        $unplaced = self::ok('unit', 'point', ['placed' => false], [[0, 0]]);
        self::assertSame([], PlanGameMapper::toGameItems(7, [$hidden, $unplaced]));
    }

    public function testPositionDansLeTempsEnLigneDroite(): void
    {
        $el = self::ok('unit', 'point', ['track' => [['t' => 0, 'p' => [0, 0]], ['t' => 10, 'p' => [1000, 0]], ['t' => 30, 'p' => [1000, 2000]]]]);
        self::assertSame([0.0, 0.0], PlanTimeline::positionAt($el, -5, []), 'avant la première image');
        self::assertSame([0.0, 0.0], PlanTimeline::positionAt($el, 0, []));
        self::assertSame([250.0, 0.0], PlanTimeline::positionAt($el, 2.5, []));
        self::assertSame([1000.0, 0.0], PlanTimeline::positionAt($el, 10, []));
        self::assertSame([1000.0, 1000.0], PlanTimeline::positionAt($el, 20, []));
        self::assertSame([1000.0, 2000.0], PlanTimeline::positionAt($el, 500, []), 'après la dernière image');
        // Sans image clé, ou hors élément ponctuel : le premier point.
        self::assertSame([1000.0, 2000.0], PlanTimeline::positionAt(self::ok('unit', 'point'), 12, []));
        self::assertSame([1000.0, 2000.0], PlanTimeline::positionAt(self::ok('line', 'line'), 12, []));
    }

    public function testPositionLeLongDUnItineraire(): void
    {
        // Itinéraire en équerre : 1000 m vers l'est puis 1000 m vers le nord.
        $route = self::ok('route', 'line', [], [[0, 0], [1000, 0], [1000, 1000]]);
        $route['uid'] = 'e_route';
        $el = self::ok('unit', 'point', ['route' => 'e_route', 'track' => [['t' => 0, 'p' => [0, 30]], ['t' => 20, 'p' => [1040, 1000]]]]);
        $by = ['e_route' => $route];
        self::assertEqualsWithDelta([500.0, 0.0], PlanTimeline::positionAt($el, 5, $by), 0.001, 'un quart du trajet : sur la première branche');
        self::assertEqualsWithDelta([1000.0, 0.0], PlanTimeline::positionAt($el, 10, $by), 0.001, 'à mi-parcours : au coude, pas dans la diagonale');
        self::assertEqualsWithDelta([1000.0, 500.0], PlanTimeline::positionAt($el, 15, $by), 0.001);
        // Aux images clés : la position saisie, pas sa projection.
        self::assertSame([0.0, 30.0], PlanTimeline::positionAt($el, 0, $by));
        // Itinéraire inconnu, ou qui n'est pas un trait : ligne droite.
        self::assertEqualsWithDelta([520.0, 515.0], PlanTimeline::positionAt($el, 10, []), 0.001);
        self::assertEqualsWithDelta([520.0, 515.0], PlanTimeline::positionAt($el, 10, ['e_route' => self::ok('ao', 'area')]), 0.001);
        self::assertSame(1500.0, PlanTimeline::abscissa([[0.0, 0.0], [1000.0, 0.0], [1000.0, 1000.0]], [1200.0, 500.0]));
        self::assertSame([1000.0, 1000.0], PlanTimeline::pointAt([[0.0, 0.0], [1000.0, 0.0], [1000.0, 1000.0]], 99999.0));
    }

    public function testPresenceEtDebutDePhase(): void
    {
        $el = self::ok('icon', 'point', ['t0' => 10, 't1' => 20]);
        self::assertFalse(PlanTimeline::visibleAt($el, 9.9));
        self::assertTrue(PlanTimeline::visibleAt($el, 10));
        self::assertTrue(PlanTimeline::visibleAt($el, 19.9));
        self::assertFalse(PlanTimeline::visibleAt($el, 20), 't1 exclu');
        self::assertTrue(PlanTimeline::visibleAt(self::ok('icon', 'point'), -500), 'sans fenêtre : toujours présent');
        self::assertFalse(PlanTimeline::visibleAt(self::ok('icon', 'point', ['hidden' => true]), 0));
        self::assertFalse(PlanTimeline::visibleAt(self::ok('unit', 'point', ['placed' => false], [[0, 0]]), 0));

        $meta = PlanValidator::meta(['timeline' => ['phases' => ['p_1' => 45, 'p_2' => '-10.5', 'pas un uid' => 3, 'p_3' => 'x']]]);
        self::assertSame(45.0, PlanTimeline::phaseStart($meta, 'p_1'));
        self::assertSame(-10.5, PlanTimeline::phaseStart($meta, 'p_2'));
        self::assertNull(PlanTimeline::phaseStart($meta, 'p_3'));
        self::assertNull(PlanTimeline::phaseStart([], 'p_1'));

        $moving = self::ok('unit', 'point', ['track' => [['t' => 0, 'p' => [0, 0]], ['t' => 10, 'p' => [100, 0]]]]);
        $moving['uid'] = 'e_m';
        $late = self::ok('icon', 'point', ['t0' => 50]);
        $at = PlanTimeline::atTime([$moving, $late, self::ok('line', 'line')], 5);
        self::assertSame(['unit', 'line'], array_column($at, 'kind'));
        self::assertSame([[50.0, 0.0]], $at[0]['pts']);
    }

    public function testMetaCalquesEtChronologie(): void
    {
        $meta = PlanValidator::meta(['layers' => [['uid' => 'base', 'name' => 'Plan'], ['uid' => 'l_2', 'name' => 'Feux', 'opacity' => 0.05], ['uid' => 'l_3', 'name' => 'Log', 'opacity' => 0.5]]]);
        self::assertSame([1.0, 0.1, 0.5], array_column($meta['layers'], 'opacity'));
        self::assertSame(['h' => '', 'dur' => 240, 'start' => 0, 'zulu' => false, 'phases' => [], 'events' => []], $meta['timeline'], 'chronologie toujours présente');

        $events = [];
        for ($i = 0; $i < 70; $i++) {
            $events[] = ['uid' => 'ev_' . $i, 't' => $i, 'label' => 'Événement ' . $i, 'kind' => $i === 0 ? 'h' : 'inconnu', 'ref' => str_repeat('r', 50)];
        }
        $events[] = ['uid' => 'mauvais uid', 't' => 1];
        $phases = [];
        for ($i = 0; $i < 30; $i++) {
            $phases['p_' . $i] = $i * 10;
        }
        $tl = PlanValidator::meta(['timeline' => ['h' => '2026-11-11T05:30', 'dur' => 999999, 'start' => -99999, 'zulu' => 1, 'phases' => $phases, 'events' => $events]])['timeline'];
        self::assertSame('2026-11-11T05:30', $tl['h']);
        self::assertSame(14400, $tl['dur']);
        self::assertSame(-1440, $tl['start']);
        self::assertTrue($tl['zulu']);
        self::assertCount(PlanValidator::MAX_PHASES, $tl['phases']);
        self::assertCount(PlanValidator::MAX_EVENTS, $tl['events']);
        self::assertSame(['uid' => 'ev_0', 't' => 0.0, 'label' => 'Événement 0', 'kind' => 'h', 'ref' => str_repeat('r', 32)], $tl['events'][0]);
        self::assertSame('event', $tl['events'][1]['kind']);
        self::assertSame('', PlanValidator::meta(['timeline' => ['h' => '2026-13-40T99:99', 'dur' => 1, 'start' => 50]])['timeline']['h']);
        self::assertSame([10, 0], array_values(array_intersect_key(PlanValidator::meta(['timeline' => ['dur' => 1, 'start' => 50]])['timeline'], ['dur' => 1, 'start' => 1])));
        // Stable : repasser une méta nettoyée ne change rien.
        $clean = PlanValidator::meta(['timeline' => $tl, 'layers' => $meta['layers']]);
        self::assertSame($clean, PlanValidator::meta($clean));
    }

    public function testEnvoiEnJeuDUnePhaseDatee(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $repo = new AtakPlanRepository(AtakTocPlanServiceTest::open());
        $sent = [];
        $n = 0;
        $svc = new PlanService($repo, function (int $t, int $m, string $cmd, string $ref, array $args) use (&$sent, &$n): ?int {
            $sent[] = ['cmd' => $cmd, 'args' => $args];

            return ++$n;
        });
        $meta = PlanValidator::meta(['side' => 'WEST', 'timeline' => ['phases' => ['p_1' => 10]]]);
        $id = $repo->create(1, 7, 'Opération RAVEN', 1, 'altis', 'private', null, $meta);
        $plan = $repo->find(1, $id);
        self::assertNotNull($plan);
        $svc->savePhases($plan, [['uid' => 'p_1', 'name' => 'Assaut'], ['uid' => 'p_2', 'name' => 'Repli']]);
        $svc->saveElements($plan, [
            ['uid' => 'e_unit', 'kind' => 'unit', 'geom' => 'point', 'pts' => [[100, 100]], 'props' => ['callsign' => 'ALPHA', 'track' => [['t' => 0, 'p' => [100, 100]], ['t' => 20, 'p' => [2100, 100]]]]],
            ['uid' => 'e_late', 'kind' => 'medevac', 'geom' => 'point', 'pts' => [[500, 500]], 'props' => ['t0' => 60]],
            ['uid' => 'e_hide', 'kind' => 'op', 'geom' => 'point', 'pts' => [[600, 600]], 'props' => ['hidden' => true]],
            ['uid' => 'e_orbat', 'kind' => 'unit', 'geom' => 'point', 'pts' => [[0, 0]], 'props' => ['placed' => false]],
            ['uid' => 'e_obj', 'kind' => 'objective', 'geom' => 'area', 'pts' => [[1000, 1000], [1500, 1000], [1500, 1500]]],
        ], [], 7, [], true);
        $plan = $repo->find(1, $id) ?? $plan;
        self::assertSame(10.0, PlanTimeline::phaseStart($plan['meta'], 'p_1'), 'chronologie gardée en base');

        // Phase datée (H+10) : l'unité est à mi-chemin, le MEDEVAC (H+60) n'est pas encore là.
        $res = $svc->publish($plan, 'WEST', 'p_1', 7, 'Adj Mercier');
        self::assertTrue($res['ok']);
        self::assertSame(2, $res['sent']);
        $adds = array_values(array_filter($sent, static fn (array $c): bool => $c['cmd'] === 'plnadd'));
        self::assertSame(['OBJ', 'UNITF'], array_column(array_column($adds, 'args'), 'k'));
        self::assertSame('1100:100', $adds[1]['args']['p0']);

        // Phase sans début, puis plan entier : position de base ; masqué et non posé écartés, MEDEVAC envoyé.
        foreach (['p_2', null] as $phase) {
            $sent = [];
            $res = $svc->publish($plan, 'WEST', $phase, 7, 'Adj Mercier');
            self::assertSame(3, $res['sent']);
            $adds = array_values(array_filter($sent, static fn (array $c): bool => $c['cmd'] === 'plnadd'));
            self::assertSame(['NOTE', 'OBJ', 'UNITF'], array_column(array_column($adds, 'args'), 'k'));
            self::assertSame('100:100', $adds[2]['args']['p0']);
        }
    }

    /** @return array<string, mixed> */
    private static function doc(): array
    {
        $els = [
            self::ok('route', 'line', ['label' => 'Route « GOLD » <&>', 'speed' => 30]),
            self::ok('unit', 'point', ['callsign' => 'ALPHA', 'echelon' => 'platoon', 'unitType' => 'mech', 'affil' => 'H', 'route' => 'e_route', 'parent' => 'e_absent', 'track' => [['t' => 0, 'p' => [1000, 2000]], ['t' => 9, 'p' => [1500, 2200]]]]),
            self::ok('fan', 'point', ['radius' => 600, 'arc' => 90, 'bearing' => 45]),
            self::ok('ring', 'circle', ['radius' => 300, 'rings' => 2]),
            self::ok('objective', 'area', ['fill' => 0.3, 'note' => 'Note']),
            self::ok('ellipse', 'ellipse', ['rx' => 300, 'ry' => 100, 'rotation' => 30]),
            self::ok('obstacle_belt', 'rect'),
            self::ok('textbox', 'line', ['label' => 'Renvoi']),
            self::ok('medevac', 'point', ['hidden' => true]),
        ];
        $els[1]['group'] = 'g_1';
        $els[0]['group'] = 'g_1';

        return ['format' => 'athena-plan', 'version' => 1, 'title' => 'Opération RAVEN', 'world' => 'altis', 'elements' => $els];
    }

    /** @param list<array<string, mixed>> $els @return list<array<string, mixed>> */
    private static function sansRenvois(array $els): array
    {
        return array_map(static function (array $el): array {
            unset($el['uid'], $el['group'], $el['props']['route'], $el['props']['parent']);

            return $el;
        }, $els);
    }

    public function testGeoJsonAllerRetourSansPerte(): void
    {
        $doc = self::doc();
        $geo = PlanInterop::toGeoJson($doc);
        self::assertSame('FeatureCollection', $geo['type']);
        self::assertSame(['format' => 'athena-plan-geojson', 'version' => 1, 'crs' => 'arma-metres', 'world' => 'altis', 'title' => 'Opération RAVEN'], $geo['athena']);
        self::assertCount(8, $geo['features'], 'élément masqué non exporté');
        $types = array_column(array_column($geo['features'], 'geometry'), 'type');
        self::assertSame(['LineString', 'Point', 'Polygon', 'Polygon', 'Polygon', 'Polygon', 'Polygon', 'LineString'], $types);
        $unit = $geo['features'][1];
        self::assertSame([1000.0, 2000.0], $unit['geometry']['coordinates']);
        self::assertSame('SHGPUCIZ---D---', $unit['properties']['sidc']);
        self::assertSame('ALPHA', $unit['properties']['callsign']);
        self::assertSame('unit', $unit['properties']['kind']);
        self::assertSame($doc['elements'][1], $unit['properties']['athena']);
        foreach ($geo['features'] as $f) {
            if ($f['geometry']['type'] === 'Polygon') {
                $ring = $f['geometry']['coordinates'][0];
                self::assertSame($ring[0], $ring[count($ring) - 1], 'anneau fermé');
            }
        }
        self::assertSame('SFGPUCVU---F---', PlanInterop::sidc(['unitType' => 'uav', 'echelon' => 'battalion']));
        self::assertSame('SHGPUCIZ---D---X', PlanInterop::sidc(['sidc' => 'SHGPUCIZ---D---X']));

        [$back, $rejected, $why] = PlanInterop::fromGeoJson(json_encode($geo) ?: '');
        self::assertSame('', $why);
        self::assertSame(0, $rejected);
        self::assertCount(8, $back);
        self::assertSame(self::sansRenvois(array_slice($doc['elements'], 0, 8)), self::sansRenvois($back), 'tout sauf les identifiants');
        // Nouveaux identifiants, renvois internes suivis, renvoi vers un absent retiré.
        self::assertSame([], array_intersect(array_column($back, 'uid'), array_column($doc['elements'], 'uid')));
        self::assertCount(8, array_unique(array_column($back, 'uid')));
        self::assertSame($back[0]['uid'], $back[1]['props']['route']);
        self::assertArrayNotHasKey('parent', $back[1]['props']);
        self::assertSame($back[0]['group'], $back[1]['group']);
        self::assertNotSame('g_1', $back[0]['group']);
    }

    public function testGeoJsonEtrangerEnDegresRameneDansLeCadre(): void
    {
        // Rectangle de 0,02° × 0,01° à 60° N : cos(60°) = 0,5, donc aussi large que haut une fois corrigé.
        $geo = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['name' => 'Zone <b>A</b>', 'stroke' => '#F00', 'stroke-width' => 5], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[10.0, 60.0], [10.02, 60.0], [10.02, 60.01], [10.0, 60.01], [10.0, 60.0]]]]],
            ['type' => 'Feature', 'properties' => ['title' => 'PC', 'marker-color' => '#00ff00'], 'geometry' => ['type' => 'Point', 'coordinates' => [10.01, 60.005, 120]]],
            ['type' => 'Feature', 'properties' => null, 'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[10.0, 60.0], [10.02, 60.01]], [[10.0, 60.01], [10.02, 60.0]]]]],
            ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'LineString', 'coordinates' => [[10.0, 60.0]]]],
            ['type' => 'Feature', 'properties' => [], 'geometry' => null],
        ]];
        [$els, $rejected, $why] = PlanInterop::fromGeoJson(json_encode($geo) ?: '', [1000, 2000, 5000, 4000]);
        self::assertSame('', $why);
        self::assertSame(2, $rejected, 'trait d’un seul point et objet sans géométrie');
        self::assertSame(['polygon', 'icon', 'line', 'line'], array_column($els, 'kind'));
        // Cadre de 4000 × 2000 m : la hauteur limite, le carré de 2000 m est centré, nord en haut.
        self::assertEqualsWithDelta([[2000.0, 2000.0], [4000.0, 2000.0], [4000.0, 4000.0], [2000.0, 4000.0]], $els[0]['pts'], 1.0);
        self::assertSame('Zone A', $els[0]['props']['label']);
        self::assertSame('#ff0000', $els[0]['props']['color']);
        self::assertSame(5, $els[0]['props']['width']);
        self::assertEqualsWithDelta([[3000.0, 3000.0]], $els[1]['pts'], 1.0);
        self::assertSame('PC', $els[1]['props']['label']);
        self::assertSame('#00ff00', $els[1]['props']['color']);
        foreach ($els as $el) {
            self::assertMatchesRegularExpression('/^i_[0-9a-f]{16}$/', $el['uid']);
        }
        // Sans cadre : 0..10000 par défaut. Cadre invalide : pareil.
        [$def] = PlanInterop::fromGeoJson(json_encode($geo) ?: '');
        self::assertEqualsWithDelta([[0.0, 0.0], [10000.0, 0.0], [10000.0, 10000.0], [0.0, 10000.0]], $def[0]['pts'], 3.0);
        self::assertSame(PlanInterop::DEFAULT_FIT, PlanInterop::fitBox([5, 5, 5, 'x']));
        self::assertSame([0.0, 100.0, 300.0, 400.0], PlanInterop::fitBox([300, 400, 0, 100]));

        // Coordonnées hors des degrés : des mètres, gardées telles quelles ; hors du terrain : refusées.
        $metres = ['type' => 'Feature', 'properties' => ['name' => 'Axe'], 'geometry' => ['type' => 'LineString', 'coordinates' => [[1200, 3400], [5600, 7800]]]];
        [$m, $r] = PlanInterop::fromGeoJson(json_encode($metres) ?: '', [0, 0, 100, 100]);
        self::assertSame([[1200.0, 3400.0], [5600.0, 7800.0]], $m[0]['pts']);
        self::assertSame(0, $r);
        $metres['geometry']['coordinates'][1] = [999999, 5];
        [$m, $r] = PlanInterop::fromGeoJson(json_encode($metres) ?: '');
        self::assertSame([[], 1], [$m, $r]);
    }

    public function testGeoJsonLimites(): void
    {
        self::assertSame('import_too_large', PlanInterop::fromGeoJson(str_repeat(' ', PlanValidator::MAX_IMPORT_BYTES + 1))[2]);
        self::assertSame('import_format', PlanInterop::fromGeoJson('pas du json')[2]);
        self::assertSame('import_format', PlanInterop::fromGeoJson('{"type":"Autre"}')[2]);
        self::assertSame('import_format', PlanInterop::fromGeoJson(str_repeat('[', 60) . str_repeat(']', 60))[2], 'profondeur bornée');
        $pts = [];
        for ($i = 0; $i <= PlanValidator::MAX_ELEMENTS; $i++) {
            $pts[] = [1000 + $i, 2000];
        }
        self::assertSame('import_too_many', PlanInterop::fromGeoJson(json_encode(['type' => 'MultiPoint', 'coordinates' => $pts]) ?: '')[2]);
        // Trait trop long : simplifié jusqu'à MAX_POINTS, pas refusé.
        $long = [];
        for ($i = 0; $i < 2000; $i++) {
            $long[] = [1000 + $i * 3, 2000 + 300 * sin($i / 9)];
        }
        [$els, $rejected] = PlanInterop::fromGeoJson(json_encode(['type' => 'LineString', 'coordinates' => $long]) ?: '');
        self::assertSame(0, $rejected);
        self::assertLessThanOrEqual(PlanValidator::MAX_POINTS, count($els[0]['pts']));
        self::assertGreaterThan(20, count($els[0]['pts']));
    }

    public function testKmlAllerRetour(): void
    {
        $doc = self::doc();
        $kml = PlanInterop::toKml($doc);
        self::assertStringContainsString('<kml xmlns="http://www.opengis.net/kml/2.2">', $kml);
        self::assertStringContainsString('<Data name="athena:crs"><value>arma-metres</value></Data>', $kml);
        self::assertStringContainsString('mètres du terrain Arma', $kml);
        self::assertStringContainsString('<name>Route « GOLD » &lt;&amp;&gt;</name>', $kml, 'texte échappé');
        self::assertStringContainsString('<Point><coordinates>1000,2000,0</coordinates></Point>', $kml);
        self::assertSame(8, substr_count($kml, '<Placemark>'), 'élément masqué non exporté');
        self::assertSame('4d4732f2', PlanInterop::kmlColor('#f23247', 0.3), 'aabbggrr');
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($kml), 'XML bien formé');

        [$back, $rejected, $why] = PlanInterop::fromKml($kml);
        self::assertSame('', $why);
        self::assertSame(0, $rejected);
        self::assertSame(self::sansRenvois(array_slice($doc['elements'], 0, 8)), self::sansRenvois($back), 'notre KML : sans perte');
        self::assertSame($back[0]['uid'], $back[1]['props']['route']);
    }

    public function testKmlEtranger(): void
    {
        $kml = '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document>'
            . '<Style id="rouge"><LineStyle><color>ff0000ff</color><width>4</width></LineStyle></Style>'
            . '<Folder><Placemark><name>Axe nord</name><description><![CDATA[<i>Par la crête</i>]]></description><styleUrl>#rouge</styleUrl>'
            . '<LineString><coordinates> 10.0,60.0,0 10.02,60.01,0 </coordinates></LineString></Placemark></Folder>'
            . '<Placemark><name>Zone</name><Style><PolyStyle><color>7f00ff00</color></PolyStyle></Style><Polygon><outerBoundaryIs><LinearRing><coordinates>10,60 10.02,60 10.02,60.01 10,60.01 10,60</coordinates></LinearRing></outerBoundaryIs>'
            . '<innerBoundaryIs><LinearRing><coordinates>10.005,60.002 10.01,60.002 10.01,60.004 10.005,60.002</coordinates></LinearRing></innerBoundaryIs></Polygon></Placemark>'
            . '<Placemark><name>Deux</name><MultiGeometry><Point><coordinates>10.01,60.005</coordinates></Point><Point><coordinates>10,60</coordinates></Point></MultiGeometry></Placemark>'
            . '<Placemark><name>Vide</name></Placemark><Placemark><name>Cassé</name><Point><coordinates>x,y</coordinates></Point></Placemark>'
            . '</Document></kml>';
        [$els, $rejected, $why] = PlanInterop::fromKml($kml, [0, 0, 2000, 2000]);
        self::assertSame('', $why);
        self::assertSame(2, $rejected);
        self::assertSame(['line', 'polygon', 'icon', 'icon'], array_column($els, 'kind'));
        self::assertEqualsWithDelta([[1000.0, 1000.0]], $els[2]['pts'], 1.0, 'degrés ramenés dans le cadre');
        self::assertEqualsWithDelta([[0.0, 0.0], [2000.0, 2000.0]], $els[0]['pts'], 1.0);
        self::assertSame('Axe nord', $els[0]['props']['label']);
        self::assertSame('Par la crête', $els[0]['props']['note']);
        self::assertSame('#ff0000', $els[0]['props']['color'], 'style partagé, aabbggrr relu');
        self::assertSame(4, $els[0]['props']['width']);
        self::assertSame('#00ff00', $els[1]['props']['color']);
        self::assertCount(4, $els[1]['pts'], 'contour extérieur seulement, sans le point de fermeture');
    }

    public function testKmlDangereuxOuTropGrosRefuse(): void
    {
        $evil = '<?xml version="1.0"?><!DOCTYPE kml [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><kml><Document><Placemark><name>&xxe;</name><Point><coordinates>1,1</coordinates></Point></Placemark></Document></kml>';
        self::assertSame([[], 0, 'import_unsafe'], PlanInterop::fromKml($evil));
        $bomb = '<?xml version="1.0"?><!doctype kml [<!entity a "aaaaaaaaaa">]><kml><Document/></kml>';
        self::assertSame('import_unsafe', PlanInterop::fromKml($bomb)[2]);
        self::assertSame('import_too_large', PlanInterop::fromKml(str_repeat(' ', PlanValidator::MAX_IMPORT_BYTES + 1))[2]);
        self::assertSame('import_format', PlanInterop::fromKml('<kml><Document>')[2]);
        self::assertSame('import_format', PlanInterop::fromKml('<gpx></gpx>')[2]);
        self::assertSame('import_format', PlanInterop::fromKml('')[2]);
        self::assertSame('import_too_many', PlanInterop::fromKml('<kml><Document>' . str_repeat('<Placemark/>', PlanValidator::MAX_ELEMENTS + 1) . '</Document></kml>')[2]);
    }

    public function testExportJsonAthenaReimporteDansUnPlan(): void
    {
        $doc = self::doc();
        $doc['phases'] = [['uid' => 'p_1', 'name' => 'Assaut'], ['uid' => 'p_2', 'name' => 'Repli']];
        $doc['elements'][0]['phases'] = ['p_1', 'p_2'];
        // L'ancien export (sans les champs de la v2) et le nouveau passent tous deux.
        [$full, $why] = PlanValidator::importDocument(json_encode($doc) ?: '');
        self::assertSame('', $why);
        self::assertSame(30.0, $full['elements'][0]['props']['speed']);
        self::assertSame(['p_1', 'p_2'], $full['elements'][0]['phases']);
        self::assertArrayHasKey('timeline', $full['meta']);

        [$els, $rejected, $why] = PlanInterop::fromAthena(json_encode($doc) ?: '', ['p_2', 'p_9']);
        self::assertSame(['', 0], [$why, $rejected]);
        self::assertCount(9, $els, 'tout le document, masqués compris');
        self::assertSame(['p_2'], $els[0]['phases'], 'phase gardée seulement si elle existe dans le plan qui reçoit');
        self::assertNotSame('e_route', $els[0]['uid']);
        self::assertSame($els[0]['uid'], $els[1]['props']['route']);
        self::assertSame('import_format', PlanInterop::fromAthena('{"format":"autre"}')[2]);
    }
}
