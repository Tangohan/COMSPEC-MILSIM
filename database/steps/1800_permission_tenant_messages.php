<?php

declare(strict_types=1);

/**
 * Étape « Permission messagerie interne (comms.tenant_messages.receive) » (Référentiels).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Permission messagerie interne : comms.tenant_messages.receive (+ liaison rôles gouvernance)
try {
    $permSlug = 'comms.tenant_messages.receive';
    $permName = 'Recevoir les messages internes adressés à l’encadrement';
    $tStmt = $pdo->query('SELECT id FROM tenants');
    if ($tStmt) {
        $insPerm = $pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module, created_at) VALUES (?, ?, ?, ?, NOW())');
        $chkPerm = $pdo->prepare('SELECT id FROM permissions WHERE tenant_id = ? AND ' . \App\Support\SqlText::equals($pdo, 'slug') . ' LIMIT 1');
        $insRp = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        $roleIds = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND ' . \App\Support\SqlText::inLiterals($pdo, 'slug', ['tenant_admin', 'community_owner']));
        while ($tRow = $tStmt->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int) ($tRow['id'] ?? 0);
            if ($tid < 1) {
                continue;
            }
            $chkPerm->execute([$tid, $permSlug]);
            $permRow = $chkPerm->fetch(PDO::FETCH_ASSOC);
            if ($permRow) {
                $permId = (int) ($permRow['id'] ?? 0);
            } else {
                $insPerm->execute([$tid, $permName, $permSlug, 'comms']);
                $permId = (int) $pdo->lastInsertId();
            }
            if ($permId < 1) {
                continue;
            }
            $roleIds->execute([$tid]);
            while ($r = $roleIds->fetch(PDO::FETCH_ASSOC)) {
                $rid = (int) ($r['id'] ?? 0);
                if ($rid > 0) {
                    $insRp->execute([$rid, $permId]);
                }
            }
        }
    }
    echo "Permission messagerie interne (comms.tenant_messages.receive) — synchronisation par tenant OK.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] Permission messagerie interne : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('Permission messagerie interne', $e);
}

$platformIntPath = $root . '/migrations/platform_integrations.sql';
if (is_file($platformIntPath)) {
    echo "Migration platform_integrations.sql (idempotent)...\n";
    try {
        $pdo->exec(file_get_contents($platformIntPath));
    } catch (Throwable $e) {
        echo '  [ATTENTION] platform_integrations.sql : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('platform_integrations.sql', $e);
    }
}
