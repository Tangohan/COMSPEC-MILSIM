<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\CustomGradeTemplates;
use PDO;
use RuntimeException;

/**
 * Grades sur mesure d’une communauté : ses systèmes (SCP, Star Wars…), leurs catégories et leurs grades.
 *
 * Tout vit dans les tables du référentiel (grade_systems, grade_categories, grades) avec owner_tenant_id :
 * users.grade_id pointe donc sur ces grades comme sur ceux du catalogue. Chaque requête vérifie que la ligne
 * appartient à la communauté.
 *
 * Ordre : plus sort_order est grand, plus le grade (ou la catégorie) est haut. L’avancement lit ainsi la
 * progression du bas vers le haut, l’écran l’affiche du haut vers le bas.
 */
class CustomGradeSystemRepository
{
    public const MAX_SYSTEMS = 10;
    public const MAX_GRADES = 120;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null, private ?string $gradeTable = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function ready(): bool
    {
        try {
            $this->pdo->query('SELECT owner_tenant_id FROM grade_systems LIMIT 1');
            $this->pdo->query('SELECT owner_tenant_id, grade_system_id FROM grade_categories LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function table(): string
    {
        if ($this->gradeTable === null) {
            try {
                $this->pdo->query('SELECT grade_system_id FROM grades LIMIT 1');
                $this->gradeTable = 'grades';
            } catch (\Throwable) {
                $this->gradeTable = 'grades_referentiel';
            }
        }

        return $this->gradeTable;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** @return list<array<string, mixed>> */
    public function listSystems(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT s.id, s.code, s.label,
                    (SELECT COUNT(*) FROM ' . $this->table() . ' g WHERE g.grade_system_id = s.id AND g.is_active = 1) AS grade_count
             FROM grade_systems s WHERE s.owner_tenant_id = ? ORDER BY s.id ASC'
        );
        $st->execute([$tenantId]);

        return array_map(static function (array $r): array {
            $r['id'] = (int) $r['id'];
            $r['grade_count'] = (int) $r['grade_count'];

            return $r;
        }, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function findSystem(int $tenantId, int $systemId): ?array
    {
        if ($tenantId < 1 || $systemId < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT id, code, label FROM grade_systems WHERE id = ? AND owner_tenant_id = ? LIMIT 1');
        $st->execute([$systemId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];

        return $row;
    }

    /** @return array<string, mixed>|null */
    public function findSystemByCode(int $tenantId, string $code): ?array
    {
        $code = trim($code);
        if ($tenantId < 1 || $code === '' || !$this->ready()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT id, code, label FROM grade_systems WHERE code = ? AND owner_tenant_id = ? LIMIT 1');
        $st->execute([$code, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];

        return $row;
    }

    /**
     * Crée un système, éventuellement rempli depuis un modèle (CustomGradeTemplates).
     */
    public function createSystem(int $tenantId, string $label, string $templateKey = 'vide'): int
    {
        $template = CustomGradeTemplates::find($templateKey) ?? CustomGradeTemplates::find('vide');
        $label = $this->clean($label, 100);
        if ($label === '') {
            $label = (string) ($template['system'] ?? 'Grades de la communauté');
        }
        if (count($this->listSystems($tenantId)) >= self::MAX_SYSTEMS) {
            throw new RuntimeException('Limite atteinte : ' . self::MAX_SYSTEMS . ' systèmes sur mesure par communauté.');
        }
        $code = $this->uniqueSystemCode($tenantId, $label);
        $now = $this->now();
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare(
                "INSERT INTO grade_systems (code, label, country_code, format_type, owner_tenant_id, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, 'classic', ?, 1, ?, ?)"
            );
            $st->execute([$code, $label, CustomGradeTemplates::COUNTRY, $tenantId, $now, $now]);
            $systemId = (int) $this->pdo->lastInsertId();
            $categories = $template['categories'] ?? [];
            $catCount = count($categories);
            foreach ($categories as $ci => $cat) {
                $catId = $this->insertCategory($tenantId, $systemId, (string) $cat['label'], ($catCount - $ci) * 10);
                $grades = $cat['grades'] ?? [];
                $n = count($grades);
                foreach ($grades as $gi => [$short, $long]) {
                    $this->insertGrade($systemId, $catId, (string) $long, (string) $short, ($n - $gi) * 10);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $systemId;
    }

    public function renameSystem(int $tenantId, int $systemId, string $label): bool
    {
        $label = $this->clean($label, 100);
        if ($label === '' || $this->findSystem($tenantId, $systemId) === null) {
            return false;
        }
        $st = $this->pdo->prepare('UPDATE grade_systems SET label = ?, updated_at = ? WHERE id = ? AND owner_tenant_id = ?');
        $st->execute([$label, $this->now(), $systemId, $tenantId]);

        return true;
    }

    /**
     * Supprime un système dont aucun grade n’est porté par un membre.
     *
     * @return int nombre de membres qui portent encore un grade du système (0 = supprimé)
     */
    public function deleteSystem(int $tenantId, int $systemId): int
    {
        if ($this->findSystem($tenantId, $systemId) === null) {
            return 0;
        }
        $holders = $this->holdersInSystem($systemId);
        if ($holders > 0) {
            return $holders;
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM ' . $this->table() . ' WHERE grade_system_id = ?')->execute([$systemId]);
            $this->pdo->prepare('DELETE FROM grade_categories WHERE grade_system_id = ? AND owner_tenant_id = ?')->execute([$systemId, $tenantId]);
            $this->pdo->prepare('DELETE FROM grade_systems WHERE id = ? AND owner_tenant_id = ?')->execute([$systemId, $tenantId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return 0;
    }

    /**
     * Catégories du système, de la plus haute à la plus basse, avec leurs grades (du plus haut au plus bas)
     * et le nombre de membres qui portent chaque grade.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(int $tenantId, int $systemId): array
    {
        if ($this->findSystem($tenantId, $systemId) === null) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT id, code, label, sort_order FROM grade_categories WHERE grade_system_id = ? AND owner_tenant_id = ? ORDER BY sort_order DESC, id ASC'
        );
        $st->execute([$systemId, $tenantId]);
        $categories = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $c) {
            $c['id'] = (int) $c['id'];
            $c['grades'] = [];
            $c['retired'] = [];
            $categories[$c['id']] = $c;
        }
        $st = $this->pdo->prepare(
            'SELECT id, grade_category_id, code, label_short, label_long, sort_order, is_active FROM ' . $this->table() . '
             WHERE grade_system_id = ? ORDER BY sort_order DESC, id ASC'
        );
        $st->execute([$systemId]);
        $grades = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $holders = $this->holdersByGrade(array_map(static fn (array $g): int => (int) $g['id'], $grades));
        foreach ($grades as $g) {
            $g['id'] = (int) $g['id'];
            $g['is_active'] = (int) $g['is_active'];
            $g['holders'] = $holders[$g['id']] ?? 0;
            $cid = (int) $g['grade_category_id'];
            if (!isset($categories[$cid])) {
                continue;
            }
            $categories[$cid][$g['is_active'] === 1 ? 'grades' : 'retired'][] = $g;
        }

        return array_values($categories);
    }

    public function createCategory(int $tenantId, int $systemId, string $label): int
    {
        $label = $this->clean($label, 100);
        if ($label === '' || $this->findSystem($tenantId, $systemId) === null) {
            return 0;
        }
        // Nouvelle catégorie en bas de l’échelle.
        $st = $this->pdo->prepare('SELECT MIN(sort_order) FROM grade_categories WHERE grade_system_id = ? AND owner_tenant_id = ?');
        $st->execute([$systemId, $tenantId]);
        $min = $st->fetchColumn();
        $order = $min === null || $min === false ? 10 : (int) $min - 10;

        return $this->insertCategory($tenantId, $systemId, $label, $order);
    }

    public function renameCategory(int $tenantId, int $categoryId, string $label): bool
    {
        $label = $this->clean($label, 100);
        if ($label === '' || $this->findCategory($tenantId, $categoryId) === null) {
            return false;
        }
        $st = $this->pdo->prepare('UPDATE grade_categories SET label = ?, updated_at = ? WHERE id = ? AND owner_tenant_id = ?');
        $st->execute([$label, $this->now(), $categoryId, $tenantId]);

        return true;
    }

    /**
     * Supprime une catégorie et ses grades, si aucun membre ne porte l’un d’eux.
     *
     * @return int membres qui portent encore un grade de la catégorie (0 = supprimée)
     */
    public function deleteCategory(int $tenantId, int $categoryId): int
    {
        if ($this->findCategory($tenantId, $categoryId) === null) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT id FROM ' . $this->table() . ' WHERE grade_category_id = ?');
        $st->execute([$categoryId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $holders = array_sum($this->holdersByGrade($ids));
        if ($holders > 0) {
            return $holders;
        }
        $this->pdo->prepare('DELETE FROM ' . $this->table() . ' WHERE grade_category_id = ?')->execute([$categoryId]);
        $this->pdo->prepare('DELETE FROM grade_categories WHERE id = ? AND owner_tenant_id = ?')->execute([$categoryId, $tenantId]);

        return 0;
    }

    /** Monte (+1) ou descend (-1) une catégorie dans l’échelle. */
    public function moveCategory(int $tenantId, int $categoryId, int $direction): bool
    {
        $cat = $this->findCategory($tenantId, $categoryId);
        if ($cat === null) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT id FROM grade_categories WHERE grade_system_id = ? AND owner_tenant_id = ? ORDER BY sort_order DESC, id ASC');
        $st->execute([(int) $cat['grade_system_id'], $tenantId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $ids = $this->swap($ids, $categoryId, $direction);
        if ($ids === null) {
            return false;
        }
        $up = $this->pdo->prepare('UPDATE grade_categories SET sort_order = ?, updated_at = ? WHERE id = ? AND owner_tenant_id = ?');
        $n = count($ids);
        foreach ($ids as $i => $id) {
            $up->execute([($n - $i) * 10, $this->now(), $id, $tenantId]);
        }

        return true;
    }

    /** Ajoute un grade en bas de sa catégorie. */
    public function createGrade(int $tenantId, int $categoryId, string $labelLong, string $labelShort): int
    {
        $cat = $this->findCategory($tenantId, $categoryId);
        $labelLong = $this->clean($labelLong, 150);
        $labelShort = $this->clean($labelShort, 100);
        if ($cat === null || $labelLong === '') {
            return 0;
        }
        $systemId = (int) $cat['grade_system_id'];
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $this->table() . ' WHERE grade_system_id = ?');
        $st->execute([$systemId]);
        if ((int) $st->fetchColumn() >= self::MAX_GRADES) {
            throw new RuntimeException('Limite atteinte : ' . self::MAX_GRADES . ' grades par système.');
        }
        $st = $this->pdo->prepare('SELECT MIN(sort_order) FROM ' . $this->table() . ' WHERE grade_category_id = ?');
        $st->execute([$categoryId]);
        $min = $st->fetchColumn();
        $order = $min === null || $min === false ? 10 : (int) $min - 10;

        return $this->insertGrade($systemId, $categoryId, $labelLong, $labelShort, $order);
    }

    public function updateGrade(int $tenantId, int $gradeId, string $labelLong, string $labelShort, int $categoryId = 0): bool
    {
        $grade = $this->findGrade($tenantId, $gradeId);
        $labelLong = $this->clean($labelLong, 150);
        $labelShort = $this->clean($labelShort, 100);
        if ($grade === null || $labelLong === '') {
            return false;
        }
        $targetCat = (int) $grade['grade_category_id'];
        $order = (int) $grade['sort_order'];
        if ($categoryId > 0 && $categoryId !== $targetCat) {
            $cat = $this->findCategory($tenantId, $categoryId);
            if ($cat === null || (int) $cat['grade_system_id'] !== (int) $grade['grade_system_id']) {
                return false;
            }
            $targetCat = $categoryId;
            $st = $this->pdo->prepare('SELECT MIN(sort_order) FROM ' . $this->table() . ' WHERE grade_category_id = ?');
            $st->execute([$categoryId]);
            $min = $st->fetchColumn();
            $order = $min === null || $min === false ? 10 : (int) $min - 10;
        }
        $st = $this->pdo->prepare(
            'UPDATE ' . $this->table() . ' SET label_long = ?, label_short = ?, grade_category_id = ?, sort_order = ?, updated_at = ? WHERE id = ?'
        );
        $st->execute([$labelLong, $labelShort !== '' ? $labelShort : $labelLong, $targetCat, $order, $this->now(), $gradeId]);

        return true;
    }

    /**
     * Supprime un grade. S’il est porté, il est seulement retiré des listes (les membres le gardent).
     *
     * @return array{deleted: bool, holders: int}
     */
    public function deleteGrade(int $tenantId, int $gradeId): array
    {
        if ($this->findGrade($tenantId, $gradeId) === null) {
            return ['deleted' => false, 'holders' => 0];
        }
        $holders = $this->holdersByGrade([$gradeId])[$gradeId] ?? 0;
        if ($holders > 0) {
            $this->setGradeActive($tenantId, $gradeId, false);

            return ['deleted' => false, 'holders' => $holders];
        }
        $this->pdo->prepare('DELETE FROM ' . $this->table() . ' WHERE id = ?')->execute([$gradeId]);

        return ['deleted' => true, 'holders' => 0];
    }

    public function setGradeActive(int $tenantId, int $gradeId, bool $active): bool
    {
        if ($this->findGrade($tenantId, $gradeId) === null) {
            return false;
        }
        $this->pdo->prepare('UPDATE ' . $this->table() . ' SET is_active = ?, updated_at = ? WHERE id = ?')
            ->execute([$active ? 1 : 0, $this->now(), $gradeId]);

        return true;
    }

    /** Monte (+1) ou descend (-1) un grade dans sa catégorie. */
    public function moveGrade(int $tenantId, int $gradeId, int $direction): bool
    {
        $grade = $this->findGrade($tenantId, $gradeId);
        if ($grade === null) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT id FROM ' . $this->table() . ' WHERE grade_category_id = ? ORDER BY sort_order DESC, id ASC');
        $st->execute([(int) $grade['grade_category_id']]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $ids = $this->swap($ids, $gradeId, $direction);
        if ($ids === null) {
            return false;
        }
        $up = $this->pdo->prepare('UPDATE ' . $this->table() . ' SET sort_order = ?, updated_at = ? WHERE id = ?');
        $n = count($ids);
        foreach ($ids as $i => $id) {
            $up->execute([($n - $i) * 10, $this->now(), $id]);
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    public function findCategory(int $tenantId, int $categoryId): ?array
    {
        if ($tenantId < 1 || $categoryId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT c.id, c.label, c.sort_order, c.grade_system_id FROM grade_categories c
             INNER JOIN grade_systems s ON s.id = c.grade_system_id AND s.owner_tenant_id = ?
             WHERE c.id = ? AND c.owner_tenant_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $categoryId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findGrade(int $tenantId, int $gradeId): ?array
    {
        if ($tenantId < 1 || $gradeId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT g.id, g.grade_system_id, g.grade_category_id, g.code, g.label_short, g.label_long, g.sort_order, g.is_active
             FROM ' . $this->table() . ' g INNER JOIN grade_systems s ON s.id = g.grade_system_id AND s.owner_tenant_id = ?
             WHERE g.id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $gradeId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Codes des grades sur mesure de la communauté (pour ne pas les compter comme grades d’avancement isolés). */
    public function gradeCodes(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->ready()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT g.code FROM ' . $this->table() . ' g INNER JOIN grade_systems s ON s.id = g.grade_system_id WHERE s.owner_tenant_id = ?'
        );
        $st->execute([$tenantId]);

        return array_values(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    private function insertCategory(int $tenantId, int $systemId, string $label, int $order): int
    {
        $now = $this->now();
        $code = 'T' . $tenantId . 'S' . $systemId . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $st = $this->pdo->prepare(
            'INSERT INTO grade_categories (code, label, sort_order, owner_tenant_id, grade_system_id, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
        );
        $st->execute([$code, $this->clean($label, 100), $order, $tenantId, $systemId, $now, $now]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertGrade(int $systemId, int $categoryId, string $labelLong, string $labelShort, int $order): int
    {
        $labelLong = $this->clean($labelLong, 150);
        $labelShort = $this->clean($labelShort, 100);
        if ($labelShort === '') {
            $labelShort = $labelLong;
        }
        $code = $this->uniqueGradeCode($systemId, $labelShort !== $labelLong ? $labelShort : $labelLong);
        $now = $this->now();
        $st = $this->pdo->prepare(
            'INSERT INTO ' . $this->table() . ' (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NULL, ?, 0, 1, ?, ?)'
        );
        $st->execute([$systemId, $categoryId, $code, $labelShort, $labelLong, $order, $now, $now]);

        return (int) $this->pdo->lastInsertId();
    }

    private function uniqueSystemCode(int $tenantId, string $label): string
    {
        $base = substr('T' . $tenantId . '_' . self::slug($label, 'GRADES'), 0, 44);
        $code = $base;
        $i = 2;
        $st = $this->pdo->prepare('SELECT 1 FROM grade_systems WHERE code = ? LIMIT 1');
        while (true) {
            $st->execute([$code]);
            if (!$st->fetchColumn()) {
                return $code;
            }
            $code = $base . '_' . $i++;
        }
    }

    private function uniqueGradeCode(int $systemId, string $label): string
    {
        $base = substr(self::slug($label, 'GRADE'), 0, 40);
        $code = $base;
        $i = 2;
        $st = $this->pdo->prepare('SELECT 1 FROM ' . $this->table() . ' WHERE grade_system_id = ? AND code = ? LIMIT 1');
        while (true) {
            $st->execute([$systemId, $code]);
            if (!$st->fetchColumn()) {
                return $code;
            }
            $code = $base . '_' . $i++;
        }
    }

    /** « Clone Sergeant » → CLONE_SERGEANT ; accents retirés. */
    public static function slug(string $text, string $fallback): string
    {
        $t = $text;
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
            if (is_string($conv)) {
                $t = $conv;
            }
        }
        $t = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $t));
        $t = trim($t, '_');

        return $t !== '' ? $t : $fallback;
    }

    /**
     * @param list<int> $ids
     * @return list<int>|null
     */
    private function swap(array $ids, int $id, int $direction): ?array
    {
        $pos = array_search($id, $ids, true);
        if ($pos === false) {
            return null;
        }
        // La liste va du plus haut au plus bas : monter = reculer d’une place.
        $target = $direction > 0 ? $pos - 1 : $pos + 1;
        if ($target < 0 || $target >= count($ids)) {
            return null;
        }
        [$ids[$pos], $ids[$target]] = [$ids[$target], $ids[$pos]];

        return $ids;
    }

    private function holdersInSystem(int $systemId): int
    {
        $st = $this->pdo->prepare('SELECT id FROM ' . $this->table() . ' WHERE grade_system_id = ?');
        $st->execute([$systemId]);

        return array_sum($this->holdersByGrade(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    /**
     * @param list<int> $gradeIds
     * @return array<int, int>
     */
    private function holdersByGrade(array $gradeIds): array
    {
        $gradeIds = array_values(array_filter(array_unique($gradeIds), static fn (int $id): bool => $id > 0));
        if ($gradeIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($gradeIds), '?'));
        try {
            $st = $this->pdo->prepare('SELECT grade_id, COUNT(*) AS n FROM users WHERE grade_id IN (' . $ph . ') GROUP BY grade_id');
            $st->execute($gradeIds);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['grade_id']] = (int) $r['n'];
        }

        return $out;
    }

    private function clean(string $value, int $max): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return mb_substr($value, 0, $max, 'UTF-8');
    }
}
