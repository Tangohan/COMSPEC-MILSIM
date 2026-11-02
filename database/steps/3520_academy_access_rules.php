<?php

declare(strict_types=1);

/**
 * Étape « Academy : droits d'accès (qui crée, qui voit, qui valide) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * - Table academy_access_rules : droits Academy de la communauté et public de chaque cours.
 * - Table academy_course_settings : auteur, public (communauté / restreint) et brouillon privé d’un cours.
 * Aucune ligne n’est créée : sans réglage, l’Academy garde son comportement d’origine.
 */

$academyAccessSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($academyAccessSchema->tableExists('academy_access_rules') && $academyAccessSchema->tableExists('academy_course_settings')) {
    echo "  [OK] Tables des droits d’accès de l’Academy déjà présentes.\n";
} else {
    $academyAccessSchema->runSqlFile($root . '/migrations/2026_11_02_090_academy_access_rules.sql');
    echo "  [OK] Tables academy_access_rules et academy_course_settings créées.\n";
}
