<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\AtakDistanceRepository;

/**
 * Flotte aérienne d'une communauté : à quel appareil et à quelle famille (transport, attaque, reconnaissance, avion à
 * réaction, cargo, drone…) appartient une classe Arma relevée par le mod.
 *
 * Sans réglage, l'appareil est celui reconnu par le site (AtakDistanceRepository::aircraftFamily : UH-60, AH-64,
 * C-130… ou la classe elle-même) et la famille est déduite de l'appareil connu, sinon de la classe et du nom affiché.
 * La communauté peut créer ses propres appareils (plusieurs classes Arma chacun), renommer ou ranger autrement un
 * appareil reconnu, et créer ses propres familles.
 *
 * Périmètres d'heures produits pour une classe (PilotQualificationEngine::scopeTotals) : « kind:heli|plane »,
 * « family:<appareil reconnu> » (toujours, pour que les cursus déjà installés gardent leurs heures),
 * « family:<appareil de la communauté> » quand la classe y est rattachée, et « group:<famille> ». Pur : testable seul.
 */
final class PilotFleet
{
    public const TRACK_OTHER = 'other';

    /** Familles proposées d'office : clé => libellé. */
    public const DEFAULT_GROUPS = [
        'transport' => 'Transport',
        'attack' => 'Attaque',
        'recon' => 'Reconnaissance et léger',
        'jet' => 'Avion à réaction',
        'cargo' => 'Cargo et ravitaillement',
        'drone' => 'Drone',
        'other' => 'Autre',
    ];

    /** Famille des appareils reconnus par le site. */
    private const BUILTIN_GROUP = [
        'ch47' => 'transport', 'ch53' => 'transport', 'uh60' => 'transport', 'uh1' => 'transport', 'v22' => 'transport',
        'mi8' => 'transport', 'ah64' => 'attack', 'ah1' => 'attack', 'mi24' => 'attack', 'mi28' => 'attack',
        'ka52' => 'attack', 'mh6' => 'recon', 'c130' => 'cargo', 'a10' => 'attack', 'su25' => 'attack',
        'f16' => 'jet', 'fa18' => 'jet', 'f35' => 'jet',
    ];

    /** Mots de la classe ou du nom qui trahissent la famille, dans l'ordre d'essai. */
    private const GROUP_HINTS = [
        'drone' => '/uav|ucav|drone|(?<![a-z])uas(?![a-z])|mq_?\d|reaper|predator|greyhawk|ababil|yabhon|sentinel|darter|pelican|orlan|shahed|falcon_uav/',
        'jet' => '/fighter|(?<![a-z])jet|mig_?\d|(?<![a-z])su_?(27|30|33|34|35|57)|gripen|rafale|typhoon|eurofighter|tornado|harrier|av_?8|mirage|f_?(14|15|22)(?![0-9])|black_?wasp|shikra|gryphon/',
        'cargo' => '/cargo|tanker|(?<![a-z])kc_?\d|c_?17(?![0-9])|(?<![a-z])an_?\d|il_?76|blackfish|vtol_01|y_?32|plane_transport|transport_plane/',
        'attack' => '/attack|gunship|(?<![a-z])cas(?![a-z])|plane_cas|armed|kajman|orca_armed|wipeout|neophron|buzzard/',
        'transport' => '/transport|lift|medevac|huron|taru|mohawk|orca|merlin|nh_?90|puma|cougar|caracal|lynx|wildcat/',
        'recon' => '/recon|scout|observ|light|littlebird|hummingbird|pawnee|hellcat|gazelle|bo_?105|cessna|caesar/',
    ];

    /**
     * @param array<string, array{name: string, sort: int, archived: bool}> $groups familles de la communauté (créées ou renommées)
     * @param array<string, array<string, mixed>> $aircraft fiches d'appareils de la communauté par clé (name, track, group_key, image_path, archived_at, is_custom)
     * @param array<string, string> $classMap classe Arma en minuscules => clé d'appareil de la communauté
     */
    public function __construct(
        private array $groups = [],
        private array $aircraft = [],
        private array $classMap = [],
    ) {
    }

