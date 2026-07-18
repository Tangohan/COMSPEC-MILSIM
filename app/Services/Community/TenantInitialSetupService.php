<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\RoleRepository;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;

/**
 * État de la configuration initiale post-création (cockpit non bloquant).
 * Distinct de onboarding_completed_at (wizard de création v2).
 */
final class TenantInitialSetupService
{
    public const VERSION = 1;

    public function __construct(
        private ?TenantRepository $tenantRepository = null,
        private ?TenantBrandingRepository $brandingRepository = null,
        private ?RoleRepository $roleRepository = null,
    ) {
        $this->tenantRepository ??= new TenantRepository();
        $this->brandingRepository ??= new TenantBrandingRepository();
        $this->roleRepository ??= new RoleRepository();
    }

    /**
     * @return array{
     *   items: array<string, bool>,
     *   optional: array<string, bool>,
     *   done: int,
     *   total: int,
     *   percent: int,
     *   completed: bool,
     *   dismissed: bool,
     *   show_banner: bool,
     *   needs_attention: bool,
     *   roles_count: int
     * }
     */
    public function analyze(int $tenantId): array
    {
        $tenant = $this->tenantRepository->findById($tenantId) ?? [];
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $branding = $this->brandingRepository->findByTenantId($tenantId) ?? [];

        $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
        if ($logoUrl === '') {
            $logoUrl = trim((string) ($tenant['logo_url'] ?? ''));
        }

        $roles = $this->roleRepository->forTenantOrganization($tenantId);
        $rolesCount = count($roles);

        $items = [
            'Nom affiché' => trim((string) ($tenant['name'] ?? '')) !== '',
            'Adresse publique' => trim((string) ($tenant['slug'] ?? '')) !== '',
            'Logo' => $logoUrl !== '',
            'E-mail de contact' => trim((string) ($community['contact_email'] ?? '')) !== '',
            'Message d’accueil' => trim((string) ($community['welcome_text'] ?? '')) !== '',
            'Mode d’inscription' => $this->hasRegistrationConfigured($community),
            'Rôles de la communauté' => $rolesCount > 0,
        ];

        $optional = [
            'Lien Discord' => trim((string) ($community['contact_discord_url'] ?? '')) !== '',
        ];

        $done = count(array_filter($items));
        $total = count($items);
        $percent = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $completed = trim((string) ($settings['initial_setup_completed_at'] ?? '')) !== '';
        $dismissed = trim((string) ($settings['initial_setup_dismissed_at'] ?? '')) !== '';
        $needsAttention = !$completed && $percent < 100;
        $showBanner = !$completed && !$dismissed;

        return [
            'items' => $items,
            'optional' => $optional,
            'done' => $done,
            'total' => $total,
            'percent' => $percent,
            'completed' => $completed,
            'dismissed' => $dismissed,
            'show_banner' => $showBanner,
            'needs_attention' => $needsAttention,
            'roles_count' => $rolesCount,
        ];
    }

    public function markDismissed(int $tenantId): void
    {
        $this->tenantRepository->mergeSettings($tenantId, [
            'initial_setup_dismissed_at' => date('c'),
            'initial_setup_version' => self::VERSION,
        ]);
    }

    public function markCompleted(int $tenantId): void
    {
        $this->tenantRepository->mergeSettings($tenantId, [
            'initial_setup_completed_at' => date('c'),
            'initial_setup_dismissed_at' => null,
            'initial_setup_version' => self::VERSION,
        ]);
    }

    public function clearDismissed(int $tenantId): void
    {
        $this->tenantRepository->mergeSettings($tenantId, [
            'initial_setup_dismissed_at' => null,
        ]);
    }

    /** @param array<string, mixed> $community */
    private function hasRegistrationConfigured(array $community): bool
    {
        if (array_key_exists('registration_mode', $community)) {
            $mode = (string) $community['registration_mode'];

            return $mode === 'simple' || $mode === 'milsim';
        }

        // Défaut seedé / implicite : considéré comme défini pour ne pas bloquer le %.
        return true;
    }
}
