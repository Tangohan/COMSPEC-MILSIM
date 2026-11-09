<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Gate;
use App\Core\Session;
use App\Support\PlanGift;

/**
 * Lecture unique de la formule effective d'une communauté : la formule réelle (PlanGift::effectivePlanSlug), sauf si
 * un administrateur de la plateforme a activé l'« Aperçu de la formule » pour la communauté de sa session.
 *
 * Garde-fous : l'aperçu vit en session seulement, ne vaut que pour la communauté en cours (tenant_id de la session) et
 * que pour une personne qui a le droit admin.system ; il ne touche ni à la formule enregistrée, ni aux données, ni aux
 * droits (Gate), ni au type de communauté, ni à l'isolation par tenant_id. Il ne sert qu'à l'affichage et aux plafonds
 * de formule (FeatureGateService), et toute requête d'écriture est refusée pendant l'aperçu (PlanPreviewReadOnlyMiddleware).
 */
final class EffectivePlan
{
    public const SESSION_SLUG = 'plan_preview_slug';
    public const SESSION_TENANT = 'plan_preview_tenant_id';
    public const SESSION_NAME = 'plan_preview_name';

    /** Formule affichée pour une ligne tenants : aperçu actif pour cette communauté, sinon formule réelle. */
    public static function slugFor(array $tenant, ?int $now = null): string
    {
        $preview = self::previewSlugFor((int) ($tenant['id'] ?? 0));

        return $preview ?? PlanGift::effectivePlanSlug($tenant, $now);
    }

    /** Formule simulée pour cette communauté, ou null (pas d'aperçu, autre communauté, pas administrateur). */
    public static function previewSlugFor(int $tenantId): ?string
    {
        if ($tenantId < 1) {
            return null;
        }
        $slug = self::sessionSlug();
        if ($slug === null || (int) Session::get(self::SESSION_TENANT, 0) !== $tenantId) {
            return null;
        }
        if ((int) Session::get('tenant_id', 0) !== $tenantId || !self::mayPreview()) {
            return null;
        }

        return $slug;
    }

    /**
     * Aperçu présent en session pour la communauté en cours, sans consulter les droits (Gate, pas encore chargé dans
     * les middlewares globaux). Ne sert qu'à REFUSER des écritures : jamais à accorder quoi que ce soit.
     */
    public static function sessionMatches(): bool
    {
        $tenantId = (int) Session::get('tenant_id', 0);

        return $tenantId > 0 && self::sessionSlug() !== null && (int) Session::get(self::SESSION_TENANT, 0) === $tenantId;
    }

    public static function sessionName(): string
    {
        $name = trim((string) Session::get(self::SESSION_NAME, ''));

        return $name !== '' ? $name : (string) self::sessionSlug();
    }

    /** Aperçu actif pour la communauté en cours de la session. */
    public static function isActive(): bool
    {
        return self::previewSlugFor((int) Session::get('tenant_id', 0)) !== null;
    }

    /** @return array{slug: string, name: string, tenant_id: int}|null */
    public static function current(): ?array
    {
        $tenantId = (int) Session::get('tenant_id', 0);
        $slug = self::previewSlugFor($tenantId);
        if ($slug === null) {
            return null;
        }
        $name = trim((string) Session::get(self::SESSION_NAME, ''));

        return ['slug' => $slug, 'name' => $name !== '' ? $name : $slug, 'tenant_id' => $tenantId];
    }

    /** Seul un administrateur de la plateforme peut simuler une formule. */
    public static function mayPreview(): bool
    {
        return Gate::getInstance()->allows('admin.system');
    }

    public static function start(string $slug, string $name, int $tenantId): void
    {
        Session::set(self::SESSION_SLUG, strtolower(trim($slug)));
        Session::set(self::SESSION_NAME, trim($name));
        Session::set(self::SESSION_TENANT, $tenantId);
    }

    public static function stop(): void
    {
        Session::forgetMany([self::SESSION_SLUG, self::SESSION_NAME, self::SESSION_TENANT]);
    }

    private static function sessionSlug(): ?string
    {
        $slug = Session::get(self::SESSION_SLUG);
        if (!is_string($slug)) {
            return null;
        }
        $slug = strtolower(trim($slug));

        return preg_match('/^[a-z0-9_\-]{1,64}$/', $slug) === 1 ? $slug : null;
    }
}
