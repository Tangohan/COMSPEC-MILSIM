<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Core\Database;
use App\Repositories\Doctrine\DocumentAcknowledgmentRepository;
use App\Repositories\Doctrine\DocumentAudienceRepository;
use App\Repositories\Doctrine\DocumentDoctrineRepository;
use App\Repositories\Doctrine\DocumentReferenceDomainRepository;
use App\Repositories\Doctrine\DocumentTypeRepository;
use App\Repositories\Doctrine\DocumentViewRepository;
use App\Repositories\DocumentAuditRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentVersionRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use App\Support\Doctrine\DoctrineComplianceStatus;
use App\Support\Doctrine\DoctrineWorkflowStatus;

/**
 * Page de pilotage Doctrine (back-office/doctrine) : tous les documents doctrinaux du tenant avec leur état,
 * leurs versions, leurs destinataires et la progression réelle des lectures et accusés.
 *
 * Les statuts de lecture suivent exactement DocumentComplianceService (même règle que le Suivi de diffusion),
 * calculés ici à partir d’accusés et de lectures chargés document par document.
 */
final class DoctrineHubService
{
    /** Statuts « fait » : le destinataire n’a plus rien à faire sur la version en vigueur. */
    public const DONE = [DoctrineComplianceStatus::ACKNOWLEDGED, DoctrineComplianceStatus::READ];

    public function __construct(
        private DocumentDoctrineRepository $doctrineRepository,
        private DocumentVersionRepository $versionRepository,
        private DocumentAudienceRepository $audienceRepository,
        private DocumentAudienceResolver $audienceResolver,
        private DocumentComplianceService $complianceService,
        private DocumentAcknowledgmentRepository $acknowledgmentRepository,
        private DocumentViewRepository $viewRepository,
        private DocumentAuditRepository $documentAuditRepository,
        private DocumentTypeRepository $typeRepository,
        private DocumentReferenceDomainRepository $domainRepository,
        private DocumentRepository $documentRepository,
        private UserRepository $userRepository,
        private UnitRepository $unitRepository,
        private PersonnelJobRoleRepository $jobRoleRepository,
        private RoleRepository $roleRepository,
        private PersonnelAssignmentRepository $assignmentRepository,
        private AuditService $auditService,
    ) {}

