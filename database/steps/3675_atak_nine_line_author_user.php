<?php

declare(strict_types=1);

/**
 * Étape « Page 9-LINE : auteur des demandes rédigées sur le site » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : ajoute atak_nine_line.author_user_id
 * (membre qui a rédigé le 9-LINE depuis la page /9-line ; vide pour ceux transmis par le jeu) et son index
 * (tenant_id, author_user_id) s’ils manquent. Sert aux droits de suppression : l’auteur ou un responsable.
 */

$nineLineSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$nineLineSchema->tableExists('atak_nine_line')) {
    echo "  [INFO] Table atak_nine_line absente : rien à faire.\n";
} else {
    if ($nineLineSchema->addColumnIfMissing('atak_nine_line', 'author_user_id', 'INT UNSIGNED NULL DEFAULT NULL AFTER author')) {
        echo "  [OK] Colonne atak_nine_line.author_user_id ajoutée.\n";
    } else {
        echo "  [OK] Colonne atak_nine_line.author_user_id déjà présente.\n";
    }
    if ($nineLineSchema->addIndexIfMissing('atak_nine_line', 'idx_atak_nine_line_author_user', ['tenant_id', 'author_user_id'])) {
        echo "  [OK] Index idx_atak_nine_line_author_user créé.\n";
    }
}
