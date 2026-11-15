<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Identité officielle d'un membre = son état civil (étape 4690). Trois colonnes, aucune donnée modifiée :
 *  - sse_identities.name_override : « nom volontairement différent » posé par un responsable ; l'alignement automatique, le
 *    bouton et l'outil en ligne de commande ne touchent plus à cette identité ;
 *  - sse_identities.civil_synced_at : dernier alignement sur l'état civil du membre ;
 *  - user_profiles.character_birth_date : date de naissance du PERSONNAGE (jamais la date de naissance réelle du joueur, qui
 *    reste dans user_profiles.birth_date / user_legal_identities). Générée une fois, de façon déterministe, puis gardée.
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests). Sans cette étape le site fonctionne en mode dégradé : le drapeau
 * « nom volontairement différent » n'est pas proposé et la date de naissance du personnage reste calculée (même résultat
 * à chaque fois) sans être enregistrée sur le profil.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $columns = [
        ['sse_identities', 'name_override', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['sse_identities', 'civil_synced_at', 'DATETIME DEFAULT NULL'],
        ['user_profiles', 'character_birth_date', 'DATE DEFAULT NULL'],
    ];
    foreach ($columns as [$table, $column, $definition]) {
        if (!$schema->tableExists($table)) {
            $log("  [OK] Table $table absente : la colonne $column sera ajoutée quand elle existera (rejouer l'étape).\n");
            continue;
        }
        $log($schema->addColumnIfMissing($table, $column, $definition)
            ? "  [OK] Colonne créée : $table.$column.\n"
            : "  [OK] Colonne $table.$column déjà présente.\n");
    }
};
