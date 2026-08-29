<?php

declare(strict_types=1);

namespace App\Services\Rank;

use App\Core\Database;
use PDO;

/**
 * Seeds rank_catalog (FR ARMY + Gendarmerie partielle) et audit/réparation contrôlée.
 */
final class RankCatalogService
{
    public function __construct(
        private RankReferenceValidator $validator,
    ) {}

    /**
     * @return array{seeded: int, audited: int, repaired: int, invalid: int}
     */
    public function bootstrapAndAudit(bool $repairCertain = true): array
    {
        $pdo = Database::getPdo();
        $stats = ['seeded' => 0, 'audited' => 0, 'repaired' => 0, 'invalid' => 0];
        if (!$this->schemaReady($pdo)) {
            return $stats;
        }
        $stats['seeded'] = $this->seedCatalog($pdo);
        $audit = $this->auditExistingGrades($pdo, $repairCertain);
        $stats['audited'] = $audit['audited'];
        $stats['repaired'] = $audit['repaired'];
        $stats['invalid'] = $audit['invalid'];

        return $stats;
    }

    public function seedCatalog(PDO $pdo): int
    {
        $added = 0;
        $exists = $pdo->prepare(
            'SELECT id FROM rank_catalog WHERE country_code = ? AND branch = ? AND canonical_name = ? LIMIT 1'
        );
        $ins = $pdo->prepare(
            'INSERT INTO rank_catalog
                (country_code, branch, canonical_name, short_name, category, nato_code, us_equivalent,
                 hierarchy_order, is_officer, is_nco, is_enlisted, is_active, verification_status,
                 reference_source, reference_version, verified_at, verified_by, legacy_grade_code, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );

        foreach (RankReferenceValidator::expectedFrArmy() as $name => $meta) {
            $exists->execute(['FR', 'ARMY', $name]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $cat = $meta['category'];
            $nato = $meta['nato_code'];
            $us = $this->usPeerForNato($nato);
            $ins->execute([
                'FR',
                'ARMY',
                $name,
                $meta['short_name'],
                $cat,
                $nato,
                $us,
                $meta['hierarchy_order'],
                in_array($cat, ['OFFICER', 'GENERAL_OFFICER'], true) ? 1 : 0,
                in_array($cat, ['NCO', 'SENIOR_NCO'], true) ? 1 : 0,
                $cat === 'ENLISTED' ? 1 : 0,
                1,
                'VERIFIED',
                'NATO STANAG / French Army customary OF/OR table',
                '2026-08-29',
                date('Y-m-d H:i:s'),
                'system',
                $this->legacyCodeFor($name),
            ]);
            ++$added;
        }

        /* Gendarmerie — OTAN nul si non certain (ne pas inventer). */
        $gendarmerie = [
            ['Gendarme', 'Gend.', 'ENLISTED', null, 20],
            ['Maréchal des logis-chef', 'MDC', 'NCO', null, 55],
            ['Adjudant', 'Adj', 'NCO', null, 70],
            ['Adjudant-chef', 'Adc', 'SENIOR_NCO', null, 80],
            ['Major', 'Maj', 'SENIOR_NCO', null, 90],
            ['Aspirant', 'Asp', 'OFFICER', null, 105],
            ['Sous-lieutenant', 'Slt', 'OFFICER', null, 110],
            ['Lieutenant', 'Lt', 'OFFICER', null, 120],
            ['Capitaine', 'Cne', 'OFFICER', null, 130],
            ['Chef d’escadron', 'Cen', 'OFFICER', null, 140],
            ['Lieutenant-colonel', 'Lcl', 'OFFICER', null, 150],
            ['Colonel', 'Col', 'OFFICER', null, 160],
            ['Général de brigade', 'Gén. bde', 'GENERAL_OFFICER', null, 170],
            ['Général de division', 'Gén. div.', 'GENERAL_OFFICER', null, 180],
        ];
        foreach ($gendarmerie as $row) {
            [$name, $short, $cat, $nato, $order] = $row;
            $exists->execute(['FR', 'GENDARMERIE', $name]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $ins->execute([
                'FR',
                'GENDARMERIE',
                $name,
                $short,
                $cat,
                $nato,
                null,
                $order,
                in_array($cat, ['OFFICER', 'GENERAL_OFFICER'], true) ? 1 : 0,
                in_array($cat, ['NCO', 'SENIOR_NCO'], true) ? 1 : 0,
                $cat === 'ENLISTED' ? 1 : 0,
                1,
                'UNVERIFIED',
                'Pending official Gendarmerie OF/OR validation',
                '2026-08-29',
                null,
                null,
                null,
            ]);
            ++$added;
        }

        return $added;
    }

    /**
     * @return array{audited: int, repaired: int, invalid: int, rows: list<array<string, mixed>>}
     */
    public function auditExistingGrades(PDO $pdo, bool $repairCertain = false): array
    {
        $out = ['audited' => 0, 'repaired' => 0, 'invalid' => 0, 'rows' => []];
        $gradeTable = $this->resolveGradeTable($pdo);
        if ($gradeTable === null) {
            return $out;
        }
        $sysId = $this->frClassicSystemId($pdo);
        if ($sysId === null) {
            return $out;
        }
        $st = $pdo->prepare(
            "SELECT g.id, g.code, g.label_short, g.label_long, g.label_otan, g.sort_order
             FROM `{$gradeTable}` g
             WHERE g.grade_system_id = ?"
        );
        $st->execute([$sysId]);
        $log = $pdo->prepare(
            'INSERT INTO rank_migration_audit
                (old_rank_id, old_name, old_nato_code, new_rank_id, new_nato_code, migration_status, reason, created_at)
             VALUES (?,?,?,?,?,?,?,NOW())'
        );
        $updNato = null;
        if ($repairCertain) {
            $updNato = $pdo->prepare(
                "UPDATE `{$gradeTable}` SET label_otan = ?, otan_verification_status = 'VERIFIED', updated_at = NOW() WHERE id = ?"
            );
        }
        $updStatus = $pdo->prepare(
            "UPDATE `{$gradeTable}` SET otan_verification_status = ? WHERE id = ?"
        );
        $hasStatus = $this->hasColumn($pdo, $gradeTable, 'otan_verification_status');

        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            ++$out['audited'];
            $name = (string) ($row['label_long'] ?? $row['label_short'] ?? $row['code'] ?? '');
            $code = (string) ($row['code'] ?? '');
            $stored = isset($row['label_otan']) ? (string) $row['label_otan'] : null;
            $eval = $this->validator->evaluateFrArmyRow($name !== '' ? $name : $code, $stored);
            if ($eval['status'] === 'UNVERIFIED' && $code !== '') {
                $eval = $this->validator->evaluateFrArmyRow($code, $stored);
            }
            $item = [
                'old_rank_id' => (int) $row['id'],
                'old_name' => $name,
                'old_nato_code' => $stored,
                'expected_nato' => $eval['expected_nato'],
                'status' => $eval['status'],
                'message' => $eval['message'],
            ];
            $catalogId = $this->findCatalogId($pdo, $eval['canonical_name'] ?? null);
            if ($eval['status'] === 'INVALID' && $repairCertain && $updNato !== null && $eval['expected_nato'] !== null) {
                $updNato->execute([$eval['expected_nato'], (int) $row['id']]);
                $this->linkCatalog($pdo, $gradeTable, (int) $row['id'], $catalogId);
                $log->execute([
                    (int) $row['id'],
                    $name,
                    $stored,
                    $catalogId,
                    $eval['expected_nato'],
                    'REPAIRED',
                    $eval['message'],
                ]);
                ++$out['repaired'];
                $item['status'] = 'REPAIRED';
            } else {
                if ($hasStatus) {
                    $updStatus->execute([$eval['status'], (int) $row['id']]);
                }
                $this->linkCatalog($pdo, $gradeTable, (int) $row['id'], $catalogId);
                $log->execute([
                    (int) $row['id'],
                    $name,
                    $stored,
                    $catalogId,
                    $eval['expected_nato'],
                    $eval['status'] === 'VERIFIED' ? 'MAPPED' : ($eval['status'] === 'INVALID' ? 'INVALID' : 'UNVERIFIED'),
                    $eval['message'],
                ]);
                if ($eval['status'] === 'INVALID') {
                    ++$out['invalid'];
                }
            }
            $out['rows'][] = $item;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCatalog(?string $country = null, ?string $branch = null): array
    {
        $pdo = Database::getPdo();
        if (!$this->schemaReady($pdo)) {
            return [];
        }
        $sql = 'SELECT * FROM rank_catalog WHERE is_active = 1';
        $params = [];
        if ($country !== null && $country !== '') {
            $sql .= ' AND country_code = ?';
            $params[] = $country;
        }
        if ($branch !== null && $branch !== '') {
            $sql .= ' AND branch = ?';
            $params[] = $branch;
        }
        $sql .= ' ORDER BY country_code ASC, branch ASC, hierarchy_order ASC';
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function usPeerForNato(string $nato): ?string
    {
        /* Correspondances usuelles OTAN OF ↔ US O — explicites, pas dérivées de hierarchy_order. */
        return match (strtoupper($nato)) {
            'OF-1' => 'O-1/O-2',
            'OF-2' => 'O-3',
            'OF-3' => 'O-4',
            'OF-4' => 'O-5',
            'OF-5' => 'O-6',
            'OF-6' => 'O-7',
            'OF-7' => 'O-8',
            'OF-8' => 'O-9',
            'OF-9' => 'O-10',
            default => null,
        };
    }

    private function legacyCodeFor(string $name): ?string
    {
        return match ($name) {
            'Sous-lieutenant' => 'SL',
            'Lieutenant' => 'LT',
            'Capitaine' => 'CNE',
            'Commandant' => 'CDT',
            'Lieutenant-colonel' => 'LCL',
            'Colonel' => 'COL',
            'Général de brigade' => 'GBR',
            'Général de division' => 'GDV',
            'Général de corps d’armée' => 'GCA',
            'Général d’armée' => 'GAR',
            'Major' => 'MAJ',
            'Adjudant-chef' => 'ADC',
            'Adjudant' => 'ADJ',
            'Sergent-chef' => 'SCH',
            'Sergent' => 'SGT',
            'Caporal-chef' => 'CCH',
            'Caporal' => 'CPL',
            'Soldat de 1re classe' => 'SD1',
            'Soldat de 2e classe', 'Soldat' => 'SD2',
            default => null,
        };
    }

    private function findCatalogId(PDO $pdo, ?string $canonical): ?int
    {
        if ($canonical === null || $canonical === '') {
            return null;
        }
        $st = $pdo->prepare(
            "SELECT id FROM rank_catalog WHERE country_code = 'FR' AND branch = 'ARMY' AND canonical_name = ? LIMIT 1"
        );
        $st->execute([$canonical]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function linkCatalog(PDO $pdo, string $gradeTable, int $gradeId, ?int $catalogId): void
    {
        if ($catalogId === null || !$this->hasColumn($pdo, $gradeTable, 'rank_catalog_id')) {
            return;
        }
        try {
            $st = $pdo->prepare("UPDATE `{$gradeTable}` SET rank_catalog_id = ? WHERE id = ?");
            $st->execute([$catalogId, $gradeId]);
        } catch (\Throwable) {
        }
    }

    private function frClassicSystemId(PDO $pdo): ?int
    {
        try {
            $st = $pdo->query("SELECT id FROM grade_systems WHERE code = 'FR_CLASSIC' LIMIT 1");
            $id = $st ? $st->fetchColumn() : false;

            return $id !== false ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveGradeTable(PDO $pdo): ?string
    {
        foreach (['grades', 'grades_referentiel'] as $t) {
            if ($this->hasColumn($pdo, $t, 'grade_system_id')) {
                return $t;
            }
        }

        return null;
    }

    private function schemaReady(PDO $pdo): bool
    {
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rank_catalog' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, $column]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
