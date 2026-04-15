<?php

declare(strict_types=1);

/**
 * Données minimales pour que le module « training » soit résolu sur tous les canaux (évite un refus par absence de release).
 * Idempotent.
 */
function run_platform_training_release_seed(PDO $pdo): void
{
    $has = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    if (!$has('platform_modules') || !$has('platform_module_versions') || !$has('platform_module_channel_releases') || !$has('deployment_channels')) {
        return;
    }

    $st = $pdo->query("SELECT id FROM platform_modules WHERE code = 'training' LIMIT 1");
    $mid = (int) $st->fetchColumn();
    if ($mid < 1) {
        $pdo->exec(
            "INSERT INTO platform_modules (code, name, description, is_active, is_public)
             VALUES ('training', 'Formations (LMS)', 'Catalogue et parcours pédagogiques', 1, 1)"
        );
        $mid = (int) $pdo->lastInsertId();
    }
    if ($mid < 1) {
        return;
    }

    $chk = $pdo->prepare('SELECT COUNT(*) FROM platform_module_channel_releases WHERE module_id = ?');
    $chk->execute([$mid]);
    if ((int) $chk->fetchColumn() > 0) {
        return;
    }

    $vins = $pdo->prepare(
        'INSERT INTO platform_module_versions (module_id, version, status) VALUES (?, ?, ?)'
    );
    $vins->execute([$mid, '1.0.0', 'published']);
    $vid = (int) $pdo->lastInsertId();
    if ($vid < 1) {
        return;
    }

    $ins = $pdo->prepare(
        'INSERT INTO platform_module_channel_releases (module_id, module_version_id, channel_id, is_current)
         VALUES (?, ?, ?, 1)'
    );
    $ch = $pdo->query('SELECT id FROM deployment_channels ORDER BY priority ASC, id ASC');
    while ($row = $ch->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int) ($row['id'] ?? 0);
        if ($cid > 0) {
            $ins->execute([$mid, $vid, $cid]);
        }
    }

    echo "  [OK] platform_training_release_seed\n";
}
