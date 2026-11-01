<?php

declare(strict_types=1);

/**
 * Étape « Académie : nouvelle version du cours JTAC pour les inscrits qui ne l’ont pas commencé ».
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Le cours intégré vit dans le code
 * (AcademyBuiltInCourses) : les nouvelles inscriptions reçoivent directement la version 2.0. Chaque inscrit
 * garde une copie privée du cours ; cette étape ne remplace que la copie des inscrits qui n’ont encore rien
 * fait (premier chapitre, première étape, aucun exercice, aucune tentative, aucun débriefing ni validation).
 * Les parcours entamés, terminés et les licences restent intacts. Idempotente : une copie déjà à jour n’est
 * pas réécrite, une relance ne change rien.
 */

$academyJtacSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$academyJtacSchema->tableExists('academy_learners')) {
    echo "  [OK] Pas encore d’inscrits à l’Académie : rien à mettre à jour.\n";
} else {
    $academyJtacKey = \App\Services\Academy\AcademyBuiltInCourses::JTAC_KEY;
    $academyJtacCourse = \App\Services\Academy\AcademyBuiltInCourses::find($academyJtacKey);
    $academyJtacSelect = $pdo->prepare('SELECT tenant_id, user_id, state_json FROM academy_learners WHERE course_key = ?');
    $academyJtacSelect->execute([$academyJtacKey]);
    $academyJtacUpdate = $pdo->prepare('UPDATE academy_learners SET state_json = ? WHERE tenant_id = ? AND user_id = ? AND course_key = ?');
    $academyJtacRefreshed = $academyJtacKept = 0;
    foreach ($academyJtacSelect->fetchAll(\PDO::FETCH_ASSOC) as $academyJtacRow) {
        $academyJtacState = json_decode((string) $academyJtacRow['state_json'], true);
        if (!is_array($academyJtacState) || ($academyJtacState['snapshot']['version'] ?? null) === $academyJtacCourse['version']) {
            continue;
        }
        $academyJtacUntouched = (int) ($academyJtacState['chapter'] ?? 0) === 0 && (int) ($academyJtacState['stage'] ?? 0) === 0
            && empty($academyJtacState['completed']) && empty($academyJtacState['practice']) && empty($academyJtacState['attempts'])
            && empty($academyJtacState['exam']) && empty($academyJtacState['debriefs']) && empty($academyJtacState['validations'])
            && empty($academyJtacState['license']);
        if (!$academyJtacUntouched) {
            ++$academyJtacKept;
            continue;
        }
        $academyJtacState['snapshot'] = $academyJtacCourse;
        $academyJtacUpdate->execute([
            json_encode($academyJtacState, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $academyJtacRow['tenant_id'], $academyJtacRow['user_id'], $academyJtacKey,
        ]);
        ++$academyJtacRefreshed;
    }
    echo "  [OK] Cours JTAC {$academyJtacCourse['version']} : {$academyJtacRefreshed} inscription(s) non commencée(s) mise(s) à jour, {$academyJtacKept} parcours en cours conservé(s) tel(s) quel(s).\n";
}
