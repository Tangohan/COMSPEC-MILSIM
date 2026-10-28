<?php

declare(strict_types=1);

/**
 * Clichés de reconnaissance confidentiels déjà reçus : sortis de public/uploads/recon (servi tel quel
 * par le serveur web) vers storage/private/recon, sous un nom aléatoire (32 hex), et image_path
 * réécrit en « recon-private/<nom> ». Ils ne sont plus servis que par GET /api/recon/images/{id}/file
 * (voir App\Support\ReconImageStorage et AtakApiController::reconImagesFile).
 *
 * Autonome (pas d'autoload : run-migrations.php ne charge pas l'application). Idempotent : une ligne
 * déjà en recon-private/ est ignorée ; un fichier introuvable est laissé tel quel et compté.
 */
return static function (PDO $pdo): void {
    $exists = $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recon_images' AND COLUMN_NAME = 'is_confidential' LIMIT 1"
    );
    if (!$exists || !$exists->fetchColumn()) {
        return;
    }

    $root = dirname(__DIR__);
    $privateDir = $root . '/storage/private/recon';
    if (!is_dir($privateDir) && !@mkdir($privateDir, 0770, true) && !is_dir($privateDir)) {
        echo "  [ATTENTION] storage/private/recon non créé : clichés confidentiels laissés en place.\n";

        return;
    }
    foreach ([dirname($privateDir), $privateDir] as $dir) {
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        }
    }

    $rows = $pdo->query(
        "SELECT id, image_path FROM recon_images
         WHERE is_confidential = 1 AND image_path IS NOT NULL AND image_path <> ''
           AND image_path NOT LIKE 'recon-private/%'"
    );
    if (!$rows) {
        return;
    }
    $update = $pdo->prepare('UPDATE recon_images SET image_path = ? WHERE id = ? AND image_path = ?');
    $moved = 0;
    $missing = 0;
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $oldPath = (string) $row['image_path'];
        $base = basename(str_replace('\\', '/', $oldPath));
        if ($base === '' || preg_match('/^[a-zA-Z0-9._-]+$/', $base) !== 1 || str_contains($base, '..')) {
            $missing++;
            continue;
        }
        $src = null;
        foreach ([$root . '/public/uploads/recon/' . $base, $root . '/storage/uploads/recon/' . $base] as $candidate) {
            if (is_file($candidate)) {
                $src = $candidate;
                break;
            }
        }
        if ($src === null) {
            $missing++;
            continue;
        }
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $privateDir . '/' . $name;
        if (!@rename($src, $dest)) {
            if (!@copy($src, $dest)) {
                $missing++;
                continue;
            }
            @unlink($src);
        }
        $update->execute(['recon-private/' . $name, (int) $row['id'], $oldPath]);
        $moved++;
    }
    echo "  [OK] clichés confidentiels rangés hors racine web : {$moved}" . ($missing > 0 ? ", introuvables : {$missing}" : '') . "\n";
};
