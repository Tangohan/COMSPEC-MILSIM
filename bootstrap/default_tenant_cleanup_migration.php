<?php

declare(strict_types=1);

/**
 * Nettoyage du tenant système « default » (pas d’organisation) : supprime les artefacts
 * qui ont pu être créés par erreur comme s’il s’agissait d’une vraie communauté
 * (ex. catégorie forum « Default Organisation — Espace dédié »). Idempotent.
 */

require_once dirname(__DIR__) . '/app/Support/SqlText.php';

return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'tenants')) {
        return;
    }
    $st = $pdo->prepare('SELECT id FROM tenants WHERE ' . \App\Support\SqlText::equalsLiteral($pdo, 'slug', 'default') . ' LIMIT 1');
    $st->execute();
    $defaultTenantId = (int) ($st->fetchColumn() ?: 0);
    if ($defaultTenantId < 1) {
        return;
    }
};
