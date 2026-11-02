<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Support\Migrations\SchemaInspector;
use PDO;

/**
 * Écritures communes des qualifications fournies par le site (catalogue OTAN importé, qualifications des cours
 * intégrés de l’Academy) : émetteur et catégorie trouvés ou créés par leur nom, fiche du référentiel créée seulement
 * si son code n’existe pas encore dans la communauté. Ne modifie ni ne supprime jamais une fiche existante.
 * Tolère un référentiel antérieur aux émetteurs enrichis (colonnes facultatives vérifiées une fois).
 */
final class QualificationReferenceWriter
{
    /** @var array<string, bool> */
    private array $columns = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function has(string $table, ?string $column = null): bool
    {
        $k = $table . '.' . ($column ?? '');
        if (!isset($this->columns[$k])) {
            $s = new SchemaInspector($this->pdo);
            $this->columns[$k] = $column === null ? $s->tableExists($table) : $s->tableExists($table) && $s->columnExists($table, $column);
        }

        return $this->columns[$k];
    }

    public function definitionId(int $tenantId, string $code): int
    {
        $st = $this->pdo->prepare('SELECT id FROM personnel_qualification_definitions WHERE tenant_id = ? AND code = ? LIMIT 1');
        $st->execute([$tenantId, $code]);

        return (int) ($st->fetchColumn() ?: 0);
    }

    /** Fiche dont le code correspond, casse ignorée (0 si aucune). */
    public function definitionIdCaseless(int $tenantId, string $code): int
    {
        $st = $this->pdo->prepare('SELECT id FROM personnel_qualification_definitions WHERE tenant_id = ? AND UPPER(code) = ? ORDER BY id LIMIT 1');
        $st->execute([$tenantId, strtoupper(trim($code))]);

        return (int) ($st->fetchColumn() ?: 0);
    }

    /** @return array<string, true> codes déjà présents dans le référentiel de la communauté (casse ignorée) */
    public function existingCodes(int $tenantId): array
    {
        $st = $this->pdo->prepare('SELECT code FROM personnel_qualification_definitions WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $code) {
            $out[strtoupper(trim((string) $code))] = true;
        }

        return $out;
    }

    /** Émetteur trouvé par son nom, ou créé (module Émetteurs) ; null sans table des émetteurs. */
    public function ensureIssuer(int $tenantId, string $name, string $shortName, string $kind, string $description, ?int $actorId): ?int
    {
        if (!$this->has('qualification_issuers')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT id FROM qualification_issuers WHERE tenant_id = ? AND name = ? LIMIT 1');
        $st->execute([$tenantId, $name]);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        $cols = ['tenant_id', 'name', 'short_name', 'issuer_kind'];
        $vals = [$tenantId, mb_substr($name, 0, 200), mb_substr($shortName, 0, 80), $kind];
        if ($this->has('qualification_issuers', 'description')) {
            $cols[] = 'description';
            $vals[] = $description;
        }
        if ($this->has('qualification_issuers', 'created_by')) {
            $cols[] = 'created_by';
            $vals[] = $actorId;
        }
        $this->pdo->prepare('INSERT INTO qualification_issuers (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute($vals);

        return (int) $this->pdo->lastInsertId() ?: null;
    }

    /** Catégorie du référentiel trouvée par son nom, ou créée ; null sans catégories. */
    public function ensureCategory(int $tenantId, string $name): ?int
    {
        if (!$this->has('qualification_categories') || !$this->has('personnel_qualification_definitions', 'category_id')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT id FROM qualification_categories WHERE tenant_id = ? AND name = ? LIMIT 1');
        $st->execute([$tenantId, $name]);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        $now = date('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT INTO qualification_categories (tenant_id, name, sort_order, created_at, updated_at) VALUES (?, ?, 0, ?, ?)')
            ->execute([$tenantId, mb_substr($name, 0, 150), $now, $now]);

        return (int) $this->pdo->lastInsertId() ?: null;
    }

    /**
     * Nouvelle fiche du référentiel (le code doit être libre : vérifié par l’appelant).
     *
     * @param array{code: string, name: string, short_name?: string, description: string, category_id?: ?int, issuer_id?: ?int, validity_months?: ?int} $data
     */
    public function createDefinition(int $tenantId, array $data, ?int $actorId): int
    {
        $now = date('Y-m-d H:i:s');
        $cols = ['tenant_id', 'code', 'name', 'description', 'is_active', 'created_at', 'updated_at'];
        $vals = [$tenantId, mb_substr($data['code'], 0, 64), mb_substr($data['name'], 0, 150), $data['description'], 1, $now, $now];
        $optional = [
            'short_name' => ($data['short_name'] ?? '') !== '' ? mb_substr((string) $data['short_name'], 0, 40) : null,
            'category_id' => $data['category_id'] ?? null,
            'default_issuer_id' => $data['issuer_id'] ?? null,
            'default_validity_months' => $data['validity_months'] ?? null,
            'validity_days' => isset($data['validity_months']) ? (int) $data['validity_months'] * 30 : null,
            'is_permanent' => isset($data['validity_months']) ? 0 : 1,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
        foreach ($optional as $col => $value) {
            if ($value !== null && $this->has('personnel_qualification_definitions', $col)) {
                $cols[] = $col;
                $vals[] = $value;
            }
        }
        $this->pdo->prepare('INSERT INTO personnel_qualification_definitions (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute($vals);

        return (int) $this->pdo->lastInsertId();
    }
}