    /**
     * @return array{documents: list<array<string, mixed>>, types: list<array<string, mixed>>, domains: list<array<string, mixed>>, stats: array<string, int>, units: array<int, string>}
     */
    public function overview(int $tenantId): array
    {
        $rows = $this->doctrineRepository->listAllForTenant($tenantId);
        $ctx = $this->context($tenantId);
        $docIds = array_map(static fn (array $r): int => (int) ($r['document_id'] ?? 0), $rows);
        $audiencesByDoc = $this->audienceRepository->listForDocuments($docIds, $tenantId);
        $reminders = $this->lastReminders($tenantId);
        $now = time();

        $documents = [];
        $stats = ['total' => 0, 'published' => 0, 'drafts' => 0, 'review' => 0, 'expired' => 0, 'archived' => 0, 'expected' => 0, 'done' => 0, 'missing' => 0, 'overdue' => 0];
        foreach ($rows as $row) {
            $doc = $this->documentRow($tenantId, $row, $audiencesByDoc[(int) $row['document_id']] ?? [], $ctx, $now);
            $doc['last_reminder_at'] = $reminders[$doc['document_id']] ?? null;
            $documents[] = $doc;

            ++$stats['total'];
            $state = $doc['state'];
            if ($state === 'published' || $state === 'expired') {
                ++$stats['published'];
            }
            if ($state === 'draft') {
                ++$stats['drafts'];
            } elseif ($state === 'review') {
                ++$stats['review'];
            } elseif ($state === 'expired') {
                ++$stats['expired'];
            } elseif ($state === 'archived') {
                ++$stats['archived'];
            }
            if ($doc['tracked']) {
                $stats['expected'] += $doc['recipients_total'];
                $stats['done'] += $doc['done_count'];
                $stats['missing'] += $doc['missing_count'];
                $stats['overdue'] += $doc['overdue_count'];
            }
        }

        $typeCounts = [];
        $domainCounts = [];
        foreach ($documents as $d) {
            $typeCounts[(int) $d['type_id']] = ($typeCounts[(int) $d['type_id']] ?? 0) + 1;
            $domainCounts[(int) $d['domain_id']] = ($domainCounts[(int) $d['domain_id']] ?? 0) + 1;
        }
        $types = [];
        foreach ($this->safe(fn (): array => $this->typeRepository->listForTenant($tenantId, false)) as $t) {
            $id = (int) ($t['id'] ?? 0);
            $types[] = [
                'id' => $id,
                'label' => (string) ($t['label'] ?? ''),
                'prefix' => (string) ($t['code_prefix'] ?? ''),
                'color' => $this->color((string) ($t['color'] ?? '')),
                'active' => !empty($t['is_active']),
                'count' => $typeCounts[$id] ?? 0,
            ];
        }
        $domains = [];
        foreach ($this->safe(fn (): array => $this->domainRepository->listAllForTenant($tenantId)) as $dm) {
            $id = (int) ($dm['id'] ?? 0);
            $domains[] = [
                'id' => $id,
                'label' => (string) ($dm['label'] ?? ''),
                'prefix' => (string) ($dm['doc_prefix'] ?? $dm['code'] ?? ''),
                'color' => $this->color((string) ($dm['color'] ?? '')),
                'count' => $domainCounts[$id] ?? 0,
            ];
        }

        return [
            'documents' => $documents,
            'types' => $types,
            'untyped' => $typeCounts[0] ?? 0,
            'domains' => $domains,
            'undomained' => $domainCounts[0] ?? 0,
            'stats' => $stats,
            'units' => $ctx['unit_names'],
        ];
    }

