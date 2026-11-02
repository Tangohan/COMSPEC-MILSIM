<?php

declare(strict_types=1);

/**
 * Étape « 9-LINE / 5-LINE : envoi au pilote, accusés et indicatifs » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente (CREATE TABLE IF NOT EXISTS) :
 * - atak_nine_line_recipients : un destinataire par demande (indicatif de l'appareil, libellé affiché, équipage),
 *   avec son statut (envoyé, reçu, lu, WILCO, UNABLE) et ses horodatages ; rempli par la page /9-line et par l'app
 *   JTAC du jeu, mis à jour par les accusés du pilote (POST /api/cas/{ref}/status).
 * - atak_brief_callsigns : indicatifs choisis sur le site, par communauté : indicatif JTAC d'un membre
 *   (scope « jtac », clé = id du membre) et indicatif affiché d'un appareil remonté par le jeu (scope « aircraft »,
 *   clé = indicatif remonté).
 * - atak_nine_line.remarks : remarques et restrictions transmises avec la demande (jeu et site), lues par le pilote.
 * Le format 5-LINE réutilise atak_nine_line (mission_kind « fiveline », lignes 1 à 5).
 */

$schema3720 = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$schema3720->tableExists('atak_nine_line')) {
    echo "  [INFO] Table atak_nine_line absente : destinataires non créés.\n";
} else {
    if ($schema3720->addColumnIfMissing('atak_nine_line', 'remarks', 'TEXT NULL DEFAULT NULL AFTER line9')) {
        echo "  [OK] Colonne atak_nine_line.remarks ajoutée.\n";
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS atak_nine_line_recipients (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            nine_line_id INT UNSIGNED NOT NULL,
            callsign VARCHAR(64) NOT NULL,
            label VARCHAR(64) DEFAULT NULL,
            crew_names VARCHAR(500) DEFAULT NULL,
            status VARCHAR(16) NOT NULL DEFAULT \'sent\',
            received_at DATETIME DEFAULT NULL,
            read_at DATETIME DEFAULT NULL,
            ack_at DATETIME DEFAULT NULL,
            ack_by VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_nine_line_recipient (nine_line_id, callsign),
            KEY idx_nine_line_recipient_tenant (tenant_id, created_at),
            CONSTRAINT atak_nine_line_recipients_line_fk FOREIGN KEY (nine_line_id) REFERENCES atak_nine_line (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    echo "  [OK] Table atak_nine_line_recipients prête.\n";
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS atak_brief_callsigns (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id INT UNSIGNED NOT NULL,
        scope VARCHAR(16) NOT NULL,
        owner_key VARCHAR(128) NOT NULL,
        callsign VARCHAR(64) NOT NULL,
        updated_by_user_id INT UNSIGNED DEFAULT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_brief_callsign (tenant_id, scope, owner_key),
        CONSTRAINT atak_brief_callsigns_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);
echo "  [OK] Table atak_brief_callsigns prête.\n";
