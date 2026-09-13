<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Repositories\Doctrine\DocumentDoctrineRepository;
use App\Repositories\ForumNotificationRepository;
use App\Repositories\DocumentAuditRepository;
use App\Services\Audit\AuditService;

/**
 * Notifications et relances liées aux documents organisationnels.
 */
final class DoctrineNotificationService
{
    public function __construct(
        private DocumentAudienceResolver $audienceResolver,
        private DocumentComplianceService $complianceService,
        private DocumentDoctrineRepository $doctrineRepository,
        private ForumNotificationRepository $forumNotifications,
        private AuditService $auditService,
        private DocumentAuditRepository $documentAuditRepository,
    ) {}

    /**
     * @param array<string, mixed> $doctrine
     */
    public function notifyPublication(int $tenantId, int $actorUserId, array $doctrine, bool $isNewVersion = false): int
    {
        $documentId = (int) ($doctrine['document_id'] ?? 0);
        if ($documentId < 1 || $tenantId < 1) {
            return 0;
        }

        $userIds = $this->audienceResolver->resolveUserIds($tenantId, $documentId);
        $reference = (string) ($doctrine['reference_code'] ?? '');
        $title = (string) ($doctrine['title'] ?? $doctrine['short_title'] ?? $reference);
        $deadline = $doctrine['acknowledgment_deadline_at'] ?? null;
        $mandatory = !empty($doctrine['reading_required']) || !empty($doctrine['acknowledgment_required']);

        $headline = $isNewVersion
            ? 'Nouvelle version d’un document'
            : 'Nouveau document publié';
        if ($mandatory) {
            $headline = $isNewVersion
                ? 'Nouvelle version à prendre en compte'
                : 'Document à lecture obligatoire';
        }

        $body = $reference !== '' ? ($reference . ' — ' . $title) : $title;
        if ($deadline) {
            $ts = strtotime((string) $deadline);
            if ($ts !== false) {
                $body .= ' · À lire avant le ' . date('d/m/Y à H:i', $ts);
            }
        }

        $count = 0;
        foreach ($userIds as $uid) {
            if ($uid === $actorUserId) {
                continue;
            }
            $id = $this->forumNotifications->create($tenantId, $uid, 'document_publication', [
                'headline' => $headline,
                'body' => $body,
                'document_id' => $documentId,
                'reference' => $reference,
                'href' => url('documents/doctrine/' . $documentId),
                'mandatory' => $mandatory,
                'deadline_at' => $deadline,
            ]);
            if ($id !== null) {
                ++$count;
            }
        }

        $this->recordReminder($tenantId, $documentId, $actorUserId, 'on_publish', $count, $isNewVersion ? 'Nouvelle version' : 'Publication');
        $this->documentAuditRepository->log($documentId, $actorUserId, 'recipients_notified', null, [
            'count' => $count,
            'event' => $isNewVersion ? 'new_version' : 'publish',
        ]);
        $this->auditService->log(
            'document.recipients_notified',
            $tenantId,
            $actorUserId,
            'document',
            $documentId,
            null,
            json_encode(['count' => $count], JSON_UNESCAPED_UNICODE)
        );

        return $count;
    }

    /**
     * Relance les destinataires non conformes (en retard / à signer / non lus obligatoires).
     *
     * @return array{ok: bool, error?: string, count?: int}
     */
    public function sendReminders(int $tenantId, int $actorUserId, int $documentId, string $note = ''): array
    {
        $doctrine = $this->doctrineRepository->findByDocumentId($documentId, $tenantId);
        if ($doctrine === null) {
            return ['ok' => false, 'error' => 'Document introuvable.'];
        }

        $doc = $this->doctrineRepository->listPublishedForTenant($tenantId);
        $row = null;
        foreach ($doc as $d) {
            if ((int) ($d['document_id'] ?? 0) === $documentId) {
                $row = $d;
                break;
            }
        }
        if ($row === null) {
            $row = $doctrine;
        }

        $versionId = (int) ($row['version_id'] ?? 0);
        $userIds = $this->audienceResolver->resolveUserIds($tenantId, $documentId);
        $reference = (string) ($doctrine['reference_code'] ?? '');
        $title = (string) ($row['title'] ?? $doctrine['short_title'] ?? $reference);
        $deadline = $doctrine['acknowledgment_deadline_at'] ?? null;

        $count = 0;
        foreach ($userIds as $uid) {
            if ($versionId < 1) {
                continue;
            }
            $badge = $this->complianceService->memberBadge($tenantId, $uid, $row, $versionId);
            if (!in_array($badge['badge'], ['ACK_REQUIRED', 'ACK_OUTDATED', 'OVERDUE', 'UNREAD'], true)) {
                continue;
            }
            $headline = $badge['badge'] === 'OVERDUE'
                ? 'Lecture en retard'
                : 'Rappel de lecture';
            $body = ($reference !== '' ? $reference . ' — ' : '') . $title;
            if ($deadline) {
                $ts = strtotime((string) $deadline);
                if ($ts !== false) {
                    $body .= ' · Échéance le ' . date('d/m/Y à H:i', $ts);
                }
            }
            if ($note !== '') {
                $body .= ' · ' . $note;
            }
            $id = $this->forumNotifications->create($tenantId, $uid, 'document_reminder', [
                'headline' => $headline,
                'body' => $body,
                'document_id' => $documentId,
                'reference' => $reference,
                'href' => url('documents/doctrine/' . $documentId),
                'status' => $badge['badge'],
            ]);
            if ($id !== null) {
                ++$count;
            }
        }

        $this->recordReminder($tenantId, $documentId, $actorUserId, 'manual', $count, $note);
        $this->documentAuditRepository->log($documentId, $actorUserId, 'reminder_sent', null, ['count' => $count]);

        return ['ok' => true, 'count' => $count];
    }

    private function recordReminder(
        int $tenantId,
        int $documentId,
        int $sentBy,
        string $scope,
        int $recipientCount,
        string $note = '',
    ): void {
        try {
            $pdo = \App\Core\Database::getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO document_doctrine_reminders (tenant_id, document_id, sent_by_user_id, target_scope, recipient_count, note)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$tenantId, $documentId, $sentBy, $scope, $recipientCount, $note !== '' ? $note : null]);
        } catch (\Throwable) {
            // table absente : ignorer
        }
    }
}
