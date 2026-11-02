<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Support\Migrations\SchemaInspector;
use PDO;

/**
 * Reprise des qualifications Academy : jusqu'ici une attestation de formation créait une ligne de qualification
 * sans fiche du référentiel (personnel_qualifications.source = 'training', definition_id vide). Pour chaque formation
 * concernée, une qualification « Academy » est créée (code ACAD-<formation>, réutilisée si elle existe), réglée en
 * « obtenue par cette formation », et les attestations y sont rattachées. Rien n'est supprimé ni modifié d'autre.
 *
 * Idempotente : seules les attestations encore sans qualification sont reprises ; une relance ne trouve plus rien.
 * Utilisée par l'étape de migration 3435 et par le bouton « Rattacher les attestations Academy » des effectifs.
 */
final class AcademyQualificationImporter
{
    public const CATEGORY_NAME = 'Academy';

    public function __construct(private PDO $pdo)
    {
    }

    public function ready(): bool
    {
        $s = new SchemaInspector($this->pdo);

        return $s->tableExists('personnel_qualifications')
            && $s->tableExists('personnel_qualification_definitions')
            && $s->tableExists('qualification_award_rules')
            && $s->columnExists('personnel_qualifications', 'training_course_id')
            && $s->columnExists('personnel_qualifications', 'definition_id')
            && $s->columnExists('personnel_qualifications', 'source');
    }

    public static function code(int $courseId): string
    {
        return 'ACAD-' . $courseId;
    }

    /** @return array<string, mixed> règle « réussir cette formation » */
    public static function rule(int $courseId): array
    {
        return ['op' => 'all', 'groups' => [['op' => 'all', 'items' => [['type' => 'academy', 'course_id' => $courseId]]]]];
    }

    /** @return array{created: int, reused: int, linked: int, log: list<string>} */
    public function run(?int $tenantId = null): array
    {
        $out = ['created' => 0, 'reused' => 0, 'linked' => 0, 'log' => []];
        if (!$this->ready()) {
            return $out;
        }
        $schema = new SchemaInspector($this->pdo);
        $hasCategories = $schema->tableExists('qualification_categories') && $schema->columnExists('personnel_qualification_definitions', 'category_id');
        $hasCourses = $schema->tableExists('training_courses');
        $now = date('Y-m-d H:i:s');

        $sql = "SELECT tenant_id, training_course_id, MAX(qualification_name) AS name, COUNT(*) AS n
                FROM personnel_qualifications
                WHERE source = 'training' AND definition_id IS NULL AND training_course_id IS NOT NULL AND tenant_id IS NOT NULL";
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $st = $this->pdo->prepare($sql . ' GROUP BY tenant_id, training_course_id ORDER BY tenant_id, training_course_id');
        $st->execute($params);
        $groups = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $categoryIds = [];
        foreach ($groups as $g) {
            $tid = (int) $g['tenant_id'];
            $courseId = (int) $g['training_course_id'];
            if ($tid < 1 || $courseId < 1) {
                continue;
            }
            $code = self::code($courseId);
            $find = $this->pdo->prepare('SELECT id FROM personnel_qualification_definitions WHERE tenant_id = ? AND code = ? LIMIT 1');
            $find->execute([$tid, $code]);
            $defId = (int) ($find->fetchColumn() ?: 0);
            if ($defId > 0) {
                $out['reused']++;
            } else {
                $title = trim((string) ($g['name'] ?? ''));
                if ($hasCourses) {
                    $c = $this->pdo->prepare('SELECT title FROM training_courses WHERE id = ? LIMIT 1');
                    $c->execute([$courseId]);
                    $title = trim((string) ($c->fetchColumn() ?: '')) ?: $title;
                }
                $title = mb_substr($title !== '' ? $title : 'Formation #' . $courseId, 0, 150);
                $cols = ['tenant_id', 'code', 'name', 'description', 'is_active', 'created_at', 'updated_at'];
                $vals = [$tid, $code, $title, 'Obtenue en réussissant la formation Academy « ' . $title . ' » : l’attestation délivrée crée la qualification.', 1, $now, $now];
                if ($hasCategories) {
                    $cols[] = 'category_id';
                    $vals[] = $categoryIds[$tid] ??= $this->academyCategory($tid, $now);
                }
                $ins = $this->pdo->prepare('INSERT INTO personnel_qualification_definitions (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');
                $ins->execute($vals);
                $defId = (int) $this->pdo->lastInsertId();
                $out['created']++;
                $out['log'][] = 'Communauté #' . $tid . ' : qualification « ' . $title . ' » (' . $code . ') créée';
            }
            $has = $this->pdo->prepare('SELECT 1 FROM qualification_award_rules WHERE definition_id = ? LIMIT 1');
            $has->execute([$defId]);
            if (!$has->fetchColumn()) {
                $this->pdo->prepare(
                    "INSERT INTO qualification_award_rules (tenant_id, definition_id, allow_manual, auto_enabled, auto_mode, rule_json, show_on_profile, show_on_orbat, created_at, updated_at)
                     VALUES (?, ?, 1, 1, 'direct', ?, 1, 1, ?, ?)"
                )->execute([$tid, $defId, json_encode(self::rule($courseId), JSON_UNESCAPED_UNICODE), $now, $now]);
            }
            $up = $this->pdo->prepare(
                "UPDATE personnel_qualifications SET definition_id = ?
                 WHERE tenant_id = ? AND source = 'training' AND definition_id IS NULL AND training_course_id = ?"
            );
            $up->execute([$defId, $tid, $courseId]);
            $n = $up->rowCount();
            $out['linked'] += $n;
            $out['log'][] = 'Communauté #' . $tid . ' : ' . $n . ' attestation(s) rattachée(s) à ' . $code;
        }

        return $out;
    }

    private function academyCategory(int $tenantId, string $now): ?int
    {
        $st = $this->pdo->prepare('SELECT id FROM qualification_categories WHERE tenant_id = ? AND name = ? LIMIT 1');
        $st->execute([$tenantId, self::CATEGORY_NAME]);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        $this->pdo->prepare('INSERT INTO qualification_categories (tenant_id, name, sort_order, created_at, updated_at) VALUES (?, ?, 0, ?, ?)')
            ->execute([$tenantId, self::CATEGORY_NAME, $now, $now]);

        return (int) $this->pdo->lastInsertId() ?: null;
    }
}
