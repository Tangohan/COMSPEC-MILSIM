<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Questions custom du formulaire de recrutement Discord (select / ouverte / fermée / libre),
 * configurées par tenant.
 */
final class RecruitmentDiscordQuestionRepository
{
    public const TYPES = ['select', 'open', 'closed', 'free'];

    private PDO $pdo;

    private static ?bool $tableExists = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
        $this->ensureSchema();
    }

    /**
     * Applique la migration Discord si absente (déploiement code avant run-migrations).
     */
    private function ensureSchema(): void
    {
        if (self::$tableExists === true) {
            return;
        }
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/discord_recruitment_migration.php';
            ensure_discord_recruitment_schema($this->pdo);
            self::$tableExists = null;
        } catch (\Throwable) {
            // Laisser tableExists() renvoyer false et l’UI afficher le bandeau.
        }
    }

    /**
     * Vérifie que la migration a bien été exécutée sur cet environnement — évite de faire
     * planter le formulaire public si le déploiement du code précède la migration.
     */
    public function tableExists(): bool
    {
        if (self::$tableExists === null) {
            try {
                $st = $this->pdo->prepare(
                    "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_discord_questions' LIMIT 1"
                );
                $st->execute();
                self::$tableExists = (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$tableExists = false;
            }
        }

        return self::$tableExists;
    }

    private function decode(array $row): array
    {
        $options = $row['options_json'] ?? null;
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $row['options'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['options'] = [];
        }
        unset($row['options_json']);
        $row['id'] = (int) $row['id'];
        $row['required'] = (bool) $row['required'];
        $row['active'] = (bool) $row['active'];
        $row['position'] = (int) $row['position'];

        return $row;
    }

    /** @return list<array{id:int,type:string,label:string,options:list<string>,required:bool,position:int,active:bool}> */
    public function listForTenant(int $tenantId, bool $activeOnly = false): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $sql = 'SELECT id, type, label, options_json, required, position, active FROM recruitment_discord_questions WHERE tenant_id = ?';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY position ASC, id ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return array_map(fn (array $r): array => $this->decode($r), $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findForTenant(int $tenantId, int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT id, type, label, options_json, required, position, active FROM recruitment_discord_questions WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->decode($row) : null;
    }

    /**
     * @param list<string> $options
     */
    public function create(int $tenantId, string $type, string $label, array $options, bool $required): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $type = in_array($type, self::TYPES, true) ? $type : 'open';
        $st = $this->pdo->prepare(
            'SELECT COALESCE(MAX(position), -1) + 1 FROM recruitment_discord_questions WHERE tenant_id = ?'
        );
        $st->execute([$tenantId]);
        $position = (int) $st->fetchColumn();

        $ins = $this->pdo->prepare(
            'INSERT INTO recruitment_discord_questions (tenant_id, type, label, options_json, required, position, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
        );
        $ins->execute([
            $tenantId,
            $type,
            mb_substr(trim($label), 0, 255),
            $type === 'select' && $options !== [] ? json_encode(array_values($options), JSON_UNESCAPED_UNICODE) : null,
            $required ? 1 : 0,
            $position,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param list<string> $options
     */
    public function update(int $tenantId, int $id, string $type, string $label, array $options, bool $required): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $type = in_array($type, self::TYPES, true) ? $type : 'open';
        $st = $this->pdo->prepare(
            'UPDATE recruitment_discord_questions SET type = ?, label = ?, options_json = ?, required = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([
            $type,
            mb_substr(trim($label), 0, 255),
            $type === 'select' && $options !== [] ? json_encode(array_values($options), JSON_UNESCAPED_UNICODE) : null,
            $required ? 1 : 0,
            $tenantId,
            $id,
        ]);

        return $st->rowCount() > 0;
    }

    public function setActive(int $tenantId, int $id, bool $active): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $st = $this->pdo->prepare('UPDATE recruitment_discord_questions SET active = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?');
        $st->execute([$active ? 1 : 0, $tenantId, $id]);

        return $st->rowCount() > 0;
    }

    public function delete(int $tenantId, int $id): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM recruitment_discord_questions WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    /** @param list<int> $orderedIds */
    public function reorder(int $tenantId, array $orderedIds): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $position = 0;
        $st = $this->pdo->prepare('UPDATE recruitment_discord_questions SET position = ? WHERE tenant_id = ? AND id = ?');
        foreach ($orderedIds as $id) {
            $st->execute([$position, $tenantId, (int) $id]);
            $position++;
        }
    }
}
