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
     * Accusés et vues préchargés pour un membre (primeForUser), le temps d’une passe sur toutes les doctrines.
     *
     * @var array{tenant_id: int, user_id: int, ack_by_version: array<int, array<string, mixed>>, latest_ack_by_document: array<int, array<string, mixed>>, viewed_versions: array<int, true>}|null
     */
    private ?array $primed = null;

    /**
     * Précharge en quelques requêtes les accusés, les vues et les audiences d’un membre, pour éviter
     * 6 à 8 requêtes par doctrine publiée. À libérer avec releasePrimed() (try/finally) : les données
     * ne reflètent pas une écriture faite pendant la passe.
     *
     * @param list<int> $documentIds
     */
    public function primeForUser(int $tenantId, int $userId, array $documentIds): void
    {
        if ($tenantId < 1 || $userId < 1) {
            return;
        }
        $ackByVersion = [];
        $latestByDocument = [];
        // Trié par signed_at DESC : la première ligne d’un document est la plus récente.
        foreach ($this->acknowledgmentRepository->listForUser($tenantId, $userId) as $ack) {
            $vid = (int) ($ack['version_id'] ?? 0);
            $did = (int) ($ack['document_id'] ?? 0);
            if ($vid > 0 && !isset($ackByVersion[$vid])) {
                $ackByVersion[$vid] = $ack;
            }
            if ($did > 0 && !isset($latestByDocument[$did])) {
                $latestByDocument[$did] = $ack;
            }
        }
        $this->primed = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'ack_by_version' => $ackByVersion,
            'latest_ack_by_document' => $latestByDocument,
            'viewed_versions' => array_fill_keys($this->viewRepository->listViewedVersionIdsForUser($tenantId, $userId), true),
        ];
        $this->audienceResolver->primeAudiences($tenantId, $documentIds);
    }

    public function releasePrimed(): void
    {
        $this->primed = null;
        $this->audienceResolver->releasePrimedAudiences();
    }

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

        $primed = $this->primed;
        if ($primed !== null && $primed['tenant_id'] === $tenantId && $primed['user_id'] === $userId) {
            $latestAck = $primed['latest_ack_by_document'][$documentId] ?? null;
            $viewed = isset($primed['viewed_versions'][$currentVersionId]);
            $ackCurrent = $primed['ack_by_version'][$currentVersionId] ?? null;
        } else {
            $latestAck = $this->acknowledgmentRepository->findLatestForUserDocument($tenantId, $userId, $documentId);
            $viewed = $this->viewRepository->hasViewed($tenantId, $userId, $currentVersionId);
            $ackCurrent = $this->acknowledgmentRepository->findForUserVersion($tenantId, $userId, $currentVersionId);
        }

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
            $item = $this->pendingActionItem($row, $badge);
            if ($item !== null) {
                $pending[] = $item;
            }
        }

        return $this->sortAndSlicePending($pending, $limit);
    }

    /**
     * Élément « action en attente » pour une doctrine publiée, ou null si le badge n’en demande pas.
     *
     * @param array<string, mixed> $row
     * @param array{badge: string, label: string, tone: string, sort_priority: int} $badge
     * @return array<string, mixed>|null
     */
    public function pendingActionItem(array $row, array $badge): ?array
    {
        $documentId = (int) ($row['document_id'] ?? 0);
        $versionId = (int) ($row['version_id'] ?? 0);
        if ($documentId < 1 || $versionId < 1) {
            return null;
        }
        if (!in_array($badge['badge'], [
            DoctrineComplianceStatus::ACK_REQUIRED,
            DoctrineComplianceStatus::ACK_OUTDATED,
            DoctrineComplianceStatus::OVERDUE,
            DoctrineComplianceStatus::UNREAD,
        ], true)) {
            return null;
        }

        return [
            'document_id' => $documentId,
            'reference' => (string) ($row['reference_code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'badge' => $badge,
            'deadline_label' => $this->deadlineLabel($row['acknowledgment_deadline_at'] ?? null),
            'deadline_at' => $row['acknowledgment_deadline_at'] ?? null,
            'version_id' => $versionId,
            'version_label' => $this->versionLabelForRow($row),
            'mandatory' => (string) ($row['requirement_level'] ?? '') === 'mandatory',
            'needs_signature' => !empty($row['acknowledgment_required']),
            'summary' => trim((string) ($row['summary'] ?? '')),
            'href' => \url('documents/doctrine/' . $documentId),
            'ack_url' => \url('documents/doctrine/' . $documentId . '/acknowledge'),
            'sort_priority' => (int) ($badge['sort_priority'] ?? 8),
        ];
    }

    /**
     * @param list<array<string, mixed>> $pending
     * @return list<array<string, mixed>>
     */
    public function sortAndSlicePending(array $pending, int $limit): array
    {
        usort($pending, static fn (array $a, array $b): int => ($a['sort_priority'] <=> $b['sort_priority']) ?: strcmp($a['reference'], $b['reference']));

        return array_slice($pending, 0, max(1, $limit));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function versionLabelForRow(array $row): string
    {
        $label = trim((string) ($row['version_label'] ?? ''));
        if ($label === '' && isset($row['version_major'], $row['version_minor'])) {
            $label = 'v' . (int) $row['version_major'] . '.' . (int) $row['version_minor'];
        }

        return $label;
    }
}
