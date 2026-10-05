<?php

declare(strict_types=1);

/**
 * Mon compte : « Déconnecter mes autres sessions ».
 * users.session_epoch = horodatage Unix ; toute session ouverte avant cette valeur est fermée
 * au prochain chargement de page (AuthMiddleware). Posé aussi au changement de mot de passe.
 * Relançable sans risque : n'ajoute la colonne que si elle manque.
 */
return static function (PDO $pdo): void {
    $st = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'session_epoch' LIMIT 1"
    );
    $st->execute();
    if ($st->fetchColumn()) {
        echo "  users.session_epoch déjà présente\n";

        return;
    }
    $pdo->exec(
        "ALTER TABLE `users`
         ADD COLUMN `session_epoch` INT UNSIGNED NULL DEFAULT NULL
         COMMENT 'Sessions ouvertes avant cet horodatage Unix : fermées' AFTER `last_login_at`"
    );
    echo "  users.session_epoch ajoutée\n";
};
