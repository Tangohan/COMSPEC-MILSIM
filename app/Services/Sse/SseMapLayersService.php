<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseMapLayersRepository;
use App\Repositories\SseSiteRepository;
use App\Support\ShortCache;
use App\Support\SseIdentityCatalog;
use App\Support\SseMapLayersGeo;

/**
 * Calques SSE des cartes (GET /api/sse/v1/calques) : contrôles d'identité, lieux et pistes de téléphones en GeoJSON.
 *
 * Complète SseAtakLayersService (dossiers, PIR, ordres, photos, tracés saisis à la main) sans le dupliquer : celui-ci
 * sert les objets du travail d'enquête déjà placés sur la carte ATAK, celui-là les données de TERRAIN qui n'y figuraient
 * pas (contrôles remontés du jeu, sites, positions extraites des téléphones).
 *
 * Sécurité (SSE est sensible) :
 *  - chaque type n'est lu que si la garde (SseMapLayerGate) l'autorise ; un type refusé n'est pas interrogé et son absence
 *    n'indique pas s'il existe des données ;
 *  - un point de contrôle ne porte un nom (fiche) que si le lecteur a le droit sur les fichiers de personnes ; sinon il
 *    reste un point anonyme « Contrôle » ; aucune identité d'emprunt, couverture ou faux n'est jamais nommée ici ;
 *  - lire des pistes de téléphones est journalisé dans l'audit SSE ; si le journal est indisponible, les pistes ne sont pas
 *    servies (échec fermé) ;
 *  - le cache court est clé par communauté, carte, paramètres ET par ce que le lecteur a le droit de voir : deux lecteurs
 *    aux droits différents ne partagent jamais une entrée.
 */
final class SseMapLayersService
{
    public const VERSION = 1;

    /** Types servis : clé de l'API => libellé. */
    public const TYPES = [
        'controles' => 'Contrôles d’identité',
        'lieux' => 'Lieux',
        'pistes' => 'Pistes de téléphones',
    ];

    public const MAX_CONTROLS = 500;

    public const MAX_PLACES = 500;

    public const MAX_TRACKS = 60;

    public const MAX_VERTICES = 150;

    /** Tolérance de simplification des pistes (mètres). */
    public const TOLERANCE_M = 12.0;

    public const CACHE_TTL = 20;

    public const AUDIT_ACTION = 'map_layers.phone_tracks.read';

    /** @var \Closure(array<string, mixed>): void */
    private \Closure $audit;

    /**
     * @param (\Closure(array<string, mixed>): void)|null $audit écrit une ligne d'audit SSE ; lève une exception si impossible
     */
    public function __construct(private ?SseMapLayersRepository $repo = null, ?\Closure $audit = null)
    {
        $this->repo ??= new SseMapLayersRepository();
        $this->audit = $audit ?? static function (array $record): void {
            (new SseAuditLogRepository())->write($record);
        };
    }

    /**
     * Paramètres de requête validés.
     *
     * @param array<string, mixed> $get
     * @return array{error: string}|array{query: array{types: list<string>, map: int, since: ?string, until: ?string, period: string, bbox: ?array{0: float, 1: float, 2: float, 3: float}}}
     */
    public static function parseQuery(array $get, ?\DateTimeImmutable $now = null): array
    {
        $str = static fn (mixed $v): string => is_scalar($v) ? (string) $v : '';
        $types = SseMapLayersGeo::parseTypes($str($get['types'] ?? ''), self::TYPES);
        if ($types === null) {
            return ['error' => 'invalid_types'];
        }
        $mapRaw = $str($get['carte'] ?? '');
        if ($types !== [] && preg_match('/^[1-9]\d{0,9}$/', $mapRaw) !== 1) {
            return ['error' => 'invalid_map'];
        }
        $bbox = SseMapLayersGeo::parseBbox($str($get['bbox'] ?? ''));
        if ($bbox === false) {
            return ['error' => 'invalid_bbox'];
        }
        $window = SseMapLayersGeo::window($str($get['depuis'] ?? ''), $str($get['jusqua'] ?? ''), $str($get['periode'] ?? ''), $now);
        if ($window === null) {
            return ['error' => 'invalid_window'];
        }

        return ['query' => [
            'types' => $types,
            'map' => $mapRaw !== '' && preg_match('/^[1-9]\d{0,9}$/', $mapRaw) === 1 ? (int) $mapRaw : 0,
            'since' => $window['since'],
            'until' => $window['until'],
            'period' => $window['period'],
            'bbox' => $bbox,
        ]];
    }

