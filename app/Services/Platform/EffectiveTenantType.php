<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Gate;
use App\Core\Session;
use App\Services\Community\TenantTypeConfig;

/**
 * « Aperçu de la vue » : un administrateur de la plateforme voit le back-office comme le verrait une communauté de
 * type Complet, Bureau des effectifs ou Carte ATAK. Même principe que EffectivePlan : session seulement, communauté en
 * cours seulement, lecture seule (PlanPreviewReadOnlyMiddleware), journal d'audit.
 *
 * L'aperçu ne peut que RESTREINDRE : le type effectif est le plus restreint des deux (réel, simulé). Une communauté
 * Bureau des effectifs ne peut donc pas être « agrandie » en Complet. Les droits (Gate) et les données ne changent pas.
 */
final class EffectiveTenantType
{
    public const SESSION_TYPE = 'view_preview_type';
    public const SESSION_TENANT = 'view_preview_tenant_id';

    /** Du plus restreint au plus large (les modules de chaque type contiennent ceux du précédent). */
    private const RANK = [
        TenantTypeConfig::TYPE_ATAK => 1,
        TenantTypeConfig::TYPE_EFFECTIFS => 2,
        TenantTypeConfig::TYPE_FULL => 3,
    ];

    /** Type à utiliser pour l'affichage : aperçu s'il est actif, permis et plus restreint, sinon le type réel. */
    public static function resolve(string $realType): string
    {
        return self::restrict($realType, self::mayPreview());
    }

    /** Idem sans consulter les droits (middlewares globaux, Gate pas encore chargé) : ne sert qu'à REFUSER. */
    public static function resolveForGuard(string $realType): string
    {
        return self::restrict($realType, true);
    }

    /** Types que cette communauté peut simuler : le sien et les plus restreints. @return list<string> */
    public static function previewable(string $realType): array
    {
        $real = TenantTypeConfig::normalizeType($realType);
        $out = [];
        foreach (self::RANK as $type => $rank) {
            if ($rank <= self::RANK[$real]) {
                $out[] = $type;
            }
        }

        return array_reverse($out);
    }

    public static function isActive(): bool
    {
        return self::mayPreview() && self::sessionMatches();
    }

    /** @return array{type: string, label: string, tenant_id: int}|null */
    public static function current(): ?array
    {
        if (!self::isActive()) {
            return null;
        }
        $type = (string) self::sessionType();

        return ['type' => $type, 'label' => TenantTypeConfig::label($type), 'tenant_id' => (int) Session::get('tenant_id', 0)];
    }

    /** Aperçu présent en session pour la communauté en cours (sans droits : sert seulement à refuser des écritures). */
    public static function sessionMatches(): bool
    {
        $tenantId = (int) Session::get('tenant_id', 0);

        return $tenantId > 0 && self::sessionType() !== null && (int) Session::get(self::SESSION_TENANT, 0) === $tenantId;
    }

    public static function sessionLabel(): string
    {
        return TenantTypeConfig::label((string) self::sessionType());
    }

    public static function mayPreview(): bool
    {
        return Gate::getInstance()->allows('admin.system');
    }

    public static function start(string $type, int $tenantId): void
    {
        Session::set(self::SESSION_TYPE, TenantTypeConfig::normalizeType($type));
        Session::set(self::SESSION_TENANT, $tenantId);
    }

    public static function stop(): void
    {
        Session::forgetMany([self::SESSION_TYPE, self::SESSION_TENANT]);
    }

    private static function restrict(string $realType, bool $allowed): string
    {
        $real = TenantTypeConfig::normalizeType($realType);
        if (!$allowed || !self::sessionMatches()) {
            return $real;
        }
        $preview = (string) self::sessionType();

        return self::RANK[$preview] < self::RANK[$real] ? $preview : $real;
    }

    private static function sessionType(): ?string
    {
        $type = Session::get(self::SESSION_TYPE);

        return is_string($type) && TenantTypeConfig::isValidType($type) ? $type : null;
    }
}
