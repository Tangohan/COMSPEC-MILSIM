<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * COMSPEC IDENTITY — images des papiers : signature du titulaire (sse_identities.signature_path) et image de fond du
 * gabarit personnalisé d'un papier (sse_id_documents.background_path). La photo (photo_path) et le drapeau du pays
 * (flag_path) existent déjà.
 *
 * Idempotente (colonnes ajoutées seulement si elles manquent) ; MySQL et sqlite (tests). Suppose le registre déjà
 * passé : sinon ne fait rien.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    if (!$schema->tableExists('sse_identities') || !$schema->tableExists('sse_id_documents')) {
        $log("  [ATTENTION] registre d'identité SSE absent — images des papiers reportées\n");

        return;
    }
    foreach ([
        ['sse_identities', 'signature_path'],
        ['sse_id_documents', 'background_path'],
    ] as [$table, $col]) {
        if ($schema->addColumnIfMissing($table, $col, 'VARCHAR(255) DEFAULT NULL')) {
            $log("  [OK] {$table}.{$col}\n");
        }
    }
};