    /**
     * Ce que ce lecteur peut demander (sert à n'afficher que les cases utiles ; ne dit rien des données).
     *
     * @return array{controles: bool, lieux: bool, pistes: bool, noms: bool}
     */
    public function access(SseMapLayerGate $gate): array
    {
        if ($gate->tenantId() < 1) {
            return ['controles' => false, 'lieux' => false, 'pistes' => false, 'noms' => false];
        }

        return [
            'controles' => $gate->canReadControls(),
            'lieux' => $gate->canReadPlaces(),
            'pistes' => $gate->canReadDigitalExploitation(),
            'noms' => $gate->canReadPersonFiles(),
        ];
    }

    /**
     * FeatureCollection GeoJSON des types demandés. La garde doit avoir franchi la porte (tenantId() > 0), sinon rien.
     *
     * @param array{types: list<string>, map: int, since: ?string, until: ?string, period: string, bbox: ?array{0: float, 1: float, 2: float, 3: float}} $query
     * @return array<string, mixed>
     */
    public function build(SseMapLayerGate $gate, array $query): array
    {
        $tenantId = $gate->tenantId();
        $access = $this->access($gate);
        $types = $tenantId > 0 ? $query['types'] : [];

        $key = 'sse_maplayers:' . $tenantId . ':' . $query['map'] . ':' . substr(sha1((string) json_encode([$query, $access])), 0, 24);
        $compute = fn (): array => $this->compute($tenantId, $types, $access, $query);

        return $tenantId > 0 ? ShortCache::remember($key, self::CACHE_TTL, $compute) : $compute();
    }

    /**
     * Journalise la lecture de pistes et retire les pistes si le journal n'est pas disponible. À appeler à CHAQUE réponse,
     * y compris celles servies depuis le cache.
     *
     * @param array<string, mixed> $payload
     * @param array{types: list<string>, map: int, since: ?string, until: ?string, period: string, bbox: ?array{0: float, 1: float, 2: float, 3: float}} $query
     * @return array<string, mixed>
     */
    public function auditTrackAccess(SseMapLayerGate $gate, array $query, array $payload): array
    {
        $ids = [];
        foreach ((array) ($payload['features'] ?? []) as $f) {
            if (is_array($f) && ($f['properties']['type'] ?? '') === 'piste') {
                $ids[] = (int) ($f['properties']['extraction_id'] ?? 0);
            }
        }
        if ($ids === []) {
            return $payload;
        }
        $tenantId = $gate->tenantId();
        $dedupe = 'sse_maplayers_audit:' . $tenantId . ':' . (int) $gate->userId() . ':' . $query['map'] . ':' . substr(sha1(implode(',', $ids)), 0, 16);
        if (ShortCache::enabled() && ShortCache::get($dedupe) !== null) {
            return $payload;
        }
        try {
            ($this->audit)([
                'tenant_id' => $tenantId,
                'actor_user_id' => $gate->userId(),
                'actor_label' => $gate->actorLabel(),
                'action' => self::AUDIT_ACTION,
                'object_type' => 'sse_phone_extraction',
                'reason' => 'Calque des pistes de téléphones, carte ' . $query['map'],
                'after' => ['map' => $query['map'], 'extractions' => array_slice($ids, 0, 60), 'count' => count($ids), 'period' => $query['period']],
            ]);
            ShortCache::put($dedupe, 1, 300);

            return $payload;
        } catch (\Throwable $e) {
            error_log('Calques SSE : journal d’audit indisponible, pistes non servies : ' . $e->getMessage());
            $payload['features'] = array_values(array_filter(
                (array) $payload['features'],
                static fn (mixed $f): bool => !is_array($f) || ($f['properties']['type'] ?? '') !== 'piste'
            ));
            $payload['meta']['types']['pistes'] = ['allowed' => false, 'count' => 0, 'truncated' => false, 'empty' => 'audit_unavailable'];

            return $payload;
        }
    }

