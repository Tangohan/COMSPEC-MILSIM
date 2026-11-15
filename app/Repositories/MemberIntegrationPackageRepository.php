<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Paquets d'intégration d'une communauté (ce que reçoit un nouveau membre) et trace de leur application.
 * Toutes les lectures et écritures sont bornées par la communauté.
 */
final class MemberIntegrationPackageRepository
{
    public const ITEM_QUALIFICATION = 'qualification';
    public const ITEM_COURSE = 'course';

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function tablesExist(): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM member_integration_packages LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM member_integration_package_items LIMIT 1');
            $this->pdo()->query('SELECT 1 FROM member_integration_applications LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM member_integration_packages WHERE tenant_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY is_default DESC, name ASC, id ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$tenantId]);

        return array_map([$this, 'hydrate'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM member_integration_packages WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $roles = [];
        $decoded = json_decode((string) ($row['role_ids_json'] ?? ''), true);
        if (is_array($decoded)) {
            foreach ($decoded as $v) {
                if ((int) $v > 0) {
                    $roles[(int) $v] = (int) $v;
                }
            }
        }
        $row['id'] = (int) $row['id'];
        $row['tenant_id'] = (int) $row['tenant_id'];
        $row['is_default'] = (int) ($row['is_default'] ?? 0);
        $row['is_active'] = (int) ($row['is_active'] ?? 1);
        foreach (['opening_id', 'match_unit_id', 'grade_id', 'unit_id', 'personnel_job_role_id'] as $k) {
            $row[$k] = (int) ($row[$k] ?? 0);
        }
        $row['role_ids'] = array_values($roles);
        $row['welcome_message'] = (string) ($row['welcome_message'] ?? '');

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function items(int $tenantId, int $packageId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM member_integration_package_items WHERE tenant_id = ? AND package_id = ? ORDER BY item_type ASC, sort_order ASC, id ASC'
        );
        $st->execute([$tenantId, $packageId]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $rows[] = [
                'id' => (int) $r['id'],
                'item_type' => (string) $r['item_type'],
                'ref_id' => (int) $r['ref_id'],
                'status' => (string) ($r['status'] ?? 'obtained'),
                'valid_months' => (int) ($r['valid_months'] ?? 0),
                'sort_order' => (int) ($r['sort_order'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Crée (id 0) ou met à jour un paquet et remplace ses éléments, en une seule transaction.
     *
     * @param array<string, mixed> $data
     * @param list<array{item_type: string, ref_id: int, status?: string, valid_months?: int}> $items
     */
    public function save(int $tenantId, int $id, array $data, array $items, ?int $actorId): int
    {
        $pdo = $this->pdo();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            $now = date('Y-m-d H:i:s');
            $roles = array_values(array_unique(array_filter(array_map('intval', (array) ($data['role_ids'] ?? [])), static fn (int $v): bool => $v > 0)));
            $nullable = static fn (mixed $v): ?int => (int) $v > 0 ? (int) $v : null;
            $values = [
                mb_substr(trim((string) ($data['name'] ?? '')), 0, 160),
                !empty($data['is_default']) ? 1 : 0,
                array_key_exists('is_active', $data) && !$data['is_active'] ? 0 : 1,
                $nullable($data['opening_id'] ?? 0),
                $nullable($data['match_unit_id'] ?? 0),
                $nullable($data['grade_id'] ?? 0),
                $nullable($data['unit_id'] ?? 0),
                $nullable($data['personnel_job_role_id'] ?? 0),
                $roles !== [] ? json_encode($roles) : null,
                trim((string) ($data['welcome_message'] ?? '')) !== '' ? mb_substr(trim((string) $data['welcome_message']), 0, 4000) : null,
            ];
            if (!empty($data['is_default'])) {
                $pdo->prepare('UPDATE member_integration_packages SET is_default = 0 WHERE tenant_id = ?')->execute([$tenantId]);
            }
            if ($id > 0) {
                $up = $pdo->prepare(
                    'UPDATE member_integration_packages SET name = ?, is_default = ?, is_active = ?, opening_id = ?, match_unit_id = ?, grade_id = ?, unit_id = ?, personnel_job_role_id = ?, role_ids_json = ?, welcome_message = ?, updated_at = ? WHERE tenant_id = ? AND id = ?'
                );
                $up->execute([...$values, $now, $tenantId, $id]);
                if ($up->rowCount() < 1 && $this->find($tenantId, $id) === null) {
                    throw new \RuntimeException('Paquet introuvable.');
                }
            } else {
                $pdo->prepare(
                    'INSERT INTO member_integration_packages (tenant_id, name, is_default, is_active, opening_id, match_unit_id, grade_id, unit_id, personnel_job_role_id, role_ids_json, welcome_message, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$tenantId, ...$values, $actorId, $now, $now]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM member_integration_package_items WHERE tenant_id = ? AND package_id = ?')->execute([$tenantId, $id]);
            $ins = $pdo->prepare(
                'INSERT INTO member_integration_package_items (tenant_id, package_id, item_type, ref_id, status, valid_months, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $seen = [];
            $order = 0;
            foreach ($items as $it) {
                $type = (string) ($it['item_type'] ?? '');
                $ref = (int) ($it['ref_id'] ?? 0);
                if (!in_array($type, [self::ITEM_QUALIFICATION, self::ITEM_COURSE], true) || $ref < 1 || isset($seen[$type . ':' . $ref])) {
                    continue;
                }
                $seen[$type . ':' . $ref] = true;
                $status = $type === self::ITEM_QUALIFICATION && (string) ($it['status'] ?? '') === 'in_training' ? 'in_training' : 'obtained';
                $months = $type === self::ITEM_QUALIFICATION ? max(0, min(600, (int) ($it['valid_months'] ?? 0))) : 0;
                $ins->execute([$tenantId, $id, $type, $ref, $status, $months > 0 ? $months : null, $order++]);
            }
            if ($own) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $id;
    }

    public function delete(int $tenantId, int $id): bool
    {
        $pdo = $this->pdo();
        $pdo->prepare('DELETE FROM member_integration_package_items WHERE tenant_id = ? AND package_id = ?')->execute([$tenantId, $id]);
        $st = $pdo->prepare('DELETE FROM member_integration_packages WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    /**
     * Paquet retenu pour une candidature : celui de l'offre, sinon celui de l'unité, sinon celui par défaut.
     * Aucun paquet actif correspondant : null (l'intégration se passe comme avant).
     *
     * @return array<string, mixed>|null
     */
    public function resolve(int $tenantId, int $openingId, int $unitId): ?array
    {
        $packages = $this->listForTenant($tenantId, true);
        foreach ($packages as $p) {
            if ($openingId > 0 && (int) $p['opening_id'] === $openingId) {
                return $p;
            }
        }
        foreach ($packages as $p) {
            if ($unitId > 0 && (int) $p['opening_id'] === 0 && (int) $p['match_unit_id'] === $unitId) {
                return $p;
            }
        }
        foreach ($packages as $p) {
            if ((int) $p['is_default'] === 1 && (int) $p['opening_id'] === 0 && (int) $p['match_unit_id'] === 0) {
                return $p;
            }
        }

        return null;
    }

    /** Libellés des définitions de qualification actives de la communauté, par identifiant. @return array<int, string> */
    public function qualificationNames(int $tenantId, ?array $ids = null): array
    {
        return $this->names(
            'SELECT id, name FROM personnel_qualification_definitions WHERE tenant_id = ? AND archived_at IS NULL',
            $tenantId,
            $ids
        );
    }

    /** Formations de la communauté, par identifiant. @return array<int, string> */
    public function courseNames(int $tenantId, ?array $ids = null): array
    {
        return $this->names('SELECT id, title AS name FROM training_courses WHERE tenant_id = ?', $tenantId, $ids);
    }

    /**
     * @param list<int>|null $ids
     * @return array<int, string>
     */
    private function names(string $sql, int $tenantId, ?array $ids): array
    {
        if ($ids !== null) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0)));
            if ($ids === []) {
                return [];
            }
            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        }
        $st = $this->pdo()->prepare($sql . ' ORDER BY name ASC');
        $st->execute([$tenantId, ...($ids ?? [])]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function application(int $tenantId, int $enlistmentId): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM member_integration_applications WHERE tenant_id = ? AND enlistment_id = ? LIMIT 1');
        $st->execute([$tenantId, $enlistmentId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $applied = json_decode((string) ($row['applied_json'] ?? ''), true);
        $row['applied'] = is_array($applied) ? $applied : [];

        return $row;
    }

    /** Enregistre ou complète la trace d'application d'une candidature (une ligne par candidature). @param array<string, mixed> $applied */
    public function recordApplication(int $tenantId, int $enlistmentId, int $userId, ?int $packageId, int $actorId, array $applied): void
    {
        $now = date('Y-m-d H:i:s');
        $json = json_encode($applied, JSON_UNESCAPED_UNICODE);
        $existing = $this->application($tenantId, $enlistmentId);
        if ($existing === null) {
            $this->pdo()->prepare(
                'INSERT INTO member_integration_applications (tenant_id, enlistment_id, user_id, package_id, actor_user_id, applied_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$tenantId, $enlistmentId, $userId, $packageId, $actorId, $json, $now, $now]);

            return;
        }
        $this->pdo()->prepare(
            'UPDATE member_integration_applications SET user_id = ?, package_id = ?, actor_user_id = ?, applied_json = ?, updated_at = ? WHERE tenant_id = ? AND enlistment_id = ?'
        )->execute([$userId, $packageId, $actorId, $json, $now, $tenantId, $enlistmentId]);
    }

    public function markWelcomeSent(int $tenantId, int $enlistmentId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->pdo()->prepare(
            'UPDATE member_integration_applications SET welcome_sent_at = ?, updated_at = ? WHERE tenant_id = ? AND enlistment_id = ? AND welcome_sent_at IS NULL'
        )->execute([$now, $now, $tenantId, $enlistmentId]);
    }
}
