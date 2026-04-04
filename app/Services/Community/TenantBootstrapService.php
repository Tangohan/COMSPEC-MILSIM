<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use PDO;

final class TenantBootstrapService
{
    private const RESERVED_SLUGS = ['default', 'admin', 'api', 'www', 'c', 'login', 'dashboard', 'hub', 'forum', 'system'];

    public function __construct(
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository
    ) {}

    /**
     * Crée une communauté (tenant), seeds forum/documents, duplique le créateur comme super-admin.
     *
     * @return array{tenant_id: int, user_id: int}
     */
    public function createCommunity(int $creatorUserId, string $name, string $slug, array $options = []): array
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?$/', $slug)) {
            throw new \InvalidArgumentException('Le slug ne peut contenir que des lettres minuscules, chiffres et tirets.');
        }
        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new \InvalidArgumentException('Ce slug est réservé.');
        }
        if ($this->tenantRepository->slugExists($slug)) {
            throw new \RuntimeException('Une communauté avec ce slug existe déjà.');
        }

        $pdo = Database::getPdo();
        $pdo->beginTransaction();
        try {
            $planSlug = in_array(($options['plan_slug'] ?? 'free'), ['free', 'premium'], true) ? (string) $options['plan_slug'] : 'free';
            $tenantId = $this->tenantRepository->create($name, $slug, $planSlug);

            $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, created_at) VALUES (?, ?, ?, ?, 1, 1, NOW())')
                ->execute([$tenantId, 'Super Administrator', 'super_admin', '']);
            $superAdminRoleId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO roles (tenant_id, name, slug, description, is_system, is_locked, created_at) VALUES (?, ?, ?, ?, 1, 0, NOW())')
                ->execute([$tenantId, 'Administrator', 'tenant_admin', '']);
            $tenantAdminRoleId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO grades (tenant_id, name, short_name, rank_order, created_at) VALUES (?, ?, ?, 10, NOW())')
                ->execute([$tenantId, 'Officer', 'OFR']);
            $gradeId = (int) $pdo->lastInsertId();

            TenantSeedHelper::seedForumAndRoles($pdo, $tenantId);
            TenantSeedHelper::seedDocumentsEquipment($pdo, $tenantId);
            TenantSeedHelper::ensureSystemAdminPermissions($pdo, $tenantId);
            TenantSeedHelper::ensurePersonnelPanelsAndMatricule($pdo, $tenantId);

            $st = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT ?, permission_id FROM role_permissions WHERE role_id = ?');
            $st->execute([$superAdminRoleId, $tenantAdminRoleId]);

            $newUserId = $this->userRepository->cloneUserToTenant($creatorUserId, $tenantId, $superAdminRoleId, $gradeId);

            $this->tenantRepository->setOwner($tenantId, $newUserId);
            $this->tenantRepository->updateSettings($tenantId, [
                'community' => [
                    'registration_mode' => ($options['registration_mode'] ?? 'milsim') === 'simple' ? 'simple' : 'milsim',
                    'community_locked' => !empty($options['community_locked']),
                    'require_ai_ack' => array_key_exists('require_ai_ack', $options) ? (bool) $options['require_ai_ack'] : true,
                    'welcome_text' => trim((string) ($options['welcome_text'] ?? '')),
                ],
            ]);

            $pdo->commit();

            return ['tenant_id' => $tenantId, 'user_id' => $newUserId];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
