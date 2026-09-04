<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Repositories\Doctrine\DocumentAcknowledgmentRepository;
use App\Repositories\Doctrine\DocumentViewRepository;
use App\Support\Doctrine\DoctrineComplianceStatus;
use App\Support\Doctrine\DoctrineWorkflowStatus;

/**
 * Statut de conformité membre ↔ doctrine (logique centralisée).
 */
final class DocumentComplianceService
{
    public function __construct(
        private DocumentAudienceResolver $audienceResolver,
        private DocumentAcknowledgmentRepository $acknowledgmentRepository,
        private DocumentViewRepository $viewRepository,
        private \App\Repositories\Doctrine\DocumentDoctrineRepository $doctrineRepository,
    ) {}

    /**
     * @param array<string, mixed> $doctrine Doctrine row with version_id, acknowledgment_required, etc.
     */
    public function statusForUser(int $tenantId, int $userId, array $doctrine, int $currentVersionId): string
    {
        $documentId = (int) ($doctrine['document_id'] ?? 0);
        if ($documentId < 1 || $currentVersionId < 1) {
            return DoctrineComplianceStatus::NOT_APPLICABLE;
        }

        if ((string) ($doctrine['doctrine_status'] ?? '') !== DoctrineWorkflowStatus::PUBLISHED) {
            return DoctrineComplianceStatus::NOT_APPLICABLE;
        }

        if (!$this->audienceResolver->isUserInAudience($tenantId, $userId, $documentId, $doctrine)) {
            return DoctrineComplianceStatus::NOT_APPLICABLE;
        }

        $requirement = (string) ($doctrine['requirement_level'] ?? 'informative');
        $ackRequired = !empty($doctrine['acknowledgment_required']) || $requirement === 'mandatory';

        $latestAck = $this->acknowledgmentRepository->findLatestForUserDocument($tenantId, $userId, $documentId);
        $viewed = $this->viewRepository->hasViewed($tenantId, $userId, $currentVersionId);
        $ackCurrent = $this->acknowledgmentRepository->findForUserVersion($tenantId, $userId, $currentVersionId);

        if ($ackRequired) {
            if ($ackCurrent !== null) {
                $deadline = $doctrine['acknowledgment_deadline_at'] ?? null;
                if ($this->isOverdue($deadline) && strtotime((string) $ackCurrent['signed_at']) > strtotime((string) $deadline)) {
                    return DoctrineComplianceStatus::OVERDUE;
                }

                return DoctrineComplianceStatus::ACKNOWLEDGED;
            }
            if ($latestAck !== null && (int) ($latestAck['version_id'] ?? 0) !== $currentVersionId) {
                $deadline = $doctrine['acknowledgment_deadline_at'] ?? null;
                if ($this->isOverdue($deadline)) {
                    return DoctrineComplianceStatus::OVERDUE;
                }

                return DoctrineComplianceStatus::ACK_OUTDATED;
            }
            $deadline = $doctrine['acknowledgment_deadline_at'] ?? null;
            if ($this->isOverdue($deadline)) {
                return DoctrineComplianceStatus::OVERDUE;
            }

            return DoctrineComplianceStatus::ACK_REQUIRED;
        }

        if ($viewed) {
            return DoctrineComplianceStatus::READ;
        }

        return DoctrineComplianceStatus::UNREAD;
    }

    /** @return array{badge: string, label: string, tone: string, sort_priority: int} */
    public function memberBadge(int $tenantId, int $userId, array $doctrine, int $currentVersionId): array
    {
        $status = $this->statusForUser($tenantId, $userId, $doctrine, $currentVersionId);
        $tones = [
            DoctrineComplianceStatus::NOT_APPLICABLE => 'neutral',
            DoctrineComplianceStatus::UNREAD => 'amber',
            DoctrineComplianceStatus::READ => 'slate',
            DoctrineComplianceStatus::ACK_REQUIRED => 'rose',
            DoctrineComplianceStatus::ACKNOWLEDGED => 'emerald',
            DoctrineComplianceStatus::ACK_OUTDATED => 'violet',
            DoctrineComplianceStatus::OVERDUE => 'red',
        ];
        $priorities = [
            DoctrineComplianceStatus::OVERDUE => 0,
            DoctrineComplianceStatus::ACK_OUTDATED => 1,
            DoctrineComplianceStatus::ACK_REQUIRED => 2,
            DoctrineComplianceStatus::UNREAD => 3,
            DoctrineComplianceStatus::READ => 4,
            DoctrineComplianceStatus::ACKNOWLEDGED => 5,
            DoctrineComplianceStatus::NOT_APPLICABLE => 9,
        ];

        return [
            'badge' => $status,
            'label' => DoctrineComplianceStatus::label($status),
            'tone' => $tones[$status] ?? 'neutral',
            'sort_priority' => $priorities[$status] ?? 8,
        ];
    }

    public function deadlineLabel(?string $deadlineAt): ?string
    {
        if ($deadlineAt === null || trim($deadlineAt) === '') {
            return null;
        }
        $ts = strtotime($deadlineAt);
        if ($ts === false) {
            return null;
        }
        $days = (int) floor(($ts - time()) / 86400);
        if ($days < 0) {
            return 'Échéance dépassée';
        }
        if ($days === 0) {
            return 'Échéance aujourd’hui';
        }

        return 'À prendre en compte sous ' . $days . ' jour' . ($days > 1 ? 's' : '');
    }

    private function isOverdue(?string $deadlineAt): bool
    {
        if ($deadlineAt === null || trim($deadlineAt) === '') {
            return false;
        }
        $ts = strtotime($deadlineAt);

        return $ts !== false && time() > $ts;
    }

    /**
     * @return list<array{document_id: int, reference: string, title: string, badge: array<string, mixed>, deadline_label: ?string, href: string, sort_priority: int}>
     */
    public function listPendingActionsForUser(int $tenantId, int $userId, int $limit = 6): array
    {
        if ($tenantId < 1 || $userId < 1) {
            return [];
        }
        $repo = $this->doctrineRepository;
        if (!$repo->tableExists()) {
            return [];
        }
        $rows = $repo->listPublishedForTenant($tenantId);
        $pending = [];
        foreach ($rows as $row) {
            $documentId = (int) ($row['document_id'] ?? 0);
            $versionId = (int) ($row['version_id'] ?? 0);
            if ($documentId < 1 || $versionId < 1) {
                continue;
            }
            $badge = $this->memberBadge($tenantId, $userId, $row, $versionId);
            if (!in_array($badge['badge'], [
                DoctrineComplianceStatus::ACK_REQUIRED,
                DoctrineComplianceStatus::ACK_OUTDATED,
                DoctrineComplianceStatus::OVERDUE,
                DoctrineComplianceStatus::UNREAD,
            ], true)) {
                continue;
            }
            $pending[] = [
                'document_id' => $documentId,
                'reference' => (string) ($row['reference_code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'badge' => $badge,
                'deadline_label' => $this->deadlineLabel($row['acknowledgment_deadline_at'] ?? null),
                'href' => \url('documents/doctrine/' . $documentId),
                'sort_priority' => (int) ($badge['sort_priority'] ?? 8),
            ];
        }
        usort($pending, static fn (array $a, array $b): int => ($a['sort_priority'] <=> $b['sort_priority']) ?: strcmp($a['reference'], $b['reference']));

        return array_slice($pending, 0, max(1, $limit));
    }
}
