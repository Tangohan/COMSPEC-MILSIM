<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Repositories\Doctrine\DocumentDoctrineRepository;
use App\Repositories\Doctrine\DocumentTypeRepository;
use App\Repositories\Doctrine\DocumentViewRepository;
use App\Repositories\Doctrine\DocumentAcknowledgmentRepository;
use App\Support\Doctrine\DoctrineComplianceStatus;
use App\Support\Doctrine\DoctrineWorkflowStatus;

/**
 * Boîte personnelle « Mes documents » : à lire, obligatoires, lus, archivés, en retard.
 */
final class DocumentInboxService
{
    public function __construct(
        private DocumentDoctrineRepository $doctrineRepository,
        private DocumentComplianceService $complianceService,
        private DocumentAudienceResolver $audienceResolver,
        private DocumentTypeRepository $typeRepository,
        private DocumentViewRepository $viewRepository,
        private DocumentAcknowledgmentRepository $acknowledgmentRepository,
    ) {}

    /**
     * @return array{
     *   counts: array<string, int>,
     *   items: list<array<string, mixed>>
     * }
     */
    public function forUser(int $tenantId, int $userId, string $filter = 'a_lire'): array
    {
        $filter = $this->normalizeFilter($filter);
        $published = $this->doctrineRepository->listPublishedForTenant($tenantId);
        $typesById = [];
        foreach ($this->typeRepository->listForTenant($tenantId, false) as $t) {
            $typesById[(int) $t['id']] = $t;
        }

        $counts = [
            'a_lire' => 0,
            'obligatoires' => 0,
            'en_retard' => 0,
            'lus' => 0,
            'archives' => 0,
        ];
        $items = [];

        foreach ($published as $row) {
            $documentId = (int) ($row['document_id'] ?? 0);
            $versionId = (int) ($row['version_id'] ?? 0);
            if ($documentId < 1 || $versionId < 1) {
                continue;
            }
            if (!$this->audienceResolver->isUserInAudience($tenantId, $userId, $documentId, $row)) {
                continue;
            }

            $badge = $this->complianceService->memberBadge($tenantId, $userId, $row, $versionId);
            $status = $badge['badge'];
            $mandatory = !empty($row['reading_required'])
                || !empty($row['acknowledgment_required'])
                || (string) ($row['requirement_level'] ?? '') === 'mandatory';
            $isArchived = in_array((string) ($row['doctrine_status'] ?? ''), [
                DoctrineWorkflowStatus::ARCHIVED,
                DoctrineWorkflowStatus::OBSOLETE,
            ], true);
            // listPublished ne renvoie que published — archives via filtre dédié plus bas
            $isRead = in_array($status, [
                DoctrineComplianceStatus::READ,
                DoctrineComplianceStatus::ACKNOWLEDGED,
            ], true);
            $isOverdue = $status === DoctrineComplianceStatus::OVERDUE;
            $needsAction = in_array($status, [
                DoctrineComplianceStatus::UNREAD,
                DoctrineComplianceStatus::ACK_REQUIRED,
                DoctrineComplianceStatus::ACK_OUTDATED,
                DoctrineComplianceStatus::OVERDUE,
            ], true);

            if ($needsAction) {
                ++$counts['a_lire'];
            }
            if ($mandatory && $needsAction) {
                ++$counts['obligatoires'];
            }
            if ($isOverdue) {
                ++$counts['en_retard'];
            }
            if ($isRead) {
                ++$counts['lus'];
            }

            $include = match ($filter) {
                'obligatoires' => $mandatory && $needsAction,
                'en_retard' => $isOverdue,
                'lus' => $isRead,
                'archives' => false, // rempli séparément
                default => $needsAction,
            };
            if (!$include) {
                continue;
            }

            $typeId = (int) ($row['document_type_id'] ?? 0);
            $type = $typesById[$typeId] ?? null;
            $view = $this->viewRepository->findForUserVersion($tenantId, $userId, $versionId);
            $ack = $this->acknowledgmentRepository->findForUserVersion($tenantId, $userId, $versionId);

            $items[] = [
                'document_id' => $documentId,
                'reference' => (string) ($row['reference_code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'type_label' => (string) ($type['label'] ?? 'Document'),
                'type_color' => (string) ($type['color'] ?? '#64748b'),
                'mandatory' => $mandatory,
                'badge' => $badge,
                'deadline_label' => $this->complianceService->deadlineLabel($row['acknowledgment_deadline_at'] ?? null),
                'deadline_at' => $row['acknowledgment_deadline_at'] ?? null,
                'first_viewed_at' => $view['first_viewed_at'] ?? null,
                'acknowledged_at' => $ack['signed_at'] ?? null,
                'version_label' => $this->versionLabel($row),
                'href' => url('documents/doctrine/' . $documentId),
                'issuing_label' => (string) ($row['issuing_label'] ?? ''),
                'published_at' => $row['published_at'] ?? $row['version_published_at'] ?? null,
            ];
        }

        if ($filter === 'archives') {
            $archived = $this->listArchivedForUser($tenantId, $userId, $typesById);
            $counts['archives'] = count($archived);
            $items = $archived;
        }

        usort($items, static function (array $a, array $b): int {
            $pa = (int) ($a['badge']['sort_priority'] ?? 8);
            $pb = (int) ($b['badge']['sort_priority'] ?? 8);
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp((string) ($a['reference'] ?? ''), (string) ($b['reference'] ?? ''));
        });

        return ['counts' => $counts, 'items' => $items];
    }

    /**
     * Synthèse dashboard.
     *
     * @return array{mandatory: int, overdue: int, to_read: int, items: list<array<string, mixed>>}
     */
    public function dashboardSummary(int $tenantId, int $userId, int $limit = 5): array
    {
        $box = $this->forUser($tenantId, $userId, 'a_lire');
        $pending = $this->complianceService->listPendingActionsForUser($tenantId, $userId, $limit);

        return [
            'mandatory' => (int) ($box['counts']['obligatoires'] ?? 0),
            'overdue' => (int) ($box['counts']['en_retard'] ?? 0),
            'to_read' => (int) ($box['counts']['a_lire'] ?? 0),
            'items' => $pending,
        ];
    }

    /**
     * Historique lectures obligatoires pour fiche personnel.
     *
     * @return list<array<string, mixed>>
     */
    public function mandatoryHistoryForUser(int $tenantId, int $userId, int $limit = 40): array
    {
        $published = $this->doctrineRepository->listPublishedForTenant($tenantId);
        $out = [];
        foreach ($published as $row) {
            $documentId = (int) ($row['document_id'] ?? 0);
            $versionId = (int) ($row['version_id'] ?? 0);
            $mandatory = !empty($row['reading_required'])
                || !empty($row['acknowledgment_required'])
                || (string) ($row['requirement_level'] ?? '') === 'mandatory';
            if (!$mandatory || $documentId < 1 || $versionId < 1) {
                continue;
            }
            if (!$this->audienceResolver->isUserInAudience($tenantId, $userId, $documentId, $row)) {
                continue;
            }
            $badge = $this->complianceService->memberBadge($tenantId, $userId, $row, $versionId);
            $ack = $this->acknowledgmentRepository->findForUserVersion($tenantId, $userId, $versionId);
            $out[] = [
                'document_id' => $documentId,
                'reference' => (string) ($row['reference_code'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'version_label' => $this->versionLabel($row),
                'status' => $badge['label'],
                'badge' => $badge['badge'],
                'read_at' => $ack['signed_at'] ?? null,
                'href' => url('documents/doctrine/' . $documentId),
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string) ($b['read_at'] ?? ''), (string) ($a['read_at'] ?? '')));

        return array_slice($out, 0, max(1, $limit));
    }

    private function normalizeFilter(string $filter): string
    {
        $allowed = ['a_lire', 'obligatoires', 'en_retard', 'lus', 'archives'];

        return in_array($filter, $allowed, true) ? $filter : 'a_lire';
    }

    /** @param array<int, array<string, mixed>> $typesById */
    private function listArchivedForUser(int $tenantId, int $userId, array $typesById): array
    {
        // Pas de liste archive dans listPublished — on s’appuie sur le filtre interne si étendu.
        // Pour l’instant : documents obsolètes via recherche statut (si dispo).
        return [];
    }

    /** @param array<string, mixed> $row */
    private function versionLabel(array $row): string
    {
        $label = trim((string) ($row['version_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        if (isset($row['version_major'], $row['version_minor'])) {
            return 'v' . (int) $row['version_major'] . '.' . (int) $row['version_minor'];
        }

        return 'v1.0';
    }
}
