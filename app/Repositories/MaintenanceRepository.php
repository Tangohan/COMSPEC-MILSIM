<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class MaintenanceRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_maintenance' LIMIT 1"
        );

        return (bool) $stmt?->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM app_maintenance ORDER BY priority DESC, updated_at DESC'
        );
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAuditFor(int $maintenanceId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM app_maintenance_audit WHERE maintenance_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $maintenanceId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM app_maintenance WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, ?int $actorUserId, ?string $actorIp): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO app_maintenance (
                    scope, is_enabled, title, message, maintenance_code,
                    starts_at, ends_at, allow_admin_bypass, allowed_ips, allowed_roles,
                    allowed_user_ids, message_preset, ui_variant, ui_animation,
                    notify_members_by_email, notify_email_subject, notify_email_message,
                    redirect_url, http_status, priority, created_by, updated_by
                ) VALUES (
                    :scope, :is_enabled, :title, :message, :maintenance_code,
                    :starts_at, :ends_at, :allow_admin_bypass, :allowed_ips, :allowed_roles,
                    :allowed_user_ids, :message_preset, :ui_variant, :ui_animation,
                    :notify_members_by_email, :notify_email_subject, :notify_email_message,
                    :redirect_url, :http_status, :priority, :created_by, :updated_by
                )'
            );
            $stmt->execute([
                'scope' => $data['scope'],
                'is_enabled' => (int) ($data['is_enabled'] ?? 0),
                'title' => $data['title'] ?? 'Maintenance en cours',
                'message' => $data['message'] ?? null,
                'maintenance_code' => $data['maintenance_code'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'allow_admin_bypass' => (int) ($data['allow_admin_bypass'] ?? 1),
                'allowed_ips' => $data['allowed_ips'] ?? null,
                'allowed_roles' => $data['allowed_roles'] ?? null,
                'allowed_user_ids' => $data['allowed_user_ids'] ?? null,
                'message_preset' => $data['message_preset'] ?? null,
                'ui_variant' => $data['ui_variant'] ?? 'military',
                'ui_animation' => (int) ($data['ui_animation'] ?? 1),
                'notify_members_by_email' => (int) ($data['notify_members_by_email'] ?? 0),
                'notify_email_subject' => $data['notify_email_subject'] ?? null,
                'notify_email_message' => $data['notify_email_message'] ?? null,
                'redirect_url' => $data['redirect_url'] ?? null,
                'http_status' => (int) ($data['http_status'] ?? 503),
                'priority' => (int) ($data['priority'] ?? 100),
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $new = $this->findById($id);
            if ($new) {
                $this->insertAuditRow($id, 'create', null, $new, $actorUserId, $actorIp);
            }
            $this->pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, ?int $actorUserId, ?string $actorIp): void
    {
        $old = $this->findById($id);
        if (!$old) {
            throw new RuntimeException('Règle de maintenance introuvable.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE app_maintenance SET
                    scope = :scope,
                    is_enabled = :is_enabled,
                    title = :title,
                    message = :message,
                    maintenance_code = :maintenance_code,
                    starts_at = :starts_at,
                    ends_at = :ends_at,
                    allow_admin_bypass = :allow_admin_bypass,
                    allowed_ips = :allowed_ips,
                    allowed_roles = :allowed_roles,
                    allowed_user_ids = :allowed_user_ids,
                    message_preset = :message_preset,
                    ui_variant = :ui_variant,
                    ui_animation = :ui_animation,
                    notify_members_by_email = :notify_members_by_email,
                    notify_email_subject = :notify_email_subject,
                    notify_email_message = :notify_email_message,
                    redirect_url = :redirect_url,
                    http_status = :http_status,
                    priority = :priority,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'scope' => $data['scope'],
                'is_enabled' => (int) ($data['is_enabled'] ?? 0),
                'title' => $data['title'] ?? 'Maintenance en cours',
                'message' => $data['message'] ?? null,
                'maintenance_code' => $data['maintenance_code'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'allow_admin_bypass' => (int) ($data['allow_admin_bypass'] ?? 1),
                'allowed_ips' => $data['allowed_ips'] ?? null,
                'allowed_roles' => $data['allowed_roles'] ?? null,
                'allowed_user_ids' => $data['allowed_user_ids'] ?? null,
                'message_preset' => $data['message_preset'] ?? null,
                'ui_variant' => $data['ui_variant'] ?? 'military',
                'ui_animation' => (int) ($data['ui_animation'] ?? 1),
                'notify_members_by_email' => (int) ($data['notify_members_by_email'] ?? 0),
                'notify_email_subject' => $data['notify_email_subject'] ?? null,
                'notify_email_message' => $data['notify_email_message'] ?? null,
                'redirect_url' => $data['redirect_url'] ?? null,
                'http_status' => (int) ($data['http_status'] ?? 503),
                'priority' => (int) ($data['priority'] ?? 100),
                'updated_by' => $actorUserId,
            ]);
            $new = $this->findById($id);
            if ($new) {
                $this->insertAuditRow($id, 'update', $old, $new, $actorUserId, $actorIp);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function setEnabled(int $id, bool $enabled, ?int $actorUserId, ?string $actorIp): void
    {
        $old = $this->findById($id);
        if (!$old) {
            throw new RuntimeException('Règle de maintenance introuvable.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE app_maintenance SET is_enabled = ?, updated_by = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([(int) $enabled, $actorUserId, $id]);
            $new = $this->findById($id);
            if ($new) {
                $this->insertAuditRow(
                    $id,
                    $enabled ? 'enable' : 'disable',
                    $old,
                    $new,
                    $actorUserId,
                    $actorIp
                );
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id, ?int $actorUserId, ?string $actorIp): void
    {
        $old = $this->findById($id);
        if (!$old) {
            throw new RuntimeException('Règle de maintenance introuvable.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->insertAuditRow($id, 'delete', $old, null, $actorUserId, $actorIp);
            $stmt = $this->pdo->prepare('DELETE FROM app_maintenance WHERE id = ?');
            $stmt->execute([$id]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    private function insertAuditRow(
        int $maintenanceId,
        string $actionType,
        ?array $old,
        ?array $new,
        ?int $actorUserId,
        ?string $actorIp
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_maintenance_audit
            (maintenance_id, action_type, old_values, new_values, actor_user_id, actor_ip)
            VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $maintenanceId,
            $actionType,
            $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $actorUserId,
            $actorIp,
        ]);
    }
}
