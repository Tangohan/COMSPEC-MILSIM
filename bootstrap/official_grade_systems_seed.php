<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;
use App\Support\OfficialGradeSystems;

/**
 * Pose dans le catalogue commun (grade_systems + grades) les systèmes officiels demandés et leurs grades
 * (OfficialGradeSystems::GRADES), sans toucher à l’existant. Retourne le nombre de systèmes et de grades ajoutés.
 *
 * @param list<string> $codes
 * @return array{systems: int, grades: int}
 */
function run_official_grade_systems_seed(PDO $pdo, array $codes): array
{
    $out = ['systems' => 0, 'grades' => 0];
    $schema = new SchemaInspector($pdo);
    if (!$schema->tableExists('grade_systems') || !$schema->tableExists('grade_categories')) {
        return $out;
    }
    $gradeTable = null;
    foreach (['grades', 'grades_referentiel'] as $t) {
        if ($schema->columnExists($t, 'grade_system_id')) {
            $gradeTable = $t;
            break;
        }
    }
    if ($gradeTable === null) {
        return $out;
    }
    $cat = [];
    foreach ($pdo->query('SELECT id, code FROM grade_categories')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cat[(string) $r['code']] = (int) $r['id'];
    }
    $findSystem = $pdo->prepare('SELECT id FROM grade_systems WHERE code = ? LIMIT 1');
    $insSystem = $pdo->prepare("INSERT INTO grade_systems (code, label, country_code, format_type, is_active, created_at, updated_at) VALUES (?, ?, ?, 'classic', 1, NOW(), NOW())");
    $exists = $pdo->prepare("SELECT 1 FROM `{$gradeTable}` WHERE grade_system_id = ? AND code = ? LIMIT 1");
    $insGrade = $pdo->prepare("INSERT INTO `{$gradeTable}` (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
    foreach ($codes as $code) {
        $grades = OfficialGradeSystems::GRADES[$code] ?? [];
        if ($grades === [] || !OfficialGradeSystems::has($code)) {
            continue;
        }
        $findSystem->execute([$code]);
        $systemId = (int) $findSystem->fetchColumn();
        if ($systemId < 1) {
            $insSystem->execute([$code, OfficialGradeSystems::label($code), OfficialGradeSystems::country($code)]);
            $systemId = (int) $pdo->lastInsertId();
            ++$out['systems'];
        }
        foreach ($grades as [$gCode, $short, $long, , $nato, $category, $order, $commissioned]) {
            if (!isset($cat[$category])) {
                continue;
            }
            $exists->execute([$systemId, $gCode]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $insGrade->execute([$systemId, $cat[$category], $gCode, $short, $long, $nato, $order, $commissioned]);
            ++$out['grades'];
        }
    }

    return $out;
}
