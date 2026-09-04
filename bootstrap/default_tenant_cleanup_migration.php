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

    if ($tableExists($pdo, 'forum_categories')) {
        $hasScope = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_categories' AND COLUMN_NAME = 'scope' LIMIT 1")->fetchColumn();
        if ($hasScope) {
            try {
                $del = $pdo->prepare("DELETE FROM forum_categories WHERE tenant_id = ? AND scope = 'organization'");
                $del->execute([$defaultTenantId]);
                if ($del->rowCount() > 0) {
                    echo '  [OK] default_tenant_cleanup : ' . $del->rowCount() . " catégorie(s) forum fantôme(s) supprimée(s) sur le tenant système.\n";
                }
            } catch (Throwable $e) {
                echo '  [ATTENTION] default_tenant_cleanup (forum_categories) : ' . $e->getMessage() . "\n";
            }
        }
    }
};
