<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Prérequis d’un emploi : doctrines à lire ou à signer, heures de jeu ou de vol mesurées, qualifications et
 * compétences détenues. Évaluation d’un membre, contrôle à l’attribution et dérogations motivées.
 *
 * Critères (personnel_job_role_requirements.criteria_json, liste) — mêmes clés que le moteur de critères des
 * qualifications (type, metric, scope, min, definition_id) pour pouvoir le brancher plus tard :
 *   {type: doctrine, document_id, mode: read|ack}
 *   {type: doctrine_ref, key, mode: read|ack}  (publication du référentiel doctrinal, rattachée depuis le catalogue
 *     d’emplois : tenue si le membre a lu un document de doctrine de la communauté rattaché à cette publication,
 *     ou portant son code ; non mesurable tant qu’aucun document ne lui correspond)
 *   {type: metric, metric: playtime_hours|flight_hours, scope, min}
 *   {type: qualification, definition_id}
 *   {type: competency, requirement_id}  (tenue si le membre détient les qualifications liées à la compétence)
 * Sources mesurées : user_arma_playtime (temps de jeu du mod), atak_pilot_time (heures aux commandes par appareil),
 * document_views / document_acknowledgments (version en vigueur), personnel_qualifications (valides, non expirées),
 * competency_requirement_links (cible qualification).
 *
 * Point de fusion avec le moteur de critères des qualifications (branche site/qualifications-libres,
 * QualificationCriteriaCatalog / QualificationCriteriaEngine) : les mesures « metric » portent les mêmes noms
 * (playtime_hours, flight_hours) et les mêmes périmètres (« server », « zeus », « kind:heli », « vclass:… ») ; une fois
 * ce moteur sur main, evaluate() peut lui déléguer les critères metric (et gagner les heures du carnet de vol
 * contresigné, les fenêtres glissantes, les sessions…) sans migration des données enregistrées ici. Les critères
 * doctrine et competency restent propres aux emplois.
 */
final class JobPrerequisiteService
{
    public const TABLE = 'personnel_job_role_requirements';
    public const WAIVERS = 'personnel_job_role_waivers';
    public const MAX_ITEMS = 12;
    public const REASON_MIN = 5;
    public const REASON_MAX = 500;

    /** Mesures disponibles : libellé, unité, périmètres possibles. */
    public const METRICS = [
        'playtime_hours' => ['label' => 'Heures de jeu', 'unit' => 'h'],
        'flight_hours' => ['label' => 'Heures de vol', 'unit' => 'h'],
    ];

    public const PLAYTIME_SCOPES = ['' => 'Tout le jeu', 'server' => 'Sur serveur', 'zeus' => 'En Zeus'];
    public const FLIGHT_SCOPES = ['' => 'Tous appareils', 'kind:heli' => 'Hélicoptères', 'kind:plane' => 'Avions'];
    public const DOCTRINE_MODES = ['read' => 'Lue', 'ack' => 'Lue et signée'];

    private PDO $pdo;

    /** @var array<string, bool> */
    private array $tables = [];

    /** @var array<string, mixed> mesures d’un membre le temps d’une requête */
    private array $memo = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    private function hasTable(string $table): bool
    {
        if (!isset($this->tables[$table])) {
            try {
                $this->pdo->query('SELECT 1 FROM ' . $table . ' WHERE 1 = 0');
                $this->tables[$table] = true;
            } catch (Throwable) {
                $this->tables[$table] = false;
            }
        }

        return $this->tables[$table];
    }

    public function schemaReady(): bool
    {
        return $this->hasTable(self::TABLE) && $this->hasTable(self::WAIVERS);
    }

    // ---------------------------------------------------------------- critères

