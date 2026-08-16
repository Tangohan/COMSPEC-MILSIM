<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

final class SseWatchlistRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        static $done = false;
        if (!$done) {
            SilentSchemaMigration::run(base_path('bootstrap/atak_sse_persons_migration.php'));
            $done = true;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO sse_watchlist_entries (
                tenant_id, last_name, first_name, alias, threat_level, notes, active
            ) VALUES (
                :tenant_id, :last_name, :first_name, :alias, :threat_level, :notes, 1
            )',
            [
                'tenant_id' => (int) $data['tenant_id'],
                'last_name' => trim((string) ($data['last_name'] ?? '')),
                'first_name' => trim((string) ($data['first_name'] ?? '')),
                'alias' => ($a = trim((string) ($data['alias'] ?? ''))) !== '' ? $a : null,
                'threat_level' => in_array(($data['threat_level'] ?? ''), ['surveillance', 'prioritaire'], true)
                    ? $data['threat_level'] : 'surveillance',
                'notes' => ($n = trim((string) ($data['notes'] ?? ''))) !== '' ? $n : null,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(int $tenantId, string $q = ''): array
    {
        $where = ['tenant_id = :t', 'active = 1'];
        $params = ['t' => $tenantId];
        $q = trim($q);
        if ($q !== '') {
            $where[] = '(last_name LIKE :q1 OR first_name LIKE :q2 OR COALESCE(alias, \'\') LIKE :q3 OR COALESCE(notes, \'\') LIKE :q4)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_watchlist_entries WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT 500',
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function deactivate(int $id, int $tenantId): bool
    {
        return $this->db->execute(
            'UPDATE sse_watchlist_entries SET active = 0 WHERE id = :id AND tenant_id = :t',
            ['id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $level = (string) ($row['threat_level'] ?? 'surveillance');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'alias' => $row['alias'] ?? null,
            'display_name' => trim(($row['last_name'] ?? '') . ' ' . ($row['first_name'] ?? ''))
                ?: (string) ($row['alias'] ?? 'Entrée sans nom'),
            'threat_level' => $level,
            'threat_level_label' => $level === 'prioritaire' ? 'Personne prioritaire' : 'Surveillance',
            'notes' => $row['notes'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
