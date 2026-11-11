<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AtakToc\TocSymbols;
use App\Services\AtakToc\TocTracks;
use App\Support\I18n\UntranslatedFrenchScanner;
use PHPUnit\Framework\TestCase;

/**
 * Carte du poste TOC web : une seule piste par joueur ou par nœud (pas de doublon), genre et code OTAN d'une piste
 * (sol, véhicules, hélicoptères, avions, drones), correspondance des marqueurs Arma vers les icônes du poste, calques
 * et textes anglais de la console.
 */
final class AtakTocTracksTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private static function read(string $rel): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $rel));
    }

    /** @param array<string, mixed> $extra */
    private static function unit(int $id, string $cs, string $status, int $age, array $extra = []): array
    {
        return ['id' => $id, 'call_sign' => $cs, 'status' => $status, 'age_seconds' => $age, 'pos_x' => 1000.0 + $id, 'pos_y' => 2000.0, 'extra' => $extra];
    }

    public function testAPlayerWhoChangedCallsignOrGroupKeepsOnlyHisFreshestNode(): void
    {
        $rows = [
            self::unit(1, 'ALPHA 2-4', 'offline', 86400, ['steam_uid' => '76561198000000001', 'group_name' => 'ALPHA 2-4']),
            self::unit(2, 'YA1', 'offline', 7200, ['steam_uid' => '76561198000000001', 'group_name' => 'YA1']),
            self::unit(3, 'TA1', 'linked', 4, ['steam_uid' => '76561198000000001', 'group_name' => 'TA1']),
            self::unit(4, 'ZPU1', 'delayed', 130, ['steamId' => '76561198000000001']),
            self::unit(5, 'BRAVO 1', 'linked', 3, ['steam_uid' => '76561198000000002']),
        ];
        $out = TocTracks::dedupe($rows, 1_800_000_000);
        self::assertSame(['TA1', 'BRAVO 1'], array_column($out, 'call_sign'));
    }

    public function testNoDuplicateByStableIdWhateverTheOrderAndTheSource(): void
    {
        $rows = [
            self::unit(10, 'IA 1', 'linked', 5, ['is_ai' => true]),
            self::unit(11, 'ia 1', 'offline', 900, ['is_ai' => true]),
            self::unit(12, 'IA 2', 'offline', 900),
            self::unit(13, 'IA 2', 'offline', 300),
            self::unit(14, 'ECHO', 'delayed', 80, ['player_uid' => 'AbC']),
            self::unit(15, 'ECHO BIS', 'delayed', 20, ['steam_uid' => 'abc']),
            ['id' => 'gw-3-9', 'call_sign' => 'IA 1', 'gateway_id' => 3, 'status' => 'linked', 'age_seconds' => 2, 'extra' => []],
        ];
        foreach ([$rows, array_reverse($rows)] as $list) {
            $out = TocTracks::units($list, 1_800_000_000);
            $ids = array_map(static fn (array $r): string => (string) $r['toc']['uid'], $out);
            self::assertSame($ids, array_values(array_unique($ids)), 'un identifiant stable ne sort qu’une fois');
            self::assertCount(4, $out);
            $by = array_column($out, null, 'call_sign');
            self::assertArrayHasKey('ECHO BIS', $by, 'le relevé le plus récent du même joueur gagne');
            self::assertSame(13, (int) array_values(array_filter($out, static fn (array $r): bool => $r['call_sign'] === 'IA 2'))[0]['id']);
        }
        // Un contact d'une communauté alliée (passerelle) porte le même indicatif : ce n'est pas le même nœud.
        self::assertNotSame(TocTracks::stableId($rows[0]), TocTracks::stableId($rows[6]));
    }

    public function testUnitRowsGetKindAirFlagAltitudeSpeedAndAge(): void
    {
        $out = TocTracks::units([
            self::unit(1, 'VIPER 1', 'linked', 2, ['steam_uid' => '1', 'vehicle_class' => 'HELICOPTER', 'vehicle' => 'UH-80 Ghost Hawk', 'pos_z' => 120.4, 'speed' => 48, 'occupants' => [['name' => 'a'], ['name' => 'b']]]),
            self::unit(2, 'SABRE', 'linked', 2, ['steam_uid' => '2', 'vehicle' => ['name' => 'M2A4 Slammer', 'class_kind' => 'TANK'], 'in_vehicle' => true]),
            self::unit(3, 'DOC', 'delayed', 70, ['steam_uid' => '3', 'role' => 'Médecin']),
            self::unit(4, 'ENI', 'linked', 9, ['enemy_ai' => 1]),
        ], 1_800_000_000);
        $by = array_column($out, 'toc', 'call_sign');
        self::assertSame(['rotary', true, 120.4, 48.0, 2], [$by['VIPER 1']['kind'], $by['VIPER 1']['air'], $by['VIPER 1']['alt'], $by['VIPER 1']['speed'], $by['VIPER 1']['crew']]);
        self::assertSame(['armor', false], [$by['SABRE']['kind'], $by['SABRE']['air']]);
        self::assertSame(['medical', 70], [$by['DOC']['kind'], $by['DOC']['age']]);
        self::assertTrue($by['ENI']['ai'] && $by['ENI']['hostile']);
    }

    public function testKindOfATrackPlatformFirstThenVehicleNameThenRole(): void
    {
        $cases = [
            'rotary' => [['vehicle_class' => 'HELICOPTER'], ['aircraft_type' => 'helicopter'], ['vehicle' => 'B_Heli_Transport_01_F'], ['vehicle' => 'AH-99 Blackfoot']],
            'fixed' => [['vehicle_class' => 'PLANE'], ['platform' => 'fixed-wing'], ['vehicle' => 'A-164 Wipeout'], ['aircraft_type' => 'plane']],
            'uav' => [['vehicle_class' => 'UAV'], ['aircraft_type' => 'uav'], ['vehicle' => 'MQ-4A Greyhawk'], ['vehicle' => 'B_UAV_02_dynamicLoadout_F'], ['platform' => 'DRONE']],
            'armor' => [['vehicle_class' => 'TANK'], ['vehicle' => 'T-72B3']],
            'mechanized' => [['vehicle_class' => 'APC'], ['vehicle' => 'AMV-7 Marshall']],
            'motorized' => [['vehicle_class' => 'MRAP'], ['in_vehicle' => true], ['platform' => 'GROUND_VEHICLE'], ['vehicle' => 'Hunter HMG']],
            'artillery' => [['vehicle_class' => 'ARTILLERY'], ['vehicle' => 'M4 Scorcher'], ['role' => 'JTAC appui-feu']],
            'mortar' => [['vehicle_class' => 'MORTAR'], ['role' => 'Chef de pièce mortier']],
            'antiair' => [['vehicle_class' => 'AA'], ['vehicle' => 'IFV-6a Cheetah']],
            'support' => [['vehicle_class' => 'TRUCK'], ['vehicle' => 'HEMTT Ammo']],
            'medical' => [['vehicle_class' => 'AMBULANCE'], ['role' => 'Médecin'], ['role' => 'OPSAN']],
            'hq' => [['role' => 'Commandant de compagnie'], ['role' => 'TOC']],
            'recon' => [['role' => 'Éclaireur'], ['role' => 'Sniper']],
            'naval' => [['vehicle_class' => 'BOAT'], ['vehicle' => 'Speedboat Minigun']],
            'static' => [['vehicle_class' => 'STATIC'], ['vehicle' => 'B_HMG_01_high_F']],
            'infantry' => [[], ['role' => 'Fusilier'], ['vehicle_class' => 'INFANTRY', 'role' => 'Médecin']],
        ];
        foreach ($cases as $kind => $list) {
            foreach ($list as $hints) {
                self::assertSame($kind, TocSymbols::kindFor($hints), json_encode($hints, JSON_UNESCAPED_UNICODE) . ' => ' . $kind);
            }
        }
        // Un véhicule générique dont le nom dit mieux : le nom l'emporte sur « véhicule ».
        self::assertSame('armor', TocSymbols::kindFor(['vehicle_class' => 'GROUND_VEHICLE', 'vehicle' => 'MBT-52 Kuma']));
    }

    public function testNatoCodesByKindAndAffiliation(): void
    {
        self::assertSame('SFGPUCI--------', TocSymbols::sidc('infantry', 'F'));
        self::assertSame('SHGPUCA--------', TocSymbols::sidc('armor', 'H'));
        self::assertSame('SFAPMH---------', TocSymbols::sidc('rotary', 'F'));
        self::assertSame('SNAPMF---------', TocSymbols::sidc('fixed', 'N'));
        self::assertSame('SUAPMFQ--------', TocSymbols::sidc('uav', 'U'));
        self::assertSame('SFSPCL---------', TocSymbols::sidc('naval', 'F'));
        self::assertSame('SFGPUCI--------', TocSymbols::sidc('inconnu', 'X'), 'genre ou affiliation inconnus : infanterie amie');
        foreach (TocSymbols::KINDS as $kind => [$fn, $dim, $label]) {
            self::assertSame(15, strlen(TocSymbols::sidc($kind, 'H')), $kind);
            self::assertSame(6, strlen($fn), $kind);
            self::assertContains($dim, ['G', 'A', 'S']);
            self::assertNotSame('', $label);
        }
        self::assertTrue(TocSymbols::isAir('rotary') && TocSymbols::isAir('fixed') && TocSymbols::isAir('uav'));
        self::assertFalse(TocSymbols::isAir('armor') || TocSymbols::isAir('naval'));
    }

    public function testVehiclesAndAircraftAreUniqueAndTyped(): void
    {
        $now = strtotime('2026-11-11 18:00:00');
        $veh = TocTracks::vehicles([
            ['id' => 1, 'vehicle_callsign' => 'VIKING 1', 'vehicle_name' => 'Hunter HMG', 'vehicle_class' => 'MRAP', 'last_seen_at' => '2026-11-11 17:59:50'],
            ['id' => 2, 'vehicle_callsign' => 'Viking 1', 'vehicle_name' => 'Hunter HMG', 'vehicle_class' => 'MRAP', 'last_seen_at' => '2026-11-11 17:00:00'],
            ['id' => 3, 'vehicle_callsign' => 'SABRE 2', 'vehicle_name' => 'M2A4 Slammer', 'vehicle_class' => 'TANK', 'last_seen_at' => '2026-11-11 17:48:00'],
            ['id' => 4, 'vehicle_callsign' => 'CAISSE', 'vehicle_name' => '', 'vehicle_class' => '', 'last_seen_at' => '2026-11-11 17:59:00'],
        ], $now);
        self::assertSame([1, 3, 4], array_column($veh, 'id'));
        self::assertSame(['motorized', 'armor', 'motorized'], array_map(static fn (array $v): string => $v['toc']['kind'], $veh));
        self::assertSame([10, 720, 60], array_map(static fn (array $v): int => $v['toc']['age'], $veh));

        $air = TocTracks::air([
            ['callsign' => 'VIPER 1', 'aircraft_type' => 'helicopter', 'model' => 'UH-80', 'updated_at' => '2026-11-11 17:59:58'],
            ['callsign' => 'viper 1', 'aircraft_type' => 'helicopter', 'model' => 'UH-80', 'updated_at' => '2026-11-11 17:59:00'],
            ['callsign' => 'HAWG 3', 'aircraft_type' => 'plane', 'model' => 'A-164 Wipeout', 'updated_at' => '2026-11-11 17:59:57'],
            ['callsign' => 'REAPER 7', 'aircraft_type' => 'uav', 'model' => 'MQ-4A Greyhawk', 'updated_at' => '2026-11-11 17:58:25'],
            ['callsign' => 'X', 'aircraft_type' => '', 'model' => '', 'updated_at' => null],
        ], $now);
        self::assertSame(['VIPER 1', 'HAWG 3', 'REAPER 7', 'X'], array_column($air, 'callsign'));
        self::assertSame(['rotary', 'fixed', 'uav', 'fixed'], array_map(static fn (array $a): string => $a['toc']['kind'], $air));
        self::assertSame([2, 3, 95, null], array_map(static fn (array $a): ?int => $a['toc']['age'], $air));
        foreach ($air as $a) {
            self::assertTrue($a['toc']['air']);
        }
    }

    public function testEveryCommonArmaMarkerHasAnIconDrawnByTheConsole(): void
    {
        $js = self::read('public/assets/js/toc/toc-tracks.js');
        self::assertSame(1, preg_match('/var ICONS = \{(.*?)\n  \};/s', $js, $m));
        preg_match_all('/^\s{4}([a-z]+): \{ d: \'M[^\']+\'/m', $m[1], $mm);
        $drawn = $mm[1];
        sort($drawn);
        $declared = TocSymbols::ICONS;
        sort($declared);
        self::assertSame($declared, $drawn, 'TocSymbols::ICONS et les icônes de toc-tracks.js vont ensemble');
        foreach (TocSymbols::MARKER_ICONS as $type => $icon) {
            self::assertContains($icon, TocSymbols::ICONS, $type);
        }
        foreach (TocSymbols::NATO_MARKERS + TocSymbols::PLATFORMS as $k => $kind) {
            self::assertArrayHasKey($kind, TocSymbols::KINDS, (string) $k);
        }

        // Types courants du jeu : aucun ne retombe sur le point par défaut sans raison.
        $expect = [
            'mil_objective' => 'objective', 'hd_objective' => 'objective', 'mil_flag' => 'flag', 'hd_flag' => 'flag', 'mil_warning' => 'warning',
            'mil_pickup' => 'pickup', 'mil_start' => 'start', 'mil_end' => 'end', 'mil_join' => 'join', 'mil_destroy' => 'destroy', 'hd_ambush' => 'ambush',
            'mil_unknown' => 'unknown', 'mil_marker' => 'pin', 'mil_triangle' => 'triangle', 'mil_box' => 'square', 'mil_circle' => 'circle', 'mil_arrow' => 'arrow',
            'mil_arrow2' => 'arrow', 'mil_dot' => 'dot', 'hd_dot_noShadow' => 'dot', 'respawn_inf' => 'respawn', 'respawn_air' => 'heli', 'respawn_plane' => 'plane',
            'respawn_naval' => 'anchor', 'respawn_para' => 'para', 'respawn_nimporte' => 'respawn', 'loc_Hospital' => 'medical', 'loc_Fuelstation' => 'fuel',
            'loc_heli' => 'heli', 'loc_Transmitter' => 'tower', 'loc_Bunker' => 'bunker', 'waypoint' => 'waypoint', 'KIA' => 'kia', 'Minefield' => 'mine',
            'flag_NATO' => 'flag', 'flag_France' => 'flag', 'Empty' => 'label', 'EmptyIcon' => 'label', 'Contact_dot1' => 'dot', 'Contact_arrow2' => 'arrow',
            'type_jamais_vu' => 'dot', '' => 'dot',
        ];
        foreach ($expect as $type => $icon) {
            self::assertSame($icon, TocSymbols::marker($type)['icon'], $type);
        }
        // Marqueurs OTAN du jeu : symbole milsymbol du genre, affiliation du préfixe.
        $nato = [
            'b_inf' => ['infantry', 'F'], 'o_armor' => ['armor', 'H'], 'n_air' => ['rotary', 'N'], 'b_plane' => ['fixed', 'F'], 'o_uav' => ['uav', 'H'],
            'b_motor_inf' => ['motorized', 'F'], 'o_mech_inf' => ['mechanized', 'H'], 'b_art' => ['artillery', 'F'], 'b_mortar' => ['mortar', 'F'],
            'b_med' => ['medical', 'F'], 'b_hq' => ['hq', 'F'], 'b_recon' => ['recon', 'F'], 'o_naval' => ['naval', 'H'], 'b_support' => ['support', 'F'],
            'b_maint' => ['maintenance', 'F'], 'b_installation' => ['installation', 'F'], 'o_antiair' => ['antiair', 'H'], 'c_unknown' => ['unknown', 'N'],
            'u_installation' => ['installation', 'U'], 'B_Service' => ['support', 'F'],
        ];
        foreach ($nato as $type => [$kind, $affil]) {
            $spec = TocSymbols::marker($type);
            self::assertSame([$kind, $affil, ''], [$spec['nato'], $spec['affil'], $spec['icon']], $type);
        }
        // Les tables du navigateur sont celles du site.
        $client = TocSymbols::forClient();
        self::assertSame(TocSymbols::MARKER_ICONS, $client['markerIcons']);
        self::assertSame(array_keys(TocSymbols::KINDS), array_keys($client['kinds']));
        self::assertSame('MH----', $client['kinds']['rotary']['fn']);
    }

    public function testTocFeedsStayBehindTenantScopeAndScramble(): void
    {
        $src = self::read('app/Controllers/Api/AtakApiController.php');
        foreach (['unitsIndex' => 'TocTracks::units', 'airAssetsIndex' => 'TocTracks::air', 'vehiclesIndex' => 'TocTracks::vehicles'] as $fn => $call) {
            self::assertSame(1, preg_match('/public function ' . $fn . '\(.*?\n    \}\n/s', $src, $m), $fn);
            $body = $m[0];
            self::assertStringContainsString('$this->requireTenant($request)', $body, $fn . ' reste cloisonné par communauté');
            self::assertStringContainsString($call, $body);
            self::assertMatchesRegularExpression('/\(\'toc\'\) === \'1\'/', $body, $fn . ' : préparation à la demande seulement (toc=1)');
            self::assertLessThan(strpos($body, $call), strpos($body, 'requireTenant'));
        }
        preg_match('/public function unitsIndex\(.*?\n    \}\n/s', $src, $m);
        self::assertLessThan(strpos($m[0], 'TocTracks::units'), strpos($m[0], "applyIntelScramble(\$request, \$tenantId, \$mapId, 'unit'"), 'le brouillage passe avant la préparation du poste');
    }

    public function testConsoleLayersDefaultsAndSources(): void
    {
        $js = self::read('public/assets/js/toc/toc-layers.js');
        self::assertSame(1, preg_match('/var DEFS = \[(.*?)\n  \];/s', $js, $m));
        preg_match_all('/\{ id: \'([a-z]+)\', label: \'([^\']+)\', source: \'[^\']+\'(?:, every: (\d+), on: (true|false))?/', $m[1], $rows, PREG_SET_ORDER);
        $on = [];
        foreach ($rows as $r) {
            $on[$r[1]] = ($r[4] ?? '') === 'true';
        }
        foreach (['units', 'vehicles', 'aircraft', 'markers', 'routes', 'shapes', 'zones', 'camp', 'plan', 'reports', 'requests'] as $id) {
            self::assertTrue($on[$id] ?? false, 'calque affiché au départ : ' . $id);
        }
        foreach (['trails', 'relays', 'photos', 'orders'] as $id) {
            self::assertFalse($on[$id], 'calque masqué au départ : ' . $id);
        }
        foreach (['/api/atak/units?mapId=', '/api/atak/vehicles?mapId=', '/api/atak/air-assets?mapId=', '/api/atak/markers?mapId=', '/api/atak/waypoints?mapId='] as $api) {
            self::assertStringContainsString($api, $js);
        }
        self::assertSame(3, substr_count($js, "&toc=1'"), 'unités, véhicules et aéronefs demandent la préparation du poste');
        self::assertStringNotContainsString('PERDU', $js . self::read('public/assets/js/toc/toc-overlay.js'), 'l’état perdu se lit au style de la plaque, pas à un mot crié dans le libellé');
    }

    public function testEveryConsoleTextHasItsEnglishTranslation(): void
    {
        require_once self::ROOT . 'app/Support/helpers.php';
        $files = array_merge(glob(self::ROOT . 'public/assets/js/toc/*.js') ?: [], glob(self::ROOT . 'views/atak/toc/*.php') ?: []);
        self::assertGreaterThan(8, count($files));
        $missing = [];
        foreach ($files as $f) {
            foreach (UntranslatedFrenchScanner::missingInFile($f) as $s) {
                $missing[] = basename($f) . ' : ' . $s;
            }
        }
        self::assertSame([], $missing, 'textes du poste TOC web sans traduction dans lang/en/site_auto.php');

        $dict = require self::ROOT . 'lang/en/site_auto.php';
        $bundle = self::read('public/assets/js/i18n_en_dict.js');
        // Libellés envoyés par le site (genres de symbole) : traduits côté site.
        foreach (TocSymbols::KINDS as [, , $label]) {
            if ($label !== 'Installation') { // même mot en anglais
                self::assertArrayHasKey($label, $dict, $label);
            }
        }
        // Vocabulaire du poste en anglais, et présence dans le paquet lu par le navigateur.
        $vocab = [
            'Groupes' => 'Groups', 'Flux' => 'Feed', 'Ordres' => 'Orders', 'Camp' => 'Side', 'Réseau' => 'Network', 'Salle' => 'Room', 'Chronos' => 'Timers',
            'Main courante' => 'Duty log', 'Pertes' => 'Casualties', 'Demandes' => 'Requests', 'Calques' => 'Layers', 'En jeu' => 'In game', 'Ancien' => 'Stale',
            'Perdu' => 'Lost', 'Hors ligne' => 'Offline', 'En direct' => 'Live', 'Point de passage…' => 'Waypoint…',
            'Cercle de portée' => 'Range ring', 'Unités au sol' => 'Ground units', 'Luminosité du fond' => 'Background brightness',
        ];
        foreach ($vocab as $fr => $en) {
            self::assertSame($en, $dict[$fr] ?? null, $fr);
            self::assertStringContainsString(json_encode($fr, JSON_UNESCAPED_UNICODE) . ':' . json_encode($en, JSON_UNESCAPED_UNICODE), $bundle, $fr . ' : relancer python tools/i18n/site_i18n.py js-bundle');
        }
        foreach (['il y a %1 min' => '%1 min ago', 'Ping envoyé : %1.' => 'Ping sent: %1.', 'équipage %1 pers.' => 'crew of %1'] as $fr => $en) {
            self::assertSame($en, $dict[$fr] ?? null, $fr);
            self::assertStringContainsString('[' . json_encode($fr, JSON_UNESCAPED_UNICODE) . ',' . json_encode($en, JSON_UNESCAPED_UNICODE) . ']', $bundle, $fr);
        }
    }

    public function testConsoleGroupsFeaturesAndKeepsWritesOnExistingApis(): void
    {
        $js = self::read('public/assets/js/toc/toc-console.js');
        self::assertSame(1, preg_match('/var NAV = \[(.*?)\n  \];/s', $js, $m));
        self::assertSame(4, preg_match_all('/^\s{4}\[\'[a-z]+\', \'/m', $m[1]), 'quatre rubriques');
        preg_match_all('/\[\'([a-z]+)\', \'[^\']+\'\](?=[,\]])/', $m[1], $tabs);
        self::assertSame(['groupes', 'orbat', 'reseau', 'flux', 'ordres', 'demandes', 'chronos', 'excheck', 'main', 'pertes', 'plans', 'camp', 'salle', 'sat', 'radio'], $tabs[1]);
        foreach (['message', 'ordre', 'marqueur', 'point'] as $win) {
            self::assertMatchesRegularExpression('/\n    ' . $win . ': function \(p\) \{/', $js, 'fenêtre de la console : ' . $win);
        }
        // Écritures : API ATAK existantes, jeton CSRF joint à toute requête qui n'est pas une lecture.
        self::assertStringContainsString("if (opts.body !== undefined || init.method !== 'GET') {\n      init.headers['X-CSRF-Token'] = BOOT.csrf || '';", $js);
        foreach (["'/api/markers'", "'/api/pings'", "'/api/chat'", "'/api/atak/orders'", "'/api/atak/waypoints?mapId='"] as $api) {
            self::assertStringContainsString($api, $js);
        }
        self::assertStringContainsString('function canEditMarker(d) { return !!(d && d.type === \'marker\'', $js);
        self::assertStringContainsString('d.own || BOOT.canManage', $js, 'modifier ou retirer un marqueur : son auteur ou un responsable du poste');
        // Plus aucun renvoi vers ATAK web pour écrire un message ou donner un ordre.
        self::assertStringNotContainsString('Message au chef', $js);
        self::assertStringNotContainsString('Donner un ordre (ATAK web)', $js);

        $ui = self::read('public/assets/js/toc/toc-console-ui.js');
        foreach (["'Escape'", "'ArrowDown'", "'ArrowUp'", "e.key !== 'Tab'", 'role="menuitem"'] as $needle) {
            self::assertStringContainsString($needle, $ui);
        }
        self::assertStringContainsString("e.key === 'ContextMenu' || (e.key === 'F10' && e.shiftKey)", $js, 'menu de la carte au clavier');
        self::assertStringContainsString("document.addEventListener('contextmenu', blockNativeMenu)", $js, 'jamais le menu du navigateur sur la carte');

        $view = self::read('views/atak/toc/console.php');
        self::assertStringContainsString('role="menu"', $view);
        self::assertStringContainsString('role="dialog" aria-modal="true"', $view);
        self::assertStringNotContainsString('toc-tabs" id="toc-tabs" role="tablist" aria-label="Onglets du poste"', $view);
    }
}
