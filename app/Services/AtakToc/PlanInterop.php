<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Échange d'un plan avec d'autres outils : GeoJSON et KML 2.2, à l'aller comme au retour.
 *
 * Export : un objet par élément montré (ni masqué, ni unité pas encore posée). Les coordonnées restent des mètres
 * Arma [x, y] (y vers le nord) : le fichier le dit (« athena.crs », « athena:crs ») et chaque objet emporte l'élément
 * complet, pour qu'un réimport dans Athena ne perde rien.
 * Import : nos fichiers sont relus sans perte ; un fichier étranger donne des repères (Point), des traits (LineString)
 * et des polygones (Polygon, contour extérieur). Si toutes ses coordonnées tiennent dans longitude [-180, 180] et
 * latitude [-90, 90], ce sont des degrés : elles sont ramenées dans un cadre en mètres (proportions gardées, nord en
 * haut, correction cos(latitude)) ; sinon elles sont lues comme des mètres. Tout élément repasse par
 * PlanValidator::element et reçoit un nouvel identifiant ; rien n'est enregistré ici.
 * KMZ (KML compressé) : non pris en charge.
 */
final class PlanInterop
{
    public const GEOJSON_FORMAT = 'athena-plan-geojson';
    public const KML_FORMAT = 'athena-plan-kml';
    public const CRS = 'arma-metres';

    /** Cadre par défaut des coordonnées en degrés quand la taille du terrain n'est pas connue (mètres). */
    public const DEFAULT_FIT = [0.0, 0.0, 10000.0, 10000.0];

    /** Lettre d'échelon du SIDC 2525C (même table que toc-geo.js). */
    private const ECHELON_CODE = ['team' => 'A', 'squad' => 'B', 'section' => 'C', 'platoon' => 'D', 'company' => 'E', 'battalion' => 'F', 'regiment' => 'G', 'brigade' => 'H'];

    /**
     * SIDC 2525C (15 caractères) d'une unité : surcharge saisie, sinon affiliation, fonction et échelon
     * (même règle que TocGeo.sidc de l'éditeur).
     *
     * @param array<string, mixed> $props
     */
    public static function sidc(array $props): string
    {
        $over = is_string($props['sidc'] ?? null) ? $props['sidc'] : '';
        if (preg_match('/^[A-Z0-9*-]{10,30}$/', $over) === 1) {
            return $over;
        }
        $aff = is_string($props['affil'] ?? null) && in_array($props['affil'], PlanCatalog::AFFILIATIONS, true) ? $props['affil'] : 'F';
        $type = is_string($props['unitType'] ?? null) ? $props['unitType'] : '';
        $fn = PlanCatalog::UNIT_TYPES[$type][1] ?? '------';
        $ech = self::ECHELON_CODE[is_string($props['echelon'] ?? null) ? $props['echelon'] : ''] ?? '-';

        return 'S' . $aff . 'GP' . $fn . '-' . $ech . '---';
    }

    /**
     * Éléments exportés d'un document (PlanService::document) : ni masqués, ni « non posés ».
     *
     * @param array<string, mixed> $doc
     * @return list<array<string, mixed>>
     */
    public static function exportable(array $doc): array
    {
        $out = [];
        foreach (is_array($doc['elements'] ?? null) ? $doc['elements'] : [] as $el) {
            if (is_array($el) && isset($el['kind'], $el['geom'], $el['pts'], $el['props']) && is_array($el['pts']) && $el['pts'] !== [] && is_array($el['props']) && PlanTimeline::placed($el)) {
                $out[] = $el;
            }
        }

        return $out;
    }

    /**
     * Géométrie d'échange d'un élément : [type GeoJSON, sommets]. Cercle, ellipse, rectangle et éventail en polygone.
     *
     * @param array<string, mixed> $el
     * @return array{0: string, 1: list<array{0: float, 1: float}>}
     */
    private static function shape(array $el): array
    {
        $num = static fn (array $q): array => [(float) $q[0], (float) $q[1]];
        if ($el['kind'] === 'fan') {
            return ['Polygon', array_map($num, PlanGameMapper::fanOutline($el, 48))];
        }

        return match ($el['geom']) {
            'point' => ['Point', [$num($el['pts'][0])]],
            'line' => ['LineString', array_map($num, array_values($el['pts']))],
            default => ['Polygon', array_map($num, PlanGameMapper::outline($el, 48))],
        };
    }

