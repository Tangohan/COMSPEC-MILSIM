<?php

declare(strict_types=1);

/**
 * Téléphone en jeu, volet terminal : réglages du back-office (format verrouillé, révision d'identité)
 * et dernier état remonté par le jeu (batterie, état, modèle, valeurs affichées par l'appareil).
 * Colonnes ajoutées une par une : relançable sans risque.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_phone_identities' LIMIT 1")->fetchColumn();
    if (!$exists) {
        return;
    }

    $have = [];
    foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_phone_identities'")->fetchAll(PDO::FETCH_COLUMN) as $c) {
        $have[strtolower((string) $c)] = true;
    }

    $columns = [
        // Format choisi par un administrateur : le numéro ne suit plus le référentiel du tenant.
        'format_locked' => "TINYINT(1) NOT NULL DEFAULT 0",
        // Horodatage de la dernière modification d'identité (numéro, IMEI, MAC, format) : révision du profil jeu.
        'identity_revision' => "INT UNSIGNED NOT NULL DEFAULT 0",
        'device_model' => "VARCHAR(80) NULL",
        'battery_pct' => "TINYINT UNSIGNED NULL",
        'device_state' => "VARCHAR(16) NULL",
        'device_reason' => "VARCHAR(80) NULL",
        'signal_bars' => "TINYINT UNSIGNED NULL",
        'live_number' => "VARCHAR(32) NULL",
        'live_imei' => "VARCHAR(32) NULL",
        'live_mac' => "VARCHAR(17) NULL",
        'device_seen_at' => "DATETIME NULL",
    ];
    foreach ($columns as $name => $def) {
        if (!isset($have[$name])) {
            $pdo->exec("ALTER TABLE game_phone_identities ADD COLUMN {$name} {$def}");
        }
    }
};
