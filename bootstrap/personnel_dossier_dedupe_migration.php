<?php

declare(strict_types=1);

/**
 * Nettoie les fiches dossier en double (personnel_profiles, personnel_extras).
 *
 * La clé unique (user_id, tenant_id) ne bloquait pas les lignes sans communauté (tenant_id NULL) :
 * chaque ouverture de son propre dossier en recréait une, ce qui multipliait les membres dans
 * « Effectifs > À traiter ». Le code ne recrée plus ces lignes ; ici on supprime celles déjà créées.
 *
 * Seules les lignes vides sont supprimées (sans communauté, jamais modifiées, sans nom ni portrait) :
 * - si le membre a déjà une autre ligne, elles disparaissent toutes ;
 * - sinon on garde la plus ancienne, rattachée à la communauté du membre quand c'est possible.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $emptyChecks = [
        'personnel_profiles' => 'd.character_name IS NULL AND d.character_portrait_path IS NULL AND d.callsign IS NULL',
        'personnel_extras' => 'd.service_number IS NULL AND d.admin_notes IS NULL',
    ];
    foreach ($emptyChecks as $table => $emptyCheck) {
        if (!$hasColumn($table, 'tenant_id') || !$hasColumn($table, 'updated_at')) {
            continue;
        }
        // Membre qui a déjà une autre ligne : ses lignes vides sans communauté sont en trop.
        $deleted = $pdo->exec(
            "DELETE d FROM {$table} d
             INNER JOIN {$table} k ON k.user_id = d.user_id AND k.id <> d.id
                AND (k.tenant_id IS NOT NULL OR k.id < d.id)
             WHERE d.tenant_id IS NULL
               AND (d.updated_at IS NULL OR d.updated_at <=> d.created_at)
               AND {$emptyCheck}"
        );
        // Ligne restante sans communauté : on la rattache à celle du membre si elle est libre.
        $attached = $pdo->exec(
            "UPDATE {$table} p
             INNER JOIN users u ON u.id = p.user_id AND u.tenant_id IS NOT NULL
             LEFT JOIN {$table} t ON t.user_id = p.user_id AND t.tenant_id = u.tenant_id
             SET p.tenant_id = u.tenant_id
             WHERE p.tenant_id IS NULL AND t.id IS NULL"
        );
        echo sprintf("  %s : %d ligne(s) en double supprimée(s), %d rattachée(s) à leur communauté.\n", $table, (int) $deleted, (int) $attached);
    }
};
