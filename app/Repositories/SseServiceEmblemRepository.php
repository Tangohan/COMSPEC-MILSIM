<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Emblèmes téléversés des services demandeurs (table sse_service_emblems, étape 4830) : un emblème au plus par service et par
 * communauté, avec ses mesures, sa version et la confirmation de celui qui l'a téléversé (« emblème de la communauté ou création
 * originale »). Toute requête est bornée à la communauté. Sans la table (étape en attente) : aucun emblème, aucune erreur.
 */
final class SseServiceEmblemRepository
{
    private Database $db;

    /** @var \WeakMap<PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $readyByConnection = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
            self::$readyByConnection ??= new \WeakMap();
            $known = self::$readyByConnection[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                $pdo->query('SELECT 1 FROM sse_service_emblems WHERE 1 = 0');
                self::$readyByConnection[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                self::$readyByConnection[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, string $serviceCode): ?array
    {
        if ($tenantId < 1 || $serviceCode === '' || !self::ready()) {
            return null;
        }
        try {
            $row = $this->db->fetchOne('SELECT * FROM sse_service_emblems WHERE tenant_id = :t AND service_code = :c', ['t' => $tenantId, 'c' => $serviceCode]);
        } catch (\Throwable $e) {
            error_log('Emblèmes de service : lecture impossible : ' . $e->getMessage());

            return null;
        }

        return $row !== null ? self::hydrate($row) : null;
    }

    /** @return array<string, array<string, mixed>> code du service => emblème */
    public function forTenant(int $tenantId): array
    {
        $out = [];
        if ($tenantId < 1 || !self::ready()) {
            return $out;
        }
        try {
            foreach ($this->db->fetchAll('SELECT * FROM sse_service_emblems WHERE tenant_id = :t ORDER BY service_code ASC LIMIT 500', ['t' => $tenantId]) as $row) {
                $out[(string) $row['service_code']] = self::hydrate($row);
            }
        } catch (\Throwable $e) {
            error_log('Emblèmes de service : liste illisible : ' . $e->getMessage());

            return [];
        }

        return $out;
    }

    /**
     * Pose ou remplace l'emblème d'un service ; la version monte d'un cran à chaque remplacement. Renvoie la nouvelle version.
     *
     * @param array{path: string, thumb_path: ?string, mime: string, width: int, height: int, bytes: int} $file
     */
    public function put(int $tenantId, string $serviceCode, array $file, ?int $userId, string $userLabel): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->db->fetchOne('SELECT id, version FROM sse_service_emblems WHERE tenant_id = :t AND service_code = :c', ['t' => $tenantId, 'c' => $serviceCode]);
        $params = [
            'path' => $file['path'], 'thumb' => $file['thumb_path'], 'mime' => $file['mime'], 'w' => $file['width'], 'h' => $file['height'], 'b' => $file['bytes'],
            'u' => $userId, 'l' => mb_substr($userLabel, 0, 80), 'at' => $now, 'now' => $now,
        ];
        if ($existing !== null) {
            $version = (int) $existing['version'] + 1;
            $this->db->execute(
                'UPDATE sse_service_emblems SET path = :path, thumb_path = :thumb, mime = :mime, width = :w, height = :h, bytes = :b, version = :v,
                    confirmed_by_user_id = :u, confirmed_by_label = :l, confirmed_at = :at, updated_at = :now WHERE id = :id AND tenant_id = :t',
                $params + ['v' => $version, 'id' => (int) $existing['id'], 't' => $tenantId]
            );

            return $version;
        }
        $this->db->insert(
            'INSERT INTO sse_service_emblems (tenant_id, service_code, path, thumb_path, mime, width, height, bytes, version, confirmed_by_user_id, confirmed_by_label, confirmed_at, created_at, updated_at)
             VALUES (:t, :c, :path, :thumb, :mime, :w, :h, :b, 1, :u, :l, :at, :created, :now)',
            $params + ['t' => $tenantId, 'c' => $serviceCode, 'created' => $now]
        );

        return 1;
    }

    public function delete(int $tenantId, string $serviceCode): bool
    {
        if ($tenantId < 1 || !self::ready()) {
            return false;
        }

        return $this->db->execute('DELETE FROM sse_service_emblems WHERE tenant_id = :t AND service_code = :c', ['t' => $tenantId, 'c' => $serviceCode]) > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'service_code' => (string) $row['service_code'],
            'path' => (string) $row['path'],
            'thumb_path' => ($row['thumb_path'] ?? null) !== null && $row['thumb_path'] !== '' ? (string) $row['thumb_path'] : null,
            'mime' => (string) $row['mime'],
            'width' => (int) $row['width'],
            'height' => (int) $row['height'],
            'bytes' => (int) $row['bytes'],
            'version' => max(1, (int) $row['version']),
            'confirmed_by_user_id' => ($row['confirmed_by_user_id'] ?? null) !== null ? (int) $row['confirmed_by_user_id'] : null,
            'confirmed_by_label' => (string) ($row['confirmed_by_label'] ?? ''),
            'confirmed_at' => ($row['confirmed_at'] ?? null) !== null ? (string) $row['confirmed_at'] : null,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