    /** @param array<string, mixed> $el */
    private static function title(array $el): string
    {
        $p = $el['props'];
        $callsign = trim((string) ($p['callsign'] ?? ''));

        return $callsign !== '' ? $callsign : trim((string) ($p['label'] ?? ''));
    }

    /**
     * Plan en GeoJSON (FeatureCollection). Coordonnées : mètres Arma [x, y], annoncées par « athena.crs ».
     *
     * @param array<string, mixed> $doc document du plan (PlanService::document)
     * @return array<string, mixed>
     */
    public static function toGeoJson(array $doc): array
    {
        $features = [];
        foreach (self::exportable($doc) as $el) {
            [$type, $pts] = self::shape($el);
            if ($type === 'Polygon') {
                $pts[] = $pts[0];
            }
            $p = $el['props'];
            $color = PlanValidator::color($p['color'] ?? null, '#f2f2f2');
            $props = [
                'kind' => $el['kind'],
                'geom' => $el['geom'],
                'name' => self::title($el),
                'label' => (string) ($p['label'] ?? ''),
                'color' => $color,
            ];
            if ($el['kind'] === 'unit') {
                $props['callsign'] = (string) ($p['callsign'] ?? '');
                $props['sidc'] = self::sidc($p);
            }
            // Style « simplestyle » : lu par la plupart des visionneuses GeoJSON.
            if ($type === 'Point') {
                $props['marker-color'] = $color;
            } else {
                $props['stroke'] = $color;
                $props['stroke-width'] = (int) ($p['width'] ?? 2);
                if ($type === 'Polygon') {
                    $props['fill'] = $color;
                    $props['fill-opacity'] = (float) ($p['fill'] ?? 0);
                }
            }
            $props['athena'] = $el;
            $features[] = [
                'type' => 'Feature',
                'id' => (string) ($el['uid'] ?? ''),
                'geometry' => ['type' => $type, 'coordinates' => match ($type) {
                    'Point' => $pts[0],
                    'LineString' => $pts,
                    default => [$pts],
                }],
                'properties' => $props,
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'athena' => [
                'format' => self::GEOJSON_FORMAT,
                'version' => 1,
                'crs' => self::CRS,
                'world' => (string) ($doc['world'] ?? ''),
                'title' => (string) ($doc['title'] ?? ''),
            ],
            'features' => $features,
        ];
    }

    /**
     * GeoJSON => éléments prêts à ajouter au plan (nouveaux identifiants). Retour : [éléments, refusés, raison] ;
     * raison non vide = fichier refusé en entier (import_too_large, import_format, import_too_many).
     *
     * @param array<mixed>|null $fit cadre [x0, y0, x1, y1] (mètres) où ramener des coordonnées en degrés
     * @param list<string> $phaseUids phases du plan qui reçoit (les autres sont retirées des éléments)
     * @return array{0: list<array<string, mixed>>, 1: int, 2: string}
     */
    public static function fromGeoJson(string $json, ?array $fit = null, array $phaseUids = []): array
    {
        if (strlen($json) > PlanValidator::MAX_IMPORT_BYTES) {
            return [[], 0, 'import_too_large'];
        }
        $doc = json_decode($json, true, 32);
        $types = ['FeatureCollection', 'Feature', 'GeometryCollection', 'Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon'];
        if (!is_array($doc) || !in_array($doc['type'] ?? null, $types, true)) {
            return [[], 0, 'import_format'];
        }
        $ours = is_array($doc['athena'] ?? null) && ($doc['athena']['format'] ?? '') === self::GEOJSON_FORMAT;
        $raws = [];
        $shapes = [];
        $bad = 0;
        self::collect($doc, [], $ours, $raws, $shapes, $bad);

        return self::build($raws, $shapes, $bad, $ours, $fit, $phaseUids);
    }

    /**
     * Parcours d'un GeoJSON : éléments Athena embarqués ($raws) et formes étrangères ($shapes).
     *
     * @param array<mixed> $node
     * @param array<mixed> $props
     * @param list<mixed> $raws
     * @param list<array{type: string, pts: list<array{0: float, 1: float}>, props: array<mixed>}> $shapes
     */
    private static function collect(array $node, array $props, bool $ours, array &$raws, array &$shapes, int &$bad, int $depth = 0): void
    {
        if ($depth > 6 || count($raws) + count($shapes) > PlanValidator::MAX_ELEMENTS) {
            return;
        }
        $type = $node['type'] ?? null;
        $coords = $node['coordinates'] ?? null;
        $add = static function (string $kind, mixed $list) use (&$shapes, &$bad, $props): void {
            $pts = self::coords($list);
            if ($pts === null) {
                $bad++;

                return;
            }
            $shapes[] = ['type' => $kind, 'pts' => $pts, 'props' => $props];
        };
        $each = static function (mixed $list, callable $fn) use (&$bad): void {
            if (!is_array($list)) {
                $bad++;

                return;
            }
            foreach ($list as $item) {
                $fn($item);
            }
        };
        switch ($type) {
            case 'FeatureCollection':
                foreach (is_array($node['features'] ?? null) ? $node['features'] : [] as $f) {
                    if (is_array($f)) {
                        self::collect($f, [], $ours, $raws, $shapes, $bad, $depth + 1);
                    }
                }
                break;
            case 'Feature':
                $fp = is_array($node['properties'] ?? null) ? $node['properties'] : [];
                if ($ours && is_array($fp['athena'] ?? null)) {
                    $raws[] = $fp['athena'];
                } elseif (is_array($node['geometry'] ?? null)) {
                    self::collect($node['geometry'], $fp, $ours, $raws, $shapes, $bad, $depth + 1);
                } else {
                    $bad++;
                }
                break;
            case 'GeometryCollection':
                foreach (is_array($node['geometries'] ?? null) ? $node['geometries'] : [] as $g) {
                    if (is_array($g)) {
                        self::collect($g, $props, $ours, $raws, $shapes, $bad, $depth + 1);
                    }
                }
                break;
            case 'Point':
                $add('point', [$coords]);
                break;
            case 'MultiPoint':
                $each($coords, static fn (mixed $c) => $add('point', [$c]));
                break;
            case 'LineString':
                $add('line', $coords);
                break;
            case 'MultiLineString':
                $each($coords, static fn (mixed $c) => $add('line', $c));
                break;
            case 'Polygon':
                $add('area', is_array($coords) ? ($coords[0] ?? null) : null);
                break;
            case 'MultiPolygon':
                $each($coords, static fn (mixed $c) => $add('area', is_array($c) ? ($c[0] ?? null) : null));
                break;
            default:
                $bad++;
        }
    }

    /**
     * Liste de positions [x, y, …] en nombres finis, ou null si l'une est invalide ou si la liste est vide.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private static function coords(mixed $list): ?array
    {
        if (!is_array($list) || $list === [] || count($list) > 200000) {
            return null;
        }
        $out = [];
        foreach ($list as $c) {
            if (!is_array($c) || !isset($c[0], $c[1]) || !is_numeric($c[0]) || !is_numeric($c[1]) || !is_finite((float) $c[0]) || !is_finite((float) $c[1])) {
                return null;
            }
            $out[] = [(float) $c[0], (float) $c[1]];
        }

        return $out;
    }

    /**
     * Cadre [x0, y0, x1, y1] en mètres, borné au terrain ; le cadre par défaut si la valeur reçue est invalide.
     *
     * @param array<mixed>|null $fit
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function fitBox(?array $fit): array
    {
        $fit = is_array($fit) ? array_values($fit) : [];
        if (count($fit) !== 4) {
            return self::DEFAULT_FIT;
        }
        $v = [];
        foreach ($fit as $n) {
            $c = PlanValidator::number($n, PlanValidator::COORD_MIN, PlanValidator::COORD_MAX);
            if ($c === null) {
                return self::DEFAULT_FIT;
            }
            $v[] = $c;
        }
        $box = [min($v[0], $v[2]), min($v[1], $v[3]), max($v[0], $v[2]), max($v[1], $v[3])];

        return ($box[2] - $box[0] >= 1.0 && $box[3] - $box[1] >= 1.0) ? $box : self::DEFAULT_FIT;
    }

    /**
     * Éléments Athena embarqués et formes étrangères => éléments validés. Formes en degrés (toutes dans
     * [-180, 180] × [-90, 90], fichier étranger) : ramenées dans le cadre ; sinon lues en mètres.
     *
     * @param list<mixed> $raws
     * @param list<array{type: string, pts: list<array{0: float, 1: float}>, props: array<mixed>}> $shapes
     * @param array<mixed>|null $fit
     * @param list<string> $phaseUids
     * @return array{0: list<array<string, mixed>>, 1: int, 2: string}
     */
    private static function build(array $raws, array $shapes, int $bad, bool $ours, ?array $fit, array $phaseUids): array
    {
        if (count($raws) + count($shapes) > PlanValidator::MAX_ELEMENTS) {
            return [[], 0, 'import_too_many'];
        }
        if (!$ours && $shapes !== []) {
            $degrees = true;
            $lon0 = $lat0 = INF;
            $lon1 = $lat1 = -INF;
            foreach ($shapes as $s) {
                foreach ($s['pts'] as [$x, $y]) {
                    if ($x < -180.0 || $x > 180.0 || $y < -90.0 || $y > 90.0) {
                        $degrees = false;
                        break 2;
                    }
                    $lon0 = min($lon0, $x);
                    $lon1 = max($lon1, $x);
                    $lat0 = min($lat0, $y);
                    $lat1 = max($lat1, $y);
                }
            }
            if ($degrees) {
                [$x0, $y0, $x1, $y1] = self::fitBox($fit);
                $lonMid = ($lon0 + $lon1) / 2;
                $latMid = ($lat0 + $lat1) / 2;
                $k = max(0.01, cos(deg2rad($latMid)));
                $w = ($lon1 - $lon0) * $k;
                $h = $lat1 - $lat0;
                $scale = 0.0;
                if ($w > 0.0 && $h > 0.0) {
                    $scale = min(($x1 - $x0) / $w, ($y1 - $y0) / $h);
                } elseif ($w > 0.0) {
                    $scale = ($x1 - $x0) / $w;
                } elseif ($h > 0.0) {
                    $scale = ($y1 - $y0) / $h;
                }
                $cx = ($x0 + $x1) / 2;
                $cy = ($y0 + $y1) / 2;
                foreach ($shapes as $i => $s) {
                    foreach ($s['pts'] as $j => [$x, $y]) {
                        $shapes[$i]['pts'][$j] = [$cx + ($x - $lonMid) * $k * $scale, $cy + ($y - $latMid) * $scale];
                    }
                }
            }
        }
        foreach ($shapes as $s) {
            $raws[] = self::foreign($s);
        }
        [$elements, $rejected] = self::reissue($raws, $phaseUids);

        return [$elements, $rejected + $bad, ''];
    }

    /**
     * Forme étrangère => élément brut : repère (Point), trait (LineString), polygone (Polygon). Nom, note et couleur
     * repris des propriétés usuelles (name, title, description, stroke, marker-color…).
     *
     * @param array{type: string, pts: list<array{0: float, 1: float}>, props: array<mixed>} $s
     * @return array<string, mixed>|null
     */
    private static function foreign(array $s): ?array
    {
        $pts = $s['pts'];
        $fp = $s['props'];
        if ($s['type'] === 'area') {
            if (count($pts) > 1 && $pts[0] === $pts[count($pts) - 1]) {
                array_pop($pts);
            }
            if (count($pts) < 3) {
                return null;
            }
        }
        if ($s['type'] === 'line' && count($pts) < 2) {
            return null;
        }
        if (count($pts) > PlanValidator::MAX_POINTS) {
            $pts = PlanGameMapper::simplify($pts, PlanValidator::MAX_POINTS);
        }
        $props = ['label' => self::scalar($fp['name'] ?? $fp['title'] ?? $fp['label'] ?? ''), 'note' => self::scalar($fp['description'] ?? '')];
        foreach (['stroke', 'marker-color', 'color', 'fill'] as $k) {
            $hex = self::hex($fp[$k] ?? null);
            if ($hex !== null) {
                $props['color'] = $hex;
                break;
            }
        }
        if (is_numeric($fp['stroke-width'] ?? null)) {
            $props['width'] = (int) round((float) $fp['stroke-width']);
        }

        return match ($s['type']) {
            'point' => ['kind' => 'icon', 'geom' => 'point', 'pts' => [$pts[0]], 'props' => $props],
            'line' => ['kind' => 'line', 'geom' => 'line', 'pts' => $pts, 'props' => $props],
            default => ['kind' => 'polygon', 'geom' => 'area', 'pts' => $pts, 'props' => $props],
        };
    }

    private static function scalar(mixed $v): string
    {
        return is_scalar($v) ? trim(strip_tags((string) $v)) : '';
    }

    /** Couleur #rgb ou #rrggbb => #rrggbb en minuscules, sinon null. */
    private static function hex(mixed $v): ?string
    {
        $s = is_string($v) ? strtolower(trim($v)) : '';
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $s, $m) === 1) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $s) === 1 ? $s : null;
    }

    public static function newUid(): string
    {
        return 'i_' . bin2hex(random_bytes(8));
    }

    /**
     * Éléments bruts => éléments validés sous de nouveaux identifiants. Les renvois internes (itinéraire suivi, unité
     * mère, groupe) suivent les nouveaux identifiants ; un renvoi vers un élément absent du lot est retiré.
     *
     * @param list<mixed> $raws
     * @param list<string> $phaseUids
     * @return array{0: list<array<string, mixed>>, 1: int} [éléments, refusés]
     */
    public static function reissue(array $raws, array $phaseUids = []): array
    {
        $map = [];
        foreach ($raws as $raw) {
            $old = is_array($raw) ? PlanValidator::uid($raw['uid'] ?? null) : null;
            if ($old !== null && !isset($map[$old])) {
                $map[$old] = self::newUid();
            }
        }
        $groups = [];
        $used = [];
        $final = [];
        $rejected = 0;
        foreach ($raws as $raw) {
            if (!is_array($raw)) {
                $rejected++;
                continue;
            }
            $old = PlanValidator::uid($raw['uid'] ?? null);
            // Deux éléments du lot sous le même identifiant : le second en reçoit un autre.
            $raw['uid'] = $old !== null && !isset($used[$old]) ? $map[$old] : self::newUid();
            if ($old !== null) {
                $used[$old] = true;
            }
            if (is_array($raw['props'] ?? null)) {
                foreach (['route', 'parent'] as $k) {
                    if (array_key_exists($k, $raw['props'])) {
                        $ref = PlanValidator::uid($raw['props'][$k]);
                        $raw['props'][$k] = $ref !== null ? ($map[$ref] ?? '') : '';
                    }
                }
            }
            $g = PlanValidator::uid($raw['group'] ?? null);
            if ($g !== null) {
                $raw['group'] = $groups[$g] ??= self::newUid();
            }
            [$el] = PlanValidator::element($raw, $phaseUids);
            if ($el === null) {
                $rejected++;
                continue;
            }
            $final[] = $el;
        }

        return [$final, $rejected];
    }

    /**
     * Export JSON du site (format athena-plan, version 1) => éléments prêts à ajouter à un plan existant : nouveaux
     * identifiants, phases gardées seulement si elles existent dans le plan qui reçoit.
     *
     * @param list<string> $phaseUids
     * @return array{0: list<array<string, mixed>>, 1: int, 2: string}
     */
    public static function fromAthena(string $json, array $phaseUids = []): array
    {
        [$doc, $why] = PlanValidator::importDocument($json);
        if ($doc === null) {
            return [[], 0, $why];
        }
        [$elements, $rejected] = self::reissue($doc['elements'], $phaseUids);

        return [$elements, $rejected + $doc['rejected'], ''];
    }

    private static function xml(mixed $v): string
    {
        $s = is_scalar($v) ? (string) $v : '';
        // Caractères interdits en XML 1.0 retirés, puis échappement.
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Couleur KML aabbggrr d'une couleur #rrggbb et d'une opacité 0..1. */
    public static function kmlColor(string $hex, float $alpha = 1.0): string
    {
        $hex = PlanValidator::color($hex, '#f2f2f2');

        return sprintf('%02x', (int) round(max(0.0, min(1.0, $alpha)) * 255)) . substr($hex, 5, 2) . substr($hex, 3, 2) . substr($hex, 1, 2);
    }

    /**
     * Plan en KML 2.2. Les coordonnées sont des mètres Arma écrits « x,y,0 » (pas des degrés) : le document le dit
     * (description et ExtendedData « athena:crs »), et chaque Placemark emporte l'élément complet (athena:element).
     *
     * @param array<string, mixed> $doc document du plan (PlanService::document)
     */
    public static function toKml(array $doc): string
    {
        $data = static fn (string $name, string $value): string => '<Data name="' . self::xml($name) . '"><value>' . self::xml($value) . '</value></Data>';
        $coord = static fn (array $q): string => rtrim(rtrim(sprintf('%.1F', $q[0]), '0'), '.') . ',' . rtrim(rtrim(sprintf('%.1F', $q[1]), '0'), '.') . ',0';
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<kml xmlns="http://www.opengis.net/kml/2.2">' . "\n<Document>\n"
            . '<name>' . self::xml($doc['title'] ?? 'Plan') . "</name>\n"
            . '<description>' . self::xml('Plan d’opération Athena. Attention : les coordonnées de ce fichier sont des mètres du terrain Arma (x, y), pas des longitudes et latitudes. À réimporter dans Athena ; un autre logiciel le placera au mauvais endroit.') . "</description>\n"
            . '<ExtendedData>' . $data('athena:crs', self::CRS) . $data('athena:format', self::KML_FORMAT) . $data('athena:version', '1') . $data('athena:world', (string) ($doc['world'] ?? '')) . "</ExtendedData>\n";
        foreach (self::exportable($doc) as $el) {
            [$type, $pts] = self::shape($el);
            $p = $el['props'];
            $color = (string) ($p['color'] ?? '#f2f2f2');
            $line = self::kmlColor($color);
            $out .= '<Placemark><name>' . self::xml(self::title($el)) . '</name><description>' . self::xml($p['note'] ?? '') . '</description>'
                . '<Style><IconStyle><color>' . $line . '</color></IconStyle><LineStyle><color>' . $line . '</color><width>' . (int) ($p['width'] ?? 2) . '</width></LineStyle>'
                . '<PolyStyle><color>' . self::kmlColor($color, (float) ($p['fill'] ?? 0)) . '</color></PolyStyle></Style>'
                . '<ExtendedData>' . $data('athena:kind', (string) $el['kind'])
                . $data('athena:element', json_encode($el, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') . '</ExtendedData>';
            if ($type === 'Point') {
                $out .= '<Point><coordinates>' . $coord($pts[0]) . '</coordinates></Point>';
            } elseif ($type === 'LineString') {
                $out .= '<LineString><coordinates>' . implode(' ', array_map($coord, $pts)) . '</coordinates></LineString>';
            } else {
                $pts[] = $pts[0];
                $out .= '<Polygon><outerBoundaryIs><LinearRing><coordinates>' . implode(' ', array_map($coord, $pts)) . '</coordinates></LinearRing></outerBoundaryIs></Polygon>';
            }
            $out .= "</Placemark>\n";
        }

        return $out . "</Document>\n</kml>\n";
    }

    /**
     * KML => éléments prêts à ajouter au plan. Lecture sûre : DOCTYPE et entités refusés (import_unsafe), aucun accès
     * réseau, aucune entité externe. Placemark Point, LineString et Polygon (outerBoundaryIs), y compris dans un
     * MultiGeometry ; même règle degrés ou mètres que le GeoJSON. KMZ non pris en charge.
     *
     * @param array<mixed>|null $fit
     * @param list<string> $phaseUids
     * @return array{0: list<array<string, mixed>>, 1: int, 2: string}
     */
    public static function fromKml(string $xml, ?array $fit = null, array $phaseUids = []): array
    {
        if (strlen($xml) > PlanValidator::MAX_IMPORT_BYTES) {
            return [[], 0, 'import_too_large'];
        }
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            return [[], 0, 'import_unsafe'];
        }
        if (!class_exists(\DOMDocument::class) || trim($xml) === '') {
            return [[], 0, 'import_format'];
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok || $dom->documentElement === null || $dom->documentElement->localName !== 'kml') {
            return [[], 0, 'import_format'];
        }
        if ($dom->doctype !== null) {
            return [[], 0, 'import_unsafe'];
        }
        $root = $dom->documentElement;
        $ours = false;
        foreach ($root->getElementsByTagNameNS('*', 'Data') as $d) {
            if ($d instanceof \DOMElement && $d->getAttribute('name') === 'athena:crs' && trim($d->textContent) === self::CRS) {
                $ours = true;
                break;
            }
        }
        $styles = [];
        foreach ($root->getElementsByTagNameNS('*', 'Style') as $st) {
            if ($st instanceof \DOMElement && $st->getAttribute('id') !== '') {
                $styles['#' . $st->getAttribute('id')] = $st;
            }
        }
        $marks = $root->getElementsByTagNameNS('*', 'Placemark');
        if ($marks->length > PlanValidator::MAX_ELEMENTS) {
            return [[], 0, 'import_too_many'];
        }
        $raws = [];
        $shapes = [];
        $bad = 0;
        foreach ($marks as $pm) {
            if (!$pm instanceof \DOMElement) {
                continue;
            }
            if ($ours) {
                $embedded = null;
                foreach ($pm->getElementsByTagNameNS('*', 'Data') as $d) {
                    if ($d instanceof \DOMElement && $d->getAttribute('name') === 'athena:element') {
                        $embedded = json_decode(trim($d->textContent), true, 16);
                    }
                }
                if (is_array($embedded)) {
                    $raws[] = $embedded;
                    continue;
                }
            }
            $props = ['name' => self::childText($pm, 'name'), 'description' => self::childText($pm, 'description')];
            $style = self::child($pm, 'Style') ?? ($styles[trim(self::childText($pm, 'styleUrl'))] ?? null);
            if ($style !== null) {
                foreach (['LineStyle', 'IconStyle', 'PolyStyle'] as $tag) {
                    $node = self::child($style, $tag);
                    $c = $node !== null ? strtolower(trim(self::childText($node, 'color'))) : '';
                    if (preg_match('/^[0-9a-f]{8}$/', $c) === 1) {
                        $props['stroke'] = '#' . substr($c, 6, 2) . substr($c, 4, 2) . substr($c, 2, 2);
                        break;
                    }
                }
                $ls = self::child($style, 'LineStyle');
                $w = $ls !== null ? self::childText($ls, 'width') : '';
                if (is_numeric($w)) {
                    $props['stroke-width'] = (float) $w;
                }
            }
            $found = 0;
            foreach (['Point' => 'point', 'LineString' => 'line', 'Polygon' => 'area'] as $tag => $kind) {
                foreach ($pm->getElementsByTagNameNS('*', $tag) as $g) {
                    if (!$g instanceof \DOMElement) {
                        continue;
                    }
                    $found++;
                    $src = $g;
                    if ($tag === 'Polygon') {
                        $src = self::child($g, 'outerBoundaryIs');
                    }
                    $pts = $src !== null ? self::kmlCoords(self::childText($src, 'coordinates')) : null;
                    if ($pts === null) {
                        $bad++;
                        continue;
                    }
                    if (count($raws) + count($shapes) >= PlanValidator::MAX_ELEMENTS) {
                        return [[], 0, 'import_too_many'];
                    }
                    $shapes[] = ['type' => $kind, 'pts' => $kind === 'point' ? [$pts[0]] : $pts, 'props' => $props];
                }
            }
            if ($found === 0) {
                $bad++;
            }
        }

        return self::build($raws, $shapes, $bad, $ours, $fit, $phaseUids);
    }

    /** Premier descendant de ce nom (sans tenir compte de l'espace de noms). */
    private static function child(\DOMElement $el, string $name): ?\DOMElement
    {
        $n = $el->getElementsByTagNameNS('*', $name)->item(0);

        return $n instanceof \DOMElement ? $n : null;
    }

    private static function childText(\DOMElement $el, string $name): string
    {
        $n = self::child($el, $name);

        return $n !== null ? trim($n->textContent) : '';
    }

    /**
     * Texte « x,y[,z] x,y[,z] … » d'un KML => positions.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private static function kmlCoords(string $text): ?array
    {
        $list = [];
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $tuple) {
            if ($tuple !== '') {
                $list[] = explode(',', $tuple);
            }
        }

        return self::coords($list);
    }
}
