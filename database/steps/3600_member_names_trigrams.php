<?php

declare(strict_types=1);

/**
 * Étape « Noms des membres : trigrammes et réglage d’affichage » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente :
 * - table member_trigrams créée si elle manque (un trigramme par membre et par communauté, index unique
 *   (tenant_id, trigram) : deux membres d’une même communauté n’ont jamais le même) ;
 * - remplissage initial : un trigramme pour chaque membre qui n’en a pas, calculé depuis le prénom et le nom
 *   du personnage (le plus ancien membre d’abord en cas de collision). Un trigramme existant n’est jamais remplacé ;
 * - réglage d’affichage « member_names » posé avec les valeurs par défaut sur les communautés qui n’en ont pas
 *   (« Prénom N. » dans les listes, « Prénom NOM » sur les fiches) : un réglage existant n’est jamais modifié ;
 * - variables de document {{user.trigram}}, {{user.short_name}}, {{superior.trigram}} ajoutées au catalogue si absentes.
 */

$memberNamesSchema = new \App\Support\Migrations\SchemaInspector($pdo);

if ($memberNamesSchema->tableExists('member_trigrams')) {
    echo "  [OK] Table member_trigrams déjà présente.\n";
} else {
    $memberNamesDdl = static fn (string $fk): string => "CREATE TABLE IF NOT EXISTS `member_trigrams` (
        `tenant_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `trigram` CHAR(3) NOT NULL,
        `source` VARCHAR(10) NOT NULL DEFAULT 'auto' COMMENT 'auto = calculé depuis le nom, manual = choisi à la main',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`tenant_id`, `user_id`),
        UNIQUE KEY `uniq_member_trigrams_tenant_trigram` (`tenant_id`, `trigram`),
        KEY `idx_member_trigrams_user` (`user_id`){$fk}
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    try {
        $pdo->exec($memberNamesDdl(",
        CONSTRAINT `fk_member_trigrams_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE"));
    } catch (\Throwable $e) {
        // Base ancienne aux types de clés différents : la table sans clé étrangère suffit.
        echo '  [INFO] Clé étrangère vers users non posée (' . $e->getMessage() . ").\n";
        $pdo->exec($memberNamesDdl(''));
    }
    echo "  [OK] Table member_trigrams créée.\n";
}

\App\Services\Personnel\MemberNameService::forgetSchema();
try {
    $memberNamesFill = (new \App\Services\Personnel\MemberNameService($pdo))->backfillAll();
    echo '  [OK] Trigrammes : ' . $memberNamesFill['assigned'] . ' attribué(s) sur ' . $memberNamesFill['tenants'] . ' communauté(s)'
        . ($memberNamesFill['failed'] > 0 ? ', ' . $memberNamesFill['failed'] . ' membre(s) sans nom exploitable laissé(s) sans trigramme' : '')
        . ".\n";
} catch (\Throwable $e) {
    // Remplissage facultatif : Organisation › Noms et trigrammes le relance (« Attribuer les trigrammes manquants »).
    echo '  [INFO] Remplissage des trigrammes reporté (' . $e->getMessage() . ").\n";
}

if ($memberNamesSchema->tableExists('tenants') && $memberNamesSchema->columnExists('tenants', 'settings')) {
    $memberNamesSeeded = 0;
    $memberNamesUpd = $pdo->prepare('UPDATE tenants SET settings = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id, settings FROM tenants')->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $memberNamesTenant) {
        $raw = $memberNamesTenant['settings'] ?? null;
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
        if (!is_array($decoded) || array_key_exists(\App\Services\Personnel\MemberNameSettings::KEY, $decoded)) {
            // JSON illisible (jamais écrasé) ou réglage déjà présent.
            continue;
        }
        $decoded[\App\Services\Personnel\MemberNameSettings::KEY] = \App\Services\Personnel\MemberNameSettings::DEFAULTS;
        $memberNamesUpd->execute([json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $memberNamesTenant['id']]);
        $memberNamesSeeded++;
    }
    echo '  [OK] Réglage d’affichage des noms posé sur ' . $memberNamesSeeded . " communauté(s).\n";
}

if ($memberNamesSchema->tableExists('document_variables_catalog')) {
    $memberNamesVars = [
        ['user.short_name', 'Nom abrégé', 'user', 'Prénom et initiale du nom (Jean D.)', 'user'],
        ['user.trigram', 'Trigramme (paraphe)', 'user', 'Trigramme du membre, pour les paraphes et visas', 'user'],
        ['superior.trigram', 'Trigramme du supérieur', 'hierarchy', 'Trigramme du supérieur hiérarchique', 'hierarchy'],
    ];
    $memberNamesHas = $pdo->prepare('SELECT 1 FROM document_variables_catalog WHERE code = ? AND tenant_id IS NULL LIMIT 1');
    $memberNamesIns = $pdo->prepare('INSERT INTO document_variables_catalog (tenant_id, code, label, source_type, description, category, is_active) VALUES (NULL, ?, ?, ?, ?, ?, 1)');
    $memberNamesAdded = 0;
    foreach ($memberNamesVars as $v) {
        $memberNamesHas->execute([$v[0]]);
        if ($memberNamesHas->fetchColumn() === false) {
            $memberNamesIns->execute($v);
            $memberNamesAdded++;
        }
    }
    echo '  [OK] Variables de document (trigramme, nom abrégé) : ' . $memberNamesAdded . " ajoutée(s).\n";
}
