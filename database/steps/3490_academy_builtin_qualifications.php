<?php

declare(strict_types=1);

/**
 * Étape « Academy : qualifications des cours intégrés » (Données, aucun schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root, après 3430 et 3470. Les cours intégrés de
 * l’Academy (JTAC et cours inspirés des standards OTAN publics) délivrent désormais une qualification Athena
 * Academy du référentiel (code ACAD-…, catégorie « Academy », émetteur « Athena Academy », mode « Formation
 * Academy »). Pour chaque communauté dont un membre a déjà terminé un de ces cours, la fiche est créée si son code
 * n’existe pas ; la règle automatique (toutes les heures, ou « Évaluer maintenant ») attribue ensuite la
 * qualification aux membres concernés. Aucune fiche existante n’est modifiée, rien n’est supprimé.
 * Idempotente : une relance retrouve les fiches par leur code et ne crée plus rien.
 */

$builtInQualifications = \App\Services\Academy\AcademyBuiltInQualifications::forPdo($pdo);
if (!$builtInQualifications->ready()) {
    echo "  [OK] Référentiel des qualifications ou Academy absents : rien à faire.\n";
} else {
    $builtInQualificationLog = $builtInQualifications->syncAll('fr');
    foreach ($builtInQualificationLog as $builtInQualificationLine) {
        echo '  [OK] ' . $builtInQualificationLine . "\n";
    }
    echo '  [OK] Qualifications des cours intégrés : ' . count($builtInQualificationLog) . " fiche(s) créée(s).\n";
}