    /**
     * Critères saisis (formulaire ou JSON) : invalides et doublons écartés, 12 au plus.
     *
     * @return list<array<string, mixed>>
     */
    public static function normalize(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($raw as $item) {
            $c = is_array($item) ? self::normalizeCriterion($item) : null;
            if ($c === null) {
                continue;
            }
            $key = json_encode([$c['type'], $c['mode'] ?? '', $c['key'] ?? '', $c['document_id'] ?? 0, $c['metric'] ?? '', $c['scope'] ?? '', $c['definition_id'] ?? 0, $c['requirement_id'] ?? 0]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $c;
            if (count($out) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $c */
    public static function normalizeCriterion(array $c): ?array
    {
        $type = (string) ($c['type'] ?? '');
        // Formulaire : « metric:flight_hours » choisi dans la même liste que les autres types.
        if (str_starts_with($type, 'metric:')) {
            $c['metric'] = substr($type, 7);
            $type = 'metric';
        }
        switch ($type) {
            case 'doctrine':
                $id = (int) ($c['document_id'] ?? $c['ref'] ?? 0);
                $mode = (string) ($c['mode'] ?? 'read');

                return $id > 0 ? ['type' => 'doctrine', 'document_id' => $id, 'mode' => isset(self::DOCTRINE_MODES[$mode]) ? $mode : 'read'] : null;
            case 'doctrine_ref':
                $key = trim((string) ($c['key'] ?? ''));
                $mode = (string) ($c['mode'] ?? 'read');

                return preg_match('/^[a-z]{2,4}-[a-z0-9-]{2,80}$/', $key) === 1 ? ['type' => 'doctrine_ref', 'key' => $key, 'mode' => isset(self::DOCTRINE_MODES[$mode]) ? $mode : 'read'] : null;
            case 'qualification':
                $id = (int) ($c['definition_id'] ?? $c['ref'] ?? 0);

                return $id > 0 ? ['type' => 'qualification', 'definition_id' => $id] : null;
            case 'competency':
                $id = (int) ($c['requirement_id'] ?? $c['ref'] ?? 0);

                return $id > 0 ? ['type' => 'competency', 'requirement_id' => $id] : null;
            case 'metric':
                $metric = (string) ($c['metric'] ?? '');
                if (!isset(self::METRICS[$metric])) {
                    return null;
                }
                $min = round((float) str_replace(',', '.', (string) ($c['min'] ?? 0)), 1);
                if ($min <= 0 || $min > 100000) {
                    return null;
                }

                return ['type' => 'metric', 'metric' => $metric, 'scope' => self::normalizeScope($metric, (string) ($c['scope'] ?? '')), 'min' => $min];
            default:
                return null;
        }
    }

    public static function normalizeScope(string $metric, string $scope): string
    {
        $scope = trim($scope);
        if ($metric === 'playtime_hours') {
            return array_key_exists($scope, self::PLAYTIME_SCOPES) ? $scope : '';
        }
        if ($metric === 'flight_hours') {
            if (array_key_exists($scope, self::FLIGHT_SCOPES)) {
                return $scope;
            }

            return preg_match('/^vclass:[A-Za-z0-9_]{1,80}$/', $scope) === 1 ? $scope : '';
        }

        return '';
    }

    /** @return list<array<string, mixed>> */
    public function requirements(int $tenantId, int $roleId): array
    {
        return $this->requirementsByRole($tenantId, [$roleId])[$roleId] ?? [];
    }

    /**
     * @param list<int>|null $roleIds null = tous les emplois de la communauté
     * @return array<int, list<array<string, mixed>>>
     */
    public function requirementsByRole(int $tenantId, ?array $roleIds = null): array
    {
        if ($tenantId < 1 || !$this->hasTable(self::TABLE) || $roleIds === []) {
            return [];
        }
        $sql = 'SELECT job_role_id, criteria_json FROM ' . self::TABLE . ' WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($roleIds !== null) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $i): bool => $i > 0)));
            if ($ids === []) {
                return [];
            }
            $sql .= ' AND job_role_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $items = self::normalize((string) $row['criteria_json']);
            if ($items !== []) {
                $out[(int) $row['job_role_id']] = $items;
            }
        }

        return $out;
    }

    /**
     * Remplace les prérequis d’un emploi (liste vide = aucun prérequis). Journalisé avec l’historique des emplois.
     *
     * @param list<array<string, mixed>> $items critères déjà normalisés
     */
    public function saveRequirements(int $tenantId, int $roleId, array $items, int $actorId): void
    {
        if (!$this->hasTable(self::TABLE)) {
            throw new InvalidArgumentException('Les prérequis arrivent avec la prochaine mise à jour du site.');
        }
        $before = $this->requirements($tenantId, $roleId);
        $items = self::normalize($items);
        $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE tenant_id = ? AND job_role_id = ?')->execute([$tenantId, $roleId]);
        if ($items !== []) {
            $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (tenant_id, job_role_id, criteria_json, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$tenantId, $roleId, json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $actorId > 0 ? $actorId : null, date('Y-m-d H:i:s')]);
        }
        $this->audit($tenantId, $actorId, 'prerequisites', $roleId, ['prerequisites' => count($before)], ['prerequisites' => count($items)]);
    }

    // ---------------------------------------------------------------- mesures

    private function playtimeSeconds(int $tenantId, int $userId, string $scope): float
    {
        $key = "pt:$tenantId:$userId";
        if (!array_key_exists($key, $this->memo)) {
            $row = null;
            if ($this->hasTable('user_arma_playtime')) {
                try {
                    $st = $this->pdo->prepare('SELECT * FROM user_arma_playtime WHERE tenant_id = ? AND user_id = ? LIMIT 1');
                    $st->execute([$tenantId, $userId]);
                    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (Throwable) {
                    $row = null;
                }
            }
            $this->memo[$key] = $row;
        }
        $row = $this->memo[$key];
        $column = ['server' => 'server_seconds', 'zeus' => 'zeus_seconds'][$scope] ?? 'total_seconds';

        return is_array($row) ? (float) ($row[$column] ?? 0) : 0.0;
    }

    private function flightSeconds(int $tenantId, int $userId, string $scope): float
    {
        $key = "fl:$tenantId:$userId";
        if (!array_key_exists($key, $this->memo)) {
            $rows = [];
            if ($this->hasTable('atak_pilot_time')) {
                try {
                    $st = $this->pdo->prepare('SELECT vehicle_class, vehicle_kind, seconds FROM atak_pilot_time WHERE tenant_id = ? AND user_id = ?');
                    $st->execute([$tenantId, $userId]);
                    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Throwable) {
                    $rows = [];
                }
            }
            $this->memo[$key] = $rows;
        }
        $total = 0.0;
        foreach ($this->memo[$key] as $row) {
            $kind = ($row['vehicle_kind'] ?? '') === 'plane' ? 'plane' : 'heli';
            $ok = match (true) {
                $scope === '' => true,
                $scope === 'kind:heli' => $kind === 'heli',
                $scope === 'kind:plane' => $kind === 'plane',
                str_starts_with($scope, 'vclass:') => strcasecmp((string) ($row['vehicle_class'] ?? ''), substr($scope, 7)) === 0,
                default => false,
            };
            if ($ok) {
                $total += (float) ($row['seconds'] ?? 0);
            }
        }

        return $total;
    }

    /** @return bool|null null = document sans version publiée */
    private function doctrineMet(int $tenantId, int $userId, int $documentId, string $mode): ?bool
    {
        if (!$this->hasTable('document_versions')) {
            return null;
        }
        try {
            $st = $this->pdo->prepare('SELECT id FROM document_versions WHERE document_id = ? AND is_current = 1 ORDER BY id DESC LIMIT 1');
            $st->execute([$documentId]);
            $versionId = (int) $st->fetchColumn();
            if ($versionId < 1) {
                return null;
            }
            if ($this->hasTable('document_acknowledgments')) {
                $ack = $this->pdo->prepare('SELECT 1 FROM document_acknowledgments WHERE tenant_id = ? AND user_id = ? AND version_id = ? LIMIT 1');
                $ack->execute([$tenantId, $userId, $versionId]);
                if ($ack->fetchColumn()) {
                    return true;
                }
            }
            if ($mode === 'ack' || !$this->hasTable('document_views')) {
                return false;
            }
            $view = $this->pdo->prepare('SELECT 1 FROM document_views WHERE tenant_id = ? AND user_id = ? AND version_id = ? LIMIT 1');
            $view->execute([$tenantId, $userId, $versionId]);

            return (bool) $view->fetchColumn();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Documents de doctrine de la communauté qui correspondent à une publication du référentiel doctrinal : rattachés
     * dans Doctrine › Référentiel, sinon portant le même code de référence (« AJP-3.5 »).
     *
     * @return list<int>
     */
    public function libraryDocuments(int $tenantId, string $entryKey): array
    {
        $key = "lib:$tenantId:$entryKey";
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }
        $ids = [];
        if ($this->hasTable('doctrine_library_links')) {
            try {
                $st = $this->pdo->prepare('SELECT document_id FROM doctrine_library_links WHERE tenant_id = ? AND entry_key = ? AND unlinked_at IS NULL');
                $st->execute([$tenantId, $entryKey]);
                $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
            } catch (Throwable) {
                $ids = [];
            }
        }
        $code = (string) (\App\Services\Doctrine\DoctrineLibraryCatalog::find($entryKey)['code'] ?? '');
        if ($ids === [] && $code !== '' && $this->hasTable('document_doctrines')) {
            try {
                $st = $this->pdo->prepare("SELECT document_id FROM document_doctrines WHERE (tenant_id = ? OR scope = 'platform') AND LOWER(reference_code) = LOWER(?)");
                $st->execute([$tenantId, $code]);
                $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
            } catch (Throwable) {
                $ids = [];
            }
        }

        return $this->memo[$key] = array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0)));
    }

    /** @return array<int, true> qualifications valides et non expirées du membre */
    private function heldQualifications(int $tenantId, int $userId): array
    {
        $key = "q:$tenantId:$userId";
        if (!array_key_exists($key, $this->memo)) {
            $held = [];
            if ($this->hasTable('personnel_qualifications')) {
                try {
                    $st = $this->pdo->prepare("SELECT definition_id FROM personnel_qualifications
                        WHERE user_id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND definition_id IS NOT NULL
                          AND status IN ('valid', 'expiring') AND (expires_at IS NULL OR expires_at >= ?)");
                    $st->execute([$userId, $tenantId, date('Y-m-d')]);
                    foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
                        $held[(int) $id] = true;
                    }
                } catch (Throwable) {
                    $held = [];
                }
            }
            $this->memo[$key] = $held;
        }

        return $this->memo[$key];
    }

    /** @return list<int> qualifications rattachées à une compétence du référentiel */
    private function competencyQualifications(int $tenantId, int $requirementId): array
    {
        if (!$this->hasTable('competency_requirement_links')) {
            return [];
        }
        try {
            $st = $this->pdo->prepare("SELECT target_ref FROM competency_requirement_links WHERE tenant_id = ? AND requirement_id = ? AND target_type = 'qualification'");
            $st->execute([$tenantId, $requirementId]);

            return array_values(array_filter(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []), static fn (int $i): bool => $i > 0));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Évaluation d’un membre : pour chaque critère, met (true / false / null = non mesurable), valeur, cible et
     * progression (0 à 1). Un critère non mesurable ne bloque jamais.
     *
     * @param list<array<string, mixed>> $items
     * @return array{items: list<array<string, mixed>>, met: int, total: int, progress: float, complete: bool}
     */
    public function evaluate(int $tenantId, int $userId, array $items): array
    {
        $rows = [];
        $met = 0;
        $counted = 0;
        $sum = 0.0;
        foreach ($items as $c) {
            $current = null;
            $target = null;
            $state = null;
            switch ($c['type']) {
                case 'metric':
                    $seconds = $c['metric'] === 'flight_hours'
                        ? $this->flightSeconds($tenantId, $userId, (string) $c['scope'])
                        : $this->playtimeSeconds($tenantId, $userId, (string) $c['scope']);
                    $current = round($seconds / 3600, 1);
                    $target = (float) $c['min'];
                    $state = $current >= $target;
                    $progress = $target > 0 ? min(1.0, $current / $target) : 1.0;
                    break;
                case 'doctrine':
                    $state = $this->doctrineMet($tenantId, $userId, (int) $c['document_id'], (string) $c['mode']);
                    break;
                case 'doctrine_ref':
                    foreach ($this->libraryDocuments($tenantId, (string) $c['key']) as $documentId) {
                        $one = $this->doctrineMet($tenantId, $userId, $documentId, (string) $c['mode']);
                        if ($one === true) {
                            $state = true;
                            break;
                        }
                        if ($one === false) {
                            $state = false;
                        }
                    }
                    break;
                case 'qualification':
                    $state = isset($this->heldQualifications($tenantId, $userId)[(int) $c['definition_id']]);
                    break;
                case 'competency':
                    $linked = $this->competencyQualifications($tenantId, (int) $c['requirement_id']);
                    $held = $this->heldQualifications($tenantId, $userId);
                    $state = $linked === [] ? null : array_diff($linked, array_keys($held)) === [];
                    break;
            }
            if (!isset($progress)) {
                $progress = $state === true ? 1.0 : 0.0;
            }
            if ($state !== null) {
                $counted++;
                $sum += $progress;
                if ($state) {
                    $met++;
                }
            }
            $rows[] = ['criterion' => $c, 'met' => $state, 'current' => $current, 'target' => $target, 'progress' => $progress];
            unset($progress);
        }

        return [
            'items' => $rows,
            'met' => $met,
            'total' => $counted,
            'progress' => $counted > 0 ? round($sum / $counted, 3) : 1.0,
            'complete' => $met >= $counted,
        ];
    }

    // ---------------------------------------------------------------- dérogations

    /** @return array<int, array<string, mixed>> dérogations actives d’un membre, par emploi */
    public function activeWaiversForUser(int $tenantId, int $userId): array
    {
        if (!$this->hasTable(self::WAIVERS)) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT w.*, u.display_name AS granted_by_name FROM ' . self::WAIVERS . ' w
            LEFT JOIN users u ON u.id = w.granted_by
            WHERE w.tenant_id = ? AND w.user_id = ? AND w.revoked_at IS NULL ORDER BY w.id DESC');
        try {
            $st->execute([$tenantId, $userId]);
        } catch (Throwable) {
            $st = $this->pdo->prepare('SELECT * FROM ' . self::WAIVERS . ' WHERE tenant_id = ? AND user_id = ? AND revoked_at IS NULL ORDER BY id DESC');
            $st->execute([$tenantId, $userId]);
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['job_role_id']] ??= $row;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> dérogations (actives puis révoquées récentes) d’un emploi */
    public function waiversForRole(int $tenantId, int $roleId, int $limit = 30): array
    {
        if (!$this->hasTable(self::WAIVERS)) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT w.*, m.display_name AS member_name, g.display_name AS granted_by_name, r.display_name AS revoked_by_name
            FROM ' . self::WAIVERS . ' w
            LEFT JOIN users m ON m.id = w.user_id LEFT JOIN users g ON g.id = w.granted_by LEFT JOIN users r ON r.id = w.revoked_by
            WHERE w.tenant_id = ? AND w.job_role_id = ? ORDER BY (w.revoked_at IS NULL) DESC, w.id DESC LIMIT ' . $limit;
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([$tenantId, $roleId]);
        } catch (Throwable) {
            $st = $this->pdo->prepare('SELECT * FROM ' . self::WAIVERS . ' WHERE tenant_id = ? AND job_role_id = ? ORDER BY id DESC LIMIT ' . $limit);
            $st->execute([$tenantId, $roleId]);
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function normalizeReason(mixed $raw): string
    {
        $reason = is_scalar($raw) ? trim((string) preg_replace('/\s+/u', ' ', (string) $raw)) : '';

        return mb_substr($reason, 0, self::REASON_MAX);
    }

    /**
     * Dérogation motivée : le membre tient l’emploi sans remplir ses prérequis. Les critères manquants au moment
     * de la décision sont conservés.
     *
     * @param list<array<string, mixed>> $missing
     */
    public function grantWaiver(int $tenantId, int $userId, int $roleId, string $reason, int $actorId, array $missing): int
    {
        if (!$this->hasTable(self::WAIVERS)) {
            throw new InvalidArgumentException('Les dérogations arrivent avec la prochaine mise à jour du site.');
        }
        $reason = self::normalizeReason($reason);
        if (mb_strlen($reason) < self::REASON_MIN) {
            throw new InvalidArgumentException('Indiquez le motif de la dérogation (5 caractères au moins).');
        }
        $existing = $this->activeWaiversForUser($tenantId, $userId)[$roleId] ?? null;
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $this->pdo->prepare('INSERT INTO ' . self::WAIVERS . ' (tenant_id, job_role_id, user_id, reason, missing_json, granted_by, granted_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$tenantId, $roleId, $userId, $reason, json_encode(array_map(static fn (array $m): array => $m['criterion'] ?? $m, $missing), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $actorId > 0 ? $actorId : null, date('Y-m-d H:i:s')]);
        $id = (int) $this->pdo->lastInsertId();
        $this->audit($tenantId, $actorId, 'waiver_granted', $roleId, [], ['waiver_member' => $this->memberLabel($userId), 'waiver_reason' => $reason]);

        return $id;
    }

    public function revokeWaiver(int $tenantId, int $waiverId, int $actorId, string $reason): ?array
    {
        if (!$this->hasTable(self::WAIVERS)) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM ' . self::WAIVERS . ' WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL');
        $st->execute([$waiverId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $reason = self::normalizeReason($reason);
        $this->pdo->prepare('UPDATE ' . self::WAIVERS . ' SET revoked_at = ?, revoked_by = ?, revoke_reason = ? WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL')
            ->execute([date('Y-m-d H:i:s'), $actorId > 0 ? $actorId : null, $reason !== '' ? $reason : null, $waiverId, $tenantId]);
        $this->audit($tenantId, $actorId, 'waiver_revoked', (int) $row['job_role_id'], ['waiver_member' => $this->memberLabel((int) $row['user_id']), 'waiver_reason' => (string) $row['reason']], ['waiver_member' => $this->memberLabel((int) $row['user_id']), 'waiver_reason' => $reason]);

        return $row;
    }

    // ---------------------------------------------------------------- contrôle à l’attribution

    /**
     * Contrôle des emplois nouvellement attribués à un membre. Sans prérequis manquant : rien. Sinon, avec un motif
     * et le droit de déroger : dérogation enregistrée. Sans motif : refus si la communauté rend les prérequis
     * bloquants, simple avertissement sinon.
     *
     * @param list<int> $newRoleIds
     * @param array<int, string> $roleNames
     * @return array{allowed: bool, unmet: array<int, list<array<string, mixed>>>, waived: list<int>, warned: list<int>}
     */
    public function gate(int $tenantId, int $userId, array $newRoleIds, string $reason, bool $canWaive, bool $blocking, int $actorId): array
    {
        $result = ['allowed' => true, 'unmet' => [], 'waived' => [], 'warned' => []];
        if ($newRoleIds === [] || !$this->hasTable(self::TABLE)) {
            return $result;
        }
        $active = $this->activeWaiversForUser($tenantId, $userId);
        foreach ($this->requirementsByRole($tenantId, $newRoleIds) as $roleId => $items) {
            if (isset($active[$roleId])) {
                continue;
            }
            $eval = $this->evaluate($tenantId, $userId, $items);
            $missing = array_values(array_filter($eval['items'], static fn (array $r): bool => $r['met'] === false));
            if ($missing !== []) {
                $result['unmet'][$roleId] = $missing;
            }
        }
        if ($result['unmet'] === []) {
            return $result;
        }
        $reason = self::normalizeReason($reason);
        if ($reason !== '' && $canWaive && $this->hasTable(self::WAIVERS)) {
            if (mb_strlen($reason) < self::REASON_MIN) {
                throw new InvalidArgumentException('Indiquez le motif de la dérogation (5 caractères au moins).');
            }
            foreach ($result['unmet'] as $roleId => $missing) {
                $this->grantWaiver($tenantId, $userId, $roleId, $reason, $actorId, $missing);
                $result['waived'][] = $roleId;
            }

            return $result;
        }
        $result['warned'] = array_keys($result['unmet']);

        return $result;
    }

    // ---------------------------------------------------------------- affichage et formulaires

    /**
     * Choix proposés à l’éditeur des prérequis : doctrines publiées, qualifications, compétences et appareils
     * déjà pilotés dans la communauté.
     *
     * @return array{doctrines: list<array{id: int, label: string}>, qualifications: list<array{id: int, label: string}>, competencies: list<array{id: int, label: string}>, aircraft: list<array{id: string, label: string}>}
     */
    public function options(int $tenantId): array
    {
        $out = ['doctrines' => [], 'qualifications' => [], 'competencies' => [], 'aircraft' => []];
        $queries = [
            'doctrines' => ["SELECT dd.document_id AS id, COALESCE(dd.reference_code, '') AS code, d.title AS label FROM document_doctrines dd
                INNER JOIN documents d ON d.id = dd.document_id WHERE dd.doctrine_status = 'published' AND (dd.tenant_id = ? OR dd.scope = 'platform')
                ORDER BY dd.reference_code, d.title LIMIT 400", [$tenantId]],
            'qualifications' => ['SELECT id, COALESCE(code, \'\') AS code, name AS label FROM personnel_qualification_definitions WHERE tenant_id = ? ORDER BY name LIMIT 400', [$tenantId]],
            'competencies' => ['SELECT id, COALESCE(palier, \'\') AS code, label FROM competency_grade_requirements WHERE tenant_id = ? ORDER BY palier_order, sort_order, id LIMIT 400', [$tenantId]],
            'aircraft' => ['SELECT vehicle_class AS id, MAX(vehicle_label) AS label, MAX(vehicle_kind) AS code FROM atak_pilot_time WHERE tenant_id = ? GROUP BY vehicle_class ORDER BY SUM(seconds) DESC LIMIT 200', [$tenantId]],
        ];
        foreach ($queries as $key => [$sql, $params]) {
            try {
                $st = $this->pdo->prepare($sql);
                $st->execute($params);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $label = trim((string) ($row['label'] ?? ''));
                    $code = trim((string) ($row['code'] ?? ''));
                    if ($key === 'aircraft') {
                        if (preg_match('/^[A-Za-z0-9_]{1,80}$/', (string) $row['id']) !== 1) {
                            continue;
                        }
                        $out[$key][] = ['id' => 'vclass:' . $row['id'], 'label' => $label !== '' ? $label : (string) $row['id']];
                        continue;
                    }
                    $out[$key][] = ['id' => (int) $row['id'], 'label' => ($code !== '' && $key !== 'competencies' ? $code . ' · ' : '') . ($label !== '' ? $label : '#' . (int) $row['id'])];
                }
            } catch (Throwable) {
                // Module absent : la liste reste vide.
            }
        }

        return $out;
    }

    /**
     * Morceaux lisibles d’un critère (chacun traduit à l’affichage) : libellé fixe, valeur, unité, cible nommée.
     *
     * @param array<string, mixed> $c
     * @param array<string, list<array{id: int|string, label: string}>> $options
     * @return array{label: string, value: string, unit: string, target: string, content: bool}
     */
    public static function describe(array $c, array $options = []): array
    {
        $find = static function (string $list, int|string $id) use ($options): string {
            foreach ($options[$list] ?? [] as $o) {
                if ((string) $o['id'] === (string) $id) {
                    return (string) $o['label'];
                }
            }

            return is_int($id) ? '#' . $id : (string) $id;
        };

        return match ($c['type'] ?? '') {
            'doctrine' => ['label' => ($c['mode'] ?? 'read') === 'ack' ? 'Doctrine lue et signée' : 'Doctrine lue', 'value' => '', 'unit' => '', 'target' => $find('doctrines', (int) $c['document_id']), 'content' => true],
            'doctrine_ref' => ['label' => ($c['mode'] ?? 'read') === 'ack' ? 'Doctrine lue et signée' : 'Doctrine lue', 'value' => '', 'unit' => '', 'target' => self::libraryLabel((string) ($c['key'] ?? '')), 'content' => true],
            'qualification' => ['label' => 'Qualification détenue', 'value' => '', 'unit' => '', 'target' => $find('qualifications', (int) $c['definition_id']), 'content' => true],
            'competency' => ['label' => 'Compétence acquise', 'value' => '', 'unit' => '', 'target' => $find('competencies', (int) $c['requirement_id']), 'content' => true],
            'metric' => [
                'label' => self::METRICS[$c['metric']]['label'] ?? (string) $c['metric'],
                'value' => self::formatNumber((float) $c['min']),
                'unit' => self::METRICS[$c['metric']]['unit'] ?? '',
                'target' => self::scopeLabel((string) $c['metric'], (string) $c['scope'], $options),
                'content' => str_starts_with((string) $c['scope'], 'vclass:'),
            ],
            default => ['label' => '', 'value' => '', 'unit' => '', 'target' => '', 'content' => false],
        };
    }

    /** Code et titre (langue du lecteur) d’une publication du référentiel doctrinal, ou sa clé si elle n’existe plus. */
    public static function libraryLabel(string $key): string
    {
        $d = \App\Services\Doctrine\DoctrineLibraryCatalog::find($key);
        if ($d === null) {
            return $key;
        }

        return $d['code'] . ' · ' . (\App\Services\Doctrine\DoctrineLibraryCatalog::lang() === 'en' ? $d['title_en'] : $d['title_fr']);
    }

    /** @param array<string, list<array{id: int|string, label: string}>> $options */
    public static function scopeLabel(string $metric, string $scope, array $options = []): string
    {
        if ($metric === 'playtime_hours') {
            return $scope === '' ? '' : (self::PLAYTIME_SCOPES[$scope] ?? $scope);
        }
        if ($scope === '') {
            return '';
        }
        if (isset(self::FLIGHT_SCOPES[$scope])) {
            return self::FLIGHT_SCOPES[$scope];
        }
        foreach ($options['aircraft'] ?? [] as $o) {
            if ($o['id'] === $scope) {
                return $o['label'];
            }
        }

        return substr($scope, 7);
    }

    public static function formatNumber(float $v): string
    {
        return abs($v - round($v)) < 0.001 ? (string) (int) round($v) : str_replace('.', ',', rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.'));
    }

    private function memberLabel(int $userId): string
    {
        try {
            $st = $this->pdo->prepare('SELECT display_name FROM users WHERE id = ?');
            $st->execute([$userId]);
            $name = trim((string) ($st->fetchColumn() ?: ''));
        } catch (Throwable) {
            $name = '';
        }

        return $name !== '' ? $name . ' (#' . $userId . ')' : '#' . $userId;
    }

    /** Journal d’audit commun à l’historique des emplois (onglet « Historique et archives »). */
    private function audit(int $tenantId, int $actorId, string $operation, int $roleId, array $before, array $after): void
    {
        try {
            $name = '';
            try {
                $st = $this->pdo->prepare('SELECT name FROM personnel_job_roles WHERE id = ? AND tenant_id = ?');
                $st->execute([$roleId, $tenantId]);
                $name = (string) ($st->fetchColumn() ?: '');
            } catch (Throwable) {
            }
            $this->pdo->prepare('INSERT INTO audit_logs (tenant_id,user_id,action,entity_type,entity_id,old_value,new_value,created_at) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)')
                ->execute([$tenantId, $actorId, 'personnel.job_role.' . $operation, 'personnel_job_role', $roleId,
                    json_encode(['name' => $name] + $before, JSON_UNESCAPED_UNICODE), json_encode(['name' => $name] + $after, JSON_UNESCAPED_UNICODE)]);
        } catch (Throwable $e) {
            error_log('Job prerequisites audit: ' . $e->getMessage());
        }
    }
}