    /** @var array<int, array<string, string>> */
    private array $groupCache = [];

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Familles proposées (d'office puis de la communauté), dans l'ordre : clé => libellé.
     *
     * @return array<string, string>
     */
    public function groups(bool $withArchived = false): array
    {
        if (isset($this->groupCache[(int) $withArchived])) {
            return $this->groupCache[(int) $withArchived];
        }
        $rows = [];
        $i = 0;
        foreach (self::DEFAULT_GROUPS as $key => $label) {
            $rows[$key] = ['name' => $label, 'sort' => $i++ * 10, 'archived' => false];
        }
        foreach ($this->groups as $key => $g) {
            $rows[$key] = [
                'name' => trim((string) $g['name']) !== '' ? (string) $g['name'] : ($rows[$key]['name'] ?? $key),
                'sort' => (int) $g['sort'],
                'archived' => !empty($g['archived']),
            ];
        }
        uasort($rows, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort'] ?: strcmp($a['name'], $b['name']));
        $out = [];
        foreach ($rows as $key => $g) {
            if ($withArchived || !$g['archived']) {
                $out[(string) $key] = $g['name'];
            }
        }

        return $this->groupCache[(int) $withArchived] = $out;
    }

    public function groupLabel(string $key): string
    {
        return $this->groups(true)[$key] ?? (self::DEFAULT_GROUPS[$key] ?? $key);
    }

    public function isCustomGroup(string $key): bool
    {
        return isset($this->groups[$key]) && !isset(self::DEFAULT_GROUPS[$key]);
    }

    /** Famille devinée d'après la classe et le nom affiché (appareil inconnu du site). */
    public static function guessGroup(string $class, string $label = ''): string
    {
        $hay = strtolower(str_replace(['-', ' ', '/'], '_', $class . '_' . $label));
        foreach (self::GROUP_HINTS as $group => $re) {
            if (preg_match($re, $hay) === 1) {
                return $group;
            }
        }

        return 'other';
    }

    /** Famille d'office d'un appareil reconnu par le site (null : inconnu). */
    public static function builtinGroup(string $key): ?string
    {
        return self::BUILTIN_GROUP[$key] ?? null;
    }

    /** @return array<string, mixed>|null fiche active de la communauté */
    public function aircraftRow(string $key): ?array
    {
        $row = $this->aircraft[$key] ?? null;

        return is_array($row) && empty($row['archived_at']) ? $row : null;
    }

    /** @return array<string, array<string, mixed>> fiches de la communauté (archivées comprises) */
    public function aircraftRows(): array
    {
        return $this->aircraft;
    }

    /** @return array<string, string> classe en minuscules => clé d'appareil */
    public function classMap(): array
    {
        return $this->classMap;
    }

    /** @return list<string> classes Arma rattachées à un appareil de la communauté */
    public function classesOf(string $key): array
    {
        return array_values(array_map('strval', array_keys(array_filter($this->classMap, static fn (string $k): bool => $k === $key))));
    }

    /**
     * Appareil d'une classe relevée en jeu : clé retenue (appareil de la communauté si la classe y est rattachée),
     * clé reconnue par le site, libellé, famille et filière.
     *
     * @return array{key: string, builtin_key: string, label: string, group: string, track: string}
     */
    public function resolve(string $class, string $label = '', string $kind = ''): array
    {
        $builtin = AtakDistanceRepository::aircraftFamily($class, $label);
        $mapped = $this->classMap[strtolower(trim($class))] ?? null;
        if ($mapped !== null && $this->aircraftRow($mapped) === null) {
            $mapped = null;
        }
        $key = $mapped ?? $builtin['key'];
        $row = $this->aircraftRow($key);
        $name = trim((string) ($row['name'] ?? ''));
        $group = trim((string) ($row['group_key'] ?? ''));
        $active = $this->groups();
        if ($group === '' || !isset($active[$group])) {
            $group = self::builtinGroup($builtin['key']) ?? self::guessGroup($class, $label);
            $group = isset($active[$group]) ? $group : 'other';
        }

        return [
            'key' => $key,
            'builtin_key' => $builtin['key'],
            'label' => $name !== '' ? $name : $builtin['label'],
            'group' => $group,
            'track' => $this->trackOf($key, $builtin['key'], $kind),
        ];
    }

    /** Filière d'un appareil : réglée sur sa fiche, sinon celle de l'appareil reconnu, sinon le genre relevé par le mod. */
    public function trackOf(string $key, string $builtinKey = '', string $kind = ''): string
    {
        $track = (string) ($this->aircraftRow($key)['track'] ?? '');
        if (in_array($track, [PilotSyllabusCatalog::TRACK_HELI, PilotSyllabusCatalog::TRACK_PLANE, self::TRACK_OTHER], true)) {
            return $track;
        }

        return PilotSyllabusCatalog::familyTrack($builtinKey !== '' ? $builtinKey : $key, $kind);
    }

    /**
     * Périmètres d'heures auxquels compte une classe pilotée.
     *
     * @return list<string>
     */
    public function scopes(string $class, string $label, string $kind): array
    {
        $r = $this->resolve($class, $label, $kind);
        $out = ['kind:' . ($kind === 'plane' ? 'plane' : 'heli'), 'family:' . $r['builtin_key']];
        if ($r['key'] !== $r['builtin_key']) {
            $out[] = 'family:' . $r['key'];
        }
        $out[] = 'group:' . $r['group'];

        return $out;
    }

    /** Clé d'un nouvel appareil de la communauté d'après son nom : « ac_tigre_had ». */
    public static function customKey(string $name): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', self::ascii($name)));
        $slug = trim(substr($slug, 0, 40), '_');

        return 'ac_' . ($slug !== '' ? $slug : 'appareil');
    }

    /** Clé d'une nouvelle famille d'après son nom : « g_evasan ». */
    public static function groupKey(string $name): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', self::ascii($name)));
        $slug = trim(substr($slug, 0, 30), '_');

        return 'g_' . ($slug !== '' ? $slug : 'famille');
    }

    /** Clé d'appareil acceptée (reconnu, classe relevée, ou appareil de la communauté). */
    public static function validKey(string $key): bool
    {
        return preg_match('/^(class:[a-z0-9_]{1,80}|[a-z0-9_]{1,80})$/', $key) === 1;
    }

    private static function ascii(string $s): string
    {
        $t = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) : false;

        return is_string($t) ? $t : $s;
    }
}