    /**
     * @param list<string> $types
     * @param array{controles: bool, lieux: bool, pistes: bool, noms: bool} $access
     * @param array{types: list<string>, map: int, since: ?string, until: ?string, period: string, bbox: ?array{0: float, 1: float, 2: float, 3: float}} $query
     * @return array<string, mixed>
     */
    private function compute(int $tenantId, array $types, array $access, array $query): array
    {
        $features = [];
        $meta = [];
        foreach ($types as $type) {
            if (!$access[$type]) {
                $meta[$type] = ['allowed' => false, 'count' => 0, 'truncated' => false, 'empty' => 'forbidden'];
                continue;
            }
            $res = match ($type) {
                'controles' => $this->controls($tenantId, $query, $access['noms']),
                'lieux' => $this->places($tenantId, $query),
                default => $this->tracks($tenantId, $query, $access['noms']),
            };
            foreach ($res['features'] as $f) {
                $features[] = $f;
            }
            $meta[$type] = ['allowed' => true, 'count' => count($res['features']), 'truncated' => $res['truncated'], 'empty' => $res['features'] === [] ? $res['empty'] : null] + $res['extra'];
        }

        return [
            'type' => 'FeatureCollection',
            'crs' => ['type' => 'name', 'properties' => ['name' => SseMapLayersGeo::CRS_NAME]],
            'features' => $features,
            'meta' => [
                'version' => self::VERSION,
                'map' => $query['map'],
                'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'period' => ['name' => $query['period'], 'since' => SseMapLayersGeo::isoUtc($query['since']), 'until' => SseMapLayersGeo::isoUtc($query['until'])],
                'access' => $access,
                'types' => $meta,
            ],
        ];
    }

    /**
     * @param array{types: list<string>, map: int, since: ?string, until: ?string, period: string, bbox: ?array{0: float, 1: float, 2: float, 3: float}} $query
     * @return array{features: list<array<string, mixed>>, truncated: bool, empty: string, extra: array<string, mixed>}
     */
    private function controls(int $tenantId, array $query, bool $names): array
    {
        $rows = $this->repo->controls($tenantId, $query['map'], $query['since'], $query['until'], $query['bbox'], self::MAX_CONTROLS + 1);
        $truncated = count($rows) > self::MAX_CONTROLS;
        $rows = array_slice($rows, 0, self::MAX_CONTROLS);

        $fiche = [];
        $plain = [];
        if ($names) {
            $fiche = $this->repo->personNames($tenantId, array_map(static fn (array $r): int => (int) ($r['person_id'] ?? 0), $rows));
            $plain = $this->repo->plainIdentityNames(
                $tenantId,
                array_map(static fn (array $r): int => (int) ($r['person_id'] ?? 0) > 0 ? 0 : (int) ($r['identity_id'] ?? 0), $rows)
            );
        }

        $out = [];
        foreach ($rows as $r) {
            if (!SseMapLayersGeo::validWorldPoint($r['pos_x'] ?? null, $r['pos_y'] ?? null)) {
                continue;
            }
            $id = (int) $r['id'];
            $verdict = (string) ($r['verdict'] ?? '');
            $name = null;
            $personId = (int) ($r['person_id'] ?? 0);
            if ($names) {
                $name = $this->displayName($fiche[$personId] ?? ($plain[(int) ($r['identity_id'] ?? 0)] ?? null));
            }
            $out[] = [
                'type' => 'Feature',
                'id' => 'controle-' . $id,
                'geometry' => ['type' => 'Point', 'coordinates' => [round((float) $r['pos_x'], 1), round((float) $r['pos_y'], 1)]],
                'properties' => [
                    'type' => 'controle',
                    'ref' => SseIdentityCatalog::checkRef($id),
                    'at' => SseMapLayersGeo::isoUtc((string) ($r['created_at'] ?? '')),
                    'game_time' => ($r['game_time'] ?? null) !== null ? mb_substr((string) $r['game_time'], 0, 32) : null,
                    'grid' => ($r['grid'] ?? null) !== null ? mb_substr((string) $r['grid'], 0, 32) : null,
                    'verdict' => $verdict,
                    'verdict_label' => SseIdentityCatalog::label(SseIdentityCatalog::VERDICTS, $verdict, 'Sans verdict'),
                    'tone' => \App\Services\Sse\SseIdCheckInsightService::verdictTone($verdict),
                    'name' => $name,
                    'anonymous' => $name === null,
                    'source' => 'id_check',
                    'sheet' => $names && $personId > 0 && isset($fiche[$personId]) ? 'atak/sse/identites/' . $personId : null,
                    'detail' => $names ? 'atak/sse/papiers/controles/' . $id : null,
                ],
            ];
        }

        return ['features' => $out, 'truncated' => $truncated, 'empty' => 'none', 'extra' => []];
    }

