<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Database;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Community\TenantArchiveState;
use App\Services\Community\TenantRemovalService;
use PDO;

/**
 * Usage d’Athena sans organisation (« compte simple ») :
 * - à la création du compte, la personne peut choisir de rester seule (users.account_mode = solo) ;
 * - un fondateur resté seul dans son organisation peut repasser en compte simple : l’organisation
 *   est archivée (réversible par l’administration du site) et le compte rejoint « Pas d’organisation ».
 */
final class AccountUsageService
{
    public const MODE_SOLO = 'solo';

    /** Clé des réglages de la communauté : comptes ayant écarté la proposition « compte simple ». */
    public const OFFER_DISMISSED_KEY = 'solo_offer_dismissed';

    private static ?bool $hasColumn = null;

    public function __construct(
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private RoleRepository $roleRepository,
        private ?PDO $pdo = null,
    ) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function hasAccountModeColumn(): bool
    {
        if (self::$hasColumn === null) {
            try {
                $st = $this->pdo()->query(
                    "SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_mode' LIMIT 1"
                );
                self::$hasColumn = $st !== false && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                self::$hasColumn = false;
            }
        }

        return self::$hasColumn;
    }

    public function isSolo(int $userId): bool
    {
        if ($userId < 1 || !$this->hasAccountModeColumn()) {
            return false;
        }
        $st = $this->pdo()->prepare('SELECT account_mode FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);

        return (string) ($st->fetchColumn() ?: '') === self::MODE_SOLO;
    }

    public function setSolo(int $userId, bool $solo): bool
    {
        if ($userId < 1 || !$this->hasAccountModeColumn()) {
            return false;
        }

        return $this->pdo()->prepare('UPDATE users SET account_mode = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$solo ? self::MODE_SOLO : null, $userId]);
    }

    /**
     * Proposition « passer en compte simple » : responsable d’une vraie organisation dont il est
     * le seul membre actif, sans abonnement payant en cours, et qui ne l’a pas écartée.
     *
     * @return array{tenant_id: int, tenant_name: string}|null
     */
    public function singleMemberOffer(int $userId, int $tenantId): ?array
    {
        if ($userId < 1 || $tenantId < 2) {
            return null;
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant || !$this->isConvertible($tenant)) {
            return null;
        }
        if (!$this->isManager($userId, $tenantId)) {
            return null;
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $dismissed = is_array($settings[self::OFFER_DISMISSED_KEY] ?? null) ? $settings[self::OFFER_DISMISSED_KEY] : [];
        if (array_key_exists((string) $userId, $dismissed)) {
            return null;
        }
        $members = $this->userRepository->listActiveUserIdsForTenant($tenantId);
        if ($members !== [$userId]) {
            return null;
        }

        return [
            'tenant_id' => $tenantId,
            'tenant_name' => trim((string) ($tenant['name'] ?? '')) ?: 'votre organisation',
        ];
    }

    public function dismissOffer(int $userId, int $tenantId): void
    {
        if ($userId < 1 || $tenantId < 1) {
            return;
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $dismissed = is_array($settings[self::OFFER_DISMISSED_KEY] ?? null) ? $settings[self::OFFER_DISMISSED_KEY] : [];
        $dismissed[(string) $userId] = gmdate('c');
        $this->tenantRepository->mergeSettings($tenantId, [self::OFFER_DISMISSED_KEY => $dismissed]);
    }

    /**
     * Archive l’organisation (le compte est relogé dans « Pas d’organisation ») et passe le compte en solo.
     *
     * @return array{ok: bool, error?: string}
     */
    public function convertToSimpleAccount(int $userId, int $tenantId): array
    {
        if ($this->singleMemberOffer($userId, $tenantId) === null
            && !($this->isManager($userId, $tenantId) && $this->userRepository->listActiveUserIdsForTenant($tenantId) === [$userId])) {
            return ['ok' => false, 'error' => 'Le passage en compte simple n’est possible que si vous êtes le seul membre de votre organisation.'];
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant || !$this->isConvertible($tenant)) {
            return ['ok' => false, 'error' => 'Cette organisation ne peut pas être fermée ainsi (abonnement payant en cours ou espace technique).'];
        }

        $result = (new TenantRemovalService($this->pdo()))->archive(
            $tenantId,
            $userId,
            'Passage en compte simple demandé par son fondateur, seul membre.'
        );
        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'Archivage impossible pour le moment.')];
        }
        $this->setSolo($userId, true);

        return ['ok' => true];
    }

    /** @param array<string, mixed> $tenant */
    private function isConvertible(array $tenant): bool
    {
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        if ((int) ($tenant['id'] ?? 0) <= 1 || $slug === '' || $slug === 'default') {
            return false;
        }
        if (TenantArchiveState::isArchived($tenant)) {
            return false;
        }
        // Formule payée en cours (prestataire ou PayPal manuel non échu) : on ne ferme pas une organisation payée.
        return !\App\Support\ManualBilling::paidActive($tenant)
            && strtolower(trim((string) ($tenant['subscription_status'] ?? ''))) !== 'past_due';
    }

    private function isManager(int $userId, int $tenantId): bool
    {
        foreach (['community_owner', 'community_admin'] as $slug) {
            $roleId = $this->roleRepository->getIdBySlug($tenantId, $slug);
            if ($roleId !== null && $this->userRepository->userHasTenantRole($userId, $roleId)) {
                return true;
            }
        }

        return false;
    }
}
