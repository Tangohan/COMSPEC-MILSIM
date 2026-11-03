<?php

declare(strict_types=1);

/**
 * Étape « Académie : copies des cours intégrés figées sur une ancienne version (JTAC 1.0 à un seul chapitre) ».
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Les stagiaires inscrits au JTAC 1.0 gardaient
 * une copie du cours à un seul chapitre ; les étapes 3280 et 3540 ne rafraîchissaient que les inscriptions non
 * commencées. Ici, toute inscription non terminée à un cours intégré reçoit la version courante via
 * AcademyBuiltInCourses::refreshSnapshot() : chapitre, étape, tentatives, débriefings et validations sont conservés.
 * Les parcours terminés et les licences restent intacts. Idempotente : une copie à jour n’est pas réécrite.
 */

$academySnapSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$academySnapSchema->tableExists('academy_learners')) {
    echo "  [OK] Pas encore d’inscrits à l’Académie : rien à mettre à jour.\n";
} else {
    $academySnapKeys = array_keys(\App\Services\Academy\AcademyBuiltInCourses::all());
    $academySnapSelect = $pdo->prepare('SELECT tenant_id, user_id, course_key, state_json FROM academy_learners WHERE course_key IN (' . implode(',', array_fill(0, count($academySnapKeys), '?')) . ')');
    $academySnapSelect->execute($academySnapKeys);
    $academySnapUpdate = $pdo->prepare('UPDATE academy_learners SET state_json = ? WHERE tenant_id = ? AND user_id = ? AND course_key = ?');
    $academySnapRefreshed = 0;
    foreach ($academySnapSelect->fetchAll(\PDO::FETCH_ASSOC) as $academySnapRow) {
        $academySnapState = json_decode((string) $academySnapRow['state_json'], true);
        if (!is_array($academySnapState)) {
            continue;
        }
        $academySnapNew = \App\Services\Academy\AcademyBuiltInCourses::refreshSnapshot($academySnapState, (string) $academySnapRow['course_key']);
        if ($academySnapNew === $academySnapState) {
            continue;
        }
        $academySnapUpdate->execute([
            json_encode($academySnapNew, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $academySnapRow['tenant_id'], $academySnapRow['user_id'], $academySnapRow['course_key'],
        ]);
        ++$academySnapRefreshed;
    }
    echo "  [OK] Cours intégrés : {$academySnapRefreshed} copie(s) de stagiaire mise(s) à la version courante (progression conservée).\n";
}
