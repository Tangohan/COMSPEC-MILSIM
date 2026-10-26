<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Container;
use App\Core\Database;
use PDO;
use Throwable;

/**
 * Matricule automatique des comptes créés sur le site (inscription, invitation, ajout par l’état-major).
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
     * Rattrapage : membres actifs de la communauté encore sans matricule de service (comptes créés avant ce correctif).
     *
     * @return int nombre de matricules attribués
     */
    public static function assignMissing(int $tenantId, int $limit = 200): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        try {
            $st = Database::getPdo()->prepare(
                "SELECT u.id FROM users u
                 LEFT JOIN personnel_extras pe ON pe.user_id = u.id
                 LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
                 WHERE u.tenant_id = ? AND u.deleted_at IS NULL AND COALESCE(u.is_service_account, 0) = 0
                   AND COALESCE(TRIM(pe.service_number), '') = '' AND COALESCE(TRIM(pp.matricule_internal), '') = ''
                 ORDER BY u.id ASC
                 LIMIT " . max(1, min(1000, $limit))
            );
            $st->execute([$tenantId]);
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable) {
            return 0;
        }
        foreach ($ids as $id) {
            self::assign($id, $tenantId);
        }

        return count($ids);
    }
}
