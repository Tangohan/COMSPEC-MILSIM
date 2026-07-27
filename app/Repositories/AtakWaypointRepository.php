<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AtakWaypointsSchema;

/**
 * Itinéraires de patrouille et waypoints partagés.
 *
 * Différence avec les marqueurs (`/api/atak/markers`) : un waypoint porte un rang dans un
 * itinéraire et un horodatage d’atteinte. C’est ce qui permet de dire « où en est la
 * patrouille », ce qu’un marqueur seul ne dit pas.
 */
class AtakWaypointRepository
{
    /** Types d’itinéraire acceptés (miroir de l’ENUM SQL). */
    private const ROUTE_TYPES = ['PATROL', 'INFILTRATION', 'EXFILTRATION', 'RESUPPLY', 'MEDEVAC', 'UAV', 'OTHER'];

    /** Statuts d’itinéraire acceptés. */
    private const ROUTE_STATUSES = ['PLANNED', 'ACTIVE', 'COMPLETED', 'ABORTED'];

    /** Types de waypoint acceptés. */
    private const WAYPOINT_TYPES = ['CHECKPOINT', 'RALLY_POINT', 'OVERWATCH', 'ASSAULT_POSITION', 'OBJECTIVE', 'LZ', 'DZ', 'OTHER'];

    /** Niveaux de visibilité acceptés. */
    private const VISIBILITY_LEVELS = ['PUBLIC', 'UNIT', 'COMMAND'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        AtakWaypointsSchema::ensure();
        $this->db = $db ?? Database::getInstance();
    }

    /** @return list<string> */
    public static function routeTypes(): array
    {
        return self::ROUTE_TYPES;
    }

    /** @return list<string> */
    public static function waypointTypes(): array
    {
        return self::WAYPOINT_TYPES;
    }

    /** @return list<string> */
    public static function routeStatuses(): array
    {
        return self::ROUTE_STATUSES;
    }

    /**
     * Crée un itinéraire.
     *
     * @param array<string,mixed> $data
     */
    public function createRoute(int $tenantId, int $contextId, array $data): int
    {
        $sql = 'INSERT INTO atak_waypoint_routes (
            tenant_id, context_id, route_name, route_code, route_type, description,
            assigned_unit, assigned_callsign, status, marker_color,
            is_visible, visibility_level, created_by_user_id, created_by_callsign
        ) VALUES (
            :tenant_id, :context_id, :route_name, :route_code, :route_type, :description,
            :assigned_unit, :assigned_callsign, :status, :marker_color,
            :is_visible, :visibility_level, :created_by_user_id, :created_by_callsign
        )';

        $name = $this->text($data['route_name'] ?? null, 200);

