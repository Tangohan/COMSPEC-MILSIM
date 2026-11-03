<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * COMSPEC IDENTITY (Lots 4 et 5) — BOLO et HVT nés en jeu, remontés sur Athena.
 *
 * - sse_watchlist_entries : origine (source = game pour un avis né en jeu), référence stable du jeu (game_ref, pour
 *   mettre l'avis à jour : FIX, capture, levée), carte, clé biométrique (visage reconnu en jeu), papiers connus et
 *   étape de la chasse (game_status : active, find, fix, captured, ekia, removed). Niveau « hvt » accepté.
 * - index (tenant_id, game_ref) : retrouver l'avis d'un BOLO ou d'une HVT envoyé plusieurs fois.
 *
 * Idempotente (colonnes et index ajoutés seulement s'ils manquent) ; MySQL et sqlite (tests). Sans la table des listes
 * de surveillance (registre SSE pas encore migré) : ne fait rien.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $u = static fn (string $def): string => $sqlite ? str_replace(' UNSIGNED', '', $def) : $def;

    if (!$schema->tableExists('sse_watchlist_entries')) {
        $log("  [ATTENTION] sse_watchlist_entries absente — BOLO et HVT du jeu reportés\n");

        return;
    }
    $columns = [
        'source' => 'VARCHAR(16) DEFAULT NULL',
        'game_ref' => 'VARCHAR(80) DEFAULT NULL',
        'context_id' => 'INT UNSIGNED DEFAULT NULL',
        'bio_key' => 'VARCHAR(64) DEFAULT NULL',
        'known_docs' => 'VARCHAR(500) DEFAULT NULL',
        'game_status' => 'VARCHAR(16) DEFAULT NULL',
    ];
    foreach ($columns as $col => $def) {
        if ($schema->addColumnIfMissing('sse_watchlist_entries', $col, $u($def))) {
            $log("  [OK] sse_watchlist_entries.{$col}\n");
        }
    }
    if ($schema->addIndexIfMissing('sse_watchlist_entries', 'idx_sse_watchlist_game_ref', ['tenant_id', 'game_ref'])) {
        $log("  [OK] index idx_sse_watchlist_game_ref\n");
    }
};
