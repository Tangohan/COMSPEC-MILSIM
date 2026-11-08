<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Core\Container;
use App\Core\Database;
use App\Repositories\AuditLogRepository;
use App\Repositories\InviteLinkRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UserRepository;
use App\Support\PlanGift;
use PDO;
use Throwable;

/**
 * Administration des formules : communautés abonnées (formule appliquée, formule offerte comprise), communautés qui
 * dépasseraient un plafond abaissé (avertissement seulement, rien n'est coupé), historique lisible et copie.
 */
final class SubscriptionPlanAdminService
{
    /** Au-delà, le contrôle des dépassements s'arrête (page d'administration, pas un traitement de fond). */
    private const MAX_TENANTS_CHECKED = 400;

    /** @var array<string, list<array{id: int, name: string}>>|null */
    private ?array $subscribers = null;

    /**
     * @param (\Closure(int): array<string, int>)|null $usage consommation d'une communauté par clé de quota (tests)
     * @param (\Closure(): list<array<string, mixed>>)|null $tenants lignes tenants (tests)
     */
    public function __construct(
        private ?PDO $pdo = null,
        private ?\Closure $usage = null,
        private ?\Closure $tenants = null,
    ) {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * Communautés par formule appliquée.
     *
     * @return array<string, list<array{id: int, name: string}>>
     */
    public function subscribersBySlug(): array
    {
        if ($this->subscribers !== null) {
            return $this->subscribers;
        }
        $out = [];
        try {
            if ($this->tenants !== null) {
                $rows = ($this->tenants)();
            } else {
                $st = $this->pdo()->query('SELECT id, name, plan_slug, subscription_status, settings FROM tenants WHERE id > 1 ORDER BY name ASC');
                $rows = $st !== false ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
            }
        } catch (Throwable) {
            $rows = [];
        }
        foreach ($rows ?: [] as $t) {
            $slug = PlanGift::effectivePlanSlug($t);
            $out[$slug][] = ['id' => (int) $t['id'], 'name' => (string) ($t['name'] ?? '')];
        }

        return $this->subscribers = $out;
    }

    /**
     * Communautés abonnées qui dépassent un plafond modifié. Seuls les quotas changés et limités sont contrôlés.
     *
     * @param array<string, bool|int> $before valeurs appliquées avant
     * @param array<string, bool|int> $after valeurs proposées
     *
     * @return list<array{tenant_id: int, tenant_name: string, key: string, label: string, used: int, limit: int}>
     */
    public function overages(string $slug, array $before, array $after): array
    {
        $keys = [];
        foreach (SubscriptionPlanFeaturesCatalog::INT_FEATURES as $key) {
            $new = (int) ($after[$key] ?? 0);
            if ($new > 0 && $new !== (int) ($before[$key] ?? 0)) {
                $keys[] = $key;
            }
        }
        if ($keys === []) {
            return [];
        }
        $defs = SubscriptionPlanFeaturesCatalog::definitions();
        $out = [];
        foreach (array_slice($this->subscribersBySlug()[$slug] ?? [], 0, self::MAX_TENANTS_CHECKED) as $tenant) {
            $used = $this->usageFor($tenant['id']);
            foreach ($keys as $key) {
                $n = (int) ($used[$key] ?? 0);
                if ($n > (int) $after[$key]) {
                    $out[] = [
                        'tenant_id' => $tenant['id'],
                        'tenant_name' => $tenant['name'],
                        'key' => $key,
                        'label' => $defs[$key]['label'],
                        'used' => $n,
                        'limit' => (int) $after[$key],
                    ];
                }
            }
        }

        return $out;
    }

    /** @return array<string, int> */
    private function usageFor(int $tenantId): array
    {
        if ($this->usage !== null) {
            return ($this->usage)($tenantId);
        }
        $used = [];
        $try = static function (callable $fn): int {
            try {
                return (int) $fn();
            } catch (Throwable) {
                return 0;
            }
        };
        $used['max_members'] = $try(fn () => Container::get(UserRepository::class)->countActiveForTenant($tenantId));
        $used['max_orbat_units'] = $try(fn () => Container::get(\App\Repositories\UnitRepository::class)->countForPlanQuota($tenantId));
        $used['max_training_courses'] = $try(fn () => Container::get(TrainingCourseRepository::class)->countTenantCatalogCourses($tenantId));
        $used['max_active_invite_links'] = $try(fn () => (new InviteLinkRepository())->countActiveForTenant($tenantId));
        try {
            $used += QualificationCatalogQuota::countUsed($tenantId, $this->pdo());
        } catch (Throwable) {
        }

        return $used;
    }

    /**
     * Historique des modifications d'une formule (journal d'audit), du plus récent au plus ancien.
     *
     * @return list<array{id: int, at: string, actor: string, action: string, changes: list<array{key: string, label: string, from: string, to: string}>}>
     */
    public function history(int $planId, int $limit = 20): array
    {
        try {
            $rows = (new AuditLogRepository($this->pdo()))->listSystem(
                ['entity_type' => 'subscription_plan', 'entity_id' => $planId],
                1,
                max(5, $limit)
            )['rows'];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $old = json_decode((string) ($r['old_value'] ?? ''), true);
            $new = json_decode((string) ($r['new_value'] ?? ''), true);
            $out[] = [
                'id' => (int) $r['id'],
                'at' => (string) ($r['created_at'] ?? ''),
                'actor' => trim((string) ($r['actor_display_name'] ?? '')) ?: trim((string) ($r['actor_callsign'] ?? '')) ?: '—',
                'action' => (string) ($r['action'] ?? ''),
                'changes' => self::snapshotChanges(is_array($old) ? $old : [], is_array($new) ? $new : []),
            ];
        }

        return $out;
    }

    /**
     * Différences lisibles entre deux instantanés d'audit d'une formule (libellé, ordre, prix, paramètres).
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     *
     * @return list<array{key: string, label: string, from: string, to: string}>
     */
    public static function snapshotChanges(array $old, array $new): array
    {
        $out = [];
        $plain = [
            'name' => 'Libellé',
            'sort_order' => 'Ordre d’affichage',
            'price_monthly_cents' => 'Prix pour 1 mois',
            'price_yearly_cents' => 'Prix pour 1 an',
            'limits_json' => 'Quotas périodiques',
        ];
        foreach ($plain as $k => $label) {
            $a = (string) ($old[$k] ?? '');
            $b = (string) ($new[$k] ?? '');
            if ($a === $b) {
                continue;
            }
            if (str_starts_with($k, 'price_')) {
                $fmt = static fn (string $c): string => (int) $c > 0 ? number_format((int) $c / 100, 2, ',', ' ') . ' €' : '—';
                $a = $fmt($a);
                $b = $fmt($b);
            } elseif ($k === 'limits_json') {
                $a = $a === '' ? '—' : 'JSON';
                $b = $b === '' ? '—' : 'JSON modifié';
            }
            $out[] = ['key' => $k, 'label' => $label, 'from' => $a === '' ? '—' : $a, 'to' => $b === '' ? '—' : $b];
        }
        $decode = static function (mixed $raw): array {
            $d = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

            return is_array($d) ? $d : [];
        };

        return array_merge($out, SubscriptionPlanFeaturesCatalog::diff($decode($old['features_json'] ?? null), $decode($new['features_json'] ?? null)));
    }

    /** Identifiant libre pour une copie : « pro_copie », « pro_copie_2 »… (50 caractères au plus). */
    public static function copySlug(string $slug, callable $exists): string
    {
        $base = substr((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($slug)), 0, 40) . '_copie';
        $candidate = $base;
        for ($n = 2; $exists($candidate); $n++) {
            $candidate = $base . '_' . $n;
        }

        return $candidate;
    }
}
