<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakModulesSchema;

/**
 * Repository pour le tracking enrichi des véhicules et assets lourds
 */
class AtakVehicleTrackingRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        AtakModulesSchema::ensure();
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Indicatif du véhicule (clé de l'upsert) : vehicle_callsign, sinon callsign, vehicle_name, vehicle_id ou vehicle_type ; '' si absent.
     */
    public static function resolveCallsign(array $data): string
    {
        foreach (['vehicle_callsign', 'callsign', 'vehicle_name', 'vehicle_id', 'vehicle_type'] as $key) {
            $value = $data[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr(trim((string) $value), 0, 100);
            }
        }
        return '';
    }

    /**
     * Crée ou met à jour un véhicule (upsert)
     *
     * @throws \InvalidArgumentException si aucun indicatif n'est fourni
     */
    public function upsert(array $data): int
    {
        $callsign = self::resolveCallsign($data);
        if ($callsign === '') {
            throw new \InvalidArgumentException('vehicle_callsign requis');
        }
        $data['vehicle_callsign'] = $callsign;
        $data = self::sanitizeColumns(self::normalizeEnums($data));

        // Vérifier si le véhicule existe déjà
        $existing = $this->findByCallsign(
            (int) ($data['tenant_id'] ?? 0),
            (int) ($data['context_id'] ?? 0),
            $callsign
        );

        if ($existing) {
            $this->update((int) $existing['id'], $data);
            return (int) $existing['id'];
        }

        // Véhicule inconnu sans position (ex. drone relâché/détruit avant sa première remontée) :
        // pos_x/pos_y sont NOT NULL, rien à créer. 0 = ignoré, pas une erreur.
        if (!is_numeric($data['pos_x'] ?? null) || !is_numeric($data['pos_y'] ?? null)) {
            return 0;
        }

        try {
            return $this->create($data);
        } catch (\PDOException $e) {
            // Deux téléphones remontent le même véhicule au même instant (équipage) : le second INSERT tombe sur la clé
            // unique (1062) ; on met à jour la ligne créée par le premier au lieu de renvoyer vehicle_exception.
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            $again = $this->findByCallsign((int) ($data['tenant_id'] ?? 0), (int) ($data['context_id'] ?? 0), $callsign);
            if (!$again) {
                throw $e;
            }
            $this->update((int) $again['id'], $data);

            return (int) $again['id'];
        }
    }

    /** Colonnes texte et longueur maximale (VARCHAR de la table). */
    private const STRING_COLUMNS = [
        'vehicle_callsign' => 100, 'vehicle_name' => 200, 'vehicle_type' => 100, 'unit_assigned' => 200,
        'crew_commander_callsign' => 100, 'mission_type' => 40, 'mission_description' => 20000,
    ];
    /** Entiers positifs : [nullable]. */
    private const UINT_COLUMNS = [
        'crew_commander_user_id' => true, 'crew_count' => false, 'crew_max' => true,
        'passenger_count' => false, 'passenger_max' => true, 'fuel_capacity' => true,
    ];
    /** Décimaux : [min, max] (DECIMAL de la table). */
    private const DECIMAL_COLUMNS = [
        'pos_x' => [-99999999.0, 99999999.0], 'pos_y' => [-99999999.0, 99999999.0], 'pos_z' => [-99999999.0, 99999999.0],
        'destination_pos_x' => [-99999999.0, 99999999.0], 'destination_pos_y' => [-99999999.0, 99999999.0],
        'heading' => [0.0, 360.0], 'speed' => [-999999.0, 999999.0], 'fuel_consumption_rate' => [-999999.0, 999999.0],
        'fuel_percent' => [0.0, 100.0], 'ammo_percent' => [0.0, 100.0], 'engine_health' => [0.0, 100.0],
        'hull_health' => [0.0, 100.0], 'tracks_wheels_health' => [0.0, 100.0], 'turret_health' => [0.0, 100.0],
    ];

    /**
     * Ramène chaque valeur du mod au type de sa colonne : texte coupé à la longueur de la colonne (UTF-8 réparé),
     * nombres bornés (un indicatif Steam dans crew_commander_user_id, une vitesse de jet, « n/a » ou NaN faisaient
     * échouer l'écriture en SQL strict : vehicle_exception, HTTP 500 dans le lot de télémétrie), booléens en 0 / 1.
     * Une valeur illisible est retirée (la ligne garde l'ancienne) ou mise à NULL quand la colonne l'accepte.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function sanitizeColumns(array $data): array
    {
        foreach (self::STRING_COLUMNS as $col => $max) {
            if (!array_key_exists($col, $data) || $data[$col] === null) {
                continue;
            }
            $v = $data[$col];
            if (is_bool($v)) {
                $v = $v ? '1' : '0';
            }
            if (!is_scalar($v)) {
                unset($data[$col]);
                continue;
            }
            $v = mb_convert_encoding(trim((string) $v), 'UTF-8', 'UTF-8');
            $data[$col] = $v === '' && $col !== 'vehicle_callsign' ? null : mb_substr($v, 0, $max);
        }
        foreach (self::UINT_COLUMNS as $col => $nullable) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $v = $data[$col];
            $ok = (is_int($v) || is_float($v) || (is_string($v) && is_numeric(trim($v))))
                && is_finite((float) $v) && (float) $v >= 0 && (float) $v <= 4294967295;
            if ($ok) {
                $data[$col] = (int) round((float) $v);
            } elseif ($nullable) {
                $data[$col] = null;
            } else {
                unset($data[$col]);
            }
        }
        foreach (self::DECIMAL_COLUMNS as $col => [$min, $max]) {
            if (!array_key_exists($col, $data) || $data[$col] === null) {
                continue;
            }
            $v = $data[$col];
            if (is_string($v)) {
                $v = str_replace(',', '.', trim($v));
            }
            if (!(is_int($v) || is_float($v) || (is_string($v) && is_numeric($v))) || !is_finite((float) $v)) {
                if (in_array($col, ['pos_x', 'pos_y'], true)) {
                    unset($data[$col]);
                } else {
                    $data[$col] = null;
                }
                continue;
            }
            $f = (float) $v;
            if ($col === 'heading') {
                $f = fmod(fmod($f, 360.0) + 360.0, 360.0);
            }
            $data[$col] = round(max($min, min($max, $f)), 4);
        }
        if (array_key_exists('is_visible', $data)) {
            $v = $data['is_visible'];
            $data['is_visible'] = (is_string($v) ? !in_array(strtolower(trim($v)), ['', '0', 'false', 'no', 'off'], true) : (bool) $v) ? 1 : 0;
        }

        return $data;
    }

    private const VEHICLE_CLASSES = [
        'LIGHT_VEHICLE', 'TRUCK', 'APC', 'IFV', 'TANK', 'ARTILLERY',
        'HELICOPTER', 'FIXED_WING', 'UAV', 'BOAT', 'STATIC', 'OTHER',
    ];
    private const SIDES = ['BLUFOR', 'OPFOR', 'INDEPENDENT', 'CIVILIAN'];
    private const STATUSES = ['OPERATIONAL', 'DAMAGED', 'IMMOBILIZED', 'DESTROYED', 'ABANDONED'];

    /**
     * Ramène les valeurs du mod dans les ENUM de la table : une valeur hors liste
     * fait échouer l'écriture en mode SQL strict (vehicle_exception côté lot télémétrie).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeEnums(array $data): array
    {
        if (array_key_exists('vehicle_class', $data)) {
            $c = strtoupper(trim((string) $data['vehicle_class']));
            $c = match ($c) {
                'MORTAR' => 'ARTILLERY',
                'INFANTRY', 'CAR', 'MRAP' => 'LIGHT_VEHICLE',
                'HELI', 'HELO' => 'HELICOPTER',
                'PLANE', 'JET' => 'FIXED_WING',
                'DRONE' => 'UAV',
                'SHIP' => 'BOAT',
                default => $c,
            };
            $data['vehicle_class'] = in_array($c, self::VEHICLE_CLASSES, true) ? $c : 'OTHER';
        }
        if (array_key_exists('side', $data)) {
            $s = strtoupper(trim((string) $data['side']));
            $s = match ($s) {
                'WEST' => 'BLUFOR',
                'EAST' => 'OPFOR',
                'GUER', 'RESISTANCE', 'INDEP', 'INDFOR' => 'INDEPENDENT',
                'CIV' => 'CIVILIAN',
                default => $s,
            };
            if (in_array($s, self::SIDES, true)) {
                $data['side'] = $s;
            } else {
                unset($data['side']);
            }
        }
        if (array_key_exists('status', $data)) {
            $st = strtoupper(trim((string) $data['status']));
            if (in_array($st, self::STATUSES, true)) {
                $data['status'] = $st;
            } else {
                unset($data['status']);
            }
        }
        if (array_key_exists('mission_type', $data)) {
            // Colonne passée en VARCHAR(40) (GPS_BEACON, types libres via COMSPEC_MissionType).
            $m = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', trim((string) $data['mission_type'])) ?? '');
            $data['mission_type'] = $m === '' ? null : mb_substr($m, 0, 40);
        }

        return $data;
    }

    /**
     * Crée un nouveau véhicule
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO atak_vehicle_tracking (
            tenant_id, context_id, vehicle_callsign, vehicle_name,
            vehicle_class, vehicle_type, side, unit_assigned,
            crew_commander_callsign, crew_commander_user_id, crew_count, crew_max,
            passenger_count, passenger_max, passengers_json,
            pos_x, pos_y, pos_z, heading, speed,
            status, fuel_percent, fuel_capacity, fuel_consumption_rate,
            ammo_percent, weapons, engine_health, hull_health, tracks_wheels_health, turret_health,
            destination_pos_x, destination_pos_y, mission_type, mission_description,
            properties, is_visible, last_seen_at
        ) VALUES (
            :tenant_id, :context_id, :vehicle_callsign, :vehicle_name,
            :vehicle_class, :vehicle_type, :side, :unit_assigned,
            :crew_commander_callsign, :crew_commander_user_id, :crew_count, :crew_max,
            :passenger_count, :passenger_max, :passengers_json,
            :pos_x, :pos_y, :pos_z, :heading, :speed,
            :status, :fuel_percent, :fuel_capacity, :fuel_consumption_rate,
            :ammo_percent, :weapons, :engine_health, :hull_health, :tracks_wheels_health, :turret_health,
            :destination_pos_x, :destination_pos_y, :mission_type, :mission_description,
            :properties, :is_visible, :last_seen_at
        )";

        $params = $this->prepareParams($data);
        $vehicleId = (int) $this->db->insert($sql, $params);

        // Sauvegarder position initiale dans historique
        $this->savePositionHistory($vehicleId, $data);

        return $vehicleId;
    }

    /**
     * Met à jour un véhicule
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'vehicle_name', 'vehicle_class', 'vehicle_type', 'side', 'unit_assigned',
            'crew_commander_callsign', 'crew_commander_user_id', 'crew_count', 'crew_max',
            'passenger_count', 'passenger_max',
            'pos_x', 'pos_y', 'pos_z', 'heading', 'speed',
            'status', 'fuel_percent', 'fuel_capacity', 'fuel_consumption_rate',
            'ammo_percent', 'engine_health', 'hull_health', 'tracks_wheels_health', 'turret_health',
            'destination_pos_x', 'destination_pos_y', 'mission_type', 'mission_description',
            'is_visible'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        $jsonFields = ['passengers_json', 'weapons', 'properties'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = self::encodeJsonField($data[$field]);
            }
        }

        // Toujours mettre à jour last_seen_at
        $fields[] = "last_seen_at = NOW()";

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE atak_vehicle_tracking SET " . implode(', ', $fields) . " WHERE id = :id";
        $success = $this->db->execute($sql, $params) > 0;

        // Sauvegarder dans historique si position changée
        if ($success && (isset($data['pos_x']) || isset($data['pos_y']))) {
            $this->savePositionHistory($id, $data);
        }

        return $success;
    }

    /**
     * Prépare les paramètres pour insertion
     */
    private function prepareParams(array $data): array
    {
        return [
            'tenant_id' => $data['tenant_id'] ?? null,
            'context_id' => $data['context_id'] ?? null,
            'vehicle_callsign' => $data['vehicle_callsign'] ?? null,
            'vehicle_name' => $data['vehicle_name'] ?? null,
            'vehicle_class' => $data['vehicle_class'] ?? 'OTHER',
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'side' => $data['side'] ?? 'BLUFOR',
            'unit_assigned' => $data['unit_assigned'] ?? null,
            'crew_commander_callsign' => $data['crew_commander_callsign'] ?? null,
            'crew_commander_user_id' => $data['crew_commander_user_id'] ?? null,
            'crew_count' => $data['crew_count'] ?? 0,
            'crew_max' => $data['crew_max'] ?? null,
            'passenger_count' => $data['passenger_count'] ?? 0,
            'passenger_max' => $data['passenger_max'] ?? null,
            'passengers_json' => self::encodeJsonField($data['passengers_json'] ?? null),
            'pos_x' => $data['pos_x'] ?? null,
            'pos_y' => $data['pos_y'] ?? null,
            'pos_z' => $data['pos_z'] ?? null,
            'heading' => $data['heading'] ?? null,
            'speed' => $data['speed'] ?? null,
            'status' => $data['status'] ?? 'OPERATIONAL',
            'fuel_percent' => $data['fuel_percent'] ?? null,
            'fuel_capacity' => $data['fuel_capacity'] ?? null,
            'fuel_consumption_rate' => $data['fuel_consumption_rate'] ?? null,
            'ammo_percent' => $data['ammo_percent'] ?? null,
            'weapons' => isset($data['weapons']) ? self::encodeJsonField($data['weapons']) : null,
            'engine_health' => $data['engine_health'] ?? null,
            'hull_health' => $data['hull_health'] ?? null,
            'tracks_wheels_health' => $data['tracks_wheels_health'] ?? null,
            'turret_health' => $data['turret_health'] ?? null,
            'destination_pos_x' => $data['destination_pos_x'] ?? null,
            'destination_pos_y' => $data['destination_pos_y'] ?? null,
            'mission_type' => $data['mission_type'] ?? null,
            'mission_description' => $data['mission_description'] ?? null,
            'properties' => isset($data['properties']) ? self::encodeJsonField($data['properties']) : null,
            'is_visible' => $data['is_visible'] ?? 1,
            'last_seen_at' => $data['last_seen_at'] ?? date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Sauvegarde position dans historique
     */
    private function savePositionHistory(int $vehicleId, array $data): void
    {
        if (!isset($data['pos_x']) || !isset($data['pos_y'])) {
            return;
        }

        // Chaque téléphone d’équipage remonte le même véhicule : pas de nouvelle ligne si le dernier point
        // a moins de 10 s et que le véhicule a bougé de moins de 15 m (trajet de relecture inchangé à l’œil).
        try {
            $last = $this->db->fetchOne(
                'SELECT pos_x, pos_y, TIMESTAMPDIFF(SECOND, recorded_at, NOW()) AS age_sec
                 FROM atak_vehicle_position_history
                 WHERE vehicle_tracking_id = :vehicle_id
                 ORDER BY id DESC LIMIT 1',
                ['vehicle_id' => $vehicleId]
            );
            if (is_array($last) && $last['age_sec'] !== null && (int) $last['age_sec'] < 10
                && is_numeric($data['pos_x']) && is_numeric($data['pos_y'])
                && hypot((float) $data['pos_x'] - (float) $last['pos_x'], (float) $data['pos_y'] - (float) $last['pos_y']) < 15.0
            ) {
                return;
            }
        } catch (\Throwable) {
        }

        $sql = "INSERT INTO atak_vehicle_position_history (
            vehicle_tracking_id, pos_x, pos_y, pos_z, heading, speed, fuel_percent, ammo_percent
        ) VALUES (
            :vehicle_id, :pos_x, :pos_y, :pos_z, :heading, :speed, :fuel_percent, :ammo_percent
        )";

        // Historique : secondaire, son échec ne doit pas faire échouer la mise à jour du véhicule.
        try {
            $this->insertHistory($sql, $vehicleId, $data);
        } catch (\Throwable $e) {
            error_log('[atak/vehicles] historique non écrit : ' . mb_substr($e->getMessage(), 0, 200));
        }
    }

    /** @param array<string, mixed> $data */
    private function insertHistory(string $sql, int $vehicleId, array $data): void
    {
        $this->db->insert($sql, [
            'vehicle_id' => $vehicleId,
            'pos_x' => $data['pos_x'],
            'pos_y' => $data['pos_y'],
            'pos_z' => $data['pos_z'] ?? null,
            'heading' => $data['heading'] ?? null,
            'speed' => $data['speed'] ?? null,
            'fuel_percent' => $data['fuel_percent'] ?? null,
            'ammo_percent' => $data['ammo_percent'] ?? null,
        ]);
    }

    /**
     * Liste les véhicules actifs
     */
    public function listActive(int $tenantId, int $contextId, array $filters = []): array
    {
        $where = ['tenant_id = :tenant_id', 'context_id = :context_id'];
        $params = ['tenant_id' => $tenantId, 'context_id' => $contextId];

        if (!empty($filters['vehicle_class'])) {
            if (is_array($filters['vehicle_class'])) {
                $placeholders = [];
                foreach ($filters['vehicle_class'] as $i => $class) {
                    $key = "vehicle_class_{$i}";
                    $placeholders[] = ":{$key}";
                    $params[$key] = $class;
                }
                $where[] = 'vehicle_class IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = 'vehicle_class = :vehicle_class';
                $params['vehicle_class'] = $filters['vehicle_class'];
            }
        }

        if (!empty($filters['side'])) {
            $where[] = 'side = :side';
            $params['side'] = $filters['side'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['fuel_critical'])) {
            $where[] = 'is_fuel_critical = :fuel_critical';
            $params['fuel_critical'] = (bool) $filters['fuel_critical'];
        }

        if (isset($filters['damaged'])) {
            $where[] = 'is_damaged = :damaged';
            $params['damaged'] = (bool) $filters['damaged'];
        }

        $where[] = "status <> 'DESTROYED'";
        $whereClause = implode(' AND ', $where);
        $orderBy = $filters['order_by'] ?? 'last_seen_at DESC';
        $allowedOrder = ['last_seen_at DESC', 'last_seen_at ASC', 'vehicle_callsign ASC'];
        if (!in_array($orderBy, $allowedOrder, true)) {
            $orderBy = 'last_seen_at DESC';
        }
        $limit = isset($filters['limit']) ? max(1, min(500, (int) $filters['limit'])) : 200;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

        // Table réelle : la vue (sous-requête d’entretien par ligne) casse la
        // relève carte si elle manque, et saturait MySQL toutes les 3 s.
        // age_sec : fraîcheur calculée côté SQL (pas de fuseau navigateur) — drones périmés > 30 s.
        $sql = "SELECT *, TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) AS age_sec
                FROM atak_vehicle_tracking
                WHERE {$whereClause}
                ORDER BY {$orderBy}
                LIMIT {$limit} OFFSET {$offset}";

        try {
            $results = $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }

        foreach ($results as &$row) {
            $this->decodeJsonFields($row);
        }

        return $results;
    }

    /**
     * Trouve un véhicule par callsign
     */
    public function findByCallsign(int $tenantId, int $contextId, string $callsign): ?array
    {
        $sql = "SELECT * FROM atak_vehicle_tracking
                WHERE tenant_id = :tenant_id
                  AND context_id = :context_id
                  AND vehicle_callsign = :callsign";

        try {
            $vehicle = $this->db->fetchOne($sql, [
                'tenant_id' => $tenantId,
                'context_id' => $contextId,
                'callsign' => $callsign
            ]);
        } catch (\Throwable) {
            return null;
        }

        if ($vehicle) {
            $this->decodeJsonFields($vehicle);
        }

        return $vehicle ?: null;
    }

    /**
     * Trouve un véhicule par ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM atak_vehicle_tracking WHERE id = :id";
        try {
            $vehicle = $this->db->fetchOne($sql, ['id' => $id]);
        } catch (\Throwable) {
            return null;
        }

        if ($vehicle) {
            $this->decodeJsonFields($vehicle);
        }

        return $vehicle ?: null;
    }

    /**
     * Encode un champ JSON sans double-encodage si la valeur est déjà une chaîne JSON.
     */
    private static function encodeJsonField(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }
        // Noms de joueurs mal encodés (passagers) : caractère de remplacement au lieu d'un échec (false → JSON invalide).
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $encoded === false ? null : $encoded;
    }

    /**
     * Décode les champs JSON
     */
    private function decodeJsonFields(array &$row): void
    {
        $jsonFields = ['passengers_json', 'weapons', 'properties'];
        foreach ($jsonFields as $field) {
            if (!empty($row[$field])) {
                $row[$field] = json_decode($row[$field], true);
            }
        }
    }

    /**
     * Crée un événement véhicule
     */
    public function createEvent(int $vehicleId, array $eventData): int
    {
        $sql = "INSERT INTO atak_vehicle_events (
            vehicle_tracking_id, event_type, event_description,
            event_pos_x, event_pos_y, actor_callsign, actor_user_id, event_data
        ) VALUES (
            :vehicle_id, :event_type, :event_description,
            :event_pos_x, :event_pos_y, :actor_callsign, :actor_user_id, :event_data
        )";

        return (int) $this->db->insert($sql, [
            'vehicle_id' => $vehicleId,
            'event_type' => $eventData['event_type'] ?? 'MOVED',
            'event_description' => $eventData['event_description'] ?? null,
            'event_pos_x' => $eventData['event_pos_x'] ?? null,
            'event_pos_y' => $eventData['event_pos_y'] ?? null,
            'actor_callsign' => $eventData['actor_callsign'] ?? null,
            'actor_user_id' => $eventData['actor_user_id'] ?? null,
            'event_data' => isset($eventData['event_data']) ? json_encode($eventData['event_data']) : null,
        ]);
    }

    /**
     * Crée une demande de service
     */
    public function createServiceRequest(int $vehicleId, array $requestData): int
    {
        $sql = "INSERT INTO atak_vehicle_service_requests (
            vehicle_tracking_id, request_type, priority, request_details,
            requested_by_callsign, service_pos_x, service_pos_y
        ) VALUES (
            :vehicle_id, :request_type, :priority, :request_details,
            :requested_by_callsign, :service_pos_x, :service_pos_y
        )";

        return (int) $this->db->insert($sql, [
            'vehicle_id' => $vehicleId,
            'request_type' => $requestData['request_type'] ?? 'REFUEL',
            'priority' => $requestData['priority'] ?? 'MEDIUM',
            'request_details' => $requestData['request_details'] ?? null,
            'requested_by_callsign' => $requestData['requested_by_callsign'] ?? null,
            'service_pos_x' => $requestData['service_pos_x'] ?? null,
            'service_pos_y' => $requestData['service_pos_y'] ?? null,
        ]);
    }

    /**
     * Liste les demandes de service en attente
     */
    public function listPendingServiceRequests(int $tenantId, int $contextId): array
    {
        $sql = "SELECT sr.*, v.vehicle_callsign, v.vehicle_class, v.pos_x, v.pos_y
                FROM atak_vehicle_service_requests sr
                INNER JOIN atak_vehicle_tracking v ON sr.vehicle_tracking_id = v.id
                WHERE v.tenant_id = :tenant_id
                  AND v.context_id = :context_id
                  AND sr.status IN ('REQUESTED', 'ACKNOWLEDGED', 'ENROUTE', 'IN_PROGRESS')
                ORDER BY sr.priority DESC, sr.requested_at ASC";

        return $this->db->fetchAll($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);
    }

    /**
     * Compte véhicules par classe
     */
    public function countByClass(int $tenantId, int $contextId): array
    {
        $sql = "SELECT vehicle_class, COUNT(*) as count 
                FROM atak_vehicle_tracking 
                WHERE tenant_id = :tenant_id 
                  AND context_id = :context_id 
                  AND status != 'DESTROYED'
                  AND TIMESTAMPDIFF(MINUTE, last_seen_at, NOW()) <= 30
                GROUP BY vehicle_class";

        return $this->db->fetchAll($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);
    }
}
