<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class TenantDecorationMotifRepository
{
    private PDO $pdo;
    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function ready(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_decoration_motifs' LIMIT 1"
            );
            $st->execute();
            $this->ready = (bool) $st->fetchColumn();
        } catch (Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $includeArchived = false): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        $sql = 'SELECT * FROM tenant_decoration_motifs WHERE tenant_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND archived_at IS NULL';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC, id ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $tenantId): ?array
    {
        if ($id < 1 || $tenantId < 1 || !$this->ready()) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM tenant_decoration_motifs WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data, ?int $actorId = null): int
    {
        if ($tenantId < 1 || !$this->ready()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO tenant_decoration_motifs
             (tenant_id, name, motif_type, level_label, description, pattern_class, drop_class, disc_class, glyph, colors_json, image_path, sort_order, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            mb_substr(trim((string) ($data['name'] ?? '')), 0, 120),
            in_array(($data['motif_type'] ?? ''), ['medal'], true) ? 'medal' : 'ribbon',
            $this->nullableString($data['level_label'] ?? null, 40),
            $this->nullableString($data['description'] ?? null, 400),
            $this->patternClass((string) ($data['pattern_class'] ?? 'dk-rb-svc2')),
            $this->nullableString($data['drop_class'] ?? null, 64),
            $this->nullableString($data['disc_class'] ?? null, 64),
            $this->nullableString($data['glyph'] ?? null, 32),
            $this->colorsJson($data['colors'] ?? $data['colors_json'] ?? null),
            $this->nullableString($data['image_path'] ?? null, 255),
            (int) ($data['sort_order'] ?? 0),
            $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $id, array $data): bool
    {
        if ($tenantId < 1 || $id < 1 || !$this->ready()) {
            return false;
        }
        $sets = [];
        $params = [];
        $map = [
            'name' => static fn ($v) => mb_substr(trim((string) $v), 0, 120),
            'motif_type' => static fn ($v) => in_array((string) $v, ['medal'], true) ? 'medal' : 'ribbon',
            'level_label' => fn ($v) => $this->nullableString($v, 40),
            'description' => fn ($v) => $this->nullableString($v, 400),
            'pattern_class' => fn ($v) => $this->patternClass((string) $v),
            'drop_class' => fn ($v) => $this->nullableString($v, 64),
            'disc_class' => fn ($v) => $this->nullableString($v, 64),
            'glyph' => fn ($v) => $this->nullableString($v, 32),
            'image_path' => fn ($v) => $this->nullableString($v, 255),
            'sort_order' => static fn ($v) => (int) $v,
        ];
        foreach ($map as $col => $cast) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $sets[] = $col . ' = ?';
            $params[] = $cast($data[$col]);
        }
        if (array_key_exists('colors', $data) || array_key_exists('colors_json', $data)) {
            $sets[] = 'colors_json = ?';
            $params[] = $this->colorsJson($data['colors'] ?? $data['colors_json'] ?? null);
        }
        if ($sets === []) {
            return false;
        }
        $params[] = $id;
        $params[] = $tenantId;
        $st = $this->pdo->prepare(
            'UPDATE tenant_decoration_motifs SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?'
        );

        return $st->execute($params);
    }

    public function archive(int $tenantId, int $id): bool
    {
        if ($tenantId < 1 || $id < 1 || !$this->ready()) {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE tenant_decoration_motifs SET archived_at = NOW() WHERE id = ? AND tenant_id = ? AND archived_at IS NULL'
        );

        return $st->execute([$id, $tenantId]);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, $max);
    }

    private function patternClass(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || !preg_match('/^dk-[a-z0-9_-]+$/i', $raw)) {
            return 'dk-rb-svc2';
        }

        return $raw;
    }

    private function colorsJson(mixed $raw): ?string
    {
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        if (!is_array($raw)) {
            return null;
        }
        $colors = [];
        foreach ($raw as $hex) {
            $hex = strtoupper(trim((string) $hex));
            if (preg_match('/^#[0-9A-F]{6}$/', $hex)) {
                $colors[] = $hex;
            }
        }
        if ($colors === []) {
            return null;
        }

        return json_encode(array_values(array_unique($colors)), JSON_UNESCAPED_UNICODE) ?: null;
    }
}
