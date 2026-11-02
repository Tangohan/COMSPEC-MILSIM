<?php

declare(strict_types=1);

/**
 * Étape « Académie : cours JTAC mis en forme (texte riche) pour les inscrits qui ne l’ont pas commencé ».
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. La version 2.1.0 du cours intégré
 * (AcademyBuiltInCourses) garde le même contenu que la 2.0.1 ; seuls les textes du cours et des démonstrations
 * sont balisés (titres, échanges radio, encadrés « Analyse » et « À retenir », voir AcademyRichText).
 * Chaque inscrit garde une copie privée du cours : seule la copie des inscrits qui n’ont encore rien fait est
 * remplacée (premier chapitre, première étape, aucun exercice, aucune tentative, aucun débriefing ni validation).
 * Les parcours entamés, terminés et les licences restent intacts. Idempotente : une copie déjà à jour n’est
 * pas réécrite, une relance ne change rien.
 */

$academyRichSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$academyRichSchema->tableExists('academy_learners')) {
    echo "  [OK] Pas encore d’inscrits à l’Académie : rien à mettre à jour.\n";
} else {
    $academyRichKey = \App\Services\Academy\AcademyBuiltInCourses::JTAC_KEY;
    $academyRichCourse = \App\Services\Academy\AcademyBuiltInCourses::find($academyRichKey);
    $academyRichSelect = $pdo->prepare('SELECT tenant_id, user_id, state_json FROM academy_learners WHERE course_key = ?');
    $academyRichSelect->execute([$academyRichKey]);
    $academyRichUpdate = $pdo->prepare('UPDATE academy_learners SET state_json = ? WHERE tenant_id = ? AND user_id = ? AND course_key = ?');
    $academyRichRefreshed = $academyRichKept = 0;
    foreach ($academyRichSelect->fetchAll(\PDO::FETCH_ASSOC) as $academyRichRow) {
        $academyRichState = json_decode((string) $academyRichRow['state_json'], true);
        if (!is_array($academyRichState) || ($academyRichState['snapshot']['version'] ?? null) === $academyRichCourse['version']) {
            continue;
        }
        $academyRichUntouched = (int) ($academyRichState['chapter'] ?? 0) === 0 && (int) ($academyRichState['stage'] ?? 0) === 0
            && empty($academyRichState['completed']) && empty($academyRichState['practice']) && empty($academyRichState['attempts'])
            && empty($academyRichState['exam']) && empty($academyRichState['debriefs']) && empty($academyRichState['validations'])
            && empty($academyRichState['license']);
        if (!$academyRichUntouched) {
            ++$academyRichKept;
            continue;
        }
        $academyRichState['snapshot'] = $academyRichCourse;
        $academyRichUpdate->execute([
            json_encode($academyRichState, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $academyRichRow['tenant_id'], $academyRichRow['user_id'], $academyRichKey,
        ]);
        ++$academyRichRefreshed;
    }
    echo "  [OK] Cours JTAC {$academyRichCourse['version']} (mise en forme) : {$academyRichRefreshed} inscription(s) non commencée(s) mise(s) à jour, {$academyRichKept} parcours en cours conservé(s) tel(s) quel(s).\n";
}
