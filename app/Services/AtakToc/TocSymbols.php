<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Symboles de la carte du poste TOC web : genre d'une piste (infanterie, blindé, hélicoptère, drone…) et son code
 * OTAN (SIDC 2525C lu par milsymbol), et correspondance des marqueurs Arma (mil_*, hd_*, b_/o_/n_, loc_*, respawn_*,
 * flag_*…) vers les icônes du poste. Les mêmes tables partent au navigateur (forClient) : toc-tracks.js n'a pas sa
 * propre copie.
 */
final class TocSymbols
{
    /** Genre => [fonction 2525C (6 caractères), dimension (G sol, A air, S mer), libellé]. */
    public const KINDS = [
        'infantry' => ['UCI---', 'G', 'Infanterie'],
        'motorized' => ['UCIM--', 'G', 'Infanterie motorisée'],
        'mechanized' => ['UCIZ--', 'G', 'Infanterie mécanisée'],
        'armor' => ['UCA---', 'G', 'Blindés'],
        'recon' => ['UCR---', 'G', 'Reconnaissance'],
        'artillery' => ['UCF---', 'G', 'Artillerie'],
        'mortar' => ['UCFM--', 'G', 'Mortier'],
        'antiair' => ['UCD---', 'G', 'Défense sol-air'],
        'engineer' => ['UCE---', 'G', 'Génie'],
        'support' => ['US----', 'G', 'Soutien'],
        'maintenance' => ['USX---', 'G', 'Maintenance'],
        'medical' => ['USM---', 'G', 'Santé'],
        'hq' => ['UH----', 'G', 'Poste de commandement'],
        'static' => ['EWM---', 'G', 'Arme en poste fixe'],
        'installation' => ['I-----', 'G', 'Installation'],
        'rotary' => ['MH----', 'A', 'Hélicoptère'],
        'fixed' => ['MF----', 'A', 'Avion'],
        'uav' => ['MFQ---', 'A', 'Drone'],
        'naval' => ['CL----', 'S', 'Embarcation'],
        'unknown' => ['------', 'G', 'Inconnu'],
    ];

    /** Classe de véhicule ou plateforme envoyée par le jeu => genre. */
    public const PLATFORMS = [
        'INFANTRY' => 'infantry', 'MAN' => 'infantry', 'SOLDIER' => 'infantry', 'FOOT' => 'infantry',
        'CAR' => 'motorized', 'MRAP' => 'motorized', 'LIGHT_VEHICLE' => 'motorized', 'MOTORIZED' => 'motorized',
        'GROUND_VEHICLE' => 'motorized', 'VEHICLE' => 'motorized', 'MOTORCYCLE' => 'motorized', 'QUAD' => 'motorized',
        'APC' => 'mechanized', 'IFV' => 'mechanized', 'MECH' => 'mechanized', 'WHEELED_APC' => 'mechanized', 'TRACKED_APC' => 'mechanized',
        'TANK' => 'armor', 'ARMOR' => 'armor', 'ARMOUR' => 'armor', 'MBT' => 'armor',
        'ARTILLERY' => 'artillery', 'ARTY' => 'artillery', 'MLRS' => 'artillery',
        'MORTAR' => 'mortar',
        'AA' => 'antiair', 'ANTIAIR' => 'antiair', 'ANTI_AIR' => 'antiair', 'SAM' => 'antiair',
        'TRUCK' => 'support', 'SUPPORT' => 'support', 'LOGISTICS' => 'support', 'SUPPLY' => 'support', 'FUEL' => 'support', 'AMMO' => 'support',
        'REPAIR' => 'maintenance', 'MAINTENANCE' => 'maintenance',
        'MEDICAL' => 'medical', 'AMBULANCE' => 'medical', 'MEDEVAC' => 'medical',
        'HQ' => 'hq', 'COMMAND' => 'hq',
        'STATIC' => 'static', 'STATIC_WEAPON' => 'static', 'STATICWEAPON' => 'static', 'TURRET' => 'static',
        'HELICOPTER' => 'rotary', 'HELI' => 'rotary', 'ROTARY' => 'rotary', 'ROTARY_WING' => 'rotary',
        'PLANE' => 'fixed', 'FIXED_WING' => 'fixed', 'AIRCRAFT' => 'fixed', 'JET' => 'fixed', 'VTOL' => 'fixed',
        'UAV' => 'uav', 'DRONE' => 'uav', 'UCAV' => 'uav', 'UGV' => 'motorized',
        'BOAT' => 'naval', 'SHIP' => 'naval', 'NAVAL' => 'naval', 'SUBMARINE' => 'naval',
    ];

