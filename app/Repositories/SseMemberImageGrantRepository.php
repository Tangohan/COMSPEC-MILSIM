<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Demandes d'accès et dérogations datées aux images confidentielles d'un membre (table sse_member_image_grants, étape 4650).
 *
 * Toute lecture est bornée à la communauté. Table absente ou illisible : aucune dérogation (fail-closed) ; l'appelant
 * décide de ce qu'il fait d'une écriture impossible.
 */
final class SseMemberImageGrantRepository
{
    public const PENDING = 'pending';
    public const GRANTED = 'granted';
    public const DENIED = 'denied';
    public const REVOKED = 'revoked';

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Dérogation en cours : accordée, commencée, non terminée (une date de fin est obligatoire), non retirée.
     *
     * @return array<string, mixed>|null
     */
    public function activeGrant(int $tenantId, int $ownerUserId, int $granteeUserId, string $now): ?array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM sse_member_image_grants WHERE tenant_id = :t AND owner_user_id = :o AND grantee_user_id = :g AND status = 'granted'
                   AND ends_at IS NOT NULL AND ends_at > :now AND (starts_at IS NULL OR starts_at <= :now2) ORDER BY id DESC",
                ['t' => $tenantId, 'o' => $ownerUserId, 'g' => $granteeUserId, 'now' => $now, 'now2' => $now]
            );
        } catch (\Throwable) {
            return null;
        }

        return $rows[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function pending(int $tenantId, int $ownerUserId, int $granteeUserId): ?array
    {
        try {
            return $this->db->fetchOne(
                "SELECT * FROM sse_member_image_grants WHERE tenant_id = :t AND owner_user_id = :o AND grantee_user_id = :g AND status = 'pending' ORDER BY id DESC LIMIT 1",
                ['t' => $tenantId, 'o' => $ownerUserId, 'g' => $granteeUserId]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        try {
            return $this->db->fetchOne('SELECT * FROM sse_member_image_grants WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return int|null identifiant, null si la table est absente */
    public function addRequest(int $tenantId, int $ownerUserId, int $granteeUserId, string $reason, string $now): ?int
    {
        try {
            return (int) $this->db->insert(
                "INSERT INTO sse_member_image_grants (tenant_id, owner_user_id, grantee_user_id, status, reason, created_at) VALUES (:t, :o, :g, 'pending', :r, :now)",
                ['t' => $tenantId, 'o' => $ownerUserId, 'g' => $granteeUserId, 'r' => mb_substr($reason, 0, 500), 'now' => $now]
            );
        } catch (\Throwable $e) {
            error_log('Images des membres : demande non enregistrée : ' . $e->getMessage());

            return null;
        }
    }

    public function decide(int $tenantId, int $id, string $status, int $deciderId, string $reason, ?string $startsAt, ?string $endsAt, string $now): bool
    {
        try {
            return $this->db->execute(
                "UPDATE sse_member_image_grants SET status = :s, decided_by_user_id = :d, decided_at = :now, reason = :r, starts_at = :st, ends_at = :en
                  WHERE id = :id AND tenant_id = :t AND status IN ('pending', 'granted')",
                ['s' => $status, 'd' => $deciderId, 'now' => $now, 'r' => mb_substr($reason, 0, 500), 'st' => $startsAt, 'en' => $endsAt, 'id' => $id, 't' => $tenantId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> demandes en attente de la communauté (la décision reste à la hiérarchie du membre) */
    public function pendingForTenant(int $tenantId, int $limit = 200): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM sse_member_image_grants WHERE tenant_id = :t AND status = 'pending' ORDER BY id DESC LIMIT " . max(1, min(500, $limit)),
                ['t' => $tenantId]
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
