<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Repositories\Doctrine\DocumentAudienceRepository;
use App\Repositories\Doctrine\DocumentDoctrineRepository;
use App\Repositories\Doctrine\DocumentTypeRepository;
use App\Repositories\DocumentAuditRepository;
use App\Repositories\DocumentCategoryRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\DocumentVersionRepository;
use App\Repositories\UnitRepository;
use App\Services\Audit\AuditService;
use App\Support\Doctrine\DoctrineWorkflowStatus;
use App\Support\MiniArticleHtml;

/**
 * Création, ciblage, publication et versionnement des documents organisationnels.
 */
final class DoctrinePublicationService
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentVersionRepository $versionRepository,
        private DocumentDoctrineRepository $doctrineRepository,
        private DocumentTypeRepository $typeRepository,
        private DocumentAudienceRepository $audienceRepository,
        private DocumentCategoryRepository $categoryRepository,
        private DoctrineReferenceService $referenceService,
        private UnitRepository $unitRepository,
        private AuditService $auditService,
        private DocumentAuditRepository $documentAuditRepository,
    ) {}

    /**
     * @param array<string, mixed> $input
     * @param list<array{audience_type: string, audience_value: string, include_children?: bool}> $audiences
     * @return array{ok: bool, error?: string, document_id?: int}
     */
    public function createDraft(int $tenantId, int $userId, array $input, array $audiences = []): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'Le titre est obligatoire.'];
        }

        $typeId = (int) ($input['document_type_id'] ?? 0);
        $type = $typeId > 0 ? $this->typeRepository->findById($typeId, $tenantId) : null;

        $categoryId = $this->resolveDoctrineCategoryId($tenantId);
        $slug = MiniArticleHtml::slugify($title) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

        $readingRequired = array_key_exists('reading_required', $input)
            ? !empty($input['reading_required'])
            : !empty($type['default_reading_required']);
        $ackRequired = array_key_exists('acknowledgment_required', $input)
            ? !empty($input['acknowledgment_required'])
            : !empty($type['default_acknowledgment_required']);
        $requireValidation = array_key_exists('require_validation', $input)
            ? !empty($input['require_validation'])
            : !empty($type['default_require_validation']);

        $status = $requireValidation ? DoctrineWorkflowStatus::REVIEW : DoctrineWorkflowStatus::DRAFT;

        $documentId = $this->documentRepository->create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'slug' => $slug,
            'short_description' => trim((string) ($input['object'] ?? $input['short_title'] ?? '')) ?: null,
            'description' => trim((string) ($input['object'] ?? '')) ?: null,
            'document_type' => $type['code'] ?? ($input['document_type'] ?? 'instruction'),
            'document_category_id' => $categoryId,
            'classification_level' => (string) ($input['classification_level'] ?? 'interne'),
            'visibility_scope' => (string) ($input['visibility_scope'] ?? 'private'),
            'owner_user_id' => $userId,
            'author_user_id' => $userId,
            'unit_id' => isset($input['issuing_unit_id']) && (int) $input['issuing_unit_id'] > 0
                ? (int) $input['issuing_unit_id']
                : null,
            'effective_at' => $this->normalizeDateTime($input['effective_at'] ?? null),
            'expires_at' => $this->normalizeDateTime($input['expires_at'] ?? null),
            'status' => 'draft',
            'created_by' => $userId,
            'require_account_signature' => $ackRequired ? 1 : 0,
        ]);

        $bodyHtml = MiniArticleHtml::sanitize((string) ($input['body_html'] ?? ''));
        $versionId = $this->versionRepository->create($documentId, [
            'version_number' => 1,
            'version_major' => 1,
            'version_minor' => 0,
            'file_path' => '',
            'created_by' => $userId,
            'change_notes' => 'Version initiale',
            'change_summary' => 'Création du document',
            'version_label' => 'v1.0',
            'acknowledgment_reset' => 0,
            'body_html' => $bodyHtml !== '' ? $bodyHtml : null,
        ]);
        $this->persistVersionBody($versionId, $bodyHtml);

        $reference = $this->buildReference($tenantId, $type, $input);
        $scopeText = $this->buildScopeOfApplication($tenantId, $audiences, $input);

        $this->doctrineRepository->create([
            'document_id' => $documentId,
            'tenant_id' => $tenantId,
            'scope' => 'tenant',
            'document_type_id' => $typeId > 0 ? $typeId : null,
            'reference_code' => $reference['reference_code'],
            'service_prefix' => $reference['service_prefix'],
            'domain_id' => $reference['domain_id'] ?? null,
            'subdomain_id' => $reference['subdomain_id'] ?? null,
            'domain_code' => $reference['domain_code'],
            'seq_year' => $reference['seq_year'],
            'seq_number' => $reference['seq_number'],
            'short_title' => trim((string) ($input['short_title'] ?? '')) ?: null,
            'summary' => trim((string) ($input['object'] ?? $input['summary'] ?? '')) ?: null,
            'body_html' => $bodyHtml !== '' ? $bodyHtml : null,
            'confirmation_text' => trim((string) ($input['confirmation_text'] ?? '')) ?: null,
            'visibility_mode' => (string) ($input['visibility_mode'] ?? 'recipients_only'),
            'is_permanent' => !empty($input['is_permanent']) ? 1 : 0,
            'require_validation' => $requireValidation ? 1 : 0,
            'reminder_on_publish' => !isset($input['reminder_on_publish']) || !empty($input['reminder_on_publish']) ? 1 : 0,
            'doctrine_status' => $status,
            'requirement_level' => $readingRequired || $ackRequired ? 'mandatory' : 'informative',
            'issuing_authority_type' => (string) ($input['issuing_authority_type'] ?? 'tenant'),
            'issuing_unit_id' => isset($input['issuing_unit_id']) && (int) $input['issuing_unit_id'] > 0
                ? (int) $input['issuing_unit_id']
                : null,
            'issuing_user_id' => $userId,
            'issuing_label' => trim((string) ($input['issuing_label'] ?? '')) ?: null,
            'effective_at' => $this->normalizeDateTime($input['effective_at'] ?? null),
            'expires_at' => !empty($input['is_permanent']) ? null : $this->normalizeDateTime($input['expires_at'] ?? null),
            'acknowledgment_required' => $ackRequired ? 1 : 0,
            'acknowledgment_deadline_at' => $this->normalizeDateTime($input['acknowledgment_deadline_at'] ?? null),
            'reading_required' => $readingRequired ? 1 : 0,
            'include_future_members' => !isset($input['include_future_members']) || !empty($input['include_future_members']) ? 1 : 0,
            'replaces_document_id' => isset($input['replaces_document_id']) && (int) $input['replaces_document_id'] > 0
                ? (int) $input['replaces_document_id']
                : null,
            'keywords_json' => $input['keywords'] ?? null,
            'scope_of_application' => $scopeText,
        ]);

        if ($audiences === []) {
            $audiences = [['audience_type' => 'all_members', 'audience_value' => '*', 'include_children' => 0]];
        }
        $this->audienceRepository->replaceForDocument($documentId, $tenantId, $audiences);

        $this->documentAuditRepository->log($documentId, $userId, 'created', null, [
            'reference' => $reference['reference_code'],
            'type' => $type['label'] ?? null,
        ]);
        $this->auditService->log(
            'document.created',
            $tenantId,
            $userId,
            'document',
            $documentId,
            null,
            json_encode(['reference' => $reference['reference_code']], JSON_UNESCAPED_UNICODE)
        );

        return ['ok' => true, 'document_id' => $documentId];
    }

    /**
     * @param array<string, mixed> $input
     * @param list<array{audience_type: string, audience_value: string, include_children?: bool}>|null $audiences
     * @return array{ok: bool, error?: string}
     */
    public function updateDraft(int $tenantId, int $userId, int $documentId, array $input, ?array $audiences = null): array
    {
        $doctrine = $this->doctrineRepository->findByDocumentId($documentId, $tenantId);
        if ($doctrine === null) {
            return ['ok' => false, 'error' => 'Document introuvable.'];
        }
        $status = (string) ($doctrine['doctrine_status'] ?? '');
        if (!in_array($status, [DoctrineWorkflowStatus::DRAFT, DoctrineWorkflowStatus::REVIEW], true)) {
            return ['ok' => false, 'error' => 'Seul un brouillon ou un document en validation peut être modifié librement. Publiez une nouvelle version pour un document déjà publié.'];
        }

        $title = trim((string) ($input['title'] ?? ''));
        $fields = [];
        if ($title !== '') {
            $fields['title'] = $title;
        }
        if (array_key_exists('object', $input) || array_key_exists('short_description', $input)) {
            $fields['short_description'] = trim((string) ($input['object'] ?? $input['short_description'] ?? '')) ?: null;
            $fields['description'] = $fields['short_description'];
        }
        if (array_key_exists('classification_level', $input)) {
            $fields['classification_level'] = (string) $input['classification_level'];
        }
        if (array_key_exists('effective_at', $input)) {
            $fields['effective_at'] = $this->normalizeDateTime($input['effective_at']);
        }
        if (array_key_exists('expires_at', $input)) {
            $fields['expires_at'] = $this->normalizeDateTime($input['expires_at']);
        }
        if ($fields !== []) {
            $this->documentRepository->update($documentId, $tenantId, $fields);
        }

        $bodyHtml = array_key_exists('body_html', $input)
            ? MiniArticleHtml::sanitize((string) $input['body_html'])
            : null;

        $docFields = [];
        foreach ([
            'short_title', 'summary', 'confirmation_text', 'visibility_mode', 'issuing_label',
            'issuing_authority_type', 'acknowledgment_deadline_at', 'effective_at', 'expires_at',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $docFields[$key] = in_array($key, ['acknowledgment_deadline_at', 'effective_at', 'expires_at'], true)
                    ? $this->normalizeDateTime($input[$key])
                    : (is_string($input[$key]) ? trim($input[$key]) : $input[$key]);
            }
        }
        if (array_key_exists('object', $input)) {
            $docFields['summary'] = trim((string) $input['object']) ?: null;
        }
        if ($bodyHtml !== null) {
            $docFields['body_html'] = $bodyHtml !== '' ? $bodyHtml : null;
            $current = $this->documentRepository->findById($documentId, $tenantId);
            $versionId = (int) ($current['version_id'] ?? 0);
            if ($versionId > 0) {
                $this->persistVersionBody($versionId, $bodyHtml);
            }
        }
        foreach (['reading_required', 'acknowledgment_required', 'is_permanent', 'require_validation', 'reminder_on_publish', 'include_future_members'] as $boolKey) {
            if (array_key_exists($boolKey, $input)) {
                $docFields[$boolKey] = !empty($input[$boolKey]) ? 1 : 0;
            }
        }
        if (array_key_exists('document_type_id', $input)) {
            $docFields['document_type_id'] = (int) $input['document_type_id'] ?: null;
        }
        if (array_key_exists('issuing_unit_id', $input)) {
            $docFields['issuing_unit_id'] = (int) $input['issuing_unit_id'] ?: null;
        }
        if (array_key_exists('replaces_document_id', $input)) {
            $docFields['replaces_document_id'] = (int) $input['replaces_document_id'] ?: null;
        }
        if (!empty($docFields['reading_required']) || !empty($docFields['acknowledgment_required'])
            || (!array_key_exists('reading_required', $docFields) && !empty($doctrine['reading_required']))
            || (!array_key_exists('acknowledgment_required', $docFields) && !empty($doctrine['acknowledgment_required']))) {
            $reading = array_key_exists('reading_required', $docFields)
                ? !empty($docFields['reading_required'])
                : !empty($doctrine['reading_required']);
            $ack = array_key_exists('acknowledgment_required', $docFields)
                ? !empty($docFields['acknowledgment_required'])
                : !empty($doctrine['acknowledgment_required']);
            $docFields['requirement_level'] = ($reading || $ack) ? 'mandatory' : 'informative';
        }

        if ($audiences !== null) {
            $this->audienceRepository->replaceForDocument($documentId, $tenantId, $audiences);
            $docFields['scope_of_application'] = $this->buildScopeOfApplication($tenantId, $audiences, $input);
        }

        if ($docFields !== []) {
            $this->doctrineRepository->updateByDocumentId($documentId, $tenantId, $docFields);
        }

        $this->documentAuditRepository->log($documentId, $userId, 'updated', null, ['fields' => array_keys($input)]);
        $this->auditService->log('document.updated', $tenantId, $userId, 'document', $documentId);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function publish(int $tenantId, int $userId, int $documentId, bool $notify = true): array
    {
        $doctrine = $this->doctrineRepository->findByDocumentId($documentId, $tenantId);
        if ($doctrine === null) {
            return ['ok' => false, 'error' => 'Document introuvable.'];
        }
        $status = (string) ($doctrine['doctrine_status'] ?? '');
        if ($status === DoctrineWorkflowStatus::PUBLISHED) {
            return ['ok' => false, 'error' => 'Ce document est déjà publié. Créez une nouvelle version pour le modifier.'];
        }
        if (!empty($doctrine['require_validation']) && $status === DoctrineWorkflowStatus::DRAFT) {
            return ['ok' => false, 'error' => 'Ce type de document doit d’abord être soumis à validation.'];
        }

        $this->documentRepository->update($documentId, $tenantId, ['status' => 'published']);
        $this->doctrineRepository->updateByDocumentId($documentId, $tenantId, [
            'doctrine_status' => DoctrineWorkflowStatus::PUBLISHED,
            'published_at' => date('Y-m-d H:i:s'),
            'published_by_user_id' => $userId,
        ]);

        $current = $this->documentRepository->findById($documentId, $tenantId);
        $versionId = (int) ($current['version_id'] ?? 0);
        if ($versionId > 0) {
            $this->markVersionPublished($versionId);
        }

        // Chaîne de remplacement
        $replacesId = (int) ($doctrine['replaces_document_id'] ?? 0);
        if ($replacesId > 0) {
            $this->doctrineRepository->updateByDocumentId($replacesId, $tenantId, [
                'replaced_by_document_id' => $documentId,
                'doctrine_status' => DoctrineWorkflowStatus::OBSOLETE,
            ]);
        }

        $this->documentAuditRepository->log($documentId, $userId, 'published', null, [
            'reference' => $doctrine['reference_code'] ?? null,
        ]);
        $this->auditService->log(
            'document.published',
            $tenantId,
            $userId,
            'document',
            $documentId,
            null,
            json_encode(['reference' => $doctrine['reference_code'] ?? null], JSON_UNESCAPED_UNICODE)
        );

        if ($notify && !empty($doctrine['reminder_on_publish'])) {
            $fresh = $this->doctrineRepository->findByDocumentId($documentId, $tenantId) ?? $doctrine;
            $this->notificationService->notifyPublication($tenantId, $userId, $fresh);
        }

        return ['ok' => true];
    }

    /**
     * Nouvelle version d’un document déjà publié.
     *
     * @param array<string, mixed> $input
     * @return array{ok: bool, error?: string, version_id?: int}
     */
    public function publishNewVersion(
        int $tenantId,
        int $userId,
        int $documentId,
        array $input,
        bool $resetAcknowledgments = true,
    ): array {
        $doctrine = $this->doctrineRepository->findByDocumentId($documentId, $tenantId);
        if ($doctrine === null) {
            return ['ok' => false, 'error' => 'Document introuvable.'];
        }
        if ((string) ($doctrine['doctrine_status'] ?? '') !== DoctrineWorkflowStatus::PUBLISHED) {
            return ['ok' => false, 'error' => 'Seuls les documents publiés acceptent une nouvelle version.'];
        }

        $current = $this->documentRepository->findById($documentId, $tenantId);
        $prevMajor = 1;
        $prevMinor = 0;
        if ($current !== null) {
            $versions = $this->versionRepository->listForDocument($documentId);
            foreach ($versions as $v) {
                if (!empty($v['is_current'])) {
                    $prevMajor = (int) ($v['version_major'] ?? 1);
                    $prevMinor = (int) ($v['version_minor'] ?? 0);
                    break;
                }
            }
        }
        $major = !empty($input['bump_major']) ? $prevMajor + 1 : $prevMajor;
        $minor = !empty($input['bump_major']) ? 0 : $prevMinor + 1;

        $bodyHtml = MiniArticleHtml::sanitize((string) ($input['body_html'] ?? ($doctrine['body_html'] ?? '')));
        $nextNum = $this->versionRepository->getNextVersionNumber($documentId);
        $versionId = $this->versionRepository->create($documentId, [
            'version_number' => $nextNum,
            'version_major' => $major,
            'version_minor' => $minor,
            'file_path' => '',
            'created_by' => $userId,
            'change_notes' => trim((string) ($input['change_notes'] ?? '')) ?: null,
            'change_summary' => trim((string) ($input['change_summary'] ?? $input['change_notes'] ?? '')) ?: null,
            'version_label' => 'v' . $major . '.' . $minor,
            'acknowledgment_reset' => $resetAcknowledgments ? 1 : 0,
        ]);
        $this->persistVersionBody($versionId, $bodyHtml);
        $this->markVersionPublished($versionId);

        $docFields = [
            'body_html' => $bodyHtml !== '' ? $bodyHtml : null,
            'published_at' => date('Y-m-d H:i:s'),
            'published_by_user_id' => $userId,
        ];
        if (array_key_exists('confirmation_text', $input)) {
            $docFields['confirmation_text'] = trim((string) $input['confirmation_text']) ?: null;
        }
        if (array_key_exists('summary', $input) || array_key_exists('object', $input)) {
            $docFields['summary'] = trim((string) ($input['object'] ?? $input['summary'] ?? '')) ?: null;
        }
        $this->doctrineRepository->updateByDocumentId($documentId, $tenantId, $docFields);

        if (array_key_exists('title', $input) && trim((string) $input['title']) !== '') {
            $this->documentRepository->update($documentId, $tenantId, [
                'title' => trim((string) $input['title']),
                'status' => 'published',
            ]);
        }

        $this->documentAuditRepository->log($documentId, $userId, 'version_published', null, [
            'version' => 'v' . $major . '.' . $minor,
            'acknowledgment_reset' => $resetAcknowledgments,
        ]);
        $this->auditService->log(
            'document.version_published',
            $tenantId,
            $userId,
            'document',
            $documentId,
            null,
            json_encode([
                'version_id' => $versionId,
                'acknowledgment_reset' => $resetAcknowledgments,
            ], JSON_UNESCAPED_UNICODE)
        );

        if ($resetAcknowledgments && !empty($doctrine['reminder_on_publish'])) {
            $fresh = $this->doctrineRepository->findByDocumentId($documentId, $tenantId) ?? $doctrine;
            $this->notificationService->notifyPublication($tenantId, $userId, $fresh, true);
        }

        return ['ok' => true, 'version_id' => $versionId];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{reference_code: string, service_prefix: string, domain_code: string, seq_year: int, seq_number: int, domain_id?: int|null, subdomain_id?: int|null}
     */
    private function buildReference(int $tenantId, ?array $type, array $input): array
    {
        $manual = strtoupper(trim((string) ($input['reference_code'] ?? '')));
        if ($manual !== '') {
            return [
                'reference_code' => $manual,
                'service_prefix' => $type['code_prefix'] ?? 'DOC',
                'domain_code' => $type['code'] ?? 'GEN',
                'seq_year' => (int) date('Y'),
                'seq_number' => 0,
                'domain_id' => isset($input['domain_id']) ? (int) $input['domain_id'] : null,
                'subdomain_id' => isset($input['subdomain_id']) ? (int) $input['subdomain_id'] : null,
            ];
        }

        $domainId = (int) ($input['domain_id'] ?? 0);
        if ($domainId > 0) {
            $generated = $this->referenceService->generateReference(
                $tenantId,
                $domainId,
                isset($input['subdomain_id']) ? (int) $input['subdomain_id'] : null,
                isset($input['issuing_unit_id']) ? (int) $input['issuing_unit_id'] : null,
            );
            $generated['domain_id'] = $domainId;
            $generated['subdomain_id'] = isset($input['subdomain_id']) ? (int) $input['subdomain_id'] : null;

            return $generated;
        }

        // Numérotation par type : NS-2026-001
        $prefix = strtoupper(trim((string) ($type['code_prefix'] ?? 'DOC')));
        $year = (int) date('Y');
        $seq = $this->doctrineRepository->nextSequenceNumber($tenantId, $prefix, $prefix, $year);
        $pattern = (string) ($type['numbering_pattern'] ?? '{PREFIX}-{YEAR}-{SEQ}');
        $code = str_replace(
            ['{PREFIX}', '{YEAR}', '{SEQ}'],
            [$prefix, (string) $year, str_pad((string) $seq, 3, '0', STR_PAD_LEFT)],
            $pattern
        );

        return [
            'reference_code' => $code,
            'service_prefix' => $prefix,
            'domain_code' => $prefix,
            'seq_year' => $year,
            'seq_number' => $seq,
            'domain_id' => null,
            'subdomain_id' => null,
        ];
    }

    /**
     * @param list<array{audience_type: string, audience_value: string, include_children?: bool|int}> $audiences
     * @param array<string, mixed> $input
     */
    private function buildScopeOfApplication(int $tenantId, array $audiences, array $input): string
    {
        $parts = [];
        if ($audiences === [] || (($audiences[0]['audience_type'] ?? '') === 'all_members')) {
            $parts[] = 'Applicable à : toute l’organisation';
        } else {
            $labels = [];
            foreach ($audiences as $row) {
                $type = (string) ($row['audience_type'] ?? '');
                $value = (string) ($row['audience_value'] ?? '');
                $includeChildren = !empty($row['include_children']);
                if ($type === 'unit') {
                    $unit = $this->unitRepository->findById((int) $value, $tenantId);
                    $name = $unit['name'] ?? ('Unité #' . $value);
                    $labels[] = $name . ($includeChildren ? ' et sous-unités' : '');
                } elseif ($type === 'user') {
                    $labels[] = 'Personnel #' . $value;
                } else {
                    $labels[] = $type . ' : ' . $value;
                }
            }
            if ($labels !== []) {
                $parts[] = 'Applicable à : ' . implode(', ', $labels);
            }
        }
        $parts[] = !empty($input['is_permanent'])
            ? 'Document permanent'
            : 'Prise d’effet : ' . ($this->normalizeDateTime($input['effective_at'] ?? null) ? date('d/m/Y', strtotime((string) $this->normalizeDateTime($input['effective_at']))) : 'immédiate');

        return implode("\n", $parts);
    }

    private function resolveDoctrineCategoryId(int $tenantId): ?int
    {
        $cat = $this->categoryRepository->findBySlug('doctrine', $tenantId);
        if ($cat !== null) {
            return (int) $cat['id'];
        }
        $all = $this->categoryRepository->listForTenant($tenantId);
        foreach ($all as $c) {
            if (stripos((string) ($c['name'] ?? ''), 'doctrine') !== false
                || stripos((string) ($c['slug'] ?? ''), 'doctrine') !== false) {
                return (int) $c['id'];
            }
        }

        return $all[0]['id'] ?? null;
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);

        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    private function persistVersionBody(int $versionId, string $bodyHtml): void
    {
        if ($versionId < 1) {
            return;
        }
        try {
            $pdo = \App\Core\Database::getPdo();
            $stmt = $pdo->prepare('UPDATE document_versions SET body_html = ? WHERE id = ?');
            $stmt->execute([$bodyHtml !== '' ? $bodyHtml : null, $versionId]);
        } catch (\Throwable) {
            // colonne absente sur ancien schéma
        }
    }

    private function markVersionPublished(int $versionId): void
    {
        try {
            $pdo = \App\Core\Database::getPdo();
            $stmt = $pdo->prepare('UPDATE document_versions SET published_at = NOW() WHERE id = ?');
            $stmt->execute([$versionId]);
        } catch (\Throwable) {
        }
    }
}