    /**
     * Une ligne par destinataire et par document publié (export CSV de conformité).
     *
     * @return list<array<string, string>>
     */
    public function complianceRows(int $tenantId, int $documentId = 0): array
    {
        $out = [];
        foreach ($this->overview($tenantId)['documents'] as $doc) {
            if (!$doc['tracked'] || ($documentId > 0 && $doc['document_id'] !== $documentId)) {
                continue;
            }
            foreach ($doc['recipients'] as $r) {
                $out[] = [
                    'reference' => $doc['reference'],
                    'title' => $doc['title'],
                    'version' => $doc['version_label'],
                    'member' => $r['name'],
                    'unit' => $r['unit'],
                    'status' => $r['label'],
                    'first_viewed_at' => (string) ($r['viewed_at'] ?? ''),
                    'signed_at' => (string) ($r['signed_at'] ?? ''),
                    'deadline' => (string) ($doc['deadline'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * Documents publiés dont au moins un destinataire n’a pas lu ou pas confirmé (relance groupée).
     *
     * @return list<int>
     */
    public function documentIdsWithMissingReaders(int $tenantId): array
    {
        $ids = [];
        foreach ($this->overview($tenantId)['documents'] as $doc) {
            if ($doc['tracked'] && $doc['editable'] && $doc['missing_count'] > 0) {
                $ids[] = $doc['document_id'];
            }
        }

        return $ids;
    }

    /**
     * Archive un document doctrinal du tenant : il sort des lectures à faire et de la bibliothèque, son historique reste.
     *
     * @return array{ok: bool, error?: string}
     */
    public function archive(int $tenantId, int $userId, int $documentId): array
    {
        $doctrine = $this->doctrineRepository->findByDocumentId($documentId, $tenantId);
        if ($doctrine === null) {
            return ['ok' => false, 'error' => 'Document introuvable.'];
        }
        $previous = (string) ($doctrine['doctrine_status'] ?? '');
        if ($previous === DoctrineWorkflowStatus::ARCHIVED) {
            return ['ok' => false, 'error' => 'Ce document est déjà archivé.'];
        }
        $this->doctrineRepository->updateByDocumentId($documentId, $tenantId, ['doctrine_status' => DoctrineWorkflowStatus::ARCHIVED]);
        $this->documentRepository->update($documentId, $tenantId, ['status' => 'archived']);
        $this->documentAuditRepository->log($documentId, $userId, 'doctrine_archived', ['status' => $previous], ['status' => DoctrineWorkflowStatus::ARCHIVED]);
        $this->auditService->log(
            'document.archived',
            $tenantId,
            $userId,
            'document',
            $documentId,
            null,
            json_encode(['reference' => $doctrine['reference_code'] ?? null], JSON_UNESCAPED_UNICODE)
        );

        return ['ok' => true];
    }

    /** Fiche lisible d’un état de document (classe d’affichage et libellé). */
    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'draft' => 'Brouillon',
            'review' => 'À valider',
            'published' => 'Publié',
            'expired' => 'Expiré',
            'suspended' => 'Suspendu',
            'archived' => 'Archivé',
            'obsolete' => 'Abrogé',
            default => 'Brouillon',
        };
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $audiences
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    private function documentRow(int $tenantId, array $row, array $audiences, array $ctx, int $now): array
    {
        $documentId = (int) ($row['document_id'] ?? 0);
        $status = (string) ($row['doctrine_status'] ?? DoctrineWorkflowStatus::DRAFT);
        $expiresAt = $this->date($row['expires_at'] ?? null);
        $expiresTs = $expiresAt !== null ? strtotime($expiresAt) : false;
        $state = match ($status) {
            DoctrineWorkflowStatus::PUBLISHED => ($expiresTs !== false && $expiresTs < $now) ? 'expired' : 'published',
            DoctrineWorkflowStatus::REVIEW, DoctrineWorkflowStatus::APPROVAL => 'review',
            DoctrineWorkflowStatus::SUSPENDED => 'suspended',
            DoctrineWorkflowStatus::ARCHIVED => 'archived',
            DoctrineWorkflowStatus::OBSOLETE => 'obsolete',
            default => 'draft',
        };
        $versionId = (int) ($row['version_id'] ?? 0);
        $versionLabel = trim((string) ($row['version_label'] ?? ''));
        if ($versionLabel === '') {
            $versionLabel = 'v' . (int) ($row['version_major'] ?? 1) . '.' . (int) ($row['version_minor'] ?? 0);
        }
        $ackRequired = !empty($row['acknowledgment_required']) || (string) ($row['requirement_level'] ?? '') === 'mandatory';
        $deadline = $this->date($row['acknowledgment_deadline_at'] ?? null);
        $deadlineTs = $deadline !== null ? strtotime($deadline) : false;
        $editable = (int) ($row['tenant_id'] ?? 0) === $tenantId;

        // Destinataires : résolus pour tous les états (prévision pour un brouillon), statut réel seulement une fois publié.
        $recipientIds = $documentId > 0 ? $this->safe(fn (): array => $this->audienceResolver->resolveUserIds($tenantId, $documentId)) : [];
        $tracked = $status === DoctrineWorkflowStatus::PUBLISHED && $versionId > 0;
        $recipients = [];
        $counts = [];
        if ($tracked) {
            $acks = $this->safe(fn (): array => $this->acknowledgmentRepository->listForDocument($tenantId, $documentId));
            $ackCurrent = [];
            $ackLatest = [];
            foreach ($acks as $a) {
                $uid = (int) ($a['user_id'] ?? 0);
                $ackLatest[$uid] ??= $a;
                if ((int) ($a['version_id'] ?? 0) === $versionId) {
                    $ackCurrent[$uid] ??= $a;
                }
            }
            $views = $this->safe(fn (): array => $this->viewRepository->firstViewsForVersion($tenantId, $versionId));
            foreach ($recipientIds as $uid) {
                $uid = (int) $uid;
                $user = $ctx['users'][$uid] ?? null;
                if ($user === null) {
                    continue;
                }
                $code = $this->complianceService->statusFromFacts($row, $versionId, $ackCurrent[$uid] ?? null, $ackLatest[$uid] ?? null, isset($views[$uid]));
                $counts[$code] = ($counts[$code] ?? 0) + 1;
                $recipients[] = [
                    'user_id' => $uid,
                    'name' => $this->userName($user),
                    'unit' => $ctx['unit_names'][$ctx['primary_unit'][$uid] ?? 0] ?? '',
                    'code' => $code,
                    'label' => DoctrineComplianceStatus::label($code),
                    'done' => in_array($code, self::DONE, true),
                    'viewed_at' => $views[$uid] ?? null,
                    'signed_at' => isset($ackCurrent[$uid]) ? (string) ($ackCurrent[$uid]['signed_at'] ?? '') : null,
                ];
            }
            usort($recipients, static fn (array $a, array $b): int => [(int) $a['done'], $a['name']] <=> [(int) $b['done'], $b['name']]);
        }
        $doneCount = 0;
        foreach (self::DONE as $c) {
            $doneCount += $counts[$c] ?? 0;
        }
        $total = $tracked ? count($recipients) : count($recipientIds);

        return [
            'document_id' => $documentId,
            'editable' => $editable,
            'reference' => (string) ($row['reference_code'] ?? ''),
            'title' => (string) ($row['title'] ?? $row['short_title'] ?? ''),
            'summary' => trim((string) ($row['summary'] ?? '')),
            'status' => $status,
            'state' => $state,
            'state_label' => self::stateLabel($state),
            'workflow_label' => DoctrineWorkflowStatus::label($status),
            'type_id' => (int) ($row['document_type_id'] ?? 0),
            'type_label' => (string) ($row['type_label'] ?? ''),
            'type_prefix' => (string) ($row['type_prefix'] ?? ''),
            'type_color' => $this->color((string) ($row['type_color'] ?? '')),
            'domain_id' => (int) ($row['domain_id'] ?? 0),
            'domain_label' => (string) ($row['domain_label'] ?? ''),
            'domain_prefix' => (string) ($row['domain_prefix'] ?? ''),
            'subdomain_label' => (string) ($row['subdomain_label'] ?? ''),
            'issuing_label' => trim((string) ($row['issuing_label'] ?? '')),
            'version_id' => $versionId,
            'version_label' => $versionLabel,
            'published_at' => $this->date($row['version_published_at'] ?? $row['published_at'] ?? null),
            'updated_at' => $this->date($row['updated_at'] ?? $row['document_updated_at'] ?? $row['created_at'] ?? null),
            'effective_at' => $this->date($row['effective_at'] ?? null),
            'expires_at' => $expiresAt,
            'deadline' => $deadline,
            'deadline_state' => $deadlineTs === false ? 'none' : ($deadlineTs < $now ? 'past' : ($deadlineTs < $now + 7 * 86400 ? 'soon' : 'later')),
            'reading_required' => !empty($row['reading_required']),
            'ack_required' => $ackRequired,
            'require_validation' => !empty($row['require_validation']),
            'visibility_mode' => (string) ($row['visibility_mode'] ?? 'library'),
            'confirmation_text' => trim((string) ($row['confirmation_text'] ?? '')),
            'replaces_document_id' => (int) ($row['replaces_document_id'] ?? 0),
            'replaced_by_document_id' => (int) ($row['replaced_by_document_id'] ?? 0),
            'audiences' => $this->audienceLabels($audiences, $ctx),
            'tracked' => $tracked,
            'recipients' => $recipients,
            'recipients_total' => $total,
            'done_count' => $doneCount,
            'missing_count' => $tracked ? max(0, $total - $doneCount) : 0,
            'overdue_count' => $counts[DoctrineComplianceStatus::OVERDUE] ?? 0,
            'status_counts' => $counts,
            'versions' => $this->versions($documentId),
            'history' => $this->history($documentId, $ctx),
        ];
    }

    /**
     * Libellés des destinataires choisis à la publication.
     *
     * @param list<array<string, mixed>> $audiences
     * @param array<string, mixed> $ctx
     * @return list<array{kind: string, id?: int, label: string, level: string, children: bool}>
     */
    private function audienceLabels(array $audiences, array $ctx): array
    {
        if ($audiences === []) {
            return [['kind' => 'all', 'label' => 'Toute la communauté', 'level' => '', 'children' => false]];
        }
        $out = [];
        foreach ($audiences as $a) {
            $type = (string) ($a['audience_type'] ?? '');
            $value = (string) ($a['audience_value'] ?? '');
            $children = !empty($a['include_children']);
            if ($type === 'all_members') {
                $out[] = ['kind' => 'all', 'label' => 'Toute la communauté', 'level' => '', 'children' => false];
            } elseif ($type === 'unit') {
                $unit = $ctx['units'][(int) $value] ?? null;
                $out[] = [
                    'kind' => 'unit',
                    'id' => (int) $value,
                    'label' => $unit !== null ? (string) ($unit['name'] ?? '') : 'Unité supprimée',
                    'level' => $unit !== null ? (string) ($unit['visibility_level'] ?? '') : '',
                    'children' => $children,
                ];
            } elseif ($type === 'job_role') {
                $out[] = ['kind' => 'job', 'id' => (int) $value, 'label' => $ctx['job_roles'][(int) $value] ?? 'Emploi supprimé', 'level' => '', 'children' => false];
            } elseif ($type === 'role') {
                $out[] = ['kind' => 'role', 'label' => $ctx['roles'][$value] ?? $value, 'level' => '', 'children' => false];
            } elseif ($type === 'user') {
                $user = $ctx['users'][(int) $value] ?? null;
                $out[] = ['kind' => 'user', 'label' => $user !== null ? $this->userName($user) : 'Membre parti', 'level' => '', 'children' => false];
            } elseif ($type === 'grade') {
                $out[] = ['kind' => 'grade', 'label' => $value, 'level' => '', 'children' => false];
            }
        }

        return $out;
    }

    /** @return list<array{label: string, published_at: ?string, summary: string, current: bool, reset: bool}> */
    private function versions(int $documentId): array
    {
        $out = [];
        foreach ($this->safe(fn (): array => $this->versionRepository->listForDocument($documentId)) as $v) {
            $label = trim((string) ($v['version_label'] ?? ''));
            if ($label === '') {
                $label = isset($v['version_major'])
                    ? 'v' . (int) $v['version_major'] . '.' . (int) ($v['version_minor'] ?? 0)
                    : 'v' . (int) ($v['version_number'] ?? 1);
            }
            $out[] = [
                'label' => $label,
                'published_at' => $this->date($v['published_at'] ?? $v['created_at'] ?? null),
                'summary' => trim((string) ($v['change_summary'] ?? $v['change_notes'] ?? '')),
                'current' => !empty($v['is_current']),
                'reset' => !empty($v['acknowledgment_reset']),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $ctx
     * @return list<array{label: string, at: string, actor: string}>
     */
    private function history(int $documentId, array $ctx): array
    {
        $labels = [
            'created' => 'Brouillon créé',
            'document_created' => 'Document créé',
            'updated' => 'Brouillon modifié',
            'published' => 'Publié',
            'version_published' => 'Nouvelle version publiée',
            'recipients_notified' => 'Destinataires prévenus',
            'reminder_sent' => 'Relance envoyée',
            'doctrine_acknowledged' => 'Lecture confirmée',
            'doctrine_archived' => 'Archivé',
            'document_archived' => 'Archivé',
        ];
        $out = [];
        foreach ($this->safe(fn (): array => $this->documentAuditRepository->getByDocument($documentId, 40)) as $e) {
            $action = (string) ($e['action'] ?? '');
            // Les confirmations individuelles se lisent dans « Lectures » : l’historique garde les décisions.
            if (!isset($labels[$action]) || $action === 'doctrine_acknowledged') {
                continue;
            }
            $label = $labels[$action];
            $new = json_decode((string) ($e['new_value'] ?? ''), true);
            if (is_array($new)) {
                if (isset($new['version']) && $action === 'version_published') {
                    $label .= ' · ' . (string) $new['version'];
                }
                if (isset($new['count']) && in_array($action, ['recipients_notified', 'reminder_sent'], true)) {
                    $label .= ' · ' . (int) $new['count'] . ' destinataire(s)';
                }
            }
            $actor = $ctx['users'][(int) ($e['user_id'] ?? 0)] ?? null;
            $out[] = [
                'label' => $label,
                'at' => (string) ($e['created_at'] ?? ''),
                'actor' => $actor !== null ? $this->userName($actor) : '',
            ];
            if (count($out) >= 10) {
                break;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function context(int $tenantId): array
    {
        $users = [];
        foreach ($this->safe(fn (): array => $this->userRepository->allForTenant($tenantId)) as $u) {
            $users[(int) ($u['id'] ?? 0)] = $u;
        }
        $units = [];
        $unitNames = [];
        foreach ($this->safe(fn (): array => $this->unitRepository->allForTenant($tenantId)) as $u) {
            $units[(int) $u['id']] = $u;
            $unitNames[(int) $u['id']] = (string) ($u['name'] ?? '');
        }
        $jobRoles = [];
        foreach ($this->safe(fn (): array => $this->jobRoleRepository->listRoleOptionsForSelect($tenantId)) as $jr) {
            $jobRoles[(int) ($jr['id'] ?? 0)] = (string) ($jr['label'] ?? '');
        }
        $roles = [];
        foreach ($this->safe(fn (): array => $this->roleRepository->allForTenant($tenantId)) as $r) {
            $roles[(string) ($r['slug'] ?? '')] = (string) ($r['name'] ?? $r['slug'] ?? '');
        }

        return [
            'users' => $users,
            'units' => $units,
            'unit_names' => $unitNames,
            'primary_unit' => $this->safe(fn (): array => $this->assignmentRepository->primaryUnitIdByUserForTenant($tenantId)),
            'job_roles' => $jobRoles,
            'roles' => $roles,
        ];
    }

    /** @return array<int, string> document_id => date de la dernière relance */
    private function lastReminders(int $tenantId): array
    {
        try {
            $stmt = Database::getPdo()->prepare(
                'SELECT document_id, MAX(created_at) AS last_at FROM document_doctrine_reminders WHERE tenant_id = ? GROUP BY document_id'
            );
            $stmt->execute([$tenantId]);
            $out = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['document_id']] = (string) $r['last_at'];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $user */
    private function userName(array $user): string
    {
        $name = trim((string) ($user['display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        }

        return $name !== '' ? $name : (string) ($user['email'] ?? 'Membre');
    }

    private function date(mixed $raw): ?string
    {
        $s = trim((string) ($raw ?? ''));

        return $s === '' || str_starts_with($s, '0000') ? null : $s;
    }

    private function color(string $raw): string
    {
        $raw = trim($raw);

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $raw) === 1 ? $raw : '';
    }

    /**
     * Les tables doctrinales sont créées par migration : une table absente ne doit pas casser la page.
     *
     * @template T
     * @param callable(): array<array-key, T> $fn
     * @return array<array-key, T>
     */
    private function safe(callable $fn): array
    {
        try {
            $r = $fn();

            return is_array($r) ? $r : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
