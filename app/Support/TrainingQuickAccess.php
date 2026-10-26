<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Core\Session;
use App\Repositories\TrainingEnrollmentRepository;
use App\Services\Moderation\ModerationRestrictionResolver;
use App\Services\Moderation\ModerationRestrictionsCatalog;
use App\Services\Platform\FeatureGateService;

/**
 * Accès direct à « Mes formations » (/formations/mes-formations) depuis le hub et le rail du tableau de bord.
 * Même conditions que la route : module Formations inclus dans la formule de la communauté, pas de restriction
 * de modération sur le module, page autorisée pour le profil de communauté et les droits de navigation.
 */
final class TrainingQuickAccess
{
    private static ?bool $allowed = null;

    public static function allowed(): bool
    {
        if (self::$allowed !== null) {
            return self::$allowed;
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return self::$allowed = false;
        }
        try {
            if (function_exists('portal_nav_href_permission_allowed') && !portal_nav_href_permission_allowed(url('formations/mes-formations'))) {
                return self::$allowed = false;
            }
            if (!Container::get(FeatureGateService::class)->allows($tenantId, 'training')) {
                return self::$allowed = false;
            }
            if (!Container::get(ModerationRestrictionResolver::class)->isModuleAllowed($tenantId, $userId, ModerationRestrictionsCatalog::KEY_TRAINING)) {
                return self::$allowed = false;
            }
        } catch (\Throwable) {
            return self::$allowed = false;
        }

        return self::$allowed = true;
    }

    /**
     * Résumé des inscriptions du membre (sans calcul de progression par module, une seule requête).
     *
     * @return array{total: int, in_progress: int, assigned: int, completed: int, pending: int, pct: int, next: ?array{title: string, url: string, status: string}}|null
     */
    public static function summary(): ?array
    {
        if (!self::allowed()) {
            return null;
        }
        try {
            $rows = Container::get(TrainingEnrollmentRepository::class)->listByUserId((int) Session::get('user_id'), (int) Session::get('tenant_id'));
        } catch (\Throwable) {
            $rows = [];
        }

        return self::summarize($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{total: int, in_progress: int, assigned: int, completed: int, pending: int, pct: int, next: ?array{title: string, url: string, status: string}}
     */
    public static function summarize(array $rows): array
    {
        $count = static fn (string $st): int => count(array_filter($rows, static fn (array $r): bool => ($r['status'] ?? '') === $st));
        $relevant = array_values(array_filter($rows, static fn (array $r): bool => !in_array($r['status'] ?? '', ['withdrawn', 'revoked'], true)));
        $completed = $count('completed');
        $next = null;
        foreach (['in_progress', 'assigned', 'pending_approval'] as $st) {
            foreach ($rows as $r) {
                if (($r['status'] ?? '') === $st && trim((string) ($r['course_title'] ?? '')) !== '') {
                    $slug = (string) ($r['course_slug'] ?? '');
                    $next = [
                        'title' => (string) $r['course_title'],
                        'url' => $slug !== '' ? url('formations/' . rawurlencode($slug)) : url('formations/mes-formations'),
                        'status' => $st,
                    ];
                    break 2;
                }
            }
        }

        return [
            'total' => count($relevant),
            'in_progress' => $count('in_progress'),
            'assigned' => $count('assigned'),
            'completed' => $completed,
            'pending' => $count('pending_approval'),
            'pct' => count($relevant) > 0 ? (int) round($completed / count($relevant) * 100) : 0,
            'next' => $next,
        ];
    }
}
