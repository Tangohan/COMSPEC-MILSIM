<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Identité réelle + couverture en jeu, photo d'identité transmise au jeu (COMSPEC IDENTITY) :
 *  - sse_id_settings.real_with_cover : « Transmettre aussi l'identité réelle avec la couverture » (défaut : oui) ;
 *  - sse_identities.photo_mosaic / photo_mosaic_src : portrait réduit (15 × 20 niveaux de gris) envoyé au jeu dans le
 *    paquet, et la photo dont il est tiré (recalculé quand la photo change).
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests).
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    if ($schema->tableExists('sse_id_settings')) {
        $schema->addColumnIfMissing('sse_id_settings', 'real_with_cover', 'TINYINT(1) NOT NULL DEFAULT 1');
        $log("  [OK] sse_id_settings.real_with_cover\n");
    } else {
        $log("  [ATTENTION] sse_id_settings absente : passez d'abord les étapes du registre d'identité, puis rejouez celle-ci.\n");
    }
    if ($schema->tableExists('sse_identities')) {
        $schema->addColumnIfMissing('sse_identities', 'photo_mosaic', 'VARCHAR(400) DEFAULT NULL');
        $schema->addColumnIfMissing('sse_identities', 'photo_mosaic_src', 'VARCHAR(255) DEFAULT NULL');
        $log("  [OK] sse_identities.photo_mosaic, photo_mosaic_src\n");
    }
};