    /**
     * @param array{types: list<string>, map: int, since: ?string, until: ?string, period: string, bbox: ?array{0: float, 1: float, 2: float, 3: float}} $query
     * @return array{features: list<array<string, mixed>>, truncated: bool, empty: string, extra: array<string, mixed>}
     */
    private function places(int $tenantId, array $query): array
    {
        $rows = $this->repo->sites($tenantId, $query['map'], $query['bbox'], self::MAX_PLACES + 1);
        $truncated = count($rows) > self::MAX_PLACES;
        $rows = array_slice($rows, 0, self::MAX_PLACES);
        $out = [];
        foreach ($rows as $r) {
            if (!SseMapLayersGeo::validWorldPoint($r['pos_x'] ?? null, $r['pos_y'] ?? null)) {
                continue;
            }
            $id = (int) $r['id'];
            $kind = (string) ($r['site_type'] ?? 'autre');
            $status = (string) ($r['status'] ?? '');
            $out[] = [
                'type' => 'Feature',
                'id' => 'lieu-' . $id,
                'geometry' => ['type' => 'Point', 'coordinates' => [round((float) $r['pos_x'], 1), round((float) $r['pos_y'], 1)]],
                'properties' => [
                    'type' => 'lieu',
                    'name' => mb_substr(trim((string) ($r['name'] ?? '')), 0, 160),
                    'kind' => $kind,
                    'kind_label' => SseSiteRepository::TYPE_LABELS[$kind] ?? SseSiteRepository::TYPE_LABELS['autre'],
                    'status' => $status,
                    'status_label' => SseSiteRepository::STATUS_LABELS[$status] ?? $status,
                    'grid' => ($r['grid_reference'] ?? null) !== null ? mb_substr((string) $r['grid_reference'], 0, 64) : null,
                    'at' => SseMapLayersGeo::isoUtc((string) ($r['created_at'] ?? '')),
                    'source' => 'site',
                    'detail' => 'atak/sse/sites/' . $id,
                ],
            ];
        }

        return ['features' => $out, 'truncated' => $truncated, 'empty' => 'none', 'extra' => []];
    }

