<?php

declare(strict_types=1);

/**
 * Étape « Bilan 30 jours : clôture automatique (néant) validée par Athena » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la colonne auto_closed_at de
 * enlistment_retro_feedbacks (date du néant posé par le système Athena ; NULL pour tout bilan rédigé par un membre) n'est
 * ajoutée que si elle manque. Aucune donnée n'est modifiée : tous les bilans existants restent des bilans rédigés.
 * Tant que la colonne manque, la tâche « recruitment_retro_auto_close » n'écrit rien. Retour arrière :
 * migrations/rollback/enlistment_retro_auto_close_down.sql.
 */

$retroAutoSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$retroAutoSchema->tableExists('enlistment_retro_feedbacks')) {
    echo "  [OK] Table enlistment_retro_feedbacks absente : rien à faire (les bilans ne sont pas activés).\n";
} elseif ($retroAutoSchema->addColumnIfMissing('enlistment_retro_feedbacks', 'auto_closed_at', 'DATETIME DEFAULT NULL')) {
    echo "  [OK] Colonne enlistment_retro_feedbacks.auto_closed_at ajoutée.\n";
} else {
    echo "  [OK] Colonne enlistment_retro_feedbacks.auto_closed_at déjà présente.\n";
}
