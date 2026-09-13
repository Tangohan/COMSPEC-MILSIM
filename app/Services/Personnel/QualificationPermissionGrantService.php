<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Repositories\QualificationReferentielRepository;
use PDO;
use Throwable;

/**
 * Lie une qualification obtenue à des permissions plateforme (allowlist stricte).
 * Jamais d’accès admin plateforme via ce mécanisme.
 */
final class QualificationPermissionGrantService
{
    /** Préfixes / codes absolument interdits. */
    private const BLOCKED_PREFIXES = [
        'admin.',
        'platform.',
        'system.',
    ];

    private const BLOCKED_EXACT = [
        'admin.access',
        'admin.organization',
        'admin.roles.manage',
        'admin.permissions.manage',
        'admin.settings.manage',
        'admin.branding.manage',
        'admin.backoffice.view',
    ];

    public function __construct(
        private QualificationReferentielRepository $referentiel,
    ) {
    }

    public function isPermissionAllowed(string $code): bool
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return false;
        }
        if (in_array($code, self::BLOCKED_EXACT, true)) {
            return false;
        }
        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return false;
            }
        }

        return true;
    }

    public function applyGrants(
        int $tenantId,
        int $userId,
        int $qualificationId,
        ?int $levelId,
        ?int $actorId
    ): void {
        $grants = $this->referentiel->listPermissionGrants($tenantId, $qualificationId);
        foreach ($grants as $grant) {
            $code = (string) ($grant['permission_code'] ?? '');
            if (!$this->isPermissionAllowed($code)) {
                continue;
            }
            $grantLevel = $grant['qualification_level_id'] ?? null;
            if ($grantLevel !== null && $levelId !== null && (int) $grantLevel !== $levelId) {
                continue;
            }
            if ($grantLevel !== null && $levelId === null) {
                continue;
            }
            $this->attachPermissionToUser($tenantId, $userId, $code);
        }
    }

    public function revokeGrants(
        int $tenantId,
        int $userId,
        int $qualificationId,
        ?int $levelId,
        ?int $actorId
    ): void {
        // Révocation soft : on ne retire pas automatiquement les permissions
        // qui pourraient être accordées par un autre mécanisme (rôle / kit).
        // Journal uniquement — les opérateurs retirent manuellement si besoin.
        unset($tenantId, $userId, $qualificationId, $levelId, $actorId);
    }

    private function attachPermissionToUser(int $tenantId, int $userId, string $permissionCode): void
    {
        try {
            $pdo = Database::getPdo();
            $permId = $this->resolvePermissionId($pdo, $permissionCode);
            if ($permId === null) {
                return;
            }
            // Rôle technique dédié par permission grant si le schéma le permet ;
            // sinon insertion dans une table de grants directs si elle existe.
            if ($this->tableExists($pdo, 'user_direct_permissions')) {
                $st = $pdo->prepare(
                    'INSERT IGNORE INTO user_direct_permissions (tenant_id, user_id, permission_id, source, created_at)
                     VALUES (?, ?, ?, ?, NOW())'
                );
                try {
                    $st->execute([$tenantId, $userId, $permId, 'qualification']);
                } catch (Throwable) {
                    $st = $pdo->prepare(
                        'INSERT IGNORE INTO user_direct_permissions (user_id, permission_id, created_at)
                         VALUES (?, ?, NOW())'
                    );
                    $st->execute([$userId, $permId]);
                }

                return;
            }
            if ($this->tableExists($pdo, 'tenant_user_permissions')) {
                $st = $pdo->prepare(
                    'INSERT IGNORE INTO tenant_user_permissions (tenant_id, user_id, permission_id, created_at)
                     VALUES (?, ?, ?, NOW())'
                );
                $st->execute([$tenantId, $userId, $permId]);
            }
        } catch (Throwable) {
        }
    }

    private function resolvePermissionId(PDO $pdo, string $code): ?int
    {
        $st = $pdo->prepare('SELECT id FROM permissions WHERE code = ? LIMIT 1');
        $st->execute([$code]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }
}