    /** Nom ou classe du véhicule (texte libre) => genre : premier motif trouvé. Les drones avant les avions. */
    private const NAME_PATTERNS = [
        'uav' => '/uav|ucav|darter|greyhawk|falcon|pelican|sentinel|mq-?\d|drone/',
        'rotary' => '/heli|huron|ghosthawk|ghost_hawk|humming|pawnee|orca|kajman|taru|mohawk|hellcat|blackfoot|\b(ah|uh|mh|ch|ka|mi)-?\d/',
        'fixed' => '/plane|wipeout|neophron|buzzard|blackfish|xian|caesar|black_wasp|gryphon|shikra|a-10|\bf-?\d\d|c-130/',
        'mortar' => '/mortar|mortier|mk6/',
        'artillery' => '/artillery|howitzer|mlrs|himars|sochor|scorcher|sandstorm|zamak_mrl|_arty/',
        'antiair' => '/cheetah|tigris|\baa\b|anti.?air|sam_|rhea|defender|centurion|praetorian|spartan/',
        'armor' => '/mbt|tank|slammer|kuma|varsuk|angara|rhino|abrams|leopard|leclerc|t-?72|t-?80|t-?90|merkava/',
        'mechanized' => '/apc|ifv|marshall|gorgon|mora|marid|kamysh|panther|nyx|bradley|bmp|warrior|btr|vbci|vab/',
        'medical' => '/medical|ambulance|medevac|sanitaire/',
        'maintenance' => '/repair|depann/',
        'support' => '/truck|camion|hemtt|zamak|tempest|kamaz|ammo|fuel|box|van_/',
        'naval' => '/ship|boat|speedboat|sdv|rhib|zodiac/',
        'static' => '/static|hmg_|gmg_|tripod|titan_static/',
        'motorized' => '/hunter|ifrit|strider|prowler|qilin|offroad|quad|mrap|jeep|hmmwv|humvee|vbl|pvp|car_/',
    ];

    /** Rôle écrit par le joueur ou l'ORBAT => genre (à pied seulement). */
    private const ROLE_PATTERNS = [
        'hq' => '/\bhq\b|\bpc\b|commandant|command post|état.?major|etat.?major|\btoc\b/',
        'medical' => '/medic|opsan|sant[ée]|medical|m[ée]decin|infirmier|corpsman/',
        'recon' => '/\brecon\b|scout|\bisr\b|sniper|[ée]claireur/',
        'mortar' => '/mortier|mortar/',
        'artillery' => '/arty|artiller|appui.?feu|fire.?support|\bjtac\b|\bfo\b/',
        'engineer' => '/g[ée]nie|engineer|sapeur|\beod\b|d[ée]mineur/',
        'support' => '/\blog\b|logisti|supply|ravitail/',
        'antiair' => '/sol.?air|anti.?a[ée]rien|manpads/',
    ];

    /** Suffixe d'un marqueur OTAN Arma (b_inf, o_armor, n_air…) => genre. */
    public const NATO_MARKERS = [
        'inf' => 'infantry', 'motor_inf' => 'motorized', 'mech_inf' => 'mechanized', 'armor' => 'armor', 'recon' => 'recon',
        'art' => 'artillery', 'mortar' => 'mortar', 'antiair' => 'antiair', 'support' => 'support', 'service' => 'support',
        'maint' => 'maintenance', 'med' => 'medical', 'hq' => 'hq', 'installation' => 'installation', 'ordnance' => 'support',
        'air' => 'rotary', 'plane' => 'fixed', 'uav' => 'uav', 'naval' => 'naval', 'unknown' => 'unknown',
    ];

    /** Préfixe d'un marqueur OTAN Arma => affiliation (F ami, H hostile, N neutre, U inconnu). */
    public const NATO_PREFIX = ['b' => 'F', 'o' => 'H', 'n' => 'N', 'c' => 'N', 'u' => 'U'];

