<?php

declare(strict_types=1);

/**
 * Étape « Page publique du joueur : bannière personnelle (user_public_cv.banner_path) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Même colonne que migrations/2026_11_02_001_user_public_cv_banner.sql et UserPublicCvRepository::ensureBannerColumn().
 */

$publicCvBannerSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$publicCvBannerSchema->tableExists('user_public_cv')) {
    throw new RuntimeException('Table user_public_cv absente : l’étape user_public_cv doit passer avant celle-ci.');
}
if ($publicCvBannerSchema->addColumnIfMissing('user_public_cv', 'banner_path', 'VARCHAR(255) NULL DEFAULT NULL')) {
    echo "  [OK] Colonne user_public_cv.banner_path ajoutée.\n";
} else {
    echo "  [OK] Colonne user_public_cv.banner_path déjà présente.\n";
}