    /**
     * @param array{types: list<string>, map: int, since: ?string, until: ?string, period: string, bbox: ?array{0: float, 1: float, 2: float, 3: float}} $query
     * @return array{features: list<array<string, mixed>>, truncated: bool, empty: string, extra: array<string, mixed>}
     */
    private function tracks(int $tenantId, array $query, bool $names): array
    {
        $unmapped = $this->repo->extractionsWithoutMap($tenantId, $query['since'], $query['until']);
        $extractions = $this->repo->extractions($tenantId, $query['map'], $query['since'], $query['until'], self::MAX_TRACKS + 1);
        $truncated = count($extractions) > self::MAX_TRACKS;
        $extractions = array_slice($extractions, 0, self::MAX_TRACKS);

        $byExtraction = [];
        foreach ($this->repo->positions($tenantId, array_map(static fn (array $e): int => (int) $e['id'], $extractions), 20000) as $p) {
            $byExtraction[(int) $p['extraction_id']][] = $p;
        }

        $out = [];
        $unreadable = 0;
        $noMovement = 0;
        $noPosition = 0;
        foreach ($extractions as $e) {
            $id = (int) $e['id'];
            $rows = $byExtraction[$id] ?? [];
            if ($rows === []) {
                $noPosition++;
                continue;
            }
            $coords = [];
            $times = [];
            foreach ($rows as $p) {
                $w = SseMapLayersGeo::gridToWorld(isset($p['grid']) ? (string) $p['grid'] : null);
                if ($w === null) {
                    $unreadable++;
                    continue;
                }
                $last = $coords === [] ? null : $coords[count($coords) - 1];
                if ($last !== null && abs($last[0] - $w[0]) < 0.5 && abs($last[1] - $w[1]) < 0.5) {
                    continue;
                }
                $coords[] = $w;
                $times[] = mb_substr(trim((string) ($p['at'] ?? '')), 0, 32);
            }
            if (count($coords) < 2) {
                $noMovement++;
                continue;
            }
            if (!SseMapLayersGeo::trackTouchesBbox($coords, $query['bbox'])) {
                continue;
            }
            $keep = SseMapLayersGeo::simplifyIndices($coords, self::TOLERANCE_M, self::MAX_VERTICES);
            $line = [];
            $lineTimes = [];
            foreach ($keep as $i) {
                $line[] = [round($coords[$i][0], 1), round($coords[$i][1], 1)];
                $lineTimes[] = $times[$i];
            }
            $ref = (string) ($e['ref'] ?? '');
            $label = $names ? trim((string) ($e['owner_label'] ?? '')) : '';
            if ($label === '' && $names) {
                $label = trim((string) ($e['device_label'] ?? ''));
            }
            $out[] = [
                'type' => 'Feature',
                'id' => 'piste-' . $id,
                'geometry' => ['type' => 'LineString', 'coordinates' => $line],
                'properties' => [
                    'type' => 'piste',
                    'extraction_id' => $id,
                    'ref' => $ref !== '' ? $ref : SsePhoneExtractionService::ref($id),
                    'label' => $label !== '' ? mb_substr($label, 0, 120) : null,
                    'recorded_at' => SseMapLayersGeo::isoUtc((string) ($e['created_at'] ?? '')),
                    'times' => $lineTimes,
                    'times_kind' => 'game_clock',
                    'vertices_total' => count($coords),
                    'simplified' => count($line) < count($coords),
                    'source' => 'phone_extraction',
                    'detail' => $names ? 'atak/sse/fichiers/telephones/' . $id : null,
                ],
            ];
        }

        $empty = 'none';
        if ($out === []) {
            $empty = $extractions === [] ? ($unmapped > 0 ? 'unmapped' : 'no_extraction') : ($noPosition === count($extractions) ? 'no_position' : 'no_movement');
        }

        return [
            'features' => $out,
            'truncated' => $truncated,
            'empty' => $empty,
            'extra' => ['unmapped_extractions' => $unmapped, 'without_positions' => $noPosition, 'single_position' => $noMovement, 'unreadable_grids' => $unreadable],
        ];
    }

    /** @param array{first: string, last: string, alias: string}|null $n */
    private function displayName(?array $n): ?string
    {
        if ($n === null) {
            return null;
        }
        $name = trim($n['first'] . ' ' . $n['last']);
        if ($name === '') {
            $name = $n['alias'];
        }

        return $name !== '' ? mb_substr($name, 0, 160) : null;
    }
}