    /**
     * Type de marqueur Arma sans préfixe (mil_, hd_, loc_, respawn_, flag…) => icône du poste (ICONS de toc-tracks.js).
     * Une entrée par type courant du jeu ; un type absent retombe sur « dot ».
     */
    public const MARKER_ICONS = [
        // mil_* et hd_* (mêmes noms)
        'dot' => 'dot', 'dot_noshadow' => 'dot', 'circle' => 'circle', 'circle_noshadow' => 'circle', 'box' => 'square', 'box_noshadow' => 'square',
        'triangle' => 'triangle', 'triangle_noshadow' => 'triangle', 'objective' => 'objective', 'objective_noshadow' => 'objective',
        'marker' => 'pin', 'marker_noshadow' => 'pin', 'flag' => 'flag', 'flag_noshadow' => 'flag',
        'arrow' => 'arrow', 'arrow_noshadow' => 'arrow', 'arrow2' => 'arrow', 'arrow2_noshadow' => 'arrow',
        'ambush' => 'ambush', 'ambush_noshadow' => 'ambush', 'destroy' => 'destroy', 'destroy_noshadow' => 'destroy',
        'start' => 'start', 'start_noshadow' => 'start', 'end' => 'end', 'end_noshadow' => 'end',
        'pickup' => 'pickup', 'pickup_noshadow' => 'pickup', 'join' => 'join', 'join_noshadow' => 'join',
        'warning' => 'warning', 'warning_noshadow' => 'warning', 'unknown' => 'unknown', 'unknown_noshadow' => 'unknown',
        // Divers du jeu
        'waypoint' => 'waypoint', 'selector_selectable' => 'circle', 'selector_selectedenemy' => 'destroy', 'selector_selectedfriendly' => 'circle',
        'selector_selectedmission' => 'objective', 'kia' => 'kia', 'minefield' => 'mine', 'minefieldap' => 'mine', 'mine' => 'mine',
        'empty' => 'label', 'emptyicon' => 'label', 'select' => 'circle', 'group_0' => 'dot',
        // respawn_*
        'respawn_inf' => 'respawn', 'respawn_unknown' => 'respawn', 'respawn_motor' => 'respawn', 'respawn_armor' => 'respawn',
        'respawn_air' => 'heli', 'respawn_plane' => 'plane', 'respawn_naval' => 'anchor', 'respawn_para' => 'para',
        // loc_* (points d'intérêt)
        'loc_hospital' => 'medical', 'loc_heal' => 'medical', 'loc_fuelstation' => 'fuel', 'loc_refuel' => 'fuel', 'loc_rearm' => 'ammo',
        'loc_repair' => 'repair', 'loc_heli' => 'heli', 'loc_plane' => 'plane', 'loc_boat' => 'anchor', 'loc_quay' => 'anchor',
        'loc_car' => 'vehicle', 'loc_truck' => 'vehicle', 'loc_tower' => 'tower', 'loc_transmitter' => 'tower', 'loc_radio' => 'tower',
        'loc_lighthouse' => 'tower', 'loc_bunker' => 'bunker', 'loc_fortress' => 'bunker', 'loc_church' => 'building', 'loc_chapel' => 'building',
        'loc_ruin' => 'building', 'loc_power' => 'warning', 'loc_powersolar' => 'warning', 'loc_powerwind' => 'warning', 'loc_powerwave' => 'warning',
        'loc_tree' => 'dot', 'loc_smalltree' => 'dot', 'loc_bush' => 'dot', 'loc_rock' => 'dot', 'loc_stack' => 'dot', 'loc_cross' => 'dot',
        'loc_fountain' => 'dot', 'loc_busstop' => 'dot', 'loc_viewtower' => 'tower', 'loc_watertower' => 'tower', 'loc_tourism' => 'dot',
        'loc_bridge' => 'square', 'loc_container' => 'square', 'loc_letter' => 'label', 'loc_food' => 'ammo', 'loc_backpack' => 'ammo',
        'loc_attack' => 'destroy', 'loc_defend' => 'bunker', 'loc_move' => 'arrow', 'loc_scout' => 'eye', 'loc_search' => 'eye',
        'loc_destroy' => 'destroy', 'loc_download' => 'pickup', 'loc_upload' => 'pickup', 'loc_interact' => 'circle', 'loc_talk' => 'circle',
        'loc_use' => 'circle', 'loc_wait' => 'circle', 'loc_takeoff' => 'plane', 'loc_land' => 'heli', 'loc_getin' => 'pickup',
        'loc_getout' => 'pickup', 'loc_support' => 'ammo', 'loc_target' => 'objective', 'loc_kill' => 'destroy', 'loc_mine' => 'mine',
        'loc_danger' => 'warning', 'loc_exit' => 'end', 'loc_run' => 'arrow', 'loc_walk' => 'arrow', 'loc_meet' => 'join', 'loc_radar' => 'eye',
        // Contacts dessinés
        'contact_dot1' => 'dot', 'contact_dot2' => 'dot', 'contact_dot3' => 'dot', 'contact_dot4' => 'dot', 'contact_dot5' => 'dot',
        'contact_circle1' => 'circle', 'contact_circle2' => 'circle', 'contact_circle3' => 'circle', 'contact_circle4' => 'circle',
        'contact_arrow1' => 'arrow', 'contact_arrow2' => 'arrow', 'contact_arrow3' => 'arrow', 'contact_arrowsmall1' => 'arrow',
        'contact_arrowsmall2' => 'arrow', 'contact_pencilcircle1' => 'circle', 'contact_pencilcircle2' => 'circle', 'contact_pencildot1' => 'dot',
    ];

