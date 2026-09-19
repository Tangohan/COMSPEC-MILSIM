<?php

declare(strict_types=1);

/**
 * Fiche relais ATAK : nom, identité réseau, débit, fiabilité, places, puissance.
 */
return static function (PDO $pdo): void {
    $colExists = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$colExists($pdo, 'atak_relays', 'id') && !$colExists($pdo, 'atak_relays', 'relay_uid')) {
        return;
    }

    $cols = [
        'display_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'identity' => "VARCHAR(80) NOT NULL DEFAULT ''",
        'ip_addr' => "VARCHAR(64) NOT NULL DEFAULT ''",
        'gateway' => "VARCHAR(64) NOT NULL DEFAULT ''",
        'certificate' => "VARCHAR(180) NOT NULL DEFAULT ''",
        'slots' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 8',
        'slots_used' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
        'power_w' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 25',
        'throughput_mbps' => 'DECIMAL(6,1) NOT NULL DEFAULT 12.0',
        'reliability_pct' => 'TINYINT UNSIGNED NOT NULL DEFAULT 92',
    ];
    foreach ($cols as $name => $ddl) {
        if (!$colExists($pdo, 'atak_relays', $name)) {
            $pdo->exec('ALTER TABLE atak_relays ADD COLUMN ' . $name . ' ' . $ddl);
        }
    }
};
