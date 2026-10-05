<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Repositories\OverwatchIntelRepository;
use App\Services\Tactical\AtakActivityLogService;
use App\Services\Tactical\AtakTelemetryJournalService;
use App\Support\ComspecApiKeyAuth;
use App\Support\HttpJsonBody;
use App\Support\ReconImageStorage;
use Throwable;

/**
 * Overwatch Beta — alertes tactiques et balises, applications synchronisées,
 * fil de renseignement unifié, anneaux de géolocalisation.
 */
final class AtakOverwatchIntelController
{
    /** Au-delà, une application est considérée inactive. */
    private const ACTIVE_SEC = 15 * 60;

    /** Balise muette depuis plus longtemps : retirée de la carte. */
    private const BEACON_TTL_SEC = 30 * 60;

    private OverwatchIntelRepository $repo;

    /** @var array<string, mixed>|null */
    private ?array $bodyCache = null;

    public function __construct(?OverwatchIntelRepository $repo = null)
    {
        $this->repo = $repo ?? new OverwatchIntelRepository();
    }

    // ================================================================== Alertes + balises

    /** GET /api/atak/overwatch/alerts?mapId= */
    public function alerts(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant($request);
        if ($tenantId === null) {
            return $this->denied();
        }
        $mapId = $this->mapId($request);
        $acks = $this->repo->acks($tenantId, $mapId);

        $alerts = [];
        try {
            $rows = (new AtakDataRepository())->getTacticalAlertsFromChat($tenantId, $mapId, 60);
        } catch (Throwable $e) {
            error_log('[overwatch-intel] alerts ' . $e->getMessage());
            $rows = [];
        }
        foreach ($rows as $row) {
            $key = 'chat:' . (int) ($row['id'] ?? 0);
            $summary = (string) ($row['summary'] ?? '');
            $kind = (string) ($row['kind'] ?? '');
            // Le module connect range la PANIQUE du téléphone sous EAGLE_DOWN : on la distingue par son texte.
            $panic = $kind === 'eagle_down' && preg_match('/PANI(Q|C)|D[ÉE]TRESSE/iu', $summary) === 1;
            $alerts[] = [
                'key' => $key,
                'id' => (int) ($row['id'] ?? 0),
                'kind' => $panic ? 'panic' : $kind,
                'kind_label' => $panic ? 'Panique — opérateur en détresse' : (string) ($row['kind_label'] ?? ''),
                'severity' => $panic ? 'critical' : (string) ($row['severity'] ?? 'medium'),
                'call_sign' => (string) ($row['call_sign'] ?? ''),
                'grid' => (string) ($row['grid'] ?? ''),
                'pos_x' => $row['pos_x'] ?? null,
                'pos_y' => $row['pos_y'] ?? null,
                'summary' => $summary,
                'author' => (string) ($row['author'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'acked' => isset($acks[$key]),
                'acked_by' => $acks[$key]['by'] ?? '',
                'acked_at' => $acks[$key]['at'] ?? '',
            ];
        }
        $alerts = array_reverse($alerts);

        $beacons = $this->beacons($tenantId, $mapId);
        foreach ($beacons as &$b) {
            $b['acked'] = isset($acks[$b['key']]);
            $b['acked_by'] = $acks[$b['key']]['by'] ?? '';
        }
        unset($b);

        return Response::json([
            'ok' => true,
            'mapId' => $mapId,
            'alerts' => $alerts,
            'beacons' => $beacons,
            'unacked' => count(array_filter($alerts, static fn (array $a): bool => !$a['acked'] && $a['kind'] !== 'tic_clear')),
        ])->header('Cache-Control', 'no-store');
    }

    /** POST /api/atak/overwatch/alerts/ack { key, undo? } */
    public function alertAck(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant($request);
        $guard = $this->guardWrite($request, $tenantId);
        if ($guard !== null) {
            return $guard;
        }
        $body = $this->body();
        $mapId = $this->mapId($request, $body);
        $key = trim((string) ($body['key'] ?? ''));
        if ($key === '' || preg_match('/^[a-z]+:[A-Za-z0-9_.:\-]{1,80}$/', $key) !== 1) {
            return Response::json(['ok' => false, 'error' => 'Alerte inconnue.'], 422);
        }
        $ok = !empty($body['undo'])
            ? $this->repo->unack((int) $tenantId, $mapId, $key)
            : $this->repo->ack((int) $tenantId, $mapId, $key, $this->operatorLabel(), $this->userId());

        return Response::json(['ok' => $ok, 'key' => $key, 'acked_by' => $this->operatorLabel()]);
    }

    /**
     * Balises GPS : suivi véhicule (mission GPS_BEACON) et contacts relais « gps_beacon ».
     *
     * @return list<array<string, mixed>>
     */
    private function beacons(int $tenantId, int $mapId): array
    {
        $out = [];
        $seen = [];
        $vt = $this->repo->columns('atak_vehicle_tracking');
        if ($vt !== []) {
            $mapCol = in_array('context_id', $vt, true) ? 'context_id' : (in_array('map_id', $vt, true) ? 'map_id' : null);
            $timeCol = in_array('last_seen_at', $vt, true) ? 'COALESCE(last_seen_at, updated_at)' : 'updated_at';
            $rows = $this->repo->safeFetch(
                "SELECT id, vehicle_callsign, vehicle_name, pos_x, pos_y, heading, speed, status, side,
                        $timeCol AS seen_at, TIMESTAMPDIFF(SECOND, $timeCol, NOW()) AS age_sec
                 FROM atak_vehicle_tracking
                 WHERE tenant_id = :t" . ($mapCol ? " AND $mapCol = :m" : '') . "
                   AND UPPER(mission_type) = 'GPS_BEACON'
                   AND $timeCol >= DATE_SUB(NOW(), INTERVAL " . self::BEACON_TTL_SEC . " SECOND)
                 ORDER BY seen_at DESC LIMIT 40",
                $mapCol ? ['t' => $tenantId, 'm' => $mapId] : ['t' => $tenantId]
            );
            foreach ($rows as $r) {
                $cs = (string) ($r['vehicle_callsign'] ?? '');
                $seen[strtoupper($cs)] = true;
                $out[] = [
                    'key' => 'beacon:v' . (int) $r['id'],
                    'id' => (int) $r['id'],
                    'call_sign' => $cs,
                    'label' => (string) (($r['vehicle_name'] ?? '') !== '' ? $r['vehicle_name'] : $cs),
                    'source' => 'vehicle',
                    'pos_x' => (float) $r['pos_x'],
                    'pos_y' => (float) $r['pos_y'],
                    'heading' => $r['heading'] !== null ? (float) $r['heading'] : null,
                    'speed' => $r['speed'] !== null ? (float) $r['speed'] : null,
                    'status' => (string) ($r['status'] ?? ''),
                    'seen_at' => (string) ($r['seen_at'] ?? ''),
                    'age_sec' => $r['age_sec'] !== null ? max(0, (int) $r['age_sec']) : null,
                ];
            }
        }
        $rows = $this->repo->safeFetch(
            "SELECT id, call_sign, pos_x, pos_y, heading, extra, updated_at,
                    TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS age_sec
             FROM atak_units
             WHERE tenant_id = :t AND map_id = :m
               AND (extra LIKE :f1 OR extra LIKE :f2)
               AND updated_at >= DATE_SUB(NOW(), INTERVAL " . self::BEACON_TTL_SEC . " SECOND)
             ORDER BY updated_at DESC LIMIT 40",
            ['t' => $tenantId, 'm' => $mapId, 'f1' => '%"gps_beacon":true%', 'f2' => '%"source":"gps"%']
        );
        foreach ($rows as $r) {
            $cs = (string) ($r['call_sign'] ?? '');
            if (isset($seen[strtoupper($cs)])) {
                continue;
            }
            $extra = json_decode((string) ($r['extra'] ?? ''), true);
            $extra = is_array($extra) ? $extra : [];
            $out[] = [
                'key' => 'beacon:u' . (int) $r['id'],
                'id' => (int) $r['id'],
                'call_sign' => $cs,
                'label' => (string) (($extra['vehicle_name'] ?? '') !== '' ? $extra['vehicle_name'] : $cs),
                'beacon_id' => (string) ($extra['beacon_id'] ?? ''),
                'source' => 'unit',
                'pos_x' => (float) $r['pos_x'],
                'pos_y' => (float) $r['pos_y'],
                'heading' => $r['heading'] !== null ? (float) $r['heading'] : null,
                'speed' => null,
                'status' => '',
                'seen_at' => (string) ($r['updated_at'] ?? ''),
                'age_sec' => $r['age_sec'] !== null ? max(0, (int) $r['age_sec']) : null,
            ];
        }

        return $out;
    }

    // ================================================================== Applications synchronisées

    /**
     * Applications du téléphone et modules qui transmettent au poste : sources SQL, télémétrie, activité.
     * Aussi lu par Mes appareils (activité d'un seul opérateur).
     *
     * @return list<array<string, mixed>>
     */
    public static function appCatalog(): array
    {
        $alertLike = "(body LIKE 'ALERTE TACTIQUE%' OR body LIKE '%] ALERTE TACTIQUE%')";
        $medLike = "(body LIKE 'ALERTE M%DICALE%' OR body LIKE 'WIA%' OR body LIKE '%] ALERTE M%DICALE%')";

        return [
            ['app' => 'BFT / GPS', 'module' => 'COMSPEC Overwatch (connect)', 'data' => 'Positions, cap, état de liaison',
                'sql' => [['table' => 'atak_units', 'time' => ['updated_at'], 'author' => ['call_sign'],
                    'where' => "(extra IS NULL OR (extra NOT LIKE '%\"gps_beacon\":true%' AND extra NOT LIKE '%\"phone_geoloc\":true%'))"]],
                'tel' => ['pos', 'position', 'heartbeat']],
            ['app' => 'Messagerie', 'module' => 'COMSPEC ATAK (natif) · Messagerie', 'data' => 'Messages de canal et de groupe',
                'sql' => [['table' => 'atak_chat_messages', 'time' => ['created_at'], 'author' => ['author'],
                    'where' => "NOT $alertLike AND NOT $medLike AND body NOT LIKE 'ORDER|%' AND body NOT LIKE 'REGLAGES AFFICHAGE%'"]]],
            ['app' => 'Alertes', 'module' => 'COMSPEC ATAK (natif) · Alertes', 'data' => 'Panique, contact, fin de contact, appareil abattu, SALUTE',
                'sql' => [['table' => 'atak_chat_messages', 'time' => ['created_at'], 'author' => ['author'], 'where' => $alertLike]]],
            ['app' => 'Médical', 'module' => 'COMSPEC ATAK (natif) · Médical', 'data' => 'Alertes blessé, triage, MEDEVAC',
                'sql' => [
                    ['table' => 'atak_chat_messages', 'time' => ['created_at'], 'author' => ['author'], 'where' => $medLike],
                    ['table' => 'atak_medevac_requests', 'time' => ['created_at'], 'author' => ['requested_by_callsign'], 'map' => ['context_id']],
                ],
                'tel' => ['med', 'medical', 'medical_alert', 'med_clear', 'medical_clear']],
            ['app' => 'Photos', 'module' => 'COMSPEC ATAK (natif) · Photos', 'data' => 'Photos géolocalisées et légendes',
                'sql' => [['table' => 'recon_images', 'time' => ['created_at'], 'author' => ['author_callsign'], 'map' => [],
                    'where' => "UPPER(COALESCE(device_type, '')) <> 'HELMET'"]]],
            ['app' => 'Live cam', 'module' => 'COMSPEC ATAK (natif) · Live cam', 'data' => 'Images caméra casque',
                'sql' => [['table' => 'recon_images', 'time' => ['created_at'], 'author' => ['author_callsign'], 'map' => [],
                    'where' => "UPPER(COALESCE(device_type, '')) = 'HELMET'"]]],
            ['app' => 'Reco', 'module' => 'COMSPEC ATAK (natif) · Reco', 'data' => 'Notes de reconnaissance (type, confiance, position)',
                'sql' => [['table' => 'recon_notes', 'time' => ['created_at'], 'author' => ['author']]],
                'tel' => ['obs', 'observation', 'recon']],
            ['app' => 'FRS / FRM', 'module' => 'COMSPEC ATAK (natif) · FRS', 'data' => 'Fiches de renseignement, pièces jointes',
                'sql' => [['table' => 'sse_field_notes', 'time' => ['created_at'], 'author' => ['author_label'], 'map' => [],
                    'where' => "UPPER(COALESCE(intel_source, '')) <> 'OSINT'"]]],
            ['app' => 'OSINT', 'module' => 'Fiches « sources ouvertes »', 'data' => 'Renseignement d’origine publique',
                'sql' => [['table' => 'sse_field_notes', 'time' => ['created_at'], 'author' => ['author_label'], 'map' => [],
                    'where' => "UPPER(COALESCE(intel_source, '')) = 'OSINT'"]]],
            ['app' => 'Comptes rendus', 'module' => 'COMSPEC ATAK (natif) · Comptes rendus', 'data' => 'Contact, SALUTE, SPOTREP, SITREP, BDA, engin explosif, NRBC, LOGREP, FRAGO, rapports IceMan',
                'sql' => [['table' => 'atak_tactical_reports', 'time' => ['created_at'], 'author' => ['submitter_callsign'], 'map' => ['context_id']]],
                'tel' => ['salute', 'bda', 'bda_confirm']],
            ['app' => 'Feux / JTAC', 'module' => 'COMSPEC ATAK (natif) · Feux', 'data' => '9-line, demandes d’appui',
                'sql' => [['table' => 'atak_nine_line', 'time' => ['created_at'], 'author' => ['author']]],
                'tel' => ['combat', 'fire']],
            ['app' => 'Explosifs', 'module' => 'COMSPEC ATAK (natif) · Explosifs', 'data' => 'Charges posées, minuteries, mises à feu',
                'sql' => [['table' => 'atak_explosive_timers', 'time' => ['created_at'], 'author' => ['author']]]],
            ['app' => 'Drones et aéronefs', 'module' => 'COMSPEC ATAK (natif) · Drone', 'data' => 'Position, carburant, statut',
                'sql' => [['table' => 'atak_air_assets', 'time' => ['updated_at', 'last_update'], 'author' => ['callsign']]],
                'tel' => ['flight', 'flight_manifest']],
            ['app' => 'Véhicules et balises', 'module' => 'COMSPEC Overwatch (connect) · suivi', 'data' => 'Position, état, carburant, balises GPS',
                'sql' => [['table' => 'atak_vehicle_tracking', 'time' => ['updated_at'], 'author' => ['vehicle_callsign'], 'map' => ['context_id']]],
                'tel' => ['veh', 'vehicle', 'vehicles']],
            ['app' => 'Guerre électronique', 'module' => 'COMSPEC ATAK (natif) · GE / SIGINT', 'data' => 'Relèvements, émissions détectées',
                'sql' => [
                    ['table' => 'atak_sigint_reports', 'time' => ['created_at'], 'author' => ['call_sign']],
                    ['table' => 'atak_rf_hits', 'time' => ['created_at'], 'author' => ['sensor_callsign']],
                ],
                'tel' => ['sigint']],
            ['app' => 'Logistique', 'module' => 'COMSPEC ATAK (natif) · Logistique', 'data' => 'Demandes de ravitaillement, états',
                'act' => ['resupply_request'], 'tel' => ['logstat', 'logistics']],
            ['app' => 'Pings', 'module' => 'COMSPEC ATAK · carte', 'data' => 'Signalements rapides',
                'sql' => [['table' => 'atak_pings', 'time' => ['created_at'], 'author' => ['author']]]],
            ['app' => 'Marqueurs carte', 'module' => 'COMSPEC Overwatch (connect) · marqueurs', 'data' => 'Marqueurs Arma synchronisés',
                'sql' => [['table' => 'atak_markers', 'time' => ['updated_at', 'created_at']]]],
            ['app' => 'Géolocalisation', 'module' => 'COMSPEC ATAK (natif) · GE / GÉOLOC', 'data' => 'Cercles de probabilité',
                'sql' => [['table' => 'atak_geoloc_rings', 'time' => ['created_at'], 'author' => ['author'], 'where' => "source = 'phone'"]],
                'tel' => ['geoloc']],
            ['app' => 'Radio', 'module' => 'COMSPEC Overwatch (connect) · ACRE', 'data' => 'Émissions radio, réseaux',
                'tel' => ['comms', 'acre', 'radio']],
            ['app' => 'Événements d’unité', 'module' => 'COMSPEC Overwatch (connect)', 'data' => 'Blessé, mort, montée en véhicule…',
                'tel' => ['unit', 'unit_event', 'state']],
            ['app' => 'Météo', 'module' => 'COMSPEC Overwatch (connect)', 'data' => 'Conditions de mission',
                'tel' => ['weather', 'wx']],
            ['app' => 'Rejeu mission (AAR)', 'module' => 'Athena · rapports AAR', 'data' => 'Rapports après action',
                'sql' => [['table' => 'aar_reports', 'time' => ['created_at'], 'map' => []]]],
        ];
    }


    /** GET /api/atak/overwatch/apps-sync?mapId= */
    public function appsSync(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant($request);
        if ($tenantId === null) {
            return $this->denied();
        }
        $mapId = $this->mapId($request);
        $telemetry = $this->telemetryStats($tenantId, $mapId);
        $activity = $this->activityStats($tenantId, $mapId);

        $apps = self::appCatalog();

        $rows = [];
        foreach ($apps as $def) {
            $today = 0;
            $bestAge = null;
            $bestAt = null;
            $bestWho = '';
            $available = false;
            $types = [];
            foreach ($def['sql'] ?? [] as $spec) {
                $st = $this->repo->sourceStats($spec, $tenantId, $mapId);
                if (!$st['available']) {
                    continue;
                }
                $available = true;
                $today += $st['today'];
                if ($st['age_sec'] !== null && ($bestAge === null || $st['age_sec'] < $bestAge)) {
                    $bestAge = $st['age_sec'];
                    $bestAt = $st['last_at'];
                    $bestWho = $st['last_author'];
                }
            }
            foreach ($def['tel'] ?? [] as $t) {
                if (!isset($telemetry[$t])) {
                    continue;
                }
                $available = true;
                $types[] = $t;
                $s = $telemetry[$t];
                // La télémétrie double souvent la table SQL : on ne l'ajoute au compte que sans table.
                if (empty($def['sql'])) {
                    $today += $s['today'];
                }
                if ($bestAge === null || $s['age_sec'] < $bestAge) {
                    $bestAge = $s['age_sec'];
                    $bestAt = date('Y-m-d H:i:s', time() - $s['age_sec']);
                    $bestWho = $s['who'];
                }
            }
            foreach ($def['act'] ?? [] as $kind) {
                if (!isset($activity[$kind])) {
                    continue;
                }
                $available = true;
                $s = $activity[$kind];
                $today += $s['today'];
                if ($bestAge === null || $s['age_sec'] < $bestAge) {
                    $bestAge = $s['age_sec'];
                    $bestAt = date('Y-m-d H:i:s', time() - $s['age_sec']);
                    $bestWho = $s['who'];
                }
            }
            $rows[] = [
                'app' => $def['app'],
                'module' => $def['module'],
                'data' => $def['data'],
                'telemetry_types' => $types,
                'today' => $today,
                'last_at' => $bestAt,
                'age_sec' => $bestAge,
                'last_author' => $bestWho,
                'status' => $bestAge === null ? 'jamais' : ($bestAge <= self::ACTIVE_SEC ? 'actif' : 'inactif'),
                'available' => $available,
            ];
        }

        return Response::json([
            'ok' => true,
            'mapId' => $mapId,
            'active_after_sec' => self::ACTIVE_SEC,
            'apps' => $rows,
            'generated_at' => date('c'),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Journal de télémétrie (fichier) : par type, nombre aujourd'hui + dernier indicatif.
     *
     * @return array<string, array{today:int, age_sec:int, who:string}>
     */
    private function telemetryStats(int $tenantId, int $mapId): array
    {
        $out = [];
        try {
            $journal = new AtakTelemetryJournalService();
            $latest = $journal->latestId($tenantId, $mapId);
            $cursor = max(0, $latest - 4000);
            $midnight = strtotime('today') ?: 0;
            for ($i = 0; $i < 10; $i++) {
                $batch = $journal->listAfter($tenantId, $mapId, $cursor, 500);
                if ($batch === []) {
                    break;
                }
                foreach ($batch as $ev) {
                    $cursor = max($cursor, (int) ($ev['id'] ?? 0));
                    $type = (string) ($ev['type'] ?? '');
                    $ts = (int) ($ev['ts'] ?? 0);
                    if ($type === '' || $ts <= 0) {
                        continue;
                    }
                    $who = trim((string) (($ev['data']['call_sign'] ?? '') ?: ''));
                    $o = $out[$type] ?? ['today' => 0, 'age_sec' => PHP_INT_MAX, 'who' => ''];
                    if ($ts >= $midnight) {
                        $o['today']++;
                    }
                    $age = max(0, time() - $ts);
                    if ($age <= $o['age_sec']) {
                        $o['age_sec'] = $age;
                        if ($who !== '') {
                            $o['who'] = $who;
                        }
                    }
                    $out[$type] = $o;
                }
                if (count($batch) < 500) {
                    break;
                }
            }
        } catch (Throwable $e) {
            error_log('[overwatch-intel] telemetry ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Journal d'activité (fichier) : entrées repérées par meta.kind.
     *
     * @return array<string, array{today:int, age_sec:int, who:string}>
     */
    private function activityStats(int $tenantId, int $mapId): array
    {
        $out = [];
        try {
            $events = (new AtakActivityLogService())->listRecent($tenantId, $mapId, 400);
            $midnight = strtotime('today') ?: 0;
            foreach ($events as $ev) {
                $kind = (string) ($ev['meta']['kind'] ?? '');
                if ($kind === '') {
                    continue;
                }
                $ts = strtotime((string) ($ev['at'] ?? '')) ?: 0;
                $o = $out[$kind] ?? ['today' => 0, 'age_sec' => PHP_INT_MAX, 'who' => ''];
                if ($ts >= $midnight) {
                    $o['today']++;
                }
                $age = max(0, time() - $ts);
                if ($age <= $o['age_sec']) {
                    $o['age_sec'] = $age;
                    $o['who'] = (string) ($ev['actor'] ?? '');
                }
                $out[$kind] = $o;
            }
        } catch (Throwable $e) {
            error_log('[overwatch-intel] activity ' . $e->getMessage());
        }

        return $out;
    }

    // ================================================================== Fil de renseignement

    /** GET /api/atak/overwatch/intel-feed?mapId=&types=reco,frs&q=&limit= */
    public function intelFeed(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant($request);
        if ($tenantId === null) {
            return $this->denied();
        }
        $mapId = $this->mapId($request);
        $limit = max(10, min(300, (int) ($request->query('limit') ?: 150)));
        $q = mb_strtolower(trim((string) ($request->query('q') ?? '')));
        $wanted = array_filter(array_map('trim', explode(',', strtolower((string) ($request->query('types') ?? '')))));

        $items = [];
        $want = static fn (string $t): bool => $wanted === [] || in_array($t, $wanted, true);

        if ($want('reco')) {
            foreach ($this->repo->safeFetch(
                'SELECT id, text, tag, confidence, author, pos_x, pos_y, created_at
                 FROM recon_notes WHERE tenant_id = :t AND map_id = :m
                 ORDER BY created_at DESC LIMIT ' . $limit,
                ['t' => $tenantId, 'm' => $mapId]
            ) as $r) {
                $items[] = [
                    'key' => 'reco:' . (int) $r['id'],
                    'type' => 'reco',
                    'type_label' => 'RECO',
                    'subtype' => (string) ($r['tag'] ?? ''),
                    'title' => $this->recoTagLabel((string) ($r['tag'] ?? '')),
                    'text' => (string) ($r['text'] ?? ''),
                    'author' => (string) ($r['author'] ?? ''),
                    'at' => (string) ($r['created_at'] ?? ''),
                    'grid' => '',
                    'pos_x' => $this->num($r['pos_x'] ?? null),
                    'pos_y' => $this->num($r['pos_y'] ?? null),
                    'meta' => array_filter(['Confiance' => $this->confidenceLabel((string) ($r['confidence'] ?? ''))]),
                    'attachments' => [],
                ];
            }
        }

        if ($want('frs') || $want('osint') || $want('sse')) {
            $notes = $this->repo->safeFetch(
                'SELECT n.id, n.reference_code, n.note_kind, n.title, n.body, n.observed_at, n.created_at, n.place_label,
                        n.grid_reference, n.pos_x, n.pos_y, n.urgency, n.intel_source, n.status, n.origin,
                        n.author_label, n.author_unit, n.source_reliability, n.info_credibility,
                        (SELECT COUNT(*) FROM sse_field_note_attachments a WHERE a.note_id = n.id AND a.tenant_id = n.tenant_id) AS att
                 FROM sse_field_notes n WHERE n.tenant_id = :t
                 ORDER BY n.created_at DESC LIMIT ' . $limit,
                ['t' => $tenantId]
            );
            $attByNote = [];
            $ids = array_map(static fn (array $n): int => (int) $n['id'], array_filter($notes, static fn (array $n): bool => (int) ($n['att'] ?? 0) > 0));
            if ($ids !== []) {
                foreach ($this->repo->safeFetch(
                    'SELECT note_id, file_path, mime_type, caption, kind FROM sse_field_note_attachments
                     WHERE tenant_id = :t AND note_id IN (' . implode(',', array_map('intval', $ids)) . ')
                     ORDER BY id ASC',
                    ['t' => $tenantId]
                ) as $a) {
                    $path = (string) ($a['file_path'] ?? '');
                    if ($path === '') {
                        continue;
                    }
                    $attByNote[(int) $a['note_id']][] = [
                        'url' => function_exists('user_media_public_url') ? (string) user_media_public_url($path) : $path,
                        'is_image' => str_starts_with((string) ($a['mime_type'] ?? ''), 'image/'),
                        'caption' => (string) ($a['caption'] ?? ''),
                    ];
                }
            }
            foreach ($notes as $n) {
                $source = strtoupper((string) ($n['intel_source'] ?? ''));
                // Téléphone (atak / arma) = FRS ; saisie au portail SSE = fiche SSE ; source ouverte = OSINT.
                $origin = strtolower((string) ($n['origin'] ?? ''));
                $type = $source === 'OSINT' ? 'osint' : (in_array($origin, ['atak', 'arma'], true) ? 'frs' : 'sse');
                if (!$want($type)) {
                    continue;
                }
                $items[] = [
                    'key' => 'note:' . (int) $n['id'],
                    'type' => $type,
                    'type_label' => $type === 'osint' ? 'OSINT' : (string) ($n['note_kind'] ?? 'FRS'),
                    'subtype' => (string) ($n['note_kind'] ?? ''),
                    'title' => (string) (($n['title'] ?? '') !== '' ? $n['title'] : ($n['reference_code'] ?? '')),
                    'text' => (string) ($n['body'] ?? ''),
                    'author' => trim((string) ($n['author_label'] ?? '') . ((string) ($n['author_unit'] ?? '') !== '' ? ' · ' . $n['author_unit'] : '')),
                    'at' => (string) ($n['created_at'] ?? $n['observed_at'] ?? ''),
                    'grid' => (string) ($n['grid_reference'] ?? ''),
                    'pos_x' => $this->num($n['pos_x'] ?? null),
                    'pos_y' => $this->num($n['pos_y'] ?? null),
                    'meta' => array_filter([
                        'Référence' => (string) ($n['reference_code'] ?? ''),
                        'Observé' => (string) ($n['observed_at'] ?? ''),
                        'Lieu' => (string) ($n['place_label'] ?? ''),
                        'Urgence' => (string) ($n['urgency'] ?? ''),
                        'Source' => $source,
                        'Cotation' => trim((string) ($n['source_reliability'] ?? '') . (string) ($n['info_credibility'] ?? '')),
                        'Statut' => (string) ($n['status'] ?? ''),
                    ]),
                    'attachments' => $attByNote[(int) $n['id']] ?? [],
                ];
            }
        }

        if ($want('photo')) {
            foreach ($this->repo->safeFetch(
                "SELECT id, author_callsign, caption, image_path, grid_ref, pos_x, pos_y, device_type, captured_at, created_at
                 FROM recon_images WHERE tenant_id = :t AND caption IS NOT NULL AND caption <> ''
                   AND UPPER(COALESCE(device_type, '')) <> 'HELMET'
                 ORDER BY created_at DESC LIMIT " . $limit,
                ['t' => $tenantId]
            ) as $r) {
                $url = ReconImageStorage::publicUrl(basename((string) ($r['image_path'] ?? '')));
                $items[] = [
                    'key' => 'photo:' . (int) $r['id'],
                    'type' => 'photo',
                    'type_label' => 'PHOTO',
                    'subtype' => (string) ($r['device_type'] ?? ''),
                    'title' => 'Légende photo',
                    'text' => (string) ($r['caption'] ?? ''),
                    'author' => (string) ($r['author_callsign'] ?? ''),
                    'at' => (string) ($r['created_at'] ?? ''),
                    'grid' => (string) ($r['grid_ref'] ?? ''),
                    'pos_x' => $this->num($r['pos_x'] ?? null),
                    'pos_y' => $this->num($r['pos_y'] ?? null),
                    'meta' => array_filter(['Prise de vue' => (string) ($r['captured_at'] ?? '')]),
                    'attachments' => $url !== '' ? [['url' => $url, 'is_image' => true, 'caption' => '']] : [],
                ];
            }
        }

        if ($want('report')) {
            $tr = $this->repo->columns('atak_tactical_reports');
            if ($tr !== []) {
                $mapClause = in_array('context_id', $tr, true) ? ' AND context_id = :m' : '';
                $deleted = in_array('deleted_at', $tr, true) ? ' AND deleted_at IS NULL' : '';
                foreach ($this->repo->safeFetch(
                    'SELECT id, report_type, summary, details, remarks, submitter_callsign, grid_reference, pos_x, pos_y, priority, created_at
                     FROM atak_tactical_reports WHERE tenant_id = :t' . $mapClause . $deleted . '
                     ORDER BY created_at DESC LIMIT ' . $limit,
                    $mapClause !== '' ? ['t' => $tenantId, 'm' => $mapId] : ['t' => $tenantId]
                ) as $r) {
                    $text = trim(implode("\n", array_filter([(string) ($r['details'] ?? ''), (string) ($r['remarks'] ?? '')])));
                    $items[] = [
                        'key' => 'report:' . (int) $r['id'],
                        'type' => 'report',
                        'type_label' => strtoupper((string) ($r['report_type'] ?? 'CR')),
                        'subtype' => (string) ($r['report_type'] ?? ''),
                        'title' => (string) ($r['summary'] ?? ''),
                        'text' => $text !== '' ? $text : (string) ($r['summary'] ?? ''),
                        'author' => (string) ($r['submitter_callsign'] ?? ''),
                        'at' => (string) ($r['created_at'] ?? ''),
                        'grid' => (string) ($r['grid_reference'] ?? ''),
                        'pos_x' => $this->num($r['pos_x'] ?? null),
                        'pos_y' => $this->num($r['pos_y'] ?? null),
                        'meta' => array_filter(['Priorité' => (string) ($r['priority'] ?? '')]),
                        'attachments' => [],
                    ];
                }
            }
        }

        if ($want('wanted')) {
            try {
                foreach ((new \App\Services\Sse\SseWantedNoticeService())->forTenant($tenantId, 40) as $w) {
                    $items[] = [
                        'key' => 'wanted:' . (string) ($w['ref'] ?? ''),
                        'type' => 'wanted',
                        'type_label' => 'RECHERCHÉ',
                        'subtype' => (string) ($w['kind'] ?? ''),
                        'title' => (string) ($w['name'] ?? '') . (($w['alias'] ?? '') !== '' ? ' « ' . $w['alias'] . ' »' : ''),
                        'text' => trim((string) ($w['level'] ?? '') . "\n" . (string) ($w['details'] ?? '')),
                        'author' => 'Cellule SSE',
                        'at' => (string) ($w['updated_at'] ?? ''),
                        'grid' => '',
                        'pos_x' => null,
                        'pos_y' => null,
                        'meta' => array_filter(['Référence' => (string) ($w['ref'] ?? '')]),
                        'attachments' => ($w['photo'] ?? '') !== '' ? [['url' => (string) $w['photo'], 'is_image' => true, 'caption' => '']] : [],
                    ];
                }
            } catch (Throwable $e) {
                error_log('[overwatch-intel] wanted ' . $e->getMessage());
            }
        }

        if ($q !== '') {
            $items = array_values(array_filter($items, static function (array $it) use ($q): bool {
                $hay = mb_strtolower($it['type_label'] . ' ' . $it['title'] . ' ' . $it['text'] . ' ' . $it['author'] . ' ' . $it['grid'] . ' ' . implode(' ', $it['meta']));

                return str_contains($hay, $q);
            }));
        }
        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));
        $counts = [];
        foreach ($items as $it) {
            $counts[$it['type']] = ($counts[$it['type']] ?? 0) + 1;
        }

        return Response::json([
            'ok' => true,
            'mapId' => $mapId,
            'items' => array_slice($items, 0, $limit),
            'counts' => $counts,
            'types' => [
                ['value' => 'reco', 'label' => 'RECO'],
                ['value' => 'frs', 'label' => 'FRS / FRM'],
                ['value' => 'osint', 'label' => 'OSINT'],
                ['value' => 'sse', 'label' => 'SSE'],
                ['value' => 'report', 'label' => 'Comptes rendus'],
                ['value' => 'photo', 'label' => 'Légendes photo'],
                ['value' => 'wanted', 'label' => 'Avis de recherche'],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    // ================================================================== Anneaux de géolocalisation

    /** GET /api/atak/overwatch/geoloc-rings?mapId= */
    public function ringsIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant($request);
        if ($tenantId === null) {
            return $this->denied();
        }
        $mapId = $this->mapId($request);

        return Response::json(['ok' => true, 'rings' => $this->repo->rings($tenantId, $mapId)])->header('Cache-Control', 'no-store');
    }

    /** POST /api/atak/overwatch/geoloc-rings { pos_x, pos_y, radius_m, label } */
    public function ringsStore(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant($request);
        $guard = $this->guardWrite($request, $tenantId);
        if ($guard !== null) {
            return $guard;
        }
        $body = $this->body();
        $mapId = $this->mapId($request, $body);
        $x = $body['pos_x'] ?? $body['x'] ?? null;
        $y = $body['pos_y'] ?? $body['y'] ?? null;
        if (!is_numeric($x) || !is_numeric($y)) {
            return Response::json(['ok' => false, 'error' => 'Position manquante : cliquez la carte ou saisissez une grille.'], 422);
        }
        $id = $this->repo->addRing((int) $tenantId, $mapId, [
            'label' => (string) ($body['label'] ?? ''),
            'pos_x' => (float) $x,
            'pos_y' => (float) $y,
            'radius_m' => (int) ($body['radius_m'] ?? $body['radius'] ?? 150),
            'grid_ref' => (string) ($body['grid_ref'] ?? ''),
            'source' => 'web',
            'author' => $this->operatorLabel(),
        ]);

        return Response::json(['ok' => $id > 0, 'id' => $id], $id > 0 ? 201 : 500);
    }

    /** POST /api/atak/overwatch/geoloc-rings/{id}/supprimer  ({id} = « tout » pour vider) */
    public function ringsDelete(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenant($request);
        $guard = $this->guardWrite($request, $tenantId);
        if ($guard !== null) {
            return $guard;
        }
        $mapId = $this->mapId($request, $this->body());
        $raw = (string) ($params['id'] ?? '');
        if ($raw === 'tout') {
            return Response::json(['ok' => true, 'removed' => $this->repo->clearRings((int) $tenantId, $mapId)]);
        }

        return Response::json(['ok' => $this->repo->deleteRing((int) $tenantId, $mapId, (int) $raw)]);
    }

    // ================================================================== Outils

    private function tenant(Request $request): ?int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        $sid = (int) Session::get('tenant_id');

        return $sid > 0 ? $sid : null;
    }

    private function denied(): Response
    {
        return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
    }

    private function guardWrite(Request $request, ?int $tenantId): ?Response
    {
        if ($tenantId === null || $this->userId() === null) {
            return $this->denied();
        }
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($this->body()['_csrf_token'] ?? ''));
        if (!Csrf::validate($token)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée, rechargez la page.'], 419);
        }

        return null;
    }

    private function userId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    private function operatorLabel(): string
    {
        $name = trim((string) Session::get('display_name', ''));

        return $name !== '' ? $name : 'Poste Athena';
    }

    /** @param array<string, mixed>|null $body */
    private function mapId(Request $request, ?array $body = null): int
    {
        $raw = $body['mapId'] ?? $body['map_id'] ?? $request->query('mapId');
        $id = (int) ($raw ?? 1);

        return $id > 0 ? $id : 1;
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }
        $decoded = json_decode(HttpJsonBody::rawJson(), true);

        return $this->bodyCache = is_array($decoded) ? $decoded : $_POST;
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return (abs($f) < 0.5) ? null : $f;
    }

    private function recoTagLabel(string $tag): string
    {
        if (class_exists(\App\Support\ReconNoteCatalog::class) && method_exists(\App\Support\ReconNoteCatalog::class, 'tagLabel')) {
            try {
                return (string) \App\Support\ReconNoteCatalog::tagLabel($tag);
            } catch (Throwable) {
            }
        }

        return $tag !== '' ? ucfirst(str_replace('_', ' ', $tag)) : 'Observation';
    }

    private function confidenceLabel(string $c): string
    {
        try {
            $label = \App\Support\ReconNoteCatalog::confidenceLabel($c);
        } catch (Throwable) {
            $label = '';
        }

        return $label !== '' ? $label : str_replace('_', ' ', $c);
    }
}