    /** Icônes dessinées par toc-tracks.js (vérifié par AtakTocTracksTest). */
    public const ICONS = [
        'dot', 'circle', 'square', 'triangle', 'objective', 'pin', 'flag', 'arrow', 'ambush', 'destroy', 'start', 'end', 'pickup', 'join',
        'warning', 'unknown', 'waypoint', 'kia', 'mine', 'label', 'respawn', 'heli', 'plane', 'anchor', 'para', 'medical', 'fuel', 'ammo',
        'repair', 'vehicle', 'tower', 'bunker', 'building', 'eye', 'relay', 'photo', 'report', 'request', 'order', 'rally', 'checkpoint',
    ];

    public static function kind(string $kind): string
    {
        return isset(self::KINDS[$kind]) ? $kind : 'infantry';
    }

    /**
     * Genre d'une piste : la plateforme ou le véhicule d'abord, le métier seulement à pied.
     *
     * @param array<string, mixed> $h platform, vehicle_class, vehicle (nom), aircraft_type, in_vehicle, role
     */
    public static function kindFor(array $h): string
    {
        foreach (['platform', 'vehicle_class', 'aircraft_type'] as $k) {
            $p = strtoupper(preg_replace('/[\s-]+/', '_', trim((string) ($h[$k] ?? ''))) ?? '');
            if ($p !== '' && isset(self::PLATFORMS[$p]) && !($p === 'GROUND_VEHICLE' || $p === 'VEHICLE')) {
                return self::PLATFORMS[$p];
            }
        }
        $name = strtolower(trim((string) ($h['vehicle'] ?? '')));
        if ($name !== '') {
            foreach (self::NAME_PATTERNS as $kind => $re) {
                if (preg_match($re, $name) === 1) {
                    return $kind;
                }
            }
        }
        $generic = strtoupper(trim((string) ($h['platform'] ?? $h['vehicle_class'] ?? '')));
        $in = $h['in_vehicle'] ?? false;
        if ($generic === 'GROUND_VEHICLE' || $generic === 'VEHICLE' || $in === true || $in === 1 || $in === '1' || $in === 'true') {
            return 'motorized';
        }
        $role = mb_strtolower(trim((string) ($h['role'] ?? '')));
        if ($role !== '') {
            foreach (self::ROLE_PATTERNS as $kind => $re) {
                if (preg_match($re . 'u', $role) === 1) {
                    return $kind;
                }
            }
        }

        return 'infantry';
    }

    /** SIDC 2525C (15 caractères) : S + affiliation + dimension + P (présent) + fonction. */
    public static function sidc(string $kind, string $affil = 'F'): string
    {
        [$fn, $dim] = self::KINDS[self::kind($kind)];
        $a = in_array($affil, ['F', 'H', 'N', 'U'], true) ? $affil : 'F';

        return 'S' . $a . $dim . 'P' . $fn . '-----';
    }

    public static function isAir(string $kind): bool
    {
        return (self::KINDS[$kind][1] ?? 'G') === 'A';
    }

    /**
     * Marqueur Arma => icône du poste, ou symbole OTAN (genre + affiliation) pour b_/o_/n_/c_/u_.
     *
     * @return array{icon: string, nato: string, affil: string}
     */
    public static function marker(string $type): array
    {
        $t = strtolower(trim($type));
        $t = preg_replace('/[\s-]+/', '_', $t) ?? $t;
        if (preg_match('/^([boncu])_(.+)$/', $t, $m) === 1) {
            $role = preg_replace('/_ca$/', '', $m[2]) ?? $m[2];
            if (isset(self::NATO_MARKERS[$role])) {
                return ['icon' => '', 'nato' => self::NATO_MARKERS[$role], 'affil' => self::NATO_PREFIX[$m[1]]];
            }
        }
        if (str_starts_with($t, 'flag')) {
            return ['icon' => 'flag', 'nato' => '', 'affil' => ''];
        }
        $bare = preg_replace('/^(mil|hd|mplus|mts)_/', '', $t) ?? $t;
        $icon = self::MARKER_ICONS[$t] ?? self::MARKER_ICONS[$bare] ?? null;
        if ($icon === null && str_starts_with($t, 'respawn_')) {
            $icon = 'respawn';
        }

        return ['icon' => $icon ?? 'dot', 'nato' => '', 'affil' => ''];
    }

    /**
     * Tables pour le navigateur (window.TOC_BOOT.symbols).
     *
     * @return array<string, mixed>
     */
    public static function forClient(): array
    {
        $kinds = [];
        foreach (self::KINDS as $k => [$fn, $dim, $label]) {
            $kinds[$k] = ['fn' => $fn, 'dim' => $dim, 'label' => $label];
        }

        return [
            'kinds' => $kinds,
            'platforms' => self::PLATFORMS,
            'natoMarkers' => self::NATO_MARKERS,
            'natoPrefix' => self::NATO_PREFIX,
            'markerIcons' => self::MARKER_ICONS,
        ];
    }
}