        return (int) $this->db->insert($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'route_name' => $name !== null ? $name : 'Itinéraire',
            'route_code' => $this->text($data['route_code'] ?? null, 50),
            'route_type' => $this->enum($data['route_type'] ?? null, self::ROUTE_TYPES, 'PATROL'),
            'description' => $this->text($data['description'] ?? null, 4000),
            'assigned_unit' => $this->text($data['assigned_unit'] ?? null, 200),
            'assigned_callsign' => $this->text($data['assigned_callsign'] ?? null, 100),
            'status' => $this->enum($data['status'] ?? null, self::ROUTE_STATUSES, 'PLANNED'),
            'marker_color' => $this->text($data['marker_color'] ?? null, 32),
            'is_visible' => array_key_exists('is_visible', $data) ? (int) (bool) $data['is_visible'] : 1,
            'visibility_level' => $this->enum($data['visibility_level'] ?? null, self::VISIBILITY_LEVELS, 'PUBLIC'),
            'created_by_user_id' => $this->positiveIntOrNull($data['created_by_user_id'] ?? null),
            'created_by_callsign' => $this->text($data['created_by_callsign'] ?? null, 100),
        ]);
    }

    /**
     * Liste les itinéraires d’un contexte, chacun avec sa progression.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listRoutes(int $tenantId, int $contextId, array $filters = []): array
    {
        $where = ['r.tenant_id = :tenant_id', 'r.context_id = :context_id', 'r.deleted_at IS NULL'];
        $params = ['tenant_id' => $tenantId, 'context_id' => $contextId];

        $status = $this->enum($filters['status'] ?? null, self::ROUTE_STATUSES, null);
        if ($status !== null) {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }

        $routeType = $this->enum($filters['route_type'] ?? null, self::ROUTE_TYPES, null);
        if ($routeType !== null) {
            $where[] = 'r.route_type = :route_type';
            $params['route_type'] = $routeType;
        }

        $callsign = $this->text($filters['assigned_callsign'] ?? null, 100);
        if ($callsign !== null) {
            $where[] = 'r.assigned_callsign = :assigned_callsign';
            $params['assigned_callsign'] = $callsign;
        }

        if (array_key_exists('is_visible', $filters) && $filters['is_visible'] !== null) {
            $where[] = 'r.is_visible = :is_visible';
            $params['is_visible'] = (int) (bool) $filters['is_visible'];
        }

        $limit = $this->boundedInt($filters['limit'] ?? null, 100, 1, 500);
        $offset = $this->boundedInt($filters['offset'] ?? null, 0, 0, 100000);

        $sql = 'SELECT r.*,
                       (SELECT COUNT(*) FROM atak_waypoints w
                         WHERE w.route_id = r.id AND w.deleted_at IS NULL) AS waypoints_total,
                       (SELECT COUNT(*) FROM atak_waypoints w
                         WHERE w.route_id = r.id AND w.deleted_at IS NULL AND w.reached = 1) AS waypoints_reached,
                       (SELECT MAX(w.reached_at) FROM atak_waypoints w
                         WHERE w.route_id = r.id AND w.deleted_at IS NULL) AS last_reached_at
                FROM atak_waypoint_routes r
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY r.created_at DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$row) {
            $row = $this->decorateRoute($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Un itinéraire et ses points, dans l’ordre de parcours.
     *
     * @return array<string,mixed>|null
     */
    public function findRoute(int $tenantId, int $routeId): ?array
    {
        $sql = 'SELECT * FROM atak_waypoint_routes
                WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL';
        $route = $this->db->fetchOne($sql, ['id' => $routeId, 'tenant_id' => $tenantId]);
        if ($route === null) {
            return null;
        }

        $waypoints = $this->listWaypoints($tenantId, (int) $route['context_id'], ['route_id' => $routeId]);
        $total = count($waypoints);
        $reached = 0;
        foreach ($waypoints as $waypoint) {
            if (!empty($waypoint['reached'])) {
                $reached++;
            }
        }

        $route['waypoints_total'] = $total;
        $route['waypoints_reached'] = $reached;
        $route = $this->decorateRoute($route);
        $route['waypoints'] = $waypoints;
        $route['total_distance_m'] = $this->totalDistance($waypoints);

        return $route;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateRoute(int $tenantId, int $routeId, array $data): bool
    {
        $fields = [];
        $params = ['id' => $routeId, 'tenant_id' => $tenantId];

        $textFields = [
            'route_name' => 200,
            'route_code' => 50,
            'description' => 4000,
            'assigned_unit' => 200,
            'assigned_callsign' => 100,
            'marker_color' => 32,
        ];
        foreach ($textFields as $field => $max) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $this->text($data[$field], $max);
            }
        }

        if (array_key_exists('route_type', $data)) {
            $type = $this->enum($data['route_type'], self::ROUTE_TYPES, null);
            if ($type !== null) {
                $fields[] = 'route_type = :route_type';
                $params['route_type'] = $type;
            }
        }

        if (array_key_exists('visibility_level', $data)) {
            $level = $this->enum($data['visibility_level'], self::VISIBILITY_LEVELS, null);
            if ($level !== null) {
                $fields[] = 'visibility_level = :visibility_level';
                $params['visibility_level'] = $level;
            }
        }

        if (array_key_exists('is_visible', $data)) {
            $fields[] = 'is_visible = :is_visible';
            $params['is_visible'] = (int) (bool) $data['is_visible'];
        }

        if (array_key_exists('status', $data)) {
            $status = $this->enum($data['status'], self::ROUTE_STATUSES, null);
            if ($status !== null) {
                $fields[] = 'status = :status';
                $params['status'] = $status;
                // Les jalons temporels suivent le statut : ils ne sont pas à la main de l’appelant.
                if ($status === 'ACTIVE') {
                    $fields[] = 'started_at = COALESCE(started_at, NOW())';
                } elseif ($status === 'COMPLETED' || $status === 'ABORTED') {
                    $fields[] = 'completed_at = COALESCE(completed_at, NOW())';
                }
            }
        }

        if ($fields === []) {
            return false;
        }
        $fields[] = 'updated_at = NOW()';

        $sql = 'UPDATE atak_waypoint_routes SET ' . implode(', ', $fields)
            . ' WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL';

        return $this->db->execute($sql, $params) > 0;
    }

    public function softDeleteRoute(int $tenantId, int $routeId): bool
    {
        $this->db->execute(
            'UPDATE atak_waypoints SET deleted_at = NOW()
             WHERE route_id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            ['id' => $routeId, 'tenant_id' => $tenantId]
        );

        return $this->db->execute(
            'UPDATE atak_waypoint_routes SET deleted_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            ['id' => $routeId, 'tenant_id' => $tenantId]
        ) > 0;
    }

    /**
     * Ajoute un waypoint. Sans rang fourni, il se place à la fin de l’itinéraire.
     *
     * @param array<string,mixed> $data
     */
    public function addWaypoint(int $tenantId, int $contextId, array $data): int
    {
        $routeId = $this->positiveIntOrNull($data['route_id'] ?? null);
        $sequence = $this->positiveIntOrNull($data['sequence_number'] ?? null)
            ?? $this->nextSequence($tenantId, $contextId, $routeId);

        $sql = 'INSERT INTO atak_waypoints (
            tenant_id, context_id, route_id, sequence_number, label, waypoint_type, description,
            pos_x, pos_y, pos_z, grid_reference, radius_m,
            reached, reached_at, reached_by_user_id, reached_by_callsign, created_by_user_id
        ) VALUES (
            :tenant_id, :context_id, :route_id, :sequence_number, :label, :waypoint_type, :description,
            :pos_x, :pos_y, :pos_z, :grid_reference, :radius_m,
            :reached, :reached_at, :reached_by_user_id, :reached_by_callsign, :created_by_user_id
        )';

        $reached = !empty($data['reached']);

        return (int) $this->db->insert($sql, [
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'route_id' => $routeId,
            'sequence_number' => $sequence,
            'label' => $this->text($data['label'] ?? null, 200),
            'waypoint_type' => $this->enum($data['waypoint_type'] ?? null, self::WAYPOINT_TYPES, 'CHECKPOINT'),
            'description' => $this->text($data['description'] ?? null, 4000),
            'pos_x' => (float) ($data['pos_x'] ?? 0),
            'pos_y' => (float) ($data['pos_y'] ?? 0),
            'pos_z' => isset($data['pos_z']) && $data['pos_z'] !== '' ? (float) $data['pos_z'] : null,
            'grid_reference' => $this->text($data['grid_reference'] ?? null, 50),
            'radius_m' => $this->positiveIntOrNull($data['radius_m'] ?? null),
            'reached' => $reached ? 1 : 0,
            'reached_at' => $reached ? ($this->text($data['reached_at'] ?? null, 32) ?? date('Y-m-d H:i:s')) : null,
            'reached_by_user_id' => $reached ? $this->positiveIntOrNull($data['reached_by_user_id'] ?? null) : null,
            'reached_by_callsign' => $reached ? $this->text($data['reached_by_callsign'] ?? null, 100) : null,
            'created_by_user_id' => $this->positiveIntOrNull($data['created_by_user_id'] ?? null),
        ]);
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listWaypoints(int $tenantId, int $contextId, array $filters = []): array
    {
        $where = ['w.tenant_id = :tenant_id', 'w.context_id = :context_id', 'w.deleted_at IS NULL'];
        $params = ['tenant_id' => $tenantId, 'context_id' => $contextId];

        $routeId = $this->positiveIntOrNull($filters['route_id'] ?? null);
        if ($routeId !== null) {
            $where[] = 'w.route_id = :route_id';
            $params['route_id'] = $routeId;
        } elseif (!empty($filters['orphans_only'])) {
            $where[] = 'w.route_id IS NULL';
        }

        $type = $this->enum($filters['waypoint_type'] ?? null, self::WAYPOINT_TYPES, null);
        if ($type !== null) {
            $where[] = 'w.waypoint_type = :waypoint_type';
            $params['waypoint_type'] = $type;
        }

        if (array_key_exists('reached', $filters) && $filters['reached'] !== null) {
            $where[] = 'w.reached = :reached';
            $params['reached'] = (int) (bool) $filters['reached'];
        }

        $limit = $this->boundedInt($filters['limit'] ?? null, 300, 1, 1000);
        $offset = $this->boundedInt($filters['offset'] ?? null, 0, 0, 100000);

        $sql = 'SELECT w.*, r.route_name, r.route_type, r.status AS route_status
                FROM atak_waypoints w
                LEFT JOIN atak_waypoint_routes r ON r.id = w.route_id AND r.deleted_at IS NULL
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY w.route_id IS NULL, w.route_id, w.sequence_number, w.id
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->db->fetchAll($sql, $params);

        return $this->withCumulativeDistance($rows);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findWaypoint(int $tenantId, int $waypointId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM atak_waypoints
             WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            ['id' => $waypointId, 'tenant_id' => $tenantId]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateWaypoint(int $tenantId, int $waypointId, array $data): bool
    {
        $fields = [];
        $params = ['id' => $waypointId, 'tenant_id' => $tenantId];

        $textFields = ['label' => 200, 'description' => 4000, 'grid_reference' => 50];
        foreach ($textFields as $field => $max) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $this->text($data[$field], $max);
            }
        }

        foreach (['pos_x', 'pos_y'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = (float) $data[$field];
            }
        }

        if (array_key_exists('pos_z', $data)) {
            $fields[] = 'pos_z = :pos_z';
            $params['pos_z'] = ($data['pos_z'] === null || $data['pos_z'] === '') ? null : (float) $data['pos_z'];
        }

        if (array_key_exists('radius_m', $data)) {
            $fields[] = 'radius_m = :radius_m';
            $params['radius_m'] = $this->positiveIntOrNull($data['radius_m']);
        }

        if (array_key_exists('sequence_number', $data)) {
            $sequence = $this->positiveIntOrNull($data['sequence_number']);
            if ($sequence !== null) {
                $fields[] = 'sequence_number = :sequence_number';
                $params['sequence_number'] = $sequence;
            }
        }

        if (array_key_exists('waypoint_type', $data)) {
            $type = $this->enum($data['waypoint_type'], self::WAYPOINT_TYPES, null);
            if ($type !== null) {
                $fields[] = 'waypoint_type = :waypoint_type';
                $params['waypoint_type'] = $type;
            }
        }

        if (array_key_exists('route_id', $data)) {
            $fields[] = 'route_id = :route_id';
            $params['route_id'] = $this->positiveIntOrNull($data['route_id']);
        }

        if ($fields === []) {
            return false;
        }
        $fields[] = 'updated_at = NOW()';

        $sql = 'UPDATE atak_waypoints SET ' . implode(', ', $fields)
            . ' WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL';

        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Marque un waypoint atteint — ou revient sur ce marquage.
     *
     * @param array<string,mixed> $context
     */
    public function markReached(int $tenantId, int $waypointId, bool $reached, array $context = []): bool
    {
        if (!$reached) {
            return $this->db->execute(
                'UPDATE atak_waypoints
                 SET reached = 0, reached_at = NULL, reached_by_user_id = NULL,
                     reached_by_callsign = NULL, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
                ['id' => $waypointId, 'tenant_id' => $tenantId]
            ) > 0;
        }

        $updated = $this->db->execute(
            'UPDATE atak_waypoints
             SET reached = 1,
                 reached_at = COALESCE(reached_at, NOW()),
                 reached_by_user_id = :user_id,
                 reached_by_callsign = :callsign,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            [
                'id' => $waypointId,
                'tenant_id' => $tenantId,
                'user_id' => $this->positiveIntOrNull($context['reached_by_user_id'] ?? null),
                'callsign' => $this->text($context['reached_by_callsign'] ?? null, 100),
            ]
        ) > 0;

        if ($updated) {
            $this->syncRouteProgress($tenantId, $waypointId);
        }

        return $updated;
    }

    public function softDeleteWaypoint(int $tenantId, int $waypointId): bool
    {
        return $this->db->execute(
            'UPDATE atak_waypoints SET deleted_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            ['id' => $waypointId, 'tenant_id' => $tenantId]
        ) > 0;
    }

    /**
     * Prochain point non atteint d’un itinéraire — ce que le mod demande pour guider.
     *
     * @return array<string,mixed>|null
     */
    public function nextPending(int $tenantId, int $routeId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM atak_waypoints
             WHERE tenant_id = :tenant_id AND route_id = :route_id
               AND deleted_at IS NULL AND reached = 0
             ORDER BY sequence_number ASC, id ASC
             LIMIT 1',
            ['tenant_id' => $tenantId, 'route_id' => $routeId]
        );
    }

    /**
     * Un itinéraire dont tous les points sont atteints passe COMPLETED de lui-même ;
     * un itinéraire encore PLANNED dont un point est atteint passe ACTIVE.
     */
    private function syncRouteProgress(int $tenantId, int $waypointId): void
    {
        $row = $this->db->fetchOne(
            'SELECT route_id FROM atak_waypoints WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $waypointId, 'tenant_id' => $tenantId]
        );
        $routeId = (int) ($row['route_id'] ?? 0);
        if ($routeId < 1) {
            return;
        }

        $counts = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, SUM(reached = 1) AS reached
             FROM atak_waypoints
             WHERE route_id = :route_id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            ['route_id' => $routeId, 'tenant_id' => $tenantId]
        );
        $total = (int) ($counts['total'] ?? 0);
        $reached = (int) ($counts['reached'] ?? 0);
        if ($total < 1) {
            return;
        }

        if ($reached >= $total) {
            $this->db->execute(
                "UPDATE atak_waypoint_routes
                 SET status = 'COMPLETED', completed_at = COALESCE(completed_at, NOW()), updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tenant_id AND status IN ('PLANNED', 'ACTIVE')",
                ['id' => $routeId, 'tenant_id' => $tenantId]
            );

            return;
        }

        $this->db->execute(
            "UPDATE atak_waypoint_routes
             SET status = 'ACTIVE', started_at = COALESCE(started_at, NOW()), updated_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id AND status = 'PLANNED'",
            ['id' => $routeId, 'tenant_id' => $tenantId]
        );
    }

    private function nextSequence(int $tenantId, int $contextId, ?int $routeId): int
    {
        if ($routeId === null) {
            $row = $this->db->fetchOne(
                'SELECT MAX(sequence_number) AS max_seq FROM atak_waypoints
                 WHERE tenant_id = :tenant_id AND context_id = :context_id
                   AND route_id IS NULL AND deleted_at IS NULL',
                ['tenant_id' => $tenantId, 'context_id' => $contextId]
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT MAX(sequence_number) AS max_seq FROM atak_waypoints
                 WHERE route_id = :route_id AND deleted_at IS NULL',
                ['route_id' => $routeId]
            );
        }

        return max(1, (int) ($row['max_seq'] ?? 0) + 1);
    }

    /**
     * Ajoute la progression lisible d’un itinéraire.
     *
     * @param array<string,mixed> $route
     * @return array<string,mixed>
     */
    private function decorateRoute(array $route): array
    {
        $total = (int) ($route['waypoints_total'] ?? 0);
        $reached = (int) ($route['waypoints_reached'] ?? 0);
        $route['waypoints_total'] = $total;
        $route['waypoints_reached'] = $reached;
        $route['progress_percent'] = $total > 0 ? (int) round($reached * 100 / $total) : 0;
        $route['is_visible'] = (bool) ($route['is_visible'] ?? true);

        return $route;
    }

    /**
     * Distance cumulée depuis le premier point de chaque itinéraire.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function withCumulativeDistance(array $rows): array
    {
        /** @var array<string, array{0: float, 1: float}> $previous */
        $previous = [];
        /** @var array<string, float> $cumulative */
        $cumulative = [];

        foreach ($rows as &$row) {
            $key = ($row['route_id'] ?? null) === null ? 'orphans' : 'route-' . (int) $row['route_id'];
            $x = (float) ($row['pos_x'] ?? 0);
            $y = (float) ($row['pos_y'] ?? 0);

            $leg = 0.0;
            if (isset($previous[$key])) {
                $leg = sqrt(($x - $previous[$key][0]) ** 2 + ($y - $previous[$key][1]) ** 2);
            }
            $cumulative[$key] = ($cumulative[$key] ?? 0.0) + $leg;
            $previous[$key] = [$x, $y];

            $row['reached'] = (bool) ($row['reached'] ?? false);
            $row['leg_distance_m'] = round($leg, 1);
            $row['cumulative_distance_m'] = round($cumulative[$key], 1);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $waypoints
     */
    private function totalDistance(array $waypoints): float
    {
        $last = end($waypoints);

        return is_array($last) ? (float) ($last['cumulative_distance_m'] ?? 0.0) : 0.0;
    }

    private function text(mixed $raw, int $max): ?string
    {
        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            return null;
        }
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * @param list<string> $allowed
     */
    private function enum(mixed $raw, array $allowed, ?string $default): ?string
    {
        if (!is_string($raw) || trim($raw) === '') {
            return $default;
        }
        $value = strtoupper(trim($raw));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function positiveIntOrNull(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || is_array($raw)) {
            return null;
        }
        $value = (int) $raw;

        return $value > 0 ? $value : null;
    }

    private function boundedInt(mixed $raw, int $default, int $min, int $max): int
    {
        if ($raw === null || $raw === '' || is_array($raw)) {
            return $default;
        }

        return max($min, min($max, (int) $raw));
    }
}
