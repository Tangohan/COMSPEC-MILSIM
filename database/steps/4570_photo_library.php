<?php

declare(strict_types=1);

/**
 * Étape « Photothèque : visages étiquetés à la main, masques, commentaires, étiquettes, versements SSE » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle
 * manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_15_080_photo_library.sql) et la colonne
 * recon_images.author_user_id (compte de l'auteur de la photo, retrouvé à la réception) n'est ajoutée que si elle manque
 * et si recon_images existe. Aucune photo ni donnée n'est modifiée. Retour arrière : migrations/rollback/photo_library_down.sql.
 */

$photoLibSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$photoLibTables = ['photo_library_tags', 'photo_library_masks', 'photo_library_comments', 'photo_library_labels', 'photo_library_state', 'photo_library_sse'];
$photoLibMissing = array_values(array_filter($photoLibTables, static fn (string $t): bool => !$photoLibSchema->tableExists($t)));
if ($photoLibMissing === []) {
    echo "  [OK] Tables de la photothèque déjà présentes.\n";
} else {
    $photoLibSchema->runSqlFile($root . '/migrations/2026_11_15_080_photo_library.sql');
    foreach ($photoLibTables as $photoLibTable) {
        if (!$photoLibSchema->tableExists($photoLibTable)) {
            throw new RuntimeException('Création de la table ' . $photoLibTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $photoLibMissing) . ".\n";
}

if ($photoLibSchema->tableExists('recon_images')) {
    if ($photoLibSchema->addColumnIfMissing('recon_images', 'author_user_id', 'INT UNSIGNED DEFAULT NULL')) {
        echo "  [OK] Colonne recon_images.author_user_id ajoutée.\n";
    } else {
        echo "  [OK] Colonne recon_images.author_user_id déjà présente.\n";
    }
}
