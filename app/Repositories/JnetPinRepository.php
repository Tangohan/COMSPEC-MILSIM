<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Jnet\JnetPinRules;
use App\Services\Jnet\JnetPinStore;
use PDO;

/**
 * Épingles de l'espace commun JNET (table jnet_pins, étape jnet_pins). Tolère l'absence de la table : lectures vides,
 * écritures sans effet. Toutes les requêtes sont bornées par communauté et par espace ; aucune ne décide d'un droit.
 */
class JnetPinRepository implements JnetPinStore
{
    private ?bool $ready = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function ready(): bool
    {
        if ($this->ready === null) {
            try {
                $this->pdo()->query('SELECT 1 FROM jnet_pins LIMIT 1');
                $this->ready = true;
            } catch (\Throwable) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    public function all(int $tenantId): array
    {
        if (!$this->ready()) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT id, target_type, target_id, `position`, pinned_by, pinned_at FROM jnet_pins
                 WHERE tenant_id = ? AND scope = ? ORDER BY `position`, id LIMIT ' . (JnetPinRules::MAX + 5)
            );
            $st->execute([$tenantId, JnetPinRules::SCOPE]);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'target_type' => (string) $r['target_type'],
                'target_id' => (string) $r['target_id'],
                'position' => (int) $r['position'],
                'pinned_by' => (int) $r['pinned_by'],
                'pinned_at' => (string) $r['pinned_at'],
            ];
        }

        return $out;
    }

    public function add(int $tenantId, string $type, string $id, int $userId): int
    {
        if (!$this->ready()) {
            return 0;
        }
        try {
            $next = $this->pdo()->prepare('SELECT COALESCE(MAX(`position`), 0) + 1 FROM jnet_pins WHERE tenant_id = ? AND scope = ?');
            $next->execute([$tenantId, JnetPinRules::SCOPE]);
            $this->pdo()->prepare(
                'INSERT INTO jnet_pins (tenant_id, scope, target_type, target_id, `position`, pinned_by) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$tenantId, JnetPinRules::SCOPE, $type, $id, (int) $next->fetchColumn(), $userId]);

            return (int) $this->pdo()->lastInsertId();
        } catch (\PDOException) {
            // Déjà épinglé (clé unique) : rien à faire.
            return 0;
        }
    }

    public function remove(int $tenantId, int $pinId): void
    {
        if (!$this->ready()) {
            return;
        }
        $this->pdo()->prepare('DELETE FROM jnet_pins WHERE tenant_id = ? AND scope = ? AND id = ?')
            ->execute([$tenantId, JnetPinRules::SCOPE, $pinId]);
    }

    public function setOrder(int $tenantId, array $ids): void
    {
        if (!$this->ready() || $ids === []) {
            return;
        }
        $up = $this->pdo()->prepare('UPDATE jnet_pins SET `position` = ? WHERE tenant_id = ? AND scope = ? AND id = ?');
        foreach (array_values($ids) as $rank => $id) {
            $up->execute([$rank + 1, $tenantId, JnetPinRules::SCOPE, (int) $id]);
        }
    }
}
