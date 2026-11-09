<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Historique des versions des kits de départ (table game_role_kit_versions, étape equipment_configurator) : une
 * version par enregistrement dont le contenu ou le nom change, numérotée à partir de 1 pour chaque kit. Sans la table,
 * rien n'est écrit et l'historique est vide (le kit s'enregistre comme avant).
 */
final class GameRoleKitVersionRepository
{
    use LazyDatabaseConnection;

    public const KEEP = 50;

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tableReady(): bool
    {
        if (self::$ready === true) {
            return true;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_role_kit_versions' LIMIT 1"
            );
            $ok = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready = true;
        }

        return $ok;
    }

    /**
     * Ajoute une version si le nom ou le contenu diffère de la dernière. Retourne le numéro (0 si rien n'est écrit).
     */
    public function record(int $tenantId, int $kitId, int $userId, string $name, string $payload, string $note = ''): int
    {
        if ($tenantId < 1 || $kitId < 1 || !$this->tableReady()) {
            return 0;
        }
        $last = $this->latest($tenantId, $kitId);
        if ($last !== null && $last['name'] === $name && $last['payload_text'] === $payload) {
            return 0;
        }
        $no = ($last['version_no'] ?? 0) + 1;
        $this->pdo()->prepare(
            'INSERT INTO game_role_kit_versions (tenant_id, kit_id, version_no, name, payload_text, note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$tenantId, $kitId, $no, mb_substr($name, 0, 120), $payload, $note !== '' ? mb_substr($note, 0, 190) : null, $userId > 0 ? $userId : null]);
        // On garde les 50 dernières versions d'un kit.
        $this->pdo()->prepare(
            'DELETE FROM game_role_kit_versions WHERE tenant_id = ? AND kit_id = ? AND version_no <= ?'
        )->execute([$tenantId, $kitId, $no - self::KEEP]);

        return $no;
    }

    /** @return array<string, mixed>|null */
    public function latest(int $tenantId, int $kitId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT version_no, name, payload_text FROM game_role_kit_versions WHERE tenant_id = ? AND kit_id = ? ORDER BY version_no DESC LIMIT 1'
        );
        $st->execute([$tenantId, $kitId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? ['version_no' => (int) $row['version_no'], 'name' => (string) $row['name'], 'payload_text' => (string) $row['payload_text']] : null;
    }

    /**
     * Versions d'un kit, de la plus récente à la plus ancienne (sans le contenu).
     *
     * @return list<array{version_no: int, name: string, note: string, created_at: string, author: string, bytes: int}>
     */
    public function listForKit(int $tenantId, int $kitId): array
    {
        if ($tenantId < 1 || $kitId < 1 || !$this->tableReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT v.version_no, v.name, v.note, v.created_at, CHAR_LENGTH(v.payload_text) AS bytes,
                    COALESCE(NULLIF(TRIM(u.callsign), \'\'), NULLIF(TRIM(u.display_name), \'\'), \'\') AS author
             FROM game_role_kit_versions v
             LEFT JOIN users u ON u.id = v.created_by
             WHERE v.tenant_id = ? AND v.kit_id = ?
             ORDER BY v.version_no DESC
             LIMIT ' . self::KEEP
        );
        $st->execute([$tenantId, $kitId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = [
                'version_no' => (int) $r['version_no'],
                'name' => (string) $r['name'],
                'note' => (string) ($r['note'] ?? ''),
                'created_at' => (string) $r['created_at'],
                'author' => (string) ($r['author'] ?? ''),
                'bytes' => (int) ($r['bytes'] ?? 0),
            ];
        }

        return $out;
    }

    /** @return array{version_no: int, name: string, payload_text: string}|null */
    public function find(int $tenantId, int $kitId, int $versionNo): ?array
    {
        if ($tenantId < 1 || $kitId < 1 || $versionNo < 1 || !$this->tableReady()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT version_no, name, payload_text FROM game_role_kit_versions WHERE tenant_id = ? AND kit_id = ? AND version_no = ? LIMIT 1'
        );
        $st->execute([$tenantId, $kitId, $versionNo]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? ['version_no' => (int) $row['version_no'], 'name' => (string) $row['name'], 'payload_text' => (string) $row['payload_text']] : null;
    }
}
