<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\MemberIntegrationCatalog;
use PDO;
use PDOException;

final class MemberIntegrationAppointmentRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesExist(): bool
    {
        return $this->hasTable('member_integration_appointments')
            && $this->hasTable('member_integration_invitations');
    }

    private function hasTable(string $table): bool
    {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($t === '') {
            return false;
        }
        try {
            $st = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($t));

            return $st !== false && (bool) $st->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    public function findForTenant(int $tenantId, int $id): ?array
    {
        if (!$this->tablesExist() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM member_integration_appointments WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listForIntegration(int $tenantId, int $integrationId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM member_integration_appointments
             WHERE tenant_id = ? AND integration_id = ?
             ORDER BY starts_at ASC'
        );
        $st->execute([$tenantId, $integrationId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function nextUpcomingForIntegration(int $tenantId, int $integrationId): ?array
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM member_integration_appointments
             WHERE tenant_id = ? AND integration_id = ? AND status = ? AND starts_at >= NOW()
             ORDER BY starts_at ASC LIMIT 1'
        );
        $st->execute([$tenantId, $integrationId, MemberIntegrationCatalog::APPT_SCHEDULED]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        if (!$this->tablesExist() || $tenantId < 1) {
            return 0;
        }
        $uid = trim((string) ($data['ics_uid'] ?? ''));
        if ($uid === '') {
            $uid = 'athena-int-' . $tenantId . '-' . bin2hex(random_bytes(8)) . '@athena';
        }
        $st = $this->pdo->prepare(
            'INSERT INTO member_integration_appointments
                (tenant_id, integration_id, step_id, title, description, event_type, starts_at, ends_at, timezone,
                 location, meeting_url, organizer_user_id, max_attendees, linked_course_id, linked_training_session_id,
                 ics_uid, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            !empty($data['integration_id']) ? (int) $data['integration_id'] : null,
            !empty($data['step_id']) ? (int) $data['step_id'] : null,
            mb_substr(trim((string) ($data['title'] ?? 'Rendez-vous')), 0, 180),
            $data['description'] ?? null,
            mb_substr((string) ($data['event_type'] ?? 'custom'), 0, 40),
            (string) $data['starts_at'],
            (string) $data['ends_at'],
            (string) ($data['timezone'] ?? 'UTC'),
            $data['location'] ?? null,
            $data['meeting_url'] ?? null,
            !empty($data['organizer_user_id']) ? (int) $data['organizer_user_id'] : null,
            isset($data['max_attendees']) ? max(0, (int) $data['max_attendees']) : null,
            !empty($data['linked_course_id']) ? (int) $data['linked_course_id'] : null,
            !empty($data['linked_training_session_id']) ? (int) $data['linked_training_session_id'] : null,
            mb_substr($uid, 0, 180),
            (string) ($data['status'] ?? MemberIntegrationCatalog::APPT_SCHEDULED),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function update(int $tenantId, int $id, array $fields): bool
    {
        $allowed = [
            'title', 'description', 'event_type', 'starts_at', 'ends_at', 'timezone', 'location', 'meeting_url',
            'organizer_user_id', 'max_attendees', 'status', 'linked_course_id',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $sets[] = $col . ' = ?';
            $params[] = $fields[$col];
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $tenantId;
        $params[] = $id;
        $st = $this->pdo->prepare(
            'UPDATE member_integration_appointments SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute($params);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createInvitation(int $tenantId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO member_integration_invitations
                (tenant_id, appointment_id, user_id, status, response_token_hash, token_expires_at, personal_message,
                 invited_by, invited_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([
            $tenantId,
            (int) $data['appointment_id'],
            (int) $data['user_id'],
            (string) ($data['status'] ?? MemberIntegrationCatalog::RSVP_PENDING),
            (string) $data['response_token_hash'],
            (string) $data['token_expires_at'],
            $data['personal_message'] ?? null,
            !empty($data['invited_by']) ? (int) $data['invited_by'] : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findInvitationByTokenHash(string $hash): ?array
    {
        if ($hash === '' || !$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT i.*, a.title AS appointment_title, a.starts_at, a.ends_at, a.timezone, a.location, a.meeting_url,
                    a.status AS appointment_status, a.ics_uid, a.max_attendees, a.organizer_user_id, a.integration_id,
                    a.tenant_id AS appointment_tenant_id
             FROM member_integration_invitations i
             INNER JOIN member_integration_appointments a ON a.id = i.appointment_id AND a.tenant_id = i.tenant_id
             WHERE i.response_token_hash = ? LIMIT 1'
        );
        $st->execute([$hash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findInvitation(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM member_integration_invitations WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findInvitationForAppointmentUser(int $tenantId, int $appointmentId, int $userId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM member_integration_invitations
             WHERE tenant_id = ? AND appointment_id = ? AND user_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $appointmentId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listInvitations(int $tenantId, int $appointmentId): array
    {
        $st = $this->pdo->prepare(
            'SELECT i.*, u.display_name, u.callsign, u.email
             FROM member_integration_invitations i
             INNER JOIN users u ON u.id = i.user_id
             WHERE i.tenant_id = ? AND i.appointment_id = ?
             ORDER BY u.display_name ASC'
        );
        $st->execute([$tenantId, $appointmentId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countAccepted(int $tenantId, int $appointmentId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM member_integration_invitations
             WHERE tenant_id = ? AND appointment_id = ? AND status = ?'
        );
        $st->execute([$tenantId, $appointmentId, MemberIntegrationCatalog::RSVP_ACCEPTED]);

        return (int) $st->fetchColumn();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updateInvitation(int $tenantId, int $id, array $fields): bool
    {
        $allowed = [
            'status', 'responded_at', 'response_comment', 'email_sent_at', 'reminded_at', 'revoked_at',
            'token_expires_at', 'response_token_hash',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $sets[] = $col . ' = ?';
            $params[] = $fields[$col];
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $tenantId;
        $params[] = $id;
        $st = $this->pdo->prepare(
            'UPDATE member_integration_invitations SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute($params);
    }

    public function addInvitationHistory(
        int $tenantId,
        int $invitationId,
        ?int $actorUserId,
        ?string $fromStatus,
        string $toStatus,
        ?string $comment
    ): void {
        if (!$this->hasTable('member_integration_invitation_history')) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO member_integration_invitation_history
                (tenant_id, invitation_id, actor_user_id, from_status, to_status, comment, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([$tenantId, $invitationId, $actorUserId, $fromStatus, $toStatus, $comment]);
    }

    /** @return list<array<string, mixed>> */
    public function listPendingForUser(int $tenantId, int $userId): array
    {
        $st = $this->pdo->prepare(
            'SELECT i.*, a.title, a.starts_at, a.ends_at, a.location, a.meeting_url, a.timezone, a.status AS appointment_status
             FROM member_integration_invitations i
             INNER JOIN member_integration_appointments a ON a.id = i.appointment_id AND a.tenant_id = i.tenant_id
             WHERE i.tenant_id = ? AND i.user_id = ? AND i.status = ? AND a.status = ?
             ORDER BY a.starts_at ASC'
        );
        $st->execute([
            $tenantId,
            $userId,
            MemberIntegrationCatalog::RSVP_PENDING,
            MemberIntegrationCatalog::APPT_SCHEDULED,
        ]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listUpcomingForUser(int $tenantId, int $userId): array
    {
        $st = $this->pdo->prepare(
            'SELECT i.*, a.title, a.starts_at, a.ends_at, a.location, a.meeting_url, a.timezone, a.ics_uid, a.status AS appointment_status
             FROM member_integration_invitations i
             INNER JOIN member_integration_appointments a ON a.id = i.appointment_id AND a.tenant_id = i.tenant_id
             WHERE i.tenant_id = ? AND i.user_id = ?
               AND i.status IN (?, ?)
               AND a.status = ? AND a.starts_at >= NOW()
             ORDER BY a.starts_at ASC'
        );
        $st->execute([
            $tenantId,
            $userId,
            MemberIntegrationCatalog::RSVP_ACCEPTED,
            MemberIntegrationCatalog::RSVP_TENTATIVE,
            MemberIntegrationCatalog::APPT_SCHEDULED,
        ]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
