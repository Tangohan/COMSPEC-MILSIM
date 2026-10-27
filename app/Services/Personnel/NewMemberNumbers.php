<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Container;
use App\Core\Database;
use App\Repositories\UserRepository;
use PDO;
use Throwable;

/**
 * Matricule automatique des comptes créés sur le site (inscription, invitation, ajout par l’état-major)
 * et des membres qui rejoignent la communauté depuis une autre.
 * Jusqu’ici seul le recrutement en attribuait un : les autres comptes restaient sans matricule.
 */
final class NewMemberNumbers
{
    /** Attribue le matricule de service, et le matricule d’organisation si son mode est automatique ou assisté. */
    public static function assign(int $userId, int $tenantId): void
    {
        if ($userId < 1 || $tenantId < 1) {
            return;
        }
        try {
            Container::get(MatriculeService::class)->assignNextForUser($userId, $tenantId);
        } catch (Throwable) {
        }
        try {
            $numbers = Container::get(TenantMemberNumberService::class);
            if (!$numbers->schemaReady() || $numbers->getForUser($tenantId, $userId) !== null) {
                return;
            }
            $cfg = $numbers->getConfig($tenantId);
            if (!empty($cfg['enabled']) && in_array((string) ($cfg['mode'] ?? ''), ['automatic', 'assisted'], true)) {
                $numbers->assignNext($tenantId, $userId, null, 'Création du compte', 'automatic');
            }
        } catch (Throwable) {
        }
    }

    /**
     * Rattrapage sur la communauté : membres actifs (y compris ceux venus d’une autre communauté) encore
     * sans matricule de service, ou sans matricule d’organisation quand celui-ci est en mode automatique.
     *
     * @return int nombre de membres traités
     */
    public static function assignMissing(int $tenantId, int $limit = 200): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $limit = max(1, min(1000, $limit));
        try {
            $users = Container::get(UserRepository::class);
            $member = $users->sqlMemberOfTenantPredicate('u', $tenantId);
            $pdo = Database::getPdo();
            $base = "FROM users u
                 WHERE {$member} AND u.deleted_at IS NULL AND COALESCE(u.is_service_account, 0) = 0";
            $st = $pdo->prepare(
                "SELECT u.id {$base}
                   AND NOT EXISTS (SELECT 1 FROM personnel_extras pe WHERE pe.user_id = u.id AND TRIM(COALESCE(pe.service_number, '')) <> '')
                   AND NOT EXISTS (SELECT 1 FROM personnel_profiles pp WHERE pp.user_id = u.id AND TRIM(COALESCE(pp.matricule_internal, '')) <> '')
                 ORDER BY u.id ASC LIMIT {$limit}"
            );
            $st->execute();
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);

            $numbers = Container::get(TenantMemberNumberService::class);
            if ($numbers->schemaReady()) {
                $cfg = $numbers->getConfig($tenantId);
                if (!empty($cfg['enabled']) && (string) ($cfg['mode'] ?? '') === 'automatic') {
                    $profile = $users->hasCommunityMembershipTable()
                        ? "AND NOT EXISTS (SELECT 1 FROM user_community_profiles ucp
                               WHERE ucp.user_id = u.id AND ucp.tenant_id = {$tenantId} AND TRIM(COALESCE(ucp.tenant_member_number, '')) <> '')"
                        : '';
                    $st = $pdo->prepare(
                        "SELECT u.id {$base}
                           AND NOT (u.tenant_id = {$tenantId} AND TRIM(COALESCE(u.tenant_member_number, '')) <> '')
                           {$profile}
                         ORDER BY u.id ASC LIMIT {$limit}"
                    );
                    $st->execute();
                    $ids = array_merge($ids, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []));
                }
            }
        } catch (Throwable) {
            return 0;
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        foreach ($ids as $id) {
            self::assign($id, $tenantId);
        }

        return count($ids);
    }
}
