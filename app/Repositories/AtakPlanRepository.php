<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Plans d'opération du poste TOC web (tables de l'étape 4370). Toutes les lectures et écritures sont bornées à la
 * communauté (tenant_id) : un plan d'une autre communauté n'existe pas pour l'appelant.
 *
 * Synchronisation entre plusieurs éditeurs : chaque élément porte updated_at_ms (horloge du serveur, strictement
 * croissante par élément) ; le dernier enregistré gagne, élément par élément. Un client demande les changements
 * depuis son dernier relevé (?since=) et ne reçoit que ce qui a bougé, suppressions comprises.
 */
class AtakPlanRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public function ready(): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM atak_plans WHERE 1 = 0');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Plans                                                               */
    /* ------------------------------------------------------------------ */

    /** @param array<string, mixed> $meta */
    public function create(int $tenantId, int $userId, string $title, int $mapId, string $world, string $sharing, ?int $unitId, array $meta, bool $template = false): int
    {
        $now = self::now();
        $this->pdo()->prepare(
            'INSERT INTO atak_plans (tenant_id, owner_user_id, title, map_id, world, sharing, unit_id, is_template, rev, meta_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)'
        )->execute([
            $tenantId, $userId, $title, max(1, $mapId), $world, $sharing, $unitId, $template ? 1 : 0,
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $now, $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $planId): ?array
    {
        if ($planId < 1 || $tenantId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM atak_plans WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1');
        $st->execute([$planId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->presentPlan($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findByToken(int $tenantId, string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM atak_plans WHERE share_token = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1');
        $st->execute([$token, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->presentPlan($row) : null;
    }

    /**
     * Plans visibles : les siens, ceux de la communauté, ceux de ses unités, ceux partagés avec soi ; tous avec
     * le droit de configuration du poste.
     *
     * @param list<int> $unitIds
     * @return list<array<string, mixed>>
     */
    public function listVisible(int $tenantId, int $userId, array $unitIds, bool $all, bool $templates = false, int $limit = 200): array
    {
        $params = [$tenantId, $templates ? 1 : 0];
        $sql = 'SELECT p.* FROM atak_plans p WHERE p.tenant_id = ? AND p.deleted_at IS NULL AND p.is_template = ?';
        if (!$all) {
            $or = ['p.owner_user_id = ?', "p.sharing = 'community'"];
            $params[] = $userId;
            $unitIds = array_values(array_filter(array_map('intval', $unitIds), static fn (int $i): bool => $i > 0));
            if ($unitIds !== []) {
                $or[] = "(p.sharing = 'unit' AND p.unit_id IN (" . implode(',', array_fill(0, count($unitIds), '?')) . '))';
                array_push($params, ...$unitIds);
                $or[] = "EXISTS (SELECT 1 FROM atak_plan_shares s WHERE s.plan_id = p.id AND s.scope = 'unit' AND s.ref_id IN (" . implode(',', array_fill(0, count($unitIds), '?')) . '))';
                array_push($params, ...$unitIds);
            }
            $or[] = "EXISTS (SELECT 1 FROM atak_plan_shares s2 WHERE s2.plan_id = p.id AND s2.scope = 'user' AND s2.ref_id = ?)";
            $params[] = $userId;
            $sql .= ' AND (' . implode(' OR ', $or) . ')';
        }
        $sql .= ' ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . max(1, min(500, $limit));
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return array_map([$this, 'presentPlan'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @param array<string, mixed> $fields title, sharing, unit_id, locked, meta, map_id, world, is_template */
    public function updatePlan(int $tenantId, int $planId, array $fields): void
    {
        $set = [];
        $params = [];
        foreach (['title', 'sharing', 'unit_id', 'locked', 'map_id', 'world', 'is_template'] as $k) {
            if (array_key_exists($k, $fields)) {
                $set[] = $k . ' = ?';
                $v = $fields[$k];
                $params[] = is_bool($v) ? ($v ? 1 : 0) : $v;
            }
        }
        if (array_key_exists('meta', $fields)) {
            $set[] = 'meta_json = ?';
            $params[] = json_encode($fields['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }
        if ($set === []) {
            return;
        }
        $set[] = 'updated_at = ?';
        $params[] = self::now();
        array_push($params, $planId, $tenantId);
        $this->pdo()->prepare('UPDATE atak_plans SET ' . implode(', ', $set) . ' WHERE id = ? AND tenant_id = ?')->execute($params);
    }

    public function bumpRev(int $tenantId, int $planId): int
    {
        $this->pdo()->prepare('UPDATE atak_plans SET rev = rev + 1, updated_at = ? WHERE id = ? AND tenant_id = ?')->execute([self::now(), $planId, $tenantId]);
        $st = $this->pdo()->prepare('SELECT rev FROM atak_plans WHERE id = ? AND tenant_id = ?');
        $st->execute([$planId, $tenantId]);

        return (int) $st->fetchColumn();
    }

    public function setShareToken(int $tenantId, int $planId, ?string $token): void
    {
        $this->pdo()->prepare('UPDATE atak_plans SET share_token = ? WHERE id = ? AND tenant_id = ?')->execute([$token, $planId, $tenantId]);
    }

    public function markPublished(int $tenantId, int $planId, string $side, int $rev, int $count): void
    {
        $this->pdo()->prepare('UPDATE atak_plans SET published_at = ?, published_side = ?, published_rev = ?, published_count = ? WHERE id = ? AND tenant_id = ?')
            ->execute([self::now(), $side, $rev, $count, $planId, $tenantId]);
    }

    public function softDelete(int $tenantId, int $planId): void
    {
        $this->pdo()->prepare('UPDATE atak_plans SET deleted_at = ?, share_token = NULL WHERE id = ? AND tenant_id = ?')->execute([self::now(), $planId, $tenantId]);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function presentPlan(array $row): array
    {
        $meta = json_decode((string) ($row['meta_json'] ?? ''), true);

        return [
            'id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'owner_user_id' => (int) $row['owner_user_id'],
            'title' => (string) $row['title'],
            'map_id' => (int) $row['map_id'],
            'world' => (string) $row['world'],
            'sharing' => (string) $row['sharing'],
            'unit_id' => $row['unit_id'] !== null ? (int) $row['unit_id'] : null,
            'share_token' => $row['share_token'] !== null ? (string) $row['share_token'] : null,
            'is_template' => (int) $row['is_template'] === 1,
            'locked' => (int) $row['locked'] === 1,
            'rev' => (int) $row['rev'],
            'meta' => is_array($meta) ? $meta : [],
            'published_at' => $row['published_at'] !== null ? (string) $row['published_at'] : null,
            'published_side' => (string) $row['published_side'],
            'published_rev' => (int) $row['published_rev'],
            'published_count' => (int) $row['published_count'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Éléments                                                            */
    /* ------------------------------------------------------------------ */

    /** @return list<array<string, mixed>> */
    public function elements(int $planId, int $sinceMs = 0, bool $withDeleted = false): array
    {
        $sql = 'SELECT * FROM atak_plan_elements WHERE plan_id = ?';
        $params = [$planId];
        if ($sinceMs > 0) {
            $sql .= ' AND updated_at_ms > ?';
            $params[] = $sinceMs;
        }
        if (!$withDeleted) {
            $sql .= ' AND deleted = 0';
        }
        $sql .= ' ORDER BY z ASC, id ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return array_map([$this, 'presentElement'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function countElements(int $planId): int
    {
        $st = $this->pdo()->prepare('SELECT COUNT(*) FROM atak_plan_elements WHERE plan_id = ? AND deleted = 0');
        $st->execute([$planId]);

        return (int) $st->fetchColumn();
    }

    public function lastChangeMs(int $planId): int
    {
        $st = $this->pdo()->prepare('SELECT MAX(updated_at_ms) FROM atak_plan_elements WHERE plan_id = ?');
        $st->execute([$planId]);
        $a = (int) $st->fetchColumn();
        $st = $this->pdo()->prepare('SELECT MAX(updated_at_ms) FROM atak_plan_phases WHERE plan_id = ?');
        $st->execute([$planId]);

        return max($a, (int) $st->fetchColumn());
    }

    /**
     * Enregistre des éléments validés ; le dernier enregistré gagne. Retour : uid => updated_at_ms, et les uid dont la
     * copie du serveur avait été changée par un autre membre depuis la base du client (base_ms), écrasée.
     *
     * @param list<array<string, mixed>> $elements
     * @param array<string, int> $baseMs uid => updated_at_ms connu du client
     * @return array{saved: array<string, int>, overwritten: list<string>, refused: list<string>}
     */
    public function upsertElements(int $tenantId, int $planId, array $elements, ?int $userId, array $baseMs = [], bool $canUnlock = false): array
    {
        $find = $this->pdo()->prepare('SELECT id, updated_at_ms, updated_by, locked, deleted FROM atak_plan_elements WHERE plan_id = ? AND uid = ?');
        $ins = $this->pdo()->prepare(
            'INSERT INTO atak_plan_elements (tenant_id, plan_id, uid, kind, geom, pts_json, props_json, phases_json, layer_uid, group_uid, z, locked, deleted, updated_at_ms, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)'
        );
        $upd = $this->pdo()->prepare(
            'UPDATE atak_plan_elements SET kind = ?, geom = ?, pts_json = ?, props_json = ?, phases_json = ?, layer_uid = ?, group_uid = ?, z = ?, locked = ?,
                deleted = 0, updated_at_ms = ?, updated_by = ? WHERE id = ?'
        );
        // Horloge du plan strictement croissante : un client qui relève « depuis » la dernière valeur vue ne rate rien.
        $now = max(self::nowMs(), $this->lastChangeMs($planId) + 1);
        // Un seul enregistrement sur disque pour tout le lot (déplacer 400 éléments ne fait pas 400 écritures).
        $own = !$this->pdo()->inTransaction() && $elements !== [];
        if ($own) {
            $this->pdo()->beginTransaction();
        }
        try {
            [$saved, $over, $refused] = $this->upsertEach($find, $ins, $upd, $tenantId, $planId, $elements, $userId, $baseMs, $canUnlock, $now);
            if ($own) {
                $this->pdo()->commit();
            }
        } catch (\Throwable $e) {
            if ($own && $this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            throw $e;
        }

        return ['saved' => $saved, 'overwritten' => $over, 'refused' => $refused];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, int> $baseMs
     * @return array{0: array<string, int>, 1: list<string>, 2: list<string>}
     */
    private function upsertEach(\PDOStatement $find, \PDOStatement $ins, \PDOStatement $upd, int $tenantId, int $planId, array $elements, ?int $userId, array $baseMs, bool $canUnlock, int $now): array
    {
        $saved = [];
        $over = [];
        $refused = [];
        foreach ($elements as $el) {
            $find->execute([$planId, $el['uid']]);
            $prev = $find->fetch(PDO::FETCH_ASSOC);
            $enc = static fn (mixed $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
            if (is_array($prev)) {
                // Élément verrouillé : seuls l'auteur du plan et la configuration du poste peuvent le changer.
                if ((int) $prev['locked'] === 1 && (int) $prev['deleted'] === 0 && !$canUnlock) {
                    $refused[] = (string) $el['uid'];
                    continue;
                }
                $ms = max($now, (int) $prev['updated_at_ms'] + 1);
                $base = $baseMs[$el['uid']] ?? null;
                if ($base !== null && (int) $prev['updated_at_ms'] > $base && (int) ($prev['updated_by'] ?? 0) !== (int) $userId) {
                    $over[] = (string) $el['uid'];
                }
                $upd->execute([
                    $el['kind'], $el['geom'], $enc($el['pts']), $enc($el['props']), $enc($el['phases']), $el['layer'], $el['group'],
                    $el['z'], $el['locked'] ? 1 : 0, $ms, $userId, (int) $prev['id'],
                ]);
            } else {
                $ms = $now;
                $ins->execute([
                    $tenantId, $planId, $el['uid'], $el['kind'], $el['geom'], $enc($el['pts']), $enc($el['props']), $enc($el['phases']),
                    $el['layer'], $el['group'], $el['z'], $el['locked'] ? 1 : 0, $ms, $userId,
                ]);
            }
            $saved[(string) $el['uid']] = $ms;
            $now = $ms + 1;
        }

        return [$saved, $over, $refused];
    }

    /**
     * @param list<string> $uids
     * @return array{deleted: array<string, int>, refused: list<string>}
     */
    public function deleteElements(int $planId, array $uids, ?int $userId, bool $canUnlock = false): array
    {
        $find = $this->pdo()->prepare('SELECT id, updated_at_ms, locked FROM atak_plan_elements WHERE plan_id = ? AND uid = ? AND deleted = 0');
        $del = $this->pdo()->prepare('UPDATE atak_plan_elements SET deleted = 1, updated_at_ms = ?, updated_by = ? WHERE id = ?');
        $out = [];
        $refused = [];
        $now = max(self::nowMs(), $this->lastChangeMs($planId) + 1);
        foreach ($uids as $uid) {
            $find->execute([$planId, $uid]);
            $prev = $find->fetch(PDO::FETCH_ASSOC);
            if (!is_array($prev)) {
                continue;
            }
            if ((int) $prev['locked'] === 1 && !$canUnlock) {
                $refused[] = $uid;
                continue;
            }
            $ms = max($now, (int) $prev['updated_at_ms'] + 1);
            $del->execute([$ms, $userId, (int) $prev['id']]);
            $out[$uid] = $ms;
            $now = $ms + 1;
        }

        return ['deleted' => $out, 'refused' => $refused];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function presentElement(array $row): array
    {
        $dec = static function (mixed $s, mixed $def): mixed {
            $v = json_decode((string) $s, true);

            return is_array($v) ? $v : $def;
        };

        return [
            'uid' => (string) $row['uid'],
            'kind' => (string) $row['kind'],
            'geom' => (string) $row['geom'],
            'pts' => $dec($row['pts_json'], []),
            'props' => $dec($row['props_json'], []),
            'phases' => $dec($row['phases_json'], []),
            'layer' => (string) $row['layer_uid'],
            'group' => (string) $row['group_uid'],
            'z' => (int) $row['z'],
            'locked' => (int) $row['locked'] === 1,
            'deleted' => (int) $row['deleted'] === 1,
            'updated_at' => (int) $row['updated_at_ms'],
            'updated_by' => $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Phases                                                              */
    /* ------------------------------------------------------------------ */

    /** @return list<array<string, mixed>> */
    public function phases(int $planId, bool $withDeleted = false): array
    {
        $st = $this->pdo()->prepare('SELECT * FROM atak_plan_phases WHERE plan_id = ?' . ($withDeleted ? '' : ' AND deleted = 0') . ' ORDER BY ord ASC, id ASC');
        $st->execute([$planId]);

        return array_map(static fn (array $r): array => [
            'uid' => (string) $r['uid'],
            'ord' => (int) $r['ord'],
            'name' => (string) $r['name'],
            'note' => (string) ($r['note'] ?? ''),
            'time_label' => (string) $r['time_label'],
            'deleted' => (int) $r['deleted'] === 1,
            'updated_at' => (int) $r['updated_at_ms'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Remplace la liste des phases (celles absentes sont retirées, leurs éléments restent visibles dans toutes les phases).
     *
     * @param list<array<string, mixed>> $phases phases validées
     */
    public function replacePhases(int $tenantId, int $planId, array $phases): void
    {
        $now = max(self::nowMs(), $this->lastChangeMs($planId) + 1);
        $keep = [];
        $find = $this->pdo()->prepare('SELECT id, updated_at_ms FROM atak_plan_phases WHERE plan_id = ? AND uid = ?');
        $ins = $this->pdo()->prepare('INSERT INTO atak_plan_phases (tenant_id, plan_id, uid, ord, name, note, time_label, deleted, updated_at_ms) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)');
        $upd = $this->pdo()->prepare('UPDATE atak_plan_phases SET ord = ?, name = ?, note = ?, time_label = ?, deleted = 0, updated_at_ms = ? WHERE id = ?');
        foreach ($phases as $i => $ph) {
            $keep[] = $ph['uid'];
            $find->execute([$planId, $ph['uid']]);
            $prev = $find->fetch(PDO::FETCH_ASSOC);
            if (is_array($prev)) {
                $upd->execute([$i, $ph['name'], $ph['note'], $ph['time_label'], max($now, (int) $prev['updated_at_ms'] + 1), (int) $prev['id']]);
            } else {
                $ins->execute([$tenantId, $planId, $ph['uid'], $i, $ph['name'], $ph['note'], $ph['time_label'], $now]);
            }
        }
        $all = $this->phases($planId);
        $del = $this->pdo()->prepare('UPDATE atak_plan_phases SET deleted = 1, updated_at_ms = ? WHERE plan_id = ? AND uid = ?');
        foreach ($all as $ph) {
            if (!in_array($ph['uid'], $keep, true)) {
                $del->execute([max($now, $ph['updated_at'] + 1), $planId, $ph['uid']]);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Versions                                                            */
    /* ------------------------------------------------------------------ */

    /** @param array<string, mixed> $snapshot */
    public function saveVersion(int $tenantId, int $planId, int $rev, string $label, array $snapshot, ?int $userId): int
    {
        $this->pdo()->prepare('INSERT INTO atak_plan_versions (tenant_id, plan_id, rev, label, snapshot_json, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$tenantId, $planId, $rev, $label, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $userId, self::now()]);
        $id = (int) $this->pdo()->lastInsertId();
        // 60 versions gardées par plan : les plus anciennes partent.
        $st = $this->pdo()->prepare('SELECT id FROM atak_plan_versions WHERE plan_id = ? ORDER BY id DESC LIMIT 1 OFFSET 60');
        $st->execute([$planId]);
        $cut = (int) $st->fetchColumn();
        if ($cut > 0) {
            $this->pdo()->prepare('DELETE FROM atak_plan_versions WHERE plan_id = ? AND id <= ?')->execute([$planId, $cut]);
        }

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function versions(int $planId): array
    {
        $st = $this->pdo()->prepare('SELECT id, rev, label, created_by, created_at FROM atak_plan_versions WHERE plan_id = ? ORDER BY id DESC LIMIT 60');
        $st->execute([$planId]);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'rev' => (int) $r['rev'], 'label' => (string) $r['label'],
            'created_by' => $r['created_by'] !== null ? (int) $r['created_by'] : null, 'created_at' => (string) $r['created_at'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function version(int $planId, int $versionId): ?array
    {
        $st = $this->pdo()->prepare('SELECT snapshot_json FROM atak_plan_versions WHERE plan_id = ? AND id = ?');
        $st->execute([$planId, $versionId]);
        $v = json_decode((string) $st->fetchColumn(), true);

        return is_array($v) ? $v : null;
    }

    /* ------------------------------------------------------------------ */
    /* Partage, commentaires, présence                                     */
    /* ------------------------------------------------------------------ */

    /** @return list<array{scope:string, ref_id:int, can_edit:bool}> */
    public function shares(int $planId): array
    {
        $st = $this->pdo()->prepare('SELECT scope, ref_id, can_edit FROM atak_plan_shares WHERE plan_id = ? ORDER BY id');
        $st->execute([$planId]);

        return array_map(static fn (array $r): array => ['scope' => (string) $r['scope'], 'ref_id' => (int) $r['ref_id'], 'can_edit' => (int) $r['can_edit'] === 1], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @param list<array{scope:string, ref_id:int, can_edit:bool}> $shares */
    public function replaceShares(int $tenantId, int $planId, array $shares): void
    {
        $this->pdo()->prepare('DELETE FROM atak_plan_shares WHERE plan_id = ?')->execute([$planId]);
        $ins = $this->pdo()->prepare('INSERT INTO atak_plan_shares (tenant_id, plan_id, scope, ref_id, can_edit, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $seen = [];
        foreach ($shares as $s) {
            $k = $s['scope'] . ':' . $s['ref_id'];
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $ins->execute([$tenantId, $planId, $s['scope'], $s['ref_id'], $s['can_edit'] ? 1 : 0, self::now()]);
        }
    }

    public function addComment(int $tenantId, int $planId, int $userId, string $label, string $body, ?string $elementUid): int
    {
        $this->pdo()->prepare('INSERT INTO atak_plan_comments (tenant_id, plan_id, element_uid, user_id, author_label, body, resolved, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?)')
            ->execute([$tenantId, $planId, $elementUid, $userId, $label, $body, self::now()]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function resolveComment(int $planId, int $commentId, bool $resolved): bool
    {
        $st = $this->pdo()->prepare('UPDATE atak_plan_comments SET resolved = ? WHERE plan_id = ? AND id = ?');
        $st->execute([$resolved ? 1 : 0, $planId, $commentId]);

        return $st->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function comments(int $planId): array
    {
        $st = $this->pdo()->prepare('SELECT id, element_uid, user_id, author_label, body, resolved, created_at FROM atak_plan_comments WHERE plan_id = ? ORDER BY id DESC LIMIT 200');
        $st->execute([$planId]);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'element_uid' => $r['element_uid'] !== null ? (string) $r['element_uid'] : null,
            'user_id' => (int) $r['user_id'], 'author' => (string) $r['author_label'], 'body' => (string) $r['body'],
            'resolved' => (int) $r['resolved'] === 1, 'created_at' => (string) $r['created_at'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return list<array{user_id:int, label:string, tool:string}> */
    public function touchPresence(int $planId, int $userId, string $label, string $tool): array
    {
        $now = self::now();
        $st = $this->pdo()->prepare('UPDATE atak_plan_presence SET label = ?, tool = ?, seen_at = ? WHERE plan_id = ? AND user_id = ?');
        $st->execute([$label, $tool, $now, $planId, $userId]);
        if ($st->rowCount() === 0) {
            try {
                $this->pdo()->prepare('INSERT INTO atak_plan_presence (plan_id, user_id, label, tool, seen_at) VALUES (?, ?, ?, ?, ?)')->execute([$planId, $userId, $label, $tool, $now]);
            } catch (\Throwable) {
                // Course entre deux onglets du même membre : la ligne existe déjà.
            }
        }
        $q = $this->pdo()->prepare('SELECT user_id, label, tool FROM atak_plan_presence WHERE plan_id = ? AND seen_at >= ? ORDER BY label');
        $q->execute([$planId, gmdate('Y-m-d H:i:s', time() - 30)]);

        return array_map(static fn (array $r): array => ['user_id' => (int) $r['user_id'], 'label' => (string) $r['label'], 'tool' => (string) $r['tool']], $q->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
}
