<?php

declare(strict_types=1);

/**
 * Terminaux du jeu enregistrés « tablet » par l'ancien module Connect : c'est le téléphone Android COMSPEC.
 * Certificats émis sans numéro de série ni empreinte : complétés comme le ferait une PKI (valeurs stables).
 * Relançable sans risque : ne touche que les lignes encore concernées.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists('atak_terminals')) {
        $n = $pdo->exec(
            "UPDATE atak_terminals
             SET terminal_type = 'phone'
             WHERE terminal_type = 'tablet'
               AND (platform_label LIKE 'Arma 3%' OR platform_label LIKE '%COMSPEC%')"
        );
        echo '  Terminaux du jeu passés en téléphone : ' . (int) $n . "\n";
    }

    if ($tableExists('atak_certificates')) {
        $n = $pdo->exec(
            "UPDATE atak_certificates
             SET serial_number = UPPER(SUBSTRING(SHA2(CONCAT(tenant_id, ':', certificate_ref, ':serial'), 256), 1, 32))
             WHERE serial_number IS NULL OR serial_number = ''"
        );
        $m = $pdo->exec(
            "UPDATE atak_certificates
             SET fingerprint_sha256 = UPPER(SHA2(CONCAT(tenant_id, ':', certificate_ref, ':', serial_number), 256))
             WHERE fingerprint_sha256 IS NULL OR fingerprint_sha256 = ''"
        );
        echo '  Certificats complétés : ' . (int) $n . ' numéro(s) de série, ' . (int) $m . " empreinte(s)\n";
    }
};
