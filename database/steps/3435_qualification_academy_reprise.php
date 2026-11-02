<?php

declare(strict_types=1);

/**
 * Étape « Qualifications : reprise des qualifications Academy » (Données, aucun schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root, après 3430. Une attestation de formation créait
 * jusqu’ici une qualification sans fiche du référentiel (personnel_qualifications.source = 'training', definition_id
 * vide) : elle n’apparaissait ni dans le référentiel ni dans la matrice. Pour chaque formation concernée, une
 * qualification « Academy » (code ACAD-<formation>, réutilisée si elle existe) est créée, réglée en « obtenue par
 * cette formation », et ses attestations y sont rattachées (App\Services\Personnel\AcademyQualificationImporter).
 * Aucune ligne n’est supprimée ; les dates, statuts et brevets restent ceux de l’attestation.
 * Idempotente : seules les attestations encore sans qualification sont reprises ; une relance ne trouve plus rien.
 */

$academyQualificationImport = new \App\Services\Personnel\AcademyQualificationImporter($pdo);
if (!$academyQualificationImport->ready()) {
    echo "  [OK] Tables des qualifications absentes : rien à reprendre.\n";
} else {
    $academyQualificationResult = $academyQualificationImport->run();
    foreach ($academyQualificationResult['log'] as $academyQualificationLine) {
        echo '  [OK] ' . $academyQualificationLine . "\n";
    }
    echo '  [OK] Reprise Academy : ' . $academyQualificationResult['created'] . ' qualification(s) créée(s), '
        . $academyQualificationResult['reused'] . ' reprise(s), ' . $academyQualificationResult['linked'] . " attestation(s) rattachée(s).\n";
}
