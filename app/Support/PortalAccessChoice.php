<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;
use App\Core\Session;
use App\Repositories\TenantRepository;

/**
 * Choix d’espace après connexion : administration (TBA) ou accueil Athena (tableau de bord).
 * Les comptes sans organisation (tenant système `default`) restent sur le dashboard classique.
 */
final class PortalAccessChoice
{
    public const SESSION_KEY = 'preferred_portal';
    public const PORTAL_TBA = 'tba';
    public const PORTAL_JNET = 'jnet';

    public static function canAccessTba(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('site.support');
    }

    /**
     * Tenant système « pas d’organisation » (slug default / libellés techniques).
     *
     * @param array<string, mixed>|null $tenant
     */
    public static function isPlaceholderTenant(?array $tenant): bool
    {
        if ($tenant === null) {
            return true;
        }
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        if ($slug === '' || $slug === 'default') {
            return true;
        }
        $name = mb_strtolower(str_replace(["'", '’'], "'", trim((string) ($tenant['name'] ?? ''))));
        if ($name === '') {
            return true;
        }

        return $name === 'aucune organisation'
            || str_contains($name, 'aucune organisation')
            || str_contains($name, "pas d'organisation");
    }

    /**
     * Appartenance / dossier dont le tenant est le sas « pas d’organisation ».
     *
     * @param array<string, mixed> $membership
     */
    public static function membershipIsPlaceholder(array $membership): bool
    {
        return self::isPlaceholderTenant([
            'slug' => (string) ($membership['tenant_slug'] ?? $membership['slug'] ?? ''),
            'name' => (string) ($membership['tenant_name'] ?? $membership['name'] ?? ''),
        ]);
    }

    /**
     * Dossiers à afficher sur la fiche personne : le tenant système disparaît
     * dès qu’une communauté réelle existe ; il reste le dossier des orphelins.
     *
     * @param list<array<string, mixed>> $memberships
     * @return list<array<string, mixed>>
     */
    public static function personFileDossiers(array $memberships): array
    {
        $live = [];
        $placeholders = [];
        foreach ($memberships as $membership) {
            if (self::membershipIsPlaceholder($membership)) {
                $placeholders[] = $membership;
                continue;
            }
            $status = strtolower(trim((string) (
                $membership['membership_status']
                ?? $membership['status']
                ?? 'active'
            )));
            if (in_array($status, ['left', 'inactive', 'merged'], true)) {
                continue;
            }
            $live[] = $membership;
        }
        if ($live !== []) {
            return array_values($live);
        }

        return array_values($placeholders);
    }

    /** Session courante sur un compte sans organisation réelle. */
    public static function isNoOrganizationContext(): bool
    {
        $tenantId = (int) Session::get('tenant_id', 0);
        if ($tenantId <= 0) {
            return true;
        }
        try {
            /** @var TenantRepository $tenants */
            $tenants = \App\Core\Container::get(TenantRepository::class);
            $tenant = $tenants->findById($tenantId);
        } catch (\Throwable) {
            return false;
        }

        return self::isPlaceholderTenant(is_array($tenant) ? $tenant : null);
    }

    public static function remember(string $portal, bool $persist): void
    {
        $portal = self::normalize($portal);
        if ($portal === null) {
            return;
        }
        Session::set(self::SESSION_KEY, $portal);
        if ($persist) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
            setcookie(
                'athena_preferred_portal',
                $portal,
                [
                    'expires' => time() + 60 * 60 * 24 * 90,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }
    }

    public static function remembered(): ?string
    {
        $fromSession = self::normalize((string) Session::get(self::SESSION_KEY, ''));
        if ($fromSession !== null) {
            return $fromSession;
        }
        $fromCookie = self::normalize((string) ($_COOKIE['athena_preferred_portal'] ?? ''));
        if ($fromCookie !== null) {
            Session::set(self::SESSION_KEY, $fromCookie);

            return $fromCookie;
        }

        return null;
    }

    public static function clearRemembered(): void
    {
        Session::forget(self::SESSION_KEY);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        setcookie(
            'athena_preferred_portal',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function redirectUrlFor(string $portal): string
    {
        if (self::isNoOrganizationContext()) {
            return url('dashboard');
        }
        $portal = self::normalize($portal) ?? self::PORTAL_JNET;
        if ($portal === self::PORTAL_TBA) {
            if (self::canAccessTba()) {
                return url('back-office');
            }

            return url('dashboard');
        }

        return url('dashboard');
    }

    public static function normalize(string $portal): ?string
    {
        $portal = strtolower(trim($portal));
        if ($portal === self::PORTAL_TBA || $portal === self::PORTAL_JNET) {
            return $portal;
        }

        return null;
    }
}
