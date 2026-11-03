<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * COMSPEC IDENTITY (Lot 2) — contrôles reliés aux fiches FRM, page « Contrôle ID » du vrai téléphone, tableau de bord
 * de l'opération.
 *
 * - sse_id_checks.field_note_id : fiche FRM (sse_field_notes) née du contrôle : brouillon « à valider » créé depuis le
 *   téléphone (/m/controle) ou fiche FRS envoyée par l'ATAK avec id_check_ref. Ses pièces jointes sont les photos du
 *   contrôle (dossier RENS de la personne, page mobile).
 * - index : historique du contrôleur (Steam ID, carte), tableau de bord par carte et opération, fiche liée.
 *
 * Idempotente (colonne et index ajoutés seulement s'ils manquent) ; MySQL et sqlite (tests). Suppose le registre
 * (atak_sse_identity_migration.php) déjà en place : sinon ne fait rien.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

    if (!$schema->tableExists('sse_id_checks')) {
        $log("  [ATTENTION] sse_id_checks absente — lot 2 du contrôle d'identité reporté\n");

        return;
    }
    if ($schema->addColumnIfMissing('sse_id_checks', 'field_note_id', $sqlite ? 'INT DEFAULT NULL' : 'INT UNSIGNED DEFAULT NULL')) {
        $log("  [OK] sse_id_checks.field_note_id\n");
    }
    $indexes = [
        'idx_sse_id_checks_controller' => ['tenant_id', 'controller_steam_id', 'context_id'],
        'idx_sse_id_checks_op' => ['tenant_id', 'context_id', 'op_key'],
        'idx_sse_id_checks_note' => ['tenant_id', 'field_note_id'],
    ];
    foreach ($indexes as $name => $cols) {
        if ($schema->addIndexIfMissing('sse_id_checks', $name, $cols)) {
            $log("  [OK] index {$name}\n");
        }
    }
};
