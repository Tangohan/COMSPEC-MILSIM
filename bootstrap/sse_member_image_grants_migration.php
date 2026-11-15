<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * SSE — images confidentielles des membres : demandes d'accès et dérogations datées (table sse_member_image_grants).
 *
 * Une ligne = une demande d'un membre pour voir les images de service d'un autre membre (statut « pending »), puis la décision d'un
 * supérieur hiérarchique du membre concerné (« granted » avec dates de début et de fin et motif, « denied »), ou le retrait (« revoked »).
 * Aucune donnée ajoutée ni modifiée ; sans cette table, le site refuse simplement toute dérogation (fail-closed).
 *
 * Idempotente ; MySQL et sqlite (tests). Retour arrière : migrations/rollback/sse_member_image_grants_down.sql.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $u = static fn (string $def): string => $sqlite ? str_replace(' UNSIGNED', '', $def) : $def;

    if ($schema->tableExists('sse_member_image_grants')) {
        $log("  [OK] sse_member_image_grants (déjà présente)\n");

        return;
    }
    $lines = [
        $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
        $u('tenant_id INT UNSIGNED NOT NULL'),
        $u('owner_user_id INT UNSIGNED NOT NULL'),
        $u('grantee_user_id INT UNSIGNED NOT NULL'),
        "status VARCHAR(12) NOT NULL DEFAULT 'pending'",
        'reason VARCHAR(500) NOT NULL',
        $u('decided_by_user_id INT UNSIGNED DEFAULT NULL'),
        'decided_at DATETIME DEFAULT NULL',
        'starts_at DATETIME DEFAULT NULL',
        'ends_at DATETIME DEFAULT NULL',
        'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];
    if (!$sqlite) {
        $lines[] = 'PRIMARY KEY (id)';
        $lines[] = 'KEY idx_sse_member_image_grants_grantee (tenant_id, grantee_user_id, owner_user_id, status)';
        $lines[] = 'KEY idx_sse_member_image_grants_owner (tenant_id, owner_user_id, status)';
        $lines[] = 'CONSTRAINT fk_sse_member_image_grants_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE';
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sse_member_image_grants (\n    " . implode(",\n    ", $lines) . "\n)"
        . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
    );
    if ($sqlite) {
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_member_image_grants_grantee ON sse_member_image_grants (tenant_id, grantee_user_id, owner_user_id, status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_member_image_grants_owner ON sse_member_image_grants (tenant_id, owner_user_id, status)');
    }
    $log("  [OK] sse_member_image_grants\n");
};
